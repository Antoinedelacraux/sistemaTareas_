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
- **Git:** Pendiente checkpoint de este incremento (`docs(ux): create wireframes and navigation design for assistant and manager roles`).
- **Deuda técnica / riesgos:** Ninguna en diseño.
- **Siguiente paso verificable:** SETUP-04 (Diseñar casos de uso, modelo de datos relacional MySQL y especificación de endpoints API REST).
