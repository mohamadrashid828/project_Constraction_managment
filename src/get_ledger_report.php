<?php
session_start();
require_once '../config.php';
require_once 'includes/permissions.php';
require_once 'includes/stakeholders.php';

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

function ledger_money(float $v, string $cur = 'IQD'): string
{
    $decimals = ($cur === 'USD' && fmod($v, 1.0) != 0.0) ? 2 : 0;
    $n = number_format($v, $decimals);
    return $cur === 'USD' ? '$' . $n : $n . ' IQD';
}

// Runs a prepared statement with a dynamic param list and returns every row.
// mysqli's bind_param needs its extra args by reference, hence the manual
// reference array — the standard workaround for a variable-length param list.
function ledger_query(mysqli $conn, string $sql, string $types, array $params): array
{
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        // A bad query here should never silently look like "no data" —
        // that's exactly the shape of bug that hides as a zero everywhere.
        error_log('get_ledger_report.php: prepare failed: ' . $conn->error);
        return [];
    }
    if ($types !== '') {
        $refs = [];
        foreach ($params as $key => $value) {
            $refs[$key] = &$params[$key];
        }
        array_unshift($refs, $types);
        call_user_func_array([$stmt, 'bind_param'], $refs);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
    $stmt->close();
    return $rows;
}

// ── Read + validate filters ─────────────────────────────────────────────────
$date_from = trim($_GET['date_from'] ?? '');
$date_to = trim($_GET['date_to'] ?? '');
$building_param = trim($_GET['building_id'] ?? '');
$floor_param = trim($_GET['floor_id'] ?? '');
$apartment_param = trim($_GET['apartment_id'] ?? '');

$hasBuilding = $building_param !== '';
$hasFloor = $floor_param !== '';
$hasApartment = $apartment_param !== '';
// Optional third-level drill-down: history for one specific subwork line,
// scoped to whichever stakeholder it belongs to (subpart_id already implies
// the stakeholder) and still respecting the date/location filters above it.
$subpart_param = trim($_GET['subpart_id'] ?? '');
$hasSubpart = $subpart_param !== '';

$whereParts = [];
$params = [];
$types = '';
if ($date_from !== '') {
    $whereParts[] = 'entry_date >= ?';
    $params[] = $date_from;
    $types .= 's';
}
if ($date_to !== '') {
    $whereParts[] = 'entry_date <= ?';
    $params[] = $date_to;
    $types .= 's';
}
if ($hasSubpart) {
    $whereParts[] = 'subpart_id = ?';
    $params[] = (int)$subpart_param;
    $types .= 'i';
}
// Apartment is the most specific filter, then floor, then building — picking
// a more specific one makes the broader ones redundant, so only the tightest
// applies (matches how the report builder's own cascade behaves).
if ($hasApartment) {
    $whereParts[] = 'apartment_id = ?';
    $params[] = (int)$apartment_param;
    $types .= 'i';
} elseif ($hasFloor) {
    $whereParts[] = 'floor_id = ?';
    $params[] = (int)$floor_param;
    $types .= 'i';
} elseif ($hasBuilding) {
    $whereParts[] = 'building_id = ?';
    $params[] = (int)$building_param;
    $types .= 'i';
}
$whereSql = $whereParts ? ('WHERE ' . implode(' AND ', $whereParts)) : '';

// ── Scope label — what the filters actually resolve to ──────────────────────
$scopeLabel = 'Entire Project';
if ($hasApartment) {
    $stmt = $conn->prepare("SELECT a.apartment_number, f.floor_name, b.building_name
        FROM apartments a JOIN floors f ON f.id = a.floor_id JOIN buildings b ON b.id = f.building_id
        WHERE a.id = ?");
    $aptId = (int)$apartment_param;
    $stmt->bind_param('i', $aptId);
    $stmt->execute();
    $r = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $scopeLabel = $r
        ? ($r['building_name'] . ' / ' . $r['floor_name'] . ' / Apt ' . $r['apartment_number'])
        : 'Common Area';
} elseif ($hasFloor) {
    $stmt = $conn->prepare("SELECT f.floor_name, b.building_name FROM floors f JOIN buildings b ON b.id = f.building_id WHERE f.id = ?");
    $flrId = (int)$floor_param;
    $stmt->bind_param('i', $flrId);
    $stmt->execute();
    $r = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    // A floor filter matches every apartment on that floor too, not just its
    // common area — the label must not imply otherwise.
    $scopeLabel = $r ? ($r['building_name'] . ' / ' . $r['floor_name']) : 'Floor';
} elseif ($hasBuilding) {
    $buildingIdInt = (int)$building_param;
    if ($buildingIdInt === 0) {
        $scopeLabel = 'Project-Wide (no building)';
    } else {
        $stmt = $conn->prepare("SELECT building_name FROM buildings WHERE id = ?");
        $stmt->bind_param('i', $buildingIdInt);
        $stmt->execute();
        $r = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        $scopeLabel = $r ? $r['building_name'] : 'Building';
    }
}

// ── One unified row set — Gechkari (`measurements`) and generic
// (`project_work_entries`) carry different columns (Gechkari has no
// stakeholder/subwork/Slfa concept at all), so every name/id this endpoint
// needs is resolved once here via LEFT JOINs, and `source` lets every
// aggregation below tell the two origins apart. ──────────────────────────────
$unifiedCte = "
    WITH unified_entries AS (
        SELECT m.id, m.measurement_date AS entry_date, 'Gechkari' AS work_type_name,
               'gechkari' AS work_type_key, m.quantity, 'm²' AS metric_type,
               m.total_price,
               CASE
                   WHEN m.notes LIKE '[%' AND SUBSTRING_INDEX(m.notes, ']', 1) LIKE '%Currency:%'
                   THEN UPPER(TRIM(SUBSTRING_INDEX(SUBSTRING_INDEX(SUBSTRING_INDEX(m.notes, ']', 1), 'Currency:', -1), '|', 1)))
                   ELSE 'USD'
               END AS currency_type,
               CASE WHEN m.status = 'approved' THEN 'accepted' ELSE m.status END AS status,
               NULL AS slfa_payment_id, NULL AS subpart_id, NULL AS stakeholder_id, 'gechkari' AS source,
               m.building_id, m.floor_id, m.apartment_id, COALESCE(b.building_name, '—') AS building_name,
               COALESCE(f.floor_name, '—') AS floor_name, COALESCE(a.apartment_number, '') AS apartment_number,
               NULL AS stakeholder_name, NULL AS subpart_name, NULL AS subpart_metric, NULL AS subpart_unit_price,
               COALESCE(u.username, '—') AS engineer_name
        FROM measurements m
        LEFT JOIN buildings b ON b.id = m.building_id
        LEFT JOIN floors f ON f.id = m.floor_id
        LEFT JOIN apartments a ON a.id = m.apartment_id
        LEFT JOIN users u ON u.id = m.measured_by
        WHERE m.work_type_id = 1
        UNION ALL
        SELECT e.id, e.work_date AS entry_date, COALESCE(t.work_type_name, e.work_type_key) AS work_type_name,
               e.work_type_key, e.quantity, e.metric_type,
               e.total_price, e.currency_type, e.status,
               e.slfa_payment_id, e.subpart_id, e.stakeholder_id, 'generic' AS source,
               e.building_id, e.floor_id, e.apartment_id, COALESCE(b.building_name, '—') AS building_name,
               COALESCE(f.floor_name, '—') AS floor_name, COALESCE(a.apartment_number, '') AS apartment_number,
               ps.stakeholder_name, sp.subpart_name, sp.metric_type AS subpart_metric, sp.unit_price AS subpart_unit_price,
               e.engineer_name
        FROM project_work_entries e
        LEFT JOIN project_work_types t ON t.work_type_key = e.work_type_key
        LEFT JOIN buildings b ON b.id = e.building_id
        LEFT JOIN floors f ON f.id = e.floor_id
        LEFT JOIN apartments a ON a.id = e.apartment_id
        LEFT JOIN project_stakeholders ps ON ps.id = e.stakeholder_id
        LEFT JOIN project_stakeholder_subparts sp ON sp.id = e.subpart_id
    )
";

$rows = ledger_query($conn, $unifiedCte . " SELECT * FROM unified_entries $whereSql ORDER BY entry_date DESC, id DESC", $types, $params);

// ── Aggregate everything in PHP from the one row set ────────────────────────
$entryCount = 0;
$approvedByCur = [];
$reviewByCur = [];
$rejectedByCur = [];
$settledByCur = [];
$outstandingByCur = [];

$categories = []; // key: work_type_name . '|' . currency
$stakeholders = []; // key: stakeholder_id

foreach ($rows as $row) {
    $cur = $row['currency_type'] ?: 'IQD';
    $status = strtolower(trim((string)$row['status']));
    $price = (float)$row['total_price'];
    $isGeneric = $row['source'] === 'generic';
    $isSettled = $isGeneric && !empty($row['slfa_payment_id']);
    $isOutstanding = $isGeneric && $status === 'accepted' && empty($row['slfa_payment_id']);

    $entryCount++;
    if ($status === 'accepted') {
        $approvedByCur[$cur] = ($approvedByCur[$cur] ?? 0) + $price;
    } elseif ($status === 'draft' || $status === 'medium') {
        $reviewByCur[$cur] = ($reviewByCur[$cur] ?? 0) + $price;
    } elseif ($status === 'rejected') {
        $rejectedByCur[$cur] = ($rejectedByCur[$cur] ?? 0) + $price;
    }
    if ($isSettled) {
        $settledByCur[$cur] = ($settledByCur[$cur] ?? 0) + $price;
    }
    if ($isOutstanding) {
        $outstandingByCur[$cur] = ($outstandingByCur[$cur] ?? 0) + $price;
    }

    // ── Category rollup, with nested stakeholder + building breakdowns ──────
    $catKey = $row['work_type_name'] . '|' . $cur;
    if (!isset($categories[$catKey])) {
        $categories[$catKey] = [
            'name' => $row['work_type_name'], 'currency' => $cur,
            'entries' => 0, 'quantity' => 0.0,
            'approved' => 0.0, 'review' => 0.0, 'rejected' => 0.0,
            'settled' => 0.0, 'outstanding' => 0.0, 'has_generic' => false,
            'stakeholders' => [], 'buildings' => [],
        ];
    }
    $c = &$categories[$catKey];
    $c['entries']++;
    $c['quantity'] += (float)$row['quantity'];
    if ($status === 'accepted') $c['approved'] += $price;
    if ($status === 'draft' || $status === 'medium') $c['review'] += $price;
    if ($status === 'rejected') $c['rejected'] += $price;
    if ($isSettled) $c['settled'] += $price;
    if ($isOutstanding) $c['outstanding'] += $price;
    if ($isGeneric) $c['has_generic'] = true;

    $stName = $row['stakeholder_name'] ?: '—';
    if (!isset($c['stakeholders'][$stName])) {
        $c['stakeholders'][$stName] = ['name' => $stName, 'entries' => 0, 'approved' => 0.0];
    }
    $c['stakeholders'][$stName]['entries']++;
    if ($status === 'accepted') $c['stakeholders'][$stName]['approved'] += $price;

    $bName = $row['building_name'] ?: '—';
    if (!isset($c['buildings'][$bName])) {
        $c['buildings'][$bName] = ['name' => $bName, 'entries' => 0];
    }
    $c['buildings'][$bName]['entries']++;
    unset($c);

    // ── Stakeholder rollup, with nested subwork breakdown ───────────────────
    // Gechkari rows have no stakeholder_id at all — they simply don't
    // participate here, same as they don't participate in Slfa settlement.
    if ($isGeneric && !empty($row['stakeholder_id'])) {
        $stId = (int)$row['stakeholder_id'];
        if (!isset($stakeholders[$stId])) {
            $stakeholders[$stId] = [
                'id' => $stId, 'name' => $row['stakeholder_name'] ?: 'Stakeholder #' . $stId,
                'currency' => $cur, 'entries' => 0,
                'approved' => 0.0, 'review' => 0.0, 'rejected' => 0.0,
                'settled' => 0.0, 'outstanding' => 0.0, 'subparts' => [],
            ];
        }
        $s = &$stakeholders[$stId];
        $s['entries']++;
        if ($status === 'accepted') $s['approved'] += $price;
        if ($status === 'draft' || $status === 'medium') $s['review'] += $price;
        if ($status === 'rejected') $s['rejected'] += $price;
        if ($isSettled) $s['settled'] += $price;
        if ($isOutstanding) $s['outstanding'] += $price;

        $spKey = (int)($row['subpart_id'] ?? 0);
        if ($spKey > 0) {
            if (!isset($s['subparts'][$spKey])) {
                $s['subparts'][$spKey] = [
                    'id' => $spKey,
                    'name' => $row['subpart_name'] ?: 'Subpart #' . $spKey,
                    'metric' => stakeholder_metric_label((string)($row['subpart_metric'] ?: 'unit')),
                    'unit_price' => (float)$row['subpart_unit_price'],
                    'currency' => $cur, 'entries' => 0, 'quantity' => 0.0,
                    'approved' => 0.0, 'review' => 0.0, 'rejected' => 0.0,
                    'settled' => 0.0, 'outstanding' => 0.0,
                ];
            }
            $sp = &$s['subparts'][$spKey];
            $sp['entries']++;
            $sp['quantity'] += (float)$row['quantity'];
            if ($status === 'accepted') $sp['approved'] += $price;
            if ($status === 'draft' || $status === 'medium') $sp['review'] += $price;
            if ($status === 'rejected') $sp['rejected'] += $price;
            if ($isSettled) $sp['settled'] += $price;
            if ($isOutstanding) $sp['outstanding'] += $price;
            unset($sp);
        }
        unset($s);
    }
}

// Dominant currency — the one with the largest approved value in this
// filtered scope, so the pie charts and meters plot a single meaningful
// currency instead of an average of two that don't share a unit.
$dominantCur = 'IQD';
$best = -1;
foreach ($approvedByCur as $cur => $v) {
    if ($v > $best) { $best = $v; $dominantCur = $cur; }
}

$reviewCount = 0;
$rejectedCountAll = 0;
foreach ($rows as $row) {
    $status = strtolower(trim((string)$row['status']));
    if ($status === 'draft' || $status === 'medium') $reviewCount++;
    if ($status === 'rejected') $rejectedCountAll++;
}
$decidedCount = $entryCount - $reviewCount;
$rejectionRatePct = $decidedCount > 0 ? round(($rejectedCountAll / $decidedCount) * 100, 1) : 0.0;

$settledDominant = $settledByCur[$dominantCur] ?? 0.0;
$outstandingDominant = $outstandingByCur[$dominantCur] ?? 0.0;
$settlementCoveragePct = ($settledDominant + $outstandingDominant) > 0.0001
    ? round(($settledDominant / ($settledDominant + $outstandingDominant)) * 100, 1)
    : 0.0;

// ── Shape categories/stakeholders for output ────────────────────────────────
$categoriesOut = [];
foreach ($categories as $c) {
    $stList = array_values($c['stakeholders']);
    usort($stList, fn($a, $b) => $b['approved'] <=> $a['approved']);
    $bList = array_values($c['buildings']);
    usort($bList, fn($a, $b) => $b['entries'] <=> $a['entries']);
    $categoriesOut[] = [
        'name' => $c['name'], 'currency' => $c['currency'],
        'entries' => $c['entries'], 'quantity' => round($c['quantity'], 2),
        'approved' => $c['approved'], 'review' => $c['review'], 'rejected' => $c['rejected'],
        'settled' => $c['settled'], 'outstanding' => $c['outstanding'], 'has_generic' => $c['has_generic'],
        'approved_display' => ledger_money($c['approved'], $c['currency']),
        'review_display' => ledger_money($c['review'], $c['currency']),
        'rejected_display' => ledger_money($c['rejected'], $c['currency']),
        'settled_display' => $c['has_generic'] ? ledger_money($c['settled'], $c['currency']) : null,
        'outstanding_display' => $c['has_generic'] ? ledger_money($c['outstanding'], $c['currency']) : null,
        'stakeholders' => array_map(function ($s) use ($c) {
            $s['approved_display'] = ledger_money($s['approved'], $c['currency']);
            return $s;
        }, $stList),
        'buildings' => $bList,
    ];
}
usort($categoriesOut, fn($a, $b) => $b['approved'] <=> $a['approved']);

$stakeholdersOut = [];
foreach ($stakeholders as $s) {
    $spList = array_values($s['subparts']);
    usort($spList, fn($a, $b) => $b['approved'] <=> $a['approved']);
    $stakeholdersOut[] = [
        'id' => $s['id'], 'name' => $s['name'], 'currency' => $s['currency'], 'entries' => $s['entries'],
        'approved' => $s['approved'], 'review' => $s['review'], 'rejected' => $s['rejected'],
        'settled' => $s['settled'], 'outstanding' => $s['outstanding'],
        'approved_display' => ledger_money($s['approved'], $s['currency']),
        'review_display' => ledger_money($s['review'], $s['currency']),
        'rejected_display' => ledger_money($s['rejected'], $s['currency']),
        'settled_display' => ledger_money($s['settled'], $s['currency']),
        'outstanding_display' => ledger_money($s['outstanding'], $s['currency']),
        'subparts' => array_map(function ($sp) {
            $sp['unit_price_display'] = ledger_money($sp['unit_price'], $sp['currency']);
            $sp['approved_display'] = ledger_money($sp['approved'], $sp['currency']);
            $sp['review_display'] = ledger_money($sp['review'], $sp['currency']);
            $sp['rejected_display'] = ledger_money($sp['rejected'], $sp['currency']);
            $sp['settled_display'] = ledger_money($sp['settled'], $sp['currency']);
            $sp['outstanding_display'] = ledger_money($sp['outstanding'], $sp['currency']);
            $sp['quantity'] = round($sp['quantity'], 2);
            return $sp;
        }, $spList),
    ];
}
usort($stakeholdersOut, fn($a, $b) => $b['approved'] <=> $a['approved']);

// ── "What we did & who did it" — capped recent-first list. Also doubles as
// the subwork history list when `subpart_id` is passed: same shape, just
// filtered to one subpart's own rows via the WHERE clause above. ───────────
$entriesOut = [];
foreach (array_slice($rows, 0, $hasSubpart ? 300 : 150) as $row) {
    $location = $row['building_name'] ?: '—';
    if (!empty($row['floor_name']) && $row['floor_name'] !== '—') {
        $location .= ' / ' . $row['floor_name'];
    }
    if (!empty($row['apartment_number'])) {
        $location .= ' / Apt ' . $row['apartment_number'];
    } elseif ($row['source'] === 'generic' || $row['source'] === 'gechkari') {
        $location .= ' / Common Area';
    }
    $entriesOut[] = [
        'date' => date('d/m/Y', strtotime((string)$row['entry_date'])),
        'work_type' => $row['work_type_name'],
        'subpart' => $row['subpart_name'] ?: '—',
        'subpart_id' => (int)($row['subpart_id'] ?? 0),
        'engineer' => $row['engineer_name'] ?: '—',
        'stakeholder' => $row['stakeholder_name'] ?: '—',
        'building' => $row['building_name'] ?: '—',
        'location' => $location,
        'quantity' => round((float)$row['quantity'], 2),
        'metric' => $row['metric_type'] ?: 'unit',
        'total_display' => ledger_money((float)$row['total_price'], $row['currency_type'] ?: 'IQD'),
        'status' => strtolower(trim((string)$row['status'])),
    ];
}

echo json_encode([
    'success' => true,
    'scope_label' => $scopeLabel,
    'dominant_currency' => $dominantCur,
    'kpi' => [
        'entries' => $entryCount,
        'approved_display' => an_money_multi_json($approvedByCur),
        'review_display' => an_money_multi_json($reviewByCur),
        'rejected_display' => an_money_multi_json($rejectedByCur),
        'settled_display' => an_money_multi_json($settledByCur),
        'outstanding_display' => an_money_multi_json($outstandingByCur),
        'review_count' => $reviewCount,
        'rejected_count' => $rejectedCountAll,
        'rejection_rate_pct' => $rejectionRatePct,
        'settlement_coverage_pct' => $settlementCoveragePct,
    ],
    'status_mix' => [
        'currency' => $dominantCur,
        'approved' => $approvedByCur[$dominantCur] ?? 0.0,
        'review' => $reviewByCur[$dominantCur] ?? 0.0,
        'rejected' => $rejectedByCur[$dominantCur] ?? 0.0,
    ],
    'settlement' => [
        'currency' => $dominantCur,
        'settled' => $settledDominant,
        'outstanding' => $outstandingDominant,
    ],
    'categories' => $categoriesOut,
    'stakeholders' => $stakeholdersOut,
    'entries' => $entriesOut,
    'has_location_filter' => $hasBuilding || $hasFloor || $hasApartment,
    'subpart_context' => ($hasSubpart && !empty($rows))
        ? ($rows[0]['subpart_name'] . ' — ' . $rows[0]['stakeholder_name'])
        : null,
]);

function an_money_multi_json(array $byCurrency): string
{
    $parts = [];
    foreach ($byCurrency as $cur => $v) {
        if (abs((float)$v) < 0.0001) {
            continue;
        }
        $parts[] = ledger_money((float)$v, (string)$cur);
    }
    return $parts ? implode(' · ', $parts) : '0';
}
