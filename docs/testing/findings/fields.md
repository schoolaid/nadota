# Findings — fields (2026-11-02)

Scope: the non-relation field classes in `src/Http/Fields/*.php` (including the abstract `Field`
and `Section`), their unit tests in `tests/Unit/Fields/*.php` and the HTTP round-trip tests in
`tests/Feature/Http/Fields/`. IDs use the `FLD-` prefix. Source code was **not** changed.

Every bug below has a regression test that asserts the **correct** behaviour and is skipped with
`ISSUE FLD-0NN`. "Verified" means the skipped test was run with the `->skip()` removed and failed
for the reason given (all 67 skipped tests of the area were checked that way, in one batch).

Root cause behind several entries: `AbstractResourcePersistService::fillField()`
(`src/Http/Services/AbstractResourcePersistService.php:197-234`) only calls a field's own `fill()`
for `BelongsTo`, `File` (and subclasses), `MorphTo` and `FillableFieldInterface` implementations
(only `Password`). Every other field is persisted with `resolveForStore()/resolveForUpdate()` on
the validated value, so the custom `fill()` of `Signature`, `KeyValue`, `Json`, `ArrayField` and
`DynamicField` is dead code on the HTTP path (FLD-001 … FLD-004, FLD-007).

## Found here, logged by another area first (not re-logged)

| ID | What | Where my tests hit it |
|----|------|-----------------------|
| HTTP-009 | Omitted attributes get no default on store (`DefaultValueHandler` is dead); `== null` replaces `0`/`false`/`""` by the default | `StudentShowcaseRoundTripTest` › "applies the default when the attribute is left out on store" (skipped with HTTP-009). `FieldTest` asserts only the `null → default` case so it does not pin the contested loose comparison. |
| TRT-005 | `resolve()` overrides drop `displayUsing()` and `default()` (generalises FLD-014) | `DateTest`, `DateTimeTest` (skipped with FLD-014) |
| TRT-001 | `displayUsing()` fields put their attribute in the SELECT | related to FLD-030 (same method, different symptom) |
| CORE-007 | `nadota.fields.*` keys partly unused / missing | every field test asserts the hard-coded component fallback because `TestCase` only configures `text` and `belongsTo` |

---

### FLD-001 — `Signature::fill()` never runs on store/update: disk mode, `clear` and file replacement are broken through the API
- **Location:** `src/Http/Fields/Signature.php:495-532` (`fill()`), not reached because `Signature`
  is neither a `File` nor a `FillableFieldInterface` (`AbstractResourcePersistService.php:213-222`).
- **Observed:** with `->storeOnDisk('public', 'signatures')` a POSTed data URL is stored verbatim in
  the column and no file is written; `'clear'` (the documented clear keyword) is stored as the
  literal string `"clear"`; the previous signature file is never deleted. Base64 mode only "works"
  because storing the raw value is what that mode does.
- **Expected:** the persist path calls `Signature::fill()` (implement `FillableFieldInterface` or add
  `Signature` to the self-filling list): disk mode writes `signatures/signature_*.png` and stores the
  path; `''`/`'clear'` null the column and delete the file.
- **Verified:** `StudentMediaRoundTripTest` › "writes a disk signature as a png file…", "clears a
  disk signature…"; `StudentShowcaseRoundTripTest` › "clears a base64 signature…" (skipped). The unit
  tests in `SignatureTest` show `fill()` itself works when called directly.
- **Severity:** high (the disk feature does not work at all over HTTP; data URLs of several hundred KB
  end up in a text column). **Kind:** bug.

### FLD-002 — `KeyValue::fill()` never runs: readonly/hidden keys can be overwritten, unknown keys kept, JSON strings double encoded
- **Location:** `src/Http/Fields/KeyValue.php:254-322` (`fill()`), unreachable for the same reason as FLD-001.
- **Observed:** a PUT with `settings: {theme, locale (readonly), token (hidden), admin (not in schema)}`
  stores the payload as is: the readonly `locale` and hidden `token` are overwritten and `admin` is
  kept although `allowNewKeys` is false. A JSON string payload (`'{"theme":"dark"}'`, which the
  validator closure explicitly accepts) is assigned to the `array`-cast column and stored double
  encoded (`"{\"theme\":\"dark\"}"`). The nullable `'{}'`/`'[]'` → `null` conversion never happens.
- **Expected:** `fill()` (or an equivalent `resolveForStore/Update`) is applied on store and update.
- **Verified:** `StudentShowcaseRoundTripTest` › "protects readonly and hidden keys…";
  `StudentFieldsRoundTripTest` › "decodes a JSON string sent to a KeyValue field…" (skipped).
  `KeyValueTest` covers `fill()` called directly (passing).
- **Severity:** high (readonly/hidden keys are a data-integrity/security promise the API does not keep). **Kind:** bug.

### FLD-003 — `Json`: a JSON string payload is double encoded; `fill()` is dead and nulls missing keys; no `json` rule
- **Location:** `src/Http/Fields/Json.php:74-98`.
- **Observed:** `POST meta='{"a":1}'` stores `"{\"a\":1}"` in the `array`-cast column (the string is
  json-encoded again by the cast); `GET` still shows `{a:1}` only because `Json::resolve()` decodes
  the resulting string. `Json::fill()`, which would decode it, is never called (see root cause); when
  called directly it assigns `$request->get()` without `has()`, so a missing key nulls the attribute.
  No `json` rule is added, so malformed JSON is stored as a plain string.
- **Expected:** decode JSON strings before storing into array/json-cast columns; reject malformed
  JSON with 422; leave the attribute alone when the key is absent.
- **Verified:** `StudentFieldsRoundTripTest` › "decodes a JSON string sent to a Json field…";
  `JsonTest` › "decodes a JSON string before storing…", "leaves the attribute alone…", "rejects malformed JSON" (skipped).
- **Severity:** medium. **Kind:** bug.

### FLD-004 — `ArrayField::fill()` never runs: no casting, de-duplication or empty-item removal on store/update
- **Location:** `src/Http/Fields/ArrayField.php:315-352`.
- **Observed:** `skills: ['php', '', 'sql']` is stored as `["php",null,"sql"]` (the empty string is turned
  into `null` by `ConvertEmptyStringsToNull`; `fill()` would drop it). `->integers()` arrays are stored
  as strings and `->unique()` arrays keep duplicates; `resolve()` hides it on display.
- **Expected:** the cleaning done in `fill()` applies to persisted data.
- **Verified:** `StudentFieldsRoundTripTest` › "drops empty items sent to an ArrayField" (skipped).
- **Severity:** medium. **Kind:** bug.

### FLD-005 — `ArrayField` item rules and `unique()` are not enforced by the backend
- **Location:** `src/Http/Fields/ArrayField.php:371-422`.
- **Observed:** `getItemRules()` (including the `email`/`url` rules added by `emails()`/`urls()` and the
  type rule of `integers()` etc.) is only sent to the frontend in `props.itemRules`; there is no
  `getNestedRules()`, so no `skills.*` rule reaches the validator. `unique()` adds `distinct` to the
  array attribute itself, where Laravel ignores it (it needs `skills.*`).
- **Expected:** `getNestedRules()` returning `['{attribute}.*' => [...itemRules, 'distinct' when unique]]`
  (the mechanism `MultiImage` already uses).
- **Verified:** `ArrayFieldTest` › "validates every item…", "rejects duplicated items…";
  `StudentShowcaseRoundTripTest` › "validates every array item and refuses duplicates" (skipped, 2 rows).
- **Severity:** medium. **Kind:** bug.

### FLD-006 — `DynamicField::getRules()` is commented out by an unterminated docblock
- **Location:** `src/Http/Fields/DynamicField.php:282-295` — the docblock opened at line 282 is closed
  only at line 299, so the whole `getRules()` method is part of the comment; `getRules()` is
  inherited from `Field`.
- **Observed:** the rules of the matched type never apply: with `status=draft` (Number type)
  `snippet='not a number'` is stored (201). Even the commented code would not help: `resolvedField` is
  only set later (in `beforeSave()`), after validation.
- **Expected:** validation uses the rules of the field matched by the request/model type.
- **Verified:** `DynamicFieldTest` › "validates with the rules of the matched field";
  `StudentShowcaseRoundTripTest` › "validates the dynamic value with the rules of the matched field" (skipped).
- **Severity:** medium. **Kind:** bug.

### FLD-007 — `DynamicField` store/update do not delegate to the matched field
- **Location:** `src/Http/Fields/DynamicField.php:247-260` (`fill()` delegates, but is never called);
  no `resolveForStore()/resolveForUpdate()` override.
- **Observed:** with the `enrolled → Checkbox(trueValue 'yes')` type, `snippet: true` is stored as `"1"`,
  not `"yes"`. A `File` type would never upload anything (the value is persisted raw), although
  `commitPendingFiles()/discardPendingFiles()` suggest it is meant to.
- **Expected:** persistence goes through the matched field (`resolveForStore/Update` or `fill()`).
- **Verified:** `DynamicFieldTest` › "converts the value with the matched field before storing it";
  `StudentShowcaseRoundTripTest` › "stores the dynamic value converted by the matched field" (skipped).
- **Severity:** medium. **Kind:** bug.

### FLD-008 — `DynamicField` caches the matched field across models (exports use the first row's type)
- **Location:** `src/Http/Fields/DynamicField.php:138-165` (`resolveFieldForModel()` returns
  `$this->resolvedField` once set); only `toArray()` and `fill()` reset it. `resolveForExport()` →
  `resolve()` never resets.
- **Observed:** exporting two rows with different types resolves the second row with the first row's
  field (a `Number` row rendered through a `Checkbox` → `true` instead of `"42"`). `ResourceExportable::
  transformForExport()` reuses the same field instances for every row.
- **Expected:** each model resolves its own type (don't cache across models, or key the cache by model).
- **Verified:** `DynamicFieldTest` › "resolves each model with its own type" (skipped).
- **Severity:** medium (silently wrong export data). **Kind:** bug.

### FLD-009 — `CheckboxList::getRules()` / `ArrayField::getRules()` crash on Closure rules
- **Location:** `src/Http/Fields/CheckboxList.php:169`, `src/Http/Fields/ArrayField.php:392` —
  `array_unique($rules)` compares rules as strings.
- **Observed:** `->rules([fn ($attribute, $value, $fail) => ...])` together with any other rule throws
  `Error: Object of class Closure could not be converted to string` (also for rule objects without
  `__toString()`), i.e. a 500 on create/edit/store. `ValidationTrait::uniqueRules()` exists precisely to
  avoid this and is bypassed.
- **Expected:** use the trait's closure-safe de-duplication.
- **Verified:** `CheckboxListTest` › "accepts closure rules next to string rules", `ArrayFieldTest` ›
  "accepts closure rules next to the array rule" (skipped; both throw).
- **Severity:** medium. **Kind:** bug.

### FLD-010 — `Toggle` stores raw input: `"false"` becomes true, custom true/false values are never written
- **Location:** `src/Http/Fields/Toggle.php` (no `resolveForStore/Update`; compare `Checkbox.php:58-74`).
- **Observed:** a form-encoded `is_active=false` is stored as the string `"false"`, which the boolean
  cast reads back as `true`. `->trueValue('active')->falseValue('inactive')` only affects `resolve()`;
  storing `true` writes `1`/`true`, not `'active'`. `Checkbox` handles both.
- **Expected:** the same `filter_var(..., FILTER_VALIDATE_BOOLEAN)` mapping to true/false values as `Checkbox`.
- **Verified:** `ToggleTest` › "normalizes boolean-like strings before storing" (3 rows), "stores the custom
  true and false values"; `StudentFieldsRoundTripTest` › "stores "false" sent by a form to a Toggle as false" (skipped).
- **Severity:** medium (multipart forms send strings). **Kind:** bug.

### FLD-011 — Section visibility is ignored by flat field lists and by store/update
- **Location:** `src/Http/Fields/Traits/ManagesFieldVisibility.php:63-73` (`flattenFields()` unwraps
  sections without checking the section's own visibility) used by `fieldsForForm()/fieldsForShow()/
  fieldsForIndex()` and by `ProcessesFields::filterFieldsForStore()/Update()` (`src/Http/Services/Traits/ProcessesFields.php:27-53`).
- **Observed:** with `Section::make('Admin', [URL website])->hideFromCreation()`, `GET /create` lists
  `website`, and a POST carrying `website` stores it. A `required()` field inside such a section would
  make every store fail with 422 although the form never shows it. Only the structured
  `sectionsFor*()` and `getSectionsLayout()` (`/config`) honour section visibility.
- **Expected:** a field inside a hidden section is treated as hidden for that view (store ignores it,
  `/create` omits it).
- **Verified:** `SectionTest` › "leaves the fields of a section hidden from creation out of the flat create form";
  `StudentShowcaseRoundTripTest` › "ignores the fields of a section hidden from creation on store",
  "leaves the fields … out of the create endpoint" (skipped).
- **Severity:** medium. **Kind:** bug.

### FLD-012 — `Html`/`RichText` advertise `sanitize = true` but nothing sanitizes on the backend
- **Location:** `src/Http/Fields/Html.php:9-50`, `src/Http/Fields/RichText.php:9-73`.
- **Observed:** `sanitize` (default `true`) and `allowedTags` are only props. `<script>alert(1)</script>`
  is stored and returned by show/edit/index unchanged.
- **Expected:** either sanitize server-side when `sanitize` is on (honouring `allowedTags`), or document
  that the flag is a frontend-only rendering hint. Relying on every client to sanitize is an XSS risk.
- **Verified:** `HtmlTest`/`RichTextTest` › "strips script tags before storing…";
  `StudentFieldsRoundTripTest` › "strips script tags from sanitized html fields" (skipped, 2 rows).
- **Severity:** medium (security, depends on the client). **Kind:** bug / doubt.

### FLD-013 — `Date`/`DateTime` add no date rule: an unparseable value is a 500; `min()/max()` are not enforced
- **Location:** `src/Http/Fields/Date.php`, `src/Http/Fields/DateTime.php:63-88` (`transformForStorage()`
  returns the unparseable value "to let the database handle it").
- **Observed:** `birth_date='not a date'` / `enrolled_at='not a date'` → the Eloquent date cast throws
  → `500 {"message":"Failed to create resource","error":"Could not parse 'not a date'…"}`. `min()`/`max()`
  dates are props only.
- **Expected:** a `date` rule (plus `after_or_equal`/`before_or_equal` from min/max) → 422.
- **Verified:** `DateTest` › "validates the submitted value as a date within min and max";
  `DateTimeTest` › "validates the submitted value as a date"; `StudentFieldsRoundTripTest` › "answers an
  unparseable date with a validation error" (skipped, 2 rows).
- **Severity:** medium. **Kind:** bug.

### FLD-014 — `Date`/`DateTime` `resolve()` ignore `default()` and `displayUsing()`
- **Location:** `src/Http/Fields/Date.php:45-52`, `src/Http/Fields/DateTime.php:163-170`.
- **Observed:** a null date with `->default('2020-01-01')` resolves `null`; `->displayUsing(fn …)` is
  ignored (the formatted column is returned) although the field is marked computed and not filled.
- **Expected:** honour the `FieldResolveTrait` contract. Generalised to every overriding field by **TRT-005**.
- **Verified:** `DateTest` › "shows the default…", "uses the displayUsing callback"; `DateTimeTest` › "shows the default…" (skipped).
- **Severity:** medium. **Kind:** bug.

### FLD-015 — The default is rendered as the value of a null column (doubt)
- **Location:** `src/Http/Fields/Traits/FieldResolveTrait.php:29-37`.
- **Observed:** a student stored with `age = null` shows `age: 18` on detail/edit/index when the field
  declares `->default(18)`, while the database keeps `null` (and store never writes the default when the
  key is omitted, HTTP-009). The client cannot tell "18" from "empty".
- **Expected (to decide):** defaults belong to the create form (`toArray()['default']`); detail/edit
  should show the stored value. Kept as a doubt because the trait does it on purpose.
- **Verified:** `StudentShowcaseRoundTripTest` › "offers the default on the create form and renders it
  for an empty column" (documents the current behaviour, with a comment pointing here).
- **Severity:** low. **Kind:** doubt.

### FLD-016 — `MultiImage::maxFiles()` only limits one request; the stored list grows without bound
- **Location:** `src/Http/Fields/MultiImage.php:43-73` (`max:{maxFiles}` on the request array),
  `:99-136` (`fill()` appends to the stored paths).
- **Observed:** with `maxFiles(3)`, two uploads followed by an update with two more → 200 and four
  stored photos. There is also no way to remove a photo (documented as v1 append-only).
- **Expected:** the limit applies to stored + new photos (422 otherwise), or the docs say it is per request.
- **Verified:** `MultiImageTest` › "never lets the stored list grow beyond maxFiles";
  `StudentMediaRoundTripTest` › "refuses an update that would take the photos beyond maxFiles" (skipped).
- **Severity:** medium. **Kind:** bug / doubt.

### FLD-017 — `KeyValue` per-key rules (`schema[...]['rules']`, `keyRules()`) are never applied
- **Location:** `src/Http/Fields/KeyValue.php:102-104`, `:170-174` — `$keyRules` is filled but never
  read: no `getNestedRules()`, and it is not even in the props.
- **Observed:** `schema(['age' => ['rules' => ['integer', 'min:0']]])` accepts `{'age': 'old'}`.
- **Expected:** nested rules `settings.age => [...]`.
- **Verified:** `KeyValueTest` › "validates each key against the rules declared in the schema" (skipped).
- **Severity:** medium. **Kind:** bug.

### FLD-018 — Every `ArrayField` reports itself as sortable
- **Location:** `src/Http/Fields/ArrayField.php:35, 152-156` — `$sortable` (default `true`) means
  "items can be reordered" but is the same property `SortableTrait::isSortable()` reads.
- **Observed:** `ArrayField::make()->isSortable()` is `true` and `toArray()['sortable']` is `true`, so the
  column is advertised as sortable on the index (ordering by a JSON column); `->sortable(false)` to stop
  reordering also removes column sorting.
- **Expected:** a separate property (e.g. `reorderable`) for the item UI.
- **Verified:** `ArrayFieldTest` › "is not sortable on the index unless asked" (skipped).
- **Severity:** low. **Kind:** bug.

### FLD-019 — `CustomComponent` runs its `withData()` callback three times per serialization
- **Location:** `src/Http/Fields/CustomComponent.php:120-152` + `Field::getProps()` (`Field.php:236-238`).
- **Observed:** the same callback fills `props.data`, `props.componentData` and `value` (3 calls per row);
  costly or side-effecting callbacks run three times and the payload carries the data three times.
- **Expected:** compute once.
- **Verified:** `CustomComponentTest` › "evaluates the data callback once per serialization" (skipped).
- **Severity:** low. **Kind:** bug.

### FLD-020 — `CustomComponent::component()` does not change the serialized component
- **Location:** `src/Http/Fields/CustomComponent.php:45-49` (overrides `FieldDataAccessorsTrait::component()`).
- **Observed:** after `->component('NewChart')`, `props.componentPath` is `NewChart` but the top-level
  `component` keeps the constructor path. The derived attribute (`custom_component_` + lower-cased
  label with spaces → `_`) is also not slugged, so two components with the same label collide.
- **Expected:** both keys in sync.
- **Verified:** `CustomComponentTest` › "keeps the serialized component in sync with component()" (skipped).
- **Severity:** low. **Kind:** bug.

### FLD-021 — `DateTime` with a custom display format stores the current time of day and rolls invalid dates over
- **Location:** `src/Http/Fields/DateTime.php:75-78` (`\DateTime::createFromFormat($this->format, $value)` without `!`/`|`).
- **Observed:** `->format('d/m/Y')` + `'15/01/2024'` stores `2024-01-15 <current H:i:s>`;
  `'31/02/2024 10:00'` is accepted as `2024-03-02 10:00:00`. The fallback `new \DateTime($value)` also
  drops the timezone offset of ISO input (stored as wall-clock of the offset, not converted to the app timezone).
- **Expected:** midnight for date-only formats; reject overflowing dates (check `DateTime::getLastErrors()`).
- **Verified:** `DateTimeTest` › "stores midnight for a date-only display format", "rejects an impossible
  calendar date…" (skipped).
- **Severity:** low. **Kind:** bug.

### FLD-022 — `VariableText` validation gaps
- **Location:** `src/Http/Fields/VariableText.php:240-244, 299-346`.
- **Observed:** `maxLength()` is a prop only (no `max` rule); required variables are found with
  `strpos()`, so `$name_full` satisfies `$name`; an array value reaches
  `getMissingRequiredVariables(string)` → `TypeError` (500).
- **Expected:** `max:{maxLength}`, a token-boundary check, and a `string` guard.
- **Verified:** `VariableTextTest` › "rejects a value longer than maxLength", "does not accept a longer
  variable as the required one", "fails validation instead of throwing for a non string value" (skipped).
- **Severity:** low. **Kind:** bug.

### FLD-023 — Option fields accept values outside their options
- **Location:** `Select.php`, `Radio.php`, `CheckboxList.php`, `Status.php` — none adds an `in:` rule.
- **Observed:** `status='expelled'`, `gender='zz'`, `interests=['cooking']` are stored (201).
- **Expected (to decide):** an automatic `in:` rule for static options (opt-out for dynamic ones).
- **Verified:** `SelectTest`/`RadioTest`/`StatusTest` › "rejects a value that is not…";
  `StudentFieldsRoundTripTest` › "rejects values outside the options of option fields" (skipped, 3 rows).
- **Severity:** low. **Kind:** doubt.

### FLD-024 — `Image::disablePreviewOnIndex()` never has an effect
- **Location:** `src/Http/Fields/Image.php:344` — `$request->is('*/index')`, but the index route is
  `GET /nadota-api/{resourceKey}/resource` (no `/index` segment).
- **Expected:** detect the index context another way (e.g. a flag set by the index pipeline).
- **Verified:** `ImageTest` › "hides the preview on the index listing when asked to" (skipped).
- **Severity:** low. **Kind:** bug.

### FLD-025 — Closure rules are serialized to the frontend as `{}`
- **Location:** `Field::toArray()` (`Field.php:187`) sends `getRules()`/`getRulesFor()`; `KeyValue.php:331`,
  `MultiImage.php:56`, `VariableText.php:336` (and user closures) put `Closure`s there.
- **Observed:** `rules: ["nullable", {}]` in every KeyValue/MultiImage payload.
- **Expected:** only serializable (string) rules are sent.
- **Verified:** `KeyValueTest` › "sends the frontend only rules it can read" (skipped).
- **Severity:** low. **Kind:** bug.

### FLD-026 — `toBeFieldType` expectation in `tests/Pest.php` cannot work
- **Location:** `tests/Pest.php:37-39` — calls `getType()->value`, but `getType()` returns a string.
- **Observed:** any use throws "Attempt to read property "value" on string". Unused today.
- **Expected:** `->toBe($type)` on `getType()`. (Shared infrastructure: not modified by this agent.)
- **Verified:** `InputTest` › "can be asserted with the toBeFieldType expectation…" (skipped).
- **Severity:** low. **Kind:** test infrastructure bug.

### FLD-027 — `Signature` base64 detection and quality handling
- **Location:** `src/Http/Fields/Signature.php:413-426` (`isBase64()` rejects any value containing `/`,
  a regular base64 character), `:179-196` (`jpeg()`/`webp()` assign the quality unchecked while `quality()` clamps 1..100).
- **Observed:** a raw base64 signature containing `/` resolves as `{type: 'file', path: …}`;
  `jpeg(150)` serializes `quality: 150`. The URL cache key (`signature_url_{id}_{attr}`) ignores the path,
  so a new signature can serve a stale cached URL.
- **Verified:** `SignatureTest` › "recognizes raw base64 that contains slashes", "clamps the quality given to jpeg() and webp()" (skipped).
- **Severity:** low. **Kind:** bug.

### FLD-028 — `File`: a stored file can never be removed; dead helpers; original name lost (doubt)
- **Location:** `src/Http/Fields/File.php:493-570` (`fill()` only reacts to uploads),
  `:626-654` (`generateFilename()`) and `:662-669` (`deleteOldFile()`) are never called;
  `resolve()` reports `name => basename(hashed path)`.
- **Observed:** sending `''`/`null` keeps the old file (by design for untouched inputs), but there is no
  other way (flag, `'clear'` like Signature) to delete the file through the API. The original client
  file name is not kept anywhere. With `storedAsUrl()`, replaced files are "deleted" by URL, which
  silently fails.
- **Expected (to decide):** an explicit removal contract.
- **Verified:** `FileTest` › "can clear the stored file" (skipped).
- **Severity:** low. **Kind:** doubt / dead code.

### FLD-029 — `Time` has no validation or normalisation (doubt)
- **Location:** `src/Http/Fields/Time.php`.
- **Observed:** `format`, `min`, `max`, `step` are props only; any string is stored (`'later'`), a
  `'08:30'` string column is returned as is although the format is `H:i:s`.
- **Expected:** `date_format:{format}` and limit rules, or document it as a frontend-only widget.
- **Verified:** `TimeTest` › "validates the submitted value against the time format and limits" (skipped).
- **Severity:** low. **Kind:** doubt.

### FLD-030 — `displayUsing()` does not make the field readonly nor take it off the forms
- **Location:** `src/Http/Fields/Field.php:548-568` (docblock: "also marks the field as computed
  (read-only, hidden from forms)"); it sets `$this->computed = true` directly instead of calling `computed()`.
- **Observed:** a `displayUsing()` field is `readonly = false` and shown on create/update forms, while
  store/update skip it (`isComputed()`), so the form offers an input whose value is thrown away.
  Related: TRT-001 (its column is still selected).
- **Expected:** behave like `computed()`, as documented.
- **Verified:** `FieldTest` › "treats a displayUsing() field like a computed one…" (skipped).
- **Severity:** low. **Kind:** contradiction (docblock vs code).

### FLD-031 — Dead code and unused state in field classes (low)
- `Toggle::$component = 'field-toggle'`, `Select::$component = 'field-select'`,
  `DateTime::$component = 'field-datetime'` — never read (the component lives in `FieldDTO`).
- `Radio::formatOptions()` — `is_numeric($value) ? $value : $value` is a no-op.
- `File::generateFilename()`, `File::deleteOldFile()`, `Image::isValidImage()`, `File::getFileSize()/getFileMimeType()` (callers commented out).
- `Signature::getRules()` only returns the parent rules ("custom validation may be needed").
- `Json::$editable`/`Code::$editable` do not stop the backend from writing the value.
- **Kind:** cleanup. No tests.

### FLD-032 — `Select::multiple()` decodes JSON on read but never encodes on store
- **Location:** `src/Http/Fields/Select.php:212-234` (no `resolveForStore/Update`; compare `CheckboxList::prepareValueForPersistence()`).
- **Observed:** a multiple select on a column without an array cast receives the raw array; Eloquent then
  fails on save ("Array to string conversion" → 500). It only works when the model casts the column.
- **Expected:** json-encode for non-cast columns, like `CheckboxList`.
- **Verified:** `SelectTest` › "encodes a multiple selection for a column without an array cast" (skipped).
- **Severity:** low. **Kind:** bug.

### FLD-033 — `Status::map()` documents enum keys PHP arrays cannot hold
- **Location:** `src/Http/Fields/Status.php:24-41` (docblock example `[StatusEnum::PAID => [...]]`) and
  `formatStatuses()` (`:116-118`, `$key instanceof BackedEnum` can never be true).
- **Observed:** the documented call is a fatal "Illegal offset type". Enum support only works through
  `addStatus(Enum::CASE, ...)` (tested in `StatusTest`).
- **Expected:** fix the docblock (use `->value` keys or `addStatus()`), drop the dead branch.
- **Verified:** by reading; `StatusTest` › "accepts backed enum cases in addStatus()" covers the working path.
- **Severity:** low. **Kind:** contradiction (docblock vs language).

---

## Test-suite issues found and fixed in `tests/Unit/Fields`

| File › test | Problem | Fix |
|---|---|---|
| `CheckboxTest` › "recognizes custom true values" | used `accept_terms`, which is cast to boolean, so `'yes'` was already `true` before the custom value was compared — passed for the wrong reason | uses the uncast `status` column |
| `SelectTest` › "resolves multiple values from model", `CheckboxListTest` › "resolves JSON string value from model" | wrote a JSON string into an `array`-cast column; Eloquent double-encoded it and the test only round-tripped by accident | use uncast columns |
| `MultiImageTest` › "rejects non-array garbage even when nullable" | `'not-an-array'` also failed `max:5` (12 chars), so the closure under test was not needed for the assertion | 3-character value |
| `MultiImageTest` › "does nothing when readonly, disabled or without uploads" | never tested `disabled` | added |
| `ImageTest` › "serializes to array correctly" | stale comment about a "(skipped, BUG)" type/component test; asserted only key presence | asserts `image` / `FieldImage` |
| `DateTimeTest` › "can set min/max date" | only `toHaveKey()` | asserts the `Y-m-d` value |
| `PasswordTest` › "can be instantiated and is form-only" | name promised visibility, nothing asserted it | asserts type, component and the four visibilities |
| `FileTest`, `ImageTest`, `MultiImageTest`, `SignatureTest`, media HTTP tests | `Storage::fake('public'|'local'|'s3')` share `storage/framework/testing/disks/<name>` with every concurrent test process (each fake wipes it): observed one flaky failure while other agents ran | dedicated disk names (`fld-*`, `fx-media`) |
