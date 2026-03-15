<!--
/**
 * 文件名: modules/class_analytics.php
 * 功能描述: 班级数据分析模块
 * 
 * 该文件负责:
 * 1. 展示班级成绩整体分析数据
 * 2. 提供学生个体成绩分析
 * 3. 计算班级各科目成绩统计指标
 * 4. 生成班级学生排名表
 * 5. 支持多维度图表化展示班级数据
 * 
 * 分析内容包括:
 * - 班级各科目平均分、最高分、最低分统计
 * - 学生个人成绩单科及总分排名
 * - 班级分数段分布分析
 * - 班级及格率、优秀率统计
 * - 学科间对比分析
 * - 学生成绩波动趋势分析
 * 
 * 关联文件:
 * - controllers/ClassAnalyticsController.php: 班级分析控制器
 * - api/index.php: API入口
 * - assets/js/class-analytics.js: 班级分析前端脚本
 * - assets/js/chart.min.js: 图表库
 */
-->
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>班级数据看板</title>
    <link href="../assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/common.css" rel="stylesheet">
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
        .analytics-table th:nth-child(2),
        .analytics-table td:nth-child(2) {
            width: 70px !important;
            min-width: 50px !important;
            max-width: 70px !important;
        }
        .analytics-table th:nth-child(3),
        .analytics-table td:nth-child(3),
        .analytics-table th:nth-child(4),
        .analytics-table td:nth-child(4) {
            width: 80px !important;
            min-width: 70px !important;
            max-width: 80px !important;
        }
        /* 数据分布列（第5-17列）*/
        .analytics-table th:nth-child(n+5):nth-child(-n+17),
        .analytics-table td:nth-child(n+5):nth-child(-n+17),
        .analytics-table tr > *:nth-child(5),  /* 100分列 */
        .analytics-table tr > *:nth-child(8),  /* 89.5-85分列 */
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
        .analytics-table th:nth-child(18),
        .analytics-table td:nth-child(18),
        .analytics-table th:nth-child(19),
        .analytics-table td:nth-child(19) {
            width: 50px !important;
            min-width: 50px !important;
            max-width: 50px !important;
        }
        /* 及格率优秀率列 */
        .analytics-table th:nth-child(20),
        .analytics-table td:nth-child(20),
        .analytics-table th:nth-child(21),
        .analytics-table td:nth-child(21) {
            width: 70px !important;
            min-width: 70px !important;
            max-width: 70px !important;
        }
        /* 分数段文字大小 */
        .analytics-table th:nth-child(n+5):nth-child(-n+17) {
            font-size: 12px !important;
            line-height: 1.2 !important;
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
            width: fit-content !important;
            margin-left: auto;
            margin-right: auto;
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
 
        .dashboard-card {
            height: 100%;
            transition: transform 0.2s;
        }
        
        .dashboard-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-value {
            font-size: 2rem;
            font-weight: 600;
            color: #2c3e50;
        }
        
        .stat-label {
            color: #6c757d;
            font-size: 0.875rem;
        }
        
        .trend-indicator {
            font-size: 0.875rem;
            padding: 0.25rem 0.5rem;
            border-radius: 20px;
        }
        
        .trend-up {
            background-color: rgba(25, 135, 84, 0.1);
            color: #198754;
        }
        
        .trend-down {
            background-color: rgba(220, 53, 69, 0.1);
            color: #dc3545;
        }
        
        /* 图表容器 */
        .chart-container {
            position: relative;
            height: 300px;
            margin: 1rem 0;
        }
        
        .chart-container.small {
            height: 200px;
        }
        
        /* 数据筛选器 */
        .filter-section {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
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

        /* 强制所有数据分布列宽度一致 */
        .analytics-table tr > *:nth-child(5),  /* 100分列 */
        .analytics-table tr > *:nth-child(8)   /* 89.5-85分列 */ {
            min-width: 30px !important;
            width: 30px !important;
            max-width: 30px !important;
        }

        /* 表格中的标题单元格背景色 */
        .analytics-table .title-cell {
            background-color: #f3f4f6 !important;
            color: #4b5563 !important;
            font-weight: 500;
        }

        /* 排序按钮组样式 */
        .sort-buttons-group {
            margin-top: 30px;
            margin-bottom: 20px;
            padding: 10px 0;
            border-top: 1px solid #eee;
            border-bottom: 1px solid #eee;
            background-color: #f8f9fa;
        }
        .sort-buttons-group .btn-group {
            display: flex;
            justify-content: center;
        }
        .sort-buttons-group .btn {
            padding: 8px 20px;
            font-size: 14px;
            position: relative;
            transition: all 0.3s ease;
        }
        .sort-buttons-group .btn.active {
            background-color: #0d6efd;
            color: #fff;
            border-color: #0d6efd;
            box-shadow: 0 2px 4px rgba(13, 110, 253, 0.2);
        }

        /* 表格单元格居中对齐样式 */
        .analytics-table th,
        .analytics-table td,
        .table th,
        .table td {
            text-align: center !important;
            vertical-align: middle !important;
        }

        /* 分数段样式 */
        .score-range {
            font-size: 12px;
            line-height: 1.2;
        }

        /* 表格内容居中对齐 */
        .table-responsive table th,
        .table-responsive table td {
            text-align: center !important;
            vertical-align: middle !important;
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

        /* 表格容器样式 */
        .table-container {
            margin-top: 20px;
            overflow-x: auto;
            max-width: 100% !important;
            width: 100% !important;
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
                            <i class="fas fa-chart-bar text-primary me-2"></i>班级数据看板
                        </h5>
                        <div class="d-flex align-items-center gap-3">
                            <div style="width: 160px;">
                                <select class="form-select" id="gradeFilter">
                                    <option value="">选择年级</option>
                                </select>
                            </div>
                            <div style="width: 160px;">
                                <select class="form-select" id="subjectFilter">
                                    <option value="">请先选择年级</option>
                                </select>
                            </div>
                            <div style="width: 160px;">
                                <select class="form-select" id="classFilter">
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

                        <!-- 学生成绩列表 -->
                        <div class="card">
                            <div class="card-header">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <i class="fas fa-list me-1"></i>
                                        <span style="font-weight: 600; color: #2c3e50;">学生列表</span>
                                    </div>
                                    <div>
                                        <select id="sort-select" class="form-select" style="width: 150px;">
                                            <option value="number">按编号排序</option>
                                            <option value="score">按成绩排序</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="card-body">
                                <div id="scoreList">
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
    <div class="alert-container position-fixed start-50 translate-middle-x" style="z-index: 1060; top: 60px;">
    </div>

    <script src="../assets/js/jquery.min.js"></script>
    <script src="../assets/js/bootstrap.bundle.min.js"></script>
    <script>
        // 显示警告
        function showAlert(message, type = 'danger') {
            const alertHtml = `
                <div class="alert alert-${type} alert-dismissible fade show" role="alert">
                    ${message}
                </div>
            `;
            
            const container = $('.alert-container');
            const alert = $(alertHtml);
            container.append(alert);
            
            setTimeout(() => {
                alert.fadeOut(300, function() {
                    $(this).remove();
                });
            }, 3000);
        }

        // 显示成功消息
        function showSuccess(message) {
            showAlert(message, 'success');
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

                            const gradeSelect = $('#gradeFilter');
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

        // 添加加载学科函数
        function loadSubjects(gradeId) {
            if (!gradeId) {
                const subjectSelect = $('#subjectFilter');
                subjectSelect.empty();
                subjectSelect.append('<option value="">请先选择年级</option>');
                return Promise.resolve();
            }

            return new Promise((resolve, reject) => {
                const subjectSelect = $('#subjectFilter');
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

        // 加载班级列表
        function loadClasses(gradeId) {
            return new Promise((resolve, reject) => {
                const classSelect = $('#classFilter');
                const subjectId = $('#subjectFilter').val();
                
                // 清空班级选择器和相关显示
                classSelect.empty();
                $('#scoreList').empty();
                $('#analyticsTable').hide();
                
                if (!gradeId) {
                    classSelect.append('<option value="">请先选择年级</option>');
                    resolve();
                    return;
                }

                if (!subjectId) {
                    classSelect.append('<option value="">请先选择学科</option>');
                    resolve();
                    return;
                }

                $.get('../api/index.php?route=score/teacher_classes', { 
                    grade_id: gradeId,
                    subject_id: subjectId
                })
                .done(function(response) {
                    if (response.success) {
                        classSelect.empty();
                        classSelect.append('<option value="">选择班级</option>');
                        
                        if (response.data && response.data.length > 0) {
                            response.data.forEach(function(cls) {
                                classSelect.append(`<option value="${cls.id}">${cls.class_name}</option>`);
                            });
                            resolve();
                        } else {
                            classSelect.append('<option value="" disabled>该年级下暂无班级</option>');
                            resolve(); // 改为resolve，因为这不是错误情况
                        }
                    } else {
                        reject(new Error(response.error || '加载班级失败'));
                    }
                })
                .fail(function(xhr) {
                    reject(new Error(xhr.responseJSON?.error || '加载班级失败'));
                });
            });
        }

        // 生成统计分析表
        function generateAnalyticsTable(analytics, schoolInfo) {
            /* 记录传入的原始数据
            console.log('generateAnalyticsTable 输入数据:', {
                analytics: analytics,
                schoolInfo: schoolInfo
            });
            */
            // 设置默认值，避免undefined
            const defaultAnalytics = {
                total_students: 0,
                attended_students: 0,
                total_score: 0,
                average_score: 0,
                max_score: 0,
                min_score: 0,
                excellent_rate: 0,
                pass_rate: 0,
                score_distribution: {}
            };

            // 合并默认值和实际数据
            analytics = Object.assign({}, defaultAnalytics, analytics || {});
            schoolInfo = Object.assign({}, {
                school_name: '',
                semester: '',
                project_name: ''
            }, schoolInfo || {});

            /* 记录处理后的数据
            console.log('处理后的统计数据:', {
                analytics: analytics,
                schoolInfo: schoolInfo
            });
            */
            let distribution = {};
            try {
                if (analytics && analytics.score_distribution) {
                    if (typeof analytics.score_distribution === 'string') {
                        distribution = JSON.parse(analytics.score_distribution);
                    } else if (typeof analytics.score_distribution === 'object') {
                        distribution = analytics.score_distribution;
                    }
                }
                //console.log('分数分布数据:', distribution);
            } catch (error) {
                console.error('解析分数分布数据失败:', error);
            }

            // 确保分布数据的所有区间都有值
            const defaultDistribution = {
                '100': 0,
                '99.5-95': 0,
                '94.5-90': 0,
                '89.5-85': 0,
                '84.5-80': 0,
                '79.5-75': 0,
                '74.5-70': 0,
                '69.5-65': 0,
                '64.5-60': 0,
                '59.5-55': 0,
                '54.5-50': 0,
                '49.5-40': 0,
                '40以下': 0
            };
            distribution = Object.assign({}, defaultDistribution, distribution);
            
            // 格式化数字，避免undefined和NaN
            const formatNumber = (num, isCount = false) => {
                if (num === null || num === undefined) return isCount ? '0' : '0.00';
                const parsedNum = parseFloat(num);
                if (isNaN(parsedNum)) return isCount ? '0' : '0.00';
                
                // 如果是人数字段，返回整数
                if (isCount) {
                    return Math.round(parsedNum).toString();
                }
                
                // 如果是整数
                if (Math.floor(parsedNum) === parsedNum) {
                    return parsedNum.toString();
                }
                
                // 如果有小数，保留两位
                return parsedNum.toFixed(2);
            };

            // 格式化百分比
            const formatPercent = (num) => {
                if (num === null || num === undefined) return '0.00';
                const parsedNum = parseFloat(num);
                if (isNaN(parsedNum)) return '0.00';
                
                // 如果是整数
                if (Math.floor(parsedNum) === parsedNum) {
                    return parsedNum.toString();
                }
                
                // 如果有小数，保留两位
                return parsedNum.toFixed(2);
            };

            // 获取班级年级和班级号
            const classText = $('#classFilter option:selected').text();
            const gradeText = $('#gradeFilter option:selected').text();
            const classInfo = gradeText + classText;
            
            // 获取学科的分数线设置
            const excellentScore = parseFloat($('#subjectFilter option:selected').data('excellent-score') || 90);
            const goodScore = parseFloat($('#subjectFilter option:selected').data('good-score') || 80);
            const passScore = parseFloat($('#subjectFilter option:selected').data('pass-score') || 60);

            // 根据分数线动态生成单元格样式
            const getCellClass = (score) => {
                if (score >= excellentScore) return 'cell-excellent';
                if (score >= goodScore) return 'cell-good';
                if (score >= passScore) return 'cell-pass';
                return 'cell-fail';
            };

            // 定义分数区间和对应的分数值
            const scoreRanges = [
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

            // 生成分数区间的表头和数据行的单元格样式
            const distributionHeaders = scoreRanges.map(range => 
                `<th class="${getCellClass(range.score)}">${range.range.includes('-') ? range.range.replace('-', '<br>/<br>') : range.range}</th>`
            ).join('');

            const distributionCells = scoreRanges.map(range => 
                `<td class="${getCellClass(range.score)}">${distribution[range.range] || 0}</td>`
            ).join('');

            return `
                <div class="analytics-title">
                    <h2 class="text-center mb-4">
                        ${schoolInfo.school_name}${schoolInfo.semester}${$('#gradeFilter option:selected').text()}${$('#classFilter option:selected').text()}${$('#subjectFilter option:selected').text()}${schoolInfo.project_name}数据统计分析表
                    </h2>
                </div>
                <table class="analytics-table">
                    <tr>
                        <th rowspan="2">班别</th>
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
                        ${distributionHeaders}
                    </tr>
                    <tr>
                        <td rowspan="2">${classInfo}</td>
                        <td rowspan="2">${formatNumber(analytics.attended_students, true)}</td>
                        <td rowspan="2">${formatNumber(analytics.total_score)}</td>
                        <td rowspan="2">${formatNumber(analytics.average_score)}</td>
                        ${distributionCells}
                        <td rowspan="2">${formatNumber(analytics.max_score)}</td>
                        <td rowspan="2">${formatNumber(analytics.min_score)}</td>
                        <td rowspan="2">${formatPercent(analytics.pass_rate)}%</td>
                        <td rowspan="2">${formatPercent(analytics.excellent_rate)}%</td>
                    </tr>
                    <tr>
                        <td>${distribution['100'] || 0}</td>
                        <td colspan="2">${distribution['99.5-90'] || 0}</td>
                        <td colspan="2">${distribution['89.5-80'] || 0}</td>
                        <td colspan="2">${distribution['79.5-70'] || 0}</td>
                        <td colspan="2">${distribution['69.5-60'] || 0}</td>
                        <td colspan="2">${distribution['59.5-50'] || 0}</td>
                        <td>${distribution['49.5-40'] || 0}</td>
                        <td>${distribution['40以下'] || 0}</td>
                    </tr>
                    <tr>
                        <td colspan="1" class="title-cell" style="text-align: center;">实际人数</td>
                        <td colspan="1" style="text-align: center;">${formatNumber(analytics.total_students, true)}人</td>
                        <td colspan="1" class="title-cell" style="text-align: center;">缺考人数</td>
                        <td colspan="1" style="text-align: center;">${formatNumber(analytics.absent_students, true)}人</td>
                        <td colspan="2" class="cell-excellent" style="text-align: center;">优秀人数</td>
                        <td style="text-align: center;">${formatNumber(analytics.excellent_count, true)}</td>
                        <td colspan="2" class="cell-good" style="text-align: center;">良好人数</td>
                        <td style="text-align: center;">${formatNumber(analytics.good_count, true)}</td>
                        <td colspan="2" class="cell-pass" style="text-align: center;">合格人数</td>
                        <td style="text-align: center;">${formatNumber(analytics.pass_count, true)}</td>
                        <td colspan="3" class="cell-fail" style="text-align: center;">待合格人数</td>
                        <td colspan="1" style="text-align: center;">${formatNumber(analytics.fail_count, true)}</td>
                        <td colspan="4"></td>
                    </tr>
                </table>
            `;
        }

        // 加载学生成绩列表
        function loadScores() {
            const classId = $('#classFilter').val();
            const subjectId = $('#subjectFilter').val();
            const gradeId = $('#gradeFilter').val();
            
            if (!gradeId || !classId || !subjectId) {
                $('#scoreList').html('<div class="alert alert-info">请选择年级、学科和班级</div>');
                $('#analyticsTable').hide();
                return;
            }

            // 记录操作日志
            const gradeName = $('#gradeFilter option:selected').text();
            const className = $('#classFilter option:selected').text();
            const subjectName = $('#subjectFilter option:selected').text();

            // 检查是否已经记录过相同的查看记录
            if (!window.lastViewRecord || 
                window.lastViewRecord !== `${gradeId}-${classId}-${subjectId}`) {
                
                // 更新最后查看记录
                window.lastViewRecord = `${gradeId}-${classId}-${subjectId}`;
                
                // 发送日志记录请求
                $.ajax({
                    url: '../api/index.php?route=log/add',
                    method: 'POST',
                    data: {
                        action_type: 'view',
                        action_detail: `查看${gradeName}${className}${subjectName}统计数据`
                    },
                    error: function(xhr) {
                        console.error('记录日志失败:', xhr.responseText);
                    }
                });
            }

            // 显示加载提示
            $('#scoreList').html('<div class="text-center p-3"><div class="spinner-border text-primary" role="status"></div><div class="mt-2">正在整理数据，请稍后...</div></div>');

            // 使用Promise处理数据加载
            $.get('../api/index.php?route=score/student_scores', { 
                class_id: classId,
                subject_id: subjectId
            })
            .then(response => {
                if (!response.success) {
                    throw new Error(response.error || '加载成绩数据失败');
                }
                
                if (!response.data || response.data.length === 0) {
                    $('#scoreList').html('<div class="alert alert-warning">该班级暂无学生成绩数据</div>');
                    $('#analyticsTable').hide();
                    return;
                }

                // 更新成绩列表显示
                updateScoreList(response.data);
                
                // 保存原始数据用于排序
                window.originalScoreData = response.data;

                // 加载统计分析数据
                return loadAnalytics();
            })
            .catch(error => {
                console.error('数据加载失败：', error);
                $('#scoreList').html('<div class="alert alert-danger">加载失败：' + error.message + '</div>');
                $('#analyticsTable').hide();
            });
        }

        // 新增函数：更新成绩列表显示
        function updateScoreList(data) {
            if (!data || !data.length) {
                $('#scoreList').html('<div class="alert alert-info">暂无数据</div>');
                return;
            }

            const subjectName = $('#subjectFilter option:selected').text();
            
            // 创建两栏布局
            let html = '<div class="row">';
            
            // 计算每列显示的数据量
            const halfLength = Math.ceil(data.length / 2);
            
            // 第一列
            html += '<div class="col-md-6">';
            html += generateScoreTable(data.slice(0, halfLength), subjectName);
            html += '</div>';
            
            // 第二列
            html += '<div class="col-md-6">';
            html += generateScoreTable(data.slice(halfLength), subjectName);
            html += '</div>';
            
            html += '</div>';
            
            $('#scoreList').html(html);
        }

        // 生成成绩表格HTML
        function generateScoreTable(data, subjectName) {
            let html = '<div class="table-responsive">';
            html += '<table class="table table-bordered table-hover">';
            html += '<thead><tr>';
            html += '<th class="text-center" style="width: 100px;">编号</th>';
            html += '<th class="text-center" style="width: 120px;">姓名</th>';
            html += `<th class="text-center" style="width: 120px;">${subjectName}成绩</th>`;
            html += '<th class="text-center" style="width: 100px;">等级</th>';
            html += '</tr></thead><tbody>';

            data.forEach(student => {
                let scoreText = '';
                let levelClass = '';
                let levelText = '';
                
                // 首先判断是否缺考
                if (student.is_absent === '1' || student.is_absent === true || student.score_level === '缺考') {
                    scoreText = '缺考';
                    levelClass = 'level-absent';
                    levelText = '缺考';
                } else if (student.total_score !== null) {
                    // 有成绩的情况
                    const score = parseFloat(student.total_score);
                    if (!isNaN(score)) {
                        scoreText = score % 1 === 0 ? score.toFixed(0) : score.toFixed(1);
                        
                        // 计算等级
                        const excellentScore = parseFloat($('#subjectFilter option:selected').data('excellent-score') || 90);
                        const goodScore = parseFloat($('#subjectFilter option:selected').data('good-score') || 80);
                        const passScore = parseFloat($('#subjectFilter option:selected').data('pass-score') || 60);

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

                html += '<tr>';
                html += `<td class="text-center">${student.student_number || ''}</td>`;
                html += `<td class="text-center">${student.student_name}</td>`;
                html += `<td class="text-center">${scoreText}</td>`;
                html += `<td class="text-center"><span class="score-level ${levelClass}">${levelText}</span></td>`;
                html += '</tr>';
            });

            html += '</tbody></table></div>';
            return html;
        }

        // 排序函数
        function sortScores(type) {
            if (!window.originalScoreData) return;
            
            let sortedData = [...window.originalScoreData];
            
            if (type === 'number') {
                // 按编号排序
                sortedData.sort((a, b) => {
                    const numA = parseInt(a.student_number) || 0;
                    const numB = parseInt(b.student_number) || 0;
                    return numA - numB;
                });
            } else if (type === 'score') {
                // 按成绩排序（从高到低）
                sortedData.sort((a, b) => {
                    // 如果是缺考，则排在最后
                    if (a.is_absent === '1' && b.is_absent !== '1') return 1;
                    if (a.is_absent !== '1' && b.is_absent === '1') return -1;
                    if (a.is_absent === '1' && b.is_absent === '1') return 0;
                    
                    // 使用 total_score 进行排序
                    const scoreA = parseFloat(a.total_score) || 0;
                    const scoreB = parseFloat(b.total_score) || 0;
                    return scoreB - scoreA;
                });
            }
            
            updateScoreList(sortedData);
        }

        // 加载统计分析数据
        function loadAnalytics() {
            // 如果未初始化，不执行统计分析
            if (!window.analyticsInitialized) {
                return Promise.resolve();
            }

            const gradeId = $('#gradeFilter').val();
            const classId = $('#classFilter').val();
            const subjectId = $('#subjectFilter').val();

            if (!gradeId || !classId || !subjectId) {
                $('#analyticsTable').hide();
                return Promise.resolve();
            }

            // 显示加载提示
            const loadingHtml = '<div class="text-center p-3"><div class="spinner-border text-primary" role="status"></div><div class="mt-2">正在整理数据，请稍后...</div></div>';
            $('#analyticsTable').html(loadingHtml).show();

            // 直接获取统计数据
            return $.get('../api/index.php?route=analytics/get', {
                grade_id: gradeId,
                class_id: classId,
                subject_id: subjectId
            })
            .then(response => {
                console.log('统计分析API响应:', response); // 添加日志记录API响应
                
                if (response.success && response.data) {
                    console.log('统计数据详情:', response.data); // 添加日志记录数据详情
                    
                    // 确保analytics和school_info存在
                    if (!response.data.analytics) {
                        throw new Error('返回的数据中缺少analytics字段');
                    }
                    
                    const html = generateAnalyticsTable(response.data.analytics, response.data.school_info);
                    $('#analyticsTable').html(html).show();
                    return Promise.resolve();
                } else {
                    throw new Error(response.error || '暂无统计数据，请等待成绩录入完成后自动生成');
                }
            })
            .catch(error => {
                console.error('统计分析加载失败:', error);
                
                if (window.analyticsInitialized) {
                    $('#analyticsTable').hide();
                    showAlert(error.message);
                }
                return Promise.reject(error);
            });
        }

        // 监听排序选择变化
        $(document).on('change', '#sort-select', function() {
            const sortType = $(this).val();
            sortScores(sortType);
        });

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
            // 初始化标志，用于控制统计分析的触发
            window.analyticsInitialized = false;
            
            // 初始隐藏统计表和清空列表
            $('#analyticsTable').hide();
            $('#scoreList').empty();

            // 获取当前项目ID
            $.get('../api/index.php?route=project/current')
                .then(response => {
                    if (response.success && response.data) {
                        window.currentProjectId = response.data.id;
                        window.currentSettingId = response.data.id;
                    }
                    // 设置默认排序方式为按编号排序
                    $('#sort-select').val('number');
                    // 加载年级列表
                    loadGrades();
                })
                .catch(error => {
                    console.error('初始化失败:', error);
                    showAlert('初始化失败：' + (error.responseJSON?.error || '未知错误'));
                });

            // 监听年级选择变化
            $('#gradeFilter').change(function() {
                const gradeId = $(this).val();
                loadSubjects(gradeId);
                $('#analyticsTable').hide();
                $('#scoreList').empty();
            });

            // 监听学科选择变化
            $('#subjectFilter').change(function() {
                const gradeId = $('#gradeFilter').val();
                const subjectId = $(this).val();
                
                if (!gradeId || !subjectId) {
                    $('#classFilter').empty().append('<option value="">请先选择年级和学科</option>');
                    $('#scoreList').empty();
                    $('#analyticsTable').hide();
                    return;
                }
                
                loadClasses(gradeId);
            });

            $('#classFilter').change(function() {
                const classId = $(this).val();
                const gradeId = $('#gradeFilter').val();
                const subjectId = $('#subjectFilter').val();
                
                if (!classId || !gradeId || !subjectId) {
                    $('#scoreList').empty();
                    $('#analyticsTable').hide();
                    window.analyticsInitialized = false;
                    return;
                }
                
                // 只在所有必要条件都满足时加载数据
                window.analyticsInitialized = true;
                loadScores();
            });

            // 初始化自定义下拉框
            initCustomSelects();
            
            // 在加载年级和学科后重新初始化
            $('#gradeFilter').on('change', function() {
                setTimeout(initCustomSelects, 100);
            });
            
            $('#subjectFilter').on('change', function() {
                setTimeout(initCustomSelects, 100);
            });
        });

        // 加载年级列表
        function loadGrades() {
            $.get('../api/index.php?route=grade/getList', function(response) {
                if (response.success) {
                    const select = $('#gradeFilter');
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