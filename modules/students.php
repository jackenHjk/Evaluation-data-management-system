<!--
/**
 * 文件名: modules/students.php
 * 功能描述: 学生信息管理模块
 * 
 * 该文件负责:
 * 1. 提供学生信息的增删改查界面
 * 2. 支持按年级、班级筛选学生
 * 3. 支持学生信息的批量导入功能
 * 4. 提供学生照片上传和管理
 * 5. 支持学生名单导出功能
 * 
 * 界面功能包括:
 * - 学生列表显示，支持分页和搜索
 * - 添加/编辑学生信息的表单
 * - Excel批量导入学生数据功能
 * - 学生照片管理
 * - 导出学生信息表
 * 
 * 关联文件:
 * - controllers/StudentController.php: 学生管理控制器
 * - api/index.php: API入口
 * - controllers/ImportController.php: 数据导入控制器
 * - assets/js/students.js: 学生管理前端脚本
 * - uploads/photos/: 学生照片存储目录
 */
-->
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>学生信息管理</title>
    <link href="../assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/common.css" rel="stylesheet">
    <!-- 引入 SweetAlert2 的 CSS 和 JS -->
    <link href="../assets/css/sweetalert2.min.css" rel="stylesheet">
    <script src="../assets/js/sweetalert2.all.min.js"></script>
    <style>
        .modal.fade .modal-dialog {
            transition: transform .3s ease-out;
            transform: scale(0.95);
        }
        .modal.show .modal-dialog {
            transform: scale(1);
        }
        /* 保留模块特定的样式 */
        .student-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
        }
        
        .import-preview {
            max-height: 400px;
            overflow-y: auto;
        }
        
        /* 批量导入进度条容器 */
        .import-progress-container {
            display: none;
            margin-top: 1rem;
        }
        
        /* 自定义文件上传按钮 */
        .custom-file-upload {
            display: inline-block;
            padding: 0.5rem 1rem;
            cursor: pointer;
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            transition: all 0.2s ease;
        }
        
        .custom-file-upload:hover {
            background: #e9ecef;
        }
        
        /* 搜索框容器 */
        .search-container {
            position: relative;
            max-width: 300px;
        }
        
        /* 过滤器下拉菜单 */
        .filter-dropdown {
            min-width: 200px;
            padding: 1rem;
        }
        
        .filter-dropdown .form-check {
            margin-bottom: 0.5rem;
        }
        
        /* 表格内的操作按钮组 */
        .action-buttons .btn {
            margin: 0 0.2rem;
        }
        
        /* 导入模板下载链接 */
        .template-download {
            display: inline-flex;
            align-items: center;
            color: #0d6efd;
            text-decoration: none;
            margin-left: 1rem;
        }
        
        .template-download:hover {
            text-decoration: underline;
        }
        
        /* 错误提示样式 */
        .error-message {
            color: #dc3545;
            font-size: 0.875rem;
            margin-top: 0.25rem;
        }
        
        /* 必填字段标记 */
        .required-field::after {
            content: "*";
            color: #dc3545;
            margin-left: 4px;
        }
        
        /* 照片预览容器 */
        .photo-preview {
            width: 150px;
            height: 150px;
            border-radius: 8px;
            overflow: hidden;
            margin-top: 1rem;
            border: 2px dashed #dee2e6;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f8f9fa;
        }
        
        .photo-preview img {
            max-width: 100%;
            max-height: 100%;
            object-fit: cover;
        }
        
        /* 状态标签样式 */
        .status-badge {
            padding: 0.25em 0.6em;
            font-size: 0.875em;
            border-radius: 30px;
        }

        /* 自定义下拉框样式 */
        .custom-select-wrapper {
            position: relative;
            width: inherit;
            margin-bottom: 0;
            transition: all 0.3s ease;
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
            transition: all 0.2s ease;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
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
        
        /* 表格响应式处理 */
        @media (max-width: 768px) {
            .table-responsive {
                margin-bottom: 0;
            }
            
            .action-buttons .btn {
                padding: 0.25rem 0.5rem;
                font-size: 0.875rem;
            }
            
            .search-container {
                max-width: 100%;
                margin-bottom: 1rem;
            }
            
            /* 移动设备上年级和班级选择器样式 */
            .custom-select-wrapper {
                min-width: 120px !important;
                max-width: 160px !important;
            }
            
            .custom-select-trigger {
                padding: 0.5rem 0.75rem;
                font-size: 0.95rem;
                min-height: 38px;
            }
            
            /* 确保按钮在移动设备上不会太大 */
            #importBtn, #reorderBtn {
                padding: 0.375rem 0.75rem;
                font-size: 0.9rem;
            }
        }

        /* 学生排序相关样式 */
        .student-number-list,
        .student-name-list {
            min-height: 400px;
        }
        
        .student-number-item,
        .student-name-item {
            height: 40px;
            width: 100%;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
        }
        
        .student-name-item .student-name {
            transition: background-color 0.3s ease;
            width: 100px;
            padding: 8px;
            border: 1px solid #add8e6; /* 浅蓝色边框 */
            border-radius: 4px;
            cursor: move;
            text-align: center;
        }
        
        .student-name-item.student-chosen .student-name {
            background-color: #e9ecef;
            box-shadow: 0 0 10px rgba(173, 216, 230, 0.5); /* 选中时加强浅蓝色阴影 */
            border: 1px solid #88c8ff; /* 选中时边框颜色更深 */
        }
        
        .student-ghost {
            opacity: 0.5;
        }
        
        .student-column {
            width: 180px; 
            margin: 0 10px 20px 10px;
            position: relative;
        }
        
        .student-column:not(:last-child)::after {
            content: '';
            position: absolute;
            right: -10px;
            top: 0;
            bottom: 0;
            width: 1px;
            background-color: #dee2e6;
        }
        
        .student-number {
            width: 60px;
            text-align: center;
            padding: 8px 0;
        }
        
        .student-column-wrapper {
            display: flex;
            flex-direction: row;
            flex-wrap: nowrap;
            width: fit-content;
            margin: 0 auto;
        }
        
        .students-layout {
            display: flex;
            flex-direction: row;
            width: 100%;
        }
        
        .reorder-modal-title {
            display: flex;
            align-items: center;
        }
        
        .reorder-modal-title .class-info {
            font-size: 0.9rem;
            color: #fff;
            margin-left: 10px;
            font-weight: normal;
        }
    </style>
</head>
<body>
    <div class="container-fluid mt-3">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">学生信息管理</h5>
                        <div>
                            <button class="btn btn-success" id="fileImportBtn">
                                <i class="fas fa-file-import me-1"></i>文件导入
                            </button>
                            <button class="btn btn-danger" id="batchDeleteBtn">
                                <i class="fas fa-trash-alt me-1"></i>批量删除学生
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <!-- 班主任提示信息 -->
                        <div id="headteacherNotice" class="alert alert-warning mb-3" style="display: none;">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            请将本班学生的姓名都录入系统，若学生当天缺考，会由登分老师标记为缺考，<strong>禁止在此处删除或不录入对应学生的姓名</strong>。
                        </div>
                        <!-- 筛选区域 -->
                        <div class="row mb-3">
                            <div class="col-12 d-flex flex-wrap align-items-center gap-2 mb-2">
                                <div style="width: auto; min-width: 140px; max-width: 45%;" class="me-2">
                                    <select class="form-select" id="gradeFilter">
                                        <option value="">选择年级</option>
                                    </select>
                                </div>
                                <div style="width: auto; min-width: 140px; max-width: 45%;">
                                    <select class="form-select" id="classFilter">
                                        <option value="">选择班级</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-12 d-flex flex-wrap gap-2">
                                <button class="btn btn-primary" id="importBtn">
                                    <i class="fas fa-user-plus me-1"></i>批量导入
                                </button>
                                <button class="btn btn-warning" id="reorderBtn" style="display: none;">
                                    <i class="fas fa-sort-amount-down me-1"></i>重新排序
                                </button>
                            </div>
                        </div>
                        
                        <!-- 学生列表 -->
                        <div id="studentList">
                            <!-- 学生列表将通过 AJAX 加载 -->
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 导入学生模态框 -->
    <div class="modal fade" id="importModal" tabindex="-1" data-bs-backdrop="static">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">批量导入学生</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="importForm">
                        <div class="mb-3">
                            <label class="form-label">年级</label>
                            <div class="form-control-plaintext border rounded p-2" id="selectedGrade" style="background: #f8f9fa;"></div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">班级</label>
                            <div class="form-control-plaintext border rounded p-2" id="selectedClass" style="background: #f8f9fa;"></div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">学生姓名列表</label>
                            <textarea class="form-control" id="importData" rows="10" required 
                                placeholder="请粘贴学生姓名列表，每行一个姓名"></textarea>
                        </div>
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" id="autoSort">
                            <label class="form-check-label" for="autoSort">
                                按姓氏排序（勾选此项系统会自动按姓名首字母进行排序）
                            </label>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
                    <button type="button" class="btn btn-primary" onclick="handleImport()">导入</button>
                </div>
            </div>
        </div>
    </div>

    <!-- 导入确认模态框 -->
    <div class="modal fade" id="confirmImportModal" tabindex="-1" aria-labelledby="confirmImportModalLabel" aria-hidden="false">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="confirmImportModalLabel">确认导入</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="关闭"></button>
                </div>
                <div class="modal-body">
                    <p>请确认以下信息：</p>
                    <div class="mb-2">年级：<span id="confirmGrade"></span></div>
                    <div class="mb-2">班级：<span id="confirmClass"></span></div>
                    <div class="mb-2">学生数量：<span id="confirmCount"></span></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
                    <button type="button" class="btn btn-primary" id="confirmImportBtn">确认导入</button>
                </div>
            </div>
        </div>
    </div>

    <!-- 编辑学生模态框 -->
    <div class="modal fade" id="editStudentModal" tabindex="-1" aria-labelledby="editStudentModalLabel" aria-hidden="false">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editStudentModalLabel">编辑学生</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="关闭"></button>
                </div>
                <div class="modal-body">
                    <form id="editStudentForm">
                        <input type="hidden" id="editStudentId">
                        <input type="hidden" id="editStudentNumber">
                        <input type="hidden" id="editGradeId">
                        <input type="hidden" id="editClassId">
                        <div class="mb-3">
                            <label class="form-label">学生姓名</label>
                            <input type="text" class="form-control" id="editStudentName" required>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
                    <button type="button" class="btn btn-primary" onclick="updateStudent()">保存</button>
                </div>
            </div>
        </div>
    </div>

    <!-- 成功提示模态框 -->
    <div class="modal fade" id="successModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">操作成功</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p id="successMessage"></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary" data-bs-dismiss="modal">确定</button>
                </div>
            </div>
        </div>
    </div>

    <!-- 警告框容器 -->
    <div class="alert-container position-fixed start-50 translate-middle-x" style="z-index: 1060; top: 60px;">
    </div>

    <!-- 批量删除确认模态框 -->
    <div class="modal fade" id="batchDeleteModal" tabindex="-1" aria-labelledby="batchDeleteModalLabel" aria-hidden="false" data-bs-backdrop="static">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="batchDeleteModalLabel">批量删除学生</h5>
                    <button type="button" class="btn-close" id="closeDeleteModal" data-bs-dismiss="modal" aria-label="关闭"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">年级</label>
                        <select class="form-select" id="deleteGrade">
                            <option value="">请选择年级</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">删除范围</label>
                        <select class="form-select" id="deleteScope">
                            <option value="class">指定班级</option>
                            <option value="all_classes">全部班级</option>
                        </select>
                    </div>
                    <div class="mb-3" id="deleteClassContainer">
                        <label class="form-label">班级</label>
                        <select class="form-select" id="deleteClass">
                            <option value="">请选择班级</option>
                        </select>
                    </div>
                    <div class="alert alert-danger">
                        <strong>警告：</strong>此操作将删除所选范围内的所有学生，且无法恢复！
                    </div>
                    <div class="mb-3">
                        <label class="form-label">请输入"<span id="confirmText" class="text-danger fw-bold"></span>"以确认删除</label>
                        <input type="text" class="form-control" id="deleteConfirmInput">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" id="cancelDeleteBtn">取消</button>
                    <button type="button" class="btn btn-danger" onclick="confirmBatchDelete()" id="confirmDeleteBtn" disabled>确认删除</button>
                </div>
            </div>
        </div>
    </div>

    <!-- 文件导入模态框 -->
    <div class="modal fade" id="fileImportModal" tabindex="-1" data-bs-backdrop="static">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">文件导入学生</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <div class="alert alert-info">
                            <strong>可用的班级代码：</strong>
                            <div id="classCodeList" class="mt-2">
                                <!-- 班级代码列表将通过 AJAX 加载 -->
                            </div>
                            <small class="d-block mt-2 text-danger">请务必使用以上列出的班级代码，否则导入将失败</small>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">选择文件</label>
                        <input type="file" class="form-control" id="importFile" accept=".xlsx,.xls">
                        <small class="text-muted">支持 Excel 文件格式（.xlsx, .xls）</small>
                        <div class="mt-2">
                            <a href="../templates/download_template.php" class="btn btn-outline-primary btn-sm">
                                <i class="fas fa-download me-1"></i>下载导入模板
                            </a>
                            <small class="text-muted ms-2">请下载模板后用Excel打开填写数据</small>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
                    <button type="button" class="btn btn-primary" onclick="handleFileImport()">导入</button>
                </div>
            </div>
        </div>
    </div>

    <!-- 重新排序模态框 -->
    <div class="modal fade" id="reorderModal" tabindex="-1" data-bs-backdrop="static">
        <div class="modal-dialog" id="reorderModalDialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title reorder-modal-title">学生排序管理<span class="class-info" id="reorderClassInfo"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info py-2 mb-3">
                        <p class="mb-0"><small><strong>提示：</strong>拖拽<strong>学生姓名</strong>可以调整学生的位置，学号需要连续不能留空。调整学号不会影响学生关联的其他数据。</small></p>
                    </div>
                    <div id="studentsGrid" class="d-flex flex-wrap justify-content-start" style="min-height: 400px;">
                        <!-- 学生信息将通过JavaScript动态加载 -->
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
                    <button type="button" class="btn btn-primary" id="saveReorderBtn">保存排序</button>
                </div>
            </div>
        </div>
    </div>

    <script src="../assets/js/jquery.min.js"></script>
    <script src="../assets/js/bootstrap.bundle.min.js"></script>
    <!-- 添加 Sortable.js 库（本地加载） -->
    <script src="../assets/js/Sortable.min.js"></script>
    <script>
        // 全局变量定义
        window.userRole = window.userRole || null;

        // 初始化自定义下拉框函数
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
                    
                    // 确保下拉菜单在视口内可见
                    if (wrapper.hasClass('open')) {
                        const optionsEl = options[0];
                        const wrapperRect = wrapper[0].getBoundingClientRect();
                        const viewportHeight = window.innerHeight;
                        const spaceBelow = viewportHeight - wrapperRect.bottom;
                        const optionsHeight = optionsEl.offsetHeight;
                        
                        // 如果下方空间不足，则向上展开
                        if (spaceBelow < optionsHeight && wrapperRect.top > optionsHeight) {
                            options.css({
                                'top': 'auto',
                                'bottom': '100%',
                                'margin-top': '0',
                                'margin-bottom': '0.25rem'
                            });
                        } else {
                            options.css({
                                'top': '100%',
                                'bottom': 'auto',
                                'margin-top': '0.25rem',
                                'margin-bottom': '0'
                            });
                        }
                        
                        // 在移动设备上调整宽度
                        if (window.innerWidth <= 768) {
                            // 确保下拉菜单不会超出屏幕
                            const optionsWidth = Math.min(250, window.innerWidth - 30);
                            options.css('width', optionsWidth + 'px');
                        }
                    }
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

        // 加载年级列表
        function loadGrades(targetSelector = '#gradeFilter') {
            //console.log('正在加载年级列表到:', targetSelector);
            $.ajax({
                url: '../api/index.php?route=grade/getList',
                method: 'GET',
                dataType: 'json',
                success: function(response) {
                    //console.log('年级列表响应:', response);
                    if (response.success && response.data) {
                        const gradeSelect = $(targetSelector);
                        gradeSelect.empty();
                        gradeSelect.append('<option value="">请选择年级</option>');
                        
                        if (Array.isArray(response.data)) {
                            response.data.forEach(function(grade) {
                                gradeSelect.append(`<option value="${grade.id}">${grade.grade_name}</option>`);
                            });
                        }
                        // 重新初始化自定义下拉框
                        initCustomSelects();
                        
                        // 如果是主年级选择器，并且有数据，则加载第一个年级的班级
                        if (targetSelector === '#gradeFilter' && response.data && response.data.length > 0) {
                            // 触发年级选择变化事件
                            $(targetSelector).trigger('change');
                        }
                    } else {
                        console.error('加载年级失败:', response.error);
                        showAlert(response.error || '加载年级失败');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('加载年级请求失败:', {
                        status: status,
                        error: error,
                        response: xhr.responseText
                    });
                    showAlert('加载年级失败：' + (xhr.responseJSON?.error || '未知错误'));
                }
            });
        }

        // 根据年级加载班级列表
        function loadClasses(gradeId, targetSelector = '#classFilter') {
            if (!gradeId) {
                const classSelect = $(targetSelector);
                classSelect.empty();
                classSelect.append('<option value="">请先选择年级</option>');
                // 重新初始化自定义下拉框
                initCustomSelects();
                return;
            }

            $.ajax({
                url: '../api/index.php?route=class/getList',
                method: 'GET',
                data: { grade_id: gradeId },
                dataType: 'json',
                success: function(response) {
                    //console.log('班级列表响应:', response);
                    if (response.success && response.data) {
                        const classSelect = $(targetSelector);
                        classSelect.empty();
                        classSelect.append('<option value="">请选择班级</option>');
                        
                        if (response.data && response.data.length > 0) {
                            response.data.forEach(function(cls) {
                                classSelect.append(`<option value="${cls.id}">${cls.class_name}</option>`);
                            });
                        } else {
                            classSelect.append('<option value="" disabled>该年级下暂无班级</option>');
                        }
                        // 重新初始化自定义下拉框
                        initCustomSelects();
                    } else {
                        console.error('加载班级失败:', response.error);
                        showAlert(response.error || '加载班级失败');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('加载班级请求失败:', {
                        status: status,
                        error: error,
                        response: xhr.responseText
                    });
                    showAlert('加载班级失败：' + (xhr.responseJSON?.error || '未知错误'));
                }
            });
        }

        // 加载学生列表
        function loadStudents() {
            const classId = $('#classFilter').val();
            const gradeId = $('#gradeFilter').val();  // 获取当前选中的年级ID
            
            if (!classId) {
                $('#studentList').html('<div class="alert alert-info">请选择班级</div>');
                // 隐藏重新排序按钮
                $('#reorderBtn').hide();
                return;
            }

            $.ajax({
                url: '../api/index.php?route=student/students',
                method: 'GET',
                data: { 
                    class_id: classId,
                    grade_id: gradeId  // 添加年级ID参数
                },
                dataType: 'json',
                success: function(response) {
                    //console.log('学生列表响应:', response);
                    if (response.success) {
                        if (!response.data || response.data.length === 0) {
                            $('#studentList').html('<div class="alert alert-warning">当前班级没有学生，请添加</div>');
                            // 隐藏重新排序按钮
                            $('#reorderBtn').hide();
                            return;
                        }

                        // 显示重新排序按钮
                        $('#reorderBtn').show();

                        let html = '<div class="table-responsive"><table class="table table-hover">';
                        html += '<thead><tr><th>编号</th><th>姓名</th><th>班级</th><th>操作</th></tr></thead><tbody>';
                        
                        response.data.forEach(function(student) {
                            // 使用当前选中的年级ID
                            const studentGradeId = student.grade_id || gradeId;
                            html += `<tr>
                                <td>${student.student_number}</td>
                                <td>${student.student_name}</td>
                                <td>${student.grade_name} ${student.class_name}</td>
                                <td>
                                    <button class="btn btn-sm btn-primary me-2 edit-student" 
                                        data-id="${student.id}" 
                                        data-name="${student.student_name}" 
                                        data-number="${student.student_number}"
                                        data-grade-id="${studentGradeId}"
                                        data-class-id="${student.class_id}">编辑</button>
                                    <button class="btn btn-sm btn-danger delete-student" data-id="${student.id}">删除</button>
                                </td>
                            </tr>`;
                        });
                        
                        html += '</tbody></table></div>';
                        $('#studentList').html(html);
                    } else {
                        $('#studentList').html('<div class="alert alert-warning">加载学生列表失败</div>');
                        // 隐藏重新排序按钮
                        $('#reorderBtn').hide();
                    }
                },
                error: function(xhr, status, error) {
                    console.error('加载学生列表失败:', {
                        status: status,
                        error: error,
                        response: xhr.responseText
                    });
                    $('#studentList').html('<div class="alert alert-danger">加载学生列表失败</div>');
                    // 隐藏重新排序按钮
                    $('#reorderBtn').hide();
                }
            });
        }

        // 显示警告的函数
        function showAlert(message, type = 'danger', isHtml = false) {
            Swal.fire({
                title: '提示',
                [isHtml ? 'html' : 'text']: message,
                icon: type === 'danger' ? 'error' : type,
                timer: type === 'success' ? 1500 : undefined,
                showConfirmButton: type !== 'success'
            });
        }

        // 显示成功消息的函数
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

        // 获取当前用户信息
        function getCurrentUser() {
            return new Promise((resolve, reject) => {
                $.ajax({
                    url: '../api/index.php?route=auth/current_user',
                    method: 'GET',
                    success: function(response) {
                        //console.log('获取用户信息响应:', response);
                        if (response.success && response.user) {  // 修改这里，从response.user获取数据
                            window.userRole = response.user.role;
                            //console.log('当前用户角色:', window.userRole);
                            initializeUI();
                            resolve(response.user);  // 修改这里，返回user对象
                        } else {
                            console.error('获取用户信息失败:', response.error);
                            reject(new Error(response.error || '获取用户信息失败'));
                        }
                    },
                    error: function(xhr) {
                        console.error('获取用户信息请求失败:', xhr);
                        reject(new Error(xhr.responseJSON?.error || '获取用户信息失败'));
                    }
                });
            });
        }

        // 初始化UI和事件绑定
        function initializeUI() {
            // 根据用户角色设置按钮状态和显示提示
            if (window.userRole === 'headteacher') {
                // 禁用文件导入按钮
                $('#fileImportBtn').prop('disabled', true)
                    .attr('title', '班主任无此权限')
                    .css({
                        'opacity': '0.65',
                        'cursor': 'not-allowed',
                        'pointer-events': 'none'
                    });
                
                // 禁用批量删除按钮
                $('#batchDeleteBtn').prop('disabled', true)
                    .attr('title', '班主任无此权限')
                    .css({
                        'opacity': '0.65',
                        'cursor': 'not-allowed',
                        'pointer-events': 'none'
                    });
                
                // 显示班主任提示信息
                $('#headteacherNotice').show();
            } else {
                $('#fileImportBtn').off('click').on('click', showFileImportModal);
                $('#batchDeleteBtn').off('click').on('click', showBatchDeleteModal);
                
                // 确保初始化时加载年级列表
                loadGrades();
                $('#headteacherNotice').hide();
            }

            // 加载年级列表
            loadGrades();

            // 绑定事件监听
            bindEventListeners();
        }

        // 关闭批量删除模态框
        function closeDeleteModal() {
            const batchDeleteModal = bootstrap.Modal.getInstance(document.getElementById('batchDeleteModal'));
            if (batchDeleteModal) {
                batchDeleteModal.hide();
                
                // 延时执行，确保模态框完全关闭后清理
                setTimeout(() => {
                    // 移除模态框背景
                    $('.modal-backdrop').remove();
                    // 移除body上的modal相关类和样式
                    $('body').removeClass('modal-open').css('overflow', '');
                    $('body').css('padding-right', '');
                    
                    // 重置表单
                    $('#deleteGrade').val('');
                    $('#deleteScope').val('class');
                    $('#deleteClass').val('');
                    $('#deleteConfirmInput').val('');
                    $('#confirmText').text('');
                    $('#confirmDeleteBtn').prop('disabled', true);
                    
                    // 重新初始化自定义下拉框
                    initCustomSelects();
                }, 300);
            }
        }

        // 显示批量删除模态框
        function showBatchDeleteModal() {
            const batchDeleteModal = new bootstrap.Modal(document.getElementById('batchDeleteModal'));
            batchDeleteModal.show();
            loadGrades('#deleteGrade');
        }

        // 绑定所有事件监听器
        function bindEventListeners() {
            // 年级选择变化
            $('#gradeFilter').on('change', function() {
                const gradeId = $(this).val();
                if (gradeId) {
                    loadClasses(gradeId);
                } else {
                    $('#classFilter').empty().append('<option value="">请先选择年级</option>');
                    // 重新初始化自定义下拉框
                    initCustomSelects();
                }
            });

            // 班级选择变化
            $('#classFilter').on('change', function() {
                loadStudents();
            });

            // 批量删除按钮
                $('#batchDeleteBtn').on('click', showBatchDeleteModal);

            // 批量导入按钮
                $('#importBtn').on('click', function() {
                    const gradeId = $('#gradeFilter').val();
                    const classId = $('#classFilter').val();
                    
                    if (!gradeId || !classId) {
                        showAlert('请先选择年级和班级', 'warning');
                        return;
                    }

                    // 获取选中的年级和班级名称
                    const gradeName = $('#gradeFilter option:selected').text();
                    const className = $('#classFilter option:selected').text();

                    // 显示选中的年级和班级
                    $('#selectedGrade').text(gradeName);
                    $('#selectedClass').text(className);
                    
                    // 清空学生姓名列表和重置自动排序选项
                    $('#importData').val('');
                    $('#autoSort').prop('checked', false);
                    
                    const importModal = new bootstrap.Modal(document.getElementById('importModal'));
                    importModal.show();
                });

            // 删除学生事件委托
                $(document).on('click', '.delete-student', function() {
                    const id = $(this).data('id');
                const studentName = $(this).closest('tr').find('td:eq(1)').text();
                deleteStudent(id, studentName);
            });

            // 编辑学生事件委托
                $(document).on('click', '.edit-student', function() {
                    const id = $(this).data('id');
                    const name = $(this).data('name');
                    const number = $(this).data('number');
                    const gradeId = $(this).data('grade-id');
                    const classId = $(this).data('class-id');
                    showEditModal(id, name, number, gradeId, classId);
                });

            // 监听批量删除模态框中的年级选择变化
            $('#deleteGrade').on('change', function() {
                const gradeId = $(this).val();
                if (gradeId) {
                    loadClasses(gradeId, '#deleteClass');
                } else {
                    $('#deleteClass').empty().append('<option value="">请先选择年级</option>');
                    // 重新初始化自定义下拉框
                    initCustomSelects();
                }
                updateConfirmText();
            });

            // 监听删除范围选择变化
            $('#deleteScope').on('change', function() {
                const scope = $(this).val();
                if (scope === 'all_classes') {
                    $('#deleteClassContainer').hide();
                    $('#deleteClass').val('');
                } else {
                    $('#deleteClassContainer').show();
                    const gradeId = $('#deleteGrade').val();
                    if (gradeId) {
                        loadClasses(gradeId, '#deleteClass');
                    }
                }
                updateConfirmText();
                // 重新初始化自定义下拉框
                initCustomSelects();
            });

            // 监听班级选择变化
            $('#deleteClass').on('change', function() {
                updateConfirmText();
            });

            // 监听确认文本输入
            $('#deleteConfirmInput').on('input', function() {
                const inputText = $(this).val();
                const expectedText = $('#confirmText').text();
                $('#confirmDeleteBtn').prop('disabled', inputText !== expectedText);
            });
            
            // 监听批量删除模态框的取消按钮点击
            $('#cancelDeleteBtn, #closeDeleteModal').on('click', function() {
                closeDeleteModal();
            });
            
            // 监听批量删除模态框关闭事件，确保完全清除
            $('#batchDeleteModal').on('hidden.bs.modal', function() {
                // 确保背景遮罩被完全移除
                $('.modal-backdrop').remove();
                $('body').removeClass('modal-open').css('overflow', '');
                $('body').css('padding-right', '');
            });

            // 重新排序按钮点击事件
            $('#reorderBtn').on('click', function() {
                showReorderModal();
            });
            
            // 保存排序按钮点击事件
            $('#saveReorderBtn').on('click', function() {
                saveReorderedStudents();
            });
        }

        // 页面加载完成后初始化
        $(document).ready(function() {
            // 获取用户信息并初始化界面
            getCurrentUser().catch(function(error) {
                console.error('获取用户信息失败:', error);
                // 即使获取用户信息失败，也初始化界面
                loadGrades();
                initializeUI();
            });
            
            // 初始化自定义下拉框
            initCustomSelects();
        });

        // 删除学生
        function deleteStudent(id, studentName) {
            Swal.fire({
                title: '确认删除',
                html: `确定要删除学生 <strong>${studentName}</strong> 吗？<br><small class="text-danger">此操作不可恢复</small>`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: '确定删除',
                cancelButtonText: '取消',
                focusCancel: true,
                customClass: {
                    popup: 'swal2-small'
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: '../api/index.php?route=student/delete',
                        method: 'POST',
                        data: { id: id },
                        success: function(response) {
                            if (response.success) {
                                showSuccess('删除成功');
                                loadStudents();
                            } else {
                                showAlert(response.error || '删除失败', 'danger', response.isHtml);
                            }
                        },
                        error: function(xhr) {
                            let errorMsg = xhr.responseJSON?.error || '未知错误';
                            let isHtml = xhr.responseJSON?.isHtml || false;
                            showAlert(errorMsg, 'danger', isHtml);
                        }
                    });
                }
            });
        }

        // 显示编辑模态框
        function showEditModal(id, name, number, gradeId, classId) {
            $('#editStudentId').val(id);
            $('#editStudentName').val(name);
            $('#editStudentNumber').val(number);
            $('#editGradeId').val(gradeId);
            $('#editClassId').val(classId);
            const editModal = new bootstrap.Modal(document.getElementById('editStudentModal'));
            editModal.show();
        }

        // 显示文件导入模态框
        function showFileImportModal() {
            // 检查用户角色
            if (window.userRole === 'headteacher') {
                showAlert('班主任无权使用文件导入功能', 'warning');
                return;
            }
            
            // 清空文件输入
            $('#importFile').val('');
            
            // 加载班级代码列表
            loadClassCodes();
            
            const fileImportModal = new bootstrap.Modal(document.getElementById('fileImportModal'));
            fileImportModal.show();
        }

        // 加载班级代码列表
        function loadClassCodes() {
            $.ajax({
                url: '../api/index.php?route=class/getList',
                method: 'GET',
                dataType: 'json',
                success: function(response) {
                    //console.log('班级代码列表响应:', response);
                    if (response.success && response.data) {
                        const classCodeList = $('#classCodeList');
                        classCodeList.empty();
                        
                        const classes = response.data;
                        const codesByGrade = {};
                        
                        // 按年级分组班级代码
                        classes.forEach(cls => {
                            if (!codesByGrade[cls.grade_name]) {
                                codesByGrade[cls.grade_name] = [];
                            }
                            codesByGrade[cls.grade_name].push(`${cls.class_code}`);
                        });
                        
                        // 生成显示内容
                        Object.keys(codesByGrade).forEach(gradeName => {
                            const codes = codesByGrade[gradeName].join('、');
                            classCodeList.append(
                                `<div class="mb-1">
                                    <strong>${gradeName}：</strong>${codes}
                                </div>`
                            );
                        });
                    } else {
                        console.error('加载班级代码失败:', response.error);
                        $('#classCodeList').html('<div class="text-danger">加载班级代码失败</div>');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('加载班级代码请求失败:', {
                        status: status,
                        error: error,
                        response: xhr.responseText
                    });
                    $('#classCodeList').html('<div class="text-danger">加载班级代码失败</div>');
                }
            });
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
            /*console.log('上传文件信息:', {
                name: file.name,
                type: file.type,
                size: file.size
            });*/
            
            // 检查文件扩展名
            const fileName = file.name.toLowerCase();
            const validExtension = fileName.endsWith('.xlsx') || fileName.endsWith('.xls');
            
            // 不同浏览器的MIME类型判断
            const allowedTypes = [
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'application/vnd.ms-excel',
                'application/octet-stream',  // 某些浏览器可能会用这个类型
                'application/x-excel',       // 某些浏览器可能使用这个类型
                'application/excel',         // 某些浏览器可能使用这个类型
                ''                          // 微信浏览器有时可能不提供类型
            ];

            // 如果扩展名不是Excel或类型不在允许列表内
            if (!validExtension) {
                //console.log("文件导入: 文件扩展名无效", fileName);
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

            // 即使文件MIME类型不匹配，如果扩展名正确，我们也继续尝试
            if (!allowedTypes.includes(file.type) && file.type !== '') {
                console.warn("文件导入: 文件MIME类型不在允许列表内，但扩展名正确，尝试继续", file.type);
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

            // 浏览器检测
            const ua = navigator.userAgent.toLowerCase();
            const isWeiXin = ua.indexOf('micromessenger') !== -1;
            /*console.log("文件导入: 当前浏览器信息", {
                userAgent: ua,
                isWeiXin: isWeiXin
            });*/

            $.ajax({
                url: '../api/index.php?route=student/import_file',
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                cache: false,
                timeout: 60000, // 微信浏览器可能较慢，设置更长的超时
                xhrFields: {
                    withCredentials: true
                },
                beforeSend: function(xhr) {
                    //console.log("文件导入: 开始发送请求");
                },
                success: function(response) {
                    try {
                        //console.log('文件导入响应:', response);
                        
                        // 确保 response 是 JSON 对象
                        if (typeof response === 'string') {
                            try {
                                response = JSON.parse(response);
                                //console.log("文件导入: 解析字符串响应为JSON", response);
                            } catch(parseError) {
                                console.error("文件导入: 响应解析失败", parseError);
                                throw new Error("响应格式错误");
                            }
                        }
                        
                        if (response && response.success) {
                            const fileImportModal = bootstrap.Modal.getInstance(document.getElementById('fileImportModal'));
                            if (fileImportModal) {
                                fileImportModal.hide();
                            }
                            
                            // 给微信浏览器一点时间来关闭模态框
                            setTimeout(() => {
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
                                    loadStudents();
                                });
                            }, isWeiXin ? 300 : 0);
                        } else {
                            const errorMsg = response && response.error ? response.error : '导入失败';
                            const isHtml = response && response.isHtml === true;
                            
                            Swal.fire({
                                title: '导入失败',
                                html: isHtml ? errorMsg : `<div style="text-align: left;">${errorMsg || '导入失败'}</div>`,
                                icon: 'error',
                                showConfirmButton: false,
                                timer: 3000,
                                customClass: {
                                    popup: 'swal2-small',
                                    htmlContainer: 'text-left'
                                }
                            });
                        }
                    } catch (e) {
                        console.error('处理文件导入响应时出错:', e);
                        Swal.fire({
                            title: '导入失败',
                            text: '处理响应数据时出错: ' + e.message,
                            icon: 'error',
                            showConfirmButton: true,
                            customClass: {
                                popup: 'swal2-small'
                            }
                        });
                    }
                },
                error: function(xhr, status, error) {
                    console.error('导入失败:', {
                        status: status,
                        error: error,
                        xhr: xhr
                    });
                    
                    let errorMessage = '未知错误';
                    let responseText = '';
                    
                    // 尝试从不同属性获取错误信息
                    try {
                        if (xhr && xhr.responseText) {
                            responseText = xhr.responseText;
                            try {
                                const responseObj = JSON.parse(xhr.responseText);
                                errorMessage = responseObj.error || errorMessage;
                               // console.log("文件导入: 从responseText解析到错误信息", errorMessage);
                            } catch(e) {
                                console.warn("文件导入: 无法解析responseText", xhr.responseText);
                            }
                        } else if (xhr && xhr.responseJSON && xhr.responseJSON.error) {
                            errorMessage = xhr.responseJSON.error;
                           // console.log("文件导入: 从responseJSON获取到错误信息", errorMessage);
                        } else if (status === 'timeout') {
                            errorMessage = '请求超时，请稍后再试';
                        } else if (error) {
                            errorMessage = error;
                        }
                    } catch (e) {
                        console.error('解析错误信息时出错:', e);
                    }
                    
                    // 在微信浏览器中，显示更多的调试信息
                    if (isWeiXin && responseText) {
                        //console.log("微信浏览器错误详情:", responseText);
                    }
                    
                    Swal.fire({
                        title: '导入失败',
                        text: errorMessage + (isWeiXin ? '。微信浏览器导入Excel文件可能会受限，建议使用电脑或手机上的其他浏览器尝试。' : '。如持续失败，请使用其他浏览器尝试。'),
                        icon: 'error',
                        showConfirmButton: true,
                        customClass: {
                            popup: 'swal2-small'
                        }
                    });
                },
                complete: function() {
                    //console.log("文件导入: 请求完成");
                }
            });
        }

        // 处理导入
        function handleImport() {
            //console.log("开始执行handleImport函数");
            const gradeId = $('#gradeFilter').val();
            const classId = $('#classFilter').val();
            const names = $('#importData').val().trim();
            const autoSort = $('#autoSort').prop('checked');
            
            // 检查浏览器类型
            const ua = navigator.userAgent.toLowerCase();
            const isWeiXin = ua.indexOf('micromessenger') !== -1;
            /*console.log("学生导入: 浏览器信息", {
                userAgent: ua,
                isWeiXin: isWeiXin
            });*/
            
            if (!classId || !names) {
                showAlert('请填写完整信息');
                return;
            }

            // 检查是否有同名学生
            const studentNames = names.split('\n').map(name => name.trim()).filter(name => name);
            //console.log(`处理导入: 共有 ${studentNames.length} 名学生`);
            
            const duplicateNames = findDuplicateNames(studentNames);
            if (duplicateNames.length > 0) {
                //console.log("检测到重名学生:", duplicateNames);
                Swal.fire({
                    title: '发现重名学生',
                    html: `以下学生姓名重复：<br>${duplicateNames.join('<br>')}`,
                    icon: 'warning',
                    confirmButtonText: '确认',
                    customClass: {
                        popup: 'swal2-small'
                    }
                });
                return;
            }

            // 获取选中年级和班级的文本
            const gradeName = $('#gradeFilter option:selected').text();
            const className = $('#classFilter option:selected').text();
            
            /*console.log("导入信息:", {
                gradeName: gradeName,
                className: className,
                studentCount: studentNames.length,
                autoSort: autoSort
            });*/

            // 关闭导入模态框
            try {
                const importModal = bootstrap.Modal.getInstance(document.getElementById('importModal'));
                if (importModal) {
                    importModal.hide();
                    
                    // 确保模态框完全关闭后再显示确认对话框
                    setTimeout(() => {
                        showImportConfirmDialog();
                    }, isWeiXin ? 500 : 300); // 微信浏览器可能需要更长时间
                } else {
                    // 如果模态框实例不存在，直接显示确认对话框
                    showImportConfirmDialog();
                }
            } catch (e) {
                console.error("关闭模态框时出错:", e);
                // 出错时仍然尝试显示确认对话框
                showImportConfirmDialog();
            }
            
            // 显示确认对话框的函数
            function showImportConfirmDialog() {
                try {
                    // 确保 body 的 modal-open 类和 style 被移除
                    if ($('body').hasClass('modal-open')) {
                        $('body').removeClass('modal-open').css('overflow', '');
                        $('body').css('padding-right', '');
                        $('.modal-backdrop').remove();
                        //console.log("手动清理模态框残留样式");
                    }
                    
                    // 显示确认模态框
                    Swal.fire({
                        title: '确认导入',
                        html: `
                            <div class="text-left">
                                <p class="mb-2">请确认以下信息：</p>
                                <div class="mb-2">年级：${gradeName}</div>
                                <div class="mb-2">班级：${className}</div>
                                <div class="mb-2">学生数量：${studentNames.length}</div>
                                ${autoSort ? '<div class="mb-2 text-primary">将按姓氏排序</div>' : ''}
                            </div>
                        `,
                        icon: 'question',
                        showCancelButton: true,
                        confirmButtonText: '确认导入',
                        cancelButtonText: '取消',
                        reverseButtons: true,
                        allowOutsideClick: false,
                        customClass: {
                            popup: 'swal2-small',
                            htmlContainer: 'text-left'
                        }
                    }).then((result) => {
                        if (result.isConfirmed) {
                            //console.log("用户确认导入，开始调用importStudents");
                            importStudents(classId, names, autoSort);
                        } else {
                            //console.log("用户取消导入");
                        }
                    }).catch(err => {
                        console.error('SweetAlert确认对话框错误:', err);
                        // 如果SweetAlert出错，直接询问用户
                        const confirmManually = window.confirm(`确认导入${studentNames.length}名学生到${gradeName}${className}？`);
                        if (confirmManually) {
                            //console.log("SweetAlert出错，使用原生确认，用户确认导入");
                            importStudents(classId, names, autoSort);
                        } else {
                            //console.log("SweetAlert出错，使用原生确认，用户取消导入");
                        }
                    });
                } catch (dialogError) {
                    console.error("显示确认对话框时出错:", dialogError);
                    // 如果显示对话框出错，使用原生确认
                    const confirmManually = window.confirm(`确认导入${studentNames.length}名学生到${gradeName}${className}？`);
                    if (confirmManually) {
                        //console.log("显示对话框出错，使用原生确认，用户确认导入");
                        importStudents(classId, names, autoSort);
                    }
                }
            }
        }

        // 查找重复的名字
        function findDuplicateNames(names) {
            const nameCount = {};
            const duplicates = [];
            
            names.forEach(name => {
                nameCount[name] = (nameCount[name] || 0) + 1;
                if (nameCount[name] > 1 && !duplicates.includes(name)) {
                    duplicates.push(name);
                }
            });
            
            return duplicates;
        }

        // 执行导入
        function importStudents(classId, names, autoSort) {
            /*console.log("开始执行importStudents函数", {
                classId: classId,
                namesLength: names.length,
                autoSort: autoSort
            });*/
            
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

            // 将 autoSort 转换为字符串，提高兼容性
            const autoSortValue = autoSort ? 'true' : 'false';
            
            // 构建表单数据，避免直接发送JSON对象，增强兼容性
            const formData = new FormData();
            formData.append('class_id', classId);
            formData.append('names', names);
            formData.append('auto_sort', autoSortValue);
            
            /*console.log("准备发送请求", {
                url: '../api/index.php?route=student/import_students',
                method: 'POST',
                class_id: classId,
                auto_sort: autoSortValue
            });*/
            
            // 使用更兼容的数据请求方式
            $.ajax({
                url: '../api/index.php?route=student/import_students',
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                cache: false,
                timeout: 20000, // 20秒超时
                xhrFields: {
                    withCredentials: true
                },
                beforeSend: function(xhr) {
                    //console.log("请求准备发送");
                },
                success: function(response) {
                    try {
                        //console.log("请求成功返回", response);
                        
                        // 确保 response 是 JSON 对象
                        if (typeof response === 'string') {
                            try {
                                response = JSON.parse(response);
                               // console.log("解析字符串响应为JSON", response);
                            } catch(parseError) {
                                console.error("响应解析失败:", parseError);
                                throw new Error("响应格式错误");
                            }
                        }
                        
                        if (response && response.success) {
                            Swal.fire({
                                title: '导入成功',
                                text: response.message || '学生信息已成功导入',
                                icon: 'success',
                                timer: 1500,
                                showConfirmButton: false,
                                customClass: {
                                    popup: 'swal2-small'
                                }
                            }).then(() => {
                                loadStudents();
                            });
                        } else {
                            const errorMessage = (response && response.error) ? 
                                response.error : '导入失败，请稍后重试';
                                
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
                    } catch (e) {
                        console.error('处理响应时出错:', e);
                        Swal.fire({
                            title: '导入失败',
                            text: '处理响应数据时出错: ' + e.message,
                            icon: 'error',
                            showConfirmButton: false,
                            timer: 3000,
                            customClass: {
                                popup: 'swal2-small'
                            }
                        });
                    }
                },
                error: function(xhr, status, error) {
                    console.error('导入请求失败:', {
                        status: status,
                        error: error,
                        xhr: xhr
                    });
                    
                    let errorMessage = '未知错误';
                    
                    // 尝试从不同属性获取错误信息
                    try {
                        if (xhr && xhr.responseText) {
                            try {
                                const responseObj = JSON.parse(xhr.responseText);
                                errorMessage = responseObj.error || errorMessage;
                                //console.log("从responseText解析到错误信息", errorMessage);
                            } catch(e) {
                                console.warn("无法解析responseText", xhr.responseText);
                            }
                        } else if (xhr && xhr.responseJSON && xhr.responseJSON.error) {
                            errorMessage = xhr.responseJSON.error;
                            //console.log("从responseJSON获取到错误信息", errorMessage);
                        } else if (status === 'timeout') {
                            errorMessage = '请求超时，请稍后再试';
                        } else if (error) {
                            errorMessage = error;
                        }
                    } catch (e) {
                        console.error('解析错误信息时出错:', e);
                    }
                    
                    Swal.fire({
                        title: '导入失败',
                        text: errorMessage + '。如持续出现此问题，请使用其他浏览器尝试。',
                        icon: 'error',
                        showConfirmButton: true,
                        customClass: {
                            popup: 'swal2-small'
                        }
                    });
                },
                complete: function() {
                    //console.log("请求完成");
                }
            });
        }

        // 更新确认文本
        function updateConfirmText() {
            const gradeId = $('#deleteGrade').val();
            const scope = $('#deleteScope').val();
            const classId = $('#deleteClass').val();
            
            if (!gradeId) {
                $('#confirmText').text('');
                $('#confirmDeleteBtn').prop('disabled', true);
                return;
            }

            const gradeName = $('#deleteGrade option:selected').text();
            let confirmText = '';

            if (scope === 'all_classes') {
                confirmText = `删除${gradeName}全部班级的学生`;
            } else {
                if (!classId) {
                    $('#confirmText').text('');
                    $('#confirmDeleteBtn').prop('disabled', true);
                    return;
                }
                const className = $('#deleteClass option:selected').text();
                confirmText = `删除${gradeName}${className}的学生`;
            }

            $('#confirmText').text(confirmText);
            $('#confirmDeleteBtn').prop('disabled', true);
            $('#deleteConfirmInput').val('');
        }

        // 确认批量删除
        function confirmBatchDelete() {
            const gradeId = $('#deleteGrade').val();
            const classId = $('#deleteClass').val();
            const scope = $('#deleteScope').val();
            const confirmText = $('#deleteConfirmInput').val();
            const expectedText = $('#confirmText').text();

            if (!gradeId) {
                showAlert('请选择年级');
                return;
            }

            if (scope === 'class' && !classId) {
                showAlert('请选择班级');
                return;
            }

            if (!confirmText || confirmText !== expectedText) {
                showAlert('请正确输入确认文本');
                return;
            }

            // 先关闭模态框，避免重叠
            const batchDeleteModal = bootstrap.Modal.getInstance(document.getElementById('batchDeleteModal'));
            batchDeleteModal.hide();
            
            // 确保模态框和背景遮罩都被完全移除
            setTimeout(() => {
                $('.modal-backdrop').remove();
                $('body').removeClass('modal-open').css('overflow', '');
                $('body').css('padding-right', '');
                
                // 显示加载提示
                Swal.fire({
                    title: '正在删除',
                    text: '请稍候...',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });
                
                $.ajax({
                    url: '../api/index.php?route=student/batchDelete',
                    method: 'POST',
                    data: {
                        grade_id: gradeId,
                        class_id: classId,
                        scope: scope
                    },
                    success: function(response) {
                        if (response.success) {
                            Swal.fire({
                                title: '删除成功',
                                text: response.message || '学生信息已成功删除',
                                icon: 'success',
                                timer: 1500,
                                showConfirmButton: false
                            }).then(() => {
                                loadStudents();
                            });
                        } else {
                            Swal.fire({
                                title: '删除失败',
                                text: response.error || '删除失败',
                                icon: 'error'
                            });
                        }
                    },
                    error: function(xhr) {
                        console.error('批量删除请求失败:', xhr);
                        
                        // 处理不同的HTTP状态码
                        if (xhr.status === 401) {
                            // 登录已过期
                            Swal.fire({
                                title: '登录已过期',
                                text: '请重新登录后再试',
                                icon: 'warning'
                            }).then(() => {
                                // 重定向到登录页面
                                window.location.href = '../login.php';
                            });
                        } else if (xhr.status === 403) {
                            // 权限不足
                            Swal.fire({
                                title: '权限不足',
                                text: '您没有执行此操作的权限',
                                icon: 'warning'
                            });
                        } else {
                            // 其他错误
                            let errorMessage = '操作失败';
                            try {
                                if (xhr.responseJSON && xhr.responseJSON.error) {
                                    errorMessage = xhr.responseJSON.error;
                                }
                            } catch (e) {
                                console.error('解析错误响应失败', e);
                            }
                            
                            Swal.fire({
                                title: '删除失败',
                                text: errorMessage,
                                icon: 'error'
                            });
                        }
                    }
                });
            }, 300);
        }

        // 更新学生信息
        function updateStudent() {
            const id = $('#editStudentId').val();
            const name = $('#editStudentName').val().trim();
            
            if (!name) {
                showAlert('请输入学生姓名');
                return;
            }

            // 显示加载提示
            Swal.fire({
                title: '正在保存',
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

            // 更新学生姓名
            $.ajax({
                url: '../api/index.php?route=student/update_name',
                method: 'POST',
                data: {
                    id: id,
                    student_name: name
                },
                success: function(response) {
                    if (response.success) {
                        const editModal = bootstrap.Modal.getInstance(document.getElementById('editStudentModal'));
                        editModal.hide();
                        
                        Swal.fire({
                            title: '保存成功',
                            text: '学生姓名已更新',
                            icon: 'success',
                            timer: 1500,
                            showConfirmButton: false,
                            customClass: {
                                popup: 'swal2-small'
                            }
                        }).then(() => {
                            loadStudents();
                        });
                    } else {
                        Swal.fire({
                            title: '保存失败',
                            text: response.error || '更新失败',
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
                    console.error('更新失败:', {
                        status: xhr.status,
                        statusText: xhr.statusText,
                        response: xhr.responseText
                    });
                    Swal.fire({
                        title: '保存失败',
                        text: xhr.responseJSON?.error || '未知错误',
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

        // 显示重新排序模态框
        function showReorderModal() {
            const classId = $('#classFilter').val();
            const gradeId = $('#gradeFilter').val();
            
            if (!classId) {
                showAlert('请先选择班级', 'warning');
                return;
            }

            // 显示加载提示
            Swal.fire({
                title: '加载学生数据',
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

            // 获取当前班级的学生列表
            $.ajax({
                url: '../api/index.php?route=student/students',
                method: 'GET',
                data: { 
                    class_id: classId,
                    grade_id: gradeId
                },
                dataType: 'json',
                success: function(response) {
                    Swal.close();
                    
                    if (response.success && response.data && response.data.length > 0) {
                        // 设置班级信息
                        const gradeName = $('#gradeFilter option:selected').text();
                        const className = $('#classFilter option:selected').text();
                        $('#reorderClassInfo').text(`（${gradeName} ${className}）`);
                        
                        // 按照学号排序
                        response.data.sort((a, b) => {
                            return a.student_number - b.student_number;
                        });
                        
                        // 渲染学生网格
                        renderStudentsGrid(response.data);
                        
                        // 调整模态框宽度
                        adjustModalWidth(response.data.length);
                        
                        // 显示模态框
                        const reorderModal = new bootstrap.Modal(document.getElementById('reorderModal'));
                        reorderModal.show();
                    } else {
                        showAlert('当前班级没有学生数据', 'warning');
                    }
                },
                error: function(xhr) {
                    Swal.close();
                    showAlert('加载学生数据失败：' + (xhr.responseJSON?.error || '未知错误'), 'error');
                }
            });
        }
        
        // 调整模态框宽度
        function adjustModalWidth(studentCount) {
            // 每列最多显示10名学生
            const studentsPerColumn = 10;
            const totalColumns = Math.ceil(studentCount / studentsPerColumn);
            
            // 计算所需宽度，每列宽度约为280px，再加上一些页边距
            const columnWidth = 280; // 每列宽度
            const marginWidth = 30;  // 额外边距
            const minWidth = 600;    // 最小宽度
            const maxWidthPercent = 95; // 最大宽度百分比
            
            // 计算合适的宽度（像素）
            let calculatedWidth = (columnWidth * totalColumns) + marginWidth;
            calculatedWidth = Math.max(calculatedWidth, minWidth); // 不小于最小宽度
            
            // 计算这个宽度相对于窗口的百分比
            const windowWidth = window.innerWidth;
            let widthPercent = (calculatedWidth / windowWidth) * 100;
            
            // 确保不超过最大宽度百分比
            widthPercent = Math.min(widthPercent, maxWidthPercent);
            
            // 设置模态框宽度
            $('#reorderModalDialog').css('max-width', widthPercent + '%');
            $('#reorderModalDialog').css('width', widthPercent + '%');
        }

        // 渲染学生网格
        function renderStudentsGrid(students) {
            const gridContainer = $('#studentsGrid');
            gridContainer.empty();
            
            // 每列最多显示10名学生
            const studentsPerColumn = 10;
            const totalColumns = Math.ceil(students.length / studentsPerColumn);
            
            // 创建列容器
            const columnContainer = $('<div class="student-column-wrapper"></div>');
            gridContainer.append(columnContainer);
            
            // 为每一列创建一个列表
            for (let col = 0; col < totalColumns; col++) {
                const columnDiv = $(`<div class="student-column"></div>`);
                const studentNumberList = $(`<div class="student-number-list" data-column="${col}"></div>`);
                const studentNameList = $(`<div class="student-name-list" data-column="${col}"></div>`);
                
                // 添加学生到当前列
                const startIndex = col * studentsPerColumn;
                const endIndex = Math.min(startIndex + studentsPerColumn, students.length);
                
                for (let i = startIndex; i < endIndex; i++) {
                    const student = students[i];
                    
                    // 创建固定的学号区域
                    studentNumberList.append(`
                        <div class="student-number-item" data-index="${i - startIndex}">
                            <div class="student-number">${student.student_number}</div>
                        </div>
                    `);
                    
                    // 创建可拖动的学生姓名区域
                    studentNameList.append(`
                        <div class="student-name-item" data-id="${student.id}" data-number="${student.student_number}" data-original-index="${i}">
                            <div class="student-name">${student.student_name}</div>
                        </div>
                    `);
                }
                
                // 创建一个包含学号列表和姓名列表的布局
                const layoutDiv = $('<div class="students-layout"></div>');
                layoutDiv.append(studentNumberList);
                layoutDiv.append(studentNameList);
                
                columnDiv.append(layoutDiv);
                columnContainer.append(columnDiv);
                
                // 初始化拖拽排序（只对姓名列表开启拖拽功能）
                new Sortable(studentNameList[0], {
                    group: 'students',
                    animation: 150,
                    ghostClass: 'student-ghost',
                    chosenClass: 'student-chosen',
                    onEnd: function(evt) {
                        markChangedStudents();
                    }
                });
            }
        }

        // 标记已更改的学生
        function markChangedStudents() {
            // 获取所有学生姓名项
            const studentNameItems = $('.student-name-item');
            
            // 重置所有学生姓名项的样式
            studentNameItems.each(function() {
                $(this).find('.student-name').css('background-color', '');
            });
            
            // 检查并标记位置发生变化的学生
            studentNameItems.each(function() {
                const originalIndex = parseInt($(this).data('original-index'));
                const currentIndex = $(this).index() + (parseInt($(this).closest('.student-name-list').data('column')) * 10);
                
                if (originalIndex !== currentIndex) {
                    $(this).find('.student-name').css('background-color', '#ffcccc');
                }
            });
        }

        // 保存重新排序后的学生
        function saveReorderedStudents() {
            const classId = $('#classFilter').val();
            const gradeId = $('#gradeFilter').val();
            
            if (!classId) {
                showAlert('无法获取班级信息', 'error');
                return;
            }
            
            // 获取所有学生的新排序
            const students = [];
            $('.student-name-list').each(function(columnIndex) {
                // 获取当前列中所有学生姓名项
                $(this).find('.student-name-item').each(function(rowIndex) {
                    const studentId = $(this).data('id');
                    const studentNumber = $(this).data('number');
                    const newIndex = columnIndex * 10 + rowIndex + 1; // 从1开始的新序号
                    
                    students.push({
                        id: studentId,
                        current_number: studentNumber,
                        new_index: newIndex
                    });
                });
            });
            
            // 显示加载提示
            Swal.fire({
                title: '保存排序',
                text: '正在保存学生排序，请稍候...',
                allowOutsideClick: false,
                showConfirmButton: false,
                didOpen: () => {
                    Swal.showLoading();
                },
                customClass: {
                    popup: 'swal2-small'
                }
            });
            
            // 发送到服务器保存
            $.ajax({
                url: '../api/index.php?route=student/reorder',
                method: 'POST',
                data: {
                    class_id: classId,
                    grade_id: gradeId,
                    students: JSON.stringify(students)
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        // 关闭模态框
                        const reorderModal = bootstrap.Modal.getInstance(document.getElementById('reorderModal'));
                        reorderModal.hide();
                        
                        // 显示成功提示
                        Swal.fire({
                            title: '排序成功',
                            text: response.message || '学生排序已成功保存',
                            icon: 'success',
                            timer: 1500,
                            showConfirmButton: false,
                            customClass: {
                                popup: 'swal2-small'
                            }
                        }).then(() => {
                            // 重新加载学生列表
                            loadStudents();
                        });
                    } else {
                        Swal.fire({
                            title: '排序失败',
                            text: response.error || '保存排序失败',
                            icon: 'error',
                            customClass: {
                                popup: 'swal2-small'
                            }
                        });
                    }
                },
                error: function(xhr) {
                    Swal.fire({
                        title: '排序失败',
                        text: xhr.responseJSON?.error || '未知错误',
                        icon: 'error',
                        customClass: {
                            popup: 'swal2-small'
                        }
                    });
                }
            });
        }
    </script>
</body>
</html> 