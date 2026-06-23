<?php
session_start();
require_once __DIR__ . '/../utils.php';
if (empty($_SESSION['cmt_user']) || ($_SESSION['cmt_user']['role'] ?? '') !== 'admin') {
    logUnauthorized('越权尝试访问管理后台(logs.php)');
    header('Location: ../?admin_login=1');
    exit;
}
$dataDir = '../data';
$logsFile = $dataDir . '/.logs.json';
$unauthFile = $dataDir . '/.unauthorized.json';

function loadLogs() {
    global $logsFile;
    if (!file_exists($logsFile)) return [];
    $data = json_decode(file_get_contents($logsFile), true);
    return is_array($data) ? $data : [];
}
function saveLogs($logs) {
    global $logsFile;
    file_put_contents($logsFile, json_encode($logs, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}

if (isset($_GET['clear'])) {
    saveLogs([]);
    header('Location: logs.php?cleared=1');
    exit;
}

if (isset($_GET['clear_unauth'])) {
    file_put_contents($unauthFile, json_encode([], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    header('Location: logs.php?cleared_unauth=1');
    exit;
}

$logs = loadLogs();
usort($logs, function($a, $b) { return strcmp($b['time'] ?? '', $a['time'] ?? ''); });

$unauthLogs = file_exists($unauthFile) ? json_decode(file_get_contents($unauthFile), true) : [];
if (!is_array($unauthLogs)) $unauthLogs = [];
usort($unauthLogs, function($a, $b) { return strcmp($b['time'] ?? '', $a['time'] ?? ''); });
$_siteConfig = [];
$_configFile = $dataDir . '/.config.json';
if (file_exists($_configFile)) {
    $_siteConfig = json_decode(file_get_contents($_configFile), true) ?: [];
}
$_siteTitle = $_siteConfig['site_title'] ?? 'You Markdown';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title>异常日志 - <?= htmlspecialchars($_siteTitle) ?></title>
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
.main{max-width:700px;margin:0 auto;padding:20px 16px 80px}
.page-title{font-size:1.3em;font-weight:650;margin-bottom:6px;display:flex;align-items:center;gap:8px}
.page-title svg{width:24px;height:24px;stroke:var(--accent);fill:none;stroke-width:2}
.page-desc{font-size:.88em;color:var(--text-muted);margin-bottom:20px}
.alert{padding:10px 14px;border-radius:8px;margin-bottom:14px;font-size:.88em;color:#16a34a;background:#f0fdf4;border:1px solid #bbf7d0}
.log-card{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius-sm);padding:14px 16px 24px;box-shadow:0 1px 2px rgba(0,0,0,.04);margin-bottom:10px;display:flex;align-items:center;gap:12px;position:relative}
.log-icon{width:36px;height:36px;border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.log-icon.warn{background:#ffedd5;color:#ea580c}
.log-icon svg{width:18px;height:18px;stroke:currentColor;fill:none;stroke-width:2}
.log-info{flex:1;min-width:0}
.log-ip{font-weight:600;font-size:.9em;font-family:monospace}
.log-action{font-size:.82em;color:var(--text-muted);margin-top:2px}
.log-time{font-size:.78em;color:var(--text-muted);position:absolute;right:16px;bottom:8px}
.empty{text-align:center;padding:40px 20px;color:var(--text-muted);font-size:.9em}
.top-actions{display:flex;justify-content:flex-end;margin-bottom:14px}
.btn-sm{padding:7px 14px;border-radius:6px;font-size:.82em;font-weight:500;cursor:pointer;border:1px solid var(--border);background:var(--surface);color:var(--text-secondary);font-family:inherit;text-decoration:none;transition:all .2s}
.btn-sm:active{border-color:var(--accent);color:var(--accent)}
.btn-danger{border-color:#fecaca;color:#dc2626}
.btn-danger:active{background:#fef2f2}
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
    <div class="page-title"><svg viewBox="0 0 24 24"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>异常日志</div>
    <div class="page-desc">记录异常行为：频繁错误登录、频繁评论、频繁注册等操作触发的自动封禁</div>
    <?php if (isset($_GET['cleared'])): ?><div class="alert">日志已清空</div><?php endif; ?>
    <?php if (isset($_GET['cleared_unauth'])): ?><div class="alert">越权访问日志已清空</div><?php endif; ?>
    <div class="top-actions"><a href="?clear=1" class="btn-sm btn-danger" onclick="return confirm('确定清空所有日志？')">清空日志</a></div>
    <?php if (empty($logs)): ?>
        <div class="empty">暂无异常日志</div>
    <?php else: ?>
        <?php foreach ($logs as $log): ?>
        <div class="log-card">
            <div class="log-icon warn"><svg viewBox="0 0 24 24"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg></div>
            <div class="log-info">
                <div class="log-ip"><?= htmlspecialchars($log['ip'] ?? '未知') ?></div>
                <div class="log-action"><?= htmlspecialchars($log['action'] ?? '') ?></div>
            </div>
            <div class="log-time"><?= htmlspecialchars($log['time'] ?? '') ?></div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <div class="page-title" style="margin-top:32px;"><svg viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>越权访问日志</div>
    <div class="page-desc">记录未登录用户尝试访问管理功能的行为</div>
    <?php if (!empty($unauthLogs)): ?><div class="top-actions"><a href="?clear_unauth=1" class="btn-sm btn-danger" onclick="return confirm('确定清空所有越权访问日志？')">清空日志</a></div><?php endif; ?>
    <?php if (empty($unauthLogs)): ?>
        <div class="empty">暂无越权访问记录</div>
    <?php else: ?>
        <?php foreach ($unauthLogs as $log): ?>
        <div class="log-card">
            <div class="log-icon" style="background:#fef3c7;color:#d97706;"><svg viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg></div>
            <div class="log-info">
                <div class="log-ip"><?= htmlspecialchars($log['ip'] ?? '未知') ?></div>
                <div class="log-action"><?= htmlspecialchars($log['action'] ?? '') ?></div>
                <div style="font-size:11px;color:var(--text-muted);margin-top:2px;">用户: <?= htmlspecialchars($log['user'] ?? '未知') ?> | UA: <?= htmlspecialchars(mb_substr($log['ua'] ?? '', 0, 60, 'UTF-8')) ?></div>
            </div>
            <div class="log-time"><?= htmlspecialchars($log['time'] ?? '') ?></div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</main>
</body>
</html>
