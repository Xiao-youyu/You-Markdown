<?php
session_start();
$dataDir = './data';
if (!is_dir($dataDir)) {
    mkdir($dataDir, 0755, true);
}

$pwFile = $dataDir . '/.password.json';
$defaultPw = 'youyou';

function getPwHash() {
    global $pwFile, $defaultPw;
    if (file_exists($pwFile)) {
        $data = json_decode(file_get_contents($pwFile), true);
        if (!empty($data['hash'])) return $data['hash'];
    }
    return password_hash($defaultPw, PASSWORD_DEFAULT);
}
function savePassword($pw) {
    global $pwFile;
    file_put_contents($pwFile, json_encode([
        'hash' => password_hash($pw, PASSWORD_DEFAULT),
        'changed' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_UNICODE));
}
function isFirstLogin() {
    global $pwFile;
    return !file_exists($pwFile);
}

if (isset($_POST['pw_action']) && $_POST['pw_action'] === 'login') {
    $inputPw = $_POST['password'] ?? '';
    if (password_verify($inputPw, getPwHash())) {
        $_SESSION['sc_authed'] = true;
        if (isFirstLogin()) {
            $_SESSION['must_change_pw'] = true;
        }
        header('Location: sc.php');
        exit;
    } else {
        $_SESSION['login_error'] = '密码错误';
        header('Location: sc.php');
        exit;
    }
}

if (isset($_POST['pw_action']) && $_POST['pw_action'] === 'change' && !empty($_SESSION['sc_authed'])) {
    $newPw = $_POST['new_password'] ?? '';
    $confirmPw = $_POST['confirm_password'] ?? '';
    if (strlen($newPw) < 4) {
        $_SESSION['pw_change_error'] = '密码至少4个字符';
    } elseif ($newPw !== $confirmPw) {
        $_SESSION['pw_change_error'] = '两次密码不一致';
    } else {
        savePassword($newPw);
        unset($_SESSION['must_change_pw']);
    }
    header('Location: sc.php');
    exit;
}

if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: sc.php');
    exit;
}

if (!empty($_SESSION['sc_authed']) && empty($_SESSION['must_change_pw']) && isset($_GET['delete'])) {
    $delFile = basename($_GET['delete']);
    $delPath = $dataDir . '/' . $delFile;
    if (file_exists($delPath) && strtolower(pathinfo($delFile, PATHINFO_EXTENSION)) === 'md') {
        $realDataPath = realpath($dataDir);
        $realDelPath = realpath($delPath);
        if ($realDelPath !== false && strpos($realDelPath, $realDataPath) === 0) {
            unlink($delPath);
        }
    }
    header('Location: sc.php?deleted=1');
    exit;
}

if (!empty($_SESSION['sc_authed']) && empty($_SESSION['must_change_pw']) && isset($_GET['action']) && $_GET['action'] === 'get_content') {
    header('Content-Type: application/json; charset=utf-8');
    $reqFile = basename($_GET['file'] ?? '');
    $reqPath = $dataDir . '/' . $reqFile;
    if (!file_exists($reqPath)) { echo json_encode(['success' => false]); exit; }
    $raw = file_get_contents($reqPath);
    $meta = []; $content = $raw;
    if (preg_match('/<!--META(.*?)-->/s', $raw, $m)) {
        $meta = json_decode(trim($m[1]), true) ?: [];
        $content = preg_replace('/<!--META.*?-->\n?/s', '', $raw);
    }
    $title = '';
    if (preg_match('/^#\s+(.+)/m', $content, $tm)) $title = $tm[1];
    else $title = preg_replace('/\.md$/i', '', $reqFile);
    echo json_encode(['success' => true, 'title' => $title, 'content' => $content, 'meta' => $meta], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!empty($_SESSION['sc_authed']) && empty($_SESSION['must_change_pw']) && $_SERVER['REQUEST_METHOD'] === 'POST' && empty($_POST['pw_action'])) {
    $title = $_POST['title'] ?? '';
    $category = $_POST['category'] ?? '';
    $tags = $_POST['tags'] ?? '';
    $excerpt = $_POST['excerpt'] ?? '';
    $content = $_POST['content'] ?? '';
    $author = $_POST['author'] ?? '';
    $license = $_POST['license'] ?? 'CC BY-NC-SA 4.0';
    $uploadedFile = $_FILES['markdown_file'] ?? null;
    $url = $_POST['url'] ?? '';
    $updateFile = $_POST['update_file'] ?? '';
    $uploadError = '';
    $uploadSuccess = false;

    if ($uploadedFile && $uploadedFile['error'] === UPLOAD_ERR_OK) {
        $content = file_get_contents($uploadedFile['tmp_name']);
    } elseif (!empty($url) && empty($content)) {
        $ctx = stream_context_create(['http' => ['timeout' => 10], 'https' => ['timeout' => 10]]);
        $fetched = @file_get_contents($url, false, $ctx);
        if ($fetched === false) { $uploadError = '无法从该链接获取内容'; }
        else { $content = $fetched; }
    }

    if (empty($content) && !$uploadError) { $uploadError = '请提供 Markdown 内容'; }

    if (!$uploadError) {
        if (empty($title)) {
            if (preg_match('/^#\s+(.+)/m', $content, $m)) { $title = $m[1]; }
            else { $title = '未命名文档'; }
        }
        $licenseUrlMap = ['CC BY 4.0' => 'https://creativecommons.org/licenses/by/4.0/', 'CC BY-SA 4.0' => 'https://creativecommons.org/licenses/by-sa/4.0/', 'CC BY-NC 4.0' => 'https://creativecommons.org/licenses/by-nc/4.0/', 'CC BY-NC-SA 4.0' => 'https://creativecommons.org/licenses/by-nc-sa/4.0/', 'CC BY-ND 4.0' => 'https://creativecommons.org/licenses/by-nd/4.0/', 'CC BY-NC-ND 4.0' => 'https://creativecommons.org/licenses/by-nc-nd/4.0/', 'CC0 1.0' => 'https://creativecommons.org/publicdomain/zero/1.0/'];
        $licenseUrl = $licenseUrlMap[$license] ?? '';
        $meta = json_encode(['category' => $category, 'tags' => $tags, 'excerpt' => $excerpt, 'author' => $author, 'license' => $license, 'licenseUrl' => $licenseUrl], JSON_UNESCAPED_UNICODE);
        $fullContent = "<!--META" . $meta . "-->\n" . $content;

        if (!empty($updateFile)) {
            $fn = basename($updateFile);
            $fp = $dataDir . '/' . $fn;
            if (file_exists($fp)) {
                if (file_put_contents($fp, $fullContent)) { $uploadSuccess = true; }
                else { $uploadError = '保存失败'; }
            } else { $uploadError = '原文件不存在'; }
        } else {
            $fn = preg_replace('/[^a-zA-Z0-9_\-\x{4e00}-\x{9fa5}]/u', '', $title);
            if (empty($fn)) { $fn = 'doc_' . time(); }
            $fn .= '.md';
            if (file_put_contents($dataDir . '/' . $fn, $fullContent)) { $uploadSuccess = true; }
            else { $uploadError = '保存失败'; }
        }
    }
    if ($uploadSuccess) $_SESSION['upload_success'] = true;
    if ($uploadError) $_SESSION['upload_error'] = $uploadError;
    header('Location: sc.php');
    exit;
}

if (empty($_SESSION['sc_authed'])) {
    $loginError = $_SESSION['login_error'] ?? '';
    unset($_SESSION['login_error']);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title>文档管理 - You Markdown</title>
<style>
@font-face{font-family:'ChineseFont';src:url('./fonts/luoliti.ttf') format('truetype');font-display:swap}
@font-face{font-family:'EnglishFont';src:url('./fonts/roundfont.ttf') format('truetype');font-display:swap}
:root{--accent-hue:220;--accent-sat:60%;--accent:hsl(var(--accent-hue),var(--accent-sat),50%);--bg:hsl(var(--accent-hue),60%,96%);--surface:#fff;--border:#dce7f5;--text:#1e293b;--text-muted:#94a3b8;--shadow:0 2px 8px rgba(0,0,0,.05);--radius:14px}
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:'EnglishFont','ChineseFont',-apple-system,sans-serif;background:var(--bg);color:var(--text);min-height:100vh;min-height:100dvh;display:flex;align-items:center;justify-content:center;padding:20px}
.login-card{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);padding:32px 28px;box-shadow:var(--shadow);max-width:380px;width:100%;text-align:center}
.login-icon{width:52px;height:52px;border-radius:14px;background:hsl(var(--accent-hue),var(--accent-sat),90%);color:var(--accent);display:flex;align-items:center;justify-content:center;margin:0 auto 14px}
.login-icon svg{width:26px;height:26px;stroke:currentColor;fill:none;stroke-width:2}
.login-title{font-size:1.2em;font-weight:650;margin-bottom:4px}
.login-desc{font-size:.85em;color:var(--text-muted);margin-bottom:20px}
.login-input{width:100%;padding:12px;border:1px solid var(--border);border-radius:8px;background:var(--bg);color:var(--text);font-size:15px;font-family:inherit;outline:none;text-align:center;letter-spacing:2px;transition:border-color .2s}
.login-input:focus{border-color:var(--accent)}
.login-btn{width:100%;padding:12px;border:none;border-radius:8px;background:var(--accent);color:#fff;font-size:15px;font-weight:600;cursor:pointer;margin-top:12px;font-family:inherit;transition:opacity .2s}
.login-btn:hover{opacity:.9}
.login-btn:active{opacity:.8}
.login-error{color:#dc2626;font-size:.85em;margin-top:10px}
</style>
</head>
<body>
<div class="login-card">
    <div class="login-icon"><svg viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg></div>
    <div class="login-title">文档管理</div>
    <div class="login-desc">请输入密码以访问管理界面</div>
    <form method="post">
        <input type="hidden" name="pw_action" value="login">
        <input type="password" name="password" class="login-input" placeholder="请输入密码" autofocus>
        <?php if ($loginError): ?><div class="login-error"><?= htmlspecialchars($loginError) ?></div><?php endif; ?>
        <button type="submit" class="login-btn">进入</button>
    </form>
</div>
</body>
</html>
<?php exit; } ?>

<?php
if (!empty($_SESSION['must_change_pw'])) {
    $pwChangeError = $_SESSION['pw_change_error'] ?? '';
    unset($_SESSION['pw_change_error']);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title>修改密码 - You Markdown</title>
<style>
@font-face{font-family:'ChineseFont';src:url('./fonts/luoliti.ttf') format('truetype');font-display:swap}
@font-face{font-family:'EnglishFont';src:url('./fonts/roundfont.ttf') format('truetype');font-display:swap}
:root{--accent-hue:220;--accent-sat:60%;--accent:hsl(var(--accent-hue),var(--accent-sat),50%);--bg:hsl(var(--accent-hue),60%,96%);--surface:#fff;--border:#dce7f5;--text:#1e293b;--text-muted:#94a3b8;--shadow:0 2px 8px rgba(0,0,0,.05);--radius:14px}
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:'EnglishFont','ChineseFont',-apple-system,sans-serif;background:var(--bg);color:var(--text);min-height:100vh;min-height:100dvh;display:flex;align-items:center;justify-content:center;padding:20px}
.card{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);padding:32px 28px;box-shadow:var(--shadow);max-width:400px;width:100%;text-align:center}
.card-icon{width:52px;height:52px;border-radius:14px;background:hsl(var(--accent-hue),var(--accent-sat),90%);color:var(--accent);display:flex;align-items:center;justify-content:center;margin:0 auto 14px}
.card-icon svg{width:26px;height:26px;stroke:currentColor;fill:none;stroke-width:2}
.card-title{font-size:1.2em;font-weight:650;margin-bottom:4px}
.card-desc{font-size:.85em;color:var(--text-muted);margin-bottom:20px}
.card input{width:100%;padding:12px;border:1px solid var(--border);border-radius:8px;background:var(--bg);color:var(--text);font-size:14px;font-family:inherit;outline:none;margin-bottom:10px;transition:border-color .2s}
.card input:focus{border-color:var(--accent)}
.card-btn{width:100%;padding:12px;border:none;border-radius:8px;background:var(--accent);color:#fff;font-size:15px;font-weight:600;cursor:pointer;margin-top:4px;font-family:inherit;transition:opacity .2s}
.card-btn:hover{opacity:.9}
.card-error{color:#dc2626;font-size:.85em;margin-top:8px}
.card-hint{font-size:.8em;color:var(--text-muted);margin-top:12px}
</style>
</head>
<body>
<div class="card">
    <div class="card-icon"><svg viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg></div>
    <div class="card-title">首次登录，请修改密码</div>
    <div class="card-desc">默认密码已不安全，请设置一个新密码</div>
    <form method="post">
        <input type="hidden" name="pw_action" value="change">
        <input type="password" name="new_password" placeholder="新密码（至少4个字符）" autofocus>
        <input type="password" name="confirm_password" placeholder="确认新密码">
        <?php if ($pwChangeError): ?><div class="card-error"><?= htmlspecialchars($pwChangeError) ?></div><?php endif; ?>
        <button type="submit" class="card-btn">确认修改</button>
    </form>
    <div class="card-hint">修改后将自动进入管理界面</div>
</div>
</body>
</html>
<?php exit; } ?>

<?php

$files = glob($dataDir . '/*.md');
$fileList = [];
if ($files) {
    usort($files, function($a, $b) { return filemtime($b) - filemtime($a); });
    foreach ($files as $file) {
        $filename = basename($file);
        if (strpos($filename, '.') === 0) continue;
        $content = file_get_contents($file);
        $displayName = preg_replace('/\.md$/i', '', $filename);
        if (preg_match('/^#\s+(.+)/m', $content, $m)) { $displayName = $m[1]; }
        $fileList[] = ['name' => $filename, 'displayName' => $displayName];
    }
}

$showSuccess = false;
$showError = '';
if (!empty($_SESSION['upload_success'])) { $showSuccess = true; unset($_SESSION['upload_success']); }
if (!empty($_SESSION['upload_error'])) { $showError = $_SESSION['upload_error']; unset($_SESSION['upload_error']); }
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title>文档管理 - You Markdown</title>
<style>
@font-face{font-family:'ChineseFont';src:url('./fonts/luoliti.ttf') format('truetype');font-display:swap}
@font-face{font-family:'EnglishFont';src:url('./fonts/roundfont.ttf') format('truetype');font-display:swap}
:root{
    --accent-hue:220;--accent-sat:60%;
    --accent:hsl(var(--accent-hue),var(--accent-sat),50%);
    --accent-hover:hsl(var(--accent-hue),calc(var(--accent-sat) + 10%),40%);
    --accent-light:hsl(var(--accent-hue),var(--accent-sat),90%);
    --bg:hsl(var(--accent-hue),60%,96%);--surface:#fff;--border:#dce7f5;
    --text:#1e293b;--text-secondary:#475569;--text-muted:#94a3b8;
    --shadow-sm:0 1px 2px rgba(0,0,0,.04);
    --shadow:0 2px 8px rgba(0,0,0,.05);
    --shadow-md:0 4px 16px rgba(0,0,0,.06);
    --radius:14px;--radius-sm:10px;
}
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:'EnglishFont','ChineseFont',-apple-system,sans-serif;background:var(--bg);color:var(--text);line-height:1.6;min-height:100vh;min-height:100dvh;-webkit-tap-highlight-color:transparent}

/* 顶栏 */
.top-bar{position:sticky;top:0;z-index:100;background:var(--surface);border-bottom:1px solid var(--border);box-shadow:var(--shadow-sm);display:flex;align-items:center;justify-content:space-between;padding:0 16px;height:52px}
.brand{font-size:14px;font-weight:650;color:var(--text-secondary)}
.header-right{display:flex;align-items:center;gap:4px}
.icon-btn{width:36px;height:36px;border-radius:8px;background:transparent;border:none;color:var(--text-secondary);display:flex;align-items:center;justify-content:center;cursor:pointer;transition:all .2s;text-decoration:none}
.icon-btn:active{opacity:.7}
.icon-btn svg{width:20px;height:20px;stroke:currentColor;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}

.main-container{max-width:960px;margin:0 auto;padding:16px 12px 80px}
.page-title{font-size:1.3em;font-weight:650;margin-bottom:18px;display:flex;align-items:center;gap:8px}
.page-title svg{width:24px;height:24px;stroke:var(--accent);fill:none;stroke-width:2}

/* 通知 */
.alert{padding:10px 14px;border-radius:8px;margin-bottom:14px;font-size:.88em;display:flex;align-items:center;gap:8px;animation:fadeSlide .3s ease}
.alert-success{color:#16a34a;background:#f0fdf4;border:1px solid #bbf7d0}
.alert-error{color:#dc2626;background:#fef2f2;border:1px solid #fecaca}
@keyframes fadeSlide{from{opacity:0;transform:translateY(-6px)}to{opacity:1;transform:translateY(0)}}

/* 表单卡片 */
.form-card{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);padding:20px;box-shadow:var(--shadow);margin-bottom:20px}
.form-card h2{font-size:1.05em;font-weight:650;margin-bottom:16px;display:flex;align-items:center;gap:8px}
.form-card h2 svg{width:18px;height:18px;stroke:var(--accent);fill:none;stroke-width:2}
.form-group{margin-bottom:14px}
.form-group label{display:block;font-weight:500;margin-bottom:5px;font-size:.85em;color:var(--text-secondary)}
input[type="text"],input[type="url"],textarea,select{width:100%;padding:10px 12px;border:1px solid var(--border);border-radius:8px;background:var(--bg);color:var(--text);font-family:inherit;font-size:14px;outline:none;transition:border-color .2s;-webkit-appearance:none}
input:focus,textarea:focus,select:focus{border-color:var(--accent)}
textarea{min-height:120px;resize:vertical;font-family:'JetBrains Mono','SF Mono',monospace;font-size:13px}
.form-row{display:grid;grid-template-columns:1fr 1fr;gap:12px}
.btn{background:var(--accent);color:#fff;border:none;padding:11px 24px;border-radius:8px;cursor:pointer;font-weight:600;font-size:14px;transition:all .2s;display:inline-flex;align-items:center;gap:6px;font-family:inherit;-webkit-tap-highlight-color:transparent}
.btn:active{opacity:.85}
.btn svg{width:16px;height:16px;stroke:currentColor;fill:none;stroke-width:2}
.btn-outline{background:var(--surface);color:var(--text-secondary);border:1px solid var(--border)}
.btn-outline:active{border-color:var(--accent);color:var(--accent)}
.btn-danger{background:#ef4444}
.btn-danger:active{background:#dc2626}
.btn-sm{padding:9px 18px;font-size:13px}
.form-actions{display:flex;gap:10px;margin-top:16px}
.char-count{text-align:right;font-size:.75em;color:var(--text-muted);margin-top:3px}

/* 上传方式选择 */
.method-selector{display:flex;gap:8px;margin-bottom:14px}
.method-btn{flex:1;padding:12px 8px;border:2px solid var(--border);border-radius:var(--radius-sm);background:var(--surface);cursor:pointer;text-align:center;transition:all .2s;display:flex;flex-direction:column;align-items:center;gap:5px;font-family:inherit;-webkit-tap-highlight-color:transparent}
.method-btn:active{transform:scale(.97)}
.method-btn.active{border-color:var(--accent);background:var(--accent-light)}
.method-btn svg{width:22px;height:22px;stroke:var(--accent);fill:none;stroke-width:2}
.method-btn span{font-size:.8em;font-weight:500;color:var(--text-secondary)}
.method-btn.active span{color:var(--accent)}
.method-panel{display:none;animation:fadeSlide .2s ease}
.method-panel.active{display:block}

/* 拖拽区域 */
.file-drop-zone{border:2px dashed var(--border);border-radius:var(--radius-sm);padding:24px 16px;text-align:center;cursor:pointer;transition:all .25s;background:var(--bg);position:relative}
.file-drop-zone:active{border-color:var(--accent);background:var(--accent-light)}
.file-drop-zone.dragover{border-color:var(--accent);background:var(--accent-light)}
.file-drop-icon{margin-bottom:6px}
.file-drop-icon svg{width:28px;height:28px;stroke:var(--accent);fill:none;stroke-width:1.5}
.file-drop-text{font-size:.9em;font-weight:500;color:var(--text-secondary)}
.file-drop-hint{font-size:.75em;color:var(--text-muted);margin-top:3px}
.file-drop-input{position:absolute;inset:0;opacity:0;cursor:pointer}
.file-selected-info{display:flex;align-items:center;gap:8px;margin-top:8px;padding:10px 12px;background:var(--accent-light);border-radius:8px;font-size:.88em;color:var(--accent)}
.file-selected-name{flex:1;font-weight:500;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.file-selected-remove{width:24px;height:24px;border-radius:50%;border:none;background:rgba(0,0,0,.1);color:var(--text-muted);cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:12px}

/* 文章列表 */
.articles-section{margin-top:4px}
.articles-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:14px}
.articles-header h2{font-size:1.05em;font-weight:650;display:flex;align-items:center;gap:8px}
.articles-header h2 svg{width:18px;height:18px;stroke:var(--accent);fill:none;stroke-width:2}
.article-count{font-size:.82em;color:var(--text-muted);background:var(--accent-light);padding:2px 10px;border-radius:12px}
.article-list{display:flex;flex-direction:column;gap:8px}
.article-item{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius-sm);padding:12px 14px;box-shadow:var(--shadow-sm);display:flex;align-items:center;gap:10px;transition:all .2s}
.article-item:active{box-shadow:var(--shadow);border-color:var(--accent)}
.article-icon{width:36px;height:36px;border-radius:8px;background:var(--accent-light);color:var(--accent);display:flex;align-items:center;justify-content:center;flex-shrink:0}
.article-icon svg{width:17px;height:17px;stroke:currentColor;fill:none;stroke-width:2}
.article-info{flex:1;min-width:0}
.article-name{font-weight:600;font-size:.9em;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.article-actions{display:flex;gap:5px}
.action-btn{width:32px;height:32px;border-radius:8px;border:1px solid var(--border);background:var(--surface);color:var(--text-secondary);display:flex;align-items:center;justify-content:center;cursor:pointer;transition:all .2s;text-decoration:none;-webkit-tap-highlight-color:transparent}
.action-btn:active{border-color:var(--accent);color:var(--accent)}
.action-btn.delete:active{border-color:#ef4444;color:#ef4444}
.action-btn svg{width:15px;height:15px;stroke:currentColor;fill:none;stroke-width:2}
.empty-state{text-align:center;padding:32px;color:var(--text-muted);font-size:.9em}

/* 弹窗 */
.modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:300;display:none;align-items:center;justify-content:center;padding:16px}
.modal-overlay.active{display:flex}
.modal-box{background:var(--surface);border-radius:var(--radius);padding:24px;box-shadow:var(--shadow-md);max-width:360px;width:100%;text-align:center}
.modal-box h3{font-size:1.05em;margin-bottom:10px}
.modal-box p{font-size:.88em;color:var(--text-secondary);margin-bottom:18px}
.modal-actions{display:flex;gap:10px;justify-content:center}

/* 编辑弹窗 */
.edit-modal .modal-box{max-width:680px;max-height:90vh;max-height:90dvh;overflow-y:auto;text-align:left;padding:24px 20px}
.edit-modal .modal-box h3{margin-bottom:16px;display:flex;align-items:center;gap:8px;font-size:1em}
.edit-modal .modal-box h3 svg{width:18px;height:18px;stroke:var(--accent);fill:none;stroke-width:2}
.edit-modal .form-group{margin-bottom:12px}
.edit-modal textarea{min-height:200px}

/* 暗色模式 */
[data-theme="dark"]{
    --bg:hsl(var(--accent-hue),40%,8%);--surface:#161b22;--border:#30363d;
    --text:#e6edf3;--text-secondary:#b1bac4;--text-muted:#768390;
    --accent-light:hsl(var(--accent-hue),var(--accent-sat),18%);
}

/* 手机端 */
@media(min-width:641px){
    .main-container{padding:20px 16px 60px}
    .form-card{padding:28px}
    .top-bar{padding:0 20px;height:56px}
    .page-title{font-size:1.5em;margin-bottom:24px}
    .page-title svg{width:28px;height:28px}
    .form-row{grid-template-columns:1fr 1fr;gap:16px}
    .article-item{padding:14px 20px;gap:14px}
    .article-icon{width:40px;height:40px;border-radius:10px}
    .article-icon svg{width:18px;height:18px}
    .edit-modal .modal-box{max-width:720px;padding:32px}
}
</style>
</head>
<body>

<header class="top-bar">
    <div><span class="brand">You Markdown</span></div>
    <div class="header-right">
        <a class="icon-btn" href="index.php" title="主页"><svg viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg></a>
        <a class="icon-btn" href="sc.php?logout=1" title="登出"><svg viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg></a>
    </div>
</header>

<main class="main-container">
    <div class="page-title">
        <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
        文档管理
    </div>

    <?php if ($showSuccess): ?><div class="alert alert-success">文档保存成功！</div><?php endif; ?>
    <?php if ($showError): ?><div class="alert alert-error"><?= htmlspecialchars($showError) ?></div><?php endif; ?>
    <?php if (isset($_GET['deleted'])): ?><div class="alert alert-success">文档已删除</div><?php endif; ?>

    <div class="form-card">
        <h2>
            <svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            上传新文档
        </h2>
        <form method="post" enctype="multipart/form-data">
            <div class="method-selector">
                <button type="button" class="method-btn active" data-method="file">
                    <svg viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                    <span>文件上传</span>
                </button>
                <button type="button" class="method-btn" data-method="url">
                    <svg viewBox="0 0 24 24"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>
                    <span>链接抓取</span>
                </button>
                <button type="button" class="method-btn" data-method="paste">
                    <svg viewBox="0 0 24 24"><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><rect x="8" y="2" width="8" height="4" rx="1" ry="1"/></svg>
                    <span>粘贴内容</span>
                </button>
            </div>
            <div class="method-panel active" data-panel="file">
                <div class="form-group">
                    <div class="file-drop-zone" id="fileDropZone">
                        <div class="file-drop-icon"><svg viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg></div>
                        <div class="file-drop-text">拖拽 .md 文件到此处，或点击选择</div>
                        <div class="file-drop-hint">支持 .md / .txt / .markdown</div>
                        <input type="file" name="markdown_file" accept=".md,.txt,.markdown" id="fileInput" class="file-drop-input">
                    </div>
                    <div class="file-selected-info" id="fileSelectedInfo" style="display:none">
                        <span class="file-selected-name" id="fileSelectedName"></span>
                        <button type="button" class="file-selected-remove" id="fileRemoveBtn">&times;</button>
                    </div>
                </div>
            </div>
            <div class="method-panel" data-panel="url">
                <div class="form-group">
                    <label>文档链接</label>
                    <input type="url" name="url" placeholder="https://example.com/doc.md">
                </div>
            </div>
            <div class="method-panel" data-panel="paste">
                <div class="form-group">
                    <label>Markdown 内容</label>
                    <textarea name="content" id="contentArea" placeholder="在此粘贴 Markdown 内容..."></textarea>
                    <div class="char-count" id="charCount"></div>
                </div>
            </div>
            <div class="form-group"><label>标题（留空则自动提取）</label><input type="text" name="title" placeholder="文档标题"></div>
            <div class="form-row">
                <div class="form-group"><label>作者</label><input type="text" name="author" placeholder="作者名称"></div>
                <div class="form-group"><label>许可证书</label>
                    <select name="license">
                        <option value="CC BY-NC-SA 4.0" selected>CC BY-NC-SA 4.0</option>
                        <option value="CC BY 4.0">CC BY 4.0</option>
                        <option value="CC BY-SA 4.0">CC BY-SA 4.0</option>
                        <option value="CC BY-NC 4.0">CC BY-NC 4.0</option>
                        <option value="CC BY-ND 4.0">CC BY-ND 4.0</option>
                        <option value="CC BY-NC-ND 4.0">CC BY-NC-ND 4.0</option>
                        <option value="CC0 1.0">CC0 1.0</option>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group"><label>分类</label><input type="text" name="category" placeholder="例如：技术、随笔"></div>
                <div class="form-group"><label>标签（逗号分隔）</label><input type="text" name="tags" placeholder="PHP, Markdown"></div>
            </div>
            <div class="form-group"><label>预览摘要（留空自动生成）</label><textarea name="excerpt" style="min-height:56px" placeholder="可选"></textarea></div>
            <div class="form-actions">
                <button type="submit" class="btn"><svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>上传文档</button>
            </div>
        </form>
    </div>

    <div class="articles-section">
        <div class="articles-header">
            <h2><svg viewBox="0 0 24 24"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>已有文章</h2>
            <span class="article-count"><?= count($fileList) ?> 篇</span>
        </div>
        <?php if (empty($fileList)): ?>
            <div class="empty-state">暂无文档</div>
        <?php else: ?>
            <div class="article-list">
                <?php foreach ($fileList as $f): ?>
                <div class="article-item">
                    <div class="article-icon"><svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg></div>
                    <div class="article-info"><div class="article-name"><?= htmlspecialchars($f['displayName']) ?></div></div>
                    <div class="article-actions">
                        <a class="action-btn" href="index.php?file=<?= urlencode($f['name']) ?>" title="查看"><svg viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg></a>
                        <button class="action-btn" title="编辑" onclick="openEditModal('<?= htmlspecialchars($f['name']) ?>')"><svg viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg></button>
                        <button class="action-btn delete" title="删除" onclick="confirmDelete('<?= htmlspecialchars($f['name']) ?>','<?= htmlspecialchars($f['displayName']) ?>')"><svg viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg></button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</main>

<div class="modal-overlay" id="deleteModal">
    <div class="modal-box">
        <h3>确认删除</h3>
        <p id="deleteModalText">确定要删除这篇文章吗？此操作不可撤销。</p>
        <div class="modal-actions">
            <button class="btn btn-outline btn-sm" onclick="closeModal('deleteModal')">取消</button>
            <a class="btn btn-danger btn-sm" id="deleteConfirmBtn" href="#">删除</a>
        </div>
    </div>
</div>

<div class="modal-overlay edit-modal" id="editModal">
    <div class="modal-box">
        <h3><svg viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>编辑文档 <span id="editFileName" style="font-weight:400;color:var(--text-muted);font-size:.82em"></span></h3>
        <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="update_file" id="editUpdateFile" value="">
            <div class="form-group"><label>Markdown 内容</label><textarea name="content" id="editContent" placeholder="Markdown 内容..."></textarea><div class="char-count" id="editCharCount"></div></div>
            <div class="form-group"><label>标题（留空则自动提取）</label><input type="text" name="title" id="editTitle" placeholder="文档标题"></div>
            <div class="form-row">
                <div class="form-group"><label>作者</label><input type="text" name="author" id="editAuthor" placeholder="作者"></div>
                <div class="form-group"><label>许可证书</label>
                    <select name="license" id="editLicense">
                        <option value="CC BY-NC-SA 4.0">CC BY-NC-SA 4.0</option>
                        <option value="CC BY 4.0">CC BY 4.0</option>
                        <option value="CC BY-SA 4.0">CC BY-SA 4.0</option>
                        <option value="CC BY-NC 4.0">CC BY-NC 4.0</option>
                        <option value="CC BY-ND 4.0">CC BY-ND 4.0</option>
                        <option value="CC BY-NC-ND 4.0">CC BY-NC-ND 4.0</option>
                        <option value="CC0 1.0">CC0 1.0</option>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group"><label>分类</label><input type="text" name="category" id="editCategory" placeholder="分类"></div>
                <div class="form-group"><label>标签（逗号分隔）</label><input type="text" name="tags" id="editTags" placeholder="标签"></div>
            </div>
            <div class="form-group"><label>预览摘要</label><textarea name="excerpt" id="editExcerpt" style="min-height:56px" placeholder="可选"></textarea></div>
            <div class="form-actions">
                <button type="submit" class="btn btn-sm"><svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>保存修改</button>
                <button type="button" class="btn btn-outline btn-sm" onclick="closeModal('editModal')">取消</button>
            </div>
        </form>
    </div>
</div>

<script>
(function(){var h=localStorage.getItem('md-reader-hue')||220;document.documentElement.style.setProperty('--accent-hue',h);var f=localStorage.getItem('md-font-type')||'default';if(f==='custom')document.body.style.fontFamily="'EnglishFont','ChineseFont',-apple-system,sans-serif";document.documentElement.setAttribute('data-theme',localStorage.getItem('md-theme')||'light')})();

function closeModal(id){document.getElementById(id).classList.remove('active')}
function confirmDelete(fn,dn){document.getElementById('deleteModalText').textContent='确定要删除「'+dn+'」吗？此操作不可撤销。';document.getElementById('deleteConfirmBtn').href='sc.php?delete='+encodeURIComponent(fn);document.getElementById('deleteModal').classList.add('active')}

document.querySelectorAll('.method-btn').forEach(function(b){b.addEventListener('click',function(){document.querySelectorAll('.method-btn').forEach(function(x){x.classList.remove('active')});document.querySelectorAll('.method-panel').forEach(function(x){x.classList.remove('active')});b.classList.add('active');document.querySelector('.method-panel[data-panel="'+b.dataset.method+'"]').classList.add('active')})});

(function(){var dz=document.getElementById('fileDropZone'),fi=document.getElementById('fileInput'),info=document.getElementById('fileSelectedInfo'),nm=document.getElementById('fileSelectedName'),rb=document.getElementById('fileRemoveBtn');if(!dz)return;function show(f){nm.textContent=f.name+' ('+(f.size/1024).toFixed(1)+' KB)';info.style.display='flex';dz.style.display='none'}function clear(){fi.value='';info.style.display='none';dz.style.display='block'}fi.addEventListener('change',function(){if(this.files.length)show(this.files[0])});if(rb)rb.addEventListener('click',clear);['dragenter','dragover'].forEach(function(e){dz.addEventListener(e,function(ev){ev.preventDefault();dz.classList.add('dragover')})});['dragleave','drop'].forEach(function(e){dz.addEventListener(e,function(ev){ev.preventDefault();dz.classList.remove('dragover')})});dz.addEventListener('drop',function(e){var f=e.dataTransfer.files;if(f.length&&f[0].name.match(/\.(md|txt|markdown)$/i)){fi.files=f;show(f[0])}})})();

(function(){var ta=document.getElementById('contentArea'),ct=document.getElementById('charCount');if(!ta||!ct)return;function u(){var l=ta.value.replace(/\s/g,'').length;ct.textContent=l>0?l+' 字':''}ta.addEventListener('input',u)})();

function openEditModal(fn){
    document.getElementById('editFileName').textContent=fn;
    document.getElementById('editUpdateFile').value=fn;
    document.getElementById('editContent').value='加载中...';
    document.getElementById('editTitle').value='';
    document.getElementById('editAuthor').value='';
    document.getElementById('editCategory').value='';
    document.getElementById('editTags').value='';
    document.getElementById('editExcerpt').value='';
    document.getElementById('editLicense').value='CC BY-NC-SA 4.0';
    document.getElementById('editCharCount').textContent='';
    document.getElementById('editModal').classList.add('active');
    fetch('sc.php?action=get_content&file='+encodeURIComponent(fn))
        .then(function(r){return r.json()})
        .then(function(d){
            if(!d.success)return;
            document.getElementById('editContent').value=d.content;
            document.getElementById('editTitle').value=d.title;
            if(d.meta){
                document.getElementById('editAuthor').value=d.meta.author||'';
                document.getElementById('editCategory').value=d.meta.category||'';
                document.getElementById('editTags').value=d.meta.tags||'';
                document.getElementById('editExcerpt').value=d.meta.excerpt||'';
                if(d.meta.license)document.getElementById('editLicense').value=d.meta.license;
            }
            var l=d.content.replace(/\s/g,'').length;
            document.getElementById('editCharCount').textContent=l>0?l+' 字':'';
        });
}
document.getElementById('editContent').addEventListener('input',function(){
    var l=this.value.replace(/\s/g,'').length;
    document.getElementById('editCharCount').textContent=l>0?l+' 字':'';
});

document.querySelectorAll('.modal-overlay').forEach(function(m){
    m.addEventListener('click',function(e){if(e.target===m)m.classList.remove('active')});
});
</script>
</body>
</html>
