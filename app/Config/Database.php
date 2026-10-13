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
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
            ];

            try {
                self::$instance = new PDO($dsn, $user, $pass, $options);
            } catch (PDOException $e) {
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
}
