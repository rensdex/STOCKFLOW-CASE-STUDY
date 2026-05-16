<?php
// File: ajax_check_updates.php
require_once 'config.php';

header('Content-Type: application/json');

$lastUpdate = isset($_GET['last_update']) ? (int)$_GET['last_update'] : 0;

// Check for recent notifications
$stmt = $pdo->prepare("SELECT * FROM notifications WHERE UNIX_TIMESTAMP(created_at) > ? ORDER BY created_at DESC LIMIT 1");
$stmt->execute([$lastUpdate]);
$notification = $stmt->fetch();

$hasUpdate = false;
$message = '';
$type = 'info';

if ($notification) {
    $hasUpdate = true;
    $message = $notification['title'] . ': ' . $notification['message'];
    $type = $notification['type'] === 'warning' ? 'warning' : 'success';
}

echo json_encode([
    'has_update' => $hasUpdate,
    'message' => $message,
    'type' => $type,
    'timestamp' => time(),
    'reload_needed' => $hasUpdate
]);
?>