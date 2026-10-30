---
name: project-action-events
description: Estado del sistema de Action Events en Nadota — qué está implementado, qué falta, bugs conocidos
metadata:
  type: project
---

El sistema de Action Events está implementado. Registra operaciones CRUD, acciones custom y attach/detach/sync en la tabla `action_events`.

**Estado (verificado 2026-10-30, v1.2.6 + Unreleased):**
- Tests: `tests/Unit/Models/ActionEventTest.php` (19), `tests/Unit/Services/ActionEventServiceTest.php` (34), `tests/ServiceIntegration/ActionEventIntegrationTest.php` (15), `AttachmentEventIntegrationTest.php` (9), más `ActionEventAuthorizationTest.php` y `ActionEventLookupTest.php` en `tests/ServiceIntegration/`.
- `action_events.track_fields` / `track_original` / `track_changes` se respetan (antes nadie los leía). Las claves `nadota.track_actions` y `action_events.table` se eliminaron (nadie las leía).
- Endpoints: `GET /nadota-api/{resourceKey}/resource/{id}/action-events` y `.../action-events/{eventId}`. Desde v1.2.6 resuelven el registro con `getQuery()` y exigen `view` (403/404).
- `ActionEventService::record()` (v1.1.0) permite registrar eventos fuera del panel; acepta `?int $actionableId` desde v1.1.4.
- Los eventos se buscan por clase del modelo **y** por morph alias.

**Pendiente:**
- La rama remota `claude/document-event-tracking-e2O5f` registra Actions fallidas y ejecuciones standalone; no está integrada en `main`.

**Bugs corregidos (2026-05-17):**
- `shouldTrackActions()` lee `nadota.action_events.enabled`.
- `logSync` error handler protegido con try/catch anidado.
- `ActionEvent::getChangedFields()` usa `getAttribute()` en vez de las propiedades internas `$this->original/$this->changes` de Eloquent.

**Why:** Evitar repetir trabajo ya hecho y saber qué queda abierto.
**How to apply:** Al agregar tracking, usar `nadota.action_events.enabled` como flag.
