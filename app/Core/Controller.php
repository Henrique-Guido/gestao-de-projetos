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

        // Campos enviados como array (ex.: name[]=x) são tratados como ausentes.
        if (is_array($value)) {
            return $default;
        }

        return is_string($value) ? trim($value) : $value;
    }

    /**
     * Igual a input(), mas sem trim: usado em senhas, onde espaços nas bordas fazem parte do valor.
     */
    protected function inputRaw(string $key, ?string $default = null): ?string
    {
        $value = $_POST[$key] ?? $default;

        return is_string($value) ? $value : $default;
    }

    protected function query(string $key, string $default = ''): string
    {
        $value = $_GET[$key] ?? $default;

        return is_string($value) ? trim($value) : $default;
    }

    protected function isValidDate(string $value): bool
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $date !== false && $date->format('Y-m-d') === $value;
    }

    /**
     * URL do POST com "https://" quando digitada sem protocolo.
     * Garante que o href exibido nas listagens seja sempre http(s).
     */
    protected function inputUrl(string $key): string
    {
        $url = $this->input($key, '');

        if ($url !== '' && !preg_match('#^https?://#i', $url)) {
            $url = 'https://' . $url;
        }

        return $url;
    }

    protected function isValidUrl(string $url): bool
    {
        return mb_strlen($url) <= 255 && filter_var($url, FILTER_VALIDATE_URL) !== false;
    }
}
