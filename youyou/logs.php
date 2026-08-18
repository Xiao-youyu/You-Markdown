<?php
session_start();
require_once __DIR__ . '/../utils.php';
if (!validateAdminSession()) {
    logUnauthorized('越权尝试访问管理后台(logs.php)');
    header('Location: ../?admin_login=1');
    exit;
}
$dataDir = '../data';
$logsFile = $dataDir . '/.logs.json';
$unauthFile = $dataDir . '/.unauthorized.json';
$opsFile = $dataDir . '/.operations.log';
$_siteConfig = [];
$_configFile = $dataDir . '/.config.json';
if (file_exists($_configFile)) {
    $_siteConfig = json_decode(file_get_contents($_configFile), true) ?: [];
}
$_siteTitle = $_siteConfig['site_title'] ?? 'You Markdown';
checkAdminPath();

if (isset($_GET['download'])) {
    $type = $_GET['download'];
    $filename = '';
    $content = '';
    if ($type === 'abnormal') {
        $logs = file_exists($logsFile) ? json_decode(file_get_contents($logsFile), true) : [];
        if (!is_array($logs)) $logs = [];
        $filename = 'abnormal_logs_' . date('Ymd_His') . '.txt';
        foreach ($logs as $l) { $content .= ($l['time'] ?? '') . ' | ' . ($l['ip'] ?? '') . ' | ' . ($l['action'] ?? '') . "\n"; }
    } elseif ($type === 'unauthorized') {
        $logs = file_exists($unauthFile) ? json_decode(file_get_contents($unauthFile), true) : [];
        if (!is_array($logs)) $logs = [];
        $filename = 'unauthorized_logs_' . date('Ymd_His') . '.txt';
        foreach ($logs as $l) { $content .= ($l['time'] ?? '') . ' | ' . ($l['ip'] ?? '') . ' | ' . ($l['user'] ?? '') . ' | ' . ($l['action'] ?? '') . ' | UA: ' . ($l['ua'] ?? '') . "\n"; }
    } elseif ($type === 'operations') {
        $content = file_exists($opsFile) ? file_get_contents($opsFile) : '';
        $filename = 'operation_logs_' . date('Ymd_His') . '.txt';
    }
    if ($filename) {
        logOperation('下载日志', $type);
        header('Content-Type: text/plain; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($content));
        echo $content;
        exit;
    }
}

if (isset($_GET['clear'])) {
    $type = $_GET['clear'];
    if ($type === 'abnormal') file_put_contents($logsFile, json_encode([], JSON_UNESCAPED_UNICODE), LOCK_EX);
    elseif ($type === 'unauthorized') file_put_contents($unauthFile, json_encode([], JSON_UNESCAPED_UNICODE), LOCK_EX);
    elseif ($type === 'operations') file_put_contents($opsFile, '', LOCK_EX);
    logOperation('清空日志', $type);
    header('Location: logs.php?cleared=1&tab=' . urlencode($type));
    exit;
}

$logs = file_exists($logsFile) ? json_decode(file_get_contents($logsFile), true) : [];
if (!is_array($logs)) $logs = [];
usort($logs, function($a, $b) { return strcmp($b['time'] ?? '', $a['time'] ?? ''); });

$unauthLogs = file_exists($unauthFile) ? json_decode(file_get_contents($unauthFile), true) : [];
if (!is_array($unauthLogs)) $unauthLogs = [];
usort($unauthLogs, function($a, $b) { return strcmp($b['time'] ?? '', $a['time'] ?? ''); });

$opsLogs = [];
if (file_exists($opsFile)) {
    $lines = file($opsFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach (array_reverse($lines) as $line) {
        $parts = explode(' | ', $line, 4);
        $opsLogs[] = ['time' => $parts[0] ?? '', 'ip' => $parts[1] ?? '', 'user' => $parts[2] ?? '', 'action' => $parts[3] ?? ''];
    }
}

$tab = $_GET['tab'] ?? 'abnormal';
if (!in_array($tab, ['abnormal', 'unauthorized', 'operations'])) $tab = 'abnormal';

// Search
$search = trim($_GET['search'] ?? '');
if ($search !== '') {
    $s = mb_strtolower($search, 'UTF-8');
    $logs = array_filter($logs, function($l) use ($s) {
        return mb_stripos($l['ip'] ?? '', $s) !== false || mb_stripos($l['action'] ?? '', $s) !== false;
    });
    $unauthLogs = array_filter($unauthLogs, function($l) use ($s) {
        return mb_stripos($l['ip'] ?? '', $s) !== false || mb_stripos($l['action'] ?? '', $s) !== false || mb_stripos($l['user'] ?? '', $s) !== false;
    });
    $opsLogs = array_filter($opsLogs, function($l) use ($s) {
        return mb_stripos($l['ip'] ?? '', $s) !== false || mb_stripos($l['action'] ?? '', $s) !== false || mb_stripos($l['user'] ?? '', $s) !== false;
    });
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title>网站日志 - <?= htmlspecialchars($_siteTitle) ?></title>
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
.alert{padding:10px 14px;border-radius:8px;margin-bottom:14px;font-size:.88em;color:#16a34a;background:#f0fdf4;border:1px solid #bbf7d0;animation:fadeSlide .3s ease}
@keyframes fadeSlide{from{opacity:0;transform:translateY(-6px)}to{opacity:1;transform:translateY(0)}}
.tabs{display:flex;gap:4px;background:var(--bg);border:1px solid var(--border);border-radius:10px;padding:4px;margin-bottom:16px}
.tab-btn{flex:1;padding:8px 12px;border:none;border-radius:8px;background:transparent;font-size:.85em;font-weight:500;color:var(--text-secondary);cursor:pointer;font-family:inherit;white-space:nowrap;transition:all .2s}
.tab-btn.active{background:var(--surface);color:var(--accent);box-shadow:0 1px 3px rgba(0,0,0,.08);font-weight:600}
.search-bar{display:flex;gap:8px;margin-bottom:14px}
.search-input{flex:1;padding:9px 11px;border:1px solid var(--border);border-radius:8px;background:var(--surface);color:var(--text);font-family:inherit;font-size:14px;outline:none;transition:border-color .2s}
.search-input:focus{border-color:var(--accent)}
.top-actions{display:flex;gap:8px;justify-content:flex-end;margin-bottom:14px;flex-wrap:wrap}
.btn-sm{padding:7px 14px;border-radius:6px;font-size:.82em;font-weight:500;cursor:pointer;border:1px solid var(--border);background:var(--surface);color:var(--text-secondary);font-family:inherit;text-decoration:none;transition:all .2s}
.btn-sm:active{border-color:var(--accent);color:var(--accent)}
.btn-primary{background:var(--accent);color:#fff;border-color:var(--accent)}
.btn-primary:active{opacity:.85}
.btn-danger{border-color:#fecaca;color:#dc2626}
.btn-danger:active{background:#fef2f2}
.log-card{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius-sm);padding:14px 16px;box-shadow:0 1px 2px rgba(0,0,0,.04);margin-bottom:10px}
.log-top{display:flex;align-items:center;gap:12px}
.log-icon{width:36px;height:36px;border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.log-icon.warn{background:#ffedd5;color:#ea580c}
.log-icon.lock{background:#fef3c7;color:#d97706}
.log-icon.ops{background:#dbeafe;color:#2563eb}
.log-icon svg{width:18px;height:18px;stroke:currentColor;fill:none;stroke-width:2}
.log-info{flex:1;min-width:0}
.log-row1{display:flex;align-items:center;justify-content:space-between;gap:8px;margin-bottom:2px}
.log-ip{font-weight:600;font-size:.9em;font-family:monospace}
.log-time{font-size:.78em;color:var(--text-muted);white-space:nowrap;flex-shrink:0}
.log-action{font-size:.82em;color:var(--text-muted);word-break:break-all}
.log-user{font-size:.78em;color:var(--text-muted);margin-top:2px;word-break:break-all}
.empty{text-align:center;padding:40px 20px;color:var(--text-muted);font-size:.9em}
.count-badge{display:inline-flex;align-items:center;justify-content:center;min-width:18px;height:18px;padding:0 5px;border-radius:9px;background:var(--accent);color:#fff;font-size:.7em;font-weight:600;margin-left:4px}
.search-result-info{margin-bottom:14px;font-size:.85em;color:var(--text-muted)}
.search-result-info a{color:var(--accent);margin-left:8px;text-decoration:none}
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
    <div class="page-title"><svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>网站日志</div>
    <div class="page-desc">查看异常行为、越权访问和管理员操作记录</div>
    <?php if (isset($_GET['cleared'])): ?><div class="alert">日志已清空</div><?php endif; ?>

    <div class="tabs">
        <button class="tab-btn <?= $tab==='abnormal'?'active':'' ?>" onclick="switchTab('abnormal')">异常日志<span class="count-badge"><?= count($logs) ?></span></button>
        <button class="tab-btn <?= $tab==='unauthorized'?'active':'' ?>" onclick="switchTab('unauthorized')">越权日志<span class="count-badge"><?= count($unauthLogs) ?></span></button>
        <button class="tab-btn <?= $tab==='operations'?'active':'' ?>" onclick="switchTab('operations')">操作日志<span class="count-badge"><?= count($opsLogs) ?></span></button>
    </div>

    <form class="search-bar" method="get">
        <input type="hidden" name="tab" value="<?= htmlspecialchars($tab) ?>">
        <input class="search-input" type="text" name="search" placeholder="搜索 IP、操作内容..." value="<?= htmlspecialchars($search) ?>">
        <button type="submit" class="btn-sm btn-primary">搜索</button>
    </form>
    <?php if ($search !== ''): ?>
    <div class="search-result-info">
        搜索 "<?= htmlspecialchars($search) ?>" · <?php if($tab==='abnormal'):?><?=count($logs)?>条<?php elseif($tab==='unauthorized'):?><?=count($unauthLogs)?>条<?php else:?><?=count($opsLogs)?>条<?php endif;?>
        <a href="?tab=<?= htmlspecialchars($tab) ?>">清除搜索</a>
    </div>
    <?php endif; ?>
    <div class="top-actions">
        <a href="?download=<?= htmlspecialchars($tab) ?>&search=<?= urlencode($search) ?>" class="btn-sm btn-primary">下载日志</a>
        <a href="?clear=<?= htmlspecialchars($tab) ?>&tab=<?= htmlspecialchars($tab) ?>" class="btn-sm btn-danger" onclick="return confirm('确定清空当前日志？')">清空日志</a>
    </div>

<?php if ($tab === 'abnormal'): ?>
    <?php if (empty($logs)): ?>
        <div class="empty">暂无异常日志</div>
    <?php else: foreach ($logs as $log): ?>
        <div class="log-card">
            <div class="log-top">
                <div class="log-icon warn"><svg viewBox="0 0 24 24"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg></div>
                <div class="log-info">
                    <div class="log-row1"><span class="log-ip"><?= htmlspecialchars($log['ip'] ?? '未知') ?></span><span class="log-time"><?= htmlspecialchars($log['time'] ?? '') ?></span></div>
                    <div class="log-action"><?= htmlspecialchars($log['action'] ?? '') ?></div>
                </div>
            </div>
        </div>
    <?php endforeach; endif; ?>

<?php elseif ($tab === 'unauthorized'): ?>
    <?php if (empty($unauthLogs)): ?>
        <div class="empty">暂无越权访问记录</div>
    <?php else: foreach ($unauthLogs as $log): ?>
        <div class="log-card">
            <div class="log-top">
                <div class="log-icon lock"><svg viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg></div>
                <div class="log-info">
                    <div class="log-row1"><span class="log-ip"><?= htmlspecialchars($log['ip'] ?? '未知') ?></span><span class="log-time"><?= htmlspecialchars($log['time'] ?? '') ?></span></div>
                    <div class="log-action"><?= htmlspecialchars($log['action'] ?? '') ?></div>
                    <div class="log-user"><?= htmlspecialchars($log['user'] ?? '未知') ?> · UA: <?= htmlspecialchars(mb_substr($log['ua'] ?? '', 0, 60, 'UTF-8')) ?></div>
                </div>
            </div>
        </div>
    <?php endforeach; endif; ?>

<?php elseif ($tab === 'operations'): ?>
    <?php if (empty($opsLogs)): ?>
        <div class="empty">暂无操作记录</div>
    <?php else: foreach ($opsLogs as $log): ?>
        <div class="log-card">
            <div class="log-top">
                <div class="log-icon ops"><svg viewBox="0 0 24 24"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg></div>
                <div class="log-info">
                    <div class="log-row1"><span class="log-ip"><?= htmlspecialchars($log['ip'] ?? '未知') ?></span><span class="log-time"><?= htmlspecialchars($log['time'] ?? '') ?></span></div>
                    <div class="log-action"><?= htmlspecialchars($log['action'] ?? '') ?></div>
                    <div class="log-user"><?= htmlspecialchars($log['user'] ?? '') ?></div>
                </div>
            </div>
        </div>
    <?php endforeach; endif; ?>
<?php endif; ?>
</main>
<script>
function switchTab(tab) {
    var search = new URLSearchParams(window.location.search).get('search') || '';
    window.location.href = '?tab=' + tab + (search ? '&search=' + encodeURIComponent(search) : '');
}
</script>
</body>
</html>
