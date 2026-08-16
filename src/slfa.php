<?php
session_start();
require_once '../config.php';
require_once 'includes/i18n.php';

if (empty($_SESSION['user_id'])) {
    header('Location: index.html');
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

if (!in_array('slfa', $permissions, true)) {
    header('Location: dashboard.php?error=access_denied');
    exit;
}

$isManager = in_array('user_management', $permissions, true) || in_array('project_settings', $permissions, true);

if (!$isManager) {
    header('Location: dashboard.php?error=access_denied');
    exit;
}

// ── Auto-create tables ─────────────────────────────────────────────────────
$conn->query("CREATE TABLE IF NOT EXISTS slfa_payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    stakeholder_id INT NOT NULL,
    payment_date DATE NOT NULL,
    total_work_value DECIMAL(14,2) NOT NULL DEFAULT 0,
    cash_percentage DECIMAL(5,2) NOT NULL DEFAULT 0,
    cash_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
    apartment_percentage DECIMAL(5,2) NOT NULL DEFAULT 0,
    apartment_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
    apartment_sqm DECIMAL(14,4) NOT NULL DEFAULT 0,
    apartment_meter_price DECIMAL(14,2) NOT NULL DEFAULT 0,
    entry_count INT NOT NULL DEFAULT 0,
    notes TEXT NULL,
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_stakeholder (stakeholder_id),
    INDEX idx_payment_date (payment_date)
)");

// Add slfa_payment_id column to project_work_entries (so we can track settled entries)
$colCheck = $conn->query("SHOW COLUMNS FROM project_work_entries LIKE 'slfa_payment_id'");
if ($colCheck && $colCheck->num_rows === 0) {
    $conn->query("ALTER TABLE project_work_entries ADD COLUMN slfa_payment_id INT NULL DEFAULT NULL AFTER status, ADD INDEX idx_slfa_payment (slfa_payment_id)");
}

// Auto-migrate: status change tracking columns
$colCheckSCB = $conn->query("SHOW COLUMNS FROM project_work_entries LIKE 'status_changed_by'");
if ($colCheckSCB && $colCheckSCB->num_rows === 0) {
    $conn->query("ALTER TABLE project_work_entries
        ADD COLUMN status_changed_by INT NULL DEFAULT NULL,
        ADD COLUMN status_changed_at DATETIME NULL DEFAULT NULL,
        ADD COLUMN previous_status VARCHAR(30) NULL DEFAULT NULL");
}

// ── Load stakeholders with live entry stats ────────────────────────────────
$stakeholderRows = $conn->query("
    SELECT
        s.id,
        s.stakeholder_name,
        s.work_type_key,
        s.cash_percentage,
        s.apartment_percentage,
        s.apartment_meter_price,
        COALESCE(wt.work_type_name, s.work_type_key) AS work_type_label,
        COUNT(e.id) AS total_entries,
        COALESCE(SUM(CASE WHEN e.status = 'accepted' AND (e.slfa_payment_id IS NULL OR e.slfa_payment_id = 0) THEN 1 ELSE 0 END), 0) AS accepted_count,
        COALESCE(SUM(CASE WHEN e.status = 'medium'   AND (e.slfa_payment_id IS NULL OR e.slfa_payment_id = 0) THEN 1 ELSE 0 END), 0) AS medium_count,
        COALESCE(SUM(CASE WHEN e.status = 'rejected' AND (e.slfa_payment_id IS NULL OR e.slfa_payment_id = 0) THEN 1 ELSE 0 END), 0) AS rejected_count,
        COALESCE(SUM(CASE WHEN e.status = 'accepted' THEN e.total_price ELSE 0 END), 0) AS accepted_value,
        COALESCE(SUM(CASE WHEN e.status = 'accepted' AND (e.slfa_payment_id IS NULL OR e.slfa_payment_id = 0) THEN e.total_price ELSE 0 END), 0) AS pending_value,
        COALESCE(SUM(CASE WHEN e.status = 'accepted' AND (e.slfa_payment_id IS NULL OR e.slfa_payment_id = 0) THEN 1 ELSE 0 END), 0) AS pending_count,
        (SELECT COUNT(*) FROM slfa_payments sp WHERE sp.stakeholder_id = s.id) AS payment_count,
        (SELECT COALESCE(SUM(sp2.apartment_sqm), 0) FROM slfa_payments sp2 WHERE sp2.stakeholder_id = s.id) AS total_slfa_sqm
    FROM project_stakeholders s
    LEFT JOIN project_work_types wt ON wt.work_type_key = s.work_type_key
    LEFT JOIN project_work_entries e ON e.stakeholder_id = s.id
    WHERE s.is_active = 1
    GROUP BY s.id
    ORDER BY s.stakeholder_name
");

$stakeholders = [];
if ($stakeholderRows) {
    while ($row = $stakeholderRows->fetch_assoc()) {
        $stakeholders[] = $row;
    }
}

$pageTitle = t('payment_management', 'Payment Management') . ' - Green World Towers';
$pageCss   = 'slfa.css';
$activePage = 'slfa';
require_once 'partials/header.php';
?>
<div id="slfa-page" class="dashboard-container">
<?php require_once 'partials/sidebar.php'; ?>

<main class="main-content">
    <header class="page-header">
        <h1>
            <i class="fas fa-file-invoice-dollar"></i>
            <?php echo htmlspecialchars(t('payment_management', 'Payment Management')); ?>
        </h1>
        <div class="user-info">
            <span><?php echo htmlspecialchars(t('welcome', 'Welcome')); ?>, <?php echo htmlspecialchars($_SESSION['username']); ?></span>
        </div>
    </header>

    <div class="content-wrapper slfa-wrapper">
        <div class="slfa-layout">

            <!-- ══ LEFT: Stakeholder list ══ -->
            <aside class="slfa-sidebar-panel">
                <div class="slfa-panel-head">
                    <h2><i class="fas fa-users"></i> <?php echo htmlspecialchars(t('stakeholders', 'Stakeholders')); ?></h2>
                    <div class="slfa-search-wrap">
                        <i class="fas fa-search slfa-search-icon"></i>
                        <input type="text" id="slfa-search" class="slfa-search-input" placeholder="<?php echo htmlspecialchars(t('search', 'Search...')); ?>">
                    </div>
                </div>
                <div class="slfa-stakeholder-list" id="slfa-stakeholder-list">
                    <?php if (empty($stakeholders)): ?>
                        <div class="slfa-empty-list">
                            <i class="fas fa-user-slash"></i>
                            <p><?php echo htmlspecialchars(t('no_stakeholders', 'No stakeholders found.')); ?></p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($stakeholders as $s): ?>
                            <?php
                            $pendingCount = (int)$s['pending_count'];
                            $pendingValue = (float)$s['pending_value'];
                            $totalSlfaSqm = (float)$s['total_slfa_sqm'];
                            ?>
                            <div class="slfa-stakeholder-card <?php echo $pendingCount > 0 ? 'has-pending' : ''; ?>"
                                 data-stakeholder-id="<?php echo (int)$s['id']; ?>"
                                 data-stakeholder-name="<?php echo htmlspecialchars($s['stakeholder_name']); ?>"
                                 onclick="selectStakeholder(<?php echo (int)$s['id']; ?>)">
                                <div class="slfa-card-top">
                                    <div class="slfa-card-name">
                                        <i class="fas fa-hard-hat"></i>
                                        <span><?php echo htmlspecialchars($s['stakeholder_name']); ?></span>
                                    </div>
                                    <?php if ($pendingCount > 0): ?>
                                        <span class="slfa-badge-pending"><?php echo $pendingCount; ?> pending</span>
                                    <?php elseif ((int)$s['payment_count'] > 0): ?>
                                        <span class="slfa-badge-done"><i class="fas fa-check-double"></i> <?php echo htmlspecialchars(t('settled_only', 'Settled')); ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="slfa-card-type">
                                    <i class="fas fa-layer-group"></i>
                                    <?php echo htmlspecialchars($s['work_type_label']); ?>
                                </div>
                                <div class="slfa-card-stats">
                                    <span class="sc-stat sc-accepted" title="Accepted"><i class="fas fa-check"></i> <?php echo (int)$s['accepted_count']; ?></span>
                                    <span class="sc-stat sc-medium"   title="Medium"><i class="fas fa-minus"></i> <?php echo (int)$s['medium_count']; ?></span>
                                    <span class="sc-stat sc-rejected" title="Rejected"><i class="fas fa-times"></i> <?php echo (int)$s['rejected_count']; ?></span>
                                    <?php if ($pendingValue > 0): ?>
                                        <span class="sc-stat sc-value">&dollar;<?php echo number_format($pendingValue, 0); ?></span>
                                    <?php endif; ?>
                                </div>
                                <?php if ($totalSlfaSqm > 0): ?>
                                    <div class="slfa-card-slfa-total">
                                        <i class="fas fa-building"></i> <?php echo htmlspecialchars(t('total_payment_earned', 'Total Payment Earned')); ?>: <strong><?php echo number_format($totalSlfaSqm, 2); ?> m²</strong>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </aside>

            <!-- ══ RIGHT: Detail panel ══ -->
            <div class="slfa-main-panel" id="slfa-main-panel">

                <!-- Empty state -->
                <div class="slfa-empty-state" id="slfa-empty-state">
                    <div class="slfa-empty-icon"><i class="fas fa-file-invoice-dollar"></i></div>
                    <h3><?php echo htmlspecialchars(t('select_stakeholder', 'Select a Stakeholder')); ?></h3>
                    <p><?php echo htmlspecialchars(t('select_stakeholder_hint', 'Choose a stakeholder from the list to view their work entries and calculate payments.')); ?></p>
                </div>

                <!-- Detail view (hidden until selected) -->
                <div class="slfa-detail-view" id="slfa-detail-view" style="display:none">

                    <!-- Stakeholder header -->
                    <div class="slfa-detail-head" id="slfa-detail-head"></div>

                    <!-- Payment settings banner -->
                    <div class="slfa-pay-settings" id="slfa-pay-settings"></div>

                    <!-- ── Entries ── -->
                    <div class="slfa-section-card">
                        <div class="slfa-section-head">
                            <h3><i class="fas fa-list-check"></i> <?php echo htmlspecialchars(t('work_entries', 'Work Entries')); ?></h3>
                            <div class="slfa-entry-filters">
                                <select id="slfa-status-filter" onchange="loadEntries()">
                                    <option value=""><?php echo htmlspecialchars(t('all_statuses', 'All Statuses')); ?></option>
                                    <option value="accepted" selected><?php echo htmlspecialchars(t('accepted_only', 'Accepted only')); ?></option>
                                    <option value="medium"><?php echo htmlspecialchars(t('medium_only', 'Medium only')); ?></option>
                                    <option value="rejected"><?php echo htmlspecialchars(t('rejected_only', 'Rejected only')); ?></option>
                                </select>
                                <select id="slfa-settled-filter" onchange="loadEntries()">
                                    <option value="unsettled" selected><?php echo htmlspecialchars(t('unsettled_only', 'Unsettled only')); ?></option>
                                    <option value=""><?php echo htmlspecialchars(t('all_entries', 'All entries')); ?></option>
                                    <option value="settled"><?php echo htmlspecialchars(t('settled_only', 'Settled only')); ?></option>
                                </select>
                                <button class="slfa-btn slfa-btn-ghost" onclick="toggleSelectAll()">
                                    <i class="fas fa-check-square"></i> <?php echo htmlspecialchars(t('toggle_all', 'Toggle All')); ?>
                                </button>
                            </div>
                        </div>
                        <div class="slfa-table-wrap" id="slfa-table-wrap">
                            <table class="slfa-table">
                                <thead>
                                    <tr>
                                        <th class="col-check">
                                            <input type="checkbox" id="check-all" onchange="checkAllEntries(this)" title="Select all">
                                        </th>
                                        <th><?php echo htmlspecialchars(t('date', 'Date')); ?></th>
                                        <th><?php echo htmlspecialchars(t('building_floor_apt', 'Building / Floor / Apt')); ?></th>
                                        <th><?php echo htmlspecialchars(t('subpart_work', 'Subpart / Work')); ?></th>
                                        <th><?php echo htmlspecialchars(t('quantity', 'Qty')); ?></th>
                                        <th><?php echo htmlspecialchars(t('unit_price', 'Unit Price')); ?></th>
                                        <th><?php echo htmlspecialchars(t('total', 'Total')); ?></th>
                                        <th><?php echo htmlspecialchars(t('status', 'Status')); ?></th>
                                        <th><?php echo htmlspecialchars(t('settlement', 'Settlement')); ?></th>
                                    </tr>
                                </thead>
                                <tbody id="slfa-entries-tbody">
                                    <tr><td colspan="9" class="slfa-empty-row"><?php echo htmlspecialchars(t('select_to_view', 'Select a stakeholder to view entries.')); ?></td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- ── Calculator ── -->
                    <div class="slfa-section-card slfa-calc-section" id="slfa-calculator">
                        <div class="slfa-section-head">
                            <h3><i class="fas fa-calculator"></i> <?php echo htmlspecialchars(t('payment_calculator', 'Payment Calculator')); ?></h3>
                            <button class="slfa-btn slfa-btn-ghost" onclick="recalculate()">
                                <i class="fas fa-sync-alt"></i> <?php echo htmlspecialchars(t('recalculate', 'Recalculate')); ?>
                            </button>
                        </div>

                        <div class="slfa-calc-grid">
                            <div class="slfa-calc-card calc-total">
                                <div class="calc-icon"><i class="fas fa-sigma"></i></div>
                                <div class="calc-body">
                                    <span class="calc-label"><?php echo htmlspecialchars(t('selected_work_value', 'Selected Work Value')); ?></span>
                                    <strong class="calc-value" id="calc-total-value">$0.00</strong>
                                    <span class="calc-sub" id="calc-entries-count">0 entries selected</span>
                                </div>
                            </div>
                            <div class="slfa-calc-card calc-cash">
                                <div class="calc-icon"><i class="fas fa-money-bill-wave"></i></div>
                                <div class="calc-body">
                                    <span class="calc-label"><?php echo htmlspecialchars(t('cash_payment', 'Cash Payment')); ?></span>
                                    <strong class="calc-value" id="calc-cash-amount">$0.00</strong>
                                    <span class="calc-sub" id="calc-cash-pct">0% of total</span>
                                </div>
                            </div>
                            <div class="slfa-calc-card calc-apt">
                                <div class="calc-icon"><i class="fas fa-building"></i></div>
                                <div class="calc-body">
                                    <span class="calc-label"><?php echo htmlspecialchars(t('apartment_value', 'Apartment Value')); ?></span>
                                    <strong class="calc-value" id="calc-apt-amount">$0.00</strong>
                                    <span class="calc-sub" id="calc-apt-pct">0% of total</span>
                                </div>
                            </div>
                            <div class="slfa-calc-card calc-slfa">
                                <div class="calc-icon"><i class="fas fa-vector-square"></i></div>
                                <div class="calc-body">
                                    <span class="calc-label"><?php echo htmlspecialchars(t('payment', 'Payment')); ?></span>
                                    <strong class="calc-value slfa-sqm-val" id="calc-apt-sqm">0.0000 m²</strong>
                                    <span class="calc-sub" id="calc-apt-price">@ $0.00/m²</span>
                                </div>
                            </div>
                        </div>

                        <div class="slfa-payment-form">
                            <div class="slfa-form-row">
                                <label for="slfa-payment-date"><i class="fas fa-calendar-alt"></i> <?php echo htmlspecialchars(t('payment_date', 'Payment Date')); ?></label>
                                <input type="date" id="slfa-payment-date" value="<?php echo date('Y-m-d'); ?>">
                            </div>
                            <div class="slfa-form-row">
                                <label for="slfa-payment-notes"><i class="fas fa-sticky-note"></i> <?php echo htmlspecialchars(t('notes', 'Notes')); ?></label>
                                <input type="text" id="slfa-payment-notes" placeholder="<?php echo htmlspecialchars(t('optional_payment_notes', 'Optional notes for this payment...')); ?>">
                            </div>
                            <button class="slfa-btn slfa-btn-primary slfa-create-btn" id="slfa-create-btn" onclick="createSlfaPayment()" disabled>
                                <i class="fas fa-file-invoice-dollar"></i> <?php echo htmlspecialchars(t('create_payment', 'Create Payment')); ?>
                            </button>
                        </div>
                    </div>

                    <!-- ── Payment History ── -->
                    <div class="slfa-section-card">
                        <div class="slfa-section-head">
                            <h3><i class="fas fa-history"></i> <?php echo htmlspecialchars(t('payment_history', 'Payment History')); ?></h3>
                        </div>
                        <div class="slfa-table-wrap">
                            <table class="slfa-table slfa-history-table">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Date</th>
                                        <th>Entries</th>
                                        <th>Work Value</th>
                                        <th>Cash (<span id="hist-cash-pct">—</span>%)</th>
                                        <th>Apt Value (<span id="hist-apt-pct">—</span>%)</th>
                                        <th><?php echo htmlspecialchars(t('payment_m2', 'Payment m2')); ?></th>
                                        <th>Notes</th>
                                    </tr>
                                </thead>
                                <tbody id="slfa-history-tbody">
                                    <tr><td colspan="8" class="slfa-empty-row"><?php echo htmlspecialchars(t('no_payment_records', 'No payment records yet.')); ?></td></tr>
                                </tbody>
                                <tfoot id="slfa-history-tfoot" style="display:none">
                                    <tr class="slfa-tfoot-totals">
                                        <td colspan="3"><strong>Totals</strong></td>
                                        <td id="hist-total-work"></td>
                                        <td id="hist-total-cash"></td>
                                        <td id="hist-total-apt"></td>
                                        <td id="hist-total-sqm"></td>
                                        <td></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>

                </div><!-- /slfa-detail-view -->
            </div><!-- /slfa-main-panel -->

        </div><!-- /slfa-layout -->
    </div><!-- /content-wrapper -->
</main>

<!-- ══ Payment Detail Modal ══════════════════════════════════════════════ -->
<div class="slfa-modal-overlay" id="payment-detail-overlay" onclick="closePaymentDetail(event)">
    <div class="slfa-modal-card" id="payment-detail-modal">

        <div class="slfa-modal-header">
            <div class="slfa-modal-title">
                <i class="fas fa-file-invoice-dollar"></i>
                <div>
                    <h2 id="modal-title"><?php echo htmlspecialchars(t('payment_detail', 'Payment Detail')); ?></h2>
                    <span id="modal-subtitle" class="slfa-modal-sub"></span>
                </div>
            </div>
            <div class="slfa-modal-header-actions">
                <button class="slfa-modal-close slfa-modal-print" id="btn-print-payment" onclick="printPaymentDetail()" title="Print" disabled>
                    <i class="fas fa-print"></i>
                </button>
                <button class="slfa-modal-close" onclick="closePaymentDetail()" title="Close">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>

        <div class="slfa-modal-body" id="payment-detail-body">
            <div class="slfa-modal-loading"><i class="fas fa-spinner fa-spin"></i> Loading…</div>
        </div>

    </div>
</div>

<!-- ══ Status Change Modal ═════════════════════════════════════════════ -->
<div class="slfa-modal-overlay" id="slfa-sc-modal-overlay" onclick="closeSlfaStatusModal(event)">
    <div class="slfa-sc-modal-card">
        <div class="slfa-modal-header">
            <div class="slfa-modal-title">
                <i class="fas fa-exchange-alt"></i>
                <div>
                    <h2><?php echo htmlspecialchars(t('change_entry_status', 'Change entry status')); ?></h2>
                    <span class="slfa-modal-sub" id="slfa-sc-current-label"><?php echo htmlspecialchars(t('current', 'Current')); ?>: —</span>
                </div>
            </div>
            <button class="slfa-modal-close" onclick="closeSlfaStatusModal()" title="<?php echo htmlspecialchars(t('close', 'Close')); ?>">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="slfa-sc-modal-body">
            <p class="slfa-sc-choose-label"><?php echo htmlspecialchars(t('choose_new_status', 'Choose new status:')); ?></p>
            <div class="slfa-sc-status-grid">
                <button type="button" class="slfa-sc-status-btn slfa-sc-btn-accepted" onclick="selectSlfaStatus('accepted')">
                    <i class="fas fa-check-circle"></i>
                    <strong><?php echo htmlspecialchars(t('accepted', 'Accepted')); ?></strong>
                    <small><?php echo htmlspecialchars(t('ready_for_payment', 'Ready for payment')); ?></small>
                </button>
                <button type="button" class="slfa-sc-status-btn slfa-sc-btn-medium" onclick="selectSlfaStatus('medium')">
                    <i class="fas fa-minus-circle"></i>
                    <strong><?php echo htmlspecialchars(t('medium', 'Medium')); ?></strong>
                    <small><?php echo htmlspecialchars(t('needs_review', 'Needs review')); ?></small>
                </button>
                <button type="button" class="slfa-sc-status-btn slfa-sc-btn-rejected" onclick="selectSlfaStatus('rejected')">
                    <i class="fas fa-times-circle"></i>
                    <strong><?php echo htmlspecialchars(t('rejected', 'Rejected')); ?></strong>
                    <small><?php echo htmlspecialchars(t('cannot_be_paid', 'Cannot be paid')); ?></small>
                </button>
            </div>
        </div>
        <div class="slfa-sc-modal-footer">
            <button type="button" class="slfa-btn slfa-btn-ghost" onclick="closeSlfaStatusModal()">
                <i class="fas fa-times"></i> <?php echo htmlspecialchars(t('cancel', 'Cancel')); ?>
            </button>
            <button type="button" class="slfa-btn slfa-btn-primary" id="slfa-sc-save-btn" disabled onclick="saveSlfaStatus()">
                <i class="fas fa-save"></i> <?php echo htmlspecialchars(t('save_status', 'Save Status')); ?>
            </button>
        </div>
    </div>
</div>

</div><!-- /slfa-page -->

<script>
const slfaStakeholders = <?php echo json_encode($stakeholders, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP); ?>;
const translations = <?php echo translation_json(); ?>;
const tr = (key, fallback) => translations[key] || fallback || key;

let currentStakeholderId  = null;
let currentStakeholderObj = null;

// ─── Select stakeholder ───────────────────────────────────────────────────
function selectStakeholder(id) {
    currentStakeholderId  = id;
    currentStakeholderObj = slfaStakeholders.find(s => parseInt(s.id) === parseInt(id)) || null;

    document.querySelectorAll('.slfa-stakeholder-card').forEach(c => c.classList.remove('active'));
    const card = document.querySelector('.slfa-stakeholder-card[data-stakeholder-id="' + id + '"]');
    if (card) card.classList.add('active');

    document.getElementById('slfa-empty-state').style.display  = 'none';
    document.getElementById('slfa-detail-view').style.display  = 'block';

    renderDetailHead();
    loadEntries();
    loadPaymentHistory();
}

// ─── Render header + payment settings ────────────────────────────────────
function renderDetailHead() {
    const s = currentStakeholderObj;
    if (!s) return;

    const cashPct  = parseFloat(s.cash_percentage)       || 0;
    const aptPct   = parseFloat(s.apartment_percentage)   || 0;
    const aptPrice = parseFloat(s.apartment_meter_price)  || 0;

    document.getElementById('slfa-detail-head').innerHTML = `
        <div class="dhead-identity">
            <div class="dhead-avatar"><i class="fas fa-hard-hat"></i></div>
            <div>
                <h2>${escHtml(s.stakeholder_name)}</h2>
                <span class="dhead-type">${escHtml(s.work_type_label)}</span>
            </div>
        </div>
        <div class="dhead-counts">
            <div class="dhead-count acc" title="${tr('pending_accepted', 'Pending Accepted')}"><i class="fas fa-check-circle"></i><span>${parseInt(s.accepted_count)}</span><small>${tr('pending_accepted', 'Pending Accepted')}</small></div>
            <div class="dhead-count med" title="${tr('pending_medium', 'Pending Medium')}"><i class="fas fa-minus-circle"></i><span>${parseInt(s.medium_count)}</span><small>${tr('pending_medium', 'Pending Medium')}</small></div>
            <div class="dhead-count rej" title="${tr('pending_rejected', 'Pending Rejected')}"><i class="fas fa-times-circle"></i><span>${parseInt(s.rejected_count)}</span><small>${tr('pending_rejected', 'Pending Rejected')}</small></div>
        </div>
    `;

    document.getElementById('slfa-pay-settings').innerHTML = `
        <div class="pay-set-grid">
            <div class="pay-set-item cash">
                <i class="fas fa-money-bill-wave"></i>
                <div><span>${tr('cash_percent', 'Cash %')}</span><strong>${cashPct.toFixed(1)}%</strong></div>
            </div>
            <div class="pay-set-item apt">
                <i class="fas fa-building"></i>
                <div><span>${tr('apartment_percent', 'Apartment %')}</span><strong>${aptPct.toFixed(1)}%</strong></div>
            </div>
            <div class="pay-set-item price">
                <i class="fas fa-tag"></i>
                <div><span>${tr('price_per_sqm', 'Price / m2')}</span><strong>$${formatNum(aptPrice)}/m²</strong></div>
            </div>
            <div class="pay-set-item pending">
                <i class="fas fa-coins"></i>
                <div><span>${tr('unsettled_value', 'Unsettled Value')}</span><strong>$${formatNum(parseFloat(s.pending_value) || 0)}</strong></div>
            </div>
            <div class="pay-set-item slfa-accum">
                <i class="fas fa-vector-square"></i>
                <div><span>${tr('total_payment_earned', 'Total Payment Earned')}</span><strong>${parseFloat(s.total_slfa_sqm || 0).toFixed(4)} m²</strong></div>
            </div>
        </div>
        ${parseInt(s.rejected_count) > 0 ? `<div class="slfa-rejected-warning"><i class="fas fa-exclamation-triangle"></i> <strong>${parseInt(s.rejected_count)}</strong> ${tr('rejected_entries_warning', 'rejected entries need status change to Accepted before payment.')}</div>` : ''}
    `;

    // Update column header percentages in history table
    const elCashPct = document.getElementById('hist-cash-pct');
    const elAptPct  = document.getElementById('hist-apt-pct');
    if (elCashPct) elCashPct.textContent = cashPct.toFixed(1);
    if (elAptPct)  elAptPct.textContent  = aptPct.toFixed(1);
}

// ─── Load entries ─────────────────────────────────────────────────────────
function loadEntries() {
    if (!currentStakeholderId) return;

    const statusFilter  = document.getElementById('slfa-status-filter').value;
    const settledFilter = document.getElementById('slfa-settled-filter').value;

    const tbody = document.getElementById('slfa-entries-tbody');
    tbody.innerHTML = '<tr><td colspan="9" class="slfa-loading-row"><i class="fas fa-spinner fa-spin"></i> ' + escHtml(tr('loading', 'Loading...')) + '</td></tr>';

    const params = new URLSearchParams({
        mode: 'entries',
        stakeholder_id: currentStakeholderId,
        status: statusFilter,
        settled: settledFilter
    });

    fetch('get_slfa_entries.php?' + params.toString())
        .then(r => r.json())
        .then(data => {
            if (!data.success) {
                tbody.innerHTML = '<tr><td colspan="9" class="slfa-empty-row">' + escHtml(data.message || tr('error_loading_entries', 'Error loading entries')) + '</td></tr>';
                return;
            }
            renderEntries(data.entries || []);
        })
        .catch(() => {
            tbody.innerHTML = '<tr><td colspan="9" class="slfa-empty-row">' + escHtml(tr('network_error', 'Network error. Could not load entries.')) + '</td></tr>';
        });
}

// ─── Render entries ───────────────────────────────────────────────────────
function renderEntries(entries) {
    const tbody = document.getElementById('slfa-entries-tbody');
    if (!entries.length) {
        tbody.innerHTML = '<tr><td colspan="9" class="slfa-empty-row">' + escHtml(tr('no_entries_found', 'No entries found for these filters.')) + '</td></tr>';
        recalculate();
        return;
    }

    tbody.innerHTML = entries.map(e => {
        const isSettled  = e.slfa_payment_id && parseInt(e.slfa_payment_id) > 0;
        const isAccepted = e.status === 'accepted';
        const checkedAttr  = (!isSettled && isAccepted) ? 'checked' : '';
        const disabledAttr = isSettled
            ? 'disabled title="' + escHtml(tr('settled', 'Settled')) + ' #' + escHtml(e.slfa_payment_id) + '"'
            : '';
        const settleBadge = isSettled
            ? `<span class="badge-settled"><i class="fas fa-check-double"></i> #${parseInt(e.slfa_payment_id)}</span>`
            : `<span class="badge-unsettled">${tr('pending', 'Pending')}</span>`;

        const statusCell = isSettled
            ? `<span class="status-badge status-${escHtml(e.status)}">${statusLabel(e.status)}</span>`
            : `<button type="button" class="status-badge status-${escHtml(e.status)} slfa-sc-btn" onclick="openSlfaStatusModal(${parseInt(e.id)}, '${escHtml(e.status)}')" title="${tr('click_change_status', 'Click to change status')}">${statusLabel(e.status)} <i class='fas fa-pen sc-pen-icon'></i></button>`;

        return `<tr class="${isSettled ? 'row-settled' : (isAccepted ? 'row-accepted' : '')}">
            <td class="col-check">
                <input type="checkbox" class="entry-check"
                    data-id="${parseInt(e.id)}"
                    data-total="${parseFloat(e.total_price)}"
                    ${checkedAttr} ${disabledAttr}
                    onchange="recalculate()">
            </td>
            <td class="col-date">${escHtml(e.entry_date_display)}</td>
            <td class="col-location">${escHtml(e.location)}</td>
            <td class="col-subpart">${escHtml(e.subpart_label)}</td>
            <td class="col-qty">${parseFloat(e.quantity).toFixed(2)} <small>${escHtml(e.metric_type)}</small></td>
            <td class="col-price">$${formatNum(parseFloat(e.unit_price))}</td>
            <td class="col-total total-cell">$${formatNum(parseFloat(e.total_price))}</td>
            <td>${statusCell}</td>
            <td>${settleBadge}</td>
        </tr>`;
    }).join('');

    recalculate();
}

// ─── Recalculate ─────────────────────────────────────────────────────────
function recalculate() {
    const s = currentStakeholderObj;
    if (!s) return;

    let totalValue = 0;
    let count      = 0;
    document.querySelectorAll('.entry-check:checked:not(:disabled)').forEach(cb => {
        totalValue += parseFloat(cb.getAttribute('data-total') || 0);
        count++;
    });

    const cashPct  = parseFloat(s.cash_percentage)      || 0;
    const aptPct   = parseFloat(s.apartment_percentage)  || 0;
    const aptPrice = parseFloat(s.apartment_meter_price) || 0;

    const cashAmount = totalValue * cashPct / 100;
    const aptAmount  = totalValue * aptPct  / 100;
    const aptSqm     = aptPrice > 0 ? aptAmount / aptPrice : 0;

    document.getElementById('calc-total-value').textContent   = '$' + formatNum(totalValue);
    document.getElementById('calc-entries-count').textContent = count + ' ' + tr(count === 1 ? 'entry_selected' : 'entries_selected', count === 1 ? 'entry selected' : 'entries selected');
    document.getElementById('calc-cash-amount').textContent   = '$' + formatNum(cashAmount);
    document.getElementById('calc-cash-pct').textContent      = cashPct.toFixed(1) + '% ' + tr('of_total', 'of total');
    document.getElementById('calc-apt-amount').textContent    = '$' + formatNum(aptAmount);
    document.getElementById('calc-apt-pct').textContent       = aptPct.toFixed(1) + '% ' + tr('of_total', 'of total');
    document.getElementById('calc-apt-sqm').textContent       = aptSqm.toFixed(4) + ' m²';
    document.getElementById('calc-apt-price').textContent     = '@ $' + formatNum(aptPrice) + '/m²';

    const createBtn = document.getElementById('slfa-create-btn');
    if (createBtn) createBtn.disabled = (count === 0 || totalValue <= 0);
}

// ─── Check-all ───────────────────────────────────────────────────────────
function checkAllEntries(masterCb) {
    document.querySelectorAll('.entry-check:not(:disabled)').forEach(cb => {
        cb.checked = masterCb.checked;
    });
    recalculate();
}

function toggleSelectAll() {
    const all       = document.querySelectorAll('.entry-check:not(:disabled)');
    const allChecked = Array.from(all).every(cb => cb.checked);
    all.forEach(cb => { cb.checked = !allChecked; });
    const masterCb  = document.getElementById('check-all');
    if (masterCb) masterCb.checked = !allChecked;
    recalculate();
}

// ─── Load payment history ─────────────────────────────────────────────────
function loadPaymentHistory() {
    if (!currentStakeholderId) return;

    const tbody = document.getElementById('slfa-history-tbody');
    tbody.innerHTML = '<tr><td colspan="8" class="slfa-loading-row"><i class="fas fa-spinner fa-spin"></i> ' + escHtml(tr('loading', 'Loading...')) + '</td></tr>';

    fetch('get_slfa_entries.php?mode=history&stakeholder_id=' + currentStakeholderId)
        .then(r => r.json())
        .then(data => {
            if (!data.success || !data.payments || !data.payments.length) {
                tbody.innerHTML = '<tr><td colspan="8" class="slfa-empty-row">' + escHtml(tr('no_payment_records_yet', 'No payment records yet.')) + '</td></tr>';
                document.getElementById('slfa-history-tfoot').style.display = 'none';
                return;
            }
            renderPaymentHistory(data.payments);
        });
}

// ─── Render payment history ───────────────────────────────────────────────
function renderPaymentHistory(payments) {
    const tbody = document.getElementById('slfa-history-tbody');
    tbody.innerHTML = payments.map((p, i) => `
        <tr class="history-row-clickable" onclick="openPaymentDetail(${parseInt(p.id)})" title="${tr('click_view_details', 'Click to view full details')}">
            <td class="col-num">${i + 1}</td>
            <td><span class="hist-date-link">${escHtml(p.payment_date_display)}</span></td>
            <td class="col-center">${parseInt(p.entry_count)}</td>
            <td class="total-cell">$${formatNum(parseFloat(p.total_work_value))}</td>
            <td class="cash-col">$${formatNum(parseFloat(p.cash_amount))}</td>
            <td class="apt-col">$${formatNum(parseFloat(p.apartment_amount))}</td>
            <td class="slfa-col"><strong>${parseFloat(p.apartment_sqm).toFixed(4)}</strong> m²</td>
            <td class="notes-cell">${escHtml(p.notes || '—')}</td>
        </tr>
    `).join('');

    const totalWork = payments.reduce((a, p) => a + parseFloat(p.total_work_value), 0);
    const totalCash = payments.reduce((a, p) => a + parseFloat(p.cash_amount), 0);
    const totalApt  = payments.reduce((a, p) => a + parseFloat(p.apartment_amount), 0);
    const totalSqm  = payments.reduce((a, p) => a + parseFloat(p.apartment_sqm), 0);

    document.getElementById('hist-total-work').innerHTML = '<strong>$' + formatNum(totalWork) + '</strong>';
    document.getElementById('hist-total-cash').innerHTML = '<strong>$' + formatNum(totalCash) + '</strong>';
    document.getElementById('hist-total-apt').innerHTML  = '<strong>$' + formatNum(totalApt)  + '</strong>';
    document.getElementById('hist-total-sqm').innerHTML  = '<strong>' + totalSqm.toFixed(4) + ' m²</strong>';
    document.getElementById('slfa-history-tfoot').style.display = '';
}

// ─── Create payment ───────────────────────────────────────────────────────
function createSlfaPayment() {
    const s = currentStakeholderObj;
    if (!s) return;

    const selectedIds = [];
    let totalValue = 0;
    document.querySelectorAll('.entry-check:checked:not(:disabled)').forEach(cb => {
        selectedIds.push(parseInt(cb.getAttribute('data-id')));
        totalValue += parseFloat(cb.getAttribute('data-total') || 0);
    });

    if (selectedIds.length === 0) {
        alert(tr('please_select_entry', 'Please select at least one entry.'));
        return;
    }

    const paymentDate = document.getElementById('slfa-payment-date').value;
    const notes       = document.getElementById('slfa-payment-notes').value.trim();

    if (!paymentDate) {
        alert(tr('please_enter_payment_date', 'Please enter a payment date.'));
        return;
    }

    const cashPct  = parseFloat(s.cash_percentage)      || 0;
    const aptPct   = parseFloat(s.apartment_percentage)  || 0;
    const aptPrice = parseFloat(s.apartment_meter_price) || 0;
    const cashAmount = totalValue * cashPct / 100;
    const aptAmount  = totalValue * aptPct  / 100;
    const aptSqm     = aptPrice > 0 ? aptAmount / aptPrice : 0;

    const msg = `${tr('confirm_create_payment', 'Create Payment?')}\n\n`
        + `${tr('work_value', 'Work Value')}:     $${formatNum(totalValue)}\n`
        + `${tr('cash', 'Cash')} (${cashPct}%): $${formatNum(cashAmount)}\n`
        + `${tr('apartment', 'Apartment')} (${aptPct}%): $${formatNum(aptAmount)}\n`
        + `${tr('payment_m2', 'Payment m2')}:     ${aptSqm.toFixed(4)} m²\n\n`
        + `${tr('this_will_mark', 'This will mark')} ${selectedIds.length} ${tr('entries_selected', 'entries')} ${tr('as_settled', 'as settled.')}`;

    if (!confirm(msg)) return;

    const btn = document.getElementById('slfa-create-btn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> ' + escHtml(tr('saving', 'Saving...'));

    fetch('save_slfa_payment.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            stakeholder_id:      parseInt(currentStakeholderId),
            payment_date:        paymentDate,
            total_work_value:    totalValue,
            cash_percentage:     cashPct,
            cash_amount:         cashAmount,
            apartment_percentage: aptPct,
            apartment_amount:    aptAmount,
            apartment_sqm:       aptSqm,
            apartment_meter_price: aptPrice,
            entry_count:         selectedIds.length,
            notes:               notes,
            entry_ids:           selectedIds
        })
    })
    .then(r => r.json())
    .then(data => {
        btn.innerHTML = '<i class="fas fa-file-invoice-dollar"></i> ' + escHtml(tr('create_payment', 'Create Payment'));
        if (data.success) {
            document.getElementById('slfa-payment-notes').value = '';
            showToast(tr('payment_created', 'Payment created successfully!'), 'success');
            refreshStakeholderStats(currentStakeholderId);
            loadEntries();
            loadPaymentHistory();
        } else {
            btn.disabled = false;
            alert(data.message || tr('error_save_payment', 'Error: Failed to save payment.'));
        }
    })
    .catch(() => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-file-invoice-dollar"></i> ' + escHtml(tr('create_payment', 'Create Payment'));
        alert(tr('network_try_again', 'Network error. Please try again.'));
    });
}

// ─── Refresh stakeholder stats in sidebar after payment ───────────────────
function refreshStakeholderStats(id) {
    fetch('get_slfa_entries.php?mode=stats&stakeholder_id=' + id)
        .then(r => r.json())
        .then(data => {
            if (!data.success || !data.stats) return;
            const s = data.stats;
            currentStakeholderObj = s;
            // Update in the global array too
            const idx = slfaStakeholders.findIndex(x => parseInt(x.id) === parseInt(id));
            if (idx >= 0) slfaStakeholders[idx] = s;
            renderDetailHead();
            // Update sidebar card
            const card = document.querySelector('.slfa-stakeholder-card[data-stakeholder-id="' + id + '"]');
            if (card) {
                const pv = parseFloat(s.pending_value) || 0;
                card.querySelector('.slfa-card-stats').innerHTML = `
                    <span class="sc-stat sc-accepted" title="Accepted"><i class="fas fa-check"></i> ${parseInt(s.accepted_count)}</span>
                    <span class="sc-stat sc-medium"   title="Medium"><i class="fas fa-minus"></i> ${parseInt(s.medium_count)}</span>
                    <span class="sc-stat sc-rejected" title="Rejected"><i class="fas fa-times"></i> ${parseInt(s.rejected_count)}</span>
                    ${pv > 0 ? '<span class="sc-stat sc-value">$' + formatNum(pv, 0) + '</span>' : ''}
                `;
                const pendingCount = parseInt(s.pending_count) || 0;
                card.classList.toggle('has-pending', pendingCount > 0);
            }
        });
}

// ─── Payment Detail Modal ────────────────────────────────────────────────
function openPaymentDetail(paymentId) {
    const overlay = document.getElementById('payment-detail-overlay');
    const body    = document.getElementById('payment-detail-body');
    const title   = document.getElementById('modal-title');
    const sub     = document.getElementById('modal-subtitle');

    title.textContent = tr('payment_number', 'Payment') + ' #' + paymentId;
    sub.textContent   = '';
    body.innerHTML    = '<div class="slfa-modal-loading"><i class="fas fa-spinner fa-spin"></i> ' + escHtml(tr('loading', 'Loading...')) + '</div>';
    document.getElementById('btn-print-payment').disabled = true;
    overlay.classList.add('open');
    document.body.style.overflow = 'hidden';

    const params = new URLSearchParams({
        mode: 'payment_detail',
        stakeholder_id: currentStakeholderId,
        payment_id: paymentId
    });

    fetch('get_slfa_entries.php?' + params.toString())
        .then(r => r.json())
        .then(data => {
            if (!data.success) {
                body.innerHTML = '<div class="slfa-modal-error"><i class="fas fa-exclamation-circle"></i> ' + escHtml(data.message || tr('failed_to_load', 'Failed to load')) + '</div>';
                return;
            }
            renderPaymentDetailModal(data.payment, data.entries || []);
        })
        .catch(() => {
            body.innerHTML = '<div class="slfa-modal-error"><i class="fas fa-exclamation-circle"></i> ' + escHtml(tr('network_error', 'Network error.')) + '</div>';
        });
}

function closePaymentDetail(event) {
    if (event && event.target !== document.getElementById('payment-detail-overlay')) return;
    const overlay = document.getElementById('payment-detail-overlay');
    overlay.classList.remove('open');
    document.body.style.overflow = '';
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closePaymentDetail();
});

function renderPaymentDetailModal(payment, entries) {
    const sub = document.getElementById('modal-subtitle');
    sub.textContent = (payment.stakeholder_name || '') + ' · ' + (payment.work_type_label || '') + ' · ' + (payment.payment_date_display || '');
    document.getElementById('btn-print-payment').disabled = false;

    const createdBy = payment.created_by_fullname || payment.created_by_name || 'Admin';

    // Build entries rows
    const entryRows = entries.length === 0
        ? '<tr><td colspan="8" class="slfa-empty-row">' + escHtml(tr('no_entries_linked', 'No entries linked to this payment.')) + '</td></tr>'
        : entries.map((e, i) => `
            <tr>
                <td class="col-num">${i + 1}</td>
                <td class="col-date">
                    <strong>${escHtml(e.entry_date_display)}</strong>
                    <div class="modal-entry-sub">${tr('submitted', 'Submitted')} ${escHtml(e.submitted_at_display)}</div>
                </td>
                <td>
                    <div>${escHtml(e.location)}</div>
                    <div class="modal-entry-sub">${escHtml(e.subpart_label)}</div>
                </td>
                <td class="modal-eng-cell">
                    <i class="fas fa-user-hard-hat" style="color:#818cf8;margin-right:5px;"></i>${escHtml(e.engineer_name)}
                </td>
                <td>${parseFloat(e.quantity).toFixed(2)} <small style="color:#64748b">${escHtml(e.metric_type)}</small></td>
                <td>$${formatNum(parseFloat(e.unit_price))}</td>
                <td class="total-cell">$${formatNum(parseFloat(e.total_price))}</td>
                <td>
                    <span class="status-badge status-${escHtml(e.status)}">${capitalize(e.status)}</span>
                    ${e.previous_status && e.status_changed_at_display ? (() => {
                        const wasRejected = e.previous_status === 'rejected';
                        const cls = wasRejected ? 'modal-sc-note modal-sc-note-rejected' : 'modal-sc-note';
                        const icon = wasRejected ? 'fas fa-exclamation-triangle' : 'fas fa-history';
                        const label = wasRejected
                            ? `<strong>${tr('rejected_by_engineer', 'Rejected by engineer')}</strong> &mdash; ${tr('accepted_by', 'accepted by')} <strong>${escHtml(e.status_changed_by_name || 'Admin')}</strong>`
                            : `${tr('was', 'Was')} <em>${statusLabel(e.previous_status)}</em> &rarr; ${escHtml(e.status_changed_by_name || 'Admin')}`;
                        return `<div class="${cls}"><i class="${icon}"></i> ${label} &middot; ${escHtml(e.status_changed_at_display)}</div>`;
                    })() : ''}
                </td>
            </tr>
        `).join('');

    const body = document.getElementById('payment-detail-body');
    body.innerHTML = `
        <!-- Summary cards -->
        <div class="modal-summary-grid">
            <div class="modal-sum-card modal-sum-work">
                <i class="fas fa-sigma"></i>
                <div>
                    <span>${tr('work_value', 'Work Value')}</span>
                    <strong>$${formatNum(parseFloat(payment.total_work_value))}</strong>
                </div>
            </div>
            <div class="modal-sum-card modal-sum-cash">
                <i class="fas fa-money-bill-wave"></i>
                <div>
                    <span>${tr('cash', 'Cash')} (${parseFloat(payment.cash_percentage).toFixed(1)}%)</span>
                    <strong>$${formatNum(parseFloat(payment.cash_amount))}</strong>
                </div>
            </div>
            <div class="modal-sum-card modal-sum-apt">
                <i class="fas fa-building"></i>
                <div>
                    <span>${tr('apartment', 'Apartment')} (${parseFloat(payment.apartment_percentage).toFixed(1)}%)</span>
                    <strong>$${formatNum(parseFloat(payment.apartment_amount))}</strong>
                </div>
            </div>
            <div class="modal-sum-card modal-sum-slfa">
                <i class="fas fa-vector-square"></i>
                <div>
                    <span>${tr('payment', 'Payment')} @ $${formatNum(parseFloat(payment.apartment_meter_price))}/m²</span>
                    <strong>${parseFloat(payment.apartment_sqm).toFixed(4)} m²</strong>
                </div>
            </div>
        </div>

        <!-- Meta info -->
        <div class="modal-meta-row">
            <div class="modal-meta-item">
                <i class="fas fa-calendar-check"></i>
                <span>${tr('payment_date', 'Payment Date')}</span>
                <strong>${escHtml(payment.payment_date_display)}</strong>
            </div>
            <div class="modal-meta-item">
                <i class="fas fa-list-ol"></i>
                <span>${tr('entries_settled', 'Entries Settled')}</span>
                <strong>${parseInt(payment.entry_count)}</strong>
            </div>
            <div class="modal-meta-item">
                <i class="fas fa-user-shield"></i>
                <span>${tr('created_by_admin', 'Created by Admin')}</span>
                <strong>${escHtml(createdBy)}</strong>
            </div>
            <div class="modal-meta-item">
                <i class="fas fa-clock"></i>
                <span>${tr('created_at', 'Created at')}</span>
                <strong>${escHtml(payment.created_at_display)}</strong>
            </div>
            ${payment.notes ? `
            <div class="modal-meta-item modal-meta-notes">
                <i class="fas fa-sticky-note"></i>
                <span>${tr('notes', 'Notes')}</span>
                <strong>${escHtml(payment.notes)}</strong>
            </div>` : ''}
        </div>

        <!-- Entries table -->
        <div class="modal-entries-head">
            <h3><i class="fas fa-list-check"></i> ${tr('settled_work_entries', 'Settled Work Entries')} (${entries.length})</h3>
        </div>
        <div class="slfa-table-wrap">
            <table class="slfa-table modal-entries-table">
                <thead>
                    <tr>
                        <th class="col-num">#</th>
                        <th>${tr('work_date_submitted', 'Work Date / Submitted')}</th>
                        <th>${tr('location_subpart', 'Location / Subpart')}</th>
                        <th>${tr('engineer', 'Engineer')}</th>
                        <th>${tr('quantity', 'Qty')}</th>
                        <th>${tr('unit_price', 'Unit Price')}</th>
                        <th>${tr('total', 'Total')}</th>
                        <th>${tr('status', 'Status')}</th>
                    </tr>
                </thead>
                <tbody>${entryRows}</tbody>
                ${entries.length > 0 ? `
                <tfoot>
                    <tr class="slfa-tfoot-totals">
                        <td colspan="6"><strong>Total</strong></td>
                        <td class="total-cell"><strong>$${formatNum(entries.reduce((a, e) => a + parseFloat(e.total_price), 0))}</strong></td>
                        <td></td>
                    </tr>
                </tfoot>` : ''}
            </table>
        </div>
    `;
}

// ─── Print payment detail ────────────────────────────────────────────────
function printPaymentDetail() {
    const title = document.getElementById('modal-title').textContent;
    const sub   = document.getElementById('modal-subtitle').textContent;
    const body  = document.getElementById('payment-detail-body').innerHTML;

    const win = window.open('', '_blank');
    if (!win) return;
    win.document.write('<html><head><title>' + escHtml(title) + '</title><style>'
        + 'body{font-family:sans-serif;padding:24px;color:#111;}'
        + 'h2{margin:0 0 4px;}'
        + '.sub{color:#555;margin:0 0 20px;font-size:13px;}'
        + 'table{border-collapse:collapse;width:100%;margin-top:8px;}'
        + 'th,td{border:1px solid #999;padding:6px 10px;font-size:12px;text-align:left;vertical-align:top;}'
        + 'th{background:#eee;}'
        + '.modal-summary-grid{display:flex;gap:14px;margin-bottom:16px;flex-wrap:wrap;}'
        + '.modal-sum-card{border:1px solid #999;border-radius:6px;padding:10px 14px;flex:1;min-width:150px;}'
        + '.modal-sum-card i{display:none;}'
        + '.modal-sum-card span{display:block;font-size:11px;color:#555;}'
        + '.modal-sum-card strong{display:block;font-size:15px;margin-top:2px;}'
        + '.modal-meta-row{display:flex;gap:18px;flex-wrap:wrap;margin-bottom:16px;}'
        + '.modal-meta-item{font-size:12px;}'
        + '.modal-meta-item i{display:none;}'
        + '.modal-meta-item span{display:block;color:#555;}'
        + '.modal-meta-item strong{display:block;}'
        + '.modal-entry-sub{color:#666;font-size:11px;}'
        + '.status-badge{display:inline-block;padding:2px 6px;border:1px solid #999;border-radius:4px;font-size:11px;}'
        + '.modal-sc-note,.modal-sc-note-rejected{font-size:10px;color:#666;margin-top:3px;}'
        + '.modal-entries-head{margin:18px 0 4px;}'
        + '.modal-entries-head h3{font-size:14px;margin:0;}'
        + '</style></head><body>'
        + '<h2>' + escHtml(title) + '</h2>'
        + '<p class="sub">' + escHtml(sub) + ' &middot; Printed ' + escHtml(new Date().toLocaleString()) + '</p>'
        + body
        + '</body></html>');
    win.document.close();
    win.print();
}

// ─── Status Change Modal ─────────────────────────────────────────────────
let slfaScEntryId   = null;
let slfaScNewStatus = null;

function openSlfaStatusModal(entryId, currentStatus) {
    slfaScEntryId   = entryId;
    slfaScNewStatus = null;
    document.getElementById('slfa-sc-current-label').textContent = tr('current', 'Current') + ': ' + statusLabel(currentStatus);
    document.querySelectorAll('.slfa-sc-status-btn').forEach(b => b.classList.remove('slfa-sc-btn-active'));
    document.getElementById('slfa-sc-save-btn').disabled = true;
    document.getElementById('slfa-sc-modal-overlay').classList.add('open');
    document.body.style.overflow = 'hidden';
}

function closeSlfaStatusModal(event) {
    if (event && event.target !== document.getElementById('slfa-sc-modal-overlay')) return;
    document.getElementById('slfa-sc-modal-overlay').classList.remove('open');
    document.body.style.overflow = '';
}

function selectSlfaStatus(status) {
    slfaScNewStatus = status;
    document.querySelectorAll('.slfa-sc-status-btn').forEach(b => b.classList.remove('slfa-sc-btn-active'));
    const btn = document.querySelector('.slfa-sc-btn-' + status);
    if (btn) btn.classList.add('slfa-sc-btn-active');
    document.getElementById('slfa-sc-save-btn').disabled = false;
}

function saveSlfaStatus() {
    if (!slfaScEntryId || !slfaScNewStatus) return;
    const saveBtn  = document.getElementById('slfa-sc-save-btn');
    const origHtml = saveBtn.innerHTML;
    saveBtn.disabled = true;
    saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> ' + escHtml(tr('saving', 'Saving...'));
    fetch('change_entry_status.php', {
        method:  'POST',
        headers: { 'Content-Type': 'application/json' },
        body:    JSON.stringify({ entry_id: slfaScEntryId, source: 'generic', new_status: slfaScNewStatus })
    })
    .then(r => r.json())
    .then(data => {
        saveBtn.disabled = false;
        saveBtn.innerHTML = origHtml;
        if (data.success) {
            closeSlfaStatusModal();
            showToast(data.message || tr('status_updated', 'Status updated.'), 'success');
            loadEntries();
            refreshStakeholderStats(currentStakeholderId);
        } else {
            showToast(data.message || tr('failed_change_status', 'Failed to change status.'), 'error');
        }
    })
    .catch(() => {
        saveBtn.disabled = false;
        saveBtn.innerHTML = origHtml;
        showToast(tr('network_try_again', 'Network error. Please try again.'), 'error');
    });
}

// ─── Sidebar search ───────────────────────────────────────────────────────
document.getElementById('slfa-search').addEventListener('input', function () {
    const q = this.value.toLowerCase().trim();
    document.querySelectorAll('.slfa-stakeholder-card').forEach(card => {
        const name = card.getAttribute('data-stakeholder-name').toLowerCase();
        card.style.display = name.includes(q) ? '' : 'none';
    });
});

// ─── Toast ────────────────────────────────────────────────────────────────
function showToast(msg, type = 'success') {
    const t = document.createElement('div');
    t.className = 'slfa-toast slfa-toast-' + type;
    t.innerHTML = '<i class="fas fa-' + (type === 'success' ? 'check-circle' : 'exclamation-circle') + '"></i> ' + escHtml(msg);
    document.body.appendChild(t);
    setTimeout(() => t.classList.add('show'), 50);
    setTimeout(() => {
        t.classList.remove('show');
        setTimeout(() => t.remove(), 400);
    }, 3000);
}

// ─── Helpers ──────────────────────────────────────────────────────────────
function escHtml(str) {
    return String(str == null ? '' : str)
        .replace(/&/g, '&amp;').replace(/</g, '&lt;')
        .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

function formatNum(num, decimals = 2) {
    return Number(num || 0).toLocaleString(undefined, {
        minimumFractionDigits: decimals,
        maximumFractionDigits: decimals
    });
}

function capitalize(str) {
    const s = String(str || '');
    return s.charAt(0).toUpperCase() + s.slice(1);
}

function statusLabel(status) {
    const key = String(status || '').toLowerCase();
    return tr(key, capitalize(key));
}
</script>
</body>
</html>
