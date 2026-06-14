<?php
namespace App\Controllers;

use App\Classes\Database;
use Twig\Environment;

class ActsController {
    private Environment $twig;
    private Database $db;
    
    public function __construct(Environment $twig, Database $db) {
        $this->twig = $twig;
        $this->db = $db;
    }

    public function index($saleID) {
        $getAct = $this->db->getAct($saleID);
        $getClient = $this->db->getClient();
        echo $this->twig->render('/pages/completedWorks.twig', [
            'CLIENT_NAME' => 'Artur',
            'CLIENT_PHONE' => '375293333333',
            'SALE' => 'clients',
        ]);
    }
}