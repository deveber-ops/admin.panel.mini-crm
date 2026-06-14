<?php
namespace App\Classes;

use App\Classes\Exceptions\KassaException;

class Kassa {
    private Database $db;

    private string $IP;
    private string $PORT;
    private string $PIN;
    private string $URL;

    private static ?self $instance = null;
    
    public function __construct(Database $db, array $kassaConfig, string $kassaIp) {
        $this->db = $db;
        $this->IP = $kassaIp; // IP теперь передается как аргумент
        $this->PORT = $kassaConfig['port'];
        $this->PIN = $this->db->getKassaPin(); // PIN по-прежнему извлекается из БД
        $this->URL = 'http://'. $this->IP .':'. $this->PORT;
    }

    public static function getInstance(Database $db, array $kassaConfig, string $kassaIp): self {
        if (self::$instance === null) {
            self::$instance = new self($db, $kassaConfig, $kassaIp);
        }
        return self::$instance;
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
            throw new KassaException("Ошибка подключения терминала! Код ошибки: $errno. Ошибка: $error. Не удалось установить соединение с терминалом, убедитесь что, терминал включен и подключен к единой локальной сети.", 5005);
        }

        $data = json_decode($response, true);
        curl_close($ch);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new KassaException("Ошибка декодирования JSON от терминала: " . json_last_error_msg(), 5005);
        }

        if (isset($data['error']) && $data['error']) {
            $code = $data['error']['code'] ?? 'N/A';
            $message = $data['error']['message'] ?? 'Неизвестная ошибка';
            throw new KassaException("Ошибка подключения терминала! Код ошибки: $code. Ошибка: $message", 5005);
        }

        if (!isset($data['sessionId'])) {
            throw new KassaException("Ответ терминала не содержит sessionId.", 5005);
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
            throw new KassaException("Ошибка при регистрации продажи! Код ошибки: $errno. Ошибка: $error. Не удалось установить соединение с терминалом.", 5005);
        }

        $data = json_decode($response, true);
        curl_close($ch);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new KassaException("Ошибка декодирования JSON от терминала при регистрации продажи: " . json_last_error_msg(), 5005);
        }

        if (isset($data['error']) && $data['error']) {
            $code = $data['error']['code'] ?? 'N/A';
            $message = $data['error']['message'] ?? 'Неизвестная ошибка';
            throw new KassaException("Ошибка оплаты! Код ошибки: $code. Ошибка: $message", 5005);
        } else {
            return ['success' => true, 'fee' => $data['cardCheck']['fee'] ?? 0, 'kardNum' => $data['cardCheck']['cardNum'] ?? null];
        }
    }

    public function xReport() {
        $sessionID = $this->authCashier();

        $headers = [
            "sessionId: $sessionID",
            "Content-Type: application/json"
        ];

        $ch = curl_init($this->URL .'/xReport');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);

        if ($response === false) {
            $errno = curl_errno($ch);
            $error = curl_error($ch);
            curl_close($ch);
            throw new KassaException("Ошибка формирования x-отчета! Код ошибки: $errno. Ошибка: $error. Не удалось установить соединение с терминалом.", 5005);
        }

        $data = json_decode($response, true);
        curl_close($ch);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new KassaException("Ошибка декодирования JSON от терминала при формировании x-отчета: " . json_last_error_msg(), 5005);
        }

        if (isset($data['error']) && $data['error']) {
            $code = $data['error']['code'] ?? 'N/A';
            $message = $data['error']['message'] ?? 'Неизвестная ошибка';
            throw new KassaException("Ошибка формирования x-отчета! Код ошибки: $code. Ошибка: $message", 5005);
        } else {
            return true;
        }
    }

    public function closeShift() {
        $sessionID = $this->authCashier();

        $headers = [
            "sessionId: $sessionID",
            "Content-Type: application/json"
        ];

        $ch = curl_init($this->URL .'/closeShift');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);

        if ($response === false) {
            $errno = curl_errno($ch);
            $error = curl_error($ch);
            curl_close($ch);
            throw new KassaException("Ошибка закрытия смены! Код ошибки: $errno. Ошибка: $error. Не удалось установить соединение с терминалом.", 5005);
        }

        $data = json_decode($response, true);
        curl_close($ch);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new KassaException("Ошибка декодирования JSON от терминала при закрытии смены: " . json_last_error_msg(), 5005);
        }

        if (isset($data['error']) && $data['error']) {
            $code = $data['error']['code'] ?? 'N/A';
            $message = $data['error']['message'] ?? 'Неизвестная ошибка';
            throw new KassaException("Ошибка закрытия смены! Код ошибки: $code. Ошибка: $message", 5005);
        } else {
            return true;
        }
    }

    public function openShift(int $userID, string $userFullName) {
        $sessionID = $this->authCashier();

        $headers = [
            "sessionId: $sessionID",
            "Content-Type: application/json"
        ];

        $cashier = [
            'cashier' => [
                'id' => $userID,
                'name' => $userFullName
            ]
        ];

        $ch = curl_init($this->URL .'/openShift');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($cashier));

        $response = curl_exec($ch);

        if ($response === false) {
            $errno = curl_errno($ch);
            $error = curl_error($ch);
            curl_close($ch);
            throw new KassaException("Ошибка открытия смены! Код ошибки: $errno. Ошибка: $error. Не удалось установить соединение с терминалом.", 5005);
        }

        $data = json_decode($response, true);
        curl_close($ch);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new KassaException("Ошибка декодирования JSON от терминала при открытии смены: " . json_last_error_msg(), 5005);
        }

        if (isset($data['error']) && $data['error']) {
            $code = $data['error']['code'] ?? 'N/A';
            $message = $data['error']['message'] ?? 'Неизвестная ошибка';
            throw new KassaException("Ошибка открытия смены! Код ошибки: $code. Ошибка: $message", 5005);
        } else {
            return true;
        }
    }

    public function shiftInfo() {
        $sessionID = $this->authCashier();

        $headers = [
            "sessionId: $sessionID",
            "Content-Type: application/json"
        ];

        $ch = curl_init($this->URL .'/shiftInfo');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);

        if ($response === false) {
            $errno = curl_errno($ch);
            $error = curl_error($ch);
            curl_close($ch);
            throw new KassaException("Ошибка получения информации о смене! Код ошибки: $errno. Ошибка: $error. Не удалось установить соединение с терминалом.", 5005);
        }

        $data = json_decode($response, true);
        curl_close($ch);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new KassaException("Ошибка декодирования JSON от терминала при получении информации о смене: " . json_last_error_msg(), 5005);
        }

        if (isset($data['error']) && $data['error']) {
            $code = $data['error']['code'] ?? 'N/A';
            $message = $data['error']['message'] ?? 'Неизвестная ошибка';
            throw new KassaException("Ошибка получения информации о смене! Код ошибки: $code. Ошибка: $message", 5005);
        } else {
            $this->db->query("UPDATE kassa SET shift = ? WHERE id = 1", [$data['open'] ?? 0]);
            return ['connection' => true, 'open' => $data['open'] ?? 0];
        }
    }
}