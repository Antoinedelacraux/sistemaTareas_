<?php

declare(strict_types=1);

$suites = [
    'auth_test.php',
    'users_test.php',
    'user_management_test.php',
    'tasks_test.php',
    'kanban_test.php',
    'subtasks_comments_test.php',
    'approvals_test.php',
    'dependencies_test.php',
    'notifications_automation_test.php',
    'qa_security_pilot_test.php'
];

$passedCount = 0;
$failedCount = 0;

echo "=================================================================\n";
echo "EJECUCIÓN INTEGRAL DE LAS " . count($suites) . " SUITES DE PRUEBAS DEL SISTEMA\n";
echo "=================================================================\n\n";

foreach ($suites as $suite) {
    echo ">>> Ejecutando: tests/{$suite} ...\n";
    $cmd = 'php ' . escapeshellarg(__DIR__ . '/' . $suite);
    passthru($cmd, $returnCode);

    if ($returnCode === 0) {
        $passedCount++;
        echo ">>> OK: tests/{$suite}\n\n";
    } else {
        $failedCount++;
        echo ">>> ERROR: tests/{$suite} (Código: {$returnCode})\n\n";
    }
}

echo "=================================================================\n";
echo "RESUMEN FINAL:\n";
echo "Suites Ejecutadas: " . count($suites) . "\n";
echo "Suites Aprobadas:  {$passedCount}\n";
echo "Suites Fallidas:   {$failedCount}\n";
echo "=================================================================\n";

if ($failedCount === 0) {
    echo "¡TODAS LAS SUITES (10/10) HAN PASADO CON 100% DE ÉXITO!\n";
    exit(0);
} else {
    echo "SE DETECTARON SUITES FALLIDAS.\n";
    exit(1);
}
