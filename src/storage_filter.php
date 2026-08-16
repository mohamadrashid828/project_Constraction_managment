<?php
session_start();
require_once '../config.php';
require_once 'includes/i18n.php';
require_once 'includes/permissions.php';
require_once 'includes/inventory.php';

if (empty($_SESSION['user_id'])) {
    header('Content-Type: text/html; charset=utf-8');
    echo '<tr><td class="stg-empty">' . htmlspecialchars(t('not_authenticated', 'Not authenticated')) . '</td></tr>';
    exit;
}
$userPermissions = get_user_permissions($conn, (int) $_SESSION['user_id']);
if (!in_array('inventory.view', $userPermissions, true)) {
    header('Content-Type: text/html; charset=utf-8');
    echo '<tr><td class="stg-empty">' . htmlspecialchars(t('access_denied', 'Access denied')) . '</td></tr>';
    exit;
}

ensure_inventory_schema($conn);

function money($v) { return '$' . number_format((float) $v, 2); }
function qty_fmt($v) { return rtrim(rtrim(number_format((float) $v, 3, '.', ','), '0'), '.'); }
function esc($v) { return htmlspecialchars((string) $v); }

$section = $_GET['section'] ?? '';
$item_id = inventory_opt_int($_GET['item_id'] ?? 0);
$date_from = trim($_GET['date_from'] ?? '');
$date_to = trim($_GET['date_to'] ?? '');
$isExport = !empty($_GET['export']) && $section === 'history';

if ($isExport) {
    if (!in_array('inventory.export', $userPermissions, true)) {
        http_response_code(403);
        exit('Access denied');
    }
} else {
    header('Content-Type: text/html; charset=utf-8');
}

switch ($section) {

    // ── Stock Balance ───────────────────────────────────────────────────────
    case 'balance': {
        $q = trim($_GET['q'] ?? '');
        $stockStatus = trim($_GET['stock_status'] ?? '');
        $where = ['i.is_active = 1'];
        $params = [];
        $types = '';
        if ($q !== '') {
            $where[] = 'i.item_name LIKE ?';
            $params[] = '%' . $q . '%';
            $types .= 's';
        }
        $sql = "SELECT i.id, i.item_name, i.unit,
                       COALESCE(SUM(CASE WHEN m.movement_type = 'in' THEN m.quantity ELSE -m.quantity END), 0) AS balance,
                       (SELECT COALESCE(AVG(p.unit_price), 0) FROM inventory_purchases p WHERE p.item_id = i.id AND p.unit_price > 0) AS avg_price
                FROM inventory_items i
                LEFT JOIN inventory_movements m ON m.item_id = i.id
                WHERE " . implode(' AND ', $where) . "
                GROUP BY i.id
                ORDER BY i.item_name";
        $res = inventory_run_dynamic($conn, $sql, $types, $params);
        $rows = [];
        while ($res && $it = $res->fetch_assoc()) {
            if ($stockStatus !== '' && inventory_stock_status((float) $it['balance']) !== $stockStatus) {
                continue;
            }
            $rows[] = $it;
        }
        if (!$rows) {
            echo '<tr><td colspan="5" class="stg-empty">' . htmlspecialchars(t('no_items_match_filters', 'No items match these filters.')) . '</td></tr>';
            exit;
        }
        foreach ($rows as $it) {
            $bal = (float) $it['balance'];
            $val = $bal * (float) $it['avg_price'];
            $status = inventory_stock_status($bal);
            echo '<tr>';
            echo '<td class="stg-strong">' . esc($it['item_name']) . '</td>';
            echo '<td>' . esc($it['unit']) . '</td>';
            echo '<td class="num"><span class="stg-qty stg-qty-' . $status . '">' . qty_fmt($bal) . '</span>' . ($status !== 'ok' ? ' <span class="stg-stock-badge stg-stock-badge-' . $status . '">' . ($status === 'out' ? t('out_of_stock_label', 'Out of Stock') : t('low_stock_label', 'Low Stock')) . '</span>' : '') . '</td>';
            echo '<td class="num">' . money($it['avg_price']) . '</td>';
            echo '<td class="num">' . money($val) . '</td>';
            echo '</tr>';
        }
        break;
    }

    // ── Stock In (purchases) ───────────────────────────────────────────────
    case 'purchases': {
        $vendor = trim($_GET['vendor'] ?? '');
        $orderedBy = trim($_GET['ordered_by'] ?? '');
        $where = ['1=1'];
        $params = [];
        $types = '';
        inventory_push_date_range($where, $params, $types, 'p.purchase_date', $date_from, $date_to);
        if ($item_id) { $where[] = 'p.item_id = ?'; $params[] = $item_id; $types .= 'i'; }
        if ($vendor !== '') { $where[] = 'p.vendor LIKE ?'; $params[] = '%' . $vendor . '%'; $types .= 's'; }
        if ($orderedBy !== '') { $where[] = 'p.ordered_by_name LIKE ?'; $params[] = '%' . $orderedBy . '%'; $types .= 's'; }

        $sql = "SELECT p.*, i.item_name, i.unit
                FROM inventory_purchases p
                JOIN inventory_items i ON i.id = p.item_id
                WHERE " . implode(' AND ', $where) . "
                ORDER BY p.purchase_date DESC, p.id DESC
                LIMIT 200";
        $res = inventory_run_dynamic($conn, $sql, $types, $params);
        if (!$res || $res->num_rows === 0) {
            echo '<tr><td colspan="9" class="stg-empty">' . htmlspecialchars(t('no_purchases_match_filters', 'No purchases match these filters.')) . '</td></tr>';
            exit;
        }
        while ($p = $res->fetch_assoc()) {
            echo '<tr>';
            echo '<td>' . esc(date('d/m/Y', strtotime($p['purchase_date']))) . '</td>';
            echo '<td class="stg-strong">' . esc($p['item_name']) . '</td>';
            echo '<td class="num">' . qty_fmt($p['quantity']) . ' <span class="stg-unit">' . esc($p['unit']) . '</span></td>';
            echo '<td class="num">' . money($p['unit_price']) . '</td>';
            echo '<td class="num">' . money($p['total_price']) . '</td>';
            echo '<td>' . ($p['vendor'] ? esc($p['vendor']) : '<span class="loc-none">—</span>') . '</td>';
            echo '<td>' . ($p['ordered_by_name'] ? esc($p['ordered_by_name']) : '<span class="loc-none">—</span>') . '</td>';
            echo '<td>' . ($p['invoice_no'] ? esc($p['invoice_no']) : '<span class="loc-none">—</span>') . '</td>';
            echo '<td>' . (!empty($p['invoice_file'])
                ? '<a class="stg-icon-btn stg-view-invoice" href="storage_invoice.php?id=' . (int) $p['id'] . '" target="_blank" title="' . htmlspecialchars(t('view_invoice', 'View invoice')) . '"><i class="fas fa-file-pdf"></i></a>'
                : '<span class="loc-none">—</span>') . '</td>';
            echo '</tr>';
        }
        break;
    }

    // ── Stock Out (movements) ──────────────────────────────────────────────
    case 'movements': {
        $building_id = inventory_opt_int($_GET['building_id'] ?? 0);
        $takenBy = trim($_GET['taken_by'] ?? '');
        $where = ["m.movement_type = 'out'"];
        $params = [];
        $types = '';
        inventory_push_date_range($where, $params, $types, 'm.movement_date', $date_from, $date_to);
        if ($item_id) { $where[] = 'm.item_id = ?'; $params[] = $item_id; $types .= 'i'; }
        if ($building_id) { $where[] = 'm.building_id = ?'; $params[] = $building_id; $types .= 'i'; }
        if ($takenBy !== '') { $where[] = 'm.person_name LIKE ?'; $params[] = '%' . $takenBy . '%'; $types .= 's'; }

        $sql = "SELECT m.*, i.item_name, i.unit, b.building_name, f.floor_name, a.apartment_number
                FROM inventory_movements m
                JOIN inventory_items i ON i.id = m.item_id
                LEFT JOIN buildings b ON b.id = m.building_id
                LEFT JOIN floors f ON f.id = m.floor_id
                LEFT JOIN apartments a ON a.id = m.apartment_id
                WHERE " . implode(' AND ', $where) . "
                ORDER BY m.movement_date DESC, m.id DESC
                LIMIT 200";
        $res = inventory_run_dynamic($conn, $sql, $types, $params);
        if (!$res || $res->num_rows === 0) {
            echo '<tr><td colspan="6" class="stg-empty">' . htmlspecialchars(t('no_stock_out_entries', 'No stock-out entries match these filters.')) . '</td></tr>';
            exit;
        }
        while ($mv = $res->fetch_assoc()) {
            echo '<tr>';
            echo '<td>' . esc(date('d/m/Y', strtotime($mv['movement_date']))) . '</td>';
            echo '<td class="stg-strong">' . esc($mv['item_name']) . '</td>';
            echo '<td class="num">' . qty_fmt($mv['quantity']) . ' <span class="stg-unit">' . esc($mv['unit']) . '</span></td>';
            echo '<td>' . inventory_location_label($mv) . '</td>';
            echo '<td>' . ($mv['person_name'] ? esc($mv['person_name']) : '<span class="loc-none">—</span>') . '</td>';
            echo '<td>' . ($mv['notes'] ? esc($mv['notes']) : '<span class="loc-none">—</span>') . '</td>';
            echo '</tr>';
        }
        break;
    }

    // ── Purchase Requests ───────────────────────────────────────────────────
    case 'requests': {
        $itemSearch = trim($_GET['item'] ?? '');
        $requester_id = inventory_opt_int($_GET['requester_id'] ?? 0);
        $status = trim($_GET['status'] ?? '');
        $priority = trim($_GET['priority'] ?? '');
        $where = ['1=1'];
        $params = [];
        $types = '';
        inventory_push_date_range($where, $params, $types, 'r.created_at', $date_from, $date_to);
        if ($itemSearch !== '') { $where[] = 'r.item_name LIKE ?'; $params[] = '%' . $itemSearch . '%'; $types .= 's'; }
        if ($requester_id) { $where[] = 'r.requested_by = ?'; $params[] = $requester_id; $types .= 'i'; }
        if (in_array($status, ['pending', 'approved', 'rejected', 'fulfilled'], true)) {
            $where[] = 'r.status = ?'; $params[] = $status; $types .= 's';
        }
        if (in_array($priority, ['low', 'medium', 'high', 'urgent'], true)) {
            $where[] = 'r.priority = ?'; $params[] = $priority; $types .= 's';
        }

        $sql = "SELECT r.*, u.username AS requester
                FROM inventory_purchase_requests r
                LEFT JOIN users u ON u.id = r.requested_by
                WHERE " . implode(' AND ', $where) . "
                ORDER BY (r.status = 'pending') DESC, r.priority = 'urgent' DESC, r.created_at DESC
                LIMIT 200";
        $res = inventory_run_dynamic($conn, $sql, $types, $params);
        if (!$res || $res->num_rows === 0) {
            echo '<tr><td colspan="7" class="stg-empty">' . htmlspecialchars(t('no_requests_match_filters', 'No requests match these filters.')) . '</td></tr>';
            exit;
        }
        while ($rq = $res->fetch_assoc()) {
            $st = $rq['status'];
            echo '<tr>';
            echo '<td class="stg-strong">' . esc($rq['item_name']) . '</td>';
            echo '<td class="num">' . qty_fmt($rq['quantity']) . ' <span class="stg-unit">' . esc($rq['unit'] ?? '') . '</span></td>';
            echo '<td><span class="stg-priority stg-priority-' . esc($rq['priority']) . '">' . esc(inventory_priority_label($rq['priority'])) . '</span></td>';
            echo '<td>' . ($rq['needed_by_date'] ? esc(date('d/m/Y', strtotime($rq['needed_by_date']))) : '<span class="loc-none">—</span>') . '</td>';
            echo '<td>' . ($rq['requester'] ? esc($rq['requester']) : '<span class="loc-none">—</span>') . '</td>';
            echo '<td><span class="stg-status stg-status-' . esc($st) . '">' . esc(inventory_request_status_label($st)) . '</span></td>';
            echo '<td>';
            if ($st === 'pending') {
                if (in_array('inventory.approve', $userPermissions, true)) {
                    echo '<button class="stg-icon-btn stg-approve" data-req="' . (int) $rq['id'] . '" title="' . htmlspecialchars(t('approve', 'Approve')) . '"><i class="fas fa-check"></i></button>';
                }
                if (in_array('inventory.reject', $userPermissions, true)) {
                    echo '<button class="stg-icon-btn stg-reject" data-req="' . (int) $rq['id'] . '" title="' . htmlspecialchars(t('reject', 'Reject')) . '"><i class="fas fa-times"></i></button>';
                }
                if (!in_array('inventory.approve', $userPermissions, true) && !in_array('inventory.reject', $userPermissions, true)) {
                    echo '<span class="loc-none">—</span>';
                }
            } elseif ($st === 'approved' && in_array('inventory.mark_delivered', $userPermissions, true)) {
                echo '<button class="stg-icon-btn stg-delivered" data-req="' . (int) $rq['id'] . '" title="' . htmlspecialchars(t('mark_as_delivered', 'Mark as delivered to storage')) . '"><i class="fas fa-truck-ramp-box"></i> ' . htmlspecialchars(t('delivered', 'Delivered')) . '</button>';
            } else {
                echo '<span class="loc-none">—</span>';
            }
            echo '</td>';
            echo '</tr>';
        }
        break;
    }

    // ── Movement History (both in and out; full server-side search) ────────
    case 'history': {
        $direction = trim($_GET['direction'] ?? '');
        $person = trim($_GET['person'] ?? '');
        $where = ['1=1'];
        $params = [];
        $types = '';
        inventory_push_date_range($where, $params, $types, 'm.movement_date', $date_from, $date_to);
        if ($item_id) { $where[] = 'm.item_id = ?'; $params[] = $item_id; $types .= 'i'; }
        if (in_array($direction, ['in', 'out'], true)) { $where[] = 'm.movement_type = ?'; $params[] = $direction; $types .= 's'; }
        if ($person !== '') {
            $where[] = '(m.person_name LIKE ? OR u.username LIKE ?)';
            $params[] = '%' . $person . '%'; $types .= 's';
            $params[] = '%' . $person . '%'; $types .= 's';
        }

        $limit = $isExport ? 5000 : 200;
        $sql = "SELECT m.*, i.item_name, i.unit, b.building_name, f.floor_name, a.apartment_number, u.username
                FROM inventory_movements m
                JOIN inventory_items i ON i.id = m.item_id
                LEFT JOIN buildings b ON b.id = m.building_id
                LEFT JOIN floors f ON f.id = m.floor_id
                LEFT JOIN apartments a ON a.id = m.apartment_id
                LEFT JOIN users u ON u.id = m.moved_by
                WHERE " . implode(' AND ', $where) . "
                ORDER BY m.movement_date DESC, m.id DESC
                LIMIT $limit";
        $res = inventory_run_dynamic($conn, $sql, $types, $params);

        if ($isExport) {
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="inventory-history-' . date('Y-m-d') . '.csv"');
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Date', 'Type', 'Item', 'Quantity', 'Unit', 'Location', 'Person', 'Recorded By', 'Notes']);
            while ($res && $h = $res->fetch_assoc()) {
                $isIn = $h['movement_type'] === 'in';
                $loc = $isIn ? '' : trim(implode(' > ', array_filter([
                    !empty($h['is_project_wide']) ? 'Project-wide' : null,
                    $h['building_name'] ?? null, $h['floor_name'] ?? null,
                    !empty($h['apartment_number']) ? 'Apt ' . $h['apartment_number'] : null,
                ])));
                fputcsv($out, [
                    $h['movement_date'], $isIn ? 'In' : 'Out', $h['item_name'], $h['quantity'], $h['unit'],
                    $loc, $h['person_name'] ?? '', $h['username'] ?? '', $h['notes'] ?? '',
                ]);
            }
            fclose($out);
            exit;
        }

        if (!$res || $res->num_rows === 0) {
            echo '<tr><td colspan="8" class="stg-empty">No movements match these filters.</td></tr>';
            exit;
        }
        while ($h = $res->fetch_assoc()) {
            $isIn = $h['movement_type'] === 'in';
            echo '<tr>';
            echo '<td>' . esc(date('d/m/Y', strtotime($h['movement_date']))) . '</td>';
            echo '<td><span class="stg-move stg-move-' . ($isIn ? 'in' : 'out') . '"><i class="fas fa-arrow-' . ($isIn ? 'down' : 'up') . '"></i> ' . ($isIn ? 'In' : 'Out') . '</span></td>';
            echo '<td class="stg-strong">' . esc($h['item_name']) . '</td>';
            echo '<td class="num">' . qty_fmt($h['quantity']) . ' <span class="stg-unit">' . esc($h['unit']) . '</span></td>';
            echo '<td>' . ($isIn ? '<span class="loc-none">—</span>' : inventory_location_label($h)) . '</td>';
            echo '<td>' . ($h['person_name'] ? esc($h['person_name']) : '<span class="loc-none">—</span>') . '</td>';
            echo '<td>' . ($h['username'] ? esc($h['username']) : '<span class="loc-none">—</span>') . '</td>';
            echo '<td>' . ($h['notes'] ? esc($h['notes']) : '<span class="loc-none">—</span>') . '</td>';
            echo '</tr>';
        }
        break;
    }

    default:
        echo '<tr><td class="stg-empty">Unknown section.</td></tr>';
}
