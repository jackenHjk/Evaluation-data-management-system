<?php
// 检查用户是否登录
session_start();
if (!isset($_SESSION['user_id'])) {
    header('HTTP/1.1 403 Forbidden');
    exit('访问被拒绝');
}

// 包含模板生成器
require_once __DIR__ . '/grade_class_template.php'; 