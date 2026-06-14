<?php
namespace App\Controllers;

use App\Classes\Auth;
use App\Classes\Database;
use Twig\Environment;
use App\Classes\Kassa;

class SalesRegisterController {
    private Auth $auth;
    private Environment $twig;
    private Database $db;
    private Kassa $kassa;

    public function __construct(Auth $auth, Environment $twig, Database $db, Kassa $kassa) {
        $this->auth = $auth;
        $this->twig = $twig;
        $this->db = $db;
        $this->kassa = $kassa;
    }

    public function index() {
        $shiftInfo = [];
        
        try {
            $shiftInfo = $this->kassa->shiftInfo();
        } catch (\Exception $e) {
            $shiftInfo = $e->getMessage();
        }

        echo $this->twig->render('/pages/salesRegister.twig', [
            'MOVEMENTS' => $this->db->getMovements(),
            'AMOUNTS' => $this->db->getMovements(amounts: true),
            'FOOTINGS' => $this->db->getMovementFootings(),
            'PAYMETHODS' => $this->db->getPayMethods(),
            'SHIFT' => $shiftInfo,
            'PAGE' => 'sales-register'
        ]);
    }
}