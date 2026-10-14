# Contexto permanente del proyecto
Versión de alcance de referencia: **V1.2**. Estado inicial: configuración de entorno, sin funcionalidades implementadas verificadas.

## Propósito
Aplicación web interna para el área de Planeamiento que centraliza la distribución, seguimiento y gestión de tareas en distintos roles. El objetivo prioritario es ser un sistema **fácil de usar, ágil y 100% funcional** para el trabajo diario del equipo antes de su despliegue en el VPS (DonWeb Ferozo), con arquitectura modular y escalable para admitir cambios futuros sin fricción.

## Usuarios iniciales
5 personas del área de Planeamiento de TAL S.A.: 3 asistentes de información, 1 jefe de personal y 1 jefe del área de Planeamiento.

## Enfoque y Decisiones Confirmadas (Refactorización Ágil V1.3)
1. **Roles y colaboración natural:**
   - **Jefatura (Planeamiento y Personal):** Asigna y reasigna tareas al equipo, supervisa el tablero completo, define prioridades y plazos.
   - **Asistentes:** Gestionan ágilmente sus tareas asignadas, tienen visibilidad del tablero general del equipo para colaborar y pueden crear tareas propias o de apoyo.
2. **Tablero Kanban fluido:**
   - Movimiento directo de tarjetas sin modales bloqueantes forzados ni candados matemáticos (DAG) que impidan la acción del usuario.
   - 4 a 5 estados claros: Pendiente, En proceso, En revisión (opcional), Finalizada.
3. **Ficha de Tarea completa y útil:**
   - Detalle rápido: responsable, prioridad, fecha límite y descripción.
   - Checklist interactivo de subtareas con barra visual de avance porcentual.
   - Comentarios y notas de avance con soporte de adjuntos/evidencias.
4. **Infraestructura y Plataforma (D-011 / ADR-0001):**
   - DonWeb Web Hosting tradicional / VPS (PHP 8.2+ REST API, MySQL/MariaDB InnoDB, SPA frontend Vanilla JS/CSS moderno).
   - Listo y verificado 100% en local antes del despliegue en producción.

## Alcance Prioritario del Sistema
- Autenticación rápida y manejo de sesiones.
- Tablero Kanban interactivo con cambio de vista ("Mis Tareas", "Tablero del Equipo", "Dashboard").
- CRUD ágil de tareas (creación, edición rápida, cambio de estado directo, eliminación/archivo).
- Checklist de subtareas con progreso en tiempo real.
- Notas, comentarios y adjuntos.
- Dashboard operativo con indicadores visuales de avance y cumplimiento.

## Fases posteriores
Plantillas, recurrencias, calendario laboral, tiempos reales, reportes avanzados y exportación Excel, editor de automatizaciones avanzado.

## Propuestas NO confirmadas todavía
- Tareas personales privadas incluso frente a jefes; se propone no incluirlas en KPI oficiales.
- Diseño de estados inicial: Pendiente, En proceso, Bloqueada, En revisión, Finalizada.
- Jefe de personal puede administrar ciertas configuraciones mediante autorización delegada.
- Retención de auditoría y política de adjuntos por acordar con el área y TI.

## Fuente de verdad
Ver `docs/REQUIREMENTS.md`, `docs/DECISIONS.md`, `docs/ROADMAP.md`, `docs/PROGRESS.md`. Si hay conflicto, no inventar: registrar pregunta y proponer resolución.
