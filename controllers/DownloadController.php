<?php
/**
 * 文件名: controllers/DownloadController.php
 * 功能描述: 成绩单导出控制器
 * 
 * 该控制器负责:
 * 1. 生成各种成绩导出Excel文件
 * 2. 提供不同格式的成绩单导出（单科目、多科目、语数成绩单）
 * 3. 计算成绩统计数据并填充到Excel文件
 * 4. 处理导出文件的临时存储和下载
 * 
 * API调用路由:
 * - download/single_subject: 导出单科目成绩单
 * - download/multi_subject: 导出多科目成绩单
 * - download/chinese_math: 导出语文数学成绩单
 * 
 * 关联文件:
 * - api/download/SingleSubjectExport.php: 单科目导出实现
 * - api/download/MultiSubjectExport.php: 多科目导出实现
 * - api/download/ChineseMathExport.php: 语数导出实现
 * - core/ExcelExport.php: Excel导出基类
 * - modules/download.php: 成绩导出页面
 */

namespace Controllers;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class DownloadController {
    private $db;
    private $tempDir;
    private $logger;
    private const ITEMS_PER_COLUMN = 20; // 每栏显示的数据条数
    private const ROW_HEIGHT = 28; // 数据行高度（磅）
    
    // 单科目下载 - 四列显示时的列宽
    private const SINGLE_SUBJECT_FOUR_COLUMN_WIDTHS = [
        'number' => 4,  // 序号列宽度
        'name' => 8,      // 姓名列宽度
        'score' => 7,     // 成绩列宽度
        'level' => 7      // 等级列宽度
    ];
    
    // 单科目下载 - 三列显示时的列宽
    private const SINGLE_SUBJECT_THREE_COLUMN_WIDTHS = [
        'number' => 5,  // 序号列宽度
        'name' => 11,      // 姓名列宽度
        'value' => 10      // 值列宽度（成绩或等级）
    ];

    // 语数下载 - 分数模式列宽
    private const CHINESE_MATH_SCORE_WIDTHS = [
        'number' => 3,      // 序号
        'name' => 7.5,      // 姓名
        'chinese' => 5.5,   // 语文
        'math' => 5.5,      // 数学
        'total' => 6        // 总分
    ];

    // 语数下载 - 等级模式列宽
    private const CHINESE_MATH_LEVEL_WIDTHS = [
        'number' => 4,      // 序号
        'name' => 8,        // 姓名
        'chinese' => 7,     // 语文
        'math' => 7         // 数学
    ];

    // 语数下载 - 分析表格列宽
    private const CHINESE_MATH_ANALYSIS_WIDTHS = [
        'gap' => 3,         // 间隔列
        'subject' => 3.3,   // 语数单科
        'total' => 8,       // 总分和平均分
        'distribution' => 3, // 数据分布列
        'score' => 6,       // 最高分和最低分
        'rate' => 7.5       // 优秀率和合格率
    ];

    public function __construct() {
        global $db;
        $this->db = $db;
        $this->tempDir = __DIR__ . '/../temp/downloads/';
        $this->logger = \core\Logger::getInstance();
        
        if (!file_exists($this->tempDir)) {
            mkdir($this->tempDir, 0777, true);
        }
    }

    /**
     * 设置单科目下载的列宽
     * @param object $sheet Excel工作表对象
     * @param string $startCol 起始列
     * @param bool $includeScore 是否包含成绩
     * @param bool $includeLevel 是否包含等级
     */
    private function setSingleSubjectColumnWidths($sheet, $startCol, $includeScore, $includeLevel) {
        if ($includeScore && $includeLevel) {
            // 四列模式
            $sheet->getColumnDimension($startCol)->setWidth(self::SINGLE_SUBJECT_FOUR_COLUMN_WIDTHS['number']);
            $sheet->getColumnDimension(chr(ord($startCol) + 1))->setWidth(self::SINGLE_SUBJECT_FOUR_COLUMN_WIDTHS['name']);
            $sheet->getColumnDimension(chr(ord($startCol) + 2))->setWidth(self::SINGLE_SUBJECT_FOUR_COLUMN_WIDTHS['score']);
            $sheet->getColumnDimension(chr(ord($startCol) + 3))->setWidth(self::SINGLE_SUBJECT_FOUR_COLUMN_WIDTHS['level']);
        } else {
            // 三列模式
            $sheet->getColumnDimension($startCol)->setWidth(self::SINGLE_SUBJECT_THREE_COLUMN_WIDTHS['number']);
            $sheet->getColumnDimension(chr(ord($startCol) + 1))->setWidth(self::SINGLE_SUBJECT_THREE_COLUMN_WIDTHS['name']);
            $sheet->getColumnDimension(chr(ord($startCol) + 2))->setWidth(self::SINGLE_SUBJECT_THREE_COLUMN_WIDTHS['value']);
        }
    }

    /**
     * 设置语数下载的列宽
     * @param object $sheet Excel工作表对象
     * @param string $startCol 起始列
     * @param string $downloadType 下载类型（score或level）
     */
    private function setChineseMathColumnWidths($sheet, $startCol, $downloadType) {
        if ($downloadType === 'score') {
            // 分数模式
            $sheet->getColumnDimension($startCol)->setWidth(self::CHINESE_MATH_SCORE_WIDTHS['number']);
            $sheet->getColumnDimension(chr(ord($startCol) + 1))->setWidth(self::CHINESE_MATH_SCORE_WIDTHS['name']);
            $sheet->getColumnDimension(chr(ord($startCol) + 2))->setWidth(self::CHINESE_MATH_SCORE_WIDTHS['chinese']);
            $sheet->getColumnDimension(chr(ord($startCol) + 3))->setWidth(self::CHINESE_MATH_SCORE_WIDTHS['math']);
            $sheet->getColumnDimension(chr(ord($startCol) + 4))->setWidth(self::CHINESE_MATH_SCORE_WIDTHS['total']);
        } else {
            // 等级模式
            $sheet->getColumnDimension($startCol)->setWidth(self::CHINESE_MATH_LEVEL_WIDTHS['number']);
            $sheet->getColumnDimension(chr(ord($startCol) + 1))->setWidth(self::CHINESE_MATH_LEVEL_WIDTHS['name']);
            $sheet->getColumnDimension(chr(ord($startCol) + 2))->setWidth(self::CHINESE_MATH_LEVEL_WIDTHS['chinese']);
            $sheet->getColumnDimension(chr(ord($startCol) + 3))->setWidth(self::CHINESE_MATH_LEVEL_WIDTHS['math']);
        }
    }

    /**
     * 获取成绩分析数据
     */
    private function getScoreAnalysis($scores, $subjectInfo, $classId, $gradeId, $subjectId, $settingId) {
        // 尝试从数据库获取分析数据
        $analyticsData = $this->db->query("
            SELECT * FROM score_analytics 
            WHERE class_id = " . intval($classId) . " 
            AND grade_id = " . intval($gradeId) . " 
            AND subject_id = " . intval($subjectId) . " 
            AND setting_id = " . intval($settingId) . "
            LIMIT 1"
        )->fetch();
        
        // 如果找到了分析数据，使用数据库中的统计结果
        if ($analyticsData && isset($analyticsData['score_distribution'])) {

            
            $analysis = [
                'total_score' => $analyticsData['total_score'],
                'average_score' => $analyticsData['average_score'],
                'present_count' => $analyticsData['attended_students'],
                'excellent_count' => $analyticsData['excellent_count'],
                'pass_count' => $analyticsData['pass_count'] + $analyticsData['good_count'] + $analyticsData['excellent_count'],
                'max_score' => $analyticsData['max_score'],
                'min_score' => $analyticsData['min_score'],
                'distribution' => json_decode($analyticsData['score_distribution'], true),
            ];
            
            // 计算各项率
            $analysis['excellent_rate'] = $analysis['present_count'] > 0 
                ? round(($analysis['excellent_count'] / $analysis['present_count']) * 100, 2) . '%'
                : '0%';
            $analysis['pass_rate'] = $analysis['present_count'] > 0 
                ? round(($analysis['pass_count'] / $analysis['present_count']) * 100, 2) . '%'
                : '0%';
                
            // 转换分布格式以匹配现有的处理逻辑
            if (is_array($analysis['distribution'])) {
                $convertedDistribution = array_fill(0, 13, 0);
                
                // 映射不同的分数段
                $keyMapping = [
                    '100' => 0,
                    '99.5-95' => 1,
                    '94.5-90' => 2,
                    '89.5-85' => 3,
                    '84.5-80' => 4,
                    '79.5-75' => 5,
                    '74.5-70' => 6,
                    '69.5-65' => 7,
                    '64.5-60' => 8,
                    '59.5-55' => 9,
                    '54.5-50' => 10,
                    '49.5-40' => 11,
                    '40以下' => 12,
                ];
                
                foreach ($keyMapping as $key => $index) {
                    if (isset($analysis['distribution'][$key])) {
                        $convertedDistribution[$index] = $analysis['distribution'][$key];
                    }
                }
                
                $analysis['distribution'] = $convertedDistribution;
            }
            
            return $analysis;
        }
        
        // 如果没有找到数据库中的分析数据，则进行实时计算（保留原有逻辑作为备份）
        
        
        $analysis = [
            'total_score' => 0,
            'average_score' => 0,
            'present_count' => 0,
            'excellent_count' => 0,
            'pass_count' => 0,
            'max_score' => 0,    // 添加最高分
            'min_score' => 100,  // 添加最低分
            'distribution' => array_fill(0, 13, 0), // 13个分数段
        ];

        // 计算总分和到考人数
        foreach ($scores as $score) {
            if (!$score['is_absent']) {
                $scoreValue = floatval($score['total_score']);
                $analysis['total_score'] += $scoreValue;
                $analysis['present_count']++;
                
                // 更新最高分和最低分
                if ($scoreValue > $analysis['max_score']) {
                    $analysis['max_score'] = $scoreValue;
                }
                if ($scoreValue < $analysis['min_score']) {
                    $analysis['min_score'] = $scoreValue;
                }

                // 统计分数段分布
                $this->updateScoreDistribution($analysis['distribution'], $scoreValue);
                
                // 统计优秀和合格人数
                if ($scoreValue >= $subjectInfo['excellent_score']) {
                    $analysis['excellent_count']++;
                }
                if ($scoreValue >= $subjectInfo['pass_score']) {
                    $analysis['pass_count']++;
                }
            }
        }

        // 如果没有有效成绩，将最低分设为0
        if ($analysis['present_count'] == 0) {
            $analysis['min_score'] = 0;
        }

        // 计算平均分
        if ($analysis['present_count'] > 0) {
            $analysis['average_score'] = round($analysis['total_score'] / $analysis['present_count'], 2);
        }

        // 计算各项率
        $analysis['excellent_rate'] = $analysis['present_count'] > 0 
            ? round(($analysis['excellent_count'] / $analysis['present_count']) * 100, 2) . '%'
            : '0%';
        $analysis['pass_rate'] = $analysis['present_count'] > 0 
            ? round(($analysis['pass_count'] / $analysis['present_count']) * 100, 2) . '%'
            : '0%';

        return $analysis;
    }

    private function updateScoreDistribution(&$distribution, $score) {
        if ($score == 100) {
            $distribution[0]++;  // 100分
        } elseif ($score >= 95) {
            $distribution[1]++;  // 99.5-95
        } elseif ($score >= 90) {
            $distribution[2]++;  // 94.5-90
        } elseif ($score >= 85) {
            $distribution[3]++;  // 89.5-85
        } elseif ($score >= 80) {
            $distribution[4]++;  // 84.5-80
        } elseif ($score >= 75) {
            $distribution[5]++;  // 79.5-75
        } elseif ($score >= 70) {
            $distribution[6]++;  // 74.5-70
        } elseif ($score >= 65) {
            $distribution[7]++;  // 69.5-65
        } elseif ($score >= 60) {
            $distribution[8]++;  // 64.5-60
        } elseif ($score >= 55) {
            $distribution[9]++;  // 59.5-55
        } elseif ($score >= 50) {
            $distribution[10]++; // 54.5-50
        } else {
            $distribution[11]++; // 49.5以下
        }
    }

    /**
     * 单科目下载
     */
    public function singleSubject() {
        try {
            if (empty($_POST['setting_id']) || empty($_POST['grade_id']) || empty($_POST['subject_id'])) {
                return ['success' => false, 'error' => '缺少必要参数'];
            }

            // 获取下载选项
            $includeScore = isset($_POST['include_score']) ? filter_var($_POST['include_score'], FILTER_VALIDATE_BOOLEAN) : false;
            $includeLevel = isset($_POST['include_level']) ? filter_var($_POST['include_level'], FILTER_VALIDATE_BOOLEAN) : false;

            if (!$includeScore && !$includeLevel) {
                return ['success' => false, 'error' => '请至少选择一项下载内容（分数或等级）'];
            }

            // 获取基础信息用于日志记录
            $settingInfo = $this->db->query("SELECT * FROM settings WHERE id = " . intval($_POST['setting_id']))->fetch();
            $subjectInfo = $this->db->query("SELECT * FROM subjects WHERE id = " . intval($_POST['subject_id']))->fetch();
            $gradeInfo = $this->db->query("SELECT * FROM grades WHERE id = " . intval($_POST['grade_id']))->fetch();

            // 获取班级列表，按班号排序
            $classes = $this->db->query("
                SELECT * FROM classes 
                WHERE grade_id = " . intval($_POST['grade_id']) . " AND status = 1
                ORDER BY CAST(class_code AS UNSIGNED) ASC"
            )->fetchAll();

            if (!$settingInfo || !$subjectInfo || !$gradeInfo || empty($classes)) {
                return ['success' => false, 'error' => '未找到相关数据'];
            }

            // 创建 PhpSpreadsheet 对象
            $spreadsheet = new Spreadsheet();
            $spreadsheet->removeSheetByIndex(0);

            // 计算每组的列数
            $columnsPerGroup = ($includeScore && $includeLevel) ? 4 : 3;

            // 为每个班级创建sheet
            foreach ($classes as $classIndex => $class) {
                // 获取该班级的成绩数据
                $sql = "SELECT s.*, st.student_number, st.student_name 
                        FROM scores s
                        JOIN students st ON s.student_id = st.id
                        WHERE s.setting_id = " . intval($_POST['setting_id']) . "
                        AND s.grade_id = " . intval($_POST['grade_id']) . "
                        AND s.class_id = " . intval($class['id']) . "
                        AND s.subject_id = " . intval($_POST['subject_id']) . "
                        ORDER BY " . ($_POST['sort_by'] === 'score' ? 'total_score DESC, student_number ASC' : 'student_number ASC');

                $scores = $this->db->query($sql)->fetchAll();

                // 根据排序方式排序
                if ($_POST['sort_by'] === 'score') {
                    uasort($scores, function($a, $b) {
                        return $b['total_score'] <=> $a['total_score'];
                    });
                } else {
                    uasort($scores, function($a, $b) {
                        return $a['student_number'] <=> $b['student_number'];
                    });
                }

                // 创建新的工作表
                $sheet = new \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet($spreadsheet, $class['class_name']);
                $spreadsheet->addSheet($sheet, $classIndex);

                // 设置页边距
                $sheet->getPageMargins()->setTop(0.7874);
                $sheet->getPageMargins()->setBottom(0.7874);
                $sheet->getPageMargins()->setLeft(0.9843);
                $sheet->getPageMargins()->setRight(0.7874);

                // 设置表头
                $headerText = $settingInfo['school_name'] . $settingInfo['current_semester'] . $subjectInfo['subject_name'] . $settingInfo['project_name'] . '数据汇总表';
                $sheet->setCellValue('A1', $headerText);

                // 计算最后一列
                $lastColumn = chr(65 + ($columnsPerGroup * 3) - 1); // 最多3组
                $sheet->mergeCells('A1:' . $lastColumn . '1');
                $sheet->getStyle('A1')->getFont()->setSize(14);
                $sheet->getStyle('A1')->getAlignment()->setHorizontal('center');

                // 设置班级信息
                $sheet->setCellValue('A2', $gradeInfo['grade_name'] . $class['class_name']);
                $sheet->setCellValue('C2', '总人数: ' . count($scores));
                $sheet->setCellValue('E2', '到考人数: ' . count(array_filter($scores, fn($s) => !$s['is_absent'])));

                // 计算需要的栏数
                $totalColumns = ceil(count($scores) / self::ITEMS_PER_COLUMN);
                $columnGroups = [];
                
                // 分配数据到每一栏
                for ($i = 0; $i < $totalColumns && $i < 3; $i++) {
                    $start = $i * self::ITEMS_PER_COLUMN;
                    $columnGroups[$i] = array_slice($scores, $start, self::ITEMS_PER_COLUMN);
                }

                // 设置每一栏的数据
                foreach ($columnGroups as $groupIndex => $groupScores) {
                    // 计算当前组的起始列和结束列
                    $startCol = chr(65 + $groupIndex * $columnsPerGroup);
                    $endCol = chr(ord($startCol) + $columnsPerGroup - 1);
                    
                    // 确保变量已定义
                    $currentIncludeScore = $includeScore ?? false;
                    $currentIncludeLevel = $includeLevel ?? false;
                    
                    // 设置列宽
                    $this->setSingleSubjectColumnWidths($sheet, $startCol, $currentIncludeScore, $currentIncludeLevel);
                    
                    // 设置表头标题
                    $sheet->setCellValue($startCol . '3', '序号');
                    $sheet->setCellValue(chr(ord($startCol) + 1) . '3', '姓名');
                    
                    $currentCol = ord($startCol) + 2;
                    if ($currentIncludeScore) {
                        $sheet->setCellValue(chr($currentCol) . '3', '成绩');
                        if ($currentIncludeLevel) $currentCol++;
                    }
                    if ($currentIncludeLevel) {
                        $sheet->setCellValue(chr($currentCol) . '3', '等级');
                    }
                    
                    // 设置表头样式
                    $sheet->getStyle($startCol . '3:' . $endCol . '3')->getFont()
                        ->setBold(true)
                        ->setSize(10);
                    // 设置自动换行
                    $sheet->getStyle($startCol . '3:' . $endCol . '3')
                        ->getAlignment()
                        ->setWrapText(true);
                    
                    // 填充数据
                    foreach ($groupScores as $index => $score) {
                        $row = $index + 4;
                        $currentCol = ord($startCol);
                        
                        // 序号
                        $sheet->setCellValue(chr($currentCol) . $row, $index + 1 + ($groupIndex * self::ITEMS_PER_COLUMN));
                        $currentCol++;
                        
                        // 姓名
                        $sheet->setCellValue(chr($currentCol) . $row, $score['student_name']);
                        $currentCol++;
                        
                        // 成绩和等级
                        if ($currentIncludeScore) {
                            $sheet->setCellValue(chr($currentCol) . $row, $score['is_absent'] ? '缺考' : $score['total_score']);
                            if ($currentIncludeLevel) $currentCol++;
                        }
                        if ($currentIncludeLevel) {
                            $sheet->setCellValue(chr($currentCol) . $row, $this->getScoreLevel($score['total_score'], $subjectInfo));
                        }
                    }
                }

                // 设置字体
                $lastRow = 3 + self::ITEMS_PER_COLUMN;
                $sheet->getStyle('A1:' . $lastColumn . $lastRow)->getFont()->setName('宋体');
                // 设置第一行加粗
                $sheet->getStyle('A1')->getFont()->setBold(true);
                // 设置A4-L4不加粗
                $sheet->getStyle('A4:L4')->getFont()->setBold(false);
                $sheet->getStyle('A4:' . $lastColumn . $lastRow)->getFont()->setSize(10);

                // 设置对齐方式
                $sheet->getStyle('A1:' . $lastColumn . $lastRow)->getAlignment()
                    ->setHorizontal('center')
                    ->setVertical('center');
                
                // 设置行高（从第3行开始）
                for ($row = 3; $row <= $lastRow; $row++) {
                    $sheet->getRowDimension($row)->setRowHeight(self::ROW_HEIGHT);
                }

                // 设置第一行行高为50磅，内容居中
                $sheet->getRowDimension(1)->setRowHeight(50);
                $sheet->getStyle('A1')->getAlignment()->setVertical('center');

                // 设置第二行行高为30磅，合并单元格，内容居中
                $sheet->getRowDimension(2)->setRowHeight(30);
                $sheet->mergeCells('A2:B2');
                $sheet->mergeCells('C2:D2');
                $sheet->mergeCells('E2:G2');  // 扩展合并单元格范围
                $sheet->setCellValue('H2', '任课教师：');
                $sheet->mergeCells('H2:I2');
                $sheet->getStyle('A2:I2')->getAlignment()
                    ->setHorizontal('center')
                    ->setVertical('center');

                // 添加内外框线
                $sheet->getStyle('A3:' . $lastColumn . $lastRow)->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
                $sheet->getStyle('A3:' . $lastColumn . $lastRow)->getBorders()->getOutline()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);

                // 获取分析数据
                $analysis = $this->getScoreAnalysis($scores, $subjectInfo, $class['id'], $gradeInfo['id'], $subjectInfo['id'], $settingInfo['id']);

                // 添加质量分析总表
                // 在Q列添加新的标题行
                $analysisHeaderText = $settingInfo['school_name'] . $settingInfo['current_semester'] . $subjectInfo['subject_name'] . $settingInfo['project_name'] . '质量分析总表';
                $sheet->setCellValue('Q1', $analysisHeaderText);
                $sheet->mergeCells('Q1:AI1');
                
                // 设置分析部分的列宽
                // 设置P列为间隔列
                $sheet->getColumnDimension('P')->setWidth(self::CHINESE_MATH_ANALYSIS_WIDTHS['gap']);
                
                // 设置Q列（语数单科）宽度
                $sheet->getColumnDimension('Q')->setWidth(self::CHINESE_MATH_ANALYSIS_WIDTHS['subject']);
                
                // 设置R-S列（总分和平均分）宽度
                foreach (range('R', 'S') as $col) {
                    $sheet->getColumnDimension($col)->setWidth(self::CHINESE_MATH_ANALYSIS_WIDTHS['total']);
                }
                
                // 设置数据分布列（T-AE）的宽度
                foreach (range('T', 'Z') as $col) {
                    $sheet->getColumnDimension($col)->setWidth(self::CHINESE_MATH_ANALYSIS_WIDTHS['distribution']);
                }
                for ($i = 0; $i <= 4; $i++) {
                    $col = 'A' . chr(ord('A') + $i);
                    $sheet->getColumnDimension($col)->setWidth(self::CHINESE_MATH_ANALYSIS_WIDTHS['distribution']);
                }
                
                // 设置最高分和最低分列宽度
                foreach (['AF', 'AG'] as $col) {
                    $sheet->getColumnDimension($col)->setWidth(self::CHINESE_MATH_ANALYSIS_WIDTHS['score']);
                }
                
                // 设置优秀率和合格率列宽度
                foreach (['AH', 'AI'] as $col) {
                    $sheet->getColumnDimension($col)->setWidth(self::CHINESE_MATH_ANALYSIS_WIDTHS['rate']);
                }
                
                // 设置标题字体
                $sheet->getStyle('Q1')->getFont()
                    ->setName('宋体')
                    ->setSize(14)
                    ->setBold(true);

                // 设置其他单元格字体
                $sheet->getStyle('Q2:AI8')->getFont()
                    ->setName('宋体')
                    ->setSize(10)
                    ->setBold(false);

                // 设置第二行信息
                $sheet->setCellValue('Q2', $gradeInfo['grade_name'] . $class['class_name']);
                $sheet->setCellValue('T2', '总人数：' . count($scores));
                $sheet->setCellValue('X2', '到考人数：' . $analysis['present_count']);
                $sheet->setCellValue('AD2', '任课教师：');
                $sheet->mergeCells('Q2:S2');
                $sheet->mergeCells('T2:W2');
                $sheet->mergeCells('X2:AB2');
                $sheet->mergeCells('AD2:AF2');

                // 设置分析数据表头
                $sheet->mergeCells('Q3:Q7');
                $sheet->setCellValue('Q3', $subjectInfo['subject_name']);
                $sheet->mergeCells('R3:R6');
                $sheet->setCellValue('R3', '总分');
                $sheet->mergeCells('S3:S6');
                $sheet->setCellValue('S3', '平均分');

                // 设置数据分布标题
                $sheet->mergeCells('T3:AE4');
                $sheet->setCellValue('T3', '数    据    分    布');

                // 设置分数段标题
                $distributionHeaders = [
                    'T5' => "100分",
                    'U5' => "99.5\n/\n95",
                    'V5' => "94.5\n/\n90",
                    'W5' => "89.5\n/\n85",
                    'X5' => "84.5\n/\n80",
                    'Y5' => "79.5\n/\n75",
                    'Z5' => "74.5\n/\n70",
                    'AA5' => "69.5\n/\n65",
                    'AB5' => "64.5\n/\n60",
                    'AC5' => "59.5\n/\n55",
                    'AD5' => "54.5\n/\n50",
                    'AE5' => "49.5\n以下"
                ];

                foreach ($distributionHeaders as $cell => $value) {
                    $sheet->setCellValue($cell, $value);
                    $sheet->getStyle($cell)->getAlignment()->setWrapText(true);
                    $sheet->getStyle($cell)->getFont()->setSize(7);
                }

                // 合并第5行到第6行的数据分布列
                foreach (range('T', 'Z') as $col) {
                    $sheet->mergeCells($col . '5:' . $col . '6');
                }
                foreach (['AA', 'AB', 'AC', 'AD', 'AE'] as $col) {
                    $sheet->mergeCells($col . '5:' . $col . '6');
                }

                // 设置最高分、最低分和率值标题
                $sheet->mergeCells('AF3:AF6');
                $sheet->setCellValue('AF3', '最高分');
                $sheet->mergeCells('AG3:AG6');
                $sheet->setCellValue('AG3', '最低分');
                $sheet->mergeCells('AH3:AH6');
                $sheet->setCellValue('AH3', '优秀率');
                $sheet->mergeCells('AI3:AI6');
                $sheet->setCellValue('AI3', '合格率');

                // 设置分析数据
                $sheet->setCellValue('R7', $analysis['total_score']);
                $sheet->setCellValue('S7', $analysis['average_score']);
                for ($i = 0; $i < 12; $i++) {
                    $col = chr(ord('T') + $i);
                    if ($i >= 7) {
                        $col = 'A' . chr(ord('A') + ($i - 7));
                    }
                    if ($col === 'AE') {
                        // 49.5分以下 = 40-49.5分人数 + 40分以下人数
                        $sheet->setCellValue($col . '7', ($analysis['distribution'][11] + $analysis['distribution'][12]));
                    } else {
                        $sheet->setCellValue($col . '7', $analysis['distribution'][$i]);
                    }
                }
                $sheet->setCellValue('AF7', $analysis['max_score']);
                $sheet->setCellValue('AG7', $analysis['min_score']);
                $sheet->setCellValue('AH7', $analysis['excellent_rate']);
                $sheet->setCellValue('AI7', $analysis['pass_rate']);

                // 设置样式
                $lastAnalysisColumn = 'AI';
                // 设置所有单元格居中对齐
                $sheet->getStyle('Q1:' . $lastAnalysisColumn . '7')->getAlignment()
                    ->setHorizontal('center')
                    ->setVertical('center');
                
                // 设置边框
                $sheet->getStyle('Q3:' . $lastAnalysisColumn . '7')->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
                $sheet->getStyle('Q3:' . $lastAnalysisColumn . '7')->getBorders()->getOutline()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
                
                // 启用所有单元格的自动换行
                $sheet->getStyle('Q1:' . $lastAnalysisColumn . '7')->getAlignment()
                    ->setWrapText(true)
                    ->setVertical('center');

                // 设置打印区域
                $printArea = 'A1:' . $lastColumn . $lastRow;  // 成绩列表区域
                $printArea .= ',Q1:' . $lastAnalysisColumn . '7';  // 分析数据区域
                $sheet->getPageSetup()->setPrintArea($printArea);

                // 设置打印相关属性
                $sheet->getPageSetup()
                    ->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_PORTRAIT)
                    ->setPaperSize(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_A4)
                    ->setFitToWidth(1)
                    ->setFitToHeight(1);
            }

            // 设置第一个sheet为活动sheet
            if ($spreadsheet->getSheetCount() > 0) {
                $spreadsheet->setActiveSheetIndex(0);
            }

            // 保存文件
            $writer = new Xlsx($spreadsheet);
            $filename = $settingInfo['school_name'] . $settingInfo['current_semester'] . $gradeInfo['grade_name'] . $subjectInfo['subject_name'] . $settingInfo['project_name'] . '数据汇总.xlsx';
            $filepath = $this->tempDir . $filename;
            $writer->save($filepath);

            return [
                'success' => true,
                'data' => [
                    'file_url' => 'temp/downloads/' . $filename,
                    'filename' => $filename
                ]
            ];

        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * 获取成绩等级
     */
    private function getScoreLevel($score, $subjectInfo) {
        if ($score === null) return '缺考';
        if ($score >= $subjectInfo['excellent_score']) return '优秀';
        if ($score >= $subjectInfo['good_score']) return '良好';
        if ($score >= $subjectInfo['pass_score']) return '合格';
        return '待合格';
    }

    /**
     * 多科目下载
     */
    public function multiSubject() {
        // 记录尝试访问未完成功能的日志
        $this->logger->warning('功能未完成', [
            'type' => '多科目下载'
        ]);
        return ['success' => false, 'error' => '功能开发中'];
    }

    /**
     * 语数下载
     */
    public function chineseMath() {
        try {
            if (empty($_POST['setting_id']) || empty($_POST['grade_id'])) {
                return ['success' => false, 'error' => '缺少必要参数'];
            }

            $downloadType = $_POST['download_type'] ?? 'score';
            $sortBy = $_POST['sort_by'] ?? 'number';
            $settingId = intval($_POST['setting_id']);
            $gradeId = intval($_POST['grade_id']);

            // 获取基础信息用于日志记录
            $settingInfo = $this->db->query("SELECT * FROM settings WHERE id = " . $settingId)->fetch();
            $gradeInfo = $this->db->query("SELECT * FROM grades WHERE id = " . $gradeId)->fetch();

            // 获取与年级关联的语文和数学科目信息 - 参照 ClassAnalyticsController.getStudentChineseMathScores 方法
            $subjectsSql = "SELECT s.id, s.subject_name, s.subject_code, 
                           s.full_score, s.excellent_score, s.good_score, s.pass_score 
                           FROM subjects s
                           JOIN subject_grades sg ON s.id = sg.subject_id
                           WHERE sg.grade_id = ?
                           AND s.subject_name IN ('语文', '数学') 
                           AND s.setting_id = ? 
                           AND s.status = 1";
            $subjects = $this->db->fetchAll($subjectsSql, [$gradeId, $settingId]);

            if (count($subjects) !== 2) {
                return ['success' => false, 'error' => '未找到语文或数学科目信息'];
            }

            // 获取语文和数学的科目ID
            $chineseSubject = null;
            $mathSubject = null;
            foreach ($subjects as $subject) {
                if ($subject['subject_name'] === '语文') {
                    $chineseSubject = $subject;
                } else if ($subject['subject_name'] === '数学') {
                    $mathSubject = $subject;
                }
            }
            
            if (!$chineseSubject) {
                return ['success' => false, 'error' => '未找到与该年级关联的语文科目'];
            }
            
            if (!$mathSubject) {
                return ['success' => false, 'error' => '未找到与该年级关联的数学科目'];
            }

            // 获取班级列表，按班号排序
            $classesSql = "SELECT * FROM classes 
                          WHERE grade_id = ? AND status = 1 
                          ORDER BY CAST(class_code AS UNSIGNED) ASC";
            $classes = $this->db->fetchAll($classesSql, [$gradeId]);

            if (empty($classes)) {
                return ['success' => false, 'error' => '未找到班级数据'];
            }

            // 创建 PhpSpreadsheet 对象
            $spreadsheet = new Spreadsheet();
            $spreadsheet->removeSheetByIndex(0);

            // 为每个班级创建sheet
            foreach ($classes as $classIndex => $class) {
                // 使用与 ClassAnalyticsController.getStudentChineseMathScores 相同的查询方式获取成绩
                $sql = "SELECT 
                    s.id as student_id,
                    s.student_number,
                    s.student_name,
                    c.class_name,
                    chinese.total_score as chinese_score,
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
                if ($sortBy === 'score') {
                    // 按总分排序（语文+数学）
                    $sql .= " ORDER BY (IFNULL(chinese.total_score, 0) + IFNULL(math.total_score, 0)) DESC, s.student_number ASC";
                        } else {
                    // 默认按学号排序
                    $sql .= " ORDER BY s.student_number ASC";
                }

                $studentScores = $this->db->fetchAll($sql, [
                    $chineseSubject['id'], 
                    $settingId, 
                    $mathSubject['id'], 
                    $settingId, 
                    $class['id']
                ]);
                
                // 将查询结果转换为所需格式
                $scores = [];
                foreach ($studentScores as $student) {
                    $scores[$student['student_id']] = [
                        'student_name' => $student['student_name'],
                        'student_number' => $student['student_number'],
                        'chinese' => [
                            'total_score' => $student['chinese_score'],
                            'is_absent' => $student['chinese_absent'],
                            'score_level' => $student['chinese_level']
                        ],
                        'math' => [
                            'total_score' => $student['math_score'],
                            'is_absent' => $student['math_absent'],
                            'score_level' => $student['math_level']
                        ],
                        'total_score' => 0
                    ];
                    
                    // 计算总分
                    if (!$student['chinese_absent'] && $student['chinese_score'] !== null) {
                        $scores[$student['student_id']]['total_score'] += floatval($student['chinese_score']);
                    }
                    if (!$student['math_absent'] && $student['math_score'] !== null) {
                        $scores[$student['student_id']]['total_score'] += floatval($student['math_score']);
                    }
                }

                // 创建新的工作表
                $sheet = new \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet($spreadsheet, $class['class_name']);
                $spreadsheet->addSheet($sheet, $classIndex);

                // 设置页边距
                $sheet->getPageMargins()->setTop(0.7874);
                $sheet->getPageMargins()->setBottom(0.7874);
                $sheet->getPageMargins()->setLeft(0.9843);
                $sheet->getPageMargins()->setRight(0.7874);

                // 设置表头
                $headerText = $settingInfo['school_name'] . $settingInfo['current_semester'] . '语文数学' . $settingInfo['project_name'] . '数据汇总表';
                $sheet->setCellValue('A1', $headerText);

                // 计算需要的列数和合并单元格范围
                $columnsPerGroup = $downloadType === 'score' ? 5 : 4; // 分数模式：序号、姓名、语文、数学、总分；等级模式：序号、姓名、语文、数学
                $lastColumn = chr(65 + ($columnsPerGroup * 3) - 1); // 最多3组
                $sheet->mergeCells('A1:' . $lastColumn . '1');
                // 设置第一行标题样式：宋体14号加粗，水平垂直居中
                $sheet->getStyle('A1')->getFont()
                    ->setName('宋体')
                    ->setSize(14)
                    ->setBold(true);
                $sheet->getStyle('A1')->getAlignment()
                    ->setHorizontal('center')
                    ->setVertical('center');

                // 设置第一行行高为50磅
                $sheet->getRowDimension(1)->setRowHeight(50);

                // 设置班级信息
                $sheet->setCellValue('A2', $gradeInfo['grade_name'] . $class['class_name']);
                $sheet->setCellValue('C2', '总人数: ' . count($scores));
                $sheet->setCellValue('E2', '到考人数: ' . count(array_filter($scores, function($s) {
                    return (!isset($s['chinese']['is_absent']) || !$s['chinese']['is_absent']) &&
                           (!isset($s['math']['is_absent']) || !$s['math']['is_absent']);
                })));
                
                // 在到考人数后面增加"任课教师："
                $sheet->setCellValue('H2', '任课教师：');
                
                // 设置第二行行高为30磅
                $sheet->getRowDimension(2)->setRowHeight(30);
                
                // 合并单元格
                $sheet->mergeCells('A2:B2');
                $sheet->mergeCells('C2:D2');
                $sheet->mergeCells('E2:G2');
                $sheet->mergeCells('H2:I2');

                // 计算需要的栏数
                $totalColumns = ceil(count($scores) / self::ITEMS_PER_COLUMN);
                $columnGroups = [];
                
                // 分配数据到每一栏
                for ($i = 0; $i < $totalColumns && $i < 3; $i++) {
                    $start = $i * self::ITEMS_PER_COLUMN;
                    $columnGroups[$i] = array_slice($scores, $start, self::ITEMS_PER_COLUMN);
                }

                // 设置每一栏的数据
                foreach ($columnGroups as $groupIndex => $groupScores) {
                    // 计算当前组的起始列和结束列
                    $startCol = chr(65 + $groupIndex * $columnsPerGroup);
                    $endCol = chr(ord($startCol) + $columnsPerGroup - 1);
                    
                    // 设置列宽
                    $this->setChineseMathColumnWidths($sheet, $startCol, $downloadType);
                    
                    // 设置表头标题
                    $sheet->setCellValue($startCol . '3', '序号');
                    $sheet->setCellValue(chr(ord($startCol) + 1) . '3', '姓名');
                    $sheet->setCellValue(chr(ord($startCol) + 2) . '3', '语文');
                    $sheet->setCellValue(chr(ord($startCol) + 3) . '3', '数学');
                    if ($downloadType === 'score') {
                        $sheet->setCellValue(chr(ord($startCol) + 4) . '3', '总分');
                    }
                    
                    // 设置表头样式
                    $sheet->getStyle($startCol . '3:' . $endCol . '3')->getFont()
                        ->setBold(true)
                        ->setSize(10);
                    // 设置自动换行
                    $sheet->getStyle($startCol . '3:' . $endCol . '3')
                        ->getAlignment()
                        ->setWrapText(true);
                    
                    // 填充数据
                    $index = 0;
                    foreach ($groupScores as $score) {
                        $row = $index + 4;
                        $currentCol = ord($startCol);
                        
                        // 序号
                        $sheet->setCellValue(chr($currentCol) . $row, $index + 1 + ($groupIndex * self::ITEMS_PER_COLUMN));
                        $currentCol++;
                        
                        // 姓名
                        $sheet->setCellValue(chr($currentCol) . $row, $score['student_name']);
                        $currentCol++;
                        
                        // 语文
                        if ($downloadType === 'score') {
                            $sheet->setCellValue(chr($currentCol) . $row, 
                                isset($score['chinese']) ? 
                                ($score['chinese']['is_absent'] ? '缺考' : $score['chinese']['total_score']) : 
                                '');
                        } else {
                            $sheet->setCellValue(chr($currentCol) . $row, 
                                isset($score['chinese']) && !$score['chinese']['is_absent'] ? 
                                ($score['chinese']['score_level'] ?: $this->getScoreLevel($score['chinese']['total_score'], $chineseSubject)) : 
                                '');
                        }
                        $currentCol++;
                        
                        // 数学
                        if ($downloadType === 'score') {
                            $sheet->setCellValue(chr($currentCol) . $row, 
                                isset($score['math']) ? 
                                ($score['math']['is_absent'] ? '缺考' : $score['math']['total_score']) : 
                                '');
                        } else {
                            $sheet->setCellValue(chr($currentCol) . $row, 
                                isset($score['math']) && !$score['math']['is_absent'] ? 
                                ($score['math']['score_level'] ?: $this->getScoreLevel($score['math']['total_score'], $mathSubject)) : 
                                '');
                        }
                        $currentCol++;
                        
                        // 总分（仅在分数模式下）
                        if ($downloadType === 'score') {
                            $sheet->setCellValue(chr($currentCol) . $row, $score['total_score'] > 0 ? $score['total_score'] : '缺考');
                        }
                        
                        $index++;
                    }
                }

                // 设置数据行高度
                for ($row = 4; $row < 4 + self::ITEMS_PER_COLUMN; $row++) {
                    $sheet->getRowDimension($row)->setRowHeight(self::ROW_HEIGHT);
                }

                // 设置所有单元格居中
                foreach ($columnGroups as $groupIndex => $groupScores) {
                    $startCol = chr(65 + $groupIndex * $columnsPerGroup);
                    $endCol = chr(ord($startCol) + $columnsPerGroup - 1);
                    for ($row = 3; $row < 4 + self::ITEMS_PER_COLUMN; $row++) {
                        $sheet->getStyle($startCol . $row . ':' . $endCol . $row)
                            ->getAlignment()
                    ->setHorizontal('center')
                    ->setVertical('center');
                    }
                }
                
                // 设置字体
                $lastRow = 3 + self::ITEMS_PER_COLUMN;
                $sheet->getStyle('A1:' . $lastColumn . $lastRow)->getFont()->setName('宋体');
                
                // 设置第一行加粗
                $sheet->getStyle('A1')->getFont()->setBold(true);
                
                // 设置第3行开始的内容行列的内外框线
                $sheet->getStyle('A3:' . $lastColumn . $lastRow)->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
                $sheet->getStyle('A3:' . $lastColumn . $lastRow)->getBorders()->getOutline()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);

                // 添加质量分析总表
                // 设置P列宽度为2.36
                $sheet->getColumnDimension('P')->setWidth(2.36);
                
                // 计算语文数学成绩的统计数据
                $chineseScores = [];
                $mathScores = [];
                $chineseTotal = 0;
                $mathTotal = 0;
                $chineseAttended = 0;
                $mathAttended = 0;
                $chineseExcellent = 0;
                $mathExcellent = 0;
                $chinesePass = 0;
                $mathPass = 0;
                $chineseMax = 0;
                $mathMax = 0;
                $chineseMin = 100;
                $mathMin = 100;
                
                // 分数分布数组 [100, 95-99.5, 90-94.5, 85-89.5, 80-84.5, 75-79.5, 70-74.5, 65-69.5, 60-64.5, 55-59.5, 50-54.5, <49.5]
                $chineseDistribution = array_fill(0, 12, 0);
                $mathDistribution = array_fill(0, 12, 0);
                
                foreach ($scores as $score) {
                    // 语文成绩统计
                    if (isset($score['chinese']) && !$score['chinese']['is_absent'] && $score['chinese']['total_score'] !== null) {
                        $chineseScore = floatval($score['chinese']['total_score']);
                        $chineseScores[] = $chineseScore;
                        $chineseTotal += $chineseScore;
                        $chineseAttended++;
                        
                        // 更新最高分和最低分
                        $chineseMax = max($chineseMax, $chineseScore);
                        $chineseMin = min($chineseMin, $chineseScore);
                        
                        // 统计优秀和合格人数
                        if ($chineseScore >= $chineseSubject['excellent_score']) {
                            $chineseExcellent++;
                        }
                        if ($chineseScore >= $chineseSubject['pass_score']) {
                            $chinesePass++;
                        }
                        
                        // 更新分数分布
                        $this->updateScoreDistribution($chineseDistribution, $chineseScore);
                    }
                    
                    // 数学成绩统计
                    if (isset($score['math']) && !$score['math']['is_absent'] && $score['math']['total_score'] !== null) {
                        $mathScore = floatval($score['math']['total_score']);
                        $mathScores[] = $mathScore;
                        $mathTotal += $mathScore;
                        $mathAttended++;
                        
                        // 更新最高分和最低分
                        $mathMax = max($mathMax, $mathScore);
                        $mathMin = min($mathMin, $mathScore);
                        
                        // 统计优秀和合格人数
                        if ($mathScore >= $mathSubject['excellent_score']) {
                            $mathExcellent++;
                        }
                        if ($mathScore >= $mathSubject['pass_score']) {
                            $mathPass++;
                        }
                        
                        // 更新分数分布
                        $this->updateScoreDistribution($mathDistribution, $mathScore);
                    }
                }
                
                // 计算平均分
                $chineseAvg = $chineseAttended > 0 ? round($chineseTotal / $chineseAttended, 2) : 0;
                $mathAvg = $mathAttended > 0 ? round($mathTotal / $mathAttended, 2) : 0;
                
                // 计算优秀率和合格率
                $chineseExcellentRate = $chineseAttended > 0 ? round(($chineseExcellent / $chineseAttended) * 100, 1) . '%' : '0%';
                $mathExcellentRate = $mathAttended > 0 ? round(($mathExcellent / $mathAttended) * 100, 1) . '%' : '0%';
                $chinesePassRate = $chineseAttended > 0 ? round(($chinesePass / $chineseAttended) * 100, 1) . '%' : '0%';
                $mathPassRate = $mathAttended > 0 ? round(($mathPass / $mathAttended) * 100, 1) . '%' : '0%';
                
                // 如果没有有效成绩，将最低分设为0
                if ($chineseAttended == 0) {
                    $chineseMin = 0;
                }
                if ($mathAttended == 0) {
                    $mathMin = 0;
                }
                
                // 在Q列添加质量分析总表
                // 设置标题
                $analysisHeaderText = $settingInfo['school_name'] . $settingInfo['current_semester'] . '语文数学' . $settingInfo['project_name'] . '质量分析总表';
                $sheet->setCellValue('Q1', $analysisHeaderText);
                $sheet->mergeCells('Q1:AI1');
                $sheet->getStyle('Q1')->getFont()->setSize(14);
                $sheet->getStyle('Q1')->getAlignment()->setHorizontal('center');

                // 设置第二行信息
                $sheet->setCellValue('Q2', $gradeInfo['grade_name'] . $class['class_name']);
                $sheet->setCellValue('T2', '总人数：' . count($scores));
                $sheet->setCellValue('X2', '到考人数：' . $chineseAttended);
                $sheet->setCellValue('AD2', '任课教师：');
                $sheet->mergeCells('Q2:S2');
                $sheet->mergeCells('T2:W2');
                $sheet->mergeCells('X2:AC2');
                $sheet->mergeCells('AD2:AF2');

                // 设置分析数据表头
                $sheet->mergeCells('Q3:Q6');
                $sheet->setCellValue('Q3', '语数单科');
                $sheet->mergeCells('R3:R6');
                $sheet->setCellValue('R3', '总分');
                $sheet->mergeCells('S3:S6');
                $sheet->setCellValue('S3', '平均分');

                // 设置数据分布标题
                $sheet->mergeCells('T3:AE4');
                $sheet->setCellValue('T3', '数    据    分    布');

                // 设置分数段标题
                $distributionHeaders = [
                    'T5' => "100分",
                    'U5' => "99.5\n/\n95",
                    'V5' => "94.5\n/\n90",
                    'W5' => "89.5\n/\n85",
                    'X5' => "84.5\n/\n80",
                    'Y5' => "79.5\n/\n75",
                    'Z5' => "74.5\n/\n70",
                    'AA5' => "69.5\n/\n65",
                    'AB5' => "64.5\n/\n60",
                    'AC5' => "59.5\n/\n55",
                    'AD5' => "54.5\n/\n50",
                    'AE5' => "49.5\n以下"
                ];

                foreach ($distributionHeaders as $cell => $value) {
                    $sheet->setCellValue($cell, $value);
                    $sheet->getStyle($cell)->getAlignment()->setWrapText(true);
                    // 设置T-AE列第5、6行的字号为7号
                    $sheet->getStyle($cell)->getFont()->setSize(7);
                }

                // 合并第5行到第6行的数据分布列
                foreach (range('T', 'Z') as $col) {
                    $sheet->mergeCells($col . '5:' . $col . '6');
                }
                foreach (['AA', 'AB', 'AC', 'AD', 'AE'] as $col) {
                    $sheet->mergeCells($col . '5:' . $col . '6');
                }

                // 设置最高分、最低分和率值标题
                $sheet->mergeCells('AF3:AF6');
                $sheet->setCellValue('AF3', '最高分');
                $sheet->mergeCells('AG3:AG6');
                $sheet->setCellValue('AG3', '最低分');
                $sheet->mergeCells('AH3:AH6');
                $sheet->setCellValue('AH3', '优秀率');
                $sheet->mergeCells('AI3:AI6');
                $sheet->setCellValue('AI3', '合格率');

                // 设置语文数据
                $sheet->setCellValue('Q7', '语文');
                $sheet->setCellValue('R7', $chineseTotal);
                $sheet->setCellValue('S7', $chineseAvg);
                
                // 填充语文分数分布
                for ($i = 0; $i < 12; $i++) {
                    $col = chr(ord('T') + $i);
                    if ($i >= 7) {
                        $col = 'A' . chr(ord('A') + ($i - 7));
                    }
                    $sheet->setCellValue($col . '7', $chineseDistribution[$i]);
                }
                
                $sheet->setCellValue('AF7', $chineseMax);
                $sheet->setCellValue('AG7', $chineseMin);
                $sheet->setCellValue('AH7', $chineseExcellentRate);
                $sheet->setCellValue('AI7', $chinesePassRate);

                // 设置数学数据
                $sheet->setCellValue('Q8', '数学');
                $sheet->setCellValue('R8', $mathTotal);
                $sheet->setCellValue('S8', $mathAvg);
                
                // 填充数学分数分布
                for ($i = 0; $i < 12; $i++) {
                    $col = chr(ord('T') + $i);
                    if ($i >= 7) {
                        $col = 'A' . chr(ord('A') + ($i - 7));
                    }
                    $sheet->setCellValue($col . '8', $mathDistribution[$i]);
                }
                
                $sheet->setCellValue('AF8', $mathMax);
                $sheet->setCellValue('AG8', $mathMin);
                $sheet->setCellValue('AH8', $mathExcellentRate);
                $sheet->setCellValue('AI8', $mathPassRate);

                // 设置样式
                $lastAnalysisColumn = 'AI';
                // 设置所有单元格居中对齐
                $sheet->getStyle('Q1:' . $lastAnalysisColumn . '8')->getAlignment()
                    ->setHorizontal('center')
                    ->setVertical('center');
                
                // 设置Q-AI列（除第一行外）的字体为宋体10号，自动换行
                $sheet->getStyle('Q2:' . $lastAnalysisColumn . '8')->getFont()
                    ->setName('宋体')
                    ->setSize(10);
                $sheet->getStyle('Q2:' . $lastAnalysisColumn . '8')->getAlignment()
                    ->setWrapText(true);
                
                // 设置边框
                $sheet->getStyle('Q3:' . $lastAnalysisColumn . '8')->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
                $sheet->getStyle('Q3:' . $lastAnalysisColumn . '8')->getBorders()->getOutline()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
                
                // 设置分析表的列宽
                // 设置Q列（语数单科）宽度
                $sheet->getColumnDimension('Q')->setWidth(3.3);
                
                // 设置R-S列（总分和平均分）宽度
                $sheet->getColumnDimension('R')->setWidth(8);
                $sheet->getColumnDimension('S')->setWidth(8);
                
                // 设置数据分布列（T-AE）的宽度
                foreach (range('T', 'Z') as $col) {
                    $sheet->getColumnDimension($col)->setWidth(3);
                }
                foreach (['AA', 'AB', 'AC', 'AD', 'AE'] as $col) {
                    $sheet->getColumnDimension($col)->setWidth(3);
                }
                
                // 设置最高分和最低分列宽度
                $sheet->getColumnDimension('AF')->setWidth(6);
                $sheet->getColumnDimension('AG')->setWidth(6);
                
                // 设置优秀率和合格率列宽度
                $sheet->getColumnDimension('AH')->setWidth(7.5);
                $sheet->getColumnDimension('AI')->setWidth(7.5);
            }

            // 保存Excel文件
            $tempFileName = 'chinese_math_' . date('YmdHis') . '_' . rand(1000, 9999) . '.xlsx';
            $tempFilePath = $this->tempDir . $tempFileName;
            
            // 设置第一个sheet为活动sheet
            if ($spreadsheet->getSheetCount() > 0) {
                $spreadsheet->setActiveSheetIndex(0);
            }
            
            $writer = new Xlsx($spreadsheet);
            $writer->save($tempFilePath);
            
            // 返回文件URL和文件名
            $fileName = $settingInfo['school_name'] . $settingInfo['current_semester'] . '语文数学' . $settingInfo['project_name'] . '成绩单.xlsx';

            return [
                'success' => true,
                'data' => [
                    'file_url' => '../temp/downloads/' . $tempFileName,
                    'filename' => $fileName
                ]
            ];

        } catch (\Exception $e) {
            // 记录错误日志
                            $this->logger->error('成绩导出失败', [
                    'type' => '语数成绩',
                    'error' => $e->getMessage(),
                    'request_data' => [
                        'setting_id' => $_POST['setting_id'] ?? null,
                        'grade_id' => $_POST['grade_id'] ?? null,
                        'download_type' => $_POST['download_type'] ?? null
                    ]
                ]);
            return ['success' => false, 'error' => '导出失败：' . $e->getMessage()];
        }
    }
} 