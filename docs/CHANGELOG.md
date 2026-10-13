# Changelog
Convención sugerida: Keep a Changelog, sin versiones ficticias.

## [Sin versión - Diseño UI/UX] - 2026-10-13
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
