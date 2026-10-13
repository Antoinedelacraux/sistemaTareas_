<?php

declare(strict_types=1);

namespace App\Models;

use App\Config\Database;
use App\Config\Env;
use PDO;

/**
 * Modelo de Sesión con tokens criptográficos de alta entropía (RF-01).
 */
class Session
{
    /**
     * Crea una nueva sesión y retorna el token en texto plano para el cliente.
     */
    public static function create(int $userId, ?string $ip = null, ?string $userAgent = null): string
    {
        $db = Database::getConnection();

        // Token seguro de 64 caracteres hexadecimales (256 bits de entropía)
        $rawToken = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $rawToken);

        $lifetimeHours = (int)Env::get('SESSION_LIFETIME_HOURS', 12);
        $expiraAt = gmdate('Y-m-d H:i:s', time() + ($lifetimeHours * 3600));

        $sql = "INSERT INTO sesiones (usuario_id, token_hash, ip_address, user_agent, expira_at, created_at)
                VALUES (:usuario_id, :token_hash, :ip_address, :user_agent, :expira_at, :created_at)";

        $stmt = $db->prepare($sql);
        $stmt->execute([
            ':usuario_id'  => $userId,
            ':token_hash'  => $tokenHash,
            ':ip_address'  => $ip ?? ($_SERVER['REMOTE_ADDR'] ?? null),
            ':user_agent'  => $userAgent ?? ($_SERVER['HTTP_USER_AGENT'] ?? null),
            ':expira_at'   => $expiraAt,
            ':created_at'  => gmdate('Y-m-d H:i:s')
        ]);

        return $rawToken;
    }

    /**
     * Busca una sesión activa y no expirada a partir del token crudo.
     * Retorna array con datos de sesión y datos del usuario asociado.
     */
    public static function findByToken(string $rawToken): ?array
    {
        $db = Database::getConnection();
        $tokenHash = hash('sha256', $rawToken);

        $sql = "SELECT s.*, u.codigo, u.nombre, u.email, u.rol, u.cargo, u.activo
                FROM sesiones s
                INNER JOIN usuarios u ON s.usuario_id = u.id
                WHERE s.token_hash = :token_hash
                  AND s.expira_at > :now
                LIMIT 1";

        $stmt = $db->prepare($sql);
        $stmt->execute([
            ':token_hash' => $tokenHash,
            ':now'        => gmdate('Y-m-d H:i:s')
        ]);

        $session = $stmt->fetch();
        return $session ?: null;
    }

    /**
     * Invalida (elimina) una sesión activa por su token.
     */
    public static function invalidate(string $rawToken): bool
    {
        $db = Database::getConnection();
        $tokenHash = hash('sha256', $rawToken);

        $stmt = $db->prepare("DELETE FROM sesiones WHERE token_hash = :token_hash");
        return $stmt->execute([':token_hash' => $tokenHash]);
    }
}
