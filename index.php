<?php

if (!is_dir('./data')) {
    mkdir('./data', 0755, true);
}

$action = isset($_GET['action']) ? $_GET['action'] : '';

if ($action === 'list') {
    header('Content-Type: application/json; charset=utf-8');
    $files = glob('./data/*.md');
    $fileList = [];
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
            foreach ($lines as $line) {
                $trimmed = trim($line);
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
            $fileList[] = [
                'name' => $filename, 'displayName' => $title, 'category' => $category,
                'size' => filesize($file), 'modified' => date('Y-m-d', filemtime($file)),
                'modifiedTimestamp' => filemtime($file), 'excerpt' => $excerpt,
                'wordCount' => $wordCount, 'tags' => $tags, 'author' => $author,
                'license' => $license, 'licenseUrl' => $licenseUrl
            ];
        }
    }
    echo json_encode(['success' => true, 'files' => $fileList, 'count' => count($fileList)], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'read') {
    header('Content-Type: application/json; charset=utf-8');
    $requestedFile = isset($_GET['file']) ? $_GET['file'] : '';
    $filename = basename($requestedFile);
    $filepath = './data/' . $filename;
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
    $realDataPath = realpath('./data');
    $realFilePath = realpath($filepath);
    if ($realFilePath === false || strpos($realFilePath, $realDataPath) !== 0) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => '禁止访问'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $content = file_get_contents($filepath);
    $content = preg_replace('/<!--META.*?-->\n?/s', '', $content);
    echo json_encode([
        'success' => true, 'name' => $filename,
        'displayName' => preg_replace('/\.md$/i', '', $filename),
        'content' => $content, 'size' => filesize($filepath),
        'modified' => date('Y-m-d H:i', filemtime($filepath))
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'delete') {
    header('Content-Type: application/json; charset=utf-8');
    $requestedFile = isset($_GET['file']) ? $_GET['file'] : '';
    $filename = basename($requestedFile);
    $filepath = './data/' . $filename;
    if (!file_exists($filepath) || !is_file($filepath)) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => '文件不存在'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $realDataPath = realpath('./data');
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
    header('Content-Type: application/json; charset=utf-8');
    $input = json_decode(file_get_contents('php://input'), true);
    $requestedFile = $input['file'] ?? '';
    $newContent = $input['content'] ?? '';
    $newCategory = $input['category'] ?? '';
    $newTags = $input['tags'] ?? '';
    $newExcerpt = $input['excerpt'] ?? '';
    $filename = basename($requestedFile);
    $filepath = './data/' . $filename;
    if (!file_exists($filepath) || !is_file($filepath)) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => '文件不存在'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $realDataPath = realpath('./data');
    $realFilePath = realpath($filepath);
    if ($realFilePath === false || strpos($realFilePath, $realDataPath) !== 0) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => '禁止访问'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $meta = json_encode(['category' => $newCategory, 'tags' => $newTags, 'excerpt' => $newExcerpt], JSON_UNESCAPED_UNICODE);
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
    <title>You Markdown</title>
    <link rel="icon" href="./logo.png" type="image/png">
    <meta name="description" content="一个基于PHP语言开发的轻量、优雅、简洁的 Markdown 在线阅读器">
    <script src="https://cdn.jsdelivr.net/npm/marked@4.3.0/marked.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/highlight.js@11.9.0/styles/github.min.css">
    <script src="https://cdn.jsdelivr.net/npm/highlight.js@11.9.0/lib/core.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/highlight.js@11.9.0/lib/languages/javascript.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/highlight.js@11.9.0/lib/languages/php.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/highlight.js@11.9.0/lib/languages/python.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/highlight.js@11.9.0/lib/languages/css.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/highlight.js@11.9.0/lib/languages/bash.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/highlight.js@11.9.0/lib/languages/json.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/highlight.js@11.9.0/lib/languages/sql.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/highlight.js@11.9.0/lib/languages/yaml.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/highlight.js@11.9.0/lib/languages/xml.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/highlight.js@11.9.0/lib/languages/markdown.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
    <link rel="stylesheet" href="css/style.css">

</head>
<body>

<header class="top-bar" id="topBar">
    <div class="header-left"><span class="brand">You Markdown</span></div>
    <div class="header-right">
        <button class="icon-btn" id="btnSearch" title="搜索"><svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg></button>
        <button class="icon-btn" id="btnToc" title="目录"><svg viewBox="0 0 24 24"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg></button>
        <button class="icon-btn" id="btnFont" title="字体设置"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="4 7 4 4 20 4 20 7"></polyline><line x1="9" y1="20" x2="15" y2="20"></line><line x1="12" y1="4" x2="12" y2="20"></line></svg></button>
        <button class="icon-btn" id="btnThemeToggle" title="明暗切换"><svg viewBox="0 0 24 24"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg></button>
        <button class="icon-btn" id="btnColor" title="调整主题色"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2a10 10 0 0 1 0 20 4 4 0 0 1-4-4v-2a2 2 0 0 0-2-2H4a2 2 0 0 1-2-2 10 10 0 0 1 10-10z"/><circle cx="8" cy="6" r="1.5" fill="currentColor" stroke="none"/><circle cx="14" cy="6" r="1.5" fill="currentColor" stroke="none"/><circle cx="18" cy="10" r="1.5" fill="currentColor" stroke="none"/><circle cx="8" cy="12" r="1.5" fill="currentColor" stroke="none"/></svg></button>
    </div>
</header>

<div class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <div class="sidebar-brand">
            <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
            You Markdown
        </div>
        <span class="sidebar-count" id="sidebarCount">0</span>
    </div>
    <div class="sidebar-search">
        <input type="text" id="sidebarSearchInput" placeholder="搜索文档...">
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
    <div class="color-panel-content"><span style="font-weight:600;">选择主题色</span><input type="range" min="0" max="360" value="220" class="hue-slider" id="hueSlider"></div>
</div>

<main class="main-container" id="mainContainer">
    <div id="homeView"><div class="cards-grid" id="cardsGrid"></div><div class="empty-state" id="emptyHome" style="display:none;">📭 暂无文档</div></div>
    <div class="reading-view" id="readingView">
        <div class="markdown-body" id="markdownBody"></div>
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

<div class="toast" id="toast"></div>

<div class="img-lightbox" id="imgLightbox"><img src="" alt="" id="lightboxImg"></div>

<script src="js/main.js"></script>

</body>
</html>
