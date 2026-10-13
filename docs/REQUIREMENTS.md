# Requisitos y reglas de negocio — V1.2

## Requisitos funcionales (MVP)
- RF-01 Autenticar usuarios y proteger sesiones.
- RF-02 Administrar cuentas, roles y permisos finos.
- RF-03 Crear tareas personales.
- RF-04 Crear tareas del área según permiso.
- RF-05 Impedir que asistentes asignen a otros usuarios.
- RF-06 Permitir asignación y reasignación por jefes autorizados.
- RF-07 Aplicar visibilidad por alcance y rol desde el servidor.
- RF-08 Gestionar tableros Kanban configurables y vista individual/general.
- RF-09 Gestionar estados, prioridades, fechas, responsables y colaboradores.
- RF-10 Gestionar subtareas, comentarios y evidencias con control de acceso.
- RF-11 Mover tareas manualmente o por automatización según reglas.
- RF-12 Solicitar confirmación explícita antes de cerrar tareas SIN aprobación.
- RF-13 Configurar aprobaciones por tarea, revisor y observaciones.
- RF-14 Modelar dependencias secuenciales entre tareas.
- RF-15 Permitir ramas de tareas en paralelo.
- RF-16 Habilitar dependientes cuando finalicen todas sus predecesoras.
- RF-17 Generar notificaciones internas de eventos relevantes.
- RF-18 Mantener historial y auditoría de cambios.
- RF-19 Mostrar dashboard personal y general según permisos.

## Funcionalidades posteriores
- RF-20 Plantillas reutilizables de procesos.
- RF-21 Recurrencia programada.
- RF-22 Registro de tiempo de trabajo.
- RF-23 Calendarios laborales.
- RF-24 Indicadores y reportes avanzados.
- RF-25 Exportación a Excel.
- RF-26 Constructor de reglas complejas de automatización.

## Reglas de negocio
- RN-01 Las tareas personales se separan de tareas oficiales del área; privacidad total ante jefes es PROPUESTA, no confirmada.
- RN-02 Tarea oficial visible a su responsable y a jefes habilitados; otra persona no obtiene acceso por conocer su ID.
- RN-03 Asistentes no pueden asignar, transferir ni reasignar tareas a compañeros.
- RN-04 Jefes autorizados asignan o reasignan tareas del área.
- RN-05 Permiso para crear tarea oficial no otorga privilegio de consultar tareas ajenas.
- RN-06 El tipo funcional de estado se conserva aunque cambie nombre/color de columna.
- RN-07 Dependencias no deben formar ciclos; una tarea puede esperar varias predecesoras.
- RN-08 Tareas paralelas progresan de forma independiente; una sucesora requiere que TODAS las predecesoras alcancen cierre efectivo (regla AND inicial).
- RN-09 Una tarea habilitada no significa tarea ejecutada o finalizada.
- RN-10 Tarea con aprobación: entrega -> En revisión -> aprobación o devolución; dependencias avanzan tras aprobación efectiva.
- RN-11 Tarea sin aprobación: confirmar explícitamente -> Finalizada; cancelar deja estado intacto.
- RN-12 Arrastrar tarjeta a Finalizada debe respetar la misma confirmación y permiso.
- RN-13 Cada transición importante registra actor, motivo/evento y fecha.
- RN-14 Reabrir una tarea requiere permiso y evaluación de dependientes, sin borrar entregas posteriores automáticamente.
- RN-15 Eventos automatizados idempotentes; cambios persistidos coherentemente.

## Aceptación mínima de MVP
1. Asistente crea tarea para sí; puede verla y actualizarla.
2. Intento de asignación de asistente a otro => denegado por API.
3. Intento de consultar ID de tarea ajena => denegado por API.
4. Jefe asigna una tarea y aparece en la vista del responsable.
5. Confirmación cancelada => no cierra tarea ni dispara dependencias.
6. Confirmación aceptada => tarea sin aprobación se cierra y activa dependientes elegibles.
7. Tarea con aprobación no cierra hasta que aprobador la autorice.
8. Tres ramas paralelas: completar 2/3 mantiene bloqueada la tarea sucesora; 3/3 la habilita.
9. Cambiar nombre de columna no rompe las reglas de transición.
10. Evitar disparos duplicados y pérdida de cambios concurrentes.
11. Se actualiza el histórico con quién, cuándo y qué cambió.
12. Funciona con conexión remota HTTPS en ambiente de despliegue seguro.

## Restricciones no funcionales
Autorización backend (evitar IDOR), seguridad de sesiones, secretos fuera de Git, backups restaurables, trazabilidad, usabilidad, adaptabilidad móvil, mantenibilidad modular y pruebas automatizadas de reglas críticas.
