<?php

namespace App\Core;

abstract class Controller
{
    protected function view(string $view, array $data = [], ?string $layout = 'app'): void
    {
        $config = require dirname(__DIR__) . '/Config/config.php';
        extract($data);
        $appName = $config['app']['name'];
        $appUrl = $config['app']['url'];
        $viewFile = dirname(__DIR__) . "/Views/{$view}.php";

        if (!is_file($viewFile)) {
            throw new \RuntimeException("View não encontrada: {$view}");
        }

        if ($layout === null) {
            require $viewFile;
            return;
        }

        $content = function () use ($viewFile, $data, $appName, $appUrl) {
            extract($data);
            require $viewFile;
        };

        require dirname(__DIR__) . "/Views/layouts/{$layout}.php";
    }

    protected function redirect(string $path): void
    {
        $config = require dirname(__DIR__) . '/Config/config.php';
        header('Location: ' . $config['app']['url'] . $path);
        exit;
    }

    protected function json(array $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }

    protected function input(string $key, mixed $default = null): mixed
    {
        $value = $_POST[$key] ?? $default;

        return is_string($value) ? trim($value) : $value;
    }
}
