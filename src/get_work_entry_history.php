<?php
session_start();
require_once '../config.php';

if (!isset($_SESSION['user_id'])) {
    echo '<tr><td colspan="7" class="no-data">Not authenticated</td></tr>';
    exit();
}

$userId = (int)$_SESSION['user_id'];
$canManageHistory = false;

$permStmt = $conn->prepare("SELECT p.name FROM permissions p JOIN role_permissions rp ON p.id = rp.permission_id JOIN users u ON rp.role_id = u.role_id WHERE u.id = ?");
if ($permStmt) {
    $permStmt->bind_param('i', $userId);
    $permStmt->execute();
    $permRes = $permStmt->get_result();
    while ($permRes && ($permRow = $permRes->fetch_assoc())) {
        if (($permRow['name'] ?? '') === 'user_management') {
            $canManageHistory = true;
            break;
        }
    }
    $permStmt->close();
}

if (!$canManageHistory && !empty($_SESSION['role_id'])) {
    $roleStmt = $conn->prepare("SELECT name FROM roles WHERE id = ? LIMIT 1");
    if ($roleStmt) {
        $roleId = (int)$_SESSION['role_id'];
        $roleStmt->bind_param('i', $roleId);
        $roleStmt->execute();
        $roleRes = $roleStmt->get_result();
        if ($roleRes && ($roleRow = $roleRes->fetch_assoc())) {
            $canManageHistory = strtolower(trim((string)$roleRow['name'])) === 'manager';
        }
        $roleStmt->close();
    }
}

$columnCount = $canManageHistory ? 8 : 7;

function normalize_history_work_type_key($key) {
    $k = strtolower(trim((string)$key));
    $k = preg_replace('/[^a-z0-9_]+/', '_', $k);
    $k = preg_replace('/_+/', '_', $k);
    return trim($k, '_');
}

function parse_gechkari_meta_and_notes($notes) {
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

function render_history_actions($source, $id, $entryDate, $engineer, $quantity, $unitPrice, $notes) {
    $attrs = [
        'data-entry-source' => $source,
        'data-entry-id' => (string)$id,
        'data-entry-date' => (string)$entryDate,
        'data-entry-engineer' => (string)$engineer,
        'data-entry-qty' => (string)$quantity,
        'data-entry-unit-price' => (string)$unitPrice,
        'data-entry-notes' => (string)$notes,
    ];

    $htmlAttrs = '';
    foreach ($attrs as $name => $value) {
        $htmlAttrs .= ' ' . $name . '="' . htmlspecialchars($value, ENT_QUOTES) . '"';
    }

    return '<td class="history-actions-cell">'
        . '<button type="button" class="history-action-btn edit-history-btn" title="Edit entry"' . $htmlAttrs . '><i class="fas fa-pen"></i></button>'
        . '<button type="button" class="history-action-btn delete-history-btn" title="Delete entry" data-entry-source="' . htmlspecialchars($source, ENT_QUOTES) . '" data-entry-id="' . (int)$id . '"><i class="fas fa-trash"></i></button>'
        . '</td>';
}

$apartment_id = (int)($_GET['apartment_id'] ?? 0);
$floor_id = (int)($_GET['floor_id'] ?? 0);
$building_id = (int)($_GET['building_id'] ?? 0);
$work_type_key = normalize_history_work_type_key($_GET['work_type_key'] ?? '');
if ($apartment_id < 0) {
    echo '<tr><td colspan="' . $columnCount . '" class="no-data">Invalid apartment</td></tr>';
    exit();
}
if ($work_type_key === '') {
    $work_type_key = 'gechkari';
}

$workTypeName = ucfirst($work_type_key);
$wtStmt = $conn->prepare("SELECT work_type_name FROM project_work_types WHERE work_type_key = ? LIMIT 1");
if ($wtStmt) {
    $wtStmt->bind_param('s', $work_type_key);
    $wtStmt->execute();
    $wtRes = $wtStmt->get_result();
    if ($wtRes && $wtRes->num_rows > 0) {
        $wtRow = $wtRes->fetch_assoc();
        if (!empty($wtRow['work_type_name'])) {
            $workTypeName = $wtRow['work_type_name'];
        }
    }
    $wtStmt->close();
}

if ($work_type_key === 'gechkari') {
    if ($apartment_id <= 0) {
        echo '<tr><td colspan="' . $columnCount . '" class="no-data">No Gechkari history for common area. Please select a specific apartment.</td></tr>';
        exit();
    }

    $stmt = $conn->prepare("SELECT m.id, m.measurement_date AS entry_date, m.notes, m.status,
            m.quantity, m.unit_price,
            COALESCE(u.username, '—') AS engineer,
            b.building_name, f.floor_name, a.apartment_number
        FROM measurements m
        LEFT JOIN users u ON m.measured_by = u.id
        LEFT JOIN buildings b ON m.building_id = b.id
        LEFT JOIN floors f ON m.floor_id = f.id
        LEFT JOIN apartments a ON m.apartment_id = a.id
        WHERE m.apartment_id = ? AND m.work_type_id = 1
        ORDER BY m.measurement_date DESC, m.created_at DESC");
    $stmt->bind_param('i', $apartment_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        echo '<tr><td colspan="' . $columnCount . '" class="no-data">No entries recorded yet for this apartment and work type</td></tr>';
        $stmt->close();
        exit();
    }

    while ($row = $result->fetch_assoc()) {
        $meta = parse_gechkari_meta_and_notes($row['notes'] ?? '');
        $subWork = $meta['subpart'] !== '' ? $meta['subpart'] : '—';
        $engineerFromNotes = $meta['engineer'];

        $location = htmlspecialchars(($row['building_name'] ?? '—') . ' / ' . ($row['floor_name'] ?? '—') . ' / Apt ' . ($row['apartment_number'] ?? '—'));
        $status = htmlspecialchars($row['status'] ?: 'draft');
        $engineer = htmlspecialchars($engineerFromNotes !== '' ? $engineerFromNotes : ($row['engineer'] ?? '—'));
        $notesEscaped = ($meta['notes'] ?? '') !== '' ? htmlspecialchars($meta['notes']) : '—';

        echo '<tr>';
        echo '<td>' . date('d/m/Y', strtotime($row['entry_date'])) . '</td>';
        echo '<td>' . htmlspecialchars($workTypeName) . '</td>';
        echo '<td>' . htmlspecialchars($subWork) . '</td>';
        echo '<td>' . $engineer . '</td>';
        echo '<td>' . $location . '</td>';
        if ($canManageHistory) {
            $gechStatusCell = '<button type="button" class="status-badge status-' . $status . ' history-sc-btn"'
                . ' onclick="openStatusChangeModal(' . (int)$row['id'] . ', \'gechkari\', \'' . $status . '\')"'
                . ' title="Click to change status">' . ucfirst($status)
                . ' <i class="fas fa-pen sc-pen-icon"></i></button>';
        } else {
            $gechStatusCell = '<span class="status-badge status-' . $status . '">' . ucfirst($status) . '</span>';
        }
        echo '<td>' . $gechStatusCell . '</td>';
        echo '<td class="notes-cell" title="' . $notesEscaped . '">' . $notesEscaped . '</td>';
        if ($canManageHistory) {
            echo render_history_actions('gechkari', (int)$row['id'], (string)$row['entry_date'], $engineerFromNotes !== '' ? $engineerFromNotes : ($row['engineer'] ?? ''), (float)$row['quantity'], (float)$row['unit_price'], (string)($meta['notes'] ?? ''));
        }
        echo '</tr>';
    }

    $stmt->close();
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

// Auto-migrate: add status tracking columns if they do not exist yet
$scColCheck = $conn->query("SHOW COLUMNS FROM project_work_entries LIKE 'status_changed_by'");
if ($scColCheck && $scColCheck->num_rows === 0) {
    $conn->query("ALTER TABLE project_work_entries
        ADD COLUMN status_changed_by INT NULL DEFAULT NULL,
        ADD COLUMN status_changed_at DATETIME NULL DEFAULT NULL,
        ADD COLUMN previous_status VARCHAR(30) NULL DEFAULT NULL");
}

$historySql = "SELECT e.id, e.work_date AS entry_date, e.engineer_name, e.notes, e.status,
        e.quantity, e.unit_price, e.total_price, e.metric_type, e.currency_type,
        e.status_changed_by, e.status_changed_at, e.previous_status,
        COALESCE(sc.full_name, sc.username, 'Admin') AS status_changed_by_name,
        b.building_name, f.floor_name, a.apartment_number,
        sp.subpart_name
    FROM project_work_entries e
    LEFT JOIN buildings b ON e.building_id = b.id
    LEFT JOIN floors f ON e.floor_id = f.id
    LEFT JOIN apartments a ON e.apartment_id = a.id
    LEFT JOIN project_stakeholder_subparts sp ON e.subpart_id = sp.id
    LEFT JOIN users sc ON sc.id = e.status_changed_by
    WHERE e.work_type_key = ?";

if ($apartment_id > 0) {
    $historySql .= " AND e.apartment_id = ?";
} else {
    $historySql .= " AND e.apartment_id = 0";
    if ($floor_id > 0) {
        $historySql .= " AND e.floor_id = ?";
    }
    if ($building_id > 0) {
        $historySql .= " AND e.building_id = ?";
    }
}

$historySql .= " ORDER BY e.work_date DESC, e.created_at DESC";

$stmt = $conn->prepare($historySql);
if ($apartment_id > 0) {
    $stmt->bind_param('si', $work_type_key, $apartment_id);
} else {
    if ($floor_id > 0 && $building_id > 0) {
        $stmt->bind_param('sii', $work_type_key, $floor_id, $building_id);
    } elseif ($floor_id > 0) {
        $stmt->bind_param('si', $work_type_key, $floor_id);
    } elseif ($building_id > 0) {
        $stmt->bind_param('si', $work_type_key, $building_id);
    } else {
        $stmt->bind_param('s', $work_type_key);
    }
}
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo '<tr><td colspan="' . $columnCount . '" class="no-data">No entries recorded yet for this apartment and work type</td></tr>';
    $stmt->close();
    exit();
}

while ($row = $result->fetch_assoc()) {
    $subWork = htmlspecialchars($row['subpart_name'] ?: '—');
    $location = htmlspecialchars(($row['building_name'] ?? '—') . ' / ' . ($row['floor_name'] ?? '—') . ' / Apt ' . ($row['apartment_number'] ?? '—'));
    $status = htmlspecialchars($row['status'] ?: 'draft');
    $engineer = htmlspecialchars($row['engineer_name'] ?? '—');
    $metric = $row['metric_type'] ?: 'unit';
    $currency = $row['currency_type'] ?: 'USD';
    $quantity = number_format((float)$row['quantity'], 2);
    $totalPrice = number_format((float)$row['total_price'], 2);
    $summary = 'Qty: ' . $quantity . ' ' . $metric . ' | Total: ' . $currency . ' ' . $totalPrice;
    $notesRaw = trim((string)($row['notes'] ?? ''));
    $notes = htmlspecialchars($summary . ($notesRaw !== '' ? ' | ' . $notesRaw : ''));

    echo '<tr>';
    echo '<td>' . date('d/m/Y', strtotime($row['entry_date'])) . '</td>';
    echo '<td>' . htmlspecialchars($workTypeName) . '</td>';
    echo '<td>' . $subWork . '</td>';
    echo '<td>' . $engineer . '</td>';
    echo '<td>' . $location . '</td>';
    // Status cell: clickable button for managers, static badge otherwise
    if ($canManageHistory) {
        $statusCell = '<button type="button" class="status-badge status-' . $status . ' history-sc-btn"'
            . ' onclick="openStatusChangeModal(' . (int)$row['id'] . ', \'generic\', \'' . $status . '\')"'
            . ' title="Click to change status">' . ucfirst($status)
            . ' <i class="fas fa-pen sc-pen-icon"></i></button>';
    } else {
        $statusCell = '<span class="status-badge status-' . $status . '">' . ucfirst($status) . '</span>';
    }
    // Status-change history note
    $scNote = '';
    if (!empty($row['status_changed_at']) && !empty($row['previous_status'])) {
        $scChangedAt  = date('d/m/Y H:i', strtotime((string)$row['status_changed_at']));
        $scChangedBy  = htmlspecialchars((string)($row['status_changed_by_name'] ?? 'Admin'));
        $scPrevStatus = htmlspecialchars(ucfirst((string)$row['previous_status']));
        $scNote = '<div class="entry-sc-note"><i class="fas fa-history"></i> Was <em>' . $scPrevStatus . '</em> &rarr; changed by ' . $scChangedBy . ' &middot; ' . $scChangedAt . '</div>';
    }
    echo '<td>' . $statusCell . $scNote . '</td>';
    echo '<td class="notes-cell" title="' . $notes . '">' . $notes . '</td>';
    if ($canManageHistory) {
        echo render_history_actions('generic', (int)$row['id'], (string)$row['entry_date'], (string)($row['engineer_name'] ?? ''), (float)$row['quantity'], (float)$row['unit_price'], $notesRaw);
    }
    echo '</tr>';
}

$stmt->close();
