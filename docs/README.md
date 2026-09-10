# Nadota Documentation

Nadota is a Laravel package that provides a resource-based, API-driven admin layer for Eloquent models (similar to Laravel Nova), designed for an Inertia/SPA frontend.

**Package:** `schoolaid/nadota` · **Version:** 1.1.4 · **Namespace:** `SchoolAid\Nadota`

This documentation is organized by audience and domain. Start with **Getting Started**, then dive into the area you need.

## Getting Started

| Doc | What it covers |
|-----|----------------|
| [Installation](getting-started/installation.md) | Requirements, Composer install, publishing config & migrations |
| [Quick Start](getting-started/quick-start.md) | Your first resource and the generated API |
| [Configuration](getting-started/configuration.md) | Full `config/nadota.php` reference |

## Guides (how-to)

| Doc | What it covers |
|-----|----------------|
| [Defining Resources](guides/resources.md) | `$model`, `fields()`, `filters()`, `actions()`, search, pagination, discovery |
| [Authorization](guides/authorization.md) | Policy integration and granular per-field gates |
| [Lifecycle Hooks](guides/lifecycle-hooks.md) | store/update/delete/restore hooks, transactions, custom delete |
| [Actions](guides/actions.md) | Defining actions, fields, destructive & custom-component actions |
| [Action Events](guides/action-events.md) | Audit logging of model changes |
| [Attachments](guides/attachments.md) | Attach/detach/sync for relation fields |
| [Exports](guides/exports.md) | CSV/Excel export |
| [Soft Deletes](guides/soft-deletes.md) | Trashed listing, restore, force delete |
| [Menu](guides/menu.md) | Navigation tree |
| [Scoped Options](guides/scoped-options.md) | Rolling out `Lookup` fields and `scopedBy()` across the API and frontend apps |

## Fields

| Doc | What it covers |
|-----|----------------|
| [Fields Overview](fields/README.md) | Catalog of all field types + shared `Field` API |
| [Basic Fields](fields/basic-fields.md) | Input, Select, Date, Boolean, File, Code/Data, layout, etc. |
| [Relation Fields](fields/relation-fields.md) | BelongsTo, HasMany, BelongsToMany, MorphTo, and more |
| [Dynamic Fields](fields/dynamic-fields.md) | `DynamicField` |
| [Depends On](fields/depends-on.md) | Conditional field dependencies |
| [Custom Fields](fields/custom-fields.md) | `CustomComponent` and subclassing `Field` |
| [Signature](fields/signature.md) | `Signature` field |
| [Exists](fields/exists.md) | `Exists` field |

## Filters

| Doc | What it covers |
|-----|----------------|
| [Filters Overview](filters/README.md) | How filtering works; field vs dedicated filters |
| [Built-in Filters](filters/built-in-filters.md) | Boolean, Date, Range, Select, Relation, Exists, Morph, etc. |
| [Morph Filters](filters/morph-filters.md) | Filtering polymorphic relations |

## API Contract (for the frontend)

| Doc | What it covers |
|-----|----------------|
| [API Overview](api/README.md) | Prefix, auth, endpoint groups |
| [Routes](api/routes.md) | Complete endpoint reference |
| [Responses](api/responses.md) | Response envelopes, pagination meta, errors |
| [Filtering & Search](api/filtering.md) | Wire format for filters and search |

## Internals (for contributors)

| Doc | What it covers |
|-----|----------------|
| [Architecture](internals/architecture.md) | Bootstrap, service binding, discovery, request lifecycle |
| [Index Pipeline](internals/pipeline.md) | BuildQuery → ApplySearch → ApplyFilters → ApplySorting → PaginateAndTransform |
| [Menu Internals](internals/menu.md) | How the navigation tree is built |
| [Filter Internals](internals/filters.md) | `FilterCriteria`, `ApplyFiltersPipe`, field→filter wiring |

## Roadmaps

Planning and design docs (some features started here and are now shipped). See [Roadmaps](roadmaps/README.md).

---

> **Maintaining these docs:** every page is validated against the source in `src/`. When you change behavior, update the matching doc. Reference docs are grouped by audience: *guides* (task-oriented), *api* (the frontend contract), *internals* (implementation), *roadmaps* (planned/historical).
