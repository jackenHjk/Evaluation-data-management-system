<?php
/**
 * 文件名: modules/operation_logs.php
 * 功能描述: 操作日志管理模块
 * 
 * 该文件负责:
 * 1. 显示系统操作日志记录
 * 2. 提供按用户、角色筛选日志功能
 * 3. 支持日志清理和维护操作
 * 4. 展示用户行为和系统关键操作记录
 * 5. 保障系统使用的可追溯性和安全性
 * 
 * 操作日志详细记录了用户在系统中的各项操作，包括登录、数据修改、导入导出等活动，
 * 支持按时间顺序分页展示，提供用户筛选和角色筛选，并可以清理过期日志。
 * 日志记录包含操作用户、操作类型、详细内容、IP地址和时间戳等信息。
 * 
 * 关联文件:
 * - controllers/LogController.php: 日志控制器
 * - api/index.php: API入口
 * - api/routes/log: 日志相关API
 * - core/Logger.php: 日志记录核心类
 * - assets/js/operation-logs.js: 日志管理前端脚本
 */

// 确保包含必要的配置文件
require_once __DIR__ . '/../config/config.php';

// 启动会话
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 检查用户是否登录
if (!isset($_SESSION['user_id'])) {
    // 使用绝对路径包含错误页面
    $errorFile = __DIR__ . '/../error/403.php';
    if (file_exists($errorFile)) {
        include $errorFile;
    } else {
        // 如果错误页面不存在，显示简单的错误消息
        header('HTTP/1.1 403 Forbidden');
        echo '访问被拒绝：请先登录';
    }
    exit;
}

// 检查用户是否为管理员
if ($_SESSION['role'] !== 'admin') {
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

// 定义系统内部访问标记
define('IN_MODULE', true);
?>

<!-- CSS 依赖 -->
<link href="../assets/css/bootstrap.min.css" rel="stylesheet">
<link href="../assets/css/sweetalert2.min.css" rel="stylesheet">
<link href="../assets/css/all.min.css" rel="stylesheet">
<link href="../assets/css/common.css" rel="stylesheet">

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="fas fa-history text-primary"></i>
                        日志管理
                    </h5>
                    <div class="d-flex align-items-center">
                        <div class="btn-group">
                            <div style="width: 160px;">
                                <select class="form-select" id="userFilter">
                                    <option value="">全部用户</option>
                                </select>
                            </div>
                            <div style="width: 160px;">
                                <select class="form-select" id="roleFilter">
                                    <option value="">全部角色</option>
                                    <option value="admin">管理员</option>
                                    <option value="teaching">教导处</option>
                                    <option value="headteacher">班主任</option>
                                    <option value="marker">阅卷老师</option>
                                </select>
                            </div>
                            <div style="width: 160px;">
                                <select class="form-select" id="actionFilter">
                                    <option value="">全部操作</option>
                                    <option value="login">登录操作</option>
                                    <option value="logout">注销操作</option>
                                    <option value="add">添加操作</option>
                                    <option value="update">更新操作</option>
                                    <option value="delete">删除操作</option>
                                    <option value="import">导入操作</option>
                                    <option value="export">导出操作</option>
                                    <option value="submit_score_edit_request">提交成绩修改申请</option>
                                    <option value="approve_score_edit_request">审核通过申请</option>
                                    <option value="reject_score_edit_request">驳回申请</option>
                                </select>
                            </div>
                            <button id="searchBtn" class="btn btn-primary">
                                <i class="fas fa-search"></i>
                                查询
                            </button>
                            <button id="cleanLogs" class="btn btn-warning">
                                <i class="fas fa-broom"></i>
                                清理过期日志
                            </button>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <!-- 日志列表 -->
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>序号</th>
                                    <th>用户名</th>
                                    <th>真实姓名</th>
                                    <th>角色</th>
                                    <th>操作类型</th>
                                    <th>操作详情</th>
                                    <th>IP地址</th>
                                    <th>操作时间</th>
                                </tr>
                            </thead>
                            <tbody id="logsList">
                                <!-- 日志数据将通过AJAX加载 -->
                            </tbody>
                        </table>
                    </div>

                    <!-- 分页 -->
                    <nav class="mt-4">
                        <ul class="pagination justify-content-center" id="pagination">
                            <!-- 分页按钮将通过JS动态生成 -->
                        </ul>
                    </nav>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* 保持导航菜单样式一致性 */
.navbar {
    padding: 1rem 0;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
}

.dropdown-menu {
    margin-top: 0;
    border: none;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    border-radius: 8px;
    padding: 8px 0;
    animation: fadeIn 0.2s ease;
    transform-origin: top;
}

.dropdown-item {
    padding: 8px 16px;
    color: #1D1D1F;
    transition: all 0.2s ease;
}

.dropdown-item:hover {
    background-color: rgba(0, 102, 204, 0.1);
    color: #0066CC;
}

.dropdown-item i {
    width: 20px;
    text-align: center;
}

.dropdown:hover .dropdown-menu {
    display: block;
}

@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(-10px);
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

/* 表格样式美化 */
.table {
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 0 20px rgba(0, 0, 0, 0.05);
}

.table thead th {
    background: linear-gradient(to bottom, #f8f9fa, #f1f3f5);
    border-bottom: 2px solid #dee2e6;
    color: #495057;
    font-weight: 600;
    text-transform: uppercase;
    font-size: 0.85rem;
    padding: 1rem;
}

.table tbody tr {
    transition: all 0.2s ease;
}

.table tbody tr:hover {
    background-color: #f8f9fa;
    transform: scale(1.001);
}

.table td {
    padding: 1rem;
    vertical-align: middle;
    border-bottom: 1px solid #eee;
}

/* 操作详情列的特殊样式 */
.table td:nth-child(5) {
    font-size: 0.85rem;
    white-space: pre-wrap;
    word-break: break-word;
    max-width: 500px;
    line-height: 1.5;
}

/* 操作详情中的颜色标记 */
.table td:nth-child(5) span[style*="color"] {
    display: inline-block;
    margin-top: 0.5rem;
    padding: 0.25rem 0;
}

.badge {
    font-weight: 500;
    padding: 0.5em 0.8em;
    border-radius: 6px;
}

/* 分页样式美化 */
.pagination {
    margin-bottom: 0;
}

.page-link {
    border-radius: 6px;
    margin: 0 2px;
    border: none;
    color: #495057;
    padding: 0.5rem 1rem;
    transition: all 0.2s ease;
}

.page-link:hover {
    background-color: #e9ecef;
    color: #0d6efd;
    transform: translateY(-1px);
}

.page-item.active .page-link {
    background-color: #0d6efd;
    border-color: #0d6efd;
    color: white;
    box-shadow: 0 2px 5px rgba(13, 110, 253, 0.3);
}

.page-item.disabled .page-link {
    color: #6c757d;
    background-color: #f8f9fa;
}

/* 隐藏原生select */
.form-select {
    display: none;
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

.card-header .btn-warning {
    background: #d97706;
    border-color: #d97706;
    color: #fff;
}

/* 添加成绩日志相关的样式 */
.score-log {
    font-family: "Courier New", monospace;
    font-size: 13px;
    line-height: 1.4;
}
.score-log .text-primary {
    color: #0066cc !important;
    font-weight: 500;
}
.score-log .text-danger {
    color: #dc3545 !important;
    font-weight: 500;
}
</style>

<script>
// 获取角色名称
function getRoleName(role) {
    const roleNames = {
        'admin': '管理员',
        'teaching': '教导处',
        'headteacher': '班主任',
        'marker': '阅卷老师'
    };
    return roleNames[role] || role;
}

// 获取操作类型名称
function getActionTypeName(type) {
    const actionTypes = {
        'login': '登录',
        'logout': '登出',
        'add': '新增',
        'edit': '修改',
        'delete': '删除',
        'import': '导入',
        'export': '导出',
        'upload': '上传',
        'download': '下载',
        'clean': '清理',
        'view': '查看',
        'submit': '提交',
        'approve': '审核',
        'reject': '驳回',
        'system': '系统操作',
        'generate': '生成',
        'access': '访问',
        'create': '创建',
        'update': '更新'
    };
    return actionTypes[type] || type;
}

// 格式化日期时间
function formatDateTime(datetime) {
    return datetime ? datetime.replace(/\.000000/, '') : '';
}

// 加载用户列表
function loadUsers() {
    return new Promise((resolve, reject) => {
        $.get('../api/index.php?route=log/getUsers', function(response) {
            if (response.success) {
                const users = response.data;
                let options = '<option value="">全部用户</option>';
                users.forEach(user => {
                    options += `<option value="${user.id}">${user.real_name || user.username}</option>`;
                });
                $('#userFilter').html(options);
                resolve();
            } else {
                reject(new Error(response.error || '加载用户列表失败'));
            }
        }).fail(function(xhr) {
            reject(new Error(xhr.responseJSON?.error || '加载用户列表失败'));
        });
    });
}

// 渲染日志列表
function renderLogs(logs) {
    let html = '';
    const startNum = (window.currentPage - 1) * 50 + 1;
    
    logs.forEach((log, index) => {
        const roleClass = {
            'admin': 'bg-danger',
            'teaching': 'bg-primary',
            'headteacher': 'bg-success',
            'marker': 'bg-warning'
        }[log.role] || 'bg-secondary';
        
        // 处理操作详情
        let actionDetail = log.action_detail || '';
        let detailClass = '';
        
        // 如果是教导处角色的创建用户操作，隐藏权限设置详情
        if (log.role === 'teaching' && log.action_type === 'create' && actionDetail.includes('权限设置')) {
            actionDetail = actionDetail.split('权限设置')[0].trim();
        }
        
        // 如果是成绩录入操作，添加特殊样式
        if (log.action_type === 'edit' && actionDetail.includes('成绩录入')) {
            detailClass = 'score-log';
            // 将成绩信息用不同颜色标记
            actionDetail = actionDetail.replace(/(\d+\.?\d*)/g, '<span class="text-primary">$1</span>');
            actionDetail = actionDetail.replace(/缺考/g, '<span class="text-danger">缺考</span>');
        }
        
        html += `
            <tr>
                <td>${startNum + index}</td>
                <td>${log.username || ''}</td>
                <td>${log.real_name || ''}</td>
                <td><span class="badge ${roleClass}">${getRoleName(log.role)}</span></td>
                <td>${getActionTypeName(log.action_type) || ''}</td>
                <td class="${detailClass}">${actionDetail}</td>
                <td>${log.ip_address || ''}</td>
                <td>${formatDateTime(log.created_at)}</td>
            </tr>
        `;
    });
    
    $('#logsList').html(html || '<tr><td colspan="8" class="text-center">暂无数据</td></tr>');
}

// 渲染分页
function renderPagination(pagination) {
    const {current_page, total_pages} = pagination;
    let html = '';
    
    html += `
        <li class="page-item ${current_page === 1 ? 'disabled' : ''}">
            <a class="page-link" href="#" data-page="${current_page - 1}">上一页</a>
        </li>
    `;
    
    for (let i = 1; i <= total_pages; i++) {
        if (
            i === 1 || 
            i === total_pages || 
            (i >= current_page - 2 && i <= current_page + 2)
        ) {
            html += `
                <li class="page-item ${i === current_page ? 'active' : ''}">
                    <a class="page-link" href="#" data-page="${i}">${i}</a>
                </li>
            `;
        } else if (
            i === current_page - 3 || 
            i === current_page + 3
        ) {
            html += `
                <li class="page-item disabled">
                    <span class="page-link">...</span>
                </li>
            `;
        }
    }
    
    html += `
        <li class="page-item ${current_page === total_pages ? 'disabled' : ''}">
            <a class="page-link" href="#" data-page="${current_page + 1}">下一页</a>
        </li>
    `;
    
    $('#pagination').html(html);
}

// 加载日志列表
function loadLogs(page = 1) {
    const params = {
        page: page,
        user_id: $('#userFilter').val(),
        role: $('#roleFilter').val(),
        action: $('#actionFilter').val()
    };
    
    $.get('../api/index.php?route=log/getList', params, function(response) {
        if (response.success) {
            renderLogs(response.data.logs);
            renderPagination(response.data.pagination);
            window.currentPage = page;
        } else {
            Swal.fire({
                icon: 'error',
                title: '加载失败',
                text: response.error || '获取日志数据失败'
            });
        }
    });
}

// 初始化自定义下拉框
function initCustomSelects() {
    $('.custom-select-wrapper').remove();
    
    $('.form-select').each(function() {
        const select = $(this);
        const wrapper = $('<div class="custom-select-wrapper"></div>');
        const trigger = $('<div class="custom-select-trigger"></div>');
        const options = $('<div class="custom-options"></div>');
        
        trigger.text(select.find('option:selected').text());
        
        select.find('option').each(function() {
            const option = $('<div class="custom-option"></div>')
                .attr('data-value', $(this).val())
                .text($(this).text());
                
            if ($(this).is(':selected')) {
                option.addClass('selected');
            }
            
            options.append(option);
        });
        
        wrapper.append(trigger).append(options);
        select.after(wrapper);
        
        trigger.on('click', function(e) {
            e.stopPropagation();
            $('.custom-select-wrapper').not(wrapper).removeClass('open');
            wrapper.toggleClass('open');
        });
        
        options.find('.custom-option').on('click', function() {
            const value = $(this).data('value');
            const text = $(this).text();
            
            trigger.text(text);
            options.find('.custom-option').removeClass('selected');
            $(this).addClass('selected');
            
            select.val(value).trigger('change');
            
            wrapper.removeClass('open');
        });
    });
    
    $(document).off('click.customSelect').on('click.customSelect', function() {
        $('.custom-select-wrapper').removeClass('open');
    });
}

// 页面加载完成后的初始化
$(document).ready(function() {
    // 声明当前页变量
    window.currentPage = 1;
    
    // 初始化自定义下拉框
    initCustomSelects();
    
    // 加载初始数据
    loadUsers().then(() => {
        initCustomSelects();
        loadLogs(window.currentPage);
    }).catch(error => {
        Swal.fire({
            icon: 'error',
            title: '加载失败',
            text: error.message
        });
    });
    
    // 绑定事件
    $('#searchBtn').click(function() {
        loadLogs(1);
    });
    
    $('#pagination').on('click', 'a.page-link', function(e) {
        e.preventDefault();
        const page = $(this).data('page');
        if (page) {
            loadLogs(page);
        }
    });
    
    // 清理日志
    $('#cleanLogs').click(function() {
        Swal.fire({
            title: '确认清理',
            text: '是否确认清理30天前的日志？此操作不可恢复！',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: '确认清理',
            cancelButtonText: '取消',
            confirmButtonColor: '#dc3545'
        }).then((result) => {
            if (result.isConfirmed) {
                $.get('../api/index.php?route=log/cleanOldLogs', function(response) {
                    if (response.success) {
                        Swal.fire({
                            icon: 'success',
                            title: '清理成功',
                            text: response.message
                        }).then(() => {
                            loadLogs(1);
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: '清理失败',
                            text: response.error || '清理日志失败'
                        });
                    }
                });
            }
        });
    });
});
</script>