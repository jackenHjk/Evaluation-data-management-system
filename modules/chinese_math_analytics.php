<?php
/**
 * 文件名: modules/chinese_math_analytics.php
 * 功能描述: 语文数学成绩对比分析模块
 * 
 * 该文件负责:
 * 1. 展示语文和数学科目的成绩关联对比分析
 * 2. 提供两科的分数段分布对比图表
 * 3. 计算语文数学的相关性分析数据
 * 4. 提供班级间语数成绩综合对比
 * 5. 生成优秀生和薄弱生的两科成绩交叉分析
 * 
 * 分析内容包括:
 * - 语数双科成绩相关性分析
 * - 语数优生与薄弱生群体分布
 * - 班级间语数综合能力对比
 * - 语数分数差异分析
 * - 两科成绩均衡性评估
 * 
 * 关联文件:
 * - api/download/chinese_math.php: 语数成绩导出API
 * - api/download/ChineseMathExport.php: 语数成绩导出类
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 检查用户是否登录
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}
?>

<!-- CSS 依赖 -->
<link href="../assets/css/bootstrap.min.css" rel="stylesheet">
<link href="../assets/css/sweetalert2.min.css" rel="stylesheet">
<link href="../assets/css/all.min.css" rel="stylesheet">
<link href="../assets/css/common.css" rel="stylesheet">
<script src="../assets/js/sweetalert2.all.min.js"></script>

<div class="container-fluid px-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h5 class="mb-0">
            <i class="fas fa-chart-line text-primary me-2"></i><span style="font-weight: 700; font-size: 1.25rem;">语数看板</span>
        </h5>
        <div class="d-flex align-items-center">
            <div style="width: 160px;">
                <select id="grade-select" class="form-select me-2">
                    <option value="">选择年级...</option>
                </select>
            </div>
        </div>
    </div>

    <!-- 统计数据表格 -->
    <div class="card mb-4" style="border: none; border-radius: 16px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); background: #ffffff;">
        <div class="card-header" style="background: transparent; border-bottom: 1px solid rgba(0,0,0,0.05); padding: 1.5rem;">
            <i class="fas fa-table me-1"></i>
            <span style="font-weight: 600; color: #2c3e50;">数据报表</span>
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

    <!-- 学生成绩列表 -->
    <div class="card" style="border: none; border-radius: 16px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); background: #ffffff;">
        <div class="card-header" style="background: transparent; border-bottom: 1px solid rgba(0,0,0,0.05); padding: 1.5rem;">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <i class="fas fa-list me-1"></i>
                    <span style="font-weight: 600; color: #2c3e50;">学生列表</span>
                </div>
                <div class="d-flex align-items-center">
                    <div style="width: 160px;" class="me-4">
                        <select id="class-filter" class="form-select">
                            <option value="">所有班级</option>
                        </select>
                    </div>
                    <div style="width: 160px;">
                        <select id="sort-select" class="form-select">
                            <option value="number">按学号排序</option>
                            <option value="total_score">按总分排序</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>
        <div class="card-body">
            <div id="scoreList">
                <div class="table-responsive" style="max-height: 666px;">
                    <table class="table table-bordered table-hover">
                        <thead>
                            <tr>
                                <th style="width: 60px;" class="text-center">序号</th>
                                <th style="width: 100px;">学号</th>
                                <th style="width: 100px;">姓名</th>
                                <th style="width: 100px;">班级</th>
                                <th style="width: 100px;" class="text-center">语文成绩</th>
                                <th style="width: 100px;" class="text-center">语文等级</th>
                                <th style="width: 100px;" class="text-center">数学成绩</th>
                                <th style="width: 100px;" class="text-center">数学等级</th>
                                <th style="width: 100px;" class="text-center">语数总分</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- 成绩列表将通过JavaScript动态生成 -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    /* 添加背景和边框样式 */
    .container-fluid {
        background: #fff;
        border-radius: 20px;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        padding: 20px;
        position: relative;
        overflow: hidden;
    }

    /* 添加表格居中样式 */
    #scoreList table td,
    #scoreList table th {
        text-align: center !important;
        vertical-align: middle !important;
    }
    
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
    
    .analytics-table {
        width: max-content !important;
        min-width: 100% !important;
        margin: 0 auto;
        font-size: 12px;
        border-collapse: separate !important;
        border-spacing: 0 !important;
    }
    .analytics-table th,
    .analytics-table td {
        border: 1px solid #ddd;
        padding: 4px 2px !important;
        text-align: center;
        vertical-align: middle;
        overflow: visible !important;
        white-space: normal !important;
        word-break: break-all !important;
        word-wrap: break-word !important;
        box-sizing: border-box !important;
    }
    /* 列样式类 */
    .col-class {
        width: 50px !important;
        min-width: 40px !important;
        max-width: 60px !important;
    }
    /* 班级总人数列样式 */
    .col-subject, 
    .col-total-students,
    .col-attended-students {
        width: 50px !important;
        min-width: 40px !important;
        max-width: 50px !important;
        white-space: normal !important;
        word-break: break-all !important;
        line-height: 1.2 !important;
    }
    /* 总分列样式 */
    .col-total-score,
    .col-average-score {
        width: 70px !important;
        min-width: 70px !important;
        max-width: 70px !important;
    }
    /* 数据分布列 */
    .col-distribution {
        width: 40px !important;
        min-width: 40px !important;
        max-width: 40px !important;
        padding: 4px 1px !important;
        box-sizing: border-box !important;
        overflow: visible !important;
        white-space: normal !important;
        font-size: 12px !important;
        line-height: 1.2 !important;
        word-break: break-all !important;
        word-wrap: break-word !important;
        text-align: center !important;
    }
    /* 最高分最低分列 */
    .col-max-score,
    .col-min-score {
        width: 60px !important;
        min-width: 60px !important;
        max-width: 60px !important;
    }
    /* 及格率优秀率列 */
    .col-pass-rate,
    .col-excellent-rate {
        width: 70px !important;
        min-width: 70px !important;
        max-width: 70px !important;
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
    /* 表格单元格颜色 */
    .cell-excellent { 
        background-color: transparent !important; 
        color: inherit !important;
    }
    .cell-good { 
        background-color: transparent !important;
        color: inherit !important;
    }
    .cell-pass { 
        background-color: transparent !important;
        color: inherit !important;
    }
    .cell-fail { 
        background-color: transparent !important;
        color: inherit !important;
    }
    /* 表格文字颜色 */
    .text-excellent { color: #0369a1; }
    .text-good { color: #15803d; }
    .text-pass { color: #854d0e; }
    .text-fail { color: #991b1b; }
    /* 合计行样式 */
    .total-row td {
        font-weight: 600 !important;
    }
    /* 固定表头样式 */
    #scoreList {
        position: relative;
    }
    #scoreList .table-responsive {
        overflow-y: auto;
        max-height: 666px;
    }
    #scoreList table {
        margin-bottom: 0;
    }
    #scoreList thead {
        position: sticky;
        top: 0;
        z-index: 1;
        background: white;
    }
    #scoreList thead th {
        background: #f8f9fa;
        border-bottom: 2px solid #dee2e6;
        vertical-align: middle;
    }
    /* 调整卡片样式 */
    .card-body {
        padding: 1rem;
        overflow: hidden;
    }

    /* 自定义下拉框样式 */
    .custom-select-wrapper {
        position: relative;
        width: inherit;  /* 继承父元素宽度 */
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
        width: 100%;  /* 确保触发器占满容器宽度 */
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

    .analytics-footer {
            font-size: 12px;
            color:rgb(245, 131, 60);
            margin-top: 20px;
            text-align: left;
            margin-left: 10px;
                
        }
</style>



<script>
$(document).ready(function() {
    let currentGrade = '';
    let subjectInfo = {
        chinese: null,
        math: null
    };
    
    // 添加班级统计分析数据缓存
    let classAnalyticsCache = new Map();
    // 添加待审核班级映射对象
    let pendingEditClasses = {};

    // 获取当前项目ID
    $.get('../api/index.php?route=settings/current', function(response) {
        if (response.success && response.data) {
            window.currentSettingId = response.data.id;
            window.currentProject = response.data;  // 存储项目信息
            // 初始化页面数据
            loadGrades();
        } else {
            alert('获取当前项目信息失败');
        }
    });

    // 加载年级列表
    function loadGrades() {
        $.get('../api/index.php?route=grade/getList', function(response) {
            if (response.success) {
                const select = $('#grade-select');
                select.empty().append('<option value="">选择年级</option>');
                
                // 对年级进行排序并去重
                const uniqueGrades = {};
                response.data.forEach(grade => {
                    if (!uniqueGrades[grade.grade_code]) {
                        uniqueGrades[grade.grade_code] = grade;
                    }
                });
                
                // 转换为数组并按年级编号排序
                const sortedGrades = Object.values(uniqueGrades).sort((a, b) => 
                    parseInt(a.grade_code) - parseInt(b.grade_code)
                );
                
                sortedGrades.forEach(grade => {
                    select.append(`<option value="${grade.id}">${grade.grade_name}</option>`);
                });

                // 重新初始化自定义下拉框
                initCustomSelects();
            }
        });
    }

    // 检查单个学科是否有待审核记录
    function checkSingleSubjectPendingEdits(gradeId, subjectId) {
        return new Promise((resolve, reject) => {
            $.ajax({
                url: '../api/index.php',
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
    function checkPendingScoreEdits(gradeId, subjectId = null) {
        return new Promise((resolve, reject) => {
            // 如果没有指定学科ID，表示是语数下载，需要同时检查语文和数学
            if (!subjectId) {
                // 先获取语文和数学的学科ID
                $.ajax({
                    url: '../api/index.php',
                    data: {
                        route: 'settings/grade/subjects',
                        grade_id: gradeId,
                        setting_id: window.currentSettingId // 添加必要的setting_id参数
                    },
                    method: 'GET',
                    dataType: 'json',
                    success: function(response) {
                        if (!response.success || !Array.isArray(response.data)) {
                            // 如果获取学科失败，我们不阻止用户继续操作，只在控制台记录错误
                            console.warn('获取学科列表失败:', response.error || '未知错误');
                            resolve({ hasPendingRequest: false, subjects: [] });
                            return;
                        }

                        // 找到语文和数学的学科ID
                        const chineseSubject = response.data.find(s => s.subject_name === '语文');
                        const mathSubject = response.data.find(s => s.subject_name === '数学');

                        if (!chineseSubject || !mathSubject) {
                            console.warn('未找到语文或数学学科');
                            resolve({ hasPendingRequest: false, subjects: [] });
                            return;
                        }

                        // 同时检查语文和数学是否有待审核记录
                        Promise.all([
                            checkSingleSubjectPendingEdits(gradeId, chineseSubject.id),
                            checkSingleSubjectPendingEdits(gradeId, mathSubject.id)
                        ]).then(results => {
                            // 如果任一学科有待审核记录，则返回true
                            const hasPending = results.some(result => result.hasPendingRequest);
                            
                            // 将变量声明移到 if 块外部，避免在下方引用时出现未定义错误
                            const pendingSubjects = [];
                            const pendingClasses = [];
                            
                            if (hasPending) {
                                // 如果有待审核记录，显示提示
                                if (results[0].hasPendingRequest) {
                                    pendingSubjects.push('语文');
                                    if (results[0].pending_classes && results[0].pending_classes.length > 0) {
                                        pendingClasses.push(...results[0].pending_classes);
                                    }
                                }
                                
                                if (results[1].hasPendingRequest) {
                                    pendingSubjects.push('数学');
                                    if (results[1].pending_classes && results[1].pending_classes.length > 0) {
                                        pendingClasses.push(...results[1].pending_classes);
                                    }
                                }
                                
                                // 去重班级列表
                                const uniqueClasses = [...new Set(pendingClasses)];
                                const pendingClassesText = uniqueClasses.length > 0 ? 
                                    `，涉及班级：${uniqueClasses.join('、')}` : '';
                                
                                // 显示提示
                                Swal.fire({
                                    icon: 'warning',
                                    title: '待审核提醒',
                                    text: `该年级${pendingSubjects.join('、')}学科存在待审核的成绩修改申请${pendingClassesText}，部分数据可能不准确。请先完成审核后再查看。`,
                                    confirmButtonText: '我知道了'
                                });
                            }
                            
                            resolve({
                                hasPendingRequest: hasPending,
                                subjects: hasPending ? ['语文', '数学'].filter((_, index) => results[index].hasPendingRequest) : [],
                                pending_classes: [...new Set(pendingClasses)]
                            });
                        }).catch(error => {
                            console.warn('检查待审核状态失败:', error);
                            // 即使检查失败也允许用户继续操作
                            resolve({ hasPendingRequest: false, subjects: [] });
                        });
                    },
                    error: function(xhr) {
                        console.warn('获取学科列表失败:', xhr.responseJSON?.error || '网络请求错误');
                        // 即使API请求失败也允许用户继续操作
                        resolve({ hasPendingRequest: false, subjects: [] });
                    }
                });
            } else {
                // 单学科下载，直接检查指定学科
                checkSingleSubjectPendingEdits(gradeId, subjectId)
                    .then(result => {
                        if (result.hasPendingRequest) {
                            // 获取待审核班级列表
                            const pendingClassesText = result.pending_classes && result.pending_classes.length > 0 ? 
                                `，涉及班级：${result.pending_classes.join('、')}` : '';
                                
                            // 显示提示
                            Swal.fire({
                                icon: 'warning',
                                title: '待审核提醒',
                                text: `该学科存在待审核的成绩修改申请${pendingClassesText}，部分数据可能不准确。请先完成审核后再查看。`,
                                confirmButtonText: '我知道了'
                            });
                        }
                        resolve(result);
                    })
                    .catch(error => {
                        console.warn('检查待审核状态失败:', error);
                        // 即使检查失败也允许用户继续操作
                        resolve({ hasPendingRequest: false, count: 0 });
                    });
            }
        });
    }

    // 加载统计数据
    function loadAnalytics() {
        if (!currentGrade || !window.currentSettingId) return;

        // 获取年级名称
        const gradeName = $('#grade-select option:selected').text();

        // 检查是否已经记录过相同的查看记录
        if (!window.lastViewRecord || 
            window.lastViewRecord !== `${currentGrade}`) {
            
            // 更新最后查看记录
            window.lastViewRecord = `${currentGrade}`;
            
            // 发送日志记录请求
            $.ajax({
                url: '../api/index.php?route=log/add',
                method: 'POST',
                data: {
                    action_type: 'view',
                    action_detail: `查看${gradeName}语数统计数据`
                },
                error: function(xhr) {
                    console.error('记录日志失败:', xhr.responseText);
                }
            });
        }

        // 获取统计数据
        $.get('api/index.php?route=analytics/getSubjectsAnalytics', {
            grade_id: currentGrade,
            setting_id: window.currentSettingId
        }, function(response) {
            if (response.success && response.data) {
                // 创建表格标题和表头
                const projectInfo = window.currentProject || {};
                const title = `
                    <div class="analytics-title">
                        ${projectInfo.school_name || ''}${projectInfo.current_semester || ''}${gradeName}语数成绩${projectInfo.project_name || ''}统计分析表
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

                // 生成表格HTML
                let tableHtml = `
                    ${title}
                    <table class="analytics-table">
                        <tr>
                            <th rowspan="2" class="col-class">班别</th>
                            <th rowspan="2" class="col-subject">学科</th>
                            <th rowspan="2" class="col-total-students">总人数</th>
                            <th rowspan="2" class="col-attended-students">到考人数</th>
                            <th rowspan="2" class="col-total-score">总分</th>
                            <th rowspan="2" class="col-average-score">平均分</th>
                            <th colspan="13">数据分布</th>
                            <th rowspan="2" class="col-max-score">最高分</th>
                            <th rowspan="2" class="col-min-score">最低分</th>
                            <th rowspan="2" class="col-pass-rate">及格率</th>
                            <th rowspan="2" class="col-excellent-rate">优秀率</th>
                        </tr>
                        <tr>
                            ${scoreRanges.map(range => `
                                <th class="col-distribution">${range.replace('-', '<br>/<br>')}</th>
                            `).join('')}
                        </tr>
                `;

                // 确保response.data是数组
                const analyticsData = Array.isArray(response.data) ? response.data : [];
                
                // 按学科分组数据
                const chineseData = analyticsData.filter(item => item.subject_name === '语文');
                const mathData = analyticsData.filter(item => item.subject_name === '数学');
                
                // 对班级进行数字排序
                const sortByClassNumber = (a, b) => {
                    // 提取班级名称中的数字部分
                    const numA = parseInt(a.class_name.replace(/[^0-9]/g, ''));
                    const numB = parseInt(b.class_name.replace(/[^0-9]/g, ''));
                    return numA - numB;
                };
                
                // 对语文和数学数据按班级编号排序
                chineseData.sort(sortByClassNumber);
                mathData.sort(sortByClassNumber);

                // 添加待审核检查提示
                if (Object.keys(pendingEditClasses).length > 0) {
                    tableHtml += `
                        <tr>
                            <td colspan="22" class="text-center" style="padding: 10px; background-color: #fff3cd; color: #856404;">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                注意：部分班级有待审核的成绩修改申请，这些班级的数据暂不显示。请审核完成后再查看。
                            </td>
                        </tr>
                    `;
                }

                // 处理语文数据
                chineseData.forEach(item => {
                    const classKey = `${item.class_id}-${item.subject_id}`;
                    if (!pendingEditClasses[classKey]) {
                        tableHtml += generateTableRow(item, scoreRanges);
                    }
                });

                // 添加语文合计行
                if (chineseData.length > 0) {
                    const filteredChineseData = chineseData.filter(item => {
                        const classKey = `${item.class_id}-${item.subject_id}`;
                        return !pendingEditClasses[classKey];
                    });
                    
                    if (filteredChineseData.length > 0) {
                        const chineseTotals = calculateTotals(filteredChineseData);
                        tableHtml += generateTotalRow(chineseTotals, '语文合计', scoreRanges);
                    }
                }

                // 处理数学数据
                mathData.forEach(item => {
                    const classKey = `${item.class_id}-${item.subject_id}`;
                    if (!pendingEditClasses[classKey]) {
                        tableHtml += generateTableRow(item, scoreRanges);
                    }
                });

                // 添加数学合计行
                if (mathData.length > 0) {
                    const filteredMathData = mathData.filter(item => {
                        const classKey = `${item.class_id}-${item.subject_id}`;
                        return !pendingEditClasses[classKey];
                    });
                    
                    if (filteredMathData.length > 0) {
                        const mathTotals = calculateTotals(filteredMathData);
                        tableHtml += generateTotalRow(mathTotals, '数学合计', scoreRanges);
                    }
                }

                // 移除总计行，直接结束表格
                tableHtml += '</table>';
                $('#analyticsTable').html(tableHtml).show();
            } else {
                $('#analyticsTable').html('<div class="alert alert-warning">暂无统计数据</div>');
            }
        });
    }

    // 加载学生成绩列表
    function loadScores() {
        if (!currentGrade || !window.currentSettingId) return;

        const loadingHtml = '<tr><td colspan="9" class="text-center"><div class="spinner-border spinner-border-sm text-primary" role="status"></div> 正在加载数据...</td></tr>';
        $('#scoreList tbody').html(loadingHtml);

        // 获取当前班级筛选
        const currentClass = $('#class-filter').val() || '';
        
        // 获取当前排序方式
        const currentSort = $('#sort-select').val() || 'number';

        // 调用API获取学生语数成绩
        $.get('../api/index.php?route=analytics/getSubjectScores', {
            grade_id: currentGrade,
            class_id: currentClass,
            setting_id: window.currentSettingId
        })
        .done(function(response) {
            if (response.success && response.data) {
                // 更新科目信息
                if (response.data.subjects) {
                    subjectInfo = response.data.subjects;
                }
                
                // 更新成绩列表
                if (response.data.scores) {
                    updateScoreList(response.data.scores, currentSort);
                } else {
                    $('#scoreList tbody').html('<tr><td colspan="9" class="text-center">暂无数据</td></tr>');
                }
            } else {
                console.error('加载成绩列表失败:', response.error);
                $('#scoreList tbody').html('<tr><td colspan="9" class="text-center text-danger">加载失败：' + (response.error || '未知错误') + '</td></tr>');
            }
        })
        .fail(function(xhr, status, error) {
            console.error('请求成绩列表失败:', {xhr, status, error});
            $('#scoreList tbody').html('<tr><td colspan="9" class="text-center text-danger">加载失败：请检查网络连接</td></tr>');
        });
    }

    // 更新成绩列表
    function updateScoreList(scores, sortBy) {
        if (!scores || !scores.length) {
            $('#scoreList tbody').html('<tr><td colspan="9" class="text-center">暂无数据</td></tr>');
            return;
        }

        // 根据排序方式排序
        if (sortBy === 'number') {
            scores.sort((a, b) => (a.student_number || '').localeCompare(b.student_number || ''));
        } else if (sortBy === 'total_score') {
            scores.sort((a, b) => {
                const totalA = (parseFloat(a.chinese_score) || 0) + (parseFloat(a.math_score) || 0);
                const totalB = (parseFloat(b.chinese_score) || 0) + (parseFloat(b.math_score) || 0);
                return totalB - totalA;
            });
        }

        let html = '';
        scores.forEach((score, index) => {
            // 处理语文成绩和等级
            const chineseScore = score.chinese_absent === '1' ? '缺考' : formatScore(score.chinese_score);
            const chineseLevel = getScoreLevelHtml(chineseScore, subjectInfo.chinese);

            // 处理数学成绩和等级
            const mathScore = score.math_absent === '1' ? '缺考' : formatScore(score.math_score);
            const mathLevel = getScoreLevelHtml(mathScore, subjectInfo.math);

            // 计算总分
            const totalScore = score.chinese_absent === '1' || score.math_absent === '1' ? '-' : 
                formatScore((parseFloat(score.chinese_score) || 0) + (parseFloat(score.math_score) || 0));

            html += `
                <tr>
                    <td class="text-center">${index + 1}</td>
                    <td>${score.student_number || ''}</td>
                    <td>${score.student_name || ''}</td>
                    <td>${score.class_name || ''}</td>
                    <td class="text-center">${chineseScore}</td>
                    <td class="text-center">${chineseLevel}</td>
                    <td class="text-center">${mathScore}</td>
                    <td class="text-center">${mathLevel}</td>
                    <td class="text-center">${totalScore}</td>
                </tr>
            `;
        });

        $('#scoreList tbody').html(html);
    }

    // 获取成绩等级HTML
    function getScoreLevelHtml(score, subjectConfig) {
        if (score === '缺考' || score === '-' || !subjectConfig) {
            return '<span class="badge bg-secondary">-</span>';
        }

        const scoreNum = parseFloat(score);
        if (isNaN(scoreNum)) {
            return '<span class="badge bg-secondary">-</span>';
        }

        let levelClass = '';
        let levelText = '';
        
        if (scoreNum >= parseFloat(subjectConfig.excellent_score)) {
            levelClass = 'bg-success';
            levelText = '优秀';
        } else if (scoreNum >= parseFloat(subjectConfig.good_score)) {
            levelClass = 'bg-primary';
            levelText = '良好';
        } else if (scoreNum >= parseFloat(subjectConfig.pass_score)) {
            levelClass = 'bg-info';
            levelText = '合格';
        } else {
            levelClass = 'bg-danger';
            levelText = '待合格';
        }
        
        return `<span class="badge ${levelClass}">${levelText}</span>`;
    }

    // 计算合计行数据
    function calculateTotals(data) {
        const totals = {
            total_students: 0,
            attended_students: 0,
            total_score: 0,
            max_score: -Infinity,
            min_score: Infinity,
            distribution: {},
            pass_count: 0,
            excellent_count: 0,
            valid_class_count: 0 // 添加有效班级计数
        };

        data.forEach(item => {
            // 检查是否为有效数据（不是"/"）
            const hasValidData = item && item.attended_students > 0;
            if (!hasValidData) return;

            totals.valid_class_count++;
            totals.total_students += parseInt(item.total_students) || 0;
            totals.attended_students += parseInt(item.attended_students) || 0;
            totals.total_score += parseFloat(item.total_score) || 0;
            totals.max_score = Math.max(totals.max_score, parseFloat(item.max_score) || -Infinity);
            totals.min_score = Math.min(totals.min_score, parseFloat(item.min_score) || Infinity);

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

        // 计算平均分，只计算有效班级
        totals.average_score = totals.attended_students > 0 ? 
            totals.total_score / totals.attended_students : 0;

        // 计算合计行的及格率和优秀率（直接累加各班级的率值并除以有效班级数）
        let totalPassRate = 0;
        let totalExcellentRate = 0;

        data.forEach(item => {
            if (item && item.attended_students > 0) {
                if (item.pass_rate) {
                    totalPassRate += parseFloat(item.pass_rate);
                }
                if (item.excellent_rate) {
                    totalExcellentRate += parseFloat(item.excellent_rate);
                }
            }
        });

        // 计算平均率值（除以有效班级数）
        totals.pass_rate = totals.valid_class_count > 0 ? 
            totalPassRate / totals.valid_class_count : 0;
        totals.excellent_rate = totals.valid_class_count > 0 ? 
            totalExcellentRate / totals.valid_class_count : 0;

        // 处理极值
        if (totals.max_score === -Infinity) totals.max_score = 0;
        if (totals.min_score === Infinity) totals.min_score = 0;

        return totals;
    }

    // 获取单元格样式类
    function getCellClass(range, subjectType = null) {
        // 移除所有单元格背景色
        return '';
    }

    // 格式化分数显示
    function formatScore(score) {
        if (score === null || score === undefined) return '0';
        if (score === '') return '0';
        
        const num = parseFloat(score);
        if (isNaN(num)) return '0';
        
        // 如果是整数，直接返回整数
        if (Number.isInteger(num)) {
            return num.toString();
        }
        // 否则保留一位小数，但如果小数部分为0，则去掉小数部分
        return parseFloat(num.toFixed(2)).toString();
    }

    // 添加生成表格行的函数
    function generateTableRow(item, scoreRanges) {
        // 检查是否有有效数据
        const hasData = item && item.attended_students > 0;
        
        // 如果没有数据，显示"/"
        const displayValue = (value) => hasData ? value : '/';
        
        // 确定科目类型
        const subjectType = item.subject_name === '语文' ? 'chinese' : (item.subject_name === '数学' ? 'math' : null);
        
        let row = `
            <tr>
                <td class="col-class">${item.class_name || ''}</td>
                <td class="col-subject">${item.subject_name || ''}</td>
                <td class="col-total-students">${hasData ? item.total_students : '/'}</td>
                <td class="col-attended-students">${displayValue(item.attended_students)}</td>
                <td class="col-total-score">${displayValue(formatScore(item.total_score))}</td>
                <td class="col-average-score">${displayValue(item.average_score != null ? formatScore(item.average_score) : '/')}</td>`;
    
        // 处理分数分布
        let distribution = {};
        try {
            distribution = typeof item.score_distribution === 'string' ? 
                JSON.parse(item.score_distribution) : 
                item.score_distribution || {};
        } catch (e) {
            console.error('解析分数分布数据失败:', e);
        }
    
        // 添加分数分布数据
        scoreRanges.forEach(range => {
            row += `<td class="col-distribution ${getCellClass(range, subjectType)}">${displayValue(distribution[range] || 0)}</td>`;
        });
    
        // 添加最高分、最低分、及格率和优秀率
        row += `
                <td class="col-max-score">${displayValue(formatScore(item.max_score))}</td>
                <td class="col-min-score">${displayValue(formatScore(item.min_score))}</td>
                <td class="col-pass-rate">${displayValue(item.pass_rate ? parseFloat(parseFloat(item.pass_rate).toFixed(2)) + '%' : '/')}</td>
                <td class="col-excellent-rate">${displayValue(item.excellent_rate ? parseFloat(parseFloat(item.excellent_rate).toFixed(2)) + '%' : '/')}</td>
            </tr>`;
    
        return row;
    }

    // 修改生成合计行的函数
    function generateTotalRow(totals, title, scoreRanges) {
        // 判断是否为语文或数学合计行
        const isSubjectTotal = title === '语文合计' || title === '数学合计';
        // 确定科目类型
        const subjectType = title === '语文合计' ? 'chinese' : (title === '数学合计' ? 'math' : null);
        
        // 计算百分比时需要乘以100
        const passRate = totals.pass_rate ? (totals.pass_rate * 1).toFixed(2) : '0';
        const excellentRate = totals.excellent_rate ? (totals.excellent_rate * 1).toFixed(2) : '0';
        
        return `
            <tr class="total-row">
                ${isSubjectTotal ? 
                    `<td colspan="2" class="col-class col-subject" style="font-weight: 600; text-align: center;">${title}</td>` :
                    `<td class="col-class" style="font-weight: 600;">${title}</td><td class="col-subject">-</td>`
                }
                <td class="col-total-students">${parseInt(totals.total_students) || 0}</td>
                <td class="col-attended-students">${parseInt(totals.attended_students) || 0}</td>
                <td class="col-total-score">${formatScore(totals.total_score)}</td>
                <td class="col-average-score">${formatScore(totals.average_score)}</td>
                ${scoreRanges.map(range => `
                    <td class="col-distribution">${totals.distribution[range] || 0}</td>
                `).join('')}
                <td class="col-max-score">${formatScore(totals.max_score)}</td>
                <td class="col-min-score">${formatScore(totals.min_score)}</td>
                <td class="col-pass-rate">${parseFloat(passRate)}%</td>
                <td class="col-excellent-rate">${parseFloat(excellentRate)}%</td>
            </tr>
        `;
    }

    // 初始化自定义下拉框
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

    // 事件监听
    $('#grade-select').change(function() {
        currentGrade = $(this).val();
        if (currentGrade) {
            // 重置班级待审核状态记录
            pendingEditClasses = {};
            
            // 检查该年级是否有待审核的成绩修改申请
            checkPendingScoreEdits(currentGrade)
                .then(() => {
                    // 无论是否有待审核申请，都继续加载数据
                    loadClassOptions();
                    loadAnalytics();
                    loadScores();
                })
                .catch(error => {
                    console.error('检查待审核状态失败:', error);
                    // 即使检查失败也继续加载数据
                    loadClassOptions();
                    loadAnalytics();
                    loadScores();
                });
        }
    });

    // 班级过滤和排序事件监听
    $('#class-filter').change(function() {
        loadAnalytics();
        loadScores();
    });

    $('#sort-select').change(function() {
        loadAnalytics();
        loadScores();
    });

    // 加载班级选项
    function loadClassOptions() {
        if (!currentGrade) return;
        
        $.get('../api/index.php?route=class/getList', { grade_id: currentGrade }, function(response) {
            if (response.success) {
                const classFilter = $('#class-filter');
                classFilter.empty().append('<option value="">所有班级</option>');
                
                if (response.data && response.data.length > 0) {
                    // 获取语文和数学科目ID
                    $.get('../api/index.php?route=subject/getList', {
                        grade_id: currentGrade,
                        setting_id: window.currentSettingId
                    })
                    .done(function(subjectResponse) {
                        if (!subjectResponse.success) {
                            console.error('加载科目列表失败:', subjectResponse.error);
                            // 简单添加班级选项，不做待审核检查
                            addSimpleClassOptions(response.data);
                            return;
                        }
                        
                        const subjects = subjectResponse.data;
                        let chineseSubjectId = null;
                        let mathSubjectId = null;
                        
                        // 找出语文和数学科目ID
                        subjects.forEach(subject => {
                            if (subject.subject_name === '语文') {
                                chineseSubjectId = subject.id;
                            } else if (subject.subject_name === '数学') {
                                mathSubjectId = subject.id;
                            }
                        });
                        
                        if (!chineseSubjectId || !mathSubjectId) {
                            console.error('未找到语文或数学科目');
                            // 简单添加班级选项，不做待审核检查
                            addSimpleClassOptions(response.data);
                            return;
                        }
                        
                        const checkPromises = [];
                        // 检查每个班级是否有待审核申请
                        response.data.forEach(cls => {
                            // 检查每个班级的语文和数学科目是否有待审核申请
                            if (chineseSubjectId) {
                                checkPromises.push(
                                    checkPendingScoreEdits(cls.id, chineseSubjectId)
                                        .then(result => {
                                            if (result.hasPendingRequest) {
                                                const classKey = `${cls.id}-${chineseSubjectId}`;
                                                pendingEditClasses[classKey] = true;
                                            }
                                        })
                                        .catch(err => console.error(`检查班级 ${cls.class_name} 语文科目待审核状态失败:`, err))
                                );
                            }
                            
                            if (mathSubjectId) {
                                checkPromises.push(
                                    checkPendingScoreEdits(cls.id, mathSubjectId)
                                        .then(result => {
                                            if (result.hasPendingRequest) {
                                                const classKey = `${cls.id}-${mathSubjectId}`;
                                                pendingEditClasses[classKey] = true;
                                            }
                                        })
                                        .catch(err => console.error(`检查班级 ${cls.class_name} 数学科目待审核状态失败:`, err))
                                );
                            }
                        });
                        
                        // 等待所有检查完成后再添加班级选项
                        Promise.all(checkPromises)
                            .finally(() => {
                // 对班级进行数字排序
                response.data.sort((a, b) => {
                    // 提取班级名称中的数字部分
                    const numA = parseInt(a.class_name.replace(/[^0-9]/g, ''));
                    const numB = parseInt(b.class_name.replace(/[^0-9]/g, ''));
                    return numA - numB;
                });
                
                response.data.forEach(cls => {
                                    // 如果班级有待审核申请，在选项中标记出来
                                    const hasPendingChinese = pendingEditClasses[`${cls.id}-${chineseSubjectId}`];
                                    const hasPendingMath = pendingEditClasses[`${cls.id}-${mathSubjectId}`];
                                    const hasPending = hasPendingChinese || hasPendingMath;
                                    
                                    classFilter.append(`
                                        <option value="${cls.id}" ${hasPending ? 'data-has-pending="true"' : ''}>
                                            ${cls.class_name} ${hasPending ? '(有待审核)' : ''}
                                        </option>
                                    `);
                });
                
                                // 重新初始化自定义下拉框
                                initCustomSelects();
                                
                                // 更新分析以反映待审核状态
                                loadAnalytics();
                            });
                    })
                    .fail(function() {
                        console.error('加载科目列表请求失败');
                        // 简单添加班级选项，不做待审核检查
                        addSimpleClassOptions(response.data);
                    });
                } else {
                // 重新初始化自定义下拉框
                initCustomSelects();
                }
            }
        });
    }
    
    // 简单添加班级选项，不做待审核检查
    function addSimpleClassOptions(classesData) {
        const classFilter = $('#class-filter');
        
        // 对班级进行数字排序
        classesData.sort((a, b) => {
            // 提取班级名称中的数字部分
            const numA = parseInt(a.class_name.replace(/[^0-9]/g, ''));
            const numB = parseInt(b.class_name.replace(/[^0-9]/g, ''));
            return numA - numB;
        });
        
        classesData.forEach(cls => {
            classFilter.append(`<option value="${cls.id}">${cls.class_name}</option>`);
        });
    }
});
</script>