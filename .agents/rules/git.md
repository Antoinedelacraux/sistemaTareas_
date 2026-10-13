---
trigger: always_on
description: "Protocolo de commits seguros en cada incremento terminado."
---
# Git obligatorio al cerrar avances
- Revisar `git status --short`, `git diff --check` y `git diff` antes de preparar un commit.
- Confirmar tests, lint y build disponibles; si no hay proyecto, documentar que no se ejecutaron.
- Analizar secretos, datos personales, dumps, binarios y artefactos. Usar rutas explícitas en `git add`.
- Revisar `git diff --cached --stat`, `git diff --cached` y `git status` antes de confirmar.
- Mensajes Conventional Commits: `docs:`, `feat:`, `fix:`, `refactor:`, `test:`, `chore:`, `security:`. Ejemplo `feat(tasks): add personal task visibility`.
- Hacer un commit por incremento coherente y verificable, no cada edición mínima. Si no hay cambios, no crear commit.
- Agente integrador/orquestador es el único que comitea en la rama compartida. Prohibido push automático, force y hooks omitidos.
