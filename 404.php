<?php
http_response_code(404);
?>
<!DOCTYPE html>
<html lang="zh-CN" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 - 页面未找到</title>
    <link rel="icon" href="./logo.png" type="image/png">
    <style>
        :root {
            --accent-hue: 220;
            --accent-sat: 60%;
            --accent-lightness: 50%;
            --accent: hsl(var(--accent-hue), var(--accent-sat), var(--accent-lightness));
            --bg: hsl(var(--accent-hue), 60%, 96%);
            --surface: #ffffff;
            --border: #dce7f5;
            --text: #1e293b;
            --text-secondary: #475569;
            --text-muted: #94a3b8;
            --shadow: 0 2px 8px rgba(0,0,0,0.05), 0 1px 3px rgba(0,0,0,0.04);
            --radius: 14px;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', 'PingFang SC', 'Hiragino Sans GB', 'Microsoft YaHei', sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            -webkit-font-smoothing: antialiased;
        }
        .error-container {
            text-align: center;
            padding: 40px;
            max-width: 480px;
        }
        .error-code {
            font-size: 120px;
            font-weight: 800;
            color: var(--accent);
            line-height: 1;
            opacity: 0.15;
            letter-spacing: -4px;
            user-select: none;
        }
        .error-icon {
            margin: -60px auto 24px;
            width: 80px;
            height: 80px;
            background: var(--surface);
            border-radius: 50%;
            box-shadow: var(--shadow);
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .error-icon svg {
            width: 36px;
            height: 36px;
            stroke: var(--text-muted);
            fill: none;
            stroke-width: 1.5;
            stroke-linecap: round;
            stroke-linejoin: round;
        }
        h1 {
            font-size: 24px;
            font-weight: 700;
            color: var(--text);
            margin-bottom: 12px;
        }
        p {
            font-size: 15px;
            color: var(--text-secondary);
            line-height: 1.6;
            margin-bottom: 32px;
        }
        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 28px;
            background: var(--accent);
            color: #fff;
            border: none;
            border-radius: var(--radius);
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.2s;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .back-btn:hover {
            background: hsl(var(--accent-hue), calc(var(--accent-sat) + 10%), calc(var(--accent-lightness) - 10%));
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        .back-btn svg {
            width: 18px;
            height: 18px;
            stroke: currentColor;
            fill: none;
            stroke-width: 2;
            stroke-linecap: round;
            stroke-linejoin: round;
        }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="error-code">404</div>
        <div class="error-icon">
            <svg viewBox="0 0 24 24">
                <circle cx="12" cy="12" r="10"/>
                <path d="M16 16s-1.5-2-4-2-4 2-4 2"/>
                <line x1="9" y1="9" x2="9.01" y2="9"/>
                <line x1="15" y1="9" x2="15.01" y2="9"/>
            </svg>
        </div>
        <h1>页面走丢了</h1>
        <p>你访问的页面不存在或已被移除，请检查链接是否正确。</p>
        <a href="/" class="back-btn">
            <svg viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"/></svg>
            返回首页
        </a>
    </div>
</body>
</html>
