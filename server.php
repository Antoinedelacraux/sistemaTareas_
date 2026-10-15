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

if ($uri === '/index.php') {
    header('Location: /', true, 302);
    return;
}

if ($uri === '/instalar.php') {
    require $publicDir . '/instalar.php';
    return;
}

// 1. Delegar a la API REST si la ruta inicia con /api
if (str_starts_with($uri, '/api')) {
    require $publicDir . '/api/index.php';
    return;
}

// 2. Servir archivos estáticos reales desde public_html
if ($uri !== '/' && file_exists($requestedFile) && !is_dir($requestedFile)) {
    $ext = strtolower(pathinfo($requestedFile, PATHINFO_EXTENSION));
    if ($ext === 'php') {
        http_response_code(404);
        return;
    }
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
    http_response_code(500);
    exit('No se encontrÃ³ el punto de entrada de la aplicaciÃ³n.');
}
