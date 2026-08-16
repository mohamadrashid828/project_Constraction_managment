<?php
session_start();
if (empty($_SESSION['user_id'])) {
    header('Location: index.html');
    exit;
}
require_once '../config.php';
require_once 'includes/permissions.php';
require_once 'includes/i18n.php';

$user_id = (int)$_SESSION['user_id'];
$username = htmlspecialchars($_SESSION['username']);

// Live-role permissions (join through users.id), so a role change applies on
// the next request rather than after re-login.
$permissions = get_user_permissions($conn, $user_id);

function dashboard_money(float $value, string $currency = 'IQD'): string
{
    $decimals = ($currency === 'USD' && fmod($value, 1.0) != 0.0) ? 2 : 0;
    $formatted = number_format($value, $decimals, '.', ',');
    return $currency === 'USD' ? '$' . $formatted : $formatted . ' IQD';
}

// A measurement's currency lives in its notes meta block
// ("[Engineer: … | Currency: IQD] …", written by data_entry.php) — extract
// it the same way get_dynamic_report.php does, defaulting to USD.
$unifiedCte = "
    WITH unified_entries AS (
        SELECT m.id, m.measurement_date AS entry_date, 'Gechkari' AS work_type_name,
               m.total_price,
               CASE
                   WHEN m.notes LIKE '[%' AND SUBSTRING_INDEX(m.notes, ']', 1) LIKE '%Currency:%'
                   THEN UPPER(TRIM(SUBSTRING_INDEX(SUBSTRING_INDEX(SUBSTRING_INDEX(m.notes, ']', 1), 'Currency:', -1), '|', 1)))
                   ELSE 'USD'
               END AS currency_type,
               CASE WHEN m.status = 'approved' THEN 'accepted' ELSE m.status END AS status,
               m.notes, m.created_at
        FROM measurements m WHERE m.work_type_id = 1
        UNION ALL
        SELECT e.id, e.work_date AS entry_date, COALESCE(t.work_type_name, e.work_type_key) AS work_type_name,
               e.total_price, e.currency_type, e.status, e.notes, e.created_at
        FROM project_work_entries e
        LEFT JOIN project_work_types t ON t.work_type_key = e.work_type_key
    )
";

// Same counting rules as the Analysis page: only active rows, and
// apartments/floors counted through their real parent chain.
$structure = $conn->query("
    SELECT
        (SELECT COUNT(*) FROM buildings WHERE status = 'active') AS buildings,
        (SELECT COUNT(*) FROM floors f
         JOIN buildings b ON b.id = f.building_id
         WHERE f.status = 'active' AND b.status = 'active') AS floors,
        (SELECT COUNT(*) FROM apartments a
         JOIN floors fa ON fa.id = a.floor_id
         JOIN buildings ba ON ba.id = fa.building_id
         WHERE a.status = 'active' AND fa.status = 'active' AND ba.status = 'active') AS apartments
")->fetch_assoc();

$entryCount = 0;
$approvedCount = 0;
$reviewCount = 0;
$rejectedCount = 0;
// IQD and USD are never added together — a cross-currency sum would be a
// meaningless number. Values are kept split per currency throughout.
$approvedByCurrency = [];

$res = $conn->query($unifiedCte . "SELECT status, currency_type, COUNT(*) c, COALESCE(SUM(total_price),0) v FROM unified_entries GROUP BY status, currency_type");
while ($res && $row = $res->fetch_assoc()) {
    $status = strtolower(trim((string)$row['status']));
    $currency = $row['currency_type'] ?: 'IQD';
    $count = (int)$row['c'];
    $value = (float)$row['v'];
    $entryCount += $count;

    if ($status === 'accepted') {
        $approvedCount += $count;
        $approvedByCurrency[$currency] = ($approvedByCurrency[$currency] ?? 0.0) + $value;
    } elseif ($status === 'draft' || $status === 'medium') {
        $reviewCount += $count;
    } elseif ($status === 'rejected') {
        $rejectedCount += $count;
    }
}

$dominantCurrency = 'IQD';
$bestValue = -1.0;
foreach ($approvedByCurrency as $currency => $value) {
    if ($value > $bestValue) {
        $bestValue = $value;
        $dominantCurrency = $currency;
    }
}

$approvalPercent = $entryCount > 0 ? (int)round(($approvedCount / $entryCount) * 100) : 0;
$pendingPurchaseRequests = (int)($conn->query("SELECT COUNT(*) as count FROM inventory_purchase_requests WHERE status = 'pending'")->fetch_assoc()['count'] ?? 0);

// Six-month trend of approved value, dominant currency only (mixing
// currencies in one line would be meaningless). Month math is anchored to
// the first of the month so "-N months" can't skid on the 29th–31st.
$trendLabels = [];
$trendValues = [];
$trendMonths = [];
$monthBase = strtotime(date('Y-m-01'));
for ($i = 5; $i >= 0; $i--) {
    $monthTs = strtotime("-$i months", $monthBase);
    $trendMonths[] = date('Y-m', $monthTs);
    $trendLabels[] = date('M', $monthTs);
}
$trendLookup = [];
$trendStmt = $conn->prepare($unifiedCte . "SELECT DATE_FORMAT(entry_date, '%Y-%m') AS month_key, COALESCE(SUM(total_price),0) AS value FROM unified_entries WHERE status = 'accepted' AND currency_type = ? GROUP BY month_key");
$trendStmt->bind_param('s', $dominantCurrency);
$trendStmt->execute();
$trendRes = $trendStmt->get_result();
while ($trendRes && $trendRow = $trendRes->fetch_assoc()) {
    $trendLookup[(string)$trendRow['month_key']] = (float)$trendRow['value'];
}
$trendStmt->close();
foreach ($trendMonths as $monthKey) {
    $trendValues[] = $trendLookup[$monthKey] ?? 0.0;
}

$recentRows = [];
$recentRes = $conn->query($unifiedCte . "SELECT entry_date, work_type_name, total_price, currency_type, status, notes FROM unified_entries ORDER BY created_at DESC, id DESC LIMIT 6");
while ($recentRes && $recentRow = $recentRes->fetch_assoc()) {
    $recentRows[] = $recentRow;
}

$reviewRows = [];
$reviewRes = $conn->query($unifiedCte . "SELECT entry_date, work_type_name, total_price, currency_type, status, notes FROM unified_entries WHERE status IN ('draft','medium') ORDER BY created_at DESC, id DESC LIMIT 4");
while ($reviewRes && $reviewRow = $reviewRes->fetch_assoc()) {
    $reviewRows[] = $reviewRow;
}

$purchaseRows = [];
$purchaseRes = $conn->query("SELECT r.id, COALESCE(i.item_name, r.item_name) AS item_name, r.quantity, r.unit, r.priority, r.needed_by_date, r.status FROM inventory_purchase_requests r LEFT JOIN inventory_items i ON i.id = r.item_id WHERE r.status IN ('pending','approved') ORDER BY r.created_at DESC LIMIT 4");
while ($purchaseRes && $purchaseRow = $purchaseRes->fetch_assoc()) {
    $purchaseRows[] = $purchaseRow;
}

$pageTitle = t('dashboard', 'Dashboard') . ' - ' . t('construction_management', 'Construction Management');
$pageCss = 'dashboard.css';
$activePage = 'dashboard';
require_once 'partials/header.php';
?>
<div id="dashboard-page" class="dashboard-container">
<?php require_once 'partials/sidebar.php'; ?>

    <main class="main-content">
        <header class="page-header">
            <div>
                <h1><i class="fas fa-tachometer-alt"></i> <?php echo htmlspecialchars(t('dashboard', 'Dashboard')); ?></h1>
                <p class="page-subtitle"><?php echo htmlspecialchars(t('dashboard_subtitle', 'A clear view of progress, approvals, and next actions.')); ?></p>
            </div>
            <div class="user-info">
                <span><?php echo htmlspecialchars(t('welcome', 'Welcome')); ?>, <?php echo htmlspecialchars($username); ?></span>
            </div>
        </header>

        <div class="content-wrapper">
            <section class="hero-card">
                <div>
                    <p class="eyebrow"><?php echo htmlspecialchars(t('project_overview', 'Project overview')); ?></p>
                    <h2><?php echo htmlspecialchars(t('dashboard_hero_title', 'Everything important is visible at a glance.')); ?></h2>
                    <p><?php echo htmlspecialchars(t('dashboard_hero_description', 'Track approvals, outstanding reviews, material requests, and recent activity without reading through raw entries.')); ?></p>
                </div>
                <div class="hero-metrics">
                    <div class="hero-metric">
                        <span><?php echo htmlspecialchars(t('approval_progress', 'Approval progress')); ?></span>
                        <strong><?php echo $approvalPercent; ?>%</strong>
                        <small><?php echo htmlspecialchars(t('entries_approved_summary', '{approved} of {total} entries approved', ['{approved}' => (string)$approvedCount, '{total}' => (string)$entryCount])); ?></small>
                    </div>
                    <div class="hero-metric">
                        <span><?php echo htmlspecialchars(t('needs_attention', 'Needs attention')); ?></span>
                        <strong><?php echo $reviewCount + $pendingPurchaseRequests; ?></strong>
                        <small><?php echo htmlspecialchars(t('review_requests_summary', '{review} review items and {requests} open requests', ['{review}' => (string)$reviewCount, '{requests}' => (string)$pendingPurchaseRequests])); ?></small>
                    </div>
                </div>
            </section>

            <section class="stats-grid">
                <article class="stat-card stat-card-blue">
                    <div class="stat-icon"><i class="fas fa-money-bill-wave"></i></div>
                    <div class="stat-content">
                        <p class="stat-label"><?php echo htmlspecialchars(t('approved_value', 'Approved value')); ?></p>
                        <h3><?php echo dashboard_money($approvedByCurrency[$dominantCurrency] ?? 0.0, $dominantCurrency); ?></h3>
                        <span class="stat-meta"><?php
                            $otherApproved = [];
                            foreach ($approvedByCurrency as $currency => $value) {
                                if ($currency !== $dominantCurrency && $value > 0) {
                                    $otherApproved[] = dashboard_money($value, $currency);
                                }
                            }
                            echo $otherApproved
                                ? htmlspecialchars(t('plus_approved_value', 'Plus {value} approved', ['{value}' => implode(' · ', $otherApproved)]))
                                : htmlspecialchars(t('signed_off_work', 'Signed-off work across the project'));
                        ?></span>
                    </div>
                </article>

                <article class="stat-card stat-card-green">
                    <div class="stat-icon"><i class="fas fa-clipboard-check"></i></div>
                    <div class="stat-content">
                        <p class="stat-label"><?php echo htmlspecialchars(t('pending_review', 'Pending review')); ?></p>
                        <h3><?php echo number_format($reviewCount); ?></h3>
                        <span class="stat-meta"><?php echo htmlspecialchars(t('entries_waiting_decision', 'Entries waiting for a decision')); ?></span>
                    </div>
                </article>

                <article class="stat-card stat-card-orange">
                    <div class="stat-icon"><i class="fas fa-boxes-stacked"></i></div>
                    <div class="stat-content">
                        <p class="stat-label"><?php echo htmlspecialchars(t('open_requests', 'Open requests')); ?></p>
                        <h3><?php echo number_format($pendingPurchaseRequests); ?></h3>
                        <span class="stat-meta"><?php echo htmlspecialchars(t('material_supply_requests', 'Material or supply requests')); ?></span>
                    </div>
                </article>

                <article class="stat-card stat-card-purple">
                    <div class="stat-icon"><i class="fas fa-building"></i></div>
                    <div class="stat-content">
                        <p class="stat-label"><?php echo htmlspecialchars(t('project_footprint', 'Project footprint')); ?></p>
                        <h3><?php echo number_format((int)($structure['buildings'] ?? 0)); ?></h3>
                        <span class="stat-meta"><?php echo htmlspecialchars(t('floors_apartments_summary', '{floors} floors · {apartments} apartments', ['{floors}' => number_format((int)($structure['floors'] ?? 0)), '{apartments}' => number_format((int)($structure['apartments'] ?? 0))])); ?></span>
                    </div>
                </article>
            </section>

            <section class="dashboard-grid">
                <article class="panel panel-large">
                    <div class="panel-header">
                        <div>
                            <p class="panel-label"><?php echo htmlspecialchars(t('project_health', 'Project health')); ?></p>
                            <h3><?php echo htmlspecialchars(t('approval_progress_status', 'Approval progress and status')); ?></h3>
                        </div>
                        <span class="pill"><?php echo htmlspecialchars(t('live', 'Live')); ?></span>
                    </div>
                    <div class="progress-block">
                        <div class="progress-copy">
                            <strong><?php echo $approvalPercent; ?>%</strong>
                            <span><?php echo htmlspecialchars(t('work_entries_already_approved', 'of work entries are already approved')); ?></span>
                        </div>
                        <div class="progress-bar">
                            <span style="width: <?php echo $approvalPercent; ?>%;"></span>
                        </div>
                    </div>
                    <div class="status-list">
                        <div class="status-item">
                            <span class="status-dot status-dot-green"></span>
                            <div>
                                <strong><?php echo number_format($approvedCount); ?></strong>
                                <p><?php echo htmlspecialchars(t('accepted', 'Approved')); ?></p>
                            </div>
                        </div>
                        <div class="status-item">
                            <span class="status-dot status-dot-amber"></span>
                            <div>
                                <strong><?php echo number_format($reviewCount); ?></strong>
                                <p><?php echo htmlspecialchars(t('needs_review', 'Needs review')); ?></p>
                            </div>
                        </div>
                        <div class="status-item">
                            <span class="status-dot status-dot-red"></span>
                            <div>
                                <strong><?php echo number_format($rejectedCount); ?></strong>
                                <p><?php echo htmlspecialchars(t('rejected', 'Rejected')); ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="insight-box">
                        <i class="fas fa-info-circle"></i>
                        <span><?php echo htmlspecialchars(t('dashboard_insight', 'Recent work is moving steadily; the next best step is reviewing the pending items and clearing open requests.')); ?></span>
                    </div>
                </article>

                <article class="panel panel-actions">
                    <div class="panel-header">
                        <div>
                            <p class="panel-label"><?php echo htmlspecialchars(t('quick_actions', 'Quick actions')); ?></p>
                            <h3><?php echo htmlspecialchars(t('next_task', 'Jump straight to the next task')); ?></h3>
                        </div>
                    </div>
                    <div class="action-list">
                        <?php if (in_array('data_entry', $permissions, true)): ?><a class="action-item" href="data_entry.php"><i class="fas fa-edit"></i><span><?php echo htmlspecialchars(t('log_new_work', 'Log new work')); ?></span></a><?php endif; ?>
                        <?php if (in_array('analytics', $permissions, true)): ?><a class="action-item" href="analytics.php"><i class="fas fa-chart-line"></i><span><?php echo htmlspecialchars(t('review_analysis', 'Review analysis')); ?></span></a><?php endif; ?>
                        <?php if (in_array('slfa', $permissions, true)): ?><a class="action-item" href="slfa.php"><i class="fas fa-file-invoice-dollar"></i><span><?php echo htmlspecialchars(t('open_slfa', 'Open payments')); ?></span></a><?php endif; ?>
                        <?php if (in_array('inventory.view', $permissions, true)): ?><a class="action-item" href="storage.php"><i class="fas fa-boxes-stacked"></i><span><?php echo htmlspecialchars(t('check_inventory', 'Check inventory')); ?></span></a><?php endif; ?>
                        <?php if (in_array('stakeholders', $permissions, true)): ?><a class="action-item" href="stakeholders.php"><i class="fas fa-handshake"></i><span><?php echo htmlspecialchars(t('view_stakeholders', 'View stakeholders')); ?></span></a><?php endif; ?>
                        <?php if (in_array('project_settings', $permissions, true)): ?><a class="action-item" href="project_settings.php"><i class="fas fa-cog"></i><span>Project settings</span></a><?php endif; ?>
                    </div>
                </article>
            </section>

            <section class="dashboard-grid secondary-grid">
                <article class="panel panel-chart">
                    <div class="panel-header">
                        <div>
                            <p class="panel-label"><?php echo htmlspecialchars(t('trend', 'Trend')); ?></p>
                            <h3><?php echo htmlspecialchars(t('approved_value_over_time', 'Approved value over time ({currency})', ['{currency}' => $dominantCurrency])); ?></h3>
                        </div>
                    </div>
                    <div class="chart-wrap">
                        <canvas id="dashboardTrendChart"></canvas>
                    </div>
                </article>

                <article class="panel panel-list">
                    <div class="panel-header">
                        <div>
                            <p class="panel-label"><?php echo htmlspecialchars(t('needs_attention', 'Needs attention')); ?></p>
                            <h3><?php echo htmlspecialchars(t('pending_review_entries', 'Pending review entries')); ?></h3>
                        </div>
                    </div>
                    <?php if (empty($reviewRows)): ?>
                        <div class="empty-state"><i class="fas fa-check-circle"></i><span><?php echo htmlspecialchars(t('no_review_items', 'No review items right now.')); ?></span></div>
                    <?php else: ?>
                        <ul class="item-list">
                            <?php foreach ($reviewRows as $row): $status = strtolower(trim((string)$row['status'])); ?>
                                <li>
                                    <div>
                                        <strong><?php echo htmlspecialchars((string)$row['work_type_name']); ?></strong>
                                        <p><?php echo htmlspecialchars(date('d M Y', strtotime((string)$row['entry_date']))); ?></p>
                                    </div>
                                    <span class="badge <?php echo $status === 'draft' ? 'badge-amber' : 'badge-blue'; ?>"><?php echo htmlspecialchars($status === 'draft' ? t('draft', 'Draft') : t('under_review', 'Under review')); ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </article>
            </section>

            <section class="dashboard-grid secondary-grid">
                <article class="panel panel-list">
                    <div class="panel-header">
                        <div>
                            <p class="panel-label"><?php echo htmlspecialchars(t('latest_activity', 'Latest activity')); ?></p>
                            <h3><?php echo htmlspecialchars(t('recent_work_entries', 'Recent work entries')); ?></h3>
                        </div>
                    </div>
                    <?php if (empty($recentRows)): ?>
                        <div class="empty-state"><i class="fas fa-file-circle-plus"></i><span><?php echo htmlspecialchars(t('no_work_entries_yet', 'No work entries yet.')); ?></span></div>
                    <?php else: ?>
                        <ul class="item-list">
                            <?php foreach ($recentRows as $row): ?>
                                <li>
                                    <div>
                                        <strong><?php echo htmlspecialchars((string)$row['work_type_name']); ?></strong>
                                        <p><?php echo htmlspecialchars(date('d M Y', strtotime((string)$row['entry_date']))); ?></p>
                                    </div>
                                    <span class="badge badge-green"><?php echo dashboard_money((float)$row['total_price'], (string)($row['currency_type'] ?: 'IQD')); ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </article>

                <article class="panel panel-list">
                    <div class="panel-header">
                        <div>
                            <p class="panel-label"><?php echo htmlspecialchars(t('procurement', 'Procurement')); ?></p>
                            <h3><?php echo htmlspecialchars(t('open_purchase_requests', 'Open purchase requests')); ?></h3>
                        </div>
                    </div>
                    <?php if (empty($purchaseRows)): ?>
                        <div class="empty-state"><i class="fas fa-truck-fast"></i><span><?php echo htmlspecialchars(t('no_pending_requests', 'No pending requests.')); ?></span></div>
                    <?php else: ?>
                        <ul class="item-list">
                            <?php foreach ($purchaseRows as $row): ?>
                                <li>
                                    <div>
                                        <strong><?php echo htmlspecialchars((string)$row['item_name']); ?></strong>
                                        <p><?php echo htmlspecialchars((string)$row['quantity']) . ' ' . htmlspecialchars((string)$row['unit']); ?></p>
                                    </div>
                                    <span class="badge badge-amber"><?php echo htmlspecialchars((string)$row['priority']); ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </article>
            </section>
        </div>
    </main>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const chartCanvas = document.getElementById('dashboardTrendChart');
    if (!chartCanvas) return;

    const labels = <?php echo json_encode($trendLabels); ?>;
    const values = <?php echo json_encode($trendValues); ?>;

    new Chart(chartCanvas, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: <?php echo json_encode(t('approved_value', 'Approved value')); ?>,
                data: values,
                borderColor: '#60a5fa',
                backgroundColor: 'rgba(96, 165, 250, 0.16)',
                fill: true,
                tension: 0.35,
                borderWidth: 3,
                pointRadius: 4,
                pointHoverRadius: 5,
                pointBackgroundColor: '#fff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false }
            },
            scales: {
                x: {
                    grid: { display: false },
                    ticks: { color: '#94a3b8' }
                },
                y: {
                    beginAtZero: true,
                    ticks: { color: '#94a3b8', callback: (value) => value.toLocaleString() },
                    grid: { color: 'rgba(255,255,255,0.08)' }
                }
            }
        }
    });
});
</script>
</body>
</html>
