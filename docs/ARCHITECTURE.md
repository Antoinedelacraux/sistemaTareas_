# Arquitectura técnica — Aprobada (ADR-0001)

## Orientación
Monolito modular estructurado por capas y dominios, con API REST desacoplada, SPA frontend y base de datos relacional. Diseñado específicamente para ejecutarse de forma óptima y nativa en el Web Hosting Ferozo de DonWeb, sin dependencias de demonios persistentes ni microservicios innecesarios.

## Stack aprobado (ADR-0001)
- **Plataforma de Alojamiento:** DonWeb Web Hosting tradicional (Panel Ferozo, Apache 2.4 / LiteSpeed con `mod_rewrite` y `.htaccess`).
- **Backend:** PHP 8.2+ (orientado a objetos, tipado estricto, PSR-4, API REST modular con enrutamiento limpio, transacciones PDO contra MySQL).
- **Base de Datos:** MySQL 8 / MariaDB (motor InnoDB, transacciones ACID, integridad referencial con claves foráneas, administrable en phpMyAdmin de Ferozo).
- **Frontend:** SPA moderna de alta fidelidad estética (HTML5 semántico, Vanilla CSS estructurado con Design Tokens / Variables CSS, modo oscuro/claro, transiciones fluidas, JavaScript modular nativo o empaquetado para `public_html`).
- **Seguridad de Sesiones:** Autenticación con credenciales protegidas con `password_hash()` (Argon2id/Bcrypt), cookies de sesión o tokens con atributos `HttpOnly; SameSite=Strict; Secure`.
- **Estructura en Servidor Ferozo:**
  - `public_html/`: Punto de entrada público (`index.html`, assets frontend, y único `api/index.php` para enrutamiento de la API vía `.htaccess`).
  - `app/` o `src/` (fuera del DocumentRoot o protegido por `.htaccess` `Require all denied`): Código de dominio, lógica de negocio, configuración, migraciones y modelos, inaccesible directamente por URL.
- **Notificaciones:** Notificaciones internas dentro de la aplicación; soporte para correo SMTP autenticado opcional posterior.

## Módulos lógicos
`identidad`, `autorizacion`, `usuarios`, `tableros`, `tareas`, `dependencias`, `aprobaciones`, `notificaciones`, `auditoria`, `reportes`.

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
