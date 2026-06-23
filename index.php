<?php
session_start();
require_once __DIR__ . '/utils.php';

function isAdmin() {
    return !empty($_SESSION['cmt_user']) && ($_SESSION['cmt_user']['role'] ?? '') === 'admin';
}

function jsonOut($data, $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}



if (!is_dir('./data/articles')) {
    mkdir('./data/articles', 0755, true);
}

$_siteConfig = [];
if (file_exists('./data/.config.json')) {
    $_siteConfig = json_decode(file_get_contents('./data/.config.json'), true) ?: [];
}
$_siteTitle = $_siteConfig['site_title'] ?? 'You Markdown';

$pinFile = './data/.pinned.json';

function getPinnedList() {
    global $pinFile;
    if (file_exists($pinFile)) {
        $data = json_decode(file_get_contents($pinFile), true);
        return is_array($data) ? $data : [];
    }
    return [];
}

function savePinnedList($list) {
    global $pinFile;
    file_put_contents($pinFile, json_encode(array_values($list), JSON_UNESCAPED_UNICODE));
}

$action = isset($_GET['action']) ? $_GET['action'] : '';

if ($action === 'pin') {
    if (!isAdmin()) { logUnauthorized('越权尝试置顶文章', true); jsonOut(['success' => false, 'error' => '无权限'], 403); }
    header('Content-Type: application/json; charset=utf-8');
    $file = basename($_POST['file'] ?? '');
    if (empty($file)) { echo json_encode(['success' => false]); exit; }
    $pinned = getPinnedList();
    if (!in_array($file, $pinned)) {
        $pinned[] = $file;
        savePinnedList($pinned);
    }
    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'unpin') {
    if (!isAdmin()) { logUnauthorized('越权尝试取消置顶文章', true); jsonOut(['success' => false, 'error' => '无权限'], 403); }
    header('Content-Type: application/json; charset=utf-8');
    $file = basename($_POST['file'] ?? '');
    if (empty($file)) { echo json_encode(['success' => false]); exit; }
    $pinned = getPinnedList();
    $pinned = array_values(array_diff($pinned, [$file]));
    savePinnedList($pinned);
    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'list') {
    header('Content-Type: application/json; charset=utf-8');
    $files = glob('./data/articles/*.md');
    $fileList = [];
    $pinnedList = getPinnedList();
    if ($files) {
        usort($files, function($a, $b) { return filemtime($b) - filemtime($a); });
        foreach ($files as $file) {
            $filename = basename($file);
            if (strpos($filename, '.') === 0) continue;
            $content = file_get_contents($file);
            $lines = explode("\n", $content);
            $title = '';
            $wordCount = mb_strlen(preg_replace('/\s+/', '', $content), 'UTF-8');
            $category = ''; $tags = []; $excerpt = ''; $author = '';
            $license = 'CC BY-NC-SA 4.0';
            $licenseUrl = 'https://creativecommons.org/licenses/by-nc-sa/4.0/';
            if (preg_match('/<!--META(.*?)-->/s', $content, $metaMatch)) {
                $meta = json_decode(trim($metaMatch[1]), true);
                if ($meta) {
                    $category = $meta['category'] ?? '';
                    $tags = array_map('trim', explode(',', $meta['tags'] ?? ''));
                    $excerpt = $meta['excerpt'] ?? '';
                    $author = $meta['author'] ?? '';
                    if (!empty($meta['license'])) {
                        $license = $meta['license'];
                        $licenseUrl = $meta['licenseUrl'] ?? '';
                    }
                }
            }
            $inCodeBlock = false;
            foreach ($lines as $line) {
                $trimmed = trim($line);
                if (preg_match('/^```/', $trimmed)) { $inCodeBlock = !$inCodeBlock; continue; }
                if ($inCodeBlock) continue;
                if (preg_match('/^#\s+(.+)/', $trimmed, $matches)) { $title = $matches[1]; break; }
            }
            if (empty($title)) $title = preg_replace('/\.md$/i', '', $filename);
            if (empty($excerpt)) {
                $textContent = preg_replace('/^<!--.*?-->\n?/s', '', $content);
                $textContent = preg_replace('/^#.*$/m', '', $textContent);
                $textContent = preg_replace('/```.*?```/s', '', $textContent);
                $textContent = preg_replace('/\[([^\]]+)\]\([^\)]+\)/', '$1', $textContent);
                $textContent = preg_replace('/[#*>`\-_\|~\[\]]/', '', $textContent);
                $textContent = trim(preg_replace('/\s+/', ' ', $textContent));
                $excerpt = mb_substr($textContent, 0, 120, 'UTF-8');
                if (mb_strlen($textContent, 'UTF-8') > 120) $excerpt .= '...';
            }
            if (empty($tags)) {
                if (preg_match_all('/#(\w+)/u', $content, $matches)) $tags = array_slice(array_unique($matches[1]), 0, 5);
                if (empty($tags)) $tags = ['markdown', '文档'];
            }
            $isPinned = in_array($filename, $pinnedList);
            $fileList[] = [
                'name' => $filename, 'displayName' => $title, 'category' => $category,
                'size' => filesize($file), 'modified' => date('Y-m-d', filemtime($file)),
                'modifiedTimestamp' => filemtime($file), 'excerpt' => $excerpt,
                'wordCount' => $wordCount, 'tags' => $tags, 'author' => $author,
                'license' => $license, 'licenseUrl' => $licenseUrl, 'pinned' => $isPinned
            ];
        }
    }
    usort($fileList, function($a, $b) {
        if ($a['pinned'] && !$b['pinned']) return -1;
        if (!$a['pinned'] && $b['pinned']) return 1;
        return $b['modifiedTimestamp'] - $a['modifiedTimestamp'];
    });
    $total = count($fileList);
    $page = max(1, intval($_GET['page'] ?? 1));
    $perPage = max(1, min(200, intval($_GET['per_page'] ?? 100)));
    $pagedList = array_slice($fileList, ($page - 1) * $perPage, $perPage);
    echo json_encode(['success' => true, 'files' => $pagedList, 'count' => $total, 'page' => $page, 'per_page' => $perPage, 'total_pages' => max(1, ceil($total / $perPage))], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'read') {
    header('Content-Type: application/json; charset=utf-8');
    $requestedFile = isset($_GET['file']) ? $_GET['file'] : '';
    $filename = basename($requestedFile);
    $filepath = './data/articles/' . $filename;
    if (!file_exists($filepath) || !is_file($filepath)) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => '文件不存在'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if (strtolower(pathinfo($filename, PATHINFO_EXTENSION)) !== 'md') {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => '仅支持 .md 文件'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $realDataPath = realpath('./data/articles');
    $realFilePath = realpath($filepath);
    if ($realFilePath === false || strpos($realFilePath, $realDataPath) !== 0) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => '禁止访问'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $content = file_get_contents($filepath);
    $displayName = preg_replace('/\.md$/i', '', $filename);
    if (preg_match('/<!--META(.*?)-->/s', $content, $metaMatch)) {
        $meta = json_decode(trim($metaMatch[1]), true);
        if ($meta && !empty($meta['title'])) {
            $displayName = $meta['title'];
        }
    }
    $content = preg_replace('/<!--META.*?-->\n?/s', '', $content);
    $contentWithoutCode = preg_replace('/```[\s\S]*?```/', '', $content);
    if ($displayName === preg_replace('/\.md$/i', '', $filename) && preg_match('/^#\s+(.+)/m', $contentWithoutCode, $tm)) $displayName = $tm[1];
    echo json_encode([
        'success' => true, 'name' => $filename,
        'displayName' => $displayName,
        'content' => $content, 'size' => filesize($filepath),
        'modified' => date('Y-m-d H:i', filemtime($filepath))
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'delete') {
    if (!isAdmin()) { logUnauthorized('越权尝试删除文章: ' . ($_GET['file'] ?? $_POST['file'] ?? ''), true); jsonOut(['success' => false, 'error' => '无权限'], 403); }
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonOut(['success' => false, 'error' => '请求方式错误'], 405);
    header('Content-Type: application/json; charset=utf-8');
    $requestedFile = $_POST['file'] ?? '';
    $filename = basename($requestedFile);
    $filepath = './data/articles/' . $filename;
    if (!file_exists($filepath) || !is_file($filepath)) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => '文件不存在'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $realDataPath = realpath('./data/articles');
    $realFilePath = realpath($filepath);
    if ($realFilePath === false || strpos($realFilePath, $realDataPath) !== 0) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => '禁止访问'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if (unlink($filepath)) {
        echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode(['success' => false, 'error' => '删除失败'], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

if ($action === 'update') {
    if (!isAdmin()) { logUnauthorized('越权尝试修改文章: ' . ($_GET['file'] ?? ''), true); jsonOut(['success' => false, 'error' => '无权限'], 403); }
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonOut(['success' => false, 'error' => '请求方式错误'], 405);
    header('Content-Type: application/json; charset=utf-8');
    $input = json_decode(file_get_contents('php://input'), true);
    $requestedFile = $input['file'] ?? '';
    $newContent = $input['content'] ?? '';
    $newCategory = $input['category'] ?? '';
    $newTags = $input['tags'] ?? '';
    $newExcerpt = $input['excerpt'] ?? '';
    $newAuthor = $input['author'] ?? '';
    $newLicense = $input['license'] ?? 'CC BY-NC-SA 4.0';
    $newLicenseUrl = $input['licenseUrl'] ?? '';
    $filename = basename($requestedFile);
    $filepath = './data/articles/' . $filename;
    if (!file_exists($filepath) || !is_file($filepath)) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => '文件不存在'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $realDataPath = realpath('./data/articles');
    $realFilePath = realpath($filepath);
    if ($realFilePath === false || strpos($realFilePath, $realDataPath) !== 0) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => '禁止访问'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $meta = json_encode(['category' => $newCategory, 'tags' => $newTags, 'excerpt' => $newExcerpt, 'author' => $newAuthor, 'license' => $newLicense, 'licenseUrl' => $newLicenseUrl], JSON_UNESCAPED_UNICODE);
    $fullContent = "<!--META" . $meta . "-->\n" . $newContent;
    if (file_put_contents($filepath, $fullContent)) {
        echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode(['success' => false, 'error' => '保存失败'], JSON_UNESCAPED_UNICODE);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="zh-CN" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($_siteTitle) ?></title>
    <link rel="icon" href="./logo.png" type="image/png">
    <meta name="description" content="一个基于PHP语言开发的轻量、优雅、简洁的 Markdown 在线阅读器">
    <script src="https://cdn.jsdelivr.net/npm/marked@4.3.0/marked.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/highlightjs/cdn-release@11.9.0/build/styles/atom-one-light.min.css" id="hljsTheme">
    <script src="https://cdn.jsdelivr.net/npm/highlight.js@11.9.0/lib/core.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/highlight.js@11.9.0/lib/languages/javascript.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/highlight.js@11.9.0/lib/languages/typescript.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/highlight.js@11.9.0/lib/languages/php.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/highlight.js@11.9.0/lib/languages/python.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/highlight.js@11.9.0/lib/languages/java.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/highlight.js@11.9.0/lib/languages/cpp.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/highlight.js@11.9.0/lib/languages/csharp.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/highlight.js@11.9.0/lib/languages/go.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/highlight.js@11.9.0/lib/languages/rust.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/highlight.js@11.9.0/lib/languages/css.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/highlight.js@11.9.0/lib/languages/bash.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/highlight.js@11.9.0/lib/languages/json.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/highlight.js@11.9.0/lib/languages/sql.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/highlight.js@11.9.0/lib/languages/yaml.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/highlight.js@11.9.0/lib/languages/xml.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/highlight.js@11.9.0/lib/languages/markdown.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/highlight.js@11.9.0/lib/languages/swift.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/highlight.js@11.9.0/lib/languages/kotlin.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
    <link rel="stylesheet" href="css/style.css">

</head>
<?php $_showAdminLogin = isset($_GET['admin_login']) && $_GET['admin_login'] === '1'; ?>
<body<?= $_showAdminLogin ? ' data-admin-login="1"' : '' ?> data-guest-comments="<?= !empty($_siteConfig['guest_comments_enabled']) ? '1' : '0' ?>">

<header class="top-bar" id="topBar">
    <div class="header-left"><a href="./" class="brand" style="text-decoration:none;cursor:pointer;"><?= htmlspecialchars($_siteTitle) ?></a></div>
    <div class="header-right">
        <button class="icon-btn" id="btnSearch" title="搜索"><svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg></button>
        <button class="icon-btn" id="btnToc" title="目录"><svg viewBox="0 0 24 24"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg></button>
        <button class="icon-btn" id="btnFont" title="字体设置"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="4 7 4 4 20 4 20 7"></polyline><line x1="9" y1="20" x2="15" y2="20"></line><line x1="12" y1="4" x2="12" y2="20"></line></svg></button>
        <button class="icon-btn" id="btnThemeToggle" title="明暗切换"><svg viewBox="0 0 24 24"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg></button>
        <button class="icon-btn" id="btnKbdHelp" title="快捷键帮助 (?)" onclick="toggleKbdHelp()"><svg viewBox="0 0 24 24"><rect x="2" y="6" width="20" height="12" rx="2"/><line x1="6" y1="10" x2="6" y2="10"/><line x1="10" y1="10" x2="10" y2="10"/><line x1="14" y1="10" x2="14" y2="10"/><line x1="18" y1="10" x2="18" y2="10"/><line x1="8" y1="14" x2="16" y2="14"/></svg></button>
        <button class="icon-btn" id="btnColor" title="调整主题色"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2a10 10 0 0 1 0 20 4 4 0 0 1-4-4v-2a2 2 0 0 0-2-2H4a2 2 0 0 1-2-2 10 10 0 0 1 10-10z"/><circle cx="8" cy="6" r="1.5" fill="currentColor" stroke="none"/><circle cx="14" cy="6" r="1.5" fill="currentColor" stroke="none"/><circle cx="18" cy="10" r="1.5" fill="currentColor" stroke="none"/><circle cx="8" cy="12" r="1.5" fill="currentColor" stroke="none"/></svg></button>
    </div>
</header>

<div class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <div class="sidebar-brand">
            <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
            <?= htmlspecialchars($_siteTitle) ?>
        </div>
        <div style="display:flex;align-items:center;gap:4px;">
            <span class="sidebar-count" id="sidebarCount">0</span>
            <button class="icon-btn" onclick="toggleSidebar()" title="折叠侧边栏 (S)" style="width:28px;height:28px;"><svg viewBox="0 0 24 24" width="16" height="16"><polyline points="11 17 6 12 11 7"/><polyline points="18 17 13 12 18 7"/></svg></button>
        </div>
    </div>
    <div class="sidebar-search">
        <input type="text" id="sidebarSearchInput" placeholder="搜索文档... (按 / 聚焦)">
    </div>
    <div class="sidebar-back-btn" id="sidebarBackBtn">
        <svg viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"/></svg>
        返回文章列表
    </div>
    <div class="sidebar-article-title" id="sidebarArticleTitle"></div>
    <div class="sidebar-toc-header" id="sidebarTocHeader">目录</div>
    <div class="sidebar-list" id="sidebarFileList"></div>
    <div class="sidebar-toc-list" id="sidebarTocList"></div>
</div>

<div class="dropdown-panel" id="searchPanel">
    <div class="dropdown-search"><input type="text" id="searchInput" placeholder="搜索文档..."></div>
    <div class="dropdown-list" id="searchResults"></div>
</div>

<div class="dropdown-panel" id="tocPanel">
    <div class="toc-panel-header" id="tocPanelHeader" style="display:none;"><div class="toc-popup-title">目录</div></div>
    <div class="dropdown-list" id="tocFileList"></div>
</div>

<div class="dropdown-panel" id="fontPanel">
    <div class="font-panel-inner">
        <span style="font-weight:600; color:var(--text);">字体设置</span>
        <div class="font-type-buttons" id="fontTypeButtons">
            <button class="font-type-btn active" data-font="default">默认</button>
            <button class="font-type-btn" data-font="custom">萝莉体</button>
        </div>
        <div class="font-size-slider">
            <span style="font-size:14px; color:var(--text-secondary);">A</span>
            <input type="range" min="12" max="24" value="16" step="1" id="fontSizeSlider">
            <span style="font-size:18px; color:var(--text-secondary);">A</span>
            <span class="font-size-value" id="fontSizeValue">16px</span>
        </div>
    </div>
</div>

<div class="dropdown-panel" id="colorPanel">
    <div class="color-panel-content"><div style="display:flex;align-items:center;justify-content:space-between;"><span style="font-weight:600;">选择主题色</span><button class="color-reset-btn" id="colorResetBtn">重置</button></div><input type="range" min="0" max="360" value="220" class="hue-slider" id="hueSlider"></div>
</div>

<main class="main-container" id="mainContainer">
    <div id="homeView"><div class="cards-grid" id="cardsGrid"></div><div class="empty-state" id="emptyHome" style="display:none;">📭 暂无文档</div></div>
    <div class="reading-view" id="readingView">
        <div class="markdown-body" id="markdownBody"></div>

        <div class="cmt-capsule-section" id="commentSection" style="display:none;">
            <div class="cmt-capsule-bar" id="cmtCapsuleBar">
                <button class="cmt-capsule-btn" id="cmtCapsuleBtn">
                    <span class="cmt-capsule-icon"><svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></span>
                    <span class="cmt-capsule-text">未登录用户</span>
                </button>
            </div>
            <div class="cmt-user-bar" id="cmtUserBar" style="display:none">
                <div class="cmt-user-inner" id="cmtUserInner">
                    <div class="cmt-user-avatar" id="cmtUserAvatar"></div>
                    <span class="cmt-user-greeting" id="cmtUserGreeting"></span>
                    <button class="cmt-logout-btn" id="cmtLogoutBtn" title="退出登录"><svg viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg></button>
                </div>
            </div>
        </div>

        <div class="cmt-input-section" id="commentArea" style="display:none;">
            <div class="cmt-input-box">
                <textarea id="cmtTextarea" placeholder="说点什么吧..." maxlength="1000" disabled></textarea>
                <div class="cmt-input-divider"></div>
                <div class="cmt-input-bottom">
                    <button class="cmt-send-btn" id="cmtSendBtn"><svg viewBox="0 0 24 24"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>发送</button>
                </div>
            </div>
        </div>

        <div class="comment-section" id="commentListSection" style="display:none;">
            <div class="cmt-list" id="cmtList"></div>
        </div>

        <div class="prev-next-nav" id="prevNextNav" style="display:none;">
            <button class="prev-next-btn" id="prevBtn"><span class="nav-arrow">‹</span><span class="nav-text" id="prevTitle"></span></button>
            <div class="prev-divider"></div>
            <button class="prev-next-btn next-btn-wrap" id="nextBtn"><span class="nav-text" id="nextTitle"></span><span class="nav-arrow">›</span></button>
        </div>
    </div>
</main>

<div class="floating-buttons" id="floatingButtons" style="display:none;">
    <button class="float-btn" id="floatTocBtn" title="目录"><svg viewBox="0 0 24 24"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg></button>
    <button class="float-btn" id="floatHomeBtn" title="返回主页"><svg viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg></button>
    <button class="float-btn" id="scrollToTopBtn" title="回到顶部"><svg viewBox="0 0 24 24"><line x1="12" y1="19" x2="12" y2="5"/><polyline points="5 12 12 5 19 12"/></svg></button>
</div>

<div class="share-modal-overlay" id="shareModalOverlay">
    <div class="share-modal">
        <div class="share-modal-title">分享文章</div>
        <div id="shareQrcode"></div>
        <button class="share-modal-close" id="shareModalClose">关闭</button>
    </div>
</div>

<div class="toc-popup" id="tocPopup"><div class="toc-popup-header"><div class="toc-popup-title">目录</div></div><div class="toc-popup-list" id="tocPopupList"></div></div>

<div class="reading-progress" id="readingProgress"></div>
<div class="reading-progress-text" id="readingProgressText"></div>

<div class="toast" id="toast"></div>


<div class="img-lightbox" id="imgLightbox"><img src="" alt="" id="lightboxImg"></div>

<div class="cmt-modal-mask" id="cmtAuthModal">
    <div class="cmt-modal-box">
        <div class="cmt-modal-head"><div class="cmt-modal-title" id="cmtAuthTitle">登录</div></div>
        <div class="cmt-modal-body cmt-auth-slide" id="cmtAuthSlide">
            <div class="cmt-modal-form" id="cmtLoginForm">
                <input class="cmt-modal-input" type="text" placeholder="QQ号" maxlength="15" id="cmtLoginQQ">
                <input class="cmt-modal-input" type="password" placeholder="密码" id="cmtLoginPw">
                <div class="cmt-modal-err" id="cmtLoginErr"></div>
                <button class="cmt-modal-submit" id="cmtLoginBtn">登录</button>
            </div>
            <div class="cmt-modal-form" id="cmtRegForm" style="display:none">
                <input class="cmt-modal-input" type="text" placeholder="QQ号" maxlength="15" id="cmtRegQQ">
                <input class="cmt-modal-input" type="text" placeholder="昵称" maxlength="20" id="cmtRegNick">
                <input class="cmt-modal-input" type="password" placeholder="密码（至少6位）" id="cmtRegPw">
                <div class="cmt-modal-err" id="cmtRegErr"></div>
                <button class="cmt-modal-submit" id="cmtRegBtn">注册</button>
            </div>
        </div>
        <div class="cmt-modal-switch"><span id="cmtSwitchText">还没有账号？</span><button class="cmt-modal-switch-link" id="cmtSwitchBtn">立即注册</button></div>
    </div>
</div>

<div class="cmt-modal-mask" id="cmtProfileModal">
    <div class="cmt-modal-box">
        <div class="cmt-modal-head"><div class="cmt-modal-title">编辑资料</div></div>
        <div class="cmt-modal-body">
            <div class="cmt-modal-form">
                <input class="cmt-modal-input" type="text" placeholder="昵称" maxlength="20" id="cmtEditNick">
                <input class="cmt-modal-input" type="text" placeholder="签名（选填，最多16字）" maxlength="16" id="cmtEditSign">
                <div class="cmt-modal-err" id="cmtProfileErr"></div>
                <button class="cmt-modal-submit" id="cmtProfileSave">保存</button>
            </div>
        </div>
    </div>
</div>

<div class="cmt-modal-mask" id="cmtAdminModal">
    <div class="cmt-modal-box">
        <div class="cmt-modal-head"><div class="cmt-modal-title">完善站长信息</div></div>
        <div class="cmt-modal-body">
            <div class="cmt-modal-form">
                <input class="cmt-modal-input" type="text" placeholder="设置QQ号" maxlength="15" id="cmtAdminQQ">
                <input class="cmt-modal-input" type="text" placeholder="设置昵称" maxlength="20" id="cmtAdminNick" value="站长">
                <input class="cmt-modal-input" type="password" placeholder="设置新密码" id="cmtAdminPw">
                <input class="cmt-modal-input" type="password" placeholder="确认新密码" id="cmtAdminPw2">
                <div class="cmt-modal-err" id="cmtAdminErr"></div>
                <button class="cmt-modal-submit" id="cmtAdminSave">保存并进入</button>
            </div>
        </div>
    </div>
</div>

<div class="cmt-confirm-overlay" id="cmtConfirmOverlay">
    <div class="cmt-confirm-box">
        <h3>确认删除</h3>
        <p>删除后不可恢复，确定要删除吗？</p>
        <div class="cmt-confirm-actions">
            <button class="cmt-confirm-cancel" id="cmtConfirmCancel">取消</button>
            <button class="cmt-confirm-ok" id="cmtConfirmOk">删除</button>
        </div>
    </div>
</div>

<script src="js/main.js"></script>

</body>
</html>
