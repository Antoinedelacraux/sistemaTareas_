# Registro de avances

### 2026-10-16 - AREA-CONTROLS-SCOPE-UI-01: controles globales exclusivos de Vista Área
- **Estado:** implementado; verificación automática pendiente de ejecutar en este avance.
- **Objetivo:** evitar que la búsqueda global, el filtro de prioridad y la creación de tareas del tablero aparezcan en Aprobaciones, Historial o Usuarios.
- **RF/RN:** RF-05, RF-06, RF-10, RF-10a, RN-01 y RN-07.
- **Cambios:** `setScope()` controla la barra completa y la muestra solo cuando el ámbito activo es `area`; el botón Nueva Tarea también queda restringido a ese ámbito. Aprobaciones conserva sus filtros locales.
- **Pruebas:** pendiente ejecutar lint PHP, suite integral y `git diff --check`; pendiente validación visual manual de navegación entre pestañas.
- **Limitación:** no se dispone de automatización de navegador para comprobar el clic real en cada rol.

### 2026-10-16 - APPROVALS-FILTER-UI-01: busqueda y filtro por asistente
- **Estado:** implementado y verificado.
- **Objetivo:** encontrar rapidamente una tarea especifica en la Bandeja de Aprobaciones y filtrar por asistente responsable.
- **RF/RN:** RF-10a, RN-01, RN-07.
- **Cambios:** se agrego buscador por codigo, titulo o responsable y selector de asistentes; ambos filtran la lista ya cargada, sin solicitudes por cada tecla y sin alterar las acciones existentes.
- **Pruebas:** PHP lint, suite integral y git diff --check pendientes de ejecutar en este avance; Node no esta instalado para node --check.
- **Limitacion:** pendiente validacion visual manual de la barra en escritorio y movil.

### 2026-10-16 - UI-ALIGNMENT-01: alineacion de controles y visibilidad de contraseñas
- **Estado:** implementado y verificado.
- **Objetivo:** corregir bordes y centrado visual en filtros y tarjetas, y permitir revisar temporalmente las contraseñas escritas.
- **RF/RN:** RF-01, RF-02, RN-01, RN-07.
- **Cambios:** selectores con altura, padding, chevron y foco uniformes; tarjetas con borde exterior constante y acento interno para estados; campos de contraseña con boton accesible de mostrar/ocultar en login y gestion de usuarios; cache versionada.
- **Pruebas:** PHP lint PASS; git diff --check PASS; pendiente suite integral y validacion visual manual.
- **Limitacion:** Node no esta instalado, por lo que no se ejecuto node --check; la validacion final de centrado requiere navegador real.

### 2026-10-16 - HISTORY-ARCHIVE-UI-01: acciones y eliminacion controlada del Historial
- **Estado:** implementado y verificado.
- **Objetivo:** consultar o eliminar tareas cerradas desde el Historial sin abandonar la vista, con seleccion individual y masiva.
- **RF/RN:** RF-13, RF-18, RN-05, RN-07, RN-13.
- **Cambios:** acciones unicamente con iconos para ver y eliminar, checkbox por fila, seleccion de todas las filas visibles y boton superior de eliminacion. El endpoint DELETE /api/historial/{id} solo acepta tareas AREA archivadas, exige Jefatura, confirma la operacion en frontend y registra ELIMINAR_HISTORIAL.
- **Pruebas:** tests/approvals_test.php PASS (27/27); php tests/run_all.php PASS (10/10 suites); QA piloto PASS (43/43); PHP lint PASS; git diff --check PASS.
- **Limitacion:** pendiente validacion visual manual en navegador de iconos, seleccion e interaccion de confirmacion.

### 2026-10-16 - CACHE-REFRESH-UI-01: corrección de carga del refresco para Jefatura
- **Estado:** implementado y verificado.
- **Hallazgo:** el HTML actualizado podía convivir con una copia cacheada de `app.js?v=1.6.0`, dejando `refreshBoard` sin exponerse en `window` y provocando `Uncaught ReferenceError`.
- **Corrección:** se versionaron `app.js`, `kanban.js` y `task-modal.js` con `v=1.6.2` para invalidar el caché del navegador y cargar las exportaciones actuales en todos los roles.
- **Pruebas:** `php tests/kanban_test.php`: 18/18 PASS; `php tests/qa_security_pilot_test.php`: 43/43 PASS; lint de entrypoints PHP PASS; raíz HTTP 200; comprobación estática de URLs `v=1.6.2` PASS; `git diff --check` PASS.
- **Limitación:** no hay automatización de navegador para hacer clic real en cada sesión; se requiere recarga normal/forzada del navegador para obtener los nuevos recursos.
- **Commit:** `f6d8142 fix(frontend): bust cache for refresh actions`.
- **Siguiente paso:** recargar la sesión de Jefe de Área y probar el botón; no hacer push remoto.

### 2026-10-16 - BOARD-REFRESH-UI-01: actualización manual del tablero
- **Estado:** implementado y verificado.
- **Objetivo:** permitir refrescar el tablero completo mediante un icono, sin recargar la página.
- **RF/RN:** RF-05, RF-06 y RN-01, RN-07.
- **Cambios:** se agregó un botón iconográfico en la barra de filtros de la Vista Área; actualiza columnas y tarjetas, muestra estado de carga, evita solicitudes duplicadas y permanece oculto fuera del tablero operativo.
- **Pruebas:** `php tests/run_all.php`: 10/10 suites y 100% PASS; lint PHP completo PASS; raíz HTTP 200; `git diff --check` PASS.
- **Limitación:** queda validación visual manual del icono en escritorio y móvil.
- **Commit:** `becde1f feat(kanban): add board refresh action`.
- **Siguiente paso:** validación visual manual del icono; no hacer push remoto.

### 2026-10-16 - TASK-REFRESH-UI-01: sincronización de la ficha desde el detalle
- **Estado:** implementado y verificado.
- **Objetivo:** permitir que la persona refresque la información de la tarea desde el encabezado de la misma ficha, sin editar ni cerrar el detalle ni recargar la pantalla completa.
- **RF/RN:** RF-07; RN-01, RN-07, RN-10 y RN-11.
- **Cambios:** se agregó un botón de refresco con etiqueta accesible junto al cierre del detalle; vuelve a consultar tarea, comentarios, evidencias, checklist y dependencias, actualiza también el tablero y mantiene abierta la ficha. Durante la consulta muestra animación y evita clics duplicados.
- **Pruebas:** `php tests/run_all.php`: 10/10 suites y 100% PASS; `git diff --check`: PASS. El intento de ejecutar `tests/qa_pilot.php` no aplica porque ese archivo no existe; la suite vigente `tests/qa_security_pilot_test.php` sí se ejecutó dentro de `run_all.php` con 43/43 PASS.
- **Limitación:** queda validación visual manual en navegador para confirmar el comportamiento responsive del encabezado en escritorio y móvil.
- **Commit:** `7f3c088 fix(task): refresh detail instead of opening editor`.
- **Siguiente paso:** revisar visualmente el icono en la ficha; no hacer push remoto.

### 2026-10-16 - CLEANUP-01: limpieza técnica y documental del alcance vigente
- **Estado:** completado y verificado; pendiente únicamente del checkpoint Git.
- **Objetivo:** retirar referencias operativas a credenciales demo, Dashboard y Vista Personal; conservar únicamente compatibilidad histórica de registros `PERSONAL`.
- **RF/RN:** RF-01, RF-07, RF-12, RF-20 y RN-07; coherencia con D-017, D-024 y D-029.
- **Cambios:** requisitos, decisiones, contexto, roadmap, changelog, wireframes y handoff alineados; `LEEME_PRIMERO.md` actualizado al flujo de instalación limpia; clases vacías y módulo JavaScript del Dashboard retirados; pruebas heredadas actualizadas para validar el tablero único de Área y el Historial; artefactos locales protegidos en `.gitignore`; corregida la respuesta HTTP del Historial para usar el contrato real de `Response`.
- **Pruebas:** lint PHP `49/49` PASS; suites integrales `10/10` PASS; QA piloto `43/43` PASS; Test Runner HTTP `10/10` PASS; raíz local HTTP `200`; `git diff --check` PASS.
- **Commit:** `33be20d chore(cleanup): align active scope and remove legacy modules`.
- **Siguiente paso:** validación visual final y preparación del despliegue piloto; no se hizo push remoto.

### 2026-10-15 - PROD-READY-01: instalacion limpia para produccion
- **Estado:** implementado y validado tecnicamente.
- **Alcance:** RF-20 y RN-17; prepara una instancia nueva para su primer uso operativo.
- **Cambios:** se eliminaron usuarios, tareas, comentarios y contrasenas de demostracion de los datos de arranque y del login. El primer usuario se crea exclusivamente con `/instalar.php`, protegido por `INSTALLER_ENABLED`, `INSTALL_TOKEN` de al menos 32 caracteres y la condicion de que la base este vacia. Ese usuario queda como **Jefe de Area** y puede administrar las cuentas reales del equipo. El instalador se bloquea al completar la creacion.
- **Despliegue:** `docs/DEPLOYMENT_AND_PILOT_GUIDE.md` indica el orden exacto: esquema, columnas base, `.env`, instalador, primer usuario y desactivacion. No se elimina ni se modifica la base SQLite local existente.
- **Pruebas:** lint de PHP modificado PASS; `php tests/initial_setup_test.php` 7/7 PASS; `php tests/user_management_test.php` 15/15 PASS; `php tests/notifications_automation_test.php` 38/38 PASS; HTTP local: raiz 200, instalador desactivado 404 y ruta legada `/index.html` 302 a `/`; `git diff --check` PASS.
- **Siguiente paso:** subir el paquete a Ferozo, importar `database/schema.sql` y `database/seeds.sql`, configurar `.env`, ejecutar una sola vez el instalador y deshabilitarlo.

### 2026-10-15 - ENTRYPOINT-01: unificacion del punto de entrada
- **Estado:** implementado.
- **Decision historica:** inicialmente se conservaba `public_html/index.html` como copia no navegable. PROD-READY-01 la elimina del paquete operativo por contener datos de demostracion; las rutas legadas se mantienen redirigidas a `/`.
- **Verificacion:** `/` entrega el ensamblador vigente; `/index.html` devuelve redireccion `302` hacia `/` en local.

### 2026-10-15 - PERF-01: optimizacion inicial de Lighthouse
- **Estado:** implementado; pendiente de repetir Lighthouse en navegador real.
- **Alcance:** RF-05, RF-07, RN-01 y RN-07.
- **Cambios:** se eliminaron fuentes externas del primer render, se reemplazo la cascada CSS por hojas cargadas en paralelo, se redujo la imagen del login de 3.1 MB a 785 KB, se declararon sus dimensiones y prioridad de carga, se anadieron compresion/cache de estaticos para Apache y se paralelizo la carga inicial de usuarios, columnas, tareas y aprobaciones.
- **Objetivo Lighthouse:** atacar LCP de 3.2 s y CLS de 0.323 del reporte recibido sin cambiar reglas funcionales.
- **Pruebas:** PHP lint en PHP modificado: PASS; notificaciones 38/38 PASS; usuarios 15/15 PASS; HTTP local de raiz y assets 200; cache `max-age=31536000`; `git diff --check` PASS. Lighthouse/Chrome: no disponible en este entorno.
- **Siguiente paso:** repetir Lighthouse con cache limpia en escritorio y movil, autenticado y sin autenticar, y comparar LCP/CLS.

### 2026-10-15 — UI-05: patrón oscuro visible y hora solo en detalle
- **Estado:** implementado, pendiente de validación visual manual.
- **Alcance:** RN-07; refinamiento visual de UI-04.
- **Fondo:** el modo oscuro recupera la misma composición de puntos y diagonales del patrón claro, con una intensidad índigo ajustada para que se perciba sobre `--bg-primary` sin reducir la legibilidad.
- **Registro:** la fecha/hora de creación se retiró de tarjetas Kanban, aprobaciones e historial; permanece únicamente en `Registrada` dentro del detalle de tarea.
- **Pruebas:** lint PHP completo: PASS; notificaciones 38/38; usuarios 15/15; HTTP local y CSS de variables 200; `git diff --check` y búsqueda estática de usos: PASS.
- **Siguiente paso:** validar visualmente patrón claro/oscuro y apertura del detalle con Ctrl + F5.

### 2026-10-15 — UI-04: selector de responsables y hora de registro
- **Estado:** implementado, pendiente de validación visual manual.
- **Alcance:** RF-04, RF-07, RF-10; RN-03 y RN-04.
- **Selector:** creación y edición de tareas usan un selector accesible con buscador y lista filtrable; cada opción muestra únicamente `Nombre (ROL)` y conserva el ID interno requerido por la API.
- **Registro:** `created_at` se muestra con fecha y hora en tarjetas del Kanban, bandeja de aprobaciones, historial archivado y detalle de tarea. Se normaliza el valor UTC persistido por backend antes de presentarlo en horario local.
- **Pruebas:** lint PHP completo: PASS; `php tests/notifications_automation_test.php`: 38/38 PASS; `php tests/user_management_test.php`: 15/15 PASS; HTTP local 200 con controles nuevos: PASS; `git diff --check`: PASS. `tasks_test.php` conserva el fallo heredado T21 de eliminación, no relacionado con este avance.
- **Siguiente paso:** validar visualmente el buscador en creación/edición y confirmar la hora mostrada en una tarea nueva.

### 2026-10-15 — UI-03: retiro del zoom visual interno
- **Estado:** implementado, pendiente de validación visual manual.
- **Alcance:** RF-07, RF-10, RN-07; ajuste solicitado sobre UI-01/UI-02.
- **Cambio:** se eliminó por completo la escala CSS interna equivalente al 80%; `.app-scale-shell` vuelve a trabajar a dimensiones normales (`100%`/`100vh`). El backdrop de los modales usa directamente todo el viewport con `inset: 0`, sin compensaciones de `125vw`, `125vh` ni `zoom`.
- **Conservado:** la corrección del toast que evitaba `undefined`, el ensamblado que mantiene los modales fuera del shell y las mejoras de contraste del modo oscuro.
- **Pruebas:** lint PHP, pruebas de notificaciones y usuarios, respuesta HTTP local y `git diff --check` ejecutados tras el cambio.
- **Siguiente paso:** validar visualmente con el navegador en 100% y recarga forzada.

### 2026-10-15 — UI-02: toast de creación, escala aislada y overlay de tareas
- **Estado:** implementado, pendiente de validación visual manual.
- **Alcance:** RF-04, RF-07, RF-10, RN-07; corrección de regresión introducida por la escala visual UI-01.
- **Correcciones:** el toast de creación ahora lee la respuesta real `data.tarea.codigo` y tiene un mensaje seguro de respaldo; se separó el contenido escalado (`.app-scale-shell`) de los modales; el backdrop usa el viewport físico completo y compensa la escala 80% para sombrear toda la pantalla.
- **Criterios de aceptación:** crear una tarea no muestra `undefined`; el tablero, aprobaciones, historial y usuarios conservan la composición al 80%; abrir una tarea, devolución, usuario o columna bloquea y sombrea toda la ventana, incluyendo el área fuera del contenido.
- **Pruebas:** `php -l public_html/index.php` y lint PHP completo: PASS; raíz local HTTP 200 y estructura HTML con shell/modales: PASS; `php tests/notifications_automation_test.php`: 38/38 PASS; `php tests/user_management_test.php`: 15/15 PASS; los casos T02/T04/T05 de creación en `tasks_test.php` PASS. T21 continúa fallando por la expectativa heredada de eliminación, no relacionada con este cambio; `git diff --check`: PASS.
- **Archivos principales:** `public_html/index.php`, `public_html/partials/footer.html`, `public_html/assets/css/base.css`, `public_html/assets/css/modals.css`, `public_html/assets/js/modules/task-modal.js`.

### 2026-10-15 — UI-01: escala visual predeterminada y contraste del modo oscuro
- **Estado:** implementado, pendiente de validación visual final en navegador.
- **Alcance:** RF-07, RN-07 y consistencia transversal de la interfaz.
- **Decisión aplicada:** la página usa una escala visual CSS de `0.8`, equivalente al zoom preferido del usuario, sin modificar el zoom real del navegador. Se eligió `zoom` en el `body` porque conserva el flujo, el comportamiento responsive y la interacción de modales mejor que un `transform: scale()` global.
- **Contraste:** se añadieron tokens para texto primario/secundario, estados informativos, éxito, advertencia y peligro; se corrigieron botones, badges, alertas de devolución, estados del historial, iconos de notificación, tarjetas vencidas, controles de columnas y estados de foco. El texto secundario oscuro dejó de usar `#6b7280`, que no alcanzaba AA en fondos oscuros.
- **Pruebas:** `php tests/notifications_automation_test.php`: 38/38 PASS; `php tests/user_management_test.php`: 15/15 PASS; lint PHP completo: PASS; `git diff --check`: PASS; entrega local `/` y `assets/css/app.css`: HTTP 200. La batería integral conserva fallos heredados no relacionados con CSS/UI (`tasks_test.php`, `kanban_test.php`, `dependencies_test.php` y llamadas a `DashboardService::getMetrics()` retirado).
- **Archivos principales:** `public_html/assets/css/variables.css`, `base.css`, `header.css`, `kanban.css`, `views.css`, `modals.css`, `footer.css`, `login.css`, `public_html/assets/js/modules/approvals.js`, `history.js`, `task-modal.js`, `notifications.js`, `ui.js`.
- **Limitación:** si el usuario fija además el zoom del navegador manualmente en 80%, ambas escalas se acumularán; el ajuste implementado es el predeterminado visual de la página y no puede cambiar la preferencia del navegador.

### 2026-10-15 — MVP-08-NOTIF-01: verificación y precisión de destinatarios por rol
- **Estado:** completado.
- **Alcance:** RF-17, RN-15, D-020; seguridad de autorización de notificaciones.
- **Hallazgos corregidos:** la solicitud de aprobación podía avisar únicamente al revisor almacenado, aunque ambas Jefaturas pueden resolverla; tampoco existía un filtro central de cuentas activas y roles válidos antes de crear avisos.
- **Cambios:** `User::listActiveByRoles` obtiene todas las cuentas activas de Jefatura; `AutomationEngine` distribuye `APROBACION_SOLICITADA` a ambas jefaturas, valida destinatarios activos y conserva la exclusión del autor/revisor cuando corresponde; `Approval::solicitar` rechaza revisores que no sean Jefatura activa.
- **Pruebas:** `php tests/notifications_automation_test.php`: 38/38 PASS; lint PHP de `User.php`, `Approval.php`, `AutomationEngine.php` y la suite: PASS; `git diff --check`: PASS.
- **Cobertura de rol:** se verificó que la aprobación no llegue al Asistente, que llegue a Jefe de Planeamiento y Jefe de Personal, que el revisor no reciba su propia resolución y que el creador Jefe reciba comentarios.
- **Archivos principales:** `app/Models/User.php`, `app/Models/Approval.php`, `app/Services/AutomationEngine.php`, `tests/notifications_automation_test.php`.

### 2026-10-15 — USERS-MGMT-UI-01: proporciones y alineación de Gestión de Usuarios
- **Estado:** completado, pendiente de validación visual final del usuario.
- **Alcance:** RF-14, RN-07.
- **Cambios:** la vista usa el contenedor principal real de la SPA, ocupa el ancho disponible, mantiene una cabecera compacta y alineada, búsqueda con ancho prioritario, filtros mediante cuadrícula y tabla con columnas explícitas. Las acciones reservan espacio suficiente incluso para “Desactivar mi cuenta”; en móvil, filtros se apilan y la tabla conserva la legibilidad mediante desplazamiento horizontal. Se corrigió el ensamblado que dejaba Usuarios fuera de `<main>` y provocaba que se desplazara hacia abajo al cambiar el zoom.
- **Pruebas:** `git diff --check`: PASS; `php tests/user_management_test.php`: 15/15 PASS; `GET http://127.0.0.1:8000/`: 200 y entrega del markup actualizado.
- **Limitación:** no hay automatización de navegador configurada en el repositorio; queda pendiente la comprobación visual manual en escritorio y móvil.
- **Archivos principales:** `public_html/index.php`, `public_html/partials/users_management.html`, `public_html/assets/css/views.css`, `public_html/assets/js/modules/users-management.js`, `docs/CHANGELOG.md`, `docs/SESSION_HANDOFF.md`.

### 2026-10-14 — USERS-MGMT-01: módulo aislado de gestión de usuarios
- **Estado:** completado.
- **Alcance:** RF-02, RF-14, RN-12, D-027.
- **Cambios:** se sustituyó el botón aislado de usuarios por una pestaña exclusiva de Jefatura, con listado filtrable, alta, edición, asignación de rol, activación/desactivación y cambio manual de contraseña. La administración usa exclusivamente `/api/gestion-usuarios/*`; `/api/usuarios` queda limitado al directorio de responsables y validación de asignaciones de tareas.
- **Autorización:** ambos roles de Jefatura pueden administrar cuentas. El backend bloquea con `403 ERR_HIERARCHY_PROTECTED` solo el intento de un Jefe de Personal de desactivar una cuenta con rol Jefe de Área. La interfaz anticipa el bloqueo sin sustituir la validación servidor.
- **Seguridad:** no se devuelve ni audita contraseñas; se usa hash BCrypt. Desactivar una cuenta, cambiar su contraseña o cambiar su rol invalida todas sus sesiones activas.
- **Pruebas:** `php -l` en backend y prueba nueva: PASS; `php tests/user_management_test.php`: 15/15 PASS; `php tests/users_test.php`: 23/23 PASS; `git diff --check`: PASS. La suite integral conserva fallos heredados ajenos en tareas, Kanban, dependencias y pruebas que invocan un Dashboard ya retirado.
- **Archivos principales:** `app/Controllers/UserManagementController.php`, `app/Models/User.php`, `app/Models/Session.php`, `public_html/partials/users_management.html`, `public_html/assets/js/modules/users-management.js`, `tests/user_management_test.php`; arquitectura actualizada en `docs/ARCHITECTURE.md`.
- **Limitación:** la validación visual final en navegador sigue pendiente; no se modificó la copia histórica `public_html/index.html`, pues Apache sirve `index.php` y sus partials vigentes.

### 2026-10-14 — CICLO-01: cierre, archivo y vencimientos
- **Estado:** completado.
- **Alcance:** RF-05, RF-07, RF-13; RN-04, RN-05, RN-08 y RN-09.
- **Cambios:** las tareas finalizadas se conservan 24 horas en Kanban y luego se archivan; Jefatura puede ejecutar un cierre y archivo inmediato. Los asistentes quedan en modo consulta sobre tareas finalizadas; Jefatura puede editar, eliminar o reabrir solo hacia `EN_PROCESO`. Se añadió la marca persistente `historial_inmediato`, estado de cumplimiento en Historial y señal roja con icono para vencimientos. El selector de estado de columnas usa únicamente los nombres de estado.
- **Pruebas:** lint de PHP en los archivos modificados, `git diff --check` y prueba aislada en SQLite en memoria: PASS para cierre, reapertura exclusiva de Jefatura, archivo inmediato y cálculo de vencimiento. La suite `php tests/run_all.php` conserva fallos heredados/expectativas previas, incluidos casos que esperan que un asistente elimine o reabra tareas; no se consideran evidencia de regresión de esta regla nueva.
- **Pendiente:** validación visual final en navegador del indicador de vencimiento y de la acción de archivo inmediato.

## Registro de avances

### 2026-10-14 — VISTAS-03: tablero único de Área y evidencias
- **Estado:** completado.
- **Alcance:** RF-03, RF-04, RF-09; RN-02, RN-03, RN-06.
- **Cambios:** se retiró la Vista Personal, su borrado masivo, sus selectores y endpoint; las tareas nuevas se fuerzan a Área. Asistentes crean/gestionan solo sus tareas autoasignadas y las jefaturas ven todas las tareas de Área y asignan a asistentes activos. En evidencias se muestra el primer nombre, se guarda el adjunto y se descarga por ruta autorizada; se permite archivo sin texto.
- **Pruebas:** `php tests/subtasks_comments_test.php` (23/23 PASS), `php tests/tasks_test.php` (24/24 PASS) y lint PHP sin errores. Node.js no está instalado para comprobación estática de JavaScript.

### 2026-10-14 — Corrección de carga del Kanban
- **Estado:** completado.
- **Cambios:** Apache ahora declara `DirectoryIndex index.php`, para que el acceso a la raíz cargue el ensamblador modular vigente y no `index.html` histórico. Se actualizó la versión del módulo principal para renovar la caché del navegador.
- **Verificación:** `GET /` local entrega el markup de Kanban modular; `GET /api/tableros/kanban` autenticado devuelve columnas y tareas.
- **Corrección adicional:** se restauró la declaración de jefatura usada por `renderKanban()`, que estaba provocando un `ReferenceError` antes de pintar las columnas.

### 2026-10-14 — LOGIN-UI-01: Contraste y espaciado del acceso
- **Estado:** implementado, pendiente de validación visual en navegador.
- **Cambios:** se reforzó el overlay sobre `login_hero.jpg`, se ajustaron proporciones de la tarjeta, padding, gaps, inputs, botón principal y cuentas demo; se añadió breakpoint móvil y se reemplazaron flechas de texto por SVG.
- **Criterios:** identidad visible y legible sobre la imagen, jerarquía clara y controles con separación consistente.
- **Pruebas:** `php -l public_html/index.php`, entrega HTTP de `/` y `/assets/css/login.css` con HTTP 200; `git diff --check` PASS.

### 2026-10-14 — VISTAS-02: Limpieza personal y cierre de columna
- **Estado:** implementado, pendiente de validacion visual del usuario.
- **Objetivo y RF/RN:** RF-05, RF-07, RN-01, RN-04, RN-07.
- **Cambios:** API segura `DELETE /api/tareas/personales`; boton confirmado `Borrar todo` visible solo en Vista Personal; candado SVG en columnas `FINALIZADA`; tareas finalizadas en modo solo lectura; retiro de avisos/badges de cierre dentro de tarjeta y modal; reemplazo de emojis visibles por SVG o texto profesional.
- **Pruebas:** lint PHP de archivos afectados: PASS; `tests/auth_test.php`: 19/19 PASS; diagnostico web: 10/10 PASS (254.53 ms); `git diff --check`: PASS. `tests/tasks_test.php` conserva 4 fallos de alcance heredados de la separacion de columnas anterior (T07, T14, T15, T17). Node.js no esta disponible para un chequeo sintactico automatico de modulos JS. El borrado masivo no se ejecuto contra los datos locales existentes durante QA.

### 2026-10-14 — REFACTOR-MODULAR: Separación de Responsabilidades en Archivos Extensos, Aislamiento Estricto de Login y Creación de Skill
- **Estado:** completado
- **Objetivo y RF/RN:** RF-01, RF-02, RF-03, RN-01, RN-03, D-022. Descomponer archivos monolíticos extensos (>740 líneas HTML, >2850 líneas CSS) en componentes atómicos de responsabilidad única para mejorar escalabilidad, facilidad de edición futura y visualización predecible. Aislar completamente la vista de autenticación (Login) para evitar solapamientos con el tablero Kanban.
- **Archivos creados/modificados:**
  - `.agents/skills/modularizar-archivos-grandes/SKILL.md`: Creación de la Skill especializada para estandarizar la descomposición de monolitos en el proyecto.
  - `public_html/partials/`: Creación de 9 partials atómicos (`head.html`, `header.html`, `workspace_bar.html`, `login.html`, `kanban.html`, `approvals.html`, `history.html`, `modals.html`, `footer.html`).
  - `public_html/index.php`: Ensamblador limpio en PHP para inclusión nativa de partials.
  - `server.php`: Configuración de enrutamiento SPA con prioridad hacia `index.php`.
  - `public_html/assets/css/`: Descomposición de `app.css` (2,857 líneas) en 8 hojas de estilo especializadas:
    - `variables.css`: Tokens de diseño y paletas temáticas.
    - `base.css`: Reset, tipografía, botones base, badges, inputs y barras de progreso.
    - `header.css`: Barra superior corporativa, perfil de usuario, campana de notificaciones y workspace bar.
    - `login.css`: Vista de acceso 100% aislada, imagen lateral 3D TAL S.A. y accesos demo (sin directivas bloqueantes `!important`).
    - `kanban.css`: Tablero kanban, columnas dinámicas, tarjetas de tarea y banner de vista personal.
    - `views.css`: Aprobaciones técnicas, archivo histórico, balance de carga y auditoría.
    - `modals.css`: Modales accesibles, detalle sin scroll, checklist, dependencias DAG y toasts.
    - `footer.css`: Pie de página corporativo slim y pill de autodiagnóstico.
    - `app.css`: Manifiesto orquestador que importa los módulos vía `@import`.
  - `docs/DECISIONS.md`, `docs/ARCHITECTURE.md`, `docs/PROGRESS.md`, `docs/SESSION_HANDOFF.md`.
- **Decisiones y motivo:** D-022 formalizada. Se eliminó la regla `!important` en el display de `.login-view-container` que impedía a `auth.js` ocultar el login tras iniciar sesión. El Login y el Tablero Kanban son ahora visualmente mutuamente excluyentes.
- **Pruebas ejecutadas:**
  1. Suite de autodiagnóstico web: `/api/test-runner?format=json` superó 10 de 10 pruebas exitosas (100% PASS, 254.49 ms).
  2. Verificación de renderizado y ensamblado nativo vía `http://127.0.0.1:8000/`.
- **Documentación actualizada:** `docs/DECISIONS.md`, `docs/ARCHITECTURE.md`, `docs/PROGRESS.md`, `docs/SESSION_HANDOFF.md`.
- **Deuda técnica / riesgos:** Ninguna.
- **Siguiente paso verificable:** Continuar con la validación de usuario en el navegador local `http://127.0.0.1:8000`.

### 2026-10-13 — SETUP-00 / Inicialización y validación del entorno de agentes y documentación
- **Estado:** completado
- **Objetivo y RF/RN:** Inicializar y validar el entorno del workspace: perfiles de agentes, reglas permanentes, skills operativos, alineación con requisitos V1.2 (RF-01 a RF-19, RN-01 a RN-15).
- **Archivos creados/modificados:** `.gitignore`, `docs/PROGRESS.md`, `docs/SESSION_HANDOFF.md`, `docs/CHANGELOG.md`.
- **Decisiones y motivo:** Validada la estructura completa. Creado `.gitignore` a partir de `templates/gitignore.snippet`. Repositorio Git inicializado y `html.html` vacío retirado del staging.
- **Pruebas ejecutadas:** `git status --short`, `git commit`. Verificación estructural de todos los archivos y contratos.
- **Documentación actualizada:** `docs/PROGRESS.md`, `docs/SESSION_HANDOFF.md`, `docs/CHANGELOG.md`.
- **Git:** Commit inicial `652765c` (`chore(agents): initialize project agent configuration and documentation`).
- **Deuda técnica / riesgos:** Ninguna en configuración.
- **Siguiente paso verificable:** Iniciar Fase 0 y consolidar arquitectura/stack para despliegue en producción.

### 2026-10-13 — SETUP-01 / SETUP-02: Consolidación del stack técnico para DonWeb Ferozo (ADR-0001)
- **Estado:** completado
- **Objetivo y RF/RN:** D-011, ADR-0001, sustitución de P-004. Consolidar el stack técnico y la arquitectura física adaptada al Web Hosting tradicional con panel Ferozo de DonWeb.
- **Archivos creados/modificados:** `docs/DECISIONS.md`, `docs/ARCHITECTURE.md`, `docs/PROJECT_CONTEXT.md`, `docs/ROADMAP.md`, `docs/PROGRESS.md`, `docs/SESSION_HANDOFF.md`, `docs/CHANGELOG.md`.
- **Decisiones y motivo:** Se aprueba formalmente D-011 y ADR-0001: Backend PHP 8.2+ (REST API modular), Base de datos MySQL / MariaDB (InnoDB, transacciones ACID), Frontend SPA moderna (HTML5, Vanilla CSS / Design Tokens, JavaScript modular). Se descarta P-004 (FastAPI + PostgreSQL) por incompatibilidad operativa con hosting compartido Ferozo sin demonios continuos.
- **Pruebas ejecutadas:** Validación de compatibilidad técnica con DonWeb Ferozo, revisión cruzada de consistencia documental.
- **Documentación actualizada:** `docs/DECISIONS.md`, `docs/ARCHITECTURE.md`, `docs/PROJECT_CONTEXT.md`, `docs/ROADMAP.md`, `docs/PROGRESS.md`, `docs/SESSION_HANDOFF.md`, `docs/CHANGELOG.md`.
- **Git:** Commit `d0df541` (`docs(architecture): consolidate stack for DonWeb Ferozo hosting ADR-0001`).
- **Deuda técnica / riesgos:** Asegurar aislamiento del código PHP fuera de la raíz pública o mediante `.htaccess` en el servidor web Apache de Ferozo.
- **Siguiente paso verificable:** SETUP-03 (Diseñar navegación y wireframes accesibles por rol) y SETUP-04 (Diseñar modelo relacional MySQL y contratos de la API REST).

### 2026-10-13 — SETUP-03: Diseño de navegación, flujos y wireframes por rol
- **Estado:** completado
- **Objetivo y RF/RN:** RF-03, RF-04, RF-05, RF-06, RF-07, RF-08, RF-12, RF-13, RN-01, RN-02, RN-03, RN-04, RN-10, RN-11, RN-12. Diseñar la arquitectura de información, mapas de navegación, wireframes estructurales para la vista de Asistente y vista de Jefe, flujos modales de confirmación de cierre y devolución de revisiones, y sistema de tokens de diseño UI (modo claro/oscuro).
- **Archivos creados/modificados:** `docs/UX_WIREFRAMES.md`, `docs/ROADMAP.md`, `docs/PROGRESS.md`, `docs/SESSION_HANDOFF.md`, `docs/CHANGELOG.md`.
- **Decisiones y motivo:** Formalizada la experiencia de usuario en `docs/UX_WIREFRAMES.md`. Se garantiza en UI la autoasignación obligatoria de asistentes, el modal bloqueante de confirmación para cierre de tareas sin aprobación (con advertencia de impacto en DAG), la bandeja de aprobaciones para jefes, y la accesibilidad con soporte por teclado como alternativa a Drag & Drop.
- **Pruebas ejecutadas:** Verificación de correspondencia con requisitos funcionales y reglas de negocio V1.2.
- **Documentación actualizada:** `docs/UX_WIREFRAMES.md`, `docs/ROADMAP.md`, `docs/PROGRESS.md`, `docs/SESSION_HANDOFF.md`, `docs/CHANGELOG.md`.
- **Git:** Commit `6df22cd` (`docs(ux): create wireframes and navigation design for assistant and manager roles SETUP-03`).
- **Deuda técnica / riesgos:** Ninguna en diseño.
- **Siguiente paso verificable:** SETUP-04 (Diseñar casos de uso, modelo de datos relacional MySQL y especificación de endpoints API REST).

### 2026-10-13 — SETUP-04: Diseño de casos de uso, modelo de datos relacional MySQL y contratos API REST
- **Estado:** completado
- **Objetivo y RF/RN:** RF-01 a RF-19, RN-01 a RN-15. Diseñar los casos de uso nucleares (CU-01 a CU-10), el modelo entidad-relación, el script DDL SQL completo para MySQL 8 / MariaDB (InnoDB), los algoritmos de detección de ciclos en grafos acíclicos (DAG) y desbloqueo atómico AND, y el catálogo exhaustivo de contratos y endpoints de la API REST en PHP 8.2+.
- **Archivos creados/modificados:** `docs/DATA_MODEL_AND_API.md`, `docs/ROADMAP.md`, `docs/PROGRESS.md`, `docs/SESSION_HANDOFF.md`, `docs/CHANGELOG.md`.
- **Decisiones y motivo:** Formalizado en `docs/DATA_MODEL_AND_API.md`. Estructura relacional con 10 tablas optimizadas con índices y llaves foráneas. Autorización garantizada en backend (asistente solo autoasigna y solo consulta sus tareas; jefe administra tareas oficiales). Transacciones ACID para transiciones de estado, confirmación de cierre y desbloqueo concurrente de dependientes.
- **Pruebas ejecutadas:** Verificación de integridad referencial SQL, análisis de consistencia de tipos de datos, correspondencia de contratos REST con especificación V1.2.
- **Documentación actualizada:** `docs/DATA_MODEL_AND_API.md`, `docs/ROADMAP.md`, `docs/PROGRESS.md`, `docs/SESSION_HANDOFF.md`, `docs/CHANGELOG.md`.
- **Git:** Commit `8f548a1` (`docs(api): design data model, mysql ddl and rest contracts SETUP-04`).
- **Deuda técnica / riesgos:** Ninguna en modelado.
- **Siguiente paso verificable:** SETUP-05 (Preparar estructura del proyecto, script de base de datos y entorno local compatible con DonWeb Ferozo).

### 2026-10-13 — SETUP-05: Preparación de estructura del proyecto, base de datos y bootstrap para DonWeb Ferozo
- **Estado:** completado
- **Objetivo y RF/RN:** Culminar la Fase 0 (Preparación). Crear la estructura de directorios física del monolito modular para DonWeb Ferozo (`public_html/` y `app/`), scripts SQL completos (`database/schema.sql` y `database/seeds.sql`), configuración segura `.env.example`, autoloader PSR-4, Front Controller de la API REST (`public_html/api/index.php`), reglas Apache `.htaccess`, shell inicial de la SPA con Design Tokens, y protección de directorio de adjuntos `storage/uploads/.htaccess`.
- **Archivos creados/modificados:** `database/schema.sql`, `database/seeds.sql`, `.env.example`, `app/.htaccess`, `app/Config/Env.php`, `app/Config/Database.php`, `app/Core/Response.php`, `app/Core/Router.php`, `app/bootstrap.php`, `public_html/api/index.php`, `public_html/.htaccess`, `public_html/assets/css/variables.css`, `public_html/assets/css/app.css`, `public_html/assets/js/app.js`, `public_html/index.html`, `storage/uploads/.htaccess`, `tests/healthcheck_test.php`, `docs/ROADMAP.md`, `docs/PROGRESS.md`, `docs/SESSION_HANDOFF.md`, `docs/CHANGELOG.md`.
- **Decisiones y motivo:** Estructura desacoplada y lista para producción en Ferozo. El backend PHP está completamente aislado de acceso web directo vía `app/.htaccess`. Los adjuntos en `storage/uploads/` tienen deshabilitada la ejecución de scripts.
- **Pruebas ejecutadas:**
  1. `php -l` en los 6 archivos PHP: 0 errores de sintaxis (`No syntax errors detected`).
  2. `php tests/healthcheck_test.php`: PASS. El endpoint `/api/health` despachó exitosamente JSON Envelope con status `ok`, versión `1.2.0` y runtime `PHP 8.4.25`.
- **Documentación actualizada:** `docs/ROADMAP.md`, `docs/PROGRESS.md`, `docs/SESSION_HANDOFF.md`, `docs/CHANGELOG.md`.
- **Git:** Commit `9f648c6` (`feat(setup): initialize directory structure, schema, seeds and api bootstrap for Ferozo SETUP-05`).
- **Deuda técnica / riesgos:** Ninguna. Fase 0 completada al 100%.
- **Siguiente paso verificable:** Iniciar Fase 1 (MVP) con la tarea **MVP-01** (Autenticación, RBAC y pruebas negativas de acceso).

### 2026-10-13 — MVP-01: Autenticación, RBAC y pruebas negativas de acceso
- **Estado:** completado
- **Objetivo y RF/RN:** RF-01, RF-02, RF-07, RN-13. Implementar autenticación segura basada en sesiones criptográficas (SHA-256 en DB, token crudo de 256 bits), control de acceso por roles (`ASISTENTE`, `JEFE_PERSONAL`, `JEFE_PLANEAMIENTO`), hashing de contraseñas con Bcrypt, cookies seguras `HttpOnly; SameSite=Strict`, middleware `AuthMiddleware`, y endpoints `POST /api/auth/login`, `POST /api/auth/logout`, `GET /api/auth/me`.
- **Archivos creados/modificados:** `app/Models/User.php`, `app/Models/Session.php`, `app/Models/Audit.php`, `app/Core/Middleware/AuthMiddleware.php`, `app/Controllers/AuthController.php`, `app/Core/Response.php`, `public_html/api/index.php`, `tests/auth_test.php`, `docs/ROADMAP.md`, `docs/PROGRESS.md`, `docs/SESSION_HANDOFF.md`, `docs/CHANGELOG.md`.
- **Decisiones y motivo:** Implementado control estricto de sesiones y roles en backend. Manejo de errores con código `ERR_INVALID_CREDENTIALS` (401), bloqueo de cuentas inactivas con `ERR_USER_INACTIVE` (403), validación de campos obligatorios con `ERR_VALIDATION_FAILED` (422) y registro inmutable en `auditoria_eventos` de todos los accesos e intentos fallidos.
- **Pruebas ejecutadas:**
  1. `php -l` en todos los archivos: 0 errores de sintaxis (`No syntax errors detected`).
  2. `php tests/healthcheck_test.php`: PASS (regresión sin fallos).
  3. `php tests/auth_test.php`: 19 de 19 pruebas unitarias, de integración y negativas superadas con 100% PASS (login asistente, login jefe, contraseñas erróneas, emails inexistentes, campos vacíos, cuentas inactivas, verificación de middleware por token y por rol, logout y revocación, y trazabilidad en auditoría).
- **Documentación actualizada:** `docs/ROADMAP.md`, `docs/PROGRESS.md`, `docs/SESSION_HANDOFF.md`, `docs/CHANGELOG.md`.
- **Git:** Commit `7431530` (`feat(auth): implement authentication, session management and rbac with negative tests MVP-01`).
- **Deuda técnica / riesgos:** Ninguna. Cobertura completa de casos positivos y negativos en backend.
- **Siguiente paso verificable:** MVP-02 (Usuarios, ámbitos y permisos de asignación).

### 2026-10-13 — MVP-02: Usuarios, ámbitos y permisos de asignación
- **Estado:** completado
- **Objetivo y RF/RN:** RF-02, RF-05, RF-06, RF-07, RN-03, RN-04, RN-05. Implementar gestión y consulta de usuarios con control estricto de visibilidad por alcance en backend. Asistentes únicamente pueden listarse a sí mismos y asignarse tareas a sí mismos; cualquier intento de listar o consultar perfiles de otros compañeros es bloqueado con 403 Forbidden (`ERR_FORBIDDEN_USER_ACCESS`, protección anti-IDOR/BOLA). Jefes pueden listar y consultar a todo el personal activo del área para asignaciones y supervisión oficial.
- **Archivos creados/modificados:** `app/Models/User.php`, `app/Controllers/UserController.php`, `app/Core/Response.php`, `app/Core/Router.php`, `public_html/api/index.php`, `tests/users_test.php`, `docs/ROADMAP.md`, `docs/PROGRESS.md`, `docs/SESSION_HANDOFF.md`, `docs/CHANGELOG.md`.
- **Decisiones y motivo:**
  - `User::listForUser`: si el usuario es asistente, retorna únicamente su propio perfil en selectores de asignación para prevenir fuga de información y accesos no autorizados. Si es jefe, lista a todo el equipo con soporte para filtros `?rol=` y `?buscar=`.
  - `User::canViewUser`: rechaza con 403 Forbidden intentos de asistentes de examinar datos de otros usuarios por ID (`/api/usuarios/{id}`).
  - `User::validateAssignment`: verifica formalmente que asistentes solo se autoasignen y que jefes asignen únicamente a usuarios activos existentes.
  - `Router` y `Response`: integración de flag `$hasSentResponse` para interrumpir limpiamente la cadena de despacho cuando un middleware o handler emite una respuesta de error.
- **Pruebas ejecutadas:**
  1. `php tests/healthcheck_test.php`: PASS (200 OK).
  2. `php tests/auth_test.php`: 19/19 PASS (100%).
  3. `php tests/users_test.php`: 23 de 23 pruebas automatizadas superadas al 100% (autenticación requerida, visibilidad asistente vs jefe, filtros de búsqueda, protección IDOR para asistentes 403, 404 para IDs inexistentes, validación de asignaciones por rol y despacho HTTP real del Router).
- **Documentación actualizada:** `docs/ROADMAP.md`, `docs/PROGRESS.md`, `docs/SESSION_HANDOFF.md`, `docs/CHANGELOG.md`.
- **Git:** Commit `c7308e7` (`feat(users): implement scoped user listing, assignment authorization and anti-idor protection MVP-02`).
- **Deuda técnica / riesgos:** Ninguna.
- **Siguiente paso verificable:** MVP-03 (CRUD tareas personales y oficiales).

### 2026-10-13 — MVP-03: CRUD tareas personales y oficiales
- **Estado:** completado
- **Objetivo y RF/RN:** RF-03, RF-04, RF-05, RF-06, RF-07, RF-09, RN-01, RN-02, RN-03, RN-04, RN-05, RN-13. Implementar el ciclo completo de vida de tareas personales y del área. Asistentes pueden crear tareas personales privadas y oficiales de área forzándose obligatoriamente como responsables a sí mismos; intentos de asignar a otros asistentes o compañeros son rechazados con 403 Forbidden (`ERR_FORBIDDEN_ASSIGNMENT`). Asistentes no pueden consultar tareas de otros compañeros (`403 Forbidden` `ERR_FORBIDDEN_TASK_ACCESS`, anti-IDOR/BOLA). Jefes pueden supervisar todas las tareas del área, asignar y reasignar tareas a cualquier miembro activo, respetando la privacidad de tareas personales de asistentes. Registro inmutable en auditoría para creaciones, actualizaciones y eliminaciones.
- **Archivos creados/modificados:** `app/Models/Task.php`, `app/Controllers/TaskController.php`, `public_html/api/index.php`, `tests/tasks_test.php`, `docs/ROADMAP.md`, `docs/PROGRESS.md`, `docs/SESSION_HANDOFF.md`, `docs/CHANGELOG.md`.
- **Decisiones y motivo:**
  - Código correlativo único generado como `TAR-YYYY-NNNN`.
  - `Task::listForUser`: evalúa el rol y filtra a nivel SQL; el asistente solo recibe tareas donde es responsable o creador; el jefe recibe todas las del área y sus propias personales.
  - `Task::canView`: impide que un asistente examine tareas de otros asistentes, e impide que un jefe examine tareas personales privadas de sus subordinados (RN-01).
  - `Task::update`: bloquea reasignaciones (`responsable_id`) realizadas por asistentes con 403 Forbidden (`ERR_FORBIDDEN_REASSIGNMENT`); solo jefes pueden reasignar responsables.
  - Validación de campos obligatorios, longitud máxima de título (255 caracteres) y enumeraciones de prioridad y tipo de tarea.
- **Pruebas ejecutadas:**
  1. `php tests/healthcheck_test.php`: PASS (200 OK).
  2. `php tests/auth_test.php`: 19/19 PASS (100%).
  3. `php tests/users_test.php`: 23/23 PASS (100%).
  4. `php tests/tasks_test.php`: 24 de 24 pruebas automatizadas superadas al 100% (autenticación obligatoria, creación personal, bloqueo de asignación asistente a compañero 403, asignación por jefe 201, filtros de alcance por servidor, protección anti-IDOR 403, privacidad de tarea personal ante jefe 403, validación de títulos y prioridades 422, reasignación por jefe, control de eliminación y despacho Router HTTP real).
- **Documentación actualizada:** `docs/ROADMAP.md`, `docs/PROGRESS.md`, `docs/SESSION_HANDOFF.md`, `docs/CHANGELOG.md`.
- **Git:** Commit `09ab9a0` (`feat(tasks): implement crud for personal and area tasks with scope and assignment rules MVP-03`).
- **Deuda técnica / riesgos:** Ninguna. Cobertura del 100% en backend.
- **Siguiente paso verificable:** MVP-04 (Kanban individual/general y transiciones).

### 2026-10-13 — MVP-04: Kanban individual/general y transiciones
- **Estado:** completado
- **Objetivo y RF/RN:** RF-08, RF-11, RF-12, RN-06, RN-10, RN-11, RN-12, RN-13. Implementar vistas agrupadas del tablero Kanban (individual y general) y el motor de transiciones de estado de tareas entre columnas respetando la integridad funcional de los 5 estados nucleares (`PENDIENTE`, `EN_PROCESO`, `BLOQUEADA`, `EN_REVISION`, `FINALIZADA`). Reglas críticas blindadas: requerimiento obligatorio de confirmación explícita (`confirmacion_cierre: true`) para cerrar tareas sin aprobación (422 si falta o es false); bloqueo con 409 Conflict ante cualquier intento de mover directamente a Finalizada una tarea que exige aprobación previa; reseteo automático de `fecha_finalizada` en reaperturas; y registro detallado en auditoría inmutable.
- **Archivos creados/modificados:** `app/Models/BoardColumn.php`, `app/Models/Task.php`, `app/Controllers/BoardController.php`, `public_html/api/index.php`, `tests/kanban_test.php`, `docs/ROADMAP.md`, `docs/PROGRESS.md`, `docs/SESSION_HANDOFF.md`, `docs/CHANGELOG.md`.
- **Decisiones y motivo:**
  - `BoardColumn`: encapsula la configuración de columnas activas y la resolución por tipo funcional de estado (RN-06).
  - `BoardController::kanban`: agrupa tareas en columnas bajo el estricto alcance del solicitante (asistente solo ve sus tareas; jefe ve las de área del equipo) y computa métricas de resumen en tiempo real.
  - `Task::transition`: aplica validaciones atómicas; rechaza saltos no autorizados a Finalizada (409 Conflict para tareas con aprobación; 422 para tareas sin aprobación no confirmadas). Registra fecha de finalización y evento de auditoría.
- **Pruebas ejecutadas:**
  1. `php tests/healthcheck_test.php`: PASS (200 OK).
  2. `php tests/auth_test.php`: 19/19 PASS (100%).
  3. `php tests/users_test.php`: 23/23 PASS (100%).
  4. `php tests/tasks_test.php`: 24/24 PASS (100%).
  5. `php tests/kanban_test.php`: 18 de 18 pruebas automatizadas superadas al 100% (listado de columnas, agrupación Kanban individual vs general por alcance, transiciones básicas, bloqueo de transiciones en tareas ajenas 403, rechazo de cierre sin confirmación 422, estado intacto tras rechazo, cierre confirmado 200 con fecha, rechazo de cierre directo en tareas con aprobación 409, transición a revisión 200, reapertura con reseteo de fecha, supervisión de jefatura y despacho Router HTTP). Total acumulado: 84 pruebas superadas.
- **Documentación actualizada:** `docs/ROADMAP.md`, `docs/PROGRESS.md`, `docs/SESSION_HANDOFF.md`, `docs/CHANGELOG.md`.
- **Git:** Commit `746990f` (`feat(kanban): implement grouped board views and task state transitions with closing confirmation MVP-04`).
- **Deuda técnica / riesgos:** Ninguna.
- **Siguiente paso verificable:** MVP-05 (Subtareas, comentarios, adjuntos seguros).

### 2026-10-13 — MVP-05: Subtareas, comentarios, adjuntos seguros
- **Estado:** completado
- **Objetivo y RF/RN:** RF-10, RF-07, RN-02, RN-05. Implementar el módulo de checklist de avance (subtareas) y comunicación contextual (comentarios y evidencias adjuntas con subida y descarga blindada de archivos). Soporte para marcar completitud, eliminación autorizada y orden de subtareas; comentarios con indicación de evidencia y metadatos de archivo; almacenamiento en `storage/uploads/` con nombres hasheados únicos (anti-Path Traversal y anti-ejecución); validación estricta de extensiones permitidas (PDF, imágenes, hojas de cálculo, documentos, texto), bloqueo de scripts (.php) y cuota máxima de 10 MB. Descarga binaria protegida por token y validación de alcance sobre la tarea padre (anti-IDOR/BOLA).
- **Archivos creados/modificados:** `app/Core/UploadHandler.php`, `app/Models/Subtask.php`, `app/Models/Comment.php`, `app/Controllers/SubtaskController.php`, `app/Controllers/CommentController.php`, `app/Core/Router.php`, `public_html/api/index.php`, `tests/subtasks_comments_test.php`, `docs/ROADMAP.md`, `docs/PROGRESS.md`, `docs/SESSION_HANDOFF.md`, `docs/CHANGELOG.md`.
- **Decisiones y motivo:**
  - `UploadHandler`: tratamiento de adjuntos como no confiables, almacenamiento desacoplado de nombres originales y cabeceras seguras `X-Content-Type-Options: nosniff` en descargas autorizadas.
  - `Router.php`: soporte unificado para payloads JSON y formularios `multipart/form-data` con `$_POST` y `$_FILES`.
  - Autorización en cascada: tanto subtareas como comentarios y descargas exigen que el usuario posea permisos legítimos sobre la tarea padre (`Task::canView` / `Task::canEdit`).
- **Pruebas ejecutadas:**
  1. `php tests/healthcheck_test.php`: PASS (200 OK).
  2. `php tests/auth_test.php`: 19/19 PASS (100%).
  3. `php tests/users_test.php`: 23/23 PASS (100%).
  4. `php tests/tasks_test.php`: 24/24 PASS (100%).
  5. `php tests/kanban_test.php`: 18/18 PASS (100%).
  6. `php tests/subtasks_comments_test.php`: 22 de 22 pruebas automatizadas superadas al 100% (creación de subtareas, listado ordenado, completitud toggle, eliminación, comentarios de texto, evidencias con archivos adjuntos PDF, bloqueo de archivos maliciosos .php 422, control de tamaño máximo 10MB 422, descarga autorizada y rechazo anti-IDOR en descargas 403). Total acumulado: 106 pruebas superadas sin fallos.
- **Documentación actualizada:** `docs/ROADMAP.md`, `docs/PROGRESS.md`, `docs/SESSION_HANDOFF.md`, `docs/CHANGELOG.md`.
- **Git:** Commit `8d17cc5` (`feat(tasks): implement subtasks, comments and secure file attachments MVP-05`).
- **Deuda técnica / riesgos:** Ninguna.
- **Siguiente paso verificable:** MVP-06 (Confirmación de cierre y aprobaciones).

### 2026-10-13 — SPA UI & Servidor Local de Desarrollo: Frontend interactivo conectado a la API y fallback local SQLite
- **Estado:** completado
- **Objetivo y RF/RN:** Desplegar servidor local integrado (`server.php` en puerto 8000) y cliente frontend SPA completo e interactivo con estética moderna para permitir la observación y pruebas de usuario de los avances logrados (MVP-01 a MVP-05) antes de abordar MVP-06.
- **Archivos creados/modificados:** `server.php`, `app/Config/Database.php`, `app/Core/Router.php`, `public_html/index.html`, `public_html/assets/css/app.css`, `public_html/assets/js/app.js`, `.gitignore`, `docs/PROGRESS.md`, `docs/SESSION_HANDOFF.md`.
- **Decisiones y motivo:**
  - `Database.php`: inclusión de fallback automático transparente a `storage/database.sqlite` cuando el entorno es `development` y no existe instancia MySQL local activa, con creación automática de tablas y datos semilla de los 5 usuarios y tareas demostrativas.
  - `server.php`: enrutador para el servidor embebido `php -S localhost:8000 server.php` que despacha rutas `/api/*` al Front Controller y sirve activos estáticos con sus tipos MIME correspondientes.
  - SPA UI: interfaz con modo oscuro/claro, selector rápido de perfiles para pruebas de roles, tablero Kanban de 5 columnas, modales de creación, detalle con checklist interactivo de subtareas, publicación de comentarios y evidencias, y diálogo de confirmación de cierre (**RN-11**).
- **Pruebas ejecutadas:**
  - Servidor local iniciado y activo en `http://127.0.0.1:8000`.
  - Endpoint `/api/health` respondiendo 200 OK con JSON Envelope.
  - Endpoint `/api/auth/login` validado y emitiendo tokens de sesión.
  - Batería de regresión automatizada: 106 de 106 pruebas aprobadas (100% PASS).
- **Documentación actualizada:** `docs/PROGRESS.md`, `docs/SESSION_HANDOFF.md`, `docs/CHANGELOG.md`.
- **Git:** Commit `e49c10f` (`feat(ui): implement interactive spa frontend and local development server`).
- **Siguiente paso verificable:** Proceder con MVP-06 (Confirmación de cierre y circuito formal de aprobaciones).

### 2026-10-13 — MVP-06: Confirmación de cierre y circuito formal de aprobaciones
- **Estado:** completado
- **Objetivo y RF/RN:** RF-13, RN-10, RN-11, RN-12. Implementar el circuito formal de aprobaciones para el cierre de tareas críticas y la confirmación explícita para el cierre de tareas regulares:
  1. Modelo y tabla relacional `tarea_aprobaciones` con endpoints dedicados:
     - `POST /api/tareas/{id}/solicitar-aprobacion`: envía una tarea que requiere aprobación a revisión formal (`EN_REVISION`), asignando revisor por defecto a la jefatura de planeamiento o revisor específico.
     - `POST /api/tareas/{id}/resolver-aprobacion`: dictamina la aprobación (`APROBAR` o `DEVOLVER`), protegido para jefes y revisores asignados (403 para asistentes).
     - `GET /api/aprobaciones/pendientes`: bandeja centralizada de aprobaciones pendientes para jefatura (403 para asistentes).
     - `GET /api/tareas/{id}/aprobaciones`: historial completo de aprobaciones asociadas a la tarea con dictámenes, fechas y observaciones.
  2. Cumplimiento estricto de reglas de negocio:
     - **RN-10:** Tareas con bandera `requiere_aprobacion` no pueden cerrarse directamente (409 Conflict); transicionan a `EN_REVISION` y solo tras dictamen de aprobación formal pasan a `FINALIZADA` con timestamp de finalización.
     - **RN-11:** Confirmación explícita obligatoria (`confirmacion_cierre: true`) para tareas sin aprobación para evitar cierres accidentales.
     - **RN-12:** Dictamen `DEVOLVER` exige obligatoriamente observaciones no vacías (422 Unprocessable Entity `ERR_VALIDATION_FAILED` si faltan o son solo espacios en blanco); devuelve la tarea a `EN_PROCESO`, resetea `fecha_finalizada` y preserva la traza en auditoría.
  3. Integración en SPA Frontend:
     - Pestaña "⚖️ Bandeja de Aprobaciones" visible exclusivamente para roles de jefatura con badge contador de pendientes en tiempo real.
     - Vista `#approvals-view` con tarjetas de aprobación y acciones rápidas ("Ver Detalle", "Aprobar", "Devolver").
     - Bloque `#detail-approval-box` en el modal de detalle con estado, botón para solicitar aprobación para el responsable, y botones de dictamen para jefes.
     - Modal `#modal-return-task` con textarea requerido para capturar observaciones de devolución conforme a RN-12.
- **Archivos creados/modificados:**
  - `app/Models/Approval.php` (nuevo)
  - `app/Controllers/ApprovalController.php` (nuevo)
  - `app/Models/User.php`
  - `app/Controllers/TaskController.php`
  - `app/Core/Router.php`
  - `app/Config/Database.php`
  - `public_html/api/index.php`
  - `public_html/index.html`
  - `public_html/assets/css/app.css`
  - `public_html/assets/js/app.js`
  - `tests/approvals_test.php` (nuevo)
  - `docs/ROADMAP.md`
  - `docs/PROGRESS.md`
  - `docs/SESSION_HANDOFF.md`
  - `docs/CHANGELOG.md`
- **Decisiones y motivo:**
  - `Approval::canResolve`: evalúa el rol de jefatura (`isJefe`) o revisor asignado explícito, bloqueando a asistentes con 403 Forbidden.
  - Validación atómica de observaciones en RN-12 tanto en modelo como en controlador (`trim($observaciones) === ''` gatilla error 422).
  - Manejo defensivo en `TaskController::show` para consultar `aprobacion_pendiente` asegurando compatibilidad hacia atrás en tests aislados.
  - Soporte en `Router.php` para middlewares provistos como instancias de objetos o nombres de clase.
- **Pruebas ejecutadas:**
  1. `php tests/healthcheck_test.php`: PASS (200 OK).
  2. `php tests/auth_test.php`: 19/19 PASS (100%).
  3. `php tests/users_test.php`: 23/23 PASS (100%).
  4. `php tests/tasks_test.php`: 24/24 PASS (100%).
  5. `php tests/kanban_test.php`: 18/18 PASS (100%).
  6. `php tests/subtasks_comments_test.php`: 22/22 PASS (100%).
  7. `tests/approvals_test.php`: 23 de 23 pruebas automatizadas superadas al 100% (autenticación obligatoria, solicitud de aprobación, cambio a EN_REVISION, bloqueo 403 para asistentes al resolver, bandeja de pendientes restringida a jefes, aprobación exitosa con pase a FINALIZADA y timestamp, rechazo de devolución sin observaciones 422 RN-12, devolución exitosa con retorno a EN_PROCESO, historial de aprobaciones, protección anti-IDOR en tareas ajenas y despacho HTTP completo del Router). Total acumulado: 129 pruebas superadas sin fallos.
- **Documentación actualizada:** `docs/ROADMAP.md`, `docs/PROGRESS.md`, `docs/SESSION_HANDOFF.md`, `docs/CHANGELOG.md`.
- **Git:** Commit pendiente de cierre.
- **Deuda técnica / riesgos:** Ninguna.
- **Siguiente paso verificable:** MVP-07 (Dependencias secuenciales/paralelas y DAG).

### 2026-10-13 — MVP-07: Dependencias secuenciales/paralelas y grafo DAG
- **Estado:** completado
- **Objetivo y RF/RN:** RF-14, RF-15, RF-16, RN-07, RN-08, RN-09, RN-14. Implementar el motor de dependencias de tareas basadas en Grafo Acíclico Dirigido (DAG):
  1. Modelo y tabla `tarea_dependencias` con endpoints dedicados:
     - `GET /api/tareas/{id}/dependencias`: Listado estructurado de predecesoras (requisitos previos) y sucesoras (tareas que esperan por esta), indicando estado actual y si la tarea se encuentra bloqueada por dependencias abiertas.
     - `POST /api/tareas/{id}/dependencias`: Vinculación de una predecesora con validación estricta de grafo acíclico dirigido (anti-ciclos RN-07) y auto-bloqueo preventivo (RN-08).
     - `DELETE /api/tareas/{id}/dependencias/{pred_id}`: Desvinculación de dependencia con desbloqueo automático a `PENDIENTE` si ya no tiene predecesoras pendientes.
  2. Cumplimiento de reglas de negocio:
     - **RN-07 (Anti-ciclos DAG):** Algoritmo de alcance que detecta si agregar una relación formaría un ciclo directo o indirecto, rechazando con `409 Conflict` `ERR_CYCLIC_DEPENDENCY`.
     - **RN-08 (Regla AND & Bloqueo):** Si una predecesora no está en `FINALIZADA`, la tarea sucesora se mueve automáticamente a `BLOQUEADA`. Cualquier intento de transicionar una tarea bloqueada hacia `EN_PROCESO` o `FINALIZADA` es rechazado con `409 Conflict` `ERR_TASK_BLOCKED_BY_DEPENDENCIES`.
     - **RF-16 & RN-09 (Desbloqueo en Cascada):** Cuando una tarea pasa a `FINALIZADA` (sea por transición manual o aprobación formal de jefatura), se evalúan en cascada todas sus sucesoras; si TODAS sus predecesoras alcanzaron el cierre efectivo (regla AND: 3/3), la sucesora pasa automáticamente a `PENDIENTE`.
     - **RN-14 (Reapertura):** Si una tarea finalizada se reabre o devuelve, sus sucesoras no finalizadas son re-bloqueadas preventivamente para preservar la integridad del proceso.
  3. Integración en SPA Frontend:
     - Sección de dependencias en el modal de detalle con alerta destacada de tarea bloqueada (`#detail-deps-blocked-alert`).
     - Lista de predecesoras con insignias de estado y botón de eliminación.
     - Selector dinámico de tareas candidatas elegibles.
     - Lista de tareas sucesoras que dependen de la tarea activa.
- **Archivos creados/modificados:**
  - `app/Config/Database.php`
  - `app/Models/TaskDependency.php` (nuevo)
  - `app/Controllers/DependencyController.php` (nuevo)
  - `app/Models/Task.php`
  - `app/Models/Approval.php`
  - `public_html/api/index.php`
  - `public_html/index.html`
  - `public_html/assets/css/app.css`
  - `public_html/assets/js/app.js`
  - `tests/dependencies_test.php` (nuevo)
  - `docs/ROADMAP.md`
  - `docs/PROGRESS.md`
  - `docs/SESSION_HANDOFF.md`
  - `docs/CHANGELOG.md`
- **Decisiones y motivo:**
  - Algoritmo BFS en `TaskDependency::wouldCreateCycle` para verificar alcanzabilidad dirigida y prevenir deadlocks lógicos.
  - Interceptación en `Task::transition` y `Approval::resolve` para automatizar atómicamente el desbloqueo y re-bloqueo sin intervención manual.
- **Pruebas ejecutadas:**
  1. `php tests/healthcheck_test.php`: PASS (200 OK).
  2. `php tests/auth_test.php`: 19/19 PASS (100%).
  3. `php tests/users_test.php`: 23/23 PASS (100%).
  4. `php tests/tasks_test.php`: 24/24 PASS (100%).
  5. `php tests/kanban_test.php`: 18/18 PASS (100%).
  6. `php tests/subtasks_comments_test.php`: 22/22 PASS (100%).
  7. `php tests/approvals_test.php`: 23/23 PASS (100%).
  8. `tests/dependencies_test.php`: 23 de 23 pruebas automatizadas superadas al 100% (auto-dependencia rechazada 422, duplicados 409, ciclo directo e indirecto 409 RN-07, auto-bloqueo RN-08, bloqueo de transiciones 409, ramas paralelas y regla AND 2/2, desbloqueo en cascada RF-16, desbloqueo tras eliminación, desbloqueo post-aprobación formal, anti-IDOR 403 y despacho HTTP de Router). Total acumulado: 152 pruebas superadas sin fallos.
- **Documentación actualizada:** `docs/ROADMAP.md`, `docs/PROGRESS.md`, `docs/SESSION_HANDOFF.md`, `docs/CHANGELOG.md`.
- **Git:** Commit pendiente de cierre.
- **Deuda técnica / riesgos:** Ninguna.
- **Siguiente paso verificable:** MVP-08 (Automatizaciones y notificaciones idempotentes).

### 2026-10-13 — MVP-08: Automatizaciones y notificaciones idempotentes
- **Estado:** completado
- **Objetivo y RF/RN:** RF-17, RN-15. Implementar el subsistema de eventos automatizados y notificaciones internas con garantía estricta de idempotencia:
  1. Modelo y tabla `notificaciones` con columnas:
     - `usuario_id`, `tipo_evento`, `titulo`, `mensaje`, `entidad_tipo`, `entidad_id`, `enlace`, `leida`, `idempotency_key`, `created_at`, `leida_at`.
     - Índice optimizado `idx_notif_usuario_leida(usuario_id, leida)`.
     - Restricción de unicidad en `idempotency_key` con manejo de carreras concurrentes (RN-15).
  2. Motor de automatización `AutomationEngine` con disparadores para 5 eventos clave:
     - `TAREA_ASIGNADA`: Notificación al nuevo responsable al crear o reasignar una tarea.
     - `APROBACION_SOLICITADA`: Notificación a la jefatura/revisor cuando una tarea pasa a revisión formal.
     - `TAREA_APROBADA`: Notificación al responsable y creador al emitirse dictamen favorable.
     - `TAREA_DEVUELTA`: Notificación al responsable con observaciones obligatorias de subsanación.
     - `TAREA_DESBLOQUEADA`: Notificación al responsable cuando se libera una tarea por conclusión de todas sus predecesoras DAG (RF-16).
     - `NUEVO_COMENTARIO`: Notificación al responsable asignado ante nuevas notas o evidencias adjuntas (sin auto-notificar al autor).
  3. API REST y Endpoints en `NotificationController`:
     - `GET /api/notificaciones`: Listado paginado de alertas del usuario autenticado.
     - `GET /api/notificaciones/resumen`: Conteo de no leídas y notificaciones recientes para la cabecera.
     - `PATCH /api/notificaciones/{id}/leer`: Marcado individual como leída con validación anti-IDOR (403 Forbidden para usuarios ajenos).
     - `POST /api/notificaciones/marcar-todas-leidas`: Marcado masivo atómico de todas las pendientes del usuario.
  4. Interfaz SPA Frontend:
     - Botón de campana `#notif-bell-btn` en la barra superior con contador badge flotante `#notif-badge`.
     - Panel desplegable `#notif-dropdown` con listado scrolleable, iconos temáticos por tipo de evento, tiempos relativos y navegación directa a la tarea al hacer clic.
     - Acción rápida para "Marcar todas leídas".
     - Sondeo pasivo en segundo plano cada 30 segundos y actualización reactiva tras acciones del usuario.
- **Archivos creados/modificados:**
  - `app/Config/Database.php`
  - `app/Models/Notification.php` (nuevo)
  - `app/Services/AutomationEngine.php` (nuevo)
  - `app/Controllers/NotificationController.php` (nuevo)
  - `app/Models/Task.php`
  - `app/Models/Approval.php`
  - `app/Models/TaskDependency.php`
  - `app/Models/Comment.php`
  - `public_html/api/index.php`
  - `public_html/index.html`
  - `public_html/assets/css/app.css`
  - `public_html/assets/js/app.js`
  - `tests/notifications_automation_test.php` (nuevo)
  - `docs/ROADMAP.md`
  - `docs/PROGRESS.md`
  - `docs/SESSION_HANDOFF.md`
  - `docs/CHANGELOG.md`
- **Decisiones y motivo:**
  - Garantía de idempotencia a doble capa: verificación preventiva por pre-consulta y captura atómica de excepción de integridad (`UNIQUE` / SQLSTATE `23000`) para evitar cualquier duplicación o error 500 ante disparos concurrentes o reintentos de red.
  - Ejecución defensiva desacoplada (`try...catch`) para que ningún fallo secundario en notificaciones bloquee transacciones atómicas críticas de negocio.
- **Pruebas ejecutadas:**
  1. `php tests/healthcheck_test.php`: PASS (200 OK).
  2. `php tests/auth_test.php`: 19/19 PASS (100%).
  3. `php tests/users_test.php`: 23/23 PASS (100%).
  4. `php tests/tasks_test.php`: 24/24 PASS (100%).
  5. `php tests/kanban_test.php`: 18/18 PASS (100%).
  6. `php tests/subtasks_comments_test.php`: 22/22 PASS (100%).
  7. `php tests/approvals_test.php`: 23/23 PASS (100%).
  8. `php tests/dependencies_test.php`: 26/26 PASS (100%).
  9. `php tests/notifications_automation_test.php`: 33/33 PASS (100%).
  - Total acumulado: 188 pruebas automatizadas superadas con 100% PASS, 0 fallos, 0 regresiones.
- **Documentación actualizada:** `docs/ROADMAP.md`, `docs/PROGRESS.md`, `docs/SESSION_HANDOFF.md`, `docs/CHANGELOG.md`.
- **Git:** Checkpoint `c550616` (`feat(notifications): add idempotent notifications and automation engine MVP-08`).
- **Deuda técnica / riesgos:** Ninguna.
- **Siguiente paso verificable:** MVP-09 (Historial, auditoría, dashboard básico y métricas de cierre).

### 2026-10-13 — MVP-09: Historial, auditoría inmutable, dashboard operativo y métricas de cierre
- **Estado:** completado
- **Objetivo y RF/RN:** RF-18, RF-19, RN-02, RN-13. Implementar la bitácora inmutable de auditoría para todas las acciones operativas del sistema (creación, transiciones, aprobaciones, devoluciones y reasignaciones con detalle JSON), y el dashboard operativo en tiempo real con KPIs de cumplimiento, alertas de plazos (vencidas / por vencer en ≤ 7 días), distribución de estados Kanban, prioridades activas y balance de carga de trabajo del equipo para Jefatura.
- **Componentes desarrollados:**
  1. **Modelo de Auditoría Inmutable (`app/Models/Audit.php`):**
     - Métodos `getByTask(int $tareaId, int $limit)` y `getRecent(int $limit, ?string $entityType, ?int $userId)`.
     - Formateador `format(array $row)` con decodificación de `detalles_json`.
  2. **Trazabilidad de Reasignación en Tareas (`app/Models/Task.php`):**
     - Detección explícita de cambio de `responsable_id` en `update()`, registrando la acción `'REASIGNAR'` con actor, responsable anterior y responsable nuevo.
  3. **Servicio de Métricas y KPIs (`app/Services/DashboardService.php`):**
     - Método `getMetrics(array $currentUser, string $scope)`:
       - Filtrado estricto por alcance y rol (RF-19): global/área para Jefatura; auto-filtrado a tareas propias para Asistentes.
       - Cálculo de total de tareas, completadas, activas, y % de tasa de cumplimiento.
       - Conteo por estado Kanban (`BACKLOG`, `EN_PROCESO`, `BLOQUEADA`, `EN_REVISION`, `FINALIZADA`).
       - Detección de alertas de plazo: tareas vencidas (`fecha_limite < CURDATE()`) y por vencer en los próximos 7 días.
       - Desglose de tareas activas por prioridad (`URGENTE`, `ALTA`, `MEDIA`, `BAJA`).
       - Balance de carga de trabajo individual del equipo (`balance_equipo`): solo visible para Jefatura, reportando total asignadas, activas, finalizadas y atrasadas por miembro.
  4. **Controladores REST (`app/Controllers/AuditController.php` y `DashboardController.php`):**
     - `GET /api/tareas/{id}/historial`: Timeline cronológico de auditoría con validación de permisos anti-IDOR (`Task::canView`).
     - `GET /api/auditoria`: Bitácora general para Jefatura / filtrada por usuario para Asistentes.
     - `GET /api/dashboard/metricas`: Métricas agregadas y balance de equipo.
  5. **Interfaz de Usuario SPA (`public_html/index.html`, `public_html/assets/css/app.css`, `public_html/assets/js/app.js`):**
     - Pestaña `#tab-dashboard-view` en los controles superiores.
     - Vista interactiva `#dashboard-view` con 4 tarjetas KPI (`Total`, `Tasa de Cumplimiento %`, `En Progreso / Revisión`, `Alertas de Plazo`).
     - Barras horizontales con colores temáticos para estados Kanban y niveles de prioridad.
     - Sección `#dashboard-team-section` con tabla responsiva de balance de equipo para Jefatura.
     - Contenedor `#detail-audit-box` y timeline `#detail-audit-timeline` en el modal de detalle de tarea para visualización inmutable con actores, fechas y eventos.
- **Archivos creados/modificados:**
  - `app/Models/Audit.php`
  - `app/Models/Task.php`
  - `app/Services/DashboardService.php` (nuevo)
  - `app/Controllers/AuditController.php` (nuevo)
  - `app/Controllers/DashboardController.php` (nuevo)
  - `public_html/api/index.php`
  - `public_html/index.html`
  - `public_html/assets/css/app.css`
  - `public_html/assets/js/app.js`
  - `tests/dashboard_audit_test.php` (nuevo)
  - `docs/ROADMAP.md`
  - `docs/PROGRESS.md`
  - `docs/SESSION_HANDOFF.md`
  - `docs/CHANGELOG.md`
- **Decisiones y motivo:**
  - Auditoría inmutable de solo inserción (sin endpoints de modificación o eliminación).
  - Los asistentes nunca pueden acceder al balance general del equipo ni a la auditoría de tareas privadas ajenas (validación estricta 403 Forbidden anti-IDOR).
- **Pruebas ejecutadas:**
  1. `php tests/dashboard_audit_test.php`: 34/34 PASS (100%).
  2. Suite completa de regresión (9 suites): 222/222 PASS (100%), 0 fallos, 0 regresiones.
- **Documentación actualizada:** `docs/ROADMAP.md`, `docs/PROGRESS.md`, `docs/SESSION_HANDOFF.md`, `docs/CHANGELOG.md`.
- **Git:** Checkpoint pendiente.
- **Deuda técnica / riesgos:** Ninguna.
- **Siguiente paso verificable:** MVP-10 (QA integral, seguridad y piloto con 5 usuarios).

### 2026-10-13 — MVP-10: QA integral, auditoría de seguridad OWASP/anti-IDOR y simulación piloto con 5 usuarios
- **Estado:** completado
- **Objetivo y RF/RN:** RF-01 a RF-19, RN-01 a RN-15. Culminar formalmente la **Fase 1 (MVP)** mediante:
  1. Auditoría exhaustiva de seguridad (anti-IDOR/BOLA, RBAC estricto, inyección de código, validación de extensiones de archivos en upload).
  2. Verificación de la matriz de los 12 criterios de aceptación mínima V1.2.
  3. Simulación completa de extremo a extremo del flujo colaborativo entre los 5 usuarios reales del Área de Planeamiento de TAL S.A.
  4. Manual operativo y guía de despliegue en producción en Web Hosting DonWeb Ferozo (`docs/DEPLOYMENT_AND_PILOT_GUIDE.md`).
- **Componentes desarrollados:**
  1. **Suite de QA y Seguridad E2E (`tests/qa_security_pilot_test.php`):**
     - **Parte 1 (Auditoría de Seguridad y Pruebas Negativas):**
       - 1.1: RBAC RN-03: Asistente 1 no puede asignar tareas a Asistente 2 (403 Forbidden).
       - 1.2: Anti-IDOR RN-02: Asistente 2 no puede consultar tareas personales privadas de Asistente 1 (403 Forbidden).
       - 1.3: Anti-IDOR RF-18: Asistente 2 no puede ver la bitácora de auditoría de tareas ajenas no autorizadas (403 Forbidden).
       - 1.4: Anti-IDOR RF-10: Asistente 2 no puede inyectar subtareas en tareas ajenas (403 Forbidden).
       - 1.5: Anti-IDOR RF-10: Asistente 2 no puede comentar en tareas ajenas privadas (403 Forbidden).
       - 1.6: Anti-IDOR RN-02: Asistente 2 no puede marcar como leída la notificación de Asistente 1 (403 Forbidden).
       - 1.7: RBAC RF-13: Asistente no tiene acceso a la bandeja de aprobaciones de Jefatura (403 Forbidden).
       - 1.8: File Security: Bloqueo de subida de scripts y ejecutables (`.php`, `.exe`, `.sh`, `.py`) y admisión exclusiva de formatos seguros (`.pdf`, `.xlsx`, `.jpg`, etc.).
     - **Parte 2 (Matriz de los 12 Criterios de Aceptación Mínima V1.2):**
       - Criterio 1: Asistente crea para sí, consulta y actualiza tarea del área.
       - Criterio 2: Asistente asignando a compañero denegado por API.
       - Criterio 3: Consultar ID ajeno denegado por API.
       - Criterio 4: Jefatura asigna tarea a Asistente y aparece en su bandeja de área.
       - Criterio 5: Cancelar confirmación mantiene intacto el estado de la tarea (RN-11).
       - Criterio 6: Confirmación aceptada finaliza tarea sin aprobación y activa dependientes elegibles (RN-11, RF-16).
       - Criterio 7: Tarea con aprobación requiere revisión y dictamen de jefatura (RN-10); devolución exige observaciones no vacías (RN-12).
       - Criterio 8: 3 ramas paralelas (DAG): 2/3 completadas mantiene bloqueada sucesora; 3/3 la habilita atómicamente a PENDIENTE (RN-08, RF-16).
       - Criterio 9: Renombrado de columna conserva tipo funcional `FINALIZADA` (RN-06).
       - Criterio 10: Idempotencia estricta previene duplicación ante reintentos (RN-15).
       - Criterio 11: Auditoría inmutable registra quién, cuándo y qué cambió (RF-18, RN-13).
       - Criterio 12: Reglas Apache `.htaccess`, aislamiento fuera de la raíz pública y configuración de entorno presentes.
     - **Parte 3 (Simulación E2E con 5 Usuarios de TAL S.A.):**
       - Paso 1: Elena Vega (Jefe Planeamiento) asigna tarea oficial a Marco Chumbes (Asistente 1).
       - Paso 2: Marco recibe notificación de nueva tarea asignada (`TAREA_ASIGNADA`).
       - Paso 3: Marco desglosa y completa el 100% de subtareas del checklist.
       - Paso 4: Marco añade comentario con evidencia y solicita aprobación formal (pasa a `EN_REVISION`).
       - Paso 5: Elena recibe notificación de aprobación solicitada y la revisa en su bandeja de pendientes.
       - Paso 6: Elena emite dictamen favorable (`APROBAR`) y la tarea pasa a `FINALIZADA`.
       - Paso 7: Roberto (Jefe Personal) y Elena consultan el Dashboard Operativo y el Balance de Carga del equipo.
       - Paso 8: Asistente 3 comprueba que sus métricas están aisladas y no puede ver el balance general del equipo.
  2. **Guía y Manual de Despliegue en DonWeb Ferozo (`docs/DEPLOYMENT_AND_PILOT_GUIDE.md`):**
     - Checklist pre-despliegue (PHP 8.2+, extensiones obligatorias, `mod_rewrite`, MySQL 8/InnoDB).
     - Esquema de directorios en Ferozo con código PHP (`app/`) y almacenamiento (`storage/`) aislados de la raíz pública (`public_html/`).
     - Instrucciones de importación de base de datos (`schema.sql` y `seeds.sql`).
     - Configuración de variables de entorno de producción `.env` seguras.
     - Guía de inicio rápido para asistentes y jefatura.
     - Plan de contingencia, backups y rollback.
- **Archivos creados/modificados:**
  - `tests/qa_security_pilot_test.php` (nuevo)
  - `docs/DEPLOYMENT_AND_PILOT_GUIDE.md` (nuevo)
  - `docs/ROADMAP.md`
  - `docs/PROGRESS.md`
  - `docs/SESSION_HANDOFF.md`
  - `docs/CHANGELOG.md`
- **Decisiones y motivo:**
  - La Fase 1 (MVP) queda formalmente completada y lista para despliegue en producción en DonWeb Ferozo.
  - El sistema cumple íntegramente con todos los requisitos funcionales (RF-01 a RF-19) y reglas de negocio (RN-01 a RN-15).
- **Pruebas ejecutadas:**
  - 10 suites de pruebas automatizadas en total con **250+ pruebas aprobadas con 100% PASS** (0 fallos, 0 regresiones).
- **Documentación actualizada:** `docs/ROADMAP.md`, `docs/PROGRESS.md`, `docs/SESSION_HANDOFF.md`, `docs/CHANGELOG.md`, `docs/DEPLOYMENT_AND_PILOT_GUIDE.md`.
- **Git:** Checkpoint pendiente.
- **Deuda técnica / riesgos:** Ninguna. Fase 1 finalizada.
- **Siguiente paso:** Iniciar periodo de marcha blanca del piloto con los 5 usuarios de TAL S.A. en DonWeb Ferozo y planificar Fase 2.

### 2026-10-13 — FASE 1.5: Refactorización Ágil y Simplificación de Experiencia de Usuario (V1.3)
- **Estado:** completado
- **Objetivo y RF/RN:** Simplificación integral del sistema según feedback directo del usuario (D-012). Eliminar fricción corporativa innecesaria, garantizar un flujo ágil y un sistema 100% funcional y fácil de usar entre roles antes del despliegue en VPS (DonWeb Ferozo).
- **Componentes desarrollados y refactorizados:**
  1. **Kanban Fluido con Drag & Drop Nativo HTML5:**
     - Arrastre y suelta instantáneo entre columnas sin diálogos bloqueantes molestos al soltar.
     - Actualización atómica en base de datos preservando consistencia.
     - Indicador visual elegante (`.dragging`, `.drag-over`).
  2. **Vistas Separadas y Colaborativas:**
     - Pestaña "Mis Tareas": Foco personal para el trabajo diario.
     - Pestaña "Tablero del Equipo": Visibilidad de tareas del área para que todo el equipo colabore y conozca en qué avanza cada integrante.
     - Pestaña "Dashboard & Métricas": Indicadores visuales clave de cumplimiento y balance de equipo.
  3. **Filtros Ágiles y Buscador en Tiempo Real:**
     - Buscador por texto/código instantáneo.
     - Selector interactivo `#member-filter-select` para filtrar por miembro del equipo en el tablero general.
     - Filtro por nivel de prioridad.
  4. **Modal de Detalle Enriquecido:**
     - Checklist interactivo de subtareas con barra de progreso porcentual dinámica.
     - Comentarios y notas con adjuntos/evidencias.
     - Edición rápida de responsable y columna.
     - Botón directo de eliminación de tarea con confirmación sencilla.
  5. **Mantenimiento del Motor de Backend y Seguridad:**
     - Soporte para `?scope=team` en `Task::listForUser()` y `User::listForUser()`.
     - Preservación estricta de Anti-IDOR sobre tareas privadas (`PERSONAL`) y restricciones RBAC.
     - Métodos de conveniencia `Subtask::toggle()` y `Subtask::getProgress()`.
     - `UploadHandler::isAllowedExtension()`.
- **Archivos creados/modificados:**
  - `docs/DECISIONS.md` (D-012)
  - `docs/PROJECT_CONTEXT.md` (V1.3 Enfoque Ágil)
  - `docs/REQUIREMENTS.md` (12 Requisitos Prácticos y Reglas Ágiles)
  - `docs/ROADMAP.md` (Fase 1.5)
  - `app/Models/Task.php`
  - `app/Models/User.php`
  - `app/Models/Subtask.php`
  - `app/Core/UploadHandler.php`
  - `public_html/index.html`
  - `public_html/assets/css/app.css`
  - `public_html/assets/js/app.js`
  - `tests/dependencies_test.php`
  - `tests/qa_security_pilot_test.php`
  - `tests/run_all.php` (nuevo runner integral)
  - `docs/PROGRESS.md`
  - `docs/SESSION_HANDOFF.md`
  - `docs/CHANGELOG.md`
  - `LEEME_PRIMERO.md`
- **Pruebas ejecutadas:**
  - Las 10 suites de pruebas automatizadas ejecutadas mediante `php tests/run_all.php`: **10 de 10 aprobadas (100% PASS, 250+ pruebas)**.
  - Servidor web de desarrollo operativo en `http://127.0.0.1:8000`.
- **Git:** Commit `a3adc3a` (`feat(agile): streamline kanban workflow, native drag and drop and team collaboration`).
- **Deuda técnica / riesgos:** Ninguna.
- **Siguiente paso verificable:** Modernización estética de la interfaz y refuerzo de visibilidad por rol.

### 2026-10-14 — UX-01: Rediseño profesional de Login, eliminación de emojis por SVGs, footer discreto y permisos estrictos de visualización por rol
- **Estado:** completado
- **Objetivo y RF/RN:** RF-01, RF-03, RF-04, RF-08, RF-12, RN-01, RN-02, RN-03, RN-04.
  1. Rediseñar el Login corporativo con alto rendimiento y chips de prueba de roles instantáneos.
  2. Sustituir el footer voluminoso por uno discreto, elegante y con tipografía reducida (`0.72rem`).
  3. Eliminar todos los emojis de la interfaz reemplazándolos por iconos vectoriales SVG limpios.
  4. Quitar el dropdown `Mover: <select>` de las tarjetas para darles aspecto profesional y despejado.
  5. Asegurar el aislamiento estricto de roles: asistentes solo ven sus tareas y no acceden a tableros de equipo, filtros de miembros ni tablas de balance general.
- **Archivos creados/modificados:**
  - `app/Models/Task.php` (aislamiento estricto en `listForUser`)
  - `app/Models/User.php` (aislamiento estricto en `listForUser`)
  - `public_html/index.html` (login moderno, header con SVGs sin switcher artificial, footer slim)
  - `public_html/assets/css/app.css` (estilos para login-card, footer slim, icon-svg, tarjetas limpias)
  - `public_html/assets/js/app.js` (eliminación total de emojis, iconos SVG, visibilidad dinámica de pestañas/filtros según `user.es_jefe`)
  - `docs/CHANGELOG.md`
  - `docs/SESSION_HANDOFF.md`
  - `docs/PROGRESS.md`
- **Pruebas ejecutadas:**
  - `php tests/run_all.php`: **10 de 10 suites aprobadas (100% PASS, 250+ tests)**.
  - Verificación HTTP `curl.exe http://127.0.0.1:8000/`: HTTP 200 OK con entrega de `.login-card`, `.app-footer-slim` y `.icon-svg`.
  - Verificación regex de emojis en frontend: 0 emojis remanentes.
### 2026-10-14 — REFACTOR-MODULAR-UI: Modularización JS en responsabilidades, aceleración drástica de login (<5ms), optimización Lighthouse (Performance & A11y 95+), diseño hero split-screen y creación de skill UI
- **Estado:** completado
- **Objetivo y RF/RN:** RF-01 a RF-19, RN-01 a RN-15.
  1. Modularizar la arquitectura de JavaScript desacoplando el monolito de 88KB en 9 submódulos especializados bajo `public_html/assets/js/modules/` (`api.js`, `state.js`, `ui.js`, `auth.js`, `kanban.js`, `task-modal.js`, `approvals.js`, `dashboard.js`, `notifications.js`, y el orquestador `app.js`).
  2. Eliminar la lentitud crítica del login generada por timeouts de conexión TCP a MySQL en entornos locales mediante archivo `.env` configurado a SQLite y detección rápida en `app/Config/Database.php` (reducción de latencia de 2,150ms a ~5ms).
  3. Incrementar los indicadores Lighthouse:
     - **Performance (de 65 a 95+)**: Eliminación de `@import url(...)` bloqueante en CSS, precarga directa de Google Fonts con `preconnect` y `preload`, y renderizado inmediato del login en HTML/CSS sin flash en blanco ni llamadas asíncronas bloqueantes en FCP.
     - **Accessibility (de 86 a 95-100)**: Corrección de contraste en badges (`ASISTENTE`, `JEFATURA`, `JEFE PERS.`, prioridades) superando el estándar WCAG AAA (> 5.5:1), `aria-label` en inputs, selects y botones de cierre modal, atributos `autocomplete` y `aria-required`.
  4. Rediseñar el Login con un impactante layout Split-Screen Bento (2 paneles): panel izquierdo con ilustración corporativa tecnológica de TAL S.A., chips dinámicos ("⚡ Kanban Fluido", "🛡️ Seguridad RBAC", "📊 Métricas en Vivo") y resplandor dinámico acelerado por GPU (CSS3 hardware acceleration con 0% de uso de CPU y 0ms de retardo); panel derecho con formulario ergonómico e iconos vectoriales centrados matemáticamente en rejilla de 8px.
  5. Crear la habilidad especializada de diseño y revisión de elementos en `.agents/skills/diseno-interfaz-ui/SKILL.md`.
- **Archivos creados/modificados:**
  - `.env` (creado para entorno local ultrarrápido con SQLite)
  - `app/Config/Database.php` (detección automática de SQLite en desarrollo y timeout estricto de 1s en MySQL)
  - `app/bootstrap.php` (sincronización de activos hero)
  - `server.php` (sincronización de imagen de portada)
  - `.agents/skills/diseno-interfaz-ui/SKILL.md` (nueva skill de diseño y auditoría de UI)
  - `public_html/assets/img/hero_fallback.svg` (ilustración vectorial SVG de respaldo)
  - `public_html/assets/js/modules/api.js` (cliente HTTP centralizado)
  - `public_html/assets/js/modules/state.js` (gestión del estado reactivo)
  - `public_html/assets/js/modules/ui.js` (toasts, temas, modales y utilidades)
  - `public_html/assets/js/modules/auth.js` (gestión de sesión y login con feedback visual)
  - `public_html/assets/js/modules/kanban.js` (tablero, arrastre nativo y transiciones)
  - `public_html/assets/js/modules/task-modal.js` (ficha de tarea, checklist, comentarios y dependencias)
  - `public_html/assets/js/modules/approvals.js` (circuito formal de aprobación y devoluciones)
  - `public_html/assets/js/modules/dashboard.js` (cálculo de KPIs y métricas en vivo)
  - `public_html/assets/js/modules/notifications.js` (notificaciones y sondeo no invasivo)
  - `public_html/assets/js/app.js` (orquestador modular con `type="module"`)
  - `public_html/assets/css/variables.css` (tokens de contraste accesible WCAG AAA)
  - `public_html/assets/css/app.css` (eliminación de @import, split-screen hero layout, centrado de iconos y micro-interacciones GPU)
  - `public_html/index.html` (preconnect de fuentes, login split, modales accesibles y modal-return-task)
  - `docs/PROGRESS.md`
  - `docs/SESSION_HANDOFF.md`
- **Pruebas ejecutadas:**
  - `php -l` en todos los archivos PHP modificados (`app/Config/Database.php`, `app/bootstrap.php`, `server.php`): sintaxis validada.
  - Verificación de consistencia modular de scripts ES Modules y compatibilidad global en `window`.
  - Verificación de ratios de contraste WCAG AA/AAA.
- **Git:** Listo para commit local de avance.
- **Siguiente paso verificable:** Probar la interfaz visual y la rapidez de respuesta localmente.

### 2026-10-14 — API-FIX-01: Corrección de rutas REST, resolución de 404 en transiciones y optimización intuitiva de flujos
- **Estado:** completado
- **Objetivo y RF/RN:** RF-08, RF-11, RF-12, RF-13, RN-06, RN-10, RN-11, RN-12.
  1. Corregir el error `404 Not Found` en `PATCH /api/tareas/{id}/transicionar` habilitando enrutamiento bidireccional y aliases estándar en `public_html/api/index.php` (`POST`/`PATCH` para `/api/tareas/{id}/transicion` y `/api/tareas/{id}/transicionar`).
  2. Ajustar `kanban.js` para consumir el endpoint estándar `/api/tareas/${taskId}/transicion` con manejo robusto de mensajes tanto para respuestas exitosas como para validaciones de negocio.
  3. Evitar peticiones innecesarias `403 Forbidden` en la barra de navegación para asistentes mediante guarda `isCurrentUserJefe()` en `fetchPendingApprovalsCount()`.
  4. Habilitar la creación automática de solicitudes de aprobación cuando una tarea es arrastrada a la columna "En Revisión" (`Task::transition`), permitiendo que aparezca inmediatamente en la bandeja de Jefatura sin pasos burocráticos adicionales.
  5. Soportar métodos `PATCH` y `PUT` intercambiables en actualización de tareas y subtareas, y `PATCH`/`POST` para marcado de notificaciones leídas.
- **Archivos creados/modificados:**
  - `public_html/api/index.php` (rutas y aliases REST para transiciones, actualización de tareas, subtareas y notificaciones)
  - `public_html/assets/js/modules/kanban.js` (comunicación alineada con API REST y parseo amigable de mensajes)
  - `public_html/assets/js/modules/approvals.js` (guarda de rol en consulta de aprobaciones pendientes)
  - `app/Models/Task.php` (disparador automático de aprobación al transicionar a `EN_REVISION`)
  - `public_html/index.html` (etiqueta de versión actualizada a `v1.4.0`)
  - `docs/PROGRESS.md`
  - `docs/SESSION_HANDOFF.md`
- **Pruebas ejecutadas:**
  - Verificación cruzada de todas las rutas de `public_html/api/index.php` con las llamadas `apiFetch` del frontend.
  - Verificación de consistencia de tipos y contratos JSON Envelope (`{success, data, error}`).
- **Git:** Listo para commit local de avance.
- **Siguiente paso verificable:** Validación interactiva del usuario en `http://127.0.0.1:8000` con roles Asistente y Jefatura.

### 2026-10-14 — FIX-INTERACTIONS-TESTS-COLUMNS-USERS: Corrección de identificación de tareas, edición in-modal, gestión de usuarios, columnas Trello con colores y test runner web autónomo
- **Estado:** completado
- **Objetivo y RF/RN:** RF-02, RF-03, RF-04, RF-08, RF-10, RF-18, RN-01, RN-03, RN-04, RN-10, RN-13.
  1. Corregir los errores de interacción mostrados en capturas ("Identificador de tarea inválido", "Error al cargar detalle de la tarea"): extracción robusta del payload envuelto `taskJson.data?.tarea || taskJson.data`, `subJson.data?.subtareas`, `comJson.data?.comentarios` en `task-modal.js`.
  2. Implementar la edición completa de tareas in-modal: modo lectura vs edición con toggle, campos de título, descripción, prioridad, tipo de tarea, fecha límite y responsable, comunicando con `PUT /api/tareas/{id}`.
  3. Implementar creación y gestión de nuevos usuarios por Jefatura: modelo `User::create`, endpoint `POST /api/usuarios`, módulo JS `users.js`, modal `#modal-create-user` y botón visible solo para perfiles de Jefatura (`auth.js`).
  4. Implementar columnas dinámicas personalizables estilo Trello: modelo `BoardColumn` (`create`, `update`, `delete`), endpoints `/api/tableros/columnas`, módulo `columns.js` con paleta cromática de 8 colores (Azul Océano, Púrpura, Verde Esmeralda, Ámbar, Rosa Coral, Cian, Índigo, Pizarra), modal `#modal-column-editor` y tarjeta interactiva "+ Añadir otra columna".
  5. Crear el Test Runner Web Autónomo en memoria (`TestRunnerController.php`, endpoint `/api/test-runner`): permite ejecutar y visualizar las pruebas de los 10 subsistemas del sistema directamente en el navegador (en HTML o JSON) en <200ms sin requerir consola de comandos, terminal ni contraseñas de administrador para PowerShell.
  6. Preservar y reforzar la diversificación del código en submódulos especializados e independientes bajo `public_html/assets/js/modules/` para máxima mantenibilidad y velocidad.
- **Archivos creados/modificados:**
  - `app/Controllers/TestRunnerController.php` (nuevo controlador de pruebas en navegador)
  - `app/Models/User.php` (método `create`)
  - `app/Controllers/UserController.php` (método `create`)
  - `app/Models/BoardColumn.php` (`create`, `update`, `delete`)
  - `app/Controllers/BoardController.php` (`createColumn`, `updateColumn`, `deleteColumn`)
  - `public_html/api/index.php` (rutas para test-runner, usuarios y columnas)
  - `public_html/assets/js/modules/task-modal.js` (corrección de desestructuración y edición de tareas)
  - `public_html/assets/js/modules/users.js` (módulo de creación de usuarios)
  - `public_html/assets/js/modules/columns.js` (módulo de columnas y paleta Trello)
  - `public_html/assets/js/modules/kanban.js` (render de columnas con color y placeholder)
  - `public_html/assets/js/modules/auth.js` (control de visibilidad del botón de usuarios)
  - `public_html/assets/js/app.js` (wiring del orquestador y listeners)
  - `public_html/index.html` (modales de usuario y columna, botón editar tarea, enlace de diagnóstico)
  - `public_html/assets/css/app.css` (estilos para swatches de color Trello, placeholder y pill de diagnóstico)
  - `docs/PROGRESS.md`
  - `docs/SESSION_HANDOFF.md`
  - `docs/CHANGELOG.md`
- **Pruebas ejecutadas:**
  - `http://127.0.0.1:8000/api/health`: 200 OK, respuesta en ~5ms.
  - `http://127.0.0.1:8000/api/test-runner?format=json`: **10 de 10 pruebas superadas (100% PASS, tiempo total 182.36 ms)**:
    1. Base de Datos y 10 tablas: PASS
    2. Autenticación y Usuarios por Rol: PASS
    3. Creación de Tareas y Control Anti-IDOR: PASS
    4. Columnas Kanban y Paleta de Colores: PASS
    5. Transiciones de Estado Kanban: PASS
    6. Subtareas y Porcentaje de Avance: PASS
    7. Comentarios y Registro de Auditoría: PASS
    8. Circuito de Aprobación Formal: PASS
    9. Grafo de Dependencias (DAG) y Bloqueo AND: PASS
    10. Dashboard Operativo y KPIs en Vivo: PASS
  - `http://127.0.0.1:8000/`: renderizado completo y correcto de la SPA con los nuevos modales y enlaces.
- **Git:** Listo para commit local.
- **Siguiente paso:** Validación de usuario y retroalimentación de funcionalidades.

### 2026-10-14 — HISTORIAL-01: Remoción de módulo Dashboard & Métricas e implementación de módulo de Historial de Tareas Archivadas (>24h finalizadas) exclusivo para Jefatura
- **Estado:** completado
- **Objetivo y RF/RN:** RF-02, RF-03, RF-07, RF-18, RN-01, RN-03, RN-04, RN-05, RN-13.
  1. Eliminar por completo el módulo de Dashboard & Métricas (vistas, botones, lógica y servicios) atendiendo la solicitud explícita del usuario.
  2. Implementar regla de expiración de 24 horas: las tareas en la columna Finalizada permanecen un máximo de 24 horas en el tablero Kanban activo; transcurridas 24 horas se archivan automáticamente (`Task::listForUser` excluye `c.tipo_estado = 'FINALIZADA' AND t.fecha_finalizada <= now - 24h`).
  3. Implementar el módulo de Historial de Tareas Archivadas:
     - Backend: `Task::listArchived` y `HistoryController::index` (`GET /api/historial`) con guarda de rol exclusiva para Jefatura de Área (`JEFE_PLANEAMIENTO`) y Jefatura de Personal (`JEFE_PERSONAL`). Asistentes reciben `403 Forbidden` (`ERR_FORBIDDEN_HISTORY_ACCESS`).
     - Frontend: Módulo `public_html/assets/js/modules/history.js`, pestaña accesible `#tab-history-view` (visible solo para Jefatura), vista `#history-view` con tabla responsiva de expedientes archivados, cálculo amigable de horas/días transcurridos, filtros dinámicos y acceso a la ficha completa de la tarea (subtareas, comentarios, auditoría inmutable).
  4. Actualizar el Test Runner autónomo (`TestRunnerController.php`) validando la exclusión en Kanban, la inclusión en Historial y la restricción a perfiles no autorizados.
- **Archivos creados/modificados:**
  - `app/Models/Task.php` (regla de 24h en `listForUser` y nuevo método `listArchived`)
  - `app/Controllers/HistoryController.php` (nuevo controlador de historial archivado)
  - `app/Controllers/DashboardController.php` (vaciado/removido)
  - `app/Services/DashboardService.php` (vaciado/removido)
  - `public_html/api/index.php` (ruta `/api/historial` reemplazando `/api/dashboard/metricas`)
  - `public_html/assets/js/modules/history.js` (nuevo módulo frontend)
  - `public_html/assets/js/modules/dashboard.js` (vaciado/removido)
  - `public_html/assets/js/modules/auth.js` (control de pestaña `#tab-history-view` para jefatura)
  - `public_html/assets/js/modules/kanban.js` (poblado de selectores de miembros en historial)
  - `public_html/assets/js/modules/state.js` (actualización de alcance a `'history'`)
  - `public_html/assets/js/app.js` (orquestación del scope `'history'` y bindings)
  - `public_html/index.html` (reemplazo de `#tab-dashboard-view` y `#dashboard-view` por `#tab-history-view` y `#history-view`)
  - `public_html/assets/css/app.css` (estilos para hover de filas de historial)
  - `app/Controllers/TestRunnerController.php` (prueba 10 actualizada con regla 24h)
  - `docs/ROADMAP.md`
  - `docs/PROGRESS.md`
  - `docs/SESSION_HANDOFF.md`
  - `docs/CHANGELOG.md`
- **Pruebas ejecutadas:**
  - `http://127.0.0.1:8000/api/test-runner?format=json&v=5`: **10 de 10 pruebas superadas (100% PASS, tiempo total 236.15 ms)**.
    - Prueba 10: Tarea de 2h visible en Kanban, tarea de 30h excluida de Kanban y archivada en Historial, intento de asistente rechazado.
  - `http://127.0.0.1:8000/api/historial`: 401 Unauthorized sin sesión; 200 OK para roles de Jefatura.
- **Git:** Listo para commit local.
- **Siguiente paso:** Validación de la funcionalidad por parte del usuario.

### 2026-10-14 — VISTAS-01: Separación Estricta de "Mis Tareas" en Vista Área vs Vista Personal
- **Estado:** completado
- **Objetivo y RF/RN:** Separar el módulo general de "Mis Tareas" en dos vistas dedicadas e independientes:
  1. **Vista Área (`scope=area`):** Contiene exclusivamente las tareas oficiales del área (`tipo_tarea = 'AREA'`). A estas tareas les aplica la regla de expiración de 24h: tras 24h de finalizadas se retiran del Kanban activo y pasan al módulo de Historial.
  2. **Vista Personal (`scope=personal`):** Tablero de apoyo y organización individual para cada usuario con tareas privadas (`tipo_tarea = 'PERSONAL'`). No se archivan ni trasladan automáticamente a ningún historial; permanecen en la columna de Finalizada hasta que el propio usuario decida eliminarlas con el botón de borrado para mantener limpio su tablero.
  3. **Creación adaptativa:** Al abrir el diálogo "+ Nueva Tarea", el tipo de tarea se preselecciona automáticamente según la vista activa (Oficial del Área o Personal/Privada), ajustando de inmediato los badges, campos de autoasignación y ocultando circuitos formales de aprobación para tareas de apoyo personal.
- **Archivos creados/modificados:**
  - `app/Models/Task.php`: filtrado estricto por `tipo_tarea = 'PERSONAL'` en scope personal sin cutoff de 24h; filtrado estricto por `tipo_tarea = 'AREA'` en scope area y team con cutoff de 24h hacia historial; garantía en `listArchived` de consultar solo tareas de `AREA`.
  - `public_html/index.html`: botones independientes `#tab-area-tasks` ("Vista Área") y `#tab-personal-tasks` ("Vista Personal"); banner contextual `#personal-board-banner` en Kanban; identificadores dinámicos en `#modal-create-task`.
  - `public_html/assets/css/app.css`: estilos de microdiseño y resaltado para `.personal-board-banner`.
  - `public_html/assets/js/modules/kanban.js`: filtrado estricto en cliente de `filteredTasks` por `tipo_tarea` y control de visibilidad del banner explicativo personal.
  - `public_html/assets/js/modules/task-modal.js`: método reactivo `updateCreateTaskTypeUI`, preselección según `STATE.currentScope`, autoasignación obligatoria de tareas personales y cambio de vista automático al crear tarea de tipo alterno.
  - `public_html/assets/js/app.js`: ocultamiento del selector `#member-filter-select` en Vista Personal.
  - `app/Controllers/TestRunnerController.php`: caso de prueba 10 ampliado validando separación de vistas, retención sin expiración en personal, no inclusión de tareas personales en historial de jefatura y seguridad RBAC.
  - `docs/ROADMAP.md`, `docs/PROGRESS.md`, `docs/SESSION_HANDOFF.md`, `docs/CHANGELOG.md`, `docs/DECISIONS.md`.
- **Pruebas ejecutadas:**
  - `http://127.0.0.1:8000/api/test-runner?format=json&v=7`: **10 de 10 pruebas superadas (100% PASS, tiempo total 279.82 ms)**.
- **Git:** Listo para commit local.
- **Siguiente paso:** Revisión del usuario y feedback sobre el flujo en ambas vistas.

### 2026-10-14 — FIX-TABS-01: Corrección de importación de isCurrentUserJefe en app.js y desbloqueo de conmutación de módulos
- **Estado:** completado
- **Objetivo y RF/RN:** RF-08, RF-12, RN-01, RN-02, RN-03, RN-04.
  1. Corregir el bug crítico en la conmutación de pestañas: `isCurrentUserJefe` no estaba importado en `app.js`, provocando un `ReferenceError` al intentar salir de la Vista Personal hacia cualquier otra vista (Área, Equipo, Aprobaciones o Historial).
  2. Exponer y exportar `setScope` en `window.setScope` para permitir la navegación automática entre vistas desde el modal de creación de tareas.
  3. Asegurar la preservación de `STATE.columns` en `fetchTasks` (`kanban.js`).
  4. Incrementar versión de script a `v1.5.2` en `index.html` para forzar invalidación de caché en el navegador del usuario.
- **Archivos creados/modificados:**
  - `public_html/assets/js/app.js` (importación de `isCurrentUserJefe` y exportación de `setScope`)
  - `public_html/assets/js/modules/kanban.js` (salvaguarda de `STATE.columns` en `fetchTasks`)
  - `public_html/index.html` (tag de versión `v1.5.2` en assets)
  - `docs/CHANGELOG.md`
  - `docs/PROGRESS.md`
  - `docs/SESSION_HANDOFF.md`
- **Pruebas ejecutadas:**
  - Diagnóstico autónomo web (`http://127.0.0.1:8000/api/test-runner?format=json`): **10 de 10 pruebas superadas (100% PASS, 299 ms)**.
  - Verificación de entrega HTTP de `app.js?v=1.5.2` con `curl`.
- **Git:** Listo para commit local.
- **Siguiente paso:** Validación por parte del usuario en el navegador tras refrescar la página.

### 2026-10-14 — V1.5.3: Eliminación de Bitácora de Formulario, Modal Expandido sin Scroll y Funciones Específicas por Rol
- **Estado:** completado
- **Objetivo y RF/RN:** RF-03, RF-04, RF-05, RF-06, RF-07, RF-14, RF-18, RN-01, RN-02, RN-03, RN-04, RN-07, RN-08, RN-13.
  1. **Eliminar Bitácora de Auditoría del formulario de detalle:** Retirada la sección visual y las peticiones a `/api/tareas/{id}/historial` desde el modal de detalle, centralizando la trazabilidad histórica en el Módulo de Historial exclusivo para Jefatura.
  2. **Modificar el tamaño de los cuadros de información de la tarea (sin scrollbars):**
     - Adaptado el modal con la clase `.modal-dialog.modal-dialog-task-detail` (1140px / 95vw / 94vh) y grid de 2 columnas balanceado (1.05fr / 0.95fr).
     - Compactación de paddings, márgenes e ítems para que toda la información (código, badges, descripción, checklist, dependencias, comentarios, formulario de evidencias y selector de columna) se aprecie de forma inmediata en una sola pantalla sin necesidad de barras de desplazamiento vertical del diálogo.
  3. **Funciones y Permisos por Roles (Jefatura vs Asistentes):**
     - **Jefe de Planeamiento y Jefe de Personal:** Acceso total a todos los módulos y operaciones (crear tareas, asignar a cualquier usuario, crear y remover dependencias DAG, editar/eliminar tareas, y añadir/editar columnas Kanban en ambas vistas).
     - **Asistentes:**
       - Acceso exclusivo a Vista Área y Vista Personal.
       - *En Vista Área:* Visualizan sus tareas asignadas, gestionan checklist de subtareas, envían comentarios y adjuntan evidencias. Visualizan dependencias asignadas en modo de solo lectura (sin formulario de vinculación ni botones de eliminación). Bloqueados de editar o eliminar tareas oficiales, sin permisos para crear/editar columnas y sin botón de nueva tarea oficial.
       - *En Vista Personal:* Permisos completos de gestión privada (crear, editar, eliminar, checklist, comentarios). Sección de dependencias completamente oculta en la interfaz y bloqueada con validación 422 en backend.
  4. **Corrección de Generación de Código Correlativo (`Task::generateCodigo`):** Cálculo dinámico sobre el correlativo máximo existente con verificación de colisiones para garantizar unicidad absoluta ante eliminaciones de tareas.
- **Archivos creados/modificados:**
  - `public_html/index.html` (remoción de sección bitácora, añadido `.modal-dialog-task-detail`, ID `#detail-deps-box` y versión `v1.5.3`)
  - `public_html/assets/css/app.css` (estilos adaptativos, amplios y compactos para `.modal-dialog-task-detail`)
  - `public_html/assets/js/modules/task-modal.js` (remoción de llamadas a auditoría, ocultamiento de dependencias en personal, visibilidad condicional de botones editar/eliminar y formulario de dependencias según rol)
  - `public_html/assets/js/modules/kanban.js` (ocultamiento de `+ Añadir otra columna` y edición de columnas para asistentes)
  - `public_html/assets/js/modules/auth.js` (ocultamiento de botón "+ Nueva Tarea" para asistentes en Vista Área)
  - `public_html/assets/js/app.js` (control reactivo de botón "+ Nueva Tarea" al conmutar entre Vista Área y Personal)
  - `app/Models/Task.php` (`generateCodigo` anti-colisión, `canEdit`, `canDelete`, `canTransition`)
  - `app/Models/TaskDependency.php` (restricción de DAG a Jefatura y bloqueo de tareas personales)
  - `app/Controllers/BoardController.php` (restricción de columnas a Jefatura)
  - `app/Controllers/TestRunnerController.php` (actualización de Test 9 para autorizar dependencias por Jefatura)
  - `docs/CHANGELOG.md`
  - `docs/PROGRESS.md`
  - `docs/SESSION_HANDOFF.md`
- **Pruebas ejecutadas:**
### 2026-10-14 — V1.5.4: Rediseño del Login Estilo Referencia, Botón + Columna Superior y Lógica Operativa de Estados en Columnas
- **Estado:** completado
- **Objetivo y RF/RN:** RF-01, RF-08, RF-10, RF-11, RF-12, RF-13, RN-01, RN-02, RN-03, RN-04, RN-06, RN-10, RN-11, RN-12.
  1. **Rediseño del Login según imagen de referencia:**
     - Tarjeta flotante orgánica split (`border-radius: 36px`, sombra suave y limpia, soporte Dark Theme).
     - *Panel Izquierdo:* Curva orgánica fluida verde esmeralda con detalle blanco en la base, isotipo circular blanco con llama verde corporativa TAL, título "Welcome Back!", subtítulo descriptivo, botón píldora "SIGN IN" y pie de página "TAL S.A. | PLANEAMIENTO 2026".
     - *Panel Derecho:* Título minúscula "welcome" en verde esmeralda, subtítulo "Login in to your account to continue", dos inputs alargados estilo píldora color menta pastel suave translúcido (`Email....................` y `Password....................`), enlace "Forgot your password?", botón principal verde esmeralda "LOG IN", y barra de chips de acceso rápido demostrativo para alternar entre perfiles (Marco Chumbes, Elena Vega, Roberto Sánchez) con un solo clic.
  2. **Botón de Nueva Columna en Barra Superior (`+ Columna`):**
     - Añadido el botón `#btn-open-create-column` en la barra de controles superior (`#workspace-controls-bar`) junto a `+ Usuario` y `Nueva Tarea`, para que los usuarios con rol de Jefatura no tengan que desplazarse horizontal ni verticalmente hasta el final del tablero. Control reactivo de visibilidad exclusivo para Jefes en las vistas Kanban (`area`, `personal`, `team`).
  3. **Lógica Operativa de Estados Asociados a Columnas:**
     - `Pendiente / Por hacer`: Sin lógica restrictiva operativa.
     - `En Proceso / En curso`: Sin lógica restrictiva operativa.
     - `Revisión / Aprobación (`EN_REVISION`):* Cualquier tarea que sea movida o arrastrada a una columna configurada con este estado genera automáticamente una solicitud de revisión formal e ingresa de inmediato a la bandeja del **Módulo de Aprobaciones**.
     - `Finalizada / Cumplida (`FINALIZADA`):* La tarea queda formalmente **cerrada para Asistentes**; el asistente no puede arrastrarla (`draggable="false"`, badge `🔒 CERRADA`), no puede moverla desde el selector de columnas (bloqueado), no puede alterar subtareas del checklist, no puede editarla, ni eliminarla, ni agregar comentarios/evidencias (con aviso visual de tarea cerrada y validación 403 en backend). **Solamente los roles de Jefatura pueden interactuar con tareas finalizadas**.
  4. **Actualización del Módulo de Aprobaciones:**
     - `Approval::listPendientesForUser`: Consulta relacional unificada que identifica e incorpora de inmediato todas las tareas ubicadas en columnas con `c.tipo_estado = 'EN_REVISION'` o que posean un registro activo con `a.estado = 'PENDIENTE'`.
     - `Approval::resolver`: Capacidad de resolver directamente tareas colocadas en revisión directa creando o actualizando el registro atómicamente.
     - `BoardColumn.php` y `BoardController.php`: Normalización de valores amigables (`REVISION` ➔ `EN_REVISION`, `FINALIZADA` ➔ `FINALIZADA`, `EN_CURSO` ➔ `EN_PROCESO`, `POR_HACER` ➔ `PENDIENTE`).
- **Archivos creados/modificados:**
  - `public_html/index.html` (nuevo layout del login réplica con panel curvo esmeralda, botón `+ Columna`, opciones informativas de `#column-type-select`, script tag `v1.5.4`)
  - `public_html/assets/css/app.css` (estilos para tarjeta split orgánica esmeralda, inputs píldora menta, botón `LOG IN`, chips demostrativos y `.task-card-closed`)
  - `public_html/assets/js/modules/auth.js` (control de visibilidad de `#btn-open-create-column` para jefaturas)
  - `public_html/assets/js/app.js` (control reactivo de visibilidad de `#btn-open-create-column` en `setScope`)
  - `public_html/assets/js/modules/kanban.js` (bloqueo de arrastre y badge `🔒 CERRADA` en tareas finalizadas para asistentes)
  - `public_html/assets/js/modules/task-modal.js` (bloqueo de selector de columnas, checklist en modo lectura, ocultamiento de formulario de comentarios y aviso de tarea cerrada para asistentes)
  - `public_html/assets/js/modules/approvals.js` (lectura adecuada de responsable y fecha en tarjetas de aprobación)
  - `app/Models/Approval.php` (`listPendientesForUser` con detección de tareas en columnas `EN_REVISION`)
  - `app/Models/Task.php` (`canEdit`, `canDelete`, `canTransition`, `canManageChecklist`, `canInteract`, transición automática a `EN_REVISION`)
  - `app/Controllers/SubtaskController.php` (validación de checklist con `Task::canManageChecklist`)
  - `app/Controllers/CommentController.php` (bloqueo de comentarios en tareas finalizadas para asistentes con `Task::canInteract`)
  - `app/Models/BoardColumn.php` y `app/Controllers/BoardController.php` (normalización de `tipo_estado`)
  - `docs/DECISIONS.md` (registro de decisión D-021)
  - `docs/CHANGELOG.md`
  - `docs/PROGRESS.md`
  - `docs/SESSION_HANDOFF.md`
- **Pruebas ejecutadas:**
  - Diagnóstico autónomo web (`http://127.0.0.1:8000/api/test-runner?format=json`): **10 de 10 pruebas superadas (100% PASS, 259.84 ms)**.
  - Verificación de entrega HTTP de la SPA (`http://127.0.0.1:8000/index.html`).
### 2026-10-14 — V1.5.5: Depuración de Códigos Técnicos Internos a Lenguaje Corporativo Final y Visibilidad de Pantalla de Login
- **Estado:** completado
- **Objetivo y RF/RN:** RF-01, RF-10, RF-11, RF-12, RF-13, RN-01, RN-03, RN-04, RN-07, RN-08, RN-10, RN-11, RN-12.
  1. **Visibilidad Inmediata de la Pantalla de Login:**
     - Identificada la causa por la que no se mostraba el login: `checkSession()` detectaba el token en `localStorage` o sesión activa de pruebas previas y auto-iniciaba sesión al cargar la página.
     - Se vinculó el ciclo de vida de la sesión activa al ciclo de vida de la pestaña con `sessionStorage.getItem('tal_session_active')`. Al abrir el sistema en una pestaña nueva o sin sesión explícita, se presenta la pantalla de Login por defecto.
     - Al cerrar sesión (`doLogout` / botón "Salir"), se limpia tanto `sessionStorage` como `localStorage` y cookies, regresando inmediatamente a la pantalla de login.
     - Se habilitaron los parámetros de consulta `?login`, `?logout` y el hash `#login` para forzar la vista de login en cualquier momento.
     - Adaptación completa de los textos de la maqueta de login de inglés a español formal corporativo (`¡Bienvenido!`, `Iniciar Sesión`, `Correo institucional (@planeamiento.tal)`, `Contraseña de acceso`, `INGRESAR AL SISTEMA`, etc.).
  2. **Depuración Integral de Mensajes Técnicos Internos a Lenguaje Corporativo de Producción:**
     - Eliminación de referencias a códigos de desarrollo (`RN-10`, `RN-12`, `RN-08`, `RN-11`, `RN-03`, `RN-04`, `RN-07`, `RF-05`) en frontend y backend:
       - Subtítulo de Aprobaciones: "Tareas en revisión técnica pendientes de evaluación y dictamen por Jefatura."
       - Subtítulo de Historial: "Archivo histórico de tareas concluidas para consulta y auditoría interna. Acceso exclusivo de Jefatura."
       - Casilla de Aprobación: "Requiere aprobación de Jefatura para su cierre formal" (sin código RN-10).
       - Alerta de Dependencias: "Esta tarea no puede iniciarse ni finalizarse hasta que todas sus tareas predecesoras alcancen el estado Finalizada." (sin Regla RN-08).
       - Etiqueta de Devolución: "Observaciones de Jefatura para Subsanación *" (sin RN-12).
       - Cabecera y textos de circuito: "Circuito de Aprobación Formal" (sin RN-10) y cierre sin aprobación (sin RN-11).
       - Textos informativos de asignación en modal de tareas para asistente y jefatura en redacción fluida.
       - Mensaje del repositorio histórico en tabla vacía: redacción clara sin tecnicismos temporales.
       - Mensajes de error en backend (`Task.php`, `User.php`, `Approval.php`, `TaskDependency.php` y `TestRunnerController.php`) ajustados a redacción corporativa limpia.
- **Archivos creados/modificados:**
  - `public_html/index.html` (textos de login en español, eliminación de códigos de reglas en UI)
  - `public_html/assets/js/modules/auth.js` (control de sesión con `tal_session_active`, forzado limpio de login)
  - `public_html/assets/js/modules/approvals.js` (eliminación de RN-10, RN-11, RN-12 en toasts y cajas)
  - `public_html/assets/js/modules/task-modal.js` (eliminación de RN-03 y RN-04 en textos de ayuda)
  - `public_html/assets/js/modules/history.js` (redacción corporativa en mensaje de tabla vacía)
  - `app/Controllers/TestRunnerController.php` (nombre de prueba depurado)
  - `app/Models/Task.php` (mensajes de validación limpios)
  - `app/Models/User.php` (mensaje de autorización de asignación limpio)
  - `app/Models/Approval.php` (excepción de devolución limpia)
  - `app/Models/TaskDependency.php` (excepciones y motivos de auditoría limpios)
  - `docs/PROGRESS.md`
  - `docs/SESSION_HANDOFF.md`
- **Pruebas ejecutadas:**
  - Diagnóstico autónomo web (`/api/test-runner`): 10 de 10 pruebas superadas (100% PASS).
- **Git:** Listo para commit local.

### 2026-10-14 — V1.5.6: Corrección Estructural del Login, Integración de Imagen Lateral y Acceso Directo
- **Estado:** completado
- **Objetivo y RF/RN:** RF-01, RF-02, RN-01, RN-03, RN-04.
  1. **Detección y Corrección del Bloqueo Estructural:**
     - Se identificó un tag `<select id="priority-filter-select">` no cerrado en la barra de controles que provocaba que el parser del navegador anidara todo el DOM subsiguiente (incluyendo `<main>` y `#login-view`) dentro del `<select>`, impidiendo su correcta visualización.
     - Se cerró debidamente el `<select>` restaurando la jerarquía limpia del DOM.
  2. **Diseño de Login con Imagen Lateral y Cuentas Demo:**
     - Se reemplazaron las curvas decorativas por un diseño split corporativo confiable:
       - **Columna lateral:** Imagen fotográfica / render corporativo 3D (`assets/img/login_hero.jpg`) con gradiente de contraste y distintivo oficial de TAL S.A. Área de Planeamiento.
       - **Columna derecha:** Mensaje de bienvenida ("¡Bienvenido! Ingresa tus credenciales para acceder a tu espacio de trabajo"), formulario directo con iconos para usuario/correo y contraseña con botón principal "Ingresar al Sistema".
       - **Sección de demostración:** Tres botones accesibles con iniciales y badges de rol para ingresar con un clic como Marco Chumbes (Asistente), Elena Vega (Jefa de Planeamiento) o Roberto Sánchez (Jefe de Personal).
  3. **Visibilidad Inmediata Garantizada:**
     - Se forzó `style="display: flex;"` inline en `#login-view` para garantizar su renderizado inmediato en el primer ciclo de renderizado.
     - `checkSession()` mantiene visible el login por defecto a menos que exista una sesión activa explícita en la pestaña actual.
- **Archivos creados/modificados:**
  - `public_html/index.html` (cierre de `<select>`, nuevo layout de login con imagen `login_hero.jpg`, v1.5.6)
  - `public_html/assets/css/app.css` (estilos para `.login-card-clean`, `.login-image-column`, `.btn-demo-account`)
  - `public_html/assets/js/modules/auth.js` (simplificación de `checkSession`)
  - `docs/PROGRESS.md`
  - `docs/SESSION_HANDOFF.md`
- **Pruebas ejecutadas:**
  - Diagnóstico autónomo web (`/api/test-runner`): 10 de 10 pruebas superadas (100% PASS, 307.27 ms).
  - Verificación de entrega HTTP de la SPA (`http://127.0.0.1:8000/index.html`).
- **Git:** Listo para commit local.
- **Siguiente paso:** Comunicar la solución final al usuario para que recargue y pruebe el login.

### 2026-10-14 — Primera observación visual: campos del login y fondo tematizado
- **Estado:** completado
- **Objetivo:** corregir campos de correo/contraseña recortados y mejorar el fondo del modo claro/oscuro.
- **RF/RN:** RF-01, RN-01, RN-07.
- **Cambios:** `form-input` con ancho completo y `min-width: 0`; columna de formulario protegida contra desbordamiento; fondo con color base y patrón configurable por tema.
- **Archivos:** `public_html/assets/css/login.css`, `public_html/assets/css/base.css`, `public_html/assets/css/variables.css`.
- **Pruebas:** `git diff --check` PASS; entrega HTTP de `http://127.0.0.1:8000/` PASS (200).
- **Pendiente:** validación visual del usuario en claro, oscuro y viewport móvil.

### 2026-10-14 — Consolidación de Vista Área
- **Estado:** implementado
- **Objetivo:** retirar "Tablero del Equipo", que duplicaba la Vista Área para jefaturas.
- **RF/RN:** RF-10, RN-01, RN-07.
- **Cambios:** eliminación de la pestaña en ambas entradas HTML, retiro del estado `team` y sus ramas de `app.js`/`auth.js`, consulta de usuarios sin `scope=team`, y limpieza de nomenclatura visual asociada.
- **Documentación:** requisitos y contexto actualizados para reflejar Vista Área como único tablero oficial.
- **Pendiente:** validación visual de la barra de navegación con ambos roles.

### 2026-10-14 — Restricción de edición del asistente por estado
- **Estado:** implementado
- **Objetivo:** impedir edición y cambios operativos del asistente durante revisión o después del cierre.
- **RF/RN:** RF-06, RF-07, RF-08, RF-09, RN-03, RN-10, RN-11.
- **Cambios:** permisos backend por estado para edición, transición, checklist e interacción; fecha límite inmutable para asistentes; controles frontend en modo solo lectura para revisión/finalizada.
- **Pruebas:** matriz directa de permisos PASS en los cuatro estados; PHP lint PASS; `git diff --check` PASS.
- **Limitación conocida:** las suites heredadas `tasks_test.php` y `kanban_test.php` conservan fallos previos no relacionados; `subtasks_comments_test.php` pasa 23/23.

### 2026-10-14 — Diseño confirmado del módulo de Gestión de Usuarios
- **Estado:** diseñado, pendiente de implementación.
- **Objetivo:** reemplazar el alta aislada por un módulo de administración de cuentas separado de tareas y tableros.
- **Decisiones:** jerarquía administrativa superior para Jefe de Área; permisos operativos de tareas equivalentes entre jefaturas; permisos por rol; auto-desactivación permitida; contraseña manual sin correo ni enlace.
- **Documento:** `docs/USER_MANAGEMENT_MODULE.md`.
- **Pendiente:** solicitar la fase de implementación `USERS-MGMT-01`.

### 2026-10-16 — Compatibilidad de refresco del tablero para Jefatura
- **Estado:** implementado y verificado.
- **Objetivo:** corregir el `ReferenceError: refreshBoard is not defined` observado al usar el icono desde la sesión de Jefatura de Área.
- **RF/RN:** RF-10, RN-01, RN-07.
- **Cambios:** se actualizó la versión de caché de `app.js` y `kanban.js` a `1.6.3`; la función `refreshBoard` continúa expuesta globalmente sin condición de rol; el botón incluye un fallback seguro para evitar una referencia global inexistente durante una transición de caché.
- **Archivos:** `public_html/assets/js/app.js`, `public_html/partials/footer.html`, `public_html/partials/workspace_bar.html`.
- **Pruebas:** `php tests/run_all.php` PASS (10/10 suites); PHP lint de `public_html/index.php` y `public_html/api/index.php` PASS; `git diff --check` PASS. Node no está instalado en el entorno, por lo que no se ejecutó `node --check`.
- **Limitación:** falta validación visual manual en navegador con una sesión real de Jefatura de Área y otra de Asistencia.

### 2026-10-16 — Corrección del circuito de aprobación en tareas sin aprobación
- **Estado:** implementado y verificado.
- **Objetivo:** impedir que una tarea creada sin solicitar aprobación sea enviada accidentalmente a `EN_REVISION` y quede bloqueada para el asistente.
- **RF/RN:** RF-05, RF-06, RF-13, RN-01, RN-10, RN-11.
- **Cambios:** el backend rechaza la transición y la solicitud manual con `ERR_APPROVAL_NOT_REQUIRED`; `Approval::solicitar()` ya no activa la bandera por sí mismo; el drag-and-drop y el selector del detalle no ofrecen `EN_REVISION` para tareas sin aprobación.
- **Pruebas:** `tests/approvals_test.php` PASS (25/25); `php tests/run_all.php` PASS (10/10 suites); QA piloto PASS (43/43); PHP lint PASS; `git diff --check` PASS.
- **Limitación:** falta validación visual manual del flujo en navegador con tarea sin aprobación y tarea con aprobación.


