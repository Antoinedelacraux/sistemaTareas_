<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Response;
use App\Core\UploadHandler;
use App\Models\Comment;
use App\Models\Task;

/**
 * Controlador de Comentarios, Evidencias y Archivos Adjuntos (RF-10, RN-02).
 */
class CommentController
{
    /**
     * Lista los comentarios y evidencias de una tarea.
     * GET /api/tareas/{id}/comentarios
     */
    public function index(array $request): void
    {
        $currentUser = $request['user'] ?? null;
        if (!$currentUser) {
            Response::error("Autenticación requerida.", 401, 'ERR_UNAUTHORIZED');
            return;
        }

        $taskId = (int)($request['params']['id'] ?? 0);
        if ($taskId <= 0) {
            Response::error("Identificador de tarea inválido.", 400, 'ERR_INVALID_ID');
            return;
        }

        $task = Task::findById($taskId);
        if (!$task) {
            Response::error("Tarea no encontrada.", 404, 'ERR_TASK_NOT_FOUND');
            return;
        }

        if (!Task::canView($currentUser, $task)) {
            Response::error("No posee permisos para consultar comentarios de esta tarea.", 403, 'ERR_FORBIDDEN_TASK_ACCESS');
            return;
        }

        $comentarios = Comment::listByTaskId($taskId);

        Response::json([
            'comentarios' => $comentarios,
            'total'       => count($comentarios)
        ]);
    }

    /**
     * Registra un comentario o evidencia (con soporte de archivo adjunto seguro).
     * POST /api/tareas/{id}/comentarios
     */
    public function create(array $request): void
    {
        $currentUser = $request['user'] ?? null;
        if (!$currentUser) {
            Response::error("Autenticación requerida.", 401, 'ERR_UNAUTHORIZED');
            return;
        }

        $taskId = (int)($request['params']['id'] ?? 0);
        if ($taskId <= 0) {
            Response::error("Identificador de tarea inválido.", 400, 'ERR_INVALID_ID');
            return;
        }

        $task = Task::findById($taskId);
        if (!$task) {
            Response::error("Tarea no encontrada.", 404, 'ERR_TASK_NOT_FOUND');
            return;
        }

        if (!Task::canView($currentUser, $task)) {
            Response::error("No posee permisos para comentar en esta tarea.", 403, 'ERR_FORBIDDEN_TASK_ACCESS');
            return;
        }

        if (!Task::canInteract($currentUser, $task)) {
            Response::error("La tarea se encuentra finalizada y cerrada para modificaciones por parte de asistentes.", 403, 'ERR_TASK_CLOSED');
            return;
        }

        $body = $request['body'] ?? [];
        $comentario = trim((string)($body['comentario'] ?? ''));

        $archivoData = null;

        // Procesar archivo adjunto si fue enviado
        $uploadedFile = $request['files']['archivo'] ?? null;
        if (!empty($uploadedFile) && is_array($uploadedFile) && !empty($uploadedFile['name'])) {
            try {
                $archivoData = UploadHandler::store($uploadedFile);
            } catch (\RuntimeException $e) {
                Response::error($e->getMessage(), $e->getCode() ?: 422, 'ERR_UPLOAD_FAILED');
                return;
            }
        }

        if ($comentario === '' && $archivoData === null) {
            Response::error("Debe escribir un comentario o adjuntar un archivo.", 422, 'ERR_VALIDATION_FAILED');
            return;
        }

        $esEvidencia = !empty($body['es_evidencia']) || $archivoData !== null;

        $nuevo = Comment::create(
            $taskId,
            (int)$currentUser['id'],
            $comentario,
            $esEvidencia,
            $archivoData
        );

        Response::json([
            'comentario' => $nuevo,
            'mensaje'    => 'Comentario registrado exitosamente.'
        ], 201);
    }

    /**
     * Descarga de forma segura un archivo adjunto validando permisos sobre la tarea padre.
     * GET /api/comentarios/{id}/archivo
     */
    public function download(array $request): void
    {
        $currentUser = $request['user'] ?? null;
        if (!$currentUser) {
            Response::error("Autenticación requerida.", 401, 'ERR_UNAUTHORIZED');
            return;
        }

        $commentId = (int)($request['params']['id'] ?? 0);
        if ($commentId <= 0) {
            Response::error("Identificador de comentario inválido.", 400, 'ERR_INVALID_ID');
            return;
        }

        $comment = Comment::findById($commentId);
        if (!$comment) {
            Response::error("Comentario no encontrado.", 404, 'ERR_COMMENT_NOT_FOUND');
            return;
        }

        if (empty($comment['adjunto'])) {
            Response::error("Este comentario no posee ningún archivo adjunto.", 404, 'ERR_FILE_NOT_FOUND');
            return;
        }

        $task = Task::findById((int)$comment['tarea_id']);
        if (!$task || !Task::canView($currentUser, $task)) {
            Response::error("No posee permisos para descargar archivos de esta tarea.", 403, 'ERR_FORBIDDEN_FILE_ACCESS');
            return;
        }

        // Obtener ruta interna
        $db = \App\Config\Database::getConnection();
        $stmt = $db->prepare("SELECT archivo_ruta_hash FROM tarea_comentarios WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $commentId]);
        $rutaHash = (string)$stmt->fetchColumn();

        UploadHandler::serve(
            $rutaHash,
            $comment['adjunto']['nombre_original'],
            $comment['adjunto']['mime']
        );
    }
}
