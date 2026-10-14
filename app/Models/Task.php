<?php

declare(strict_types=1);

namespace App\Models;

use App\Config\Database;
use PDO;

/**
 * Modelo de Tarea (Fase 1: MVP-03).
 * Maneja el ciclo de vida, alcance, persistencia y reglas de negocio
 * asociadas a tareas personales y oficiales del área (RF-03, RF-04, RF-05, RF-06, RF-07, RF-09).
 */
class Task
{
    public const TIPO_PERSONAL = 'PERSONAL';
    public const TIPO_AREA = 'AREA';

    public const PRIORIDAD_BAJA = 'BAJA';
    public const PRIORIDAD_MEDIA = 'MEDIA';
    public const PRIORIDAD_ALTA = 'ALTA';
    public const PRIORIDAD_URGENTE = 'URGENTE';

    /**
     * Genera un código correlativo único con formato TAR-YYYY-NNNN.
     */
    public static function generateCodigo(): string
    {
        $db = Database::getConnection();
        $year = (int)gmdate('Y');
        $prefix = "TAR-{$year}-";

        $stmt = $db->prepare("SELECT COUNT(*) AS total FROM tareas WHERE codigo LIKE :prefix");
        $stmt->execute([':prefix' => $prefix . '%']);
        $count = (int)$stmt->fetchColumn();

        $nextNumber = str_pad((string)($count + 1), 4, '0', STR_PAD_LEFT);
        return "{$prefix}{$nextNumber}";
    }

    /**
     * Busca una tarea por su ID con datos relacionados (columna, creador, responsable, revisor).
     */
    public static function findById(int $id): ?array
    {
        $db = Database::getConnection();
        $sql = "
            SELECT 
                t.*,
                c.nombre AS columna_nombre,
                c.tipo_estado AS columna_tipo_estado,
                c.color_hex AS columna_color_hex,
                uc.nombre AS creador_nombre,
                uc.email AS creador_email,
                uc.rol AS creador_rol,
                ur.nombre AS responsable_nombre,
                ur.email AS responsable_email,
                ur.rol AS responsable_rol,
                ux.nombre AS revisor_nombre,
                ux.email AS revisor_email
            FROM tareas t
            INNER JOIN tableros_columnas c ON t.columna_id = c.id
            INNER JOIN usuarios uc ON t.creador_id = uc.id
            INNER JOIN usuarios ur ON t.responsable_id = ur.id
            LEFT JOIN usuarios ux ON t.revisor_id = ux.id
            WHERE t.id = :id
            LIMIT 1
        ";

        $stmt = $db->prepare($sql);
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();

        return $row ? self::format($row) : null;
    }

    /**
     * Lista tareas según el alcance y rol del usuario autenticado (RF-07, RN-01, RN-02, RN-05).
     * - Asistentes: solo tareas donde son responsable o creador.
     * - Jefes: todas las tareas del área (tipo AREA) y sus propias tareas personales.
     */
    public static function listForUser(array $currentUser, array $filters = []): array
    {
        $db = Database::getConnection();
        $isJefe = in_array($currentUser['rol'] ?? '', [User::ROL_JEFE_PERSONAL, User::ROL_JEFE_PLANEAMIENTO], true);
        $userId = (int)$currentUser['id'];

        $sql = "
            SELECT 
                t.*,
                c.nombre AS columna_nombre,
                c.tipo_estado AS columna_tipo_estado,
                c.color_hex AS columna_color_hex,
                uc.nombre AS creador_nombre,
                uc.email AS creador_email,
                ur.nombre AS responsable_nombre,
                ur.email AS responsable_email
            FROM tareas t
            INNER JOIN tableros_columnas c ON t.columna_id = c.id
            INNER JOIN usuarios uc ON t.creador_id = uc.id
            INNER JOIN usuarios ur ON t.responsable_id = ur.id
            WHERE 1=1
        ";

        $params = [];

        // Filtro por alcance estricto según rol y vista solicitada (RN-01, RN-02, RN-05)
        $scope = strtolower(trim((string)($filters['scope'] ?? $filters['alcance'] ?? '')));
        $tipoFilter = !empty($filters['tipo']) ? strtoupper(trim((string)$filters['tipo'])) : null;

        if ($scope === 'personal' || $tipoFilter === 'PERSONAL') {
            // Vista Personal: exclusivamente tareas personales del usuario autenticado
            $sql .= " AND t.tipo_tarea = 'PERSONAL' AND (t.responsable_id = :current_user_id OR t.creador_id = :current_user_id)";
            $params[':current_user_id'] = $userId;
            // En Vista Personal las tareas permanecen hasta que el usuario decida borrarlas (no expiran al historial)
        } elseif ($scope === 'team') {
            // Tablero del equipo (jefatura): todas las tareas de área
            $sql .= " AND t.tipo_tarea = 'AREA'";
            $cutoff24h = gmdate('Y-m-d H:i:s', time() - 86400);
            if (!empty($filters['solo_archivadas'])) {
                $sql .= " AND c.tipo_estado = 'FINALIZADA' AND t.fecha_finalizada IS NOT NULL AND t.fecha_finalizada <= :cutoff_historial";
                $params[':cutoff_historial'] = $cutoff24h;
            } elseif (empty($filters['incluir_archivadas'])) {
                $sql .= " AND NOT (c.tipo_estado = 'FINALIZADA' AND t.fecha_finalizada IS NOT NULL AND t.fecha_finalizada <= :cutoff_kanban)";
                $params[':cutoff_kanban'] = $cutoff24h;
            }
        } else {
            // Vista Área (scope 'area' o general): exclusivamente tareas oficiales del área
            $sql .= " AND t.tipo_tarea = 'AREA'";
            if (!$isJefe) {
                // Asistente solo ve las tareas del área donde es responsable o creador
                $sql .= " AND (t.responsable_id = :current_user_id OR t.creador_id = :current_user_id)";
                $params[':current_user_id'] = $userId;
            }
            // Regla de expiración de 24h para tareas oficiales del área hacia el historial
            $cutoff24h = gmdate('Y-m-d H:i:s', time() - 86400);
            if (!empty($filters['solo_archivadas'])) {
                $sql .= " AND c.tipo_estado = 'FINALIZADA' AND t.fecha_finalizada IS NOT NULL AND t.fecha_finalizada <= :cutoff_historial";
                $params[':cutoff_historial'] = $cutoff24h;
            } elseif (empty($filters['incluir_archivadas'])) {
                $sql .= " AND NOT (c.tipo_estado = 'FINALIZADA' AND t.fecha_finalizada IS NOT NULL AND t.fecha_finalizada <= :cutoff_kanban)";
                $params[':cutoff_kanban'] = $cutoff24h;
            }
        }

        // Filtro opcional por columna_id
        if (!empty($filters['columna_id'])) {
            $sql .= " AND t.columna_id = :columna_id";
            $params[':columna_id'] = (int)$filters['columna_id'];
        }

        // Filtro opcional por prioridad
        if (!empty($filters['prioridad'])) {
            $sql .= " AND t.prioridad = :prioridad";
            $params[':prioridad'] = strtoupper(trim((string)$filters['prioridad']));
        }

        // Filtro opcional por responsable_id (solo si está dentro de su alcance)
        if (!empty($filters['responsable_id'])) {
            $targetRespId = (int)$filters['responsable_id'];
            if ($isJefe || $targetRespId === $userId) {
                $sql .= " AND t.responsable_id = :responsable_id";
                $params[':responsable_id'] = $targetRespId;
            } else {
                return [];
            }
        }

        // Búsqueda textual por título o código
        if (!empty($filters['buscar'])) {
            $sql .= " AND (t.titulo LIKE :buscar OR t.codigo LIKE :buscar)";
            $params[':buscar'] = '%' . trim((string)$filters['buscar']) . '%';
        }

        $sql .= " ORDER BY CASE t.prioridad 
                    WHEN 'URGENTE' THEN 1 
                    WHEN 'ALTA' THEN 2 
                    WHEN 'MEDIA' THEN 3 
                    WHEN 'BAJA' THEN 4 
                    ELSE 5 END ASC, 
                    t.fecha_limite ASC, 
                    t.id DESC";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        return array_map(fn($row) => self::format($row), $rows);
    }

    /**
     * Lista tareas archivadas en el historial (finalizadas hace más de 24 horas).
     * Acceso reservado exclusivamente para Jefatura de Área y Jefatura de Personal.
     */
    public static function listArchived(array $currentUser, array $filters = []): array
    {
        $db = Database::getConnection();
        $isJefe = in_array($currentUser['rol'] ?? '', [User::ROL_JEFE_PERSONAL, User::ROL_JEFE_PLANEAMIENTO], true) || User::isJefe($currentUser);
        if (!$isJefe) {
            return [];
        }

        $cutoff24h = gmdate('Y-m-d H:i:s', time() - 86400);

        $sql = "
            SELECT 
                t.*,
                c.nombre AS columna_nombre,
                c.tipo_estado AS columna_tipo_estado,
                c.color_hex AS columna_color_hex,
                uc.nombre AS creador_nombre,
                uc.email AS creador_email,
                ur.nombre AS responsable_nombre,
                ur.email AS responsable_email,
                (SELECT COUNT(*) FROM subtareas s WHERE s.tarea_id = t.id) AS total_subtareas,
                (SELECT COUNT(*) FROM subtareas s WHERE s.tarea_id = t.id AND s.completada = 1) AS subtareas_completadas,
                (SELECT COUNT(*) FROM tarea_comentarios tc WHERE tc.tarea_id = t.id) AS total_comentarios
            FROM tareas t
            INNER JOIN tableros_columnas c ON t.columna_id = c.id
            INNER JOIN usuarios uc ON t.creador_id = uc.id
            INNER JOIN usuarios ur ON t.responsable_id = ur.id
            WHERE c.tipo_estado = 'FINALIZADA'
              AND t.fecha_finalizada IS NOT NULL
              AND t.fecha_finalizada <= :cutoff
              AND t.tipo_tarea = 'AREA'
        ";

        $params = [':cutoff' => $cutoff24h];

        if (!empty($filters['buscar'])) {
            $sql .= " AND (t.titulo LIKE :buscar OR t.codigo LIKE :buscar)";
            $params[':buscar'] = '%' . trim((string)$filters['buscar']) . '%';
        }

        if (!empty($filters['responsable_id'])) {
            $sql .= " AND t.responsable_id = :responsable_id";
            $params[':responsable_id'] = (int)$filters['responsable_id'];
        }

        if (!empty($filters['prioridad'])) {
            $sql .= " AND t.prioridad = :prioridad";
            $params[':prioridad'] = strtoupper(trim((string)$filters['prioridad']));
        }

        $sql .= " ORDER BY t.fecha_finalizada DESC, t.id DESC";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        return array_map(function ($row) {
            $formatted = self::format($row);
            $formatted['total_subtareas'] = (int)($row['total_subtareas'] ?? 0);
            $formatted['subtareas_completadas'] = (int)($row['subtareas_completadas'] ?? 0);
            $formatted['total_comentarios'] = (int)($row['total_comentarios'] ?? 0);
            $formatted['horas_desde_finalizada'] = !empty($row['fecha_finalizada'])
                ? round((time() - strtotime($row['fecha_finalizada'])) / 3600, 1)
                : null;
            return $formatted;
        }, $rows);
    }

    /**
     * Valida si un usuario tiene autorización para ver una tarea (RF-07, RN-01, RN-02, RN-05).
     */
    public static function canView(array $currentUser, array $task): bool
    {
        $isJefe = in_array($currentUser['rol'] ?? '', [User::ROL_JEFE_PERSONAL, User::ROL_JEFE_PLANEAMIENTO], true);
        $userId = (int)$currentUser['id'];
        $respId = (int)$task['responsable_id'];
        $creadorId = (int)$task['creador_id'];

        // Si es el responsable o creador, siempre puede verla
        if ($userId === $respId || $userId === $creadorId) {
            return true;
        }

        // Si es jefe y la tarea es del área (AREA), puede supervisarla
        if ($isJefe && $task['tipo_tarea'] === self::TIPO_AREA) {
            return true;
        }

        // En cualquier otro caso (asistente intentando ver tarea privada ajena), denegar
        return false;
    }

    /**
     * Valida si un usuario tiene autorización para editar una tarea.
     */
    public static function canEdit(array $currentUser, array $task): bool
    {
        $isJefe = in_array($currentUser['rol'] ?? '', [User::ROL_JEFE_PERSONAL, User::ROL_JEFE_PLANEAMIENTO], true);
        $userId = (int)$currentUser['id'];
        $respId = (int)$task['responsable_id'];
        $creadorId = (int)$task['creador_id'];

        if ($userId === $respId || $userId === $creadorId || ($isJefe && $task['tipo_tarea'] === self::TIPO_AREA)) {
            return true;
        }

        return false;
    }

    /**
     * Valida si un usuario tiene autorización para eliminar una tarea.
     */
    public static function canDelete(array $currentUser, array $task): bool
    {
        $isJefe = in_array($currentUser['rol'] ?? '', [User::ROL_JEFE_PERSONAL, User::ROL_JEFE_PLANEAMIENTO], true);
        $userId = (int)$currentUser['id'];
        $creadorId = (int)$task['creador_id'];

        return $userId === $creadorId || $isJefe;
    }

    /**
     * Crea una nueva tarea respetando asignaciones y roles (RF-03, RF-04, RF-05, RF-06, RN-03, RN-04).
     */
    public static function create(array $data, array $currentUser): array
    {
        $db = Database::getConnection();
        $isJefe = in_array($currentUser['rol'] ?? '', [User::ROL_JEFE_PERSONAL, User::ROL_JEFE_PLANEAMIENTO], true);
        $userId = (int)$currentUser['id'];

        $tipoTarea = !empty($data['tipo_tarea']) ? strtoupper(trim((string)$data['tipo_tarea'])) : self::TIPO_AREA;
        if (!in_array($tipoTarea, [self::TIPO_AREA, self::TIPO_PERSONAL], true)) {
            $tipoTarea = self::TIPO_AREA;
        }

        // Regla RF-05, RN-03: Asistente solo puede autoasignarse.
        // Tareas personales siempre se autoasignan al creador propio.
        if (!$isJefe || $tipoTarea === self::TIPO_PERSONAL) {
            $responsableId = $userId;
        } else {
            $responsableId = !empty($data['responsable_id']) ? (int)$data['responsable_id'] : $userId;
        }

        // Tareas personales son de apoyo interno; no requieren circuito formal de aprobación
        $requiereAprobacion = ($tipoTarea === self::TIPO_PERSONAL) ? 0 : (!empty($data['requiere_aprobacion']) ? 1 : 0);

        $codigo = self::generateCodigo();
        $now = gmdate('Y-m-d H:i:s');

        $sql = "
            INSERT INTO tareas (
                codigo, titulo, descripcion, tipo_tarea, columna_id, prioridad,
                creador_id, responsable_id, requiere_aprobacion, revisor_id,
                fecha_limite, created_at, updated_at
            ) VALUES (
                :codigo, :titulo, :descripcion, :tipo_tarea, :columna_id, :prioridad,
                :creador_id, :responsable_id, :requiere_aprobacion, :revisor_id,
                :fecha_limite, :created_at, :updated_at
            )
        ";

        $stmt = $db->prepare($sql);
        $stmt->execute([
            ':codigo'              => $codigo,
            ':titulo'              => trim((string)$data['titulo']),
            ':descripcion'         => !empty($data['descripcion']) ? trim((string)$data['descripcion']) : null,
            ':tipo_tarea'          => $tipoTarea,
            ':columna_id'          => !empty($data['columna_id']) ? (int)$data['columna_id'] : 1, // 1 = Pendiente
            ':prioridad'           => !empty($data['prioridad']) ? strtoupper(trim((string)$data['prioridad'])) : self::PRIORIDAD_MEDIA,
            ':creador_id'          => $userId,
            ':responsable_id'      => $responsableId,
            ':requiere_aprobacion' => $requiereAprobacion,
            ':revisor_id'          => !empty($data['revisor_id']) ? (int)$data['revisor_id'] : null,
            ':fecha_limite'        => !empty($data['fecha_limite']) ? trim((string)$data['fecha_limite']) : null,
            ':created_at'          => $now,
            ':updated_at'          => $now
        ]);

        $newId = (int)$db->lastInsertId();

        // Registrar en auditoría de eventos (RN-13)
        Audit::log(
            $userId,
            'TAREA',
            $newId,
            'CREAR',
            null,
            'PENDIENTE',
            [
                'codigo'         => $codigo,
                'titulo'         => $data['titulo'],
                'tipo_tarea'     => $data['tipo_tarea'] ?? self::TIPO_AREA,
                'responsable_id' => $responsableId
            ]
        );

        $createdTask = self::findById($newId);

        // Automatización: Notificar asignación (MVP-08)
        try {
            if (class_exists(\App\Services\AutomationEngine::class)) {
                \App\Services\AutomationEngine::notifyTaskAssigned($createdTask, $currentUser);
            }
        } catch (\Throwable $e) {}

        return $createdTask;
    }

    /**
     * Actualiza una tarea existente respetando reglas de reasignación.
     */
    public static function update(int $id, array $data, array $currentUser): array
    {
        $db = Database::getConnection();
        $isJefe = in_array($currentUser['rol'] ?? '', [User::ROL_JEFE_PERSONAL, User::ROL_JEFE_PLANEAMIENTO], true);
        $userId = (int)$currentUser['id'];

        $currentTask = self::findById($id);
        if (!$currentTask) {
            throw new \RuntimeException("Tarea no encontrada.");
        }

        $fields = [];
        $params = [':id' => $id, ':updated_at' => gmdate('Y-m-d H:i:s')];

        if (isset($data['titulo'])) {
            $fields[] = "titulo = :titulo";
            $params[':titulo'] = trim((string)$data['titulo']);
        }

        if (array_key_exists('descripcion', $data)) {
            $fields[] = "descripcion = :descripcion";
            $params[':descripcion'] = !empty($data['descripcion']) ? trim((string)$data['descripcion']) : null;
        }

        if (isset($data['prioridad'])) {
            $fields[] = "prioridad = :prioridad";
            $params[':prioridad'] = strtoupper(trim((string)$data['prioridad']));
        }

        if (array_key_exists('fecha_limite', $data)) {
            $fields[] = "fecha_limite = :fecha_limite";
            $params[':fecha_limite'] = !empty($data['fecha_limite']) ? trim((string)$data['fecha_limite']) : null;
        }

        if (isset($data['requiere_aprobacion']) && $isJefe) {
            $fields[] = "requiere_aprobacion = :requiere_aprobacion";
            $params[':requiere_aprobacion'] = !empty($data['requiere_aprobacion']) ? 1 : 0;
        }

        if (array_key_exists('revisor_id', $data) && $isJefe) {
            $fields[] = "revisor_id = :revisor_id";
            $params[':revisor_id'] = !empty($data['revisor_id']) ? (int)$data['revisor_id'] : null;
        }

        // Reasignación de responsable (Solo permitida para jefes según RN-03, RN-04)
        if (isset($data['responsable_id']) && $isJefe) {
            $fields[] = "responsable_id = :responsable_id";
            $params[':responsable_id'] = (int)$data['responsable_id'];
        }

        if (empty($fields)) {
            return $currentTask;
        }

        $fields[] = "updated_at = :updated_at";
        $sql = "UPDATE tareas SET " . implode(', ', $fields) . " WHERE id = :id";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        $esReasignacion = isset($data['responsable_id']) && (int)$data['responsable_id'] !== (int)$currentTask['responsable_id'];
        $accionAudit = $esReasignacion ? 'REASIGNAR' : 'ACTUALIZAR';
        $detallesAudit = ['cambios' => array_keys($data)];
        if ($esReasignacion) {
            $detallesAudit['responsable_anterior_id'] = (int)$currentTask['responsable_id'];
            $detallesAudit['responsable_nuevo_id'] = (int)$data['responsable_id'];
        }

        // Registrar en auditoría
        Audit::log(
            $userId,
            'TAREA',
            $id,
            $accionAudit,
            $currentTask['columna_tipo_estado'] ?? null,
            $currentTask['columna_tipo_estado'] ?? null,
            $detallesAudit
        );

        $updatedTask = self::findById($id);

        // Automatización: Notificar reasignación si el responsable cambió (MVP-08)
        if (isset($data['responsable_id']) && (int)$data['responsable_id'] !== (int)$currentTask['responsable_id']) {
            try {
                if (class_exists(\App\Services\AutomationEngine::class)) {
                    \App\Services\AutomationEngine::notifyTaskAssigned($updatedTask, $currentUser);
                }
            } catch (\Throwable $e) {}
        }

        return $updatedTask;
    }

    /**
     * Elimina una tarea y sus registros dependientes.
     */
    public static function delete(int $id, array $currentUser): bool
    {
        $db = Database::getConnection();
        $task = self::findById($id);
        if (!$task) {
            return false;
        }

        $userId = (int)$currentUser['id'];

        $stmt = $db->prepare("DELETE FROM tareas WHERE id = :id");
        $deleted = $stmt->execute([':id' => $id]);

        if ($deleted) {
            Audit::log(
                $userId,
                'TAREA',
                $id,
                'ELIMINAR',
                $task['columna_tipo_estado'] ?? null,
                'ELIMINADA',
                ['codigo' => $task['codigo'], 'titulo' => $task['titulo']]
            );
        }

        return $deleted;
    }

    /**
     * Ejecuta la transición de una tarea a otra columna del tablero Kanban (RF-11, RF-12, RN-06, RN-10, RN-11, RN-12, RN-13).
     * Aplica validaciones atómicas para confirmación de cierre y requerimiento de aprobación.
     *
     * @param int $id ID de la tarea
     * @param int $nuevaColumnaId ID de la columna destino
     * @param array $options ['confirmacion_cierre' => bool, 'motivo' => string]
     * @param array $currentUser Usuario autenticado en sesión
     * @return array
     */
    public static function transition(int $id, int $nuevaColumnaId, array $options, array $currentUser): array
    {
        $task = self::findById($id);
        if (!$task) {
            return [
                'success' => false,
                'status'  => 404,
                'code'    => 'ERR_TASK_NOT_FOUND',
                'message' => 'Tarea no encontrada.'
            ];
        }

        // Validar permisos de edición/movimiento sobre la tarea
        if (!self::canEdit($currentUser, $task)) {
            return [
                'success' => false,
                'status'  => 403,
                'code'    => 'ERR_FORBIDDEN_ACTION',
                'message' => 'No posee permisos para modificar el estado de esta tarea.'
            ];
        }

        // Obtener la columna de destino
        $nuevaColumna = BoardColumn::findById($nuevaColumnaId);
        if (!$nuevaColumna || !$nuevaColumna['activo']) {
            return [
                'success' => false,
                'status'  => 404,
                'code'    => 'ERR_COLUMN_NOT_FOUND',
                'message' => 'La columna de destino no existe o se encuentra desactivada.'
            ];
        }

        // Si ya está en esa columna, no hay cambio necesario
        if ((int)$task['columna_id'] === $nuevaColumnaId) {
            return [
                'success' => true,
                'tarea'   => $task,
                'mensaje' => 'La tarea ya se encuentra en la columna indicada.'
            ];
        }

        $estadoDestino = $nuevaColumna['tipo_estado'];
        $fechaFinalizada = $task['fecha_finalizada'];
        $isJefe = in_array($currentUser['rol'] ?? '', [User::ROL_JEFE_PERSONAL, User::ROL_JEFE_PLANEAMIENTO], true);

        // Regla RN-08: Si la tarea posee dependencias predecesoras pendientes, no puede avanzarse
        if (in_array($estadoDestino, [BoardColumn::ESTADO_EN_PROCESO, BoardColumn::ESTADO_FINALIZADA], true)) {
            if (class_exists(TaskDependency::class) && TaskDependency::hasPendingPredecessors($id)) {
                return [
                    'success' => false,
                    'status'  => 409,
                    'code'    => 'ERR_TASK_BLOCKED_BY_DEPENDENCIES',
                    'message' => 'No se puede mover la tarea porque posee dependencias predecesoras pendientes que no han finalizado (Regla RN-08).'
                ];
            }
        }

        // Si se mueve hacia Finalizada, validar reglas y registrar timestamp automático
        if ($estadoDestino === BoardColumn::ESTADO_FINALIZADA) {
            // Caso 1: La tarea requiere aprobación previa por jefatura
            if ($task['requiere_aprobacion'] && !$isJefe && empty($options['aprobado'])) {
                return [
                    'success' => false,
                    'status'  => 409,
                    'code'    => 'ERR_APPROVAL_REQUIRED',
                    'message' => 'Esta tarea exige aprobación previa por parte de jefatura. Debe solicitar revisión.'
                ];
            }

            // Caso 2: Cierre explícitamente cancelado o no confirmado
            if (array_key_exists('confirmacion_cierre', $options) && $options['confirmacion_cierre'] === false) {
                return [
                    'success' => false,
                    'status'  => 422,
                    'code'    => 'ERR_CONFIRMATION_REQUIRED',
                    'message' => 'Se requiere confirmación explícita para finalizar tareas sin aprobación (RN-11).'
                ];
            }

            $fechaFinalizada = gmdate('Y-m-d H:i:s');
        } else {
            // Si sale de finalizada a otro estado (reapertura), resetear fecha_finalizada
            if ($task['columna_tipo_estado'] === BoardColumn::ESTADO_FINALIZADA) {
                $fechaFinalizada = null;
            }
        }

        $db = Database::getConnection();
        $now = gmdate('Y-m-d H:i:s');

        $stmt = $db->prepare("
            UPDATE tareas
            SET columna_id = :columna_id,
                fecha_finalizada = :fecha_finalizada,
                updated_at = :updated_at
            WHERE id = :id
        ");

        $stmt->execute([
            ':columna_id'       => $nuevaColumnaId,
            ':fecha_finalizada' => $fechaFinalizada,
            ':updated_at'       => $now,
            ':id'               => $id
        ]);

        // Disparadores de dependencias (RN-08, RN-14, RF-16)
        if ($estadoDestino === BoardColumn::ESTADO_FINALIZADA) {
            try {
                if (class_exists(TaskDependency::class)) {
                    TaskDependency::onTaskCompleted($id, $currentUser);
                }
            } catch (\Throwable $e) {}
        } elseif ($task['columna_tipo_estado'] === BoardColumn::ESTADO_FINALIZADA) {
            try {
                if (class_exists(TaskDependency::class)) {
                    TaskDependency::onTaskReopened($id, $currentUser);
                }
            } catch (\Throwable $e) {}
        } elseif ($estadoDestino === BoardColumn::ESTADO_EN_REVISION) {
            try {
                if (class_exists(Approval::class)) {
                    $stmtCheck = $db->prepare("SELECT id FROM tarea_aprobaciones WHERE tarea_id = :id AND estado = 'PENDIENTE'");
                    $stmtCheck->execute([':id' => $id]);
                    if (!$stmtCheck->fetch()) {
                        Approval::solicitar($id, (int)$currentUser['id'], 'Movida a columna En Revisión desde el tablero');
                    }
                }
            } catch (\Throwable $e) {}
        }

        // Registrar en auditoría de eventos inmutable (RN-13)
        $userId = (int)$currentUser['id'];
        Audit::log(
            $userId,
            'TAREA',
            $id,
            'TRANSICION',
            $task['columna_tipo_estado'],
            $estadoDestino,
            [
                'columna_anterior_id'     => $task['columna_id'],
                'columna_anterior_nombre' => $task['columna_nombre'],
                'columna_destino_id'      => $nuevaColumnaId,
                'columna_destino_nombre'  => $nuevaColumna['nombre'],
                'motivo'                  => $options['motivo'] ?? null
            ]
        );

        $tareaActualizada = self::findById($id);

        return [
            'success' => true,
            'tarea'   => $tareaActualizada,
            'mensaje' => "Tarea transicionada a '{$nuevaColumna['nombre']}' exitosamente."
        ];
    }

    /**
     * Formatea un registro de tarea para salida JSON estandarizada.
     */
    public static function format(array $row): array
    {
        return [
            'id'                  => (int)$row['id'],
            'codigo'              => $row['codigo'],
            'titulo'              => $row['titulo'],
            'descripcion'         => $row['descripcion'] ?? null,
            'tipo_tarea'          => $row['tipo_tarea'],
            'columna_id'          => (int)$row['columna_id'],
            'columna_nombre'      => $row['columna_nombre'] ?? null,
            'columna_tipo_estado' => $row['columna_tipo_estado'] ?? null,
            'columna_color_hex'   => $row['columna_color_hex'] ?? null,
            'prioridad'           => $row['prioridad'],
            'creador_id'          => (int)$row['creador_id'],
            'creador_nombre'      => $row['creador_nombre'] ?? null,
            'creador_email'       => $row['creador_email'] ?? null,
            'responsable_id'      => (int)$row['responsable_id'],
            'responsable_nombre'  => $row['responsable_nombre'] ?? null,
            'responsable_email'   => $row['responsable_email'] ?? null,
            'requiere_aprobacion' => (bool)$row['requiere_aprobacion'],
            'revisor_id'          => !empty($row['revisor_id']) ? (int)$row['revisor_id'] : null,
            'revisor_nombre'      => $row['revisor_nombre'] ?? null,
            'fecha_limite'        => $row['fecha_limite'] ?? null,
            'fecha_finalizada'    => $row['fecha_finalizada'] ?? null,
            'created_at'          => $row['created_at'],
            'updated_at'          => $row['updated_at']
        ];
    }
}
