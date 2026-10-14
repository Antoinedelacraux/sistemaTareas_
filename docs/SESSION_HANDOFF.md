# Traspaso entre sesiones (leer primero)

## Estado actual verificable
- Repositorio Git con arquitectura modular completa y operativa en desarrollo local (`PHP 8.4.25` + `SQLite`).
- **Fase 1.5+ / V1.5.3 COMPLETADA (Retiro de Bitácora, Modal de Tarea sin Scroll y Roles Jefatura vs Asistentes):**
  - **Retiro de Bitácora de Formulario:** Sección "Bitácora de Auditoría Inmutable" eliminada del modal `#modal-task-detail`; la trazabilidad histórica de eventos se consulta exclusivamente en el Módulo de Historial por roles de Jefatura.
  - **Modal de Detalle de Tarea Ampliado y Optimizado:**
    - Diálogo estilizado mediante `.modal-dialog.modal-dialog-task-detail` (1140px / 95vw / 94vh) con distribución en 2 columnas equilibradas (1.05fr / 0.95fr).
    - Adaptación visual integral que muestra la ficha de la tarea, checklist de subtareas, dependencias, comentarios, formulario de evidencias y selector de columnas sin requerir barras de desplazamiento vertical en el modal.
  - **Roles y Permisos Rigurosamente Validados:**
    - **Jefe de Planeamiento y Jefe de Personal:** Acceso irrestricto a todos los módulos (Área, Personal, Equipo, Aprobaciones, Historial, Usuarios), capacidad total para crear, editar, eliminar y reasignar tareas, vincular/desvincular dependencias DAG, y crear o personalizar columnas Kanban en ambas vistas.
    - **Asistentes Operativos:**
      - Acceso limitado a **Vista Área** y **Vista Personal**.
      - *En Vista Área:* Visualizan sus tareas asignadas, gestionan checklist de subtareas, publican comentarios y adjuntan evidencias. Visualizan dependencias asignadas en modo solo lectura. Restricción total contra edición, eliminación o asignación de tareas oficiales, y sin botones de gestión de columnas ni botón de nueva tarea oficial.
      - *En Vista Personal:* Permisos totales (crear, editar, eliminar, checklist, comentarios). Dependencias ocultas por ser innecesarias en tareas privadas individuales y bloqueadas a nivel backend con código 422.
  - **Generación Robusta de Códigos (`Task::generateCodigo`):** Cálculo dinámico sobre el número correlativo máximo con verificación de colisiones para evitar violaciones de clave única (`UNIQUE constraint failed`) tras eliminaciones de tareas.
  - **Test Runner Web Autónomo Validado:** 10 de 10 pruebas superadas con éxito (100% PASS en 271.03 ms).
- Base de datos local: SQLite activa en `storage/database.sqlite` con esquema e integridad comprobada.
- Servidor local activo en: `http://127.0.0.1:8000`.

## Decisiones cerradas
- Ver D-001 a D-018 en `docs/DECISIONS.md`.
- **D-019 (Centralización de Auditoría en Historial y Modal de Tarea sin Scroll):** Se retira la bitácora de auditoría inmutable del formulario de detalle para reservar su trazabilidad al Módulo de Historial de Jefatura, aprovechando el espacio para desplegar la ficha de tarea en 2 columnas optimizadas sin barras de desplazamiento vertical.
- **D-020 (Matriz de Permisos Diferenciada Jefatura vs Asistentes):** Jefatura dispone de control total en registros, dependencias, módulos y columnas. Los asistentes operan como ejecutores en Vista Área (checklist, evidencias, comentarios, dependencias solo lectura) y como administradores plenos en su Vista Personal (sin dependencias DAG).

## Bloqueos y pendientes
- Sin bloqueos técnicos. 10 de 10 pruebas automatizadas superadas con 100% PASS en 271.03 ms.

## Próximo paso recomendado
1. Ingresar en el navegador a `http://127.0.0.1:8000` (o presionar `Ctrl + F5` para recargar scripts con versión `v1.5.3`).
2. Probar como Elena Vega (Jefa de Planeamiento) o Roberto Sánchez (Jefe de Personal):
   - Abrir cualquier tarea y constatar el nuevo modal amplio sin Bitácora y con botones de Edición, Eliminación y Vincular dependencias.
   - Constatar el botón `+ Añadir otra columna` y la personalización de columnas.
3. Probar como Marco Chumbes (Asistente):
   - En **Vista Área**: constatar que no aparece `+ Añadir otra columna`, no aparece `Nueva Tarea`, y en el modal de detalle no aparecen botones de editar ni eliminar ni vincular dependencias.
   - En **Vista Personal**: constatar el botón `Nueva Tarea`, crear y editar tareas personales sin sección de dependencias.
4. Ejecutar el diagnóstico autónomo en `http://127.0.0.1:8000/api/test-runner`.

## Datos para la próxima sesión
- Diagnóstico autónomo web: `http://127.0.0.1:8000/api/test-runner` o `http://127.0.0.1:8000/api/test-runner?format=json`.
- Plataforma web: `http://127.0.0.1:8000` (Clave de acceso común: `Planeamiento2026!`).


