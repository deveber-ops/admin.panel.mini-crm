<?php
namespace App\Classes;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use Twig\Environment;

class Mailer
{
    private PHPMailer $mail;
    private Environment $twig;
    private array $smtpConfig;
    private static ?self $instance = null;

    public function __construct(Environment $twig, array $smtpConfig) {
        $this->mail = new PHPMailer(true);
        $this->twig = $twig;
        $this->smtpConfig = $smtpConfig;
        $this->configure();
    }

    public static function getInstance(Environment $twig, array $smtpConfig): self {
        if (self::$instance === null) {
            self::$instance = new self($twig, $smtpConfig);
        }
        return self::$instance;
    }

    private function configure(): void {
        $this->mail->isSMTP();
        $this->mail->Host       = $this->smtpConfig['host'];
        $this->mail->SMTPAuth   = true;
        $this->mail->Username   = $this->smtpConfig['user'];
        $this->mail->Password   = $this->smtpConfig['pass'];
        $this->mail->SMTPSecure = $this->smtpConfig['encryption'];
        $this->mail->Port       = $this->smtpConfig['port'];
        $this->mail->CharSet    = 'UTF-8';
    }

    public function send(
        string $to,
        string $subject,
        string $body,
        array $attachments = [],
        ?string $from = null
    ): bool {
        try {
            $this->mail->clearAddresses();
            $this->mail->setFrom($from ?? $this->smtpConfig['from_email'], $this->smtpConfig['from_name']);
            $this->mail->addAddress($to);

            foreach ($attachments as $file) {
                $this->mail->addAttachment($file['path'], $file['name']);
            }

            $this->mail->isHTML(true);
            $this->mail->Subject = $subject;
            $this->mail->Body    = $body;

            return $this->mail->send();
        } catch (Exception $e) {
            error_log('Mailer Error: ' . $this->mail->ErrorInfo);
            return false;
        }
    }

    public function sendWelcomeEmail($email, $name, $username, $password) {
        $html = $this->twig->render('notifications/newUser.twig', [
            'name' => $name,
            'username' => $username,
            'password' => $password,
            'BASE_URL' => BASE_URL
        ]);
        
        return $this->send(
            $email,
            'Добро пожаловать в PROTEGO!',
            $html
        );
    }

    public function resetPasswordEmail($email, $name, $username, $password) {
        $html = $this->twig->render('notifications/resetPassword.twig', [
            'name' => $name,
            'username' => $username,
            'password' => $password,
            'BASE_URL' => BASE_URL
        ]);
        
        return $this->send(
            $email,
            'Восстановление доступа PROTEGO.',
            $html
        );
    }
}