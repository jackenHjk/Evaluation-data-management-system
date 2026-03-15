<!--
/**
 * 文件名: modules/grade_analytics.php
 * 功能描述: 年级数据分析模块
 *
 * 该文件负责:
 * 1. 显示年级整体成绩统计分析数据
 * 2. 提供按学科分类的年级数据统计
 * 3. 生成年级内各班级的成绩对比分析
 * 4. 显示全年级学生排名数据
 * 5. 支持多维度的数据可视化图表
 *
 * 分析内容包括:
 * - 年级各班级的平均分、最高分、最低分对比
 * - 全年级的分数段分布统计
 * - 不同班级之间的及格率、优秀率对比
 * - 学生成绩排名和成绩区间分布
 * - 年级整体教学质量评估指标
 *
 * 关联文件:
 * - controllers/GradeAnalyticsController.php: 年级分析控制器
 * - api/routes/grade.php: 年级数据API路由
 * - api/index.php: API入口
 * - assets/js/grade-analytics.js: 年级分析前端脚本
 * - assets/js/chart.min.js: 图表库
 */
-->
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>年级数据看板</title>
    <link href="../assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/common.css" rel="stylesheet">
    <link href="../assets/css/sweetalert2.min.css" rel="stylesheet">
    <script src="../assets/js/sweetalert2.all.min.js"></script>
    <style>
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
        /* 宽列样式 */
        .analytics-table th:nth-child(1),
        .analytics-table td:nth-child(1) {
            width: 90px !important;
            min-width: 60px !important;
            max-width: 90px !important;
        }
        /* 班级总人数列样式 */
        .analytics-table th:nth-child(2),
        .analytics-table td:nth-child(2),
        .analytics-table th:nth-child(3),
        .analytics-table td:nth-child(3){
            width: 50px !important;
            min-width: 40px !important;
            max-width: 50px !important;
            white-space: normal !important;
            word-break: break-all !important;
            line-height: 1.2 !important;
        }
        /* 总分列样式 */
        .analytics-table th:nth-child(4),
        .analytics-table td:nth-child(4),
        .analytics-table th:nth-child(5),
        .analytics-table td:nth-child(5) {
            width: 70px !important;
            min-width: 50px !important;
            max-width: 70px !important;
        }
        /* 数据分布列（第6-18列）*/
        .analytics-table th:nth-child(n+6):nth-child(-n+18),
        .analytics-table td:nth-child(n+6):nth-child(-n+18),
        .analytics-table tr > *:nth-child(6),  /* 100分列 */
        .analytics-table tr > *:nth-child(9),  /* 89.5-85分列 */
        .analytics-table tr > *[class*="cell-"] {
            width: 40px !important;
            min-width: 40px !important;
            max-width: 50px !important;
            padding: 4px 1px !important;
            box-sizing: border-box !important;
            overflow: hidden !important;
            white-space: nowrap !important;
            font-size: 12px !important;
            line-height: 1.2 !important;
            word-break: break-all !important;
            text-align: center !important;
        }
        /* 最高分最低分列 */
        .analytics-table th:nth-child(19),
        .analytics-table td:nth-child(19),
        .analytics-table th:nth-child(20),
        .analytics-table td:nth-child(20) {
            width: 70px !important;
            min-width: 50px !important;
            max-width: 70px !important;
        }
        /* 及格率优秀率列 */
        .analytics-table th:nth-child(21),
        .analytics-table td:nth-child(21),
        .analytics-table th:nth-child(22),
        .analytics-table td:nth-child(22) {
            width: 80px !important;
            min-width: 80px !important;
            max-width: 80px !important;
        }
        .analytics-table th {
            background-color: #f8f9fa;
            font-weight: 500;
            white-space: nowrap;
        }
        .analytics-title {
            text-align: center;
            margin-bottom: 20px;
            font-size: 16px;
            font-weight: 500;
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
            background-color: #e8f4ff !important;
            color: #0369a1 !important;
        }
        .cell-good {
            background-color: #e8fff0 !important;
            color: #15803d !important;
        }
        .cell-pass {
            background-color: #fff8e8 !important;
            color: #854d0e !important;
        }
        .cell-fail {
            background-color: #ffe8e8 !important;
            color: #991b1b !important;
        }
        /* 表格文字颜色 */
        .text-excellent { color: #0369a1; }
        .text-good { color: #15803d; }
        .text-pass { color: #854d0e; }
        .text-fail { color: #991b1b; }
        .analytics-table td {
            padding: 8px 4px !important;
            font-size: 13px;
            line-height: 1.2;
            vertical-align: middle;
        }
        .analytics-table th {
            padding: 8px 4px !important;
            font-size: 13px;
            white-space: nowrap;
            background-color: #f8f9fa;
            font-weight: 500;
        }
        .distribution-cell {
            min-width: unset !important;
            width: unset !important;
            max-width: unset !important;
        }
        /* 表格中的标题单元格背景色 */
        .analytics-table .title-cell {
            background-color: #f3f4f6 !important;
            color: #4b5563 !important;
            font-weight: 500;
        }

        /* 分页样式 */
        .pagination-container {
            margin-top: 20px;
            margin-bottom: 20px;
        }
        .pagination {
            justify-content: center;
        }
        .page-link {
            padding: 0.5rem 0.75rem;
            margin: 0 2px;
            border-radius: 4px;
        }
        .page-item.active .page-link {
            background-color: #0d6efd;
            border-color: #0d6efd;
        }

        /* 序号列样式 */
        .sequence-number {
            width: 60px;
            text-align: center;
            background-color: #f8f9fa;
        }

        /* 排序按钮组样式 */
        .sort-buttons-group {
            display: none;
        }

        /* 加载动画容器 */
        .loading-container {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255,255,255,0.8);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
        }

        /* 响应式调整 */
        @media (max-width: 768px) {
            .stat-value {
                font-size: 1.5rem;
            }

            .chart-container {
                height: 250px;
            }

            .chart-container.small {
                height: 150px;
            }

            .filter-section {
                padding: 0.75rem;
            }

            .ranking-list {
                max-height: 300px;
            }
        }

        /* 排名列表 */
        .ranking-list {
            max-height: 400px;
            overflow-y: auto;
        }

        .ranking-item {
            display: flex;
            align-items: center;
            padding: 0.75rem 1rem;
            border-bottom: 1px solid #dee2e6;
        }

        .ranking-item:last-child {
            border-bottom: none;
        }

        .ranking-number {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            background: #f8f9fa;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1rem;
            font-weight: 600;
            font-size: 0.875rem;
        }

        .ranking-item.top-3 .ranking-number {
            color: white;
        }

        .ranking-item.rank-1 .ranking-number {
            background: #ffd700;
        }

        .ranking-item.rank-2 .ranking-number {
            background: #c0c0c0;
        }

        .ranking-item.rank-3 .ranking-number {
            background: #cd7f32;
        }

        /* 导出按钮 */
        .export-btn {
            position: relative;
            padding-left: 2.5rem;
        }

        .export-btn i {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
        }


        /* 固定表头样式 */
        #scoreList {
            position: relative;
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
        }
        /* 确保横向滚动时表头也能固定 */
        #scoreList .table-responsive {
            overflow-x: auto;
            max-height: 666px;
        }

        /* 自定义下拉框样式 */
        .custom-select-wrapper {
            position: relative;
            width: inherit;
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
            width: 100%;
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

        /* 浮窗提示样式 */
        .floating-toast {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background-color: rgba(0, 0, 0, 0.85);
            color: white;
            padding: 20px 30px;
            border-radius: 10px;
            z-index: 99999; /* 提高z-index确保在最上层 */
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-width: 250px;
            max-width: 80%;
            text-align: center;
            font-size: 16px;
            font-weight: 500;
            letter-spacing: 0.5px;
        }

        .floating-toast .spinner-border {
            margin-bottom: 15px;
            width: 2.5rem;
            height: 2.5rem;
        }
    </style>
</head>
<body>


    <div class="container-fluid mt-3">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="fas fa-chart-line text-primary me-2"></i>年级数据看板
                        </h5>
                        <div class="d-flex align-items-center gap-3">
                            <div style="width: 160px;">
                                <select class="form-select" id="grade-select">
                                    <option value="">选择年级</option>
                                </select>
                            </div>
                            <div style="width: 160px;">
                                <select class="form-select" id="subject-select">
                                    <option value="">请先选择年级</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <!-- 统计分析表 -->
                        <div class="card mb-4" style="border: none; border-radius: 16px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); background: #ffffff;">
                            <div class="card-header" style="background: transparent; border-bottom: 1px solid rgba(0,0,0,0.05); padding: 1.5rem;">
                                <i class="fas fa-table me-1"></i>
                                <span style="font-weight: 600; color: #2c3e50;">数据报表</span>
                            </div>
                            <div class="card-body" style="padding: 1.5rem;">
                                <div id="analyticsTable" class="table-container" style="display: none;">
                                    <!-- 统计分析表将通过JavaScript动态生成 -->
                                </div>
                            </div>
                        </div>

                        <!-- 学生列表 -->
                        <div class="card mt-4">
                            <div class="card-header">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <i class="fas fa-list me-1"></i>
                                        <span style="font-weight: 600; color: #2c3e50;">学生列表</span>
                                    </div>
                                    <div>
                                        <select class="form-select" id="sortSelect" style="width: 150px;">
                                            <option value="number">按学号排序</option>
                                            <option value="score">按成绩排序</option>
                                            <option value="level">按等级排序</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="card-body">
                                <div id="scoreList" style="max-height: 666px; overflow-y: auto;">
                                    <!-- 成绩列表将通过JavaScript动态生成 -->
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 警告框容器 -->
    <div class="alert-container position-fixed start-50 translate-middle-x" style="z-index: 1060; top: 60px;"></div>

    <!-- 移除加载提示模态框，只保留浮动提示框的样式 -->
    <script src="../assets/js/jquery.min.js"></script>
    <script src="../assets/js/bootstrap.bundle.min.js"></script>
    <script>
        // 使用window对象来存储全局变量
        window.currentPage = window.currentPage || 1;
        window.pageSize = 50;
        window.totalPages = window.totalPages || 0;
        window.allScoreData = window.allScoreData || [];

        // 显示警告
        function showAlert(message, type = 'danger') {
            const alertHtml = `
                <div class="alert alert-${type} alert-dismissible fade show" role="alert">
                    ${message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            `;
            $('.alert-container').append(alertHtml);
            setTimeout(() => {
                $('.alert-container .alert').first().remove();
            }, 3000);
        }

        // 显示加载提示
        function showLoadingToast(message = null) {
            // 移除任何已存在的提示框
            $('.floating-toast').remove();

            // 创建新的提示框
            const toast = $(`
                <div class="floating-toast">
                    <div class="spinner-border text-light" role="status"></div>
                    <div class="mt-2">${message || '请耐心等待，数据加载完成后将自动关闭此窗口'}</div>
                </div>
            `);
            $('body').append(toast);
            return toast;
        }

        // 隐藏加载提示
        function hideLoadingToast() {
            $('.floating-toast').fadeOut(300, function() {
                $(this).remove();
            });
        }

        // 检查待审核成绩修改申请
        function checkPendingScoreEdits(classId, subjectId) {
            return new Promise((resolve, reject) => {
                $.get('../api/index.php?route=score_edit/check_pending_by_grade', {
                    grade_id: classId,
                    subject_id: subjectId
                })
                .done(function(response) {
                    if (response.success) {
                        const result = {
                            hasPendingRequest: response.has_pending_request,
                            count: response.count || 0,
                            pending_classes: response.pending_classes || []
                        };
                        
                        // 如果有待审核记录，显示提示
                        if (result.hasPendingRequest) {
                            // 获取年级和学科名称
                            const gradeName = $('#grade-select option:selected').text();
                            const subjectName = $('#subject-select option:selected').text();
                            
                            // 获取待审核班级列表
                            let pendingClassesText = '';
                            if (result.pending_classes && result.pending_classes.length > 0) {
                                pendingClassesText = `，涉及班级：${result.pending_classes.join('、')}`;
                            }
                            
                            // 显示提示
                            Swal.fire({
                                icon: 'warning',
                                title: '待审核提醒',
                                text: `${gradeName}${subjectName}存在待审核的成绩修改申请${pendingClassesText}，部分数据可能不准确。请先完成审核后再查看。`,
                                confirmButtonText: '我知道了'
                            });
                        }
                        
                        resolve(result);
                    } else {
                        console.warn('检查待审核状态失败：' + (response.error || '未知错误'));
                        // 即使API返回失败也允许用户继续操作
                        resolve({ hasPendingRequest: false, count: 0 });
                    }
                })
                .fail(function(xhr, status, error) {
                    console.warn('检查待审核状态请求失败：', status, error);
                    // 即使API请求失败也允许用户继续操作
                    resolve({ hasPendingRequest: false, count: 0 });
                });
            });
        }

        // 修改加载教师有权限的年级和学科
        function loadTeacherSubjects() {
            return new Promise((resolve, reject) => {
                $.get('../api/index.php?route=score/teacher_subjects')
                    .done(function(response) {
                        if (response.success) {
                            const grades = new Set();

                            if (!response.data || response.data.length === 0) {
                                showAlert('您暂无任何学科的成绩录入权限');
                                reject(new Error('无权限'));
                                return;
                            }

                            // 只处理年级数据
                            response.data.forEach(item => {
                                grades.add(JSON.stringify({
                                    id: item.grade_id,
                                    name: item.grade_name,
                                    code: item.grade_code
                                }));
                            });

                            const gradeSelect = $('#grade-select');
                            gradeSelect.empty();
                            gradeSelect.append('<option value="">选择年级</option>');

                            Array.from(grades).forEach(grade => {
                                const g = JSON.parse(grade);
                                gradeSelect.append(`<option value="${g.id}" data-code="${g.code}">${g.name}</option>`);
                            });

                            resolve();
                        } else {
                            showAlert(response.error || '加载年级和学科失败');
                            reject(new Error(response.error || '加载失败'));
                        }
                    })
                    .fail(function(xhr) {
                        const error = '加载年级和学科失败：' + (xhr.responseJSON?.error || '未知错误');
                        showAlert(error);
                        reject(new Error(error));
                    });
            });
        }

        // 修改加载学科函数
        function loadSubjects(gradeId) {
            if (!gradeId) {
                const subjectSelect = $('#subject-select');
                subjectSelect.empty();
                subjectSelect.append('<option value="">请先选择年级</option>');
                return Promise.resolve();
            }

            return new Promise((resolve, reject) => {
                const subjectSelect = $('#subject-select');
                subjectSelect.empty();
                subjectSelect.append('<option value="">选择学科</option>');

                $.get('../api/index.php?route=score/teacher_subjects', {
                    grade_id: gradeId
                })
                .done(function(response) {
                    if (response.success && response.data) {
                        // 使用Map去重，同时确保只显示当前年级的学科
                        const uniqueSubjects = new Map();

                        response.data.forEach(subject => {
                            // 只处理与当前年级匹配的学科
                            if (subject.grade_id == gradeId && !uniqueSubjects.has(subject.subject_id)) {
                                uniqueSubjects.set(subject.subject_id, subject);
                            }
                        });

                        if (uniqueSubjects.size === 0) {
                            subjectSelect.html('<option value="">暂未录入学科</option>');
                        } else {
                            uniqueSubjects.forEach(subject => {
                                subjectSelect.append(
                                    `<option value="${subject.subject_id}"
                                        data-code="${subject.subject_code}"
                                        data-full-score="${subject.full_score || 100}"
                                        data-excellent-score="${subject.excellent_score || 90}"
                                        data-good-score="${subject.good_score || 80}"
                                        data-pass-score="${subject.pass_score || 60}"
                                    >${subject.subject_name}</option>`
                                );
                            });
                        }
                        resolve();
                    } else {
                        const error = response.error || '加载学科失败';
                        showAlert(error);
                        reject(new Error(error));
                    }
                })
                .fail(function(xhr) {
                    const error = '加载学科失败：' + (xhr.responseJSON?.error || '未知错误');
                    showAlert(error);
                    reject(new Error(error));
                });
            });
        }

        // 加载年级统计数据
        function loadGradeAnalytics() {
            const gradeId = $('#grade-select').val();
            const subjectId = $('#subject-select').val();
            const settingId = window.currentSettingId;

            if (!gradeId || !subjectId || !settingId) {
                console.log('参数不完整:', {gradeId, subjectId, settingId});
                return;
            }

            // 初始化数据存储
            window.classAnalyticsMap = new Map();
            // 添加待审核班级映射对象
            window.pendingEditClasses = {};

            // 清空并显示分析表容器
            $('#analyticsTable').empty().show();
            $('#scoreList').empty();

            // 显示加载提示
            showLoadingToast('请耐心等待，数据加载完成后将自动关闭此窗口');

            // 获取年级和科目名称
            const gradeName = $('#grade-select option:selected').text();
            const subjectName = $('#subject-select option:selected').text();

            // 检查是否已经记录过相同的查看记录
            if (!window.lastViewRecord ||
                window.lastViewRecord !== `${gradeId}-${subjectId}`) {
                window.lastViewRecord = `${gradeId}-${subjectId}`;
                $.ajax({
                    url: '../api/index.php?route=log/add',
                    method: 'POST',
                    data: {
                        action_type: 'view',
                        action_detail: `查看${gradeName}${subjectName}统计数据`
                    },
                    error: function(xhr) {
                        console.error('记录日志失败:', xhr.responseText);
                    }
                });
            }

            // 获取学科的分数线设置
            const excellentScore = parseFloat($('#subject-select option:selected').data('excellent-score') || 90);
            const goodScore = parseFloat($('#subject-select option:selected').data('good-score') || 80);
            const passScore = parseFloat($('#subject-select option:selected').data('pass-score') || 60);

            // 根据分数线动态生成分数段的样式类
            function getScoreRangeClass(score) {
                if (score >= excellentScore) {
                    return 'cell-excellent';
                } else if (score >= goodScore) {
                    return 'cell-good';
                } else if (score >= passScore) {
                    return 'cell-pass';
                } else {
                    return 'cell-fail';
                }
            }

            // 创建表格结构
            const tableHtml = `
                <div class="analytics-title">
                    <h2 class="text-center mb-4">
                        ${gradeName}${subjectName}年级数据统计分析表
                    </h2>
                </div>
                <table class="table table-bordered analytics-table">
                    <thead>
                        <tr>
                            <th rowspan="2">班级</th>
                            <th rowspan="2">总人数</th>
                            <th rowspan="2">到考人数</th>
                            <th rowspan="2">总分</th>
                            <th rowspan="2">平均分</th>
                            <th colspan="13" style="text-align: center;">数据分布</th>
                            <th rowspan="2">最高分</th>
                            <th rowspan="2">最低分</th>
                            <th rowspan="2">及格率</th>
                            <th rowspan="2">优秀率</th>
                        </tr>
                        <tr>
                            <th class="${getScoreRangeClass(100)}">100</th>
                            <th class="${getScoreRangeClass(95)}">99.5<br>/<br>95</th>
                            <th class="${getScoreRangeClass(90)}">94.5<br>/<br>90</th>
                            <th class="${getScoreRangeClass(85)}">89.5<br>/<br>85</th>
                            <th class="${getScoreRangeClass(80)}">84.5<br>/<br>80</th>
                            <th class="${getScoreRangeClass(75)}">79.5<br>/<br>75</th>
                            <th class="${getScoreRangeClass(70)}">74.5<br>/<br>70</th>
                            <th class="${getScoreRangeClass(65)}">69.5<br>/<br>65</th>
                            <th class="${getScoreRangeClass(60)}">64.5<br>/<br>60</th>
                            <th class="${getScoreRangeClass(55)}">59.5<br>/<br>55</th>
                            <th class="${getScoreRangeClass(50)}">54.5<br>/<br>50</th>
                            <th class="${getScoreRangeClass(40)}">49.5<br>/<br>40</th>
                            <th class="${getScoreRangeClass(39)}">40<br>以下</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
                <div class="analytics-footer">
                    <p>为"/"的表示还未完成数据录入。"合计"行中涉及平均值计算的，只计算已完成数据录入的班级，未完成的不参与计算。</p>
                </div>
            `;
            $('#analyticsTable').html(tableHtml).show();

            // 定义全局的分数区间
            window.scoreRanges = [
                { range: '100', score: 100 },
                { range: '99.5-95', score: 95 },
                { range: '94.5-90', score: 90 },
                { range: '89.5-85', score: 85 },
                { range: '84.5-80', score: 80 },
                { range: '79.5-75', score: 75 },
                { range: '74.5-70', score: 70 },
                { range: '69.5-65', score: 65 },
                { range: '64.5-60', score: 60 },
                { range: '59.5-55', score: 55 },
                { range: '54.5-50', score: 50 },
                { range: '49.5-40', score: 40 },
                { range: '40以下', score: 0 }
            ];

            // 并行加载班级列表和成绩列表数据
            Promise.all([
                // 获取班级列表
                $.ajax({
                    url: '../api/index.php?route=class/getList',
                    type: 'GET',
                    data: { grade_id: gradeId }
                }),
                // 获取成绩列表
                $.ajax({
                    url: '../api/index.php?route=grade_analytics/student_rank',
                    type: 'GET',
                    data: {
                        grade_id: gradeId,
                        subject_id: subjectId,
                        setting_id: settingId,
                        get_all: true
                    }
                })
            ]).then(([classResponse, scoreResponse]) => {
                if (!classResponse.success || !scoreResponse.success) {
                    throw new Error('数据加载失败');
                }

                // 处理成绩列表数据
                window.allScoreData = scoreResponse.data.ranks || [];
                if (window.allScoreData.length > 0) {
                    sortScores('number'); // 默认按学号排序
                }

                // 处理班级列表数据
                if (classResponse.data && classResponse.data.length > 0) {
                    const sortedClasses = classResponse.data.sort((a, b) => {
                        const codeA = parseInt(a.class_code) || 0;
                        const codeB = parseInt(b.class_code) || 0;
                        return codeA - codeB;
                    });

                    // 先检查每个班级是否有待审核的成绩修改申请
                    const pendingCheckPromises = sortedClasses.map(classItem => {
                        return checkPendingScoreEdits(classItem.id, subjectId)
                            .then(result => {
                                if (result.hasPendingRequest) {
                                    // 记录有待审核申请的班级
                                    window.pendingEditClasses[`${classItem.id}-${subjectId}`] = {
                                        count: result.count,
                                        class_name: classItem.class_name
                                    };
                                }
                                return classItem;
                            })
                            .catch(error => {
                                console.error(`检查班级 ${classItem.class_name} 待审核状态失败:`, error);
                                return classItem;
                            });
                    });

                    return Promise.all(pendingCheckPromises).then(checkedClasses => {
                        // 检查是否有待审核的记录
                        const hasPendingEdits = Object.keys(window.pendingEditClasses).length > 0;
                        
                        // 如果有待审核的记录，在表格上方添加提示
                        if (hasPendingEdits) {
                            const pendingClassNames = Object.values(window.pendingEditClasses)
                                .map(item => item.class_name)
                                .join('、');
                            
                            $('#analyticsTable table tbody').prepend(`
                                <tr>
                                    <td colspan="22" class="text-center" style="padding: 10px; background-color: #fff3cd; color: #856404;">
                                        <i class="fas fa-exclamation-triangle me-2"></i>
                                        注意：${pendingClassNames} 班级有待审核的成绩修改申请，这些班级的数据暂不显示。请审核完成后再查看。
                                    </td>
                                </tr>
                            `);
                        }

                        // 创建所有班级的统计数据请求
                        const analyticsPromises = checkedClasses.map(classItem => {
                            // 检查班级是否有待审核申请
                            const hasPendingRequest = window.pendingEditClasses[`${classItem.id}-${subjectId}`];
                            
                            // 如果有待审核申请，跳过该班级的数据加载
                            if (hasPendingRequest) {
                                return Promise.resolve();
                            }
                            
                            // 初始化班级数据
                            window.classAnalyticsMap.set(classItem.class_code, {
                                classId: classItem.id,
                                class_code: classItem.class_code,
                                class_info: {
                                    grade_name: gradeName,
                                    class_name: classItem.class_name
                                }
                            });

                            // 返回获取班级统计数据的Promise
                            return $.ajax({
                                url: '../api/index.php?route=analytics/generate',
                                type: 'POST',
                                data: {
                                    grade_id: gradeId,
                                    class_id: classItem.id,
                                    subject_id: subjectId,
                                    setting_id: settingId
                                }
                            }).then(analyticsResponse => {
                                const existingData = window.classAnalyticsMap.get(classItem.class_code) || {};
                                window.classAnalyticsMap.set(classItem.class_code, {
                                    ...existingData,
                                    ...analyticsResponse.data || {},
                                    classId: classItem.id,
                                    class_code: classItem.class_code,
                                    class_info: {
                                        grade_name: gradeName,
                                        class_name: classItem.class_name
                                    }
                                });
                            });
                        });

                        // 等待所有班级统计数据加载完成
                        return Promise.all(analyticsPromises);
                    });
                }
            }).then(() => {
                // 更新表格显示
                const finalAnalytics = Array.from(window.classAnalyticsMap.entries())
                    .sort((a, b) => parseInt(a[0]) - parseInt(b[0]))
                    .map(entry => entry[1]);

                // 更新表格数据
                $('#analyticsTable table tbody').empty();
                finalAnalytics.forEach(data => {
                    // 检查班级是否有待审核申请
                    const hasPendingRequest = window.pendingEditClasses[`${data.classId}-${$('#subject-select').val()}`];
                    
                    // 如果没有待审核申请，显示班级数据
                    if (!hasPendingRequest) {
                        updateAnalyticsTable(data.classId, data);
                    }
                });
                addTotalRow();

                // 隐藏加载提示
                hideLoadingToast();
            }).catch(error => {
                console.error('数据加载失败:', error);
                showAlert('数据加载失败，请刷新页面重试');
                hideLoadingToast();
            });
        }

        // 添加计算合计行的函数
        function addTotalRow() {
            if (!window.classAnalyticsMap || window.classAnalyticsMap.size === 0) return;

            let totalData = {
                total_students: 0,
                attended_students: 0,
                total_score: 0,
                max_score: -Infinity,
                min_score: Infinity,
                score_distribution: new Array(13).fill(0),
                valid_class_count: 0 // 用于记录有效的班级数量
            };

            // 使用排序后的数据计算总和
            const sortedAnalytics = Array.from(window.classAnalyticsMap.entries())
                .sort((a, b) => parseInt(a[0]) - parseInt(b[0]))
                .map(entry => entry[1]);

            // 计算总和
            sortedAnalytics.forEach(data => {
                // 检查班级是否有有效数据
                if (data && data.total_students && data.total_students !== '/' &&
                    data.attended_students && data.attended_students !== '/' &&
                    parseInt(data.total_students) > 0 && parseInt(data.attended_students) > 0) {
                    totalData.valid_class_count++;
                    totalData.total_students += parseInt(data.total_students) || 0;
                    totalData.attended_students += parseInt(data.attended_students) || 0;
                    totalData.total_score += parseFloat(data.total_score) || 0;

                    // 修改最高分和最低分的计算逻辑，直接使用表格中显示的数据
                    const maxScoreCell = $(`#class-${data.classId} td:nth-child(19)`).text();
                    const minScoreCell = $(`#class-${data.classId} td:nth-child(20)`).text();
                    
                    const maxScore = maxScoreCell === '/' ? -Infinity : Number(maxScoreCell);
                    const minScore = minScoreCell === '/' ? Infinity : Number(minScoreCell);
                    
                    // 更新最高分，使用表格中显示的数据
                    if (maxScore > totalData.max_score) {
                        totalData.max_score = maxScore;
                    }
                    
                    // 更新最低分，使用表格中显示的数据
                    if (minScore < totalData.min_score) {
                        totalData.min_score = minScore;
                    }

                    // 合并分数分布数据
                    try {
                        const distribution = typeof data.score_distribution === 'string' ?
                            JSON.parse(data.score_distribution) :
                            data.score_distribution || {};

                        const distributionArray = [
                            distribution['100'] || 0,
                            distribution['99.5-95'] || 0,
                            distribution['94.5-90'] || 0,
                            distribution['89.5-85'] || 0,
                            distribution['84.5-80'] || 0,
                            distribution['79.5-75'] || 0,
                            distribution['74.5-70'] || 0,
                            distribution['69.5-65'] || 0,
                            distribution['64.5-60'] || 0,
                            distribution['59.5-55'] || 0,
                            distribution['54.5-50'] || 0,
                            distribution['49.5-40'] || 0,
                            distribution['40以下'] || 0
                        ];
                        distributionArray.forEach((count, index) => {
                            totalData.score_distribution[index] += parseInt(count) || 0;
                        });
                    } catch (e) {
                        console.error('解析分数分布数据失败:', e);
                    }
                }
            });

            // 如果没有任何有效数据，显示"/"
            if (totalData.valid_class_count === 0) {
                const totalRowHtml = `
                    <tr class="total-row">
                        <td style="font-weight: 600; font-size: 14px;">合计</td>
                        <td style="font-weight: 600;">/</td>
                        <td style="font-weight: 600;">/</td>
                        <td style="font-weight: 600;">/</td>
                        <td style="font-weight: 600;">/</td>
                        ${new Array(13).fill('<td style="font-weight: 600;">/</td>').join('')}
                        <td style="font-weight: 600;">/</td>
                        <td style="font-weight: 600;">/</td>
                        <td style="font-weight: 600;">/</td>
                        <td style="font-weight: 600;">/</td>
                    </tr>
                `;
                $('#analyticsTable table tbody').append(totalRowHtml);
                return;
            }

            // 计算年级平均分
            const averageScore = totalData.attended_students > 0 ?
                formatNumber(totalData.total_score / totalData.attended_students, 2) : '/';

            // 计算年级及格率和优秀率
            let passRate = '/';
            let excellentRate = '/';

            if (totalData.valid_class_count > 0) {
                // 获取优秀分数线和及格分数线
                const excellentScore = parseFloat($('#subject-select option:selected').data('excellent-score') || 90);
                const passScore = parseFloat($('#subject-select option:selected').data('pass-score') || 60);

                // 计算优秀和及格人数
                let totalExcellentStudents = 0;
                let totalPassStudents = 0;
                let totalStudentsForRate = 0;

                // 遍历所有班级，累加优秀人数和及格人数
                sortedAnalytics.forEach(data => {
                    if (data && data.attended_students && data.attended_students !== '/' &&
                        parseInt(data.attended_students) > 0) {

                        const attendedStudents = parseInt(data.attended_students);
                        totalStudentsForRate += attendedStudents;

                        // 使用班级的优秀率和及格率计算人数
                        if (data.excellent_rate !== undefined && data.excellent_rate !== null) {
                            const excellentRate = parseFloat(data.excellent_rate.toString().replace('%', ''));
                            if (!isNaN(excellentRate)) {
                                totalExcellentStudents += (excellentRate / 100) * attendedStudents;
                            }
                        }

                        if (data.pass_rate !== undefined && data.pass_rate !== null) {
                            const passRate = parseFloat(data.pass_rate.toString().replace('%', ''));
                            if (!isNaN(passRate)) {
                                totalPassStudents += (passRate / 100) * attendedStudents;
                            }
                        }
                    }
                });

                // 计算总体优秀率和及格率
                if (totalStudentsForRate > 0) {
                    let passRateValue = (totalPassStudents / totalStudentsForRate) * 100;
                    let excellentRateValue = (totalExcellentStudents / totalStudentsForRate) * 100;

                    // 保存原始数值（不带百分号）供后续使用
                    totalData.passRateValue = passRateValue;
                    totalData.excellentRateValue = excellentRateValue;

                    // 格式化显示用的值（带百分号）
                    passRate = formatNumber(passRateValue, 2) + '%';
                    excellentRate = formatNumber(excellentRateValue, 2) + '%';
                }
            }

            // 创建合计行HTML
            const totalRowHtml = `
                <tr class="total-row">
                    <td style="font-weight: 600; font-size: 14px;">合计</td>
                    <td style="font-weight: 600;">${totalData.total_students}</td>
                    <td style="font-weight: 600;">${totalData.attended_students}</td>
                    <td style="font-weight: 600;">${formatNumber(totalData.total_score, 1)}</td>
                    <td style="font-weight: 600;">${averageScore}</td>
                    ${totalData.score_distribution.map(count => `
                        <td style="font-weight: 600;">${count}</td>
                    `).join('')}
                    <td style="font-weight: 600;">${totalData.max_score === -Infinity ? '/' : formatNumber(Number(totalData.max_score), 1)}</td>
                    <td style="font-weight: 600;">${totalData.min_score === Infinity ? '/' : formatNumber(Number(totalData.min_score), 1)}</td>
                    <td style="font-weight: 600;">${passRate}</td>
                    <td style="font-weight: 600;">${excellentRate}</td>
                </tr>
            `;

            // 添加合计行到表格
            $('#analyticsTable table tbody').append(totalRowHtml);
        }

        // 更新统计表格的单个班级数据
        function updateAnalyticsTable(classId, data) {
            // 检查班级是否有数据
            const hasData = data && data.total_students && data.total_students !== '/' &&
                           data.attended_students && data.attended_students !== '/' &&
                           parseInt(data.total_students) > 0 && parseInt(data.attended_students) > 0;

            // 获取班级名称
            const className = data?.class_info ?
                `${data.class_info.grade_name}${data.class_info.class_name}` :
                '未知班级';

            // 所有数据默认显示为"/"
            const defaultData = {
                total_students: '/',
                attended_students: '/',
                total_score: '/',
                average_score: '/',
                max_score: '/',
                min_score: '/',
                pass_rate: '/',
                excellent_rate: '/',
                score_distribution: new Array(13).fill('/')
            };

            // 如果有数据，则使用实际数据
            const displayData = hasData ? {
                total_students: parseInt(data.total_students) || '/',
                attended_students: parseInt(data.attended_students) || '/',
                total_score: formatNumber(parseFloat(data.total_score) || 0, 1),
                average_score: formatNumber(parseFloat(data.average_score) || 0, 2),
                max_score: formatNumber(parseFloat(data.max_score) || 0, 1),
                min_score: formatNumber(parseFloat(data.min_score) || 0, 1),
                // 存储原始值供后续计算使用
                pass_rate_value: parseFloat(data.pass_rate) || 0,
                excellent_rate_value: parseFloat(data.excellent_rate) || 0,
                // 格式化显示用的值（带百分号）
                pass_rate: formatNumber(parseFloat(data.pass_rate) || 0, 2) + '%',
                excellent_rate: formatNumber(parseFloat(data.excellent_rate) || 0, 2) + '%',
                score_distribution: []
            } : defaultData;

            // 处理分数分布数据
            if (hasData && data.score_distribution) {
                try {
                    const distributionData = typeof data.score_distribution === 'string' ?
                        JSON.parse(data.score_distribution) :
                        data.score_distribution;

                    // 如果存在0分的数据，将其加入到40以下的分数段中
                    if (distributionData['0'] !== undefined) {
                        distributionData['40以下'] = (parseInt(distributionData['40以下']) || 0) + (parseInt(distributionData['0']) || 0);
                        delete distributionData['0'];
                    }

                    displayData.score_distribution = window.scoreRanges.map(range => {
                        if (range.range === '100') {
                            return distributionData['100'] || 0;
                        } else if (range.range === '40以下') {
                            return distributionData['40以下'] || 0;
                        } else {
                            return distributionData[range.range] || 0;
                        }
                    });
                } catch (e) {
                    console.error('分数分布数据解析失败:', e);
                    displayData.score_distribution = new Array(13).fill('/');
                }
            }

            // 获取学科的分数线设置
            const excellentScore = parseFloat($('#subject-select option:selected').data('excellent-score') || 90);
            const goodScore = parseFloat($('#subject-select option:selected').data('good-score') || 80);
            const passScore = parseFloat($('#subject-select option:selected').data('pass-score') || 60);

            // 根据分数线动态生成单元格样式
            const getCellClass = (score) => {
                if (!hasData || score === '/') return '';
                const numScore = parseFloat(score);
                if (isNaN(numScore)) return '';

                // 使用分数线设置动态判断等级
                if (numScore >= excellentScore) {
                    return 'cell-excellent';
                } else if (numScore >= goodScore) {
                    return 'cell-good';
                } else if (numScore >= passScore) {
                    return 'cell-pass';
                } else {
                    return 'cell-fail';
                }
            };

            // 创建或更新班级行
            const rowHtml = `
                <tr id="class-${classId}">
                    <td>${className}</td>
                    <td>${displayData.total_students}</td>
                    <td>${displayData.attended_students}</td>
                    <td>${displayData.total_score}</td>
                    <td>${displayData.average_score}</td>
                    ${displayData.score_distribution.map((count, index) => {
                        // 获取分数段的起始分数
                        const range = window.scoreRanges[index];
                        const score = range.score;
                        // 根据分数段的起始分数决定样式
                        const cellClass = hasData ? getCellClass(score) : '';
                        return `<td class="${cellClass}">${count}</td>`;
                    }).join('')}
                    <td>${displayData.max_score}</td>
                    <td>${displayData.min_score}</td>
                    <td>${displayData.pass_rate}</td>
                    <td>${displayData.excellent_rate}</td>
                </tr>
            `;

            const existingRow = $(`#class-${classId}`);
            if (existingRow.length) {
                existingRow.replaceWith(rowHtml);
            } else {
                $('#analyticsTable table tbody').append(rowHtml);
            }
        }

        // 加载成绩列表
        function loadScoreList() {
            const gradeId = $('#grade-select').val();
            const subjectId = $('#subject-select').val();
            const settingId = window.currentSettingId;

            if (!gradeId || !subjectId || !settingId) {
                console.log('参数不完整:', {gradeId, subjectId, settingId});
                return;
            }

            // 显示加载提示
            $('#scoreList').html('<div class="text-center p-3"><div class="spinner-border text-primary" role="status"></div><div class="mt-2">正在加载数据，请稍候...</div></div>');

            $.ajax({
                url: '../api/index.php?route=grade_analytics/student_rank',
                type: 'GET',
                data: {
                    grade_id: gradeId,
                    subject_id: subjectId,
                    setting_id: settingId,
                    get_all: true  // 添加参数表示获取所有数据
                },
                success: function(response) {
                    if (response.success) {
                        console.log('成绩列表加载成功:', response.data);
                        window.allScoreData = response.data.ranks || [];

                        // 如果没有数据，显示提示信息
                        if (!window.allScoreData.length) {
                            $('#scoreList').html('<div class="alert alert-info">暂无学生成绩数据</div>');
                            return;
                        }

                        // 默认按学号排序
                        sortScores('number');
                    } else {
                        console.error('获取成绩列表失败:', response.error);
                        showAlert(response.error || '获取成绩列表失败');
                        $('#scoreList').html('<div class="alert alert-danger">获取数据失败</div>');
                    }
                },
                error: function(xhr) {
                    console.error('获取成绩列表请求失败:', xhr);
                    showAlert('获取成绩列表失败：' + (xhr.responseJSON?.error || '未知错误'));
                    $('#scoreList').html('<div class="alert alert-danger">获取数据失败</div>');
                }
            });
        }

        // 更新成绩列表显示
        function updateScoreList(data) {
            if (!data || !data.length) {
                $('#scoreList').html('<div class="alert alert-info">暂无数据</div>');
                return;
            }

            let html = `
                <div class="table-responsive">
                    <table class="table table-bordered table-hover">
                        <thead>
                            <tr>
                                <th style="width: 80px;" class="text-center">序号</th>
                                <th style="width: 120px;" class="text-center">学号</th>
                                <th style="width: 100px;" class="text-center">姓名</th>
                                <th style="width: 100px;" class="text-center">班级</th>
                                <th style="width: 100px;" class="text-center">成绩</th>
                                <th style="width: 100px;" class="text-center">等级</th>
                            </tr>
                        </thead>
                        <tbody>
            `;

            data.forEach((item, index) => {
                let scoreText = '';
                let levelClass = '';
                let levelText = '';

                // 首先判断是否缺考
                if (item.is_absent === '1' || item.is_absent === true || item.score_level === '缺考') {
                    scoreText = '缺考';
                    levelClass = 'level-absent';
                    levelText = '缺考';
                } else if (item.total_score !== null) {
                    // 有成绩的情况
                    const score = parseFloat(item.total_score);
                    if (!isNaN(score)) {
                        scoreText = score % 1 === 0 ? score.toFixed(0) : score.toFixed(1);

                        // 计算等级
                        const excellentScore = parseFloat($('#subject-select option:selected').data('excellent-score') || 90);
                        const goodScore = parseFloat($('#subject-select option:selected').data('good-score') || 80);
                        const passScore = parseFloat($('#subject-select option:selected').data('pass-score') || 60);

                        if (score >= excellentScore) {
                            levelClass = 'level-excellent';
                            levelText = '优秀';
                        } else if (score >= goodScore) {
                            levelClass = 'level-good';
                            levelText = '良好';
                        } else if (score >= passScore) {
                            levelClass = 'level-pass';
                            levelText = '合格';
                        } else {
                            levelClass = 'level-fail';
                            levelText = '待合格';
                        }
                    }
                }

                html += `
                    <tr>
                        <td class="text-center">${index + 1}</td>
                        <td class="text-center">${item.student_number || '-'}</td>
                        <td class="text-center">${item.student_name}</td>
                        <td class="text-center">${item.class_name}</td>
                        <td class="text-center">${scoreText}</td>
                        <td class="text-center"><span class="score-level ${levelClass}">${levelText}</span></td>
                    </tr>
                `;
            });

            html += '</tbody></table></div>';
            $('#scoreList').html(html);
        }

        // 排序成绩列表
        function sortScores(type) {
            if (!allScoreData.length) return;

            // 显示加载提示
            $('#scoreList').html('<div class="text-center p-3"><div class="spinner-border text-primary" role="status"></div><div class="mt-2">正在排序数据，请稍候...</div></div>');

            // 使用setTimeout确保加载提示能够显示
            setTimeout(() => {
                switch (type) {
                    case 'number':
                        // 按学号排序
                        allScoreData.sort((a, b) => {
                            // 处理学号为空的情况
                            const numA = a.student_number || '';
                            const numB = b.student_number || '';
                            // 确保数字部分按数值大小排序
                            const numPartA = parseInt(numA.match(/\d+/)?.[0] || '0');
                            const numPartB = parseInt(numB.match(/\d+/)?.[0] || '0');
                            return numPartA - numPartB;
                        });
                        break;
                    case 'score':
                        // 按成绩排序（从高到低）
                        allScoreData.sort((a, b) => {
                            // 处理缺考情况
                            if (a.is_absent === '1' || a.is_absent === true) return 1;
                            if (b.is_absent === '1' || b.is_absent === true) return -1;
                            // 使用total_score进行排序
                            return (parseFloat(b.total_score) || 0) - (parseFloat(a.total_score) || 0);
                        });
                        break;
                    case 'level':
                        // 按等级排序（优秀>良好>合格>待合格>缺考）
                        allScoreData.sort((a, b) => {
                            // 获取等级权重
                            function getLevelWeight(level) {
                                switch(level) {
                                    case '优秀': return 4;
                                    case '良好': return 3;
                                    case '合格': return 2;
                                    case '待合格': return 1;
                                    case '缺考': return 0;
                                    default: return -1;
                                }
                            }

                            const weightA = getLevelWeight(a.score_level);
                            const weightB = getLevelWeight(b.score_level);

                            // 如果等级不同，按等级排序
                            if (weightA !== weightB) {
                                return weightB - weightA;
                            }
                            // 如果等级相同，按分数排序
                            return (parseFloat(b.total_score) || 0) - (parseFloat(a.total_score) || 0);
                        });
                        break;
                }

                // 更新成绩列表显示
                updateScoreList(allScoreData);
            }, 100);
        }

        // 获取成绩等级
        function getScoreLevel(score) {
            const excellentScore = parseFloat($('#subject-select option:selected').data('excellent-score') || 90);
            const goodScore = parseFloat($('#subject-select option:selected').data('good-score') || 80);
            const passScore = parseFloat($('#subject-select option:selected').data('pass-score') || 60);

            if (score >= excellentScore) {
                return { class: 'level-excellent', text: '优秀' };
            } else if (score >= goodScore) {
                return { class: 'level-good', text: '良好' };
            } else if (score >= passScore) {
                return { class: 'level-pass', text: '合格' };
            } else {
                return { class: 'level-fail', text: '待合格' };
            }
        }

        // 格式化数字，如果小数部分为0则显示整数，否则保留指定位数的小数
        function formatNumber(number, decimals) {
            if (isNaN(number)) return '/';
            
            // 将数字四舍五入到指定小数位
            const rounded = Number(Math.round(number + 'e' + decimals) + 'e-' + decimals);
            
            // 检查小数部分是否全为0
            const parts = rounded.toString().split('.');
            if (!parts[1]) {
                return parts[0]; // 如果没有小数部分，直接返回整数部分
            }
            
            // 检查小数部分是否全为0
            const decimalPart = parts[1].slice(0, decimals);
            if (parseInt(decimalPart) === 0) {
                return parts[0]; // 如果小数部分全为0，返回整数部分
            }
            
            // 否则返回带小数的数字
            return rounded.toFixed(decimals);
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

        // 页面加载完成后初始化
        $(document).ready(function() {
            // 获取当前项目ID
            $.get('../api/index.php?route=project/current')
                .then(response => {
                    if (response.success && response.data) {
                        window.currentProjectId = response.data.id;
                        window.currentSettingId = response.data.id;
                    }
                    // 设置默认排序方式为按编号排序
                    $('#sortSelect').val('number');
                    // 加载年级列表
                    loadGrades();
                })
                .catch(error => {
                    console.error('初始化失败:', error);
                    showAlert('初始化失败：' + (error.responseJSON?.error || '未知错误'));
                });

            // 监听年级选择变化
            $('#grade-select').change(function() {
                const gradeId = $(this).val();
                loadSubjects(gradeId);
                $('#analyticsTable').hide();
                $('#scoreList').empty();
            });

            // 监听学科选择变化
            $('#subject-select').change(function() {
                if ($('#grade-select').val() && $(this).val()) {
                    const gradeId = $('#grade-select').val();
                    const subjectId = $(this).val();
                    
                    // 检查是否有待审核记录
                    checkPendingScoreEdits(gradeId, subjectId)
                        .then(() => {
                            // 无论是否有待审核记录，都继续加载数据
                            // 显示加载提示
                            showLoadingToast('正在加载年级数据分析表，请稍候...');
                            // 加载年级统计数据
                            loadGradeAnalytics();
                        })
                        .catch(error => {
                            console.error('检查待审核状态失败:', error);
                            // 即使检查失败也继续加载数据
                            showLoadingToast('正在加载年级数据分析表，请稍候...');
                            loadGradeAnalytics();
                        });
                } else {
                    $('#analyticsTable').hide();
                    $('#scoreList').empty();
                }
            });

            // 更新排序事件监听
            $('#sortSelect').change(function() {
                const sortType = $(this).val();
                sortScores(sortType);
            });

            // 初始化自定义下拉框
            initCustomSelects();

            // 在加载年级和学科后重新初始化
            $('#grade-select').on('change', function() {
                setTimeout(initCustomSelects, 100);
            });
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
    </script>
</body>
</html>


