# Guía de Despliegue en DonWeb Ferozo y Manual del Piloto (5 Usuarios)
**TAL S.A. — Área de Planeamiento**  
**Sistema Web de Gestión y Seguimiento de Tareas (Versión V1.2.0 — MVP)**

---

## 1. Resumen Ejecutivo y Alcance del Piloto

El presente documento establece el procedimiento técnico y operativo para la puesta en producción y ejecución del piloto del **Sistema de Gestión y Seguimiento de Tareas** en la infraestructura de Web Hosting DonWeb con panel Ferozo.

El sistema ha superado satisfactoriamente las 10 etapas de desarrollo de la **Fase 1 (MVP)**, totalizando **250+ pruebas automatizadas** que cubren autenticación RBAC, tablero Kanban, asignación controlada, dependencias DAG acíclicas, circuito formal de aprobaciones con confirmación de cierre, motor de notificaciones e historial inmutable con dashboard de métricas operativas.

---

## 2. Equipo del Piloto (5 Usuarios de TAL S.A.)

El piloto involucra a 5 integrantes clave del Área de Planeamiento, configurados en `database/seeds.sql`:

| ID | Código | Nombre y Apellidos | Correo Corporativo | Rol en Plataforma | Cargo Operativo | Alcance y Permisos |
|---|---|---|---|---|---|---|
| **1** | `ASIST-01` | Marco Chumbes | `mchumbes@planeamiento.tal` | `ASISTENTE` | Asistente de Información 1 | Autoasignación forzosa, tareas del área asignadas, tareas personales privadas, checklist y carga de evidencias. |
| **2** | `ASIST-02` | Asistente Dos | `asistente2@planeamiento.tal` | `ASISTENTE` | Asistente de Información 2 | Autoasignación forzosa, tareas del área asignadas, tareas personales privadas. |
| **3** | `ASIST-03` | Asistente Tres | `asistente3@planeamiento.tal` | `ASISTENTE` | Asistente de Información 3 | Autoasignación forzosa, tareas del área asignadas, tareas personales privadas. |
| **4** | `JEFE-PER` | Roberto (Jefe Personal) | `jefe.personal@planeamiento.tal` | `JEFE_PERSONAL` | Jefe de Personal | Creación y asignación de tareas oficiales, dictamen de aprobaciones, balance de equipo y métricas globales. |
| **5** | `JEFE-PLA` | Elena Vega | `jefe.planeamiento@planeamiento.tal` | `JEFE_PLANEAMIENTO` | Jefe del Área de Planeamiento | Supervisión integral del área, asignación/reasignación de tareas oficiales, resolución de circuito de aprobación, dashboard operativo y balance general. |

> [!NOTE]
> **Contraseña inicial para el piloto:** `Planeamiento2026!` (hash Bcrypt `$2y$12$uEG1MZnqkcoThSpgvrgduuqHdhIaVKPlwGHRsPA6prWncrmVvQeJK`). Se recomienda solicitar cambio de contraseña tras el primer acceso.

---

## 3. Checklist Pre-Despliegue en DonWeb Ferozo

### A. Requisitos del Servidor
- [x] **Versión de PHP:** PHP 8.2 o superior activado en el panel Ferozo (`Configuración de PHP` -> Versión 8.2+).
- [x] **Extensiones PHP obligatorias:** `pdo_mysql`, `json`, `mbstring`, `fileinfo`, `openssl`.
- [x] **Módulo Apache:** `mod_rewrite` activo para soportar URLs amigables (`.htaccess`).
- [x] **Base de Datos:** MySQL 8.0 o MariaDB 10.5+ con soporte transaccional InnoDB y collation `utf8mb4_unicode_ci`.

### B. Distribución Física de Archivos en Ferozo
En el explorador de archivos o vía FTP seguro (SFTP/FTPS) de DonWeb Ferozo, la estructura debe montarse de la siguiente manera:

```text
/home/usuario_ferozo/
├── app/                        <- Fuera de la raíz web pública (Aislado con app/.htaccess)
│   ├── Config/
│   ├── Controllers/
│   ├── Core/
│   ├── Middleware/
│   ├── Models/
│   ├── Services/
│   └── bootstrap.php
├── database/                   <- Scripts SQL (schema.sql, seeds.sql)
├── storage/                    <- Almacenamiento seguro
│   ├── logs/                   <- Bitácoras del sistema (permisos 0750)
│   └── uploads/                <- Evidencias adjuntas (Aislado con storage/uploads/.htaccess)
├── .env                        <- Variables de entorno de producción (NUNCA versionar en Git)
└── public_html/                <- ÚNICA CARPETA EXPUESTA PÚBLICAMENTE AL SERVIDOR WEB
    ├── .htaccess               <- Enrutamiento hacia api/index.php y seguridad HTTP
    ├── index.html              <- Interfaz de Usuario SPA
    ├── assets/
    │   ├── css/                <- Estilos (variables.css, app.css)
    │   └── js/                 <- Lógica interactiva (app.js)
    └── api/
        └── index.php           <- Front Controller REST API
```

---

## 4. Procedimiento de Instalación Paso a Paso

### Paso 1: Creación de la Base de Datos
1. Iniciar sesión en el panel Ferozo de DonWeb.
2. Ir a **Bases de Datos MySQL** -> **Crear Base de Datos**.
3. Registrar:
   - Nombre de BD: ej. `tal_planeamiento_db`
   - Usuario de BD: ej. `tal_planeamiento_usr`
   - Contraseña segura: Generar una clave robusta alfanumérica con símbolos.

### Paso 2: Importación de Estructura y Semillas
1. Abrir **phpMyAdmin** desde Ferozo.
2. Seleccionar la base de datos creada.
3. En la pestaña **Importar**:
   - Seleccionar y ejecutar el archivo `database/schema.sql` (Crea las 10 tablas relacionales optimizadas con índices y llaves foráneas).
   - Seleccionar y ejecutar el archivo `database/seeds.sql` (Inserta las 5 columnas funcionales Kanban y los 5 usuarios del equipo piloto).

### Paso 3: Configuración del Archivo `.env` en Producción
Crear el archivo `.env` en la raíz privada `/home/usuario_ferozo/.env` (tomando como base `.env.example`):

```ini
APP_ENV=production
APP_DEBUG=false
APP_URL=https://planeamiento.tal.com.pe
APP_KEY=generar_clave_hexadecimal_aleatoria_de_64_caracteres_minimo

DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=tal_planeamiento_db
DB_USERNAME=tal_planeamiento_usr
DB_PASSWORD="TU_PASSWORD_SEGURO_DE_FEROZO"

SESSION_LIFETIME_HOURS=24
UPLOAD_MAX_FILE_SIZE_MB=10
```

### Paso 4: Ajuste de Permisos de Archivos (CHMOD)
Desde el Administrador de Archivos de Ferozo o terminal SSH:
```bash
chmod 755 public_html
chmod -R 750 app
chmod -R 770 storage/logs
chmod -R 770 storage/uploads
chmod 600 .env
```

### Paso 5: Certificado SSL y Cabeceras HTTPS
1. En el panel Ferozo, activar **Certificado SSL Let's Encrypt** (gratuito) para el dominio o subdominio asignado.
2. Verificar que `public_html/.htaccess` aplique las cabeceras de seguridad activas:
   - `X-Content-Type-Options: nosniff`
   - `X-Frame-Options: SAMEORIGIN`
   - `X-XSS-Protection: 1; mode=block`
   - `Referrer-Policy: strict-origin-when-cross-origin`

---

## 5. Guía Rápida para los Usuarios del Piloto

### A. Para Asistentes (Marco Chumbes, Asistente 2, Asistente 3)
1. **Acceso:** Ingresar a la URL del sistema e iniciar sesión con correo corporativo y clave asignada.
2. **Navegación:**
   - **📋 Mis Tareas del Área:** Muestra las tareas oficiales asignadas a tu persona.
   - **🔒 Mis Tareas Personales:** Espacio privado para organizar tus apuntes o subtareas individuales.
   - **📊 Dashboard & Métricas:** Indicadores personales de cumplimiento y tareas activas.
3. **Creación de Tareas:**
   - Al crear una tarea, la asignación es automáticamente a ti mismo (Regla **RN-03**).
4. **Ejecución y Checklist:**
   - Abre la tarjeta para desglosar tu trabajo en subtareas interactivas, adjuntar evidencias (PDF, Excel, imágenes) y escribir notas.
5. **Cierre o Revisión:**
   - Si la tarea NO requiere aprobación: Al mover a Finalizada, marca el checkbox de confirmación consciente (**RN-11**).
   - Si la tarea REQUIERE aprobación: Pulsa el botón "Solicitar Aprobación a Jefatura" (**RN-10**). La tarea se enviará a revisión y Jefatura dictaminará el cierre.

### B. Para Jefatura (Elena Vega y Roberto)
1. **👥 Tablero General del Equipo:** Permite supervisar todas las tareas del área, filtradas por estado o prioridad.
2. **Asignación y Reasignación:** Los jefes pueden asignar y reasignar responsables entre los miembros del equipo.
3. **⚖️ Bandeja de Aprobaciones:**
   - Notificaciones y badge numérico de tareas enviadas a revisión formal.
   - Opciones de dictamen:
     - **Aprobar:** Cierra formalmente la tarea con timestamp y libera dependencias sucesoras.
     - **Devolver:** Exige observaciones detalladas obligatorias (**RN-12**) y retorna la tarea a *En Proceso*.
4. **📊 Dashboard Operativo & Balance de Equipo:**
   - Supervisión en tiempo real de la tasa de cumplimiento del área.
   - Alertas tempranas de tareas vencidas o próximas a vencer (≤ 7 días).
   - Tabla de **Balance de Carga de Trabajo** por cada integrante del equipo.

---

## 6. Plan de Contingencia, Backup y Rollback

### A. Estrategia de Backups Diarios
1. **Base de Datos MySQL:** Configurar en Ferozo backup automático diario de la base de datos o ejecutar vía Cron Job:
   ```bash
   mysqldump -u tal_planeamiento_usr -p'PASSWORD' tal_planeamiento_db | gzip > /home/usuario_ferozo/backups/db_$(date +\%Y\%m\%d).sql.gz
   ```
2. **Archivos de Evidencias (`storage/uploads/`):** Respaldo comprimido semanal.

### B. Procedimiento de Rollback
1. Si se detecta un incidente crítico tras una actualización:
   - Restaurar el último dump SQL consistente:
     ```bash
     gunzip < /home/usuario_ferozo/backups/db_PREVIO.sql.gz | mysql -u tal_planeamiento_usr -p'PASSWORD' tal_planeamiento_db
     ```
   - Revertir los archivos del directorio `app/` y `public_html/` a la versión estable previa.

---

## 7. Cierre de Fase y Próximos Pasos

Con la culminación de **MVP-10**:
- La **Fase 1 (MVP)** queda formalmente **CONCLUIDA AL 100%**.
- Se recomienda un periodo de marcha blanca de 2 semanas con el equipo de 5 usuarios para recopilar retroalimentación antes de iniciar las funcionalidades de **Fase 2** (Plantillas de procesos, tareas recurrentes, registro de tiempos y calendario laboral).
