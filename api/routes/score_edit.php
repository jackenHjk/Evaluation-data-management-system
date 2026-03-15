<?php
require_once __DIR__ . '/../../controllers/ScoreEditRequestController.php';

// 路由处理
$route = $_GET['route'] ?? '';
$controller = new Controllers\ScoreEditRequestController($pdo);

switch ($route) {
    case 'submit':
        echo $controller->submitRequest();
        break;
    
    case 'list':
        echo $controller->getRequestList();
        break;
    
    case 'detail':
        echo $controller->getRequestDetail();
        break;
    
    case 'approve':
        echo $controller->approveRequest();
        break;
    
    case 'reject':
        echo $controller->rejectRequest();
        break;
    
    case 'mark_as_read':
        echo $controller->markAsRead();
        break;
    
    case 'mark_all_as_read':
        echo $controller->markAllAsRead();
        break;
    
    case 'check_pending':
        echo $controller->checkPendingRequests();
        break;
    
    case 'get_pending_details':
        echo $controller->getPendingDetails();
        break;
    
    case 'check_pending_by_grade':
        echo $controller->checkPendingByGrade();
        break;
    
    default:
        echo json_encode([
            'success' => false,
            'error' => '未知的路由'
        ]);
}

// 注意：以下路由需要在api/index.php中添加才能生效
/*
// 需要在api/index.php的路由数组中添加以下路由:
'score_edit/check_pending_by_grade' => [
    'controller' => 'ScoreEditRequestController',
    'action' => 'checkPendingByGrade'
],
'score_edit/batch_approve' => [
    'controller' => 'ScoreEditRequestController',
    'action' => 'batchApprove',
    'method' => 'POST'
],
'score_edit/batch_reject' => [
    'controller' => 'ScoreEditRequestController',
    'action' => 'batchReject',
    'method' => 'POST'
]
*/ 