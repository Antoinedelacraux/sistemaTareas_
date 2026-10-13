# Changelog
Convención sugerida: Keep a Changelog, sin versiones ficticias.

## [Sin versión - Autenticación y RBAC] - 2026-10-13
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
