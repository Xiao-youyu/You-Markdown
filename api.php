<?php
session_start();
require_once __DIR__ . '/utils.php';

$_rawInput = file_get_contents('php://input');
if (strlen($_rawInput) > 65536) { 
    jsonOut(['success' => false, 'error' => '请求数据过大'], 413);
}

$_csrfWhitelist = ['check', 'get', 'avatar', 'oauth_login', 'oauth_callback', 'bg_config'];
$_currentAction = $_GET['action'] ?? $_POST['action'] ?? '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !in_array($_currentAction, $_csrfWhitelist)) {
    $_csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (empty($_csrfToken)) $_csrfToken = $_POST['csrf_token'] ?? '';
    if (!validateCSRFToken($_csrfToken)) {
        jsonOut(['success' => false, 'error' => '安全验证失败，请刷新页面重试'], 403);
    }
}

header('Content-Type: application/json; charset=utf-8');
$dataDir = './data';
$commentDir = $dataDir . '/.comments';
$userFile = $dataDir . '/.users.json';
if (!is_dir($commentDir)) { mkdir($commentDir, 0755, true); }

function loadComments($article) {
    global $commentDir;
    $safe = preg_replace('/[^a-zA-Z0-9_\-\x{4e00}-\x{9fa5}]/u', '_', $article);
    $file = $commentDir . '/' . $safe . '.json';
    if (!file_exists($file)) return [];
    $data = json_decode(file_get_contents($file), true);
    return is_array($data) ? $data : [];
}
function saveComments($article, $comments) {
    global $commentDir;
    $safe = preg_replace('/[^a-zA-Z0-9_\-\x{4e00}-\x{9fa5}]/u', '_', $article);
    file_put_contents($commentDir . '/' . $safe . '.json', json_encode($comments, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}
function getUser() { return empty($_SESSION['cmt_user']) ? null : $_SESSION['cmt_user']; }
function validateSession() {
    if (empty($_SESSION['cmt_user'])) return null;
    $sess = $_SESSION['cmt_user'];
    $users = loadUsers();
    foreach ($users as $u) {
        if ($u['id'] === ($sess['id'] ?? '')) {
            $_SESSION['cmt_user']['role'] = $u['role'] ?? 'user';
            return $_SESSION['cmt_user'];
        }
    }
    session_unset();
    session_destroy();
    return null;
}
function jsonOut($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}
function ensureAdmin() {
    $users = loadUsers();
    foreach ($users as $u) { if (($u['role'] ?? '') === 'admin') return; }
    $users[] = [
        'id' => genId(),
        'qq' => 'youyou',
        'nickname' => '站长',
        'password' => password_hash('youyou', PASSWORD_DEFAULT),
        'avatar' => 'https://q1.qlogo.cn/g?b=qq&nk=youyou&s=100',
        'signature' => '网站管理员',
        'role' => 'admin',
        'created' => date('Y-m-d H:i:s')
    ];
    saveUsers($users);
}
function getAvatarUrl($qq) {
    return 'https://q1.qlogo.cn/g?b=qq&nk=' . urlencode($qq) . '&s=100';
}
function addReplyRecursive(&$replies, $parentId, $reply) {
    foreach ($replies as &$r) {
        if ($r['id'] === $parentId) {
            if (!isset($r['replies'])) $r['replies'] = [];
            $r['replies'][] = $reply;
            return true;
        }
        if (!empty($r['replies'])) {
            if (addReplyRecursive($r['replies'], $parentId, $reply)) return true;
        }
    }
    return false;
}
function delReplyRecursive(&$replies, $delId, $userId, $isAdmin) {
    foreach ($replies as $i => $r) {
        if ($r['id'] === $delId && ($isAdmin || $r['user_id'] === $userId)) {
            array_splice($replies, $i, 1);
            return true;
        }
        if (!empty($r['replies'])) {
            if (delReplyRecursive($r['replies'], $delId, $userId, $isAdmin)) return true;
        }
    }
    return false;
}
ensureAdmin();
$action = $_GET['action'] ?? $_POST['action'] ?? '';
if ($action === 'avatar') {
checkScanner();
    $qq = trim($_GET['qq'] ?? '');
    $uid = trim($_GET['uid'] ?? '');
    $avatarUrl = '';

    
    if (!empty($uid)) {
        $users = loadUsers();
        foreach ($users as $u) {
            if ($u['id'] === $uid) {
                $avatarUrl = $u['avatar'] ?? '';
                
                if (!empty($avatarUrl) && strpos($avatarUrl, 'data/') === 0 && file_exists('./' . $avatarUrl)) {
                    $ext = strtolower(pathinfo($avatarUrl, PATHINFO_EXTENSION));
                    $mimeMap = ['jpg'=>'image/jpeg','jpeg'=>'image/jpeg','png'=>'image/png','gif'=>'image/gif','webp'=>'image/webp'];
                    header('Content-Type: ' . ($mimeMap[$ext] ?? 'image/jpeg'));
                    header('Cache-Control: public, max-age=86400');
                    readfile('./' . $avatarUrl);
                    exit;
                }
                
                if (!empty($avatarUrl) && strpos($avatarUrl, 'qlogo.cn') !== false) {
                    $ctx = stream_context_create(['http' => ['timeout' => 5, 'method' => 'GET'], 'ssl' => ['verify_peer' => true]]);
                    $img = @file_get_contents($avatarUrl, false, $ctx);
                    if ($img !== false && strlen($img) > 100) {
                        header('Content-Type: image/jpeg');
                        header('Cache-Control: public, max-age=86400');
                        echo $img;
                        exit;
                    }
                }
                break;
            }
        }
    }

    
    if (empty($avatarUrl) && !empty($qq) && preg_match('/^[0-9]{5,12}$/', $qq)) {
        $url = getAvatarUrl($qq);
        $ctx = stream_context_create(['http' => ['timeout' => 5, 'method' => 'GET'], 'ssl' => ['verify_peer' => true]]);
        $img = @file_get_contents($url, false, $ctx);
        if ($img !== false && strlen($img) > 100) {
            header('Content-Type: image/jpeg');
            header('Cache-Control: public, max-age=86400');
            echo $img;
            exit;
        }
    }

    
    $initial = mb_substr($qq ?: $uid ?: '?', 0, 1, 'UTF-8');
    header('Content-Type: image/svg+xml');
    header('Cache-Control: public, max-age=86400');
    echo '<svg xmlns="http://www.w3.org/2000/svg" width="100" height="100"><rect width="100" height="100" fill="#8b95a5"/><text x="50" y="58" text-anchor="middle" fill="#fff" font-size="40" font-family="sans-serif">' . htmlspecialchars($initial) . '</text></svg>';
    exit;
}
if ($action === 'register' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $siteCfg = loadSiteConfig();
    if (empty($siteCfg['registration_enabled'])) {
        jsonOut(['success' => false, 'error' => '注册已关闭'], 403);
    }
    $clientIP = getClientIP();
    if (isIPBanned($clientIP, 'register')) jsonOut(['success' => false, 'error' => '你的 IP 已被封禁，无法注册'], 403);
    checkProxyBlock('注册');
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    $userEmail = '';
    if (!empty($siteCfg['email_verify_enabled'])) {
        $emailCode = trim($input['email_code'] ?? '');
        $userEmail = trim($input['email'] ?? '');
        if (empty($userEmail)) jsonOut(['success' => false, 'error' => '请填写邮箱'], 400);
        if (empty($emailCode)) jsonOut(['success' => false, 'error' => '请输入邮箱验证码'], 400);
        $verifyResult = verifyEmailCode($userEmail, $emailCode);
        if (!$verifyResult['success']) jsonOut(['success' => false, 'error' => $verifyResult['error']], 400);
    }
    $regRateFile = './data/.reg_rates.json';
    $regRates = file_exists($regRateFile) ? json_decode(file_get_contents($regRateFile), true) : [];
    if (!is_array($regRates)) $regRates = [];
    $ipRegs = array_filter($regRates, function($r) use ($clientIP) { return ($r['ip'] ?? '') === $clientIP; });
    $regLimit = max(1, intval($siteCfg['max_registrations_per_ip'] ?? $siteCfg['reg_limit_per_ip'] ?? 3));
    if (count($ipRegs) >= $regLimit) {
        logAbnormal($clientIP, '频繁注册（累计' . count($ipRegs) . '次，限制' . $regLimit . '次）');
        if ($siteCfg['auto_ban'] ?? false) addBan($clientIP, ['register'], '自动封禁：频繁注册');
        jsonOut(['success' => false, 'error' => '注册次数已达上限'], 429);
    }
    $qq = trim($input['qq'] ?? '');
    $nick = trim($input['nickname'] ?? '');
    $pw = $input['password'] ?? '';
    if (empty($qq) || empty($pw)) jsonOut(['success' => false, 'error' => 'QQ号和密码不能为空'], 400);
    if (strlen($pw) < 6) jsonOut(['success' => false, 'error' => '密码至少6位'], 400);
    if (empty($nick)) $nick = '用户' . substr($qq, -4);
    $nick = mb_substr($nick, 0, 20, 'UTF-8');
    $users = loadUsers();
    foreach ($users as $u) { if (($u['qq'] ?? '') === $qq) jsonOut(['success' => false, 'error' => '该QQ号已注册'], 409); }
    $avatarUrl = getAvatarUrl($qq);
    $new = [
        'id' => genId(), 'qq' => $qq, 'nickname' => $nick, 'email' => $userEmail,
        'password' => password_hash($pw, PASSWORD_DEFAULT),
        'avatar' => $avatarUrl, 'signature' => '', 'role' => 'user',
        'created' => date('Y-m-d H:i:s')
    ];
    $users[] = $new;
    saveUsers($users);
    $regRates[] = ['ip' => $clientIP, 't' => time()];
    file_put_contents($regRateFile, json_encode($regRates));
    logSensitiveOp('新用户注册', $nick . ' (' . $qq . ')');
    logOperation('新用户注册', $nick . ' (QQ: ' . maskQQ($qq) . ')');
    
    $body = '<div style="height:2px;background:linear-gradient(90deg,transparent,#7fb069,transparent);margin:0 0 20px;border-radius:1px;"></div>';
    $body .= '<div style="background:#f4f7f2;border-left:4px solid #7fb069;border-radius:0 8px 8px 0;padding:16px 20px;">';
    $body .= '<table width="100%" cellpadding="0" cellspacing="0" style="font-size:13px;">';
    $body .= '<tr><td style="padding:5px 0;color:#999;width:50px;">昵称</td><td style="padding:5px 0;color:#1a1a1a;font-weight:600;">' . htmlspecialchars($nick) . '</td></tr>';
    $body .= '<tr><td style="padding:5px 0;color:#999;">QQ</td><td style="padding:5px 0;color:#333;">' . htmlspecialchars($qq) . '</td></tr>';
    $body .= '<tr><td style="padding:5px 0;color:#999;">IP</td><td style="padding:5px 0;color:#333;font-family:Menlo,Consolas,monospace;">' . $clientIP . '</td></tr>';
    $body .= '<tr><td style="padding:5px 0;color:#999;">时间</td><td style="padding:5px 0;color:#333;">' . date('Y-m-d H:i:s') . '</td></tr>';
    $body .= '</table></div>';
    notifyAdmin('新用户注册 - ' . $nick, $body, '', '#7fb069');
    session_regenerate_id(true);
    $_SESSION['cmt_user'] = [
        'id' => $new['id'], 'qq' => $qq, 'nickname' => $nick,
        'avatar' => $avatarUrl, 'signature' => '', 'role' => 'user',
        
    ];
    $_safe = $_SESSION['cmt_user']; $_safe['qq'] = maskQQ($_safe['qq'] ?? ''); jsonOut(['success' => true, 'user' => $_safe]);
}
if ($action === 'login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $clientIP = getClientIP();
    if (isIPBanned($clientIP, 'login')) jsonOut(['success' => false, 'error' => '你的 IP 已被封禁，无法登录'], 403);
    checkProxyBlock('登录');
    $input = json_decode(file_get_contents('php://input'), true);
    $qq = trim($input['qq'] ?? '');
    $pw = $input['password'] ?? '';
    if (empty($qq) || empty($pw)) jsonOut(['success' => false, 'error' => 'QQ号和密码不能为空'], 400);
    $users = loadUsers();
    $isAdminFirst = false;
    $loginFailed = false;
    foreach ($users as $u) {
        if (($u['qq'] ?? '') === $qq && password_verify($pw, $u['password'])) {
            if (!empty($u['disabled'])) {
                logAbnormal($clientIP, '已禁用用户尝试登录: ' . ($u['nickname'] ?? $qq));
                jsonOut(['success' => false, 'error' => '该账号已被禁用，请联系管理员'], 403);
            }
            session_regenerate_id(true);
            $avatar = $u['avatar'] ?? getAvatarUrl($qq);
            $_SESSION['cmt_user'] = [
                'id' => $u['id'], 'qq' => $u['qq'],
                'nickname' => $u['nickname'] ?? '',
                'avatar' => $avatar,
                'signature' => $u['signature'] ?? '',
                'role' => $u['role'] ?? 'user'
            ];
            if (($u['role'] ?? '') === 'admin' && $u['qq'] === 'youyou') $isAdminFirst = true;
            logSensitiveOp('用户登录', $u['nickname'] . ' (' . maskQQ($u['qq'] ?? '') . ')');
            logOperation('用户登录', $u['nickname'] . ' (' . maskQQ($u['qq'] ?? '') . ')');
            
            if (($u['role'] ?? '') === 'admin') {
                $loginTime = date('Y-m-d H:i:s');
                $loginIP = $clientIP;
                $loginUA = $_SERVER['HTTP_USER_AGENT'] ?? '未知设备';
                $body = '<div style="height:2px;background:linear-gradient(90deg,transparent,#4ecdc4,transparent);margin:0 0 20px;border-radius:1px;"></div>';
                $body .= '<div style="background:#f0f7f6;border-left:4px solid #4ecdc4;border-radius:0 8px 8px 0;padding:16px 20px;margin-bottom:16px;">';
                $body .= '<table width="100%" cellpadding="0" cellspacing="0" style="font-size:13px;">';
                $body .= '<tr><td style="padding:5px 0;color:#999;width:50px;">账号</td><td style="padding:5px 0;color:#1a1a1a;font-weight:600;">' . htmlspecialchars($u['nickname'] ?? $u['qq']) . ' <span style="color:#999;font-weight:400;">(' . maskQQ($u['qq'] ?? '') . ')</span></td></tr>';
                $body .= '<tr><td style="padding:5px 0;color:#999;">时间</td><td style="padding:5px 0;color:#333;">' . $loginTime . '</td></tr>';
                $body .= '<tr><td style="padding:5px 0;color:#999;">IP</td><td style="padding:5px 0;color:#333;font-family:Menlo,Consolas,monospace;">' . $loginIP . '</td></tr>';
                $body .= '<tr><td style="padding:5px 0;color:#999;">设备</td><td style="padding:5px 0;color:#666;font-size:12px;">' . htmlspecialchars(mb_substr($loginUA, 0, 50, 'UTF-8')) . '</td></tr>';
                $body .= '</table></div>';
                $body .= '<p style="margin:0;font-size:12px;color:#e67e22;text-align:center;">不是你操作的？尽快改密码</p>';
                notifyAdmin('登录通知 - ' . ($u['nickname'] ?? ''), $body, '', '#4ecdc4');
            }
            $_safe = $_SESSION['cmt_user']; $_safe['qq'] = maskQQ($_safe['qq'] ?? ''); jsonOut(['success' => true, 'user' => $_safe, 'isAdminFirstLogin' => $isAdminFirst]);
        }
    }
    $failFile = './data/.login_fails.json';
    $fails = file_exists($failFile) ? json_decode(file_get_contents($failFile), true) : [];
    if (!is_array($fails)) $fails = [];
    $now = time();
    $fails = array_filter($fails, function($f) use ($now) { return ($now - ($f['t'] ?? 0)) < 3600; });
    $fails[] = ['ip' => $clientIP, 't' => $now];
    file_put_contents($failFile, json_encode($fails));
    $ipFails = array_filter($fails, function($f) use ($clientIP) { return $f['ip'] === $clientIP; });
    $loginCfg = loadSiteConfig();
    $maxLoginFails = max(3, intval($loginCfg['max_login_fails'] ?? 10));
    if (count($ipFails) >= $maxLoginFails) {
        logAbnormal($clientIP, '频繁错误登录（' . count($ipFails) . '次/小时）');
        if ($loginCfg['auto_ban'] ?? false) addBan($clientIP, ['login'], '自动封禁：频繁错误登录');
        $fails = array_filter($fails, function($f) use ($clientIP) { return $f['ip'] !== $clientIP; });
        file_put_contents($failFile, json_encode(array_values($fails)));
    }
    jsonOut(['success' => false, 'error' => 'QQ号或密码错误'], 401);
}
if ($action === 'logout' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    unset($_SESSION['cmt_user']);
    jsonOut(['success' => true]);
}
if ($action === 'check') {
    $u = validateSession();
    if ($u) { $_s = $u; $_s['qq'] = maskQQ($_s['qq'] ?? ''); jsonOut(['success' => true, 'loggedIn' => true, 'user' => $_s, 'csrf_token' => generateCSRFToken()]); } else { jsonOut(['success' => true, 'loggedIn' => false, 'csrf_token' => generateCSRFToken()]); }
}
if ($action === 'update_profile' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $u = validateSession();
    if (!$u) jsonOut(['success' => false, 'error' => '请先登录'], 401);
    $input = json_decode(file_get_contents('php://input'), true);
    $nick = trim($input['nickname'] ?? '');
    $sign = trim($input['signature'] ?? '');
    if (empty($nick)) jsonOut(['success' => false, 'error' => '昵称不能为空'], 400);
    $nick = mb_substr($nick, 0, 20, 'UTF-8');
    $sign = mb_substr($sign, 0, 16, 'UTF-8');
    $users = loadUsers();
    foreach ($users as &$usr) {
        if ($usr['id'] === $u['id']) {
            $usr['nickname'] = $nick;
            $usr['signature'] = $sign;
            break;
        }
    }
    unset($usr);
    saveUsers($users);
    $_SESSION['cmt_user']['nickname'] = $nick;
    $_SESSION['cmt_user']['signature'] = $sign;
    $_safe = $_SESSION['cmt_user']; $_safe['qq'] = maskQQ($_safe['qq'] ?? ''); jsonOut(['success' => true, 'user' => $_safe]);
}
if ($action === 'admin_setup' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $u = validateSession();
    if (!$u || ($u['role'] ?? '') !== 'admin') { logAbnormal(getClientIP(), '越权尝试修改站长信息'); jsonOut(['success' => false, 'error' => '无权限'], 403); }
    $input = json_decode(file_get_contents('php://input'), true);
    $qq = trim($input['qq'] ?? '');
    $nick = trim($input['nickname'] ?? '');
    $pw = $input['password'] ?? '';
    if (empty($qq)) jsonOut(['success' => false, 'error' => '请填写QQ号'], 400);
    if (empty($nick)) jsonOut(['success' => false, 'error' => '请填写昵称'], 400);
    if ($pw && strlen($pw) < 6) jsonOut(['success' => false, 'error' => '密码至少6位'], 400);
    $nick = mb_substr($nick, 0, 20, 'UTF-8');
    $avatarUrl = getAvatarUrl($qq);
    $users = loadUsers();
    foreach ($users as &$usr) {
        if ($usr['id'] === $u['id']) {
            $usr['qq'] = $qq;
            $usr['nickname'] = $nick;
            $usr['avatar'] = $avatarUrl;
            if ($pw) $usr['password'] = password_hash($pw, PASSWORD_DEFAULT);
            break;
        }
    }
    unset($usr);
    saveUsers($users);
    $_SESSION['cmt_user']['qq'] = $qq;
    $_SESSION['cmt_user']['nickname'] = $nick;
    $_SESSION['cmt_user']['avatar'] = $avatarUrl;
    logSensitiveOp('管理员信息修改', 'QQ:' . $qq . ' 昵称:' . $nick . ($pw ? ' [密码已修改]' : ''));
    jsonOut(['success' => true, 'user' => $_SESSION['cmt_user']]);
}
if ($action === 'get') {
    $article = $_GET['article'] ?? '';
    if (empty($article)) jsonOut(['success' => false, 'error' => '缺少文章参数'], 400);
    $comments = loadComments($article);
    usort($comments, function($a, $b) { return strcmp($b['created_at'] ?? '', $a['created_at'] ?? ''); });
    jsonOut(['success' => true, 'comments' => $comments]);
}
if ($action === 'post' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $clientIP = getClientIP();
    if (isIPBanned($clientIP, 'comment')) jsonOut(['success' => false, 'error' => '你的 IP 已被封禁，无法评论'], 403);
    checkProxyBlock('评论');
    $siteCfg = loadSiteConfig();
    if (!($siteCfg['comments_enabled'] ?? true)) jsonOut(['success' => false, 'error' => '评论区已关闭'], 403);
    $u = validateSession();
    if (!$u && empty($siteCfg['guest_comments_enabled'])) jsonOut(['success' => false, 'error' => '请先登录'], 401);
    if ($u && !empty($u['disabled'])) jsonOut(['success' => false, 'error' => '你的账号已被禁用'], 403);
    if (!$u && !empty($siteCfg['guest_comments_enabled'])) {
        $u = ['id' => 'guest', 'nickname' => '访客', 'avatar' => '', 'qq' => '', 'role' => 'guest'];
    }
    $rateFile = './data/.comment_rates.json';
    $rates = file_exists($rateFile) ? json_decode(file_get_contents($rateFile), true) : [];
    if (!is_array($rates)) $rates = [];
    $now = time();
    $rates = array_filter($rates, function($r) use ($now) { return ($now - ($r['t'] ?? 0)) < 60; });
    $rates[] = ['ip' => $clientIP, 't' => $now];
    file_put_contents($rateFile, json_encode($rates));
    $ipRates = array_filter($rates, function($r) use ($clientIP) { return $r['ip'] === $clientIP; });
    $maxCommentsPerMin = max(1, intval($siteCfg['max_comments_per_minute'] ?? 5));
    if (count($ipRates) > $maxCommentsPerMin) {
        logAbnormal($clientIP, '频繁评论（' . count($ipRates) . '条/分钟）');
        if ($siteCfg['auto_ban'] ?? false) addBan($clientIP, ['comment'], '自动封禁：频繁评论');
        jsonOut(['success' => false, 'error' => '评论太频繁，请稍后再试'], 429);
    }
    $input = json_decode(file_get_contents('php://input'), true);
    $article = trim($input['article'] ?? '');
    $content = trim($input['content'] ?? '');
    $content = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $content);
    if (empty($article)) jsonOut(['success' => false, 'error' => '缺少文章参数'], 400);
    if (empty($content)) jsonOut(['success' => false, 'error' => '内容不能为空'], 400);
    if (mb_strlen($content, 'UTF-8') > 1000) jsonOut(['success' => false, 'error' => '评论不能超过1000字'], 400);
    $comments = loadComments($article);
    $users = loadUsers();
    $userNick = $u['nickname'];
    $userSign = '';
    $userAvatar = $u['avatar'] ?? '';
    $userQQ = $u['qq'] ?? '';
    foreach ($users as $usr) {
        if ($usr['id'] === $u['id']) {
            $userNick = $usr['nickname'];
            $userSign = $usr['signature'] ?? '';
            $userAvatar = $usr['avatar'] ?? getAvatarUrl($usr['qq'] ?? '');
            $userQQ = $usr['qq'] ?? '';
            break;
        }
    }
    $new = [
        'id' => genId(), 'user_id' => $u['id'], 'qq' => maskQQ($userQQ), 'nickname' => $userNick,
        'avatar' => 'api.php?action=avatar&uid=' . $u['id'], 'signature' => $userSign, 'content' => $content,
        'likes' => 0, 'replies' => [], 'created_at' => date('Y-m-d H:i:s')
    ];
    $comments[] = $new;
    saveComments($article, $comments);
    jsonOut(['success' => true, 'comment' => $new]);
}
if ($action === 'reply' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $clientIP = getClientIP();
    if (isIPBanned($clientIP, 'comment')) jsonOut(['success' => false, 'error' => '你的 IP 已被封禁，无法回复'], 403);
    checkProxyBlock('回复');
    $u = validateSession();
    $replyCfg = loadSiteConfig();
    if (!$u && empty($replyCfg['guest_comments_enabled'])) jsonOut(['success' => false, 'error' => '请先登录'], 401);
    if ($u && !empty($u['disabled'])) jsonOut(['success' => false, 'error' => '你的账号已被禁用'], 403);
    if (!$u && !empty($replyCfg['guest_comments_enabled'])) {
        $u = ['id' => 'guest', 'nickname' => '访客', 'avatar' => '', 'qq' => '', 'role' => 'guest'];
    }
    $input = json_decode(file_get_contents('php://input'), true);
    $article = trim($input['article'] ?? '');
    $parentId = trim($input['parent_id'] ?? '');
    $content = trim($input['content'] ?? '');
    $content = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $content);
    if (empty($article) || empty($parentId) || empty($content)) jsonOut(['success' => false, 'error' => '参数不完整'], 400);
    if (mb_strlen($content, 'UTF-8') > 1000) jsonOut(['success' => false, 'error' => '回复不能超过1000字'], 400);
    $comments = loadComments($article);
    $users = loadUsers();
    $userNick = $u['nickname'];
    $userAvatar = $u['avatar'] ?? '';
    $userQQ = $u['qq'] ?? '';
    foreach ($users as $usr) {
        if ($usr['id'] === $u['id']) {
            $userNick = $usr['nickname'];
            $userAvatar = $usr['avatar'] ?? getAvatarUrl($usr['qq'] ?? '');
            $userQQ = $usr['qq'] ?? '';
            break;
        }
    }
    $reply = [
        'id' => genId(), 'user_id' => $u['id'], 'qq' => maskQQ($userQQ), 'nickname' => $userNick,
        'avatar' => 'api.php?action=avatar&uid=' . $u['id'], 'content' => $content,
        'likes' => 0, 'replies' => [], 'created_at' => date('Y-m-d H:i:s')
    ];
    $added = false;
    foreach ($comments as &$c) {
        if ($c['id'] === $parentId) {
            if (!isset($c['replies'])) $c['replies'] = [];
            $c['replies'][] = $reply;
            $added = true;
            break;
        }
        if (!empty($c['replies'])) {
            if (addReplyRecursive($c['replies'], $parentId, $reply)) {
                $added = true;
                break;
            }
        }
    }
    unset($c);
    if ($added) { saveComments($article, $comments); jsonOut(['success' => true]); }
    jsonOut(['success' => false, 'error' => '父评论不存在'], 404);
}
if ($action === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $u = validateSession();
    if (!$u) jsonOut(['success' => false, 'error' => '请先登录'], 401);
    $input = json_decode(file_get_contents('php://input'), true);
    $article = trim($input['article'] ?? '');
    $delId = trim($input['id'] ?? '');
    if (empty($article) || empty($delId)) jsonOut(['success' => false, 'error' => '参数不完整'], 400);
    $comments = loadComments($article);
    $isAdmin = ($u['role'] ?? '') === 'admin';
    $found = false;
    foreach ($comments as $i => $c) {
        if ($c['id'] === $delId && ($isAdmin || $c['user_id'] === $u['id'])) {
            array_splice($comments, $i, 1);
            $found = true;
            break;
        }
    }
    if (!$found) {
        foreach ($comments as &$c) {
            if (!empty($c['replies'])) {
                if (delReplyRecursive($c['replies'], $delId, $u['id'], $isAdmin)) {
                    $found = true;
                    break;
                }
            }
        }
        unset($c);
    }
    if ($found) { saveComments($article, $comments); jsonOut(['success' => true]); }
    logAbnormal(getClientIP(), '越权尝试删除评论: ' . $delId . ' (文章: ' . $article . ')');
    jsonOut(['success' => false, 'error' => '评论不存在或无权删除'], 404);
}
if ($action === 'bg_upload' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $u = validateSession();
    if (!$u || ($u['role'] ?? '') !== 'admin') jsonOut(['success' => false, 'error' => '无权限'], 403);
    if (!isset($_FILES['bg_image']) || $_FILES['bg_image']['error'] !== UPLOAD_ERR_OK) jsonOut(['success' => false, 'error' => '上传失败'], 400);
    $file = $_FILES['bg_image'];
    $extMap = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif', 'webp' => 'image/webp'];
    $origExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $detectedMime = null;
    if (function_exists('getimagesize')) {
        $imgInfo = @getimagesize($file['tmp_name']);
        if ($imgInfo && isset($imgInfo['mime'])) $detectedMime = $imgInfo['mime'];
    }
    if (!$detectedMime && isset($extMap[$origExt])) {
        $detectedMime = $extMap[$origExt];
    }
    if (!$detectedMime || !in_array($detectedMime, array_values($extMap))) {
        jsonOut(['success' => false, 'error' => '仅支持 JPG/PNG/GIF/WebP 格式'], 400);
    }
    if ($file['size'] > 10 * 1024 * 1024) jsonOut(['success' => false, 'error' => '文件大小不能超过 10MB'], 400);
    $saveExt = array_search($detectedMime, $extMap) ?: $origExt;
    $filename = 'bg_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $saveExt;
    $bgDir = './data/bg/';
    if (!is_dir($bgDir)) mkdir($bgDir, 0755, true);
    if (move_uploaded_file($file['tmp_name'], $bgDir . $filename)) {
        jsonOut(['success' => true, 'path' => 'data/bg/' . $filename]);
    }
    jsonOut(['success' => false, 'error' => '保存失败，请检查 data/bg/ 目录权限'], 500);
}
if ($action === 'bg_config' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $u = validateSession();
    if (!$u || ($u['role'] ?? '') !== 'admin') jsonOut(['success' => false, 'error' => '无权限'], 403);
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) jsonOut(['success' => false, 'error' => '无效的请求数据'], 400);
    $configFile = __DIR__ . '/data/.config.json';
    if (!is_dir(__DIR__ . '/data')) jsonOut(['success' => false, 'error' => 'data 目录不存在'], 500);
    if (file_exists($configFile) && !is_writable($configFile)) jsonOut(['success' => false, 'error' => '配置文件不可写，请检查文件权限'], 500);
    $config = loadSiteConfig();
    $config['bg_type'] = in_array($input['bg_type'] ?? '', ['none', 'image', 'api']) ? $input['bg_type'] : 'none';
    $config['bg_image'] = trim($input['bg_image'] ?? '');
    $config['bg_api_url'] = trim($input['bg_api_url'] ?? '');
    $config['bg_blur_enabled'] = !empty($input['bg_blur_enabled']);
    $config['bg_blur_level'] = max(0, min(50, intval($input['bg_blur_level'] ?? 0)));
    $config['bg_card_opacity'] = max(50, min(100, intval($input['bg_card_opacity'] ?? 100)));
    $json = json_encode($config, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    if ($json === null) jsonOut(['success' => false, 'error' => 'JSON 编码失败'], 500);
    $written = file_put_contents($configFile, $json, LOCK_EX);
    if ($written === false || $written === 0) jsonOut(['success' => false, 'error' => '写入失败(' . $written . ')，请检查 data/ 目录权限'], 500);
    $verify = json_decode(file_get_contents($configFile), true);
    if (($verify['bg_type'] ?? '') !== $config['bg_type']) jsonOut(['success' => false, 'error' => '写入验证失败'], 500);
    jsonOut(['success' => true, 'written' => $written, 'path' => $configFile]);
}
if ($action === 'bg_config' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $config = loadSiteConfig();
    jsonOut([
        'success' => true,
        'bg_type' => $config['bg_type'] ?? 'none',
        'bg_image' => $config['bg_image'] ?? '',
        'bg_api_url' => $config['bg_api_url'] ?? '',
        'bg_blur_enabled' => !empty($config['bg_blur_enabled']),
        'bg_blur_level' => $config['bg_blur_level'] ?? 0,
        'bg_card_opacity' => $config['bg_card_opacity'] ?? 100
    ]);
}
if ($action === 'oauth_callback') {
    $code = $_GET['code'] ?? '';
    $state = $_GET['state'] ?? '';
    if (empty($code)) jsonOut(['success' => false, 'error' => '缺少授权码'], 400);
    if (empty($_SESSION['oauth_state']) || !hash_equals($_SESSION['oauth_state'], $state)) {
        jsonOut(['success' => false, 'error' => '无效的授权状态'], 400);
    }
    unset($_SESSION['oauth_state']);
    $redirectUri = $_SESSION['oauth_redirect'] ?? '';
    unset($_SESSION['oauth_redirect']);
    $siteConfig = loadSiteConfig();
    $clientId = $siteConfig['oauth_client_id'] ?? '';
    $clientSecret = $siteConfig['oauth_client_secret'] ?? '';
    $oauthBaseUrl = rtrim($siteConfig['oauth_base_url'] ?? '', '/');
    $tokenPath = $siteConfig['oauth_token_path'] ?? '/api/oauth.php?action=token';
    $verifyPath = $siteConfig['oauth_verify_path'] ?? '/api/oauth.php?action=verify';
    $tokenUrl = $oauthBaseUrl . $tokenPath
        . '?code=' . urlencode($code)
        . '&client_id=' . urlencode($clientId)
        . '&client_secret=' . urlencode($clientSecret)
        . '&redirect_uri=' . urlencode($redirectUri);
    $ctx = stream_context_create(['http' => ['timeout' => 10, 'method' => 'GET']]);
    $tokenResp = @file_get_contents($tokenUrl, false, $ctx);
    if ($tokenResp === false) jsonOut(['success' => false, 'error' => '获取 Token 失败'], 500);
    $tokenData = json_decode($tokenResp, true);
    if (empty($tokenData['access_token'])) jsonOut(['success' => false, 'error' => 'Token 无效: ' . ($tokenData['error'] ?? '未知错误')], 400);
    $verifyUrl = $oauthBaseUrl . $verifyPath
        . '?access_token=' . urlencode($tokenData['access_token']);
    $verifyResp = @file_get_contents($verifyUrl, false, $ctx);
    if ($verifyResp === false) jsonOut(['success' => false, 'error' => '验证 Token 失败'], 500);
    $userInfo = json_decode($verifyResp, true);
    if (empty($userInfo['valid']) || empty($userInfo['user'])) jsonOut(['success' => false, 'error' => 'Token 无效或已过期'], 401);
    $yyUser = $userInfo['user'];
    $yyId = $yyUser['id'] ?? '';
    $yyUsername = $yyUser['username'] ?? '';
    $yyAvatar = $yyUser['avatar'] ?? '';
    if (empty($yyId)) jsonOut(['success' => false, 'error' => '无法获取用户信息'], 400);
    $users = loadUsers();
    $found = null;
    foreach ($users as $u) {
        if (($u['youyun_id'] ?? '') === $yyId) { $found = $u; break; }
    }
    if (!$found) {
        $avatarUrl = '';
        if (!empty($yyAvatar)) {
            $avatarDir = './data/avatars/';
            if (!is_dir($avatarDir)) mkdir($avatarDir, 0755, true);
            $ext = 'png';
            if (strpos($yyAvatar, 'data:image/jpeg') === 0) $ext = 'jpg';
            elseif (strpos($yyAvatar, 'data:image/png') === 0) $ext = 'png';
            elseif (strpos($yyAvatar, 'data:image/webp') === 0) $ext = 'webp';
            $base64Data = preg_replace('/^data:image\/\w+;base64,/', '', $yyAvatar);
            $avatarFile = 'yy_' . $yyId . '.' . $ext;
            $savedPath = $avatarDir . $avatarFile;
            if (file_put_contents($savedPath, base64_decode($base64Data))) {
                $avatarUrl = 'data/avatars/' . $avatarFile;
            }
        }
        $new = [
            'id' => genId(), 'youyun_id' => $yyId, 'qq' => '', 'nickname' => $yyUsername ?: 'Youyun用户',
            'password' => '', 'avatar' => $avatarUrl, 'signature' => '通过 Youyun 登录',
            'role' => 'user', 'created' => date('Y-m-d H:i:s')
        ];
        $users[] = $new;
        saveUsers($users);
        $found = $new;
    } else {
        if (!empty($yyAvatar)) {
            $avatarDir = './data/avatars/';
            if (!is_dir($avatarDir)) mkdir($avatarDir, 0755, true);
            $ext = 'png';
            if (strpos($yyAvatar, 'data:image/jpeg') === 0) $ext = 'jpg';
            elseif (strpos($yyAvatar, 'data:image/png') === 0) $ext = 'png';
            elseif (strpos($yyAvatar, 'data:image/webp') === 0) $ext = 'webp';
            $base64Data = preg_replace('/^data:image\/\w+;base64,/', '', $yyAvatar);
            $avatarFile = 'yy_' . $yyId . '.' . $ext;
            $savedPath = $avatarDir . $avatarFile;
            if (file_put_contents($savedPath, base64_decode($base64Data))) {
                $avatarUrl = 'data/avatars/' . $avatarFile;
                foreach ($users as &$usr) {
                    if (($usr['youyun_id'] ?? '') === $yyId) {
                        $usr['avatar'] = $avatarUrl;
                        break;
                    }
                }
                unset($usr);
                saveUsers($users);
                $found['avatar'] = $avatarUrl;
            }
        }
        if (!empty($yyUsername) && $yyUsername !== ($found['nickname'] ?? '')) {
            foreach ($users as &$usr) {
                if (($usr['youyun_id'] ?? '') === $yyId) { $usr['nickname'] = $yyUsername; break; }
            }
            unset($usr);
            saveUsers($users);
            $found['nickname'] = $yyUsername;
        }
    }
    session_regenerate_id(true);
    $_SESSION['cmt_user'] = [
        'id' => $found['id'], 'qq' => $found['qq'] ?? '', 'nickname' => $found['nickname'],
        'avatar' => $found['avatar'] ?? '', 'signature' => $found['signature'] ?? '',
        'role' => $found['role'] ?? 'user'
    ];
    jsonOut(['success' => true, 'user' => $_SESSION['cmt_user'], 'youyun' => true]);
}
if ($action === 'oauth_login') {
    $siteConfig = loadSiteConfig();
    $clientId = $siteConfig['oauth_client_id'] ?? '';
    $oauthBaseUrl = rtrim($siteConfig['oauth_base_url'] ?? '', '/');
    $authPath = $siteConfig['oauth_auth_path'] ?? '/api/oauth.php?action=authorize';
    if (empty($clientId) || empty($oauthBaseUrl)) {
        jsonOut(['success' => false, 'error' => 'OAuth 未配置'], 400);
    }
    $state = bin2hex(random_bytes(16));
    $_SESSION['oauth_state'] = $state;
    
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '';
    $basePath = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/api.php'), '/');
    $redirectUri = $scheme . '://' . $host . $basePath . '/oauth_callback.php';
    $_SESSION['oauth_redirect'] = $redirectUri;
    $authUrl = $oauthBaseUrl . $authPath
        . '&client_id=' . urlencode($clientId)
        . '&redirect_uri=' . urlencode($redirectUri)
        . '&state=' . urlencode($state);
    jsonOut(['success' => true, 'auth_url' => $authUrl]);
}
if ($action === 'oauth_user_info') {
    $u = validateSession();
    if (!$u) jsonOut(['success' => false, 'error' => '未登录'], 401);
    $users = loadUsers();
    foreach ($users as $usr) {
        if ($usr['id'] === $u['id']) {
            $avatar = '';
            if (!empty($usr['avatar']) && strpos($usr['avatar'], 'data/') === 0 && file_exists('./' . $usr['avatar'])) {
                $avatar = 'data:image/' . pathinfo($usr['avatar'], PATHINFO_EXTENSION) . ';base64,' . base64_encode(file_get_contents('./' . $usr['avatar']));
            }
            jsonOut(['success' => true, 'user' => [
                'id' => $usr['id'], 'nickname' => $usr['nickname'] ?? '',
                'avatar' => $avatar, 'youyun_id' => $usr['youyun_id'] ?? '',
                'role' => $usr['role'] ?? 'user'
            ]]);
        }
    }
    jsonOut(['success' => false, 'error' => '用户不存在'], 404);
}

if ($action === 'send_email_code' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $siteCfg = loadSiteConfig();
    if (empty($siteCfg['email_verify_enabled'])) jsonOut(['success' => false, 'error' => '邮箱验证未开启'], 400);
    if (empty($siteCfg['smtp_host'])) jsonOut(['success' => false, 'error' => '邮件服务未配置'], 500);
    checkProxyBlock('获取验证码');
    $input = json_decode(file_get_contents('php://input'), true);
    $email = trim($input['email'] ?? '');
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) jsonOut(['success' => false, 'error' => '请输入有效邮箱'], 400);
    $clientIP = getClientIP();
    if (isIPBanned($clientIP, 'register')) jsonOut(['success' => false, 'error' => '你的 IP 已被封禁'], 403);
    
    $rateFile = './data/.email_code_rates.json';
    $rates = file_exists($rateFile) ? json_decode(file_get_contents($rateFile), true) : [];
    if (!is_array($rates)) $rates = [];
    $now = time();
    $rates = array_filter($rates, function($r) use ($now) { return ($now - ($r['t'] ?? 0)) < 60; });
    $ipRates = array_filter($rates, function($r) use ($clientIP) { return $r['ip'] === $clientIP; });
    if (count($ipRates) >= 1) jsonOut(['success' => false, 'error' => '请60秒后再试'], 429);
    $rates[] = ['ip' => $clientIP, 't' => $now];
    file_put_contents($rateFile, json_encode(array_values($rates)));
    $result = generateEmailCode($email);
    if ($result['success']) {
        jsonOut(['success' => true, 'message' => '验证码已发送']);
    } else {
        jsonOut(['success' => false, 'error' => $result['error'] ?? '发送失败'], 500);
    }
}

jsonOut(['success' => false, 'error' => '未知操作'], 400);
