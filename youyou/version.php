<?php
session_start();
require_once __DIR__ . '/../utils.php';
if (!validateAdminSession()) {
    logUnauthorized('越权尝试访问版本信息');
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
checkAdminPath();
$channel = ($_siteConfig['update_channel'] ?? 'stable') === 'beta' ? 'beta' : 'stable';

/* AJAX: scan files for update */
if (isset($_GET['ajax']) && $_GET['ajax'] === 'update_files') {
    header('Content-Type: application/json; charset=utf-8');
    error_reporting(0); ini_set('display_errors', '0');
    $release = checkGitHubRelease($channel);
    if (!$release || empty($release['zipball_url'])) {
        echo json_encode(['success' => false, 'error' => '\u83b7\u53d6\u7248\u672c\u4fe1\u606f\u5931\u8d25'], JSON_UNESCAPED_UNICODE); exit;
    }
    $tmpDir = sys_get_temp_dir() . '/you_md_up_' . uniqid();
    $zipFile = $tmpDir . '.zip';
    if (!is_dir($tmpDir)) mkdir($tmpDir, 0755, true);
    $zipContent = @file_get_contents($release['zipball_url'], false, stream_context_create([
        'http' => ['timeout' => 60, 'User-Agent' => 'You-Markdown', 'follow_location' => true]
    ]));
    if ($zipContent === false) { echo json_encode(['success' => false, 'error' => '\u4e0b\u8f7d\u5931\u8d25'], JSON_UNESCAPED_UNICODE); exit; }
    file_put_contents($zipFile, $zipContent);
    $zip = new ZipArchive();
    if ($zip->open($zipFile) !== true) { @unlink($zipFile); echo json_encode(['success' => false, 'error' => '\u89e3\u538b\u5931\u8d25'], JSON_UNESCAPED_UNICODE); exit; }
    $zip->extractTo($tmpDir); $zip->close(); @unlink($zipFile);
    $dirs = array_filter(glob($tmpDir . '/*'), 'is_dir');
    $extractedRoot = $dirs ? reset($dirs) : $tmpDir;
    $siteRoot = realpath(__DIR__ . '/..');
    $changes = [];
    $protected = ['data/', '.htaccess', 'nginx.conf.example'];
    $skipDirs = ['.git', '.github', '.well-known', 'node_modules', 'trap'];
    $iter = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($extractedRoot, RecursiveDirectoryIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
    foreach ($iter as $file) {
        if ($file->isDir()) continue;
        $rel = str_replace($extractedRoot . '/', '', $file->getPathname());
        $skip = false;
        foreach ($skipDirs as $sd) { if (strpos($rel, $sd . '/') === 0 || $rel === $sd) { $skip = true; break; } }
        if ($skip) continue;
        foreach ($protected as $pf) { if (strpos($rel, $pf) === 0) { $skip = true; break; } }
        if ($skip) continue;
        $serverFile = $siteRoot . '/' . $rel;
        $gMd5 = md5_file($file->getPathname());
        $sMd5 = file_exists($serverFile) ? md5_file($serverFile) : null;
        $status = 'same';
        if ($sMd5 === null) $status = 'new';
        elseif ($gMd5 !== $sMd5) $status = 'changed';
        if ($status !== 'same') $changes[] = ['path' => $rel, 'status' => $status, 'github_md5' => $gMd5, 'server_md5' => $sMd5];
    }
    deleteDir($tmpDir);
    echo json_encode(['success' => true, 'version' => $release['version'], 'changes' => $changes], JSON_UNESCAPED_UNICODE);
    exit;
}

/* AJAX: apply update */
if (isset($_GET['ajax']) && $_GET['ajax'] === 'update_apply') {
    header('Content-Type: application/json; charset=utf-8');
    error_reporting(0); ini_set('display_errors', '0');
    $files = json_decode($_POST['files'] ?? '[]', true);
    if (empty($files)) { echo json_encode(['success' => false, 'error' => '\u672a\u9009\u62e9\u6587\u4ef6'], JSON_UNESCAPED_UNICODE); exit; }
    $release = checkGitHubRelease($channel);
    if (!$release || empty($release['zipball_url'])) { echo json_encode(['success' => false, 'error' => '\u83b7\u53d6\u5931\u8d25'], JSON_UNESCAPED_UNICODE); exit; }
    $tmpDir = sys_get_temp_dir() . '/you_md_up_' . uniqid();
    $zipFile = $tmpDir . '.zip';
    if (!is_dir($tmpDir)) mkdir($tmpDir, 0755, true);
    $zipContent = @file_get_contents($release['zipball_url'], false, stream_context_create([
        'http' => ['timeout' => 60, 'User-Agent' => 'You-Markdown', 'follow_location' => true]
    ]));
    if ($zipContent === false) { echo json_encode(['success' => false, 'error' => '\u4e0b\u8f7d\u5931\u8d25'], JSON_UNESCAPED_UNICODE); exit; }
    file_put_contents($zipFile, $zipContent);
    $zip = new ZipArchive();
    if ($zip->open($zipFile) !== true) { @unlink($zipFile); echo json_encode(['success' => false, 'error' => '\u89e3\u538b\u5931\u8d25'], JSON_UNESCAPED_UNICODE); exit; }
    $zip->extractTo($tmpDir); $zip->close(); @unlink($zipFile);
    $dirs = array_filter(glob($tmpDir . '/*'), 'is_dir');
    $extractedRoot = $dirs ? reset($dirs) : $tmpDir;
    $siteRoot = realpath(__DIR__ . '/..');
    $backupDir = $siteRoot . '/data/.update_backups/' . date('Ymd_His');
    if (!is_dir($backupDir)) mkdir($backupDir, 0755, true);
    $results = [];
    foreach ($files as $rel) {
        $src = $extractedRoot . '/' . $rel;
        $dst = $siteRoot . '/' . $rel;
        if (!file_exists($src)) { $results[] = ['path' => $rel, 'ok' => false, 'msg' => '\u6e90\u6587\u4ef6\u4e0d\u5b58\u5728']; continue; }
        if (file_exists($dst)) {
            $bakPath = $backupDir . '/' . $rel;
            $bakDir = dirname($bakPath);
            if (!is_dir($bakDir)) mkdir($bakDir, 0755, true);
            copy($dst, $bakPath);
        }
        $dstDir = dirname($dst);
        if (!is_dir($dstDir)) mkdir($dstDir, 0755, true);
        $srcMd5 = md5_file($src);
        if (copy($src, $dst)) {
            $dstMd5 = md5_file($dst);
            if ($srcMd5 === $dstMd5) {
                $results[] = ['path' => $rel, 'ok' => true, 'msg' => '\u66f4\u65b0\u6210\u529f', 'md5' => $dstMd5];
            } else {
                $results[] = ['path' => $rel, 'ok' => false, 'msg' => 'MD5\u6821\u9a8c\u5931\u8d25'];
            }
        } else {
            $results[] = ['path' => $rel, 'ok' => false, 'msg' => '\u5199\u5165\u5931\u8d25'];
        }
    }
    deleteDir($tmpDir);
    $logFile = $siteRoot . '/data/.update_log.json';
    $log = file_exists($logFile) ? json_decode(file_get_contents($logFile), true) ?? [] : [];
    $log[] = ['time' => date('Y-m-d H:i:s'), 'version' => $release['version'], 'files' => $results, 'backup' => $backupDir];
    file_put_contents($logFile, json_encode(array_slice($log, -20), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
    echo json_encode(['success' => true, 'results' => $results, 'backup_dir' => $backupDir, 'success_count' => count(array_filter($results, fn($r) => $r['ok'])), 'fail_count' => count(array_filter($results, fn($r) => !$r['ok']))], JSON_UNESCAPED_UNICODE);
    exit;
}

function deleteDir($dir) {
    if (!is_dir($dir)) return;
    $items = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($items as $item) { $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname()); }
    rmdir($dir);
}


/* AJAX: 检查更新 */
if (isset($_GET['ajax']) && $_GET['ajax'] === 'check') {
    error_reporting(0);
    ini_set('display_errors', '0');
    header('Content-Type: application/json; charset=utf-8');
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
        'body' => $release['body'] ?? '',
        'published_at' => $release['published_at'] ?? '',
        'channel' => $ch
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$release = checkGitHubRelease($channel);
$hasUpdate = false;
$latestVer = '';
$releaseBody = '';
$publishedAt = '';
if ($release) {
    $latestVer = $release['version'];
    $hasUpdate = isNewVersion($latestVer);
    $releaseBody = $release['body'] ?? '';
    $publishedAt = $release['published_at'] ?? '';
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title>网站版本 - <?= htmlspecialchars($_siteTitle) ?></title>
<style>
@font-face{font-family:'ChineseFont';src:url('../fonts/luoliti.ttf') format('truetype');font-display:swap}
@font-face{font-family:'EnglishFont';src:url('../fonts/roundfont.ttf') format('truetype');font-display:swap}
:root{--accent-hue:220;--accent-sat:60%;--accent:hsl(var(--accent-hue),var(--accent-sat),50%);--bg:hsl(var(--accent-hue),60%,96%);--surface:#fff;--border:#dce7f5;--text:#1e293b;--text-secondary:#475569;--text-muted:#94a3b8;--shadow:0 2px 8px rgba(0,0,0,.05);--shadow-md:0 4px 16px rgba(0,0,0,.06);--radius:14px;--radius-sm:10px}
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:'EnglishFont','ChineseFont',-apple-system,sans-serif;background:var(--bg);color:var(--text);min-height:100vh;min-height:100dvh;-webkit-tap-highlight-color:transparent}
.top-bar{position:sticky;top:0;z-index:100;background:var(--surface);border-bottom:1px solid var(--border);box-shadow:0 1px 2px rgba(0,0,0,.04);display:flex;align-items:center;justify-content:space-between;padding:0 16px;height:52px}
.brand{font-size:14px;font-weight:650;color:var(--text-secondary)}
.header-right{display:flex;align-items:center;gap:4px}
.icon-btn{width:36px;height:36px;border-radius:8px;background:transparent;border:none;color:var(--text-secondary);display:flex;align-items:center;justify-content:center;cursor:pointer;text-decoration:none}
.icon-btn:active{opacity:.7}
.icon-btn svg{width:20px;height:20px;stroke:currentColor;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}
.main{max-width:600px;margin:0 auto;padding:20px 16px 80px}

/* Hero version card */
.version-hero{background:var(--surface) url(https://free.picui.cn/free/2026/08/17/6a82db29a51ab.jpeg) center/cover no-repeat;border:1px solid var(--border);border-radius:20px;padding:64px 24px 52px;text-align:center;box-shadow:var(--shadow-md);margin-bottom:20px;position:relative;overflow:hidden}

.version-hero .os-icon{width:64px;height:64px;margin:0 auto 16px;background:linear-gradient(135deg,var(--accent),hsl(var(--accent-hue),var(--accent-sat),65%));border-radius:16px;display:flex;align-items:center;justify-content:center;box-shadow:0 4px 20px hsla(var(--accent-hue),var(--accent-sat),50%,.25)}
.version-hero .os-icon svg{width:32px;height:32px;stroke:#fff;fill:none;stroke-width:2}
.version-hero .os-name{font-size:2em;font-weight:800;margin-bottom:4px;padding-top:10px;background:linear-gradient(180deg,rgba(255,255,255,1) 0%,rgba(255,255,255,.85) 40%,rgba(200,220,255,.7) 70%,rgba(255,255,255,.9) 100%);-webkit-background-clip:text;background-clip:text;color:transparent;filter:drop-shadow(0 2px 6px rgba(0,0,0,.25)) drop-shadow(0 0 20px rgba(180,200,255,.3))}
.version-hero .ver-big{font-size:2.4em;font-weight:800;color:var(--accent);font-family:monospace;letter-spacing:-.02em;line-height:1.2}
.version-hero .ver-channel{display:inline-block;margin-top:8px;padding:4px 14px;border-radius:20px;font-size:.78em;font-weight:600;background:hsl(var(--accent-hue),var(--accent-sat),94%);color:var(--accent)}
.version-hero .ver-channel.beta{background:#fef3c7;color:#d97706}

/* 检查更新按钮 */
.check-btn{display:inline-flex;align-items:center;gap:6px;margin-top:31px;padding:10px 28px;border-radius:999px;border:1px solid rgba(255,255,255,.2);backdrop-filter:blur(24px) saturate(1.6);-webkit-backdrop-filter:blur(24px) saturate(1.6);background:rgba(255,255,255,.15);color:var(--text-secondary);font-size:.88em;font-weight:600;cursor:pointer;font-family:inherit;transition:all .4s cubic-bezier(.16,1,.3,1);white-space:nowrap;transform-origin:center}
.check-btn:hover{border-color:rgba(255,255,255,.3);color:var(--text-secondary)}
.check-btn:active{transform:scale(.96);outline:none;border-color:rgba(255,255,255,.3);color:var(--text-secondary)}
.check-btn:focus{outline:none;border-color:rgba(255,255,255,.3);color:var(--text-secondary)}
.check-btn:disabled{opacity:.6;cursor:not-allowed}
.check-btn svg{width:16px;height:16px;stroke:currentColor;fill:none;stroke-width:2;vertical-align:middle;margin-top:-1px}
.check-btn .spinner-sm{display:inline-block;width:14px;height:14px;vertical-align:middle;margin-top:-1px;border:2px solid rgba(255,255,255,.3);border-top-color:rgba(255,255,255,.8);border-radius:50%;animation:spin .6s linear infinite}
.check-btn.stretch{animation:btnStretch .5s cubic-bezier(.16,1,.3,1) forwards}
.check-btn.shrink{animation:btnShrink .4s cubic-bezier(.16,1,.3,1) forwards}
.check-btn.checking{background:rgba(255,255,255,.22);backdrop-filter:blur(24px) saturate(1.6);-webkit-backdrop-filter:blur(24px) saturate(1.6);border-color:rgba(255,255,255,.25);opacity:1}
.check-btn.ok{background:rgba(34,197,94,.3);backdrop-filter:blur(24px) saturate(1.6);-webkit-backdrop-filter:blur(24px) saturate(1.6);border-color:rgba(34,197,94,.5);color:#15803d;font-weight:700}
.check-btn.has-update{background:rgba(59,130,246,.25);backdrop-filter:blur(24px) saturate(1.6);-webkit-backdrop-filter:blur(24px) saturate(1.6);border-color:rgba(59,130,246,.4);color:#3b82f6}
.check-btn.error{background:rgba(239,68,68,.2);backdrop-filter:blur(24px) saturate(1.6);-webkit-backdrop-filter:blur(24px) saturate(1.6);border-color:rgba(239,68,68,.3);color:#dc2626}


/* Changelog card */
.changelog-card{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);box-shadow:var(--shadow);overflow:hidden;margin-bottom:16px}
.changelog-head{padding:16px 20px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:10px}
.changelog-head svg{width:18px;height:18px;stroke:var(--accent);fill:none;stroke-width:2}
.changelog-head .ch-title{font-weight:600;font-size:.95em}
.changelog-head .ch-ver{margin-left:auto;font-family:monospace;font-size:.85em;color:var(--accent);font-weight:600}
.changelog-body{padding:16px 20px;font-size:.88em;color:var(--text-secondary);line-height:1.75;white-space:pre-wrap;word-break:break-word;max-height:400px;overflow-y:auto}
.changelog-body:empty{color:var(--text-muted);text-align:center;padding:24px 20px}
.changelog-meta{padding:12px 20px;border-top:1px solid var(--border);font-size:.8em;color:var(--text-muted);display:flex;align-items:center;gap:6px}
.changelog-meta svg{width:14px;height:14px;stroke:currentColor;fill:none;stroke-width:2;flex-shrink:0}

/* Info rows */
.info-card{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);box-shadow:var(--shadow);overflow:hidden;margin-bottom:16px}
.info-row{display:flex;align-items:center;justify-content:space-between;padding:14px 20px;border-bottom:1px solid var(--border);font-size:.9em}
.info-row:last-child{border-bottom:none}
.info-row .info-label{color:var(--text-secondary)}
.info-row .info-value{font-weight:600;font-family:monospace;color:var(--text)}

/* Loading skeleton */
.skeleton{background:linear-gradient(90deg,var(--border) 25%,hsl(var(--accent-hue),20%,92%) 50%,var(--border) 75%);background-size:200% 100%;animation:shimmer 1.5s infinite;border-radius:6px;height:16px;display:inline-block}
@keyframes shimmer{0%{background-position:200% 0}100%{background-position:-200% 0}}
.skeleton-line{height:14px;margin-bottom:10px;border-radius:6px}
.skeleton-line:nth-child(1){width:80%}
.skeleton-line:nth-child(2){width:60%}
.skeleton-line:nth-child(3){width:90%}
.skeleton-line:nth-child(4){width:45%}

.error-state{text-align:center;padding:32px 20px;color:var(--text-muted)}
.error-state svg{width:40px;height:40px;stroke:var(--text-muted);fill:none;stroke-width:1.5;margin-bottom:12px;opacity:.5}
.error-state .err-title{font-weight:600;color:var(--text-secondary);margin-bottom:4px}
.error-state .err-desc{font-size:.85em}

.btn{background:var(--accent);color:#fff;border:none;padding:10px 20px;border-radius:8px;cursor:pointer;font-weight:600;font-size:13px;font-family:inherit;transition:all .2s;white-space:nowrap}
.btn:active{opacity:.85}
.btn-outline{background:transparent;border:1px solid var(--border);color:var(--text-secondary)}
.btn-outline:hover{border-color:var(--accent);color:var(--accent)}
.btn-sm{padding:8px 16px;font-size:13px}

@keyframes spin{to{transform:rotate(360deg)}}
@keyframes modalIn{from{transform:scale(.92);opacity:0}to{transform:scale(1);opacity:1}}
@keyframes modalOut{from{transform:scale(1);opacity:1}to{transform:scale(.92);opacity:0}}
@keyframes btnStretch{0%{transform:scaleX(1) scaleY(1)} 30%{transform:scaleX(1.15) scaleY(.92)} 60%{transform:scaleX(.97) scaleY(1.04)} 100%{transform:scaleX(1) scaleY(1)}}
@keyframes btnShrink{0%{transform:scaleX(1) scaleY(1)} 40%{transform:scaleX(.88) scaleY(1.08)} 70%{transform:scaleX(1.05) scaleY(.96)} 100%{transform:scaleX(1) scaleY(1)}}

[data-theme="dark"]{--bg:hsl(var(--accent-hue),40%,8%);--surface:#161b22;--border:#30363d;--text:#e6edf3;--text-secondary:#b1bac4;--text-muted:#768390}
@media(min-width:641px){.main{padding:28px 20px 60px}}
</style>
</head>
<body>
<header class="top-bar">
    <div><span class="brand"><?= htmlspecialchars($_siteTitle) ?></span></div>
    <div class="header-right">
        <a class="icon-btn" href="index.php" title="后台管理"><svg viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg></a>
        <a class="icon-btn" href="../" title="返回主页"><svg viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg></a>
    </div>
</header>
<main class="main">
    <!-- Hero: current version -->
    <div class="version-hero">
        <div class="os-name">You Markdown</div>
        <button class="check-btn" id="checkBtn" onclick="doCheck()">
            <span id="checkBtnText"><svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg> 检查更新</span>
        </button>
    </div>

    <!-- Latest release changelog -->
    <div class="changelog-card" id="changelogCard">
        <div class="changelog-head">
            <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
            <span class="ch-title">最新版本更新内容</span>
            <span class="ch-ver" id="changelogVer"><?= $hasUpdate ? 'v' . htmlspecialchars($latestVer) : 'v' . htmlspecialchars(APP_VERSION) ?></span>
        </div>
        <div class="changelog-body" id="changelogBody"><?php if ($releaseBody): ?><?= htmlspecialchars($releaseBody) ?><?php endif; ?></div>
        <?php if ($publishedAt): ?>
        <div class="changelog-meta">
            <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
            发布于 <?= htmlspecialchars($publishedAt) ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- System info -->
    <div class="info-card">

        <div class="info-row">
            <span class="info-label">仓库地址</span>
            <span class="info-value" style="font-size:.82em"><?= $channel === 'beta' ? 'Xiao-youyu/You-Markdown-Beta' : 'Xiao-youyu/You-Markdown' ?></span>
        </div>
        <div class="info-row">
            <span class="info-label">PHP 版本</span>
            <span class="info-value"><?= phpversion() ?></span>
        </div>
    </div>

    <!-- Update Modal -->
    <div id="updateModal" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.45);backdrop-filter:blur(4px);-webkit-backdrop-filter:blur(4px);display:none;align-items:center;justify-content:center;padding:20px">
        <div style="background:var(--surface);border-radius:16px;max-width:420px;width:100%;box-shadow:0 20px 60px rgba(0,0,0,.2);overflow:hidden;transform:scale(.92);opacity:0;transition:all .3s cubic-bezier(.16,1,.3,1)" id="modalBox">
            <div style="padding:24px 24px 0;text-align:center">
                <div style="width:48px;height:48px;margin:0 auto 12px;background:linear-gradient(135deg,var(--accent),hsl(var(--accent-hue),var(--accent-sat),65%));border-radius:14px;display:flex;align-items:center;justify-content:center"><svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg></div>
                <div style="font-size:1.1em;font-weight:700;margin-bottom:4px" id="modalTitle">发现新版本</div>
                <div style="font-size:.88em;color:var(--text-secondary)" id="modalVer"></div>
            </div>
            <div style="padding:16px 24px;max-height:200px;overflow-y:auto;font-size:.85em;color:var(--text-secondary);line-height:1.7;white-space:pre-wrap" id="modalBody"></div>
            <div id="modalProgress" style="display:none;padding:0 24px 8px">
                <div style="background:var(--border);border-radius:3px;height:6px;overflow:hidden"><div id="modalProgressBar" style="height:100%;background:var(--accent);border-radius:3px;width:0;transition:width .3s"></div></div>
                <div style="font-size:.78em;color:var(--text-muted);margin-top:6px;text-align:center" id="modalProgressText"></div>
            </div>
            <div style="padding:16px 24px 24px;display:flex;gap:10px;justify-content:flex-end" id="modalActions">
                <button onclick="closeModal()" style="padding:10px 22px;border-radius:8px;border:1px solid var(--border);background:transparent;color:var(--text-secondary);font-weight:600;font-size:.9em;cursor:pointer;font-family:inherit">取消</button>
                <button onclick="startUpdate()" id="modalUpdateBtn" style="padding:10px 22px;border-radius:8px;border:none;background:var(--accent);color:#fff;font-weight:600;font-size:.9em;cursor:pointer;font-family:inherit">立即更新</button>
            </div>
        </div>
    </div>

</main>
<script>
var currentChannel = '<?= $channel ?>';

/* 检查更新：始终显示 3 秒加载动画，结果有伸缩动画 */
function doCheck() {
    var btn = document.getElementById('checkBtn');
    var btnText = document.getElementById('checkBtnText');
    btn.disabled = true;
    btn.className = 'check-btn checking';
    btnText.innerHTML = '<span class="spinner-sm"></span> 检查中...';

    var fetchPromise = fetch('version.php?ajax=check&channel=' + currentChannel)
        .then(function(r) { return r.json(); });

    Promise.all([fetchPromise, new Promise(function(ok) { setTimeout(ok, 3000); })])
        .then(function(arr) {
            var d = arr[0];
            if (!d.success) {
                btn.className = 'check-btn error';
                btnText.innerHTML = '✗ ' + (d.error || '检查失败');
            } else if (d.hasUpdate) {
                btn.className = 'check-btn has-update';
                btnText.innerHTML = '发现新版本 v' + d.latest + ' →';
                btn.onclick = function() { showUpdateModal(d); };
                if (d.body) {
                    document.getElementById('changelogBody').textContent = d.body;
                    document.getElementById('changelogVer').textContent = 'v' + d.latest;
                    var meta = document.querySelector('.changelog-meta');
                    if (d.published_at) {
                        if (!meta) {
                            meta = document.createElement('div');
                            meta.className = 'changelog-meta';
                            meta.innerHTML = '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>';
                            document.getElementById('changelogCard').appendChild(meta);
                        }
                        meta.innerHTML = '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg> 发布于 ' + d.published_at;
                    }
                }
            } else {
                btn.className = 'check-btn ok';
                btnText.innerHTML = '✓ 已是最新 v' + d.current;
            }
        })
        .catch(function() {
            btn.className = 'check-btn error';
            btnText.innerHTML = '✗ 网络错误';
        })
        .finally(function() {
            btn.disabled = false;
            setTimeout(function() {
                btn.className = 'check-btn';
                btnText.innerHTML = '<svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg> 检查更新';
                btn.onclick = doCheck;
            }, 3000);
        });
}

/* 页面加载时若无 changelog 内容则自动拉取 */
(function() {
    var body = document.getElementById('changelogBody');
    if (body && !body.textContent.trim()) {
        body.innerHTML = '<div style="text-align:center;padding:8px 0"><span class="skeleton skeleton-line" style="width:80%"></span><span class="skeleton skeleton-line" style="width:60%"></span><span class="skeleton skeleton-line" style="width:90%"></span></div>';
        fetch('version.php?ajax=check&channel=' + currentChannel)
            .then(function(r) { return r.json(); })
            .then(function(d) {
                if (d.success && d.body) {
                    body.textContent = d.body;
                    if (d.published_at) {
                        var meta = document.querySelector('.changelog-meta');
                        if (!meta) {
                            meta = document.createElement('div');
                            meta.className = 'changelog-meta';
                            meta.innerHTML = '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>';
                            document.getElementById('changelogCard').appendChild(meta);
                        }
                        meta.innerHTML += ' 发布于 ' + d.published_at;
                    }
                    if (d.latest) {
                        document.getElementById('changelogVer').textContent = 'v' + d.latest;
                    }
                } else {
                    body.innerHTML = '<div class="error-state"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg><div class="err-title">暂无更新内容</div><div class="err-desc">无法获取版本更新信息</div></div>';
                }
            })
            .catch(function() {
                body.innerHTML = '<div class="error-state"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg><div class="err-title">获取失败</div><div class="err-desc">网络错误，请稍后重试</div></div>';
            });
    }
})();

var _updateData = null;

function showUpdateModal(d) {
    _updateData = d;
    document.getElementById('modalVer').textContent = 'v' + d.current + ' → v' + d.latest;
    document.getElementById('modalBody').textContent = d.body || '\u6682\u65e0\u66f4\u65b0\u65e5\u5fd7';
    document.getElementById('modalProgress').style.display = 'none';
    document.getElementById('modalActions').style.display = '';
    document.getElementById('modalUpdateBtn').disabled = false;
    document.getElementById('modalUpdateBtn').textContent = '\u7acb\u5373\u66f4\u65b0';
    var modal = document.getElementById('updateModal');
    modal.style.display = 'flex';
    requestAnimationFrame(function() {
        document.getElementById('modalBox').style.transform = 'scale(1)';
        document.getElementById('modalBox').style.opacity = '1';
    });
}

function closeModal() {
    var box = document.getElementById('modalBox');
    box.style.transform = 'scale(.92)';
    box.style.opacity = '0';
    setTimeout(function() { document.getElementById('updateModal').style.display = 'none'; }, 300);
}

function startUpdate() {
    var btn = document.getElementById('modalUpdateBtn');
    btn.disabled = true;
    btn.textContent = '\u626b\u63cf\u4e2d...';
    document.getElementById('modalProgress').style.display = '';
    document.getElementById('modalProgressBar').style.width = '10%';
    document.getElementById('modalProgressText').textContent = '\u6b63\u5728\u4ece GitHub \u4e0b\u8f7d\u66f4\u65b0\u5305\u5e76\u6821\u9a8c MD5...';

    fetch('version.php?ajax=update_files', { method: 'POST' })
        .then(function(r) { return r.json(); })
        .then(function(d) {
            if (!d.success) {
                document.getElementById('modalProgressText').textContent = '\u2717 ' + (d.error || '\u5931\u8d25');
                btn.textContent = '\u91cd\u8bd5';
                btn.disabled = false;
                return;
            }
            if (d.changes.length === 0) {
                document.getElementById('modalProgressBar').style.width = '100%';
                document.getElementById('modalProgressText').textContent = '\u2713 \u6240\u6709\u6587\u4ef6\u5df2\u662f\u6700\u65b0';
                btn.textContent = '\u5b8c\u6210';
                setTimeout(closeModal, 2000);
                return;
            }
            document.getElementById('modalProgressBar').style.width = '30%';
            document.getElementById('modalProgressText').textContent = '\u53d1\u73b0 ' + d.changes.length + ' \u4e2a\u6587\u4ef6\u53d8\u66f4\uff0c\u6b63\u5728\u5e94\u7528...';
            btn.textContent = '\u66f4\u65b0\u4e2d...';

            // Apply update
            var files = d.changes.map(function(c) { return c.path; });
            fetch('version.php?ajax=update_apply', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'files=' + encodeURIComponent(JSON.stringify(files))
            })
            .then(function(r) { return r.json(); })
            .then(function(r) {
                document.getElementById('modalProgressBar').style.width = '100%';
                if (r.success) {
                    document.getElementById('modalProgressText').textContent = '\u2713 \u66f4\u65b0\u6210\u529f\uff0c' + r.success_count + ' \u4e2a\u6587\u4ef6\u5df2\u66f4\u65b0';
                    btn.textContent = '\u5b8c\u6210';
                    setTimeout(function() { closeModal(); location.reload(); }, 2000);
                } else {
                    document.getElementById('modalProgressText').textContent = '\u2717 ' + (r.error || '\u66f4\u65b0\u5931\u8d25');
                    btn.textContent = '\u91cd\u8bd5';
                    btn.disabled = false;
                }
            })
            .catch(function() {
                document.getElementById('modalProgressText').textContent = '\u2717 \u7f51\u7edc\u9519\u8bef';
                btn.textContent = '\u91cd\u8bd5';
                btn.disabled = false;
            });
        })
        .catch(function() {
            document.getElementById('modalProgressText').textContent = '\u2717 \u7f51\u7edc\u9519\u8bef';
            btn.textContent = '\u91cd\u8bd5';
            btn.disabled = false;
        });
}

// ESC to close modal
document.addEventListener('keydown', function(e) { if (e.key === 'Escape') closeModal(); });

</script>
</body>
</html>
