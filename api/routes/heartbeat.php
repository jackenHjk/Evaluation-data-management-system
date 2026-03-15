<?php
/**
 * 文件名: api/routes/heartbeat.php
 * 功能描述: 会话保活心跳处理路由
 * 
 * 该文件负责:
 * 1. 处理用户的心跳请求
 * 2. 更新用户的最后活动时间
 * 3. 验证会话是否有效
 * 
 * API调用方式:
 * - 端点: api/index.php?route=heartbeat
 * - 方法: POST
 * - 参数: 无（使用当前会话信息）
 * - 返回: JSON格式
 *   - success: 布尔值，表示心跳成功或失败
 *   - error: 字符串，失败原因（仅在失败时）
 * 
 * 关联文件:
 * - config/config.php: 系统配置文件
 * - core/Database.php: 数据库操作类
 * - api/index.php: API入口文件，路由请求到此文件
 * - index.php: 主页面文件，调用此API进行会话保活
 */

// 确保会话已启动
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 检查是否已登录
if (!isset($_SESSION['user_id']) || !isset($_SESSION['login_token'])) {
    echo json_encode(['success' => false, 'error' => '未登录']);
    exit;
}

require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/core/Database.php';

try {
    $db = \Core\Database::getInstance()->getConnection();
    
    // 验证用户会话
    $stmt = $db->prepare('SELECT login_token, last_activity FROM users WHERE id = ? AND status = 1');
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    
    if (!$user || empty($user['login_token']) || $user['login_token'] != $_SESSION['login_token']) {
        echo json_encode(['success' => false, 'error' => '会话已失效']);
        exit;
    }
    
    // 检查最后活动时间
    $lastActivityTime = strtotime($user['last_activity']);
    $currentTime = time();
    
    // 如果超过30分钟没有活动，会话失效
    if (($currentTime - $lastActivityTime) > 1800) {
        echo json_encode(['success' => false, 'error' => '会话已过期']);
        exit;
    }
    
    // 更新最后活动时间
    $updateStmt = $db->prepare('UPDATE users SET last_activity = NOW() WHERE id = ?');
    $updateStmt->execute([$_SESSION['user_id']]);
    
    // 更新会话中的最后活动时间
    $_SESSION['last_activity_update'] = $currentTime;
    
    echo json_encode(['success' => true]);
} catch (\Exception $e) {
    error_log('Heartbeat error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => '系统错误']);
} 