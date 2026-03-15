<?php
/**
 * 文件名: cron/cleanup_connections.php
 * 功能描述: 清理过期的数据库连接
 * 
 * 该脚本负责:
 * 1. 清理长时间未使用的数据库连接
 * 2. 记录清理日志
 * 
 * 建议每5分钟执行一次
 * crontab设置: */5 * * * * php /path/to/cleanup_connections.php
 */

require_once dirname(__DIR__) . '/core/Database.php';

use Core\Database;

try {
    $db = Database::getInstance();
    $db->cleanupConnections();
    
    // 记录清理时间
    file_put_contents(
        dirname(__DIR__) . '/logs/cleanup.log',
        date('Y-m-d H:i:s') . " - 数据库连接清理完成\n",
        FILE_APPEND
    );
} catch (\Exception $e) {
    file_put_contents(
        dirname(__DIR__) . '/logs/cleanup_error.log',
        date('Y-m-d H:i:s') . " - 错误: " . $e->getMessage() . "\n",
        FILE_APPEND
    );
} 