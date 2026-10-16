# Traspaso entre sesiones

## Actualización 2026-10-16 - Alcance de controles entre módulos

- El contenedor de acciones globales (`.workspace-actions`) se muestra solo con `currentScope = area`; las pestañas de navegación permanecen visibles en todos los módulos.
- En Aprobaciones, Historial y Usuarios quedan ocultos el buscador global, el filtro de prioridad, el botón de refresco del tablero y Nueva Tarea.
- Aprobaciones conserva su buscador y filtro por asistente dentro de la propia bandeja.
- `app.js` usa `v=1.7.4` para invalidar caché.
- Pendiente: ejecutar suite/lint y validar visualmente la navegación entre Vista Área y Aprobaciones con los roles disponibles.

## Actualizacion 2026-10-16 - Filtros de la Bandeja de Aprobaciones

- Se agrego buscador por codigo, titulo o responsable.
- Se agrego selector de asistente responsable; sus opciones se construyen con los usuarios cargados y se complementan con los responsables presentes en la bandeja.
- Ambos filtros trabajan sobre la cache de aprobaciones y no generan una peticion por cada tecla.
- Las acciones Ver Detalle, Aprobar y Devolver permanecen ligadas al ID real de cada tarjeta.
- Pendiente: ejecutar verificaciones finales y validacion visual manual.

## Actualizacion 2026-10-16 - Alineacion visual y contraseñas

- Se unifico la geometria de selectores y controles: altura minima, padding derecho para la flecha, apariencia nativa reemplazada por un chevron consistente y foco visible.
- Las tarjetas de tarea mantienen un borde exterior de un pixel; los estados cerrado/vencido usan un acento interno para no deformar las esquinas.
- Login y Gestion de Usuarios tienen boton de ojo accesible para mostrar u ocultar contraseña; el valor no se modifica.
- Cache actualizada: hojas CSS a v1.5.9, app y UI a v1.7.1.
- Verificacion parcial: PHP lint y git diff --check PASS; Node no esta instalado.
- Siguiente paso: ejecutar suite integral y revisar visualmente en navegador claro/oscuro y viewport movil.

## Actualizacion 2026-10-16 - Historial: consulta y eliminacion por iconos

- Se agrego seleccion individual y seleccion de todas las filas visibles del Historial.
- Las acciones de fila ahora son iconos accesibles: ver tarea cerrada y eliminar del Historial.
- El boton superior de papelera elimina las tareas seleccionadas una por una mediante DELETE /api/historial/{id}; cada operacion exige confirmacion.
- Backend: solo Jefatura puede eliminar y la tarea debe cumplir exactamente condicion de archivo (AREA, FINALIZADA, fecha valida y archivo inmediato o antiguedad mayor a 24 horas).
- Auditoria: se registra ELIMINAR_HISTORIAL; una tarea activa no puede eliminarse desde este endpoint.
- Verificacion: tests/approvals_test.php 27/27, suite integral 10/10, QA 43/43 y lint PHP PASS.
- Siguiente paso: validacion visual manual con Jefe de Area y Jefe de Personal, incluyendo seleccion filtrada y confirmacion de eliminacion.

## Actualización 2026-10-16 — Corrección de caché de módulos JavaScript

- Se corrigió `Uncaught ReferenceError: refreshBoard is not defined` observado en la sesión de Jefe de Área.
- La causa era la mezcla de HTML nuevo con `app.js?v=1.6.0` cacheado; se subieron a `v=1.6.2` el script principal y los módulos `kanban.js`/`task-modal.js`.
- La autorización por rol no era el problema: el botón y los endpoints funcionan para Asistentes, Jefe de Planeamiento y Jefe de Personal según la matriz vigente.

## Actualización 2026-10-16 — Refresco de ficha de tarea

- Se añadió un icono de refresco en el encabezado del detalle de tarea, junto al botón de cierre.
- El botón vuelve a consultar la tarea, comentarios, evidencias, checklist y dependencias; mantiene abierta la ficha y actualiza el Kanban sin recargar toda la página.
- Mientras consulta, el icono gira y el botón queda deshabilitado para evitar solicitudes duplicadas.
- La edición continúa disponible únicamente mediante el botón inferior y las reglas de Jefatura, responsable y estado (`PENDIENTE`/`EN_PROCESO`).

## Actualización 2026-10-16 — Refresco manual del tablero

- Se añadió un botón de icono en la barra de filtros de la Vista Área para actualizar columnas y tarjetas sin recargar la página.
- El botón muestra animación, bloquea clics duplicados y no aparece fuera del tablero operativo.

## Estado verificable

- Rama: `main`, árbol de trabajo limpio después del checkpoint local.
- Último commit confirmado: `f6d8142 fix(frontend): bust cache for refresh actions`.
- Plataforma local: PHP 8.4 + SQLite.
- Verificación actual: `php tests/run_all.php` completó 10/10 suites; QA piloto integrado 43/43 PASS; `git diff --check` PASS.
- Limitación: falta validación visual manual del botón en escritorio y móvil.

## Primer paso de la próxima sesión

1. Revisar visualmente la ficha de tarea y confirmar el acceso rápido en ambos breakpoints.
2. Ejecutar `git status` para confirmar que no haya cambios ajenos antes de la siguiente unidad.
3. Mantener pendiente el despliegue remoto hasta autorización explícita.

## Actualización 2026-10-16 — Corrección definitiva de caché de `refreshBoard`

- Se detectó que Jefatura de Área recibía el HTML del botón junto con una versión anterior de `app.js`, provocando `Uncaught ReferenceError: refreshBoard is not defined`.
- Se publicó la versión `1.6.3` del script principal y del módulo Kanban, manteniendo `window.refreshBoard` fuera de cualquier condición de rol.
- El `onclick` del botón ahora verifica la función antes de invocarla y usa recarga completa únicamente como fallback de transición si el módulo todavía no está disponible.
- Verificación: `php tests/run_all.php` 10/10 suites y lint PHP PASS.
- Siguiente paso: probar el icono en navegador con Jefe de Área, Jefe de Personal y Asistente; confirmar que actualiza tarjetas sin recargar la página en las tres sesiones.

---

## Actualización 2026-10-16 — Limpieza técnica y documental

- Se está alineando el repositorio con el alcance vigente: tablero único de Área, Historial de Jefatura e instalación limpia.
- Se retiraron las instrucciones de acceso con cuentas demo de `LEEME_PRIMERO.md`.
- RF-11 Dashboard quedó registrado como requisito retirado por D-017.
- D-018 quedó marcado como superado por D-024.
- Los registros históricos `tipo_tarea = PERSONAL` se conservan en la base, pero no se crean ni se exponen por los flujos operativos actuales.
- Los módulos vacíos/no referenciados del Dashboard y las pruebas que dependían de ellos ya fueron retirados del árbol operativo.
- `.tmp-cloudflare/` y respaldos locales están excluidos por `.gitignore`; no deben subirse al repositorio ni al paquete de producción.

## Estado verificable

- Rama: `main`, sincronizada con `origin/main` antes de este avance.
- Último commit previo: `60ce24f feat(deploy): prepare clean production installation`.
- Plataforma local: PHP 8.4 + SQLite.
- Entrada vigente: `public_html/index.php` ensamblando partials.
- Producción: preparada técnicamente, todavía no desplegada en Ferozo.
- Limpieza CLEANUP-01: verificada con lint PHP `49/49`, suite integral `10/10`, QA piloto `43/43` y Test Runner HTTP `10/10`.
- Commit de limpieza: `33be20d chore(cleanup): align active scope and remove legacy modules`.

## Pendientes después de esta limpieza

1. Ejecutar lint y pruebas actualizadas.
2. Confirmar que no queden referencias operativas a credenciales demo, Dashboard ni Vista Personal.
3. Validar visualmente el flujo vigente en escritorio y móvil.
4. Repetir Lighthouse con navegador real.
5. Desplegar mediante `docs/DEPLOYMENT_AND_PILOT_GUIDE.md` y realizar piloto controlado.

## Archivos de referencia

- `docs/PROJECT_CONTEXT.md`
- `docs/REQUIREMENTS.md`
- `docs/DECISIONS.md`
- `docs/ROADMAP.md`
- `docs/PROGRESS.md`
- `docs/CHANGELOG.md`

## Actualización 2026-10-16 — Coherencia de aprobación y revisión

- Se corrigió el caso en que una tarea sin aprobación era arrastrada a `EN_REVISION`; antes `Approval::solicitar()` activaba la bandera y dejaba al asistente en modo consulta.
- Ahora la API rechaza la transición y la solicitud con `ERR_APPROVAL_NOT_REQUIRED`, sin cambiar la tarea ni crear aprobaciones.
- La interfaz bloquea el destino `EN_REVISION` en drag-and-drop y lo excluye del selector para tareas sin aprobación; el botón de envío no aparece.
- Verificación: `tests/approvals_test.php` 25/25, suite integral 10/10 y QA 43/43 PASS.
- Siguiente paso: validación visual manual de ambos tipos de tarea en navegador.
