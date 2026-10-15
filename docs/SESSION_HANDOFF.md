# Traspaso entre sesiones (leer primero)

## Actualizacion 2026-10-15 - PROD-READY-01 implementado
- La entrega para Ferozo inicia sin cuentas, tareas, comentarios ni contrasenas de demostracion. `database/seeds.sql` solamente crea las cinco columnas operativas base.
- `public_html/index.html` fue eliminado para que no persista una interfaz antigua con credenciales demostrativas. `/index.html` e `/index.php` redirigen a `/` en el servidor local; Apache usa `index.php`.
- El primer usuario real se crea una sola vez en `/instalar.php`: activar temporalmente `INSTALLER_ENABLED=true`, definir un `INSTALL_TOKEN` de 32 o mas caracteres e ingresar el mismo token en el formulario. El servicio exige base vacia, contrasena de 12 o mas caracteres y crea el rol `JEFE_PLANEAMIENTO`.
- Al terminar, cambiar `INSTALLER_ENABLED=false`. El candado persistente y la existencia del primer usuario impiden una segunda ejecucion.
- Guia vigente: `docs/DEPLOYMENT_AND_PILOT_GUIDE.md`. No eliminar `storage/database.sqlite` local como parte de este despliegue.
- QA ejecutado: lint PHP PASS; instalacion inicial 7/7; gestion de usuarios 15/15; notificaciones 38/38; raiz local 200, instalador deshabilitado 404 y `/index.html` 302 a `/`.

## Actualizacion 2026-10-15 - ENTRYPOINT-01 implementado
- `public_html/index.php` es el unico punto de entrada navegable.
- La referencia historica a `public_html/index.html` queda superada: el archivo fue eliminado del paquete operativo en PROD-READY-01 y las rutas legadas redirigen a `/`.

## Actualizacion 2026-10-15 - PERF-01 implementado
- Se retiro la dependencia de Google Fonts del primer render; el frontend usa la pila tipografica nativa ya definida en tokens.
- Las ocho hojas CSS vigentes se cargan directamente y en paralelo desde `partials/head.html`; `app.css` ya no encadena importaciones.
- El hero del login fue sustituido por la imagen de usuario `fondo_img.jpg` de 2.18 MB, con `width`, `height`, `fetchpriority` y `decoding` declarados. Esta imagen es visualmente vigente, pero pesa mÃ¡s que la optimizada anterior y debe considerarse en la siguiente mediciÃ³n Lighthouse.
- La carga inicial de usuarios, columnas, tareas y conteo de aprobaciones se ejecuta en paralelo. El tablero reserva altura minima para reducir CLS durante el montaje.
- Apache y el servidor local entregan compresion/cache para recursos estaticos. Se versionaron hojas CSS y el modulo principal con `v=1.5.8`.
- QA: PHP lint PASS; notificaciones 38/38; usuarios 15/15; HTTP 200; `git diff --check` PASS. No se pudo ejecutar Lighthouse porque no hay Chrome/Node/Lighthouse instalado.
- Pendiente: repetir medicion Lighthouse real y verificar que el proveedor Ferozo conserve `mod_deflate`, `mod_expires` y `Cache-Control`.

## Actualización 2026-10-15 — UI-05 implementado
- El patrón del modo oscuro ahora replica la estructura del modo claro: puntos y diagonales a 24px, con mayor contraste índigo sobre el fondo oscuro.
- La hora de registro de la tarea ya no aparece en tarjetas Kanban, aprobaciones ni historial. Solo se muestra al abrir el detalle, en `Registrada`.
- Validación técnica ejecutada: lint PHP completo, notificaciones 38/38, usuarios 15/15, raíz/CSS local 200 y hora de registro limitada al detalle. Falta confirmación visual manual en ambos temas.

## Actualización 2026-10-15 — UI-04 implementado
- El selector de responsable de creación y edición dejó de mostrar cargo y texto de autoasignación; ahora presenta `Nombre (ROL)` con buscador, lista desplegable filtrable y selección por teclado.
- La selección mantiene un `<select>` interno con el ID del usuario, por lo que el contrato de creación/actualización no cambia.
- La hora de creación se muestra junto con la fecha en tarjetas Kanban, aprobaciones, historial y detalle de tarea. El formateador interpreta `created_at` como UTC y lo presenta en horario local `es-PE`.
- Validación ejecutada: lint PHP completo, notificaciones 38/38, usuarios 15/15, HTTP 200, markup actualizado y `git diff --check`. Node.js no está disponible para un parser JavaScript automático.
- Pendiente: validación manual con Ctrl + F5, crear una tarea, buscar responsables por nombre/rol, editar una tarea y confirmar fecha/hora.

## Actualización 2026-10-15 — UI-03 implementado
- Se retiró por completo el zoom visual interno equivalente al 80%; `.app-scale-shell` conserva únicamente el layout normal de la aplicación.
- El backdrop de los modales volvió a cubrir el viewport con `inset: 0`, sin `zoom: 0.8` ni dimensiones compensadas. Se conserva el ensamblado que deja los modales como hijos directos de `body`.
- Se mantienen la corrección del toast de creación y las mejoras de contraste del modo oscuro.
- Validación técnica ejecutada: lint PHP completo, notificaciones 38/38, usuarios 15/15, HTTP local 200, estructura HTML presente, toast corregido y ninguna regla activa de zoom.
- Siguiente paso: recargar con `Ctrl + F5` y comprobar tablero, Usuarios, aprobaciones, modal de tarea y ambos temas con el navegador al 100%.

## Actualización 2026-10-15 — UI-02 implementado
- Corregido el toast de creación: el backend responde la tarea en `data.tarea`, por lo que el frontend ya no intenta leer `data.codigo`. Si el API no entrega código, muestra `¡Tarea creada exitosamente!` sin interpolar `undefined`.
- La escala 80% dejó de aplicarse al `body`. `#app-scale-shell` contiene header, barra, vistas y footer; los modales quedan como hijos directos del `body` para que `position: fixed` se mida contra el viewport completo.
- El backdrop de modales compensa la escala con `125vw/125vh` y `zoom: 0.8`; esto evita que al abrir una tarea quede una franja sin sombrear.
- Verificación: HTTP 200 local, HTML ensamblado correctamente, lint PHP PASS, notificaciones 38/38 PASS, usuarios 15/15 PASS y casos de creación T02/T04/T05 PASS.
- Pendiente: validar manualmente con `Ctrl + F5` en navegador al 100%: crear tarea, abrir detalle, abrir devolución y editar usuario/columna. El zoom 80% del navegador se acumularía con la escala interna.

## Actualización 2026-10-15 — UI-01 implementado
- La página aplica una escala visual predeterminada equivalente al 80% mediante `.app-scale-shell { zoom: 0.8; }` dentro de `@supports`; los modales quedan fuera de ese contexto para que los elementos interactivos fixed conserven el viewport completo.
- Se auditó el modo oscuro y se corrigieron contrastes en botones de éxito/peligro, enlaces primarios, textos secundarios, placeholders, alertas de devolución, badges dinámicos, historial, notificaciones, tarjetas vencidas y foco visible. Los tokens semánticos separan color de texto, fondo, borde y fondo sólido de botón.
- Verificación: `/` y `assets/css/app.css` responden HTTP 200; `php tests/notifications_automation_test.php` 38/38 PASS; `php tests/user_management_test.php` 15/15 PASS; lint PHP completo PASS; `git diff --check` PASS.
- La batería integral sigue reportando fallos heredados no causados por este avance: permisos esperados antiguos en `tasks_test.php`/`kanban_test.php`, ciclos en `dependencies_test.php` y llamadas a `DashboardService::getMetrics()` retirado en dashboard/QA.
- Pendiente: recarga visual manual con `Ctrl + F5` a 100% de zoom del navegador; validar tablero, Usuarios, modal, notificaciones y tema oscuro. No usar 80% del navegador durante esta comprobación para evitar doble escala.

## Actualización 2026-10-15 — MVP-08-NOTIF-01 completado
- El enrutamiento de notificaciones fue verificado y ajustado por rol. `APROBACION_SOLICITADA` llega a todas las cuentas activas de `JEFE_PLANEAMIENTO` y `JEFE_PERSONAL`; no llega a Asistentes.
- El motor filtra cuentas inactivas y roles obsoletos antes de persistir cualquier aviso. `Approval::solicitar` rechaza un Asistente como revisor.
- La matriz completa quedó cubierta con `tests/notifications_automation_test.php`: 38/38 PASS, incluyendo exclusiones por rol, anti-auto-notificación e idempotencia.
- Siguiente verificación recomendada: ingresar en local con una cuenta de cada rol y provocar una asignación, solicitud de aprobación y comentario para confirmar visualmente la campana.

## Actualización 2026-10-15 — USERS-MGMT-UI-01 completado
- La Vista de Gestión de Usuarios fue ajustada para corregir las desproporciones observadas: está dentro de `<main class="main-content">`, ocupa el ancho disponible, alinea cabecera, filtros y tabla; la cabecera se compactó; el buscador recibe el espacio principal; y las acciones tienen una columna fija para evitar compresión de nombres y correos.
- La vista responde sin perder legibilidad: filtros en dos filas a ancho medio, en una columna a ancho móvil y tabla con desplazamiento horizontal controlado cuando corresponde.
- Se corrigió la causa del desplazamiento al hacer zoom: el ensamblador cargaba Usuarios después del cierre de `<main>` en el partial de Historial. Validación ejecutada: `php tests/user_management_test.php` con 15/15 PASS, `git diff --check` PASS y entrega local HTTP 200. Pendiente: confirmación visual manual del usuario en escritorio y móvil.

## Actualización 2026-10-14 — USERS-MGMT-01 completado
- Jefatura dispone de la pestaña **Gestión de Usuarios** para listar, filtrar, crear, editar, asignar rol, cambiar contraseña y activar/desactivar cuentas. La API administrativa está aislada en `/api/gestion-usuarios/*`; el directorio `/api/usuarios` permanece para tareas.
- Regla jerárquica confirmada y aplicada en servidor: ambos jefes administran cuentas, pero `JEFE_PERSONAL` no puede desactivar una cuenta con rol `JEFE_PLANEAMIENTO`. El intento devuelve `403 ERR_HIERARCHY_PROTECTED`; el Jefe de Área sí puede desactivar cualquier cuenta, incluida la propia.
- Seguridad del módulo: no hay contraseñas en respuestas ni auditoría; cambio de contraseña, rol o desactivación invalidan sesiones de la cuenta afectada. Pruebas: `php tests/user_management_test.php` (15/15 PASS) y `php tests/users_test.php` (23/23 PASS).
- Pendiente recomendado: validar visualmente el módulo con ambas cuentas de Jefatura en `http://127.0.0.1:8000`.

## Estado actual verificable
- CICLO-01 completado: el cierre en `FINALIZADA` conserva la tarea 24 horas en Kanban; Jefatura puede forzar el archivo inmediato. Las tareas vencidas muestran una franja roja e icono, y el historial indica si terminaron en plazo. La reapertura de un cierre queda limitada en backend a Jefatura y a una columna `EN_PROCESO`.
- Corrección de carga Kanban: `public_html/.htaccess` establece `DirectoryIndex index.php`; la raíz del sitio utiliza los partials actuales y no la copia histórica `index.html`.
- Se corrigió `isJefe` en `public_html/assets/js/modules/kanban.js`; el error impedía agregar columnas al DOM aunque la API respondiera correctamente.
- VISTAS-03 completado: un único tablero de Área. La Vista Personal dejó de estar expuesta por UI y API; los datos personales históricos no se eliminaron.
- Comentarios/evidencias: se muestra el primer nombre del autor; archivos permitidos se almacenan bajo `storage/uploads` y se descargan por endpoint autorizado. QA backend: 23/23 en `subtasks_comments_test.php` y 24/24 en `tasks_test.php`.
- LOGIN-UI-01 implementado: imagen corporativa con overlay de contraste, textos institucionales legibles, espaciado revisado y layout responsive. Pendiente: validación visual final con el navegador en escritorio y móvil.
- VISTAS-02 implementado: borrado masivo seguro de tareas personales, candado en columna finalizada y tareas finalizadas en modo solo lectura. QA: lint PHP, auth 19/19 y diagnostico web 10/10 PASS; `tasks_test.php` mantiene 4 fallos heredados de alcance y Node.js no esta instalado. Pendiente: confirmar visualmente el boton y la explicacion del candado en navegador.
- Repositorio Git con arquitectura modular completa y operativa en desarrollo local (`PHP 8.4.25` + `SQLite`).
- **Fase 1.5+ / V1.5.7 COMPLETADA (Modularización de Archivos Extensos, Aislamiento Estricto del Login y Skill):**
  - **Modularización HTML:** Descompuesto el monolito `index.html` en 9 partials en `public_html/partials/` (`head.html`, `header.html`, `workspace_bar.html`, `login.html`, `kanban.html`, `approvals.html`, `history.html`, `modals.html`, `footer.html`), ensamblados nativamente vía `public_html/index.php`.
  - **Modularización CSS:** Descompuesto `app.css` (2,857 líneas) en 8 hojas especializadas en `public_html/assets/css/` (`variables.css`, `base.css`, `header.css`, `login.css`, `kanban.css`, `views.css`, `modals.css`, `footer.css`), orquestadas vía `@import` en `app.css`.
  - **Aislamiento Total de Login:** Resuelto el bug de solapamiento visual. Eliminada la regla bloqueante `!important` en el display de login; ahora el Login y el Tablero Kanban son estados mutuamente excluyentes y jamás se mezclan.
  - **Skill Creada:** Creada la skill `.agents/skills/modularizar-archivos-grandes/SKILL.md` para estandarizar la división de monolitos visuales en el repositorio.
  - **Test Runner Web Autónomo Validado:** 10 de 10 pruebas superadas con éxito (100% PASS, 254.49 ms).
- Base de datos local: SQLite activa en `storage/database.sqlite` con esquema e integridad comprobada.
- Servidor local activo en: `http://127.0.0.1:8000`.

## Decisiones cerradas
- Ver D-001 a D-022 en `docs/DECISIONS.md`.

## Bloqueos y pendientes
- Sin bloqueos técnicos para CICLO-01. Pendiente de validación visual final de la tarjeta vencida y del botón de archivo inmediato. La suite integral conserva fallos heredados por expectativas de permisos antiguas.

## Próximo paso recomendado
1. Ingresar en el navegador a `http://127.0.0.1:8000` (o recargar con `Ctrl + F5` para actualizar a `v1.5.7`).
2. Se visualiza de inmediato la pantalla de Login limpia con la imagen 3D lateral y accesos directos, completamente aislada sin tableros por debajo.
3. Al ingresar con cualquiera de las cuentas demo (ej. Marco Chumbes), el Login desaparece completamente y se muestra el Tablero Kanban limpio.
4. Al hacer clic en "Salir", el tablero se oculta y regresa de inmediato al Login aislado.

## Datos para la próxima sesión
- Diagnóstico autónomo web: `http://127.0.0.1:8000/api/test-runner` o `http://127.0.0.1:8000/api/test-runner?format=json`.
- Plataforma web: `http://127.0.0.1:8000` (Clave de acceso común: `Planeamiento2026!`).

## Última observación visual atendida
- Los campos de correo y contraseña del login ahora ocupan todo el ancho disponible y no se recortan.
- El fondo global usa un color sólido con patrón de puntos sutil en modo claro y oscuro.
- Cambios pendientes de confirmación visual por el usuario en ambos temas y móvil.

## Último cambio funcional
- "Tablero del Equipo" fue retirado completamente de la navegación y lógica frontend.
- Vista Área quedó como único tablero oficial para asistentes y jefaturas.
- No existe una ruta backend diferenciada para ese módulo; se eliminó también el parámetro frontend `scope=team` que solo era usado para cargar usuarios.

## Última regla de permisos implementada
- Asistentes: edición, checklist, comentarios/evidencias y movimiento únicamente en `PENDIENTE` y `EN_PROCESO` sobre tareas asignadas.
- Asistentes: fecha límite siempre inmutable.
- Asistentes: `EN_REVISION` y `FINALIZADA` quedan en solo lectura mientras esperan respuesta o dictamen de Jefatura.

## Diseño pendiente de implementación
- Gestión de Usuarios fue diseñada en `docs/USER_MANAGEMENT_MODULE.md`.
- El módulo será independiente de la consulta de responsables usada por tareas.
- Decisiones confirmadas: Jefe de Área con jerarquía administrativa superior, permisos por rol, auto-desactivación permitida y contraseña asignada manualmente.


