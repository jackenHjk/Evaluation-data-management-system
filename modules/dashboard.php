<?php
/**
 * 文件名: modules/dashboard.php
 * 功能描述: 系统仪表盘/首页模块
 * 
 * 该文件负责:
 * 1. 展示系统仪表盘首页
 * 2. 获取当前项目信息和配置
 * 3. 获取年级和科目数据
 * 4. 显示各年级数据录入进度
 * 5. 提供快速访问系统主要功能的入口
 * 
 * 该模块通过AJAX加载到index.php的主界面中，
 * 是用户登录系统后默认看到的首页。
 * 
 * API调用说明:
 * - 直接读取数据库获取项目、年级和科目信息
 * 
 * 关联文件:
 * - index.php: 主入口文件，通过AJAX加载此模块
 * - config/config.php: 配置文件，提供数据库连接参数
 * - assets/css/: 样式文件目录
 * - assets/js/: JavaScript文件目录
 */

// 获取项目根目录
$rootPath = dirname(dirname(__FILE__));
$config = require_once $rootPath . '/config/config.php';

try {
    // 使用配置数组连接数据库
    $dsn = "mysql:host={$config['db']['host']};dbname={$config['db']['dbname']};charset={$config['db']['charset']}";
    $db = new PDO($dsn, $config['db']['username'], $config['db']['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$config['db']['charset']}"
    ]);

    // 获取当前可用项目信息
    $currentProject = null;
    $stmt = $db->query("SELECT * FROM settings WHERE status = 1 LIMIT 1");
    if ($stmt) {
        $currentProject = $stmt->fetch();
    }

    // 获取所有年级信息，按年级代码排序
    $grades = [];
    if ($currentProject) {
        $stmt = $db->prepare("SELECT * FROM grades WHERE status = 1 AND setting_id = ? ORDER BY grade_code ASC");
        $stmt->execute([$currentProject['id']]);
        if ($stmt) {
            $grades = $stmt->fetchAll();
        }
    }

    // 获取所有学科信息
    $projectSubjects = [];
    if ($currentProject) {
        // 获取项目关联的学科
        $stmt = $db->prepare("
            SELECT s.* 
            FROM subjects s
            WHERE s.setting_id = ? 
            AND s.status = 1
        ");
        $stmt->execute([$currentProject['id']]);
        while ($row = $stmt->fetch()) {
            $projectSubjects[$row['id']] = $row;
        }
    }

    // 获取年级关联的学科
    $gradeSubjects = [];
    if ($currentProject) {
        foreach ($grades as $grade) {
            $stmt = $db->prepare("
                SELECT DISTINCT s.* 
                FROM subjects s
                INNER JOIN subject_grades sg ON s.id = sg.subject_id
                WHERE s.setting_id = ?
                AND s.status = 1
                AND sg.grade_id = ?
            ");
            $stmt->execute([$currentProject['id'], $grade['id']]);
            while ($row = $stmt->fetch()) {
                if (isset($projectSubjects[$row['id']])) {
                    $gradeSubjects[$grade['id']][$row['id']] = $row;
                }
            }
        }
    }

} catch (Exception $e) {
    error_log("Dashboard Error: " . $e->getMessage());
    die('<div class="alert alert-danger">数据库连接失败，请检查配置或联系管理员。详细错误：' . $e->getMessage() . '</div>');
}
?>

<!-- CSS 依赖 -->
<link href="../assets/css/bootstrap.min.css" rel="stylesheet">
<link href="../assets/css/sweetalert2.min.css" rel="stylesheet">
<link href="../assets/css/all.min.css" rel="stylesheet">
<link href="../assets/css/common.css" rel="stylesheet">

<style>
/* 页面背景色 */
#content {
    background-color: white;
    padding: 20px;
    border-radius: 8px;
}

.welcome-section {
    padding: 1.5rem 20px;
    background: rgba(248, 249, 250, 0.2);
    border-radius: 12px;
    margin: 0 auto 2rem;
    max-width: 1200px;  /* 增加最大宽度以适应网格布局 */
    text-align: center;
    box-shadow: 0 1px 3px rgba(0,0,0,0.05);
}

/* 移动端立即应用样式 */
@media (max-width: 991.98px) {
    .welcome-section {
        padding: 1rem 15px;
        margin: 1rem auto;
    }
    
    .welcome-section h2 {
        font-size: 1.3rem;
    }
    
    .welcome-section p {
        font-size: 0.9rem;
    }
}

.welcome-section h2 {
    font-weight: 600;
    color: #1D1D1F;
    margin-bottom: 0.5rem;
}

.welcome-section p {
    color: #6c757d;
    margin-bottom: 0;
}
.progress-legend {
    background: #f8f9fa;
    padding: 12px 20px;
    border-radius: 8px;
    margin: 0 auto 1.5rem;
    max-width: 1200px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.05);
    text-align: center;
}
.legend-item {
    display: inline-block;
    margin-right: 1.5rem;
    font-size: 0.9rem;
    color: #666;
}
.legend-color {
    display: inline-block;
    width: 16px;
    height: 16px;
    margin-right: 5px;
    vertical-align: middle;
    border-radius: 4px;
}
.not-started { background: transparent; border: 1px solid #ddd; }
.in-progress { background: rgba(255, 99, 71, 0.2); }
.completed { background: rgba(40, 167, 69, 0.2); }

.grade-section {
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    overflow: hidden;
    height: fit-content;
    border: 1px solid #eee;
}

/* 为不同年级添加不同的背景色 */
.grade-section:nth-child(4n+1) {
    background: linear-gradient(to bottom right, #fff, #f0f7ff);
}
.grade-section:nth-child(4n+1) .grade-header,
.grade-section:nth-child(4n+1) thead {
    background: linear-gradient(to right, #fff, #f0f7ff);
}

.grade-section:nth-child(4n+2) {
    background: linear-gradient(to bottom right, #fff, #f6fff0);
}
.grade-section:nth-child(4n+2) .grade-header,
.grade-section:nth-child(4n+2) thead {
    background: linear-gradient(to right, #fff, #f6fff0);
}

.grade-section:nth-child(4n+3) {
    background: linear-gradient(to bottom right, #fff, #fff7f0);
}
.grade-section:nth-child(4n+3) .grade-header,
.grade-section:nth-child(4n+3) thead {
    background: linear-gradient(to right, #fff, #fff7f0);
}

.grade-section:nth-child(4n+4) {
    background: linear-gradient(to bottom right, #fff, #f0f0ff);
}
.grade-section:nth-child(4n+4) .grade-header,
.grade-section:nth-child(4n+4) thead {
    background: linear-gradient(to right, #fff, #f0f0ff);
}

.grade-header {
    padding: 8px 12px;
    font-weight: 600;
    font-size: 0.95rem;
    color: #1D1D1F;
    border-bottom: 1px solid rgba(0,0,0,0.05);
    text-align: center;
}

.grade-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.85rem;
}

.grade-table thead {
    position: relative;
}

.grade-table th {
    padding: 6px 8px;
    font-weight: 600;
    color: #1D1D1F;
    border-bottom: 1px solid rgba(0,0,0,0.05);
    white-space: nowrap;
    text-align: center;
    background: transparent;
}

.grade-table td {
    padding: 6px 8px;
    border-bottom: 1px solid #eee;
}

/* 设置列宽和对齐方式 */
.grade-table th:nth-child(1),
.grade-table td:nth-child(1) {
    width: 80px;
    text-align: center;  /* 班级列居中 */
}

.grade-table th:nth-child(2),
.grade-table td:nth-child(2) {
    width: 60px;
    text-align: center;  /* 学生数列居中 */
}

.grade-table th:nth-child(3) {
    text-align: center;  /* 学科录入情况标题居中 */
}

.grade-table td:nth-child(3) {
    text-align: left;  /* 学科标签左对齐 */
    padding-left: 15px;  /* 添加左侧内边距 */
}

/* 调整学科标签样式 */
.subject-tag {
    display: inline-block;
    padding: 2px 6px;
    margin: 2px;
    border-radius: 3px;
    font-size: 0.8rem;
    min-width: 50px;
    text-align: center;  /* 标签内的文字保持居中 */
}

.subject-tag.not-started {
    background: #f8f9fa;
    border: 1px solid #eee;
    color: #666;
}

.subject-tag.in-progress {
    background: #fff3f0;
    border: 1px solid #ffccc7;
    color: #ff4d4f;
}

.subject-tag.completed {
    background: #f6ffed;
    border: 1px solid #b7eb8f;
    color: #52c41a;
}

.zero-students {
    color: #ff4d4f;
    font-weight: 500;
    font-size: 0.85rem;
}

.project-title {
    font-size: 1.5rem;
    font-weight: 600;
    color: #1D1D1F;
    margin: 0 auto 1.5rem;
    max-width: 1000px;
    text-align: center;
    padding: 0 20px;
}

/* 新的网格布局容器 */
.grades-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
    gap: 15px;
    max-width: 1600px;
    margin: 0 auto;
    padding: 0 15px;
}

/* 响应式布局调整 */
@media (max-width: 1200px) {
    .grades-grid {
        grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    }
}

@media (max-width: 900px) {
    .grades-grid {
        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    }
    
    .welcome-section {
        padding: 1rem 15px;
    }
    
    .welcome-section h2 {
        font-size: 1.3rem;
    }
    
    .welcome-section p {
        font-size: 0.9rem;
    }
    
    .progress-legend {
        padding: 8px 10px;
        display: flex;
        flex-wrap: wrap;
        justify-content: center;
        gap: 0.5rem;
    }
    
    .legend-item {
        margin-right: 0;
        font-size: 0.8rem;
    }
}

@media (max-width: 600px) {
    .grades-grid {
        grid-template-columns: 1fr;
        gap: 10px;
        padding: 0 10px;
    }
    
    .grade-table th,
    .grade-table td {
        padding: 5px 6px;
        font-size: 0.8rem;
    }
    
    .subject-tag {
        padding: 1px 4px;
        margin: 1px;
        min-width: 40px;
        font-size: 0.75rem;
    }
    
    .grade-table th:nth-child(1),
    .grade-table td:nth-child(1) {
        width: 60px;
    }
    
    .grade-table th:nth-child(2),
    .grade-table td:nth-child(2) {
        width: 50px;
    }
    
    .grade-table td:nth-child(3) {
        padding-left: 8px;
    }
}

/* 简化动画 */
.auto-refresh {
    animation: fadeIn 0.3s ease;
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

<?php foreach ($grades as $grade): ?>
    .grade-section:nth-child(<?php echo $loop->iteration; ?>) {
        animation-delay: <?php echo ($loop->iteration - 1) * 0.1; ?>s;
    }
<?php endforeach; ?>
</style>

<div class="welcome-section">
    <h2>欢迎使用测评数据管理系统</h2>
    <p>请从上方菜单选择要使用的功能</p>
</div>

<div class="auto-refresh">
    <?php if ($currentProject): ?>

    <div class="progress-legend text-center">
    学科颜色块图示：
        <div class="legend-item">
            <span class="legend-color not-started"></span>
            暂无数据
        </div>
        <div class="legend-item">
            <span class="legend-color in-progress"></span>
            录入中
        </div>
        <div class="legend-item">
            <span class="legend-color completed"></span>
            已完成
        </div>
    </div>

    <div class="grades-grid">
    <?php foreach ($grades as $grade): ?>
        <?php
        // 获取该年级下的所有班级
        $stmt = $db->prepare("
            SELECT c.* 
            FROM classes c
            WHERE c.grade_id = ?
            AND c.status = 1
            AND c.setting_id = ?
            ORDER BY c.class_code ASC
        ");
        $stmt->execute([$grade['id'], $currentProject['id']]);
        $classes = $stmt->fetchAll();
        ?>

        <div class="grade-section">
            <div class="grade-header">
                <?php echo htmlspecialchars($grade['grade_name']); ?>
            </div>
            <table class="grade-table">
                <thead>
                    <tr>
                        <th>班级</th>
                        <th>学生数</th>
                        <th>学科录入情况</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($classes as $class): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($class['class_name']); ?></td>
                            <?php
                            // 获取班级学生总数
                            $stmt = $db->prepare("
                                SELECT COUNT(*) as total 
                                FROM students 
                                WHERE class_id = ? 
                                AND status = 1
                            ");
                            $stmt->execute([$class['id']]);
                            $totalStudents = $stmt->fetch()['total'];
                            ?>
                            <td class="<?php echo $totalStudents == 0 ? 'zero-students' : ''; ?>">
                                <?php echo $totalStudents; ?>
                            </td>
                            <td>
                                <?php if (isset($gradeSubjects[$grade['id']])): ?>
                                    <?php foreach ($gradeSubjects[$grade['id']] as $subject): ?>
                                        <?php
                                        // 获取该班级该学科的有效成绩数
                                        $stmt = $db->prepare("
                                            SELECT COUNT(*) as count
                                            FROM scores sc
                                            INNER JOIN students s ON s.id = sc.student_id
                                            WHERE s.class_id = ?
                                            AND sc.subject_id = ?
                                            AND sc.setting_id = ?
                                            AND (
                                                sc.is_absent = 1 
                                                OR sc.base_score IS NOT NULL 
                                                OR sc.total_score IS NOT NULL
                                            )
                                        ");
                                        $stmt->execute([$class['id'], $subject['id'], $currentProject['id']]);
                                        $validScoreCount = $stmt->fetch()['count'];

                                        // 检查是否已生成分析报告
                                        $stmt = $db->prepare("
                                            SELECT COUNT(*) as count
                                            FROM score_analytics
                                            WHERE class_id = ?
                                            AND subject_id = ?
                                            AND setting_id = ?
                                        ");
                                        $stmt->execute([$class['id'], $subject['id'], $currentProject['id']]);
                                        $hasAnalytics = $stmt->fetch()['count'] > 0;

                                        // 根据录入情况设置不同的样式
                                        $tagClass = 'subject-tag ';
                                        if ($totalStudents == 0) {
                                            $tagClass .= 'not-started';
                                        } elseif ($validScoreCount == 0) {
                                            $tagClass .= 'not-started';
                                        } elseif ($validScoreCount < $totalStudents || !$hasAnalytics) {
                                            $tagClass .= 'in-progress';
                                        } else {
                                            $tagClass .= 'completed';
                                        }
                                        ?>
                                        <span class="<?php echo $tagClass; ?>">
                                            <?php echo htmlspecialchars($subject['subject_name']); ?>
                                        </span>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endforeach; ?>
    </div>

    <?php else: ?>
    <div class="alert alert-warning text-center">
        当前没有可用的项目，请先在系统设置中创建并启用一个项目。
    </div>
    <?php endif; ?>
</div>

<script>
// 每60秒自动刷新一次
setInterval(function() {
    var container = document.querySelector('.auto-refresh');
    if (container) {
        fetch(window.location.href)
            .then(response => response.text())
            .then(html => {
                var parser = new DOMParser();
                var doc = parser.parseFromString(html, 'text/html');
                var newContent = doc.querySelector('.auto-refresh');
                if (newContent) {
                    container.innerHTML = newContent.innerHTML;
                }
            })
            .catch(error => console.error('自动刷新失败:', error));
    }
}, 60000);

// 确保dashboard页面加载时立即应用移动设备样式
(function() {
    // 检测是否为移动设备
    function isMobileDevice() {
        return window.innerWidth < 992 || sessionStorage.getItem('isMobileDevice') === 'true' || 
               /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
    }
    
    function applyMobileStyles() {
        if (isMobileDevice()) {
            document.body.classList.add('mobile-device');
            // 通知父窗口这是移动设备
            if (window.parent && window.parent !== window) {
                window.parent.postMessage({ type: 'mobile-device-detected' }, '*');
            }
        }
    }
    
    // 立即执行
    applyMobileStyles();
    
    // DOMContentLoaded时再次执行
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', applyMobileStyles);
    }
    
    // 窗口大小变化时再次检查
    window.addEventListener('resize', applyMobileStyles);
})();
</script> 