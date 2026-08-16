<?php
session_start();
require_once '../config.php';
require_once 'includes/permissions.php';
require_once 'includes/hr.php';

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    exit('Not authenticated');
}
if (!in_array('hr', get_user_permissions($conn, (int)$_SESSION['user_id']), true)) {
    http_response_code(403);
    exit('Access denied');
}

$source = ($_GET['source'] ?? 'document') === 'contract' ? 'contract' : 'document';
$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    http_response_code(404);
    exit('File not found.');
}

if ($source === 'contract') {
    $stmt = $conn->prepare("SELECT file_name AS f, COALESCE(contract_title, 'contract') AS label FROM hr_contracts WHERE id = ? LIMIT 1");
} else {
    $stmt = $conn->prepare("SELECT file_name AS f, doc_name AS label FROM hr_documents WHERE id = ? LIMIT 1");
}
$stmt->bind_param('i', $id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row || empty($row['f'])) {
    http_response_code(404);
    exit('File not found.');
}

// file_name is always a name we generated ourselves (see hr_store_document),
// never user input, so no path-traversal risk from it.
$fileName = basename((string)$row['f']);
$filePath = hr_document_dir() . $fileName;

if (!is_file($filePath)) {
    http_response_code(404);
    exit('File is missing on disk.');
}

$ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
$mime = ['pdf' => 'application/pdf', 'jpg' => 'image/jpeg', 'png' => 'image/png'][$ext] ?? 'application/octet-stream';
$niceName = preg_replace('/[^A-Za-z0-9 _.-]+/', '_', (string)$row['label']) . '.' . $ext;

header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($filePath));
header('Content-Disposition: inline; filename="' . $niceName . '"');
header('X-Content-Type-Options: nosniff');
readfile($filePath);
