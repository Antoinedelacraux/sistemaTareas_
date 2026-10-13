# Prompt para Antigravity: instalar paquete sin destruir archivos

Estoy desarrollando el Sistema Web de Gestión y Seguimiento de Tareas del área de Planeamiento. En la raíz de este workspace he añadido un paquete con `AGENTS.md`, `.agents/agents`, `.agents/rules`, `.agents/skills` y `docs`.

Objetivo: validar y poner en funcionamiento estas customizaciones sin empezar todavía a programar el sistema.

Instrucciones:
1. Inspecciona el repositorio, `git status` y los archivos actuales antes de cualquier cambio. No sobrescribas archivos existentes sin fusionar su contenido.
2. Revisa si Antigravity reconoce reglas, agentes personalizados y skills. Si tu versión no soporta alguna función, explícame la limitación y cómo emplear los perfiles manualmente.
3. Comprueba que `AGENTS.md` y toda la documentación representen las decisiones V1.2 y distingan propuestas de decisiones confirmadas.
4. No crees backend, frontend ni dependencias todavía. No sustituyas la arquitectura por una decisión inventada.
5. Fusiona `templates/gitignore.snippet` dentro del `.gitignore` existente si corresponde, sin borrar reglas previas.
6. Ejecuta validaciones de estructura. Si Git está inicializado, realiza un commit local `chore(agents): initialize project agent configuration` con archivos explícitos y revisión previa, siempre que no haya modificaciones ajenas ni errores; no hagas push.
7. Devuélveme el árbol de customizaciones activas, resultados comprobados, commit real y siguientes pasos.
