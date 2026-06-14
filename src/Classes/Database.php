<?php
namespace App\Classes;

use App\Classes\Exceptions\DatabaseException;

class Database
{
    private \mysqli $connection;
    private static ?self $instance = null;
    private array $dbConfig;

    public function __construct(array $dbConfig) {
        $this->dbConfig = $dbConfig;
        try {
            $this->connection = new \mysqli(
                $this->dbConfig['host'],
                $this->dbConfig['user'],
                $this->dbConfig['pass'],
                $this->dbConfig['name']
            );

            if ($this->connection->connect_error) {
                throw new DatabaseException(
                    "MySQL connection failed: " . $this->connection->connect_error
                );
            }

            $this->connection->set_charset($this->dbConfig['charset']);
        } catch (\Exception $e) {
            throw new DatabaseException("Database error: " . $e->getMessage());
        }
    }

    public static function getInstance(array $dbConfig): self {
        if (self::$instance === null) {
            self::$instance = new self($dbConfig);
        }
        return self::$instance;
    }

    public function getConnection(): \mysqli {
        return $this->connection;
    }

    public function query(string $sql, array $params = []): \mysqli_result|bool {
        try {
            $stmt = $this->connection->prepare($sql);
            if ($stmt === false) {
                throw new DatabaseException(
                    "Prepare failed: " . $this->connection->error,
                    $this->connection->errno,
                    $this->connection->sqlstate
                );
            }

            if (!empty($params)) {
                $types = '';
                $bindParams = [];
                foreach ($params as $param) {
                    if (is_int($param)) {
                        $types .= 'i';
                    } elseif (is_float($param)) {
                        $types .= 'd';
                    } else {
                        $types .= 's';
                    }
                    $bindParams[] = &$param;
                }
                array_unshift($bindParams, $types);
                call_user_func_array([$stmt, 'bind_param'], $bindParams);
            }

            if (!$stmt->execute()) {
                throw new DatabaseException(
                    "Execute failed: " . $stmt->error,
                    $stmt->errno,
                    $stmt->sqlstate
                );
            }

            return $stmt->get_result() ?: true;
        } catch (\Exception $e) {
            if (!$e instanceof DatabaseException) {
                throw new DatabaseException($e->getMessage(), $e->getCode());
            }
            throw $e;
        }
    }

    function validatePhoneNumber($phone) {
        $cleaned = preg_replace('/[^0-9]/', '', $phone);
        
        if (strlen($cleaned) !== 12) {
            return [
                'success' => false,
                'message' => 'Номер телефона должен содержать 12 цифр (включая код страны 375)'
            ];
        }
        
        $operator_codes = ['17', '25', '29', '33', '44'];
        $operator_code = substr($cleaned, 3, 2);
        
        if (!in_array($operator_code, $operator_codes)) {
            return [
                'success' => false,
                'message' => 'Введите корректный код оператора например: 17, 25, 29, 33, 44'
            ];
        }

        return [
            'success' => true,
            'phone' => $cleaned
        ];
    }

    public function getUsers(?int $userID = null, ?int $roleID = null) {
        $sql = "SELECT u.id, u.role AS role_id, ug.name AS role_name, ug.code AS role_code, u.username, u.full_name, u.post, u.email, u.phone, u.is_active, u.last_login, u.created_at, u.is_system
                FROM users u
                LEFT JOIN user_groups ug ON u.role = ug.id";
        
        $params = [];
        $conditions = [];

        if ($userID !== null) {
            $conditions[] = "u.id = ?";
            $params[] = $userID;
        }

        if ($roleID !== null) {
            $conditions[] = "u.role = ?";
            $params[] = $roleID;
        }

        if (!empty($conditions)) {
            $sql .= " WHERE " . implode(" AND ", $conditions);
        }

        $sql .= " ORDER BY ug.name ASC";

        return $this->fetchAll($sql, $params);
    }

    public function insertUser($role, $post, $full_name, $email, $userPhone) {
        $response = [];

        try {
            $newPassword = bin2hex(random_bytes(5));
            $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);
            $newUserName = $this->generateUsername($full_name);
            $phone = preg_replace('/[^0-9]/', '', $userPhone);
            $pass_is_rand = 1;

            $sql = "INSERT INTO users (role, username, post, password, pass_is_rand, full_name, email, phone) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $params = [$role, $newUserName, $post, $hashedPassword, $pass_is_rand, $full_name, $email, $phone];
            $this->query($sql, $params);

            $response['success'] = true;
            $response['message'] = 'Пользователь успешно зарегистрирован, информация с именем пользователя и паролем отправлена на ' . $email;
            $response['username'] = $newUserName;
            $response['password'] = $newPassword;
        } catch (\Exception $e) {
            $errorCode = $e->getCode();
            $response['success'] = false;

            if ($errorCode == 1062 && strpos($e->getMessage(), "users.email") !== false) {
                $response['message'] = 'Пользователь с таким E-MAIL уже существует.';
            } else if ($errorCode == 1062 && strpos($e->getMessage(), "users.phone") !== false) {
                $response['message'] = 'Пользователь с таким номером телефона уже существует.';
            } else if ($errorCode == 1062 && strpos($e->getMessage(), "users.full_name") !== false) {
                $response['message'] = 'Пользователь с таким ФИО уже существует.';
            } else {
                $response['message'] = 'Системная ошибка, обратитесь к администратору! Код ошибки: ' . $errorCode;
            }
        }

        return $response;
    }

    public function updateUser(int $id, $role, $post, $full_name, $email, $userPhone) {
        $response = [];

        try {
            $phone = preg_replace('/[^0-9]/', '', $userPhone);
            $sql = "UPDATE users SET role = ?, post = ?, full_name = ?, email = ?, phone = ? WHERE id = ?";
            $this->query($sql, [$role, $post, $full_name, $email, $phone, $id]);
            $response['success'] = true;
            $response['message'] = 'Данные пользователя сохранены.';
        } catch (\Exception $e) {
            $errorCode = $e->getCode();
            $errorMessage = $e->getMessage();
            $response['success'] = false;
            $response['message'] = 'Системная ошибка, обратитесь к администратору! Код ошибки: ' . $errorCode . ', Ошибка: ' . $errorMessage;
        }
        return $response;
    }

    public function removeUser(int $id, string $name) {
        $isSystem = $this->getUsers((int)$id)[0]['is_system'];
        $response = [];

        if ($isSystem == 1) {
            $response['success'] = false;
            $response['message'] = 'Невозможно удалить пользователя <strong>' . $name . '</strong>, пользователь является системным.';
        } else {
            try {
                $sql = "DELETE FROM users WHERE id = ?";
                $this->query($sql, [$id]);
                $response['success'] = true;
                $response['message'] = 'Пользователь <strong>' . $name . '</strong>, удален.';
            } catch (\Exception $e) {
                $errorCode = $e->getCode();
                $errorMessage = $e->getMessage();
                $response['success'] = false;
                $response['message'] = 'Системная ошибка, обратитесь к администратору! Код ошибки: ' . $errorCode . ', Ошибка: ' . $errorMessage;
            }
        }
        return $response;
    }

    public function resetPassword(int $id) {
        $user = $this->getUsers((int)$id)[0];
        $userName = $user['username'];
        $email = $user['email'];
        $full_name = $user['full_name'];
        $newPassword = bin2hex(random_bytes(5));
        $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);
        $pass_is_rand = 1;

        $fullName = explode(' ', trim($full_name));
        $name = $fullName[1];

        $response = [];

        try {
            $sql = "UPDATE users SET password = ?, pass_is_rand = ? WHERE id = ?";
            $this->query($sql, [$hashedPassword, $pass_is_rand, $id]);

            $response['success'] = true;
            $response['message'] = 'Пароль пользователя <strong>' . $full_name . '</strong> сброшен, новый пароль отправлен на <strong>' . $email . '</strong>';
            $response['email'] = $email;
            $response['name'] = $name;
            $response['username'] = $userName;
            $response['password'] = $newPassword;

        } catch (\Exception $e) {
            $errorCode = $e->getCode();
            $errorMessage = $e->getMessage();
            $response['success'] = false;
            $response['message'] = 'Системная ошибка, обратитесь к администратору! Код ошибки: ' . $errorCode . ', Ошибка: ' . $errorMessage;
        }

        return $response;
    }

    function blockUser(int $id) {
        $user = $this->getUsers((int)$id)[0];
        $full_name = $user['full_name'];

        try {
            $sql = "UPDATE users SET is_active = ? WHERE id = ?";
            $this->query($sql, [0, $id]);
            $response['success'] = true;
            $response['message'] = 'Пользователь <strong>' . $full_name . '</strong> заблокирован.';

        } catch (\Exception $e) {
            $errorCode = $e->getCode();
            $errorMessage = $e->getMessage();
            $response['success'] = false;
            $response['message'] = 'Системная ошибка, обратитесь к администратору! Код ошибки: ' . $errorCode . ', Ошибка: ' . $errorMessage;
        }

        return $response;
    }

    function unblockUser(int $id) {
        $user = $this->getUsers((int)$id)[0];
        $full_name = $user['full_name'];

        try {
            $sql = "UPDATE users SET is_active = ? WHERE id = ?";
            $this->query($sql, [1, $id]);
            $response['success'] = true;
            $response['message'] = 'Пользователь <strong>' . $full_name . '</strong> разблокирован.';

        } catch (\Exception $e) {
            $errorCode = $e->getCode();
            $errorMessage = $e->getMessage();
            $response['success'] = false;
            $response['message'] = 'Системная ошибка, обратитесь к администратору! Код ошибки: ' . $errorCode . ', Ошибка: ' . $errorMessage;
        }

        return $response;
    }

    public function generateUsername($fullName): string {
        $transliteratedFullName = transliterator_transliterate('Russian-Latin/BGN', $fullName);
        $parts = explode(' ', trim($transliteratedFullName));

        if (count($parts) < 2) {
            return '';
        }

        $lastName = $parts[0];

        $firstNameInitial = mb_substr($parts[1], 0, 1, 'UTF-8');
        
        return strtolower($firstNameInitial . '.' . $lastName);
    }

    public function getClientGroups(int $groupID = null) {
        $sql = "SELECT 
                    cg.id, 
                    cg.name, 
                    cg.discount, 
                    cg.is_system,
                    COUNT(c.id) AS clients_count
                FROM client_groups cg
                LEFT JOIN clients c ON cg.id = c.`group`";
        
        $params = [];
        $conditions = [];

        if ($groupID !== null) {
            $conditions[] = "cg.id = ?";
            $params[] = $groupID;
        }

        if (!empty($conditions)) {
            $sql .= " WHERE " . implode(" AND ", $conditions);
        }

        $sql .= " GROUP BY cg.id, cg.name, cg.discount, cg.is_system";
        $sql .= " ORDER BY cg.name ASC";

        return $this->fetchAll($sql, $params);
    }

    public function getClients(int $phone = null, $legal = null, $group = null) {
        $sql = "SELECT 
            c.id,
            c.reg_date,
            c.name,
            c.phone,
            c.valid,
            c.type,
            c.`group` AS group_id,
            cg.name AS group_name,
            c.discount,
            lf.id AS legal_form_id,
            lf.name AS legal_form_name,
            lf.code AS legal_form_code,
            c.legal_name,
            c.legal_unp,
            c.legal_address,
            c.legal_email,
            c.legal_phone,
            c.legal_bank,
            c.legal_check,
            c.legal_bic,
            c.legal_bank_address,
            c.legal_post,
            c.legal_signatory,
            c.legal_document,
            c.last_sale_date,
            c.total_sales
        FROM clients c
        LEFT JOIN client_groups cg ON c.`group` = cg.id
        LEFT JOIN legal_forms lf ON c.legal_form = lf.id";
        
        $params = [];
        $conditions = [];

        if ($group !== null) {
            $conditions[] = "c.`group` = ?";
            $params[] = $group;
        }

        if ($phone !== null) {
            $phonePattern = '%' . $phone . '%';
            $conditions[] = "(c.phone LIKE ? OR c.legal_phone LIKE ?)";
            $params[] = $phonePattern;
            $params[] = $phonePattern;
        }

        if ($legal !== null) {
            $legalPattern = '%' . $legal . '%';
            if (ctype_digit($legal)) {
                $conditions[] = "c.legal_unp LIKE ?";
                $params[] = $legalPattern;
            } else {
                $conditions[] = "c.legal_name LIKE ?";
                $params[] = $legalPattern;
            }
        }

        if (!empty($conditions)) {
            $sql .= " WHERE " . implode(" AND ", $conditions);
        }

        return $this->fetchAll($sql, $params);
    }

    public function getLegalForms(int $groupID = null) {
        $sql = "SELECT id, name, code FROM legal_forms";
        
        $params = [];
        $conditions = [];

        if ($groupID !== null) {
            $conditions[] = "id = ?";
            $params[] = $groupID;
        }

        if (!empty($conditions)) {
            $sql .= " WHERE " . implode(" AND ", $conditions);
        }

        $sql .= " ORDER BY name ASC";

        return $this->fetchAll($sql, $params);
    }

    public function insertClientGroup($name, float $discount) {
        $response = [];
        try {
            $sql = "INSERT INTO client_groups (name, discount) VALUES (?, ?)";
            $params = [$name, $discount];
            $this->query($sql, $params);
            
            $response['success'] = true;
            $response['message'] = 'Группа клиентов <strong>' . $name . '</strong>, успешно добавлена.';
        } catch (\Exception $e) {
            $errorCode = $e->getCode();
            $errorMessage = $e->getMessage();
            $response['success'] = false;

            if ($errorCode == 1062 && strpos($e->getMessage(), "client_groups.name") !== false) {
                $response['message'] = 'Группа клиентов с названием <strong>' . $name . '</strong> уже существует.';
            } else {
                $response['message'] = 'Системная ошибка, обратитесь к администратору! Код ошибки: <strong>' . $errorCode . '</strong>, Ошибка: ' . $errorMessage;
            }
        }
        return $response;
    }

    public function insertClient($name, $phone, $type, $group, int $discount, $legal_form = null, $legal_name = null, $legal_unp = null, $legal_address = null, $legal_email = null, $legal_phone = null, $legal_bank = null, $legal_check = null, $legal_bic = null, $legal_bank_address = null, $legal_post = null, $legal_signatory = null, $legal_document = null, $json = null) {
        $response = [];

        $legal_form = empty($legal_form) ? null : $legal_form;
        $legal_name = empty($legal_name) ? null : $legal_name;
        $legal_unp = empty($legal_unp) ? null : $legal_unp;
        $legal_address = empty($legal_address) ? null : $legal_address;
        $legal_email = empty($legal_email) ? null : $legal_email;
        $legal_phone = empty($legal_phone) ? null : $legal_phone;
        $legal_bank = empty($legal_bank) ? null : $legal_bank;
        $legal_check = empty($legal_check) ? null : $legal_check;
        $legal_bic = empty($legal_bic) ? null : $legal_bic;
        $legal_bank_address = empty($legal_bank_address) ? null : $legal_bank_address;
        $legal_post = empty($legal_post) ? null : $legal_post;
        $legal_signatory = empty($legal_signatory) ? null : $legal_signatory;
        $legal_document = empty($legal_document) ? null : $legal_document;

        $phoneValidate = $this->validatePhoneNumber($phone);

        if ($phoneValidate['success'] !== true) {
            $response['success'] = false;
            $response['message'] = $phoneValidate['message'];
            return $response;
        } else {
            $phone = $phoneValidate['phone'];
        }

        if ($legal_phone !== null ) {
            $phoneLegalValidate = $this->validatePhoneNumber($phone);

            if ($phoneLegalValidate['success'] !== true) {
                $response['success'] = false;
                $response['message'] = $phoneLegalValidate['message'];
                return $response;
            } else {
                $legal_phone = $phoneLegalValidate['phone'];
            }
        }

        try {
            $sql = "INSERT INTO clients (name, phone, type, `group`, discount, legal_form, legal_name, legal_unp, legal_address, legal_email, legal_phone, legal_bank, legal_check, legal_bic, legal_bank_address, legal_post, legal_signatory, legal_document) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $params = [$name, $phone, $type, $group, $discount, $legal_form, $legal_name, $legal_unp, $legal_address, $legal_email, $legal_phone, $legal_bank, $legal_check, $legal_bic, $legal_bank_address, $legal_post, $legal_signatory, $legal_document];
            $this->query($sql, $params);
            
            $response['success'] = true;
            $response['message'] = 'Клиент успешно добавлен.';
        } catch (\Exception $e) {
            $errorCode = $e->getCode();
            $errorMessage = $e->getMessage();
            $response['success'] = false;

            if ($errorCode == 1062 && strpos($e->getMessage(), "clients.phone") !== false) {
                $response['message'] = 'Клиент с таким номером телефона уже существует.';
            } else if ($errorCode == 1062 && strpos($e->getMessage(), "clients.legal_name") !== false) {
                $response['message'] = 'Клиент с таким наименованием уже существует.';
            } else if ($errorCode == 1062 && strpos($e->getMessage(), "clients.legal_unp") !== false) {
                $response['message'] = 'Клиент с таким УНП уже существует.';
            } else {
                $response['message'] = 'Системная ошибка, обратитесь к администратору! Код ошибки: <strong>' . $errorCode . '</strong>, Ошибка: ' . $errorMessage;
            }
        }

        return $response;
    }

    public function removeClient(int $id, string $name) {
        $response = [];

        try {
            $sql = "DELETE FROM clients WHERE id = ?";
            $this->query($sql, [$id]);
            $response['success'] = true;
            $response['message'] = 'Клиент <strong>' . $name . '</strong>, удален.';
        } catch (\Exception $e) {
            $errorCode = $e->getCode();
            $errorMessage = $e->getMessage();
            $response['success'] = false;
            $response['message'] = 'Системная ошибка, обратитесь к администратору! Код ошибки: ' . $errorCode . ', Ошибка: ' . $errorMessage;
        }

        return $response;
    }

    public function updateClient(int $id, $name, $phone, $type, $group, int $discount, $legal_form = null, $legal_name = null, $legal_unp = null, $legal_address = null, $legal_email = null, $legal_phone = null, $legal_bank = null, $legal_check = null, $legal_bic = null, $legal_bank_address = null, $legal_post = null, $legal_signatory = null, $legal_document = null, $json = null) {

        $legal_form = empty($legal_form) ? null : $legal_form;
        $legal_name = empty($legal_name) ? null : $legal_name;
        $legal_unp = empty($legal_unp) ? null : $legal_unp;
        $legal_address = empty($legal_address) ? null : $legal_address;
        $legal_email = empty($legal_email) ? null : $legal_email;
        $legal_phone = empty($legal_phone) ? null : $legal_phone;
        $legal_bank = empty($legal_bank) ? null : $legal_bank;
        $legal_check = empty($legal_check) ? null : $legal_check;
        $legal_bic = empty($legal_bic) ? null : $legal_bic;
        $legal_bank_address = empty($legal_bank_address) ? null : $legal_bank_address;
        $legal_post = empty($legal_post) ? null : $legal_post;
        $legal_signatory = empty($legal_signatory) ? null : $legal_signatory;
        $legal_document = empty($legal_document) ? null : $legal_document;

        $phoneValidate = $this->validatePhoneNumber($phone);

        if ($phoneValidate['success'] !== true) {
            $response['success'] = false;
            $response['message'] = $phoneValidate['message'];
            return $response;
        } else {
            $phone = $phoneValidate['phone'];
        }

        $sql = "UPDATE clients SET name = ?, phone = ?, type = ?, `group` = ?, discount = ?, legal_form = ?, legal_name = ?, legal_unp = ?, legal_address = ?, legal_email = ?, legal_phone = ?, legal_bank = ?, legal_check = ?, legal_bic = ?, legal_bank_address = ?, legal_post = ?, legal_signatory = ?, legal_document = ? WHERE id = ?";
        $params = [$name, $phone, $type, $group, $discount, $legal_form, $legal_name, $legal_unp, $legal_address, $legal_email, $legal_phone, $legal_bank, $legal_check, $legal_bic, $legal_bank_address, $legal_post, $legal_signatory, $legal_document, $id];

        $response = [];
        try {
            $this->query($sql, $params);
            
            $response['success'] = true;
            $response['message'] = 'Данные клиента <strong>#' . $id . '</strong>, успешно изменены.';
        } catch (\Exception $e) {
            $errorCode = $e->getCode();
            $errorMessage = $e->getMessage();
            $response['success'] = false;
            $response['message'] = 'Системная ошибка, обратитесь к администратору! Код ошибки: <strong>' . $errorCode . '</strong>, Ошибка: ' . $errorMessage;
        }
        return $response;
    }

    public function updateClientGroup(int $id, $name, float $discount) {
        $response = [];
        try {
            $sql = "UPDATE client_groups SET name = ?, discount = ? WHERE id = ?";
            $params = [$name, $discount, $id];
            $this->query($sql, $params);
            
            $response['success'] = true;
            $response['message'] = 'Группа клиентов <strong>' . $name . '</strong>, успешно изменена.';
        } catch (\Exception $e) {
            $errorCode = $e->getCode();
            $errorMessage = $e->getMessage();
            $response['success'] = false;

            if ($errorCode == 1062 && strpos($e->getMessage(), "client_groups.name") !== false) {
                $response['message'] = 'Группа клиентов с названием <strong>' . $name . '</strong> уже существует.';
            } else {
                $response['message'] = 'Системная ошибка, обратитесь к администратору! Код ошибки: <strong>' . $errorCode . '</strong>, Ошибка: ' . $errorMessage;
            }
        }
        return $response;
    }

    public function removeClientGroup(int $id, $name) {
        $response = [];

        $isSystem = $this->getClientGroups((int)$id)[0]['is_system'];
        $response = [];

        if ($isSystem == 1) {
            $response['success'] = false;
            $response['message'] = 'Невозможно удалить группу клиентов <strong>' . $name . '</strong>, группа является системной.';
        } else {
            try {
                $sql = "DELETE FROM client_groups WHERE id = ?";
                $this->query($sql, [$id]);
                $response['success'] = true;
                $response['message'] = 'Группа клиентов <strong>' . $name . '</strong>, успешно удалена.';
            } catch (\Exception $e) {
                $errorCode = $e->getCode();
                $errorMessage = $e->getMessage();
                $response['success'] = false;

                if ($errorCode == 1451) {
                    $response['message'] = 'Невозможно удалить группу со связанными клиентами. Сначала удалите или переназначьте всех клиентов этой группы.';
                } else {
                    $response['message'] = 'Системная ошибка, обратитесь к администратору! Код ошибки: <strong>' . $errorCode . '</strong>, Ошибка: ' . $errorMessage;
                }
            }
        }
        return $response;
    }

    public function getCategories(?int $id = null): array {
        if ($id === null) {
            $sql = "WITH numbered_categories AS (
              SELECT 
                id,
                name,
                SUBSTRING_INDEX(name, ' до', 1) AS category_name,
                CASE 
                  WHEN name REGEXP '[0-9]+\"' THEN 
                    CAST(REGEXP_SUBSTR(name, '[0-9]+') AS UNSIGNED)
                  ELSE 999999
                END AS size_num,
                MIN(CASE 
                      WHEN name REGEXP '[0-9]+\"' THEN 
                        CAST(REGEXP_SUBSTR(name, '[0-9]+') AS UNSIGNED)
                      ELSE 999999
                    END) OVER (PARTITION BY SUBSTRING_INDEX(name, ' до', 1)) AS category_min_size
              FROM service_categories
            )
            SELECT 
              id,
              name
            FROM numbered_categories
            ORDER BY
              category_min_size ASC,
              size_num ASC,
              category_name";

            return $this->fetchAll($sql);
        }
        $sql = "SELECT id, name FROM service_categories WHERE id = ? LIMIT 1";
        $result = $this->fetchOne($sql, [$id]);
        
        return $result ? [$result] : [];
    }

    public function insertCategory(string $name) {
        $response = [];
        try {
            $sql = "INSERT INTO service_categories (name) VALUES (?)";
            $this->query($sql, [$name]);
            $response['success'] = true;
            $response['message'] = 'Категория <strong>' . $name . '</strong>, успешно добавлена.';
        } catch (\Exception $e) {
            $errorMessage = $e->getMessage();
            $response['success'] = false;
            if (strpos($errorMessage, 'Duplicate entry') !== false && strpos($errorMessage, 'services.category_id') !== false) {
                $response['message'] = 'Категория <strong>' . $name . '</strong>, уже существует!';
            } else {
                $response['message'] = $errorMessage;
            }
        }
        return $response;
    }

    public function removeCategory(int $id, string $name) {
        $response = [];
        try {
            $sql = "DELETE FROM service_categories WHERE id = ?";
            $this->query($sql, [$id]);
            $response['success'] = true;
            $response['message'] = 'Категория <strong>' . $name . '</strong>, успешно удалена.';
        } catch (\Exception $e) {
            $errorCode = $e->getCode();
            $response['success'] = false;

            if ($errorCode == 1451) {
                $response['message'] = 'Невозможно удалить категорию со связанными услугами. Сначала удалите или переназначьте все услуги этой категории.';
            } else {
                $response['message'] = 'Системная ошибка, обратитесь к администратору! Код ошибки: ' . $errorCode;
            }
        }
        return $response;
    }

    public function updateCategory(int $id, string $name) {
        $response = [];
        try {
            $sql = "UPDATE service_categories SET name = ? WHERE id = ?";
            $this->query($sql, [$name, $id]);
            $response['success'] = true;
            $response['message'] = 'Наименование категории успешно изменено.';
        } catch (\Exception $e) {
            $response['success'] = false;
            $response['message'] = $e->getMessage();
        }
        return $response;
    }

    public function getServices(int $id = null, int $categoryID = null) {
        $params = [];
        $conditions = [];

        $sql = "WITH category_sorting AS (
            SELECT 
                sc.id,
                sc.name AS category_name,
                CASE 
                    WHEN sc.name REGEXP '[0-9]+\"' THEN 
                        CAST(REGEXP_SUBSTR(sc.name, '[0-9]+') AS UNSIGNED)
                    ELSE 999999
                END AS category_size
            FROM service_categories sc
        ),
        service_sales AS (
            SELECT 
                service,
                COUNT(*) as totalSales
            FROM sale_items
            GROUP BY service
        )
        SELECT 
            s.id,
            s.category_id,
            cs.category_name,
            s.name,
            s.price,
            s.completion_time,
            s.warranty_days,
            s.is_active,
            s.description,
            IFNULL(ss.totalSales, 0) as totalSales
        FROM services s
        JOIN category_sorting cs ON s.category_id = cs.id
        LEFT JOIN service_sales ss ON s.id = ss.service";

        if ($id !== null) {
            $conditions[] = "s.id = ?";
            $params[] = $id;
        }

        if ($categoryID !== null) {
            $conditions[] = "s.category_id = ?";
            $params[] = $categoryID;
        }

        if (!empty($conditions)) {
            $sql .= " WHERE " . implode(" AND ", $conditions);
        }

        $sql .= " ORDER BY cs.category_size ASC, cs.category_name ASC, s.price ASC, s.name ASC";
        
        return $this->fetchAll($sql, $params);
    }

    public function insertService(int $category_id, string $name, float $price, string $description, int $completion_time, int $warranty_days = 0) {
        $response = [];
        try {
            $sql = "INSERT INTO services (category_id, name, price, warranty_days, completion_time, description) VALUES (?, ?, ?, ?, ?, ?)";
            $params = [$category_id, $name, $price, $warranty_days, $completion_time, $description];
            $this->query($sql, $params);
            
            $response['success'] = true;
            $response['id'] = $this->lastInsertId();
            $response['message'] = 'Услуга <strong>' . $name . '</strong>, успешно добавлена.';
        } catch (\Exception $e) {
            $errorMessage = $e->getMessage();
            $response['success'] = false;
            if (strpos($errorMessage, 'Duplicate entry') !== false && strpos($errorMessage, 'services.category_id') !== false) {
                $response['message'] = 'Услуга <strong>' . $name . '</strong>, уже существует!';
            } else {
                $response['message'] = $errorMessage;
            }
        }
        return $response;
    }

    public function removeService(int $id, string $name) {
        $response = [];
        try {
            $sql = "DELETE FROM services WHERE id = ?";
            $this->query($sql, [$id]);
            $response['success'] = true;
            $response['message'] = 'Услуга <strong>' . $name . '</strong>, успешно удалена.';
        } catch (\Exception $e) {
            $response['success'] = false;
            $response['message'] = $e->getMessage();
        }
        return $response;
    }

    public function updateService(int $id, int $category_id, $name, float $price, int $warranty_days, int $completion_time, $description) {
        $response = [];
        try {
            $sql = "UPDATE services SET category_id = ?, name = ?, price = ?, warranty_days = ?, completion_time = ?, description = ? WHERE id = ?";
            $this->query($sql, [$category_id, $name, $price, $warranty_days, $completion_time, $description, $id]);
            $response['success'] = true;
            $response['message'] = 'Услуга успешно изменена.';
        } catch (\Exception $e) {
            $response['success'] = false;
            $response['message'] = $e->getMessage();
        }
        return $response;
    }

    public function getRoles(?int $role_id = null) {
        if ($role_id === null) {
            $sql = "SELECT ug.id, ug.name, ug.code, ug.description, ug.is_system, COUNT(u.id) AS user_count
            FROM user_groups ug
            LEFT JOIN users u ON ug.id = u.role
            GROUP BY ug.id, ug.code, ug.description
            ORDER BY user_count DESC;";
            return $this->fetchAll($sql);
        } else {
            $sql = "SELECT ug.id, ug.name, ug.code, ug.description, ug.is_system, COUNT(u.id) AS user_count 
            FROM user_groups ug 
            LEFT JOIN users u ON ug.id = u.role 
            WHERE ug.id = ?
            GROUP BY ug.id, ug.code, ug.description
            ORDER BY user_count DESC";
            return $this->fetchAll($sql,  [$role_id]);
        }
    }

    public function insertRole($name, $code, $description) {
        $response = [];
        try {
            $sql = "INSERT INTO user_groups (name, code, description) VALUES (?, ?, ?)";
            $params = [$name, $code, $description];
            $this->query($sql, $params);
            
            $response['success'] = true;
            $response['message'] = 'Группа пользователей <strong>' . $name . '</strong>, успешно добавлена.';
        } catch (\Exception $e) {
            $errorCode = $e->getCode();
            $response['success'] = false;

            if ($errorCode == 1062 && strpos($e->getMessage(), "user_groups.name") !== false) {
                $response['message'] = 'Группа пользователей с названием <strong>' . $name . '</strong> уже существует.';
            } else if ($errorCode == 1062 && strpos($e->getMessage(), "user_groups.code") !== false) {
                $response['message'] = 'Группа пользователей с системным кодом <strong>' . $code . '</strong> уже существует.';
            } else {
                $response['message'] = 'Системная ошибка, обратитесь к администратору! Код ошибки: <strong>' . $errorCode . '</strong>';
            }
        }
        return $response;
    }

    public function updateRole(int $id, $name, $code, $description) {
        $response = [];
        try {
            $sql = "UPDATE user_groups SET name = ?, code = ?, description = ? WHERE id = ?";
            $this->query($sql, [$name, $code, $description, $id]);
            
            $response['success'] = true;
            $response['message'] = 'Группа пользователей успешно изменена.';
        } catch (\Exception $e) {
            $errorCode = $e->getCode();
            $errorMessage = $e->getMessage();
            $response['success'] = false;

            if ($errorCode == 1062 && strpos($e->getMessage(), "user_groups.name") !== false) {
                $response['message'] = 'Группа пользователей с названием ' . $name . ' уже существует.';
            } else if ($errorCode == 1062 && strpos($e->getMessage(), "user_groups.code") !== false) {
                $response['message'] = 'Группа пользователей с системным кодом ' . $code . ' уже существует.';
            } else {
                $response['message'] = 'Системная ошибка, обратитесь к администратору! Код ошибки: <strong>' . $errorCode . '</strong>, Ошибка: ' . $errorMessage;
            }
        }
        return $response;
    }

    public function removeRole(int $id, $name) {
        $isSystem = $this->getRoles((int)$id)[0]['is_system'];
        $response = [];

        if ($isSystem == 1) {
            $response['success'] = false;
            $response['message'] = 'Невозможно удалить группу пользователей <strong>' . $name . '</strong>, группа является системной.';
        } else {
            try {
                $sql = "DELETE FROM user_groups WHERE id = ?";
                $this->query($sql, [$id]);
                $response['success'] = true;
                $response['message'] = 'Группа пользователей <strong>' . $name . '</strong>, удалена.';
            } catch (\Exception $e) {
                $errorMessage = $e->getMessage();
                $response['success'] = false;
                $response['message'] = $errorMessage;
            }
        }
        return $response;
    }

    public function getPayMethods($id = null) {
        $params = [];
        if ($id === null) {
            $sql = "SELECT * FROM payment_methods";
        } else {
            $sql = "SELECT * FROM payment_methods WHERE id = ?";
            $params[] = $id;
        }
        
        return $this->fetchAll($sql, $params);
    }

    public function getSettings() {
        return $this->fetchAll("SELECT * FROM settings")[0];
    }

    public function getClient(string $id) {
        $sql = "SELECT * FROM clients WHERE id = ?";
        return $this->fetchAll($sql, [$id]);
    }

    public function getAct(string $id) {
        $sql = "SELECT * FROM acts WHERE `unique` = ?";
        return $this->fetchAll($sql, [$id]);
    }

    public function getSales(
        ?string $startDate = null,
        ?string $endDate = null,
        ?string $clientPhone = null,
        ?string $clientType = null,
        ?int $executorId = null,
        ?int $limit = null,
        ?int $offset = null
    ): array {
        if ($startDate === null && $endDate === null) {
            $firstDayOfMonth = date('Y-m-01 00:00:00');
            $lastDayOfMonth = date('Y-m-t 23:59:59');
            $startDate = $firstDayOfMonth;
            $endDate = $lastDayOfMonth;
        }

        $sql = "SELECT 
            s.id,
            s.timestamp,
            s.amount,
            pm.id AS payment_method_id,
            pm.name AS payment_method_name,
            pm.code AS payment_method_code,
            s.cash,
            s.card,
            c.id AS client_id,
            c.name AS client_name,
            c.phone AS client_phone,
            c.valid AS client_valid,
            c.type AS client_type,
            c.group AS client_group_id,
            cg.name AS client_group_name,
            c.discount AS client_discount,
            cg.discount AS client_group_discount,
            u.id AS executor_id,
            u.username AS executor_username,
            u.full_name AS executor_full_name,
            u.post AS executor_post,
            u.role AS executor_role_id,
            ug.name AS executor_role_name,
            ug.code AS executor_role_code,
            a.id AS act_id,
            a.unique AS act_unique
        FROM sales s
        LEFT JOIN payment_methods pm ON s.payment_method = pm.id
        LEFT JOIN clients c ON s.client = c.id
        LEFT JOIN client_groups cg ON c.group = cg.id
        LEFT JOIN users u ON s.executor = u.id
        LEFT JOIN user_groups ug ON u.role = ug.id
        LEFT JOIN acts a ON a.sale = s.id";
        
        $params = [];
        $conditions = [];

        if ($startDate !== null) {
            $conditions[] = "s.timestamp >= ?";
            $params[] = $startDate;
        }
        
        if ($endDate !== null) {
            $conditions[] = "s.timestamp <= ?";
            $params[] = $endDate;
        }
        
        if ($clientPhone !== null) {
            $conditions[] = "c.phone LIKE ?";
            $params[] = "%$clientPhone%";
        }
        
        if ($clientType !== null) {
            $conditions[] = "c.type = ?";
            $params[] = $clientType;
        }
        
        if ($executorId !== null) {
            $conditions[] = "s.executor = ?";
            $params[] = $executorId;
        }

        if (!empty($conditions)) {
            $sql .= " WHERE " . implode(" AND ", $conditions);
        }

        $sql .= " ORDER BY s.timestamp DESC";

        if ($limit !== null) {
            $sql .= " LIMIT ?";
            $params[] = $limit;
            
            if ($offset !== null) {
                $sql .= " OFFSET ?";
                $params[] = $offset;
            }
        }

        $salesData = $this->fetchAll($sql, $params);

        $sales = [];
        foreach ($salesData as $saleData) {
            $sale = [
                'id' => $saleData['id'],
                'timestamp' => $saleData['timestamp'],
                'amount' => $this->toFixed($saleData['amount']),
                'payment_method' => [
                    'id' => $saleData['payment_method_id'],
                    'name' => $saleData['payment_method_name'],
                    'code' => $saleData['payment_method_code']
                ],
                'cash' => $saleData['cash'],
                'card' => $saleData['card'],
                'client' => [
                    'id' => $saleData['client_id'],
                    'name' => $saleData['client_name'],
                    'phone' => $saleData['client_phone'],
                    'valid' => $saleData['client_valid'],
                    'type' => $saleData['client_type'],
                    'group' => [
                        'id' => $saleData['client_group_id'],
                        'name' => $saleData['client_group_name'],
                        'discount' => $saleData['client_group_discount']
                    ],
                    'discount' => $saleData['client_discount']
                ],
                'executor' => [
                    'id' => $saleData['executor_id'],
                    'username' => $saleData['executor_username'],
                    'full_name' => $saleData['executor_full_name'],
                    'post' => $saleData['executor_post'],
                    'role' => [
                        'id' => $saleData['executor_role_id'],
                        'name' => $saleData['executor_role_name'],
                        'code' => $saleData['executor_role_code']
                    ]
                ],
                'items' => [],
                'act' => $saleData['act_id'] ? [
                    'id' => $saleData['act_id'],
                    'unique' => $saleData['act_unique']
                ] : null
            ];

            $itemsSql = "SELECT
                si.id,
                si.service,
                sv.name AS service_name,
                sv.price,
                sv.category_id,
                sc.name AS category_name,
                si.discount,
                si.device
            FROM sale_items si
            LEFT JOIN services sv ON si.service = sv.id
            LEFT JOIN service_categories sc ON sv.category_id = sc.id
            WHERE si.sale = ?";
            
            $itemsData = $this->fetchAll($itemsSql, [$saleData['id']]);
            
            foreach ($itemsData as $itemData) {
                $sale['items'][] = [
                    'id' => $itemData['id'],
                    'service' => [
                        'id' => $itemData['service'],
                        'name' => $itemData['service_name'],
                        'price' => $this->toFixed($itemData['price']),
                        'total_price' => $this->toFixed($itemData['price'] - ($itemData['price'] * ($itemData['discount'] / 100))),
                        'category' => [
                            'id' => $itemData['category_id'],
                            'name' => $itemData['category_name']
                        ]
                    ],
                    'discount' => $itemData['discount'],
                    'device' => $itemData['device']
                ];
            }

            $sales[] = $sale;
        }

        return $sales;
    }

    function toFixed($number, $decimals = 2) {
        $formatted = number_format($number, $decimals, '.', '');
        return rtrim(rtrim($formatted, '0'), '.');
    }

    public function insertSale(int $executor, string $phone, string $name, $services, float $summ, string $pay_method, float $cash = null, float $card = null, float $noncash = null, string $receipt = null, float $bankComission = 0) {
        $response = ['success' => false, 'paid' => false];

        $receipt = $receipt == 'true' ? true : false;

        try {
            $response['paid'] = true;

            if ($response['paid'] == true) {
                $phoneValidity = $this->validatePhoneNumber($phone);

                if ($phoneValidity['success'] !== true) {
                    $response['message'] = $phoneValidity['message'];
                    return $response;
                }
                $validatePhone = $phoneValidity['phone'];

                $checkClient = $this->fetchAll("SELECT id FROM clients WHERE phone = ?",  [$validatePhone]);
                $clientID = !empty($checkClient) ? $checkClient[0]['id'] : 0;

                if (empty($checkClient)) {
                    $clientDefaultGroup = $this->fetchAll("SELECT id FROM client_groups WHERE is_default = 1")[0]['id'];
                    $clientDefaultDiscount = $this->getSettings()['default_discount'];
                    $insertClientResponse = $this->insertClient($name, $validatePhone, 'individual', $clientDefaultGroup, $clientDefaultDiscount, json: false);
                    if (!$insertClientResponse['success']) {
                        throw new DatabaseException($insertClientResponse['message']);
                    }
                    $clientID = $this->lastInsertId();
                }

                $sql = "INSERT INTO sales (amount, client, executor, payment_method, cash, card, noncash, receipt, bank_comission) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $params = [$summ, $clientID, $executor, $pay_method, $cash, $card, $noncash, $receipt, $bankComission];
                
                $this->query($sql, $params);
                $lastSaleID = $this->lastInsertId();

                $itemsSql = "INSERT INTO sale_items (sale, service, price, discount, device) VALUES (?, ?, ?, ?, ?)";

                foreach ($services as $index => $service) {
                    $itemsParams = [$lastSaleID, $service['id'], $service['price'], $service['discount'], $service['device']];
                    $this->query($itemsSql, $itemsParams);
                }

                $actsSql = "INSERT INTO acts (client, sale) VALUES (?, ?)";
                $this->query($actsSql, [$clientID, $lastSaleID]);

                $response['success'] = true;
                if (empty($checkClient)) {
                    $response['message'] = 'Продажа и новый клиент успешно добавлены. Ожидаем подпись клиента.';
                } else {
                    $response['message'] = 'Продажа успешно добавлена. Ожидаем подпись клиента.';
                }
            }
        } catch (\Exception $e) {
            $errorCode = $e->getCode();
            $errorMessage = $e->getMessage();
            $response['success'] = false;
            if ($errorCode == 5005) {
                $response['message'] = $errorMessage;
            } else {
                $response['message'] = 'Системная ошибка, обратитесь к администратору! Код ошибки: <strong>' . $errorCode . '</strong>, Ошибка: ' . $errorMessage;
            }
        }

        return $response;
    }

    public function removeSale(int $id) {
        $response = [];

        try {
            $sql = "DELETE FROM sales WHERE id = ?";
            $this->query($sql, [$id]);
            $response['success'] = true;
            $response['message'] = 'Продажа <strong>#' . $id . '</strong>, удалена.';
        } catch (\Exception $e) {
            $errorCode = $e->getCode();
            $errorMessage = $e->getMessage();
            $response['success'] = false;
            $response['message'] = 'Системная ошибка, обратитесь к администратору! Код ошибки: ' . $errorCode . ', Ошибка: ' . $errorMessage;
        }

        return $response;
    }

    public function getMovements($amounts = null, $filters = null) {
        if ($amounts == true) {
            $methods = $this->fetchAll("SELECT id, code FROM payment_methods");
            
            $requiredMethods = ['CASH', 'CARD', 'NONCASH', 'MIXED'];
            $methodIds = [];
            
            foreach ($methods as $method) {
                if (in_array($method['code'], $requiredMethods)) {
                    $methodIds[$method['code']] = (int)$method['id'];
                }
            }
            
            $missingMethods = array_diff($requiredMethods, array_keys($methodIds));
            if (!empty($missingMethods)) {
                throw new DatabaseException("Отсутствуют методы оплаты: " . implode(', ', $missingMethods));
            }

            $sql = "SELECT
                COALESCE(SUM(CASE WHEN type = 'приход' THEN cash + card + noncash ELSE 0 END), 0) AS total_all,
                COALESCE(COUNT(DISTINCT CASE WHEN type = 'приход' THEN id ELSE NULL END), 0) AS total_receipts_count,

                COALESCE(SUM(CASE
                    WHEN type = 'приход' AND method = {$methodIds['CASH']} THEN cash
                    WHEN type = 'приход' AND method = {$methodIds['MIXED']} THEN cash
                    ELSE 0
                END), 0) AS coming_cash,
                
                COALESCE(SUM(CASE 
                    WHEN type = 'приход' AND method = {$methodIds['CARD']} THEN card
                    WHEN type = 'приход' AND method = {$methodIds['MIXED']} THEN card
                    ELSE 0
                END), 0) AS coming_card,
                
                COALESCE(SUM(CASE 
                    WHEN type = 'приход' AND method = {$methodIds['NONCASH']} THEN noncash
                    ELSE 0
                END), 0) AS coming_noncash,
                
                COALESCE(SUM(CASE
                    WHEN type = 'расход' AND method = {$methodIds['CASH']} THEN cash
                    WHEN type = 'расход' AND method = {$methodIds['MIXED']} THEN cash
                    ELSE 0
                END), 0) AS expenses_cash,
                
                COALESCE(SUM(CASE 
                    WHEN type = 'расход' AND method = {$methodIds['CARD']} THEN card
                    WHEN type = 'расход' AND method = {$methodIds['MIXED']} THEN card
                    ELSE 0
                END), 0) AS expenses_card,
                
                COALESCE(SUM(CASE 
                    WHEN type = 'расход' AND method = {$methodIds['NONCASH']} THEN noncash
                    ELSE 0
                END), 0) AS expenses_noncash,
                
                COALESCE(SUM(CASE
                    WHEN footing = 'Комиссия банка' THEN cash + card + noncash
                    ELSE 0
                END), 0) AS bank_comission,
                
                COALESCE(SUM(CASE
                    WHEN type = 'приход' AND receipt = 1 THEN cash + card + noncash
                    ELSE 0
                END), 0) AS taxed_amount
                
                FROM movements WHERE 1=1";
            
            $params = [];
            
            if (!empty($filters['start_date'])) {
                $sql .= " AND timestamp >= ?";
                $params[] = $filters['start_date'] . ' 00:00:00';
            }
            if (!empty($filters['end_date'])) {
                $sql .= " AND timestamp <= ?";
                $params[] = $filters['end_date'] . ' 23:59:59';
            }
            
            if (!empty($filters['type']) && $filters['type'] !== 'Все') {
                $sql .= " AND type = ?";
                $params[] = $filters['type'];
            }
            
            if (!empty($filters['footing'])) {
                $sql .= " AND footing LIKE ?";
                $params[] = '%' . $filters['footing'] . '%';
            }
            
            if (isset($filters['method']) && $filters['method'] !== '') {
                if ($filters['method'] != 0) {
                    $sql .= " AND method = ?";
                    $params[] = (int)$filters['method'];
                }
            }

            if (empty($filters['start_date']) && empty($filters['end_date'])) {
                $sql .= " AND MONTH(timestamp) = MONTH(CURRENT_DATE) 
                        AND YEAR(timestamp) = YEAR(CURRENT_DATE)";
            }
            
            $result = $this->fetchAll($sql, $params)[0];
            
            $todaySql = "SELECT
                COALESCE(SUM(CASE 
                    WHEN type = 'приход' AND DATE(timestamp) = CURDATE() AND method = {$methodIds['CASH']} THEN cash
                    WHEN type = 'приход' AND DATE(timestamp) = CURDATE() AND method = {$methodIds['MIXED']} THEN cash
                    ELSE 0
                END), 0) AS today_coming_cash,
                
                COALESCE(SUM(CASE 
                    WHEN type = 'приход' AND DATE(timestamp) = CURDATE() AND method = {$methodIds['CARD']} THEN card
                    WHEN type = 'приход' AND DATE(timestamp) = CURDATE() AND method = {$methodIds['MIXED']} THEN card
                    ELSE 0
                END), 0) AS today_coming_card,
                
                COALESCE(SUM(CASE 
                    WHEN type = 'приход' AND DATE(timestamp) = CURDATE() AND method = {$methodIds['NONCASH']} THEN noncash
                    ELSE 0
                END), 0) AS today_coming_noncash
                
                FROM movements WHERE DATE(timestamp) = CURDATE()";
            
            $todayResult = $this->fetchAll($todaySql)[0];

            $daysWithIncomeQuery = "SELECT COUNT(DISTINCT DATE(timestamp)) AS days_with_income 
                        FROM movements 
                        WHERE type = 'приход' 
                        AND MONTH(timestamp) = MONTH(CURRENT_DATE) 
                        AND YEAR(timestamp) = YEAR(CURRENT_DATE)";
            $daysWithIncome = $this->fetchAll($daysWithIncomeQuery)[0]['days_with_income'];

            $dailyAverage = $daysWithIncome > 0
                ? $result['total_all'] / $daysWithIncome
                : 0;
            
            $tax = $result['taxed_amount'] * 0.06;
            
            return [
                'comings' => [
                    'cash' => (float)$result['coming_cash'],
                    'card' => (float)$result['coming_card'],
                    'noncash' => (float)$result['coming_noncash']
                ],
                'expenses' => [
                    'cash' => (float)$result['expenses_cash'],
                    'card' => (float)$result['expenses_card'],
                    'noncash' => (float)$result['expenses_noncash']
                ],
                'totals' => [
                    'cash' => (float)($result['coming_cash'] - $result['expenses_cash']),
                    'card' => (float)($result['coming_card'] - $result['expenses_card']),
                    'noncash' => (float)($result['coming_noncash'] - $result['expenses_noncash']),
                    'sum' => (float)(($result['coming_cash'] - $result['expenses_cash'])) + ((float)($result['coming_card'] - $result['expenses_card'])) + (($result['coming_noncash'] - $result['expenses_noncash'])),
                    'bank_comission' => (float)$result['bank_comission'],
                    'tax' => round((float)$tax, 2),
                    'average' => $result['total_receipts_count'] > 0 ? (float)($result['total_all']/$result['total_receipts_count']) : 0,
                    'daily_average' => (float)$dailyAverage
                ],
                'today' => [
                    'coming' => [
                        'cash' => (float)$todayResult['today_coming_cash'],
                        'card' => (float)$todayResult['today_coming_card'],
                        'noncash' => (float)$todayResult['today_coming_noncash']
                    ]
                ]
            ];
        } else {
            $sql = "SELECT m.*, pm.name as payment_method_name
                    FROM movements m
                    LEFT JOIN payment_methods pm ON m.method = pm.id
                    WHERE 1=1";
            $params = [];

            if (!empty($filters['start_date'])) {
                $sql .= " AND m.timestamp >= ?";
                $params[] = $filters['start_date'] . ' 00:00:00';
            }
            if (!empty($filters['end_date'])) {
                $sql .= " AND m.timestamp <= ?";
                $params[] = $filters['end_date'] . ' 23:59:59';
            }
            
            if (!empty($filters['type']) && $filters['type'] !== 'Все') {
                $sql .= " AND m.type = ?";
                $params[] = $filters['type'];
            }
            
            if (!empty($filters['footing'])) {
                $sql .= " AND m.footing LIKE ?";
                $params[] = '%' . $filters['footing'] . '%';
            }
            
            if (isset($filters['method']) && $filters['method'] !== '') {
                if ($filters['method'] != 0) {
                    $sql .= " AND m.method = ?";
                    $params[] = (int)$filters['method'];
                }
            }

            if (empty($filters)) {
                $sql .= " AND MONTH(timestamp) = MONTH(CURRENT_DATE) 
                        AND YEAR(timestamp) = YEAR(CURRENT_DATE) ORDER BY m.timestamp DESC";
            } else {
                $sql .= " ORDER BY m.timestamp DESC";
            }
            
            return $this->fetchAll($sql, $params);
        }
    }

    public function insertMovement(string $type, float $summ, string $footing, int $pay_method) {
        $response = [];
        try {
            try {
                $checkValueInMovements = $this->query("INSERT INTO movement_footings (value) VALUES (?)", [$footing]);
            } catch (\Exception $e) {
                $checkValueInMovements = false;
            }

            $cash = 0.0;
            $card = 0.0;
            $noncash = 0.0;

            $payMethods = $this->fetchAll("SELECT id, code FROM payment_methods WHERE id = ?", [$pay_method]);
            if (empty($payMethods)) {
                throw new DatabaseException("Неизвестный метод оплаты с ID: " . $pay_method);
            }
            $payMethodCode = $payMethods[0]['code'];

            switch ($payMethodCode) {
                case 'CASH':
                    $cash = $summ;
                    break;
                case 'CARD':
                    $card = $summ;
                    break;
                case 'NONCASH':
                    $noncash = $summ;
                    break;
                case 'MIXED':
                    throw new DatabaseException("Метод оплаты MIXED не поддерживается для прямого добавления движения.");
                default:
                    throw new DatabaseException("Неизвестный код метода оплаты: " . $payMethodCode);
            }

            $sql = "INSERT INTO movements (cash, card, noncash, type, footing, method) VALUES (?, ?, ?, ?, ?, ?)";
            $params = [$cash, $card, $noncash, $type, $footing, $pay_method];
            
            $this->query($sql, $params);

            $response['success'] = true;
            $response['message'] = 'Движение успешно добавлено.';
        } catch (\Exception $e) {
            $errorCode = $e->getCode();
            $errorMessage = $e->getMessage();
            $response['success'] = false;
            $response['message'] = 'Системная ошибка, обратитесь к администратору! Код ошибки: <strong>' . $errorCode . '</strong>, Ошибка: ' . $errorMessage;
        }

        return $response;
    }

    public function removeMovement(int $id) {
        $response = [];

        try {
            $sql = "DELETE FROM movements WHERE id = ?";
            $this->query($sql, [$id]);
            $response['success'] = true;
            $response['message'] = 'Движение <strong>#' . $id . '</strong>, удалено.';
        } catch (\Exception $e) {
            $errorCode = $e->getCode();
            $errorMessage = $e->getMessage();
            $response['success'] = false;
            $response['message'] = 'Системная ошибка, обратитесь к администратору! Код ошибки: ' . $errorCode . ', Ошибка: ' . $errorMessage;
        }

        return $response;
    }

    public function changeCommission(int $id, float $value) {
        try {
            $response = [];

            $sql = "UPDATE movements SET bank_commission = ? WHERE id = ?";
            $this->query($sql, [$value, $id]);

            $response['success'] = true;
            $response['message'] = 'Коммиссия изменена.';
        } catch (\Exception $e) {
            $errorCode = $e->getCode();
            $errorMessage = $e->getMessage();
            $response['success'] = false;
            $response['message'] = 'Системная ошибка, обратитесь к администратору! Код ошибки: <strong>' . $errorCode . '</strong>, Ошибка: ' . $errorMessage;
        }
        return $response;
    }

    public function getMovementFootings() {
        return $this->fetchAll("SELECT * FROM movement_footings ORDER BY value ASC");
    }

    public function getKassaPin() {
        return $this->fetchAll("SELECT pin FROM kassa")[0]['pin'];
    }

    public function fetchAll(string $sql, array $params = []): array {
        $result = $this->query($sql, $params);
        if ($result instanceof \mysqli_result) {
            return $result->fetch_all(MYSQLI_ASSOC);
        }
        return [];
    }

    public function fetchOne(string $sql, array $params = []): ?array {
        $result = $this->query($sql, $params);
        if ($result instanceof \mysqli_result) {
            return $result->fetch_assoc() ?: null;
        }
        return null;
    }

    public function lastInsertId(): int {
        return $this->connection->insert_id;
    }

    public function beginTransaction(): void {
        $this->connection->begin_transaction();
    }

    public function commit(): void {
        $this->connection->commit();
    }

    public function rollback(): void {
        $this->connection->rollback();
    }

    public function __destruct() {
        if (isset($this->connection)) {
            $this->connection->close();
        }
    }
}