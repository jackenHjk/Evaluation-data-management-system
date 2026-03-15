<?php
/**
 * 文件名: controllers/StudentController.php
 * 功能描述: 学生管理控制器
 * 
 * 该控制器负责:
 * 1. 学生信息的增删改查
 * 2. 学生Excel数据导入
 * 3. 学生姓名批量检查和更新
 * 4. 学生批量删除
 * 5. 更新与学生相关的统计分析数据
 * 
 * API调用路由:
 * - student/list: 获取学生列表
 * - student/update: 更新学生信息
 * - student/check_names: 检查学生姓名
 * - student/import: 导入学生数据
 * - student/update_name: 更新学生姓名
 * - student/delete: 删除学生
 * - student/batch_delete: 批量删除学生
 * 
 * 关联文件:
 * - core/Controller.php: 基础控制器
 * - modules/students.php: 学生管理页面
 * - controllers/ScoreController.php: 成绩控制器
 * - api/controllers/StudentController.php: API学生控制器
 * - students表: 存储学生数据的表
 */

namespace Controllers;

use Core\Controller;

class StudentController extends Controller {
    
    /**
     * 构造函数，负责进行权限验证
     */
    public function __construct() {
        parent::__construct();
        
        // 确保会话已启动
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // 检查用户是否登录
        if (!isset($_SESSION['user_id'])) {
            error_log("未登录用户尝试访问学生管理功能");
            $this->json([
                'success' => false,
                'error' => '登录已过期，请重新登录',
                'code' => 'SESSION_EXPIRED'
            ], 401);
            exit;
        }

        // 获取当前请求的路由
        $route = isset($_GET['route']) ? explode('/', $_GET['route']) : [];
        $action = end($route);

        // 敏感操作（如删除、批量删除）需要额外权限检查
        $sensitiveActions = ['delete', 'batchDelete'];
        
        if (in_array($action, $sensitiveActions)) {
            $userRole = $_SESSION['role'] ?? '';
            // 只有管理员、教导处和班主任（如果有学生管理权限）可以执行敏感操作
            if ($userRole !== 'admin' && $userRole !== 'teaching' && !$this->checkStudentManagePermission()) {
                error_log("用户尝试执行无权限的操作: {$action}, 角色: {$userRole}, 用户ID: {$_SESSION['user_id']}");
                $this->json([
                    'success' => false, 
                    'error' => '无权执行此操作'
                ], 403);
                exit;
            }
        }
    }
    
    /**
     * 检查用户是否有学生管理权限
     */
    private function checkStudentManagePermission() {
        try {
            // 如果是班主任，检查是否有学生管理权限
            if ($_SESSION['role'] === 'headteacher') {
                $stmt = $this->db->query(
                    "SELECT COUNT(*) as count FROM user_permissions 
                    WHERE user_id = ? AND can_edit_students = 1",
                    [$_SESSION['user_id']]
                );
                $result = $stmt->fetch();
                return $result && $result['count'] > 0;
            }
            return false;
        } catch (\Exception $e) {
            error_log("检查学生管理权限失败: " . $e->getMessage());
            return false;
        }
    }
    
    // 获取学生列表
    public function getStudents() {
        $classId = $_GET['class_id'] ?? '';
        
        try {
            $sql = "SELECT s.*, c.class_name, g.grade_name 
                    FROM students s
                    JOIN classes c ON s.class_id = c.id
                    JOIN grades g ON c.grade_id = g.id
                    WHERE s.status = 1
                    AND s.setting_id = (SELECT id FROM settings WHERE status = 1 LIMIT 1)";
            
            $params = [];
            if ($classId) {
                $sql .= " AND s.class_id = ?";
                $params[] = $classId;
            }
            
            $sql .= " ORDER BY s.student_number";
            
            $stmt = $this->db->query($sql, $params);
            $students = $stmt->fetchAll();
            
            $this->json([
                'success' => true,
                'data' => $students
            ]);
        } catch (\Exception $e) {
            $this->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // 更新学生信息
    public function updateStudent() {
        $id = $_POST['id'] ?? '';
        $name = $_POST['name'] ?? '';
        
        if (empty($id) || empty($name)) {
            $this->json([
                'success' => false,
                'error' => '参数不完整'
            ], 400);
            return;
        }

        try {
            // 获取学生当前班级
            $stmt = $this->db->query(
                "SELECT class_id FROM students WHERE id = ? AND status = 1",
                [$id]
            );
            $student = $stmt->fetch();
            
            if (!$student) {
                $this->json([
                    'success' => false,
                    'error' => '学生不存在'
                ], 400);
                return;
            }

            // 检查同班级是否有重名
            $stmt = $this->db->query(
                "SELECT COUNT(*) as count FROM students 
                WHERE class_id = ? AND student_name = ? AND id != ? AND status = 1",
                [$student['class_id'], $name, $id]
            );
            if ($stmt->fetch()['count'] > 0) {
                $this->json([
                    'success' => false,
                    'error' => '该班级中已存在同名学生'
                ], 400);
                return;
            }

            // 更新学生姓名
            $stmt = $this->db->query(
                "UPDATE students SET student_name = ? WHERE id = ?",
                [$name, $id]
            );
            
            // 记录操作日志
            $this->logger->info('更新学生信息', [
                'action_type' => 'edit',
                'action_detail' => sprintf('更新学生 %s 的姓名为 %s', 
                    $student['student_name'] ?? 'unknown',
                    $name
                )
            ]);
            
            $this->json([
                'success' => true,
                'message' => '修改成功'
            ]);
        } catch (\Exception $e) {
            $this->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // 检查姓名是否重复
    public function checkNames() {
        $classId = $_POST['class_id'] ?? '';
        $names = $_POST['names'] ?? '';

        if (empty($classId) || empty($names)) {
            $this->json([
                'success' => false,
                'error' => '参数不完整'
            ], 400);
            return;
        }

        try {
            // 将姓名列表转换为数组
            $nameList = array_filter(array_map('trim', explode("\n", $names)));
            $duplicates = [];

            // 检查每个姓名是否在该班级中已存在
            foreach ($nameList as $name) {
                $stmt = $this->db->query(
                    "SELECT COUNT(*) as count FROM students 
                    WHERE class_id = ? AND student_name = ? AND status = 1",
                    [$classId, $name]
                );
                if ($stmt->fetch()['count'] > 0) {
                    $duplicates[] = $name;
                }
            }

            $this->json([
                'success' => true,
                'duplicates' => $duplicates
            ]);
        } catch (\Exception $e) {
            $this->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * 批量导入学生
     */
    public function import_students() {
        try {
            // 增加请求日志记录，便于调试浏览器兼容性问题
            error_log("开始处理导入学生请求: " . json_encode($_POST));
            
            $classId = $_POST['class_id'] ?? '';
            $names = $_POST['names'] ?? '';
            $shouldAutoSort = isset($_POST['auto_sort']) && ($_POST['auto_sort'] === 'true' || $_POST['auto_sort'] === true);

            // 兼容处理，打印实际收到的数据以便调试
            error_log("导入学生请求参数: class_id=" . $classId . ", auto_sort类型=" . gettype($_POST['auto_sort']) . ", auto_sort值=" . ($_POST['auto_sort'] ?? 'undefined'));

            if (empty($classId) || empty($names)) {
                error_log("导入学生参数不完整: class_id=" . $classId . ", names长度=" . strlen($names));
                return $this->json([
                    'success' => false,
                    'error' => '参数不完整'
                ], 400);
            }

            // 获取当前项目ID
            $stmt = $this->db->query("SELECT id FROM settings WHERE status = 1 ORDER BY id DESC LIMIT 1");
            $setting = $stmt->fetch();
            if (!$setting) {
                error_log("导入学生失败: 未找到可用的项目");
                return $this->json([
                    'success' => false,
                    'error' => '未找到可用的项目'
                ], 400);
            }

            // 获取班级信息
            $stmt = $this->db->query(
                "SELECT c.*, g.grade_name 
                FROM classes c 
                JOIN grades g ON c.grade_id = g.id 
                WHERE c.id = ?",
                [$classId]
            );
            $class = $stmt->fetch();

            if (!$class) {
                error_log("导入学生失败: 班级不存在 (class_id=$classId)");
                return $this->json([
                    'success' => false,
                    'error' => '班级不存在'
                ], 400);
            }

            // 处理学生姓名列表
            $names = array_filter(array_map('trim', explode("\n", $names)));
            $studentCount = count($names);
            
            error_log("导入学生: 共 $studentCount 名学生需要导入");

            if ($shouldAutoSort) {
                error_log("导入学生: 开始按姓名排序");
                \Core\ChineseNameSorter::sort($names);
            }

            $success = 0;
            $studentNumbers = [];
            $errors = [];
            
            $this->db->query("START TRANSACTION");

            try {
                // 获取当前班级的最大编号序号（只在当前项目ID范围内查找）
                $prefix = $class['class_code'];
                $stmt = $this->db->query(
                    "SELECT MAX(CAST(SUBSTRING(student_number, -2) AS UNSIGNED)) as max_num 
                    FROM students 
                    WHERE student_number LIKE ? 
                    AND student_number != ''
                    AND setting_id = ?
                    AND status = 1",
                    [$prefix . '%', $setting['id']]
                );
                $result = $stmt->fetch();
                $maxNum = (int)($result['max_num'] ?? 0);
                
                error_log("导入学生: 当前班级最大编号为 $maxNum");

                foreach ($names as $i => $name) {
                    if (empty($name)) {
                        error_log("导入学生: 跳过空姓名");
                        continue;
                    }

                    // 检查是否存在同名学生
                    $stmt = $this->db->query(
                        "SELECT COUNT(*) as count FROM students 
                        WHERE class_id = ? AND student_name = ? AND status = 1",
                        [$classId, $name]
                    );
                    if ($stmt->fetch()['count'] > 0) {
                        error_log("导入学生: 学生 $name 已存在，跳过");
                        $errors[] = "学生 '$name' 在该班级中已存在";
                        continue;
                    }

                    $newNum = $maxNum + $i + 1;
                    $studentNumber = $prefix . str_pad($newNum, 2, '0', STR_PAD_LEFT);
                    $studentNumbers[] = $studentNumber;

                    // 插入学生记录
                    try {
                        $stmt = $this->db->query(
                            "INSERT INTO students (class_id, student_name, student_number, setting_id) VALUES (?, ?, ?, ?)",
                            [$classId, $name, $studentNumber, $setting['id']]
                        );
                        $success++;
                        error_log("导入学生: 成功插入学生 $name, 学号: $studentNumber");
                    } catch (\Exception $insertEx) {
                        error_log("导入学生: 插入学生失败 " . $insertEx->getMessage());
                        $errors[] = "插入学生 '$name' 失败: " . $insertEx->getMessage();
                    }
                }

                // 如果有成功导入的学生，提交事务
                if ($success > 0) {
                    $this->db->commit();
                    error_log("导入学生: 提交事务成功，共导入 $success 名学生");
                    
                    // 记录操作日志
                    $logDetail = sprintf(
                        "批量导入学生\n" .
                        "导入班级：%s %s\n" .
                        "导入方式：%s\n" .
                        "<span style='color: #1890ff'>导入结果：成功导入 %d 名学生\n" .
                        "学号范围：%s - %s</span>",
                        $class['grade_name'],
                        $class['class_name'],
                        $shouldAutoSort ? '按姓名排序' : '保持原顺序',
                        $success,
                        $studentNumbers[0] ?? '',
                        end($studentNumbers) ?? ''
                    );

                    if (!empty($errors)) {
                        $logDetail .= "\n<span style='color: #ff4d4f'>导入失败：" . count($errors) . " 名学生</span>";
                    }

                    $this->logger->info($logDetail, [
                        'action_type' => 'import',
                        'action_detail' => $logDetail
                    ]);

                    $message = "成功导入 {$success} 名学生";
                    if (!empty($errors)) {
                        $message .= "，" . count($errors) . " 名学生导入失败";
                    }

                    return $this->json([
                        'success' => true,
                        'message' => $message,
                        'data' => [
                            'success_count' => $success,
                            'error_count' => count($errors),
                            'errors' => $errors
                        ]
                    ]);
                } else {
                    $this->db->rollBack();
                    error_log("导入学生: 没有成功导入的学生，回滚事务");
                    
                    if (!empty($errors)) {
                        return $this->json([
                            'success' => false,
                            'error' => '导入失败: ' . implode(', ', $errors)
                        ]);
                    } else {
                        return $this->json([
                            'success' => false,
                            'error' => '未能导入任何学生'
                        ]);
                    }
                }
            } catch (\Exception $e) {
                $this->db->rollBack();
                error_log("导入学生: 事务中出现异常，回滚 - " . $e->getMessage());
                throw $e;
            }
        } catch (\Exception $e) {
            error_log("学生导入失败: " . $e->getMessage() . "\n" . $e->getTraceAsString());
            return $this->json([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * 处理Excel文件导入
     */
    public function importFile() {
        // 定义调试模式常量，用于控制错误信息详细程度
        define('DEBUG_MODE', false); // 生产环境设为false
        
        try {
            error_log("开始处理文件导入请求 - UserAgent: " . $_SERVER['HTTP_USER_AGENT']);
            require_once __DIR__ . '/../vendor/autoload.php';
            
            // 检查是否有文件上传
            if (!isset($_FILES['file'])) {
                error_log("未检测到上传文件");
                throw new \Exception('请选择要导入的文件');
            }
            
            if ($_FILES['file']['error'] !== UPLOAD_ERR_OK) {
                error_log("文件上传错误，错误代码：" . $_FILES['file']['error']);
                throw new \Exception('文件上传失败，错误代码：' . $_FILES['file']['error']);
            }

            // 获取当前项目ID
            $stmt = $this->db->query("SELECT id FROM settings WHERE status = 1 ORDER BY id DESC LIMIT 1");
            $setting = $stmt->fetch();
            if (!$setting) {
                error_log("未找到活动的项目设置");
                return $this->json(['error' => '未找到可用的项目'], 400);
            }
            $settingId = $setting['id'];
            error_log("当前项目ID: " . $settingId);

            $file = $_FILES['file'];
            error_log("上传文件信息：" . json_encode([
                'name' => $file['name'],
                'type' => $file['type'],
                'size' => $file['size'],
                'tmp_name' => $file['tmp_name'],
                'error' => $file['error']
            ]));
            
            // 检查文件类型和扩展名
            $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (!in_array($fileExtension, ['xlsx', 'xls'])) {
                error_log("文件扩展名不正确：" . $fileExtension);
                throw new \Exception('请上传Excel文件（.xls或.xlsx格式）');
            }

            // 类型检查宽松处理，微信浏览器可能传递空类型或不同类型
            $allowedTypes = [
                'application/vnd.ms-excel',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'application/octet-stream',
                'application/x-excel',
                'application/excel',
                ''
            ];
            
            if (!in_array($file['type'], $allowedTypes) && !empty($file['type'])) {
                error_log("文件MIME类型不正确（但仍继续处理）：" . $file['type']);
                // 不再直接抛出异常，宽松处理，根据扩展名继续尝试处理
            }

            // 创建临时目录并确保它存在且可写
            $tempDir = __DIR__ . '/../temp';
            if (!file_exists($tempDir)) {
                if (!mkdir($tempDir, 0777, true)) {
                    error_log("无法创建临时目录：" . $tempDir);
                    throw new \Exception('服务器临时目录创建失败');
                }
                error_log("已创建临时目录：" . $tempDir);
            } else if (!is_writable($tempDir)) {
                error_log("临时目录不可写：" . $tempDir);
                throw new \Exception('服务器临时目录不可写');
            }
            
            $tempFile = $tempDir . '/excel_' . uniqid() . '.' . $fileExtension;
            error_log("临时文件路径：" . $tempFile);
            
            // 移动上传文件到临时目录
            if (!move_uploaded_file($file['tmp_name'], $tempFile)) {
                error_log("移动上传文件失败");
                throw new \Exception('无法处理上传文件');
            }
            error_log("文件已成功移动到临时目录");
            
            // 检查文件大小
            $fileSize = filesize($tempFile);
            if ($fileSize <= 0) {
                error_log("文件大小异常：" . $fileSize . " 字节");
                unlink($tempFile);
                throw new \Exception('上传文件大小异常，请重新上传');
            }
            error_log("临时文件大小：" . $fileSize . " 字节");

            try {
                // 使用 PhpSpreadsheet 读取文件
                error_log("开始读取Excel文件");
                try {
                    $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($tempFile);
                    if (!$reader) {
                        throw new \Exception("无法创建适用于此文件的读取器");
                    }
                } catch (\Exception $readerEx) {
                    error_log("创建Excel读取器失败：" . $readerEx->getMessage());
                    throw new \Exception("无法读取Excel文件格式，请确保文件是有效的Excel文件");
                }
                
                $reader->setReadDataOnly(true);
                
                try {
                    $spreadsheet = $reader->load($tempFile);
                    if (!$spreadsheet) {
                        throw new \Exception("无法加载电子表格内容");
                    }
                } catch (\Exception $loadEx) {
                    error_log("加载Excel文件失败：" . $loadEx->getMessage());
                    throw new \Exception("无法读取Excel内容，文件可能已损坏");
                }
                
                $worksheet = $spreadsheet->getActiveSheet();
                error_log("Excel文件加载成功");
                
                // 获取数据范围
                $highestRow = $worksheet->getHighestRow();
                error_log("Excel文件总行数：" . $highestRow);
                
                if ($highestRow <= 1) {
                    error_log("Excel文件行数不足：" . $highestRow);
                    throw new \Exception("Excel文件没有数据行或只有标题行");
                }
                
                // 解析数据（从第2行开始，跳过表头）
                $data = [];
                for ($row = 2; $row <= $highestRow; $row++) {
                    $classCode = trim($worksheet->getCell('A' . $row)->getValue());
                    $studentName = trim($worksheet->getCell('B' . $row)->getValue());
                    
                    error_log("处理第 {$row} 行数据：班级代码 = {$classCode}, 学生姓名 = {$studentName}");
                    
                    // 验证数据
                    if (!empty($classCode) && !empty($studentName) && 
                        mb_strlen($classCode) <= 10 && 
                        mb_strlen($studentName) <= 50 && !preg_match('/[<>]/', $studentName)) {
                        
                        $data[] = [
                            'class_code' => $classCode,
                            'student_name' => $studentName
                        ];
                        error_log("第 {$row} 行数据有效，已添加到导入列表");
                    } else {
                        error_log("第 {$row} 行数据无效：" . json_encode([
                            'class_code' => $classCode,
                            'student_name' => $studentName
                        ]));
                    }
                }
                
                // 清理内存
                $spreadsheet->disconnectWorksheets();
                unset($spreadsheet);
                
                // 删除临时文件
                unlink($tempFile);
                error_log("临时文件已删除");

                if (empty($data)) {
                    error_log("未找到有效的学生数据");
                    throw new \Exception('没有找到有效的学生数据，请确保文件格式正确且包含有效数据');
                }

                // 开始导入数据
                $successCount = 0;
                $errors = [];
                
                // 开始事务
                error_log("开始数据库事务");
                $this->db->query("START TRANSACTION");
                
                try {
                    // 按班级分组数据
                    $studentsByClass = [];
                    foreach ($data as $item) {
                        $studentsByClass[$item['class_code']][] = $item['student_name'];
                    }
                    error_log("学生数据按班级分组完成：" . json_encode($studentsByClass));

                    // 按班级处理数据
                    foreach ($studentsByClass as $classCode => $studentNames) {
                        error_log("处理班级 {$classCode} 的数据");
                        // 查找班级ID和信息
                        $stmt = $this->db->query(
                            "SELECT c.* 
                             FROM classes c 
                             JOIN grades g ON c.grade_id = g.id 
                             WHERE c.class_code = ? AND c.status = 1",
                            [$classCode]
                        );
                        $class = $stmt->fetch(\PDO::FETCH_ASSOC);

                        if (!$class) {
                            error_log("班级代码 {$classCode} 不存在");
                            foreach ($studentNames as $studentName) {
                                $errors[] = "班级代码 {$classCode} 不存在，学生 {$studentName} 导入失败";
                            }
                            continue;
                        }
                        error_log("找到班级信息：" . json_encode($class));

                        // 获取当前班级最大编号
                        $stmt = $this->db->query(
                            "SELECT MAX(CAST(SUBSTRING(student_number, -2) AS UNSIGNED)) as max_num 
                             FROM students 
                             WHERE student_number LIKE ? 
                             AND student_number != ''
                             AND setting_id = ?",
                            [$classCode . '%', $settingId]
                        );
                        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
                        $maxNum = (int)($result['max_num'] ?? 0);
                        error_log("当前班级最大编号：" . $maxNum);

                        // 处理该班级的所有学生
                        foreach ($studentNames as $index => $studentName) {
                            error_log("处理学生：{$studentName}");
                            // 检查学生姓名是否已存在于该班级
                            $stmt = $this->db->query(
                                "SELECT COUNT(*) as count FROM students 
                                 WHERE class_id = ? AND student_name = ? AND setting_id = ? AND status = 1",
                                [$class['id'], $studentName, $settingId]
                            );
                            $exists = $stmt->fetch(\PDO::FETCH_ASSOC);

                            if ($exists['count'] > 0) {
                                error_log("学生 {$studentName} 在班级中已存在");
                                $errors[] = "学生 {$studentName} 在班级 {$class['class_name']} 中已存在";
                                continue;
                            }

                            $newNum = $maxNum + $index + 1;
                            if ($newNum > 99) {
                                error_log("班级 {$class['class_name']} 学生数量超过限制");
                                $errors[] = "班级 {$class['class_name']} 学生数量超过限制（最多99人）";
                                continue;
                            }

                            // 生成编号
                            $studentNumber = $classCode . str_pad($newNum, 2, '0', STR_PAD_LEFT);
                            error_log("生成学号：{$studentNumber}");

                            try {
                                // 插入学生记录
                                $stmt = $this->db->query(
                                    "INSERT INTO students (class_id, student_name, student_number, setting_id, status) 
                                     VALUES (?, ?, ?, ?, 1)",
                                    [$class['id'], $studentName, $studentNumber, $settingId]
                                );
                                error_log("成功插入学生记录：{$studentName}, 学号：{$studentNumber}");
                                $successCount++;
                            } catch (\Exception $e) {
                                error_log("插入学生记录失败：" . $e->getMessage());
                                $errors[] = "插入学生 {$studentName} 失败：" . $e->getMessage();
                            }
                        }
                    }

                    if ($successCount > 0) {
                        error_log("提交事务：成功导入 {$successCount} 名学生，失败 " . count($errors) . " 条记录");
                        $this->db->query("COMMIT");
                        
                        // 记录操作日志
                        $logDetail = sprintf(
                            "文件批量导入学生\n" .
                            "<span style='color: #1890ff'>导入结果：成功导入 %d 名学生%s</span>",
                            $successCount,
                            !empty($errors) ? sprintf("\n失败：%d 条记录", count($errors)) : ""
                        );

                        $this->logger->info($logDetail, [
                            'action_type' => 'import',
                            'action_detail' => $logDetail
                        ]);
                    } else {
                        error_log("回滚事务：没有任何数据被导入");
                        $this->db->query("ROLLBACK");
                        throw new \Exception('没有任何数据被导入');
                    }

                } catch (\Exception $e) {
                    error_log("导入过程发生错误，执行回滚：" . $e->getMessage());
                    $this->db->query("ROLLBACK");
                    throw $e;
                }

                // 返回导入结果
                $message = "<div style='text-align: left;'>";
                $message .= "<div style='font-size: 16px; margin-bottom: 10px;'>成功导入 {$successCount} 名学生</div>";
                
                if (!empty($errors)) {
                    $message .= "<div style='color: #666; font-size: 14px;'>";
                    $message .= "但有以下错误：";
                    $message .= "<div style='margin-top: 8px; margin-left: 10px;'>";
                    foreach ($errors as $error) {
                        $message .= "<div style='margin-bottom: 6px;'><span style='color: #ff4d4f; margin-right: 5px;'>•</span>" . 
                                   htmlspecialchars($error) . "</div>";
                    }
                    $message .= "</div></div>";
                }
                
                $message .= "</div>";

                return $this->json([
                    'success' => true,
                    'message' => $message,
                    'isHtml' => true
                ]);

            } catch (\Exception $e) {
                // 确保清理临时文件
                if (file_exists($tempFile)) {
                    unlink($tempFile);
                }
                throw $e;
            }

        } catch (\Exception $e) {
            error_log("文件导入失败: " . $e->getMessage() . "\n" . $e->getTraceAsString());
            return $this->json([
                'success' => false,
                'error' => $e->getMessage(),
                'trace' => DEBUG_MODE ? $e->getTraceAsString() : null
            ]);
        }
    }

    // 删除单个学生
    public function delete() {
        try {
            $id = $_POST['id'] ?? '';
            if (empty($id)) {
                throw new \Exception('参数不完整');
            }

            // 获取学生信息
            $stmt = $this->db->query(
                "SELECT s.*, c.class_name, g.grade_name 
                FROM students s 
                JOIN classes c ON s.class_id = c.id 
                JOIN grades g ON c.grade_id = g.id 
                WHERE s.id = ?",
                [$id]
            );
            $student = $stmt->fetch();
            
            if (!$student) {
                throw new \Exception('学生不存在');
            }

            // 获取当前项目ID
            $stmt = $this->db->query("SELECT id FROM settings WHERE status = 1 ORDER BY id DESC LIMIT 1");
            $setting = $stmt->fetch();
            if (!$setting) {
                throw new \Exception('未找到可用的项目');
            }

            // 检查是否有成绩记录
            $stmt = $this->db->query(
                "SELECT COUNT(*) as count 
                FROM scores 
                WHERE student_id = ? AND setting_id = ?",
                [$id, $setting['id']]
            );
            $result = $stmt->fetch();

            if ($result['count'] > 0) {
                $errorMsg = '该学生已有成绩记录，无法删除。<br>如需删除，请使用批量删除功能。';
                return $this->json([
                    'success' => false,
                    'error' => $errorMsg,
                    'isHtml' => true
                ], 400);
            }

            $this->db->query("START TRANSACTION");

            try {
                // 删除学生记录（硬删除）
                $stmt = $this->db->query(
                    "DELETE FROM students WHERE id = ?",
                    [$id]
                );

                $this->db->commit();
                
                // 记录操作日志
                $logDetail = sprintf(
                    "删除学生：%s\n" .
                    "学号：%s\n" .
                    "所在班级：%s %s",
                    $student['student_name'],
                    $student['student_number'],
                    $student['grade_name'],
                    $student['class_name']
                );

                $this->logger->info($logDetail, [
                    'action_type' => 'delete',
                    'action_detail' => $logDetail
                ]);

                $this->json([
                    'success' => true,
                    'message' => '删除成功'
                ]);
            } catch (\Exception $e) {
                $this->db->rollBack();
                throw $e;
            }
        } catch (\Exception $e) {
            error_log("Error in delete student: " . $e->getMessage());
            
            // 检查是否是我们自己设置的带HTML的错误消息
            if (strpos($e->getMessage(), '<br>') !== false) {
                $this->json([
                    'success' => false,
                    'error' => $e->getMessage(),
                    'isHtml' => true
                ], 400);
            } else {
                $this->json([
                    'success' => false,
                    'error' => $e->getMessage()
                ], 500);
            }
        }
    }

    // 批量删除学生
    public function batchDelete() {
        try {
            // 记录请求信息
            error_log("开始处理批量删除学生请求: " . json_encode([
                'user_id' => $_SESSION['user_id'] ?? 'unknown',
                'role' => $_SESSION['role'] ?? 'unknown',
                'request_data' => $_POST
            ]));
            
            $gradeId = $_POST['grade_id'] ?? '';
            $classId = $_POST['class_id'] ?? '';
            $scope = $_POST['scope'] ?? '';

            if (empty($gradeId) || empty($scope)) {
                throw new \Exception('参数不完整');
            }

            if ($scope === 'class' && empty($classId)) {
                throw new \Exception('未指定班级');
            }

            // 获取年级和班级信息
            $gradeStmt = $this->db->query(
                "SELECT grade_name FROM grades WHERE id = ?",
                [$gradeId]
            );
            $grade = $gradeStmt->fetch();
            if (!$grade) {
                throw new \Exception('年级不存在');
            }

            $className = '';
            if ($scope === 'class') {
                $classStmt = $this->db->query(
                    "SELECT class_name FROM classes WHERE id = ?",
                    [$classId]
                );
                $class = $classStmt->fetch();
                if (!$class) {
                    throw new \Exception('班级不存在');
                }
                $className = $class['class_name'];
            }

            // 获取当前项目ID
            $stmt = $this->db->query("SELECT id FROM settings WHERE status = 1 ORDER BY id DESC LIMIT 1");
            $setting = $stmt->fetch();
            if (!$setting) {
                throw new \Exception('未找到可用的项目');
            }
            $settingId = $setting['id'];
            
            error_log("批量删除学生 - 已验证参数: grade_id={$gradeId}, class_id={$classId}, scope={$scope}, setting_id={$settingId}");

            $this->db->query("START TRANSACTION");

            try {
                // 获取要删除的学生数量
                if ($scope === 'all_classes') {
                    $stmt = $this->db->query(
                        "SELECT COUNT(*) as count 
                        FROM students s 
                        JOIN classes c ON s.class_id = c.id 
                        WHERE c.grade_id = ? AND s.setting_id = ?",
                        [$gradeId, $settingId]
                    );
                } else {
                    $stmt = $this->db->query(
                        "SELECT COUNT(*) as count 
                        FROM students 
                        WHERE class_id = ? AND setting_id = ?",
                        [$classId, $settingId]
                    );
                }
                $result = $stmt->fetch();
                $studentCount = $result['count'];
                
                error_log("批量删除学生 - 需要删除的学生数量: {$studentCount}");

                // 删除成绩记录
                if ($scope === 'all_classes') {
                    $stmt = $this->db->query(
                        "DELETE sc FROM scores sc 
                        JOIN students s ON sc.student_id = s.id 
                        JOIN classes c ON s.class_id = c.id 
                        WHERE c.grade_id = ? AND sc.setting_id = ?",
                        [$gradeId, $settingId]
                    );
                } else {
                    $stmt = $this->db->query(
                        "DELETE sc FROM scores sc 
                        JOIN students s ON sc.student_id = s.id 
                        WHERE s.class_id = ? AND sc.setting_id = ?",
                        [$classId, $settingId]
                    );
                }
                error_log("批量删除学生 - 删除成绩记录完成");

                // 删除统计分析数据
                if ($scope === 'all_classes') {
                    $stmt = $this->db->query(
                        "DELETE sa FROM score_analytics sa 
                        JOIN classes c ON sa.class_id = c.id 
                        WHERE c.grade_id = ? AND sa.setting_id = ?",
                        [$gradeId, $settingId]
                    );
                } else {
                    $stmt = $this->db->query(
                        "DELETE FROM score_analytics 
                        WHERE class_id = ? AND setting_id = ?",
                        [$classId, $settingId]
                    );
                }
                error_log("批量删除学生 - 删除统计分析数据完成");

                // 执行删除学生操作（硬删除）
                if ($scope === 'all_classes') {
                    $stmt = $this->db->query(
                        "DELETE s FROM students s 
                        JOIN classes c ON s.class_id = c.id 
                        WHERE c.grade_id = ? AND s.setting_id = ?",
                        [$gradeId, $settingId]
                    );
                } else {
                    $stmt = $this->db->query(
                        "DELETE FROM students 
                        WHERE class_id = ? AND setting_id = ?",
                        [$classId, $settingId]
                    );
                }
                error_log("批量删除学生 - 删除学生数据完成");

                $this->db->commit();
                error_log("批量删除学生 - 事务提交成功");
                
                // 记录操作日志
                $logDetail = sprintf(
                    "批量删除学生\n" .
                    "删除范围：%s\n" .
                    "年级：%s%s\n" .
                    "<span style='color: #1890ff'>删除数量：共删除 %d 名学生</span>",
                    $scope === 'class' ? '指定班级' : '整个年级',
                    $grade['grade_name'],
                    $scope === 'class' ? sprintf("，班级：%s", $className) : "（所有班级）",
                    $studentCount
                );

                $this->logger->info($logDetail, [
                    'action_type' => 'delete',
                    'action_detail' => $logDetail,
                    'user_id' => $_SESSION['user_id'],
                    'role' => $_SESSION['role']
                ]);

                $this->json([
                    'success' => true,
                    'message' => '删除成功'
                ]);
            } catch (\Exception $e) {
                $this->db->rollBack();
                error_log("批量删除学生 - 执行SQL出错: " . $e->getMessage());
                error_log("批量删除学生 - 堆栈跟踪: " . $e->getTraceAsString());
                throw $e;
            }
        } catch (\Exception $e) {
            error_log("批量删除学生错误: " . $e->getMessage());
            error_log("批量删除学生错误 - 堆栈跟踪: " . $e->getTraceAsString());
            $this->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // 更新统计分析数据
    private function updateScoreAnalytics($classId, $subjectId) {
        try {
            error_log("开始更新统计分析 - 班级ID: " . $classId . ", 科目ID: " . $subjectId);
            
            // 获取班级信息和当前项目ID
            $stmt = $this->db->query(
                "SELECT c.grade_id, s.id as setting_id 
                FROM classes c 
                CROSS JOIN settings s 
                WHERE c.id = ? AND s.status = 1
                LIMIT 1",
                [$classId]
            );
            $info = $stmt->fetch();

            if (!$info) {
                throw new \Exception('班级不存在或未找到活动的项目');
            }

            error_log("获取到项目信息 - setting_id: {$info['setting_id']}, grade_id: {$info['grade_id']}");

            // 实例化 ClassAnalyticsController
            $analyticsController = new \Controllers\ClassAnalyticsController($this->db, $this->logger);
            
            // 构造参数
            $_POST['grade_id'] = $info['grade_id'];
            $_POST['class_id'] = $classId;
            $_POST['subject_id'] = $subjectId;
            $_POST['setting_id'] = $info['setting_id'];

            // 调用 generateAnalytics 方法生成统计数据
            $result = $analyticsController->generateAnalytics();
            
            error_log("统计分析更新完成: " . json_encode($result));
            
            if (!$result['success']) {
                throw new \Exception($result['error'] ?? '更新统计分析失败');
            }

        } catch (\Exception $e) {
            error_log("统计分析更新失败: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            throw $e;
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
                throw new \Exception('缺少学生ID');
            }
            if (!$student_name) {
                throw new \Exception('缺少学生姓名');
            }

            // 获取学生当前信息
            $stmt = $this->db->query(
                "SELECT s.*, c.class_name, g.grade_name 
                FROM students s
                JOIN classes c ON s.class_id = c.id
                JOIN grades g ON c.grade_id = g.id
                WHERE s.id = ?",
                [$id]
            );
            $student = $stmt->fetch();
            
            if (!$student) {
                throw new \Exception('学生不存在');
            }

            // 检查同班级是否有重名
            $stmt = $this->db->query(
                "SELECT COUNT(*) as count FROM students 
                WHERE class_id = ? AND student_name = ? AND id != ?",
                [$student['class_id'], $student_name, $id]
            );
            if ($stmt->fetch()['count'] > 0) {
                throw new \Exception('该班级中已存在同名学生');
            }

            // 更新学生姓名
            $stmt = $this->db->query(
                "UPDATE students SET student_name = ? WHERE id = ?",
                [$student_name, $id]
            );
            
            // 记录操作日志
            $logDetail = sprintf(
                "编辑学生：%s（学号：%s）\n" .
                "所在班级：%s %s\n",
                $student['student_name'],
                $student['student_number'],
                $student['grade_name'],
                $student['class_name']
            );

            if ($student_name !== $student['student_name']) {
                $logDetail .= "<span style='color: #1890ff'>修改内容：姓名 " . $student['student_name'] . " -> " . $student_name . "</span>";
            } else {
                $logDetail .= "<span style='color: #52c41a'>无修改内容</span>";
            }

            $this->logger->info($logDetail, [
                'action_type' => 'edit',
                'action_detail' => $logDetail
            ]);

            return $this->json([
                'success' => true,
                'message' => '更新成功'
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * 重新排序学生
     * 根据拖拽排序后的结果重新分配学生的学号
     */
    public function reorder() {
        try {
            $classId = $_POST['class_id'] ?? '';
            $gradeId = $_POST['grade_id'] ?? '';
            $studentsJson = $_POST['students'] ?? '';
            
            if (empty($classId) || empty($studentsJson)) {
                return $this->json([
                    'success' => false,
                    'error' => '参数不完整'
                ], 400);
            }
            
            $students = json_decode($studentsJson, true);
            if (!$students || !is_array($students)) {
                return $this->json([
                    'success' => false,
                    'error' => '学生数据格式错误'
                ], 400);
            }
            
            // 获取当前项目ID
            $stmt = $this->db->query("SELECT id FROM settings WHERE status = 1 ORDER BY id DESC LIMIT 1");
            $setting = $stmt->fetch();
            if (!$setting) {
                return $this->json([
                    'success' => false, 
                    'error' => '未找到可用的项目'
                ], 400);
            }
            $settingId = $setting['id'];
            
            // 获取班级信息
            $stmt = $this->db->query(
                "SELECT c.*, g.grade_name, g.grade_code 
                FROM classes c 
                JOIN grades g ON c.grade_id = g.id 
                WHERE c.id = ?",
                [$classId]
            );
            $class = $stmt->fetch();
            
            if (!$class) {
                return $this->json([
                    'success' => false,
                    'error' => '班级不存在'
                ], 400);
            }
            
            // 开始事务，确保操作的原子性
            $this->db->query("START TRANSACTION");
            
            try {
                $updatedCount = 0;
                $prefix = $class['class_code'];
                $updateLog = [];
                
                // 遍历学生数据，根据新的顺序更新学号
                foreach ($students as $student) {
                    $id = $student['id'] ?? 0;
                    $currentNumber = $student['current_number'] ?? '';
                    $newIndex = $student['new_index'] ?? 0;
                    
                    if (!$id || !$newIndex) continue;
                    
                    // 生成新的学号
                    $newNumber = $prefix . str_pad($newIndex, 2, '0', STR_PAD_LEFT);
                    
                    // 如果学号没有变化，跳过更新
                    if ($currentNumber === $newNumber) continue;
                    
                    // 获取学生信息（用于日志记录）
                    $stmt = $this->db->query(
                        "SELECT student_name, student_number FROM students WHERE id = ?",
                        [$id]
                    );
                    $studentInfo = $stmt->fetch();
                    
                    if ($studentInfo) {
                        $updateLog[] = sprintf(
                            "%s: %s → %s",
                            $studentInfo['student_name'],
                            $studentInfo['student_number'],
                            $newNumber
                        );
                    }
                    
                    // 更新学生学号
                    $stmt = $this->db->query(
                        "UPDATE students 
                        SET student_number = ? 
                        WHERE id = ? 
                        AND setting_id = ? 
                        AND class_id = ?",
                        [$newNumber, $id, $settingId, $classId]
                    );
                    
                    if ($stmt->rowCount() > 0) {
                        $updatedCount++;
                    }
                }
                
                // 提交事务
                $this->db->query("COMMIT");
                
                // 记录操作日志
                $logDetail = sprintf(
                    "重新排序学生\n" .
                    "班级：%s %s\n" .
                    "<span style='color: #1890ff'>修改了%d个学生的学号</span>",
                    $class['grade_name'] ?? '',
                    $class['class_name'] ?? '',
                    $updatedCount
                );
                
                if (!empty($updateLog)) {
                    $logDetail .= "\n<span style='color: #52c41a'>学号变更明细：</span>\n" . implode("\n", $updateLog);
                }

                $this->logger->info($logDetail, [
                    'action_type' => 'reorder',
                    'action_detail' => $logDetail
                ]);
                
                return $this->json([
                    'success' => true,
                    'message' => sprintf('排序完成，共更新了%d个学生的学号', $updatedCount)
                ]);
                
            } catch (\Exception $e) {
                // 回滚事务
                $this->db->query("ROLLBACK");
                throw $e;
            }
            
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => '排序失败：' . $e->getMessage()
            ], 500);
        }
    }
} 