<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';

use App\Config\Env;
use App\Services\InitialSetupService;

function installerEscape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

$enabled = filter_var(Env::get('INSTALLER_ENABLED', false), FILTER_VALIDATE_BOOL);
$token = (string) Env::get('INSTALL_TOKEN', '');
$lockFile = dirname(__DIR__) . '/storage/installation.lock';
$error = '';
$success = '';

if (!$enabled || strlen($token) < 32 || file_exists($lockFile) || InitialSetupService::hasUsers()) {
    http_response_code(404);
    exit('Instalador no disponible.');
}

header('Cache-Control: no-store, max-age=0');
header('X-Frame-Options: DENY');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedToken = (string) ($_POST['install_token'] ?? '');
    if (!hash_equals($token, $submittedToken)) {
        $error = 'Token de instalación inválido.';
    } elseif ((string) ($_POST['password'] ?? '') !== (string) ($_POST['password_confirmation'] ?? '')) {
        $error = 'Las contraseñas no coinciden.';
    } else {
        try {
            $admin = InitialSetupService::createFirstAdmin([
                'nombre' => $_POST['nombre'] ?? '',
                'email' => $_POST['email'] ?? '',
                'cargo' => $_POST['cargo'] ?? '',
                'password' => $_POST['password'] ?? ''
            ]);
            @file_put_contents($lockFile, 'Instalación completada el ' . gmdate('c') . ' para ' . $admin['email'] . PHP_EOL, LOCK_EX);
            $success = 'Cuenta inicial creada. Desactiva INSTALLER_ENABLED en .env y entra al sistema.';
        } catch (Throwable $exception) {
            $error = $exception->getMessage();
        }
    }
}
?><!doctype html>
<html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Instalación inicial | TAL S.A.</title><style>body{font-family:system-ui,sans-serif;background:#f8fafc;color:#0f172a;margin:0;padding:2rem}.card{max-width:560px;margin:auto;background:#fff;padding:2rem;border:1px solid #e2e8f0;border-radius:16px;box-shadow:0 10px 30px #0f172a14}label{display:block;font-weight:600;margin-top:1rem}input{width:100%;box-sizing:border-box;padding:.75rem;margin-top:.35rem;border:1px solid #94a3b8;border-radius:8px}button{width:100%;margin-top:1.5rem;padding:.8rem;border:0;border-radius:8px;background:#4338ca;color:#fff;font-weight:700;cursor:pointer}.error{background:#fff1f2;color:#b91c1c;padding:.75rem;border-radius:8px}.success{background:#ecfdf5;color:#047857;padding:.75rem;border-radius:8px}</style></head><body><main class="card"><h1>Instalación inicial</h1><p>Crea la primera cuenta de Jefe de Área. Este formulario solo funciona una vez sobre una base vacía.</p><?php if ($error !== ''): ?><p class="error"><?= installerEscape($error) ?></p><?php endif; ?><?php if ($success !== ''): ?><p class="success"><?= installerEscape($success) ?></p><?php else: ?><form method="post" autocomplete="off"><label>Token de instalación<input name="install_token" type="password" required></label><label>Nombre completo<input name="nombre" required maxlength="100" value="<?= installerEscape((string) ($_POST['nombre'] ?? '')) ?>"></label><label>Correo institucional<input name="email" type="email" required maxlength="120" value="<?= installerEscape((string) ($_POST['email'] ?? '')) ?>"></label><label>Cargo<input name="cargo" maxlength="100" value="<?= installerEscape((string) ($_POST['cargo'] ?? 'Jefe de Área')) ?>"></label><label>Contraseña inicial (12+ caracteres)<input name="password" type="password" minlength="12" required></label><label>Confirmar contraseña<input name="password_confirmation" type="password" minlength="12" required></label><button type="submit">Crear cuenta inicial</button></form><?php endif; ?></main></body></html>
