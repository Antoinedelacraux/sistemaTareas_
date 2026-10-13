<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Gestor de respuestas HTTP estandarizadas en formato JSON Envelope.
 */
class Response
{
    /**
     * Permite desactivar el exit automático durante la ejecución de pruebas unitarias.
     */
    public static bool $exitOnSend = true;

    /**
     * Indica si ya se ha emitido una respuesta en la petición actual.
     */
    public static bool $hasSentResponse = false;

    /**
     * Guarda el último código de estado HTTP emitido.
     */
    public static int $lastStatusCode = 200;

    /**
     * Emite una respuesta de éxito con código HTTP.
     */
    public static function json(mixed $data = null, int $statusCode = 200, array $meta = []): void
    {
        self::$hasSentResponse = true;
        self::$lastStatusCode = $statusCode;
        if (!headers_sent()) {
            http_response_code($statusCode);
            header('Content-Type: application/json; charset=utf-8');
        }

        $response = [
            'success' => true,
            'data'    => $data,
            'error'   => null,
            'meta'    => array_merge([
                'timestamp' => gmdate('Y-m-d\TH:i:s\Z')
            ], $meta)
        ];

        echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if (self::$exitOnSend) {
            exit;
        }
    }

    /**
     * Emite una respuesta de error estandarizada.
     */
    public static function error(string $message, int $statusCode = 400, ?string $errorCode = null, mixed $details = null): void
    {
        self::$hasSentResponse = true;
        self::$lastStatusCode = $statusCode;
        if (!headers_sent()) {
            http_response_code($statusCode);
            header('Content-Type: application/json; charset=utf-8');
        }

        $errorPayload = [
            'code'    => $errorCode ?? 'ERR_HTTP_' . $statusCode,
            'message' => $message
        ];

        if ($details !== null) {
            $errorPayload['details'] = $details;
        }

        $response = [
            'success' => false,
            'data'    => null,
            'error'   => $errorPayload,
            'meta'    => [
                'timestamp' => gmdate('Y-m-d\TH:i:s\Z')
            ]
        ];

        echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if (self::$exitOnSend) {
            exit;
        }
    }
}
