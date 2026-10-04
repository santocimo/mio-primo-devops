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

    public static function normalizeProviderType(?string $providerType): string {
        $normalized = strtolower(trim((string)($providerType ?? 'internal')));
        return in_array($normalized, ['internal', 'external'], true) ? $normalized : 'internal';
    }

    public static function canAccessGymResource(?int $userGymId, int $resourceGymId, bool $isAdmin = false): bool {
        if ($isAdmin) {
            return true;
        }
        return $userGymId !== null && $userGymId > 0 && (int)$resourceGymId === (int)$userGymId;
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
            $this->ensureGymsSchema();
            $this->ensureGymCategory();
            $this->ensureVisitorSchema();
            $this->ensureUsersSchema();
            $this->ensureServiceSchema();
            $this->ensureAppointmentSchema();
            $this->ensureSettingsSchema();
        } catch (Exception $e) {
            $logger = Logger::getInstance();
            $logger->warning('Schema initialization warning', ['error' => $e->getMessage()]);
        }
    }

    private function ensureVisitorSchema() {
        // visitatori table used by legacy web UI and API
        $this->pdo->exec(
            "CREATE TABLE IF NOT EXISTS visitatori (
              id INT AUTO_INCREMENT PRIMARY KEY,
              nome VARCHAR(150) NOT NULL,
              cognome VARCHAR(150) NOT NULL,
              codice_fiscale VARCHAR(16) DEFAULT NULL,
              data_nascita DATE DEFAULT NULL,
              luogo_nascita VARCHAR(150) DEFAULT NULL,
              indirizzo VARCHAR(255) DEFAULT NULL,
              recapito VARCHAR(100) DEFAULT NULL,
              sesso CHAR(1) DEFAULT 'M',
              gym_id INT NOT NULL DEFAULT 1,
              created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
              UNIQUE KEY ux_visitatori_gym_cf (gym_id, codice_fiscale),
              INDEX idx_visitatori_gym (gym_id)
            ) ENGINE=InnoDB CHARSET=utf8mb4"
        );
    }

    private function ensureGymsSchema() {
        $this->pdo->exec(
            "CREATE TABLE IF NOT EXISTS gyms (
              id INT AUTO_INCREMENT PRIMARY KEY,
              name VARCHAR(255) NOT NULL,
              slug VARCHAR(100) NOT NULL UNIQUE,
              category VARCHAR(50) NOT NULL DEFAULT 'gym',
              settings JSON DEFAULT NULL,
              created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )"
        );

        $this->pdo->exec(
            "INSERT INTO gyms (name, slug, category)
             SELECT 'Default Gym', 'default', 'gym'
             WHERE NOT EXISTS (SELECT 1 FROM gyms WHERE slug = 'default')"
        );
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

    private function ensureUsersSchema() {
        $this->pdo->exec(
            "CREATE TABLE IF NOT EXISTS users (
              id INT AUTO_INCREMENT PRIMARY KEY,
              name VARCHAR(150) NOT NULL,
              email VARCHAR(150) NOT NULL UNIQUE,
              username VARCHAR(50) NOT NULL UNIQUE,
              password_hash VARCHAR(255) NOT NULL,
              role VARCHAR(50) NOT NULL DEFAULT 'ADMIN',
              gym_id INT NOT NULL DEFAULT 1,
              created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )"
        );

        $userColumns = $this->pdo->query("SHOW COLUMNS FROM users")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('name', $userColumns, true)) {
            $this->pdo->exec("ALTER TABLE users ADD COLUMN name VARCHAR(150) NOT NULL DEFAULT ''");
        }
        if (!in_array('email', $userColumns, true)) {
            $this->pdo->exec("ALTER TABLE users ADD COLUMN email VARCHAR(150) NOT NULL DEFAULT ''");
        }

        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ? LIMIT 1");
        $stmt->execute(['admin']);
        if ((int)$stmt->fetchColumn() === 0) {
            $adminPasswordHash = password_hash('admin123', PASSWORD_DEFAULT);
            $insert = $this->pdo->prepare(
                "INSERT INTO users (name, email, username, password_hash, role, gym_id)
                 VALUES (?, ?, ?, ?, ?, ?)"
            );
            $insert->execute([
                'Administrator',
                'admin@system.local',
                'admin',
                $adminPasswordHash,
                'ADMIN',
                1,
            ]);
        } else {
            $this->pdo->prepare(
                "UPDATE users SET name = COALESCE(NULLIF(name, ''), ?), email = COALESCE(NULLIF(email, ''), ?) WHERE username = ?"
            )->execute(['Administrator', 'admin@system.local', 'admin']);
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
              provider_name VARCHAR(150) DEFAULT NULL,
              provider_type VARCHAR(30) NOT NULL DEFAULT 'internal',
              duration_minutes INT NOT NULL DEFAULT 60,
              capacity INT NOT NULL DEFAULT 10,
              price DECIMAL(10,2) DEFAULT NULL,
              created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
              UNIQUE KEY (gym_id, slug),
              FOREIGN KEY (gym_id) REFERENCES gyms(id) ON DELETE CASCADE
            )"
        );

        $serviceColumns = $this->pdo->query("SHOW COLUMNS FROM services")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('provider_name', $serviceColumns, true)) {
            $this->pdo->exec("ALTER TABLE services ADD COLUMN provider_name VARCHAR(150) DEFAULT NULL");
        }
        if (!in_array('provider_type', $serviceColumns, true)) {
            $this->pdo->exec("ALTER TABLE services ADD COLUMN provider_type VARCHAR(30) NOT NULL DEFAULT 'internal'");
        }
    }

    private function ensureAppointmentSchema() {
        $this->pdo->exec(
            "CREATE TABLE IF NOT EXISTS appointments (
              id INT AUTO_INCREMENT PRIMARY KEY,
              service_id INT NOT NULL,
              contact_id INT DEFAULT NULL,
              customer_name VARCHAR(150) NOT NULL,
              customer_email VARCHAR(150) DEFAULT NULL,
              scheduled_at DATETIME NOT NULL,
              status VARCHAR(30) NOT NULL DEFAULT 'pending',
              notes TEXT DEFAULT NULL,
              created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
              INDEX idx_appointments_contact_id (contact_id),
              FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE CASCADE
            )"
        );

        $columns = $this->pdo->query("SHOW COLUMNS FROM appointments")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('contact_id', $columns, true)) {
            $this->pdo->exec("ALTER TABLE appointments ADD COLUMN contact_id INT DEFAULT NULL, ADD INDEX idx_appointments_contact_id (contact_id)");
        }
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
