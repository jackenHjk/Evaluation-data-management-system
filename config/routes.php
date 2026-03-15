<?php
/**
 * 文件名: config/routes.php
 * 功能描述: 系统路由配置文件
 *
 * 该文件负责:
 * 1. 定义系统所有HTTP请求路由规则
 * 2. 将URL请求映射到相应的控制器和方法
 * 3. 支持不同HTTP方法(GET/POST)的路由配置
 *
 * 路由格式:
 * 'URL路径' => [
 *    'controller' => '控制器名',
 *    'action' => '方法名',
 *    'method' => 'HTTP方法(GET/POST)'
 * ]
 *
 * 或简写形式:
 * 'URL路径' => ['控制器名', '方法名']
 *
 * 关联文件:
 * - index.php: 主入口文件，使用此路由配置解析请求
 * - core/Controller.php: 基础控制器类
 * - controllers/: 存放所有具体控制器类的目录
 */

return [
    // 基础路由
    'settings/school/info' => [
        'controller' => 'Settings',
        'action' => 'getSchoolInfo',
        'method' => 'GET'
    ],
    'settings/school/save' => [
        'controller' => 'Settings',
        'action' => 'saveSchoolInfo',
        'method' => 'POST'
    ],

    // 用户管理相关路由
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

    // 年级相关路由
    'settings/grades' => ['Settings', 'getGrades'],
    'settings/grade/list' => ['GradeController', 'list'],
    'settings/grade/add' => ['GradeController', 'add'],
    'settings/grade/delete' => ['GradeController', 'delete'],
    'settings/grade/check-name' => ['GradeController', 'check_name'],
    'settings/grade/check-code' => ['GradeController', 'check_code'],
    'settings/grade/check_upgradable' => ['GradeController', 'check_upgradable'],
    'settings/grade/upgrade' => ['GradeController', 'upgrade'],

    // 班级相关路由
    'settings/classes' => [
        'controller' => 'Settings',
        'action' => 'getClasses',
        'method' => 'GET'
    ],

    // 项目设置路由
    'settings/project/list' => ['ProjectController', 'getList'],
    'settings/project/get' => ['ProjectController', 'get'],
    'settings/project/add' => ['ProjectController', 'add'],
    'settings/project/update' => ['ProjectController', 'update'],
    'settings/project/delete' => ['ProjectController', 'delete'],
    'settings/project/toggle_status' => ['ProjectController', 'toggleStatus'],
    'settings/project/current' => ['ProjectController', 'getCurrent'],

    // 年级统计相关路由
    'grade/analytics' => ['GradeAnalyticsController', 'analytics'],
    'grade/student_rank' => ['GradeAnalyticsController', 'studentRank'],
    'analytics/class_report' => [
        'controller' => 'Grade',
        'action' => 'classAnalytics',
        'method' => 'GET'
    ],
    'analytics/student_rank' => [
        'controller' => 'Grade',
        'action' => 'studentRank',
        'method' => 'GET'
    ],
    'analytics/report' => [
        'controller' => 'Grade',
        'action' => 'gradeAnalytics',
        'method' => 'GET'
    ],
    'analytics/getSubjectsAnalytics' => [
        'controller' => 'ChineseMathAnalyticsController',
        'action' => 'getSubjectsAnalytics',
        'method' => 'GET'
    ],
    'analytics/getSubjectScores' => [
        'controller' => 'ChineseMathAnalyticsController',
        'action' => 'getSubjectScores',
        'method' => 'GET'
    ],
    'analytics/get' => [
        'controller' => 'ClassAnalyticsController',
        'action' => 'getAnalytics'
    ],
    'analytics/generate' => [
        'controller' => 'ClassAnalyticsController',
        'action' => 'generateAnalytics'
    ],

    // 下载相关路由
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
        'action' => 'getCurrent',
        'method' => 'GET'
    ],
];