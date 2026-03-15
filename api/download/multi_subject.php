<?php
/**
 * 文件名: api/download/multi_subject.php
 * 功能描述: 多科目成绩导出API接口
 * 
 * 该文件负责:
 * 1. 接收多科目成绩导出请求
 * 2. 验证请求参数
 * 3. 调用MultiSubjectExport类生成Excel文件
 * 4. 返回文件下载URL
 * 5. 清理过期的临时文件
 * 
 * API调用说明:
 * - 端点: api/index.php?route=download/multi_subject
 * - 方法: POST
 * - 必需参数: 
 *   - project_id: 项目ID
 *   - grade_id: 年级ID
 *   - subject_ids: 科目ID数组
 * - 可选参数:
 *   - download_type: 下载类型，可选值为'score'(分数)或'level'(等级)，默认为'score'
 *   - sort_by: 排序方式，可选值为'score'(按总分)或'number'(按学号)，默认为'number'
 * - 返回: JSON格式，包含文件URL和文件名
 * 
 * 关联文件:
 * - api/download/MultiSubjectExport.php: 多科目导出实现类
 * - core/ExcelExport.php: Excel导出基类
 * - core/Database.php: 数据库操作类
 * - controllers/DownloadController.php: 下载控制器
 * - temp/downloads/: 临时文件存储目录
 */

require_once __DIR__ . '/MultiSubjectExport.php';

// 检查请求方法
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => '不支持的请求方法']);
    exit;
}

// 验证必要参数
$requiredParams = ['project_id', 'grade_id', 'subject_ids'];
$params = $_POST;

foreach ($requiredParams as $param) {
    if (!isset($params[$param]) || empty($params[$param])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => "缺少必要参数：{$param}"]);
        exit;
    }
}

// 验证科目ID数组
if (!is_array($params['subject_ids'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => "科目ID必须是数组"]);
    exit;
}

try {
    // 设置默认值
    $params['download_type'] = isset($params['download_type']) && $params['download_type'] === 'level' ? 'level' : 'score';
    $params['sort_by'] = isset($params['sort_by']) && $params['sort_by'] === 'score' ? 'score' : 'number';

    // 创建导出实例并生成文件
    $exporter = new MultiSubjectExport($params);
    $result = $exporter->generate();

    // 清理过期文件
    MultiSubjectExport::cleanupTempFiles();

    // 返回结果
    echo json_encode([
        'success' => true,
        'data' => $result
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => '导出失败：' . $e->getMessage()
    ]);
} 