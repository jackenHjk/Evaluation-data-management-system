<?php
/**
 * 文件名: core/Auth.php
 * 功能描述: 用户认证类
 * 
 * 该类负责:
 * 1. 用户登录验证
 * 2. 用户会话管理
 * 3. 获取当前登录用户信息
 * 4. 用户状态管理
 * 
 * 使用单例模式，全系统统一使用同一认证实例。
 * 提供登录、登出和验证等基本功能，保障系统安全。
 * 
 * API调用说明:
 * - 不直接通过API调用，由controllers/AuthController.php调用
 * 
 * 关联文件:
 * - core/Database.php: 数据库操作类，用于用户数据查询
 * - controllers/AuthController.php: 认证控制器，调用Auth类进行用户认证
 * - api/index.php: API入口文件，调用AuthController处理登录请求
 */

namespace Core;

class Auth {
    private static $instance = null;
    
    private function __construct() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function login($username, $password) {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare('SELECT * FROM users WHERE username = ?');
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password'])) {
            if ($user['status'] == 0) {
                throw new \Exception('该账号已被禁用');
            }
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            return true;
        }
        return false;
    }
    
    public function logout() {
        session_destroy();
    }
    
    public function isLoggedIn() {
        return isset($_SESSION['user_id']);
    }
    
    public function getCurrentUser() {
        if (!$this->isLoggedIn()) {
            return null;
        }
        
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare('SELECT * FROM users WHERE id = ?');
        $stmt->execute([$_SESSION['user_id']]);
        return $stmt->fetch();
    }
    
    public function toggleUserStatus($user_id) {
        $db = Database::getInstance()->getConnection();
        
        $stmt = $db->prepare('SELECT role, status FROM users WHERE id = ?');
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
        
        if ($user['role'] === 'admin' && $user['status'] == 1) {
            $stmt = $db->prepare('SELECT COUNT(*) as admin_count FROM users WHERE role = "admin" AND status = 1');
            $stmt->execute();
            $result = $stmt->fetch();
            if ($result['admin_count'] <= 1) {
                throw new \Exception('系统必须保留至少一个可用的管理员账号');
            }
        }
        
        $stmt = $db->prepare('UPDATE users SET status = NOT status WHERE id = ?');
        return $stmt->execute([$user_id]);
    }
} 