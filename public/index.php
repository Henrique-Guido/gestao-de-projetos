<?php

require dirname(__DIR__) . '/vendor/autoload.php';

$config = require dirname(__DIR__) . '/app/Config/config.php';

if ($config['app']['debug']) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
}

session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'httponly' => true,
    'samesite' => 'Lax',
    'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
]);
session_start();

use App\Controllers\AuthController;
use App\Controllers\BlogController;
use App\Controllers\ClienteController;
use App\Controllers\FtpAccountController;
use App\Controllers\UserController;
use App\Controllers\WordpressController;
use App\Core\Router;

$router = new Router();

$router->get('/', [AuthController::class, 'showLogin']);
$router->get('/login', [AuthController::class, 'showLogin']);
$router->post('/login', [AuthController::class, 'login']);
$router->post('/logout', [AuthController::class, 'logout']);

$router->get('/ftp', [FtpAccountController::class, 'index']);
$router->get('/ftp/create', [FtpAccountController::class, 'create']);
$router->post('/ftp', [FtpAccountController::class, 'store']);
$router->get('/ftp/{id}/edit', [FtpAccountController::class, 'edit']);
$router->post('/ftp/{id}', [FtpAccountController::class, 'update']);
$router->post('/ftp/{id}/delete', [FtpAccountController::class, 'destroy']);
$router->post('/ftp/{id}/reveal', [FtpAccountController::class, 'reveal']);
$router->post('/ftp/{id}/test', [FtpAccountController::class, 'test']);

$router->get('/blogs', [BlogController::class, 'index']);
$router->get('/blogs/create', [BlogController::class, 'create']);
$router->post('/blogs', [BlogController::class, 'store']);
$router->get('/blogs/{id}/edit', [BlogController::class, 'edit']);
$router->post('/blogs/{id}', [BlogController::class, 'update']);
$router->post('/blogs/{id}/delete', [BlogController::class, 'destroy']);
$router->post('/blogs/{id}/reveal', [BlogController::class, 'reveal']);

$router->get('/wordpress', [WordpressController::class, 'index']);
$router->get('/wordpress/create', [WordpressController::class, 'create']);
$router->post('/wordpress', [WordpressController::class, 'store']);
$router->get('/wordpress/{id}/edit', [WordpressController::class, 'edit']);
$router->post('/wordpress/{id}', [WordpressController::class, 'update']);
$router->post('/wordpress/{id}/delete', [WordpressController::class, 'destroy']);
$router->post('/wordpress/{id}/reveal', [WordpressController::class, 'reveal']);

$router->get('/clientes', [ClienteController::class, 'index']);
$router->get('/clientes/{id}/edit', [ClienteController::class, 'edit']);
$router->post('/clientes/{id}', [ClienteController::class, 'update']);

$router->get('/usuarios', [UserController::class, 'index']);
$router->get('/usuarios/create', [UserController::class, 'create']);
$router->post('/usuarios', [UserController::class, 'store']);
$router->get('/usuarios/{id}/edit', [UserController::class, 'edit']);
$router->post('/usuarios/{id}', [UserController::class, 'update']);
$router->post('/usuarios/{id}/delete', [UserController::class, 'destroy']);

$basePath = parse_url($config['app']['url'], PHP_URL_PATH) ?? '';

$router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI'], $basePath);
