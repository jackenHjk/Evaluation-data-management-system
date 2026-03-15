<?php
/**
 * 文件名: modules/subjects_analytics.php
 * 功能描述: 学科数据分析看板模块
 * 
 * 该文件负责:
 * 1. 展示多学科综合分析数据
 * 2. 提供各学科成绩对比分析
 * 3. 生成学科间相关性分析
 * 4. 支持学科成绩趋势分析
 * 5. 提供多维度的学科分析图表
 * 
 * 学科分析看板展示所有学科的综合分析数据，包括各学科的平均分、及格率、优秀率对比，
 * 学科间的相关性分析，以及多个学科的成绩分布对比。同时提供按年级、班级筛选的功能，
 * 支持查看学科成绩随时间的变化趋势。
 * 
 * 关联文件:
 * - controllers/SubjectsAnalyticsController.php: 学科分析控制器
 * - api/index.php: API入口
 * - api/routes/subjects_analytics.php: 学科分析API
 * - assets/js/subjects-analytics.js: 学科分析前端脚本
 * - assets/js/chart.min.js: 图表库
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

<div class="container-fluid px-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h5 class="mb-0">
            <i class="fas fa-chart-line text-primary me-2"></i>语数看板
        </h5>
        <div class="d-flex align-items-center">
            <select id="grade-select" class="form-select me-2" style="width: 150px;">
                <option value="">选择年级...</option>
            </select>
        </div>
    </div>

    <!-- 统计数据表格 -->
    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-table me-1"></i>
            数据报表
        </div>
        <div class="card-body">
            <div class="table-container">
                <div id="analyticsTable">
                    <!-- 统计分析表将通过JavaScript动态生成 -->
                </div>
            </div>
        </div>
    </div>

    <!-- 学生成绩列表 -->
    <div class="card">
        <div class="card-header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <i class="fas fa-list me-1"></i>
                    学生列表
                </div>
                <div class="d-flex align-items-center">
                    <select id="class-filter" class="form-select me-2" style="width: 150px;">
                        <option value="">所有班级</option>
                    </select>
                    <select id="sort-select" class="form-select" style="width: 150px;">
                        <option value="number">按学号排序</option>
                        <option value="total_score">按总分排序</option>
                    </select>
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
        min-width: 70px !important;
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
        overflow: visible !important;
        white-space: normal !important;
        font-size: 12px !important;
        line-height: 1.2 !important;
        word-break: break-all !important;
        word-wrap: break-word !important;
        text-align: center !important;
    }
    /* 最高分最低分列 */
    .analytics-table th:nth-child(19),
    .analytics-table td:nth-child(19),
    .analytics-table th:nth-child(20),
    .analytics-table td:nth-child(20) {
        width: 60px !important;
        min-width: 60px !important;
        max-width: 60px !important;
    }
    /* 及格率优秀率列 */
    .analytics-table th:nth-child(21),
    .analytics-table td:nth-child(21),
    .analytics-table th:nth-child(22),
    .analytics-table td:nth-child(22) {
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
</style>

<script>
$(document).ready(function() {
    let currentGrade = '';
    let currentClass = '';
    let currentSort = 'number';
    let subjectInfo = {
        chinese: null,
        math: null
    };
    // 添加待审核班级映射对象
    let pendingEditClasses = {};

    // 获取当前项目ID
    $.get('api/index.php?route=settings/current', function(response) {
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
        $.get('api/index.php?route=grade/getList', function(response) {
            if (response.success) {
                const select = $('#grade-select');
                select.empty().append('<option value="">选择年级...</option>');
                
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
            }
        });
    }

    // 检查待审核成绩修改申请
    function checkPendingScoreEdits(classId, subjectId) {
        return new Promise((resolve, reject) => {
            $.get('../api/index.php?route=score_edit/check_pending', {
                class_id: classId,
                subject_id: subjectId
            })
            .done(function(response) {
                if (response.success) {
                    resolve({
                        hasPendingRequest: response.has_pending_request,
                        count: response.count || 0
                    });
                } else {
                    reject(new Error(response.error || '检查待审核状态失败'));
                }
            })
            .fail(function(xhr) {
                reject(new Error(xhr.responseJSON?.error || '网络错误'));
            });
        });
    }

    // 加载统计数据
    function loadAnalytics() {
        if (!currentGrade || !window.currentSettingId) return;

        // 获取统计数据
        $.get('api/index.php?route=analytics/getSubjectsAnalytics', {
            grade_id: currentGrade,
            setting_id: window.currentSettingId
        }, function(response) {
            if (response.success && response.data) {
                // 创建表格标题和表头
                const gradeName = $('#grade-select option:selected').text();
                const projectInfo = window.currentProject || {};
                const title = `
                    <div class="analytics-title">
                        ${projectInfo.school_name || ''}${projectInfo.current_semester || ''}${gradeName}语数成绩${projectInfo.project_name || ''}统计分析表
                    </div>
                `;

                // 定义分数区间
                const scoreRanges = [
                    '100', '99.5-95', '94.5-90', '89.5-85', '84.5-80',
                    '79.5-75', '74.5-70', '69.5-65', '64.5-60', '59.5-55',
                    '54.5-50', '49.5-40', '40以下'
                ];

                // 生成表格HTML
                let tableHtml = `
                    ${title}
                    <table class="analytics-table">
                        <tr>
                            <th rowspan="2">班别</th>
                            <th rowspan="2">科目</th>
                            <th rowspan="2">总人数</th>
                            <th rowspan="2">到考人数</th>
                            <th rowspan="2">总分</th>
                            <th rowspan="2">平均分</th>
                            <th colspan="13">数据分布</th>
                            <th rowspan="2">最高分</th>
                            <th rowspan="2">最低分</th>
                            <th rowspan="2">及格率</th>
                            <th rowspan="2">优秀率</th>
                        </tr>
                        <tr>
                            ${scoreRanges.map(range => `
                                <th class="${getCellClass(range)}">${range.replace('-', '<br>/<br>')}</th>
                            `).join('')}
                        </tr>
                `;

                // 确保response.data是数组
                const analyticsData = Array.isArray(response.data) ? response.data : [];
                
                // 按科目分组数据
                const chineseData = analyticsData.filter(item => item.subject_name === '语文');
                const mathData = analyticsData.filter(item => item.subject_name === '数学');

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
                    // 过滤掉待审核班级
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
                    // 过滤掉待审核班级
                    const filteredMathData = mathData.filter(item => {
                        const classKey = `${item.class_id}-${item.subject_id}`;
                        return !pendingEditClasses[classKey];
                    });
                    
                    if (filteredMathData.length > 0) {
                        const mathTotals = calculateTotals(filteredMathData);
                    tableHtml += generateTotalRow(mathTotals, '数学合计', scoreRanges);
                    }
                }

                // 添加注释说明
                tableHtml += '</table>';
                tableHtml += `
                    <div class="analytics-footer" style="font-size: 12px; color:rgb(245, 131, 60); margin-top: 20px; text-align: left; margin-left: 10px;">
                        为"/"的表示还未完成数据录入。"合计"行中涉及平均值计算的，只计算已完成数据录入的班级，未完成的不参与计算。
                    </div>
                `;
                
                $('#analyticsTable').html(tableHtml).show();
            } else {
                $('#analyticsTable').html('<div class="alert alert-warning">暂无统计数据</div>');
            }
        });
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
            excellent_count: 0
        };

        data.forEach(item => {
            totals.total_students += parseInt(item.total_students) || 0;
            totals.attended_students += parseInt(item.attended_students) || 0;
            totals.total_score += parseFloat(item.total_score) || 0;
            totals.max_score = Math.max(totals.max_score, parseFloat(item.max_score) || 0);
            totals.min_score = Math.min(totals.min_score, parseFloat(item.min_score) || 0);

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

        // 计算及格率和优秀率
        // 及格：统计60分及以上的人数
        const passCount = (totals.distribution['100'] || 0) +
            (totals.distribution['99.5-95'] || 0) +
            (totals.distribution['94.5-90'] || 0) +
            (totals.distribution['89.5-85'] || 0) +
            (totals.distribution['84.5-80'] || 0) +
            (totals.distribution['79.5-75'] || 0) +
            (totals.distribution['74.5-70'] || 0) +
            (totals.distribution['69.5-65'] || 0) +
            (totals.distribution['64.5-60'] || 0);

        // 优秀：统计90分及以上的人数
        const excellentCount = (totals.distribution['100'] || 0) +
            (totals.distribution['99.5-95'] || 0) +
            (totals.distribution['94.5-90'] || 0);

        // 计算比率
        totals.pass_rate = totals.attended_students > 0 ? 
            (passCount / totals.attended_students) * 100 : 0;
        totals.excellent_rate = totals.attended_students > 0 ? 
            (excellentCount / totals.attended_students) * 100 : 0;

        return totals;
    }

    // 获取单元格样式类
    function getCellClass(range) {
        const score = parseFloat(range.split('-')[0]);
        if (score >= 90) return 'cell-excellent';
        if (score >= 80) return 'cell-good';
        if (score >= 60) return 'cell-pass';
        return 'cell-fail';
    }

    // 加载成绩列表
    function loadScores() {
        if (!currentGrade || !window.currentSettingId) return;

        const loadingHtml = '<div class="text-center p-3"><div class="spinner-border text-primary" role="status"></div><div class="mt-2">正在加载数据，请稍候...</div></div>';
        $('#scoreList tbody').html(loadingHtml);

        // 先获取语文和数学科目ID
        $.get('../api/index.php?route=subject/getList', {
            grade_id: currentGrade,
            setting_id: window.currentSettingId
        })
        .done(function(subjectResponse) {
            if (!subjectResponse.success) {
                $('#scoreList tbody').html('<tr><td colspan="9" class="text-center text-danger">加载科目失败</td></tr>');
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
                $('#scoreList tbody').html('<tr><td colspan="9" class="text-center text-danger">未找到语文或数学科目</td></tr>');
                return;
            }
            
            // 检查所选班级是否有待审核申请
            const checkPromises = [];
            
            if (currentClass) {
                if (chineseSubjectId) {
                    checkPromises.push(
                        checkPendingScoreEdits(currentClass, chineseSubjectId)
                            .then(result => {
                                if (result.hasPendingRequest) {
                                    const classKey = `${currentClass}-${chineseSubjectId}`;
                                    pendingEditClasses[classKey] = true;
                                }
                                return result;
                            })
                    );
                }
                
                if (mathSubjectId) {
                    checkPromises.push(
                        checkPendingScoreEdits(currentClass, mathSubjectId)
                            .then(result => {
                                if (result.hasPendingRequest) {
                                    const classKey = `${currentClass}-${mathSubjectId}`;
                                    pendingEditClasses[classKey] = true;
                                }
                                return result;
                            })
                    );
                }
            }
            
            Promise.all(checkPromises)
                .then(() => {
                    // 继续获取成绩数据
        $.get('../api/index.php?route=analytics/getSubjectScores', {
            grade_id: currentGrade,
            class_id: currentClass || '',
            setting_id: window.currentSettingId
        })
        .done(function(response) {
            if (response.success && response.data) {
                // 更新科目信息
                if (response.data.subjects) {
                    // 直接使用返回的科目配置
                    subjectInfo = response.data.subjects;
                }
                            
                            // 如果当前班级的任一科目有待审核状态，显示提醒
                            if (currentClass && (pendingEditClasses[`${currentClass}-${chineseSubjectId}`] || 
                                pendingEditClasses[`${currentClass}-${mathSubjectId}`])) {
                                $('#scoreList tbody').html(`
                                    <tr>
                                        <td colspan="9" class="text-center" style="padding: 15px;">
                                            <div class="alert alert-warning mb-0">
                                                <i class="fas fa-exclamation-triangle me-2"></i>
                                                当前班级有待审核的成绩修改申请，成绩数据暂不显示。请审核完成后再查看。
                                            </div>
                                        </td>
                                    </tr>
                                `);
                                return;
                            }
                            
                // 更新成绩列表
                if (response.data.scores) {
                    updateScoreList(response.data.scores);
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
                })
                .catch(error => {
                    console.error('检查待审核状态失败:', error);
                    // 即使检查失败也继续加载数据
                    $('#scoreList tbody').html('<tr><td colspan="9" class="text-center text-warning">无法检查待审核状态，数据可能不完整</td></tr>');
                });
        })
        .fail(function(xhr) {
            $('#scoreList tbody').html('<tr><td colspan="9" class="text-center text-danger">加载科目失败</td></tr>');
        });
    }

    // 更新成绩列表
    function updateScoreList(scores) {
        if (!scores || !scores.length) {
            $('#scoreList tbody').html('<tr><td colspan="9" class="text-center">暂无数据</td></tr>');
            return;
        }

        // 根据当前排序方式排序
        if (currentSort === 'number') {
            scores.sort((a, b) => a.student_number.localeCompare(b.student_number));
        } else if (currentSort === 'total_score') {
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
                    <td>${index + 1}</td>
                    <td>${score.student_number}</td>
                    <td>${score.student_name}</td>
                    <td>${score.class_name}</td>
                    <td>${chineseScore}</td>
                    <td>${chineseLevel}</td>
                    <td>${mathScore}</td>
                    <td>${mathLevel}</td>
                    <td>${totalScore}</td>
                </tr>
            `;
        });

        $('#scoreList tbody').html(html);
    }

    // 获取成绩等级HTML
    function getScoreLevelHtml(score, subjectConfig) {
        if (score === '缺考' || score === '-') {
            return '<span class="score-level level-absent">缺考</span>';
        }
        if (!score || !subjectConfig) return '';
        
        const scoreNum = parseFloat(score);
        if (isNaN(scoreNum)) return '';
        
        let levelClass = '';
        let levelText = '';
        
        if (scoreNum >= parseFloat(subjectConfig.excellent_score)) {
            levelClass = 'level-excellent';
            levelText = '优秀';
        } else if (scoreNum >= parseFloat(subjectConfig.good_score)) {
            levelClass = 'level-good';
            levelText = '良好';
        } else if (scoreNum >= parseFloat(subjectConfig.pass_score)) {
            levelClass = 'level-pass';
            levelText = '合格';
        } else {
            levelClass = 'level-fail';
            levelText = '待合格';
        }
        
        return `<span class="score-level ${levelClass}">${levelText}</span>`;
    }

    // 事件监听
    $('#grade-select').change(function() {
        currentGrade = $(this).val();
        if (currentGrade) {
            // 重置班级待审核状态记录
            pendingEditClasses = {};
            
            // 加载班级列表
            $.get('../api/index.php?route=class/getList', { 
                grade_id: currentGrade 
            })
            .done(function(response) {
                if (response.success) {
                    const select = $('#class-filter');
                    select.empty().append('<option value="">所有班级</option>');
                    
                    if (response.data && response.data.length > 0) {
                        // 使用Set去重
                        const uniqueClasses = new Map();
                        response.data.forEach(cls => {
                            uniqueClasses.set(cls.class_name, cls);
                        });
                        
                        // 获取语文和数学科目ID
                        $.get('../api/index.php?route=subject/getList', {
                            grade_id: currentGrade,
                            setting_id: window.currentSettingId
                        })
                        .done(function(subjectResponse) {
                            if (!subjectResponse.success) {
                                console.error('加载科目列表失败:', subjectResponse.error);
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
                                return;
                            }
                            
                            const checkPromises = [];
                            // 检查每个班级是否有待审核申请
                            Array.from(uniqueClasses.values()).forEach(cls => {
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
                            
                            // 等待所有检查完成后再添加班级选项并加载数据
                            Promise.all(checkPromises)
                                .finally(() => {
                        // 转换为数组并排序
                                    Array.from(uniqueClasses.values())
                                        .sort((a, b) => a.class_name.localeCompare(b.class_name))
                                        .forEach(cls => {
                                            // 如果班级有待审核申请，在选项中标记出来
                                            const hasPendingChinese = pendingEditClasses[`${cls.id}-${chineseSubjectId}`];
                                            const hasPendingMath = pendingEditClasses[`${cls.id}-${mathSubjectId}`];
                                            const hasPending = hasPendingChinese || hasPendingMath;
                                            
                                            select.append(`
                                                <option value="${cls.id}" ${hasPending ? 'data-has-pending="true"' : ''}>
                                                    ${cls.class_name} ${hasPending ? '(有待审核)' : ''}
                                                </option>
                                            `);
                                        });
                                    
                                    // 加载其他数据
                                    loadAnalytics();
                                    loadScores();
                                });
                        })
                        .fail(function() {
                            console.error('加载科目列表请求失败');
                            
                            // 简单添加班级选项，不做待审核检查
                        Array.from(uniqueClasses.values())
                            .sort((a, b) => a.class_name.localeCompare(b.class_name))
                            .forEach(cls => {
                                select.append(`<option value="${cls.id}">${cls.class_name}</option>`);
                                });
                                
                            loadAnalytics();
                            loadScores();
                            });
                    } else {
                        console.log('未找到班级数据');
                        loadAnalytics();
                        loadScores();
                    }
                } else {
                    console.error('加载班级列表失败:', response.error);
                    alert('加载班级列表失败：' + (response.error || '未知错误'));
                }
            })
            .fail(function(xhr, status, error) {
                console.error('请求班级列表失败:', {xhr, status, error});
                alert('请求班级列表失败，请检查网络连接');
            });
        } else {
            // 清空班级选择器
            $('#class-filter').empty().append('<option value="">所有班级</option>');
        }
    });

    $('#class-filter').change(function() {
        currentClass = $(this).val();
        loadScores();
    });

    $('#sort-select').change(function() {
        currentSort = $(this).val();
        loadScores();
    });

    // 格式化分数显示
    function formatScore(score) {
        if (!score) return '0';
        const num = parseFloat(score);
        if (isNaN(num)) return '0';
        // 如果是整数，直接返回整数
        if (Number.isInteger(num)) {
            return num.toString();
        }
        // 否则保留一位小数
        return num.toFixed(1);
    }

    // 添加生成表格行的函数
    function generateTableRow(item, scoreRanges) {
        let distribution = {};
        try {
            distribution = typeof item.score_distribution === 'string' ? 
                JSON.parse(item.score_distribution) : 
                item.score_distribution || {};
        } catch (e) {
            console.error('解析分数分布数据失败:', e);
            distribution = {};
        }

        // 确保所有数值都转换为数字类型
        const total_score = parseFloat(item.total_score) || 0;
        const average_score = parseFloat(item.average_score) || 0;
        const max_score = parseFloat(item.max_score) || 0;
        const min_score = parseFloat(item.min_score) || 0;
        const pass_rate = parseFloat(item.pass_rate) || 0;
        const excellent_rate = parseFloat(item.excellent_rate) || 0;

        return `
            <tr>
                <td>${item.class_name || ''}</td>
                <td>${item.subject_name || ''}</td>
                <td>${parseInt(item.total_students) || 0}</td>
                <td>${parseInt(item.attended_students) || 0}</td>
                <td>${formatScore(total_score)}</td>
                <td>${formatScore(average_score)}</td>
                ${scoreRanges.map(range => `
                    <td class="${getCellClass(range)}">${distribution[range] || 0}</td>
                `).join('')}
                <td>${formatScore(max_score)}</td>
                <td>${formatScore(min_score)}</td>
                <td>${formatScore(pass_rate)}%</td>
                <td>${formatScore(excellent_rate)}%</td>
            </tr>
        `;
    }

    // 修改生成合计行的函数
    function generateTotalRow(totals, title, scoreRanges) {
        // 判断是否为语文或数学合计行
        const isSubjectTotal = title === '语文合计' || title === '数学合计';
        
        return `
            <tr class="total-row">
                ${isSubjectTotal ? 
                    `<td colspan="2" style="font-weight: 600; text-align: center;">${title}</td>` :
                    `<td style="font-weight: 600;">${title}</td><td>-</td>`
                }
                <td>${parseInt(totals.total_students) || 0}</td>
                <td>${parseInt(totals.attended_students) || 0}</td>
                <td>${formatScore(totals.total_score)}</td>
                <td>${formatScore(totals.average_score)}</td>
                ${scoreRanges.map(range => `
                    <td class="${getCellClass(range)}">${totals.distribution[range] || 0}</td>
                `).join('')}
                <td>${formatScore(totals.max_score)}</td>
                <td>${formatScore(totals.min_score)}</td>
                <td>${formatScore(totals.pass_rate)}%</td>
                <td>${formatScore(totals.excellent_rate)}%</td>
            </tr>
        `;
    }
});
</script> 