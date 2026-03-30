<?php
session_start();
require_once '../config.php';

header('Content-Type: application/json');

if (empty($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$stmt = $conn->prepare('SELECT p.name FROM permissions p JOIN role_permissions rp ON p.id = rp.permission_id JOIN users u ON rp.role_id = u.role_id WHERE u.id = ?');
$stmt->bind_param('i', $user_id);
$stmt->execute();
$res = $stmt->get_result();
$permissions = [];
while ($row = $res->fetch_assoc()) {
    $permissions[] = $row['name'];
}
$stmt->close();

if (!in_array('data_entry', $permissions, true) && !in_array('project_settings', $permissions, true)) {
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
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

function normalize_work_type_key_report($key) {
    $k = strtolower(trim((string)$key));
    $k = preg_replace('/[^a-z0-9_]+/', '_', $k);
    $k = preg_replace('/_+/', '_', $k);
    return trim($k, '_');
}

function parse_gechkari_report_meta($notes) {
    $raw = trim((string)$notes);
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

$breakdown = trim((string)($_GET['breakdown'] ?? 'category'));
$allowedBreakdowns = ['category', 'building', 'floor', 'apartment', 'stakeholder'];
if (!in_array($breakdown, $allowedBreakdowns, true)) {
    $breakdown = 'category';
}

$filterWorkType = normalize_work_type_key_report($_GET['work_type_key'] ?? '');
$filterBuildingId = (int)($_GET['building_id'] ?? 0);
$filterFloorId = (int)($_GET['floor_id'] ?? 0);
$filterApartmentId = (int)($_GET['apartment_id'] ?? 0);
$dateFrom = trim((string)($_GET['date_from'] ?? ''));
$dateTo = trim((string)($_GET['date_to'] ?? ''));

$entries = [];

$sql = "SELECT 'gechkari' AS source, m.id, m.measurement_date AS entry_date,
        'gechkari' AS work_type_key, 'Gechkari' AS work_type_name,
        NULL AS stakeholder_id, '' AS stakeholder_name, '' AS subpart_name,
        m.quantity, m.unit_price, m.total_price, 'm²' AS metric_type, 'USD' AS currency_type,
        m.building_id, COALESCE(b.building_name, '—') AS building_name,
        m.floor_id, COALESCE(f.floor_name, '—') AS floor_name,
        m.apartment_id, COALESCE(a.apartment_number, '') AS apartment_number,
        COALESCE(u.username, '') AS engineer_name,
        m.notes, m.created_at
    FROM measurements m
    LEFT JOIN buildings b ON b.id = m.building_id
    LEFT JOIN floors f ON f.id = m.floor_id
    LEFT JOIN apartments a ON a.id = m.apartment_id
    LEFT JOIN users u ON u.id = m.measured_by
    WHERE m.work_type_id = 1
    UNION ALL
    SELECT 'generic' AS source, e.id, e.work_date AS entry_date,
        e.work_type_key, COALESCE(pwt.work_type_name, e.work_type_key) AS work_type_name,
        e.stakeholder_id, COALESCE(ps.stakeholder_name, '') AS stakeholder_name, COALESCE(sp.subpart_name, '') AS subpart_name,
        e.quantity, e.unit_price, e.total_price, e.metric_type, e.currency_type,
        e.building_id, COALESCE(b.building_name, '—') AS building_name,
        e.floor_id, COALESCE(f.floor_name, '—') AS floor_name,
        e.apartment_id, COALESCE(a.apartment_number, '') AS apartment_number,
        e.engineer_name,
        e.notes, e.created_at
    FROM project_work_entries e
    LEFT JOIN project_work_types pwt ON pwt.work_type_key = e.work_type_key
    LEFT JOIN project_stakeholders ps ON ps.id = e.stakeholder_id
    LEFT JOIN project_stakeholder_subparts sp ON sp.id = e.subpart_id
    LEFT JOIN buildings b ON b.id = e.building_id
    LEFT JOIN floors f ON f.id = e.floor_id
    LEFT JOIN apartments a ON a.id = e.apartment_id";

$result = $conn->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $row['work_type_key'] = normalize_work_type_key_report($row['work_type_key'] ?? '');
        if ($row['source'] === 'gechkari') {
            $meta = parse_gechkari_report_meta($row['notes'] ?? '');
            if ($meta['engineer'] !== '') {
                $row['engineer_name'] = $meta['engineer'];
            }
            if ($meta['stakeholder'] !== '') {
                $row['stakeholder_name'] = $meta['stakeholder'];
            }
            if ($meta['subpart'] !== '') {
                $row['subpart_name'] = $meta['subpart'];
            }
            $row['metric_type'] = $meta['metric'] !== '' ? $meta['metric'] : 'm²';
            $row['currency_type'] = $meta['currency'] !== '' ? $meta['currency'] : 'USD';
            $row['notes'] = $meta['notes'];
        }

        if ($filterWorkType !== '' && $row['work_type_key'] !== $filterWorkType) {
            continue;
        }
        if ($filterBuildingId > 0 && (int)$row['building_id'] !== $filterBuildingId) {
            continue;
        }
        if ($filterFloorId > 0 && (int)$row['floor_id'] !== $filterFloorId) {
            continue;
        }
        if ($filterApartmentId > 0 && (int)$row['apartment_id'] !== $filterApartmentId) {
            continue;
        }
        if ($dateFrom !== '' && (string)$row['entry_date'] < $dateFrom) {
            continue;
        }
        if ($dateTo !== '' && (string)$row['entry_date'] > $dateTo) {
            continue;
        }

        $entries[] = $row;
    }
}

usort($entries, function ($a, $b) {
    $dateCompare = strcmp((string)$b['entry_date'], (string)$a['entry_date']);
    if ($dateCompare !== 0) {
        return $dateCompare;
    }
    return strcmp((string)($b['created_at'] ?? ''), (string)($a['created_at'] ?? ''));
});

$groupsMap = [];
$totalQuantity = 0.0;
$totalValue = 0.0;

foreach ($entries as $entry) {
    $quantity = (float)($entry['quantity'] ?? 0);
    $value = (float)($entry['total_price'] ?? 0);
    $totalQuantity += $quantity;
    $totalValue += $value;

    switch ($breakdown) {
        case 'building':
            $groupLabel = trim((string)$entry['building_name']) !== '' ? (string)$entry['building_name'] : '—';
            break;
        case 'floor':
            $groupLabel = trim((string)$entry['building_name']) . ' / ' . trim((string)$entry['floor_name']);
            break;
        case 'apartment':
            $apartmentLabel = (int)$entry['apartment_id'] > 0 ? ('Apt ' . (string)$entry['apartment_number']) : 'Common Area';
            $groupLabel = trim((string)$entry['building_name']) . ' / ' . trim((string)$entry['floor_name']) . ' / ' . $apartmentLabel;
            break;
        case 'stakeholder':
            $groupLabel = trim((string)$entry['stakeholder_name']) !== '' ? (string)$entry['stakeholder_name'] : 'No Stakeholder';
            break;
        case 'category':
        default:
            $groupLabel = trim((string)$entry['work_type_name']) !== '' ? (string)$entry['work_type_name'] : ucfirst((string)$entry['work_type_key']);
            break;
    }

    if (!isset($groupsMap[$groupLabel])) {
        $groupsMap[$groupLabel] = [
            'label' => $groupLabel,
            'entries_count' => 0,
            'total_quantity' => 0.0,
            'total_value' => 0.0,
            'primary_metric' => (string)($entry['metric_type'] ?? 'unit'),
        ];
    }

    $groupsMap[$groupLabel]['entries_count']++;
    $groupsMap[$groupLabel]['total_quantity'] += $quantity;
    $groupsMap[$groupLabel]['total_value'] += $value;
}

$groups = array_values($groupsMap);
usort($groups, function ($a, $b) {
    return ($b['total_value'] <=> $a['total_value']);
});

$details = [];
foreach (array_slice($entries, 0, 100) as $entry) {
    $details[] = [
        'entry_date_display' => date('d/m/Y', strtotime((string)$entry['entry_date'])),
        'work_type_name' => trim((string)$entry['work_type_name']) !== '' ? (string)$entry['work_type_name'] : ucfirst((string)$entry['work_type_key']),
        'building_name' => trim((string)$entry['building_name']) !== '' ? (string)$entry['building_name'] : '—',
        'floor_name' => trim((string)$entry['floor_name']) !== '' ? (string)$entry['floor_name'] : '—',
        'apartment_label' => (int)$entry['apartment_id'] > 0 ? ('Apt ' . (string)$entry['apartment_number']) : 'Common Area',
        'stakeholder_name' => trim((string)$entry['stakeholder_name']) !== '' ? (string)$entry['stakeholder_name'] : '—',
        'quantity' => (float)($entry['quantity'] ?? 0),
        'metric_type' => (string)($entry['metric_type'] ?? 'unit'),
        'total_price' => (float)($entry['total_price'] ?? 0),
        'currency_type' => (string)($entry['currency_type'] ?? 'USD'),
    ];
}

echo json_encode([
    'success' => true,
    'summary' => [
        'entries_count' => count($entries),
        'groups_count' => count($groups),
        'total_quantity' => round($totalQuantity, 2),
        'total_value' => round($totalValue, 2),
    ],
    'groups' => array_map(function ($group) {
        $group['total_quantity'] = round((float)$group['total_quantity'], 2);
        $group['total_value'] = round((float)$group['total_value'], 2);
        return $group;
    }, $groups),
    'details' => $details,
]);
