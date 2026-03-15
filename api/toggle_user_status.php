<?php
/**
 * 文件名: api/toggle_user_status.php
 * 功能描述: 用户状态切换API处理文件
 * 
 * 该文件负责:
 * 处理用户状态的切换请求（启用/禁用用户账号）
 * 
 * API调用说明:
 * - 直接调用此文件进行用户状态切换
 * - 需要管理员权限
 * - 通过POST参数传递用户ID和目标状态
 * 
 * 关联文件:
 * - controllers/UserController.php: 用户控制器，包含toggleStatus方法
 * - core/Controller.php: 基础控制器
 * - core/Database.php: 数据库操作类
 */

require_once __DIR__ . '/../core/Controller.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../controllers/UserController.php';

// 启动会话
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 实例化控制器并调用方法
$controller = new Controllers\UserController();
$controller->toggleStatus(); 