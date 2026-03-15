<!--
/**
 * 文件名: modules/scores.php
 * 功能描述: 成绩录入模块
 * 
 * 该文件负责:
 * 1. 提供成绩录入和编辑的用户界面
 * 2. 支持按班级、科目筛选学生名单
 * 3. 允许批量录入基础分和附加分
 * 4. 支持缺考标记功能
 * 5. 实时计算总分和成绩等级
 * 6. 提供成绩统计分析功能
 * 
 * 成绩录入界面采用直观的表格布局，支持批量编辑和保存，
 * 提供成绩等级自动判断和颜色标识，提升录入效率。
 * 系统自动计算各分数段分布、平均分、及格率等统计数据。
 * 
 * 关联文件:
 * - controllers/ScoreController.php: 成绩控制器
 * - api/index.php: API入口
 * - assets/js/scores.js: 成绩录入前端脚本
 * - assets/css/common.css: 通用样式文件
 */
-->
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>成绩录入</title>
    <link href="../assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/common.css" rel="stylesheet">
    <link href="../assets/css/sweetalert2.min.css" rel="stylesheet">
    <style>
        .score-input {
            width: 60px;
            text-align: center;
            padding: 2px 4px;
            height: 28px;
            margin: 0 auto;
        }
        .score-input.success {
            background-color: #d4edda;
        }
        .score-input.error {
            background-color: #f8d7da;
        }
        .score-input.modified {
            background-color: #ffcccc;
        }
        .score-row.editing .score-input {
            background-color: #fff;
        }
        .score-row.editing .score-input:focus {
            background-color: #fff;
        }
        .score-row.editing .score-input.modified {
            background-color: #ffcccc;
        }
        .score-input[readonly] {
            background-color: #e9ecef;
            border-color: transparent;
        }
        .table {
            margin-bottom: 0;
            font-size: 14px;
        }
        .table th {
            background-color: #f8f9fa;
            text-align: center;
            vertical-align: middle;
            padding: 8px 4px;
            font-weight: 500;
            white-space: nowrap;
        }
        .table td {
            text-align: center;
            vertical-align: middle;
            padding: 4px;
            white-space: nowrap;
        }
        .score-level {
            display: inline-block;
            min-width: 60px;
            padding: 2px 8px;
            border-radius: 3px;
        }
        .score-level.excellent {
            background-color: #d4edda;
        }
        .score-level.good {
            background-color: #cce5ff;
        }
        .score-level.pass {
            background-color: #fff;
        }
        .score-level.fail {
            background-color: #f8d7da;
        }
        .score-level.absent {
            background-color: #343a40;
            color: #fff;
        }
        .total-score {
            font-size: 14px;
            font-weight: 500;
        }
        .table-hover tbody tr:hover {
            background-color: rgba(0,0,0,.02);
        }
        .form-control-sm {
            font-size: 14px;
        }
        .score-input-group {
            display: flex;
            align-items: center;
            gap: 2px;
            justify-content: center;
        }
        .score-input-group .form-control {
            flex: 1;
        }
        .absent-checkbox {
            display: none;
        }
        .absent-label {
            font-size: 12px;
            padding: 2px 4px;
        }
        .absent-checkbox:checked + .absent-label {
            background-color: #dc3545;
            color: #fff;
        }
        .score-input-group {
            display: flex;
            align-items: center;
            gap: 2px;
        }
        .score-input {
            width: 60px !important;
            text-align: center;
        }
        .form-check {
            margin: 0;
            padding-left: 0.5rem;
        }
        .form-check-input {
            margin-top: 0.2rem;
        }
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
        
        /* 表格容器样式 */
        .col-md-4 {
            padding: 0 3px;
            max-width: 390px;
        }
        .table {
            margin-bottom: 0;
            width: 100%;
            font-size: 13px;
        }
        .table td {
            padding: 3px;
            vertical-align: middle;
            white-space: nowrap;
            min-width: 60px;
            position: relative;
        }
        .table th {
            padding: 4px 3px;
            white-space: nowrap;
            background-color: #f8f9fa;
        }
        .score-level {
            padding: 1px 3px;
            font-size: 12px;
        }
        .alert-info {
            padding: 6px 12px;
            margin-bottom: 10px;
            font-size: 13px;
            color: #000;
            background-color: #fee2e2;
            border-color: #fee2e2;
        }
        /* 添加按钮样式 */
        .btn {
            border-radius: 20px;
            padding: 6px 16px;
            transition: all 0.3s ease;
        }
        .btn-sm {
            border-radius: 15px;
            padding: 4px 12px;
        }
        /* 排序按钮激活状态 */
        .btn-outline-secondary.active {
            background-color: #6c757d;
            color: #fff;
            border-color: #6c757d;
        }
        .btn-outline-secondary:hover {
            background-color: #6c757d;
            color: #fff;
        }
        /* 其他按钮样式优化 */
        .btn-primary, .btn-success {
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .btn-primary:hover, .btn-success:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
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
        /* 表格内容居中对齐 */
        .table-responsive table th,
        .table-responsive table td {
            text-align: center !important;
            vertical-align: middle !important;
        }
        .score-input.pending-edit {
            background-color: #f8d7da !important;
            border: 2px solid #dc3545 !important;
            color: #dc3545 !important;
            font-weight: bold !important;
            box-shadow: 0 0 5px rgba(220, 53, 69, 0.5) !important;
        }
        
        .total-score.pending-edit {
            color: #dc3545 !important;
            font-weight: bold !important;
            border-bottom: 2px solid #dc3545 !important;
            padding: 2px 4px !important;
            background-color: #f8d7da !important;
            border-radius: 3px !important;
        }
        
        /* 整行标记待审核状态 */
        .pending-edit-row {
            background-color: rgba(248, 215, 218, 0.5) !important;
            border: 2px solid #dc3545 !important;
            position: relative;
        }
        .pending-edit-row td {
            background-color: rgba(248, 215, 218, 0.3) !important;
            position: relative;
        }
        .pending-edit-row td::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            border: 1px solid #f5c2c7;
            pointer-events: none;
            z-index: 1;
        }
    </style>
</head>
<body>

    <div class="container-fluid mt-3">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center flex-wrap">
                        <h5 class="mb-0"><span id="scoreTitle">成绩录入</span></h5>
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
                            <div class="btn-group ms-3">
                                <button class="btn btn-outline-secondary btn-sm" id="sortByNameBtn" style="display: none; border-top-right-radius: 0; border-bottom-right-radius: 0; border-right: none;">
                                    <i class="fas fa-sort-alpha-down me-1"></i>姓名
                                </button>
                                <button class="btn btn-outline-secondary btn-sm me-4" id="sortByNumberBtn" style="display: none; border-top-left-radius: 0; border-bottom-left-radius: 0; border-top-right-radius: 15px; border-bottom-right-radius: 15px;">
                                    <i class="fas fa-sort-numeric-down me-1"></i>编号
                                </button>
                                <button class="btn btn-primary" id="editBtn" style="display: none; border-radius: 20px; padding: 8px 20px; font-size: 14px; transition: all 0.3s ease;">
                                    <i class="fas fa-edit me-1"></i>录入数据
                                </button>
                            </div>
                        </div>
                        <!-- 编辑操作按钮，单独一行显示 -->
                        <div class="w-100 mt-2 d-flex justify-content-end" id="editActionButtons" style="display: none !important;">
                            <button class="btn btn-success" id="saveBtn" style="display: none; border-radius: 20px; padding: 8px 20px; font-size: 14px; transition: all 0.3s ease;">
                                <i class="fas fa-check me-1"></i>完成录入
                            </button>
                            <button class="btn btn-info" id="submitEditRequestBtn" style="display: none; border-radius: 20px; padding: 8px 15px; font-size: 14px; transition: all 0.3s ease; min-width: 140px; margin-left: 10px;">
                                <i class="fas fa-paper-plane me-1"></i>提交修改申请
                            </button>
                            <button class="btn btn-danger" id="cancelEditBtn" style="display: none; border-radius: 20px; padding: 8px 15px; font-size: 14px; transition: all 0.3s ease; margin-left: 10px;">
                                <i class="fas fa-times me-1"></i>放弃
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <!-- 编辑模式下的提示语 -->
                        <div id="editTips" class="alert alert-info mb-2 py-1" style="display: none;">
                            如果缺考的，请点击"缺"标注缺考，缺考学生不会计入到考总人数，不参与成绩分析与统计。
                        </div>
                        
                        <!-- 学生成绩列表 -->
                        <div id="scoreList">
                            <!-- 成绩列表将通过 AJAX 加载 -->
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
        $(function() {
            // 使用window对象来存储全局变量，避免重复声明
            window.isEditing = window.isEditing || false;
            window.currentSubject = window.currentSubject || null;
            window.currentGrade = window.currentGrade || null;
            window.currentClass = window.currentClass || null;
            window.isChineseAboveGrade3 = window.isChineseAboveGrade3 || false;
            window.userRole = null;
            window.currentSettingId = null; // 添加当前项目ID变量

            // 初始化自定义下拉框
            initCustomSelects();

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
                    } else {
                        showAlert(response.error || '加载年级失败');
                    }
                }).fail(function(xhr) {
                    showAlert('加载年级失败：' + (xhr.responseJSON?.error || '未知错误'));
                });
            }

            // 加载学科列表
            function loadSubjects(gradeId) {
                if (!gradeId) {
                    $('#subjectFilter').empty().append('<option value="">请先选择年级</option>');
                    $('#classFilter').empty().append('<option value="">请先选择年级</option>');
                    return;
                }

                $.get('../api/index.php?route=score/teacher_subjects', { grade_id: gradeId }, function(response) {
                    if (response.success) {
                        const select = $('#subjectFilter');
                        select.empty().append('<option value="">选择学科</option>');
                        
                        if (response.data && response.data.length > 0) {
                            response.data.forEach(subject => {
                                //console.log('加载学科数据:', subject); // 添加调试日志
                                select.append(
                                    `<option value="${subject.subject_id}" 
                                    data-code="${subject.subject_code}"
                                    data-full-score="${subject.full_score || 100}"
                                    data-excellent-score="${subject.excellent_score || 90}"
                                    data-good-score="${subject.good_score || 80}"
                                    data-pass-score="${subject.pass_score || 60}"
                                    data-is-split="${subject.is_split ? 1 : 0}"
                                    data-split-name-1="${subject.split_name_1 || ''}"
                                    data-split-name-2="${subject.split_name_2 || ''}"
                                    data-split-score-1="${subject.split_score_1 || ''}"
                                    data-split-score-2="${subject.split_score_2 || ''}"
                                    >${subject.subject_name}</option>`
                                );
                            });
                        }
                        
                        // 重新初始化自定义下拉框
                        initCustomSelects();
                    } else {
                        showAlert(response.error || '加载学科失败');
                    }
                }).fail(function(xhr) {
                    showAlert('加载学科失败：' + (xhr.responseJSON?.error || '未知错误'));
                });
            }

            // 获取当前项目ID
            function getCurrentSetting() {
                return new Promise((resolve, reject) => {
                    $.ajax({
                        url: '../api/index.php?route=project/current',
                        method: 'GET',
                        success: function(response) {
                            if (response.success && response.data) {
                                window.currentSettingId = response.data.id;
                                //console.log('获取到当前项目ID:', window.currentSettingId);
                                
                                // 获取当前用户信息
                                getCurrentUser()
                                    .then(user => {
                                        window.userRole = user.role;
                                resolve(window.currentSettingId);
                                    })
                                    .catch(error => {
                                        console.error('获取用户信息失败:', error);
                                        // 即使获取用户信息失败，仍然返回项目ID
                                        resolve(window.currentSettingId);
                                    });
                            } else {
                                console.error('获取项目ID失败:', response.error);
                                reject(new Error('获取项目ID失败：' + response.error));
                            }
                        },
                        error: function(xhr) {
                            console.error('获取项目ID请求失败:', xhr);
                            reject(new Error('获取项目ID失败：' + (xhr.responseJSON?.error || '未知错误')));
                        }
                    });
                });
            }

            // 初始化自定义下拉框函数
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

            // 在年级和学科选择变化时重新初始化下拉框
            $('#gradeFilter').on('change', function() {
                setTimeout(initCustomSelects, 100);
            });
            
            $('#subjectFilter').on('change', function() {
                setTimeout(initCustomSelects, 100);
            });

            // 页面加载完成后初始化
            $(document).ready(function() {
                initScoreInputs();
                
                // 初始化修改跟踪对象
                window.originalScores = {};
                window.modifiedScores = {};
                window.pendingEditStudents = {};
                
                // 获取当前项目ID并加载年级列表
            getCurrentSetting()
                .then(() => {
                    // 加载年级列表
                    loadGrades();
                })
                .catch(error => {
                    console.error('初始化失败:', error);
                        showAlert('初始化失败: ' + error.message);
                    });
                
                // 检查URL参数或sessionStorage中是否有编辑状态标记
                const urlParams = new URLSearchParams(window.location.search);
                const classId = urlParams.get('class_id');
                const subjectId = urlParams.get('subject_id');
                const editMode = urlParams.get('edit_mode');
                    
                // 如果URL中包含edit_mode=1参数，则设置为编辑模式
                if (editMode === '1') {
                    window.isEditing = true;
                    // 存储编辑状态到sessionStorage
                    sessionStorage.setItem('isEditing', 'true');
                    sessionStorage.setItem('editClass', classId);
                    sessionStorage.setItem('editSubject', subjectId);
                } 
                // 如果sessionStorage中有编辑状态且班级和科目匹配，也设置为编辑模式
                else if (sessionStorage.getItem('isEditing') === 'true' && 
                         sessionStorage.getItem('editClass') === classId && 
                         sessionStorage.getItem('editSubject') === subjectId) {
                    window.isEditing = true;
                }
                
                // 如果URL中包含参数，自动选择对应的年级、学科和班级
                if (classId && subjectId) {
                    // 等待页面完全加载，然后检查统计分析数据
                        setTimeout(() => {
                        // 先检查是否有待审核修改申请
                        $.get('../api/index.php?route=score_edit/check_pending', {
                            class_id: classId,
                            subject_id: subjectId
                        })
                        .done(function(response) {
                            if (response.success && response.has_pending_request) {
                                console.log('检测到待审核修改申请');
                                // 获取待审核修改详情
                                $.get('../api/index.php?route=score_edit/pending_details', {
                                    class_id: classId,
                                    subject_id: subjectId
                                })
                                .done(function(detailResponse) {
                                    if (detailResponse.success && detailResponse.data) {
                                        window.pendingEditStudents = {};
                                        detailResponse.data.forEach(detail => {
                                            // 使用字符串ID来避免类型不匹配问题
                                            window.pendingEditStudents[detail.student_id.toString()] = true;
                                        });
                                        console.log('待审核修改学生:', window.pendingEditStudents);
                                    }
                                    checkAnalyticsExists(classId, subjectId);
                                })
                                .fail(function() {
                                    checkAnalyticsExists(classId, subjectId);
                                });
                            } else {
                                checkAnalyticsExists(classId, subjectId);
                            }
                        })
                        .fail(function() {
                            checkAnalyticsExists(classId, subjectId);
                        });
                        
                        // 如果处于编辑模式，更新界面状态
                        if (window.isEditing) {
                            updateUIForEditMode();
                        }
                    }, 1000);
                }
                
                // 添加放弃修改按钮的点击事件
                $('#cancelEditBtn').click(function() {
                    Swal.fire({
                        title: '确定放弃修改？',
                        text: '所有未提交的修改将被丢弃',
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonText: '确定放弃',
                        cancelButtonText: '取消'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            // 清除编辑状态
                            window.isEditing = false;
                            sessionStorage.removeItem('isEditing');
                            sessionStorage.removeItem('editClass');
                            sessionStorage.removeItem('editSubject');
                            
                            // 清除修改记录
                            window.originalScores = {};
                            window.modifiedScores = {};
                            
                            // 移除URL中的edit_mode参数
                            const url = new URL(window.location.href);
                            url.searchParams.delete('edit_mode');
                            window.history.replaceState({}, '', url);
                            
                            // 重新加载页面
                            location.reload();
                        }
                    });
                });
            });
            
            // 更新界面为编辑模式
            function updateUIForEditMode() {
                // 显示/隐藏按钮
                $('#editBtn').hide();
                $('#sortByNameBtn').show();
                $('#sortByNumberBtn').show();
                $('#editTips').show();
                $('#cancelEditBtn').show(); // 始终显示放弃按钮
                $('#editActionButtons').css('display', 'flex'); // 显示编辑操作按钮容器
                
                // 禁用筛选下拉框
                $('.custom-select-wrapper').addClass('disabled').css('pointer-events', 'none');
                $('.custom-select-trigger').css({
                    'opacity': '0.6',
                    'background': '#f8f9fa',
                    'cursor': 'not-allowed'
                });
                
                // 检查是否所有成绩已录入，如果是则显示"提交修改申请"按钮
                $.get('../api/index.php?route=analytics/get', {
                    grade_id: $('#gradeFilter').val(),
                    class_id: $('#classFilter').val(),
                    subject_id: $('#subjectFilter').val()
                })
                .done(function(response) {
                    console.log('统计分析API响应:', response);
                    if (response.success && response.data && response.data.analytics) {
                        // 如果已经有统计数据，说明成绩已经录入完成，显示"提交修改申请"按钮
                        $('#saveBtn').hide();
                        $('#submitEditRequestBtn').show();
                        $('#cancelEditBtn').show(); // 确保放弃按钮也显示
                        window.hasAnalyticsData = true;
                        console.log('检测到统计分析数据，显示提交修改申请按钮');
                    } else {
                        // 如果没有统计数据，说明成绩未录入完成，显示"完成录入"按钮
                        $('#saveBtn').show().html('<i class="fas fa-check me-1"></i>完成录入');
                        $('#submitEditRequestBtn').hide();
                        $('#cancelEditBtn').show(); // 确保放弃按钮也显示
                        window.hasAnalyticsData = false;
                        console.log('未检测到统计分析数据，显示完成录入按钮');
                    }
                })
                .fail(function(xhr) {
                    // 请求失败时默认显示"完成录入"按钮
                    $('#saveBtn').show().html('<i class="fas fa-check me-1"></i>完成录入');
                    $('#submitEditRequestBtn').hide();
                    $('#cancelEditBtn').show(); // 确保放弃按钮也显示
                    window.hasAnalyticsData = false;
                    console.log('检查统计分析数据失败，显示完成录入按钮');
                });
                
                // 重新加载成绩列表以显示编辑模式
                loadScores();
                
                // 更新URL参数，添加edit_mode=1
                const url = new URL(window.location.href);
                url.searchParams.set('edit_mode', '1');
                window.history.replaceState({}, '', url);
                
                // 使表格可编辑
                $('.score-row').each(function() {
                    const $row = $(this);
                    const $baseScoreInput = $row.find('.base-score');
                    const $extraScoreInput = $row.find('.extra-score');
                    
                    // 启用输入框
                    $baseScoreInput.prop('disabled', false);
                    $extraScoreInput.prop('disabled', false);
                    
                    // 添加编辑样式
                    $baseScoreInput.addClass('editing');
                    $extraScoreInput.addClass('editing');
                });
            }

            // 监听年级选择变化
            $('#gradeFilter').change(function() {
                const selectedGradeId = $(this).val();
                window.currentGrade = selectedGradeId;
                loadSubjects(selectedGradeId);
            });

            // 监听学科选择变化
            $('#subjectFilter').change(function() {
                const selectedOption = $('option:selected', this);
                const subjectName = selectedOption.text();
                $('#scoreTitle').text(subjectName + '成绩录入');
                
                // 获取拆分成绩设置
                const isSplit = selectedOption.data('is-split');
                /*console.log('拆分成绩设置:', {
                    rawValue: selectedOption.data('is-split'),
                    isSplit: isSplit,
                    splitName1: selectedOption.data('split-name-1'),
                    splitName2: selectedOption.data('split-name-2'),
                    splitScore1: selectedOption.data('split-score-1'),
                    splitScore2: selectedOption.data('split-score-2')
                });*/
                
                // 判断是否启用拆分成绩
                window.currentSubject = {
                    id: $(this).val(),
                    name: subjectName,
                    isSplit: isSplit == 1 || isSplit === '1' || isSplit === true,
                    splitName1: selectedOption.data('split-name-1'),
                    splitName2: selectedOption.data('split-name-2'),
                    splitScore1: selectedOption.data('split-score-1'),
                    splitScore2: selectedOption.data('split-score-2')
                };
                
                /* 调试输出
                console.log('学科选择变更:', {
                    subjectName,
                    currentSubject: window.currentSubject
                });
                */
                // 更新班级列表
                loadClasses(currentGrade);
                
                // 如果已选择班级，重新加载成绩列表
                if (currentClass) {
                    loadScores();
                }
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
                        beforeSend: function(xhr) {
                            //console.log('发送获取用户信息请求');
                        },
                        success: function(response) {
                            //console.log('获取用户信息响应:', response);
                            // 修改这里以适配正确的响应结构
                            if (response && response.success && response.user) {
                                window.userRole = response.user.role;
                               // console.log('当前用户角色:', window.userRole);
                                
                                // 根据用户角色设置按钮状态
                                if (window.userRole === 'teaching' || window.userRole === 'admin') {
                                    $('#editBtn, #saveBtn').prop('disabled', true)
                                                         .attr('title', '教导处和管理员角色不能录入成绩')
                                                         .tooltip();
                                } else if (window.userRole === 'marker') {
                                    // 阅卷老师可以录入成绩，但需要等待选择完成
                                    $('#editBtn').hide();
                                }
                                
                                resolve(response.user);
                            } else {
                                console.error('获取用户信息失败:', response);
                                const errorMsg = (response && response.error) ? response.error : '获取用户信息失败，请重新登录';
                                reject(new Error(errorMsg));
                            }
                        },
                        error: function(xhr, status, error) {
                            console.error('获取用户信息请求失败:', {
                                status: status,
                                error: error,
                                response: xhr.responseText
                            });
                            
                            let errorMsg = '获取用户信息失败';
                            try {
                                const response = JSON.parse(xhr.responseText);
                                errorMsg = response.error || errorMsg;
                            } catch(e) {
                                errorMsg += ': ' + (error || '未知错误');
                            }
                            
                            // 如果是 401 错误，说明未登录或会话过期
                            if (xhr.status === 401) {
                                errorMsg = '用户未登录或会话已过期，请重新登录';
                                // 可以选择重定向到登录页面
                                window.location.href = '../login.php';
                            }
                            
                            reject(new Error(errorMsg));
                        }
                    });
                });
            }

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

            // 加载教师有权限的年级和学科
            function loadTeacherSubjects(gradeId = null) {
                $.get('../api/index.php?route=score/teacher_subjects', { grade_id: gradeId })
                    .done(function(response) {
                        if (response.success) {
                            const grades = new Set();
                            const gradeSubjects = {}; // 存储年级和学科的关联关系
                            
                            if (!response.data || !Array.isArray(response.data)) {
                                showAlert('获取教师权限数据格式错误');
                                return;
                            }
                            
                            response.data.forEach(item => {
                                if (!item || !item.grade_id || !item.grade_name) {
                                    console.error('年级数据不完整:', item);
                                    return;
                                }
                                
                                // 添加年级
                                grades.add(JSON.stringify({
                                    id: item.grade_id,
                                    name: item.grade_name
                                }));
                                
                                // 记录每个年级对应的学科
                                if (!gradeSubjects[item.grade_id]) {
                                    gradeSubjects[item.grade_id] = new Set();
                                }
                                
                                if (!item.subject_id || !item.subject_name) {
                                    console.error('学科数据不完整:', item);
                                    return;
                                }
                                
                                gradeSubjects[item.grade_id].add(JSON.stringify({
                                    id: item.subject_id,
                                    name: item.subject_name,
                                    full_score: item.full_score || 100,
                                    excellent_score: item.excellent_score || 90,
                                    good_score: item.good_score || 80,
                                    pass_score: item.pass_score || 60
                                }));
                            });

                            // 填充年级下拉框
                            const gradeSelect = $('#gradeFilter');
                            gradeSelect.empty();
                            gradeSelect.append('<option value="">选择年级</option>');
                            
                            Array.from(grades).forEach(grade => {
                                const g = JSON.parse(grade);
                                gradeSelect.append(`<option value="${g.id}" data-code="${g.code}">${g.name}</option>`);
                            });

                            // 年级选择变化时更新学科列表
                            gradeSelect.change(function() {
                                const selectedGradeId = $(this).val();
                                const gradeCode = $('option:selected', this).data('code');
                                currentGrade = selectedGradeId;
                                
                                const subjectSelect = $('#subjectFilter');
                                subjectSelect.empty();
                                
                                if (!selectedGradeId) {
                                    subjectSelect.append('<option value="">请先选择年级</option>');
                                    $('#classFilter').empty().append('<option value="">请先选择年级</option>');
                                    return;
                                }
                                
                                subjectSelect.append('<option value="">选择学科</option>');
                                
                                if (gradeSubjects[selectedGradeId]) {
                                    Array.from(gradeSubjects[selectedGradeId]).forEach(subject => {
                                        const s = JSON.parse(subject);
                                        subjectSelect.append(
                                            `<option value="${s.id}" 
                                            data-code="${s.code}"
                                            data-full-score="${s.full_score}"
                                            data-excellent-score="${s.excellent_score}"
                                            data-good-score="${s.good_score}"
                                            data-pass-score="${s.pass_score}"
                                            >${s.name}</option>`
                                        );
                                    });
                                }
                                
                                // 加载班级列表
                                loadClasses(selectedGradeId);
                                
                                // 重新初始化自定义下拉框
                                setTimeout(initCustomSelects, 100);
                            });
                        } else {
                            showAlert(response.error || '加载年级和学科失败');
                        }
                    })
                    .fail(function(xhr) {
                        const errorMsg = xhr.responseJSON?.error || '未知错误';
                        showAlert('加载年级和学科失败：' + errorMsg);
                        console.error('加载教师权限失败:', xhr);
                    });
            }

            // 修改加载班级列表函数
            function loadClasses(gradeId) {
                const classSelect = $('#classFilter');
                classSelect.empty();
                
                if (!gradeId) {
                    classSelect.append('<option value="">请先选择年级</option>');
                    // 重新初始化自定义下拉框
                    setTimeout(initCustomSelects, 100);
                    return;
                }

                const subjectId = $('#subjectFilter').val();
                if (!subjectId) {
                    classSelect.append('<option value="">请先选择学科</option>');
                    // 重新初始化自定义下拉框
                    setTimeout(initCustomSelects, 100);
                    return;
                }

                $.get('../api/index.php?route=score/teacher_classes', { 
                    grade_id: gradeId,
                    subject_id: subjectId
                }, function(response) {
                    if (response.success) {
                        classSelect.empty();
                        classSelect.append('<option value="">选择班级</option>');
                        
                        if (response.data && response.data.length > 0) {
                            response.data.forEach(function(cls) {
                                classSelect.append(`<option value="${cls.id}">${cls.class_name}</option>`);
                            });
                        } else {
                            classSelect.append('<option value="" disabled>该年级下暂无班级</option>');
                        }
                        
                        // 重新初始化自定义下拉框
                        setTimeout(initCustomSelects, 100);
                    } else {
                        showAlert(response.error || '加载班级失败');
                    }
                }).fail(function(xhr) {
                    showAlert('加载班级失败：' + (xhr.responseJSON?.error || '未知错误'));
                });
            }

            // 格式化分数显示
            function formatScore(score, isAbsent) {
                if (isAbsent === true || isAbsent === '1') return '缺考';
                if (!score || score === '0' || score === null) return '';
                const num = parseFloat(score);
                if (isNaN(num)) return '';
                // 如果是整数，直接返回整数
                if (Number.isInteger(num)) {
                    return num.toString();
                }
                // 否则四舍五入保留一位小数
                return num.toFixed(1);
            }

            // 获取等级的函数
            function getScoreLevel(score, isAbsent) {
                if (isAbsent === true || isAbsent === '1') return ['level-absent', '缺考'];
                if (!score || score === '' || score === null) return ['', '']; // 如果没有成绩，返回空
                
                score = parseFloat(score);
                if (isNaN(score)) return ['', '']; // 如果成绩无效，返回空
                
                const selectedOption = $('#subjectFilter option:selected');
                if (!selectedOption.length) return ['', ''];

                const excellentScore = parseFloat(selectedOption.data('excellent-score'));
                const goodScore = parseFloat(selectedOption.data('good-score'));
                const passScore = parseFloat(selectedOption.data('pass-score'));

                if (isNaN(excellentScore) || isNaN(goodScore) || isNaN(passScore)) {
                    console.error('分数线获取失败:', selectedOption.data());
                    return ['', ''];
                }

                if (score >= excellentScore) return ['level-excellent', '优秀'];
                if (score >= goodScore) return ['level-good', '良好'];
                if (score >= passScore) return ['level-pass', '合格'];
                return ['level-fail', '待合格'];
            }

            // 生成成绩输入组件
            function generateScoreInput(student, type, placeholder, isEditing, showAbsent) {
                const isAbsent = student.is_absent === '1' || student.is_absent === 1 || student.is_absent === true;
                const score = type === 'base' ? student.base_score : student.extra_score;
                const isPending = student.isPending === true;
                
                // 获取对应的满分值
                let maxScore = 100;
                if (window.currentSubject?.isSplit) {
                    maxScore = type === 'base' ? 
                        parseFloat(window.currentSubject.splitScore1) : 
                        parseFloat(window.currentSubject.splitScore2);
                } else {
                    const selectedOption = $('#subjectFilter option:selected');
                    maxScore = parseFloat(selectedOption.data('full-score')) || 100;
                }
                
                // 添加待审核标记类
                const pendingClass = isPending ? 'pending-edit' : '';
                
                return `
                    <div class="score-input-group">
                        <input type="text" 
                            class="form-control form-control-sm score-input ${type}-score ${pendingClass}" 
                            value="${isAbsent ? '' : formatScore(score, false)}"
                            data-student-id="${student.id}"
                            data-type="${type}"
                            data-max-score="${maxScore}"
                            placeholder="${placeholder}"
                            ${window.isEditing ? '' : 'readonly'}
                            ${isAbsent ? 'disabled' : ''}>
                        ${showAbsent && window.isEditing ? `
                        <div class="form-check">
                            <input class="form-check-input absent-checkbox" 
                                type="checkbox" 
                                id="absent-${student.id}-${type}"
                                ${isAbsent ? 'checked' : ''}
                                data-student-id="${student.id}">
                            <label class="form-check-label absent-label" for="absent-${student.id}-${type}">
                                缺
                            </label>
                        </div>
                        ` : ''}
                    </div>
                `;
            }

            // 生成表格的函数
            function generateTable(students) {
                if (!students || students.length === 0) {
                    return '<div class="alert alert-warning">该班级暂无学生，请先添加学生。</div>';
                }
                
                let tableHtml = '<table class="table table-bordered table-hover align-middle">';
                tableHtml += '<thead><tr>';
                tableHtml += '<th style="width: 55px;">编号</th>';
                tableHtml += '<th style="width: 60px;">姓名</th>';
                
                // 根据是否拆分显示不同的列
                if (window.currentSubject?.isSplit) {
                    if (window.isEditing) {
                        tableHtml += `<th style="width: 80px;">${window.currentSubject.splitName1 || '基础分'}</th>`;
                        tableHtml += `<th style="width: 80px;">${window.currentSubject.splitName2 || '操作分'}</th>`;
                        tableHtml += '<th style="width: 50px;">总分</th>';
                    } else {
                        tableHtml += `<th style="width: 80px;">${window.currentSubject.name}成绩</th>`;
                    }
                } else {
                    tableHtml += `<th style="width: ${window.isEditing ? '80px' : '80px'}">${window.currentSubject.name}成绩</th>`;
                }
                
                tableHtml += '<th style="width: 45px;">等级</th>';
                tableHtml += '</tr></thead><tbody>';
                
                students.forEach(student => {
                    const isAbsent = student.is_absent === '1' || student.is_absent === 1;
                    const hasScore = student.total_score !== null && student.total_score !== '' && !isNaN(parseFloat(student.total_score));
                    const isPending = window.pendingEditStudents && window.pendingEditStudents[student.id.toString()];
                    
                    // 添加待审核标记类
                    const pendingClass = isPending ? 'pending-edit-row' : '';
                    
                    tableHtml += `<tr class="score-row ${pendingClass}" data-id="${student.id}">`;
                    tableHtml += `<td>${student.student_number || ''}</td>`;
                    tableHtml += `<td>${student.student_name}</td>`;
                    
                    if (window.currentSubject?.isSplit) {
                        if (window.isEditing) {
                            // 第一个拆分成绩（不带缺考按钮）
                            tableHtml += `<td>${generateScoreInput({
                                id: student.id,
                                base_score: student.base_score,
                                extra_score: student.extra_score,
                                is_absent: isAbsent,
                                isPending: isPending
                            }, 'base', window.currentSubject.splitName1 || '基础分', true, false)}</td>`;
                            // 第二个拆分成绩（带缺考按钮）
                            tableHtml += `<td>${generateScoreInput({
                                id: student.id,
                                base_score: student.base_score,
                                extra_score: student.extra_score,
                                is_absent: isAbsent,
                                isPending: isPending
                            }, 'extra', window.currentSubject.splitName2 || '操作分', true, true)}</td>`;
                            // 总分
                            const totalScore = isAbsent ? '缺考' : formatScore(student.total_score, false);
                            tableHtml += `<td><span class="total-score">${totalScore}</span></td>`;
                        } else {
                            // 查看模式只显示总分
                            const totalScore = isAbsent ? '缺考' : formatScore(student.total_score, false);
                            tableHtml += `<td><span class="total-score">${totalScore}</span></td>`;
                        }
                    } else {
                        if (window.isEditing) {
                            // 编辑模式显示输入框
                            tableHtml += `<td>${generateScoreInput({
                                id: student.id,
                                base_score: student.total_score,
                                is_absent: isAbsent,
                                isPending: isPending
                            }, 'base', '成绩', true, true)}</td>`;
                        } else {
                            // 查看模式显示分数或缺考
                            tableHtml += `<td><span class="total-score ${isPending ? 'pending-edit' : ''}">${isAbsent ? '缺考' : formatScore(student.total_score, false)}</span></td>`;
                        }
                    }
                    
                    // 等级显示
                    let levelClass = '';
                    let levelText = '';
                    
                    if (isAbsent) {
                        levelClass = 'level-absent';
                        levelText = '缺考';
                    } else if (hasScore) {
                        // 只有在有成绩的情况下才显示等级
                        const [cls, text] = getScoreLevel(student.total_score, false);
                        levelClass = cls;
                        levelText = text;
                    }
                    
                    tableHtml += `<td><span class="score-level ${levelClass}">${levelText}</span></td>`;
                    tableHtml += '</tr>';
                });
                
                tableHtml += '</tbody></table>';
                return tableHtml;
            }

            // 加载学生成绩列表
            function loadScores() {
                const classId = $('#classFilter').val();
                const subjectId = $('#subjectFilter').val();
                
                if (!classId || !subjectId) {
                    $('#scoreList').html('<div class=\"alert alert-info\">请选择学科和班级</div>');
                    return;
                }

                $.get('../api/index.php?route=score/student_scores', {
                    class_id: classId,
                    subject_id: subjectId
                })
                .done(function(response) {
                    if (response.success) {
                        if (!response.data || response.data.length === 0) {
                            $('#scoreList').html('<div class=\"alert alert-warning\">该班级暂无学生数据</div>');
                            return;
                        }

                        // 保存原始成绩数据用于比较
                        if (window.isEditing) {
                            window.originalScores = {};
                            response.data.forEach(student => {
                                window.originalScores[student.id.toString()] = {
                                    base_score: student.base_score,
                                    extra_score: student.extra_score,
                                    total_score: student.total_score,
                                    is_absent: student.is_absent === '1' || student.is_absent === 1
                                };
                            });
                        }

                        // 将学生数据分成多个表格，每个表格固定20行
                        const students = response.data;
                        const columns = [[], [], []];
                        const rowsPerTable = 20;
                        
                        // 计算需要多少列来显示所有学生
                        const totalTables = Math.ceil(students.length / rowsPerTable);
                        const columnsNeeded = Math.min(totalTables, 3); // 最多3列
                        
                        // 将学生数据平均分配到每列
                        for (let i = 0; i < students.length; i++) {
                            const columnIndex = Math.floor(i / rowsPerTable);
                            if (columnIndex < 3) { // 最多3列
                                columns[columnIndex].push(students[i]);
                            }
                        }

                        let html = '<div class="row">';
                        
                        // 生成表格
                        columns.forEach((columnData, index) => {
                            if (columnData && columnData.length > 0) {
                                html += '<div class="col-4">';
                                html += generateTable(columnData);
                                html += '</div>';
                            }
                        });

                        html += '</div>';
                        $('#scoreList').html(html);

                        // 根据编辑状态显示/隐藏按钮
                        $('#editBtn').toggle(!window.isEditing);
                        
                        // 根据编辑状态和是否有统计分析数据显示不同的按钮
                        if (window.isEditing) {
                            if (window.hasAnalyticsData) {
                                // 如果已有统计分析数据且在编辑模式，显示"提交修改申请"按钮和"放弃修改"按钮
                                $('#saveBtn').hide();
                                $('#submitEditRequestBtn').show();
                                $('#cancelEditBtn').show();
                                
                                // 调试日志
                                console.log('编辑模式下已有统计分析数据，显示"提交修改申请"和"放弃修改"按钮');
                            } else {
                                // 如果没有统计分析数据但在编辑模式，显示"完成录入"按钮
                                $('#saveBtn').show().html('<i class="fas fa-check me-1"></i>完成录入');
                                $('#submitEditRequestBtn').hide();
                                $('#cancelEditBtn').show(); // 确保放弃按钮也显示
                                window.hasAnalyticsData = false;
                                console.log('编辑模式下无统计分析数据，显示"完成录入"按钮');
                            }
                        } else {
                            // 非编辑模式下隐藏所有操作按钮
                            $('#saveBtn').hide();
                            $('#submitEditRequestBtn').hide();
                            $('#cancelEditBtn').hide();
                        }
                        
                        // 如果在编辑模式，确保输入框可编辑，但缺考学生的输入框保持禁用
                        if (window.isEditing) {
                            $('.score-input').each(function() {
                                const $input = $(this);
                                const $row = $input.closest('tr');
                                const isAbsent = $row.find('.absent-checkbox').prop('checked');
                                $input.prop('readonly', false).prop('disabled', isAbsent);
                            });
                        }
                        
                        // 检查是否所有成绩已录入
                        checkAllScoresEntered().then(allEntered => {
                            if (allEntered) {
                                // 检查是否已有统计分析数据
                                checkAnalyticsExists(classId, subjectId);
                            }
                        }).catch(error => {
                            console.error('检查成绩录入状态失败:', error);
                        });
                        
                        // 无论成绩是否全部录入，都直接检查是否存在统计分析数据
                        checkAnalyticsExists(classId, subjectId);
                    } else {
                        $('#scoreList').html('<div class="alert alert-warning">加载学生名单失败</div>');
                    }
                })
                .fail(function(xhr) {
                    const errorMsg = xhr.responseJSON?.error || '未知错误';
                    $('#scoreList').html(`<div class="alert alert-danger">加载学生名单失败：${errorMsg}</div>`);
                });
            }

            // 修改生成统计分析函数，添加前置检查
            function generateAnalytics() {
                return new Promise((resolve, reject) => {
                    if (!window.currentSettingId) {
                        reject(new Error('未获取到当前项目ID'));
                        return;
                    }

                    // 先检查是否所有成绩都已录入
                    $.get('../api/index.php?route=score/check_all_scores', {
                        class_id: $('#classFilter').val(),
                        subject_id: $('#subjectFilter').val()
                    })
                    .done(function(checkResponse) {
                        if (!checkResponse.success) {
                            reject(new Error('检查成绩录入状态失败'));
                            return;
                        }

                        if (!checkResponse.all_entered) {
                            const missingStudents = checkResponse.missing_students || [];
                            if (missingStudents.length > 0) {
                                reject(new Error('还有学生未录入成绩：' + missingStudents.join('、')));
                                return;
                            }
                        }

                        // 只有在所有成绩都已录入的情况下，才执行统计分析
                        if (checkResponse.all_entered) {
                            $.ajax({
                                url: '../api/index.php?route=analytics/generate',
                                method: 'POST',
                                data: {
                                    grade_id: $('#gradeFilter').val(),
                                    class_id: $('#classFilter').val(),
                                    subject_id: $('#subjectFilter').val(),
                                    setting_id: window.currentSettingId
                                },
                                success: function(response) {
                                    if (response.success) {
                                        //console.log('统计分析生成成功:', response);
                                        resolve(response);
                                    } else {
                                        console.error('生成统计分析失败:', response.error);
                                        reject(new Error('生成统计分析失败：' + response.error));
                                    }
                                },
                                error: function(xhr) {
                                    console.error('生成统计分析请求失败:', xhr);
                                    reject(new Error('生成统计分析失败：' + (xhr.responseJSON?.error || '未知错误')));
                                }
                            });
                        } else {
                            reject(new Error('还有未录入的成绩，无法生成统计分析'));
                        }
                    })
                    .fail(function(xhr) {
                        reject(new Error('检查成绩录入状态失败：' + (xhr.responseJSON?.error || '未知错误')));
                    });
                });
            }

            // 修改保存按钮点击事件
            $('#saveBtn').off('click').on('click', function(e) {
                e.preventDefault();
                //console.log('点击保存按钮，检查成绩录入状态');
                
                // 检查是否已有统计分析数据
                if (window.hasAnalyticsData) {
                    // 如果已有统计分析数据，提示用户需要提交修改申请
                    Swal.fire({
                        title: '需要提交修改申请',
                        html: `
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>该班级的成绩统计分析数据已存在。
                                <br>如需修改成绩，请点击"提交修改申请"按钮，提交修改申请。
                            </div>
                        `,
                        confirmButtonText: '确定'
                    });
                    return;
                }
                
                // 检查是否所有成绩都已录入
                $.get('../api/index.php?route=score/check_all_scores', {
                    class_id: $('#classFilter').val(),
                    subject_id: $('#subjectFilter').val()
                })
                .done(function(response) {
                    if (!response.success) {
                        showAlert(response.error || '检查成绩录入状态失败');
                        return;
                    }

                    
                    // 确认所有成绩都已录入后，才执行保存
                    if (response.all_entered) {
                        //console.log('所有成绩已录入，执行保存');
                        
                        // 退出编辑模式
                        window.isEditing = false;
                        
                        // 更新界面状态
                        $('#saveBtn').hide();
                        $('#submitEditRequestBtn').hide();
                        $('#editBtn').show();
                        $('#editTips').hide();
                        $('#sortByNameBtn').hide().removeClass('active');
                        $('#sortByNumberBtn').hide().removeClass('active');
                        
                        // 启用筛选下拉框
                        $('.custom-select-wrapper').removeClass('disabled').css('pointer-events', 'auto');
                        $('.custom-select-trigger').css({
                            'opacity': '1',
                            'background': 'linear-gradient(to bottom, #ffffff, #f8f9fa)',
                            'cursor': 'pointer'
                        });
                        
                        // 重新加载成绩列表
                        loadScores();
                        
                        // 检查是否已有统计分析数据
                        $.get('../api/index.php?route=analytics/get', {
                            class_id: $('#classFilter').val(),
                            subject_id: $('#subjectFilter').val(),
                            setting_id: window.currentSettingId
                        })
                        .done(function(analyticsResponse) {
                            // 如果已有统计分析数据，则提示用户需要提交修改申请
                            if (analyticsResponse.success && analyticsResponse.data) {
                                window.hasAnalyticsData = true;
                                
                                // 显示提示对话框
                                Swal.fire({
                                    title: '成绩已存在',
                                    html: `
                                        <div class="alert alert-info">
                                            <i class="fas fa-info-circle me-2"></i>该班级的成绩统计分析数据已存在。
                                            <br>如需修改成绩，请点击"修改数据"按钮，然后提交修改申请。
                                        </div>
                                    `,
                                    confirmButtonText: '确定'
                                });
                            } else {
                                // 如果没有统计分析数据，则生成新的统计分析
                                window.hasAnalyticsData = false;
                        
                        // 生成统计分析
                        generateAnalytics()
                            .then(response => {
                                showSuccess('成绩录入已完成，已生成统计分析数据。');
                                return loadAnalytics();
                            })
                            .catch(error => {
                                console.error('统计分析处理失败:', error);
                                showAlert(error.message);
                            });
                            }
                        })
                        .fail(function(xhr) {
                            console.error('检查统计分析数据失败:', xhr);
                            
                            // 默认生成统计分析
                            generateAnalytics()
                                .then(response => {
                                    showSuccess('成绩录入已完成，已生成统计分析数据。');
                                    return loadAnalytics();
                                })
                                .catch(error => {
                                    console.error('统计分析处理失败:', error);
                                    showAlert(error.message);
                                });
                        });
                    }
                })
                .fail(function(xhr) {
                    showAlert('检查成绩录入状态失败：' + (xhr.responseJSON?.error || '未知错误'));
                });
            });

            // 检查是否所有成绩都已录入
            function checkAllScoresEntered() {
                return new Promise((resolve, reject) => {
                    const classId = $('#classFilter').val();
                    const subjectId = $('#subjectFilter').val();

                    if (!classId || !subjectId) {
                        resolve(false);
                        return;
                    }

                    $.get('../api/index.php?route=score/check_all_entered', {
                        class_id: classId,
                        subject_id: subjectId,
                        setting_id: window.currentSettingId  // 使用全局存储的项目ID
                    })
                    .done(function(response) {
                        if (response.success) {
                            resolve(response.data.all_entered);
                            // 如果全部录入完成，更新状态显示
                            if (response.data.all_entered) {
                                updateScoreStatus('completed');
                                // 更新按钮文本为"修改数据"
                                $('#editBtn').html('<i class="fas fa-edit me-1"></i>修改数据');
                                
                                // 检查是否已有统计分析数据
                                checkAnalyticsExists(classId, subjectId);
                            } else {
                                // 未全部录入时，按钮文本为"录入数据"
                                $('#editBtn').html('<i class="fas fa-edit me-1"></i>录入数据');
                                // 隐藏提交修改申请按钮
                                $('#submitEditRequestBtn').hide();
                            }
                        } else {
                            reject(new Error(response.error || '检查成绩录入状态失败'));
                        }
                    })
                    .fail(function(xhr) {
                        reject(new Error(xhr.responseJSON?.error || '检查成绩录入状态失败'));
                    });
                });
            }
            
            // 检查是否已有统计分析数据
            function checkAnalyticsExists(classId, subjectId) {
                if (!window.currentSettingId || !classId || !subjectId) {
                    return;
                }
                
                // 直接使用student_scores接口检查是否有成绩数据
                $.get('../api/index.php?route=score/student_scores', {
                    class_id: classId,
                    subject_id: subjectId
                })
                .done(function(response) {
                    if (response.success && response.data && response.data.length > 0) {
                        // 检查是否有任何学生的成绩已录入
                        const hasAnyScores = response.data.some(student => 
                            student.score_id !== null && student.total_score !== null);
                        
                        if (hasAnyScores) {
                            // 检查是否所有学生都已录入成绩
                            const allEntered = response.data.every(student => 
                                student.score_id !== null && 
                                (student.total_score !== null || student.is_absent === '1' || student.is_absent === 1));
                            
                            if (allEntered) {
                                // 如果所有学生都已录入成绩，则更新按钮状态为"修改数据"
                                $('#editBtn').html('<i class="fas fa-edit me-1"></i>修改数据');
                                window.hasAnalyticsData = true;
                                
                                // 如果当前处于编辑模式，则显示"提交修改申请"按钮而不是"完成录入"按钮
                                if (window.isEditing) {
                                    $('#saveBtn').hide();
                                    $('#submitEditRequestBtn').show();
                                }
                                
                                // 调试日志
                                console.log('检测到所有学生已录入成绩，按钮已更新为"修改数据"');
                            } else {
                                // 如果只有部分学生已录入成绩，则更新按钮状态为"录入数据"
                                $('#editBtn').html('<i class="fas fa-edit me-1"></i>录入数据');
                                window.hasAnalyticsData = false;
                                
                                // 如果当前处于编辑模式，则显示"完成录入"按钮而不是"提交修改申请"按钮
                                if (window.isEditing) {
                                    $('#saveBtn').show().html('<i class="fas fa-check me-1"></i>完成录入');
                                    $('#submitEditRequestBtn').hide();
                                }
                                
                                // 调试日志
                                console.log('检测到部分学生已录入成绩，按钮为"录入数据"');
                            }
                        } else {
                            // 如果没有学生已录入成绩，则更新按钮状态为"录入数据"
                            $('#editBtn').html('<i class="fas fa-edit me-1"></i>录入数据');
                            window.hasAnalyticsData = false;
                            
                            // 如果当前处于编辑模式，则显示"完成录入"按钮而不是"提交修改申请"按钮
                            if (window.isEditing) {
                                $('#saveBtn').show().html('<i class="fas fa-check me-1"></i>完成录入');
                                $('#submitEditRequestBtn').hide();
                            }
                            
                            // 调试日志
                            console.log('未检测到已录入的成绩，按钮为"录入数据"');
                        }
                    } else {
                        $('#editBtn').html('<i class="fas fa-edit me-1"></i>录入数据');
                        window.hasAnalyticsData = false;
                        
                        // 如果当前处于编辑模式，则显示"完成录入"按钮而不是"提交修改申请"按钮
                        if (window.isEditing) {
                            $('#saveBtn').show().html('<i class="fas fa-check me-1"></i>完成录入');
                            $('#submitEditRequestBtn').hide();
                        }
                        
                        // 调试日志
                        console.log('未检测到学生数据，按钮为"录入数据"');
                    }
                })
                .fail(function(xhr) {
                    $('#editBtn').html('<i class="fas fa-edit me-1"></i>录入数据');
                    window.hasAnalyticsData = false;
                    
                    // 如果当前处于编辑模式，则显示"完成录入"按钮而不是"提交修改申请"按钮
                    if (window.isEditing) {
                        $('#saveBtn').show().html('<i class="fas fa-check me-1"></i>完成录入');
                        $('#submitEditRequestBtn').hide();
                    }
                    
                    // 调试日志
                    console.error('检查学生成绩失败:', xhr.status, xhr.statusText);
                });
            }

            // 更新成绩录入状态显示
            function updateScoreStatus(status) {
                const $statusBadge = $('.score-status');
                $statusBadge.removeClass('badge-warning badge-success');
                
                if (status === 'completed') {
                    $statusBadge.addClass('badge-success').text('已完成');
                } else {
                    $statusBadge.addClass('badge-warning').text('录入中');
                }
            }

            // 保存单个成绩
            function saveScore(studentId, type, value, isAbsent) {
                // 构建要提交的数据对象
                const formData = new FormData();
                formData.append('student_id', studentId);
                formData.append('subject_id', $('#subjectFilter').val());
                formData.append('class_id', $('#classFilter').val());
                formData.append('grade_id', $('#gradeFilter').val());
                formData.append('is_absent', isAbsent ? 1 : 0);
                formData.append('setting_id', window.currentSettingId || 1);

                // 如果是拆分成绩
                if (window.currentSubject?.isSplit) {
                    if (isAbsent) {
                        formData.append('base_score', '');
                        formData.append('extra_score', '');
                        formData.append('total_score', '');
                    } else {
                        const $row = $(`.score-input[data-student-id="${studentId}"]`).closest('tr');
                        const baseScore = $row.find('.base-score').val().trim();
                        const extraScore = $row.find('.extra-score').val().trim();

                        // 如果是保存第一个分数，但第二个还没有，则等待
                        if (type === 'base' && !extraScore) return;
                        // 如果是保存第二个分数，但第一个还没有，则等待
                        if (type === 'extra' && !baseScore) return;

                        const totalScore = calculateTotalScore(baseScore, extraScore);

                        // 分别保存拆分成绩
                        formData.append('base_score', baseScore || '');
                        formData.append('extra_score', extraScore || '');
                        formData.append('total_score', totalScore || '');
                    }
                } else {
                    // 非拆分成绩直接保存到总分
                    if (!isAbsent) {
                        formData.append('base_score', '');
                        formData.append('extra_score', '');
                        formData.append('total_score', value || '');
                    } else {
                        formData.append('base_score', '');
                        formData.append('extra_score', '');
                        formData.append('total_score', '');
                    }
                }

                // 打印要发送的数据
                const debugData = {};
                for (let [key, value] of formData.entries()) {
                    debugData[key] = value;
                }
                //console.log('保存成绩数据:', debugData);
                
                // 发送请求
                $.ajax({
                    url: '../api/index.php?route=score/save_score',
                    method: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        if (response.success) {
                            if (type) {
                                const input = $(`.score-input[data-student-id="${studentId}"][data-type="${type}"]`);
                                input.addClass('success');
                                setTimeout(() => {
                                    input.removeClass('success');
                                }, 500);
                                
                                // 更新等级显示
                                const $row = input.closest('tr');
                                const totalScore = window.currentSubject?.isSplit ? 
                                    calculateTotalScore(
                                    formData.get('base_score'),
                                    formData.get('extra_score')
                                    ) : formData.get('total_score');
                                
                                const [levelClass, levelText] = getScoreLevel(totalScore, isAbsent);
                                $row.find('.score-level')
                                    .attr('class', `score-level ${levelClass}`)
                                    .text(levelText);
                                
                                // 如果是拆分成绩，更新总分显示
                                if (window.currentSubject?.isSplit) {
                                    $row.find('.total-score').text(formatScore(totalScore, isAbsent));
                                }
                            }
                            //console.log('成绩保存成功，响应数据:', response);
                        } else {
                            console.error('保存失败:', response.error);
                            showAlert(response.error || '保存失败');
                        }
                    },
                    error: function(xhr) {
                        console.error('保存失败:', xhr.responseText);
                        showAlert('保存失败：' + (xhr.responseJSON?.error || '未知错误'));
                    }
                });
            }

            // 计算总分
            function calculateTotalScore(baseScore, extraScore) {
                if (!baseScore && !extraScore) return null;
                const base = parseFloat(baseScore) || 0;
                const extra = parseFloat(extraScore) || 0;
                return base + extra;
            }

            // 验证分数格式
            function validateScore(value, type) {
                if (!value) return true;
                if (!/^\d{1,3}(\.\d)?$/.test(value)) return false;
                const score = parseFloat(value);
                
                // 获取对应的满分值
                let maxScore = 100;
                if (window.currentSubject?.isSplit) {
                    maxScore = type === 'base' ? 
                        parseFloat(window.currentSubject.splitScore1) : 
                        parseFloat(window.currentSubject.splitScore2);
                } else {
                const selectedOption = $('#subjectFilter option:selected');
                    maxScore = parseFloat(selectedOption.data('full-score')) || 100;
                }
                
                if (isNaN(maxScore)) {
                    console.error('获取满分分数失败');
                    return false;
                }
                
                if (score > maxScore) {
                    showAlert(`分数不能超过${maxScore}分`, 'warning');
                    return false;
                }
                
                return score >= 0 && score <= maxScore;
            }

            // 加载统计分析数据
            function loadAnalytics() {
                const gradeId = $('#gradeFilter').val();
                const classId = $('#classFilter').val();
                const subjectId = $('#subjectFilter').val();

                if (!gradeId || !classId || !subjectId) {
                    //console.log('缺少必要参数，无法加载统计分析');
                    return;
                }

                $.get('../api/index.php?route=analytics/get', {
                    grade_id: gradeId,
                    class_id: classId,
                    subject_id: subjectId
                })
                .done(function(response) {
                    if (response.success) {
                        //console.log('统计分析数据加载成功:', response.data);
                        // 这里可以添加显示统计分析数据的逻辑
                        // 例如更新页面上的统计数据显示
                    } else {
                        console.error('加载统计分析失败:', response.error);
                    }
                })
                .fail(function(xhr) {
                    console.error('加载统计分析失败:', xhr.responseJSON?.error || '未知错误');
                });
            }

            // 检查是否可以显示编辑按钮
            function checkShowEditButton() {
                const gradeId = $('#gradeFilter').val();
                const subjectId = $('#subjectFilter').val();
                const classId = $('#classFilter').val();
                
                /*console.log('检查是否显示编辑按钮:', {
                    gradeId,
                    subjectId,
                    classId,
                    userRole: window.userRole
                });*/

                // 只有当所有选项都已选择,且用户是阅卷老师,且班级有学生数据时才显示编辑按钮
                if (gradeId && subjectId && classId && window.userRole === 'marker') {
                    // 检查班级是否有学生数据
                    $.get('../api/index.php?route=score/student_scores', {
                        class_id: classId,
                        subject_id: subjectId
                    })
                    .done(function(response) {
                        if (response.success && response.data && response.data.length > 0) {
                    $('#editBtn').show();
                            
                            // 检查是否已有统计分析数据，更新按钮文本
                            checkAnalyticsExists(classId, subjectId);
                        } else {
                            $('#editBtn').hide();
                        }
                    })
                    .fail(function() {
                        $('#editBtn').hide();
                    });
                } else {
                    $('#editBtn').hide();
                }
            }

            // 修改年级选择事件
            $('#gradeFilter').change(function() {
                const selectedGradeId = $(this).val();
                currentGrade = selectedGradeId;
                
                // 重置学科和班级选择
                $('#subjectFilter').empty().append('<option value="">请先选择年级</option>');
                $('#classFilter').empty().append('<option value="">请先选择年级</option>');
                
                // 隐藏编辑按钮
                $('#editBtn').hide();
                
                if (!selectedGradeId) {
                    return;
                }

                // 加载学科列表...（原有代码）
            });

            // 修改学科选择事件
            $('#subjectFilter').change(function() {
                const selectedOption = $('option:selected', this);
                const subjectName = selectedOption.text();
                $('#scoreTitle').text(subjectName + '成绩录入');
                
                // 获取拆分成绩设置
                const isSplit = selectedOption.data('is-split');
                /*console.log('拆分成绩设置:', {
                    rawValue: selectedOption.data('is-split'),
                    isSplit: isSplit,
                    splitName1: selectedOption.data('split-name-1'),
                    splitName2: selectedOption.data('split-name-2'),
                    splitScore1: selectedOption.data('split-score-1'),
                    splitScore2: selectedOption.data('split-score-2')
                });*/
                
                // 判断是否启用拆分成绩
                window.currentSubject = {
                    id: $(this).val(),
                    name: subjectName,
                    isSplit: isSplit == 1 || isSplit === '1' || isSplit === true,
                    splitName1: selectedOption.data('split-name-1'),
                    splitName2: selectedOption.data('split-name-2'),
                    splitScore1: selectedOption.data('split-score-1'),
                    splitScore2: selectedOption.data('split-score-2')
                };
                    
                    /* 调试输出
                    console.log('学科选择变更:', {
                        subjectName,
                    currentSubject: window.currentSubject
                    });*/
                    
                    // 更新班级列表
                    loadClasses(currentGrade);
                    
                    // 如果已选择班级，重新加载成绩列表
                    if (currentClass) {
                        loadScores();
                    }
                });
                
            // 修改班级选择事件
                $('#classFilter').change(function() {
                    currentClass = $(this).val();
                
                    if (currentClass) {
                        loadScores();
                    // 检查是否显示编辑按钮
                    checkShowEditButton();
                        
                        // 检查是否已有统计分析数据
                        if (currentClass && $('#subjectFilter').val()) {
                            checkAnalyticsExists(currentClass, $('#subjectFilter').val());
                        }
                    } else {
                        $('#scoreList').empty();
                    // 隐藏编辑按钮
                    $('#editBtn').hide();
                    }
                });

                // 修改按钮点击事件
                $('#editBtn').click(function() {
                    //console.log('点击编辑按钮，切换到编辑模式');
                    window.isEditing = true;
                    
                    // 存储编辑状态到sessionStorage
                    sessionStorage.setItem('isEditing', 'true');
                    sessionStorage.setItem('editClass', $('#classFilter').val());
                    sessionStorage.setItem('editSubject', $('#subjectFilter').val());
                
                // 确保当前学科信息正确
                const selectedOption = $('#subjectFilter option:selected');
                const isSplit = selectedOption.data('is-split');
                /*console.log('编辑模式 - 拆分成绩设置:', {
                    rawValue: selectedOption.data('is-split'),
                    isSplit: isSplit,
                    splitName1: selectedOption.data('split-name-1'),
                    splitName2: selectedOption.data('split-name-2'),
                    splitScore1: selectedOption.data('split-score-1'),
                    splitScore2: selectedOption.data('split-score-2')
                });*/
                
                window.currentSubject = {
                    id: selectedOption.val(),
                    name: selectedOption.text(),
                    isSplit: isSplit == 1 || isSplit === '1' || isSplit === true,
                    splitName1: selectedOption.data('split-name-1'),
                    splitName2: selectedOption.data('split-name-2'),
                    splitScore1: selectedOption.data('split-score-1'),
                    splitScore2: selectedOption.data('split-score-2')
                };
                    
                    // 更新界面为编辑模式
                    updateUIForEditMode();
                });

                // 保存按钮点击事件
                $('#saveBtn').click(function() {
                    //console.log('点击保存按钮，退出编辑模式');
                    
                    // 检查是否有错误输入
                    const hasErrors = $('.score-input.error').length > 0;
                    if (hasErrors) {
                        showAlert('请修正输入错误后再保存');
                        return;
                    }
                    
                    // 检查是否所有成绩都已录入
                    checkAllScoresEntered().then(allEntered => {
                        if (allEntered) {
                            // 如果所有成绩都已录入，生成统计分析
                            if (!window.hasAnalyticsData) {
                                // 如果还没有统计分析数据，则生成
                                generateAnalytics()
                                    .then(response => {
                                        // 更新按钮状态
                                        $('#editBtn').html('<i class="fas fa-edit me-1"></i>修改数据');
                                        window.hasAnalyticsData = true;
                                        
                                        // 退出编辑模式
                                        exitEditMode();
                                        
                                        // 显示成功消息
                                        showAlert('成绩录入完成，统计分析已生成', 'success');
                                    })
                                    .catch(error => {
                                        console.error('生成统计分析失败:', error);
                                        showAlert('成绩已保存，但统计分析生成失败：' + error.message);
                                        
                                        // 仍然退出编辑模式
                                        exitEditMode();
                                    });
                            } else {
                                // 如果已有统计分析数据，直接退出编辑模式
                                exitEditMode();
                                showAlert('成绩修改已保存', 'success');
                            }
                        } else {
                            // 如果还有未录入的成绩，提示用户
                            showAlert('部分学生成绩未录入，请完成所有成绩录入后再保存');
                        }
                    }).catch(error => {
                        console.error('检查成绩录入状态失败:', error);
                        showAlert('检查成绩录入状态失败：' + error.message);
                    });
                });
                
                // 退出编辑模式的函数
                function exitEditMode() {
                    window.isEditing = false;
                    
                    // 显示/隐藏按钮
                    $('#saveBtn').hide();
                    $('#submitEditRequestBtn').hide();
                    $('#editBtn').show();
                    $('#sortByNameBtn').hide().removeClass('active');
                    $('#sortByNumberBtn').hide().removeClass('active');
                    $('#editTips').hide();
                    $('#cancelEditBtn').hide(); // 确保隐藏放弃按钮
                    $('#editActionButtons').hide(); // 隐藏编辑操作按钮容器
                    
                    // 启用筛选下拉框
                    $('.custom-select-wrapper').removeClass('disabled').css('pointer-events', 'auto');
                    $('.custom-select-trigger').css({
                        'opacity': '1',
                        'background': 'linear-gradient(to bottom, #ffffff, #f8f9fa)',
                        'cursor': 'pointer'
                    });
                    
                    // 重新加载成绩列表
                    loadScores();
                }

                // 提交修改申请按钮点击事件
                $('#submitEditRequestBtn').click(function() {
                    // 收集已修改的成绩数据
                    const editedScores = [];
                    
                    // 检查是否有修改
                    if (!window.modifiedScores || Object.keys(window.modifiedScores).length === 0) {
                        Swal.fire({
                            icon: 'info',
                            title: '无修改',
                            text: '您没有修改任何成绩数据',
                            confirmButtonText: '确定'
                        });
                        return;
                    }
                    
                    // 收集所有已修改的学生数据
                    for (const studentId in window.modifiedScores) {
                        if (window.modifiedScores.hasOwnProperty(studentId)) {
                            const $row = $(`.score-row[data-id="${studentId}"]`);
                            const studentName = $row.find('td:eq(1)').text();
                            const isAbsent = $row.find('.absent-checkbox').prop('checked') ? 1 : 0;
                            
                            let baseScore = null;
                            let extraScore = null;
                            let totalScore = null;
                            
                            if (window.currentSubject?.isSplit) {
                                baseScore = $row.find('.base-score').val() || null;
                                extraScore = $row.find('.extra-score').val() || null;
                                totalScore = calculateTotalScore(baseScore, extraScore);
                            } else {
                                totalScore = $row.find('.base-score').val() || null;
                            }
                            
                            // 获取原始成绩数据
                            const original = window.originalScores[studentId] || {
                                base_score: null,
                                extra_score: null,
                                total_score: null,
                                is_absent: false
                            };
                            
                            // 添加修改过的成绩
                            editedScores.push({
                                student_id: studentId,
                                student_name: studentName,
                                base_score: baseScore,
                                extra_score: extraScore,
                                total_score: totalScore,
                                is_absent: isAbsent,
                                // 添加原始成绩数据用于对比
                                old_base_score: original.base_score,
                                old_extra_score: original.extra_score,
                                old_total_score: original.total_score,
                                old_is_absent: original.is_absent ? 1 : 0
                            });
                        }
                    }
                    
                    // 生成修改记录HTML
                    let changesHtml = '<div class="table-responsive" style="max-height: 300px; overflow-y: auto;"><table class="table table-sm table-bordered">';
                    changesHtml += '<thead><tr><th>学生</th><th>修改项</th><th>原值</th><th>新值</th></tr></thead><tbody>';
                    
                    editedScores.forEach(score => {
                        const formatValue = (value, isAbsent) => {
                            if (isAbsent) return '缺考';
                            return value || '';
                        };
                        
                        if (score.is_absent !== score.old_is_absent) {
                            changesHtml += `<tr>
                                <td>${score.student_name}</td>
                                <td>缺考状态</td>
                                <td>${score.old_is_absent ? '缺考' : '正常'}</td>
                                <td>${score.is_absent ? '缺考' : '正常'}</td>
                            </tr>`;
                        }
                        
                        if (!score.is_absent && !score.old_is_absent) {
                            if (window.currentSubject?.isSplit) {
                                if (score.base_score !== score.old_base_score) {
                                    changesHtml += `<tr>
                                        <td>${score.student_name}</td>
                                        <td>${window.currentSubject.splitName1 || '基础分'}</td>
                                        <td>${formatValue(score.old_base_score, false)}</td>
                                        <td>${formatValue(score.base_score, false)}</td>
                                    </tr>`;
                                }
                                
                                if (score.extra_score !== score.old_extra_score) {
                                    changesHtml += `<tr>
                                        <td>${score.student_name}</td>
                                        <td>${window.currentSubject.splitName2 || '附加分'}</td>
                                        <td>${formatValue(score.old_extra_score, false)}</td>
                                        <td>${formatValue(score.extra_score, false)}</td>
                                    </tr>`;
                                }
                            }
                            
                            if (score.total_score !== score.old_total_score) {
                                changesHtml += `<tr>
                                    <td>${score.student_name}</td>
                                    <td>总分</td>
                                    <td>${formatValue(score.old_total_score, false)}</td>
                                    <td>${formatValue(score.total_score, false)}</td>
                                </tr>`;
                            }
                        }
                    });
                    
                    changesHtml += '</tbody></table></div>';
                    
                    // 显示修改申请确认对话框
                    Swal.fire({
                        title: '提交成绩修改申请',
                        html: `
                            <div class="mb-3">
                                <label class="form-label">修改内容：</label>
                                ${changesHtml}
                            </div>
                            <div class="mb-3">
                                <label for="edit-reason" class="form-label">请输入修改原因：</label>
                                <textarea id="edit-reason" class="form-control" rows="3" placeholder="请详细说明修改原因..."></textarea>
                            </div>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>修改申请将提交给管理员/教导处审核，审核通过后才会更新成绩。
                            </div>
                        `,
                        showCancelButton: true,
                        confirmButtonText: '提交申请',
                        cancelButtonText: '取消',
                        showLoaderOnConfirm: true,
                        preConfirm: () => {
                            const reason = document.getElementById('edit-reason').value;
                            if (!reason.trim()) {
                                Swal.showValidationMessage('请输入修改原因');
                                return false;
                            }
                            
                            // 提交修改申请
                            return $.ajax({
                                url: '../api/index.php?route=score_edit/submit',
                                method: 'POST',
                                data: {
                                    grade_id: $('#gradeFilter').val(),
                                    class_id: $('#classFilter').val(),
                                    subject_id: $('#subjectFilter').val(),
                                    reason: reason,
                                    edited_scores: JSON.stringify(editedScores)
                                }
                            });
                        },
                        allowOutsideClick: () => !Swal.isLoading()
                    }).then((result) => {
                        if (result.isConfirmed && result.value.success) {
                            Swal.fire({
                                icon: 'success',
                                title: '申请已提交',
                                text: '成绩修改申请已提交，等待审核。',
                                confirmButtonText: '确定'
                            }).then(() => {
                                // 清除编辑状态
                    window.isEditing = false;
                                sessionStorage.removeItem('isEditing');
                                sessionStorage.removeItem('editClass');
                                sessionStorage.removeItem('editSubject');
                                
                                // 清除修改记录
                                window.originalScores = {};
                                window.modifiedScores = {};
                                
                                // 移除URL中的edit_mode参数
                                const url = new URL(window.location.href);
                                url.searchParams.delete('edit_mode');
                                window.history.replaceState({}, '', url);
                    
                    // 显示/隐藏按钮
                                $('#submitEditRequestBtn').hide();
                                $('#cancelEditBtn').hide(); // 确保隐藏放弃按钮
                    $('#editBtn').show();
                    $('#sortByNameBtn').hide().removeClass('active');
                    $('#sortByNumberBtn').hide().removeClass('active');
                    $('#editTips').hide();
                    $('#editActionButtons').hide(); // 隐藏编辑操作按钮容器
                    
                    // 启用筛选下拉框
                    $('.custom-select-wrapper').removeClass('disabled').css('pointer-events', 'auto');
                    $('.custom-select-trigger').css({
                        'opacity': '1',
                        'background': 'linear-gradient(to bottom, #ffffff, #f8f9fa)',
                        'cursor': 'pointer'
                    });
                    
                                // 重新加载成绩列表
                    loadScores();
                            });
                        } else if (result.value && !result.value.success) {
                            Swal.fire({
                                icon: 'error',
                                title: '提交失败',
                                text: result.value.error || '提交修改申请失败',
                                confirmButtonText: '确定'
                            });
                        }
                    });
                });

                // 按姓名排序按钮点击事件
                $('#sortByNameBtn').click(function() {
                    // 切换激活状态
                    $(this).addClass('active');
                    $('#sortByNumberBtn').removeClass('active');
                    
                    const students = [];
                    $('.score-row').each(function() {
                        const $row = $(this);
                        students.push({
                            id: $row.data('id'),
                            student_number: $row.find('td:eq(0)').text(),
                            student_name: $row.find('td:eq(1)').text(),
                            base_score: $row.find('.base-score').val(),
                            extra_score: $row.find('.extra-score').val(),
                            total_score: $row.find('.total-score').text(),
                            score_level: $row.find('.score-level').attr('class'),
                            score_level_text: $row.find('.score-level').text(),
                            is_absent: $row.find('.absent-checkbox').prop('checked') ? 1 : 0
                        });
                    });

                    // 按姓名排序
                    students.sort((a, b) => {
                        const nameA = a.student_name;
                        const nameB = b.student_name;
                        return nameA.localeCompare(nameB, 'zh-CN');
                    });

                    // 重新生成表格
                    refreshTableWithSortedData(students);
                });

                // 按号数排序按钮点击事件
                $('#sortByNumberBtn').click(function() {
                    // 切换激活状态
                    $(this).addClass('active');
                    $('#sortByNameBtn').removeClass('active');
                    
                    const students = [];
                    $('.score-row').each(function() {
                        const $row = $(this);
                        students.push({
                            id: $row.data('id'),
                            student_number: $row.find('td:eq(0)').text(),
                            student_name: $row.find('td:eq(1)').text(),
                            base_score: $row.find('.base-score').val(),
                            extra_score: $row.find('.extra-score').val(),
                            total_score: $row.find('.total-score').text(),
                            score_level: $row.find('.score-level').attr('class'),
                            score_level_text: $row.find('.score-level').text(),
                            is_absent: $row.find('.absent-checkbox').prop('checked') ? 1 : 0
                        });
                    });

                    // 按号数排序
                    students.sort((a, b) => {
                        const numA = parseInt(a.student_number) || 0;
                        const numB = parseInt(b.student_number) || 0;
                        return numA - numB;
                    });

                    // 重新生成表格
                    refreshTableWithSortedData(students);
                });

                // 刷新表格数据的函数
                function refreshTableWithSortedData(students) {
                    // 将学生数据分成多个表格，每个表格固定20行
                    const columns = [[], [], []];
                    const rowsPerTable = 20;
                    
                    // 计算需要多少列来显示所有学生
                    const totalTables = Math.ceil(students.length / rowsPerTable);
                    const columnsNeeded = Math.min(totalTables, 3); // 最多3列
                    
                    // 将学生数据平均分配到每列
                    for (let i = 0; i < students.length; i++) {
                        const columnIndex = Math.floor(i / rowsPerTable);
                        if (columnIndex < 3) { // 最多3列
                            columns[columnIndex].push(students[i]);
                        }
                    }

                    let html = '<div class="row">';
                    
                    // 生成表格
                    columns.forEach((columnData, index) => {
                        if (columnData && columnData.length > 0) {
                            html += '<div class="col-4">';
                            html += generateTableWithScores(columnData);
                            html += '</div>';
                        }
                    });

                    html += '</div>';
                    $('#scoreList').html(html);

                    // 恢复编辑状态
                    if (window.isEditing) {
                        $('.score-input').prop('readonly', false);
                    }
                }
                
                // 生成包含成绩和等级的表格
                function generateTableWithScores(students) {
                    if (!students || students.length === 0) {
                        return '<div class="alert alert-warning">该班级暂无学生，请先添加学生。</div>';
                    }
                    
                    let tableHtml = '<table class="table table-bordered table-hover align-middle">';
                    tableHtml += '<thead><tr>';
                    tableHtml += '<th style="width: 55px;">编号</th>';
                    tableHtml += '<th style="width: 60px;">姓名</th>';
                    
                    // 根据是否拆分显示不同的列
                    if (window.currentSubject?.isSplit) {
                        if (window.isEditing) {
                            tableHtml += `<th style="width: 80px;">${window.currentSubject.splitName1 || '基础分'}</th>`;
                            tableHtml += `<th style="width: 80px;">${window.currentSubject.splitName2 || '操作分'}</th>`;
                            tableHtml += '<th style="width: 50px;">总分</th>';
                        } else {
                            tableHtml += `<th style="width: 80px;">${window.currentSubject.name}成绩</th>`;
                        }
                    } else {
                        tableHtml += `<th style="width: ${window.isEditing ? '80px' : '80px'}">${window.currentSubject.name}成绩</th>`;
                    }
                    
                    tableHtml += '<th style="width: 45px;">等级</th>';
                    tableHtml += '</tr></thead><tbody>';
                    
                    // 生成学生行
                    students.forEach(student => {
                        const isAbsent = student.is_absent === '1' || student.is_absent === 1 || student.is_absent === true;
                        
                        tableHtml += `<tr class="score-row" data-id="${student.id}">`;
                        tableHtml += `<td>${student.student_number}</td>`;
                        tableHtml += `<td>${student.student_name}</td>`;
                        
                        if (window.currentSubject?.isSplit && window.isEditing) {
                            // 拆分成绩，编辑模式
                            tableHtml += '<td>' + generateScoreInput(student, 'base', '基础分', true, true) + '</td>';
                            tableHtml += '<td>' + generateScoreInput(student, 'extra', '操作分', true, false) + '</td>';
                            tableHtml += `<td class="total-score">${isAbsent ? '缺考' : student.total_score || ''}</td>`;
                        } else if (window.currentSubject?.isSplit && !window.isEditing) {
                            // 拆分成绩，查看模式
                            tableHtml += `<td>${isAbsent ? '缺考' : student.total_score || ''}</td>`;
                        } else {
                            // 非拆分成绩
                            if (window.isEditing) {
                                tableHtml += '<td>' + generateScoreInput(student, 'base', '成绩', true, true) + '</td>';
                            } else {
                                tableHtml += `<td>${isAbsent ? '缺考' : student.base_score || ''}</td>`;
                            }
                        }
                        
                        // 等级显示
                        tableHtml += `<td><span class="${student.score_level || ''}">${student.score_level_text || ''}</span></td>`;
                        tableHtml += '</tr>';
                    });
                    
                    tableHtml += '</tbody></table>';
                    return tableHtml;
                }

                // 修改成绩输入事件处理
                $(document).on('input', '.score-input', function() {
                    const value = $(this).val();
                    const type = $(this).data('type');
                    const $input = $(this);
                    
                    if (value && !validateScore(value, type)) {
                        $input.addClass('error');
                    } else {
                        $input.removeClass('error');
                    }
                });

                // 获取学科等级设置
                function getSubjectLevels(subjectId) {
                    return new Promise((resolve) => {
                        $.get('../api/index.php?route=subject/get_levels', { subject_id: subjectId })
                            .done(function(response) {
                                if (response.success) {
                                    resolve(response.data);
                                } else {
                                    resolve(null);
                                }
                            })
                            .fail(function() {
                                resolve(null);
                            });
                    });
                }

                // 计算等级
                function calculateLevel(score, levels) {
                    if (!levels) return '';
                    if (score >= levels.excellent) return '优秀';
                    if (score >= levels.good) return '良好';
                    if (score >= levels.pass) return '及格';
                    return '不及格';
                }

                // 自动保存成绩
                function autoSaveScore(studentId, subjectId, classId, gradeId, isSplit, baseScore, extraScore, isAbsent) {
                    // 检查是否已有统计分析数据，如果有则不自动保存，而是仅记录修改
                    if (window.hasAnalyticsData) {
                        console.log('检测到已有统计分析数据，不自动保存，仅记录修改');
                        
                        // 更新UI显示
                        const $row = $(`.score-input[data-student-id="${studentId}"]`).closest('tr');
                        const $inputs = $row.find('.score-input');
                        
                        // 计算新的总分
                        let totalScore = null;
                        if (!isAbsent) {
                            if (isSplit) {
                                if (baseScore || extraScore) {
                                    totalScore = parseFloat(baseScore || 0) + parseFloat(extraScore || 0);
                                }
                            } else {
                                totalScore = baseScore || '';
                            }
                        }
                        
                        // 更新等级显示
                        const [levelClass, levelText] = getScoreLevel(totalScore, isAbsent);
                        $row.find('.score-level')
                            .attr('class', `score-level ${levelClass}`)
                            .text(levelText);
                        
                        // 如果是拆分成绩，更新总分显示
                        if (window.currentSubject?.isSplit) {
                            $row.find('.total-score').text(formatScore(totalScore, isAbsent));
                        }

                        // 更新缺考状态
                        if (isAbsent) {
                            $row.find('.score-input').addClass('absent');
                        } else {
                            $row.find('.score-input').removeClass('absent');
                        }
                        
                        return Promise.resolve({ success: true });
                    }
                    
                    // 如果没有统计分析数据，则正常保存
                    const formData = new FormData();
                    formData.append('student_id', studentId);
                    formData.append('subject_id', subjectId);
                    formData.append('class_id', classId);
                    formData.append('grade_id', gradeId);
                    formData.append('is_absent', isAbsent ? 1 : 0);

                    // 获取学生姓名和学科名称，用于日志记录
                    const $row = $(`.score-input[data-student-id="${studentId}"]`).closest('tr');
                    const studentName = $row.find('td:eq(1)').text();
                    const subjectName = $('#subjectFilter option:selected').text();
                    const className = $('#classFilter option:selected').text();
                    const gradeName = $('#gradeFilter option:selected').text();

                    // 计算新的总分
                    let totalScore = null;
                    if (!isAbsent) {
                        if (isSplit) {
                            formData.append('base_score', baseScore || '');
                            formData.append('extra_score', extraScore || '');
                            if (baseScore || extraScore) {
                                totalScore = parseFloat(baseScore || 0) + parseFloat(extraScore || 0);
                                formData.append('total_score', totalScore);
                            }
                        } else {
                            formData.append('total_score', baseScore || '');
                            totalScore = baseScore || '';
                        }
                    }

                    // 添加日志所需信息
                    formData.append('student_name', studentName);
                    formData.append('subject_name', subjectName);
                    formData.append('class_name', className);
                    formData.append('grade_name', gradeName);

                    return $.ajax({
                        url: '../api/index.php?route=score/save_score',
                        type: 'POST',
                        data: formData,
                        processData: false,
                        contentType: false,
                        success: function(response) {
                            if (response.success) {
                                // 更新UI显示
                                const $row = $(`.score-input[data-student-id="${studentId}"]`).closest('tr');
                                const $inputs = $row.find('.score-input');
                                
                                // 为每个输入框添加成功效果
                                $inputs.each(function() {
                                    const $input = $(this);
                                    $input.addClass('success');
                                    setTimeout(() => {
                                        $input.removeClass('success');
                                    }, 500);
                                });
                                
                                // 更新等级显示
                                const [levelClass, levelText] = getScoreLevel(totalScore, isAbsent);
                                $row.find('.score-level')
                                    .attr('class', `score-level ${levelClass}`)
                                    .text(levelText);
                                
                                // 如果是拆分成绩，更新总分显示
                                if (window.currentSubject?.isSplit) {
                                    $row.find('.total-score').text(formatScore(totalScore, isAbsent));
                                }

                                // 更新缺考状态
                                if (isAbsent) {
                                    $row.find('.score-input').addClass('absent');
                                } else {
                                    $row.find('.score-input').removeClass('absent');
                                }
                            } else {
                                console.error('保存失败:', response.error);
                                throw new Error(response.error || '保存失败');
                            }
                        },
                        error: function(xhr) {
                            console.error('保存失败:', xhr.responseText);
                            throw new Error(xhr.responseJSON?.error || '保存失败');
                        }
                    });
                }

                // 初始化成绩输入框事件
                function initScoreInputs() {
                    // 使用事件委托处理成绩输入框的blur事件
                    $(document).on('blur', '.score-input', function() {
                        const $input = $(this);
                        const $row = $input.closest('tr');
                        const studentId = $row.data('id');
                        const subjectId = $('#subjectFilter').val();
                        const classId = $('#classFilter').val();
                        const gradeId = $('#gradeFilter').val();
                        const isSplit = window.currentSubject?.isSplit;
                        const isAbsent = $row.find('.absent-checkbox').prop('checked');
                        const inputType = $input.data('type');
                        
                        if (!studentId || !subjectId || !classId || !gradeId) {
                            console.error('缺少必要的参数:', {
                                studentId,
                                subjectId,
                                classId,
                                gradeId,
                                isSplit
                            });
                            return;
                        }

                        // 如果是缺考，不处理成绩输入
                        if (isAbsent) {
                            return;
                        }

                        // 获取基础分和附加分
                        let baseScore = null;
                        let extraScore = null;
                        let totalScore = null;
                        
                        if (isSplit) {
                            // 如果是拆分成绩，需要检查两个输入框
                            const $baseInput = $row.find('.base-score');
                            const $extraInput = $row.find('.extra-score');
                            const $totalDisplay = $row.find('.total-score');
                            const $levelDisplay = $row.find('.score-level');
                            
                            baseScore = $baseInput.val().trim();
                            extraScore = $extraInput.val().trim();

                            // 清空总分和等级显示
                            $totalDisplay.text('');
                            $levelDisplay.attr('class', 'score-level').text('');

                            // 只有当两个输入框都有有效值时才计算总分和触发保存
                            if (baseScore !== '' && extraScore !== '') {
                                const baseVal = parseFloat(baseScore);
                                const extraVal = parseFloat(extraScore);
                                
                                if (!isNaN(baseVal) && !isNaN(extraVal)) {
                                    totalScore = baseVal + extraVal;
                                    $totalDisplay.text(totalScore.toFixed(1));
                                    
                                    // 检查是否有修改
                                    if (window.originalScores && window.originalScores[studentId]) {
                                        const original = window.originalScores[studentId];
                                        const originalBase = original.base_score !== null ? parseFloat(original.base_score) : null;
                                        const originalExtra = original.extra_score !== null ? parseFloat(original.extra_score) : null;
                                        
                                        // 检查是否有变化
                                        if (baseVal !== originalBase) {
                                            $baseInput.addClass('modified');
                                            // 记录修改
                                            if (!window.modifiedScores[studentId]) {
                                                window.modifiedScores[studentId] = {};
                                            }
                                            window.modifiedScores[studentId].base_score = baseVal;
                                        } else {
                                            $baseInput.removeClass('modified');
                                            if (window.modifiedScores[studentId]) {
                                                delete window.modifiedScores[studentId].base_score;
                                            }
                                        }
                                        
                                        if (extraVal !== originalExtra) {
                                            $extraInput.addClass('modified');
                                            // 记录修改
                                            if (!window.modifiedScores[studentId]) {
                                                window.modifiedScores[studentId] = {};
                                            }
                                            window.modifiedScores[studentId].extra_score = extraVal;
                                        } else {
                                            $extraInput.removeClass('modified');
                                            if (window.modifiedScores[studentId]) {
                                                delete window.modifiedScores[studentId].extra_score;
                                            }
                                        }
                                        
                                        // 如果没有修改，删除该学生的记录
                                        if (window.modifiedScores[studentId] && 
                                            Object.keys(window.modifiedScores[studentId]).length === 0) {
                                            delete window.modifiedScores[studentId];
                                        }
                                    }
                                    
                                    // 触发自动保存
                                    autoSaveScore(studentId, subjectId, classId, gradeId, isSplit, baseScore, extraScore, isAbsent);
                                }
                            }
                        } else {
                            // 非拆分成绩直接使用输入值
                            const score = $input.val().trim();
                            if (score !== '') {
                                totalScore = parseFloat(score);
                                if (!isNaN(totalScore)) {
                                    // 检查是否有修改
                                    if (window.originalScores && window.originalScores[studentId]) {
                                        const original = window.originalScores[studentId];
                                        const originalTotal = original.total_score !== null ? parseFloat(original.total_score) : null;
                                        
                                        // 检查是否有变化
                                        if (totalScore !== originalTotal) {
                                            $input.addClass('modified');
                                            // 记录修改
                                            if (!window.modifiedScores[studentId]) {
                                                window.modifiedScores[studentId] = {};
                                            }
                                            window.modifiedScores[studentId].total_score = totalScore;
                                        } else {
                                            $input.removeClass('modified');
                                            if (window.modifiedScores[studentId]) {
                                                delete window.modifiedScores[studentId].total_score;
                                                if (Object.keys(window.modifiedScores[studentId]).length === 0) {
                                                    delete window.modifiedScores[studentId];
                                                }
                                            }
                                        }
                                    }
                                    
                                    autoSaveScore(studentId, subjectId, classId, gradeId, false, totalScore, null, isAbsent);
                                }
                            }
                        }
                    });

                    // 处理缺考复选框的change事件
                    $(document).on('change', '.absent-checkbox', function() {
                        const $checkbox = $(this);
                        const $row = $checkbox.closest('tr');
                        const studentId = $row.data('id');
                        const subjectId = $('#subjectFilter').val();
                        const classId = $('#classFilter').val();
                        const gradeId = $('#gradeFilter').val();
                        const isSplit = window.currentSubject?.isSplit;
                        const isAbsent = $checkbox.prop('checked');

                        if (!studentId || !subjectId || !classId || !gradeId) {
                            console.error('缺少必要的参数:', {
                                studentId,
                                subjectId,
                                classId,
                                gradeId,
                                isSplit
                            });
                            return;
                        }

                        // 禁用或启用成绩输入框
                        if (isSplit) {
                            const $baseInput = $row.find('.base-score');
                            const $extraInput = $row.find('.extra-score');
                            const $totalDisplay = $row.find('.total-score');
                            const $levelDisplay = $row.find('.score-level');

                            // 更新输入框状态和值
                            $baseInput.prop('disabled', isAbsent);
                            $extraInput.prop('disabled', isAbsent);

                            if (isAbsent) {
                                // 清空所有成绩相关字段
                                $baseInput.val('');
                                $extraInput.val('');
                                $totalDisplay.text('缺考');
                                $levelDisplay.attr('class', 'score-level level-absent').text('缺考');
                                
                                // 检查是否有修改
                                if (window.originalScores && window.originalScores[studentId]) {
                                    const original = window.originalScores[studentId];
                                    
                                    // 检查是否有变化
                                    if (original.is_absent !== isAbsent) {
                                        $row.addClass('modified');
                                        // 记录修改
                                        if (!window.modifiedScores[studentId]) {
                                            window.modifiedScores[studentId] = {};
                                        }
                                        window.modifiedScores[studentId].is_absent = isAbsent;
                                    } else {
                                        $row.removeClass('modified');
                                        if (window.modifiedScores[studentId]) {
                                            delete window.modifiedScores[studentId].is_absent;
                                            if (Object.keys(window.modifiedScores[studentId]).length === 0) {
                                                delete window.modifiedScores[studentId];
                                            }
                                        }
                                    }
                                }
                                
                                // 保存缺考状态
                                autoSaveScore(studentId, subjectId, classId, gradeId, isSplit, null, null, true);
                            } else {
                                $totalDisplay.text('');
                                $levelDisplay.attr('class', 'score-level').text('');
                                
                                // 检查是否有修改
                                if (window.originalScores && window.originalScores[studentId]) {
                                    const original = window.originalScores[studentId];
                                    
                                    // 检查是否有变化
                                    if (original.is_absent !== isAbsent) {
                                        $row.addClass('modified');
                                        // 记录修改
                                        if (!window.modifiedScores[studentId]) {
                                            window.modifiedScores[studentId] = {};
                                        }
                                        window.modifiedScores[studentId].is_absent = isAbsent;
                                    } else {
                                        $row.removeClass('modified');
                                        if (window.modifiedScores[studentId]) {
                                            delete window.modifiedScores[studentId].is_absent;
                                            if (Object.keys(window.modifiedScores[studentId]).length === 0) {
                                                delete window.modifiedScores[studentId];
                                            }
                                        }
                                    }
                                }
                                
                                // 保存取消缺考状态
                                autoSaveScore(studentId, subjectId, classId, gradeId, isSplit, null, null, false);
                            }
                        } else {
                            const $scoreInput = $row.find('.score-input');
                            const $levelDisplay = $row.find('.score-level');

                            // 更新输入框状态和值
                            $scoreInput.prop('disabled', isAbsent);
                            
                            if (isAbsent) {
                                // 清空成绩并标记缺考
                                $scoreInput.val('');
                                $levelDisplay.attr('class', 'score-level level-absent').text('缺考');
                                
                                // 检查是否有修改
                                if (window.originalScores && window.originalScores[studentId]) {
                                    const original = window.originalScores[studentId];
                                    
                                    // 检查是否有变化
                                    if (original.is_absent !== isAbsent) {
                                        $row.addClass('modified');
                                        // 记录修改
                                        if (!window.modifiedScores[studentId]) {
                                            window.modifiedScores[studentId] = {};
                                        }
                                        window.modifiedScores[studentId].is_absent = isAbsent;
                                    } else {
                                        $row.removeClass('modified');
                                        if (window.modifiedScores[studentId]) {
                                            delete window.modifiedScores[studentId].is_absent;
                                            if (Object.keys(window.modifiedScores[studentId]).length === 0) {
                                                delete window.modifiedScores[studentId];
                                            }
                                        }
                                    }
                                }
                                
                                // 保存缺考状态
                                autoSaveScore(studentId, subjectId, classId, gradeId, false, null, null, true);
                            } else {
                                $levelDisplay.attr('class', 'score-level').text('');
                                
                                // 检查是否有修改
                                if (window.originalScores && window.originalScores[studentId]) {
                                    const original = window.originalScores[studentId];
                                    
                                    // 检查是否有变化
                                    if (original.is_absent !== isAbsent) {
                                        $row.addClass('modified');
                                        // 记录修改
                                        if (!window.modifiedScores[studentId]) {
                                            window.modifiedScores[studentId] = {};
                                        }
                                        window.modifiedScores[studentId].is_absent = isAbsent;
                                    } else {
                                        $row.removeClass('modified');
                                        if (window.modifiedScores[studentId]) {
                                            delete window.modifiedScores[studentId].is_absent;
                                            if (Object.keys(window.modifiedScores[studentId]).length === 0) {
                                                delete window.modifiedScores[studentId];
                                            }
                                        }
                                    }
                                }
                                
                                // 保存取消缺考状态
                                autoSaveScore(studentId, subjectId, classId, gradeId, false, null, null, false);
                            }
                        }
                    });
                }

                // 页面加载完成后初始化
                $(document).ready(function() {
                    initScoreInputs();
                });
        });
    </script>
</body>
</html> 