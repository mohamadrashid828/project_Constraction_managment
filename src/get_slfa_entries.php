<?php
session_start();
require_once '../config.php';

header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
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

$mode            = trim($_GET['mode'] ?? 'entries');
$stakeholder_id  = (int)($_GET['stakeholder_id'] ?? 0);

if ($stakeholder_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid stakeholder ID']);
    exit;
}

// ── MODE: history ─────────────────────────────────────────────────────────
if ($mode === 'history') {
    $payments = [];
    $stmt = $conn->prepare("
        SELECT id, payment_date, total_work_value, cash_percentage, cash_amount,
               apartment_percentage, apartment_amount, apartment_sqm,
               apartment_meter_price, entry_count, notes, created_at
        FROM slfa_payments
        WHERE stakeholder_id = ?
        ORDER BY payment_date DESC, created_at DESC
    ");
    $stmt->bind_param('i', $stakeholder_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $row['payment_date_display'] = date('d/m/Y', strtotime((string)$row['payment_date']));
        $payments[] = $row;
    }
    $stmt->close();
    echo json_encode(['success' => true, 'payments' => $payments]);
    exit;
}

// ── MODE: stats ───────────────────────────────────────────────────────────
if ($mode === 'stats') {
    $stmt = $conn->prepare("
        SELECT
            s.id,
            s.stakeholder_name,
            s.work_type_key,
            s.cash_percentage,
            s.apartment_percentage,
            s.apartment_meter_price,
            COALESCE(wt.work_type_name, s.work_type_key) AS work_type_label,
            COUNT(e.id) AS total_entries,
            COALESCE(SUM(CASE WHEN e.status = 'accepted' AND (e.slfa_payment_id IS NULL OR e.slfa_payment_id = 0) THEN 1 ELSE 0 END), 0) AS accepted_count,
            COALESCE(SUM(CASE WHEN e.status = 'medium'   AND (e.slfa_payment_id IS NULL OR e.slfa_payment_id = 0) THEN 1 ELSE 0 END), 0) AS medium_count,
            COALESCE(SUM(CASE WHEN e.status = 'rejected' AND (e.slfa_payment_id IS NULL OR e.slfa_payment_id = 0) THEN 1 ELSE 0 END), 0) AS rejected_count,
            COALESCE(SUM(CASE WHEN e.status = 'accepted' THEN e.total_price ELSE 0 END), 0) AS accepted_value,
            COALESCE(SUM(CASE WHEN e.status = 'accepted' AND (e.slfa_payment_id IS NULL OR e.slfa_payment_id = 0) THEN e.total_price ELSE 0 END), 0) AS pending_value,
            COALESCE(SUM(CASE WHEN e.status = 'accepted' AND (e.slfa_payment_id IS NULL OR e.slfa_payment_id = 0) THEN 1 ELSE 0 END), 0) AS pending_count,
            (SELECT COUNT(*) FROM slfa_payments sp WHERE sp.stakeholder_id = s.id) AS payment_count,
            (SELECT COALESCE(SUM(sp2.apartment_sqm), 0) FROM slfa_payments sp2 WHERE sp2.stakeholder_id = s.id) AS total_slfa_sqm
        FROM project_stakeholders s
        LEFT JOIN project_work_types wt ON wt.work_type_key = s.work_type_key
        LEFT JOIN project_work_entries e ON e.stakeholder_id = s.id
        WHERE s.is_active = 1 AND s.id = ?
        GROUP BY s.id
        LIMIT 1
    ");
    $stmt->bind_param('i', $stakeholder_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $stats = $res->fetch_assoc();
    $stmt->close();

    if (!$stats) {
        echo json_encode(['success' => false, 'message' => 'Stakeholder not found']);
        exit;
    }
    echo json_encode(['success' => true, 'stats' => $stats]);
    exit;
}

// ── MODE: payment_detail ────────────────────────────────────────────────────
if ($mode === 'payment_detail') {
    $payment_id = (int)($_GET['payment_id'] ?? 0);
    if ($payment_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid payment ID']);
        exit;
    }

    // Fetch the payment record (must belong to this stakeholder)
    $pStmt = $conn->prepare("
        SELECT sp.*,
               u.username       AS created_by_name,
               u.full_name      AS created_by_fullname,
               s.stakeholder_name,
               COALESCE(wt.work_type_name, s.work_type_key) AS work_type_label
        FROM slfa_payments sp
        LEFT JOIN users u ON u.id = sp.created_by
        LEFT JOIN project_stakeholders s ON s.id = sp.stakeholder_id
        LEFT JOIN project_work_types wt ON wt.work_type_key = s.work_type_key
        WHERE sp.id = ? AND sp.stakeholder_id = ?
        LIMIT 1
    ");
    $pStmt->bind_param('ii', $payment_id, $stakeholder_id);
    $pStmt->execute();
    $payment = $pStmt->get_result()->fetch_assoc();
    $pStmt->close();

    if (!$payment) {
        echo json_encode(['success' => false, 'message' => 'Payment not found']);
        exit;
    }

    $payment['payment_date_display'] = date('d/m/Y', strtotime((string)$payment['payment_date']));
    $payment['created_at_display']   = date('d/m/Y H:i', strtotime((string)$payment['created_at']));

    // Fetch all work entries that were settled in this payment
    $eStmt = $conn->prepare("
        SELECT
            e.id,
            e.work_date,
            e.engineer_name,
            e.quantity,
            e.unit_price,
            e.total_price,
            e.metric_type,
            e.status,
            e.notes,
            e.created_at,
            e.previous_status,
            e.status_changed_at,
            COALESCE(sc_u.full_name, sc_u.username, 'Admin') AS status_changed_by_name,
            COALESCE(b.building_name, '—')    AS building_name,
            COALESCE(f.floor_name, '—')       AS floor_name,
            COALESCE(a.apartment_number, '—') AS apartment_number,
            COALESCE(sp.subpart_name, e.work_type_key) AS subpart_label,
            COALESCE(wt.work_type_name, e.work_type_key) AS work_type_name
        FROM project_work_entries e
        LEFT JOIN buildings  b  ON b.id  = e.building_id
        LEFT JOIN floors     f  ON f.id  = e.floor_id
        LEFT JOIN apartments a  ON a.id  = e.apartment_id
        LEFT JOIN project_stakeholder_subparts sp ON sp.id = e.subpart_id
        LEFT JOIN project_work_types wt ON wt.work_type_key = e.work_type_key
        LEFT JOIN users sc_u ON sc_u.id = e.status_changed_by
        WHERE e.slfa_payment_id = ?
        ORDER BY e.work_date ASC, e.created_at ASC
    ");
    $eStmt->bind_param('i', $payment_id);
    $eStmt->execute();
    $eRes = $eStmt->get_result();
    $entries = [];
    while ($row = $eRes->fetch_assoc()) {
        $row['entry_date_display']    = date('d/m/Y', strtotime((string)$row['work_date']));
        $row['submitted_at_display']  = date('d/m/Y H:i', strtotime((string)$row['created_at']));
        $row['status_changed_at_display'] = !empty($row['status_changed_at']) ? date('d/m/Y H:i', strtotime((string)$row['status_changed_at'])) : '';
        $row['location'] = $row['building_name'] . ' / ' . $row['floor_name'] . ' / Apt ' . $row['apartment_number'];
        $entries[] = $row;
    }
    $eStmt->close();

    echo json_encode(['success' => true, 'payment' => $payment, 'entries' => $entries]);
    exit;
}

// ── MODE: entries (default) ───────────────────────────────────────────────
$statusFilter  = trim($_GET['status']  ?? '');
$settledFilter = trim($_GET['settled'] ?? '');

// Build WHERE clauses
$whereClauses = ['e.stakeholder_id = ?'];
$bindTypes    = 'i';
$bindValues   = [$stakeholder_id];

$validStatuses = ['accepted', 'medium', 'rejected'];
if (in_array($statusFilter, $validStatuses, true)) {
    $whereClauses[] = 'e.status = ?';
    $bindTypes      .= 's';
    $bindValues[]   = $statusFilter;
}

if ($settledFilter === 'unsettled') {
    $whereClauses[] = '(e.slfa_payment_id IS NULL OR e.slfa_payment_id = 0)';
} elseif ($settledFilter === 'settled') {
    $whereClauses[] = '(e.slfa_payment_id IS NOT NULL AND e.slfa_payment_id > 0)';
}

$whereSQL = implode(' AND ', $whereClauses);

$sql = "
    SELECT
        e.id,
        e.work_date,
        e.engineer_name,
        e.work_type_key,
        e.quantity,
        e.unit_price,
        e.total_price,
        e.metric_type,
        e.currency_type,
        e.status,
        e.notes,
        e.slfa_payment_id,
        COALESCE(b.building_name, '—')    AS building_name,
        COALESCE(f.floor_name, '—')       AS floor_name,
        COALESCE(a.apartment_number, '—') AS apartment_number,
        COALESCE(sp.subpart_name, e.work_type_key) AS subpart_label,
        COALESCE(wt.work_type_name, e.work_type_key) AS work_type_name
    FROM project_work_entries e
    LEFT JOIN buildings  b  ON b.id  = e.building_id
    LEFT JOIN floors     f  ON f.id  = e.floor_id
    LEFT JOIN apartments a  ON a.id  = e.apartment_id
    LEFT JOIN project_stakeholder_subparts sp ON sp.id = e.subpart_id
    LEFT JOIN project_work_types wt ON wt.work_type_key = e.work_type_key
    WHERE $whereSQL
    ORDER BY e.work_date DESC, e.created_at DESC
    LIMIT 300
";

$stmt = $conn->prepare($sql);
// Dynamic bind
$refs = [];
$refs[] = &$bindTypes;
foreach ($bindValues as $i => $v) {
    $refs[] = &$bindValues[$i];
}
call_user_func_array([$stmt, 'bind_param'], $refs);
$stmt->execute();
$res = $stmt->get_result();

$entries = [];
while ($row = $res->fetch_assoc()) {
    $row['entry_date_display'] = date('d/m/Y', strtotime((string)$row['work_date']));
    $row['location']           = trim(
        htmlspecialchars_decode($row['building_name'])
        . ' / ' . htmlspecialchars_decode($row['floor_name'])
        . ' / Apt ' . htmlspecialchars_decode($row['apartment_number'])
    );
    $entries[] = $row;
}
$stmt->close();

echo json_encode(['success' => true, 'entries' => $entries]);
exit;
