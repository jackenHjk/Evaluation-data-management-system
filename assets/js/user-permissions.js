/**
 * 文件名: assets/js/user-permissions.js
 * 功能描述: 用户权限管理模块
 * 
 * 该模块负责:
 * 1. 加载和显示用户权限数据
 * 2. 处理权限变更时的UI更新
 * 3. 根据不同权限类型(年级权限、科目权限)渲染权限UI
 * 4. 响应项目切换事件，更新相应权限数据
 * 
 * API调用说明:
 * - GET ../api/index.php?route=user/permissions: 获取用户权限数据
 * 
 * 关联文件:
 * - modules/user_settings.php: 用户设置页面
 * - controllers/UserController.php: 用户控制器
 * - assets/js/settings.js: 通用设置函数库
 */

const UserPermissions = {
    /**
     * 初始化
     */
    init: function() {
        console.log('初始化用户权限管理模块');
        this.setupEventListeners();
        
        // 加载当前用户权限
        this.loadUserPermissions();
        
        // 监听项目变更事件
        $(document).on('projectChanged', (e, projectId, projectName) => {
            console.log('接收到项目变更事件:', projectId, projectName);
            this.loadUserPermissions(projectId);
        });
    },
    
    /**
     * 设置事件监听器
     */
    setupEventListeners: function() {
        // 监听项目切换事件
        $(document).on('projectChanged', (event, projectId, projectName) => {
            console.log('监听到项目切换事件:', projectId, projectName);
            // 加载新项目的用户权限
            this.loadUserPermissions(projectId);
        });
    },
    
    /**
     * 加载用户权限
     * @param {number} projectId - 可选的项目ID，如果不提供则使用当前项目
     */
    loadUserPermissions: function(projectId) {
        const userId = this.getCurrentUserId();
        if (!userId) {
            console.warn('无法获取当前用户ID');
            return;
        }
        
        console.log('加载用户权限，用户ID:', userId, '项目ID:', projectId || '当前项目');
        
        $.ajax({
            url: '../api/index.php?route=user/permissions',
            type: 'GET',
            data: {
                user_id: userId,
                project_id: projectId || undefined
            },
            success: (response) => {
                if (response.success && response.data) {
                    console.log('获取权限成功:', response.data.length, '条记录');
                    
                    // 将权限数据存储到全局变量，方便其他组件使用
                    window.userPermissions = response.data;
                    
                    // 渲染权限
                    this.renderPermissions(response.data);
                    
                    // 触发权限已更新事件
                    $(document).trigger('permissionsLoaded', [response.data]);
                } else {
                    console.error('获取权限失败:', response.error || '未知错误');
                }
            },
            error: (xhr) => {
                console.error('权限请求失败:', xhr.status, xhr.statusText);
            }
        });
    },
    
    /**
     * 获取当前用户ID
     * @returns {string|null} 当前用户ID或null
     */
    getCurrentUserId: function() {
        // 尝试多种方式获取用户ID
        return $('#user-id').val() || 
               $('#userId').val() || 
               $('[name="user_id"]').val() ||
               this.getUserIdFromUrl() ||
               window.currentUserId;
    },
    
    /**
     * 从URL中提取用户ID
     * @returns {string|null} 用户ID或null
     */
    getUserIdFromUrl: function() {
        const urlParams = new URLSearchParams(window.location.search);
        return urlParams.get('user_id');
    },
    
    /**
     * 渲染权限到UI
     * @param {Array} permissions - 权限数据数组
     */
    renderPermissions: function(permissions) {
        // 检查权限表格是否存在
        if ($('#permissionsTable').length) {
            this.renderPermissionsTable(permissions);
        }
        
        // 检查是否存在年级权限复选框
        if ($('.grade-permission').length) {
            this.renderGradePermissions(permissions);
        }
        
        // 检查是否存在科目权限复选框
        if ($('.subject-permission').length) {
            this.renderSubjectPermissions(permissions);
        }
        
        // 权限渲染完成后的回调
        console.log('权限渲染完成');
        $(document).trigger('permissionsRendered', [permissions]);
    },
    
    /**
     * 渲染权限表格
     * @param {Array} permissions - 权限数据数组
     */
    renderPermissionsTable: function(permissions) {
        const $table = $('#permissionsTable');
        if (!$table.length) return;
        
        console.log('渲染权限表格，共', permissions.length, '条记录');
        
        // 清空表格内容
        const $tbody = $table.find('tbody');
        $tbody.empty();
        
        // 渲染权限行
        if (Array.isArray(permissions) && permissions.length > 0) {
            permissions.forEach(perm => {
                const $row = $('<tr></tr>');
                
                // 根据权限表格的实际结构添加单元格
                $row.append(`<td>${perm.grade_name || '未知年级'}</td>`);
                $row.append(`<td>${perm.class_name || '全部班级'}</td>`);
                $row.append(`<td>${perm.subject_name || '全部科目'}</td>`);
                
                // 添加权限状态单元格
                $row.append(`<td>${this.formatPermission(perm.can_edit)}</td>`);
                $row.append(`<td>${this.formatPermission(perm.can_download)}</td>`);
                $row.append(`<td>${this.formatPermission(perm.can_edit_students)}</td>`);
                
                $tbody.append($row);
            });
        } else {
            // 无权限记录
            $tbody.append(`
                <tr>
                    <td colspan="6" class="text-center">暂无权限记录</td>
                </tr>
            `);
        }
    },
    
    /**
     * 渲染年级权限
     * @param {Array} permissions - 权限数据数组
     */
    renderGradePermissions: function(permissions) {
        if (!Array.isArray(permissions)) return;
        
        // 获取当前项目ID
        const currentSettingId = window.currentSettingId || localStorage.getItem('currentProjectId');
        console.log('渲染年级权限，当前项目ID:', currentSettingId);
        
        // 清除所有年级权限选中状态
        $('.grade-permission').prop('checked', false);
        
        // 设置权限选中状态
        permissions.forEach(perm => {
            // 只渲染当前项目的权限
            if (currentSettingId && perm.setting_id && parseInt(perm.setting_id) !== parseInt(currentSettingId)) {
                return;
            }
            
            if (perm.grade_id && (perm.can_edit_students === '1' || perm.can_edit_students === 1)) {
                $(`.grade-permission[data-grade-id="${perm.grade_id}"]`).prop('checked', true);
            }
        });
    },
    
    /**
     * 渲染科目权限
     * @param {Array} permissions - 权限数据数组
     */
    renderSubjectPermissions: function(permissions) {
        if (!Array.isArray(permissions)) return;
        
        // 获取当前项目ID
        const currentSettingId = window.currentSettingId || localStorage.getItem('currentProjectId');
        console.log('渲染科目权限，当前项目ID:', currentSettingId);
        
        // 清除所有科目权限选中状态
        $('.subject-permission').prop('checked', false);
        
        // 设置权限选中状态
        permissions.forEach(perm => {
            // 只渲染当前项目的权限
            if (currentSettingId && perm.setting_id && parseInt(perm.setting_id) !== parseInt(currentSettingId)) {
                return;
            }
            
            if (perm.grade_id && perm.subject_id && (perm.can_edit === '1' || perm.can_edit === 1)) {
                $(`.subject-permission[data-grade-id="${perm.grade_id}"][data-subject-id="${perm.subject_id}"]`).prop('checked', true);
            }
        });
    },
    
    /**
     * 格式化权限显示
     * @param {string|number} permission - 权限值
     * @returns {string} 格式化后的HTML
     */
    formatPermission: function(permission) {
        if (permission === '1' || permission === 1) {
            return '<span class="badge bg-success">是</span>';
        } else {
            return '<span class="badge bg-secondary">否</span>';
        }
    }
};

// 页面加载完成后初始化
$(document).ready(function() {
    UserPermissions.init();
}); 