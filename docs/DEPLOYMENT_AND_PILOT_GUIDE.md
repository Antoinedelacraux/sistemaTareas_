# Despliegue inicial en DonWeb Ferozo

Esta guía instala el sistema desde cero: no incorpora usuarios, tareas, comentarios, adjuntos ni credenciales de demostración.

## 1. Requisitos

- PHP 8.2+ con `pdo_mysql`, `json`, `mbstring`, `fileinfo` y `openssl`.
- MySQL 8 o MariaDB con InnoDB y `utf8mb4`.
- HTTPS activo, `mod_rewrite`, `mod_deflate` y `mod_expires` cuando estén disponibles.
- El directorio público del dominio debe apuntar exclusivamente a `public_html/`.

## 2. Subir archivos

Sube `app/`, `database/`, `storage/` y `public_html/` a la raíz privada de la cuenta. Expón solamente `public_html/` al dominio. No subas `.env`, bases SQLite, respaldos, `tests/`, `.tmp-cloudflare/` ni datos de desarrollo.

Asigna permisos de escritura al proceso PHP sobre `storage/`, `storage/uploads/` y `storage/logs/`; el archivo `.env` debe quedar fuera de `public_html/` y con permisos restrictivos.

## 3. Crear base de datos vacía

En phpMyAdmin crea una base y un usuario MySQL exclusivos. Importa, en este orden:

1. `database/schema.sql`
2. `database/seeds.sql`

El segundo archivo solo crea las cinco columnas del tablero: Pendiente, En proceso, Bloqueada, En revisión y Finalizada. No crea cuentas ni tareas.

## 4. Crear `.env` de producción

Parte de `.env.example` y usa nombres reales de las variables:

```ini
APP_ENV=production
APP_DEBUG=false
APP_URL=https://tu-dominio.example
APP_SECRET=clave_aleatoria_de_64_caracteres_o_mas

DB_DRIVER=mysql
DB_HOST=localhost
DB_PORT=3306
DB_NAME=nombre_real_de_base
DB_USER=usuario_real_de_base
DB_PASS="clave_real_de_base"

SESSION_LIFETIME_HOURS=12
UPLOAD_DIR=../storage/uploads
MAX_UPLOAD_SIZE_MB=10

INSTALLER_ENABLED=true
INSTALL_TOKEN=token_aleatorio_de_32_caracteres_o_mas
```

No reutilices contraseñas conocidas ni guardes este archivo en Git. Genera `APP_SECRET` e `INSTALL_TOKEN` con un gestor de contraseñas.

## 5. Crear la primera cuenta administradora

1. Abre `https://tu-dominio.example/instalar.php` mediante HTTPS.
2. Ingresa el `INSTALL_TOKEN`, el nombre, correo institucional y una contraseña de al menos 12 caracteres.
3. El instalador crea un único usuario con rol **Jefe de Área**, registra el bloqueo de instalación y no puede crear una segunda cuenta.
4. Cambia inmediatamente `INSTALLER_ENABLED=false` en `.env`.
5. Inicia sesión con esa cuenta y crea los demás miembros desde **Usuarios**.

Si el instalador muestra 404, revisa que `INSTALLER_ENABLED=true`, que el token tenga 32+ caracteres, que no exista `storage/installation.lock` y que la tabla `usuarios` esté vacía. No borres el lock ni vacíes usuarios en una instalación real para reabrir el instalador.

## 6. Verificación antes de abrir el sistema

- `/` muestra el login sin valores precargados ni cuentas demo.
- `/instalar.php` queda inaccesible tras la primera cuenta o al desactivar el instalador.
- El primer Jefe de Área puede crear las cuentas del equipo desde Usuarios.
- Prueba crear una tarea, asignarla, comentar y adjuntar un archivo de prueba.
- Confirma el candado HTTPS, las cabeceras de seguridad y que los adjuntos no sean ejecutables.

## 7. Backups y rollback

Configura respaldo diario de MySQL y copia semanal de `storage/uploads/`. Antes de cada actualización, exporta la base y guarda una copia de `app/`, `public_html/` y `storage/`. Para rollback, restaura el último respaldo consistente y la versión anterior de archivos.
