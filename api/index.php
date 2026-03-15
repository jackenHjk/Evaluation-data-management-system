<?php
/**
 * 文件名: api/index.php
 * 功能描述: 成绩统计分析系统API入口文件
 * 
 * 该文件负责:
 * 1. API请求的接收与路由分发
 * 2. 错误和异常处理
 * 3. 自动加载类和控制器
 * 4. 数据库连接初始化
 * 5. 处理所有系统的API调用，包括登录验证、数据查询、统计分析等
 * 
 * API调用方式:
 * - 通过GET参数'route'指定路由
 * - 支持GET、POST、PUT、DELETE等HTTP方法
 * - 返回JSON格式数据
 * 
 * 关联文件:
 * - controllers/: 控制器目录，包含所有业务逻辑控制器
 * - core/: 核心类库目录，包含数据库、日志等基础功能
 * - routes/: 路由规则目录
 * - services/: 业务逻辑服务目录
 * - logs/: API日志存储目录
 * - download/: 下载相关处理目录
 */

// 加载Composer的自动加载器
require_once dirname(__DIR__) . '/vendor/autoload.php';

// 开发环境显示错误
ini_set('display_errors', 0);
error_reporting(E_ALL);

// 设置错误日志文件
ini_set('log_errors', 1);
ini_set('error_log', dirname(__DIR__) . '/logs/error.log');

// 确保日志目录存在并可写
$logDir = dirname(__DIR__) . '/logs';
if (!file_exists($logDir)) {
    mkdir($logDir, 0777, true);
} elseif (!is_writable($logDir)) {
    chmod($logDir, 0777);
}

// 清理旧的错误日志文件（如果超过10MB）
$logFile = $logDir . '/error.log';
if (file_exists($logFile) && filesize($logFile) > 10 * 1024 * 1024) {
    unlink($logFile);
}

// 设置错误处理函数
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    // 忽略 E_DEPRECATED 和 E_USER_DEPRECATED 错误
    if ($errno === E_DEPRECATED || $errno === E_USER_DEPRECATED) {
        return true;
    }
    
    $message = sprintf(
        "[%s] PHP Error [%d]: %s in %s on line %d\nBacktrace:\n%s",
        date('Y-m-d H:i:s'),
        $errno,
        $errstr,
        $errfile,
        $errline,
        print_r(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS), true)
    );
    error_log($message);
    return true;
});

// 设置异常处理函数
set_exception_handler(function($e) {
    $message = sprintf(
        "[%s] Uncaught Exception: %s in %s on line %d\nStack trace:\n%s\nRequest Data:\nGET: %s\nPOST: %s",
        date('Y-m-d H:i:s'),
        $e->getMessage(),
        $e->getFile(),
        $e->getLine(),
        $e->getTraceAsString(),
        print_r($_GET, true),
        print_r($_POST, true)
    );
    error_log($message);
    
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'debug_info' => [
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString()
        ]
    ], JSON_UNESCAPED_UNICODE);
});

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json; charset=utf-8');

// 设置基础路径
define('BASE_PATH', dirname(__DIR__));

// 添加自动加载
spl_autoload_register(function ($class) {
    // 保持原始大小写
    $file = dirname(__DIR__) . '/' . str_replace('\\', '/', $class) . '.php';
    error_log("Trying to load class: $class from file: $file");
    
    // 如果文件存在，直接加载
    if (file_exists($file)) {
        require_once $file;
        error_log("Successfully loaded: $file");
        return;
    }
    
    // 如果文件不存在，尝试在 controllers 目录中查找
    $parts = explode('\\', $class);
    $className = end($parts);
    $controllerFile = dirname(__DIR__) . '/controllers/' . $className . '.php';
    
    if (file_exists($controllerFile)) {
        require_once $controllerFile;
        error_log("Successfully loaded from controllers: $controllerFile");
        return;
    }
    
    error_log("File not found: $file and $controllerFile");
});

// 手动引入核心文件
require_once __DIR__ . '/../core/Controller.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Logger.php';
require_once __DIR__ . '/../core/Cache.php';
require_once __DIR__ . '/../core/ChineseNameSorter.php';

// 初始化数据库连接
$db = core\Database::getInstance();
$GLOBALS['db'] = $db;

// 初始化缓存
$cache = core\Cache::getInstance();
$GLOBALS['cache'] = $cache;

// 启动会话
session_start();

// 手动引入控制器文件
require_once __DIR__ . '/../controllers/InstallController.php';
require_once __DIR__ . '/../controllers/AuthController.php';
require_once __DIR__ . '/../controllers/StudentController.php';
require_once __DIR__ . '/../controllers/SubjectController.php';
require_once __DIR__ . '/../controllers/ScoreController.php';
require_once __DIR__ . '/../controllers/ClassAnalyticsController.php';
require_once __DIR__ . '/../controllers/GradeController.php';
require_once __DIR__ . '/../controllers/ClassController.php';
require_once __DIR__ . '/../controllers/UserController.php';
require_once __DIR__ . '/../controllers/SchoolSettingsController.php';
require_once __DIR__ . '/../controllers/ProjectController.php';
require_once __DIR__ . '/../controllers/GradeAnalyticsController.php';
require_once __DIR__ . '/../controllers/DownloadController.php';
require_once __DIR__ . '/../controllers/ComprehensiveController.php';
require_once __DIR__ . '/../controllers/ScoreEditRequestController.php';
require_once __DIR__ . '/../controllers/LogController.php';
// 设置时区
date_default_timezone_set('Asia/Shanghai');

$route = $_GET['route'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

// 设置路由
$routes = [
    // 安装和认证相关路由
    'install' => [
        'controller' => 'InstallController',
        'action' => 'install'
    ],
    'login' => [
        'controller' => 'AuthController',
        'action' => 'login'
    ],
    'logout' => [
        'controller' => 'AuthController',
        'action' => 'logout'
    ],
    'auth/current_user' => [
        'controller' => 'AuthController',
        'action' => 'getCurrentUser'
    ],
    
    // 年级统计相关路由
    'grade' => [
        'controller' => 'GradeController',
        'action' => 'handleGradeAction'
    ],
    
    // 设置相关路由
    'settings/grade/list' => [
        'controller' => 'GradeController',
        'action' => 'list'
    ],
    'settings/grades' => [
        'controller' => 'GradeController',
        'action' => 'getList'
    ],
    'settings/grade/get' => [
        'controller' => 'GradeController',
        'action' => 'get'
    ],
    'settings/grade/add' => [
        'controller' => 'GradeController',
        'action' => 'add',
        'method' => 'POST'
    ],
    'settings/grade/update' => [
        'controller' => 'GradeController',
        'action' => 'update',
        'method' => 'POST'
    ],
    'settings/grade/delete' => [
        'controller' => 'GradeController',
        'action' => 'delete',
        'method' => 'POST'
    ],
    'settings/grade/check_upgradable' => [
        'controller' => 'GradeController',
        'action' => 'checkUpgradable'
    ],
    'settings/grade/upgrade' => [
        'controller' => 'GradeController',
        'action' => 'upgrade',
        'method' => 'POST'
    ],
    'settings/grade/check_code' => [
        'controller' => 'GradeController',
        'action' => 'checkCode'
    ],
    'settings/grade/check_name' => [
        'controller' => 'GradeController',
        'action' => 'checkName'
    ],
    'settings/import_grade_class' => [
        'controller' => 'ImportController',
        'action' => 'importGradeClass'
    ],
    'settings/classes' => [
        'controller' => 'ClassController',
        'action' => 'getList'
    ],
    'settings/class/get' => [
        'controller' => 'ClassController',
        'action' => 'get'
    ],
    'settings/class/add' => [
        'controller' => 'ClassController',
        'action' => 'add'
    ],
    'settings/class/update' => [
        'controller' => 'ClassController',
        'action' => 'update'
    ],
    'settings/class/delete' => [
        'controller' => 'ClassController',
        'action' => 'delete'
    ],
    'settings/class/check_code' => [
        'controller' => 'ClassController',
        'action' => 'checkCode'
    ],
    'settings/subjects' => [
        'controller' => 'SubjectController',
        'action' => 'getList'
    ],
    'settings/subject/get' => [
        'controller' => 'SubjectController',
        'action' => 'get'
    ],
    'settings/subject/add' => [
        'controller' => 'SubjectController',
        'action' => 'add'
    ],
    'settings/subject/update' => [
        'controller' => 'SubjectController',
        'action' => 'update'
    ],
    'settings/subject/delete' => [
        'controller' => 'SubjectController',
        'action' => 'delete'
    ],
    'settings/subject/get_list' => [
        'controller' => 'SubjectController',
        'action' => 'get_list'
    ],
    'settings/subject/check_name' => [
        'controller' => 'SubjectController',
        'action' => 'checkName'
    ],
    'settings/subject/check_has_scores' => [
        'controller' => 'SubjectController',
        'action' => 'checkHasScores',
        'method' => 'GET'
    ],
    'settings/subject/generate_code' => [
        'controller' => 'SubjectController',
        'action' => 'generateCode',
        'method' => 'GET'
    ],
    // 用户管理相关路由
    'user/list' => [
        'controller' => 'UserController',
        'action' => 'getUserList'
    ],
    'user/get' => [
        'controller' => 'UserController',
        'action' => 'getUser'
    ],
    'user/add' => [
        'controller' => 'UserController',
        'action' => 'add'
    ],
    'user/create' => [
        'controller' => 'UserController',
        'action' => 'add'
    ],
    'user/update' => [
        'controller' => 'UserController',
        'action' => 'updateUser'
    ],
    'user/delete' => [
        'controller' => 'UserController',
        'action' => 'deleteUser'
    ],
    'user/permissions' => [
        'controller' => 'UserController',
        'action' => 'getUserPermissions'
    ],
    'user/toggle_status' => [
        'controller' => 'UserController',
        'action' => 'toggleStatus'
    ],
    'user/update_profile' => [
        'controller' => 'UserController',
        'action' => 'updateProfile',
        'method' => 'POST'
    ],
    'user/check_initial_password' => [
        'controller' => 'UserController',
        'action' => 'checkInitialPassword',
        'method' => 'GET'
    ],
    'user/batch_toggle_status' => [
        'controller' => 'UserController',
        'action' => 'batchToggleStatus',
        'method' => 'POST'
    ],
    'user/batch_delete' => [
        'controller' => 'UserController',
        'action' => 'batchDelete',
        'method' => 'POST'
    ],
    'user/batch_import' => [
        'controller' => 'UserController',
        'action' => 'batchImport',
        'method' => 'POST'
    ],
    'user/permissions/update' => [
        'controller' => 'UserController',
        'action' => 'updatePermissions',
        'method' => 'POST'
    ],
    'user/update_permissions' => [
        'controller' => 'UserController',
        'action' => 'updatePermissions',
        'method' => 'POST'
    ],
    'settings/add_user' => [
        'controller' => 'UserController',
        'action' => 'add',
        'method' => 'POST'
    ],
    'settings/grade/subjects' => [
        'controller' => 'GradeController',
        'action' => 'getGradeSubjects'
    ],
    'settings/school/info' => [
        'controller' => 'SchoolSettingsController',
        'action' => 'getInfo'
    ],
    'settings/school/save' => [
        'controller' => 'SchoolSettingsController',
        'action' => 'save'
    ],
    'settings/project/list' => [
        'controller' => 'ProjectController',
        'action' => 'getList'
    ],
    'settings/project/get' => [
        'controller' => 'ProjectController',
        'action' => 'get'
    ],
    'settings/project/add' => [
        'controller' => 'ProjectController',
        'action' => 'add'
    ],
    'settings/project/update' => [
        'controller' => 'ProjectController',
        'action' => 'update'
    ],
    'settings/project/delete' => [
        'controller' => 'ProjectController',
        'action' => 'delete',
        'method' => 'POST'
    ],
    'settings/project/toggle_status' => [
        'controller' => 'ProjectController',
        'action' => 'toggleStatus',
        'method' => 'POST'
    ],
    'student/get_list' => [
        'controller' => 'StudentController',
        'action' => 'getStudents'
    ],
    'student/students' => [
        'controller' => 'StudentController',
        'action' => 'getStudents'
    ],
    'student/add' => [
        'controller' => 'StudentController',
        'action' => 'add'
    ],
    'student/import_students' => [
        'controller' => 'StudentController',
        'action' => 'import_students'
    ],
    'student/delete' => [
        'controller' => 'StudentController',
        'action' => 'delete'
    ],
    'student/batchDelete' => [
        'controller' => 'StudentController',
        'action' => 'batchDelete'
    ],
    'student/update' => [
        'controller' => 'StudentController',
        'action' => 'updateStudent'
    ],
    'student/update_name' => [
        'controller' => 'StudentController',
        'action' => 'updateName'
    ],
    'student/check_names' => [
        'controller' => 'StudentController',
        'action' => 'checkNames'
    ],
    'student/reorder' => [
        'controller' => 'StudentController',
        'action' => 'reorder',
        'method' => 'POST'
    ],
    // 年级相关路由
    'grade/getList' => [
        'controller' => 'GradeController',
        'action' => 'getList'
    ],
    'grade/getAllGrades' => [
        'controller' => 'GradeController',
        'action' => 'getAllGrades'
    ],
    'grade/get' => [
        'controller' => 'GradeController',
        'action' => 'get'
    ],
    // 班级相关路由
    'class/getList' => [
        'controller' => 'ClassController',
        'action' => 'getList'
    ],
    'class/get' => [
        'controller' => 'ClassController',
        'action' => 'get'
    ],
    'class/add' => [
        'controller' => 'ClassController',
        'action' => 'add'
    ],
    'class/update' => [
        'controller' => 'ClassController',
        'action' => 'update'
    ],
    'subject/grade_list' => [
        'controller' => 'SubjectController',
        'action' => 'getGradeSubjects'
    ],
    'subject/getGradeSubjects' => [
        'controller' => 'SubjectController',
        'action' => 'getGradeSubjects'
    ],
    // 成绩相关路由
    'score/teacher_subjects' => [
        'controller' => 'ScoreController',
        'action' => 'getTeacherSubjects'
    ],
    'score/teacher_classes' => [
        'controller' => 'ScoreController',
        'action' => 'getTeacherClasses'
    ],
    'score/student_scores' => [
        'controller' => 'ScoreController',
        'action' => 'getStudentScores'
    ],
    'score/save_score' => [
        'controller' => 'ScoreController',
        'action' => 'saveScore'
    ],
    'score/save_absent' => [
        'controller' => 'ScoreController',
        'action' => 'saveAbsent'
    ],
    'score/check_all_scores' => [
        'controller' => 'ScoreController',
        'action' => 'checkAllScores'
    ],
    'score/check_all_entered' => [
        'controller' => 'ScoreController',
        'action' => 'checkAllEntered'
    ],
    
    // 统计分析相关路由
    'analytics/generate' => [
        'controller' => 'ClassAnalyticsController',
        'action' => 'generateAnalytics'
    ],
    'analytics/get' => [
        'controller' => 'ClassAnalyticsController',
        'action' => 'getAnalytics'
    ],
    'analytics/getSubjectsAnalytics' => [
        'controller' => 'ClassAnalyticsController',
        'action' => 'getSubjectsAnalytics'
    ],
    'analytics/getSubjectScores' => [
        'controller' => 'ClassAnalyticsController',
        'action' => 'getSubjectScores'
    ],
    'analytics/getStudentChineseMathScores' => [
        'controller' => 'ClassAnalyticsController',
        'action' => 'getStudentChineseMathScores'
    ],
    // 权限检查路由
    'check_permission' => [
        'controller' => 'AuthController',
        'action' => 'checkModuleAccess'
    ],
    'Subject/add' => [
        'controller' => 'SubjectController',
        'action' => 'add',
        'method' => 'POST'
    ],
    // 学生导入相关路由
    'student/import_file' => [
        'controller' => 'StudentController',
        'action' => 'importFile',
        'method' => 'POST'
    ],
    // 项目相关路由
    'project/current' => [
        'controller' => 'ProjectController',
        'action' => 'getCurrent'
    ],
    'project/list' => [
        'controller' => 'ProjectController',
        'action' => 'getList'
    ],
    'project/add' => [
        'controller' => 'ProjectController',
        'action' => 'add',
        'method' => 'POST'
    ],
    'project/update' => [
        'controller' => 'ProjectController',
        'action' => 'update',
        'method' => 'POST'
    ],
    'project/delete' => [
        'controller' => 'ProjectController',
        'action' => 'delete',
        'method' => 'POST'
    ],
    'project/toggle_status' => [
        'controller' => 'ProjectController',
        'action' => 'toggleStatus',
        'method' => 'POST'
    ],
    // 年级统计分析相关路由
    'grade_analytics/generate' => [
        'controller' => 'GradeAnalyticsController',
        'action' => 'generateAnalytics'
    ],
    'grade_analytics/student_rank' => [
        'controller' => 'GradeAnalyticsController',
        'action' => 'getStudentRanks'
    ],
    // 添加下载相关路由
    'download/single_subject' => [
        'controller' => 'DownloadController',
        'action' => 'singleSubject',
        'method' => 'POST'
    ],
    'download/multi_subject' => [
        'controller' => 'DownloadController',
        'action' => 'multiSubject',
        'method' => 'POST'
    ],
    'download/chinese_math' => [
        'controller' => 'DownloadController',
        'action' => 'chineseMath',
        'method' => 'POST'
    ],
    'settings/current' => [
        'controller' => 'ProjectController',
        'action' => 'getCurrent'
    ],
    // 日志管理路由
    'log/getList' => [
        'controller' => 'LogController',
        'action' => 'getList'
    ],
    'log/getUsers' => [
        'controller' => 'LogController',
        'action' => 'getUsers'
    ],
    'log/cleanOldLogs' => [
        'controller' => 'LogController',
        'action' => 'cleanOldLogs'
    ],
    'log/add' => [
        'controller' => 'LogController',
        'action' => 'add',
        'method' => 'POST'
    ],
    // 全科统计分析相关路由
    'comprehensive/getClassAnalytics' => [
        'controller' => 'ComprehensiveController',
        'action' => 'getClassAnalytics',
        'method' => 'POST'
    ],
    'comprehensive/getExcellentGoodSummary' => [
        'controller' => 'ComprehensiveController',
        'action' => 'getExcellentGoodSummary',
        'method' => 'POST'
    ],
    'comprehensive/getStudentList' => [
        'controller' => 'ComprehensiveController',
        'action' => 'getStudentList',
        'method' => 'POST'
    ],
    'subject/getList' => [
        'controller' => 'SubjectController',
        'action' => 'getList'
    ],
    
    // 成绩修改申请相关路由
    'score_edit/submit' => [
        'controller' => 'ScoreEditRequestController',
        'action' => 'submitRequest',
        'method' => 'POST'
    ],
    'score_edit/list' => [
        'controller' => 'ScoreEditRequestController',
        'action' => 'getRequestList'
    ],
    'score_edit/detail' => [
        'controller' => 'ScoreEditRequestController',
        'action' => 'getRequestDetail'
    ],
    'score_edit/approve' => [
        'controller' => 'ScoreEditRequestController',
        'action' => 'approveRequest',
        'method' => 'POST'
    ],
    'score_edit/reject' => [
        'controller' => 'ScoreEditRequestController',
        'action' => 'rejectRequest',
        'method' => 'POST'
    ],
    'score_edit/unread_count' => [
        'controller' => 'ScoreEditRequestController',
        'action' => 'getUnreadCount'
    ],
    'score_edit/mark_read' => [
        'controller' => 'ScoreEditRequestController',
        'action' => 'markAsRead',
        'method' => 'POST'
    ],
    'score_edit/mark_all_read' => [
        'controller' => 'ScoreEditRequestController',
        'action' => 'markAllAsRead',
        'method' => 'POST'
    ],
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
];

// 检查路由是否存在
if ($route === 'auto_login') {
    // 直接包含auto_login.php文件处理自动登录
    require_once __DIR__ . '/routes/auto_login.php';
    exit;
}

// 处理心跳请求
if ($route === 'heartbeat') {
    // 直接包含heartbeat.php文件处理心跳请求
    require_once __DIR__ . '/routes/heartbeat.php';
    exit;
}

if (!isset($routes[$route])) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(404);
    echo json_encode([
        'success' => false,
        'error' => '路由不存在'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 在所有需要登录的API路由中调用验证
if (!in_array($_GET['route'], ['login', 'logout'])) {
    // 初始化认证控制器
    $authController = new Controllers\AuthController();
    
    // 验证会话
    if (!$authController->validateSession()) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => '登录已失效，请重新登录',
            'code' => 'SESSION_EXPIRED'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

// 加载控制器
$controllerName = "Controllers\\" . $routes[$route]['controller'];
$actionName = $routes[$route]['action'];

try {
    header('Content-Type: application/json; charset=utf-8');
    $controller = new $controllerName();
    $result = $controller->$actionName();
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
