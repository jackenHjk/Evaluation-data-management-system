<?php
/**
 * 文件名: controllers/GradeAnalyticsController.php
 * 功能描述: 年级成绩分析控制器
 * 
 * 该控制器负责:
 * 1. 生成年级级别的成绩统计分析数据
 * 2. 计算年级整体的分数分布、平均分、及格率等指标
 * 3. 提供学生成绩排名数据
 * 4. 整合学校和班级信息用于报表生成
 * 
 * API调用路由:
 * - grade/analytics: 生成年级统计分析
 * - grade/student_rank: 获取学生成绩排名
 * 
 * 关联文件:
 * - core/Controller.php: 基础控制器
 * - modules/grade_analytics.php: 年级分析页面
 * - api/routes/grade.php: 年级API路由处理
 * - api/index.php: API入口文件
 */

namespace Controllers;

use Core\Controller;
use PDOException;

class GradeAnalyticsController extends Controller {
    protected $routes = [
        'generate' => 'generateAnalytics',
        'student_rank' => 'getStudentRanks'
    ];

    public function __construct() {
        parent::__construct();
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (!isset($_SESSION['user_id'])) {
            $this->json(['error' => '未登录'], 401);
            exit;
        }
    }

    /**
     * 获取当前项目设置
     */
    private function getSettings() {
        $sql = "SELECT * FROM settings WHERE status = 1 LIMIT 1";
        $stmt = $this->db->query($sql);
        return $stmt->fetch();
    }

    /**
     * 生成班级统计数据
     */
    public function generateAnalytics() {
        try {
            // 获取并验证参数
            $gradeId = $_GET['grade_id'] ?? null;
            $classId = $_GET['class_id'] ?? null;
            $subjectId = $_GET['subject_id'] ?? null;
            $settingId = $_GET['setting_id'] ?? null;

            if (!$gradeId || !$classId || !$subjectId || !$settingId) {
                return $this->json([
                    'success' => false,
                    'error' => '参数不完整'
                ], 400);
            }

            // 获取班级信息
            $stmt = $this->db->query(
                "SELECT g.grade_name, c.class_name 
                 FROM classes c 
                 JOIN grades g ON c.grade_id = g.id 
                 WHERE c.id = ?",
                [$classId]
            );
            $classInfo = $stmt->fetch();
            if (!$classInfo) {
                return $this->json([
                    'success' => false,
                    'error' => '班级信息不存在'
                ], 404);
            }

            // 从score_analytics表获取统计数据
            $sql = "SELECT * FROM score_analytics 
                    WHERE setting_id = ? 
                    AND class_id = ? 
                    AND subject_id = ? 
                    AND grade_id = ?";
            
            $stmt = $this->db->query($sql, [$settingId, $classId, $subjectId, $gradeId]);
            $analytics = $stmt->fetch();

            if (!$analytics) {
                return $this->json([
                    'success' => false,
                    'error' => '统计数据不存在'
                ], 404);
            }

            // 获取学校信息
            $settings = $this->getSettings();
            if (!$settings) {
                return $this->json([
                    'success' => false,
                    'error' => '项目设置不存在'
                ], 404);
            }

            // 解码分数分布数据
            $scoreDistribution = json_decode($analytics['score_distribution'], true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->logger->error('分数分布数据解析失败', [
                    'error' => json_last_error_msg(),
                    'data' => $analytics['score_distribution']
                ]);
                $scoreDistribution = array_fill(0, 13, 0); // 如果解析失败，使用默认值
            }


            return $this->json([
                'success' => true,
                'data' => [
                    'total_students' => (int)$analytics['total_students'],
                    'attended_students' => (int)$analytics['attended_students'],
                    'total_score' => (float)$analytics['total_score'],
                    'average_score' => (float)$analytics['average_score'],
                    'max_score' => (float)$analytics['max_score'],
                    'min_score' => (float)$analytics['min_score'],
                    'score_distribution' => $scoreDistribution,
                    'excellent_count' => (int)$analytics['excellent_count'],
                    'good_count' => (int)$analytics['good_count'],
                    'pass_count' => (int)$analytics['pass_count'],
                    'fail_count' => (int)$analytics['fail_count'],
                    'pass_rate' => (float)$analytics['pass_rate'],
                    'excellent_rate' => (float)$analytics['excellent_rate'],
                    'school_info' => [
                        'school_name' => $settings['school_name'],
                        'semester' => $settings['current_semester'],
                        'project_name' => $settings['project_name']
                    ],
                    'class_info' => [
                        'grade_name' => $classInfo['grade_name'],
                        'class_name' => $classInfo['class_name']
                    ]
                ]
            ]);
        } catch (PDOException $e) {
            $this->logger->error('生成统计数据失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->json([
                'success' => false,
                'error' => '生成统计数据失败：' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 获取学生成绩排名
     */
    public function getStudentRanks() {
        try {
            // 获取并验证参数
            $gradeId = $_GET['grade_id'] ?? null;
            $subjectId = $_GET['subject_id'] ?? null;
            $settingId = $_GET['setting_id'] ?? null;
            $getAll = isset($_GET['get_all']) && $_GET['get_all'] === 'true';
            $limit = $getAll ? null : intval($_GET['limit'] ?? 50);
            $offset = $getAll ? null : intval($_GET['offset'] ?? 0);

            if (!$gradeId || !$subjectId || !$settingId) {
                return $this->json([
                    'success' => false,
                    'error' => '参数不完整'
                ], 400);
            }

            // 获取总记录数
            $countSql = "SELECT COUNT(*) as total 
                        FROM scores s
                        JOIN students st ON s.student_id = st.id
                        JOIN classes c ON st.class_id = c.id
                        WHERE s.grade_id = ?
                        AND s.subject_id = ?
                        AND s.setting_id = ?";
            
            $stmt = $this->db->query($countSql, [$gradeId, $subjectId, $settingId]);
            $total = $stmt->fetch()['total'];

            // 获取成绩列表（使用子查询计算排名）
            $sql = "SELECT 
                        st.student_number,
                        st.student_name,
                        c.class_name,
                        s.total_score,
                        s.is_absent,
                        s.score_level,
                        (SELECT COUNT(*) + 1 
                         FROM scores s2 
                         WHERE s2.grade_id = s.grade_id 
                         AND s2.subject_id = s.subject_id 
                         AND s2.setting_id = s.setting_id 
                         AND s2.total_score > s.total_score) as rank
                    FROM scores s
                    JOIN students st ON s.student_id = st.id
                    JOIN classes c ON st.class_id = c.id
                    WHERE s.grade_id = ?
                    AND s.subject_id = ?
                    AND s.setting_id = ?
                    ORDER BY s.total_score DESC, st.student_number";

            // 只有在不是获取全部数据时才添加LIMIT
            if (!$getAll) {
                $sql .= " LIMIT " . $limit . " OFFSET " . $offset;
            }

            $stmt = $this->db->query($sql, [$gradeId, $subjectId, $settingId]);
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
} 