<?php
declare(strict_types=1);

ini_set('display_errors', '1');
error_reporting(E_ALL);

require dirname(__DIR__) . '/src/Config/config.php';
require BASE_DIR .'/vendor/autoload.php';

global $config;

header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("X-XSS-Protection: 1; mode=block");
header("Referrer-Policy: strict-origin-when-cross-origin");

$loader = new \Twig\Loader\FilesystemLoader(TEMPLATES_DIR);
$twig = new \Twig\Environment($loader, [
    'cache' => CACHE_DIR . '/twig',
    'debug' => $config['twig']['debug'],
    'auto_reload' => $config['twig']['debug']
]);

$twig->addExtension(new App\Twig\RolesExtension());

$twig->addGlobal('BASE_DIR', BASE_DIR);
$twig->addGlobal('ASSETS_DIR', ASSETS_DIR);
$twig->addGlobal('SITE_NAME', $config['site']['name']);
$twig->addGlobal('BASE_URL', $config['site']['base_url']);

$phoneFormat = new \Twig\TwigFunction('phoneFormat', function ($number) {
    if (empty($number) || $number == null) {
        return '';
    } else {
        return "+375 (" . substr($number, -9, -7) . ") " . substr($number, -7, -4) . "-" . substr($number, -4, -2) . "-" . substr($number, -2);
    }
});

$dateTimeFormat = new \Twig\TwigFunction('dateTimeFormat', function ($date) {
    if (empty($date) || $date == null) {
        return '';
    } else {
        $date = new DateTime($date);
        return $date->format('d.m.Y - H:i');
    }
});

$dateFormat = new \Twig\TwigFunction('dateFormat', function ($date) {
    if (empty($date) || $date == null) {
        return '';
    } else {
        $date = new DateTime($date);
        return $date->format('d.m.Y');
    }
});

$toFixed = new \Twig\TwigFunction('toFixed', function ($number, $decimals = 2) {
    if (empty($number) || $number == null) {
        return 0;
    } else {
        return number_format(floatval($number), $decimals, '.', '');
    }
});

$twig->addFunction($phoneFormat);
$twig->addFunction($dateTimeFormat);
$twig->addFunction($dateFormat);
$twig->addFunction($toFixed);

set_error_handler(function($errno, $errstr) use ($config) {
    if (str_contains($errstr, 'Mailer')) {
        error_log("Mail Error: $errstr");
        if ($config['app']['debug_mode']) {
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
                strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                echo json_encode(['error' => 'Ошибка отправки письма']);
                exit;
            }
        }
    }
});

try {
    $db = App\Classes\Database::getInstance($config['database']);
    $kassa = App\Classes\Kassa::getInstance($db, $config['kassa'], $_SERVER['REMOTE_ADDR']);
    $bin = App\Classes\Bin::getInstance($config['bin']);
    $mailer = App\Classes\Mailer::getInstance($twig, $config['smtp']);

    $sms = new \App\Classes\SMS($config['sms']);
    
    $auth = new App\Classes\Auth($db, $config['security'], $config['session']);
    
    $requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    $route = trim($requestPath, '/');

    if ($auth->isLoggedIn()) {
        $twig->addGlobal('USERNAME', $_SESSION['username']);
        $twig->addGlobal('USERID', $_SESSION['userID']);
        $twig->addGlobal('U_ROLE', $_SESSION['role']);
        $fullName = $_SESSION['fullName'];

        $parts = explode(" ", $fullName);

        if (count($parts) === 3) {
            $lastName = $parts[0];
            $firstNameInitial = mb_substr($parts[1], 0, 1) . ".";
            $middleNameInitial = mb_substr($parts[2], 0, 1) . ".";
            
            $shortName = $lastName . " " . $firstNameInitial . $middleNameInitial;
        } else {
            $shortName = $fullName;
        }

        $twig->addGlobal('U_SHORTNAME', $shortName);
    }
    
    $routes = [
        'GET' => [
            'login' => function() use ($auth, $twig) {
                if ($auth->isLoggedIn()) {
                    header('Location: /panel');
                    exit;
                }
                (new App\Controllers\AuthController($auth, $twig))->showLogin();
            },
            'panel' => function() use ($auth, $twig, $db) {
                if (!$auth->isLoggedIn()) {
                    header('Location: /login');
                    exit;
                }
                (new App\Controllers\PanelController($auth, $twig, $db))->index();
            },
            'services' => function() use ($auth, $twig, $db) {
                if (!$auth->isLoggedIn()) {
                    header('Location: /login');
                    exit;
                }
                (new App\Controllers\ServicesController($auth, $twig, $db))->index();
            },
            'users' => function() use ($auth, $twig, $db) {
                if (!$auth->isLoggedIn()) {
                    header('Location: /login');
                    exit;
                }
                (new App\Controllers\UsersController($auth, $twig, $db))->index();
            },
            'clients' => function() use ($auth, $twig, $db) {
                if (!$auth->isLoggedIn()) {
                    header('Location: /login');
                    exit;
                }
                (new App\Controllers\ClientsController($auth, $twig, $db))->index();
            },
            'sales-register' => function() use ($auth, $twig, $db, $kassa) {
                if (!$auth->isLoggedIn()) {
                    header('Location: /login');
                    exit;
                }
                (new App\Controllers\SalesRegisterController($auth, $twig, $db, $kassa))->index();
            },
            'stocks' => function() use ($auth, $twig, $db) {
                if (!$auth->isLoggedIn()) {
                    header('Location: /login');
                    exit;
                }
                (new App\Controllers\StocksController($auth, $twig, $db))->index();
            },
            'logout' => function() use ($auth) {
                $auth->logout();
                header('Location: /login');
                exit;
            },
            'workAct' => function() use ($twig, $db) {
                $saleID = $_GET['saleID'] ?? null;
        
                if (!$saleID) {
                    http_response_code(400);
                    echo $twig->render('/errors/400.twig', [
                        'message' => 'Не указан ID продажи'
                    ]);
                    exit;
                }

                (new App\Controllers\ActsController($twig, $db))->index($saleID);
            },
            '' => function() use ($auth) {
                if ($auth->isLoggedIn()) {
                    header('Location: /panel');
                } else {
                    header('Location: /login');
                }
                exit;
            }
        ],
        'POST' => [
            'login' => function() use ($auth, $twig) {
                (new App\Controllers\AuthController($auth, $twig))->handleLogin();
            },
            'checkAuth' => function () use ($config) {
                session_start();
                
                if (!isset($_SESSION['userID'])) {
                    http_response_code(401);
                    exit;
                }

                $sessionLifetime = $config['session']['lifetime'];
                $secondsRemaining = max(0, ($_SESSION['LAST_ACTIVITY'] + $sessionLifetime) - time());
                
                header('Content-Type: application/json');
                echo json_encode(['seconds' => $secondsRemaining]);
            },
            'updateSession' => function() use ($auth, $twig) {
                header('Content-Type: application/json');
                session_regenerate_id(true);
                echo json_encode(['success' => true, 'message' => 'Сессия продлена на <strong>12 часов</strong>']);
                exit;
            },
            'insertUser' => function() use ($auth, $db, $mailer, $config) {
                header('Content-Type: application/json');
                if (!$auth->isLoggedIn()) {
                    $error = [
                        "error" => "Данный ресурс предназначен только для авторизованных пользователей!",
                    ];
                    echo(json_encode($error, JSON_UNESCAPED_UNICODE));
                    exit;
                }
                $response = $db->insertUser($_POST['role'], $_POST['post'], $_POST['full_name'], $_POST['email'], $_POST['phone']);

                if ($response['success']) {
                    $fullNameParts = explode(' ', trim($_POST['full_name']));
                    $name = $fullNameParts[1] ?? $_POST['full_name'];
                    $mailer->sendWelcomeEmail($_POST['email'], $name, $response['username'], $response['password']);
                }
                echo(json_encode($response, JSON_UNESCAPED_UNICODE));
            },
            'updateUser' => function() use ($auth, $db) {
                header('Content-Type: application/json');
                if (!$auth->isLoggedIn()) {
                    $error = [
                        "error" => "Данный ресурс предназначен только для авторизованных пользователей!",
                    ];
                    echo(json_encode($error, JSON_UNESCAPED_UNICODE));
                    exit;
                }
                $response = $db->updateUser((int)$_POST['id'], $_POST['role'], $_POST['post'], $_POST['full_name'], $_POST['email'], $_POST['phone']);
                echo(json_encode($response, JSON_UNESCAPED_UNICODE));
            },
            'removeUser' => function() use ($auth, $db) {
                header('Content-Type: application/json');
                if (!$auth->isLoggedIn()) {
                    $error = [
                        "error" => "Данный ресурс предназначен только для авторизованных пользователей!",
                    ];
                    echo(json_encode($error, JSON_UNESCAPED_UNICODE));
                    exit;
                }
                $response = $db->removeUser((int)$_POST['id'], $_POST['name']);
                echo(json_encode($response, JSON_UNESCAPED_UNICODE));
            },
            'resetPassword' => function() use ($auth, $db, $mailer, $config) {
                header('Content-Type: application/json');
                if (!$auth->isLoggedIn()) {
                    $error = [
                        "error" => "Данный ресурс предназначен только для авторизованных пользователей!",
                    ];
                    echo(json_encode($error, JSON_UNESCAPED_UNICODE));
                    exit;
                }
                $response = $db->resetPassword((int)$_POST['id']);

                if ($response['success']) {
                    $mailer->resetPasswordEmail($response['email'], $response['name'], $response['username'], $response['password']);
                }
                echo(json_encode($response, JSON_UNESCAPED_UNICODE));
            },
            'blockUser' => function() use ($auth, $db) {
                header('Content-Type: application/json');
                if (!$auth->isLoggedIn()) {
                    $error = [
                        "error" => "Данный ресурс предназначен только для авторизованных пользователей!",
                    ];
                    echo(json_encode($error, JSON_UNESCAPED_UNICODE));
                    exit;
                }
                $response = $db->blockUser((int)$_POST['id']);
                echo(json_encode($response, JSON_UNESCAPED_UNICODE));
            },
            'unblockUser' => function() use ($auth, $db) {
                header('Content-Type: application/json');
                if (!$auth->isLoggedIn()) {
                    $error = [
                        "error" => "Данный ресурс предназначен только для авторизованных пользователей!",
                    ];
                    echo(json_encode($error, JSON_UNESCAPED_UNICODE));
                    exit;
                }
                $response = $db->unblockUser((int)$_POST['id']);
                echo(json_encode($response, JSON_UNESCAPED_UNICODE));
            },
            'insertRole' => function() use ($auth, $db) {
                header('Content-Type: application/json');
                if (!$auth->isLoggedIn()) {
                    $error = [
                        "error" => "Данный ресурс предназначен только для авторизованных пользователей!",
                    ];
                    echo(json_encode($error, JSON_UNESCAPED_UNICODE));
                    exit;
                }
                $response = $db->insertRole($_POST['name'], $_POST['code'], $_POST['description']);
                echo(json_encode($response, JSON_UNESCAPED_UNICODE));
            },
            'updateRole' => function() use ($auth, $db) {
                header('Content-Type: application/json');
                if (!$auth->isLoggedIn()) {
                    $error = [
                        "error" => "Данный ресурс предназначен только для авторизованных пользователей!",
                    ];
                    echo(json_encode($error, JSON_UNESCAPED_UNICODE));
                    exit;
                }
                $response = $db->updateRole((int)$_POST['id'], $_POST['name'], $_POST['code'], $_POST['description']);
                echo(json_encode($response, JSON_UNESCAPED_UNICODE));
            },
            'removeRole' => function() use ($auth, $db) {
                header('Content-Type: application/json');
                if (!$auth->isLoggedIn()) {
                    $error = [
                        "error" => "Данный ресурс предназначен только для авторизованных пользователей!",
                    ];
                    echo(json_encode($error, JSON_UNESCAPED_UNICODE));
                    exit;
                }
                $response = $db->removeRole((int)$_POST['id'], $_POST['name']);
                echo(json_encode($response, JSON_UNESCAPED_UNICODE));
            },
            'insertCategory' => function() use ($auth, $db) {
                header('Content-Type: application/json');
                if (!$auth->isLoggedIn()) {
                    $error = [
                        "error" => "Данный ресурс предназначен только для авторизованных пользователей!",
                    ];
                    echo(json_encode($error, JSON_UNESCAPED_UNICODE));
                    exit;
                }
                $response = $db->insertCategory($_POST['name']);
                echo(json_encode($response, JSON_UNESCAPED_UNICODE));
            },
            'removeCategory' => function() use ($auth, $db) {
                header('Content-Type: application/json');
                if (!$auth->isLoggedIn()) {
                    $error = [
                        "error" => "Данный ресурс предназначен только для авторизованных пользователей!",
                    ];
                    echo(json_encode($error, JSON_UNESCAPED_UNICODE));
                    exit;
                }
                $response = $db->removeCategory((int)$_POST['id'], $_POST['name']);
                echo(json_encode($response, JSON_UNESCAPED_UNICODE));
            },
            'updateCategory' => function() use ($auth, $db) {
                header('Content-Type: application/json');
                if (!$auth->isLoggedIn()) {
                    $error = [
                        "error" => "Данный ресурс предназначен только для авторизованных пользователей!",
                    ];
                    echo(json_encode($error, JSON_UNESCAPED_UNICODE));
                    exit;
                }
                $response = $db->updateCategory((int)$_POST['id'], $_POST['name']);
                echo(json_encode($response, JSON_UNESCAPED_UNICODE));
            },
            'insertService' => function() use ($auth, $db) {
                header('Content-Type: application/json');
                if (!$auth->isLoggedIn()) {
                    $error = [
                        "error" => "Данный ресурс предназначен только для авторизованных пользователей!",
                    ];
                    echo(json_encode($error, JSON_UNESCAPED_UNICODE));
                    exit;
                }
                $response = $db->insertService((int)$_POST['category_id'], $_POST['name'], (float)$_POST['price'], (string)$_POST['description'], (int)$_POST['completion_time'], (int)$_POST['warranty_days']);
                echo(json_encode($response, JSON_UNESCAPED_UNICODE));
            },
            'removeService' => function() use ($auth, $db) {
                header('Content-Type: application/json');
                if (!$auth->isLoggedIn()) {
                    $error = [
                        "error" => "Данный ресурс предназначен только для авторизованных пользователей!",
                    ];
                    echo(json_encode($error, JSON_UNESCAPED_UNICODE));
                    exit;
                }
                $response = $db->removeService((int)$_POST['id'], $_POST['name']);
                echo(json_encode($response, JSON_UNESCAPED_UNICODE));
            },
            'updateService' => function() use ($auth, $db) {
                header('Content-Type: application/json');
                if (!$auth->isLoggedIn()) {
                    $error = [
                        "error" => "Данный ресурс предназначен только для авторизованных пользователей!",
                    ];
                    echo(json_encode($error, JSON_UNESCAPED_UNICODE));
                    exit;
                }
                $response = $db->updateService((int)$_POST['id'], (int)$_POST['category_id'], $_POST['name'], (float)$_POST['price'], (int)$_POST['warranty_days'], (int)$_POST['completion_time'], $_POST['description']);
                echo(json_encode($response, JSON_UNESCAPED_UNICODE));
            },
            'insertClientGroup' => function() use ($auth, $db) {
                header('Content-Type: application/json');
                if (!$auth->isLoggedIn()) {
                    $error = [
                        "error" => "Данный ресурс предназначен только для авторизованных пользователей!",
                    ];
                    echo(json_encode($error, JSON_UNESCAPED_UNICODE));
                    exit;
                }
                $response = $db->insertClientGroup($_POST['name'], (float)$_POST['discount']);
                echo(json_encode($response, JSON_UNESCAPED_UNICODE));
            },
            'updateClientGroup' => function() use ($auth, $db) {
                header('Content-Type: application/json');
                if (!$auth->isLoggedIn()) {
                    $error = [
                        "error" => "Данный ресурс предназначен только для авторизованных пользователей!",
                    ];
                    echo(json_encode($error, JSON_UNESCAPED_UNICODE));
                    exit;
                }
                $response = $db->updateClientGroup((int)$_POST['id'], $_POST['name'], (float)$_POST['discount']);
                echo(json_encode($response, JSON_UNESCAPED_UNICODE));
            },
            'removeClientGroup' => function() use ($auth, $db) {
                header('Content-Type: application/json');
                if (!$auth->isLoggedIn()) {
                    $error = [
                        "error" => "Данный ресурс предназначен только для авторизованных пользователей!",
                    ];
                    echo(json_encode($error, JSON_UNESCAPED_UNICODE));
                    exit;
                }
                $response = $db->removeClientGroup((int)$_POST['id'], $_POST['name']);
                echo(json_encode($response, JSON_UNESCAPED_UNICODE));
            },
            'insertClient' => function() use ($auth, $db) {
                header('Content-Type: application/json');
                if (!$auth->isLoggedIn()) {
                    $error = [
                        "error" => "Данный ресурс предназначен только для авторизованных пользователей!",
                    ];
                    echo(json_encode($error, JSON_UNESCAPED_UNICODE));
                    exit;
                }
                $response = $db->insertClient($_POST['name'], $_POST['phone'], $_POST['type'], $_POST['group'], (int)$_POST['discount'], $_POST['legal_form'], $_POST['legal_name'], $_POST['legal_unp'], $_POST['legal_address'], $_POST['legal_email'], $_POST['legal_phone'], $_POST['legal_bank'], $_POST['legal_check'], $_POST['legal_bic'], $_POST['legal_bank_address'], $_POST['legal_post'], $_POST['legal_signatory'], $_POST['legal_document'], $_POST['json']);
                echo(json_encode($response, JSON_UNESCAPED_UNICODE));
            },
            'removeClient' => function() use ($auth, $db) {
                header('Content-Type: application/json');
                if (!$auth->isLoggedIn()) {
                    $error = [
                        "error" => "Данный ресурс предназначен только для авторизованных пользователей!",
                    ];
                    echo(json_encode($error, JSON_UNESCAPED_UNICODE));
                    exit;
                }
                $response = $db->removeClient((int)$_POST['id'], $_POST['name']);
                echo(json_encode($response, JSON_UNESCAPED_UNICODE));
            },
            'updateClient' => function() use ($auth, $db) {
                header('Content-Type: application/json');
                if (!$auth->isLoggedIn()) {
                    $error = [
                        "error" => "Данный ресурс предназначен только для авторизованных пользователей!",
                    ];
                    echo(json_encode($error, JSON_UNESCAPED_UNICODE));
                    exit;
                }
                $response = $db->updateClient((int)$_POST['id'] ,$_POST['name'], $_POST['phone'], $_POST['type'], $_POST['group'], (int)$_POST['discount'], $_POST['legal_form'], $_POST['legal_name'], $_POST['legal_unp'], $_POST['legal_address'], $_POST['legal_email'], $_POST['legal_phone'], $_POST['legal_bank'], $_POST['legal_check'], $_POST['legal_bic'], $_POST['legal_bank_address'], $_POST['legal_post'], $_POST['legal_signatory'], $_POST['legal_document'], $_POST['json']);
                echo(json_encode($response, JSON_UNESCAPED_UNICODE));
            },
            'updateSection' => function() use ($auth, $twig, $db, $kassa, $config) {
                header('Content-Type: application/json');
                $name = $_POST['sectionName'];
                $filter = json_decode($_POST['filter'], true);
                $response = [];
                $twigArray =[];

                if (!$auth->isLoggedIn()) {
                    $response = [
                        "error" => "Данный ресурс предназначен только для авторизованных пользователей!",
                    ];
                    echo(json_encode($response, JSON_UNESCAPED_UNICODE));
                    exit;
                } else {
                    if ($name == 'insertServiceModal' || $name == 'updateServiceModal' || $name == 'categoriesList') {
                        $twigArray['CATEGORIES'] = $db->getCategories();
                    } else if ($name == 'insertUserModal' || $name == 'updateUserModal' || $name == 'userRolesList') {
                        $twigArray['ROLES'] = $db->getRoles();
                    } else if ($name == 'servicesList') {
                        $twigArray['SERVICES'] = $db->getServices();
                    } else if ($name == 'usersList') {
                        $twigArray['USERS'] = $db->getUsers();
                    } else if ($name == 'clientGroupsList') {
                        $twigArray['GROUPS'] = $db->getClientGroups();
                    } else if ($name == 'clientsList') {
                        $twigArray['CLIENTS'] = $db->getClients();
                    } else if ($name == 'salesList') {
                        $twigArray['SALES'] = $db->getSales();
                    } else if ($name == 'totalSumms') {
                        $twigArray['AMOUNTS'] = $db->getMovements(amounts: true, filters: $filter);
                    } else if ($name == 'movementsList' || $name == 'movementsFilter') {
                        $twigArray['MOVEMENTS'] = $db->getMovements(filters: $filter);
                        $twigArray['PAYMETHODS'] = $db->getPayMethods();
                    } else if ($name == 'insertMovementModal') {
                        $twigArray['MOVEMENTS'] = $db->getMovements();
                    } else if ($name == 'shiftBlock') {
                        $shiftInfo = [];
                        try {
                            $shiftInfo = $kassa->shiftInfo();
                        } catch (\Exception $e) {
                            $shiftInfo = $e->getMessage();
                        }
                        $twigArray['SHIFT'] = $shiftInfo;
                    }
                    $section = $twig->render('/sections/'. $name .'.twig', $twigArray);
                }

                $response['html'] = $section;
                echo(json_encode($response, JSON_UNESCAPED_UNICODE));
            },
            'sortItems' => function() use ($auth, $twig, $db) {
                header('Content-Type: application/json');
                $name = $_POST['sort'];
                $id = $_POST['id'] == 'null' ? null : (int)$_POST['id'];
                $items;

                if (!$auth->isLoggedIn()) {
                    $response['error'] = 'Данный ресурс предназначен только для авторизованных пользователей';
                    echo(json_encode($response, JSON_UNESCAPED_UNICODE));
                    exit;
                } else if ($name == 'usersList') {
                    $items = $db->getUsers(roleID: $id);
                    $twigArray['USERS'] = $items;
                    $emptyMessage = 'У этой группы еще нет ни одного пользователя.';
                } else if ($name == 'servicesList') {
                    $items = $db->getServices(categoryID: $id);
                    $twigArray['SERVICES'] = $items;
                    $emptyMessage = 'У этой категории еще нет ни одной услуги.';
                } else if ($name == 'clientsList') {
                    $items = $db->getClients(group: $id);
                    $twigArray['CLIENTS'] = $items;
                    $emptyMessage = 'У этой группы еще нет ни одного клиента.';
                } else {
                    $response['success'] = false;
                    $response['message'] = 'Неизвестный тип сортировки.';
                    echo(json_encode($response, JSON_UNESCAPED_UNICODE));
                    exit;
                }

                if (empty($items)) {
                    $response['success'] = false;
                    $response['message'] = $emptyMessage;
                    $response['items'] = $items;
                } else {
                    $response['success'] = true;
                    $response['html'] = $twig->render('/sections/'. $name .'.twig', $twigArray);
                }

                echo(json_encode($response, JSON_UNESCAPED_UNICODE));
            },
            'searchClients' => function() use ($auth, $twig, $db) {
                header('Content-Type: application/json');
                $phone = $_POST['phone'];
                $legal = empty($_POST['legal']) || !isset($_POST['legal']) ? null : $_POST['legal'];
                
                $items = $db->getClients(phone: (int)$phone, legal: $legal);
                $twigArray['CLIENTS'] = $items;

                if (!$auth->isLoggedIn()) {
                    $response['error'] = 'Данный ресурс предназначен только для авторизованных пользователей';
                    echo(json_encode($response, JSON_UNESCAPED_UNICODE));
                    exit;
                } else if (empty($items)) {
                    $response['success'] = false;
                    $response['message'] = 'Клиент не найден.';
                } else {
                    $response['success'] = true;
                    $response['html'] = $twig->render('/sections/clientsList.twig', $twigArray);
                    $response['json'] = json_encode($items);
                }

                echo(json_encode($response, JSON_UNESCAPED_UNICODE));
            },
            'insertSale' => function() use ($auth, $db, $kassa, $bin, $config) {
                header('Content-Type: application/json');
                $response = ['success' => false, 'paid' => false];

                if (!$auth->isLoggedIn()) {
                    $response['error'] = "Данный ресурс предназначен только для авторизованных пользователей!";
                    echo(json_encode($error, JSON_UNESCAPED_UNICODE));
                    exit;
                }

                try {
                    $receipt = ($_POST['receipt'] ?? 'false') == 'true';
                    $pay_method_id = (int)$_POST['pay_method'];
                    $cash = (float)($_POST['cash'] ?? 0);
                    $card = (float)($_POST['card'] ?? 0);
                    $noncash = (float)($_POST['noncash'] ?? 0);
                    $summ = (float)$_POST['summ'];
                    $services = $_POST['services'];

                    $bankComission = 0;
                    $kassaPaid = ['success' => false, 'kardNum' => null];

                    $paymentMethod = $db->getPayMethods($pay_method_id);
                    $payMethodCode = $paymentMethod[0]['code'] ?? null;

                    if ($payMethodCode === 'CASH' || $payMethodCode === 'MIXED' || ($payMethodCode === 'CARD' && $receipt)) {
                        $saleData = [
                            "currency" => "BYN",
                            "items" => [],
                            "paymentMethods" => []
                        ];

                        foreach ($services as $index => $service) {
                            $serviceInfo = $db->getServices(id: $service['id'])[0];

                            $saleData['items'][] = [
                                'discount' => $service['discount'] !== '' ? floatval($db->toFixed($service['price'] * ($service['discount'] / 100))) : 0,
                                'info' => [
                                    'amount' => floatval($service['price']),
                                    'id' => intval($service['id']),
                                    'productName' => $serviceInfo['category_name'] . ' - ' . $serviceInfo['name'] . ' | #',
                                    'quantity' => 1,
                                    'type' => 3
                                ],
                                'sum' => floatval($service['price'])
                            ];
                        }

                        if ($cash > 0) {
                            $saleData['paymentMethods']['CASH'] = $cash;
                        }
                        if ($card > 0) {
                            $saleData['paymentMethods']['CARD'] = $card;
                        }

                        $kassaPaid = $kassa->saleRegister($saleData);
                        $response['paid'] = $kassaPaid['success'];

                        if ($response['paid'] && isset($kassaPaid['kardNum'])) {
                            $cardInfo = $bin->checkBinInfo(substr($kassaPaid['kardNum'], 0, 6));

                            if ($cardInfo['country'] !== 'BY') {
                                $bankComission = $card * 0.027;
                            } else if ($cardInfo['country'] == 'BY' && $cardInfo['bank'] !== 'Closed Joint Stock Company "Alfa-Bank"') {
                                $bankComission = $card * 0.019;
                            }  else if ($cardInfo['country'] == 'BY' && $cardInfo['bank'] == 'Closed Joint Stock Company "Alfa-Bank"') {
                                $bankComission = $card * 0.015;
                            }
                        }
                    } else {
                        $response['paid'] = true;
                    }

                    if ($response['paid']) {
                        $dbResponse = $db->insertSale((int)$_POST['executor_id'], (string)$_POST['phone'], (string)$_POST['name'], $services, $summ, (string)$_POST['pay_method'], $cash, $card, $noncash, (string)$_POST['receipt'], $bankComission);
                        $response = array_merge($response, $dbResponse);
                    } else {
                        $response['message'] = $kassaPaid['message'] ?? 'Ошибка оплаты через кассу.';
                    }

                } catch (\Exception $e) {
                    $response['message'] = $e->getMessage();
                }
                echo(json_encode($response, JSON_UNESCAPED_UNICODE));
            },
            'removeSale' => function() use ($auth, $db) {
                header('Content-Type: application/json');
                if (!$auth->isLoggedIn()) {
                    $error = [
                        "error" => "Данный ресурс предназначен только для авторизованных пользователей!",
                    ];
                    echo(json_encode($error, JSON_UNESCAPED_UNICODE));
                    exit;
                }
                $response = $db->removeSale((int)$_POST['id']);
                echo(json_encode($response, JSON_UNESCAPED_UNICODE));
            },
            'insertMovement' => function() use ($auth, $db) {
                header('Content-Type: application/json');
                if (!$auth->isLoggedIn()) {
                    $error = [
                        "error" => "Данный ресурс предназначен только для авторизованных пользователей!",
                    ];
                    echo(json_encode($error, JSON_UNESCAPED_UNICODE));
                    exit;
                }
                $response = $db->insertMovement((string)$_POST['type'], (float)$_POST['summ'], (string)$_POST['footing'], (int)$_POST['pay_method']);
                echo(json_encode($response, JSON_UNESCAPED_UNICODE));
            },
            'removeMovement' => function() use ($auth, $db) {
                header('Content-Type: application/json');
                if (!$auth->isLoggedIn()) {
                    $error = [
                        "error" => "Данный ресурс предназначен только для авторизованных пользователей!",
                    ];
                    echo(json_encode($error, JSON_UNESCAPED_UNICODE));
                    exit;
                }
                $response = $db->removeMovement((int)$_POST['id']);
                echo(json_encode($response, JSON_UNESCAPED_UNICODE));
            },
            'changeMovementCommission' => function() use ($auth, $db) {
                header('Content-Type: application/json');
                if (!$auth->isLoggedIn()) {
                    $error = [
                        "error" => "Данный ресурс предназначен только для авторизованных пользователей!",
                    ];
                    echo(json_encode($error, JSON_UNESCAPED_UNICODE));
                    exit;
                }
                $response = $db->changeCommission((int)$_POST['id'], (float)$_POST['value']);
                echo(json_encode($response, JSON_UNESCAPED_UNICODE));
            },
            'closeShift' => function() use ($auth, $kassa) {
                header('Content-Type: application/json');
                $response = [];
                if (!$auth->isLoggedIn()) {
                    $response['success'] = false;
                    $response['message'] = 'Данный ресурс предназначен только для авторизованных пользователей!';
                    echo(json_encode($response, JSON_UNESCAPED_UNICODE));
                    exit;
                } else {
                    try {
                        $kassa->closeShift();
                        $response['success'] = true;
                        $response['message'] = 'Смена успешно закрыта.';
                    } catch (\Exception $e) {
                        $response['success'] = false;
                        $response['message'] = $e->getMessage();
                    }
                }
                echo(json_encode($response, JSON_UNESCAPED_UNICODE));
            },
            'openShift' => function() use ($auth, $kassa) {
                header('Content-Type: application/json');
                $response = [];
                if (!$auth->isLoggedIn()) {
                    $response['success'] = false;
                    $response['message'] = 'Данный ресурс предназначен только для авторизованных пользователей!';
                    echo(json_encode($response, JSON_UNESCAPED_UNICODE));
                    exit;
                } else {
                    try {
                        $kassa->openShift((int)$_POST['id'], (string)$_POST['shortname']);
                        $response['success'] = true;
                        $response['message'] = 'Смена успешно открыта. Хорошей работы и отличных продаж!';
                    } catch (\Exception $e) {
                        $response['success'] = false;
                        $response['message'] = $e->getMessage();
                    }
                }
                echo(json_encode($response, JSON_UNESCAPED_UNICODE));
            }
        ]
    ];
    
    if (isset($routes[$requestMethod][$route])) {
        $routes[$requestMethod][$route]();
    } else {
        http_response_code(404);
        echo $twig->render('/errors/404.twig', [
            'auth' => ['isLoggedIn' => $auth->isLoggedIn()]
        ]);
        exit;
    }

} catch (Throwable $e) {
    error_log("Error: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
    http_response_code(500);
    
    if (isset($twig)) {
        echo $twig->render('/errors/error.twig', [
            'debug_mode' => $config['app']['debug_mode'],
            'error_title' => 'Ошибка сервера',
            'error_message' => $config['app']['debug_mode'] ? $e->getMessage() : 'Произошла внутренняя ошибка сервера',
            'error_code' => $e->getCode(),
            'error_file' => $config['app']['debug_mode'] ? $e->getFile() : '',
            'error_line' => $config['app']['debug_mode'] ? $e->getLine() : '',
            'error_trace' => $config['app']['debug_mode'] ? $e->getTraceAsString() : '',
            'auth' => ['isLoggedIn' => isset($auth) ? $auth->isLoggedIn() : false]
        ]);
    } else {
        echo 'Произошла ошибка. Пожалуйста, попробуйте позже.';
    }
    exit;
}