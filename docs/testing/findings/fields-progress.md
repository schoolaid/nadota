# Progress — fields (2026-11-02)

Area owner: FLD agent. Issues: [fields.md](./fields.md). Scope: the non-relation field classes in
`src/Http/Fields/*.php` (abstract `Field` and `Section` included). Source code was not changed.

## (a) Done

1. Read every field class, the field traits they rely on (`FieldResolveTrait`, `ValidationTrait`,
   `DefaultValueTrait`, `VisibilityTrait`, `ManagesFieldVisibility`) and the persist path
   (`AbstractResourcePersistService`, `ProcessesFields`, show/edit/create services, `ResourceExportable`).
2. Probed every suspected bug with throw-away tests (deleted afterwards) before writing assertions.
3. **Audited the 25 existing unit files** (356 tests). Fixed tests that passed for the wrong reason or
   asserted too little (table at the end of `fields.md`), made the storage tests independent of other
   test processes (dedicated fake disk names), and extended every file with behaviour that was not
   covered: store/update conversion (`resolveForStore/Update`), validation through a real `Validator`,
   export values, defaults, props defaults, and the `fill()` storage paths of `File`/`Image`/`MultiImage`/`Signature`.
4. **Created 12 unit files**: the 11 fields that had none (`ColorTest`, `CountTest`, `CurrencyTest`,
   `CustomComponentTest`, `DateTest`, `HtmlTest`, `KeyValueTest`, `RichTextTest`, `StatusTest`, `TimeTest`,
   `VariableTextTest` — list verified against the directory) plus `FieldTest` for the behaviour of the
   abstract base class. Every concrete class now has a `tests/Unit/Fields/<Class>Test.php` (guarded by a test).
5. **Fixtures (new files only)**:
   - `tests/Fixtures/Resources/StudentMediaResource.php` (`student-media`): `File` document, `Image` avatar,
     `MultiImage` photos, `Signature` on disk — all on the `fx-media` disk.
   - `tests/Fixtures/Resources/StudentShowcaseResource.php` (`student-showcase`): four `Section`s
     (one hidden from creation, one collapsible, one collapsed), `Select`, `Number` with default, `Hidden`,
     `Lookup`, `DynamicField` (Checkbox / Number / default Textarea), `VariableText` with required
     variables, base64 `Signature`, `KeyValue` with a schema (readonly / hidden keys), `ArrayField` of
     unique e-mails, loose `CustomComponent`.
   - No new tables: both resources use the existing `fx_students` columns.
6. **HTTP tests** in `tests/Feature/Http/Fields/`:
   - `StudentFieldsRoundTripTest` — every scalar field of `StudentResource` stored with POST, checked in
     the raw column and on show/edit, updated with PUT (23-row dataset × store and update), clearing with
     null, password hashing and keeping, Status badge, Count/Exists, index columns, `/create` description,
     validation errors.
   - `StudentMediaRoundTripTest` — multipart uploads, rendering of urls/dimensions, replacement and
     deletion of old files, append-only photos, rollback of uploads when the save fails, validation of
     types/sizes/counts.
   - `StudentShowcaseRoundTripTest` — `/config` section layout, sections on store/update, Hidden, Lookup
     (validated, never persisted), defaults, VariableText, DynamicField rendering and storing, base64
     Signature, KeyValue schema rendering, ArrayField limits, CustomComponent.
   - `FixtureResourcesUseEveryFieldTest` — reflection guards: every concrete class in `src/Http/Fields`
     is used by at least one fixture resource (looking inside Sections and DynamicField types) and has a
     `tests/Unit/Fields/<Class>Test.php`.
7. Logged FLD-001 … FLD-033; cross-referenced HTTP-009, TRT-005, TRT-001, CORE-007 instead of re-logging.
8. Verified each of the 67 skipped tests fails for the documented reason with its `->skip()` removed
   (copied to a temporary directory with the skips stripped, run, deleted).

## Results

| Path | Before | After | Passed | Skipped |
|------|-------:|------:|-------:|--------:|
| `tests/Unit/Fields/*.php` (37 files) | 356 | 737 | 693 | 44 |
| `tests/Feature/Http/Fields/` (4 files) | 0 | 129 | 106 | 23 |
| **Total** | 356 | **866** (+510) | **799** | **67** |

Stable over repeated random-order runs. Full suite (2026-11-02, after the other agents' files landed):
**2 601 passed, 339 skipped, 0 failed** (2 940 tests), both with `memory_limit=1G` and the default 128M.

## (b) Pending (and why)

- **Field export over HTTP** (`/export` with Currency/Select/Status labels, DynamicField rows): covered at
  unit level (`resolveForExport`); the endpoint itself belongs to the export/ops area.
- **Field-generated filters, `dependsOn()`, `scopedBy()`/options endpoints** (Lookup and Select options):
  owned by the filters, traits and ops areas; only the field side (props, rules, `optionsUrl`) is asserted here.
- **DynamicField with relation types and a dot-notation `basedOn()`** at HTTP level: unit tests cover
  `getNestedRelationFields()`, `getRequiredRelations()` and `getColumnsForSelect()`; an HTTP test needs a
  relation-typed fixture and would mostly exercise eager loading (relations area).
- **Signature `cache()` / `cachedTemporaryUrl()`** and **File `cache()` without temporary URLs**: only the
  temporary-url cache is tested (`FileCachedTemporaryUrlTest`). Low value until FLD-027's stale cache key is decided.
- Decisions needed before tests can be written for the doubts: FLD-015 (default shown for a null column),
  FLD-023 (automatic `in:` rule), FLD-028 (file removal contract), FLD-029 (Time validation).

## (c) Missing / not attempted

- Image thumbnail generation and `convertTo()`/`quality()` conversion: the backend never generates or
  converts anything (they are props); only reading existing thumbnails is tested.
- Real S3 behaviour (visibility ACLs, signed URLs): only `Storage::fake()` and its temporary-url builder.
- Concurrency: `Storage::fake('<name>')` wipes a directory shared by every test process using that disk
  name; my tests use dedicated names (`fld-*`, `fx-media`), other areas may still collide on `public`/`local`/`s3`.

## (d) Coverage table

Unit test counts include skipped tests. HTTP column: **R** = `StudentFieldsRoundTripTest`,
**M** = `StudentMediaRoundTripTest`, **S** = `StudentShowcaseRoundTripTest` (all also counted by the
coverage guard).

| Field class | Unit test file(s) | Unit tests (skipped) | HTTP | Status |
|---|---|---:|---|---|
| `Field` (abstract) | `FieldTest` | 40 (1) | R, M, S | full |
| `ArrayField` | `ArrayFieldTest` | 48 (4) | R, S | full — FLD-004/005/009/018 open |
| `Checkbox` | `CheckboxTest` | 24 | R, S (via DynamicField) | full |
| `CheckboxList` | `CheckboxListTest` | 23 (1) | R | full — FLD-009/023 open |
| `Code` | `CodeTest` | 17 | R | full |
| `Color` | `ColorTest` | 7 | R | full |
| `Count` | `CountTest` | 10 | R | full (constraints: TRT-002) |
| `Currency` | `CurrencyTest` | 17 | R | full |
| `CustomComponent` | `CustomComponentTest` | 14 (2) | S | full — FLD-019/020 open |
| `Date` | `DateTest` | 13 (3) | R | full — FLD-013/014 open |
| `DateTime` | `DateTimeTest` | 29 (4) | R | full — FLD-013/014/021 open |
| `DynamicField` | `DynamicFieldTest` | 35 (3) | S | partial — relation types not at HTTP level; FLD-006/007/008 open |
| `Email` | `EmailTest` | 11 | R | full |
| `Exists` | `ExistsTest` | 16 | R | full |
| `File` | `FileTest`, `FileCachedTemporaryUrlTest` | 51 (1) + 3 | M | full — FLD-028 open |
| `Hidden` | `HiddenTest` | 10 | R, S | full |
| `Html` | `HtmlTest` | 6 (1) | R | full — FLD-012 open |
| `Image` | `ImageTest` | 36 (1) | M | full (no thumbnail generation exists) — FLD-024 open |
| `Input` | `InputTest` | 21 (1) | R, M, S | full |
| `Json` | `JsonTest` | 18 (3) | R | full — FLD-003 open |
| `KeyValue` | `KeyValueTest` | 26 (2) | R, S | full — FLD-002/017/025 open |
| `Lookup` | `LookupTest` | 12 | S | full (options endpoint: ops area) |
| `MultiImage` | `MultiImageTest` | 19 (1) | M | full — FLD-016 open |
| `Number` | `NumberTest` | 23 | R, S | full |
| `Password` | `PasswordTest` | 17 | R | full |
| `Radio` | `RadioTest` | 13 (1) | R | full — FLD-023 open |
| `RichText` | `RichTextTest` | 7 (1) | R | full — FLD-012 open |
| `Section` | `SectionTest` | 19 (1) | S | full — FLD-011 open |
| `Select` | `SelectTest` | 25 (2) | R, S | full — FLD-023/032 open |
| `Signature` | `SignatureTest` | 46 (2) | M (disk), S (base64) | full — FLD-001/027 open |
| `Status` | `StatusTest` | 13 (1) | R | full — FLD-023/033 open |
| `Textarea` | `TextareaTest` | 12 | R, S (DynamicField default) | full |
| `Time` | `TimeTest` | 12 (1) | R | full — FLD-029 open |
| `Toggle` | `ToggleTest` | 21 (4) | R | full — FLD-010 open |
| `URL` | `URLTest` | 10 | R, S | full |
| `VariableText` | `VariableTextTest` | 13 (3) | R, S | full — FLD-022 open |
