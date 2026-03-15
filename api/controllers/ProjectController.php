<?php
/**
 * 文件名: api/controllers/ProjectController.php
 * 功能描述: API项目控制器
 * 
 * 该控制器负责:
 * 1. 项目数据的增删改查操作
 * 2. 数据同步功能 - 将现有项目的数据（年级、班级、学生）复制到新项目
 * 3. 项目状态切换
 * 
 * API调用路由:
 * - POST /api/add: 添加新项目，支持同步现有数据
 * - 其他方法（查询、更新、删除等）
 * 
 * 关联文件:
 * - BaseController.php: 基础控制器，提供数据库连接和通用方法
 * - api/index.php: API路由分发
 * - controllers/ProjectController.php: 主系统中的项目控制器
 * - api/controllers/GradeController.php: API年级控制器
 * - api/controllers/ClassController.php: API班级控制器
 * - api/controllers/StudentController.php: API学生控制器
 */

class ProjectController extends BaseController {
    public function add() {
        try {
            $school_name = isset($_POST['school_name']) ? trim($_POST['school_name']) : '';
            $current_semester = isset($_POST['current_semester']) ? trim($_POST['current_semester']) : '';
            $project_name = isset($_POST['project_name']) ? trim($_POST['project_name']) : '';
            $sync_data = isset($_POST['sync_data']) ? filter_var($_POST['sync_data'], FILTER_VALIDATE_BOOLEAN) : false;

            if (!$current_semester) {
                throw new Exception('缺少学期');
            }
            if (!$project_name) {
                throw new Exception('缺少项目名称');
            }

            // 检查同一学期下是否存在相同项目名称
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM settings WHERE current_semester = ? AND project_name = ?");
            $stmt->execute([$current_semester, $project_name]);
            if ($stmt->fetchColumn() > 0) {
                throw new Exception('该学期下已存在相同项目名称');
            }

            $this->db->beginTransaction();

            try {
                // 插入新项目
                $stmt = $this->db->prepare("INSERT INTO settings (school_name, current_semester, project_name) VALUES (?, ?, ?)");
                $stmt->execute([$school_name, $current_semester, $project_name]);
                $new_setting_id = $this->db->lastInsertId();

                // 如果需要同步数据
                if ($sync_data) {
                    // 获取当前可用项目的ID
                    $stmt = $this->db->prepare("SELECT id FROM settings WHERE status = 1 LIMIT 1");
                    $stmt->execute();
                    $current_setting_id = $stmt->fetchColumn();

                    if ($current_setting_id) {
                        // 复制年级数据
                        $stmt = $this->db->prepare("
                            INSERT INTO grades (setting_id, grade_name, grade_code)
                            SELECT ?, grade_name, grade_code
                            FROM grades
                            WHERE setting_id = ?
                        ");
                        $stmt->execute([$new_setting_id, $current_setting_id]);

                        // 获取年级ID映射关系
                        $grade_map = [];
                        $stmt = $this->db->prepare("
                            SELECT old.id as old_id, new.id as new_id
                            FROM grades old
                            JOIN grades new ON old.grade_code = new.grade_code
                            WHERE old.setting_id = ? AND new.setting_id = ?
                        ");
                        $stmt->execute([$current_setting_id, $new_setting_id]);
                        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                            $grade_map[$row['old_id']] = $row['new_id'];
                        }

                        // 复制班级数据
                        foreach ($grade_map as $old_grade_id => $new_grade_id) {
                            $stmt = $this->db->prepare("
                                INSERT INTO classes (setting_id, grade_id, class_name, class_code)
                                SELECT ?, ?, class_name, class_code
                                FROM classes
                                WHERE setting_id = ? AND grade_id = ?
                            ");
                            $stmt->execute([$new_setting_id, $new_grade_id, $current_setting_id, $old_grade_id]);
                        }

                        // 获取班级ID映射关系
                        $class_map = [];
                        $stmt = $this->db->prepare("
                            SELECT old.id as old_id, new.id as new_id
                            FROM classes old
                            JOIN classes new ON old.class_code = new.class_code AND old.grade_id = ?
                            WHERE old.setting_id = ? AND new.setting_id = ?
                        ");
                        foreach ($grade_map as $old_grade_id => $new_grade_id) {
                            $stmt->execute([$old_grade_id, $current_setting_id, $new_setting_id]);
                            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                $class_map[$row['old_id']] = $row['new_id'];
                            }
                        }

                        // 复制学生数据
                        foreach ($class_map as $old_class_id => $new_class_id) {
                            $stmt = $this->db->prepare("
                                INSERT INTO students (setting_id, grade_id, class_id, student_name, student_number)
                                SELECT ?, 
                                       (SELECT id FROM grades WHERE setting_id = ? AND grade_code = (
                                           SELECT grade_code FROM grades WHERE id = students.grade_id
                                       )),
                                       ?,
                                       student_name,
                                       student_number
                                FROM students
                                WHERE setting_id = ? AND class_id = ?
                            ");
                            $stmt->execute([$new_setting_id, $new_setting_id, $new_class_id, $current_setting_id, $old_class_id]);
                        }
                    }
                }

                $this->db->commit();
                $this->success('添加成功');
            } catch (Exception $e) {
                $this->db->rollBack();
                throw $e;
            }
        } catch (Exception $e) {
            $this->error('添加失败：' . $e->getMessage());
        }
    }
} 