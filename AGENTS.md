# Contrato permanente del proyecto — Seguimiento de tareas (Planeamiento)

Eres parte de un equipo de ingeniería asistido por IA. La fuente de verdad son los archivos versionados del repositorio, nunca el historial pasajero del chat.

## Lectura obligatoria antes de actuar
1. Leer `docs/PROJECT_CONTEXT.md`, `docs/REQUIREMENTS.md`, `docs/DECISIONS.md`, `docs/ROADMAP.md`, `docs/PROGRESS.md` y `docs/SESSION_HANDOFF.md`.
2. Inspeccionar los archivos, el estado real de Git y la configuración antes de cambiar código. No suponer que ya existe una tecnología o función.
3. Clasificar requisitos en CONFIRMADO, PROPUESTO y PENDIENTE. No elevar una propuesta a decisión confirmada sin autorización.
4. Relacionar cada cambio con uno o varios IDs RF/RN o tareas del roadmap. Realizar cambios acotados.

## Forma de trabajo
- Un agente Orquestador coordina. Delegar a analista, arquitecto, UX, desarrollo, QA, seguridad y documentación según el caso.
- Si se usan subagentes paralelos, asignar archivos o ramas/worktrees separados; integrar secuencialmente. No editar ni confirmar commits concurrentes sobre la misma rama.
- Prioridad: autorización efectiva, integridad, facilidad de uso y mantenibilidad. Arquitectura simple y modular; evitar microservicios por defecto.
- Antes de implementar: definir criterio de aceptación y plan. Para tareas pequeñas, plan conciso; para arquitectura o alcance nuevo, pedir validación.
- Verificar cambios usando los comandos realmente disponibles en el repositorio. Nunca inventar pruebas aprobadas.
- Proteger secretos, datos del personal, registros y archivos. No incluir credenciales o registros productivos en prompts, logs ni Git.
- No instalar, desplegar, borrar archivos o migrar datos sin necesidad y autorización para operaciones destructivas o externas.
- No modificar producción ni enviar cambios remotos (`git push`) sin autorización explícita.

## Memoria persistente y documentación
Después de cada avance funcional documentar: decisión/razón, archivos afectados, RF/RN cubiertos, pruebas ejecutadas y resultado, limitaciones y siguiente paso.
Actualizar `docs/PROGRESS.md`, `docs/SESSION_HANDOFF.md`, `docs/CHANGELOG.md` y, si procede, `docs/ARCHITECTURE.md`, `docs/DECISIONS.md`, `docs/REQUIREMENTS.md`. Mantener documentos coherentes con código real.

## Git en cada avance coherente
Al cerrar una unidad comprobable: ejecutar `git status`, revisar `git diff`, correr verificaciones pertinentes, actualizar documentos, revisar archivos y secretos a incluir, hacer `git add` con rutas explícitas, comprobar `git diff --cached` y `git commit` convencional. Registrar hash final.
No crear commits vacíos, no hacer commit de código roto ni de trabajo ajeno, no usar `git add .` a ciegas, no usar `--force`, `reset --hard`, `push --force` ni `--no-verify`.
Si faltan Git, identidad, permisos o fallan pruebas: reportar bloqueo y preservar trabajo; jamás declarar un commit inexistente.
Si el repositorio aún no existe, consultar antes de `git init` si hay dudas sobre directorio o remoto.

## Comunicación de resultados
Respuesta final de cada avance: objetivo, cambios, pruebas (evidencia/estado), documentación actualizada, commit (hash o bloqueo), siguiente paso.
No iniciar la construcción de funcionalidades hasta que el usuario solicite la fase correspondiente; primero validar alcance, diseño y arquitectura.
