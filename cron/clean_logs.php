<?php
/**
 * 文件名: cron/clean_logs.php
 * 功能描述: 系统日志清理计划任务
 * 
 * 该脚本负责:
 * 1. 清理过期的系统错误日志文件
 * 2. 清理临时下载目录中超过24小时的文件
 * 3. 记录清理过程和结果
 * 
 * 推荐的计划任务配置:
 * 0 2 * * * php /path/to/score-system/cron/clean_logs.php
 * (每天凌晨2点执行)
 * 
 * 关联文件:
 * - core/Logger.php: 日志类，提供日志清理功能
 * - logs/: 日志存储目录
 * - temp/downloads/: 临时下载文件目录
 */

require_once __DIR__ . '/../core/Logger.php';

use Core\Logger;

// 设置时区
date_default_timezone_set('Asia/Shanghai');

// 记录清理开始
$logger = Logger::getInstance();
$logger->debug('开始清理任务', [
    'time' => date('Y-m-d H:i:s'),
    'max_days' => 30
]);

try {
    // 1. 清理错误日志
    $logger->debug('开始清理错误日志');
    $logger->cleanOldLogs(30);
    
    // 2. 清理临时文件
    $tempDir = __DIR__ . '/../temp/downloads/';
    $logger->debug('开始清理临时下载文件', ['dir' => $tempDir]);
    
    if (is_dir($tempDir)) {
        $files = glob($tempDir . '*');
        $now = time();
        $deletedCount = 0;
        $totalSize = 0;
        
        foreach ($files as $file) {
            if (is_file($file)) {
                $fileAge = $now - filemtime($file);
                // 清理超过24小时的文件
                if ($fileAge > 24 * 3600) {
                    $fileSize = filesize($file);
                    $totalSize += $fileSize;
                    unlink($file);
                    $deletedCount++;
                }
            }
        }
        
        $logger->debug('临时文件清理完成', [
            'deleted_files' => $deletedCount,
            'total_size' => round($totalSize / 1024 / 1024, 2) . 'MB'
        ]);
    }
    
    // 记录清理成功
    $logger->debug('所有清理任务完成', [
        'time' => date('Y-m-d H:i:s')
    ]);
    
} catch (\Exception $e) {
    // 记录清理失败
    $logger->error('清理任务失败', [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
} 