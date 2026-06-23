<?php
define('APP_VERSION', '1.2.0');

function getUpdateChannel() {
    $cfg = loadSiteConfig();
    return ($cfg['update_channel'] ?? 'stable') === 'beta' ? 'beta' : 'stable';
}

function getUpdateRepo($channel = null) {
    if ($channel === null) $channel = getUpdateChannel();
    return $channel === 'beta' ? 'Xiao-youyu/You-Markdown-Beta' : 'Xiao-youyu/You-Markdown';
}

function checkGitHubRelease($channel = null) {
    if ($channel === null) $channel = getUpdateChannel();
    $repo = getUpdateRepo($channel);
    $url = "https://api.github.com/repos/{$repo}/releases/latest";
    $cacheFile = __DIR__ . '/data/.update_cache.json';

    if (file_exists($cacheFile)) {
        $cache = json_decode(file_get_contents($cacheFile), true);
        if ($cache && ($cache['channel'] ?? '') === $channel && time() - ($cache['time'] ?? 0) < 300) {
            return $cache['data'] ?? null;
        }
    }

    $opts = [
        'http' => [
            'method' => 'GET',
            'header' => [
                'User-Agent: You-Markdown',
                'Accept: application/vnd.github.v3+json'
            ],
            'timeout' => 10,
            'ignore_errors' => true
        ]
    ];
    $context = stream_context_create($opts);
    $response = @file_get_contents($url, false, $context);
    if ($response === false) return null;

    $data = json_decode($response, true);
    if (!$data || !isset($data['tag_name'])) return null;

    $result = [
        'version' => ltrim($data['tag_name'], 'v'),
        'body' => $data['body'] ?? '',
        'zipball_url' => $data['zipball_url'] ?? '',
        'published_at' => isset($data['published_at']) ? date('Y-m-d H:i', strtotime($data['published_at'])) : ''
    ];

    file_put_contents($cacheFile, json_encode([
        'channel' => $channel,
        'time' => time(),
        'data' => $result
    ], JSON_UNESCAPED_UNICODE));

    return $result;
}

function isNewVersion($remote, $local = APP_VERSION) {
    return version_compare(ltrim($remote, 'v'), ltrim($local, 'v'), '>');
}

function loadSiteConfig() {
    $f = __DIR__ . '/data/.config.json';
    if (!file_exists($f)) return [
        'site_title' => 'You Markdown',
        'reg_limit_per_ip' => 3,
        'comments_enabled' => true,
        'auto_ban' => true,
        'auto_ban_unauthorized' => false,
        'registration_enabled' => true,
        'guest_comments_enabled' => false,
        'max_login_fails' => 10,
        'max_comments_per_minute' => 5,
        'max_registrations_per_ip' => 3,
    ];
    $d = json_decode(file_get_contents($f), true);
    return is_array($d) ? $d : [];
}

function saveSiteConfig($config) {
    file_put_contents(__DIR__ . '/data/.config.json', json_encode($config, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}

function loadBansList() {
    $f = __DIR__ . '/data/.bans.json';
    if (!file_exists($f)) return [];
    $d = json_decode(file_get_contents($f), true);
    return is_array($d) ? $d : [];
}

function saveBansList($bans) {
    file_put_contents(__DIR__ . '/data/.bans.json', json_encode($bans, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}

function addBan($ip, $types, $reason = '') {
    $bans = loadBansList();
    foreach ($bans as &$b) {
        if ($b['ip'] === $ip) {
            foreach ($types as $t) { if (!in_array($t, $b['types'])) $b['types'][] = $t; }
            $b['reason'] = $reason;
            saveBansList($bans);
            return;
        }
    }
    unset($b);
    $bans[] = ['ip' => $ip, 'types' => $types, 'reason' => $reason, 'time' => date('Y-m-d H:i:s')];
    saveBansList($bans);
}

function isIPBanned($ip, $type) {
    $bans = loadBansList();
    foreach ($bans as $b) { if ($b['ip'] === $ip && in_array($type, $b['types'] ?? [])) return true; }
    return false;
}

function getClientIP() {
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) { $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']); return trim($ips[0]); }
    if (!empty($_SERVER['HTTP_X_REAL_IP'])) return $_SERVER['HTTP_X_REAL_IP'];
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

function loadLogsList() {
    $f = __DIR__ . '/data/.logs.json';
    if (!file_exists($f)) return [];
    $d = json_decode(file_get_contents($f), true);
    return is_array($d) ? $d : [];
}

function saveLogsList($logs) {
    file_put_contents(__DIR__ . '/data/.logs.json', json_encode($logs, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}

function logAbnormal($ip, $action) {
    $logs = loadLogsList();
    $logs[] = ['ip' => $ip, 'action' => $action, 'time' => date('Y-m-d H:i:s')];
    if (count($logs) > 500) $logs = array_slice($logs, -500);
    saveLogsList($logs);
}

function logUnauthorized($action, $ban = false) {
    $logFile = __DIR__ . '/data/.unauthorized.json';
    $logs = file_exists($logFile) ? json_decode(file_get_contents($logFile), true) : [];
    if (!is_array($logs)) $logs = [];
    $ip = getClientIP();
    $logs[] = [
        'ip' => $ip,
        'action' => $action,
        'user' => $_SESSION['cmt_user']['nickname'] ?? '未登录',
        'user_id' => $_SESSION['cmt_user']['id'] ?? '',
        'ua' => $_SERVER['HTTP_USER_AGENT'] ?? '',
        'time' => date('Y-m-d H:i:s')
    ];
    if (count($logs) > 1000) $logs = array_slice($logs, -1000);
    file_put_contents($logFile, json_encode($logs, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

    if ($ban) {
        $config = loadSiteConfig();
        if (!empty($config['auto_ban_unauthorized'])) {
            addBan($ip, ['register', 'comment', 'login'], '自动封禁：越权操作 - ' . $action);
            logAbnormal($ip, '自动封禁越权用户: ' . $action);
        }
    }
}
