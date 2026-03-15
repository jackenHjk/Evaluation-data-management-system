<?php
/**
 * 文件名: login.php
 * 功能描述: 成绩统计分析系统登录入口文件
 * 
 * 该文件负责:
 * 1. 检查用户登录状态，已登录则重定向到系统主页
 * 2. 提供用户登录界面和登录表单
 * 3. 处理用户登录请求，包括验证码校验、账号密码验证
 * 4. 记住登录功能的实现
 * 
 * API调用说明:
 * - 调用 user/login API进行用户登录验证 (api/index.php?route=user/login)
 * - 调用 get_captcha API获取验证码 (api/index.php?route=get_captcha)
 * 
 * 关联文件:
 * - index.php: 系统主入口文件
 * - api/index.php: API入口文件
 * - assets/: 静态资源目录
 */

session_start();

// 检查会话是否有效
if (isset($_SESSION['user_id'])) {
    // 获取URL参数中的module
    $module = $_GET['module'] ?? 'dashboard';
    header("Location: index.php?module=$module");
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>登录 - 测评数据管理系统</title>
    <link href="assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/all.min.css" rel="stylesheet">
    <link href="assets/css/sweetalert2.min.css" rel="stylesheet">
    <style>
        body {
            margin: 0;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: -apple-system, BlinkMacSystemFont, "SF Pro Text", "Helvetica Neue", Arial, sans-serif;
            background: linear-gradient(135deg, #EBF4FF 0%, #F5F7FA 100%);
            position: relative;
            overflow: hidden;
            -webkit-font-smoothing: antialiased;
        }

        /* 波浪效果 */
        .wave {
            position: fixed;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 200px;
            background: url('assets/images/wave.svg') repeat-x;
            background-size: 1600px 200px;
            animation: wave 10s linear infinite;
            opacity: 0.6;
            z-index: 0;
        }

        .wave:nth-child(2) {
            bottom: 10px;
            opacity: 0.4;
            animation: wave 7s linear reverse infinite;
        }

        .wave:nth-child(3) {
            bottom: 20px;
            opacity: 0.2;
            animation: wave 5s linear infinite;
        }

        @keyframes wave {
            0% { background-position-x: 0; }
            100% { background-position-x: 1600px; }
        }

        /* 动态背景元素 */
        .bg-elements {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 0;
            pointer-events: none;
        }

        .bg-element {
            position: absolute;
            opacity: 0.06;
            color: #0066CC;
            animation: float 20s infinite;
            filter: blur(0.6px);
        }

        .bg-element:nth-child(1) { top: 15%; left: 15%; font-size: 5rem; animation-delay: 0s; }
        .bg-element:nth-child(2) { top: 25%; right: 20%; font-size: 4.5rem; animation-delay: 2s; }
        .bg-element:nth-child(3) { bottom: 25%; left: 25%; font-size: 6rem; animation-delay: 4s; }
        .bg-element:nth-child(4) { bottom: 30%; right: 15%; font-size: 5.5rem; animation-delay: 6s; }

        @keyframes float {
            0%, 100% { transform: translate(0, 0) rotate(0deg); }
            25% { transform: translate(-15px, -20px) rotate(-5deg); }
            50% { transform: translate(0, -35px) rotate(0deg); }
            75% { transform: translate(15px, -20px) rotate(5deg); }
        }

        .login-container {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            box-shadow: 
                0 10px 40px rgba(0, 0, 0, 0.08),
                0 0 0 1px rgba(0, 0, 0, 0.02);
            padding: 40px;
            width: 100%;
            max-width: 380px;
            position: relative;
            z-index: 1;
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            animation: slideUp 0.6s cubic-bezier(0.16, 1, 0.3, 1);
        }

        @keyframes slideUp {
            0% { 
                opacity: 0;
                transform: translateY(20px);
            }
            100% { 
                opacity: 1;
                transform: translateY(0);
            }
        }

        .login-header {
            text-align: center;
            margin-bottom: 32px;
        }

        .school-icon {
            font-size: 32px;
            color: #0066CC;
            margin-bottom: 16px;
            animation: scaleIn 0.5s cubic-bezier(0.16, 1, 0.3, 1) 0.2s backwards;
        }

        @keyframes scaleIn {
            0% { 
                opacity: 0;
                transform: scale(0.8);
            }
            100% { 
                opacity: 1;
                transform: scale(1);
            }
        }

        .login-header h2 {
            color: #1D1D1F;
            font-size: 24px;
            font-weight: 600;
            margin: 0 0 8px;
            letter-spacing: -0.5px;
        }

        .login-header p {
            color: #86868B;
            font-size: 14px;
            margin: 0;
            animation: fadeIn 0.5s ease 0.3s backwards;
        }

        .form-group {
            margin-bottom: 20px;
            position: relative;
        }

        .form-group label {
            display: block;
            margin-bottom: 6px;
            color: #1D1D1F;
            font-size: 13px;
            font-weight: 500;
        }

        .form-control {
            width: 100%;
            height: 40px;
            padding: 8px 12px;
            padding-left: 36px;
            border: 1px solid #E5E5EA;
            border-radius: 10px;
            font-size: 15px;
            transition: all 0.2s ease;
            background: #FFFFFF;
            color: #1D1D1F;
        }

        .form-control:focus {
            border-color: #0066CC;
            box-shadow: 0 0 0 4px rgba(0, 102, 204, 0.1);
            outline: none;
        }

        .input-icon {
            position: absolute;
            left: 12px;
            top: 34px;
            color: #86868B;
            font-size: 14px;
            transition: all 0.2s ease;
        }

        .form-control:focus + .input-icon {
            color: #0066CC;
        }

        .btn-login {
            width: 100%;
            height: 44px;
            border: none;
            border-radius: 10px;
            background: #0066CC;
            color: white;
            font-size: 15px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            position: relative;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-top: 32px;
        }

        .btn-login:hover {
            background: #0055B3;
            transform: translateY(-1px);
        }

        .btn-login:active {
            transform: translateY(0);
        }

        .btn-login::after {
            content: '';
            position: absolute;
            width: 200%;
            height: 100%;
            background: linear-gradient(
                120deg,
                transparent,
                rgba(255, 255, 255, 0.3),
                transparent
            );
            transform: translateX(-100%);
        }

        .btn-login:hover::after {
            animation: shine 1.5s ease-in-out;
        }

        @keyframes shine {
            to {
                transform: translateX(100%);
            }
        }

        .alert {
            padding: 12px 16px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 13px;
            display: flex;
            align-items: center;
            animation: shake 0.5s cubic-bezier(.36,.07,.19,.97) both;
        }

        @keyframes shake {
            10%, 90% { transform: translateX(-1px); }
            20%, 80% { transform: translateX(2px); }
            30%, 50%, 70% { transform: translateX(-4px); }
            40%, 60% { transform: translateX(4px); }
        }

        .alert-danger {
            background: #FFF5F5;
            color: #FF3B30;
            border: 1px solid #FFE3E3;
        }

        .alert-success {
            background: #F0FFF4;
            color: #34C759;
            border: 1px solid #C6F6D5;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        /* 微信浏览器提醒模态框样式 */
        .wechat-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.6);
            z-index: 9999;
            align-items: center;
            justify-content: center;
        }

        .wechat-modal-content {
            background: white;
            padding: 30px;
            border-radius: 15px;
            width: 90%;
            max-width: 400px;
            text-align: center;
            position: relative;
            animation: modalSlideUp 0.3s ease;
        }

        /* 确保Swal提示框显示在最上层 */
        .swal2-container {
            z-index: 10000 !important;
        }

        /* 调整Swal提示框的位置 */
        .swal2-popup {
            position: relative !important;
            margin-top: -150px !important;
        }

        @keyframes modalSlideUp {
            from { transform: translateY(20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        .wechat-modal-title {
            font-size: 18px;
            font-weight: 600;
            color: #1D1D1F;
            margin-bottom: 15px;
        }

        .wechat-modal-text {
            font-size: 14px;
            color: #86868B;
            margin-bottom: 20px;
            line-height: 1.6;
        }

        .wechat-modal-url {
            background: #F5F7FA;
            padding: 12px;
            border-radius: 8px;
            font-size: 14px;
            color: #0066CC;
            margin-bottom: 20px;
            word-break: break-all;
            user-select: all;
        }

        .copy-btn {
            background: #0066CC;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .copy-btn:hover {
            background: #0055B3;
        }

        .copy-btn:active {
            transform: scale(0.98);
        }

        .browser-icons {
            margin-top: 20px;
            display: flex;
            justify-content: center;
            gap: 20px;
        }

        .browser-icons i {
            font-size: 24px;
            color: #86868B;
        }
    </style>
</head>
<body>
    <!-- 波浪效果 -->
    <div class="wave"></div>
    <div class="wave"></div>
    <div class="wave"></div>

    <!-- 微信浏览器提醒模态框 -->
    <div class="wechat-modal" id="wechatModal">
        <div class="wechat-modal-content">
            <div class="wechat-modal-title">
                <i class="fab fa-weixin" style="color: #07C160; margin-right: 8px;"></i>浏览器提醒
            </div>
            <div class="wechat-modal-text">
                抱歉，本系统不支持微信内置浏览器。<br>
                请点击窗口右上角的🌐用系统默认浏览器打开。<br>或复制以下网址，使用其他浏览器访问：
            </div>
            <div class="wechat-modal-url" id="systemUrl"></div>
            <button class="copy-btn" onclick="copyUrl()">
                <i class="far fa-copy"></i> 复制网址
            </button>
            <div class="wechat-modal-text" style="margin-top: 20px;">
                推荐使用以下浏览器：
            </div>
            <div class="browser-icons">
                <i class="fab fa-edge" title="Edge浏览器"></i>
                <i class="fab fa-chrome" title="Chrome浏览器"></i>
            </div>
        </div>
    </div>

    <!-- 背景动画元素 -->
    <div class="bg-elements">
        <i class="fas fa-graduation-cap bg-element"></i>
        <i class="fas fa-book-reader bg-element"></i>
        <i class="fas fa-pencil-alt bg-element"></i>
        <i class="fas fa-chart-line bg-element"></i>
    </div>

    <div class="login-container">
        <div class="login-header">
            <div class="school-icon">
                <i class="fas fa-school"></i>
            </div>
            <h2>测评数据管理系统</h2>
            <p>欢迎使用，请登录您的账号</p>
        </div>

        <div id="errorMsg" class="alert alert-danger" style="display:none;"></div>
        <div id="successMsg" class="alert alert-success" style="display:none;"></div>
        
        <form id="loginForm">
            <div class="form-group">
                <label>账号</label>
                <input type="text" name="username" class="form-control" required placeholder="请输入账号">
                <i class="fas fa-user input-icon"></i>
            </div>
            <div class="form-group">
                <label>密码</label>
                <input type="password" name="password" class="form-control" required placeholder="请输入密码">
                <i class="fas fa-lock input-icon"></i>
            </div>
            <button type="submit" class="btn-login">
                <span>登录</span>
            </button>
        </form>
    </div>

    <script src="assets/js/jquery.min.js"></script>
    <script src="assets/js/sweetalert2.all.min.js"></script>
    <script>
        // 检测是否是微信浏览器
        function isWeixinBrowser(){
            var ua = navigator.userAgent.toLowerCase();
            return ua.match(/MicroMessenger/i) == "micromessenger";
        }

        // 生成设备指纹
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

        // 复制网址到剪贴板
        function copyUrl() {
            var urlText = document.getElementById('systemUrl').innerText;
            
            // 使用现代的Clipboard API
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(urlText).then(() => {
                    Swal.fire({
                        icon: 'success',
                        title: '复制成功',
                        text: '网址已复制到剪贴板',
                        timer: 1500,
                        showConfirmButton: false,
                        position: 'top', // 设置提示框显示在顶部
                        customClass: {
                            container: 'swal-on-top' // 添加自定义类
                        }
                    });
                });
            } else {
                // 降级方案
                const textArea = document.createElement("textarea");
                textArea.value = urlText;
                document.body.appendChild(textArea);
                textArea.select();
                try {
                    document.execCommand('copy');
                    Swal.fire({
                        icon: 'success',
                        title: '复制成功',
                        text: '网址已复制到剪贴板',
                        timer: 1500,
                        showConfirmButton: false,
                        position: 'top', // 设置提示框显示在顶部
                        customClass: {
                            container: 'swal-on-top' // 添加自定义类
                        }
                    });
                } catch (err) {
                    Swal.fire({
                        icon: 'error',
                        title: '复制失败',
                        text: '请手动长按选择并复制网址',
                        timer: 1500,
                        showConfirmButton: false,
                        position: 'top', // 设置提示框显示在顶部
                        customClass: {
                            container: 'swal-on-top' // 添加自定义类
                        }
                    });
                }
                document.body.removeChild(textArea);
            }
        }

        $(document).ready(function() {
            // 生成设备指纹并保存
            const deviceFingerprint = generateDeviceFingerprint();
            localStorage.setItem('device_fingerprint', deviceFingerprint);
            
            // 检查是否是会话过期重定向
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('expired') === '1') {
                $('#successMsg').hide();
                $('#errorMsg').html('您的登录会话已过期，请重新登录').show();
                // 清除URL参数
                history.replaceState(null, '', 'login.php');
            }
            
            // 检测微信浏览器并显示提醒
            if (isWeixinBrowser()) {
                // 获取当前完整URL
                var currentUrl = window.location.href;
                // 设置URL到显示区域
                document.getElementById('systemUrl').innerText = currentUrl;
                // 显示模态框
                document.getElementById('wechatModal').style.display = 'flex';
            }

            // 添加键盘事件监听，实现按Enter键登录
            $(document).on('keypress', function(e) {
                if (e.which === 13) { // 13是Enter键的keyCode
                    $('#loginForm').submit();
                }
            });

            $('#loginForm').on('submit', function(e) {
                e.preventDefault();
                
                $('#errorMsg, #successMsg').hide();
                
                const submitBtn = $(this).find('button[type="submit"]');
                const originalText = submitBtn.text();
                submitBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-2"></i>登录中...');
                
                // 获取设备指纹
                const deviceFingerprint = localStorage.getItem('device_fingerprint') || '';
                
                // 创建表单数据，添加设备指纹
                const formData = new FormData(this);
                formData.append('device_fingerprint', deviceFingerprint);
                
                // 创建一个自定义的 Promise 来处理登录请求
                new Promise((resolve, reject) => {
                    $.ajax({
                        url: 'api/index.php?route=login',
                        method: 'POST',
                        data: formData,
                        processData: false,
                        contentType: false,
                        dataType: 'json'
                    })
                    .done(resolve)
                    .fail((xhr) => {
                        // 如果是预期中的错误状态，则正常处理
                        if (xhr.status === 403 && xhr.responseJSON?.code === 'ALREADY_LOGGED_IN') {
                            resolve(xhr.responseJSON);
                        } else {
                            reject(xhr);
                        }
                    });
                })
                .then(response => {
                    if (response.success) {
                        $('#successMsg').html('登录成功，正在跳转...').show();
                        // 设置cookie，过期时间为30分钟
                        const expires = new Date();
                        expires.setMinutes(expires.getMinutes() + 30);
                        document.cookie = `login_token=${response.login_token}; expires=${expires.toUTCString()}; path=/; secure; samesite=Strict`;
                        document.cookie = `username=${response.username}; expires=${expires.toUTCString()}; path=/; secure; samesite=Strict`;
                        // 获取URL参数中的module
                        const urlParams = new URLSearchParams(window.location.search);
                        const module = urlParams.get('module') || 'dashboard';
                        // 跳转到指定模块
                        window.location.href = `index.php?module=${module}`;
                    } else {
                        let message = response.error || '用户名或密码错误';
                        if (response.code === 'ALREADY_LOGGED_IN') {
                            message = '该账号已在其他设备登录，请先退出后再登录,或30分钟后重试。';
                        }
                        $('#errorMsg').html(message).show();
                    }
                })
                .catch(xhr => {
                    console.error('Login error:', xhr);
                    let ret = xhr.responseJSON;
                    // 如果尝试解析失败，可能是返回了HTML报错页面
                    if (!ret && xhr.responseText) {
                         try {
                             ret = JSON.parse(xhr.responseText);
                         } catch (e) {
                             // 无法解析为JSON，直接显示原始内容的前200个字符用于调试
                             // 注意：生产环境应谨慎显示，防止XSS，这里仅用于调试
                             $('#errorMsg').text('系统错误 (Raw): ' + xhr.responseText.substring(0, 200)).show();
                             return;
                         }
                    }
                    
                    const errorMsg = (ret && ret.error) ? ret.error : '登录失败，请稍后重试 (Status: ' + xhr.status + ')';
                    $('#errorMsg').html(errorMsg).show();
                })
                .finally(() => {
                    submitBtn.prop('disabled', false).html(originalText);
                });
            });
        });
    </script>
</body>
</html>