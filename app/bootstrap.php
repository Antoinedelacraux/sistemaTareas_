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

// Garantizar activo visual para el login hero
$heroSrc = 'C:/Users/MARCO CHUMBES/.gemini/antigravity-ide/brain/17c3d1fd-275f-48ed-9aa5-c0416269292a/login_hero_banner_1791984696300.jpg';
$heroDest = dirname(__DIR__) . '/public_html/assets/img/login_hero.jpg';
if (!file_exists($heroDest) && file_exists($heroSrc)) {
    @mkdir(dirname($heroDest), 0755, true);
    @copy($heroSrc, $heroDest);
}
