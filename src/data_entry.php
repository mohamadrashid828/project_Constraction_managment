<?php
session_start();
require_once '../config.php';
require_once 'includes/i18n.php';
require_once 'includes/csrf.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: index.html');
    exit();
}

// Check permissions for data entry
$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("
    SELECT p.name FROM permissions p
    JOIN role_permissions rp ON p.id = rp.permission_id
    JOIN users u ON rp.role_id = u.role_id
    WHERE u.id = ?
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$permissions = [];
while ($row = $result->fetch_assoc()) {
    $permissions[] = $row['name'];
}
$stmt->close();

$canManageHistory = in_array('user_management', $permissions, true);
if (!$canManageHistory && !empty($_SESSION['role_id'])) {
    $roleStmt = $conn->prepare("SELECT name FROM roles WHERE id = ? LIMIT 1");
    if ($roleStmt) {
        $roleId = (int)$_SESSION['role_id'];
        $roleStmt->bind_param("i", $roleId);
        $roleStmt->execute();
        $roleRes = $roleStmt->get_result();
        if ($roleRes && ($roleRow = $roleRes->fetch_assoc())) {
            $canManageHistory = strtolower(trim((string)$roleRow['name'])) === 'manager';
        }
        $roleStmt->close();
    }
}

if (!in_array('data_entry', $permissions)) {
    header('Location: dashboard.php?error=access_denied');
    exit();
}

// Get buildings for location bar
$buildings = $conn->query("SELECT id, building_name FROM buildings ORDER BY building_name");

$conn->query("CREATE TABLE IF NOT EXISTS project_work_types (
    id INT AUTO_INCREMENT PRIMARY KEY,
    work_type_name VARCHAR(120) NOT NULL,
    work_type_key VARCHAR(80) NOT NULL UNIQUE,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_work_type_key (work_type_key)
)");

// Ensure stakeholder + subparts tables exist and load Gechkari pricing map
$conn->query("CREATE TABLE IF NOT EXISTS project_stakeholders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    stakeholder_name VARCHAR(160) NOT NULL,
    work_type_key VARCHAR(80) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_stakeholder_work (stakeholder_name, work_type_key),
    INDEX idx_work_type (work_type_key)
)");

$conn->query("CREATE TABLE IF NOT EXISTS project_stakeholder_subparts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    stakeholder_id INT NOT NULL,
    subpart_name VARCHAR(160) NOT NULL,
    unit_price DECIMAL(12,2) NOT NULL,
    metric_type VARCHAR(30) NOT NULL DEFAULT 'm²',
    currency_type VARCHAR(20) NOT NULL DEFAULT 'USD',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_stakeholder_subpart (stakeholder_id, subpart_name),
    INDEX idx_stakeholder (stakeholder_id)
)");

$colMetric = $conn->query("SHOW COLUMNS FROM project_stakeholder_subparts LIKE 'metric_type'");
if ($colMetric && $colMetric->num_rows === 0) {
    $conn->query("ALTER TABLE project_stakeholder_subparts ADD COLUMN metric_type VARCHAR(30) NOT NULL DEFAULT 'm²' AFTER unit_price");
}

$colCurrency = $conn->query("SHOW COLUMNS FROM project_stakeholder_subparts LIKE 'currency_type'");
if ($colCurrency && $colCurrency->num_rows === 0) {
    $conn->query("ALTER TABLE project_stakeholder_subparts ADD COLUMN currency_type VARCHAR(20) NOT NULL DEFAULT 'USD' AFTER metric_type");
}

function normalize_work_type_key($key) {
    $k = strtolower(trim((string)$key));
    $k = preg_replace('/[^a-z0-9_]+/', '_', $k);
    $k = preg_replace('/_+/', '_', $k);
    return trim($k, '_');
}

$workTypes = [];
$workTypeNameMap = [];
$workTypeKeysSet = [];
$workTypeRes = $conn->query("SELECT work_type_key, work_type_name, work_type_name_ku, image_file FROM project_work_types WHERE is_active = 1 ORDER BY work_type_name");
if ($workTypeRes) {
    while ($wt = $workTypeRes->fetch_assoc()) {
        $key = normalize_work_type_key($wt['work_type_key']);
        if ($key === '') {
            continue;
        }

        $languageCode = $_SESSION['language'] ?? 'en';
        $englishName = trim((string)$wt['work_type_name']) !== '' ? $wt['work_type_name'] : ucfirst($key);
        $kuName = trim((string)($wt['work_type_name_ku'] ?? ''));
        $name = ($languageCode === 'ckb' && $kuName !== '') ? $kuName : $englishName;

        if (isset($workTypeKeysSet[$key])) {
            continue;
        }
        $workTypes[] = ['key' => $key, 'name' => $name, 'image' => $wt['image_file']];
        $workTypeNameMap[$key] = $name;
        $workTypeKeysSet[$key] = true;
    }
}

// Include any active stakeholder work_type_key dynamically, even if missing in project_work_types.
$stakeholderTypeRes = $conn->query("SELECT DISTINCT work_type_key FROM project_stakeholders WHERE is_active = 1 AND work_type_key IS NOT NULL AND TRIM(work_type_key) <> ''");
if ($stakeholderTypeRes) {
    while ($row = $stakeholderTypeRes->fetch_assoc()) {
        $key = normalize_work_type_key($row['work_type_key']);
        if ($key === '' || isset($workTypeKeysSet[$key])) {
            continue;
        }
        $label = ucwords(str_replace('_', ' ', $key));
        $workTypes[] = ['key' => $key, 'name' => $label, 'image' => null];
        $workTypeNameMap[$key] = $label;
        $workTypeKeysSet[$key] = true;
    }
}

usort($workTypes, function($a, $b) {
    return strcasecmp($a['name'], $b['name']);
});

if (empty($workTypes)) {
    $workTypes[] = ['key' => 'gechkari', 'name' => 'Gechkari', 'image' => null];
    $workTypeNameMap['gechkari'] = 'Gechkari';
}

$defaultWorkTypeKey = $workTypes[0]['key'];

// Logged-in user's display name for auto-filling the Engineer Name field.
$engineerName = $_SESSION['username'];
$nameStmt = $conn->prepare("SELECT full_name FROM users WHERE id = ? LIMIT 1");
if ($nameStmt) {
    $nameStmt->bind_param("i", $user_id);
    $nameStmt->execute();
    $nameRes = $nameStmt->get_result();
    if ($nameRes && ($nameRow = $nameRes->fetch_assoc()) && trim((string)$nameRow['full_name']) !== '') {
        $engineerName = trim($nameRow['full_name']);
    }
    $nameStmt->close();
}

$stakeholdersByWorkType = [];
$subpartsByStakeholder = [];
$pricingRes = $conn->query("SELECT s.id AS stakeholder_id, s.stakeholder_name, s.work_type_key, sp.id AS subpart_id, sp.subpart_name, sp.unit_price, sp.metric_type, sp.currency_type
    FROM project_stakeholders s
    LEFT JOIN project_stakeholder_subparts sp ON sp.stakeholder_id = s.id AND sp.is_active = 1
    WHERE s.is_active = 1
    ORDER BY s.work_type_key, s.stakeholder_name, sp.subpart_name");

if ($pricingRes) {
    while ($row = $pricingRes->fetch_assoc()) {
        $sid = (int)$row['stakeholder_id'];
        $sname = $row['stakeholder_name'];
        $workTypeKey = normalize_work_type_key($row['work_type_key']);
        if (!isset($stakeholdersByWorkType[$workTypeKey])) {
            $stakeholdersByWorkType[$workTypeKey] = [];
        }
        if (!isset($stakeholdersByWorkType[$workTypeKey][$sid])) {
            $stakeholdersByWorkType[$workTypeKey][$sid] = ['id' => $sid, 'name' => $sname];
        }

        if (!isset($subpartsByStakeholder[$sid])) {
            $subpartsByStakeholder[$sid] = [];
        }
        if (!empty($row['subpart_id'])) {
            $subpartsByStakeholder[$sid][] = [
                'id' => (int)$row['subpart_id'],
                'name' => $row['subpart_name'],
                'price' => (float)$row['unit_price'],
                'metric' => $row['metric_type'] ?: 'm²',
                'currency' => $row['currency_type'] ?: 'USD'
            ];
        }
    }
}

foreach ($stakeholdersByWorkType as $wtKey => $stMap) {
    $stakeholdersByWorkType[$wtKey] = array_values($stMap);
}

$pageTitle = t('work_data_entry', 'Work Data Entry') . ' - ' . t('construction_management', 'Construction Management');
$pageCss = 'data_entry.css';
$activePage = 'data-entry';
require_once 'partials/header.php';
?>
    <div id="data-entry-page" class="dashboard-container">
<?php
require_once 'partials/sidebar.php';
?>

        <!-- Main Content -->
        <main class="main-content">
            <header class="page-header">
                <h1><i class="fas fa-clipboard-list"></i> <?php echo htmlspecialchars(t('work_data_entry', 'Work Data Entry')); ?></h1>
                <div class="user-info">
                    <span><?php echo htmlspecialchars(t('welcome', 'Welcome')); ?>, <?php echo htmlspecialchars($_SESSION['username']); ?></span>
                </div>
            </header>

            <div class="content-wrapper gechkari-content-wrapper">

                <!-- Work Type Selector (extensible for future types) -->
                <div class="work-type-selector-card">
                    <div class="work-type-selector-head">
                        <span class="work-type-selector-title"><i class="fas fa-layer-group"></i> <?php echo htmlspecialchars(t('entry_types', 'Entry Types')); ?></span>
                        <span class="work-type-selector-sub"><?php echo htmlspecialchars(t('choose_work_category', 'Choose a work category')); ?></span>
                    </div>
                    <div class="work-type-grid">
                        <?php foreach ($workTypes as $idx => $wt): ?>
                            <button type="button" class="work-type-item <?php echo $idx === 0 ? 'is-active' : ''; ?>" data-work-type="<?php echo htmlspecialchars($wt['key']); ?>" data-work-type-name="<?php echo htmlspecialchars($wt['name']); ?>">
                                <span class="work-type-icon">
                                    <?php if (!empty($wt['image'])): ?>
                                        <img src="category_image.php?key=<?php echo urlencode($wt['key']); ?>" alt="">
                                    <?php else: ?>
                                        <i class="fas fa-hard-hat"></i>
                                    <?php endif; ?>
                                </span>
                                <span class="work-type-text">
                                    <strong><?php echo htmlspecialchars($wt['name']); ?></strong>
                                </span>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div id="gechkari-entry-section">

                <!-- Location Bar -->
                <div class="location-bar-card">
                    <div class="location-bar-label">
                        <i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars(t('select_location', 'Select Location')); ?>
                    </div>
                    <div class="location-bar-selects">
                        <div class="loc-group">
                            <label><?php echo htmlspecialchars(t('building', 'Building')); ?></label>
                            <select id="sel-building" class="location-select">
                                <option value="">— <?php echo htmlspecialchars(t('building', 'Building')); ?> —</option>
                                <option value="0">— <?php echo htmlspecialchars(t('no_building_project_wide', 'No Building (Project-Wide)')); ?> —</option>
                                <?php $buildings->data_seek(0); while ($b = $buildings->fetch_assoc()): ?>
                                <option value="<?php echo $b['id']; ?>"><?php echo htmlspecialchars($b['building_name']); ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="loc-arrow"><i class="fas fa-chevron-right"></i></div>
                        <div class="loc-group">
                            <label><?php echo htmlspecialchars(t('floor', 'Floor')); ?></label>
                            <select id="sel-floor" class="location-select" disabled>
                                <option value="">— <?php echo htmlspecialchars(t('floor', 'Floor')); ?> —</option>
                            </select>
                        </div>
                        <div class="loc-arrow"><i class="fas fa-chevron-right"></i></div>
                        <div class="loc-group">
                            <label><?php echo htmlspecialchars(t('apartment', 'Apartment')); ?></label>
                            <select id="sel-apartment" class="location-select" disabled>
                                <option value="">— <?php echo htmlspecialchars(t('apartment', 'Apartment')); ?> —</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Empty State -->
                <div id="empty-state" class="empty-state-card">
                    <div class="empty-icon"><i class="fas fa-building"></i></div>
                    <p><?php echo htmlspecialchars(t('select_building_floor_apartment_help', 'Select a building, floor, and apartment above<br>to start recording work entries.')); ?></p>
                </div>

                <!-- Workspace (shown when apartment is selected) -->
                <div id="gechkari-workspace" style="display:none;">

                    <div class="workspace-grid">

                        <!-- Apartment Info Card -->
                        <div class="apt-info-card">
                            <div class="apt-info-header">
                                <div class="apt-badge"><i class="fas fa-door-open"></i></div>
                                <div class="apt-meta">
                                    <strong id="apt-number">—</strong>
                                    <span id="apt-location" class="apt-location">—</span>
                                    <span id="apt-type-badge" class="apt-type-tag">—</span>
                                </div>
                            </div>
                            <div class="apt-stats-grid">
                                <div class="apt-stat">
                                    <div class="apt-stat-icon stat-blue">
                                        <i class="fas fa-ruler-combined stat-icon-blue"></i>
                                    </div>
                                    <div>
                                        <div class="apt-stat-value" id="apt-area">—</div>
                                        <div class="apt-stat-label">Total Area</div>
                                    </div>
                                </div>
                                <div class="apt-stat">
                                    <div class="apt-stat-icon stat-green">
                                        <i class="fas fa-check-circle stat-icon-green"></i>
                                    </div>
                                    <div>
                                        <div class="apt-stat-value" id="apt-recorded">—</div>
                                        <div class="apt-stat-label">Recorded</div>
                                    </div>
                                </div>
                                <div class="apt-stat">
                                    <div class="apt-stat-icon stat-amber">
                                        <i class="fas fa-calendar-check stat-icon-amber"></i>
                                    </div>
                                    <div>
                                        <div class="apt-stat-value" id="apt-visits">—</div>
                                        <div class="apt-stat-label">Visits</div>
                                    </div>
                                </div>
                                <div class="apt-stat"<?php echo $canManageHistory ? '' : ' style="display:none;"'; ?>>
                                    <div class="apt-stat-icon stat-violet">
                                        <i class="fas fa-coins stat-icon-violet"></i>
                                    </div>
                                    <div>
                                        <div class="apt-stat-value" id="apt-total-value">—</div>
                                        <div class="apt-stat-label">Total Value</div>
                                    </div>
                                </div>
                            </div>
                            <div class="gech-progress">
                                <div class="gech-progress-header">
                                    <span class="gech-progress-title">
                                        <i class="fas fa-tasks"></i> Gechkari Progress
                                    </span>
                                    <span class="gech-progress-pct" id="progress-pct">0%</span>
                                </div>
                                <div class="gech-bar-track">
                                    <div class="gech-bar-fill" id="progress-bar" style="width:0%"></div>
                                </div>
                                <div class="gech-bar-sub" id="progress-sub">0 / 0 m² completed</div>
                            </div>
                        </div>

                        <!-- Entry Form Card -->
                        <div class="entry-form-card">
                            <div class="entry-form-title">
                                <i class="fas fa-plus-circle"></i> <?php echo htmlspecialchars(t('record_new_entry', 'Record New Entry')); ?>
                            </div>
                            <form id="gechkari-form">
                                <input type="hidden" id="form-apartment-id">
                                <input type="hidden" id="form-floor-id">
                                <input type="hidden" id="form-building-id">
                                <input type="hidden" id="entry-work-type-key" value="<?php echo htmlspecialchars($defaultWorkTypeKey); ?>">
                                <div class="entry-form-grid">
                                    <div class="form-group">
                                        <label><i class="fas fa-calendar-alt"></i> <?php echo htmlspecialchars(t('visit_date', 'Visit Date')); ?></label>
                                        <input type="date" id="entry-date" required>
                                    </div>
                                    <div class="form-group">
                                        <label><i class="fas fa-user-tie"></i> <?php echo htmlspecialchars(t('engineer_name', 'Engineer Name')); ?> <span class="optional"><?php echo htmlspecialchars(t('auto_from_account', 'auto from account')); ?></span></label>
                                        <input type="text" id="entry-engineer-name" value="<?php echo htmlspecialchars($engineerName); ?>" readonly required>
                                    </div>
                                    <div class="form-group">
                                        <label><i class="fas fa-briefcase"></i> <?php echo htmlspecialchars(t('type_of_work', 'Type of Work')); ?></label>
                                        <input type="text" id="entry-work-type-name" readonly value="<?php echo htmlspecialchars($workTypeNameMap[$defaultWorkTypeKey] ?? ucfirst($defaultWorkTypeKey)); ?>">
                                    </div>
                                    <div class="form-group">
                                        <label><i class="fas fa-users"></i> <?php echo htmlspecialchars(t('stakeholder_team', 'Stakeholder / Team')); ?> <span class="optional"><?php echo htmlspecialchars(t('optional', 'optional')); ?></span></label>
                                        <select id="entry-stakeholder">
                                            <option value=""><?php echo htmlspecialchars(t('select_stakeholder', 'Select stakeholder')); ?></option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label><i class="fas fa-th-list"></i> <?php echo htmlspecialchars(t('sub_work', 'Sub Work')); ?> <span class="optional"><?php echo htmlspecialchars(t('if_available', 'if available')); ?></span></label>
                                        <select id="entry-subpart" disabled>
                                            <option value=""><?php echo htmlspecialchars(t('select_sub_work', 'Select sub work')); ?></option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label><i class="fas fa-ruler-combined"></i> <span id="entry-qty-label-text"><?php echo htmlspecialchars(t('finished_quantity', 'Finished Quantity')); ?></span></label>
                                        <input type="number" id="entry-qty" step="0.01" min="0.01" placeholder="0.00" required>
                                        <span id="entry-qty-unit-hint" class="entry-unit-hint"><?php echo htmlspecialchars(t('unit_hint', 'Unit: —')); ?></span>
                                    </div>
                                    <div class="form-group"<?php echo $canManageHistory ? '' : ' style="display:none;"'; ?>>
                                        <label><i class="fas fa-tag"></i> <span id="entry-unit-price-label-text"><?php echo htmlspecialchars(t('unit_price', 'Unit Price')); ?></span></label>
                                        <input type="number" id="entry-unit-price" step="0.01" min="0" placeholder="<?php echo htmlspecialchars(t('select_sub_work', 'Select sub work')); ?>" readonly required>
                                    </div>
                                    <div class="form-group">
                                        <label><i class="fas fa-clipboard-check"></i> <?php echo htmlspecialchars(t('engineer_decision', 'Engineer Decision')); ?></label>
                                        <div class="entry-status-pills" id="entry-status-pills">
                                            <button type="button" class="status-pill pill-accepted" data-value="accepted">
                                                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars(t('accepted', 'Accepted')); ?>
                                            </button>
                                            <button type="button" class="status-pill pill-medium active" data-value="medium">
                                                <i class="fas fa-minus-circle"></i> <?php echo htmlspecialchars(t('medium', 'Medium')); ?>
                                            </button>
                                            <button type="button" class="status-pill pill-rejected" data-value="rejected">
                                                <i class="fas fa-times-circle"></i> <?php echo htmlspecialchars(t('rejected', 'Rejected')); ?>
                                            </button>
                                            <input type="hidden" id="entry-status" value="medium">
                                        </div>
                                    </div>
                                    <div class="form-group"<?php echo $canManageHistory ? '' : ' style="display:none;"'; ?>>
                                        <label><i class="fas fa-receipt"></i> <span id="entry-total-label-text"><?php echo htmlspecialchars(t('total_value', 'Total Value')); ?></span></label>
                                        <input type="number" id="entry-total" readonly placeholder="<?php echo htmlspecialchars(t('auto_calculated', 'Auto-calculated')); ?>">
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label><i class="fas fa-sticky-note"></i> <?php echo htmlspecialchars(t('engineer_notes', 'Engineer Notes')); ?> <span class="optional"><?php echo htmlspecialchars(t('optional', 'optional')); ?></span></label>
                                    <textarea id="entry-notes" placeholder="<?php echo htmlspecialchars(t('visit_notes_placeholder', 'Any observations or notes about this visit...')); ?>"></textarea>
                                </div>
                                <div class="entry-form-actions">
                                    <button type="button" class="btn btn-secondary" id="btn-clear">
                                        <i class="fas fa-times"></i> <?php echo htmlspecialchars(t('clear', 'Clear')); ?>
                                    </button>
                                    <button type="submit" class="btn btn-primary" id="btn-save">
                                        <i class="fas fa-save"></i> <?php echo htmlspecialchars(t('save_entry', 'Save Entry')); ?>
                                    </button>
                                </div>
                            </form>
                        </div>

                    </div><!-- /workspace-grid -->

                    <!-- History Card -->
                    <div class="history-card">
                        <div class="history-card-header">
                            <h3><i class="fas fa-history"></i> <?php echo htmlspecialchars(t('history', 'History')); ?> — <span id="history-apt-label">—</span></h3>
                            <span id="history-loading" class="loading-inline" style="display:none;">
                                <i class="fas fa-spinner fa-spin"></i> <?php echo htmlspecialchars(t('loading', 'Loading...')); ?>
                            </span>
                        </div>
                        <div class="table-container">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th><?php echo htmlspecialchars(t('date', 'Date')); ?></th>
                                        <th><?php echo htmlspecialchars(t('work_type', 'Work Type')); ?></th>
                                        <th><?php echo htmlspecialchars(t('sub_work', 'Sub Work')); ?></th>
                                        <th><?php echo htmlspecialchars(t('engineer', 'Engineer')); ?></th>
                                        <th><?php echo htmlspecialchars(t('location', 'Location')); ?></th>
                                        <th><?php echo htmlspecialchars(t('status', 'Status')); ?></th>
                                        <th><?php echo htmlspecialchars(t('notes', 'Notes')); ?></th>
                                        <th><?php echo htmlspecialchars(t('actions', 'Actions')); ?></th>
                                    </tr>
                                </thead>
                                <tbody id="gechkari-history-tbody">
                                    <tr><td colspan="8" class="no-data"><?php echo htmlspecialchars(t('select_apartment_to_view_history', 'Select an apartment to view history')); ?></td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                </div><!-- /gechkari-workspace -->

                </div><!-- /gechkari-entry-section -->

            </div><!-- /content-wrapper -->
        </main>
    </div>

    <div id="toast-container"></div>

    <?php if ($canManageHistory): ?>
    <div id="history-entry-modal" class="modal-overlay de-modal-overlay" style="display:none;">
        <div class="de-modal-card">
            <div class="de-modal-head">
                <h3><i class="fas fa-pen"></i> <?php echo htmlspecialchars(t('edit_history_entry', 'Edit History Entry')); ?></h3>
                <button type="button" class="de-modal-close" id="history-entry-close"><i class="fas fa-times"></i></button>
            </div>
            <form id="history-entry-form" class="de-modal-form">
                <input type="hidden" id="history-entry-id">
                <input type="hidden" id="history-entry-source">
                <div class="de-modal-grid">
                    <div class="form-group">
                        <label><i class="fas fa-calendar-alt"></i> <?php echo htmlspecialchars(t('date', 'Date')); ?></label>
                        <input type="date" id="history-edit-date" required>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-user-tie"></i> <?php echo htmlspecialchars(t('engineer', 'Engineer')); ?></label>
                        <input type="text" id="history-edit-engineer" required>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-ruler-combined"></i> <?php echo htmlspecialchars(t('quantity', 'Quantity')); ?></label>
                        <input type="number" id="history-edit-qty" step="0.01" min="0.01" required>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-tag"></i> <?php echo htmlspecialchars(t('unit_price', 'Unit Price')); ?></label>
                        <input type="number" id="history-edit-unit-price" step="0.01" min="0" required>
                    </div>
                </div>
                <div class="form-group">
                    <label><i class="fas fa-sticky-note"></i> <?php echo htmlspecialchars(t('notes', 'Notes')); ?></label>
                    <textarea id="history-edit-notes" placeholder="<?php echo htmlspecialchars(t('notes_placeholder', 'Notes...')); ?>"></textarea>
                </div>
                <div class="entry-form-actions de-modal-actions">
                    <button type="button" class="btn btn-secondary" id="history-entry-cancel"><i class="fas fa-times"></i> <?php echo htmlspecialchars(t('cancel', 'Cancel')); ?></button>
                    <button type="submit" class="btn btn-primary" id="history-entry-save"><i class="fas fa-save"></i> <?php echo htmlspecialchars(t('update_entry', 'Update Entry')); ?></button>
                </div>
            </form>
        </div>
    </div>

    <!-- ── Status Change Modal ─────────────────────────────────────────── -->
    <div id="de-status-modal" class="de-sc-modal-overlay" style="display:none;">
        <div class="de-sc-modal-card">
            <div class="de-modal-head">
                <h3><i class="fas fa-exchange-alt"></i> <?php echo htmlspecialchars(t('change_entry_status', 'Change Entry Status')); ?></h3>
                <button type="button" class="de-modal-close" id="de-sc-modal-close"><i class="fas fa-times"></i></button>
            </div>
            <div class="de-sc-modal-body">
                <div class="de-sc-current-row">
                    <span><?php echo htmlspecialchars(t('current_status', 'Current status:')); ?></span>
                    <span id="de-sc-current-badge" class="status-badge"></span>
                </div>
                <p class="de-sc-choose-label"><?php echo htmlspecialchars(t('choose_new_status', 'Choose new status:')); ?></p>
                <div class="de-sc-status-grid">
                    <button type="button" class="de-sc-status-btn de-sc-btn-accepted" onclick="deSelectStatus('accepted')">
                        <i class="fas fa-check-circle"></i>
                        <strong><?php echo htmlspecialchars(t('accepted', 'Accepted')); ?></strong>
                        <small><?php echo htmlspecialchars(t('ready_for_payment', 'Ready for payment')); ?></small>
                    </button>
                    <button type="button" class="de-sc-status-btn de-sc-btn-medium" onclick="deSelectStatus('medium')">
                        <i class="fas fa-minus-circle"></i>
                        <strong><?php echo htmlspecialchars(t('medium', 'Medium')); ?></strong>
                        <small><?php echo htmlspecialchars(t('needs_review', 'Needs review')); ?></small>
                    </button>
                    <button type="button" class="de-sc-status-btn de-sc-btn-rejected" onclick="deSelectStatus('rejected')">
                        <i class="fas fa-times-circle"></i>
                        <strong><?php echo htmlspecialchars(t('rejected', 'Rejected')); ?></strong>
                        <small><?php echo htmlspecialchars(t('cannot_be_paid', 'Cannot be paid')); ?></small>
                    </button>
                </div>
                <div class="form-group de-sc-notes-row">
                    <label><i class="fas fa-sticky-note"></i> <?php echo htmlspecialchars(t('reason_note', 'Reason / Note')); ?> <span class="de-sc-opt">(<?php echo htmlspecialchars(t('optional', 'optional')); ?>)</span></label>
                    <input type="text" id="de-sc-notes" placeholder="<?php echo htmlspecialchars(t('status_change_reason_placeholder', 'Reason for changing this status…')); ?>">
                </div>
            </div>
            <div class="entry-form-actions de-modal-actions">
                <button type="button" class="btn btn-secondary" id="de-sc-cancel"><i class="fas fa-times"></i> <?php echo htmlspecialchars(t('cancel', 'Cancel')); ?></button>
                <button type="button" class="btn btn-primary" id="de-sc-save" disabled><i class="fas fa-save"></i> <?php echo htmlspecialchars(t('save_status', 'Save Status')); ?></button>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <script>
    function showToast(msg, type, actionLabel, actionFn) {
        var t = document.createElement('div');
        t.className = 'de-toast de-toast-' + type;
        t.innerHTML = '<i class="fas fa-' + (type === 'success' ? 'check-circle' : 'exclamation-circle') + '"></i> <span>' + msg + '</span>';
        if (actionLabel && actionFn) {
            var actionBtn = document.createElement('button');
            actionBtn.type = 'button';
            actionBtn.className = 'de-toast-action';
            actionBtn.textContent = actionLabel;
            actionBtn.addEventListener('click', actionFn);
            t.appendChild(actionBtn);
        }
        document.getElementById('toast-container').appendChild(t);
        setTimeout(function(){ t.classList.add('show'); }, 10);
        var dismissAfter = (actionLabel && actionFn) ? 8000 : 3500;
        setTimeout(function(){
            t.classList.remove('show');
            setTimeout(function(){ t.remove(); }, 400);
        }, dismissAfter);
    }

    function printWorkEntry(data) {
        var esc = function(v) {
            var d = document.createElement('div');
            d.textContent = (v === undefined || v === null || v === '') ? '—' : String(v);
            return d.innerHTML;
        };
        var dir = (window.appLanguage && ['ar', 'ckb'].includes(window.appLanguage)) ? 'rtl' : 'ltr';
        var tr = function(key, fallback) {
            return (window.appTranslations && window.appTranslations[key]) || fallback || key;
        };
        var rows = [
            [tr('date', 'Date'), data.date],
            [tr('location', 'Location'), data.location],
            [tr('work_type', 'Work Type'), data.workType],
            [tr('sub_work', 'Sub Work'), data.subpart],
            [tr('stakeholder', 'Stakeholder'), data.stakeholder],
            [tr('engineer', 'Engineer'), data.engineer],
            [tr('quantity', 'Quantity'), data.quantity],
            [tr('status', 'Status'), data.status]
        ];
        if (data.showPrice) {
            rows.push([tr('unit_price', 'Unit Price'), data.unitPrice]);
            rows.push([tr('total_value', 'Total Value'), data.total]);
        }
        rows.push([tr('notes', 'Notes'), data.notes]);

        var rowsHtml = rows.map(function(r) {
            return '<tr><th>' + esc(r[0]) + '</th><td>' + esc(r[1]) + '</td></tr>';
        }).join('');

        var win = window.open('', '_blank');
        if (!win) { return; }
        win.document.write('<html dir="' + dir + '" lang="' + (window.appLanguage || 'en') + '"><head><title>' + esc(tr('work_entry', 'Work Entry')) + '</title><style>'
            + 'body{font-family:sans-serif;padding:24px;color:#111;direction:' + dir + ';text-align:' + (dir === 'rtl' ? 'right' : 'left') + ';}'
            + 'h2{margin:0 0 4px;}'
            + '.sub{color:#555;margin:0 0 20px;font-size:13px;}'
            + 'table{border-collapse:collapse;width:100%;max-width:560px;direction:' + dir + ';}'
            + 'th,td{border:1px solid #999;padding:8px 12px;font-size:13px;text-align:start;vertical-align:top;}'
            + 'th{background:#eee;width:160px;}'
            + '</style></head><body>'
            + '<h2>' + esc(tr('work_entry_record', 'Work Entry Record')) + '</h2>'
            + '<p class="sub">' + esc(tr('printed', 'Printed')) + ' ' + esc(new Date().toLocaleString()) + '</p>'
            + '<table>' + rowsHtml + '</table>'
            + '</body></html>');
        win.document.close();
        win.focus();
        setTimeout(function(){ win.print(); }, 250);
    }

    var workTypes = <?php echo json_encode($workTypes, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP); ?>;
    var workTypeNameMap = <?php echo json_encode($workTypeNameMap, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP); ?>;
    var stakeholdersByWorkType = <?php echo json_encode($stakeholdersByWorkType, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP); ?>;
    var currentEngineerName = <?php echo json_encode($engineerName, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP); ?>;
    var subpartsByStakeholder = <?php echo json_encode($subpartsByStakeholder, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP); ?>;
    var activeWorkType = '<?php echo htmlspecialchars($defaultWorkTypeKey, ENT_QUOTES); ?>';
    var canManageHistory = <?php echo $canManageHistory ? 'true' : 'false'; ?>;

    function normalizeWorkTypeKey(key) {
        return (key || '')
            .toString()
            .toLowerCase()
            .trim()
            .replace(/[^a-z0-9_]+/g, '_')
            .replace(/_+/g, '_')
            .replace(/^_+|_+$/g, '');
    }

    function updateAptCard(d) {
        document.getElementById('apt-number').textContent     = 'Apt ' + d.apartment_number;
        document.getElementById('apt-location').textContent   = d.building_name + ' › ' + d.floor_name;
        document.getElementById('apt-type-badge').textContent = d.apartment_type || 'Residential';
        document.getElementById('apt-area').textContent       = (d.area_sqm || 0) + ' m²';
        document.getElementById('apt-recorded').textContent   = (d.total_quantity || 0) + ' m²';
        document.getElementById('apt-visits').textContent     = d.visits || 0;
        document.getElementById('apt-total-value').textContent = '$' + parseFloat(d.total_price || 0).toFixed(2);
        document.getElementById('history-apt-label').textContent = 'Apt ' + d.apartment_number;
        var pct = parseFloat(d.progress_pct || 0);
        document.getElementById('progress-pct').textContent = pct + '%';
        document.getElementById('progress-sub').textContent = (d.total_quantity || 0) + ' / ' + (d.area_sqm || 0) + ' m² completed';
        var bar = document.getElementById('progress-bar');
        bar.style.width = Math.min(pct, 100) + '%';
        bar.className = 'gech-bar-fill' + (pct >= 100 ? ' done' : (pct >= 50 ? ' half' : ''));
    }

    function updateCommonAreaCard() {
        var isProjectWide = document.getElementById('form-building-id').value === '0';
        var bSel = document.getElementById('sel-building');
        var fSel = document.getElementById('sel-floor');
        var buildingName = bSel && bSel.selectedIndex >= 0 ? bSel.options[bSel.selectedIndex].text : '—';
        var floorName = fSel && fSel.selectedIndex >= 0 ? fSel.options[fSel.selectedIndex].text : '—';

        document.getElementById('apt-number').textContent = isProjectWide ? 'Project-Wide' : 'Common Area';
        document.getElementById('apt-location').textContent = isProjectWide ? 'Entire project (no specific building)' : (buildingName + ' › ' + floorName);
        document.getElementById('apt-type-badge').textContent = isProjectWide ? 'Project-Wide' : 'Floor / Hallway';
        document.getElementById('apt-area').textContent = '—';
        document.getElementById('apt-recorded').textContent = '—';
        document.getElementById('apt-visits').textContent = '—';
        document.getElementById('apt-total-value').textContent = '—';
        document.getElementById('history-apt-label').textContent = isProjectWide ? 'Project-Wide' : 'Common Area';
        document.getElementById('progress-pct').textContent = '—';
        document.getElementById('progress-sub').textContent = isProjectWide ? 'Project-wide tracking' : 'Common area tracking';
        var bar = document.getElementById('progress-bar');
        bar.style.width = '0%';
        bar.className = 'gech-bar-fill';
    }

    function switchWorkType(type) {
        var gechkariSection = document.getElementById('gechkari-entry-section');
        if (!gechkariSection) return;
        gechkariSection.style.display = 'block';

        activeWorkType = normalizeWorkTypeKey(type);
        document.getElementById('entry-work-type-key').value = activeWorkType;
        document.getElementById('entry-work-type-name').value = workTypeNameMap[activeWorkType] || activeWorkType;
        populateStakeholders(activeWorkType);

        document.getElementById('entry-qty').value = '';
        document.getElementById('entry-unit-price').value = '';
        document.getElementById('entry-total').value = '';
        updateQuantityAndPriceLabels('unit', 'USD');

        var aptId = document.getElementById('form-apartment-id').value;
        if (aptId) {
            loadHistory(aptId);
        }
    }

    function populateStakeholders(workTypeKey) {
        var stakeholderSelect = document.getElementById('entry-stakeholder');
        var subpartSelect = document.getElementById('entry-subpart');
        if (!stakeholderSelect || !subpartSelect) return;

        var normalizedKey = normalizeWorkTypeKey(workTypeKey);
        var rows = stakeholdersByWorkType[normalizedKey] || stakeholdersByWorkType[workTypeKey] || [];
        var selectStakeholderText = (window.appTranslations && window.appTranslations.select_stakeholder) || 'Select stakeholder';
        var selectSubWorkText = (window.appTranslations && window.appTranslations.select_sub_work) || 'Select sub work';
        stakeholderSelect.innerHTML = '<option value="">' + selectStakeholderText + '</option>';
        rows.forEach(function(st) {
            var opt = document.createElement('option');
            opt.value = st.id;
            opt.textContent = st.name;
            stakeholderSelect.appendChild(opt);
        });

        subpartSelect.innerHTML = '<option value="">' + (window.appTranslations && window.appTranslations.select_sub_work ? window.appTranslations.select_sub_work : 'Select sub work') + '</option>';
        subpartSelect.disabled = true;
        updateQuantityAndPriceLabels('unit', 'USD');
    }

    var METRIC_UNIT_TEXT = {
        'per_apartment': 'per apartment',
        'per_unit': 'per unit',
        'lump_sum': 'lump sum',
        'per_hour': 'hour',
        'per_day': 'day'
    };

    function updateQuantityAndPriceLabels(metricType, currencyType) {
        var metric = metricType || 'unit';
        var currency = currencyType || 'USD';
        var unitText = METRIC_UNIT_TEXT[metric] || metric;
        document.getElementById('entry-qty-label-text').textContent = 'Finished Quantity (' + unitText + ')';
        document.getElementById('entry-unit-price-label-text').textContent = 'Unit Price (' + currency + '/' + unitText + ')';
        document.getElementById('entry-total-label-text').textContent = 'Total Value (' + currency + ')';
        document.getElementById('entry-qty-unit-hint').textContent = 'Unit: ' + unitText;
    }

    function syncEntryStatusStyle() {
        var val = (document.getElementById('entry-status').value || 'medium').toLowerCase();
        if (val !== 'accepted' && val !== 'rejected' && val !== 'medium') val = 'medium';
        document.querySelectorAll('#data-entry-page .status-pill').forEach(function(btn) {
            btn.classList.toggle('active', btn.getAttribute('data-value') === val);
        });
    }

    document.querySelectorAll('#data-entry-page .status-pill').forEach(function(btn) {
        btn.addEventListener('click', function() {
            document.getElementById('entry-status').value = btn.getAttribute('data-value');
            syncEntryStatusStyle();
        });
    });

    function loadStats(aptId) {
        fetch('get_gechkari_stats.php?apartment_id=' + aptId)
            .then(function(r){ return r.json(); })
            .then(function(d){ if (d.success) updateAptCard(d.data); });
    }

    function loadHistory(aptId) {
        var el = document.getElementById('history-loading');
        var floorId = document.getElementById('form-floor-id').value || '';
        var buildingId = document.getElementById('form-building-id').value || '';
        el.style.display = 'flex';
        fetch('get_work_entry_history.php?apartment_id=' + encodeURIComponent(aptId) + '&floor_id=' + encodeURIComponent(floorId) + '&building_id=' + encodeURIComponent(buildingId) + '&work_type_key=' + encodeURIComponent(activeWorkType))
            .then(function(r){ return r.text(); })
            .then(function(html){
                document.getElementById('gechkari-history-tbody').innerHTML = html;
                el.style.display = 'none';
            });
    }

    function openHistoryEntryModal(payload) {
        if (!canManageHistory) return;
        document.getElementById('history-entry-id').value = payload.id || '';
        document.getElementById('history-entry-source').value = payload.source || '';
        document.getElementById('history-edit-date').value = payload.date || '';
        document.getElementById('history-edit-engineer').value = payload.engineer || '';
        document.getElementById('history-edit-qty').value = payload.quantity || '';
        document.getElementById('history-edit-unit-price').value = payload.unitPrice || '';
        document.getElementById('history-edit-notes').value = payload.notes || '';
        document.getElementById('history-entry-modal').style.display = 'flex';
    }

    function closeHistoryEntryModal() {
        if (!canManageHistory) return;
        document.getElementById('history-entry-modal').style.display = 'none';
        document.getElementById('history-entry-form').reset();
    }

    function refreshCurrentHistory() {
        var aptId = document.getElementById('form-apartment-id').value || '';
        if (aptId === '') return;
        loadHistory(aptId);
        if (activeWorkType === 'gechkari' && parseInt(aptId, 10) > 0) {
            loadStats(aptId);
        }
    }

    function loadApartmentData(aptId, floorId, buildingId) {
        document.getElementById('form-apartment-id').value = aptId;
        document.getElementById('form-floor-id').value     = floorId;
        document.getElementById('form-building-id').value  = buildingId;
        document.getElementById('empty-state').style.display        = 'none';
        document.getElementById('gechkari-workspace').style.display = 'block';

        if (parseInt(aptId, 10) > 0) {
            loadStats(aptId);
        } else {
            updateCommonAreaCard();
        }
        loadHistory(aptId);
    }

    function resetWorkspace() {
        document.getElementById('gechkari-workspace').style.display = 'none';
        document.getElementById('empty-state').style.display        = 'flex';
    }

    document.querySelectorAll('.work-type-item').forEach(function(card) {
        card.addEventListener('click', function() {
            document.querySelectorAll('.work-type-item').forEach(function(el){ el.classList.remove('is-active'); });
            card.classList.add('is-active');
            switchWorkType(card.getAttribute('data-work-type'));
        });
    });

    document.getElementById('sel-building').addEventListener('change', function() {
        var bId  = this.value;
        var fSel = document.getElementById('sel-floor');
        var aSel = document.getElementById('sel-apartment');
        fSel.innerHTML = '<option value="">— Floor —</option>';
        aSel.innerHTML = '<option value="">— Apartment —</option>';
        fSel.disabled = true;
        aSel.disabled = true;
        resetWorkspace();
        if (!bId) return;
        if (bId === '0') {
            // Project-wide: not tied to any building, floor, or apartment.
            loadApartmentData('0', '0', '0');
            return;
        }
        fetch('get_floors.php', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:'building_id='+bId})
            .then(function(r){ return r.text(); })
            .then(function(html){ fSel.innerHTML = html; fSel.disabled = false; });
    });

    document.getElementById('sel-floor').addEventListener('change', function() {
        var fId  = this.value;
        var aSel = document.getElementById('sel-apartment');
        var apartmentText = (window.appTranslations && window.appTranslations.apartment) || 'Apartment';
        aSel.innerHTML = '<option value="">— ' + apartmentText + ' —</option>';
        aSel.disabled  = true;
        resetWorkspace();
        if (!fId) return;
        fetch('get_apartments.php', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:'floor_id='+fId})
            .then(function(r){ return r.text(); })
            .then(function(html){
                aSel.innerHTML = html;
                var commonOpt = document.createElement('option');
                commonOpt.value = '0';
                commonOpt.textContent = (window.appTranslations && window.appTranslations.common_area_hallway_no_apartment) || 'Common Area / Hallway (No Apartment)';
                aSel.appendChild(commonOpt);
                aSel.disabled = false;
            });
    });

    document.getElementById('sel-apartment').addEventListener('change', function() {
        var aId = this.value;
        if (aId === '') { resetWorkspace(); return; }
        loadApartmentData(aId, document.getElementById('sel-floor').value, document.getElementById('sel-building').value);
    });

    function calcTotal() {
        var qty   = parseFloat(document.getElementById('entry-qty').value) || 0;
        var price = parseFloat(document.getElementById('entry-unit-price').value) || 0;
        document.getElementById('entry-total').value = (qty && price) ? (qty * price).toFixed(2) : '';
    }

    document.getElementById('entry-stakeholder').addEventListener('change', function() {
        var stakeholderId = this.value;
        var subpartSelect = document.getElementById('entry-subpart');
        subpartSelect.innerHTML = '<option value="">' + ((window.appTranslations && window.appTranslations.select_sub_work) || 'Select sub work') + '</option>';
        subpartSelect.disabled = true;
        document.getElementById('entry-unit-price').value = '';
        document.getElementById('entry-total').value = '';
        updateQuantityAndPriceLabels('unit', 'USD');

        if (!stakeholderId || !subpartsByStakeholder[stakeholderId] || subpartsByStakeholder[stakeholderId].length === 0) {
            return;
        }

        subpartsByStakeholder[stakeholderId].forEach(function(sp) {
            var opt = document.createElement('option');
            opt.value = sp.id;
            var metric = sp.metric || 'm²';
            var currency = sp.currency || 'USD';
            opt.textContent = sp.name + ' — ' + currency + ' ' + Number(sp.price).toFixed(2) + '/' + metric;
            opt.setAttribute('data-price', Number(sp.price).toFixed(2));
            opt.setAttribute('data-name', sp.name);
            opt.setAttribute('data-metric', metric);
            opt.setAttribute('data-currency', currency);
            subpartSelect.appendChild(opt);
        });

        subpartSelect.disabled = false;
    });

    document.getElementById('entry-subpart').addEventListener('change', function() {
        var selected = this.options[this.selectedIndex];
        var unitPrice = selected ? (selected.getAttribute('data-price') || '') : '';
        var metric = selected ? (selected.getAttribute('data-metric') || 'unit') : 'unit';
        var currency = selected ? (selected.getAttribute('data-currency') || 'USD') : 'USD';
        document.getElementById('entry-unit-price').value = unitPrice;
        updateQuantityAndPriceLabels(metric, currency);
        calcTotal();
    });

    document.getElementById('entry-qty').addEventListener('input', calcTotal);

    document.getElementById('entry-date').valueAsDate = new Date();
    syncEntryStatusStyle();

    document.getElementById('btn-clear').addEventListener('click', function() {
        document.getElementById('entry-engineer-name').value = currentEngineerName;
        document.getElementById('entry-stakeholder').value = '';
        document.getElementById('entry-subpart').innerHTML = '<option value="">' + ((window.appTranslations && window.appTranslations.select_sub_work) || 'Select sub work') + '</option>';
        document.getElementById('entry-subpart').disabled = true;
        document.getElementById('entry-qty').value        = '';
        document.getElementById('entry-unit-price').value = '';
        document.getElementById('entry-total').value      = '';
        document.getElementById('entry-status').value     = 'medium';
        syncEntryStatusStyle();
        document.getElementById('entry-notes').value      = '';
        document.getElementById('entry-date').valueAsDate = new Date();
    });

    document.getElementById('gechkari-form').addEventListener('submit', function(e) {
        e.preventDefault();
        var aptId = document.getElementById('form-apartment-id').value;
        var flrId = document.getElementById('form-floor-id').value;
        var bldId = document.getElementById('form-building-id').value;
        var workTypeKey = (document.getElementById('entry-work-type-key').value || '').toLowerCase();
        var engineerName = (document.getElementById('entry-engineer-name').value || '').trim();
        var stakeholderSelect = document.getElementById('entry-stakeholder');
        var stakeholderId = stakeholderSelect.value;
        var stakeholderName = stakeholderId ? stakeholderSelect.options[stakeholderSelect.selectedIndex].text : '';
        var subpartSelect = document.getElementById('entry-subpart');
        var subpartId = subpartSelect.value;
        var subpartName = subpartId ? (subpartSelect.options[subpartSelect.selectedIndex].getAttribute('data-name') || '') : '';
        var subpartMetric = subpartId ? (subpartSelect.options[subpartSelect.selectedIndex].getAttribute('data-metric') || 'm²') : 'm²';
        var subpartCurrency = subpartId ? (subpartSelect.options[subpartSelect.selectedIndex].getAttribute('data-currency') || 'USD') : 'USD';
        var entryStatus = (document.getElementById('entry-status').value || 'medium').toLowerCase();
        var date  = document.getElementById('entry-date').value;
        var qty   = document.getElementById('entry-qty').value || 0;
        var price = document.getElementById('entry-unit-price').value || 0;
        var total = document.getElementById('entry-total').value || 0;
        var notes = document.getElementById('entry-notes').value;
        if (aptId === '' || !date || !engineerName || !workTypeKey) { showToast((window.appTranslations && window.appTranslations.fill_required_fields) || 'Please fill in all required fields.', 'error'); return; }

        var btn = document.getElementById('btn-save');
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> ' + ((window.appTranslations && window.appTranslations.saving) || 'Saving...');

        var endpoint = 'save_work_entry.php';
        var body = 'apartment_id=' + encodeURIComponent(aptId)
                 + '&floor_id=' + encodeURIComponent(flrId)
                 + '&building_id=' + encodeURIComponent(bldId)
                 + '&work_date=' + encodeURIComponent(date)
                 + '&engineer_name=' + encodeURIComponent(engineerName)
                 + '&work_type_key=' + encodeURIComponent(workTypeKey)
                 + '&stakeholder_id=' + encodeURIComponent(stakeholderId || '')
                 + '&subpart_id=' + encodeURIComponent(subpartId || '')
                 + '&quantity=' + encodeURIComponent(qty)
                 + '&unit_price=' + encodeURIComponent(price)
                 + '&total_price=' + encodeURIComponent(total)
                 + '&metric_type=' + encodeURIComponent(subpartMetric)
                 + '&currency_type=' + encodeURIComponent(subpartCurrency)
                 + '&status=' + encodeURIComponent(entryStatus)
                 + '&notes=' + encodeURIComponent(notes || '')
                 + '&csrf_token=<?php echo urlencode(csrf_token()); ?>';

        if (!stakeholderId || !subpartId || !qty || parseFloat(qty) <= 0 || !price || parseFloat(price) < 0) {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-save"></i> ' + ((window.appTranslations && window.appTranslations.save_entry) || 'Save Entry');
            showToast((window.appTranslations && window.appTranslations.select_stakeholder_subwork_quantity) || 'Please select stakeholder, sub work, and enter finished quantity.', 'error');
            return;
        }

        if (workTypeKey === 'gechkari') {
            if (parseInt(aptId, 10) <= 0) {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-save"></i> ' + ((window.appTranslations && window.appTranslations.save_entry) || 'Save Entry');
                showToast((window.appTranslations && window.appTranslations.gechkari_entry_requires_apartment) || 'Gechkari entry must be linked to a specific apartment.', 'error');
                return;
            }
            endpoint = 'gechkari_ajax.php';
            var notesWithStakeholder = '[Engineer: ' + engineerName + ' | Stakeholder: ' + stakeholderName + ' | Subpart: ' + subpartName + ' | Metric: ' + subpartMetric + ' | Currency: ' + subpartCurrency + ']' + (notes ? ' ' + notes : '');
            body = 'apartment_id=' + encodeURIComponent(aptId)
                 + '&floor_id=' + encodeURIComponent(flrId)
                 + '&building_id=' + encodeURIComponent(bldId)
                 + '&measurement_date=' + encodeURIComponent(date)
                 + '&quantity=' + encodeURIComponent(qty)
                 + '&unit_price=' + encodeURIComponent(price)
                 + '&status=' + encodeURIComponent(entryStatus)
                 + '&notes=' + encodeURIComponent(notesWithStakeholder)
                 + '&csrf_token=<?php echo urlencode(csrf_token()); ?>';
        }

        fetch(endpoint, {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:body})
            .then(function(r){ return r.json(); })
            .then(function(d){
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-save"></i> ' + ((window.appTranslations && window.appTranslations.save_entry) || 'Save Entry');
                if (d.success) {
                    var printPayload = {
                        date: date,
                        location: (document.getElementById('apt-number').textContent || '') + ' — ' + (document.getElementById('apt-location').textContent || ''),
                        workType: workTypeNameMap[workTypeKey] || workTypeKey,
                        subpart: subpartName || '—',
                        stakeholder: stakeholderName || '—',
                        engineer: engineerName,
                        quantity: qty + ' ' + subpartMetric,
                        status: entryStatus,
                        notes: notes,
                        showPrice: canManageHistory,
                        unitPrice: subpartCurrency + ' ' + price,
                        total: subpartCurrency + ' ' + total
                    };
                    showToast((window.appTranslations && window.appTranslations.entry_saved_successfully) || 'Entry saved successfully!', 'success', (window.appTranslations && window.appTranslations.print) || 'Print', function() {
                        printWorkEntry(printPayload);
                    });
                    document.getElementById('entry-engineer-name').value = currentEngineerName;
                    document.getElementById('entry-stakeholder').value = '';
                    document.getElementById('entry-subpart').innerHTML = '<option value="">' + ((window.appTranslations && window.appTranslations.select_sub_work) || 'Select sub work') + '</option>';
                    document.getElementById('entry-subpart').disabled = true;
                    document.getElementById('entry-qty').value        = '';
                    document.getElementById('entry-unit-price').value = '';
                    document.getElementById('entry-total').value      = '';
                    document.getElementById('entry-status').value     = 'medium';
                    syncEntryStatusStyle();
                    document.getElementById('entry-notes').value      = '';
                    document.getElementById('entry-date').valueAsDate = new Date();
                    loadStats(aptId);
                    loadHistory(aptId);
                } else {
                    showToast(d.message || ((window.appTranslations && window.appTranslations.failed_to_save_entry) || 'Failed to save entry.'), 'error');
                }
            })
            .catch(function(){
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-save"></i> ' + ((window.appTranslations && window.appTranslations.save_entry) || 'Save Entry');
                showToast((window.appTranslations && window.appTranslations.network_error) || 'Network error. Please try again.', 'error');
            });
    });

    document.addEventListener('click', function(e) {
        var printBtn = e.target.closest('.print-history-btn');
        if (!printBtn) return;
        var payload = {};
        try {
            payload = JSON.parse(printBtn.getAttribute('data-print') || '{}');
        } catch (err) {
            return;
        }
        printWorkEntry(payload);
    });

    if (canManageHistory) {
        document.addEventListener('click', function(e) {
            var editBtn = e.target.closest('.edit-history-btn');
            if (editBtn) {
                openHistoryEntryModal({
                    id: editBtn.getAttribute('data-entry-id'),
                    source: editBtn.getAttribute('data-entry-source'),
                    date: editBtn.getAttribute('data-entry-date'),
                    engineer: editBtn.getAttribute('data-entry-engineer'),
                    quantity: editBtn.getAttribute('data-entry-qty'),
                    unitPrice: editBtn.getAttribute('data-entry-unit-price'),
                    notes: editBtn.getAttribute('data-entry-notes')
                });
                return;
            }

            var deleteBtn = e.target.closest('.delete-history-btn');
            if (deleteBtn) {
                if (!window.confirm('Delete this history entry?')) {
                    return;
                }

                var formData = new FormData();
                formData.append('action', 'delete');
                formData.append('source', deleteBtn.getAttribute('data-entry-source') || 'generic');
                formData.append('entry_id', deleteBtn.getAttribute('data-entry-id') || '');

                fetch('manage_work_history_entry.php', { method: 'POST', body: formData })
                    .then(function(r){ return r.json(); })
                    .then(function(d) {
                        if (d.success) {
                            showToast(d.message || 'Entry deleted successfully.', 'success');
                            refreshCurrentHistory();
                        } else {
                            showToast(d.message || 'Failed to delete entry.', 'error');
                        }
                    })
                    .catch(function() {
                        showToast('Network error. Please try again.', 'error');
                    });
            }
        });

        document.getElementById('history-entry-close').addEventListener('click', closeHistoryEntryModal);
        document.getElementById('history-entry-cancel').addEventListener('click', closeHistoryEntryModal);
        document.getElementById('history-entry-modal').addEventListener('click', function(e) {
            if (e.target.id === 'history-entry-modal') {
                closeHistoryEntryModal();
            }
        });

        document.getElementById('history-entry-form').addEventListener('submit', function(e) {
            e.preventDefault();
            var saveBtn = document.getElementById('history-entry-save');
            var originalHtml = saveBtn.innerHTML;
            saveBtn.disabled = true;
            saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Updating...';

            var formData = new FormData();
            formData.append('action', 'update');
            formData.append('source', document.getElementById('history-entry-source').value);
            formData.append('entry_id', document.getElementById('history-entry-id').value);
            formData.append('work_date', document.getElementById('history-edit-date').value);
            formData.append('engineer_name', document.getElementById('history-edit-engineer').value);
            formData.append('quantity', document.getElementById('history-edit-qty').value);
            formData.append('unit_price', document.getElementById('history-edit-unit-price').value);
            formData.append('notes', document.getElementById('history-edit-notes').value);

            fetch('manage_work_history_entry.php', { method: 'POST', body: formData })
                .then(function(r){ return r.json(); })
                .then(function(d) {
                    saveBtn.disabled = false;
                    saveBtn.innerHTML = originalHtml;
                    if (d.success) {
                        closeHistoryEntryModal();
                        showToast(d.message || 'Entry updated successfully.', 'success');
                        refreshCurrentHistory();
                    } else {
                        showToast(d.message || 'Failed to update entry.', 'error');
                    }
                })
                .catch(function() {
                    saveBtn.disabled = false;
                    saveBtn.innerHTML = originalHtml;
                    showToast('Network error. Please try again.', 'error');
                });
        });

        // ── Status Change Modal ───────────────────────────────────────────
        var deScEntryId   = null;
        var deScSource    = null;
        var deScNewStatus = null;

        function openStatusChangeModal(entryId, source, currentStatus) {
            deScEntryId   = entryId;
            deScSource    = source;
            deScNewStatus = null;
            var badge = document.getElementById('de-sc-current-badge');
            badge.className   = 'status-badge status-' + currentStatus;
            badge.textContent = currentStatus.charAt(0).toUpperCase() + currentStatus.slice(1);
            document.querySelectorAll('.de-sc-status-btn').forEach(function(b){ b.classList.remove('de-sc-btn-active'); });
            document.getElementById('de-sc-notes').value = '';
            document.getElementById('de-sc-save').disabled = true;
            document.getElementById('de-status-modal').style.display = 'flex';
        }

        function closeStatusChangeModal() {
            document.getElementById('de-status-modal').style.display = 'none';
        }

        function deSelectStatus(status) {
            deScNewStatus = status;
            document.querySelectorAll('.de-sc-status-btn').forEach(function(b){ b.classList.remove('de-sc-btn-active'); });
            var btn = document.querySelector('.de-sc-btn-' + status);
            if (btn) btn.classList.add('de-sc-btn-active');
            document.getElementById('de-sc-save').disabled = false;
        }

        document.getElementById('de-sc-modal-close').addEventListener('click', closeStatusChangeModal);
        document.getElementById('de-sc-cancel').addEventListener('click', closeStatusChangeModal);
        document.getElementById('de-status-modal').addEventListener('click', function(e) {
            if (e.target === this) closeStatusChangeModal();
        });
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && document.getElementById('de-status-modal').style.display !== 'none') closeStatusChangeModal();
        });

        document.getElementById('de-sc-save').addEventListener('click', function() {
            if (!deScEntryId || !deScNewStatus) return;
            var notes    = document.getElementById('de-sc-notes').value.trim();
            var saveBtn  = this;
            var origHtml = saveBtn.innerHTML;
            saveBtn.disabled = true;
            saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving…';
            fetch('change_entry_status.php', {
                method:  'POST',
                headers: { 'Content-Type': 'application/json' },
                body:    JSON.stringify({ entry_id: deScEntryId, source: deScSource, new_status: deScNewStatus, notes: notes })
            })
            .then(function(r){ return r.json(); })
            .then(function(data) {
                saveBtn.disabled = false;
                saveBtn.innerHTML = origHtml;
                if (data.success) {
                    closeStatusChangeModal();
                    showToast(data.message || 'Status updated.', 'success');
                    refreshCurrentHistory();
                } else {
                    showToast(data.message || 'Failed to change status.', 'error');
                }
            })
            .catch(function() {
                saveBtn.disabled = false;
                saveBtn.innerHTML = origHtml;
                showToast('Network error. Please try again.', 'error');
            });
        });
    }

    switchWorkType(activeWorkType);
    </script>
</body>
</html>