<?php
/**
 * 文件名: controllers/ClassAnalyticsController.php
 * 功能描述: 班级成绩分析控制器
 * 
 * 该控制器负责:
 * 1. 生成班级成绩统计分析数据
 * 2. 计算成绩分布、平均分、及格率等统计指标
 * 3. 返回班级分析报表数据
 * 4. 验证用户是否有权限查看特定班级和科目的分析数据
 * 
 * API调用路由:
 * - analytics/getAnalytics: 获取班级成绩分析数据
 * - analytics/generateAnalytics: 生成班级成绩分析数据
 * - analytics/getSubjectsAnalytics: 获取科目分析数据
 * - analytics/getSubjectScores: 获取科目成绩数据
 * 
 * 关联文件:
 * - core/Controller.php: 基础控制器
 * - modules/class_analytics.php: 班级分析页面
 * - api/index.php: API入口文件
 */

namespace Controllers;

use Core\Controller;
use \PDO;

class ClassAnalyticsController extends Controller {
    public $db;
    public $logger;
    
    protected $routes = [
        'getAnalytics' => 'getAnalytics',
        'generateAnalytics' => 'generateAnalytics',
        'getSubjectsAnalytics' => 'getSubjectsAnalytics',
        'getSubjectScores' => 'getSubjectScores',
        'getStudentChineseMathScores' => 'getStudentChineseMathScores'
    ];
    
    /**
     * 构造函数，允许从外部传入db和logger对象
     */
    public function __construct($db = null, $logger = null) {
        if ($db === null || $logger === null) {
            // 如果没有传入参数，使用默认构造函数
            parent::__construct();
        } else {
            // 如果传入了参数，直接使用
            $this->db = $db;
            $this->logger = $logger;
        }
    }
    
    /**
     * 检查用户是否有权限访问指定的年级和科目
     */
    protected function checkPermission($grade_id = null, $subject_id = null) {
        $user = $this->getUser();
        if (!$user) {
            throw new \Exception('未登录');
        }

        // 如果是管理员或教导处，直接返回true
        if ($user['role'] === 'admin' || $user['role'] === 'teaching') {
            return true;
        }

        // 检查具体权限
        $sql = "SELECT 1 FROM user_permissions 
                WHERE user_id = ? 
                AND grade_id = ? 
                AND subject_id = ?
                AND can_edit = 1";
        
        $result = $this->db->fetch($sql, [$user['id'], $grade_id, $subject_id]);
        return !empty($result);
    }

    /**
     * 生成统计分析数据
     */
    public function generateAnalytics() {
        try {
            // 获取参数
            $gradeId = $_POST['grade_id'] ?? null;
            $classId = $_POST['class_id'] ?? null;
            $subjectId = $_POST['subject_id'] ?? null;
            $settingId = $_POST['setting_id'] ?? null;

            if (!$gradeId || !$classId || !$subjectId || !$settingId) {
                error_log('[Analytics] 参数不完整: ' . json_encode([
                    'grade_id' => $gradeId,
                    'class_id' => $classId,
                    'subject_id' => $subjectId,
                    'setting_id' => $settingId
                ]));
                return $this->json([
                    'success' => false,
                    'error' => '参数不完整：需要年级、班级、科目ID和项目ID'
                ]);
            }

            error_log('[Analytics] 开始生成统计分析，参数：' . json_encode([
                'grade_id' => $gradeId,
                'class_id' => $classId,
                'subject_id' => $subjectId,
                'setting_id' => $settingId
            ]));

            // 检查班级是否存在
            error_log('[Analytics] 检查班级存在性');
            $class = $this->db->fetch(
                "SELECT * FROM classes WHERE id = ? AND status = 1",
                [$classId]
            );
            if (empty($class)) {
                return $this->json([
                    'success' => false,
                    'error' => '班级不存在或已禁用'
                ]);
            }

            // 检查班级学生数量
            error_log('[Analytics] 检查班级学生数量');
            $students = $this->db->fetch(
                "SELECT COUNT(*) as count FROM students WHERE class_id = ? AND status = 1",
                [$classId]
            );
            if (empty($students) || $students['count'] == 0) {
                return $this->json([
                    'success' => false,
                    'error' => '该班级暂无学生'
                ]);
            }

            // 生成统计数据
            error_log('[Analytics] 开始生成统计数据');
            $stats = $this->calculateStats($gradeId, $classId, $subjectId);
            
            // 添加 setting_id 到统计数据中
            $stats['setting_id'] = $settingId;
            
            // 保存统计数据
            error_log('[Analytics] 保存统计数据');
            $this->saveAnalytics($stats);

            return $this->json([
                'success' => true,
                'message' => '保存完成，班级统计分析数据已更新！',
                'data' => $stats
            ]);

        } catch (\Exception $e) {
            error_log('[Analytics] 生成统计分析失败：' . $e->getMessage());
            return $this->json([
                'success' => false,
                'error' => '生成统计分析失败：' . $e->getMessage()
            ]);
        }
    }

    /**
     * 计算统计数据
     */
    public function calculateStats($gradeId, $classId, $subjectId) {
        // 获取科目满分和分数线
        $subject = $this->db->fetch(
            "SELECT * FROM subjects WHERE id = ?",
            [$subjectId]
        );
        
        if (empty($subject)) {
            throw new \Exception('科目不存在');
        }

        // 获取所有成绩
        $scores = $this->db->fetchAll(
            "SELECT s.*, sc.base_score, sc.extra_score, sc.total_score, sc.is_absent 
            FROM students s 
            LEFT JOIN scores sc ON s.id = sc.student_id AND sc.subject_id = ?
            WHERE s.class_id = ? AND s.status = 1",
            [$subjectId, $classId]
        );

        // 计算统计数据
        $stats = [
            'grade_id' => $gradeId,
            'class_id' => $classId,
            'subject_id' => $subjectId,
            'total_students' => (int)count($scores),
            'attended_students' => 0,
            'absent_students' => 0,
            'max_score' => 0,
            'min_score' => $subject['full_score'],
            'total_score' => 0,
            'average_score' => 0,
            'excellent_count' => 0,
            'good_count' => 0,
            'pass_count' => 0,
            'fail_count' => 0,
            'pass_rate' => 0,
            'excellent_rate' => 0,
            'score_distribution' => []
        ];

        foreach ($scores as $score) {
            // 计算总分（包括基础分和附加分）
            if (empty($score['total_score']) && !empty($score['base_score'])) {
                $score['total_score'] = floatval($score['base_score']) + floatval($score['extra_score'] ?? 0);
            }

            // 跳过缺考学生
            if ($score['is_absent'] == 1) {
                $stats['absent_students'] = (int)$stats['absent_students'] + 1;
                continue;
            }

            // 跳过没有成绩的学生
            if (empty($score['total_score'])) {
                continue;
            }

            $stats['attended_students'] = (int)$stats['attended_students'] + 1;
            $totalScore = floatval($score['total_score']);
            
            // 更新最高分和最低分
            $stats['max_score'] = $this->formatScore(max($stats['max_score'], $totalScore));
            $stats['min_score'] = $this->formatScore(min($stats['min_score'], $totalScore));
            $stats['total_score'] += $totalScore;

            // 统计等级
            if ($totalScore >= $subject['excellent_score']) {
                $stats['excellent_count']++;
            } elseif ($totalScore >= $subject['good_score']) {
                $stats['good_count']++;
            } elseif ($totalScore >= $subject['pass_score']) {
                $stats['pass_count']++;
            } else {
                $stats['fail_count']++;
            }

            // 更新分数分布
            $this->updateScoreDistribution($stats['score_distribution'], $totalScore);
        }

        // 计算平均分
        if ($stats['attended_students'] > 0) {
            $stats['average_score'] = $this->formatScore(
                $stats['total_score'] / $stats['attended_students']
            );
        }

        // 计算及格率和优秀率
        if ($stats['attended_students'] > 0) {
            $stats['pass_rate'] = $this->formatScore(
                ($stats['pass_count'] + $stats['good_count'] + $stats['excellent_count']) * 100 / $stats['attended_students']
            );
            $stats['excellent_rate'] = $this->formatScore(
                $stats['excellent_count'] * 100 / $stats['attended_students']
            );
        }

        // 进行最终验证，确保分数段统计的一致性
        $this->validateScoreDistribution($stats['score_distribution']);

        error_log('[Analytics] 统计数据计算完成：' . json_encode($stats, JSON_UNESCAPED_UNICODE));
        return $stats;
    }

    /**
     * 格式化分数
     * 如果是整数则不显示小数点，如果有小数则保留两位小数
     */
    private function formatScore($score) {
        if ($score == 0) return 0;
        
        // 如果是整数
        if (floor($score) == $score) {
            return (int)$score;
        }
        
        // 如果有小数，保留两位
        return number_format($score, 2, '.', '');
    }

    /**
     * 使用新的简单直接的方法更新分数分布
     * 
     * @param array &$distribution 分数分布数组
     * @param float $score 要处理的分数
     * @return void
     */
    private function updateScoreDistribution(&$distribution, $score) {
        // 初始化分布数组，如果不存在的话
        $ranges = [
            '0', '40以下', '49.5-40', '54.5-50', '59.5-55', '64.5-60', '69.5-65', 
            '74.5-70', '79.5-75', '84.5-80', '89.5-85', '94.5-90', '99.5-95', '100',
            '99.5-90', '89.5-80', '79.5-70', '69.5-60', '59.5-50'
        ];
        
        foreach ($ranges as $range) {
            if (!isset($distribution[$range])) {
                $distribution[$range] = 0;
            }
        }
        
        // 记录调试日志
        $this->logger->debug("[分数段统计] 处理分数: $score");
        
        // 使用硬编码的条件分支明确分类，避免浮点数比较问题
        if ($score === 0.0 || $score === 0) {
            $distribution['0']++;
            $this->logger->debug("[分数段统计] 分数 $score 添加到段位: 0");
        } 
        else if ($score < 40) {
            $distribution['40以下']++;
            $this->logger->debug("[分数段统计] 分数 $score 添加到段位: 40以下");
        } 
        else if ($score >= 40 && $score < 50) {
            $distribution['49.5-40']++;
            $this->logger->debug("[分数段统计] 分数 $score 添加到段位: 49.5-40");
        } 
        else if ($score >= 50 && $score < 55) {
            $distribution['54.5-50']++;
            $this->logger->debug("[分数段统计] 分数 $score 添加到段位: 54.5-50");
        } 
        else if ($score >= 55 && $score < 60) {
            $distribution['59.5-55']++;
            $this->logger->debug("[分数段统计] 分数 $score 添加到段位: 59.5-55");
        } 
        else if ($score >= 60 && $score < 65) {
            $distribution['64.5-60']++;
            $this->logger->debug("[分数段统计] 分数 $score 添加到段位: 64.5-60");
        } 
        else if ($score >= 65 && $score < 70) {
            $distribution['69.5-65']++;
            $this->logger->debug("[分数段统计] 分数 $score 添加到段位: 69.5-65");
        } 
        else if ($score >= 70 && $score < 75) {
            $distribution['74.5-70']++;
            $this->logger->debug("[分数段统计] 分数 $score 添加到段位: 74.5-70");
        } 
        else if ($score >= 75 && $score < 80) {
            $distribution['79.5-75']++;
            $this->logger->debug("[分数段统计] 分数 $score 添加到段位: 79.5-75");
        } 
        else if ($score >= 80 && $score < 85) {
            $distribution['84.5-80']++;
            $this->logger->debug("[分数段统计] 分数 $score 添加到段位: 84.5-80");
        } 
        else if ($score >= 85 && $score < 90) {
            $distribution['89.5-85']++;
            $this->logger->debug("[分数段统计] 分数 $score 添加到段位: 89.5-85");
        } 
        else if ($score >= 90 && $score < 95) {
            $distribution['94.5-90']++;
            $this->logger->debug("[分数段统计] 分数 $score 添加到段位: 94.5-90");
        } 
        else if ($score >= 95 && $score < 100) {
            $distribution['99.5-95']++;
            $this->logger->debug("[分数段统计] 分数 $score 添加到段位: 99.5-95");
        } 
        else if ($score === 100.0 || $score === 100) {
            $distribution['100']++;
            $this->logger->debug("[分数段统计] 分数 $score 添加到段位: 100");
        }
    }
    
    /**
     * 验证分数分布并计算合并段的分布
     * 
     * @param array &$distribution 分数分布数组
     * @return void
     */
    private function validateScoreDistribution(&$distribution) {
        $this->logger->debug("[分数段统计] 验证和计算合并段分布");
        
        // 计算合并段分布 - 注意：100分不计入99.5-90合并段
        $distribution['99.5-90'] = $distribution['99.5-95'] + $distribution['94.5-90'];
        $distribution['89.5-80'] = $distribution['89.5-85'] + $distribution['84.5-80'];
        $distribution['79.5-70'] = $distribution['79.5-75'] + $distribution['74.5-70'];
        $distribution['69.5-60'] = $distribution['69.5-65'] + $distribution['64.5-60'];
        $distribution['59.5-50'] = $distribution['59.5-55'] + $distribution['54.5-50'];
        
        $this->logger->debug("[分数段统计] 合并段计算结果:");
        $this->logger->debug("[分数段统计] 99.5-90: " . $distribution['99.5-90'] . " (不包含100分)");
        $this->logger->debug("[分数段统计] 89.5-80: " . $distribution['89.5-80']);
        $this->logger->debug("[分数段统计] 79.5-70: " . $distribution['79.5-70']);
        $this->logger->debug("[分数段统计] 69.5-60: " . $distribution['69.5-60']);
        $this->logger->debug("[分数段统计] 59.5-50: " . $distribution['59.5-50']);
    }
    
    /**
     * 按照分数统计的标准分段，将原始分数数组转换为分段统计
     * 这是一个用于测试目的的直接实现方法
     */
    public function convertScoresToDistribution($scores) {
        $distribution = [];
        
        foreach ($scores as $score) {
            $this->updateScoreDistribution($distribution, $score);
        }
        
        $this->validateScoreDistribution($distribution);
        return $distribution;
    }

    /**
     * 保存统计数据
     */
    public function saveAnalytics($stats) {
        try {
            // 格式化数值字段
            $stats['total_score'] = $this->formatScore($stats['total_score']);
            $stats['max_score'] = $this->formatScore($stats['max_score']);
            $stats['min_score'] = $this->formatScore($stats['min_score']);
            $stats['average_score'] = $this->formatScore($stats['average_score']);
            $stats['pass_rate'] = $this->formatScore($stats['pass_rate']);
            $stats['excellent_rate'] = $this->formatScore($stats['excellent_rate']);
            
            // 确保人数字段为整数
            $stats['total_students'] = (int)$stats['total_students'];
            $stats['attended_students'] = (int)$stats['attended_students'];
            $stats['absent_students'] = (int)$stats['absent_students'];
            $stats['excellent_count'] = (int)$stats['excellent_count'];
            $stats['good_count'] = (int)$stats['good_count'];
            $stats['pass_count'] = (int)$stats['pass_count'];
            $stats['fail_count'] = (int)$stats['fail_count'];

            // 保存到数据库
            $sql = "INSERT INTO score_analytics (
                setting_id, grade_id, class_id, subject_id, 
                total_students, attended_students, absent_students,
                max_score, min_score, total_score, average_score,
                excellent_count, good_count, pass_count, fail_count,
                pass_rate, excellent_rate,
                score_distribution, created_at, updated_at
            ) VALUES (
                :setting_id, :grade_id, :class_id, :subject_id,
                :total_students, :attended_students, :absent_students,
                :max_score, :min_score, :total_score, :average_score,
                :excellent_count, :good_count, :pass_count, :fail_count,
                :pass_rate, :excellent_rate,
                :score_distribution, NOW(), NOW()
            ) ON DUPLICATE KEY UPDATE
                total_students = VALUES(total_students),
                attended_students = VALUES(attended_students),
                absent_students = VALUES(absent_students),
                max_score = VALUES(max_score),
                min_score = VALUES(min_score),
                total_score = VALUES(total_score),
                average_score = VALUES(average_score),
                excellent_count = VALUES(excellent_count),
                good_count = VALUES(good_count),
                pass_count = VALUES(pass_count),
                fail_count = VALUES(fail_count),
                pass_rate = VALUES(pass_rate),
                excellent_rate = VALUES(excellent_rate),
                score_distribution = VALUES(score_distribution),
                updated_at = NOW()";

            $params = [
                ':setting_id' => $stats['setting_id'],
                ':grade_id' => $stats['grade_id'],
                ':class_id' => $stats['class_id'],
                ':subject_id' => $stats['subject_id'],
                ':total_students' => $stats['total_students'],
                ':attended_students' => $stats['attended_students'],
                ':absent_students' => $stats['absent_students'],
                ':max_score' => $stats['max_score'],
                ':min_score' => $stats['min_score'],
                ':total_score' => $stats['total_score'],
                ':average_score' => $stats['average_score'],
                ':excellent_count' => $stats['excellent_count'],
                ':good_count' => $stats['good_count'],
                ':pass_count' => $stats['pass_count'],
                ':fail_count' => $stats['fail_count'],
                ':pass_rate' => $stats['pass_rate'],
                ':excellent_rate' => $stats['excellent_rate'],
                ':score_distribution' => json_encode($stats['score_distribution'], JSON_UNESCAPED_UNICODE)
            ];

            error_log('[Analytics] 保存统计数据，参数：' . json_encode($params, JSON_UNESCAPED_UNICODE));
            $this->db->execute($sql, $params);

        } catch (\Exception $e) {
            error_log('[Analytics] 保存统计数据失败：' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * 获取班级成绩分析数据
     */
    public function getAnalytics() {
        try {
            // 获取参数
            $gradeId = $_GET['grade_id'] ?? null;
            $classId = $_GET['class_id'] ?? null;
            $subjectId = $_GET['subject_id'] ?? null;

            if (!$gradeId || !$classId || !$subjectId) {
                return $this->json([
                    'success' => false,
                    'error' => '参数不完整：需要年级、班级和科目ID'
                ]);
            }

            // 首先检查是否有待审核的成绩修改申请
            $hasPendingRequests = $this->checkPendingEditRequests($classId, $subjectId);
            if ($hasPendingRequests) {
                return $this->json([
                    'success' => false,
                    'error' => '该班级该科目有待审核的成绩修改申请，请先完成审核后再查看分析数据',
                    'has_pending_edit_requests' => true
                ]);
            }
            
            // 获取当前项目ID
            $settingId = $this->getCurrentSettingId();
            if (!$settingId) {
                return $this->json([
                    'success' => false,
                    'error' => '未找到当前启用的项目'
                ]);
            }
            
            // 查询分析数据
            $analytics = $this->db->fetch(
                "SELECT * FROM score_analytics 
                WHERE grade_id = ? AND class_id = ? AND subject_id = ? AND setting_id = ?",
                [$gradeId, $classId, $subjectId, $settingId]
            );

            if (empty($analytics)) {
                return $this->json([
                    'success' => false,
                    'error' => '未找到班级分析数据，请先生成分析数据'
                ]);
            }

            // 获取班级和科目名称
            $classInfo = $this->db->fetch(
                "SELECT c.class_name, g.grade_name 
                FROM classes c 
                JOIN grades g ON c.grade_id = g.id 
                WHERE c.id = ?",
                [$classId]
            );
            
            $subjectInfo = $this->db->fetch(
                "SELECT subject_name FROM subjects WHERE id = ?", 
                [$subjectId]
            );
            
            if (empty($classInfo) || empty($subjectInfo)) {
                return $this->json([
                    'success' => false,
                    'error' => '班级或科目不存在'
                ]);
            }
            
            // 解析分数分布JSON
            if (!empty($analytics['score_distribution'])) {
                $analytics['score_distribution'] = json_decode($analytics['score_distribution'], true);
            } else {
                $analytics['score_distribution'] = [];
            }
            
            // 添加班级和科目名称到返回数据
            $analytics['class_name'] = $classInfo['class_name'];
            $analytics['grade_name'] = $classInfo['grade_name'];
            $analytics['subject_name'] = $subjectInfo['subject_name'];
            
            // 获取学校信息
            $schoolInfo = $this->db->fetch("SELECT * FROM settings WHERE id = ?", [$settingId]);
            
            // 返回符合前端期望的数据格式
            return $this->json([
                'success' => true,
                'data' => [
                    'analytics' => $analytics,
                    'school_info' => [
                        'school_name' => $schoolInfo['school_name'] ?? '',
                        'semester' => $schoolInfo['semester'] ?? '',
                        'project_name' => $schoolInfo['project_name'] ?? ''
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => '获取班级分析数据失败：' . $e->getMessage()
            ]);
        }
    }

    /**
     * 检查是否有待审核的成绩修改申请
     * 
     * @param int $classId 班级ID
     * @param int $subjectId 科目ID
     * @return bool 是否有待审核的申请
     */
    private function checkPendingEditRequests($classId, $subjectId) {
        try {
            // 获取当前项目ID
            $settingId = $this->getCurrentSettingId();
            if (!$settingId) {
                return false;
            }
            
            // 查询是否存在待审核的修改申请
            $result = $this->db->fetch(
                "SELECT COUNT(*) as count FROM score_edit_requests 
                WHERE class_id = ? AND subject_id = ? AND setting_id = ? AND status = 'pending'",
                [$classId, $subjectId, $settingId]
            );
            
            return ($result && $result['count'] > 0);
        } catch (\Exception $e) {
            $this->logger->error('检查待审核修改申请失败: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * 获取当前启用的项目ID
     * 
     * @return int|null 项目ID或null
     */
    private function getCurrentSettingId() {
        try {
            $setting = $this->db->fetch(
                "SELECT id FROM settings WHERE status = 1 ORDER BY id DESC LIMIT 1"
            );
            
            return $setting ? $setting['id'] : null;
        } catch (\Exception $e) {
            $this->logger->error('获取当前项目ID失败: ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * 获取当前用户信息
     */
    protected function getUser() {
        if (!isset($_SESSION['user'])) {
            return null;
        }
        return $_SESSION['user'];
    }

    /**
     * 获取年级语数成绩统计数据
     */
    public function getSubjectsAnalytics() {
        try {
            $gradeId = $_GET['grade_id'] ?? null;
            
            if (!$gradeId) {
                return $this->json(['success' => false, 'error' => '缺少年级ID']);
            }

            // 获取语文和数学的科目ID
            $subjectsSql = "SELECT id, subject_name FROM subjects 
                           WHERE subject_name IN ('语文', '数学') 
                           AND setting_id = (SELECT id FROM settings WHERE status = 1 LIMIT 1)";
            $subjects = $this->db->fetchAll($subjectsSql);
            
            if (empty($subjects)) {
                return $this->json(['success' => false, 'error' => '未找到语文或数学科目']);
            }

            // 获取年级下所有班级的统计数据
            $sql = "SELECT 
                    c.class_name,
                    s.subject_name,
                    sa.*
                   FROM score_analytics sa
                   JOIN classes c ON sa.class_id = c.id
                   JOIN subjects s ON sa.subject_id = s.id
                   WHERE sa.grade_id = ?
                   AND s.subject_name IN ('语文', '数学')
                   AND sa.setting_id = (SELECT id FROM settings WHERE status = 1 LIMIT 1)
                   ORDER BY c.class_name, s.subject_name";

            $analytics = $this->db->fetchAll($sql, [$gradeId]);


            return $this->json([
                'success' => true,
                'data' => $analytics
            ]);

        } catch (\Exception $e) {
            $this->logger->error('获取语数统计数据失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->json([
                'success' => false,
                'error' => '获取统计数据失败'
            ]);
        }
    }

    /**
     * 获取学生语数成绩列表
     */
    public function getSubjectScores() {
        try {
            $gradeId = $_GET['grade_id'] ?? null;
            $classId = $_GET['class_id'] ?? '';
            $settingId = $_GET['setting_id'] ?? null;

            if (!$gradeId || !$settingId) {
                return $this->json(['success' => false, 'error' => '缺少必要参数']);
            }

            // 获取语文和数学科目信息
            $subjectsSql = "SELECT id, subject_name, subject_code, 
                           full_score, excellent_score, good_score, pass_score 
                           FROM subjects 
                           WHERE subject_name IN ('语文', '数学') 
                           AND setting_id = ? 
                           AND status = 1";
            $subjects = $this->db->fetchAll($subjectsSql, [$settingId]);
            
            if (empty($subjects)) {
                return $this->json(['success' => false, 'error' => '未找到语文或数学科目信息']);
            }

            // 获取语文和数学的科目ID
            $chineseSubject = null;
            $mathSubject = null;
            foreach ($subjects as $subject) {
                if ($subject['subject_name'] === '语文') {
                    $chineseSubject = $subject;
                } else if ($subject['subject_name'] === '数学') {
                    $mathSubject = $subject;
                }
            }

            // 构建基础SQL
            $sql = "SELECT 
                s.id as student_id,
                s.student_number,
                s.student_name,
                c.class_name,
                chinese.total_score as chinese_score,
                chinese.is_absent as chinese_absent,
                math.total_score as math_score,
                math.is_absent as math_absent
            FROM students s
            JOIN classes c ON s.class_id = c.id
            LEFT JOIN scores chinese ON s.id = chinese.student_id 
                AND chinese.subject_id = ?
            LEFT JOIN scores math ON s.id = math.student_id 
                AND math.subject_id = ?
            WHERE s.status = 1
            AND c.grade_id = ?";

            $params = [$chineseSubject['id'], $mathSubject['id'], $gradeId];

            // 如果指定了班级，添加班级过滤
            if (!empty($classId)) {
                $sql .= " AND c.id = ?";
                $params[] = $classId;
            }

            // 添加排序
            $sql .= " ORDER BY s.student_number ASC";

            $stmt = $this->db->query($sql, $params);
            $scores = $stmt->fetchAll();

            // 添加科目配置信息到返回数据中
            $subjectsConfig = [
                'chinese' => $chineseSubject,
                'math' => $mathSubject
            ];

            return $this->json([
                'success' => true,
                'data' => [
                    'scores' => $scores,
                    'subjects' => $subjectsConfig
                ]
            ]);

        } catch (\Exception $e) {
            error_log("Error in getSubjectScores: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            return $this->json(['success' => false, 'error' => '获取成绩数据失败']);
        }
    }
    
    /**
     * 获取学生语文数学成绩列表（用于语数看板）- 新方法，专门用于获取特定年级关联的语文和数学科目
     */
    public function getStudentChineseMathScores() {
        try {
            $gradeId = $_GET['grade_id'] ?? null;
            $classId = $_GET['class_id'] ?? '';
            $sortBy = $_GET['sort'] ?? 'number'; // 默认按学号排序
            
            if (!$gradeId) {
                return $this->json(['success' => false, 'error' => '缺少年级ID']);
            }
            
            // 获取当前项目ID
            $settingId = $this->db->fetch("SELECT id FROM settings WHERE status = 1 LIMIT 1");
            if (empty($settingId)) {
                return $this->json(['success' => false, 'error' => '未找到有效的项目设置']);
            }
            $settingId = $settingId['id'];
            
            // 获取与年级关联的语文和数学科目信息
            $subjectsSql = "SELECT s.id, s.subject_name, s.subject_code, 
                           s.full_score, s.excellent_score, s.good_score, s.pass_score 
                           FROM subjects s
                           JOIN subject_grades sg ON s.id = sg.subject_id
                           WHERE sg.grade_id = ?
                           AND s.subject_name IN ('语文', '数学') 
                           AND s.setting_id = ? 
                           AND s.status = 1";
            $subjects = $this->db->fetchAll($subjectsSql, [$gradeId, $settingId]);
            
            if (empty($subjects)) {
                return $this->json(['success' => false, 'error' => '未找到与该年级关联的语文或数学科目信息']);
            }

            // 获取语文和数学的科目ID
            $chineseSubject = null;
            $mathSubject = null;
            foreach ($subjects as $subject) {
                if ($subject['subject_name'] === '语文') {
                    $chineseSubject = $subject;
                } else if ($subject['subject_name'] === '数学') {
                    $mathSubject = $subject;
                }
            }
            
            if (!$chineseSubject) {
                return $this->json(['success' => false, 'error' => '未找到与该年级关联的语文科目']);
            }
            
            if (!$mathSubject) {
                return $this->json(['success' => false, 'error' => '未找到与该年级关联的数学科目']);
            }

            // 构建查询SQL
            $sql = "SELECT 
                s.id as student_id,
                s.student_number,
                s.student_name,
                c.class_name,
                chinese.total_score as chinese_score,
                chinese.score_level as chinese_level,
                chinese.is_absent as chinese_absent,
                math.total_score as math_score,
                math.score_level as math_level,
                math.is_absent as math_absent
            FROM students s
            JOIN classes c ON s.class_id = c.id
            LEFT JOIN scores chinese ON s.id = chinese.student_id 
                AND chinese.subject_id = ?
                AND chinese.setting_id = ?
            LEFT JOIN scores math ON s.id = math.student_id 
                AND math.subject_id = ?
                AND math.setting_id = ?
            WHERE s.status = 1
            AND c.grade_id = ?";

            $params = [$chineseSubject['id'], $settingId, $mathSubject['id'], $settingId, $gradeId];

            // 如果指定了班级，添加班级过滤
            if (!empty($classId)) {
                $sql .= " AND c.id = ?";
                $params[] = $classId;
            }

            // 添加排序
            if ($sortBy === 'total_score') {
                // 按总分排序（语文+数学）
                $sql .= " ORDER BY (IFNULL(chinese.total_score, 0) + IFNULL(math.total_score, 0)) DESC, s.student_number ASC";
            } else {
                // 默认按学号排序
                $sql .= " ORDER BY s.student_number ASC";
            }

            $stmt = $this->db->query($sql, $params);
            $scores = $stmt->fetchAll();

            // 添加科目配置信息到返回数据中
            $subjectsConfig = [
                'chinese' => $chineseSubject,
                'math' => $mathSubject
            ];

            return $this->json([
                'success' => true,
                'data' => [
                    'scores' => $scores,
                    'subjects' => $subjectsConfig
                ]
            ]);

        } catch (\Exception $e) {
            $this->logger->error('获取学生语文数学成绩列表失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->json([
                'success' => false, 
                'error' => '获取成绩数据失败'
            ]);
        }
    }

    /**
     * 生成统计分析数据并返回结果，不输出JSON（用于被其他控制器调用）
     * 
     * @param int $gradeId 年级ID
     * @param int $classId 班级ID
     * @param int $subjectId 科目ID
     * @param int $settingId 设置ID
     * @return array 操作结果数组，包含success字段和其他信息
     */
    public function generateAnalyticsWithReturn($gradeId, $classId, $subjectId, $settingId) {
        try {
            if (!$gradeId || !$classId || !$subjectId || !$settingId) {
                $this->logger->error('[Analytics] 参数不完整', [
                    'grade_id' => $gradeId,
                    'class_id' => $classId,
                    'subject_id' => $subjectId,
                    'setting_id' => $settingId
                ]);
                return [
                    'success' => false,
                    'error' => '参数不完整：需要年级、班级、科目ID和项目ID'
                ];
            }

            $this->logger->debug('[Analytics] 开始生成统计分析', [
                'grade_id' => $gradeId,
                'class_id' => $classId,
                'subject_id' => $subjectId,
                'setting_id' => $settingId
            ]);

            // 检查班级是否存在
            $class = $this->db->fetch(
                "SELECT * FROM classes WHERE id = ? AND status = 1",
                [$classId]
            );
            if (empty($class)) {
                return [
                    'success' => false,
                    'error' => '班级不存在或已禁用'
                ];
            }

            // 检查班级学生数量
            $students = $this->db->fetch(
                "SELECT COUNT(*) as count FROM students WHERE class_id = ? AND status = 1",
                [$classId]
            );
            if (empty($students) || $students['count'] == 0) {
                return [
                    'success' => false,
                    'error' => '该班级暂无学生'
                ];
            }

            // 生成统计数据
            $stats = $this->calculateStats($gradeId, $classId, $subjectId);
            
            // 添加 setting_id 到统计数据中
            $stats['setting_id'] = $settingId;
            
            // 保存统计数据
            $this->saveAnalytics($stats);

            return [
                'success' => true,
                'message' => '保存完成，班级统计分析数据已更新！',
                'data' => $stats
            ];

        } catch (\Exception $e) {
            $this->logger->error('[Analytics] 生成统计分析失败：' . $e->getMessage(), [
                'grade_id' => $gradeId,
                'class_id' => $classId,
                'subject_id' => $subjectId,
                'setting_id' => $settingId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            return [
                'success' => false,
                'error' => '生成统计分析失败：' . $e->getMessage()
            ];
        }
    }
} 