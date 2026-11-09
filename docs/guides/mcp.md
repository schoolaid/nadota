# MCP Read Tools

Nadota ships four [laravel/mcp](https://github.com/laravel/mcp) tools that let an AI agent read the resources a user can already read in the panel, through Nadota's own query pipeline. An app registers them in its MCP server and lists what may leave:

| Tool | Arguments | Answer |
| ---- | --------- | ------ |
| `list-admin-resources` | — | `{resources: [{key, title, searchable}]}`, only what the user may read |
| `describe-admin-resource` | `resource` | `{key, title, fields, filters, search, defaultSort}`: what `query-admin-resource` accepts |
| `query-admin-resource` | `resource`, `search?`, `filters?`, `sortField?`, `sortDirection?`, `perPage?` (1–50, default 20), `page?` | `{data, pagination: {total, perPage, page, lastPage}}` |
| `get-admin-record` | `resource`, `id` | `{id, label, fields}` with the detail fields |

All four are `#[IsReadOnly]` and `#[IsIdempotent]`. There is no write path: no persist services, no Nadota actions.

## Everything fails closed

Nadota authorizes open: a model without a policy, or a policy without the method, is allowed by `ResourceAuthorizationService`. The MCP tools do the opposite, at four levels.

1. **The list.** A resource outside `nadota.mcp.resources` does not exist for the tools. A field outside an entry's `fields` never leaves them, whatever the panel shows. The default list is empty.
2. **The rules.** A listed resource must meet every `ExposureRule`. The package always applies `RequiresExplicitPolicy`: the model needs a policy that defines `viewAny` and `view`. The app adds its own rules, for what its panel relies on and the package cannot know.
3. **The Gate.** `viewAny` per user on the resource, and `view` per record on `get-admin-record`.
4. **The types.** Only these field types can leave: text, textarea, email, URL, number, currency, checkbox, boolean, date, datetime, time, select, radio, status, and the to-one relations (BelongsTo, MorphTo, HasOne, MorphOne, as `{id, label}`). Everything else is blocked in code and no entry can enable it: File, Image, Json, Code, KeyValue, Password, Signature, CustomComponent, Html, the to-many relations and any type Nadota adds later. A field whose attribute is a forbidden name (`password`, `remember_token`, `two_factor_secret`, `api_key`, `api_secret`, `secret`, `private_key`, `access_token`… plus `nadota.mcp.forbidden_attributes`) is blocked too, whatever its type.

A resource that is unlisted, open, refused by a rule, or denied by the Gate answers the same error, and so does a missing record and one the policy hides. The agent cannot tell which check failed.

The tools also register only when the user can read at least one listed resource (`shouldRegister`), and `handle()` checks again, so a stale `tools/list` does not get past the gatekeeper.

## Setup

### 1. Require laravel/mcp

```bash
composer require laravel/mcp:^0.5.9
```

The `SchoolAid\Nadota\Mcp\Tools` classes need it; the rest of Nadota does not. An app without laravel/mcp is unaffected.

### 2. Register the tools

```php
use Laravel\Mcp\Server;
use SchoolAid\Nadota\Mcp\Tools\DescribeAdminResourceTool;
use SchoolAid\Nadota\Mcp\Tools\GetAdminRecordTool;
use SchoolAid\Nadota\Mcp\Tools\ListAdminResourcesTool;
use SchoolAid\Nadota\Mcp\Tools\QueryAdminResourceTool;

class AdminServer extends Server
{
    protected array $tools = [
        ListAdminResourcesTool::class,
        DescribeAdminResourceTool::class,
        QueryAdminResourceTool::class,
        GetAdminRecordTool::class,
    ];
}
```

The MCP route must authenticate the user and set up the same context the panel's Nadota routes have (session, tenancy middleware…). The tools run Nadota inside that request: they build a `NadotaRequest` for the MCP user with the MCP request's session, and bind it as `app('request')` while Nadota runs, because fields and scopes read `request()`.

### 3. List the resources

In `config/nadota.php`:

```php
'mcp' => [
    'resources' => [
        'bus' => [
            'fields' => ['name', 'plate', 'capacity', 'route_id', 'active'],
            'search' => true,
        ],
        'invoice' => [
            'fields' => ['number', 'total', 'currency_code', 'status', 'issued_at'],
            'money' => ['total' => 'currency_code'],
            'hints' => ['total' => 'Negative for credit notes.'],
        ],
    ],
    'rules' => [App\Mcp\Rules\RequiresTenantScope::class],
    'forbidden_attributes' => ['merchant_id'],
    'default_currency' => 'USD',
    'descriptions' => [],
    'translation_prefixes' => [],
],
```

| Entry key | Meaning |
| --------- | ------- |
| `fields` | Nadota field keys (`Field::key()`; for a BelongsTo it is `snake(relation)_id`). Required. |
| `money` | `attribute => source`. The source is `'default'` (the `CurrencyResolver`), a column of the record (an empty value falls back to the default), or `['amount' => method, 'currency' => method]` on the model. An attribute may be a non-field column: it comes out as an extra `money` column. Amounts leave as `{amount, currency}`, raw, never the panel's formatted text. |
| `search` | Expose the resource's search. Every direct `searchableAttributes` entry must be among the listed fields (the primary key counts: it always leaves as `id`). Default `false`. |
| `hints` | `attribute => short text` that `describe-admin-resource` returns as `hint`: the sign of an amount, what not to sum. Only for the agent; not translated. |
| any other key | Free for the app's rules (e.g. `'global' => true`). |

If you published `config/nadota.php` before this release, add the `mcp` key: Laravel merges only top-level keys, so a published file without it uses the package defaults (nothing exposed).

### 4. Guard the list with a test

`ResourceListValidator` reports every entry that would silently shrink or widen what the agent sees: a key that is not a resource, a rule that fails, a field that does not exist, a blocked or forbidden field, a Currency without a currency source, a money column or currency column that does not exist, a search over an unlisted attribute, a hint of an unlisted field, an exact Status filter over a non-column.

```php
it('keeps the MCP list fail-closed and in sync with the resources', function () {
    expect(app(ResourceListValidator::class)->problems($admin))->toBe([]);
});
```

Pass a user who sees every field (fields with `showWhen` are resolved for that user). Pass `checkColumns: false` when the test has no tables.

## Extension points

| Need | How |
| ---- | --- |
| A structural condition per resource (a tenancy scope, no allow-all policy) | A class implementing `Mcp\Contracts\ExposureRule::check(Resource, array $entry): ?string`, listed in `nadota.mcp.rules`. Return the reason to refuse, or null. |
| Several lists in one app (a tenant server and a central one) | Bind your own `Mcp\Contracts\ResourceList`. |
| A currency per tenant or per session | Bind your own `Mcp\Contracts\CurrencyResolver`. |
| Another tool text | `nadota.mcp.descriptions.<tool name>`, or a subclass. |
| Titles and labels that are translation keys of the frontend, kept in one group (`global.studentResource.title`) | `nadota.mcp.translation_prefixes` (`['global.']`): each prefix is tried before the bare key. Bind your own `Mcp\Labels` for anything else. |
| An extra query argument (a branch office, say) | Subclass `QueryAdminResourceTool`: `extraSchema()`, `extraRules()`, and `beforeQuery()` to apply it or answer an error. |
| Records keyed by text (a slug, a UUID) | Subclass `GetAdminRecordTool` with `usesStringIds()` returning true. |

## What the agent gets

| Field / case | Value |
| ------------ | ----- |
| `money` attribute | `{"amount": 1234.5, "currency": "GTQ"}` |
| Number | `int` or `float` |
| Checkbox / Boolean | `bool` |
| Select / Radio / Status | `{"value": "done", "label": "Done"}`, label translated |
| To-one relation | `{"id": 81, "label": "Order #000123"}`. If a scope hides the related record, the id still comes from the foreign key and the label is null. |
| Date | `Y-m-d` |
| DateTime | ISO 8601 in the app timezone |
| Time | the stored text (`07:15:00`); its date filter is not offered |
| Text | `string`, cut to 1,000 characters with "…" |
| No value | `null` |

`query-admin-resource` rows carry the index fields; detail-only fields come from `get-admin-record`.

## How a query differs from the panel

The listing runs Nadota's index pipes (`BuildQueryPipe`, `ApplySearchPipe`, `ApplySortingPipe`), so scopes, `queryIndex`, search and sort are the panel's. Five things differ on purpose:

- **Filters come from the catalog, not `ApplyFiltersPipe`.** Every listed field the user sees, on the index or the detail page, can be filtered, so an agent can ask for the children of a parent whose foreign key only shows on the detail page. An unknown filter, sort field or value is an error listing the valid ones: Nadota ignores them silently and the agent would believe it filtered.
- **A `Status` with options filters exactly** (`whereIn`, one value or a list), not with the `LIKE` of its default filter. The panel keeps Nadota's filter.
- **A BelongsTo `relationId` filter compares the foreign key**, not `whereHas`: the related model's scopes would hide the rows whose parent is deleted or out of reach.
- **Ties break by primary key**, in the sort direction, so rows that share a `created_at` keep their order across pages. The panel leaves ties to the database.
- **The detail eager loads only the relations of the listed fields**, not every detail relation nor `getWithOnShow()`: a parent would otherwise load all its children, or a student its medical records, just for the projection to drop them. Whatever the label reads is lazy loaded on that one record (`preventLazyLoading` only guards results of more than one model). The listing keeps the index pipe's eager loads: one query per relation for the page.

Only live records are read: there is no `withTrashed`.

`tests/Mcp/IndexQueryParityTest.php` pins the parity with `ResourceIndexService`. If Nadota changes what it filters or how it sorts, it turns red with the pipes.

## Errors

When Nadota throws (a column missing in one tenant, a label that fails), the tool answers `No se pudo consultar {key}.` without the SQL, and reports the exception once the MCP request is back in the container, so the app's handler and logging see it.

The default tool descriptions and agent-facing errors are in Spanish, the language of the first consumers. Override the descriptions in config.
