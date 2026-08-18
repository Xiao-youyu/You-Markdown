<?php
session_start();
require_once __DIR__ . '/../utils.php';
if (!validateAdminSession()) {
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
    $config['music_playlist_id'] = trim($_POST['music_playlist_id'] ?? ($config['music_playlist_id'] ?? '3778678'));
    // Music sources (JSON from hidden input)
    $sourcesJson = $_POST['music_sources_json'] ?? '';
    if (!empty($sourcesJson)) {
        $decoded = json_decode($sourcesJson, true);
        if (is_array($decoded)) $config['music_sources'] = $decoded;
    }
    $config['active_music_source'] = trim($_POST['active_music_source'] ?? ($config['active_music_source'] ?? ''));
    // Remove legacy keys (migrated to music_sources)
    unset($config['music_cookies'], $config['music_source'], $config['music_quality'], $config['music_api_url'], $config['music_api_key']);
    // OAuth config
    $config['oauth_enabled'] = isset($_POST['oauth_enabled']);
    $config['oauth_name'] = trim($_POST['oauth_name'] ?? ($config['oauth_name'] ?? 'Youyun'));
    $config['oauth_icon'] = trim($_POST['oauth_icon'] ?? ($config['oauth_icon'] ?? ''));
    $config['oauth_base_url'] = trim($_POST['oauth_base_url'] ?? ($config['oauth_base_url'] ?? ''));
    $config['oauth_client_id'] = trim($_POST['oauth_client_id'] ?? ($config['oauth_client_id'] ?? ''));
    $config['oauth_client_secret'] = trim($_POST['oauth_client_secret'] ?? ($config['oauth_client_secret'] ?? ''));
    $config['oauth_auth_path'] = trim($_POST['oauth_auth_path'] ?? ($config['oauth_auth_path'] ?? '/api/oauth.php?action=authorize'));
    $config['oauth_token_path'] = trim($_POST['oauth_token_path'] ?? ($config['oauth_token_path'] ?? '/api/oauth.php?action=token'));
    $config['oauth_verify_path'] = trim($_POST['oauth_verify_path'] ?? ($config['oauth_verify_path'] ?? '/api/oauth.php?action=verify'));
    // Announcement config
    $newAnnTitle = trim($_POST['ann_title'] ?? '');
    $newAnnContent = trim($_POST['ann_content'] ?? '');
    $newAnnCountdown = max(0, intval($_POST['ann_countdown'] ?? 3));
    $oldAnn = $config['announcement'] ?? [];
    $config['announcement'] = [
        'title' => $newAnnTitle,
        'content' => $newAnnContent,
        'countdown' => $newAnnCountdown,
        'updated_at' => ($oldAnn['title'] !== $newAnnTitle || $oldAnn['content'] !== $newAnnContent) ? time() : ($oldAnn['updated_at'] ?? time())
    ];
        // 自定义后台入口 - 支持目录重命名
    $newPath = preg_replace('/[^a-zA-Z0-9_\-]/', '', trim($_POST['admin_path'] ?? ''));
    if (empty($newPath)) $newPath = 'youyou';
    $oldPath = $config['admin_path'] ?? 'youyou';
    $config['admin_path'] = $newPath;
    // 拦截异常IP注册（代理/VPN检测）
    $config['block_proxy'] = !empty($_POST['block_proxy']);
    saveConfig($config);
    // 如果路径变更，重命名物理目录
    if ($newPath !== $oldPath) {
        $projectRoot = realpath(__DIR__ . '/..');
        $oldDir = $projectRoot . '/' . $oldPath;
        $newDir = $projectRoot . '/' . $newPath;
        if (is_dir($oldDir) && !is_dir($newDir)) {
            if (rename($oldDir, $newDir)) {
                $msg = '后台入口已更改为 /' . htmlspecialchars($newPath) . '/';
            } else {
                $msg = '目录重命名失败，请检查权限';
            }
        } elseif (is_dir($newDir)) {
            $msg = '目标路径 /' . htmlspecialchars($newPath) . '/ 已存在，仅更新配置';
        } else {
            $msg = '原目录不存在，仅更新配置';
        }
    } else {
        $msg = '保存成功';
    }
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
@keyframes spin{to{transform:rotate(360deg)}}
.form-card{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);padding:20px;box-shadow:var(--shadow);margin-bottom:16px}
.form-group{margin-bottom:16px}
.form-group label{display:block;font-weight:500;margin-bottom:6px;font-size:.88em;color:var(--text-secondary)}
.form-group .hint{font-size:.78em;color:var(--text-muted);margin-top:4px}
input[type="text"],input[type="number"],textarea{width:100%;padding:10px 12px;border:1px solid var(--border);border-radius:8px;background:var(--bg);color:var(--text);font-family:inherit;font-size:14px;outline:none;transition:border-color .2s;box-sizing:border-box;min-width:0}
textarea{min-height:120px;resize:vertical}
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
.modal-overlay.active { display:flex; animation: cfgModalFadeIn 0.25s ease; }
.modal-overlay.closing { animation: cfgModalFadeOut 0.2s ease forwards; }
.modal-overlay.active .modal-box { animation: cfgModalSlideIn 0.3s cubic-bezier(0.34, 1.56, 0.64, 1); }
.modal-overlay.closing .modal-box { animation: cfgModalSlideOut 0.2s ease forwards; }
.modal-overlay.active .custom-select-modal-box { animation: cfgModalBottomUp 0.3s cubic-bezier(0.25, 0.1, 0.25, 1); }
.modal-overlay.closing .custom-select-modal-box { animation: cfgModalBottomDown 0.25s ease forwards; }
.modal-box { background:var(--surface); border-radius:var(--radius); padding:24px; box-shadow:0 8px 32px rgba(0,0,0,.15); max-width:calc(100vw - 32px); overflow-x:hidden; word-break:break-word; }
@keyframes cfgModalFadeIn { from { opacity:0; } to { opacity:1; } }
@keyframes cfgModalFadeOut { from { opacity:1; } to { opacity:0; } }
@keyframes cfgModalSlideIn { from { opacity:0; transform:scale(0.9) translateY(10px); } to { opacity:1; transform:scale(1) translateY(0); } }
@keyframes cfgModalSlideOut { from { opacity:1; transform:scale(1) translateY(0); } to { opacity:0; transform:scale(0.95) translateY(8px); } }
@keyframes cfgModalBottomUp { from { transform:translateY(100%); } to { transform:translateY(0); } }
@keyframes cfgModalBottomDown { from { transform:translateY(0); } to { transform:translateY(100%); } }
.btn-outline { background:transparent; border:1px solid var(--border); color:var(--text-secondary); }
.btn-sm { padding:8px 16px; font-size:13px; }
.custom-select { display:flex; align-items:center; justify-content:space-between; width:100%; padding:9px 12px; border:1px solid var(--border); border-radius:8px; background:var(--bg); cursor:pointer; transition:border-color .2s; font-size:14px; }
.custom-select:hover, .custom-select:focus { border-color:var(--accent); }
.custom-select-text { flex:1; }
.custom-select-arrow { flex-shrink:0; color:var(--text-muted); margin-left:8px; }
.select-option { display:flex; align-items:center; gap:12px; padding:13px 20px; cursor:pointer; transition:background .15s; font-size:14px; margin:0 8px; border-radius:10px; }
.select-option:hover { background:var(--bg); }
.select-option.active { color:var(--accent); font-weight:600; background:hsl(var(--accent-hue),var(--accent-sat),95%); }
.select-option.active::after { content:'✓'; margin-left:auto; font-weight:700; font-size:15px; }
.select-option .opt-dot { width:8px; height:8px; border-radius:50%; flex-shrink:0; }
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
            <div class="toggle-row">
                <div><div class="toggle-label">限制代理/VPN 访问</div><div class="toggle-desc">开启后，代理/VPN 用户将被禁止操作</div></div>
                <label class="toggle"><input type="checkbox" name="block_proxy" <?= !empty($config['block_proxy']) ? 'checked' : '' ?>><span class="slider"></span></label>
            </div>
            <div class="toggle-row" onclick="openRateLimitModal()" style="cursor:pointer;">
                <div><div class="toggle-label">频率限制设置</div><div class="toggle-desc">登录/评论/注册频率上限</div></div>
                <svg viewBox="0 0 24 24" width="20" height="20" stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
            </div>
            <div class="toggle-row" onclick="openOAuthModal()" style="cursor:pointer;">
                <div><div class="toggle-label">OAuth 登录配置</div><div class="toggle-desc">配置第三方 OAuth 登录（<?= ($config['oauth_enabled'] ?? false) ? '<span style="color:#16a34a">已开启</span>' : '未开启' ?>）</div></div>
                <svg viewBox="0 0 24 24" width="20" height="20" stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
            </div>
            <div class="toggle-row" onclick="openAnnModal()" style="cursor:pointer;">
                <div><div class="toggle-label">公告管理</div><div class="toggle-desc"><?= !empty($config['announcement']['title']) ? '当前公告：' . htmlspecialchars(mb_substr($config['announcement']['title'], 0, 15, 'UTF-8')) : '未设置公告' ?></div></div>
                <svg viewBox="0 0 24 24" width="20" height="20" stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
            </div>
            <div class="toggle-row" onclick="openAdminPathModal()" style="cursor:pointer;">
                <div><div class="toggle-label">后台入口路径</div><div class="toggle-desc">当前：/<strong><?= htmlspecialchars($config['admin_path'] ?? 'youyou') ?></strong>/</div></div>
                <svg viewBox="0 0 24 24" width="20" height="20" stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
            </div>

        </div>
        <!-- ========== 音乐配置 ========== -->
        <div class="form-card">
            <div class="form-group">
                <label>音乐歌单 ID</label>
                <input type="text" name="music_playlist_id" value="<?= htmlspecialchars($config['music_playlist_id'] ?? '3778678') ?>" placeholder="3778678">
                <div class="hint">网易云音乐歌单 ID，默认 3778678 为热歌榜。从歌单分享链接中获取</div>
            </div>
        </div>

        <div class="section-label" style="font-size:.82em;font-weight:600;color:var(--text-muted);text-transform:uppercase;letter-spacing:.5px;margin:20px 0 10px;display:flex;align-items:center;justify-content:space-between;">
            <span>音乐音源</span>
            <button type="button" onclick="openImportSourceModal()" style="background:none;border:1px solid var(--border);border-radius:6px;padding:4px 10px;font-size:12px;color:var(--accent);cursor:pointer;font-family:inherit;">+ 导入音源</button>
        </div>

        <?php
        $musicSources = $config['music_sources'] ?? [];
        if (empty($musicSources)) {
            // Migration: create default source from old config
            $oldKey = $config['music_api_key'] ?? '';
            $oldUrl = $config['music_api_url'] ?? 'https://c.wwwweb.top';
            $oldSource = $config['music_source'] ?? 'wy';
            $oldQuality = $config['music_quality'] ?? '320k';
            if ($oldKey) {
                $musicSources[] = [
                    'id' => 'default',
                    'name' => 'ikun 音源',
                    'api_url' => $oldUrl,
                    'api_key' => $oldKey,
                    'source' => $oldSource,
                    'quality' => $oldQuality,
                ];
            }
        }
        $activeSourceId = $config['active_music_source'] ?? ($musicSources[0]['id'] ?? '');
        $platformNames = ['wy' => '网易云', 'tx' => 'QQ音乐', 'kg' => '酷狗', 'kw' => '酷我', 'git' => 'git'];
        $platformColors = ['wy' => '#e74c3c', 'tx' => '#31c27c', 'kg' => '#2ca2c9', 'kw' => '#f5a623', 'git' => '#333'];
        $qualityLabels = ['128k' => '128k', '320k' => '320k', 'flac' => 'FLAC', 'flac24bit' => 'FLAC 24bit', 'hires' => 'Hi-Res', 'atmos' => 'Atmos', 'master' => 'Master'];
        ?>

        <?php foreach ($musicSources as $idx => $src): ?>
        <div class="form-card" style="padding:14px 16px;display:flex;align-items:center;gap:14px;position:relative;" data-source-idx="<?= $idx ?>" data-source-idx="<?= $idx ?>">
            <div style="width:44px;height:44px;border-radius:10px;background:<?= ($platformColors[$src['source'] ?? 'wy'] ?? '#6366f1') ?>15;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                <svg viewBox="0 0 24 24" width="24" height="24" stroke="<?= ($platformColors[$src['source'] ?? 'wy'] ?? '#6366f1') ?>" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18V5l12-2v13"/><circle cx="6" cy="18" r="3"/><circle cx="18" cy="16" r="3"/></svg>
            </div>
            <div style="flex:1;min-width:0;overflow:hidden;">
                <div data-src-name style="font-weight:600;font-size:.92em;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;<?= ($activeSourceId === $src['id']) ? 'color:var(--accent);' : '' ?>">
                    <?= htmlspecialchars($src['name'] ?? '未命名') ?>
                    <?php if ($activeSourceId === $src['id']): ?><span style="font-size:10px;background:var(--accent);color:#fff;padding:1px 6px;border-radius:4px;margin-left:6px;font-weight:500;">使用中</span><?php endif; ?>
                </div>
                <div data-src-info style="font-size:.78em;color:var(--text-muted);margin-top:3px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                    <?php
                    $srcPlatforms = $src['platforms'] ?? [($src['source'] ?? 'wy')];
                    $pNames = array_map(function($p) use ($platformNames) { return $platformNames[$p] ?? $p; }, $srcPlatforms);
                    echo htmlspecialchars(implode(' · ', $pNames));
                    ?>
                    · <?= htmlspecialchars($qualityLabels[$src['quality'] ?? '320k'] ?? ($src['quality'] ?? '320k')) ?>
                </div>
            </div>
            <div style="display:flex;align-items:center;gap:6px;">
                <?php if ($activeSourceId !== $src['id']): ?>
                <button type="button" onclick="event.stopPropagation();activateSource(<?= $idx ?>)" style="background:none;border:1px solid var(--border);border-radius:6px;padding:4px 10px;font-size:11px;color:var(--accent);cursor:pointer;font-family:inherit;">启用</button>
                <?php endif; ?>
                <button type="button" onclick="event.stopPropagation();openSourceConfigModal(<?= $idx ?>)" style="background:none;border:1px solid var(--border);border-radius:6px;padding:4px 10px;font-size:11px;color:var(--text-secondary);cursor:pointer;font-family:inherit;">配置</button>
                <button type="button" onclick="event.stopPropagation();removeSource(<?= $idx ?>)" style="background:none;border:none;color:var(--text-muted);cursor:pointer;padding:4px;font-size:16px;line-height:1;" title="删除">×</button>
            </div>
        </div>
        <?php endforeach; ?>



        <!-- Hidden inputs for music sources data -->
        <input type="hidden" name="music_sources_json" id="musicSourcesJson" value="<?= htmlspecialchars(json_encode($musicSources, JSON_UNESCAPED_UNICODE)) ?>">
        <input type="hidden" name="active_music_source" id="activeMusicSource" value="<?= htmlspecialchars($activeSourceId) ?>">

        <!-- 音源配置弹窗 -->
        <div class="modal-overlay" id="sourceConfigModal">
            <div class="modal-box" style="max-width:460px;text-align:left;max-height:90vh;overflow-y:auto;">
                <h3 style="display:flex;align-items:center;gap:8px;margin-bottom:4px;">
                    <svg viewBox="0 0 24 24" width="20" height="20" stroke="var(--accent)" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18V5l12-2v13"/><circle cx="6" cy="18" r="3"/><circle cx="18" cy="16" r="3"/></svg>
                    <span id="sourceConfigTitle">配置音源</span>
                </h3>
                <p style="font-size:.85em;color:var(--text-muted);margin-bottom:16px;">配置音源的播放平台和音质</p>
                <div class="form-group">
                    <label>音源名称</label>
                    <input type="text" id="srcCfgName" placeholder="我的音源">
                </div>
                <div class="form-group">
                    <label>默认平台</label>
                    <div class="custom-select" id="platformSelect" onclick="openCustomSelect('platform')">
                        <span class="custom-select-text" id="platformSelectText">网易云</span>
                        <svg class="custom-select-arrow" viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" fill="none" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
                    </div>
                    <input type="hidden" id="srcCfgPlatform" value="wy">
                </div>
                <div class="form-group">
                    <label>默认音质</label>
                    <div class="custom-select" id="qualitySelect" onclick="openCustomSelect('quality')">
                        <span class="custom-select-text" id="qualitySelectText">320k</span>
                        <svg class="custom-select-arrow" viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" fill="none" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
                    </div>
                    <input type="hidden" id="srcCfgQuality" value="320k">
                </div>
                <div style="display:flex;align-items:center;gap:10px;margin-top:20px;">
                    <button type="button" class="btn btn-outline btn-sm" onclick="testCurrentSourceSpeed()" id="srcSpeedBtn" style="margin-right:auto;">
                        <svg viewBox="0 0 24 24" width="14" height="14" stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-2px;margin-right:4px;"><path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/></svg>测速
                    </button>
                    <button type="button" class="btn btn-outline btn-sm" onclick="closeSourceConfigModal()">取消</button>
                    <button type="button" class="btn btn-sm" onclick="saveSourceConfig()">保存</button>
                </div>
            </div>
        </div>

        <!-- 自定义选择弹窗 -->
        <div class="modal-overlay" id="customSelectModal" style="align-items:flex-end;">
            <div class="custom-select-modal-box" style="width:100%;max-width:600px;margin:0 auto;background:var(--surface);border-radius:16px 16px 0 0;box-shadow:0 -4px 24px rgba(0,0,0,.12);overflow:hidden;">
                <div style="padding:14px 20px;display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid var(--border);">
                    <div style="font-weight:600;font-size:1em;" id="customSelectTitle">选择</div>
                    <button type="button" onclick="closeCustomSelect()" style="background:none;border:none;width:28px;height:28px;display:flex;align-items:center;justify-content:center;cursor:pointer;color:var(--text-muted);border-radius:50%;transition:background .15s;" onmouseover="this.style.background='var(--bg)'" onmouseout="this.style.background=''">
                        <svg viewBox="0 0 24 24" width="18" height="18" stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                    </button>
                </div>
                <div style="max-height:340px;overflow-y:auto;padding:6px 0;" id="customSelectOptions">
                </div>
                <div style="padding:12px 20px 16px;border-top:1px solid var(--border);text-align:center;">
                    <button type="button" onclick="closeCustomSelect()" style="background:var(--bg);border:1px solid var(--border);border-radius:10px;padding:10px 0;width:100%;font-size:14px;color:var(--text-secondary);cursor:pointer;font-family:inherit;transition:all .15s;" onmouseover="this.style.borderColor='var(--accent)'" onmouseout="this.style.borderColor='var(--border)'">取消</button>
                </div>
            </div>
        </div>

        <!-- 导入音源弹窗 -->
        <div class="modal-overlay" id="importSourceModal">
            <div class="modal-box" style="max-width:480px;text-align:left;max-height:90vh;overflow-y:auto;">
                <h3 style="display:flex;align-items:center;gap:8px;margin-bottom:4px;">
                    <svg viewBox="0 0 24 24" width="20" height="20" stroke="var(--accent)" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                    导入音源
                </h3>
                <p style="font-size:.85em;color:var(--text-muted);margin-bottom:16px;">粘贴音源 JSON 配置，或输入在线配置地址</p>
                <div class="form-group">
                    <label>音源名称</label>
                    <input type="text" id="importSrcName" placeholder="导入的音源">
                </div>
                <div class="form-group">
                    <label>JSON 配置</label>
                    <textarea id="importSrcJson" style="min-height:120px;font-family:monospace;font-size:13px;word-break:break-all;" placeholder='{"api_url":"https://example.com","api_key":"xxx","source":"wy","quality":"320k"}'></textarea>
                    <div class="hint">支持格式：{"api_url":"...","api_key":"...","source":"wy","quality":"320k"} 或 {"url":"...","key":"...","platforms":["wy","tx"]}</div>
                </div>
                <div class="form-group">
                    <label>上传插件文件</label>
                    <div id="uploadDropZone" style="border:2px dashed var(--border);border-radius:10px;padding:20px 16px;text-align:center;cursor:pointer;transition:all .2s;" onclick="document.getElementById('uploadPluginFile').click()">
                        <svg viewBox="0 0 24 24" width="24" height="24" stroke="var(--text-muted)" fill="none" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="margin-bottom:6px;"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                        <div style="font-size:.85em;color:var(--text-muted);">点击选择 .js / .json 文件</div>
                        <div id="uploadFileName" style="font-size:.78em;color:var(--accent);margin-top:4px;"></div>
                    </div>
                    <input type="file" id="uploadPluginFile" accept=".json,.js,application/json,text/plain" style="display:none;" onchange="handlePluginUpload(this)">
                </div>
                <div class="form-group">
                    <label>或从 URL 导入</label>
                    <div style="display:flex;gap:8px;">
                        <input type="text" id="importSrcUrl" placeholder="https://example.com/source.json" style="flex:1;">
                        <button type="button" class="btn btn-outline btn-sm" onclick="fetchImportUrl()">获取</button>
                    </div>
                </div>
                <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:20px;">
                    <button type="button" class="btn btn-outline btn-sm" onclick="closeImportSourceModal()">取消</button>
                    <button type="button" class="btn btn-sm" onclick="importSource()">导入</button>
                </div>
            </div>
        </div>


        <div style="display:flex;justify-content:flex-end"><button type="submit" class="btn">保存配置</button></div>
</main>
<div class="modal-overlay" id="adminPathModal">
    <div class="modal-box" style="max-width:420px;text-align:left;">
        <h3 style="margin:0 0 16px;font-size:16px;">修改后台入口路径</h3>
        <div class="form-group">
            <label>新路径</label>
            <div style="display:flex;align-items:center;gap:8px;">
                <span style="color:var(--text-muted);font-size:14px;white-space:nowrap;">/</span>
                <input type="text" name="admin_path" value="<?= htmlspecialchars($config['admin_path'] ?? 'youyou') ?>" placeholder="youyou" style="flex:1;">
                <span style="color:var(--text-muted);font-size:14px;white-space:nowrap;">/</span>
            </div>
            <div class="hint">仅允许字母、数字、下划线和连字符。修改后请记住新路径</div>
        </div>
        <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:16px;">
            <button type="button" class="btn btn-outline btn-sm" onclick="closeAdminPathModal()">取消</button>
            <button type="submit" class="btn btn-sm">确定</button>
        </div>
    </div>
</div>
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
<div class="modal-overlay" id="oauthModal">
    <div class="modal-box" style="max-width:480px;text-align:left;max-height:90vh;overflow-y:auto;">
        <h3 style="display:flex;align-items:center;gap:8px;margin-bottom:4px;">
            <svg viewBox="0 0 24 24" width="20" height="20" stroke="var(--accent)" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>
            OAuth 登录配置
        </h3>
        <p style="font-size:0.85em;color:var(--text-muted);margin-bottom:20px;">配置第三方 OAuth 登录，用户可通过授权登录网站</p>
        <div class="toggle-row" style="padding-top:0;">
            <div><div class="toggle-label">启用 OAuth 登录</div><div class="toggle-desc">开启后登录界面显示 OAuth 登录按钮</div></div>
            <label class="toggle"><input type="checkbox" name="oauth_enabled" <?= ($config['oauth_enabled'] ?? false) ? 'checked' : '' ?>><span class="slider"></span></label>
        </div>
        <div class="form-group" style="margin-top:16px;">
            <label>显示名称</label>
            <input type="text" name="oauth_name" value="<?= htmlspecialchars($config['oauth_name'] ?? 'Youyun') ?>" placeholder="Youyun">
            <div class="hint">登录按钮上显示的名称</div>
        </div>
        <div class="form-group">
            <label>图标 URL</label>
            <input type="text" name="oauth_icon" value="<?= htmlspecialchars($config['oauth_icon'] ?? '') ?>" placeholder="https://example.com/icon.png">
            <div class="hint">登录按钮图标，留空使用默认图标</div>
        </div>
        <div class="form-group">
            <label>API 请求地址</label>
            <input type="text" name="oauth_base_url" value="<?= htmlspecialchars($config['oauth_base_url'] ?? '') ?>" placeholder="http://111.230.193.63:520">
            <div class="hint">OAuth 服务的基础地址，不含路径</div>
        </div>
        <div class="form-group">
            <label>Client ID</label>
            <input type="text" name="oauth_client_id" value="<?= htmlspecialchars($config['oauth_client_id'] ?? '') ?>" placeholder="H0vGcwr3tZPiLJQEFB">
        </div>
        <div class="form-group">
            <label>Client Secret</label>
            <input type="text" name="oauth_client_secret" value="<?= htmlspecialchars($config['oauth_client_secret'] ?? '') ?>" placeholder="9GvFbAYDOyqH7yS1BVntKxUlTKHOey6CnA1PdaBA">
        </div>
        <details style="margin-top:8px;">
            <summary style="font-size:.85em;color:var(--accent);cursor:pointer;font-weight:500;margin-bottom:12px;">高级路径配置</summary>
            <div class="form-group">
                <label>授权端点路径</label>
                <input type="text" name="oauth_auth_path" value="<?= htmlspecialchars($config['oauth_auth_path'] ?? '/api/oauth.php?action=authorize') ?>" placeholder="/api/oauth.php?action=authorize">
            </div>
            <div class="form-group">
                <label>Token 端点路径</label>
                <input type="text" name="oauth_token_path" value="<?= htmlspecialchars($config['oauth_token_path'] ?? '/api/oauth.php?action=token') ?>" placeholder="/api/oauth.php?action=token">
            </div>
            <div class="form-group">
                <label>Verify 端点路径</label>
                <input type="text" name="oauth_verify_path" value="<?= htmlspecialchars($config['oauth_verify_path'] ?? '/api/oauth.php?action=verify') ?>" placeholder="/api/oauth.php?action=verify">
            </div>
        </details>
        <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:20px;">
            <button type="button" class="btn btn-outline btn-sm" onclick="closeOAuthModal()">取消</button>
            <button type="submit" class="btn btn-sm">保存</button>
        </div>
    </div>
</div>
<div class="modal-overlay" id="annModal">
    <div class="modal-box" style="max-width:480px;text-align:left;max-height:90vh;overflow-y:auto;">
        <h3 style="display:flex;align-items:center;gap:8px;margin-bottom:4px;">
            <svg viewBox="0 0 24 24" width="20" height="20" stroke="var(--accent)" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
            公告管理
        </h3>
        <p style="font-size:0.85em;color:var(--text-muted);margin-bottom:20px;">设置网站公告，支持 HTML 和 Markdown 语法。用户确认后 7 天内不再弹出（内容更新则重新弹出）</p>
        <div class="form-group">
            <label>公告标题</label>
            <input type="text" name="ann_title" value="<?= htmlspecialchars(($config['announcement'] ?? [])['title'] ?? '') ?>" placeholder="例如：网站更新通知">
        </div>
        <div class="form-group">
            <label>公告内容（支持 Markdown）</label>
            <textarea name="ann_content" placeholder="支持 HTML 标签和 Markdown 语法"><?= htmlspecialchars(($config['announcement'] ?? [])['content'] ?? '') ?></textarea>
        </div>
        <div class="form-group">
            <label>确认按钮倒计时（秒）</label>
            <input type="number" name="ann_countdown" value="<?= ($config['announcement'] ?? [])['countdown'] ?? 3 ?>" min="0">
            <div class="hint">0 表示无倒计时，直接可点击确认</div>
        </div>
        <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:20px;">
            <button type="button" class="btn btn-outline btn-sm" onclick="closeAnnModal()">取消</button>
            <button type="submit" class="btn btn-sm">保存</button>
        </div>
    </div>
</div>
</form>
<script>
function openAdminPathModal() { document.getElementById('adminPathModal').classList.add('active'); }
function closeAdminPathModal() {
    var m = document.getElementById('adminPathModal');
    m.classList.add('closing');
    setTimeout(function() { m.classList.remove('active', 'closing'); }, 200);
}
function openRateLimitModal() { document.getElementById('rateLimitModal').classList.add('active'); }
function closeRateLimitModal() {
    var m = document.getElementById('rateLimitModal');
    m.classList.add('closing');
    setTimeout(function() { m.classList.remove('active', 'closing'); }, 200);
}
function openOAuthModal() { document.getElementById('oauthModal').classList.add('active'); }
function closeOAuthModal() {
    var m = document.getElementById('oauthModal');
    m.classList.add('closing');
    setTimeout(function() { m.classList.remove('active', 'closing'); }, 200);
}
function openAnnModal() { document.getElementById('annModal').classList.add('active'); }
function closeAnnModal() {
    var m = document.getElementById('annModal');
    m.classList.add('closing');
    setTimeout(function() { m.classList.remove('active', 'closing'); }, 200);
}
// ========== 音源管理 ==========
var musicSources = JSON.parse(document.getElementById('musicSourcesJson').value || '[]');
var activeSourceId = document.getElementById('activeMusicSource').value;
var editingSourceIdx = -1;

function syncSourcesToInput() {
    document.getElementById('musicSourcesJson').value = JSON.stringify(musicSources);
    document.getElementById('activeMusicSource').value = activeSourceId;
}

function openSourceConfigModal(idx) {
    editingSourceIdx = idx;
    var src = musicSources[idx];
    document.getElementById('sourceConfigTitle').textContent = '配置 - ' + (src.name || '未命名');
    document.getElementById('srcCfgName').value = src.name || '';
    // Platform
    var platform = (src.platforms && src.platforms[0]) || src.source || 'wy';
    document.getElementById('srcCfgPlatform').value = platform;
    document.getElementById('platformSelectText').textContent = platformNames[platform] || platform;
    // Quality
    var quality = src.quality || '320k';
    document.getElementById('srcCfgQuality').value = quality;
    document.getElementById('qualitySelectText').textContent = qualityLabels[quality] || quality;
    // Reset speed btn
    var sbtn = document.getElementById('srcSpeedBtn');
    if (sbtn) { sbtn.innerHTML = '<svg viewBox="0 0 24 24" width="14" height="14" stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-2px;margin-right:4px;"><path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/></svg>测速'; sbtn.style.color = ''; }
    document.getElementById('sourceConfigModal').classList.add('active');
}

function closeSourceConfigModal() {
    var m = document.getElementById('sourceConfigModal');
    m.classList.add('closing');
    setTimeout(function() { m.classList.remove('active', 'closing'); editingSourceIdx = -1; }, 200);
}

// (platform select moved to custom select)

function saveSourceConfig() {
    if (editingSourceIdx < 0 || editingSourceIdx >= musicSources.length) return;
    var src = musicSources[editingSourceIdx];
    src.name = document.getElementById('srcCfgName').value.trim() || '未命名';
    src.quality = document.getElementById('srcCfgQuality').value;
    var platform = document.getElementById('srcCfgPlatform').value || 'wy';
    src.platforms = [platform];
    src.source = platform;
    syncSourcesToInput();
    // Update card display without reload
    var card = document.querySelector('[data-source-idx="' + editingSourceIdx + '"]');
    if (card) {
        var nameEl = card.querySelector('[data-src-name]');
        var infoEl = card.querySelector('[data-src-info]');
        if (nameEl) {
            var isActive = src.id === activeSourceId;
            nameEl.style.color = isActive ? 'var(--accent)' : '';
            var badge = isActive ? ' <span style="font-size:10px;background:var(--accent);color:#fff;padding:1px 6px;border-radius:4px;margin-left:6px;font-weight:500;">使用中</span>' : '';
            nameEl.innerHTML = src.name + badge;
        }
        if (infoEl) {
            var pNames = (src.platforms || []).map(function(p) { return platformNames[p] || p; });
            infoEl.textContent = pNames.join(' · ') + ' · ' + (qualityLabels[src.quality] || src.quality);
        }
    }
    closeSourceConfigModal();
}

function addNewSource() {
    musicSources.push({
        id: 'src_' + Date.now(),
        name: '新音源',
        api_url: 'https://c.wwwweb.top',
        api_key: '',
        source: 'wy',
        quality: '320k',
        platforms: ['wy']
    });
    syncSourcesToInput();
    location.reload();
}

function activateSource(idx) {
    if (idx < 0 || idx >= musicSources.length) return;
    activeSourceId = musicSources[idx].id;
    syncSourcesToInput();
    // Update UI
    document.querySelectorAll('[data-source-idx]').forEach(function(card) {
        var cardIdx = parseInt(card.dataset.sourceIdx);
        var isActive = cardIdx === idx;
        var nameEl = card.querySelector('div > div:first-child');
        if (nameEl) {
            nameEl.style.color = isActive ? 'var(--accent)' : '';
            var badge = nameEl.querySelector('span');
            if (isActive && !badge) {
                nameEl.innerHTML += ' <span style="font-size:10px;background:var(--accent);color:#fff;padding:1px 6px;border-radius:4px;margin-left:6px;font-weight:500;">使用中</span>';
            } else if (!isActive && badge) {
                badge.remove();
            }
        }
        // Show/hide activate button
        var activateBtn = card.querySelector('button[onclick*="activateSource"]');
        if (activateBtn) activateBtn.style.display = isActive ? 'none' : '';
    });
}

function removeSource(idx) {
    if (!confirm('确定删除此音源？')) return;
    var removed = musicSources.splice(idx, 1)[0];
    if (removed.id === activeSourceId && musicSources.length > 0) {
        activeSourceId = musicSources[0].id;
    }
    syncSourcesToInput();
    location.reload();
}

function openImportSourceModal() {
    document.getElementById('importSrcName').value = '';
    document.getElementById('importSrcJson').value = '';
    document.getElementById('importSrcUrl').value = '';
    document.getElementById('uploadFileName').textContent = '';
    window._uploadedPluginContent = '';
    document.getElementById('importSourceModal').classList.add('active');
}

function closeImportSourceModal() {
    var m = document.getElementById('importSourceModal');
    m.classList.add('closing');
    setTimeout(function() { m.classList.remove('active', 'closing'); }, 200);
}

function importSource() {
    var name = document.getElementById('importSrcName').value.trim() || '导入的音源';
    // 优先用文件上传的内容，其次用 textarea
    var jsonStr = (window._uploadedPluginContent || document.getElementById('importSrcJson').value).trim();
    if (!jsonStr) { alert('请粘贴 JSON 配置、选择文件或输入 URL'); return; }
    try {
        var data = JSON.parse(jsonStr);
        var newSrc = {
            id: 'src_' + Date.now(),
            name: name,
            api_url: data.api_url || data.url || 'https://c.wwwweb.top',
            api_key: data.api_key || data.key || '',
            source: data.source || (data.platforms && data.platforms[0]) || 'wy',
            quality: data.quality || '320k',
            platforms: data.platforms || [data.source || 'wy']
        };
        musicSources.push(newSrc);
        syncSourcesToInput();
        window._uploadedPluginContent = '';
        closeImportSourceModal();
        location.reload();
    } catch(e) {
        alert('JSON 解析失败: ' + e.message);
    }
}

// Speed test (via server proxy)
function testCurrentSourceSpeed() {
    var btn = document.getElementById('srcSpeedBtn');
    if (!btn) return;
    btn.innerHTML = '测试中...';
    btn.disabled = true;
    fetch('../music.php?speed_test=1')
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) {
                var ms = data.ms;
                btn.innerHTML = '<svg viewBox="0 0 24 24" width="14" height="14" stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-2px;margin-right:4px;"><path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/></svg>' + ms + 'ms';
                btn.style.color = ms < 500 ? '#16a34a' : ms < 1500 ? '#f59e0b' : '#ef4444';
            } else {
                btn.innerHTML = '失败';
                btn.style.color = '#ef4444';
            }
            btn.disabled = false;
            setTimeout(function() { btn.innerHTML = '<svg viewBox="0 0 24 24" width="14" height="14" stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-2px;margin-right:4px;"><path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/></svg>测速'; btn.style.color = ''; }, 4000);
        })
        .catch(function() {
            btn.innerHTML = '超时';
            btn.style.color = '#ef4444';
            btn.disabled = false;
            setTimeout(function() { btn.innerHTML = '<svg viewBox="0 0 24 24" width="14" height="14" stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-2px;margin-right:4px;"><path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/></svg>测速'; btn.style.color = ''; }, 4000);
        });
}

// File upload handling
window._uploadedPluginContent = '';
function handlePluginUpload(input) {
    var file = input.files[0];
    if (!file) return;
    document.getElementById('uploadFileName').textContent = file.name;
    var reader = new FileReader();
    reader.onload = function(e) {
        var text = e.target.result;
        window._uploadedPluginContent = text;
        document.getElementById('importSrcJson').value = text;
        // Auto-fill name from file
        if (!document.getElementById('importSrcName').value) {
            document.getElementById('importSrcName').value = file.name.replace(/\.(js|json)$/, '');
        }
        // Try to parse LX Music plugin format
        tryParseLxPlugin(text);
    };
    reader.readAsText(file);
}

// Drag & drop
var dropZone = document.getElementById('uploadDropZone');
if (dropZone) {
    ['dragenter','dragover'].forEach(function(ev) {
        dropZone.addEventListener(ev, function(e) { e.preventDefault(); dropZone.style.borderColor = 'var(--accent)'; dropZone.style.background = 'var(--bg)'; });
    });
    ['dragleave','drop'].forEach(function(ev) {
        dropZone.addEventListener(ev, function(e) { e.preventDefault(); dropZone.style.borderColor = ''; dropZone.style.background = ''; });
    });
    dropZone.addEventListener('drop', function(e) {
        var file = e.dataTransfer.files[0];
        if (file) {
            document.getElementById('uploadFileName').textContent = file.name;
            var reader = new FileReader();
            reader.onload = function(ev) {
                document.getElementById('importSrcJson').value = ev.target.result;
                if (!document.getElementById('importSrcName').value) {
                    document.getElementById('importSrcName').value = file.name.replace(/\.(js|json)$/, '');
                }
                tryParseLxPlugin(ev.target.result);
            };
            reader.readAsText(file);
        }
    });
}

// Parse LX Music plugin format (extract API_URL, API_KEY from JS)
function tryParseLxPlugin(text) {
    var apiUrlMatch = text.match(/API_URL\s*=\s*["']([^"']+)["']/);
    var apiKeyMatch = text.match(/API_KEY\s*=\s*["']([^"']+)["']/);
    var nameMatch = text.match(/@name\s+(.+)/);
    var qualityMatch = text.match(/MUSIC_QUALITY\s*=\s*JSON\.parse\('(\{.+?\})'\)/);
    if (apiKeyMatch) {
        var obj = {};
        if (apiUrlMatch) obj.api_url = apiUrlMatch[1];
        obj.api_key = apiKeyMatch[1];
        if (qualityMatch) {
            try {
                var qData = JSON.parse(qualityMatch[1]);
                obj.platforms = Object.keys(qData);
                obj.source = obj.platforms[0] || 'wy';
            } catch(e) {}
        }
        document.getElementById('importSrcJson').value = JSON.stringify(obj, null, 2);
        if (nameMatch && !document.getElementById('importSrcName').value) {
            document.getElementById('importSrcName').value = nameMatch[1].trim();
        }
    }
}


// ===== Custom Select =====
var platformNames = {wy:'网易云',tx:'QQ音乐',kg:'酷狗',kw:'酷我',git:'git'};
var platformColors = {wy:'#e74c3c',tx:'#31c27c',kg:'#2ca2c9',kw:'#f5a623',git:'#333'};
var qualityLabels = {'128k':'128k','320k':'320k','flac':'FLAC','flac24bit':'FLAC 24bit','hires':'Hi-Res','atmos':'Atmos 全景声','master':'Master 母带'};
var customSelectType = '';

function openCustomSelect(type) {
    customSelectType = type;
    var title = document.getElementById('customSelectTitle');
    var options = document.getElementById('customSelectOptions');
    options.innerHTML = '';
    if (type === 'platform') {
        title.textContent = '选择平台';
        var current = document.getElementById('srcCfgPlatform').value;
        Object.keys(platformNames).forEach(function(key) {
            var opt = document.createElement('div');
            opt.className = 'select-option' + (key === current ? ' active' : '');
            opt.innerHTML = '<span class="opt-dot" style="background:' + (platformColors[key] || '#999') + '"></span>' + platformNames[key];
            opt.onclick = function() { selectCustomOption(type, key, platformNames[key]); };
            options.appendChild(opt);
        });
    } else if (type === 'quality') {
        title.textContent = '选择音质';
        var current = document.getElementById('srcCfgQuality').value;
        Object.keys(qualityLabels).forEach(function(key) {
            var opt = document.createElement('div');
            opt.className = 'select-option' + (key === current ? ' active' : '');
            opt.textContent = qualityLabels[key];
            opt.onclick = function() { selectCustomOption(type, key, qualityLabels[key]); };
            options.appendChild(opt);
        });
    }
    document.getElementById('customSelectModal').classList.add('active');
}

function selectCustomOption(type, value, text) {
    if (type === 'platform') {
        document.getElementById('srcCfgPlatform').value = value;
        document.getElementById('platformSelectText').textContent = text;
    } else if (type === 'quality') {
        document.getElementById('srcCfgQuality').value = value;
        document.getElementById('qualitySelectText').textContent = text;
    }
    closeCustomSelect();
}

function closeCustomSelect() {
    var m = document.getElementById('customSelectModal');
    m.classList.add('closing');
    setTimeout(function() { m.classList.remove('active', 'closing'); }, 250);
}

function fetchImportUrl() {
    var url = document.getElementById('importSrcUrl').value.trim();
    if (!url) { alert('请输入 URL'); return; }
    fetch(url).then(function(r) { return r.text(); }).then(function(text) {
        document.getElementById('importSrcJson').value = text;
        try {
            var data = JSON.parse(text);
            if (data.name && !document.getElementById('importSrcName').value) {
                document.getElementById('importSrcName').value = data.name;
            }
        } catch(e) {}
    }).catch(function(err) {
        alert('获取失败: ' + err.message);
    });
}

</script>
</body>
</html>








