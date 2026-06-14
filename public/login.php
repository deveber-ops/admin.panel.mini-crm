<?php
require_once __DIR__ . '/index.php';

// Если пользователь уже авторизован, перенаправляем на панель
if ($auth->isLoggedIn()) {
    header('Location: panel');
    exit;
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if ($auth->login($username, $password)) {
        header('Location: panel');
        exit;
    } else {
        $error = 'Invalid username or password';
    }
}

echo $twig->render('login.twig', [
    'error' => $error,
    'auth' => $auth,
    'PAGE' => 'login'
]);