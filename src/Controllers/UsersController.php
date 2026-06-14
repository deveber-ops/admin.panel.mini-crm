<?php
namespace App\Controllers;

use App\Classes\Auth;
use App\Classes\Database;
use Twig\Environment;

class UsersController {
    private $auth;
    private Environment $twig;
    private Database $db;
    
    public function __construct(Auth $auth, Environment $twig, Database $db) {
        $this->auth = $auth;
        $this->twig = $twig;
        $this->db = $db;
    }

    public function index() {
        echo $this->twig->render('/pages/users.twig', [
            'PAGE' => 'users',
            'ROLES' => $this->db->getRoles(),
            'USERS' => $this->db->getUsers(),
        ]);
    }
}