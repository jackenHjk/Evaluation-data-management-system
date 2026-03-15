<?php
/**
 * 文件名: api/routes/grade.php
 * 功能描述: 年级数据处理路由
 * 
 * 该文件负责:
 * 1. 处理与年级相关的API请求
 * 2. 获取年级统计分析数据
 * 3. 获取学生成绩排名数据
 * 
 * API调用方式:
 * - 端点: api/index.php?route=grade&action=操作名称
 * - 方法: GET
 * - 操作类型:
 *   - analytics: 获取年级统计数据
 *     - 参数: grade_id, subject_id
 *   - student_rank: 获取学生成绩排名
 *     - 参数: grade_id, subject_id, limit(可选), offset(可选)
 * 
 * 关联文件:
 * - api/index.php: API入口文件，路由请求到此文件
 * - controllers/GradeController.php: 主系统中的年级控制器
 * - controllers/GradeAnalyticsController.php: 年级分析控制器
 */

// 权限检查
checkPermission();

// 路由处理
$action = $_GET['action'] ?? '';

switch ($action) {
    case 'analytics':
        // 获取年级统计数据
        $grade_id = $_GET['grade_id'] ?? null;
        $subject_id = $_GET['subject_id'] ?? null;

        if (!$grade_id || !$subject_id) {
            sendError('参数不完整', 400);
        }

        try {
            $sql = "SELECT * FROM score_analytics 
                    WHERE grade_id = ? 
                    AND subject_id = ? 
                    AND setting_id = (SELECT id FROM settings WHERE status = 1 LIMIT 1)";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([$grade_id, $subject_id]);
            $analytics = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$analytics) {
                sendError('统计数据不存在', 404);
            }

            sendSuccess($analytics);
        } catch (Exception $e) {
            logError('获取年级统计数据失败: ' . $e->getMessage());
            sendError('获取统计数据失败');
        }
        break;

    case 'student_rank':
        // 获取学生成绩排名
        $grade_id = $_GET['grade_id'] ?? null;
        $subject_id = $_GET['subject_id'] ?? null;
        $limit = intval($_GET['limit'] ?? 50);
        $offset = intval($_GET['offset'] ?? 0);

        if (!$grade_id || !$subject_id) {
            sendError('参数不完整', 400);
        }

        try {
            // 获取总记录数
            $countSql = "SELECT COUNT(*) as total 
                        FROM scores s
                        JOIN students st ON s.student_id = st.id
                        JOIN classes c ON st.class_id = c.id
                        WHERE c.grade_id = ? AND s.subject_id = ?
                        AND s.setting_id = (SELECT id FROM settings WHERE status = 1 LIMIT 1)";
            
            $stmt = $pdo->prepare($countSql);
            $stmt->execute([$grade_id, $subject_id]);
            $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

            // 获取成绩列表
            $sql = "SELECT 
                    st.student_number,
                    st.student_name,
                    c.class_name,
                    s.total_score,
                    s.is_absent
                FROM scores s
                JOIN students st ON s.student_id = st.id
                JOIN classes c ON st.class_id = c.id
                WHERE c.grade_id = ? AND s.subject_id = ?
                AND s.setting_id = (SELECT id FROM settings WHERE status = 1 LIMIT 1)
                ORDER BY s.total_score DESC, st.student_number
                LIMIT ? OFFSET ?";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([$grade_id, $subject_id, $limit, $offset]);
            $ranks = $stmt->fetchAll(PDO::FETCH_ASSOC);

            sendSuccess([
                'total' => $total,
                'ranks' => $ranks
            ]);
        } catch (Exception $e) {
            logError('获取学生成绩排名失败: ' . $e->getMessage());
            sendError('获取成绩排名失败');
        }
        break;

    default:
        sendError('未知的操作', 400);
} 