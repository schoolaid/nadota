# Lookup Fields and Scoped Options

A `Lookup` field appears in a form, is validated, and is **never written to the
database**. Its only job is to hold a value that other fields use to narrow their
own options — a grade that filters the list of students, a campus that filters the
list of classrooms.

## Quick start

```php
use SchoolAid\Nadota\Http\Fields\Lookup;
use SchoolAid\Nadota\Http\Fields\Relations\BelongsTo;

public function fields(NadotaRequest $request): array
{
    return [
        Lookup::make('Grade', 'grade')
            ->resource(GradeResource::class)
            ->searchable()
            ->help('Narrows the students you can pick'),

        BelongsTo::make('Student', 'student', StudentResource::class)
            ->scopedBy('grade', 'grade_id'),
    ];
}
```

Picking a grade re-fetches the student options, constrained to that grade. The
grade itself never reaches the model.

## The Lookup field

| Behaviour | Detail |
| --------- | ------ |
| Persistence | Never. The field is virtual and skips fill entirely. |
| Visibility | Forms only. There is no stored value to show on index or detail. |
| Options | Served by the field options endpoint, from the Resource given to `->resource()`. Search, ordering and `displayLabel()` all work as they do for relation fields. |
| Validation | Applies as usual. A `->required()` Lookup makes store and update require the value, even though nothing is written with it. |

Any field can play the same role: `Select::make('Grade', 'grade')->virtual()->options([...])`
works as a scope source with static options. `Lookup` is the Resource-backed variant.

> **`optionsUrl` is null without a resource argument.** `getOptionsUrl()` needs the owning
> resource to build the route, so serializing a field with `toArray($request)` and no third
> argument yields `"optionsUrl": null` — for `Lookup` and for relation fields alike. This is a
> property of how the field was serialized, not a sign that the field exposes no endpoint. It is
> also why action and attach dialogs cannot use scoped options at all; see
> [Integrating Scoped Options](../guides/scoped-options.md#limitations).

## Scoping options with `scopedBy()`

```php
public function scopedBy(
    string $field,
    string|Closure|null $target = null,
    bool $optional = false
): static
```

Available on every field.

- `$field` — the observed field's key in the form.
- `$target` — a column on the related model, or `fn(Builder $query, mixed $value): Builder`.
  Defaults to the observed field name in snake_case with an `_id` suffix.
- `$optional` — when `false` (the default), an observed field with no value makes the
  options query return nothing.

```php
// Column by convention: 'grade' => 'grade_id'
->scopedBy('grade')

// Explicit column
->scopedBy('grade', 'current_grade_id')

// Closure, for a link through an intermediate relation
->scopedBy('grade', fn ($query, $value) => $query->whereHas(
    'enrollments', fn ($q) => $q->where('grade_id', $value)
))

// Optional: with no value, it does not narrow anything
->scopedBy('campus', 'campus_id', optional: true)

// Several scopes are applied as AND
->scopedBy('grade', 'grade_id')->scopedBy('campus', 'campus_id')
```

Scopes are keyed by the observed field. Declaring one twice on the same field
replaces the earlier declaration.

Declaring a scope also registers the observed field with `dependsOn()` and turns on
`clearOnDependencyChange()`, so changing the grade clears the selected student
instead of submitting a stale one. Note that `clearOnChange` is a single flag on the
field: it applies to every dependency of that field, not just the scoped one.

## Cascading further

Because `scopedBy()` lives on the base field, a `Lookup` can itself be scoped by
another one:

```php
Lookup::make('Level', 'level')->resource(LevelResource::class),

Lookup::make('Grade', 'grade')
    ->resource(GradeResource::class)
    ->scopedBy('level', 'level_id'),

BelongsTo::make('Student', 'student', StudentResource::class)
    ->scopedBy('grade', 'grade_id'),
```

## Wire format

The field's payload tells the client which values to send with its options request:

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

The client sends each value under `scope[<field>]`:

```
GET /nadota-api/enrollments/resource/field/student/options?scope[grade]=5&search=ana
```

`optional` is included so the client can disable the dependent control until the
observed field has a value.

## Why not `filters[]`

The generic `filters[]` parameter turns a scalar into `where(column, 'like', '%5%')`,
which for a foreign key also matches 5, 15, 51 and 105. It also accepts any column
name from the client unless the target resource defines `getAllowedOptionsFilters()`.

Option scopes are the opposite: the column comes from the server-side declaration and
the request only supplies the value, compared with `=` (or `whereIn` for arrays). Any
`scope[]` key the field did not declare is ignored.

## What scopes do not do

Scopes constrain the **options query only**. They are not enforced on store or update:
a request that posts a student from another grade is saved. `clearOnDependencyChange`
covers the realistic mistake — a stale selection after changing the grade — but if you
need a hard guarantee, add your own validation rule on the relation field.
