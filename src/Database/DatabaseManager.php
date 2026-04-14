<?php
/**
 * Database Connection Manager
 * 
 * Singleton PDO connection with proper error handling
 * and configuration from environment variables.
 */

namespace App\Database;

use PDO;
use PDOException;
use App\Logger\Logger;

class DatabaseManager {
    private static $instance = null;
    private $pdo = null;

    private function __construct() {
        $this->connect();
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection() {
        return $this->pdo;
    }

    private function connect() {
        try {
            $host = getenv('DB_HOST') ?: 'localhost';
            $port = getenv('DB_PORT') ?: 3306;
            $database = getenv('DB_NAME');
            $username = getenv('DB_USER');
            $password = getenv('DB_PASSWORD');

            if (!$database || !$username) {
                throw new PDOException('Database credentials not configured');
            }

            $dsn = "mysql:host=$host;port=$port;dbname=$database;charset=utf8mb4";

            $this->pdo = new PDO($dsn, $username, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);

            // Initialize schema
            $this->initializeSchema();

        } catch (PDOException $e) {
            $logger = Logger::getInstance();
            $logger->error('Database connection failed', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    private function initializeSchema() {
        try {
            $this->ensureGymCategory();
            $this->ensureServiceSchema();
            $this->ensureAppointmentSchema();
            $this->ensureSettingsSchema();
        } catch (Exception $e) {
            $logger = Logger::getInstance();
            $logger->warning('Schema initialization warning', ['error' => $e->getMessage()]);
        }
    }

    private function ensureGymCategory() {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
             WHERE table_schema = DATABASE() 
             AND table_name = 'gyms' 
             AND column_name = 'category'"
        );
        $stmt->execute();
        if (!(bool)$stmt->fetchColumn()) {
            $this->pdo->exec("ALTER TABLE gyms ADD COLUMN category VARCHAR(50) NOT NULL DEFAULT 'gym'");
        }
    }

    private function ensureServiceSchema() {
        $this->pdo->exec(
            "CREATE TABLE IF NOT EXISTS services (
              id INT AUTO_INCREMENT PRIMARY KEY,
              gym_id INT NOT NULL,
              name VARCHAR(150) NOT NULL,
              slug VARCHAR(100) NOT NULL,
              category VARCHAR(50) NOT NULL DEFAULT 'class',
              description TEXT DEFAULT NULL,
              duration_minutes INT NOT NULL DEFAULT 60,
              capacity INT NOT NULL DEFAULT 10,
              price DECIMAL(10,2) DEFAULT NULL,
              created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
              UNIQUE KEY (gym_id, slug),
              FOREIGN KEY (gym_id) REFERENCES gyms(id) ON DELETE CASCADE
            )"
        );
    }

    private function ensureAppointmentSchema() {
        $this->pdo->exec(
            "CREATE TABLE IF NOT EXISTS appointments (
              id INT AUTO_INCREMENT PRIMARY KEY,
              service_id INT NOT NULL,
              customer_name VARCHAR(150) NOT NULL,
              customer_email VARCHAR(150) DEFAULT NULL,
              scheduled_at DATETIME NOT NULL,
              status VARCHAR(30) NOT NULL DEFAULT 'pending',
              notes TEXT DEFAULT NULL,
              created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
              FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE CASCADE
            )"
        );
    }

    private function ensureSettingsSchema() {
        $this->pdo->exec(
            "CREATE TABLE IF NOT EXISTS app_settings (
              id INT AUTO_INCREMENT PRIMARY KEY,
              setting_key VARCHAR(100) NOT NULL UNIQUE,
              setting_value TEXT NOT NULL,
              updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )"
        );
    }
}
