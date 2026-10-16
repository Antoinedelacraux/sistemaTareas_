# Especificación de Diseño UI/UX y Wireframes — V1.2 (SETUP-03)

Este documento detalla la arquitectura de información, flujos de usuario, diseño de componentes, wireframes estructurales y el sistema de diseño visual para el «Sistema Web de Gestión y Seguimiento de Tareas del Área de Planeamiento», en cumplimiento de los requisitos RF-01 a RF-19 y las reglas de negocio RN-01 a RN-15.

---

## 1. Principios de Experiencia de Usuario (UX)

1. **Autorización y contexto transparente:** La interfaz refleja de manera inmediata el alcance del rol activo. No se muestran opciones prohibidas; las restricciones de negocio se comunican con claridad pedagógica y sin ambigüedades.
2. **Eficiencia y densidad de información:** Diseñado para trabajo operativo diario en el área de Planeamiento. Información clara sobre dependencias, plazos y estados sin saturación visual.
3. **Cero operaciones accidentales:** Las acciones críticas (cerrar tareas sin aprobación, devolver tareas con observaciones, cambios de responsable) exigen confirmación explícita con explicación del impacto en la cadena de dependencias.
4. **Accesibilidad total (a11y):** Alternativa completa por teclado para todas las operaciones de arrastre (drag-and-drop), contrastes WCAG AA+, navegación por atajos y atributos semánticos ARIA.
5. **Estética profesional y moderna:** Interfaz sobria, dinámica y cuidada, con soporte nativo para modo claro y oscuro basado en Design Tokens.

---

## 2. Mapa de Navegación y Vistas por Rol

```mermaid
graph TD
    Login["Pantalla de Login"] --> SessionCheck{"Validación de Sesión y Rol"}
    
    SessionCheck -->|Rol: Asistente| NavAsistente["Panel de Asistente"]
    NavAsistente --> TabMisPersonales["Pestaña: Mis Tareas Personales (Privadas)"]
    NavAsistente --> TabMisOficiales["Pestaña: Mis Tareas del Área (Asignadas a mí)"]
    NavAsistente --> ModalCrearAsistente["Modal: Crear Tarea (Autoasignación obligatoria)"]
    NavAsistente --> ModalConfirmCierre["Modal: Confirmación de Cierre (Sin aprobación)"]
    NavAsistente --> ModalDetalleTarea["Modal/Panel: Detalle de Tarea y Subtareas"]

    SessionCheck -->|Rol: Jefe (Planeamiento / Personal)| NavJefe["Panel de Supervisión (Jefe)"]
    NavJefe --> TabGeneralArea["Pestaña: Tablero General del Equipo"]
    NavJefe --> TabAprobaciones["Pestaña: Bandeja de Aprobaciones (Badge contador)"]
    NavJefe --> TabMisTareasJefe["Pestaña: Mis Tareas Personales/Propias"]
    NavJefe --> FiltrosEquipo["Filtros: Por Asistente, Prioridad, Dependencias"]
    NavJefe --> ModalCrearJefe["Modal: Crear/Asignar Tarea a Miembro"]
    NavJefe --> ModalRevisarTarea["Modal: Revisión y Aprobación / Devolución"]
```

---

## 3. Tokens del Sistema de Diseño (Design Tokens)

El sistema visual utiliza variables CSS personalizadas para garantizar máxima coherencia, soporte para modo claro/oscuro y cero dependencias pesadas:

```css
:root {
  /* Paleta Base */
  --bg-primary: #f8fafc;
  --bg-surface: #ffffff;
  --bg-surface-hover: #f1f5f9;
  --border-subtle: #e2e8f0;
  --border-strong: #cbd5e1;
  --text-main: #0f172a;
  --text-muted: #64748b;
  --text-inverse: #ffffff;

  /* Colores de Acento y Marca */
  --primary-500: #4f46e5;       /* Índigo primario */
  --primary-600: #4338ca;
  --primary-subtle: #eef2ff;

  /* Estados Funcionales de Tarea */
  --status-pending: #64748b;     /* Pendiente (Gris pizarra) */
  --status-in-progress: #2563eb; /* En proceso (Azul activo) */
  --status-blocked: #e11d48;     /* Bloqueada (Rojo rubí) */
  --status-review: #d97706;      /* En revisión (Ámbar) */
  --status-done: #059669;        /* Finalizada (Esmeralda) */

  /* Prioridades */
  --priority-baja: #0284c7;
  --priority-media: #f59e0b;
  --priority-alta: #ea580c;
  --priority-urgente: #dc2626;

  /* Elevaciones y Sombras */
  --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05);
  --shadow-card: 0 1px 3px 0 rgb(0 0 0 / 0.1), 0 1px 2px -1px rgb(0 0 0 / 0.1);
  --shadow-card-hover: 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1);
  --shadow-modal: 0 20px 25px -5px rgb(0 0 0 / 0.15), 0 8px 10px -6px rgb(0 0 0 / 0.1);

  /* Tipografía */
  --font-family: system-ui, -apple-system, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
  --radius-sm: 6px;
  --radius-md: 10px;
  --radius-lg: 16px;
}

[data-theme="dark"] {
  --bg-primary: #0b0f19;
  --bg-surface: #111827;
  --bg-surface-hover: #1f2937;
  --border-subtle: #1f2937;
  --border-strong: #374151;
  --text-main: #f9fafb;
  --text-muted: #9ca3af;
  --primary-subtle: #1e1b4b;
}
```

---

## 4. Wireframes Estructurales

### 4.1 Vista Asistente (Mi Espacio de Trabajo)

Diseñada para máxima concentración. El asistente solo visualiza sus tareas asignadas y puede alternar rápidamente entre tareas del área y sus notas personales privadas.

```text
+--------------------------------------------------------------------------------------------------------------------+
|  [LOGO] Planeamiento TAL  |  [Pestaña] Mis Tareas Oficiales  |  [Pestaña] Mis Tareas Personales  |   [Modo Oscuro] [User: Marco C.] |
+--------------------------------------------------------------------------------------------------------------------+
|  HERRAMIENTAS:  [ + Nueva Tarea ]   [ Buscar en mis tareas... ]   [ Filtro: Prioridad v ]   [ Ordenar: Vencimiento v ]       |
+--------------------------------------------------------------------------------------------------------------------+
|                                                  TABLERO KANBAN PERSONAL                                           |
|                                                                                                                    |
|  [ PENDIENTE (3) ]       [ EN PROCESO (2) ]     [ BLOQUEADA (1) ]        [ EN REVISIÓN (1) ]    [ FINALIZADA (8) ] |
|  +--------------------+  +--------------------+ +----------------------+ +--------------------+ +------------------+ |
|  | #104 | Tarea Área  |  | #101 | Tarea Área  | | #106 | Tarea Área    | | #98 | Tarea Área   | | #92 | Tarea Área | |
|  | Plan anual q3      |  | Carga de datos SIP | | Reporte consolidado  | | Matriz de costos   | | Cierre mensual   | |
|  | [Media] 18-Oct     |  | [Alta]  15-Oct     | | [Urgente] 20-Oct     | | [Media] Esperando  | | [Baja] Finalizada| |
|  | Subtareas: 2/4     |  | Subtareas: 1/2     | | [!] Espera a #101    | | Revisor: Jefe Plan.| | Con confirmación | |
|  | [ Mover v ]  [···] |  | [ Mover v ]  [···] | | [ Mover v ]  [···]   | | [ Mover v ]  [···] | | [ Reabrir ] [···]| |
|  +--------------------+  +--------------------+ +----------------------+ +--------------------+ +------------------+ |
|  | #108 | Tarea Área  |  | #105 | Personal    |                                                                    |
|  | Verificar invent.  |  | Revisar notas curso|                                                                    |
|  | [Baja]  22-Oct     |  | [Baja] 16-Oct      |                                                                    |
|  +--------------------+  +--------------------+                                                                    |
+--------------------------------------------------------------------------------------------------------------------+
```

### 4.2 Vista Jefe (Tablero General y Supervisión del Equipo)

Permite visualizar la carga global del área, filtrar por cada asistente, asignar responsabilidades y tramitar revisiones.

```text
+--------------------------------------------------------------------------------------------------------------------+
|  [LOGO] Planeamiento TAL  |  [Tablero General]  |  [Bandeja Aprobaciones (3)]  |  [Mis Tareas]  | [Dark] [User: Ing. Silva (Jefe)] |
+--------------------------------------------------------------------------------------------------------------------+
|  FILTROS:  [ + Crear y Asignar Tarea ]  [ Asistente: Todos v ]  [ Prioridad: Todas v ]  [ Ver: Kanban | Lista ]     |
|  RESUMEN:  Total: 24 | Pendientes: 8 | En Proceso: 6 | Bloqueadas: 3 | En Revisión: 3 | Finalizadas: 4             |
+--------------------------------------------------------------------------------------------------------------------+
|                                           TABLERO OFICIAL DEL ÁREA DE PLANEAMIENTO                                 |
|                                                                                                                    |
|  [ PENDIENTE (8) ]       [ EN PROCESO (6) ]     [ BLOQUEADA (3) ]        [ EN REVISIÓN (3) ]    [ FINALIZADA (4) ] |
|  +--------------------+  +--------------------+ +----------------------+ +--------------------+ +------------------+ |
|  | #110 | Del Área    |  | #101 | Del Área    | | #106 | Del Área      | | #98 | Del Área     | | #92 | Del Área   | |
|  | Proyección Q4      |  | Carga datos SIP    | | Consolidado semanal  | | Matriz de costos   | | Auditoría Q2     | |
|  | Resp: Asistente 2  |  | Resp: Marco C.     | | Resp: Marco C.       | | Resp: Marco C.     | | Resp: Asistente 3| |
|  | [Alta] 24-Oct      |  | [Alta] 15-Oct      | | [!] Bloq por: #101   | | [!] REQUIERE TU OK | | [Ver detalle]    | |
|  | [ Reasignar v ]    |  | [ Reasignar v ]    | | [ Reasignar v ]      | | [ REVISAR AHORA ]  | |                  |
|  +--------------------+  +--------------------+ +----------------------+ +--------------------+ +------------------+ |
+--------------------------------------------------------------------------------------------------------------------+
```

### 4.3 Bandeja de Aprobaciones (Exclusiva para Jefes)

Pantalla dedicada para atender cuellos de botella y autorizar pasos clave del proceso.

```text
+--------------------------------------------------------------------------------------------------------------------+
|  BANDEJA DE APROBACIONES PENDIENTES (3 tareas requieren tu autorización)                                           |
+--------------------------------------------------------------------------------------------------------------------+
|  TAREA #98: Matriz de costos de operaciones                                                                       |
|  Responsable: Marco Chumbes (Asistente) | Entregado: 13-Oct 14:30 | Prioridad: Alta                                |
|  Evidencias/Adjuntos: [Archivo: matriz_costos_v1.xlsx (2.4 MB)]                                                     |
|  Comentario de entrega: "Se completaron las subtareas 1 y 2 con datos conciliados de almacén central".             |
|  Dependencias que se desbloquearán:                                                                               |
|    -> Desbloqueará #102: "Elaboración de informe de gerencia" (Asistente 2)                                         |
|                                                                                                                    |
|  Acciones:                                                                                                         |
|  [ ✓ APROBAR Y FINALIZAR TAREA ]     [ ↺ DEVOLVER CON OBSERVACIONES ]     [ Ver Historial Completo ]               |
+--------------------------------------------------------------------------------------------------------------------+
```

---

## 5. Modales Clave y Flujos de Interacción

### 5.1 Modal de Creación / Edición de Tarea

El modal adapta automáticamente sus campos según el rol autenticado:

```text
+-----------------------------------------------------------------------------------------+
|  CREAR NUEVA TAREA                                                                 [ X ]|
+-----------------------------------------------------------------------------------------+
|  Título de la tarea:                                                                    |
|  [ Elaboración de informe de control de inventarios                                  ]  |
|                                                                                         |
|  Tipo de tarea:                                                                         |
|  ( ) Tarea Personal (privada)      (•) Tarea Oficial del Área                           |
|                                                                                         |
|  Responsable:                                                                           |
|  [ Marco Chumbes (Tú mismo)                                                    [v] ]   |
|  *Nota para asistente: Solo puedes asignarte tareas a ti mismo.                         |
|  *(Si el usuario es Jefe: selector abierto con lista completa de asistentes y jefes)   |
|                                                                                         |
|  Prioridad:            Fecha límite:           Colaboradores (opcional):                |
|  [ Alta           v ]  [ 2026-10-20       ]    [ Seleccionar miembros...          v ]   |
|                                                                                         |
|  [x] Requiere aprobación previa al cierre                                               |
|      Revisor asignado: [ Ing. Silva (Jefe de Planeamiento)                        v ]   |
|                                                                                         |
|  Dependencias predecesoras (Grafo DAG):                                                 |
|  [ Buscar tareas predecesoras que deben finalizar antes...                       v ]   |
|  Predecesoras seleccionadas:                                                            |
|    - #101: Carga de datos SIP (Marco C.) [x quitar]                                     |
|                                                                                         |
|  Descripción / Instrucciones:                                                           |
|  [ Detalle de los entregables esperados y criterios de verificación...              ]   |
|                                                                                         |
|  + Subtareas rápidas:                                                                   |
|  [ ] Extraer dump de existencias                                                        |
|  [ ] Conciliar variaciones > 5%                                                         |
|  [ + Añadir subtarea ]                                                                  |
+-----------------------------------------------------------------------------------------+
|                                              [ Cancelar ]   [ Guardar y Crear Tarea ]   |
+-----------------------------------------------------------------------------------------+
```

### 5.2 Modal de Confirmación Explícita de Cierre (RF-12, RN-11, RN-12)

**Obligatorio** cuando una tarea **NO requiere aprobación** y el usuario intenta moverla a la columna `Finalizada` (sea por arrastre o por menú de selección):

```text
+-----------------------------------------------------------------------------------------+
|  CONFIRMACIÓN DE CIERRE DE TAREA                                                   [ X ]|
+-----------------------------------------------------------------------------------------+
|  ¿Confirmas que has completado la tarea #101: "Carga de datos SIP"?                     |
|                                                                                         |
|  INFORMACIÓN DE IMPACTO:                                                                |
|  - Estado actual: En proceso -> Pasará a: FINALIZADA.                                   |
|  - Esta tarea NO requiere aprobación de jefatura.                                       |
|  - Subtareas completadas: 2 de 2 (100%).                                                |
|                                                                                         |
|  AUTOMATIZACIÓN DE DEPENDENCIAS (DAG):                                                  |
|  Al confirmar este cierre, se evaluarán las dependencias sucesoras.                     |
|  -> Tarea #106 ("Consolidado semanal"): Se DESBLOQUEARÁ automáticamente al cumplirse   |
|     todas sus predecesoras (Regla AND).                                                 |
|                                                                                         |
|  [ ] Certifico que los datos cargados fueron validados conforme a procedimiento.        |
+-----------------------------------------------------------------------------------------+
|                    [ Cancelar y Mantener En Proceso ]    [ Sí, Confirmar y Cerrar Tarea]|
+-----------------------------------------------------------------------------------------+
```
> **Comportamiento si se presiona "Cancelar":** La tarjeta se devuelve visualmente a su columna previa (`En proceso`) sin registrar ningún cambio en la base de datos ni disparar eventos de automatización.

### 5.3 Modal de Devolución con Observaciones (RN-10)

Cuando un jefe revisa una tarea y no la aprueba:

```text
+-----------------------------------------------------------------------------------------+
|  DEVOLVER TAREA CON OBSERVACIONES                                                  [ X ]|
+-----------------------------------------------------------------------------------------+
|  Tarea: #98 - Matriz de costos de operaciones                                           |
|  Responsable: Marco Chumbes                                                             |
|                                                                                         |
|  Motivo de la devolución / Correcciones requeridas:                                     |
|  +-----------------------------------------------------------------------------------+  |
|  | Falta conciliar la columna de mermas del almacén B con el cierre de la semana 41. |  |
|  | Por favor revisar la pestaña 2 y volver a someter a revisión.                     |  |
|  +-----------------------------------------------------------------------------------+  |
|                                                                                         |
|  Efecto en el flujo:                                                                    |
|  - El estado regresará a: EN PROCESO.                                                   |
|  - Se enviará una notificación interna inmediata al responsable con tus notas.          |
|  - Las tareas dependientes continuarán BLOQUEADAS.                                      |
+-----------------------------------------------------------------------------------------+
|                                    [ Cancelar ]    [ Enviar Devolución al Responsable ] |
+-----------------------------------------------------------------------------------------+
```

---

## 6. Accesibilidad y Alternativas al Drag & Drop

Para garantizar que el sistema cumpla con estándares de accesibilidad y soporte teclado completo:

1. **Menú de Acción Rápida en Tarjeta (`···`):**
   - Atajo de teclado: foco en tarjeta + `Enter` o `Espacio`.
   - Menú desplegable con opciones:
     - `Ver detalle completo` (`D`)
     - `Mover a: Pendiente`
     - `Mover a: En proceso`
     - `Mover a: En revisión` (si requiere aprobación)
     - `Mover a: Finalizada...` (abre modal de confirmación)
     - `Asignar colaborador...`
2. **Navegación por columnas:**
   - Flechas `←` y `→` navegan entre columnas del Kanban.
   - Flechas `↑` y `↓` navegan entre tarjetas de la misma columna.

---

## 7. Estados de Interfaz (UI States)

| Estado | Representación Visual | Comportamiento |
| :--- | :--- | :--- |
| **Cargando (Loading)** | *Skeleton screens* con gradiente pulso suave en columnas y tarjetas | Previene saltos de layout (*CLS*) durante peticiones a la API. |
| **Vacío (Empty state)** | Ilustración discreta y mensaje amigable: *"No tienes tareas pendientes. ¡Buen trabajo!"* | Botón de acción directa `[ + Crear tarea ]`. |
| **Bloqueada (Blocked)** | Borde sutil color rubí (`--status-blocked`), icono de candado `🔒` y etiqueta con el ID de la tarea bloqueante | Al pasar el cursor o enfocar, tooltip accesible: *"Bloqueada por #101"*. |
| **En Revisión** | Borde color ámbar (`--status-review`), icono de reloj `⏳` y etiqueta *"En espera de aprobación"*. | Solo lectura para el asistente hasta que el jefe decida. |
| **Error de Red** | Banner flotante accesible (*toast alert*) con botón `[ Reintentar ]` | Conserva el estado local del formulario o movimiento sin perder datos. |
# Nota de vigencia

Este documento conserva wireframes históricos de exploración. El diseño operativo vigente expone únicamente la Vista Área, Aprobaciones, Historial y Gestión de Usuarios; las pantallas de Vista Personal y Dashboard no forman parte de la interfaz actual.
