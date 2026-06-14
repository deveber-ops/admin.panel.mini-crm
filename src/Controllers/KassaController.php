<?php
namespace App\Controllers;

use App\Classes\Auth;
use App\Classes\Database;
use Twig\Environment;
use App\Classes\Exceptions\KassaException;

class KassaController {
    private $IP;
    private $PORT;
    private $PIN;
    private $URL;

    public function __construct() {
        $this->IP = KASSA_IP;
        $this->PORT = KASSA_PORT;
        $this->PIN = KASSA_PIN;
        $this->URL = 'http://'. $this->IP .':'. $this->PORT;
    }

    public function authCashier(): string {
        $ch = curl_init($this->URL .'/login?pin='.$this->PIN);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);

        $response = curl_exec($ch);

        if ($response === false) {
            $errno = curl_errno($ch);
            $error = curl_error($ch);
            curl_close($ch);
            throw new KassaException("Ошибка подключения терминала! Код ошибки: $errno. Ошибка: $error. Не удалось установить соединение с терминалом, убедитесь что, терминал включен и подключен к единой локальной сети.");
        }

        $data = json_decode($response, true);
        curl_close($ch);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new KassaException("Ошибка декодирования JSON от терминала: " . json_last_error_msg());
        }

        if (isset($data['error']) && $data['error']) {
            $code = $data['error']['code'] ?? 'N/A';
            $message = $data['error']['message'] ?? 'Неизвестная ошибка';
            throw new KassaException("Ошибка подключения терминала! Код ошибки: $code. Ошибка: $message");
        }

        if (!isset($data['sessionId'])) {
            throw new KassaException("Ответ терминала не содержит sessionId.");
        }

        return $data['sessionId'];
    }

    public function saleRegister(array $saleData) {
        $sessionID = $this->authCashier();

        $headers = [
            "sessionId: $sessionID",
            "Content-Type: application/json"
        ];

        $ch = curl_init($this->URL .'/registerSale');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($saleData));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);

        if ($response === false) {
            $errno = curl_errno($ch);
            $error = curl_error($ch);
            curl_close($ch);
            throw new KassaException("Ошибка при регистрации продажи! Код ошибки: $errno. Ошибка: $error. Не удалось установить соединение с терминалом.");
        }

        $data = json_decode($response, true);
        curl_close($ch);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new KassaException("Ошибка декодирования JSON от терминала при регистрации продажи: " . json_last_error_msg());
        }

        if (isset($data['error']) && $data['error']) {
            $code = $data['error']['code'] ?? 'N/A';
            $message = $data['error']['message'] ?? 'Неизвестная ошибка';
            throw new KassaException("Ошибка оплаты! Код ошибки: $code. Ошибка: $message");
        } else {
            return ['success' => true, 'fee' => $data['cardCheck']['fee'] ?? 0];
        }
    }

    public function index() {
        $saleData = [
            "currency" => "BYN",
            "items" => [
                [
                "discount" => 0,
                "info" => [
                    "amount" => 1,
                    "id" => 0,
                    "productName" => "Услуга 1",
                    "quantity" => 1,
                    "type" => 3
                ],
                "sum" => 1
                ]
            ],
            "paymentMethods" => [
                "CASH" => 1
            ]
        ];

        echo $this->saleRegister($saleData);
        
    }
}