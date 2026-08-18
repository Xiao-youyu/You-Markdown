<?php

// ========== 音源插件 URL 解析 ==========
function getActiveMusicSource($config) {
    $sources = $config['music_sources'] ?? [];
    $activeId = $config['active_music_source'] ?? '';
    if (!empty($sources)) {
        foreach ($sources as $src) {
            if (($src['id'] ?? '') === $activeId) return $src;
        }
        return $sources[0]; // fallback to first
    }
    // Legacy fallback
    if (!empty($config['music_api_key'])) {
        return [
            'id' => 'default',
            'name' => 'ikun 音源',
            'api_url' => $config['music_api_url'] ?? 'https://c.wwwweb.top',
            'api_key' => $config['music_api_key'] ?? '',
            'source' => $config['music_source'] ?? 'wy',
            'quality' => $config['music_quality'] ?? '320k',
        ];
    }
    return null;
}

// ========== URL 缓存 ==========
$CACHE_DIR = __DIR__ . '/data';
$CACHE_FILE = $CACHE_DIR . '/.music_url_cache.json';
$CACHE_TTL = 600; // 缓存10分钟（CDN URL约15-30分钟过期）

function loadUrlCache() {
    global $CACHE_FILE;
    if (!file_exists($CACHE_FILE)) return [];
    $data = json_decode(file_get_contents($CACHE_FILE), true);
    if (!is_array($data)) return [];
    // 清理过期
    $now = time();
    foreach ($data as $k => $v) {
        if (($v['expires'] ?? 0) < $now) unset($data[$k]);
    }
    return $data;
}

function saveUrlCache($cache) {
    global $CACHE_FILE, $CACHE_DIR;
    if (!is_dir($CACHE_DIR)) mkdir($CACHE_DIR, 0755, true);
    // 限制缓存大小，保留最新500条
    if (count($cache) > 500) {
        uasort($cache, fn($a, $b) => ($b['expires'] ?? 0) <=> ($a['expires'] ?? 0));
        $cache = array_slice($cache, 0, 500, true);
    }
    file_put_contents($CACHE_FILE, json_encode($cache), LOCK_EX);
}

function getCachedUrl($songId) {
    global $CACHE_TTL;
    static $cache = null;
    if ($cache === null) $cache = loadUrlCache();
    $key = (string)$songId;
    if (isset($cache[$key]) && ($cache[$key]['expires'] ?? 0) > time()) {
        return $cache[$key]['url'];
    }
    return null;
}

function setCachedUrl($songId, $url) {
    global $CACHE_TTL;
    static $cache = null;
    if ($cache === null) $cache = loadUrlCache();
    $key = (string)$songId;
    $cache[$key] = ['url' => $url, 'expires' => time() + $CACHE_TTL];
    saveUrlCache($cache);
}

function clearCachedUrl($songId) {
    static $cache = null;
    if ($cache === null) $cache = loadUrlCache();
    $key = (string)$songId;
    unset($cache[$key]);
    saveUrlCache($cache);
}

function resolveMusicUrl($songId, $config, $forceRefresh = false) {
    // 先查缓存（除非强制刷新）
    if (!$forceRefresh) {
        $cached = getCachedUrl($songId);
        if ($cached) return $cached;
    }

    $src = getActiveMusicSource($config);
    if (!$src || empty($src['api_key'])) return null;

    $apiUrl = rtrim($src['api_url'] ?? 'https://c.wwwweb.top', '/');
    $apiKey = $src['api_key'] ?? '';
    $source = $src['source'] ?? 'wy';
    $quality = $src['quality'] ?? '320k';

    if (empty($apiKey)) return null;

    $postData = json_encode([
        'source'  => $source,
        'musicId' => (string)$songId,
        'quality' => $quality,
    ]);

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $apiUrl . '/music/url',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $postData,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'X-Api-Key: ' . $apiKey,
            'User-Agent: lx-music-web/1.0',
        ],
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $resp = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    if ($err || $httpCode !== 200 || empty($resp)) return null;
    $data = json_decode($resp, true);
    if (!$data || !isset($data['code'])) return null;

    if ($data['code'] === 200 && !empty($data['url'])) {
        setCachedUrl($songId, $data['url']);
        return $data['url'];
    }
    return null;
}

// ========== 音频代理（支持 Range 请求以实现拖拽进度条和歌词跳转） ==========
if (isset($_GET['proxy'])) {
    $songId = preg_replace('/[^0-9]/', '', $_GET['proxy']);
    if (empty($songId)) { http_response_code(400); exit('Bad request'); }

    $configFile = __DIR__ . '/data/.config.json';
    $config = [];
    if (file_exists($configFile)) {
        $config = json_decode(file_get_contents($configFile), true) ?: [];
    }

    // 解析真实 URL（带缓存）
    $audioUrl = resolveMusicUrl($songId, $config);
    if (!$audioUrl) {
        $audioUrl = 'https://music.163.com/song/media/outer/url?id=' . $songId;
    }
    // CDN URL 转 HTTPS
    if (strpos($audioUrl, 'http://') === 0) {
        $audioUrl = 'https://' . substr($audioUrl, 7);
    }

    // 用 HEAD 获取元数据，带超时
    $headTimeout = 5;
    $headCode = 0;
    $fileSize = 0;
    $contentType = 'audio/mpeg';
    for ($attempt = 0; $attempt < 2; $attempt++) {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $audioUrl,
            CURLOPT_NOBODY         => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 5,
            CURLOPT_TIMEOUT        => $headTimeout,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_HTTPHEADER     => ['Referer: https://music.163.com/'],
        ]);
        curl_exec($ch);
        $headCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $fileSize = curl_getinfo($ch, CURLINFO_CONTENT_LENGTH_DOWNLOAD);
        $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE) ?: 'audio/mpeg';
        curl_close($ch);

        // 如果 HEAD 返回403或无大小，清除缓存并重新解析
        if ($attempt === 0 && ($headCode === 403 || $headCode === 404 || !$fileSize || $fileSize <= 0)) {
            clearCachedUrl($songId);
            $audioUrl = resolveMusicUrl($songId, $config, true); // 强制刷新
            if (!$audioUrl) {
                $audioUrl = 'https://music.163.com/song/media/outer/url?id=' . $songId;
            }
            if (strpos($audioUrl, 'http://') === 0) {
                $audioUrl = 'https://' . substr($audioUrl, 7);
            }
            continue;
        }
        break;
    }

    // 如果无法获取大小，直接透传（不支持 Range）
    if (!$fileSize || $fileSize <= 0) {
        header('Content-Type: audio/mpeg');
        header('Access-Control-Allow-Origin: *');
        header('Cache-Control: public, max-age=86400');
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $audioUrl,
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 5,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_HTTPHEADER     => ['Referer: https://music.163.com/'],
        ]);
        curl_exec($ch);
        curl_close($ch);
        exit;
    }

    // 支持 Range 请求
    $start = 0;
    $end = $fileSize - 1;
    $rangeHeader = $_SERVER['HTTP_RANGE'] ?? '';

    if (!empty($rangeHeader) && preg_match('/bytes=(\d+)-(\d*)/', $rangeHeader, $m)) {
        $start = intval($m[1]);
        $end = ($m[2] !== '') ? min(intval($m[2]), $fileSize - 1) : $fileSize - 1;
        if ($start > $end || $start >= $fileSize) {
            http_response_code(416);
            header('Content-Range: bytes */' . $fileSize);
            exit;
        }
        http_response_code(206);
    }

    $length = $end - $start + 1;

    header('Content-Type: ' . $contentType);
    header('Accept-Ranges: bytes');
    header('Content-Length: ' . $length);
    if ($rangeHeader) {
        header('Content-Range: bytes ' . $start . '-' . $end . '/' . $fileSize);
    }
    header('Access-Control-Allow-Origin: *');
    header('Cache-Control: public, max-age=86400');

    // 禁用 PHP 输出缓冲，减少首字节延迟
    if (function_exists('ob_end_flush')) { while (ob_get_level()) ob_end_flush(); }
    @ini_set('zlib.output_compression', 'Off');

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $audioUrl,
        CURLOPT_RETURNTRANSFER => false,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 5,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_HTTPHEADER     => ['Referer: https://music.163.com/'],
        CURLOPT_RANGE          => $start . '-' . $end,
        CURLOPT_BUFFERSIZE     => 32768, // 32KB chunks
    ]);
    curl_exec($ch);
    curl_close($ch);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

$configFile = __DIR__ . '/data/.config.json';
$config = [];
if (file_exists($configFile)) {
    $config = json_decode(file_get_contents($configFile), true) ?: [];
}

$defaultPlaylistId = $config['music_playlist_id'] ?? '3778678';
$sortAll = $_GET['sortAll'] ?? '';
$playlistId = $_GET['playlistId'] ?? '';
$lyricId = $_GET['lyric'] ?? '';

// ========== 歌词 ==========
if (!empty($lyricId)) {
    if (!preg_match('/^\d+$/', $lyricId)) { http_response_code(400); echo json_encode(['error'=>'invalid id']); exit; }
    $lyricUrl = 'https://music.163.com/api/song/lyric?os=pc&id=' . urlencode($lyricId) . '&yv=-1&lv=-1&tv=-1&rv=-1';
    $lyricData = apiRequest($lyricUrl, 10);
    if ($lyricData && (isset($lyricData['lrc']) || isset($lyricData['yrc']))) {
        echo json_encode([
            'success' => true,
            'lrc'     => $lyricData['lrc']['lyric'] ?? '',
            'tlrc'    => $lyricData['tlyric']['lyric'] ?? '',
            'yrc'     => $lyricData['yrc']['lyric'] ?? '',
            'romalrc' => $lyricData['romalrc']['lyric'] ?? '',
        ], JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode(['success' => false, 'error' => '暂无歌词'], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

// ========== 歌单 ==========
$chartMap = [
    '热歌榜' => '3778678',
    '新歌榜' => '3779629',
    '原创榜' => '2884035',
    '飙升榜' => '19723756',
];
if (!empty($playlistId)) {
    if (!preg_match('/^\d+$/', $playlistId)) { http_response_code(400); echo json_encode(['error'=>'invalid playlist id']); exit; }
} elseif (!empty($sortAll) && isset($chartMap[$sortAll])) {
    $playlistId = $chartMap[$sortAll];
} else {
    $playlistId = $defaultPlaylistId;
}

function apiRequestPost($url, $ids) {
    $headers = [
        'Referer: https://music.163.com/',
        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        'os: pc',
        'Content-Type: application/x-www-form-urlencoded',
    ];
    $postData = 'ids=' . urlencode(json_encode($ids));
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $postData,
        CURLOPT_HTTPHEADER     => $headers,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    if ($error || $httpCode !== 200 || empty($response)) return null;
    $decoded = json_decode($response, true);
    return is_array($decoded) ? $decoded : null;
}

function apiRequest($url, $timeout = 15) {
    $headers = [
        'Referer: https://music.163.com/',
        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        'os: pc',
    ];
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_HTTPHEADER     => $headers,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    if ($error || $httpCode !== 200 || empty($response)) return null;
    $decoded = json_decode($response, true);
    return is_array($decoded) ? $decoded : null;
}

// 获取歌单数据（先从网易云获取歌曲列表，再通过音源插件解析 URL）
$songs = null;

// Step 1: 从网易云获取歌单/榜单的歌曲 ID 列表
$chartMap = [
    '热歌榜' => '3778678', '新歌榜' => '3779629',
    '原创榜' => '2884035', '飙升榜' => '19723756',
];
$fetchPlaylistId = $playlistId;
if (!empty($sortAll) && isset($chartMap[$sortAll])) {
    $fetchPlaylistId = $chartMap[$sortAll];
}

// 方式1: 网易云 API 获取完整歌单
$detailUrl = 'https://music.163.com/api/v6/playlist/detail?id=' . urlencode($fetchPlaylistId) . '&n=10000';
$detailData = apiRequest($detailUrl, 15);
$trackIds = [];

if ($detailData) {
    // 获取 trackIds（即使 tracks 为空，trackIds 可能有）
    $trackIds = array_column($detailData['playlist']['trackIds'] ?? [], 'id');
    $trackCount = $detailData['playlist']['trackCount'] ?? 0;

    // 先用 tracks 数据（可能不完整）
    if (isset($detailData['playlist']['tracks']) && count($detailData['playlist']['tracks']) > 0) {
        $songs = [];
        foreach ($detailData['playlist']['tracks'] as $t) {
            $artists = [];
            foreach ($t['ar'] ?? $t['artists'] ?? [] as $a) { $artists[] = $a['name'] ?? ''; }
            $songs[] = [
                'name'        => $t['name'] ?? '',
                'id'          => $t['id'] ?? 0,
                'url'         => '',
                'picurl'      => ($t['al']['picUrl'] ?? $t['album']['picUrl'] ?? ''),
                'artistsname' => implode(' / ', $artists),
                'duration'    => (($t['dt'] ?? $t['duration'] ?? 0) / 1000),
            ];
        }
    }

    // 如果 tracks 不完整（trackCount > count(tracks)），用 trackIds 批量补全
    $fetchedCount = $songs ? count($songs) : 0;
    if (!empty($trackIds) && $trackCount > $fetchedCount) {
        $missingIds = $fetchedCount > 0
            ? array_values(array_diff($trackIds, array_column($songs, 'id')))
            : $trackIds;
        $batchIds = array_slice($missingIds, 0, 200);
        $songsData = apiRequestPost('https://music.163.com/api/song/detail', $batchIds);
        if ($songsData && isset($songsData['songs'])) {
            if (!$songs) $songs = [];
            $existingIds = array_flip(array_column($songs, 'id'));
            foreach ($songsData['songs'] as $t) {
                if (isset($existingIds[$t['id']])) continue;
                $artists = [];
                foreach ($t['ar'] ?? $t['artists'] ?? [] as $a) { $artists[] = $a['name'] ?? ''; }
                $songs[] = [
                    'name'        => $t['name'] ?? '',
                    'id'          => $t['id'] ?? 0,
                    'url'         => '',
                    'picurl'      => ($t['al']['picUrl'] ?? $t['album']['picUrl'] ?? ''),
                    'artistsname' => implode(' / ', $artists),
                    'duration'    => (($t['dt'] ?? $t['duration'] ?? 0) / 1000),
                ];
            }
        }
    }


}

// 方式2: 回退到第三方 API
if (!$songs) {
    $fallbackUrl = 'https://api.xfyun.club/musicAll/';
    if (!empty($sortAll)) {
        $fallbackUrl .= '?sortAll=' . urlencode($sortAll);
    } else {
        $fallbackUrl .= '?playlistId=' . urlencode($fetchPlaylistId);
    }
    $fbData = apiRequest($fallbackUrl);
    if ($fbData && is_array($fbData) && count($fbData) > 0 && isset($fbData[0]['name'])) {
        $songs = [];
        foreach ($fbData as $t) {
            $songs[] = [
                'name'        => $t['name'] ?? '',
                'id'          => $t['id'] ?? 0,
                'url'         => $t['url'] ?? '',
                'picurl'      => $t['picurl'] ?? '',
                'artistsname' => $t['artistsname'] ?? '',
                'duration'    => $t['duration'] ?? 0,
            ];
        }
    }
}

// ========== 使用音源插件批量解析 URL（并行请求） ==========
if ($songs && (!empty($config['music_api_key']) || !empty($config['music_sources']))) {
    $src = getActiveMusicSource($config);
    if ($src && !empty($src['api_key'])) {
        $apiUrl = rtrim($src['api_url'] ?? 'https://c.wwwweb.top', '/');
        $apiKey = $src['api_key'];
        $source = $src['source'] ?? 'wy';
        $quality = $src['quality'] ?? '320k';

        $batchLimit = min(count($songs), 15);
        // 先查缓存，只对未缓存的发起请求
        $toResolve = []; // index => songId
        for ($i = 0; $i < $batchLimit; $i++) {
            $cached = getCachedUrl($songs[$i]['id']);
            if ($cached) {
                $songs[$i]['url'] = $cached;
            } else {
                $toResolve[$i] = $songs[$i]['id'];
            }
        }

        // 并行解析未缓存的 URL
        if (!empty($toResolve)) {
            $mh = curl_multi_init();
            $handles = [];
            foreach ($toResolve as $idx => $songId) {
                $ch = curl_init();
                curl_setopt_array($ch, [
                    CURLOPT_URL            => $apiUrl . '/music/url',
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT        => 10,
                    CURLOPT_CONNECTTIMEOUT => 5,
                    CURLOPT_POST           => true,
                    CURLOPT_POSTFIELDS     => json_encode([
                        'source'  => $source,
                        'musicId' => (string)$songId,
                        'quality' => $quality,
                    ]),
                    CURLOPT_HTTPHEADER     => [
                        'Content-Type: application/json',
                        'X-Api-Key: ' . $apiKey,
                        'User-Agent: lx-music-web/1.0',
                    ],
                    CURLOPT_SSL_VERIFYPEER => false,
                ]);
                curl_multi_add_handle($mh, $ch);
                $handles[$idx] = $ch;
            }

            // 执行所有请求
            $running = null;
            do {
                curl_multi_exec($mh, $running);
                curl_multi_select($mh, 0.1);
            } while ($running > 0);

            // 收集结果
            foreach ($handles as $idx => $ch) {
                $resp = curl_multi_getcontent($ch);
                $data = json_decode($resp, true);
                if ($data && ($data['code'] ?? 0) === 200 && !empty($data['url'])) {
                    $songs[$idx]['url'] = $data['url'];
                    setCachedUrl($toResolve[$idx], $data['url']);
                }
                curl_multi_remove_handle($mh, $ch);
                curl_close($ch);
            }
            curl_multi_close($mh);
        }
    }
}

// ========== 测速 ==========
if (isset($_GET['speed_test'])) {
    $src = getActiveMusicSource($config);
    if (!$src || empty($src['api_key'])) {
        echo json_encode(['success' => false, 'error' => '未配置音源'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $apiUrl = rtrim($src['api_url'] ?? 'https://c.wwwweb.top', '/');
    $postData = json_encode([
        'source'  => $src['source'] ?? 'wy',
        'musicId' => '1901371647',
        'quality' => '128k',
    ]);
    $start = microtime(true);
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $apiUrl . '/music/url',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $postData,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'X-Api-Key: ' . $src['api_key'],
            'User-Agent: lx-music-web/1.0',
        ],
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $resp = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    $ms = round((microtime(true) - $start) * 1000);
    if ($err || $httpCode !== 200) {
        echo json_encode(['success' => false, 'error' => $err ?: 'HTTP ' . $httpCode, 'ms' => $ms], JSON_UNESCAPED_UNICODE);
    } else {
        $data = json_decode($resp, true);
        if ($data && ($data['code'] ?? 0) === 200) {
            echo json_encode(['success' => true, 'ms' => $ms], JSON_UNESCAPED_UNICODE);
        } else {
            echo json_encode(['success' => false, 'error' => $data['message'] ?? '未知错误', 'ms' => $ms], JSON_UNESCAPED_UNICODE);
        }
    }
    exit;
}

// ========== 输出（添加代理URL给前端） ==========
if ($songs && count($songs) > 0) {
    // CDN URL 转 HTTPS，浏览器直接播放不走代理
    foreach ($songs as &$s) {
        if (!empty($s['url']) && strpos($s['url'], 'http://') === 0) {
            $s['url'] = 'https://' . substr($s['url'], 7);
        }
        if (empty($s['url']) || strpos($s['url'], '/outer/url?id=') !== false) {
            $s['proxy_url'] = 'music.php?proxy=' . $s['id'];
        }
    }
    unset($s);
    echo json_encode($songs, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} else {
    http_response_code(502);
    echo json_encode([
        'error' => '获取歌单失败，请检查网络或歌单ID',
        'playlistId' => $playlistId,
    ], JSON_UNESCAPED_UNICODE);
}




