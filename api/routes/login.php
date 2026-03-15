<?php
/**
 * 文件名: api/routes/login.php
 * 功能描述: 登录处理路由
 * 
 * 该文件负责:
 * 1. 生成新的登录令牌
 * 2. 更新用户的登录令牌和最后活动时间
 * 3. 将登录令牌保存在用户会话中
 * 4. 返回登录成功的响应，包含必要的用户信息和token
 * 
 * 注意：此文件仅执行登录成功后的操作，应由其他控制器调用
 * 
 * API调用关系:
 * - 由controllers/AuthController.php的login方法调用
 * - 接收已验证的用户ID和用户名
 * 
 * 关联文件:
 * - controllers/AuthController.php: 认证控制器，验证用户并调用此文件
 * - api/index.php: API入口文件，路由登录请求
 * - api/routes/auto_login.php: 自动登录处理路由，使用此处生成的token
 */

// 生成新的登录令牌
$loginToken = bin2hex(random_bytes(16));

// 更新用户的登录令牌和最后活动时间
$stmt = $conn->prepare("UPDATE users SET login_token = ?, last_activity = NOW() WHERE id = ?");
$stmt->bind_param("si", $loginToken, $userId);
$stmt->execute();

// 在session中保存登录令牌
$_SESSION['login_token'] = $loginToken;
$_SESSION['last_activity_update'] = time();

// 返回成功响应，包含必要的用户信息和token
echo json_encode([
    'success' => true,
    'message' => '登录成功',
    'login_token' => $loginToken,
    'username' => $username,
    'role' => $_SESSION['role'],
    'real_name' => $_SESSION['real_name']
]);