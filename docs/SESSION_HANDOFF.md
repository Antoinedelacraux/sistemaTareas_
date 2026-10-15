# Traspaso entre sesiones (leer primero)

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


