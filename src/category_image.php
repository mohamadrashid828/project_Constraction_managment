<?php
session_start();
require_once '../config.php';
require_once 'includes/project_settings.php';

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    exit('Not authenticated');
}

$key = trim($_GET['key'] ?? '');
if ($key === '') {
    http_response_code(404);
    exit('Image not found.');
}

$stmt = $conn->prepare("SELECT image_file FROM project_work_types WHERE work_type_key = ? LIMIT 1");
$stmt->bind_param('s', $key);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row || empty($row['image_file'])) {
    http_response_code(404);
    exit('Image not found.');
}

// image_file is always a name we generated ourselves (see store_category_image),
// never user input, so no path-traversal risk from it.
$fileName = basename((string)$row['image_file']);
$filePath = category_image_dir() . $fileName;

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
