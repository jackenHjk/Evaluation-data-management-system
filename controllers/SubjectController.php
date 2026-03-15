<?php
/**
 * 文件名: controllers/SubjectController.php
 * 功能描述: 科目管理控制器
 * 
 * 该控制器负责:
 * 1. 科目信息的增删改查
 * 2. 科目代码生成和唯一性检查
 * 3. 科目与年级关联管理
 * 4. 科目分数线设置（满分、优秀线、良好线、及格线）
 * 
 * API调用路由:
 * - subject/list: 获取科目列表
 * - subject/get: 获取科目详情
 * - subject/add: 添加科目
 * - subject/update: 更新科目信息
 * - subject/delete: 删除科目
 * - subject/check_name: 检查科目名称是否重复
 * - grade/subjects: 获取年级关联的科目
 * - subject/delete_by_project: 按项目删除科目
 * 
 * 关联文件:
 * - core/Controller.php: 基础控制器
 * - modules/subject_settings.php: 科目设置页面
 * - controllers/ScoreController.php: 成绩控制器，使用科目数据
 * - controllers/ClassAnalyticsController.php: 班级分析控制器，使用科目分数线
 * - subjects表: 存储科目数据的表
 * - subject_grades表: 存储科目与年级关联的表
 */

namespace Controllers;

use Core\Controller;

class SubjectController extends Controller {
    public function getGradeSubjects() {
        $gradeId = $_GET['grade_id'] ?? '';
        
        if (empty($gradeId)) {
            $this->json(['success' => false, 'error' => '参数不完整'], 400);
            return;
        }

        try {
            // 获取当前可用项目
            $settingStmt = $this->db->query(
                "SELECT id FROM settings WHERE status = 1 LIMIT 1"
            );
            $setting = $settingStmt->fetch();
            
            if (!$setting) {
                $this->json([
                    'success' => false,
                    'error' => '当前无可用项目'
                ], 404);
                return;
            }

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
            $this->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function add() {
        try {
            // 验证必填字段
            $subjectName = $_POST['subject_name'] ?? '';
            $subjectCode = $_POST['subject_code'] ?? '';
            $fullScore = $_POST['full_score'] ?? '';
            $excellentScore = $_POST['excellent_score'] ?? '';
            $goodScore = $_POST['good_score'] ?? '';
            $passScore = $_POST['pass_score'] ?? '';
            $settingId = $_POST['setting_id'] ?? '';
            
            // 获取成绩拆分相关字段
            $isSplit = isset($_POST['is_split']) ? (int)$_POST['is_split'] : 0;
            $splitName1 = $isSplit ? ($_POST['split_name_1'] ?? '') : null;
            $splitName2 = $isSplit ? ($_POST['split_name_2'] ?? '') : null;
            $splitScore1 = $isSplit ? ($_POST['split_score_1'] ?? null) : null;
            $splitScore2 = $isSplit ? ($_POST['split_score_2'] ?? null) : null;
            
            // 处理grade_ids数组
            $gradeIds = [];
            if (isset($_POST['grade_ids'])) {
                if (is_array($_POST['grade_ids'])) {
                    $gradeIds = $_POST['grade_ids'];
                } else {
                    $gradeIds = json_decode($_POST['grade_ids'], true) ?? [];
                }
            }

            if (empty($subjectName) || empty($subjectCode) || empty($fullScore) || empty($excellentScore) || 
                empty($goodScore) || empty($passScore) || empty($gradeIds) || empty($settingId)) {
                return $this->json(['success' => false, 'error' => '请填写完整信息'], 400);
            }

            // 验证成绩拆分数据
            if ($isSplit) {
                if (empty($splitName1) || empty($splitName2) || $splitScore1 === null || $splitScore2 === null) {
                    return $this->json(['success' => false, 'error' => '请填写完整的成绩拆分信息'], 400);
                }
                
                // 验证拆分成绩之和等于总分
                $splitTotal = (float)$splitScore1 + (float)$splitScore2;
                if (abs($splitTotal - (float)$fullScore) > 0.01) {
                    return $this->json(['success' => false, 'error' => '拆分成绩之和必须等于总分'], 400);
                }
            }

            // 验证项目是否存在且处于启用状态
            $stmt = $this->db->query(
                "SELECT id FROM settings WHERE id = ? AND status = 1",
                [$settingId]
            );
            if (!$stmt->fetch()) {
                return $this->json(['success' => false, 'error' => '所选项目不存在或已被禁用'], 400);
            }

            // 验证分数大小关系
            if ($passScore > $goodScore || $goodScore > $excellentScore || $excellentScore > $fullScore) {
                return $this->json(['success' => false, 'error' => '分数线设置不合理，请确保：合格分数 ≤ 良好分数 ≤ 优秀分数 ≤ 满分分数'], 400);
            }

            // 获取年级名称列表，用于日志记录和重复检查
            $gradeNames = [];
            $stmt = $this->db->query(
                "SELECT id, grade_name FROM grades WHERE id IN (" . implode(',', array_fill(0, count($gradeIds), '?')) . ")",
                $gradeIds
            );
            while ($grade = $stmt->fetch()) {
                $gradeNames[$grade['id']] = $grade['grade_name'];
            }

            // 在事务开始前检查在选择的年级和项目中是否已存在同名学科
            foreach ($gradeIds as $gradeId) {
                $stmt = $this->db->query(
                    "SELECT s.id 
                     FROM subjects s 
                     INNER JOIN subject_grades sg ON s.id = sg.subject_id 
                     WHERE s.subject_name = ? AND sg.grade_id = ? AND s.setting_id = ? AND s.status = 1",
                    [$subjectName, $gradeId, $settingId]
                );
                if ($stmt->fetch()) {
                    return $this->json(['success' => false, 'error' => '该年级和项目下已存在同名学科'], 400);
                }
            }

            // 开始事务
            $this->db->beginTransaction();

            // 插入学科基本信息，包括成绩拆分相关字段
            $stmt = $this->db->query(
                "INSERT INTO subjects (
                    subject_name, subject_code, setting_id, 
                    full_score, excellent_score, good_score, pass_score, 
                    is_split, split_name_1, split_name_2, split_score_1, split_score_2,
                    status, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())",
                [
                    $subjectName, $subjectCode, $settingId,
                    $fullScore, $excellentScore, $goodScore, $passScore,
                    $isSplit, $splitName1, $splitName2, $splitScore1, $splitScore2
                ]
            );
            
            $subjectId = $this->db->lastInsertId();
            if (!$subjectId) {
                throw new \Exception('插入学科失败');
            }

            // 同步保存分数线到subject_settings表
            $stmt = $this->db->query(
                "INSERT INTO subject_settings (
                    setting_id, subject_id, full_score, excellent_score, good_score, pass_score,
                    is_split, split_name_1, split_name_2, split_score_1, split_score_2
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                [
                    $settingId, $subjectId, $fullScore, $excellentScore, $goodScore, $passScore,
                    $isSplit, $splitName1, $splitName2, $splitScore1, $splitScore2
                ]
            );

            // 插入学科-年级关联
            $insertGradeSql = "INSERT INTO subject_grades (subject_id, grade_id, setting_id, subject_name, created_at) VALUES ";
            $insertGradeParams = [];
            $placeholders = [];
            
            foreach ($gradeIds as $gradeId) {
                $placeholders[] = "(?, ?, ?, ?, NOW())";
                $insertGradeParams[] = $subjectId;
                $insertGradeParams[] = $gradeId;
                $insertGradeParams[] = $settingId;
                $insertGradeParams[] = $subjectName;  // 添加学科名称
            }
            
            // 合并占位符
            $insertGradeSql .= implode(", ", $placeholders);

            // 确保只有在有参数时才执行查询
            if (!empty($insertGradeParams)) {
                $this->db->query($insertGradeSql, $insertGradeParams);
            }

            // 提交事务
            $this->db->commit();

            // 记录详细的操作日志
            $gradeList = array_map(function($gradeId) use ($gradeNames) {
                return $gradeNames[$gradeId] ?? '未知年级';
            }, $gradeIds);

            $splitInfo = $isSplit ? 
                sprintf("\n拆分成绩：\n- %s：%.2f\n- %s：%.2f", 
                    $splitName1, $splitScore1, 
                    $splitName2, $splitScore2
                ) : "\n不拆分成绩";

            $logDetail = sprintf(
                "添加学科：%s（%s）\n" .
                "关联年级：%s\n" .
                "分数设置：\n" .
                "- 满分：%.1f\n" .
                "- 优秀：%.1f\n" .
                "- 良好：%.1f\n" .
                "- 合格：%.1f%s",
                $subjectName,
                $subjectCode,
                implode('、', $gradeList),
                $fullScore,
                $excellentScore,
                $goodScore,
                $passScore,
                $splitInfo
            );

            $this->logger->info($logDetail, [
                'action_type' => 'add',
                'action_detail' => $logDetail
            ]);

            return $this->json([
                'success' => true,
                'message' => '添加成功',
                'data' => [
                    'id' => (string)$subjectId,
                    'subject_name' => $subjectName,
                    'subject_code' => $subjectCode
                ]
            ]);

        } catch (\Exception $e) {
            // 回滚事务
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            
            // 记录错误日志
            $this->logger->error('添加学科失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'subject_name' => $subjectName ?? null,
                'grade_ids' => $gradeIds ?? null
            ]);

            return $this->json([
                'success' => false,
                'error' => '系统错误，请查看错误日志了解详情'
            ], 500);
        }
    }

    // 生成学科代码
    private function generateSubjectCode() {
        // 获取当前最大的学科代码
        $stmt = $this->db->query(
            "SELECT MAX(CAST(subject_code AS UNSIGNED)) as max_code 
             FROM subjects 
             WHERE subject_code REGEXP '^[0-9]+$'"
        );
        $result = $stmt->fetch();
        $maxCode = $result['max_code'] ?? 0;

        // 生成新的学科代码（当前最大值+1）
        return sprintf('%02d', $maxCode + 1);
    }

    public function get() {
        $id = $_GET['id'] ?? '';
        
        if (empty($id)) {
            return $this->json(['success' => false, 'error' => '参数不完整'], 400);
        }

        try {
            // 获取学科基本信息和分数设置
            $stmt = $this->db->query(
                "SELECT s.*, 
                    (SELECT GROUP_CONCAT(g.grade_name ORDER BY g.grade_code) 
                     FROM grades g 
                     INNER JOIN subject_grades sg ON g.id = sg.grade_id 
                     WHERE sg.subject_id = s.id) as grade_names
                FROM subjects s 
                WHERE s.id = ?",
                [$id]
            );
            $subject = $stmt->fetch();
            
            if (!$subject) {
                return $this->json(['success' => false, 'error' => '学科不存在'], 404);
            }

            // 获取学科关联的年级ID
            $stmt = $this->db->query(
                "SELECT grade_id FROM subject_grades WHERE subject_id = ? ORDER BY grade_id",
                [$id]
            );
            $gradeIds = array_column($stmt->fetchAll(), 'grade_id');
            
            // 将年级ID添加到返回数据中
            $subject['grade_ids'] = $gradeIds;
            $subject['grade_names'] = $subject['grade_names'] ? explode(',', $subject['grade_names']) : [];

            // 确保ID是字符串类型
            $subject['id'] = (string)$subject['id'];
            $subject['grade_ids'] = array_map('strval', $subject['grade_ids']);
            
            // 确保分数值是数字类型
            $subject['full_score'] = floatval($subject['full_score']);
            $subject['excellent_score'] = floatval($subject['excellent_score']);
            $subject['good_score'] = floatval($subject['good_score']);
            $subject['pass_score'] = floatval($subject['pass_score']);

            return $this->json([
                'success' => true,
                'data' => $subject
            ]);
        } catch (\Exception $e) {
            $this->logger->error('获取学科信息失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'subject_id' => $id
            ]);
            return $this->json([
                'success' => false,
                'error' => '获取学科信息失败'
            ], 500);
        }
    }

    public function delete() {
        try {
            if (!isset($_POST['id'])) {
                return $this->json(['success' => false, 'error' => '参数不完整'], 400);
            }

            $id = $_POST['id'];

            // 检查是否存在相关的成绩记录
            $stmt = $this->db->query(
                "SELECT COUNT(*) as count FROM scores WHERE subject_id = ?",
                [$id]
            );
            $result = $stmt->fetch();
            
            if ($result && $result['count'] > 0) {
                return $this->json([
                    'success' => false, 
                    'error' => '该学科已有成绩数据，不允许删除'
                ], 400);
            }

            // 获取学科信息，用于记录日志
            $stmt = $this->db->query(
                "SELECT * FROM subjects WHERE id = ?",
                [$id]
            );
            $subject = $stmt->fetch();
            
            if (!$subject) {
                return $this->json(['success' => false, 'error' => '学科不存在'], 404);
            }

            // 开始事务
            $this->db->beginTransaction();

            // 1. 删除学科与年级的关联
            $this->db->query(
                "DELETE FROM subject_grades WHERE subject_id = ?",
                [$id]
            );

            // 2. 删除学科设置
            $this->db->query(
                "DELETE FROM subject_settings WHERE subject_id = ?",
                [$id]
            );

            // 3. 彻底删除学科记录（不是软删除）
            $this->db->query(
                "DELETE FROM subjects WHERE id = ?",
                [$id]
            );

            // 提交事务
            $this->db->commit();

            // 记录日志
            $this->logger->info("删除学科：{$subject['subject_name']}（ID: {$id}）", [
                'action_type' => 'delete',
                'action_detail' => "彻底删除学科：{$subject['subject_name']}（ID: {$id}）"
            ]);

            return $this->json([
                'success' => true,
                'message' => '删除成功'
            ]);

        } catch (\Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            
            $this->logger->error("删除学科失败: " . $e->getMessage(), [
                'exception' => $e
            ]);
            
            return $this->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function update() {
        try {
            if (!isset($_POST['id'])) {
                return $this->json(['success' => false, 'error' => '参数不完整'], 400);
            }

            $id = $_POST['id'];
            $subjectName = $_POST['subject_name'] ?? '';
            $fullScore = $_POST['full_score'] ?? '';
            $excellentScore = $_POST['excellent_score'] ?? '';
            $goodScore = $_POST['good_score'] ?? '';
            $passScore = $_POST['pass_score'] ?? '';
            $settingId = $_POST['setting_id'] ?? '';
            
            // 获取成绩拆分相关字段
            $isSplit = isset($_POST['is_split']) ? (int)$_POST['is_split'] : 0;
            $splitName1 = $isSplit ? ($_POST['split_name_1'] ?? '') : null;
            $splitName2 = $isSplit ? ($_POST['split_name_2'] ?? '') : null;
            $splitScore1 = $isSplit ? ($_POST['split_score_1'] ?? null) : null;
            $splitScore2 = $isSplit ? ($_POST['split_score_2'] ?? null) : null;
            
            // 处理grade_ids数组
            $gradeIds = [];
            if (isset($_POST['grade_ids'])) {
                if (is_array($_POST['grade_ids'])) {
                    $gradeIds = $_POST['grade_ids'];
                } else {
                    $gradeIds = json_decode($_POST['grade_ids'], true) ?? [];
                }
            }

            if (empty($subjectName) || empty($fullScore) || empty($excellentScore) || 
                empty($goodScore) || empty($passScore) || empty($gradeIds) || empty($settingId)) {
                return $this->json(['success' => false, 'error' => '请填写完整信息'], 400);
            }

            // 验证成绩拆分数据
            if ($isSplit) {
                if (empty($splitName1) || empty($splitName2) || $splitScore1 === null || $splitScore2 === null) {
                    return $this->json(['success' => false, 'error' => '请填写完整的成绩拆分信息'], 400);
                }
                
                // 验证拆分成绩之和等于总分
                $splitTotal = (float)$splitScore1 + (float)$splitScore2;
                if (abs($splitTotal - (float)$fullScore) > 0.01) {
                    return $this->json(['success' => false, 'error' => '拆分成绩之和必须等于总分'], 400);
                }
            }

            // 验证项目是否存在且处于启用状态
            $stmt = $this->db->query(
                "SELECT id FROM settings WHERE id = ? AND status = 1",
                [$settingId]
            );
            if (!$stmt->fetch()) {
                return $this->json(['success' => false, 'error' => '所选项目不存在或已被禁用'], 400);
            }

            // 验证分数大小关系
            if ($passScore > $goodScore || $goodScore > $excellentScore || $excellentScore > $fullScore) {
                return $this->json(['success' => false, 'error' => '分数线设置不合理，请确保：合格分数 ≤ 良好分数 ≤ 优秀分数 ≤ 满分分数'], 400);
            }

            // 开始事务
            $this->db->beginTransaction();

            // 获取学科原有信息，用于日志记录和比较
            $stmt = $this->db->query(
                "SELECT * FROM subjects WHERE id = ?",
                [$id]
            );
            $oldSubject = $stmt->fetch();
            if (!$oldSubject) {
                $this->db->rollBack();
                return $this->json(['success' => false, 'error' => '学科不存在'], 404);
            }

            // 检查是否有成绩数据
            $stmt = $this->db->query(
                "SELECT COUNT(*) as count FROM scores WHERE subject_id = ?",
                [$id]
            );
            $result = $stmt->fetch();
            $hasScores = $result && $result['count'] > 0;

            // 如果有成绩数据，不允许修改成绩拆分状态
            if ($hasScores && $oldSubject['is_split'] != $isSplit) {
                $this->db->rollBack();
                return $this->json([
                    'success' => false, 
                    'error' => '该学科已有成绩数据，不允许修改成绩拆分状态'
                ], 400);
            }

            // 如果有成绩数据且启用了成绩拆分，不允许修改拆分分数值
            if ($hasScores && $oldSubject['is_split'] == 1 && $isSplit == 1) {
                if ($oldSubject['split_score_1'] != $splitScore1 || $oldSubject['split_score_2'] != $splitScore2) {
                    $this->db->rollBack();
                    return $this->json([
                        'success' => false, 
                        'error' => '该学科已有成绩数据，不允许修改拆分分数值'
                    ], 400);
                }
            }

            // 获取年级名称列表，用于日志记录
            $gradeNames = [];
            $stmt = $this->db->query(
                "SELECT id, grade_name FROM grades WHERE id IN (" . implode(',', array_fill(0, count($gradeIds), '?')) . ")",
                $gradeIds
            );
            while ($grade = $stmt->fetch()) {
                $gradeNames[$grade['id']] = $grade['grade_name'];
            }

            // 获取学科已有关联的年级
            $stmt = $this->db->query(
                "SELECT grade_id FROM subject_grades WHERE subject_id = ?",
                [$id]
            );
            $oldGradeIds = [];
            while ($row = $stmt->fetch()) {
                $oldGradeIds[] = $row['grade_id'];
            }

            // 获取有成绩数据的年级
            $gradesWithScores = [];
            if ($hasScores) {
                $stmt = $this->db->query(
                    "SELECT DISTINCT grade_id FROM scores WHERE subject_id = ?",
                    [$id]
                );
                while ($row = $stmt->fetch()) {
                    $gradesWithScores[] = $row['grade_id'];
                }
            }

            // 检查是否移除了已有成绩数据的年级
            foreach ($gradesWithScores as $gradeId) {
                if (!in_array($gradeId, $gradeIds)) {
                    $this->db->rollBack();
                    return $this->json([
                        'success' => false, 
                        'error' => '不能移除已有成绩数据的年级关联'
                    ], 400);
                }
            }

            // 计算要删除的年级ID和要添加的年级ID
            $gradeIdsToAdd = array_diff($gradeIds, $oldGradeIds);
            $gradeIdsToDelete = array_diff($oldGradeIds, $gradeIds);

            // 删除不再关联的年级
            if (!empty($gradeIdsToDelete)) {
                $this->db->query(
                    "DELETE FROM subject_grades 
                     WHERE subject_id = ? AND grade_id IN (" . implode(',', array_fill(0, count($gradeIdsToDelete), '?')) . ")",
                    array_merge([$id], $gradeIdsToDelete)
                );
            }

            // 添加新关联的年级
            if (!empty($gradeIdsToAdd)) {
                $insertGradeSql = "INSERT INTO subject_grades (subject_id, grade_id, setting_id, subject_name, created_at) VALUES ";
                $insertGradeParams = [];
                $placeholders = [];
                
                foreach ($gradeIdsToAdd as $gradeId) {
                    $placeholders[] = "(?, ?, ?, ?, NOW())";
                    $insertGradeParams[] = $id;
                    $insertGradeParams[] = $gradeId;
                    $insertGradeParams[] = $settingId;
                    $insertGradeParams[] = $subjectName;  // 添加学科名称
                }
                
                // 合并占位符
                $insertGradeSql .= implode(", ", $placeholders);

                // 确保只有在有参数时才执行查询
                if (!empty($insertGradeParams)) {
                    $this->db->query($insertGradeSql, $insertGradeParams);
                }
            }

            // 更新学科基本信息，包括成绩拆分相关字段
            $updateSubjectSql = "UPDATE subjects SET 
                subject_name = ?, 
                full_score = ?, 
                excellent_score = ?, 
                good_score = ?, 
                pass_score = ?, 
                is_split = ?,
                split_name_1 = ?,
                split_name_2 = ?,
                split_score_1 = ?,
                split_score_2 = ?,
                updated_at = NOW() 
                WHERE id = ?";
            
            $this->db->query($updateSubjectSql, [
                $subjectName, 
                $fullScore, 
                $excellentScore, 
                $goodScore, 
                $passScore,
                $isSplit,
                $splitName1,
                $splitName2,
                $splitScore1,
                $splitScore2,
                $id
            ]);

            // 同步更新分数线到subject_settings表
            $updateSettingsSQL = "UPDATE subject_settings SET 
                full_score = ?, 
                excellent_score = ?, 
                good_score = ?, 
                pass_score = ?, 
                is_split = ?,
                split_name_1 = ?,
                split_name_2 = ?,
                split_score_1 = ?,
                split_score_2 = ?,
                updated_at = NOW() 
                WHERE subject_id = ?";

            $this->db->query($updateSettingsSQL, [
                $fullScore, 
                $excellentScore, 
                $goodScore, 
                $passScore,
                $isSplit,
                $splitName1,
                $splitName2,
                $splitScore1,
                $splitScore2,
                $id
            ]);

            // 提交事务
            $this->db->commit();

            // 记录详细的操作日志
            $gradeList = array_map(function($gradeId) use ($gradeNames) {
                return $gradeNames[$gradeId] ?? '未知年级';
            }, $gradeIds);

            $oldGradeList = [];
            foreach ($oldGradeIds as $oldGradeId) {
                $oldGradeList[] = $gradeNames[$oldGradeId] ?? '未知年级';
            }

            $splitInfo = $isSplit ? 
                sprintf("\n拆分成绩：\n- %s：%.2f\n- %s：%.2f", 
                    $splitName1, $splitScore1, 
                    $splitName2, $splitScore2
                ) : "\n不拆分成绩";

            $logDetail = sprintf(
                "更新学科：%s（ID: %s）\n" .
                "名称：%s -> %s\n" .
                "分数设置：\n" .
                "- 满分：%.1f -> %.1f\n" .
                "- 优秀：%.1f -> %.1f\n" .
                "- 良好：%.1f -> %.1f\n" .
                "- 合格：%.1f -> %.1f%s\n" .
                "关联年级：%s -> %s",
                $oldSubject['subject_name'],
                $id,
                $oldSubject['subject_name'],
                $subjectName,
                $oldSubject['full_score'],
                $fullScore,
                $oldSubject['excellent_score'],
                $excellentScore,
                $oldSubject['good_score'],
                $goodScore,
                $oldSubject['pass_score'],
                $passScore,
                $splitInfo,
                implode('、', $oldGradeList),
                implode('、', $gradeList)
            );

            $this->logger->info($logDetail, [
                'action_type' => 'update',
                'action_detail' => $logDetail
            ]);

            // 判断是否修改了分数线设置
            $scoreLineChanged = (
                $oldSubject['full_score'] != $fullScore ||
                $oldSubject['excellent_score'] != $excellentScore ||
                $oldSubject['good_score'] != $goodScore ||
                $oldSubject['pass_score'] != $passScore
            );

            // 如果修改了分数线，更新所有已生成的统计分析数据
            if ($scoreLineChanged && $hasScores) {
                $this->updateAnalyticsData($id, $settingId);
            }

            return $this->json([
                'success' => true,
                'message' => '更新成功',
                'data' => [
                    'id' => $id,
                    'subject_name' => $subjectName
                ]
            ]);

        } catch (\Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            
            $this->logger->error("学科更新失败: " . $e->getMessage(), [
                'exception' => $e
            ]);
            
            return $this->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * 更新与学科相关的统计分析数据
     */
    private function updateAnalyticsData($subjectId, $settingId) {
        try {
            // 获取与该学科相关的所有班级
            $stmt = $this->db->query(
                "SELECT DISTINCT c.id as class_id, c.grade_id 
                FROM classes c 
                INNER JOIN scores s ON c.id = s.class_id 
                WHERE s.subject_id = ? AND c.status = 1",
                [$subjectId]
            );
            
            $classes = $stmt->fetchAll();
            
            if (empty($classes)) {
                return true; // 没有需要更新的班级
            }
            
            // 实例化ClassAnalyticsController
            $analyticsController = new \controllers\ClassAnalyticsController($this->db, $this->logger);
            
            // 遍历每个班级，重新生成统计分析数据
            foreach ($classes as $class) {
                // 设置POST参数
                $_POST['grade_id'] = $class['grade_id'];
                $_POST['class_id'] = $class['class_id'];
                $_POST['subject_id'] = $subjectId;
                $_POST['setting_id'] = $settingId;
                
                // 调用生成统计数据的方法
                $result = $analyticsController->generateAnalytics();
                
                // 记录日志
                $this->logger->info("更新班级统计分析数据", [
                    'class_id' => $class['class_id'],
                    'subject_id' => $subjectId,
                    'result' => $result
                ]);
            }
            
            return true;
        } catch (\Exception $e) {
            $this->logger->error("更新统计分析数据失败: " . $e->getMessage(), [
                'exception' => $e
            ]);
            return false;
        }
    }

    public function getList() {
        try {
            // 获取当前启用的项目ID
            $stmt = $this->db->query(
                "SELECT id FROM settings WHERE status = 1 ORDER BY id DESC LIMIT 1"
            );
            $setting = $stmt->fetch();
            $settingId = $setting['id'] ?? null;

            if (!$settingId) {
                return $this->json(['success' => false, 'error' => '未找到启用的项目'], 400);
            }

            // 获取所有学科及其关联的年级信息和分数设置
            $stmt = $this->db->query(
                "SELECT s.*, 
                    (SELECT GROUP_CONCAT(g.grade_name ORDER BY g.grade_code) 
                     FROM grades g 
                     INNER JOIN subject_grades sg ON g.id = sg.grade_id 
                     WHERE sg.subject_id = s.id) as grade_names,
                    (SELECT GROUP_CONCAT(g.id) 
                     FROM grades g 
                     INNER JOIN subject_grades sg ON g.id = sg.grade_id 
                     WHERE sg.subject_id = s.id) as grade_ids
                FROM subjects s 
                WHERE s.setting_id = ?
                ORDER BY s.subject_code ASC",
                [$settingId]
            );
            
            $subjects = $stmt->fetchAll();
            
            // 处理年级名称和ID字符串
            foreach ($subjects as &$subject) {
                $subject['grade_names'] = $subject['grade_names'] ? explode(',', $subject['grade_names']) : [];
                $subject['grade_ids'] = $subject['grade_ids'] ? explode(',', $subject['grade_ids']) : [];
                // 确保ID是字符串类型
                $subject['id'] = (string)$subject['id'];
                // 确保分数值是数字类型
                $subject['full_score'] = floatval($subject['full_score']);
                $subject['excellent_score'] = floatval($subject['excellent_score']);
                $subject['good_score'] = floatval($subject['good_score']);
                $subject['pass_score'] = floatval($subject['pass_score']);
            }

            return $this->json([
                'success' => true,
                'data' => $subjects
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * 根据项目ID获取学科列表
     */
    public function get_list() {
        try {
            $projectId = $_GET['project_id'] ?? '';
            $type = $_GET['type'] ?? '';

            if (empty($projectId)) {
                return $this->json(['success' => false, 'error' => '项目ID不能为空'], 400);
            }

            // 获取指定项目的所有学科及其关联的年级信息
            $stmt = $this->db->query(
                "SELECT s.*, 
                    (SELECT GROUP_CONCAT(g.grade_name ORDER BY g.grade_code) 
                     FROM grades g 
                     INNER JOIN subject_grades sg ON g.id = sg.grade_id 
                     WHERE sg.subject_id = s.id) as grade_names,
                    (SELECT GROUP_CONCAT(g.id) 
                     FROM grades g 
                     INNER JOIN subject_grades sg ON g.id = sg.grade_id 
                     WHERE sg.subject_id = s.id) as grade_ids
                FROM subjects s 
                WHERE s.setting_id = ?
                ORDER BY s.subject_code ASC",
                [$projectId]
            );
            
            $subjects = $stmt->fetchAll();
            
            // 处理年级名称和ID字符串
            foreach ($subjects as &$subject) {
                $subject['grade_names'] = $subject['grade_names'] ? explode(',', $subject['grade_names']) : [];
                $subject['grade_ids'] = $subject['grade_ids'] ? explode(',', $subject['grade_ids']) : [];
                // 确保ID是字符串类型
                $subject['id'] = (string)$subject['id'];
            }

            return $this->json([
                'success' => true,
                'data' => $subjects
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * 删除项目相关的学科数据
     */
    public function deleteByProject() {
        $projectId = $_POST['project_id'] ?? '';
        
        if (empty($projectId)) {
            return $this->json(['success' => false, 'error' => '参数不完整'], 400);
        }

        try {
            $this->db->beginTransaction();

            // 获取项目下所有学科的信息
            $stmt = $this->db->query(
                "SELECT s.*, GROUP_CONCAT(g.grade_name) as grade_names
                 FROM subjects s
                 LEFT JOIN subject_grades sg ON s.id = sg.subject_id
                 LEFT JOIN grades g ON sg.grade_id = g.id
                 WHERE s.setting_id = ?
                 GROUP BY s.id",
                [$projectId]
            );
            $subjects = $stmt->fetchAll();

            // 先删除学科关联的成绩记录
            $this->db->query(
                "DELETE scores FROM scores 
                 INNER JOIN subjects ON scores.subject_id = subjects.id 
                 WHERE subjects.setting_id = ?",
                [$projectId]
            );

            // 删除学科-年级关联
            $this->db->query(
                "DELETE subject_grades FROM subject_grades 
                 INNER JOIN subjects ON subject_grades.subject_id = subjects.id 
                 WHERE subjects.setting_id = ?",
                [$projectId]
            );

            // 删除学科
            $this->db->query(
                "DELETE FROM subjects WHERE setting_id = ?",
                [$projectId]
            );

            $this->db->commit();

            // 记录详细的操作日志
            $subjectDetails = array_map(function($subject) {
                return sprintf(
                    "- %s（代码：%s）\n  关联年级：%s\n  分数设置：满分 %.1f，优秀 %.1f，良好 %.1f，及格 %.1f",
                    $subject['subject_name'],
                    $subject['subject_code'],
                    $subject['grade_names'] ? str_replace(',', '、', $subject['grade_names']) : '无',
                    $subject['full_score'],
                    $subject['excellent_score'],
                    $subject['good_score'],
                    $subject['pass_score']
                );
            }, $subjects);

            $logDetail = sprintf(
                "删除项目（ID：%s）的所有学科，共 %d 个：\n\n%s",
                $projectId,
                count($subjects),
                implode("\n\n", $subjectDetails)
            );

            $this->logger->info($logDetail, [
                'action_type' => 'delete',
                'action_detail' => $logDetail
            ]);

            return $this->json([
                'success' => true,
                'message' => '删除成功'
            ]);
        } catch (\Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            
            // 记录错误日志
            $this->logger->error('删除项目相关学科失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'project_id' => $projectId
            ]);

            return $this->json([
                'success' => false,
                'error' => '系统错误，请查看错误日志了解详情'
            ], 500);
        }
    }

    /**
     * 检查学科名称在选中年级中是否已存在
     */
    public function checkName() {
        try {
            $subjectName = trim($_GET['subject_name'] ?? '');
            $gradeIds = json_decode($_GET['grade_ids'] ?? '[]', true);
            $currentSubjectId = $_GET['current_subject_id'] ?? null;

            if (empty($subjectName) || empty($gradeIds)) {
                return $this->json([
                    'success' => false,
                    'error' => '参数不完整'
                ]);
            }

            // 获取当前启用的项目ID
            $stmt = $this->db->query(
                "SELECT id FROM settings WHERE status = 1 ORDER BY id DESC LIMIT 1"
            );
            $setting = $stmt->fetch();
            $settingId = $setting['id'] ?? null;

            if (!$settingId) {
                return $this->json([
                    'success' => false,
                    'error' => '未找到启用的项目'
                ]);
            }

            // 构建查询SQL，同时获取年级名称，添加项目ID条件
            $sql = "SELECT s.id, s.subject_name, sg.grade_id, g.grade_name 
                   FROM subjects s 
                   JOIN subject_grades sg ON s.id = sg.subject_id 
                   JOIN grades g ON sg.grade_id = g.id
                   WHERE s.subject_name = ? 
                   AND s.setting_id = ? 
                   AND sg.grade_id IN (" . implode(',', array_fill(0, count($gradeIds), '?')) . ")";
            
            $params = [$subjectName, $settingId];
            foreach ($gradeIds as $gradeId) {
                $params[] = $gradeId;
            }

            // 如果是编辑模式，排除当前学科
            if ($currentSubjectId) {
                $sql .= " AND s.id != ?";
                $params[] = $currentSubjectId;
            }

            $stmt = $this->db->query($sql, $params);
            $conflicts = $stmt->fetchAll();
            
            if (!empty($conflicts)) {
                // 收集冲突的年级名称
                $conflictGrades = array_map(function($item) {
                    return $item['grade_name'];
                }, $conflicts);

                return $this->json([
                    'success' => true,
                    'exists' => true,
                    'conflictGrades' => $conflictGrades,
                    'message' => '以下年级已存在名为"' . $subjectName . '"的学科：' . implode('、', $conflictGrades)
                ]);
            }

            return $this->json([
                'success' => true,
                'exists' => false
            ]);
        } catch (\PDOException $e) {
            $this->logger->error('检查学科名称失败', [
                'error' => $e->getMessage(),
                'subject_name' => $subjectName ?? null,
                'grade_ids' => $gradeIds ?? null
            ]);
            return $this->json([
                'success' => false,
                'error' => '检查学科名称失败'
            ]);
        }
    }

    /**
     * 生成随机学科代码
     * 生成6位大写字母和数字组合的唯一代码
     */
    public function generateCode() {
        try {
            // 获取当前项目ID
            $stmt = $this->db->query(
                "SELECT id FROM settings WHERE status = 1 ORDER BY id DESC LIMIT 1"
            );
            $setting = $stmt->fetch();
            $settingId = $setting['id'] ?? null;

            if (!$settingId) {
                return $this->json(['success' => false, 'error' => '未找到启用的项目']);
            }

            $maxAttempts = 10; // 最大尝试次数
            $attempt = 0;
            
            do {
                // 生成6位随机代码
                $code = '';
                $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
                $charLength = strlen($characters);
                
                for ($i = 0; $i < 6; $i++) {
                    $code .= $characters[rand(0, $charLength - 1)];
                }
                
                // 检查代码是否已存在
                $stmt = $this->db->query(
                    "SELECT COUNT(*) as count FROM subjects WHERE subject_code = ? AND setting_id = ?",
                    [$code, $settingId]
                );
                $result = $stmt->fetch();
                
                $attempt++;
                
                // 如果代码不存在，或者已经尝试超过最大次数，则退出循环
                if ($result['count'] == 0 || $attempt >= $maxAttempts) {
                    break;
                }
            } while (true);
            
            if ($attempt >= $maxAttempts && $result['count'] > 0) {
                return $this->json(['success' => false, 'error' => '无法生成唯一的学科代码，请稍后重试']);
            }
            
            return $this->json([
                'success' => true,
                'data' => ['code' => $code]
            ]);
            
        } catch (\Exception $e) {
            $this->logger->error('生成学科代码失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->json([
                'success' => false,
                'error' => '生成学科代码失败'
            ], 500);
        }
    }

    /**
     * 检查学科是否有成绩数据
     * API路径: settings/subject/check_has_scores
     */
    public function checkHasScores() {
        try {
            $subjectId = $_GET['id'] ?? null;
            $settingId = $_GET['setting_id'] ?? null;

            if (!$subjectId) {
                return $this->json(['success' => false, 'error' => '缺少必要参数: id'], 400);
            }

            // 查询该学科是否有成绩数据
            $stmt = $this->db->query(
                "SELECT COUNT(*) as count FROM scores WHERE subject_id = ?",
                [$subjectId]
            );
            $result = $stmt->fetch();
            $hasScores = $result && $result['count'] > 0;

            // 如果有成绩数据，获取具体是哪些年级下有数据
            $gradeWithScores = [];
            if ($hasScores) {
                $stmt = $this->db->query(
                    "SELECT DISTINCT grade_id FROM scores WHERE subject_id = ?",
                    [$subjectId]
                );
                while ($row = $stmt->fetch()) {
                    $gradeWithScores[] = $row['grade_id'];
                }
            }

            return $this->json([
                'success' => true,
                'data' => [
                    'has_scores' => $hasScores,
                    'grades_with_scores' => $gradeWithScores
                ]
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
} 