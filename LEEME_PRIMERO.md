# Kit Antigravity — Sistema de seguimiento de tareas

## Qué incluye
- `AGENTS.md`: reglas maestras permanentes.
- `.agents/agents/`: 9 perfiles de agentes personalizados reales; el Orquestador es agente principal y los otros subagentes cuando la superficie lo permite.
- `.agents/rules/`: seguridad, calidad, documentación y commits.
- `.agents/skills/`: 10 workflows reutilizables como skills modernos.
- `docs/`: memoria permanente, requisitos V1.2, decisiones, diseño propuesto, roadmap y handoff.
- `prompts/`: prompts listos para instalación, reinicio, planificación, implementación y cierre.
- `templates/gitignore.snippet`: exclusiones sugeridas que deben fusionarse con `.gitignore`.

## Instalación segura en Antigravity
1. Copiar los contenidos de esta carpeta A LA RAÍZ del proyecto Antigravity, no dentro de una subcarpeta accidental. Si ya existen `AGENTS.md`, `.agents/` o `docs/`, fusionar cuidadosamente y no sobrescribir trabajo propio.
2. Verificar que se ven las carpetas ocultas `.agents` (en Windows, activar elementos ocultos si es necesario).
3. Abrir Antigravity sobre la raíz del workspace y revisar Customizations / Rules y Skills. Los subagentes personalizados tienen soporte en Antigravity app/CLI; si el IDE usado no los carga, los perfiles siguen siendo prompts reutilizables y se pueden abrir conversaciones especializadas.
4. Pegar `prompts/01-INSTALAR-CONFIGURACION.md` en el agente principal. Pedir primero inspección, sin construcción de aplicación.
5. Al comenzar cada nueva conversación ejecutar `/iniciar-sesion`; al trabajar usar `/planificar-fase`, `/implementar-funcionalidad`; al cerrar usar `/ cerrar-avance`.
6. Fusionar `templates/gitignore.snippet` en `.gitignore` sin eliminar excepciones existentes. Comprobar que no se versiona información sensible.
7. Git: el agente Orquestador realizará checkpoints LOCALMENTE por avances probados; si se requiere repositorio remoto, solicitar autorización antes de `git push`.

## Nota importante sobre Workflows
La documentación oficial de Antigravity señala retirada de workflows `.agents/workflows/*.md` a partir del 19 de octubre de 2026 y recomienda migración a `SKILL.md`. Por eso estos workflows están implementados como skills y se invocan con `/nombre`.

## Primer hito recomendado
Fase 0: validar decisiones pendientes -> aprobar arquitectura ADR -> diseñar pantallas/diagramas y datos -> preparar repositorio y CI -> implementar incrementalmente.

## No presupone funcionamiento en el repositorio original
Este ZIP es un paquete listo para copiar, pero no ha sido integrado ni ejecutado dentro de tu instalación de Antigravity. Los commits se harán cuando instales la configuración en tu repositorio.
