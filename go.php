<?php
$d = $_GET['d'] ?? '';

if (empty($d)) {
    header('Location: index.php');
    exit;
}

$filename = basename($d) . '.md';
$filepath = './data/' . $filename;

if (file_exists($filepath) && is_file($filepath)) {
    header('Location: index.php?file=' . urlencode($filename));
    exit;
}

header('Location: index.php');
exit;
