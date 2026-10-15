<?php

declare(strict_types=1);

namespace App\Models;

use App\Config\Database;
use App\Config\Env;
use PDO;

/**
 * Registro durable de cambios compartidos para la sincronización de la SPA.
 * Los eventos no contienen contenido funcional; el cliente vuelve a consultar
 * el recurso por sus endpoints autorizados.
 */
class RealtimeEvent
{
    private const MAX_BATCH_SIZE = 100;
    private static bool $schemaEnsured = false;
    private static bool $prunedInRequest = false;

    /** Asegura la estructura al ejecutar diagnósticos o tras actualizar una instalación existente. */
    public static function ensureStorage(): void
    {
        self::ensureSchema();
    }

    public static function publish(string $type, string $entityType, int $entityId, ?int $taskId, ?int $actorId, array $recipientIds): ?int
    {
        $recipientIds = array_values(array_unique(array_filter(array_map('intval', $recipientIds), static fn (int $id): bool => $id > 0)));
        if ($recipientIds === []) {
            return null;
        }

        self::ensureSchema();
        $db = Database::getConnection();
        $startedTransaction = !$db->inTransaction();
        if ($startedTransaction) {
            $db->beginTransaction();
        }

        try {
            $now = gmdate('Y-m-d H:i:s');
            $stmt = $db->prepare(
                'INSERT INTO sincronizacion_eventos (tipo, entidad_tipo, entidad_id, tarea_id, actor_id, created_at)
                 VALUES (:tipo, :entidad_tipo, :entidad_id, :tarea_id, :actor_id, :created_at)'
            );
            $stmt->execute([
                ':tipo' => $type,
                ':entidad_tipo' => $entityType,
                ':entidad_id' => $entityId,
                ':tarea_id' => $taskId,
                ':actor_id' => $actorId,
                ':created_at' => $now,
            ]);
            $eventId = (int)$db->lastInsertId();

            $recipientStmt = $db->prepare(
                'INSERT INTO sincronizacion_evento_destinatarios (evento_id, usuario_id, created_at)
                 VALUES (:evento_id, :usuario_id, :created_at)'
            );
            foreach ($recipientIds as $recipientId) {
                $recipientStmt->execute([
                    ':evento_id' => $eventId,
                    ':usuario_id' => $recipientId,
                    ':created_at' => $now,
                ]);
            }

            if ($startedTransaction) {
                $db->commit();
            }
            self::pruneExpired();

            return $eventId;
        } catch (\Throwable $e) {
            if ($startedTransaction && $db->inTransaction()) {
                $db->rollBack();
            }
            error_log('No se pudo publicar evento de sincronización: ' . $e->getMessage());
            return null;
        }
    }

    public static function publishTask(string $type, array $task, array $actor, array $previousTask = [], array $extraRecipientIds = []): ?int
    {
        $taskId = (int)($task['id'] ?? $previousTask['id'] ?? 0);
        if ($taskId <= 0) {
            return null;
        }

        return self::publish(
            $type,
            'TAREA',
            $taskId,
            $taskId,
            (int)($actor['id'] ?? 0) ?: null,
            array_merge(self::taskAudience($task, $previousTask), $extraRecipientIds)
        );
    }

    public static function publishSystem(string $type, string $entityType, int $entityId, array $actor, array $extraRecipientIds = []): ?int
    {
        return self::publish(
            $type,
            $entityType,
            $entityId,
            null,
            (int)($actor['id'] ?? 0) ?: null,
            array_merge(self::allActiveUserIds(), $extraRecipientIds)
        );
    }

    public static function publishForJefatura(string $type, string $entityType, int $entityId, array $actor, array $extraRecipientIds = []): ?int
    {
        return self::publish(
            $type,
            $entityType,
            $entityId,
            null,
            (int)($actor['id'] ?? 0) ?: null,
            array_merge(self::jefaturaUserIds(), $extraRecipientIds)
        );
    }

    public static function eventsForUser(int $userId, int $afterId): array
    {
        self::ensureSchema();
        $db = Database::getConnection();
        $stmt = $db->prepare(
            'SELECT e.id, e.tipo, e.entidad_tipo, e.entidad_id, e.tarea_id, e.created_at
             FROM sincronizacion_eventos e
             INNER JOIN sincronizacion_evento_destinatarios d ON d.evento_id = e.id
             WHERE d.usuario_id = :usuario_id AND e.id > :after_id
             ORDER BY e.id ASC
             LIMIT ' . self::MAX_BATCH_SIZE
        );
        $stmt->bindValue(':usuario_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':after_id', max(0, $afterId), PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public static function latestCursorForUser(int $userId): int
    {
        self::ensureSchema();
        $db = Database::getConnection();
        $stmt = $db->prepare('SELECT MAX(evento_id) FROM sincronizacion_evento_destinatarios WHERE usuario_id = :usuario_id');
        $stmt->execute([':usuario_id' => $userId]);
        return (int)($stmt->fetchColumn() ?: 0);
    }

    public static function taskAudience(array $task, array $previousTask = []): array
    {
        $ids = self::jefaturaUserIds();
        foreach ([$task, $previousTask] as $candidate) {
            foreach (['creador_id', 'responsable_id', 'revisor_id'] as $field) {
                if (!empty($candidate[$field])) {
                    $ids[] = (int)$candidate[$field];
                }
            }
        }
        return self::activeOnly($ids);
    }

    public static function allActiveUserIds(): array
    {
        $db = Database::getConnection();
        $rows = $db->query('SELECT id FROM usuarios WHERE activo = 1')->fetchAll(PDO::FETCH_COLUMN);
        return array_map('intval', $rows);
    }

    public static function jefaturaUserIds(): array
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT id FROM usuarios WHERE activo = 1 AND rol IN (:rol_personal, :rol_planeamiento)");
        $stmt->execute([
            ':rol_personal' => User::ROL_JEFE_PERSONAL,
            ':rol_planeamiento' => User::ROL_JEFE_PLANEAMIENTO,
        ]);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    private static function activeOnly(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn (int $id): bool => $id > 0)));
        if ($ids === []) {
            return [];
        }
        $db = Database::getConnection();
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $db->prepare("SELECT id FROM usuarios WHERE activo = 1 AND id IN ({$placeholders})");
        $stmt->execute($ids);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    private static function ensureSchema(): void
    {
        if (self::$schemaEnsured) {
            return;
        }
        $db = Database::getConnection();
        $driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            $db->exec('CREATE TABLE IF NOT EXISTS sincronizacion_eventos (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                tipo TEXT NOT NULL,
                entidad_tipo TEXT NOT NULL,
                entidad_id INTEGER NOT NULL,
                tarea_id INTEGER NULL,
                actor_id INTEGER NULL,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
            )');
            $db->exec('CREATE TABLE IF NOT EXISTS sincronizacion_evento_destinatarios (
                evento_id INTEGER NOT NULL,
                usuario_id INTEGER NOT NULL,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (evento_id, usuario_id)
            )');
        } else {
            $db->exec('CREATE TABLE IF NOT EXISTS sincronizacion_eventos (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                tipo VARCHAR(60) NOT NULL,
                entidad_tipo VARCHAR(30) NOT NULL,
                entidad_id INT UNSIGNED NOT NULL,
                tarea_id INT UNSIGNED NULL,
                actor_id INT UNSIGNED NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                INDEX idx_sync_eventos_created (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
            $db->exec('CREATE TABLE IF NOT EXISTS sincronizacion_evento_destinatarios (
                evento_id BIGINT UNSIGNED NOT NULL,
                usuario_id INT UNSIGNED NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (evento_id, usuario_id),
                INDEX idx_sync_destinatario_cursor (usuario_id, evento_id),
                CONSTRAINT fk_sync_dest_evento FOREIGN KEY (evento_id) REFERENCES sincronizacion_eventos(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        }
        $db->exec('CREATE INDEX IF NOT EXISTS idx_sync_eventos_tarea ON sincronizacion_eventos(tarea_id)');
        $db->exec('CREATE INDEX IF NOT EXISTS idx_sync_destinatario_cursor ON sincronizacion_evento_destinatarios(usuario_id, evento_id)');
        self::$schemaEnsured = true;
    }

    private static function pruneExpired(): void
    {
        if (self::$prunedInRequest) {
            return;
        }
        self::$prunedInRequest = true;
        $days = max(1, min(90, (int)Env::get('REALTIME_EVENT_RETENTION_DAYS', 14)));
        $cutoff = gmdate('Y-m-d H:i:s', time() - ($days * 86400));
        $db = Database::getConnection();
        try {
            if ($db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
                $stmt = $db->prepare('DELETE FROM sincronizacion_evento_destinatarios WHERE evento_id IN (SELECT id FROM sincronizacion_eventos WHERE created_at < :cutoff)');
                $stmt->execute([':cutoff' => $cutoff]);
            }
            $stmt = $db->prepare('DELETE FROM sincronizacion_eventos WHERE created_at < :cutoff');
            $stmt->execute([':cutoff' => $cutoff]);
        } catch (\Throwable $e) {
            error_log('No se pudo depurar eventos de sincronización: ' . $e->getMessage());
        }
    }
}
