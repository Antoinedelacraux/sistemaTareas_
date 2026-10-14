# Changelog
Convención sugerida: Keep a Changelog, sin versiones ficticias.

## [Sin versión - Automatizaciones y Notificaciones Idempotentes] - 2026-10-13 (MVP-08)
### Añadido
- Módulo completo de eventos automatizados y notificaciones internas con garantía estricta de idempotencia (**Fase 1: MVP-08**).
- Modelo relacional `app/Models/Notification.php` y tabla `notificaciones` con tracking de alertas para usuarios, tipos de evento, metadatos JSON y clave única de idempotencia `idempotency_key` (**RN-15**).
- Motor de automatización `app/Services/AutomationEngine.php` con disparadores para 5 eventos críticos del sistema:
  - `TAREA_ASIGNADA`: Notificación al nuevo responsable al crear o reasignar una tarea.
  - `APROBACION_SOLICITADA`: Notificación a la jefatura/revisor cuando una tarea pasa a revisión formal.
  - `TAREA_APROBADA`: Notificación al responsable y creador al emitirse dictamen favorable.
  - `TAREA_DEVUELTA`: Notificación al responsable con observaciones de devolución obligatorias para subsanación.
  - `TAREA_DESBLOQUEADA`: Notificación al responsable cuando una tarea sucesora es liberada por conclusión de dependencias DAG (**RF-16**).
  - `NUEVO_COMENTARIO`: Notificación al responsable asignado ante nuevas notas o evidencias (sin auto-notificar al autor).
- Controlador `app/Controllers/NotificationController.php` con 4 endpoints:
  - `GET /api/notificaciones`: Listado de notificaciones con soporte para filtro de no leídas y paginación.
  - `GET /api/notificaciones/resumen`: Conteo de no leídas y notificaciones recientes para la campana de cabecera.
  - `PATCH /api/notificaciones/{id}/leer`: Marcado individual como leída con protección anti-IDOR (**403 Forbidden** para otros usuarios).
  - `POST /api/notificaciones/marcar-todas-leidas`: Marcado masivo atómico de todas las pendientes del usuario.
- Interfaz gráfica SPA (`index.html`, `app.css`, `app.js`):
  - Botón de campana interactivo con contador badge dinámico en tiempo real (`#notif-badge`).
  - Dropdown desplegable `#notif-dropdown` con scroll, iconos por tipo de evento, tiempos relativos ("Hace 5 min") y enlace a la tarea.
  - Botón de marcado masivo "Marcar todas leídas".
  - Polling pasivo suave en segundo plano y actualización reactiva tras acciones del usuario.
- Suite de pruebas automatizadas `tests/notifications_automation_test.php` con 33 casos de prueba unitarios, de integración y de idempotencia estricta (100% PASS).

## [Sin versión - Dependencias entre Tareas y Motor DAG] - 2026-10-13 (MVP-07)
### Añadido
- Módulo completo de dependencias secuenciales, ramas en paralelo y validación de Grafo Acíclico Dirigido (**Fase 1: MVP-07**).
- Modelo relacional `app/Models/TaskDependency.php` y tabla `tarea_dependencias` con tracking de relaciones de precedencia.
- Algoritmo de detección de ciclos en grafos dirigidos para salvaguardar la regla **RN-07** (evita deadlocks directos e indirectos).
- Controlador `app/Controllers/DependencyController.php` con 3 endpoints:
  - `GET /api/tareas/{id}/dependencias`: Listado de predecesoras y sucesoras con estado y métricas de bloqueo.
  - `POST /api/tareas/{id}/dependencias`: Vinculación de predecesora con comprobación anti-ciclos y auto-bloqueo preventivo (**RN-08**).
  - `DELETE /api/tareas/{id}/dependencias/{pred_id}`: Eliminación de precedencia con auto-desbloqueo a `PENDIENTE`.
- Motor de desbloqueo en cascada integrado en `Task::transition` y `Approval::resolve`: al finalizarse una tarea, sus sucesoras se habilitan atómicamente cuando TODAS sus predecesoras hayan concluido (**RN-08, RF-16**).
- Protección de transiciones en `Task::transition`: deniega movimientos hacia `EN_PROCESO` o `FINALIZADA` si la tarea tiene dependencias pendientes (`409 Conflict`).
- Interfaz gráfica SPA:
  - Sección de dependencias en el modal de detalle con alerta visual de tarea bloqueada.
  - Listas interactivas de predecesoras y sucesoras con badges de estado y desvinculación.
  - Selector dinámico de tareas predecesoras disponibles.
- Suite de pruebas automatizadas `tests/dependencies_test.php` con 23 casos positivos, negativos y de integración HTTP (100% PASS).

## [Sin versión - Circuito Formal de Aprobaciones y Cierre] - 2026-10-13 (MVP-06)
### Añadido
- Módulo completo de circuito formal de aprobaciones y confirmación de cierre (**Fase 1: MVP-06**).
- Modelo relacional `app/Models/Approval.php` y tabla `tarea_aprobaciones` con tracking de solicitudes, revisiones y dictámenes (`PENDIENTE`, `APROBADA`, `DEVUELTA`).
- Controlador `app/Controllers/ApprovalController.php` con 4 endpoints:
  - `POST /api/tareas/{id}/solicitar-aprobacion`: Pasa la tarea a `EN_REVISION` y asigna revisor de jefatura (**RN-10**).
  - `POST /api/tareas/{id}/resolver-aprobacion`: Emite dictamen `APROBAR` o `DEVOLVER`. `APROBAR` marca la tarea como `FINALIZADA` con timestamp; `DEVOLVER` exige obligatoriamente observaciones no vacías (**RN-12**, 422 si falta), retorna la tarea a `EN_PROCESO` y resetea fecha de finalización.
  - `GET /api/aprobaciones/pendientes`: Bandeja centralizada de tareas en espera de dictamen para roles de jefatura (**RF-13**, 403 Forbidden para asistentes).
  - `GET /api/tareas/{id}/aprobaciones`: Historial completo de dictámenes, fechas y observaciones de la tarea.
- Integración en frontend SPA (`index.html`, `app.css`, `app.js`):
  - Pestaña "⚖️ Bandeja de Aprobaciones" exclusiva para jefatura con contador de pendientes.
  - Vista `#approvals-view` con tarjetas de aprobación interactiva.
  - Bloque contextual de aprobación en modal de detalle de tarea y modal `#modal-return-task` para observaciones requeridas.
- Suite de pruebas automatizadas `tests/approvals_test.php` con 23 casos positivos, negativos y de integración HTTP (100% PASS).

## [Sin versión - Interfaz SPA y Servidor Local de Desarrollo] - 2026-10-13 (Commit `e49c10f`)
### Añadido
- Interfaz gráfica Single Page Application (SPA) completa e interactiva con soporte para modo claro/oscuro persistente y estética TAL S.A.
- Servidor de desarrollo integrado PHP `server.php` para ejecución local en puerto 8000 con soporte para API REST y activos estáticos MIME.
- Fallback automático y transparente a SQLite local (`storage/database.sqlite`) en entorno de desarrollo si MySQL no está levantado, con inicialización y semillas automáticas.
- Tablero Kanban dinámico de 5 columnas con filtros por alcance, texto de búsqueda, prioridad y menú de transiciones rápidas.
- Selector de cambio rápido de usuarios/roles para pruebas inmediatas de políticas RBAC y alcance (Asistente 1, Asistente 2, Jefe Planeamiento, Jefe Personal).
- Modal de detalle de tarea integrado con checklist de subtareas dinámico (`PATCH /api/subtareas/{id}`), timeline de comentarios y carga/descarga de evidencias adjuntas.
- Modal de confirmación de cierre obligatorio con ratificación consciente por checkbox (**RN-11**).

## [Sin versión - Subtareas, Comentarios y Adjuntos] - 2026-10-13 (Commit `8d17cc5`)
### Añadido
- Módulo de subtareas, comentarios y subida/descarga segura de evidencias (**Fase 1: MVP-05**).
- Servicio `app/Core/UploadHandler.php` para almacenamiento sanitizado de adjuntos en `storage/uploads/` con nombres hasheados únicos, validación estricta de extensiones (PDF, imágenes, hojas de cálculo, documentos, texto), bloqueo de scripts maliciosos (.php) y cuota máxima de 10 MB.
- Modelos `app/Models/Subtask.php` y `app/Models/Comment.php`.
- Controladores y endpoints:
  - `GET /api/tareas/{id}/subtareas`: Listado de subtareas ordenadas.
  - `POST /api/tareas/{id}/subtareas`: Creación de subtareas con validación de permisos de edición sobre la tarea padre.
  - `PATCH /api/subtareas/{id}`: Actualización de estado completado o título.
  - `DELETE /api/subtareas/{id}`: Eliminación de subtareas.
  - `GET /api/tareas/{id}/comentarios`: Listado de comentarios y evidencias de la tarea.
  - `POST /api/tareas/{id}/comentarios`: Registro de comentarios de texto o evidencias con archivo adjunto procesado de forma segura.
  - `GET /api/comentarios/{id}/archivo`: Descarga de archivo adjunto protegida por alcance en servidor (anti-IDOR) y cabeceras de seguridad `X-Content-Type-Options: nosniff`.
- Soporte para formularios `multipart/form-data` y `$_FILES` en `app/Core/Router.php`.
- Suite de pruebas automatizadas `tests/subtasks_comments_test.php` con 22 casos positivos, negativos y de integración HTTP (100% PASS).

## [Sin versión - Tablero Kanban y Transiciones] - 2026-10-13 (Commit `746990f`)
### Añadido
- Módulo de tablero Kanban y motor de transiciones de estado (**Fase 1: MVP-04**).
- Modelo `app/Models/BoardColumn.php` para gestión de columnas y mapeo de estados funcionales (`PENDIENTE`, `EN_PROCESO`, `BLOQUEADA`, `EN_REVISION`, `FINALIZADA`).
- Controlador `app/Controllers/BoardController.php` con endpoints:
  - `GET /api/tableros/columnas`: Listado de columnas activas ordenadas por secuencia visual.
  - `GET /api/tableros/kanban`: Vista Kanban completa con tareas agrupadas por columna bajo alcance estricto de usuario (asistente vs jefatura) y métricas de resumen en tiempo real.
  - `POST /api/tareas/{id}/transicion`: Transición de tareas entre columnas aplicando validaciones atómicas: exige confirmación explícita (`confirmacion_cierre: true`) para cerrar tareas sin aprobación (422 si falta), bloquea cierre directo para tareas con aprobación (409 Conflict), gestiona fecha de finalización y registra eventos en auditoría.
- Método `Task::transition` en `app/Models/Task.php`.
- Suite de pruebas automatizadas `tests/kanban_test.php` con 18 casos positivos, negativos y de integración HTTP (100% PASS).

## [Sin versión - CRUD de Tareas y Alcance] - 2026-10-13 (Commit `09ab9a0`)
### Añadido
- Módulo integral de gestión de tareas personales y del área (**Fase 1: MVP-03**).
- Modelo `app/Models/Task.php` con generación de códigos correlativos `TAR-YYYY-NNNN`, alcance en consultas SQL por rol, reglas de visibilidad y métodos de persistencia con auditoría.
- Controlador `app/Controllers/TaskController.php` con endpoints:
  - `GET /api/tareas`: Listado con filtro por alcance; asistentes solo ven tareas asignadas o creadas por ellos; jefes ven tareas del área de todo el equipo y tareas personales propias. Soporte para filtros `?tipo=`, `?columna_id=`, `?prioridad=`, `?responsable_id=`, `?buscar=`.
  - `GET /api/tareas/{id}`: Detalle de tarea con protección anti-IDOR para tareas ajenas y salvaguarda de privacidad de tareas personales frente a jefes (**RF-07, RN-01, RN-02**).
  - `POST /api/tareas`: Creación de tareas; fuerza autoasignación en asistentes y deniega con 403 Forbidden intentos de asignar a compañeros (**RF-05, RN-03**); jefes pueden asignar a cualquier miembro activo (**RF-06, RN-04**).
  - `PUT /api/tareas/{id}`: Modificación de atributos de tareas; deniega reasignación de responsable ejecutada por asistentes (**RN-03**).
  - `DELETE /api/tareas/{id}`: Eliminación de tareas con autorización exclusiva para el creador o jefatura.
- Suite de pruebas automatizadas `tests/tasks_test.php` con 24 casos positivos, negativos y de integración HTTP (100% PASS).

## [Sin versión - Usuarios, Ámbitos y Asignación] - 2026-10-13 (Commit `c7308e7`)
### Añadido
- Módulo de consulta de usuarios y validación de asignaciones por rol (**Fase 1: MVP-02**).
- Endpoints en `app/Controllers/UserController.php`:
  - `GET /api/usuarios`: Retorna únicamente al propio usuario autenticado si es asistente (**RF-05, RN-03, RN-05**); retorna a todos los usuarios activos del área si es jefe (**RF-06, RN-04**) con soporte para filtros `?rol=` y `?buscar=`.
  - `GET /api/usuarios/{id}`: Retorna el perfil seguro del usuario si es el propio solicitante o si el solicitante es jefe. Bloquea con `403 Forbidden` (`ERR_FORBIDDEN_USER_ACCESS`) cualquier intento de un asistente de inspeccionar perfiles ajenos (protección anti-IDOR/BOLA).
  - `POST /api/usuarios/validar-asignacion`: Valida formalmente si el solicitante puede asignar tareas a un responsable específico, impidiendo que asistentes asignen tareas a compañeros o jefes.
- Métodos en modelo `app/Models/User.php`: `listAll`, `listForUser`, `canViewUser`, `canAssignTo`, `validateAssignment`.
- Mecanismo en `Response.php` y `Router.php` (`$hasSentResponse`) para cancelar limpiamente la cadena de ejecución cuando un middleware o handler emite una respuesta de error.
- Suite de pruebas automatizadas `tests/users_test.php` con 23 casos positivos y negativos (100% PASS).

## [Sin versión - Autenticación y RBAC] - 2026-10-13 (Commit `7431530`)
### Añadido
- Módulo de autenticación y sesiones de la **Fase 1 (MVP-01)**.
- Modelos `User`, `Session` y `Audit` en `app/Models/`.
- Middleware de autenticación y RBAC `app/Core/Middleware/AuthMiddleware.php`.
- Controlador de autenticación `app/Controllers/AuthController.php` con endpoints `POST /api/auth/login`, `POST /api/auth/logout` y `GET /api/auth/me`.
- Soporte para cookies `HttpOnly; SameSite=Strict` y encabezados `Authorization: Bearer <token>`.
- Suite automatizada `tests/auth_test.php` con 19 pruebas positivas, negativas de acceso y de auditoría inmutable (100% PASS).

## [Sin versión - Estructura y Bootstrap Ferozo] - 2026-10-13 (Commit `9f648c6`)
### Añadido
- Culminación de la **Fase 0 (Preparación)** con la tarea `SETUP-05`.
- Estructura física del monolito modular para DonWeb Ferozo: `public_html/` y `app/` protegida con directiva Apache `Require all denied`.
- Script DDL completo de base de datos en `database/schema.sql` (10 tablas InnoDB con FKs e índices).
- Datos semilla iniciales en `database/seeds.sql` (5 columnas Kanban y 5 usuarios iniciales con hashes Bcrypt).
- Plantilla de variables de entorno `.env.example` y cargador liviano `app/Config/Env.php`.
- Conector seguro PDO MySQL con transacciones ACID en `app/Config/Database.php`.
- Enrutador REST `app/Core/Router.php`, respuestas JSON `app/Core/Response.php` y Front Controller `public_html/api/index.php`.
- Reglas de reescritura `.htaccess` en `public_html/` para API y SPA client-side routing.
- Shell inicial SPA con Design Tokens en `public_html/index.html` y script `public_html/assets/js/app.js`.
- Protección de almacenamiento de adjuntos en `storage/uploads/.htaccess`.
- Script de prueba de salud `tests/healthcheck_test.php` verificado exitosamente.

## [Sin versión - Modelo de Datos y API] - 2026-10-13 (Commit `8f548a1`)
### Añadido
- Especificación formal de casos de uso (CU-01 a CU-10), diagrama ER y script DDL SQL completo para MySQL 8 / MariaDB (InnoDB) en `docs/DATA_MODEL_AND_API.md` (SETUP-04).
- Definición de 10 tablas relacionales (`usuarios`, `sesiones`, `tableros_columnas`, `tareas`, `subtareas`, `tarea_dependencias`, `tarea_aprobaciones`, `tarea_comentarios`, `tarea_colaboradores`, `auditoria_eventos`) con claves foráneas e índices optimizados.
- Algoritmos para validación de aciclicidad (DFS en DAG) y desbloqueo atómico secuencial y paralelo (Regla AND) con transacciones ACID.
- Catálogo exhaustivo de endpoints de la API REST PHP 8.2+ con validación de autorización por alcance en servidor (asistente solo autoasigna, jefes gestionan equipo).

## [Sin versión - Diseño UI/UX] - 2026-10-13 (Commit `6df22cd`)
### Añadido
- Especificación formal de UI/UX, arquitectura de información y wireframes estructurales en `docs/UX_WIREFRAMES.md` (SETUP-03).
- Definición de Design Tokens para modo claro y oscuro (paleta cromática, elevaciones, bordes y tipografía).
- Wireframes de alta fidelidad para Vista Asistente (tareas personales vs. área), Vista Jefe (tablero general y bandeja de aprobaciones) y modales clave (confirmación explícita de cierre, devolución con observaciones, creación adaptativa).
- Mecanismo accesible de navegación por teclado y menú rápido como alternativa al Drag & Drop.

## [Sin versión - Arquitectura Ferozo] - 2026-10-13 (Commit `d0df541`)
### Modificado
- Aprobada decisión **D-011** y **ADR-0001**: Consolidación del stack tecnológico en PHP 8.2+ REST API, MySQL/MariaDB y SPA frontend sobre DonWeb Web Hosting tradicional (Ferozo).
- Sustituida propuesta P-004 (FastAPI + PostgreSQL descartados por incompatibilidad operativa con hosting Ferozo compartido sin demonios).
- Actualizados `docs/ARCHITECTURE.md`, `docs/DECISIONS.md`, `docs/PROJECT_CONTEXT.md` y `docs/ROADMAP.md` (SETUP-01 y SETUP-02 completados).

## [Sin versión - Configuración Inicial] - 2026-10-13 (Commit `652765c`)
### Añadido
- Contrato permanente del proyecto en `AGENTS.md`.
- Reglas operativas en `.agents/rules/` (`documentation.md`, `git.md`, `quality.md`, `security.md`).
- Definición de roles especializados en `.agents/agents/` (9 perfiles).
- Workflows operativos como skills en `.agents/skills/` (10 skills).
- Memoria técnica y funcional en `docs/` (`PROJECT_CONTEXT.md`, `REQUIREMENTS.md`, `DECISIONS.md`, `ROADMAP.md`, `ARCHITECTURE.md`, `PROGRESS.md`, `SESSION_HANDOFF.md`, `CHANGELOG.md`).
- Archivo `.gitignore` para salvaguarda de secretos y exclusión de artefactos locales.
- Prompts de operación en `prompts/`.
- Ninguna funcionalidad de frontend, backend o base de datos implementada aún.

## Regla
Anotar solo cambios reales, verificables, visibles o relevantes al repositorio; enlazar RF y commit cuando exista.
