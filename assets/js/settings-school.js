/**
 * 文件名: assets/js/settings-school.js
 * 功能描述: 学校信息管理模块
 * 
 * 该模块负责:
 * 1. 学校基本信息的加载与显示
 * 2. 学校信息编辑表单的处理
 * 3. 学校信息的保存与更新
 * 
 * API调用说明:
 * - GET api/index.php?route=school/getInfo: 获取学校信息
 * - POST api/index.php?route=school/updateInfo: 更新学校信息
 * 
 * 关联文件:
 * - modules/school_settings.php: 学校设置页面
 * - controllers/SchoolSettingsController.php: 学校设置控制器
 * - assets/js/settings.js: 通用设置函数库
 */

/**
 * 学校信息管理模块
 */
window.School = {
    /**
     * 初始化学校信息管理模块
     */
    init() {
        // 绑定事件
        this.bindEvents();
        // 加载学校信息
        this.loadSchoolInfo();
    },

    /**
     * 绑定事件
     */
    bindEvents() {
        // 编辑按钮点击事件
        $('#editSchoolBtn').on('click', () => {
            this.showEditModal();
        });

        // 保存按钮点击事件
        $('#saveSchoolBtn').on('click', () => {
            this.saveSchoolInfo();
        });

        // 表单提交事件
        $('#schoolInfoForm').on('submit', (e) => {
            e.preventDefault();
            this.saveSchoolInfo();
        });
    },

    /**
     * 加载学校信息
     */
    loadSchoolInfo() {
        $.ajax({
            url: 'api/index.php',
            method: 'GET',
            data: {
                route: 'school/getInfo'
            },
            success: (response) => {
                if (response.success) {
                    const school = response.data;
                    // 显示学校信息
                    $('#schoolName').val(school.school_name || '');
                    $('#schoolYear').val(school.school_year || '');
                    $('#semester').val(school.semester || '1');
                    $('#projectName').val(school.project_name || '');
                } else {
                    window.utils.showAlert('加载学校信息失败：' + response.error);
                }
            },
            error: (jqXHR, textStatus, errorThrown) => {
                console.error('API Error:', textStatus, errorThrown);
                window.utils.showAlert('加载学校信息失败，请检查网络连接');
            }
        });
    },

    /**
     * 保存学校信息
     */
    saveSchoolInfo() {
        if (!window.utils.validateForm('schoolInfoForm')) {
            return;
        }

        const formData = {
            school_name: $('#schoolName').val(),
            school_year: $('#schoolYear').val(),
            semester: $('#semester').val(),
            project_name: $('#projectName').val()
        };

        $.ajax({
            url: 'api/index.php',
            method: 'POST',
            data: {
                route: 'school/updateInfo',
                ...formData
            },
            success: (response) => {
                if (response.success) {
                    window.utils.showSuccess('保存成功');
                } else {
                    window.utils.showAlert('保存失败：' + response.error);
                }
            },
            error: (jqXHR, textStatus, errorThrown) => {
                console.error('API Error:', textStatus, errorThrown);
                window.utils.showAlert('保存失败，请检查网络连接');
            }
        });
    }
};

// 等待DOM和其他脚本加载完成
$(document).ready(() => {
    // 确保utils和CONFIG已经加载
    if (window.utils && window.CONFIG) {
        window.School.init();
    } else {
        console.error('Required dependencies not loaded');
    }
}); 