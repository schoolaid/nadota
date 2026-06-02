# Nadota Roadmaps

This folder holds **planning and historical design documents** for the
`SchoolAid\Nadota` package (current version: **1.1.4**). These are not reference docs —
they capture the design intent, phased plans, and remaining work for various subsystems.
Each document below carries a status line at the top, checked against the actual source
code, so you can tell at a glance how much has shipped versus what is still planned.

For day-to-day, "how do I use this" reference material, see the feature guides under
[`../guides/`](../guides/) instead.

## Roadmaps

| Roadmap | Status | Summary |
|---------|--------|---------|
| [store-update.md](./store-update.md) | **Partial** | Store/Update refactor. Phases 1–4 delivered (shared trait, abstract persist service, `afterSave` on create, resource hooks); phases 5–8 (FieldProcessor, ValidationRulesBuilder, file rollback, dedicated tests) pending/optional. |
| [edit-service.md](./edit-service.md) | **Implemented** | `ResourceEditService` rework. All four phases delivered — transformed fields, eager loading, column selection, metadata/permissions, custom response resource. |
| [attachments.md](./attachments.md) | **Partial** | Attach/detach/sync across relations. Core services, controller and routes delivered for all relation types; resource-level `before*/after*` hooks and a dedicated `AttachmentRequest` are still pending. |
| [attachment-events.md](./attachment-events.md) | **Partial** | Action-event logging for attach/detach/sync. Tracking infrastructure (P2/P3/P4) delivered; only the integration test suite (P1) remains. |

## Delivered (now documented as features)

Several documents that started life in this folder describe work that is now fully
implemented and shipped. They have graduated from "roadmap" to "feature guide" — read the
guides for current, usage-oriented documentation:

- **Action Events** → [../guides/action-events.md](../guides/action-events.md)
- **Exports** → [../guides/exports.md](../guides/exports.md)
- **Attachments** → [../guides/attachments.md](../guides/attachments.md)
