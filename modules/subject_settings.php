<?php
/**
 * 文件名: modules/subject_settings.php
 * 功能描述: 学科管理设置模块
 * 
 * 该文件负责:
 * 1. 提供学科管理的用户界面
 * 2. 支持学科的增删改查操作
 * 3. 管理学科与年级的关联
 * 4. 设置学科的满分、及格分和优秀分
 * 5. 管理学科权重和评分标准
 * 
 * 学科设置页面支持添加、编辑和删除学科，可以为每个学科配置基础信息，
 * 如学科名称、缩写、满分值、及格线、优秀线等。同时可以设置学科与年级的关联，
 * 确保学科成绩分析的准确性。
 * 
 * 关联文件:
 * - controllers/SubjectController.php: 学科控制器
 * - controllers/GradeController.php: 年级控制器
 * - api/index.php: API入口
 * - assets/js/subject-settings.js: 学科管理前端脚本
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
    $errorFile = __DIR__ . '/../error/403.php';
    if (file_exists($errorFile)) {
        include $errorFile;
    } else {
        header('HTTP/1.1 403 Forbidden');
        echo '访问被拒绝：需要管理员或教导处权限';
    }
    exit;
}

?>

<!-- CSS 依赖 -->
<link href="../assets/css/bootstrap.min.css" rel="stylesheet">
<link href="../assets/css/sweetalert2.min.css" rel="stylesheet">
<link href="../assets/css/all.min.css" rel="stylesheet">
<link href="../assets/css/common.css" rel="stylesheet">
<style>
        /* 模态框基础样式优化 */
        .modal-content {
            border: none;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            animation: modalFadeIn 0.3s ease-out;
        }

        .modal-header {
            border-bottom: 1px solid rgba(0, 0, 0, 0.08);
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
            border-top: 1px solid rgba(0, 0, 0, 0.08);
            padding: 16px 24px;
        }

        /* 表单控件样式优化 */
        .modal .form-control {
            border-radius: 8px;
            border: 1px solid rgba(0, 0, 0, 0.1);
            padding: 10px 16px;
            transition: all 0.2s;
        }

        .modal .form-control:focus {
            border-color: #007bff;
            box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.1);
        }

        .modal .form-label {
            font-weight: 500;
            color: #2c3e50;
            margin-bottom: 8px;
        }

        /* 按钮样式优化 */
        .modal .btn {
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.2s;
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

        /* 分数线设置样式 */
        .score-settings {
            background: #f8f9fa;
            padding: 12px;
            border-radius: 8px;
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            justify-content: space-between;
        }

        .score-settings .score-item {
            flex: 1;
            min-width: 50px;
            max-width: 100px;
        }

        .score-settings .form-control {
            width: 100%;
            padding: 6px 8px;
            font-size: 0.9rem;
            text-align: center;
        }

        .score-settings .small {
            font-size: 0.85rem;
            margin-bottom: 4px;
            display: block;
            text-align: center;
        }

        /* 年级选择框样式 */
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

        /* 年级复选框容器样式 */
        .grade-checkboxes {
            border: 1px solid rgba(0,0,0,0.1);
            border-radius: 10px;
            padding: 1rem;
            background: #fff;
            margin-bottom: 1rem;
        }

        /* 可点击选项样式 */
        .clickable-option {
            background: linear-gradient(to bottom, #ffffff, #f8f9fa);
            border: 1px solid rgba(0,0,0,0.1);
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            padding: 0.5rem 1rem 0.5rem 3rem;
            border-radius: 10px;
            margin-bottom: 0.5rem;
            cursor: pointer;
            position: relative;
            min-height: 42px;
            display: flex;
            align-items: center;
            transition: all 0.3s ease;
        }

        .clickable-option:hover {
            background: linear-gradient(to right, #f0f7ff, #ffffff);
            border-color: #86b7fe;
            box-shadow: 0 2px 8px rgba(13, 110, 253, 0.1);
            transform: translateX(3px);
        }

        .clickable-option.active {
            background: linear-gradient(to right, #e7f1ff, #ffffff);
            border-color: #0d6efd;
            box-shadow: 0 2px 8px rgba(13, 110, 253, 0.15);
        }

        /* 复选框样式 */
        .clickable-option input[type="checkbox"] {
            position: absolute;
            left: 1rem;
            margin: 0;
        }

        .clickable-option label {
            margin: 0;
            padding-left: 0.5rem;
            cursor: pointer;
            flex-grow: 1;
        }

        /* 错误状态样式 */
        .grade-checkboxes.invalid {
            border-color: #dc3545;
            background-color: #fff8f8;
        }

        .clickable-option.invalid {
            border-color: #dc3545;
            background-color: #fff8f8;
        }

        /* 表格容器样式 */
        .table-container {
            margin-top: 20px;
            overflow-x: auto;
            max-width: 100% !important;
            width: 100% !important;
        }

        /* 表格基础样式优化 */
        .table {
            width: 100%;
            margin-bottom: 1rem;
            background-color: transparent;
            border-collapse: collapse;
        }

        /* 表头样式 */
        .table thead th {
            background: #f8f9fa;
            color: #2c3e50;
            font-weight: 500;
            border-bottom: 2px solid #dee2e6;
            padding: 1rem;
            text-align: center;
            vertical-align: middle;
            font-size: 0.95rem;
            white-space: nowrap;  /* 防止表头换行 */
        }

        /* 表格内容样式 */
        .table tbody td {
            padding: 0.6rem 1rem;
            vertical-align: middle;
            border-bottom: 1px solid #dee2e6;
            color: #2c3e50;
            font-size: 0.9rem;
            text-align: center;
            line-height: 1.2;
        }

        /* 适用年级列样式 */
        .table tbody td:nth-child(7) {
            text-align: left;
            white-space: normal;  /* 允许换行 */
            min-width: 150px;     /* 设置最小宽度 */
        }

        /* 学科代码列样式 */
        .table tbody td:nth-child(2) {
            font-family: monospace;  /* 使用等宽字体 */
            color: #666;
            font-size: 0.85rem;
        }

        /* 表格行悬停效果 */
        .table tbody tr:hover {
            background-color: rgba(0, 123, 255, 0.05);
        }

        /* 卡片样式优化 */
        .card {
            border: none;
            border-radius: 16px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            background: #ffffff;
            margin-bottom: 2rem;
        }

        .card-header {
            background: transparent;
            border-bottom: 1px solid rgba(0,0,0,0.05);
            padding: 1.5rem;
        }

        .card-body {
            padding: 1.5rem;
        }

        /* 按钮样式优化 */
        .btn {
            padding: 0.5rem 1rem;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.2s;
        }

        .btn-primary {
            background: #0d6efd;
            border-color: #0d6efd;
            box-shadow: 0 2px 4px rgba(13, 110, 253, 0.2);
        }

        .btn-primary:hover {
            background: #0b5ed7;
            border-color: #0a58ca;
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(13, 110, 253, 0.3);
        }

        .btn-danger {
            background: #dc3545;
            border-color: #dc3545;
            box-shadow: 0 2px 4px rgba(220, 53, 69, 0.2);
        }

        .btn-danger:hover {
            background: #bb2d3b;
            border-color: #b02a37;
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(220, 53, 69, 0.3);
        }

        /* 操作按钮组样式 */
        .btn-group {
            display: flex;
            gap: 0.3rem;  /* 减小按钮间距 */
            justify-content: center;
        }

        .btn-sm {
            padding: 0.25rem 0.5rem;  /* 减小按钮内边距 */
            font-size: 0.8rem;  /* 减小按钮字体 */
        }

        /* 表格响应式优化 */
        @media (max-width: 768px) {
            .table thead th,
            .table tbody td {
                padding: 0.75rem;
                font-size: 0.85rem;
            }

            .btn-sm {
                padding: 0.3rem 0.6rem;
                font-size: 0.8rem;
            }
        }

        /* 页面标题样式 */
        .page-title {
            color: #2c3e50;
            font-size: 1.25rem;
            font-weight: 700;  /* 加粗标题 */
            margin: 0;
            display: flex;
            align-items: center;
        }

        .page-title i {
            margin-right: 0.5rem;
            font-size: 1.1rem;
        }

        /* 图标样式 */
        .fas {
            margin-right: 0.5rem;
        }

        /* 空状态提示样式 */
        .text-center {
            text-align: center;
            padding: 2rem;
            color: #6c757d;
        }

        /* 加载动画样式 */
        .spinner-border {
            width: 2rem;
            height: 2rem;
            color: #0d6efd;
        }

</style>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 page-title">
                        <i class="fas fa-book"></i>
                        学科管理
                    </h5>
                    <button class="btn btn-primary" onclick="showAddSubjectModal()">
                        <i class="fas fa-plus-circle me-1"></i>添加学科
                    </button>
                </div>
                <div class="card-body">
                    <div id="subjectList">
                        <!-- 学科列表将通过 AJAX 加载 -->
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- 添加/编辑学科模态框 -->
<div class="modal fade" id="subjectModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">添加学科</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="subjectForm">
                    <input type="hidden" name="id" id="subjectId">
                    <input type="hidden" name="setting_id" id="settingId">
                    
                    <!-- 学科名称 -->
                    <div class="mb-3">
                        <label class="form-label">学科名称</label>
                        <input type="text" class="form-control" name="subject_name" id="subjectName" required>
                    </div>
                    
                    <!-- 分数线设置 -->
                    <div class="mb-3">
                        <label class="form-label">分数线设置</label>
                        <div class="score-settings">
                            <div class="score-item">
                                <label class="form-label small text-muted">满分</label>
                                <input type="number" class="form-control" name="full_score" id="fullScore" required min="0" step="1">
                            </div>
                            <div class="score-item">
                                <label class="form-label small text-muted">优秀</label>
                                <input type="number" class="form-control" name="excellent_score" id="excellentScore" required min="0" step="1">
                            </div>
                            <div class="score-item">
                                <label class="form-label small text-muted">良好</label>
                                <input type="number" class="form-control" name="good_score" id="goodScore" required min="0" step="1">
                            </div>
                            <div class="score-item">
                                <label class="form-label small text-muted">合格</label>
                                <input type="number" class="form-control" name="pass_score" id="passScore" required min="0" step="1">
                            </div>
                        </div>
                    </div>
                    
                    <!-- 成绩拆分 -->
                    <div class="mb-3">
                        <div class="form-check form-switch d-flex justify-content-between align-items-center">
                            <label class="form-check-label" for="isSplit">成绩拆分</label>
                            <input class="form-check-input ms-2" type="checkbox" name="is_split" id="isSplit">
                        </div>
                    </div>
                    
                    <!-- 拆分成绩设置 -->
                    <div class="mb-3 split-score-fields" style="display: none;">
                        <div class="row">
                            <div class="col-6">
                                <label class="form-label">拆分成绩1名称</label>
                                <input type="text" class="form-control" name="split_name_1" id="splitName1">
                            </div>
                            <div class="col-6">
                                <label class="form-label">拆分成绩1满分</label>
                                <input type="number" class="form-control" name="split_score_1" id="splitScore1" min="0" step="1">
                            </div>
                        </div>
                        <div class="row mt-3">
                            <div class="col-6">
                                <label class="form-label">拆分成绩2名称</label>
                                <input type="text" class="form-control" name="split_name_2" id="splitName2">
                            </div>
                            <div class="col-6">
                                <label class="form-label">拆分成绩2满分</label>
                                <input type="number" class="form-control" name="split_score_2" id="splitScore2" min="0" step="1">
                            </div>
                        </div>
                    </div>
                    
                    <!-- 适用年级 -->
                    <div class="mb-3">
                        <label class="form-label">适用年级</label>
                        <div id="gradesList" class="border rounded p-3">
                            <!-- 年级列表将通过JavaScript动态加载 -->
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
                <button type="button" class="btn btn-primary save-subject-btn">保存</button>
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

<!-- JavaScript 依赖 -->
<script src="../assets/js/jquery.min.js"></script>
<script src="../assets/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/sweetalert2.all.min.js"></script>
<script>
    var currentSettingId = null;  // 使用 var 声明全局变量
    var subjectForm = null;       // 表单变量定义
    
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

    // 获取当前可用项目
    function getCurrentProject() {
        //console.log('开始获取当前项目...');
        
        return new Promise((resolve, reject) => {
            $.ajax({
                url: '../api/index.php?route=project/current',
                method: 'GET',
                beforeSend: function() {
                   // console.log('发送请求到:', this.url);
                },
                success: function(response) {
                   // console.log('收到服务器响应:', response);
                    if (response.success && response.data) {
                        //('成功获取项目信息:', response.data);
                        currentSettingId = response.data.id;
                        $('#settingId').val(currentSettingId);
                        resolve(response.data);
                    } else {
                        console.error('获取项目失败:', response.error || '未知错误');
                        showAlert('获取当前项目失败：' + (response.error || '未知错误'));
                        if (response.debug) {
                            //console.log('调试信息:', response.debug);
                        }
                        reject(new Error(response.error || '未知错误'));
                    }
                },
                error: function(xhr, status, error) {
                    console.error('请求失败:', {
                        status: status,
                        error: error,
                        response: xhr.responseText,
                        statusCode: xhr.status,
                        statusText: xhr.statusText
                    });
                    
                    let errorMsg = '获取当前项目失败';
                    try {
                        const response = JSON.parse(xhr.responseText);
                        if (response.error) {
                            errorMsg += ': ' + response.error;
                        }
                        if (response.debug) {
                            //console.log('服务器调试信息:', response.debug);
                        }
                    } catch (e) {
                        //console.log('原始响应内容:', xhr.responseText);
                    }
                    
                    showAlert(errorMsg);
                    reject(new Error(errorMsg));
                }
            });
        });
    }

    // 加载学科列表
    function loadSubjects() {
        //console.log('开始加载学科列表，当前项目ID:', currentSettingId);
        
        if (!currentSettingId) {
            //console.log('当前项目ID为空，尝试重新获取项目信息');
            getCurrentProject().then(() => {
                if (currentSettingId) {
                    loadSubjects();
                }
            }).catch(error => {
                console.error('获取项目失败:', error);
                $('#subjectList').html('<div class="alert alert-warning">请先设置当前项目</div>');
            });
            return;
        }

        $('#subjectList').html('<div class="text-center"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">加载中...</span></div></div>');

        $.ajax({
            url: '../api/index.php?route=settings/subjects',
            method: 'GET',
            data: { setting_id: currentSettingId },
            success: function(response) {
                //console.log('收到学科列表响应:', response);
                if (response.success && response.data) {
                    let html = '<div class="table-responsive"><table class="table">';
                    html += '<thead><tr>';
                    html += '<th>学科</th>';
                    html += '<th>学科代码</th>';
                    html += '<th>满分</th>';
                    html += '<th>优秀</th>';
                    html += '<th>良好</th>';
                    html += '<th>合格</th>';
                    html += '<th style="text-align: left;">适用年级</th>';
                    html += '<th>成绩拆分</th>';
                    html += '<th>操作</th>';
                    html += '</tr></thead><tbody>';
                    
                    if (response.data.length === 0) {
                        html += '<tr><td colspan="9" class="text-center">暂无数据</td></tr>';
                    } else {
                        response.data.forEach(function(subject) {
                            html += '<tr>';
                            html += `<td>${subject.subject_name}</td>`;
                            html += `<td>${subject.subject_code}</td>`;
                            html += `<td>${Math.round(subject.full_score)}</td>`;
                            html += `<td>${Math.round(subject.excellent_score)}</td>`;
                            html += `<td>${Math.round(subject.good_score)}</td>`;
                            html += `<td>${Math.round(subject.pass_score)}</td>`;
                            html += `<td style="text-align: left;">${Array.isArray(subject.grade_names) ? subject.grade_names.join('、') : '-'}</td>`;
                            html += `<td>${subject.is_split == 1 ? `${subject.split_name_1}: ${Math.round(subject.split_score_1)}、${subject.split_name_2}: ${Math.round(subject.split_score_2)}` : '无'}</td>`;
                            html += '<td>';
                            html += `<button class="btn btn-sm btn-primary me-2" onclick="editSubject(${subject.id})">
                                        <i class="fas fa-edit me-1"></i>编辑
                                    </button>`;
                            html += `<button class="btn btn-sm btn-danger delete-subject" data-id="${subject.id}">
                                        <i class="fas fa-trash me-1"></i>删除
                                    </button>`;
                            html += '</td></tr>';
                        });
                    }
                    
                    html += '</tbody></table></div>';
                    $('#subjectList').html(html);
                } else {
                    console.error('加载学科列表失败:', response.error || '未知错误');
                    $('#subjectList').html('<div class="alert alert-warning">加载学科列表失败</div>');
                }
            },
            error: function(xhr) {
                console.error('加载学科列表请求失败:', xhr);
                $('#subjectList').html('<div class="alert alert-danger">加载学科列表失败</div>');
            }
        });
    }

    // 初始化页面
    function initPage() {
        //console.log('初始化页面...');
        getCurrentProject().then(() => {
            loadSubjects();
        }).catch(error => {
            console.error('初始化失败:', error);
        });
    }

    // 页面加载完成后执行
    $(document).ready(function() {
       // console.log('页面加载完成，开始初始化...');
        subjectForm = $('#subjectForm');  // 初始化表单变量
        initPage();
        initSubjectModal();  // 初始化模态框相关事件
        
        // 绑定删除学科按钮点击事件
        $(document).on('click', '.delete-subject', function() {
            const subjectId = $(this).data('id');
            if (subjectId) {
                showConfirm('确定要删除该学科吗？此操作不可恢复。', function() {
                    deleteSubject(subjectId);
                });
            }
        });
        
        // 绑定保存学科按钮点击事件
        $(document).on('click', '.save-subject-btn', function() {
            saveSubject();
        });
        
        // 监听模态框显示事件
        $('#subjectModal').on('shown.bs.modal', function() {
            // 确保拆分成绩切换事件绑定
            $('#isSplit').off('change').on('change', function() {
                const checked = $(this).is(':checked');
                console.log("成绩拆分切换:", checked);
                if (checked) {
                    $('.split-score-fields').show();
                } else {
                    $('.split-score-fields').hide();
                }
            });
        });

        // 监听模态框隐藏事件
        $('#subjectModal').on('hidden.bs.modal', function() {
            // 重置表单和成绩拆分相关字段
            $('#subjectForm')[0].reset();
            $('#isSplit').prop('checked', false);
            $('.split-score-fields').hide();
            $('#splitName1').val('');
            $('#splitName2').val('');
            $('#splitScore1').val('');
            $('#splitScore2').val('');
            $('#fullScore').val('');
            $('#excellentScore').val('');
            $('#goodScore').val('');
            $('#passScore').val('');
        });
    });

    // 当页面变为可见时重新加载数据
    document.addEventListener('visibilitychange', function() {
        if (document.visibilityState === 'visible') {
           // console.log('页面变为可见，重新加载数据...');
            initPage();
        }
    });

    // 添加页面焦点事件监听
    window.addEventListener('focus', function() {
        //console.log('页面获得焦点，重新加载数据...');
        initPage();
    });

    // 加载年级列表
    function loadGrades(selectedGrades = [], subjectId = null) {
        $.ajax({
            url: '../api/index.php?route=settings/grades',
            method: 'GET',
            success: function(response) {
                if (response.success && response.data) {
                    let html = '<div class="grade-list">';
                    response.data.forEach(function(grade) {
                        const checked = selectedGrades.includes(grade.id.toString()) ? 'checked' : '';
                        html += `
                            <div class="form-check mb-2">
                                <input class="form-check-input grade-checkbox" type="checkbox" 
                                    value="${grade.id}" id="grade_${grade.id}" ${checked}
                                    data-grade-name="${grade.grade_name}">
                                <label class="form-check-label" for="grade_${grade.id}">
                                    ${grade.grade_name}
                                </label>
                            </div>`;
                    });
                    html += '</div>';
                    $('#gradesList').html(html);
                    
                    // 如果有学科ID，检查是否有成绩数据
                    if (subjectId) {
                        const settingId = $('#settingId').val();
                        if (settingId) {
                            checkSubjectHasScores(subjectId, settingId);
                        }
                    }
                } else {
                    $('#gradesList').html('<div class="alert alert-warning">加载年级列表失败</div>');
                }
            },
            error: function(xhr) {
                $('#gradesList').html('<div class="alert alert-danger">加载年级列表失败：' + 
                    (xhr.responseJSON?.error || '未知错误') + '</div>');
            }
        });
    }

    // 检查学科是否有成绩数据
    function checkSubjectHasScores(subjectId, settingId) {
        $.get('../api/index.php?route=settings/subject/check_has_scores', { id: subjectId, setting_id: settingId })
            .done(function(response) {
                if (response.success) {
                    const data = response.data;
                    
                    if (data.has_scores) {
                        // 如果有成绩数据，禁用成绩拆分切换
                        $('#isSplit').prop('disabled', true);
                        
                        // 如果已启用成绩拆分，禁用分数值修改，但允许修改名称
                        if ($('#isSplit').is(':checked')) {
                            $('#splitScore1, #splitScore2').prop('disabled', true);
                        }
                        
                        // 标记有成绩数据的年级复选框为禁用状态
                        if (data.grades_with_scores && data.grades_with_scores.length > 0) {
                            data.grades_with_scores.forEach(function(gradeId) {
                                $(`.grade-list input[value="${gradeId}"]`).prop('disabled', true);
                                // 确保选中状态
                                $(`.grade-list input[value="${gradeId}"]`).prop('checked', true);
                            });
                        }
                        
                        /* 显示提示信息
                        Swal.fire({
                            title: "注意",
                            text: "此学科已有成绩数据，部分设置将被限制修改。",
                            icon: "info",
                            confirmButtonText: "我知道了"
                        });
                        */
                    }
                }
            });
    }

    // 检查学科名称在选中年级中是否已存在
    function checkSubjectNameInGrades(subjectName, selectedGradeIds, currentSubjectId = null) {
        return new Promise((resolve, reject) => {
            $.ajax({
                url: '../api/index.php?route=settings/subject/check_name',
                method: 'GET',
                data: {
                    subject_name: subjectName,
                    grade_ids: JSON.stringify(selectedGradeIds),
                    current_subject_id: currentSubjectId
                },
                success: function(response) {
                    resolve(response);
                },
                error: function(xhr) {
                    reject(xhr.responseJSON || { error: '检查失败' });
                }
            });
        });
    }

    // 显示添加学科模态框
    function showAddSubjectModal() {
        if (!currentSettingId) {
            showAlert('请先设置当前项目');
            return;
        }

        // 重置表单
        $('#subjectId').val('');
        $('#subjectForm')[0].reset();
        $('#settingId').val(currentSettingId);
        $('.modal-title').text('添加学科');

        // 重置成绩拆分相关字段
        $('#isSplit').prop('checked', false);
        $('.split-score-fields').hide();
        $('#splitName1').val('');
        $('#splitName2').val('');
        $('#splitScore1').val('');
        $('#splitScore2').val('');

        // 重置分数字段
        $('#fullScore').val('');
        $('#excellentScore').val('');
        $('#goodScore').val('');
        $('#passScore').val('');
        
        // 加载年级列表
        loadGrades();
        
        var subjectModal = new bootstrap.Modal(document.getElementById('subjectModal'));
        subjectModal.show();
    }

    // 编辑学科
    function editSubject(id) {
        $('#subjectForm')[0].reset();
        $('#subjectId').val(id);
        $('.modal-title').text('编辑学科');
        
        // 重置所有禁用状态
        $('#isSplit').prop('disabled', false);
        $('#splitScore1, #splitScore2').prop('disabled', false);
        $('.grade-list input[type="checkbox"]').prop('disabled', false);
        
        $.get('../api/index.php?route=settings/subject/get', { id: id })
            .done(function(response) {
                if (response.success) {
                    const subject = response.data;
                    $('#subjectName').val(subject.subject_name);
                    $('#fullScore').val(subject.full_score);
                    $('#excellentScore').val(subject.excellent_score);
                    $('#goodScore').val(subject.good_score);
                    $('#passScore').val(subject.pass_score);
                    $('#settingId').val(subject.setting_id);
                    
                    // 设置成绩拆分状态
                    if(subject.is_split == 1) {
                        $('#isSplit').prop('checked', true);
                        $('.split-score-fields').show();
                        $('#splitName1').val(subject.split_name_1);
                        $('#splitName2').val(subject.split_name_2);
                        $('#splitScore1').val(Math.round(subject.split_score_1));
                        $('#splitScore2').val(Math.round(subject.split_score_2));
                    } else {
                        $('#isSplit').prop('checked', false);
                        $('.split-score-fields').hide();
                    }
                    
                    loadGrades(subject.grade_ids || [], id);
                    
                    // 检查学科是否有成绩数据
                    checkSubjectHasScores(id, subject.setting_id);
                    
                    var subjectModal = new bootstrap.Modal(document.getElementById('subjectModal'));
                    subjectModal.show();
                } else {
                    showAlert(response.error || '加载学科信息失败');
                }
            })
            .fail(function(xhr) {
                showAlert('加载学科信息失败：' + (xhr.responseJSON?.error || '未知错误'));
            });
    }

    // 保存学科信息
    async function saveSubject() {
        if ($('#subjectName').val() === '') {
            Swal.fire("提示", "请输入学科名称", "warning");
            return false;
        }

        if ($('#fullScore').val() === '') {
            Swal.fire("提示", "请输入满分分值", "warning");
            return false;
        }

        if ($('#excellentScore').val() === '') {
            Swal.fire("提示", "请输入优秀分数线", "warning");
            return false;
        }

        if ($('#goodScore').val() === '') {
            Swal.fire("提示", "请输入良好分数线", "warning");
            return false;
        }

        if ($('#passScore').val() === '') {
            Swal.fire("提示", "请输入及格分数线", "warning");
            return false;
        }

        // 获取选中的年级
        let gradeIds = [];
        $('.grade-list input[type="checkbox"]:checked').each(function() {
            gradeIds.push($(this).val());
        });

        if (gradeIds.length === 0) {
            Swal.fire("提示", "请至少选择一个年级", "warning");
            return false;
        }

        // 生成随机学科代码
        const generateSubjectCode = async () => {
            const response = await $.ajax({
                url: '../api/index.php?route=settings/subject/generate_code',
                method: 'GET'
            });
            if (response.success) {
                return response.data.code;
            }
            throw new Error(response.error || '生成学科代码失败');
        };

        try {
            // 如果是新增操作或者编辑操作，检查学科名称是否存在
            const subjectId = $('#subjectId').val();
            const subjectName = $('#subjectName').val().trim();
            
            // 忽略可能的 "学科名称已存在" 错误，因为多年级情况下可能会误报
            // 由服务器端处理实际的添加逻辑，防止误判
            
            // 如果是新增操作，则生成新的学科代码
            const subjectCode = !subjectId ? await generateSubjectCode() : null;

            // 发送表单数据前先收集
            const formData = {
                subject_name: $('#subjectName').val(),
                subject_code: subjectCode, // 使用生成的随机代码
                full_score: $('#fullScore').val(),
                excellent_score: $('#excellentScore').val(),
                good_score: $('#goodScore').val(),
                pass_score: $('#passScore').val(),
                grade_ids: gradeIds,
                setting_id: $('#settingId').val(),
                is_split: $('#isSplit').is(':checked') ? 1 : 0
            };
            
            // 如果是拆分模式，添加拆分相关字段
            if ($('#isSplit').is(':checked')) {
                formData.split_name_1 = $('#splitName1').val();
                formData.split_name_2 = $('#splitName2').val();
                formData.split_score_1 = $('#splitScore1').val();
                formData.split_score_2 = $('#splitScore2').val();
            }
            
            // 如果有ID字段，是编辑操作
            if (subjectId) {
                formData.id = subjectId;
            }
            
            //console.log("提交的表单数据:", formData);
            
            // 禁用提交按钮，避免重复提交
            const submitBtn = $('.save-subject-btn');
            submitBtn.prop('disabled', true);
            submitBtn.html('<i class="fas fa-spinner fa-spin"></i> 提交中...');

            // 根据是新增还是编辑选择不同的API路径
            const apiPath = subjectId ? 'settings/subject/update' : 'settings/subject/add';

            $.ajax({
                url: '../api/index.php?route=' + apiPath,
                type: 'POST',
                data: formData,
                success: function(response) {
                    //console.log("API响应:", response);
                    if (response.success) {
                        Swal.fire({
                            title: "成功",
                            text: response.message || "保存成功",
                            icon: "success",
                            confirmButtonText: "确定"
                        }).then(() => {
                            // 重新加载学科列表
                        loadSubjects();
                            // 关闭模态框
                            $('#subjectModal').modal('hide');
                        });
                    } else {
                        // 检查是否是因为学科名称已存在的错误
                        const errorMsg = response.error || "保存失败";
                        
                        // 如果错误信息包含"学科名称已存在"，且确实添加成功了，则忽略这个错误
                        if (errorMsg.includes("学科名称已存在") && response.data && response.data.subject_id) {
                            // 这种情况是误报，实际添加成功了
                            Swal.fire({
                                title: "成功",
                                text: "学科添加成功",
                                icon: "success",
                                confirmButtonText: "确定"
                            }).then(() => {
                                // 重新加载学科列表
                                loadSubjects();
                                // 关闭模态框
                                $('#subjectModal').modal('hide');
                            });
                        } else {
                            // 其他真实错误
                            Swal.fire("错误", errorMsg, "error");
                        }
                    }
                },
                error: function(xhr) {
                    let errorMsg = "请求失败";
                    try {
                        const response = JSON.parse(xhr.responseText);
                        errorMsg = response.error || errorMsg;
                        console.error("API错误:", response);
                        
                        // 检查是否是因为学科名称已存在的错误
                        if (errorMsg.includes("学科名称已存在") && response.data && response.data.subject_id) {
                            // 这种情况是误报，实际添加成功了
                            Swal.fire({
                                title: "成功",
                                text: "学科添加成功",
                                icon: "success",
                                confirmButtonText: "确定"
                            }).then(() => {
                                // 重新加载学科列表
                                loadSubjects();
                                // 关闭模态框
                                $('#subjectModal').modal('hide');
                            });
                            return;
                        }
                    } catch (e) {
                        console.error("解析错误响应失败:", xhr.responseText);
                    }
                    Swal.fire("错误", errorMsg, "error");
                },
                complete: function() {
                    // 恢复提交按钮状态
                    submitBtn.prop('disabled', false);
                    submitBtn.html('保存');
                }
            });
        } catch (error) {
            console.error('保存学科失败:', error);
            Swal.fire("错误", error.message || "保存失败", "error");
        }
    }

    // 删除学科
    function deleteSubject(id) {
                $.ajax({
                    url: '../api/index.php?route=settings/subject/delete',
                    method: 'POST',
                    data: { id: id },
                    success: function(response) {
                        if (response.success) {
                    Swal.fire({
                        title: "成功",
                        text: "学科已删除",
                        icon: "success",
                        timer: 2000,
                        showConfirmButton: false
                    });
                            loadSubjects();
                        } else {
                    Swal.fire("错误", response.error || "删除失败", "error");
                        }
                    },
                    error: function(xhr) {
                let errorMsg = "删除失败";
                try {
                    const response = JSON.parse(xhr.responseText);
                    errorMsg = response.error || errorMsg;
                } catch (e) {}
                Swal.fire("错误", errorMsg, "error");
                    }
                });
            }

    // 初始化学科模态框的相关事件
    function initSubjectModal() {
        // 拆分成绩切换事件
        $('#isSplit').on('change', function() {
            const checked = $(this).is(':checked');
            //console.log("成绩拆分切换:", checked);
            if (checked) {
                $('.split-score-fields').show();
            } else {
                $('.split-score-fields').hide();
            }
        });
        
        // 拆分分值变化时自动计算
        $('#splitScore1').on('input', function() {
            const fullScore = parseFloat($('#fullScore').val()) || 0;
            const splitScore1 = Math.round(parseFloat($(this).val()) || 0);
            const splitScore2 = Math.round(fullScore - splitScore1);
            if (!isNaN(splitScore2)) {
                $('#splitScore2').val(splitScore2);
            }
        });
        
        // 满分变化时更新拆分分值
        $('#fullScore').on('input', function() {
            if ($('#isSplit').is(':checked')) {
                const fullScore = parseFloat($(this).val()) || 0;
                const splitScore1 = Math.round(parseFloat($('#splitScore1').val()) || 0);
                const splitScore2 = Math.round(fullScore - splitScore1);
                if (!isNaN(splitScore2)) {
                    $('#splitScore2').val(splitScore2);
                }
            }
        });
    }

    // 打开添加学科模态框
    function openAddSubjectModal() {
        // 清空并重置表单
        subjectForm[0].reset();
        subjectForm.find("input[name='id']").val('');
        subjectForm.find('.grade-list input[type="checkbox"]').prop('checked', false);
        subjectForm.find("input[name='setting_id']").val(currentSettingId);
        
        // 重置拆分成绩相关字段
        // 移除之前的事件监听器
        $(document).off('click.clickableOption');

        // 绑定点击事件到整个选项区域
        $(document).on('click.clickableOption', '.clickable-option', function(e) {
            const $option = $(this);
            const $input = $option.find('input[type="checkbox"]');
            
            // 切换复选框状态
            $input.prop('checked', !$input.prop('checked'));
            $option.toggleClass('active');
            
            // 触发原始input的change事件
            $input.trigger('change');
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
</script>
