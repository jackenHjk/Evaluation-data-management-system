<?php
/**
 * 文件名: controllers/ProjectController.php
 * 功能描述: 项目管理控制器
 * 
 * 该控制器负责:
 * 1. 项目信息的增删改查
 * 2. 项目状态切换（启用/禁用）
 * 3. 获取当前活动项目信息
 * 4. 管理项目数据同步功能
 * 
 * API调用路由:
 * - settings/project/list: 获取项目列表
 * - settings/project/get: 获取项目详情
 * - settings/project/add: 添加新项目
 * - settings/project/update: 更新项目信息
 * - settings/project/delete: 删除项目
 * - settings/project/toggle_status: 切换项目状态
 * - settings/project/current: 获取当前活动项目
 * 
 * 关联文件:
 * - core/Controller.php: 基础控制器
 * - modules/project_settings.php: 项目设置页面
 * - api/controllers/ProjectController.php: API项目控制器
 * - assets/js/project-settings.js: 项目设置客户端脚本
 */

namespace Controllers;

use Core\Controller;
use PDOException;
use Exception;

class ProjectController extends Controller {
    protected $routes = [
        'list' => 'getList',
        'get' => 'get',
        'add' => 'add',
        'update' => 'update',
        'delete' => 'delete',
        'toggle_status' => 'toggleStatus',
        'current' => 'getCurrent'
    ];

    public function __construct() {
        parent::__construct();
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (!isset($_SESSION['user_id'])) {
            $this->logger->warning('未登录用户尝试访问项目设置', [
                'ip' => $_SERVER['REMOTE_ADDR']
            ]);
            $this->json(['error' => '未登录'], 401);
            exit;
        }

        // 获取当前请求的方法
        $route = $_GET['route'] ?? '';
        $parts = explode('/', $route);
        $action = end($parts);

        // 允许 admin 和 teaching 角色访问
        if ($action !== 'current' && 
            $_SESSION['role'] !== 'admin' && 
            $_SESSION['role'] !== 'teaching' && 
            !$this->checkPermission('settings')) {
            $this->logger->warning('用户权限不足', [
                'user_id' => $_SESSION['user_id'],
                'role' => $_SESSION['role'] ?? null,
                'ip' => $_SERVER['REMOTE_ADDR']
            ]);
            $this->json(['error' => '无权访问'], 403);
            exit;
        }
    }

    /**
     * 获取项目列表
     */
    public function getList() {
        try {
            $sql = "SELECT * FROM settings ORDER BY created_at DESC";
            $stmt = $this->db->query($sql);
            $projects = $stmt->fetchAll();

            // 添加调试日志
            $this->logger->debug('获取项目列表', [
                'user_id' => $_SESSION['user_id'] ?? null,
                'project_count' => count($projects),
                'projects' => $projects
            ]);

            // 重新格式化项目数据
            $formattedProjects = array_map(function($project) {
                $project['display_name'] = sprintf(
                    "%s - %s - %s",
                    $project['school_name'],
                    $project['current_semester'],
                    $project['project_name']
                );
                return $project;
            }, $projects);

            $this->logger->debug('项目列表格式化完成', [
                'formatted_count' => count($formattedProjects)
            ]);

            return $this->json([
                'success' => true,
                'data' => $formattedProjects
            ]);
        } catch (PDOException $e) {
            $this->logger->error('获取项目列表失败', [
                'error' => $e->getMessage(),
                'user_id' => $_SESSION['user_id']
            ]);
            return $this->json(['error' => '获取项目列表失败'], 500);
        }
    }

    /**
     * 获取单个项目信息
     */
    public function get() {
        try {
            $id = $_GET['id'] ?? null;
            if (!$id) {
                return $this->json(['error' => '项目ID不能为空'], 400);
            }

            $sql = "SELECT * FROM settings WHERE id = ?";
            $stmt = $this->db->query($sql, [$id]);
            $project = $stmt->fetch();

            if (!$project) {
                return $this->json(['error' => '项目不存在'], 404);
            }

            return $this->json([
                'success' => true,
                'data' => $project
            ]);
        } catch (PDOException $e) {
            $this->logger->error('获取项目信息失败', [
                'error' => $e->getMessage(),
                'user_id' => $_SESSION['user_id'],
                'project_id' => $id ?? null
            ]);
            return $this->json(['error' => '获取项目信息失败'], 500);
        }
    }

    /**
     * 添加项目
     */
    public function add() {
        try {
            $schoolName = trim($_POST['school_name'] ?? '');
            $currentSemester = trim($_POST['current_semester'] ?? '');
            $projectName = trim($_POST['project_name'] ?? '');
            $syncData = isset($_POST['sync_data']) && $_POST['sync_data'] === 'true';
            $sourceProjectId = $syncData ? (int)($_POST['source_project_id'] ?? 0) : 0;

            if (empty($currentSemester) || empty($projectName)) {
                $this->logger->warning('添加项目参数不完整', [
                    'school_name' => $schoolName,
                    'current_semester' => $currentSemester,
                    'project_name' => $projectName,
                    'user_id' => $_SESSION['user_id'] ?? null
                ]);
                return $this->json(['error' => '学期和项目名称不能为空'], 400);
            }

            // 如果需要同步数据，验证源项目ID
            if ($syncData && $sourceProjectId <= 0) {
                return $this->json(['error' => '请选择要同步的源项目'], 400);
            }

            // 验证数据长度
            if (mb_strlen($schoolName) > 100) {
                $this->logger->warning('学校名称超长', [
                    'length' => mb_strlen($schoolName),
                    'user_id' => $_SESSION['user_id'] ?? null
                ]);
                return $this->json(['error' => '学校名称不能超过100个字符'], 400);
            }
            if (mb_strlen($currentSemester) > 50) {
                $this->logger->warning('学期名称超长', [
                    'length' => mb_strlen($currentSemester),
                    'user_id' => $_SESSION['user_id'] ?? null
                ]);
                return $this->json(['error' => '学期不能超过50个字符'], 400);
            }
            if (mb_strlen($projectName) > 100) {
                $this->logger->warning('项目名称超长', [
                    'length' => mb_strlen($projectName),
                    'user_id' => $_SESSION['user_id'] ?? null
                ]);
                return $this->json(['error' => '项目名称不能超过100个字符'], 400);
            }

            // 检查是否存在相同学期和项目名称的记录
            $sql = "SELECT COUNT(*) as count FROM settings WHERE current_semester = ? AND project_name = ?";
            $stmt = $this->db->query($sql, [$currentSemester, $projectName]);
            $result = $stmt->fetch();

            if ($result['count'] > 0) {
                $this->logger->warning('添加项目时发现重复名称', [
                    'current_semester' => $currentSemester,
                    'project_name' => $projectName,
                    'user_id' => $_SESSION['user_id'] ?? null
                ]);
                return $this->json(['error' => '该学期下已存在相同项目名称'], 400);
            }

            // 开始事务
            $this->db->query("START TRANSACTION");

            try {
                // 将其他项目设置为停用状态
                $this->db->query("UPDATE settings SET status = 0 WHERE status = 1");

                // 添加新项目
                $sql = "INSERT INTO settings (school_name, current_semester, project_name, status) 
                        VALUES (?, ?, ?, 1)";
                $this->db->query($sql, [$schoolName, $currentSemester, $projectName]);
                $projectId = $this->db->lastInsertId();

                // 如果需要同步数据
                if ($syncData && $sourceProjectId > 0) {
                    // 复制年级数据
                    $sql = "INSERT INTO grades (setting_id, grade_name, grade_code, status) 
                           SELECT ?, grade_name, grade_code, status 
                           FROM grades WHERE setting_id = ?";
                    $this->db->query($sql, [$projectId, $sourceProjectId]);

                    // 获取年级ID映射
                    $sql = "SELECT g1.id as old_id, g2.id as new_id 
                           FROM grades g1 
                           JOIN grades g2 ON g1.grade_code = g2.grade_code 
                           WHERE g1.setting_id = ? AND g2.setting_id = ?";
                    $stmt = $this->db->query($sql, [$sourceProjectId, $projectId]);
                    $gradeMap = [];
                    while ($row = $stmt->fetch()) {
                        $gradeMap[$row['old_id']] = $row['new_id'];
                    }

                    // 复制班级数据
                    foreach ($gradeMap as $oldGradeId => $newGradeId) {
                        $sql = "INSERT INTO classes (setting_id, grade_id, class_name, class_code, status) 
                               SELECT ?, ?, class_name, class_code, status 
                               FROM classes WHERE setting_id = ? AND grade_id = ?";
                        $this->db->query($sql, [$projectId, $newGradeId, $sourceProjectId, $oldGradeId]);
                    }

                    // 获取班级ID映射
                    $sql = "SELECT c1.id as old_id, c2.id as new_id 
                           FROM classes c1 
                           JOIN classes c2 ON c1.class_code = c2.class_code 
                           WHERE c1.setting_id = ? AND c2.setting_id = ?";
                    $stmt = $this->db->query($sql, [$sourceProjectId, $projectId]);
                    $classMap = [];
                    while ($row = $stmt->fetch()) {
                        $classMap[$row['old_id']] = $row['new_id'];
                    }

                    // 复制学生数据
                    foreach ($classMap as $oldClassId => $newClassId) {
                        $sql = "INSERT INTO students (setting_id, class_id, student_name, student_number, status) 
                               SELECT ?, ?, student_name, student_number, status 
                               FROM students WHERE setting_id = ? AND class_id = ?";
                        $this->db->query($sql, [$projectId, $newClassId, $sourceProjectId, $oldClassId]);
                    }

                    // 获取源项目中的所有科目
                    $stmt = $this->db->query(
                        "SELECT id, subject_name, full_score, excellent_score, good_score, pass_score, 
                        is_split, split_name_1, split_name_2, split_score_1, split_score_2, status 
                        FROM subjects WHERE setting_id = ?",
                        [$sourceProjectId]
                    );
                    $sourceSubjects = $stmt->fetchAll();
                    
                    // 创建旧科目ID到新科目ID的映射
                    $subjectMap = [];
                    
                    // 为每个科目生成新的代码并插入
                    foreach ($sourceSubjects as $subject) {
                        // 生成新的随机科目代码（6位大写字母和数字）
                        $isUnique = false;
                        $newSubjectCode = '';
                        $maxAttempts = 10; // 最大尝试次数
                        $attempts = 0;
                        
                        while (!$isUnique && $attempts < $maxAttempts) {
                            $newSubjectCode = '';
                            $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
                            $charLength = strlen($characters);
                            
                            for ($i = 0; $i < 6; $i++) {
                                $newSubjectCode .= $characters[rand(0, $charLength - 1)];
                            }
                            
                            // 检查代码是否已存在于新项目中
                            $stmt = $this->db->query(
                                "SELECT COUNT(*) as count FROM subjects WHERE subject_code = ? AND setting_id = ?",
                                [$newSubjectCode, $projectId]
                            );
                            $result = $stmt->fetch();
                            
                            if ($result['count'] == 0) {
                                $isUnique = true;
                            }
                            
                            $attempts++;
                        }
                        
                        if (!$isUnique) {
                            // 如果无法生成唯一代码，使用科目名加随机数
                            $newSubjectCode = substr(strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $subject['subject_name'])), 0, 3);
                            $newSubjectCode .= rand(100, 999);
                        }
                        
                        // 插入新科目，使用新生成的代码
                        $sql = "INSERT INTO subjects (
                            setting_id, subject_name, subject_code, full_score, excellent_score, 
                            good_score, pass_score, is_split, split_name_1, split_name_2, 
                            split_score_1, split_score_2, status
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                        
                        $this->db->query($sql, [
                            $projectId, 
                            $subject['subject_name'], 
                            $newSubjectCode, 
                            $subject['full_score'], 
                            $subject['excellent_score'], 
                            $subject['good_score'], 
                            $subject['pass_score'],
                            $subject['is_split'],
                            $subject['split_name_1'],
                            $subject['split_name_2'],
                            $subject['split_score_1'],
                            $subject['split_score_2'],
                            $subject['status']
                        ]);
                        
                        // 记录新旧科目ID的映射
                        $subjectMap[$subject['id']] = $this->db->lastInsertId();
                    }

                    // 同步subject_settings表数据
                    foreach ($subjectMap as $oldSubjectId => $newSubjectId) {
                        $sql = "INSERT INTO subject_settings (
                            setting_id, subject_id, full_score, excellent_score, good_score, pass_score,
                            is_split, split_name_1, split_name_2, split_score_1, split_score_2
                        ) 
                        SELECT 
                            ?, ?, full_score, excellent_score, good_score, pass_score,
                            is_split, split_name_1, split_name_2, split_score_1, split_score_2
                        FROM subject_settings 
                        WHERE subject_id = ? AND setting_id = ?";
                        $this->db->query($sql, [$projectId, $newSubjectId, $oldSubjectId, $sourceProjectId]);
                    }

                    // 复制科目年级关联数据
                    foreach ($subjectMap as $oldSubjectId => $newSubjectId) {
                        $sql = "INSERT INTO subject_grades (setting_id, subject_id, grade_id) 
                               SELECT ?, ?, g2.id 
                               FROM subject_grades sg
                               JOIN grades g1 ON sg.grade_id = g1.id
                               JOIN grades g2 ON g1.grade_code = g2.grade_code AND g2.setting_id = ?
                               WHERE sg.subject_id = ?";
                        $this->db->query($sql, [$projectId, $newSubjectId, $projectId, $oldSubjectId]);
                    }

                    // 同步班主任和阅卷老师的权限配置
                    $sql = "SELECT id, role FROM users WHERE role IN ('headteacher', 'marker')";
                    $stmt = $this->db->query($sql);
                    $users = $stmt->fetchAll();

                    foreach ($users as $user) {
                        // 删除新项目中的旧权限配置
                        $sql = "DELETE FROM user_permissions WHERE user_id = ? AND setting_id = ?";
                        $this->db->query($sql, [$user['id'], $projectId]);

                        // 复制权限配置
                        if ($user['role'] === 'headteacher') {
                            // 复制班主任的年级权限
                            $sql = "INSERT INTO user_permissions (user_id, grade_id, subject_id, setting_id, can_edit, can_download, can_edit_students, created_at)
                                   SELECT up.user_id, g2.id, NULL, ?, up.can_edit, up.can_download, up.can_edit_students, NOW()
                                   FROM user_permissions up
                                   JOIN grades g1 ON up.grade_id = g1.id
                                   JOIN grades g2 ON g1.grade_code = g2.grade_code AND g2.setting_id = ?
                                   WHERE up.user_id = ? AND up.setting_id = ? AND up.subject_id IS NULL";
                            $this->db->query($sql, [$projectId, $projectId, $user['id'], $sourceProjectId]);
                        } else if ($user['role'] === 'marker') {
                            // 获取该用户在源项目中的权限
                            $stmt = $this->db->query(
                                "SELECT up.*, g1.grade_code 
                                FROM user_permissions up
                                JOIN grades g1 ON up.grade_id = g1.id
                                WHERE up.user_id = ? AND up.setting_id = ? AND up.subject_id IS NOT NULL",
                                [$user['id'], $sourceProjectId]
                            );
                            $permissions = $stmt->fetchAll();
                            
                            // 为每条权限记录创建新项目中的对应记录
                            foreach ($permissions as $perm) {
                                // 如果原科目ID在映射表中且有对应的新年级
                                if (isset($subjectMap[$perm['subject_id']])) {
                                    // 查找新项目中对应的年级ID
                                    $stmt = $this->db->query(
                                        "SELECT id FROM grades 
                                        WHERE grade_code = ? AND setting_id = ?",
                                        [$perm['grade_code'], $projectId]
                                    );
                                    $newGrade = $stmt->fetch();
                                    
                                    if ($newGrade) {
                                        // 插入新的权限记录
                                        $this->db->query(
                                            "INSERT INTO user_permissions 
                                            (user_id, grade_id, subject_id, setting_id, can_edit, can_download, can_edit_students, created_at)
                                            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())",
                                            [
                                                $user['id'],
                                                $newGrade['id'],
                                                $subjectMap[$perm['subject_id']],
                                                $projectId,
                                                $perm['can_edit'],
                                                $perm['can_download'],
                                                $perm['can_edit_students']
                                            ]
                                        );
                                    }
                                }
                            }
                        }
                    }
                }

                $this->db->query("COMMIT");

                // 验证数据是否真正插入
                $verifyStmt = $this->db->query("SELECT * FROM settings WHERE id = ?", [$projectId]);
                $insertedProject = $verifyStmt->fetch();

                $this->logger->debug('项目添加成功', [
                    'user_id' => $_SESSION['user_id'] ?? null,
                    'project_id' => $projectId,
                    'project_name' => $projectName,
                    'sync_data' => $syncData,
                    'source_project_id' => $sourceProjectId,
                    'inserted_data' => $insertedProject,
                    'verification' => $insertedProject ? '数据已插入' : '警告:数据未找到'
                ]);

                return $this->json([
                    'success' => true,
                    'message' => '项目添加成功',
                    'data' => ['id' => $projectId]
                ]);
            } catch (PDOException $e) {
                $this->db->query("ROLLBACK");
                throw $e;
            }
        } catch (PDOException $e) {
            if ($this->db->inTransaction()) {
                $this->db->query("ROLLBACK");
            }
            $this->logger->error('添加项目失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => $_SESSION['user_id'] ?? null,
                'data' => $_POST
            ]);
            return $this->json(['error' => '添加项目失败'], 500);
        }
    }

    /**
     * 更新项目
     */
    public function update() {
        try {
            $id = $_POST['id'] ?? null;
            $schoolName = trim($_POST['school_name'] ?? '');
            $currentSemester = trim($_POST['current_semester'] ?? '');
            $projectName = trim($_POST['project_name'] ?? '');

            if (!$id || empty($currentSemester) || empty($projectName)) {
                $this->logger->warning('更新项目参数不完整', [
                    'id' => $id,
                    'school_name' => $schoolName,
                    'current_semester' => $currentSemester,
                    'project_name' => $projectName,
                    'user_id' => $_SESSION['user_id'] ?? null
                ]);
                return $this->json(['error' => '请填写完整信息'], 400);
            }

            // 检查项目是否存在
            $sql = "SELECT id FROM settings WHERE id = ?";
            $stmt = $this->db->query($sql, [$id]);
            if (!$stmt->fetch()) {
                $this->logger->warning('更新的项目不存在', [
                    'id' => $id,
                    'user_id' => $_SESSION['user_id'] ?? null
                ]);
                return $this->json(['error' => '项目不存在'], 404);
            }

            // 检查是否存在同名项目
            $sql = "SELECT id FROM settings WHERE current_semester = ? AND project_name = ? AND id != ?";
            $stmt = $this->db->query($sql, [$currentSemester, $projectName, $id]);
            if ($stmt->fetch()) {
                $this->logger->warning('更新项目时发现重复名称', [
                    'id' => $id,
                    'current_semester' => $currentSemester,
                    'project_name' => $projectName,
                    'user_id' => $_SESSION['user_id'] ?? null
                ]);
                return $this->json(['error' => '该学期下已存在相同项目名称'], 400);
            }

            // 开始事务
            $this->db->query("START TRANSACTION");

            try {
                // 更新项目
                $sql = "UPDATE settings 
                        SET school_name = ?, 
                            current_semester = ?, 
                            project_name = ?,
                            updated_at = CURRENT_TIMESTAMP
                        WHERE id = ?";
                $this->db->query($sql, [$schoolName, $currentSemester, $projectName, $id]);

                $this->db->query("COMMIT");

                $this->logger->debug('项目更新成功', [
                    'user_id' => $_SESSION['user_id'] ?? null,
                    'project_id' => $id,
                    'project_name' => $projectName
                ]);

                return $this->json([
                    'success' => true,
                    'message' => '项目更新成功'
                ]);
            } catch (PDOException $e) {
                $this->db->query("ROLLBACK");
                throw $e;
            }
        } catch (PDOException $e) {
            $this->logger->error('更新项目失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => $_SESSION['user_id'] ?? null,
                'data' => $_POST
            ]);
            return $this->json(['error' => '更新项目失败'], 500);
        }
    }

    /**
     * 切换项目状态
     */
    public function toggleStatus() {
        try {
            // 获取并验证参数
            $id = isset($_POST['id']) ? (int)$_POST['id'] : null;
            $status = isset($_POST['status']) ? (int)$_POST['status'] : null;

            // 记录请求参数
            $this->logger->debug('切换项目状态请求', [
                'id' => $id,
                'status' => $status,
                'raw_post' => $_POST,
                'user_id' => $_SESSION['user_id'] ?? null
            ]);

            // 参数验证
            if ($id === null || !in_array($status, [0, 1], true)) {
                $this->logger->warning('切换项目状态参数错误', [
                    'id' => $id,
                    'status' => $status,
                    'user_id' => $_SESSION['user_id'] ?? null
                ]);
                return $this->json(['error' => '参数错误：项目ID或状态值无效'], 400);
            }

            // 检查项目是否存在
            $sql = "SELECT status, project_name FROM settings WHERE id = ?";
            $stmt = $this->db->query($sql, [$id]);
            $project = $stmt->fetch();
            
            if (!$project) {
                $this->logger->warning('切换状态的项目不存在', [
                    'id' => $id,
                    'user_id' => $_SESSION['user_id'] ?? null
                ]);
                return $this->json(['error' => '项目不存在'], 404);
            }

            // 开始事务
            $this->db->query("START TRANSACTION");

            try {
                if ($status == 1) {
                    // 如果要启用，先将其他项目设置为停用
                    $this->db->query("UPDATE settings SET status = 0 WHERE status = 1");
                } else {
                    // 如果要停用，确保至少有一个可用项目
                    $sql = "SELECT COUNT(*) as count FROM settings WHERE status = 1 AND id != ?";
                    $stmt = $this->db->query($sql, [$id]);
                    $result = $stmt->fetch();
                    if ($result['count'] == 0) {
                        $this->db->query("ROLLBACK");
                        $this->logger->warning('尝试停用最后一个可用项目', [
                            'id' => $id,
                            'user_id' => $_SESSION['user_id'] ?? null
                        ]);
                        return $this->json(['error' => '必须保持至少一个可用项目'], 400);
                    }
                }

                // 更新指定项目的状态
                $sql = "UPDATE settings SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?";
                $this->db->query($sql, [$status, $id]);

                $this->db->query("COMMIT");

                $this->logger->debug('项目状态更新成功', [
                    'id' => $id,
                    'new_status' => $status,
                    'user_id' => $_SESSION['user_id'] ?? null
                ]);

                // 记录项目切换日志
                if ($status == 1) {
                    $this->logger->info('切换至项目: ' . $project['project_name'], [
                        'project_id' => $id,
                        'project_name' => $project['project_name'],
                        'user_id' => $_SESSION['user_id'] ?? null,
                        'action_type' => 'switch_project'
                    ]);
                }

                return $this->json([
                    'success' => true,
                    'message' => $status == 1 ? '项目已启用' : '项目已停用',
                    'data' => [
                        'id' => $id,
                        'status' => $status,
                        'project_name' => $project['project_name']
                    ]
                ]);
            } catch (PDOException $e) {
                $this->db->query("ROLLBACK");
                throw $e;
            }
        } catch (PDOException $e) {
            if ($this->db->inTransaction()) {
                $this->db->query("ROLLBACK");
            }
            $this->logger->error('切换项目状态失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'id' => $id ?? null,
                'status' => $status ?? null,
                'user_id' => $_SESSION['user_id'] ?? null
            ]);
            return $this->json(['error' => '切换项目状态失败'], 500);
        }
    }

    /**
     * 删除项目
     */
    public function delete() {
        if (empty($_POST['id'])) {
            $this->logger->warning('删除项目时ID为空', [
                'user_id' => $_SESSION['user_id']
            ]);
            return $this->json(['success' => false, 'error' => '项目ID不能为空']);
        }

        $projectId = $_POST['id'];

        try {
            $this->db->query("START TRANSACTION");

            // 1. 删除成绩分析数据
            $sql = "DELETE FROM score_analytics WHERE subject_id IN (SELECT id FROM subjects WHERE setting_id = ?)";
            $this->db->query($sql, [$projectId]);

            // 2. 删除成绩数据
            $sql = "DELETE FROM scores WHERE subject_id IN (SELECT id FROM subjects WHERE setting_id = ?)";
            $this->db->query($sql, [$projectId]);

            // 3. 删除科目年级关联
            $sql = "DELETE FROM subject_grades WHERE subject_id IN (SELECT id FROM subjects WHERE setting_id = ?)";
            $this->db->query($sql, [$projectId]);

            // 4. 删除科目
            $sql = "DELETE FROM subjects WHERE setting_id = ?";
            $this->db->query($sql, [$projectId]);

            // 5. 删除项目
            $sql = "DELETE FROM settings WHERE id = ?";
            $this->db->query($sql, [$projectId]);

            $this->db->query("COMMIT");

            $this->logger->debug('项目删除成功', [
                'project_id' => $projectId,
                'user_id' => $_SESSION['user_id']
            ]);

            return $this->json(['success' => true, 'message' => '删除成功']);
        } catch (Exception $e) {
            $this->db->query("ROLLBACK");
            $this->logger->error('删除项目失败', [
                'project_id' => $projectId,
                'error' => $e->getMessage(),
                'user_id' => $_SESSION['user_id']
            ]);
            return $this->json(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * 获取当前可用项目
     */
    public function getCurrent() {
        try {
            $this->logger->debug('开始获取当前可用项目', [
                'user_id' => $_SESSION['user_id'] ?? null,
                'ip' => $_SERVER['REMOTE_ADDR']
            ]);

            // 检查数据库连接
            try {
                $this->db->query("SELECT 1");
                $this->logger->debug('数据库连接正常');
            } catch (PDOException $e) {
                $this->logger->error('数据库连接失败', [
                    'error' => $e->getMessage(),
                    'code' => $e->getCode()
                ]);
                return $this->json([
                    'success' => false,
                    'error' => '数据库连接失败',
                    'debug' => [
                        'message' => $e->getMessage(),
                        'code' => $e->getCode()
                    ]
                ], 500);
            }

            // 检查settings表是否存在
            $tables = $this->db->query("SHOW TABLES LIKE 'settings'")->fetchAll();
            if (empty($tables)) {
                $this->logger->error('settings表不存在');
                return $this->json([
                    'success' => false,
                    'error' => 'settings表不存在',
                    'debug' => ['tables' => $tables]
                ], 500);
            }

            // 获取当前可用项目
            $sql = "SELECT * FROM settings WHERE status = 1 LIMIT 1";
            $this->logger->debug('执行SQL查询', ['sql' => $sql]);
            
            $stmt = $this->db->query($sql);
            $project = $stmt->fetch();

            if (!$project) {
                $this->logger->warning('当前无可用项目', [
                    'user_id' => $_SESSION['user_id'] ?? null
                ]);
                
                // 获取所有项目数量
                $totalProjects = $this->db->query("SELECT COUNT(*) as count FROM settings")->fetch();
                
                return $this->json([
                    'success' => false,
                    'error' => '当前无可用项目',
                    'debug' => [
                        'total_projects' => $totalProjects['count'],
                        'query' => $sql
                    ]
                ], 404);
            }

            $this->logger->debug('成功获取当前项目', [
                'project_id' => $project['id'],
                'project_name' => $project['project_name']
            ]);

            return $this->json([
                'success' => true,
                'data' => $project,
                'debug' => [
                    'query_time' => date('Y-m-d H:i:s'),
                    'project_count' => 1
                ]
            ]);
        } catch (PDOException $e) {
            $this->logger->error('获取当前项目失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => $_SESSION['user_id'] ?? null
            ]);
            return $this->json([
                'success' => false,
                'error' => '获取当前项目失败',
                'debug' => [
                    'message' => $e->getMessage(),
                    'code' => $e->getCode(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                ]
            ], 500);
        }
    }
}