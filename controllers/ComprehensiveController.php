<?php
/**
 * 文件名: ComprehensiveController.php
 * 功能描述: 全科统计分析控制器
 * 
 * 该控制器提供以下API:
 * 1. getClassAnalytics - 获取班级成绩统计数据
 * 2. getExcellentGoodSummary - 获取全优生/优良生统计数据
 * 3. getStudentList - 获取全优生/优良生详细名单
 */

namespace Controllers;

use Core\Controller;
use Exception;

class ComprehensiveController extends Controller {
    private $user_id;
    private $role;
    
    public function __construct() {
        parent::__construct();
        
        // 获取当前用户信息
        $this->user_id = $_SESSION['user_id'] ?? 0;
        $this->role = $_SESSION['role'] ?? '';
        
        // 检查用户是否登录
        if (!$this->user_id) {
            $this->json(['success' => false, 'error' => '请先登录'], 401);
            exit;
        }
        
        // 检查用户是否有权限访问统计分析
        if ($this->role === 'headteacher') {
            $this->json(['success' => false, 'error' => '没有权限访问此功能'], 403);
            exit;
        }
    }
    
    /**
     * 获取班级成绩统计数据
     * 
     * 请求参数:
     * - grade_id: 年级ID
     * - subject_ids: 学科ID数组
     * 
     * 返回数据:
     * - classes: 班级成绩统计数据数组
     */
    public function getClassAnalytics() {
        // 获取POST数据
        $input = json_decode(file_get_contents('php://input'), true);
        
        // 验证参数，优先使用JSON输入，其次使用POST
        $grade_id = isset($input['grade_id']) ? intval($input['grade_id']) : (isset($_POST['grade_id']) ? intval($_POST['grade_id']) : 0);
        $subject_ids = isset($input['subject_ids']) ? $input['subject_ids'] : (isset($_POST['subject_ids']) ? $_POST['subject_ids'] : []);
        
        // 记录调试信息
        error_log('getClassAnalytics 参数: grade_id=' . $grade_id . ', subject_ids=' . json_encode($subject_ids));
        
        // 确保subject_ids是数组
        if (!is_array($subject_ids)) {
            $subject_ids = [$subject_ids];
        }
        
        // 验证参数有效性
        if (!$grade_id || empty($subject_ids)) {
            return $this->json(['success' => false, 'error' => '参数不完整']);
        }
        
        // 检查用户是否有权限查看该年级的统计数据
        if (!$this->checkGradePermission($grade_id)) {
            return $this->json(['success' => false, 'error' => '没有权限查看该年级的统计数据']);
        }
        
        try {
            // 获取当前项目ID
            $setting_id = $this->getCurrentSettingId();
            if (!$setting_id) {
                return $this->json(['success' => false, 'error' => '未找到当前项目']);
            }
            
            // 获取年级下的所有班级
            $classes = $this->getClassesByGradeId($grade_id);
            if (empty($classes)) {
                return $this->json(['success' => true, 'data' => ['classes' => []]]);
            }
            
            // 获取每个班级的每个学科的统计数据
            foreach ($classes as &$class) {
                // 获取班级学生总数
                $class['total_students'] = $this->getClassStudentCount($class['id']);
                $class['subjects'] = [];
                
                foreach ($subject_ids as $subject_id) {
                    // 检查用户是否有权限查看该学科的统计数据
                    if (!$this->checkSubjectPermission($subject_id)) {
                        continue;
                    }
                    
                    // 获取班级学科统计数据
                    $analytics = $this->getClassSubjectAnalytics($class['id'], $subject_id, $setting_id);
                    if ($analytics) {
                        $class['subjects'][] = $analytics;
                    }
                }
            }
            
            return $this->json(['success' => true, 'data' => ['classes' => $classes]]);
        } catch (Exception $e) {
            error_log('获取班级成绩统计数据失败: ' . $e->getMessage());
            return $this->json(['success' => false, 'error' => '获取数据失败: ' . $e->getMessage()]);
        }
    }
    
    /**
     * 获取全优生/优良生统计数据
     * 
     * 请求参数:
     * - grade_id: 年级ID
     * - subject_ids: 学科ID数组
     * 
     * 返回数据:
     * - classes: 班级全优生/优良生统计数据数组
     */
    public function getExcellentGoodSummary() {
        // 获取POST数据
        $input = json_decode(file_get_contents('php://input'), true);
        
        // 验证参数，优先使用JSON输入，其次使用POST
        $grade_id = isset($input['grade_id']) ? intval($input['grade_id']) : (isset($_POST['grade_id']) ? intval($_POST['grade_id']) : 0);
        $subject_ids = isset($input['subject_ids']) ? $input['subject_ids'] : (isset($_POST['subject_ids']) ? $_POST['subject_ids'] : []);
        
        // 记录调试信息
        error_log('getExcellentGoodSummary 参数: grade_id=' . $grade_id . ', subject_ids=' . json_encode($subject_ids));
        
        // 确保subject_ids是数组
        if (!is_array($subject_ids)) {
            $subject_ids = [$subject_ids];
        }
        
        // 验证参数有效性
        if (!$grade_id || empty($subject_ids)) {
            return $this->json(['success' => false, 'error' => '参数不完整']);
        }
        
        // 检查用户是否有权限查看该年级的统计数据
        if (!$this->checkGradePermission($grade_id)) {
            return $this->json(['success' => false, 'error' => '没有权限查看该年级的统计数据']);
        }
        
        try {
            // 获取当前项目ID
            $setting_id = $this->getCurrentSettingId();
            if (!$setting_id) {
                return $this->json(['success' => false, 'error' => '未找到当前项目']);
            }
            
            // 获取年级下的所有班级
            $classes = $this->getClassesByGradeId($grade_id);
            if (empty($classes)) {
                return $this->json(['success' => true, 'data' => ['classes' => []]]);
            }
            
            // 检查用户是否有权限查看所有选择的学科
            $validSubjectIds = [];
            foreach ($subject_ids as $subject_id) {
                if ($this->checkSubjectPermission($subject_id)) {
                    $validSubjectIds[] = $subject_id;
                }
            }
            
            if (empty($validSubjectIds)) {
                return $this->json(['success' => false, 'error' => '没有权限查看所选学科的统计数据']);
            }
            
            // 获取每个班级的全优生/优良生统计数据
            $result = [];
            foreach ($classes as $class) {
                // 获取班级学生总数
                $studentCount = $this->getClassStudentCount($class['id']);
                
                // 获取全优生数量
                $excellentCount = $this->getExcellentStudentCount($class['id'], $validSubjectIds, $setting_id);
                
                // 获取优良生数量
                $goodCount = $this->getGoodStudentCount($class['id'], $validSubjectIds, $setting_id);
                
                // 获取全科及格学生数量
                $passCount = $this->getPassStudentCount($class['id'], $validSubjectIds, $setting_id);
                
                $result[] = [
                    'class_id' => $class['id'],
                    'class_name' => $class['class_name'],
                    'student_count' => $studentCount,
                    'excellent_count' => $excellentCount,
                    'good_count' => $goodCount,
                    'pass_count' => $passCount
                ];
            }
            
            return $this->json(['success' => true, 'data' => ['classes' => $result]]);
        } catch (Exception $e) {
            error_log('获取全优生/优良生统计数据失败: ' . $e->getMessage());
            return $this->json(['success' => false, 'error' => '获取数据失败: ' . $e->getMessage()]);
        }
    }
    
    /**
     * 获取全优生/优良生详细名单
     * 
     * 请求参数:
     * - grade_id: 年级ID
     * - subject_ids: 学科ID数组
     * - type: 类型 (excellent=全优生, good=优良生)
     * 
     * 返回数据:
     * - students: 学生详细名单数组
     */
    public function getStudentList() {
        // 获取POST数据
        $input = json_decode(file_get_contents('php://input'), true);
        
        // 验证参数，优先使用JSON输入，其次使用POST
        $grade_id = isset($input['grade_id']) ? intval($input['grade_id']) : (isset($_POST['grade_id']) ? intval($_POST['grade_id']) : 0);
        $subject_ids = isset($input['subject_ids']) ? $input['subject_ids'] : (isset($_POST['subject_ids']) ? $_POST['subject_ids'] : []);
        $type = isset($input['type']) ? $input['type'] : (isset($_POST['type']) ? $_POST['type'] : 'excellent');
        
        // 记录调试信息
        error_log('getStudentList 参数: grade_id=' . $grade_id . ', subject_ids=' . json_encode($subject_ids) . ', type=' . $type);
        
        // 确保subject_ids是数组
        if (!is_array($subject_ids)) {
            $subject_ids = [$subject_ids];
        }
        
        // 验证参数有效性
        if (!$grade_id || empty($subject_ids) || !in_array($type, ['excellent', 'good'])) {
            return $this->json(['success' => false, 'error' => '参数不完整或无效']);
        }
        
        // 检查用户是否有权限查看该年级的统计数据
        if (!$this->checkGradePermission($grade_id)) {
            return $this->json(['success' => false, 'error' => '没有权限查看该年级的统计数据']);
        }
        
        try {
            // 获取当前项目ID
            $setting_id = $this->getCurrentSettingId();
            if (!$setting_id) {
                return $this->json(['success' => false, 'error' => '未找到当前项目']);
            }
            
            // 检查用户是否有权限查看所有选择的学科
            $validSubjectIds = [];
            $subjects = [];
            foreach ($subject_ids as $subject_id) {
                if ($this->checkSubjectPermission($subject_id)) {
                    $validSubjectIds[] = $subject_id;
                    
                    // 获取学科信息
                    $stmt = $this->db->query("
                        SELECT id, subject_name, subject_code, full_score, excellent_score, good_score, pass_score
                        FROM subjects
                        WHERE id = ?
                    ", [$subject_id]);
                    $subject = $stmt->fetch();
                    if ($subject) {
                        $subjects[] = $subject;
                    }
                }
            }
            
            if (empty($validSubjectIds)) {
                return $this->json(['success' => false, 'error' => '没有权限查看所选学科的统计数据']);
            }
            
            // 根据类型获取学生名单
            $students = [];
            if ($type === 'excellent') {
                $students = $this->getExcellentStudentList($grade_id, $validSubjectIds, $setting_id);
            } else {
                $students = $this->getGoodStudentList($grade_id, $validSubjectIds, $setting_id);
            }
            
            return $this->json(['success' => true, 'data' => [
                'students' => $students,
                'subjects' => $subjects
            ]]);
        } catch (Exception $e) {
            error_log('获取学生名单失败: ' . $e->getMessage());
            return $this->json(['success' => false, 'error' => '获取数据失败: ' . $e->getMessage()]);
        }
    }
    
    /**
     * 获取当前启用的项目ID
     */
    private function getCurrentSettingId() {
        $stmt = $this->db->query("SELECT id FROM settings WHERE status = 1 ORDER BY id DESC LIMIT 1");
        $result = $stmt->fetch();
        return $result ? $result['id'] : 0;
    }
    
    /**
     * 检查用户是否有权限查看该年级的统计数据
     */
    private function checkGradePermission($grade_id) {
        // 管理员和教导处有所有权限
        if (in_array($this->role, ['admin', 'teaching'])) {
            return true;
        }
        
        // 阅卷老师检查权限
        $stmt = $this->db->query("
            SELECT COUNT(*) as count 
            FROM user_permissions 
            WHERE user_id = ? AND grade_id = ?
        ", [$this->user_id, $grade_id]);
        $result = $stmt->fetch();
        
        return $result && $result['count'] > 0;
    }
    
    /**
     * 检查用户是否有权限查看该学科的统计数据
     */
    private function checkSubjectPermission($subject_id) {
        // 管理员和教导处有所有权限
        if (in_array($this->role, ['admin', 'teaching'])) {
            return true;
        }
        
        // 阅卷老师检查权限
        $stmt = $this->db->query("
            SELECT COUNT(*) as count 
            FROM user_permissions 
            WHERE user_id = ? AND subject_id = ?
        ", [$this->user_id, $subject_id]);
        $result = $stmt->fetch();
        
        return $result && $result['count'] > 0;
    }
    
    /**
     * 获取年级下的所有班级
     */
    private function getClassesByGradeId($grade_id) {
        $stmt = $this->db->query("
            SELECT id, class_name, class_code 
            FROM classes 
            WHERE grade_id = ? AND status = 1
            ORDER BY class_code ASC
        ", [$grade_id]);
        return $stmt->fetchAll();
    }
    
    /**
     * 获取班级学科统计数据
     */
    private function getClassSubjectAnalytics($class_id, $subject_id, $setting_id) {
        $stmt = $this->db->query("
            SELECT 
                sa.subject_id,
                sa.total_students,
                sa.attended_students,
                sa.absent_students,
                sa.average_score,
                sa.excellent_rate,
                sa.pass_rate,
                sa.total_score,
                sa.max_score,
                sa.min_score,
                sa.score_distribution,
                sa.excellent_count,
                sa.good_count,
                sa.pass_count,
                sa.fail_count,
                s.subject_name
            FROM score_analytics sa
            JOIN subjects s ON sa.subject_id = s.id
            WHERE sa.class_id = ? AND sa.subject_id = ? AND sa.setting_id = ?
        ", [$class_id, $subject_id, $setting_id]);
        
        $result = $stmt->fetch();
        
        // 如果找到数据，确保分数分布是JSON格式
        if ($result) {
            if (is_string($result['score_distribution'])) {
                $result['score_distribution'] = json_decode($result['score_distribution'], true);
            }
            
            // 确保百分比格式正确
            if (isset($result['excellent_rate'])) {
                $result['excellent_rate'] = floatval($result['excellent_rate']);
            }
            if (isset($result['pass_rate'])) {
                $result['pass_rate'] = floatval($result['pass_rate']);
            }
        }
        
        return $result;
    }
    
    /**
     * 获取班级学生总数
     */
    private function getClassStudentCount($class_id) {
        $stmt = $this->db->query("
            SELECT COUNT(*) as count 
            FROM students 
            WHERE class_id = ? AND status = 1
        ", [$class_id]);
        $result = $stmt->fetch();
        return $result ? $result['count'] : 0;
    }
    
    /**
     * 获取班级全优生数量
     */
    private function getExcellentStudentCount($class_id, $subject_ids, $setting_id) {
        // 获取班级中所有学生
        $stmt = $this->db->query("
            SELECT id FROM students WHERE class_id = ? AND status = 1
        ", [$class_id]);
        $students = $stmt->fetchAll();
        
        if (empty($students)) {
            return 0;
        }
        
        // 计算全优生数量
        $excellentCount = 0;
        foreach ($students as $student) {
            $isExcellent = true;
            
            // 检查该学生在所有选择的学科中是否都是优秀
            foreach ($subject_ids as $subject_id) {
                $stmt = $this->db->query("
                    SELECT score_level 
                    FROM scores 
                    WHERE student_id = ? AND subject_id = ? AND setting_id = ?
                ", [$student['id'], $subject_id, $setting_id]);
                $score = $stmt->fetch();
                
                // 如果没有成绩记录或成绩等级不是优秀，则不是全优生
                if (!$score || $score['score_level'] !== '优秀') {
                    $isExcellent = false;
                    break;
                }
            }
            
            if ($isExcellent) {
                $excellentCount++;
            }
        }
        
        return $excellentCount;
    }
    
    /**
     * 获取班级优良生数量
     */
    private function getGoodStudentCount($class_id, $subject_ids, $setting_id) {
        // 获取班级中所有学生
        $stmt = $this->db->query("
            SELECT id FROM students WHERE class_id = ? AND status = 1
        ", [$class_id]);
        $students = $stmt->fetchAll();
        
        if (empty($students)) {
            return 0;
        }
        
        // 计算优良生数量
        $goodCount = 0;
        foreach ($students as $student) {
            $isGood = true;
            $hasGoodLevel = false;
            
            // 检查该学生在所有选择的学科中是否符合优良生条件
            foreach ($subject_ids as $subject_id) {
                $stmt = $this->db->query("
                    SELECT score_level 
                    FROM scores 
                    WHERE student_id = ? AND subject_id = ? AND setting_id = ?
                ", [$student['id'], $subject_id, $setting_id]);
                $score = $stmt->fetch();
                
                // 如果没有成绩记录，则不是优良生
                if (!$score) {
                    $isGood = false;
                    break;
                }
                
                // 如果有合格或待合格等级，则不是优良生
                if (in_array($score['score_level'], ['合格', '待合格', '及格'])) {
                    $isGood = false;
                    break;
                }
                
                // 检查是否有良好等级
                if ($score['score_level'] === '良好') {
                    $hasGoodLevel = true;
                }
            }
            
            // 必须至少有一个良好等级
            if (!$hasGoodLevel) {
                $isGood = false;
            }
            
            if ($isGood) {
                $goodCount++;
            }
        }
        
        return $goodCount;
    }
    
    /**
     * 获取全科及格学生数量
     */
    private function getPassStudentCount($class_id, $subject_ids, $setting_id) {
        // 获取班级中所有学生
        $stmt = $this->db->query("
            SELECT id FROM students WHERE class_id = ? AND status = 1
        ", [$class_id]);
        $students = $stmt->fetchAll();
        
        if (empty($students)) {
            return 0;
        }
        
        // 计算全科及格学生数量
        $passCount = 0;
        foreach ($students as $student) {
            $isPass = true;
            
            // 检查该学生在所有选择的学科中是否都及格（不包含"待合格"等级）
            foreach ($subject_ids as $subject_id) {
                $stmt = $this->db->query("
                    SELECT score_level 
                    FROM scores 
                    WHERE student_id = ? AND subject_id = ? AND setting_id = ?
                ", [$student['id'], $subject_id, $setting_id]);
                $score = $stmt->fetch();
                
                // 如果没有成绩记录或成绩等级是"待合格"，则不是全科及格
                if (!$score || $score['score_level'] === '待合格') {
                    $isPass = false;
                    break;
                }
            }
            
            if ($isPass) {
                $passCount++;
            }
        }
        
        return $passCount;
    }
    
    /**
     * 获取全优生详细名单
     */
    private function getExcellentStudentList($grade_id, $subject_ids, $setting_id) {
        // 获取年级下的所有班级
        $classes = $this->getClassesByGradeId($grade_id);
        if (empty($classes)) {
            return [];
        }
        
        $result = [];
        foreach ($classes as $class) {
            // 获取班级中所有学生
            $stmt = $this->db->query("
                SELECT id, student_name, student_number
                FROM students 
                WHERE class_id = ? AND status = 1
                ORDER BY student_number ASC
            ", [$class['id']]);
            $students = $stmt->fetchAll();
            
            foreach ($students as $student) {
                $isExcellent = true;
                $scores = [];
                
                // 检查该学生在所有选择的学科中是否都是优秀
                foreach ($subject_ids as $subject_id) {
                    $stmt = $this->db->query("
                        SELECT score_level, total_score, subject_id
                        FROM scores 
                        WHERE student_id = ? AND subject_id = ? AND setting_id = ?
                    ", [$student['id'], $subject_id, $setting_id]);
                    $score = $stmt->fetch();
                    
                    // 如果没有成绩记录或成绩等级不是优秀，则不是全优生
                    if (!$score || $score['score_level'] !== '优秀') {
                        $isExcellent = false;
                        break;
                    }
                    
                    // 记录成绩
                    $scores[] = [
                        'subject_id' => $score['subject_id'],
                        'total_score' => $score['total_score'],
                        'score_level' => $score['score_level']
                    ];
                }
                
                // 如果是全优生，添加到结果中
                if ($isExcellent) {
                    $result[] = [
                        'student_id' => $student['id'],
                        'student_name' => $student['student_name'],
                        'student_number' => $student['student_number'],
                        'class_id' => $class['id'],
                        'class_name' => $class['class_name'],
                        'scores' => $scores
                    ];
                }
            }
        }
        
        return $result;
    }
    
    /**
     * 获取优良生详细名单
     */
    private function getGoodStudentList($grade_id, $subject_ids, $setting_id) {
        // 获取年级下的所有班级
        $classes = $this->getClassesByGradeId($grade_id);
        if (empty($classes)) {
            return [];
        }
        
        $result = [];
        foreach ($classes as $class) {
            // 获取班级中所有学生
            $stmt = $this->db->query("
                SELECT id, student_name, student_number
                FROM students 
                WHERE class_id = ? AND status = 1
                ORDER BY student_number ASC
            ", [$class['id']]);
            $students = $stmt->fetchAll();
            
            foreach ($students as $student) {
                $isGood = true;
                $hasGoodLevel = false;
                $scores = [];
                
                // 检查该学生在所有选择的学科中是否符合优良生条件
                foreach ($subject_ids as $subject_id) {
                    $stmt = $this->db->query("
                        SELECT score_level, total_score, subject_id
                        FROM scores 
                        WHERE student_id = ? AND subject_id = ? AND setting_id = ?
                    ", [$student['id'], $subject_id, $setting_id]);
                    $score = $stmt->fetch();
                    
                    // 如果没有成绩记录，则不是优良生
                    if (!$score) {
                        $isGood = false;
                        break;
                    }
                    
                    // 如果有合格或待合格等级，则不是优良生
                    if (in_array($score['score_level'], ['合格', '待合格', '及格'])) {
                        $isGood = false;
                        break;
                    }
                    
                    // 检查是否有良好等级
                    if ($score['score_level'] === '良好') {
                        $hasGoodLevel = true;
                    }
                    
                    // 记录成绩
                    $scores[] = [
                        'subject_id' => $score['subject_id'],
                        'total_score' => $score['total_score'],
                        'score_level' => $score['score_level']
                    ];
                }
                
                // 必须至少有一个良好等级
                if (!$hasGoodLevel) {
                    $isGood = false;
                }
                
                // 如果是优良生，添加到结果中
                if ($isGood) {
                    $result[] = [
                        'student_id' => $student['id'],
                        'student_name' => $student['student_name'],
                        'student_number' => $student['student_number'],
                        'class_id' => $class['id'],
                        'class_name' => $class['class_name'],
                        'scores' => $scores
                    ];
                }
            }
        }
        
        return $result;
    }
}
