# Issues found during the 2026-11-02 test audit

**198 issues** (24 high · 82 medium · 92 low), found while normalising and
extending the test suite. The audit itself fixed none of them — `src/`, `config/`
and `routes/` were left untouched. They are here to be re-evaluated and fixed in
later sessions.

**Fixed since the audit (25; OPS-024 closed by decision; REL-009, FLD-029 and HTTP-005 in part):**

| ID | Fixed by | Regression tests |
|----|----------|------------------|
| [FLD-003](findings/fields.md) | `9e7292c` fix(json): el Json del form ya no se guarda encodado dos veces | `JsonTest`, `StudentFieldsRoundTripTest`, un-skipped |
| [OPS-001](findings/ops.md) | `dfdb766` fix(has-many): agregar/quitar hijos en HasMany y MorphMany es opt-in y solo toma huérfanos | `AttachmentControllerTest` (MorphMany attach / attachable), un-skipped |
| [OPS-025](findings/ops.md) | `dfdb766` (same commit) — attach is opt-in (`->attachable()`) and only adopts children without a parent unless `->reassignable()` | `AttachmentControllerTest` › "lists only HasMany children without a parent", "refuses to move a HasMany child away from another parent" |
| [OPS-005](findings/ops.md) | `3e462bc` fix(belongs-to-many): el form y attach/sync validan los ids y el edit trae la lista entera | `AttachmentControllerTest` › "refuses ids that do not belong to any related record", un-skipped |
| [OPS-006](findings/ops.md) | `3e462bc` (same commit) — `getAttachedIds()` plucks the qualified column | `FieldOptionsControllerTest` (4 auto-exclusion tests), un-skipped |
| [REL-009](findings/relations.md) (part) | `3e462bc` (same commit) — form ids are checked against the candidates; `required()` on an empty-attribute field still never passes | `StudentRelationsTest` › "rejects course ids that do not exist", un-skipped; the `required()` test stays skipped |
| [OPS-028](findings/ops.md) | `96fa4bf` — `rejected` ids come back as they were sent | `AttachmentControllerTest` › "reports rejected ids with the same type as attached ids", un-skipped |
| [FLD-019](findings/fields.md) | `5bed5c0` — `withData()` runs once per serialization | `CustomComponentTest`, un-skipped |
| [FLD-020](findings/fields.md) | `5bed5c0` (same commit) — `component()` changes the serialized component | `CustomComponentTest`, un-skipped |
| [FLD-002](findings/fields.md) | `785ccae` fix(key-value): KeyValue::fill() corre al guardar (v1.5.0) | `StudentShowcaseRoundTripTest` › "protects readonly and hidden keys…", `StudentFieldsRoundTripTest` › "decodes a JSON string sent to a KeyValue field…", un-skipped |
| [FLD-029](findings/fields.md) (part) | `28451c6` fix(time): valida que el valor sea una hora (v1.5.0), `c86ec9f` normalizes to `H:i:s` (v1.5.1) — a value that is not a time is rejected; `min()`/`max()` are still frontend-only | `TimeTest` › "validates the submitted value against the time limits" stays skipped, narrowed to the limits |
| [CORE-004](findings/core.md) | `ac69d7f` (P0, branch `fix/p0-data-isolation`) — `/data` builds on `getQuery()` + `queryIndex()` and returns only the key when no requested column is valid | the `ISSUE CORE-004` tests, un-skipped |
| [REL-005](findings/relations.md) | `7958fd8` (P0, branch `fix/p0-data-isolation`) — the parent comes from `getQuery()`, needs `view`, and only fields shown on detail or edit are served | the `ISSUE REL-005` tests, un-skipped |
| [OPS-003](findings/ops.md) | `6ad6fee` (P0, branch `fix/p0-data-isolation`) — attach/sync drop the pivot keys, morph type and timestamps from the payload; attach uses the filtered rows when keyed by id | the `ISSUE OPS-003` tests, un-skipped |
| [OPS-007](findings/ops.md) | `9eb3725` (P0, branch `fix/p0-data-isolation`) — `OptionsColumnGuard`: filters and client `orderBy` limited to `getAllowedOptionsFilters()` or the non-`$hidden` columns | the `ISSUE OPS-007` tests, un-skipped |
| [OPS-008](findings/ops.md) | `9eb3725` (P0, branch `fix/p0-data-isolation`) — field options (plain, paginated, MorphTo) start from the target resource's `getQuery()` | the `ISSUE OPS-008` tests, un-skipped |
| [OPS-009](findings/ops.md) | `cc260d5` (P0, branch `fix/p0-data-isolation`) — a policy without `viewOptions()` decides by `viewAny` (decision 2026-11-17) | the `ISSUE OPS-009` tests, un-skipped |
| [OPS-010](findings/ops.md) | `d6f7437` (P0, branch `fix/p0-data-isolation`) — actions load their targets with `getQuery()->whereKey()` | the `ISSUE OPS-010` tests, un-skipped |
| [AUD-025](findings/audit.md) | `d6f7437` (P0, branch `fix/p0-data-isolation`) — same fix as OPS-010 (key name and `getQuery()`) | the `ISSUE AUD-025` tests, un-skipped |
| [OPS-022](findings/ops.md) | `af36502` (P0, branch `fix/p0-data-isolation`) — `/export` requires the `export` ability | the `ISSUE OPS-022` tests, un-skipped |
| [AUD-013](findings/audit.md) | `af36502` (P0, branch `fix/p0-data-isolation`) — same fix as OPS-022 | the `ISSUE AUD-013` tests, un-skipped |
| [OPS-021](findings/ops.md) | `af36502` (P0, branch `fix/p0-data-isolation`) — XLSX writes `=`-strings as text; CSV prefixes `= + - @`/tab/CR cells with `'` (numbers kept) | the `ISSUE OPS-021` tests, un-skipped |
| [AUD-017](findings/audit.md) | `af36502` (P0, branch `fix/p0-data-isolation`) — same fix as OPS-021 | the `ISSUE AUD-017` tests, un-skipped |
| [AUD-028](findings/audit.md) | `20942f9` (P0, branch `fix/p0-data-isolation`) — `ActionEventResource` answers only read abilities, and only with a registered `ActionEvent` policy (decision 2026-11-17) | the `ISSUE AUD-028` tests, un-skipped |
| [AUD-012](findings/audit.md) | `c12db48` (P0, branch `fix/p0-data-isolation`) — default `middlewares` is `['api', 'auth']` (decision 2026-11-17) | the `ISSUE AUD-012` tests, un-skipped |
| [AUD-030](findings/audit.md) | `0bb193d` (P0, branch `fix/p0-data-isolation`) — the config is merged in `NadotaServiceProvider::register()` | the `ISSUE AUD-030` tests, un-skipped |
| [HTTP-007](findings/http.md) | `c03a612` (P0, branch `fix/p0-data-isolation`) — destroy/restore/force delete answer 403 when `canDelete()`/`canRestore()`/`canForceDelete()` is false | the `ISSUE HTTP-007` tests, un-skipped |
| [OPS-024](findings/ops.md) | closed by decision (2026-11-17): field options stay authorized against the resource that owns the field; the related resource's `getQuery()` limits what is offered (OPS-008) | `FieldOptionsControllerTest` › "authorizes against the resource that owns the field" (passing, unchanged) |
| [HTTP-005](findings/http.md) (part) | `ac69d7f` — without `?fields` the empty attribute of relation fields is no longer selected; scalar columns are still not selectable | `ResourceMetaEndpointsTest` › "selects real columns when no fields are requested", un-skipped; "returns the requested index columns" stays skipped |

Found while checking those fixes: OPS-028 (low), fixed since; and a regression of `3e462bc` (the
MorphToMany/MorphedByMany attachable list answered 500), fixed by `96fa4bf` in the same push.

How to read this file:

- Full write-up of each issue (location `file:line`, observed vs expected, how it
  was verified, kind) lives in the per-area file linked from the ID:
  [core](findings/core.md) · [fields](findings/fields.md) · [relations](findings/relations.md) ·
  [filters](findings/filters.md) · [traits](findings/traits.md) · [http](findings/http.md) ·
  [ops](findings/ops.md) (options, attachments, actions, export routes) · [audit](findings/audit.md) (menu, exports, actions, configuration).
- **138 open issues have a regression test** that asserts the correct behaviour and is
  skipped with `->skip('ISSUE <ID>: …')`. Every skipped test was run once without
  its skip and failed for the stated reason. When you fix an issue, remove the
  skip and the test becomes the regression test:

  ```bash
  grep -rn "ISSUE HTTP-001" tests        # find the regression test(s) of an issue
  ./vendor/bin/pest --filter="meta"      # run them after removing ->skip()
  ```

- The rest are contradictions (docs vs code, config vs code), doubts that need a
  product decision, or dead code; those have no test on purpose.
- Severity is the reporting agent's judgement; the three highest-impact claims
  (HTTP-001, HTTP-007, REL-005) were re-verified independently with a throwaway
  probe before writing this file.
- Several issues were found twice from different angles. The last column links
  them; fix them together.

## Root causes (my reading of the 197 issues found by the audit)

Most issues are symptoms of a small number of structural problems. Fixing the
cause closes several IDs at once.

1. **Data isolation is only enforced on the main CRUD routes.** `getQuery()` /
   `queryIndex()` scoping and policies are skipped by `/data` (CORE-004),
   `/relation/{field}` (REL-005), field options (OPS-008, OPS-009, OPS-024),
   actions (OPS-010/AUD-025), export (OPS-022/AUD-013), attachments
   (OPS-003, OPS-025) and the built-in audit-log resource (AUD-028). Routes also
   disagree on which scope loads a record (HTTP-011).
   → One "load record for this request" + "authorize" helper used by every
   controller.
2. **The persist pipeline bypasses the field's own `fill()`.** Signature,
   KeyValue, Json, ArrayField, DynamicField and Toggle implement `fill()` /
   casting that store/update never call (FLD-001…004, FLD-007, FLD-010);
   `disabled()`/readonly and section visibility are not respected when writing
   (HTTP-008, FLD-011, REL-002, TRT-006); defaults are applied inconsistently
   (HTTP-009).
   → Make `AbstractResourcePersistService` delegate to `Field::fill()` for every
   field, after a single visibility/writability check that has the request and
   the model.
3. **Validation rules declared by the API are not enforced.** Action fields
   (OPS-004/AUD-021), DynamicField (FLD-006), ArrayField item rules (FLD-005),
   KeyValue per-key rules (FLD-017), to-many relations keyed by `''` (REL-009),
   MorphTo keyed rules (TRT-018), dates (FLD-013), `requiredIf` (TRT-007),
   options (FLD-023).
4. **Bad input becomes a 500 instead of a 4xx** — and the 500 echoes the
   exception message (HTTP-014/OPS-015/AUD-022): perPage/sort/direction
   (HTTP-002, HTTP-003, REL-004, OPS-011, OPS-013), non-array `filters`
   (FLT-008, FLT-009), scalar `resources`/`columns` (OPS-016), unknown morph
   type (REL-011), int-typed `{id}` (CORE-003/OPS-017), hooks (HTTP-006).
   → A request-validation layer per endpoint plus an exception handler that
   maps to 4xx and hides messages outside debug.
5. **SELECT/relationship query building** emits invalid SQL that SQLite
   forgives and MySQL/PostgreSQL reject: TRT-001, TRT-002, REL-006, REL-010,
   FLT-025, OPS-006. The suite only runs on SQLite, so this class of bug stays
   invisible (see "My recommendations" in [PROGRESS.md](PROGRESS.md)).
6. **Field filters and resource filters share one key space** (FLT-001) and the
   index applies only filters of index-visible fields while `/filters`
   advertises all of them (FLT-004).
7. **Response contract drift**: duplicated pagination meta (HTTP-001), raw
   Eloquent models from store/update/restore (HTTP-013), inconsistent relation
   payloads (REL-013).
8. **Configuration and boot**: three API-prefix keys (CORE-001), unpublished
   config leaves routes without middleware (AUD-030, AUD-012), boot crash
   without `app/Nadota` (AUD-031), unread keys (OPS-019/AUD-014, AUD-035),
   publish commands that publish nothing (AUD-034).
9. **Menu** grouping/ordering edge cases (AUD-001…011).
10. **Documentation drift** across `docs/api`, `docs/guides`, `docs/fields`
    (HTTP-015, OPS-023, REL-014, FLT-002, FLT-021, AUD-010, AUD-023, AUD-037).

## Suggested fix order

| Priority | What | IDs |
|----------|------|-----|
| P0 — security / data isolation ✅ done 2026-11-17 (`fix/p0-data-isolation`) | scoping + authorization on every route; audit-log resource; pivot FK override; options oracle; export ability; default middleware | CORE-004, REL-005, OPS-003, OPS-007, OPS-008, OPS-009, OPS-010/AUD-025, OPS-022/AUD-013, OPS-024, AUD-028, AUD-012/AUD-030, HTTP-007, OPS-021/AUD-017 |
| P1 — documented features that do not work | persist pipeline + validation (root causes 2 and 3), MorphMany attach, filters key space, pagination meta, invalid SQL on MySQL | FLD-001…007, FLD-010, REL-002, REL-009, OPS-001, OPS-004/AUD-021, FLT-001, FLT-004, FLT-025, HTTP-001, TRT-001, TRT-002, AUD-031 |
| P2 — robustness | 4xx instead of 500, hidden messages | root cause 4 |
| P3 — consistency, docs, dead code | everything else | — |

## Index (all issues)

| ID | Sev. | Area | Title | Same root cause as |
|----|------|------|-------|--------------------|
| [CORE-001](findings/core.md) | medium | core | Three different config keys for the API prefix |  |
| [CORE-002](findings/core.md) | low | core | `/lens` route points to a method that does not exist |  |
| [CORE-003](findings/core.md) | medium | core | `permissions` endpoint types `$id` as `int` | OPS-017 |
| [CORE-004](findings/core.md) | high | core | ✅ **Fixed** (`ac69d7f`) — `/data` (compact) bypasses `getQuery()`/`queryIndex()` and authorization scoping | HTTP-005 |
| [CORE-005](findings/core.md) | low | core | `ResourceManager::getModelByResource()` cannot work |  |
| [CORE-006](findings/core.md) | low | core | Default resource title keeps the word "Resource" |  |
| [CORE-007](findings/core.md) | low | core | `config/nadota.php` `fields.*` entries are partly unused and partly missing |  |
| [CORE-008](findings/core.md) | low | core | Several relation fields report another relation's `type` |  |
| [CORE-009](findings/core.md) | low | core | `tests/ServiceIntegration/README.md` is stale |  |
| [CORE-010](findings/core.md) | low | core | PHP 8.4 implicit-nullable parameters in filters | TRT-020, AUD-008 |
| [FLD-001](findings/fields.md) | high | fields | `Signature::fill()` never runs on store/update: disk mode, `clear` and file replacement are broken through the API |  |
| [FLD-002](findings/fields.md) | high | fields | ✅ **Fixed** (`785ccae`) — `KeyValue::fill()` never runs: readonly/hidden keys can be overwritten, unknown keys kept, JSON strings double encoded |  |
| [FLD-003](findings/fields.md) | medium | fields | ✅ **Fixed** (`9e7292c`) — `Json`: a JSON string payload is double encoded; `fill()` is dead and nulls missing keys; no `json` rule |  |
| [FLD-004](findings/fields.md) | medium | fields | `ArrayField::fill()` never runs: no casting, de-duplication or empty-item removal on store/update |  |
| [FLD-005](findings/fields.md) | medium | fields | `ArrayField` item rules and `unique()` are not enforced by the backend |  |
| [FLD-006](findings/fields.md) | medium | fields | `DynamicField::getRules()` is commented out by an unterminated docblock |  |
| [FLD-007](findings/fields.md) | medium | fields | `DynamicField` store/update do not delegate to the matched field |  |
| [FLD-008](findings/fields.md) | medium | fields | `DynamicField` caches the matched field across models (exports use the first row's type) |  |
| [FLD-009](findings/fields.md) | medium | fields | `CheckboxList::getRules()` / `ArrayField::getRules()` crash on Closure rules |  |
| [FLD-010](findings/fields.md) | medium | fields | `Toggle` stores raw input: `"false"` becomes true, custom true/false values are never written |  |
| [FLD-011](findings/fields.md) | medium | fields | Section visibility is ignored by flat field lists and by store/update | TRT-003 |
| [FLD-012](findings/fields.md) | medium | fields | `Html`/`RichText` advertise `sanitize = true` but nothing sanitizes on the backend |  |
| [FLD-013](findings/fields.md) | medium | fields | `Date`/`DateTime` add no date rule: an unparseable value is a 500; `min()/max()` are not enforced |  |
| [FLD-014](findings/fields.md) | medium | fields | `Date`/`DateTime` `resolve()` ignore `default()` and `displayUsing()` |  |
| [FLD-015](findings/fields.md) | low | fields | The default is rendered as the value of a null column (doubt) |  |
| [FLD-016](findings/fields.md) | medium | fields | `MultiImage::maxFiles()` only limits one request; the stored list grows without bound |  |
| [FLD-017](findings/fields.md) | medium | fields | `KeyValue` per-key rules (`schema[...]['rules']`, `keyRules()`) are never applied |  |
| [FLD-018](findings/fields.md) | low | fields | Every `ArrayField` reports itself as sortable |  |
| [FLD-019](findings/fields.md) | low | fields | ✅ **Fixed** (`5bed5c0`) — `CustomComponent` runs its `withData()` callback three times per serialization |  |
| [FLD-020](findings/fields.md) | low | fields | ✅ **Fixed** (`5bed5c0`) — `CustomComponent::component()` does not change the serialized component |  |
| [FLD-021](findings/fields.md) | low | fields | `DateTime` with a custom display format stores the current time of day and rolls invalid dates over |  |
| [FLD-022](findings/fields.md) | low | fields | `VariableText` validation gaps |  |
| [FLD-023](findings/fields.md) | low | fields | Option fields accept values outside their options |  |
| [FLD-024](findings/fields.md) | low | fields | `Image::disablePreviewOnIndex()` never has an effect |  |
| [FLD-025](findings/fields.md) | low | fields | Closure rules are serialized to the frontend as `{}` |  |
| [FLD-026](findings/fields.md) | low | fields | `toBeFieldType` expectation in `tests/Pest.php` cannot work |  |
| [FLD-027](findings/fields.md) | low | fields | `Signature` base64 detection and quality handling |  |
| [FLD-028](findings/fields.md) | low | fields | `File`: a stored file can never be removed; dead helpers; original name lost (doubt) |  |
| [FLD-029](findings/fields.md) | low | fields | ◐ **Partly fixed** (`28451c6`, `c86ec9f`: the time shape is validated; `min()`/`max()` are not) — `Time` has no validation or normalisation (doubt) |  |
| [FLD-030](findings/fields.md) | low | fields | `displayUsing()` does not make the field readonly nor take it off the forms |  |
| [FLD-031](findings/fields.md) | low | fields | Dead code and unused state in field classes (low) |  |
| [FLD-032](findings/fields.md) | low | fields | `Select::multiple()` decodes JSON on read but never encodes on store |  |
| [FLD-033](findings/fields.md) | low | fields | `Status::map()` documents enum keys PHP arrays cannot hold |  |
| [REL-001](findings/relations.md) | medium | relations | `MorphOne` and `HasOneThrough` have an empty field key |  |
| [REL-002](findings/relations.md) | high | relations | `MorphTo::fill()` wipes the association on partial updates and ignores readonly/disabled |  |
| [REL-003](findings/relations.md) | medium | relations | `/relation/{field}` ignores the direction of the field's `orderBy()` |  |
| [REL-004](findings/relations.md) | medium | relations | `/relation/{field}` sorting is neither validated nor qualified |  |
| [REL-005](findings/relations.md) | high | relations | ✅ **Fixed** (`7958fd8`) — `/relation/{field}` performs no authorization and bypasses resource scoping and field visibility |  |
| [REL-006](findings/relations.md) | medium | relations | Through relations select an intermediate-table column from the far table |  |
| [REL-007](findings/relations.md) | low | relations | `MorphTo::resolveForExport()` labels the target with the parent resource |  |
| [REL-008](findings/relations.md) | low | relations | Export of `HasManyThrough`, `HasOneThrough`, `MorphOne`, `MorphedByMany` |  |
| [REL-009](findings/relations.md) | medium | relations | ◐ **Partly fixed** (`3e462bc`: ids are validated; `required()` still broken) — Validation of empty-attribute relation fields never reaches the request key |  |
| [REL-010](findings/relations.md) | medium | relations | Sorting the index by a `BelongsTo` drops `Count`/`Exists` columns |  |
| [REL-011](findings/relations.md) | medium | relations | Unknown morph type on store is a 500, not a validation error |  |
| [REL-012](findings/relations.md) | low | relations | Attach/create affordances are not gated consistently across relation fields |  |
| [REL-013](findings/relations.md) | low | relations | Collection payloads differ between relation types |  |
| [REL-014](findings/relations.md) | low | relations | `docs/fields/relation-fields.md` contradicts the code |  |
| [REL-015](findings/relations.md) | medium | relations | Morph maps are ignored | FLT-014 |
| [REL-016](findings/relations.md) | low | relations | `MorphTo` assumes `{relation}_type` / `{relation}_id` columns |  |
| [REL-017](findings/relations.md) | medium | relations | A to-many relation shown on the index is loaded per row (N+1) |  |
| [REL-018](findings/relations.md) | low | relations | `Handlers/RelationHandler` is dead and broken |  |
| [REL-019](findings/relations.md) | low | relations | `/relation/{field}` paging and search robustness |  |
| [REL-020](findings/relations.md) | low | relations | Resource-based morph type labels are dead code |  |
| [REL-021](findings/relations.md) | low | relations | `HasMany` has no `relationType()` |  |
| [FLT-001](findings/filters.md) | high | filters | A field filter and a resource filter with the same key: duplicate keys, an unreachable range filter, a hijacked exact filter |  |
| [FLT-002](findings/filters.md) | medium | filters | The documented nested range format `filters[key][from\|to]` is silently ignored |  |
| [FLT-003](findings/filters.md) | medium | filters | A date range "to" bound excludes the end day on datetime columns |  |
| [FLT-004](findings/filters.md) | high | filters | Filters of fields hidden from the index are listed but never applied, and that covers every to-many and has-one relation filter by default |  |
| [FLT-005](findings/filters.md) | medium | filters | DynamicSelectFilter static options in `{value, label}` form lose their label |  |
| [FLT-006](findings/filters.md) | medium | filters | Zero and empty-string values are handled differently by each filter |  |
| [FLT-007](findings/filters.md) | low | filters | ExistsFilter treats every unrecognised value as "false" |  |
| [FLT-008](findings/filters.md) | medium | filters | A `filters` parameter that is not an array answers 500 |  |
| [FLT-009](findings/filters.md) | medium | filters | DefaultFilter answers 500 when given an array |  |
| [FLT-010](findings/filters.md) | medium | filters | Paginated relation listings do not normalise range filters |  |
| [FLT-011](findings/filters.md) | medium | filters | `Count->filterable()` filters on the `withCount` alias |  |
| [FLT-012](findings/filters.md) | low | filters | `Time->filterable()` can never match |  |
| [FLT-013](findings/filters.md) | medium | filters | `CheckboxList->filterable()` can never match |  |
| [FLT-014](findings/filters.md) | medium | filters | MorphTypeFilter ignores the morph map | REL-015 |
| [FLT-015](findings/filters.md) | low | filters | `SelectFilter::options()` takes `[label => value]`, while `Select::options()` takes `[value => label]` |  |
| [FLT-016](findings/filters.md) | low | filters | SelectFilter with an empty array hides every row |  |
| [FLT-017](findings/filters.md) | low | filters | A `"0"` default is dropped from the serialised filter |  |
| [FLT-018](findings/filters.md) | low | filters | DynamicSelectFilter: dead foreign-key resolution, and an `applyToQuery` prop that can be wrong |  |
| [FLT-019](findings/filters.md) | low | filters | DefaultFilter does not escape LIKE wildcards |  |
| [FLT-020](findings/filters.md) | medium | filters | An array sent to a single-value filter is silently reduced to its first element |  |
| [FLT-021](findings/filters.md) | low | filters | The descriptor examples in the documentation do not match the real payload |  |
| [FLT-022](findings/filters.md) | low | filters | ExistsFilter renders as FilterBoolean but ships no options |  |
| [FLT-023](findings/filters.md) | low | filters | `MorphToFilter::$resourceKey` is stored but never used |  |
| [FLT-024](findings/filters.md) | low | filters | Currency, Status and Color fall back to a LIKE DefaultFilter |  |
| [FLT-025](findings/filters.md) | high | filters | RelationFilter and DynamicSelectFilter compare an unqualified primary key inside `whereHas()` |  |
| [FLT-026](findings/filters.md) | low | filters | The MorphOne filter is keyed by its label |  |
| [FLT-027](findings/filters.md) | medium | filters | `Radio` and `CheckboxList` produce filters with swapped options |  |
| [FLT-028](findings/filters.md) | low | filters | A field's `filterKeys` disagree with the keys of the filters it generates |  |
| [FLT-029](findings/filters.md) | medium | filters | A resource-level DynamicSelectFilter advertises an options endpoint for a field that does not exist |  |
| [TRT-001](findings/traits.md) | high | traits | Computed (`displayUsing`) fields put their attribute in the SELECT |  |
| [TRT-002](findings/traits.md) | high | traits | A constrained `Count`/`Exists` breaks `withCount()`/`withExists()` |  |
| [TRT-003](findings/traits.md) | medium | traits | Section layout (and `/fields`, `/config` field lists) evaluate visibility without the request | AUD-016 |
| [TRT-004](findings/traits.md) | medium | traits | A string that names a PHP function is called instead of used |  |
| [TRT-005](findings/traits.md) | medium | traits | `resolve()` overrides drop the trait's display callback and default |  |
| [TRT-006](findings/traits.md) | medium | traits | Update persist evaluates record-dependent visibility without the record |  |
| [TRT-007](findings/traits.md) | medium | traits | `requiredIf()` / `requiredUnless()` serialise booleans so Laravel never matches them |  |
| [TRT-008](findings/traits.md) | low | traits | `isRequired()` without context ignores a `required` added through `rules()` |  |
| [TRT-009](findings/traits.md) | low | traits | A field cannot customise its own sorting |  |
| [TRT-010](findings/traits.md) | medium | traits | `dependsOn()` can serialise `fields` as a JSON object |  |
| [TRT-011](findings/traits.md) | low | traits | `optionsFromEndpoint()` after `cascadeFrom()` drops `cascadeFrom` |  |
| [TRT-012](findings/traits.md) | medium | traits | Field-level search flags are not read by any search |  |
| [TRT-013](findings/traits.md) | medium | traits | "Nothing searchable configured" behaves three different ways, none as documented |  |
| [TRT-014](findings/traits.md) | medium | traits | `VisibleWhen::isVisible()` throws on a null callback result |  |
| [TRT-015](findings/traits.md) | low | traits | The default sort is ignored when its field is hidden from the index (doubt) |  |
| [TRT-016](findings/traits.md) | low | traits | A hidden section splits the loose fields around it into two groups (doubt) |  |
| [TRT-017](findings/traits.md) | low | traits | `apiUrl()`/`frontendUrl()` are the only URLs without a leading slash (doubt) |  |
| [TRT-018](findings/traits.md) | medium | traits | MorphTo validation: keyed rules and context rules never apply |  |
| [TRT-019](findings/traits.md) | low | traits | Dead or one-way state in the traits (low) |  |
| [TRT-020](findings/traits.md) | low | traits | `TracksActionEvents::trackUpdate()` has an implicitly nullable parameter | CORE-010, AUD-008 |
| [TRT-021](findings/traits.md) | low | traits | `FieldDataAccessorsTrait::__get()` exposes every protected property |  |
| [TRT-022](findings/traits.md) | low | traits | Test-suite quality issues found in the audited files |  |
| [TRT-023](findings/traits.md) | medium | traits | `attachableQuery()` is ignored for pivot relations (doubt, attachments area) |  |
| [HTTP-001](findings/http.md) | high | http | Index `meta` / `links` values are duplicated into two-element arrays |  |
| [HTTP-002](findings/http.md) | medium | http | `perPage` is not validated: `allowedPerPage` is never enforced, bad values are 500s | OPS-011 |
| [HTTP-003](findings/http.md) | low | http | An invalid `sortDirection` is a 500 |  |
| [HTTP-004](findings/http.md) | low | http | Global search term handling |  |
| [HTTP-005](findings/http.md) | medium | http | ◐ **Partly fixed** (`ac69d7f`: no-fields case) — `/data` (compact): the column allow-list is inverted, and no `fields` selects `""` | CORE-004 |
| [HTTP-006](findings/http.md) | medium | http | CRUD services mishandle exceptions thrown inside hooks |  |
| [HTTP-007](findings/http.md) | high | http | ✅ **Fixed** (`c03a612`) — `$canDelete` / `$canRestore` / `$canForceDelete` are not enforced by the endpoints |  |
| [HTTP-008](findings/http.md) | medium | http | `disabled()` fields are written on store and update |  |
| [HTTP-009](findings/http.md) | medium | http | Field defaults on store: omitted attributes get none, falsy values get overwritten |  |
| [HTTP-010](findings/http.md) | low | http | PATCH is a PUT: a partial update must resend every required field |  |
| [HTTP-011](findings/http.md) | medium | http | Routes disagree on which query scope loads a record |  |
| [HTTP-012](findings/http.md) | low | http | Trashed records cannot be shown, edited or asked for permissions |  |
| [HTTP-013](findings/http.md) | medium | http | Store / update / restore answer with the raw Eloquent model |  |
| [HTTP-014](findings/http.md) | medium | http | 500 responses echo the exception message whatever `app.debug` says | OPS-015, AUD-022 |
| [HTTP-015](findings/http.md) | low | http | Documentation drift (docs/api, guides) against the behaviour |  |
| [HTTP-016](findings/http.md) | low | http | Each index row rebuilds the resource's `fields()` |  |
| [HTTP-017](findings/http.md) | low | http | The edit envelope always includes `id` |  |
| [OPS-001](findings/ops.md) | high | ops | ✅ **Fixed** (`dfdb766`) — Every MorphMany attachment call is a 500 (`FieldType::MORPH_TO_MANY` does not exist) |  |
| [OPS-002](findings/ops.md) | low | ops | Attaching to a non-to-many field is a 500, not 422 |  |
| [OPS-003](findings/ops.md) | high | ops | ✅ **Fixed** (`6ad6fee`) — Pivot payload can override the pivot foreign key: attach/sync write rows for another parent |  |
| [OPS-004](findings/ops.md) | medium | ops | Action fields are never validated | AUD-021 |
| [OPS-005](findings/ops.md) | medium | ops | ✅ **Fixed** (`3e462bc`) — Attaching an id that matches no related record inserts a dangling pivot row |  |
| [OPS-006](findings/ops.md) | medium | ops | ✅ **Fixed** (`3e462bc`) — `resourceId` auto-exclusion silently does nothing when the pivot table has an `id` column |  |
| [OPS-007](findings/ops.md) | high | ops | ✅ **Fixed** (`9eb3725`) — Options `filters[]` accept any column and operator: oracle on hidden columns |  |
| [OPS-008](findings/ops.md) | high | ops | ✅ **Fixed** (`9eb3725`) — Field options ignore the target resource's `getQuery()` |  |
| [OPS-009](findings/ops.md) | high | ops | ✅ **Fixed** (`cc260d5`) — Denying `viewAny` never protects options when the policy has no `viewOptions()` |  |
| [OPS-010](findings/ops.md) | high | ops | ✅ **Fixed** (`d6f7437`) — Actions can target records outside the resource's `getQuery()` | AUD-025 |
| [OPS-011](findings/ops.md) | medium | ops | Page-size / limit clamps can be bypassed (unbounded rows or 500) | HTTP-002 |
| [OPS-012](findings/ops.md) | low | ops | Field options `meta.limit` reports the requested value, not the applied one |  |
| [OPS-013](findings/ops.md) | low | ops | Invalid `orderDirection` on options is a 500 |  |
| [OPS-014](findings/ops.md) | low | ops | Field options answer `200 {success:false}` where attachments answer 404 |  |
| [OPS-015](findings/ops.md) | low | ops | Action failures echo the exception message to the client | AUD-022, HTTP-014 |
| [OPS-016](findings/ops.md) | low | ops | Scalar `resources` / `columns` values are a 500 (TypeError) |  |
| [OPS-017](findings/ops.md) | medium | ops | Action-events endpoints type `{id}` as `int` | CORE-003 |
| [OPS-018](findings/ops.md) | medium | ops | An `exportable()` field hidden from index is always exported empty | AUD-015 |
| [OPS-019](findings/ops.md) | medium | ops | `nadota.export.enabled`, `sync_limit` and `chunk_size` are never read | AUD-014 |
| [OPS-020](findings/ops.md) | low | ops | Export filename is pasted unescaped into `Content-Disposition` | AUD-018 |
| [OPS-021](findings/ops.md) | medium | ops | ✅ **Fixed** (`af36502`) — CSV / formula injection in exports | AUD-017 |
| [OPS-022](findings/ops.md) | high | ops | ✅ **Fixed** (`af36502`) — The export endpoint does not check the `export` ability | AUD-013 |
| [OPS-023](findings/ops.md) | low | ops | Documentation contradictions found while testing these routes |  |
| [OPS-024](findings/ops.md) | medium | ops | ☑️ **Closed by decision** (kept, documented) — Field options are authorized against the owning resource, never the related one |  |
| [OPS-025](findings/ops.md) | medium | ops | ✅ **Fixed** (`dfdb766`) — HasMany / MorphMany attach moves children away from their current parent |  |
| [OPS-026](findings/ops.md) | low | ops | Global options endpoint is public and runs resource code for anonymous callers |  |
| [OPS-027](findings/ops.md) | low | ops | Minor behaviours worth a decision (low, no fix required) |  |
| [OPS-028](findings/ops.md) | low | ops | ✅ **Fixed** (`96fa4bf`) — HasMany / MorphMany attach answers `rejected` ids as strings while `attached` ids are integers |  |
| [AUD-001](findings/audit.md) | medium | audit | Resources sharing a title collapse into one menu item |  |
| [AUD-002](findings/audit.md) | medium | audit | Top-level custom `MenuSection`s overwrite each other |  |
| [AUD-003](findings/audit.md) | medium | audit | Section placement loses resources in three edge cases |  |
| [AUD-004](findings/audit.md) | low | audit | `MenuItemResource` never serializes a `MenuItem`'s children |  |
| [AUD-005](findings/audit.md) | medium | audit | Children of a custom section are not visibility-filtered |  |
| [AUD-006](findings/audit.md) | low | audit | `collapsible` / `defaultCollapsed` never reach the menu JSON |  |
| [AUD-007](findings/audit.md) | low | audit | `MenuItem::fromResource()` ignores order and `isResource` |  |
| [AUD-008](findings/audit.md) | low | audit | `MenuItem` constructor: implicit nullable label/key on non-nullable properties | CORE-010, TRT-020 |
| [AUD-009](findings/audit.md) | low | audit | Menu section definitions are cached forever after the first read |  |
| [AUD-010](findings/audit.md) | low | audit | Menu docs contradict the endpoint |  |
| [AUD-011](findings/audit.md) | low | audit | `Resource::canSee()` has no effect on the menu |  |
| [AUD-012](findings/audit.md) | medium | audit | ✅ **Fixed** (`c12db48`) — Default install serves the menu and CRUD to guests | AUD-030 |
| [AUD-013](findings/audit.md) | high | audit | ✅ **Fixed** (`af36502`) — `/export` ignores the `export` ability that `/export/config` reports | OPS-022 |
| [AUD-014](findings/audit.md) | medium | audit | `export.enabled`, `export.sync_limit`, `export.chunk_size` are never read | OPS-019 |
| [AUD-015](findings/audit.md) | medium | audit | Fields hidden from index but `exportable()` are exported empty | OPS-018 |
| [AUD-016](findings/audit.md) | medium | audit | Export evaluates index visibility without the request | TRT-003 |
| [AUD-017](findings/audit.md) | medium | audit | ✅ **Fixed** (`af36502`) — Spreadsheet formula injection in XLSX and CSV exports | OPS-021 |
| [AUD-018](findings/audit.md) | low | audit | `filename` is put into `Content-Disposition` unescaped | OPS-020 |
| [AUD-019](findings/audit.md) | medium | audit | No size guard; XLSX built fully in memory; offset paging without a tiebreaker |  |
| [AUD-020](findings/audit.md) | low | audit | To-many exportable relations query once per row and drop falsy values |  |
| [AUD-021](findings/audit.md) | high | audit | Action field `rules()` are never validated | OPS-004 |
| [AUD-022](findings/audit.md) | medium | audit | `ActionController` turns every exception into a 500 with the raw message | OPS-015, HTTP-014 |
| [AUD-023](findings/audit.md) | low | audit | Action keys are pluralised and can collide; docs describe FQCN keys |  |
| [AUD-024](findings/audit.md) | medium | audit | Action runs are logged even with `action_events.enabled = false` |  |
| [AUD-025](findings/audit.md) | high | audit | ✅ **Fixed** (`d6f7437`) — Action targets are loaded by raw `id`, bypassing the key name and `getQuery()` scoping | OPS-010 |
| [AUD-026](findings/audit.md) | low | audit | `ActionInterface` misses methods the service and controller call |  |
| [AUD-027](findings/audit.md) | low | audit | Action API naming doubts |  |
| [AUD-028](findings/audit.md) | high | audit | ✅ **Fixed** (`20942f9`) — Built-in `ActionEventResource` exposes and lets anyone delete the audit log |  |
| [AUD-029](findings/audit.md) | low | audit | `ActionEventResource` declares properties the base `Resource` never reads |  |
| [AUD-030](findings/audit.md) | medium | audit | ✅ **Fixed** (`0bb193d`) — Routes read `nadota.middlewares` before the package config is merged | AUD-012 |
| [AUD-031](findings/audit.md) | high | audit | Boot crashes when `path_resources` is missing or absolute |  |
| [AUD-032](findings/audit.md) | medium | audit | Discovery derives class names from paths and wipes earlier registrations |  |
| [AUD-033](findings/audit.md) | low | audit | `key_resources_cache` is checked but never written |  |
| [AUD-034](findings/audit.md) | medium | audit | Documented publish commands publish nothing; provider stub missing |  |
| [AUD-035](findings/audit.md) | low | audit | `path` and `namespace` config keys are never read |  |
| [AUD-036](findings/audit.md) | low | audit | `excludeResources()` only works if called before the package boots |  |
| [AUD-037](findings/audit.md) | low | audit | `configuration.md` is incomplete and partly wrong |  |
