<?php
namespace App\Classes;

class SMS
{
    private string $apiUrl = 'http://api.rocketsms.by/json/send';
    private string $username;
    private string $password;
    
    public function __construct(array $smsConfig) {
        if (empty($smsConfig['rocket_user'])) {
            throw new \InvalidArgumentException('SMS Username cannot be empty');
        }
        if (empty($smsConfig['rocket_password'])) {
            throw new \InvalidArgumentException('SMS Password cannot be empty');
        }
        
        $this->username = $smsConfig['rocket_user'];
        $this->password = $smsConfig['rocket_password'];
    }

    public function send(string $phone, string $message): string {
        $curl = curl_init();
        
        $postFields = http_build_query([
            'username' => $this->username,
            'password' => $this->password,
            'phone' => $phone,
            'text' => $message
        ]);
        
        curl_setopt_array($curl, [
            CURLOPT_URL => $this->apiUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $postFields,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/x-www-form-urlencoded'
            ]
        ]);
        
        $response = curl_exec($curl);
        $result = json_decode($response, true);
        curl_close($curl);
        
        if ($result && isset($result['id'])) {
            return "Message has been sent. MessageID=" . $result['id'];
        } 
        
        if ($result && isset($result['error'])) {
            return "Error occurred while sending message. ErrorID=" . $result['error'];
        }
        
        return "Service error";
    }
}