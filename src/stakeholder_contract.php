<?php
require_once '../config.php';
require_once 'includes/stakeholder_access.php';

ensure_stakeholder_access_column($conn);

$token = trim($_GET['token'] ?? '');
$stakeholder = get_stakeholder_by_token($conn, $token);

if (!$stakeholder || empty($stakeholder['contract_file'])) {
    http_response_code(404);
    echo 'Contract not found.';
    exit;
}

$contractDir = dirname(__DIR__) . '/data/contracts/';
$fileName = basename((string)$stakeholder['contract_file']);
$filePath = $contractDir . $fileName;

if (!is_file($filePath)) {
    http_response_code(404);
    echo 'Contract file missing.';
    exit;
}

$ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
$mimeMap = [
    'pdf' => 'application/pdf',
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png' => 'image/png',
    'gif' => 'image/gif',
    'webp' => 'image/webp',
];
$mime = $mimeMap[$ext] ?? 'application/octet-stream';

header('Content-Type: ' . $mime);
header('Content-Disposition: inline; filename="' . $fileName . '"');
header('Content-Length: ' . filesize($filePath));
readfile($filePath);
exit;
