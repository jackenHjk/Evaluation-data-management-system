<?php
/**
 * 文件名: modules/class_settings.php
 * 功能描述: 班级管理设置模块
 * 
 * 该文件负责:
 * 1. 提供班级管理的用户界面
 * 2. 支持班级的增删改查操作
 * 3. 班级与年级的关联管理
 * 4. 班级信息批量导入功能
 * 5. 班级数据校验与错误处理
 * 
 * 班级管理页面提供直观的班级列表展示，支持按年级筛选，
 * 可以进行单个班级的添加、编辑和删除，也支持通过Excel导入批量创建班级。
 * 同时提供班级人数、班主任等信息的管理。
 * 
 * 关联文件:
 * - controllers/ClassController.php: 班级控制器
 * - controllers/GradeController.php: 年级控制器
 * - api/index.php: API入口
 * - assets/js/class-settings.js: 班级管理前端脚本
 * - assets/js/settings.js: 通用设置脚本
 */

// 确保包含必要的配置文件
require_once __DIR__ . '/../config/config.php';

// 启动会话
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 检查用户是否登录且是管理员或教导处
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'teaching'])) {
    // 使用绝对路径包含错误页面
    $errorFile = __DIR__ . '/../error/403.php';
    if (file_exists($errorFile)) {
        include $errorFile;
    } else {
        // 如果错误页面不存在，显示简单的错误消息
        header('HTTP/1.1 403 Forbidden');
        echo '访问被拒绝：需要管理员或教导处权限';
    }
    exit;
}

// 设置错误报告
error_reporting(E_ALL);
ini_set('display_errors', 1);
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>年级班级管理</title>
    <!-- CSS 依赖 -->
    <link href="../assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/sweetalert2.min.css" rel="stylesheet">
    <link href="../assets/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/common.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/modal.css">
    <style>
        /* 整体布局优化 */
        .container-fluid {
            max-width: 1600px;
            padding: 0 15px;
            margin: 0 auto;
        }

        /* 卡片容器样式 */
        .card {
            background: transparent;
            border: none;
            box-shadow: none;
        }

        .card-body {
            padding: 0;
        }

        /* 年级网格布局 */
        #gradeList {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 15px;
            padding: 15px;
        }

        /* 年级卡片样式 */
        .grade-section {
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            overflow: hidden;
            height: fit-content;
            border: 1px solid rgba(0,0,0,0.05);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            margin: 0;
        }

        .grade-section:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }

        /* 为不同年级添加不同的渐变背景色 */
        .grade-section:nth-child(4n+1) {
            background: linear-gradient(to bottom right, #f0f9ff, #e0f2fe);
        }
        .grade-section:nth-child(4n+1) .grade-header {
            background: linear-gradient(to right, #f0f9ff, #e0f2fe);
        }

        .grade-section:nth-child(4n+2) {
            background: linear-gradient(to bottom right, #f0fdf4, #dcfce7);
        }
        .grade-section:nth-child(4n+2) .grade-header {
            background: linear-gradient(to right, #f0fdf4, #dcfce7);
        }

        .grade-section:nth-child(4n+3) {
            background: linear-gradient(to bottom right, #fef2f2, #fee2e2);
        }
        .grade-section:nth-child(4n+3) .grade-header {
            background: linear-gradient(to right, #fef2f2, #fee2e2);
        }

        .grade-section:nth-child(4n+4) {
            background: linear-gradient(to bottom right, #f5f3ff, #ede9fe);
        }
        .grade-section:nth-child(4n+4) .grade-header {
            background: linear-gradient(to right, #f5f3ff, #ede9fe);
        }

        /* 年级标题样式 */
        .grade-header {
            padding: 10px 15px;
            border-bottom: 1px solid rgba(0,0,0,0.05);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .grade-title {
            font-size: 0.95rem;
            font-weight: 600;
            color: #1D1D1F;
            margin: 0;
        }

        /* 表格样式优化 */
        .table-container {
            padding: 0;
            margin: 0;
            overflow-x: auto;
        }

        .table {
            margin: 0;
            font-size: 0.85rem;
            width: 100%;
        }

        .table th {
            padding: 6px 8px;
            font-weight: 600;
            color: #1D1D1F;
            background: transparent;
            border-bottom: 1px solid rgba(0,0,0,0.05);
            text-align: center;
        }

        .table td {
            padding: 6px 8px;
            vertical-align: middle;
            border-bottom: 1px solid rgba(0,0,0,0.05);
            text-align: center;
        }

        /* 按钮组样式优化 */
        .btn-group {
            display: flex;
            gap: 8px;
        }

        .btn-group .btn {
            padding: 4px 8px;
            font-size: 0.85rem;
            border-radius: 6px;
            transition: all 0.2s ease;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .btn-group .btn:hover {
            transform: translateY(-1px);
        }

        /* 操作按钮图标样式 */
        .action-btn {
            width: 28px;
            height: 28px;
            padding: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            border: none;
            background: transparent;
            color: #666;
            transition: all 0.2s ease;
        }

        .action-btn:hover {
            transform: translateY(-1px);
        }

        .action-btn.edit-btn {
            color: #0284c7;
        }

        .action-btn.delete-btn {
            color: #dc2626;
        }

        /* 响应式布局调整 */
        @media (max-width: 1400px) {
            #gradeList {
                grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            }
        }

        @media (max-width: 1200px) {
            #gradeList {
                grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            }
        }

        @media (max-width: 768px) {
            #gradeList {
                grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
                gap: 10px;
                padding: 10px;
            }
        }

        /* 表格列宽调整 */
        .table th:nth-child(1),
        .table td:nth-child(1) {
            width: 30%;
        }

        .table th:nth-child(2),
        .table td:nth-child(2) {
            width: 30%;
        }

        .table th:last-child,
        .table td:last-child {
            width: 40%;
            min-width: 100px;
        }

        /* 工具栏样式 */
        .card-header {
            background: #fff;
            padding: 15px;
            border-radius: 0;
            margin-bottom: 0;
            border: none;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }

        /* 标题样式 */
        .card-header h5 {
            font-size: 1.1rem;
            font-weight: 600;
            color: #2c3e50;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        /* 按钮组样式优化 */
        .card-header .btn-group {
            display: flex;
            gap: 10px;
        }

        .card-header .btn {
            padding: 8px 16px;
            font-size: 0.9rem;
            min-width: 120px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            border-radius: 6px;
            transition: all 0.2s ease;
        }

        .card-header .btn i {
            font-size: 0.9rem;
        }

        .card-header .btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        /* 调整按钮颜色 */
        .card-header .btn-primary {
            background: #0284c7;
            border-color: #0284c7;
        }

        .card-header .btn-success {
            background: #059669;
            border-color: #059669;
        }

        .card-header .btn-warning {
            background: #d97706;
            border-color: #d97706;
            color: #fff;
        }

        /* 添加按钮禁用时的样式 */
        .btn:disabled {
            cursor: not-allowed;
            opacity: 0.6;
            position: relative;
            z-index: 2;
        }

        /* 添加提示框样式 */
        [data-bs-toggle="tooltip"] {
            position: relative;
        }
        
        /* 按钮包装器样式 */
        .button-wrapper {
            display: inline-flex;
            position: relative;
        }

        /* tooltip触发器样式 */
        .tooltip-trigger {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            z-index: 3;
            cursor: help;
            background: transparent;
            pointer-events: none;  /* 默认不接收鼠标事件 */
        }

        /* 当按钮禁用时，tooltip触发器才接收鼠标事件 */
        .btn:disabled + .tooltip-trigger {
            pointer-events: auto;
        }

        /* 自定义tooltip样式 */
        .tooltip.bs-tooltip-top .tooltip-inner {
            background-color: #fff;
            color: #d97706;
            padding: 6px 12px;
            font-size: 13px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            border: 1px solid #fde68a;
            white-space: nowrap;
            max-width: none;
        }

        .tooltip.bs-tooltip-top .tooltip-arrow::before {
            border-top-color: #fff;
        }

        /* 移除原有的升级提醒样式 */
        #upgradeAlert {
            display: none !important;
        }

        /* 动画效果 */
        .grade-section {
            animation: fadeIn 0.3s ease forwards;
            animation-delay: calc(var(--animation-order) * 0.1s);
            opacity: 0;
        }

        @keyframes fadeIn {
            from { 
                opacity: 0; 
                transform: translateY(10px); 
            }
            to { 
                opacity: 1; 
                transform: translateY(0); 
            }
        }

        /* 自定义下拉框样式 */
        .custom-select-wrapper {
            position: relative;
            width: inherit;
            margin-bottom: 0;
        }

        .custom-select-trigger {
            background: linear-gradient(to bottom, #ffffff, #f8f9fa);
            border: 1px solid rgba(0,0,0,0.1);
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            padding: 0.625rem 1rem;
            border-radius: 10px;
            font-size: 1.1rem;
            color: #2c3e50;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            min-height: 42px;
            width: 100%;
        }

        .custom-select-trigger::after {
            content: '▼';
            font-size: 0.8em;
            color: #6c757d;
            transition: transform 0.3s ease;
        }

        .custom-select-trigger:hover {
            border-color: #86b7fe;
            box-shadow: 0 2px 8px rgba(13, 110, 253, 0.1);
        }

        .custom-select-wrapper.open .custom-select-trigger::after {
            transform: rotate(180deg);
        }

        .custom-options {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: #fff;
            border: 1px solid rgba(0,0,0,0.1);
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            margin-top: 0.25rem;
            max-height: 280px;
            overflow-y: auto;
            z-index: 1000;
            display: none;
        }

        .custom-select-wrapper.open .custom-options {
            display: block;
        }

        .custom-option {
            padding: 0.625rem 1rem;
            color: #2c3e50;
            border-bottom: 1px solid rgba(0,0,0,0.05);
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .custom-option:last-child {
            border-bottom: none;
        }

        .custom-option:hover {
            background: linear-gradient(to right, #f0f7ff, transparent);
            padding-left: 1.25rem;
        }

        .custom-option.selected {
            background: linear-gradient(to right, #e7f1ff, transparent);
            color: #0d6efd;
            font-weight: 500;
            padding-left: 1.25rem;
        }

        /* 隐藏原生select */
        .form-select {
            display: none;
        }

        /* 班级特定的自定义样式 */
        .grade-section {
            animation: fadeIn 0.3s ease forwards;
            animation-delay: calc(var(--animation-order) * 0.1s);
            opacity: 0;
        }

        @keyframes fadeIn {
            from { 
                opacity: 0; 
                transform: translateY(10px); 
            }
            to { 
                opacity: 1; 
                transform: translateY(0); 
            }
        }

        .class-card {
            transition: all 0.3s ease;
        }

        .class-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        .class-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .class-status {
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.875rem;
        }

        .status-active {
            background-color: #e7f5ea;
            color: #198754;
        }

        .status-inactive {
            background-color: #f8f9fa;
            color: #6c757d;
        }

        /* 工具提示样式 */
        .tooltip.bs-tooltip-top .tooltip-inner {
            background-color: #fff;
            color: #d97706;
            padding: 6px 12px;
            font-size: 13px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            border: 1px solid #fde68a;
            white-space: nowrap;
            max-width: none;
        }

        .tooltip.bs-tooltip-top .tooltip-arrow::before {
            border-top-color: #fff;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="fas fa-chalkboard text-primary"></i>
                            年级班级管理
                        </h5>
                        <div class="d-flex align-items-center">
                            <div id="upgradeAlert" class="me-3" style="display: none;">
                                <div class="alert alert-warning py-1 px-3 mb-0 d-flex align-items-center">
                                    <i class="fas fa-exclamation-circle me-2"></i>
                                    <span id="upgradeMessage"></span>
                                </div>
                            </div>
                            <div class="btn-group">
                                <button class="btn btn-success" id="fileImportBtn">
                                    <i class="fas fa-file-import me-1"></i>
                                    文件导入
                                </button>
                                <button class="btn btn-primary" onclick="showAddGradeModal()">
                                    <i class="fas fa-plus-circle"></i>
                                    添加年级
                                </button>
                                <button class="btn btn-success" onclick="showAddClassModal()">
                                    <i class="fas fa-plus-circle"></i>
                                    添加班级
                                </button>
                                <div class="button-wrapper">
                                    <button id="upgradeGradesBtn" class="btn btn-warning" onclick="upgradeGrades()" disabled>
                                        <i class="fas fa-arrow-up"></i>
                                        一键升级
                                    </button>
                                    <div class="tooltip-trigger" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-title="加载中..."></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <div id="gradeList">
                            <!-- 年级列表将通过JavaScript动态加载 -->
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 添加/编辑年级模态框 -->
    <div class="modal fade" id="gradeModal" tabindex="-1" data-bs-backdrop="static">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">添加年级</h5>
                    <button type="button" class="btn-close" onclick="closeGradeModal()"></button>
                </div>
                <div class="modal-body">
                    <form id="gradeForm">
                        <input type="hidden" id="gradeId">
                        <div class="mb-3">
                            <label class="form-label">年级名称</label>
                            <input type="text" class="form-control" id="gradeName" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">年级代码</label>
                            <input type="text" class="form-control" id="gradeCode" required>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeGradeModal()">取消</button>
                    <button type="button" class="btn btn-primary" onclick="saveGrade()">保存</button>
                </div>
            </div>
        </div>
    </div>

    <!-- 修改添加班级模态框的内容 -->
    <div class="modal fade" id="classModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">添加班级</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="classForm">
                        <input type="hidden" name="id" id="classId">
                        <div class="mb-3">
                            <label class="form-label">所属年级</label>
                            <select class="form-select" name="grade_id" id="gradeSelect" required>
                                <option value="">请选择年级</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">班级名称</label>
                            <input type="text" class="form-control" name="class_name" id="className" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">班级代码</label>
                            <div class="input-group">
                                <span class="input-group-text" id="selectedGradeCode"></span>
                                <input type="text" class="form-control" name="class_code" id="classCode" required>
                            </div>
                            <div class="form-text">完整班级代码将为：年级代码 + 班级代码</div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
                    <button type="button" class="btn btn-primary" id="saveClass">保存</button>
                </div>
            </div>
        </div>
    </div>

    <!-- 编辑班级模态框 -->
    <div class="modal fade" id="editClassModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">编辑班级</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="editClassForm">
                        <input type="hidden" id="editClassId">
                        <div class="mb-3">
                            <label class="form-label">所属年级</label>
                            <div id="editGradeName" class="form-control-plaintext"></div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">班级名称</label>
                            <input type="text" class="form-control" id="editClassName" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">班级代码</label>
                            <div class="input-group">
                                <span class="input-group-text" id="editGradeCode"></span>
                                <input type="text" class="form-control" id="editClassCode" required 
                                       placeholder="请输入班级代码">
                            </div>
                            <div class="form-text">完整班级代码将为：年级代码 + 班级代码</div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
                    <button type="button" class="btn btn-primary" onclick="updateClass()">保存</button>
                </div>
            </div>
        </div>
    </div>

    <!-- 提示模态框 -->
    <div class="modal fade" id="alertModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-exclamation-circle modal-icon"></i>
                        <span>提示</span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-0"></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary" data-bs-dismiss="modal">确定</button>
                </div>
            </div>
        </div>
    </div>

    <!-- 确认模态框 -->
    <div id="confirmModal" class="modal fade" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">确认操作</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p id="confirmMessage"></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
                    <button type="button" class="btn btn-primary" id="confirmBtn">确认</button>
                </div>
            </div>
        </div>
    </div>

    <!-- 文件导入模态框 -->
    <div class="modal fade" id="fileImportModal" tabindex="-1" data-bs-backdrop="static">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">文件导入年级班级</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">选择文件</label>
                        <input type="file" class="form-control" id="importFile" accept=".xlsx,.xls">
                        <small class="text-muted">支持 Excel 文件格式（.xlsx, .xls）</small>
                        <div class="mt-2">
                            <a href="../templates/download_grade_class_template.php" class="btn btn-outline-primary btn-sm">
                                <i class="fas fa-download me-1"></i>下载导入模板
                            </a>
                            <small class="text-muted ms-2">请下载模板后用Excel打开填写数据</small>
                        </div>
                    </div>
                    <div class="alert alert-info">
                        <strong>导入说明：</strong>
                        <ul class="mb-0 ps-3">
                            <li>Excel文件需包含：年级名称、年级代码、班级名称、班级代码</li>
                            <li>年级代码和班级代码不能重复</li>
                            <li>请确保数据格式正确，避免重复数据</li>
                        </ul>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
                    <button type="button" class="btn btn-primary" onclick="handleFileImport()">导入</button>
                </div>
            </div>
        </div>
    </div>

    <!-- JavaScript 依赖 -->
    <script src="../assets/js/jquery.min.js"></script>
    <script src="../assets/js/bootstrap.bundle.min.js"></script>
    <!-- 添加 SweetAlert2 -->
    <script src="../assets/js/sweetalert2.all.min.js"></script>
    
    <script>
        // 显示成功消息
        function showSuccess(message) {
            Swal.fire({
                title: '成功',
                text: message,
                icon: 'success',
                timer: 2000,
                showConfirmButton: false,
                customClass: {
                    popup: 'swal2-small'
                }
            });
        }

        // 显示警告消息
        function showAlert(message) {
            Swal.fire({
                title: '提示',
                html: message,
                icon: 'warning',
                showConfirmButton: false,
                timer: 3000,
                customClass: {
                    popup: 'swal2-small'
                }
            });
        }

        // 显示确认模态框的函数
        function showConfirm(message, callback) {
            Swal.fire({
                title: '确认操作',
                html: message,
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: '确认',
                cancelButtonText: '取消',
                customClass: {
                    popup: 'swal2-small'
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    callback(true);
                }
            });
        }

        // 加载年级列表
        function loadGrades() {
            $.get('../api/index.php?route=settings/grades', function(response) {
                let html = '';
                if (response.data && response.data.length > 0) {
                    response.data.forEach(function(grade) {
                        html += `
                            <div class="grade-section">
                                <div class="grade-header d-flex justify-content-between align-items-center">
                                    <div class="grade-title">
                                        ${grade.grade_name}
                                        <small class="text-muted ms-2">(${grade.grade_code})</small>
                                    </div>
                                    <div class="btn-group">
                                        <button class="action-btn edit-btn edit-grade" data-id="${grade.id}" title="编辑">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="action-btn delete-btn delete-grade" data-id="${grade.id}" title="删除">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </div>
                                </div>
                                
                                <div class="table-container">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th width="30%">班级名称</th>
                                                <th width="30%">班级代码</th>
                                                <th width="20%">学生人数</th>
                                                <th width="20%">操作</th>
                                            </tr>
                                        </thead>
                                        <tbody id="classList-${grade.id}">
                                            <!-- 班级列表将通过JavaScript动态加载 -->
                                        </tbody>
                                    </table>
                                </div>
                            </div>`;
                    });
                } else {
                    html = '<div class="alert alert-info text-center"><i class="fas fa-info-circle me-2"></i>暂无年级数据</div>';
                }
                $('#gradeList').html(html);
                
                // 加载每个年级的班级列表
                if (response.data) {
                    let loadedCount = 0;
                    const totalCount = response.data.length;
                    
                    if (totalCount === 0) {
                        // 如果没有年级数据，直接触发事件
                        $(document).trigger('gradesLoaded');
                    } else {
                        response.data.forEach(grade => {
                            loadClassesForGrade(grade.id, function() {
                                loadedCount++;
                                if (loadedCount === totalCount) {
                                    // 所有班级数据都加载完成后触发事件
                                    $(document).trigger('gradesLoaded');
                                }
                            });
                        });
                    }
                }
            }).fail(function(xhr) {
                showAlert('加载年级列表失败：' + (xhr.responseJSON?.error || '网络错误'));
                // 即使加载失败也触发事件
                $(document).trigger('gradesLoaded');
            });
        }

        // 加载班级列表
        function loadClassesForGrade(gradeId, callback) {
            $.get('../api/index.php?route=settings/classes', { grade_id: gradeId }, function(response) {
                let html = '';
                if (response.data && response.data.length > 0) {
                    response.data.forEach(function(cls) {
                        html += `
                            <tr>
                                <td>${cls.class_name}</td>
                                <td>${cls.class_code}</td>
                                <td>${cls.student_count || 0}</td>
                                <td>
                                    <div class="btn-group">
                                        <button class="action-btn edit-btn edit-class" data-id="${cls.id}" title="编辑">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="action-btn delete-btn delete-class" data-id="${cls.id}" title="删除">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>`;
                    });
                } else {
                    html = '<tr><td colspan="4" class="text-center text-muted">暂无班级数据</td></tr>';
                }
                $(`#classList-${gradeId}`).html(html);
                if (typeof callback === 'function') {
                    callback();
                }
            }).fail(function(xhr) {
                $(`#classList-${gradeId}`).html('<tr><td colspan="4" class="text-center text-danger">加载班级列表失败</td></tr>');
                if (typeof callback === 'function') {
                    callback();
                }
            });
        }

        // 显示添加年级模态框
        function showAddGradeModal() {
            $('#gradeId').val('');
            $('#gradeName').val('');
            $('#gradeCode').val('');
            $('.modal-title').text('添加年级');
            const modal = new bootstrap.Modal(document.getElementById('gradeModal'));
            modal.show();
        }

        // 关闭年级模态框
        function closeGradeModal() {
            const modal = bootstrap.Modal.getInstance(document.getElementById('gradeModal'));
            if (modal) {
                modal.hide();
            }
        }

        // 保存年级信息
        function saveGrade() {
            const id = $('#gradeId').val();
            const gradeName = $('#gradeName').val().trim();
            const gradeCode = $('#gradeCode').val().trim();
            
            if (!gradeName || !gradeCode) {
                showAlert('请填写完整信息');
                return;
            }

            // 获取当前可用项目的setting_id
            $.get('../api/index.php?route=settings/current', function(response) {
                if (!response.success || !response.data || !response.data.id) {
                    showAlert('获取当前项目信息失败');
                    return;
                }

                const settingId = response.data.id;
                const url = id ? '../api/index.php?route=settings/grade/update' : '../api/index.php?route=settings/grade/add';
                
                $.ajax({
                    url: url,
                    method: 'POST',
                    data: {
                        id: id,
                        setting_id: settingId,
                        grade_name: gradeName,
                        grade_code: gradeCode
                    },
                    success: function(response) {
                        if (response.success) {
                            closeGradeModal();
                            loadGrades();
                            showSuccess('年级' + (id ? '修改' : '添加') + '成功');
                        } else {
                            showAlert(response.error || '操作失败');
                        }
                    },
                    error: function(xhr) {
                        let errorMsg = '操作失败';
                        if (xhr.responseJSON && xhr.responseJSON.error) {
                            errorMsg = xhr.responseJSON.error;
                        }
                        showAlert(errorMsg);
                    }
                });
            }).fail(function(xhr) {
                showAlert('获取当前项目信息失败：' + (xhr.responseJSON?.error || '网络错误'));
            });
        }

        // 修改年级选择的change事件处理
        $('#gradeSelect').on('change', function() {
            const selectedOption = $(this).find('option:selected');
            const gradeCode = selectedOption.data('code') || '';
            
            // 更新班级代码输入框前的年级代码显示
            $('#selectedGradeCode').text(gradeCode);
        });

        // 修改显示添加班级模态框的函数
        function showAddClassModal() {
            $('#classForm')[0].reset();
            $('#classId').val('');
            $('#selectedGradeCode').text('');  // 清空年级代码显示
            $('.modal-title').text('添加班级');
            
            // 加载年级列表
            $.get('../api/index.php?route=settings/grades', function(response) {
                if (response.success && response.data) {
                    let options = '<option value="">请选择年级</option>';
                    response.data.forEach(function(grade) {
                        if (grade && grade.id) {
                            options += `<option value="${grade.id}" data-code="${grade.grade_code || ''}">${grade.grade_name || ''}</option>`;
                        }
                    });
                    $('#gradeSelect').html(options);
                    
                    // 重新初始化自定义下拉框
                    initCustomSelects();
                    
                    // 显示模态框
                    const modal = new bootstrap.Modal(document.getElementById('classModal'));
                    modal.show();
                } else {
                    showAlert(response.error || '加载年级列表失败');
                }
            }).fail(function(xhr) {
                showAlert('加载年级列表失败：' + (xhr.responseJSON?.error || '网络错误'));
            });
        }

        // 保存班级信息
        $('#saveClass').click(function() {
            const form = $('#classForm')[0];
            if (!form.checkValidity()) {
                form.reportValidity();
                return;
            }

            // 获取当前可用项目的setting_id
            $.get('../api/index.php?route=settings/current', function(response) {
                if (!response.success || !response.data || !response.data.id) {
                    showAlert('获取当前项目信息失败');
                    return;
                }

                const formData = {
                    id: $('#classId').val(),
                    setting_id: response.data.id,
                    grade_id: $('#gradeSelect').val(),
                    class_name: $('#className').val(),
                    class_code: $('#classCode').val()
                };

                $.ajax({
                    url: '../api/index.php?route=class/add',
                    method: 'POST',
                    data: formData,
                    success: function(response) {
                        if (response.success) {
                            $('#classModal').modal('hide');
                            showSuccess('保存成功');
                            loadGrades();
                        } else {
                            if (response.error === 'class_code_exists') {
                                showAlert('班级代码已存在，请使用其他代码');
                            } else {
                                showAlert(response.error || '保存失败');
                            }
                        }
                    },
                    error: function(xhr) {
                        showAlert('保存失败：' + (xhr.responseJSON?.error || '网络错误'));
                    }
                });
            }).fail(function(xhr) {
                showAlert('获取当前项目信息失败：' + (xhr.responseJSON?.error || '网络错误'));
            });
        });

        // 编辑班级
        function editClass(id) {
            // 重置表单
            $('#editClassForm')[0].reset();
            $('#editClassId').val(id);
            $('.modal-title').text('编辑班级');
            
            // 获取班级信息
            $.get('../api/index.php?route=settings/class/get', { id: id }, function(response) {
                if (response.success) {
                    const classData = response.data;
                    $('#editGradeName').text(classData.grade_name);
                    $('#editGradeCode').text(classData.grade_code);
                    $('#editClassName').val(classData.class_name);
                    
                    // 只显示班级代码的后缀部分（去掉年级代码部分）
                    const classCodeSuffix = classData.class_code.substring(classData.grade_code.length);
                    $('#editClassCode').val(classCodeSuffix);
                    
                    const modal = new bootstrap.Modal(document.getElementById('editClassModal'));
                    modal.show();
                } else {
                    showAlert(response.error || '加载班级信息失败');
                }
            }).fail(function(xhr) {
                showAlert('加载班级信息失败：' + (xhr.responseJSON?.error || '未知错误'));
            });
        }

        // 更新班级信息
        function updateClass() {
            const id = $('#editClassId').val();
            const className = $('#editClassName').val().trim();
            const classCode = $('#editClassCode').val().trim();
            const gradeCode = $('#editGradeCode').text().trim();
            
            if (!className || !classCode) {
                showAlert('请填写完整信息');
                return;
            }

            // 检查班级代码是否以年级代码开头
            if (classCode.startsWith(gradeCode)) {
                showAlert('请只输入班级代码的后缀部分，无需输入年级代码');
                return;
            }

            // 获取当前可用项目的setting_id
            $.get('../api/index.php?route=settings/current', function(response) {
                if (!response.success || !response.data || !response.data.id) {
                    showAlert('获取当前项目信息失败');
                    return;
                }

                const settingId = response.data.id;

                // 获取班级所属的年级ID
                $.get('../api/index.php?route=settings/class/get', { id: id }, function(classData) {
                    if (!classData.success || !classData.data || !classData.data.grade_id) {
                        showAlert('获取班级信息失败');
                        return;
                    }

                    const gradeId = classData.data.grade_id;

                    $.ajax({
                        url: '../api/index.php?route=settings/class/update',
                        method: 'POST',
                        data: {
                            id: id,
                            setting_id: settingId,
                            grade_id: gradeId,
                            class_name: className,
                            class_code: classCode
                        },
                        success: function(response) {
                            if (response.success) {
                                const modal = bootstrap.Modal.getInstance(document.getElementById('editClassModal'));
                                if (modal) modal.hide();
                                loadGrades();  // 重新加载年级和班级列表
                                showSuccess('修改成功');
                            } else {
                                showAlert(response.error || '修改失败');
                            }
                        },
                        error: function(xhr) {
                            showAlert('修改失败：' + (xhr.responseJSON?.error || '未知错误'));
                        }
                    });
                }).fail(function(xhr) {
                    showAlert('获取班级信息失败：' + (xhr.responseJSON?.error || '未知错误'));
                });
            }).fail(function(xhr) {
                showAlert('获取当前项目信息失败：' + (xhr.responseJSON?.error || '网络错误'));
            });
        }

        // 编辑班级按钮点击事件
        $(document).on('click', '.edit-class', function() {
            const id = $(this).data('id');
            editClass(id);
        });

        // 删除年级
        $(document).on('click', '.delete-grade', function() {
            const id = $(this).data('id');
            const $gradeSection = $(this).closest('.grade-section');
            const gradeName = $gradeSection.find('.grade-title').text().trim().split('(')[0].trim();
            
            if (!window.currentSettingId) {
                showAlert('无法获取当前项目ID，请刷新页面重试');
                return;
            }
            
            showConfirm(
                `确定要删除年级"${gradeName}"吗？注意：只有在该年级下没有班级和学生时才能删除。`,
                function(confirmed) {
                    if (confirmed) {
                        $.ajax({
                            url: '../api/index.php?route=settings/grade/delete',
                            method: 'POST',
                            data: { 
                                id: id,
                                setting_id: window.currentSettingId 
                            },
                            success: function(response) {
                                if (response.success) {
                                    showSuccess('删除成功');
                                    loadGrades();
                                } else {
                                    setTimeout(() => {
                                        showAlert(response.error || '删除失败');
                                    }, 100);
                                }
                            },
                            error: function(xhr) {
                                setTimeout(() => {
                                    showAlert('删除失败：' + (xhr.responseJSON?.error || '网络错误'));
                                }, 100);
                            }
                        });
                    }
                }
            );
        });

        // 删除班级
        $(document).on('click', '.delete-class', function() {
            const id = $(this).data('id');
            const $row = $(this).closest('tr');
            const className = $row.find('td:first').text().trim();
            const $gradeSection = $(this).closest('.grade-section');
            const gradeName = $gradeSection.find('.grade-title').text().trim().split('(')[0].trim();
            
            showConfirm(
                `确定要删除"${gradeName}${className}"吗？\n注意：只有在该班级下没有学生时才能删除。`,
                function(confirmed) {
                    if (confirmed) {
                        $.ajax({
                            url: '../api/index.php?route=settings/class/delete',
                            method: 'POST',
                            data: { id: id },
                            success: function(response) {
                                if (response.success) {
                                    showSuccess('删除成功');
                                    loadGrades();
                                } else {
                                    setTimeout(() => {
                                        showAlert(response.error || '删除失败');
                                    }, 100);
                                }
                            },
                            error: function(xhr) {
                                setTimeout(() => {
                                    showAlert('删除失败：' + (xhr.responseJSON?.error || '网络错误'));
                                }, 100);
                            }
                        });
                    }
                }
            );
        });

        // 编辑年级按钮点击事件
        $(document).on('click', '.edit-grade', function() {
            const id = $(this).data('id');
            $.get('../api/index.php?route=grade/get', { id: id }, function(response) {
                if (response.success && response.data) {
                    const gradeData = response.data;
                    $('#gradeId').val(gradeData.id);
                    $('#gradeName').val(gradeData.grade_name);
                    $('#gradeCode').val(gradeData.grade_code);
                    $('.modal-title').text('编辑年级');
                    const gradeModal = new bootstrap.Modal(document.getElementById('gradeModal'));
                    gradeModal.show();
                } else {
                    showAlert(response.error || '加载年级信息失败');
                }
            }).fail(function(xhr) {
                showAlert('加载年级信息失败：' + (xhr.responseJSON?.error || '未知错误'));
            });
        });

        // 显示文件导入模态框
        function showFileImportModal() {
            // 清空文件输入
            $('#importFile').val('');
            const fileImportModal = new bootstrap.Modal(document.getElementById('fileImportModal'));
            fileImportModal.show();
        }

        // 处理文件导入
        function handleFileImport() {
            const fileInput = $('#importFile')[0];
            
            if (!fileInput.files || !fileInput.files[0]) {
                Swal.fire({
                    title: '提示',
                    text: '请选择要导入的文件',
                    icon: 'warning',
                    showConfirmButton: false,
                    timer: 2000,
                    customClass: {
                        popup: 'swal2-small'
                    }
                });
                return;
            }

            // 检查文件类型
            const file = fileInput.files[0];
            console.log('上传文件信息:', {
                name: file.name,
                type: file.type,
                size: file.size
            });

            const allowedTypes = [
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'application/vnd.ms-excel',
                'application/octet-stream'  // 某些浏览器可能会用这个类型
            ];
            
            // 检查文件扩展名
            const fileName = file.name.toLowerCase();
            const validExtension = fileName.endsWith('.xlsx') || fileName.endsWith('.xls');
            
            if (!validExtension || (!allowedTypes.includes(file.type) && file.type !== '')) {
                Swal.fire({
                    title: '提示',
                    text: '请选择正确的Excel文件格式（.xlsx或.xls）',
                    icon: 'warning',
                    showConfirmButton: false,
                    timer: 2000,
                    customClass: {
                        popup: 'swal2-small'
                    }
                });
                return;
            }

            const formData = new FormData();
            formData.append('file', file);

            // 显示加载提示
            Swal.fire({
                title: '正在导入',
                text: '请稍候...',
                allowOutsideClick: false,
                showConfirmButton: false,
                didOpen: () => {
                    Swal.showLoading();
                },
                customClass: {
                    popup: 'swal2-small'
                }
            });

            $.ajax({
                url: '../api/index.php?route=settings/import_grade_class',
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    console.log('文件导入响应:', response);
                    if (response.success) {
                        const fileImportModal = bootstrap.Modal.getInstance(document.getElementById('fileImportModal'));
                        fileImportModal.hide();
                        
                        Swal.fire({
                            title: '导入成功',
                            html: response.message || '导入成功',
                            icon: 'success',
                            showConfirmButton: false,
                            timer: 3000,
                            customClass: {
                                popup: 'swal2-small',
                                htmlContainer: 'text-left'
                            }
                        }).then(() => {
                            loadGrades();
                        });
                    } else {
                        Swal.fire({
                            title: '导入失败',
                            html: response.isHtml ? response.error : `<div style="text-align: left;">${response.error}</div>`,
                            icon: 'error',
                            showConfirmButton: false,
                            timer: 3000,
                            customClass: {
                                popup: 'swal2-small',
                                htmlContainer: 'text-left'
                            }
                        });
                    }
                },
                error: function(xhr, status, error) {
                    console.error('文件导入请求失败:', {
                        status: status,
                        error: error,
                        response: xhr.responseText
                    });
                    
                    let errorMessage = '未知错误';
                    try {
                        const response = JSON.parse(xhr.responseText);
                        errorMessage = response.error || '导入失败';
                    } catch (e) {
                        errorMessage = '导入失败：' + xhr.statusText;
                    }
                    
                    Swal.fire({
                        title: '导入失败',
                        text: errorMessage,
                        icon: 'error',
                        showConfirmButton: false,
                        timer: 3000,
                        customClass: {
                            popup: 'swal2-small'
                        }
                    });
                }
            });
        }

        // 初始化UI和事件绑定
        function initializeUI() {
            // 获取当前项目ID
            $.get('../api/index.php?route=settings/current', function(response) {
                if (response.success && response.data && response.data.id) {
                    window.currentSettingId = response.data.id;
                    // 加载年级列表
                    loadGrades();
                    // 绑定事件监听
                    bindEventListeners();
                } else {
                    showAlert('获取当前项目信息失败');
                }
            }).fail(function(xhr) {
                showAlert('获取当前项目信息失败：' + (xhr.responseJSON?.error || '网络错误'));
            });
        }

        // 绑定事件监听器
        function bindEventListeners() {
            // 年级选择变化时更新班级列表
            $('#gradeSelect').on('change', function() {
                const gradeId = $(this).val();
                if (gradeId) {
                    loadClassesForGrade(gradeId);
                }
            });

            // 文件导入按钮点击事件
            $('#fileImportBtn').off('click').on('click', showFileImportModal);

            // 初始化工具提示
            $('[data-bs-toggle="tooltip"]').tooltip();
        }

        // 页面加载完成后初始化
        $(document).ready(function() {
            initializeUI();
            // 初始化自定义下拉框
            initCustomSelects();
            checkUpgradable(); // 直接调用检查函数
        });

        // 初始化自定义下拉框
        function initCustomSelects() {
            // 先移除已存在的自定义下拉框
            $('.custom-select-wrapper').remove();
            
            $('.form-select').each(function() {
                const select = $(this);
                const wrapper = $('<div class="custom-select-wrapper"></div>');
                const trigger = $('<div class="custom-select-trigger"></div>');
                const options = $('<div class="custom-options"></div>');
                
                // 设置默认显示文本
                trigger.text(select.find('option:selected').text());
                
                // 创建选项
                select.find('option').each(function() {
                    const option = $('<div class="custom-option"></div>')
                        .attr('data-value', $(this).val())
                        .text($(this).text());
                        
                    if ($(this).is(':selected')) {
                        option.addClass('selected');
                    }
                    
                    options.append(option);
                });
                
                // 包装元素
                wrapper.append(trigger).append(options);
                select.after(wrapper);
                
                // 点击触发器
                trigger.on('click', function(e) {
                    e.stopPropagation();
                    $('.custom-select-wrapper').not(wrapper).removeClass('open');
                    wrapper.toggleClass('open');
                });
                
                // 点击选项
                options.find('.custom-option').on('click', function() {
                    const value = $(this).data('value');
                    const text = $(this).text();
                    
                    // 更新显示文本和选中状态
                    trigger.text(text);
                    options.find('.custom-option').removeClass('selected');
                    $(this).addClass('selected');
                    
                    // 更新原始select的值并触发change事件
                    select.val(value).trigger('change');
                    
                    wrapper.removeClass('open');
                });
            });
            
            // 点击其他地方关闭下拉框
            $(document).off('click.customSelect').on('click.customSelect', function() {
                $('.custom-select-wrapper').removeClass('open');
            });
        }

        // 检查是否可以进行年级升级
        function checkUpgradable() {
            const upgradeBtn = $('#upgradeGradesBtn');
            const tooltipTrigger = upgradeBtn.closest('.button-wrapper').find('.tooltip-trigger');
            
            // 设置初始状态
            tooltipTrigger.attr('data-bs-title', '加载中...');
            
            // 确保tooltip已初始化
            let tooltip = bootstrap.Tooltip.getInstance(tooltipTrigger[0]);
            if (tooltip) {
                tooltip.dispose();
            }
            tooltip = new bootstrap.Tooltip(tooltipTrigger[0], {
                title: '加载中...',
                placement: 'top',
                trigger: 'hover',
                template: '<div class="tooltip" role="tooltip"><div class="tooltip-arrow"></div><div class="tooltip-inner"></div></div>'
            });
            
            $.ajax({
                url: '../api/index.php?route=settings/grade/check_upgradable',
                type: 'GET',
                success: function(response) {
                    if (response.success) {
                        if (response.upgradable) {
                            upgradeBtn.prop('disabled', false);
                            tooltipTrigger.attr('data-bs-title', '一键升级所有年级班级');
                        } else {
                            upgradeBtn.prop('disabled', true);
                            tooltipTrigger.attr('data-bs-title', response.message || '当前不可进行年级升级');
                        }
                        
                        // 更新tooltip内容
                        tooltip.dispose();
                        new bootstrap.Tooltip(tooltipTrigger[0], {
                            title: tooltipTrigger.attr('data-bs-title'),
                            placement: 'top',
                            trigger: 'hover',
                            template: '<div class="tooltip" role="tooltip"><div class="tooltip-arrow"></div><div class="tooltip-inner"></div></div>'
                        });
                    } else {
                        showAlert(response.error);
                    }
                },
                error: function(xhr) {
                    console.error('检查升级状态失败:', xhr.responseText);
                    showAlert('检查升级状态失败');
                    tooltipTrigger.attr('data-bs-title', '检查升级状态失败');
                    
                    // 更新tooltip内容
                    tooltip.dispose();
                    new bootstrap.Tooltip(tooltipTrigger[0], {
                        title: '检查升级状态失败',
                        placement: 'top',
                        trigger: 'hover',
                        template: '<div class="tooltip" role="tooltip"><div class="tooltip-arrow"></div><div class="tooltip-inner"></div></div>'
                    });
                }
            });
        }

        // 修改升级函数
        function upgradeGrades() {
            Swal.fire({
                title: '确认操作',
                html: '确定要进行年级升级吗？<br>此操作不可恢复！请慎重！！！',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: '确认',
                cancelButtonText: '取消',
                customClass: {
                    popup: 'swal2-small'
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    const upgradeBtn = $('#upgradeGradesBtn');
                    upgradeBtn.prop('disabled', true);
                    upgradeBtn.html('<i class="fas fa-spinner fa-spin me-1"></i>升级中...');
                    
                    // 显示升级中的提示
                    Swal.fire({
                        title: '正在升级',
                        text: '正在进行年级升级，请稍候...',
                        allowOutsideClick: false,
                        showConfirmButton: false,
                        didOpen: () => {
                            Swal.showLoading();
                        },
                        customClass: {
                            popup: 'swal2-small'
                        }
                    });
                    
                    $.ajax({
                        url: '../api/index.php?route=settings/grade/upgrade',
                        type: 'POST',
                        success: function(response) {
                            if (response.success) {
                                Swal.fire({
                                    title: '成功',
                                    text: '年级升级成功',
                                    icon: 'success',
                                    showConfirmButton: false,
                                    timer: 2000,
                                    customClass: {
                                        popup: 'swal2-small'
                                    }
                                });
                                loadGrades();
                            } else {
                                Swal.fire({
                                    title: '升级失败',
                                    text: response.error || '年级升级失败',
                                    icon: 'error',
                                    showConfirmButton: false,
                                    timer: 3000,
                                    customClass: {
                                        popup: 'swal2-small'
                                    }
                                });
                            }
                        },
                        error: function(xhr) {
                            Swal.fire({
                                title: '升级失败',
                                text: xhr.responseJSON?.error || '年级升级失败',
                                icon: 'error',
                                showConfirmButton: false,
                                timer: 3000,
                                customClass: {
                                    popup: 'swal2-small'
                                }
                            });
                        },
                        complete: function() {
                            upgradeBtn.prop('disabled', false);
                            upgradeBtn.html('<i class="fas fa-arrow-up"></i>一键升级');
                            checkUpgradable();
                        }
                    });
                }
            });
        }
    </script>
</body>
</html>
