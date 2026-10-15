<?php

declare(strict_types=1);

namespace App\Config;

use PDO;
use PDOException;
use RuntimeException;

/**
 * Conector de base de datos MySQL 8 / MariaDB (InnoDB) mediante PDO.
 * Garantiza transacciones ACID seguras y sentencias preparadas nativas.
 */
class Database
{
    private static ?PDO $instance = null;

    /**
     * Retorna la instancia compartida de conexión PDO.
     */
    public static function getConnection(): PDO
    {
        if (self::$instance === null) {
            $defaultSqlite = dirname(__DIR__, 2) . '/storage/database.sqlite';
            $sqlitePath = (string) Env::get('DB_PATH', $defaultSqlite);
            $driver = (string) Env::get('DB_DRIVER', file_exists($sqlitePath) ? 'sqlite' : 'mysql');

            if ($driver === 'sqlite') {
                self::$instance = self::createSqliteConnection($sqlitePath);
                return self::$instance;
            }

            $host = (string) Env::get('DB_HOST', '127.0.0.1');
            $port = (string) Env::get('DB_PORT', '3306');
            $dbName = (string) Env::get('DB_NAME', 'planeamiento_tareas');
            $user = (string) Env::get('DB_USER', 'root');
            $pass = (string) Env::get('DB_PASS', '');

            $dsn = "mysql:host={$host};port={$port};dbname={$dbName};charset=utf8mb4";

            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false, // Previene inyección SQL nativamente
                PDO::ATTR_TIMEOUT            => 1,     // Timeout rápido de 1s para prevenir demoras
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
            ];

            try {
                self::$instance = new PDO($dsn, $user, $pass, $options);
            } catch (PDOException $e) {
                // En entorno local de desarrollo, fallback automático a SQLite si MySQL no está levantado
                if (Env::get('APP_ENV', 'development') === 'development' && file_exists($sqlitePath)) {
                    error_log("MySQL no disponible localmente. Usando SQLite en: {$sqlitePath}");
                    self::$instance = self::createSqliteConnection($sqlitePath);
                    return self::$instance;
                }

                // Registrar log y no exponer detalles sensibles de conexión en producción
                error_log("Database connection error: " . $e->getMessage());
                throw new RuntimeException("No se pudo conectar a la base de datos MySQL.", 500, $e);
            }
        }

        return self::$instance;
    }

    /**
     * Permite inyectar una conexión para pruebas o reiniciar la instancia.
     */
    public static function setConnection(?PDO $pdo): void
    {
        self::$instance = $pdo;
    }

    /**
     * Crea y asegura la estructura de una base de datos local SQLite.
     */
    private static function createSqliteConnection(string $path): PDO
    {
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $isNew = !file_exists($path) || filesize($path) === 0;

        $pdo = new PDO("sqlite:{$path}", null, null, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false
        ]);
        $pdo->exec('PRAGMA foreign_keys = ON;');

        if ($isNew) {
            self::seedSqliteSchema($pdo);
        }

        return $pdo;
    }

    /**
     * Inicializa esquema y datos semilla en SQLite local.
     */
    public static function seedSqliteSchema(PDO $pdo): void
    {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS usuarios (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                codigo TEXT NOT NULL UNIQUE,
                nombre TEXT NOT NULL,
                email TEXT NOT NULL UNIQUE,
                password_hash TEXT NOT NULL,
                rol TEXT NOT NULL,
                cargo TEXT NOT NULL,
                activo INTEGER NOT NULL DEFAULT 1,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE IF NOT EXISTS sesiones (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                usuario_id INTEGER NOT NULL,
                token_hash TEXT NOT NULL UNIQUE,
                ip_address TEXT,
                user_agent TEXT,
                expira_at TEXT NOT NULL,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
            );

            CREATE TABLE IF NOT EXISTS tableros_columnas (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                nombre TEXT NOT NULL,
                orden INTEGER NOT NULL DEFAULT 1,
                tipo_estado TEXT NOT NULL,
                color_hex TEXT NOT NULL DEFAULT '#64748b',
                ambito TEXT NOT NULL DEFAULT 'AREA',
                usuario_id INTEGER NULL,
                activo INTEGER NOT NULL DEFAULT 1,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE IF NOT EXISTS tareas (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                codigo TEXT NOT NULL UNIQUE,
                titulo TEXT NOT NULL,
                descripcion TEXT,
                tipo_tarea TEXT NOT NULL DEFAULT 'AREA',
                columna_id INTEGER NOT NULL,
                prioridad TEXT NOT NULL DEFAULT 'MEDIA',
                creador_id INTEGER NOT NULL,
                responsable_id INTEGER NOT NULL,
                requiere_aprobacion INTEGER NOT NULL DEFAULT 0,
                revisor_id INTEGER,
                fecha_limite TEXT,
                fecha_finalizada TEXT,
                historial_inmediato INTEGER NOT NULL DEFAULT 0,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (columna_id) REFERENCES tableros_columnas(id),
                FOREIGN KEY (creador_id) REFERENCES usuarios(id),
                FOREIGN KEY (responsable_id) REFERENCES usuarios(id)
            );

            CREATE TABLE IF NOT EXISTS subtareas (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                tarea_id INTEGER NOT NULL,
                titulo TEXT NOT NULL,
                completada INTEGER NOT NULL DEFAULT 0,
                orden INTEGER NOT NULL DEFAULT 1,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (tarea_id) REFERENCES tareas(id) ON DELETE CASCADE
            );

            CREATE TABLE IF NOT EXISTS tarea_comentarios (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                tarea_id INTEGER NOT NULL,
                usuario_id INTEGER NOT NULL,
                comentario TEXT NOT NULL,
                es_evidencia INTEGER NOT NULL DEFAULT 0,
                archivo_nombre_original TEXT,
                archivo_ruta_hash TEXT,
                archivo_mime TEXT,
                archivo_size_bytes INTEGER,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (tarea_id) REFERENCES tareas(id) ON DELETE CASCADE,
                FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
            );

            CREATE TABLE IF NOT EXISTS auditoria_eventos (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                usuario_id INTEGER,
                entidad_tipo TEXT NOT NULL,
                entidad_id INTEGER NOT NULL,
                accion TEXT NOT NULL,
                estado_anterior TEXT,
                estado_nuevo TEXT,
                detalles_json TEXT,
                ip_address TEXT,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE IF NOT EXISTS tarea_aprobaciones (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                tarea_id INTEGER NOT NULL,
                revisor_id INTEGER NOT NULL,
                estado TEXT NOT NULL DEFAULT 'PENDIENTE',
                comentario_entrega TEXT,
                observaciones_devolucion TEXT,
                fecha_solicitud TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                fecha_resolucion TEXT,
                FOREIGN KEY (tarea_id) REFERENCES tareas(id) ON DELETE CASCADE,
                FOREIGN KEY (revisor_id) REFERENCES usuarios(id)
            );

            CREATE TABLE IF NOT EXISTS tarea_dependencias (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                tarea_id INTEGER NOT NULL,
                predecesora_id INTEGER NOT NULL,
                tipo_dependencia TEXT NOT NULL DEFAULT 'AND_FINISH_TO_START',
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (tarea_id) REFERENCES tareas(id) ON DELETE CASCADE,
                FOREIGN KEY (predecesora_id) REFERENCES tareas(id) ON DELETE CASCADE,
                UNIQUE(tarea_id, predecesora_id)
            );

            CREATE TABLE IF NOT EXISTS notificaciones (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                usuario_id INTEGER NOT NULL,
                tipo_evento TEXT NOT NULL,
                titulo TEXT NOT NULL,
                mensaje TEXT NOT NULL,
                entidad_tipo TEXT NOT NULL DEFAULT 'TAREA',
                entidad_id INTEGER NOT NULL,
                enlace TEXT,
                leida INTEGER NOT NULL DEFAULT 0,
                idempotency_key TEXT UNIQUE,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                leida_at TEXT,
                FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
            );

            CREATE INDEX IF NOT EXISTS idx_notif_usuario_leida ON notificaciones(usuario_id, leida);
        ");

        // Semilla de columnas si no existen
        $colCount = (int)$pdo->query("SELECT COUNT(*) FROM tableros_columnas")->fetchColumn();
        if ($colCount === 0) {
            $pdo->exec("
                INSERT INTO tableros_columnas (id, nombre, orden, tipo_estado, color_hex, ambito, activo) VALUES
                (1, 'Pendiente', 1, 'PENDIENTE', '#64748b', 'AREA', 1),
                (2, 'En proceso', 2, 'EN_PROCESO', '#2563eb', 'AREA', 1),
                (3, 'Bloqueada', 3, 'BLOQUEADA', '#e11d48', 'AREA', 1),
                (4, 'En revisión', 4, 'EN_REVISION', '#d97706', 'AREA', 1),
                (5, 'Finalizada', 5, 'FINALIZADA', '#059669', 'AREA', 1);
            ");
        }

    }
}
