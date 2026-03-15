<?php
/**
 * 学科数据检查工具
 * 用于检查数据库中学科数据的状态和成绩拆分信息
 */

// 加载配置文件
$config = require_once __DIR__ . '/../config/config.php';

try {
    // 连接数据库
    $dsn = "mysql:host={$config['db']['host']};dbname={$config['db']['dbname']};charset={$config['db']['charset']}";
    $pdo = new PDO($dsn, $config['db']['username'], $config['db']['password']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 检查subjects表结构
    echo "【学科表结构】\n";
    $columns = $pdo->query("SHOW COLUMNS FROM subjects");
    foreach ($columns as $column) {
        echo "{$column['Field']} - {$column['Type']} - {$column['Null']} - {$column['Default']}\n";
    }
    
    echo "\n【所有学科数据】\n";
    $stmt = $pdo->query("
        SELECT s.id, s.subject_name, s.subject_code, s.full_score, 
               s.is_split, s.split_name_1, s.split_name_2, s.split_score_1, s.split_score_2
        FROM subjects s
        ORDER BY s.id ASC
    ");
    
    if ($stmt->rowCount() === 0) {
        echo "未找到任何学科数据\n";
    } else {
        echo "ID | 学科名称 | 学科代码 | 满分 | 是否拆分 | 拆分1名称 | 拆分1分值 | 拆分2名称 | 拆分2分值\n";
        echo "------------------------------------------------------\n";
        while ($subject = $stmt->fetch(PDO::FETCH_ASSOC)) {
            echo "{$subject['id']} | {$subject['subject_name']} | {$subject['subject_code']} | {$subject['full_score']} | ";
            echo ($subject['is_split'] ? '是' : '否') . " | ";
            echo "{$subject['split_name_1']} | {$subject['split_score_1']} | {$subject['split_name_2']} | {$subject['split_score_2']}\n";
        }
    }

    // 检查API处理学科的代码
    echo "\n【检查API相关代码】\n";
    $apiFiles = [
        '../api/routes/settings.php',
        '../controllers/SubjectController.php'
    ];

    foreach ($apiFiles as $file) {
        if (file_exists($file)) {
            echo "文件 {$file} 存在，内容摘要：\n";
            $content = file_get_contents($file);
            
            // 检查是否处理了拆分成绩相关字段
            if (strpos($content, 'is_split') !== false) {
                echo "- 包含is_split字段处理\n";
            } else {
                echo "- 未包含is_split字段处理\n";
            }
            
            if (strpos($content, 'split_name_1') !== false) {
                echo "- 包含split_name_1字段处理\n";
            } else {
                echo "- 未包含split_name_1字段处理\n";
            }
            
            if (strpos($content, 'split_score_1') !== false) {
                echo "- 包含split_score_1字段处理\n";
            } else {
                echo "- 未包含split_score_1字段处理\n";
            }
        } else {
            echo "文件 {$file} 不存在\n";
        }
    }

} catch (PDOException $e) {
    echo "数据库操作失败：" . $e->getMessage() . "\n";
    exit(1);
} 