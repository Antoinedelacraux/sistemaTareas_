---
name: git-checkpoint
description: "Prepara y realiza un commit local seguro para cada unidad coherente y validada, sin push automático."
---

# Workflow: checkpoint Git
**Único agente integrador responsable del commit**: si eres subagente, solo entrega sugerencias al Orquestador.
1. Comprobar repositorio y rama con `git status --short --branch`; no ejecutar `git init` automáticamente si hay dudas.
2. Revisar `git diff --check`, `git diff` y `git status --short` para separar trabajo propio de ajeno.
3. Exigir las pruebas requeridas por el cambio; si faltan o fallan, informar y NO confirmar código como validado.
4. Comprobar que el documento de avance ya fue actualizado (por `/actualizar-contexto`) y verificar coherencia documental.
5. Comprobar que no hay .env, secretos, tokens, dumps, data de trabajadores, archivos de producción o build artifacts.
6. `git add ruta/precisa1 ruta/precisa2 ...` (no usar `git add .` por defecto).
7. Inspeccionar `git diff --cached --stat` y `git diff --cached`; si se detecta archivo impropio, quitarlo del staging sin destruir cambios.
8. Crear **un** commit convencional por cambio coherente. Ejemplo: `feat(tasks): enforce self-assignment for assistants`.
9. Verificar hash real con `git log -1 --format='%h %s'` y comprobar `git status`.
10. Registrar hash en progreso/handoff cuando sea coherente; si la documentación del hash cambió después del commit, incorporar con un checkpoint documental separado o informar el hash en la respuesta (no usar amend automático).
11. Jamás push remoto, force, reset hard ni omitir hooks sin autorización.
12. Si no hay cambios, NO hacer commit; si Git/identidad/pruebas fallan, declarar bloqueo explícito.
