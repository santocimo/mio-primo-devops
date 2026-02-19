<?php
function getPDO() {
    static $pdo = null;
    if ($pdo) return $pdo;
    $host = 'database-santo';
    $db = 'mio_database';
    $user = 'root';
    $pass = 'password_segreta';
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    return $pdo;
}

?>
