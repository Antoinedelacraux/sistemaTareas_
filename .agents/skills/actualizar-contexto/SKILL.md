---
name: actualizar-contexto
description: "Actualiza memoria persistente del proyecto, bitácora de avances y traspaso para próxima sesión."
---

# Workflow: actualizar contexto
1. Comprobar qué se hizo realmente con archivos, resultados y Git.
2. Actualizar `docs/PROGRESS.md` con objetivo, RF/RN, cambios, pruebas, errores y estado.
3. Actualizar `docs/ROADMAP.md` y `docs/CHANGELOG.md` según progreso verificado.
4. Registrar decisiones en `docs/DECISIONS.md`, sin transformar suposiciones en confirmaciones.
5. Actualizar `docs/ARCHITECTURE.md` solo si cambió una decisión técnica aceptada.
6. Reescribir `docs/SESSION_HANDOFF.md` con estado actual verificable, pendientes, bloqueos y siguiente primer paso.
7. Devolver un resumen breve con rutas y cambios documentados.
