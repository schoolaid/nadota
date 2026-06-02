# Roadmap: Attachment System for Relations

> **Status (v1.1.4): Delivered.** The core attach / detach / sync system is implemented
> across [`src/Http/Services/Attachments/`](../../src/Http/Services/Attachments/)
> (`AbstractAttachmentService`, `BelongsToManyAttachmentService`,
> `MorphToManyAttachmentService`, `HasManyAttachmentService`, `MorphManyAttachmentService`),
> wired through the attachment controller and the
> `attach` / `detach` / `sync` / `attachable` routes in `routes/api.php`. For day-to-day
> usage see the feature guide: [../guides/attachments.md](../guides/attachments.md).
>
> **Remaining / optional:** there is no dedicated `AttachmentRequest` class (Phase 5 pivot
> validation is handled inline within the services rather than a standalone request), and
> the relation-level `beforeAttach` / `afterAttach` / `beforeDetach` / `afterDetach` /
> `beforeSync` / `afterSync` resource hooks (Phase 6.1) are **not** implemented. The
> action-event side of Phase 6.2 *is* delivered — see
> [attachment-events.md](./attachment-events.md).

This document describes the implementation plan for the full attach/detach/sync system
across all relations.

## Current State

| Relation | ManagesAttachments | Service | Controller | Routes | Status |
|----------|--------------------|---------|------------|--------|--------|
| HasMany | ✅ | ✅ `HasManyAttachmentService` | ✅ | ✅ | **Complete** |
| BelongsToMany | ✅ | ✅ `BelongsToManyAttachmentService` | ✅ | ✅ | **Complete** |
| MorphToMany | ✅ | ✅ `MorphToManyAttachmentService` | ✅ | ✅ | **Complete** |
| MorphedByMany | ✅ | ✅ (uses MorphToMany) | ✅ | ✅ | **Complete** |
| MorphMany | ✅ | ✅ `MorphManyAttachmentService` | ✅ | ✅ | **Complete** |

---

## Phase 1: Base Infrastructure — Done

### 1.1 Create Base Contract for Attachment Services
- [x] Create `AttachmentServiceInterface`
- [x] Define methods: `attach()`, `detach()`, `sync()`, `getAttachableItems()`

### 1.2 Create Abstract Base Class
- [x] Create `AbstractAttachmentService`
- [x] Implement common logic (validation, permissions, responses)

**Files:**
- `src/Http/Services/Attachments/Contracts/AttachmentServiceInterface.php`
- `src/Http/Services/Attachments/AbstractAttachmentService.php`

---

## Phase 2: BelongsToMany Attachment Service — Done

### 2.1 Create `BelongsToManyAttachmentService`
- [x] Implement `getAttachableItems()` — items not related yet
- [x] Implement `attach()` — uses `$relation->attach($ids, $pivotData)`
- [x] Implement `detach()` — uses `$relation->detach($ids)`
- [x] Implement `sync()` — uses `$relation->sync($ids)`
- [x] Support pivot data on attach/sync

### 2.2 Add ManagesAttachments to BelongsToMany
- [x] Add the `ManagesAttachments` trait
- [x] Add specific methods for pivot data

### 2.3 Update URLs in BelongsToMany
- [x] Verify URLs point to the correct routes

**Files:**
- `src/Http/Services/Attachments/BelongsToManyAttachmentService.php`
- `src/Http/Fields/Relations/BelongsToMany.php` (modified)

---

## Phase 3: MorphToMany Attachment Service — Done

### 3.1 Create `MorphToManyAttachmentService`
- [x] Extend/reuse `BelongsToMany` logic
- [x] Handle the morph type in queries
- [x] Implement `attach()`, `detach()`, `sync()`

### 3.2 Add ManagesAttachments to MorphToMany
- [x] Add the trait
- [x] Configure for polymorphic relations

### 3.3 MorphedByMany
- [x] Reuses `MorphToMany` — no separate service needed

**Files:**
- `src/Http/Services/Attachments/MorphToManyAttachmentService.php`
- `src/Http/Fields/Relations/MorphToMany.php` (modified)
- `src/Http/Fields/Relations/MorphedByMany.php` (modified)

---

## Phase 4: Controller and Routes — Done

### 4.1 Update AttachmentController
- [x] Add cases for `belongsToMany`, `morphToMany`, `morphedByMany`
- [x] Inject the new services
- [x] Implement the `sync()` method

### 4.2 Add Sync Route
- [x] Add `POST /{id}/sync/{field}` in `routes/api.php`

### 4.3 Refactor switch to Strategy/Factory (optional)
- [ ] Consider a factory pattern to select the service by type

**Files:**
- `src/Http/Controllers/AttachmentController.php` (modified)
- `routes/api.php` (modified)

---

## Phase 5: Validation and Pivot Data — Partial

> Pivot handling is implemented inside the attachment services, but the standalone
> `AttachmentRequest` class proposed below was not created. Pivot data is read and
> applied directly in `BelongsToManyAttachmentService` / `MorphToManyAttachmentService`.

### 5.1 Pivot Field Validation
- [ ] Validate pivot data against the `pivotFields()` defined on the field
- [ ] Use each pivot field's validation rules

### 5.2 Request for Attachments
- [ ] Create `AttachmentRequest` with dynamic validation
- [ ] Support the structure: `{ items: [id], pivot: { field: value } }`

**Files:**
- `src/Http/Requests/AttachmentRequest.php` (not created)

---

## Phase 6: Hooks and Events — Partial

### 6.1 Resource Hooks — Pending
- [ ] `beforeAttach($model, $field, $ids)`
- [ ] `afterAttach($model, $field, $ids)`
- [ ] `beforeDetach($model, $field, $ids)`
- [ ] `afterDetach($model, $field, $ids)`
- [ ] `beforeSync($model, $field, $ids)`
- [ ] `afterSync($model, $field, $changes)`

### 6.2 Action Events — Done
- [x] Log attach/detach/sync events in `action_events`

> See the dedicated roadmap [attachment-events.md](./attachment-events.md) for the
> action-event implementation details.

**Files:**
- `src/Resource.php` (hooks not added)
- `src/Http/Services/Attachments/AbstractAttachmentService.php` (action-event tracking added)

---

## Phase 7: Documentation — Done

### 7.1 Update Documentation
- [x] Document the endpoints
- [x] Document attachment configuration per relation type
- [x] Usage examples with pivot data

> Now consolidated in the feature guide [../guides/attachments.md](../guides/attachments.md).

---

## Request/Response Structure

### Request: Attach
```json
POST /{resource}/resource/{id}/attach/{field}
{
  "items": [1, 2, 3],
  "pivot": {
    "role": "admin",
    "expires_at": "2025-12-31"
  }
}
```

### Request: Detach
```json
POST /{resource}/resource/{id}/detach/{field}
{
  "items": [1, 2]
}
```

### Request: Sync
```json
POST /{resource}/resource/{id}/sync/{field}
{
  "items": [1, 2, 3],
  "pivot": {
    "1": { "role": "admin" },
    "2": { "role": "user" },
    "3": { "role": "guest" }
  },
  "detaching": true
}
```

### Response: Success
```json
{
  "success": true,
  "message": "Items attached successfully",
  "attached": [1, 2, 3],
  "count": 3
}
```

### Response: Sync
```json
{
  "success": true,
  "message": "Items synced successfully",
  "attached": [3],
  "detached": [4, 5],
  "updated": [1, 2]
}
```

---

## Progress

- [x] Current state analysis
- [x] Roadmap creation
- [x] Phase 1: Base infrastructure
  - [x] `AttachmentServiceInterface`
  - [x] `AbstractAttachmentService`
- [x] Phase 2: BelongsToMany
  - [x] `BelongsToManyAttachmentService`
  - [x] `ManagesAttachments` trait added
- [x] Phase 3: MorphToMany
  - [x] `MorphToManyAttachmentService`
  - [x] `ManagesAttachments` trait added
  - [x] `MorphedByMany` supported
- [x] Phase 4: Controller and routes
  - [x] `AttachmentController` updated
  - [x] sync route added
- [ ] Phase 5: Validation and pivot (partial — no dedicated request class)
- [~] Phase 6: Hooks and events (action events done; resource hooks pending)
- [x] Phase 7: Documentation

---

## Implementation Notes

### Differences Between HasMany and BelongsToMany

| Aspect | HasMany | BelongsToMany |
|--------|---------|---------------|
| Attach | Sets FK on the child | Inserts into pivot table |
| Detach | Sets FK to null | Removes from pivot table |
| Sync | N/A | Synchronizes the pivot table |
| Pivot Data | N/A | Supports additional data |

### Security Considerations

1. Verify `attach` and `detach` permissions on the Resource
2. Validate that items belong to the correct model
3. Sanitize pivot data
4. Respect `attachableLimit` if configured
