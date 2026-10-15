# Arquitectura técnica — Aprobada (ADR-0001)

## Orientación
Monolito modular estructurado por capas y dominios, con API REST desacoplada, SPA frontend y base de datos relacional. Diseñado específicamente para ejecutarse de forma óptima y nativa en el Web Hosting Ferozo de DonWeb, sin dependencias de demonios persistentes ni microservicios innecesarios.

## Stack aprobado (ADR-0001)
- **Plataforma de Alojamiento:** DonWeb Web Hosting tradicional (Panel Ferozo, Apache 2.4 / LiteSpeed con `mod_rewrite` y `.htaccess`).
- **Backend:** PHP 8.2+ (orientado a objetos, tipado estricto, PSR-4, API REST modular con enrutamiento limpio, transacciones PDO contra MySQL).
- **Base de Datos:** MySQL 8 / MariaDB (motor InnoDB, transacciones ACID, integridad referencial con claves foráneas, administrable en phpMyAdmin de Ferozo).
- **Frontend:** SPA moderna de alta fidelidad estética estructurada bajo separación estricta de responsabilidades:
  - **Partials HTML (`public_html/partials/`):** Descomposición atómica de vistas (`head.html`, `header.html`, `workspace_bar.html`, `login.html`, `kanban.html`, `approvals.html`, `history.html`, `users_management.html`, `modals.html`, `footer.html`). Ensamblado limpio mediante `public_html/index.php`; `index.html` es una copia histórica redirigida por Apache.
  - **CSS Modular por Dominios (`public_html/assets/css/`):** Estilos desacoplados (`variables.css`, `base.css`, `header.css`, `login.css`, `kanban.css`, `views.css`, `modals.css`, `footer.css`) orquestados por `app.css` vía `@import`.
  - **Aislamiento de Login:** Regla de exclusividad mutua: la pantalla de Login y el espacio privado nunca coexisten visualmente; `login.css` desacoplado y sin directivas `!important` que impidan la manipulación dinámica del DOM desde `auth.js`.
- **Seguridad de Sesiones:** Autenticación con credenciales protegidas con `password_hash()` (Argon2id/Bcrypt), cookies de sesión o tokens con atributos `HttpOnly; SameSite=Strict; Secure`.
- **Estructura en Servidor Ferozo:**
  - `public_html/`: Punto de entrada público (`index.php`, `index.html`, `partials/`, assets frontend y `api/index.php` para enrutamiento de la API vía `.htaccess`).
  - `app/`: Modelos, controladores, servicios y lógica de negocio protegida.
- **Notificaciones:** Notificaciones internas dentro de la aplicación; soporte para correo SMTP autenticado opcional posterior.

## Módulos lógicos
`identidad`, `autorizacion`, `usuarios`, `tableros`, `tareas`, `dependencias`, `aprobaciones`, `notificaciones`, `auditoria`, `reportes`.

## Gestión administrativa de usuarios
- El directorio `/api/usuarios` está limitado a responsables de tareas, consulta acotada y validación de asignaciones.
- La administración de cuentas se aísla en `/api/gestion-usuarios/*` y su controlador dedicado. Solo los roles de Jefatura acceden a esas rutas.
- La política jerárquica se aplica en el backend: ambos jefes gestionan cuentas por rol, excepto que `JEFE_PERSONAL` no puede desactivar una cuenta `JEFE_PLANEAMIENTO`.
- Las contraseñas se procesan mediante hash y nunca se devuelven ni registran en auditoría. El cambio de rol, contraseña o desactivación revoca todas las sesiones de la cuenta afectada.

## Invariantes técnicos a diseñar
1. Toda consulta de tarea verifica visibilidad. Prohibir referencias directas que permitan ver tareas de otros usuarios.
2. Permisos de creación y asignación no equivalen a lectura general.
3. Dependencias forman DAG; evaluar predecesoras en cierre confirmado/aprobado.
4. Operaciones de cierre + historial + desbloqueo deben ser transaccionales o usar patrón robusto de eventos.
5. Idempotencia ante reintentos y protección ante actualización simultánea.
6. Diferenciar estados de negocio de nombres editables de columnas.
7. Auditoría conserva sujeto y evento incluso si se desactiva usuario.
8. Gestión de adjuntos con almacenamiento protegido, no ejecutar cargas.

## Documento vivo
Describir aquí decisiones técnicas reales a medida que se aprueben y se implementen. No afirmar que algo ya existe sin inspección del código.

## Cierre e historial
El cierre de una tarea conserva `fecha_finalizada`; `historial_inmediato` permite a Jefatura omitir únicamente la espera de 24 horas, sin alterar la trazabilidad del estado.
