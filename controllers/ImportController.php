<?php
/**
 * 文件名: controllers/ImportController.php
 * 功能描述: 数据导入控制器
 * 
 * 该控制器负责:
 * 1. 处理Excel文件导入功能
 * 2. 解析Excel数据并验证格式
 * 3. 导入年级和班级数据
 * 4. 导入学生数据
 * 5. 处理导入过程中的错误和异常
 * 
 * API调用路由:
 * - import/grade_class: 导入年级和班级数据
 * - import/students: 导入学生数据
 * 
 * 关联文件:
 * - core/Controller.php: 基础控制器
 * - core/Logger.php: 日志记录类
 * - modules/import.php: 数据导入页面
 * - controllers/GradeController.php: 年级控制器，用于校验年级数据
 * - controllers/ClassController.php: 班级控制器，用于校验班级数据
 */

namespace Controllers;

use core\Controller;
use core\Logger;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ImportController extends Controller {
    
    public function importGradeClass() {
        try {
            // 开启错误报告
            ini_set('display_errors', 1);
            error_reporting(E_ALL);

            // 检查权限
            if (!$this->checkPermission('settings')) {
                return $this->json(['error' => '无权访问'], 403);
            }

            // 检查是否有文件上传
            if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
                throw new \Exception('请选择要导入的文件');
            }

            // 获取当前项目ID
            $stmt = $this->db->query("SELECT id FROM settings WHERE status = 1 ORDER BY id DESC LIMIT 1");
            $setting = $stmt->fetch();
            if (!$setting) {
                return $this->json(['error' => '未找到可用的项目'], 400);
            }
            $settingId = $setting['id'];

            $file = $_FILES['file'];
            
            // 检查文件类型
            $allowedTypes = [
                'application/vnd.ms-excel',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
            ];
            if (!in_array($file['type'], $allowedTypes)) {
                throw new \Exception('请上传Excel文件（.xls或.xlsx格式）');
            }

            // 创建临时目录
            $tempDir = __DIR__ . '/../temp';
            if (!file_exists($tempDir)) {
                mkdir($tempDir, 0777, true);
            }
            $tempFile = $tempDir . '/excel_' . uniqid() . '.xlsx';
            if (!move_uploaded_file($file['tmp_name'], $tempFile)) {
                throw new \Exception('文件上传失败');
            }

            try {
                // 使用 PhpSpreadsheet 读取文件
                $reader = IOFactory::createReaderForFile($tempFile);
                $reader->setReadDataOnly(true);
                $spreadsheet = $reader->load($tempFile);
                $worksheet = $spreadsheet->getActiveSheet();
                $data = $worksheet->toArray();

                // 清理临时文件
                unlink($tempFile);

                // 跳过说明行和表头行（前8行）
                $data = array_slice($data, 8);

                // 数据验证
                $errors = [];
                $grades = [];
                $classes = [];
                $row = 9; // 从第9行开始是实际数据

                // 获取现有年级信息
                $existingGrades = $this->db->query(
                    "SELECT id, grade_name, grade_code FROM grades WHERE setting_id = ? AND status = 1",
                    [$settingId]
                )->fetchAll(\PDO::FETCH_ASSOC);
                $existingGradeMap = [];
                foreach ($existingGrades as $grade) {
                    $existingGradeMap[$grade['grade_code']] = [
                        'id' => $grade['id'],
                        'name' => $grade['grade_name'],
                        'code' => $grade['grade_code']
                    ];
                }

                // 获取现有班级信息
                $existingClasses = $this->db->query(
                    "SELECT c.id, c.class_name, c.class_code, g.grade_code 
                     FROM classes c 
                     JOIN grades g ON c.grade_id = g.id 
                     WHERE c.setting_id = ? AND c.status = 1",
                    [$settingId]
                )->fetchAll(\PDO::FETCH_ASSOC);
                $existingClassMap = [];
                foreach ($existingClasses as $class) {
                    if (!isset($existingClassMap[$class['grade_code']])) {
                        $existingClassMap[$class['grade_code']] = [];
                    }
                    $existingClassMap[$class['grade_code']][] = [
                        'name' => $class['class_name'],
                        'code' => $class['class_code']
                    ];
                }

                $lastGradeName = '';
                $lastGradeCode = '';
                $newGrades = [];
                $newClasses = [];
                $existingGradeClasses = [];

                foreach ($data as $item) {
                    // 跳过空行
                    if (empty(array_filter($item))) {
                        $row++;
                        continue;
                    }

                    // 检查数据完整性
                    if (count(array_filter($item)) < 2) {
                        $errors[] = "第{$row}行：数据不完整";
                        $row++;
                        continue;
                    }

                    list($gradeName, $gradeCode, $className, $classCode) = array_map('trim', $item);
                    
                    // 移除可能存在的单引号前缀（Excel文本格式）
                    $gradeCode = ltrim($gradeCode, "'");
                    $classCode = ltrim($classCode, "'");

                    // 如果年级名称和代码为空，使用上一行的年级信息
                    if ((empty($gradeName) || $gradeName === '') && (empty($gradeCode) || $gradeCode === '')) {
                        if (empty($lastGradeName) || empty($lastGradeCode)) {
                            $errors[] = "第{$row}行：年级信息为空，且无法获取上一行的年级信息";
                            $row++;
                            continue;
                        }
                        $gradeName = $lastGradeName;
                        $gradeCode = $lastGradeCode;
                    } else {
                        // 保存当前年级信息，供下一行使用
                        $lastGradeName = $gradeName;
                        $lastGradeCode = $gradeCode;
                    }

                    // 验证必填字段
                    if (empty($className) || empty($classCode)) {
                        $errors[] = "第{$row}行：班级名称和班级代码不能为空";
                        $row++;
                        continue;
                    }

                    // 验证年级代码和班级代码格式（只允许字母和数字）
                    if (!preg_match('/^[a-zA-Z0-9]+$/', $gradeCode)) {
                        $errors[] = "第{$row}行：年级代码只能包含字母和数字";
                        $row++;
                        continue;
                    }
                    if (!preg_match('/^[a-zA-Z0-9]+$/', $classCode)) {
                        $errors[] = "第{$row}行：班级代码只能包含字母和数字";
                        $row++;
                        continue;
                    }

                    // 检查班级是否已存在于该年级
                    if (isset($existingClassMap[$gradeCode])) {
                        foreach ($existingClassMap[$gradeCode] as $existingClass) {
                            if ($existingClass['name'] === $className) {
                                $errors[] = "第{$row}行：班级名称 '{$className}' 在该年级中已存在";
                                continue 2;
                            }
                            if ($existingClass['code'] === $classCode) {
                                $errors[] = "第{$row}行：班级代码 '{$classCode}' 在该年级中已存在";
                                continue 2;
                            }
                        }
                    }

                    // 检查Excel中是否有重复的班级
                    if (isset($newClasses[$gradeCode])) {
                        foreach ($newClasses[$gradeCode] as $newClass) {
                            if ($newClass['name'] === $className) {
                                $errors[] = "第{$row}行：班级名称 '{$className}' 在Excel中重复";
                                continue 2;
                            }
                            if ($newClass['code'] === $classCode) {
                                $errors[] = "第{$row}行：班级代码 '{$classCode}' 在Excel中重复";
                                continue 2;
                            }
                        }
                    }

                    // 如果年级不存在，添加到新年级列表
                    if (!isset($existingGradeMap[$gradeCode]) && !isset($newGrades[$gradeCode])) {
                        $newGrades[$gradeCode] = [
                            'name' => $gradeName,
                            'code' => $gradeCode
                        ];
                    }

                    // 添加班级信息
                    if (!isset($newClasses[$gradeCode])) {
                        $newClasses[$gradeCode] = [];
                    }
                    $newClasses[$gradeCode][] = [
                        'name' => $className,
                        'code' => $classCode,
                        'row' => $row
                    ];

                    $row++;
                }

                // 如果有错误，返回错误信息
                if (!empty($errors)) {
                    $errorHtml = "<div style='font-size: 14px; line-height: 1.5;'>";
                    $errorHtml .= "<div style='color: #333; margin-bottom: 10px;'>导入失败：</div>";
                    foreach ($errors as $error) {
                        $errorHtml .= "<div style='color: #ff4d4f; margin-bottom: 5px;'>• " . htmlspecialchars($error) . "</div>";
                    }
                    $errorHtml .= "</div>";
                    
                    return $this->json([
                        'success' => false,
                        'error' => $errorHtml,
                        'isHtml' => true
                    ]);
                }

                // 如果没有需要导入的数据
                if (empty($newGrades) && empty($newClasses)) {
                    return $this->json([
                        'success' => true,
                        'message' => '没有新的年级和班级需要导入'
                    ]);
                }

                // 开始事务
                $this->db->query("START TRANSACTION");

                try {
                    // 导入新年级
                    foreach ($newGrades as $gradeCode => $grade) {
                        $this->db->query(
                            "INSERT INTO grades (setting_id, grade_name, grade_code, status, created_at) 
                             VALUES (?, ?, ?, 1, NOW())",
                            [$settingId, $grade['name'], $grade['code']]
                        );
                        $existingGradeMap[$gradeCode] = [
                            'id' => $this->db->lastInsertId(),
                            'name' => $grade['name'],
                            'code' => $grade['code']
                        ];
                    }

                    // 导入班级
                    $totalClasses = 0;
                    $importDetails = [];
                    foreach ($newClasses as $gradeCode => $classes) {
                        $gradeId = $existingGradeMap[$gradeCode]['id'];
                        $gradeName = $existingGradeMap[$gradeCode]['name'];
                        $importDetails[$gradeCode] = [
                            'grade_name' => $gradeName,
                            'classes' => []
                        ];
                        
                        foreach ($classes as $class) {
                            $fullClassCode = $gradeCode . $class['code'];
                            try {
                                $this->db->query(
                                    "INSERT INTO classes (setting_id, grade_id, class_name, class_code, status, created_at) 
                                     VALUES (?, ?, ?, ?, 1, NOW())",
                                    [$settingId, $gradeId, $class['name'], $fullClassCode]
                                );
                                $importDetails[$gradeCode]['classes'][] = [
                                    'name' => $class['name'],
                                    'code' => $fullClassCode
                                ];
                                $totalClasses++;
                            } catch (\Exception $e) {
                                $this->logger->error('导入班级失败', [
                                    'grade_code' => $gradeCode,
                                    'class_name' => $class['name'],
                                    'class_code' => $fullClassCode,
                                    'error' => $e->getMessage()
                                ]);
                                throw new \Exception("导入班级 '{$class['name']}' 失败：" . $e->getMessage());
                            }
                        }
                    }

                    $this->db->query("COMMIT");

                    // 生成导入结果的详细HTML
                    $resultHtml = "<div style='font-size: 14px; line-height: 1.5;'>";
                    $resultHtml .= "<div style='color: #52c41a; margin-bottom: 10px;'>导入成功！</div>";
                    $resultHtml .= "<div style='margin-bottom: 10px;'>共导入：</div>";
                    $resultHtml .= "<div style='margin-left: 15px; margin-bottom: 5px;'>• " . count($newGrades) . " 个新年级</div>";
                    $resultHtml .= "<div style='margin-left: 15px; margin-bottom: 10px;'>• " . $totalClasses . " 个新班级</div>";
                    
                    if (!empty($importDetails)) {
                        $resultHtml .= "<div style='margin-bottom: 10px;'>导入详情：</div>";
                        foreach ($importDetails as $gradeCode => $detail) {
                            $resultHtml .= "<div style='margin-left: 15px; margin-bottom: 5px;'>";
                            $resultHtml .= "• {$detail['grade_name']} ({$gradeCode})：";
                            $resultHtml .= "<div style='margin-left: 20px;'>";
                            foreach ($detail['classes'] as $class) {
                                $resultHtml .= "- {$class['name']} ({$class['code']})<br>";
                            }
                            $resultHtml .= "</div></div>";
                        }
                    }
                    $resultHtml .= "</div>";

                    return $this->json([
                        'success' => true,
                        'message' => $resultHtml,
                        'isHtml' => true,
                        'details' => $importDetails
                    ]);

                } catch (\Exception $e) {
                    $this->db->query("ROLLBACK");
                    $this->logger->error('导入过程中发生错误', [
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                    throw $e;
                }

            } catch (\Exception $e) {
                // 确保清理临时文件
                if (file_exists($tempFile)) {
                    unlink($tempFile);
                }
                throw $e;
            }

        } catch (\Exception $e) {
            $this->logger->error('导入年级班级失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->json([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }
} 