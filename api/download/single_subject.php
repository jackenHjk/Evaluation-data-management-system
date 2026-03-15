<?php
/**
 * 文件名: api/download/single_subject.php
 * 功能描述: 单科目成绩导出API接口
 * 
 * 该文件负责:
 * 1. 接收单科目成绩导出请求
 * 2. 验证请求参数及系统环境
 * 3. 调用SingleSubjectExport类生成Excel文件
 * 4. 返回文件下载URL或错误信息
 * 5. 清理过期的临时文件
 * 
 * API调用说明:
 * - 端点: api/index.php?route=download/single_subject
 * - 方法: POST
 * - 必需参数: 
 *   - setting_id: 项目ID
 *   - grade_id: 年级ID
 *   - subject_id: 科目ID
 * - 可选参数:
 *   - include_score: 是否包含分数 (布尔值，默认为true)
 *   - include_level: 是否包含等级 (布尔值，默认为true)
 *   - sort_by: 排序方式，可选值为'score'(按分数)或'number'(按学号)，默认为'number'
 * - 返回: JSON格式，包含文件URL和文件名
 * 
 * 关联文件:
 * - api/download/SingleSubjectExport.php: 单科目导出实现类
 * - core/ExcelExport.php: Excel导出基类
 * - core/Database.php: 数据库操作类
 * - controllers/DownloadController.php: 下载控制器
 * - temp/downloads/: 临时文件存储目录
 */

namespace Api\Download;

use Exception;
use Core\Database;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// 设置错误报告
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/error.log');

// 记录请求开始
error_log("Download request started: " . date('Y-m-d H:i:s'));

try {
    // 检查文件是否存在
    $files = [
        __DIR__ . '/SingleSubjectExport.php' => 'SingleSubjectExport 类文件',
        __DIR__ . '/../../core/ExcelExport.php' => 'ExcelExport 基类文件',
        __DIR__ . '/../../core/Database.php' => '数据库类文件',
        __DIR__ . '/../../vendor/PhpSpreadsheet/autoload.php' => 'PhpSpreadsheet 自动加载文件'
    ];
    foreach ($files as $file => $desc) {
        if (!file_exists($file)) {
            throw new Exception("{$desc}不存在：{$file}");
        }
        error_log("File exists: {$file} ({$desc})");
    }

    // 检查请求方法
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => '不支持的请求方法']);
        exit;
    }

    // 记录请求参数
    error_log("Request parameters: " . json_encode($_POST));

    // 验证必要参数
    $requiredParams = ['setting_id', 'grade_id', 'subject_id'];
    $params = $_POST;

    foreach ($requiredParams as $param) {
        if (!isset($params[$param]) || empty($params[$param])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => "缺少必要参数：{$param}"]);
            exit;
        }
    }

    // 检查文件和目录
    $tempDir = __DIR__ . '/../../temp/downloads/';
    if (!file_exists($tempDir)) {
        error_log("Creating temp directory: {$tempDir}");
        if (!mkdir($tempDir, 0777, true)) {
            throw new Exception("无法创建临时目录：{$tempDir}");
        }
    }
    if (!is_writable($tempDir)) {
        throw new Exception("临时目录没有写入权限：{$tempDir}");
    }
    
    // 设置默认值
    $params['include_score'] = isset($params['include_score']) ? filter_var($params['include_score'], FILTER_VALIDATE_BOOLEAN) : true;
    $params['include_level'] = isset($params['include_level']) ? filter_var($params['include_level'], FILTER_VALIDATE_BOOLEAN) : true;
    $params['sort_by'] = isset($params['sort_by']) && $params['sort_by'] === 'score' ? 'score' : 'number';

    // 加载自动加载器
    require_once __DIR__ . '/../../vendor/PhpSpreadsheet/autoload.php';
    error_log("Autoloader loaded successfully");

    // 加载必要的类文件
    require_once __DIR__ . '/../../core/Database.php';
    require_once __DIR__ . '/../../core/ExcelExport.php';
    require_once __DIR__ . '/SingleSubjectExport.php';
    error_log("Required classes loaded successfully");

    error_log("Creating exporter instance with params: " . json_encode($params));
    
    // 创建导出实例并生成文件
    try {
        $exporter = new SingleSubjectExport($params);
        error_log("SingleSubjectExport instance created successfully");
        
        $result = $exporter->generate();
        error_log("Export completed successfully: " . json_encode($result));
    } catch (Exception $e) {
        error_log("Export process failed: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
        throw $e;
    }

    // 清理过期文件
    try {
        SingleSubjectExport::cleanupTempFiles();
        error_log("Temp files cleanup completed");
    } catch (Exception $e) {
        error_log("Failed to cleanup temp files: " . $e->getMessage());
        // 不抛出异常，因为这不是关键错误
    }

    // 返回结果
    echo json_encode([
        'success' => true,
        'data' => $result
    ]);

} catch (Exception $e) {
    // 记录详细错误信息
    error_log("Export failed with error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => '导出失败：' . $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
} 