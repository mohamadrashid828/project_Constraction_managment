<?php
session_start();
require_once '../config.php';
require_once 'includes/permissions.php';

// Identifies the "Gechkari" row in work_types; kept as a named constant here
// (and in gechkari_ajax.php / get_gechkari_stats.php) instead of a bare
// literal scattered across the three endpoints.
define('WORK_TYPE_GECHKARI', 1);

if (!isset($_SESSION['user_id'])) {
    echo '<tr><td colspan="7" class="no-data">Not authenticated</td></tr>';
    exit();
}

$user_id = $_SESSION['user_id'];
if (!in_array('data_entry', get_user_permissions($conn, $user_id), true)) {
    echo '<tr><td colspan="7" class="no-data">Access denied</td></tr>';
    exit();
}

$apartment_id = intval($_GET['apartment_id'] ?? 0);
if (!$apartment_id) {
    echo '<tr><td colspan="7" class="no-data">Invalid apartment</td></tr>';
    exit();
}

$work_type_id = WORK_TYPE_GECHKARI;

$stmt = $conn->prepare("
    SELECT m.id, m.measurement_date, m.quantity, m.unit_price, m.total_price,
           m.status, m.notes, u.username AS engineer
    FROM measurements m
    LEFT JOIN users u ON m.measured_by = u.id
    WHERE m.apartment_id = ? AND m.work_type_id = ?
    ORDER BY m.measurement_date DESC, m.created_at DESC
");
$stmt->bind_param("ii", $apartment_id, $work_type_id);
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
