<?php
session_start();
require_once __DIR__ . '/utils.php';

$code = $_GET['code'] ?? '';
$state = $_GET['state'] ?? '';

if (empty($code)) {
        header('Location: ./?oauth_error=no_code');
    exit;
}
if (empty($_SESSION['oauth_state']) || !hash_equals($_SESSION['oauth_state'], $state)) {
        header('Location: ./?oauth_error=invalid_state');
    exit;
}
unset($_SESSION['oauth_state']);

$siteConfig = loadSiteConfig();
$clientId = $siteConfig['oauth_client_id'] ?? '';
$clientSecret = $siteConfig['oauth_client_secret'] ?? '';
$oauthBaseUrl = rtrim($siteConfig['oauth_base_url'] ?? '', '/');
$authPath = $siteConfig['oauth_auth_path'] ?? '/api/oauth.php?action=authorize';
$tokenPath = $siteConfig['oauth_token_path'] ?? '/api/oauth.php?action=token';
$verifyPath = $siteConfig['oauth_verify_path'] ?? '/api/oauth.php?action=verify';

if (empty($clientId) || empty($clientSecret) || empty($oauthBaseUrl)) {
        header('Location: ./?oauth_error=config_missing');
    exit;
}

$redirectUri = $_SESSION['oauth_redirect'] ?? '';
unset($_SESSION['oauth_redirect']);
if (empty($redirectUri)) {
    
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '';
    $basePath = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/oauth_callback.php'), '/');
    $redirectUri = $scheme . '://' . $host . $basePath . '/oauth_callback.php';
}

function yyHttp($url, $method = 'GET', $postData = [], $format = 'form', $bearerToken = '') {
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        $headers = ['Accept: application/json'];
        if (!empty($bearerToken)) $headers[] = 'Authorization: Bearer ' . $bearerToken;
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_FOLLOWLOCATION => false,
        ]);
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($format === 'json') {
                $body = json_encode($postData);
                $headers[] = 'Content-Type: application/json';
                curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            } else {
                curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
            }
        } else {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }
        $resp = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);
        if ($resp === false) return ['error' => 'cURL: ' . $err];
        return ['data' => $resp];
    }
    
    $headerLines = [];
    if (!empty($bearerToken)) $headerLines[] = 'Authorization: Bearer ' . $bearerToken;
    $opts = ['timeout' => 15, 'ignore_errors' => true];
    if ($method === 'POST') {
        $opts['method'] = 'POST';
        if ($format === 'json') {
            $headerLines[] = 'Content-Type: application/json';
            $opts['content'] = json_encode($postData);
        } else {
            $headerLines[] = 'Content-Type: application/x-www-form-urlencoded';
            $opts['content'] = http_build_query($postData);
        }
    } else {
        $opts['method'] = 'GET';
    }
    if (!empty($headerLines)) $opts['header'] = implode("\r\n", $headerLines);
    $ctx = stream_context_create([
        'http' => $opts,
        'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
    ]);
    $resp = @file_get_contents($url, false, $ctx);
    if ($resp === false) return ['error' => 'HTTP request failed'];
    return ['data' => $resp];
}

$tokenUrl = $oauthBaseUrl . $tokenPath;
$tokenPostData = [
    'code' => $code,
    'client_id' => $clientId,
    'client_secret' => $clientSecret,
    'redirect_uri' => $redirectUri,
];

$tokenResult = yyHttp($tokenUrl, 'POST', $tokenPostData);
if (!empty($tokenResult['error'])) {
    error_log('Youyun OAuth token error: ' . $tokenResult['error']);
        header('Location: ./?oauth_error=token_failed');
    exit;
}

$tokenData = json_decode($tokenResult['data'], true);
if (!$tokenData) {
    error_log('Youyun OAuth token JSON decode failed, raw: ' . substr($tokenResult['data'], 0, 500));
        header('Location: ./?oauth_error=token_invalid');
    exit;
}
if (empty($tokenData['access_token'])) {
    $errMsg = $tokenData['error'] ?? $tokenData['error_description'] ?? 'token_invalid';
    error_log('Youyun OAuth no access_token, response: ' . json_encode($tokenData));
    header('Location: ./?oauth_error=' . urlencode($errMsg));
    exit;
}

$verifyUrl = $oauthBaseUrl . $verifyPath;
$verifyPostData = [
    'access_token' => $tokenData['access_token'],
    'client_secret' => $clientSecret,
];

$verifyResult = yyHttp($verifyUrl, 'POST', $verifyPostData, 'json');
if (!empty($verifyResult['error'])) {
    error_log('Youyun OAuth verify error: ' . $verifyResult['error']);
        header('Location: ./?oauth_error=verify_failed');
    exit;
}

$userInfo = json_decode($verifyResult['data'], true);
if (empty($userInfo['valid']) || empty($userInfo['user'])) {
    error_log('Youyun OAuth verify invalid: ' . substr($verifyResult['data'], 0, 300));
        header('Location: ./?oauth_error=verify_invalid');
    exit;
}

$yyUser = $userInfo['user'];
$yyId = $yyUser['id'] ?? '';
$yyUsername = $yyUser['username'] ?? '';
$yyAvatar = $yyUser['avatar'] ?? '';

if (empty($yyId)) {
        header('Location: ./?oauth_error=no_user');
    exit;
}

$users = loadUsers();
$found = null;

foreach ($users as $u) {
    if (($u['youyun_id'] ?? '') === $yyId) {
        $found = $u;
        break;
    }
}

function saveYouyunAvatar($yyAvatar, $yyId) {
    if (empty($yyAvatar) || strpos($yyAvatar, 'data:image') !== 0) return '';
    $avatarDir = __DIR__ . '/data/avatars/';
    if (!is_dir($avatarDir)) @mkdir($avatarDir, 0755, true);
    $ext = 'png';
    if (strpos($yyAvatar, 'data:image/jpeg') === 0 || strpos($yyAvatar, 'data:image/jpg') === 0) $ext = 'jpg';
    elseif (strpos($yyAvatar, 'data:image/webp') === 0) $ext = 'webp';
    $base64Data = preg_replace('/^data:image\/\w+;base64,/', '', $yyAvatar);
    $decoded = base64_decode($base64Data);
    if ($decoded === false || strlen($decoded) < 10) return '';
    $avatarFile = 'yy_' . preg_replace('/[^a-zA-Z0-9]/', '', $yyId) . '.' . $ext;
    if (file_put_contents($avatarDir . $avatarFile, $decoded)) {
        return 'data/avatars/' . $avatarFile;
    }
    return '';
}

if (!$found) {
    $avatarUrl = saveYouyunAvatar($yyAvatar, $yyId);
    $new = [
        'id' => genId(), 'youyun_id' => $yyId, 'qq' => '',
        'nickname' => $yyUsername ?: 'Youyun用户',
        'password' => '', 'avatar' => $avatarUrl,
        'signature' => '通过 Youyun 登录',
        'role' => 'user', 'created' => date('Y-m-d H:i:s')
    ];
    $users[] = $new;
    saveUsers($users);
    $found = $new;
} else {
    
    $avatarUrl = saveYouyunAvatar($yyAvatar, $yyId);
    if ($avatarUrl) {
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
    
    if (!empty($yyUsername) && $yyUsername !== ($found['nickname'] ?? '')) {
        foreach ($users as &$usr) {
            if (($usr['youyun_id'] ?? '') === $yyId) {
                $usr['nickname'] = $yyUsername;
                break;
            }
        }
        unset($usr);
        saveUsers($users);
        $found['nickname'] = $yyUsername;
    }
}

session_regenerate_id(true);
$_SESSION['cmt_user'] = [
    'id' => $found['id'], 'qq' => $found['qq'] ?? '',
    'nickname' => $found['nickname'],
    'avatar' => $found['avatar'] ?? '',
    'signature' => $found['signature'] ?? '',
    'role' => $found['role'] ?? 'user'
];

require_once __DIR__ . '/utils.php';
$loginTime = date('Y-m-d H:i:s');
$loginIP = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$loginUA = $_SERVER['HTTP_USER_AGENT'] ?? '未知设备';
$roleLabel = ($found['role'] ?? '') === 'admin' ? '管理员' : '用户';
$body = '<div style="height:2px;background:linear-gradient(90deg,transparent,#4ecdc4,transparent);margin:0 0 20px;border-radius:1px;"></div>';
$body .= '<div style="background:#f0f7f6;border-left:4px solid #4ecdc4;border-radius:0 8px 8px 0;padding:16px 20px;margin-bottom:16px;">';
$body .= '<table width="100%" cellpadding="0" cellspacing="0" style="font-size:13px;">';
$body .= '<tr><td style="padding:5px 0;color:#999;width:50px;">账号</td><td style="padding:5px 0;color:#1a1a1a;font-weight:600;">' . htmlspecialchars($found['nickname'] ?? '') . ' <span style="color:#999;font-weight:400;">(' . $roleLabel . ' · Youyun OAuth)</span></td></tr>';
$body .= '<tr><td style="padding:5px 0;color:#999;">时间</td><td style="padding:5px 0;color:#333;">' . $loginTime . '</td></tr>';
$body .= '<tr><td style="padding:5px 0;color:#999;">IP</td><td style="padding:5px 0;color:#333;font-family:Menlo,Consolas,monospace;">' . $loginIP . '</td></tr>';
$body .= '<tr><td style="padding:5px 0;color:#999;">设备</td><td style="padding:5px 0;color:#666;font-size:12px;">' . htmlspecialchars(mb_substr($loginUA, 0, 50, 'UTF-8')) . '</td></tr>';
$body .= '</table></div>';
notifyAdmin('登录通知 - ' . ($found['nickname'] ?? ''), $body, '', '#4ecdc4');

    header('Location: ./?oauth_success=1');
exit;
