<?php

declare(strict_types=1);

// Servidor de desarrollo integrado para PHP (php -S localhost:8000 server.php)
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$publicDir = __DIR__ . '/public_html';
$requestedFile = $publicDir . $uri;

// Mantener el desarrollo local alineado con Apache: index.php es la entrada
// vigente y index.html solo conserva una copia histÃ³rica no navegable.
if ($uri === '/index.html') {
    header('Location: /', true, 302);
    return;
}

// Sincronizar imagen de portada del login hero si existe en artefactos
$heroArtifact = 'C:/Users/MARCO CHUMBES/.gemini/antigravity-ide/brain/17c3d1fd-275f-48ed-9aa5-c0416269292a/login_hero_banner_1791984696300.jpg';
$heroTarget = $publicDir . '/assets/img/login_hero.jpg';
if (!file_exists($heroTarget) && file_exists($heroArtifact)) {
    @mkdir(dirname($heroTarget), 0755, true);
    @copy($heroArtifact, $heroTarget);
}

// 1. Delegar a la API REST si la ruta inicia con /api
if (str_starts_with($uri, '/api')) {
    require $publicDir . '/api/index.php';
    return;
}

// 2. Servir archivos estáticos reales desde public_html
if ($uri !== '/' && file_exists($requestedFile) && !is_dir($requestedFile)) {
    $ext = strtolower(pathinfo($requestedFile, PATHINFO_EXTENSION));
    $mimes = [
        'css'   => 'text/css; charset=utf-8',
        'js'    => 'application/javascript; charset=utf-8',
        'json'  => 'application/json; charset=utf-8',
        'png'   => 'image/png',
        'jpg'   => 'image/jpeg',
        'jpeg'  => 'image/jpeg',
        'svg'   => 'image/svg+xml',
        'ico'   => 'image/x-icon',
        'webp'  => 'image/webp',
        'woff2' => 'font/woff2',
        'woff'  => 'font/woff',
        'ttf'   => 'font/ttf'
    ];
    header('Content-Type: ' . ($mimes[$ext] ?? 'application/octet-stream'));
    if (str_starts_with($uri, '/assets/')) {
        header('Cache-Control: public, max-age=31536000, immutable');
    }
    readfile($requestedFile);
    return;
}

// 3. Para todas las demás rutas de cliente (SPA), servir index.php modular o index.html
if (file_exists($publicDir . '/index.php')) {
    require $publicDir . '/index.php';
} else {
    require $publicDir . '/index.html';
}
