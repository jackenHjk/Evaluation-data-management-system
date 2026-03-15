<?php
/**
 * 文件名: modules/notifications.php
 * 功能描述: 消息通知模块
 * 
 * 该文件负责:
 * 1. 显示用户的消息通知列表
 * 2. 支持按类型筛选通知
 * 3. 提供通知标记已读功能
 * 4. 支持查看通知详情
 * 5. 显示成绩修改申请的通知
 * 
 * 消息通知模块为用户提供系统内的各类通知，包括成绩修改申请、审核结果等，
 * 支持分页显示，提供已读/未读状态标识，并可以直接跳转到相关操作页面。
 * 
 * 关联文件:
 * - controllers/ScoreEditRequestController.php: 成绩修改申请控制器
 * - api/index.php: API入口
 * - assets/js/notifications.js: 消息通知前端脚本
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
                        <i class="fas fa-bell text-primary"></i>
                        消息通知
                    </h5>
                    <div class="d-flex align-items-center">
                        <div class="btn-group">
                            <div style="width: 160px;">
                                <select class="form-select" id="typeFilter">
                                    <option value="">全部类型</option>
                                    <option value="score_edit_request">审核中</option>
                                    <option value="score_edit_approved">已通过</option>
                                    <option value="score_edit_rejected">已驳回</option>
                                </select>
                            </div>
                            <button id="searchBtn" class="btn btn-primary">
                                <i class="fas fa-search"></i>
                                查询
                            </button>
                            <button id="markAllReadBtn" class="btn btn-secondary">
                                <i class="fas fa-check-double"></i>
                                全部标为已读
                            </button>
                            <div class="batch-actions ms-2" style="display:none;">
                                <button id="batchApproveBtn" class="btn btn-success">
                                    <i class="fas fa-check me-1"></i>批量通过
                                </button>
                                <button id="batchRejectBtn" class="btn btn-danger ms-2">
                                    <i class="fas fa-times me-1"></i>批量驳回
                                </button>
                                <span class="selected-count ms-2"></span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <!-- 通知列表 -->
                    <div id="notificationList" class="mt-3">
                        <!-- 通知列表将通过JS加载 -->
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
/* 表格样式 */
.notification-table {
    width: 100%;
    border-collapse: collapse;
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
    border-radius: 10px;
    overflow: hidden;
}

.notification-table th {
    background-color: #f8f9fa;
    padding: 12px 15px;
    text-align: left;
    font-weight: 600;
    color: #212529;
    white-space: nowrap;
}

.notification-table td {
    padding: 12px 15px;
    border-top: 1px solid #e9ecef;
    vertical-align: middle;
}

.notification-table tbody tr:hover {
    background-color: #f8f9fa;
}

/* 状态标签样式 */
.status-badge {
    padding: 5px 12px;
    border-radius: 20px;
    font-size: 0.9em;
    font-weight: 600;
    display: inline-block;
    min-width: 100px;
    text-align: center;
}

.status-pending {
    color: #0d6efd;
}

.status-approved {
    color: #198754;
}

.status-rejected {
    color: #dc3545;
}

/* 截断文本样式 */
.truncate-text {
    max-width: 200px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    cursor: pointer;
}

/* 操作按钮样式 */
.action-btn {
    padding: 4px 10px;
    border-radius: 15px;
    font-size: 0.85em;
    margin-right: 5px;
    display: inline-block;
}

.btn-view {
    background-color: #0d6efd;
    color: white;
}

.btn-approve {
    background-color: #198754;
    color: white;
}

.btn-reject {
    background-color: #dc3545;
    color: white;
}

/* 操作列样式 */
.action-cell {
    white-space: nowrap;
    width: 240px;
    min-width: 240px;
    text-align: center;
}

/* 新增的CSS样式 */
.unread-row {
    background-color: #e7f1ff;
    border-left: 4px solid #0d6efd;
    font-weight: 500;
}

.unread-row td:first-child::before {
    content: "";
    display: inline-block;
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background-color: #dc3545;
    margin-right: 8px;
    vertical-align: middle;
}

.unread-row:hover {
    background-color: #d0e6ff !important;
}

.badge {
    font-size: 0.7rem;
    font-weight: 600;
    padding: 0.25em 0.5em;
    border-radius: 10px;
}
</style>

<script>
$(document).ready(function() {
    // 当前页码
    let currentPage = 1;
    // 每页显示数量
    const pageSize = 10;
    // 通知类型过滤器
    let typeFilter = '';
    
    // 获取当前用户信息和角色
    getCurrentUser()
        .then(user => {
            // 设置全局变量
            window.userRole = user.role;
            console.log('当前用户角色:', window.userRole);
            
            // 加载通知列表
            loadNotifications();
        })
        .catch(error => {
            console.error('获取用户信息失败:', error);
            showError('获取用户信息失败: ' + error.message);
            loadNotifications(); // 仍然尝试加载通知
        });
    
    // 获取当前用户信息
    function getCurrentUser() {
        return new Promise((resolve, reject) => {
            // 添加时间戳防止缓存
            const timestamp = new Date().getTime();
            $.ajax({
                url: '../api/index.php?route=auth/current_user&_=' + timestamp,
                method: 'GET',
                dataType: 'json',
                xhrFields: {
                    withCredentials: true
                },
                success: function(response) {
                    console.log('获取用户信息响应:', response);
                    if (response && response.success && response.user) {
                        resolve(response.user);
                    } else {
                        reject(new Error('获取用户信息失败'));
                    }
                },
                error: function(xhr) {
                    reject(new Error('网络请求失败: ' + xhr.status));
                }
            });
        });
    }
    
    // 加载通知列表
    function loadNotifications() {
        $('#notificationList').html(`
            <div class="text-center py-5">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="mt-2">加载中...</p>
            </div>
        `);
        
        $.get('../api/index.php?route=score_edit/list', {
            page: currentPage,
            page_size: pageSize,
            type: typeFilter
        })
        .done(function(response) {
            if (response.success) {
                displayNotifications(response.data);
                // 更新全局未读消息数量
                updateNotificationBadge();
            } else {
                showError('加载通知失败：' + response.error);
            }
        })
        .fail(function(xhr) {
            showError('加载通知失败：' + (xhr.responseJSON?.error || '未知错误'));
        });
    }
    
    // 显示通知列表
    function displayNotifications(data) {
        // 检查数据结构
        const notifications = data.requests || [];
        const pagination = data.pagination || {
            total: 0,
            page: 1,
            page_size: 10,
            total_pages: 0
        };
        
        if (notifications.length === 0) {
            $('#notificationList').html(`
                <div class="empty-notification">
                    <i class="fas fa-bell-slash"></i>
                    <p>暂无通知</p>
                </div>
            `);
            $('#pagination').empty();
            return;
        }
        
        console.log('当前用户角色:', window.userRole, '是否admin/teaching:', window.userRole === 'admin' || window.userRole === 'teaching');
        
        // 表格形式显示通知列表
        let html = `
            <table class="notification-table">
                <thead>
                    <tr>
                        <th width="3%"><input type="checkbox" class="form-check-input" id="selectAllNotifications"></th>
                        <th width="5%">序号</th>
                        <th width="10%">申请人</th>
                        <th width="10%">申请班级</th>
                        <th width="10%">申请学科</th>
                        <th width="20%">修改原因</th>
                        <th width="12%">提交时间</th>
                        <th width="12%">状态</th>
                        <th width="18%">操作</th>
                    </tr>
                </thead>
                <tbody>
        `;
        
        notifications.forEach((notification, index) => {
            const isUnread = notification.is_read === 0 || notification.is_read === '0';
            let statusText = '';
            let statusClass = '';
            
            // 在列表显示中显示状态和操作人姓名
            switch (notification.status) {
                case 'pending':
                    statusText = '审核中';
                    statusClass = 'status-pending';
                    break;
                case 'approved':
                    statusText = `已通过 (${notification.reviewer_name})`;
                    statusClass = 'status-approved';
                    break;
                case 'rejected':
                    statusText = `已驳回 (${notification.reviewer_name})`;
                    statusClass = 'status-rejected';
                    break;
                default:
                    statusText = '未知状态';
                    statusClass = '';
            }
            
            // 生成操作按钮
            let actionButtons = `<button class="btn btn-sm action-btn btn-view view-detail-btn" data-id="${notification.id}"><i class="fas fa-eye me-1"></i>详情</button>`;
            
            // 只有待审核的申请才显示通过/驳回按钮，且只对管理员和教导处显示
            if (notification.status === 'pending' && (window.userRole === 'admin' || window.userRole === 'teaching')) {
                actionButtons += `
                    <button class="btn btn-sm action-btn btn-approve approve-btn" data-id="${notification.id}"><i class="fas fa-check me-1"></i>通过</button>
                    <button class="btn btn-sm action-btn btn-reject reject-btn" data-id="${notification.id}"><i class="fas fa-times me-1"></i>驳回</button>
                `;
            }
            
            // 格式化日期
            const createdAt = new Date(notification.created_at);
            const formattedDate = createdAt.getFullYear() + '-' + 
                padZero(createdAt.getMonth() + 1) + '-' + 
                padZero(createdAt.getDate()) + ' ' + 
                padZero(createdAt.getHours()) + ':' + 
                padZero(createdAt.getMinutes());
                
            // 计算序号
            const rowNum = (pagination.page - 1) * pagination.page_size + index + 1;
            
            // 申请班级 = 年级名称 + 班级名称
            const classInfo = notification.grade_name + notification.class_name;
            
            // 添加未读角标
            const unreadBadge = isUnread ? '<span class="badge bg-danger position-absolute top-0 start-100 translate-middle">新</span>' : '';
            
            html += `
                <tr class="${isUnread ? 'unread-row' : ''}" data-id="${notification.id}">
                    <td>
                        <input type="checkbox" class="form-check-input notification-checkbox" 
                               data-id="${notification.id}" 
                               data-status="${notification.status}"
                               ${notification.status !== 'pending' ? 'disabled' : ''}>
                    </td>
                    <td>${rowNum}</td>
                    <td>${notification.requester_name}</td>
                    <td>${classInfo}</td>
                    <td>${notification.subject_name}</td>
                    <td>
                        <div class="truncate-text reason-text position-relative" data-reason="${notification.reason}">
                            ${notification.reason}
                            ${unreadBadge}
                        </div>
                    </td>
                    <td>${formattedDate}</td>
                    <td><span class="status-badge ${statusClass}">${statusText}</span></td>
                    <td class="action-cell">${actionButtons}</td>
                </tr>
            `;
        });
        
        html += `
                </tbody>
            </table>
        `;
        
        $('#notificationList').html(html);

        // 全部标记为已读按钮
        $('#markAllReadBtn').click(function() {
            $.post('../api/index.php?route=score_edit/mark_all_read')
                .done(function(response) {
                    if (response.success) {
                        // 重新加载通知列表
                        loadNotifications();
                        
                        // 显示成功消息
                        Swal.fire({
                            icon: 'success',
                            title: '已全部标记为已读',
                            toast: true,
                            position: 'top-end',
                            showConfirmButton: false,
                            timer: 2000
                        });
                    } else {
                        showError('标记全部已读失败：' + response.error);
                    }
                })
                .fail(function(xhr) {
                    showError('标记全部已读失败：' + (xhr.responseJSON?.error || '未知错误'));
                });
        });
        
        // 生成分页
        generatePagination(pagination.page, pagination.total_pages);
        
        // 绑定查看详情点击事件
        $('.view-detail-btn').click(function() {
            const requestId = $(this).data('id');
            viewNotificationDetail(requestId);
        });
        
        // 截断文本点击事件
        $('.reason-text').click(function() {
            const reason = $(this).data('reason');
            if (reason) {
                Swal.fire({
                    title: '修改原因',
                    html: `<div style="text-align: left;">${reason}</div>`,
                    confirmButtonText: '关闭'
                });
            }
        });
        
        // 绑定通过按钮点击事件
        $('.approve-btn').click(function() {
            const requestId = $(this).data('id');
            approveRequest(requestId);
        });
        
        // 绑定驳回按钮点击事件
        $('.reject-btn').click(function() {
            const requestId = $(this).data('id');
            rejectRequest(requestId);
        });
        
        // 全选/取消全选功能
        $('#selectAllNotifications').click(function() {
            const isChecked = $(this).prop('checked');
            $('.notification-checkbox:not(:disabled)').prop('checked', isChecked);
            updateBatchActionsVisibility();
        });
        
        // 单个复选框点击事件
        $('.notification-checkbox').click(function(e) {
            e.stopPropagation();
            updateBatchActionsVisibility();
        });
        
        // 行点击事件 - 切换复选框状态
        $('tr[data-id]').click(function(e) {
            // 如果点击的是复选框、按钮或链接，不处理
            if ($(e.target).is('input, button, a, .btn, .action-btn, .reason-text') || 
                $(e.target).closest('button, a, .btn, .action-btn').length) {
                return;
            }
            
            const $checkbox = $(this).find('.notification-checkbox');
            if (!$checkbox.prop('disabled')) {
                $checkbox.prop('checked', !$checkbox.prop('checked'));
                updateBatchActionsVisibility();
            }
        });
        
        // 批量通过按钮点击事件
        $('#batchApproveBtn').click(function() {
            batchApprove();
        });
        
        // 批量驳回按钮点击事件
        $('#batchRejectBtn').click(function() {
            batchReject();
        });
        
        // 延迟标记已读
        setTimeout(() => {
            markAllAsRead();
        }, 2000);
    }
    
    // 补零函数
    function padZero(num) {
        return num.toString().padStart(2, '0');
    }

    // 查看通知详情
    function viewNotificationDetail(requestId) {
        $.get('../api/index.php?route=score_edit/detail', {
            request_id: requestId
        })
        .done(function(response) {
            if (response.success && response.data) {
                displayNotificationDetail(response.data);
                // 标记该通知为已读
                $.post('../api/index.php?route=score_edit/mark_read', {
                    request_id: requestId
                })
                .done(function(markResponse) {
                    if (markResponse.success) {
                        // 更新未读消息数量
                        updateNotificationBadge();
                    } else {
                        console.error('标记已读失败:', markResponse.error);
                    }
                })
                .fail(function(xhr) {
                    console.error('标记已读API调用失败:', xhr.status, xhr.statusText);
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: '查看详情失败',
                    text: response.error || '未能获取详细信息'
                });
            }
        })
        .fail(function(xhr) {
            Swal.fire({
                icon: 'error',
                title: '查看详情失败',
                text: xhr.responseJSON?.error || '未知错误'
            });
        });
    }

    // 显示通知详情
    function displayNotificationDetail(data) {
        const request = data.request;
        const details = data.details || [];
        
        console.log('显示通知详情：', data, '当前用户角色:', window.userRole);
        
        // 状态标签
        let statusBadge = '';
        let statusClass = '';
        switch (request.status) {
            case 'pending':
                statusBadge = '<span class="badge bg-primary">审核中</span>';
                statusClass = 'border-primary';
                break;
            case 'approved':
                statusBadge = '<span class="badge bg-success">已通过</span>';
                statusClass = 'border-success';
                break;
            case 'rejected':
                statusBadge = '<span class="badge bg-danger">已驳回</span>';
                statusClass = 'border-danger';
                break;
        }
        
        // 生成修改详情表格
        let detailsTable = `
            <div class="table-responsive mt-3">
                <table class="table table-bordered table-striped table-sm">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 20%">学生</th>
                            <th style="width: 20%">修改项</th>
                            <th style="width: 30%">原值</th>
                            <th style="width: 30%">新值</th>
                        </tr>
                    </thead>
                    <tbody>
        `;
        
        if (details.length === 0) {
            detailsTable += `
                <tr>
                    <td colspan="4" class="text-center">无修改记录</td>
                </tr>
            `;
        } else {
            details.forEach(detail => {
                // 检查缺考状态变更
                if (detail.old_is_absent !== detail.new_is_absent) {
                    detailsTable += `
                        <tr>
                            <td>${detail.student_name || '未知'}</td>
                            <td>缺考状态</td>
                            <td>${detail.old_is_absent == 1 ? '<span class="badge bg-secondary">缺考</span>' : '<span class="badge bg-success">正常</span>'}</td>
                            <td>${detail.new_is_absent == 1 ? '<span class="badge bg-secondary">缺考</span>' : '<span class="badge bg-success">正常</span>'}</td>
                        </tr>
                    `;
                }
                
                // 如果都不是缺考，检查成绩变更
                if (detail.new_is_absent != 1 && detail.old_is_absent != 1) {
                    // 基础分变更
                    if (detail.old_base_score !== detail.new_base_score) {
                        detailsTable += `
                            <tr>
                                <td>${detail.student_name || '未知'}</td>
                                <td>基础分</td>
                                <td>${detail.old_base_score !== null ? detail.old_base_score : '<span class="text-muted">未录入</span>'}</td>
                                <td class="text-primary fw-bold">${detail.new_base_score !== null ? detail.new_base_score : '<span class="text-muted">未录入</span>'}</td>
                            </tr>
                        `;
                    }
                    
                    // 附加分变更
                    if (detail.old_extra_score !== detail.new_extra_score) {
                        detailsTable += `
                            <tr>
                                <td>${detail.student_name || '未知'}</td>
                                <td>附加分</td>
                                <td>${detail.old_extra_score !== null ? detail.old_extra_score : '<span class="text-muted">未录入</span>'}</td>
                                <td class="text-primary fw-bold">${detail.new_extra_score !== null ? detail.new_extra_score : '<span class="text-muted">未录入</span>'}</td>
                            </tr>
                        `;
                    }
                    
                    // 总分变更
                    if (detail.old_total_score !== detail.new_total_score) {
                        detailsTable += `
                            <tr>
                                <td>${detail.student_name || '未知'}</td>
                                <td>总分</td>
                                <td>${detail.old_total_score !== null ? detail.old_total_score : '<span class="text-muted">未录入</span>'}</td>
                                <td class="text-primary fw-bold">${detail.new_total_score !== null ? detail.new_total_score : '<span class="text-muted">未录入</span>'}</td>
                            </tr>
                        `;
                    }
                }
            });
        }
        
        detailsTable += `
                    </tbody>
                </table>
            </div>
        `;
        
        // 审核操作按钮
        let actionButtons = '';
        if (request.status === 'pending' && (window.userRole === 'admin' || window.userRole === 'teaching')) {
            actionButtons = `
                <div class="d-flex justify-content-end mt-4 gap-2">
                    <button class="btn btn-success approve-detail-btn" data-id="${request.id}">
                        <i class="fas fa-check me-1"></i>通过
                    </button>
                    <button class="btn btn-danger reject-detail-btn" data-id="${request.id}">
                        <i class="fas fa-times me-1"></i>驳回
                    </button>
                    <button class="btn btn-secondary ms-2 close-detail-btn">
                        <i class="fas fa-times me-1"></i>关闭
                    </button>
                </div>
            `;
        } else {
            actionButtons = `
                <div class="d-flex justify-content-end mt-4">
                    <button class="btn btn-secondary close-detail-btn">
                        <i class="fas fa-times me-1"></i>关闭
                    </button>
                </div>
            `;
        }
        
        // 审核信息
        let reviewInfo = '';
        if (request.status !== 'pending') {
            const statusColorClass = request.status === 'approved' ? 'success' : 'danger';
            reviewInfo = `
                <div class="mt-3 p-3 bg-light rounded border border-${statusColorClass}">
                    <h6 class="border-bottom pb-2 text-${statusColorClass}">审核信息</h6>
                    <div class="row">
                        <div class="col-md-4">
                            <p class="mb-1"><strong>审核人：</strong><span class="text-${statusColorClass}">${request.reviewer_name || '未知'}</span></p>
                        </div>
                        <div class="col-md-8">
                            <p class="mb-1"><strong>审核时间：</strong>${request.reviewed_at || '未知'}</p>
                        </div>
                    </div>
                    <p class="mt-2 mb-0"><strong>审核意见：</strong>${request.review_comment || '无'}</p>
                </div>
            `;
        }
        
        Swal.fire({
            title: '成绩修改申请详情',
            html: `
                <div class="text-start">
                    <div class="card border ${statusClass} mb-3">
                        <div class="card-header bg-light d-flex justify-content-between align-items-center">
                            <h6 class="mb-0 fw-bold">申请信息 ${statusBadge}</h6>
                            <div><small class="text-muted">申请ID: ${request.id}</small></div>
                        </div>
                        <div class="card-body">
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <p class="mb-1"><strong><i class="fas fa-user me-2"></i>申请人：</strong>${request.requester_name}</p>
                                    <p class="mb-1"><strong><i class="fas fa-school me-2"></i>申请班级：</strong>${request.grade_name}${request.class_name}</p>
                                </div>
                                <div class="col-md-6">
                                    <p class="mb-1"><strong><i class="fas fa-book me-2"></i>申请学科：</strong>${request.subject_name}</p>
                                    <p class="mb-1"><strong><i class="fas fa-clock me-2"></i>提交时间：</strong>${request.created_at}</p>
                                </div>
                            </div>
                            <div class="mb-3">
                                <p class="mb-1"><strong><i class="fas fa-info-circle me-2"></i>修改原因：</strong></p>
                                <div class="p-3 border rounded bg-light">${request.reason}</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card border mb-3">
                        <div class="card-header bg-light">
                            <h6 class="mb-0 fw-bold"><i class="fas fa-edit me-2"></i>修改详情</h6>
                        </div>
                        <div class="card-body p-0">
                            ${detailsTable}
                        </div>
                    </div>
                    
                    ${reviewInfo}
                    
                    ${actionButtons}
                </div>
            `,
            width: '800px',
            showConfirmButton: false,
            showCloseButton: true,
            didOpen: () => {
                // 绑定审批按钮事件
                $('.approve-detail-btn').click(function() {
                    Swal.close();
                    approveRequest($(this).data('id'));
                });
                
                $('.reject-detail-btn').click(function() {
                    Swal.close();
                    rejectRequest($(this).data('id'));
                });
                
                $('.close-detail-btn').click(function() {
                    Swal.close();
                });
            }
        });
    }

    // 审核通过请求
    function approveRequest(requestId) {
        Swal.fire({
            title: '通过申请',
            html: `
                <div class="mb-3">
                    <label for="reviewComment" class="form-label text-start d-block">审核意见 (选填):</label>
                    <textarea id="reviewComment" class="form-control" rows="3" placeholder="请输入审核意见..."></textarea>
                </div>
            `,
            showCancelButton: true,
            confirmButtonText: '确认通过',
            cancelButtonText: '取消',
            preConfirm: () => {
                const reviewComment = $('#reviewComment').val();
                return { reviewComment };
            }
        }).then((result) => {
            if (result.isConfirmed) {
                // 发送通过请求到后端
                $.post('../api/index.php?route=score_edit/approve', {
                    request_id: requestId,
                    review_comment: result.value.reviewComment
                })
                .done(function(response) {
                    if (response.success) {
                        Swal.fire({
                            icon: 'success',
                            title: '已通过申请',
                            text: '成绩修改申请已审核通过'
                        }).then(() => {
                            // 重新加载通知列表
                            loadNotifications();
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: '操作失败',
                            text: response.error || '未知错误'
                        });
                    }
                })
                .fail(function(xhr) {
                    Swal.fire({
                        icon: 'error',
                        title: '操作失败',
                        text: xhr.responseJSON?.error || '网络错误'
                    });
                });
            }
        });
    }

    // 驳回请求
    function rejectRequest(requestId) {
        Swal.fire({
            title: '驳回申请',
            html: `
                <div class="mb-3">
                    <label for="reviewComment" class="form-label text-start d-block">驳回理由 (必填):</label>
                    <textarea id="reviewComment" class="form-control" rows="3" placeholder="请输入驳回理由..."></textarea>
                </div>
            `,
            showCancelButton: true,
            confirmButtonText: '确认驳回',
            cancelButtonText: '取消',
            preConfirm: () => {
                const reviewComment = $('#reviewComment').val();
                if (!reviewComment.trim()) {
                    Swal.showValidationMessage('请填写驳回理由');
                    return false;
                }
                return { reviewComment };
            }
        }).then((result) => {
            if (result.isConfirmed) {
                // 发送驳回请求到后端
                $.post('../api/index.php?route=score_edit/reject', {
                    request_id: requestId,
                    review_comment: result.value.reviewComment
                })
                .done(function(response) {
                    if (response.success) {
                        Swal.fire({
                            icon: 'success',
                            title: '已驳回申请',
                            text: '成绩修改申请已驳回'
                        }).then(() => {
                            // 重新加载通知列表
                            loadNotifications();
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: '操作失败',
                            text: response.error || '未知错误'
                        });
                    }
                })
                .fail(function(xhr) {
                    Swal.fire({
                        icon: 'error',
                        title: '操作失败',
                        text: xhr.responseJSON?.error || '网络错误'
                    });
                });
            }
        });
    }
    
    // 生成分页
    function generatePagination(pageNum, totalPages) {
        if (totalPages <= 1) {
            $('#pagination').empty();
            return;
        }
        
        let html = '';
        
        // 上一页按钮
        html += `
            <li class="page-item ${pageNum === 1 ? 'disabled' : ''}">
                <a class="page-link" href="#" data-page="${pageNum - 1}">
                    <i class="fas fa-chevron-left"></i>
                </a>
            </li>
        `;
        
        // 页码按钮
        const startPage = Math.max(1, pageNum - 2);
        const endPage = Math.min(totalPages, startPage + 4);
        
        for (let i = startPage; i <= endPage; i++) {
            html += `
                <li class="page-item ${i === pageNum ? 'active' : ''}">
                    <a class="page-link" href="#" data-page="${i}">${i}</a>
                </li>
            `;
        }
        
        // 下一页按钮
        html += `
            <li class="page-item ${pageNum === totalPages ? 'disabled' : ''}">
                <a class="page-link" href="#" data-page="${pageNum + 1}">
                    <i class="fas fa-chevron-right"></i>
                </a>
            </li>
        `;
        
        $('#pagination').html(html);
        
        // 绑定分页事件 - 使用外部作用域的 currentPage 变量
        $('#pagination .page-link').click(function(e) {
            e.preventDefault();
            const page = $(this).data('page');
            if (page >= 1 && page <= totalPages) {
                currentPage = page;  // 这里正确引用外部的 currentPage 变量
                loadNotifications();
            }
        });
    }
    
    // 全部标记为已读
    function markAllAsRead() {
        $.post('../api/index.php?route=score_edit/mark_all_read')
            .done(function(response) {
                if (response.success) {
                    // 更新未读消息数量
                    updateNotificationBadge();
                } else {
                    console.error('全部标记已读失败:', response.error);
                }
            })
            .fail(function(xhr) {
                console.error('全部标记已读API调用失败:', xhr.status, xhr.statusText);
            });
    }
    
    // 搜索按钮点击事件
    $('#searchBtn').click(function() {
        typeFilter = $('#typeFilter').val();
        currentPage = 1;
        loadNotifications();
    });
    
    // 显示错误信息
    function showError(message) {
        Swal.fire({
            icon: 'error',
            title: '错误',
            text: message,
            confirmButtonText: '确定'
        });
    }
    
    // 更新全局未读消息数量
    function updateNotificationBadge() {
        if (typeof window.parent.updateNotificationBadge === 'function') {
            window.parent.updateNotificationBadge();
        }
    }
    
    // 更新批量操作按钮的可见性
    function updateBatchActionsVisibility() {
        const checkedCount = $('.notification-checkbox:checked').length;
        
        if (checkedCount > 0) {
            $('.batch-actions').show();
            $('.selected-count').text(`已选择 ${checkedCount} 项`);
        } else {
            $('.batch-actions').hide();
        }
    }
    
    // 批量通过申请
    function batchApprove() {
        const selectedIds = getSelectedNotificationIds();
        
        if (selectedIds.length === 0) {
            showError('请至少选择一项待审核的申请');
            return;
        }
        
        Swal.fire({
            title: '批量通过确认',
            text: `确定要批量通过选中的 ${selectedIds.length} 项申请吗？`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: '确定通过',
            cancelButtonText: '取消'
        }).then((result) => {
            if (result.isConfirmed) {
                // 显示加载提示
                showLoadingToast('正在处理批量通过请求...');
                
                // 发送批量通过请求
                $.ajax({
                    url: '../api/index.php?route=score_edit/batch_approve',
                    method: 'POST',
                    data: { request_ids: selectedIds.join(',') },
                    success: function(response) {
                        if (response.success) {
                            Swal.fire({
                                icon: 'success',
                                title: '操作成功',
                                text: `成功通过 ${response.approved_count || selectedIds.length} 项申请`,
                                confirmButtonText: '确定'
                            }).then(() => {
                                // 刷新通知列表
                                loadNotifications();
                            });
                        } else {
                            showError(response.error || '批量通过失败');
                        }
                    },
                    error: function(xhr) {
                        showError(xhr.responseJSON?.error || '网络错误，请稍后重试');
                    },
                    complete: function() {
                        hideLoadingToast();
                    }
                });
            }
        });
    }
    
    // 批量驳回申请
    function batchReject() {
        const selectedIds = getSelectedNotificationIds();
        
        if (selectedIds.length === 0) {
            showError('请至少选择一项待审核的申请');
            return;
        }
        
        // 弹出输入驳回原因的对话框
        Swal.fire({
            title: '批量驳回确认',
            text: `确定要批量驳回选中的 ${selectedIds.length} 项申请吗？`,
            input: 'textarea',
            inputLabel: '驳回原因',
            inputPlaceholder: '请输入驳回原因...',
            inputAttributes: {
                required: 'required'
            },
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: '确定驳回',
            cancelButtonText: '取消',
            preConfirm: (reason) => {
                if (!reason || reason.trim() === '') {
                    Swal.showValidationMessage('请输入驳回原因');
                    return false;
                }
                return reason;
            }
        }).then((result) => {
            if (result.isConfirmed) {
                const reason = result.value;
                
                // 显示加载提示
                showLoadingToast('正在处理批量驳回请求...');
                
                // 发送批量驳回请求
                $.ajax({
                    url: '../api/index.php?route=score_edit/batch_reject',
                    method: 'POST',
                    data: { 
                        request_ids: selectedIds.join(','),
                        reason: reason
                    },
                    success: function(response) {
                        if (response.success) {
                            Swal.fire({
                                icon: 'success',
                                title: '操作成功',
                                text: `成功驳回 ${response.rejected_count || selectedIds.length} 项申请`,
                                confirmButtonText: '确定'
                            }).then(() => {
                                // 刷新通知列表
                                loadNotifications();
                            });
                        } else {
                            showError(response.error || '批量驳回失败');
                        }
                    },
                    error: function(xhr) {
                        showError(xhr.responseJSON?.error || '网络错误，请稍后重试');
                    },
                    complete: function() {
                        hideLoadingToast();
                    }
                });
            }
        });
    }
    
    // 获取选中的通知ID
    function getSelectedNotificationIds() {
        const ids = [];
        $('.notification-checkbox:checked').each(function() {
            ids.push($(this).data('id'));
        });
        return ids;
    }
});
</script> 