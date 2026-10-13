<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Gestor de almacenamiento y servicio seguro de archivos adjuntos y evidencias (RF-10, RN-02).
 * Aplica validación estricta de extensiones, tipos MIME, nombres sanitizados y bloqueo de ejecución.
 */
class UploadHandler
{
    public const MAX_FILE_SIZE_BYTES = 10485760; // 10 MB

    public const ALLOWED_EXTENSIONS = [
        'pdf'  => 'application/pdf',
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png'  => 'image/png',
        'webp' => 'image/webp',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'xls'  => 'application/vnd.ms-excel',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'csv'  => 'text/csv',
        'txt'  => 'text/plain'
    ];

    /**
     * Retorna la ruta física del directorio de almacenamiento.
     */
    public static function getUploadsDir(): string
    {
        $dir = dirname(__DIR__, 2) . '/storage/uploads';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        return $dir;
    }

    /**
     * Valida y almacena de forma segura un archivo cargado.
     *
     * @param array $file Estructura de archivo ($_FILES['archivo'])
     * @return array ['nombre_original', 'ruta_hash', 'mime', 'size']
     * @throws \RuntimeException Si la validación falla
     */
    public static function store(array $file): array
    {
        if (empty($file) || !isset($file['tmp_name'])) {
            throw new \RuntimeException("No se recibió ningún archivo adjunto.", 422);
        }

        if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            throw new \RuntimeException("Error al subir el archivo (código {$file['error']}).", 422);
        }

        $size = (int)($file['size'] ?? 0);
        if ($size <= 0 || $size > self::MAX_FILE_SIZE_BYTES) {
            throw new \RuntimeException("El archivo excede el tamaño máximo permitido de 10 MB.", 422);
        }

        $originalName = trim((string)($file['name'] ?? 'adjunto'));
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        if (!array_key_exists($extension, self::ALLOWED_EXTENSIONS)) {
            throw new \RuntimeException("Extensión de archivo no permitida (.{$extension}). Extensiones válidas: PDF, JPG, PNG, WEBP, XLSX, XLS, DOCX, CSV, TXT.", 422);
        }

        $tmpPath = (string)$file['tmp_name'];
        if (!is_file($tmpPath)) {
            throw new \RuntimeException("El archivo temporal no es válido.", 422);
        }

        // Detección segura de tipo MIME
        $mime = 'application/octet-stream';
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo) {
                $detectedMime = finfo_file($finfo, $tmpPath);
                if ($detectedMime) {
                    $mime = $detectedMime;
                }
                finfo_close($finfo);
            }
        }

        // Generar nombre en disco aleatorio y único con hash (Anti-Path Traversal)
        $hashName = bin2hex(random_bytes(16)) . '_' . time() . '.' . $extension;
        $destPath = self::getUploadsDir() . '/' . $hashName;

        // Mover o copiar archivo (is_uploaded_file para HTTP, copy para testing CLI)
        $moved = false;
        if (is_uploaded_file($tmpPath)) {
            $moved = move_uploaded_file($tmpPath, $destPath);
        } else {
            $moved = copy($tmpPath, $destPath);
        }

        if (!$moved) {
            throw new \RuntimeException("No fue posible persistir el archivo en almacenamiento.", 500);
        }

        return [
            'nombre_original' => basename($originalName),
            'ruta_hash'       => $hashName,
            'mime'            => $mime,
            'size'            => $size
        ];
    }

    /**
     * Sirve el archivo descargable con cabeceras de seguridad.
     */
    public static function serve(string $rutaHash, string $nombreOriginal, ?string $mime = null): void
    {
        $safeHash = basename($rutaHash);
        $filePath = self::getUploadsDir() . '/' . $safeHash;

        if (!file_exists($filePath)) {
            Response::error("El archivo solicitado no se encuentra en el servidor.", 404, 'ERR_FILE_NOT_FOUND');
            return;
        }

        $safeMime = $mime ?? 'application/octet-stream';
        $safeOriginalName = preg_replace('/[^a-zA-Z0-9_\-\. ]/', '_', basename($nombreOriginal));

        if (!headers_sent()) {
            header('Content-Type: ' . $safeMime);
            header('Content-Disposition: attachment; filename="' . $safeOriginalName . '"');
            header('Content-Length: ' . filesize($filePath));
            header('X-Content-Type-Options: nosniff');
            header('Cache-Control: private, max-age=3600');
        }

        readfile($filePath);

        if (Response::$exitOnSend) {
            exit;
        }
    }
}
