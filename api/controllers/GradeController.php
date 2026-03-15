<?php
/**
 * 文件名: api/controllers/GradeController.php
 * 功能描述: API年级控制器
 * 
 * 该控制器负责:
 * 1. 年级数据的增删改查操作
 * 2. 年级列表的获取
 * 3. 年级数据的验证和处理
 * 
 * API调用路由:
 * - GET /api/list: 获取年级列表
 * - POST /api/add: 添加新年级
 * - POST /api/update: 更新年级信息
 * - POST /api/delete: 删除年级
 * 
 * 所有操作均需要提供setting_id参数，表示项目ID
 * 
 * 关联文件:
 * - BaseController.php: 基础控制器，提供数据库连接和通用方法
 * - api/index.php: API路由分发
 * - controllers/GradeController.php: 主系统中的年级控制器
 */

class GradeController extends BaseController {
    public function list() {
        try {
            $setting_id = isset($_GET['setting_id']) ? intval($_GET['setting_id']) : 0;
            if (!$setting_id) {
                throw new Exception('缺少项目ID');
            }

            $stmt = $this->db->prepare("SELECT * FROM grades WHERE setting_id = ? ORDER BY grade_code");
            $stmt->execute([$setting_id]);
            $grades = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $this->success('获取成功', $grades);
        } catch (Exception $e) {
            $this->error('获取失败：' . $e->getMessage());
        }
    }

    public function add() {
        try {
            $setting_id = isset($_POST['setting_id']) ? intval($_POST['setting_id']) : 0;
            $grade_name = isset($_POST['grade_name']) ? trim($_POST['grade_name']) : '';
            $grade_code = isset($_POST['grade_code']) ? trim($_POST['grade_code']) : '';

            if (!$setting_id) {
                throw new Exception('缺少项目ID');
            }
            if (!$grade_name) {
                throw new Exception('缺少年级名称');
            }
            if (!$grade_code) {
                throw new Exception('缺少年级代码');
            }

            // 检查同一项目下是否存在相同代码的年级
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM grades WHERE setting_id = ? AND grade_code = ?");
            $stmt->execute([$setting_id, $grade_code]);
            if ($stmt->fetchColumn() > 0) {
                throw new Exception('该年级代码已存在');
            }

            // 添加年级
            $stmt = $this->db->prepare("INSERT INTO grades (setting_id, grade_name, grade_code, status) VALUES (?, ?, ?, 1)");
            $stmt->execute([$setting_id, $grade_name, $grade_code]);
            
            $this->success('添加成功');
        } catch (Exception $e) {
            $this->error('添加失败：' . $e->getMessage());
        }
    }

    public function update() {
        try {
            $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
            $setting_id = isset($_POST['setting_id']) ? intval($_POST['setting_id']) : 0;
            $grade_name = isset($_POST['grade_name']) ? trim($_POST['grade_name']) : '';
            $grade_code = isset($_POST['grade_code']) ? trim($_POST['grade_code']) : '';

            if (!$id) {
                throw new Exception('缺少年级ID');
            }
            if (!$setting_id) {
                throw new Exception('缺少项目ID');
            }
            if (!$grade_name) {
                throw new Exception('缺少年级名称');
            }
            if (!$grade_code) {
                throw new Exception('缺少年级代码');
            }

            // 检查同一项目下是否存在相同代码的其他年级
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM grades WHERE setting_id = ? AND grade_code = ? AND id != ?");
            $stmt->execute([$setting_id, $grade_code, $id]);
            if ($stmt->fetchColumn() > 0) {
                throw new Exception('该年级代码已存在');
            }

            $stmt = $this->db->prepare("UPDATE grades SET grade_name = ?, grade_code = ? WHERE id = ? AND setting_id = ?");
            $stmt->execute([$grade_name, $grade_code, $id, $setting_id]);
            
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
                throw new Exception('缺少年级ID');
            }
            if (!$setting_id) {
                throw new Exception('缺少项目ID');
            }

            $this->db->beginTransaction();

            try {
                // 先删除相关的统计分析数据
                $stmt = $this->db->prepare("DELETE FROM score_analytics WHERE grade_id = ? AND setting_id = ?");
                $stmt->execute([$id, $setting_id]);

                // 再删除年级数据
                $stmt = $this->db->prepare("DELETE FROM grades WHERE id = ? AND setting_id = ?");
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