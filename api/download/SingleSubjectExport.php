<?php
/**
 * 文件名: api/download/SingleSubjectExport.php
 * 功能描述: 单科目成绩导出类
 * 
 * 该类负责:
 * 1. 生成单科目成绩单Excel文件
 * 2. 按班级分工作表展示学生成绩
 * 3. 支持按成绩或学号排序
 * 4. 支持是否显示分数和等级的选项
 * 5. 清理过期的临时文件
 * 
 * API调用说明:
 * - 不直接通过API调用，由controllers/DownloadController.php调用
 * - 通过download/single_subject路由访问
 * 
 * 关联文件:
 * - core/ExcelExport.php: 基础Excel导出类
 * - core/Database.php: 数据库操作类
 * - controllers/DownloadController.php: 下载控制器
 * - api/download/single_subject.php: 单科目导出API入口
 * - temp/downloads/: 临时文件目录
 */

namespace Api\Download;

use Core\ExcelExport;
use Core\Database;
use PDO;
use Exception;

class SingleSubjectExport extends ExcelExport {
    private $db;
    private $settingId;
    private $gradeId;
    private $subjectId;
    private $includeScore;
    private $includeLevel;
    private $sortBy;
    private $settingInfo;
    private $gradeInfo;
    private $subjectInfo;

    public function __construct($params) {
        parent::__construct();
        $this->db = new Database();
        
        // 初始化参数
        $this->settingId = $params['setting_id'];
        $this->gradeId = $params['grade_id'];
        $this->subjectId = $params['subject_id'];
        $this->includeScore = $params['include_score'];
        $this->includeLevel = $params['include_level'];
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
        $stmt->execute([$this->settingId]);
        $this->settingInfo = $stmt->fetch(PDO::FETCH_ASSOC);

        // 获取年级信息
        $stmt = $this->db->prepare("SELECT * FROM grades WHERE id = ?");
        $stmt->execute([$this->gradeId]);
        $this->gradeInfo = $stmt->fetch(PDO::FETCH_ASSOC);

        // 获取科目信息
        $stmt = $this->db->prepare("SELECT * FROM subjects WHERE id = ? AND status = 1");
        $stmt->execute([$this->subjectId]);
        $this->subjectInfo = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$this->settingInfo || !$this->gradeInfo || !$this->subjectInfo) {
            throw new Exception('未找到相关基础信息');
        }
    }

    /**
     * 获取班级学生成绩数据
     */
    private function getClassScores($classId) {
        $orderBy = $this->sortBy === 'score' ? 'total_score DESC' : 'student_number ASC';
        $sql = "SELECT 
                    s.student_number,
                    s.student_name,
                    sc.base_score,
                    sc.extra_score,
                    sc.total_score,
                    sc.is_absent
                FROM students s
                LEFT JOIN scores sc ON s.id = sc.student_id 
                    AND sc.subject_id = ?
                    AND sc.setting_id = ?
                WHERE s.class_id = ?
                ORDER BY " . ($this->sortBy === 'score' ? 'sc.total_score DESC NULLS LAST, s.student_number ASC' : 's.student_number ASC');

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$this->subjectId, $this->settingId, $classId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * 获取成绩等级
     */
    private function getScoreLevel($score) {
        if ($score === null || $score === '') return '缺考';
        if ($score >= $this->subjectInfo['excellent_score']) return '优秀';
        if ($score >= $this->subjectInfo['good_score']) return '良好';
        if ($score >= $this->subjectInfo['pass_score']) return '及格';
        return '不及格';
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
                "%s %s %s %s成绩单",
                $this->settingInfo['project_name'],
                $this->gradeInfo['grade_name'],
                $class['class_name'],
                $this->subjectInfo['subject_name']
            );
            $this->setTitle($title, 'A1:F1');

            // 获取班级学生数据
            $scores = $this->getClassScores($class['id']);
            $totalStudents = count($scores);
            $presentStudents = count(array_filter($scores, fn($s) => !$s['is_absent']));
            
            // 设置班级信息
            $worksheet->setCellValue('A2', "总人数：{$totalStudents}");
            $worksheet->setCellValue('C2', "到考人数：{$presentStudents}");
            $worksheet->getStyle('A2:C2')->getFont()->setBold(true);

            // 设置表头
            $headers = ['序号', '姓名'];
            if ($this->includeScore) {
                $headers[] = '分数';
            }
            if ($this->includeLevel) {
                $headers[] = '等级';
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
                if ($this->includeScore) {
                    $value = $score['is_absent'] ? '缺考' : $score['total_score'];
                    $worksheet->setCellValue($currentCol . $row, $value);
                    $currentCol = chr(ord($currentCol) + 1);
                }
                
                if ($this->includeLevel) {
                    $value = $score['is_absent'] ? '缺考' : $this->getScoreLevel($score['total_score']);
                    $worksheet->setCellValue($currentCol . $row, $value);
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

    /**
     * 清理过期文件
     */
    public static function cleanupTempFiles() {
        $tempDir = __DIR__ . '/../../temp/downloads/';
        if (!is_dir($tempDir)) return;

        // 清理24小时前的文件
        $files = glob($tempDir . '*.xlsx');
        $now = time();
        foreach ($files as $file) {
            if ($now - filemtime($file) >= 86400) {
                unlink($file);
            }
        }
    }
} 