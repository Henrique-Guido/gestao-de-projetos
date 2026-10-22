<?php

namespace App\Core;

class Router
{
    private array $routes = [
        'GET' => [],
        'POST' => [],
    ];

    public function get(string $path, array $handler): void
    {
        $this->routes['GET'][$path] = $handler;
    }

    public function post(string $path, array $handler): void
    {
        $this->routes['POST'][$path] = $handler;
    }

    public function dispatch(string $method, string $uri, string $basePath = ''): void
    {
        $uri = parse_url($uri, PHP_URL_PATH) ?? '/';
        $basePath = rtrim($basePath, '/');

        if ($basePath !== '' && str_starts_with($uri, $basePath)) {
            $uri = substr($uri, strlen($basePath));
        }

        $uri = '/' . trim($uri, '/');

        foreach ($this->routes[$method] ?? [] as $path => $handler) {
            $pattern = preg_replace('#\{[a-zA-Z_]+\}#', '([^/]+)', rtrim($path, '/') ?: '/');
            $pattern = '#^' . $pattern . '$#';

            if (preg_match($pattern, $uri, $matches)) {
                array_shift($matches);
                [$controllerClass, $action] = $handler;
                $controller = new $controllerClass();
                $controller->$action(...$matches);
                return;
            }
        }

        http_response_code(404);
        (new \App\Controllers\ErrorController())->notFound();
    }
}
