<?php
session_start();
require_once __DIR__ . '/../utils.php';
if (!validateAdminSession()) {
    logUnauthorized('越权尝试访问AI写作');
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

$aiModel = $_siteConfig['ai_model'] ?? '';
$aiEndpoint = $_siteConfig['ai_endpoint'] ?? '';
$aiApiKey = $_siteConfig['ai_api_key'] ?? '';
$aiMaxTokens = intval($_siteConfig['ai_max_tokens'] ?? 8192);
$aiTemperature = floatval($_siteConfig['ai_temperature'] ?? 0.7);
$aiEnabled = !empty($_siteConfig['ai_writing_enabled']);

// Save config
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_config') {
    $token = $_POST['csrf_token'] ?? '';
    if (!validateCSRFToken($token)) { header('Location: ai_write.php'); exit; }
    $_siteConfig['ai_writing_enabled'] = !empty($_POST['ai_enabled']);
    $_siteConfig['ai_model'] = trim($_POST['ai_model'] ?? '');
    $_siteConfig['ai_endpoint'] = trim($_POST['ai_endpoint'] ?? '');
    $_siteConfig['ai_max_tokens'] = max(1024, min(32768, intval($_POST['ai_max_tokens'] ?? 8192)));
    $_siteConfig['ai_temperature'] = max(0, min(2, floatval($_POST['ai_temperature'] ?? 0.7)));
    $newKey = trim($_POST['ai_api_key'] ?? '');
    if ($newKey !== '' && $newKey !== '••••••••') $_siteConfig['ai_api_key'] = $newKey;
    file_put_contents($_configFile, json_encode($_siteConfig, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
    logOperation('更新AI写作配置', '模型: ' . $_siteConfig['ai_model']);
    header('Location: ai_write.php?saved=1');
    exit;
}

function checkMaliciousContent($content) {
    $dangerous = ['<?php','<?=','eval(','system(','exec(','passthru(','shell_exec(','popen(','proc_open(','assert(','preg_replace(.*/e','file_put_contents($_','file_get_contents($_','unserialize($_','base64_decode($_','$_GET[','$_POST[','$_REQUEST[','$_SERVER[','$_FILES[','curl_exec','fsockopen','chmod(','chown(','unlink($_','symlink(','rename(','javascript:','onerror=','onload=','document.cookie','document.write','window.location','cmd.exe','/bin/bash','/bin/sh','powershell','wget ','curl -','ssh ','scp '];
    $lower = strtolower($content);
    foreach ($dangerous as $p) { if (stripos($lower, strtolower($p)) !== false) return '检测到危险内容: ' . $p; }
    return false;
}

$msg = '';
$msgType = 'success';
if (isset($_GET['saved'])) $msg = '配置已保存';

// Stream generate (SSE)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['stream'])) {
    header('Content-Type: text/event-stream');
    header('Cache-Control: no-cache');
    header('X-Accel-Buffering: no');
    $input = json_decode(file_get_contents('php://input'), true);
    $prompt = trim($input['prompt'] ?? '');
    if (!$aiEnabled) { echo "data: " . json_encode(['error' => 'AI 写作功能未开启']) . "\n\n"; exit; }
    if (empty($aiEndpoint)) { echo "data: " . json_encode(['error' => '请先配置 API 地址']) . "\n\n"; exit; }
    if (empty($aiApiKey)) { echo "data: " . json_encode(['error' => '请先配置 API Key']) . "\n\n"; exit; }
    if (empty($prompt)) { echo "data: " . json_encode(['error' => '请输入提示词']) . "\n\n"; exit; }
    $malCheck = checkMaliciousContent($prompt);
    if ($malCheck) { echo "data: " . json_encode(['error' => '提示词包含不允许的内容']) . "\n\n"; exit; }

    $systemPrompt = '你是一个专业的 Markdown 文章写作助手。根据用户要求生成高质量 Markdown 文章。只输出 Markdown 内容，不要输出其他说明。内容必须安全、正能量，不要生成任何可执行代码。';
    $messages = [['role'=>'system','content'=>$systemPrompt]];
    if (($input['mode'] ?? '') === 'revise' && !empty($input['original'])) {
        $messages[] = ['role'=>'user','content'=>'请帮我写一篇 Markdown 文章'];
        $messages[] = ['role'=>'assistant','content'=>$input['original']];
        $messages[] = ['role'=>'user','content'=>'请根据以下要求修改上面的文章：'.$prompt];
    } else {
        $messages[] = ['role'=>'user','content'=>$prompt];
    }
    $endpoint = rtrim($aiEndpoint, '/');
    if (!preg_match('#/v1/chat/completions$#', $endpoint)) $endpoint .= '/v1/chat/completions';
    $postData = json_encode([
        'model' => $aiModel ?: 'default', 'stream' => true,
        'messages' => $messages,
        'max_tokens' => $aiMaxTokens, 'temperature' => $aiTemperature
    ]);
    $ctx = stream_context_create(['http' => [
        'method' => 'POST',
        'header' => ['Content-Type: application/json', 'Authorization: Bearer ' . $aiApiKey],
        'content' => $postData, 'timeout' => 120
    ]]);
    $resp = @fopen($endpoint, 'r', false, $ctx);
    if (!$resp) { echo "data: " . json_encode(['error' => 'AI 服务连接失败']) . "\n\n"; exit; }

    $fullContent = '';
    $buffer = '';
    while (!feof($resp)) {
        $chunk = fread($resp, 4096);
        if ($chunk === false || $chunk === '') { if (connection_aborted()) break; usleep(50000); continue; }
        $buffer .= $chunk;
        while (($pos = strpos($buffer, "\n")) !== false) {
            $line = substr($buffer, 0, $pos);
            $buffer = substr($buffer, $pos + 1);
            $line = trim($line);
            if (!str_starts_with($line, 'data: ')) continue;
            $data = substr($line, 6);
            if ($data === '[DONE]') { echo "data: [DONE]\n\n"; break 2; }
            $json = json_decode($data, true);
            $token = $json['choices'][0]['delta']['content'] ?? '';
            if ($token !== '') {
                $fullContent .= $token;
                echo "data: " . json_encode(['token' => $token]) . "\n\n";
                if (ob_get_level()) ob_flush();
                flush();
            }
        }
    }
    fclose($resp);
    // Post-check
    if ($fullContent) {
        $malCheck = checkMaliciousContent($fullContent);
        if ($malCheck) {
            logOperation('AI写作拦截(生成后)', $malCheck);
            echo "data: " . json_encode(['error' => '生成内容包含不允许的内容，已拦截']) . "\n\n";
        } else {
            logOperation('AI写作生成', '提示词: ' . mb_substr($prompt, 0, 50, 'UTF-8'));
            echo "data: " . json_encode(['done' => true]) . "\n\n";
        }
    }
    exit;
}

// Save article (AJAX)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save') {
    $token = $_POST['csrf_token'] ?? '';
    if (!validateCSRFToken($token)) { jsonOut(['success' => false, 'error' => '安全验证失败']); }
    $content = $_POST['content'] ?? '';
    $filename = trim($_POST['filename'] ?? '');
    if (empty($content) || empty($filename)) { jsonOut(['success' => false, 'error' => '内容和文件名不能为空']); }
    $filename = preg_replace('/[^a-zA-Z0-9_\-\x{4e00}-\x{9fa5}]/u', '_', $filename);
    $filename = preg_replace('/_+/', '_', trim($filename, '_'));
    if (empty($filename)) { jsonOut(['success' => false, 'error' => '文件名无效']); }
    $filename .= '.md';
    $filepath = __DIR__ . '/../data/articles/' . $filename;
    $malCheck = checkMaliciousContent($content);
    if ($malCheck) { logOperation('AI写作拦截', $malCheck); jsonOut(['success' => false, 'error' => $malCheck]); }
    if (file_put_contents($filepath, $content, LOCK_EX) !== false) {
        logOperation('AI写作上传', '文件: ' . $filename);
        jsonOut(['success' => true, 'filename' => $filename]);
    }
    jsonOut(['success' => false, 'error' => '保存失败，请检查目录权限']);
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title>AI 写作 - <?= htmlspecialchars($_siteTitle) ?></title>
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
.main{max-width:800px;margin:0 auto;padding:20px 16px 80px}
.page-title{font-size:1.3em;font-weight:650;margin-bottom:6px;display:flex;align-items:center;gap:8px}
.page-title svg{width:24px;height:24px;stroke:var(--accent);fill:none;stroke-width:2}
.page-desc{font-size:.88em;color:var(--text-muted);margin-bottom:20px}
.alert{padding:10px 14px;border-radius:8px;margin-bottom:14px;font-size:.88em;animation:fadeSlide .3s ease}
.alert.success{color:#16a34a;background:#f0fdf4;border:1px solid #bbf7d0}
.alert.error{color:#dc2626;background:#fef2f2;border:1px solid #fecaca}
@keyframes fadeSlide{from{opacity:0;transform:translateY(-6px)}to{opacity:1;transform:translateY(0)}}
.card{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);padding:18px 20px;box-shadow:var(--shadow);margin-bottom:16px}
.card-title{font-size:.95em;font-weight:600;margin-bottom:14px;display:flex;align-items:center;gap:6px}
.card-title svg{width:16px;height:16px;stroke:var(--accent);fill:none;stroke-width:2}
.form-row{display:flex;gap:10px;flex-wrap:wrap}
.form-row .form-group{flex:1;min-width:180px}
.form-group{margin-bottom:14px}
.form-group label{display:block;font-weight:500;margin-bottom:5px;font-size:.85em;color:var(--text-secondary)}
input[type="text"],input[type="number"]{width:100%;padding:9px 11px;border:1px solid var(--border);border-radius:8px;background:var(--bg);color:var(--text);font-family:inherit;font-size:14px;outline:none;transition:border-color .2s}
input:focus{border-color:var(--accent)}
.prompt-area{width:100%;min-height:80px;padding:12px;border:1px solid var(--border);border-radius:8px;background:var(--surface);color:var(--text);font-family:inherit;font-size:14px;outline:none;resize:vertical;transition:border-color .2s}
.prompt-area:focus{border-color:var(--accent)}
.btn{border:none;padding:10px 20px;border-radius:8px;cursor:pointer;font-weight:600;font-size:13px;font-family:inherit;transition:all .2s;white-space:nowrap}
.btn:active{opacity:.85}
.btn:disabled{opacity:.5;cursor:not-allowed}
.btn-primary{background:var(--accent);color:#fff}
.btn-success{background:#16a34a;color:#fff}
.btn-ghost{background:transparent;border:1px solid var(--border);color:var(--text-secondary)}
.btn-ghost:hover{border-color:var(--accent);color:var(--accent)}
.btn-upload{background:#2563eb;color:#fff}
.btn-sm{padding:7px 14px;font-size:.82em}
.preview-box{background:var(--bg);border:1px solid var(--border);border-radius:var(--radius-sm);padding:16px;min-height:180px;max-height:500px;overflow-y:auto;white-space:pre-wrap;font-family:'Courier New',Consolas,monospace;font-size:13px;line-height:1.7;color:var(--text);scroll-behavior:smooth}
.preview-empty{color:var(--text-muted);text-align:center;padding:50px 20px;font-family:inherit}
.bottom-bar{display:flex;gap:10px;margin-top:14px}
.capsule{position:relative;width:44px;height:24px;flex-shrink:0}
.capsule input{opacity:0;width:0;height:0}
.capsule .slider{position:absolute;inset:0;background:#cbd5e1;border-radius:12px;cursor:pointer;transition:.3s}
.capsule .slider:before{content:'';position:absolute;width:18px;height:18px;border-radius:50%;background:#fff;left:3px;top:3px;transition:.3s;box-shadow:0 1px 3px rgba(0,0,0,.15)}
.capsule input:checked+.slider{background:var(--accent)}
.capsule input:checked+.slider:before{transform:translateX(20px)}
.toggle-row{display:flex;align-items:center;justify-content:space-between;padding:10px 0}
.toggle-label{font-size:.9em;color:var(--text)}
.toggle-desc{font-size:.78em;color:var(--text-muted);margin-top:2px}
.modal-mask{position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:500;display:none;align-items:center;justify-content:center;padding:16px;backdrop-filter:blur(6px)}
.modal-mask.show{display:flex}
.modal-box{background:var(--surface);border-radius:20px;width:440px;max-width:100%;overflow:hidden;box-shadow:0 12px 40px rgba(0,0,0,.15);animation:modalIn .25s cubic-bezier(.16,1,.3,1)}
@keyframes modalIn{from{opacity:0;transform:scale(.92) translateY(12px)}to{opacity:1;transform:none}}
.modal-head{padding:24px 24px 0;text-align:center}
.modal-icon{width:56px;height:56px;border-radius:50%;background:#fef3c7;display:flex;align-items:center;justify-content:center;margin:0 auto 14px}
.modal-icon svg{width:28px;height:28px;stroke:#d97706;fill:none;stroke-width:2}
.modal-title{font-size:1.1em;font-weight:700;margin-bottom:8px}
.modal-body{padding:0 24px 24px}
.modal-text{font-size:.88em;color:var(--text-secondary);line-height:1.7;text-align:center;margin-bottom:16px}
.modal-warn-list{text-align:left;font-size:.82em;color:var(--text-muted);background:var(--bg);border-radius:8px;padding:12px 14px;margin-bottom:18px;line-height:1.8}
.modal-warn-list li{margin-left:16px}
.modal-confirm{width:100%;padding:12px;border:none;border-radius:10px;background:var(--accent);color:#fff;font-size:14px;font-weight:600;cursor:pointer;font-family:inherit;transition:all .2s}
.modal-confirm:disabled{opacity:.4;cursor:not-allowed}
.modal-cancel{width:100%;padding:10px;border:none;background:transparent;color:var(--text-muted);font-size:13px;cursor:pointer;font-family:inherit;margin-top:8px}
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
    <div class="page-title"><svg viewBox="0 0 24 24"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>AI 写作</div>
    <div class="page-desc">输入主题，AI 帮你生成 Markdown 文章</div>
    <?php if ($msg): ?><div class="alert success"><?= htmlspecialchars($msg) ?></div><?php endif; ?>

    <!-- Writing Area (first) -->
    <div class="card">
        <div class="card-title"><svg viewBox="0 0 24 24"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>开始写作</div>
        <div class="form-group">
            <label>文章标题（同时作为文件名）</label>
            <input type="text" id="articleFilename" placeholder="例如：Markdown 入门教程">
        </div>
        <div class="form-group">
            <label>写作提示词</label>
            <textarea class="prompt-area" id="promptInput" placeholder="例如：写一篇 Markdown 入门教程，包含标题、列表、代码块等常用语法"></textarea>
        </div>
        <div class="form-group" id="reviseGroup" style="display:none">
            <label>修改提示词（告诉 AI 怎么改）</label>
            <textarea class="prompt-area" id="reviseInput" placeholder="例如：把代码示例改丰富一点，加上注释说明" style="min-height:60px"></textarea>
        </div>
        <div class="form-group">
            <label>生成结果（可编辑）</label>
            <div class="preview-box" id="previewBox"><div class="preview-empty">写完后内容会显示在这里...</div></div>
        </div>
        <div class="bottom-bar">
            <button class="btn btn-ghost" onclick="clearAll()">清空</button>
            <div style="flex:1"></div>
            <button class="btn btn-ghost" id="reviseBtn" style="display:none" onclick="doRevise()">修改</button>
            <button class="btn btn-primary" id="mainBtn" onclick="handleMainBtn()" <?= !$aiEnabled ? 'disabled title="请先开启 AI 写作功能"' : '' ?>>✨ 开始生成</button>
        </div>
    </div>

    <!-- Config (second) -->
    <div class="card">
        <div class="card-title"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>模型配置</div>
        <form method="post">
            <input type="hidden" name="action" value="save_config">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCSRFToken()) ?>">
            <div class="toggle-row">
                <div><div class="toggle-label">启用 AI 写作</div><div class="toggle-desc">开启后可使用 AI 生成文章</div></div>
                <label class="capsule"><input type="checkbox" name="ai_enabled" <?= $aiEnabled ? 'checked' : '' ?>><span class="slider"></span></label>
            </div>
            <div class="form-row">
                <div class="form-group"><label>模型名称</label><input type="text" name="ai_model" value="<?= htmlspecialchars($aiModel) ?>" placeholder="例如：mimo-v2-pro"></div>
                <div class="form-group"><label>API 地址（Base URL）</label><input type="text" name="ai_endpoint" value="<?= htmlspecialchars($aiEndpoint) ?>" placeholder="例如：https://api.mimo.ai"></div>
            </div>
            <div class="form-row">
                <div class="form-group"><label>API Key</label><input type="text" name="ai_api_key" value="<?= $aiApiKey ? '••••••••' : '' ?>" placeholder="留空则不修改"></div>
                <div class="form-group"><label>Max Tokens</label><input type="number" name="ai_max_tokens" value="<?= $aiMaxTokens ?>" min="1024" max="32768"></div>
                <div class="form-group"><label>Temperature</label><input type="number" name="ai_temperature" value="<?= $aiTemperature ?>" min="0" max="2" step="0.1"></div>
            </div>
            <div style="display:flex;justify-content:flex-end"><button type="submit" class="btn btn-primary btn-sm">保存配置</button></div>
        </form>
    </div>
</main>

<!-- Error Modal -->
<div class="modal-mask" id="errorModal">
    <div class="modal-box">
        <div class="modal-head">
            <div class="modal-icon" style="background:#fee2e2"><svg viewBox="0 0 24 24" style="stroke:#dc2626"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg></div>
            <div class="modal-title">连接失败</div>
        </div>
        <div class="modal-body">
            <div id="errorMsg" style="font-size:.88em;color:var(--text-secondary);line-height:1.7;text-align:center;margin-bottom:20px;word-break:break-all"></div>
            <button class="modal-confirm" onclick="document.getElementById('errorModal').classList.remove('show')" style="background:#dc2626">我知道了</button>
        </div>
    </div>
</div>

<!-- Warning Modal -->
<div class="modal-mask" id="warnModal">
    <div class="modal-box">
        <div class="modal-head">
            <div class="modal-icon"><svg viewBox="0 0 24 24"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg></div>
            <div class="modal-title">⚠️ AI 写作 · Beta 功能</div>
        </div>
        <div class="modal-body">
            <div class="modal-text">该功能目前为 <strong>best 版</strong>，安全防护仍在完善中：</div>
            <ul class="modal-warn-list">
                <li>API Key 存储在服务器配置中，<strong>存在泄露风险</strong></li>
                <li>AI 生成内容可能包含不准确信息</li>
                <li>安全检查不保证 100% 拦截恶意内容</li>
                <li>建议不要在提示词中包含敏感信息</li>
            </ul>
            <button class="modal-confirm" id="confirmBtn" disabled>我已知晓风险 (<span id="countdown">10</span>s)</button>
            <button class="modal-cancel" onclick="document.getElementById('warnModal').classList.remove('show')">取消</button>
        </div>
    </div>
</div>

<script>
var generatedContent = '';
var warned = sessionStorage.getItem('ai_write_warned') === '1';
var streaming = false;

function isNearBottom(el) {
    return el.scrollHeight - el.scrollTop - el.clientHeight < 100;
}

function appendText(text) {
    generatedContent += text;
    var p = document.getElementById("previewBox");
    var wasAtBottom = isNearBottom(p);
    p.textContent = generatedContent;
    if (wasAtBottom) p.scrollTop = p.scrollHeight;
}

function showWarnModal(cb) {
    document.getElementById('warnModal').classList.add('show');
    var sec = 10, btn = document.getElementById('confirmBtn'), span = document.getElementById('countdown');
    btn.disabled = true; span.textContent = sec; btn.textContent = '我已知晓风险 (10s)';
    var timer = setInterval(function() { sec--; span.textContent = sec; btn.textContent = '我已知晓风险 (' + sec + 's)'; if (sec <= 0) { clearInterval(timer); btn.disabled = false; btn.textContent = '我已知晓风险，继续使用'; } }, 1000);
    btn.onclick = function() { sessionStorage.setItem('ai_write_warned', '1'); warned = true; document.getElementById('warnModal').classList.remove('show'); if (cb) cb(); };
}

function showError(msg) {
    document.getElementById('errorMsg').textContent = msg;
    document.getElementById('errorModal').classList.add('show');
}

function handleMainBtn() {
    if (!warned) { showWarnModal(function() { doGenerate(); }); return; }
    if (generatedContent) { doUpload(); } else { doGenerate(); }
}

async function doGenerate() {
    var prompt = document.getElementById('promptInput').value.trim();
    if (!prompt) { alert('请输入写作提示词'); return; }
    var btn = document.getElementById('mainBtn');
    btn.disabled = true; btn.textContent = '⏳ 生成中...'; streaming = true;
    var preview = document.getElementById('previewBox');
    preview.textContent = ''; generatedContent = '';

    try {
        var resp = await fetch('?stream=1', {
            method: 'POST',
            headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': '<?= htmlspecialchars(generateCSRFToken()) ?>'},
            body: JSON.stringify({prompt: prompt})
        });
        var reader = resp.body.getReader();
        var decoder = new TextDecoder();
        var buffer = '';

        var streamDone = false;
        while (!streamDone) {
            var result = await reader.read();
            if (result.done) break;
            buffer += decoder.decode(result.value, {stream: true});
            var lines = buffer.split('\n');
            buffer = lines.pop();
            for (var line of lines) {
                line = line.trim();
                if (!line.startsWith('data: ')) continue;
                var data = line.slice(6);
                if (data === '[DONE]') { streamDone = true; break; }
                try {
                    var obj = JSON.parse(data);
                    if (obj.error) { showError(obj.error); btn.disabled = false; btn.textContent = '✨ 开始生成'; streaming = false; return; }
                    if (obj.token) appendText(obj.token);
                    if (obj.done) { btn.textContent = '上传文档'; btn.classList.remove('btn-primary'); btn.classList.add('btn-upload'); }
                } catch(e) {}
            }
        }
    } catch(err) {
        showError('请求失败: ' + err.message);
    }
    var preview = document.getElementById('previewBox');
    preview.textContent = generatedContent;
    btn.disabled = false;
    if (generatedContent) { btn.textContent = '上传文档'; btn.classList.remove('btn-primary'); btn.classList.add('btn-upload'); document.getElementById('reviseBtn').style.display = ''; document.getElementById('reviseGroup').style.display = ''; }
    else { btn.textContent = '✨ 开始生成'; }
    streaming = false;
}

async function doUpload() {
    var filename = document.getElementById('articleFilename').value.trim();
    if (!filename) { alert('请输入文章标题'); return; }
    var btn = document.getElementById('mainBtn');
    btn.disabled = true; btn.textContent = '上传中...';
    try {
        var form = new FormData();
        form.append('action', 'save');
        form.append('content', generatedContent);
        form.append('filename', filename);
        form.append('csrf_token', '<?= htmlspecialchars(generateCSRFToken()) ?>');
        var resp = await fetch('ai_write.php', {method: 'POST', body: form});
        var data = await resp.json();
        if (data.success) {
            alert('✅ 文档已保存: ' + data.filename);
            clearAll();
        } else {
            showError('保存失败: ' + (data.error || '未知错误'));
        }
    } catch(err) {
        showError('请求失败: ' + err.message);
    }
    btn.disabled = false; btn.textContent = '上传文档';
    btn.classList.remove('btn-upload'); btn.classList.add('btn-primary');
}

function clearAll() {
    document.getElementById('promptInput').value = '';
    document.getElementById('articleFilename').value = '';
    document.getElementById('previewBox').innerHTML = '<div class="preview-empty">写完后内容会显示在这里...</div>';
    document.getElementById('reviseGroup').style.display = 'none';
    document.getElementById('reviseBtn').style.display = 'none';
    document.getElementById('reviseInput').value = '';
    generatedContent = '';
    var btn = document.getElementById('mainBtn');
    btn.textContent = '✨ 开始生成'; btn.classList.remove('btn-upload'); btn.classList.add('btn-primary');
}

async function doRevise() {
    var revisePrompt = document.getElementById('reviseInput').value.trim();
    if (!revisePrompt) { alert('请输入修改提示词'); return; }
    if (!warned) { showWarnModal(function() { doRevise(); }); return; }
    var btn = document.getElementById('mainBtn');
    var revBtn = document.getElementById('reviseBtn');
    btn.disabled = true; revBtn.disabled = true;
    btn.textContent = '⏳ 修改中...'; streaming = true;
    var preview = document.getElementById('previewBox');
    preview.textContent = '';

    try {
        var resp = await fetch('?stream=1', {
            method: 'POST',
            headers: {'Content-Type':'application/json','X-CSRF-TOKEN':'<?= htmlspecialchars(generateCSRFToken()) ?>'},
            body: JSON.stringify({prompt: revisePrompt, mode: 'revise', original: generatedContent})
        });
        var reader = resp.body.getReader();
        var decoder = new TextDecoder();
        var buffer = '';
        generatedContent = '';

        var streamDone = false;
        while (!streamDone) {
            var result = await reader.read();
            if (result.done) break;
            buffer += decoder.decode(result.value, {stream: true});
            var lines = buffer.split('\n');
            buffer = lines.pop();
            for (var line of lines) {
                line = line.trim();
                if (!line.startsWith('data: ')) continue;
                var data = line.slice(6);
                if (data === '[DONE]') { streamDone = true; break; }
                try {
                    var obj = JSON.parse(data);
                    if (obj.error) { showError(obj.error); btn.disabled = false; revBtn.disabled = false; btn.textContent = '上传文档'; streaming = false; return; }
                    if (obj.token) appendText(obj.token);
                } catch(e) {}
            }
        }
    } catch(err) {
        showError('请求失败: ' + err.message);
    }
    btn.disabled = false; revBtn.disabled = false;
    btn.textContent = '上传文档'; streaming = false;
}
</script>
</body>
</html>
