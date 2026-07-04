<?php
session_start();
require_once '../config.php';
require_once 'includes/permissions.php';
require_once 'includes/csrf.php';

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

if (!in_array('data_entry', get_user_permissions($conn, (int)$_SESSION['user_id']), true)) {
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit();
}

$conn->query("CREATE TABLE IF NOT EXISTS project_work_entries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    work_date DATE NOT NULL,
    engineer_name VARCHAR(180) NOT NULL,
    work_type_key VARCHAR(80) NOT NULL,
    stakeholder_id INT NULL,
    subpart_id INT NULL,
    quantity DECIMAL(12,2) NOT NULL DEFAULT 0,
    unit_price DECIMAL(12,2) NOT NULL DEFAULT 0,
    total_price DECIMAL(14,2) NOT NULL DEFAULT 0,
    metric_type VARCHAR(30) NOT NULL DEFAULT 'unit',
    currency_type VARCHAR(20) NOT NULL DEFAULT 'USD',
    building_id INT NOT NULL,
    floor_id INT NOT NULL,
    apartment_id INT NOT NULL,
    notes TEXT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'draft',
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_work_type_key (work_type_key),
    INDEX idx_apartment (apartment_id),
    INDEX idx_work_date (work_date)
)");

$colQuantity = $conn->query("SHOW COLUMNS FROM project_work_entries LIKE 'quantity'");
if ($colQuantity && $colQuantity->num_rows === 0) {
    $conn->query("ALTER TABLE project_work_entries ADD COLUMN quantity DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER subpart_id");
}

$colUnitPrice = $conn->query("SHOW COLUMNS FROM project_work_entries LIKE 'unit_price'");
if ($colUnitPrice && $colUnitPrice->num_rows === 0) {
    $conn->query("ALTER TABLE project_work_entries ADD COLUMN unit_price DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER quantity");
}

$colTotalPrice = $conn->query("SHOW COLUMNS FROM project_work_entries LIKE 'total_price'");
if ($colTotalPrice && $colTotalPrice->num_rows === 0) {
    $conn->query("ALTER TABLE project_work_entries ADD COLUMN total_price DECIMAL(14,2) NOT NULL DEFAULT 0 AFTER unit_price");
}

$colMetricType = $conn->query("SHOW COLUMNS FROM project_work_entries LIKE 'metric_type'");
if ($colMetricType && $colMetricType->num_rows === 0) {
    $conn->query("ALTER TABLE project_work_entries ADD COLUMN metric_type VARCHAR(30) NOT NULL DEFAULT 'unit' AFTER total_price");
}

$colCurrencyType = $conn->query("SHOW COLUMNS FROM project_work_entries LIKE 'currency_type'");
if ($colCurrencyType && $colCurrencyType->num_rows === 0) {
    $conn->query("ALTER TABLE project_work_entries ADD COLUMN currency_type VARCHAR(20) NOT NULL DEFAULT 'USD' AFTER metric_type");
}

$work_date = trim($_POST['work_date'] ?? '');
$engineer_name = trim($_POST['engineer_name'] ?? '');
$work_type_key = strtolower(trim($_POST['work_type_key'] ?? ''));
$stakeholder_id = isset($_POST['stakeholder_id']) && $_POST['stakeholder_id'] !== '' ? (int)$_POST['stakeholder_id'] : null;
$subpart_id = isset($_POST['subpart_id']) && $_POST['subpart_id'] !== '' ? (int)$_POST['subpart_id'] : null;
$quantity = (float)($_POST['quantity'] ?? 0);
$unit_price = (float)($_POST['unit_price'] ?? 0);
$metric_type = trim($_POST['metric_type'] ?? 'unit');
$currency_type = trim($_POST['currency_type'] ?? 'USD');
$building_id = (int)($_POST['building_id'] ?? 0);
$floor_id = (int)($_POST['floor_id'] ?? 0);
$apartment_id = (int)($_POST['apartment_id'] ?? 0);
$notes = trim($_POST['notes'] ?? '');
$status = strtolower(trim($_POST['status'] ?? 'medium'));
$created_by = (int)$_SESSION['user_id'];

if (!in_array($status, ['accepted', 'rejected', 'medium'], true)) {
    $status = 'medium';
}

$stakeholder_id = $stakeholder_id ?: 0;
$subpart_id = $subpart_id ?: 0;

if ($metric_type === '') {
    $metric_type = 'unit';
}
if ($currency_type === '') {
    $currency_type = 'USD';
}

// total_price is always derived server-side from the validated quantity and
// unit_price; a client-supplied total is never trusted (financial integrity).
$total_price = $quantity * $unit_price;

if ($work_date === '' || $engineer_name === '' || $work_type_key === '' || $building_id <= 0 || $floor_id <= 0 || $apartment_id < 0) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit();
}

if ($subpart_id <= 0 || $quantity <= 0) {
    echo json_encode(['success' => false, 'message' => 'Please select sub work and enter finished quantity']);
    exit();
}

$stmt = $conn->prepare("INSERT INTO project_work_entries
    (work_date, engineer_name, work_type_key, stakeholder_id, subpart_id, quantity, unit_price, total_price, metric_type, currency_type, building_id, floor_id, apartment_id, notes, status, created_by)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

$stmt->bind_param(
    'sssiidddssiiissi',
    $work_date,
    $engineer_name,
    $work_type_key,
    $stakeholder_id,
    $subpart_id,
    $quantity,
    $unit_price,
    $total_price,
    $metric_type,
    $currency_type,
    $building_id,
    $floor_id,
    $apartment_id,
    $notes,
    $status,
    $created_by
);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Entry saved successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
}

$stmt->close();
