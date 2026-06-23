<?php
session_start();
require_once __DIR__ . '/../utils.php';
if (empty($_SESSION['cmt_user']) || ($_SESSION['cmt_user']['role'] ?? '') !== 'admin') {
    logUnauthorized('越权尝试访问管理后台');
    header('Location: ../?admin_login=1');
    exit;
}
$user = $_SESSION['cmt_user'];
$dataDir = '../data';
$configFile = $dataDir . '/.config.json';
$_siteConfig = [];
if (file_exists($configFile)) {
    $_siteConfig = json_decode(file_get_contents($configFile), true) ?: [];
}
$_siteTitle = $_siteConfig['site_title'] ?? 'You Markdown';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title>后台管理 - <?= htmlspecialchars($_siteTitle) ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<style>
@font-face{font-family:'ChineseFont';src:url('../fonts/luoliti.ttf') format('truetype');font-display:swap}
@font-face{font-family:'EnglishFont';src:url('../fonts/roundfont.ttf') format('truetype');font-display:swap}
:root{--accent-hue:220;--accent-sat:60%;--accent:hsl(var(--accent-hue),var(--accent-sat),50%);--bg:hsl(var(--accent-hue),60%,96%);--surface:#fff;--border:#dce7f5;--text:#1e293b;--text-secondary:#475569;--text-muted:#94a3b8;--shadow:0 2px 8px rgba(0,0,0,.05);--shadow-md:0 4px 16px rgba(0,0,0,.06);--radius:14px;--radius-sm:10px}
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:'EnglishFont','ChineseFont',-apple-system,sans-serif;background:var(--bg);color:var(--text);min-height:100vh;min-height:100dvh;-webkit-tap-highlight-color:transparent}
.top-bar{position:sticky;top:0;z-index:100;background:var(--surface);border-bottom:1px solid var(--border);box-shadow:0 1px 2px rgba(0,0,0,.04);display:flex;align-items:center;justify-content:space-between;padding:0 16px;height:52px}
.brand{font-size:14px;font-weight:650;color:var(--text-secondary)}
.header-right{display:flex;align-items:center;gap:4px}
.icon-btn{width:36px;height:36px;border-radius:8px;background:transparent;border:none;color:var(--text-secondary);display:flex;align-items:center;justify-content:center;cursor:pointer;transition:all .2s;text-decoration:none}
.icon-btn:active{opacity:.7}
.icon-btn svg{width:20px;height:20px;stroke:currentColor;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}
.main{max-width:600px;margin:0 auto;padding:20px 16px 80px}
.page-title{font-size:1.3em;font-weight:650;margin-bottom:6px;display:flex;align-items:center;gap:8px}
.page-title svg{width:24px;height:24px;stroke:var(--accent);fill:none;stroke-width:2}
.page-desc{font-size:.88em;color:var(--text-muted);margin-bottom:24px}
.card{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);padding:18px 20px;box-shadow:var(--shadow);display:flex;align-items:center;gap:14px;cursor:pointer;transition:all .2s;text-decoration:none;color:var(--text);margin-bottom:12px}
.card:active{transform:scale(.98);box-shadow:var(--shadow-md)}
.card-icon{width:44px;height:44px;border-radius:12px;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.card-icon svg{width:22px;height:22px;stroke:currentColor;fill:none;stroke-width:2}
.card-icon.blue{background:#dbeafe;color:#2563eb}
.card-icon.green{background:#dcfce7;color:#16a34a}
.card-icon.orange{background:#ffedd5;color:#ea580c}
.card-icon.red{background:#fee2e2;color:#dc2626}
.card-info{flex:1;min-width:0}
.card-name{font-weight:600;font-size:.95em;margin-bottom:2px}
.card-desc{font-size:.82em;color:var(--text-muted)}
.card-arrow{color:var(--text-muted);flex-shrink:0}
.card-arrow svg{width:18px;height:18px;stroke:currentColor;fill:none;stroke-width:2}
.user-bar{margin-top:24px;padding:14px 16px;background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);display:flex;align-items:center;gap:10px;font-size:.88em;color:var(--text-secondary)}
.user-bar a{color:var(--accent);text-decoration:none;font-weight:500}
[data-theme="dark"]{--bg:hsl(var(--accent-hue),40%,8%);--surface:#161b22;--border:#30363d;--text:#e6edf3;--text-secondary:#b1bac4;--text-muted:#768390}
@media(min-width:641px){.main{padding:28px 20px 60px}.card{padding:20px 24px}}
</style>
</head>
<body>
<header class="top-bar">
    <div><span class="brand"><?= htmlspecialchars($_siteTitle) ?></span></div>
    <div class="header-right">
        <a class="icon-btn" href="../" title="返回主页"><svg viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg></a>
        <a class="icon-btn" href="../sc.php" title="文档管理"><svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg></a>
    </div>
</header>

<main class="main">
    <div class="page-title">
        <svg viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
        后台管理
    </div>
    <div class="page-desc">欢迎回来，<?= htmlspecialchars($user['nickname'] ?? '站长') ?></div>

    <a class="card" href="../sc.php">
        <div class="card-icon blue"><svg viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg></div>
        <div class="card-info"><div class="card-name">文档上传</div><div class="card-desc">上传、编辑、删除文档</div></div>
        <div class="card-arrow"><svg viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg></div>
    </a>

    <a class="card" href="config.php">
        <div class="card-icon green"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg></div>
        <div class="card-info"><div class="card-name">网站配置</div><div class="card-desc">标题、注册限制、评论开关、自动封禁</div></div>
        <div class="card-arrow"><svg viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg></div>
    </a>

    <a class="card" href="logs.php">
        <div class="card-icon orange"><svg viewBox="0 0 24 24"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg></div>
        <div class="card-info"><div class="card-name">异常日志</div><div class="card-desc">频繁错误登录、刷评论、刷注册</div></div>
        <div class="card-arrow"><svg viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg></div>
    </a>

    <a class="card" href="bans.php">
        <div class="card-icon red"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/></svg></div>
        <div class="card-info"><div class="card-name">封禁管理</div><div class="card-desc">查看、添加、解除 IP 封禁</div></div>
        <div class="card-arrow"><svg viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg></div>
    </a>

    <a class="card" href="update.php">
        <div class="card-icon" style="background:#f0fdf4;color:#16a34a;"><svg viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg></div>
        <div class="card-info"><div class="card-name">版本更新</div><div class="card-desc">检查更新、更新通道设置</div></div>
        <div class="card-arrow"><svg viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg></div>
    </a>

    <div class="user-bar">
        <span>当前身份：<?= htmlspecialchars($user['nickname'] ?? '站长') ?></span>
        <span style="margin-left:auto"><a href="../">返回主页</a></span>
    </div>
</main>

<style>
.upd-modal-mask{position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:500;display:none;align-items:center;justify-content:center;padding:16px;backdrop-filter:blur(4px)}
.upd-modal-mask.show{display:flex}
.upd-modal-box{background:var(--surface);border-radius:20px;width:420px;max-width:100%;overflow:hidden;box-shadow:0 12px 40px rgba(0,0,0,.15);animation:updModalIn .25s cubic-bezier(.16,1,.3,1)}
@keyframes updModalIn{from{opacity:0;transform:scale(.92) translateY(12px)}to{opacity:1;transform:none}}
.upd-modal-head{padding:24px 24px 0;display:flex;align-items:center;justify-content:space-between}
.upd-modal-title{font-size:18px;font-weight:700}
.upd-modal-body{padding:16px 24px 24px}
.upd-modal-body .upd-ver{font-size:1.4em;font-weight:700;color:var(--accent);font-family:monospace;margin-bottom:8px}
.upd-modal-body .upd-desc{font-size:.88em;color:var(--text-secondary);line-height:1.6;white-space:pre-wrap;max-height:180px;overflow-y:auto;margin-bottom:12px}
.upd-modal-actions{display:flex;gap:10px;justify-content:flex-end}
.upd-btn{border:none;padding:10px 20px;border-radius:8px;cursor:pointer;font-weight:600;font-size:13px;font-family:inherit;transition:all .2s}
.upd-btn:active{opacity:.85}
.upd-btn-primary{background:var(--accent);color:#fff}
.upd-btn-ghost{background:transparent;border:1px solid var(--border);color:var(--text-secondary)}
.upd-btn-ghost:hover{border-color:var(--accent);color:var(--accent)}
</style>
<div class="upd-modal-mask" id="updNotifyModal">
    <div class="upd-modal-box">
        <div class="upd-modal-head">
            <div class="upd-modal-title"><svg viewBox="0 0 24 24" width="20" height="20" style="vertical-align:middle;margin-right:6px;stroke:#16a34a;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg> 发现新版本</div>
        </div>
        <div class="upd-modal-body">
            <div class="upd-ver" id="updNotifyVer"></div>
            <div class="upd-desc" id="updNotifyBody"></div>
            <div class="upd-modal-actions">
                <button class="upd-btn upd-btn-ghost" id="updIgnoreBtn">忽略此版本</button>
                <button class="upd-btn upd-btn-primary" id="updGoBtn">去更新</button>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    var autoCheck = <?= !empty($_siteConfig['auto_check_update']) ? 'true' : 'false' ?>;
    var ignoredVer = <?= json_encode($_siteConfig['ignored_version'] ?? '') ?>;
    var channel = <?= json_encode($channel) ?>;
    if (!autoCheck) return;

    fetch('update.php?ajax=check&channel=' + channel)
        .then(function(r) { return r.json(); })
        .then(function(d) {
            if (!d.success || !d.hasUpdate) return;
            if (ignoredVer && d.latest === ignoredVer) return;
            document.getElementById('updNotifyVer').textContent = 'v' + d.latest;
            document.getElementById('updNotifyBody').textContent = d.body || '暂无更新说明';
            document.getElementById('updNotifyModal').classList.add('show');
            document.getElementById('updIgnoreBtn').onclick = function() {
                fetch('update.php?ajax=ignore&version=' + d.latest).then(function() {
                    document.getElementById('updNotifyModal').classList.remove('show');
                });
            };
            document.getElementById('updGoBtn').onclick = function() {
                window.location.href = 'update.php';
            };
        })
        .catch(function() {});
})();
</script>
</body>
</html>
