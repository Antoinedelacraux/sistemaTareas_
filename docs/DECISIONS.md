# Registro de decisiones y supuestos

## Decisiones CONFIRMADAS con el usuario
- D-032 Consistencia de controles y visibilidad de contraseñas: los selectores y tarjetas conservan un sistema visual uniforme; login y gestión de usuarios incorporan un control accesible para mostrar u ocultar la contraseña sin alterar su valor.
- D-033 Filtros de la Bandeja de Aprobaciones: la búsqueda por tarea/código y el filtro por asistente se ejecutan sobre la lista cargada, conservando las acciones de aprobar y devolver sobre cada tarjeta.
- D-001 Equipo inicial 5: tres asistentes y dos jefes.
- D-002 Creación de tareas por jefes y asistentes sujeta a permisos.
- D-003 Asistentes solo asignan a sí mismos; jefes asignan a otros.
- D-004 Asistentes solo ven sus propias tareas, jefes supervisan tareas oficiales.
- D-005 Aprobación solo cuando esté configurada.
- D-006 Confirmación adicional para cierre sin aprobación.
- D-007 Automatización de movimiento de estados y secuencias.
- D-008 Procesos secuenciales y paralelos.
- D-009 Tareas personales y del área separadas.
- D-010 Acceso web seguro desde cualquier ubicación.
- D-011 Plataforma de despliegue DonWeb Ferozo (Web Hosting tradicional): Backend PHP 8.2+ (REST API modular), Base de datos MySQL / MariaDB (InnoDB con transacciones ACID), Frontend SPA moderna (HTML5, Vanilla CSS / Design Tokens, JavaScript modular).
- D-012 Simplificación ágil y usabilidad prioritaria: Refactorización orientada a la fluidez en la gestión de tareas. Eliminación de trabas burocráticas en Kanban (sin modales bloqueantes de confirmación forzada al arrastrar, sin candados rígidos de grafos matemáticos DAG); tablero directo e interactivo, roles colaborativos (jefes asignan y supervisan todo; asistentes gestionan sus tareas y visualizan el tablero general del equipo) y checklist integrado con progreso porcentual.
- D-015 Test Runner Web Autónomo en Memoria: Suite de autodiagnóstico integral ejecutable vía HTTP en `/api/test-runner` sin depender de consola de comandos ni permisos locales de PowerShell.
- D-016 Personalización Cromática de Columnas Trello-Style: Configuración libre de columnas con paleta de 8 colores y border-top identificador.
- D-017 Retiro de Dashboard y Módulo de Historial con Expiración de 24h: Descarte del módulo de métricas/dashboard y creación de un Historial exclusivo para Jefatura (`JEFE_PLANEAMIENTO`, `JEFE_PERSONAL`) con auto-archivo pasivo de tareas finalizadas tras 24 horas (`t.fecha_finalizada <= time() - 86400`).
- D-018 Separación Estricta de Vista Área vs Vista Personal: **SUPERADA por D-024**. Fue una decisión intermedia del diseño y ya no describe el alcance operativo vigente.
- D-019 Centralización de Trazabilidad en Módulo de Historial y Modal de Tarea sin Scroll: Se retira la sección de bitácora del formulario de detalle para dejarla en el módulo de Historial de Jefatura. Se expande el modal a 1140px (95vw) con cuadrícula 1fr / 1fr balanceada para permitir visualizar la información de la tarea, checklist, dependencias, comentarios y selector de columna sin requerir barras de desplazamiento vertical.
- D-020 Matriz de Permisos Diferenciada por Roles (Jefes vs Asistentes): Los Jefes de Planeamiento y Personal tienen control total (módulos, registros, dependencias, columnas, usuarios). Los asistentes tienen acceso a Vista Área (checklist, evidencias, comentarios, dependencias read-only, sin edición/borrado de tareas de área ni columnas). La referencia a Vista Personal quedó superada por D-024.
- D-021 Rediseño de Login Réplica Referencia y Lógica Operativa de Estados de Columna:
  1. Login con tarjeta flotante orgánica split (panel curvo esmeralda con logo circular blanco TAL, bienvenida corporativa; panel derecho blanco con inputs alargados menta suave, botón píldora 'LOG IN' y accesos rápidos demostrativos).
  2. Botón `+ Columna` en barra superior (`#workspace-controls-bar`) para jefaturas, evitando scroll horizontal/vertical innecesario.
  3. Lógica operativa de estados en columnas:
     - `Pendiente` y `En Proceso`: Sin restricciones operativas.
     - `Revisión / Aprobación`: Tareas movidas a columnas de este estado ingresan de forma automática a la bandeja del Módulo de Aprobaciones para dictamen de Jefatura.
     - `Finalizada / Cumplida`: Tarea cerrada para Asistentes (bloqueo total de interacción: arrastre, edición, checklist y comentarios); solo personal de Jefatura puede interactuar.
- D-022 Modularización Arquitectónica del Frontend, Separación de Responsabilidades y Aislamiento Estricto del Login:
  1. Descomposición del archivo HTML monolítico en 9 partials atómicos bajo `public_html/partials/` (`head.html`, `header.html`, `workspace_bar.html`, `login.html`, `kanban.html`, `approvals.html`, `history.html`, `modals.html`, `footer.html`).
  2. Creación del ensamblador PHP `public_html/index.php` con fallback automático en `server.php` para despliegues dinámicos y estáticos.
  3. Descomposición del CSS monolítico (2,857 líneas) en 8 hojas de estilo especializadas por dominio (`variables.css`, `base.css`, `header.css`, `login.css`, `kanban.css`, `views.css`, `modals.css`, `footer.css`), orquestadas mediante `@import` en `app.css`.
  4. Aislamiento estricto de la vista de autenticación: `login.css` desacoplado y eliminación de bloqueos `!important` en display, garantizando que el Login y el Tablero Kanban sean estados visualmente mutuamente excluyentes.
  5. Creación de la Skill `.agents/skills/modularizar-archivos-grandes/SKILL.md` para estandarizar el proceso en el repositorio.

- D-023 Cierre visible por columna y limpieza personal: las columnas `FINALIZADA` muestran un candado informativo; una tarea movida a ellas queda solo para consulta. La eliminación masiva de Vista Personal quedó superada por D-024; se conserva la decisión visual del candado y la iconografía SVG profesional.
- D-024 Tablero único de Área: se retira la Vista Personal y sus rutas de uso. Todas las tareas nuevas se fuerzan a `AREA`; asistentes crean y gestionan solo sus tareas asignadas, mientras Jefe de Personal y Jefe de Área ven todas las tareas y pueden asignarlas a asistentes activos. Las tareas personales históricas se conservan en datos, pero no se exponen.

- D-029 Instalación limpia de producción: se eliminan usuarios, tareas y accesos demo de las semillas y del login. Un instalador temporal protegido por `INSTALL_TOKEN` crea únicamente el primer Jefe de Área sobre una base vacía; queda bloqueado después de usarse.
- D-030 Coherencia del circuito formal: una tarea sin `requiere_aprobacion` no puede pasar a `EN_REVISION`, no puede crear solicitudes de aprobación y conserva el cierre directo. La restricción se aplica en backend, drag-and-drop y selector del detalle.

## Propuestas pendientes de validación
- P-001 Privacidad de tareas personales también ante jefes, exclusión de KPIs oficiales.
- P-002 Motor de dependencias AND para habilitar sucesores; OR futuro si lo requiere el proceso.
- P-003 Estado inicial de 5 columnas Kanban (Pendiente, En proceso, Bloqueada, En revisión, Finalizada).
- P-004 [SUSTITUIDO POR D-011 / ADR-0001] Stack sugerido React/FastAPI/Postgres descartado por incompatibilidad con Web Hosting Ferozo compartido.
- P-005 Sesiones protegidas por tokens/cookies HttpOnly; MFA/SSO a evaluar para versiones posteriores.
- P-006 Gestión de adjuntos: directorio protegido fuera de web root o vía `.htaccess`, tipos MIME validados, límite de tamaño (10MB).
- P-007 Modo de reasignar/convertir tarea personal a oficial y su autorización por el usuario o jefe.

## ADR (Architecture Decision Records)
## Decision confirmada 2026-10-16 - Acciones del Historial por iconos y seleccion masiva
- La tabla de tareas archivadas usa iconos accesibles para ver el expediente y eliminarlo.
- Jefatura puede seleccionar filas individuales o todas las visibles, siempre con confirmacion, autorizacion backend y auditoria.

- **ADR-0001** | Estado: ACEPTADA | 2026-10-13
  - **Contexto:** El sistema se desplegará en la infraestructura de DonWeb bajo panel Ferozo (Web Hosting tradicional compartido).
  - **Alternativas consideradas:**
    1. *Python FastAPI + PostgreSQL:* Descartada porque Ferozo compartido no admite ejecución continua de demonios ASGI (uvicorn/gunicorn) de fondo ni ofrece PostgreSQL nativo.
    2. *Node.js Express + MongoDB:* Descartada por limitaciones de procesos en segundo plano y compatibilidad en Ferozo tradicional.
    3. *PHP 8.2+ + MySQL 8 / MariaDB + SPA moderna:* Seleccionada. Es el entorno 100% nativo de Ferozo, ofrece soporte nativo para Apache/mod_rewrite, base de datos administrada con phpMyAdmin, cero demonios propensos a caídas y rendimiento óptimo para el equipo de 5 usuarios y escalabilidad modular.
  - **Decisión:** Implementar backend en PHP 8.2+ con arquitectura monolítica modular (API REST limpia, PDO con transacciones, validaciones en backend), base de datos relacional MySQL/MariaDB (InnoDB), y frontend SPA moderno (HTML5 semántico, Vanilla CSS de alta fidelidad, JavaScript modular) servido en `public_html`.
  - **Consecuencias:** Despliegue directo sin fricción en DonWeb, soporte garantizado en Apache/.htaccess, máxima velocidad de respuesta y mantenimiento simple.
- **Aprobador:** Usuario / Responsable del proyecto.

## Decisión posterior
- D-025 Ciclo de cierre y vencimientos: una tarea aprobada o ubicada en `FINALIZADA` se cierra y permanece 24 horas en el tablero antes de pasar al historial. Asistentes solo la visualizan; Jefatura puede editarla, eliminarla o reabrirla únicamente en `EN_PROCESO`, y puede archivarla inmediatamente. Las tarjetas vencidas se distinguen visualmente y el historial registra el cumplimiento del plazo.
- D-026 Edición por estado para asistentes: el asistente puede editar únicamente tareas asignadas en `PENDIENTE` y `EN_PROCESO`, sin modificar nunca la fecha límite. En `EN_REVISION` y `FINALIZADA` la tarea queda en modo consulta hasta la respuesta de Jefatura.
- D-027 Gestión de Usuarios por roles: se aprueba un módulo separado para Jefatura. El Jefe de Área posee mayor jerarquía administrativa; ambos jefes mantienen los mismos permisos operativos en tareas y pueden crear, editar, cambiar rol, activar, desactivar y restablecer contraseñas. La única restricción jerárquica es que el Jefe de Personal no puede desactivar cuentas con rol Jefe de Área. Los permisos serán por rol, se permitirá auto-desactivación y el cambio de contraseña será manual, sin enlaces ni correo.

## Estado vigente del alcance

- El tablero operativo expuesto es únicamente `AREA`.
- Dashboard, Vista Personal y sus rutas operativas no forman parte del producto vigente.
- El soporte de `tipo_tarea = PERSONAL` queda limitado a compatibilidad con datos históricos no expuestos.
