<?php
define('SECURITY_VERSION', '3.0.0');

function getCoreHashes() {
    return [
        'utils.php' => ['file' => __DIR__ . '/utils.php', 'tail_check' => true],
        'api.php' => ['file' => __DIR__ . '/api.php', 'tail_check' => true],
        'music.php' => ['file' => __DIR__ . '/music.php', 'tail_check' => true],
    ];
}

function checkFileIntegrity($filepath) {
    if (!file_exists($filepath)) return ['ok' => true];
    $content = file_get_contents($filepath);
    if ($content === false) return ['ok' => false, 'reason' => 'unreadable'];
    $malwarePatterns = [
        'eval($_POST', 'eval($_GET', 'eval($_REQUEST', 'eval($code)',
        'eval(base64_decode', 'eval(gzinflate', 'eval(gzuncompress',
        'assert($_POST', 'assert($_GET',
        'system($_GET', 'system($_POST', 'system($_REQUEST',
        'exec($_GET', 'exec($_POST', 'exec($_REQUEST',
        'passthru($_', 'shell_exec($_', 'popen($_', 'proc_open($_',
        'base64_decode($_POST', 'base64_decode($_GET', 'base64_decode($_REQUEST',
        'str_rot13($_', 'gzinflate(base64_decode',
        'YMCORE', 'YM-CORE', 'YmCore', 'yshell', 'YunShell', 'c99shell', 'r57shell',
        '/* self-heal', '/* YMCORE', '/*YMA-SENT',
        'file_put_contents($_POST', 'file_put_contents($_GET',
        'unserialize($_POST', 'unserialize($_GET',
        'password:@eval', 'preg_replace(.*/e',
    ];
    foreach ($malwarePatterns as $pattern) {
        if (stripos($content, $pattern) !== false) {
            return ['ok' => false, 'reason' => 'malware_detected', 'pattern' => $pattern];
        }
    }
    return ['ok' => true];
}

function autoRepairFile($filepath) {
    $content = file_get_contents($filepath);
    if ($content === false) return false;
    $markers = ['/* YMCORE-BOOT */', '/* YMCORE-SEED */', '/*YMA-SENT*/', '/* YM-CORE', '/* self-heal footer'];
    $modified = false;
    foreach ($markers as $marker) {
        $pos = strpos($content, $marker);
        if ($pos !== false) { $content = substr($content, 0, $pos); $modified = true; }
    }
    if ($modified) { file_put_contents($filepath, rtrim($content) . "\n", LOCK_EX); return true; }
    return false;
}

function logSecurityEvent($event, $details = '') {
    $logFile = __DIR__ . '/data/.security.log';
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $entry = date('Y-m-d H:i:s') . ' | ' . $ip . ' | ' . $event;
    if ($details) $entry .= ' | ' . $details;
    $entry .= "\n";
    file_put_contents($logFile, $entry, FILE_APPEND | LOCK_EX);
    if (file_exists($logFile) && filesize($logFile) > 512000) {
        $lines = file($logFile);
        file_put_contents($logFile, implode('', array_slice($lines, -500)), LOCK_EX);
    }
    if (in_array($event, ['SCAN_DETECTED', 'AUTO_BAN_SCAN', 'ATTACK_BLOCKED', 'INFECTION_DETECTED'])) {
        require_once __DIR__ . '/utils.php';
        $emoji = $event === 'AUTO_BAN_SCAN' ? '🔨' : ($event === 'ATTACK_BLOCKED' ? '🛡️' : ($event === 'INFECTION_DETECTED' ? '🦠' : '🔍'));
        $color = $event === 'AUTO_BAN_SCAN' ? '#dc3545' : '#ff6b35';
        $body = '<div style="background:#fff3cd;border-left:4px solid ' . $color . ';border-radius:0 8px 8px 0;padding:16px 20px;">';
        $body .= '<p style="margin:0 0 8px;font-size:14px;color:#856404;font-weight:600;">' . $emoji . ' 安全事件: ' . htmlspecialchars($event) . '</p>';
        $body .= '<table width="100%" cellpadding="0" cellspacing="0" style="font-size:13px;">';
        $body .= '<tr><td style="padding:4px 0;color:#999;width:50px;">IP</td><td style="padding:4px 0;color:#1a1a1a;font-weight:600;font-family:Menlo,Consolas,monospace;">' . htmlspecialchars($ip) . '</td></tr>';
        if ($details) $body .= '<tr><td style="padding:4px 0;color:#999;">详情</td><td style="padding:4px 0;color:#333;">' . htmlspecialchars($details) . '</td></tr>';
        $body .= '<tr><td style="padding:4px 0;color:#999;">时间</td><td style="padding:4px 0;color:#333;">' . date("Y-m-d H:i:s") . '</td></tr>';
        $body .= '</table></div>';
        notifyAdmin($emoji . ' 安全事件 - ' . $event, $body, '', $color);
    }
}

function runSecurityCheck() {
    $hashes = getCoreHashes();
    foreach ($hashes as $name => $info) {
        $result = checkFileIntegrity($info['file']);
        if (!$result['ok']) {
            logSecurityEvent('INFECTION_DETECTED', $name . ': ' . ($result['pattern'] ?? $result['reason']));
            autoRepairFile($info['file']);
        }
    }
    $requestUri = $_SERVER['REQUEST_URI'] ?? '';
    $queryString = $_SERVER['QUERY_STRING'] ?? '';
    $postData = @file_get_contents('php://input');
    $combined = $requestUri . '&' . $queryString . '&' . $postData;
    $attackPatterns = ['eval(', 'system(', 'exec(', 'passthru', 'shell_exec', 'popen(', '../', '..\\', '%2e%2e', "' OR ", 'UNION SELECT', 'DROP TABLE'];
    foreach ($attackPatterns as $p) {
        if (stripos($combined, $p) !== false) {
            logSecurityEvent('ATTACK_BLOCKED', $p . ' in ' . substr($requestUri, 0, 100));
            http_response_code(403);
            exit('Forbidden');
        }
    }
}

function checkIPBan() {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    if (empty($ip)) return;
    $banFile = __DIR__ . '/data/.bans.json';
    if (!file_exists($banFile)) return;
    $bans = json_decode(file_get_contents($banFile), true);
    if (!is_array($bans)) return;
    foreach ($bans as $b) {
        if ($b['ip'] === $ip && in_array('scan', $b['types'] ?? [])) {
            http_response_code(403);
            echo json_encode(['error' => 'Forbidden']);
            exit;
        }
    }
}

function antiScan() {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    if (empty($ip)) return;
    $scanFile = __DIR__ . '/data/.scan_tracker.json';
    $maxHits = 15;
    $window = 300;
    $tracker = file_exists($scanFile) ? (json_decode(file_get_contents($scanFile), true) ?: []) : [];
    $now = time();
    foreach ($tracker as $k => $v) { if (($v['expires'] ?? 0) < $now) unset($tracker[$k]); }
    $key = 'scan_' . $ip;
    $uri = strtolower($_SERVER['REQUEST_URI'] ?? '');
    $ua = strtolower($_SERVER['HTTP_USER_AGENT'] ?? '');
    $isScan = false;
    $reason = '';

    $scanPaths = ['/wp-admin','/wp-login','/wp-content','/phpmyadmin','/pma','/adminer',
        '/.env','/.git','/.svn','/config.php','/config.inc','/configuration.php',
        '/backup','/bak','/old','/shell','/cmd','/webshell','/c99','/r57',
        '/upload','/xmlrpc.php','/actuator','/swagger','/api-docs',
        '/jenkins','/console','/debug','/manager/html','/cgi-bin',
        '/vendor/','/node_modules/','/database','/db_backup','/dump',
        '/install','/setup','/test.php','/info.php','/phpinfo',
        '/.htaccess','/.htpasswd','/crossdomain.xml','/fdcpay','/mstsc'];
    foreach ($scanPaths as $sp) {
        if (strpos($uri, $sp) !== false) { $isScan = true; $reason = 'path:' . $sp; break; }
    }

    if (!$isScan) {
        $badUAs = ['nikto','sqlmap','nmap','masscan','zgrab','goby','awvs','acunetix',
            'nessus','openvas','burpsuite','dirbuster','gobuster','ffuf','wfuzz','hydra',
            'metasploit','havij','w3af','arachni','skipfish','whatweb','appscan',
            'python-requests','python-urllib','libwww-perl'];
        foreach ($badUAs as $b) {
            if (strpos($ua, $b) !== false) { $isScan = true; $reason = 'ua:' . $b; break; }
        }
    }

    if (!$isScan) {
        $ext = strtolower(pathinfo(parse_url($uri, PHP_URL_PATH), PATHINFO_EXTENSION));
        if (in_array($ext, ['bak','sql','tar','gz','zip','rar','old','tmp','swp'])) {
            $isScan = true; $reason = 'ext:.' . $ext;
        }
    }

    if (!$isScan) return;

    if (!isset($tracker[$key])) {
        $tracker[$key] = ['count' => 0, 'expires' => $now + $window, 'reasons' => []];
    }
    $tracker[$key]['count']++;
    $tracker[$key]['reasons'][] = $reason;
    if (count($tracker[$key]['reasons']) > 10) $tracker[$key]['reasons'] = array_slice($tracker[$key]['reasons'], -10);
    logSecurityEvent('SCAN_DETECTED', $reason . ' (' . $tracker[$key]['count'] . ')');

    if ($tracker[$key]['count'] >= $maxHits) {
        $banFile = __DIR__ . '/data/.bans.json';
        $bans = file_exists($banFile) ? json_decode(file_get_contents($banFile), true) : [];
        if (!is_array($bans)) $bans = [];
        $found = false;
        foreach ($bans as &$b) {
            if ($b['ip'] === $ip) {
                foreach (['scan','register','comment','login'] as $t) { if (!in_array($t, $b['types'])) $b['types'][] = $t; }
                $b['reason'] = 'Auto-ban: file scanning';
                $found = true; break;
            }
        }
        unset($b);
        if (!$found) $bans[] = ['ip'=>$ip, 'types'=>['scan','register','comment','login'], 'reason'=>'Auto-ban: file scanning', 'time'=>date('Y-m-d H:i:s')];
        file_put_contents($banFile, json_encode($bans, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
        logSecurityEvent('AUTO_BAN_SCAN', $ip);
        unset($tracker[$key]);
        http_response_code(403);
        echo json_encode(['error' => 'Forbidden']);
        exit;
    }
    file_put_contents($scanFile, json_encode($tracker, JSON_UNESCAPED_UNICODE), LOCK_EX);
}

checkIPBan();
antiScan();

runSecurityCheck();
