# Integrating Scoped Options

This guide is for the two applications that consume Nadota: the **Laravel API app** that defines resources, and the **frontend app** that renders fields. It covers what each one must do to adopt `Lookup` fields and `scopedBy()`.

For the field-level reference, see [Lookup Fields and Scoped Options](../fields/lookup.md). This page is about rollout.

## What the package now provides

| Piece | Summary |
| ----- | ------- |
| `Lookup` field | A form-only field, virtual from its constructor. Never persisted, never contributes a SELECT column, never appears on index or detail. Its options come from a Nadota Resource. Field type `lookup`, default component `FieldLookup`. |
| `Field::scopedBy()` | Available on every field. Narrows that field's options query using the current value of another field in the same form. |
| `scope[]` request parameter | Honoured by all three options code paths: the strategy pipeline, the paginated endpoint, and the morph-options endpoint. |

The whole feature affects the **options query only**. Nothing about persistence, validation or the store/update pipeline changed, and no existing field behaves differently unless it declares a scope.

## Backend: the Laravel API app

### 1. Upgrade the dependency

```bash
composer require schoolaid/nadota:^1.3
```

### 2. Merge the new config key

Only needed if you published `config/nadota.php`. Add the `lookup` entry alongside the other selection fields:

```php
'lookup' => [
    'type' => 'lookup',
    'component' => 'FieldLookup'
],
```

Point `component` at whatever name your frontend registers the control under. If you never published the config, the packaged default applies and there is nothing to do.

### 3. Declare the fields in your resources

```php
use SchoolAid\Nadota\Http\Fields\Lookup;
use SchoolAid\Nadota\Http\Fields\Relations\BelongsTo;

public function fields(NadotaRequest $request): array
{
    return [
        Lookup::make('Grade', 'grade')
            ->resource(GradeResource::class)
            ->searchable(),

        BelongsTo::make('Student', 'student', StudentResource::class)
            ->scopedBy('grade', 'grade_id'),
    ];
}
```

When the link is not a direct column on the related model — the common case in an enrolment-shaped schema — use the closure form:

```php
BelongsTo::make('Student', 'student', StudentResource::class)
    ->scopedBy('grade', fn ($query, $value) => $query->whereHas(
        'enrollments', fn ($q) => $q->where('grade_id', $value)
    ));
```

### 4. Decide about validation

Scopes constrain the options list; they are **not** enforced when the form is submitted. A request that posts a student from another grade is saved. If you need a hard guarantee, add your own rule on the relation field — the package deliberately does not:

```php
BelongsTo::make('Student', 'student', StudentResource::class)
    ->scopedBy('grade', 'grade_id')
    ->rules([
        Rule::exists('students', 'id')->where(
            fn ($q) => $q->where('grade_id', request('grade'))
        ),
    ]);
```

Also note that a `Lookup` takes part in validation like any virtual field: marking one `->required()` makes store and update require the value even though nothing is written with it.

## Frontend: the client app

This is where the real work is. The backend serializes a contract; nothing renders it yet.

### 1. Register the `FieldLookup` component

Register a control under the name in `nadota.fields.lookup.component` (default `FieldLookup`). Behaviourally it is a remote-options select: it fetches from `optionsUrl`, supports search, and holds a value that is submitted with the form but that the backend will not persist.

It needs no special rendering — an existing remote select can be reused. What is new is the scope wiring below, which applies to **every** field, not just `Lookup`.

### 2. Read the scope declaration from the field payload

A field that declares scopes serializes them under its dependencies:

```json
{
  "key": "student",
  "optionsUrl": "/nadota-api/enrollments/resource/field/student/options",
  "dependencies": {
    "fields": ["grade"],
    "options": {
      "scope": [
        { "field": "grade", "optional": false }
      ]
    },
    "clearOnChange": true
  }
}
```

`dependencies.options.scope` may sit alongside the pre-existing `cascadeFrom` / `endpoint` keys, so read it without assuming it is the only key under `options`.

### 3. Send the values as `scope[<field>]`

When fetching options for a field that declares scopes, attach the current form value of each observed field:

```
GET /nadota-api/enrollments/resource/field/student/options?scope[grade]=5&search=ana
```

The paginated variant is the same URL with `/paginated` appended, and takes the same parameter:

```
GET /nadota-api/enrollments/resource/field/student/options/paginated?scope[grade]=5&page=2
```

Send **only** the declared keys. Anything else is ignored server-side, so extra keys are harmless but pointless. Send the raw value; the backend compares it exactly and uses it only as a bound parameter, never as a column name — the column comes from the server-side declaration.

Array values work and become an `IN (…)` filter: `scope[grade][]=5&scope[grade][]=7`.

### 4. Honour `optional`

- `"optional": false` (the default) — the field cannot be resolved until the observed field has a value. The backend returns an **empty list** in that state. Disable the control and skip the request entirely rather than showing an empty dropdown with no explanation.
- `"optional": true` — the scope simply does not narrow anything while empty. Fetch normally.

### 5. Refetch and clear on dependency change

`dependencies.fields` already tells you which fields to observe; that mechanism exists today for `dependsOn`. Two additions:

- When an observed field changes, **refetch** the dependent field's options with the new `scope[]` values.
- Honour `clearOnChange`, which `scopedBy()` turns on automatically. Without it, the user picks a student, changes the grade, and submits a student from the previous grade — the exact mistake the feature exists to prevent. Note that `clearOnChange` is a single flag for the whole field: it applies to every dependency of that field, not only the scoped ones.

Respect `debounce` if present, as with any other dependency-driven refetch.

### 6. Cascades

Because `scopedBy()` lives on the base field, a `Lookup` can itself be scoped by another one. If your implementation is generic — read the declaration, send the values, refetch on change — chains of any depth work with no extra code:

```php
Lookup::make('Level', 'level')->resource(LevelResource::class),

Lookup::make('Grade', 'grade')
    ->resource(GradeResource::class)
    ->scopedBy('level', 'level_id'),

BelongsTo::make('Student', 'student', StudentResource::class)
    ->scopedBy('grade', 'grade_id'),
```

Build it generically rather than special-casing `Lookup`.

## Rollout order

The two sides are independent and the backend is backward compatible, so ship in this order:

1. **Release the package.** Nothing changes for existing resources; no field behaves differently until it declares a scope.
2. **Ship the frontend scope wiring**, before any resource declares a scope. With no `scope` key in a field's payload, the new code is inert.
3. **Add `Lookup` and `scopedBy()` to one resource** and verify end to end.
4. Roll out to the remaining resources.

Reversing steps 2 and 3 leaves a window where a strict scope is declared but the client sends no value — and the backend correctly returns an empty list, which users will read as a broken dropdown.

## Verifying it works

| Check | Expected |
| ----- | -------- |
| Open the form without touching the lookup | The dependent control is disabled and no options request fires. |
| Pick a value in the lookup | The dependent control enables and fetches with `scope[…]` in the query string. |
| Inspect the returned list | Only records matching the scope. Verify with an id that is a substring of another (`5` against `15` and `51`) — a correct implementation returns only the exact match. |
| Change the lookup value | The dependent field's selection clears and its options refetch. |
| Submit the form | The lookup's value is absent from the persisted record. |
| Search inside the dependent field | Results are narrowed by both the scope and the search term. |
| Call the endpoint with a scope key the field does not declare | It is ignored; the list is unaffected. |

## See Also

- [Lookup Fields and Scoped Options](../fields/lookup.md) — the field reference
- [Conditional Field Dependencies](../fields/depends-on.md) — the wider `dependsOn` contract this builds on
- [Relation Fields](../fields/relation-fields.md) — the options endpoints and their other parameters
