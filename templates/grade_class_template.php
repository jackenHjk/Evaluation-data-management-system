<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/config.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

// 创建新的Excel文档
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// 设置列宽
$sheet->getColumnDimension('A')->setWidth(20);
$sheet->getColumnDimension('B')->setWidth(15);
$sheet->getColumnDimension('C')->setWidth(20);
$sheet->getColumnDimension('D')->setWidth(15);

// 设置代码列为文本格式
$sheet->getStyle('B:B')->getNumberFormat()->setFormatCode('@');  // 年级代码列
$sheet->getStyle('D:D')->getNumberFormat()->setFormatCode('@');  // 班级代码列

// 添加说明
$notes = [
    '年级班级导入说明：',
    '1. 年级名称和年级代码在系统中必须唯一',
    '2. 班级名称和班级代码在同一年级内必须唯一',
    '3. 年级代码和班级代码只能包含字母和数字',
    '4. 如果多个班级属于同一年级，第一行填写完整年级信息，后续行的年级名称和年级代码可以留空',
    '5. 已存在的年级将被自动跳过',
    '6. 请参考下方示例数据进行填写'
];

// 添加说明文字
for ($i = 0; $i < count($notes); $i++) {
    $row = $i + 1;
    $sheet->mergeCells("A{$row}:D{$row}");
    $sheet->setCellValue("A{$row}", $notes[$i]);
}

// 设置说明文字样式
$noteStyle = [
    'font' => [
        'color' => ['rgb' => '666666'],
        'size' => 10,
    ]
];
$sheet->getStyle('A1')->getFont()->setBold(true);
$sheet->getStyle('A2:D7')->applyFromArray($noteStyle);

// 设置标题行（从第8行开始）
$titleRow = 8;
$sheet->setCellValue("A{$titleRow}", '年级名称');
$sheet->setCellValue("B{$titleRow}", '年级代码');
$sheet->setCellValue("C{$titleRow}", '班级名称');
$sheet->setCellValue("D{$titleRow}", '班级代码');

// 设置标题行样式
$titleStyle = [
    'font' => [
        'bold' => true,
        'color' => ['rgb' => '000000'],
        'size' => 11,
    ],
    'fill' => [
        'fillType' => Fill::FILL_SOLID,
        'startColor' => ['rgb' => 'E6E6E6'],
    ],
    'alignment' => [
        'horizontal' => Alignment::HORIZONTAL_CENTER,
        'vertical' => Alignment::VERTICAL_CENTER,
    ],
    'borders' => [
        'allBorders' => [
            'borderStyle' => Border::BORDER_THIN,
            'color' => ['rgb' => '000000'],
        ],
    ],
];
$sheet->getStyle("A{$titleRow}:D{$titleRow}")->applyFromArray($titleStyle);

// 添加示例数据
$data = [
    ['一年级', "1", '1班', "01"],
    ['', '', '2班', "02"],
    ['', '', '3班', "03"],
    ['二年级', "2", '1班', "01"],
    ['', '', '2班', "02"],
    ['', '', '3班', "03"],
    ['三年级', "3", '1班', "01"],
    ['', '', '2班', "02"],
    ['', '', '3班', "03"],
];

$row = $titleRow + 1;
foreach ($data as $dataRow) {
    $sheet->setCellValue('A' . $row, $dataRow[0]);
    $sheet->setCellValue('B' . $row, $dataRow[1]);
    $sheet->setCellValue('C' . $row, $dataRow[2]);
    $sheet->setCellValue('D' . $row, $dataRow[3]);
    $row++;
}

// 设置示例数据样式
$dataStyle = [
    'alignment' => [
        'horizontal' => Alignment::HORIZONTAL_CENTER,
        'vertical' => Alignment::VERTICAL_CENTER,
    ],
    'borders' => [
        'allBorders' => [
            'borderStyle' => Border::BORDER_THIN,
            'color' => ['rgb' => '000000'],
        ],
    ],
];
$sheet->getStyle("A" . ($titleRow + 1) . ":D" . ($row - 1))->applyFromArray($dataStyle);

// 设置文档属性
$spreadsheet->getProperties()
    ->setCreator('年级班级管理系统')
    ->setLastModifiedBy('年级班级管理系统')
    ->setTitle('年级班级导入模板')
    ->setSubject('年级班级导入模板')
    ->setDescription('用于批量导入年级和班级信息的Excel模板');

// 输出Excel文件
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="年级班级导入模板.xlsx"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit; 