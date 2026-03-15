// 成绩修改申请相关路由
$router->map('POST', '/score_edit/submit', ['Controllers\\ScoreEditRequestController', 'submitRequest']);
$router->map('GET', '/score_edit/list', ['Controllers\\ScoreEditRequestController', 'getRequestList']);
$router->map('GET', '/score_edit/detail', ['Controllers\\ScoreEditRequestController', 'getRequestDetail']);
$router->map('POST', '/score_edit/approve', ['Controllers\\ScoreEditRequestController', 'approveRequest']);
$router->map('POST', '/score_edit/reject', ['Controllers\\ScoreEditRequestController', 'rejectRequest']);
$router->map('GET', '/score_edit/unread_count', ['Controllers\\ScoreEditRequestController', 'getUnreadCount']);
$router->map('POST', '/score_edit/mark_read', ['Controllers\\ScoreEditRequestController', 'markAsRead']);
$router->map('POST', '/score_edit/mark_all_read', ['Controllers\\ScoreEditRequestController', 'markAllAsRead']);
$router->map('GET', '/score_edit/check_pending', ['Controllers\\ScoreEditRequestController', 'checkPendingRequests']);
$router->map('GET', '/score_edit/pending_details', ['Controllers\\ScoreEditRequestController', 'getPendingDetails']); 