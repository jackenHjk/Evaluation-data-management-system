<?php
/**
 * 文件名: index.php
 * 功能描述: 成绩统计分析系统主入口文件
 *
 * 该文件负责:
 * 1. 系统会话初始化和安全配置
 * 2. 检查系统安装状态，未安装则重定向到安装页面
 * 3. 验证用户登录状态，未登录则尝试自动登录或重定向到登录页面
 * 4. 为已登录用户提供系统主界面
 *
 * API调用说明:
 * - 调用 auto_login API进行自动登录验证 (api/index.php?route=auto_login)
 *
 * 关联文件:
 * - login.php: 系统登录页面
 * - api/index.php: API入口文件
 * - config/install.lock: 安装状态锁定文件
 * - assets/: 静态资源目录
 */

// 在启动会话前设置cookie参数
session_set_cookie_params([
    'lifetime' => 1800,
    'path' => '/',
    'secure' => true,
    'httponly' => true,
    'samesite' => 'Strict'
]);

session_start();

// 定义调试模式（生产环境应设置为false）
define('DEBUG_MODE', false);

// 设置错误报告
if (DEBUG_MODE) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// 检查是否已安装
if (!file_exists(__DIR__ . '/config/install.lock')) {
    header('Location: install/');
    exit;
}

// 检查是否已登录，如果未登录则尝试自动登录
if (!isset($_SESSION['user_id'])) {
    // 在检查session之前，先尝试从localStorage恢复会话
    // 这样可以避免不必要的登录页面跳转
    ?><!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>自动登录中 - 测评数据管理系统</title>
    <link href="assets/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100vh;
            background: #f5f7fa;
        }
        .loading-container {
            text-align: center;
        }
        .spinner-border {
            width: 3rem;
            height: 3rem;
            color: #0066CC;
        }
    </style>
</head>
<body>
    <div class="loading-container">
        <div class="spinner-border mb-3" role="status">
            <span class="visually-hidden">Loading...</span>
        </div>
        <h4>正在自动登录，请稍候...</h4>
    </div>

    <script src="assets/js/jquery.min.js"></script>
    <script>
    // 检测是否为移动设备
    function isMobileDevice() {
        return window.innerWidth < 992 || /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
    }

    // 设置移动设备标记，用于登录成功后保持移动样式
    function setDeviceFlag() {
        if (isMobileDevice()) {
            sessionStorage.setItem('isMobileDevice', 'true');
        } else {
            sessionStorage.removeItem('isMobileDevice');
        }
    }

    // 立即设置设备标记
    setDeviceFlag();

    // 立即执行自动登录逻辑
    (function() {
        // 从cookie中获取登录信息
        function getCookie(name) {
            const value = `; ${document.cookie}`;
            const parts = value.split(`; ${name}=`);
            if (parts.length === 2) return parts.pop().split(';').shift();
            return null;
        }

        const token = getCookie('login_token');
        const username = getCookie('username');
        
        // 获取或生成设备指纹
        function generateDeviceFingerprint() {
            // 收集设备信息
            const screenInfo = `${window.screen.width}x${window.screen.height}x${window.screen.colorDepth}`;
            const timezone = new Date().getTimezoneOffset();
            const language = navigator.language || navigator.userLanguage || '';
            const platform = navigator.platform || '';
            const userAgent = navigator.userAgent;
            const plugins = Array.from(navigator.plugins || []).map(p => p.name).join(',');
            
            // 将所有信息组合起来
            const rawFingerprint = `${screenInfo}|${timezone}|${language}|${platform}|${userAgent}|${plugins}`;
            
            // 使用简单的哈希函数生成指纹
            let hash = 0;
            for (let i = 0; i < rawFingerprint.length; i++) {
                const char = rawFingerprint.charCodeAt(i);
                hash = ((hash << 5) - hash) + char;
                hash = hash & hash; // 转换为32位整数
            }
            
            // 转换为16进制字符串
            return Math.abs(hash).toString(16);
        }
        
        // 从localStorage获取设备指纹，如果没有则生成并保存
        let deviceFingerprint = localStorage.getItem('device_fingerprint');
        if (!deviceFingerprint) {
            deviceFingerprint = generateDeviceFingerprint();
            localStorage.setItem('device_fingerprint', deviceFingerprint);
        }

        if (token && username) {
            // 使用cookie中的token进行自动登录验证
            fetch('api/index.php?route=auto_login', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    token: token,
                    username: username,
                    device_fingerprint: deviceFingerprint
                }),
                credentials: 'include'
            })
            .then(response => response.json())
            .then(data => {
                if (!data.success) {
                    // 清除cookie
                    document.cookie = 'login_token=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;';
                    document.cookie = 'username=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;';
                    window.location.href = 'login.php';
                } else {
                    // 如果返回了新的token，更新cookie
                    if (data.login_token) {
                        const expires = new Date();
                        expires.setMinutes(expires.getMinutes() + 30);
                        document.cookie = `login_token=${data.login_token}; expires=${expires.toUTCString()}; path=/; secure; samesite=Strict`;
                    }
                    // 登录成功，刷新页面以加载完整的首页，并传递设备标记
                    window.location.href = 'index.php?device_detected=1';
                }
            })
            .catch(() => {
                // 清除cookie
                document.cookie = 'login_token=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;';
                document.cookie = 'username=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;';
                window.location.href = 'login.php';
            });
        } else {
            window.location.href = 'login.php';
        }
    })();
    </script>
</body>
</html>
    <?php
    exit;
}

// 获取用户角色
$userRole = $_SESSION['role'] ?? '';

// 这里是已登录用户看到的首页内容
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>测评数据管理系统</title>
    <!-- <link rel="icon" type="image/svg+xml" href="favicon.svg"> -->
    <link rel="alternate icon" href="favicon.ico">
    <link href="assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/all.min.css" rel="stylesheet">
    <link href="assets/css/sweetalert2.min.css" rel="stylesheet">
    <script src="assets/js/jquery.min.js"></script>
    <script src="assets/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/sweetalert2.min.js"></script>
    

    <!-- 预先应用移动设备样式，避免闪烁 -->
    <script>
        // 预检测移动设备并立即添加样式类
        (function() {
            function isMobileDevice() {
                return window.innerWidth < 992 || /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
            }
            
            if (isMobileDevice() || sessionStorage.getItem('isMobileDevice') === 'true') {
                document.documentElement.classList.add('mobile-detected');
                document.addEventListener('DOMContentLoaded', function() {
                    document.body.classList.add('mobile-device');
                });
            }
        })();
    </script>
    <style>
        /* 全局水印样式 */
        .watermark {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: 9999;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }
        .watermark-grid {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            display: grid;
            grid-template-columns: repeat(6, 1fr);
            grid-template-rows: repeat(6, 1fr);
            gap: 20px;
            padding: 20px;
        }
        .watermark-item {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 10px;
        }
        .watermark-text {
            transform: rotate(-30deg);
            font-size: 20px;
            color: rgba(0, 0, 0, 0.08);
            white-space: nowrap;
            user-select: none;
        }
        
        /* 移除可能干扰Bootstrap折叠功能的样式 */
        
        .navbar {
            padding: 1rem 0;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        
        /* 移除模态框中的水印 */
        .modal-content {
            background-image: none !important;
        }
        
        /* 修复修改密码开关按钮样式 */
        .form-switch .form-check-input {
            margin-left: 0;
            float: none;
            cursor: pointer;
            position: relative;
            z-index: 2;
        }
        
        /* 自定义开关样式 */
        .password-switch-container {
            position: relative;
            display: flex;
            align-items: center;
            cursor: pointer;
            justify-content: flex-start;
            margin-left: 0;
            padding-left: 0;
        }
        
        .password-switch-label {
            cursor: pointer;
            user-select: none;
            margin-left: 0;
        }
        
        .form-check.form-switch {
            padding-left: 0;
            margin-left: 50px;
        }
        
        .navbar-brand {
            font-weight: 600;
            font-size: 1.3rem;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            transition: all 0.3s ease;
        }
        .navbar-brand:hover {
            background: rgba(255, 255, 255, 0.1);
        }
        .nav-link {
            padding: 0.5rem 1rem !important;
            margin: 0 0.2rem;
            border-radius: 6px;
            transition: all 0.3s ease;
            position: relative;
            font-weight: 500;
        }
        .nav-link:hover, .nav-link.active {
            background: rgba(255, 255, 255, 0.15);
            transform: translateY(-1px);
        }
        .nav-link.active::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 20px;
            height: 2px;
            background: rgba(255, 255, 255, 0.8);
            border-radius: 2px;
        }
        
        /* 移动设备优化样式 */
        @media (max-width: 991.98px) {
            .navbar-collapse {
                max-height: calc(100vh - 60px);
                overflow-y: auto;
                padding-bottom: 70px; /* 增加底部填充，确保菜单内容完全可见 */
            }
            
            .navbar-collapse .navbar-nav {
                padding: 0.5rem 0;
            }
            
            .nav-link {
                padding: 0.75rem 1rem !important;
                margin: 0.1rem 0;
            }
            
            .dropdown-menu {
                border: none;
                background-color: rgba(0, 0, 0, 0.05);
                padding: 0;
                margin: 0;
            }
            
            .dropdown-item {
                padding: 0.75rem 1.5rem;
            }
            
            /* 修正移动端上的用户信息和注销按钮 */
            .navbar-nav.ms-auto {
                flex-direction: row;
                flex-wrap: wrap;
                justify-content: center;
                padding: 0.5rem;
                gap: 0.5rem;
                margin-bottom: 15px; /* 增加底部边距 */
            }
            
            .navbar-nav.ms-auto .nav-item {
                width: auto;
            }
            
            /* 确保移动端上菜单项可点击区域足够大 */
            .nav-item {
                width: 100%;
            }
            
            /* 移动设备上的下拉菜单样式 */
            .mobile-device .dropdown-menu {
                display: none;
                position: static !important;
                transform: none !important;
                float: none;
                width: 100%;
            }
            
            .mobile-device .dropdown-menu.show {
                display: block;
            }
            
            /* 注销提示在移动端显示 */
            .mobile-device .logout-tip,
            .logout-tip.mobile-tip {
                left: 50%;
                transform: translateX(-50%);
                z-index: 1050; /* 提高z-index确保可见 */
                position: absolute; /* 使用绝对定位 */
                bottom: auto; /* 取消底部定位 */
                top: -70px; /* 放在注销按钮上方，增加距离 */
                width: auto; /* 自动宽度适应内容 */
                min-width: 200px; /* 最小宽度 */
                max-width: 280px; /* 最大宽度，避免横向滚动 */
                padding: 10px 15px 12px; /* 适当内边距，底部增加一点空间 */
                font-size: 14px; /* 增大字体 */
                box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3); /* 增强阴影 */
                animation: fadeIn 0.3s ease; /* 添加动画 */
                background-color: #ffce3a; /* 确保背景色一致 */
                border-left: 4px solid #dc3545; /* 添加左侧边框增强视觉效果 */
                white-space: normal; /* 允许文本换行 */
                text-align: center; /* 文本居中 */
                line-height: 1.4; /* 增加行高提高可读性 */
                border-radius: 8px; /* 确保圆角 */
            }
            
            @keyframes fadeIn {
                from {
                    opacity: 0;
                }
                to {
                    opacity: 1;
                }
            }
            
            .mobile-device .logout-tip:after,
            .logout-tip.mobile-tip:after {
                content: '';
                position: absolute;
                bottom: -8px; /* 箭头位于底部 */
                left: 50%;
                transform: translateX(-50%);
                width: 0;
                height: 0;
                border-left: 8px solid transparent;
                border-right: 8px solid transparent;
                border-top: 8px solid #ffce3a; /* 箭头朝下 */
            }
            
            /* 移除原来的before伪元素 */
            .mobile-device .logout-tip:before,
            .logout-tip.mobile-tip:before {
                display: none;
            }
            
            .mobile-device .logout-tip i,
            .logout-tip.mobile-tip i {
                font-size: 18px; /* 增大图标 */
                margin-right: 10px;
                margin-bottom: 2px; /* 微调垂直对齐 */
            }
            
            /* 确保移动设备上没有横向滚动 */
            .mobile-device {
                overflow-x: hidden;
            }
        }
        
        /* 用户信息区域样式 - 全局样式，确保在所有模块页面中保持一致 */
        /* 使用更强的选择器和更高的优先级 */
        body .navbar .navbar-nav .nav-item .user-info,
        body #main-navbar .navbar-nav .nav-item .user-info,
        body header .navbar .navbar-nav .nav-item .user-info,
        body .user-info {
            display: flex !important;
            align-items: center !important;
            padding: 0.5rem 1rem !important;
            background: rgba(255, 255, 255, 0.1) !important;
            border-radius: 20px !important;
            margin-right: 1rem !important;
            height: 40px !important;  /* 固定高度 */
            cursor: pointer !important;
            transition: all 0.3s ease !important;
        }
        body .navbar .navbar-nav .nav-item .user-info:hover,
        body #main-navbar .navbar-nav .nav-item .user-info:hover,
        body header .navbar .navbar-nav .nav-item .user-info:hover,
        body .user-info:hover {
            background: rgba(255, 255, 255, 0.2) !important;
        }
        body .navbar .navbar-nav .nav-item .user-info .user-avatar,
        body #main-navbar .navbar-nav .nav-item .user-info .user-avatar,
        body header .navbar .navbar-nav .nav-item .user-info .user-avatar,
        body .user-info .user-avatar {
            width: 28px !important;  /* 稍微调小头像 */
            height: 28px !important;
            background: rgba(255, 255, 255, 0.2) !important;
            border-radius: 50% !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            margin-right: 0.5rem !important;
        }
        body .navbar .navbar-nav .nav-item .user-info .user-name,
        body #main-navbar .navbar-nav .nav-item .user-info .user-name,
        body header .navbar .navbar-nav .nav-item .user-info .user-name,
        body .user-info .user-name {
            color: white !important;
            font-weight: 500 !important;
            margin: 0 !important;
            line-height: 1 !important;  /* 调整行高 */
            font-size: 14px !important;
        }

        /* 确保图标在用户头像中居中 */
        body .user-info .user-avatar i,
        body .navbar .navbar-nav .nav-item .user-info .user-avatar i,
        body #main-navbar .navbar-nav .nav-item .user-info .user-avatar i,
        body header .navbar .navbar-nav .nav-item .user-info .user-avatar i {
            font-size: 14px !important;
            color: white !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            width: 100% !important;
            height: 100% !important;
            margin: 0 !important;
            padding: 0 !important;
            position: relative !important;
            top: 0 !important;
            left: 0 !important;
        }
        #logout {
            padding: 0.5rem 1.2rem;
            background: rgba(255, 255, 255, 0.15);
            border-radius: 20px;
            transition: all 0.3s ease;
            height: 40px;  /* 固定高度，与user-info相同 */
            display: flex;
            align-items: center;
            color: white;
            text-decoration: none;
        }
        #logout:hover {
            background: rgba(255, 255, 255, 0.25);
            transform: translateY(-1px);
        }
        .error-icon {
            position: relative;
            overflow: hidden;
        }
        .error-icon .fa-ban {
            opacity: 0.9;
        }
        .error-icon .fa-user-shield {
            z-index: 2;
        }
        /* 添加下拉菜单样式 */
        .dropdown-menu {
            margin-top: 0;
            border: none;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            border-radius: 8px;
            padding: 8px 0;
        }
        .dropdown-item {
            padding: 8px 16px;
            color: #1D1D1F;
            transition: all 0.2s ease;
        }
        .dropdown-item:hover, .dropdown-item.active {
            background-color: rgba(0, 102, 204, 0.1);
            color: #0066CC;
        }
        .dropdown-item i {
            width: 20px;
            text-align: center;
        }
        /* 修改下拉箭头的样式 */
        .dropdown-toggle::after {
            margin-left: 0.5em;
            vertical-align: middle;
        }
        /* 下拉菜单动画效果 */
        .dropdown-menu {
            animation: fadeIn 0.2s ease;
            transform-origin: top;
        }
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        /* 调整导航栏右侧对齐 */
        .navbar-nav.ms-auto {
            display: flex;
            align-items: center;
            gap: 0.5rem;  /* 统一间距 */
        }
        .navbar-nav.ms-auto .nav-item {
            display: flex;
            align-items: center;
        }

        /* 添加注销提示样式 */
        .logout-tip {
            position: absolute;
            top: 60px;  /* 刚好在注销按钮下方 */
            left: -110px;  /* 左对齐而不是右对齐 */
            background-color: #ffce3a;
            color: #333;
            padding: 8px 12px;  /* 增加内边距，提高可点击区域 */
            border-radius: 8px;
            font-size: 13px;  /* 稍微增大字体 */
            z-index: 1000;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.2);
            display: flex;
            align-items: center;
            width: auto;
            white-space: nowrap;
            transition: all 0.3s ease;  /* 添加过渡效果 */
        }
        .logout-tip:before {
            content: '';
            position: absolute;
            top: -8px;
            left: 150px;  /* 改为左侧显示箭头 */
            width: 0;
            height: 0;
            border-left: 8px solid transparent;
            border-right: 8px solid transparent;
            border-bottom: 8px solid #ffce3a;
        }
        .logout-tip i {
            margin-right: 8px;
            color: #dc3545;
            font-size: 16px;
            display: inline-block;
            vertical-align: middle;
        }
        
        .logout-tip span {
            display: inline-block;
            vertical-align: middle;
        }
        /* 注销按钮激活状态 */
        #logout.disabled {
            pointer-events: none;
            cursor: not-allowed;
        }
        .notification-link {
            position: relative;
            padding: 8px 12px;
            margin-right: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            transition: all 0.3s ease;
        }
        
        .notification-link:hover {
            background: rgba(255, 255, 255, 0.15);
            border-radius: 50%;
        }
        
        .notification-badge {
            position: absolute;
            top: 0;
            right: 0;
            background-color: #dc3545;
            color: white;
            font-size: 10px;
            font-weight: bold;
            min-width: 16px;
            height: 16px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0 4px;
        }
        
        /* 页脚样式 */
        .footer {
            margin-top: 2rem;
            border-top: 1px solid rgba(0, 0, 0, 0.05);
            color: #6c757d;
            font-size: 0.85rem;
        }
        
        .footer .text-muted {
            opacity: 0.8;
        }
        
        /* 确保页脚在内容少时也位于底部 */
        @media (min-height: 700px) {
            body {
                display: flex;
                flex-direction: column;
                min-height: 100vh;
            }
            
            .container:not(.footer .container) {
                flex: 1 0 auto;
            }
            
            .footer {
                flex-shrink: 0;
                margin-top: auto;
            }
        }
    </style>
</head>
<body>
    <!-- 全局水印容器 -->
    <div class="watermark">
        <div class="watermark-grid" id="watermarkGrid"></div>
    </div>

    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="#" id="home-link">
                <i class="fas fa-chart-line me-2"></i>
                测评数据管理系统
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav">
                    <?php if (in_array($_SESSION['role'], ['admin', 'teaching'])): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="settingsDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-cog me-1"></i>系统设置
                        </a>
                        <ul class="dropdown-menu" aria-labelledby="settingsDropdown">
                            <li>
                                <a class="dropdown-item" href="#" data-module="project_settings">
                                    <i class="fas fa-tasks me-1"></i>项目管理
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="#" data-module="class_settings">
                                    <i class="fas fa-chalkboard me-1"></i>班级设置
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="#" data-module="subject_settings">
                                    <i class="fas fa-book me-1"></i>学科管理
                                </a>
                            </li>
                            <?php if ($userRole === 'admin'): ?>
                            <li>
                                <a class="dropdown-item" href="#" data-module="user_settings">
                                    <i class="fas fa-users-cog me-1"></i>账号管理
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="#" data-module="operation_logs">
                                    <i class="fas fa-history me-1"></i>日志管理
                                </a>
                            </li>
                            <?php endif; ?>
                        </ul>
                    </li>
                    <?php endif; ?>

                    <?php if ($userRole !== 'marker'): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="#" data-module="students">
                            <i class="fas fa-user-graduate me-1"></i>学生信息录入
                        </a>
                    </li>
                    <?php endif; ?>

                    <?php if ($userRole !== 'headteacher'): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="#" data-module="scores">
                            <i class="fas fa-edit me-1"></i>测评数据录入
                        </a>
                    </li>

                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="analyticsDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-chart-bar me-1"></i>数据看板
                        </a>
                        <ul class="dropdown-menu" aria-labelledby="analyticsDropdown">
                            <li>
                                <a class="dropdown-item" href="#" data-module="class_analytics">
                                    <i class="fas fa-chalkboard-teacher me-1"></i>看班级
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="#" data-module="grade_analytics">
                                    <i class="fas fa-school me-1"></i>看年级
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="#" data-module="chinese_math_analytics">
                                    <i class="fas fa-book me-1"></i>看语数
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="#" data-module="comprehensive_analytics">
                                    <i class="fas fa-chart-pie me-1"></i>全科统计
                                </a>
                            </li>
                            <?php if ($userRole !== 'marker'): ?>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <a class="dropdown-item" href="#" data-module="download">
                                    <i class="fas fa-download me-1"></i>数据下载
                                </a>
                            </li>
                            <?php endif; ?>
                        </ul>
                    </li>
                    <?php endif; ?>
                    
                    <li class="nav-item">
                        <a class="nav-link" href="https://www.yuque.com/jacken/iuhvve/tb81pudrf6aany6z?singleDoc#" target="_blank">
                            <i class="fa fa-question-circle me-1"></i>帮助
                        </a>
                    </li>
                </ul>
                <ul class="navbar-nav ms-auto">
                <li class="nav-item" style="position: relative;">
                        <a class="nav-link notification-link" href="#" id="notificationBtn" title="消息通知">
                            <i class="fas fa-bell"></i>
                            <span class="notification-badge" id="notificationBadge" style="display: none;">0</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <?php if (in_array($_SESSION['role'], ['admin', 'teaching', 'marker'])): ?>
                        <div class="user-info" id="edit-user-info" title="编辑用户信息">
                        <?php else: ?>
                        <div class="user-info">
                        <?php endif; ?>
                            <div class="user-avatar">
                                <i class="fas fa-user"></i>
                            </div>
                            <span class="user-name"><?php
                                echo htmlspecialchars($_SESSION['real_name'] ?? '未知用户');
                            ?></span>
                        </div>
                    </li>
                    <li class="nav-item" style="position: relative;">
                        <a class="nav-link" href="#" id="logout">
                            <i class="fas fa-sign-out-alt me-1"></i>注销
                        </a>
                        <!-- 注销提示 -->
                        <div class="logout-tip" id="logout-tip">
                            <i class="fas fa-exclamation-triangle"></i>
                            <span>为避免账号被占用，请先"注销"再关闭。</span>
                        </div>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div id="moduleContent">
            <!-- 模块内容将通过 AJAX 加载到这里 -->
        </div>
    </div>
    
    <!-- 页脚部分 -->
    <footer class="footer py-2 bg-light">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center flex-wrap">
                <div class="small text-muted py-1">测评数据管理系统 v1.6.0</div>
                <div class="small text-muted py-1">&copy; 2025 测评数据管理系统 by Jacken 版权所有</div>
            </div>
        </div>
    </footer>

    <!-- 用户信息编辑模态框 -->
    <div class="modal fade" id="userEditModal" tabindex="-1" aria-labelledby="userEditModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="userEditModalLabel">
                        <?php if ($_SESSION['role'] === 'marker'): ?>
                            修改密码
                        <?php else: ?>
                            编辑用户信息
                        <?php endif; ?>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="userEditForm">
                        <div class="mb-3">
                            <label for="edit-username" class="form-label">用户名</label>
                            <input type="text" class="form-control" id="edit-username" value="<?php echo htmlspecialchars($_SESSION['username'] ?? ''); ?>" readonly>
                        </div>
                        <div class="mb-3">
                            <label for="edit-real-name" class="form-label">真实姓名</label>
                            <input type="text" class="form-control" id="edit-real-name" value="<?php echo htmlspecialchars($_SESSION['real_name'] ?? ''); ?>">
                        </div>
                        <div class="mb-3 d-flex align-items-center password-switch-container">
                            <div class="form-check form-switch me-2">
                                <input class="form-check-input" type="checkbox" id="change-password-switch" role="switch">
                            </div>
                            <label class="password-switch-label" for="change-password-switch">修改密码</label>
                        </div>
                        <div id="password-fields" style="display: none !important;">
                            <div class="mb-3">
                                <label for="edit-old-password" class="form-label">旧密码</label>
                                <input type="password" class="form-control" id="edit-old-password" placeholder="请输入旧密码">
                            </div>
                            <div class="mb-3">
                                <label for="edit-new-password" class="form-label">新密码</label>
                                <input type="password" class="form-control" id="edit-new-password" placeholder="请输入新密码">
                            </div>
                            <div class="mb-3">
                                <label for="edit-confirm-password" class="form-label">确认新密码</label>
                                <input type="password" class="form-control" id="edit-confirm-password" placeholder="请再次输入新密码">
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
                    <button type="button" class="btn btn-primary" id="saveUserInfo">保存</button>
                </div>
            </div>
        </div>
    </div>

    <!-- 阅卷老师初始密码强制修改模态框 -->
    <div class="modal fade" id="forceChangePasswordModal" tabindex="-1" aria-labelledby="forceChangePasswordModalLabel" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title" id="forceChangePasswordModalLabel">
                        <i class="fas fa-exclamation-triangle me-2"></i>安全提示：请修改初始密码
                    </h5>
                    <!-- 不提供关闭按钮 -->
                </div>
                <div class="modal-body">
                    <div class="alert alert-info mb-3">
                        <i class="fas fa-info-circle me-2"></i>
                        当前为初始密码，为了账号安全，请立即修改密码。
                    </div>
                    <form id="forceChangePasswordForm">
                        <div class="mb-3">
                            <label for="force-username" class="form-label">用户名</label>
                            <input type="text" class="form-control" id="force-username" value="<?php echo htmlspecialchars($_SESSION['username'] ?? ''); ?>" readonly>
                        </div>
                        <div class="mb-3">
                            <label for="force-old-password" class="form-label">旧密码</label>
                            <input type="password" class="form-control" id="force-old-password" placeholder="请输入旧密码" value="123456" readonly>
                            <!--<div class="form-text">初始密码为：123456</div>-->
                        </div>
                        <div class="mb-3">
                            <label for="force-new-password" class="form-label">新密码</label>
                            <input type="password" class="form-control" id="force-new-password" placeholder="请输入新密码">
                            <div class="form-text">密码长度至少6位，建议包含字母和数字</div>
                        </div>
                        <div class="mb-3">
                            <label for="force-confirm-password" class="form-label">确认新密码</label>
                            <input type="password" class="form-control" id="force-confirm-password" placeholder="请再次输入新密码">
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-danger" id="forceLogout">
                        <i class="fas fa-sign-out-alt me-1"></i>注销
                    </button>
                    <button type="button" class="btn btn-primary" id="forceSavePassword">
                        <i class="fas fa-save me-1"></i>保存新密码
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="assets/js/jquery.min.js"></script>
    <script src="assets/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/sweetalert2.all.min.js"></script>
    <script>
        // 检测是否为微信浏览器
        function isWechatBrowser() {
            const ua = navigator.userAgent.toLowerCase();
            return ua.indexOf('micromessenger') !== -1;
        }

        // 检测是否为移动设备
        function isMobileDevice() {
            // 优先考虑会话存储的设备类型标记
            if (sessionStorage.getItem('isMobileDevice') === 'true') {
                return true;
            }
            return window.innerWidth < 992 || /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
        }

        // 根据设备类型自动调整导航栏状态
        function adjustNavbarForDevice() {
            if (isMobileDevice()) {
                // 在移动设备上自动收起导航栏，但不干扰用户手动点击功能
                // 仅在初始化阶段或窗口大小改变时生效
                if (!window.navbarInitialized) {
                    $('.navbar-collapse').removeClass('show');
                    window.navbarInitialized = true;
                }
                
                // 添加移动设备特定样式
                $('body').addClass('mobile-device');
                // 保存设备类型到会话存储
                sessionStorage.setItem('isMobileDevice', 'true');
            } else {
                // 在桌面设备上展开导航栏
                if (window.innerWidth >= 992) {
                    $('.navbar-collapse').addClass('show');
                }
                $('body').removeClass('mobile-device');
                // 清除会话存储中的设备类型
                sessionStorage.removeItem('isMobileDevice');
            }
        }

        // 密码开关切换函数
        function togglePasswordFields() {
            const isChecked = $('#change-password-switch').prop('checked');
            console.log('Toggle password fields:', isChecked);
            if (isChecked) {
                document.getElementById('password-fields').style.display = 'block';
            } else {
                document.getElementById('password-fields').style.display = 'none';
                $('#edit-old-password, #edit-new-password, #edit-confirm-password').val('');
            }
        }
        
        // 确保密码字段默认是隐藏的
        function ensurePasswordFieldsHidden() {
            // 非阅卷老师角色确保密码开关默认关闭
            if ('<?php echo $_SESSION['role']; ?>' !== 'marker') {
                $('#change-password-switch').prop('checked', false);
                $('#password-fields').hide();
            }
        }

        // 在DOM完全加载前预先执行一些关键函数
        document.addEventListener('DOMContentLoaded', function() {
            // 立即检测设备并应用样式
            if (isMobileDevice()) {
                document.body.classList.add('mobile-device');
            }
        });

        $(document).ready(function() {
            // 确保密码字段默认是隐藏的
            ensurePasswordFieldsHidden();
            
            // 检查阅卷老师初始密码状态
            function checkMarkerInitialPassword() {
                const userRole = '<?php echo $_SESSION['role']; ?>';
                
                // 仅当用户是阅卷老师时进行检查
                if (userRole === 'marker') {
                    console.log('正在检查阅卷老师初始密码状态...');
                    $.ajax({
                        url: 'api/index.php?route=user/check_initial_password',
                        method: 'GET',
                        dataType: 'json',
                        cache: false,
                        success: function(response) {
                            console.log('密码检查响应:', response);
                            if (response.success && response.data && response.data.is_initial_password) {
                                // 显示强制修改密码模态框
                                $('#forceChangePasswordModal').modal('show');
                            }
                        },
                        error: function(xhr, status, error) {
                            console.error('检查密码状态失败:', status, error);
                            console.error('响应内容:', xhr.responseText);
                        }
                    });
                }
            }
            
            // 页面加载完成后立即检查阅卷老师初始密码状态
            checkMarkerInitialPassword();
            
            // 处理强制修改密码表单提交
            $('#forceSavePassword').on('click', function() {
                const newPassword = $('#force-new-password').val();
                const confirmPassword = $('#force-confirm-password').val();
                
                // 验证新密码
                if (!newPassword) {
                    Swal.fire({
                        icon: 'error',
                        title: '错误',
                        text: '请输入新密码'
                    });
                    return;
                }
                
                // 验证密码长度
                if (newPassword.length < 6) {
                    Swal.fire({
                        icon: 'error',
                        title: '错误',
                        text: '新密码长度不能少于6位'
                    });
                    return;
                }
                
                // 验证两次密码是否一致
                if (newPassword !== confirmPassword) {
                    Swal.fire({
                        icon: 'error',
                        title: '错误',
                        text: '两次输入的新密码不一致'
                    });
                    return;
                }
                
                // 验证新密码不能和初始密码相同
                if (newPassword === '123456') {
                    Swal.fire({
                        icon: 'error',
                        title: '错误',
                        text: '新密码不能与初始密码相同'
                    });
                    return;
                }
                
                // 显示加载提示
                Swal.fire({
                    title: '处理中',
                    text: '正在修改密码...',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });
                
                // 发送修改密码请求
                $.ajax({
                    url: 'api/index.php?route=user/update_profile',
                    method: 'POST',
                    dataType: 'json',
                    data: {
                        old_password: '123456',
                        new_password: newPassword
                    },
                    success: function(response) {
                        console.log('密码修改响应:', response);
                        if (response.success) {
                            Swal.fire({
                                icon: 'success',
                                title: '成功',
                                text: '密码修改成功！'
                            }).then(() => {
                                // 关闭强制修改密码模态框
                                $('#forceChangePasswordModal').modal('hide');
                                
                                // 清空密码字段
                                $('#force-new-password').val('');
                                $('#force-confirm-password').val('');
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: '错误',
                                text: response.error || '密码修改失败，请稍后重试'
                            });
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('密码修改请求失败:', status, error);
                        console.error('响应内容:', xhr.responseText);
                        
                        Swal.fire({
                            icon: 'error',
                            title: '错误',
                            text: '服务器错误，请稍后重试'
                        });
                    }
                });
            });
            
            // 处理强制注销按钮
            $('#forceLogout').on('click', function() {
                Swal.fire({
                    title: '确认注销',
                    text: '您确定要注销登录吗？',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#dc3545',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: '确认注销',
                    cancelButtonText: '取消'
                }).then((result) => {
                    if (result.isConfirmed) {
                        // 显示加载提示
                        Swal.fire({
                            title: '正在注销...',
                            text: '请稍候',
                            allowOutsideClick: false,
                            didOpen: () => {
                                Swal.showLoading();
                            }
                        });
                        
                        // 发送注销请求
                        $.ajax({
                            url: 'api/index.php?route=logout',
                            method: 'POST',
                            success: function(response) {
                                if (response.success) {
                                    window.location.href = 'login.php';
                                } else {
                                    Swal.fire({
                                        icon: 'error',
                                        title: '注销失败',
                                        text: '请稍后重试'
                                    });
                                }
                            },
                            error: function() {
                                Swal.fire({
                                    icon: 'error',
                                    title: '注销失败',
                                    text: '请稍后重试'
                                });
                            }
                        });
                    }
                });
            });
            
            // 确保即使是自动登录后的页面刷新也能立即应用移动设备样式
            setTimeout(function() {
                // 页面加载完成后立即调整导航栏状态适应设备
                adjustNavbarForDevice();
                
                // 初始化导航栏下拉菜单行为
                initNavbarBehavior();
                
                // 确保折叠按钮能正常工作 - 显式绑定点击事件
                $('.navbar-toggler').off('click').on('click', function() {
                    // 可能原生的data-bs-toggle被覆盖，手动切换
                    $('#navbarNav').collapse('toggle');
                    return false; // 阻止默认行为
                });
                
                // 确保Bootstrap组件被正确初始化
                if (typeof bootstrap !== 'undefined') {
                    // 重新初始化所有工具提示和弹出框
                    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
                    tooltipTriggerList.map(function (tooltipTriggerEl) {
                        return new bootstrap.Tooltip(tooltipTriggerEl);
                    });
                    
                    // 确保折叠组件正确初始化
                    var navbarCollapse = document.getElementById('navbarNav');
                    if (navbarCollapse) {
                        new bootstrap.Collapse(navbarCollapse, {
                            toggle: false
                        });
                    }
                }
            }, 0);
            
            // 在窗口大小变化时重新调整导航栏
            $(window).on('resize', function() {
                adjustNavbarForDevice();
                initNavbarBehavior();
            });
            
            // 监听来自iframe的消息
            window.addEventListener('message', function(event) {
                // 安全检查
                if (event.data && event.data.type === 'mobile-device-detected') {
                    // 收到移动设备检测消息，立即应用样式
                    adjustNavbarForDevice();
                    initNavbarBehavior();
                }
            });
            
            // 初始化密码开关
            $('#change-password-switch').prop('checked', false);
            document.getElementById('password-fields').style.display = 'none';

            // 绑定密码开关事件
            document.getElementById('change-password-switch').addEventListener('change', togglePasswordFields);
            document.getElementById('change-password-switch').addEventListener('click', function() {
                setTimeout(togglePasswordFields, 0);
            });
            
            // 初始化导航栏下拉菜单行为
            function initNavbarBehavior() {
                // 清除之前可能的事件绑定
                $('.dropdown-toggle').off('mouseenter mouseleave click');
                $('.dropdown-menu').off('mouseenter mouseleave');
                $(document).off('click.dropdown-close');
                
                // 优化注销提示显示
                if (isMobileDevice()) {
                    // 移动设备上，将注销提示重新定位到注销按钮上方
                    $('#logout-tip').addClass('mobile-tip');
                    
                    // 移动设备上使用点击切换下拉菜单
                    $('.dropdown-toggle').on('click', function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        const $this = $(this);
                        const $dropdown = $this.next('.dropdown-menu');
                        
                        // 关闭其他打开的下拉菜单
                        $('.dropdown-menu.show').not($dropdown).removeClass('show');
                        
                        // 切换当前下拉菜单
                        $dropdown.toggleClass('show');
                    });
                    
                    // 点击下拉菜单外部区域关闭菜单
                    $(document).on('click.dropdown-close', function(e) {
                        if (!$(e.target).closest('.dropdown').length) {
                            $('.dropdown-menu.show').removeClass('show');
                        }
                    });
                } else {
                    // 桌面设备上，恢复注销提示原始位置
                    $('#logout-tip').removeClass('mobile-tip');
                    
                    // 桌面设备上使用悬停展开下拉菜单
                    $('.dropdown').on('mouseenter', function() {
                        $(this).find('.dropdown-menu').addClass('show');
                    }).on('mouseleave', function() {
                        $(this).find('.dropdown-menu').removeClass('show');
                    });
                }
            }
            
            // 初始化导航栏行为
            initNavbarBehavior();
            
            // 当窗口大小改变时重新初始化导航栏行为
            $(window).on('resize', function() {
                initNavbarBehavior();
            });
            
            // 初始化导航栏收起功能
            function initCollapseNavbar() {
                // 为所有可能的导航项添加点击收起功能
                $('.navbar-nav .nav-link, .navbar-nav .dropdown-item').on('click', function() {
                    const navbarToggler = $('.navbar-toggler');
                    if (navbarToggler.is(':visible')) {
                        $('.navbar-collapse').collapse('hide');
                    }
                });
            }
            
            // 调用初始化导航栏收起功能
            initCollapseNavbar();
            
            // 获取URL参数中的module
            const urlParams = new URLSearchParams(window.location.search);
            const currentModule = urlParams.get('module') || 'dashboard';

            // 加载模块内容
            function loadModule(moduleName) {
                $.ajax({
                    url: `modules/${moduleName}.php`,
                    method: 'GET',
                    success: function(response) {
                        $('#moduleContent').html(response);
                        // 更新URL，但不刷新页面
                        history.pushState({module: moduleName}, '', `index.php?module=${moduleName}`);
                        // 更新导航栏活动状态
                        $('.nav-link').removeClass('active');
                        $('.dropdown-item').removeClass('active');
                        // 找到对应的菜单项并添加active类
                        const menuItem = $(`[data-module="${moduleName}"]`);
                        if (menuItem.length) {
                            menuItem.addClass('active');
                            // 如果是子菜单项，同时激活父菜单
                            if (menuItem.hasClass('dropdown-item')) {
                                menuItem.closest('.nav-item').find('.nav-link').addClass('active');
                                // 移除其他子菜单的active类
                                $('.dropdown-item').not(menuItem).removeClass('active');
                            }
                        }
                    },
                    error: function() {
                        $('#moduleContent').html('<div class="alert alert-danger">加载模块失败</div>');
                    }
                });
            }

            // 处理导航链接点击事件
            $('[data-module]').on('click', function(e) {
                e.preventDefault();
                const moduleName = $(this).data('module');
                loadModule(moduleName);
                
                // 在小屏幕模式下自动收起导航栏
                const navbarToggler = $('.navbar-toggler');
                if (navbarToggler.is(':visible')) {
                    $('.navbar-collapse').collapse('hide');
                }
            });

            // 处理浏览器前进/后退事件
            window.onpopstate = function(event) {
                if (event.state && event.state.module) {
                    loadModule(event.state.module);
                }
            };

            // 处理主页链接点击事件
            $('#home-link').on('click', function(e) {
                e.preventDefault();
                loadModule('dashboard');
                
                // 在小屏幕模式下自动收起导航栏
                const navbarToggler = $('.navbar-toggler');
                if (navbarToggler.is(':visible')) {
                    $('.navbar-collapse').collapse('hide');
                }
            });

            // 处理帮助链接点击
            $('a.nav-link[href*="yuque.com"]').on('click', function() {
                // 在小屏幕模式下自动收起导航栏
                const navbarToggler = $('.navbar-toggler');
                if (navbarToggler.is(':visible')) {
                    $('.navbar-collapse').collapse('hide');
                }
            });

            // 处理注销登录
            $('#logout').on('click', function(e) {
                e.preventDefault();
                
                // 在点击时立即给予视觉反馈
                const $logoutBtn = $(this);
                $logoutBtn.addClass('disabled').css('opacity', '0.7');
                
                // 显示加载提示
                let loadingToast;
                if (isMobileDevice()) {
                    loadingToast = Swal.fire({
                        title: '正在注销...',
                        text: '请稍候',
                        allowOutsideClick: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });
                }
                
                // 在小屏幕模式下自动收起导航栏
                const navbarToggler = $('.navbar-toggler');
                if (navbarToggler.is(':visible')) {
                    $('.navbar-collapse').collapse('hide');
                }
                
                // 使用较短的超时时间，提高响应速度
                $.ajax({
                    url: 'api/index.php?route=logout',
                    method: 'POST',
                    timeout: 5000, // 5秒超时
                    success: function(response) {
                        if (response.success) {
                            window.location.href = 'login.php';
                        } else {
                            // 恢复按钮状态
                            $logoutBtn.removeClass('disabled').css('opacity', '');
                            if (loadingToast) {
                                loadingToast.close();
                            }
                            Swal.fire({
                                icon: 'error',
                                title: '注销失败',
                                text: '请稍后重试'
                            });
                        }
                    },
                    error: function() {
                        // 恢复按钮状态
                        $logoutBtn.removeClass('disabled').css('opacity', '');
                        if (loadingToast) {
                            loadingToast.close();
                        }
                        Swal.fire({
                            icon: 'error',
                            title: '注销失败',
                            text: '请稍后重试'
                        });
                    }
                });
            });

            // 初始加载当前模块
            loadModule(currentModule);

            // 处理用户信息编辑
            $('#edit-user-info').on('click', function() {
                // 重置密码开关和密码字段
                $('#change-password-switch').prop('checked', false);  // 确保默认为关闭状态
                $('#password-fields').hide();
                $('#edit-old-password, #edit-new-password, #edit-confirm-password').val('');

                // 获取当前用户角色
                const userRole = '<?php echo $_SESSION['role']; ?>';
                
                // 如果是阅卷老师，则禁用真实姓名字段，并自动开启密码修改
                if (userRole === 'marker') {
                    $('#edit-real-name').prop('disabled', true);
                    // 阅卷老师默认开启密码修改，且不能关闭
                    $('#change-password-switch').prop('checked', true);
                    $('#password-fields').show();
                    // 让开关按钮可见但不可操作
                    $('.password-switch-container').addClass('disabled').css('opacity', '0.7');
                } else {
                    // 其他角色保持正常
                    $('#edit-real-name').prop('disabled', false);
                    $('#change-password-switch').prop('checked', false); // 确保默认关闭
                    $('#password-fields').hide();
                    // 确保开关按钮可操作
                    $('.password-switch-container').removeClass('disabled').css('opacity', '1');
                }
                
                // 确保密码字段状态正确
                if (userRole !== 'marker') {
                    $('#change-password-switch').prop('checked', false);
                    $('#password-fields').css('display', 'none');
                }
                
                // 打开模态框
                $('#userEditModal').modal('show');
            });

            // 绑定密码开关事件
            $('#change-password-switch').on('change', togglePasswordFields);
            
            // 监听模态框显示事件，确保密码字段状态正确
            $('#userEditModal').on('shown.bs.modal', function() {
                const userRole = '<?php echo $_SESSION['role']; ?>';
                if (userRole !== 'marker') {
                    $('#change-password-switch').prop('checked', false);
                    $('#password-fields').css('display', 'none');
                }
            });
            
            // 确保密码开关可点击
            $('.password-switch-container').on('click', function(e) {
                // 如果容器被禁用，则不做任何操作
                if ($(this).hasClass('disabled')) {
                    return;
                }
                
                // 如果点击的不是开关本身，则手动切换开关状态
                if (!$(e.target).hasClass('form-check-input')) {
                    const checkbox = $('#change-password-switch');
                    const newState = !checkbox.prop('checked');
                    checkbox.prop('checked', newState);
                    
                    // 显示或隐藏密码字段
                    if (newState) {
                        $('#password-fields').css('display', 'block');
                    } else {
                        $('#password-fields').css('display', 'none');
                        $('#edit-old-password, #edit-new-password, #edit-confirm-password').val('');
                    }
                }
            });

            // 处理用户信息保存
            $('#saveUserInfo').on('click', function() {
                // 获取当前用户角色
                const userRole = '<?php echo $_SESSION['role']; ?>';
                
                // 获取表单数据
                const realName = $('#edit-real-name').val().trim();
                const oldPassword = $('#edit-old-password').val();
                const newPassword = $('#edit-new-password').val();
                const confirmPassword = $('#edit-confirm-password').val();

                // 获取密码开关状态
                const isChangingPassword = $('#change-password-switch').is(':checked');
                
                // 阅卷老师只能修改密码，不能修改姓名
                if (userRole === 'marker') {
                    // 阅卷老师必须修改密码，且不能修改真实姓名
                    if (!oldPassword || !newPassword || !confirmPassword) {
                        Swal.fire({
                            icon: 'error',
                            title: '错误',
                            text: '请完整填写密码信息'
                        });
                        return;
                    }
                } else {
                    // 其他角色的验证逻辑
                    // 如果用户名为空且不修改密码，提示错误
                    if (!realName && !isChangingPassword) {
                        Swal.fire({
                            icon: 'error',
                            title: '错误',
                            text: '请至少修改姓名或密码'
                        });
                        return;
                    }
                }

                // 如果开启了密码修改，验证密码字段
                if (isChangingPassword) {
                    // 验证旧密码
                    if (!oldPassword) {
                        Swal.fire({
                            icon: 'error',
                            title: '错误',
                            text: '请输入旧密码'
                        });
                        return;
                    }

                    // 验证新密码
                    if (!newPassword) {
                        Swal.fire({
                            icon: 'error',
                            title: '错误',
                            text: '请输入新密码'
                        });
                        return;
                    }

                    // 验证密码一致性
                    if (newPassword !== confirmPassword) {
                        Swal.fire({
                            icon: 'error',
                            title: '错误',
                            text: '两次输入的新密码不一致'
                        });
                        return;
                    }
                }

                // 构建请求数据
                const formData = new FormData();
                
                // 阅卷老师只能修改密码，不能修改真实姓名
                if (userRole === 'marker') {
                    // 不提交姓名变更，只提交密码变更
                    formData.append('old_password', oldPassword);
                    formData.append('new_password', newPassword);
                } else {
                    // 其他角色可以修改姓名和密码
                    formData.append('real_name', realName);
                    if (isChangingPassword) {
                        formData.append('old_password', oldPassword);
                        formData.append('new_password', newPassword);
                    }
                }

                // 发送请求
                $.ajax({
                    url: 'api/index.php?route=user/update_profile',
                    method: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        if (response.success) {
                            Swal.fire({
                                icon: 'success',
                                title: '成功',
                                text: userRole === 'marker' ? '密码修改成功' : '用户信息更新成功'
                            }).then(() => {
                                // 只有非阅卷老师角色才更新页面上显示的用户姓名
                                if (userRole !== 'marker') {
                                    $('.user-name').text(realName);
                                }
                                // 关闭模态框
                                $('#userEditModal').modal('hide');
                                // 清空密码字段
                                $('#edit-old-password').val('');
                                $('#edit-new-password').val('');
                                $('#edit-confirm-password').val('');
                                // 刷新页面以更新会话
                                window.location.reload();
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: '错误',
                                text: response.error || '更新失败，请稍后重试'
                            });
                        }
                    },
                    error: function(xhr) {
                        // 处理400错误（旧密码不正确）
                        if (xhr.status === 400 && xhr.responseJSON && xhr.responseJSON.error) {
                            Swal.fire({
                                icon: 'error',
                                title: '密码错误',
                                text: xhr.responseJSON.error
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: '系统错误',
                                text: '服务器错误，请稍后重试'
                            });
                        }
                    }
                });
            });

            // 仅在微信浏览器中添加页面可见性和关闭事件监听
            if (isWechatBrowser()) {
                // 使用页面可见性API监听页面隐藏事件
                document.addEventListener('visibilitychange', function() {
                    if (document.visibilityState === 'hidden') {
                        // 页面隐藏时自动注销登录
                        $.ajax({
                            url: 'api/index.php?route=logout',
                            method: 'POST',
                            async: false // 使用同步请求确保在页面关闭前完成
                        });
                    }
                });

                // 监听页面关闭/刷新事件
                window.addEventListener('beforeunload', function() {
                    // 页面关闭时自动注销登录
                    $.ajax({
                        url: 'api/index.php?route=logout',
                        method: 'POST',
                        async: false // 使用同步请求确保在页面关闭前完成
                    });
                });
            }

            // 初始化水印
            function initWatermark() {
                const realName = '<?php echo $_SESSION['real_name']; ?>';
                const username = '<?php echo $_SESSION['username']; ?>';
                const watermarkText = `${realName}(${username})`;
                const grid = document.getElementById('watermarkGrid');
                
                if (grid) {
                    grid.innerHTML = '';
                    for (let i = 0; i < 36; i++) {
                        const div = document.createElement('div');
                        div.className = 'watermark-item';
                        const span = document.createElement('span');
                        span.className = 'watermark-text';
                        span.innerHTML = `${watermarkText}<br/>${new Date().toLocaleString()}`;
                        div.appendChild(span);
                        grid.appendChild(div);
                    }
                }
            }

            // 定期更新水印
            function updateWatermark() {
                initWatermark();
            }

            // 初始化水印
            initWatermark();
            
            // 每分钟更新一次水印（更新时间戳）
            setInterval(updateWatermark, 60000);
            
            // 窗口大小改变时重新生成水印
            $(window).on('resize', initWatermark);
            
            // 确保水印在所有内容之上
            $(document).ready(function() {
                $('.watermark').appendTo('body');
            });

            // 会话保活函数 - 每10分钟发送一次心跳请求
            function setupSessionKeepAlive() {
                // 初始化心跳计时器
                const keepAliveInterval = 10 * 60 * 1000; // 10分钟
                
                // 发送心跳请求的函数
                function sendHeartbeat() {
                    fetch('api/index.php?route=heartbeat', {
                        method: 'POST',
                        credentials: 'include'
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (!data.success) {
                            // 如果心跳失败（会话已过期），重定向到登录页面
                            window.location.href = 'login.php?expired=1';
                        }
                    })
                    .catch(() => {
                        // 请求失败，可能是网络问题，继续保持心跳
                        console.log('Heartbeat failed, will retry next interval');
                    });
                }
                
                // 设置定期发送心跳的定时器
                const heartbeatTimer = setInterval(sendHeartbeat, keepAliveInterval);
                
                // 在页面可见性变化时调整心跳行为
                document.addEventListener('visibilitychange', function() {
                    if (document.visibilityState === 'visible') {
                        // 页面变为可见时立即发送一次心跳
                        sendHeartbeat();
                    }
                });
                
                // 在页面关闭/刷新前尝试发送最后一次心跳
                window.addEventListener('beforeunload', function() {
                    // 使用navigator.sendBeacon进行异步请求，确保在页面关闭时也能发送
                    if (navigator.sendBeacon) {
                        navigator.sendBeacon('api/index.php?route=heartbeat');
                    }
                });
                
                // 初始心跳
                sendHeartbeat();
                
                return heartbeatTimer;
            }

            // 页面加载完成后启动会话保活
            document.addEventListener('DOMContentLoaded', function() {
                setupSessionKeepAlive();
            });

            // 获取未读消息数量并更新通知图标
            function updateNotificationBadge() {
                if (!window.userLoggedIn) return;
                
                $.get('api/index.php?route=score_edit/unread_count')
                    .done(function(response) {
                        if (response.success) {
                            const unreadCount = response.data.unread_count;
                            if (unreadCount > 0) {
                                $('#notificationBadge').text(unreadCount > 99 ? '99+' : unreadCount).show();
                            } else {
                                $('#notificationBadge').hide();
                            }
                        }
                    })
                    .fail(function() {
                        console.error('获取未读消息数量失败');
                    });
            }
            
            // 点击通知图标
            $('#notificationBtn').click(function(e) {
                e.preventDefault();
                // 加载消息通知模块
                loadModule('notifications');
            });

            // 每60秒检查一次未读消息
            setInterval(updateNotificationBadge, 60000);
            
            // 页面加载完成后立即检查一次未读消息
            $(document).ready(function() {
                // 设置用户已登录标志
                window.userLoggedIn = true;
                
                // 立即检查未读消息
                updateNotificationBadge();
                
                // 其他初始化代码...
            });
        });
    </script>
</body>
</html>