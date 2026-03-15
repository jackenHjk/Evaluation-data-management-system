<?php
/**
 * 文件名: controllers/SettingsController.php
 * 功能描述: 系统设置控制器
 * 
 * 该控制器负责:
 * 1. 学校信息管理
 * 2. 用户管理和权限设置
 * 3. 年级和班级设置
 * 4. 科目管理
 * 5. 统一处理设置相关的API请求
 * 
 * API调用路由:
 * - school/info: 获取学校信息
 * - school/save: 保存学校信息
 * - grades: 获取年级列表
 * - grade/add: 添加年级
 * - grade/update: 更新年级
 * - grade/delete: 删除年级
 * - grade/check_name: 检查年级名称
 * - grade/check_code: 检查年级代码
 * - grade/get: 获取年级详情
 * - grade/subjects: 获取年级科目
 * - classes: 获取班级列表
 * - subject/add: 添加科目
 * - user/list: 获取用户列表
 * - user/get: 获取用户详情
 * - user/permissions: 获取用户权限
 * - user/add: 添加用户
 * - user/update: 更新用户
 * - user/delete: 删除用户
 * - user/toggle_status: 切换用户状态
 * 
 * 关联文件:
 * - core/Controller.php: 基础控制器
 * - modules/user_settings.php: 用户设置页面
 * - modules/subject_settings.php: 科目设置页面
 * - modules/class_settings.php: 班级设置页面
 * - modules/grade_settings.php: 年级设置页面
 * - controllers/GradeController.php: 年级控制器
 * - controllers/ClassController.php: 班级控制器
 * - controllers/UserController.php: 用户控制器
 * - controllers/SubjectController.php: 科目控制器
 */

namespace Controllers;

use Core\Controller;
use PDOException;

class SettingsController extends Controller {
    protected $routes = [
        'school/info' => 'getSchoolInfo',
        'school/save' => 'saveSchoolInfo',
        'grades' => 'getGradeList',
        'grade/add' => 'addGrade',
        'grade/update' => 'updateGrade',
        'grade/delete' => 'deleteGrade',
        'grade/check_name' => 'checkGradeName',
        'grade/check_code' => 'checkGradeCode',
        'grade/get' => 'getGrade',
        'grade/subjects' => 'getGradeSubjects',
        'classes' => 'getClassList',
        'class/add' => 'addClass',
        'class/update' => 'updateClass',
        'class/delete' => 'deleteClass',
        'class/check_code' => 'checkClassCode',
        'users' => 'getUserList',
        'user/get' => 'getUser',
        'user/add' => 'addUser',
        'user/update' => 'updateUser',
        'user/delete' => 'deleteUser',
        'user/permissions' => 'getUserPermissions',
        'toggle_user_status' => 'toggleUserStatus'
    ];

    public function __construct() {
        parent::__construct();
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (!isset($_SESSION['user_id'])) {
            $this->logger->warning('未登录用户尝试访问设置', [
                'ip' => $_SERVER['REMOTE_ADDR']
            ]);
            $this->json(['error' => '未登录'], 401);
            exit;
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

    public function getSchoolInfo() {
        try {
            $this->logger->debug('获取学校信息', [
                'user_id' => $_SESSION['user_id']
            ]);

            $stmt = $this->db->query("SELECT * FROM settings LIMIT 1");
            $settings = $stmt->fetch();

            return $this->json([
                'success' => true,
                'data' => $settings
            ]);
        } catch (\Exception $e) {
            $this->logger->error('获取学校信息失败', [
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

    public function saveSchoolInfo() {
        try {
            $schoolName = $_POST['school_name'] ?? '';
            $currentSemester = $_POST['current_semester'] ?? '';
            $projectName = $_POST['project_name'] ?? '';

            if (empty($schoolName)) {
                $this->logger->warning('保存学校信息参数不完整', [
                    'school_name' => $schoolName,
                    'user_id' => $_SESSION['user_id']
                ]);
                return $this->json(['error' => '学校名称不能为空'], 400);
            }

            $this->logger->debug('尝试保存学校信息', [
                'school_name' => $schoolName,
                'current_semester' => $currentSemester,
                'project_name' => $projectName,
                'user_id' => $_SESSION['user_id']
            ]);

            $stmt = $this->db->query(
                "SELECT COUNT(*) as count FROM settings"
            );
            $result = $stmt->fetch();

            if ($result['count'] > 0) {
                $this->db->query(
                    "UPDATE settings SET 
                    school_name = ?, 
                    current_semester = ?, 
                    project_name = ?,
                    updated_at = CURRENT_TIMESTAMP",
                    [$schoolName, $currentSemester, $projectName]
                );
            } else {
                $this->db->query(
                    "INSERT INTO settings (school_name, current_semester, project_name) 
                    VALUES (?, ?, ?)",
                    [$schoolName, $currentSemester, $projectName]
                );
            }

            $this->logger->debug('学校信息保存成功', [
                'user_id' => $_SESSION['user_id']
            ]);

            return $this->json([
                'success' => true,
                'message' => '保存成功'
            ]);
        } catch (\Exception $e) {
            $this->logger->error('保存学校信息失败', [
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

    public function getSubjects() {
        try {
            $this->logger->debug('获取科目列表', [
                'user_id' => $_SESSION['user_id']
            ]);

            $stmt = $this->db->query(
                "SELECT * FROM subjects WHERE status = 1 ORDER BY subject_name"
            );
            $subjects = $stmt->fetchAll();

            return $this->json([
                'success' => true,
                'data' => $subjects
            ]);
        } catch (\Exception $e) {
            $this->logger->error('获取科目列表失败', [
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

    public function addUser() {
        try {
            // 验证必填字段
            $username = $_POST['username'] ?? '';
            $realName = $_POST['real_name'] ?? '';
            $password = $_POST['password'] ?? '';
            $role = $_POST['role'] ?? '';
            $permissions = json_decode($_POST['permissions'] ?? '[]', true);

            if (empty($username) || empty($realName) || empty($password) || empty($role)) {
                $this->logger->warning('添加用户失败：参数不完整', [
                    'user_id' => $_SESSION['user_id']
                ]);
                return $this->json(['error' => '请填写完整信息'], 400);
            }

            // 检查用户名是否已存在
            $stmt = $this->db->query(
                "SELECT id FROM users WHERE username = ?",
                [$username]
            );
            if ($stmt->fetch()) {
                $this->logger->warning('添加用户失败：用户名已存在', [
                    'username' => $username,
                    'user_id' => $_SESSION['user_id']
                ]);
                return $this->json(['error' => '用户名已存在'], 400);
            }

            // 开始事务
            $this->db->beginTransaction();

            // 插入用户基本信息
            $stmt = $this->db->query(
                "INSERT INTO users (username, real_name, password, role, status, created_at) 
                 VALUES (?, ?, ?, ?, 1, NOW())",
                [$username, $realName, password_hash($password, PASSWORD_DEFAULT), $role]
            );

            $userId = $this->db->lastInsertId();

            // 获取当前项目ID
            $settingStmt = $this->db->query(
                "SELECT id FROM settings WHERE status = 1 LIMIT 1"
            );
            $currentSetting = $settingStmt->fetch();
            $settingId = $currentSetting ? $currentSetting['id'] : 0;

            if (!$settingId) {
                throw new \Exception('未找到当前可用项目');
            }

            // 插入用户权限
            if (!empty($permissions)) {
                foreach ($permissions as $permission) {
                    $this->db->query(
                        "INSERT INTO user_permissions (
                            user_id, grade_id, subject_id, setting_id,
                            can_edit, can_download, can_edit_students, 
                            created_at
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())",
                        [
                            $userId,
                            $permission['grade_id'] ?? null,
                            $permission['subject_id'] ?? null,
                            $settingId,
                            $permission['can_edit'] ?? 0,
                            $permission['can_download'] ?? 0,
                            $permission['can_edit_students'] ?? 0
                        ]
                    );
                }
            }

            // 提交事务
            $this->db->commit();

            $this->logger->debug('添加用户成功', [
                'new_user_id' => $userId,
                'username' => $username,
                'role' => $role,
                'operator_id' => $_SESSION['user_id']
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
            // 回滚事务
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            
            $this->logger->error('添加用户失败', [
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

    public function getGradeSubjects() {
        try {
            $gradeId = $_GET['grade_id'] ?? '';
            
            if (empty($gradeId)) {
                $this->logger->warning('获取年级科目失败：未指定年级ID', [
                    'user_id' => $_SESSION['user_id']
                ]);
                return $this->json(['error' => '参数不完整'], 400);
            }

            // 获取当前可用项目
            $settingStmt = $this->db->query(
                "SELECT id FROM settings WHERE status = 1 LIMIT 1"
            );
            $setting = $settingStmt->fetch();
            
            if (!$setting) {
                $this->logger->warning('获取年级科目失败：当前无可用项目', [
                    'user_id' => $_SESSION['user_id']
                ]);
                return $this->json(['error' => '当前无可用项目'], 404);
            }

            $this->logger->debug('获取年级科目', [
                'grade_id' => $gradeId,
                'user_id' => $_SESSION['user_id'],
                'setting_id' => $setting['id']
            ]);

            // 获取与当前可用项目关联的科目
            $stmt = $this->db->query(
                "SELECT s.* 
                 FROM subjects s 
                 INNER JOIN subject_grades sg ON s.id = sg.subject_id 
                 WHERE sg.grade_id = ? 
                 AND s.status = 1 
                 AND s.setting_id = ?
                 ORDER BY s.subject_name",
                [$gradeId, $setting['id']]
            );

            $this->json([
                'success' => true,
                'data' => $stmt->fetchAll()
            ]);
        } catch (\Exception $e) {
            $this->logger->error('获取年级科目失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $this->json([
                'success' => false,
                'error' => $e->getMessage()
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
            $password = $_POST['password'] ?? ''; // 密码可选

            if (empty($userId) || empty($username) || empty($realName) || empty($role)) {
                $this->logger->warning('更新用户失败：参数不完整', [
                    'user_id' => $_SESSION['user_id']
                ]);
                return $this->json(['error' => '请填写完整信息'], 400);
            }

            // 检查用户是否存在
            $stmt = $this->db->query(
                "SELECT id FROM users WHERE id = ?",
                [$userId]
            );
            if (!$stmt->fetch()) {
                $this->logger->warning('更新用户失败：用户不存在', [
                    'target_user_id' => $userId,
                    'user_id' => $_SESSION['user_id']
                ]);
                return $this->json(['error' => '用户不存在'], 404);
            }

            // 开始事务
            $this->db->beginTransaction();

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

            // 获取当前项目ID
            $settingStmt = $this->db->query(
                "SELECT id FROM settings WHERE status = 1 LIMIT 1"
            );
            $currentSetting = $settingStmt->fetch();
            $settingId = $currentSetting ? $currentSetting['id'] : 0;

            if (!$settingId) {
                throw new \Exception('未找到当前可用项目');
            }

            // 只删除当前项目的权限，保留其他项目的权限
            $this->db->query(
                "DELETE FROM user_permissions WHERE user_id = ? AND setting_id = ?",
                [$userId, $settingId]
            );

            // 插入新的权限
            if (!empty($permissions)) {
                foreach ($permissions as $permission) {
                    // 如果是阅卷老师角色，只允许编辑权限，不允许下载权限
                    $canDownload = ($role === '阅卷老师') ? 0 : ($permission['can_download'] ?? 0);
                    
                    // 检查是否已存在相同的权限记录
                    $checkStmt = $this->db->query(
                        "SELECT id FROM user_permissions 
                        WHERE user_id = ? AND grade_id = ? AND subject_id = ? AND setting_id = ?",
                        [$userId, $permission['grade_id'], $permission['subject_id'], $settingId]
                    );
                    
                    if (!$checkStmt->fetch()) {
                        $this->db->query(
                            "INSERT INTO user_permissions (
                                user_id, grade_id, subject_id, setting_id,
                                can_edit, can_download, can_edit_students, 
                                created_at
                            ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())",
                            [
                                $userId,
                                $permission['grade_id'],
                                $permission['subject_id'],
                                $settingId,
                                $permission['can_edit'] ?? 0,
                                $canDownload,
                                $permission['can_edit_students'] ?? 0
                            ]
                        );
                    }
                }
            }

            // 提交事务
            $this->db->commit();

            $this->logger->debug('更新用户成功', [
                'target_user_id' => $userId,
                'operator_id' => $_SESSION['user_id']
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
            // 回滚事务
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            
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

            // 检查用户是否存在
            $stmt = $this->db->query(
                "SELECT id FROM users WHERE id = ?",
                [$userId]
            );
            if (!$stmt->fetch()) {
                $this->logger->warning('删除用户失败：用户不存在', [
                    'target_user_id' => $userId,
                    'user_id' => $_SESSION['user_id']
                ]);
                return $this->json(['error' => '用户不存在'], 404);
            }

            // 开始事务
            $this->db->beginTransaction();

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

            // 提交事务
            $this->db->commit();

            $this->logger->debug('删除用户成功', [
                'target_user_id' => $userId,
                'operator_id' => $_SESSION['user_id']
            ]);

            return $this->json([
                'success' => true,
                'message' => '删除成功'
            ]);

        } catch (\Exception $e) {
            // 回滚事务
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            
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

    public function toggleUserStatus() {
        try {
            $userId = $_POST['id'] ?? '';
            
            if (empty($userId)) {
                $this->logger->warning('切换用户状态失败：未指定用户ID', [
                    'user_id' => $_SESSION['user_id']
                ]);
                return $this->json(['error' => '参数不完整'], 400);
            }

            // 检查用户是否存在并获取当前状态
            $stmt = $this->db->query(
                "SELECT id, status FROM users WHERE id = ?",
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

            $this->logger->debug('切换用户状态成功', [
                'target_user_id' => $userId,
                'old_status' => $user['status'],
                'new_status' => $newStatus,
                'operator_id' => $_SESSION['user_id']
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

    // ... existing code ...
} 