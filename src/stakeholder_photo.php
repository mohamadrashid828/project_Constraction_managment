<?php
session_start();
require_once '../config.php';
require_once 'includes/permissions.php';
require_once 'includes/stakeholders.php';

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    exit('Not authenticated');
}
if (!in_array('stakeholders', get_user_permissions($conn, (int)$_SESSION['user_id']), true)) {
    http_response_code(403);
    exit('Access denied');
}

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    http_response_code(404);
    exit('Image not found.');
}

$stmt = $conn->prepare('SELECT profile_image FROM project_stakeholders WHERE id = ? LIMIT 1');
$stmt->bind_param('i', $id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row || empty($row['profile_image'])) {
    http_response_code(404);
    exit('Image not found.');
}

// profile_image is always a name we generated ourselves (see store_stakeholder_photo),
// never user input, so no path-traversal risk from it.
$fileName = basename((string)$row['profile_image']);
$filePath = stakeholder_photo_dir() . $fileName;

if (!is_file($filePath)) {
    http_response_code(404);
    exit('Image is missing on disk.');
}

$ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
$mime = ['jpg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif', 'webp' => 'image/webp'][$ext] ?? 'application/octet-stream';

header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($filePath));
header('Cache-Control: private, max-age=86400');
header('X-Content-Type-Options: nosniff');
readfile($filePath);
