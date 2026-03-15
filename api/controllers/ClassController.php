<?php
/**
 * 文件名: api/controllers/ClassController.php
 * 功能描述: API班级控制器
 * 
 * 该控制器负责:
 * 1. 班级数据的增删改查操作
 * 2. 班级列表的获取
 * 3. 班级数据的验证和处理
 * 
 * API调用路由:
 * - GET /api/list: 获取班级列表
 * - POST /api/add: 添加新班级
 * - POST /api/update: 更新班级信息
 * - POST /api/delete: 删除班级
 * 
 * 所有操作均需要提供setting_id参数，表示项目ID
 * 大部分操作需要提供grade_id参数，表示年级ID
 * 
 * 关联文件:
 * - BaseController.php: 基础控制器，提供数据库连接和通用方法
 * - api/index.php: API路由分发
 * - controllers/ClassController.php: 主系统中的班级控制器
 * - api/controllers/GradeController.php: API年级控制器
 */

class ClassController extends BaseController {
    public function list() {
        try {
            $setting_id = isset($_GET['setting_id']) ? intval($_GET['setting_id']) : 0;
            $grade_id = isset($_GET['grade_id']) ? intval($_GET['grade_id']) : 0;

            if (!$setting_id) {
                throw new Exception('缺少项目ID');
            }
            if (!$grade_id) {
                throw new Exception('缺少年级ID');
            }

            $stmt = $this->db->prepare("SELECT * FROM classes WHERE setting_id = ? AND grade_id = ? ORDER BY class_code");
            $stmt->execute([$setting_id, $grade_id]);
            $classes = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $this->success('获取成功', $classes);
        } catch (Exception $e) {
            $this->error('获取失败：' . $e->getMessage());
        }
    }

    public function add() {
        try {
            $setting_id = isset($_POST['setting_id']) ? intval($_POST['setting_id']) : 0;
            $grade_id = isset($_POST['grade_id']) ? intval($_POST['grade_id']) : 0;
            $class_name = isset($_POST['class_name']) ? trim($_POST['class_name']) : '';
            $class_code = isset($_POST['class_code']) ? trim($_POST['class_code']) : '';

            if (!$setting_id) {
                throw new Exception('缺少项目ID');
            }
            if (!$grade_id) {
                throw new Exception('缺少年级ID');
            }
            if (!$class_name) {
                throw new Exception('缺少班级名称');
            }
            if (!$class_code) {
                throw new Exception('缺少班级代码');
            }

            // 检查同一项目和年级下是否存在相同代码的班级
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM classes WHERE setting_id = ? AND grade_id = ? AND class_code = ?");
            $stmt->execute([$setting_id, $grade_id, $class_code]);
            if ($stmt->fetchColumn() > 0) {
                throw new Exception('该班级代码已存在');
            }

            $stmt = $this->db->prepare("INSERT INTO classes (setting_id, grade_id, class_name, class_code) VALUES (?, ?, ?, ?)");
            $stmt->execute([$setting_id, $grade_id, $class_name, $class_code]);
            
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
            $class_name = isset($_POST['class_name']) ? trim($_POST['class_name']) : '';
            $class_code = isset($_POST['class_code']) ? trim($_POST['class_code']) : '';

            if (!$id) {
                throw new Exception('缺少班级ID');
            }
            if (!$setting_id) {
                throw new Exception('缺少项目ID');
            }
            if (!$grade_id) {
                throw new Exception('缺少年级ID');
            }
            if (!$class_name) {
                throw new Exception('缺少班级名称');
            }
            if (!$class_code) {
                throw new Exception('缺少班级代码');
            }

            // 检查同一项目和年级下是否存在相同代码的其他班级
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM classes WHERE setting_id = ? AND grade_id = ? AND class_code = ? AND id != ?");
            $stmt->execute([$setting_id, $grade_id, $class_code, $id]);
            if ($stmt->fetchColumn() > 0) {
                throw new Exception('该班级代码已存在');
            }

            $stmt = $this->db->prepare("UPDATE classes SET class_name = ?, class_code = ? WHERE id = ? AND setting_id = ? AND grade_id = ?");
            $stmt->execute([$class_name, $class_code, $id, $setting_id, $grade_id]);
            
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
                throw new Exception('缺少班级ID');
            }
            if (!$setting_id) {
                throw new Exception('缺少项目ID');
            }

            $this->db->beginTransaction();

            try {
                // 先删除相关的统计分析数据
                $stmt = $this->db->prepare("DELETE FROM score_analytics WHERE class_id = ? AND setting_id = ?");
                $stmt->execute([$id, $setting_id]);

                // 再删除班级数据
                $stmt = $this->db->prepare("DELETE FROM classes WHERE id = ? AND setting_id = ?");
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
} 