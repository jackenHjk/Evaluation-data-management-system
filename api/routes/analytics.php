<?php
require_once __DIR__ . '/../controllers/ClassAnalyticsController.php';

// 路由处理
$route = $_GET['route'] ?? '';
$controller = new ClassAnalyticsController($pdo);

switch ($route) {
    case 'getAnalytics':
    case 'get':
        echo $controller->getAnalytics();
        break;
    
    case 'generateAnalytics':
        echo $controller->generateAnalytics();
        break;
    
    case 'getSubjectsAnalytics':
        echo $controller->getSubjectsAnalytics();
        break;
    
    case 'getSubjectScores':
        echo $controller->getSubjectScores();
        break;
        
    case 'getStudentChineseMathScores':
        echo $controller->getStudentChineseMathScores();
        break;
        
    default:
        sendError('未知的路由');
} 