/**
 * 文件名: assets/js/settings.js
 * 功能描述: 系统设置公共JS库
 * 
 * 该文件负责:
 * 1. 提供全局配置参数 (window.CONFIG)
 * 2. 提供通用工具函数 (window.utils)
 * 3. 定义全局UI交互行为
 * 4. 初始化系统公共组件
 * 
 * 主要功能:
 * - 提示信息显示 (showAlert/showSuccess)
 * - 表单验证 (validateForm)
 * - 防抖函数 (debounce)
 * - 初始化Bootstrap工具提示和模态框
 * 
 * 关联文件:
 * - 所有前端JS模块: 依赖此文件提供的工具函数
 * - assets/js/project-settings.js: 使用通用配置和工具函数
 * - assets/js/user-permissions.js: 使用通用配置和工具函数
 * - assets/js/settings-school.js: 使用通用配置和工具函数
 */

// 全局配置
window.CONFIG = {
    apiBase: '/api/index.php?route=',
    itemsPerPage: 10,
    debounceDelay: 300
};

// 工具函数
window.utils = {
    /**
     * 显示提示信息
     */
    showAlert: function(message, type = 'info', callback = null) {
        const modal = document.getElementById('alertModal');
        const alertModal = new bootstrap.Modal(modal);
        
        // 保存触发模态框的元素
        const triggerElement = document.activeElement;
        
        // 设置消息
        $('#alertMessage').text(message);
        
        // 模态框显示时的处理
        modal.addEventListener('shown.bs.modal', function () {
            // 将焦点移到确定按钮
            modal.querySelector('.btn-primary').focus();
        }, { once: true });
        
        // 模态框隐藏时的处理
        modal.addEventListener('hidden.bs.modal', function () {
            // 恢复焦点到触发元素
            if (triggerElement) {
                triggerElement.focus();
            }
            // 执行回调
            if (callback) {
                callback();
            }
        }, { once: true });
        
        alertModal.show();
    },
    
    /**
     * 显示成功提示
     */
    showSuccess: function(message, callback = null) {
        this.showAlert(message, 'success', callback);
    },
    
    /**
     * 防抖函数
     */
    debounce: function(func, wait) {
        let timeout;
        return function(...args) {
            clearTimeout(timeout);
            timeout = setTimeout(() => func.apply(this, args), wait);
        };
    },

    /**
     * 验证表单
     */
    validateForm: function(formId) {
        let isValid = true;
        $(`#${formId} [required]`).each(function() {
            if (!$(this).val()) {
                isValid = false;
                $(this).addClass('is-invalid');
            } else {
                $(this).removeClass('is-invalid');
            }
        });
        return isValid;
    }
};

// 初始化
$(document).ready(function() {
    // 初始化Bootstrap工具提示
    $('[data-bs-toggle="tooltip"]').tooltip();
    
    // 处理表单验证
    $('form').on('submit', function(e) {
        if (!utils.validateForm(this.id)) {
            e.preventDefault();
            utils.showAlert('请填写所有必填字段', 'warning');
        }
    });

    // 为所有模态框添加焦点管理
    $('.modal').each(function() {
        const modal = this;
        
        // 保存最后一个获得焦点的元素
        let lastFocusedElement = null;
        
        $(modal).on('show.bs.modal', function() {
            // 保存触发模态框的元素
            lastFocusedElement = document.activeElement;
        });
        
        $(modal).on('shown.bs.modal', function() {
            // 将焦点移到第一个可聚焦元素
            const focusableElements = modal.querySelectorAll(
                'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
            );
            if (focusableElements.length > 0) {
                focusableElements[0].focus();
            }
        });
        
        $(modal).on('hidden.bs.modal', function() {
            // 恢复焦点
            if (lastFocusedElement) {
                lastFocusedElement.focus();
            }
        });
    });
}); 