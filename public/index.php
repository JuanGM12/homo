<?php

declare(strict_types=1);

use App\Config\Config;
use App\Core\App;

require dirname(__DIR__) . '/vendor/autoload.php';

// Cargar variables de entorno
$dotenvPath = dirname(__DIR__);
if (file_exists($dotenvPath . '/.env')) {
    (Dotenv\Dotenv::createImmutable($dotenvPath))->safeLoad();
}

// Zona horaria
date_default_timezone_set(Config::timezone());

// Configuración básica de errores (según entorno)
if (Config::isDev()) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
}

// Configuración de sesión (secure = true solo con HTTPS para que la cookie se guarde en HTTP/incógnito)
$sessionSecure = Config::env('SESSION_SECURE');
if ($sessionSecure === null || $sessionSecure === '') {
    $sessionSecure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== '' && $_SERVER['HTTPS'] !== 'off';
} else {
    $sessionSecure = filter_var($sessionSecure, FILTER_VALIDATE_BOOLEAN);
}
session_name((string) Config::env('SESSION_NAME', 'accion_territorio'));
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => '',
    'secure' => $sessionSecure,
    'httponly' => filter_var(Config::env('SESSION_HTTP_ONLY', true), FILTER_VALIDATE_BOOLEAN),
    'samesite' => (string) Config::env('SESSION_SAME_SITE', 'Lax'),
]);
session_start();

$app = new App();
$app->run();
