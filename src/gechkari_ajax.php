<?php
session_start();
require_once '../config.php';
require_once 'includes/permissions.php';
require_once 'includes/csrf.php';

// Identifies the "Gechkari" row in work_types; kept as a named constant here
// (and in get_gechkari_history.php / get_gechkari_stats.php) instead of a
// bare literal scattered across the three endpoints.
define('WORK_TYPE_GECHKARI', 1);

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit();
}

$user_id = $_SESSION['user_id'];
if (!in_array('data_entry', get_user_permissions($conn, $user_id), true)) {
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit();
}

$apartment_id     = intval($_POST['apartment_id']     ?? 0);
$floor_id         = intval($_POST['floor_id']         ?? 0);
$building_id      = intval($_POST['building_id']      ?? 0);
$quantity         = floatval($_POST['quantity']        ?? 0);
$unit_price       = floatval($_POST['unit_price']      ?? 0);
$measurement_date = trim($_POST['measurement_date']    ?? date('Y-m-d'));
$notes            = trim($_POST['notes']               ?? '');
$status           = strtolower(trim($_POST['status']   ?? 'medium'));
$measured_by      = $_SESSION['user_id'];

if (!in_array($status, ['accepted', 'rejected', 'medium'], true)) {
    $status = 'medium';
}

if (!$apartment_id || !$floor_id || !$building_id || $quantity <= 0) {
    echo json_encode(['success' => false, 'message' => 'Missing or invalid required fields']);
    exit();
}

// Confirm apartment_id/floor_id/building_id actually form one consistent
// apartment -> floor -> building chain before trusting them in the INSERT.
$chk = $conn->prepare("
    SELECT a.id FROM apartments a
    JOIN floors f ON a.floor_id = f.id
    JOIN buildings b ON f.building_id = b.id
    WHERE a.id = ? AND f.id = ? AND b.id = ?
");
if (!$chk) {
    echo json_encode(['success' => false, 'message' => 'Server error']);
    exit();
}
$chk->bind_param("iii", $apartment_id, $floor_id, $building_id);
$chk->execute();
if ($chk->get_result()->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid location']);
    exit();
}
$chk->close();

$total_price = $quantity * $unit_price;
$work_type_id = WORK_TYPE_GECHKARI;

$stmt = $conn->prepare("
    INSERT INTO measurements
        (work_type_id, building_id, floor_id, apartment_id, quantity, unit_price, total_price,
         measurement_date, measured_by, notes, is_general_measurement, measurement_type, status)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 'specific', ?)
");
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Server error']);
    exit();
}
$stmt->bind_param("iiiidddsiss",
    $work_type_id,
    $building_id,
    $floor_id,
    $apartment_id,
    $quantity,
    $unit_price,
    $total_price,
    $measurement_date,
    $measured_by,
    $notes,
    $status
);
$stmt->execute();

if ($stmt->affected_rows > 0) {
    echo json_encode(['success' => true, 'message' => 'Entry saved successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
}

$stmt->close();
?>
