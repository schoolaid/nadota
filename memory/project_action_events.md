---
name: project-action-events
description: Estado del sistema de Action Events en Nadota — qué está implementado, qué falta, bugs conocidos
metadata:
  type: project
---

El sistema de Action Events está 100% implementado. Registra operaciones CRUD y acciones custom en la tabla `action_events`.

**Completado (2026-05-17):**
- 57 tests escritos: `tests/Unit/Models/ActionEventTest.php` (19), `tests/Unit/Services/ActionEventServiceTest.php` (23), `tests/ServiceIntegration/ActionEventIntegrationTest.php` (15)
- Endpoint `show` implementado: `GET /nadota-api/{resourceKey}/resource/{id}/action-events/{eventId}`
- 4 bugs corregidos en producción (ver abajo)

**Bugs corregidos:**
- `shouldTrackActions()` ahora lee `nadota.action_events.enabled` (antes usaba flag duplicado `nadota.track_actions`)
- `ResourceServiceProvider` también corregido con el mismo flag
- `logSync` error handler protegido con try/catch anidado
- `ActionEvent::getChangedFields()` usaba `$this->original/$this->changes` que son propiedades internas de Eloquent (dirty tracking) — corregido con `getAttribute()`

**Why:** El sistema estaba funcional para operaciones CRUD pero sin coverage de tests y con bugs silenciosos.
**How to apply:** Al agregar features de tracking, usar `nadota.action_events.enabled` como config flag. El endpoint show es `GET .../action-events/{eventId}`.
