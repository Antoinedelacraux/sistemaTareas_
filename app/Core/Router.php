<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Enrutador REST ligero y eficiente para PHP 8.2+.
 * Soporta parámetros en URL ({id}), métodos HTTP y middlewares.
 */
class Router
{
    private array $routes = [];

    /**
     * Registra una ruta para el método HTTP especificado.
     */
    public function add(string $method, string $path, callable|array $handler, array $middlewares = []): void
    {
        // Normalizar path
        $path = '/' . trim($path, '/');

        // Convertir ruta con parámetros {param} en expresión regular
        $regex = preg_replace('/\{([a-zA-Z0-9_]+)\}/', '(?P<$1>[^/]+)', $path);
        $regex = '#^' . $regex . '$#';

        $this->routes[] = [
            'method'      => strtoupper($method),
            'path'        => $path,
            'regex'       => $regex,
            'handler'     => $handler,
            'middlewares' => $middlewares
        ];
    }

    public function get(string $path, callable|array $handler, array $middlewares = []): void
    {
        $this->add('GET', $path, $handler, $middlewares);
    }

    public function post(string $path, callable|array $handler, array $middlewares = []): void
    {
        $this->add('POST', $path, $handler, $middlewares);
    }

    public function put(string $path, callable|array $handler, array $middlewares = []): void
    {
        $this->add('PUT', $path, $handler, $middlewares);
    }

    public function patch(string $path, callable|array $handler, array $middlewares = []): void
    {
        $this->add('PATCH', $path, $handler, $middlewares);
    }

    public function delete(string $path, callable|array $handler, array $middlewares = []): void
    {
        $this->add('DELETE', $path, $handler, $middlewares);
    }

    /**
     * Resuelve y despacha la petición entrante.
     */
    public function dispatch(string $method, string $uri): void
    {
        $method = strtoupper($method);
        // Extraer path sin query string
        $path = parse_url($uri, PHP_URL_PATH) ?? '/';
        $path = '/' . trim($path, '/');

        $methodMatches = false;

        foreach ($this->routes as $route) {
            if (preg_match($route['regex'], $path, $matches)) {
                if ($route['method'] !== $method) {
                    $methodMatches = true;
                    continue;
                }

                // Filtrar solo los parámetros nombrados
                $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);

                // Obtener payload JSON o POST estándar
                $body = [];
                $input = file_get_contents('php://input');
                if (!empty($input)) {
                    $decoded = json_decode($input, true);
                    if (is_array($decoded)) {
                        $body = $decoded;
                    }
                }

                // Soportar formularios multipart/form-data o x-www-form-urlencoded
                if (!empty($_POST)) {
                    $body = array_merge($body, $_POST);
                }

                $request = [
                    'method' => $method,
                    'path'   => $path,
                    'params' => $params,
                    'query'  => $_GET,
                    'body'   => $body,
                    'files'  => $_FILES ?? []
                ];

                // Resetear flag de respuesta antes de iniciar la cadena de ejecución
                Response::$hasSentResponse = false;

                // Ejecutar middlewares
                foreach ($route['middlewares'] as $middleware) {
                    if (Response::$hasSentResponse) {
                        return;
                    }

                    if (is_callable($middleware)) {
                        $middleware($request);
                    } elseif (is_string($middleware) && class_exists($middleware)) {
                        $instance = new $middleware();
                        if (method_exists($instance, 'handle')) {
                            $instance->handle($request);
                        }
                    }

                    if (Response::$hasSentResponse) {
                        return;
                    }
                }

                // Ejecutar handler
                $handler = $route['handler'];
                if (is_callable($handler)) {
                    $handler($request);
                    return;
                } elseif (is_array($handler) && count($handler) === 2) {
                    [$class, $action] = $handler;
                    if (class_exists($class)) {
                        $controller = new $class();
                        if (method_exists($controller, $action)) {
                            $controller->$action($request);
                            return;
                        }
                    }
                }

                Response::error("Handler de ruta no válido.", 500, 'ERR_INVALID_HANDLER');
                return;
            }
        }

        if ($methodMatches) {
            Response::error("Método HTTP no permitido para esta ruta.", 405, 'ERR_METHOD_NOT_ALLOWED');
        } else {
            Response::error("Recurso no encontrado: {$method} {$path}", 404, 'ERR_NOT_FOUND');
        }
    }
}
