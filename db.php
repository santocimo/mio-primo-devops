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
    ensureGymCategory($pdo);
    ensureServiceSchema($pdo);
    ensureAppointmentSchema($pdo);
    ensureSettingsSchema($pdo);
    return $pdo;
}

function ensureGymCategory(PDO $pdo): void {
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE table_schema = DATABASE() AND table_name = 'gyms' AND column_name = 'category'");
        $stmt->execute();
        if (!(bool)$stmt->fetchColumn()) {
            $pdo->exec("ALTER TABLE gyms ADD COLUMN category VARCHAR(50) NOT NULL DEFAULT 'gym'");
        }
    } catch (Exception $e) {
        // ignore schema changes when table doesn't exist or DB is not ready
    }
}

function ensureServiceSchema(PDO $pdo): void {
    try {
        $pdo->exec(
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
    } catch (Exception $e) {
        // ignore if schema not available yet
    }
}

function ensureAppointmentSchema(PDO $pdo): void {
    try {
        $pdo->exec(
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
    } catch (Exception $e) {
        // ignore if schema not available yet
    }
}

function ensureSettingsSchema(PDO $pdo): void {
    try {
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS app_settings (
              id INT AUTO_INCREMENT PRIMARY KEY,
              setting_key VARCHAR(100) NOT NULL UNIQUE,
              setting_value TEXT NOT NULL,
              updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )"
        );
    } catch (Exception $e) {
        // ignore if schema not available yet
    }
}

function getAppSetting(PDO $pdo, string $key, $default = null) {
    try {
        $stmt = $pdo->prepare('SELECT setting_value FROM app_settings WHERE setting_key = ? LIMIT 1');
        $stmt->execute([$key]);
        $value = $stmt->fetchColumn();
        return $value !== false ? $value : $default;
    } catch (Exception $e) {
        return $default;
    }
}

function setAppSetting(PDO $pdo, string $key, string $value): bool {
    try {
        $stmt = $pdo->prepare('INSERT INTO app_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)');
        return $stmt->execute([$key, $value]);
    } catch (Exception $e) {
        return false;
    }
}

?>
