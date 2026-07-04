<?php
session_start();
require_once '../config.php';
require_once 'includes/permissions.php';
require_once 'includes/inventory.php';

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    exit('Not authenticated');
}
if (!in_array('inventory.view', get_user_permissions($conn, (int) $_SESSION['user_id']), true)) {
    http_response_code(403);
    exit('Access denied');
}

$purchaseId = (int) ($_GET['id'] ?? 0);
if ($purchaseId <= 0) {
    http_response_code(404);
    exit('Invoice not found.');
}

$stmt = $conn->prepare("SELECT invoice_file FROM inventory_purchases WHERE id = ? LIMIT 1");
$stmt->bind_param('i', $purchaseId);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row || empty($row['invoice_file'])) {
    http_response_code(404);
    exit('Invoice not found.');
}

// invoice_file is always a name we generated ourselves (see inventory_store_invoice),
// never user input, so no path-traversal risk from it.
$fileName = basename((string) $row['invoice_file']);
$filePath = inventory_invoice_dir() . $fileName;

if (!is_file($filePath)) {
    http_response_code(404);
    exit('Invoice file is missing.');
}

header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="invoice-' . $purchaseId . '.pdf"');
header('Content-Length: ' . filesize($filePath));
readfile($filePath);
exit;
