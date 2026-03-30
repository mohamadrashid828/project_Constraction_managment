<?php
session_start();
require_once '../config.php';

if (!isset($_SESSION['user_id'])) {
    echo '<tr><td colspan="7" class="no-data">Not authenticated</td></tr>';
    exit();
}

$apartment_id = intval($_GET['apartment_id'] ?? 0);
if (!$apartment_id) {
    echo '<tr><td colspan="7" class="no-data">Invalid apartment</td></tr>';
    exit();
}

$stmt = $conn->prepare("
    SELECT m.id, m.measurement_date, m.quantity, m.unit_price, m.total_price,
           m.status, m.notes, u.username AS engineer
    FROM measurements m
    LEFT JOIN users u ON m.measured_by = u.id
    WHERE m.apartment_id = ? AND m.work_type_id = 1
    ORDER BY m.measurement_date DESC, m.created_at DESC
");
$stmt->bind_param("i", $apartment_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo '<tr><td colspan="7" class="no-data">No entries recorded yet for this apartment</td></tr>';
    $stmt->close();
    exit();
}

while ($row = $result->fetch_assoc()) {
    $status  = htmlspecialchars($row['status'] ?? 'draft');
    $notes   = $row['notes'] ? htmlspecialchars($row['notes']) : '—';
    $engineer = htmlspecialchars($row['engineer'] ?? '—');

    echo '<tr id="gechkari-row-' . $row['id'] . '">';
    echo '<td>' . date('d/m/Y', strtotime($row['measurement_date'])) . '</td>';
    echo '<td>' . htmlspecialchars($row['quantity']) . ' m²</td>';
    echo '<td>' . ($row['unit_price'] ? '$' . number_format($row['unit_price'], 2) : '—') . '</td>';
    echo '<td>' . ($row['total_price'] ? '$' . number_format($row['total_price'], 2) : '—') . '</td>';
    echo '<td><span class="status-badge status-' . $status . '">' . ucfirst($status) . '</span></td>';
    echo '<td>' . $engineer . '</td>';
    echo '<td class="notes-cell" title="' . $notes . '">' . $notes . '</td>';
    echo '</tr>';
}

$stmt->close();
?>
