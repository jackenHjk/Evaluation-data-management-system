<?php
/**
 * 文件名: api/download/ChineseMathExport.php
 * 功能描述: 语文数学成绩导出类
 * 
 * 该类负责:
 * 1. 生成语文数学双科成绩对比Excel文件
 * 2. 按班级分工作表展示学生语文数学成绩
 * 3. 计算语文数学总分和平均分
 * 4. 支持按总分或学号排序
 * 5. 分析语文数学双科成绩关系
 * 
 * API调用说明:
 * - 不直接通过API调用，由controllers/DownloadController.php调用
 * - 通过download/chinese_math路由访问
 * 
 * 关联文件:
 * - core/ExcelExport.php: 基础Excel导出类
 * - core/Database.php: 数据库操作类
 * - controllers/DownloadController.php: 下载控制器
 * - api/download/chinese_math.php: 语文数学导出API入口
 * - temp/downloads/: 临时文件目录
 */

namespace Api\Download;

require_once __DIR__ . '/../../core/ExcelExport.php';
require_once __DIR__ . '/../../core/Database.php';

use \PDO;
use \Exception;

class ChineseMathExport extends \ExcelExport {
    private $db;
    private $projectId;
    private $gradeId;
    private $downloadType;
    private $sortBy;
    private $projectInfo;
    private $gradeInfo;
    private $chineseSubject;
    private $mathSubject;

    public function __construct($params) {
        parent::__construct();
        $this->db = new \Database();
        
        // 初始化参数
        $this->projectId = $params['setting_id'];
        $this->gradeId = $params['grade_id'];
        $this->downloadType = $params['download_type'];
        $this->sortBy = $params['sort_by'];

        // 获取基础信息
        $this->loadBaseInfo();
    }

    /**
     * 加载基础信息
     */
    private function loadBaseInfo() {
        try {
            // 获取项目信息
            $stmt = $this->db->prepare("SELECT * FROM settings WHERE id = ?");
            $stmt->execute([$this->projectId]);
            $this->projectInfo = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$this->projectInfo) {
                throw new Exception('未找到项目信息');
            }

            // 获取年级信息
            $stmt = $this->db->prepare("SELECT * FROM grades WHERE id = ?");
            $stmt->execute([$this->gradeId]);
            $this->gradeInfo = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$this->gradeInfo) {
                throw new Exception('未找到年级信息');
            }

            // 获取与年级关联的语文和数学科目信息
            $subjectsSql = "SELECT s.id, s.subject_name, s.subject_code, 
                           s.full_score, s.excellent_score, s.good_score, s.pass_score 
                           FROM subjects s
                           JOIN subject_grades sg ON s.id = sg.subject_id
                           WHERE sg.grade_id = ?
                           AND s.subject_name IN ('语文', '数学') 
                           AND s.setting_id = ? 
                           AND s.status = 1";
            $stmt = $this->db->prepare($subjectsSql);
            $stmt->execute([$this->gradeId, $this->projectId]);
            $subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (empty($subjects)) {
                throw new Exception('未找到与该年级关联的语文或数学科目信息');
            }

            // 获取语文和数学的科目ID
            foreach ($subjects as $subject) {
                if ($subject['subject_name'] === '语文') {
                    $this->chineseSubject = $subject;
                } else if ($subject['subject_name'] === '数学') {
                    $this->mathSubject = $subject;
                }
            }
            
            if (!$this->chineseSubject) {
                throw new Exception('未找到与该年级关联的语文科目');
            }
            
            if (!$this->mathSubject) {
                throw new Exception('未找到与该年级关联的数学科目');
            }
        } catch (Exception $e) {
            throw new Exception('加载基础信息失败: ' . $e->getMessage());
        }
    }

    /**
     * 获取年级下所有班级的学生成绩数据
     * 
     * @param string $classId 班级ID
     * @return array 学生成绩数据
     */
    private function getClassScores($classId) {
        try {
            // 构建查询SQL - 完全参照 ClassAnalyticsController.getStudentChineseMathScores 方法
            $sql = "SELECT 
                s.id as student_id,
                s.student_number,
                s.student_name,
                c.class_name,
                chinese.total_score as chinese_score,
                chinese.base_score as chinese_base_score,
                chinese.extra_score as chinese_extra_score,
                chinese.score_level as chinese_level,
                chinese.is_absent as chinese_absent,
                math.total_score as math_score,
                math.score_level as math_level,
                math.is_absent as math_absent
            FROM students s
            JOIN classes c ON s.class_id = c.id
            LEFT JOIN scores chinese ON s.id = chinese.student_id 
                AND chinese.subject_id = ?
                AND chinese.setting_id = ?
            LEFT JOIN scores math ON s.id = math.student_id 
                AND math.subject_id = ?
                AND math.setting_id = ?
            WHERE s.status = 1
            AND c.id = ?";

            // 添加排序
            if ($this->sortBy === 'score') {
                // 按总分排序（语文+数学）
                $sql .= " ORDER BY (IFNULL(chinese.total_score, 0) + IFNULL(math.total_score, 0)) DESC, s.student_number ASC";
            } else {
                // 默认按学号排序
                $sql .= " ORDER BY s.student_number ASC";
            }

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $this->chineseSubject['id'], 
                $this->projectId, 
                $this->mathSubject['id'], 
                $this->projectId, 
                $classId
            ]);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            throw new Exception('获取班级学生成绩数据失败: ' . $e->getMessage());
        }
    }

    /**
     * 计算语数总分
     */
    private function calculateTotalScore($score) {
        $total = 0;
        if (isset($score['chinese_score']) && $score['chinese_score'] !== null && $score['chinese_absent'] != '1') {
            $total += (float)$score['chinese_score'];
        }
        if (isset($score['math_score']) && $score['math_score'] !== null && $score['math_absent'] != '1') {
            $total += (float)$score['math_score'];
        }
        return $total;
    }

    /**
     * 获取成绩等级
     */
    private function getScoreLevel($score, $excellentScore, $goodScore, $passScore) {
        if ($score === null || $score === '') return '';
        
        $scoreValue = (float)$score;
        if ($scoreValue >= $excellentScore) return '优秀';
        if ($scoreValue >= $goodScore) return '良好';
        if ($scoreValue >= $passScore) return '合格';
        return '待合格';
    }

    /**
     * 生成Excel文件
     */
    public function generate() {
        try {
            // 获取年级下所有班级
            $stmt = $this->db->prepare("SELECT * FROM classes WHERE grade_id = ? AND status = 1 ORDER BY CAST(REPLACE(class_code, '班', '') AS UNSIGNED)");
            $stmt->execute([$this->gradeId]);
            $classes = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($classes)) {
                throw new Exception('未找到班级信息');
            }

            // 存储工作表索引和班级编号的映射
            $worksheetIndexMap = [];

            // 为每个班级创建工作表
            foreach ($classes as $index => $class) {
                // 创建新工作表
                $worksheet = $this->spreadsheet->createSheet();
                $worksheet->setTitle($class['class_name']);
                
                // 存储工作表索引和班级编号的映射
                $classNumber = (int)preg_replace('/[^0-9]/', '', $class['class_code']);
                $worksheetIndexMap[$index + 1] = $classNumber;

                // 设置标题
                $title = sprintf(
                    "%s %s %s 语数成绩单",
                    $this->projectInfo['project_name'],
                    $this->gradeInfo['grade_name'],
                    $class['class_name']
                );
                $this->setTitle($title, 'A1:F1');

                // 获取班级学生数据
                $scores = $this->getClassScores($class['id']);
                $totalStudents = count($scores);
                
                // 设置班级信息
                $worksheet->setCellValue('A2', "总人数：{$totalStudents}");
                $worksheet->getStyle('A2')->getFont()->setBold(true);

                // 设置表头
                $headers = ['序号', '学号', '姓名', '语文', '数学'];
                if ($this->downloadType === 'score') {
                    $headers[] = '总分';
                }
                $this->setHeaders($headers, 3);

                // 填充数据
                $row = 4;
                $leftCol = 1;  // 左侧数据起始列
                $index = 0;
                foreach ($scores as $score) {
                    if ($index > 0 && $index % 30 === 0) {
                        $leftCol = count($headers) + 2;  // 切换到右侧列
                        $row = 4;  // 重置行号
                    }
                    
                    $col = chr(ord('A') + $leftCol - 1);
                    
                    // 填充基础数据
                    $worksheet->setCellValue($col . $row, $index + 1);
                    $worksheet->setCellValue(chr(ord($col) + 1) . $row, $score['student_number']);
                    $worksheet->setCellValue(chr(ord($col) + 2) . $row, $score['student_name']);
                    
                    // 填充语文成绩
                    $currentCol = chr(ord($col) + 3);
                    if ($score['chinese_absent'] == '1') {
                        $worksheet->setCellValue($currentCol . $row, '缺考');
                    } else {
                        if ($this->downloadType === 'score') {
                            $worksheet->setCellValue($currentCol . $row, $score['chinese_score']);
                        } else {
                            // 使用预先计算的等级或根据分数线计算等级
                            if (!empty($score['chinese_level'])) {
                                $worksheet->setCellValue($currentCol . $row, $score['chinese_level']);
                            } else {
                                $worksheet->setCellValue($currentCol . $row, $this->getScoreLevel(
                                    $score['chinese_score'],
                                    $this->chineseSubject['excellent_score'],
                                    $this->chineseSubject['good_score'],
                                    $this->chineseSubject['pass_score']
                                ));
                            }
                        }
                    }
                    
                    // 填充数学成绩
                    $currentCol = chr(ord($currentCol) + 1);
                    if ($score['math_absent'] == '1') {
                        $worksheet->setCellValue($currentCol . $row, '缺考');
                    } else {
                        if ($this->downloadType === 'score') {
                            $worksheet->setCellValue($currentCol . $row, $score['math_score']);
                        } else {
                            // 使用预先计算的等级或根据分数线计算等级
                            if (!empty($score['math_level'])) {
                                $worksheet->setCellValue($currentCol . $row, $score['math_level']);
                            } else {
                                $worksheet->setCellValue($currentCol . $row, $this->getScoreLevel(
                                    $score['math_score'],
                                    $this->mathSubject['excellent_score'],
                                    $this->mathSubject['good_score'],
                                    $this->mathSubject['pass_score']
                                ));
                            }
                        }
                    }
                    
                    // 填充总分（仅在分数模式下）
                    if ($this->downloadType === 'score') {
                        $currentCol = chr(ord($currentCol) + 1);
                        $totalScore = $this->calculateTotalScore($score);
                        $worksheet->setCellValue($currentCol . $row, $totalScore > 0 ? $totalScore : '缺考');
                    }
                    
                    $row++;
                    $index++;
                }

                // 设置数据样式
                $lastCol = chr(ord('A') + count($headers) - 1);
                $this->setDataStyle('A3:' . $lastCol . ($row - 1));

                // 设置打印设置
                $this->setPageSetup();
            }

            // 移除默认工作表
            $this->spreadsheet->removeSheetByIndex(0);
            
            // 对工作表进行重新排序（按班级编号排序）
            if (count($worksheetIndexMap) > 1) {
                // 获取当前工作表顺序
                $worksheets = [];
                foreach ($this->spreadsheet->getWorksheetIterator() as $index => $sheet) {
                    $worksheets[$index] = $sheet;
                }
                
                // 根据班级编号对工作表索引进行排序
                asort($worksheetIndexMap);
                
                // 重新创建工作表，按排序后的顺序
                $newSpreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
                $newSpreadsheet->removeSheetByIndex(0); // 移除默认工作表
                
                foreach ($worksheetIndexMap as $oldIndex => $classNumber) {
                    $newSpreadsheet->addExternalSheet(clone $worksheets[$oldIndex - 1]);
                }
                
                // 替换原有的电子表格
                $this->spreadsheet = $newSpreadsheet;
            }

            // 保存文件
            return $this->save();
        } catch (Exception $e) {
            throw new Exception('生成Excel文件失败: ' . $e->getMessage());
        }
    }
} 