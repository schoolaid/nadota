# Progress — functional audit (menu, exports, actions, configuration)

Area owner: AUD agent, 2026-11-02. Issues: [audit.md](./audit.md). Narrative audits:
[menu](../../audit/menu.md) · [exports](../../audit/exports.md) · [actions](../../audit/actions.md) ·
[configuration](../../audit/configuration.md).

## Done

1. Read the menu, export, action, configuration and provider code end to end, and the docs that
   describe them (`docs/guides/{menu,exports,actions}.md`, `docs/internals/menu.md`,
   `docs/getting-started/{configuration,installation}.md`, README, `config/nadota.php`).
2. Probed every suspected bug with throw-away tests (deleted afterwards) before writing the real tests.
3. Wrote 153 tests (154 runs with one dataset: 108 passing, 46 skipped) — see the coverage table. Each skipped test asserts the
   correct behaviour and carries its issue id; **every one was checked to fail when the `->skip()` is
   removed** (script: strip `->skip('ISSUE …')`, run the file alone, expect ⨯).
4. Logged AUD-001 … AUD-037 and extended CORE-007 (corrections: `lookup` is declared; the key read is
   `variableText`; added `fields.text`, `file/image.max_size`).
5. Wrote `docs/audit/{menu,exports,actions,configuration}.md`.
6. Added fixtures (new files only):
   - `tests/Fixtures/Menu/` — `AbstractMenuFixture` + `MenuAlpha|Beta|Gamma|DeltaResource` (menu options
     from a static map), `DenyViewAnyPolicy`, `MenuState` (resets every `NadotaService` static and the
     `ResourceManager` registry).
   - `tests/Fixtures/Export/` — `AuditExportStudentResource`, `DenyExportPolicy`.
   - `tests/Fixtures/Actions/SendReminder.php`, `SendReminders.php`.

## Results

- My paths (`tests/Unit/Menu`, `tests/Feature/Http/Menu`, `tests/Unit/Actions`, `tests/Unit/Config`,
  `tests/Unit/Export/{ResourceExportService,ExporterHardening}Test.php`, `tests/Feature/Http/BuiltIn`):
  **108 passed, 46 skipped, 0 failed**, stable over repeated random-order runs.
- Full suite (2026-11-02, other agents still writing):
  - with the default `memory_limit = 128M`: **fatal "Allowed memory size exhausted"** inside
    `zipstream-php` while an export test wrote an XLSX, after ~2 100 tests. The suite as a whole (≈2 900
    tests now) outgrows 128 MB; none of my tests writes an XLSX that is not skipped.
  - with `php -d memory_limit=512M vendor/bin/pest`: **2 573 passed, 324 skipped, 3 failed** — the three
    failures are in `tests/Unit/Fields/FileTest.php` (fields area, being edited; it fails on its own too).
    None comes from my files.

## Pending (and why)

- **HTTP regression tests for AUD-013, AUD-021, AUD-022** (export ability, action validation, action
  exception mapping) — the HTTP routes for actions/exports belong to the OPS area. The bugs are covered
  here at controller/service level; the HTTP reproduction is described in each finding.
- **AUD-019 (offset paging determinism)** — needs MySQL/PostgreSQL; SQLite returns a stable order.
- **AUD-020 (to-many export N+1)** — verified by reading; a query-count test would duplicate the relation
  agents' export tests.
- **Doc fixes** — not applied: the brief limits this audit to `docs/audit/` and findings. Corrected
  content for `configuration.md` is proposed in `docs/audit/configuration.md`.

## Missing / out of scope

- A custom `MenuServiceInterface` / `ResourceExportInterface` binding test (plain container swap).
- Queue-based action logging (`action_events.queue`) end to end — covered by existing ActionEvent tests.
- Octane/long-running behaviour beyond AUD-009.

## Notes for other agents

- **Pest pitfall:** `expect(fn () => …)->not->toThrow(Throwable::class)` passes even when the closure
  throws (verified with a probe). Call the code directly and assert afterwards instead.
- **Memory:** run the full suite with `-d memory_limit=512M` (or raise it in `phpunit.xml`), see Results.
- **Closures capturing by reference:** an arrow function `fn () => [ … function () use (&$x) … ]` captures
  `$x` by value first; use a regular `function () use (&$x)` for the outer closure.

## Coverage

| Area | File | Tests | Skipped (issues) |
|------|------|------:|------------------|
| Menu item | `tests/Unit/Menu/MenuItemTest.php` | 8 | 2 (AUD-007, AUD-008) |
| Menu section | `tests/Unit/Menu/MenuSectionTest.php` | 5 | 0 |
| Section definition | `tests/Unit/Menu/MenuSectionDefinitionTest.php` | 5 | 1 (AUD-006) |
| Menu serializers | `tests/Unit/Menu/MenuResourceTest.php` | 4 | 1 (AUD-004) |
| Menu builder | `tests/Unit/Menu/MenuServiceTest.php` | 21 | 6 (AUD-001, 002, 003×3, 005) |
| Static registry | `tests/Unit/Menu/NadotaServiceTest.php` | 5 | 1 (AUD-009) |
| Resource menu options | `tests/Unit/Menu/ResourceMenuOptionsTest.php` | 3 | 1 (AUD-011) |
| `GET /menu` | `tests/Feature/Http/Menu/MenuEndpointTest.php` | 8 | 0 (AUD-010, AUD-012 pinned) |
| Action base class | `tests/Unit/Actions/ActionTest.php` | 12 | 2 (AUD-023×2) |
| Destructive action | `tests/Unit/Actions/DestructiveActionTest.php` | 3 | 0 |
| Action response | `tests/Unit/Actions/ActionResponseTest.php` | 9 | 0 |
| Action controller | `tests/Unit/Actions/ActionControllerTest.php` | 10 | 3 (AUD-021, AUD-022×2) |
| Action execution | `tests/Unit/Actions/ActionExecutionTest.php` | 8 | 3 (AUD-024, AUD-025×2) |
| Export service | `tests/Unit/Export/ResourceExportServiceTest.php` | 12 | 5 (AUD-013, 014×2, 015, 016) |
| Exporter hardening | `tests/Unit/Export/ExporterHardeningTest.php` | 4 (5 runs) | 3 (AUD-017×2, AUD-018 ×2 datasets) |
| Config keys | `tests/Unit/Config/ConfigurationKeysTest.php` | 9 | 5 (AUD-014, AUD-035, CORE-007×2, CORE-001) |
| Service provider | `tests/Unit/Config/NadotaServiceProviderTest.php` | 12 | 5 (AUD-030, 031, 033, 034×2) |
| Discovery | `tests/Unit/Config/ResourceManagerDiscoveryTest.php` | 10 | 3 (AUD-031×2, AUD-032) |
| Built-in ActionEventResource | `tests/Feature/Http/BuiltIn/ActionEventResourceTest.php` | 5 | 4 (AUD-028×3, AUD-029) |

Issues verified by reading only (no test): AUD-019, AUD-020, AUD-026, AUD-027 (partly pinned in `ActionTest`),
AUD-036 (pinned), AUD-037.
