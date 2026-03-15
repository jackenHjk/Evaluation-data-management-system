<?php
/**
 * 文件名: controllers/LogController.php
 * 功能描述: 操作日志管理控制器
 * 
 * 该控制器负责:
 * 1. 记录系统操作日志
 * 2. 获取和显示操作日志列表
 * 3. 提供日志筛选功能
 * 4. 清理过期日志记录
 * 
 * API调用路由:
 * - log/list: 获取日志列表
 * - log/users: 获取有日志记录的用户列表
 * - log/clean: 清理过期日志
 * - log/add: 添加新的日志记录
 * 
 * 关联文件:
 * - core/Controller.php: 基础控制器
 * - core/Logger.php: 日志记录类
 * - modules/operation_logs.php: 操作日志页面
 * - operation_logs表: 存储操作日志的数据表
 */

/**
 * 操作日志控制器
 * 创建日期: 2024-01-21
 */

namespace Controllers;

use Exception;
use PDO;

class LogController extends \core\Controller {
    public function __construct() {
        parent::__construct();
    }
    
    /**
     * 显示操作日志列表页面
     */
    public function index() {
        // 记录访问日志
        $this->logger->info('访问操作日志管理页面', [
            'user_id' => $_SESSION['user_id'],
            'role' => $_SESSION['role']
        ]);
        
        // 加载操作日志列表页面
        include_once 'modules/operation_logs.php';
    }
    
    /**
     * 获取操作日志列表数据
     */
    public function getList() {
        try {
            $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
            $limit = 50;
            $offset = ($page - 1) * $limit;
            
            // 构建基础查询，确保选择所有必要的字段
            $query = "SELECT 
                        l.id,
                        l.user_id,
                        l.username,
                        l.role,
                        l.action_type,
                        l.action_detail,
                        l.ip_address,
                        l.created_at,
                        u.real_name
                     FROM operation_logs l 
                     LEFT JOIN users u ON l.user_id = u.id 
                     WHERE 1=1";
            $params = [];
            
            // 添加筛选条件
            if (!empty($_GET['user_id'])) {
                $query .= " AND l.user_id = ?";
                $params[] = $_GET['user_id'];
            }
            if (!empty($_GET['role'])) {
                $query .= " AND l.role = ?";
                $params[] = $_GET['role'];
            }
            if (!empty($_GET['action_type'])) {
                $query .= " AND l.action_type = ?";
                $params[] = $_GET['action_type'];
            }
            
            // 获取总记录数
            $countQuery = str_replace("l.id,
                        l.user_id,
                        l.username,
                        l.role,
                        l.action_type,
                        l.action_detail,
                        l.ip_address,
                        l.created_at,
                        u.real_name", "COUNT(*) as total", $query);
            $result = $this->db->query($countQuery, $params);
            $total = $result->fetch()['total'];
            
            // 添加排序和分页
            $query .= " ORDER BY l.created_at DESC LIMIT {$limit} OFFSET {$offset}";
            
            // 执行查询
            $result = $this->db->query($query, $params);
            $logs = $result->fetchAll();
            
            // 计算总页数
            $totalPages = ceil($total / $limit);
            
            $this->json([
                'success' => true,
                'data' => [
                    'logs' => $logs,
                    'pagination' => [
                        'current_page' => $page,
                        'total_pages' => $totalPages,
                        'total_records' => $total,
                        'per_page' => $limit
                    ]
                ]
            ]);
        } catch (Exception $e) {
            $this->logger->error('获取日志列表失败: ' . $e->getMessage());
            $this->json(['success' => false, 'error' => '获取日志列表失败'], 500);
        }
    }
    
    /**
     * 获取用户列表（用于筛选）
     */
    public function getUsers() {
        try {
            $result = $this->db->query("
                SELECT DISTINCT u.id, u.username, u.real_name, u.role
                FROM users u
                INNER JOIN operation_logs l ON u.id = l.user_id
                WHERE u.status = 1
                ORDER BY u.real_name
            ");
            $users = $result->fetchAll();
            
            $this->json(['success' => true, 'data' => $users]);
        } catch (Exception $e) {
            $this->logger->error('获取用户列表失败: ' . $e->getMessage());
            $this->json(['success' => false, 'error' => '获取用户列表失败'], 500);
        }
    }
    
    /**
     * 清理30天前的日志
     */
    public function cleanOldLogs() {
        try {
            // 删除30天前的日志
            $result = $this->db->query("
                DELETE FROM operation_logs 
                WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)
            ");
            
            $affectedRows = $result->rowCount();
            
            // 记录清理操作
            $this->logger->info("清理了 {$affectedRows} 条过期日志", [
                'user_id' => $_SESSION['user_id'],
                'role' => $_SESSION['role'],
                'action_type' => 'clean',
                'action_detail' => "清理了 {$affectedRows} 条过期日志"
            ]);
            
            $this->json([
                'success' => true,
                'message' => "成功清理了 {$affectedRows} 条过期日志",
                'data' => ['affected_rows' => $affectedRows]
            ]);
        } catch (Exception $e) {
            $this->logger->error('清理过期日志失败: ' . $e->getMessage());
            $this->json(['success' => false, 'error' => '清理过期日志失败'], 500);
        }
    }

    /**
     * 添加操作日志
     */
    public function add() {
        try {
            // 获取请求参数
            $actionType = $_POST['action_type'] ?? '';
            $actionDetail = $_POST['action_detail'] ?? '';
            
            if (empty($actionType) || empty($actionDetail)) {
                $this->json(['success' => false, 'error' => '参数不完整'], 400);
                return;
            }

            // 获取用户信息
            $userId = $_SESSION['user_id'] ?? null;
            $role = $_SESSION['role'] ?? null;
            
            if (!$userId || !$role) {
                $this->json(['success' => false, 'error' => '未登录或会话已过期'], 401);
                return;
            }

            // 获取客户端IP
            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '';

            // 插入日志记录
            $stmt = $this->db->query(
                "INSERT INTO operation_logs (
                    user_id, 
                    username,
                    role, 
                    action_type, 
                    action_detail, 
                    ip_address,
                    created_at
                ) VALUES (?, ?, ?, ?, ?, ?, NOW())",
                [
                    $userId, 
                    $_SESSION['username'] ?? '', 
                    $role, 
                    $actionType, 
                    $actionDetail, 
                    $ipAddress
                ]
            );

            if ($stmt->rowCount() > 0) {
                $this->json(['success' => true, 'message' => '日志记录成功']);
            } else {
                throw new Exception('日志记录失败');
            }
        } catch (Exception $e) {
            $this->logger->error('添加日志失败: ' . $e->getMessage());
            $this->json(['success' => false, 'error' => '添加日志失败'], 500);
        }
    }
} 