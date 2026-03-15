<?php
/**
 * 文件名: controllers/ScoreController.php
 * 功能描述: 成绩管理控制器
 * 
 * 该控制器负责:
 * 1. 学生成绩的增删改查
 * 2. 成绩批量录入和验证
 * 3. 缺考标记管理
 * 4. 教师有权限的科目和班级获取
 * 5. 检查成绩录入完成情况
 * 
 * API调用路由:
 * - score/teacher_subjects: 获取教师有权限的科目
 * - score/teacher_classes: 获取教师有权限的班级
 * - score/student_scores: 获取学生成绩
 * - score/save: 保存学生成绩
 * - score/absent: 标记学生缺考
 * - score/check_all: 检查所有成绩是否录入
 * - score/classes_by_grade_subject: 获取特定年级和科目的班级
 * 
 * 关联文件:
 * - core/Controller.php: 基础控制器
 * - modules/scores.php: 成绩录入页面
 * - controllers/ClassAnalyticsController.php: 班级分析控制器，更新分析数据
 * - scores表: 存储成绩数据的表
 */

namespace Controllers;

use Core\Controller;

class ScoreController extends Controller {
    // 获取教师有权限的科目和年级
    public function getTeacherSubjects() {
        try {
            $userId = $_SESSION['user_id'] ?? '';
            $role = $_SESSION['role'] ?? '';
            $gradeId = $_GET['grade_id'] ?? null; // 添加年级ID参数
            
            if (empty($userId)) {
                throw new \Exception('未登录');
            }

            // 获取当前启用的项目ID
            $stmt = $this->db->query(
                "SELECT id FROM settings WHERE status = 1 ORDER BY id DESC LIMIT 1"
            );
            $setting = $stmt->fetch();
            $settingId = $setting['id'] ?? null;

            if (!$settingId) {
                return $this->json(['success' => false, 'error' => '未找到启用的项目'], 400);
            }

            error_log("getTeacherSubjects - User ID: " . $userId);
            error_log("getTeacherSubjects - User Role: " . $role);
            error_log("getTeacherSubjects - Grade ID: " . $gradeId);

            // 添加年级筛选条件
            $gradeCondition = $gradeId ? "AND g.id = ?" : "";

            // 管理员和教导处可以看到所有科目和年级
            if ($role === 'admin' || $role === 'teaching') {
                $sql = "SELECT DISTINCT g.id as grade_id, g.grade_name, s.id as subject_id, s.subject_name, s.subject_code,
                ss.full_score, ss.excellent_score, ss.good_score, ss.pass_score,
                ss.is_split, ss.split_name_1, ss.split_name_2, ss.split_score_1, ss.split_score_2,
                1 as can_edit, 1 as can_download
                FROM grades g 
                INNER JOIN subject_grades sg ON g.id = sg.grade_id
                INNER JOIN subjects s ON sg.subject_id = s.id
                LEFT JOIN subject_settings ss ON s.id = ss.subject_id
                WHERE g.status = 1 
                AND s.status = 1
                AND s.setting_id = ?
                {$gradeCondition}
                ORDER BY g.grade_code, s.subject_code";
                $params = $gradeId ? [$settingId, $gradeId] : [$settingId];
            } else {
                $sql = "SELECT DISTINCT g.id as grade_id, g.grade_name, s.id as subject_id, s.subject_name, s.subject_code,
                ss.full_score, ss.excellent_score, ss.good_score, ss.pass_score,
                ss.is_split, ss.split_name_1, ss.split_name_2, ss.split_score_1, ss.split_score_2,
                up.can_edit, up.can_download
                FROM user_permissions up 
                INNER JOIN grades g ON up.grade_id = g.id 
                INNER JOIN subjects s ON up.subject_id = s.id
                LEFT JOIN subject_settings ss ON s.id = ss.subject_id
                WHERE up.user_id = ? 
                AND s.status = 1 
                AND g.status = 1
                AND s.setting_id = ?
                {$gradeCondition}
                AND EXISTS (
                    SELECT 1 FROM subject_grades sg 
                    WHERE sg.subject_id = s.id 
                    AND sg.grade_id = g.id
                )
                ORDER BY g.grade_code, s.subject_code";
                $params = $gradeId ? [$userId, $settingId, $gradeId] : [$userId, $settingId];
            }
            
            error_log("getTeacherSubjects - SQL: " . $sql);
            error_log("getTeacherSubjects - Params: " . json_encode($params));
            
            $stmt = $this->db->query($sql, $params);
            $data = $stmt->fetchAll();
            
            error_log("getTeacherSubjects - Found data: " . json_encode($data));
            
            $this->json([
                'success' => true,
                'data' => $data
            ]);
        } catch (\Exception $e) {
            error_log("Error in getTeacherSubjects: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            $this->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // 获取班级学生列表和成绩
    public function getStudentScores() {
        try {
            $classId = $_GET['class_id'] ?? '';
            $subjectId = $_GET['subject_id'] ?? '';
            $userId = $_SESSION['user_id'] ?? '';
            $role = $_SESSION['role'] ?? '';

            if (empty($classId) || empty($subjectId) || empty($userId)) {
                throw new \Exception('参数不完整');
            }

            error_log("getStudentScores - User ID: " . $userId);
            error_log("getStudentScores - User Role: " . $role);
            error_log("getStudentScores - Class ID: " . $classId);
            error_log("getStudentScores - Subject ID: " . $subjectId);

            // 如果不是管理员或教导处，验证权限
            if (!in_array($role, ['admin', 'teaching'])) {
                $stmt = $this->db->query(
                    "SELECT up.* FROM user_permissions up
                    JOIN classes c ON up.grade_id = c.grade_id
                    WHERE up.user_id = ? 
                    AND c.id = ? 
                    AND up.subject_id = ? 
                    AND up.can_edit = 1",
                    [$userId, $classId, $subjectId]
                );
                
                if (!$stmt->fetch()) {
                    throw new \Exception('无权限操作此班级或科目');
                }
            }

            // 获取科目信息
            $stmt = $this->db->query(
                "SELECT s.*, ss.full_score, ss.excellent_score, ss.good_score, ss.pass_score,
                ss.is_split, ss.split_name_1, ss.split_name_2, ss.split_score_1, ss.split_score_2  
                FROM subjects s 
                LEFT JOIN subject_settings ss ON s.id = ss.subject_id 
                WHERE s.id = ?",
                [$subjectId]
            );
            $subject = $stmt->fetch();

            if (!$subject) {
                throw new \Exception('未找到科目信息');
            }
            
            // 如果subject_settings为空（full_score为null），则使用subjects表的设置
            if ($subject['full_score'] === null) {
                $subject['full_score'] = $subject['full_score'] ?? 100;
                $subject['excellent_score'] = $subject['excellent_score'] ?? 85;
                $subject['good_score'] = $subject['good_score'] ?? 70;
                $subject['pass_score'] = $subject['pass_score'] ?? 60;
                
                // 尝试将这些设置写入subject_settings表
                try {
                    // 获取当前启用的项目ID
                    $stmt = $this->db->query(
                        "SELECT id FROM settings WHERE status = 1 ORDER BY id DESC LIMIT 1"
                    );
                    $setting = $stmt->fetch();
                    $settingId = $setting['id'] ?? null;
                    
                    if ($settingId) {
                        $this->db->query(
                            "INSERT INTO subject_settings (
                                setting_id, subject_id, full_score, excellent_score, good_score, pass_score,
                                is_split, split_name_1, split_name_2, split_score_1, split_score_2
                            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                            ON DUPLICATE KEY UPDATE
                                full_score = VALUES(full_score),
                                excellent_score = VALUES(excellent_score),
                                good_score = VALUES(good_score),
                                pass_score = VALUES(pass_score)",
                            [
                                $settingId, $subjectId, 
                                $subject['full_score'], $subject['excellent_score'], 
                                $subject['good_score'], $subject['pass_score'],
                                $subject['is_split'] ?? 0, $subject['split_name_1'], 
                                $subject['split_name_2'], $subject['split_score_1'], 
                                $subject['split_score_2']
                            ]
                        );
                    }
                } catch (\Exception $e) {
                    // 记录错误但继续执行，不影响正常获取成绩
                    error_log("Error syncing subject settings: " . $e->getMessage());
                }
            }

            // 获取学生列表和成绩
            $sql = "SELECT 
                s.id, s.student_name, s.student_number,
                sc.id as score_id,
                sc.base_score,
                sc.extra_score,
                sc.total_score,
                sc.is_absent,
                CASE
                    WHEN sc.is_absent = 1 THEN '缺考'
                    WHEN sc.total_score >= ? THEN '优秀'
                    WHEN sc.total_score >= ? THEN '良好'
                    WHEN sc.total_score >= ? THEN '合格'
                    ELSE '待合格'
                END as score_level
            FROM students s
            LEFT JOIN scores sc ON s.id = sc.student_id 
                AND sc.subject_id = ?
            WHERE s.class_id = ? 
            AND s.status = 1
            ORDER BY s.student_number";
            
            error_log("getStudentScores - SQL: " . $sql);
            error_log("getStudentScores - Params: " . json_encode([$subject['excellent_score'], $subject['good_score'], $subject['pass_score'], $subjectId, $classId]));

            $stmt = $this->db->query($sql, [$subject['excellent_score'], $subject['good_score'], $subject['pass_score'], $subjectId, $classId]);
            $result = $stmt->fetchAll();

            error_log("getStudentScores - Found students: " . json_encode($result));

            $this->json([
                'success' => true,
                'data' => $result,
                'subject' => $subject
            ]);
        } catch (\Exception $e) {
            error_log("Error in getStudentScores: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            
            $this->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // 保存成绩
    public function saveScore() {
        try {
            $studentId = $_POST['student_id'] ?? '';
            $subjectId = $_POST['subject_id'] ?? '';
            $classId = $_POST['class_id'] ?? '';
            $gradeId = $_POST['grade_id'] ?? '';
            $isAbsent = isset($_POST['is_absent']) ? (int)$_POST['is_absent'] : 0;
            $baseScore = isset($_POST['base_score']) ? $_POST['base_score'] : null;
            $extraScore = isset($_POST['extra_score']) ? $_POST['extra_score'] : null;
            $totalScore = isset($_POST['total_score']) ? $_POST['total_score'] : null;

            if (empty($studentId) || empty($subjectId) || empty($classId) || empty($gradeId)) {
                throw new \Exception('参数不完整');
            }

            // 获取当前启用的项目ID
            $stmt = $this->db->query(
                "SELECT id FROM settings WHERE status = 1 ORDER BY id DESC LIMIT 1"
            );
            $setting = $stmt->fetch();
            $settingId = $setting['id'] ?? null;

            if (!$settingId) {
                throw new \Exception('未找到启用的项目');
            }

            // 获取科目设置
            $stmt = $this->db->query(
                "SELECT * FROM subject_settings WHERE subject_id = ? AND setting_id = ?",
                [$subjectId, $settingId]
            );
            $subjectSettings = $stmt->fetch();

            if (!$subjectSettings) {
                // 如果subject_settings中没有记录，尝试从subjects表获取分数设置
                $stmt = $this->db->query(
                    "SELECT id, full_score, excellent_score, good_score, pass_score,
                     is_split, split_name_1, split_name_2, split_score_1, split_score_2
                     FROM subjects WHERE id = ? AND setting_id = ?",
                    [$subjectId, $settingId]
                );
                $subjectSettings = $stmt->fetch();
                
                if (!$subjectSettings) {
                    throw new \Exception('未找到科目设置');
                }
                
                // 当找到subjects表中的设置，同时将其写入subject_settings表，保证下次能正常使用
                $this->db->query(
                    "INSERT INTO subject_settings (
                        setting_id, subject_id, full_score, excellent_score, good_score, pass_score,
                        is_split, split_name_1, split_name_2, split_score_1, split_score_2
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                    [
                        $settingId, $subjectId, 
                        $subjectSettings['full_score'], $subjectSettings['excellent_score'], 
                        $subjectSettings['good_score'], $subjectSettings['pass_score'],
                        $subjectSettings['is_split'], $subjectSettings['split_name_1'], 
                        $subjectSettings['split_name_2'], $subjectSettings['split_score_1'], 
                        $subjectSettings['split_score_2']
                    ]
                );
            }

            // 计算等级和样式类
            $scoreLevel = '';
            $levelClass = '';
            if ($isAbsent) {
                $scoreLevel = '缺考';
                $levelClass = 'level-absent';
                $baseScore = null;
                $extraScore = null;
                $totalScore = null;
            } else if ($totalScore !== null) {
                $totalScore = floatval($totalScore);
                if ($totalScore >= $subjectSettings['excellent_score']) {
                    $scoreLevel = '优秀';
                    $levelClass = 'level-excellent';
                } else if ($totalScore >= $subjectSettings['good_score']) {
                    $scoreLevel = '良好';
                    $levelClass = 'level-good';
                } else if ($totalScore >= $subjectSettings['pass_score']) {
                    $scoreLevel = '合格';
                    $levelClass = 'level-pass';
                } else {
                    $scoreLevel = '待合格';
                    $levelClass = 'level-fail';
                }
            }

            // 检查是否已存在记录，同时获取原始成绩信息
            $stmt = $this->db->query(
                "SELECT id, total_score, is_absent FROM scores WHERE student_id = ? AND subject_id = ?",
                [$studentId, $subjectId]
            );
            $existing = $stmt->fetch();

            // 获取学生和学科信息用于日志
            $stmt = $this->db->query(
                "SELECT s.student_name, g.grade_name, c.class_name, sub.subject_name 
                FROM students s 
                INNER JOIN classes c ON s.class_id = c.id 
                INNER JOIN grades g ON c.grade_id = g.id 
                INNER JOIN subjects sub ON sub.id = ? 
                WHERE s.id = ?",
                [$subjectId, $studentId]
            );
            $info = $stmt->fetch();

            // 准备日志信息
            $logAction = $existing ? 'edit' : 'create';
            $logDetail = '';
            
            if ($existing) {
                // 获取修改前的成绩
                $oldScore = $existing['is_absent'] ? '缺考' : ($existing['total_score'] !== null ? $existing['total_score'] : '空');
                // 获取修改后的成绩
                $newScore = $isAbsent ? '缺考' : ($totalScore !== null ? $totalScore : '空');
                // 构建日志详情
                $logDetail = "{$info['grade_name']}{$info['class_name']} {$info['student_name']} {$info['subject_name']} {$oldScore}=>{$newScore}";

                // 更新记录
                $stmt = $this->db->query(
                    "UPDATE scores SET 
                    is_absent = ?,
                    base_score = ?,
                    extra_score = ?,
                    total_score = ?,
                    score_level = ?,
                    updated_at = CURRENT_TIMESTAMP
                    WHERE id = ?",
                    [$isAbsent, $baseScore, $extraScore, $totalScore, $scoreLevel, $existing['id']]
                );
            } else {
                // 首次录入
                $newScore = $isAbsent ? '缺考' : ($totalScore !== null ? $totalScore : '空');
                $logDetail = "{$info['grade_name']}{$info['class_name']} {$info['student_name']} {$info['subject_name']} 录入：{$newScore}";

                // 插入新记录，包含 setting_id
                $stmt = $this->db->query(
                    "INSERT INTO scores 
                    (student_id, subject_id, class_id, grade_id, setting_id, is_absent, base_score, extra_score, total_score, score_level) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                    [$studentId, $subjectId, $classId, $gradeId, $settingId, $isAbsent, $baseScore, $extraScore, $totalScore, $scoreLevel]
                );
            }

            // 获取客户端IP地址
            $ipAddress = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
            
            // 记录操作日志
            $this->logger->info($logDetail, [
                'action' => $logAction,
                'username' => $_SESSION['username'] ?? '',
                'ip_address' => $ipAddress
            ]);

            $this->json([
                'success' => true,
                'message' => '保存成功',
                'data' => [
                    'score_level' => $scoreLevel,
                    'level_class' => $levelClass,
                    'total_score' => $totalScore
                ]
            ]);
        } catch (\Exception $e) {
            error_log("Error in saveScore: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            
            $this->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // 获取指定年级和科目下的班级列表
    public function getTeacherClasses() {
        try {
            $gradeId = $_GET['grade_id'] ?? '';
            $subjectId = $_GET['subject_id'] ?? '';
            $userId = $_SESSION['user_id'] ?? '';
            $role = $_SESSION['role'] ?? '';
            
            if (empty($gradeId) || empty($subjectId) || empty($userId)) {
                throw new \Exception('参数不完整');
            }

            error_log("getTeacherClasses - User ID: " . $userId);
            error_log("getTeacherClasses - User Role: " . $role);
            error_log("getTeacherClasses - Grade ID: " . $gradeId);
            error_log("getTeacherClasses - Subject ID: " . $subjectId);

            // 管理员和教导处可以看到所有班级
            if (in_array($role, ['admin', 'teaching'])) {
                $sql = "SELECT c.* 
                       FROM classes c
                       WHERE c.grade_id = ?
                       AND c.status = 1
                       AND EXISTS (
                           SELECT 1 FROM subject_grades sg 
                           WHERE sg.grade_id = c.grade_id 
                           AND sg.subject_id = ?
                       )
                       ORDER BY c.class_code";
                $params = [$gradeId, $subjectId];
            } else {
                // 阅卷老师只能看到有权限的班级
                $sql = "SELECT DISTINCT c.* 
                       FROM classes c
                       INNER JOIN user_permissions up ON c.grade_id = up.grade_id
                       WHERE c.grade_id = ?
                       AND up.user_id = ?
                       AND up.subject_id = ?
                       AND up.can_edit = 1
                       AND c.status = 1
                       AND EXISTS (
                           SELECT 1 FROM subject_grades sg 
                           WHERE sg.grade_id = c.grade_id 
                           AND sg.subject_id = up.subject_id
                       )
                       ORDER BY c.class_code";
                $params = [$gradeId, $userId, $subjectId];
            }

            error_log("getTeacherClasses - SQL: " . $sql);
            error_log("getTeacherClasses - Params: " . json_encode($params));

            $stmt = $this->db->query($sql, $params);
            $result = $stmt->fetchAll();

            error_log("getTeacherClasses - Found classes: " . json_encode($result));

            $this->json([
                'success' => true,
                'data' => $result
            ]);
        } catch (\Exception $e) {
            error_log("Error in getTeacherClasses: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            
            $this->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * 检查所有成绩是否已录入
     */
    public function checkAllScores() {
        try {
            if (empty($_GET['class_id']) || empty($_GET['subject_id'])) {
                return ['success' => false, 'error' => '缺少必要参数'];
            }

            $classId = intval($_GET['class_id']);
            $subjectId = intval($_GET['subject_id']);

            // 获取学科信息
            $subject = $this->db->query("SELECT * FROM subjects WHERE id = $subjectId")->fetch();
            if (!$subject) {
                return ['success' => false, 'error' => '未找到学科信息'];
            }

            // 获取班级所有学生
            $students = $this->db->query("
                SELECT s.*, COALESCE(sc.is_absent, 0) as is_absent, 
                       sc.total_score, sc.base_score, sc.extra_score
                FROM students s
                LEFT JOIN scores sc ON s.id = sc.student_id 
                    AND sc.subject_id = $subjectId 
                    AND sc.class_id = $classId
                WHERE s.class_id = $classId
                ORDER BY s.student_number ASC
            ")->fetchAll();

            if (empty($students)) {
                return ['success' => true, 'all_entered' => true];
            }

            $missingStudents = [];
            $allEntered = true;

            foreach ($students as $student) {
                $hasScore = false;
                
                // 检查是否已标记缺考
                if ($student['is_absent']) {
                    $hasScore = true;
                } else {
                    if ($subject['is_split']) {
                        // 拆分成绩的情况
                        $hasScore = ($student['base_score'] !== null && $student['extra_score'] !== null);
                    } else {
                        // 非拆分成绩的情况
                        $hasScore = ($student['total_score'] !== null);
                    }
                }

                if (!$hasScore) {
                    $allEntered = false;
                    $missingStudents[] = $student['student_name'];
                }
            }

            return [
                'success' => true,
                'all_entered' => $allEntered,
                'missing_students' => $missingStudents
            ];

        } catch (\Exception $e) {
            $this->logger->error('检查成绩录入状态失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return ['success' => false, 'error' => '检查成绩录入状态失败：' . $e->getMessage()];
        }
    }

    // 保存缺考状态
    public function saveAbsent() {
        try {
            $studentId = $_POST['student_id'] ?? '';
            $subjectId = $_POST['subject_id'] ?? '';
            $isAbsent = isset($_POST['is_absent']) ? (int)$_POST['is_absent'] : 0;
            $userId = $_SESSION['user_id'] ?? '';

            if (empty($studentId) || empty($subjectId) || empty($userId)) {
                throw new \Exception('参数不完整');
            }

            // 获取学生信息
            $stmt = $this->db->query(
                "SELECT s.*, c.grade_id FROM students s 
                JOIN classes c ON s.class_id = c.id 
                WHERE s.id = ? AND s.status = 1",
                [$studentId]
            );
            $student = $stmt->fetch();
            if (!$student) {
                throw new \Exception('学生不存在或已被删除');
            }

            // 验证权限
            $stmt = $this->db->query(
                "SELECT up.* FROM user_permissions up
                WHERE up.user_id = ? 
                AND up.grade_id = ? 
                AND up.subject_id = ? 
                AND up.can_edit = 1",
                [$userId, $student['grade_id'], $subjectId]
            );
            
            if (!$stmt->fetch()) {
                throw new \Exception('无权限操作此班级或科目');
            }

            $this->db->query("START TRANSACTION");

            try {
                // 检查是否存在记录
                $stmt = $this->db->query(
                    "SELECT id FROM scores 
                    WHERE student_id = ? AND subject_id = ?",
                    [$studentId, $subjectId]
                );
                $existing = $stmt->fetch();

                if ($existing) {
                    // 更新记录
                    $stmt = $this->db->query(
                        "UPDATE scores SET 
                        is_absent = ?,
                        base_score = CASE WHEN ? = 1 THEN NULL ELSE base_score END,
                        extra_score = CASE WHEN ? = 1 THEN NULL ELSE extra_score END,
                        total_score = CASE WHEN ? = 1 THEN NULL ELSE total_score END,
                        updated_at = CURRENT_TIMESTAMP
                        WHERE id = ?",
                        [$isAbsent, $isAbsent, $isAbsent, $isAbsent, $existing['id']]
                    );
                } else {
                    // 插入新记录
                    $stmt = $this->db->query(
                        "INSERT INTO scores 
                        (student_id, subject_id, class_id, grade_id, is_absent) 
                        VALUES (?, ?, ?, ?, ?)",
                        [$studentId, $subjectId, $student['class_id'], $student['grade_id'], $isAbsent]
                    );
                }

                $this->db->query("COMMIT");
                $this->json([
                    'success' => true,
                    'message' => '保存成功'
                ]);
            } catch (\Exception $e) {
                $this->db->query("ROLLBACK");
                throw $e;
            }
        } catch (\Exception $e) {
            error_log("Error in saveAbsent: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            
            $this->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // 检查所有成绩是否已录入
    public function checkAllEntered() {
        try {
            $classId = $_GET['class_id'] ?? '';
            $subjectId = $_GET['subject_id'] ?? '';
            $settingId = $_GET['setting_id'] ?? '';
            
            if (empty($classId) || empty($subjectId) || empty($settingId)) {
                throw new \Exception('参数不完整');
            }

            // 获取班级总人数和已录入成绩的人数
            $sql = "SELECT 
                (SELECT COUNT(*) FROM students WHERE class_id = ? AND status = 1) as total_students,
                (SELECT COUNT(*) FROM scores s 
                 INNER JOIN students st ON s.student_id = st.id 
                 WHERE s.class_id = ? 
                 AND s.subject_id = ? 
                 AND s.setting_id = ?
                 AND st.status = 1 
                 AND (s.base_score IS NOT NULL OR s.is_absent = 1)
                ) as entered_scores";
            
            $stmt = $this->db->query($sql, [$classId, $classId, $subjectId, $settingId]);
            $result = $stmt->fetch();
            
            $totalStudents = (int)$result['total_students'];
            $enteredScores = (int)$result['entered_scores'];
            
            $allEntered = ($totalStudents > 0 && $totalStudents === $enteredScores);

            $this->json([
                'success' => true,
                'data' => [
                    'all_entered' => $allEntered,
                    'total_students' => $totalStudents,
                    'entered_scores' => $enteredScores
                ]
            ]);
        } catch (\Exception $e) {
            error_log("Error in checkAllEntered: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            
            $this->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getClassesByGradeAndSubject() {
        try {
            $userId = $_SESSION['user_id'] ?? '';
            $role = $_SESSION['role'] ?? '';
            $gradeId = $_GET['grade_id'] ?? '';
            $subjectId = $_GET['subject_id'] ?? '';

            if (empty($userId) || empty($gradeId) || empty($subjectId)) {
                return $this->json(['success' => false, 'error' => '参数不完整'], 400);
            }

            // 获取当前启用的项目ID
            $stmt = $this->db->query(
                "SELECT id FROM settings WHERE status = 1 ORDER BY id DESC LIMIT 1"
            );
            $setting = $stmt->fetch();
            $settingId = $setting['id'] ?? null;

            if (!$settingId) {
                return $this->json(['success' => false, 'error' => '未找到启用的项目'], 400);
            }

            // 验证科目是否属于当前项目
            $stmt = $this->db->query(
                "SELECT id FROM subjects WHERE id = ? AND setting_id = ? AND status = 1",
                [$subjectId, $settingId]
            );
            if (!$stmt->fetch()) {
                return $this->json(['success' => false, 'error' => '所选科目不存在或不属于当前项目'], 400);
            }

            // 管理员和教导处可以看到所有班级
            if (in_array($role, ['admin', 'teaching'])) {
                $sql = "SELECT c.* 
                       FROM classes c
                       WHERE c.grade_id = ?
                       AND c.status = 1
                       AND EXISTS (
                           SELECT 1 FROM subject_grades sg 
                           WHERE sg.grade_id = c.grade_id 
                           AND sg.subject_id = ?
                       )
                       ORDER BY c.class_code";
                $params = [$gradeId, $subjectId];
            } else {
                // 阅卷老师只能看到有权限的班级
                $sql = "SELECT DISTINCT c.* 
                       FROM classes c
                       INNER JOIN user_permissions up ON c.grade_id = up.grade_id
                       WHERE c.grade_id = ?
                       AND up.user_id = ?
                       AND up.subject_id = ?
                       AND up.can_edit = 1
                       AND c.status = 1
                       AND EXISTS (
                           SELECT 1 FROM subject_grades sg 
                           WHERE sg.grade_id = c.grade_id 
                           AND sg.subject_id = up.subject_id
                       )
                       ORDER BY c.class_code";
                $params = [$gradeId, $userId, $subjectId];
            }

            error_log("getTeacherClasses - SQL: " . $sql);
            error_log("getTeacherClasses - Params: " . json_encode($params));

            $stmt = $this->db->query($sql, $params);
            $result = $stmt->fetchAll();

            error_log("getTeacherClasses - Found classes: " . json_encode($result));

            $this->json([
                'success' => true,
                'data' => $result
            ]);
        } catch (\Exception $e) {
            error_log("Error in getTeacherClasses: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            
            $this->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * 获取科目的等级设置
     * 返回科目的满分、优秀分数线、良好分数线和及格分数线
     */
    public function subject_levels() {
        try {
            $subjectId = $_GET['subject_id'] ?? '';
            
            if (empty($subjectId)) {
                throw new \Exception('参数不完整');
            }

            $stmt = $this->db->query(
                "SELECT full_score, excellent_score, good_score, pass_score 
                FROM subject_settings 
                WHERE subject_id = ?",
                [$subjectId]
            );
            
            $levels = $stmt->fetch();
            
            if (!$levels) {
                throw new \Exception('未找到科目等级设置');
            }

            $this->json([
                'success' => true,
                'data' => [
                    'levels' => [
                        ['min' => $levels['excellent_score'], 'level' => '优秀'],
                        ['min' => $levels['good_score'], 'level' => '良好'],
                        ['min' => $levels['pass_score'], 'level' => '合格'],
                        ['min' => 0, 'level' => '待合格']
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            $this->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
} 