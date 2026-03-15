<?php
/**
 * 文件名: modules/user_settings.php
 * 功能描述: 用户管理设置模块
 *
 * 该文件负责:
 * 1. 提供用户管理的用户界面
 * 2. 支持用户的增删改查功能
 * 3. 用户权限的分配和管理
 * 4. 用户状态切换（启用/禁用）
 * 5. 提供用户密码修改功能
 *
 * 该模块仅限管理员访问，提供完整的用户管理功能。
 * 支持创建不同角色的用户（管理员、教师、教导处），
 * 并可为教师分配具体的年级和科目权限。
 *
 * 关联文件:
 * - controllers/UserController.php: 用户控制器
 * - controllers/SettingsController.php: 设置控制器
 * - api/toggle_user_status.php: 用户状态切换API
 * - api/index.php: API入口
 * - assets/js/user-permissions.js: 用户权限管理前端脚本
 * - assets/js/settings.js: 通用设置脚本
 */

// 确保包含必要的配置文件
require_once __DIR__ . '/../config/config.php';

// 启动会话
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 检查用户是否登录且是管理员
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    // 使用绝对路径包含错误页面
    $errorFile = __DIR__ . '/../error/403.php';
    if (file_exists($errorFile)) {
        include $errorFile;
    } else {
        // 如果错误页面不存在，显示简单的错误消息
        header('HTTP/1.1 403 Forbidden');
        echo '访问被拒绝：需要管理员权限';
    }
    exit;
}

// 设置错误报告
error_reporting(E_ALL);
ini_set('display_errors', 1);
?>

<!-- CSS 依赖 -->
<link href="../assets/css/bootstrap.min.css" rel="stylesheet">
<link href="../assets/css/sweetalert2.min.css" rel="stylesheet">
<link href="../assets/css/all.min.css" rel="stylesheet">
<link href="../assets/css/common.css" rel="stylesheet">
<style>
    /* 卡片样式优化 */
    .card {
        border: none;
        border-radius: 16px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        background: #ffffff;
        margin-bottom: 20px;
    }

    /* 卡片头部样式 */
    .card-header {
        background: transparent;
        border-bottom: 1px solid rgba(0,0,0,0.05);
        padding: 1.5rem;
    }

    .card-header h5 {
        font-size: 1.1rem;
        font-weight: 600;
        color: #2c3e50;
        margin: 0;
        display: flex;
        align-items: center;
    }

    .card-header .btn-primary {
        padding: 8px 20px;
        font-size: 0.9rem;
        border-radius: 8px;
        background: #0284c7;
        border-color: #0284c7;
        transition: all 0.2s ease;
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .card-header .btn-primary:hover {
        transform: translateY(-1px);
        box-shadow: 0 2px 4px rgba(13, 110, 253, 0.2);
    }

    /* 表格样式优化 */
    .table {
        margin-bottom: 0;
        background: #fff;
        border-radius: 8px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    }

    .table thead th {
        background-color: #f8f9fa;
        border-bottom: 2px solid #e9ecef;
        padding: 1rem;
        font-weight: 500;
        color: #2c3e50;
        white-space: nowrap;
    }

    /* 表头排序样式 */
    .sortable {
        cursor: pointer;
        position: relative;
        user-select: none;
    }

    .sortable:hover {
        background-color: #e9ecef;
    }

    .sortable i.fas {
        margin-left: 5px;
        color: #adb5bd;
        font-size: 0.8rem;
    }

    .sortable.sort-asc i.fas {
        color: #0d6efd;
    }

    .sortable.sort-desc i.fas {
        color: #0d6efd;
    }

    .sortable.sort-asc i.fas:before {
        content: "\f0de"; /* fa-sort-up */
    }

    .sortable.sort-desc i.fas:before {
        content: "\f0dd"; /* fa-sort-down */
    }

    .table td {
        padding: 1rem;
        vertical-align: middle;
        border-bottom: 1px solid #e9ecef;
        color: #2c3e50;
    }

    /* 按钮样式优化 */
    .btn-sm {
        padding: 4px 10px;
        font-size: 0.85rem;
        border-radius: 6px;
        display: inline-flex;
        align-items: center;
        gap: 4px;
        transition: all 0.2s ease;
    }

    .btn-sm:hover {
        transform: translateY(-1px);
    }

    .btn-sm i {
        font-size: 0.8rem;
    }

    /* 状态标签样式 */
    .text-success, .text-danger {
        font-weight: 500;
    }

    .badge {
        padding: 4px 8px;
        font-weight: 500;
        font-size: 0.75rem;
        border-radius: 4px;
    }

    /* 模态框样式优化 */
    .modal-content {
        border: none;
        border-radius: 16px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        animation: modalFadeIn 0.3s ease-out;
    }

    .modal-header {
        border-bottom: 1px solid rgba(0,0,0,0.08);
        padding: 20px 24px;
    }

    .modal-header .modal-title {
        font-size: 1.25rem;
        font-weight: 600;
        color: #ffffff;
    }

    .modal-body {
        padding: 24px;
    }

    .modal-footer {
        border-top: 1px solid rgba(0,0,0,0.08);
        padding: 16px 24px;
    }

    /* 表单控件样式优化 */
    .modal .form-control {
        border-radius: 8px;
        border: 1px solid rgba(0,0,0,0.1);
        padding: 10px 16px;
        transition: all 0.2s;
    }

    .modal .form-control:focus {
        border-color: #007bff;
        box-shadow: 0 0 0 3px rgba(0,123,255,0.1);
    }

    .modal .form-label {
        font-weight: 500;
        color: #2c3e50;
        margin-bottom: 8px;
    }

    /* 动画效果 */
    @keyframes modalFadeIn {
        from {
            opacity: 0;
            transform: translateY(-20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    /* 响应式调整 */
    @media (max-width: 576px) {
        .modal-content {
            margin: 10px;
        }
        .modal .btn {
            width: 100%;
            margin: 5px 0;
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

    /* 权限设置区域样式 */
    #permissionsSection {
        background: #f8f9fa;
        border-radius: 12px;
        padding: 1.5rem;
        margin-top: 1rem;
    }

    .permissions-title {
        color: #2c3e50;
        font-weight: 500;
        margin-bottom: 1rem;
    }

    /* 年级列表样式 */
    .grade-list {
        max-height: 300px;
        overflow-y: auto;
        padding: 8px;
    }

    .form-check {
        transition: all 0.3s ease;
        padding: 8px 16px;
        margin: 4px 0;
        border-radius: 6px;
        display: block;
    }

    .form-check:hover {
        background-color: rgba(0, 123, 255, 0.05);
    }

    .form-check.text-danger {
        background-color: rgba(220, 53, 69, 0.1);
        animation: highlight 0.5s ease;
    }

    .form-check-input {
        margin-right: 8px;
    }

    .form-check-label {
        user-select: none;
        cursor: pointer;
        color: #2c3e50;
    }

    @keyframes highlight {
        0% { transform: translateX(0); }
        25% { transform: translateX(-5px); }
        75% { transform: translateX(5px); }
        100% { transform: translateX(0); }
    }

    /* 可点击选项样式 */
    .clickable-option {
        background: #f8fafc;
        padding: 1rem;
        border-radius: 8px;
        border: 1px solid rgba(0,0,0,0.05);
        margin-bottom: 0.5rem;
        cursor: pointer;
        transition: all 0.2s ease;
        position: relative;
    }

    .clickable-option:hover {
        background-color: rgba(0, 123, 255, 0.05);
        border-color: #86b7fe;
        transform: translateX(3px);
    }

    .clickable-option.active {
        background: linear-gradient(to right, #e7f1ff, #ffffff);
        border-color: #0d6efd;
    }

    .clickable-option input[type="checkbox"] {
        margin-right: 8px;
    }

    /* 警告框样式 */
    .alert {
        border: none;
        border-radius: 8px;
    }

    .alert-info {
        background: #f0f9ff;
        color: #0369a1;
    }

    .alert-warning {
        background: #fef3c7;
        color: #92400e;
    }

    .alert-danger {
        background: #fee2e2;
        color: #991b1b;
    }

    /* 表格行选中效果 */
    .table tbody tr {
        cursor: pointer;
        transition: all 0.2s ease;
    }

    .table tbody tr:hover {
        background-color: rgba(0, 123, 255, 0.05);
    }

    .table tbody tr.selected {
        background-color: rgba(0, 123, 255, 0.1);
        border-left: 3px solid #0d6efd;
    }

    /* 批量操作按钮样式 */
    .batch-actions {
        display: none;
        margin-left: 8px;
    }

    .batch-actions .btn {
        padding: 4px 12px;
        font-size: 0.85rem;
        display: inline-flex;
        align-items: center;
        gap: 4px;
        transition: all 0.2s ease;
    }

    .batch-actions .btn i {
        font-size: 0.8rem;
    }

    /* 批量操作按钮悬停效果 */
    .batch-actions .btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0,0,0,0.15);
    }

    /* 批量禁用按钮样式 */
    .batch-toggle.btn-warning {
        background-color: #fd7e14;
        border-color: #fd7e14;
        color: #fff;
        font-weight: 500;
    }

    .batch-toggle.btn-warning:hover {
        background-color: #e96b02;
        border-color: #e96b02;
    }

    /* 批量启用按钮样式 */
    .batch-toggle.btn-success {
        background-color: #20c997;
        border-color: #20c997;
        color: #fff;
        font-weight: 500;
    }

    .batch-toggle.btn-success:hover {
        background-color: #0ca678;
        border-color: #0ca678;
    }

    /* 批量操作默认样式 */
    .batch-toggle.btn-primary {
        background-color: #0d6efd;
        border-color: #0d6efd;
        color: #fff;
        opacity: 0.85;
    }
    
    /* 批量删除按钮样式 */
    .batch-delete {
        background-color: #dc3545;
        border-color: #dc3545;
        color: #fff;
        font-weight: 500;
    }
    
    .batch-delete:hover {
        background-color: #bb2d3b;
        border-color: #b02a37;
        box-shadow: 0 4px 8px rgba(220, 53, 69, 0.3);
    }
    
    .batch-delete:disabled {
        background-color: #dc354580;
        border-color: #dc354580;
    }
    
    /* 按钮动画效果 */
    @keyframes pulse-animation {
        0% { transform: scale(1); }
        50% { transform: scale(1.05); }
        100% { transform: scale(1); }
    }
    
    .pulse-animation {
        animation: pulse-animation 0.5s ease;
    }

    /* 复选框样式优化 */
    .form-check-input {
        cursor: pointer;
    }

    /* 选中行数提示 */
    .selected-count {
        font-size: 0.85rem;
        color: #666;
        margin-left: 8px;
        display: none;
    }

    /* 用户特定的自定义样式 */
    .user-avatar {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        object-fit: cover;
    }

    .role-badge {
        padding: 0.35rem 0.65rem;
        font-size: 0.875rem;
        font-weight: 500;
        border-radius: 6px;
    }

    .role-admin {
        background-color: #e7f1ff;
        color: #0d6efd;
    }

    .role-teacher {
        background-color: #e7f5ea;
        color: #198754;
    }

    .role-student {
        background-color: #fff3cd;
        color: #664d03;
    }

    /* 权限列样式 */
    .permissions-info {
        max-width: 400px;
        word-wrap: break-word;
        white-space: normal;
        font-size: 0.85rem !important;
        line-height: 1.4;
        padding: 8px !important;
    }

    /* 表格响应式处理 */
    @media (max-width: 1200px) {
        .table th:nth-child(5),
        .table td:nth-child(5) {
            width: 300px;
            max-width: 300px;
        }
    }

    @media (max-width: 992px) {
        .table th:nth-child(5),
        .table td:nth-child(5) {
            width: 250px;
            max-width: 250px;
        }
    }

    /* 响应式调整 */
    @media (max-width: 768px) {
        .card-header {
            padding: 1rem;
        }

        .btn-group {
            flex-wrap: wrap;
        }

        .table td, .table th {
            padding: 8px;
        }
    }

    /* 操作列样式 */
    .operations-column {
        width: 180px !important;
        min-width: 180px !important;
        max-width: 180px !important;
    }

    /* 按钮组样式 */
    .table td .btn-group {
        display: flex;
        gap: 2px;
        justify-content: flex-start;
        width: 100%;
    }

    .table td .btn {
        padding: 0.2rem 0.4rem;
        font-size: 0.8125rem;
        border-radius: 4px;
        transition: all 0.2s ease;
        flex: 0 0 auto;
        min-width: auto;
        white-space: nowrap;
        line-height: 1.3;
    }

    /* 按钮悬停效果 */
    .table td .btn:hover {
        transform: translateY(-1px);
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }

    /* 表格单元格内容居中 */
    .table td, .table th {
        text-align: center;
    }

    /* 标题加粗 */
    .fw-bold {
        font-weight: 600 !important;
    }

    /* 卡片样式美化 */
    .card {
        border: none;
        border-radius: 12px;
        box-shadow: 0 4px 6px rgba(0,0,0,0.05);
        margin-bottom: 1.5rem;
    }

    .card-body {
        padding: 1.25rem;
    }

    /* 权限卡片样式 */
    #subjectPermissionsList .card {
        border: none;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        overflow: hidden;
        margin-bottom: 1rem;
    }

    #subjectPermissionsList .card-header {
        background: #f8fafc;
        border-bottom: 1px solid rgba(0,0,0,0.05);
        padding: 0.75rem 1rem;
    }

    #subjectPermissionsList .card-header h6 {
        color: #2c3e50;
        font-weight: 600;
        margin: 0;
    }

    #subjectPermissionsList .card-body {
        padding: 1rem;
        background: #fff;
    }

    /* 权限设置表单样式 */
    #permissionsSection .form-check {
        transition: all 0.3s ease;
        padding: 0.5rem 1rem;
        margin: 0.25rem 0;
        border-radius: 6px;
    }

    #permissionsSection .form-check:hover {
        background-color: rgba(0, 123, 255, 0.05);
    }

    #gradePermissionsList, #subjectPermissionsList {
        background: #fff;
        border-radius: 8px;
        border: 1px solid rgba(0,0,0,0.1);
    }

    /* 可点击选项改进样式 */
    .clickable-option {
        background: #f8fafc;
        padding: 0.75rem 1rem;
        border-radius: 8px;
        border: 1px solid rgba(0,0,0,0.05);
        margin-bottom: 0.5rem;
        cursor: pointer;
        transition: all 0.2s ease;
        position: relative;
        display: flex;
        align-items: center;
    }

    .clickable-option:hover {
        background-color: rgba(0, 123, 255, 0.05);
        border-color: #86b7fe;
        transform: translateX(3px);
        box-shadow: 0 2px 5px rgba(0,0,0,0.05);
    }

    .clickable-option.active {
        background: linear-gradient(to right, #e7f1ff, #ffffff);
        border-color: #0d6efd;
        box-shadow: 0 2px 5px rgba(13, 110, 253, 0.1);
    }

    .clickable-option input[type="checkbox"] {
        margin-right: 8px;
    }
</style>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="fas fa-users text-primary me-2"></i>账号管理
                    </h5>
                    <div class="d-flex">
                        <button class="btn btn-primary me-2" onclick="showAddUserModal()">
                            <i class="fas fa-plus-circle me-2"></i>添加用户
                        </button>
                        <button class="btn btn-success" onclick="showBatchImportModal()">
                            <i class="fas fa-file-import me-2"></i>批量创建账户
                        </button>
                    </div>
                </div>
                <div class="card-body p-3">
                    <!-- 添加搜索输入框 -->
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-search"></i></span>
                                <input type="text" class="form-control" id="userSearchInput" placeholder="搜索用户名或姓名">
                                <button class="btn btn-outline-secondary" type="button" id="clearSearchBtn" style="display:none;">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    <div id="userList" class="table-responsive">
                        <!-- 用户列表将通过 AJAX 加载 -->
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- 添加/编辑用户模态框 -->
<div class="modal fade" id="userModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">添加用户</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="关闭"></button>
            </div>
            <div class="modal-body">
                <form id="userForm">
                    <input type="hidden" name="id" id="userId">
                    <input type="hidden" name="permissions" id="permissions-data">
                    <!-- 第一行：用户名和姓名 -->
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">用户名</label>
                            <input type="text" class="form-control" name="username" id="username" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">姓名</label>
                            <input type="text" class="form-control" name="real_name" id="realName" required>
                        </div>
                    </div>

                    <!-- 第二行：密码和角色 -->
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">密码</label>
                            <input type="password" class="form-control" name="password" id="password">
                            <div class="form-text" id="passwordHint">编辑时留空表示不修改密码</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">角色</label>
                            <select class="form-select" name="role" id="role" required onchange="handleRoleChange()">
                                <option value="">请选择角色</option>
                                <option value="admin">系统管理员</option>
                                <option value="teaching">教导处</option>
                                <option value="headteacher">班主任</option>
                                <option value="marker">阅卷老师</option>
                            </select>
                        </div>
                    </div>

                    <!-- 第三行：权限设置 -->
                    <div id="permissionsSection" style="display: none;">
                        <hr>
                        <h6 class="mb-3 permissions-title">权限设置</h6>
                        <!-- 教导处权限说明 -->
                        <div id="teachingPermissions" style="display: none;">
                            <div class="alert alert-info">
                                <h6 class="alert-heading mb-2">教导处角色具备以下权限：</h6>
                                <ul class="mb-0">
                                    <li>管理所有学生信息数据</li>
                                    <li>管理所有学科测评数据</li>
                                    <li>查看和下载所有测评数据报表</li>
                                </ul>
                            </div>
                        </div>

                        <!-- 班主任权限设置 -->
                        <div id="headteacherPermissions" style="display: none;">
                            <div id="gradePermissionsList" class="border rounded p-3">
                                <!-- 年级权限列表将通过JavaScript动态加载 -->
                            </div>
                        </div>

                        <!-- 阅卷老师权限设置 -->
                        <div id="markerPermissions" style="display: none;">
                            <div id="subjectPermissionsList">
                                <!-- 学科权限列表将通过JavaScript动态加载 -->
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
                <button type="button" class="btn btn-primary" onclick="saveUser()">保存</button>
            </div>
        </div>
    </div>
</div>

<!-- 提示模态框 -->
<div class="modal fade" id="alertModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">提示</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p id="alertMessage"></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" data-bs-dismiss="modal">确定</button>
            </div>
        </div>
    </div>
</div>

<!-- 确认模态框 -->
<div class="modal fade" id="confirmModal" tabindex="-1">
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
                <button type="button" class="btn btn-danger" id="confirmModalYes">确定</button>
            </div>
        </div>
    </div>
</div>

<!-- 批量导入用户模态框 -->
<div class="modal fade" id="batchImportModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">批量创建账户</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">选择文件</label>
                    <input type="file" class="form-control" id="importUserFile" accept=".xlsx,.xls">
                    <small class="text-muted">支持 Excel 文件格式（.xlsx, .xls）</small>
                    <div class="mt-2">
                        <a href="../templates/download_user_template.php" class="btn btn-outline-primary btn-sm">
                            <i class="fas fa-download me-1"></i>下载导入模板
                        </a>
                        <small class="text-muted ms-2">请下载模板后用Excel打开填写数据</small>
                    </div>
                </div>
                <div class="alert alert-info">
                    <strong>导入说明：</strong>
                    <ul class="mb-0 ps-3">
                        <li>Excel文件需包含：用户名、姓名、角色代码（表头中不要包含星号*）</li>
                        <li>角色代码：0=管理员，1=教导处，2=班主任，3=阅卷老师</li>
                        <li>用户名不能重复，若已存在将被跳过并记录</li>
                        <li>姓名不能为空，否则将被跳过并记录</li>
                        <li>角色代码必须为0、1、2、3之一，否则将被跳过并记录</li>
                        <li>角色代码为0或1时，年段代码和学科代码将被自动忽略</li>
                        <li>角色代码为2(班主任)时，需提供有效的年段代码，学科代码将被忽略</li>
                        <li>角色代码为3(阅卷老师)时，需同时提供有效的年段代码和学科代码</li>
                        <li>默认密码为123456</li>
                    </ul>
                </div>
                <div id="roleCodeInfo" class="mt-3">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="d-flex justify-content-between">
                                <h6 class="mb-2">当前可用年段代码：</h6>
                                <div class="spinner-border spinner-border-sm text-primary" id="gradeCodesLoading" role="status">
                                    <span class="visually-hidden">加载中...</span>
                                </div>
                            </div>
                            <div id="gradeCodes" class="small text-muted">
                                加载中...
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="d-flex justify-content-between">
                                <h6 class="mb-2">当前可用学科代码：</h6>
                                <div class="spinner-border spinner-border-sm text-primary" id="subjectCodesLoading" role="status">
                                    <span class="visually-hidden">加载中...</span>
                                </div>
                            </div>
                            <div id="subjectCodes" class="small text-muted">
                                加载中...
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="d-flex justify-content-center mt-4">
                    <button type="button" class="btn btn-secondary me-2" data-bs-dismiss="modal">取消</button>
                    <button type="button" class="btn btn-primary" onclick="handleUserImport()">导入</button>
                </div>
            </div>
            <div class="modal-footer" style="display:none;">
                <!-- 按钮已移至上方 -->
            </div>
        </div>
    </div>
</div>

<!-- JavaScript 依赖 -->
<script src="../assets/js/jquery.min.js"></script>
<script src="../assets/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/sweetalert2.all.min.js"></script>
<script>
    // 使用window对象来存储全局变量，避免重复声明
    window.currentSettingId = null;  // 当前项目ID

    // 显示提示信息
    function showAlert(message, type = 'error') {
        Swal.fire({
            title: type === 'success' ? '成功' : '错误',
            text: message,
            icon: type,
            timer: type === 'success' ? 2000 : undefined,
            timerProgressBar: type === 'success',
            showConfirmButton: type !== 'success'
        });
    }

    // 显示确认对话框
    function showConfirm(message, callback) {
        Swal.fire({
            title: '确认',
            text: message,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d',
            confirmButtonText: '确定',
            cancelButtonText: '取消'
        }).then((result) => {
            if (result.isConfirmed) {
                callback();
            }
        });
    }

    // 加载用户列表
    function loadUsers() {
        const currentUserId = <?php echo isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'null'; ?>;
        console.log('加载用户列表...');

        $.get('../api/index.php?route=user/list', function(response) {
            if (response.success && response.data) {
                console.log('获取到用户数据:', response.data.length, '条记录');
                
                // 保存用户数据到全局变量
                window.userData = response.data.map(user => ({
                    ...user,
                    status: parseInt(user.status),
                    has_permissions: false // 默认设置为false，后续加载权限时更新
                }));
                
                // 默认按角色排序
                window.sortConfig = {
                    column: 'role',
                    direction: 'asc'
                };
                
                // 排序并渲染用户列表
                sortAndRenderUsers();
                
                // 重新绑定表头排序事件
                console.log('重新绑定表头排序事件');
                $('.sortable').off('click').on('click', function() {
                    const sortColumn = $(this).data('sort');
                    console.log('表头点击:', sortColumn);
                    
                    // 如果点击的是当前排序列，则切换排序方向
                    if (window.sortConfig.column === sortColumn) {
                        window.sortConfig.direction = window.sortConfig.direction === 'asc' ? 'desc' : 'asc';
                    } else {
                        // 否则，更新排序列并设置为升序
                        window.sortConfig.column = sortColumn;
                        window.sortConfig.direction = 'asc';
                    }
                    
                    console.log('排序配置更新为:', window.sortConfig);
                    
                    // 更新表头样式
                    updateSortHeaderStyles();
                    
                    // 重新排序并渲染用户列表
                    sortAndRenderUsers();
                });
            } else {
                $('#userList').html('<div class="alert alert-warning">加载用户列表失败</div>');
            }
        }).fail(function() {
            $('#userList').html('<div class="alert alert-danger">加载用户列表失败</div>');
        });
    }

    // 显示添加用户模态框
    function showAddUserModal() {
        $('#userId').val('');
        $('#userForm')[0].reset();
        $('.modal-title').text('添加用户');
        $('#password').prop('required', true);

        // 确保角色选择恢复到默认选项
        $('#role').val('');

        // 重新初始化自定义下拉框
        initCustomSelects();

        // 触发角色变更事件
        handleRoleChange();

        loadPermissionSettings();
        var userModal = new bootstrap.Modal(document.getElementById('userModal'));
        userModal.show();
    }

    // 编辑用户
    function editUser(id) {
        $('#userForm')[0].reset();
        $('#userId').val(id);
        $('#permissions-data').val('');  // 清空权限数据
        $('.modal-title').text('编辑用户');
        $('#passwordHint').show();
        $('#password').prop('required', false);  // 编辑时密码不是必填

        // 显示加载中提示
        Swal.fire({
            title: '加载中',
            text: '正在加载用户信息...',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        // 获取用户基本信息和权限信息
        Promise.all([
            // 请求1：获取用户基本信息
            $.get('../api/index.php?route=user/get', { id: id }),
            // 请求2：获取用户权限信息
            $.get('../api/index.php?route=user/permissions', { user_id: id })
        ]).then(function([userResponse, permResponse]) {
            // 处理用户基本信息
            if (!userResponse.success) {
                throw new Error(userResponse.error || '加载用户信息失败');
            }
            
            const user = userResponse.data;
            $('#username').val(user.username);
            $('#realName').val(user.real_name);
            $('#role').val(user.role);
            
            // 记住用户角色，用于权限处理
            const userRole = user.role;
            
            // 重新初始化自定义下拉框
            initCustomSelects();
            
            // 触发角色变更事件以显示相应的权限设置
            handleRoleChange();
            
            // 关闭加载提示
            Swal.close();
            
            // 打开模态框
            const modal = new bootstrap.Modal(document.getElementById('userModal'));
            modal.show();
            
            // 在模态框显示完成后设置权限（使用更长的延时）
            setTimeout(() => {
                // 处理权限信息
                if ((userRole === 'headteacher' || userRole === 'marker') && permResponse.success) {
                    console.log('准备设置权限:', permResponse.data);
                    
                    // 显示权限加载指示器
                    const $permsSection = $('#permissionsSection');
                    $permsSection.prepend('<div id="perms-loading" class="text-center mb-3"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">加载权限中...</span></div><p class="mt-2">加载权限中...</p></div>');
                    
                    try {
                        // 清除已有的权限选中状态
                        $('input.grade-permission, input.subject-permission').prop('checked', false);
                        $('.clickable-option').removeClass('active');
                        
                        // 确保DOM已完全更新
                        setTimeout(() => {
                            try {
                                // 保存权限数据到隐藏字段
                                $('#permissions-data').val(JSON.stringify(permResponse.data));
                                
                                // 首次设置已有权限
                                setExistingPermissions(permResponse.data);
                                
                                // 再次延时，确保权限正确设置
                                setTimeout(() => {
                                    // 二次确认权限设置
                                    setExistingPermissions(permResponse.data);
                                    
                                    // 确保clickable-option正确初始化
                                    initClickableOptions();
                                    
                                    // 打印权限设置结果统计
                                    const checkedCount = $('input.grade-permission:checked, input.subject-permission:checked').length;
                                    const activeCount = $('.clickable-option.active').length;
                                    console.log(`权限设置完成：选中的复选框 ${checkedCount} 个，激活的选项 ${activeCount} 个`);
                                }, 500);
                            } catch (e) {
                                console.error('设置权限时出错:', e);
                            }
                        }, 500);
                    } catch (e) {
                        console.error('设置权限时出错:', e);
                    } finally {
                        // 延时移除加载指示器，确保用户能看到加载过程
                        setTimeout(() => {
                            $('#perms-loading').remove();
                        }, 1500);
                    }
                }
            }, 1000);
            
        }).catch(function(error) {
            Swal.close();
            console.error('加载用户数据失败:', error);
            showAlert(error.message || '加载用户数据失败，请稍后重试');
        });
    }

    // 保存用户信息
    function saveUser() {
        // 防止重复提交
        if (window.isSubmittingUser) {
            console.log('正在提交中，忽略重复点击');
            return;
        }
        window.isSubmittingUser = true;
        
        const role = $('#role').val();
        const userId = $('#userId').val();
        
        // 收集权限数据
        let permissions = [];
        
        if (role === 'headteacher') {
            // 收集班主任的年级权限
            $('.grade-permission:checked').each(function() {
                const gradeId = $(this).data('grade-id');
                permissions.push({
                    grade_id: gradeId,
                    can_edit: 1,
                    can_download: 1,
                    can_edit_students: 1
                });
            });
        } else if (role === 'marker') {
            // 收集阅卷老师的学科权限
            $('.subject-permission:checked').each(function() {
                const gradeId = $(this).data('grade-id');
                const subjectId = $(this).data('subject-id');
                permissions.push({
                    grade_id: gradeId,
                    subject_id: subjectId,
                    can_edit: 1,
                    can_download: 0,
                    can_edit_students: 0
                });
            });
        }
        
        // 准备用户数据
        const formData = {
            username: $('#username').val(),
            real_name: $('#realName').val(),
            role: role,
            password: $('#password').val(),
            permissions: JSON.stringify(permissions)
        };
        
        if (userId) {
            formData.id = userId;
        }
        
        //console.log('准备提交的用户数据:', formData);
        //console.log('权限数据:', permissions);
        
        // 保存用户基本信息
        $.ajax({
            url: '../api/index.php?route=' + (userId ? 'user/update' : 'user/add'),
            method: 'POST',
            data: formData,
            success: function(response) {
                if (response.success) {
                    const newUserId = userId || response.data.id;
                    
                    // 如果有权限数据，更新权限
                    if (permissions.length > 0) {
                        const permissionData = {
                            user_id: newUserId,
                            role: role,
                            permissions: JSON.stringify(permissions)
                        };
                        
                        // 更新权限
                        $.ajax({
                            url: '../api/index.php?route=user/update_permissions',
                            method: 'POST',
                            data: permissionData,
                            success: function(permResponse) {
                                if (permResponse.success) {
                                    Swal.fire({
                                        icon: 'success',
                                        title: userId ? '更新成功' : '添加成功',
                                        text: '用户信息和权限已保存',
                                        timer: 2000,
                                        showConfirmButton: false
                                    });
                                } else {
                                    console.error('权限更新失败:', permResponse.error);
                                    Swal.fire({
                                        icon: 'warning',
                                        title: '部分成功',
                                        text: '用户信息已保存，但权限设置失败: ' + (permResponse.error || '未知错误'),
                                        timer: 5000
                                    });
                                }
                                // 关闭模态框
                                const userModal = bootstrap.Modal.getInstance(document.getElementById('userModal'));
                                userModal.hide();
                                // 重新加载用户数据
                                loadUsers();
                            },
                            error: function(xhr) {
                                console.error('权限更新请求失败:', xhr);
                                Swal.fire({
                                    icon: 'warning',
                                    title: '部分成功',
                                    text: '用户信息已保存，但权限设置失败',
                                    timer: 3000
                                });
                                // 关闭模态框并重新加载
                                const userModal = bootstrap.Modal.getInstance(document.getElementById('userModal'));
                                userModal.hide();
                                loadUsers();
                            }
                        });
                    } else {
                        // 没有权限数据，直接显示成功
                        Swal.fire({
                            icon: 'success',
                            title: userId ? '更新成功' : '添加成功',
                            text: response.message || (userId ? '用户信息已更新' : '新用户已创建'),
                            timer: 2000,
                            showConfirmButton: false
                        });
                        // 关闭模态框
                        const userModal = bootstrap.Modal.getInstance(document.getElementById('userModal'));
                        userModal.hide();
                        // 重新加载用户数据
                        loadUsers();
                    }
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: '保存失败',
                        text: response.error || '操作失败，请稍后重试'
                    });
                }
                // 重置提交标志
                window.isSubmittingUser = false;
            },
            error: function(xhr) {
                console.error('保存用户请求失败:', xhr);
                Swal.fire({
                    icon: 'error',
                    title: '保存失败',
                    text: '请求失败，请检查网络连接后重试'
                });
                // 重置提交标志
                window.isSubmittingUser = false;
            }
        });
    }

    // 处理角色变更
    function handleRoleChange() {
        const role = $('#role').val();
        const $permissionsSection = $('#permissionsSection');
        const $teachingPerms = $('#teachingPermissions');
        const $headteacherPerms = $('#headteacherPermissions');
        const $markerPerms = $('#markerPermissions');

        //console.log('处理角色变更:', role);
        
        // 隐藏所有权限选项
        $permissionsSection.hide();
        $teachingPerms.hide();
        $headteacherPerms.hide();
        $markerPerms.hide();

        if (role === 'teaching') {
            $permissionsSection.show();
            $teachingPerms.show();
        } else if (role === 'headteacher') {
            $permissionsSection.show();
            $('.permissions-title').text('权限设置');
            $headteacherPerms.show();
            
            // 加载年级权限并在加载完成后应用已有权限
            loadGradePermissions(function() {
                console.log('年级权限加载完成，准备应用已有权限');
                
                // 如果有已保存的权限数据，重新应用
                const savedPermissions = $('#permissions-data').val();
                if (savedPermissions) {
                    try {
                        const permissionsData = JSON.parse(savedPermissions);
                        if (Array.isArray(permissionsData) && permissionsData.length > 0) {
                            console.log('应用已保存的权限数据:', permissionsData);
                            setTimeout(() => setExistingPermissions(permissionsData), 300);
                        }
                    } catch (e) {
                        console.error('解析权限数据失败:', e);
                    }
                }
            });
        } else if (role === 'marker') {
            $permissionsSection.show();
            $('.permissions-title').text('数据录入权限设置');
            $markerPerms.show();
            
            // 加载学科权限并在加载完成后应用已有权限
            loadSubjectPermissions(function() {
                console.log('学科权限加载完成，准备应用已有权限');
                
                // 如果有已保存的权限数据，重新应用
                const savedPermissions = $('#permissions-data').val();
                if (savedPermissions) {
                    try {
                        const permissionsData = JSON.parse(savedPermissions);
                        if (Array.isArray(permissionsData) && permissionsData.length > 0) {
                            console.log('应用已保存的权限数据:', permissionsData);
                            setTimeout(() => setExistingPermissions(permissionsData), 300);
                        }
                    } catch (e) {
                        console.error('解析权限数据失败:', e);
                    }
                }
            });
        }
    }

    // 加载年级权限设置
    function loadGradePermissions(callback) {
        console.log('开始加载年级权限设置');
        $('#gradePermissionsList').html('<div class="spinner-border text-primary" role="status"><span class="visually-hidden">加载中...</span></div>');
        
        $.get('../api/index.php?route=settings/grades', function(response) {
            if (response.success) {
                let html = '<div class="grade-list">';
                response.data.forEach(function(grade) {
                    html += `
                        <div class="form-check mb-2">
                            <input class="form-check-input grade-permission" type="checkbox"
                                value="${grade.id}" id="grade_${grade.id}"
                                data-grade-id="${grade.id}">
                            <label class="form-check-label" for="grade_${grade.id}">
                                ${grade.grade_name} - 学生信息管理权限
                            </label>
                        </div>`;
                });
                html += '</div>';
                $('#gradePermissionsList').html(html);
                
                // 在加载完年级权限后初始化可点击选项
                console.log('年级权限加载完成，初始化可点击选项');
                initClickableOptions();
                
                // 调用回调函数
                if (typeof callback === 'function') {
                    callback();
                }
            } else {
                $('#gradePermissionsList').html(`<div class="text-danger">${response.error || '加载年级信息失败'}</div>`);
                if (typeof callback === 'function') {
                    callback();
                }
            }
        }).fail(function(xhr) {
            $('#gradePermissionsList').html(`<div class="text-danger">加载年级信息失败：${xhr.statusText}</div>`);
            if (typeof callback === 'function') {
                callback();
            }
        });
    }

    // 加载学科权限设置
    function loadSubjectPermissions(callback) {
        console.log('开始加载学科权限设置');
        $('#subjectPermissionsList').html('<div class="spinner-border text-primary" role="status"><span class="visually-hidden">加载中...</span></div>');
        
        $.get('../api/index.php?route=settings/grades', function(response) {
            if (response.success) {
                let html = '';
                response.data.forEach(function(grade) {
                    html += `
                        <div class="card mb-3">
                            <div class="card-header">
                                <h6 class="mb-0">${grade.grade_name}</h6>
                            </div>
                            <div class="card-body">
                                <div class="subjects-list grade-list" data-grade-id="${grade.id}">
                                    <div class="spinner-border spinner-border-sm" role="status">
                                        <span class="visually-hidden">加载中...</span>
                                    </div>
                                </div>
                            </div>
                        </div>`;
                });
                $('#subjectPermissionsList').html(html);

                // 加载每个年级的学科列表
                let loadedCount = 0;
                const totalCount = response.data.length;
                
                if (totalCount === 0) {
                    // 如果没有年级数据，直接调用回调
                    console.log('没有年级数据，直接调用回调');
                    if (typeof callback === 'function') {
                        callback();
                    }
                    return;
                }

                response.data.forEach(grade => {
                    loadGradeSubjects(grade.id, () => {
                        loadedCount++;
                        console.log(`已加载 ${loadedCount}/${totalCount} 个年级的学科`);
                        
                        // 当所有年级的学科都加载完成后，初始化可点击选项并调用回调
                        if (loadedCount === totalCount) {
                            console.log('所有学科加载完成，初始化可点击选项');
                            initClickableOptions();
                            
                            if (typeof callback === 'function') {
                                callback();
                            }
                        }
                    });
                });
            } else {
                $('#subjectPermissionsList').html(`<div class="text-danger">${response.error || '加载年级信息失败'}</div>`);
                if (typeof callback === 'function') {
                    callback();
                }
            }
        }).fail(function(xhr) {
            $('#subjectPermissionsList').html(`<div class="text-danger">加载年级信息失败：${xhr.statusText}</div>`);
            if (typeof callback === 'function') {
                callback();
            }
        });
    }

    // 加载年级的学科列表
    function loadGradeSubjects(gradeId, callback) {
        const $subjectsList = $(`.subjects-list[data-grade-id="${gradeId}"]`);

        $.get('../api/index.php?route=settings/grade/subjects', {
            grade_id: gradeId,
            setting_id: window.currentSettingId
        })
            .done(function(response) {
                if (response.success) {
                    if (!response.data || response.data.length === 0) {
                        $subjectsList.html('<div class="text-muted">当前项目下暂无可用学科</div>');
                    } else {
                        let html = '<div class="row g-3">';
                        response.data.forEach(function(subject) {
                            // 使用唯一ID确保没有HTML ID冲突
                            const uniqueId = `edit_${gradeId}_${subject.id}_${new Date().getTime()}`;
                            html += `
                                <div class="col-md-4 col-lg-3">
                                    <div class="form-check">
                                        <input class="form-check-input subject-permission" type="checkbox"
                                            value="edit" id="${uniqueId}"
                                            data-grade-id="${gradeId}"
                                            data-subject-id="${subject.id}"
                                            data-permission-type="edit">
                                        <label class="form-check-label" for="${uniqueId}">
                                            ${subject.subject_name}
                                        </label>
                                    </div>
                                </div>`;
                        });
                        html += '</div>';
                        $subjectsList.html(html);
                        
                        // 确保所有事件绑定正确
                        setTimeout(() => {
                            initClickableOptions();
                        }, 100);
                    }
                } else {
                    $subjectsList.html(`<div class="text-danger">${response.error || '加载学科失败'}</div>`);
                }
                if (typeof callback === 'function') {
                    callback();
                }
            })
            .fail(function(jqXHR) {
                $subjectsList.html(`<div class="text-danger">${jqXHR.responseJSON?.error || '加载学科失败'}</div>`);
                if (typeof callback === 'function') {
                    callback();
                }
            });
    }

    // 设置已有权限
    function setExistingPermissions(permissions) {
        const role = $('#role').val();
        console.log('设置已有权限', role, permissions);

        // 确保permissions是数组
        if (!Array.isArray(permissions)) {
            console.error('Invalid permissions data', permissions);
            return;
        }
        
        // 如果权限数组为空，不需要执行后续操作
        if (permissions.length === 0) {
            console.log('权限数组为空，不执行设置操作');
            return;
        }
        
        // 添加用于防止多次重复执行的标志
        if (window.isSettingPermissions) {
            console.log('已经在设置权限中，忽略重复调用');
            return;
        }
        
        window.isSettingPermissions = true;
        
        try {
            // 添加延时处理，确保DOM元素已完全加载
            setTimeout(() => {
                try {
                    // 保存权限数据到hidden字段，供其他函数使用
                    $('#permissions-data').val(JSON.stringify(permissions));
                    
                    if (role === 'headteacher') {
                        // 首先取消选中所有年级权限
                        $('input.grade-permission').prop('checked', false);
                        $('.clickable-option').removeClass('active');
                        
                        // 收集所有年级ID，用于后续处理
                        const gradeIds = new Set();
                        
                        // 然后根据已有权限设置选中状态
                        permissions.forEach(function(perm) {
                            try {
                                // 检查权限对象的完整性
                                if (perm && perm.grade_id && (perm.can_edit_students === '1' || perm.can_edit_students === 1)) {
                                    console.log('处理班主任权限', perm.grade_id);
                                    gradeIds.add(perm.grade_id.toString());
                                    
                                    // 查找并标记所有匹配的复选框
                                    // 1. 直接查找原始复选框
                                    const $checkboxes = $(`input.grade-permission[data-grade-id="${perm.grade_id}"]`);
                                    $checkboxes.prop('checked', true);
                                    
                                    // 2. 查找clickable-option包装的复选框
                                    const $wrappedCheckboxes = $(`.clickable-option input[data-grade-id="${perm.grade_id}"]`);
                                    $wrappedCheckboxes.prop('checked', true);
                                    $wrappedCheckboxes.closest('.clickable-option').addClass('active');
                                    
                                    const totalFound = $checkboxes.length + $wrappedCheckboxes.length;
                                    if (totalFound > 0) {
                                        console.log(`找到并勾选了 ${totalFound} 个班主任权限复选框`, perm.grade_id);
                                    } else {
                                        console.warn('未找到年级权限复选框', perm.grade_id);
                                    }
                                }
                            } catch (e) {
                                console.error('设置班主任权限出错', e, perm);
                            }
                        });
                        
                        // 确保所有grade-permission的clickable-option状态与复选框一致
                        $('input.grade-permission').each(function() {
                            const $checkbox = $(this);
                            const gradeId = $checkbox.data('grade-id');
                            const shouldBeChecked = gradeIds.has(gradeId.toString());
                            
                            // 设置复选框状态
                            $checkbox.prop('checked', shouldBeChecked);
                            
                            // 设置父容器状态
                            const $parent = $checkbox.closest('.clickable-option');
                            if ($parent.length) {
                                $parent.toggleClass('active', shouldBeChecked);
                            }
                        });
                    } else if (role === 'marker') {
                        // 首先取消选中所有学科权限
                        $('input.subject-permission').prop('checked', false);
                        $('.clickable-option').removeClass('active');
                        
                        // 收集所有年级-学科组合，用于后续处理
                        const subjectMap = new Map();
                        
                        // 然后根据已有权限设置选中状态
                        permissions.forEach(function(perm) {
                            try {
                                // 检查权限对象的完整性
                                if (perm && perm.grade_id && perm.subject_id && (perm.can_edit === '1' || perm.can_edit === 1)) {
                                    console.log('处理阅卷老师权限', perm.grade_id, perm.subject_id);
                                    const key = `${perm.grade_id}-${perm.subject_id}`;
                                    subjectMap.set(key, { gradeId: perm.grade_id, subjectId: perm.subject_id });
                                    
                                    // 查找并标记所有匹配的复选框
                                    // 1. 直接查找原始复选框
                                    const $checkboxes = $(`input.subject-permission[data-grade-id="${perm.grade_id}"][data-subject-id="${perm.subject_id}"]`);
                                    $checkboxes.prop('checked', true);
                                    
                                    // 2. 查找clickable-option包装的复选框
                                    const $wrappedCheckboxes = $(`.clickable-option input[data-grade-id="${perm.grade_id}"][data-subject-id="${perm.subject_id}"]`);
                                    $wrappedCheckboxes.prop('checked', true);
                                    $wrappedCheckboxes.closest('.clickable-option').addClass('active');
                                    
                                    const totalFound = $checkboxes.length + $wrappedCheckboxes.length;
                                    if (totalFound > 0) {
                                        console.log(`找到并勾选了 ${totalFound} 个阅卷老师权限复选框`, perm.grade_id, perm.subject_id);
                                    } else {
                                        console.warn('未找到学科权限复选框', perm.grade_id, perm.subject_id);
                                    }
                                }
                            } catch (e) {
                                console.error('设置阅卷老师权限出错', e, perm);
                            }
                        });
                        
                        // 确保所有subject-permission的clickable-option状态与复选框一致
                        $('input.subject-permission').each(function() {
                            const $checkbox = $(this);
                            const gradeId = $checkbox.data('grade-id');
                            const subjectId = $checkbox.data('subject-id');
                            const key = `${gradeId}-${subjectId}`;
                            const shouldBeChecked = subjectMap.has(key);
                            
                            // 设置复选框状态
                            $checkbox.prop('checked', shouldBeChecked);
                            
                            // 设置父容器状态
                            const $parent = $checkbox.closest('.clickable-option');
                            if ($parent.length) {
                                $parent.toggleClass('active', shouldBeChecked);
                            }
                        });
                    }
                    
                    // 最后，再次调用initClickableOptions确保UI状态一致
                    setTimeout(() => {
                        initClickableOptions();
                        console.log('权限设置完成，已重新初始化可点击选项');
                    }, 200);
                } catch (e) {
                    console.error('设置权限时发生错误:', e);
                } finally {
                    window.isSettingPermissions = false;
                }
            }, 500);
        } catch (e) {
            console.error('设置权限外层处理出错:', e);
            window.isSettingPermissions = false;
        }
    }

    // 删除用户点击事件
    $(document).on('click', '.delete-user', function() {
        if ($(this).prop('disabled')) {
            const title = $(this).attr('title');
            if (title) {
                showAlert(title);
            }
            return;
        }

        const id = $(this).data('id');
        const $row = $(this).closest('tr');
        const username = $row.find('td:eq(1)').text().trim().replace('当前账号', '').trim();
        const realName = $row.find('td:eq(2)').text().trim();

        showConfirm(
            `确定要删除用户"${realName}"(${username})吗？\n此操作不可恢复。`,
            function() {
                $.ajax({
                    url: '../api/index.php?route=user/delete',
                    method: 'POST',
                    data: { id: id },
                    success: function(response) {
                        if (response.success) {
                            showAlert('删除成功', 'success');
                            
                            // 从本地数据中移除已删除的用户
                            window.userData = window.userData.filter(user => user.id != id);
                            
                            // 重新排序并渲染
                            sortAndRenderUsers();
                        } else {
                            showAlert(response.error || '删除失败');
                        }
                    },
                    error: function(xhr) {
                        showAlert('删除失败：' + (xhr.responseJSON?.error || '未知错误'));
                    }
                });
            }
        );
    });

    // 加载权限设置
    function loadPermissionSettings(selectedPermissions = []) {
        // 确保 selectedPermissions 是数组
        selectedPermissions = Array.isArray(selectedPermissions) ? selectedPermissions : [];

        $.get('../api/index.php?route=settings/grades', function(response) {
            if (!response.success) {
                showAlert(response.error || '加载年级信息失败');
                return;
            }

            const role = $('#role').val();

            // 根据角色显示不同的权限选项
            if (role === 'teaching') {
                $('#permissionsSection').show();
                $('#teachingPermissions').show();
                $('#headteacherPermissions').hide();
                $('#markerPermissions').hide();
            } else if (role === 'headteacher') {
                $('#permissionsSection').show();
                $('#teachingPermissions').hide();
                $('#headteacherPermissions').show();
                $('#markerPermissions').hide();
                loadGradePermissions();
            } else if (role === 'marker') {
                $('#permissionsSection').show();
                $('#teachingPermissions').hide();
                $('#headteacherPermissions').hide();
                $('#markerPermissions').show();
                loadSubjectPermissions();
            } else {
                $('#permissionsSection').hide();
            }
        });
    }

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

    // 在页面加载完成后初始化
    $(document).ready(function() {
        // 初始化自定义下拉框
        initCustomSelects();

        // 在加载数据后重新初始化
        loadUsers();

        // 处理模态框事件，解决可访问性问题
        $('#userModal').on('shown.bs.modal', function () {
            // 设置焦点到第一个表单域
            $('#username').focus();
        });

        $('#userModal').on('hidden.bs.modal', function () {
            // 清理可能的aria-hidden问题
            $('.modal-backdrop').remove();
            $('[aria-hidden="true"]').not('#userModal').removeAttr('aria-hidden');
            
            // 恢复页面状态
            setTimeout(() => {
                // 重新绑定事件
                initClickableOptions();
            }, 100);
        });
    });

    // 初始化可点击选项
    function initClickableOptions() {
        console.log('初始化可点击选项');
        
        // 先收集所有已经选中的复选框信息，用于稍后恢复
        const selectedGradeIds = new Set();
        const selectedSubjects = new Map();
        
        // 收集年级权限
        $('input.grade-permission:checked').each(function() {
            const gradeId = $(this).data('grade-id');
            if (gradeId) {
                selectedGradeIds.add(gradeId);
                console.log('收集到已选中的年级权限:', gradeId);
            }
        });
        
        // 收集学科权限
        $('input.subject-permission:checked').each(function() {
            const gradeId = $(this).data('grade-id');
            const subjectId = $(this).data('subject-id');
            if (gradeId && subjectId) {
                const key = `${gradeId}-${subjectId}`;
                selectedSubjects.set(key, { gradeId, subjectId });
                console.log('收集到已选中的学科权限:', gradeId, subjectId);
            }
        });
        
        // 包装年级复选框
        $('.form-check').each(function() {
            const $formCheck = $(this);
            if (!$formCheck.parent().hasClass('clickable-option')) {
                const $input = $formCheck.find('input[type="checkbox"]');
                const $label = $formCheck.find('label');

                if ($input.length && $label.length) {
                    // 保存原始复选框的数据属性
                    const gradeId = $input.data('grade-id');
                    const subjectId = $input.data('subject-id');
                    const permissionType = $input.data('permission-type');
                    let isChecked = $input.prop('checked');
                    const inputId = $input.attr('id');
                    const inputValue = $input.val();
                    const inputClass = $input.attr('class');
                    
                    // 根据之前收集的选中状态再次检查
                    if (!isChecked && gradeId) {
                        if ($input.hasClass('grade-permission') && selectedGradeIds.has(gradeId)) {
                            isChecked = true;
                            console.log('根据收集的状态，年级权限应该选中:', gradeId);
                        } else if ($input.hasClass('subject-permission') && subjectId) {
                            const key = `${gradeId}-${subjectId}`;
                            if (selectedSubjects.has(key)) {
                                isChecked = true;
                                console.log('根据收集的状态，学科权限应该选中:', gradeId, subjectId);
                            }
                        }
                    }
                    
                    // 记录创建前的状态
                    if (isChecked) {
                        console.log('创建包装前复选框已选中', gradeId, subjectId);
                    }
                    
                    // 创建新的包装元素，保留原始数据属性
                    const $wrapper = $('<div></div>')
                        .addClass('clickable-option')
                        .append(
                            $('<input>')
                                .attr('type', 'checkbox')
                                .attr('id', inputId)
                                .attr('class', inputClass)
                                .val(inputValue)
                                .prop('checked', isChecked)
                                .data('grade-id', gradeId)
                                .data('subject-id', subjectId)
                                .data('permission-type', permissionType)
                        )
                        .append($label.clone());

                    if (isChecked) {
                        $wrapper.addClass('active');
                        console.log('已将包装元素设为active', gradeId, subjectId);
                    }

                    $formCheck.replaceWith($wrapper);
                    
                    // 记录创建后的状态
                    if (isChecked) {
                        const $newInput = $wrapper.find('input[type="checkbox"]');
                        console.log('创建包装后复选框状态:', $newInput.prop('checked'));
                        console.log('包装元素是否有active类:', $wrapper.hasClass('active'));
                    }
                }
            } else {
                // 如果已经是包装元素的子元素，确保状态一致
                const $parent = $formCheck.parent('.clickable-option');
                const $input = $formCheck.find('input[type="checkbox"]');
                if ($input.length) {
                    const gradeId = $input.data('grade-id');
                    const subjectId = $input.data('subject-id');
                    let isChecked = $input.prop('checked');
                    
                    // 根据之前收集的选中状态再次检查
                    if (!isChecked && gradeId) {
                        if ($input.hasClass('grade-permission') && selectedGradeIds.has(gradeId)) {
                            isChecked = true;
                            $input.prop('checked', true);
                            console.log('根据收集的状态，设置已有元素中的年级权限选中:', gradeId);
                        } else if ($input.hasClass('subject-permission') && subjectId) {
                            const key = `${gradeId}-${subjectId}`;
                            if (selectedSubjects.has(key)) {
                                isChecked = true;
                                $input.prop('checked', true);
                                console.log('根据收集的状态，设置已有元素中的学科权限选中:', gradeId, subjectId);
                            }
                        }
                    }
                    
                    $parent.toggleClass('active', isChecked);
                }
            }
        });
        
        // 处理已经存在的clickable-option元素内的复选框
        $('.clickable-option').each(function() {
            const $option = $(this);
            const $input = $option.find('input[type="checkbox"]');
            
            if ($input.length) {
                const gradeId = $input.data('grade-id');
                const subjectId = $input.data('subject-id');
                let isChecked = $input.prop('checked');
                
                // 根据之前收集的选中状态再次检查
                if (!isChecked && gradeId) {
                    if ($input.hasClass('grade-permission') && selectedGradeIds.has(gradeId)) {
                        isChecked = true;
                        $input.prop('checked', true);
                        console.log('设置已有clickable-option中的年级权限选中:', gradeId);
                    } else if ($input.hasClass('subject-permission') && subjectId) {
                        const key = `${gradeId}-${subjectId}`;
                        if (selectedSubjects.has(key)) {
                            isChecked = true;
                            $input.prop('checked', true);
                            console.log('设置已有clickable-option中的学科权限选中:', gradeId, subjectId);
                        }
                    }
                }
                
                $option.toggleClass('active', isChecked);
                
                if (isChecked) {
                    console.log('已有clickable-option中的复选框已选中', 
                        $input.data('grade-id'), 
                        $input.data('subject-id'));
                }
            }
        });

        // 移除之前的事件监听器
        $(document).off('click.clickableOption');
        
        // 使用事件委托绑定点击事件到clickable-option元素
        $(document).on('click.clickableOption', '.clickable-option', function(e) {
            // 如果点击的是复选框本身，不需额外处理
            if ($(e.target).is('input[type="checkbox"]')) {
                const $input = $(e.target);
                const newState = $input.prop('checked');
                $(this).toggleClass('active', newState);
                console.log('复选框直接点击:', 
                    $input.data('grade-id'), 
                    $input.data('subject-id'), 
                    '新状态:', newState);
                return;
            }
            
            // 如果点击的是选项区域
            const $option = $(this);
            const $input = $option.find('input[type="checkbox"]');
            
            // 切换复选框状态
            const newCheckedState = !$input.prop('checked');
            $input.prop('checked', newCheckedState);
            $option.toggleClass('active', newCheckedState);
            
            console.log('选项区域点击:', 
                $input.data('grade-id'), 
                $input.data('subject-id'), 
                '新状态:', newCheckedState);
            
            // 确保复选框的change事件被触发
            $input.trigger('change');
        });
    }

    // 显示批量导入用户模态框
    function showBatchImportModal() {
        // 清空文件输入
        $('#importUserFile').val('');
        
        // 加载年段和学科代码信息
        loadGradeCodesInfo();
        loadSubjectCodesInfo();
        
        // 显示模态框
        const batchImportModal = new bootstrap.Modal(document.getElementById('batchImportModal'));
        batchImportModal.show();
    }
    
    // 加载年段代码信息
    function loadGradeCodesInfo() {
        $('#gradeCodesLoading').show();
        $('#gradeCodes').text('加载中...');
        
        $.get('../api/index.php?route=settings/grades')
            .done(function(response) {
                if (response.success && response.data) {
                    const grades = response.data;
                    let html = '<table class="table table-sm table-bordered">';
                    html += '<thead><tr><th>年段名称</th><th>年段代码</th></tr></thead><tbody>';
                    
                    grades.forEach(function(grade) {
                        html += `<tr><td>${grade.grade_name}</td><td>${grade.grade_code}</td></tr>`;
                    });
                    
                    html += '</tbody></table>';
                    $('#gradeCodes').html(html);
                } else {
                    $('#gradeCodes').text('暂无可用年段');
                }
            })
            .fail(function() {
                $('#gradeCodes').text('加载年段信息失败');
            })
            .always(function() {
                $('#gradeCodesLoading').hide();
            });
    }
    
    // 加载学科代码信息
    function loadSubjectCodesInfo() {
        $('#subjectCodesLoading').show();
        $('#subjectCodes').text('加载中...');
        
        $.get('../api/index.php?route=settings/subjects', { setting_id: window.currentSettingId })
            .done(function(response) {
                if (response.success && response.data) {
                    const subjects = response.data;
                    let html = '<table class="table table-sm table-bordered">';
                    html += '<thead><tr><th>学科名称</th><th>学科代码</th><th>适用年级</th></tr></thead><tbody>';
                    
                    subjects.forEach(function(subject) {
                        const gradeNames = Array.isArray(subject.grade_names) ? subject.grade_names.join('、') : '-';
                        html += `<tr><td>${subject.subject_name}</td><td>${subject.subject_code}</td><td>${gradeNames}</td></tr>`;
                    });
                    
                    html += '</tbody></table>';
                    $('#subjectCodes').html(html);
                } else {
                    $('#subjectCodes').text('暂无可用学科');
                }
            })
            .fail(function() {
                $('#subjectCodes').text('加载学科信息失败');
            })
            .always(function() {
                $('#subjectCodesLoading').hide();
            });
    }
    
    // 处理用户批量导入
    function handleUserImport() {
        const fileInput = $('#importUserFile')[0];
        
        if (!fileInput.files || !fileInput.files[0]) {
            Swal.fire({
                title: '提示',
                text: '请选择要导入的文件',
                icon: 'warning',
                showConfirmButton: false,
                timer: 2000
            });
            return;
        }

        // 检查文件类型
        const file = fileInput.files[0];
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
                timer: 2000
            });
            return;
        }

        const formData = new FormData();
        formData.append('file', file);
        formData.append('setting_id', window.currentSettingId);

        // 显示加载提示
        Swal.fire({
            title: '正在导入',
            text: '请稍候...',
            allowOutsideClick: false,
            showConfirmButton: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        $.ajax({
            url: '../api/index.php?route=user/batch_import',
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                console.log('用户导入响应:', response);
                if (response.success) {
                    const batchImportModal = bootstrap.Modal.getInstance(document.getElementById('batchImportModal'));
                    batchImportModal.hide();
                    
                    // 构建导入结果HTML
                    let resultHtml = '<div style="text-align: left;">';
                    
                    // 导入成功部分
                    resultHtml += `<div class="mb-3">
                        <h5 class="text-success"><i class="fas fa-check-circle me-2"></i>导入成功</h5>
                        <div class="ps-4">成功导入 ${response.data?.success_count || 0} 个用户</div>
                    </div>`;
                    
                    // 导入失败部分（如果有）
                    if (response.message && response.message.includes('但存在以下错误')) {
                        resultHtml += `<div>
                            <h5 class="text-danger"><i class="fas fa-exclamation-circle me-2"></i>导入失败项</h5>
                            <div class="ps-4">${response.message.split('但存在以下错误：')[1] || ''}</div>
                        </div>`;
                    }
                    
                    resultHtml += '</div>';
                    
                    Swal.fire({
                        title: '导入结果',
                        html: resultHtml,
                        icon: 'info',
                        confirmButtonText: '确定',
                        showConfirmButton: true,
                        allowOutsideClick: false
                    }).then(() => {
                        loadUsers();
                    });
                } else {
                    Swal.fire({
                        title: '导入失败',
                        html: response.isHtml ? 
                            `<div style="text-align: left;">${response.error}</div>` : 
                            `<div style="text-align: left;">${response.error}</div>`,
                        icon: 'error',
                        confirmButtonText: '确定',
                        allowOutsideClick: false
                    });
                }
            },
            error: function(xhr, status, error) {
                console.error('用户导入请求失败:', {
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
                    html: `<div style="text-align: left;">${errorMessage}</div>`,
                    icon: 'error',
                    confirmButtonText: '确定',
                    allowOutsideClick: false
                });
            }
        });
    }
    
    // 在文档加载完成后初始化
    $(document).ready(function() {
        initClickableOptions();

        // 监听模态框显示事件
        $('.modal').on('shown.bs.modal', function() {
            initClickableOptions();
        });
    });

    // 更新排序表头样式
    function updateSortHeaderStyles() {
        // 移除所有表头的排序样式
        $('.sortable').removeClass('sort-asc sort-desc');
        $('.sortable i.fas').removeClass('fa-sort-up fa-sort-down').addClass('fa-sort');
        
        // 为当前排序列添加样式
        const $currentSortHeader = $(`.sortable[data-sort="${window.sortConfig.column}"]`);
        $currentSortHeader.addClass(`sort-${window.sortConfig.direction}`);
        $currentSortHeader.find('i.fas').removeClass('fa-sort').addClass(`fa-sort-${window.sortConfig.direction === 'asc' ? 'up' : 'down'}`);
    }
    
    // 排序并重新渲染用户列表
    function sortAndRenderUsers() {
        if (!window.userData || window.userData.length === 0) {
            return;
        }
        
        // 克隆用户数据进行排序
        const sortedUsers = [...window.userData];
        const column = window.sortConfig.column;
        const direction = window.sortConfig.direction;
        
        // 根据不同列进行排序
        sortedUsers.sort((a, b) => {
            let valueA, valueB;
            
            switch (column) {
                case 'username':
                    valueA = a.username.toLowerCase();
                    valueB = b.username.toLowerCase();
                    break;
                case 'real_name':
                    valueA = a.real_name.toLowerCase();
                    valueB = b.real_name.toLowerCase();
                    break;
                case 'role':
                    // 角色排序顺序：管理员 > 教导处 > 班主任 > 阅卷老师
                    const roleOrder = {'admin': 1, 'teaching': 2, 'headteacher': 3, 'marker': 4};
                    valueA = roleOrder[a.role] || 999;
                    valueB = roleOrder[b.role] || 999;
                    break;
                case 'permissions':
                    // 权限排序顺序：系统最高权限 > 所有年级和学科的管理权限 > 有具体权限 > 未配置权限
                    if (a.role === 'admin') valueA = 1;
                    else if (a.role === 'teaching') valueA = 2;
                    else valueA = a.has_permissions ? 3 : 4;
                    
                    if (b.role === 'admin') valueB = 1;
                    else if (b.role === 'teaching') valueB = 2;
                    else valueB = b.has_permissions ? 3 : 4;
                    break;
                case 'status':
                    valueA = a.status;
                    valueB = b.status;
                    break;
                default:
                    valueA = a[column] || '';
                    valueB = b[column] || '';
            }
            
            // 根据排序方向进行比较
            if (direction === 'asc') {
                return valueA > valueB ? 1 : valueA < valueB ? -1 : 0;
            } else {
                return valueA < valueB ? 1 : valueA > valueB ? -1 : 0;
            }
        });
        
        // 使用排序后的数据重新渲染用户列表
        renderUserList(sortedUsers);
    }
    
    // 渲染用户列表
    function renderUserList(users) {
        const currentUserId = <?php echo isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'null'; ?>;
        const roleNames = {
            'admin': '管理员',
            'teaching': '教导处',
            'headteacher': '班主任',
            'marker': '阅卷老师'
        };
        
        let adminCount = users.filter(user => user.role === 'admin' && user.status === 1).length;
        let html = '<div class="table-responsive"><table class="table">';
        html += '<thead><tr>';
        html += '<th><input type="checkbox" class="form-check-input" id="selectAllUsers"></th>';
        html += '<th class="sortable" data-sort="username">用户名 <i class="fas fa-sort"></i></th>';
        html += '<th class="sortable" data-sort="real_name">姓名 <i class="fas fa-sort"></i></th>';
        html += '<th class="sortable" data-sort="role">角色 <i class="fas fa-sort"></i></th>';
        html += '<th class="sortable" data-sort="permissions">权限配置 <i class="fas fa-sort"></i></th>';
        html += '<th class="sortable" data-sort="status">状态 <i class="fas fa-sort"></i></th>';
        html += '<th>操作 <div class="btn-group ms-2 batch-actions d-inline-flex">' +
               '<button class="btn btn-sm btn-primary batch-toggle" title="批量禁用/启用账户">' +
               '<i class="fas fa-toggle-on me-1"></i>批量禁用/启用账户</button>' +
               '<button class="btn btn-sm btn-danger batch-delete ms-2" title="批量删除选中用户">' +
               '<i class="fas fa-trash-alt me-1"></i>批量删除</button>' +
               '</div>' +
               '<span class="selected-count"></span></th>';
        html += '</tr></thead><tbody>';

        users.forEach(function(user) {
            const isCurrentUser = parseInt(user.id) === parseInt(currentUserId);
            const isLastAdmin = user.role === 'admin' && user.status === 1 && adminCount === 1;
            const status = parseInt(user.status);

            html += '<tr' + (isCurrentUser ? ' class="table-active"' : '') + '>';
            html += `<td>
                <input type="checkbox" class="form-check-input user-checkbox"
                    data-id="${user.id}"
                    data-status="${status}"
                    ${isCurrentUser || isLastAdmin ? 'disabled' : ''}
                    ${isCurrentUser ? 'title="不能操作当前登录账号"' : ''}
                    ${isLastAdmin ? 'title="不能操作最后一个管理员账号"' : ''}>
            </td>`;
            html += `<td>${user.username}${isCurrentUser ? ' <span class="badge bg-info">当前账号</span>' : ''}</td>`;
            html += `<td>${user.real_name}</td>`;
            html += `<td>${roleNames[user.role] || user.role}</td>`;

            // 添加权限配置列的内容
            html += '<td class="permissions-info" style="font-size: 0.85rem;">';
            if (user.role === 'admin') {
                html += '<span class="text-muted">系统最高权限</span>';
            } else if (user.role === 'teaching') {
                html += '<span class="text-muted">所有年级和学科的管理权限</span>';
            } else {
                // 为班主任和阅卷老师加载详细权限
                html += `<div class="permission-details" data-user-id="${user.id}" data-role="${user.role}">
                        <div class="spinner-border spinner-border-sm" role="status">
                            <span class="visually-hidden">加载中...</span>
                        </div>
                    </div>`;
            }
            html += '</td>';

            html += `<td>${status === 1 ? '<span class="text-success">启用</span>' : '<span class="text-danger">禁用</span>'}</td>`;
            html += '<td>';
            html += `<button class="btn btn-sm btn-primary me-2" onclick="editUser(${user.id})">
                                <i class="fas fa-edit me-1"></i>编辑
                            </button>`;

            const statusBtnClass = status === 1 ? 'btn-warning' : 'btn-success';
            const statusBtnText = status === 1 ? '禁用' : '启用';
            const statusBtnIcon = status === 1 ? 'fa-ban' : 'fa-check-circle';
            const statusDisabled = isCurrentUser || isLastAdmin;
            const statusTitle = isCurrentUser ? '不能禁用当前登录的账号' :
                              isLastAdmin ? '系统必须保留至少一个可用的管理员账号' : '';

            html += `<button class="btn btn-sm ${statusBtnClass} me-2 toggle-status"
                data-id="${user.id}"
                ${statusDisabled ? 'disabled' : ''}
                title="${statusTitle}">
                <i class="fas ${statusBtnIcon} me-1"></i>${statusBtnText}
            </button>`;

            const deleteDisabled = isCurrentUser || isLastAdmin;
            const deleteTitle = isCurrentUser ? '不能删除当前登录的账号' :
                              isLastAdmin ? '系统必须保留至少一个管理员账号' : '';

            html += `<button class="btn btn-sm btn-danger delete-user"
                data-id="${user.id}"
                ${deleteDisabled ? 'disabled' : ''}
                title="${deleteTitle}">
                <i class="fas fa-trash me-1"></i>删除
            </button>`;

            html += '</td></tr>';
        });

        html += '</tbody></table></div>';
        $('#userList').html(html);
        
        // 更新表头排序样式
        updateSortHeaderStyles();
        
        // 加载详细权限信息
        loadPermissionDetails();
    }
    
    // 加载权限详情
    function loadPermissionDetails() {
        $('.permission-details').each(function() {
            const $details = $(this);
            const userId = $details.data('user-id');
            const role = $details.data('role');

            $.get('../api/index.php?route=user/permissions', { user_id: userId })
                .done(function(response) {
                    if (response.success && response.data) {
                        let permHtml = '';
                        let hasPermissions = false;
                        
                        if (role === 'headteacher') {
                            const grades = response.data
                                .filter(p => p.can_edit_students === '1' || p.can_edit_students === 1)
                                .map(p => p.grade_name)
                                .filter((value, index, self) => self.indexOf(value) === index);

                            if (grades.length > 0) {
                                permHtml = `<span class="text-success">
                                    管理年级：${grades.join('、')}
                                </span>`;
                                hasPermissions = true;
                            } else {
                                permHtml = '<span class="text-warning">未配置权限</span>';
                            }
                        } else if (role === 'marker') {
                            const subjects = response.data
                                .filter(p => p.can_edit === '1' || p.can_edit === 1)
                                .map(p => `${p.grade_name}-${p.subject_name}`)
                                .filter((value, index, self) => self.indexOf(value) === index);

                            if (subjects.length > 0) {
                                permHtml = `<span class="text-success">
                                    录入权限：${subjects.join('、')}
                                </span>`;
                                hasPermissions = true;
                            } else {
                                permHtml = '<span class="text-warning">未配置权限</span>';
                            }
                        }
                        
                        $details.html(permHtml);
                        
                        // 更新用户数据中的权限标志
                        const userIndex = window.userData.findIndex(u => u.id == userId);
                        if (userIndex !== -1) {
                            window.userData[userIndex].has_permissions = hasPermissions;
                        }
                    } else {
                        $details.html('<span class="text-warning">未配置权限</span>');
                    }
                })
                .fail(function() {
                    $details.html('<span class="text-danger">加载权限失败</span>');
                });
        });
    }

    // 提交用户表单
    $('#userForm').submit(function(e) {
        e.preventDefault();
        
        // 收集表单数据
        const formData = new FormData(this);
        const userId = formData.get('id');
        const role = formData.get('role');
        
        // 显示加载中提示
        Swal.fire({
            title: '处理中',
            text: '正在保存用户信息...',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });
        
        // 判断是否有权限数据
        const permissionsInput = $('#permissions-data');
        let hasPermissions = permissionsInput.length > 0 && permissionsInput.val();
        const isAddOrUpdate = userId ? 'update' : 'add';
        
        // 发送用户信息请求
        $.ajax({
            url: '../api/index.php?route=user/' + isAddOrUpdate,
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    // 如果有权限数据，保存权限
                    if (hasPermissions && (role === 'headteacher' || role === 'marker')) {
                        // 获取用户ID，如果是新建用户，使用返回的ID
                        const targetUserId = userId || response.data.user_id;
                        
                        // 解析权限数据
                        let permissions;
                        try {
                            permissions = JSON.parse(permissionsInput.val());
                            console.log('解析到的权限数据:', permissions);
                        } catch (e) {
                            console.error('权限数据解析失败:', e, permissionsInput.val());
                            permissions = [];
                        }
                        
                        // 发送权限更新请求
                        if (permissions && permissions.length > 0) {
                            const permissionData = {
                                user_id: targetUserId,
                                role: role,
                                permissions: JSON.stringify(permissions)
                            };
                            
                            console.log('准备提交权限数据:', permissionData);
                            console.log('请求URL:', '../api/index.php?route=user/permissions/update');
                            
                            $.ajax({
                                url: '../api/index.php?route=user/permissions/update',
                                method: 'POST',
                                data: permissionData,
                                success: function(permResponse) {
                                    console.log('权限更新响应:', permResponse);
                                    if (!permResponse.success) {
                                        console.error('权限更新失败:', permResponse.error);
                                        // 虽然权限更新失败，但用户信息已保存，所以仍然显示成功
                                        Swal.fire({
                                            icon: 'warning',
                                            title: '部分成功',
                                            text: '用户信息已保存，但权限设置失败: ' + (permResponse.error || '未知错误'),
                                            timer: 5000,
                                        });
                                    } else {
                                        // 完全成功
                                        Swal.fire({
                                            icon: 'success',
                                            title: userId ? '更新成功' : '添加成功',
                                            text: response.message || (userId ? '用户信息已更新' : '新用户已创建'),
                                            timer: 2000,
                                            showConfirmButton: false
                                        });
                                    }
                                    // 关闭模态框
                                    const userModal = bootstrap.Modal.getInstance(document.getElementById('userModal'));
                                    userModal.hide();
                                    // 重新加载用户数据
                                    loadUsers();
                                },
                                error: function(xhr, status, error) {
                                    console.error('权限更新请求失败:', {
                                        status: status,
                                        error: error,
                                        response: xhr.responseText
                                    });
                                    
                                    // 检查是否是API路径问题
                                    let errorMessage = '用户信息已保存，但权限设置失败';
                                    
                                    // 如果是404错误，可能是API路径不正确
                                    if (xhr.status === 404) {
                                        errorMessage = '权限API路径不正确 (404 Not Found)，请联系管理员检查API路由配置';
                                        
                                        // 尝试可能正确的API路径
                                        $.ajax({
                                            url: '../api/index.php?route=user/update_permissions',
                                            method: 'POST',
                                            data: permissionData,
                                            success: function(altResponse) {
                                                console.log('备选API路径响应:', altResponse);
                                                if (altResponse.success) {
                                                    Swal.fire({
                                                        icon: 'success',
                                                        title: '更新成功',
                                                        text: '用户信息和权限已保存 (使用备选API路径)',
                                                        timer: 2000,
                                                        showConfirmButton: false
                                                    });
                                                } else {
                                                    Swal.fire({
                                                        icon: 'warning',
                                                        title: '部分成功',
                                                        text: '用户信息已保存，但权限设置失败 (备选API也失败)',
                                                        timer: 3000
                                                    });
                                                }
                                            },
                                            error: function() {
                                                Swal.fire({
                                                    icon: 'warning',
                                                    title: '部分成功',
                                                    text: errorMessage,
                                                    timer: 3000
                                                });
                                            },
                                            complete: function() {
                                                // 关闭模态框
                                                const userModal = bootstrap.Modal.getInstance(document.getElementById('userModal'));
                                                userModal.hide();
                                                // 重新加载用户数据
                                                loadUsers();
                                            }
                                        });
                                        return; // 不执行后续代码，让备选API处理完成后关闭模态框
                                    }
                                    
                                    // 虽然权限更新出错，但用户信息已保存
                                    Swal.fire({
                                        icon: 'warning',
                                        title: '部分成功',
                                        text: errorMessage,
                                        timer: 3000,
                                    });
                                    // 关闭模态框
                                    const userModal = bootstrap.Modal.getInstance(document.getElementById('userModal'));
                                    userModal.hide();
                                    // 重新加载用户数据
                                    loadUsers();
                                }
                            });
                        } else {
                            // 没有权限数据或权限为空，直接显示成功
                            Swal.fire({
                                icon: 'success',
                                title: userId ? '更新成功' : '添加成功',
                                text: response.message || (userId ? '用户信息已更新' : '新用户已创建'),
                                timer: 2000,
                                showConfirmButton: false
                            });
                            // 关闭模态框
                            const userModal = bootstrap.Modal.getInstance(document.getElementById('userModal'));
                            userModal.hide();
                            // 重新加载用户数据
                            loadUsers();
                        }
                    } else {
                        // 无需更新权限（管理员或教导处角色）
                        Swal.fire({
                            icon: 'success',
                            title: userId ? '更新成功' : '添加成功',
                            text: response.message || (userId ? '用户信息已更新' : '新用户已创建'),
                            timer: 2000,
                            showConfirmButton: false
                        });
                        // 关闭模态框
                        const userModal = bootstrap.Modal.getInstance(document.getElementById('userModal'));
                        userModal.hide();
                        
                        // 如果是编辑用户，更新本地数据
                        if (userId) {
                            const userIndex = window.userData.findIndex(u => u.id == userId);
                            
                            if (userIndex !== -1) {
                                // 更新用户基本信息
                                window.userData[userIndex].username = formData.get('username');
                                window.userData[userIndex].real_name = formData.get('real_name');
                                window.userData[userIndex].role = formData.get('role');
                                
                                // 重新排序并渲染
                                sortAndRenderUsers();
                            } else {
                                // 如果找不到用户，重新加载所有用户
                                loadUsers();
                            }
                        } else {
                            // 如果是新增用户，重新加载所有用户
                            loadUsers();
                        }
                    }
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: '操作失败',
                        text: response.error || '保存用户信息时发生错误'
                    });
                }
            },
            error: function(xhr) {
                let errorMsg = '操作失败';
                try {
                    const response = JSON.parse(xhr.responseText);
                    errorMsg = response.error || errorMsg;
                } catch (e) {}
                
                Swal.fire({
                    icon: 'error',
                    title: '操作失败',
                    text: errorMsg
                });
            }
        });
    });

    // 切换用户状态
    $(document).on('click', '.toggle-status', function() {
        if ($(this).prop('disabled')) {
            const title = $(this).attr('title');
            if (title) {
                showAlert(title);
            }
            return;
        }

        const id = $(this).data('id');
        const $row = $(this).closest('tr');
        const username = $row.find('td:eq(1)').text().trim().replace('当前账号', '').trim();
        const realName = $row.find('td:eq(2)').text().trim();
        const currentStatus = $(this).text().trim() === '禁用' ? 1 : 0;
        const actionText = currentStatus === 1 ? '禁用' : '启用';

        showConfirm(
            `确定要${actionText}用户"${realName}"(${username})吗？`,
            function() {
                $.ajax({
                    url: '../api/index.php?route=user/toggle_status',
                    method: 'POST',
                    data: { id: id },
                    success: function(response) {
                        if (response.success) {
                            showAlert(response.message || `${actionText}成功`, 'success');
                            
                            // 更新本地数据中的用户状态
                            const userIndex = window.userData.findIndex(u => u.id == id);
                            if (userIndex !== -1) {
                                window.userData[userIndex].status = currentStatus === 1 ? 0 : 1;
                            }
                            
                            // 重新排序并渲染
                            sortAndRenderUsers();
                        } else {
                            showAlert(response.error || `${actionText}失败`);
                        }
                    },
                    error: function(xhr) {
                        showAlert(`${actionText}失败：` + (xhr.responseJSON?.error || '未知错误'));
                    }
                });
            }
        );
    });

    // 页面加载完成后执行
    $(document).ready(function() {
        // 获取当前项目ID
        $.get('../api/index.php?route=project/current')
            .done(function(response) {
                if (response.success && response.data) {
                    window.currentSettingId = response.data.id;
                    // 同时存储到localStorage，确保多个页面共享
                    try {
                        localStorage.setItem('currentProjectId', response.data.id);
                        localStorage.setItem('currentProjectName', response.data.project_name || '');
                    } catch (e) {
                        console.warn('无法保存项目信息到本地存储:', e);
                    }
                } else {
                    // 如果API请求失败，尝试从localStorage获取
                    window.currentSettingId = localStorage.getItem('currentProjectId');
                }
                console.log('当前项目ID:', window.currentSettingId);
                loadUsers();
            })
            .fail(function(xhr) {
                console.error('获取当前项目失败:', xhr.status, xhr.statusText);
                // 尝试从localStorage获取
                window.currentSettingId = localStorage.getItem('currentProjectId');
                console.log('从localStorage获取的项目ID:', window.currentSettingId);
                loadUsers();
            });

        // 全局变量，存储用户数据和排序状态
        window.userData = [];
        window.sortConfig = {
            column: 'role', // 默认按角色排序
            direction: 'asc' // 默认升序
        };

        // 表头排序点击事件
        $(document).on('click', '.sortable', function() {
            console.log('表头点击：', $(this).data('sort'));
            const sortColumn = $(this).data('sort');
            
            // 如果点击的是当前排序列，则切换排序方向
            if (window.sortConfig.column === sortColumn) {
                window.sortConfig.direction = window.sortConfig.direction === 'asc' ? 'desc' : 'asc';
            } else {
                // 否则，更新排序列并设置为升序
                window.sortConfig.column = sortColumn;
                window.sortConfig.direction = 'asc';
            }
            
            console.log('排序配置更新为:', window.sortConfig);
            
            // 更新表头样式
            updateSortHeaderStyles();
            
            // 重新排序并渲染用户列表
            sortAndRenderUsers();
        });

        // 点击行切换选中状态
        $(document).on('click', '.table tbody tr', function(e) {
            // 如果点击的是按钮或者链接，不触发选中效果
            if ($(e.target).is('button, a, .btn, input[type="checkbox"]')) {
                return;
            }

            const $checkbox = $(this).find('.user-checkbox');
            if (!$checkbox.prop('disabled')) {
                $checkbox.prop('checked', !$checkbox.prop('checked'));
                $(this).toggleClass('selected');
                updateBatchActions();
            }
        });

        // 复选框状态改变时更新行样式
        $(document).on('change', '.user-checkbox', function(e) {
            e.stopPropagation();
            const $row = $(this).closest('tr');
            $row.toggleClass('selected', $(this).prop('checked'));
            updateBatchActions();
        });

        // 全选/取消全选
        $(document).on('change', '#selectAllUsers', function(e) {
            e.stopPropagation();
            const isChecked = $(this).prop('checked');
            const $checkboxes = $('.user-checkbox:not(:disabled)');
            $checkboxes.prop('checked', isChecked);
            $checkboxes.closest('tr').toggleClass('selected', isChecked);
            updateBatchActions();
        });

        // 批量切换状态
        $(document).on('click', '.batch-toggle', function() {
            const $checkedBoxes = $('.user-checkbox:checked');
            const selectedIds = $checkedBoxes.map(function() {
                return $(this).data('id');
            }).get();

            if (selectedIds.length === 0) {
                showAlert('请先选择要操作的用户');
                return;
            }

            // 检查选中用户的状态
            const hasEnabled = $checkedBoxes.filter(function() {
                return $(this).data('status') === 1;
            }).length > 0;

            const hasDisabled = $checkedBoxes.filter(function() {
                return $(this).data('status') === 0;
            }).length > 0;

            let action, status;
            if (hasEnabled && !hasDisabled) {
                action = '禁用';
                status = 0;
            } else if (!hasEnabled && hasDisabled) {
                action = '启用';
                status = 1;
            } else {
                showAlert('请选择状态相同的用户进行批量操作');
                return;
            }

            showConfirm(`确定要${action}选中的 ${selectedIds.length} 个用户吗？`, function() {
                batchToggleStatus(selectedIds, status);
            });
        });
        
        // 批量删除用户
        $(document).on('click', '.batch-delete', function() {
            const $checkedBoxes = $('.user-checkbox:checked');
            const selectedIds = $checkedBoxes.map(function() {
                return $(this).data('id');
            }).get();

            if (selectedIds.length === 0) {
                showAlert('请先选择要删除的用户');
                return;
            }

            // 检查是否选中了当前登录用户或最后一个管理员账号
            const hasProtectedUser = $checkedBoxes.filter(function() {
                return $(this).prop('disabled');
            }).length > 0;

            if (hasProtectedUser) {
                showAlert('所选用户中包含当前登录账号或最后一个管理员账号，无法删除');
                return;
            }

            // 提示用户确认删除操作
            showConfirm(
                `确定要删除选中的 ${selectedIds.length} 个用户吗？\n此操作不可恢复，请谨慎操作！`, 
                function() {
                    batchDelete(selectedIds);
                }
            );
        });
    });

    // 更新批量操作按钮显示状态
    function updateBatchActions() {
        const $checkedBoxes = $('.user-checkbox:checked');
        const $batchActions = $('.batch-actions');
        const $selectedCount = $('.selected-count');
        const checkedCount = $checkedBoxes.length;

        if (checkedCount > 0) {
            // 显示批量操作区域和选中计数
            $batchActions.show();
            $selectedCount.show().text(`已选择 ${checkedCount} 个用户`);

            // 检查选中用户的状态
            const hasEnabled = $checkedBoxes.filter(function() {
                return $(this).data('status') === 1;
            }).length > 0;

            const hasDisabled = $checkedBoxes.filter(function() {
                return $(this).data('status') === 0;
            }).length > 0;

            const $batchToggle = $('.batch-toggle');
            const $batchDelete = $('.batch-delete');

            // 控制批量禁用/启用按钮状态
            if (hasEnabled && !hasDisabled) {
                // 所有选中的账号都是启用状态 - 显示批量禁用按钮
                $batchToggle.html('<i class="fas fa-toggle-off me-1"></i>批量禁用账户');
                $batchToggle.removeClass('btn-success btn-primary').addClass('btn-warning');
                $batchToggle.prop('disabled', false);
            } else if (!hasEnabled && hasDisabled) {
                // 所有选中的账号都是禁用状态 - 显示批量启用按钮
                $batchToggle.html('<i class="fas fa-toggle-on me-1"></i>批量启用账户');
                $batchToggle.removeClass('btn-warning btn-primary').addClass('btn-success');
                $batchToggle.prop('disabled', false);
            } else {
                // 选中的账号状态不一致 - 显示无法操作的提示
                $batchToggle.html('<i class="fas fa-exclamation-circle me-1"></i>请选择相同状态账户');
                $batchToggle.removeClass('btn-success btn-warning').addClass('btn-primary');
                $batchToggle.prop('disabled', true);
            }

            // 检查选中的用户是否包含受保护的账户（当前登录用户或最后一个管理员）
            const hasProtectedUser = $checkedBoxes.filter(function() {
                return $(this).prop('disabled');
            }).length > 0;

            // 控制批量删除按钮状态
            if (hasProtectedUser) {
                $batchDelete.prop('disabled', true);
                $batchDelete.attr('title', '所选用户中包含不可删除的账号');
            } else {
                $batchDelete.prop('disabled', false);
                $batchDelete.attr('title', '批量删除选中用户');
            }

            // 添加动画效果
            $batchToggle.addClass('pulse-animation');
            $batchDelete.addClass('pulse-animation');
            setTimeout(() => {
                $batchToggle.removeClass('pulse-animation');
                $batchDelete.removeClass('pulse-animation');
            }, 500);

        } else {
            // 没有选中任何账号 - 隐藏批量操作区域
            $batchActions.hide();
            $selectedCount.hide();

            // 重置按钮状态
            const $batchToggle = $('.batch-toggle');
            const $batchDelete = $('.batch-delete');
            
            $batchToggle.html('<i class="fas fa-toggle-on me-1"></i>批量禁用/启用账户');
            $batchToggle.removeClass('btn-success btn-warning').addClass('btn-primary');
            $batchToggle.prop('disabled', false);
            
            $batchDelete.prop('disabled', false);
            $batchDelete.attr('title', '批量删除选中用户');
        }

        // 更新全选框状态
        const totalEnabled = $('.user-checkbox:not(:disabled)').length;
        $('#selectAllUsers').prop('checked', totalEnabled > 0 && totalEnabled === checkedCount);
    }

    // 批量切换用户状态
    function batchToggleStatus(userIds, status) {
        $.ajax({
            url: '../api/index.php?route=user/batch_toggle_status',
            method: 'POST',
            data: {
                ids: userIds,
                status: status
            },
            success: function(response) {
                if (response.success) {
                    showAlert(response.message || '操作成功', 'success');
                    
                    // 更新本地数据并重新排序渲染
                    userIds.forEach(id => {
                        const userIndex = window.userData.findIndex(u => u.id == id);
                        if (userIndex !== -1) {
                            window.userData[userIndex].status = status;
                        }
                    });
                    
                    // 重新排序并渲染
                    sortAndRenderUsers();
                } else {
                    showAlert(response.error || '操作失败');
                }
            },
            error: function(xhr) {
                showAlert('操作失败：' + (xhr.responseJSON?.error || '未知错误'));
            }
        });
    }
    
    // 批量删除用户
    function batchDelete(userIds) {
        $.ajax({
            url: '../api/index.php?route=user/batch_delete',
            method: 'POST',
            data: { ids: userIds },
            success: function(response) {
                if (response.success) {
                    showAlert(response.message || '删除成功', 'success');
                    
                    // 从本地数据中移除已删除的用户
                    window.userData = window.userData.filter(user => !userIds.includes(parseInt(user.id)));
                    
                    // 重新排序并渲染
                    sortAndRenderUsers();
                } else {
                    showAlert(response.error || '删除失败');
                }
            },
            error: function(xhr) {
                showAlert('删除失败：' + (xhr.responseJSON?.error || '未知错误'));
            }
        });
    }

    // 确保表单提交前再次收集权限信息
    $(document).ready(function() {
        // 表单提交前预处理
        $('#userForm').on('submit', function(e) {
            // 阻止默认提交行为
            e.preventDefault();
            
            const role = $('#role').val();
            const userId = $('#userId').val();
            
            console.log('表单提交前重新收集权限数据');
            
            // 针对需要权限的角色重新收集权限数据
            if (role === 'headteacher' || role === 'marker') {
                // 确保所有clickable-option的状态与checkbox一致
                $('.clickable-option').each(function() {
                    const $option = $(this);
                    const $checkbox = $option.find('input[type="checkbox"]');
                    if ($checkbox.length) {
                        const isChecked = $checkbox.prop('checked');
                        $option.toggleClass('active', isChecked);
                    }
                });
                
                // 获取表单序列化数据前更新permissions-data字段
                let permissions = [];
                
                if (role === 'headteacher') {
                    // 创建一个集合来存储唯一的年级ID
                    const gradeIds = new Set();
                    
                    // 两种方式收集权限：直接选中的复选框和通过clickable-option选中的复选框
                    $('input.grade-permission:checked, .clickable-option.active input.grade-permission').each(function() {
                        const gradeId = $(this).data('grade-id');
                        if (gradeId) {
                            gradeIds.add(gradeId);
                            console.log('收集到班主任年级权限:', gradeId);
                        }
                    });
                    
                    // 将收集到的年级ID转换为权限对象
                    gradeIds.forEach(gradeId => {
                        permissions.push({
                            grade_id: gradeId,
                            can_edit_students: 1
                        });
                    });
                    
                    console.log('收集到的班主任权限数量:', permissions.length);
                } else if (role === 'marker') {
                    // 创建一个映射来存储唯一的年级-学科组合
                    const subjectMap = new Map();
                    
                    // 两种方式收集权限：直接选中的复选框和通过clickable-option选中的复选框
                    $('input.subject-permission:checked, .clickable-option.active input.subject-permission').each(function() {
                        const gradeId = $(this).data('grade-id');
                        const subjectId = $(this).data('subject-id');
                        if (gradeId && subjectId) {
                            const key = `${gradeId}-${subjectId}`;
                            if (!subjectMap.has(key)) {
                                subjectMap.set(key, { grade_id: gradeId, subject_id: subjectId });
                                console.log('收集到阅卷老师学科权限:', gradeId, subjectId);
                            }
                        }
                    });
                    
                    // 将收集到的学科信息转换为权限对象
                    subjectMap.forEach((value) => {
                        permissions.push({
                            grade_id: value.grade_id,
                            subject_id: value.subject_id,
                            can_edit: 1
                        });
                    });
                    
                    console.log('收集到的阅卷老师权限数量:', permissions.length);
                }
                
                // 将权限数据添加到隐藏字段
                $('#permissions-data').val(JSON.stringify(permissions));
                console.log('更新permissions-data字段完成，内容:', $('#permissions-data').val());
            }
            
            // 继续原有的表单提交流程
            const formData = new FormData(this);
            
            // 显示加载中提示
            Swal.fire({
                title: '保存中',
                text: userId ? '正在更新用户信息...' : '正在创建新用户...',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            
            // 发送请求
            $.ajax({
                url: '../api/index.php?route=' + (userId ? 'user/update' : 'user/create'),
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    console.log('保存用户响应:', response);
                    
                    if (response.success) {
                        // 如果有权限数据，并且是班主任或阅卷老师角色，尝试更新权限
                        const permissionsData = $('#permissions-data').val();
                        const needUpdatePermissions = (role === 'headteacher' || role === 'marker') && permissionsData && permissionsData !== '[]';
                        
                        if (needUpdatePermissions) {
                            // 获取用户ID（如果是新建用户，从响应中获取）
                            const targetUserId = userId || (response.data && response.data.id);
                            console.log('用户ID:', targetUserId);
                            
                            try {
                                const permissions = JSON.parse(permissionsData);
                                
                                if (permissions && permissions.length > 0 && targetUserId) {
                                    // 构建权限更新请求数据
                                    const permissionData = {
                                        user_id: targetUserId,
                                        role: role,
                                        permissions: permissionsData
                                    };
                                    
                                    console.log('准备更新权限:', permissionData);
                                    
                                    // 发送权限更新请求
                                    $.ajax({
                                        url: '../api/index.php?route=user/permissions/update',
                                        method: 'POST',
                                        data: permissionData,
                                        success: function(permResponse) {
                                            console.log('权限更新响应:', permResponse);
                                            if (permResponse.success) {
                                                console.log('权限更新成功');
                                            } else {
                                                console.warn('权限更新失败:', permResponse.error);
                                                // 尝试使用备用API路径
                                                console.log('尝试使用备用API路径...');
                                                $.ajax({
                                                    url: '../api/index.php?route=user/update_permissions',
                                                    method: 'POST',
                                                    data: permissionData,
                                                    success: function(altResponse) {
                                                        console.log('备用API响应:', altResponse);
                                                    },
                                                    error: function(xhr) {
                                                        console.error('备用API请求失败:', xhr);
                                                    }
                                                });
                                            }
                                        },
                                        error: function(xhr) {
                                            console.error('权限更新请求失败:', xhr);
                                        }
                                    });
                                }
                            } catch (e) {
                                console.error('解析权限数据失败:', e);
                            }
                        }
                        
                        // 无论权限更新是否成功，都显示成功信息并关闭模态框
                        Swal.fire({
                            icon: 'success',
                            title: '成功',
                            text: userId ? '用户信息已更新' : '新用户已创建',
                            timer: 1500,
                            showConfirmButton: false
                        }).then(() => {
                            // 关闭模态框
                            const modal = bootstrap.Modal.getInstance(document.getElementById('userModal'));
                            if (modal) {
                                modal.hide();
                            }
                            
                            // 刷新用户列表
                            loadUsers();
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: '错误',
                            text: response.error || '操作失败'
                        });
                    }
                },
                error: function(xhr) {
                    console.error('保存用户失败:', xhr);
                    Swal.fire({
                        icon: 'error',
                        title: '错误',
                        text: xhr.responseJSON?.error || '请求失败，请稍后重试'
                    });
                }
            });
        });
    });
    // 新增：更新用户权限函数
    function updateUserPermissions(userId, permissionData) {
        // 解析权限数据
        const permissions = permissionData.permissions ? permissionData.permissions : permissionData;
        
        //console.log("解析到的权限数据:", permissions);

        // 如果userId无效，将权限数据保存到待处理列表中
        if (!userId) {
            console.log("用户ID无效，将权限数据添加到待处理列表");
            window.pendingPermissions = {
                role: permissionData.role || $('#role').val(),
                permissions: Array.isArray(permissions) ? permissions : [permissions]
            };
            return;
        }
        
        // 准备提交的数据
        const requestData = {
            user_id: userId,
            role: permissionData.role || $('#role').val(),
            permissions: JSON.stringify(permissions)
        };
        
        //console.log("准备提交权限数据:", requestData);
        //console.log("请求URL:", "../api/index.php?route=user/permissions/update");

        // 定义可能的API路径顺序
        const apiPaths = [
            "user/permissions/update",       // 首选路径
            "user/add/permissions",          // 备选路径1
            "settings/user_permission",      // 备选路径2
            "settings/add_user_permission",  // 备选路径3
            "user/create/permission"         // 备选路径4
        ];
        
        let currentPathIndex = 0;
        let backupXhr = null;
        
        // 尝试使用不同的API路径发送请求
        function tryNextPermissionApi() {
            if (currentPathIndex >= apiPaths.length) {
                // 所有API路径都失败了
                console.error('所有备用API路径都失败了');
                
                // 检查是否是"用户名已存在"错误
                let isUserExistsError = false;
                try {
                    // 检查所有backupXhr中是否有"用户名已存在"错误
                    if (backupXhr && backupXhr.responseJSON && 
                        backupXhr.responseJSON.error === '用户名已存在') {
                        isUserExistsError = true;
                        console.log('检测到"用户名已存在"错误，可能是重复请求，忽略错误');
                    } else if (backupXhr && backupXhr.responseText && 
                              backupXhr.responseText.includes('用户名已存在')) {
                        isUserExistsError = true;
                        console.log('检测到"用户名已存在"错误，可能是重复请求，忽略错误');
                    }
                } catch (e) {
                    console.error('检查错误类型时出错:', e);
                }
                return;
            }
            
            const apiPath = apiPaths[currentPathIndex];
            //console.log(`尝试API路径 [${currentPathIndex + 1}/${apiPaths.length}]: ${apiPath}`);
            
            $.ajax({
                url: `../api/index.php?route=${apiPath}`,
                method: 'POST',
                data: requestData,
                success: function(response) {
                    //console.log(`API路径 ${apiPath} 成功响应:`, response);
                    
                    if (response.success) {
                        // 权限更新成功
                        return;
                    } else {
                        // 当前API路径失败，尝试下一个
                        console.warn(`API路径 ${apiPath} 返回失败:`, response.error || 'Unknown error');
                        backupXhr = { responseJSON: response, status: 'error', error: response.error || '' };
                        currentPathIndex++;
                        tryNextPermissionApi();
                    }
                },
                error: function(xhr, status, error) {
                    console.error(`API路径 ${apiPath} 请求错误:`, {status, error, response: xhr.responseText});
                    backupXhr = xhr;
                    currentPathIndex++;
                    tryNextPermissionApi();
                }
            });
        }
        
        // 在发送权限请求前，检查user_id是否有效
        if (!requestData.user_id) {
            console.log('user_id无效，跳过权限请求，但不影响用户创建成功状态');
            return;
        }
        
        // 开始尝试第一个权限API
        tryNextPermissionApi();
    }

    // 用户搜索和筛选功能
    function filterUsers(searchTerm) {
        if (!window.userData || window.userData.length === 0) {
            return [];
        }
        
        // 将搜索词转换为小写，去除首尾空白
        searchTerm = searchTerm.toLowerCase().trim();
        
        // 如果搜索词为空，返回所有用户
        if (!searchTerm) {
            return window.userData;
        }
        
        // 过滤用户
        return window.userData.filter(function(user) {
            // 检查用户名和姓名是否包含搜索词
            const usernameMatch = user.username.toLowerCase().includes(searchTerm);
            const realNameMatch = user.real_name.toLowerCase().includes(searchTerm);
            
            return usernameMatch || realNameMatch;
        });
    }

    // 在页面加载完成后初始化搜索功能
    $(document).ready(function() {
        const $searchInput = $('#userSearchInput');
        const $clearSearchBtn = $('#clearSearchBtn');
        
        // 搜索输入事件处理
        $searchInput.on('input', function() {
            const searchTerm = $(this).val();
            
            // 切换清除按钮的显示状态
            $clearSearchBtn.toggle(searchTerm.length > 0);
            
            // 执行搜索和筛选
            const filteredUsers = filterUsers(searchTerm);
            
            // 重新渲染用户列表
            renderUserList(filteredUsers);
        });
        
        // 清除搜索按钮事件处理
        $clearSearchBtn.on('click', function() {
            $searchInput.val('');
            $clearSearchBtn.hide();
            
            // 恢复完整的用户列表
            renderUserList(window.userData);
        });
    });
    </script>
</body>
</html>