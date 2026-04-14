<?php
/**
 * Authentication Manager
 * 
 * Handles user authentication with proper password hashing
 * and session management.
 */

namespace App\Auth;

use App\Database\DatabaseManager;
use App\Logger\Logger;
use PDO;

class AuthManager {
    private $pdo;
    private $logger;

    public function __construct() {
        $this->pdo = DatabaseManager::getInstance()->getConnection();
        $this->logger = Logger::getInstance();
    }

    /**
     * Authenticate user by username and password
     */
    public function authenticate($username, $password) {
        try {
            $stmt = $this->pdo->prepare("SELECT id, username, password_hash, role, gym_id FROM users WHERE username = ? LIMIT 1");
            $stmt->execute([$username]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password_hash'])) {
                $this->logger->info('User login successful', ['username' => $username]);
                return $user;
            }

            $this->logger->warning('Failed login attempt', ['username' => $username]);
            return null;

        } catch (\Exception $e) {
            $this->logger->error('Authentication error', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Create a new user with hashed password
     */
    public function createUser($username, $password, $role = 'OPERATOR', $gym_id = null) {
        try {
            $passwordHash = password_hash($password, PASSWORD_BCRYPT);
            
            $stmt = $this->pdo->prepare(
                "INSERT INTO users (username, password_hash, role, gym_id, created_at) 
                 VALUES (?, ?, ?, ?, NOW())"
            );
            
            $result = $stmt->execute([$username, $passwordHash, strtoupper($role), $gym_id]);
            
            if ($result) {
                $this->logger->info('New user created', ['username' => $username, 'role' => $role]);
                return true;
            }
            return false;

        } catch (\Exception $e) {
            $this->logger->error('User creation error', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Change user password
     */
    public function changePassword($userId, $newPassword) {
        try {
            $passwordHash = password_hash($newPassword, PASSWORD_BCRYPT);
            
            $stmt = $this->pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
            $result = $stmt->execute([$passwordHash, $userId]);

            if ($result) {
                $this->logger->info('Password changed', ['user_id' => $userId]);
                return true;
            }
            return false;

        } catch (\Exception $e) {
            $this->logger->error('Password change error', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Get user by ID
     */
    public function getUserById($userId) {
        try {
            $stmt = $this->pdo->prepare("SELECT id, username, role, gym_id FROM users WHERE id = ? LIMIT 1");
            $stmt->execute([$userId]);
            return $stmt->fetch();
        } catch (\Exception $e) {
            $this->logger->error('Get user error', ['error' => $e->getMessage()]);
            return null;
        }
    }
}
