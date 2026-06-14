<?php
require __DIR__ . '/config/config.php';
try {
    $pdo = new PDO('mysql:host='. DB_HOST .';dbname='. DB_NAME, DB_USER, DB_PASS);
    echo "PDO connection successful!";
} catch (PDOException $e) {
    echo "PDO ERROR: " . $e->getMessage();
    echo "<br>Required extensions: ";
    print_r(get_loaded_extensions());
}