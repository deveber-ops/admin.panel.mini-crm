<?php
namespace App\Controllers;

use App\Classes\Auth;
use App\Classes\Database;
use Twig\Environment;

class ClientsController {
    private $auth;
    private Environment $twig;
    private Database $db;
    
    public function __construct(Auth $auth, Environment $twig, Database $db) {
        $this->auth = $auth;
        $this->twig = $twig;
        $this->db = $db;
    }

    public function index() {
        echo $this->twig->render('/pages/clients.twig', [
            'PAGE' => 'clients',
            'GROUPS' => $this->db->getClientGroups(),
            'CLIENTS' => $this->db->getClients(),
            'LEGAL_FORMS' => $this->db->getLegalForms(),
        ]);
    }
}