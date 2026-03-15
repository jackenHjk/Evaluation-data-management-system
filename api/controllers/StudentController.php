<?php
/**
 * 文件名: api/controllers/StudentController.php
 * 功能描述: API学生控制器
 * 
 * 该控制器负责:
 * 1. 学生数据的增删改查操作
 * 2. 学生列表的获取
 * 3. 学生姓名的单独更新
 * 4. 学生数据的验证和处理
 * 
 * API调用路由:
 * - GET /api/list: 获取学生列表
 * - POST /api/add: 添加新学生
 * - POST /api/update: 更新学生信息
 * - POST /api/delete: 删除学生
 * - POST /api/updateName: 更新学生姓名
 * 
 * 所有操作均需要提供setting_id参数，表示项目ID
 * 大部分操作需要提供grade_id和class_id参数，表示年级ID和班级ID
 * 
 * 关联文件:
 * - BaseController.php: 基础控制器，提供数据库连接和通用方法
 * - api/index.php: API路由分发
 * - controllers/StudentController.php: 主系统中的学生控制器
 * - api/controllers/ClassController.php: API班级控制器
 * - api/controllers/GradeController.php: API年级控制器
 */

class StudentController extends BaseController {
    public function list() {
        try {
            $setting_id = isset($_GET['setting_id']) ? intval($_GET['setting_id']) : 0;
            $grade_id = isset($_GET['grade_id']) ? intval($_GET['grade_id']) : 0;
            $class_id = isset($_GET['class_id']) ? intval($_GET['class_id']) : 0;

            if (!$setting_id) {
                throw new Exception('缺少项目ID');
            }
            if (!$grade_id) {
                throw new Exception('缺少年级ID');
            }
            if (!$class_id) {
                throw new Exception('缺少班级ID');
            }

            $stmt = $this->db->prepare("
                SELECT * FROM students 
                WHERE setting_id = ? AND grade_id = ? AND class_id = ? 
                ORDER BY student_number
            ");
            $stmt->execute([$setting_id, $grade_id, $class_id]);
            $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $this->success('获取成功', $students);
        } catch (Exception $e) {
            $this->error('获取失败：' . $e->getMessage());
        }
    }

    public function add() {
        try {
            $setting_id = isset($_POST['setting_id']) ? intval($_POST['setting_id']) : 0;
            $grade_id = isset($_POST['grade_id']) ? intval($_POST['grade_id']) : 0;
            $class_id = isset($_POST['class_id']) ? intval($_POST['class_id']) : 0;
            $student_name = isset($_POST['student_name']) ? trim($_POST['student_name']) : '';
            $student_number = isset($_POST['student_number']) ? trim($_POST['student_number']) : '';

            if (!$setting_id) {
                throw new Exception('缺少项目ID');
            }
            if (!$grade_id) {
                throw new Exception('缺少年级ID');
            }
            if (!$class_id) {
                throw new Exception('缺少班级ID');
            }
            if (!$student_name) {
                throw new Exception('缺少学生姓名');
            }
            if (!$student_number) {
                throw new Exception('缺少学号');
            }

            // 检查同一项目下是否存在相同学号的学生
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM students WHERE setting_id = ? AND student_number = ?");
            $stmt->execute([$setting_id, $student_number]);
            if ($stmt->fetchColumn() > 0) {
                throw new Exception('该学号已存在');
            }

            // 检查班级是否属于指定的年级和项目
            $stmt = $this->db->prepare("
                SELECT COUNT(*) FROM classes 
                WHERE id = ? AND grade_id = ? AND setting_id = ?
            ");
            $stmt->execute([$class_id, $grade_id, $setting_id]);
            if ($stmt->fetchColumn() == 0) {
                throw new Exception('无效的班级ID');
            }

            $stmt = $this->db->prepare("
                INSERT INTO students (setting_id, grade_id, class_id, student_name, student_number) 
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$setting_id, $grade_id, $class_id, $student_name, $student_number]);
            
            $this->success('添加成功');
        } catch (Exception $e) {
            $this->error('添加失败：' . $e->getMessage());
        }
    }

    public function update() {
        try {
            $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
            $setting_id = isset($_POST['setting_id']) ? intval($_POST['setting_id']) : 0;
            $grade_id = isset($_POST['grade_id']) ? intval($_POST['grade_id']) : 0;
            $class_id = isset($_POST['class_id']) ? intval($_POST['class_id']) : 0;
            $student_name = isset($_POST['student_name']) ? trim($_POST['student_name']) : '';
            $student_number = isset($_POST['student_number']) ? trim($_POST['student_number']) : '';

            if (!$id) {
                throw new Exception('缺少学生ID');
            }
            if (!$setting_id) {
                throw new Exception('缺少项目ID');
            }
            if (!$grade_id) {
                throw new Exception('缺少年级ID');
            }
            if (!$class_id) {
                throw new Exception('缺少班级ID');
            }
            if (!$student_name) {
                throw new Exception('缺少学生姓名');
            }
            if (!$student_number) {
                throw new Exception('缺少学号');
            }

            // 检查同一项目下是否存在相同学号的其他学生
            $stmt = $this->db->prepare("
                SELECT COUNT(*) FROM students 
                WHERE setting_id = ? AND student_number = ? AND id != ?
            ");
            $stmt->execute([$setting_id, $student_number, $id]);
            if ($stmt->fetchColumn() > 0) {
                throw new Exception('该学号已存在');
            }

            // 检查班级是否属于指定的年级和项目
            $stmt = $this->db->prepare("
                SELECT COUNT(*) FROM classes 
                WHERE id = ? AND grade_id = ? AND setting_id = ?
            ");
            $stmt->execute([$class_id, $grade_id, $setting_id]);
            if ($stmt->fetchColumn() == 0) {
                throw new Exception('无效的班级ID');
            }

            $stmt = $this->db->prepare("
                UPDATE students 
                SET student_name = ?, student_number = ?, class_id = ? 
                WHERE id = ? AND setting_id = ? AND grade_id = ?
            ");
            $stmt->execute([$student_name, $student_number, $class_id, $id, $setting_id, $grade_id]);
            
            $this->success('更新成功');
        } catch (Exception $e) {
            $this->error('更新失败：' . $e->getMessage());
        }
    }

    public function delete() {
        try {
            $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
            $setting_id = isset($_POST['setting_id']) ? intval($_POST['setting_id']) : 0;

            if (!$id) {
                throw new Exception('缺少学生ID');
            }
            if (!$setting_id) {
                throw new Exception('缺少项目ID');
            }

            $this->db->beginTransaction();

            try {
                // 先删除相关的成绩数据
                $stmt = $this->db->prepare("DELETE FROM scores WHERE student_id = ? AND setting_id = ?");
                $stmt->execute([$id, $setting_id]);

                // 再删除学生数据
                $stmt = $this->db->prepare("DELETE FROM students WHERE id = ? AND setting_id = ?");
                $stmt->execute([$id, $setting_id]);

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

    /**
     * 更新学生姓名
     */
    public function updateName() {
        try {
            $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
            $student_name = isset($_POST['student_name']) ? trim($_POST['student_name']) : '';

            if (!$id) {
                throw new Exception('缺少学生ID');
            }
            if (!$student_name) {
                throw new Exception('缺少学生姓名');
            }

            // 获取学生当前信息
            $stmt = $this->db->prepare("SELECT class_id FROM students WHERE id = ?");
            $stmt->execute([$id]);
            $student = $stmt->fetch();
            
            if (!$student) {
                throw new Exception('学生不存在');
            }

            // 检查同班级是否有重名
            $stmt = $this->db->prepare(
                "SELECT COUNT(*) FROM students 
                WHERE class_id = ? AND student_name = ? AND id != ?"
            );
            $stmt->execute([$student['class_id'], $student_name, $id]);
            if ($stmt->fetchColumn() > 0) {
                throw new Exception('该班级中已存在同名学生');
            }

            // 更新学生姓名
            $stmt = $this->db->prepare("UPDATE students SET student_name = ? WHERE id = ?");
            $stmt->execute([$student_name, $id]);
            
            $this->success('更新成功');
        } catch (Exception $e) {
            $this->error('更新失败：' . $e->getMessage());
        }
    }
} 