<?php

declare(strict_types=1);

namespace Dasintranet\Framework;

use JsonException;
use RuntimeException;

class App
{
    /** @var array<string, array<string, callable|array>> */
    private static array $routes = [];

    private static ?string $views = null;

    public static function getViews(): string
    {
        return self::$views ?? dirname(__DIR__) . '/app/views';
    }

    public static function setViews(string $path): void
    {
        self::$views = rtrim($path, '/\\');
    }

    public static function get(string $uri, $callback): void
    {
        self::addRoute('GET', $uri, $callback);
    }

    public static function post(string $uri, $callback): void
    {
        self::addRoute('POST', $uri, $callback);
    }

    public static function delete(string $uri, $callback): void
    {
        self::addRoute('DELETE', $uri, $callback);
    }

    public static function put(string $uri, $callback): void
    {
        self::addRoute('PUT', $uri, $callback);
    }

    public static function patch(string $uri, $callback): void
    {
        self::addRoute('PATCH', $uri, $callback);
    }

    private static function addRoute(string $method, string $uri, $callback): void
    {
        if (!is_callable($callback) && !(is_array($callback) && count($callback) === 2)) {
            throw new RuntimeException('Invalid route callback.');
        }

        self::$routes[$method][trim($uri, '/')] = $callback;
    }

    /** Turn a route template into a safe regular expression. */
    private static function routePattern(string $route): string
    {
        $parts = preg_split('/(\{[a-zA-Z_][a-zA-Z_0-9]*\})/', $route, -1, PREG_SPLIT_DELIM_CAPTURE);
        if ($parts === false) {
            throw new RuntimeException('Invalid route template.');
        }

        $pattern = '';
        foreach ($parts as $part) {
            $pattern .= preg_match('/^\{[a-zA-Z_][a-zA-Z_0-9]*\}$/D', $part)
                ? '([^/]+)'
                : preg_quote($part, '~');
        }

        return '~^' . $pattern . '$~D';
    }

    public static function run(): void
    {
        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        $requestUri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
        $uri = trim((string) (parse_url($requestUri, PHP_URL_PATH) ?: '/'), '/');
        $routes = self::$routes[$method === 'HEAD' ? 'GET' : $method] ?? [];

        foreach ($routes as $route => $callback) {
            if (!preg_match(self::routePattern($route), $uri, $matches)) {
                continue;
            }

            $params = array_map('rawurldecode', array_slice($matches, 1));
            if (is_array($callback) && is_string($callback[0]) && is_string($callback[1])) {
                $controller = new $callback[0]();
                $response = $controller->{$callback[1]}(...$params);
            } else {
                $response = $callback(...$params);
            }

            if (is_array($response) || is_object($response)) {
                header('Content-Type: application/json; charset=utf-8');
                $output = json_encode($response, JSON_THROW_ON_ERROR);
            } else {
                $output = (string) ($response ?? '');
            }

            if ($method !== 'HEAD') {
                echo $output;
            }

            return;
        }

        http_response_code(404);
        $errorPage = self::getViews() . '/error404.php';
        if (is_file($errorPage)) {
            if ($method !== 'HEAD') {
                require $errorPage;
            }
        } elseif ($method !== 'HEAD') {
            header('Content-Type: text/plain; charset=utf-8');
            echo '404 Not Found';
        }
    }
}
