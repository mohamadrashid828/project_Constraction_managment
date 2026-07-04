<?php
require_once '../config.php';
require_once 'includes/stakeholder_access.php';

ensure_stakeholder_access_column($conn);

$token = trim($_GET['token'] ?? '');
$stakeholder = get_stakeholder_by_token($conn, $token);

if (!$stakeholder) {
    http_response_code(404);
    $pageTitle = 'Link Not Found';
    require_once 'partials/header.php';
    echo '<div class="portal-page portal-error-page">';
    echo '<div class="portal-error-card"><i class="fas fa-link-slash"></i><h1>This link does not work</h1>';
    echo '<p>The page you tried to open is invalid or no longer active. Please ask the project team to send you a new personal link.</p></div></div>';
    echo '<link rel="stylesheet" href="../css/stakeholder_portal.css"></body></html>';
    exit;
}

$stakeholderId = (int)$stakeholder['id'];

$projectName = 'Construction Project';
$settingsRes = $conn->query("SELECT setting_key, setting_value FROM project_settings WHERE setting_key IN ('project_name', 'project_description')");
if ($settingsRes) {
    $settings = [];
    while ($s = $settingsRes->fetch_assoc()) {
        $settings[$s['setting_key']] = $s['setting_value'];
    }
    if (!empty($settings['project_name'])) {
        $projectName = $settings['project_name'];
    }
    $projectDescription = $settings['project_description'] ?? '';
}

$subparts = [];
$subStmt = $conn->prepare('SELECT subpart_name, unit_price, metric_type, currency_type FROM project_stakeholder_subparts WHERE stakeholder_id = ? AND is_active = 1 ORDER BY subpart_name');
$subStmt->bind_param('i', $stakeholderId);
$subStmt->execute();
$subRes = $subStmt->get_result();
while ($sp = $subRes->fetch_assoc()) {
    $subparts[] = $sp;
}
$subStmt->close();

$statsStmt = $conn->prepare("
    SELECT
        COUNT(e.id) AS total_entries,
        COALESCE(SUM(CASE WHEN e.status = 'accepted' THEN 1 ELSE 0 END), 0) AS accepted_count,
        COALESCE(SUM(CASE WHEN e.status = 'medium' THEN 1 ELSE 0 END), 0) AS medium_count,
        COALESCE(SUM(CASE WHEN e.status = 'rejected' THEN 1 ELSE 0 END), 0) AS rejected_count,
        COALESCE(SUM(CASE WHEN e.status = 'accepted' THEN e.total_price ELSE 0 END), 0) AS accepted_value,
        COALESCE(SUM(CASE WHEN e.status = 'accepted' AND (e.slfa_payment_id IS NULL OR e.slfa_payment_id = 0) THEN e.total_price ELSE 0 END), 0) AS pending_value,
        COALESCE(SUM(CASE WHEN e.status = 'accepted' AND (e.slfa_payment_id IS NULL OR e.slfa_payment_id = 0) THEN 1 ELSE 0 END), 0) AS pending_count
    FROM project_work_entries e
    WHERE e.stakeholder_id = ?
");
$statsStmt->bind_param('i', $stakeholderId);
$statsStmt->execute();
$stats = $statsStmt->get_result()->fetch_assoc() ?: [];
$statsStmt->close();

$payments = [];
$payStmt = $conn->prepare("
    SELECT id, payment_date, total_work_value, cash_percentage, cash_amount,
           apartment_percentage, apartment_amount, apartment_sqm, apartment_meter_price, entry_count, notes
    FROM slfa_payments
    WHERE stakeholder_id = ?
    ORDER BY payment_date DESC, created_at DESC
");
$payStmt->bind_param('i', $stakeholderId);
$payStmt->execute();
$payRes = $payStmt->get_result();
while ($p = $payRes->fetch_assoc()) {
    $payments[] = $p;
}
$payStmt->close();

$entries = [];
$entryStmt = $conn->prepare("
    SELECT
        e.work_date, e.engineer_name, e.quantity, e.unit_price, e.total_price,
        e.metric_type, e.currency_type, e.status, e.notes,
        COALESCE(b.building_name, '—') AS building_name,
        COALESCE(f.floor_name, '—') AS floor_name,
        COALESCE(a.apartment_number, '—') AS apartment_number,
        COALESCE(sp.subpart_name, e.work_type_key) AS subpart_label
    FROM project_work_entries e
    LEFT JOIN buildings b ON b.id = e.building_id
    LEFT JOIN floors f ON f.id = e.floor_id
    LEFT JOIN apartments a ON a.id = e.apartment_id
    LEFT JOIN project_stakeholder_subparts sp ON sp.id = e.subpart_id
    WHERE e.stakeholder_id = ?
    ORDER BY e.work_date DESC, e.created_at DESC
    LIMIT 200
");
$entryStmt->bind_param('i', $stakeholderId);
$entryStmt->execute();
$entryRes = $entryStmt->get_result();
while ($e = $entryRes->fetch_assoc()) {
    $entries[] = $e;
}
$entryStmt->close();

$pageTitle = htmlspecialchars($stakeholder['stakeholder_name']) . ' - My Account';
$pageCss = 'stakeholder_portal.css';
require_once 'partials/header.php';
?>
<div class="portal-page">
    <header class="portal-header">
        <div class="portal-header-inner">
            <div>
                <p class="portal-project-label"><?php echo htmlspecialchars($projectName); ?></p>
                <h1>Hello, <?php echo htmlspecialchars($stakeholder['stakeholder_name']); ?></h1>
                <p class="portal-subtitle">This is your personal page. Here you can see your contract details, agreed prices, work history, and payments — no login needed.</p>
            </div>
            <div class="portal-header-badges">
                <span class="portal-badge portal-badge-work"><i class="fas fa-hard-hat"></i> <?php echo htmlspecialchars($stakeholder['work_type_name']); ?></span>
                <?php if (!empty($stakeholder['stakeholder_date'])): ?>
                    <span class="portal-badge portal-badge-date"><i class="fas fa-calendar"></i> Started <?php echo date('d M Y', strtotime((string)$stakeholder['stakeholder_date'])); ?></span>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <main class="portal-main">
        <div class="portal-welcome-banner">
            <i class="fas fa-info-circle"></i>
            <div>
                <strong>What you can see here</strong>
                <p>Your profile, agreed unit prices, every work item recorded for you, and any advance payments (Slfa / سلفه) already made.</p>
            </div>
        </div>

        <section class="portal-stats-grid">
            <div class="portal-stat-card">
                <div class="portal-stat-icon"><i class="fas fa-list-check"></i></div>
                <span class="portal-stat-label">Work items recorded</span>
                <strong><?php echo (int)($stats['total_entries'] ?? 0); ?></strong>
                <small>Total entries on your account</small>
            </div>
            <div class="portal-stat-card portal-stat-highlight">
                <div class="portal-stat-icon"><i class="fas fa-circle-check"></i></div>
                <span class="portal-stat-label">Approved work value</span>
                <strong>$<?php echo number_format((float)($stats['accepted_value'] ?? 0), 2); ?></strong>
                <small>Work that has been accepted</small>
            </div>
            <div class="portal-stat-card">
                <div class="portal-stat-icon"><i class="fas fa-hourglass-half"></i></div>
                <span class="portal-stat-label">Waiting to be paid</span>
                <strong>$<?php echo number_format((float)($stats['pending_value'] ?? 0), 2); ?></strong>
                <small><?php echo (int)($stats['pending_count'] ?? 0); ?> approved item(s) not paid yet</small>
            </div>
            <div class="portal-stat-card">
                <div class="portal-stat-icon"><i class="fas fa-money-bill-wave"></i></div>
                <span class="portal-stat-label">Advance payments received</span>
                <strong><?php echo count($payments); ?></strong>
                <small>Slfa / سلفه payments on record</small>
            </div>
        </section>

        <section class="portal-card">
            <div class="portal-card-head">
                <h2><i class="fas fa-id-card"></i> Your agreement details</h2>
                <p class="portal-card-desc">How your work is paid — cash vs apartment, and your agreed rates.</p>
            </div>
            <div class="portal-profile-grid">
                <div class="portal-profile-item">
                    <span>Type of work</span>
                    <strong><?php echo htmlspecialchars($stakeholder['work_type_name']); ?></strong>
                </div>
                <div class="portal-profile-item">
                    <span>Paid in cash</span>
                    <strong><?php echo number_format((float)($stakeholder['cash_percentage'] ?? 100), 0); ?>%</strong>
                    <small>Share of payment you receive as cash</small>
                </div>
                <div class="portal-profile-item">
                    <span>Paid as apartment</span>
                    <strong><?php echo number_format((float)($stakeholder['apartment_percentage'] ?? 0), 0); ?>%</strong>
                    <small>Share converted to apartment area</small>
                </div>
                <div class="portal-profile-item">
                    <span>Apartment price per m²</span>
                    <strong>$<?php echo number_format((float)($stakeholder['apartment_meter_price'] ?? 0), 2); ?></strong>
                </div>
                <div class="portal-profile-item">
                    <span>Approved items</span>
                    <strong><?php echo (int)($stats['accepted_count'] ?? 0); ?></strong>
                </div>
                <div class="portal-profile-item">
                    <span>Under review</span>
                    <strong><?php echo (int)($stats['medium_count'] ?? 0); ?></strong>
                </div>
                <div class="portal-profile-item">
                    <span>Rejected items</span>
                    <strong><?php echo (int)($stats['rejected_count'] ?? 0); ?></strong>
                </div>
                <?php if (!empty($stakeholder['contract_file'])): ?>
                <div class="portal-profile-contract">
                    <span>Your contract</span>
                    <a href="<?php echo htmlspecialchars(stakeholder_contract_url($token)); ?>" target="_blank" class="portal-link-btn">
                        <i class="fas fa-file-contract"></i> Open contract file
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </section>

        <?php if (!empty($subparts)): ?>
        <section class="portal-card">
            <div class="portal-card-head">
                <h2><i class="fas fa-tags"></i> Agreed prices</h2>
                <p class="portal-card-desc">The unit prices agreed for each part of your work.</p>
            </div>
            <div class="portal-table-wrap">
                <table class="portal-table">
                    <thead>
                        <tr>
                            <th>Work part</th>
                            <th>Measured in</th>
                            <th>Currency</th>
                            <th>Price per unit</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($subparts as $sp): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($sp['subpart_name']); ?></td>
                            <td><?php echo htmlspecialchars($sp['metric_type'] ?? 'm²'); ?></td>
                            <td><?php echo htmlspecialchars($sp['currency_type'] ?? 'USD'); ?></td>
                            <td class="portal-money"><?php echo number_format((float)$sp['unit_price'], 2); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
        <?php endif; ?>

        <?php if (!empty($payments)): ?>
        <section class="portal-card">
            <div class="portal-card-head">
                <h2><i class="fas fa-file-invoice-dollar"></i> Advance payments (Slfa / سلفه)</h2>
                <p class="portal-card-desc">Payments already made to you based on approved work.</p>
            </div>
            <div class="portal-table-wrap">
                <table class="portal-table">
                    <thead>
                        <tr>
                            <th>Payment date</th>
                            <th>Total work value</th>
                            <th>Cash portion</th>
                            <th>Apartment portion</th>
                            <th>Apartment area</th>
                            <th>Items included</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($payments as $p): ?>
                        <tr>
                            <td><?php echo date('d M Y', strtotime((string)$p['payment_date'])); ?></td>
                            <td class="portal-money">$<?php echo number_format((float)$p['total_work_value'], 2); ?></td>
                            <td><?php echo number_format((float)$p['cash_percentage'], 0); ?>% · $<?php echo number_format((float)$p['cash_amount'], 2); ?></td>
                            <td><?php echo number_format((float)$p['apartment_percentage'], 0); ?>% · $<?php echo number_format((float)$p['apartment_amount'], 2); ?></td>
                            <td><?php echo number_format((float)$p['apartment_sqm'], 2); ?> m²</td>
                            <td><?php echo (int)$p['entry_count']; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
        <?php endif; ?>

        <section class="portal-card">
            <div class="portal-card-head">
                <h2><i class="fas fa-clipboard-list"></i> Your work history</h2>
                <p class="portal-card-desc">Every work item linked to your name, with location and current status.</p>
            </div>

            <div class="portal-legend">
                <span><i class="portal-dot status-accepted"></i> Approved</span>
                <span><i class="portal-dot status-medium"></i> Under review</span>
                <span><i class="portal-dot status-rejected"></i> Rejected</span>
                <span><i class="portal-dot status-draft"></i> Waiting</span>
            </div>

            <?php if (empty($entries)): ?>
                <div class="portal-empty-box">
                    <i class="fas fa-inbox"></i>
                    <p>No work has been recorded for you yet.</p>
                    <small>When the site team adds your work, it will appear here automatically.</small>
                </div>
            <?php else: ?>
            <div class="portal-table-wrap">
                <table class="portal-table portal-table-entries">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Building / Floor / Apartment</th>
                            <th>Work part</th>
                            <th>Amount done</th>
                            <th>Total price</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($entries as $e): ?>
                        <tr>
                            <td><?php echo date('d M Y', strtotime((string)$e['work_date'])); ?></td>
                            <td>
                                <span class="portal-location"><?php echo htmlspecialchars($e['building_name']); ?></span>
                                <span class="portal-location-sub">Floor <?php echo htmlspecialchars($e['floor_name']); ?> · Apt <?php echo htmlspecialchars($e['apartment_number']); ?></span>
                            </td>
                            <td><?php echo htmlspecialchars($e['subpart_label']); ?></td>
                            <td><?php echo number_format((float)$e['quantity'], 2); ?> <?php echo htmlspecialchars($e['metric_type'] ?? ''); ?></td>
                            <td class="portal-money"><?php echo htmlspecialchars($e['currency_type'] ?? 'USD'); ?> <?php echo number_format((float)$e['total_price'], 2); ?></td>
                            <td><span class="portal-status <?php echo portal_status_class((string)$e['status']); ?>"><?php echo htmlspecialchars(portal_status_label((string)$e['status'])); ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </section>

        <footer class="portal-footer">
            <p><i class="fas fa-lock"></i> Private page for <strong><?php echo htmlspecialchars($stakeholder['stakeholder_name']); ?></strong> only. Please do not share this link with others.</p>
            <p class="portal-footer-help">Questions? Contact the project management team.</p>
        </footer>
    </main>
</div>
</body>
</html>
