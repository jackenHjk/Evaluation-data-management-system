<?php
/**
 * 文件名: install/check_db.php
 * 功能描述: 数据库诊断工具
 * 
 * 该文件负责:
 * 1. 检查数据库连接和表结构
 * 2. 诊断用户表和权限表结构
 * 3. 列出当前用户和权限信息
 * 4. 检查外键约束关系
 * 5. 检测并清理孤立的权限记录
 * 
 * 该工具用于系统维护和故障排查，帮助管理员检查数据库状态
 * 并自动修复一些常见的数据一致性问题。
 * 
 * 注意: 该工具应仅供系统管理员使用，包含敏感信息。
 * 
 * 关联文件:
 * - config/config.php: 数据库配置信息
 * - core/Database.php: 数据库操作类
 */

define('BASE_PATH', dirname(__DIR__));
$config = require BASE_PATH . '/config/config.php';
require_once BASE_PATH . '/core/Database.php';

try {
    $db = \Core\Database::getInstance($config['db']);
    
    echo "<h2>数据库诊断</h2>";
    
    // 1. 检查用户表结构
    echo "<h3>1. 用户表结构：</h3>";
    $stmt = $db->query("SHOW CREATE TABLE users");
    $result = $stmt->fetch();
    echo "<pre>" . $result['Create Table'] . "</pre>";
    
    // 2. 检查权限表结构
    echo "<h3>2. 权限表结构：</h3>";
    $stmt = $db->query("SHOW CREATE TABLE user_permissions");
    $result = $stmt->fetch();
    echo "<pre>" . $result['Create Table'] . "</pre>";
    
    // 3. 列出所有用户
    echo "<h3>3. 当前用户列表：</h3>";
    $stmt = $db->query(
        "SELECT id, username, real_name, role, status 
         FROM users 
         ORDER BY id"
    );
    echo "<pre>";
    print_r($stmt->fetchAll());
    echo "</pre>";
    
    // 4. 列出所有权限
    echo "<h3>4. 当前权限列表：</h3>";
    $stmt = $db->query(
        "SELECT * FROM user_permissions 
         ORDER BY user_id, grade_id, subject_id"
    );
    echo "<pre>";
    print_r($stmt->fetchAll());
    echo "</pre>";
    
    // 5. 检查外键约束
    echo "<h3>5. 外键约束检查：</h3>";
    $stmt = $db->query(
        "SELECT 
            TABLE_NAME,
            COLUMN_NAME,
            CONSTRAINT_NAME,
            REFERENCED_TABLE_NAME,
            REFERENCED_COLUMN_NAME
         FROM information_schema.KEY_COLUMN_USAGE
         WHERE 
            TABLE_SCHEMA = ? AND
            REFERENCED_TABLE_NAME IS NOT NULL AND
            (TABLE_NAME = 'user_permissions' OR REFERENCED_TABLE_NAME = 'user_permissions')",
        [$config['db']['dbname']]
    );
    echo "<pre>";
    print_r($stmt->fetchAll());
    echo "</pre>";
    
    // 6. 检查孤立的权限记录
    echo "<h3>6. 检查孤立的权限记录：</h3>";
    $stmt = $db->query(
        "SELECT up.* 
         FROM user_permissions up 
         LEFT JOIN users u ON up.user_id = u.id 
         WHERE u.id IS NULL"
    );
    $orphanedPermissions = $stmt->fetchAll();
    if ($orphanedPermissions) {
        echo "<div style='color:red;'>发现孤立的权限记录：</div>";
        echo "<pre>";
        print_r($orphanedPermissions);
        echo "</pre>";
        
        // 自动清理孤立记录
        $db->query("DELETE FROM user_permissions WHERE user_id NOT IN (SELECT id FROM users)");
        echo "<div style='color:green;'>已清理孤立记录</div>";
    } else {
        echo "<div style='color:green;'>未发现孤立的权限记录</div>";
    }
    
} catch (\Exception $e) {
    echo "<div style='color:red;'>错误：" . $e->getMessage() . "</div>";
} 