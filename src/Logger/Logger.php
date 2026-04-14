<?php
/**
 * Logger Class
 * 
 * Central logging system for the application.
 * Writes to daily log files with different severity levels.
 */

namespace App\Logger;

class Logger {
    const LEVEL_ERROR = 'ERROR';
    const LEVEL_WARNING = 'WARNING';
    const LEVEL_INFO = 'INFO';
    const LEVEL_DEBUG = 'DEBUG';

    private $logPath;
    private $currentLevel;
    private static $instance = null;

    private function __construct() {
        $this->logPath = dirname(__DIR__) . '/../logs';
        $this->currentLevel = getenv('LOG_LEVEL') ?: 'error';
        $this->ensureLogDirectory();
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function error($message, $context = []) {
        $this->log(self::LEVEL_ERROR, $message, $context);
    }

    public function warning($message, $context = []) {
        $this->log(self::LEVEL_WARNING, $message, $context);
    }

    public function info($message, $context = []) {
        $this->log(self::LEVEL_INFO, $message, $context);
    }

    public function debug($message, $context = []) {
        $this->log(self::LEVEL_DEBUG, $message, $context);
    }

    private function log($level, $message, $context = []) {
        // Don't log if below minimum level in production
        if ($this->shouldLog($level)) {
            $timestamp = date('Y-m-d H:i:s');
            $contextStr = !empty($context) ? ' | ' . json_encode($context) : '';
            $logEntry = "[$timestamp] $level: $message$contextStr\n";

            $filename = $this->logPath . '/' . date('Y-m-d') . '.log';
            @file_put_contents($filename, $logEntry, FILE_APPEND | LOCK_EX);
        }
    }

    private function shouldLog($level) {
        $levels = ['DEBUG' => 0, 'INFO' => 1, 'WARNING' => 2, 'ERROR' => 3];
        $currentLevelValue = $levels[strtoupper($this->currentLevel)] ?? 3;
        $logLevelValue = $levels[$level] ?? 3;
        
        return $logLevelValue >= $currentLevelValue;
    }

    private function ensureLogDirectory() {
        if (!is_dir($this->logPath)) {
            @mkdir($this->logPath, 0755, true);
        }
    }
}
