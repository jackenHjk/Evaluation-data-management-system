<?php
/**
 * 文件名: modules/score_edit_detail.php
 * 功能描述: 成绩修改申请详情模块
 * 
 * 该文件负责:
 * 1. 显示成绩修改申请的详细信息
 * 2. 展示修改前后的成绩对比
 * 3. 提供审核通过/驳回功能
 * 4. 显示审核状态和审核意见
 * 5. 支持查看审核历史记录
 * 
 * 成绩修改申请详情模块提供直观的成绩修改前后对比，
 * 管理员和教导处可以在此页面进行审核操作，
 * 阅卷老师可以查看自己提交的申请的审核状态和结果。
 * 
 * 关联文件:
 * - controllers/ScoreEditRequestController.php: 成绩修改申请控制器
 * - api/index.php: API入口
 * - modules/notifications.php: 消息通知模块
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

// 获取请求ID
$requestId = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($requestId <= 0) {
    echo '<div class="alert alert-danger">无效的请求ID</div>';
    exit;
}

// 获取用户角色
$userRole = $_SESSION['role'] ?? '';
$userId = $_SESSION['user_id'] ?? 0;
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
                        <i class="fas fa-clipboard-check text-primary"></i>
                        成绩修改申请详情
                    </h5>
                    <div class="d-flex align-items-center">
                        <a href="?module=notifications" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left me-1"></i>返回通知列表
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <!-- 申请信息 -->
                    <div id="requestInfo">
                        <div class="text-center py-5">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <p class="mt-2">加载中...</p>
                        </div>
                    </div>
                    
                    <!-- 成绩修改详情 -->
                    <div id="scoreDetails" class="mt-4">
                        <!-- 成绩修改详情将通过AJAX加载 -->
                    </div>
                    
                    <!-- 审核表单（仅管理员和教导处可见） -->
                    <?php if (in_array($userRole, ['admin', 'teaching'])): ?>
                    <div id="reviewForm" class="mt-4" style="display: none;">
                        <div class="card">
                            <div class="card-header bg-light">
                                <h5 class="mb-0">审核操作</h5>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <label for="reviewComment" class="form-label">审核意见</label>
                                    <textarea class="form-control" id="reviewComment" rows="3" placeholder="请输入审核意见..."></textarea>
                                </div>
                                <div class="d-flex justify-content-end gap-2">
                                    <button class="btn btn-danger" id="rejectBtn">
                                        <i class="fas fa-times me-1"></i>驳回申请
                                    </button>
                                    <button class="btn btn-success" id="approveBtn">
                                        <i class="fas fa-check me-1"></i>通过申请
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* 申请信息样式 */
.request-info-card {
    border-radius: 10px;
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
    margin-bottom: 20px;
}

.request-info-header {
    background-color: #f8f9fa;
    padding: 15px;
    border-radius: 10px 10px 0 0;
    border-bottom: 1px solid #e9ecef;
}

.request-info-body {
    padding: 15px;
}

.request-info-item {
    margin-bottom: 10px;
}

.request-info-label {
    font-weight: 600;
    color: #495057;
    margin-bottom: 5px;
}

.request-info-value {
    color: #6c757d;
}

.request-status {
    display: inline-block;
    padding: 5px 10px;
    border-radius: 20px;
    font-size: 0.85rem;
    font-weight: 500;
}

.request-status.pending {
    background-color: #fff3cd;
    color: #856404;
}

.request-status.approved {
    background-color: #d4edda;
    color: #155724;
}

.request-status.rejected {
    background-color: #f8d7da;
    color: #721c24;
}

/* 已读/未读状态样式 */
.unread-indicator {
    position: absolute;
    top: 10px;
    right: 10px;
    width: 10px;
    height: 10px;
    border-radius: 50%;
    background-color: #dc3545;
    display: none;
}

.unread-card {
    position: relative;
    border-left: 4px solid #dc3545;
}

.unread-card .unread-indicator {
    display: block;
}

.mark-read-btn {
    margin-left: 10px;
    font-size: 0.85rem;
}

/* 成绩修改详情样式 */
.score-details-table {
    width: 100%;
    border-collapse: collapse;
}

.score-details-table th,
.score-details-table td {
    padding: 10px;
    text-align: center;
    border: 1px solid #dee2e6;
}

.score-details-table th {
    background-color: #f8f9fa;
    font-weight: 500;
}

.score-details-table tr:nth-child(even) {
    background-color: #f8f9fa;
}

.score-details-table tr:hover {
    background-color: #f1f3f5;
}

.score-changed {
    background-color: #fff3cd;
}

.score-old {
    text-decoration: line-through;
    color: #dc3545;
}

.score-new {
    color: #28a745;
    font-weight: 500;
}

/* 审核结果样式 */
.review-result {
    background-color: #f8f9fa;
    border-radius: 10px;
    padding: 15px;
    margin-top: 20px;
    border-left: 4px solid #6c757d;
}

.review-result.approved {
    border-left-color: #28a745;
}

.review-result.rejected {
    border-left-color: #dc3545;
}

.review-result-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 10px;
}

.review-result-title {
    font-weight: 600;
    font-size: 1.1rem;
}

.review-result-status {
    display: inline-block;
    padding: 5px 10px;
    border-radius: 20px;
    font-size: 0.85rem;
    font-weight: 500;
}

.review-result-status.approved {
    background-color: #d4edda;
    color: #155724;
}

.review-result-status.rejected {
    background-color: #f8d7da;
    color: #721c24;
}

.review-result-comment {
    color: #6c757d;
    margin-top: 10px;
    padding: 10px;
    background-color: #fff;
    border-radius: 5px;
    border: 1px solid #dee2e6;
}
</style>

<script>
$(document).ready(function() {
    // 获取请求ID
    const requestId = <?php echo $requestId; ?>;
    
    // 加载申请详情
    function loadRequestDetail() {
        $.get('../api/index.php?route=score_edit/detail', {
            request_id: requestId
        })
        .done(function(response) {
            if (response.success) {
                // 获取通知的已读/未读状态
                $.get('../api/index.php?route=notification/check_read_status', {
                    type: 'score_edit_request',
                    related_id: requestId
                })
                .done(function(readStatusResponse) {
                    if (readStatusResponse.success) {
                        displayRequestInfo(response.data.request, readStatusResponse.data.is_read);
                        displayScoreDetails(response.data.details, response.data.request);
                    } else {
                        console.error('获取已读状态失败:', readStatusResponse.error);
                        displayRequestInfo(response.data.request, true); // 默认显示为已读
                        displayScoreDetails(response.data.details, response.data.request);
                    }
                })
                .fail(function(xhr) {
                    console.error('获取已读状态请求失败:', xhr);
                    displayRequestInfo(response.data.request, true); // 默认显示为已读
                    displayScoreDetails(response.data.details, response.data.request);
                });
            } else {
                showError('加载申请详情失败：' + response.error);
            }
        })
        .fail(function(xhr) {
            showError('加载申请详情失败：' + (xhr.responseJSON?.error || '未知错误'));
        });
    }
    
    // 显示申请信息
    function displayRequestInfo(request, isRead) {
        let statusClass = '';
        let statusText = '';
        
        switch (request.status) {
            case 'pending':
                statusClass = 'pending';
                statusText = '待审核';
                break;
            case 'approved':
                statusClass = 'approved';
                statusText = '已通过';
                break;
            case 'rejected':
                statusClass = 'rejected';
                statusText = '已驳回';
                break;
        }
        
        // 判断是否为管理员或教导处角色，且申请状态为待审核
        const isAdminOrTeaching = '<?php echo $userRole; ?>' === 'admin' || '<?php echo $userRole; ?>' === 'teaching';
        const isPending = request.status === 'pending';
        
        // 确定是否可以标记为已读
        // 对于管理员/教导处角色，只有已审核的记录才能标记为已读
        const canMarkAsRead = !isRead && (!isAdminOrTeaching || !isPending);
        
        // 添加已读/未读状态和标记为已读按钮
        let markReadButton = '';
        if (canMarkAsRead) {
            markReadButton = `
                <button class="btn btn-sm btn-outline-secondary mark-read-btn" id="markReadBtn">
                    <i class="fas fa-check-circle me-1"></i>标记为已读
                </button>
            `;
        }
        
        let html = `
            <div class="request-info-card ${!isRead ? 'unread-card' : ''}">
                <div class="unread-indicator"></div>
                <div class="request-info-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">申请信息</h5>
                    <div class="d-flex align-items-center">
                        <span class="request-status ${statusClass}">${statusText}</span>
                        ${markReadButton}
                    </div>
                </div>
                <div class="request-info-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="request-info-item">
                                <div class="request-info-label">申请编号</div>
                                <div class="request-info-value">${request.id}</div>
                            </div>
                            <div class="request-info-item">
                                <div class="request-info-label">申请人</div>
                                <div class="request-info-value">${request.requester_name}</div>
                            </div>
                            <div class="request-info-item">
                                <div class="request-info-label">申请时间</div>
                                <div class="request-info-value">${formatDate(request.created_at)}</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="request-info-item">
                                <div class="request-info-label">年级班级</div>
                                <div class="request-info-value">${request.grade_name} ${request.class_name}</div>
                            </div>
                            <div class="request-info-item">
                                <div class="request-info-label">学科</div>
                                <div class="request-info-value">${request.subject_name}</div>
                            </div>
                            <div class="request-info-item">
                                <div class="request-info-label">修改原因</div>
                                <div class="request-info-value">${request.reason}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        // 如果已经审核，显示审核结果
        if (request.status === 'approved' || request.status === 'rejected') {
            html += `
                <div class="review-result ${request.status}">
                    <div class="review-result-header">
                        <div class="review-result-title">
                            <i class="fas ${request.status === 'approved' ? 'fa-check-circle' : 'fa-times-circle'} me-2"></i>
                            审核结果
                        </div>
                        <span class="review-result-status ${request.status}">
                            ${request.status === 'approved' ? '已通过' : '已驳回'}
                        </span>
                    </div>
                    <div>
                        <div><strong>审核人：</strong>${request.reviewer_name}</div>
                        <div><strong>审核时间：</strong>${formatDate(request.reviewed_at)}</div>
                        ${request.review_comment ? `<div class="review-result-comment">${request.review_comment}</div>` : ''}
                    </div>
                </div>
            `;
        } else {
            // 如果是管理员或教导处，且申请状态为待审核，显示审核表单
            if ('<?php echo $userRole; ?>' === 'admin' || '<?php echo $userRole; ?>' === 'teaching') {
                $('#reviewForm').show();
            }
        }
        
        $('#requestInfo').html(html);
        
        // 绑定标记为已读按钮事件
        if (canMarkAsRead) {
            $('#markReadBtn').click(function() {
                markAsRead(requestId);
            });
        }
    }
    
    // 显示成绩修改详情
    function displayScoreDetails(details, request) {
        if (!details || details.length === 0) {
            $('#scoreDetails').html('<div class="alert alert-info">暂无成绩修改详情</div>');
            return;
        }
        
        // 判断是否为拆分成绩
        const isSplit = request.is_split == 1 || request.is_split === '1' || request.is_split === true;
        const splitName1 = request.split_name_1 || '基础分';
        const splitName2 = request.split_name_2 || '附加分';
        
        let html = `
            <div class="card">
                <div class="card-header bg-light">
                    <h5 class="mb-0">成绩修改详情</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="score-details-table">
                            <thead>
                                <tr>
                                    <th>序号</th>
                                    <th>学生姓名</th>
                                    ${isSplit ? `
                                        <th>${splitName1}</th>
                                        <th>${splitName2}</th>
                                        <th>总分</th>
                                    ` : `
                                        <th>成绩</th>
                                    `}
                                    <th>缺考状态</th>
                                </tr>
                            </thead>
                            <tbody>
        `;
        
        details.forEach((detail, index) => {
            const oldBaseScore = detail.old_base_score;
            const newBaseScore = detail.new_base_score;
            const oldExtraScore = detail.old_extra_score;
            const newExtraScore = detail.new_extra_score;
            const oldTotalScore = detail.old_total_score;
            const newTotalScore = detail.new_total_score;
            const oldIsAbsent = detail.old_is_absent == 1 || detail.old_is_absent === '1';
            const newIsAbsent = detail.new_is_absent == 1 || detail.new_is_absent === '1';
            
            const baseScoreChanged = oldBaseScore !== newBaseScore;
            const extraScoreChanged = oldExtraScore !== newExtraScore;
            const totalScoreChanged = oldTotalScore !== newTotalScore;
            const isAbsentChanged = oldIsAbsent !== newIsAbsent;
            
            html += `
                <tr>
                    <td>${index + 1}</td>
                    <td>${detail.student_name}</td>
            `;
            
            if (isSplit) {
                // 基础分
                html += `
                    <td class="${baseScoreChanged ? 'score-changed' : ''}">
                        ${baseScoreChanged ? 
                            `<span class="score-old">${oldBaseScore || '无'}</span> → <span class="score-new">${newBaseScore || '无'}</span>` : 
                            (newBaseScore || '无')}
                    </td>
                `;
                
                // 附加分
                html += `
                    <td class="${extraScoreChanged ? 'score-changed' : ''}">
                        ${extraScoreChanged ? 
                            `<span class="score-old">${oldExtraScore || '无'}</span> → <span class="score-new">${newExtraScore || '无'}</span>` : 
                            (newExtraScore || '无')}
                    </td>
                `;
                
                // 总分
                html += `
                    <td class="${totalScoreChanged ? 'score-changed' : ''}">
                        ${totalScoreChanged ? 
                            `<span class="score-old">${oldTotalScore || '无'}</span> → <span class="score-new">${newTotalScore || '无'}</span>` : 
                            (newTotalScore || '无')}
                    </td>
                `;
            } else {
                // 非拆分成绩只显示总分
                html += `
                    <td class="${totalScoreChanged ? 'score-changed' : ''}">
                        ${totalScoreChanged ? 
                            `<span class="score-old">${oldTotalScore || '无'}</span> → <span class="score-new">${newTotalScore || '无'}</span>` : 
                            (newTotalScore || '无')}
                    </td>
                `;
            }
            
            // 缺考状态
            html += `
                <td class="${isAbsentChanged ? 'score-changed' : ''}">
                    ${isAbsentChanged ? 
                        `<span class="score-old">${oldIsAbsent ? '缺考' : '正常'}</span> → <span class="score-new">${newIsAbsent ? '缺考' : '正常'}</span>` : 
                        (newIsAbsent ? '缺考' : '正常')}
                </td>
            `;
            
            html += `</tr>`;
        });
        
        html += `
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        `;
        
        $('#scoreDetails').html(html);
    }
    
    // 标记为已读
    function markAsRead(requestId) {
        $.ajax({
            url: '../api/index.php?route=score_edit/mark_as_read',
            method: 'POST',
            data: {
                request_id: requestId
            },
            success: function(response) {
                if (response.success) {
                    // 更新界面显示
                    $('.unread-card').removeClass('unread-card');
                    $('.unread-indicator').hide();
                    $('#markReadBtn').remove();
                    
                    // 更新全局未读消息数量
                    if (window.updateNotificationBadge) {
                        window.updateNotificationBadge();
                    }
                    
                    // 显示成功提示
                    Swal.fire({
                        icon: 'success',
                        title: '已标记为已读',
                        toast: true,
                        position: 'top-end',
                        showConfirmButton: false,
                        timer: 2000
                    });
                } else {
                    showError('标记已读失败：' + response.error);
                }
            },
            error: function(xhr) {
                showError('标记已读失败：' + (xhr.responseJSON?.error || '未知错误'));
            }
        });
    }
    
    // 审核通过按钮点击事件
    $('#approveBtn').click(function() {
        const comment = $('#reviewComment').val();
        
        Swal.fire({
            title: '确认通过申请？',
            text: '通过后将更新学生成绩并重新生成统计分析数据',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: '确认通过',
            cancelButtonText: '取消',
            showLoaderOnConfirm: true,
            preConfirm: () => {
                return $.ajax({
                    url: '../api/index.php?route=score_edit/approve',
                    method: 'POST',
                    data: {
                        request_id: requestId,
                        review_comment: comment
                    }
                });
            },
            allowOutsideClick: () => !Swal.isLoading()
        }).then((result) => {
            if (result.isConfirmed && result.value.success) {
                Swal.fire({
                    icon: 'success',
                    title: '审核通过',
                    text: '成绩修改申请已通过，成绩已更新',
                    confirmButtonText: '确定'
                }).then(() => {
                    // 重新加载申请详情
                    loadRequestDetail();
                });
            } else if (result.value && !result.value.success) {
                Swal.fire({
                    icon: 'error',
                    title: '审核失败',
                    text: result.value.error || '审核申请失败',
                    confirmButtonText: '确定'
                });
            }
        });
    });
    
    // 审核驳回按钮点击事件
    $('#rejectBtn').click(function() {
        const comment = $('#reviewComment').val();
        
        if (!comment.trim()) {
            Swal.fire({
                icon: 'warning',
                title: '请填写驳回原因',
                text: '驳回申请时必须填写驳回原因',
                confirmButtonText: '确定'
            });
            return;
        }
        
        Swal.fire({
            title: '确认驳回申请？',
            text: '驳回后将通知申请人',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: '确认驳回',
            cancelButtonText: '取消',
            showLoaderOnConfirm: true,
            preConfirm: () => {
                return $.ajax({
                    url: '../api/index.php?route=score_edit/reject',
                    method: 'POST',
                    data: {
                        request_id: requestId,
                        review_comment: comment
                    }
                });
            },
            allowOutsideClick: () => !Swal.isLoading()
        }).then((result) => {
            if (result.isConfirmed && result.value.success) {
                Swal.fire({
                    icon: 'success',
                    title: '审核驳回',
                    text: '成绩修改申请已驳回',
                    confirmButtonText: '确定'
                }).then(() => {
                    // 重新加载申请详情
                    loadRequestDetail();
                });
            } else if (result.value && !result.value.success) {
                Swal.fire({
                    icon: 'error',
                    title: '审核失败',
                    text: result.value.error || '审核申请失败',
                    confirmButtonText: '确定'
                });
            }
        });
    });
    
    // 格式化日期
    function formatDate(dateString) {
        if (!dateString) return '无';
        
        const date = new Date(dateString);
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        const hours = String(date.getHours()).padStart(2, '0');
        const minutes = String(date.getMinutes()).padStart(2, '0');
        const seconds = String(date.getSeconds()).padStart(2, '0');
        
        return `${year}-${month}-${day} ${hours}:${minutes}:${seconds}`;
    }
    
    // 显示错误信息
    function showError(message) {
        Swal.fire({
            icon: 'error',
            title: '错误',
            text: message,
            confirmButtonText: '确定'
        });
    }
    
    // 初始加载
    loadRequestDetail();
});
</script>