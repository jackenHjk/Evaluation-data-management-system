<?php
/**
 * 文件名: comprehensive_analytics.php
 * 功能描述: 全科统计分析模块
 * 
 * 该模块功能:
 * 1. 按年级查看各班级所有学科的成绩统计分析
 * 2. 统计全优生、优良生数据并展示名单
 * 3. 可自由选择要统计的学科
 */
?>

<style>
/* 成绩等级样式 */
.score-level {
    display: inline-block;
    padding: 2px 6px;
    border-radius: 3px;
    font-size: 0.9em;
}
.level-excellent { background-color: #f0f9ff; color: #0369a1; }
.level-good { background-color: #f0fdf4; color: #15803d; }
.level-pass { background-color: #fef9c3; color: #854d0e; }
.level-fail { background-color: #fee2e2; color: #991b1b; }
.level-absent { background-color: #f3f4f6; color: #4b5563; }

/* 固定表头样式 */
.table-fixed-header {
    position: relative;
    max-height: 70vh;
    overflow-y: auto;
}
.table-fixed-header table {
    width: 100%;
    border-collapse: collapse;
}
.table-fixed-header thead {
    position: sticky;
    top: 0;
    z-index: 1;
    background-color: #f8f9fa;
}
.table-fixed-header th {
    background-color: #f8f9fa;
    box-shadow: 0 2px 2px -1px rgba(0, 0, 0, 0.1);
}

/* 统计表格样式 */
.analytics-table {
    width: max-content !important;
    min-width: 100% !important;
    margin: 0 auto;
    font-size: 12px;
    border-collapse: separate;
    border-spacing: 0;
    table-layout: fixed;
}
.analytics-table th,
.analytics-table td {
    border: 1px solid #ddd;
    padding: 4px 2px !important;
    text-align: center;
    vertical-align: middle;
    overflow: hidden !important;
    text-overflow: ellipsis !important;
    white-space: nowrap !important;
    box-sizing: border-box !important;
}
/* 班级列样式 */
.analytics-table .col-class {
    width: 80px !important;
}
/* 学科列样式 */
.analytics-table .col-subject {
    width: 60px !important;
}
/* 人数列样式 */
.analytics-table .col-count {
    width: 50px !important;
}
/* 总分平均分列样式 */
.analytics-table .col-score {
    width: 70px !important;
}
/* 数据分布列 - 通过CSS变量确保完全相同的宽度 */
.analytics-table .col-distribution {
    width: 40px !important;
}
/* 最高分最低分列 */
.analytics-table .col-max-min {
    width: 60px !important;
}
/* 及格率优秀率列 */
.analytics-table .col-rate {
    width: 70px !important;
}

.analytics-table th {
    background-color: #f8f9fa;
    font-weight: 500;
    white-space: normal !important;
    word-break: break-all !important;
    word-wrap: break-word !important;
    line-height: 1.2 !important;
}
.analytics-title {
    text-align: center;
    margin-bottom: 20px;
    font-size: 1.75rem !important;
    font-weight: 500;
    line-height: 1.2;
}
.table-container {
    margin-top: 20px;
    overflow-x: auto;
    max-width: 100% !important;
    width: 100% !important;
}
/* 分数段样式 */
.score-range {
    font-size: 12px;
    line-height: 1.2;
}
/* 合计行样式 */
.total-row td {
    font-weight: 600 !important;
    background-color: #f8f9fa !important;
}
.analytics-footer {
    font-size: 12px;
    color: rgb(245, 131, 60);
    margin-top: 20px;
    text-align: left;
    margin-left: 10px;
}

/* 班级行交替背景色 */
.class-group:nth-child(odd) {
    background-color: #f9f9f9;
}
.class-group:nth-child(even) {
    background-color: #ffffff;
}

/* 班级分隔线 */
.class-group {
    border-bottom: 2px solid #aaa;
}

/* 学科文本颜色 - 基于学科ID */
/* 预设10种高对比度的颜色 */
.subject-color-1 { color: #1e40af; } /* 深蓝色 */
.subject-color-2 { color: #047857; } /* 深绿色 */
.subject-color-3 { color: #b45309; } /* 棕色 */
.subject-color-4 { color: #7e22ce; } /* 紫色 */
.subject-color-5 { color: #be185d; } /* 深粉色 */
.subject-color-6 { color: #0e7490; } /* 青色 */
.subject-color-7 { color: #b91c1c; } /* 红色 */
.subject-color-8 { color: #4d7c0f; } /* 橄榄绿色 */
.subject-color-9 { color: #6d28d9; } /* 靛蓝色 */
.subject-color-10 { color: #7c2d12; } /* 褐色 */

/* 班级列始终使用黑色 */
.analytics-table td:first-child {
    color: #000000 !important;
    font-weight: 500;
}
</style>

<div class="container-fluid px-4">
    <div class="d-flex align-items-center justify-content-between mb-4">
        <h4 class="mb-0">
            <i class="fas fa-chart-pie text-primary me-2"></i>全科统计分析
        </h4>
    </div>
    
    <div class="row">
        <div class="col-12">
            <div class="card mb-4" style="border: none; border-radius: 16px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); background: #ffffff;">
                <div class="card-header d-flex justify-content-between align-items-center" style="background: transparent; border-bottom: 1px solid rgba(0,0,0,0.05); padding: 1.5rem;">
                    <div>
                        <i class="fas fa-filter me-1"></i>
                        <span style="font-weight: 600; color: #2c3e50;">筛选条件</span>
                    </div>
                </div>
                <div class="card-body" style="padding: 1.5rem;">
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="grade-select" class="form-label">选择年级</label>
                            <select class="form-select" id="grade-select">
                                <option value="">请选择年级</option>
                                <!-- 年级选项将通过JS动态加载 -->
                            </select>
                        </div>
                        <div class="col-md-8 mb-3">
                            <label class="form-label">选择学科</label>
                            <div id="subject-checkboxes" class="d-flex flex-wrap gap-2">
                                <!-- 学科复选框将通过JS动态加载 -->
                                <span class="text-muted">请先选择年级</span>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-12">
                            <button id="analyze-btn" class="btn btn-primary" disabled>
                                <i class="fas fa-search me-1"></i>查询
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- 班级成绩统计表格 -->
    <div class="row" id="class-analytics-container" style="display: none;">
        <div class="col-12">
            <div class="card mb-4" style="border: none; border-radius: 16px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); background: #ffffff;">
                <div class="card-header" style="background: transparent; border-bottom: 1px solid rgba(0,0,0,0.05); padding: 1.5rem;">
                    <i class="fas fa-table me-1"></i>
                    <span style="font-weight: 600; color: #2c3e50;">班级成绩统计</span>
                </div>
                <div class="card-body" style="padding: 1.5rem;">
                    <div class="table-container">
                        <div id="analyticsTable">
                            <!-- 统计分析表将通过JavaScript动态生成 -->
                        </div>
                        <div class="analytics-footer">
                            <p>为"/"的表示还未完成数据录入。"合计"行中涉及平均值计算的，只计算已完成数据录入的班级，未完成的不参与计算。</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- 全优生/优良生统计 -->
    <div class="row" id="excellent-good-summary-container" style="display: none;">
        <div class="col-12">
            <div class="card mb-4" style="border: none; border-radius: 16px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); background: #ffffff;">
                <div class="card-header" style="background: transparent; border-bottom: 1px solid rgba(0,0,0,0.05); padding: 1.5rem;">
                    <i class="fas fa-medal me-1"></i>
                    <span style="font-weight: 600; color: #2c3e50;">全优生/优良生统计</span>
                </div>
                <div class="card-body" style="padding: 1.5rem;">
                    <div class="table-responsive table-fixed-header">
                        <table class="table table-bordered table-hover table-striped" id="excellent-good-table">
                            <thead class="table-light">
                                <tr>
                                    <th class="text-center">班级</th>
                                    <th class="text-center">班级总人数</th>
                                    <th class="text-center">全优生人数</th>
                                    <th class="text-center">优良生人数</th>
                                    <th class="text-center">全科及格率</th>
                                    <th class="text-center">全科优秀率</th>
                                </tr>
                            </thead>
                            <tbody id="excellent-good-body">
                                <!-- 全优生/优良生统计数据将通过JS动态加载 -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- 全优生/优良生详细名单 -->
    <div class="row" id="student-list-container" style="display: none;">
        <div class="col-12">
            <div class="card mb-4" style="border: none; border-radius: 16px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); background: #ffffff;">
                <div class="card-header d-flex justify-content-between align-items-center" style="background: transparent; border-bottom: 1px solid rgba(0,0,0,0.05); padding: 1.5rem;">
                    <div>
                        <i class="fas fa-list me-1"></i>
                        <span id="student-list-title" style="font-weight: 600; color: #2c3e50;">全优生名单</span>
                    </div>
                    <div class="btn-group" role="group">
                        <button type="button" class="btn btn-sm btn-primary active" id="show-excellent">全优生</button>
                        <button type="button" class="btn btn-sm btn-outline-primary" id="show-good">优良生</button>
                    </div>
                </div>
                <div class="card-body" style="padding: 1.5rem;">
                    <div class="table-responsive table-fixed-header">
                        <table class="table table-bordered table-hover table-striped" id="student-list-table">
                            <thead class="table-light">
                                <tr id="student-list-header">
                                    <th class="text-center">班级</th>
                                    <th class="text-center">学号</th>
                                    <th class="text-center">姓名</th>
                                    <!-- 学科表头将通过JS动态加载 -->
                                </tr>
                            </thead>
                            <tbody id="student-list-body">
                                <!-- 学生详细名单将通过JS动态加载 -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- 加载中遮罩 -->
<div id="loading-overlay" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(255,255,255,0.7); z-index: 9999; display: flex; justify-content: center; align-items: center;">
    <div class="text-center">
        <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Loading...</span>
        </div>
        <div class="mt-2">数据加载中，请稍候...</div>
    </div>
</div>

<script>
$(document).ready(function() {
    // 全局变量
    let selectedGradeId = '';
    let availableSubjects = [];
    let selectedSubjects = [];
    let currentView = 'excellent'; // 'excellent' 或 'good'
    let pendingEditClasses = {}; // 存储待审核的班级
    
    // 获取当前项目ID
    $.get('api/index.php?route=settings/current', function(response) {
        if (response.success && response.data) {
            window.currentSettingId = response.data.id;
            window.currentProject = response.data;  // 存储项目信息
        } else {
            console.warn('获取当前项目信息失败');
        }
    });
    
    // 初始化加载年级列表
    loadGrades();
    
    // 年级选择变化事件
    $('#grade-select').on('change', function() {
        selectedGradeId = $(this).val();
        if (selectedGradeId) {
            loadSubjects(selectedGradeId);
            $('#analyze-btn').prop('disabled', false);
        } else {
            $('#subject-checkboxes').html('<span class="text-muted">请先选择年级</span>');
            $('#analyze-btn').prop('disabled', true);
            hideResults();
        }
    });
    
    // 分析按钮点击事件
    $('#analyze-btn').on('click', function() {
        // 获取选中的学科
        selectedSubjects = [];
        $('input[name="subject"]:checked').each(function() {
            selectedSubjects.push($(this).val());
        });
        
        if (selectedSubjects.length === 0) {
            Swal.fire({
                icon: 'warning',
                title: '请选择学科',
                text: '请至少选择一个学科进行分析'
            });
            return;
        }
        
        // 检查是否有待审核记录
        checkPendingScoreEdits(selectedGradeId, selectedSubjects)
            .then(() => {
                // 无论是否有待审核记录，都继续加载数据
                loadAnalyticsData();
            })
            .catch(error => {
                console.error('检查待审核状态失败:', error);
                // 即使检查失败也继续加载数据
                loadAnalyticsData();
            });
    });
    
    // 全优生/优良生切换按钮
    $('#show-excellent').on('click', function() {
        $(this).addClass('btn-primary').removeClass('btn-outline-primary');
        $('#show-good').addClass('btn-outline-primary').removeClass('btn-primary');
        currentView = 'excellent';
        $('#student-list-title').text('全优生名单');
        if (selectedGradeId && selectedSubjects.length > 0) {
            loadStudentList(currentView);
        }
    });
    
    $('#show-good').on('click', function() {
        $(this).addClass('btn-primary').removeClass('btn-outline-primary');
        $('#show-excellent').addClass('btn-outline-primary').removeClass('btn-primary');
        currentView = 'good';
        $('#student-list-title').text('优良生名单');
        if (selectedGradeId && selectedSubjects.length > 0) {
            loadStudentList(currentView);
        }
    });
    
    // 加载年级列表
    function loadGrades() {
        showLoading();
        $.ajax({
            url: 'api/index.php?route=grade/getList',
            method: 'GET',
            dataType: 'json',
            success: function(response) {
                hideLoading();
                if (response.success) {
                    const grades = response.data;
                    let options = '<option value="">请选择年级</option>';
                    
                    // 使用Map对象去重，按年级编码进行去重
                    const uniqueGrades = new Map();
                    grades.forEach(function(grade) {
                        if (grade.status == 1) { // 只显示启用的年级
                            // 使用年级编码作为唯一标识符
                            if (!uniqueGrades.has(grade.grade_code)) {
                                uniqueGrades.set(grade.grade_code, grade);
                            }
                        }
                    });
                    
                    // 将Map对象转换为数组并按年级编码排序
                    const sortedGrades = Array.from(uniqueGrades.values()).sort((a, b) => 
                        parseInt(a.grade_code) - parseInt(b.grade_code)
                    );
                    
                    // 生成选项
                    sortedGrades.forEach(function(grade) {
                        options += `<option value="${grade.id}">${grade.grade_name}</option>`;
                    });
                    
                    $('#grade-select').html(options);
                } else {
                    showError('加载年级列表失败：' + (response.error || '未知错误'));
                }
            },
            error: function() {
                hideLoading();
                showError('加载年级列表失败，请检查网络连接');
            }
        });
    }
    
    // 检查单个学科是否有待审核记录
    function checkSingleSubjectPendingEdits(gradeId, subjectId) {
        return new Promise((resolve, reject) => {
            $.ajax({
                url: 'api/index.php',
                data: {
                    route: 'score_edit/check_pending_by_grade',
                    grade_id: gradeId,
                    subject_id: subjectId
                },
                method: 'GET',
                dataType: 'json',
                timeout: 5000, // 添加超时设置
                success: function(response) {
                    if (response.success) {
                        resolve({
                            hasPendingRequest: response.has_pending_request,
                            count: response.count || 0,
                            pending_classes: response.pending_classes || []
                        });
                    } else {
                        console.warn('检查待审核状态失败：' + (response.error || '未知错误'));
                        // 即使API返回失败也允许用户继续操作
                        resolve({ hasPendingRequest: false, count: 0, pending_classes: [] });
                    }
                },
                error: function(xhr, status, error) {
                    console.warn('检查待审核状态请求失败：', status, error);
                    // 即使API请求失败也允许用户继续操作
                    resolve({ hasPendingRequest: false, count: 0, pending_classes: [] });
                }
            });
        });
    }
    
    // 检查待审核成绩修改申请
    function checkPendingScoreEdits(gradeId, subjectIds) {
        return new Promise((resolve, reject) => {
            if (!gradeId || !subjectIds || subjectIds.length === 0) {
                resolve({ hasPendingRequest: false, subjects: [] });
                return;
            }
            
            // 获取当前项目ID
            const settingId = window.currentSettingId;
            if (!settingId) {
                console.warn('未找到当前项目ID');
                resolve({ hasPendingRequest: false, subjects: [] });
                return;
            }
            
            // 为每个学科创建一个检查请求
            const checkPromises = subjectIds.map(subjectId => 
                checkSingleSubjectPendingEdits(gradeId, subjectId)
            );
            
            // 等待所有检查完成
            Promise.all(checkPromises)
                .then(results => {
                    // 如果任一学科有待审核记录，则返回true
                    const hasPending = results.some(result => result.hasPendingRequest);
                    
                    // 收集所有待审核的班级
                    const pendingClasses = [];
                    const pendingSubjectIds = [];
                    
                    results.forEach((result, index) => {
                        if (result.hasPendingRequest) {
                            pendingSubjectIds.push(subjectIds[index]);
                            if (result.pending_classes && result.pending_classes.length > 0) {
                                pendingClasses.push(...result.pending_classes);
                            }
                        }
                    });
                    
                    // 去重班级列表
                    const uniqueClasses = [...new Set(pendingClasses)];
                    
                    if (hasPending) {
                        // 获取待审核学科名称
                        const pendingSubjectNames = [];
                        pendingSubjectIds.forEach(id => {
                            const subject = availableSubjects.find(s => s.id == id);
                            if (subject) {
                                pendingSubjectNames.push(subject.subject_name);
                            }
                        });
                        
                        // 构建待审核班级文本
                        const pendingClassesText = uniqueClasses.length > 0 ? 
                            `，涉及班级：${uniqueClasses.join('、')}` : '';
                        
                        // 显示提示
                        Swal.fire({
                            icon: 'warning',
                            title: '待审核提醒',
                            text: `该年级${pendingSubjectNames.join('、')}学科存在待审核的成绩修改申请${pendingClassesText}，部分数据可能不准确。请先完成审核后再查看。`,
                            confirmButtonText: '我知道了'
                        });
                    }
                    
                    resolve({
                        hasPendingRequest: hasPending,
                        pendingSubjectIds: pendingSubjectIds,
                        pending_classes: uniqueClasses
                    });
                })
                .catch(error => {
                    console.warn('检查待审核状态失败:', error);
                    // 即使检查失败也允许用户继续操作
                    resolve({ hasPendingRequest: false, subjects: [] });
                });
        });
    }
    
    // 加载学科列表
    function loadSubjects(gradeId) {
        //console.log('开始加载学科列表，年级ID:', gradeId);
        showLoading();
        $('#subject-checkboxes').html('<div class="spinner-border spinner-border-sm text-primary" role="status"><span class="visually-hidden">Loading...</span></div><span class="text-muted">加载中...</span>');
        
        $.ajax({
            url: 'api/index.php?route=subject/getGradeSubjects',
            method: 'GET',
            data: { grade_id: gradeId },
            dataType: 'json',
            success: function(response) {
                //console.log('学科列表API响应:', response);
                hideLoading();
                if (response.success) {
                    //console.log('学科数据:', response.data);
                    availableSubjects = response.data.filter(subject => subject.status == 1);
                    //console.log('过滤后的可用学科:', availableSubjects);
                    renderSubjectCheckboxes();
                } else {
                    $('#subject-checkboxes').html('<span class="text-danger">加载失败</span>');
                    showError('加载学科列表失败：' + (response.error || '未知错误'));
                    console.error('API返回错误:', response.error);
                }
            },
            error: function(xhr, status, error) {
                console.error('学科列表请求失败:', {
                    status: status,
                    error: error,
                    response: xhr.responseText
                });
                hideLoading();
                $('#subject-checkboxes').html('<span class="text-danger">加载失败</span>');
                showError('加载学科列表失败，请检查网络连接');
            }
        });
    }
    
    // 渲染学科复选框
    function renderSubjectCheckboxes() {
        if (availableSubjects.length === 0) {
            $('#subject-checkboxes').html('<span class="text-muted">没有可用学科</span>');
            return;
        }
        
        let checkboxesHtml = '';
        availableSubjects.forEach(function(subject) {
            checkboxesHtml += `
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="checkbox" name="subject" id="subject-${subject.id}" value="${subject.id}" checked>
                    <label class="form-check-label" for="subject-${subject.id}">${subject.subject_name}</label>
                </div>
            `;
        });
        
        $('#subject-checkboxes').html(checkboxesHtml);
        
        // 添加全选/取消全选按钮
        $('#subject-checkboxes').append(`
            <div class="ms-2">
                <button type="button" class="btn btn-sm btn-outline-secondary" id="select-all-subjects">全选</button>
                <button type="button" class="btn btn-sm btn-outline-secondary ms-1" id="deselect-all-subjects">取消全选</button>
            </div>
        `);
        
        // 绑定全选/取消全选事件
        $('#select-all-subjects').on('click', function() {
            $('input[name="subject"]').prop('checked', true);
        });
        
        $('#deselect-all-subjects').on('click', function() {
            $('input[name="subject"]').prop('checked', false);
        });
    }
    
    // 加载分析数据
    function loadAnalyticsData() {
        if (!selectedGradeId || selectedSubjects.length === 0) {
            console.warn('未选择年级或学科，无法加载数据');
            return;
        }
        
        console.log('开始加载分析数据:', {
            grade_id: selectedGradeId,
            subject_ids: selectedSubjects
        });
        
        showLoading();
        
        // 1. 加载班级成绩统计数据
        //console.log('发送请求: api/index.php?route=comprehensive/getClassAnalytics');
        $.ajax({
            url: 'api/index.php?route=comprehensive/getClassAnalytics',
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({
                grade_id: selectedGradeId,
                subject_ids: selectedSubjects
            }),
            dataType: 'json',
            success: function(response) {
                //console.log('班级成绩统计API响应:', response);
                if (response.success) {
                    //console.log('班级成绩数据:', response.data);
                    renderClassAnalytics(response.data);
                    $('#class-analytics-container').show();
                } else {
                    console.error('API返回错误:', response.error);
                    showError('加载班级成绩统计失败：' + (response.error || '未知错误'));
                }
                
                // 2. 加载全优生/优良生统计数据
                loadExcellentGoodSummary();
            },
            error: function(xhr, status, error) {
                console.error('班级成绩统计请求失败:', {
                    status: status,
                    error: error,
                    response: xhr.responseText
                });
                hideLoading();
                showError('加载班级成绩统计失败，请检查网络连接');
            }
        });
    }
    
    // 加载全优生/优良生统计数据
    function loadExcellentGoodSummary() {
        $.ajax({
            url: 'api/index.php?route=comprehensive/getExcellentGoodSummary',
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({
                grade_id: selectedGradeId,
                subject_ids: selectedSubjects
            }),
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    renderExcellentGoodSummary(response.data);
                    $('#excellent-good-summary-container').show();
                } else {
                    showError('加载全优生/优良生统计失败：' + (response.error || '未知错误'));
                }
                
                // 3. 加载学生详细名单
                loadStudentList(currentView);
            },
            error: function() {
                hideLoading();
                showError('加载全优生/优良生统计失败，请检查网络连接');
            }
        });
    }
    
    // 加载学生详细名单
    function loadStudentList(type) {
        // 显示加载中状态
        $('#student-list-body').html('<tr><td colspan="3" class="text-center"><div class="spinner-border spinner-border-sm text-primary" role="status"></div> 加载中...</td></tr>');
        $('#student-list-container').show();
        
        $.ajax({
            url: 'api/index.php?route=comprehensive/getStudentList',
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({
                grade_id: selectedGradeId,
                subject_ids: selectedSubjects,
                type: type // 'excellent' 或 'good'
            }),
            dataType: 'json',
            success: function(response) {
                hideLoading();
                if (response.success) {
                    // 调试输出
                    //console.log('学生列表数据:', response.data);
                    
                    // 检查数据结构
                    if (response.data.students && response.data.students.length > 0) {
                        const firstStudent = response.data.students[0];
                       // console.log('第一个学生数据:', firstStudent);
                        //console.log('成绩数据:', firstStudent.scores);
                        
                        // 更新学科信息
                        if (response.data.subjects) {
                            //console.log('学科信息:', response.data.subjects);
                        }
                    }
                    
                    renderStudentList(response.data, type);
                    $('#student-list-container').show();
                } else {
                    $('#student-list-body').html('<tr><td colspan="3" class="text-center text-danger">加载失败：' + (response.error || '未知错误') + '</td></tr>');
                    showError('加载学生名单失败：' + (response.error || '未知错误'));
                }
            },
            error: function(xhr) {
                hideLoading();
                $('#student-list-body').html('<tr><td colspan="3" class="text-center text-danger">加载失败：网络错误</td></tr>');
                showError('加载学生名单失败，请检查网络连接');
                console.error('加载学生名单失败:', xhr);
            }
        });
    }
    
    // 渲染班级成绩统计表格
    function renderClassAnalytics(data) {
        //console.log('开始渲染班级成绩统计表格，数据:', data);
        
        // 检查数据是否为空
        if (!data || !data.classes || data.classes.length === 0) {
            $('#analyticsTable').html('<div class="alert alert-warning">暂无班级成绩统计数据</div>');
            return;
        }
        
        // 获取年级名称
        const gradeName = $('#grade-select option:selected').text();
        //console.log('当前年级:', gradeName);
        
        // 获取当前项目信息
       // console.log('请求项目信息...');
        $.get('api/index.php?route=settings/current', function(response) {
           // console.log('项目信息响应:', response);
            if (response.success && response.data) {
                const projectInfo = response.data;
                
                // 创建表格标题
                const title = `
                    <div class="analytics-title">
                        ${projectInfo.school_name || ''}${projectInfo.current_semester || ''}${gradeName}${projectInfo.project_name || ''}（全科）统计分析表
                    </div>
                `;
                
                // 定义分数区间
                const scoreRanges = [
                    '100',        // [99.5, 100]
                    '99.5-95',    // [94.5, 99.5)
                    '94.5-90',    // [89.5, 94.5)
                    '89.5-85',    // [84.5, 89.5)
                    '84.5-80',    // [79.5, 84.5)
                    '79.5-75',    // [74.5, 79.5)
                    '74.5-70',    // [69.5, 74.5)
                    '69.5-65',    // [64.5, 69.5)
                    '64.5-60',    // [59.5, 64.5)
                    '59.5-55',    // [54.5, 59.5)
                    '54.5-50',    // [49.5, 54.5)
                    '49.5-40',    // [40, 49.5)
                    '40以下'       // [0, 40)
                ];
                
                // 创建学科ID到颜色映射
                const subjectColors = {};
                let colorIndex = 1;
                
                // 先收集所有学科ID
                if (data.classes && data.classes.length > 0) {
                    data.classes.forEach(classItem => {
                        if (classItem.subjects && classItem.subjects.length > 0) {
                            classItem.subjects.forEach(subject => {
                                const subjectId = subject.subject_id;
                                if (subjectId && !subjectColors[subjectId]) {
                                    // 分配颜色，最多10种颜色循环使用
                                    subjectColors[subjectId] = `subject-color-${colorIndex}`;
                                    colorIndex = colorIndex < 10 ? colorIndex + 1 : 1;
                                }
                            });
                        }
                    });
                }
                
                // 生成表格HTML，使用class属性代替colgroup
                let tableHtml = `
                    ${title}
                    <table class="analytics-table">
                        <tr>
                            <th class="col-class" rowspan="2">班级</th>
                            <th class="col-subject" rowspan="2">学科</th>
                            <th class="col-count" rowspan="2">总人数</th>
                            <th class="col-count" rowspan="2">到考人数</th>
                            <th class="col-score" rowspan="2">总分</th>
                            <th class="col-score" rowspan="2">平均分</th>
                            <th colspan="13">数据分布</th>
                            <th class="col-max-min" rowspan="2">最高分</th>
                            <th class="col-max-min" rowspan="2">最低分</th>
                            <th class="col-rate" rowspan="2">及格率</th>
                            <th class="col-rate" rowspan="2">优秀率</th>
                        </tr>
                        <tr>
                            ${scoreRanges.map(range => `
                                <th class="col-distribution">${range.replace('-', '<br>/<br>')}</th>
                            `).join('')}
                        </tr>
                `;
                
                // 处理班级数据
                if (data.classes && data.classes.length > 0) {
                    // 对班级进行排序
                    const sortedClasses = [...data.classes].sort((a, b) => {
                        const numA = parseInt(a.class_name.match(/\d+/)?.[0] || '0');
                        const numB = parseInt(b.class_name.match(/\d+/)?.[0] || '0');
                        return numA - numB;
                    });
                    
                    // 按学科分组的数据
                    const subjectData = {};
                    
                    // 处理每个班级的数据，按班级分组
                    let classGroupId = 0;
                    sortedClasses.forEach(classItem => {
                        // 为每个班级创建一个分组
                        classGroupId++;
                        
                        if (classItem.subjects && classItem.subjects.length > 0) {
                            // 开始班级分组
                            tableHtml += `<tbody class="class-group" id="class-group-${classGroupId}">`;
                            
                            // 获取班级的学科数量
                            const subjectCount = classItem.subjects.length;
                            
                            // 处理该班级的每个学科
                            classItem.subjects.forEach((subject, index) => {
                                // 获取学科信息
                                const subjectId = subject.subject_id;
                                const subjectName = subject.subject_name;
                                
                                // 获取学科对应的CSS类
                                const subjectColorClass = subjectColors[subjectId] || '';
                                
                                if (subjectName) {
                                    // 生成学科行HTML
                                    let rowHtml = '';
                                    
                                    // 所有行都包含班级名称，不再合并班级列
                                    rowHtml = `
                                        <tr class="${subjectColorClass}">
                                            <td class="${subjectColorClass}">${classItem.class_name || ''}</td>
                                            <td class="${subjectColorClass}">${subject.subject_name || ''}</td>
                                            <td class="${subjectColorClass}">${classItem.total_students || '/'}</td>
                                    `;
                                    
                                    // 检查是否有有效数据
                                    const hasData = subject && subject.attended_students > 0;
                                    
                                    // 如果没有数据，显示"/"
                                    const displayValue = (value) => hasData ? value : '/';
                                    
                                    // 格式化分数显示
                                    const formatScore = (score) => {
                                        if (score === null || score === undefined) return '0';
                                        if (score === '') return '0';
                                        
                                        const num = parseFloat(score);
                                        if (isNaN(num)) return '0';
                                        
                                        // 如果是整数，直接返回整数
                                        if (Number.isInteger(num)) {
                                            return num.toString();
                                        }
                                        // 否则保留一位小数，但如果小数部分为0，则去掉小数部分
                                        return parseFloat(num.toFixed(1)).toString();
                                    };
                                    
                                    // 添加到考人数和成绩数据
                                    rowHtml += `
                                        <td class="${subjectColorClass}">${displayValue(subject.attended_students)}</td>
                                        <td class="${subjectColorClass}">${displayValue(formatScore(subject.total_score))}</td>
                                        <td class="${subjectColorClass}">${displayValue(subject.average_score != null ? formatScore(subject.average_score) : '/')}</td>
                                    `;
                                    
                                    // 处理分数分布
                                    let distribution = {};
                                    try {
                                        distribution = typeof subject.score_distribution === 'string' ? 
                                            JSON.parse(subject.score_distribution) : 
                                            subject.score_distribution || {};
                                    } catch (e) {
                                        console.error('解析分数分布数据失败:', e);
                                    }
                                    
                                    // 添加分数分布数据
                                    scoreRanges.forEach(range => {
                                        rowHtml += `<td class="${subjectColorClass}">${displayValue(distribution[range] || 0)}</td>`;
                                    });
                                    
                                    // 添加最高分、最低分、及格率和优秀率
                                    rowHtml += `
                                        <td class="${subjectColorClass}">${displayValue(formatScore(subject.max_score))}</td>
                                        <td class="${subjectColorClass}">${displayValue(formatScore(subject.min_score))}</td>
                                        <td class="${subjectColorClass}">${displayValue(subject.pass_rate ? formatRate(parseFloat(subject.pass_rate)) : '/')}</td>
                                        <td class="${subjectColorClass}">${displayValue(subject.excellent_rate ? formatRate(parseFloat(subject.excellent_rate)) : '/')}</td>
                                    </tr>`;
                                    
                                    // 添加到表格HTML
                                    tableHtml += rowHtml;
                                    
                                    // 收集学科数据用于生成合计行 - 不再需要
                                    // if (!subjectData[subjectId]) {
                                    //     subjectData[subjectId] = {
                                    //         subject_id: subjectId,
                                    //         subject_name: subjectName,
                                    //         classes: [],
                                    //         colorClass: subjectColorClass
                                    //     };
                                    // }
                                    // subjectData[subjectId].classes.push({
                                    //     ...subject,
                                    //     class_name: classItem.class_name,
                                    //     total_students: classItem.total_students
                                    // });
                                }
                            });
                            
                            // 结束班级分组
                            tableHtml += '</tbody>';
                        }
                    });
                    
                    // 不再添加各学科合计行
                    // tableHtml += '<tbody>';
                    // Object.keys(subjectData).forEach(subjectId => {
                    //     const subject = subjectData[subjectId];
                    //     const totals = calculateSubjectTotals(subject.classes);
                    //     tableHtml += generateSubjectTotalRow(totals, subject.subject_name + '合计', scoreRanges, subject.colorClass);
                    // });
                    // tableHtml += '</tbody>';
                } else {
                    tableHtml += `<tr><td colspan="${22}" class="text-center">暂无数据</td></tr>`;
                }
                
                // 结束表格
                tableHtml += '</table>';
                
                // 更新表格内容
                $('#analyticsTable').html(tableHtml);
                
                // 显示结果容器
                $('#class-analytics-container').show();
            }
        });
    }
    
    // 计算学科合计数据
    function calculateSubjectTotals(classes) {
        // 初始化合计数据
        const totals = {
            subject_id: null,
            subject_name: '',
            total_students: 0,
            attended_students: 0,
            total_score: 0,
            max_score: -Infinity,
            min_score: Infinity,
            distribution: {},
            pass_count: 0,
            excellent_count: 0,
            valid_class_count: 0
        };

        // 遍历每个班级
        classes.forEach(item => {
            // 检查是否为有效数据（不是"/"）
            const hasValidData = item && item.attended_students > 0;
            if (!hasValidData) return;

            // 增加有效班级计数
            totals.valid_class_count++;
            
            // 累加基础数据
            totals.total_students += parseInt(item.total_students) || 0;
            totals.attended_students += parseInt(item.attended_students) || 0;
            totals.total_score += parseFloat(item.total_score) || 0;
            
            // 更新最高分最低分
            totals.max_score = Math.max(totals.max_score, parseFloat(item.max_score) || -Infinity);
            totals.min_score = Math.min(totals.min_score, parseFloat(item.min_score) || Infinity);
            
            // 获取班级的及格人数
            let classPassCount = 0;
            if (item.pass_count !== undefined) {
                // 如果有直接提供的及格人数，直接使用
                classPassCount = parseInt(item.pass_count) || 0;
            } else if (item.pass_rate !== undefined) {
                // 如果只有及格率，通过到考人数计算及格人数
                const passRate = parseFloat(item.pass_rate) / 100; // 转换为小数
                classPassCount = Math.round(passRate * item.attended_students);
            }
            
            // 获取班级的优秀人数
            let classExcellentCount = 0;
            if (item.excellent_count !== undefined) {
                // 如果有直接提供的优秀人数，直接使用
                classExcellentCount = parseInt(item.excellent_count) || 0;
            } else if (item.excellent_rate !== undefined) {
                // 如果只有优秀率，通过到考人数计算优秀人数
                const excellentRate = parseFloat(item.excellent_rate) / 100; // 转换为小数
                classExcellentCount = Math.round(excellentRate * item.attended_students);
            }
            
            // 累加及格人数和优秀人数
            totals.pass_count += classPassCount;
            totals.excellent_count += classExcellentCount;
            
            // 处理分数分布
            try {
                const distribution = typeof item.score_distribution === 'string' ? 
                    JSON.parse(item.score_distribution) : 
                    item.score_distribution || {};

                Object.entries(distribution).forEach(([range, count]) => {
                    totals.distribution[range] = (totals.distribution[range] || 0) + parseInt(count);
                });
            } catch (e) {
                console.error('解析分数分布数据失败:', e);
            }
        });

        // 计算平均分
        totals.average_score = totals.attended_students > 0 ? 
            totals.total_score / totals.attended_students : 0;

        // 计算及格率和优秀率 - 确保乘以100
        totals.pass_rate = totals.attended_students > 0 ? 
            (totals.pass_count / totals.attended_students) * 100 : 0;
        totals.excellent_rate = totals.attended_students > 0 ? 
            (totals.excellent_count / totals.attended_students) * 100 : 0;

        // 处理极值边界情况
        if (totals.max_score === -Infinity) totals.max_score = 0;
        if (totals.min_score === Infinity) totals.min_score = 0;

        return totals;
    }
    
    // 格式化百分比率显示，整数不显示小数部分
    function formatRate(rate) {
        if (rate === null || rate === undefined) return '0%';
        
        // 确保是数字
        const num = parseFloat(rate);
        if (isNaN(num)) return '0%';
        
        // 如果是整数，不显示小数部分
        if (num % 1 === 0) {
            return Math.floor(num) + '%';
        }
        
        // 保留两位小数，但如果小数部分末尾是0，则去掉
        let formatted = num.toFixed(2);
        if (formatted.endsWith('0')) {
            formatted = num.toFixed(1);
            if (formatted.endsWith('0')) {
                formatted = Math.floor(num) + '';
            }
        }
        
        return formatted + '%';
    }
    
    // 生成学科合计行
    function generateSubjectTotalRow(totals, title, scoreRanges, colorClass) {
        // 格式化分数显示
        const formatScore = (score) => {
            if (score === null || score === undefined) return '0';
            if (score === '') return '0';
            
            const num = parseFloat(score);
            if (isNaN(num)) return '0';
            
            // 如果是整数，直接返回整数
            if (Number.isInteger(num)) {
                return num.toString();
            }
            // 否则保留一位小数，但如果小数部分为0，则去掉小数部分
            return parseFloat(num.toFixed(1)).toString();
        };
        
        return `
            <tr class="${colorClass} total-row">
                <td colspan="2" class="${colorClass}" style="font-weight: 600; text-align: center;">${title}</td>
                <td>${parseInt(totals.total_students) || 0}</td>
                <td>${parseInt(totals.attended_students) || 0}</td>
                <td>${formatScore(totals.total_score)}</td>
                <td>${formatScore(totals.average_score)}</td>
                ${scoreRanges.map(range => `<td>${totals.distribution[range] || 0}</td>`).join('')}
                <td>${formatScore(totals.max_score)}</td>
                <td>${formatScore(totals.min_score)}</td>
                <td>${formatRate(totals.pass_rate)}</td>
                <td>${formatRate(totals.excellent_rate)}</td>
            </tr>
        `;
    }
    
    // 渲染全优生/优良生统计表格
    function renderExcellentGoodSummary(data) {
        //console.log('开始渲染全优生/优良生统计表格，数据:', data);
        
        // 获取年级名称
        const gradeName = $('#grade-select option:selected').text();
        //console.log('当前年级:', gradeName);
        
        // 格式化百分比，如果小数点位为0，则不显示小数点位
        function formatPercent(value, total) {
            if (total <= 0) return '0%';
            const percent = (value / total) * 100;
            return percent % 1 === 0 ? Math.floor(percent) + '%' : percent.toFixed(2) + '%';
        }
        
        let bodyHtml = '';
        let totalExcellent = 0;
        let totalGood = 0;
        let totalStudents = 0;
        let totalPass = 0;
        
        if (data.classes && data.classes.length > 0) {
            data.classes.forEach(function(classItem) {
                const excellentCount = classItem.excellent_count || 0;
                const goodCount = classItem.good_count || 0;
                const studentCount = classItem.student_count || 0;
                const passCount = classItem.pass_count || 0;
                
                totalExcellent += excellentCount;
                totalGood += goodCount;
                totalStudents += studentCount;
                totalPass += passCount;
                
                const excellentRate = formatPercent(excellentCount, studentCount);
                const passRate = formatPercent(passCount, studentCount);
                
                bodyHtml += `
                    <tr>
                        <td class="text-center">${gradeName}${classItem.class_name}</td>
                        <td class="text-center">${studentCount}</td>
                        <td class="text-center">${excellentCount}</td>
                        <td class="text-center">${goodCount}</td>
                        <td class="text-center">${passRate}</td>
                        <td class="text-center">${excellentRate}</td>
                    </tr>
                `;
            });
            
            // 添加合计行
            const totalExcellentRate = formatPercent(totalExcellent, totalStudents);
            const totalPassRate = formatPercent(totalPass, totalStudents);
            
            bodyHtml += `
                <tr class="table-secondary fw-bold">
                    <td class="text-center">合计</td>
                    <td class="text-center">${totalStudents}</td>
                    <td class="text-center">${totalExcellent}</td>
                    <td class="text-center">${totalGood}</td>
                    <td class="text-center">${totalPassRate}</td>
                    <td class="text-center">${totalExcellentRate}</td>
                </tr>
            `;
        } else {
            bodyHtml = '<tr><td colspan="6" class="text-center">暂无数据</td></tr>';
        }
        
        $('#excellent-good-body').html(bodyHtml);
    }
    
    // 根据分数和学科信息获取等级
    function getScoreLevel(score, subjectInfo) {
        if (!score || score < 0) return { text: '缺考', class: 'level-absent' };
        
        const excellentScore = subjectInfo ? parseFloat(subjectInfo.excellent_score) : 90;
        const goodScore = subjectInfo ? parseFloat(subjectInfo.good_score) : 80;
        const passScore = subjectInfo ? parseFloat(subjectInfo.pass_score) : 60;
        
        if (score >= excellentScore) {
            return { text: '优秀', class: 'level-excellent' };
        } else if (score >= goodScore) {
            return { text: '良好', class: 'level-good' };
        } else if (score >= passScore) {
            return { text: '合格', class: 'level-pass' };
        } else {
            return { text: '待合格', class: 'level-fail' };
        }
    }
    
    // 渲染学生详细名单表格
    function renderStudentList(data, type) {
        //console.log('开始渲染学生详细名单表格，类型:', type, '数据:', data);
        
        if (!data) {
            console.warn('学生名单数据为空');
            const totalColspan = 3 + (selectedSubjects ? selectedSubjects.length : 0);
            $('#student-list-body').html(`<tr><td colspan="${totalColspan}" class="text-center">暂无${type === 'excellent' ? '全优生' : '优良生'}数据</td></tr>`);
            return;
        }
        
        // 获取年级名称
        const gradeName = $('#grade-select option:selected').text();
        
        // 获取学科信息映射
        const subjectInfoMap = new Map();
        if (data.subjects && Array.isArray(data.subjects)) {
            data.subjects.forEach(subject => {
                subjectInfoMap.set(subject.id.toString(), subject);
            });
        }
        
        // 构建已选择的学科列表
        const selectedSubjectsInfo = [];
        selectedSubjects.forEach(subjectId => {
            // 首先从后端返回的学科信息中查找
            const subjectFromBackend = data.subjects?.find(s => s.id.toString() === subjectId.toString());
            if (subjectFromBackend) {
                selectedSubjectsInfo.push(subjectFromBackend);
            } else {
                // 如果后端没有返回，则从前端可用学科列表中查找
                const subjectFromFrontend = availableSubjects.find(s => s.id.toString() === subjectId.toString());
                if (subjectFromFrontend) {
                    selectedSubjectsInfo.push(subjectFromFrontend);
                }
            }
        });
        
        //console.log('已选择的学科信息:', selectedSubjectsInfo);
        
        // 渲染表头
        let headerHtml = '<th class="text-center">班级</th><th class="text-center">学号</th><th class="text-center">姓名</th>';
        
        // 添加学科列 - 只显示分数列
        selectedSubjectsInfo.forEach(subject => {
            headerHtml += `<th class="text-center">${subject.subject_name}</th>`;
        });
        
        $('#student-list-header').html(headerHtml);
        
        // 渲染表格内容
        let bodyHtml = '';
        
        if (!data.students || data.students.length === 0) {
            const totalColspan = 3 + selectedSubjectsInfo.length;
            bodyHtml = `<tr><td colspan="${totalColspan}" class="text-center">暂无${type === 'excellent' ? '全优生' : '优良生'}数据</td></tr>`;
        } else {
            // 按班级分组
            const studentsByClass = {};
            data.students.forEach(student => {
                const className = student.class_name;
                if (!studentsByClass[className]) {
                    studentsByClass[className] = [];
                }
                studentsByClass[className].push(student);
            });
            
            // 班级排序
            const sortedClasses = Object.keys(studentsByClass).sort((a, b) => {
                // 提取班级名称中的数字部分进行排序
                const numA = parseInt(a.match(/\d+/)?.[0] || '0');
                const numB = parseInt(b.match(/\d+/)?.[0] || '0');
                return numA - numB;
            });
            
            // 总学生计数
            let totalStudentCount = 0;
            
            // 按班级生成表格行
            sortedClasses.forEach(className => {
                const students = studentsByClass[className];
                const classStudentCount = students.length;
                totalStudentCount += classStudentCount;
                
                // 生成每个学生的行
                students.forEach(student => {
                    let rowHtml = `
                        <td class="text-center">${gradeName}${student.class_name}</td>
                        <td class="text-center">${student.student_number || '-'}</td>
                        <td class="text-center">${student.student_name}</td>
                    `;
                    
                    // 添加各学科成绩 - 只显示分数
                    selectedSubjectsInfo.forEach(subject => {
                        // 查找该学科的成绩
                        const scoreInfo = student.scores?.find(s => s.subject_id.toString() === subject.id.toString());
                        
                        // 默认显示
                        let scoreDisplay = '-';
                        
                        if (scoreInfo && scoreInfo.total_score !== null && scoreInfo.total_score !== undefined) {
                            // 格式化分数显示
                            const score = parseFloat(scoreInfo.total_score);
                            scoreDisplay = score % 1 === 0 ? score.toFixed(0) : score.toFixed(1);
                        }
                        
                        rowHtml += `<td class="text-center">${scoreDisplay}</td>`;
                    });
                    
                    bodyHtml += `<tr>${rowHtml}</tr>`;
                });
                
                // 添加班级小计行
                const totalColspan = 3 + selectedSubjectsInfo.length;
                bodyHtml += `<tr class="table-light">
                    <td colspan="${totalColspan}" class="text-end fw-bold">
                        ${gradeName}${className} 小计: ${classStudentCount}人
                    </td>
                </tr>`;
            });
            
            // 添加总计行
            const totalColspan = 3 + selectedSubjectsInfo.length;
            bodyHtml += `<tr class="table-secondary">
                <td colspan="${totalColspan}" class="text-end fw-bold">
                    总计: ${totalStudentCount}人
                </td>
            </tr>`;
        }
        
        $('#student-list-body').html(bodyHtml);
    }
    
    // 隐藏结果区域
    function hideResults() {
        $('#class-analytics-container').hide();
        $('#excellent-good-summary-container').hide();
        $('#student-list-container').hide();
    }
    
    // 显示加载中遮罩
    function showLoading() {
        $('#loading-overlay').show();
    }
    
    // 隐藏加载中遮罩
    function hideLoading() {
        $('#loading-overlay').hide();
    }
    
    // 显示错误提示
    function showError(message) {
        Swal.fire({
            icon: 'error',
            title: '错误',
            text: message
        });
    }
    
    // 空函数，用于解决从其他模块复制代码时的引用问题
    function loadStudentScores() {
        // 这个函数在全科统计分析模块中不需要实际功能
        // 仅用于防止控制台报错
        console.log('loadStudentScores 在全科统计分析模块中不需要实际功能');
    }
});
</script> 