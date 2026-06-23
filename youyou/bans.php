<?php
session_start();
require_once __DIR__ . '/../utils.php';
if (empty($_SESSION['cmt_user']) || ($_SESSION['cmt_user']['role'] ?? '') !== 'admin') {
    logUnauthorized('越权尝试访问管理后台(bans.php)');
    header('Location: ../?admin_login=1');
    exit;
}
$dataDir = '../data';
$bansFile = $dataDir . '/.bans.json';

function loadBans() {
    global $bansFile;
    if (!file_exists($bansFile)) return [];
    $data = json_decode(file_get_contents($bansFile), true);
    return is_array($data) ? $data : [];
}
function saveBans($bans) {
    global $bansFile;
    file_put_contents($bansFile, json_encode($bans, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add') {
        $ip = trim($_POST['ip'] ?? '');
        $types = $_POST['types'] ?? [];
        $reason = trim($_POST['reason'] ?? '');
        if ($ip && !empty($types)) {
            $bans = loadBans();
            $exists = false;
            foreach ($bans as &$b) {
                if ($b['ip'] === $ip) {
                    foreach ($types as $t) { if (!in_array($t, $b['types'])) $b['types'][] = $t; }
                    $exists = true; break;
                }
            }
            unset($b);
            if (!$exists) {
                $bans[] = ['ip' => $ip, 'types' => $types, 'reason' => $reason, 'time' => date('Y-m-d H:i:s')];
            }
            saveBans($bans);
            $msg = '封禁已添加';
        }
    } elseif ($action === 'remove') {
        $ip = trim($_POST['ip'] ?? '');
        $bans = loadBans();
        $bans = array_values(array_filter($bans, function($b) use ($ip) { return $b['ip'] !== $ip; }));
        saveBans($bans);
        $msg = '已解除封禁';
    } elseif ($action === 'update_types') {
        $ip = trim($_POST['ip'] ?? '');
        $types = $_POST['types'] ?? [];
        $bans = loadBans();
        foreach ($bans as &$b) {
            if ($b['ip'] === $ip) { $b['types'] = $types; break; }
        }
        unset($b);
        saveBans($bans);
        $msg = '权限已更新';
    }
    header('Location: bans.php?msg=' . urlencode($msg));
    exit;
}
if (isset($_GET['msg'])) $msg = $_GET['msg'];
$bans = loadBans();
$typeLabels = ['register' => '注册', 'comment' => '评论', 'login' => '登录'];
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
<title>封禁管理 - <?= htmlspecialchars($_siteTitle) ?></title>
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
.page-title{font-size:1.3em;font-weight:650;margin-bottom:20px;display:flex;align-items:center;gap:8px}
.page-title svg{width:24px;height:24px;stroke:var(--accent);fill:none;stroke-width:2}
.alert{padding:10px 14px;border-radius:8px;margin-bottom:14px;font-size:.88em;color:#16a34a;background:#f0fdf4;border:1px solid #bbf7d0;animation:fadeSlide .3s ease}
@keyframes fadeSlide{from{opacity:0;transform:translateY(-6px)}to{opacity:1;transform:translateY(0)}}
.form-card{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);padding:20px;box-shadow:var(--shadow);margin-bottom:20px}
.form-card h3{font-size:.95em;margin-bottom:14px;display:flex;align-items:center;gap:6px}
.form-card h3 svg{width:16px;height:16px;stroke:var(--accent);fill:none;stroke-width:2}
.form-row{display:flex;gap:8px;align-items:end;flex-wrap:wrap}
.form-row .form-group{flex:1;min-width:120px}
.form-group{margin-bottom:12px}
.form-group label{display:block;font-weight:500;margin-bottom:5px;font-size:.85em;color:var(--text-secondary)}
input[type="text"]{width:100%;padding:9px 11px;border:1px solid var(--border);border-radius:8px;background:var(--bg);color:var(--text);font-family:inherit;font-size:14px;outline:none;transition:border-color .2s}
input:focus{border-color:var(--accent)}
.checkbox-row{display:flex;gap:14px;margin-bottom:12px;flex-wrap:wrap}
.checkbox-row label{display:flex;align-items:center;gap:5px;font-size:.88em;cursor:pointer}
.checkbox-row input[type="checkbox"]{accent-color:var(--accent)}
.btn{background:var(--accent);color:#fff;border:none;padding:9px 18px;border-radius:8px;cursor:pointer;font-weight:600;font-size:13px;transition:all .2s;font-family:inherit;white-space:nowrap}
.btn:active{opacity:.85}
.ban-card{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius-sm);padding:14px 16px 40px;box-shadow:0 1px 2px rgba(0,0,0,.04);margin-bottom:10px;position:relative}
.ban-top{display:flex;align-items:center;justify-content:space-between;margin-bottom:8px}
.ban-ip{font-weight:600;font-family:monospace;font-size:.95em}
.ban-time{font-size:.78em;color:var(--text-muted)}
.ban-types{display:flex;gap:6px;flex-wrap:wrap;margin-bottom:8px}
.ban-tag{font-size:.78em;padding:2px 8px;border-radius:4px;font-weight:500}
.ban-tag.register{background:#dbeafe;color:#2563eb}
.ban-tag.comment{background:#fef3c7;color:#d97706}
.ban-tag.login{background:#fee2e2;color:#dc2626}
.ban-actions{position:absolute;right:12px;bottom:10px;display:flex;gap:6px;align-items:center}
.ban-icon-btn{width:30px;height:30px;border-radius:8px;border:1px solid var(--border);background:transparent;color:var(--text-muted);display:flex;align-items:center;justify-content:center;cursor:pointer;transition:all .2s;font-family:inherit}
.ban-icon-btn svg{width:15px;height:15px;stroke:currentColor;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}
.ban-icon-btn.unban{border-color:#fecaca;color:#ef4444}
.ban-icon-btn.unban:hover{background:#fef2f2}
.ban-icon-btn.edit:hover{border-color:var(--accent);color:var(--accent)}
.ban-modal-mask{position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:500;display:none;align-items:center;justify-content:center;padding:16px;backdrop-filter:blur(6px);-webkit-backdrop-filter:blur(6px)}
.ban-modal-mask.show{display:flex}
.ban-modal-box{background:var(--surface);border-radius:20px;width:380px;max-width:100%;overflow:hidden;box-shadow:0 12px 40px rgba(0,0,0,.12);animation:banModalIn .25s cubic-bezier(.16,1,.3,1)}
@keyframes banModalIn{from{opacity:0;transform:scale(.92) translateY(12px)}to{opacity:1;transform:none}}
.ban-modal-head{padding:24px 24px 0;display:flex;align-items:center;justify-content:space-between}
.ban-modal-title{font-size:18px;font-weight:700;color:var(--text)}
.ban-modal-close{width:32px;height:32px;border-radius:50%;border:none;background:var(--bg);color:var(--text-muted);display:flex;align-items:center;justify-content:center;cursor:pointer;font-size:18px;transition:all .2s}
.ban-modal-close:hover{background:#fef2f2;color:#ef4444}
.ban-modal-body{padding:20px 24px 24px}
.ban-modal-ip{font-family:monospace;font-size:14px;font-weight:600;color:var(--accent);margin-bottom:16px}
.ban-toggle-row{display:flex;align-items:center;justify-content:space-between;padding:12px 0;border-bottom:1px solid var(--border)}
.ban-toggle-row:last-child{border-bottom:none}
.ban-toggle-label{font-size:.9em;color:var(--text)}
.ban-toggle-desc{font-size:.78em;color:var(--text-muted);margin-top:2px}
.ban-capsule{position:relative;width:50px;height:28px;flex-shrink:0}
.ban-capsule input{opacity:0;width:0;height:0}
.ban-capsule .capsule-slider{position:absolute;inset:0;background:#cbd5e1;border-radius:14px;cursor:pointer;transition:.3s}
.ban-capsule .capsule-slider:before{content:'';position:absolute;width:22px;height:22px;border-radius:50%;background:#fff;left:3px;top:3px;transition:.3s;box-shadow:0 1px 3px rgba(0,0,0,.15)}
.ban-capsule input:checked+.capsule-slider{background:var(--accent)}
.ban-capsule input:checked+.capsule-slider:before{transform:translateX(22px)}
.ban-modal-actions{display:flex;gap:10px;margin-top:16px}
.ban-modal-save{flex:1;padding:11px;border:none;border-radius:10px;background:var(--accent);color:#fff;font-size:14px;font-weight:600;cursor:pointer;font-family:inherit;transition:all .2s}
.ban-modal-save:hover{opacity:.85}
.ban-modal-cancel{padding:11px 20px;border:1px solid var(--border);border-radius:10px;background:var(--surface);color:var(--text-secondary);font-size:14px;cursor:pointer;font-family:inherit;transition:all .2s}
.ban-modal-cancel:hover{border-color:var(--accent);color:var(--accent)}
.empty{text-align:center;padding:40px 20px;color:var(--text-muted);font-size:.9em}
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
    <div class="page-title"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/></svg>封禁管理</div>
    <?php if ($msg): ?><div class="alert"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
    <div class="form-card">
        <h3><svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>添加封禁</h3>
        <form method="post">
            <input type="hidden" name="action" value="add">
            <div class="form-row">
                <div class="form-group"><label>IP 地址</label><input type="text" name="ip" placeholder="例如 1.2.3.4" required></div>
            </div>
            <div class="form-group"><label>封禁功能</label></div>
            <div class="checkbox-row">
                <label><input type="checkbox" name="types[]" value="register"> 注册</label>
                <label><input type="checkbox" name="types[]" value="comment"> 评论</label>
                <label><input type="checkbox" name="types[]" value="login"> 登录</label>
            </div>
            <div class="form-group"><label>原因（可选）</label><input type="text" name="reason" placeholder="封禁原因"></div>
            <div style="display:flex;justify-content:flex-end"><button type="submit" class="btn">添加封禁</button></div>
        </form>
    </div>
    <?php if (empty($bans)): ?>
        <div class="empty">暂无封禁记录</div>
    <?php else: ?>
        <?php foreach ($bans as $ban): ?>
        <div class="ban-card">
            <div class="ban-top">
                <span class="ban-ip"><?= htmlspecialchars($ban['ip']) ?></span>
                <span class="ban-time"><?= htmlspecialchars($ban['time'] ?? '') ?></span>
            </div>
            <div class="ban-types">
                <?php foreach (($ban['types'] ?? []) as $t): ?>
                <span class="ban-tag <?= $t ?>"><?= $typeLabels[$t] ?? $t ?></span>
                <?php endforeach; ?>
            </div>
            <?php if (!empty($ban['reason'])): ?><div style="font-size:.82em;color:var(--text-muted);margin-bottom:8px">原因：<?= htmlspecialchars($ban['reason']) ?></div><?php endif; ?>
            <div class="ban-actions">
                <form method="post" style="display:inline"><input type="hidden" name="action" value="remove"><input type="hidden" name="ip" value="<?= htmlspecialchars($ban['ip']) ?>"><button type="submit" class="ban-icon-btn unban" title="解除封禁" onclick="return confirm('确定解除封禁？')"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/></svg></button></form>
                <button type="button" class="ban-icon-btn edit" title="编辑" onclick="openBanEdit('<?= htmlspecialchars($ban['ip']) ?>', <?= htmlspecialchars(json_encode($ban['types'] ?? [])) ?>)"><svg viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg></button>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</main>

<div class="ban-modal-mask" id="banEditModal">
    <div class="ban-modal-box">
        <div class="ban-modal-head">
            <div class="ban-modal-title">编辑封禁</div>
            <button class="ban-modal-close" id="banModalClose">&times;</button>
        </div>
        <div class="ban-modal-body">
            <div class="ban-modal-ip" id="banModalIp"></div>
            <form id="banEditForm" method="post">
                <input type="hidden" name="action" value="update_types">
                <input type="hidden" name="ip" id="banModalIpInput">
                <div class="ban-toggle-row">
                    <div><div class="ban-toggle-label">注册</div><div class="ban-toggle-desc">禁止该 IP 注册新账号</div></div>
                    <label class="ban-capsule"><input type="checkbox" name="types[]" value="register"><span class="capsule-slider"></span></label>
                </div>
                <div class="ban-toggle-row">
                    <div><div class="ban-toggle-label">评论</div><div class="ban-toggle-desc">禁止该 IP 发表评论</div></div>
                    <label class="ban-capsule"><input type="checkbox" name="types[]" value="comment"><span class="capsule-slider"></span></label>
                </div>
                <div class="ban-toggle-row">
                    <div><div class="ban-toggle-label">登录</div><div class="ban-toggle-desc">禁止该 IP 登录账号</div></div>
                    <label class="ban-capsule"><input type="checkbox" name="types[]" value="login"><span class="capsule-slider"></span></label>
                </div>
                <div class="ban-modal-actions">
                    <button type="button" class="ban-modal-cancel" id="banModalCancel">取消</button>
                    <button type="submit" class="ban-modal-save">保存</button>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
function openBanEdit(ip, types) {
    document.getElementById('banModalIp').textContent = ip;
    document.getElementById('banModalIpInput').value = ip;
    var modal = document.getElementById('banEditModal');
    modal.querySelectorAll('input[name="types[]"]').forEach(function(cb) {
        cb.checked = types.indexOf(cb.value) !== -1;
    });
    modal.classList.add('show');
}
document.getElementById('banModalClose').addEventListener('click', function() {
    document.getElementById('banEditModal').classList.remove('show');
});
document.getElementById('banModalCancel').addEventListener('click', function() {
    document.getElementById('banEditModal').classList.remove('show');
});
document.getElementById('banEditModal').addEventListener('click', function(e) {
    if (e.target === this) this.classList.remove('show');
});
</script>
</body>
</html>
