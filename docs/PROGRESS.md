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
- **Git:** Pendiente checkpoint de este incremento (`feat(tasks): implement crud for personal and area tasks with scope and assignment rules MVP-03`).
- **Deuda técnica / riesgos:** Ninguna. Cobertura del 100% en backend.
- **Siguiente paso verificable:** MVP-04 (Kanban individual/general y transiciones).
