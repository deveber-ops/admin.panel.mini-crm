<?php
// URL для авторизации в Webkassa Open API
$auth_url = 'https://webkassa.by/api/auth/login';

// Данные для авторизации (замените на свои реальные данные)
$credentials = [
    'login' => 'USER14518',      // Ваш логин в Webkassa
    'password' => 'Nailine2024' // Ваш пароль в Webkassa
];

// Инициализация cURL
$ch = curl_init();

// Настройка параметров cURL
curl_setopt($ch, CURLOPT_URL, $auth_url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($credentials));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Accept: application/json'
]);

// Выполнение запроса
$response = curl_exec($ch);

// Проверка на ошибки
if (curl_errno($ch)) {
    echo 'Ошибка cURL: ' . curl_error($ch);
    exit;
}

// Закрытие соединения
curl_close($ch);

// Декодирование ответа
$data = json_decode($response, true);

// Проверка успешности авторизации и получение sessionid
if (isset($data['sessionid'])) {
    $sessionid = $data['sessionid'];
    echo "Авторизация успешна. SessionID: " . $sessionid;
    
    // Здесь вы можете использовать $sessionid для последующих запросов к API
    // Например, сохранить его для использования в других функциях
} else {
    echo "Ошибка авторизации: ";
    if (isset($data['message'])) {
        echo $data['message'];
    } else {
        echo "Неизвестная ошибка";
    }
    print_r($data); // Вывод полного ответа для отладки
}
?>