<?php
/**
 * 文件名: core/ExcelExport.php
 * 功能描述: Excel文件导出基础类
 * 
 * 该类负责:
 * 1. 创建和管理Excel文件
 * 2. 设置Excel文件样式（标题、表头、数据样式）
 * 3. 配置打印设置
 * 4. 保存Excel文件到临时目录
 * 
 * 此类为Excel导出提供基础功能，具体业务导出类应继承此类并实现具体导出逻辑。
 * 使用PhpSpreadsheet库进行Excel文件生成。
 * 
 * API调用说明:
 * - 不直接通过API调用，由controllers/DownloadController.php调用
 * 
 * 关联文件:
 * - temp/downloads/: 临时文件存储目录
 * - controllers/DownloadController.php: 下载控制器，调用Excel导出功能
 * - vendor/phpoffice/phpspreadsheet: PhpSpreadsheet库
 */

namespace Core;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;

class ExcelExport {
    protected $spreadsheet;
    protected $tempDir;

    public function __construct() {
        $this->spreadsheet = new Spreadsheet();
        $this->tempDir = __DIR__ . '/../temp/downloads/';
        
        // 创建临时目录
        if (!file_exists($this->tempDir)) {
            if (!mkdir($this->tempDir, 0777, true)) {
                throw new \Exception("无法创建临时目录：{$this->tempDir}");
            }
        }
        
        if (!is_writable($this->tempDir)) {
            throw new \Exception("临时目录没有写入权限：{$this->tempDir}");
        }
    }

    /**
     * 设置标题
     */
    protected function setTitle($title, $range) {
        $sheet = $this->spreadsheet->getActiveSheet();
        $sheet->mergeCells($range);
        $sheet->setCellValue(explode(':', $range)[0], $title);
        $sheet->getStyle($range)->applyFromArray([
            'font' => [
                'bold' => true,
                'size' => 14
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER
            ]
        ]);
    }

    /**
     * 设置表头
     */
    protected function setHeaders($headers, $row) {
        $sheet = $this->spreadsheet->getActiveSheet();
        $col = 'A';
        foreach ($headers as $header) {
            $sheet->setCellValue($col . $row, $header);
            $sheet->getColumnDimension($col)->setAutoSize(true);
            $col++;
        }

        // 设置表头样式
        $lastCol = chr(ord($col) - 1);
        $sheet->getStyle("A{$row}:{$lastCol}{$row}")->applyFromArray([
            'font' => [
                'bold' => true
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN
                ]
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => [
                    'rgb' => 'F2F2F2'
                ]
            ]
        ]);
    }

    /**
     * 设置数据样式
     */
    protected function setDataStyle($range) {
        $this->spreadsheet->getActiveSheet()->getStyle($range)->applyFromArray([
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN
                ]
            ]
        ]);
    }

    /**
     * 设置打印设置
     */
    protected function setPageSetup() {
        $sheet = $this->spreadsheet->getActiveSheet();
        $sheet->getPageSetup()
            ->setOrientation(PageSetup::ORIENTATION_PORTRAIT)
            ->setPaperSize(PageSetup::PAPERSIZE_A4)
            ->setFitToWidth(1)
            ->setFitToHeight(0);
        
        $sheet->getPageMargins()
            ->setTop(0.7)
            ->setRight(0.7)
            ->setLeft(0.7)
            ->setBottom(0.7);
    }

    /**
     * 保存文件
     */
    protected function save() {
        $filename = uniqid('export_') . '.xlsx';
        $filepath = $this->tempDir . $filename;
        
        $writer = new Xlsx($this->spreadsheet);
        $writer->save($filepath);

        return [
            'file_url' => 'temp/downloads/' . $filename,
            'filename' => $filename
        ];
    }
} 