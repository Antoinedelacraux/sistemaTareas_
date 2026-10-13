<?php

declare(strict_types=1);

// Simulación de entorno HTTP para pruebas de enrutamiento
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/api/health';

ob_start();
require __DIR__ . '/../public_html/api/index.php';
$output = ob_get_clean();

$response = json_decode($output, true);

if (!is_array($response)) {
    echo "FAIL: Salida no es un JSON válido.\n";
    echo "Raw output: " . $output . "\n";
    exit(1);
}

if (!isset($response['success']) || $response['success'] !== true) {
    echo "FAIL: Campo success no es true.\n";
    exit(1);
}

if (!isset($response['data']['status']) || $response['data']['status'] !== 'ok') {
    echo "FAIL: Estado de salud no es ok.\n";
    exit(1);
}

echo "PASS: Endpoint /api/health respondió exitosamente con status: ok, php: " . $response['data']['php_version'] . "\n";
exit(0);
