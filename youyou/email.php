<?php
session_start();
require_once __DIR__ . '/../utils.php';
if (!validateAdminSession()) {
    logUnauthorized('越权尝试访问邮件配置');
    header('Location: ../?admin_login=1');
    exit;
}
$config = loadSiteConfig();
$msg = '';
checkAdminPath();
$msgType = 'success';

// 处理表单提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'save';
    if ($action === 'save') {
        $config['smtp_host'] = trim($_POST['smtp_host'] ?? '');
        $config['smtp_port'] = max(1, intval($_POST['smtp_port'] ?? 465));
        $config['smtp_user'] = trim($_POST['smtp_user'] ?? '');
        if (!empty($_POST['smtp_pass'])) $config['smtp_pass'] = $_POST['smtp_pass'];
        $config['smtp_from_name'] = trim($_POST['smtp_from_name'] ?? 'You Markdown');
        $config['smtp_from_addr'] = trim($_POST['smtp_from_addr'] ?? '');
        $config['smtp_encryption'] = in_array($_POST['smtp_encryption'] ?? '', ['ssl', 'tls', 'none']) ? $_POST['smtp_encryption'] : 'ssl';
        $config['email_notify_enabled'] = !empty($_POST['email_notify_enabled']);
        $config['email_notify_to'] = trim($_POST['email_notify_to'] ?? '');
        $config['email_verify_enabled'] = !empty($_POST['email_verify_enabled']);
        saveSiteConfig($config);
        $msg = '邮件配置已保存';
    } elseif ($action === 'test') {
        $testTo = trim($_POST['test_email'] ?? '');
        if (empty($testTo)) {
            $msg = '请输入测试邮箱'; $msgType = 'error';
        } else {
            $html = '<div style="font-family:sans-serif;max-width:500px;margin:0 auto;padding:20px;">';
            $html .= '<h2 style="color:#16a34a;">✅ 邮件发送测试</h2>';
            $html .= '<p>如果你收到这封邮件，说明 SMTP 配置正确！</p>';
            $html .= '<p style="color:#6b7280;font-size:12px;">来自 You Markdown · ' . date('Y-m-d H:i:s') . '</p>';
            $html .= '</div>';
            $result = sendEmail($testTo, '[You Markdown] 邮件发送测试', $html);
            if ($result['success']) {
                $msg = '测试邮件已发送至 ' . htmlspecialchars($testTo);
            } else {
                $msg = '发送失败: ' . ($result['error'] ?? '未知错误');
                $msgType = 'error';
            }
        }
    }
    $config = loadSiteConfig();
}

// 检查 PHP 扩展
$extCheck = [
    'openssl' => extension_loaded('openssl'),
    'mbstring' => extension_loaded('mbstring'),
    'curl' => extension_loaded('curl'),
];
$allExtOk = !in_array(false, $extCheck);

$_siteTitle = $config['site_title'] ?? 'You Markdown';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title>邮件配置 - <?= htmlspecialchars($_siteTitle) ?></title>
<style>
@font-face{font-family:'ChineseFont';src:url('../fonts/luoliti.ttf') format('truetype');font-display:swap}
@font-face{font-family:'EnglishFont';src:url('../fonts/roundfont.ttf') format('truetype');font-display:swap}
:root{--accent-hue:220;--accent-sat:60%;--accent:hsl(var(--accent-hue),var(--accent-sat),50%);--bg:hsl(var(--accent-hue),60%,96%);--surface:#fff;--border:#dce7f5;--text:#1e293b;--text-secondary:#475569;--text-muted:#94a3b8;--shadow:0 2px 8px rgba(0,0,0,.05);--radius:14px;--radius-sm:10px}
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:'EnglishFont','ChineseFont',-apple-system,sans-serif;background:var(--bg);color:var(--text);min-height:100vh;-webkit-tap-highlight-color:transparent}
.top-bar{position:sticky;top:0;z-index:100;background:var(--surface);border-bottom:1px solid var(--border);box-shadow:0 1px 2px rgba(0,0,0,.04);display:flex;align-items:center;justify-content:space-between;padding:0 16px;height:52px}
.brand{font-size:14px;font-weight:650;color:var(--text-secondary)}
.header-right{display:flex;align-items:center;gap:4px}
.icon-btn{width:36px;height:36px;border-radius:8px;background:transparent;border:none;color:var(--text-secondary);display:flex;align-items:center;justify-content:center;cursor:pointer;text-decoration:none}
.icon-btn svg{width:20px;height:20px;stroke:currentColor;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}
.main{max-width:600px;margin:0 auto;padding:20px 16px 80px}
.page-title{font-size:1.3em;font-weight:650;margin-bottom:6px;display:flex;align-items:center;gap:8px}
.page-title svg{width:24px;height:24px;stroke:var(--accent);fill:none;stroke-width:2}
.page-desc{font-size:.88em;color:var(--text-muted);margin-bottom:20px}
.alert{padding:10px 14px;border-radius:8px;margin-bottom:14px;font-size:.88em;animation:fadeSlide .3s ease}
.alert-success{color:#16a34a;background:#f0fdf4;border:1px solid #bbf7d0}
.alert-error{color:#dc2626;background:#fef2f2;border:1px solid #fecaca}
@keyframes fadeSlide{from{opacity:0;transform:translateY(-6px)}to{opacity:1;transform:translateY(0)}}
.form-card{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);padding:20px;box-shadow:var(--shadow);margin-bottom:16px}
.form-card h3{font-size:.95em;font-weight:600;margin-bottom:14px;display:flex;align-items:center;gap:8px}
.form-card h3 svg{width:18px;height:18px;stroke:var(--accent);fill:none;stroke-width:2}
.form-group{margin-bottom:14px}
.form-group label{display:block;font-weight:500;margin-bottom:5px;font-size:.85em;color:var(--text-secondary)}
.form-group .hint{font-size:.78em;color:var(--text-muted);margin-top:4px}
input[type="text"],input[type="number"],input[type="password"],input[type="email"],select{width:100%;padding:9px 11px;border:1px solid var(--border);border-radius:8px;background:var(--bg);color:var(--text);font-family:inherit;font-size:14px;outline:none;transition:border-color .2s;-webkit-appearance:none}
input:focus,select:focus{border-color:var(--accent)}
.form-row{display:flex;gap:10px}
.form-row .form-group{flex:1}
.toggle-row{display:flex;align-items:center;justify-content:space-between;padding:12px 0;border-bottom:1px solid var(--border)}
.toggle-row:last-child{border-bottom:none}
.toggle-label{font-size:.9em}
.toggle-desc{font-size:.78em;color:var(--text-muted);margin-top:2px}
.toggle{position:relative;width:44px;height:24px;flex-shrink:0}
.toggle input{opacity:0;width:0;height:0}
.toggle .slider{position:absolute;inset:0;background:#cbd5e1;border-radius:12px;cursor:pointer;transition:.2s}
.toggle .slider:before{content:'';position:absolute;width:18px;height:18px;border-radius:50%;background:#fff;left:3px;top:3px;transition:.2s}
.toggle input:checked+.slider{background:var(--accent)}
.toggle input:checked+.slider:before{transform:translateX(20px)}
.btn{background:var(--accent);color:#fff;border:none;padding:10px 20px;border-radius:8px;cursor:pointer;font-weight:600;font-size:14px;transition:all .2s;font-family:inherit}
.btn:active{opacity:.85}
.btn-outline{background:transparent;color:var(--accent);border:1px solid var(--accent);padding:8px 16px;border-radius:8px;cursor:pointer;font-weight:500;font-size:13px;font-family:inherit}
.btn-outline:hover{background:hsl(var(--accent-hue),var(--accent-sat),95%)}
.ext-check{display:flex;align-items:center;gap:8px;padding:10px 14px;border-radius:8px;margin-bottom:8px;font-size:.88em}
.ext-check.ok{background:#f0fdf4;color:#16a34a;border:1px solid #bbf7d0}
.ext-check.missing{background:#fef2f2;color:#dc2626;border:1px solid #fecaca}
.ext-check svg{width:18px;height:18px;flex-shrink:0}
[data-theme="dark"]{--bg:hsl(var(--accent-hue),40%,8%);--surface:#161b22;--border:#30363d;--text:#e6edf3;--text-secondary:#b1bac4;--text-muted:#768390}
</style>
</head>
<body>
<header class="top-bar">
    <div><span class="brand"><?= htmlspecialchars($_siteTitle) ?></span></div>
    <div class="header-right">
        <a class="icon-btn" href="index.php" title="后台"><svg viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg></a>
        <a class="icon-btn" href="../" title="主页"><svg viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg></a>
    </div>
</header>
<main class="main">
    <div class="page-title"><svg viewBox="0 0 24 24"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>邮件配置</div>
    <div class="page-desc">配置 SMTP 邮件服务器，用于发送通知邮件和注册验证码</div>

    <?php if ($msg): ?><div class="alert alert-<?= $msgType ?>"><?= $msg ?></div><?php endif; ?>

    <!-- 环境检测 -->
    <div class="form-card">
        <h3><svg viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>环境检测</h3>
        <?php foreach ($extCheck as $ext => $ok): ?>
        <div class="ext-check <?= $ok ? 'ok' : 'missing' ?>">
            <?php if ($ok): ?>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
            <?php else: ?>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
            <?php endif; ?>
            <span>PHP 扩展 <b><?= $ext ?></b> — <?= $ok ? '已安装' : '未安装，邮件功能不可用' ?></span>
        </div>
        <?php endforeach; ?>
        <?php if (!$allExtOk): ?>
        <div class="ext-check missing">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
            <span>缺少必要扩展，请安装后重启 PHP：<code>apt install php-openssl php-mbstring php-curl</code></span>
        </div>
        <?php endif; ?>
    </div>

    <!-- SMTP 配置 -->
    <form method="POST">
    <input type="hidden" name="action" value="save">
    <div class="form-card">
        <h3><svg viewBox="0 0 24 24"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>SMTP 服务器</h3>
        <div class="form-row">
            <div class="form-group">
                <label>服务器地址</label>
                <input type="text" name="smtp_host" value="<?= htmlspecialchars($config['smtp_host'] ?? '') ?>" placeholder="smtp.qq.com">
            </div>
            <div class="form-group" style="max-width:120px">
                <label>端口</label>
                <input type="number" name="smtp_port" value="<?= intval($config['smtp_port'] ?? 465) ?>" placeholder="465">
            </div>
        </div>
        <div class="form-group">
            <label>加密方式</label>
            <select name="smtp_encryption">
                <option value="ssl" <?= ($config['smtp_encryption'] ?? 'ssl') === 'ssl' ? 'selected' : '' ?>>SSL</option>
                <option value="tls" <?= ($config['smtp_encryption'] ?? '') === 'tls' ? 'selected' : '' ?>>TLS</option>
                <option value="none" <?= ($config['smtp_encryption'] ?? '') === 'none' ? 'selected' : '' ?>>无加密</option>
            </select>
        </div>
        <div class="form-group">
            <label>SMTP 用户名</label>
            <input type="text" name="smtp_user" value="<?= htmlspecialchars($config['smtp_user'] ?? '') ?>" placeholder="your@email.com">
        </div>
        <div class="form-group">
            <label>SMTP 密码</label>
            <input type="password" name="smtp_pass" value="" placeholder="<?= !empty($config['smtp_pass']) ? '已设置（留空不修改）' : '输入密码或授权码' ?>">
            <div class="hint">QQ邮箱请使用授权码，Gmail请使用应用专用密码</div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>发件人名称</label>
                <input type="text" name="smtp_from_name" value="<?= htmlspecialchars($config['smtp_from_name'] ?? 'You Markdown') ?>">
            </div>
            <div class="form-group">
                <label>发件人邮箱</label>
                <input type="email" name="smtp_from_addr" value="<?= htmlspecialchars($config['smtp_from_addr'] ?? '') ?>" placeholder="your@email.com">
            </div>
        </div>
    </div>

    <!-- 通知设置 -->
    <div class="form-card">
        <h3><svg viewBox="0 0 24 24"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>通知设置</h3>
        <div class="toggle-row">
            <div>
                <div class="toggle-label">异常行为邮件通知</div>
                <div class="toggle-desc">异常登录、越权访问等行为通过邮件通知管理员</div>
            </div>
            <label class="toggle"><input type="checkbox" name="email_notify_enabled" <?= !empty($config['email_notify_enabled']) ? 'checked' : '' ?>><span class="slider"></span></label>
        </div>
        <div class="form-group" style="margin-top:12px">
            <label>通知接收邮箱</label>
            <input type="email" name="email_notify_to" value="<?= htmlspecialchars($config['email_notify_to'] ?? '') ?>" placeholder="admin@email.com">
            <div class="hint">留空则使用发件人邮箱</div>
        </div>
    </div>

    <!-- 注册验证 -->
    <div class="form-card">
        <h3><svg viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><polyline points="17 11 19 13 23 9"/></svg>注册验证</h3>
        <div class="toggle-row">
            <div>
                <div class="toggle-label">邮箱验证码注册</div>
                <div class="toggle-desc">开启后注册需通过邮箱验证码验证</div>
            </div>
            <label class="toggle"><input type="checkbox" name="email_verify_enabled" <?= !empty($config['email_verify_enabled']) ? 'checked' : '' ?>><span class="slider"></span></label>
        </div>
    </div>

    <button type="submit" class="btn" style="width:100%">保存配置</button>
    </form>

    <!-- 测试邮件 -->
    <div class="form-card" style="margin-top:16px">
        <h3><svg viewBox="0 0 24 24"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>发送测试</h3>
        <form method="POST" style="display:flex;gap:8px">
            <input type="hidden" name="action" value="test">
            <input type="email" name="test_email" placeholder="输入测试邮箱" style="flex:1" required>
            <button type="submit" class="btn-outline">发送测试</button>
        </form>
    </div>
</main>
</body>
</html>