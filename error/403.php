<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>无权访问 - 成绩统计分析系统</title>
    <link href="../assets/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #0066CC;
            --error-color: #FF3B30;
            --text-primary: #1D1D1F;
            --text-secondary: #86868B;
            --background-primary: #F5F7FA;
            --nav-bg: #0066CC;
            --nav-text: #FFFFFF;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "SF Pro Text", "Helvetica Neue", Arial, sans-serif;
            background: var(--background-primary);
            min-height: 100vh;
            color: var(--text-primary);
            line-height: 1.5;
            display: flex;
            flex-direction: column;
        }

        /* 导航栏样式 */
        .nav {
            background: var(--nav-bg);
            padding: 0.8rem 1.5rem;
            color: var(--nav-text);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .nav-left {
            display: flex;
            align-items: center;
            gap: 2rem;
        }

        .nav-brand {
            font-size: 1.2rem;
            font-weight: 500;
            text-decoration: none;
            color: var(--nav-text);
        }

        .nav-menu {
            display: flex;
            gap: 1.5rem;
            list-style: none;
        }

        .nav-menu a {
            color: var(--nav-text);
            text-decoration: none;
            font-size: 0.95rem;
            opacity: 0.9;
            transition: opacity 0.3s;
        }

        .nav-menu a:hover {
            opacity: 1;
        }

        .nav-right {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .nav-user {
            font-size: 0.9rem;
        }

        .nav-logout {
            color: var(--nav-text);
            text-decoration: none;
            font-size: 0.9rem;
            opacity: 0.9;
        }

        /* 错误页面样式 */
        .main-content {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .error-container {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            padding: 40px;
            text-align: center;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.06);
            backdrop-filter: blur(10px);
            max-width: 480px;
            width: 100%;
        }

        .error-icon {
            width: 80px;
            height: 80px;
            background: var(--error-color);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 24px;
        }

        .error-icon i {
            font-size: 32px;
            color: white;
        }

        h1 {
            font-size: 24px;
            margin-bottom: 16px;
            font-weight: 600;
            color: var(--text-primary);
        }

        .error-message {
            font-size: 16px;
            color: var(--text-secondary);
            margin-bottom: 32px;
            line-height: 1.6;
        }

        .back-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 12px 24px;
            background: var(--primary-color);
            color: white;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .back-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 102, 204, 0.2);
            background: #0052a3;
        }

        .back-button i {
            margin-right: 8px;
            transition: transform 0.3s ease;
        }

        .back-button:hover i {
            transform: translateX(-4px);
        }

        @media (max-width: 480px) {
            .error-container {
                padding: 24px;
                margin: 20px;
            }

            .error-icon {
                width: 64px;
                height: 64px;
                margin-bottom: 20px;
            }

            .error-icon i {
                font-size: 28px;
            }
        }
    </style>
</head>
<body>
 

    <!-- 错误内容 -->
    <div class="main-content">
        <div class="error-container">
            <div class="error-icon">
                <i class="fas fa-lock"></i>
            </div>
            <h1>无权访问此页面</h1>
            <div class="error-message">
                抱歉，您当前的用户权限不足以访问此页面。如需访问，请联系系统管理员获取相应权限。
            </div>
            <a href="../index.php" class="back-button">
                <i class="fas fa-arrow-left"></i>
                返回首页
            </a>
        </div>
    </div>
</body>
</html> 