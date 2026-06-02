# Roadmap: Store/Update System Improvement

> **Status (v1.1.4): Mostly implemented.** Based on the current code in
> [`src/Http/Services/AbstractResourcePersistService.php`](../../src/Http/Services/AbstractResourcePersistService.php),
> [`src/Http/Services/ResourceStoreService.php`](../../src/Http/Services/ResourceStoreService.php) and
> [`src/Http/Services/ResourceUpdateService.php`](../../src/Http/Services/ResourceUpdateService.php),
> phases 1–4 are fully delivered: the shared `ProcessesFields` trait, the
> `AbstractResourcePersistService` Template Method base class, `afterSave()` support in
> store (so `BelongsToMany` / `MorphToMany` work on create), and the `beforeStore` /
> `afterStore` / `beforeUpdate` / `afterUpdate` resource hooks. Phases 5–8 remain
> **optional / pending**: there is no dedicated `FieldProcessor`, `ValidationRulesBuilder`
> or `TransactionManager` class (that logic lives inline in the abstract service), file
> rollback is not implemented, and the dedicated test suite for these services is not yet
> in place.

This document describes the implementation plan to improve the `ResourceStoreService` and
`ResourceUpdateService` services.

## Current State Analysis

### Identified Problems

| Problem | Severity | Impact |
|---------|----------|--------|
| Duplicated code between Store and Update | High | Reduced maintainability |
| Repeated validation logic | High | Potential inconsistencies |
| Hardcoded special handling (`instanceof`) | Medium | Hard to extend |
| No `afterSave` support in Store | High | `BelongsToMany` does not work on create |
| Inconsistent `fill` across fields | Medium | Unpredictable behavior |
| No `beforeStore` / `afterStore` hooks | Medium | Limited extensibility |
| Inline `MorphTo` handling is complex | Medium | Hard-to-follow code |

### Original Architecture

```
ResourceStoreService                ResourceUpdateService
├── Filter fields                   ├── Filter fields
├── Build validation rules          ├── Build validation rules (with :id)
├── Validate request                ├── Validate request
├── Collect attributes              ├── Collect attributes
├── Begin transaction               ├── Store original data
├── Process fields                  ├── Begin transaction
│   ├── File.fill()                 ├── Process fields
│   ├── MorphTo.fill()              │   ├── File.fill()
│   └── Default: resolveForStore    │   ├── MorphTo.fill()
├── model.save()                    │   └── Default: resolveForUpdate
├── Track action event              ├── model.save()
└── Commit                          ├── Track action event
                                    └── Commit
```

### Fields With Custom `fill()`

| Field | `fill()` behavior |
|-------|-------------------|
| Field (base) | Assigns request value to the attribute |
| File | Uploads file and assigns path |
| MorphTo | Assigns type and id from the request |
| Json | Decodes JSON and respects model casts |
| ArrayField | Similar to Json |
| KeyValue | Similar to Json |
| BelongsToMany | Empty (uses `afterSave`) |
| HasMany | Empty (does not apply on store/update) |
| MorphToMany | Empty (uses `afterSave`) |
| MorphedByMany | Empty (uses `afterSave`) |

---

## Phase 1: Base Contract and Shared Trait — Delivered

### 1.1 Create Field Filling Contract
- [x] Create `FillableFieldInterface`
- [x] Define methods: `fill()`, `afterSave()`, `beforeSave()`, `supportsAfterSave()`

### 1.2 Create Trait for Shared Logic
- [x] Create `ProcessesFields` trait
- [x] Move field filtering logic
- [x] Move validation rule building logic
- [x] Move attribute collection logic
- [x] Method to replace the `:id` placeholder

**Files:**
- `src/Http/Fields/Contracts/FillableFieldInterface.php`
- `src/Http/Services/Traits/ProcessesFields.php`

---

## Phase 2: Abstract Base Service — Delivered

### 2.1 Create `AbstractResourcePersistService`
- [x] Extract common logic from Store and Update
- [x] Template Method pattern for the flow
- [x] Abstract methods for the specific differences

```php
abstract class AbstractResourcePersistService
{
    use ProcessesFields, TracksActionEvents;

    abstract protected function getModel(NadotaRequest $request, ResourceInterface $resource, $id): Model;
    abstract protected function getAuthorizationAction(): string;
    abstract protected function filterFields(ResourceInterface $resource, NadotaRequest $request): Collection;
    abstract protected function trackAction(Model $model, NadotaRequest $request, array $data, ?array $original = null): void;
    abstract protected function getSuccessMessage(): string;
    abstract protected function getSuccessStatusCode(): int;

    public function handle(NadotaRequest $request, $id = null): JsonResponse
    {
        // Template method implementation
    }
}
```

### 2.2 Refactor `ResourceStoreService`
- [x] Extend `AbstractResourcePersistService`
- [x] Implement the specific abstract methods
- [x] Maintain backward compatibility

### 2.3 Refactor `ResourceUpdateService`
- [x] Extend `AbstractResourcePersistService`
- [x] Implement the specific abstract methods
- [x] Maintain backward compatibility

**Files:**
- `src/Http/Services/AbstractResourcePersistService.php`
- `src/Http/Services/ResourceStoreService.php` (modified)
- `src/Http/Services/ResourceUpdateService.php` (modified)

---

## Phase 3: `afterSave` Support in Store — Delivered

### 3.1 Implement `afterSave` in the Flow
- [x] Call `afterSave()` on fields that support it
- [x] Order: `fill()` → `save()` → `afterSave()`
- [x] Pass the already-persisted model

### 3.2 Update Relation Fields
- [x] Verify `BelongsToMany.afterSave()` works on create
- [x] Verify `MorphToMany.afterSave()` works on create
- [x] Add `afterSave` to `MorphedByMany` if needed

**Files:**
- `src/Http/Services/AbstractResourcePersistService.php` (modified)
- `src/Http/Fields/Relations/BelongsToMany.php` (verified)
- `src/Http/Fields/Relations/MorphToMany.php` (verified)

---

## Phase 4: Resource Hooks — Delivered

### 4.1 Before/After Hooks for Store
- [x] `beforeStore(Model $model, NadotaRequest $request): void`
- [x] `afterStore(Model $model, NadotaRequest $request): void`

### 4.2 Before/After Hooks for Update
- [x] `beforeUpdate(Model $model, NadotaRequest $request): void`
- [x] `afterUpdate(Model $model, NadotaRequest $request, array $originalData): void`

### 4.3 Integration in the Services
- [x] Call hooks from `AbstractResourcePersistService`
- [x] Document the execution order

**Files:**
- `src/Resource.php` (modified — hooks defined around lines 845–885)
- `src/Http/Services/AbstractResourcePersistService.php` (modified)

---

## Phase 5: FieldProcessor Service — Pending (optional)

> The fill / `beforeSave` / `afterSave` logic currently lives inline in
> `AbstractResourcePersistService` (`processFields()`, `fillField()`, `processAfterSave()`).
> A dedicated `FieldProcessor` class was never extracted, and `instanceof` checks for
> `BelongsTo` / `File` / `MorphTo` remain in `fillField()`.

### 5.1 Create FieldProcessor
- [ ] Centralize field-processing logic
- [ ] `processForStore()` method
- [ ] `processForUpdate()` method
- [ ] Unified handling of special fields

### 5.2 Remove `instanceof` Checks
- [ ] Use polymorphism instead of `instanceof`
- [ ] Each field decides how it fills itself
- [ ] Support for fields needing pre/post processing

```php
class FieldProcessor
{
    public function process(
        Collection $fields,
        Request $request,
        Model $model,
        ResourceInterface $resource,
        array $validatedData,
        string $operation // 'store' | 'update'
    ): void {
        $fields->each(fn($f) => $f->beforeSave($request, $model, $operation));
        $fields->each(fn($f) => $this->fillField($f, $request, $model, $resource, $validatedData, $operation));
    }

    public function afterSave(Collection $fields, Request $request, Model $model): void
    {
        $fields
            ->filter(fn($f) => $f->supportsAfterSave())
            ->each(fn($f) => $f->afterSave($request, $model));
    }
}
```

**Files:**
- `src/Http/Services/FieldProcessor.php`
- `src/Http/Services/AbstractResourcePersistService.php` (modified)

---

## Phase 6: Improved Validation — Pending (optional)

> Validation rules are still built inline via `ProcessesFields::buildValidationRules()`.
> No standalone `ValidationRulesBuilder` class exists.

### 6.1 ValidationRulesBuilder
- [ ] Create a dedicated class to build rules
- [ ] Support fields with multiple attributes (`MorphTo`)
- [ ] Placeholder replacement (`:id`, `:model`, etc.)
- [ ] Conditional rules per operation

### 6.2 Custom Error Messages
- [ ] Allow custom messages per field
- [ ] `getValidationMessages()` method on Field
- [ ] Translation support

**Files:**
- `src/Http/Services/ValidationRulesBuilder.php`
- `src/Http/Fields/Field.php` (modified — add `getRulesFor`, `getValidationMessages`)

---

## Phase 7: Improved Transactions and Rollback — Pending (optional)

> The flow already wraps everything in a DB transaction with rollback on exception,
> but uploaded files are not cleaned up on rollback and there is no `TransactionManager`.

### 7.1 File Rollback
- [ ] Register files uploaded during the transaction
- [ ] Delete files on rollback
- [ ] `cleanup` method on the File field

### 7.2 Relation Rollback
- [ ] Register relation changes
- [ ] Revert on error

### 7.3 TransactionManager (optional)
- [ ] Create a transaction wrapper
- [ ] Register cleanup actions
- [ ] Run cleanup on rollback

**Files:**
- `src/Http/Services/TransactionManager.php` (optional)
- `src/Http/Fields/File.php` (modified)

---

## Phase 8: Testing and Documentation — Pending

### 8.1 Unit Tests
- [ ] Tests for `AbstractResourcePersistService`
- [ ] Tests for `FieldProcessor`
- [ ] Tests for `ValidationRulesBuilder`
- [ ] Tests for resource hooks

### 8.2 Integration Tests
- [ ] Store test with `BelongsToMany`
- [ ] Update test with `MorphTo`
- [ ] Store test with `File` + rollback

### 8.3 Documentation
- [ ] Document resource hooks
- [ ] Document how to create custom fields with `fill`
- [ ] Document the full store/update flow
- [ ] Update `docs/fields/README.md`

**Files:**
- `tests/Unit/Services/AbstractResourcePersistServiceTest.php`
- `tests/Unit/Services/FieldProcessorTest.php`
- `tests/Integration/StoreUpdateFlowTest.php`
- `docs/fields/CUSTOM_FIELDS.md`

---

## Proposed Post-Refactor Flow

```
Request → Service.handle()
           │
           ├── getModel() [abstract]
           ├── authorize()
           ├── filterFields()
           ├── buildValidationRules()
           ├── Validator.validate()
           │
           ├── DB::beginTransaction()
           │   │
           │   ├── Resource.beforeStore/Update()
           │   ├── processFields()
           │   │   ├── field.beforeSave()
           │   │   └── field.fill() [polymorphic]
           │   ├── model.save()
           │   ├── processAfterSave()
           │   │   └── field.afterSave() [relations]
           │   ├── Resource.afterStore/Update()
           │   ├── TracksActionEvents.track()
           │   │
           │   └── DB::commit()
           │
           └── JsonResponse
```

---

## Prioritization

### High Priority (Phases 1–3) — Done
- Remove duplicated code
- `afterSave` support in Store (`BelongsToMany` on create)
- Shared trait

### Medium Priority (Phases 4–6)
- Resource hooks — Done
- FieldProcessor — Pending
- ValidationRulesBuilder — Pending

### Low Priority (Phases 7–8) — Pending
- Improved rollback
- Full testing
- Documentation

---

## Implementation Notes

### Backward Compatibility

1. Keep public method signatures
2. New hooks are optional (empty methods on the base Resource)
3. Existing fields keep working without changes

### Performance Considerations

1. The processor should not add significant overhead
2. Avoid unnecessary object instantiation
3. Lazy-load services

### Execution Order

```
1. Resource.beforeStore()
2. Field.beforeSave() [each field]
3. Field.fill() [each field]
4. Model.save()
5. Field.afterSave() [supported fields]
6. Resource.afterStore()
7. ActionEvent tracking
```
