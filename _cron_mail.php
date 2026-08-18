<?php
chdir(__DIR__);
require_once "utils.php";
$queueFile = __DIR__ . "/data/.mail_queue.json";
if (!file_exists($queueFile)) exit(0);
$queue = json_decode(file_get_contents($queueFile), true);
if (empty($queue)) exit(0);
$sent = 0;
$remaining = [];
foreach ($queue as $item) {
    $to = $item["to"] ?? "";
    $subject = $item["subject"] ?? "";
    $html = $item["html"] ?? "";
    if (empty($to) || empty($subject)) continue;
    $result = sendEmail($to, $subject, $html);
    if (($result["success"] ?? false)) {
        $sent++;
    } else {
        $remaining[] = $item;
    }
}
if (!empty($remaining)) {
    file_put_contents($queueFile, json_encode($remaining, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
} else {
    @unlink($queueFile);
}
echo "Sent: $sent, Remaining: " . count($remaining) . "\n";
