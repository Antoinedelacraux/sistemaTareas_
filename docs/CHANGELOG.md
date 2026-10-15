# Changelog

## [Sin versión - Ajuste visual de Gestión de Usuarios] - 2026-10-15
### Modificado
- La vista de Gestión de Usuarios ahora usa un contenedor común alineado con el espacio de trabajo, una cabecera compacta y filtros distribuidos en una cuadrícula estable.
- La tabla reserva columnas para código, rol, estado, fecha y acciones; los botones de administración conservan su espacio sin comprimir el nombre ni el correo.
- Se añadieron adaptaciones para pantallas medianas y móviles, manteniendo la tabla desplazable cuando el ancho no permite presentar todas las columnas con legibilidad.
- Se corrigió el ensamblado de la vista: Usuarios vuelve a estar dentro de `<main class="main-content">`, evitando que el espacio flexible del contenido principal la empuje hacia abajo al cambiar el zoom.
- Se retiró el límite fijo de 1440px para que la vista ocupe el ancho disponible, igual que los demás módulos.

## [Sin versión - Gestión de Usuarios] - 2026-10-14
### Agregado
- Módulo exclusivo de Jefatura para listar, filtrar, crear, editar, cambiar contraseñas, asignar roles y activar/desactivar cuentas.
- API administrativa separada bajo `/api/gestion-usuarios/*`, auditoría de acciones y revocación de sesiones tras desactivación, cambio de contraseña o cambio de rol.

### Modificado
- La creación de cuentas deja de existir en `/api/usuarios`; ese recurso queda reservado a responsables de tareas y validación de asignaciones.
- Ambos roles de Jefatura administran cuentas; solo se bloquea que el Jefe de Personal desactive una cuenta con rol Jefe de Área.

## [Sin versión - Ciclo de cierre y vencimientos] - 2026-10-14
### Agregado
- Archivo automático al historial tras 24 horas en `FINALIZADA` y acción exclusiva de Jefatura para archivar de inmediato.
- Indicador rojo e icono de vencimiento en tarjetas activas; columna de cumplimiento en Historial.

### Modificado
- Asistentes consultan tareas finalizadas sin editarlas ni eliminarlas; Jefatura puede editar, eliminar o reabrirlas hacia `EN_PROCESO`.
- El selector de estado de columna muestra solamente los nombres de los estados.

## [Sin versión - Tablero único de Área] - 2026-10-14
### Modificado
- Retirada la Vista Personal, su borrado masivo y sus flujos de creación/columnas; las tareas nuevas se fuerzan a Área.
- Asistentes: creación autoasignada y visibilidad limitada a sus tareas. Jefaturas: supervisión total y asignación limitada a asistentes activos.
- Comentarios y evidencias alineados: primer nombre del autor, adjuntos guardados de forma segura y descarga autorizada; se permite enviar un archivo sin texto.
- La SPA se sirve desde `index.php` en Apache para usar los partials vigentes en lugar de la copia histórica `index.html`.

## [Sin version] - 2026-10-14
### Modificado
- Login con panel de imagen corporativa de mayor contraste para `TAL S.A.`, `Área de Planeamiento` y la descripción operativa.
- Mejorada la escala de espaciado, jerarquía tipográfica, tamaño de controles y adaptación responsive del formulario de acceso.
- Flechas de cuentas demo reemplazadas por iconos SVG accesibles.

## [Sin version] - 2026-10-14
### Agregado
- Boton **Borrar todo** en la Vista Personal. Requiere confirmacion y la API elimina exclusivamente tareas personales propias del usuario autenticado.
- Indicador de candado profesional en la cabecera de columnas `FINALIZADA`; al seleccionarlo explica el cierre de solo lectura.

### Modificado
- Una tarea en una columna `FINALIZADA` queda disponible solo para visualizacion, sin etiquetas ni avisos repetidos dentro de la tarjeta o el modal.
- Sustituidos emojis visibles por SVG o texto profesional.
Convención sugerida: Keep a Changelog, sin versiones ficticias.

## [V1.5.7 - Modularización Frontend, Separación de Responsabilidades y Aislamiento Estricto del Login] - 2026-10-14
### Agregado
- **Skill de Descomposición Modular (`modularizar-archivos-grandes`):**
  - Creada en `.agents/skills/modularizar-archivos-grandes/SKILL.md` con pautas para descomponer monolitos extensos (>400 líneas HTML, >500 líneas CSS).
- **Estructura Atómica de Partials HTML (`public_html/partials/`):**
  - Descomposición de la plantilla principal en 9 módulos independientes: `head.html`, `header.html`, `workspace_bar.html`, `login.html`, `kanban.html`, `approvals.html`, `history.html`, `modals.html` y `footer.html`.
  - Ensamblador nativo `public_html/index.php` con fallback automático en `server.php`.
- **Estructura Modular de Hojas de Estilo por Dominio (`public_html/assets/css/`):**
  - `variables.css`: Tokens de diseño y paletas temáticas.
  - `base.css`: Reset, utilidades tipográficas, botones (.btn), inputs, badges y barras de progreso.
  - `header.css`: Barra superior corporativa, perfil de usuario, campana de notificaciones y workspace bar.
  - `login.css`: Vista de acceso 100% aislada, imagen lateral 3D TAL S.A. y accesos demo.
  - `kanban.css`: Tablero kanban, columnas dinámicas, tarjetas de tarea y banner de vista personal.
  - `views.css`: Aprobaciones técnicas, archivo histórico, balance de carga y auditoría.
  - `modals.css`: Modales accesibles, detalle sin scroll, checklist, dependencias DAG y toasts.
  - `footer.css`: Pie de página corporativo slim y pill de autodiagnóstico.

### Modificado
- **Aislamiento Total del Login y Corrección de Solapamiento:**
  - Desacoplamiento definitivo de `login.css`.
  - Eliminación de reglas `!important` en el display de `.login-view-container`.
  - Garantía de mutua exclusividad: el login y el tablero Kanban nunca coexisten ni se solapan visualmente.
- **Hoja Maestra `app.css`:**
  - Reducida de un archivo monolítico de 2,857 líneas a un manifiesto maestro limpio con directivas `@import` por dominio.

## [V1.5.4 - Rediseño de Login Réplica Referencia, Botón + Columna Superior y Lógica Operativa de Estados en Columnas] - 2026-10-14
### Agregado
- **Rediseño del Login según Imagen de Referencia:**
  - Tarjeta flotante orgánica split con bordes redondeados (`border-radius: 36px`), adaptada al tema corporativo esmeralda de TAL S.A.
  - *Panel Izquierdo:* Curva orgánica fluida verde esmeralda con detalle cóncavo blanco inferior, isotipo circular blanco con llama verde corporativa TAL, título "Welcome Back!", subtítulo y botón contorneado de píldora "SIGN IN". Pie institucional: "TAL S.A. | PLANEAMIENTO 2026".
  - *Panel Derecho:* Título minúscula "welcome" en verde esmeralda, subtítulo "Login in to your account to continue", campos de texto alargados en forma de píldora menta pastel suave translúcido con placeholders punteados (`Email....................` y `Password....................`), enlace de recuperación, botón píldora verde esmeralda "LOG IN", y barra de chips de acceso rápido demostrativo para alternar entre usuarios (Marco Chumbes, Elena Vega, Roberto Sánchez).
- **Botón de Creación Rápida de Columna (`+ Columna`):**
  - Añadido `#btn-open-create-column` en la barra de controles superior (`#workspace-controls-bar`) para permitir a los roles de Jefatura crear columnas inmediatamente sin requerir scroll horizontal o vertical.

### Modificado
- **Lógica Operativa de Estados Asociados a Columnas:**
  - `Pendiente / Por hacer` y `En Proceso / En curso`: Libres de lógica restrictiva operativa.
  - `Revisión / Aprobación (`EN_REVISION`):* Al mover cualquier tarea a una columna de este tipo, se solicita revisión de forma atómica y la tarea ingresa automáticamente a la bandeja del **Módulo de Aprobaciones**.
  - `Finalizada / Cumplida (`FINALIZADA`):* La tarea queda **cerrada para Asistentes**. Se bloquea su arrastre (`draggable="false"`, badge `🔒 CERRADA`), se deshabilita el selector de cambio de columna, se deshabilitan las subtareas del checklist, se oculta el formulario de nuevos comentarios/evidencias con aviso visual de tarea cerrada, y el backend deniega cualquier interacción con código 403. **Solo los roles de Jefatura pueden interactuar con tareas finalizadas**.
- **Actualización del Módulo de Aprobaciones:**
  - Consulta unificada en `Approval::listPendientesForUser` que detecta e incluye en la bandeja de Jefatura todas las tareas en columnas con `c.tipo_estado = 'EN_REVISION'` o con solicitudes activas `a.estado = 'PENDIENTE'`.
  - Normalización en `BoardColumn.php` y `BoardController.php` para valores amigables de tipo de estado.

## [V1.5.3 - Retiro de Bitácora de Formulario, Modal Expandido sin Scroll y Permisos Estrictos por Rol] - 2026-10-14
### Eliminado
- **Bitácora de Auditoría en Modal de Tarea (`#modal-task-detail`):**
  - Removido el bloque HTML de la "Bitácora de Auditoría Inmutable" y el badge `#detail-audit-count`.
  - Retirada la llamada asíncrona a `/api/tareas/{id}/historial` y la invocación de `renderAuditTimeline()` en `task-modal.js`.
  - La trazabilidad histórica completa permanece centralizada y disponible para roles de Jefatura en el Módulo de Historial.

### Modificado
- **Ergonomía Visual y Eliminación de Scrollbars en Modal de Detalle (`UI-TASK-01`):**
  - Nuevo estilo `.modal-dialog.modal-dialog-task-detail` con ancho máximo de 1140px (95vw) y altura máxima de 94vh.
  - Cuadrícula balanceada de 2 columnas (1.05fr / 0.95fr): Columna izquierda (datos generales, checklist de subtareas, dependencias) y columna derecha (comentarios y evidencias adjuntas).
  - Compactación de paddings en `.section-box` (0.85rem), listas con micro-scrollbars sutiles solo cuando el contenido interno excede la altura asignada, garantizando que todo el modal se visualice completo sin requerir desplazamiento de la ventana principal.
- **Diferenciación Estricta de Funciones por Roles (Jefatura vs Asistentes):**
  - **Jefe de Planeamiento y Jefe de Personal:**
    - Acceso irrestricto a todos los módulos: Vista Área, Vista Personal, Tablero del Equipo, Aprobaciones, Historial y Gestión de Usuarios.
    - Facultades completas: crear tareas, asignar y reasignar tareas a cualquier miembro, vincular y desvincular dependencias DAG, editar y eliminar tareas tanto en Vista Área como en Vista Personal.
    - Capacidad de agregar nuevas columnas (`+ Añadir otra columna`) y personalizar columnas existentes en ambos tableros.
  - **Asistentes Operativos:**
    - Acceso limitado únicamente a **Vista Área** y **Vista Personal**.
    - *En Vista Área:* Pueden visualizar sus tareas asignadas, marcar checklist, enviar comentarios y adjuntar evidencias. Visualizan dependencias asignadas en modo de solo lectura (sin formulario de vinculación ni botones de eliminación). No pueden editar ni eliminar tareas de área, ni asignar tareas a compañeros, ni gestionar columnas. El botón de "+ Nueva Tarea" se oculta en el alcance oficial del área.
    - *En Vista Personal:* Permisos completos para crear tareas personales, editarlas, eliminarlas, gestionar checklist y comentarios. La sección de dependencias se oculta por completo por ser de ámbito individual privado; el backend rechaza con 422 cualquier intento de crear dependencias sobre tareas personales.
- **Robustez de Generación de Códigos de Tareas (`Task::generateCodigo`):**
  - Cálculo del número correlativo máximo existente con verificación de colisiones para evitar violaciones de clave única (`UNIQUE constraint failed: tareas.codigo`) tras eliminaciones de registros.
- **Suite de Pruebas Autónomas (`/api/test-runner`):**
  - Test 9 ajustado a las nuevas reglas de negocio autorizando la configuración de dependencias a roles de Jefatura.
  - 10 de 10 pruebas superadas con éxito (100% PASS en 271.03 ms).

## [V1.5.2 - Corrección de Conmutación de Vistas y Módulos en Navegación SPA] - 2026-10-14
### Corregido
- **Error Crítico de Conmutación en Pestañas (`app.js`):**
  - Resuelto fallo de JavaScript `ReferenceError: isCurrentUserJefe is not defined` en la función `setScope(scope)`. El método `isCurrentUserJefe` no estaba importado desde `./modules/state.js`, lo que provocaba que al conmutar a la Vista Personal y luego intentar seleccionar cualquier otro módulo (Vista Área, Tablero del Equipo, Aprobaciones o Historial), la evaluación `!isCurrentUserJefe()` abortara la ejecución del hilo principal de JavaScript, impidiendo la actualización de vistas y congelando la pantalla.
  - Se importó `isCurrentUserJefe` desde `./modules/state.js` en `app.js`.
  - Se expuso y exportó `setScope` globalmente en `window.setScope = setScope` para permitir llamadas interoperables desde el modal de creación de tareas (`task-modal.js`).
  - Añadida salvaguarda en `fetchTasks` (`kanban.js`) para garantizar que `STATE.columns` permanezca poblado ante refrescos de vista.
  - Actualizada la etiqueta de versión y consulta de activos a `v1.5.2` en `index.html` para invalidar cachés del navegador.

## [V1.5.1 - Separación Estricta de Vista Área vs Vista Personal y Creación Adaptativa] - 2026-10-14
### Añadido
- **Separación en Dos Vistas Dedicadas en Navegación Principal (`VISTAS-01`):**
  - **Vista Área (`#tab-area-tasks`, `scope=area`):** Agrupa exclusivamente las tareas oficiales del área (`tipo_tarea = 'AREA'`). A estas tareas les aplica la regla de expiración de 24h, tras la cual pasan al Historial de Jefatura.
  - **Vista Personal (`#tab-personal-tasks`, `scope=personal`):** Tablero individual privado para que cada usuario (asistentes y jefes) organice sus tareas y pendientes cotidianos (`tipo_tarea = 'PERSONAL'`).
  - **Banner Explicativo de Vista Personal:** Elemento informativo `#personal-board-banner` en la parte superior del tablero que aclara que las tareas personales no se transfieren al historial y se limpian mediante el botón de eliminación manual.
- **Creación Adaptativa de Tareas:**
  - El modal de creación `#modal-create-task` detecta la vista activa y preselecciona automáticamente el tipo de tarea (`AREA` o `PERSONAL`).
  - Si se selecciona o crea una tarea personal, el responsable se autoasigna obligatoriamente a la persona autenticada y se desactiva el circuito formal de aprobación (RN-10).
  - Al crear una tarea de tipo alternativo al alcance activo, el sistema conmuta automáticamente a la pestaña correspondiente para visualización inmediata.
- **Aislamiento Estricto en Backend (`Task.php`):**
  - `Task::listForUser` aísla por completo tareas `AREA` de `PERSONAL` según el scope solicitado. En la Vista Personal no se aplica el cutoff de 24 horas.
  - `Task::listArchived` garantiza de forma estricta que únicamente las tareas de tipo `AREA` ingresan al módulo de Historial de Jefatura.

## [V1.5.0 - Retiro de Dashboard, Regla 24h y Módulo de Historial para Jefatura] - 2026-10-14
### Eliminado
- **Módulo Dashboard & Métricas:**
  - Retirada la vista `#dashboard-view`, tarjetas de KPIs y tabla de balance de equipo a solicitud del usuario.
  - Vaciados y deprecados los servicios de cálculo analítico (`app/Services/DashboardService.php`, `app/Controllers/DashboardController.php`, `public_html/assets/js/modules/dashboard.js`).
  - Removido el endpoint `/api/dashboard/metricas`.

### Añadido
- **Regla de Expiración de 24 Horas en Columna Finalizada:**
  - Modificación en `Task::listForUser` para que las tareas en estado `FINALIZADA` únicamente permanezcan visibles en el tablero Kanban activo por un periodo máximo de 24 horas (`t.fecha_finalizada > gmdate('Y-m-d H:i:s', time() - 86400)`).
  - Al superar las 24 horas, las tareas finalizadas se retiran de manera automática del flujo activo de trabajo sin requerir cron jobs ni scripts externos.
- **Módulo de Historial de Tareas Archivadas (`HISTORIAL-01`):**
  - Nuevo endpoint protegido `GET /api/historial` en `app/Controllers/HistoryController.php` conectado al método `Task::listArchived`.
  - Control de acceso RBAC de doble capa: asistentes reciben `403 Forbidden` (`ERR_FORBIDDEN_HISTORY_ACCESS`); solo roles de Jefatura (`JEFE_PLANEAMIENTO`, `JEFE_PERSONAL`) tienen autorización.
  - Módulo cliente `public_html/assets/js/modules/history.js` con listado reactivo, barra de búsqueda en tiempo real, filtros combinados por miembro asignado y prioridad.
  - Tabla responsiva con código de tarea, título, descripción abreviada, responsable con avatar, creador, badge de prioridad, badge de tipo, fecha de finalización con tiempo relativo transcurrido ("Hace X horas/días") y botón de acceso "Expediente".
  - Apertura del expediente completo mediante el modal de detalle existente (`openTaskDetailModal`), permitiendo consultar el historial inmutable de auditoría, evidencias adjuntas para descarga, checklist de subtareas y comentarios registrados.
- **Test Runner Web Actualizado:**
  - Caso de prueba 10 adaptado en `TestRunnerController.php` para validar en memoria: exclusión de tareas >24h del Kanban activo, retención de tareas <24h en Kanban, consulta del historial por jefatura y denegación estricta de acceso al historial para asistentes (10/10 PASS).

## [V1.4.1 - Corrección de Interacciones, Edición In-Modal, Gestión de Usuarios, Columnas Trello y Test Runner Autónomo] - 2026-10-14
### Añadido
- **Test Runner Web Autónomo en Memoria (`TestRunnerController`):**
  - Endpoint `/api/test-runner` ejecutable directamente desde el navegador vía HTTP sin necesidad de consola de comandos, terminal ni privilegios de administrador para PowerShell.
  - Ejecuta y valida en memoria los 10 subsistemas del sistema (Base de Datos, RBAC, Tareas, Columnas, Transiciones, Subtareas, Comentarios, Aprobaciones, DAG y Dashboard) en **<200 ms** (10/10 PASS).
  - Soporta interfaz visual en modo oscuro y salida cruda en JSON vía `?format=json`.
  - Acceso directo mediante enlace `🧪 Diagnóstico del Sistema` integrado discretamente en el footer de la plataforma.
- **Edición Completa de Tareas In-Modal:**
  - Botón `#btn-edit-task` en el pie del modal de detalle para alternar instantáneamente entre modo lectura y modo edición (`toggleTaskEditMode`).
  - Formulario `#edit-task-form` que permite modificar título, descripción, prioridad, tipo de tarea, fecha límite y responsable asignado.
  - Conexión directa con `PUT /api/tareas/{id}` y recarga reactiva sin refrescar la página.
- **Creación y Gestión de Usuarios (RF-02):**
  - Endpoint `POST /api/usuarios` con validación de roles (`ASISTENTE`, `JEFE_PLANEAMIENTO`, `JEFE_PERSONAL`), contraseña encriptada por Bcrypt y registro inmutable en auditoría.
  - Módulo cliente `public_html/assets/js/modules/users.js` con método `openCreateUserModal` y `handleCreateUser`.
  - Diálogo modal `#modal-create-user` y botón `+ Usuario` en la barra superior con control de visibilidad por rol (exclusivo para Jefatura).
- **Columnas Dinámicas Personalizables Estilo Trello con Paleta de Colores:**
  - Endpoints REST `POST /api/tableros/columnas`, `PUT /api/tableros/columnas/{id}` y `DELETE /api/tableros/columnas/{id}`.
  - Módulo cliente `public_html/assets/js/modules/columns.js` con soporte para paleta cromática de 8 colores (Azul Océano, Púrpura Real, Verde Esmeralda, Ámbar Cálido, Rosa Coral, Cian Glaciar, Índigo Profundo, Gris Pizarra).
  - Modal `#modal-column-editor` para configurar nombre, estado operativo asociado y color de distinción.
  - Tarjeta interactiva "+ Añadir otra columna" al final del tablero Kanban y botón de edición en cada cabecera.
  - Bordes superiores con acento cromático `border-top: 4px solid {color_hex}` en las columnas.

### Corregido
- **Identificación de Tareas y Manejo de Envelopes JSON:**
  - Corrección de desestructuración en `task-modal.js` extrayendo `taskJson.data?.tarea || taskJson.data`, resolviendo los errores de "Identificador de tarea inválido" y "Error al cargar detalle de la tarea".
  - Normalización de arreglos para subtareas (`subJson.data?.subtareas`) y comentarios (`comJson.data?.comentarios`).

## [V1.4.0 - Modularización Frontend, Optimización Lighthouse (95+), Login Dinámico Bento y Aceleración Backend] - 2026-10-14
### Añadido
- **Arquitectura Modular de Frontend (Separación de Responsabilidades):** Desacople completo del archivo monolítico `app.js` (88KB) en 9 módulos ES6 especializados bajo `public_html/assets/js/modules/`:
  - `api.js`: Cliente HTTP centralizado con token Bearer, credenciales y manejo de errores.
  - `state.js`: Estado reactivo central (`STATE`) y helpers de permisos (`canAssignOthers`, etc.).
  - `ui.js`: Temas claro/oscuro, toasts accesibles, modales y formateadores.
  - `auth.js`: Sesión rápida, login con feedback visual de botón (`.icon-spin`), quick login y logout.
  - `kanban.js`: Renderizado de columnas, tarjetas con progreso de checklist, arrastre HTML5 Drag & Drop y transiciones.
  - `task-modal.js`: Ficha de detalle, CRUD de subtareas, comentarios con adjuntos, dependencias DAG y timeline de auditoría.
  - `approvals.js`: Circuito formal de revisión de jefatura y modal de devolución con observaciones (RN-12).
  - `dashboard.js`: KPIs, barras de progreso y balance de carga de equipo.
  - `notifications.js`: Campana, dropdown y sondeo no invasivo cada 30s.
  - `app.js`: Orquestador principal cargado con `<script type="module">`, exponiendo métodos requeridos en `window` para compatibilidad con handlers inline.
- **Rediseño Bento Split-Screen en Login con Micro-Interacciones Dinámicas:**
  - Panel izquierdo con imagen de bienvenida de TAL S.A., chips de características ("⚡ Kanban Fluido", "🛡️ Seguridad RBAC", "📊 Métricas en Vivo") y resplandor dinámico acelerado por hardware GPU (CSS3 puro, sin scripts pesados de Canvas/Three.js, 0% CPU overhead).
  - Panel derecho ergonómico con formulario centrado y chips de acceso rápido demostrativo.
- **Skill Especializada de UI:** Creación de `.agents/skills/diseno-interfaz-ui/SKILL.md` para checklist de diseño visual, centrado geométrico de iconos, sistema de márgenes en grid de 8px/4px, accesibilidad WCAG y auditoría Lighthouse.
- **Modal de Devolución de Tareas (`modal-return-task`):** Interfaz modal requerida para registrar observaciones formales al devolver tareas en revisión a asistentes (RN-12).

### Modificado
- **Aceleración Drástica del Login (de 2.1s a ~5ms):**
  - Creación de `.env` configurando `DB_DRIVER=sqlite` y `DB_PATH=storage/database.sqlite` para desarrollo local ultrarrápido.
  - Optimización de `app/Config/Database.php` con timeout TCP de 1s para MySQL y detección/fallback automático e inmediato a SQLite en desarrollo.
- **Optimización de Lighthouse:**
  - **Performance:** Retiro de `@import url(...)` bloqueante en `public_html/assets/css/app.css`, inclusión de `<link rel="preconnect">` y preloads en `index.html` para Google Fonts, renderizado directo de `#login-view` en HTML sin bloqueo FCP.
  - **Accessibility:** Corrección de ratios de contraste en badges (`ASISTENTE`, `JEFATURA`, `JEFE PERS.`, prioridades) superando WCAG AAA (>5.5:1), adición de `aria-label`, `autocomplete` y navegación por teclado en inputs y botones.
- **Geometría de Iconos y Márgenes UI:** Estandarización de márgenes y espaciados internos a múltiplos de 8px (paddings 16px/24px/32px) y centrado absoluto `display: inline-flex; align-items: center; justify-content: center` en todos los SVGs.

## [V1.3.1 - Rediseño de Login, Eliminación de Emojis por SVG, Footer Slim y Permisos Estrictos por Rol] - 2026-10-14
### Añadido
- **Pantalla de Login Corporativa y Rápida:** Rediseño integral de `#login-view` con tarjeta ejecutiva (`.login-card`), branding de TAL S.A., inputs con iconos vectoriales SVG y 3 chips de acceso rápido de demostración (Marco Chumbes, Elena Vega, Roberto Sánchez) con auto-completado y login inmediato en un clic.
- **Sistema de Iconos Vectoriales SVG:** Sustitución de todos los emojis de la interfaz por iconos vectoriales SVG inline con clase `.icon-svg` (portapapeles, usuarios, métricas, aprobaciones, campana, sol/luna, salida, candado, comentarios, clips, etc.).
- **Footer Slim y Profesional (`.app-footer-slim`):** Reemplazo del pie de página voluminoso por una barra compacta de 36px con tipografía reducida (`0.72rem`), indicador de pulso verde en tiempo real (`.status-pulse-dot`) y datos institucionales discretos.
- **Tarjetas Kanban Limpias:** Eliminación del selector desplegable invasivo `Mover: <select>` en cada tarjeta en favor del Drag & Drop nativo y del modal de detalle; integración de chips SVG para fecha límite y comentarios.

### Modificado
- **Aislamiento Estricto de Roles y Visibilidad:**
  - `Task::listForUser`: Los asistentes únicamente tienen visibilidad de sus tareas asignadas o creadas (`responsable_id == :userId OR creador_id == :userId`), sin importar parámetros externos. Las jefaturas supervisan todas las tareas del área y disponen del scope personal y de equipo.
  - `User::listForUser`: Los asistentes solo pueden listarse a sí mismos (evitando espionaje de nómina o asignación indebida a pares), mientras que las jefaturas listan al equipo completo.
  - Interfaz gráfica: La pestaña "Tablero del Equipo", "Aprobaciones", el filtro `#member-filter-select` y la tabla de balance de carga se ocultan automáticamente para asistentes.
- **Barra de Navegación Superior:** Se retira el selector artificial `#quick-role-select` de la cabecera autenticada; el cambio de perfil se gestiona ordenadamente a través de los accesos de prueba en la pantalla de login.

## [V1.3.0 - Refactorización Ágil, Tablero Fluido y Roles Colaborativos] - 2026-10-13 (Fase 1.5)
### Añadido
- **Drag & Drop Nativo HTML5:** Interacción suave de arrastrar y soltar tarjetas entre columnas del tablero Kanban sin diálogos modales molestos ni bloqueos artificiales (**D-012**).
- **Tablero Colaborativo del Equipo:** Pestaña "Tablero del Equipo" accesible para todos los roles para visualizar en tiempo real en qué trabaja el área, evitando duplicidad de esfuerzo.
- **Filtro Rápido por Miembro del Equipo:** Selector dinámico `#member-filter-select` en la barra de control para filtrar instantáneamente el tablero por cualquier colaborador del área.
- **Progreso Visual en Checklist de Subtareas:** Barra de progreso porcentual dinámica en el modal de detalle que refleja el avance acumulado al marcar/desmarcar ítems.
- **Botón de Eliminación Directa:** Eliminación rápida de tareas en el pie del modal para simplificar la gestión diaria.
- **Runner de Pruebas Unificado (`tests/run_all.php`):** Ejecución secuencial y reporte consolidado de las 10 suites de prueba del sistema.

### Modificado
- **Flujo de Transición Kanban:** Eliminación de pantallas de confirmación obligatorias al arrastrar tarjetas; las transiciones aplican de inmediato en frontend y backend.
- **Modelo de Visibilidad:** `Task::listForUser` y `User::listForUser` soportan parámetro `?scope=team` para habilitar colaboración de equipo sin comprometer el aislamiento de tareas estrictamente personales (`PERSONAL`).
- **Arquitectura de Requisitos y Roadmap:** Consolidación de 12 requisitos funcionales prácticos en `docs/REQUIREMENTS.md` y registro de la decisión **D-012** en `docs/DECISIONS.md`.

## [V1.2.0 - MVP Completo: QA Integral, Seguridad y Piloto con 5 Usuarios] - 2026-10-13 (MVP-10)
### Añadido
- Culminación integral de la **Fase 1 (MVP)** del Sistema de Gestión y Seguimiento de Tareas de TAL S.A.
- Suite de pruebas de aceptación y auditoría de seguridad E2E `tests/qa_security_pilot_test.php` que verifica sistemáticamente:
  - Pruebas negativas y RBAC: Prevención de asignación indebida de asistentes a compañeros (**RN-03**).
  - Protección anti-IDOR/BOLA en todas las entidades: Tareas personales privadas, subtareas, comentarios, historial de auditoría y notificaciones (**RN-02, RN-05**).
  - Validación de los 12 criterios de aceptación mínima V1.2 (autoasignación, visibilidad por rol, confirmación de cierre RN-11, aprobaciones formales RN-10/RN-12, ramas paralelas y motor DAG RN-08/RF-16, conservación de tipo funcional RN-06, idempotencia RN-15, e historial inmutable RF-18/RN-13).
  - Seguridad en subida de archivos: Bloqueo de ejecutables y scripts en `UploadHandler` y reglas Apache `storage/uploads/.htaccess`.
  - Simulación colaborativa de extremo a extremo con los 5 usuarios reales del Área de Planeamiento de TAL S.A.
- Manual y guía completa de despliegue en producción para Web Hosting DonWeb Ferozo (`docs/DEPLOYMENT_AND_PILOT_GUIDE.md`):
  - Requisitos de servidor (PHP 8.2+, extensiones obligatorias, `mod_rewrite`, MySQL 8/InnoDB).
  - Aislamiento de directorios (`app/` y `storage/` fuera de la raíz pública).
  - Procedimiento de importación en phpMyAdmin (`schema.sql` y `seeds.sql`).
  - Configuración de variables de entorno `.env` en producción.
  - Manual de usuario rápido para Asistentes y Jefatura.
  - Plan de contingencia, backups diarios y procedimiento de rollback.

## [Sin versión - Historial, Auditoría Inmutable y Dashboard Operativo] - 2026-10-13 (MVP-09)
### Añadido
- Módulo completo de auditoría inmutable y dashboard operativo con métricas de cierre (**Fase 1: MVP-09**).
- Modelo relacional `app/Models/Audit.php` y tabla `auditoria_log` para registro inalterable de eventos críticos (**RF-18, RN-13**).
- Registro diferenciado de reasignación (`REASIGNAR`) en `Task::update`, documentando actor, responsable anterior y responsable nuevo en `detalles_json`.
- Servicio central de KPIs `app/Services/DashboardService.php` con filtrado por alcance y rol (**RF-19**):
  - Total de tareas, completadas, activas y cálculo de % tasa de cumplimiento.
  - Conteo por estado Kanban (`BACKLOG`, `EN_PROCESO`, `BLOQUEADA`, `EN_REVISION`, `FINALIZADA`).
  - Alertas de plazos: tareas vencidas (`fecha_limite < CURDATE()`) y tareas por vencer en ≤ 7 días.
  - Desglose de tareas activas por nivel de prioridad (`URGENTE`, `ALTA`, `MEDIA`, `BAJA`).
  - Matriz de balance de carga de trabajo por integrante del equipo (`balance_equipo`) con tareas asignadas, activas, completadas y atrasadas (restringido a roles de Jefatura).
- Controlador `app/Controllers/AuditController.php`:
  - `GET /api/tareas/{id}/historial`: Timeline de eventos inmutables de una tarea con protección anti-IDOR (**RN-02**, 403 Forbidden para asistentes en tareas no autorizadas).
  - `GET /api/auditoria`: Consulta global para Jefatura y auto-filtrada por usuario para Asistentes.
- Controlador `app/Controllers/DashboardController.php`:
  - `GET /api/dashboard/metricas`: Despacho de métricas según alcance (`global`, `area`, `personal`).
- Interfaz gráfica SPA (`index.html`, `app.css`, `app.js`):
  - Pestaña `#tab-dashboard-view` ("📊 Dashboard & Métricas") en la barra de control.
  - Vista `#dashboard-view` con grid de tarjetas KPI (`Total`, `Tasa de Cumplimiento`, `En Progreso / Revisión`, `Alertas de Plazo`).
  - Barras horizontales temáticas con porcentajes para distribución de estados Kanban y prioridades activas.
  - Sección `#dashboard-team-section` con tabla responsiva de balance de equipo visible únicamente para roles de Jefatura.
  - Contenedor `#detail-audit-box` y timeline `#detail-audit-timeline` en el modal de detalle para trazabilidad inmutable de cambios.
- Suite de pruebas automatizadas `tests/dashboard_audit_test.php` con 34 casos de prueba (100% PASS), alcanzando un acumulado de 222 pruebas superadas en el proyecto.

## [Sin versión - Automatizaciones y Notificaciones Idempotentes] - 2026-10-13 (MVP-08)
### Añadido
- Módulo completo de eventos automatizados y notificaciones internas con garantía estricta de idempotencia (**Fase 1: MVP-08**).
- Modelo relacional `app/Models/Notification.php` y tabla `notificaciones` con tracking de alertas para usuarios, tipos de evento, metadatos JSON y clave única de idempotencia `idempotency_key` (**RN-15**).
- Motor de automatización `app/Services/AutomationEngine.php` con disparadores para 5 eventos críticos del sistema:
  - `TAREA_ASIGNADA`: Notificación al nuevo responsable al crear o reasignar una tarea.
  - `APROBACION_SOLICITADA`: Notificación a la jefatura/revisor cuando una tarea pasa a revisión formal.
  - `TAREA_APROBADA`: Notificación al responsable y creador al emitirse dictamen favorable.
  - `TAREA_DEVUELTA`: Notificación al responsable con observaciones de devolución obligatorias para subsanación.
  - `TAREA_DESBLOQUEADA`: Notificación al responsable cuando una tarea sucesora es liberada por conclusión de dependencias DAG (**RF-16**).
  - `NUEVO_COMENTARIO`: Notificación al responsable asignado ante nuevas notas o evidencias (sin auto-notificar al autor).
- Controlador `app/Controllers/NotificationController.php` con 4 endpoints:
  - `GET /api/notificaciones`: Listado de notificaciones con soporte para filtro de no leídas y paginación.
  - `GET /api/notificaciones/resumen`: Conteo de no leídas y notificaciones recientes para la campana de cabecera.
  - `PATCH /api/notificaciones/{id}/leer`: Marcado individual como leída con protección anti-IDOR (**403 Forbidden** para otros usuarios).
  - `POST /api/notificaciones/marcar-todas-leidas`: Marcado masivo atómico de todas las pendientes del usuario.
- Interfaz gráfica SPA (`index.html`, `app.css`, `app.js`):
  - Botón de campana interactivo con contador badge dinámico en tiempo real (`#notif-badge`).
  - Dropdown desplegable `#notif-dropdown` con scroll, iconos por tipo de evento, tiempos relativos ("Hace 5 min") y enlace a la tarea.
  - Botón de marcado masivo "Marcar todas leídas".
  - Polling pasivo suave en segundo plano y actualización reactiva tras acciones del usuario.
- Suite de pruebas automatizadas `tests/notifications_automation_test.php` con 33 casos de prueba unitarios, de integración y de idempotencia estricta (100% PASS).

## [Sin versión - Dependencias entre Tareas y Motor DAG] - 2026-10-13 (MVP-07)
### Añadido
- Módulo completo de dependencias secuenciales, ramas en paralelo y validación de Grafo Acíclico Dirigido (**Fase 1: MVP-07**).
- Modelo relacional `app/Models/TaskDependency.php` y tabla `tarea_dependencias` con tracking de relaciones de precedencia.
- Algoritmo de detección de ciclos en grafos dirigidos para salvaguardar la regla **RN-07** (evita deadlocks directos e indirectos).
- Controlador `app/Controllers/DependencyController.php` con 3 endpoints:
  - `GET /api/tareas/{id}/dependencias`: Listado de predecesoras y sucesoras con estado y métricas de bloqueo.
  - `POST /api/tareas/{id}/dependencias`: Vinculación de predecesora con comprobación anti-ciclos y auto-bloqueo preventivo (**RN-08**).
  - `DELETE /api/tareas/{id}/dependencias/{pred_id}`: Eliminación de precedencia con auto-desbloqueo a `PENDIENTE`.
- Motor de desbloqueo en cascada integrado en `Task::transition` y `Approval::resolve`: al finalizarse una tarea, sus sucesoras se habilitan atómicamente cuando TODAS sus predecesoras hayan concluido (**RN-08, RF-16**).
- Protección de transiciones en `Task::transition`: deniega movimientos hacia `EN_PROCESO` o `FINALIZADA` si la tarea tiene dependencias pendientes (`409 Conflict`).
- Interfaz gráfica SPA:
  - Sección de dependencias en el modal de detalle con alerta visual de tarea bloqueada.
  - Listas interactivas de predecesoras y sucesoras con badges de estado y desvinculación.
  - Selector dinámico de tareas predecesoras disponibles.
- Suite de pruebas automatizadas `tests/dependencies_test.php` con 23 casos positivos, negativos y de integración HTTP (100% PASS).

## [Sin versión - Circuito Formal de Aprobaciones y Cierre] - 2026-10-13 (MVP-06)
### Añadido
- Módulo completo de circuito formal de aprobaciones y confirmación de cierre (**Fase 1: MVP-06**).
- Modelo relacional `app/Models/Approval.php` y tabla `tarea_aprobaciones` con tracking de solicitudes, revisiones y dictámenes (`PENDIENTE`, `APROBADA`, `DEVUELTA`).
- Controlador `app/Controllers/ApprovalController.php` con 4 endpoints:
  - `POST /api/tareas/{id}/solicitar-aprobacion`: Pasa la tarea a `EN_REVISION` y asigna revisor de jefatura (**RN-10**).
  - `POST /api/tareas/{id}/resolver-aprobacion`: Emite dictamen `APROBAR` o `DEVOLVER`. `APROBAR` marca la tarea como `FINALIZADA` con timestamp; `DEVOLVER` exige obligatoriamente observaciones no vacías (**RN-12**, 422 si falta), retorna la tarea a `EN_PROCESO` y resetea fecha de finalización.
  - `GET /api/aprobaciones/pendientes`: Bandeja centralizada de tareas en espera de dictamen para roles de jefatura (**RF-13**, 403 Forbidden para asistentes).
  - `GET /api/tareas/{id}/aprobaciones`: Historial completo de dictámenes, fechas y observaciones de la tarea.
- Integración en frontend SPA (`index.html`, `app.css`, `app.js`):
  - Pestaña "⚖️ Bandeja de Aprobaciones" exclusiva para jefatura con contador de pendientes.
  - Vista `#approvals-view` con tarjetas de aprobación interactiva.
  - Bloque contextual de aprobación en modal de detalle de tarea y modal `#modal-return-task` para observaciones requeridas.
- Suite de pruebas automatizadas `tests/approvals_test.php` con 23 casos positivos, negativos y de integración HTTP (100% PASS).

## [Sin versión - Interfaz SPA y Servidor Local de Desarrollo] - 2026-10-13 (Commit `e49c10f`)
### Añadido
- Interfaz gráfica Single Page Application (SPA) completa e interactiva con soporte para modo claro/oscuro persistente y estética TAL S.A.
- Servidor de desarrollo integrado PHP `server.php` para ejecución local en puerto 8000 con soporte para API REST y activos estáticos MIME.
- Fallback automático y transparente a SQLite local (`storage/database.sqlite`) en entorno de desarrollo si MySQL no está levantado, con inicialización y semillas automáticas.
- Tablero Kanban dinámico de 5 columnas con filtros por alcance, texto de búsqueda, prioridad y menú de transiciones rápidas.
- Selector de cambio rápido de usuarios/roles para pruebas inmediatas de políticas RBAC y alcance (Asistente 1, Asistente 2, Jefe Planeamiento, Jefe Personal).
- Modal de detalle de tarea integrado con checklist de subtareas dinámico (`PATCH /api/subtareas/{id}`), timeline de comentarios y carga/descarga de evidencias adjuntas.
- Modal de confirmación de cierre obligatorio con ratificación consciente por checkbox (**RN-11**).

## [Sin versión - Subtareas, Comentarios y Adjuntos] - 2026-10-13 (Commit `8d17cc5`)
### Añadido
- Módulo de subtareas, comentarios y subida/descarga segura de evidencias (**Fase 1: MVP-05**).
- Servicio `app/Core/UploadHandler.php` para almacenamiento sanitizado de adjuntos en `storage/uploads/` con nombres hasheados únicos, validación estricta de extensiones (PDF, imágenes, hojas de cálculo, documentos, texto), bloqueo de scripts maliciosos (.php) y cuota máxima de 10 MB.
- Modelos `app/Models/Subtask.php` y `app/Models/Comment.php`.
- Controladores y endpoints:
  - `GET /api/tareas/{id}/subtareas`: Listado de subtareas ordenadas.
  - `POST /api/tareas/{id}/subtareas`: Creación de subtareas con validación de permisos de edición sobre la tarea padre.
  - `PATCH /api/subtareas/{id}`: Actualización de estado completado o título.
  - `DELETE /api/subtareas/{id}`: Eliminación de subtareas.
  - `GET /api/tareas/{id}/comentarios`: Listado de comentarios y evidencias de la tarea.
  - `POST /api/tareas/{id}/comentarios`: Registro de comentarios de texto o evidencias con archivo adjunto procesado de forma segura.
  - `GET /api/comentarios/{id}/archivo`: Descarga de archivo adjunto protegida por alcance en servidor (anti-IDOR) y cabeceras de seguridad `X-Content-Type-Options: nosniff`.
- Soporte para formularios `multipart/form-data` y `$_FILES` en `app/Core/Router.php`.
- Suite de pruebas automatizadas `tests/subtasks_comments_test.php` con 22 casos positivos, negativos y de integración HTTP (100% PASS).

## [Sin versión - Tablero Kanban y Transiciones] - 2026-10-13 (Commit `746990f`)
### Añadido
- Módulo de tablero Kanban y motor de transiciones de estado (**Fase 1: MVP-04**).
- Modelo `app/Models/BoardColumn.php` para gestión de columnas y mapeo de estados funcionales (`PENDIENTE`, `EN_PROCESO`, `BLOQUEADA`, `EN_REVISION`, `FINALIZADA`).
- Controlador `app/Controllers/BoardController.php` con endpoints:
  - `GET /api/tableros/columnas`: Listado de columnas activas ordenadas por secuencia visual.
  - `GET /api/tableros/kanban`: Vista Kanban completa con tareas agrupadas por columna bajo alcance estricto de usuario (asistente vs jefatura) y métricas de resumen en tiempo real.
  - `POST /api/tareas/{id}/transicion`: Transición de tareas entre columnas aplicando validaciones atómicas: exige confirmación explícita (`confirmacion_cierre: true`) para cerrar tareas sin aprobación (422 si falta), bloquea cierre directo para tareas con aprobación (409 Conflict), gestiona fecha de finalización y registra eventos en auditoría.
- Método `Task::transition` en `app/Models/Task.php`.
- Suite de pruebas automatizadas `tests/kanban_test.php` con 18 casos positivos, negativos y de integración HTTP (100% PASS).

## [Sin versión - CRUD de Tareas y Alcance] - 2026-10-13 (Commit `09ab9a0`)
### Añadido
- Módulo integral de gestión de tareas personales y del área (**Fase 1: MVP-03**).
- Modelo `app/Models/Task.php` con generación de códigos correlativos `TAR-YYYY-NNNN`, alcance en consultas SQL por rol, reglas de visibilidad y métodos de persistencia con auditoría.
- Controlador `app/Controllers/TaskController.php` con endpoints:
  - `GET /api/tareas`: Listado con filtro por alcance; asistentes solo ven tareas asignadas o creadas por ellos; jefes ven tareas del área de todo el equipo y tareas personales propias. Soporte para filtros `?tipo=`, `?columna_id=`, `?prioridad=`, `?responsable_id=`, `?buscar=`.
  - `GET /api/tareas/{id}`: Detalle de tarea con protección anti-IDOR para tareas ajenas y salvaguarda de privacidad de tareas personales frente a jefes (**RF-07, RN-01, RN-02**).
  - `POST /api/tareas`: Creación de tareas; fuerza autoasignación en asistentes y deniega con 403 Forbidden intentos de asignar a compañeros (**RF-05, RN-03**); jefes pueden asignar a cualquier miembro activo (**RF-06, RN-04**).
  - `PUT /api/tareas/{id}`: Modificación de atributos de tareas; deniega reasignación de responsable ejecutada por asistentes (**RN-03**).
  - `DELETE /api/tareas/{id}`: Eliminación de tareas con autorización exclusiva para el creador o jefatura.
- Suite de pruebas automatizadas `tests/tasks_test.php` con 24 casos positivos, negativos y de integración HTTP (100% PASS).

## [Sin versión - Usuarios, Ámbitos y Asignación] - 2026-10-13 (Commit `c7308e7`)
### Añadido
- Módulo de consulta de usuarios y validación de asignaciones por rol (**Fase 1: MVP-02**).
- Endpoints en `app/Controllers/UserController.php`:
  - `GET /api/usuarios`: Retorna únicamente al propio usuario autenticado si es asistente (**RF-05, RN-03, RN-05**); retorna a todos los usuarios activos del área si es jefe (**RF-06, RN-04**) con soporte para filtros `?rol=` y `?buscar=`.
  - `GET /api/usuarios/{id}`: Retorna el perfil seguro del usuario si es el propio solicitante o si el solicitante es jefe. Bloquea con `403 Forbidden` (`ERR_FORBIDDEN_USER_ACCESS`) cualquier intento de un asistente de inspeccionar perfiles ajenos (protección anti-IDOR/BOLA).
  - `POST /api/usuarios/validar-asignacion`: Valida formalmente si el solicitante puede asignar tareas a un responsable específico, impidiendo que asistentes asignen tareas a compañeros o jefes.
- Métodos en modelo `app/Models/User.php`: `listAll`, `listForUser`, `canViewUser`, `canAssignTo`, `validateAssignment`.
- Mecanismo en `Response.php` y `Router.php` (`$hasSentResponse`) para cancelar limpiamente la cadena de ejecución cuando un middleware o handler emite una respuesta de error.
- Suite de pruebas automatizadas `tests/users_test.php` con 23 casos positivos y negativos (100% PASS).

## [Sin versión - Autenticación y RBAC] - 2026-10-13 (Commit `7431530`)
### Añadido
- Módulo de autenticación y sesiones de la **Fase 1 (MVP-01)**.
- Modelos `User`, `Session` y `Audit` en `app/Models/`.
- Middleware de autenticación y RBAC `app/Core/Middleware/AuthMiddleware.php`.
- Controlador de autenticación `app/Controllers/AuthController.php` con endpoints `POST /api/auth/login`, `POST /api/auth/logout` y `GET /api/auth/me`.
- Soporte para cookies `HttpOnly; SameSite=Strict` y encabezados `Authorization: Bearer <token>`.
- Suite automatizada `tests/auth_test.php` con 19 pruebas positivas, negativas de acceso y de auditoría inmutable (100% PASS).

## [Sin versión - Estructura y Bootstrap Ferozo] - 2026-10-13 (Commit `9f648c6`)
### Añadido
- Culminación de la **Fase 0 (Preparación)** con la tarea `SETUP-05`.
- Estructura física del monolito modular para DonWeb Ferozo: `public_html/` y `app/` protegida con directiva Apache `Require all denied`.
- Script DDL completo de base de datos en `database/schema.sql` (10 tablas InnoDB con FKs e índices).
- Datos semilla iniciales en `database/seeds.sql` (5 columnas Kanban y 5 usuarios iniciales con hashes Bcrypt).
- Plantilla de variables de entorno `.env.example` y cargador liviano `app/Config/Env.php`.
- Conector seguro PDO MySQL con transacciones ACID en `app/Config/Database.php`.
- Enrutador REST `app/Core/Router.php`, respuestas JSON `app/Core/Response.php` y Front Controller `public_html/api/index.php`.
- Reglas de reescritura `.htaccess` en `public_html/` para API y SPA client-side routing.
- Shell inicial SPA con Design Tokens en `public_html/index.html` y script `public_html/assets/js/app.js`.
- Protección de almacenamiento de adjuntos en `storage/uploads/.htaccess`.
- Script de prueba de salud `tests/healthcheck_test.php` verificado exitosamente.

## [Sin versión - Modelo de Datos y API] - 2026-10-13 (Commit `8f548a1`)
### Añadido
- Especificación formal de casos de uso (CU-01 a CU-10), diagrama ER y script DDL SQL completo para MySQL 8 / MariaDB (InnoDB) en `docs/DATA_MODEL_AND_API.md` (SETUP-04).
- Definición de 10 tablas relacionales (`usuarios`, `sesiones`, `tableros_columnas`, `tareas`, `subtareas`, `tarea_dependencias`, `tarea_aprobaciones`, `tarea_comentarios`, `tarea_colaboradores`, `auditoria_eventos`) con claves foráneas e índices optimizados.
- Algoritmos para validación de aciclicidad (DFS en DAG) y desbloqueo atómico secuencial y paralelo (Regla AND) con transacciones ACID.
- Catálogo exhaustivo de endpoints de la API REST PHP 8.2+ con validación de autorización por alcance en servidor (asistente solo autoasigna, jefes gestionan equipo).

## [Sin versión - Diseño UI/UX] - 2026-10-13 (Commit `6df22cd`)
### Añadido
- Especificación formal de UI/UX, arquitectura de información y wireframes estructurales en `docs/UX_WIREFRAMES.md` (SETUP-03).
- Definición de Design Tokens para modo claro y oscuro (paleta cromática, elevaciones, bordes y tipografía).
- Wireframes de alta fidelidad para Vista Asistente (tareas personales vs. área), Vista Jefe (tablero general y bandeja de aprobaciones) y modales clave (confirmación explícita de cierre, devolución con observaciones, creación adaptativa).
- Mecanismo accesible de navegación por teclado y menú rápido como alternativa al Drag & Drop.

## [Sin versión - Arquitectura Ferozo] - 2026-10-13 (Commit `d0df541`)
### Modificado
- Aprobada decisión **D-011** y **ADR-0001**: Consolidación del stack tecnológico en PHP 8.2+ REST API, MySQL/MariaDB y SPA frontend sobre DonWeb Web Hosting tradicional (Ferozo).
- Sustituida propuesta P-004 (FastAPI + PostgreSQL descartados por incompatibilidad operativa con hosting Ferozo compartido sin demonios).
- Actualizados `docs/ARCHITECTURE.md`, `docs/DECISIONS.md`, `docs/PROJECT_CONTEXT.md` y `docs/ROADMAP.md` (SETUP-01 y SETUP-02 completados).

## [Sin versión - Configuración Inicial] - 2026-10-13 (Commit `652765c`)
### Añadido
- Contrato permanente del proyecto en `AGENTS.md`.
- Reglas operativas en `.agents/rules/` (`documentation.md`, `git.md`, `quality.md`, `security.md`).
- Definición de roles especializados en `.agents/agents/` (9 perfiles).
- Workflows operativos como skills en `.agents/skills/` (10 skills).
- Memoria técnica y funcional en `docs/` (`PROJECT_CONTEXT.md`, `REQUIREMENTS.md`, `DECISIONS.md`, `ROADMAP.md`, `ARCHITECTURE.md`, `PROGRESS.md`, `SESSION_HANDOFF.md`, `CHANGELOG.md`).
- Archivo `.gitignore` para salvaguarda de secretos y exclusión de artefactos locales.
- Prompts de operación en `prompts/`.
- Ninguna funcionalidad de frontend, backend o base de datos implementada aún.

## Regla
Anotar solo cambios reales, verificables, visibles o relevantes al repositorio; enlazar RF y commit cuando exista.

## [Sin versión - Primera observación visual del login] - 2026-10-14
### Modificado
- Corregido el ancho de los campos de correo y contraseña para ocupar la columna disponible sin recortes.
- Añadido un fondo sólido con patrón de puntos sutil, adaptado a los temas claro y oscuro.
- RF-01 / RN-01: login más legible y usable en escritorio y responsive.

## [Sin versión - Consolidación de Vista Área] - 2026-10-14
### Eliminado
- Retirado el módulo duplicado "Tablero del Equipo" de la navegación y del estado de la SPA.
- Eliminadas sus condiciones de carga, visibilidad por rol y parámetro `scope=team` del listado de usuarios.
- Renombrada la tabla visual del historial para no conservar nomenclatura del módulo retirado.

## [Sin versión - Restricción de edición para asistentes] - 2026-10-14
### Modificado
- Asistentes editan únicamente tareas asignadas en `PENDIENTE` y `EN_PROCESO`.
- La fecha límite queda inmutable para asistentes y solo Jefatura puede modificarla.
- En `EN_REVISION` y `FINALIZADA`, asistentes quedan en modo consulta: sin edición, checklist, comentarios, evidencias ni movimiento.

## [Sin versión - Diseño de Gestión de Usuarios] - 2026-10-14
### Añadido
- Diseño funcional y técnico del módulo dedicado de Gestión de Usuarios, pendiente de implementación.
- Registro de jerarquía administrativa, permisos por rol, auto-desactivación y gestión manual de contraseñas.
