<?php
/**
 * 文件名: controllers/UserController.php
 * 功能描述: 用户管理控制器
 * 
 * 该控制器负责:
 * 1. 用户信息的增删改查
 * 2. 用户权限管理
 * 3. 用户状态切换
 * 4. 用户认证与权限验证
 * 
 * API调用路由:
 * - user/list: 获取用户列表
 * - user/get: 获取用户详情
 * - user/add: 添加新用户
 * - user/update: 更新用户信息
 * - user/delete: 删除用户
 * - user/permissions: 获取用户权限
 * - user/toggle_status: 切换用户状态
 * 
 * 关联文件:
 * - core/Controller.php: 基础控制器
 * - core/Database.php: 数据库操作类
 * - api/toggle_user_status.php: 用户状态切换独立接口
 */

namespace Controllers;

use Core\Controller;
use Core\Database;

class UserController extends Controller {
    protected $routes = [
        'list' => 'getUserList',
        'get' => 'getUser',
        'add' => 'add',
        'update' => 'updateUser',
        'delete' => 'deleteUser',
        'permissions' => 'getUserPermissions',
        'permissions/update' => 'updatePermissions',
        'toggle_status' => 'toggleStatus',
        'update_profile' => 'updateProfile',
        'batch_delete' => 'batchDelete',
        'batch_import' => 'batchImport',
        'check_initial_password' => 'checkInitialPassword'
    ];

    public function __construct() {
        parent::__construct();
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (!isset($_SESSION['user_id'])) {
            $this->logger->warning('未登录用户尝试访问用户管理', [
                'ip' => $_SERVER['REMOTE_ADDR']
            ]);
            $this->json(['error' => '未登录'], 401);
            exit;
        }

        // 获取当前请求的路由
        $route = isset($_GET['route']) ? explode('/', $_GET['route']) : [];
        $action = $route[1] ?? '';
        
        // 如果是更新个人资料或检查初始密码，不需要settings权限
        if ($action === 'update_profile' || $action === 'check_initial_password') {
            return;
        }

        if (!$this->checkPermission('settings')) {
            $this->logger->warning('用户权限不足', [
                'user_id' => $_SESSION['user_id'],
                'role' => $_SESSION['role'] ?? null,
                'ip' => $_SERVER['REMOTE_ADDR']
            ]);
            $this->json(['error' => '无权访问'], 403);
            exit;
        }
    }

    public function getUserList() {
        try {
            $this->logger->debug('获取用户列表', [
                'user_id' => $_SESSION['user_id']
            ]);

            $stmt = $this->db->query(
                "SELECT id, username, real_name, role, status, created_at 
                 FROM users 
                 ORDER BY role, username"
            );
            $users = $stmt->fetchAll();

            return $this->json([
                'success' => true,
                'data' => $users
            ]);
        } catch (\Exception $e) {
            $this->logger->error('获取用户列表失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => $_SESSION['user_id']
            ]);
            return $this->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getUser() {
        try {
            $userId = $_GET['id'] ?? '';
            if (empty($userId)) {
                $this->logger->warning('获取用户信息失败：未指定用户ID', [
                    'user_id' => $_SESSION['user_id']
                ]);
                return $this->json(['error' => '参数不完整'], 400);
            }

            $this->logger->debug('获取用户信息', [
                'target_user_id' => $userId,
                'user_id' => $_SESSION['user_id']
            ]);

            $stmt = $this->db->query(
                "SELECT id, username, real_name, role, status, created_at 
                 FROM users 
                 WHERE id = ?",
                [$userId]
            );
            $user = $stmt->fetch();

            if (!$user) {
                $this->logger->warning('获取用户信息失败：用户不存在', [
                    'target_user_id' => $userId,
                    'user_id' => $_SESSION['user_id']
                ]);
                return $this->json(['error' => '用户不存在'], 404);
            }

            return $this->json([
                'success' => true,
                'data' => $user
            ]);
        } catch (\Exception $e) {
            $this->logger->error('获取用户信息失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'target_user_id' => $_GET['id'] ?? null,
                'user_id' => $_SESSION['user_id']
            ]);
            return $this->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getUserPermissions() {
        try {
            $userId = $_GET['user_id'] ?? '';
            if (empty($userId)) {
                $this->logger->warning('获取用户权限失败：未指定用户ID', [
                    'user_id' => $_SESSION['user_id']
                ]);
                return $this->json(['error' => '参数不完整'], 400);
            }

            $this->logger->debug('获取用户权限', [
                'target_user_id' => $userId,
                'user_id' => $_SESSION['user_id']
            ]);

            // 获取用户角色
            $stmt = $this->db->query(
                "SELECT role FROM users WHERE id = ?",
                [$userId]
            );
            $user = $stmt->fetch();

            if (!$user) {
                $this->logger->warning('获取用户权限失败：用户不存在', [
                    'target_user_id' => $userId,
                    'user_id' => $_SESSION['user_id']
                ]);
                return $this->json(['error' => '用户不存在'], 404);
            }

            // 获取当前项目ID
            $settingStmt = $this->db->query(
                "SELECT id FROM settings WHERE status = 1 LIMIT 1"
            );
            $currentSetting = $settingStmt->fetch();
            $currentSettingId = $currentSetting ? $currentSetting['id'] : 0;
            
            // 指定项目ID参数，允许查询特定项目的权限
            $specifiedProjectId = $_GET['project_id'] ?? null;
            $settingId = $specifiedProjectId ?: $currentSettingId;
            
            $this->logger->debug('查询权限配置', [
                'settingId' => $settingId,
                'currentSettingId' => $currentSettingId,
                'specifiedProjectId' => $specifiedProjectId
            ]);
            
            // 获取用户权限，根据指定的项目ID或当前活动项目ID筛选
            $stmt = $this->db->query(
                "SELECT up.*, g.grade_name, s.subject_name 
                 FROM user_permissions up 
                 LEFT JOIN grades g ON up.grade_id = g.id 
                 LEFT JOIN subjects s ON up.subject_id = s.id 
                 WHERE up.user_id = ? AND up.setting_id = ?",
                [$userId, $settingId]
            );
            $permissions = $stmt->fetchAll();

            return $this->json([
                'success' => true,
                'data' => $permissions,
                'role' => $user['role'],
                'current_setting_id' => $currentSettingId,
                'queried_setting_id' => $settingId
            ]);
        } catch (\Exception $e) {
            $this->logger->error('获取用户权限失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'target_user_id' => $_GET['user_id'] ?? null,
                'user_id' => $_SESSION['user_id']
            ]);
            return $this->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function add() {
        try {
            // 检查权限
            if (!$this->checkPermission('settings')) {
                return $this->json(['error' => '无权访问'], 403);
            }

            $username = $_POST['username'] ?? '';
            $password = $_POST['password'] ?? '';
            $realName = $_POST['real_name'] ?? '';
            $role = $_POST['role'] ?? '';
            $permissions = isset($_POST['permissions']) ? json_decode($_POST['permissions'], true) : [];

            if (empty($username) || empty($password) || empty($realName) || empty($role)) {
                return $this->json(['error' => '请填写完整信息'], 400);
            }

            // 检查用户名是否已存在
            $stmt = $this->db->query(
                "SELECT COUNT(*) as count FROM users WHERE username = ?",
                [$username]
            );
            if ($stmt->fetch()['count'] > 0) {
                return $this->json(['error' => '用户名已存在'], 400);
            }

            // 获取当前项目ID
            $stmt = $this->db->query("SELECT id FROM settings WHERE status = 1 ORDER BY id DESC LIMIT 1");
            $setting = $stmt->fetch();
            if (!$setting) {
                return $this->json(['error' => '未找到可用的项目'], 400);
            }
            $defaultSettingId = $setting['id'];

            // 开始事务
            $this->db->beginTransaction();

            try {
                // 插入用户
                $this->db->execute(
                    "INSERT INTO users (username, password, real_name, role, status, created_at) 
                     VALUES (?, ?, ?, ?, 1, NOW())",
                    [$username, password_hash($password, PASSWORD_DEFAULT), $realName, $role]
                );
                $userId = $this->db->lastInsertId();
                
                if (!$userId) {
                    $this->db->rollBack();
                    throw new \Exception("创建用户失败");
                }

                // 添加用户权限
                $permissionLogs = [];
                
                if ($role === 'teaching') {
                    // 教导处角色自动加载所有权限
                    $grades = $this->db->query("SELECT id, grade_name FROM grades WHERE status = 1")->fetchAll();
                    $subjects = $this->db->query("SELECT id, subject_name FROM subjects WHERE status = 1")->fetchAll();
                    
                    foreach ($grades as $grade) {
                        $this->db->query(
                            "INSERT INTO user_permissions 
                             (user_id, grade_id, subject_id, setting_id, can_edit, can_download, can_edit_students, created_at) 
                             VALUES (?, ?, NULL, ?, 1, 1, 1, NOW())",
                            [$userId, $grade['id'], $defaultSettingId]
                        );
                        $permissionLogs[] = "{$grade['grade_name']} ";
                        
                        foreach ($subjects as $subject) {
                            $this->db->query(
                                "INSERT INTO user_permissions 
                                 (user_id, grade_id, subject_id, setting_id, can_edit, can_download, can_edit_students, created_at) 
                                 VALUES (?, ?, ?, ?, 1, 1, 0, NOW())",
                                [$userId, $grade['id'], $subject['id'], $defaultSettingId]
                            );
                            $permissionLogs[] = "{$grade['grade_name']} - {$subject['subject_name']}";
                        }
                    }
                } else if ($role === 'headteacher' || $role === 'marker') {
                    // 处理班主任和数据录入员的权限
                    foreach ($permissions as $permission) {
                        $gradeId = $permission['grade_id'] ?? null;
                        $subjectId = $permission['subject_id'] ?? null;
                        $permSettingId = $permission['setting_id'] ?? $defaultSettingId;
                        
                        if ($gradeId) {
                            // 获取年级名称
                            $gradeStmt = $this->db->query("SELECT grade_name FROM grades WHERE id = ?", [$gradeId]);
                            $grade = $gradeStmt->fetch();
                            $gradeName = $grade ? $grade['grade_name'] : '未知年级';

                            if ($role === 'headteacher') {
                                $this->db->query(
                                    "INSERT INTO user_permissions 
                                     (user_id, grade_id, subject_id, setting_id, can_edit, can_download, can_edit_students, created_at) 
                                     VALUES (?, ?, NULL, ?, 1, 1, 1, NOW())",
                                    [$userId, $gradeId, $permSettingId]
                                );
                                $permissionLogs[] = "{$gradeName}";
                            } else if ($role === 'marker' && $subjectId) {
                                // 获取科目名称
                                $subjectStmt = $this->db->query("SELECT subject_name FROM subjects WHERE id = ?", [$subjectId]);
                                $subject = $subjectStmt->fetch();
                                $subjectName = $subject ? $subject['subject_name'] : '未知科目';

                                $this->db->query(
                                    "INSERT INTO user_permissions 
                                     (user_id, grade_id, subject_id, setting_id, can_edit, can_download, can_edit_students, created_at) 
                                     VALUES (?, ?, ?, ?, 1, 1, 0, NOW())",
                                    [$userId, $gradeId, $subjectId, $permSettingId]
                                );
                                $permissionLogs[] = "{$gradeName} - {$subjectName} ";
                            }
                        }
                    }
                }

                $this->db->commit();

                // 记录详细的操作日志
                $roleNames = [
                    'admin' => '管理员',
                    'teaching' => '教导处',
                    'headteacher' => '班主任',
                    'marker' => '数据录入员'
                ];
                $roleName = $roleNames[$role] ?? $role;
                
                $logDetail = sprintf(
                    "创建用户：%s（%s），角色：%s\n权限设置：\n%s",
                    $username,
                    $realName,
                    $roleName,
                    implode("\n", $permissionLogs)
                );

                $this->logger->info($logDetail, [
                    'action_type' => 'add',
                    'action_detail' => $logDetail
                ]);

                return $this->json([
                    'success' => true,
                    'message' => '添加成功',
                    'data' => [
                        'id' => $userId,
                        'username' => $username,
                        'real_name' => $realName,
                        'role' => $role
                    ]
                ]);
            } catch (\Exception $e) {
                $this->db->rollBack();
                throw $e;
            }
        } catch (\Exception $e) {
            error_log("Error in UserController::add: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            return $this->json([
                'success' => false,
                'error' => '添加用户失败：' . $e->getMessage()
            ], 500);
        }
    }

    public function updateUser() {
        try {
            // 验证必填字段
            $userId = $_POST['id'] ?? '';
            $username = $_POST['username'] ?? '';
            $realName = $_POST['real_name'] ?? '';
            $role = $_POST['role'] ?? '';
            $permissions = json_decode($_POST['permissions'] ?? '[]', true);
            $password = $_POST['password'] ?? '';

            if (empty($userId) || empty($username) || empty($realName) || empty($role)) {
                $this->logger->warning('更新用户失败：参数不完整', [
                    'user_id' => $_SESSION['user_id']
                ]);
                return $this->json(['error' => '请填写完整信息'], 400);
            }

            // 获取当前项目ID
            $stmt = $this->db->query("SELECT id FROM settings WHERE status = 1 ORDER BY id DESC LIMIT 1");
            $setting = $stmt->fetch();
            if (!$setting) {
                return $this->json(['error' => '未找到可用的项目'], 400);
            }
            $settingId = $setting['id'];

            // 获取用户原有信息
            $stmt = $this->db->query(
                "SELECT username, real_name, role FROM users WHERE id = ?",
                [$userId]
            );
            $oldUser = $stmt->fetch();
            if (!$oldUser) {
                $this->logger->warning('更新用户失败：用户不存在', [
                    'target_user_id' => $userId,
                    'user_id' => $_SESSION['user_id']
                ]);
                return $this->json(['error' => '用户不存在'], 404);
            }

            // 开始事务
            $this->db->beginTransaction();

            try {
                // 更新用户基本信息
                if (!empty($password)) {
                    $this->db->query(
                        "UPDATE users SET 
                            username = ?, 
                            real_name = ?, 
                            password = ?, 
                            role = ? 
                        WHERE id = ?",
                        [$username, $realName, password_hash($password, PASSWORD_DEFAULT), $role, $userId]
                    );
                } else {
                    $this->db->query(
                        "UPDATE users SET 
                            username = ?, 
                            real_name = ?, 
                            role = ? 
                        WHERE id = ?",
                        [$username, $realName, $role, $userId]
                    );
                }

                // 获取原有权限
                $oldPermissions = [];
                $stmt = $this->db->query(
                    "SELECT up.*, g.grade_name, s.subject_name 
                     FROM user_permissions up 
                     LEFT JOIN grades g ON up.grade_id = g.id 
                     LEFT JOIN subjects s ON up.subject_id = s.id 
                     WHERE up.user_id = ?",
                    [$userId]
                );
                while ($row = $stmt->fetch()) {
                    if ($row['subject_id']) {
                        $oldPermissions[] = sprintf("%s - %s", $row['grade_name'], $row['subject_name']);
                    } else {
                        $oldPermissions[] = $row['grade_name'];
                    }
                }

                // 获取当前项目ID - 用于确保至少有一个setting_id可用
                $settingStmt = $this->db->query(
                    "SELECT id FROM settings WHERE status = 1 ORDER BY id DESC LIMIT 1"
                );
                $setting = $settingStmt->fetch();
                $defaultSettingId = $setting ? $setting['id'] : 0;

                if (!$defaultSettingId) {
                    throw new \Exception('未找到可用的项目');
                }

                // 不再全局删除用户权限，而是根据每个权限的setting_id删除
                // 先收集permission中的所有setting_id
                $settingIds = [];
                foreach ($permissions as $permission) {
                    if (isset($permission['setting_id']) && !in_array($permission['setting_id'], $settingIds)) {
                        $settingIds[] = $permission['setting_id'];
                    }
                }
                
                // 如果前端没有提供setting_id，使用默认值
                if (empty($settingIds)) {
                    $settingIds[] = $defaultSettingId;
                }
                
                $this->logger->debug('更新权限的项目IDs:', $settingIds);
                
                // 针对各个项目ID删除旧权限
                foreach ($settingIds as $settingId) {
                    $this->db->query(
                        "DELETE FROM user_permissions WHERE user_id = ? AND setting_id = ?",
                        [$userId, $settingId]
                    );
                }

                // 插入新的权限并记录
                $newPermissions = [];
                if (!empty($permissions)) {
                    foreach ($permissions as $permission) {
                        $gradeId = $permission['grade_id'] ?? null;
                        $subjectId = $permission['subject_id'] ?? null;
                        $permSettingId = $permission['setting_id'] ?? $defaultSettingId;
                        
                        if ($gradeId) {
                            // 获取年级名称
                            $gradeStmt = $this->db->query("SELECT grade_name FROM grades WHERE id = ?", [$gradeId]);
                            $grade = $gradeStmt->fetch();
                            $gradeName = $grade ? $grade['grade_name'] : '未知年级';

                            if ($role === 'headteacher') {
                                $this->db->query(
                                    "INSERT INTO user_permissions 
                                     (user_id, grade_id, subject_id, setting_id, can_edit, can_download, can_edit_students, created_at) 
                                     VALUES (?, ?, NULL, ?, 1, 1, 1, NOW())",
                                    [$userId, $gradeId, $permSettingId]
                                );
                                $newPermissions[] = $gradeName;
                            } else if ($role === 'marker' && $subjectId) {
                                // 获取科目名称
                                $subjectStmt = $this->db->query("SELECT subject_name FROM subjects WHERE id = ?", [$subjectId]);
                                $subject = $subjectStmt->fetch();
                                $subjectName = $subject ? $subject['subject_name'] : '未知科目';

                                $this->db->query(
                                    "INSERT INTO user_permissions 
                                     (user_id, grade_id, subject_id, setting_id, can_edit, can_download, can_edit_students, created_at) 
                                     VALUES (?, ?, ?, ?, 1, 1, 0, NOW())",
                                    [$userId, $gradeId, $subjectId, $permSettingId]
                                );
                                $newPermissions[] = sprintf("%s - %s", $gradeName, $subjectName);
                            }
                        }
                    }
                } else if ($role === 'teaching') {
                    // 如果是教导处角色，自动添加所有权限
                    $grades = $this->db->query("SELECT id, grade_name FROM grades WHERE status = 1")->fetchAll();
                    $subjects = $this->db->query("SELECT id, subject_name FROM subjects WHERE status = 1")->fetchAll();
                    
                    foreach ($grades as $grade) {
                        $this->db->query(
                            "INSERT INTO user_permissions 
                             (user_id, grade_id, subject_id, setting_id, can_edit, can_download, can_edit_students, created_at) 
                             VALUES (?, ?, NULL, ?, 1, 1, 1, NOW())",
                            [$userId, $grade['id'], $settingId]
                        );
                        $newPermissions[] = $grade['grade_name'];
                        
                        foreach ($subjects as $subject) {
                            $this->db->query(
                                "INSERT INTO user_permissions 
                                 (user_id, grade_id, subject_id, setting_id, can_edit, can_download, can_edit_students, created_at) 
                                 VALUES (?, ?, ?, ?, 1, 1, 0, NOW())",
                                [$userId, $grade['id'], $subject['id'], $settingId]
                            );
                            $newPermissions[] = sprintf("%s - %s", $grade['grade_name'], $subject['subject_name']);
                        }
                    }
                }

                $this->db->commit();

                // 记录详细的操作日志
                $roleNames = [
                    'admin' => '管理员',
                    'teaching' => '教导处',
                    'headteacher' => '班主任',
                    'marker' => '数据录入员'
                ];
                $oldRoleName = $roleNames[$oldUser['role']] ?? $oldUser['role'];
                $newRoleName = $roleNames[$role] ?? $role;

                $changes = [];
                if ($username !== $oldUser['username']) {
                    $changes[] = sprintf("用户名：%s -> %s", $oldUser['username'], $username);
                }
                if ($realName !== $oldUser['real_name']) {
                    $changes[] = sprintf("姓名：%s -> %s", $oldUser['real_name'], $realName);
                }
                if ($role !== $oldUser['role']) {
                    $changes[] = sprintf("角色：%s -> %s", $oldRoleName, $newRoleName);
                }
                if (!empty($password)) {
                    $changes[] = "修改了密码";
                }

                // 如果没有任何基本信息变更，但有权限变更
                if (empty($changes) && ($oldPermissions != $newPermissions)) {
                    $changes[] = "仅修改了权限设置";
                }

                $logDetail = sprintf(
                    "编辑用户：%s（%s）\n" .
                    "当前设置：角色 %s，真实姓名 %s\n" .
                    "权限：%s\n",
                    $username,
                    $oldUser['username'],
                    $oldUser['role'],
                    $oldUser['real_name'],
                    implode(' | ', $oldPermissions)
                );

                // 如果有修改内容，添加到日志中
                if (!empty($changes)) {
                    $logDetail .= "<span style='color: #1890ff'>修改内容：" . implode(" | ", $changes) . "</span>";
                } else {
                    $logDetail .= "<span style='color: #52c41a'>无修改内容</span>";
                }

                $this->logger->info($logDetail, [
                    'action_type' => 'edit',
                    'action_detail' => $logDetail
                ]);

                return $this->json([
                    'success' => true,
                    'message' => '更新成功',
                    'data' => [
                        'id' => $userId,
                        'username' => $username,
                        'real_name' => $realName,
                        'role' => $role
                    ]
                ]);
            } catch (\Exception $e) {
                $this->db->rollBack();
                throw $e;
            }
        } catch (\Exception $e) {
            $this->logger->error('更新用户失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'target_user_id' => $_POST['id'] ?? null,
                'user_id' => $_SESSION['user_id']
            ]);
            return $this->json([
                'success' => false,
                'error' => '系统错误，请查看错误日志了解详情'
            ], 500);
        }
    }

    public function deleteUser() {
        try {
            $userId = $_POST['id'] ?? '';
            
            if (empty($userId)) {
                $this->logger->warning('删除用户失败：未指定用户ID', [
                    'user_id' => $_SESSION['user_id']
                ]);
                return $this->json(['error' => '参数不完整'], 400);
            }

            // 获取用户信息和权限信息
            $stmt = $this->db->query(
                "SELECT u.*, GROUP_CONCAT(
                    DISTINCT CONCAT(
                        COALESCE(g.grade_name, ''),
                        CASE 
                            WHEN up.subject_id IS NULL THEN ' - 学生信息管理权限'
                            ELSE CONCAT(' - ', s.subject_name, ' - 成绩管理权限')
                        END
                    ) SEPARATOR '\n'
                ) as permissions
                FROM users u
                LEFT JOIN user_permissions up ON u.id = up.user_id
                LEFT JOIN grades g ON up.grade_id = g.id
                LEFT JOIN subjects s ON up.subject_id = s.id
                WHERE u.id = ?
                GROUP BY u.id",
                [$userId]
            );
            $user = $stmt->fetch();
            
            if (!$user) {
                $this->logger->warning('删除用户失败：用户不存在', [
                    'target_user_id' => $userId,
                    'user_id' => $_SESSION['user_id']
                ]);
                return $this->json(['error' => '用户不存在'], 404);
            }
            
            // 检查是否是系统默认的admin账户
            if ($user['username'] === 'admin') {
                $this->logger->warning('删除用户失败：不能删除系统默认admin账户', [
                    'target_user_id' => $userId,
                    'user_id' => $_SESSION['user_id']
                ]);
                return $this->json(['error' => '不能删除系统默认admin账户'], 403);
            }

            // 开始事务
            $this->db->beginTransaction();

            try {
                // 删除用户权限
                $this->db->query(
                    "DELETE FROM user_permissions WHERE user_id = ?",
                    [$userId]
                );

                // 删除用户
                $this->db->query(
                    "DELETE FROM users WHERE id = ?",
                    [$userId]
                );

                $this->db->commit();

                // 记录详细的操作日志
                $roleNames = [
                    'admin' => '管理员',
                    'teaching' => '教导处',
                    'headteacher' => '班主任',
                    'marker' => '数据录入员'
                ];
                $roleName = $roleNames[$user['role']] ?? $user['role'];

                $logDetail = sprintf(
                    "删除用户：%s（%s），角色：%s\n被删除的权限：\n%s",
                    $user['username'],
                    $user['real_name'],
                    $roleName,
                    $user['permissions'] ?? '无权限'
                );

                $this->logger->info($logDetail, [
                    'action_type' => 'delete',
                    'action_detail' => $logDetail
                ]);

                return $this->json([
                    'success' => true,
                    'message' => '删除成功'
                ]);
            } catch (\Exception $e) {
                $this->db->rollBack();
                throw $e;
            }
        } catch (\Exception $e) {
            $this->logger->error('删除用户失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'target_user_id' => $_POST['id'] ?? null,
                'user_id' => $_SESSION['user_id']
            ]);
            return $this->json([
                'success' => false,
                'error' => '系统错误，请查看错误日志了解详情'
            ], 500);
        }
    }

    public function toggleStatus() {
        try {
            $userId = $_POST['id'] ?? '';
            
            if (empty($userId)) {
                $this->logger->warning('切换用户状态失败：未指定用户ID', [
                    'user_id' => $_SESSION['user_id']
                ]);
                return $this->json(['error' => '参数不完整'], 400);
            }

            // 获取用户完整信息
            $stmt = $this->db->query(
                "SELECT u.*, GROUP_CONCAT(
                    DISTINCT CONCAT(
                        COALESCE(g.grade_name, ''),
                        CASE 
                            WHEN up.subject_id IS NULL THEN ' - 学生信息管理权限'
                            ELSE CONCAT(' - ', s.subject_name, ' - 成绩管理权限')
                        END
                    ) SEPARATOR '\n'
                ) as permissions
                FROM users u
                LEFT JOIN user_permissions up ON u.id = up.user_id
                LEFT JOIN grades g ON up.grade_id = g.id
                LEFT JOIN subjects s ON up.subject_id = s.id
                WHERE u.id = ?
                GROUP BY u.id",
                [$userId]
            );
            $user = $stmt->fetch();
            
            if (!$user) {
                $this->logger->warning('切换用户状态失败：用户不存在', [
                    'target_user_id' => $userId,
                    'user_id' => $_SESSION['user_id']
                ]);
                return $this->json(['error' => '用户不存在'], 404);
            }

            // 切换状态
            $newStatus = $user['status'] == 1 ? 0 : 1;
            
            $this->db->query(
                "UPDATE users SET status = ? WHERE id = ?",
                [$newStatus, $userId]
            );

            // 记录详细的操作日志
            $roleNames = [
                'admin' => '管理员',
                'teaching' => '教导处',
                'headteacher' => '班主任',
                'marker' => '数据录入员'
            ];
            $roleName = $roleNames[$user['role']] ?? $user['role'];
            $action = $newStatus == 1 ? '启用' : '禁用';

            $logDetail = sprintf(
                "%s用户：%s（%s），角色：%s\n用户权限：\n%s",
                $action,
                $user['username'],
                $user['real_name'],
                $roleName,
                $user['permissions'] ?? '无权限'
            );

            $this->logger->info($logDetail, [
                'action_type' => 'edit',
                'action_detail' => $logDetail
            ]);

            return $this->json([
                'success' => true,
                'message' => '状态更新成功',
                'data' => [
                    'status' => $newStatus
                ]
            ]);
        } catch (\Exception $e) {
            $this->logger->error('切换用户状态失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'target_user_id' => $_POST['id'] ?? null,
                'user_id' => $_SESSION['user_id']
            ]);
            return $this->json([
                'success' => false,
                'error' => '系统错误，请查看错误日志了解详情'
            ], 500);
        }
    }

    public function batchToggleStatus() {
        try {
            $userIds = $_POST['ids'] ?? [];
            $status = isset($_POST['status']) ? (int)$_POST['status'] : null;
            
            if (empty($userIds) || !is_array($userIds) || !in_array($status, [0, 1])) {
                $this->logger->warning('批量切换用户状态失败：参数不完整或无效', [
                    'user_id' => $_SESSION['user_id'],
                    'ids' => $userIds,
                    'status' => $status
                ]);
                return $this->json(['error' => '参数不完整或无效'], 400);
            }

            // 开始事务
            $this->db->beginTransaction();

            try {
                // 获取要更新的用户信息
                $placeholders = str_repeat('?,', count($userIds) - 1) . '?';
                $stmt = $this->db->query(
                    "SELECT u.*, GROUP_CONCAT(
                        DISTINCT CONCAT(
                            COALESCE(g.grade_name, ''),
                            CASE 
                                WHEN up.subject_id IS NULL THEN ' - 学生信息管理权限'
                                ELSE CONCAT(' - ', s.subject_name, ' - 成绩管理权限')
                            END
                        ) SEPARATOR '\n'
                    ) as permissions
                    FROM users u
                    LEFT JOIN user_permissions up ON u.id = up.user_id
                    LEFT JOIN grades g ON up.grade_id = g.id
                    LEFT JOIN subjects s ON up.subject_id = s.id
                    WHERE u.id IN ($placeholders)
                    GROUP BY u.id",
                    $userIds
                );
                $users = $stmt->fetchAll();

                // 检查是否会禁用最后一个管理员
                if ($status === 0) {
                    $adminCount = $this->db->query(
                        "SELECT COUNT(*) as count FROM users WHERE role = 'admin' AND status = 1"
                    )->fetch()['count'];
                    
                    $affectedAdmins = array_filter($users, function($user) {
                        return $user['role'] === 'admin' && $user['status'] === 1;
                    });
                    
                    if ($adminCount === count($affectedAdmins)) {
                        $this->db->rollBack();
                        return $this->json(['error' => '不能禁用最后一个管理员账号'], 400);
                    }
                }

                // 更新用户状态
                $this->db->query(
                    "UPDATE users SET status = ? WHERE id IN ($placeholders)",
                    array_merge([$status], $userIds)
                );

                // 记录操作日志
                $roleNames = [
                    'admin' => '管理员',
                    'teaching' => '教导处',
                    'headteacher' => '班主任',
                    'marker' => '数据录入员'
                ];
                $action = $status == 1 ? '启用' : '禁用';
                
                $logDetails = [];
                foreach ($users as $user) {
                    $roleName = $roleNames[$user['role']] ?? $user['role'];
                    $logDetails[] = sprintf(
                        "%s（%s）- %s",
                        $user['username'],
                        $user['real_name'],
                        $roleName
                    );
                }

                $logDetail = sprintf(
                    "批量%s用户：\n%s",
                    $action,
                    implode("\n", $logDetails)
                );

                $this->logger->info($logDetail, [
                    'action_type' => 'batch_edit',
                    'action_detail' => $logDetail
                ]);

                $this->db->commit();

                return $this->json([
                    'success' => true,
                    'message' => sprintf('批量%s成功', $action),
                    'data' => [
                        'affected_count' => count($users)
                    ]
                ]);
            } catch (\Exception $e) {
                $this->db->rollBack();
                throw $e;
            }
        } catch (\Exception $e) {
            $this->logger->error('批量切换用户状态失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_ids' => $_POST['ids'] ?? null,
                'status' => $_POST['status'] ?? null,
                'user_id' => $_SESSION['user_id']
            ]);
            return $this->json([
                'success' => false,
                'error' => '系统错误，请查看错误日志了解详情'
            ], 500);
        }
    }
    
    /**
     * 批量删除用户
     * 
     * 该方法允许管理员批量删除多个用户
     * 会同时删除用户权限
     * 
     * @return array 操作结果的JSON响应
     */
    public function batchDelete() {
        try {
            $userIds = $_POST['ids'] ?? [];
            
            if (empty($userIds) || !is_array($userIds)) {
                $this->logger->warning('批量删除用户失败：参数不完整或无效', [
                    'user_id' => $_SESSION['user_id'],
                    'ids' => $userIds
                ]);
                return $this->json(['error' => '参数不完整或无效'], 400);
            }

            // 获取当前登录用户ID
            $currentUserId = $_SESSION['user_id'] ?? 0;

            // 检查是否包含当前登录用户
            if (in_array($currentUserId, $userIds)) {
                $this->logger->warning('批量删除用户失败：不能删除当前登录用户', [
                    'user_id' => $_SESSION['user_id'],
                    'ids' => $userIds
                ]);
                return $this->json(['error' => '不能删除当前登录的账号'], 400);
            }

            // 开始事务
            $this->db->beginTransaction();

            try {
                // 获取要删除的用户信息
                $placeholders = str_repeat('?,', count($userIds) - 1) . '?';
                $stmt = $this->db->query(
                    "SELECT u.*, GROUP_CONCAT(
                        DISTINCT CONCAT(
                            COALESCE(g.grade_name, ''),
                            CASE 
                                WHEN up.subject_id IS NULL THEN ' - 学生信息管理权限'
                                ELSE CONCAT(' - ', s.subject_name, ' - 成绩管理权限')
                            END
                        ) SEPARATOR '\n'
                    ) as permissions
                    FROM users u
                    LEFT JOIN user_permissions up ON u.id = up.user_id
                    LEFT JOIN grades g ON up.grade_id = g.id
                    LEFT JOIN subjects s ON up.subject_id = s.id
                    WHERE u.id IN ($placeholders)
                    GROUP BY u.id",
                    $userIds
                );
                $users = $stmt->fetchAll();

                // 检查是否包含系统默认的admin账户
                $adminUsernames = array_column(array_filter($users, function($user) {
                    return $user['username'] === 'admin';
                }), 'username');
                
                if (!empty($adminUsernames)) {
                    $this->db->rollBack();
                    return $this->json(['error' => '不能删除系统默认admin账户'], 403);
                }
                
                // 检查是否会删除所有管理员
                $adminsToDelete = array_filter($users, function($user) {
                    return $user['role'] === 'admin' && $user['status'] === 1;
                });
                
                if (!empty($adminsToDelete)) {
                    $adminCount = $this->db->query(
                        "SELECT COUNT(*) as count FROM users WHERE role = 'admin' AND status = 1"
                    )->fetch()['count'];
                    
                    if ($adminCount === count($adminsToDelete)) {
                        $this->db->rollBack();
                        return $this->json(['error' => '不能删除最后一个管理员账号'], 400);
                    }
                }

                // 先删除用户权限
                $this->db->query(
                    "DELETE FROM user_permissions WHERE user_id IN ($placeholders)",
                    $userIds
                );

                // 删除用户
                $this->db->query(
                    "DELETE FROM users WHERE id IN ($placeholders)",
                    $userIds
                );

                // 记录操作日志
                $roleNames = [
                    'admin' => '管理员',
                    'teaching' => '教导处',
                    'headteacher' => '班主任',
                    'marker' => '数据录入员'
                ];
                
                $logDetails = [];
                foreach ($users as $user) {
                    $roleName = $roleNames[$user['role']] ?? $user['role'];
                    $logDetails[] = sprintf(
                        "%s（%s）- %s",
                        $user['username'],
                        $user['real_name'],
                        $roleName
                    );
                }

                $logDetail = sprintf(
                    "批量删除用户：\n%s",
                    implode("\n", $logDetails)
                );

                $this->logger->info($logDetail, [
                    'action_type' => 'batch_delete',
                    'action_detail' => $logDetail
                ]);

                $this->db->commit();

                return $this->json([
                    'success' => true,
                    'message' => '批量删除成功',
                    'data' => [
                        'affected_count' => count($users)
                    ]
                ]);
            } catch (\Exception $e) {
                $this->db->rollBack();
                throw $e;
            }
        } catch (\Exception $e) {
            $this->logger->error('批量删除用户失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_ids' => $_POST['ids'] ?? null,
                'user_id' => $_SESSION['user_id']
            ]);
            return $this->json([
                'success' => false,
                'error' => '系统错误，请查看错误日志了解详情'
            ], 500);
        }
    }
    
    /**
     * 批量导入用户
     * 
     * 该方法处理Excel文件中的用户数据，批量创建用户账号
     * 支持不同角色的用户创建，并根据角色分配相应权限
     * 
     * @return array 导入结果的JSON响应
     */
    public function batchImport() {
        try {
            // 检查是否有文件上传
            if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
                return $this->json(['error' => '文件上传失败或未选择文件'], 400);
            }
            
            // 获取当前项目ID
            $settingId = $_POST['setting_id'] ?? null;
            if (!$settingId) {
                return $this->json(['error' => '未指定项目ID'], 400);
            }

            // 检查文件类型
            $fileName = $_FILES['file']['name'];
            $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            
            if (!in_array($fileExt, ['xlsx', 'xls'])) {
                return $this->json(['error' => '仅支持Excel文件格式（.xlsx, .xls）'], 400);
            }

            // 创建临时文件目录（如果不存在）
            $tempDir = __DIR__ . '/../temp';
            if (!is_dir($tempDir)) {
                mkdir($tempDir, 0755, true);
            }
            
            // 生成唯一的文件名并移动上传的文件到temp目录
            $uniqueFileName = uniqid('import_') . '.' . $fileExt;
            $tempFilePath = $tempDir . '/' . $uniqueFileName;
            
            if (!move_uploaded_file($_FILES['file']['tmp_name'], $tempFilePath)) {
                return $this->json(['error' => '无法保存上传的文件'], 400);
            }
            
            // 使用PhpSpreadsheet库读取Excel文件
            require_once __DIR__ . '/../vendor/autoload.php';
            
            try {
                $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($tempFilePath);
                $reader->setReadDataOnly(true);
                $spreadsheet = $reader->load($tempFilePath);
                $worksheet = $spreadsheet->getActiveSheet();
                $data = $worksheet->toArray();
                
                // 处理完成后删除临时文件
                @unlink($tempFilePath);
                
                // 记录文件基本信息
                $this->logger->debug('批量导入用户 - 文件基本信息', [
                    'total_rows' => count($data),
                    'user_id' => $_SESSION['user_id']
                ]);
                
                // 检查文件是否有足够的行数
                if (count($data) < 2) {
                    return $this->json(['error' => '文件内容为空或格式不正确'], 400);
                }
                
                // 智能查找表头行（在前15行中查找包含"用户名"的行）
                $headerRowIndex = -1;
                for ($i = 0; $i < min(15, count($data)); $i++) {
                    $possibleHeaders = array_map(function($cell) {
                        return is_string($cell) ? strtolower(trim($cell)) : '';
                    }, $data[$i]);
                    
                    // 检查这一行是否包含"用户名"、"姓名"、"角色代码"等关键词
                    if (in_array('用户名', $possibleHeaders) || 
                        in_array('用户名*', $possibleHeaders) ||
                        (in_array('姓名', $possibleHeaders) && in_array('角色代码', $possibleHeaders))) {
                        $headerRowIndex = $i;
                        break;
                    }
                }
                
                if ($headerRowIndex === -1) {
                    $this->logger->warning('批量导入用户 - 未找到表头行', [
                        'user_id' => $_SESSION['user_id']
                    ]);
                    return $this->json(['error' => '无法识别表头行，请确保Excel文件包含正确的表头'], 400);
                }
                
                // 获取表头
                $headers = array_map(function($header) {
                    if (is_null($header)) return '';
                    // 移除星号和其他特殊字符，只保留基本列名
                    return trim(preg_replace('/[*]/', '', strtolower($header)));
                }, $data[$headerRowIndex]);
                
                // 记录表头处理日志
                $this->logger->debug('批量导入用户 - 处理表头', [
                    'header_row_index' => $headerRowIndex,
                    'raw_headers' => $data[$headerRowIndex],
                    'processed_headers' => $headers,
                    'user_id' => $_SESSION['user_id']
                ]);
                
                // 检查必要的列是否存在
                $requiredColumns = ['用户名', '姓名', '角色代码'];
                $missingColumns = [];
                foreach ($requiredColumns as $column) {
                    if (!in_array($column, $headers)) {
                        $missingColumns[] = $column;
                    }
                }
                
                if (!empty($missingColumns)) {
                    $errorMsg = "缺少必要的列：" . implode(", ", $missingColumns);
                    $this->logger->warning('批量导入用户 - 缺少必要列', [
                        'missing_columns' => $missingColumns,
                        'headers' => $headers,
                        'user_id' => $_SESSION['user_id']
                    ]);
                    return $this->json(['error' => $errorMsg], 400);
                }
                
                // 获取列索引
                $usernameIdx = array_search('用户名', $headers);
                $realNameIdx = array_search('姓名', $headers);
                $roleCodeIdx = array_search('角色代码', $headers);
                $gradeCodeIdx = array_search('年段代码', $headers);
                $subjectCodeIdx = array_search('学科代码', $headers);
                
                // 从表头的下一行开始处理数据
                $userData = array_slice($data, $headerRowIndex + 1);
                
                // 开始事务
                $this->db->beginTransaction();
                
                try {
                    $successCount = 0;
                    $errorRows = [];
                    $defaultPassword = password_hash('123456', PASSWORD_DEFAULT);
                    $validUsers = []; // 存储验证通过的用户数据
                    
                    // 获取角色映射
                    $roleMapping = [
                        '0' => 'admin',
                        '1' => 'teaching',
                        '2' => 'headteacher',
                        '3' => 'marker'
                    ];
                    
                    // 获取年段和学科映射
                    $gradeCodeMapping = $this->getGradeCodeMapping();
                    $subjectCodeMapping = $this->getSubjectCodeMapping($settingId);
                    
                    // 第一阶段：验证所有数据
                    foreach ($userData as $rowIdx => $row) {
                        $rowNum = $headerRowIndex + 2 + $rowIdx; // Excel行号（表头行+1，再加上当前索引）
                        
                        // 跳过空行
                        if (empty($row[$usernameIdx]) && empty($row[$realNameIdx])) {
                            continue;
                        }
                        
                        // 1. 检查用户名是否已存在
                        $username = trim($row[$usernameIdx] ?? '');
                        if (empty($username)) {
                            $errorRows[] = "第{$rowNum}行：用户名不能为空";
                            continue;
                        }
                        
                        $existingUser = $this->db->query(
                            "SELECT id FROM users WHERE username = ?",
                            [$username]
                        )->fetch();
                        
                        if ($existingUser) {
                            $errorRows[] = "第{$rowNum}行：用户名 '{$username}' 已存在";
                            continue;
                        }
                        
                        // 2. 检查姓名是否为空
                        $realName = trim($row[$realNameIdx] ?? '');
                        if (empty($realName)) {
                            $errorRows[] = "第{$rowNum}行：姓名不能为空";
                            continue;
                        }
                        
                        // 3. 检查角色代码是否为空或不在规定范围内
                        $roleCode = trim((string)($row[$roleCodeIdx] ?? ''));
                        
                        // 特别处理角色代码为0的情况
                        if ($roleCode === '0') {
                            $role = 'admin';
                        } else if (isset($roleMapping[$roleCode])) {
                            $role = $roleMapping[$roleCode];
                        } else {
                            $errorRows[] = "第{$rowNum}行：无效的角色代码 '{$roleCode}'，应为0-3之间的数字";
                            continue;
                        }
                        
                        // 准备权限数据
                        $permissions = [];
                        
                        // 4. 根据角色代码处理年段代码和学科代码
                        if ($roleCode === '0' || $roleCode === '1') {
                            // 管理员和教务老师不需要年段代码和学科代码
                            // 自动忽略这两列的值
                        } else if ($roleCode === '2') {
                            // 班主任需要年段代码，忽略学科代码
                            if ($gradeCodeIdx === false || empty($row[$gradeCodeIdx])) {
                                $errorRows[] = "第{$rowNum}行：班主任角色需要提供年段代码";
                                continue;
                            }
                            
                            $gradeCode = trim((string)$row[$gradeCodeIdx]);
                            
                            if (!isset($gradeCodeMapping[$gradeCode])) {
                                $errorRows[] = "第{$rowNum}行：无效的年段代码 '{$gradeCode}'";
                                continue;
                            }
                            
                            $gradeId = $gradeCodeMapping[$gradeCode];
                            
                            // 添加班主任权限
                            $permissions[] = [
                                'grade_id' => $gradeId,
                                'subject_id' => null,
                                'setting_id' => $settingId,
                                'can_edit' => 1,
                                'can_download' => 1,
                                'can_edit_students' => 1
                            ];
                        } else if ($roleCode === '3') {
                            // 阅卷老师需要同时提供年段代码和学科代码
                            if ($gradeCodeIdx === false || empty($row[$gradeCodeIdx])) {
                                $errorRows[] = "第{$rowNum}行：阅卷老师角色需要提供年段代码";
                                continue;
                            }
                            
                            if ($subjectCodeIdx === false || empty($row[$subjectCodeIdx])) {
                                $errorRows[] = "第{$rowNum}行：阅卷老师角色需要提供学科代码";
                                continue;
                            }
                            
                            $gradeCode = trim((string)$row[$gradeCodeIdx]);
                            $subjectCode = trim((string)$row[$subjectCodeIdx]);
                            
                            if (!isset($gradeCodeMapping[$gradeCode])) {
                                $errorRows[] = "第{$rowNum}行：无效的年段代码 '{$gradeCode}'";
                                continue;
                            }
                            
                            if (!isset($subjectCodeMapping[$subjectCode])) {
                                $errorRows[] = "第{$rowNum}行：无效的学科代码 '{$subjectCode}'";
                                continue;
                            }
                            
                            $gradeId = $gradeCodeMapping[$gradeCode];
                            $subjectInfo = $subjectCodeMapping[$subjectCode];
                            
                            // 检查该学科是否适用于指定年段
                            if (!in_array($gradeId, $subjectInfo['grade_ids'])) {
                                $errorRows[] = "第{$rowNum}行：学科代码 '{$subjectCode}' 不适用于年段代码 '{$gradeCode}'";
                                continue;
                            }
                            
                            // 添加阅卷老师权限（只添加指定的年段和学科组合）
                            $permissions[] = [
                                'grade_id' => $gradeId,
                                'subject_id' => $subjectInfo['id'],
                                'setting_id' => $settingId,
                                'can_edit' => 1,
                                'can_download' => 1,
                                'can_edit_students' => 0
                            ];
                        }
                        
                        // 验证通过，保存用户数据
                        $validUsers[] = [
                            'username' => $username,
                            'real_name' => $realName,
                            'role' => $role,
                            'permissions' => $permissions
                        ];
                    }
                    
                    // 如果有任何错误，回滚事务并返回错误信息
                    if (!empty($errorRows)) {
                        $this->db->rollBack();
                        
                        $errorMessage = "导入失败，所有数据必须符合要求才能导入。存在以下错误：<ul class=\"import-error-list\">";
                        foreach ($errorRows as $error) {
                            $errorMessage .= "<li class=\"import-error-item\">{$error}</li>";
                        }
                        $errorMessage .= "</ul>";
                        
                        return $this->json([
                            'success' => false,
                            'error' => $errorMessage,
                            'isHtml' => true
                        ]);
                    }
                    
                    // 如果没有有效用户数据，回滚事务并返回错误信息
                    if (empty($validUsers)) {
                        $this->db->rollBack();
                        return $this->json([
                            'success' => false,
                            'error' => '导入失败，没有有效的用户数据',
                            'isHtml' => true
                        ]);
                    }
                    
                    // 第二阶段：所有数据验证通过，开始导入
                    foreach ($validUsers as $user) {
                        // 创建用户
                        $this->db->execute(
                            "INSERT INTO users (username, password, real_name, role, status, created_at) 
                            VALUES (?, ?, ?, ?, 1, NOW())",
                            [$user['username'], $defaultPassword, $user['real_name'], $user['role']]
                        );
                        $userId = $this->db->lastInsertId();
                        
                        if (!$userId) {
                            // 如果创建用户失败，回滚整个事务
                            $this->db->rollBack();
                            return $this->json([
                                'success' => false,
                                'error' => '导入失败，创建用户时发生错误',
                                'isHtml' => true
                            ]);
                        }
                        
                        // 保存权限
                        foreach ($user['permissions'] as $perm) {
                            $this->db->execute(
                                "INSERT INTO user_permissions 
                                (user_id, grade_id, subject_id, setting_id, can_edit, can_download, can_edit_students)
                                VALUES (?, ?, ?, ?, ?, ?, ?)",
                                [
                                    $userId,
                                    $perm['grade_id'],
                                    $perm['subject_id'],
                                    $perm['setting_id'],
                                    $perm['can_edit'],
                                    $perm['can_download'],
                                    $perm['can_edit_students']
                                ]
                            );
                        }
                        
                        $successCount++;
                    }
                    
                    $this->db->commit();
                    
                    // 记录操作日志
                    $this->logger->info("批量导入用户：成功导入 {$successCount} 个用户", [
                        'action_type' => 'batch_import',
                        'user_id' => $_SESSION['user_id'],
                        'success_count' => $successCount
                    ]);
                    
                    return $this->json([
                        'success' => true,
                        'message' => "成功导入 {$successCount} 个用户",
                        'isHtml' => true,
                        'data' => [
                            'success_count' => $successCount,
                            'error_count' => 0
                        ]
                    ]);
                } catch (\Exception $e) {
                    $this->db->rollBack();
                    throw $e;
                }
            } catch (\PhpOffice\PhpSpreadsheet\Reader\Exception $e) {
                // 确保删除临时文件
                @unlink($tempFilePath);
                return $this->json(['error' => '无法读取Excel文件：' . $e->getMessage()], 400);
            }
        } catch (\Exception $e) {
            $this->logger->error('批量导入用户失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => $_SESSION['user_id']
            ]);
            return $this->json([
                'success' => false,
                'error' => '系统错误，请查看错误日志了解详情'
            ], 500);
        }
    }
    
    /**
     * 获取年段代码映射
     * 
     * @return array 年段代码到ID的映射
     */
    private function getGradeCodeMapping() {
        $grades = $this->db->query("SELECT id, grade_code FROM grades")->fetchAll();
        $mapping = [];
        
        foreach ($grades as $grade) {
            $mapping[$grade['grade_code']] = $grade['id'];
        }
        
        return $mapping;
    }
    
    /**
     * 获取学科代码映射
     * 
     * @param int $settingId 项目ID
     * @return array 学科代码到ID和年级ID的映射
     */
    private function getSubjectCodeMapping($settingId) {
        $subjects = $this->db->query(
            "SELECT s.id, s.subject_code, sg.grade_id
            FROM subjects s
            JOIN subject_grades sg ON s.id = sg.subject_id
            WHERE s.setting_id = ?",
            [$settingId]
        )->fetchAll();
        
        $mapping = [];
        
        foreach ($subjects as $subject) {
            if (!isset($mapping[$subject['subject_code']])) {
                $mapping[$subject['subject_code']] = [
                    'id' => $subject['id'],
                    'grade_ids' => []
                ];
            }
            
            $mapping[$subject['subject_code']]['grade_ids'][] = $subject['grade_id'];
        }
        
        return $mapping;
    }
    
    /**
     * 更新用户个人信息
     * 
     * 该方法允许用户更新自己的真实姓名和密码
     * 仅需要当前已登录的用户，无需管理员权限
     * 
     * @return array 更新结果的JSON响应
     */
    public function updateProfile() {
        try {
            // 确认已登录
            if (!isset($_SESSION['user_id'])) {
                return $this->json(['error' => '未登录'], 401);
            }

            // 确保更新的是当前用户的信息
            $userId = $_SESSION['user_id'];
            
            // 验证参数
            $realName = $_POST['real_name'] ?? '';
            $oldPassword = $_POST['old_password'] ?? '';
            $newPassword = $_POST['new_password'] ?? '';
            
            // 获取用户信息
            $stmt = $this->db->query(
                "SELECT username, real_name, password FROM users WHERE id = ?",
                [$userId]
            );
            $user = $stmt->fetch();
            
            if (!$user) {
                $this->logger->warning('更新个人信息失败：用户不存在', [
                    'user_id' => $userId
                ]);
                return $this->json(['error' => '用户不存在'], 404);
            }
            
            // 开始更新操作
            $this->db->beginTransaction();
            
            try {
                $changes = [];
                
                // 如果提供了新的真实姓名且与原姓名不同，则更新
                if (!empty($realName) && $realName !== $user['real_name']) {
                    $this->db->query(
                        "UPDATE users SET real_name = ? WHERE id = ?",
                        [$realName, $userId]
                    );
                    $changes[] = "姓名";
                    
                    // 更新会话中的真实姓名
                    $_SESSION['real_name'] = $realName;
                }
                
                // 如果提供了旧密码和新密码，则更新密码
                if (!empty($oldPassword) && !empty($newPassword)) {
                    // 验证旧密码
                    if (!password_verify($oldPassword, $user['password'])) {
                        $this->db->rollBack();
                        return $this->json(['error' => '旧密码不正确'], 400);
                    }
                    
                    // 更新密码
                    $this->db->query(
                        "UPDATE users SET password = ? WHERE id = ?",
                        [password_hash($newPassword, PASSWORD_DEFAULT), $userId]
                    );
                    $changes[] = "密码";
                }
                
                // 如果没有任何更改，返回成功但提示无修改
                if (empty($changes)) {
                    $this->db->rollBack();
                    return $this->json([
                        'success' => true,
                        'message' => '未进行任何修改'
                    ]);
                }
                
                // 提交事务
                $this->db->commit();
                
                // 记录操作日志
                $logDetail = sprintf(
                    "用户 %s 更新了个人信息：%s",
                    $user['username'],
                    implode('、', $changes)
                );
                
                $this->logger->info($logDetail, [
                    'action_type' => 'edit_profile',
                    'action_detail' => $logDetail,
                    'user_id' => $userId
                ]);
                
                return $this->json([
                    'success' => true,
                    'message' => '更新成功',
                    'data' => [
                        'real_name' => $realName ?: $user['real_name']
                    ]
                ]);
                
            } catch (\Exception $e) {
                $this->db->rollBack();
                throw $e;
            }
        } catch (\Exception $e) {
            $this->logger->error('更新个人信息失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => $_SESSION['user_id'] ?? null
            ]);
            return $this->json([
                'success' => false,
                'error' => '系统错误，请稍后重试'
            ], 500);
        }
    }

    /**
     * 更新用户权限
     * 此方法专门用于单独更新用户权限
     */
    public function updatePermissions() {
        try {
            // 检查必要参数
            $userId = $_POST['user_id'] ?? '';
            $role = $_POST['role'] ?? '';
            $permissionsJson = $_POST['permissions'] ?? '[]';
            
            if (empty($userId) || empty($role)) {
                $this->logger->warning('更新用户权限失败：参数不完整', [
                    'user_id' => $_SESSION['user_id'],
                    'target_user_id' => $userId,
                    'role' => $role
                ]);
                return $this->json(['error' => '请提供完整的用户ID和角色信息'], 400);
            }
            
            // 解析权限数据
            $permissions = json_decode($permissionsJson, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->logger->warning('更新用户权限失败：权限数据格式错误', [
                    'user_id' => $_SESSION['user_id'],
                    'json_error' => json_last_error_msg(),
                    'permissions_data' => $permissionsJson
                ]);
                return $this->json(['error' => '权限数据格式错误：' . json_last_error_msg()], 400);
            }
            
            $this->logger->debug('准备更新权限', [
                'user_id' => $userId,
                'role' => $role,
                'permissions' => $permissions
            ]);
            
            // 获取用户信息
            $stmt = $this->db->query(
                "SELECT username, real_name, role FROM users WHERE id = ?",
                [$userId]
            );
            $user = $stmt->fetch();
            if (!$user) {
                $this->logger->warning('更新用户权限失败：用户不存在', [
                    'target_user_id' => $userId,
                    'user_id' => $_SESSION['user_id']
                ]);
                return $this->json(['error' => '用户不存在'], 404);
            }
            
            // 获取当前项目ID
            $stmt = $this->db->query("SELECT id FROM settings WHERE status = 1 ORDER BY id DESC LIMIT 1");
            $setting = $stmt->fetch();
            if (!$setting) {
                return $this->json(['error' => '未找到可用的项目'], 400);
            }
            $defaultSettingId = $setting['id'];
            
            // 开始事务
            $this->db->beginTransaction();
            
            try {
                // 获取原有权限
                $oldPermissions = [];
                $stmt = $this->db->query(
                    "SELECT up.*, g.grade_name, s.subject_name 
                     FROM user_permissions up 
                     LEFT JOIN grades g ON up.grade_id = g.id 
                     LEFT JOIN subjects s ON up.subject_id = s.id 
                     WHERE up.user_id = ?",
                    [$userId]
                );
                while ($row = $stmt->fetch()) {
                    if ($row['subject_id']) {
                        $oldPermissions[] = sprintf("%s - %s", $row['grade_name'], $row['subject_name']);
                    } else {
                        $oldPermissions[] = $row['grade_name'];
                    }
                }
                
                // 收集permission中的所有setting_id
                $settingIds = [];
                foreach ($permissions as $permission) {
                    if (isset($permission['setting_id']) && !in_array($permission['setting_id'], $settingIds)) {
                        $settingIds[] = $permission['setting_id'];
                    }
                }
                
                // 如果前端没有提供setting_id，使用默认值
                if (empty($settingIds)) {
                    $settingIds[] = $defaultSettingId;
                }
                
                $this->logger->debug('更新权限的项目IDs:', $settingIds);
                
                // 针对各个项目ID删除旧权限
                foreach ($settingIds as $settingId) {
                    $this->db->query(
                        "DELETE FROM user_permissions WHERE user_id = ? AND setting_id = ?",
                        [$userId, $settingId]
                    );
                }
                
                // 插入新的权限并记录
                $newPermissions = [];
                if (!empty($permissions)) {
                    foreach ($permissions as $permission) {
                        $gradeId = $permission['grade_id'] ?? null;
                        $subjectId = $permission['subject_id'] ?? null;
                        $permSettingId = $permission['setting_id'] ?? $defaultSettingId;
                        
                        if ($gradeId) {
                            // 获取年级名称
                            $gradeStmt = $this->db->query("SELECT grade_name FROM grades WHERE id = ?", [$gradeId]);
                            $grade = $gradeStmt->fetch();
                            $gradeName = $grade ? $grade['grade_name'] : '未知年级';

                            if ($role === 'headteacher') {
                                $this->db->query(
                                    "INSERT INTO user_permissions 
                                     (user_id, grade_id, subject_id, setting_id, can_edit, can_download, can_edit_students, created_at) 
                                     VALUES (?, ?, NULL, ?, 1, 1, 1, NOW())",
                                    [$userId, $gradeId, $permSettingId]
                                );
                                $newPermissions[] = $gradeName;
                            } else if ($role === 'marker' && $subjectId) {
                                // 获取科目名称
                                $subjectStmt = $this->db->query("SELECT subject_name FROM subjects WHERE id = ?", [$subjectId]);
                                $subject = $subjectStmt->fetch();
                                $subjectName = $subject ? $subject['subject_name'] : '未知科目';

                                $this->db->query(
                                    "INSERT INTO user_permissions 
                                     (user_id, grade_id, subject_id, setting_id, can_edit, can_download, can_edit_students, created_at) 
                                     VALUES (?, ?, ?, ?, 1, 1, 0, NOW())",
                                    [$userId, $gradeId, $subjectId, $permSettingId]
                                );
                                $newPermissions[] = sprintf("%s - %s", $gradeName, $subjectName);
                            }
                        }
                    }
                } else if ($role === 'teaching') {
                    // 如果是教导处角色，自动添加所有权限
                    $grades = $this->db->query("SELECT id, grade_name FROM grades WHERE status = 1")->fetchAll();
                    $subjects = $this->db->query("SELECT id, subject_name FROM subjects WHERE status = 1")->fetchAll();
                    
                    foreach ($grades as $grade) {
                        $this->db->query(
                            "INSERT INTO user_permissions 
                             (user_id, grade_id, subject_id, setting_id, can_edit, can_download, can_edit_students, created_at) 
                             VALUES (?, ?, NULL, ?, 1, 1, 1, NOW())",
                            [$userId, $grade['id'], $defaultSettingId]
                        );
                        $newPermissions[] = $grade['grade_name'];
                        
                        foreach ($subjects as $subject) {
                            $this->db->query(
                                "INSERT INTO user_permissions 
                                 (user_id, grade_id, subject_id, setting_id, can_edit, can_download, can_edit_students, created_at) 
                                 VALUES (?, ?, ?, ?, 1, 1, 0, NOW())",
                                [$userId, $grade['id'], $subject['id'], $defaultSettingId]
                            );
                            $newPermissions[] = sprintf("%s - %s", $grade['grade_name'], $subject['subject_name']);
                        }
                    }
                }
                
                $this->db->commit();
                
                // 记录操作日志
                $logDetail = sprintf(
                    "更新用户权限：%s（%s）\n当前设置：角色 %s，权限：%s\n",
                    $user['username'],
                    $user['real_name'],
                    $role,
                    implode('、', $newPermissions)
                );
                
                // 检查是否有变化
                $hasChanges = false;
                $oldPermCount = count($oldPermissions);
                $newPermCount = count($newPermissions);
                
                if ($oldPermCount !== $newPermCount) {
                    $hasChanges = true;
                    $logDetail .= sprintf(
                        '<span style="color: #1890ff">修改内容：权限数量从 %d 变为 %d</span>',
                        $oldPermCount,
                        $newPermCount
                    );
                } else if ($oldPermCount === 0 && $newPermCount === 0) {
                    $logDetail .= '<span style="color: #52c41a">无修改内容</span>';
                } else {
                    $hasChanges = true;
                    $logDetail .= '<span style="color: #1890ff">修改内容：仅修改了权限设置</span>';
                }
                
                $this->logger->info($logDetail, [
                    'action_type' => 'edit',
                    'action_detail' => $logDetail
                ]);
                
                return $this->json([
                    'success' => true,
                    'message' => '权限更新成功',
                    'has_changes' => $hasChanges,
                    'old_permissions_count' => $oldPermCount,
                    'new_permissions_count' => $newPermCount
                ]);
                
            } catch (\Exception $e) {
                $this->db->rollBack();
                throw $e;
            }
            
        } catch (\Exception $e) {
            $this->logger->error('更新用户权限失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => $_SESSION['user_id'],
                'target_user_id' => $_POST['user_id'] ?? null
            ]);
            
            return $this->json([
                'success' => false,
                'error' => '更新用户权限失败：' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 检查当前登录用户是否使用初始密码123456
     * 
     * 该方法用于检查阅卷老师是否需要强制修改初始密码
     * 
     * @return array 检查结果的JSON响应
     */
    public function checkInitialPassword() {
        try {
            // 确认已登录
            if (!isset($_SESSION['user_id'])) {
                $this->logger->warning('检查初始密码失败：未登录用户', [
                    'ip' => $_SERVER['REMOTE_ADDR']
                ]);
                return $this->json(['success' => false, 'error' => '未登录'], 401);
            }
            
            // 获取当前用户ID
            $userId = $_SESSION['user_id'];
            $userRole = $_SESSION['role'] ?? '';
            
            $this->logger->debug('开始检查初始密码', [
                'user_id' => $userId,
                'role' => $userRole,
                'ip' => $_SERVER['REMOTE_ADDR']
            ]);
            
            // 仅当用户是阅卷老师角色时进行检查
            if ($userRole !== 'marker') {
                $this->logger->debug('非阅卷老师角色，跳过初始密码检查', [
                    'user_id' => $userId,
                    'role' => $userRole
                ]);
                return $this->json([
                    'success' => true,
                    'data' => [
                        'is_initial_password' => false,
                        'message' => '非阅卷老师角色，无需检查初始密码'
                    ]
                ]);
            }
            
            // 获取用户信息
            $stmt = $this->db->query(
                "SELECT username, password FROM users WHERE id = ?",
                [$userId]
            );
            $user = $stmt->fetch();
            
            if (!$user) {
                $this->logger->warning('检查初始密码失败：用户不存在', [
                    'user_id' => $userId
                ]);
                return $this->json(['success' => false, 'error' => '用户不存在'], 404);
            }
            
            // 检查是否使用初始密码123456
            $isInitialPassword = password_verify('123456', $user['password']);
            
            // 记录日志
            $this->logger->debug('检查用户初始密码状态完成', [
                'user_id' => $userId,
                'username' => $user['username'],
                'is_initial_password' => $isInitialPassword
            ]);
            
            return $this->json([
                'success' => true,
                'data' => [
                    'is_initial_password' => $isInitialPassword,
                    'message' => $isInitialPassword ? '用户正在使用初始密码' : '用户已修改初始密码'
                ]
            ]);
        } catch (\Exception $e) {
            $this->logger->error('检查初始密码失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => $_SESSION['user_id'] ?? null
            ]);
            return $this->json([
                'success' => false,
                'error' => '系统错误，请稍后重试'
            ], 500);
        }
    }
}