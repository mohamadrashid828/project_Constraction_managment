<?php
session_start();
require_once '../config.php';

header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$user_id = (int)$_SESSION['user_id'];

// ── Permission check ──────────────────────────────────────────────────────
$permStmt = $conn->prepare('SELECT p.name FROM permissions p JOIN role_permissions rp ON p.id = rp.permission_id JOIN users u ON rp.role_id = u.role_id WHERE u.id = ?');
$permStmt->bind_param('i', $user_id);
$permStmt->execute();
$permRes  = $permStmt->get_result();
$allowed  = false;
while ($row = $permRes->fetch_assoc()) {
    if (in_array($row['name'], ['user_management', 'project_settings'], true)) {
        $allowed = true;
        break;
    }
}
$permStmt->close();

if (!$allowed && !empty($_SESSION['role_id'])) {
    $roleId   = (int)$_SESSION['role_id'];
    $roleStmt = $conn->prepare("SELECT name FROM roles WHERE id = ? LIMIT 1");
    $roleStmt->bind_param('i', $roleId);
    $roleStmt->execute();
    $roleRes = $roleStmt->get_result();
    if ($roleRes && ($roleRow = $roleRes->fetch_assoc())) {
        $allowed = in_array(strtolower(trim((string)$roleRow['name'])), ['manager', 'admin']);
    }
    $roleStmt->close();
}

if (!$allowed) {
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

// ── Parse request ─────────────────────────────────────────────────────────
$raw  = file_get_contents('php://input');
$body = json_decode($raw, true);

if (!is_array($body)) {
    echo json_encode(['success' => false, 'message' => 'Invalid JSON payload']);
    exit;
}

$entry_id   = (int)($body['entry_id']   ?? 0);
$source     = trim($body['source']      ?? 'generic'); // 'generic' or 'gechkari'
$new_status = strtolower(trim($body['new_status'] ?? ''));

if ($entry_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid entry ID']);
    exit;
}

$valid_statuses = ['accepted', 'medium', 'rejected', 'draft'];
if (!in_array($new_status, $valid_statuses, true)) {
    echo json_encode(['success' => false, 'message' => 'Invalid status value']);
    exit;
}

// ── Gechkari (measurements table) ────────────────────────────────────────
if ($source === 'gechkari') {
    // Legacy schema had status ENUM('draft','approved','rejected'); under
    // STRICT_TRANS_TABLES writing 'accepted'/'medium' into it fails the whole
    // UPDATE. Widen it once so every status the app uses is storable.
    $statusCol = $conn->query("SHOW COLUMNS FROM measurements LIKE 'status'");
    if ($statusCol && ($col = $statusCol->fetch_assoc()) && stripos((string)$col['Type'], "'medium'") === false) {
        $conn->query("ALTER TABLE measurements MODIFY status ENUM('draft','medium','accepted','approved','rejected') NOT NULL DEFAULT 'draft'");
    }

    $stmt = $conn->prepare("SELECT id, status FROM measurements WHERE id = ? LIMIT 1");
    $stmt->bind_param('i', $entry_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
        echo json_encode(['success' => false, 'message' => 'Entry not found']);
        exit;
    }

    $old_status = (string)$row['status'];
    $upd = $conn->prepare("UPDATE measurements SET status = ? WHERE id = ?");
    $upd->bind_param('si', $new_status, $entry_id);
    $ok = $upd->execute();
    $updError = $upd->error;
    $upd->close();

    if (!$ok) {
        echo json_encode(['success' => false, 'message' => 'Failed to change status: ' . $updError]);
        exit;
    }

    echo json_encode([
        'success'    => true,
        'message'    => 'Status changed from ' . ucfirst($old_status) . ' to ' . ucfirst($new_status),
        'old_status' => $old_status,
        'new_status' => $new_status,
    ]);
    exit;
}

// ── Generic (project_work_entries) ────────────────────────────────────────
// Ensure tracking columns exist (auto-migrate)
$colCheck = $conn->query("SHOW COLUMNS FROM project_work_entries LIKE 'status_changed_by'");
if ($colCheck && $colCheck->num_rows === 0) {
    $conn->query("ALTER TABLE project_work_entries
        ADD COLUMN status_changed_by INT NULL DEFAULT NULL,
        ADD COLUMN status_changed_at DATETIME NULL DEFAULT NULL,
        ADD COLUMN previous_status VARCHAR(30) NULL DEFAULT NULL");
}

$stmt = $conn->prepare("SELECT id, status FROM project_work_entries WHERE id = ? LIMIT 1");
$stmt->bind_param('i', $entry_id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row) {
    echo json_encode(['success' => false, 'message' => 'Entry not found']);
    exit;
}

$old_status = (string)$row['status'];
$now        = date('Y-m-d H:i:s');

$upd = $conn->prepare("UPDATE project_work_entries
    SET status = ?, previous_status = ?, status_changed_by = ?, status_changed_at = ?
    WHERE id = ?");
$upd->bind_param('ssisi', $new_status, $old_status, $user_id, $now, $entry_id);
$ok = $upd->execute();
$updError = $upd->error;
$upd->close();

if (!$ok) {
    echo json_encode(['success' => false, 'message' => 'Failed to change status: ' . $updError]);
    exit;
}

echo json_encode([
    'success'    => true,
    'message'    => 'Status changed from ' . ucfirst($old_status) . ' to ' . ucfirst($new_status),
    'old_status' => $old_status,
    'new_status' => $new_status,
]);
