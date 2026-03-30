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

function current_user_can_manage_history(mysqli $conn): bool {
    $userId = (int)($_SESSION['user_id'] ?? 0);
    if ($userId <= 0) {
        return false;
    }

    $permStmt = $conn->prepare("SELECT p.name FROM permissions p JOIN role_permissions rp ON p.id = rp.permission_id JOIN users u ON rp.role_id = u.role_id WHERE u.id = ?");
    if ($permStmt) {
        $permStmt->bind_param('i', $userId);
        $permStmt->execute();
        $permRes = $permStmt->get_result();
        while ($permRes && ($permRow = $permRes->fetch_assoc())) {
            if (($permRow['name'] ?? '') === 'user_management') {
                $permStmt->close();
                return true;
            }
        }
        $permStmt->close();
    }

    $roleId = (int)($_SESSION['role_id'] ?? 0);
    if ($roleId > 0) {
        $roleStmt = $conn->prepare("SELECT name FROM roles WHERE id = ? LIMIT 1");
        if ($roleStmt) {
            $roleStmt->bind_param('i', $roleId);
            $roleStmt->execute();
            $roleRes = $roleStmt->get_result();
            if ($roleRes && ($roleRow = $roleRes->fetch_assoc())) {
                $roleStmt->close();
                return strtolower(trim((string)$roleRow['name'])) === 'manager';
            }
            $roleStmt->close();
        }
    }

    return false;
}

function parse_gechkari_meta_and_notes(string $notes): array {
    $raw = trim($notes);
    $meta = [
        'engineer' => '',
        'stakeholder' => '',
        'subpart' => '',
        'metric' => 'm²',
        'currency' => 'USD',
        'notes' => $raw,
    ];

    if (preg_match('/^\[(.*?)\]\s*(.*)$/s', $raw, $matches)) {
        $metaPart = trim($matches[1]);
        $meta['notes'] = trim($matches[2]);
        foreach (preg_split('/\s*\|\s*/', $metaPart) as $piece) {
            if (stripos($piece, 'Engineer:') === 0) {
                $meta['engineer'] = trim(substr($piece, strlen('Engineer:')));
            } elseif (stripos($piece, 'Stakeholder:') === 0) {
                $meta['stakeholder'] = trim(substr($piece, strlen('Stakeholder:')));
            } elseif (stripos($piece, 'Subpart:') === 0) {
                $meta['subpart'] = trim(substr($piece, strlen('Subpart:')));
            } elseif (stripos($piece, 'Metric:') === 0) {
                $meta['metric'] = trim(substr($piece, strlen('Metric:')));
            } elseif (stripos($piece, 'Currency:') === 0) {
                $meta['currency'] = trim(substr($piece, strlen('Currency:')));
            }
        }
    }

    return $meta;
}

function build_gechkari_notes(array $meta): string {
    $parts = [];
    $parts[] = 'Engineer: ' . ($meta['engineer'] ?? '');
    if (($meta['stakeholder'] ?? '') !== '') {
        $parts[] = 'Stakeholder: ' . $meta['stakeholder'];
    }
    if (($meta['subpart'] ?? '') !== '') {
        $parts[] = 'Subpart: ' . $meta['subpart'];
    }
    if (($meta['metric'] ?? '') !== '') {
        $parts[] = 'Metric: ' . $meta['metric'];
    }
    if (($meta['currency'] ?? '') !== '') {
        $parts[] = 'Currency: ' . $meta['currency'];
    }

    $notes = trim((string)($meta['notes'] ?? ''));
    return '[' . implode(' | ', $parts) . ']' . ($notes !== '' ? ' ' . $notes : '');
}

if (!current_user_can_manage_history($conn)) {
    echo json_encode(['success' => false, 'message' => 'Only managers can edit or delete history.']);
    exit();
}

$action = trim((string)($_POST['action'] ?? ''));
$source = trim((string)($_POST['source'] ?? ''));
$entryId = (int)($_POST['entry_id'] ?? 0);

if (!in_array($action, ['update', 'delete'], true) || !in_array($source, ['generic', 'gechkari'], true) || $entryId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid request data']);
    exit();
}

if ($action === 'delete') {
    if ($source === 'generic') {
        $stmt = $conn->prepare("DELETE FROM project_work_entries WHERE id = ?");
    } else {
        $stmt = $conn->prepare("DELETE FROM measurements WHERE id = ? AND work_type_id = 1");
    }

    $stmt->bind_param('i', $entryId);
    $ok = $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();

    echo json_encode([
        'success' => $ok && $affected > 0,
        'message' => $ok && $affected > 0 ? 'Entry deleted successfully.' : 'Unable to delete entry.'
    ]);
    exit();
}

$workDate = trim((string)($_POST['work_date'] ?? ''));
$engineerName = trim((string)($_POST['engineer_name'] ?? ''));
$quantity = (float)($_POST['quantity'] ?? 0);
$unitPrice = (float)($_POST['unit_price'] ?? 0);
$notes = trim((string)($_POST['notes'] ?? ''));

if ($workDate === '' || $engineerName === '' || $quantity <= 0 || $unitPrice < 0) {
    echo json_encode(['success' => false, 'message' => 'Please fill all required fields.']);
    exit();
}

$totalPrice = $quantity * $unitPrice;

if ($source === 'generic') {
    $stmt = $conn->prepare("UPDATE project_work_entries SET work_date = ?, engineer_name = ?, quantity = ?, unit_price = ?, total_price = ?, notes = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
    $stmt->bind_param('ssdddsi', $workDate, $engineerName, $quantity, $unitPrice, $totalPrice, $notes, $entryId);
    $ok = $stmt->execute();
    $stmt->close();

    echo json_encode([
        'success' => $ok,
        'message' => $ok ? 'Entry updated successfully.' : 'Unable to update entry.'
    ]);
    exit();
}

$stmt = $conn->prepare("SELECT notes FROM measurements WHERE id = ? AND work_type_id = 1 LIMIT 1");
$stmt->bind_param('i', $entryId);
$stmt->execute();
$res = $stmt->get_result();
if (!$res || !($row = $res->fetch_assoc())) {
    $stmt->close();
    echo json_encode(['success' => false, 'message' => 'Entry not found.']);
    exit();
}
$currentMeta = parse_gechkari_meta_and_notes((string)($row['notes'] ?? ''));
$stmt->close();

$currentMeta['engineer'] = $engineerName;
$currentMeta['notes'] = $notes;
$updatedNotes = build_gechkari_notes($currentMeta);

$stmt = $conn->prepare("UPDATE measurements SET measurement_date = ?, quantity = ?, unit_price = ?, total_price = ?, notes = ? WHERE id = ? AND work_type_id = 1");
$stmt->bind_param('sdddsi', $workDate, $quantity, $unitPrice, $totalPrice, $updatedNotes, $entryId);
$ok = $stmt->execute();
$stmt->close();

echo json_encode([
    'success' => $ok,
    'message' => $ok ? 'Entry updated successfully.' : 'Unable to update entry.'
]);
