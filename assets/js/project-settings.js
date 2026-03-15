/**
 * 文件名: assets/js/project-settings.js
 * 功能描述: 项目设置管理模块
 * 
 * 该模块负责:
 * 1. 项目数据的增删改查操作
 * 2. 项目状态切换（启用/禁用）
 * 3. 数据同步功能管理
 * 4. 项目列表渲染与交互
 * 
 * API调用说明:
 * - GET ./api/index.php?route=settings/project/list: 获取项目列表
 * - GET ./api/index.php?route=settings/project/get: 获取单个项目详情
 * - POST ./api/index.php?route=settings/project/add: 添加新项目
 * - POST ./api/index.php?route=settings/project/update: 更新项目信息
 * - POST ./api/index.php?route=settings/project/delete: 删除项目
 * - POST ./api/index.php?route=settings/project/toggle_status: 切换项目状态
 * 
 * 关联文件:
 * - modules/project_settings.php: 项目设置页面
 * - api/controllers/ProjectController.php: 后端项目控制器
 * - assets/js/settings.js: 通用设置函数库
 */

const ProjectSettings = {
    // API基础路径
    baseUrl: './api/index.php?route=settings/project/',
    isLoading: false,
    
    // 状态管理
    state: {
        isSubmitting: false,
        initialized: false,
        modalInstances: {} // 存储模态框实例
    },

    /**
     * 销毁实例
     */
    destroy: function() {
        console.log('开始销毁ProjectSettings实例...');
        
        try {
            // 移除所有相关的事件绑定（使用命名空间）
            $(document).off('.projectSettings');
            $(document).off('click.projectSettings');
            $(document).off('input.projectSettings');
            $(document).off('change.projectSettings');
            $(document).off('submit.projectSettings');
            $(document).off('focusin.projectSettings');
            $(document).off('focusout.projectSettings');
            
            // 解绑单独绑定的事件
            $('#addProjectForm').off();
            $('#editProjectForm').off();
            $('#syncDataCheck').off();
            $('.clickable-option').off();
            $('#confirmDeleteModal').off();
            $('#confirmDeleteInput').off();
            $('#executeDeleteBtn').off();
            
            // 确保清理掉所有模态框实例
            this.disposeAllModals();
            
            // 重置状态
            this.state.initialized = false;
            this.isLoading = false;
            this.state.modalInstances = {};
            
            console.log('ProjectSettings实例已完全销毁');
        } catch (error) {
            console.error('销毁实例时出错:', error);
        }
    },
    
    /**
     * 销毁所有Bootstrap模态框实例
     */
    disposeAllModals: function() {
        try {
            // 销毁所有可能存在的模态框实例
            const modalIds = ['addProjectModal', 'editProjectModal', 'confirmDeleteModal', 'projectToggleStatusModal'];
            
            modalIds.forEach(id => {
                try {
                    const modalElement = document.getElementById(id);
                    if (modalElement) {
                        // 先尝试获取已存在的实例
                        let modalInstance = bootstrap.Modal.getInstance(modalElement);
                        
                        // 如果实例存在，销毁它
                        if (modalInstance) {
                            // 先隐藏模态框
                            modalInstance.hide();
                            // 然后销毁实例
                            modalInstance.dispose();
                            console.log(`销毁模态框实例: ${id}`);
                        }
                        
                        // 移除所有相关事件监听器
                        $(modalElement).off();
                        
                        // 确保清除任何焦点相关问题
                        $(modalElement).find('button, input, select, textarea').off();
                    }
                } catch (modalError) {
                    console.warn(`销毁模态框时出错 ${id}:`, modalError);
                }
            });
            
            // 清除任何后台残留的模态框元素
            $('.modal-backdrop').remove();
        } catch (error) {
            console.error('销毁模态框实例时出错:', error);
        }
    },

    /**
     * 初始化
     */
    init: function() {
        const self = this;
        
        // 确保只初始化一次
        if (this.state.initialized) {
            console.log('ProjectSettings已初始化，跳过');
            return this;
        }

        console.log('正在初始化ProjectSettings...');
        
        // 确保依赖已加载
        if (typeof $ === 'undefined') {
            console.error('jQuery未加载!');
            return this;
        }
        
        if (typeof bootstrap === 'undefined') {
            console.error('Bootstrap未加载!');
            return this;
        }

        try {
            // 销毁任何可能存在的旧模态框实例
            this.disposeAllModals();
            
            this.state.initialized = true;
            this.bindEvents();
            this.loadProjects();
            
            // 初始化项目同步相关功能
            this.initProjectSync();
            
            // 初始化可点击选项
            this.initClickableOptions();
            
            console.log('ProjectSettings初始化完成');
        } catch (error) {
            console.error('初始化ProjectSettings时出错:', error);
        }

        return this;
    },

    /**
     * 绑定事件
     */
    bindEvents: function() {
        // 保存 this 的引用
        const self = this;
        
        // 获取常用元素引用
        const $modal = $('#addProjectModal');
        const $form = $('#addProjectForm');
        const $submitBtn = $form.find('.submit-btn');
        
        // 先解绑所有事件，防止重复绑定
        $(document).off('click.projectSettings');
        $form.off();
        $submitBtn.off();

        // 添加项目按钮点击事件 - 使用事件委托
        $(document).on('click.projectSettings', '#addProjectBtn', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            // 先确保没有其他模态框打开
            self.disposeAllModals();
            
            // 重置表单
            if ($form.length) {
                $form[0].reset();
                self.clearFormValidation();
            }
            
            // 隐藏同步容器
            $('.sync-source-container').hide();
            
            // 使用bootstrap创建新的模态框实例并显示
            try {
                const modalElement = document.getElementById('addProjectModal');
                if (modalElement) {
                    const modalInstance = new bootstrap.Modal(modalElement, {
                        backdrop: true,
                        keyboard: true,
                        focus: true
                    });
                    
                    // 存储实例引用
                    self.state.modalInstances.addProject = modalInstance;
                    
                    // 显示模态框
                    modalInstance.show();

                    // 在模态框显示后重新绑定同步数据复选框的事件
                    $(modalElement).one('shown.bs.modal', function() {
                        // 确保同步数据复选框事件被正确绑定
                        self.initProjectSync();
                    });
                    
                    console.log('添加项目模态框已显示');
                }
            } catch (modalError) {
                console.error('创建/显示模态框时出错:', modalError);
            }
        });

        // 处理表单提交
        const handleSubmit = function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            // 防止重复提交
            if (self.state.isSubmitting) {
                console.log('已在提交中，忽略此次提交');
                return;
            }
            
            // 表单验证
            if (!$form[0].checkValidity()) {
                console.log('表单验证失败');
                $form[0].reportValidity();
                return;
            }
            
            // 设置提交状态
            self.state.isSubmitting = true;
            
            // 更新按钮状态
            $submitBtn.prop('disabled', true)
                     .html('<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>创建中...');
            
            // 获取表单数据
            const data = {
                school_name: $('input[name="school_name"]').val()?.trim() || '',
                current_semester: $('input[name="current_semester"]').val()?.trim() || '',
                project_name: $('input[name="project_name"]').val()?.trim() || '',
                sync_data: $('#syncDataCheck').prop('checked'),
                source_project_id: ''
            };

            // 如果选择了同步数据，获取源项目ID
            if (data.sync_data) {
                data.source_project_id = $('#sourceProjectSelect').val() || '';
            }

            // 发送添加请求
            $.ajax({
                url: self.baseUrl + 'add',
                type: 'POST',
                data: data,
                success: (res) => {
                    console.log('=== 添加项目响应 ===');
                    console.log('完整响应:', res);
                    console.log('成功状态:', res.success);
                    console.log('返回数据:', res.data);
                    console.log('==================');
                    
                    if (res.success) {
                        self.closeModal('addProjectModal', () => {
                            self.showAlert(res.message || '添加成功', 'success');
                            console.log('准备重新加载项目列表...');
                            self.loadProjects();
                        });
                    } else {
                        // 显示错误信息
                        self.showAlert(res.error || '添加失败');
                        // 如果是重复数据错误，高亮相关字段
                        if (res.error && res.error.includes('已存在相同项目名称')) {
                            $('input[name="project_name"]').addClass('is-invalid');
                            $('input[name="current_semester"]').addClass('is-invalid');
                        }
                    }
                },
                error: (xhr) => {
                    let errorMsg = '添加失败';
                    try {
                        const response = JSON.parse(xhr.responseText);
                        if (response.error && response.error.includes('已存在相同项目名称')) {
                            $('input[name="project_name"]').addClass('is-invalid');
                            $('input[name="current_semester"]').addClass('is-invalid');
                            errorMsg = response.error;
                        } else {
                            errorMsg = response.error || response.message || errorMsg;
                        }
                    } catch(e) {
                        console.error('解析错误响应失败:', e);
                    }
                    self.showAlert(errorMsg);
                },
                complete: () => {
                    // 重置提交状态
                    self.state.isSubmitting = false;
                    // 恢复按钮状态
                    $submitBtn.prop('disabled', false)
                            .html('确定');
                }
            });
        };

        // 绑定表单提交事件
        $form.on('submit', handleSubmit);
        
        // 绑定确定按钮点击事件
        $submitBtn.on('click', function(e) {
            e.preventDefault();
            handleSubmit(e);
        });

        // 监听输入事件，清除错误状态
        $(document).on('input.projectSettings', 'input[name="project_name"], input[name="current_semester"]', function() {
            $(this).removeClass('is-invalid');
        });

        // 编辑项目表单提交
        $('#editProjectForm').on('submit', (e) => {
            e.preventDefault();
            e.stopPropagation();
            this.updateProject();
        });

        // 编辑按钮点击事件
        $(document).on('click.projectSettings', '.edit-project', (e) => {
            e.preventDefault();
            e.stopPropagation();
            const projectId = $(e.currentTarget).data('project-id');
            this.showEditModal(projectId);
        });

        // 删除按钮点击事件
        $(document).on('click.projectSettings', '.delete-project', (e) => {
            e.preventDefault();
            e.stopPropagation();
            const projectId = $(e.currentTarget).data('project-id');
            this.deleteProject(projectId);
        });

        // 切换状态按钮点击事件
        $(document).on('click.projectSettings', '.project-toggle-status', (e) => {
            e.preventDefault();
            e.stopPropagation();
            const projectId = $(e.currentTarget).data('project-id');
            const currentStatus = parseInt($(e.currentTarget).data('current-status'));
            this.toggleProjectStatus(projectId, currentStatus);
        });
        
        // 模态框关闭事件，确保清理资源
        $(document).on('hidden.bs.modal', '.modal', function() {
            // 模态框关闭时移除类
            $(this).removeClass('closing');
            // 解决焦点管理问题
            $(document).off('focusin.modal');
        });
    },

    /**
     * 初始化可点击选项
     */
    initClickableOptions: function() {
        try {
            // 包装复选框选项
            $('.form-check').each(function() {
                const $formCheck = $(this);
                if (!$formCheck.parent().hasClass('clickable-option-wrapper')) {
                    const $input = $formCheck.find('input');
                    const $label = $formCheck.find('label');
                    
                    if ($input.length && $label.length) {
                        $formCheck.wrap('<div class="clickable-option-wrapper"></div>');
                        
                        const $wrapper = $('<div></div>')
                            .addClass('clickable-option')
                            .addClass('checkbox')
                            .append($input.clone())
                            .append($label.text());
                        
                        $formCheck.parent().append($wrapper);
                        $formCheck.remove();
                    }
                }
            });

            // 绑定点击事件
            $('.clickable-option').off('click').on('click', function() {
                const $option = $(this);
                const $input = $option.find('input');
                const isChecked = $input.prop('checked');
                
                $option.toggleClass('active');
                $input.prop('checked', !isChecked);
                
                // 触发change事件
                $input.trigger('change');
            });

            // 初始化默认选中状态
            $('.clickable-option input:checked').each(function() {
                $(this).closest('.clickable-option').addClass('active');
            });
        } catch (error) {
            console.log('初始化选项出错:', error);
        }
    },

    /**
     * 初始化项目同步相关功能
     */
    initProjectSync: function() {
        const self = this;
        console.log('初始化项目同步功能...');
        
        // 移除旧事件绑定
        $('#syncDataCheck').off('change');
        
        // 处理同步数据复选框变化
        $('#syncDataCheck').on('change', function() {
            console.log('同步数据复选框状态变化:', $(this).is(':checked'));
            const syncSourceContainer = $('.sync-source-container');
            if ($(this).is(':checked')) {
                syncSourceContainer.slideDown();
                $('#sourceProjectSelect').prop('required', true);
                self.loadProjectsToSelect();
            } else {
                syncSourceContainer.slideUp();
                $('#sourceProjectSelect').prop('required', false);
            }
        });
        
        // 检查当前复选框状态并触发相应的行为
        if ($('#syncDataCheck').is(':checked')) {
            $('.sync-source-container').show();
            $('#sourceProjectSelect').prop('required', true);
            self.loadProjectsToSelect();
        }
    },

    /**
     * 加载项目列表到下拉框
     */
    loadProjectsToSelect: function() {
        console.log('加载项目用于同步...');
        const self = this;
        const $select = $('#sourceProjectSelect');
        const $customOptions = $('.custom-select-wrapper .custom-options');
        const $customTrigger = $('.custom-select-wrapper .custom-select-trigger');
        
        // 显示加载中
        $customTrigger.text('加载中...');
        $customOptions.empty();
        $select.empty().append('<option value="">加载中...</option>');
        
        // 构建API URL
        const apiUrl = this.baseUrl + 'list';
        console.log('请求URL:', apiUrl);
        
        $.ajax({
            url: apiUrl,
            method: 'GET',
            dataType: 'json',
            cache: false,
            success: function(response) {
                console.log('项目加载完成:', response);
                if (response.success && response.data && Array.isArray(response.data)) {
                    $select.empty().append('<option value="">请选择项目...</option>');
                    $customOptions.empty();
                    $customTrigger.text('请选择项目...');
                    
                    if (response.data.length === 0) {
                        console.log('没有可用的项目');
                        $select.append('<option value="" disabled>没有可用的项目</option>');
                        $customTrigger.text('没有可用的项目');
                        return;
                    }
                    
                    response.data.forEach(function(project) {
                        let optionText = project.school_name || '';
                        if (project.current_semester) {
                            optionText += (optionText ? ' - ' : '') + project.current_semester;
                        }
                        if (project.project_name) {
                            optionText += (optionText ? ' - ' : '') + project.project_name;
                        }
                        optionText = optionText || '未命名项目';
                        
                        // 添加到原生select
                        $select.append($('<option>', {
                            value: project.id,
                            text: optionText
                        }));
                        
                        // 添加到自定义下拉框
                        $customOptions.append($('<div>', {
                            class: 'custom-option',
                            'data-value': project.id,
                            text: optionText
                        }));
                    });
                    
                    // 初始化自定义下拉框事件
                    self.initCustomSelect();
                    console.log('项目下拉框加载完成，共', response.data.length, '个项目');
                } else {
                    console.error('加载项目失败:', response);
                    $select.empty().append('<option value="">加载失败，请重试</option>');
                    $customTrigger.text('加载失败，请重试');
                    $customOptions.empty();
                    
                    // 显示调试信息
                    console.error('响应内容:', response);
                    if (response && !response.success) {
                        console.error('错误信息:', response.error || '未知错误');
                    }
                }
            },
            error: function(xhr, status, error) {
                console.error('Ajax错误:', error);
                console.error('状态码:', xhr.status);
                console.error('响应文本:', xhr.responseText);
                
                $select.empty().append('<option value="">加载失败，请重试</option>');
                $customTrigger.text('加载失败，请重试');
                $customOptions.empty();
                
                try {
                    const errorResponse = JSON.parse(xhr.responseText);
                    console.error('错误详情:', errorResponse);
                } catch (e) {
                    console.error('无法解析错误响应');
                }
            }
        });
    },

    /**
     * 初始化自定义下拉框
     */
    initCustomSelect: function() {
        console.log('初始化自定义下拉框...');
        const $wrapper = $('.custom-select-wrapper');
        const $trigger = $wrapper.find('.custom-select-trigger');
        const $options = $wrapper.find('.custom-options');
        const $select = $('#sourceProjectSelect');
        
        // 先解绑之前的事件
        $trigger.off('click');
        $options.find('.custom-option').off('click');
        $(document).off('click.customSelect');
        
        // 点击触发器
        $trigger.on('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            console.log('触发下拉框');
            $wrapper.toggleClass('open');
        });
        
        // 点击选项
        $options.find('.custom-option').on('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const value = $(this).data('value');
            const text = $(this).text();
            console.log('选择项目:', text, '(ID:', value, ')');
            
            $trigger.text(text);
            $options.find('.custom-option').removeClass('selected');
            $(this).addClass('selected');
            
            $select.val(value).trigger('change');
            $wrapper.removeClass('open');
        });
        
        // 点击其他地方关闭下拉框
        $(document).on('click.customSelect', function() {
            $wrapper.removeClass('open');
        });
        
        console.log('自定义下拉框初始化完成，选项数:', $options.find('.custom-option').length);
    },

    /**
     * 渲染项目列表
     */
    renderProjects: function(projects) {
        console.log('渲染项目列表:', projects.length);
        const tbody = $('#projectList');
        
        // 确保tbody存在
        if (!tbody.length) {
            console.error('项目列表表格不存在!');
            return;
        }

        // 清空现有内容
        tbody.empty();

        // 检查projects是否为有效数组
        if (!Array.isArray(projects)) {
            console.error('无效的项目数据:', projects);
            tbody.html(`
                <tr>
                    <td colspan="6" class="text-center text-danger">
                        <i class="fas fa-exclamation-circle me-2"></i>数据格式错误
                    </td>
                </tr>
            `);
            return;
        }

        // 检查是否有数据
        if (projects.length === 0) {
            console.log('项目列表为空,显示"暂无数据"提示');
            tbody.html(`
                <tr>
                    <td colspan="6" class="text-center text-muted">
                        <i class="fas fa-info-circle me-2"></i>暂无数据
                    </td>
                </tr>
            `);
            return;
        }

        console.log('开始渲染', projects.length, '个项目');
        // 渲染每个项目
        projects.forEach(project => {
            const status = parseInt(project.status);
            const tr = $('<tr>').append(
                $('<td>').text(project.school_name || '-'),
                $('<td>').text(project.current_semester),
                $('<td>').text(project.project_name),
                $('<td>').html(`
                    <span class="badge ${status === 1 ? 'bg-success' : 'bg-secondary'}">
                        ${status === 1 ? '可用' : '停用'}
                    </span>
                `),
                $('<td>').text(project.created_at),
                $('<td>').html(`
                    <button type="button" 
                            class="btn btn-sm btn-outline-primary edit-project" 
                            data-project-id="${project.id}">
                        编辑
                    </button>
                    <button type="button" 
                            class="btn btn-sm btn-outline-success project-toggle-status" 
                            data-project-id="${project.id}"
                            data-current-status="${status}">
                        切换
                    </button>
                    <button type="button" 
                            class="btn btn-sm btn-outline-danger delete-project" 
                            data-project-id="${project.id}"
                            ${status === 1 ? 'disabled' : ''}>
                        删除
                    </button>
                `)
            );
            tbody.append(tr);
        });
    },

    /**
     * 关闭模态框
     */
    closeModal: function(modalId, callback) {
        const self = this;
        try {
            const modalElement = document.getElementById(modalId);
            const modalInstance = bootstrap.Modal.getInstance(modalElement);
            
            if (modalInstance) {
                // 绑定关闭后回调
                if (typeof callback === 'function') {
                    $(modalElement).one('hidden.bs.modal', function() {
                        callback();
                    });
                }
                
                // 隐藏模态框
                modalInstance.hide();
                
                // 清理资源
                setTimeout(() => {
                    // 移除类
                    $(modalElement).removeClass('closing');
                    // 解决焦点管理问题
                    $(document).off('focusin.modal');
                }, 300);
            } else if (typeof callback === 'function') {
                // 如果没有实例但有回调，直接执行
                callback();
            }
        } catch (error) {
            console.error('关闭模态框出错:', error);
            // 如果出错但有回调，尝试执行
            if (typeof callback === 'function') {
                callback();
            }
        }
    },

    /**
     * 清除表单验证状态
     */
    clearFormValidation: function() {
        $('.is-invalid').removeClass('is-invalid');
    },

    /**
     * 显示编辑项目模态框
     */
    showEditModal: function(projectId) {
        const self = this;
        console.log('显示编辑模态框，项目ID:', projectId);
        
        try {
            // 先确保没有其他模态框打开
            this.disposeAllModals();
            
            // 发起AJAX请求获取项目详情
            $.ajax({
                url: this.baseUrl + 'get',
                type: 'GET',
                dataType: 'json',
                data: { id: projectId },
                success: (res) => {
                    console.log('获取项目详情响应:', res);
                    if (res.success && res.data) {
                        const project = res.data;
                        
                        // 填充表单字段
                        $('#editProjectId').val(project.id);
                        $('#editSchoolName').val(project.school_name || '');
                        $('#editCurrentSemester').val(project.current_semester || '');
                        $('#editProjectName').val(project.project_name || '');
                        
                        // 使用bootstrap创建新的模态框实例并显示
                        const modalElement = document.getElementById('editProjectModal');
                        if (modalElement) {
                            const modalInstance = new bootstrap.Modal(modalElement, {
                                backdrop: true,
                                keyboard: true,
                                focus: true
                            });
                            
                            // 存储实例引用
                            self.state.modalInstances.editProject = modalInstance;
                            
                            // 显示模态框
                            modalInstance.show();
                            
                            console.log('编辑项目模态框已显示');
                        }
                    } else {
                        this.showAlert(res.error || '获取项目信息失败');
                    }
                },
                error: (xhr) => {
                    console.error('获取项目详情出错:', xhr);
                    let errorMsg = '获取项目信息失败';
                    try {
                        const response = JSON.parse(xhr.responseText);
                        errorMsg = response.error || response.message || errorMsg;
                    } catch(e) {
                        console.error('解析错误响应失败:', e);
                    }
                    this.showAlert(errorMsg);
                }
            });
        } catch (error) {
            console.error('显示编辑模态框出错:', error);
            this.showAlert('显示编辑模态框出错: ' + error.message);
        }
    },

    /**
     * 更新项目
     */
    updateProject: function() {
        const self = this;
        console.log('更新项目...');
        
        try {
            const data = {
                id: $('#editProjectId').val(),
                school_name: $('#editSchoolName').val().trim(),
                current_semester: $('#editCurrentSemester').val().trim(),
                project_name: $('#editProjectName').val().trim()
            };
    
            // 验证必填字段
            if (!data.id || !data.current_semester || !data.project_name) {
                this.showAlert('请填写必填项');
                return;
            }
    
            // 验证字段长度
            if (data.school_name && data.school_name.length > 100) {
                this.showAlert('学校名称不能超过100个字符');
                return;
            }
            if (data.current_semester.length > 50) {
                this.showAlert('学期不能超过50个字符');
                return;
            }
            if (data.project_name.length > 100) {
                this.showAlert('项目名称不能超过100个字符');
                return;
            }
    
            console.log('更新项目数据:', data);
            
            // 禁用提交按钮，显示加载状态
            const $submitBtn = $('#editProjectForm button[type="submit"]');
            $submitBtn.prop('disabled', true)
                     .html('<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>保存中...');
            
            $.ajax({
                url: this.baseUrl + 'update',
                type: 'POST',
                data: data,
                success: (res) => {
                    console.log('更新项目响应:', res);
                    if (res.success) {
                        this.closeModal('editProjectModal', () => {
                            this.showAlert(res.message || '更新成功', 'success');
                            this.loadProjects();
                        });
                    } else {
                        this.showAlert(res.error || '更新失败');
                    }
                },
                error: (xhr) => {
                    console.error('更新项目失败:', xhr.responseText);
                    let errorMsg = '更新失败';
                    try {
                        const response = JSON.parse(xhr.responseText);
                        errorMsg = response.error || response.message || errorMsg;
                    } catch(e) {
                        console.error('解析错误响应失败:', e);
                    }
                    this.showAlert(errorMsg);
                },
                complete: () => {
                    // 恢复按钮状态
                    $submitBtn.prop('disabled', false).html('保存');
                }
            });
        } catch (error) {
            console.error('更新项目出错:', error);
            this.showAlert('更新项目出错: ' + error.message);
            
            // 恢复按钮状态
            $('#editProjectForm button[type="submit"]').prop('disabled', false).html('保存');
        }
    },

    /**
     * 删除项目及其关联数据
     */
    deleteProject: function(projectId) {
        const self = this;
        console.log('准备删除项目, ID:', projectId);
        
        try {
            // 先确保没有其他模态框打开
            this.disposeAllModals();
            
            // 创建删除确认模态框实例
            const modalElement = document.getElementById('confirmDeleteModal');
            if (modalElement) {
                // 清理旧事件
                $(modalElement).off();
                $('#confirmDeleteInput').off();
                $('#executeDeleteBtn').off();
                
                // 初始化输入框和按钮状态
                const $input = $('#confirmDeleteInput');
                const $deleteBtn = $('#executeDeleteBtn');
                const $progress = $('#confirmDeleteModal .progress');
                const $progressBar = $('#deleteProgress');
                const $progressText = $('#progressText');
                
                $input.val('').prop('disabled', false);
                $deleteBtn.prop('disabled', true);
                $progress.hide();
                $progressBar.css('width', '0%');
                $progressText.text('');
                
                // 绑定输入事件
                $input.on('input', function() {
                    const confirmText = "确认删除";
                    const inputValue = $(this).val();
                    $deleteBtn.prop('disabled', inputValue !== confirmText);
                });
                
                // 绑定删除按钮事件
                $deleteBtn.off('click').on('click', function() {
                    if ($input.val() !== '确认删除') {
                        return;
                    }
                    
                    // 禁用输入和按钮
                    $input.prop('disabled', true);
                    $deleteBtn.prop('disabled', true);
                    
                    // 显示进度条
                    $progress.show();
                    $progressBar.css('width', '30%');
                    $progressText.text('正在删除项目相关数据...');
                    
                    // 执行删除请求
                    $.ajax({
                        url: self.baseUrl + 'delete',
                        type: 'POST',
                        data: { 
                            id: projectId,
                            delete_relations: true
                        },
                        success: function(response) {
                            if (response.success) {
                                $progressBar.css('width', '100%');
                                $progressText.text('删除成功');
                                
                                setTimeout(function() {
                                    self.closeModal('confirmDeleteModal', function() {
                                        self.showAlert('项目删除成功', 'success');
                                        self.loadProjects();
                                    });
                                }, 500);
                            } else {
                                $progressBar.css('width', '0%');
                                $progressText.text('删除失败: ' + (response.error || '未知错误'));
                                $deleteBtn.prop('disabled', false);
                                $input.prop('disabled', false);
                                
                                self.showAlert(response.error || '删除失败');
                            }
                        },
                        error: function(xhr) {
                            $progressBar.css('width', '0%');
                            let errorMsg = '删除失败';
                            try {
                                const response = JSON.parse(xhr.responseText);
                                errorMsg = response.error || response.message || errorMsg;
                            } catch(e) {
                                console.error('解析错误响应失败:', e);
                            }
                            
                            $progressText.text('删除失败: ' + errorMsg);
                            $deleteBtn.prop('disabled', false);
                            $input.prop('disabled', false);
                            
                            self.showAlert(errorMsg);
                        }
                    });
                });
                
                // 创建和显示模态框
                const modalInstance = new bootstrap.Modal(modalElement, {
                    backdrop: true,
                    keyboard: true
                });
                
                // 存储实例引用
                self.state.modalInstances.confirmDelete = modalInstance;
                
                // 绑定关闭事件，清理临时绑定的事件
                $(modalElement).one('hidden.bs.modal', function() {
                    $input.off();
                    $deleteBtn.off();
                });
                
                // 显示模态框
                modalInstance.show();
                
                // 设置焦点到输入框
                setTimeout(function() {
                    $input.focus();
                }, 500);
                
                console.log('删除确认模态框已显示');
            }
        } catch (error) {
            console.error('显示删除确认模态框出错:', error);
            this.showAlert('显示删除确认模态框出错: ' + error.message);
        }
    },

    /**
     * 检查项目是否存在
     */
    checkProjectExists: function(projectId) {
        return new Promise((resolve, reject) => {
            $.ajax({
                url: this.baseUrl + 'get',
                type: 'GET',
                data: { id: projectId },
                success: (res) => {
                    resolve(res.success);
                },
                error: () => {
                    // 如果获取失败，认为项目不存在
                    resolve(false);
                }
            });
        });
    },

    /**
     * 切换项目状态
     */
    toggleProjectStatus: function(projectId, currentStatus) {
        const newStatus = currentStatus === 1 ? 0 : 1;
        
        console.log('切换项目状态:', projectId, '从', currentStatus, '到', newStatus);
        $.ajax({
            url: this.baseUrl + 'toggle_status',
            type: 'POST',
            data: { 
                id: projectId,
                status: newStatus 
            },
            success: (res) => {
                console.log('切换状态响应:', res);
                if (res.success) {
                    this.showAlert(res.message || '状态切换成功', 'success');
                    
                    // 如果是启用项目（状态变为1），则更新全局当前项目ID
                    if (newStatus === 1 && res.data && res.data.id) {
                        console.log('切换到新项目:', res.data.project_name, '(ID:', res.data.id, ')');
                        
                        // 更新全局当前项目ID
                        if (window.currentSettingId !== res.data.id) {
                            window.currentSettingId = res.data.id;
                            window.currentProjectId = res.data.id;
                            
                            // 保存到本地存储，方便其他页面使用
                            try {
                                localStorage.setItem('currentProjectId', res.data.id);
                                localStorage.setItem('currentProjectName', res.data.project_name);
                            } catch (e) {
                                console.warn('保存项目信息到本地存储失败:', e);
                            }
                            
                            // 触发自定义事件，通知其他组件项目已切换
                            $(document).trigger('projectChanged', [res.data.id, res.data.project_name]);
                            
                            // 如果存在用户权限管理功能，刷新用户权限
                            if (typeof UserPermissions !== 'undefined' && UserPermissions.loadUserPermissions) {
                                console.log('刷新用户权限数据...');
                                UserPermissions.loadUserPermissions();
                            }
                            
                            // 如果页面上有管理员界面的权限表格，也刷新它
                            if ($('#permissionsTable').length > 0) {
                                console.log('刷新权限管理表格...');
                                this.refreshPermissionsTable(res.data.id);
                            }
                        }
                    }
                    
                    // 重新加载项目列表
                    this.loadProjects();
                } else {
                    this.showAlert(res.error || '状态切换失败');
                }
            },
            error: (xhr) => {
                console.error('切换状态失败:', xhr.responseText);
                let errorMsg = '切换状态失败';
                try {
                    const response = JSON.parse(xhr.responseText);
                    errorMsg = response.error || response.message || errorMsg;
                } catch(e) {
                    console.error('解析错误响应失败:', e);
                }
                this.showAlert(errorMsg);
            }
        });
    },
    
    /**
     * 刷新权限管理表格
     */
    refreshPermissionsTable: function(projectId) {
        // 查找当前显示的用户ID
        const currentUserId = $('#user-id').val() || $('#userId').val();
        
        if (!currentUserId) {
            console.warn('无法找到当前用户ID，跳过权限表刷新');
            return;
        }
        
        console.log('刷新用户权限表，用户ID:', currentUserId, '项目ID:', projectId);
        
        // 加载指定项目的用户权限
        $.ajax({
            url: '../api/index.php?route=user/permissions',
            type: 'GET',
            data: {
                user_id: currentUserId,
                project_id: projectId
            },
            success: function(response) {
                if (response.success) {
                    console.log('获取新项目权限成功:', response.data.length, '条记录');
                    
                    // 如果页面有权限表格渲染函数，调用它
                    if (typeof renderPermissionsTable === 'function') {
                        renderPermissionsTable(response.data);
                    } else if (typeof UserPermissions !== 'undefined' && UserPermissions.renderPermissions) {
                        UserPermissions.renderPermissions(response.data);
                    } else {
                        console.warn('找不到权限表格渲染函数');
                    }
                } else {
                    console.error('获取新项目权限失败:', response.error);
                }
            },
            error: function(xhr) {
                console.error('请求新项目权限失败:', xhr.status, xhr.statusText);
            }
        });
    },

    /**
     * 显示提示信息
     */
    showAlert: function(message, type = 'error') {
        if (window.showAlert) {
            // 如果是成功消息，延迟显示以等待模态框关闭动画
            if (type === 'success') {
                setTimeout(() => {
                    window.showAlert(message, type);
                }, 300);
            } else {
                window.showAlert(message, type);
            }
        } else {
            console.error('showAlert未找到:', message);
            alert(type === 'success' ? '成功: ' + message : '错误: ' + message);
        }
    },

    /**
     * 加载项目列表
     */
    loadProjects: function() {
        // 如果正在加载中，则不重复加载
        if (this.isLoading) {
            console.log('已在加载项目，跳过...');
            return;
        }

        console.log('加载项目列表...');
        this.isLoading = true;
        this.showLoading();

        // 发起AJAX请求
        $.ajax({
            url: this.baseUrl + 'list',
            type: 'GET',
            dataType: 'json',
            success: (res) => {
                console.log('=== 加载项目列表响应 ===');
                console.log('完整响应:', res);
                console.log('响应类型:', typeof res);
                console.log('是否成功:', res.success);
                console.log('数据:', res.data);
                console.log('数据类型:', Array.isArray(res.data) ? '数组' : typeof res.data);
                console.log('数据长度:', res.data ? res.data.length : 'N/A');
                console.log('=======================');
                
                if (res && typeof res === 'object') {
                    if (res.success) {
                        if (Array.isArray(res.data)) {
                            console.log('成功加载项目:', res.data.length);
                            console.log('项目详情:', res.data);
                            this.renderProjects(res.data);
                        } else {
                            console.error('无效数据格式，期望数组:', res.data);
                            this.showAlert('数据格式错误');
                        }
                    } else {
                        console.error('服务器返回错误:', res.error);
                        this.showAlert(res.error || '加载项目列表失败');
                    }
                } else {
                    console.error('无效响应格式:', res);
                    this.showAlert('服务器响应格式错误');
                }
            },
            error: (xhr, status, error) => {
                console.error('加载项目出错:', {
                    status: xhr.status,
                    statusText: xhr.statusText,
                    responseText: xhr.responseText,
                    error: error
                });
                let errorMessage = '加载失败';
                try {
                    const response = JSON.parse(xhr.responseText);
                    errorMessage = response.error || response.message || errorMessage;
                } catch (e) {
                    console.error('解析错误响应失败:', e);
                }
                this.showAlert(errorMessage);
                $('#projectList').html(`
                    <tr>
                        <td colspan="6" class="text-center text-danger">
                            <i class="fas fa-exclamation-circle me-2"></i>${errorMessage}
                        </td>
                    </tr>
                `);
            },
            complete: () => {
                console.log('加载项目请求完成');
                this.isLoading = false;
                this.hideLoading();
            }
        });
    },

    /**
     * 显示加载状态
     */
    showLoading: function() {
        $('#projectList').html(`
            <tr>
                <td colspan="6" class="text-center">
                    <div class="d-flex justify-content-center align-items-center py-3">
                        <div class="spinner-border text-primary me-2" role="status">
                            <span class="visually-hidden">加载中...</span>
                        </div>
                        <span>加载中...</span>
                    </div>
                </td>
            </tr>
        `);
    },

    /**
     * 隐藏加载状态
     */
    hideLoading: function() {
        // 加载状态会在renderProjects中自动清除，这里不需要额外处理
    }
}; 