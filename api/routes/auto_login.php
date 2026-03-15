<?php
/**
 * 文件名: api/routes/auto_login.php
 * 功能描述: 自动登录处理路由
 * 
 * 该文件负责:
 * 1. 处理用户自动登录请求
 * 2. 验证登录token的有效性
 * 3. 更新用户会话和最后活动时间
 * 4. 重新生成会话ID，防止会话固定攻击
 * 
 * API调用方式:
 * - 端点: api/index.php?route=auto_login
 * - 方法: POST
 * - 参数: 
 *   - token: 用户登录令牌
 *   - username: 用户名
 *   - device_fingerprint: 设备指纹(可选)
 * - 返回: JSON格式
 *   - success: 布尔值，表示登录成功或失败
 *   - error: 字符串，失败原因（仅在失败时）
 * 
 * 关联文件:
 * - config/config.php: 系统配置文件
 * - core/Database.php: 数据库操作类
 * - api/index.php: API入口文件，路由请求到此文件
 * - index.php: 主页面文件，调用此API进行自动登录
 */

require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/core/Database.php';

// 获取请求数据
$data = json_decode(file_get_contents('php://input'), true);
$token = $data['token'] ?? '';
$username = $data['username'] ?? '';
$deviceFingerprint = $data['device_fingerprint'] ?? ''; // 添加设备指纹参数

if (empty($token) || empty($username)) {
    echo json_encode(['success' => false, 'error' => '无效的请求参数']);
    exit;
}

// 验证token
try {
    $db = \Core\Database::getInstance();
    
    // 首先获取用户信息
    $user = $db->fetch('SELECT * FROM users WHERE username = ?', [$username]);
    
    if (!$user) {
        echo json_encode(['success' => false, 'error' => '用户不存在']);
        exit;
    }
    
    // 检查token是否匹配
    if (empty($user['login_token']) || $user['login_token'] != $token) {
        // 如果token不匹配，但提供了设备指纹且与数据库中的匹配，则可能是重新打开浏览器的情况
        if (!empty($deviceFingerprint) && !empty($user['device_fingerprint']) && 
            $deviceFingerprint === $user['device_fingerprint']) {
            // 生成新的token
            $newToken = bin2hex(random_bytes(16));
            
            // 更新用户的token和最后活动时间
            $db->execute('UPDATE users SET login_token = ?, last_activity = NOW() WHERE id = ?', [$newToken, $user['id']]);
            
            // 在启动会话前设置cookie参数
            session_set_cookie_params([
                'lifetime' => 1800,
                'path' => '/',
                'secure' => true,
                'httponly' => true,
                'samesite' => 'Strict'
            ]);

            // 启动会话
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }

            // 重新生成会话ID以防止会话固定攻击
            session_regenerate_id(true);

            // 设置会话数据
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['real_name'] = $user['real_name'];
            $_SESSION['login_token'] = $newToken;
            $_SESSION['last_activity_update'] = time();
            $_SESSION['device_fingerprint'] = $deviceFingerprint;

            // 返回成功响应，包含新的token
            echo json_encode([
                'success' => true, 
                'login_token' => $newToken,
                'message' => '设备识别成功，已更新登录状态'
            ]);
            exit;
        } else {
            echo json_encode(['success' => false, 'error' => '登录已失效']);
            exit;
        }
    }

    // 检查最后活动时间
    $lastActivityTime = strtotime($user['last_activity']);
    $currentTime = time();
    
    // 如果最后活动时间在30分钟内，允许继续使用当前会话
    if (($currentTime - $lastActivityTime) <= 1800) {
        // 更新最后活动时间
        $db->execute('UPDATE users SET last_activity = NOW() WHERE id = ?', [$user['id']]);

        // 在启动会话前设置cookie参数
        session_set_cookie_params([
            'lifetime' => 1800,
            'path' => '/',
            'secure' => true,
            'httponly' => true,
            'samesite' => 'Strict'
        ]);

        // 启动会话
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // 重新生成会话ID以防止会话固定攻击
        session_regenerate_id(true);

        // 设置会话数据
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['real_name'] = $user['real_name'];
        $_SESSION['login_token'] = $token;
        $_SESSION['last_activity_update'] = time();
        
        // 如果提供了设备指纹，更新会话和数据库
        if (!empty($deviceFingerprint)) {
            $_SESSION['device_fingerprint'] = $deviceFingerprint;
            
            // 如果数据库中没有设备指纹或者设备指纹不匹配，则更新
            if (empty($user['device_fingerprint']) || $user['device_fingerprint'] !== $deviceFingerprint) {
                $db->execute('UPDATE users SET device_fingerprint = ? WHERE id = ?', [$deviceFingerprint, $user['id']]);
            }
        }

        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => '登录已过期，请重新登录']);
    }
} catch (\Exception $e) {
    error_log('Auto login error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => '系统错误']);
}