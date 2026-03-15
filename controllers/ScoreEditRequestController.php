<?php
/**
 * 文件名: controllers/ScoreEditRequestController.php
 * 功能描述: 成绩修改申请控制器
 * 
 * 该控制器负责:
 * 1. 提交成绩修改申请
 * 2. 获取成绩修改申请列表
 * 3. 审核成绩修改申请
 * 4. 查看成绩修改申请详情
 * 
 * API调用路由:
 * - score_edit/submit: 提交成绩修改申请
 * - score_edit/list: 获取成绩修改申请列表
 * - score_edit/detail: 获取成绩修改申请详情
 * - score_edit/approve: 审核通过成绩修改申请
 * - score_edit/reject: 驳回成绩修改申请
 * 
 * 关联文件:
 * - core/Controller.php: 基础控制器
 * - controllers/ScoreController.php: 成绩控制器
 * - controllers/ClassAnalyticsController.php: 班级分析控制器
 * - controllers/NotificationController.php: 消息通知控制器
 */

namespace Controllers;

use Core\Controller;

class ScoreEditRequestController extends Controller {
    // 将db和logger改为public，允许在外部访问
    public $db;
    public $logger;
    
    /**
     * 提交成绩修改申请
     */
    public function submitRequest() {
        try {
            // 验证用户权限
            $userId = $_SESSION['user_id'] ?? '';
            $role = $_SESSION['role'] ?? '';
            
            if (empty($userId)) {
                throw new \Exception('未登录');
            }
            
            if ($role !== 'marker') {
                throw new \Exception('只有阅卷老师可以提交成绩修改申请');
            }
            
            // 获取请求参数
            $gradeId = $_POST['grade_id'] ?? '';
            $classId = $_POST['class_id'] ?? '';
            $subjectId = $_POST['subject_id'] ?? '';
            $reason = $_POST['reason'] ?? '';
            $editedScoresJson = $_POST['edited_scores'] ?? '';
            
            if (empty($gradeId) || empty($classId) || empty($subjectId) || empty($reason)) {
                throw new \Exception('参数不完整');
            }
            
            // 解析JSON字符串为数组
            $editedScores = [];
            if (!empty($editedScoresJson)) {
                $editedScores = json_decode($editedScoresJson, true);
                
                // 记录解析结果便于调试
                error_log("解析编辑成绩数据: " . print_r($editedScores, true));
            }
            
            if (empty($editedScores) || !is_array($editedScores)) {
                throw new \Exception('未提供有效的修改成绩数据');
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
            
            // 验证用户是否有权限修改该班级科目的成绩
            if ($role !== 'admin' && $role !== 'teaching') {
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
            
            // 获取用户真实姓名
            $stmt = $this->db->query(
                "SELECT real_name FROM users WHERE id = ?",
                [$userId]
            );
            $user = $stmt->fetch();
            $requesterName = $user['real_name'] ?? '未知用户';
            
            // 开始事务
            $this->db->beginTransaction();
            
            try {
                // 创建成绩修改申请记录
                $stmt = $this->db->query(
                    "INSERT INTO score_edit_requests (
                        setting_id, grade_id, class_id, subject_id, 
                        requester_id, requester_name, reason, status
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')",
                    [$settingId, $gradeId, $classId, $subjectId, $userId, $requesterName, $reason]
                );
                
                $requestId = $this->db->lastInsertId();
                
                // 添加成绩修改详情
                foreach ($editedScores as $score) {
                    $studentId = $score['student_id'] ?? '';
                    $studentName = $score['student_name'] ?? '';
                    
                    if (empty($studentId) || empty($studentName)) {
                        continue;
                    }
                    
                    // 获取原始成绩
                    $stmt = $this->db->query(
                        "SELECT base_score, extra_score, total_score, is_absent 
                        FROM scores 
                        WHERE student_id = ? AND subject_id = ? AND setting_id = ?",
                        [$studentId, $subjectId, $settingId]
                    );
                    $oldScore = $stmt->fetch();
                    
                    // 插入成绩修改详情
                    $this->db->query(
                        "INSERT INTO score_edit_details (
                            request_id, student_id, student_name,
                            old_base_score, old_extra_score, old_total_score, old_is_absent,
                            new_base_score, new_extra_score, new_total_score, new_is_absent
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                        [
                            $requestId, $studentId, $studentName,
                            $oldScore['base_score'] ?? null, $oldScore['extra_score'] ?? null, 
                            $oldScore['total_score'] ?? null, $oldScore['is_absent'] ?? 0,
                            $score['base_score'] ?? null, $score['extra_score'] ?? null, 
                            $score['total_score'] ?? null, $score['is_absent'] ?? 0
                        ]
                    );
                }
                
                // 向管理员和教导处发送通知
                $this->sendNotificationToAdmins($requestId, $requesterName, $gradeId, $classId, $subjectId);
                
                $this->db->commit();
                
                // 提交申请时不记录日志，只在审核通过后记录
                
                return $this->json([
                    'success' => true,
                    'message' => '成绩修改申请已提交，等待审核',
                    'request_id' => $requestId
                ]);
                
            } catch (\Exception $e) {
                $this->db->rollBack();
                throw $e;
            }
            
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * 向管理员和教导处发送通知
     */
    private function sendNotificationToAdmins($requestId, $requesterName, $gradeId, $classId, $subjectId) {
        // 获取年级、班级和科目名称
        $stmt = $this->db->query(
            "SELECT g.grade_name, c.class_name, s.subject_name
            FROM grades g
            JOIN classes c ON g.id = c.grade_id
            JOIN subjects s ON s.id = ?
            WHERE g.id = ? AND c.id = ?",
            [$subjectId, $gradeId, $classId]
        );
        $info = $stmt->fetch();
        
        if (!$info) {
            return;
        }
        
        $title = "新的成绩修改申请";
        $content = "{$requesterName}提交了{$info['grade_name']}{$info['class_name']}{$info['subject_name']}的成绩修改申请，请及时审核。";
        
        // 获取所有管理员和教导处角色的用户
        $stmt = $this->db->query(
            "SELECT id FROM users WHERE role IN ('admin', 'teaching') AND status = 1"
        );
        $admins = $stmt->fetchAll();
        
        foreach ($admins as $admin) {
            $this->db->query(
                "INSERT INTO user_notifications (
                    user_id, title, content, type, related_id
                ) VALUES (?, ?, ?, 'score_edit_request', ?)",
                [$admin['id'], $title, $content, $requestId]
            );
        }
    }
    
    /**
     * 获取成绩修改申请列表
     */
    public function getRequestList() {
        try {
            // 验证用户权限
            $userId = $_SESSION['user_id'] ?? '';
            $role = $_SESSION['role'] ?? '';
            
            if (empty($userId)) {
                throw new \Exception('未登录');
            }
            
            // 获取请求参数
            $status = $_GET['status'] ?? '';
            $page = max(1, intval($_GET['page'] ?? 1));
            $pageSize = max(10, min(100, intval($_GET['page_size'] ?? 20)));
            
            // 构建查询条件
            $conditions = [];
            $params = [];
            
            // 管理员和教导处可以看到所有申请，阅卷老师只能看到自己的申请
            if ($role === 'marker') {
                $conditions[] = "ser.requester_id = ?";
                $params[] = $userId;
            }
            
            // 筛选状态
            if (!empty($status) && in_array($status, ['pending', 'approved', 'rejected'])) {
                $conditions[] = "ser.status = ?";
                $params[] = $status;
            }
            
            // 只显示最近6个月的申请
            $conditions[] = "ser.created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)";
            
            // 构建WHERE子句
            $where = !empty($conditions) ? "WHERE " . implode(" AND ", $conditions) : "";
            
            // 计算总数
            $countSql = "
                SELECT COUNT(*) as total
                FROM score_edit_requests ser
                $where
            ";
            $stmt = $this->db->query($countSql, $params);
            $total = $stmt->fetch()['total'] ?? 0;
            
            // 计算分页
            $offset = ($page - 1) * $pageSize;
            $totalPages = ceil($total / $pageSize);
            
            // 获取申请列表 - 将LIMIT和OFFSET直接嵌入SQL语句
            $sql = "
                SELECT ser.*, 
                       g.grade_name, 
                       c.class_name, 
                       s.subject_name,
                       (SELECT COUNT(*) FROM score_edit_details sed WHERE sed.request_id = ser.id) as edit_count,
                       u.real_name as requester_name
                FROM score_edit_requests ser
                JOIN grades g ON ser.grade_id = g.id
                JOIN classes c ON ser.class_id = c.id
                JOIN subjects s ON ser.subject_id = s.id
                JOIN users u ON ser.requester_id = u.id
                $where
                ORDER BY 
                    CASE WHEN ser.status = 'pending' THEN 0
                         WHEN ser.status = 'approved' THEN 1
                         ELSE 2 END,
                    ser.created_at DESC
                LIMIT $pageSize OFFSET $offset
            ";
            
            $stmt = $this->db->query($sql, $params);
            $requests = $stmt->fetchAll();
            
            return $this->json([
                'success' => true,
                'data' => [
                    'requests' => $requests,
                    'pagination' => [
                        'total' => $total,
                        'page' => $page,
                        'page_size' => $pageSize,
                        'total_pages' => $totalPages
                    ]
                ]
            ]);
            
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * 获取成绩修改申请详情
     */
    public function getRequestDetail() {
        try {
            // 验证用户权限
            $userId = $_SESSION['user_id'] ?? '';
            $role = $_SESSION['role'] ?? '';
            
            if (empty($userId)) {
                throw new \Exception('未登录');
            }
            
            // 获取请求参数
            $requestId = $_GET['request_id'] ?? '';
            
            if (empty($requestId)) {
                throw new \Exception('参数不完整');
            }
            
            // 获取申请信息
            $sql = "
                SELECT ser.*, 
                       g.grade_name, 
                       c.class_name, 
                       s.subject_name,
                       s.is_split,
                       s.split_name_1,
                       s.split_name_2
                FROM score_edit_requests ser
                JOIN grades g ON ser.grade_id = g.id
                JOIN classes c ON ser.class_id = c.id
                JOIN subjects s ON ser.subject_id = s.id
                WHERE ser.id = ?
            ";
            
            $stmt = $this->db->query($sql, [$requestId]);
            $request = $stmt->fetch();
            
            if (!$request) {
                throw new \Exception('未找到申请记录');
            }
            
            // 阅卷老师只能查看自己的申请
            if ($role === 'marker' && $request['requester_id'] != $userId) {
                throw new \Exception('无权限查看此申请');
            }
            
            // 获取修改详情
            $stmt = $this->db->query(
                "SELECT sed.*, s.student_name 
                FROM score_edit_details sed
                JOIN students s ON sed.student_id = s.id
                WHERE sed.request_id = ? 
                ORDER BY sed.id",
                [$requestId]
            );
            $details = $stmt->fetchAll();
            
            // 标记消息为已读
            if ($role === 'admin' || $role === 'teaching') {
                $this->db->query(
                    "UPDATE user_notifications 
                    SET is_read = 1 
                    WHERE user_id = ? AND type = 'score_edit_request' AND related_id = ?",
                    [$userId, $requestId]
                );
            }
            
            return $this->json([
                'success' => true,
                'data' => [
                    'request' => $request,
                    'details' => $details
                ]
            ]);
            
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * 通过成绩修改申请
     */
    public function approveRequest() {
        // 在方法开始时先创建一个事务，确保所有操作在同一事务中
        try {
            // 检查是否已经存在事务
            if (!$this->db->inTransaction()) {
                $this->db->beginTransaction();
            }
            
            // 验证用户权限
            $userId = $_SESSION['user_id'] ?? '';
            $role = $_SESSION['role'] ?? '';
            
            if (empty($userId)) {
                throw new \Exception('未登录');
            }
            
            if ($role !== 'admin' && $role !== 'teaching') {
                throw new \Exception('无权限进行此操作');
            }
            
            // 获取请求参数
            $requestId = $_POST['request_id'] ?? '';
            $reviewComment = $_POST['review_comment'] ?? '';
            
            if (empty($requestId)) {
                throw new \Exception('参数不完整');
            }
            
            // 调试日志
            $this->logger->debug('开始处理审核通过请求', [
                'request_id' => $requestId,
                'reviewer_id' => $userId,
                'reviewer_role' => $role
            ]);
            
            // 获取申请信息
            $stmt = $this->db->query(
                "SELECT * FROM score_edit_requests WHERE id = ?",
                [$requestId]
            );
            $request = $stmt->fetch();
            
            if (!$request) {
                throw new \Exception('申请不存在');
            }
            
            if ($request['status'] !== 'pending') {
                throw new \Exception('该申请已被处理');
            }
            
            // 获取修改详情
            $stmt = $this->db->query(
                "SELECT * FROM score_edit_details WHERE request_id = ?",
                [$requestId]
            );
            $details = $stmt->fetchAll();
            
            // 获取审核人姓名
            $stmt = $this->db->query(
                "SELECT real_name FROM users WHERE id = ?",
                [$userId]
            );
            $user = $stmt->fetch();
            $reviewerName = $user['real_name'] ?? '未知用户';
            
            // 记录操作成功状态
            $operationSuccess = true;
            $errors = [];
            
            // 更新每个学生的成绩
            foreach ($details as $detail) {
                try {
                    $this->updateScore(
                        $detail['student_id'],
                        $request['subject_id'],
                        $request['setting_id'],
                        $detail['new_base_score'],
                        $detail['new_extra_score'],
                        $detail['new_total_score'],
                        $detail['new_is_absent']
                    );
                    
                    $this->logger->debug("成功更新学生成绩", [
                        'student_id' => $detail['student_id'],
                        'request_id' => $requestId
                    ]);
                } catch (\Exception $e) {
                    $operationSuccess = false;
                    $errors[] = "更新学生ID {$detail['student_id']} 的成绩失败: " . $e->getMessage();
                    $this->logger->error("更新成绩失败", [
                        'student_id' => $detail['student_id'],
                        'error' => $e->getMessage()
                    ]);
                }
            }
            
            // 更新申请状态
            $result = $this->db->query(
                "UPDATE score_edit_requests 
                SET status = 'approved', 
                    reviewer_id = ?, 
                    reviewer_name = ?,
                    review_comment = ?,
                    reviewed_at = NOW()
                WHERE id = ?",
                [$userId, $reviewerName, $reviewComment, $requestId]
            );
            
            $this->logger->debug("更新申请状态结果", [
                'request_id' => $requestId,
                'rowCount' => $result->rowCount()
            ]);
            
            if ($result->rowCount() <= 0) {
                $operationSuccess = false;
                $errors[] = "更新申请状态失败";
                $this->logger->error("更新申请状态失败", [
                    'request_id' => $requestId
                ]);
            }
            
            // 重新生成分析数据
            $analyticsResult = $this->regenerateAnalytics(
                $request['grade_id'],
                $request['class_id'],
                $request['subject_id'],
                $request['setting_id']
            );
            
            if (!$analyticsResult) {
                $operationSuccess = false;
                $errors[] = "重新生成统计分析数据失败";
            }
            
            // 记录日志
            // 构建详细的日志内容
            $detailsText = [];
            foreach ($details as $detail) {
                $studentName = $this->db->fetch(
                    "SELECT student_name FROM students WHERE id = ?",
                    [$detail['student_id']]
                )['student_name'] ?? '未知学生';
                
                // 旧成绩信息
                $oldScoreText = $detail['old_is_absent'] ? '缺考' : 
                    (isset($detail['old_total_score']) ? $detail['old_total_score'] : '无成绩');
                
                // 新成绩信息
                $newScoreText = $detail['new_is_absent'] ? '缺考' : 
                    (isset($detail['new_total_score']) ? $detail['new_total_score'] : '无成绩');
                
                $detailsText[] = "{$studentName}: {$oldScoreText} → {$newScoreText}";
            }
            
            // 获取年级班级和科目名称
            $gradeClass = $this->db->fetch(
                "SELECT g.grade_name, c.class_name FROM grades g 
                JOIN classes c ON g.id = c.grade_id 
                WHERE c.id = ?",
                [$request['class_id']]
            );
            
            $subject = $this->db->fetch(
                "SELECT subject_name FROM subjects WHERE id = ?",
                [$request['subject_id']]
            );
            
            $gradeName = $gradeClass['grade_name'] ?? '未知年级';
            $className = $gradeClass['class_name'] ?? '未知班级';
            $subjectName = $subject['subject_name'] ?? '未知科目';
            
            $logDetail = "已通过ID为{$requestId}的成绩修改申请\n" .
                        "年级班级: {$gradeName} {$className}\n" .
                        "学科: {$subjectName}\n" .
                        "修改详情: \n" . implode("\n", $detailsText);
            
            $this->logActivity(
                '审核通过成绩修改',
                $logDetail,
                $userId
            );
            
            // 检查操作是否全部成功
            if ($operationSuccess) {
                // 提交事务
                if ($this->db->inTransaction()) {
                    $this->db->commit();
                }
                
                // 发送通知给申请人
                try {
                    $this->sendApprovalNotification($request, $reviewerName, $reviewComment);
                } catch (\Exception $e) {
                    // 通知失败不回滚，但记录日志
                    $this->logger->warning("发送通知失败", [
                        'request_id' => $requestId,
                        'error' => $e->getMessage()
                    ]);
                }
                
                // 记录审核成功日志
                $this->logger->info("成绩修改申请审核通过", [
                    'request_id' => $requestId,
                    'reviewer_id' => $userId,
                    'reviewer_name' => $reviewerName
                ]);
                
                return $this->json([
                    'success' => true,
                    'message' => '成绩修改申请已审核通过'
                ]);
            } else {
                // 有操作失败，回滚事务
                if ($this->db->inTransaction()) {
                    $this->db->rollBack();
                }
                throw new \Exception("审核过程中发生错误: " . implode(", ", $errors));
            }
            
        } catch (\Exception $e) {
            // 确保如果有活跃的事务在发生异常时回滚
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            
            $this->logger->error("审核申请失败", [
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
     * 更新成绩
     */
    private function updateScore($studentId, $subjectId, $settingId, $baseScore, $extraScore, $totalScore, $isAbsent) {
        // 检查是否已存在成绩记录
        $stmt = $this->db->query(
            "SELECT id FROM scores WHERE student_id = ? AND subject_id = ? AND setting_id = ?",
            [$studentId, $subjectId, $settingId]
        );
        $score = $stmt->fetch();
        
        // 获取学生所属的班级ID
        $stmt = $this->db->query(
            "SELECT class_id FROM students WHERE id = ?",
            [$studentId]
        );
        $student = $stmt->fetch();
        
        if (!$student) {
            throw new \Exception('找不到该学生信息');
        }
        
        // 通过班级ID获取年级ID
        $stmt = $this->db->query(
            "SELECT grade_id FROM classes WHERE id = ?",
            [$student['class_id']]
        );
        $class = $stmt->fetch();
        
        if (!$class) {
            throw new \Exception('找不到该班级信息');
        }
        
        $gradeId = $class['grade_id'];
        $classId = $student['class_id'];
        
        if ($score) {
            // 更新现有成绩
            $this->db->query(
                "UPDATE scores SET 
                is_absent = ?,
                base_score = ?,
                extra_score = ?,
                total_score = ?,
                updated_at = CURRENT_TIMESTAMP
                WHERE id = ?",
                [$isAbsent, $baseScore, $extraScore, $totalScore, $score['id']]
            );
        } else {
            // 插入新成绩
            $this->db->query(
                "INSERT INTO scores 
                (student_id, subject_id, grade_id, class_id, setting_id, is_absent, base_score, extra_score, total_score) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
                [
                    $studentId, $subjectId, $gradeId, $classId, 
                    $settingId, $isAbsent, $baseScore, $extraScore, $totalScore
                ]
            );
        }
    }
    
    /**
     * 重新生成统计分析数据
     */
    private function regenerateAnalytics($gradeId, $classId, $subjectId, $settingId) {
        try {
            // 输出详细日志用于调试
            $this->logger->debug("开始重新生成统计分析数据", [
                'grade_id' => $gradeId,
                'class_id' => $classId,
                'subject_id' => $subjectId,
                'setting_id' => $settingId
            ]);
            
            // 创建ClassAnalyticsController实例，并传递db和logger对象
            $analyticsController = new \Controllers\ClassAnalyticsController($this->db, $this->logger);
            
            // 直接调用ClassAnalyticsController的generateAnalyticsWithReturn方法
            // 这个方法不会输出JSON和退出，而是返回结果
            $response = $analyticsController->generateAnalyticsWithReturn(
                $gradeId, 
                $classId, 
                $subjectId, 
                $settingId
            );
            
            // 检查响应是否成功
            if (!$response['success']) {
                $this->logger->error("生成统计分析数据失败", [
                    'response' => $response
                ]);
                return false;
            }
            
            // 确保成功后，删除旧的统计分析数据并重新生成
            $this->db->query(
                "DELETE FROM score_analytics 
                WHERE grade_id = ? AND class_id = ? AND subject_id = ? AND setting_id = ?",
                [$gradeId, $classId, $subjectId, $settingId]
            );
            
            // 获取最新的统计数据
            $stats = $analyticsController->calculateStats($gradeId, $classId, $subjectId);
            $stats['setting_id'] = $settingId;
            
            // 保存到score_analytics表
            $analyticsController->saveAnalytics($stats);
            
            $this->logger->info("成功重新生成统计分析数据", [
                'grade_id' => $gradeId,
                'class_id' => $classId,
                'subject_id' => $subjectId,
                'setting_id' => $settingId
            ]);
            
            return true;
        } catch (\Exception $e) {
            $this->logger->error('重新生成统计分析数据失败: ' . $e->getMessage(), [
                'grade_id' => $gradeId,
                'class_id' => $classId,
                'subject_id' => $subjectId,
                'setting_id' => $settingId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            return false;
        }
    }
    
    /**
     * 向申请人发送审核通过通知
     */
    private function sendApprovalNotification($request, $reviewerName, $comment) {
        $title = "成绩修改申请已通过";
        $content = "您提交的成绩修改申请已被{$reviewerName}审核通过。";
        
        if (!empty($comment)) {
            $content .= " 审核意见：{$comment}";
        }
        
        $this->db->query(
            "INSERT INTO user_notifications (
                user_id, title, content, type, related_id
            ) VALUES (?, ?, ?, 'score_edit_approved', ?)",
            [$request['requester_id'], $title, $content, $request['id']]
        );
    }
    
    /**
     * 驳回成绩修改申请
     */
    public function rejectRequest() {
        try {
            // 验证用户权限
            $userId = $_SESSION['user_id'] ?? '';
            $role = $_SESSION['role'] ?? '';
            
            if (empty($userId)) {
                throw new \Exception('未登录');
            }
            
            if ($role !== 'admin' && $role !== 'teaching') {
                throw new \Exception('无权限进行此操作');
            }
            
            // 获取请求参数
            $requestId = $_POST['request_id'] ?? '';
            $reviewComment = $_POST['review_comment'] ?? '';
            
            if (empty($requestId) || empty($reviewComment)) {
                throw new \Exception('参数不完整，驳回理由不能为空');
            }
            
            // 获取申请信息
            $stmt = $this->db->query(
                "SELECT * FROM score_edit_requests WHERE id = ?",
                [$requestId]
            );
            $request = $stmt->fetch();
            
            if (!$request) {
                throw new \Exception('申请不存在');
            }
            
            if ($request['status'] !== 'pending') {
                throw new \Exception('该申请已被处理');
            }
            
            // 获取审核人姓名
            $stmt = $this->db->query(
                "SELECT real_name FROM users WHERE id = ?",
                [$userId]
            );
            $user = $stmt->fetch();
            $reviewerName = $user['real_name'] ?? '未知用户';
            
            // 开始事务
            $this->db->beginTransaction();
            
            try {
                // 更新申请状态
                $this->db->query(
                    "UPDATE score_edit_requests 
                    SET status = 'rejected', 
                        reviewer_id = ?, 
                        reviewer_name = ?,
                        review_comment = ?,
                        reviewed_at = NOW()
                    WHERE id = ?",
                    [$userId, $reviewerName, $reviewComment, $requestId]
                );
                
                // 发送通知给申请人
                $this->sendRejectionNotification($request, $reviewerName, $reviewComment);
                
                // 记录日志
                $this->logActivity(
                    '驳回成绩修改',
                    "已驳回ID为{$requestId}的成绩修改申请",
                    $userId
                );
                
                $this->db->commit();
                
                return $this->json([
                    'success' => true,
                    'message' => '成绩修改申请已驳回'
                ]);
                
            } catch (\Exception $e) {
                $this->db->rollBack();
                throw $e;
            }
            
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * 向申请人发送驳回通知
     */
    private function sendRejectionNotification($request, $reviewerName, $comment) {
        $title = "成绩修改申请被驳回";
        $content = "您提交的成绩修改申请被{$reviewerName}驳回。";
        
        if (!empty($comment)) {
            $content .= " 驳回原因：{$comment}";
        }
        
        $this->db->query(
            "INSERT INTO user_notifications (
                user_id, title, content, type, related_id
            ) VALUES (?, ?, ?, 'score_edit_rejected', ?)",
            [$request['requester_id'], $title, $content, $request['id']]
        );
    }
    
    /**
     * 获取未读消息数量
     */
    public function getUnreadCount() {
        try {
            // 验证用户权限
            $userId = $_SESSION['user_id'] ?? '';
            
            if (empty($userId)) {
                throw new \Exception('未登录');
            }
            
            // 获取未读消息数量
            $stmt = $this->db->query(
                "SELECT COUNT(*) as count FROM user_notifications WHERE user_id = ? AND is_read = 0",
                [$userId]
            );
            $result = $stmt->fetch();
            
            return $this->json([
                'success' => true,
                'data' => [
                    'unread_count' => (int)($result['count'] ?? 0)
                ]
            ]);
            
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * 标记通知为已读
     */
    public function markAsRead() {
        try {
            // 验证用户权限
            $userId = $_SESSION['user_id'] ?? '';
            $role = $_SESSION['role'] ?? '';
            
            if (empty($userId)) {
                throw new \Exception('未登录');
            }
            
            // 获取请求参数
            $requestId = $_POST['request_id'] ?? '';
            
            if (empty($requestId)) {
                throw new \Exception('参数不完整');
            }
            
            // 检查通知是否存在
            $stmt = $this->db->query(
                "SELECT * FROM user_notifications 
                WHERE user_id = ? AND type = 'score_edit_request' AND related_id = ?",
                [$userId, $requestId]
            );
            
            $notification = $stmt->fetch();
            
            // 如果通知不存在，可能是因为用户没有相应的通知权限，直接返回成功
            if (!$notification) {
                return $this->json([
                    'success' => true,
                    'message' => '通知不存在或已删除'
                ]);
            }
            
            // 更新通知状态
            $this->db->query(
                "UPDATE user_notifications 
                SET is_read = 1 
                WHERE id = ?",
                [$notification['id']]
            );
            
            return $this->json([
                'success' => true,
                'message' => '通知已标记为已读'
            ]);
            
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * 标记所有通知为已读
     */
    public function markAllAsRead() {
        try {
            // 验证用户权限
            $userId = $_SESSION['user_id'] ?? '';
            
            if (empty($userId)) {
                throw new \Exception('未登录');
            }
            
            // 标记所有通知为已读
            $this->db->query(
                "UPDATE user_notifications SET is_read = 1 WHERE user_id = ?",
                [$userId]
            );
            
            return $this->json([
                'success' => true,
                'message' => '已全部标记为已读'
            ]);
            
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * 记录操作日志
     */
    private function logActivity($actionType, $actionDetail, $userId = null) {
        try {
            if ($userId === null) {
                $userId = $_SESSION['user_id'] ?? 0;
            }
            
            // 获取用户信息
            $stmt = $this->db->query(
                "SELECT username, role FROM users WHERE id = ?", 
                [$userId]
            );
            $user = $stmt->fetch();
            
            if (!$user) {
                return false;
            }
            
            // 记录操作日志
            $this->db->query(
                "INSERT INTO operation_logs (user_id, username, role, action_type, action_detail, ip_address) 
                VALUES (?, ?, ?, ?, ?, ?)",
                [
                    $userId, 
                    $user['username'], 
                    $user['role'], 
                    $actionType, 
                    $actionDetail, 
                    $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'
                ]
            );
            
            return true;
        } catch (\Exception $e) {
            $this->logger->error('记录操作日志失败: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * 检查是否存在待审核的申请
     */
    public function checkPendingRequests() {
        try {
            // 获取班级和科目参数
            $classId = $_GET['class_id'] ?? '';
            $subjectId = $_GET['subject_id'] ?? '';
            
            if (empty($classId) || empty($subjectId)) {
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
            
            // 检查是否存在待审核的修改申请
            $stmt = $this->db->query(
                "SELECT COUNT(*) as count FROM score_edit_requests 
                WHERE class_id = ? AND subject_id = ? AND setting_id = ? AND status = 'pending'",
                [$classId, $subjectId, $settingId]
            );
            $result = $stmt->fetch();
            $hasPendingRequests = ($result['count'] > 0);
            
            return $this->json([
                'success' => true,
                'has_pending_request' => $hasPendingRequests,
                'count' => $result['count']
            ]);
            
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * 获取班级科目下待审核的修改详情
     */
    public function getPendingDetails() {
        try {
            // 获取班级和科目参数
            $classId = $_GET['class_id'] ?? '';
            $subjectId = $_GET['subject_id'] ?? '';
            
            if (empty($classId) || empty($subjectId)) {
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
            
            // 获取最新的待审核申请
            $stmt = $this->db->query(
                "SELECT id FROM score_edit_requests 
                WHERE class_id = ? AND subject_id = ? AND setting_id = ? AND status = 'pending'
                ORDER BY created_at DESC LIMIT 1",
                [$classId, $subjectId, $settingId]
            );
            $request = $stmt->fetch();
            
            if (!$request) {
                return $this->json([
                    'success' => true,
                    'data' => []
                ]);
            }
            
            // 获取该申请下的修改详情
            $stmt = $this->db->query(
                "SELECT student_id, student_name, 
                 old_base_score, new_base_score, 
                 old_extra_score, new_extra_score, 
                 old_total_score, new_total_score,
                 old_is_absent, new_is_absent
                 FROM score_edit_details 
                 WHERE request_id = ?",
                [$request['id']]
            );
            $details = $stmt->fetchAll();
            
            return $this->json([
                'success' => true,
                'data' => $details
            ]);
            
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * 检查年级是否有待审核的成绩修改申请
     */
    public function checkPendingByGrade() {
        try {
            // 验证用户权限
            if (!isset($_SESSION['user_id'])) {
                throw new \Exception('未登录');
            }
            
            // 获取请求参数
            $gradeId = isset($_GET['grade_id']) ? intval($_GET['grade_id']) : 0;
            $subjectId = isset($_GET['subject_id']) ? intval($_GET['subject_id']) : 0;
            
            if (!$gradeId || !$subjectId) {
                throw new \Exception('参数不完整');
            }
            
            // 查询是否有待审核的申请
            $stmt = $this->db->query(
                "SELECT COUNT(*) as count
                FROM score_edit_requests
                WHERE grade_id = ? AND subject_id = ? AND status = 'pending'",
                [$gradeId, $subjectId]
            );
            
            $result = $stmt->fetch();
            $count = intval($result['count'] ?? 0);
            
            return $this->json([
                'success' => true,
                'has_pending_request' => $count > 0,
                'count' => $count
            ]);
            
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * 获取班级历史成绩修改记录
     */
    public function getClassHistory() {
        try {
            // 验证用户权限
            if (!isset($_SESSION['user_id'])) {
                throw new \Exception('未登录');
            }
            
            // 获取请求参数
            $classId = isset($_GET['class_id']) ? intval($_GET['class_id']) : 0;
            $subjectId = isset($_GET['subject_id']) ? intval($_GET['subject_id']) : 0;
            
            if (!$classId || !$subjectId) {
                throw new \Exception('参数不完整');
            }
            
            // 查询历史记录
            $stmt = $this->db->query(
                "SELECT r.id, r.requester_name, r.reason, r.status, r.created_at, r.updated_at,
                 u.real_name as reviewer_name, r.review_comment
                 FROM score_edit_requests r
                 LEFT JOIN users u ON r.reviewer_id = u.id
                 WHERE r.class_id = ? AND r.subject_id = ?
                 ORDER BY r.created_at DESC",
                [$classId, $subjectId]
            );
            
            $history = $stmt->fetchAll();
            
            return $this->json([
                'success' => true,
                'data' => $history
            ]);
            
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * 批量通过成绩修改申请
     */
    public function batchApprove() {
        try {
            // 验证用户权限
            $userId = $_SESSION['user_id'] ?? '';
            $role = $_SESSION['role'] ?? '';
            
            if (empty($userId)) {
                throw new \Exception('未登录');
            }
            
            if ($role !== 'admin' && $role !== 'teaching') {
                throw new \Exception('没有权限执行此操作');
            }
            
            // 获取请求参数
            $requestIds = $_POST['request_ids'] ?? '';
            
            if (empty($requestIds)) {
                throw new \Exception('参数不完整');
            }
            
            // 将请求ID字符串转换为数组
            $requestIdArray = explode(',', $requestIds);
            
            // 开始事务
            $this->db->beginTransaction();
            
            try {
                $approvedCount = 0;
                
                foreach ($requestIdArray as $requestId) {
                    // 获取申请详情
                    $stmt = $this->db->query(
                        "SELECT * FROM score_edit_requests WHERE id = ? AND status = 'pending'",
                        [intval($requestId)]
                    );
                    $request = $stmt->fetch();
                    
                    if (!$request) {
                        continue;
                    }
                    
                    // 更新申请状态
                    $this->db->query(
                        "UPDATE score_edit_requests 
                        SET status = 'approved', reviewer_id = ?, updated_at = NOW() 
                        WHERE id = ?",
                        [$userId, $requestId]
                    );
                    
                    // 获取成绩修改详情
                    $stmt = $this->db->query(
                        "SELECT * FROM score_edit_details WHERE request_id = ?",
                        [$requestId]
                    );
                    $details = $stmt->fetchAll();
                    
                    // 更新学生成绩
                    foreach ($details as $detail) {
                        $this->db->query(
                            "UPDATE scores 
                            SET base_score = ?, extra_score = ?, total_score = ?, is_absent = ? 
                            WHERE student_id = ? AND subject_id = ? AND setting_id = ?",
                            [
                                $detail['new_base_score'],
                                $detail['new_extra_score'],
                                $detail['new_total_score'],
                                $detail['new_is_absent'],
                                $detail['student_id'],
                                $request['subject_id'],
                                $request['setting_id']
                            ]
                        );
                    }
                    
                    $approvedCount++;
                }
                
                $this->db->commit();
                
                return $this->json([
                    'success' => true,
                    'approved_count' => $approvedCount,
                    'message' => '批量通过成功'
                ]);
                
            } catch (\Exception $e) {
                $this->db->rollBack();
                throw $e;
            }
            
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * 批量驳回成绩修改申请
     */
    public function batchReject() {
        try {
            // 验证用户权限
            $userId = $_SESSION['user_id'] ?? '';
            $role = $_SESSION['role'] ?? '';
            
            if (empty($userId)) {
                throw new \Exception('未登录');
            }
            
            if ($role !== 'admin' && $role !== 'teaching') {
                throw new \Exception('没有权限执行此操作');
            }
            
            // 获取请求参数
            $requestIds = $_POST['request_ids'] ?? '';
            $reason = $_POST['reason'] ?? '';
            
            if (empty($requestIds) || empty($reason)) {
                throw new \Exception('参数不完整');
            }
            
            // 将请求ID字符串转换为数组
            $requestIdArray = explode(',', $requestIds);
            
            // 开始事务
            $this->db->beginTransaction();
            
            try {
                $rejectedCount = 0;
                
                foreach ($requestIdArray as $requestId) {
                    // 获取申请详情
                    $stmt = $this->db->query(
                        "SELECT * FROM score_edit_requests WHERE id = ? AND status = 'pending'",
                        [intval($requestId)]
                    );
                    $request = $stmt->fetch();
                    
                    if (!$request) {
                        continue;
                    }
                    
                    // 更新申请状态
                    $this->db->query(
                        "UPDATE score_edit_requests 
                        SET status = 'rejected', reviewer_id = ?, review_comment = ?, updated_at = NOW() 
                        WHERE id = ?",
                        [$userId, $reason, $requestId]
                    );
                    
                    $rejectedCount++;
                }
                
                $this->db->commit();
                
                return $this->json([
                    'success' => true,
                    'rejected_count' => $rejectedCount,
                    'message' => '批量驳回成功'
                ]);
                
            } catch (\Exception $e) {
                $this->db->rollBack();
                throw $e;
            }
            
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
} 