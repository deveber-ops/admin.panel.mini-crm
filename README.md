# PROTEGO.BY Admin Panel

## Описание Проекта

PROTEGO.BY Admin Panel - это веб-приложение для управления различными аспектами бизнеса, включая управление пользователями, клиентами, услугами, продажами, а также интеграцию с кассовым оборудованием и системами уведомлений. Проект разработан на PHP с использованием фреймворка Twig для шаблонизации и MySQL в качестве базы данных.

## Функциональные Возможности

*   **Аутентификация и Авторизация**:
    *   Вход/выход пользователей с системой аутентификации.
    *   Защита от атак методом перебора (brute-force) с блокировкой по количеству попыток.
    *   CSRF-защита для всех POST-запросов.
    *   Управление сессиями пользователей.
*   **Управление Пользователями**:
    *   Просмотр, добавление, редактирование и удаление пользователей.
    *   Сброс паролей пользователей с отправкой уведомлений по электронной почте.
    *   Блокировка/разблокировка пользователей.
    *   Управление ролями пользователей.
*   **Управление Клиентами**:
    *   Просмотр, добавление, редактирование и удаление клиентов (физических и юридических лиц).
    *   Поиск клиентов по номеру телефона или юридическим данным.
    *   Управление группами клиентов и скидками.
*   **Управление Услугами**:
    *   Просмотр, добавление, редактирование и удаление услуг.
    *   Управление категориями услуг.
*   **Управление Продажами**:
    *   Регистрация новых продаж с учетом услуг, клиентов, исполнителей и методов оплаты.
    *   Интеграция с кассовым аппаратом (Kassa) для фискализации продаж.
    *   Расчет банковских комиссий на основе BIN-информации карты.
    *   Просмотр и удаление записей о продажах.
*   **Управление Движениями Средств (Касса)**:
    *   Просмотр приходов и расходов по различным методам оплаты.
    *   Добавление и удаление движений средств.
    *   Расчет общей статистики (приходы, расходы, налоги, комиссии).
    *   Управление сменами кассового аппарата (открытие/закрытие смены, получение информации о смене).
*   **Уведомления**:
    *   Отправка электронных писем (через PHPMailer) для приветствия новых пользователей и сброса паролей.
    *   Интеграция с SMS-шлюзом (RocketSMS.by).
*   **Отчетность**:
    *   Формирование X-отчетов кассового аппарата.
    *   Просмотр актов выполненных работ.

## Используемые Технологии

*   **Backend**: PHP (версия 8.0+)
*   **База данных**: MySQL
*   **Шаблонизатор**: Twig
*   **HTTP-клиент**: cURL
*   **Управление зависимостями**: Composer
*   **Отправка почты**: PHPMailer
*   **SMS-шлюз**: RocketSMS.by (интеграция)
*   **BIN-lookup**: binlist.net (интеграция)

## Структура Проекта

```
.
├── public/                 # Публичная директория (точка входа)
│   ├── index.php           # Главный файл маршрутизации
│   ├── assets/             # Статические файлы (CSS, JS, изображения)
│   └── ...
├── src/                    # Исходный код приложения
│   ├── Classes/            # Основные классы (Auth, Database, Kassa, Mailer, Bin, SMS, Exceptions)
│   ├── Config/             # Файлы конфигурации
│   │   ├── app_config.php  # Конфигурация приложения (чувствительные данные)
│   │   └── config.php      # Загрузка app_config.php и определение констант (для обратной совместимости)
│   ├── Controllers/        # Контроллеры MVC
│   ├── Templates/          # Twig шаблоны
│   └── Twig/               # Пользовательские расширения Twig
├── vendor/                 # Зависимости Composer
├── cache/                  # Кэш Twig
├── .htaccess               # Правила перезаписи URL для Apache
├── composer.json           # Описание зависимостей Composer
├── composer.lock           # Заблокированные версии зависимостей
└── README.md               # Этот файл
```

## Установка и Настройка

### 1. Клонирование Репозитория

```bash
git clone <URL_ВАШЕГО_РЕПОЗИТОРИЯ>
cd panel.protego.by
```

### 2. Установка Зависимостей Composer

```bash
composer install
```

### 3. Настройка Базы Данных

Создайте базу данных MySQL (например, `protegoby_admin`).
Импортируйте схему базы данных. Предполагается наличие следующих таблиц:
`users`, `user_groups`, `clients`, `client_groups`, `service_categories`, `services`, `sales`, `sale_items`, `acts`, `payment_methods`, `settings`, `login_attempts`, `csrf_tokens`, `movement_footings`, `movements`, `kassa`.

### 4. Настройка Конфигурации

Отредактируйте файл `src/Config/app_config.php`, заполнив его актуальными данными:

```php
<?php

return [
    'database' => [
        'host' => 'localhost',
        'user' => 'ВАШ_ПОЛЬЗОВАТЕЛЬ_БД',
        'pass' => 'ВАШ_ПАРОЛЬ_БД',
        'name' => 'ВАША_БД',
        'charset' => 'utf8mb4',
    ],

    'site' => [
        'name' => 'PROTEGO',
        'base_url' => 'https://panel.protego.by', // Укажите ваш базовый URL
    ],

    'app' => [
        'name' => 'Protego Admin',
        'debug_mode' => true, // Установите false для продакшн
    ],

    'twig' => [
        'cache' => true, // true для продакшн
        'debug' => true, // false для продакшн
    ],

    'security' => [
        'max_login_attempts' => 5,
        'login_lockout_time' => 300,
        'csrf_token_lifetime' => 3600,
    ],

    'session' => [
        'name' => 'PROTEGO',
        'lifetime' => 43200,
        'path' => '/',
        'domain' => '.protego.by', // Укажите ваш домен с точкой в начале
        'secure' => true, // true для HTTPS
        'httponly' => true,
        'samesite' => 'Strict',
    ],

    'smtp' => [
        'host' => 'ВАШ_SMTP_ХОСТ',
        'port' => 465,
        'encryption' => 'ssl',
        'user' => 'ВАШ_SMTP_ПОЛЬЗОВАТЕЛЬ',
        'pass' => 'ВАШ_SMTP_ПАРОЛЬ',
        'from_email' => 'noreply@protego.by', // Адрес отправителя
        'from_name' => 'PROTEGO.BY', // Имя отправителя
    ],

    'sms' => [
        'rocket_user' => 'ВАШ_ROCKET_SMS_ПОЛЬЗОВАТЕЛЬ',
        'rocket_password' => 'ВАШ_ROCKET_SMS_ПАРОЛЬ',
    ],

    'kassa' => [
        'port' => '8085', // Порт кассового аппарата
    ],
    'bin' => [
        'url' => 'https://lookup.binlist.net/', // URL для BIN-lookup
    ],
];
```

### 5. Настройка Веб-сервера (Apache/Nginx)

Настройте ваш веб-сервер так, чтобы корневая директория документа (document root) указывала на директорию `public/` вашего проекта.

**Пример для Apache (`.htaccess` уже есть, но может потребоваться конфигурация виртуального хоста):**

```apache
<VirtualHost *:80>
    ServerName panel.protego.by
    DocumentRoot /path/to/your/project/public

    <Directory /path/to/your/project/public>
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/error.log
    CustomLog ${APACHE_LOG_DIR}/access.log combined
</VirtualHost>
```

**Пример для Nginx:**

```nginx
server {
    listen 80;
    server_name panel.protego.by;
    root /path/to/your/project/public;

    add_header X-Frame-Options "DENY";
    add_header X-Content-Type-Options "nosniff";
    add_header X-XSS-Protection "1; mode=block";
    add_header Referrer-Policy "strict-origin-when-cross-origin";

    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php8.0-fpm.sock; # Укажите ваш сокет PHP-FPM
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.ht {
        deny all;
    }
}
```

## Использование

После успешной установки и настройки вы можете получить доступ к панели администратора, перейдя по настроенному `BASE_URL` в вашем браузере.

*   **`/login`**: Страница входа в систему.
*   **`/panel`**: Главная панель администратора.
*   **`/users`**: Управление пользователями.
*   **`/clients`**: Управление клиентами.
*   **`/services`**: Управление услугами и категориями.
*   **`/sales-register`**: Регистрация продаж и управление движениями средств.
*   **`/stocks`**: Управление запасами (если реализовано).

## API Эндпоинты (POST-запросы)

Проект использует AJAX-запросы для большинства операций управления данными. Основные POST-эндпоинты включают:

*   `/login`
*   `/checkAuth`
*   `/updateSession`
*   `/{entity} (insert|update|remove){Entity}` (например, `/insertUser`, `/updateClient`, `/removeService`)
*   `/resetPassword`
*   `/blockUser`, `/unblockUser`
*   `/insertMovement`, `/removeMovement`, `/changeMovementCommission`
*   `/closeShift`, `/openShift`
*   `/updateSection`
*   `/sortItems`
*   `/searchClients`
*   `/insertSale`

## Безопасность

Проект включает базовые меры безопасности:
*   **CSRF-защита**: Используется для всех POST-запросов.
*   **Хеширование паролей**: Используется `password_hash()` с `PASSWORD_BCRYPT`.
*   **Подготовленные запросы**: Для предотвращения SQL-инъекций.
*   **Блокировка по количеству попыток входа**: Защита от перебора паролей.
*   **Безопасные заголовки HTTP**: Для защиты от XSS, Clickjacking и других атак.

## Дальнейшие Улучшения

*   **Полное внедрение зависимостей**: Переход от статических методов `getInstance()` и глобальных констант к полноценному контейнеру внедрения зависимостей.
*   **Более строгая валидация ввода**: Расширение валидации данных на стороне сервера.
*   **Логирование**: Внедрение более детального логирования событий и ошибок.
*   **Тестирование**: Добавление модульных и интеграционных тестов.
*   **API Документация**: Создание документации для внутренних API-эндпоинтов.
*   **Frontend Framework**: Использование современного JavaScript-фреймворка для улучшения пользовательского интерфейса и опыта.
*   **Разделение бизнес-логики**: Вынесение сложной бизнес-логики из контроллеров и классов базы данных в отдельные сервисные слои.
