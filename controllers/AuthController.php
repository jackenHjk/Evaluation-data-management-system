<?php
/**
 * 文件名: controllers/AuthController.php
 * 功能描述: 用户认证控制器
 * 
 * 该控制器负责:
 * 1. 用户登录验证和会话管理
 * 2. 用户注销功能
 * 3. 获取当前登录用户信息
 * 4. 用户权限验证
 * 5. 用户会话有效性验证
 * 
 * API调用路由:
 * - POST login: 处理用户登录请求
 * - POST logout: 处理用户注销请求
 * - GET auth/current_user: 获取当前登录用户信息
 * - GET check_permission: 检查模块权限
 * 
 * 关联文件:
 * - core/Controller.php: 基础控制器
 * - core/Auth.php: 认证基础类
 * - core/Cache.php: 缓存类
 * - api/routes/login.php: 登录成功后的处理
 * - api/routes/auto_login.php: 自动登录处理
 * - index.php: 主入口文件，使用此控制器的验证功能
 * - login.php: 登录页面，调用此控制器进行登录验证
 */

namespace Controllers;

use core\Controller;
use core\Cache;

class AuthController extends Controller {
    private $cache;
    
    public function __construct() {
        parent::__construct();
        $this->cache = Cache::getInstance();
    }
    
    public function login() {
        try {
            // 获取请求数据
            $username = $_POST['username'] ?? '';
            $password = $_POST['password'] ?? '';
            $deviceFingerprint = $_POST['device_fingerprint'] ?? ''; // 添加设备指纹
            
            if (empty($username) || empty($password)) {
                return $this->json(['error' => '用户名和密码不能为空'], 400);
            }
            
            // 检查登录失败次数
            $failedKey = 'login_failed_' . $username;
            $failedCount = $this->cache->get($failedKey) ?? 0;
            
            if ($failedCount >= 5) {
                return $this->json(['error' => '登录失败次数过多，请15分钟后再试'], 429);
            }
            
            // 验证用户
            $stmt = $this->db->query(
                "SELECT * FROM users WHERE username = ? LIMIT 1",
                [$username]
            );
            $user = $stmt->fetch();
            
            if (!$user) {
                // 增加失败次数
                $this->cache->set($failedKey, $failedCount + 1, 900);
                
                return $this->json(['error' => '用户名或密码错误'], 401);
            }
            
            if ($user['status'] != 1) {
                $this->logger->warning('登录失败：账号已禁用', [
                    'username' => $username,
                    'user_id' => $user['id'],
                    'ip' => $_SERVER['REMOTE_ADDR']
                ]);
                return $this->json(['error' => '该账号已被禁用，请联系管理员'], 403);
            }
            
            if (password_verify($password, $user['password'])) {
                // 添加调试日志
                $this->logger->debug('登录验证信息', [
                    'username' => $username,
                    'user_id' => $user['id'],
                    'login_token' => $user['login_token'],
                    'last_activity' => $user['last_activity'],
                    'current_time' => date('Y-m-d H:i:s'),
                    'ip' => $_SERVER['REMOTE_ADDR'],
                    'device_fingerprint' => $deviceFingerprint
                ]);

                // 检查是否已在其他设备登录
                // 直接查询数据库获取最新的登录状态
                $stmt = $this->db->query(
                    "SELECT login_token, last_activity, device_fingerprint FROM users WHERE id = ? AND login_token IS NOT NULL AND last_activity > DATE_SUB(NOW(), INTERVAL 30 MINUTE)",
                    [$user['id']]
                );
                $activeSession = $stmt->fetch();
                
                error_log(sprintf(
                    "[%s] Checking active session for user %s - Result: %s",
                    date('Y-m-d H:i:s'),
                    $username,
                    $activeSession ? 'Active session found' : 'No active session'
                ));
                
                // 仅当存在活跃会话且设备指纹不匹配时，才拒绝登录
                if ($activeSession && !empty($activeSession['device_fingerprint']) && 
                    !empty($deviceFingerprint) && $activeSession['device_fingerprint'] !== $deviceFingerprint) {
                    $this->logger->warning('用户在其他设备登录', [
                        'username' => $username,
                        'user_id' => $user['id'],
                        'ip' => $_SERVER['REMOTE_ADDR'],
                        'last_activity' => $activeSession['last_activity'],
                        'login_token' => $activeSession['login_token'],
                        'device_fingerprint' => $deviceFingerprint,
                        'active_device_fingerprint' => $activeSession['device_fingerprint']
                    ]);
                    
                    // 返回错误信息，不允许登录
                    return $this->json([
                        'success' => false,
                        'error' => '该账号已在其他设备登录，请先退出后再登录,或30分钟后重试',
                        'code' => 'ALREADY_LOGGED_IN'
                    ], 403);
                }

                // 生成新的登录令牌
                $loginToken = bin2hex(random_bytes(16));
                
                // 更新用户的登录令牌、最后活动时间和设备指纹
                $this->db->query(
                    "UPDATE users SET login_token = ?, last_activity = NOW(), device_fingerprint = ? WHERE id = ?",
                    [$loginToken, $deviceFingerprint, $user['id']]
                );

                // 清除登录失败次数
                $this->cache->delete($failedKey);

                // 设置会话数据
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['real_name'] = $user['real_name'];
                $_SESSION['login_token'] = $loginToken;
                $_SESSION['last_activity_update'] = time();
                $_SESSION['device_fingerprint'] = $deviceFingerprint;
                
                $this->logger->info('登录成功', [
                    'action_type' => 'login',
                    'action_detail' => sprintf('用户 %s(%s) 登录系统', $user['username'], $user['real_name']),
                    'login_token' => $loginToken,
                    'device_fingerprint' => $deviceFingerprint
                ]);

                return $this->json([
                    'success' => true,
                    'login_token' => $loginToken,
                    'username' => $user['username'],
                    'user' => [
                        'id' => $user['id'],
                        'username' => $user['username'],
                        'role' => $user['role']
                    ]
                ]);
            }
            
            $this->logger->warning('登录失败：密码错误', [
                'username' => $username,
                'ip' => $_SERVER['REMOTE_ADDR']
            ]);
            
            // 增加失败次数
            $this->cache->set($failedKey, $failedCount + 1, 900);
            
            return $this->json(['error' => '用户名或密码错误'], 401);
        } catch (\Exception $e) {
            $this->logger->error('登录系统错误', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'username' => $username,
                'ip' => $_SERVER['REMOTE_ADDR']
            ]);
            // DEBUG: 返回具体错误信息以便排查
            return $this->json(['error' => '登录系统错误: ' . $e->getMessage()], 500);
        }
    }
    
    public function logout() {
        $userId = $_SESSION['user_id'] ?? null;
        $username = $_SESSION['username'] ?? null;
        $realName = $_SESSION['real_name'] ?? null;
        
        try {
            if ($userId) {
                // 清除数据库中的登录令牌
                $this->db->query(
                    "UPDATE users SET login_token = NULL WHERE id = ?",
                    [$userId]
                );
                
                // 清除用户缓存
                if ($username) {
                    $this->cache->delete('user_' . $username);
                }
            }
            
            // 记录操作日志
            if ($userId && $username) {
                $this->logger->info('注销', [
                    'action_type' => 'logout',
                    'action_detail' => sprintf('用户 %s(%s) 退出系统', $username, $realName),
                    'logout_type' => isset($_SERVER['HTTP_ACCEPT']) ? 'manual' : 'auto'  // 区分手动退出还是自动退出
                ]);
            }
            
            // 如果是 beacon 请求，检查是否是刷新操作
            if (!isset($_SERVER['HTTP_ACCEPT'])) {
                $isRefresh = isset($_SERVER['HTTP_CACHE_CONTROL']) && 
                             (strpos($_SERVER['HTTP_CACHE_CONTROL'], 'max-age=0') !== false ||
                              strpos($_SERVER['HTTP_CACHE_CONTROL'], 'no-cache') !== false);
                
                if ($isRefresh) {
                    return null;
                }
            }
            
            session_destroy();

            // 如果是 beacon 请求，直接返回
            if (!isset($_SERVER['HTTP_ACCEPT'])) {
                return null;
            }
            
            return $this->json(['success' => true]);
        } catch (\Exception $e) {
            $this->logger->error('注销失败', [
                'error' => $e->getMessage(),
                'user_id' => $userId
            ]);

            // 如果是 beacon 请求，直接返回
            if (!isset($_SERVER['HTTP_ACCEPT'])) {
                return null;
            }
            
            return $this->json(['error' => '注销失败，请稍后重试'], 500);
        }
    }
    
    public function getCurrentUser() {
        $userId = $_SESSION['user_id'] ?? null;
        $username = $_SESSION['username'] ?? null;
        
        if (!$userId || !$username) {
            return $this->json(['error' => '未登录'], 401);
        }

        try {
            // 尝试从缓存获取用户信息
            $cacheKey = 'user_' . $username;
            $user = $this->cache->get($cacheKey);
            
            if (!$user) {
            $stmt = $this->db->query(
                    "SELECT id, username, real_name, role, status FROM users WHERE id = ? LIMIT 1",
                    [$userId]
            );
            $user = $stmt->fetch();

                if ($user) {
                    // 缓存用户信息，有效期30分钟
                    $this->cache->set($cacheKey, $user, 1800);
                }
            }
            
            if (!$user || $user['status'] != 1) {
                session_destroy();
                return $this->json(['error' => '用户状态异常'], 401);
            }

            return $this->json([
                'success' => true,
                'user' => [
                    'id' => $user['id'],
                    'username' => $user['username'],
                    'real_name' => $user['real_name'],
                    'role' => $user['role']
                ]
            ]);
        } catch (\Exception $e) {
            $this->logger->error('获取当前用户信息失败', [
                'error' => $e->getMessage(),
                'user_id' => $userId
            ]);
            return $this->json(['error' => '获取用户信息失败'], 500);
        }
    }
    
    public function checkModulePermission() {
        try {
            // 确保会话已启动
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }

            $module = $_GET['module'] ?? '';
            if (empty($module)) {
                $this->logger->warning('检查模块权限失败：未指定模块', [
                    'user_id' => $_SESSION['user_id'] ?? null,
                    'ip' => $_SERVER['REMOTE_ADDR'],
                    'session_id' => session_id()
                ]);
                return $this->json(['error' => '未指定模块'], 400);
            }

            // 检查是否登录
            if (!isset($_SESSION['user_id'])) {
                $this->logger->warning('未登录用户尝试访问模块', [
                    'module' => $module,
                    'ip' => $_SERVER['REMOTE_ADDR'],
                    'session_id' => session_id()
                ]);
                return $this->json(['error' => '未登录'], 401);
            }

            $hasPermission = $this->checkPermission($module);
            
            if (!$hasPermission) {
                $this->logger->warning('模块访问被拒绝', [
                    'user_id' => $_SESSION['user_id'] ?? null,
                    'role' => $_SESSION['role'] ?? null,
                    'module' => $module,
                    'ip' => $_SERVER['REMOTE_ADDR'],
                    'session_id' => session_id()
                ]);
                return $this->json(['error' => '无权访问此模块'], 403);
            }

            $this->logger->debug('模块访问权限验证通过', [
                'user_id' => $_SESSION['user_id'],
                'role' => $_SESSION['role'] ?? null,
                'module' => $module,
                'ip' => $_SERVER['REMOTE_ADDR'],
                'session_id' => session_id()
            ]);
            
            return $this->json(['success' => true]);
        } catch (\Exception $e) {
            $this->logger->error('检查模块权限时发生错误', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'module' => $module ?? null,
                'user_id' => $_SESSION['user_id'] ?? null,
                'session_id' => session_id()
            ]);
            return $this->json(['error' => '检查权限失败：' . $e->getMessage()], 500);
        }
    }
    
    /**
     * 检查模块权限
     * @param string $module 模块名称
     * @return bool
     */
    protected function checkPermission($module) {
        try {
            // 确保会话已启动
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }

            // 检查是否登录
            if (!isset($_SESSION['user_id'])) {
                return false;
            }

            // 获取用户角色
            $userRole = $_SESSION['role'] ?? 'teacher';

            // 定义模块权限
            $modulePermissions = [
                'download' => ['admin', 'teaching', 'headteacher'],
                'dashboard' => ['admin', 'teaching', 'headteacher', 'marker'],
                'settings' => ['admin', 'teaching'],
                'students' => ['admin', 'teaching', 'headteacher'],
                'scores' => ['admin', 'teaching', 'headteacher', 'marker'],
                'analytics' => ['admin','teaching', 'headteacher', 'marker']
            ];

            // 检查模块是否存在
            if (!isset($modulePermissions[$module])) {
                return true;  // 如果模块未定义权限，默认允许访问
            }

            // 检查用户是否有权限
            return in_array($userRole, $modulePermissions[$module]);
        } catch (\Exception $e) {
            $this->logger->error('权限检查错误', [
                'error' => $e->getMessage(),
                'module' => $module,
                'user_id' => $_SESSION['user_id'] ?? null
            ]);
            return false;
        }
    }

    /**
     * 检查模块权限的API接口
     */
    public function checkModuleAccess() {
        try {
            $module = $_GET['module'] ?? '';
            if (empty($module)) {
                return ['success' => false, 'error' => '未指定模块'];
            }

            if ($this->checkPermission($module)) {
                return ['success' => true];
            }

            return ['success' => false, 'error' => '权限不足'];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function validateSession() {
        if (!isset($_SESSION['user_id']) || !isset($_SESSION['login_token'])) {
            return false;
        }

        try {
            $stmt = $this->db->query(
                "SELECT login_token, last_activity, device_fingerprint FROM users WHERE id = ? AND status = 1",
                [$_SESSION['user_id']]
            );
            $user = $stmt->fetch();

            if (!$user || empty($user['login_token']) || $user['login_token'] != $_SESSION['login_token']) {
                return false;
            }

            // 检查最后活动时间
            $lastActivityTime = strtotime($user['last_activity']);
            $currentTime = time();
            
            // 如果超过30分钟没有活动，session失效
            if (($currentTime - $lastActivityTime) > 1800) {
                return false;
            }

            // 检查设备指纹（如果存在）
            $sessionDeviceFingerprint = $_SESSION['device_fingerprint'] ?? '';
            $userDeviceFingerprint = $user['device_fingerprint'] ?? '';
            
            // 如果数据库中有设备指纹，但与会话中的不匹配，则可能是会话被劫持
            if (!empty($userDeviceFingerprint) && !empty($sessionDeviceFingerprint) && 
                $userDeviceFingerprint !== $sessionDeviceFingerprint) {
                $this->logger->warning('设备指纹不匹配，可能的会话劫持', [
                    'user_id' => $_SESSION['user_id'],
                    'session_fingerprint' => $sessionDeviceFingerprint,
                    'db_fingerprint' => $userDeviceFingerprint,
                    'ip' => $_SERVER['REMOTE_ADDR']
                ]);
                return false;
            }

            // 更新最后活动时间（每5分钟更新一次）
            if (!isset($_SESSION['last_activity_update']) || ($currentTime - $_SESSION['last_activity_update']) > 300) {
                $this->db->query(
                    "UPDATE users SET last_activity = NOW() WHERE id = ?",
                    [$_SESSION['user_id']]
                );
                $_SESSION['last_activity_update'] = $currentTime;
            }

            return true;
        } catch (\Exception $e) {
            $this->logger->error('验证会话失败', [
                'error' => $e->getMessage(),
                'user_id' => $_SESSION['user_id']
            ]);
            return false;
        }
    }
}