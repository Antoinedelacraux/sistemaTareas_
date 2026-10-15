<?php

declare(strict_types=1);

// Configuración de reporte de errores para desarrollo
ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

// Autoloader PSR-4 liviano para el namespace App\
spl_autoload_register(function (string $class) {
    $prefix = 'App\\';
    $baseDir = __DIR__ . '/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    if (file_exists($file)) {
        require_once $file;
    }
});

// Cargar variables de entorno desde la raíz del proyecto
use App\Config\Env;
Env::load(dirname(__DIR__) . '/.env');
