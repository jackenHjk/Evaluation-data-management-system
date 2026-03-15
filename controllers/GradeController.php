<?php
/**
 * 文件名: controllers/GradeController.php
 * 功能描述: 年级管理控制器
 * 
 * 该控制器负责:
 * 1. 年级信息的增删改查
 * 2. 年级升级功能实现
 * 3. 年级数据验证
 * 4. 年级相关的统计分析
 * 5. 年级学生排名数据获取
 * 
 * API调用路由:
 * - settings/grade/list: 获取年级列表
 * - settings/grades: 获取年级列表（另一种格式）
 * - grade/get: 获取年级详情
 * - grade/add: 添加年级
 * - grade/update: 更新年级信息
 * - grade/delete: 删除年级
 * - grade/check_name: 检查年级名称是否重复
 * - grade/check_code: 检查年级代码是否重复
 * - grade/check_upgradable: 检查年级是否可以升级
 * - grade/upgrade: 执行年级升级
 * - grade/analytics: 获取年级分析数据
 * - grade/student_rank: 获取学生排名
 * 
 * 关联文件:
 * - core/Controller.php: 基础控制器
 * - modules/grade_settings.php: 年级设置页面
 * - api/controllers/GradeController.php: API年级控制器
 * - controllers/GradeAnalyticsController.php: 年级分析控制器
 */

namespace Controllers;

use core\Controller;
use core\Logger;
use PDO;
use Exception;

class GradeController extends Controller {
    protected $logger;
    protected $routes = [
        'list' => 'getList',
        'get' => 'get',
        'add' => 'add',
        'update' => 'update',
        'delete' => 'delete',
        'subjects' => 'getSubjects',
        'analytics' => 'classAnalytics',
        'student_rank' => 'studentRank',
        'check_upgradable' => 'checkUpgradable',
        'upgrade' => 'upgrade',
        'check_name' => 'checkName',
        'check_code' => 'checkCode'
    ];

    public function __construct() {
        parent::__construct();
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // 检查用户是否登录
        if (!isset($_SESSION['user_id'])) {
            $this->logger->warning('未登录用户尝试访问', [
                'ip' => $_SERVER['REMOTE_ADDR']
            ]);
            $this->json(['error' => '未登录'], 401);
            exit;
        }

        // 获取当前请求的方法
        $route = $_GET['route'] ?? '';
        $parts = explode('/', $route);
        $action = end($parts);

        // subjects 接口允许 teaching 角色访问
        if ($action === 'subjects') {
            if ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'teaching' && !$this->checkPermission('settings')) {
                $this->logger->warning('用户权限不足', [
                    'user_id' => $_SESSION['user_id'],
                    'role' => $_SESSION['role'] ?? null,
                    'ip' => $_SERVER['REMOTE_ADDR']
                ]);
                $this->json(['error' => '无权访问'], 403);
                exit;
            }
        }
    }

    public function getList() {
        try {
            $this->logger->debug('获取年级列表', [
                'user_id' => $_SESSION['user_id'] ?? null,
                'role' => $_SESSION['role'] ?? null
            ]);

            // 构建基础SQL查询
            $sql = "SELECT g.*, 
                    (SELECT COUNT(*) FROM classes c WHERE c.grade_id = g.id AND c.status = 1) as class_count,
                    (SELECT COUNT(*) FROM students s 
                     INNER JOIN classes c ON s.class_id = c.id 
                     WHERE c.grade_id = g.id AND s.status = 1) as student_count
                    FROM grades g
                    WHERE g.status = 1
                    AND g.setting_id = (SELECT id FROM settings WHERE status = 1 LIMIT 1)";
            $params = [];

            // 根据用户角色添加权限过滤
            $role = $_SESSION['role'] ?? '';
            if (!in_array($role, ['admin', 'teaching'])) {
                $this->logger->debug('应用权限过滤', [
                    'user_id' => $_SESSION['user_id'],
                    'role' => $role
                ]);
                
                $sql = "SELECT g.*, 
                        (SELECT COUNT(*) FROM classes c WHERE c.grade_id = g.id AND c.status = 1) as class_count,
                        (SELECT COUNT(*) FROM students s 
                         INNER JOIN classes c ON s.class_id = c.id 
                         WHERE c.grade_id = g.id AND s.status = 1) as student_count
                        FROM grades g 
                        INNER JOIN user_permissions up ON g.id = up.grade_id 
                        WHERE up.user_id = ? AND g.status = 1 AND g.setting_id = (SELECT id FROM settings WHERE status = 1 LIMIT 1)";
                $params[] = $_SESSION['user_id'];

                if ($role === 'marker') {
                    $sql .= " AND up.subject_id IS NOT NULL AND up.can_edit = 1";
                } else if ($role === 'headteacher') {
                    $sql .= " AND up.can_edit_students = 1";
                }
            }

            // 添加排序
            $sql .= " ORDER BY g.grade_code ASC";

            $stmt = $this->db->query($sql, $params);
            $grades = $stmt->fetchAll();

            // 确保id是字符串类型
            foreach ($grades as &$grade) {
                $grade['id'] = (string)$grade['id'];
            }

            $this->logger->debug('年级列表获取成功', [
                'count' => count($grades)
            ]);

            return $this->json([
                'success' => true,
                'data' => $grades
            ]);
        } catch (\Exception $e) {
            $this->logger->error('获取年级列表失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => $_SESSION['user_id'] ?? null
            ]);
            return $this->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function add() {
        try {
            if (!$this->checkPermission('settings')) {
                $this->logger->warning('添加年级权限不足', [
                    'user_id' => $_SESSION['user_id'] ?? null,
                    'role' => $_SESSION['role'] ?? null
                ]);
                return $this->json(['error' => '无权访问'], 403);
            }

            $gradeName = $_POST['grade_name'] ?? '';
            $gradeCode = $_POST['grade_code'] ?? '';
            $settingId = $_POST['setting_id'] ?? '';

            if (empty($settingId)) {
                $this->logger->warning('添加年级参数不完整：缺少项目ID', [
                    'user_id' => $_SESSION['user_id'] ?? null
                ]);
                return $this->json(['error' => '缺少项目ID'], 400);
            }

            if (empty($gradeName) || empty($gradeCode)) {
                $this->logger->warning('添加年级参数不完整', [
                    'grade_name' => $gradeName,
                    'grade_code' => $gradeCode,
                    'user_id' => $_SESSION['user_id'] ?? null
                ]);
                return $this->json(['error' => '请填写完整信息'], 400);
            }

            // 检查年级代码是否已存在（在同一项目下）
            $stmt = $this->db->query(
                "SELECT COUNT(*) as count FROM grades 
                 WHERE grade_code = ? AND setting_id = ? AND status = 1",
                [$gradeCode, $settingId]
            );
            if ($stmt->fetch()['count'] > 0) {
                return $this->json(['error' => '年级代码已存在'], 400);
            }

            // 检查年级名称是否已存在（在同一项目下）
            $stmt = $this->db->query(
                "SELECT COUNT(*) as count FROM grades 
                 WHERE grade_name = ? AND setting_id = ? AND status = 1",
                [$gradeName, $settingId]
            );
            if ($stmt->fetch()['count'] > 0) {
                return $this->json(['error' => '年级名称已存在'], 400);
            }

            // 开始事务
            $this->db->query("START TRANSACTION");

            try {
                // 插入年级记录
                $this->db->query(
                    "INSERT INTO grades (setting_id, grade_name, grade_code, status, created_at) 
                     VALUES (?, ?, ?, 1, NOW())",
                    [$settingId, $gradeName, $gradeCode]
                );

                $this->db->query("COMMIT");
                $this->logger->debug('年级添加成功', [
                    'grade_id' => $this->db->lastInsertId(),
                    'grade_name' => $gradeName,
                    'user_id' => $_SESSION['user_id'] ?? null
                ]);

                return $this->json([
                    'success' => true,
                    'message' => '年级添加成功'
                ]);
            } catch (\Exception $e) {
                $this->db->query("ROLLBACK");
                throw $e;
            }
        } catch (\Exception $e) {
            $this->logger->error('添加年级失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'grade_name' => $_POST['grade_name'] ?? null,
                'user_id' => $_SESSION['user_id'] ?? null
            ]);
            return $this->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function checkName()
    {
        try {
            if (!$this->checkPermission('settings')) {
                return $this->json(['error' => '无权访问'], 403);
            }

            $name = $_GET['name'] ?? '';
            $id = $_GET['id'] ?? null;
            $settingId = $_GET['setting_id'] ?? null;

            if (empty($name) || empty($settingId)) {
                return $this->json(['error' => '年级名称和项目ID不能为空'], 400);
            }

            $sql = "SELECT COUNT(*) as count FROM grades WHERE grade_name = ? AND setting_id = ?";
            $params = [$name, $settingId];

            if ($id) {
                $sql .= " AND id != ?";
                $params[] = $id;
            }

            $stmt = $this->db->query($sql, $params);
            $result = $stmt->fetch();

            return $this->json([
                'success' => true,
                'exists' => $result['count'] > 0
            ]);
        } catch (Exception $e) {
            $this->logger->error('检查年级名称失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function checkCode()
    {
        try {
            if (!$this->checkPermission('settings')) {
                return $this->json(['error' => '无权访问'], 403);
            }

            $code = $_GET['code'] ?? '';
            $id = $_GET['id'] ?? null;
            $settingId = $_GET['setting_id'] ?? null;

            if (empty($code) || empty($settingId)) {
                return $this->json(['error' => '年级代码和项目ID不能为空'], 400);
            }

            $sql = "SELECT COUNT(*) as count FROM grades WHERE grade_code = ? AND setting_id = ?";
            $params = [$code, $settingId];

            if ($id) {
                $sql .= " AND id != ?";
                $params[] = $id;
            }

            $stmt = $this->db->query($sql, $params);
            $result = $stmt->fetch();

            return $this->json([
                'success' => true,
                'exists' => $result['count'] > 0
            ]);
        } catch (Exception $e) {
            $this->logger->error('检查年级代码失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * 删除年级
     * 如果年级下存在班级，则无法删除
     */
    public function delete()
    {
        try {
            $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
            $setting_id = isset($_POST['setting_id']) ? intval($_POST['setting_id']) : 0;

            if (!$id) {
                throw new Exception('缺少年级ID');
            }
            if (!$setting_id) {
                throw new Exception('缺少项目ID');
            }

            $this->db->beginTransaction();

            try {
                // 先删除相关的统计分析数据
                $this->db->query(
                    "DELETE FROM score_analytics WHERE grade_id = ? AND setting_id = ?",
                    [$id, $setting_id]
                );

                // 再删除年级数据
                $this->db->query(
                    "DELETE FROM grades WHERE id = ? AND setting_id = ?",
                    [$id, $setting_id]
                );

                $this->db->commit();
                
                $this->success('删除成功');
            } catch (Exception $e) {
                $this->db->rollBack();
                throw $e;
            }
        } catch (Exception $e) {
            $this->error('删除失败：' . $e->getMessage());
        }
    }

    public function list() {
        // 调用原有的getList方法保持代码复用
        return $this->getList();
    }

    public function get() {
        try {
            if (!$this->checkPermission('settings')) {
                $this->logger->warning('获取年级详情权限不足', [
                    'user_id' => $_SESSION['user_id'] ?? null,
                    'role' => $_SESSION['role'] ?? null
                ]);
                return $this->json(['error' => '无权访问'], 403);
            }

            $id = $_GET['id'] ?? '';
            
            if (empty($id)) {
                $this->logger->warning('获取年级详情参数不完整', [
                    'id' => $id,
                    'user_id' => $_SESSION['user_id'] ?? null
                ]);
                return $this->json(['error' => '参数不完整'], 400);
            }

            $stmt = $this->db->query(
                "SELECT g.*, 
                (SELECT COUNT(*) FROM classes c WHERE c.grade_id = g.id) as class_count,
                (SELECT COUNT(*) FROM students s 
                 INNER JOIN classes c ON s.class_id = c.id 
                 WHERE c.grade_id = g.id) as student_count
                FROM grades g 
                WHERE g.id = ?",
                [$id]
            );

            $grade = $stmt->fetch();
            
            if (!$grade) {
                $this->logger->warning('年级不存在', [
                    'id' => $id,
                    'user_id' => $_SESSION['user_id'] ?? null
                ]);
                return $this->json(['error' => '年级不存在'], 404);
            }

            $this->logger->debug('获取年级详情成功', [
                'grade_id' => $id,
                'user_id' => $_SESSION['user_id'] ?? null
            ]);

            return $this->json([
                'success' => true,
                'data' => $grade
            ]);
        } catch (\Exception $e) {
            $this->logger->error('获取年级详情失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'id' => $_GET['id'] ?? null,
                'user_id' => $_SESSION['user_id'] ?? null
            ]);
            return $this->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function update() {
        try {
            if (!$this->checkPermission('settings')) {
                $this->logger->warning('更新年级权限不足', [
                    'user_id' => $_SESSION['user_id'] ?? null,
                    'role' => $_SESSION['role'] ?? null
                ]);
                return $this->json(['error' => '无权访问'], 403);
            }

            $id = $_POST['id'] ?? '';
            $gradeName = $_POST['grade_name'] ?? '';
            $gradeCode = $_POST['grade_code'] ?? '';

            if (empty($id) || empty($gradeName) || empty($gradeCode)) {
                $this->logger->warning('更新年级参数不完整', [
                    'id' => $id,
                    'grade_name' => $gradeName,
                    'grade_code' => $gradeCode,
                    'user_id' => $_SESSION['user_id'] ?? null
                ]);
                return $this->json(['error' => '请填写完整信息'], 400);
            }

            $this->logger->debug('尝试更新年级', [
                'grade_id' => $id,
                'grade_name' => $gradeName,
                'grade_code' => $gradeCode,
                'user_id' => $_SESSION['user_id'] ?? null
            ]);

            // 开始事务
            $this->db->query("START TRANSACTION");

            try {
                // 检查年级是否存在
                $stmt = $this->db->query(
                    "SELECT id FROM grades WHERE id = ?",
                    [$id]
                );
                if (!$stmt->fetch()) {
                    $this->db->query("ROLLBACK");
                    return $this->json(['error' => '年级不存在'], 404);
                }

                // 检查年级代码是否与其他年级重复
                $stmt = $this->db->query(
                    "SELECT COUNT(*) as count FROM grades WHERE grade_code = ? AND id != ? AND setting_id = ? AND status = 1",
                    [$gradeCode, $id, $_POST['setting_id']]
                );
                if ($stmt->fetch()['count'] > 0) {
                    $this->db->query("ROLLBACK");
                    return $this->json(['error' => '该年级代码已存在'], 400);
                }

                // 检查年级名称是否与其他年级重复
                $stmt = $this->db->query(
                    "SELECT COUNT(*) as count FROM grades WHERE grade_name = ? AND id != ? AND setting_id = ? AND status = 1",
                    [$gradeName, $id, $_POST['setting_id']]
                );
                if ($stmt->fetch()['count'] > 0) {
                    $this->db->query("ROLLBACK");
                    return $this->json(['error' => '年级名称已存在'], 400);
                }

                // 更新年级信息
                $this->db->query(
                    "UPDATE grades SET grade_name = ?, grade_code = ? WHERE id = ?",
                    [$gradeName, $gradeCode, $id]
                );

                $this->db->query("COMMIT");

                $this->logger->debug('年级更新成功', [
                    'grade_id' => $id,
                    'grade_name' => $gradeName,
                    'grade_code' => $gradeCode,
                    'user_id' => $_SESSION['user_id'] ?? null
                ]);

                return $this->json([
                    'success' => true,
                    'message' => '年级更新成功'
                ]);
            } catch (\Exception $e) {
                $this->db->query("ROLLBACK");
                throw $e;
            }
        } catch (\Exception $e) {
            $this->logger->error('更新年级失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'id' => $_POST['id'] ?? null,
                'grade_name' => $_POST['grade_name'] ?? null,
                'grade_code' => $_POST['grade_code'] ?? null,
                'user_id' => $_SESSION['user_id'] ?? null
            ]);
            return $this->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * 删除项目相关的成绩数据
     */
    public function deleteByProject() {
        try {
            $projectId = $_POST['project_id'] ?? '';
            
            if (empty($projectId)) {
                return $this->json(['success' => false, 'error' => '项目ID不能为空'], 400);
            }

            // 开始事务
            $this->db->beginTransaction();

            // 删除项目相关的成绩数据
            $this->db->query(
                "DELETE s FROM scores s 
                 INNER JOIN subjects sub ON s.subject_id = sub.id 
                 WHERE sub.setting_id = ?",
                [$projectId]
            );

            $this->db->commit();

            return $this->json([
                'success' => true,
                'message' => '成绩数据删除成功'
            ]);
        } catch (\Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return $this->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * 处理年级相关的动态路由
     */
    public function handleGradeAction() {
        try {
            // 检查权限
            if (!$this->checkPermission('grade_analytics')) {
                return $this->json([
                    'success' => false,
                    'error' => '您没有访问此功能的权限'
                ], 403);
            }

            $action = $_GET['action'] ?? '';
            
            switch ($action) {
                case 'analytics':
                    return $this->getGradeAnalytics();
                case 'student_rank':
                    return $this->getStudentRank();
                case 'subjects':
                    return $this->getGradeSubjects();
                default:
                    return $this->json(['success' => false, 'error' => '未知的操作'], 400);
            }
        } catch (\Exception $e) {
            $this->logger->error('年级数据处理失败: ' . $e->getMessage());
            return $this->json([
                'success' => false,
                'error' => '处理请求失败'
            ], 500);
        }
    }

    /**
     * 获取年级统计数据
     */
    private function getGradeAnalytics() {
        $grade_id = $_GET['grade_id'] ?? null;
        $subject_id = $_GET['subject_id'] ?? null;

        if (!$grade_id || !$subject_id) {
            return $this->json(['success' => false, 'error' => '参数不完整'], 400);
        }

        try {
            $sql = "SELECT 
                    COUNT(*) as total_students,
                    COUNT(CASE WHEN is_absent = 0 THEN 1 END) as attended_students,
                    COUNT(CASE WHEN is_absent = 1 THEN 1 END) as absent_students,
                    MAX(CASE WHEN is_absent = 0 THEN total_score END) as max_score,
                    MIN(CASE WHEN is_absent = 0 THEN total_score END) as min_score,
                    AVG(CASE WHEN is_absent = 0 THEN total_score END) as average_score,
                    COUNT(CASE WHEN is_absent = 0 AND total_score = 100 THEN 1 END) as score_100,
                    COUNT(CASE WHEN is_absent = 0 AND total_score >= 95 AND total_score < 100 THEN 1 END) as score_95_99,
                    COUNT(CASE WHEN is_absent = 0 AND total_score >= 90 AND total_score < 95 THEN 1 END) as score_90_94,
                    COUNT(CASE WHEN is_absent = 0 AND total_score >= 85 AND total_score < 90 THEN 1 END) as score_85_89,
                    COUNT(CASE WHEN is_absent = 0 AND total_score >= 80 AND total_score < 85 THEN 1 END) as score_80_84,
                    COUNT(CASE WHEN is_absent = 0 AND total_score >= 75 AND total_score < 80 THEN 1 END) as score_75_79,
                    COUNT(CASE WHEN is_absent = 0 AND total_score >= 70 AND total_score < 75 THEN 1 END) as score_70_74,
                    COUNT(CASE WHEN is_absent = 0 AND total_score >= 65 AND total_score < 70 THEN 1 END) as score_65_69,
                    COUNT(CASE WHEN is_absent = 0 AND total_score >= 60 AND total_score < 65 THEN 1 END) as score_60_64,
                    COUNT(CASE WHEN is_absent = 0 AND total_score >= 55 AND total_score < 60 THEN 1 END) as score_55_59,
                    COUNT(CASE WHEN is_absent = 0 AND total_score >= 50 AND total_score < 55 THEN 1 END) as score_50_54,
                    COUNT(CASE WHEN is_absent = 0 AND total_score >= 40 AND total_score < 50 THEN 1 END) as score_40_49,
                    COUNT(CASE WHEN is_absent = 0 AND total_score < 40 THEN 1 END) as score_below_40,
                    COUNT(CASE WHEN is_absent = 0 AND total_score >= excellent_score THEN 1 END) as excellent_count,
                    COUNT(CASE WHEN is_absent = 0 AND total_score >= good_score AND total_score < excellent_score THEN 1 END) as good_count,
                    COUNT(CASE WHEN is_absent = 0 AND total_score >= pass_score AND total_score < good_score THEN 1 END) as pass_count,
                    COUNT(CASE WHEN is_absent = 0 AND total_score < pass_score THEN 1 END) as fail_count
                FROM scores s
                JOIN students st ON s.student_id = st.id
                JOIN classes c ON st.class_id = c.id
                JOIN subjects sub ON s.subject_id = sub.id
                WHERE c.grade_id = ? AND s.subject_id = ?
                AND s.setting_id = (SELECT id FROM settings WHERE status = 1 LIMIT 1)";

            $stmt = $this->db->query($sql, [$grade_id, $subject_id]);
            $analytics = $stmt->fetch();

            // 计算及格率和优秀率
            if ($analytics['attended_students'] > 0) {
                $analytics['pass_rate'] = round(($analytics['pass_count'] + $analytics['good_count'] + $analytics['excellent_count']) / $analytics['attended_students'] * 100, 2);
                $analytics['excellent_rate'] = round($analytics['excellent_count'] / $analytics['attended_students'] * 100, 2);
            } else {
                $analytics['pass_rate'] = 0;
                $analytics['excellent_rate'] = 0;
            }

            // 添加分数分布数组
            $analytics['score_distribution'] = [
                $analytics['score_100'],
                $analytics['score_95_99'],
                $analytics['score_90_94'],
                $analytics['score_85_89'],
                $analytics['score_80_84'],
                $analytics['score_75_79'],
                $analytics['score_70_74'],
                $analytics['score_65_69'],
                $analytics['score_60_64'],
                $analytics['score_55_59'],
                $analytics['score_50_54'],
                $analytics['score_40_49'],
                $analytics['score_below_40']
            ];

            return $this->json([
                'success' => true,
                'data' => $analytics
            ]);
        } catch (\Exception $e) {
            $this->logger->error('获取年级统计数据失败: ' . $e->getMessage());
            return $this->json([
                'success' => false,
                'error' => '获取统计数据失败'
            ], 500);
        }
    }

    /**
     * 获取学生成绩排名
     */
    private function getStudentRank() {
        $grade_id = $_GET['grade_id'] ?? null;
        $subject_id = $_GET['subject_id'] ?? null;
        $limit = intval($_GET['limit'] ?? 50);
        $offset = intval($_GET['offset'] ?? 0);

        if (!$grade_id || !$subject_id) {
            return $this->json(['success' => false, 'error' => '参数不完整'], 400);
        }

        try {
            // 获取总记录数
            $countSql = "SELECT COUNT(*) as total 
                        FROM scores s
                        JOIN students st ON s.student_id = st.id
                        JOIN classes c ON st.class_id = c.id
                        WHERE c.grade_id = ? AND s.subject_id = ?
                        AND s.setting_id = (SELECT id FROM settings WHERE status = 1 LIMIT 1)";
            
            $stmt = $this->db->query($countSql, [$grade_id, $subject_id]);
            $total = $stmt->fetch()['total'];

            // 获取成绩列表
            $sql = "SELECT 
                    st.student_number,
                    st.student_name,
                    c.class_name,
                    CAST(s.total_score AS DECIMAL(10,2)) as total_score,
                    s.is_absent
                FROM scores s
                JOIN students st ON s.student_id = st.id
                JOIN classes c ON st.class_id = c.id
                WHERE c.grade_id = ? AND s.subject_id = ?
                AND s.setting_id = (SELECT id FROM settings WHERE status = 1 LIMIT 1)
                ORDER BY s.total_score DESC, st.student_number
                LIMIT {$limit} OFFSET {$offset}";

            $stmt = $this->db->query($sql, [$grade_id, $subject_id]);
            $ranks = $stmt->fetchAll();

            return $this->json([
                'success' => true,
                'data' => [
                    'total' => $total,
                    'ranks' => $ranks
                ]
            ]);
        } catch (\Exception $e) {
            $this->logger->error('获取学生成绩排名失败: ' . $e->getMessage());
            return $this->json([
                'success' => false,
                'error' => '获取成绩排名失败'
            ], 500);
        }
    }

    /**
     * 获取所有年级和科目
     */
    public function getGradeSubjects() {
        try {
            $gradeId = $_GET['grade_id'] ?? null;
            $settingId = $_GET['setting_id'] ?? null;

            if (!$gradeId) {
                return $this->json(['error' => '年级ID不能为空'], 400);
            }

            // 构建查询SQL
            $sql = "SELECT s.*, sg.grade_id 
                   FROM subjects s 
                   INNER JOIN subject_grades sg ON s.id = sg.subject_id 
                   WHERE sg.grade_id = ?";
            $params = [$gradeId];

            // 如果提供了setting_id，添加项目过滤
            if ($settingId) {
                $sql .= " AND s.setting_id = ?";
                $params[] = $settingId;
            }

            $stmt = $this->db->query($sql, $params);
            $subjects = $stmt->fetchAll();

            return $this->json([
                'success' => true,
                'data' => $subjects
            ]);
        } catch (\Exception $e) {
            $this->logger->error('获取年级科目列表失败: ' . $e->getMessage());
            return $this->json([
                'success' => false,
                'error' => '获取年级科目列表失败'
            ], 500);
        }
    }

    /**
     * 获取班级统计数据
     */
    public function classAnalytics() {
        try {
            // 检查权限
            if (!$this->checkPermission('grade_analytics')) {
                return $this->json([
                    'success' => false,
                    'error' => '您没有访问此功能的权限'
                ], 403);
            }

            $grade_id = $_GET['grade_id'] ?? null;
            $subject_id = $_GET['subject_id'] ?? null;
            $class_id = $_GET['class_id'] ?? null;

            if (!$grade_id || !$subject_id || !$class_id) {
                return $this->json(['success' => false, 'error' => '参数不完整'], 400);
            }

            $sql = "SELECT * FROM score_analytics 
                    WHERE grade_id = ? 
                    AND subject_id = ? 
                    AND class_id = ?
                    AND setting_id = (SELECT id FROM settings WHERE status = 1 LIMIT 1)";

            $stmt = $this->db->query($sql, [$grade_id, $subject_id, $class_id]);
            $analytics = $stmt->fetch();

            if (!$analytics) {
                return $this->json([
                    'success' => false,
                    'error' => '统计数据不存在'
                ], 404);
            }

            return $this->json([
                'success' => true,
                'data' => $analytics
            ]);
        } catch (\Exception $e) {
            $this->logger->error('获取班级统计数据失败: ' . $e->getMessage());
            return $this->json([
                'success' => false,
                'error' => '获取统计数据失败'
            ], 500);
        }
    }

    /**
     * 获取学生成绩排名
     */
    public function studentRank() {
        try {
            // 检查权限
            if (!$this->checkPermission('grade_analytics')) {
                return $this->json([
                    'success' => false,
                    'error' => '您没有访问此功能的权限'
                ], 403);
            }

            $grade_id = $_GET['grade_id'] ?? null;
            $subject_id = $_GET['subject_id'] ?? null;
            $limit = min(intval($_GET['limit'] ?? 50), 100); // 限制最大返回数量
            $offset = max(intval($_GET['offset'] ?? 0), 0); // 确保offset不为负

            if (!$grade_id || !$subject_id) {
                return $this->json(['success' => false, 'error' => '参数不完整'], 400);
            }

            // 获取总记录数
            $countSql = "SELECT COUNT(*) as total 
                        FROM scores s
                        JOIN students st ON s.student_id = st.id
                        JOIN classes c ON st.class_id = c.id
                        WHERE c.grade_id = ? AND s.subject_id = ?
                        AND s.setting_id = (SELECT id FROM settings WHERE status = 1 LIMIT 1)";
            
            $stmt = $this->db->query($countSql, [$grade_id, $subject_id]);
            $total = $stmt->fetch()['total'];

            // 获取成绩列表
            $sql = "SELECT 
                    st.student_number,
                    st.student_name,
                    c.class_name,
                    CAST(s.total_score AS DECIMAL(10,2)) as total_score,
                    s.is_absent
                FROM scores s
                JOIN students st ON s.student_id = st.id
                JOIN classes c ON st.class_id = c.id
                WHERE c.grade_id = ? AND s.subject_id = ?
                AND s.setting_id = (SELECT id FROM settings WHERE status = 1 LIMIT 1)
                ORDER BY s.total_score DESC, st.student_number
                LIMIT ? OFFSET ?";

            $stmt = $this->db->query($sql, [$grade_id, $subject_id, $limit, $offset]);
            $ranks = $stmt->fetchAll();

            return $this->json([
                'success' => true,
                'data' => [
                    'total' => $total,
                    'ranks' => $ranks
                ]
            ]);
        } catch (\Exception $e) {
            $this->logger->error('获取学生成绩排名失败: ' . $e->getMessage());
            return $this->json([
                'success' => false,
                'error' => '获取成绩排名失败'
            ], 500);
        }
    }

    public function getSubjects() {
        try {
            // 检查用户是否登录
            if (!isset($_SESSION['user_id'])) {
                return $this->json(['error' => '未登录'], 401);
            }

            // 允许 admin 和 teaching 角色访问
            if ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'teaching' && !$this->checkPermission('settings')) {
                return $this->json(['error' => '无权访问'], 403);
            }

            $gradeId = $_GET['grade_id'] ?? null;
            $settingId = $_GET['setting_id'] ?? null;

            if (!$gradeId) {
                return $this->json(['error' => '年级ID不能为空'], 400);
            }

            // 构建查询SQL
            $sql = "SELECT s.*, sg.grade_id 
                   FROM subjects s 
                   INNER JOIN subject_grades sg ON s.id = sg.subject_id 
                   WHERE sg.grade_id = ?";
            $params = [$gradeId];

            // 如果提供了setting_id，添加项目过滤
            if ($settingId) {
                $sql .= " AND s.setting_id = ?";
                $params[] = $settingId;
            }

            $stmt = $this->db->query($sql, $params);
            $subjects = $stmt->fetchAll();

            return $this->json([
                'success' => true,
                'data' => $subjects
            ]);
        } catch (\Exception $e) {
            $this->logger->error('获取年级科目列表失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'grade_id' => $_GET['grade_id'] ?? null,
                'setting_id' => $_GET['setting_id'] ?? null,
                'user_id' => $_SESSION['user_id'] ?? null
            ]);
            return $this->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * 检查是否可以进行年级升级
     * 
     * @return array
     */
    public function checkUpgradable()
    {
        try {
            // 检查权限
            if (!$this->checkPermission('settings')) {
                return $this->json([
                    'success' => false,
                    'error' => '无权访问此功能'
                ], 403);
            }

            // 获取当前项目ID
            $settingId = $this->getCurrentProjectId();
            if (!$settingId) {
                throw new Exception('未找到当前项目');
            }
            
            // 检查是否存在成绩数据
            $stmt = $this->db->query(
                "SELECT COUNT(*) as count 
                FROM scores s 
                INNER JOIN students st ON s.student_id = st.id 
                WHERE st.setting_id = ? AND st.status = 1",
                [$settingId]
            );
            $result = $stmt->fetch();
            
            if ($result['count'] > 0) {
                return $this->json([
                    'success' => true,
                    'upgradable' => false,
                    'message' => '有班级存在成绩数据，无法使用一键升级'
                ]);
            }
            
            // 获取所有年级
            $stmt = $this->db->query(
                "SELECT grade_code 
                FROM grades 
                WHERE setting_id = ? AND status = 1",
                [$settingId]
            );
            $grades = $stmt->fetchAll();
            
            // 检查是否有年级代码不是以数字结尾
            foreach ($grades as $grade) {
                if (!preg_match('/\d$/', $grade['grade_code'])) {
                    return $this->json([
                        'success' => true,
                        'upgradable' => false,
                        'message' => '有年级代码非数字结尾，无法使用一键升级'
                    ]);
                }
            }
            
            // 检查是否有可升级的年级（至少有一个年级代码以数字结尾）
            if (empty($grades)) {
                return $this->json([
                    'success' => true,
                    'upgradable' => false,
                    'message' => '没有可升级的年级'
                ]);
            }
            
            return $this->json([
                'success' => true,
                'upgradable' => true,
                'message' => '可以进行年级升级'
            ]);
            
        } catch (Exception $e) {
            $this->logger->error('检查年级升级状态失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => $_SESSION['user_id'] ?? null
            ]);
            return $this->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * 执行年级升级
     * 
     * @return array
     */
    public function upgrade()
    {
        try {
            $this->db->beginTransaction();
            
            // 获取当前项目ID
            $settingId = $this->getCurrentProjectId();
            if (!$settingId) {
                throw new Exception('未找到当前项目');
            }
            
            // 1. 删除六年级的数据
            $stmt = $this->db->query("
                SELECT g.id as grade_id, c.id as class_id 
                FROM grades g 
                LEFT JOIN classes c ON g.id = c.grade_id 
                WHERE g.setting_id = ? AND g.status = 1 
                AND g.grade_code LIKE '%6'
            ", [$settingId]);
            $sixthGradeData = $stmt->fetchAll();
            
            foreach ($sixthGradeData as $data) {
                // 删除学生
                if ($data['class_id']) {
                    $this->db->query("DELETE FROM students WHERE class_id = ?", [$data['class_id']]);
                }
                // 删除班级
                $this->db->query("DELETE FROM classes WHERE grade_id = ?", [$data['grade_id']]);
            }
            // 删除年级
            $this->db->query("DELETE FROM grades WHERE setting_id = ? AND grade_code LIKE '%6'", [$settingId]);
            
            // 2. 获取需要升级的年级（按年级代码倒序，从5年级开始）
            $stmt = $this->db->query("
                SELECT id, grade_name, grade_code 
                FROM grades 
                WHERE setting_id = ? AND status = 1 
                AND grade_code REGEXP '[0-9]$'
                ORDER BY grade_code DESC
            ", [$settingId]);
            $grades = $stmt->fetchAll();
            
            foreach ($grades as $grade) {
                // 解析年级代码和名称
                $currentNumber = substr($grade['grade_code'], -1);
                $newNumber = $currentNumber + 1;
                $newGradeCode = substr($grade['grade_code'], 0, -1) . $newNumber;
                
                // 更新年级名称（替换中文数字）
                $chineseNumbers = ['一', '二', '三', '四', '五', '六'];
                $newGradeName = str_replace(
                    $chineseNumbers[$currentNumber - 1],
                    $chineseNumbers[$newNumber - 1],
                    $grade['grade_name']
                );
                
                // 更新年级信息
                $this->db->query("
                    UPDATE grades 
                    SET grade_code = ?, grade_name = ? 
                    WHERE id = ?
                ", [$newGradeCode, $newGradeName, $grade['id']]);
                
                // 获取该年级下的所有班级
                $stmt = $this->db->query("
                    SELECT id, class_code 
                    FROM classes 
                    WHERE grade_id = ? AND status = 1
                ", [$grade['id']]);
                $classes = $stmt->fetchAll();
                
                foreach ($classes as $class) {
                    // 更新班级代码
                    $newClassCode = $newGradeCode . substr($class['class_code'], -2);
                    $this->db->query("
                        UPDATE classes 
                        SET class_code = ? 
                        WHERE id = ?
                    ", [$newClassCode, $class['id']]);
                    
                    // 更新学生编号
                    $this->db->query("
                        UPDATE students 
                        SET student_number = CONCAT(?, SUBSTRING(student_number, 4))
                        WHERE class_id = ? AND status = 1
                    ", [$newClassCode, $class['id']]);
                }
            }
            
            $this->db->commit();
            return $this->json([
                'success' => true,
                'message' => '年级升级成功'
            ]);
            
        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            $this->logger->error('年级升级失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * 获取当前项目ID
     */
    private function getCurrentProjectId()
    {
        $stmt = $this->db->query("SELECT id FROM settings WHERE status = 1 LIMIT 1");
        $result = $stmt->fetch();
        return $result ? $result['id'] : null;
    }

    private function updateDistribution(&$distribution, $score) {
        if ($score >= 99.5) {
            $distribution['100']++;
        } elseif ($score >= 94.5) {
            $distribution['99.5-95']++;
        } elseif ($score >= 89.5) {
            $distribution['94.5-90']++;
        } elseif ($score >= 84.5) {
            $distribution['89.5-85']++;
        } elseif ($score >= 79.5) {
            $distribution['84.5-80']++;
        } elseif ($score >= 74.5) {
            $distribution['79.5-75']++;
        } elseif ($score >= 69.5) {
            $distribution['74.5-70']++;
        } elseif ($score >= 64.5) {
            $distribution['69.5-65']++;
        } elseif ($score >= 59.5) {
            $distribution['64.5-60']++;
        } elseif ($score >= 54.5) {
            $distribution['59.5-55']++;
        } elseif ($score >= 49.5) {
            $distribution['54.5-50']++;
        } elseif ($score >= 40) {
            $distribution['49.5-40']++;
        } else {
            $distribution['40以下']++;
        }

        // 处理合并区间的统计
        if ($score >= 89.5 && $score < 99.5) {
            if (!isset($distribution['99.5-90'])) {
                $distribution['99.5-90'] = 0;
            }
            $distribution['99.5-90']++;
        }
        
        if ($score >= 79.5 && $score < 89.5) {
            if (!isset($distribution['89.5-80'])) {
                $distribution['89.5-80'] = 0;
            }
            $distribution['89.5-80']++;
        }
        
        if ($score >= 69.5 && $score < 79.5) {
            if (!isset($distribution['79.5-70'])) {
                $distribution['79.5-70'] = 0;
            }
            $distribution['79.5-70']++;
        }
        
        if ($score >= 59.5 && $score < 69.5) {
            if (!isset($distribution['69.5-60'])) {
                $distribution['69.5-60'] = 0;
            }
            $distribution['69.5-60']++;
        }
    }

    /**
     * 获取项目下所有年级（不考虑项目状态）
     */
    public function getAllGrades() {
        try {
            $setting_id = isset($_GET['setting_id']) ? intval($_GET['setting_id']) : 0;
            
            if (!$setting_id) {
                return $this->json(['success' => false, 'error' => '缺少项目ID'], 400);
            }

            // 直接获取项目下所有年级，按年级代码排序
            $stmt = $this->db->query(
                "SELECT * FROM grades WHERE setting_id = ? ORDER BY grade_code",
                [$setting_id]
            );
            $grades = $stmt->fetchAll();
            
            return $this->json([
                'success' => true,
                'data' => $grades
            ]);
        } catch (Exception $e) {
            $this->logger->error('获取年级列表失败: ' . $e->getMessage());
            return $this->json([
                'success' => false,
                'error' => '获取年级列表失败：' . $e->getMessage()
            ], 500);
        }
    }
} 