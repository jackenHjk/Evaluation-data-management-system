<?php
/**
 * 文件名: core/Controller.php
 * 功能描述: 系统基础控制器类
 * 
 * 该类负责:
 * 1. 提供所有控制器的基础功能
 * 2. 数据库连接管理
 * 3. JSON响应生成
 * 4. 用户权限验证
 * 5. 日志记录
 * 
 * 所有业务控制器均继承自该基类，获取统一的功能支持。
 * 
 * 关联文件:
 * - core/Database.php: 数据库操作类，提供数据库连接和操作方法
 * - core/Logger.php: 日志记录类，提供日志记录功能
 * - controllers/: 各业务控制器目录，所有业务控制器均继承自本类
 */

namespace core;

use core\Logger;

class Controller {
    protected $db;
    protected $logger;

    public function __construct() {
        global $db;
        if (!$db) {
            $db = \core\Database::getInstance();
            $GLOBALS['db'] = $db;
        }
        $this->db = $db;
        $this->logger = Logger::getInstance();
    }

    /**
     * 返回JSON响应
     */
    protected function json($data, $code = 200) {
        http_response_code($code);
        header('Content-Type: application/json');
        
        // 记录错误响应的日志
        if ($code >= 400) {
            $this->logger->error('API错误响应', [
                'code' => $code,
                'data' => $data,
                'url' => $_SERVER['REQUEST_URI'],
                'method' => $_SERVER['REQUEST_METHOD'],
                'user_id' => $_SESSION['user_id'] ?? null,
                'role' => $_SESSION['role'] ?? null
            ]);
        }
        
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * 检查模块权限
     */
    protected function checkPermission($module) {
        try {
            // 确保会话已启动
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }

            // 检查是否登录
            if (!isset($_SESSION['user_id'])) {
                $this->logger->warning('未登录用户尝试访问', [
                    'module' => $module,
                    'ip' => $_SERVER['REMOTE_ADDR'],
                    'session_id' => session_id()
                ]);
                return false;
            }

            // 获取用户信息
            $stmt = $this->db->query(
                "SELECT role, status FROM users WHERE id = ?",
                [$_SESSION['user_id']]
            );
            $user = $stmt->fetch();

            if (!$user) {
                $this->logger->warning('用户不存在或会话已过期', [
                    'user_id' => $_SESSION['user_id'],
                    'module' => $module,
                    'session_id' => session_id()
                ]);
                // 清除无效的会话
                session_destroy();
                return false;
            }

            if ($user['status'] != 1) {
                $this->logger->warning('用户已禁用', [
                    'user_id' => $_SESSION['user_id'],
                    'module' => $module,
                    'status' => $user['status'],
                    'session_id' => session_id()
                ]);
                return false;
            }

            // 检查模块权限
            $hasPermission = false;
            switch ($user['role']) {
                case 'admin':
                    $hasPermission = true;
                    break;
                case 'teaching':
                    $hasPermission = in_array($module, ['dashboard', 'students', 'scores', 'analytics', 'grade_analytics', 'settings']);
                    break;
                case 'headteacher':
                    $hasPermission = in_array($module, ['dashboard', 'students', 'analytics', 'grade_analytics']);
                    break;
                case 'marker':
                    $hasPermission = in_array($module, ['dashboard', 'scores', 'analytics', 'grade_analytics']);
                    break;
            }

            if (!$hasPermission) {
                $this->logger->warning('权限不足', [
                    'user_id' => $_SESSION['user_id'],
                    'role' => $user['role'],
                    'module' => $module,
                    'session_id' => session_id()
                ]);
            } else {
                $this->logger->debug('权限检查通过', [
                    'user_id' => $_SESSION['user_id'],
                    'role' => $user['role'],
                    'module' => $module,
                    'session_id' => session_id()
                ]);
            }

            return $hasPermission;
        } catch (\Exception $e) {
            $this->logger->error('权限检查过程发生错误', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'module' => $module,
                'user_id' => $_SESSION['user_id'] ?? null,
                'session_id' => session_id()
            ]);
            return false;
        }
    }

    /**
     * 获取当前用户信息
     */
    protected function getCurrentUser() {
        try {
            if (!isset($_SESSION['user_id'])) {
                return null;
            }

            $stmt = $this->db->query(
                "SELECT id, username, role, real_name, status 
                 FROM users 
                 WHERE id = ?",
                [$_SESSION['user_id']]
            );
            return $stmt->fetch();
        } catch (\Exception $e) {
            $this->logger->error('获取用户信息错误', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => $_SESSION['user_id'] ?? null
            ]);
            return null;
        }
    }

    /**
     * 返回错误响应
     */
    protected function error($message) {
        $this->logger->error($message);
        return $this->json([
            'success' => false,
            'error' => $message
        ], 500);
    }

    /**
     * 返回成功响应
     */
    protected function success($message = '操作成功', $data = null) {
        return $this->json([
            'success' => true,
            'message' => $message,
            'data' => $data
        ]);
    }
} 