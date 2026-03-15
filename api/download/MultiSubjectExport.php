<?php
/**
 * 文件名: api/download/MultiSubjectExport.php
 * 功能描述: 多科目成绩导出类
 * 
 * 该类负责:
 * 1. 生成多科目成绩单Excel文件
 * 2. 按班级分工作表展示学生多科目成绩
 * 3. 计算各科目总分和平均分
 * 4. 支持按总分或学号排序
 * 5. 显示每个科目及成绩总分排名
 * 
 * API调用说明:
 * - 不直接通过API调用，由controllers/DownloadController.php调用
 * - 通过download/multi_subject路由访问
 * 
 * 关联文件:
 * - core/ExcelExport.php: 基础Excel导出类
 * - core/Database.php: 数据库操作类
 * - controllers/DownloadController.php: 下载控制器
 * - api/download/multi_subject.php: 多科目导出API入口
 * - temp/downloads/: 临时文件目录
 */

namespace Api\Download;

require_once __DIR__ . '/../../core/ExcelExport.php';
require_once __DIR__ . '/../../core/Database.php';

class MultiSubjectExport extends ExcelExport {
    private $db;
    private $projectId;
    private $gradeId;
    private $subjectIds;
    private $downloadType;
    private $sortBy;
    private $projectInfo;
    private $gradeInfo;
    private $subjectInfos;

    public function __construct($params) {
        parent::__construct();
        $this->db = new Database();
        
        // 初始化参数
        $this->projectId = $params['project_id'];
        $this->gradeId = $params['grade_id'];
        $this->subjectIds = $params['subject_ids'];
        $this->downloadType = $params['download_type'];
        $this->sortBy = $params['sort_by'];

        // 获取基础信息
        $this->loadBaseInfo();
    }

    /**
     * 加载基础信息
     */
    private function loadBaseInfo() {
        // 获取项目信息
        $stmt = $this->db->prepare("SELECT * FROM settings WHERE id = ?");
        $stmt->execute([$this->projectId]);
        $this->projectInfo = $stmt->fetch(PDO::FETCH_ASSOC);

        // 获取年级信息
        $stmt = $this->db->prepare("SELECT * FROM grades WHERE id = ?");
        $stmt->execute([$this->gradeId]);
        $this->gradeInfo = $stmt->fetch(PDO::FETCH_ASSOC);

        // 获取科目信息
        $placeholders = str_repeat('?,', count($this->subjectIds) - 1) . '?';
        $stmt = $this->db->prepare("SELECT * FROM subjects WHERE id IN ($placeholders)");
        $stmt->execute($this->subjectIds);
        $this->subjectInfos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * 获取班级学生成绩数据
     */
    private function getClassScores($classId) {
        $orderBy = $this->sortBy === 'score' ? 'total_score DESC' : 'student_number ASC';
        $sql = "SELECT 
                    s.student_number,
                    s.student_name,
                    sc.subject_id,
                    sc.base_score,
                    sc.extra_score,
                    sc.total_score,
                    sc.is_absent
                FROM students s
                LEFT JOIN scores sc ON s.id = sc.student_id 
                    AND sc.subject_id IN (" . implode(',', $this->subjectIds) . ")
                    AND sc.setting_id = ?
                WHERE s.class_id = ?
                ORDER BY s.student_number ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$this->projectId, $classId]);
        
        // 重组数据结构
        $scores = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $studentId = $row['student_number'];
            if (!isset($scores[$studentId])) {
                $scores[$studentId] = [
                    'student_number' => $row['student_number'],
                    'student_name' => $row['student_name'],
                    'subjects' => []
                ];
            }
            if ($row['subject_id']) {
                $scores[$studentId]['subjects'][$row['subject_id']] = [
                    'base_score' => $row['base_score'],
                    'extra_score' => $row['extra_score'],
                    'total_score' => $row['total_score'],
                    'is_absent' => $row['is_absent']
                ];
            }
        }

        // 如果按成绩排序，需要计算总分并重新排序
        if ($this->sortBy === 'score') {
            uasort($scores, function($a, $b) {
                $totalA = $this->calculateTotalScore($a['subjects']);
                $totalB = $this->calculateTotalScore($b['subjects']);
                return $totalB <=> $totalA;
            });
        }

        return $scores;
    }

    /**
     * 计算总分
     */
    private function calculateTotalScore($subjects) {
        $total = 0;
        foreach ($subjects as $subject) {
            if (!$subject['is_absent'] && $subject['total_score'] !== null) {
                $total += $subject['total_score'];
            }
        }
        return $total;
    }

    /**
     * 生成Excel文件
     */
    public function generate() {
        // 获取年级下所有班级
        $stmt = $this->db->prepare("SELECT * FROM classes WHERE grade_id = ? ORDER BY CAST(REPLACE(class_code, '班', '') AS UNSIGNED)");
        $stmt->execute([$this->gradeId]);
        $classes = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
                "%s %s %s 成绩单",
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
            $headers = ['序号', '姓名'];
            foreach ($this->subjectInfos as $subject) {
                // 3-6年级的语文只显示总分或等级
                if ($subject['subject_name'] === '语文' && 
                    in_array($this->gradeInfo['grade_code'], [3,4,5,6])) {
                    $headers[] = '语文';
                    continue;
                }
                $headers[] = $subject['subject_name'];
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
                $worksheet->setCellValue(chr(ord($col) + 1) . $row, $score['student_name']);
                
                // 填充成绩数据
                $currentCol = chr(ord($col) + 2);
                foreach ($this->subjectInfos as $subject) {
                    $subjectScore = $score['subjects'][$subject['id']] ?? null;
                    
                    if (!$subjectScore || $subjectScore['is_absent']) {
                        $value = '缺考';
                    } else {
                        if ($this->downloadType === 'score') {
                            $value = $subjectScore['total_score'];
                        } else {
                            $value = $this->getScoreLevel(
                                $subjectScore['total_score'],
                                $subject['excellent_score'],
                                $subject['good_score'],
                                $subject['pass_score']
                            );
                        }
                    }
                    
                    $worksheet->setCellValue($currentCol . $row, $value);
                    $currentCol = chr(ord($currentCol) + 1);
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
    }
} 