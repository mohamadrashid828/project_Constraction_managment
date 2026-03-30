<?php
session_start();
require_once '../config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
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

$total_price = $quantity * $unit_price;

$stmt = $conn->prepare("
    INSERT INTO measurements
        (work_type_id, building_id, floor_id, apartment_id, quantity, unit_price, total_price,
         measurement_date, measured_by, notes, is_general_measurement, measurement_type, status)
    VALUES (1, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 'specific', ?)
");
$stmt->bind_param("iiidddsiss",
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
