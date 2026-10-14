# Traspaso entre sesiones (leer primero)

## Estado actual verificable
- Repositorio Git con arquitectura modular completa y operativa en desarrollo local (`PHP 8.4.25` + `SQLite`).
- **Fase 1.5+ / V1.5.2 COMPLETADA (Corrección de Conmutación de Pestañas y Vistas - FIX-TABS-01):**
  - **Corrección en `app.js`:** Corregido error de importación `isCurrentUserJefe` que bloqueaba la salida de la Vista Personal hacia cualquier otra vista. Exportado `setScope` globalmente.
  - **Separación Estricta de Vistas en Kanban:** Sustitución de la pestaña general "Mis Tareas" por dos vistas independientes:
    1. **"Vista Área" (`scope=area`):** Contiene exclusivamente las tareas oficiales del área (`tipo_tarea = 'AREA'`). Se les aplica la regla de expiración de 24 horas hacia el módulo de Historial.
    2. **"Vista Personal" (`scope=personal`):** Tablero individual privado para que cada integrante (asistente o jefe) se organice con tareas personales (`tipo_tarea = 'PERSONAL'`). No se envían al historial al finalizar; se mantienen visibles hasta que el usuario decida eliminarlas manualmente para limpiar su espacio.
  - **Banner Explicativo de Vista Personal:** Elemento informativo visible en `#kanban-view` cuando se está en la vista personal, recordando que las tareas finalizadas no se trasladan al historial.
  - **Creación Adaptativa en Modal (`#modal-create-task`):** Preselección automática del tipo de tarea según la vista activa (Área o Personal), ajuste automático de autoasignación para tareas personales y supresión de flujos de aprobación innecesarios. Conmutación reactiva de pestaña si se crea una tarea del tipo contrario.
  - **Aislamiento en Backend (`Task.php`):** Consultas SQL blindadas para aislar ambos tipos de tareas; `listArchived` garantiza de forma estricta que únicamente las tareas de área finalizadas ingresan al historial de jefatura.
  - **Test Runner Autónomo Validado:** Prueba 10 ampliada para verificar separación de vistas, no expiración en personal, no inclusión de personal en historial y aislamiento RBAC (10/10 PASS en 299 ms).
- Base de datos local: SQLite activa en `storage/database.sqlite` con esquema e integridad comprobada.
- Servidor local activo en: `http://127.0.0.1:8000`.

## Decisiones cerradas
- Ver D-001 a D-017 en `docs/DECISIONS.md`.
- **D-018 (Separación Estricta de Vista Área vs Vista Personal):** Se separan en dos pestañas dedicadas las tareas oficiales del área frente a las tareas personales de apoyo cotidiano. Solo las tareas de área entran al circuito formal y al historial (>24h). Las personales permanecen en el tablero privado hasta su eliminación manual por el usuario.

## Bloqueos y pendientes
- Sin bloqueos técnicos. 10 de 10 pruebas automatizadas superadas con 100% PASS en 299 ms.

## Próximo paso recomendado
1. Ingresar en el navegador a `http://127.0.0.1:8000` (o presionar `Ctrl + F5` para recargar scripts).
2. Probar la alternancia libre entre **"Vista Área"**, **"Vista Personal"**, **"Tablero del Equipo"** (jefes), **"Aprobaciones"** (jefes) e **"Historial"** (jefes).
3. Crear una tarea en "Vista Personal" y verificar que se preselecciona como personal, se autoasigna y se ubica en el tablero personal sin mezclarse con las tareas de área.
4. Crear una tarea en "Vista Área" y comprobar que solo se lista en el área.
5. Ejecutar el diagnóstico autónomo en `http://127.0.0.1:8000/api/test-runner`.

## Datos para la próxima sesión
- Diagnóstico autónomo web: `http://127.0.0.1:8000/api/test-runner` o `http://127.0.0.1:8000/api/test-runner?format=json`.
- Plataforma web: `http://127.0.0.1:8000` (Clave de acceso común: `Planeamiento2026!`).


