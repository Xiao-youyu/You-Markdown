<?php
session_start();
require_once __DIR__ . '/../utils.php';
if (!validateAdminSession()) {
    logUnauthorized('越权尝试访问用户管理');
    header('Location: ../?admin_login=1');
    exit;
}
$user = $_SESSION['cmt_user'];
$dataDir = '../data';
$_siteConfig = [];
$_configFile = $dataDir . '/.config.json';
if (file_exists($_configFile)) {
    $_siteConfig = json_decode(file_get_contents($_configFile), true) ?: [];
}
$_siteTitle = $_siteConfig['site_title'] ?? 'You Markdown';
checkAdminPath();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? '';
    if (!validateCSRFToken($token)) {
        header('Location: users.php?msg=' . urlencode('安全验证失败，请刷新页面重试') . '&msg_type=error');
        exit;
    }
}

function maskEmail($email) {
    if (empty($email)) return '未绑定';
    $parts = explode('@', $email, 2);
    if (count($parts) !== 2) return $email;
    $name = $parts[0];
    $domain = $parts[1];
    $len = mb_strlen($name, 'UTF-8');
    if ($len <= 1) return $name . '***@' . $domain;
    if ($len <= 2) return $name[0] . '*@' . $domain;
    return mb_substr($name, 0, 1, 'UTF-8') . str_repeat('*', min(4, $len - 1)) . '@' . $domain;
}

$msg = '';
$msgType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['act'] ?? '';

    if ($act === 'update_user') {
        $targetId = trim($_POST['user_id'] ?? '');
        if (empty($targetId)) { $msg = '参数不完整'; $msgType = 'error'; }
        else {
            $users = loadUsers();
            $found = false;
            $targetUser = null;
            foreach ($users as $u) { if ($u['id'] === $targetId) { $targetUser = $u; $found = true; break; } }
            if (!$found) { $msg = '用户不存在'; $msgType = 'error'; }
            elseif (($targetUser['role'] ?? '') === 'admin') { $msg = '不能修改管理员'; $msgType = 'error'; }
            else {
                $newPw = trim($_POST['new_password'] ?? '');
                $newEmail = trim($_POST['new_email'] ?? '');
                $disabled = isset($_POST['disabled']);
                foreach ($users as &$usr) {
                    if ($usr['id'] === $targetId) {
                        if ($newPw !== '') {
                            if (strlen($newPw) < 6) { $msg = '密码至少6位'; $msgType = 'error'; break; }
                            $usr['password'] = password_hash($newPw, PASSWORD_DEFAULT);
                        }
                        if ($newEmail !== ($usr['email'] ?? '')) {
                            if ($newEmail !== '' && !filter_var($newEmail, FILTER_VALIDATE_EMAIL)) { $msg = '邮箱格式无效'; $msgType = 'error'; break; }
                            $usr['email'] = $newEmail;
                        }
                        $usr['disabled'] = $disabled;
                        break;
                    }
                }
                unset($usr);
                if ($msgType !== 'error') {
                    saveUsers($users);
                    logOperation('更新用户信息', '用户: ' . ($targetUser['nickname'] ?? '') . ' (' . $targetId . ')' . ($newPw ? ' [密码已改]' : '') . ($disabled ? ' [已禁用]' : ' [已启用]'));
                    $msg = '用户信息已更新';
                }
            }
        }
    }

    if ($act === 'ban_user') {
        $targetId = trim($_POST['user_id'] ?? '');
        $banTypes = $_POST['ban_types'] ?? [];
        if (empty($targetId)) { $msg = '参数不完整'; $msgType = 'error'; }
        elseif (empty($banTypes)) { $msg = '请选择封禁类型'; $msgType = 'error'; }
        else {
            $users = loadUsers();
            $targetUser = null;
            foreach ($users as $u) { if ($u['id'] === $targetId) { $targetUser = $u; break; } }
            if (!$targetUser) { $msg = '用户不存在'; $msgType = 'error'; }
            elseif (($targetUser['role'] ?? '') === 'admin') { $msg = '不能封禁管理员'; $msgType = 'error'; }
            else {
                $targetIP = '';
                $logs = loadLogsList();
                foreach (array_reverse($logs) as $l) {
                    if (strpos($l['action'] ?? '', $targetUser['nickname'] ?? '') !== false) { $targetIP = $l['ip'] ?? ''; break; }
                }
                if (empty($targetIP)) {
                    $msg = '未找到该用户 IP，请在封禁管理中手动添加';
                    $msgType = 'error';
                } else {
                    addBan($targetIP, $banTypes, '管理员封禁用户: ' . ($targetUser['nickname'] ?? $targetId));
                    logOperation('封禁用户', '用户: ' . ($targetUser['nickname'] ?? '') . ' IP: ' . $targetIP . ' 类型: ' . implode(',', $banTypes));
                    $msg = '已封禁 IP: ' . $targetIP;
                }
            }
        }
    }

    if ($act === 'delete_user') {
        $targetId = trim($_POST['user_id'] ?? '');
        if (empty($targetId)) { $msg = '参数不完整'; $msgType = 'error'; }
        else {
            $users = loadUsers();
            $targetUser = null;
            foreach ($users as $u) { if ($u['id'] === $targetId) { $targetUser = $u; break; } }
            if (!$targetUser) { $msg = '用户不存在'; $msgType = 'error'; }
            elseif (($targetUser['role'] ?? '') === 'admin') { $msg = '不能删除管理员'; $msgType = 'error'; }
            else {
                $users = array_values(array_filter($users, function($u) use ($targetId) { return $u['id'] !== $targetId; }));
                saveUsers($users);
                logOperation('删除用户', '用户: ' . ($targetUser['nickname'] ?? '') . ' (' . $targetId . ')');
                $msg = '用户已删除';
            }
        }
    }

    header('Location: users.php?msg=' . urlencode($msg) . '&msg_type=' . urlencode($msgType));
    exit;
}

if (isset($_GET['msg'])) $msg = $_GET['msg'];
if (isset($_GET['msg_type'])) $msgType = $_GET['msg_type'];

$users = loadUsers();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title>用户管理 - <?= htmlspecialchars($_siteTitle) ?></title>
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
.alert{padding:10px 14px;border-radius:8px;margin-bottom:14px;font-size:.88em;animation:fadeSlide .3s ease}
.alert.success{color:#16a34a;background:#f0fdf4;border:1px solid #bbf7d0}
.alert.error{color:#dc2626;background:#fef2f2;border:1px solid #fecaca}
@keyframes fadeSlide{from{opacity:0;transform:translateY(-6px)}to{opacity:1;transform:translateY(0)}}
.user-card{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);padding:16px 18px;box-shadow:var(--shadow);margin-bottom:12px;position:relative}
.user-top{display:flex;align-items:center;gap:12px;margin-bottom:8px}
.user-avatar{width:40px;height:40px;border-radius:10px;object-fit:cover;flex-shrink:0;background:#e2e8f0}
.user-name-row{display:flex;align-items:center;gap:6px;flex-wrap:wrap}
.user-name{font-weight:600;font-size:.95em}
.role-badge{font-size:.7em;padding:1px 6px;border-radius:4px;font-weight:500}
.role-badge.admin{background:#dbeafe;color:#2563eb}
.role-badge.user{background:#f1f5f9;color:#64748b}
.status-badge{font-size:.7em;padding:1px 6px;border-radius:4px;font-weight:500}
.status-badge.disabled{background:#fee2e2;color:#dc2626}
.user-id{font-size:.75em;color:var(--text-muted);font-family:monospace;margin-top:2px}
.user-detail{font-size:.82em;color:var(--text-muted);margin-bottom:10px;line-height:1.7}
.user-detail span{margin-right:16px}
.user-actions{display:flex;gap:8px;justify-content:flex-end}
.action-btn{padding:7px 16px;border-radius:8px;font-size:.82em;font-weight:500;cursor:pointer;border:1px solid var(--border);background:var(--surface);color:var(--text-secondary);font-family:inherit;transition:all .2s;display:flex;align-items:center;gap:4px}
.action-btn:active{transform:scale(.97)}
.action-btn svg{width:14px;height:14px;stroke:currentColor;fill:none;stroke-width:2}
.action-btn.edit{border-color:var(--accent);color:var(--accent)}
.action-btn.ban{border-color:#fecaca;color:#dc2626}
/* Modal */
.modal-mask{position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:500;display:none;align-items:center;justify-content:center;padding:16px;backdrop-filter:blur(6px);-webkit-backdrop-filter:blur(6px)}
.modal-mask.show{display:flex}
.modal-box{background:var(--surface);border-radius:20px;width:420px;max-width:100%;overflow:hidden;box-shadow:0 12px 40px rgba(0,0,0,.12);animation:modalIn .25s cubic-bezier(.16,1,.3,1)}
@keyframes modalIn{from{opacity:0;transform:scale(.92) translateY(12px)}to{opacity:1;transform:none}}
.modal-head{padding:24px 24px 0;display:flex;align-items:center;justify-content:space-between}
.modal-title{font-size:18px;font-weight:700;color:var(--text)}
.modal-close{width:32px;height:32px;border-radius:50%;border:none;background:var(--bg);color:var(--text-muted);display:flex;align-items:center;justify-content:center;cursor:pointer;font-size:18px;transition:all .2s}
.modal-close:hover{background:#fef2f2;color:#ef4444}
.modal-body{padding:20px 24px 24px}
.modal-user-info{display:flex;align-items:center;gap:10px;margin-bottom:18px;padding-bottom:14px;border-bottom:1px solid var(--border)}
.modal-user-avatar{width:36px;height:36px;border-radius:8px;object-fit:cover;background:#e2e8f0}
.modal-user-name{font-weight:600;font-size:.95em}
.modal-user-id{font-size:.75em;color:var(--text-muted);font-family:monospace}
.form-group{margin-bottom:16px}
.form-group label{display:block;font-weight:500;margin-bottom:5px;font-size:.85em;color:var(--text-secondary)}
.form-group .hint{font-size:.78em;color:var(--text-muted);margin-top:4px}
input[type="text"],input[type="password"],input[type="email"]{width:100%;padding:9px 11px;border:1px solid var(--border);border-radius:8px;background:var(--bg);color:var(--text);font-family:inherit;font-size:14px;outline:none;transition:border-color .2s}
input:focus{border-color:var(--accent)}
.toggle-row{display:flex;align-items:center;justify-content:space-between;padding:12px 0;border-bottom:1px solid var(--border)}
.toggle-row:last-child{border-bottom:none}
.toggle-label{font-size:.9em;color:var(--text)}
.toggle-desc{font-size:.78em;color:var(--text-muted);margin-top:2px}
.capsule{position:relative;width:50px;height:28px;flex-shrink:0}
.capsule input{opacity:0;width:0;height:0}
.capsule .slider{position:absolute;inset:0;background:#cbd5e1;border-radius:14px;cursor:pointer;transition:.3s}
.capsule .slider:before{content:'';position:absolute;width:22px;height:22px;border-radius:50%;background:#fff;left:3px;top:3px;transition:.3s;box-shadow:0 1px 3px rgba(0,0,0,.15)}
.capsule input:checked+.slider{background:var(--accent)}
.capsule input:checked+.slider:before{transform:translateX(22px)}
.modal-actions{display:flex;gap:10px;margin-top:20px}
.modal-save{flex:1;padding:11px;border:none;border-radius:10px;background:var(--accent);color:#fff;font-size:14px;font-weight:600;cursor:pointer;font-family:inherit;transition:all .2s}
.modal-save:hover{opacity:.85}
.modal-cancel{padding:11px 20px;border:1px solid var(--border);border-radius:10px;background:var(--surface);color:var(--text-secondary);font-size:14px;cursor:pointer;font-family:inherit;transition:all .2s}
.modal-cancel:hover{border-color:var(--accent);color:var(--accent)}
.ban-checkbox-row{display:flex;gap:14px;flex-wrap:wrap;margin-top:8px}
.ban-checkbox-row label{display:flex;align-items:center;gap:5px;font-size:.88em;cursor:pointer}
.ban-checkbox-row input[type="checkbox"]{accent-color:var(--accent)}
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
    <div class="page-title"><svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>用户管理</div>
    <div class="page-desc">管理注册用户，支持修改信息、封禁和禁用操作</div>

    <?php if ($msg): ?><div class="alert <?= $msgType === 'error' ? 'error' : 'success' ?>"><?= htmlspecialchars($msg) ?></div><?php endif; ?>

    <?php if (empty($users)): ?>
        <div class="empty">暂无注册用户</div>
    <?php else: ?>
        <?php foreach ($users as $u): ?>
        <?php $isAdmin = ($u['role'] ?? '') === 'admin'; ?>
        <div class="user-card" data-uid="<?= htmlspecialchars($u['id'] ?? '') ?>" data-name="<?= htmlspecialchars($u['nickname'] ?? '') ?>" data-qq="<?= htmlspecialchars($u['qq'] ?? '') ?>" data-email="<?= htmlspecialchars($u['email'] ?? '') ?>" data-disabled="<?= !empty($u['disabled']) ? '1' : '0' ?>" data-admin="<?= $isAdmin ? '1' : '0' ?>">
            <div class="user-top">
                <?php
                $avatarSrc = $u['avatar'] ?? '';
                if (strpos($avatarSrc, 'data/') === 0) $avatarSrc = '../' . $avatarSrc;
                elseif (strpos($avatarSrc, 'api.php') === 0) $avatarSrc = '../' . $avatarSrc;
                ?>
                <img class="user-avatar" src="<?= htmlspecialchars($avatarSrc) ?>" onerror="this.style.display='none'">
                <div>
                    <div class="user-name-row">
                        <span class="user-name"><?= htmlspecialchars($u['nickname'] ?? '未知') ?></span>
                        <span class="role-badge <?= $isAdmin ? 'admin' : 'user' ?>"><?= $isAdmin ? '管理员' : '用户' ?></span>
                        <?php if (!empty($u['disabled'])): ?><span class="status-badge disabled">已禁用</span><?php endif; ?>
                    </div>
                    <div class="user-id">ID: <?= htmlspecialchars($u['id'] ?? '') ?></div>
                </div>
            </div>
            <div class="user-detail">
                <span>QQ: <?= htmlspecialchars(maskQQ($u['qq'] ?? '')) ?></span>
                <span>邮箱: <?= htmlspecialchars(maskEmail($u['email'] ?? '')) ?></span>
                <span>注册: <?= htmlspecialchars($u['created'] ?? '') ?></span>
            </div>
            <?php if (!$isAdmin): ?>
            <div class="user-actions">
                <button class="action-btn edit" onclick="openEdit(this.closest('.user-card'))"><svg viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>编辑</button>
                <button class="action-btn ban" onclick="openBan(this.closest('.user-card'))"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/></svg>封禁</button>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</main>

<!-- Edit Modal -->
<div class="modal-mask" id="editModal">
    <div class="modal-box">
        <div class="modal-head">
            <div class="modal-title">编辑用户</div>
            <button class="modal-close" onclick="closeModal('edit')">&times;</button>
        </div>
        <div class="modal-body">
            <div class="modal-user-info">
                <div>
                    <div class="modal-user-name" id="editUserName"></div>
                    <div class="modal-user-id" id="editUserId"></div>
                </div>
            </div>
            <form method="post" id="editForm">
                <input type="hidden" name="act" value="update_user">
                <input type="hidden" name="user_id" id="editFormUserId">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCSRFToken()) ?>">

                <div class="form-group">
                    <label>新密码</label>
                    <input type="password" name="new_password" placeholder="留空则不修改" minlength="6">
                    <div class="hint">至少6位，留空表示不修改密码</div>
                </div>
                <div class="form-group">
                    <label>新邮箱</label>
                    <input type="email" name="new_email" id="editEmail" placeholder="留空则不修改">
                    <div class="hint">留空表示不修改，填入新邮箱则更新</div>
                </div>

                <div class="toggle-row">
                    <div>
                        <div class="toggle-label">禁用账号</div>
                        <div class="toggle-desc">禁止该用户登录、评论、注册</div>
                    </div>
                    <label class="capsule"><input type="checkbox" name="disabled" id="editDisabled"><span class="slider"></span></label>
                </div>

                <div style="display:flex;gap:8px;margin-top:20px">
                    <button type="button" class="modal-cancel" style="flex:1" onclick="openDeleteConfirm()">删除用户</button>
                    <button type="submit" class="modal-save">保存</button>
                </div>
            </form>
            <form method="post" id="editDeleteForm" style="display:none">
                <input type="hidden" name="act" value="delete_user">
                <input type="hidden" name="user_id" id="editDeleteUserId">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCSRFToken()) ?>">
            </form>
        </div>
    </div>
</div>

<!-- Ban Modal -->
<div class="modal-mask" id="banModal">
    <div class="modal-box">
        <div class="modal-head">
            <div class="modal-title">封禁用户</div>
            <button class="modal-close" onclick="closeModal('ban')">&times;</button>
        </div>
        <div class="modal-body">
            <div class="modal-user-info">
                <div>
                    <div class="modal-user-name" id="banUserName"></div>
                    <div class="modal-user-id" id="banUserId"></div>
                </div>
            </div>
            <form method="post">
                <input type="hidden" name="act" value="ban_user">
                <input type="hidden" name="user_id" id="banFormUserId">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCSRFToken()) ?>">
                <div class="form-group">
                    <label>封禁功能（将封禁该用户关联 IP）</label>
                    <div class="ban-checkbox-row">
                        <label><input type="checkbox" name="ban_types[]" value="register" checked> 注册</label>
                        <label><input type="checkbox" name="ban_types[]" value="comment" checked> 评论</label>
                        <label><input type="checkbox" name="ban_types[]" value="login" checked> 登录</label>
                    </div>
                </div>
                <div class="modal-actions">
                    <button type="button" class="modal-cancel" onclick="closeModal('ban')">取消</button>
                    <button type="submit" class="modal-save" style="background:#dc2626">确认封禁</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openEdit(card) {
    var uid = card.dataset.uid;
    document.getElementById('editUserName').textContent = card.dataset.name;
    document.getElementById('editUserId').textContent = 'ID: ' + uid;
    document.getElementById('editFormUserId').value = uid;
    document.getElementById('editDeleteUserId').value = uid;
    document.getElementById('editEmail').value = card.dataset.email || '';
    document.getElementById('editDisabled').checked = card.dataset.disabled === '1';
    document.getElementById('confirmDeleteName').textContent = card.dataset.name;
    document.getElementById('editModal').classList.add('show');
}
function openBan(card) {
    var uid = card.dataset.uid;
    document.getElementById('banUserName').textContent = card.dataset.name;
    document.getElementById('banUserId').textContent = 'ID: ' + uid;
    document.getElementById('banFormUserId').value = uid;
    document.getElementById('banModal').classList.add('show');
}
function closeModal(type) {
    document.getElementById(type + 'Modal').classList.remove('show');
}
document.querySelectorAll('.modal-mask').forEach(function(mask) {
    mask.addEventListener('click', function(e) { if (e.target === this) this.classList.remove('show'); });
});
function openDeleteConfirm() {
    document.getElementById('confirmDeleteModal').classList.add('show');
}
function closeDeleteConfirm() {
    document.getElementById('confirmDeleteModal').classList.remove('show');
}
function confirmDeleteSubmit() {
    closeDeleteConfirm();
    document.getElementById('editDeleteForm').submit();
}
</script>
<!-- Confirm Delete Modal -->
<div class="modal-mask" id="confirmDeleteModal">
    <div class="modal-box">
        <div class="modal-head">
            <div class="modal-title">确认删除</div>
            <button class="modal-close" onclick="closeDeleteConfirm()">&times;</button>
        </div>
        <div class="modal-body">
            <div style="font-size:.92em;color:var(--text-secondary);line-height:1.7;text-align:center;margin-bottom:20px">确定删除用户 <strong id="confirmDeleteName"></strong> ？<br><span style="color:#dc2626;font-size:.88em">此操作不可撤销</span></div>
            <div style="display:flex;gap:10px">
                <button type="button" class="modal-cancel" style="flex:1" onclick="closeDeleteConfirm()">取消</button>
                <button type="button" style="flex:1;padding:11px;border:none;border-radius:10px;background:#dc2626;color:#fff;font-size:14px;font-weight:600;cursor:pointer;font-family:inherit" onclick="confirmDeleteSubmit()">确认删除</button>
            </div>
        </div>
    </div>
</div>
</body>
</html>
