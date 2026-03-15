<?php
/**
 * 文件名: controllers/ClassController.php
 * 功能描述: 班级管理控制器
 * 
 * 该控制器负责:
 * 1. 班级信息的增删改查
 * 2. 班级列表获取与筛选
 * 3. 班级数据验证
 * 4. 班级代码唯一性检查
 * 
 * API调用路由:
 * - settings/grade/list: 获取班级列表
 * - settings/classes: 获取班级列表（按年级筛选）
 * - class/get: 获取班级详情
 * - class/add: 添加班级
 * - class/update: 更新班级信息
 * - class/delete: 删除班级
 * - class/check_code: 检查班级代码是否重复
 * 
 * 关联文件:
 * - core/Controller.php: 基础控制器
 * - modules/class_settings.php: 班级设置页面
 * - api/controllers/ClassController.php: API班级控制器
 * - api/index.php: API入口文件
 */

namespace Controllers;

use Core\Controller;

class ClassController extends Controller {
    public function getList() {
        try {
            $this->logger->debug('获取班级列表', [
                'user_id' => $_SESSION['user_id'] ?? null,
                'role' => $_SESSION['role'] ?? null,
                'grade_id' => $_GET['grade_id'] ?? null
            ]);
            
            $gradeId = $_GET['grade_id'] ?? null;
            
            // 构建基础SQL查询
            $sql = "SELECT c.*, g.grade_name, g.grade_code,
                    (SELECT COUNT(*) FROM students s WHERE s.class_id = c.id AND s.status = 1) as student_count 
                    FROM classes c 
                    LEFT JOIN grades g ON c.grade_id = g.id 
                    WHERE c.status = 1
                    AND c.setting_id = (SELECT id FROM settings WHERE status = 1 LIMIT 1)";
            $params = [];

            // 根据用户角色添加权限过滤
            $role = $_SESSION['role'] ?? '';
            if (!in_array($role, ['admin', 'teaching'])) {
                $this->logger->debug('应用权限过滤', [
                    'user_id' => $_SESSION['user_id'],
                    'role' => $role
                ]);
                $sql = "SELECT c.*, g.grade_name, g.grade_code,
                        (SELECT COUNT(*) FROM students s WHERE s.class_id = c.id AND s.status = 1) as student_count 
                        FROM classes c 
                        LEFT JOIN grades g ON c.grade_id = g.id 
                        INNER JOIN user_permissions up ON c.grade_id = up.grade_id 
                        WHERE c.status = 1 AND up.user_id = ?
                        AND c.setting_id = (SELECT id FROM settings WHERE status = 1 LIMIT 1)";
                $params[] = $_SESSION['user_id'];

                // 如果是数据录入员，只显示有成绩录入权限的班级
                if ($role === 'marker') {
                    $sql .= " AND up.subject_id IS NOT NULL AND up.can_edit = 1";
                }
                // 如果是班主任，只显示有学生管理权限的班级
                else if ($role === 'headteacher') {
                    $sql .= " AND up.can_edit_students = 1";
                }
            }

            // 如果指定了年级ID，添加年级筛选条件
            if ($gradeId) {
                $sql .= " AND c.grade_id = ?";
                $params[] = $gradeId;
            }

            $sql .= " ORDER BY g.grade_code, c.class_code";
            
            $stmt = $this->db->query($sql, $params);
            $classes = $stmt->fetchAll();

            $this->logger->debug('班级列表获取成功', [
                'count' => count($classes),
                'grade_id' => $gradeId
            ]);

            return $this->json([
                'success' => true,
                'data' => $classes
            ]);
        } catch (\Exception $e) {
            $this->logger->error('获取班级列表失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => $_SESSION['user_id'] ?? null,
                'grade_id' => $_GET['grade_id'] ?? null
            ]);
            return $this->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // 获取单个班级信息
    public function get() {
        try {
            error_log("ClassController::get called with id: " . ($_GET['id'] ?? 'none'));
            
            $id = $_GET['id'] ?? '';
            
            if (empty($id)) {
                return $this->json(['error' => '参数不完整'], 400);
            }

            $stmt = $this->db->query(
                "SELECT c.*, g.grade_name, g.grade_code 
                 FROM classes c 
                 JOIN grades g ON c.grade_id = g.id 
                 WHERE c.id = ?",
                [$id]
            );
            $class = $stmt->fetch();
            
            error_log("Found class data: " . print_r($class, true));
            
            if (!$class) {
                return $this->json(['error' => '班级不存在'], 404);
            }

            // 从班级代码中移除年级代码前缀
            $class['class_code'] = substr($class['class_code'], strlen($class['grade_code']));

            return $this->json([
                'success' => true,
                'data' => $class
            ]);
        } catch (\Exception $e) {
            error_log("Error in ClassController::get: " . $e->getMessage());
            return $this->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // 更新班级信息
    public function update() {
        $id = $_POST['id'] ?? '';
        $className = $_POST['class_name'] ?? '';
        $classCode = $_POST['class_code'] ?? '';
        
        if (empty($id) || empty($className) || empty($classCode)) {
            $this->json(['success' => false, 'error' => '参数不完整'], 400);
            return;
        }

        try {
            $this->db->query("START TRANSACTION");

            // 首先获取班级所属的年级代码
            $stmt = $this->db->query(
                "SELECT g.grade_code 
                 FROM classes c 
                 JOIN grades g ON c.grade_id = g.id 
                 WHERE c.id = ?",
                [$id]
            );
            $grade = $stmt->fetch();
            
            if (!$grade) {
                $this->db->query("ROLLBACK");
                $this->json(['success' => false, 'error' => '找不到对应的年级信息']);
                return;
            }

            // 组合完整的班级代码
            $fullClassCode = $grade['grade_code'] . $classCode;

            // 检查班级代码是否已存在（排除当前班级）
            $stmt = $this->db->query(
                "SELECT COUNT(*) as count 
                 FROM classes 
                 WHERE class_code = ? 
                 AND id != ? 
                 AND status = 1",
                [$fullClassCode, $id]
            );
            
            if ($stmt->fetch()['count'] > 0) {
                $this->db->query("ROLLBACK");
                $this->json(['success' => false, 'error' => '班级代码已存在']);
                return;
            }

            // 更新班级信息
            $this->db->query(
                "UPDATE classes 
                 SET class_name = ?, 
                     class_code = ? 
                 WHERE id = ?",
                [$className, $fullClassCode, $id]
            );

            $this->db->query("COMMIT");
            $this->json(['success' => true, 'message' => '更新成功']);
        } catch (\Exception $e) {
            $this->db->query("ROLLBACK");
            $this->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // 检查班级代码是否存在
    public function checkCode() {
        try {
            if (!$this->checkPermission('settings')) {
                return $this->json(['error' => '无权访问'], 403);
            }

            $code = $_GET['code'] ?? '';
            $gradeId = $_GET['grade_id'] ?? '';
            $id = $_GET['id'] ?? null;
            $settingId = $_GET['setting_id'] ?? null;

            if (empty($code) || empty($gradeId) || empty($settingId)) {
                return $this->json(['error' => '班级代码、年级ID和项目ID不能为空'], 400);
            }

            $sql = "SELECT COUNT(*) as count FROM classes WHERE class_code = ? AND grade_id = ? AND setting_id = ?";
            $params = [$code, $gradeId, $settingId];

            if ($id) {
                $sql .= " AND id != ?";
                $params[] = $id;
            }

            $stmt = $this->db->query($sql, $params);
            $result = $stmt->fetch();

            return $this->json([
                'success' => true,
                'exists' => $result['count'] > 0
            ]);
        } catch (\Exception $e) {
            $this->logger->error('检查班级代码失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'code' => $_GET['code'] ?? null,
                'grade_id' => $_GET['grade_id'] ?? null,
                'user_id' => $_SESSION['user_id'] ?? null
            ]);
            return $this->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // 添加班级
    public function add() {
        try {
            if (!$this->checkPermission('settings')) {
                $this->logger->warning('添加班级权限不足', [
                    'user_id' => $_SESSION['user_id'] ?? null,
                    'role' => $_SESSION['role'] ?? null
                ]);
                return $this->json(['error' => '无权访问'], 403);
            }

            $gradeId = $_POST['grade_id'] ?? '';
            $className = $_POST['class_name'] ?? '';
            $classCode = $_POST['class_code'] ?? '';
            $settingId = $_POST['setting_id'] ?? '';

            if (empty($settingId)) {
                $this->logger->warning('添加班级参数不完整：缺少项目ID', [
                    'user_id' => $_SESSION['user_id'] ?? null
                ]);
                return $this->json(['error' => '缺少项目ID'], 400);
            }

            if (empty($gradeId) || empty($className) || empty($classCode)) {
                $this->logger->warning('添加班级参数不完整', [
                    'grade_id' => $gradeId,
                    'class_name' => $className,
                    'class_code' => $classCode,
                    'user_id' => $_SESSION['user_id'] ?? null
                ]);
                return $this->json(['error' => '请填写完整信息'], 400);
            }

            $this->logger->debug('尝试添加新班级', [
                'grade_id' => $gradeId,
                'class_name' => $className,
                'class_code' => $classCode,
                'user_id' => $_SESSION['user_id'] ?? null
            ]);

            // 获取年级代码
            $stmt = $this->db->query(
                "SELECT grade_code FROM grades WHERE id = ? AND status = 1",
                [$gradeId]
            );
            $grade = $stmt->fetch();
            
            if (!$grade) {
                return $this->json(['error' => '年级不存在或已被禁用'], 400);
            }

            // 组合完整的班级代码
            $fullClassCode = $grade['grade_code'] . $classCode;

            // 检查班级代码是否已存在（在同一项目下）
            $stmt = $this->db->query(
                "SELECT c.*, g.grade_name 
                 FROM classes c 
                 JOIN grades g ON c.grade_id = g.id 
                 WHERE c.class_code = ? AND c.status = 1 AND c.setting_id = ?",
                [$fullClassCode, $settingId]
            );
            $existingClass = $stmt->fetch();
            if ($existingClass) {
                return $this->json([
                    'error' => sprintf(
                        '班级代码 %s 已被 %s 的 %s 使用',
                        $fullClassCode,
                        $existingClass['grade_name'],
                        $existingClass['class_name']
                    )
                ], 400);
            }

            // 开始事务
            $this->db->query("START TRANSACTION");

            try {
                // 插入班级记录
                $this->db->query(
                    "INSERT INTO classes (setting_id, grade_id, class_name, class_code, status, created_at) 
                     VALUES (?, ?, ?, ?, 1, NOW())",
                    [$settingId, $gradeId, $className, $fullClassCode]
                );

                $this->db->query("COMMIT");
                $this->logger->debug('班级添加成功', [
                    'class_id' => $this->db->lastInsertId(),
                    'grade_id' => $gradeId,
                    'class_name' => $className,
                    'user_id' => $_SESSION['user_id'] ?? null
                ]);

                return $this->json([
                    'success' => true,
                    'message' => '班级添加成功'
                ]);
            } catch (\Exception $e) {
                $this->db->query("ROLLBACK");
                throw $e;
            }
        } catch (\Exception $e) {
            $this->logger->error('添加班级失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'grade_id' => $_POST['grade_id'] ?? null,
                'class_name' => $_POST['class_name'] ?? null,
                'user_id' => $_SESSION['user_id'] ?? null
            ]);
            return $this->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * 删除班级
     * 如果班级下存在学生，则无法删除
     */
    public function delete()
    {
        try {
            if (!isset($_POST['id'])) {
                throw new \Exception('参数不完整');
            }

            $classId = intval($_POST['id']);
            
            // 检查班级是否存在学生
            $stmt = $this->db->query(
                "SELECT COUNT(*) as student_count FROM students WHERE class_id = ? AND status = 1",
                [$classId]
            );
            $result = $stmt->fetch();
            
            if ($result['student_count'] > 0) {
                return $this->json([
                    'success' => false,
                    'error' => '该班级下存在学生，无法删除'
                ], 400);
            }
            
            // 开始事务
            $this->db->query("START TRANSACTION");
            
            try {
                // 删除班级
                $stmt = $this->db->query(
                    "DELETE FROM classes WHERE id = ?",
                    [$classId]
                );
                
                if ($stmt->rowCount() === 0) {
                    throw new \Exception('班级不存在或已被删除');
                }
                
                // 提交事务
                $this->db->query("COMMIT");
                
                return $this->json([
                    'success' => true,
                    'message' => '班级删除成功'
                ]);
                
            } catch (\Exception $e) {
                // 回滚事务
                $this->db->query("ROLLBACK");
                throw $e;
            }
            
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => '删除班级失败：' . $e->getMessage()
            ], 500);
        }
    }
} 