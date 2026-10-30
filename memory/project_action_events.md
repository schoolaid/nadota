---
name: project-action-events
description: Estado del sistema de Action Events en Nadota — qué está implementado, qué falta, bugs conocidos
metadata:
  type: project
---

El sistema de Action Events está implementado. Registra operaciones CRUD, acciones custom y attach/detach/sync en la tabla `action_events`.

**Estado (verificado 2026-10-29, v1.2.6):**
- Tests: `tests/Unit/Models/ActionEventTest.php` (19), `tests/Unit/Services/ActionEventServiceTest.php` (30), `tests/ServiceIntegration/ActionEventIntegrationTest.php` (15), más `ActionEventAuthorizationTest.php` y `ActionEventLookupTest.php` en `tests/ServiceIntegration/`.
- Endpoints: `GET /nadota-api/{resourceKey}/resource/{id}/action-events` y `.../action-events/{eventId}`. Desde v1.2.6 resuelven el registro con `getQuery()` y exigen `view` (403/404).
- `ActionEventService::record()` (v1.1.0) permite registrar eventos fuera del panel; acepta `?int $actionableId` desde v1.1.4.
- Los eventos se buscan por clase del modelo **y** por morph alias.

**Pendiente:**
- Suite de integración para eventos de attachments (`tests/ServiceIntegration/AttachmentEventIntegrationTest.php`, ver `docs/roadmaps/attachment-events.md`).
- La rama remota `claude/document-event-tracking-e2O5f` registra Actions fallidas y ejecuciones standalone; no está integrada en `main`.

**Bugs corregidos (2026-05-17):**
- `shouldTrackActions()` lee `nadota.action_events.enabled`. La clave vieja `nadota.track_actions` sigue en `config/nadota.php` pero ya nada la lee.
- `logSync` error handler protegido con try/catch anidado.
- `ActionEvent::getChangedFields()` usa `getAttribute()` en vez de las propiedades internas `$this->original/$this->changes` de Eloquent.

**Why:** Evitar repetir trabajo ya hecho y saber qué queda abierto.
**How to apply:** Al agregar tracking, usar `nadota.action_events.enabled` como flag.
