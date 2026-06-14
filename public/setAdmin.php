<?php
require __DIR__ . '../../config/config.php';
require BASE_DIR.'/vendor/autoload.php';

// Проверка безопасности (разрешаем только локальный доступ)
if ($_SERVER['REMOTE_ADDR'] !== '127.0.0.1' && $_SERVER['REMOTE_ADDR'] !== '::1') {
    die('Access denied! This script can only be run locally.');
}

// Подключение к базе
$db = new App\Classes\Database();
$connection = $db->getConnection();

// Данные администратора
$adminData = [
    'username' => 'e.bernatsky',
    'password' => 'Prezzone1171083', // Замените на реальный пароль
    'email'    => 'evberkutone@gmail.com',
    'role'     => '1'
];

try {
    // Хеширование пароля
    $hashedPassword = password_hash($adminData['password'], PASSWORD_BCRYPT);
    
    // Подготовленный запрос
    $stmt = $connection->prepare(
        "INSERT INTO users (username, password, email, role, created_at, is_active) 
         VALUES (?, ?, ?, ?, NOW(), 1)"
    );
    
    $stmt->bind_param(
        "ssss",
        $adminData['username'],
        $hashedPassword,
        $adminData['email'],
        $adminData['role']
    );
    
    // Выполнение запроса
    if ($stmt->execute()) {
        echo "Администратор успешно создан!<br>";
        echo "Логин: " . htmlspecialchars($adminData['username']) . "<br>";
        echo "Пароль: " . htmlspecialchars($adminData['password']);
    } else {
        throw new Exception("Ошибка при создании администратора: " . $stmt->error);
    }
    
} catch (Exception $e) {
    die("Ошибка: " . $e->getMessage());
}

// Закрытие соединения
$connection->close();