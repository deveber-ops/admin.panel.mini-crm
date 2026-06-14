<?php
require_once __DIR__ . '/index.php';

// Если пользователь не авторизован, перенаправляем на страницу входа
if (!$auth->isLoggedIn()) {
    header('Location: login);
    exit;
}

echo $twig->render('panel.twig', [
    'auth' => $auth
]);