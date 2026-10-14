# Requisitos y reglas de negocio — V1.3 (Refactorización Ágil)

## Requisitos funcionales (Core 100% Funcional)
- RF-01 Autenticar usuarios con sesiones seguras y cambio rápido de rol para testing.
- RF-02 Administrar roles de usuario: Jefatura (Planeamiento / Personal) y Asistentes.
- RF-03 Crear tareas con título, descripción, prioridad, fecha límite y responsable.
- RF-04 Gestión de roles y asignación: Jefatura asigna y reasigna a cualquiera; Asistentes pueden asignarse tareas y crear tareas de apoyo.
- RF-05 Tablero Kanban interactivo, rápido y fluido con columnas: Pendiente, En proceso, En revisión (opcional), Finalizada.
- RF-06 Movimiento directo e inmediato de tarjetas (Drag & Drop y cambio por selector) sin bloqueos artificiales ni modales forzados.
- RF-07 Ficha de tarea enriquecida (estilo Trello/Notion): edición de campos, estado, prioridad y fechas.
- RF-08 Checklist interactivo de subtareas con barra de progreso porcentual visible en tarjeta y detalle.
- RF-09 Muro de comentarios y notas de avance con soporte de archivos adjuntos/evidencias.
- RF-10 Filtros rápidos en un solo clic: "Mis Tareas", "Tablero del Equipo", filtro por responsable y por prioridad.
- RF-11 Dashboard operativo con métricas de tareas completadas, en progreso, tasa de cumplimiento y balance general.
- RF-12 Compatibilidad nativa con PHP 8.2+ y MySQL/MariaDB para despliegue directo en DonWeb Ferozo / VPS.

## Reglas de negocio prácticas
- RN-01 La experiencia de usuario debe ser ágil y libre de fricción: mover una tarjeta a cualquier columna debe reflejarse inmediatamente.
- RN-02 Roles colaborativos: El asistente tiene su vista por defecto en sus tareas asignadas, pero puede consultar el tablero general del equipo para saber en qué están sus compañeros.
- RN-03 Asignaciones: Jefes pueden asignar y reasignar tareas a cualquier miembro del equipo.
- RN-04 Toda finalización registra automáticamente fecha y hora de término.
- RN-05 Al reabrir una tarea hacia otra columna, la fecha de finalización se limpia automáticamente.
- RN-06 Subida de archivos segura: extensiones controladas (PDF, imágenes, hojas de cálculo, documentos), nombres hasheados para evitar colisiones y límite de 10 MB.
- RN-07 El código debe ser limpio, modular y fácilmente modificable para permitir futuros cambios sin romper el sistema.

## Aceptación mínima para subida a VPS
1. Login y cambio de usuario funcional entre Asistente y Jefa.
2. Creación ágil de tarea con título, prioridad, fecha y responsable.
3. Arrastre de tarjeta directo entre columnas en Kanban con actualización en base de datos.
4. Agregar, marcar y desmarcar subtareas con cálculo inmediato de % de progreso.
5. Dejar comentarios y adjuntar archivos con descarga funcional.
6. Filtro ágil entre "Mis Tareas" y "Tablero del Equipo".
7. Visualización del Dashboard con métricas reales.
8. Verificación 100% funcional local sin errores de consola ni de PHP.
