<?php
/**
 * 文件名: core/Logger.php
 * 功能描述: 系统日志记录类
 * 
 * 该类负责:
 * 1. 记录系统运行日志，支持不同日志级别（ERROR, WARNING, INFO, DEBUG）
 * 2. 日志文件管理，包括日志轮转和旧日志清理
 * 3. 操作日志记录到数据库
 * 4. 提供全局日志记录接口
 * 
 * 使用单例模式，全系统统一使用同一日志实例。
 * 支持日志按级别记录，并可配置最大文件大小和备份文件数量。
 * 
 * 关联文件:
 * - logs/error.log: 系统错误日志文件
 * - operation_logs表: 用户操作日志记录表
 * - core/Controller.php: 基础控制器，使用日志记录功能
 * - core/Database.php: 数据库操作类，用于数据库日志记录
 */

namespace core;

class Logger {
    private static $logFile = 'logs/error.log';
    private static $instance = null;
    private static $maxFileSize = 10485760; // 10MB
    private static $maxBackupFiles = 5;
    private $db;

    private function __construct() {
        // 确保日志目录存在
        $logDir = dirname(self::$logFile);
        if (!file_exists($logDir)) {
            mkdir($logDir, 0777, true);
        }
        
        global $db;
        $this->db = $db;
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * 记录错误日志
     * @param string $message 错误信息
     * @param array $context 上下文信息
     */
    public function error($message, $context = []) {
        $this->log('ERROR', $message, $context);
    }

    /**
     * 记录警告日志
     * @param string $message 警告信息
     * @param array $context 上下文信息
     */
    public function warning($message, $context = []) {
        $this->log('WARNING', $message, $context);
    }

    /**
     * 记录调试日志（仅在DEBUG模式下记录）
     * @param string $message 调试信息
     * @param array $context 上下文信息
     */
    public function debug($message, $context = []) {
        if (defined('DEBUG_MODE') && DEBUG_MODE) {
            $this->log('DEBUG', $message, $context);
        }
    }

    /**
     * 记录信息日志
     * @param string $message 信息
     * @param array $context 上下文信息
     */
    public function info($message, $context = []) {
        $this->log('INFO', $message, $context);
        
        // 如果有用户信息，记录到数据库
        if (isset($_SESSION['user_id'])) {
            try {
                $this->db->query("
                    INSERT INTO operation_logs (
                        user_id, 
                        username,
                        role, 
                        action_type, 
                        action_detail, 
                        ip_address
                    ) VALUES (?, ?, ?, ?, ?, ?)", [
                        $_SESSION['user_id'],
                        $_SESSION['username'],
                        $_SESSION['role'],
                        $context['action_type'] ?? '系统操作',
                        $message,
                        $_SERVER['REMOTE_ADDR']
                    ]
                );
            } catch (\Exception $e) {
                $this->error('记录操作日志失败: ' . $e->getMessage());
            }
        }
    }

    /**
     * 记录日志
     * @param string $level 日志级别
     * @param string $message 日志信息
     * @param array $context 上下文信息
     */
    private function log($level, $message, $context = []) {
        // 检查并轮转日志文件
        $this->rotateLogFileIfNeeded();

        $date = date('Y-m-d H:i:s');
        $logMessage = "[$date] [$level] $message";
        
        if (!empty($context)) {
            $logMessage .= "\nContext: " . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        }
        
        $logMessage .= "\n\n";
        
        // 读取现有日志
        $existingLog = '';
        if (file_exists(self::$logFile)) {
            $existingLog = file_get_contents(self::$logFile);
        }
        
        // 将新日志添加到开头
        file_put_contents(self::$logFile, $logMessage . $existingLog);
    }

    /**
     * 检查并轮转日志文件
     */
    private function rotateLogFileIfNeeded() {
        if (!file_exists(self::$logFile)) {
            return;
        }

        if (filesize(self::$logFile) >= self::$maxFileSize) {
            // 移动现有备份文件
            for ($i = self::$maxBackupFiles - 1; $i >= 1; $i--) {
                $oldFile = self::$logFile . '.' . $i;
                $newFile = self::$logFile . '.' . ($i + 1);
                if (file_exists($oldFile)) {
                    rename($oldFile, $newFile);
                }
            }

            // 移动当前日志文件为第一个备份
            rename(self::$logFile, self::$logFile . '.1');
        }
    }

    /**
     * 清理旧日志
     * @param int $days 保留天数
     */
    public function cleanOldLogs($days = 30) {
        if (!file_exists(self::$logFile)) {
            return;
        }

        $logs = file_get_contents(self::$logFile);
        $logEntries = explode("\n\n", $logs);
        $currentTime = time();
        $filteredLogs = [];

        foreach ($logEntries as $entry) {
            if (empty(trim($entry))) continue;
            
            if (preg_match('/^\[([\d\- :]+)\]/', $entry, $matches)) {
                $logTime = strtotime($matches[1]);
                if ($currentTime - $logTime < $days * 86400) {
                    $filteredLogs[] = $entry;
                }
            }
        }

        file_put_contents(self::$logFile, implode("\n\n", $filteredLogs));

        // 同时清理备份文件
        for ($i = 1; $i <= self::$maxBackupFiles; $i++) {
            $backupFile = self::$logFile . '.' . $i;
            if (file_exists($backupFile)) {
                $this->cleanBackupFile($backupFile, $days);
            }
        }
    }

    /**
     * 清理备份日志文件
     * @param string $file 文件路径
     * @param int $days 保留天数
     */
    private function cleanBackupFile($file, $days) {
        $logs = file_get_contents($file);
        $logEntries = explode("\n\n", $logs);
        $currentTime = time();
        $filteredLogs = [];

        foreach ($logEntries as $entry) {
            if (empty(trim($entry))) continue;
            
            if (preg_match('/^\[([\d\- :]+)\]/', $entry, $matches)) {
                $logTime = strtotime($matches[1]);
                if ($currentTime - $logTime < $days * 86400) {
                    $filteredLogs[] = $entry;
                }
            }
        }

        if (empty($filteredLogs)) {
            unlink($file); // 如果没有需要保留的日志，删除备份文件
        } else {
            file_put_contents($file, implode("\n\n", $filteredLogs));
        }
    }

    /**
     * 设置最大文件大小
     * @param int $size 文件大小（字节）
     */
    public static function setMaxFileSize($size) {
        self::$maxFileSize = $size;
    }

    /**
     * 设置最大备份文件数
     * @param int $count 文件数量
     */
    public static function setMaxBackupFiles($count) {
        self::$maxBackupFiles = $count;
    }
} 