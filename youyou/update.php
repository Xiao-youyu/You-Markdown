<?php
session_start();
require_once __DIR__ . '/../utils.php';
if (empty($_SESSION['cmt_user']) || ($_SESSION['cmt_user']['role'] ?? '') !== 'admin') {
    logUnauthorized('越权尝试访问版本更新');
    header('Location: ../?admin_login=1');
    exit;
}
$dataDir = '../data';
$configFile = $dataDir . '/.config.json';
$_siteConfig = [];
if (file_exists($configFile)) {
    $_siteConfig = json_decode(file_get_contents($configFile), true) ?: [];
}
$_siteTitle = $_siteConfig['site_title'] ?? 'You Markdown';
$channel = ($_siteConfig['update_channel'] ?? 'stable') === 'beta' ? 'beta' : 'stable';
$autoCheck = !empty($_siteConfig['auto_check_update']);
$ignoredVersion = $_siteConfig['ignored_version'] ?? '';

if (isset($_GET['ajax'])) {
    header('Content-Type: application/json; charset=utf-8');
    $action = $_GET['ajax'];

    if ($action === 'check') {
        $ch = ($_GET['channel'] ?? $channel) === 'beta' ? 'beta' : 'stable';
        $release = checkGitHubRelease($ch);
        if (!$release) {
            echo json_encode(['success' => false, 'error' => '获取版本信息失败，请稍后重试'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $hasUpdate = isNewVersion($release['version']);
        echo json_encode([
            'success' => true,
            'hasUpdate' => $hasUpdate,
            'current' => APP_VERSION,
            'latest' => $release['version'],
            'body' => $release['body'],
            'published_at' => $release['published_at'],
            'channel' => $ch
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($action === 'update') {
        $ch = $channel;
        $release = checkGitHubRelease($ch);
        if (!$release || empty($release['zipball_url'])) {
            echo json_encode(['success' => false, 'error' => '获取下载地址失败'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $zipUrl = $release['zipball_url'];
        $tmpDir = sys_get_temp_dir() . '/ym_update_' . uniqid();
        $zipFile = $tmpDir . '.zip';

        $opts = [
            'http' => [
                'method' => 'GET',
                'header' => ['User-Agent: You-Markdown'],
                'timeout' => 60,
                'ignore_errors' => true
            ]
        ];
        $ctx = stream_context_create($opts);
        $zipData = @file_get_contents($zipUrl, false, $ctx);
        if ($zipData === false || strlen($zipData) < 1000) {
            echo json_encode(['success' => false, 'error' => '下载失败，请检查网络'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        file_put_contents($zipFile, $zipData);

        $zip = new ZipArchive();
        if ($zip->open($zipFile) !== true) {
            @unlink($zipFile);
            echo json_encode(['success' => false, 'error' => '解压失败'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        mkdir($tmpDir, 0755, true);
        $zip->extractTo($tmpDir);
        $zip->close();
        @unlink($zipFile);

        $dirs = glob($tmpDir . '/*', GLOB_ONLYDIR);
        $srcDir = !empty($dirs) ? $dirs[0] : $tmpDir;

        $projectRoot = realpath(__DIR__ . '/..');
        $skipDirs = ['data', 'fonts'];
        $skipFiles = ['.htaccess', 'robots.txt'];

        function copyUpdateDir($src, $dst, $skipDirs, $skipFiles) {
            $dir = opendir($src);
            if (!$dir) return false;
            if (!is_dir($dst)) mkdir($dst, 0755, true);
            while (($file = readdir($dir)) !== false) {
                if ($file === '.' || $file === '..') continue;
                $srcPath = $src . '/' . $file;
                $dstPath = $dst . '/' . $file;
                if (is_dir($srcPath)) {
                    if (in_array($file, $skipDirs)) continue;
                    copyUpdateDir($srcPath, $dstPath, $skipDirs, $skipFiles);
                } else {
                    if (in_array($file, $skipFiles)) continue;
                    copy($srcPath, $dstPath);
                }
            }
            closedir($dir);
            return true;
        }

        $ok = copyUpdateDir($srcDir, $projectRoot, $skipDirs, $skipFiles);

        function removeDir($dir) {
            if (!is_dir($dir)) return;
            foreach (scandir($dir) as $f) {
                if ($f === '.' || $f === '..') continue;
                $p = $dir . '/' . $f;
                is_dir($p) ? removeDir($p) : unlink($p);
            }
            rmdir($dir);
        }
        removeDir($tmpDir);

        if ($ok) {
            $logFile = $dataDir . '/.update_log.json';
            $logs = file_exists($logFile) ? json_decode(file_get_contents($logFile), true) : [];
            if (!is_array($logs)) $logs = [];
            $logs[] = [
                'from' => APP_VERSION,
                'to' => $release['version'],
                'channel' => $ch,
                'time' => date('Y-m-d H:i:s')
            ];
            if (count($logs) > 20) $logs = array_slice($logs, -20);
            file_put_contents($logFile, json_encode($logs, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            @unlink($dataDir . '/.update_cache.json');
            echo json_encode(['success' => true, 'version' => $release['version']], JSON_UNESCAPED_UNICODE);
        } else {
            echo json_encode(['success' => false, 'error' => '文件复制失败'], JSON_UNESCAPED_UNICODE);
        }
        exit;
    }

    if ($action === 'ignore') {
        $ver = $_GET['version'] ?? '';
        if ($ver) {
            $_siteConfig['ignored_version'] = $ver;
            file_put_contents($configFile, json_encode($_siteConfig, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        }
        echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($action === 'save_channel') {
        $ch = ($_GET['channel'] ?? 'stable') === 'beta' ? 'beta' : 'stable';
        $_siteConfig['update_channel'] = $ch;
        file_put_contents($configFile, json_encode($_siteConfig, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        @unlink($dataDir . '/.update_cache.json');
        echo json_encode(['success' => true, 'channel' => $ch], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($action === 'save_autocheck') {
        $_siteConfig['auto_check_update'] = !empty($_GET['enabled']);
        file_put_contents($configFile, json_encode($_siteConfig, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
        exit;
    }

    echo json_encode(['success' => false, 'error' => '未知操作'], JSON_UNESCAPED_UNICODE);
    exit;
}

$logFile = $dataDir . '/.update_log.json';
$updateLogs = file_exists($logFile) ? json_decode(file_get_contents($logFile), true) : [];
if (!is_array($updateLogs)) $updateLogs = [];
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title>版本更新 - <?= htmlspecialchars($_siteTitle) ?></title>
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
.card{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);padding:18px 20px;box-shadow:var(--shadow);margin-bottom:12px}
.card-title{font-size:.95em;font-weight:600;margin-bottom:12px;display:flex;align-items:center;gap:8px}
.card-title svg{width:18px;height:18px;stroke:var(--accent);fill:none;stroke-width:2}
.version-display{display:flex;align-items:center;gap:12px;padding:14px 16px;background:var(--bg);border-radius:var(--radius-sm);margin-bottom:14px}
.version-tag{font-size:1.6em;font-weight:700;color:var(--accent);font-family:monospace}
.version-label{font-size:.82em;color:var(--text-muted)}
.channel-tabs{display:flex;gap:8px;margin-bottom:14px}
.channel-tab{flex:1;padding:10px;border:2px solid var(--border);border-radius:var(--radius-sm);text-align:center;cursor:pointer;transition:all .2s;background:var(--surface);font-family:inherit;font-size:.88em;font-weight:500;display:flex;align-items:center;justify-content:center}
.channel-tab.active{border-color:var(--accent);color:var(--accent);background:hsl(var(--accent-hue),var(--accent-sat),96%)}
.channel-tab:hover:not(.active){border-color:var(--text-muted)}
.toggle-row{display:flex;align-items:center;justify-content:space-between;padding:12px 0;border-top:1px solid var(--border)}
.toggle-label{font-size:.9em}
.toggle-desc{font-size:.78em;color:var(--text-muted);margin-top:2px}
.toggle{position:relative;width:44px;height:24px;flex-shrink:0}
.toggle input{opacity:0;width:0;height:0}
.toggle .slider{position:absolute;inset:0;background:#cbd5e1;border-radius:12px;cursor:pointer;transition:.2s}
.toggle .slider:before{content:'';position:absolute;width:18px;height:18px;border-radius:50%;background:#fff;left:3px;top:3px;transition:.2s}
.toggle input:checked+.slider{background:var(--accent)}
.toggle input:checked+.slider:before{transform:translateX(20px)}
.btn{background:var(--accent);color:#fff;border:none;padding:11px 24px;border-radius:8px;cursor:pointer;font-weight:600;font-size:14px;transition:all .2s;font-family:inherit;white-space:nowrap}
.btn:active{opacity:.85}
.btn:disabled{opacity:.5;cursor:not-allowed}
.btn-outline{background:transparent;border:1px solid var(--border);color:var(--text-secondary)}
.btn-outline:hover{border-color:var(--accent);color:var(--accent)}
.btn-sm{padding:8px 16px;font-size:13px}
.btn-row{display:flex;gap:10px;margin-top:16px;justify-content:flex-end}
.update-result{display:none;margin-top:14px;padding:16px 18px;background:var(--bg);border-radius:12px;border:1px solid var(--border)}
.update-result.show{display:block}
.update-result .ver-info{font-size:.95em;font-weight:600;margin-bottom:8px}
.update-result .ver-date{font-size:.82em;color:var(--text-muted);margin-bottom:10px}
.update-result .ver-body{font-size:.88em;color:var(--text-secondary);line-height:1.6;white-space:pre-wrap;max-height:200px;overflow-y:auto}
.log-item{display:flex;align-items:center;gap:10px;padding:10px 0;border-bottom:1px solid var(--border);font-size:.85em}
.log-item:last-child{border-bottom:none}
.log-ver{font-weight:600;font-family:monospace;color:var(--accent)}
.log-arrow{color:var(--text-muted)}
.log-time{color:var(--text-muted);font-size:.82em;margin-left:auto}
.spinner{display:inline-block;width:16px;height:16px;border:2px solid rgba(255,255,255,.3);border-top-color:#fff;border-radius:50%;animation:spin .6s linear infinite;vertical-align:middle;margin-right:6px}
@keyframes spin{to{transform:rotate(360deg)}}
.empty{text-align:center;padding:24px;color:var(--text-muted);font-size:.88em}
.badge{display:inline-block;padding:2px 8px;border-radius:4px;font-size:.75em;font-weight:600;margin-left:8px}
.badge.stable{background:#dbeafe;color:#2563eb}
.badge.beta{background:#fef3c7;color:#d97706}
.badge.has-update{background:#dcfce7;color:#16a34a;animation:pulse 2s infinite}
@keyframes pulse{0%,100%{opacity:1}50%{opacity:.7}}
.modal-mask{position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:500;display:none;align-items:center;justify-content:center;padding:16px;backdrop-filter:blur(4px)}
.modal-mask.show{display:flex}
.modal-box{background:var(--surface);border-radius:20px;width:420px;max-width:100%;overflow:hidden;box-shadow:0 12px 40px rgba(0,0,0,.15);animation:modalIn .25s cubic-bezier(.16,1,.3,1)}
@keyframes modalIn{from{opacity:0;transform:scale(.92) translateY(12px)}to{opacity:1;transform:none}}
.modal-head{padding:24px 24px 0;display:flex;align-items:center;justify-content:space-between}
.modal-title{font-size:18px;font-weight:700}
.modal-body{padding:16px 24px 24px}
.modal-body p{font-size:.9em;color:var(--text-secondary);line-height:1.6;margin-bottom:12px}
.modal-body .warn-text{color:#dc2626;font-weight:600;font-size:.95em;text-align:center}
.modal-actions{display:flex;gap:10px;justify-content:flex-end;align-items:center;padding:0 24px 24px}
.updating-spinner{width:48px;height:48px;border:3px solid var(--border);border-top-color:var(--accent);border-radius:50%;animation:spin 1s linear infinite;margin:0 auto 16px}
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
    <div class="page-title"><svg viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>版本更新</div>

    <div class="card">
        <div class="card-title"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>当前版本</div>
        <div class="version-display">
            <div>
                <div class="version-tag">v<?= htmlspecialchars(APP_VERSION) ?></div>
                <div class="version-label"><?= $channel === 'beta' ? '测试版' : '正式版' ?></div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-title"><svg viewBox="0 0 24 24"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>更新通道</div>
        <div class="channel-tabs">
            <div class="channel-tab <?= $channel === 'stable' ? 'active' : '' ?>" data-channel="stable" onclick="switchChannel('stable')">
                <svg viewBox="0 0 24 24" width="16" height="16" style="vertical-align:middle;margin-right:4px;stroke:currentColor;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg> 正式版
            </div>
            <div class="channel-tab <?= $channel === 'beta' ? 'active' : '' ?>" data-channel="beta" onclick="switchChannel('beta')">
                <svg viewBox="0 0 24 24" width="16" height="16" style="vertical-align:middle;margin-right:4px;stroke:currentColor;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/></svg> 测试版
            </div>
        </div>
        <div class="toggle-row">
            <div><div class="toggle-label">新版本自动提示</div><div class="toggle-desc">管理员进入后台时自动检查更新</div></div>
            <label class="toggle"><input type="checkbox" id="autoCheckToggle" <?= $autoCheck ? 'checked' : '' ?> onchange="saveAutoCheck()"><span class="slider"></span></label>
        </div>
    </div>

    <div class="card">
        <div class="card-title" style="justify-content:space-between"><div style="display:flex;align-items:center;gap:8px"><svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>检查更新</div><button class="btn btn-outline btn-sm" id="checkBtn" onclick="checkUpdate()">检查更新</button></div>
        <div class="update-result" id="updateResult">
            <div class="ver-info" id="verInfo"></div>
            <div class="ver-date" id="verDate"></div>
            <div class="ver-body" id="verBody"></div>
            <div class="btn-row">
                <button class="btn btn-outline btn-sm" id="ignoreBtn" onclick="ignoreVersion()" style="display:none">忽略此版本</button>
                <button class="btn btn-sm" id="updateBtn" onclick="doUpdate()" style="display:none">立即更新</button>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-title"><svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>更新记录</div>
        <?php if (empty($updateLogs)): ?>
            <div class="empty">暂无更新记录</div>
        <?php else: ?>
            <?php foreach (array_reverse($updateLogs) as $log): ?>
            <div class="log-item">
                <span class="log-ver">v<?= htmlspecialchars($log['from'] ?? '?') ?></span>
                <span class="log-arrow">→</span>
                <span class="log-ver">v<?= htmlspecialchars($log['to'] ?? '?') ?></span>
                <span class="badge <?= ($log['channel'] ?? 'stable') === 'beta' ? 'beta' : 'stable' ?>"><?= ($log['channel'] ?? 'stable') === 'beta' ? '测试' : '正式' ?></span>
                <span class="log-time"><?= htmlspecialchars($log['time'] ?? '') ?></span>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</main>

<div class="modal-mask" id="betaWarnModal">
    <div class="modal-box">
        <div class="modal-head">
            <div class="modal-title"><svg viewBox="0 0 24 24" width="20" height="20" style="vertical-align:middle;margin-right:6px;stroke:#d97706;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg> 切换到测试版</div>
        </div>
        <div class="modal-body">
            <div style="text-align:center;margin-bottom:12px"><svg viewBox="0 0 24 24" width="48" height="48" style="stroke:#dc2626;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg></div>
            <p class="warn-text">本版本为上线时测试的专用版本！非常不建议您选择此版本！更新后可能会造成多个bug！</p>
        </div>
        <div class="modal-actions">
            <button class="btn btn-outline btn-sm" onclick="closeBetaWarn()">取消</button>
            <button class="btn btn-sm" id="betaConfirmBtn" disabled style="opacity:.5">确定 (3s)</button>
        </div>
    </div>
</div>

<div class="modal-mask" id="updatingModal">
    <div class="modal-box">
        <div class="modal-head">
            <div class="modal-title">正在更新</div>
        </div>
        <div class="modal-body" style="text-align:center;padding:30px 24px">
            <div class="updating-spinner"></div>
            <p>正在下载并安装更新，请勿关闭页面...</p>
        </div>
    </div>
</div>

<script>
var currentChannel = '<?= $channel ?>';
var pendingChannel = null;
var latestVersion = '';

function switchChannel(ch) {
    if (ch === currentChannel) return;
    if (ch === 'beta') {
        pendingChannel = ch;
        showBetaWarn();
    } else {
        saveChannel(ch);
    }
}

function showBetaWarn() {
    var modal = document.getElementById('betaWarnModal');
    var btn = document.getElementById('betaConfirmBtn');
    modal.classList.add('show');
    btn.disabled = true;
    btn.style.opacity = '.5';
    var sec = 3;
    btn.textContent = '确定 (' + sec + 's)';
    var timer = setInterval(function() {
        sec--;
        if (sec <= 0) {
            clearInterval(timer);
            btn.disabled = false;
            btn.style.opacity = '1';
            btn.textContent = '确定';
        } else {
            btn.textContent = '确定 (' + sec + 's)';
        }
    }, 1000);
    btn.onclick = function() { closeBetaWarn(); saveChannel('beta'); };
}

function closeBetaWarn() {
    document.getElementById('betaWarnModal').classList.remove('show');
    if (pendingChannel === 'beta') {
        document.querySelectorAll('.channel-tab').forEach(function(t) { t.classList.toggle('active', t.dataset.channel === currentChannel); });
        pendingChannel = null;
    }
}

function saveChannel(ch) {
    fetch('update.php?ajax=save_channel&channel=' + ch)
        .then(function(r) { return r.json(); })
        .then(function(d) {
            if (d.success) {
                currentChannel = ch;
                document.querySelectorAll('.channel-tab').forEach(function(t) { t.classList.toggle('active', t.dataset.channel === ch); });
                document.querySelector('.version-label').textContent = ch === 'beta' ? '测试版' : '正式版';
            }
        });
}

function saveAutoCheck() {
    var enabled = document.getElementById('autoCheckToggle').checked;
    fetch('update.php?ajax=save_autocheck&enabled=' + (enabled ? '1' : '0'));
}

function checkUpdate() {
    var btn = document.getElementById('checkBtn');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner"></span>检查中...';
    document.getElementById('updateResult').classList.remove('show');

    fetch('update.php?ajax=check&channel=' + currentChannel)
        .then(function(r) { return r.json(); })
        .then(function(d) {
            var result = document.getElementById('updateResult');
            if (!d.success) {
                result.classList.add('show');
                document.getElementById('verInfo').innerHTML = '<svg viewBox="0 0 24 24" width="18" height="18" style="vertical-align:middle;margin-right:4px;stroke:#dc2626;fill:none;stroke-width:2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg> ' + (d.error || '检查失败');
                document.getElementById('verDate').textContent = '';
                document.getElementById('verBody').textContent = '';
                document.getElementById('ignoreBtn').style.display = 'none';
                document.getElementById('updateBtn').style.display = 'none';
                return;
            }
            latestVersion = d.latest;
            result.classList.add('show');
            if (d.hasUpdate) {
                document.getElementById('verInfo').innerHTML = '<svg viewBox="0 0 24 24" width="18" height="18" style="vertical-align:middle;margin-right:4px;stroke:#16a34a;fill:none;stroke-width:2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg> 发现新版本 v' + d.latest + ' <span class="badge has-update">可更新</span>';
                document.getElementById('verDate').textContent = '发布时间：' + d.published_at;
                document.getElementById('verBody').textContent = d.body || '暂无更新说明';
                document.getElementById('ignoreBtn').style.display = '';
                document.getElementById('updateBtn').style.display = '';
            } else {
                document.getElementById('verInfo').innerHTML = '<svg viewBox="0 0 24 24" width="18" height="18" style="vertical-align:middle;margin-right:4px;stroke:#16a34a;fill:none;stroke-width:2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg> 当前已是最新版本 v' + d.current;
                document.getElementById('verDate').textContent = '';
                document.getElementById('verBody').textContent = '';
                document.getElementById('ignoreBtn').style.display = 'none';
                document.getElementById('updateBtn').style.display = 'none';
            }
        })
        .catch(function() {
            document.getElementById('updateResult').classList.add('show');
            document.getElementById('verInfo').innerHTML = '<svg viewBox="0 0 24 24" width="18" height="18" style="vertical-align:middle;margin-right:4px;stroke:#dc2626;fill:none;stroke-width:2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg> 网络错误';
        })
        .finally(function() { btn.disabled = false; btn.textContent = '检查更新'; });
}

function ignoreVersion() {
    if (!latestVersion) return;
    fetch('update.php?ajax=ignore&version=' + latestVersion)
        .then(function(r) { return r.json(); })
        .then(function(d) {
            if (d.success) {
                document.getElementById('updateResult').classList.remove('show');
                showToast('已忽略版本 v' + latestVersion);
            }
        });
}

function doUpdate() {
    if (!confirm('确定要更新到最新版本吗？\n\n更新过程可能需要几十秒，期间请勿关闭页面。\ndata 目录和自定义字体不会被覆盖。')) return;
    document.getElementById('updatingModal').classList.add('show');
    document.getElementById('updateResult').classList.remove('show');

    fetch('update.php?ajax=update')
        .then(function(r) { return r.json(); })
        .then(function(d) {
            document.getElementById('updatingModal').classList.remove('show');
            if (d.success) {
                showToast('<svg viewBox="0 0 24 24" width="16" height="16" style="vertical-align:middle;margin-right:4px;stroke:#16a34a;fill:none;stroke-width:2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg> 更新成功！新版本 v' + d.version + '，请刷新页面');
                setTimeout(function() { location.reload(); }, 2000);
            } else {
                showToast('<svg viewBox="0 0 24 24" width="16" height="16" style="vertical-align:middle;margin-right:4px;stroke:#dc2626;fill:none;stroke-width:2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg> ' + (d.error || '更新失败'));
            }
        })
        .catch(function() {
            document.getElementById('updatingModal').classList.remove('show');
            showToast('<svg viewBox="0 0 24 24" width="16" height="16" style="vertical-align:middle;margin-right:4px;stroke:#dc2626;fill:none;stroke-width:2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg> 网络错误');
        });
}

var toastTimer = null;
function showToast(msg, dur) {
    dur = dur || 3000;
    var t = document.getElementById('toast');
    if (!t) {
        t = document.createElement('div');
        t.id = 'toast';
        t.style.cssText = 'position:fixed;bottom:24px;left:50%;transform:translateX(-50%);background:#1e293b;color:#fff;padding:10px 20px;border-radius:10px;font-size:14px;z-index:9999;opacity:0;transition:opacity .3s;pointer-events:none;max-width:90%;text-align:center';
        document.body.appendChild(t);
    }
    t.innerHTML = msg;
    t.style.opacity = '1';
    clearTimeout(toastTimer);
    toastTimer = setTimeout(function() { t.style.opacity = '0'; }, dur);
}
</script>
</body>
</html>
