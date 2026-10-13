# Contexto permanente del proyecto
Versión de alcance de referencia: **V1.2**. Estado inicial: configuración de entorno, sin funcionalidades implementadas verificadas.

## Propósito
Aplicación web interna para el área de Planeamiento que centraliza distribución, seguimiento, aprobación y automatización de tareas; tablero Kanban configurable, trabajo individual y supervisión general.

## Usuarios iniciales
5 personas: 3 asistentes de información, 1 jefe de personal y 1 jefe del área de Planeamiento. Debe ser escalable sin necesidad de microservicios.

## Decisiones confirmadas por el responsable del proyecto
1. Jefes y asistentes pueden crear tareas según permisos.
2. Un asistente **solo puede asignarse tareas a sí mismo**; los jefes asignan a otros.
3. Los asistentes solo consultan sus propias tareas; los jefes ven tareas oficiales del equipo según su ámbito.
4. Una tarea solo exige aprobación cuando está configurada para ello.
5. Una tarea sin aprobación exige **confirmación explícita adicional** antes de cerrarse, incluso al arrastrar tarjeta.
6. Debe haber movimientos automáticos entre columnas y desbloqueo/asignación del siguiente paso.
7. Los procesos admiten actividades **secuenciales y paralelas**, con dependencias AND (todas las predecesoras terminan antes de continuar) como regla inicial propuesta.
8. Hay tareas **personales** y **del área**.
9. El acceso será web y seguro desde cualquier ubicación.
10. Plataforma y stack confirmados (D-011 / ADR-0001): DonWeb Web Hosting Ferozo, PHP 8.2+ REST API, MySQL/MariaDB y SPA frontend moderna.

## Alcance del MVP
Login, RBAC + permisos particulares, tareas personales y del área, tablero propio/general, CRUD tareas y subtareas, colaboradores según reglas, asignaciones de jefes, comentarios/evidencias, prioridades/fechas, estados, revisión/aprobación, confirmación de cierre, dependencias múltiples y paralelas, notificaciones, auditoría y dashboard básico.

## Fases posteriores
Plantillas, recurrencias, calendario laboral, tiempos reales, reportes avanzados y exportación Excel, editor de automatizaciones avanzado.

## Propuestas NO confirmadas todavía
- Tareas personales privadas incluso frente a jefes; se propone no incluirlas en KPI oficiales.
- Diseño de estados inicial: Pendiente, En proceso, Bloqueada, En revisión, Finalizada.
- Jefe de personal puede administrar ciertas configuraciones mediante autorización delegada.
- Retención de auditoría y política de adjuntos por acordar con el área y TI.

## Fuente de verdad
Ver `docs/REQUIREMENTS.md`, `docs/DECISIONS.md`, `docs/ROADMAP.md`, `docs/PROGRESS.md`. Si hay conflicto, no inventar: registrar pregunta y proponer resolución.
