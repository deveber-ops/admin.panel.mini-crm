<?php
require __DIR__ . '../../config/config.php';
require BASE_DIR.'/vendor/autoload.php';

session_start();

$db = new App\Classes\Database();
$auth = new App\Classes\Auth($db);

// Тест 1: Генерация токена для неавторизованного пользователя
$token1 = $auth->generateCsrfToken();
echo "Token for guest: $token1<br>";
echo "Session token: {$_SESSION['csrf_token']}<br>";
echo "DB records: " . $db->getConnection()->query("SELECT COUNT(*) FROM csrf_tokens")->fetch_row()[0] . "<hr>";

// Тест 2: Имитация авторизации
$_SESSION['user_id'] = 1; // Тестовый ID пользователя
$token2 = $auth->generateCsrfToken();
echo "Token for auth user: $token2<br>";
echo "DB records: " . $db->getConnection()->query("SELECT COUNT(*) FROM csrf_tokens")->fetch_row()[0] . "<br>";
echo "Token in DB: " . $db->getConnection()->query("SELECT token FROM csrf_tokens WHERE user_id = 1")->fetch_row()[0];