<?php

$config = require __DIR__ . '/app_config.php';

$rootPath = isset($_SERVER['DOCUMENT_ROOT']) ? $_SERVER['DOCUMENT_ROOT'] : dirname(__DIR__);
define('BASE_DIR', $rootPath);
define('SRC_DIR', BASE_DIR . '/src');
define('TEMPLATES_DIR', SRC_DIR . '/Templates');
define('ASSETS_DIR', BASE_DIR . '/assets');
define('CACHE_DIR', BASE_DIR . '/cache');

define('DB_HOST', $config['database']['host']);
define('DB_USER', $config['database']['user']);
define('DB_PASS', $config['database']['pass']);
define('DB_NAME', $config['database']['name']);
define('DB_CHARSET', $config['database']['charset']);

define('SITE_NAME', $config['site']['name']);
define('BASE_URL', $config['site']['base_url']);

define('APP_NAME', $config['app']['name']);
define('DEBUG_MODE', $config['app']['debug_mode']);

define('TWIG_CACHE', $config['twig']['cache']);
define('TWIG_DEBUG', $config['twig']['debug']);

define('MAX_LOGIN_ATTEMPTS', $config['security']['max_login_attempts']);
define('LOGIN_LOCKOUT_TIME', $config['security']['login_lockout_time']);
define('CSRF_TOKEN_LIFETIME', $config['security']['csrf_token_lifetime']);

session_name($config['session']['name']);
ini_set('session.gc_maxlifetime', (string)$config['session']['lifetime']);
session_set_cookie_params([
    'lifetime' => $config['session']['lifetime'],
    'path' => $config['session']['path'],
    'domain' => $config['session']['domain'],
    'secure' => $config['session']['secure'],
    'httponly' => $config['session']['httponly'],
    'samesite' => $config['session']['samesite']
]);

session_start();

define('SMTP_HOST', $config['smtp']['host']);
define('SMTP_PORT', $config['smtp']['port']);
define('SMTP_ENCRYPTION', $config['smtp']['encryption']);
define('SMTP_USER', $config['smtp']['user']);
define('SMTP_PASS', $config['smtp']['pass']);
define('SMTP_FROM_EMAIL', $config['smtp']['from_email']);
define('SMTP_FROM_NAME', $config['smtp']['from_name']);

define('ROCKET_USER', $config['sms']['rocket_user']);
define('ROCKET_PASSWORD', $config['sms']['rocket_password']);

define('KASSA_PORT', $config['kassa']['port']);
