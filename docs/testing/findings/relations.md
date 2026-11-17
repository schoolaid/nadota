# Findings — relation fields (2026-11-02)

Area: `src/Http/Fields/Relations/*`, the relation traits they use (`RelationshipTrait`,
`ManagesRelationLoading`, `LimitsInlineRelation`, `BuildsSelectColumns`), `RelationIndexService`
+ `RelationController` (`GET /{resource}/resource/{id}/relation/{field}`), `Handlers/RelationHandler`,
and the relation parts of `docs/fields/relation-fields.md`. IDs use the `REL-` prefix.

Every issue below has at least one test that asserts the **correct** behaviour and is skipped with
`ISSUE REL-00N`. Each of those tests was checked to fail when the skip is removed.
Related core findings: CORE-001 (api prefix keys used to build relation URLs), CORE-008 (relation
`type` values) — not repeated here.

---

### REL-001 — `MorphOne` and `HasOneThrough` have an empty field key
- **Location:** `src/Http/Fields/Relations/MorphOne.php:33-52`, `src/Http/Fields/Relations/HasOneThrough.php:33-52`.
  Every other relation sets `$this->fieldData->key = $relation` in the constructor (e.g. `HasMany.php:70`); these two don't.
- **Observed:** `key()` is `""`. In `StudentResource`'s show payload the `latestNote` field has `"key": ""`,
  in `GradeResource` the `firstStudentProfile` field too. Consequences: `optionsUrl` is `/field//options`,
  the whereHas filter created by `filterable()` is keyed `""`, `getSectionsLayout()` emits `""` field keys,
  the export header map keys it `""`, and two such fields on one resource collide in any keyed map on the frontend.
- **Expected:** key = relation name (`latestNote`, `firstStudentProfile`), like every other relation field.
- **Verified by:** `MorphOneTest` › *uses the relation name as the field key…*, *exposes a whereHas relation filter…*;
  `HasOneThroughTest` › same two; `StudentRelationsTest` › *gives every relation field of the show payload a distinct, non-empty key*;
  `ThroughRelationsTest` › *keys the HasOneThrough field by its relation name*.
- **Severity:** medium · **Kind:** bug

### REL-002 — `MorphTo::fill()` wipes the association on partial updates and ignores readonly/disabled
- **Location:** `src/Http/Fields/Relations/MorphTo.php:518-529`; called unconditionally by
  `AbstractResourcePersistService::fillField()` (`AbstractResourcePersistService.php:213-215`).
- **Observed:** when the request does not carry `notable_type`/`notable_id` the field sets both to `null`.
  `PATCH /note/resource/1 {"body":"changed"}` returns 200 and detaches the note from its student (data loss).
  `MorphTo::make(...)->readonly()` / `->disabled()` are still filled (`BelongsTo::fill()` checks both, `BelongsTo.php:370`).
  Note: `filterFieldsForUpdate()` drops readonly fields, so through HTTP only `disabled()` leaks.
- **Expected:** leave the columns untouched when the request does not mention them (clear only on an explicit
  `null`), and honour readonly/disabled.
- **Verified by:** `NoteMorphToTest` › *keeps the notable when a partial update does not send it*;
  `MorphToTest` › *leaves the association untouched when the request does not carry the morph columns*,
  *does not fill when readonly or disabled* (2 dataset rows).
- **Severity:** high · **Kind:** bug

### REL-003 — `/relation/{field}` ignores the direction of the field's `orderBy()`
- **Location:** `src/Http/Services/RelationIndexService.php:249-258`.
- **Observed:** `sort_direction` defaults to `'desc'` in `$request->get('sort_direction', 'desc')`, so the fallback
  `if (!$sortDirection && …getOrderDirection())` is dead. `HasMany::make(...)->paginated()->orderBy('name', 'asc')`
  is served `desc`. Inline rendering honours the direction (`LimitsInlineRelation`), the endpoint doesn't.
- **Expected:** without `sort_direction` in the request, use the field's `orderBy()` direction
  (docs: "Default ordering for inline rendering, options, and pagination").
- **Verified by:** `RelationPaginationEndpointTest` › *defaults to the orderBy() direction of the field*;
  `RelationIndexServiceTest` › *falls back to the field orderBy() direction without a request sort*.
- **Severity:** medium · **Kind:** bug / contradiction docs-vs-code

### REL-004 — `/relation/{field}` sorting is neither validated nor qualified
- **Location:** `src/Http/Services/RelationIndexService.php:245-265`.
- **Observed:**
  1. `sort_direction=sideways` reaches `Builder::orderBy()` → `InvalidArgumentException` → **500**.
  2. `sort_field` is any string; it is not checked against the related resource's sortable fields (the main
     index does, `ApplySortingPipe`). On MySQL/Postgres an unknown column is a 500 (SQLite reads an unknown
     quoted identifier as a literal, which is why the test checks the SQL).
  3. The column is not qualified: a paginated pivot relation with `orderBy('created_at')` emits
     `order by "created_at"` although both `fx_courses` and the pivot have `created_at` (ambiguous on MySQL).
     `LimitsInlineRelation::applyInlineConstraints()` qualifies it for the inline path.
- **Expected:** ignore (or 422) non-sortable fields and invalid directions; qualify the column with the related table.
- **Verified by:** `RelationPaginationEndpointTest` › *rejects an invalid sort_direction instead of failing*,
  *ignores a sort_field that is not a sortable field of the related resource*, *qualifies the sort column of a pivot relation*.
- **Severity:** medium · **Kind:** bug

### REL-005 — `/relation/{field}` performs no authorization and bypasses resource scoping and field visibility
- **Location:** `src/Http/Controllers/RelationController.php:19-24`, `src/Http/Services/RelationIndexService.php:36-95`.
- **Observed:**
  1. No `authorized('view', $parent)` and no `viewAny` on the related resource. With a policy denying `view`,
     `GET /student/resource/1` is 403 but `GET /student/resource/1/relation/courses` is 200 with the data.
  2. The parent is loaded with `$resource->model::findOrFail($id)` (line 41), so tenant/ownership scoping done in
     `getQuery()` is skipped (same family as CORE-004): a student excluded by `getQuery()` is 404 on show but its
     relation is served.
  3. `findRelationField()` searches `flattenFields()` (line 84) without visibility, so a relation hidden with
     `showOnDetail(fn () => false)` (e.g. admin-only) is still served.
- **Expected:** authorize `view` on the parent (and `viewAny` on the related resource), load the parent through
  `getQuery()`, only serve fields visible on detail.
- **Verified by:** `RelationPaginationEndpointTest` › *denies the relation of a parent the user may not view*,
  *does not serve a parent excluded by the resource query scope*, *does not serve a relation field hidden from the detail view*.
- **Severity:** high (data exposure) · **Kind:** bug
- **Status:** ✅ fixed by `7958fd8` (2026-11-17): the parent is loaded through `getQuery()` and authorized with `view`; only a field the detail or the edit view shows is served (the edit form pages its attach lists through the same URL). No `viewAny` check on the related resource, as on the show page that embeds the relation. The regression tests are un-skipped.

### REL-006 — Through relations select an intermediate-table column from the far table
- **Location:** `src/Http/Fields/Relations/HasManyThrough.php:304-354` (line 346), `HasOneThrough.php:189-236` (line 228).
- **Observed:** `getRelatedColumns()` appends `getSecondLocalKeyName()` — the local key of the **intermediate**
  table — to the far table's select (the comment says "the key on the final table"). With the default `id` it is
  harmless; with a custom second local key the eager load fails:
  `no such column: fx_students.level` (show → 500). Fixtures: `Tests\Fixtures\Relations\Models\CustomKeysSchool`
  / `CustomKeysGrade`.
- **Expected:** nothing extra is needed (Laravel adds `laravel_through_key` itself); at most the far table's
  `getSecondKeyName()` should be ensured.
- **Verified by:** `HasManyThroughTest`/`HasOneThroughTest` › *does not add intermediate-table columns to the far table select*;
  `ThroughRelationsTest` › *shows a HasManyThrough/HasOneThrough whose second local key is not the primary key*.
- **Severity:** medium · **Kind:** bug

### REL-007 — `MorphTo::resolveForExport()` labels the target with the parent resource
- **Location:** `src/Http/Fields/Relations/MorphTo.php:606`.
- **Observed:** `resolveLabel($relatedModel, $resource)` receives the **parent** resource (the exporter passes
  `$this`), so a custom `displayLabel()` on `NoteResource` labels the Student. `BelongsTo::resolveForExport()`
  had the same bug and was fixed (comment at `BelongsTo.php:347-349`). Also `resolve()` never uses the morph
  target resource for the label in `formatBasic`, so without a parent resource the export shows the key (`[Grade] 1`).
- **Expected:** use the morph target resource (`morphResources[$alias]`).
- **Verified by:** `MorphToTest` › *exports the label from the target resource, not the parent resource*.
- **Severity:** low · **Kind:** bug

### REL-008 — Export of `HasManyThrough`, `HasOneThrough`, `MorphOne`, `MorphedByMany`
- **Location:** these four classes have no `resolveForExport()`; `Field::resolveForExport()` falls back to
  `resolve()`, and `ResourceExportable::formatExportValue()` json-encodes the array. `ResourceExportable::isFieldExportable()`
  (`ResourceExportable.php:92-109`) whitelists single-value relations only through `isBelongsTo/isMorphTo/isHasOne`.
- **Observed:** `->exportable()` on a `HasManyThrough`/`MorphedByMany` exports a JSON blob of `{data, meta}`
  instead of "Ana, Beto"; `HasOneThrough`/`MorphOne` shown on the index are dropped from the export (HasOne is kept)
  and, when forced with `exportable()`, export a JSON object.
- **Expected:** same behaviour as `HasMany`/`BelongsToMany` (joined labels) and `HasOne` (label).
- **Verified by:** `HasManyThroughTest`, `MorphedByManyTest` › *exports the … labels joined with the separator*;
  `HasOneThroughTest`, `MorphOneTest` › *exports the … label like HasOne does*, *is exported as a single value when shown on the index, like HasOne*.
- **Severity:** low · **Kind:** bug

### REL-009 — Validation of empty-attribute relation fields never reaches the request key
- **Status:** ◐ partly fixed by `3e462bc`: `BelongsToMany` checks the submitted ids against its candidates and answers
  422 under the field key (test un-skipped). Rules keyed by `""` (so `required()` never passes) are still open.
- **Location:** `src/Http/Services/Traits/ProcessesFields.php:75` keys rules by `$field->getAttribute()`, which is
  `''` for `HasMany`, `BelongsToMany`, `MorphToMany`, … (their request key is the relation name);
  `BelongsToMany.php:919` / `MorphToMany.php:677` sync from `$request->input()` (unvalidated).
- **Observed:**
  1. `BelongsToMany::make('Courses', 'courses')->required()` → every store fails with
     `{"errors": {"": ["The  field is required."]}}`, even when `courses` is sent.
  2. Any `->rules([...])` on such a field validates the non-existent `''` key; `courses: [999]` is stored (201)
     and a pivot row with `course_id = 999` is inserted (SQLite has no FK; on MySQL with FKs it is a 500).
- **Expected:** key rules by the field key (`courses`, `courses.*`), sync only validated ids (at least an
  `exists` check on the related table).
- **Verified by:** `StudentRelationsTest` › *validates a required BelongsToMany against the field key*,
  *rejects course ids that do not exist instead of attaching them*.
- **Severity:** medium · **Kind:** bug

### REL-010 — Sorting the index by a `BelongsTo` drops `Count`/`Exists` columns
- **Location:** `src/Http/Fields/Relations/BelongsTo.php:254-269` (`->select("$modelTable.*")`, line 268).
- **Observed:** `BuildQueryPipe` adds `withCount`/`withExists` sub-selects before `ApplySortingPipe`; the join-sort
  then **replaces** the select list, so `courses_count` is missing and the `Count` field shows `0`
  (sorted by `grade_id`: 0; unsorted: 1). It also discards the optimized column selection.
- **Expected:** keep the existing select (e.g. `addSelect`/qualify the existing columns) and only add the join + order.
- **Verified by:** `tests/ServiceIntegration/Relations/BelongsToTest.php` › *keeps Count fields when the index is sorted by a BelongsTo*.
- **Severity:** medium (silently wrong data) · **Kind:** bug

### REL-011 — Unknown morph type on store is a 500, not a validation error
- **Location:** `src/Http/Fields/Relations/MorphTo.php:548-560`; `src/ResourceManager.php:60-69` (`abort_if(..., 404)`).
- **Observed:** `POST /note/resource {"notable_type":"teacher", …}` → `ResourceManager::getResourceByKey('teacher')`
  aborts with a 404 `HttpException`, which the persist service catches as `\Exception` →
  `500 {"message":"Failed to create resource","error":""}`. The `Log::warning` + null fallback after it is unreachable.
- **Expected:** a 422 (validate `notable_type` against the declared aliases / resource keys), or at least the documented fallback.
- **Verified by:** `NoteMorphToTest` › *rejects an unknown morph type with a validation error*;
  `MorphToTest` › *rejects an unknown morph type without throwing*.
- **Severity:** medium · **Kind:** bug

### REL-012 — Attach/create affordances are not gated consistently across relation fields
- **Location:** `MorphMany.php:448-531`, `MorphToMany.php:494-538`, `MorphedByMany.php:380-424` vs
  `HasMany.php:480-562` / `BelongsToMany.php:633-714`.
- **Observed:** `HasMany`/`BelongsToMany` emit `canAttach`/`canDetach`/`canCreate` and add attach/detach/sync URLs
  only when `attachable` **and** the policy allow it. `MorphMany`, `MorphToMany` and `MorphedByMany` always emit the
  attach/detach(/sync/attachable) URLs, even after `->attachable(false)` or a denying policy, and expose no
  `canAttach`/`canDetach`/`canCreate`; `MorphMany` always emits `createContext` (no `canCreate()` gate). The server
  still authorizes the attachment endpoints (c5450ee), so this is a UI-affordance bug, not a bypass.
- **Expected:** the same gating and flags as `HasMany`/`BelongsToMany` (docs: MorphMany "Props mirror HasMany").
- **Verified by:** `MorphManyTest` › *drops attach urls when attachable(false), like HasMany does*;
  `MorphToManyTest`, `MorphedByManyTest` › *drops attach, detach and sync urls when attachable(false)*.
- **Severity:** low · **Kind:** bug / contradiction docs-vs-code

### REL-013 — Collection payloads differ between relation types
- **Location:** `HasMany.php:303-306, 345-381`; `RelationResource.php:87-89`; the `formatWithResource()` of every to-many field.
- **Observed:**
  1. `withoutId()` is honoured by `HasMany` and the relation endpoint only; `BelongsToMany`, `MorphMany`,
     `MorphToMany`, `MorphedByMany`, `HasManyThrough` ignore it.
  2. `HasMany` without a resource returns `attributes` = the full `$item->toArray()` and a `"Item #id"` label
     fallback; every other relation returns `{id, label, deletedAt}` and falls back to the key.
  3. Items of to-many relations carry `permissions` (several policy calls per related row); single relations
     (`withoutPermissions()`) don't. Doubt: intentional for row actions, but costly on large inline lists.
- **Expected:** one shape for every collection relation.
- **Verified by:** `BelongsToManyTest` › *omits the item id when withoutId() is set* (skipped); `HasManyTest` ›
  *falls back to common attributes and then "Item #id"…* documents (2).
- **Severity:** low · **Kind:** bug (1) / doubt (2, 3)

### REL-014 — `docs/fields/relation-fields.md` contradicts the code
- `:134` "With `withFields()` … carries the rendered `fields` array" → the key is `attributes` (a list of field arrays).
- `:121-131` single-record shape omits `key` (always present next to `id`); `:136-148` collection meta also has
  `resource` and `fields`; with a resource, to-many items also have `key`, `resource`, `permissions`.
- `:153` "`MorphedByMany` items also carry `morphType`" → only without a related resource (`MorphedByMany.php:308`).
- `:158` "Every relation field with a related resource exposes `props.urls.options`" → `HasOne`, `HasOneThrough`,
  `MorphOne`, `HasManyThrough` and `MorphTo` don't (MorphTo has `baseOptionsUrl`/per-type URLs).
- `:95` / `:240` orderBy is the default for pagination → only the column is (REL-003).
- `:248` example `"attributes": { "name": … }` → `attributes` is a list and only present with `withFields()`.
- `:258` `"relation_type": "hasMany"` → `null` for HasMany (REL-021).
- `:267` "`meta.actions` lists its authorized actions" → only filtered by `showOnIndex()` (`RelationIndexService.php:331`), no authorization.
- `:389` MorphMany "Props mirror HasMany" → REL-012.
- **Verified by:** `MorphedByManyTest` › *carries morphType on items rendered with a resource too, as documented*; the
  other points by the passing shape assertions in the unit/HTTP tests.
- **Severity:** low · **Kind:** contradiction docs-vs-code

### REL-015 — Morph maps are ignored
- **Location:** `MorphMany.php:501`, `MorphOne.php:306` (`get_class($model)` for `createContext.morphClass`/`prefill`);
  `MorphTo.php:532-553` (`fill()` stores `$this->morphModels[...]`, a class name); `MorphOne.php:140,154`,
  `MorphedByMany.php:308` (`morphType` = class name).
- **Observed:** with `Relation::morphMap(['student' => Student::class])`, storing a note writes
  `notable_type = 'SchoolAid\…\Student'`; `$student->notes()` then queries `notable_type = 'student'` and does not find
  it. The `createContext.prefill` handed to the frontend also carries the class name. (Reading works: `MorphTo::resolve()`
  maps aliases, `MorphToTest` › *resolves a target stored under a morph-map alias*.)
- **Expected:** use `$model->getMorphClass()` / `Relation::getMorphAlias()` when writing or prefilling morph types.
- **Verified by:** `NoteMorphToTest` › *stores the morph-map alias so the parent relation still finds the note*;
  `MorphToTest` › *stores the morph-map alias instead of the class…*; `MorphManyTest` › *prefills the morph alias when a morph map is registered*.
- **Severity:** medium (apps using `enforceMorphMap`) · **Kind:** bug

### REL-016 — `MorphTo` assumes `{relation}_type` / `{relation}_id` columns
- **Location:** `MorphTo.php:69-71` (inferred attributes) used by `resolve()` (`:262-263`), `fill()`, `applySorting()`,
  validation (`ProcessesFields::addMorphToRules()`/`collectFieldAttributes()`); only `getColumnsForSelect()` (`:184-219`)
  reads the real columns from the Eloquent relation.
- **Observed:** `morphTo('owner', 'notable_type', 'notable_id')` (fixture `OwnedNote`) resolves to `null`, and would be
  filled/validated/sorted on `owner_type`/`owner_id`, which don't exist.
- **Expected:** resolve the morph type/id column names from the relation, as `getColumnsForSelect()` already does.
- **Verified by:** `MorphToTest` › *resolves through the relation even when its columns do not follow {relation}_type/_id*.
- **Severity:** low · **Kind:** bug

### REL-017 — A to-many relation shown on the index is loaded per row (N+1)
- **Location:** constructors of `HasMany`, `BelongsToMany`, `MorphMany`, … set `$this->applyInIndexQuery = false`
  (e.g. `HasMany.php:79`); `BuildQueryPipe` only eager-loads fields with `isAppliedInIndexQuery()`.
- **Observed:** `HasMany::make(...)->showOnIndex()` renders, but `resolveInlineItems()` lazy-loads it per row:
  5 grades → 5 `fx_students` queries. Calling `->applyInIndexQuery()` fixes it, but nothing ties it to `showOnIndex()`.
- **Expected (doubt):** making a relation visible on the index should eager-load it (or the docs should say to call
  `applyInIndexQuery()`).
- **Verified by:** `StudentRelationsTest` › *eager loads a to-many relation shown on the index instead of querying it per row*.
- **Severity:** medium (performance) · **Kind:** doubt

### REL-018 — `Handlers/RelationHandler` is dead and broken
- **Location:** `src/Http/Services/Handlers/RelationHandler.php:13-16, 51-54`.
- **Observed:** no caller in `src/`. `getField()` returns `$model->{$attribute}()` (an Eloquent `Relation`) from a
  method typed `?Field` → `TypeError` as soon as a validated key matches a relation method; `Field::getRelationType()`
  does not exist (`relationType()` does); `handleHasOne/HasMany` use `getAttribute()` (empty for those fields) as the
  relation name.
- **Expected:** remove it or fix it.
- **Verified by:** `tests/Unit/Services/Handlers/RelationHandlerTest.php` › *handles a validated key that matches a relation method without crashing*.
- **Severity:** low (dead code) · **Kind:** bug

### REL-019 — `/relation/{field}` paging and search robustness
- **Location:** `src/Http/Services/RelationIndexService.php:165-196, 277-278`.
- **Observed:** `per_page` is `(int)` cast and passed to `paginate()` unchecked: `per_page=-1` → 500; any large
  value is honoured (`per_page=500`; the options/paginated endpoint caps at 100). Search only uses the related
  resource's `searchableAttributes`, not its `searchableRelations` (`StudentResource` declares `grade.name`).
- **Expected:** clamp `per_page` to `[1, max]`; doubt on whether relation search should follow `searchableRelations`.
- **Verified by:** `RelationPaginationEndpointTest` › *rejects or clamps a non-positive per_page instead of failing* (skipped),
  *honours any per_page the client asks for* (documents current behaviour).
- **Severity:** low · **Kind:** bug (negative per_page) / doubt (cap, search)

### REL-020 — Resource-based morph type labels are dead code
- **Location:** `MorphTo.php:420-430`, `FilterableTrait.php:399-405`.
- **Observed:** both check `method_exists($resourceClass, 'label')` and call `$resourceClass::label()`, but `Resource`
  has no static `label()` (only `title()`; `label()` exists on fields). Morph type labels therefore always come from the
  alias (`Str::title` / `ucfirst`), and the two places format them differently (`study_group` → "Study Group" vs "Study group").
- **Expected (doubt):** decide whether the resource title should be used (beware CORE-006: titles end in "Resources"),
  then drop or fix the branch and share one formatter.
- **Verified by:** `MorphToTest` › *labels morph types by titleizing the alias* (documents current behaviour).
- **Severity:** low · **Kind:** doubt

### REL-021 — `HasMany` has no `relationType()`
- **Location:** `src/Http/Fields/Relations/HasMany.php` (every other relation field defines it);
  consumed by `RelationIndexService.php:300`.
- **Observed:** `/relation/{field}` returns `"relation_type": null` for HasMany fields (docs example shows `"hasMany"`).
- **Expected:** `relationType(): 'hasMany'`.
- **Verified by:** `HasManyTest` › *reports its relation type like every other relation field*;
  `RelationPaginationEndpointTest` › *reports relation_type "hasMany" for a HasMany field*.
- **Severity:** low · **Kind:** bug / contradiction docs-vs-code
