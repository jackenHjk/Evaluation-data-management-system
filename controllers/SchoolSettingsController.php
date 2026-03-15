<?php
/**
 * 文件名: controllers/SchoolSettingsController.php
 * 功能描述: 学校设置控制器
 * 
 * 该控制器负责:
 * 1. 获取和更新学校基本信息
 * 2. 学校名称、学期、项目名称等数据管理
 * 3. 数据验证和存储
 * 
 * API调用路由:
 * - school/info: 获取学校信息
 * - school/save: 保存学校信息
 * 
 * 关联文件:
 * - core/Controller.php: 基础控制器
 * - modules/school_settings.php: 学校设置页面
 * - settings表: 存储学校设置信息的数据表
 * - assets/js/settings-school.js: 学校设置客户端脚本
 */

namespace Controllers;

// 防止直接访问
if (!defined('BASE_PATH')) {
    http_response_code(403);
    exit('禁止直接访问');
}

use Core\Controller;

class SchoolSettingsController extends Controller {
    public function getInfo() {
        try {
            // 检查权限
            if (!$this->checkPermission('settings')) {
                return $this->json(['error' => '无权访问'], 403);
            }

            $stmt = $this->db->query("SELECT * FROM settings ORDER BY id DESC LIMIT 1");
            $settings = $stmt->fetch();
            
            return $this->json([
                'success' => true,
                'data' => $settings ?: [
                    'school_name' => '',
                    'current_semester' => '',
                    'project_name' => ''
                ]
            ]);
        } catch (\Exception $e) {
            error_log("获取学校设置错误: " . $e->getMessage());
            return $this->json(['error' => '获取设置失败：' . $e->getMessage()], 500);
        }
    }
    
    public function save() {
        try {
            // 检查权限
            if (!$this->checkPermission('settings')) {
                return $this->json(['error' => '无权访问'], 403);
            }

            $schoolName = $_POST['school_name'] ?? '';
            $currentSemester = $_POST['current_semester'] ?? '';
            $projectName = $_POST['project_name'] ?? '';
            
            if (empty($schoolName) || empty($currentSemester) || empty($projectName)) {
                return $this->json(['error' => '所有字段都必须填写'], 400);
            }
            
            // 开始事务
            $this->db->query("START TRANSACTION");
            
            try {
                // 检查是否已有设置
                $stmt = $this->db->query("SELECT id FROM settings LIMIT 1");
                $existing = $stmt->fetch();
                
                if ($existing) {
                    // 更新现有设置
                    $this->db->query(
                        "UPDATE settings 
                         SET school_name = ?, 
                             current_semester = ?, 
                             project_name = ?, 
                             updated_at = NOW() 
                         WHERE id = ?",
                        [$schoolName, $currentSemester, $projectName, $existing['id']]
                    );
                } else {
                    // 创建新设置
                    $this->db->query(
                        "INSERT INTO settings 
                         (school_name, current_semester, project_name, created_at, updated_at) 
                         VALUES (?, ?, ?, NOW(), NOW())",
                        [$schoolName, $currentSemester, $projectName]
                    );
                }
                
                $this->db->query("COMMIT");
                
                return $this->json([
                    'success' => true,
                    'message' => '设置保存成功'
                ]);
            } catch (\Exception $e) {
                $this->db->query("ROLLBACK");
                throw $e;
            }
        } catch (\Exception $e) {
            error_log("保存学校设置错误: " . $e->getMessage());
            return $this->json(['error' => '保存设置失败：' . $e->getMessage()], 500);
        }
    }
} 