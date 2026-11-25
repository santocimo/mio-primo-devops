<?php

require_once dirname(__DIR__) . '/config/loader.php';
require_once dirname(__DIR__) . '/vendor/autoload.php';

$host = getenv('DB_HOST') ?: 'localhost';
$port = getenv('DB_PORT') ?: '3306';
$database = getenv('DB_NAME');
$username = getenv('DB_USER');
$password = getenv('DB_PASSWORD') ?: '';

if (!$database || !$username) {
    throw new RuntimeException('Database credentials not configured');
}

$pdo = new PDO(
    "mysql:host=$host;port=$port;dbname=$database;charset=utf8mb4",
    $username,
    $password,
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]
);
$deleted = App\Audit\AdminAuditLogger::purgeExpired($pdo);

printf(
    "Removed %d admin audit records older than %d days.\n",
    $deleted,
    App\Audit\AdminAuditLogger::RETENTION_DAYS
);
