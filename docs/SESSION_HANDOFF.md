# Traspaso entre sesiones (leer primero)

## Estado actual verificable
- Repositorio Git inicializado con commits `652765c`, `d0df541`, `6df22cd`, `8f548a1`, `9f648c6` (Fase 0 completa), `7431530` (MVP-01), `c7308e7` (MVP-02), `09ab9a0` (MVP-03), `746990f` (MVP-04), `8d17cc5` (MVP-05), `e49c10f` (SPA UI & Dev Server).
- Plataforma de despliegue consolidada: DonWeb Web Hosting tradicional (Ferozo) con stack PHP 8.2+ / MySQL / SPA (ADR-0001).
- **Fase 1 (MVP) en marcha:**
  - **MVP-01 COMPLETADO AL 100%:** Autenticación segura y RBAC (`AuthController`, `AuthMiddleware`, `User`, `Session`, `Audit`) con 19/19 pruebas superadas (commit `7431530`).
  - **MVP-02 COMPLETADO AL 100%:** Consulta de usuarios con alcance y permisos de asignación (`UserController`, `User.php`) con 23/23 pruebas superadas (commit `c7308e7`).
  - **MVP-03 COMPLETADO AL 100%:** CRUD completo de tareas personales y del área (`TaskController`, `Task.php`) con 24/24 pruebas superadas (commit `09ab9a0`).
  - **MVP-04 COMPLETADO AL 100%:** Tablero Kanban individual/general y motor de transiciones (`BoardController`, `BoardColumn`, `Task::transition`) con 18/18 pruebas superadas (commit `746990f`).
  - **MVP-05 COMPLETADO AL 100%:** Subtareas, comentarios contextuales y adjuntos/evidencias seguros (`SubtaskController`, `CommentController`, `Subtask`, `Comment`, `UploadHandler`) con 22/22 pruebas superadas (commit `8d17cc5`).
  - **MVP-06 COMPLETADO AL 100%:** Circuito formal de aprobaciones y confirmación de cierre (`ApprovalController`, `Approval`, `User`, `TaskController`, bandeja de jefatura en SPA y modales) con 23/23 pruebas superadas al 100%.
  - **MVP-07 COMPLETADO AL 100%:** Dependencias secuenciales/paralelas entre tareas y motor de Grafo Acíclico Dirigido DAG (`DependencyController`, `TaskDependency`, detección de ciclos RN-07, regla AND RN-08, auto-bloqueo y desbloqueo en cascada RF-16). Suite `tests/dependencies_test.php` con 23/23 pruebas superadas al 100%.
- Total acumulado sin regresiones: **152 pruebas automatizadas superadas con 100% PASS**.

## Decisiones cerradas
- Ver D-001 a D-011 y **ADR-0001** en `docs/DECISIONS.md`.
- Especificación UX en `docs/UX_WIREFRAMES.md`.
- Modelo de datos y contratos API en `docs/DATA_MODEL_AND_API.md`.
- Mecanismo de autenticación y RBAC validado en `MVP-01`.
- Política de visibilidad y asignación por alcance validada en `MVP-02`.
- Ciclo de vida y reglas de asignación de tareas validadas en `MVP-03`.
- Modelo de estados funcionales y transiciones Kanban validadas en `MVP-04`.
- Gestión de subtareas, comentarios y evidencias seguras validadas en `MVP-05`.
- Circuito formal de aprobaciones, devoluciones con observaciones y confirmación de cierre validadas en `MVP-06`.
- Grafo de dependencias DAG, prevención de ciclos y desbloqueo en cascada validadas en `MVP-07`.

## Bloqueos y pendientes
- Sin bloqueos técnicos. Siguiente hito del MVP: **MVP-08** (Automatizaciones y notificaciones idempotentes).

## Próximo paso recomendado
1. Desarrollar **MVP-08**: Diseñar e implementar el motor de automatizaciones (reglas evento-condición-acción) y centro de notificaciones internas idempotentes (`notificaciones`, prevención de duplicados por clave única/hash de evento, alertas de asignación, cambio de estado y aprobación).

## Datos para la próxima sesión
- Servidor local activo en segundo plano: `http://127.0.0.1:8000` (despacha Front Controller de API y SPA con SQLite fallback).
- Pruebas acumuladas: 152 pruebas automatizadas aprobadas con 100% PASS:
  - Suite de dependencias y motor DAG: `php tests/dependencies_test.php` -> 23/23 PASS (100%).
  - Suite de aprobaciones y circuito formal: `php tests/approvals_test.php` -> 23/23 PASS (100%).
  - Suite de subtareas y comentarios: `php tests/subtasks_comments_test.php` -> 22/22 PASS (100%).
  - Suite de kanban: `php tests/kanban_test.php` -> 18/18 PASS (100%).
  - Suite de tareas: `php tests/tasks_test.php` -> 24/24 PASS (100%).
  - Suite de usuarios: `php tests/users_test.php` -> 23/23 PASS (100%).
  - Suite de autenticación: `php tests/auth_test.php` -> 19/19 PASS (100%).
  - Suite de salud: `php tests/healthcheck_test.php` -> PASS (100%).
- Siguiente tarea: MVP-08 (Automatizaciones y notificaciones idempotentes).

Actualizar este documento al FINAL de cada avance, sin prometer trabajo futuro automático.

