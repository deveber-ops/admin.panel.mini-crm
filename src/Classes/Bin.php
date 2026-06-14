<?php
namespace App\Classes;

use App\Classes\Exceptions\BinException;

class Bin {
    private string $URL;

    private static ?self $instance = null;

    public function __construct(array $binConfig) {
        $this->URL = $binConfig['url'] ?? 'https://lookup.binlist.net/';
    }

    public static function getInstance(array $binConfig): self {
        if (self::$instance === null) {
            self::$instance = new self($binConfig);
        }
        return self::$instance;
    }

    public function checkBinInfo($kardNum): array {
        $headers = [
            "Accept-Version: 3",
        ];

        $ch = curl_init($this->URL . $kardNum);
        curl_setopt($ch, CURLOPT_HTTPGET, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);

        $response = curl_exec($ch);

        if ($response === false) {
            $errno = curl_errno($ch);
            $error = curl_error($ch);
            curl_close($ch);
            throw new BinException("Ошибка cURL при запросе BIN-информации: Код $errno, Сообщение: $error", 5006);
        }

        $data = json_decode($response, true);
        curl_close($ch);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new BinException("Ошибка декодирования JSON от Binlist API: " . json_last_error_msg(), 5006);
        }

        $country = $data['country']['alpha2'] ?? 'N/A';
        $bank = $data['bank']['name'] ?? 'N/A';

        return ['country' => $country, 'bank' => $bank];
    }
}