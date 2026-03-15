<?php
require_once '../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

// 创建临时文件路径
$tempDir = __DIR__ . '/../temp';
if (!file_exists($tempDir)) {
    mkdir($tempDir, 0777, true);
}
$tempFile = $tempDir . '/student_template_' . uniqid() . '.xlsx';

try {
    // 创建新的 Spreadsheet 对象
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    
    // 基本设置
    $sheet->setTitle('学生导入模板');
    
    // 设置列宽
    $sheet->getColumnDimension('A')->setWidth(20);
    $sheet->getColumnDimension('B')->setWidth(20);
    
    // 设置默认样式（所有单元格居中对齐）
    $spreadsheet->getDefaultStyle()->getAlignment()
        ->setHorizontal(Alignment::HORIZONTAL_CENTER)
        ->setVertical(Alignment::VERTICAL_CENTER);
    
    // 设置表头
    $sheet->setCellValue('A1', '班级代码');
    $sheet->setCellValue('B1', '学生姓名');
    
    // 设置表头样式
    $headerStyle = [
        'font' => [
            'bold' => true,
            'size' => 11
        ],
        'fill' => [
            'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
            'startColor' => [
                'rgb' => 'E0E0E0',
            ],
        ],
        'borders' => [
            'allBorders' => [
                'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                'color' => ['rgb' => '000000'],
            ],
        ],
    ];
    $sheet->getStyle('A1:B1')->applyFromArray($headerStyle);
    
    // 添加示例数据
    $data = [
        ['1', '张三'],
        ['1', '李四'],
    ];
    $sheet->fromArray($data, null, 'A2');
    
    // 设置数据区域样式
    $dataStyle = [
        'borders' => [
            'allBorders' => [
                'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                'color' => ['rgb' => '000000'],
            ],
        ],
    ];
    $sheet->getStyle('A2:B3')->applyFromArray($dataStyle);
    
    // 冻结表头
    $sheet->freezePane('A2');
    
    // 设置行高
    $sheet->getDefaultRowDimension()->setRowHeight(20);
    
    // 保存到临时文件
    $writer = new Xlsx($spreadsheet);
    $writer->save($tempFile);
    
    // 清理内存
    $spreadsheet->disconnectWorksheets();
    unset($spreadsheet);
    
    // 发送文件
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="学生导入模板.xlsx"');
    header('Content-Length: ' . filesize($tempFile));
    header('Cache-Control: max-age=0');
    
    readfile($tempFile);
    
} catch (Exception $e) {
    error_log('Template generation error: ' . $e->getMessage());
    header('HTTP/1.1 500 Internal Server Error');
    echo "生成模板文件时出错";
} finally {
    // 删除临时文件
    if (file_exists($tempFile)) {
        unlink($tempFile);
    }
}
?> 