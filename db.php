<?php
/**
 * Backward Compatibility Layer
 * 
 * This file provides the legacy getPDO() function for backward compatibility.
 * NEW CODE SHOULD USE: App\Database\DatabaseManager::getInstance()->getConnection()
 * 
 * @deprecated Use DatabaseManager instead
 */

require_once __DIR__ . '/bootstrap.php';

use App\Database\DatabaseManager;

/**
 * Get PDO connection (legacy function)
 * 
 * @deprecated Use DatabaseManager::getInstance()->getConnection() instead
 * @return PDO
 */
function getPDO() {
    return DatabaseManager::getInstance()->getConnection();
}

/**
 * Get application setting
 */
function getAppSetting($pdo, $key, $default = null) {
    try {
        $stmt = $pdo->prepare('SELECT setting_value FROM app_settings WHERE setting_key = ? LIMIT 1');
        $stmt->execute([$key]);
        $value = $stmt->fetchColumn();
        return $value !== false ? $value : $default;
    } catch (Exception $e) {
        return $default;
    }
}

/**
 * Set application setting
 */
function setAppSetting($pdo, $key, $value) {
    try {
        $stmt = $pdo->prepare('INSERT INTO app_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)');
        return $stmt->execute([$key, $value]);
    } catch (Exception $e) {
        return false;
    }
}

