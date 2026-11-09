# Testing guide

How the Nadota test suite is organised, the conventions every new test follows,
and where to look for the audit trail of the test-normalisation effort that
started on 2026-11-02.

| Document | What it holds |
|----------|---------------|
| [PROGRESS.md](./PROGRESS.md) | Step-by-step log: what was done, what is pending and why, what is still missing. |
| [COVERAGE.md](./COVERAGE.md) | Matrix of every field / relation / filter / trait / action / route against the tests that cover it. |
| [ISSUES.md](./ISSUES.md) | Bugs, contradictions and doubts found while testing. **Documented only, not fixed** — to re-evaluate in later sessions. |
| [findings/](./findings/) | Raw per-area findings the consolidated ISSUES.md is built from. |
| [../audit/](../audit/) | Functional audits of the menu, exports, actions and configuration. |

## Running

```bash
composer test                                   # whole suite (random order)
./vendor/bin/pest tests/Unit/Fields/InputTest.php
./vendor/bin/pest --filter="StudentResource"
./vendor/bin/pest tests/Feature/Http            # every HTTP route test
```

`phpunit.xml` runs in random order and fails on warnings and risky tests, so a
test that leaks state or prints output fails the run.

## Layout

```
tests/
├── Pest.php, TestCase.php          # base case: SQLite :memory:, legacy schema (test_models…), mock authorization
├── Concerns/
│   └── InteractsWithNadotaApi.php  # boots bindings + routes + fixture resources for real HTTP calls
├── Fixtures/                       # the "school" domain used by composite-resource and HTTP tests
│   ├── FixtureSchema.php           # fx_* tables (created on demand, not by TestCase)
│   ├── Models/                     # School, Grade, Student, StudentProfile, Course, Note, Label
│   ├── Resources/                  # one Resource per model; StudentResource is the "kitchen sink"
│   └── Actions/                    # ActivateStudents, PurgeInactiveStudents
├── Models/, Resources/, Policies/, Mocks/, Database/Factories/   # legacy fixtures (test_models domain)
├── Unit/                           # one class under test per file, mirrors src/
│   ├── Fields/<Field>Test.php
│   ├── Fields/Relations/<Relation>Test.php
│   ├── Fields/Traits/<Trait>Test.php
│   ├── Filters/<Filter>Test.php
│   ├── Traits/<ResourceTrait>Test.php       # src/Http/Traits
│   ├── Actions/, Export/, Menu/, Models/, Resource/, Services/
├── ServiceIntegration/             # a service driven end to end with a real DB (no HTTP layer)
└── Feature/
    ├── Http/                       # real HTTP requests against routes/api.php + routes/public.php
    └── *.php                       # migrations, route matching, authorization through controllers
```

## The fixture domain

```
School 1─n Grade 1─n Student 1─1 StudentProfile
School ──(through Grade)── Student            HasManyThrough
Grade  ──(through Student)── StudentProfile   HasOneThrough
Student n─n Course          (fx_course_student, pivot "score")
Student|Grade 1─n Note      (morph "notable")  MorphMany / MorphOne / MorphTo
Student n─n Label           (morph "labelable") MorphToMany / MorphedByMany
```

Route keys come from `Helpers::toUri()` and are **singular**: `StudentResource`
→ `/nadota-api/student/resource`, `StudentProfileResource` → `student-profile`.

`StudentResource` declares one field of nearly every scalar type, every relation
of the Student model, `Count`/`Exists`, three resource filters, two actions,
search over `name`, `email` and `grade.name`, a default sort and soft deletes.
Resources for file uploads and composite fields live next to it.

## Conventions

1. **Pest `it()` style**, one class under test per file, file named `<Class>Test.php`
   and placed in the directory that mirrors the class's location in `src/`.
2. **Describe the behaviour, not the method**: `it('stores the hashed password, never the plain one')`.
3. **Reusable classes go to `tests/Fixtures/`** (or the legacy `tests/Models`,
   `tests/Resources`). Declaring a named class inside a test file is reserved for
   one-off doubles; prefer anonymous classes.
4. **Helper functions declared in a test file are global**: give them a name
   unique to the file (`studentIndexRequest()`, not `makeRequest()`), or use a
   closure stored on `$this` in `beforeEach`.
5. **HTTP tests** use `InteractsWithNadotaApi`:

   ```php
   uses(InteractsWithNadotaApi::class);
   beforeEach(fn () => $this->bootNadotaApi());

   it('lists students', function () {
       Student::create(['name' => 'Ana']);
       $this->getJson($this->apiUrl('student'))->assertOk()->assertJsonPath('data.0.id', 1);
   });
   ```

   Authorization is mocked to "allow"; call `$this->useRealAuthorization()` and
   register a policy with `Gate::policy()` to test denials.
6. **A test that exposes a bug asserts the correct behaviour and is skipped**
   with the issue id, so the suite stays green and the test turns into the
   regression test once the bug is fixed:

   ```php
   it('returns 404 for an unknown lens', function () { ... })
       ->skip('ISSUE HTTP-003: /lens route points to a missing controller method');
   ```

   The issue itself is described in `ISSUES.md`. Source code is **not** changed
   while auditing.
7. Tests never depend on order or on state left by another test (the suite runs
   randomly). Static registries (`ResourceManager`, `NadotaService` menu
   callbacks) must be reset by the test that touches them.
8. **Never write `expect($fn)->not->toThrow(Throwable::class)`**: with an
   interface Pest's negated `toThrow` passes even when the closure throws
   (verified 2026-11-02 with a throwaway probe). Use a concrete class
   (`Exception::class`) or simply call the code — an uncaught exception fails
   the test on its own.
