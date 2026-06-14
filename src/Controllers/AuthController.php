<?php
namespace App\Controllers;

use App\Classes\Auth;
use Twig\Environment;

class AuthController
{
    private Auth $auth;
    private Environment $twig;

    public function __construct(Auth $auth, Environment $twig) {
        $this->auth = $auth;
        $this->twig = $twig;
    }

    public function showLogin(): void {
        $token = $this->auth->generateCsrfToken();
        
        echo $this->twig->render('/pages/login.twig', [
            'csrf_token' => $token,
            'error' => $_SESSION['login_error'] ?? null,
            'PAGE' => 'login'
        ]);
        
        unset($_SESSION['login_error']);
    }

    public function handleLogin(): void {
        try {
            if (empty($_POST['csrf_token'])) {
                throw new \RuntimeException('Отсутствует токен безопастности, перезагрузите страницу или попробуйте позже!');
            }

            if (!$this->auth->validateCsrfToken($_POST['csrf_token'])) {
                throw new \RuntimeException('Токен безопастности недействителен, перезагрузите страницу или попробуйте позже!');
            }

            $rememberMe = isset($_POST['remember_me']) && $_POST['remember_me'] === 'on';

            if (!$this->auth->login(
                $_POST['username'] ?? '',
                $_POST['password'] ?? '',
                $rememberMe
            )) {
                throw new \RuntimeException('Имя пользователя или пароль не верны!');
            }

            header('Location: /panel');
            exit;
        } catch (\Exception $e) {
            $_SESSION['login_error'] = $e->getMessage();
            header('Location: /login');
            exit;
        }
    }

    public function logout(): void
    {
        $this->auth->logout();
        header('Location: /login');
        exit;
    }
}