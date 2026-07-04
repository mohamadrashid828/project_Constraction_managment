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

// Permission check
$permStmt = $conn->prepare('SELECT p.name FROM permissions p JOIN role_permissions rp ON p.id = rp.permission_id JOIN users u ON rp.role_id = u.role_id WHERE u.id = ?');
$permStmt->bind_param('i', $user_id);
$permStmt->execute();
$permRes  = $permStmt->get_result();
$permissions = [];
while ($permRow = $permRes->fetch_assoc()) {
    $permissions[] = $permRow['name'];
}
$permStmt->close();

$isManager = in_array('user_management', $permissions, true) || in_array('project_settings', $permissions, true);
if (!$isManager && !empty($_SESSION['role_id'])) {
    $roleStmt = $conn->prepare("SELECT name FROM roles WHERE id = ? LIMIT 1");
    if ($roleStmt) {
        $roleId = (int)$_SESSION['role_id'];
        $roleStmt->bind_param("i", $roleId);
        $roleStmt->execute();
        $roleRes = $roleStmt->get_result();
        if ($roleRes && ($roleRow = $roleRes->fetch_assoc())) {
            $isManager = in_array(strtolower(trim((string)$roleRow['name'])), ['manager', 'admin']);
        }
        $roleStmt->close();
    }
}

// Match the slfa.php page gate: must be a manager AND hold the 'slfa' permission.
if (!$isManager || !in_array('slfa', $permissions, true)) {
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

// ── Parse JSON body ───────────────────────────────────────────────────────
$raw  = file_get_contents('php://input');
$body = json_decode($raw, true);

if (!is_array($body)) {
    echo json_encode(['success' => false, 'message' => 'Invalid JSON payload']);
    exit;
}

$stakeholder_id      = (int)($body['stakeholder_id']      ?? 0);
$payment_date        = trim($body['payment_date']          ?? '');
$total_work_value    = (float)($body['total_work_value']   ?? 0);
$cash_percentage     = (float)($body['cash_percentage']    ?? 0);
$cash_amount         = (float)($body['cash_amount']        ?? 0);
$apartment_percentage = (float)($body['apartment_percentage'] ?? 0);
$apartment_amount    = (float)($body['apartment_amount']   ?? 0);
$apartment_sqm       = (float)($body['apartment_sqm']      ?? 0);
$apartment_meter_price = (float)($body['apartment_meter_price'] ?? 0);
$entry_count         = (int)($body['entry_count']          ?? 0);
$notes               = trim($body['notes']                 ?? '');
$entry_ids           = $body['entry_ids'] ?? [];

// ── Validate ──────────────────────────────────────────────────────────────
if ($stakeholder_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid stakeholder']);
    exit;
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $payment_date)) {
    echo json_encode(['success' => false, 'message' => 'Invalid payment date']);
    exit;
}
if (!is_array($entry_ids) || count($entry_ids) === 0) {
    echo json_encode(['success' => false, 'message' => 'No entries selected']);
    exit;
}
// Note: all financial figures (total_work_value, cash/apartment amounts, sqm)
// are recomputed server-side below from the verified entries and the
// stakeholder's configured split — the client-sent amounts are used only for
// the confirm-dialog preview and are never persisted.

// Sanitise entry_ids to integers
$entry_ids = array_filter(array_map('intval', $entry_ids), fn($v) => $v > 0);
if (count($entry_ids) === 0) {
    echo json_encode(['success' => false, 'message' => 'No valid entry IDs']);
    exit;
}

// ── Verify stakeholder exists ─────────────────────────────────────────────
$chk = $conn->prepare("SELECT id FROM project_stakeholders WHERE id = ? AND is_active = 1 LIMIT 1");
$chk->bind_param('i', $stakeholder_id);
$chk->execute();
if (!$chk->get_result()->fetch_assoc()) {
    $chk->close();
    echo json_encode(['success' => false, 'message' => 'Stakeholder not found']);
    exit;
}
$chk->close();

// ── Ensure slfa_payments table ────────────────────────────────────────────
$conn->query("CREATE TABLE IF NOT EXISTS slfa_payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    stakeholder_id INT NOT NULL,
    payment_date DATE NOT NULL,
    total_work_value DECIMAL(14,2) NOT NULL DEFAULT 0,
    cash_percentage DECIMAL(5,2) NOT NULL DEFAULT 0,
    cash_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
    apartment_percentage DECIMAL(5,2) NOT NULL DEFAULT 0,
    apartment_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
    apartment_sqm DECIMAL(14,4) NOT NULL DEFAULT 0,
    apartment_meter_price DECIMAL(14,2) NOT NULL DEFAULT 0,
    entry_count INT NOT NULL DEFAULT 0,
    notes TEXT NULL,
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_stakeholder (stakeholder_id),
    INDEX idx_payment_date (payment_date)
)");

// ── Ensure slfa_payment_id column on project_work_entries ─────────────────
$colCheck = $conn->query("SHOW COLUMNS FROM project_work_entries LIKE 'slfa_payment_id'");
if ($colCheck && $colCheck->num_rows === 0) {
    $conn->query("ALTER TABLE project_work_entries ADD COLUMN slfa_payment_id INT NULL DEFAULT NULL AFTER status, ADD INDEX idx_slfa_payment (slfa_payment_id)");
}

// ── Verify that all entry_ids belong to this stakeholder & are unsettled ──
$placeholders = implode(',', array_fill(0, count($entry_ids), '?'));
$verifySQL    = "SELECT id FROM project_work_entries
                 WHERE id IN ($placeholders)
                   AND stakeholder_id = ?
                   AND (slfa_payment_id IS NULL OR slfa_payment_id = 0)";

$verifyStmt = $conn->prepare($verifySQL);
$verifyTypes = str_repeat('i', count($entry_ids)) . 'i';
$verifyArgs  = array_merge($entry_ids, [$stakeholder_id]);
$verifyRefs  = [&$verifyTypes];
foreach ($verifyArgs as $i => $v) {
    $verifyRefs[] = &$verifyArgs[$i];
}
call_user_func_array([$verifyStmt, 'bind_param'], $verifyRefs);
$verifyStmt->execute();
$verifyRes    = $verifyStmt->get_result();
$verifiedIds  = [];
while ($vr = $verifyRes->fetch_assoc()) {
    $verifiedIds[] = (int)$vr['id'];
}
$verifyStmt->close();

if (count($verifiedIds) === 0) {
    echo json_encode(['success' => false, 'message' => 'None of the selected entries are valid or unsettled for this stakeholder']);
    exit;
}

// Use only verified IDs
$entry_ids   = $verifiedIds;
$entry_count = count($entry_ids);

// ── Recompute all money server-side (never trust client amounts) ──────────
$sumPlaceholders = implode(',', array_fill(0, count($entry_ids), '?'));
$sumStmt  = $conn->prepare("SELECT COALESCE(SUM(total_price), 0) AS t FROM project_work_entries WHERE id IN ($sumPlaceholders)");
$sumTypes = str_repeat('i', count($entry_ids));
$sumRefs  = [&$sumTypes];
foreach ($entry_ids as $i => $v) {
    $sumRefs[] = &$entry_ids[$i];
}
call_user_func_array([$sumStmt, 'bind_param'], $sumRefs);
$sumStmt->execute();
$total_work_value = (float)($sumStmt->get_result()->fetch_assoc()['t'] ?? 0);
$sumStmt->close();

$splitStmt = $conn->prepare("SELECT cash_percentage, apartment_percentage, apartment_meter_price FROM project_stakeholders WHERE id = ?");
$splitStmt->bind_param('i', $stakeholder_id);
$splitStmt->execute();
$splitRow = $splitStmt->get_result()->fetch_assoc();
$splitStmt->close();

$cash_percentage       = (float)($splitRow['cash_percentage'] ?? 0);
$apartment_percentage  = (float)($splitRow['apartment_percentage'] ?? 0);
$apartment_meter_price = (float)($splitRow['apartment_meter_price'] ?? 0);
$cash_amount      = round($total_work_value * $cash_percentage / 100, 2);
$apartment_amount = round($total_work_value * $apartment_percentage / 100, 2);
$apartment_sqm    = $apartment_meter_price > 0 ? round($apartment_amount / $apartment_meter_price, 4) : 0;

if ($total_work_value <= 0) {
    echo json_encode(['success' => false, 'message' => 'Selected entries have no billable value']);
    exit;
}

// ── Begin transaction ─────────────────────────────────────────────────────
$conn->begin_transaction();

try {
    // 1. Insert slfa_payments record
    $ins = $conn->prepare("INSERT INTO slfa_payments
        (stakeholder_id, payment_date, total_work_value, cash_percentage, cash_amount,
         apartment_percentage, apartment_amount, apartment_sqm, apartment_meter_price,
         entry_count, notes, created_by)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $ins->bind_param(
        'isddddddddsi',
        $stakeholder_id,
        $payment_date,
        $total_work_value,
        $cash_percentage,
        $cash_amount,
        $apartment_percentage,
        $apartment_amount,
        $apartment_sqm,
        $apartment_meter_price,
        $entry_count,
        $notes,
        $user_id
    );
    $ins->execute();
    $payment_id = (int)$conn->insert_id;
    $ins->close();

    if ($payment_id <= 0) {
        throw new Exception('Failed to create payment record');
    }

    // 2. Mark all selected entries as settled — but only rows that still belong
    //    to this stakeholder and are still unsettled. This closes the TOCTOU gap
    //    between the verify SELECT above and this write: if a concurrent request
    //    settled any of them first, affected_rows won't match and we roll back.
    $updatePlaceholders = implode(',', array_fill(0, count($entry_ids), '?'));
    $updateSQL = "UPDATE project_work_entries SET slfa_payment_id = ?
                  WHERE id IN ($updatePlaceholders)
                    AND stakeholder_id = ?
                    AND (slfa_payment_id IS NULL OR slfa_payment_id = 0)";
    $updStmt   = $conn->prepare($updateSQL);
    $updTypes  = 'i' . str_repeat('i', count($entry_ids)) . 'i';
    $updArgs   = array_merge([$payment_id], $entry_ids, [$stakeholder_id]);
    $updRefs   = [&$updTypes];
    foreach ($updArgs as $i => $v) {
        $updRefs[] = &$updArgs[$i];
    }
    call_user_func_array([$updStmt, 'bind_param'], $updRefs);
    $updStmt->execute();
    $affected = $updStmt->affected_rows;
    $updStmt->close();

    if ($affected !== $entry_count) {
        throw new Exception('Some entries were already settled by another request');
    }

    $conn->commit();

    echo json_encode([
        'success'    => true,
        'payment_id' => $payment_id,
        'message'    => 'Slfa payment created successfully. ' . $entry_count . ' entries settled.',
        'entry_count' => $entry_count,
        'total_work_value' => $total_work_value,
        'cash_amount' => $cash_amount,
        'apartment_amount' => $apartment_amount,
        'apartment_sqm' => $apartment_sqm,
    ]);

} catch (Exception $e) {
    $conn->rollback();
    error_log('save_slfa_payment failed: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Could not save the payment. Please try again.']);
}
