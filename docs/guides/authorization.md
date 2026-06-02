# Authorization

Nadota authorizes every resource action through Laravel policies, with optional granular control per relationship field and per individual field. This guide covers resource-level policy integration, the granular attach/detach system, and how field-level visibility interacts with it.

## How authorization works

Every authorization check flows through one method on the resource:

```php
$resource->authorizedTo($request, $action, $model, $context);
```

That delegates to `ResourceAuthorizationService`, which resolves the Laravel policy for the model and calls the matching ability. The logic is:

1. Resolve the policy with `Gate::getPolicyFor($model)`.
2. If a `$context['field']` is present, try a **field-specific** method first (e.g. `attachGrades`).
3. Otherwise (or as fallback) try the **generic** ability method (e.g. `attach`).
4. If no policy exists, or the policy has no matching method, **authorization passes** (permissive fallback).

```php
// src/Http/Services/ResourceAuthorizationService.php
public function authorizedTo(NadotaRequest $request, string $action, array $context = []): bool
{
    $gate = Gate::getPolicyFor($this->model);

    if (!is_null($gate)) {
        if (!empty($context['field'])) {
            $fieldSpecificAction = $action . ucfirst($context['field']);
            if (method_exists($gate, $fieldSpecificAction)) {
                return Gate::forUser($request->user())->allows($fieldSpecificAction, [$this->model, $context]);
            }
        }

        if (method_exists($gate, $action)) {
            return Gate::forUser($request->user())->allows(
                $action,
                empty($context) ? $this->model : [$this->model, $context]
            );
        }
    }

    return true; // permissive fallback
}
```

> The permissive fallback is convenient for prototyping but means **a resource with no policy is fully open**. Always define a policy in production.

Inside HTTP requests, the `AuthorizesResources` trait provides `authorized($action, $model)`, which calls `authorizedTo()` and `abort(403)` on failure. It also exposes `validateResource()`, which `abort(404)`s when the resource key is unknown.

## Resource-level policies

Create a standard Laravel policy for the model and register it as usual. Nadota maps each action to a policy ability of the same name:

```php
class PostPolicy
{
    public function viewAny(User $user): bool   { return true; }
    public function view(User $user, Post $post): bool   { return true; }
    public function create(User $user): bool    { return $user->isEditor(); }
    public function update(User $user, Post $post): bool { return $user->id === $post->author_id; }
    public function delete(User $user, Post $post): bool { return $user->isAdmin(); }
    public function restore(User $user, Post $post): bool { return $user->isAdmin(); }
    public function forceDelete(User $user, Post $post): bool { return $user->isSuperAdmin(); }
    public function attach(User $user, Post $post): bool { return $user->isEditor(); }
    public function detach(User $user, Post $post): bool { return $user->isEditor(); }
}
```

### Supported actions

These are the action names Nadota passes to `authorizedTo()`:

| Action | Triggered by | Policy receives |
|---|---|---|
| `view` | Show / per-record permissions | `($user, $model)` |
| `update` | Edit / update | `($user, $model)` |
| `create` | Store | `($user)` |
| `delete` | Destroy (soft or hard) | `($user, $model)` |
| `restore` | Restore a trashed record | `($user, $model)` |
| `forceDelete` | Permanent delete | `($user, $model)` |
| `attach` | Attach / sync a relation | `($user, $model[, $context])` |
| `detach` | Detach a relation | `($user, $model[, $context])` |

`create` is checked before a model exists, so the model is passed by class, and the ability receives only the user.

## Per-record permission matrix

For each record returned by the index, Nadota builds a `permissions` block via `getPermissionsForResource()`. This combines the **policy** result with **per-record `can*` methods** and **global `$can*` flags** on the resource:

```php
[
    'view'        => authorizedTo('view'),
    'update'      => authorizedTo('update'),
    'delete'      => canDelete($model) && authorizedTo('delete'),
    'forceDelete' => canForceDelete($model) && authorizedTo('forceDelete') && hasSoftDeleteColumn,
    'restore'     => canRestore($model) && authorizedTo('restore') && hasSoftDeleteColumn && isTrashed,
    'attach'      => authorizedTo('attach'),
    'detach'      => authorizedTo('detach'),
    'fields'      => [ /* per-field attach/detach permissions */ ],
]
```

The `can*` methods let you add per-record business rules **on top of** the policy. They default to the global `$canDelete` / `$canForceDelete` / `$canRestore` properties:

```php
class InvoiceResource extends Resource
{
    public string $model = Invoice::class;

    public function canDelete(Model $model, NadotaRequest $request): bool
    {
        return $model->status !== 'paid'; // also AND-ed with the policy's delete()
    }
}
```

The final decision is always `can*() AND authorizedTo()`. To disable a destructive action for the whole resource regardless of policy, set the property:

```php
protected bool $canForceDelete = false;
```

`forceDelete` and `restore` additionally require the model to have a `deleted_at` column, and `restore` requires the record to actually be trashed.

## Granular per-field authorization (attach / detach)

When a resource has attachable relationship fields (`BelongsToMany`, `HasMany`, `MorphMany`, `MorphToMany` marked `->attachable()`), the same `attach`/`detach`/`sync` policy ability applies to **all** of them by default. To control them individually, define **field-specific** policy methods named `{action} + Ucfirst(fieldKey)`:

```php
class SchoolPolicy
{
    // Generic fallback — applies to any attachable field without a specific method
    public function attach(User $user, School $school, array $context = []): bool
    {
        return $user->school_id === $school->id;
    }

    // Only super admins may attach the "grades" relation
    public function attachGrades(User $user, School $school, array $context = []): bool
    {
        return $user->isSuperAdmin();
    }

    // Regular users may attach "students" to their own school
    public function attachStudents(User $user, School $school, array $context = []): bool
    {
        return $user->isSuperAdmin() || $user->school_id === $school->id;
    }
}
```

Method naming:

| Field key | Attach method | Detach method |
|---|---|---|
| `grades` | `attachGrades()` | `detachGrades()` |
| `students` | `attachStudents()` | `detachStudents()` |
| `schoolYears` | `attachSchoolYears()` | `detachSchoolYears()` |

### The `$context` argument

Field-specific methods receive a third `array $context` parameter describing the operation:

```php
[
    'field'     => 'grades',          // the field key
    'items'     => [1, 2, 3],         // IDs being attached/detached
    'pivot'     => ['active' => true],// pivot data (attach/sync only)
    'detaching' => true,              // sync only: whether missing items are detached
]
```

This lets you validate counts, protect specific IDs, or inspect pivot data:

```php
public function attachStudents(User $user, School $school, array $context = []): bool
{
    if ($user->isSuperAdmin()) {
        return true;
    }

    $items = $context['items'] ?? [];

    // Cap regular users at 10 attachments per call
    if (count($items) > 10) {
        return false;
    }

    return $user->school_id === $school->id;
}
```

> `sync` uses the `attach` ability. The `$context['detaching']` flag is present only for sync, so you can branch on it inside `attach()` if you need different rules.

### Resolution order

1. Field-specific method (`attachGrades`) — used if it exists.
2. Generic method (`attach`) — fallback.
3. No method / no policy — permissive (returns `true`).

The per-field results appear in the index `permissions.fields` block:

```php
'fields' => [
    'grades'   => ['attach' => false, 'detach' => false],
    'students' => ['attach' => true,  'detach' => true],
]
```

This is computed by `getFieldPermissions()`, which iterates the flattened fields and includes only those whose field reports `isAttachable() === true`.

See [the attachments guide](attachments.md) for the attach/detach/sync endpoints themselves.

## Field-level visibility (not authorization)

Visibility controls **which fields render** in each view; it is independent of policy authorization. Each field uses the `VisibilityTrait`:

| Method | Effect |
|---|---|
| `showOnIndex` / `showOnDetail` / `showOnCreation` / `showOnUpdate` | Enable a view; accepts a bool or `callable(request, resource)`. |
| `hideFromIndex` / `hideFromDetail` / `hideFromCreation` / `hideFromUpdate` | Hide from a view. |
| `onlyOnIndex` / `onlyOnDetail` / `onlyOnForms` | Restrict to a single context. |
| `exceptOnForms` | Show everywhere except create/update. |
| `hideWhen(callable)` / `showWhen(callable)` / `onlyWhen(callable)` | Conditional visibility evaluated per request. |

```php
Currency::make('Salary', 'salary')
    ->onlyWhen(fn ($request) => $request->user()->isHr())
    ->hideFromIndex();
```

`hideWhen` is evaluated first (returns false → hidden), then `showWhen` (false → hidden), then the per-view flag. For gating an entire resource behind a callback, use the resource-level `canSee()` (see [resources](resources.md#visibility-of-the-whole-resource)).

> Visibility hides fields from the payload; it is not a security boundary on its own. Sensitive write operations are still governed by the policy via `create`/`update` abilities and field validation rules.

## Testing policies

```php
it('authorizes attach based on field', function () {
    Gate::policy(School::class, SchoolPolicy::class);

    $allowed = $resource->authorizedTo($request, 'attach', $school, [
        'field' => 'grades',
        'items' => [1, 2, 3],
    ]);

    expect($allowed)->toBeTrue();
});
```

## Related guides

- [Resources](resources.md)
- [Fields](../fields/README.md)
- [Attachments](attachments.md)
- [Lifecycle hooks](lifecycle-hooks.md)
- [Soft deletes](soft-deletes.md)
