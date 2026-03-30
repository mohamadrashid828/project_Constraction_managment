<?php
session_start();
require_once '../config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

$apartment_id = intval($_GET['apartment_id'] ?? 0);
if (!$apartment_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid apartment ID']);
    exit();
}

// Get apartment details
$stmt = $conn->prepare("
    SELECT a.apartment_number, a.apartment_type, a.area_sqm,
           b.building_name, f.floor_name
    FROM apartments a
    JOIN floors    f ON a.floor_id    = f.id
    JOIN buildings b ON f.building_id = b.id
    WHERE a.id = ?
");
$stmt->bind_param("i", $apartment_id);
$stmt->execute();
$apt = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$apt) {
    echo json_encode(['success' => false, 'message' => 'Apartment not found']);
    exit();
}

// Get Gechkari aggregates for this apartment
$stmt = $conn->prepare("
    SELECT
        COALESCE(SUM(quantity),    0) AS total_quantity,
        COALESCE(SUM(total_price), 0) AS total_price,
        COUNT(*)                      AS visits
    FROM measurements
    WHERE apartment_id = ? AND work_type_id = 1
");
$stmt->bind_param("i", $apartment_id);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();
$stmt->close();

$area = floatval($apt['area_sqm'] ?: 1);
$qty  = floatval($stats['total_quantity']);
$pct  = $area > 0 ? min(100, round(($qty / $area) * 100, 1)) : 0;

echo json_encode([
    'success' => true,
    'data'    => [
        'apartment_number' => $apt['apartment_number'],
        'apartment_type'   => $apt['apartment_type'],
        'area_sqm'         => $apt['area_sqm'],
        'building_name'    => $apt['building_name'],
        'floor_name'       => $apt['floor_name'],
        'total_quantity'   => $stats['total_quantity'],
        'total_price'      => $stats['total_price'],
        'visits'           => $stats['visits'],
        'progress_pct'     => $pct,
    ]
]);
?>
