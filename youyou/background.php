<?php
session_start();
require_once __DIR__ . '/../utils.php';
if (empty($_SESSION['cmt_user']) || ($_SESSION['cmt_user']['role'] ?? '') !== 'admin') {
    logUnauthorized('越权尝试访问网站背景设置');
    header('Location: ../?admin_login=1');
    exit;
}

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['bg_image']) && $_FILES['bg_image']['error'] === UPLOAD_ERR_OK) {
    $file = $_FILES['bg_image'];
    $extMap = ['jpg'=>'image/jpeg','jpeg'=>'image/jpeg','png'=>'image/png','gif'=>'image/gif','webp'=>'image/webp'];
    $origExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $mime = null;
    if (function_exists('getimagesize')) { $info = @getimagesize($file['tmp_name']); if ($info && isset($info['mime'])) $mime = $info['mime']; }
    if (!$mime && isset($extMap[$origExt])) $mime = $extMap[$origExt];
    if ($mime && in_array($mime, array_values($extMap)) && $file['size'] <= 10*1024*1024) {
        $ext = array_search($mime, $extMap) ?: $origExt;
        $fname = 'bg_'.time().'_'.bin2hex(random_bytes(4)).'.'.$ext;
        $dir = __DIR__.'/../data/bg/';
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        if (move_uploaded_file($file['tmp_name'], $dir.$fname)) {
            $config = loadSiteConfig();
            $config['bg_type'] = 'image';
            $config['bg_image'] = 'data/bg/'.$fname;
            saveSiteConfig($config);
            header('Location: background.php?msg=uploaded');
            exit;
        }
    }
    header('Location: background.php?msg=upload_error');
    exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['_bg_save'])) {
    $config = loadSiteConfig();
    $config['bg_type'] = in_array($_POST['bg_type']??'', ['none','image','api']) ? $_POST['bg_type'] : 'none';
    $config['bg_image'] = trim($_POST['bg_image']??'');
    $config['bg_api_url'] = trim($_POST['bg_api_url']??'');
    $config['bg_blur_enabled'] = !empty($_POST['bg_blur_enabled']);
    $config['bg_blur_level'] = max(0, min(50, intval($_POST['bg_blur_level']??0)));
    $config['bg_card_opacity'] = max(50, min(100, intval($_POST['bg_card_opacity']??100)));
    $result = saveSiteConfig($config);
    header('Location: background.php?msg='.($result !== false ? 'saved' : 'error'));
    exit;
}

$_siteConfig = loadSiteConfig();
$_siteTitle = $_siteConfig['site_title'] ?? 'You Markdown';
$bgType = $_siteConfig['bg_type'] ?? 'none';
$bgImage = $_siteConfig['bg_image'] ?? '';
$bgApiUrl = $_siteConfig['bg_api_url'] ?? '';
$bgBlurEnabled = !empty($_siteConfig['bg_blur_enabled']);
$bgBlurLevel = $_siteConfig['bg_blur_level'] ?? 0;
$bgCardOpacity = $_siteConfig['bg_card_opacity'] ?? 100;
$msg = $_GET['msg'] ?? '';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title>网站背景 - <?= htmlspecialchars($_siteTitle) ?></title>
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
.main{max-width:600px;margin:0 auto;padding:20px 16px 80px}
.page-title{font-size:1.3em;font-weight:650;margin-bottom:20px;display:flex;align-items:center;gap:8px}
.page-title svg{width:24px;height:24px;stroke:var(--accent);fill:none;stroke-width:2}
.alert{padding:10px 14px;border-radius:8px;margin-bottom:14px;font-size:.88em;animation:fadeSlide .3s ease}
.alert-success{color:#16a34a;background:#f0fdf4;border:1px solid #bbf7d0}
.alert-error{color:#dc2626;background:#fef2f2;border:1px solid #fecaca}
@keyframes fadeSlide{from{opacity:0;transform:translateY(-6px)}to{opacity:1;transform:translateY(0)}}
.form-card{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);padding:20px;box-shadow:var(--shadow);margin-bottom:16px}
.form-group{margin-bottom:16px}
.form-group:last-child{margin-bottom:0}
.form-group label{display:block;font-weight:500;margin-bottom:6px;font-size:.88em;color:var(--text-secondary)}
.form-group .hint{font-size:.78em;color:var(--text-muted);margin-top:4px}
.section-title{font-size:1em;font-weight:600;color:var(--text);margin-bottom:14px;display:flex;align-items:center;gap:8px}
.section-title svg{width:18px;height:18px;stroke:var(--accent);fill:none;stroke-width:2}
.bg-type-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin-bottom:16px}
.bg-type-card{position:relative;border:2px solid var(--border);border-radius:12px;padding:14px 10px;text-align:center;cursor:pointer;transition:all .2s;background:var(--surface)}
.bg-type-card:hover{border-color:var(--accent)}
.bg-type-card.active{border-color:var(--accent);background:hsl(var(--accent-hue),var(--accent-sat),96%)}
.bg-type-card input{position:absolute;opacity:0;pointer-events:none}
.bg-type-card .type-icon{width:36px;height:36px;margin:0 auto 8px;border-radius:10px;display:flex;align-items:center;justify-content:center}
.bg-type-card .type-icon svg{width:20px;height:20px;stroke:currentColor;fill:none;stroke-width:2}
.bg-type-card .type-icon.none{background:#f1f5f9;color:#64748b}
.bg-type-card .type-icon.upload{background:#dbeafe;color:#2563eb}
.bg-type-card .type-icon.api{background:#f0fdf4;color:#16a34a}
.bg-type-card .type-name{font-size:.82em;font-weight:600;color:var(--text)}
.bg-type-card.active .type-name{color:var(--accent)}
.upload-area{border:2px dashed var(--border);border-radius:12px;padding:24px;text-align:center;cursor:pointer;transition:all .2s;position:relative;overflow:hidden}
.upload-area:hover{border-color:var(--accent);background:hsl(var(--accent-hue),var(--accent-sat),98%)}
.upload-area svg{width:32px;height:32px;stroke:var(--text-muted);fill:none;stroke-width:1.5;margin-bottom:8px}
.upload-area .upload-text{font-size:.88em;color:var(--text-secondary)}
.upload-area .upload-hint{font-size:.75em;color:var(--text-muted);margin-top:4px}
.upload-area input{position:absolute;inset:0;opacity:0;cursor:pointer}
.preview-box{position:relative;border-radius:12px;overflow:hidden;aspect-ratio:16/9;background:var(--bg);transition:background .4s}
.preview-box .preview-blur{position:absolute;inset:0;pointer-events:none;transition:backdrop-filter .3s,-webkit-backdrop-filter .3s}
.preview-card-sim{position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);background:rgba(255,255,255,.85);border-radius:10px;padding:12px 16px;box-shadow:0 2px 8px rgba(0,0,0,.1);min-width:120px;text-align:center;z-index:2;transition:background .3s}
.preview-card-sim .sim-title{font-size:12px;font-weight:600;color:#1e293b;margin-bottom:4px}
.preview-card-sim .sim-line{height:4px;background:#e2e8f0;border-radius:2px;margin-bottom:3px}
.preview-card-sim .sim-line:last-child{width:60%;margin:0 auto}
.preview-overlay{position:absolute;bottom:0;left:0;right:0;padding:10px 12px;background:linear-gradient(transparent,rgba(0,0,0,.5));color:#fff;font-size:.75em;z-index:3;display:flex;gap:12px}
.preview-overlay span{white-space:nowrap}
input[type="text"],input[type="url"]{width:100%;padding:10px 12px;border:1px solid var(--border);border-radius:8px;background:var(--bg);color:var(--text);font-family:inherit;font-size:14px;outline:none;transition:border-color .2s}
input:focus{border-color:var(--accent)}
.toggle-row{display:flex;align-items:center;justify-content:space-between;padding:12px 0;border-bottom:1px solid var(--border)}
.toggle-row:last-child{border-bottom:none}
.toggle-label{font-size:.9em}
.toggle-desc{font-size:.78em;color:var(--text-muted);margin-top:2px}
.toggle{position:relative;width:44px;height:24px;flex-shrink:0}
.toggle input{opacity:0;width:0;height:0}
.toggle .slider{position:absolute;inset:0;background:#cbd5e1;border-radius:12px;cursor:pointer;transition:.2s}
.toggle .slider:before{content:'';position:absolute;width:18px;height:18px;border-radius:50%;background:#fff;left:3px;top:3px;transition:.2s}
.toggle input:checked+.slider{background:var(--accent)}
.toggle input:checked+.slider:before{transform:translateX(20px)}
.slider-group{margin-top:12px}
.slider-group .slider-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:6px}
.slider-group .slider-header label{font-weight:500;font-size:.88em;color:var(--text-secondary)}
.slider-group .slider-header .slider-val{font-size:13px;font-weight:600;color:var(--accent);min-width:36px;text-align:right}
.slider-row{display:flex;align-items:center;gap:10px}
.slider-row .slider-label{font-size:11px;color:var(--text-muted);white-space:nowrap}
.slider-row input[type="range"]{flex:1;-webkit-appearance:none;height:6px;border-radius:3px;background:var(--border);outline:none}
.slider-row input[type="range"]::-webkit-slider-thumb{-webkit-appearance:none;width:18px;height:18px;border-radius:50%;background:var(--accent);cursor:pointer}
.btn{background:var(--accent);color:#fff;border:none;padding:11px 24px;border-radius:8px;cursor:pointer;font-weight:600;font-size:14px;transition:all .2s;font-family:inherit}
.btn:active{opacity:.85}
.btn-outline{background:transparent;border:1px solid var(--border);color:var(--text-secondary)}
.btn-sm{padding:8px 16px;font-size:13px}
.api-url-group{display:flex;gap:8px}
.api-url-group input{flex:1}
.img-preview-thumb{display:inline-block;position:relative;margin-top:8px;border-radius:8px;overflow:hidden;border:1px solid var(--border)}
.img-preview-thumb img{display:block;max-width:200px;max-height:120px;object-fit:cover}
.img-preview-thumb .remove-img{position:absolute;top:4px;right:4px;width:22px;height:22px;border-radius:50%;background:rgba(0,0,0,.6);border:none;color:#fff;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:14px;line-height:1}
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
    <div class="page-title"><svg viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>网站背景</div>
    <?php if ($msg === 'saved'): ?><div class="alert alert-success">保存成功</div><?php endif; ?>
    <?php if ($msg === 'error'): ?><div class="alert alert-error">保存失败，请检查 data/ 目录权限</div><?php endif; ?>
    <?php if ($msg === 'uploaded'): ?><div class="alert alert-success">上传成功</div><?php endif; ?>
    <?php if ($msg === 'upload_error'): ?><div class="alert alert-error">上传失败，请检查 data/bg/ 目录权限</div><?php endif; ?>

    <!-- 背景类型 -->
    <div class="form-card">
        <div class="section-title"><svg viewBox="0 0 24 24"><path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5"/><path d="M2 12l10 5 10-5"/></svg>背景类型</div>
        <div class="bg-type-grid">
            <label class="bg-type-card <?= $bgType==='none'?'active':'' ?>" data-type="none"><input type="radio" name="bg_type" value="none" <?= $bgType==='none'?'checked':'' ?>><div class="type-icon none"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/></svg></div><div class="type-name">无背景</div></label>
            <label class="bg-type-card <?= $bgType==='image'?'active':'' ?>" data-type="image"><input type="radio" name="bg_type" value="image" <?= $bgType==='image'?'checked':'' ?>><div class="type-icon upload"><svg viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg></div><div class="type-name">上传图片</div></label>
            <label class="bg-type-card <?= $bgType==='api'?'active':'' ?>" data-type="api"><input type="radio" name="bg_type" value="api" <?= $bgType==='api'?'checked':'' ?>><div class="type-icon api"><svg viewBox="0 0 24 24"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg></div><div class="type-name">API 获取</div></label>
        </div>
    </div>

    <!-- 上传 -->
    <div class="form-card" id="imageSection" style="display:<?= $bgType==='image'?'block':'none' ?>">
        <div class="section-title"><svg viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>上传背景图片</div>
        <?php if ($bgImage && $bgType==='image'): ?>
            <div class="img-preview-thumb"><img src="../<?= htmlspecialchars($bgImage) ?>" alt="当前背景"><button class="remove-img" onclick="removeBgImage()" title="移除">&times;</button></div>
        <?php endif; ?>
        <form method="post" enctype="multipart/form-data">
            <div class="upload-area">
                <svg viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                <div class="upload-text">点击上传背景图片</div>
                <div class="upload-hint">支持 JPG / PNG / GIF / WebP，最大 10MB</div>
                <input type="file" name="bg_image" accept="image/jpeg,image/png,image/gif,image/webp" onchange="if(this.files.length)this.form.submit()">
            </div>
        </form>
        <input type="hidden" id="bgImagePath" value="<?= htmlspecialchars($bgImage) ?>">
    </div>

    <!-- API -->
    <div class="form-card" id="apiSection" style="display:<?= $bgType==='api'?'block':'none' ?>">
        <div class="section-title"><svg viewBox="0 0 24 24"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>API 背景地址</div>
        <div class="form-group">
            <label>图片 API URL</label>
            <div class="api-url-group">
                <input type="url" id="bgApiUrl" value="<?= htmlspecialchars($bgApiUrl) ?>" placeholder="https://api.example.com/random-bg">
                <button class="btn btn-sm" type="button" onclick="testApiUrl()">测试</button>
            </div>
        </div>
        <div id="apiTestResult" style="margin-top:8px"></div>
    </div>

    <!-- 模糊与透明度 -->
    <div class="form-card" id="blurSection">
        <div class="section-title"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>模糊与透明度</div>
        <div id="bgBlurRow" style="display:<?= $bgType!=='none'?'flex':'none' ?>;align-items:center;justify-content:space-between;padding:12px 0;border-bottom:1px solid var(--border)">
            <div><div class="toggle-label">背景模糊</div><div class="toggle-desc">对网站背景应用高斯模糊</div></div>
            <label class="toggle"><input type="checkbox" id="blurToggle" <?= $bgBlurEnabled?'checked':'' ?>><span class="slider"></span></label>
        </div>
        <div id="blurLevelWrap" style="display:<?= ($bgBlurEnabled && $bgType!=='none')?'block':'none' ?>;padding-top:8px">
            <div class="slider-group">
                <div class="slider-header"><label>模糊程度</label><span class="slider-val" id="blurVal"><?= $bgBlurLevel ?>px</span></div>
                <div class="slider-row"><span class="slider-label">清晰</span><input type="range" min="0" max="50" value="<?= $bgBlurLevel ?>" step="2" id="blurSlider"><span class="slider-label">模糊</span></div>
            </div>
        </div>
        <div class="slider-group" style="margin-top:16px">
            <div class="slider-header"><label>卡片透明度</label><span class="slider-val" id="opacityVal"><?= $bgCardOpacity ?>%</span></div>
            <div class="slider-row"><span class="slider-label">透明</span><input type="range" min="50" max="100" value="<?= $bgCardOpacity ?>" step="5" id="opacitySlider"><span class="slider-label">不透明</span></div>
        </div>
    </div>

    <!-- 预览 -->
    <div class="form-card">
        <div class="section-title"><svg viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>实时预览</div>
        <div class="preview-box" id="previewBox">
            <div class="preview-blur" id="previewBlur"></div>
            <div class="preview-card-sim" id="previewCard"><div class="sim-title">文章卡片</div><div class="sim-line" style="width:80%"></div><div class="sim-line" style="width:100%"></div><div class="sim-line"></div></div>
            <div class="preview-overlay"><span id="previewBgLabel">无背景</span><span id="previewBlurLabel"></span></div>
        </div>
    </div>

    <!-- 保存表单 -->
    <form method="post" id="bgForm">
        <input type="hidden" name="_bg_save" value="1">
        <input type="hidden" name="bg_type" id="formBgType" value="<?= htmlspecialchars($bgType) ?>">
        <input type="hidden" name="bg_image" id="formBgImage" value="<?= htmlspecialchars($bgImage) ?>">
        <input type="hidden" name="bg_api_url" id="formBgApiUrl" value="<?= htmlspecialchars($bgApiUrl) ?>">
        <input type="hidden" name="bg_blur_enabled" id="formBlurEnabled" value="<?= $bgBlurEnabled?'1':'' ?>">
        <input type="hidden" name="bg_blur_level" id="formBlurLevel" value="<?= $bgBlurLevel ?>">
        <input type="hidden" name="bg_card_opacity" id="formCardOpacity" value="<?= $bgCardOpacity ?>">
        <div style="display:flex;justify-content:flex-end;gap:10px">
            <button type="button" class="btn btn-outline" onclick="resetBg()">重置</button>
            <button type="submit" class="btn">保存配置</button>
        </div>
    </form>
</main>

<script>
(function() {
    var typeCards = document.querySelectorAll('.bg-type-card');
    var imageSection = document.getElementById('imageSection');
    var apiSection = document.getElementById('apiSection');
    var bgBlurRow = document.getElementById('bgBlurRow');
    var blurLevelWrap = document.getElementById('blurLevelWrap');
    var bgImagePath = document.getElementById('bgImagePath');
    var bgApiUrl = document.getElementById('bgApiUrl');
    var blurToggle = document.getElementById('blurToggle');
    var blurSlider = document.getElementById('blurSlider');
    var blurVal = document.getElementById('blurVal');
    var opacitySlider = document.getElementById('opacitySlider');
    var opacityVal = document.getElementById('opacityVal');
    var previewBox = document.getElementById('previewBox');
    var previewBlur = document.getElementById('previewBlur');
    var previewCard = document.getElementById('previewCard');
    var previewBgLabel = document.getElementById('previewBgLabel');
    var previewBlurLabel = document.getElementById('previewBlurLabel');

    var currentType = '<?= $bgType ?>';
    var previewApiSrc = '';

    typeCards.forEach(function(card) {
        card.addEventListener('click', function() {
            typeCards.forEach(function(c) { c.classList.remove('active'); });
            card.classList.add('active');
            currentType = card.dataset.type;
            imageSection.style.display = currentType === 'image' ? 'block' : 'none';
            apiSection.style.display = currentType === 'api' ? 'block' : 'none';
            bgBlurRow.style.display = currentType !== 'none' ? 'flex' : 'none';
            if (currentType === 'none') blurLevelWrap.style.display = 'none';
            else if (blurToggle.checked) blurLevelWrap.style.display = 'block';
            updatePreview();
        });
    });

    window.testApiUrl = function() {
        var url = bgApiUrl.value.trim();
        if (!url) return;
        var result = document.getElementById('apiTestResult');
        result.innerHTML = '<span style="color:var(--text-muted);font-size:13px">测试中...</span>';
        var img = new Image();
        img.onload = function() {
            previewApiSrc = url;
            result.innerHTML = '<div class="img-preview-thumb"><img src="'+url+'" style="max-width:200px;max-height:120px"></div><div style="font-size:12px;color:#16a34a;margin-top:4px">✓ API 可用</div>';
            updatePreview();
        };
        img.onerror = function() {
            result.innerHTML = '<div style="font-size:12px;color:#dc2626">✗ 无法加载图片</div>';
        };
        img.src = url + (url.indexOf('?')>=0?'&':'?') + '_t=' + Date.now();
    };

    bgApiUrl.addEventListener('input', function() { previewApiSrc = ''; updatePreview(); });
    blurToggle.addEventListener('change', function() { blurLevelWrap.style.display = blurToggle.checked ? 'block' : 'none'; updatePreview(); });
    blurSlider.addEventListener('input', function() { blurVal.textContent = blurSlider.value + 'px'; updatePreview(); });
    opacitySlider.addEventListener('input', function() { opacityVal.textContent = opacitySlider.value + '%'; updatePreview(); });

    function updatePreview() {
        var blur = blurToggle.checked ? parseInt(blurSlider.value) : 0;
        var opacity = parseInt(opacitySlider.value) / 100;

        if (currentType === 'none') {
            previewBox.style.backgroundImage = 'none'; previewBox.style.backgroundColor = 'var(--bg)';
            previewBgLabel.textContent = '无背景';
        } else if (currentType === 'image' && bgImagePath.value) {
            previewBox.style.backgroundImage = 'url(../' + bgImagePath.value + ')'; previewBox.style.backgroundColor = '';
            previewBgLabel.textContent = '自定义图片';
        } else if (currentType === 'api') {
            if (previewApiSrc) {
                previewBox.style.backgroundImage = 'url(' + previewApiSrc + ')'; previewBox.style.backgroundColor = '';
                previewBgLabel.textContent = 'API 图片';
            } else {
                previewBox.style.backgroundImage = 'none'; previewBox.style.backgroundColor = 'var(--bg)';
                previewBgLabel.textContent = bgApiUrl.value.trim() ? 'API（请先测试）' : '待配置';
            }
        } else {
            previewBox.style.backgroundImage = 'none'; previewBox.style.backgroundColor = 'var(--bg)';
            previewBgLabel.textContent = '待配置';
        }
        previewBox.style.backgroundSize = 'cover'; previewBox.style.backgroundPosition = 'center';

        previewBlur.style.backdropFilter = 'blur(' + blur + 'px)';
        previewBlur.style.webkitBackdropFilter = 'blur(' + blur + 'px)';
        previewCard.style.background = 'rgba(255,255,255,' + opacity + ')';

        var labels = [];
        if (blur > 0) labels.push('模糊 ' + blur + 'px');
        labels.push('卡片 ' + Math.round(opacity*100) + '%');
        previewBlurLabel.textContent = labels.join(' · ');
    }

    window.removeBgImage = function() {
        if (confirm('确定移除背景图片？')) {
            bgImagePath.value = '';
            document.getElementById('formBgImage').value = '';
            document.getElementById('formBgType').value = 'none';
            currentType = 'none';
            typeCards.forEach(function(c) { c.classList.remove('active'); });
            typeCards[0].classList.add('active');
            imageSection.style.display = 'none';
            bgBlurRow.style.display = 'none';
            updatePreview();
        }
    };

    window.resetBg = function() {
        currentType = 'none';
        typeCards.forEach(function(c) { c.classList.remove('active'); });
        typeCards[0].classList.add('active');
        imageSection.style.display = 'none'; apiSection.style.display = 'none';
        bgImagePath.value = ''; bgApiUrl.value = ''; previewApiSrc = '';
        blurToggle.checked = false; blurSlider.value = 0; blurVal.textContent = '0px';
        opacitySlider.value = 100; opacityVal.textContent = '100%';
        blurLevelWrap.style.display = 'none'; bgBlurRow.style.display = 'none';
        updatePreview();
    };

    document.getElementById('bgForm').addEventListener('submit', function() {
        document.getElementById('formBgType').value = currentType;
        document.getElementById('formBgImage').value = bgImagePath.value;
        document.getElementById('formBgApiUrl').value = bgApiUrl.value.trim();
        document.getElementById('formBlurEnabled').value = blurToggle.checked ? '1' : '';
        document.getElementById('formBlurLevel').value = blurSlider.value;
        document.getElementById('formCardOpacity').value = opacitySlider.value;
    });

    <?php if ($bgType === 'api' && $bgApiUrl): ?>
    (function() { var u=<?= json_encode($bgApiUrl) ?>; var img=new Image(); img.onload=function(){previewApiSrc=u;updatePreview();}; img.src=u; })();
    <?php endif; ?>
    updatePreview();
})();
</script>
</body>
</html>
