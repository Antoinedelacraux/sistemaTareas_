# Traspaso entre sesiones (leer primero)

## Estado actual verificable
- Repositorio Git inicializado con commits `652765c`, `d0df541`, `6df22cd`, `8f548a1`, `9f648c6` (Fase 0 completa), `7431530` (MVP-01), `c7308e7` (MVP-02), `09ab9a0` (MVP-03) y `746990f` (MVP-04).
- Plataforma de despliegue consolidada: DonWeb Web Hosting tradicional (Ferozo) con stack PHP 8.2+ / MySQL / SPA (ADR-0001).
- **Fase 1 (MVP) en marcha:**
  - **MVP-01 COMPLETADO AL 100%:** Autenticación segura y RBAC (`AuthController`, `AuthMiddleware`, `User`, `Session`, `Audit`) con 19/19 pruebas superadas (commit `7431530`).
  - **MVP-02 COMPLETADO AL 100%:** Consulta de usuarios con alcance y permisos de asignación (`UserController`, `User.php`) con 23/23 pruebas superadas (commit `c7308e7`).
  - **MVP-03 COMPLETADO AL 100%:** CRUD completo de tareas personales y del área (`TaskController`, `Task.php`) con 24/24 pruebas superadas (commit `09ab9a0`).
  - **MVP-04 COMPLETADO AL 100%:** Tablero Kanban individual/general y motor de transiciones (`BoardController`, `BoardColumn`, `Task::transition`) con 18/18 pruebas superadas (commit `746990f`).
  - **MVP-05 COMPLETADO AL 100%:** Subtareas, comentarios contextuales y adjuntos/evidencias seguros (`SubtaskController`, `CommentController`, `Subtask`, `Comment`, `UploadHandler`). Soporte para checklists con completitud toggle y eliminación; comentarios de texto y evidencias con subida segura de archivos en `storage/uploads/` con nombres hasheados únicos, validación de extensiones y cuotas de 10 MB; descarga binaria protegida por autorización y alcance (anti-IDOR/BOLA). Suite `tests/subtasks_comments_test.php` con 22/22 pruebas superadas al 100%.
- No se detectan regresiones (106 pruebas automatizadas acumuladas aprobadas con 100% PASS).

## Decisiones cerradas
- Ver D-001 a D-011 y **ADR-0001** en `docs/DECISIONS.md`.
- Especificación UX en `docs/UX_WIREFRAMES.md`.
- Modelo de datos y contratos API en `docs/DATA_MODEL_AND_API.md`.
- Mecanismo de autenticación y RBAC validado en `MVP-01`.
- Política de visibilidad y asignación por alcance validada en `MVP-02`.
- Ciclo de vida y reglas de asignación de tareas validadas en `MVP-03`.
- Modelo de estados funcionales y transiciones Kanban validadas en `MVP-04`.
- Gestión de subtareas, comentarios y evidencias seguras validadas en `MVP-05`.

## Bloqueos y pendientes
- Sin bloqueos técnicos. Siguiente hito del MVP: **MVP-06** (Confirmación de cierre y circuito formal de aprobaciones con revisión por jefatura).

## Próximo paso recomendado
1. Desarrollar **MVP-06**: Implementar controlador `ApprovalController.php` (`POST /api/tareas/{id}/solicitar-aprobacion`, `POST /api/tareas/{id}/resolver-aprobacion`, `GET /api/aprobaciones/pendientes`) y modelo `Approval.php` (`app/Models/Approval.php`). Validar resolución de aprobación (`APROBADA` -> `FINALIZADA` atómica / `DEVUELTA` -> `EN_PROCESO` obligando `observaciones_devolucion` no vacías) con permisos exclusivos para jefes y revisores asignados (**RF-13, RN-10**).

## Datos para la próxima sesión
- Últimos commits verificados:
  - `8d17cc5` (`feat(tasks): implement subtasks, comments and secure file attachments MVP-05`).
  - `e49c10f` (`feat(ui): implement interactive spa frontend and local development server`).
- Servidor local activo en segundo plano: `http://127.0.0.1:8000` (despacha Front Controller de API y SPA con SQLite fallback).
- Pruebas acumuladas: 106 pruebas automatizadas aprobadas con 100% PASS:
  - Suite de subtareas y comentarios: `php tests/subtasks_comments_test.php` -> 22/22 PASS (100%).
  - Suite de kanban: `php tests/kanban_test.php` -> 18/18 PASS (100%).
  - Suite de tareas: `php tests/tasks_test.php` -> 24/24 PASS (100%).
  - Suite de usuarios: `php tests/users_test.php` -> 23/23 PASS (100%).
  - Suite de autenticación: `php tests/auth_test.php` -> 19/19 PASS (100%).
  - Suite de salud: `php tests/healthcheck_test.php` -> PASS (100%).
- Siguiente tarea: MVP-06 (Confirmación de cierre y circuito formal de aprobaciones).

Actualizar este documento al FINAL de cada avance, sin prometer trabajo futuro automático.
