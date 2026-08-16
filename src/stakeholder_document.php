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
    exit('File not found.');
}

$stmt = $conn->prepare('SELECT file_name, doc_name FROM project_stakeholder_documents WHERE id = ? LIMIT 1');
$stmt->bind_param('i', $id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row || empty($row['file_name'])) {
    http_response_code(404);
    exit('File not found.');
}

// file_name is always a name we generated ourselves (see store_stakeholder_document),
// never user input, so no path-traversal risk from it.
$fileName = basename((string)$row['file_name']);
$filePath = stakeholder_document_dir() . $fileName;

if (!is_file($filePath)) {
    http_response_code(404);
    exit('File is missing on disk.');
}

$ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
$mime = ['pdf' => 'application/pdf', 'jpg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif', 'webp' => 'image/webp'][$ext] ?? 'application/octet-stream';
$niceName = preg_replace('/[^A-Za-z0-9 _.-]+/', '_', (string)$row['doc_name']) . '.' . $ext;

header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($filePath));
header('Content-Disposition: inline; filename="' . $niceName . '"');
header('X-Content-Type-Options: nosniff');
readfile($filePath);
