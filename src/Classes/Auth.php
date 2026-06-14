<?php
namespace App\Classes;

class Auth
{
    private \mysqli $db;
    private int $maxAttempts;
    private int $lockoutTime;
    private int $tokenLifetime;
    private int $sessionLifetime;

    public function __construct(Database $database, array $securityConfig, array $sessionConfig) {
        $this->db = $database->getConnection();
        $this->maxAttempts = $securityConfig['max_login_attempts'];
        $this->lockoutTime = $securityConfig['login_lockout_time'];
        $this->tokenLifetime = $securityConfig['csrf_token_lifetime'];
        $this->sessionLifetime = $sessionConfig['lifetime'];

        ini_set('session.gc_maxlifetime', (string)$this->sessionLifetime);
        ini_set('session.cookie_lifetime', (string)$this->sessionLifetime);
        session_set_cookie_params([
            'lifetime' => $this->sessionLifetime,
            'path' => $sessionConfig['path'],
            'domain' => $sessionConfig['domain'],
            'secure' => $sessionConfig['secure'],
            'httponly' => $sessionConfig['httponly'],
            'samesite' => $sessionConfig['samesite']
        ]);
    }

    public function login(string $username, string $password, bool $remember = false): bool {
        if ($this->isBruteForce($username)) {
            throw new \RuntimeException('Слишком много попыток авторизации, попробуйте позже или обратитесь к администратору.');
        }

        $user = $this->getUser($username);

        if (!$user || !$this->verifyPassword($password, $user['password'])) {
            $this->logFailedAttempt($username);
            return false;
        }

        if (!$user['is_active']) {
            throw new \RuntimeException('Ваш аккаун деактивирован, обратитесь к администратору.');
        }

        $this->resetAttempts($username);
        $this->startSession($user);

        if ($remember) {
            $rememberToken = bin2hex(random_bytes(32));
            $expiry = time() + $this->sessionLifetime;
            
            setcookie(
                'remember_me',
                $rememberToken,
                $expiry,
                '/',
                '',
                true,
                true
            );
            
            $this->db->query(
                "UPDATE users SET 
                remember_token = '$rememberToken', 
                token_expiry = FROM_UNIXTIME($expiry) 
                WHERE id = {$user['id']}"
            );
        }

        return true;
    }

    private function getUser(string $username): ?array {
        $stmt = $this->db->prepare(
            "SELECT u.id, u.username, u.full_name, ug.code AS role, u.password, u.is_active
            FROM users u LEFT JOIN user_groups ug ON u.role = ug.id
            WHERE u.username = ?"
        );
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_assoc();
    }

    private function verifyPassword(string $password, string $hash): bool {
        return password_verify($password, $hash);
    }

    private function isBruteForce(string $username): bool {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) as attempts 
             FROM login_attempts 
             WHERE user_id = (SELECT id FROM users WHERE username = ?) 
               AND successful = 0 
               AND attempt_time > DATE_SUB(NOW(), INTERVAL ? SECOND)"
        );
        $stmt->bind_param("si", $username, $this->lockoutTime);
        $stmt->execute();
        $result = $stmt->get_result();
        $data = $result->fetch_assoc();
        return $data['attempts'] >= $this->maxAttempts;
    }

    private function logFailedAttempt(string $username): void {
        $user = $this->getUser($username);
        $userId = $user ? $user['id'] : null;

        $stmt = $this->db->prepare(
            "INSERT INTO login_attempts 
             (user_id, ip_address, user_agent, successful) 
             VALUES (?, ?, ?, 0)"
        );
        $stmt->bind_param(
            "iss", 
            $userId,
            $_SERVER['REMOTE_ADDR'],
            $_SERVER['HTTP_USER_AGENT']
        );
        $stmt->execute();

        if ($user) {
            $this->db->query(
                "UPDATE users 
                 SET login_attempts = login_attempts + 1, 
                     last_failed_login = NOW() 
                 WHERE id = {$user['id']}"
            );
        }
    }

    private function resetAttempts(string $username): void {
        $this->db->query(
            "UPDATE users 
             SET login_attempts = 0 
             WHERE username = '{$this->db->real_escape_string($username)}'"
        );
    }

    private function setLastLogin(string $id): void {
        $this->db->query(
            "UPDATE users 
             SET last_login = NOW() 
             WHERE id = '{$id}'"
        );
    }

    public function generateCsrfToken(): string {
        $this->db->query("DELETE FROM csrf_tokens WHERE expires_at < NOW()");

        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', time() + $this->tokenLifetime);

        $_SESSION['csrf_token'] = $token;
        $_SESSION['csrf_token_expires'] = $expires;

        if ($this->isLoggedIn()) {
            $userId = $_SESSION['userID'];
            $_SESSION['LAST_ACTIVITY'] = time();
            $this->db->query(
                "INSERT INTO csrf_tokens (user_id, token, expires_at)
                VALUES ($userId, '$token', '$expires')
                ON DUPLICATE KEY UPDATE
                    token = VALUES(token),
                    expires_at = VALUES(expires_at)"
            );
        }

        return $token;
    }

    private function cleanExpiredTokens(): void {
        $this->db->query("DELETE FROM csrf_tokens WHERE expires_at < NOW()");
    }

    public function validateCsrfToken(string $token): bool {

        if (!isset($_SESSION['csrf_token'])) {
            error_log("CSRF Fail: No token in session");
            return false;
        }

        if (!hash_equals($_SESSION['csrf_token'], $token)) {
            error_log("CSRF Fail: Token mismatch");
            error_log("Session: " . $_SESSION['csrf_token']);
            error_log("Form: " . $token);
            return false;
        }

        if (isset($_SESSION['csrf_token_expires']) &&
            time() > strtotime($_SESSION['csrf_token_expires'])) {
            error_log("CSRF Fail: Token expired");
            return false;
        }

        if ($this->isLoggedIn()) {
            $userId = $_SESSION['userID'];
            $stmt = $this->db->prepare(
                "SELECT token FROM csrf_tokens 
                WHERE user_id = ? AND token = ? AND expires_at > NOW()"
            );
            $stmt->bind_param("is", $userId, $token);
            $stmt->execute();
            
            if (!$stmt->get_result()->fetch_assoc()) {
                error_log("CSRF Fail: DB token not found or expired");
                return false;
            }
        }

        error_log("CSRF Validation Success");
        return true;
    }

    public function isLoggedIn(): bool {
        if (!isset($_SESSION['userID'])) {
            return false;
        }
        
        if ($_SESSION['ip_address'] !== $_SERVER['REMOTE_ADDR'] ||
            $_SESSION['user_agent'] !== $_SERVER['HTTP_USER_AGENT']) {
            $this->logout();
            return false;
        }
        
        if (isset($_SESSION['last_activity']) &&
            (time() - $_SESSION['last_activity'] > $this->sessionLifetime)) {
            $this->logout();
            return false;
        }
        
        $_SESSION['last_activity'] = time();
        return true;
    }

    public function regenerateSession(): void {
        session_regenerate_id(true);
        $_SESSION['created'] = time();
    }

    private function startSession(array $user): void {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_unset();
            session_destroy();
        }

        session_start([
            'cookie_lifetime' => $this->sessionLifetime,
        ]);

        $_SESSION = [
            'userID' => $user['id'],
            'username' => $user['username'],
            'fullName' => $user['full_name'],
            'role' => $user['role'],
            'ip_address' => $_SERVER['REMOTE_ADDR'],
            'user_agent' => $_SERVER['HTTP_USER_AGENT'],
            'LAST_ACTIVITY' => time(),
            'created' => time(),
        ];

        $this->setLastLogin($user['id']);
    }

    public function logout(): void {
        session_unset();
        session_destroy();
    }
}