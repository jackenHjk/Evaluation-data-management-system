<!--
/**
 * 文件名: modules/download.php
 * 功能描述: 数据下载模块
 *
 * 该文件负责:
 * 1. 提供成绩数据导出下载的用户界面
 * 2. 支持单科目成绩下载功能
 * 3. 支持多科目成绩合并下载功能
 * 4. 支持语文数学专项成绩对比下载
 * 5. 提供不同下载格式和排序方式的选项
 *
 * 界面包含三种下载模式，每种模式有独立的配置选项:
 * - 单科目下载: 导出单一科目的成绩数据表格
 * - 多科目下载: 同时导出多个科目的成绩数据到一个Excel文件
 * - 语数下载: 专门用于导出语文和数学成绩的对比数据
 *
 * 关联文件:
 * - controllers/DownloadController.php: 下载控制器
 * - api/download/: 下载API接口目录
 * - api/download/single_subject.php: 单科目下载API
 * - api/download/multi_subject.php: 多科目下载API
 * - api/download/chinese_math.php: 语数下载API
 * - assets/js/download.js: 下载功能前端脚本
 */
-->
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>数据下载</title>
    <link href="../assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/common.css" rel="stylesheet">
    <!-- 修改 SweetAlert2 引入路径 -->
    <link href="../assets/css/sweetalert2.min.css" rel="stylesheet">
    <script src="../assets/js/sweetalert2.all.min.js"></script>
</head>
<body>
<?php
// 检查用户是否登录
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}
?>

<div class="container-fluid mt-3">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="fas fa-download text-primary me-2"></i>数据下载
                    </h5>
        </div>
                <div class="card-body">
    <div class="row g-4">
        <!-- 单学科下载 -->
        <div class="col-md-4">
                            <div class="feature-card h-100" onclick="openModal('singleSubjectModal')">
                <div class="card-body d-flex flex-column">
                                    <h4 class="card-title mb-4">
                        <i class="fas fa-file-download me-2"></i>单学科下载
                                    </h4>
                    <p class="card-text flex-grow-1">下载单个学科的成绩数据，支持分数和等级数据下载，可选择排序方式。</p>
                </div>
            </div>
        </div>

        <!-- 多学科下载 -->
        <div class="col-md-4">
                            <div class="feature-card h-100 disabled">
                <div class="card-body d-flex flex-column">
                                    <h4 class="card-title mb-4">
                        <i class="fas fa-copy me-2"></i>多学科下载
                                    </h4>
                    <p class="card-text flex-grow-1">同时下载多个学科的成绩数据，支持分数或等级数据下载，可选择排序方式。</p>
                </div>
            </div>
        </div>

        <!-- 语数下载 -->
        <div class="col-md-4">
                            <div class="feature-card h-100" onclick="openModal('chineseMathModal')">
                <div class="card-body d-flex flex-column">
                                    <h4 class="card-title mb-4">
                        <i class="fas fa-book me-2"></i>语数下载
                                    </h4>
                    <p class="card-text flex-grow-1">专门用于下载语文和数学的成绩数据，支持分数或等级数据下载，可选择排序方式。</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- 单学科下载模态框 -->
<div class="modal fade" id="singleSubjectModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">单学科下载</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="singleSubjectForm">
                    <div class="mb-3">
                        <label class="form-label">选择项目</label>
                        <select class="form-select" name="setting_id" required>
                            <option value="">请选择项目</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">选择年级</label>
                        <select class="form-select" name="grade_id" required>
                            <option value="">请选择年级</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">选择学科</label>
                        <select class="form-select" name="subject_id" required>
                            <option value="">请选择学科</option>
                        </select>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">下载内容</label>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="includeScore" name="include_score" checked>
                            <label class="form-check-label">包含分数</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="includeLevel" name="include_level" checked>
                            <label class="form-check-label">包含等级</label>
                        </div>
                    </div>
                        </div>
                        <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">排序方式</label>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="sort_by" value="number" checked>
                            <label class="form-check-label">按编号排序</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="sort_by" value="score">
                            <label class="form-check-label">按成绩排序</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
                <button type="button" class="btn btn-primary" id="downloadSingleSubject">
                    <i class="fas fa-download me-2"></i>下载
                </button>
            </div>
        </div>
    </div>
</div>

<!-- 多学科下载模态框 -->
<div class="modal fade" id="multiSubjectModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">多学科下载</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="multiSubjectForm">
                    <div class="mb-3">
                        <label class="form-label">选择项目</label>
                        <select class="form-select" name="setting_id" required>
                            <option value="">请选择项目</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">选择年级</label>
                        <select class="form-select" name="grade_id" required>
                            <option value="">请选择年级</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">选择学科</label>
                        <div id="subjectCheckboxes" class="border rounded p-3">
                            <!-- 学科复选框将通过JavaScript动态添加 -->
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">下载内容</label>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="downloadType" value="score" checked>
                            <label class="form-check-label">下载分数</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="downloadType" value="level">
                            <label class="form-check-label">下载等级</label>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">排序方式</label>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="sortBy" value="number" checked>
                            <label class="form-check-label">按编号排序</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="sortBy" value="score">
                            <label class="form-check-label">按成绩排序</label>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
                <button type="button" class="btn btn-primary" id="downloadMultiSubject">
                    <i class="fas fa-download me-2"></i>下载
                </button>
            </div>
        </div>
    </div>
</div>

<!-- 语数下载模态框 -->
<div class="modal fade" id="chineseMathModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">语数下载</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="chineseMathForm">
                    <div class="mb-3">
                        <label class="form-label">选择项目</label>
                        <select class="form-select" name="setting_id" required>
                            <option value="">请选择项目</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">选择年级</label>
                        <select class="form-select" name="grade_id" required>
                            <option value="">请选择年级</option>
                        </select>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">下载内容</label>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="download_type" value="score" checked>
                            <label class="form-check-label">下载分数</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="download_type" value="level">
                            <label class="form-check-label">下载等级</label>
                        </div>
                    </div>
                        </div>
                        <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">排序方式</label>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="sort_by" value="number" checked>
                            <label class="form-check-label">按编号排序</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="sort_by" value="score">
                            <label class="form-check-label">按成绩排序</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
                <button type="button" class="btn btn-primary" id="downloadChineseMath">
                    <i class="fas fa-download me-2"></i>下载
                </button>
            </div>
        </div>
    </div>
</div>

<!-- 添加成功提示模态框 -->
<div class="modal fade" id="successModal" tabindex="-1" style="z-index: 1070;">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-body text-center p-4">
                <i class="fas fa-check-circle text-success mb-3" style="font-size: 3rem;"></i>
                <p class="mb-0">下载成功，文件已开始下载</p>
            </div>
        </div>
    </div>
</div>

<!-- 修改错误提示模态框的HTML结构 -->
<div class="modal fade custom-modal" id="errorModal" tabindex="-1" style="z-index: 1070;">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-body text-center p-4">
                <div class="error-icon-wrapper mb-4">
                    <i class="fas fa-exclamation-circle"></i>
                </div>
                <p class="error-message mb-4"></p>
                <button type="button" class="btn btn-danger px-4 py-2" data-bs-dismiss="modal">确定</button>
            </div>
        </div>
    </div>
</div>

<style>
/* 卡片基础样式和动画 */
.feature-card {
    transition: all 0.3s ease;
    cursor: pointer;
    border: none;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    border-radius: 16px;
    min-height: 220px;
    padding: 1.5rem;
}

/* 单学科下载卡片样式 */
.col-md-4:nth-child(1) .feature-card {
    background: linear-gradient(145deg, #e8f4ff 0%, #f0f9ff 100%);
}

.col-md-4:nth-child(1) .feature-card:not(.disabled):hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 16px rgba(13, 110, 253, 0.15);
    background: linear-gradient(145deg, #dbeeff 0%, #e8f4ff 100%);
}

/* 多学科下载卡片样式 */
.col-md-4:nth-child(2) .feature-card {
    background: linear-gradient(145deg, #fff5f7 0%, #fff0f3 100%);
}

.col-md-4:nth-child(2) .feature-card:not(.disabled):hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 16px rgba(225, 83, 97, 0.15);
    background: linear-gradient(145deg, #ffe4e9 0%, #fff5f7 100%);
}

/* 语数下载卡片样式 */
.col-md-4:nth-child(3) .feature-card {
    background: linear-gradient(145deg, #f0fdf4 0%, #ecfdf5 100%);
}

.col-md-4:nth-child(3) .feature-card:not(.disabled):hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 16px rgba(16, 185, 129, 0.15);
    background: linear-gradient(145deg, #e1fbe7 0%, #f0fdf4 100%);
}

.feature-card .card-title {
    font-size: 1.5rem;
    font-weight: 600;
    color: #2c3e50;
}

.feature-card .card-text {
    font-size: 1.1rem;
    color: #6c757d;
    line-height: 1.6;
}

/* 禁用状态的卡片样式 */
.feature-card.disabled {
    opacity: 0.7;
    cursor: not-allowed;
    background: linear-gradient(145deg, #f8f9fa 0%, #e9ecef 100%) !important;
    position: relative;
    overflow: hidden;
}

.feature-card.disabled::before {
    content: "开发中...";
    position: absolute;
    top: 10px;
    right: -35px;
    background: #6c757d;
    color: white;
    padding: 5px 40px;
    transform: rotate(45deg);
    font-size: 12px;
    z-index: 1;
}

/* 主容器卡片样式 */
.container-fluid .card {
    border: none;
    border-radius: 16px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    background: #ffffff;
}

.container-fluid .card-header {
    background: transparent;
    border-bottom: 1px solid rgba(0,0,0,0.05);
    padding: 1.5rem;
}

.container-fluid .card-header h5 {
    font-size: 1.5rem;
    font-weight: 600;
    color: #2c3e50;
    margin: 0;
}

.container-fluid .card-body {
    padding: 1.5rem;
}

/* 模态框样式优化 */
.modal-dialog {
    max-width: 480px;
}

.modal-content {
    border: none;
    border-radius: 16px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.15);
    background: linear-gradient(145deg, #ffffff 0%, #f8f9fa 100%);
}

.modal-header {
    background: rgba(255,255,255,0.9);
    border-bottom: 1px solid rgba(0,0,0,0.05);
    border-radius: 16px 16px 0 0;
    padding: 1.5rem;
    position: relative;
}

.modal-header::after {
    content: '';
    position: absolute;
    bottom: -1px;
    left: 5%;
    right: 5%;
    height: 1px;
    background: linear-gradient(90deg, transparent, rgba(0,0,0,0.1), transparent);
}

.modal-title {
    font-size: 1.3rem;
    font-weight: 600;
    color: #2c3e50;
}

.modal-body {
    background: rgba(255,255,255,0.9);
    padding: 1.25rem;
}

.modal-footer {
    background: rgba(248,249,250,0.9);
    border-top: 1px solid rgba(0,0,0,0.05);
    border-radius: 0 0 16px 16px;
    padding: 1rem 1.25rem;
    position: relative;
}

.modal-footer::before {
    content: '';
    position: absolute;
    top: -1px;
    left: 5%;
    right: 5%;
    height: 1px;
    background: linear-gradient(90deg, transparent, rgba(0,0,0,0.1), transparent);
}

/* 表单标签样式 */
.form-label {
    font-weight: 500;
    color: #2c3e50;
    margin-bottom: 0.5rem;
    font-size: 1.1rem;
}

/* 表单组样式优化 */
.mb-3 {
    margin-bottom: 0.75rem !important;
}

/* 自定义下拉框样式 */
.custom-select-wrapper {
    position: relative;
    width: 100%;
    margin-bottom: 0.75rem;
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
    background: linear-gradient(to right, #f0f7ff, #ffffff);
    padding-left: 1.25rem;
}

.custom-option.selected {
    background: linear-gradient(to right, #e7f1ff, #ffffff);
    color: #0d6efd;
    font-weight: 500;
    padding-left: 1.25rem;
}

/* 可点击选项样式 */
.clickable-option {
    background: linear-gradient(to bottom, #ffffff, #f8f9fa);
    border: 1px solid rgba(0,0,0,0.1);
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    padding: 0.5rem 1rem 0.5rem 3rem;
    border-radius: 10px;
    margin-bottom: 0.5rem;
    cursor: pointer;
    position: relative;
    min-height: 42px;
    display: flex;
    align-items: center;
}

.clickable-option:hover {
    background: linear-gradient(to right, #f0f7ff, #ffffff);
    border-color: #86b7fe;
    box-shadow: 0 2px 8px rgba(13, 110, 253, 0.1);
    transform: translateX(3px);
}

.clickable-option.active {
    background: linear-gradient(to right, #e7f1ff, #ffffff);
    border-color: #0d6efd;
    box-shadow: 0 2px 8px rgba(13, 110, 253, 0.15);
}

/* 成功提示模态框样式 */
#successModal .modal-content {
    border-radius: 20px;
    background: linear-gradient(145deg, #ebfff3 0%, #d1ffe3 100%);
    box-shadow: 0 10px 30px rgba(25, 135, 84, 0.15);
    border: 1px solid rgba(25, 135, 84, 0.1);
    transform: scale(0.9);
    animation: modalPop 0.3s ease forwards;
}

#successModal .modal-body {
    padding: 2.5rem 2rem;
    border-radius: 20px;
}

#successModal .fa-check-circle {
    color: #198754;
    font-size: 4.5rem;
    margin-bottom: 1.5rem;
    animation: iconBounce 0.5s ease;
}

#successModal p {
    color: #0f5132;
    font-size: 1.2rem;
    font-weight: 500;
    margin: 0;
}

/* 错误提示模态框样式 */
#errorModal .modal-content {
    background: linear-gradient(145deg, #fff5f5 0%, #ffe0e0 100%);
    box-shadow: 0 10px 30px rgba(220, 53, 69, 0.15);
    border: 1px solid rgba(220, 53, 69, 0.1);
    transform: scale(0.9);
    animation: modalPop 0.3s ease forwards;
}

#errorModal .modal-body {
    padding: 2.5rem 2rem;
}

#errorModal .error-icon-wrapper {
    width: 80px;
    height: 80px;
    margin: 0 auto;
    background: rgba(220, 53, 69, 0.1);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
}

#errorModal .fa-exclamation-circle {
    color: #dc3545;
    font-size: 3.5rem;
    animation: iconShake 0.5s ease;
}

#errorModal .error-message {
    color: #842029;
    font-size: 1.2rem;
    font-weight: 500;
    margin: 1rem 0;
    padding: 0 1rem;
}

#errorModal .btn-danger {
    background: linear-gradient(145deg, #dc3545, #c82333);
    border: none;
    border-radius: 10px;
    font-weight: 500;
    padding: 0.75rem 2rem;
    min-width: 120px;
    box-shadow: 0 4px 12px rgba(220, 53, 69, 0.2);
    transition: all 0.3s ease;
}

#errorModal .btn-danger:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 15px rgba(220, 53, 69, 0.3);
    background: linear-gradient(145deg, #c82333, #bd2130);
}

/* 加载提示模态框样式 */
#loadingModal .modal-content {
    background: rgba(255,255,255,0.95);
    border-radius: 20px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.1);
}

#loadingModal .modal-body {
    padding: 2rem;
    border-radius: 20px;
    text-align: center;
}

#loadingModal .spinner-border {
    width: 3rem;
    height: 3rem;
    margin-bottom: 1rem;
}

/* 表单验证样式 */
.form-select:required:invalid {
    border-color: rgba(0,0,0,0.1);
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%23343a40' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M2 5l6 6 6-6'/%3e%3c/svg%3e");
}

.form-select:required:invalid:focus {
    border-color: #86b7fe;
    box-shadow: 0 2px 8px rgba(13, 110, 253, 0.15);
}

.custom-select-wrapper.invalid .custom-select-trigger {
    border-color: #dc3545;
    background-color: #fff8f8;
}

/* 动画效果 */
@keyframes modalPop {
    0% {
        transform: scale(0.9);
        opacity: 0;
    }
    100% {
        transform: scale(1);
        opacity: 1;
    }
}

@keyframes iconBounce {
    0%, 20%, 50%, 80%, 100% {
        transform: translateY(0);
    }
    40% {
        transform: translateY(-20px);
    }
    60% {
        transform: translateY(-10px);
    }
}

@keyframes iconShake {
    0%, 100% {
        transform: translateX(0);
    }
    10%, 30%, 50%, 70%, 90% {
        transform: translateX(-5px);
    }
    20%, 40%, 60%, 80% {
        transform: translateX(5px);
    }
}

/* 确保模态框背景遮罩层样式正确 */
.modal-backdrop {
    background-color: rgba(33, 37, 41, 0.65);
}

/* 移除隐藏select的样式 */
.form-select {
    display: block !important; /* 强制显示select元素 */
    width: 100%;
    padding: 0.375rem 0.75rem;
    font-size: 1rem;
    font-weight: 400;
    line-height: 1.5;
    color: #212529;
    background-color: #fff;
    border: 1px solid #ced4da;
    border-radius: 0.25rem;
    transition: border-color .15s ease-in-out,box-shadow .15s ease-in-out;
}

.form-select:focus {
    border-color: #86b7fe;
    outline: 0;
    box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
}

/* 调整表单组间距 */
.mb-3 {
    margin-bottom: 1rem !important;
}

/* 调整标签样式 */
.form-label {
    margin-bottom: 0.5rem;
    font-weight: 500;
}

/* 下拉框基础样式 */
.form-select {
    background: linear-gradient(to bottom, #ffffff, #f8f9fa);
    border: 1px solid rgba(0,0,0,0.1);
    box-shadow: 0 2px 5px rgba(0,0,0,0.05);
    padding: 0.625rem 1rem;
    border-radius: 10px;
    font-size: 1.1rem;
    color: #2c3e50;
    cursor: pointer;
    min-height: 42px;
    transition: all 0.3s ease;
    -webkit-appearance: none;
    -moz-appearance: none;
    appearance: none;
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%23343a40' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M2 5l6 6 6-6'/%3e%3c/svg%3E");
    background-repeat: no-repeat;
    background-position: right 1rem center;
    background-size: 16px 12px;
}

.form-select:hover {
    border-color: #86b7fe;
    box-shadow: 0 2px 8px rgba(13, 110, 253, 0.1);
    transform: translateY(-1px);
}

.form-select:focus {
    border-color: #86b7fe;
    box-shadow: 0 2px 8px rgba(13, 110, 253, 0.15);
    outline: none;
}

/* 下拉框选项样式 */
.form-select option {
    padding: 0.75rem 1rem;
    font-size: 1.1rem;
    color: #2c3e50;
    background: #ffffff;
    cursor: pointer;
    transition: all 0.2s ease;
}

/* 下拉框选项悬停和选中样式 */
.form-select option:hover,
.form-select option:focus {
    background: linear-gradient(to right, #f0f7ff, #ffffff);
    color: #0d6efd;
}

.form-select option:checked {
    background: linear-gradient(to right, #e7f1ff, #ffffff);
    color: #0d6efd;
    font-weight: 500;
}

/* 下拉框禁用状态 */
.form-select:disabled,
.form-select[readonly] {
    background-color: #f8f9fa;
    opacity: 0.7;
    cursor: not-allowed;
    border-color: rgba(0,0,0,0.1);
}

/* 表单组样式优化 */
.form-group {
    margin-bottom: 1.5rem;
}

.form-label {
    font-weight: 500;
    color: #2c3e50;
    margin-bottom: 0.5rem;
    font-size: 1.1rem;
    display: block;
}

/* 下拉框加载状态样式 */
.form-select.loading {
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 16 16'%3E%3Cpath fill='%236c757d' d='M8 0a8 8 0 100 16A8 8 0 008 0zm0 14A6 6 0 118 2a6 6 0 010 12z'/%3E%3C/svg%3E");
    background-position: right 1rem center;
    background-size: 16px 16px;
    background-repeat: no-repeat;
    animation: rotate 1s linear infinite;
}

@keyframes rotate {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

/* 占位符文本样式 */
.form-select option[value=""] {
    color: #6c757d;
    font-style: normal;
}
</style>

<script src="../assets/js/jquery.min.js"></script>
<script src="../assets/js/bootstrap.bundle.min.js"></script>
<script>
// 将openModal函数移到全局作用域
function openModal(modalId) {
    if (modalId === 'multiSubjectModal') {
        return; // 多学科下载功能禁用
    }
    const modal = new bootstrap.Modal(document.getElementById(modalId));
    modal.show();
}

// 修改点击选项的处理逻辑
function handleOptionClick(optionElement) {
    try {
        const $option = $(optionElement);
        const $input = $option.find('input');

        if (!$input.length) {
            console.log('未找到input元素');
            return;
        }

        const isRadio = $input.attr('type') === 'radio';
        const formId = $option.closest('form').attr('id');

        // 处理单选框
        if (isRadio) {
            const name = $input.attr('name');
            if (name) {
                $(`.clickable-option:has(input[name="${name}"])`).removeClass('active');
                $option.addClass('active');
                $input.prop('checked', true);
            }
            return;
        }

        // 处理复选框（仅用于单学科下载）
        if (formId === 'singleSubjectForm') {
            const isChecked = $input.prop('checked');
            $option.toggleClass('active');
            $input.prop('checked', !isChecked);

            const $downloadSection = $option.closest('.col-md-6');
            if ($downloadSection.length) {
                const $downloadOptions = $downloadSection.find('.clickable-option');
                if ($downloadOptions.length) {
                    const hasCheckedOption = $downloadOptions.find('input:checked').length > 0;
                    $downloadOptions.each(function() {
                        $(this).css({
                            'border-color': hasCheckedOption ? '' : '#dc3545',
                            'background-color': hasCheckedOption ? '' : '#fff8f8'
                        });
                    });
                }
            }
        }
    } catch (error) {
        console.log('选项点击处理出错:', error);
    }
}

// 初始化可点击选项
function initClickableOptions() {
    try {
        // 包装下载内容选项
        $('.form-check').each(function() {
            const $formCheck = $(this);
            if (!$formCheck.parent().hasClass('clickable-option-wrapper')) {
                const $input = $formCheck.find('input');
                const $label = $formCheck.find('label');

                if ($input.length && $label.length) {
                    const isRadio = $input.attr('type') === 'radio';
                    $formCheck.wrap('<div class="clickable-option-wrapper"></div>');

                    const $wrapper = $('<div></div>')
                        .addClass('clickable-option')
                        .addClass(isRadio ? 'radio' : 'checkbox')
                        .append($input.clone())
                        .append($label.text());

                    $formCheck.parent().append($wrapper);
                    $formCheck.remove();
                }
            }
        });

        // 绑定点击事件
        $('.clickable-option').off('click').on('click', function() {
            handleOptionClick(this);
        });

        // 初始化默认选中状态
        $('.clickable-option input:checked').each(function() {
            $(this).closest('.clickable-option').addClass('active');
        });
    } catch (error) {
        console.log('初始化选项出错:', error);
    }
}

// 添加项目数据缓存
let projectsCache = null;

// 修改loadProjects函数
function loadProjects(selectElement) {
    if (!selectElement) {
        console.error('未提供select元素');
        return;
    }

    // 显示加载中提示
    selectElement.innerHTML = '<option value="">加载中...</option>';

    // 如果有缓存数据，直接使用
    if (projectsCache) {
        updateProjectSelect(selectElement, projectsCache);
        return;
    }

    fetch('../api/index.php?route=settings/project/list')
        .then(response => {
            if (!response.ok) {
                throw new Error('网络请求失败');
            }
            return response.json();
        })
        .then(data => {
           // console.log('加载项目列表响应:', data);
            if (data.success && Array.isArray(data.data)) {
                // 缓存数据
                projectsCache = data.data;
                // 更新所有项目选择下拉框
                document.querySelectorAll('select[name="setting_id"]').forEach(select => {
                    updateProjectSelect(select, data.data);
                });
            } else {
                throw new Error(data.error || '加载项目列表失败');
            }
        })
        .catch(error => {
            console.error('加载项目列表失败:', error);
            selectElement.innerHTML = '<option value="">加载失败，请刷新重试</option>';
            if (typeof showError === 'function') {
                showError('加载项目列表失败：' + error.message);
            }
        });
}

// 添加更新项目选择下拉框的函数
function updateProjectSelect(selectElement, projects) {
    selectElement.innerHTML = '<option value="">请选择项目</option>';
    projects.forEach(project => {
        if (project && project.id) {
            const option = document.createElement('option');
            option.value = project.id;
            option.textContent = `${project.school_name} - ${project.current_semester} - ${project.project_name}`;
            selectElement.appendChild(option);
        }
    });
}

// 修改loadGrades函数
function loadGrades(projectId) {
    if (!projectId) {
        //console.log('未选择项目，无法加载年级');
        $('select[name="grade_id"]').html('<option value="">请先选择项目</option>');
        return;
    }

   // console.log('开始加载项目对应的年级列表，项目ID：', projectId);

    // 显示加载中提示
    $('select[name="grade_id"]').html('<option value="">加载中...</option>');

    $.ajax({
        url: '../api/index.php',
        data: {
            route: 'grade/getAllGrades',
            setting_id: projectId
        },
        method: 'GET',
        dataType: 'json',
        success: function(response) {
            //console.log('年级列表响应：', response);

            if (response.success && Array.isArray(response.data)) {
                // 按年级代码排序
                const grades = response.data.sort((a, b) => {
                    const codeA = parseInt(a.grade_code) || 0;
                    const codeB = parseInt(b.grade_code) || 0;
                    return codeA - codeB;
                });

                if (grades.length > 0) {
                    let html = '<option value="">请选择年级</option>';
                    grades.forEach(grade => {
                        if (grade && grade.id && grade.grade_name) {
                            html += `<option value="${grade.id}">${grade.grade_name}</option>`;
                        }
                    });

                    $('select[name="grade_id"]').html(html);
                } else {
                    $('select[name="grade_id"]').html('<option value="">该项目下没有年级</option>');
                }
            } else {
                console.error('未找到关联的年级或加载失败:', response);
                $('select[name="grade_id"]').html('<option value="">该项目下没有年级</option>');
            }
        },
        error: function(xhr, status, error) {
            console.error('年级列表请求失败:', { status, error, xhr });
            $('select[name="grade_id"]').html('<option value="">加载失败</option>');
            if (typeof showError === 'function') {
                showError('加载年级失败：' + (xhr.responseJSON?.error || '网络请求错误'));
            }
        }
    });
}

// 添加loadSubjects函数
function loadSubjects(projectId, gradeId) {
    if (!projectId || !gradeId) {
        //console.log('未选择项目或年级，无法加载学科');
        $('select[name="subject_id"]').html('<option value="">请先选择项目和年级</option>');
        $('#subjectCheckboxes').html('请先选择项目和年级');
        return;
    }

    //console.log('开始加载年级对应的学科列表，项目ID：', projectId, '年级ID：', gradeId);

    // 显示加载中提示
    $('select[name="subject_id"]').html('<option value="">加载中...</option>');
    $('#subjectCheckboxes').html('加载中...');

    $.ajax({
        url: '../api/index.php',
        data: {
            route: 'settings/grade/subjects',
            grade_id: gradeId,
            setting_id: projectId
        },
        method: 'GET',
        dataType: 'json',
        success: function(response) {
            //console.log('学科列表响应：', response);

            if (response.success && Array.isArray(response.data)) {
                // 按学科代码排序
                const subjects = response.data.sort((a, b) => {
                    const codeA = parseInt(a.subject_code) || 0;
                    const codeB = parseInt(b.subject_code) || 0;
                    return codeA - codeB;
                });

                if (subjects.length > 0) {
                    let selectHtml = '<option value="">请选择学科</option>';
                    subjects.forEach(subject => {
                        if (subject && subject.id && subject.subject_name) {
                            selectHtml += `<option value="${subject.id}">${subject.subject_name}</option>`;
                        }
                    });
                    $('select[name="subject_id"]').html(selectHtml);

                    // 更新多学科复选框
                    let checkboxesHtml = '';
                    subjects.forEach(subject => {
                        if (subject && subject.id && subject.subject_name) {
                            checkboxesHtml += `
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" name="subjects[]" value="${subject.id}" id="subject_${subject.id}">
                                    <label class="form-check-label" for="subject_${subject.id}">${subject.subject_name}</label>
                                </div>`;
                        }
                    });
                    $('#subjectCheckboxes').html(checkboxesHtml);
                } else {
                    $('select[name="subject_id"]').html('<option value="">该年级下没有学科</option>');
                    $('#subjectCheckboxes').html('该年级下没有学科');
                    // 关闭模态框
                    const currentModal = bootstrap.Modal.getInstance(document.querySelector('.modal.show'));
                    if (currentModal) {
                        currentModal.hide();
                    }
                    showError('该年级下暂无可用学科');
                }
            } else {
                console.error('未找到关联的学科或加载失败:', response);
                $('select[name="subject_id"]').html('<option value="">加载学科失败</option>');
                $('#subjectCheckboxes').html('加载学科失败');
                // 关闭模态框
                const currentModal = bootstrap.Modal.getInstance(document.querySelector('.modal.show'));
                if (currentModal) {
                    currentModal.hide();
                }
                showError('加载学科失败：' + (response.error || '未知错误'));
            }
        },
        error: function(xhr, status, error) {
            console.error('加载学科列表失败:', error);
            $('select[name="subject_id"]').html('<option value="">加载失败，请重试</option>');
            $('#subjectCheckboxes').html('加载失败，请重试');
            // 关闭模态框
            const currentModal = bootstrap.Modal.getInstance(document.querySelector('.modal.show'));
            if (currentModal) {
                currentModal.hide();
            }
            showError('加载学科列表失败：' + (xhr.responseJSON?.error || '网络请求错误'));
        }
    });
}

// 检查测试数据函数
function checkTestData(projectId, gradeId, subjectId = null) {
    return new Promise((resolve, reject) => {
        if (subjectId) {
            // 单学科下载：先获取班级列表
            $.ajax({
                url: '../api/index.php',
                data: {
                    route: 'score/teacher_classes',
                    grade_id: gradeId,
                    subject_id: subjectId
                },
                method: 'GET',
                dataType: 'json',
                success: function(response) {
                    if (response.success && Array.isArray(response.data) && response.data.length > 0) {
                        const params = {
                            route: 'score/student_scores',
                            setting_id: projectId,
                            grade_id: gradeId,
                            subject_id: subjectId,
                            class_id: response.data[0].id
                        };
                        checkScores(params, resolve, reject);
                    } else {
                        reject('该年级下暂无班级数据');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('获取班级列表失败:', error);
                    reject('获取班级列表失败：' + (xhr.responseJSON?.error || '网络请求错误'));
                }
            });
        } else {
            // 语数下载：先获取语文和数学的学科ID
            $.ajax({
                url: '../api/index.php',
                data: {
                    route: 'settings/grade/subjects',
                    grade_id: gradeId,
                    setting_id: projectId
                },
                method: 'GET',
                dataType: 'json',
                success: function(response) {
                    if (!response.success || !Array.isArray(response.data)) {
                        reject('获取学科列表失败');
                        return;
                    }

                    // 找到语文和数学的学科ID
                    const chineseSubject = response.data.find(s => s.subject_name === '语文');
                    const mathSubject = response.data.find(s => s.subject_name === '数学');

                    if (!chineseSubject || !mathSubject) {
                        reject('未找到语文或数学学科');
                        return;
                    }

                    // 获取班级列表
                    $.ajax({
                        url: '../api/index.php',
                        data: {
                            route: 'class/getList',
                            grade_id: gradeId
                        },
                        method: 'GET',
                        dataType: 'json',
                        success: function(response) {
                            if (!response.success || !Array.isArray(response.data) || response.data.length === 0) {
                                reject('该年级下暂无班级数据');
                                return;
                            }

                            const classId = response.data[0].id;

                            // 检查语文成绩
                            const chineseParams = {
                                route: 'score/student_scores',
                                setting_id: projectId,
                                grade_id: gradeId,
                                subject_id: chineseSubject.id,
                                class_id: classId
                            };

                            // 检查数学成绩
                            const mathParams = {
                                route: 'score/student_scores',
                                setting_id: projectId,
                                grade_id: gradeId,
                                subject_id: mathSubject.id,
                                class_id: classId
                            };

                            // 同时检查语文和数学的成绩
                            Promise.all([
                                new Promise((res, rej) => checkScores(chineseParams, res, rej)),
                                new Promise((res, rej) => checkScores(mathParams, res, rej))
                            ]).then(() => {
                                resolve(true);
                            }).catch(error => {
                                reject(error);
                            });
                        },
                        error: function(xhr, status, error) {
                            console.error('获取班级列表失败:', error);
                            reject('获取班级列表失败：' + (xhr.responseJSON?.error || '网络请求错误'));
                        }
                    });
                },
                error: function(xhr, status, error) {
                    console.error('获取学科列表失败:', error);
                    reject('获取学科列表失败：' + (xhr.responseJSON?.error || '网络请求错误'));
                }
            });
        }
    });
}

// 检查成绩数据的辅助函数
function checkScores(params, resolve, reject) {
    $.ajax({
        url: '../api/index.php',
        data: params,
        method: 'GET',
        dataType: 'json',
        success: function(response) {
            //console.log('检查测试数据响应：', response);
            // 无论是否有数据，都允许下载
            if (!response.success) {
                reject('检查数据失败：' + (response.error || '未知错误'));
            } else {
                // 即使没有数据，也允许下载
                resolve(true);

                // 如果没有数据，在控制台输出提示信息（仅供调试）
                if (!response.data || response.data.length === 0) {
                    //console.log('注意：该年级下暂无测试数据，但仍允许下载');
                } else {
                    const hasScores = response.data.some(student =>
                        student.score_id !== null ||
                        student.base_score !== null ||
                        student.total_score !== null ||
                        student.is_absent !== null
                    );

                    if (!hasScores) {
                        //console.log('注意：该年级下暂无测试数据，但仍允许下载');
                    }
                }
            }
        },
        error: function(xhr, status, error) {
            console.error('检查测试数据失败:', error);
            reject('检查测试数据失败：' + (xhr.responseJSON?.error || '网络请求错误'));
        }
    });
}

// 处理成功提示模态框关闭
window.handleSuccessModalClose = function() {
    // 关闭成功提示模态框
    const successModal = bootstrap.Modal.getInstance(document.getElementById('successModal'));
    if (successModal) {
        successModal.hide();
    }

    // 关闭加载提示模态框
    const loadingModal = document.getElementById('loadingModal');
    if (loadingModal) {
        const bsLoadingModal = bootstrap.Modal.getInstance(loadingModal);
        if (bsLoadingModal) {
            bsLoadingModal.hide();
        }
        // 移除加载提示模态框
        document.body.removeChild(loadingModal);
    }

    // 清理所有模态框相关的DOM元素和样式
    $('.modal-backdrop').remove();
    $('body').removeClass('modal-open').css('padding-right', '');
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
                    grade_id: gradeId
                },
                method: 'GET',
                dataType: 'json',
                success: function(response) {
                    if (!response.success || !Array.isArray(response.data)) {
                        reject('获取学科列表失败');
                        return;
                    }

                    // 找到语文和数学的学科ID
                    const chineseSubject = response.data.find(s => s.subject_name === '语文');
                    const mathSubject = response.data.find(s => s.subject_name === '数学');

                    if (!chineseSubject || !mathSubject) {
                        reject('未找到语文或数学学科');
                        return;
                    }

                    // 同时检查语文和数学是否有待审核记录
                    Promise.all([
                        checkSingleSubjectPendingEdits(gradeId, chineseSubject.id),
                        checkSingleSubjectPendingEdits(gradeId, mathSubject.id)
                    ]).then(results => {
                        // 如果任一学科有待审核记录，则返回true
                        const hasPending = results.some(result => result.hasPendingRequest);
                        resolve({
                            hasPendingRequest: hasPending,
                            subjects: hasPending ? ['语文', '数学'].filter((_, index) => results[index].hasPendingRequest) : []
                        });
                    }).catch(error => {
                        reject(error);
                    });
                },
                error: function(xhr) {
                    reject('获取学科列表失败：' + (xhr.responseJSON?.error || '网络请求错误'));
                }
            });
        } else {
            // 单学科下载，直接检查指定学科
            checkSingleSubjectPendingEdits(gradeId, subjectId)
                .then(result => resolve(result))
                .catch(error => reject(error));
        }
    });
}

// 检查单个学科是否有待审核记录
function checkSingleSubjectPendingEdits(gradeId, subjectId) {
    return new Promise((resolve, reject) => {
        $.ajax({
            url: '../api/index.php?route=score_edit/check_pending_by_grade',
            data: {
                grade_id: gradeId,
                subject_id: subjectId
            },
            method: 'GET',
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    resolve({
                        hasPendingRequest: response.has_pending_request,
                        count: response.count || 0
                    });
                } else {
                    reject('检查待审核状态失败：' + (response.error || '未知错误'));
                }
            },
            error: function(xhr) {
                reject('检查待审核状态失败：' + (xhr.responseJSON?.error || '网络请求错误'));
            }
        });
    });
}

// 下载处理函数
function handleDownload(type, formData) {
    // 创建加载提示模态框
    const loadingModal = document.createElement('div');
    loadingModal.className = 'modal fade';
    loadingModal.id = 'loadingModal';
    loadingModal.setAttribute('data-bs-backdrop', 'static');
    loadingModal.setAttribute('data-bs-keyboard', 'false');
    loadingModal.style.zIndex = '1060';
    loadingModal.innerHTML = `
        <div class="modal-dialog modal-sm modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-body text-center p-4">
                    <div class="spinner-border text-primary mb-3" role="status"></div>
                    <p class="mb-0">正在检查数据状态，请稍候...</p>
                </div>
            </div>
        </div>
    `;
    document.body.appendChild(loadingModal);

    // 显示加载提示
    const bsLoadingModal = new bootstrap.Modal(loadingModal);
    bsLoadingModal.show();

    // 添加超时处理，确保加载提示不会无限显示
    const loadingTimeout = setTimeout(() => {
        try {
            bsLoadingModal.hide();
            if (document.body.contains(loadingModal)) {
                document.body.removeChild(loadingModal);
            }
            showError('操作超时，请重试');
        } catch (e) {
            console.error('关闭加载提示失败:', e);
        }
    }, 15000); // 15秒超时

    // 检查是否有待审核的成绩修改申请
    const gradeId = formData.grade_id;
    const subjectId = type === 'single_subject' ? formData.subject_id : null;

    checkPendingScoreEdits(gradeId, subjectId)
        .then(result => {
            if (result.hasPendingRequest) {
                // 有待审核记录，中断下载操作
                clearTimeout(loadingTimeout);
                
                // 关闭当前打开的模态框
                const currentModal = bootstrap.Modal.getInstance(document.getElementById(type + 'Modal'));
                if (currentModal) {
                    currentModal.hide();
                }

                // 显示错误提示
                let message = '';
                if (type === 'single_subject') {
                    message = `该年级学科存在待审核的成绩修改申请，请先完成审核后再下载数据。`;
                } else {
                    message = `该年级${result.subjects.join('、')}学科存在待审核的成绩修改申请，请先完成审核后再下载数据。`;
                }
                
                // 先关闭加载提示，然后显示错误信息
                try {
                    bsLoadingModal.hide();
                } catch (e) {
                    console.error('关闭加载提示失败:', e);
                }
                
                // 确保DOM元素被移除
                setTimeout(() => {
                    try {
                        if (document.body.contains(loadingModal)) {
                            document.body.removeChild(loadingModal);
                        }
                        
                        // 清理模态框相关的DOM元素和样式
                        $('.modal-backdrop').remove();
                        $('body').removeClass('modal-open').css('padding-right', '');
                        
                        // 显示错误提示
                        showError(message);
                    } catch (e) {
                        console.error('清理DOM元素失败:', e);
                        // 最后尝试直接显示错误
                        showError(message);
                    }
                }, 300);
                
                return;
            }

            // 更新加载提示文本
            loadingModal.querySelector('p').textContent = '正在生成下载文件，请稍候...';

            // 关闭当前打开的模态框
            const currentModal = bootstrap.Modal.getInstance(document.getElementById(type + 'Modal'));
            if (currentModal) {
                currentModal.hide();
            }

            $.ajax({
                url: '../api/index.php?route=download/' + type,
                method: 'POST',
                data: formData,
                success: function(response) {
                    // 清除超时计时器
                    clearTimeout(loadingTimeout);
                    
                    if (response.success) {
                        // 创建下载链接并触发下载
                        const link = document.createElement('a');
                        link.href = response.data.file_url;
                        link.download = response.data.filename;
                        document.body.appendChild(link);
                        link.click();
                        document.body.removeChild(link);

                        // 关闭加载提示
                        try {
                            bsLoadingModal.hide();
                            if (document.body.contains(loadingModal)) {
                                document.body.removeChild(loadingModal);
                            }
                        } catch (e) {
                            console.error('关闭加载提示失败:', e);
                        }

                        // 清理模态框相关的DOM元素和样式
                        $('.modal-backdrop').remove();
                        $('body').removeClass('modal-open').css('padding-right', '');

                        // 显示成功提示模态框
                        const successModal = new bootstrap.Modal(document.getElementById('successModal'));
                        successModal.show();

                        // 设置定时器，3秒后自动关闭模态框
                        setTimeout(() => {
                            successModal.hide();
                            handleSuccessModalClose();
                        }, 3000);
                    } else {
                        // 关闭加载提示
                        try {
                            bsLoadingModal.hide();
                            if (document.body.contains(loadingModal)) {
                                document.body.removeChild(loadingModal);
                            }
                        } catch (e) {
                            console.error('关闭加载提示失败:', e);
                        }

                        // 清理模态框相关的DOM元素和样式
                        $('.modal-backdrop').remove();
                        $('body').removeClass('modal-open').css('padding-right', '');

                        // 显示错误提示
                        showError(response.error || '下载失败，请重试');
                    }
                },
                error: function(xhr) {
                    // 清除超时计时器
                    clearTimeout(loadingTimeout);
                    
                    // 关闭加载提示
                    try {
                        bsLoadingModal.hide();
                        if (document.body.contains(loadingModal)) {
                            document.body.removeChild(loadingModal);
                        }
                    } catch (e) {
                        console.error('关闭加载提示失败:', e);
                    }

                    // 清理模态框相关的DOM元素和样式
                    $('.modal-backdrop').remove();
                    $('body').removeClass('modal-open').css('padding-right', '');

                    // 显示错误提示
                    showError('下载请求失败：' + (xhr.responseJSON?.error || '网络错误，请重试'));
                }
            });
        })
        .catch(error => {
            // 清除超时计时器
            clearTimeout(loadingTimeout);
            
            // 检查失败，关闭加载提示
            try {
                bsLoadingModal.hide();
                if (document.body.contains(loadingModal)) {
                    document.body.removeChild(loadingModal);
                }
            } catch (e) {
                console.error('关闭加载提示失败:', e);
            }

            // 清理模态框相关的DOM元素和样式
            $('.modal-backdrop').remove();
            $('body').removeClass('modal-open').css('padding-right', '');

            // 显示错误提示
            showError('检查数据状态失败：' + error);
        });
}

// 单学科下载
$('#downloadSingleSubject').click(function() {
    const form = document.getElementById('singleSubjectForm');

    // 移除所有之前的验证样式
    form.querySelectorAll('.form-select').forEach(select => {
        select.classList.remove('is-invalid');
    });

    // 检查必填字段
    let isValid = true;
    form.querySelectorAll('select[required]').forEach(select => {
        if (!select.value) {
            select.classList.add('is-invalid');
            const wrapper = select.nextElementSibling;
            if (wrapper && wrapper.classList.contains('custom-select-wrapper')) {
                wrapper.classList.add('invalid');
            }
            isValid = false;
        }
    });

    // 检查是否至少选择了一项下载内容
    const includeScore = form.querySelector('input[name="include_score"]').checked;
    const includeLevel = form.querySelector('input[name="include_level"]').checked;

    if (!includeScore && !includeLevel) {
        const downloadOptions = form.querySelector('.clickable-option-wrapper');
        if (downloadOptions) {
            downloadOptions.querySelectorAll('.clickable-option').forEach(option => {
                option.style.borderColor = '#dc3545';
                option.style.backgroundColor = '#fff8f8';
            });
        }
        isValid = false;
    }

    if (!isValid) {
        return;
    }

    // 获取表单数据
    const settingId = form.querySelector('select[name="setting_id"]').value;
    const gradeId = form.querySelector('select[name="grade_id"]').value;
    const subjectId = form.querySelector('select[name="subject_id"]').value;
    const projectName = form.querySelector('select[name="setting_id"] option:checked').text;
    const gradeName = form.querySelector('select[name="grade_id"] option:checked').text;
    const subjectName = form.querySelector('select[name="subject_id"] option:checked').text;

    // 构建下载内容描述
    let downloadContent = [];
    if (includeScore) downloadContent.push('分数');
    if (includeLevel) downloadContent.push('等级');

    // 记录日志
    $.ajax({
        url: '../api/index.php?route=log/add',
        method: 'POST',
        data: {
            action_type: 'download',
            action_detail: `单科目下载\n项目：${projectName}\n年级：${gradeName}${subjectName}\n下载内容：${downloadContent.join('、')}`
        }
    });

    const formData = {
        setting_id: settingId,
        grade_id: gradeId,
        subject_id: subjectId,
        include_score: includeScore ? "1" : "0",
        include_level: includeLevel ? "1" : "0",
        sort_by: form.querySelector('input[name="sort_by"]:checked').value
    };

    handleDownload('single_subject', formData);
});

// 多学科下载
$('#downloadMultiSubject').click(function() {
    const formData = {
        setting_id: $('#multiSubjectForm select[name="setting_id"]').val(),
        grade_id: $('#multiSubjectForm select[name="grade_id"]').val(),
        subject_ids: $('#multiSubjectForm input[name="subjects[]"]:checked').map(function() {
            return $(this).val();
        }).get(),
        download_type: $('#multiSubjectForm input[name="downloadType"]:checked').val(),
        sort_by: $('#multiSubjectForm input[name="sortBy"]:checked').val()
    };
    handleDownload('multi_subject', formData);
});

// 语数下载
$('#downloadChineseMath').click(function() {
    const form = document.getElementById('chineseMathForm');

    // 移除所有之前的验证样式
    form.querySelectorAll('.form-select').forEach(select => {
        select.classList.remove('is-invalid');
    });

    // 检查必填字段
    let isValid = true;
    form.querySelectorAll('select[required]').forEach(select => {
        if (!select.value) {
            select.classList.add('is-invalid');
            const wrapper = select.nextElementSibling;
            if (wrapper && wrapper.classList.contains('custom-select-wrapper')) {
                wrapper.classList.add('invalid');
            }
            isValid = false;
        }
    });

    if (!isValid) {
        return;
    }

    // 获取表单数据
    const settingId = form.querySelector('select[name="setting_id"]').value;
    const gradeId = form.querySelector('select[name="grade_id"]').value;
    const projectName = form.querySelector('select[name="setting_id"] option:checked').text;
    const gradeName = form.querySelector('select[name="grade_id"] option:checked').text;
    const downloadType = form.querySelector('input[name="download_type"]:checked').value;

    // 记录日志
    $.ajax({
        url: '../api/index.php?route=log/add',
        method: 'POST',
        data: {
            action_type: 'download',
            action_detail: `语数下载\n项目：${projectName}\n年级：${gradeName}\n下载内容：${downloadType === 'score' ? '分数' : '等级'}`
        }
    });

    const formData = {
        setting_id: settingId,
        grade_id: gradeId,
        download_type: downloadType,
        sort_by: form.querySelector('input[name="sort_by"]:checked').value
    };
    handleDownload('chinese_math', formData);
});

// 添加显示错误信息的函数
function showError(message) {
    // 先关闭可能存在的加载提示模态框
    const loadingModal = document.getElementById('loadingModal');
    if (loadingModal) {
        try {
            const bsLoadingModal = bootstrap.Modal.getInstance(loadingModal);
            if (bsLoadingModal) {
                bsLoadingModal.hide();
                // 给浏览器一点时间处理modal隐藏
                setTimeout(() => {
                    try {
                        if (document.body.contains(loadingModal)) {
                            document.body.removeChild(loadingModal);
                        }
                    } catch (e) {
                        console.error('移除加载提示模态框失败:', e);
                    }
                }, 300);
            } else if (document.body.contains(loadingModal)) {
                // 如果没有实例但元素存在，直接移除
                document.body.removeChild(loadingModal);
            }
        } catch (e) {
            console.error('关闭加载提示模态框失败:', e);
            // 尝试直接移除元素
            try {
                if (document.body.contains(loadingModal)) {
                    document.body.removeChild(loadingModal);
                }
            } catch (innerE) {
                console.error('移除加载提示模态框元素失败:', innerE);
            }
        }
    }

    // 清理模态框相关的DOM元素和样式
    try {
        $('.modal-backdrop').remove();
        $('body').removeClass('modal-open').css('padding-right', '');
    } catch (e) {
        console.error('清理模态框样式失败:', e);
    }

    // 显示错误提示模态框
    try {
        const errorModal = document.getElementById('errorModal');
        if (errorModal) {
            errorModal.querySelector('.error-message').textContent = message;
            const bsErrorModal = new bootstrap.Modal(errorModal);
            bsErrorModal.show();
        } else {
            // 如果找不到错误模态框，使用alert作为备选
            alert(message);
        }
    } catch (e) {
        console.error('显示错误提示失败:', e);
        // 使用原生alert作为最后的备选
        alert(message);
    }
}

// 修改初始化函数
function initializeDownloadModule() {
    // 确保所有select元素都存在
    const projectSelects = document.querySelectorAll('select[name="setting_id"]');
    if (projectSelects.length > 0) {
        // 加载项目数据（只需要调用一次，会自动更新所有下拉框）
        loadProjects(projectSelects[0]);

        // 为每个项目选择框添加change事件
        projectSelects.forEach(select => {
            $(select).off('change').on('change', function() {
                const projectId = $(this).val();
                //console.log('项目选择变更，ID:', projectId);
                loadGrades(projectId);
            });
        });

        // 为年级选择框添加change事件
        $('select[name="grade_id"]').off('change').on('change', function() {
            const projectId = $(this).closest('form').find('select[name="setting_id"]').val();
            const gradeId = $(this).val();
            const formId = $(this).closest('form').attr('id');

            if (formId === 'chineseMathForm') {
                // 语数下载：选择年级后立即检查测试数据
                checkTestData(projectId, gradeId)
                    .catch(error => {
                        const currentModal = bootstrap.Modal.getInstance(document.querySelector('.modal.show'));
                        if (currentModal) {
                            currentModal.hide();
                        }
                        showError(error);
                    });
            } else {
                // 其他表单：正常加载学科列表
                loadSubjects(projectId, gradeId);
            }
        });

        // 为学科选择框添加change事件（单学科下载）
        $('select[name="subject_id"]').off('change').on('change', function() {
            const form = $(this).closest('form');
            if (form.attr('id') === 'singleSubjectForm') {
                const projectId = form.find('select[name="setting_id"]').val();
                const gradeId = form.find('select[name="grade_id"]').val();
                const subjectId = $(this).val();

                if (subjectId) {
                    checkTestData(projectId, gradeId, subjectId)
                        .catch(error => {
                            $(this).val(''); // 清空选择
                            const currentModal = bootstrap.Modal.getInstance(document.querySelector('.modal.show'));
                            if (currentModal) {
                                currentModal.hide();
                            }
                            showError(error);
                        });
                }
            }
        });
    } else {
        console.error('未找到项目选择下拉框');
    }
}

// 添加页面加载完成后的初始化
$(document).ready(function() {
    initializeDownloadModule();
    initClickableOptions();
});
</script>