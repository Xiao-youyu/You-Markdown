<?php
session_start();
require_once __DIR__ . '/../utils.php';
if (empty($_SESSION['cmt_user']) || ($_SESSION['cmt_user']['role'] ?? '') !== 'admin') {
    logUnauthorized('越权尝试访问管理后台(config.php)');
    header('Location: ../?admin_login=1');
    exit;
}
$dataDir = '../data';
$configFile = $dataDir . '/.config.json';

function loadConfig() {
    global $configFile;
    if (!file_exists($configFile)) return ['site_title' => 'You Markdown', 'reg_limit_per_ip' => 3, 'comments_enabled' => true, 'auto_ban' => true];
    $data = json_decode(file_get_contents($configFile), true);
    return is_array($data) ? $data : [];
}
function saveConfig($config) {
    global $configFile;
    file_put_contents($configFile, json_encode($config, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $config = loadConfig();
    $config['site_title'] = trim($_POST['site_title'] ?? $config['site_title']);
    $config['reg_limit_per_ip'] = max(1, intval($_POST['reg_limit_per_ip'] ?? $config['reg_limit_per_ip']));
    $config['comments_enabled'] = isset($_POST['comments_enabled']);
    $config['auto_ban'] = isset($_POST['auto_ban']);
    $autoBanUnauth = isset($_POST['auto_ban_unauthorized']) ? true : false;
    $regEnabled = isset($_POST['registration_enabled']) ? true : false;
    $guestComments = isset($_POST['guest_comments_enabled']) ? true : false;
    $maxLoginFails = max(3, intval($_POST['max_login_fails'] ?? 10));
    $maxCommentsPerMin = max(1, intval($_POST['max_comments_per_minute'] ?? 5));
    $maxRegsPerIP = max(1, intval($_POST['max_registrations_per_ip'] ?? 3));
    $config['auto_ban_unauthorized'] = $autoBanUnauth;
    $config['registration_enabled'] = $regEnabled;
    $config['guest_comments_enabled'] = $guestComments;
    $config['max_login_fails'] = $maxLoginFails;
    $config['max_comments_per_minute'] = $maxCommentsPerMin;
    $config['max_registrations_per_ip'] = $maxRegsPerIP;
    saveConfig($config);
    $msg = '保存成功';
}
$config = loadConfig();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title>网站配置 - <?= htmlspecialchars($config['site_title'] ?? 'You Markdown') ?></title>
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
.page-title{font-size:1.3em;font-weight:650;margin-bottom:20px;display:flex;align-items:center;gap:8px}
.page-title svg{width:24px;height:24px;stroke:var(--accent);fill:none;stroke-width:2}
.alert{padding:10px 14px;border-radius:8px;margin-bottom:14px;font-size:.88em;color:#16a34a;background:#f0fdf4;border:1px solid #bbf7d0;animation:fadeSlide .3s ease}
@keyframes fadeSlide{from{opacity:0;transform:translateY(-6px)}to{opacity:1;transform:translateY(0)}}
.form-card{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);padding:20px;box-shadow:var(--shadow);margin-bottom:16px}
.form-group{margin-bottom:16px}
.form-group label{display:block;font-weight:500;margin-bottom:6px;font-size:.88em;color:var(--text-secondary)}
.form-group .hint{font-size:.78em;color:var(--text-muted);margin-top:4px}
input[type="text"],input[type="number"]{width:100%;padding:10px 12px;border:1px solid var(--border);border-radius:8px;background:var(--bg);color:var(--text);font-family:inherit;font-size:14px;outline:none;transition:border-color .2s}
input:focus{border-color:var(--accent)}
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
.btn{background:var(--accent);color:#fff;border:none;padding:11px 24px;border-radius:8px;cursor:pointer;font-weight:600;font-size:14px;transition:all .2s;font-family:inherit}
.btn:active{opacity:.85}
[data-theme="dark"]{--bg:hsl(var(--accent-hue),40%,8%);--surface:#161b22;--border:#30363d;--text:#e6edf3;--text-secondary:#b1bac4;--text-muted:#768390}
.modal-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,.4); z-index:200; align-items:center; justify-content:center; }
.modal-overlay.active { display:flex; }
.modal-box { background:var(--surface); border-radius:var(--radius); padding:24px; box-shadow:0 8px 32px rgba(0,0,0,.15); }
.btn-outline { background:transparent; border:1px solid var(--border); color:var(--text-secondary); }
.btn-sm { padding:8px 16px; font-size:13px; }
</style>
</head>
<body>
<header class="top-bar">
    <div><span class="brand"><?= htmlspecialchars($config['site_title'] ?? 'You Markdown') ?></span></div>
    <div class="header-right">
        <a class="icon-btn" href="index.php" title="后台"><svg viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg></a>
        <a class="icon-btn" href="../" title="主页"><svg viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg></a>
    </div>
</header>
<main class="main">
    <div class="page-title"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>网站配置</div>
    <?php if ($msg): ?><div class="alert"><?= $msg ?></div><?php endif; ?>
    <form method="post">
        <div class="form-card">
            <div class="form-group">
                <label>网站标题</label>
                <input type="text" name="site_title" value="<?= htmlspecialchars($config['site_title']) ?>" maxlength="30">
                <div class="hint">显示在主界面左上角和浏览器标签页</div>
            </div>
            <div class="toggle-row">
                <div><div class="toggle-label">开放评论区</div><div class="toggle-desc">关闭后用户无法发表评论</div></div>
                <label class="toggle"><input type="checkbox" name="comments_enabled" <?= $config['comments_enabled'] ? 'checked' : '' ?>><span class="slider"></span></label>
            </div>
            <div class="toggle-row">
                <div><div class="toggle-label">自动 IP 封禁</div><div class="toggle-desc">检测到异常行为自动封禁 IP</div></div>
                <label class="toggle"><input type="checkbox" name="auto_ban" <?= $config['auto_ban'] ? 'checked' : '' ?>><span class="slider"></span></label>
            </div>
            <div class="toggle-row">
                <div><div class="toggle-label">自动封禁越权用户</div><div class="toggle-desc">开启后，尝试越权访问的 IP 将自动被封禁</div></div>
                <label class="toggle"><input type="checkbox" name="auto_ban_unauthorized" <?= ($config['auto_ban_unauthorized'] ?? false) ? 'checked' : '' ?>><span class="slider"></span></label>
            </div>
            <div class="toggle-row">
                <div><div class="toggle-label">开放网站注册</div><div class="toggle-desc">关闭后，新用户将无法注册账号</div></div>
                <label class="toggle"><input type="checkbox" name="registration_enabled" <?= ($config['registration_enabled'] ?? true) ? 'checked' : '' ?>><span class="slider"></span></label>
            </div>
            <div class="toggle-row">
                <div><div class="toggle-label">允许访客评论</div><div class="toggle-desc">开启后，访客无需登录即可发表评论</div></div>
                <label class="toggle"><input type="checkbox" name="guest_comments_enabled" <?= ($config['guest_comments_enabled'] ?? false) ? 'checked' : '' ?>><span class="slider"></span></label>
            </div>
            <div class="toggle-row" onclick="openRateLimitModal()" style="cursor:pointer;">
                <div><div class="toggle-label">频率限制设置</div><div class="toggle-desc">登录/评论/注册频率上限</div></div>
                <svg viewBox="0 0 24 24" width="20" height="20" stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
            </div>
        </div>
        <div style="display:flex;justify-content:flex-end"><button type="submit" class="btn">保存配置</button></div>
</main>
<div class="modal-overlay" id="rateLimitModal">
    <div class="modal-box" style="max-width:420px;text-align:left;">
        <h3>频率限制设置</h3>
        <p style="font-size:0.88em;color:var(--text-secondary);margin-bottom:16px;">设置各项操作的频率上限，超过后将记录日志（开启自动封禁则同时封禁 IP）</p>
        <div class="form-group">
            <label>频繁登录次数（次/小时）</label>
            <input type="number" name="max_login_fails" value="<?= $config['max_login_fails'] ?? 10 ?>" min="3" max="100">
        </div>
        <div class="form-group">
            <label>频繁评论（条/分钟）</label>
            <input type="number" name="max_comments_per_minute" value="<?= $config['max_comments_per_minute'] ?? 5 ?>" min="1" max="60">
        </div>
        <div class="form-group">
            <label>频繁注册（次/IP）</label>
            <input type="number" name="max_registrations_per_ip" value="<?= $config['max_registrations_per_ip'] ?? ($config['reg_limit_per_ip'] ?? 3) ?>" min="1" max="50">
        </div>
        <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:16px;">
            <button type="button" class="btn btn-outline btn-sm" onclick="closeRateLimitModal()">取消</button>
            <button type="submit" class="btn btn-sm">确定</button>
        </div>
    </div>
</div>
</form>
<script>
function openRateLimitModal() { document.getElementById('rateLimitModal').classList.add('active'); }
function closeRateLimitModal() { document.getElementById('rateLimitModal').classList.remove('active'); }
</script>
</body>
</html>
