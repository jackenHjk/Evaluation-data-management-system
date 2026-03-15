<?php
/**
 * 文件名: templates/download_user_template.php
 * 功能描述: 用户批量导入模板下载
 * 
 * 该文件负责:
 * 1. 创建用户批量导入的Excel模板
 * 2. 提供模板下载功能
 * 3. 包含必要的列和说明
 */

// 设置错误报告
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/php_errors.log');

// 确保错误不会中断文件生成
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    error_log("Error ($errno): $errstr in $errfile on line $errline");
    return true;
});

// 引入PhpSpreadsheet库
require_once __DIR__ . '/../vendor/autoload.php';

// 引入数据库配置
require_once __DIR__ . '/../config/config.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

// 获取配置
$config = require_once __DIR__ . '/../config/config.php';

// 初始化连接状态
$dbConnected = false;

// 创建数据库连接
try {
    $dsn = "mysql:host={$config['db']['host']};dbname={$config['db']['dbname']};charset=utf8mb4";
    
    $db = new PDO(
        $dsn,
        $config['db']['username'],
        $config['db']['password'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
    $dbConnected = true;
} catch (PDOException $e) {
    // 记录错误但继续执行，生成基本模板
    error_log("数据库连接失败：" . $e->getMessage());
}

// 获取年段和学科信息
$grades = [];
$subjects = [];

// 默认示例数据
$defaultGrades = [
    ['grade_code' => 'G1', 'grade_name' => '一年级'],
    ['grade_code' => 'G2', 'grade_name' => '二年级'],
    ['grade_code' => 'G3', 'grade_name' => '三年级']
];

$defaultSubjects = [
    ['subject_code' => 'S1', 'subject_name' => '语文', 'grade_names' => '一年级、二年级、三年级'],
    ['subject_code' => 'S2', 'subject_name' => '数学', 'grade_names' => '一年级、二年级、三年级'],
    ['subject_code' => 'S3', 'subject_name' => '英语', 'grade_names' => '三年级']
];

if ($dbConnected) {
    try {
        // 获取年段信息
        $stmt = $db->query("SELECT grade_name, grade_code FROM grades ORDER BY id");
        if ($stmt) {
            $grades = $stmt->fetchAll();
        }

        // 获取当前项目ID
        $settingId = null;
        $stmt = $db->query("SELECT id FROM settings WHERE is_current = 1 LIMIT 1");
        if ($stmt) {
            $currentSetting = $stmt->fetch();
            if ($currentSetting) {
                $settingId = $currentSetting['id'];
            }
        }

        // 获取学科信息
        if ($settingId) {
            $stmt = $db->query(
                "SELECT s.subject_name, s.subject_code, GROUP_CONCAT(g.grade_name SEPARATOR '、') as grade_names
                 FROM subjects s
                 JOIN subject_grades sg ON s.id = sg.subject_id
                 JOIN grades g ON sg.grade_id = g.id
                 WHERE s.setting_id = {$settingId}
                 GROUP BY s.id
                 ORDER BY s.id"
            );
            if ($stmt) {
                $subjects = $stmt->fetchAll();
            }
        }
        
        // 如果数据库中没有数据，使用默认示例数据
        if (empty($grades)) {
            $grades = $defaultGrades;
        }
        if (empty($subjects)) {
            $subjects = $defaultSubjects;
        }
    } catch (Exception $e) {
        // 数据库查询出错，使用默认示例数据
        error_log("数据库查询失败：" . $e->getMessage());
        $dbConnected = false;
    }
}

// 如果数据库连接失败，使用默认示例数据
if (!$dbConnected) {
    $grades = $defaultGrades;
    $subjects = $defaultSubjects;
}

// 创建一个新的Spreadsheet对象
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('用户导入模板');

// 设置列宽
$sheet->getColumnDimension('A')->setWidth(15);
$sheet->getColumnDimension('B')->setWidth(15);
$sheet->getColumnDimension('C')->setWidth(10);
$sheet->getColumnDimension('D')->setWidth(10);
$sheet->getColumnDimension('E')->setWidth(10);
$sheet->getColumnDimension('F')->setWidth(30);
$sheet->getColumnDimension('G')->setWidth(30);

// 添加数据库连接状态提示
if (!$dbConnected) {
    $sheet->setCellValue('A2', '注意：数据库连接失败，以下为示例数据，请根据实际情况修改');
    $sheet->getStyle('A2')->getFont()->getColor()->setRGB('FF0000');
    $sheet->mergeCells('A2:G2');
    $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    
    // 向下移动示例数据
    $sheet->insertNewRowBefore(3, 1);
}

// 定义导入说明样式
$infoStyle = [
    'fill' => [
        'fillType' => Fill::FILL_SOLID,
        'startColor' => ['rgb' => 'E6F2FF'],
    ],
    'alignment' => [
        'horizontal' => Alignment::HORIZONTAL_LEFT,
        'vertical' => Alignment::VERTICAL_CENTER,
    ],
];

// 添加导入说明
$sheet->setCellValue('A1', '导入说明：');
$sheet->mergeCells('A1:G1');
$sheet->getStyle('A1')->getFont()->setBold(true);
$sheet->getStyle('A1:G1')->applyFromArray($infoStyle);

$sheet->setCellValue('A2', '• Excel文件需包含: 用户名、姓名、角色代码（表头中不要包含星号*）');
$sheet->mergeCells('A2:G2');
$sheet->getStyle('A2:G2')->applyFromArray($infoStyle);
$sheet->getStyle('A2')->getFont()->getColor()->setRGB('FF0000');

$sheet->setCellValue('A3', '• 角色代码: 0=管理员, 1=教导处, 2=班主任, 3=阅卷老师');
$sheet->mergeCells('A3:G3');
$sheet->getStyle('A3:G3')->applyFromArray($infoStyle);

$sheet->setCellValue('A4', '• 用户名不能重复，若已存在将被跳过并记录');
$sheet->mergeCells('A4:G4');
$sheet->getStyle('A4:G4')->applyFromArray($infoStyle);

$sheet->setCellValue('A5', '• 姓名不能为空，否则将被跳过并记录');
$sheet->mergeCells('A5:G5');
$sheet->getStyle('A5:G5')->applyFromArray($infoStyle);

$sheet->setCellValue('A6', '• 角色代码必须为0、1、2、3之一，否则将被跳过并记录');
$sheet->mergeCells('A6:G6');
$sheet->getStyle('A6:G6')->applyFromArray($infoStyle);

$sheet->setCellValue('A7', '• 角色代码为0或1时，年段代码和学科代码将被自动忽略');
$sheet->mergeCells('A7:G7');
$sheet->getStyle('A7:G7')->applyFromArray($infoStyle);

$sheet->setCellValue('A8', '• 角色代码为2(班主任)时，需提供有效的年段代码，学科代码将被忽略');
$sheet->mergeCells('A8:G8');
$sheet->getStyle('A8:G8')->applyFromArray($infoStyle);

$sheet->setCellValue('A9', '• 角色代码为3(阅卷老师)时，需同时提供有效的年段代码和学科代码');
$sheet->mergeCells('A9:G9');
$sheet->getStyle('A9:G9')->applyFromArray($infoStyle);

$sheet->setCellValue('A10', '• 默认密码为123456');
$sheet->mergeCells('A10:G10');
$sheet->getStyle('A10:G10')->applyFromArray($infoStyle);

$sheet->setCellValue('A11', '• 年段代码、学科代码请见批量创建账户对话框页面');
$sheet->mergeCells('A11:G11');
$sheet->getStyle('A11:G11')->applyFromArray($infoStyle);
$sheet->getStyle('A11')->getFont()->setItalic(true);
$sheet->getStyle('A11')->getFont()->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('555555'));

// 设置表头 - 移到导入说明下面
$sheet->setCellValue('A13', '用户名');
$sheet->setCellValue('B13', '姓名');
$sheet->setCellValue('C13', '角色代码');
$sheet->setCellValue('D13', '年段代码');
$sheet->setCellValue('E13', '学科代码');
$sheet->setCellValue('F13', '说明');
$sheet->setCellValue('G13', '备注');

// 添加示例数据
$sheet->setCellValue('A14', 'teacher1');
$sheet->setCellValue('B14', '张老师');
$sheet->setCellValue('C14', '2');
$sheet->setCellValue('D14', 'G1');
$sheet->setCellValue('E14', '');
$sheet->setCellValue('F14', '班主任示例 - 需要填写年段代码');

$sheet->setCellValue('A15', 'marker1');
$sheet->setCellValue('B15', '李老师');
$sheet->setCellValue('C15', '3');
$sheet->setCellValue('D15', 'G1');
$sheet->setCellValue('E15', 'S1');
$sheet->setCellValue('F15', '阅卷老师示例 - 需要同时填写年段代码和学科代码');

$sheet->setCellValue('A16', 'admin1');
$sheet->setCellValue('B16', '王管理');
$sheet->setCellValue('C16', '0');
$sheet->setCellValue('D16', '');
$sheet->setCellValue('E16', '');
$sheet->setCellValue('F16', '管理员示例 - 无需填写年段或学科代码');

$sheet->setCellValue('A17', 'teaching1');
$sheet->setCellValue('B17', '赵教导');
$sheet->setCellValue('C17', '1');
$sheet->setCellValue('D17', '');
$sheet->setCellValue('E17', '');
$sheet->setCellValue('F17', '教导处示例 - 无需填写年段或学科代码');

// 不再添加其他说明内容，直接结束

// 设置表头样式
$headerStyle = [
    'font' => [
        'bold' => true,
        'color' => ['rgb' => 'FFFFFF'],
    ],
    'fill' => [
        'fillType' => Fill::FILL_SOLID,
        'startColor' => ['rgb' => '4472C4'],
    ],
    'borders' => [
        'allBorders' => [
            'borderStyle' => Border::BORDER_THIN,
            'color' => ['rgb' => '000000'],
        ],
    ],
    'alignment' => [
        'horizontal' => Alignment::HORIZONTAL_CENTER,
        'vertical' => Alignment::VERTICAL_CENTER,
    ],
];

$sheet->getStyle('A13:G13')->applyFromArray($headerStyle);

// 设置必填项的红色星号
$sheet->getStyle('A13')->getFont()->getColor()->setRGB('FF0000');
$sheet->getStyle('B13')->getFont()->getColor()->setRGB('FF0000');
$sheet->getStyle('C13')->getFont()->getColor()->setRGB('FF0000');

// 设置示例数据样式
$dataStyle = [
    'borders' => [
        'allBorders' => [
            'borderStyle' => Border::BORDER_THIN,
            'color' => ['rgb' => '000000'],
        ],
    ],
    'alignment' => [
        'vertical' => Alignment::VERTICAL_CENTER,
    ],
];

$sheet->getStyle('A14:G17')->applyFromArray($dataStyle);

// 设置文件头，指定文件类型和文件名
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="用户批量导入模板.xlsx"');
header('Cache-Control: max-age=0');

// 创建Excel文件
$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit; 