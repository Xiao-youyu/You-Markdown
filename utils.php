<?php
define('APP_VERSION', '1.4.5');
function genId() { return bin2hex(random_bytes(8)); }

if (!function_exists('jsonOut')) {
    function jsonOut($data, $code = 200) {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }
}

function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}
function validateCSRFToken($token) {
    return !empty($token) && !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function sendBanAlert($ip, $reason) {
    $alertFile = __DIR__ . '/data/.ban_alert_tracker.json';
    $tracker = file_exists($alertFile) ? (json_decode(file_get_contents($alertFile), true) ?: []) : [];
    $now = time();
    foreach ($tracker as $k => $v) { if (($v['time'] ?? 0) < $now - 3600) unset($tracker[$k]); }
    if (count($tracker) >= 3) return;
    $tracker[] = ['time' => $now, 'ip' => $ip];
    file_put_contents($alertFile, json_encode($tracker, JSON_UNESCAPED_UNICODE), LOCK_EX);
    $body = '<div style="height:2px;background:linear-gradient(90deg,transparent,#8b3a5c,transparent);margin:0 0 20px;border-radius:1px;"></div>';
    $body .= '<div style="background:#faf5f7;border-left:4px solid #8b3a5c;border-radius:0 8px 8px 0;padding:16px 20px;margin-bottom:16px;">';
    $body .= '<p style="margin:0 0 12px;font-size:14px;color:#6b2a42;font-weight:600;">已自动封禁以下 IP</p>';
    $body .= '<table width="100%" cellpadding="0" cellspacing="0" style="font-size:13px;">';
    $body .= '<tr><td style="padding:4px 0;color:#999;width:50px;">IP</td><td style="padding:4px 0;color:#1a1a1a;font-weight:600;font-family:Menlo,Consolas,monospace;">' . htmlspecialchars($ip) . '</td></tr>';
    $body .= '<tr><td style="padding:4px 0;color:#999;">原因</td><td style="padding:4px 0;color:#333;">' . htmlspecialchars($reason) . '</td></tr>';
    $body .= '<tr><td style="padding:4px 0;color:#999;">时间</td><td style="padding:4px 0;color:#333;">' . date('Y-m-d H:i:s') . '</td></tr>';
    $body .= '</table></div>';
    $body .= '<p style="margin:0;font-size:12px;color:#999;text-align:center;">已禁止该 IP 登录、注册、评论等全部操作</p>';
    notifyAdmin('安全提醒：IP 已封禁', $body, '', '#8b3a5c');
}

function logSensitiveOp($action, $detail = '') {
    $logFile = __DIR__ . '/data/.sensitive_ops.log';
    $user = $_SESSION['cmt_user'] ?? [];
    $entry = date('Y-m-d H:i:s') . ' | ' . getClientIP() . ' | ' . ($user['nickname'] ?? 'unknown') . ' (' . ($user['id'] ?? '') . ') | ' . $action;
    if ($detail) $entry .= ' | ' . $detail;
    $entry .= "\n";
    file_put_contents($logFile, $entry, FILE_APPEND | LOCK_EX);
    if (file_exists($logFile) && filesize($logFile) > 512000) {
        $lines = file($logFile);
        file_put_contents($logFile, implode('', array_slice($lines, -500)), LOCK_EX);
    }
}

function loadUsers() {
    $f = __DIR__ . '/data/.users.json';
    if (!file_exists($f)) return [];
    $d = json_decode(file_get_contents($f), true);
    return is_array($d) ? $d : [];
}
function saveUsers($users) {
    $userFile = __DIR__ . '/data/.users.json';
    file_put_contents($userFile, json_encode($users, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
}
function validateAdminSession() {
    if (empty($_SESSION['cmt_user'])) return false;
    $users = loadUsers();
    foreach ($users as $u) {
        if ($u['id'] === $_SESSION['cmt_user']['id']) {
            $_SESSION['cmt_user']['role'] = $u['role'] ?? 'user';
            return ($u['role'] ?? '') === 'admin';
        }
    }
    session_unset();
    session_destroy();
    return false;
}
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
    ], JSON_UNESCAPED_UNICODE), LOCK_EX);
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
    return file_put_contents(__DIR__ . '/data/.config.json', json_encode($config, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
}
function loadBansList() {
    $f = __DIR__ . '/data/.bans.json';
    if (!file_exists($f)) return [];
    $d = json_decode(file_get_contents($f), true);
    return is_array($d) ? $d : [];
}
function saveBansList($bans) {
    file_put_contents(__DIR__ . '/data/.bans.json', json_encode($bans, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
}
function addBan($ip, $types, $reason = '') {
    $bans = loadBansList();
    $isNew = true;
    foreach ($bans as &$b) {
        if ($b['ip'] === $ip) {
            foreach ($types as $t) { if (!in_array($t, $b['types'])) $b['types'][] = $t; }
            $b['reason'] = $reason;
            $isNew = false;
            break;
        }
    }
    unset($b);
    if ($isNew) $bans[] = ['ip' => $ip, 'types' => $types, 'reason' => $reason, 'time' => date('Y-m-d H:i:s')];
    saveBansList($bans);
    sendBanAlert($ip, $reason);
}
function isIPBanned($ip, $type) {
    $bans = loadBansList();
    foreach ($bans as $b) { if ($b['ip'] === $ip && in_array($type, $b['types'] ?? [])) return true; }
    return false;
}
function getClientIP() {
    $config = loadSiteConfig();
    
    if (!empty($config['block_proxy'])) {
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
    
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        return trim($ips[0]);
    }
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
    file_put_contents(__DIR__ . '/data/.logs.json', json_encode($logs, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
}
function logAbnormal($ip, $action) {
    $logs = loadLogsList();
    $logs[] = ['ip' => $ip, 'action' => $action, 'time' => date('Y-m-d H:i:s')];
    if (count($logs) > 500) $logs = array_slice($logs, -500);
    saveLogsList($logs);
    
    $body = '<div style="background:#fff3cd;border-left:4px solid #ffc107;border-radius:0 8px 8px 0;padding:16px 20px;">';
    $body .= '<p style="margin:0 0 8px;font-size:14px;color:#856404;font-weight:600;">⚠️ 异常行为检测</p>';
    $body .= '<table width="100%" cellpadding="0" cellspacing="0" style="font-size:13px;">';
    $body .= '<tr><td style="padding:4px 0;color:#999;width:50px;">IP</td><td style="padding:4px 0;color:#1a1a1a;font-weight:600;font-family:Menlo,Consolas,monospace;">' . htmlspecialchars($ip) . '</td></tr>';
    $body .= '<tr><td style="padding:4px 0;color:#999;">行为</td><td style="padding:4px 0;color:#333;">' . htmlspecialchars($action) . '</td></tr>';
    $body .= '<tr><td style="padding:4px 0;color:#999;">时间</td><td style="padding:4px 0;color:#333;">' . date("Y-m-d H:i:s") . '</td></tr>';
    $body .= '</table></div>';
    notifyAdmin("⚠️ 异常行为提醒 - " . $action, $body, "", "#ffc107");
}
function logUnauthorized($action, $ban = false) {
    $logFile = __DIR__ . '/data/.unauthorized.json';
    $logs = file_exists($logFile) ? json_decode(file_get_contents($logFile), true) : [];
    if (!is_array($logs)) $logs = [];
    $ip = getClientIP();
    $user = $_SESSION['cmt_user']['nickname'] ?? '未登录';
    $userId = $_SESSION['cmt_user']['id'] ?? '';
    $logs[] = [
        'ip' => $ip,
        'action' => $action,
        'user' => $user,
        'user_id' => $userId,
        'ua' => $_SERVER['HTTP_USER_AGENT'] ?? '',
        'time' => date('Y-m-d H:i:s')
    ];
    if (count($logs) > 1000) $logs = array_slice($logs, -1000);
    file_put_contents($logFile, json_encode($logs, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
    
    $body = '<div style="background:#f8d7da;border-left:4px solid #dc3545;border-radius:0 8px 8px 0;padding:16px 20px;">';
    $body .= '<p style="margin:0 0 8px;font-size:14px;color:#721c24;font-weight:600;">🚫 越权访问检测</p>';
    $body .= '<table width="100%" cellpadding="0" cellspacing="0" style="font-size:13px;">';
    $body .= '<tr><td style="padding:4px 0;color:#999;width:50px;">IP</td><td style="padding:4px 0;color:#1a1a1a;font-weight:600;font-family:Menlo,Consolas,monospace;">' . htmlspecialchars($ip) . '</td></tr>';
    $body .= '<tr><td style="padding:4px 0;color:#999;">用户</td><td style="padding:4px 0;color:#333;">' . htmlspecialchars($user) . ' (' . htmlspecialchars($userId) . ')</td></tr>';
    $body .= '<tr><td style="padding:4px 0;color:#999;">行为</td><td style="padding:4px 0;color:#333;">' . htmlspecialchars($action) . '</td></tr>';
    $body .= '<tr><td style="padding:4px 0;color:#999;">时间</td><td style="padding:4px 0;color:#333;">' . date("Y-m-d H:i:s") . '</td></tr>';
    $body .= '</table></div>';
    notifyAdmin("🚫 越权访问提醒 - " . $action, $body, "", "#dc3545");
    if ($ban) {
        $config = loadSiteConfig();
        if (!empty($config['auto_ban_unauthorized'])) {
            addBan($ip, ['register', 'comment', 'login'], '自动封禁：越权操作 - ' . $action);
            logAbnormal($ip, '自动封禁越权用户: ' . $action);
        }
    }
}

function logOperation($action, $detail = '') {
    $logFile = __DIR__ . '/data/.operations.log';
    $ip = getClientIP();
    $user = $_SESSION['cmt_user'] ?? [];
    $entry = date('Y-m-d H:i:s') . ' | ' . $ip . ' | ' . ($user['nickname'] ?? 'unknown') . ' (' . ($user['id'] ?? '') . ') | ' . $action;
    if ($detail) $entry .= ' | ' . $detail;
    $entry .= "\n";
    file_put_contents($logFile, $entry, FILE_APPEND | LOCK_EX);
    if (file_exists($logFile) && filesize($logFile) > 512000) {
        $lines = file($logFile);
        file_put_contents($logFile, implode('', array_slice($lines, -500)), LOCK_EX);
    }
}

function checkAdminPath() {
    $config = loadSiteConfig();
    $adminPath = $config['admin_path'] ?? 'youyou';
    if (empty($adminPath)) return;
    $scriptDir = trim(dirname($_SERVER['SCRIPT_NAME']), '/');
    if ($scriptDir !== $adminPath) {
        http_response_code(404);
        echo '<!DOCTYPE html><html><head><title>404</title></head><body><h1>404 Not Found</h1></body></html>';
        exit;
    }
}

function maskQQ($qq) {
    if (empty($qq)) return '';
    $len = mb_strlen($qq, 'UTF-8');
    if ($len <= 5) return $qq;
    return mb_substr($qq, 0, 3, 'UTF-8') . str_repeat('*', $len - 5) . mb_substr($qq, -2, 2, 'UTF-8');
}

function getDatacenterPatterns() {
    return [
        
        'amazonaws','aws','ec2','compute-1','elastic','s3.amazonaws',
        'googleusercontent','googlecloud','bc.google','googlevpn','ggpht',
        'azure','msn','microsoft','bing','msedge',
        'digitalocean','vultr','linode','akamai','fastly','cloudflare',
        'ovh','hetzner','contabo','ionos','strato','netcup',
        'upcloud','ramnode','hosthatch','buyvm','frantech','serverhub',
        'psychz','choopa','constant','leaseweb','serverion','phoenixnap','stackpath',
        'rackspace','softlayer','ibmcloud','oraclecloud','kamatera','liquidweb',
        'hostwinds','a2hosting','inmotion','siteground','bluehost','hostgator',
        'namecheap','godaddy','dreamhost','dreamcompute',
        
        'alibaba','aliyun','alicloud','taobao','tmall','alipay',
        'tencent','tencentcloud','qq.com','weixin','wechat',
        'baidu','bce','volcengine','bytedance','toutiao','douyin',
        'huawei','huaweicloud','hicloud',
        'jdcloud','jd.id','wangsu','cdnunion','chinacache',
        'ksyun','ksyun.com','kingsoft','westcn','ecloud','chinaentercom',
        'ucloud','ucloud.cn','qiniu','qiniucdn','upyun',
        'dun','dun.cn','baishan','baishancloud',
        
        'nordvpn','expressvpn','surfshark','protonvpn','cyberghost','pia',
        'privateinternetaccess','ipvanish','hotspotshield','windscribe',
        'tunnelbear','hidemyass','vyprvpn','strongvpn','purevpn',
        'zenmate','betternet','nvpn','mullvad','ivpn','perfect-privacy',
        'vpnsecure','vpnarea','torvpn','trustzone','vpnunlimited',
        'hide.me','hidemy.name','smartdns','smartproxy','brightdata',
        'luminati','oxylabs','iproyal','geosurf','netnut',
        
        'wireguard','openvpn','shadowsock','v2ray','trojan','vless','xray',
        'clash','ssr','brook','naiveproxy','hysteria','tuic','reality',
        
        'hosting','hosted','server','dedicated','cloud','datacenter',
        'data-center','colo','colocation','noc.','network',
        'proxy','vpn','gateway','relay','exit','node','tor.',
        'broadband','telecom','isp','static','dedicated',
        
        'cdn','edge','cache','proxy','reverse','lb.','loadbalancer',
        'colo','colo crossing','singlehop','multacom','coresite',
        'equinix','level3','cogent','he.net','zayo','pccw','ntt.',
        'ip-','secureserver','kimsufi','soyoustart','ovhcloud','ovh.net',
        'your-server','hetzner-online','contabo-server','netcup-hosting',
        'cloudns','cloudflared','warp','bgp/',
        'sakura','sakuracloud','conoha','gmo','xtom','bandwagonhost','bwh',
        'dmit','tygercon','racknerd','hostinger','aiven','fly.io','render.com',
        'railway.app','herokuapp','vercel','netlify','surge.sh',
    ];
}

function checkReverseDNS($ip){
    $hn=@gethostbyaddr($ip);
    if(empty($hn)||$hn===$ip)return['detected'=>false,'hostname'=>''];
    $patterns=getDatacenterPatterns();
    $lower=strtolower($hn);
    foreach($patterns as $p){
        if(strpos($lower,$p)!==false){
            return['detected'=>true,'hostname'=>$hn,'pattern'=>$p];
        }
    }
    return['detected'=>false,'hostname'=>$hn];
}

function isProxyIP($ip){
    if(empty($ip)||$ip==='127.0.0.1'||$ip==='::1'||$ip==='10.1.0.10'||$ip==='129.204.86.146')return false;
    $cacheFile=__DIR__.'/data/.proxy_cache.json';
    $cache=file_exists($cacheFile)?(json_decode(file_get_contents($cacheFile),true)?:[]):[];
    $now=time();
    foreach($cache as $k=>$v){if(($v['expires']??0)<$now)unset($cache[$k]);}
    if(isset($cache[$ip]))return!empty($cache[$ip]['is_proxy']);
    $isProxy=false;$reason='';
    
    $dns=checkReverseDNS($ip);
    if($dns['detected']){$isProxy=true;$reason='rDNS:'.($dns['pattern']??'');}
    $cache[$ip]=['is_proxy'=>$isProxy,'reason'=>$reason,'expires'=>$now+86400];
    file_put_contents($cacheFile,json_encode($cache,JSON_UNESCAPED_UNICODE), LOCK_EX);
    return $isProxy;
}

function checkScanner() {
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    if (empty($ua)) return;
    $scanners = [
        'nmap','sqlmap','nikto','masscan','zgrab','nuclei','dirsearch','gobuster',
        'ffuf','wfuzz','hydra','medusa','burp','owasp','acunetix','nessus',
        'openvas','qualys','w3af','skipfish','arachni','appscan','webscarab',
        'paros','vega','grabber','httrack','cutter','whatweb','wpscan',
        'joomscan','droopescan','cmseek','xsstrike','dalfox','subfinder',
        'amass','httpx','kiterunner','jaeles','enum4linux','smbclient',
        'responder','impacket','crackmapexec','evil-winrm','certutil',
        'python-requests','go-http-client','libwww-perl','scanner','spider',
        'crawler','harvester','zmeu','dirbuster','metasploit','msfconsole',
        'nessus','openvas','qualys','acunetix','appscan','netsparker',
        'wpscan','joomscan','droopescan','cmseek','xsstrike','dalfox',
    ];
    $lower = strtolower($ua);
    foreach ($scanners as $s) {
        if (strpos($lower, $s) !== false) {
            $ip = getClientIP();
            logAbnormal($ip, '扫描工具检测: ' . $ua);
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => '访问被拒绝'], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }
}

function checkProxyBlock($actionName = '') {
    $config = loadSiteConfig();
    if (empty($config['block_proxy'])) return;
    $ip = getClientIP();
    if (!isProxyIP($ip)) return;
    logAbnormal($ip, '代理/VPN IP 尝试' . ($actionName ?: '操作'));
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => '检测到你正在使用代理或 VPN，已禁止操作'], JSON_UNESCAPED_UNICODE);
    exit;
}

function generateEmailCode($email) {
    $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $codeFile = __DIR__ . '/data/.email_codes.json';
    $codes = file_exists($codeFile) ? json_decode(file_get_contents($codeFile), true) : [];
    if (!is_array($codes)) $codes = [];
    $codes[$email] = [
        'code' => $code,
        'time' => time(),
        'expires' => time() + 600 
    ];
    file_put_contents($codeFile, json_encode($codes, JSON_UNESCAPED_UNICODE), LOCK_EX);
    
    
    $config = loadSiteConfig();
    $subject = '邮箱验证码 - ' . ($config['site_title'] ?? 'You Markdown');
    $body = '<div style="height:2px;background:linear-gradient(90deg,transparent,#d4943a,transparent);margin:0 0 20px;border-radius:1px;"></div>';
    $body .= '<div style="background:#fdf6ee;border:2px solid #f5dfc5;border-radius:12px;padding:24px 0;text-align:center;margin:0 0 20px;"><span style="font-size:42px;font-weight:800;letter-spacing:12px;color:#c27d3a;font-family:Menlo,Consolas,monospace;">' . $code . '</span></div>';
    $body .= '<p style="margin:0;text-align:center;color:#888;font-size:13px;">验证码 <b style="color:#555;">10 分钟</b>内有效</p>';
    $body .= '<p style="margin:8px 0 0;text-align:center;color:#b0a89a;font-size:12px;">如非本人操作，请忽略此邮件</p>';
    $html = buildEmailHTML('验证码', $body, '', '#d4943a');
    
    $result = sendEmail($email, $subject, $html);
    return $result;
}

function verifyEmailCode($email, $code) {
    $codeFile = __DIR__ . '/data/.email_codes.json';
    if (!file_exists($codeFile)) {
        return ['success' => false, 'error' => '验证码不存在'];
    }
    $codes = json_decode(file_get_contents($codeFile), true);
    if (!is_array($codes) || !isset($codes[$email])) {
        return ['success' => false, 'error' => '验证码不存在'];
    }
    $record = $codes[$email];
    if (time() > ($record['expires'] ?? 0)) {
        unset($codes[$email]);
        file_put_contents($codeFile, json_encode($codes, JSON_UNESCAPED_UNICODE), LOCK_EX);
        return ['success' => false, 'error' => '验证码已过期'];
    }
    if ($record['code'] !== $code) {
        return ['success' => false, 'error' => '验证码错误'];
    }
    
    unset($codes[$email]);
    file_put_contents($codeFile, json_encode($codes, JSON_UNESCAPED_UNICODE), LOCK_EX);
    return ['success' => true];
}

function buildEmailHTML($title, $bodyHTML, $footerText = '', $accentColor = '#d4943a') {
    $config = loadSiteConfig();
    $siteTitle = $config['site_title'] ?? 'You Markdown';
    $footer = $footerText ?: "这是一封系统自动发送的邮件";
    $year = date('Y');
    
    $gradients = [
        '#d4943a' => 'linear-gradient(135deg,#e8875a 0%,#d4943a 100%)',   
        '#8b3a5c' => 'linear-gradient(135deg,#2d2024 0%,#8b3a5c 100%)',   
        '#4ecdc4' => 'linear-gradient(135deg,#1a535c 0%,#4ecdc4 100%)',   
        '#7fb069' => 'linear-gradient(135deg,#3d6b4f 0%,#7fb069 100%)',   
    ];
    $gradient = $gradients[$accentColor] ?? reset($gradients);

    return '<!DOCTYPE html>
<html lang="zh-CN"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"></head>
<body style="margin:0;padding:0;background:#eef0f4;font-family:-apple-system,BlinkMacSystemFont,&quot;Helvetica Neue&quot;,&quot;PingFang SC&quot;,&quot;Microsoft YaHei&quot;,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" role="presentation"><tr><td style="padding:20px 12px;" align="center">
<table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="max-width:520px;">

  <!-- Header -->
  <tr><td style="background:' . $gradient . ';border-radius:16px 16px 0 0;padding:32px 36px 28px;">
    <table width="100%" cellpadding="0" cellspacing="0" role="presentation">
      <tr>
        <td style="font-size:13px;font-weight:600;color:rgba(255,255,255,.85);letter-spacing:1px;">' . strtoupper($siteTitle) . '</td>
        <td align="right"><span style="display:inline-block;background:rgba(255,255,255,.2);color:#fff;font-size:11px;font-weight:600;padding:3px 10px;border-radius:20px;letter-spacing:.5px;">SYSTEM</span></td>
      </tr>
    </table>
    <h1 style="margin:20px 0 0;font-size:24px;font-weight:700;color:#fff;line-height:1.3;">' . $title . '</h1>
  </td></tr>

  <!-- Body -->
  <tr><td style="background:#fff;padding:28px 36px 32px;border-radius:0 0 16px 16px;box-shadow:0 2px 12px rgba(0,0,0,.06);">
    ' . $bodyHTML . '
  </td></tr>

  <!-- Footer -->
  <tr><td style="padding:20px 8px;text-align:center;">
    <p style="margin:0 0 4px;color:#aaa;font-size:11px;">' . $footer . '</p>
    <p style="margin:0;color:#c0c0c0;font-size:10px;">© ' . $year . ' ' . $siteTitle . '</p>
  </td></tr>

</table>
</td></tr></table>
</body></html>';
}

function notifyAdmin($subject, $bodyHTML, $footerText = '', $accentColor = '#d4943a') {
    $config = loadSiteConfig();
    if (empty($config['email_notify_enabled'])) return;
    $to = $config['email_notify_to'] ?? '';
    if (empty($to)) $to = $config['smtp_from_addr'] ?? $config['smtp_user'] ?? '';
    if (empty($to)) return;
    $html = buildEmailHTML($subject, $bodyHTML, $footerText, $accentColor);
    $queueFile = __DIR__ . '/data/.mail_queue.json';
    $queue = file_exists($queueFile) ? (json_decode(file_get_contents($queueFile), true) ?: []) : [];
    $queue[] = ['to' => $to, 'subject' => $subject, 'html' => $html, 'time' => date('Y-m-d H:i:s')];
    if (count($queue) > 50) $queue = array_slice($queue, -50);
    file_put_contents($queueFile, json_encode($queue, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
}

function sendEmail($to, $subject, $htmlBody) {
    $config = loadSiteConfig();
    $host = $config['smtp_host'] ?? '';
    $port = $config['smtp_port'] ?? 465;
    $user = $config['smtp_user'] ?? '';
    $pass = $config['smtp_pass'] ?? '';
    $fromName = $config['smtp_from_name'] ?? 'You Markdown';
    $fromAddr = $config['smtp_from_addr'] ?? $user;
    $encryption = $config['smtp_encryption'] ?? 'ssl';
    
    if (empty($host) || empty($user) || empty($pass)) {
        return ['success' => false, 'error' => 'SMTP 配置不完整'];
    }
    
    
    
    $headers = [
        'MIME-Version: 1.0',
        'Content-type: text/html; charset=UTF-8',
        'From: ' . $fromName . ' <' . $fromAddr . '>',
        'To: ' . $to,
        'Subject: ' . $subject,
        'X-Mailer: PHP/' . phpversion()
    ];
    
    
    $protocol = ($encryption === 'ssl') ? 'ssl://' : 'tcp://';
    $socket = @fsockopen($protocol . $host, $port, $errno, $errstr, 10);
    
    if (!$socket) {
        
        $success = @mail($to, $subject, $htmlBody, implode("\r\n", $headers));
        if ($success) {
            return ['success' => true];
        }
        return ['success' => false, 'error' => '邮件发送失败: ' . $errstr];
    }
    
    
    $read = function() use ($socket) {
        $data = '';
        while ($line = fgets($socket, 515)) {
            $data .= $line;
            if (substr($line, 3, 1) === ' ') break;
        }
        return $data;
    };
    
    $send = function($cmd) use ($socket) {
        fputs($socket, $cmd . "\r\n");
    };
    
    $read(); 
    $send('EHLO ' . $host);
    $read(); 
    if ($encryption === 'tls') {
        $send('STARTTLS');
        $read();
        stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
        $send('EHLO ' . $host);
        $read();
    }
    $send('AUTH LOGIN');
    $read(); 
    $send(base64_encode($user));
    $read(); 
    $send(base64_encode($pass));
    $resp = $read(); 
    if (strpos($resp, '235') !== 0) {
        fclose($socket);
        return ['success' => false, 'error' => 'SMTP 认证失败'];
    }
    $send('MAIL FROM:<' . $fromAddr . '>');
    $read(); 
    $send('RCPT TO:<' . $to . '>');
    $read(); 
    $send('DATA');
    $read(); 
    $msg = "To: $to\r\nFrom: $fromName <$fromAddr>\r\nSubject: $subject\r\n" . implode("\r\n", $headers) . "\r\n\r\n" . $htmlBody . "\r\n.";
    $send($msg);
    $read(); 
    $send('QUIT');
    $read();
    fclose($socket);
    
    return ['success' => true];
}

