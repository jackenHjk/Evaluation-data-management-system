<?php
/**
 * 文件名: ComprehensiveController.php
 * 功能描述: 全科统计分析控制器
 * 
 * 该控制器提供以下API:
 * 1. getClassAnalytics - 获取班级成绩统计数据
 * 2. getExcellentGoodSummary - 获取全优生/全良生统计数据
 * 3. getStudentList - 获取全优生/全良生详细名单
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
        // 验证参数
        $grade_id = isset($_POST['grade_id']) ? intval($_POST['grade_id']) : 0;
        $subject_ids = isset($_POST['subject_ids']) ? $_POST['subject_ids'] : [];
        
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
     * 获取全优生/全良生统计数据
     * 
     * 请求参数:
     * - grade_id: 年级ID
     * - subject_ids: 学科ID数组
     * 
     * 返回数据:
     * - classes: 班级全优生/全良生统计数据数组
     */
    public function getExcellentGoodSummary() {
        // 验证参数
        $grade_id = isset($_POST['grade_id']) ? intval($_POST['grade_id']) : 0;
        $subject_ids = isset($_POST['subject_ids']) ? $_POST['subject_ids'] : [];
        
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
            
            // 获取每个班级的全优生/全良生统计数据
            $result = [];
            foreach ($classes as $class) {
                // 获取班级学生总数
                $studentCount = $this->getClassStudentCount($class['id']);
                
                // 获取全优生数量
                $excellentCount = $this->getExcellentStudentCount($class['id'], $validSubjectIds, $setting_id);
                
                // 获取全良生数量
                $goodCount = $this->getGoodStudentCount($class['id'], $validSubjectIds, $setting_id);
                
                $result[] = [
                    'class_id' => $class['id'],
                    'class_name' => $class['class_name'],
                    'student_count' => $studentCount,
                    'excellent_count' => $excellentCount,
                    'good_count' => $goodCount
                ];
            }
            
            return $this->json(['success' => true, 'data' => ['classes' => $result]]);
        } catch (Exception $e) {
            error_log('获取全优生/全良生统计数据失败: ' . $e->getMessage());
            return $this->json(['success' => false, 'error' => '获取数据失败: ' . $e->getMessage()]);
        }
    }
    
    /**
     * 获取全优生/全良生详细名单
     * 
     * 请求参数:
     * - grade_id: 年级ID
     * - subject_ids: 学科ID数组
     * - type: 类型 (excellent=全优生, good=全良生)
     * 
     * 返回数据:
     * - students: 学生详细名单数组
     */
    public function getStudentList() {
        // 验证参数
        $grade_id = isset($_POST['grade_id']) ? intval($_POST['grade_id']) : 0;
        $subject_ids = isset($_POST['subject_ids']) ? $_POST['subject_ids'] : [];
        $type = isset($_POST['type']) ? $_POST['type'] : 'excellent';
        
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
            foreach ($subject_ids as $subject_id) {
                if ($this->checkSubjectPermission($subject_id)) {
                    $validSubjectIds[] = $subject_id;
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
            
            return $this->json(['success' => true, 'data' => ['students' => $students]]);
        } catch (Exception $e) {
            error_log('获取学生名单失败: ' . $e->getMessage());
            return $this->json(['success' => false, 'error' => '获取数据失败: ' . $e->getMessage()]);
        }
    }
    
    /**
     * 获取当前项目ID
     */
    private function getCurrentSettingId() {
        $stmt = $this->db->prepare("SELECT id FROM settings WHERE status = 1 ORDER BY id DESC LIMIT 1");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
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
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as count 
            FROM user_permissions 
            WHERE user_id = ? AND grade_id = ?
        ");
        $stmt->execute([$this->user_id, $grade_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
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
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as count 
            FROM user_permissions 
            WHERE user_id = ? AND subject_id = ?
        ");
        $stmt->execute([$this->user_id, $subject_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result && $result['count'] > 0;
    }
    
    /**
     * 获取年级下的所有班级
     */
    private function getClassesByGradeId($grade_id) {
        $stmt = $this->db->prepare("
            SELECT id, class_name, class_code 
            FROM classes 
            WHERE grade_id = ? AND status = 1
            ORDER BY class_code ASC
        ");
        $stmt->execute([$grade_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * 获取班级学科统计数据
     */
    private function getClassSubjectAnalytics($class_id, $subject_id, $setting_id) {
        $stmt = $this->db->prepare("
            SELECT 
                sa.subject_id,
                sa.average_score,
                sa.excellent_rate,
                sa.pass_rate
            FROM score_analytics sa
            WHERE sa.class_id = ? AND sa.subject_id = ? AND sa.setting_id = ?
        ");
        $stmt->execute([$class_id, $subject_id, $setting_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * 获取班级学生总数
     */
    private function getClassStudentCount($class_id) {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as count 
            FROM students 
            WHERE class_id = ? AND status = 1
        ");
        $stmt->execute([$class_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $result['count'] : 0;
    }
    
    /**
     * 获取班级全优生数量
     */
    private function getExcellentStudentCount($class_id, $subject_ids, $setting_id) {
        // 获取班级中所有学生
        $stmt = $this->db->prepare("
            SELECT id FROM students WHERE class_id = ? AND status = 1
        ");
        $stmt->execute([$class_id]);
        $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($students)) {
            return 0;
        }
        
        // 计算全优生数量
        $excellentCount = 0;
        foreach ($students as $student) {
            $isExcellent = true;
            
            // 检查该学生在所有选择的学科中是否都是优秀
            foreach ($subject_ids as $subject_id) {
                $stmt = $this->db->prepare("
                    SELECT score_level 
                    FROM scores 
                    WHERE student_id = ? AND subject_id = ? AND setting_id = ?
                ");
                $stmt->execute([$student['id'], $subject_id, $setting_id]);
                $score = $stmt->fetch(PDO::FETCH_ASSOC);
                
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
     * 获取班级全良生数量
     */
    private function getGoodStudentCount($class_id, $subject_ids, $setting_id) {
        // 获取班级中所有学生
        $stmt = $this->db->prepare("
            SELECT id FROM students WHERE class_id = ? AND status = 1
        ");
        $stmt->execute([$class_id]);
        $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($students)) {
            return 0;
        }
        
        // 计算全良生数量
        $goodCount = 0;
        foreach ($students as $student) {
            $isGood = true;
            
            // 检查该学生在所有选择的学科中是否都是良好
            foreach ($subject_ids as $subject_id) {
                $stmt = $this->db->prepare("
                    SELECT score_level 
                    FROM scores 
                    WHERE student_id = ? AND subject_id = ? AND setting_id = ?
                ");
                $stmt->execute([$student['id'], $subject_id, $setting_id]);
                $score = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // 如果没有成绩记录或成绩等级不是良好，则不是全良生
                if (!$score || $score['score_level'] !== '良好') {
                    $isGood = false;
                    break;
                }
            }
            
            if ($isGood) {
                $goodCount++;
            }
        }
        
        return $goodCount;
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
            $stmt = $this->db->prepare("
                SELECT id, student_name, student_number
                FROM students 
                WHERE class_id = ? AND status = 1
                ORDER BY student_number ASC
            ");
            $stmt->execute([$class['id']]);
            $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($students as $student) {
                $isExcellent = true;
                $scores = [];
                
                // 检查该学生在所有选择的学科中是否都是优秀
                foreach ($subject_ids as $subject_id) {
                    $stmt = $this->db->prepare("
                        SELECT score_level, total_score, subject_id
                        FROM scores 
                        WHERE student_id = ? AND subject_id = ? AND setting_id = ?
                    ");
                    $stmt->execute([$student['id'], $subject_id, $setting_id]);
                    $score = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    // 如果没有成绩记录或成绩等级不是优秀，则不是全优生
                    if (!$score || $score['score_level'] !== '优秀') {
                        $isExcellent = false;
                    }
                    
                    // 记录成绩
                    if ($score) {
                        $scores[] = $score;
                    }
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
     * 获取全良生详细名单
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
            $stmt = $this->db->prepare("
                SELECT id, student_name, student_number
                FROM students 
                WHERE class_id = ? AND status = 1
                ORDER BY student_number ASC
            ");
            $stmt->execute([$class['id']]);
            $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($students as $student) {
                $isGood = true;
                $scores = [];
                
                // 检查该学生在所有选择的学科中是否都是良好
                foreach ($subject_ids as $subject_id) {
                    $stmt = $this->db->prepare("
                        SELECT score_level, total_score, subject_id
                        FROM scores 
                        WHERE student_id = ? AND subject_id = ? AND setting_id = ?
                    ");
                    $stmt->execute([$student['id'], $subject_id, $setting_id]);
                    $score = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    // 如果没有成绩记录或成绩等级不是良好，则不是全良生
                    if (!$score || $score['score_level'] !== '良好') {
                        $isGood = false;
                    }
                    
                    // 记录成绩
                    if ($score) {
                        $scores[] = $score;
                    }
                }
                
                // 如果是全良生，添加到结果中
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
