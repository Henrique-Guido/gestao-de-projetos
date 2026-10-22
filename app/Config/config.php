<?php

use App\Core\Env;

Env::load(dirname(__DIR__, 2) . '/.env');

return [
    'app' => [
        'name' => Env::get('APP_NAME', 'Publinet - Gestao de Clientes'),
        'url' => rtrim(Env::get('APP_URL', 'http://localhost'), '/'),
        'env' => Env::get('APP_ENV', 'production'),
        'debug' => filter_var(Env::get('APP_DEBUG', false), FILTER_VALIDATE_BOOLEAN),
        'key' => Env::get('APP_KEY', ''),
        'timezone' => Env::get('APP_TIMEZONE', 'America/Sao_Paulo'),
    ],
    'db' => [
        'host' => Env::get('DB_HOST', '127.0.0.1'),
        'port' => Env::get('DB_PORT', '3306'),
        'database' => Env::get('DB_DATABASE', 'publinet_gestao'),
        'username' => Env::get('DB_USERNAME', 'root'),
        'password' => Env::get('DB_PASSWORD', ''),
    ],
];
