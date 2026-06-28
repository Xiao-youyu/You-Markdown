<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
$configFile = __DIR__ . '/data/.config.json';
$config = [];
if (file_exists($configFile)) {
    $config = json_decode(file_get_contents($configFile), true) ?: [];
}
$defaultPlaylistId = $config['music_playlist_id'] ?? '3778678';
$musicCookies = $config['music_cookies'] ?? '';
$sortAll = $_GET['sortAll'] ?? '';
$playlistId = $_GET['playlistId'] ?? '';
$lyricId = $_GET['lyric'] ?? '';
if (!empty($lyricId)) {
    $lyricUrl = 'https://music.163.com/api/song/lyric?os=pc&id=' . urlencode($lyricId) . '&yv=-1&lv=-1&tv=-1&rv=-1';
    $lyricData = apiRequest($lyricUrl, 10, $musicCookies);
    if ($lyricData && (isset($lyricData['lrc']) || isset($lyricData['yrc']))) {
        echo json_encode([
            'success' => true,
            'lrc'     => $lyricData['lrc']['lyric'] ?? '',
            'tlrc'    => $lyricData['tlyric']['lyric'] ?? '',
            'yrc'     => $lyricData['yrc']['lyric'] ?? '',
            'romalrc' => $lyricData['romalrc']['lyric'] ?? '',
        ], JSON_UNESCAPED_UNICODE);
    } else {
        $fbUrl = 'https://api.xfyun.club/musicAll/?lyric=' . urlencode($lyricId);
        $fbData = apiRequest($fbUrl);
        if ($fbData && isset($fbData['lrc']['lyric'])) {
            echo json_encode([
                'success' => true,
                'lrc'     => $fbData['lrc']['lyric'],
                'tlrc'    => $fbData['tlyric']['lyric'] ?? '',
            ], JSON_UNESCAPED_UNICODE);
        } else {
            echo json_encode(['success' => false, 'error' => '暂无歌词'], JSON_UNESCAPED_UNICODE);
        }
    }
    exit;
}
$chartMap = [
    '热歌榜' => '3778678',
    '新歌榜' => '3779629',
    '原创榜' => '2884035',
    '飙升榜' => '19723756',
];
if (!empty($playlistId)) {
} elseif (!empty($sortAll) && isset($chartMap[$sortAll])) {
    $playlistId = $chartMap[$sortAll];
} else {
    $playlistId = $defaultPlaylistId;
}
function apiRequest($url, $timeout = 15, $cookies = '') {
    $headers = [
        'Referer: https://music.163.com/',
        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
    ];
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTPHEADER => $headers,
    ]);
    if (!empty($cookies)) {
        curl_setopt($ch, CURLOPT_COOKIE, $cookies);
    }
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    $errno = curl_errno($ch);
    curl_close($ch);
    if ($error || $httpCode !== 200 || empty($response)) return null;
    $decoded = json_decode($response, true);
    return is_array($decoded) ? $decoded : null;
}
function apiRequestPost($url, $songIds, $cookies = '') {
    $headers = [
        'Referer: https://music.163.com/',
        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        'Content-Type: application/x-www-form-urlencoded',
    ];
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => 'ids=' . urlencode(json_encode($songIds)) . '&level=exhigh&encodeType=flac',
    ]);
    if (!empty($cookies)) {
        curl_setopt($ch, CURLOPT_COOKIE, $cookies);
    }
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    if ($error || $httpCode !== 200 || empty($response)) return null;
    $decoded = json_decode($response, true);
    return is_array($decoded) ? $decoded : null;
}
$errors = [];
$songs = null;
$hasCookie = !empty($musicCookies);
$apiUrls = [];
if (!empty($sortAll)) {
    $apiUrls[] = 'https://api.xfyun.club/musicAll/?sortAll=' . urlencode($sortAll);
} else {
    $apiUrls[] = 'https://api.xfyun.club/musicAll/?playlistId=' . urlencode($playlistId);
}
if (!empty($config['music_api_backup'])) {
    $backupBase = rtrim($config['music_api_backup'], '/');
    if (!empty($sortAll)) {
        $apiUrls[] = $backupBase . '/musicAll/?sortAll=' . urlencode($sortAll);
    } else {
        $apiUrls[] = $backupBase . '/musicAll/?playlistId=' . urlencode($playlistId);
    }
}
foreach ($apiUrls as $apiUrl) {
    $data = apiRequest($apiUrl);
    if (!$data) { $errors[] = 'API源无响应: ' . substr($apiUrl, 0, 80); continue; }
    $tracks = null;
    if (is_array($data) && count($data) > 0 && isset($data[0]['name'])) {
        $tracks = $data;
    } elseif (isset($data['playlist']['tracks'])) {
        $tracks = [];
        foreach ($data['playlist']['tracks'] as $t) {
            $artists = [];
            foreach ($t['ar'] ?? $t['artists'] ?? [] as $a) { $artists[] = $a['name'] ?? ''; }
            $tracks[] = [
                'name' => $t['name'] ?? '', 'id' => $t['id'] ?? 0,
                'url'  => $t['url'] ?? '',
                'picurl' => ($t['al']['picUrl'] ?? $t['album']['picUrl'] ?? ''),
                'artistsname' => implode(' / ', $artists),
                'duration' => (($t['dt'] ?? $t['duration'] ?? 0) / 1000),
            ];
        }
    }
    if ($tracks && count($tracks) > 0) {
        $songs = [];
        foreach ($tracks as $t) {
            $songs[] = [
                'name' => $t['name'] ?? '', 'id' => $t['id'] ?? 0,
                'url'  => $t['url'] ?? '', 'picurl' => $t['picurl'] ?? '',
                'artistsname' => $t['artistsname'] ?? '', 'duration' => $t['duration'] ?? 0,
            ];
        }
        $errors[] = '第三方API: 获取'.count($songs).'首';
        break;
    }
    $errors[] = 'API源格式不识别: ' . substr($apiUrl, 0, 80);
}
if ($songs && $hasCookie) {
    $songIds = array_column($songs, 'id');
    $urlMap = [];
    $idsJson = json_encode($songIds);
    $playerUrl = 'https://music.163.com/api/song/enhance/player/url?ids=' . urlencode($idsJson) . '&br=999000';
    $playerData = apiRequest($playerUrl, 15, $musicCookies);
    if ($playerData && isset($playerData['data'])) {
        foreach ($playerData['data'] as $item) {
            if (!empty($item['url'])) $urlMap[$item['id']] = $item['url'];
        }
    }
    $missingIds = array_filter($songIds, function($id) use ($urlMap) { return !isset($urlMap[$id]); });
    if (!empty($missingIds)) {
        $v1Data = apiRequestPost('https://music.163.com/api/song/enhance/player/url/v1', $missingIds, $musicCookies);
        if ($v1Data && isset($v1Data['data'])) {
            foreach ($v1Data['data'] as $item) {
                if (!empty($item['url']) && !isset($urlMap[$item['id']])) $urlMap[$item['id']] = $item['url'];
            }
        }
    }
    foreach ($songs as &$s) {
        if (isset($urlMap[$s['id']])) $s['url'] = $urlMap[$s['id']];
    }
    unset($s);
    $errors[] = '官方API: 替换'.count($urlMap).'首播放地址';
}
if (!$hasCookie && $songs) {
    foreach ($songs as &$s) {
        if (strpos($s['url'], '/outer/url?id=') !== false) {
            $id = $s['id'];
            $s['url'] = 'https://music.163.com/song/media/outer/url?id=' . $id;
        }
    }
    unset($s);
}
if ($songs && count($songs) > 0) {
    echo json_encode($songs, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} else {
    http_response_code(502);
    echo json_encode([
        'error' => '获取歌单失败，请检查网络或歌单ID',
        'debug' => $errors,
        'playlistId' => $playlistId,
        'hasCookies' => !empty($musicCookies),
    ], JSON_UNESCAPED_UNICODE);
}
