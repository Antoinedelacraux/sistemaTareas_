# Registro de avances

## Registro de avances

### 2026-10-13 — SETUP-00 / Inicialización y validación del entorno de agentes y documentación
- **Estado:** completado
- **Objetivo y RF/RN:** Inicializar y validar el entorno del workspace: perfiles de agentes, reglas permanentes, skills operativos, alineación con requisitos V1.2 (RF-01 a RF-19, RN-01 a RN-15).
- **Archivos creados/modificados:** `.gitignore`, `docs/PROGRESS.md`, `docs/SESSION_HANDOFF.md`, `docs/CHANGELOG.md`.
- **Decisiones y motivo:** Validada la estructura completa. Creado `.gitignore` a partir de `templates/gitignore.snippet`. Repositorio Git inicializado y `html.html` vacío retirado del staging.
- **Pruebas ejecutadas:** `git status --short`, `git commit`. Verificación estructural de todos los archivos y contratos.
- **Documentación actualizada:** `docs/PROGRESS.md`, `docs/SESSION_HANDOFF.md`, `docs/CHANGELOG.md`.
- **Git:** Commit inicial `652765c` (`chore(agents): initialize project agent configuration and documentation`).
- **Deuda técnica / riesgos:** Ninguna en configuración.
- **Siguiente paso verificable:** Iniciar Fase 0 y consolidar arquitectura/stack para despliegue en producción.

### 2026-10-13 — SETUP-01 / SETUP-02: Consolidación del stack técnico para DonWeb Ferozo (ADR-0001)
- **Estado:** completado
- **Objetivo y RF/RN:** D-011, ADR-0001, sustitución de P-004. Consolidar el stack técnico y la arquitectura física adaptada al Web Hosting tradicional con panel Ferozo de DonWeb.
- **Archivos creados/modificados:** `docs/DECISIONS.md`, `docs/ARCHITECTURE.md`, `docs/PROJECT_CONTEXT.md`, `docs/ROADMAP.md`, `docs/PROGRESS.md`, `docs/SESSION_HANDOFF.md`, `docs/CHANGELOG.md`.
- **Decisiones y motivo:** Se aprueba formalmente D-011 y ADR-0001: Backend PHP 8.2+ (REST API modular), Base de datos MySQL / MariaDB (InnoDB, transacciones ACID), Frontend SPA moderna (HTML5, Vanilla CSS / Design Tokens, JavaScript modular). Se descarta P-004 (FastAPI + PostgreSQL) por incompatibilidad operativa con hosting compartido Ferozo sin demonios continuos.
- **Pruebas ejecutadas:** Validación de compatibilidad técnica con DonWeb Ferozo, revisión cruzada de consistencia documental.
- **Documentación actualizada:** `docs/DECISIONS.md`, `docs/ARCHITECTURE.md`, `docs/PROJECT_CONTEXT.md`, `docs/ROADMAP.md`, `docs/PROGRESS.md`, `docs/SESSION_HANDOFF.md`, `docs/CHANGELOG.md`.
- **Git:** Commit `d0df541` (`docs(architecture): consolidate stack for DonWeb Ferozo hosting ADR-0001`).
- **Deuda técnica / riesgos:** Asegurar aislamiento del código PHP fuera de la raíz pública o mediante `.htaccess` en el servidor web Apache de Ferozo.
- **Siguiente paso verificable:** SETUP-03 (Diseñar navegación y wireframes accesibles por rol) y SETUP-04 (Diseñar modelo relacional MySQL y contratos de la API REST).

### 2026-10-13 — SETUP-03: Diseño de navegación, flujos y wireframes por rol
- **Estado:** completado
- **Objetivo y RF/RN:** RF-03, RF-04, RF-05, RF-06, RF-07, RF-08, RF-12, RF-13, RN-01, RN-02, RN-03, RN-04, RN-10, RN-11, RN-12. Diseñar la arquitectura de información, mapas de navegación, wireframes estructurales para la vista de Asistente y vista de Jefe, flujos modales de confirmación de cierre y devolución de revisiones, y sistema de tokens de diseño UI (modo claro/oscuro).
- **Archivos creados/modificados:** `docs/UX_WIREFRAMES.md`, `docs/ROADMAP.md`, `docs/PROGRESS.md`, `docs/SESSION_HANDOFF.md`, `docs/CHANGELOG.md`.
- **Decisiones y motivo:** Formalizada la experiencia de usuario en `docs/UX_WIREFRAMES.md`. Se garantiza en UI la autoasignación obligatoria de asistentes, el modal bloqueante de confirmación para cierre de tareas sin aprobación (con advertencia de impacto en DAG), la bandeja de aprobaciones para jefes, y la accesibilidad con soporte por teclado como alternativa a Drag & Drop.
- **Pruebas ejecutadas:** Verificación de correspondencia con requisitos funcionales y reglas de negocio V1.2.
- **Documentación actualizada:** `docs/UX_WIREFRAMES.md`, `docs/ROADMAP.md`, `docs/PROGRESS.md`, `docs/SESSION_HANDOFF.md`, `docs/CHANGELOG.md`.
- **Git:** Commit `6df22cd` (`docs(ux): create wireframes and navigation design for assistant and manager roles SETUP-03`).
- **Deuda técnica / riesgos:** Ninguna en diseño.
- **Siguiente paso verificable:** SETUP-04 (Diseñar casos de uso, modelo de datos relacional MySQL y especificación de endpoints API REST).

### 2026-10-13 — SETUP-04: Diseño de casos de uso, modelo de datos relacional MySQL y contratos API REST
- **Estado:** completado
- **Objetivo y RF/RN:** RF-01 a RF-19, RN-01 a RN-15. Diseñar los casos de uso nucleares (CU-01 a CU-10), el modelo entidad-relación, el script DDL SQL completo para MySQL 8 / MariaDB (InnoDB), los algoritmos de detección de ciclos en grafos acíclicos (DAG) y desbloqueo atómico AND, y el catálogo exhaustivo de contratos y endpoints de la API REST en PHP 8.2+.
- **Archivos creados/modificados:** `docs/DATA_MODEL_AND_API.md`, `docs/ROADMAP.md`, `docs/PROGRESS.md`, `docs/SESSION_HANDOFF.md`, `docs/CHANGELOG.md`.
- **Decisiones y motivo:** Formalizado en `docs/DATA_MODEL_AND_API.md`. Estructura relacional con 10 tablas optimizadas con índices y llaves foráneas. Autorización garantizada en backend (asistente solo autoasigna y solo consulta sus tareas; jefe administra tareas oficiales). Transacciones ACID para transiciones de estado, confirmación de cierre y desbloqueo concurrente de dependientes.
- **Pruebas ejecutadas:** Verificación de integridad referencial SQL, análisis de consistencia de tipos de datos, correspondencia de contratos REST con especificación V1.2.
- **Documentación actualizada:** `docs/DATA_MODEL_AND_API.md`, `docs/ROADMAP.md`, `docs/PROGRESS.md`, `docs/SESSION_HANDOFF.md`, `docs/CHANGELOG.md`.
- **Git:** Commit `8f548a1` (`docs(api): design data model, mysql ddl and rest contracts SETUP-04`).
- **Deuda técnica / riesgos:** Ninguna en modelado.
- **Siguiente paso verificable:** SETUP-05 (Preparar estructura del proyecto, script de base de datos y entorno local compatible con DonWeb Ferozo).

### 2026-10-13 — SETUP-05: Preparación de estructura del proyecto, base de datos y bootstrap para DonWeb Ferozo
- **Estado:** completado
- **Objetivo y RF/RN:** Culminar la Fase 0 (Preparación). Crear la estructura de directorios física del monolito modular para DonWeb Ferozo (`public_html/` y `app/`), scripts SQL completos (`database/schema.sql` y `database/seeds.sql`), configuración segura `.env.example`, autoloader PSR-4, Front Controller de la API REST (`public_html/api/index.php`), reglas Apache `.htaccess`, shell inicial de la SPA con Design Tokens, y protección de directorio de adjuntos `storage/uploads/.htaccess`.
- **Archivos creados/modificados:** `database/schema.sql`, `database/seeds.sql`, `.env.example`, `app/.htaccess`, `app/Config/Env.php`, `app/Config/Database.php`, `app/Core/Response.php`, `app/Core/Router.php`, `app/bootstrap.php`, `public_html/api/index.php`, `public_html/.htaccess`, `public_html/assets/css/variables.css`, `public_html/assets/css/app.css`, `public_html/assets/js/app.js`, `public_html/index.html`, `storage/uploads/.htaccess`, `tests/healthcheck_test.php`, `docs/ROADMAP.md`, `docs/PROGRESS.md`, `docs/SESSION_HANDOFF.md`, `docs/CHANGELOG.md`.
- **Decisiones y motivo:** Estructura desacoplada y lista para producción en Ferozo. El backend PHP está completamente aislado de acceso web directo vía `app/.htaccess`. Los adjuntos en `storage/uploads/` tienen deshabilitada la ejecución de scripts.
- **Pruebas ejecutadas:**
  1. `php -l` en los 6 archivos PHP: 0 errores de sintaxis (`No syntax errors detected`).
  2. `php tests/healthcheck_test.php`: PASS. El endpoint `/api/health` despachó exitosamente JSON Envelope con status `ok`, versión `1.2.0` y runtime `PHP 8.4.25`.
- **Documentación actualizada:** `docs/ROADMAP.md`, `docs/PROGRESS.md`, `docs/SESSION_HANDOFF.md`, `docs/CHANGELOG.md`.
- **Git:** Commit `9f648c6` (`feat(setup): initialize directory structure, schema, seeds and api bootstrap for Ferozo SETUP-05`).
- **Deuda técnica / riesgos:** Ninguna. Fase 0 completada al 100%.
- **Siguiente paso verificable:** Iniciar Fase 1 (MVP) con la tarea **MVP-01** (Autenticación, RBAC y pruebas negativas de acceso).

### 2026-10-13 — MVP-01: Autenticación, RBAC y pruebas negativas de acceso
- **Estado:** completado
- **Objetivo y RF/RN:** RF-01, RF-02, RF-07, RN-13. Implementar autenticación segura basada en sesiones criptográficas (SHA-256 en DB, token crudo de 256 bits), control de acceso por roles (`ASISTENTE`, `JEFE_PERSONAL`, `JEFE_PLANEAMIENTO`), hashing de contraseñas con Bcrypt, cookies seguras `HttpOnly; SameSite=Strict`, middleware `AuthMiddleware`, y endpoints `POST /api/auth/login`, `POST /api/auth/logout`, `GET /api/auth/me`.
- **Archivos creados/modificados:** `app/Models/User.php`, `app/Models/Session.php`, `app/Models/Audit.php`, `app/Core/Middleware/AuthMiddleware.php`, `app/Controllers/AuthController.php`, `app/Core/Response.php`, `public_html/api/index.php`, `tests/auth_test.php`, `docs/ROADMAP.md`, `docs/PROGRESS.md`, `docs/SESSION_HANDOFF.md`, `docs/CHANGELOG.md`.
- **Decisiones y motivo:** Implementado control estricto de sesiones y roles en backend. Manejo de errores con código `ERR_INVALID_CREDENTIALS` (401), bloqueo de cuentas inactivas con `ERR_USER_INACTIVE` (403), validación de campos obligatorios con `ERR_VALIDATION_FAILED` (422) y registro inmutable en `auditoria_eventos` de todos los accesos e intentos fallidos.
- **Pruebas ejecutadas:**
  1. `php -l` en todos los archivos: 0 errores de sintaxis (`No syntax errors detected`).
  2. `php tests/healthcheck_test.php`: PASS (regresión sin fallos).
  3. `php tests/auth_test.php`: 19 de 19 pruebas unitarias, de integración y negativas superadas con 100% PASS (login asistente, login jefe, contraseñas erróneas, emails inexistentes, campos vacíos, cuentas inactivas, verificación de middleware por token y por rol, logout y revocación, y trazabilidad en auditoría).
- **Documentación actualizada:** `docs/ROADMAP.md`, `docs/PROGRESS.md`, `docs/SESSION_HANDOFF.md`, `docs/CHANGELOG.md`.
- **Git:** Commit `7431530` (`feat(auth): implement authentication, session management and rbac with negative tests MVP-01`).
- **Deuda técnica / riesgos:** Ninguna. Cobertura completa de casos positivos y negativos en backend.
- **Siguiente paso verificable:** MVP-02 (Usuarios, ámbitos y permisos de asignación).

### 2026-10-13 — MVP-02: Usuarios, ámbitos y permisos de asignación
- **Estado:** completado
- **Objetivo y RF/RN:** RF-02, RF-05, RF-06, RF-07, RN-03, RN-04, RN-05. Implementar gestión y consulta de usuarios con control estricto de visibilidad por alcance en backend. Asistentes únicamente pueden listarse a sí mismos y asignarse tareas a sí mismos; cualquier intento de listar o consultar perfiles de otros compañeros es bloqueado con 403 Forbidden (`ERR_FORBIDDEN_USER_ACCESS`, protección anti-IDOR/BOLA). Jefes pueden listar y consultar a todo el personal activo del área para asignaciones y supervisión oficial.
- **Archivos creados/modificados:** `app/Models/User.php`, `app/Controllers/UserController.php`, `app/Core/Response.php`, `app/Core/Router.php`, `public_html/api/index.php`, `tests/users_test.php`, `docs/ROADMAP.md`, `docs/PROGRESS.md`, `docs/SESSION_HANDOFF.md`, `docs/CHANGELOG.md`.
- **Decisiones y motivo:**
  - `User::listForUser`: si el usuario es asistente, retorna únicamente su propio perfil en selectores de asignación para prevenir fuga de información y accesos no autorizados. Si es jefe, lista a todo el equipo con soporte para filtros `?rol=` y `?buscar=`.
  - `User::canViewUser`: rechaza con 403 Forbidden intentos de asistentes de examinar datos de otros usuarios por ID (`/api/usuarios/{id}`).
  - `User::validateAssignment`: verifica formalmente que asistentes solo se autoasignen y que jefes asignen únicamente a usuarios activos existentes.
  - `Router` y `Response`: integración de flag `$hasSentResponse` para interrumpir limpiamente la cadena de despacho cuando un middleware o handler emite una respuesta de error.
- **Pruebas ejecutadas:**
  1. `php tests/healthcheck_test.php`: PASS (200 OK).
  2. `php tests/auth_test.php`: 19/19 PASS (100%).
  3. `php tests/users_test.php`: 23 de 23 pruebas automatizadas superadas al 100% (autenticación requerida, visibilidad asistente vs jefe, filtros de búsqueda, protección IDOR para asistentes 403, 404 para IDs inexistentes, validación de asignaciones por rol y despacho HTTP real del Router).
- **Documentación actualizada:** `docs/ROADMAP.md`, `docs/PROGRESS.md`, `docs/SESSION_HANDOFF.md`, `docs/CHANGELOG.md`.
- **Git:** Commit `c7308e7` (`feat(users): implement scoped user listing, assignment authorization and anti-idor protection MVP-02`).
- **Deuda técnica / riesgos:** Ninguna.
- **Siguiente paso verificable:** MVP-03 (CRUD tareas personales y oficiales).

### 2026-10-13 — MVP-03: CRUD tareas personales y oficiales
- **Estado:** completado
- **Objetivo y RF/RN:** RF-03, RF-04, RF-05, RF-06, RF-07, RF-09, RN-01, RN-02, RN-03, RN-04, RN-05, RN-13. Implementar el ciclo completo de vida de tareas personales y del área. Asistentes pueden crear tareas personales privadas y oficiales de área forzándose obligatoriamente como responsables a sí mismos; intentos de asignar a otros asistentes o compañeros son rechazados con 403 Forbidden (`ERR_FORBIDDEN_ASSIGNMENT`). Asistentes no pueden consultar tareas de otros compañeros (`403 Forbidden` `ERR_FORBIDDEN_TASK_ACCESS`, anti-IDOR/BOLA). Jefes pueden supervisar todas las tareas del área, asignar y reasignar tareas a cualquier miembro activo, respetando la privacidad de tareas personales de asistentes. Registro inmutable en auditoría para creaciones, actualizaciones y eliminaciones.
- **Archivos creados/modificados:** `app/Models/Task.php`, `app/Controllers/TaskController.php`, `public_html/api/index.php`, `tests/tasks_test.php`, `docs/ROADMAP.md`, `docs/PROGRESS.md`, `docs/SESSION_HANDOFF.md`, `docs/CHANGELOG.md`.
- **Decisiones y motivo:**
  - Código correlativo único generado como `TAR-YYYY-NNNN`.
  - `Task::listForUser`: evalúa el rol y filtra a nivel SQL; el asistente solo recibe tareas donde es responsable o creador; el jefe recibe todas las del área y sus propias personales.
  - `Task::canView`: impide que un asistente examine tareas de otros asistentes, e impide que un jefe examine tareas personales privadas de sus subordinados (RN-01).
  - `Task::update`: bloquea reasignaciones (`responsable_id`) realizadas por asistentes con 403 Forbidden (`ERR_FORBIDDEN_REASSIGNMENT`); solo jefes pueden reasignar responsables.
  - Validación de campos obligatorios, longitud máxima de título (255 caracteres) y enumeraciones de prioridad y tipo de tarea.
- **Pruebas ejecutadas:**
  1. `php tests/healthcheck_test.php`: PASS (200 OK).
  2. `php tests/auth_test.php`: 19/19 PASS (100%).
  3. `php tests/users_test.php`: 23/23 PASS (100%).
  4. `php tests/tasks_test.php`: 24 de 24 pruebas automatizadas superadas al 100% (autenticación obligatoria, creación personal, bloqueo de asignación asistente a compañero 403, asignación por jefe 201, filtros de alcance por servidor, protección anti-IDOR 403, privacidad de tarea personal ante jefe 403, validación de títulos y prioridades 422, reasignación por jefe, control de eliminación y despacho Router HTTP real).
- **Documentación actualizada:** `docs/ROADMAP.md`, `docs/PROGRESS.md`, `docs/SESSION_HANDOFF.md`, `docs/CHANGELOG.md`.
- **Git:** Commit `09ab9a0` (`feat(tasks): implement crud for personal and area tasks with scope and assignment rules MVP-03`).
- **Deuda técnica / riesgos:** Ninguna. Cobertura del 100% en backend.
- **Siguiente paso verificable:** MVP-04 (Kanban individual/general y transiciones).

### 2026-10-13 — MVP-04: Kanban individual/general y transiciones
- **Estado:** completado
- **Objetivo y RF/RN:** RF-08, RF-11, RF-12, RN-06, RN-10, RN-11, RN-12, RN-13. Implementar vistas agrupadas del tablero Kanban (individual y general) y el motor de transiciones de estado de tareas entre columnas respetando la integridad funcional de los 5 estados nucleares (`PENDIENTE`, `EN_PROCESO`, `BLOQUEADA`, `EN_REVISION`, `FINALIZADA`). Reglas críticas blindadas: requerimiento obligatorio de confirmación explícita (`confirmacion_cierre: true`) para cerrar tareas sin aprobación (422 si falta o es false); bloqueo con 409 Conflict ante cualquier intento de mover directamente a Finalizada una tarea que exige aprobación previa; reseteo automático de `fecha_finalizada` en reaperturas; y registro detallado en auditoría inmutable.
- **Archivos creados/modificados:** `app/Models/BoardColumn.php`, `app/Models/Task.php`, `app/Controllers/BoardController.php`, `public_html/api/index.php`, `tests/kanban_test.php`, `docs/ROADMAP.md`, `docs/PROGRESS.md`, `docs/SESSION_HANDOFF.md`, `docs/CHANGELOG.md`.
- **Decisiones y motivo:**
  - `BoardColumn`: encapsula la configuración de columnas activas y la resolución por tipo funcional de estado (RN-06).
  - `BoardController::kanban`: agrupa tareas en columnas bajo el estricto alcance del solicitante (asistente solo ve sus tareas; jefe ve las de área del equipo) y computa métricas de resumen en tiempo real.
  - `Task::transition`: aplica validaciones atómicas; rechaza saltos no autorizados a Finalizada (409 Conflict para tareas con aprobación; 422 para tareas sin aprobación no confirmadas). Registra fecha de finalización y evento de auditoría.
- **Pruebas ejecutadas:**
  1. `php tests/healthcheck_test.php`: PASS (200 OK).
  2. `php tests/auth_test.php`: 19/19 PASS (100%).
  3. `php tests/users_test.php`: 23/23 PASS (100%).
  4. `php tests/tasks_test.php`: 24/24 PASS (100%).
  5. `php tests/kanban_test.php`: 18 de 18 pruebas automatizadas superadas al 100% (listado de columnas, agrupación Kanban individual vs general por alcance, transiciones básicas, bloqueo de transiciones en tareas ajenas 403, rechazo de cierre sin confirmación 422, estado intacto tras rechazo, cierre confirmado 200 con fecha, rechazo de cierre directo en tareas con aprobación 409, transición a revisión 200, reapertura con reseteo de fecha, supervisión de jefatura y despacho Router HTTP). Total acumulado: 84 pruebas superadas.
- **Documentación actualizada:** `docs/ROADMAP.md`, `docs/PROGRESS.md`, `docs/SESSION_HANDOFF.md`, `docs/CHANGELOG.md`.
- **Git:** Commit `746990f` (`feat(kanban): implement grouped board views and task state transitions with closing confirmation MVP-04`).
- **Deuda técnica / riesgos:** Ninguna.
- **Siguiente paso verificable:** MVP-05 (Subtareas, comentarios, adjuntos seguros).

### 2026-10-13 — MVP-05: Subtareas, comentarios, adjuntos seguros
- **Estado:** completado
- **Objetivo y RF/RN:** RF-10, RF-07, RN-02, RN-05. Implementar el módulo de checklist de avance (subtareas) y comunicación contextual (comentarios y evidencias adjuntas con subida y descarga blindada de archivos). Soporte para marcar completitud, eliminación autorizada y orden de subtareas; comentarios con indicación de evidencia y metadatos de archivo; almacenamiento en `storage/uploads/` con nombres hasheados únicos (anti-Path Traversal y anti-ejecución); validación estricta de extensiones permitidas (PDF, imágenes, hojas de cálculo, documentos, texto), bloqueo de scripts (.php) y cuota máxima de 10 MB. Descarga binaria protegida por token y validación de alcance sobre la tarea padre (anti-IDOR/BOLA).
- **Archivos creados/modificados:** `app/Core/UploadHandler.php`, `app/Models/Subtask.php`, `app/Models/Comment.php`, `app/Controllers/SubtaskController.php`, `app/Controllers/CommentController.php`, `app/Core/Router.php`, `public_html/api/index.php`, `tests/subtasks_comments_test.php`, `docs/ROADMAP.md`, `docs/PROGRESS.md`, `docs/SESSION_HANDOFF.md`, `docs/CHANGELOG.md`.
- **Decisiones y motivo:**
  - `UploadHandler`: tratamiento de adjuntos como no confiables, almacenamiento desacoplado de nombres originales y cabeceras seguras `X-Content-Type-Options: nosniff` en descargas autorizadas.
  - `Router.php`: soporte unificado para payloads JSON y formularios `multipart/form-data` con `$_POST` y `$_FILES`.
  - Autorización en cascada: tanto subtareas como comentarios y descargas exigen que el usuario posea permisos legítimos sobre la tarea padre (`Task::canView` / `Task::canEdit`).
- **Pruebas ejecutadas:**
  1. `php tests/healthcheck_test.php`: PASS (200 OK).
  2. `php tests/auth_test.php`: 19/19 PASS (100%).
  3. `php tests/users_test.php`: 23/23 PASS (100%).
  4. `php tests/tasks_test.php`: 24/24 PASS (100%).
  5. `php tests/kanban_test.php`: 18/18 PASS (100%).
  6. `php tests/subtasks_comments_test.php`: 22 de 22 pruebas automatizadas superadas al 100% (creación de subtareas, listado ordenado, completitud toggle, eliminación, comentarios de texto, evidencias con archivos adjuntos PDF, bloqueo de archivos maliciosos .php 422, control de tamaño máximo 10MB 422, descarga autorizada y rechazo anti-IDOR en descargas 403). Total acumulado: 106 pruebas superadas sin fallos.
- **Documentación actualizada:** `docs/ROADMAP.md`, `docs/PROGRESS.md`, `docs/SESSION_HANDOFF.md`, `docs/CHANGELOG.md`.
- **Git:** Commit `8d17cc5` (`feat(tasks): implement subtasks, comments and secure file attachments MVP-05`).
- **Deuda técnica / riesgos:** Ninguna.
- **Siguiente paso verificable:** MVP-06 (Confirmación de cierre y aprobaciones).

### 2026-10-13 — SPA UI & Servidor Local de Desarrollo: Frontend interactivo conectado a la API y fallback local SQLite
- **Estado:** completado
- **Objetivo y RF/RN:** Desplegar servidor local integrado (`server.php` en puerto 8000) y cliente frontend SPA completo e interactivo con estética moderna para permitir la observación y pruebas de usuario de los avances logrados (MVP-01 a MVP-05) antes de abordar MVP-06.
- **Archivos creados/modificados:** `server.php`, `app/Config/Database.php`, `app/Core/Router.php`, `public_html/index.html`, `public_html/assets/css/app.css`, `public_html/assets/js/app.js`, `.gitignore`, `docs/PROGRESS.md`, `docs/SESSION_HANDOFF.md`.
- **Decisiones y motivo:**
  - `Database.php`: inclusión de fallback automático transparente a `storage/database.sqlite` cuando el entorno es `development` y no existe instancia MySQL local activa, con creación automática de tablas y datos semilla de los 5 usuarios y tareas demostrativas.
  - `server.php`: enrutador para el servidor embebido `php -S localhost:8000 server.php` que despacha rutas `/api/*` al Front Controller y sirve activos estáticos con sus tipos MIME correspondientes.
  - SPA UI: interfaz con modo oscuro/claro, selector rápido de perfiles para pruebas de roles, tablero Kanban de 5 columnas, modales de creación, detalle con checklist interactivo de subtareas, publicación de comentarios y evidencias, y diálogo de confirmación de cierre (**RN-11**).
- **Pruebas ejecutadas:**
  - Servidor local iniciado y activo en `http://127.0.0.1:8000`.
  - Endpoint `/api/health` respondiendo 200 OK con JSON Envelope.
  - Endpoint `/api/auth/login` validado y emitiendo tokens de sesión.
  - Batería de regresión automatizada: 106 de 106 pruebas aprobadas (100% PASS).
- **Documentación actualizada:** `docs/PROGRESS.md`, `docs/SESSION_HANDOFF.md`, `docs/CHANGELOG.md`.
- **Git:** Commit `e49c10f` (`feat(ui): implement interactive spa frontend and local development server`).
- **Siguiente paso verificable:** Proceder con MVP-06 (Confirmación de cierre y circuito formal de aprobaciones).

### 2026-10-13 — MVP-06: Confirmación de cierre y circuito formal de aprobaciones
- **Estado:** completado
- **Objetivo y RF/RN:** RF-13, RN-10, RN-11, RN-12. Implementar el circuito formal de aprobaciones para el cierre de tareas críticas y la confirmación explícita para el cierre de tareas regulares:
  1. Modelo y tabla relacional `tarea_aprobaciones` con endpoints dedicados:
     - `POST /api/tareas/{id}/solicitar-aprobacion`: envía una tarea que requiere aprobación a revisión formal (`EN_REVISION`), asignando revisor por defecto a la jefatura de planeamiento o revisor específico.
     - `POST /api/tareas/{id}/resolver-aprobacion`: dictamina la aprobación (`APROBAR` o `DEVOLVER`), protegido para jefes y revisores asignados (403 para asistentes).
     - `GET /api/aprobaciones/pendientes`: bandeja centralizada de aprobaciones pendientes para jefatura (403 para asistentes).
     - `GET /api/tareas/{id}/aprobaciones`: historial completo de aprobaciones asociadas a la tarea con dictámenes, fechas y observaciones.
  2. Cumplimiento estricto de reglas de negocio:
     - **RN-10:** Tareas con bandera `requiere_aprobacion` no pueden cerrarse directamente (409 Conflict); transicionan a `EN_REVISION` y solo tras dictamen de aprobación formal pasan a `FINALIZADA` con timestamp de finalización.
     - **RN-11:** Confirmación explícita obligatoria (`confirmacion_cierre: true`) para tareas sin aprobación para evitar cierres accidentales.
     - **RN-12:** Dictamen `DEVOLVER` exige obligatoriamente observaciones no vacías (422 Unprocessable Entity `ERR_VALIDATION_FAILED` si faltan o son solo espacios en blanco); devuelve la tarea a `EN_PROCESO`, resetea `fecha_finalizada` y preserva la traza en auditoría.
  3. Integración en SPA Frontend:
     - Pestaña "⚖️ Bandeja de Aprobaciones" visible exclusivamente para roles de jefatura con badge contador de pendientes en tiempo real.
     - Vista `#approvals-view` con tarjetas de aprobación y acciones rápidas ("Ver Detalle", "Aprobar", "Devolver").
     - Bloque `#detail-approval-box` en el modal de detalle con estado, botón para solicitar aprobación para el responsable, y botones de dictamen para jefes.
     - Modal `#modal-return-task` con textarea requerido para capturar observaciones de devolución conforme a RN-12.
- **Archivos creados/modificados:**
  - `app/Models/Approval.php` (nuevo)
  - `app/Controllers/ApprovalController.php` (nuevo)
  - `app/Models/User.php`
  - `app/Controllers/TaskController.php`
  - `app/Core/Router.php`
  - `app/Config/Database.php`
  - `public_html/api/index.php`
  - `public_html/index.html`
  - `public_html/assets/css/app.css`
  - `public_html/assets/js/app.js`
  - `tests/approvals_test.php` (nuevo)
  - `docs/ROADMAP.md`
  - `docs/PROGRESS.md`
  - `docs/SESSION_HANDOFF.md`
  - `docs/CHANGELOG.md`
- **Decisiones y motivo:**
  - `Approval::canResolve`: evalúa el rol de jefatura (`isJefe`) o revisor asignado explícito, bloqueando a asistentes con 403 Forbidden.
  - Validación atómica de observaciones en RN-12 tanto en modelo como en controlador (`trim($observaciones) === ''` gatilla error 422).
  - Manejo defensivo en `TaskController::show` para consultar `aprobacion_pendiente` asegurando compatibilidad hacia atrás en tests aislados.
  - Soporte en `Router.php` para middlewares provistos como instancias de objetos o nombres de clase.
- **Pruebas ejecutadas:**
  1. `php tests/healthcheck_test.php`: PASS (200 OK).
  2. `php tests/auth_test.php`: 19/19 PASS (100%).
  3. `php tests/users_test.php`: 23/23 PASS (100%).
  4. `php tests/tasks_test.php`: 24/24 PASS (100%).
  5. `php tests/kanban_test.php`: 18/18 PASS (100%).
  6. `php tests/subtasks_comments_test.php`: 22/22 PASS (100%).
  7. `tests/approvals_test.php`: 23 de 23 pruebas automatizadas superadas al 100% (autenticación obligatoria, solicitud de aprobación, cambio a EN_REVISION, bloqueo 403 para asistentes al resolver, bandeja de pendientes restringida a jefes, aprobación exitosa con pase a FINALIZADA y timestamp, rechazo de devolución sin observaciones 422 RN-12, devolución exitosa con retorno a EN_PROCESO, historial de aprobaciones, protección anti-IDOR en tareas ajenas y despacho HTTP completo del Router). Total acumulado: 129 pruebas superadas sin fallos.
- **Documentación actualizada:** `docs/ROADMAP.md`, `docs/PROGRESS.md`, `docs/SESSION_HANDOFF.md`, `docs/CHANGELOG.md`.
- **Git:** Commit pendiente de cierre.
- **Deuda técnica / riesgos:** Ninguna.
- **Siguiente paso verificable:** MVP-07 (Dependencias secuenciales/paralelas y DAG).

### 2026-10-13 — MVP-07: Dependencias secuenciales/paralelas y grafo DAG
- **Estado:** completado
- **Objetivo y RF/RN:** RF-14, RF-15, RF-16, RN-07, RN-08, RN-09, RN-14. Implementar el motor de dependencias de tareas basadas en Grafo Acíclico Dirigido (DAG):
  1. Modelo y tabla `tarea_dependencias` con endpoints dedicados:
     - `GET /api/tareas/{id}/dependencias`: Listado estructurado de predecesoras (requisitos previos) y sucesoras (tareas que esperan por esta), indicando estado actual y si la tarea se encuentra bloqueada por dependencias abiertas.
     - `POST /api/tareas/{id}/dependencias`: Vinculación de una predecesora con validación estricta de grafo acíclico dirigido (anti-ciclos RN-07) y auto-bloqueo preventivo (RN-08).
     - `DELETE /api/tareas/{id}/dependencias/{pred_id}`: Desvinculación de dependencia con desbloqueo automático a `PENDIENTE` si ya no tiene predecesoras pendientes.
  2. Cumplimiento de reglas de negocio:
     - **RN-07 (Anti-ciclos DAG):** Algoritmo de alcance que detecta si agregar una relación formaría un ciclo directo o indirecto, rechazando con `409 Conflict` `ERR_CYCLIC_DEPENDENCY`.
     - **RN-08 (Regla AND & Bloqueo):** Si una predecesora no está en `FINALIZADA`, la tarea sucesora se mueve automáticamente a `BLOQUEADA`. Cualquier intento de transicionar una tarea bloqueada hacia `EN_PROCESO` o `FINALIZADA` es rechazado con `409 Conflict` `ERR_TASK_BLOCKED_BY_DEPENDENCIES`.
     - **RF-16 & RN-09 (Desbloqueo en Cascada):** Cuando una tarea pasa a `FINALIZADA` (sea por transición manual o aprobación formal de jefatura), se evalúan en cascada todas sus sucesoras; si TODAS sus predecesoras alcanzaron el cierre efectivo (regla AND: 3/3), la sucesora pasa automáticamente a `PENDIENTE`.
     - **RN-14 (Reapertura):** Si una tarea finalizada se reabre o devuelve, sus sucesoras no finalizadas son re-bloqueadas preventivamente para preservar la integridad del proceso.
  3. Integración en SPA Frontend:
     - Sección de dependencias en el modal de detalle con alerta destacada de tarea bloqueada (`#detail-deps-blocked-alert`).
     - Lista de predecesoras con insignias de estado y botón de eliminación.
     - Selector dinámico de tareas candidatas elegibles.
     - Lista de tareas sucesoras que dependen de la tarea activa.
- **Archivos creados/modificados:**
  - `app/Config/Database.php`
  - `app/Models/TaskDependency.php` (nuevo)
  - `app/Controllers/DependencyController.php` (nuevo)
  - `app/Models/Task.php`
  - `app/Models/Approval.php`
  - `public_html/api/index.php`
  - `public_html/index.html`
  - `public_html/assets/css/app.css`
  - `public_html/assets/js/app.js`
  - `tests/dependencies_test.php` (nuevo)
  - `docs/ROADMAP.md`
  - `docs/PROGRESS.md`
  - `docs/SESSION_HANDOFF.md`
  - `docs/CHANGELOG.md`
- **Decisiones y motivo:**
  - Algoritmo BFS en `TaskDependency::wouldCreateCycle` para verificar alcanzabilidad dirigida y prevenir deadlocks lógicos.
  - Interceptación en `Task::transition` y `Approval::resolve` para automatizar atómicamente el desbloqueo y re-bloqueo sin intervención manual.
- **Pruebas ejecutadas:**
  1. `php tests/healthcheck_test.php`: PASS (200 OK).
  2. `php tests/auth_test.php`: 19/19 PASS (100%).
  3. `php tests/users_test.php`: 23/23 PASS (100%).
  4. `php tests/tasks_test.php`: 24/24 PASS (100%).
  5. `php tests/kanban_test.php`: 18/18 PASS (100%).
  6. `php tests/subtasks_comments_test.php`: 22/22 PASS (100%).
  7. `php tests/approvals_test.php`: 23/23 PASS (100%).
  8. `tests/dependencies_test.php`: 23 de 23 pruebas automatizadas superadas al 100% (auto-dependencia rechazada 422, duplicados 409, ciclo directo e indirecto 409 RN-07, auto-bloqueo RN-08, bloqueo de transiciones 409, ramas paralelas y regla AND 2/2, desbloqueo en cascada RF-16, desbloqueo tras eliminación, desbloqueo post-aprobación formal, anti-IDOR 403 y despacho HTTP de Router). Total acumulado: 152 pruebas superadas sin fallos.
- **Documentación actualizada:** `docs/ROADMAP.md`, `docs/PROGRESS.md`, `docs/SESSION_HANDOFF.md`, `docs/CHANGELOG.md`.
- **Git:** Commit pendiente de cierre.
- **Deuda técnica / riesgos:** Ninguna.
- **Siguiente paso verificable:** MVP-08 (Automatizaciones y notificaciones idempotentes).


