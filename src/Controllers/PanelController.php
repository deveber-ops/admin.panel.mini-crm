<?php
namespace App\Controllers;

use App\Classes\Auth;
use App\Classes\Database;
use Twig\Environment;

class PanelController {
    private $auth;
    private Environment $twig;
    private Database $db;
    
    public function __construct(Auth $auth, Environment $twig, Database $db) {
        $this->auth = $auth;
        $this->twig = $twig;
        $this->db = $db;
    }

    public function index() {
        echo $this->twig->render('/pages/panel.twig', [
            'SALES' => $this->db->getSales(),
            'SERVICES' => $this->db->getServices(),
            'CATEGORIES' => $this->db->getCategories(),
            'GROUPS' => $this->db->getClientGroups(),
            'PAYMETHODS' => $this->db->getPayMethods(),
            'PAGE' => 'panel'
        ]);
    }
}