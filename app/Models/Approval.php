<?php

declare(strict_types=1);

namespace App\Models;

use App\Config\Database;
use InvalidArgumentException;
use PDO;
use RuntimeException;

/**
 * Modelo de Aprobaciones y Revisiones de Tareas (Fase 1: MVP-06).
 * Implementa el circuito formal de revisión, aprobación y devolución con observaciones
 * conforme a los requisitos RF-13 y las reglas de negocio RN-10, RN-11 y RN-12.
 */
class Approval
{
    public const ESTADO_PENDIENTE = 'PENDIENTE';
    public const ESTADO_APROBADA  = 'APROBADA';
    public const ESTADO_DEVUELTA  = 'DEVUELTA';

    public const ACCION_APROBAR  = 'APROBAR';
    public const ACCION_DEVOLVER = 'DEVOLVER';

    /**
     * Solicita la aprobación de una tarea, moviéndola a la columna 'EN_REVISION'
     * y registrando la solicitud en la tabla de aprobaciones (RN-10).
     */
    public static function solicitar(int $tareaId, int $solicitanteId, ?string $comentario = null, ?int $revisorId = null): array
    {
        $db = Database::getConnection();

        $stmt = $db->prepare("SELECT * FROM tareas WHERE id = :id");
        $stmt->execute([':id' => $tareaId]);
        $tarea = $stmt->fetch();

        if (!$tarea) {
            throw new RuntimeException("La tarea solicitada no existe.", 404);
        }

        // Obtener la columna del tablero configurada como 'EN_REVISION'
        $colRevision = BoardColumn::findByTipoEstado('EN_REVISION');
        if (!$colRevision) {
            throw new RuntimeException("No se encontró una columna de tipo 'EN_REVISION' en el tablero.", 500);
        }

        // Determinar el revisor asignado
        if ($revisorId === null || $revisorId <= 0) {
            $revisorId = !empty($tarea['revisor_id']) ? (int)$tarea['revisor_id'] : null;
        }

        if ($revisorId === null) {
            // Asignar al Jefe de Planeamiento por defecto o al primer jefe disponible
            $jefePlan = User::findByRol('JEFE_PLANEAMIENTO');
            if ($jefePlan) {
                $revisorId = (int)$jefePlan['id'];
            } else {
                $jefePers = User::findByRol('JEFE_PERSONAL');
                $revisorId = $jefePers ? (int)$jefePers['id'] : $solicitanteId;
            }
        }

        $now = gmdate('Y-m-d H:i:s');
        $comentarioLimpio = $comentario !== null ? trim($comentario) : null;

        $db->beginTransaction();
        try {
            // Actualizar la tarea hacia 'EN_REVISION' con requiere_aprobacion activo
            $stmtUpdate = $db->prepare("
                UPDATE tareas
                SET columna_id = :col_id,
                    requiere_aprobacion = 1,
                    revisor_id = :revisor_id,
                    updated_at = :now
                WHERE id = :id
            ");
            $stmtUpdate->execute([
                ':col_id'     => $colRevision['id'],
                ':revisor_id' => $revisorId,
                ':now'        => $now,
                ':id'         => $tareaId
            ]);

            // Insertar solicitud en tarea_aprobaciones
            $stmtAprob = $db->prepare("
                INSERT INTO tarea_aprobaciones (
                    tarea_id, revisor_id, estado, comentario_entrega, fecha_solicitud
                ) VALUES (
                    :tarea_id, :revisor_id, 'PENDIENTE', :comentario, :now
                )
            ");
            $stmtAprob->execute([
                ':tarea_id'   => $tareaId,
                ':revisor_id' => $revisorId,
                ':comentario' => $comentarioLimpio !== '' ? $comentarioLimpio : null,
                ':now'        => $now
            ]);
            $aprobacionId = (int)$db->lastInsertId();

            // Registrar evento inmutable en auditoría
            Audit::log(
                $solicitanteId,
                'APROBACION',
                $aprobacionId,
                'SOLICITAR_APROBACION',
                (string)$tarea['columna_id'],
                'EN_REVISION',
                [
                    'tarea_id'           => $tareaId,
                    'codigo'             => $tarea['codigo'],
                    'revisor_id'         => $revisorId,
                    'comentario_entrega' => $comentarioLimpio
                ],
                $_SERVER['REMOTE_ADDR'] ?? null
            );

            // Si hay comentario de entrega, registrarlo en el muro de comentarios de la tarea
            if ($comentarioLimpio !== null && $comentarioLimpio !== '') {
                Comment::create(
                    $tareaId,
                    $solicitanteId,
                    "📤 Solicitud de Aprobación enviada a revisión: \"{$comentarioLimpio}\"",
                    false
                );
            }

            $db->commit();

            $aprobacion = self::findById($aprobacionId);

            // Disparo de notificación automática (MVP-08 / RF-17 / RN-15)
            try {
                if (class_exists(\App\Services\AutomationEngine::class)) {
                    $solicitante = User::findById($solicitanteId);
                    \App\Services\AutomationEngine::notifyApprovalRequested($tarea, $aprobacion, $solicitante);
                }
            } catch (\Throwable $e) {}

            return $aprobacion;
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    /**
     * Resuelve una solicitud de aprobación (APROBAR o DEVOLVER).
     * Aplica la regla RN-10 (transición atómica a Finalizada) o RN-12 (devolución obligatoria con observaciones).
     */
    public static function resolver(int $tareaId, int $resolutorId, string $accion, ?string $observaciones = null): array
    {
        $db = Database::getConnection();

        $stmt = $db->prepare("SELECT * FROM tareas WHERE id = :id");
        $stmt->execute([':id' => $tareaId]);
        $tarea = $stmt->fetch();

        if (!$tarea) {
            throw new RuntimeException("La tarea no existe.", 404);
        }

        $resolutor = User::findById($resolutorId);
        if (!$resolutor) {
            throw new RuntimeException("Usuario resolutor no válido.", 401);
        }

        // Validación de permisos: Solo jefaturas o el revisor asignado pueden resolver
        if (!self::canResolve($resolutor, $tarea)) {
            throw new RuntimeException("No tienes autorización para resolver la aprobación de esta tarea.", 403);
        }

        $accion = strtoupper(trim($accion));
        if ($accion !== self::ACCION_APROBAR && $accion !== self::ACCION_DEVOLVER) {
            throw new InvalidArgumentException("Acción inválida. Debe ser 'APROBAR' o 'DEVOLVER'.", 422);
        }

        // Buscar la solicitud pendiente más reciente
        $stmtPend = $db->prepare("
            SELECT * FROM tarea_aprobaciones
            WHERE tarea_id = :tarea_id AND estado = 'PENDIENTE'
            ORDER BY id DESC LIMIT 1
        ");
        $stmtPend->execute([':tarea_id' => $tareaId]);
        $aprobacion = $stmtPend->fetch();

        $now = gmdate('Y-m-d H:i:s');

        if ($accion === self::ACCION_APROBAR) {
            // APROBAR (RN-10): Transición atómica hacia la columna FINALIZADA
            $colFinalizada = BoardColumn::findByTipoEstado('FINALIZADA');
            if (!$colFinalizada) {
                throw new RuntimeException("No se encontró la columna 'FINALIZADA' en el tablero.", 500);
            }

            $db->beginTransaction();
            try {
                if ($aprobacion) {
                    $stmtUpdateAprob = $db->prepare("
                        UPDATE tarea_aprobaciones
                        SET estado = 'APROBADA',
                            revisor_id = :resolutor_id,
                            fecha_resolucion = :now
                        WHERE id = :id
                    ");
                    $stmtUpdateAprob->execute([
                        ':resolutor_id' => $resolutorId,
                        ':now'          => $now,
                        ':id'           => $aprobacion['id']
                    ]);
                    $aprobacionId = (int)$aprobacion['id'];
                } else {
                    // Si no había solicitud pendiente explícita, se crea como aprobada directa por el jefe
                    $stmtInsertAprob = $db->prepare("
                        INSERT INTO tarea_aprobaciones (
                            tarea_id, revisor_id, estado, fecha_solicitud, fecha_resolucion
                        ) VALUES (
                            :tarea_id, :revisor_id, 'APROBADA', :now, :now
                        )
                    ");
                    $stmtInsertAprob->execute([
                        ':tarea_id'    => $tareaId,
                        ':revisor_id'  => $resolutorId,
                        ':now'         => $now
                    ]);
                    $aprobacionId = (int)$db->lastInsertId();
                }

                // Mover tarea a Finalizada y registrar fecha_finalizada
                $stmtUpdateTarea = $db->prepare("
                    UPDATE tareas
                    SET columna_id = :col_id,
                        fecha_finalizada = :now,
                        updated_at = :now
                    WHERE id = :id
                ");
                $stmtUpdateTarea->execute([
                    ':col_id' => $colFinalizada['id'],
                    ':now'    => $now,
                    ':id'     => $tareaId
                ]);

                // Registrar en auditoría inmutable
                Audit::log(
                    $resolutorId,
                    'APROBACION',
                    $aprobacionId,
                    'APROBAR',
                    'EN_REVISION',
                    'FINALIZADA',
                    [
                        'tarea_id'   => $tareaId,
                        'codigo'     => $tarea['codigo'],
                        'resolutor'  => $resolutor['nombre']
                    ],
                    $_SERVER['REMOTE_ADDR'] ?? null
                );

                // Comentario de trazabilidad
                Comment::create(
                    $tareaId,
                    $resolutorId,
                    "✅ Tarea Aprobada formalmente por {$resolutor['nombre']} ({$resolutor['cargo']}). Estado finalizado exitosamente.",
                    false
                );

                $db->commit();

                // Desbloqueo en cascada de dependientes (RN-08, RF-16)
                try {
                    if (class_exists(TaskDependency::class)) {
                        TaskDependency::onTaskCompleted($tareaId, $resolutor);
                    }
                } catch (\Throwable $e) {}

                $aprobacion = self::findById($aprobacionId);

                // Disparo de notificación automática (MVP-08 / RF-17 / RN-15)
                try {
                    if (class_exists(\App\Services\AutomationEngine::class)) {
                        \App\Services\AutomationEngine::notifyApprovalResolved($tarea, $aprobacion, 'APROBAR', $resolutor);
                    }
                } catch (\Throwable $e) {}

                return $aprobacion;
            } catch (\Throwable $e) {
                $db->rollBack();
                throw $e;
            }
        } else {
            // DEVOLVER (RN-12): Exige observaciones obligatorias no vacías
            $observacionesLimpias = $observaciones !== null ? trim($observaciones) : '';
            if ($observacionesLimpias === '') {
                throw new InvalidArgumentException("Las observaciones son obligatorias al devolver una tarea para corrección.", 422);
            }

            // Columna destino: EN_PROCESO
            $colEnProceso = BoardColumn::findByTipoEstado('EN_PROCESO');
            if (!$colEnProceso) {
                throw new RuntimeException("No se encontró la columna 'EN_PROCESO' en el tablero.", 500);
            }

            $db->beginTransaction();
            try {
                if ($aprobacion) {
                    $stmtUpdateAprob = $db->prepare("
                        UPDATE tarea_aprobaciones
                        SET estado = 'DEVUELTA',
                            observaciones_devolucion = :obs,
                            revisor_id = :resolutor_id,
                            fecha_resolucion = :now
                        WHERE id = :id
                    ");
                    $stmtUpdateAprob->execute([
                        ':obs'          => $observacionesLimpias,
                        ':resolutor_id' => $resolutorId,
                        ':now'          => $now,
                        ':id'           => $aprobacion['id']
                    ]);
                    $aprobacionId = (int)$aprobacion['id'];
                } else {
                    $stmtInsertAprob = $db->prepare("
                        INSERT INTO tarea_aprobaciones (
                            tarea_id, revisor_id, estado, observaciones_devolucion, fecha_solicitud, fecha_resolucion
                        ) VALUES (
                            :tarea_id, :revisor_id, 'DEVUELTA', :obs, :now, :now
                        )
                    ");
                    $stmtInsertAprob->execute([
                        ':tarea_id'    => $tareaId,
                        ':revisor_id'  => $resolutorId,
                        ':obs'         => $observacionesLimpias,
                        ':now'         => $now
                    ]);
                    $aprobacionId = (int)$db->lastInsertId();
                }

                // Regresar tarea a EN_PROCESO y asegurar fecha_finalizada = NULL
                $stmtUpdateTarea = $db->prepare("
                    UPDATE tareas
                    SET columna_id = :col_id,
                        fecha_finalizada = NULL,
                        updated_at = :now
                    WHERE id = :id
                ");
                $stmtUpdateTarea->execute([
                    ':col_id' => $colEnProceso['id'],
                    ':now'    => $now,
                    ':id'     => $tareaId
                ]);

                // Registrar en auditoría inmutable
                Audit::log(
                    $resolutorId,
                    'APROBACION',
                    $aprobacionId,
                    'DEVOLVER',
                    'EN_REVISION',
                    'EN_PROCESO',
                    [
                        'tarea_id'                 => $tareaId,
                        'codigo'                   => $tarea['codigo'],
                        'observaciones_devolucion' => $observacionesLimpias,
                        'resolutor'                => $resolutor['nombre']
                    ],
                    $_SERVER['REMOTE_ADDR'] ?? null
                );

                // Comentario automático de retroalimentación
                Comment::create(
                    $tareaId,
                    $resolutorId,
                    "⚠️ Tarea Devuelta por {$resolutor['nombre']}. Observaciones de subsanación: \"{$observacionesLimpias}\". La tarea regresa a 'En proceso'.",
                    false
                );

                $db->commit();

                // Re-evaluar dependientes tras devolución de tarea (RN-14)
                try {
                    if (class_exists(TaskDependency::class)) {
                        TaskDependency::onTaskReopened($tareaId, $resolutor);
                    }
                } catch (\Throwable $e) {}

                $aprobacion = self::findById($aprobacionId);

                // Disparo de notificación automática (MVP-08 / RF-17 / RN-15)
                try {
                    if (class_exists(\App\Services\AutomationEngine::class)) {
                        \App\Services\AutomationEngine::notifyApprovalResolved($tarea, $aprobacion, 'DEVOLVER', $resolutor);
                    }
                } catch (\Throwable $e) {}

                return $aprobacion;
            } catch (\Throwable $e) {
                $db->rollBack();
                throw $e;
            }
        }
    }

    /**
     * Comprueba si el usuario tiene autorización para resolver la aprobación de la tarea.
     */
    public static function canResolve(array $user, array $tarea): bool
    {
        // Jefes autorizados siempre pueden resolver tareas oficiales
        if (User::isJefe($user)) {
            return true;
        }

        // Si el usuario fue explícitamente configurado como revisor de la tarea
        if (!empty($tarea['revisor_id']) && (int)$tarea['revisor_id'] === (int)$user['id']) {
            return true;
        }

        return false;
    }

    /**
     * Retorna la lista de tareas en revisión con aprobaciones pendientes para jefatura (RF-13).
     */
    public static function listPendientesForUser(array $user): array
    {
        $db = Database::getConnection();

        // Asistentes comunes no tienen acceso a la bandeja de jefatura
        if (!User::isJefe($user)) {
            return [];
        }

        $sql = "
            SELECT 
                COALESCE(a.id, 0) AS aprobacion_id,
                t.id AS tarea_id,
                COALESCE(a.estado, 'PENDIENTE') AS estado_aprobacion,
                a.comentario_entrega,
                COALESCE(a.fecha_solicitud, t.updated_at, t.created_at) AS fecha_solicitud,
                t.codigo AS tarea_codigo,
                t.titulo AS tarea_titulo,
                t.descripcion AS tarea_descripcion,
                t.prioridad AS tarea_prioridad,
                t.tipo_tarea AS tarea_tipo,
                t.fecha_limite AS tarea_fecha_limite,
                u_resp.id AS responsable_id,
                u_resp.nombre AS responsable_nombre,
                u_resp.cargo AS responsable_cargo,
                COALESCE(u_rev.id, t.revisor_id) AS revisor_id,
                COALESCE(u_rev.nombre, 'Sin asignar') AS revisor_nombre,
                c.nombre AS columna_nombre,
                c.tipo_estado AS columna_tipo_estado
            FROM tareas t
            JOIN tableros_columnas c ON t.columna_id = c.id
            JOIN usuarios u_resp ON t.responsable_id = u_resp.id
            LEFT JOIN tarea_aprobaciones a ON a.tarea_id = t.id AND a.estado = 'PENDIENTE'
            LEFT JOIN usuarios u_rev ON COALESCE(a.revisor_id, t.revisor_id) = u_rev.id
            WHERE (c.tipo_estado = 'EN_REVISION' OR a.estado = 'PENDIENTE')
            GROUP BY t.id
            ORDER BY fecha_solicitud ASC
        ";

        $stmt = $db->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Retorna el historial cronológico de aprobaciones de una tarea específica.
     */
    public static function listByTaskId(int $tareaId): array
    {
        $db = Database::getConnection();

        $stmt = $db->prepare("
            SELECT 
                a.id,
                a.tarea_id,
                a.revisor_id,
                u_rev.nombre AS revisor_nombre,
                u_rev.cargo AS revisor_cargo,
                a.estado,
                a.comentario_entrega,
                a.observaciones_devolucion,
                a.fecha_solicitud,
                a.fecha_resolucion
            FROM tarea_aprobaciones a
            LEFT JOIN usuarios u_rev ON a.revisor_id = u_rev.id
            WHERE a.tarea_id = :tarea_id
            ORDER BY a.fecha_solicitud DESC, a.id DESC
        ");
        $stmt->execute([':tarea_id' => $tareaId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Busca una aprobación por ID.
     */
    public static function findById(int $id): ?array
    {
        $db = Database::getConnection();

        $stmt = $db->prepare("
            SELECT 
                a.*,
                t.codigo AS tarea_codigo,
                t.titulo AS tarea_titulo,
                u.nombre AS revisor_nombre
            FROM tarea_aprobaciones a
            JOIN tareas t ON a.tarea_id = t.id
            LEFT JOIN usuarios u ON a.revisor_id = u.id
            WHERE a.id = :id
        ");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Obtiene la solicitud de aprobación pendiente activa de una tarea.
     */
    public static function findPendienteByTaskId(int $tareaId): ?array
    {
        $db = Database::getConnection();

        $stmt = $db->prepare("
            SELECT a.*, u.nombre AS revisor_nombre
            FROM tarea_aprobaciones a
            LEFT JOIN usuarios u ON a.revisor_id = u.id
            WHERE a.tarea_id = :tarea_id AND a.estado = 'PENDIENTE'
            ORDER BY a.id DESC LIMIT 1
        ");
        $stmt->execute([':tarea_id' => $tareaId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }
}
