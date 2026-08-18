<?php
// Honeypot - any access = instant ban
require_once __DIR__ . '/../security_check.php';
require_once __DIR__ . '/../utils.php';
$ip = getClientIP();

logSecurityEvent('HONEYPOT_HIT', $ip . ' | ' . ($_SERVER['REQUEST_URI'] ?? '') . ' | ' . ($_SERVER['HTTP_USER_AGENT'] ?? ''));

$bans = loadBansList();
$found = false;
foreach ($bans as &$b) {
    if ($b['ip'] === $ip) {
        foreach (['scan','login','register','comment'] as $t) {
            if (!in_array($t, $b['types'])) $b['types'][] = $t;
        }
        $b['reason'] = '蜜罐触发: ' . ($_SERVER['REQUEST_URI'] ?? 'unknown');
        $found = true;
        break;
    }
}
unset($b);
if (!$found) {
    $bans[] = [
        'ip' => $ip,
        'types' => ['scan','login','register','comment'],
        'reason' => '蜜罐触发: ' . ($_SERVER['REQUEST_URI'] ?? 'unknown'),
        'time' => date('Y-m-d H:i:s')
    ];
}
saveBansList($bans);
logAbnormal($ip, '蜜罐触发，已封禁: ' . ($_SERVER['REQUEST_URI'] ?? ''));

http_response_code(404);
echo '<!DOCTYPE html><html><head><title>404 Not Found</title></head><body><h1>404 Not Found</h1></body></html>';
