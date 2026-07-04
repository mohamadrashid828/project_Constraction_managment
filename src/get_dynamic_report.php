<?php
session_start();
require_once '../config.php';
require_once 'includes/permissions.php';

header('Content-Type: application/json');

if (empty($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$permissions = get_user_permissions($conn, $user_id);

if (!in_array('analytics', $permissions, true)) {
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

function normalize_work_type_key_report($key) {
    $k = strtolower(trim((string)$key));
    $k = preg_replace('/[^a-z0-9_]+/', '_', $k);
    $k = preg_replace('/_+/', '_', $k);
    return trim($k, '_');
}

// The "Gechkari" quick-entry feature stores into the older `measurements`
// table (work_type_id = 1) rather than `project_work_entries`; every report
// here treats both as one "work entries" concept so the two-table split is
// an implementation detail the user never sees.
function normalize_status_report($raw) {
    $s = strtolower(trim((string)$raw));
    if ($s === 'approved') { return 'accepted'; } // measurements uses 'approved', project_work_entries uses 'accepted'
    if (in_array($s, ['accepted', 'medium', 'draft', 'rejected'], true)) { return $s; }
    return $s !== '' ? $s : 'draft';
}

function status_report_label($status) {
    $map = [
        'accepted' => 'Approved',
        'medium'   => 'Under Review',
        'draft'    => 'Waiting',
        'rejected' => 'Rejected',
    ];
    return $map[$status] ?? ucfirst($status);
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
$filterStatus = normalize_status_report($_GET['status'] ?? '');
if (($_GET['status'] ?? '') === '') {
    $filterStatus = ''; // '' means "all statuses", not the normalize() fallback of 'draft'
}
$dateFrom = trim((string)($_GET['date_from'] ?? ''));
$dateTo = trim((string)($_GET['date_to'] ?? ''));

$entries = [];

$sql = "SELECT 'gechkari' AS source, m.id, m.measurement_date AS entry_date,
        'gechkari' AS work_type_key, 'Gechkari' AS work_type_name,
        NULL AS stakeholder_id, '' AS stakeholder_name, '' AS subpart_name,
        m.quantity, m.unit_price, m.total_price, 'm²' AS metric_type, 'USD' AS currency_type,
        m.status,
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
        e.status,
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
        $row['status'] = normalize_status_report($row['status'] ?? '');
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
        $row['currency_type'] = $row['currency_type'] ?: 'IQD';

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
        if ($filterStatus !== '' && $row['status'] !== $filterStatus) {
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

// Values are kept split by currency everywhere below — IQD and USD entries
// are never added together, since that number would be meaningless.
$groupsMap = [];
$totalQuantity = 0.0;
$totalValueByCurrency = [];

foreach ($entries as $entry) {
    $quantity = (float)($entry['quantity'] ?? 0);
    $value = (float)($entry['total_price'] ?? 0);
    $currency = (string)$entry['currency_type'];
    $totalQuantity += $quantity;
    $totalValueByCurrency[$currency] = ($totalValueByCurrency[$currency] ?? 0) + $value;

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
            'total_value_by_currency' => [],
            'primary_metric' => (string)($entry['metric_type'] ?? 'unit'),
        ];
    }

    $groupsMap[$groupLabel]['entries_count']++;
    $groupsMap[$groupLabel]['total_quantity'] += $quantity;
    $groupsMap[$groupLabel]['total_value_by_currency'][$currency] =
        ($groupsMap[$groupLabel]['total_value_by_currency'][$currency] ?? 0) + $value;
}

// Rank groups by their value in whichever currency has the largest overall
// total (the "dominant" currency for this filtered result set).
$dominantCurrency = 'IQD';
$bestTotal = -1;
foreach ($totalValueByCurrency as $cur => $v) {
    if ($v > $bestTotal) { $bestTotal = $v; $dominantCurrency = $cur; }
}

$groups = array_values($groupsMap);
usort($groups, function ($a, $b) use ($dominantCurrency) {
    $av = $a['total_value_by_currency'][$dominantCurrency] ?? 0;
    $bv = $b['total_value_by_currency'][$dominantCurrency] ?? 0;
    return $bv <=> $av;
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
        'currency_type' => (string)$entry['currency_type'],
        'status' => (string)$entry['status'],
        'status_label' => status_report_label($entry['status']),
    ];
}

echo json_encode([
    'success' => true,
    'summary' => [
        'entries_count' => count($entries),
        'groups_count' => count($groups),
        'total_quantity' => round($totalQuantity, 2),
        'total_value_by_currency' => array_map(fn($v) => round($v, 2), $totalValueByCurrency),
        'dominant_currency' => $dominantCurrency,
    ],
    'groups' => array_map(function ($group) {
        $group['total_quantity'] = round((float)$group['total_quantity'], 2);
        $group['total_value_by_currency'] = array_map(fn($v) => round($v, 2), $group['total_value_by_currency']);
        return $group;
    }, $groups),
    'details' => $details,
]);
