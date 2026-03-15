<!--
/**
 * 文件名: modules/project_settings.php
 * 功能描述: 项目管理设置模块
 * 
 * 该文件负责:
 * 1. 提供项目管理的用户界面
 * 2. 支持项目的增删改查
 * 3. 项目状态切换（启用/禁用）
 * 4. 从现有项目同步数据
 * 5. 项目删除确认和安全验证
 * 
 * 项目在系统中是最上层的数据组织单位，包含学校名称、学期信息和项目名称。
 * 每个项目下可以创建多个年级、班级、学科和学生，
 * 系统同一时间只允许一个项目处于启用状态。
 * 
 * 关联文件:
 * - controllers/ProjectController.php: 项目控制器
 * - api/index.php: API入口
 * - assets/js/project-settings.js: 项目管理前端脚本
 * - assets/js/settings.js: 通用设置脚本
 */
-->
<!-- 项目管理 -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0 fw-bold">
            <i class="fas fa-tasks text-primary me-2"></i>项目管理
        </h5>
        <button type="button" class="btn btn-primary" id="addProjectBtn">
            <i class="fas fa-plus-circle me-1"></i> 添加项目
        </button>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead class="thead-light">
                    <tr>
                        <th>学校名称</th>
                        <th>当前学期</th>
                        <th>项目名称</th>
                        <th>状态</th>
                        <th>创建时间</th>
                        <th class="operations-column text-center">操作</th>
                    </tr>
                </thead>
                <tbody id="projectList">
                    <tr class="loading-row">
                        <td colspan="6" class="text-center">
                            <div class="d-flex justify-content-center align-items-center py-3">
                                <div class="spinner-border text-primary me-2" role="status">
                                    <span class="visually-hidden">加载中...</span>
                                </div>
                                <span>加载中...</span>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- 添加项目模态框 -->
<div class="modal fade" id="addProjectModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">添加项目</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="addProjectForm">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">学校名称</label>
                        <input type="text" class="form-control" name="school_name" required maxlength="100">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">当前学期</label>
                        <input type="text" class="form-control" name="current_semester" required maxlength="50">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">项目名称</label>
                        <input type="text" class="form-control" name="project_name" required maxlength="100">
                    </div>
                    <div class="mb-3">
                        <div class="clickable-option-wrapper">
                            <div class="clickable-option checkbox">
                                <input type="checkbox" class="form-check-input" id="syncDataCheck" name="sync_data">
                                <label class="form-check-label" for="syncDataCheck">同步现有年级、班级和学生数据</label>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3 sync-source-container" style="display: none;">
                        <label class="form-label">选择要同步的项目</label>
                        <div class="custom-select-wrapper">
                            <div class="custom-select-trigger">请选择项目...</div>
                            <div class="custom-options">
                                <!-- 选项将通过JavaScript动态添加 -->
                            </div>
                        </div>
                        <select class="form-select" name="source_project_id" id="sourceProjectSelect">
                            <option value="">请选择项目...</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
                    <button type="submit" class="btn btn-primary submit-btn">确定</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- 编辑项目模态框 -->
<div class="modal fade" id="editProjectModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">编辑项目</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="editProjectForm">
                <input type="hidden" id="editProjectId">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="editSchoolName" class="form-label">学校名称</label>
                        <input type="text" class="form-control" id="editSchoolName" maxlength="100" placeholder="请输入学校名称">
                    </div>
                    <div class="mb-3">
                        <label for="editCurrentSemester" class="form-label">当前学期 <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="editCurrentSemester" maxlength="50" required placeholder="例如：2023-2024学年第二学期">
                    </div>
                    <div class="mb-3">
                        <label for="editProjectName" class="form-label">项目名称 <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="editProjectName" maxlength="100" required placeholder="请输入项目名称">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
                    <button type="submit" class="btn btn-primary">保存</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- 删除确认模态框 -->
<div class="modal fade" id="confirmDeleteModal" tabindex="-1" aria-labelledby="confirmDeleteModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="confirmDeleteModalLabel">确认删除</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>
                    <i class="fas fa-exclamation-triangle text-danger me-2"></i>
                    此操作将删除项目及其所有相关数据，包括：
                </p>
                <ul>
                    <li class="text-danger">所有学科设置</li>
                    <li class="text-danger">所有学科的年级关联</li>
                    <li class="text-danger">所有相关的成绩数据</li>
                </ul>
                <p>此操作不可恢复！请输入"<span class="text-danger">确认删除</span>"以继续：</p>
                <input type="text" 
                       class="form-control" 
                       id="confirmDeleteInput" 
                       placeholder="请输入：确认删除"
                       autocomplete="off">
                <div class="mt-3">
                    <div class="progress" style="display: none;">
                        <div class="progress-bar progress-bar-striped progress-bar-animated" 
                             role="progressbar" style="width: 0%" id="deleteProgress">
                        </div>
                    </div>
                    <p class="text-muted small mb-0 mt-2" id="progressText"></p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
                <button type="button" class="btn btn-danger" id="executeDeleteBtn">确认删除</button>
            </div>
        </div>
    </div>
</div>

<!-- 切换状态确认模态框 -->
<div class="modal fade" id="projectToggleStatusModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">确认操作</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="mb-0">
                    <i class="fas fa-info-circle me-2 text-primary"></i>
                    启用此项目将会自动停用其他项目，确定要继续吗？
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
                <button type="button" class="btn btn-primary" id="projectConfirmToggleBtn">确定</button>
            </div>
        </div>
    </div>
</div>

<!-- 全局提示模态框 -->
<div class="modal fade" id="alertModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 360px;">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 16px; overflow: hidden;">
            <div class="modal-body p-0">
                <div class="text-center p-4">
                    <!-- 图标区域 -->
                    <div class="mb-4 mx-auto" style="width: 48px; height: 48px;">
                        <i id="alertModalIcon" class="fas fa-check-circle" style="font-size: 48px;"></i>
                    </div>
                    <!-- 标题和内容 -->
                    <h5 id="alertModalTitle" class="mb-3" style="font-weight: 600;"></h5>
                    <p id="alertModalContent" class="mb-4 text-secondary"></p>

                </div>
            </div>
        </div>
    </div>
</div>

<!-- CSS 依赖 -->
<link href="../assets/css/bootstrap.min.css" rel="stylesheet">
<link href="../assets/css/sweetalert2.min.css" rel="stylesheet">
<link href="../assets/css/all.min.css" rel="stylesheet">
<link href="../assets/css/common.css" rel="stylesheet">

<!-- JavaScript 依赖 -->
<script src="../assets/js/jquery.min.js"></script>
<script src="../assets/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/sweetalert2.all.min.js"></script>

<!-- 引入项目管理相关的JavaScript -->
<script>
// 检查jQuery是否已加载
if (typeof $ === 'undefined') {
    console.error('jQuery未加载，项目列表将无法加载');
}

// 定义全局错误处理函数
window.showAlert = function(message, type = 'error', title = '') {
    // 添加防抖机制，避免重复显示相同的提示
    if (window.lastAlertInfo && 
        window.lastAlertInfo.message === message && 
        window.lastAlertInfo.type === type &&
        Date.now() - window.lastAlertInfo.timestamp < 2000) {
        //console.log('已存在相同提示，忽略:', message);
        return;
    }
    
    // 记录当前提示信息
    window.lastAlertInfo = {
        message: message,
        type: type,
        timestamp: Date.now()
    };
    
    Swal.fire({
        title: title || (type === 'success' ? '成功' : '错误'),
        text: message,
        icon: type,
        timer: type === 'success' ? 2000 : undefined,
        timerProgressBar: type === 'success',
        showConfirmButton: type !== 'success',
        confirmButtonColor: '#dc3545',
        confirmButtonText: '确定'
    });
};

// 显示确认对话框
window.showConfirm = function(message, callback) {
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
};
</script>
<script src="assets/js/project-settings.js"></script>
<script>
    // 项目设置页面初始化函数
    function initProjectSettings() {
        //console.log('正在初始化项目管理页面...');
        
        // 确保ProjectSettings模块已加载
        if (typeof ProjectSettings === 'undefined') {
            console.error('ProjectSettings模块未加载');
            window.showAlert('项目管理模块加载失败，请刷新页面重试');
            return;
        }

        // 确保表格存在
        if ($('#projectList').length === 0) {
            console.error('项目列表表格不存在');
            window.showAlert('页面元素加载失败，请刷新页面重试');
            return;
        }

        try {
            // 清除任何遗留模态框背景
            $('.modal-backdrop').remove();
            
            // 如果已经初始化过，先销毁之前的实例
            if (window.projectSettingsInstance) {
                //console.log('销毁之前的ProjectSettings实例...');
                window.projectSettingsInstance.destroy();
                window.projectSettingsInstance = null;
            }

            // 移除之前的projectChanged事件处理程序，避免重复绑定
            $(document).off('projectChanged.global');
            
            // 只绑定一次全局项目变更事件监听
            $(document).on('projectChanged.global', function(e, projectId, projectName) {
                //console.log('全局项目变更事件:', projectId, projectName);
                // 这里不需要重复显示提示，因为toggleProjectStatus函数中已经有提示
            });

            // 创建新实例并保存
           // console.log('创建新的ProjectSettings实例...');
            window.projectSettingsInstance = ProjectSettings.init();
            
            //console.log('项目管理页面初始化完成');
        } catch (error) {
            console.error('初始化ProjectSettings失败:', error);
            window.showAlert('初始化失败：' + error.message);
        }
    }

    // 页面加载完成后初始化
    $(document).ready(function() {
        // 初始化项目设置
        initProjectSettings();
    });
</script>
<style>
    /* 表格样式美化 */
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

    .table td {
        padding: 1rem;
        vertical-align: middle;
        border-bottom: 1px solid #e9ecef;
        color: #2c3e50;
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

    .card-header {
        background: #fff;
        border-bottom: 1px solid rgba(0,0,0,0.05);
        padding: 1.25rem;
    }

    .card-body {
        padding: 1.25rem;
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

    .clickable-option input[type="checkbox"] {
        position: absolute;
        left: 1rem;
        margin: 0;
    }

    /* 自定义下拉框样式 */
    .custom-select-wrapper {
        position: relative;
        width: 100%;
        margin-bottom: 0.75rem;
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
        transition: all 0.3s ease;
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
        animation: fadeInDown 0.3s ease;
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

    /* 动画效果 */
    @keyframes fadeInDown {
        from {
            opacity: 0;
            transform: translateY(-10px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    @keyframes fadeOut {
        from {
            opacity: 1;
        }
        to {
            opacity: 0;
        }
    }

    /* 表单验证样式 */
    .custom-select-wrapper.invalid .custom-select-trigger {
        border-color: #dc3545;
        background-color: #fff8f8;
    }

    /* 确保模态框内的输入框始终可用 */
    #confirmDeleteModal {
        z-index: 1050 !important;
    }

    #confirmDeleteModal .modal-dialog {
        z-index: 1051 !important;
    }

    #confirmDeleteModal .modal-content {
        background: #fff;
        border: none;
        border-radius: 12px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
    }

    #confirmDeleteInput {
        background: #fff !important;
        opacity: 1 !important;
        border: 1px solid #ced4da;
        padding: 0.5rem 0.75rem;
        margin-bottom: 1rem;
        width: 100%;
        transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
    }

    #confirmDeleteInput:focus {
        border-color: #86b7fe;
        outline: 0;
        box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
    }

    /* 进度条容器样式 */
    .progress {
        height: 0.5rem;
        border-radius: 0.25rem;
        background-color: #e9ecef;
        margin: 1rem 0;
        overflow: hidden;
    }

    .modal-backdrop {
        z-index: 1040 !important;
    }

    /* 表格内容溢出处理 */
    .table td {
        max-width: 200px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    /* 除操作列外的其他列自适应宽度 */
    .table th:not(.operations-column),
    .table td:not(:last-child) {
        width: auto;
    }

    /* 加载动画样式 */
    .spinner-border-sm {
        width: 1rem;
        height: 1rem;
        border-width: 0.15em;
    }

    /* 按钮加载状态样式 */
    .btn:disabled {
        cursor: not-allowed;
        opacity: 0.8;
    }

    .btn:disabled .spinner-border {
        vertical-align: middle;
        margin-top: -2px;
    }

    /* 项目特定的自定义样式 */
    .sync-source-container {
        display: none;
    }

    .clickable-option-wrapper {
        margin-bottom: 10px;
    }
</style> 