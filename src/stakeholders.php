<?php
session_start();
if (empty($_SESSION['user_id'])) {
    header('Location: index.html');
    exit;
}
require_once '../config.php';

$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare('SELECT p.name FROM permissions p JOIN role_permissions rp ON p.id = rp.permission_id JOIN users u ON rp.role_id = u.role_id WHERE u.id = ?');
$stmt->bind_param('i', $user_id);
$stmt->execute();
$res = $stmt->get_result();
$permissions = [];
while ($row = $res->fetch_assoc()) {
    $permissions[] = $row['name'];
}
$stmt->close();

if (!in_array('project_settings', $permissions)) {
    header('Location: dashboard.php?error=access_denied');
    exit;
}

$message = '';
$message_type = 'success';

$conn->query("CREATE TABLE IF NOT EXISTS project_work_types (
    id INT AUTO_INCREMENT PRIMARY KEY,
    work_type_name VARCHAR(120) NOT NULL,
    work_type_key VARCHAR(80) NOT NULL UNIQUE,
    quantity_unit VARCHAR(30) NOT NULL DEFAULT 'm²',
    scope_level VARCHAR(30) NOT NULL DEFAULT 'apartment',
    pricing_mode VARCHAR(30) NOT NULL DEFAULT 'per_unit',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_work_type_key (work_type_key)
)");

$conn->query("CREATE TABLE IF NOT EXISTS project_stakeholders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    stakeholder_name VARCHAR(160) NOT NULL,
    stakeholder_date DATE NULL,
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

$colCheck = $conn->query("SHOW COLUMNS FROM project_stakeholders LIKE 'stakeholder_date'");
if ($colCheck && $colCheck->num_rows === 0) {
    $conn->query("ALTER TABLE project_stakeholders ADD COLUMN stakeholder_date DATE NULL AFTER stakeholder_name");
}

$colCheckCash = $conn->query("SHOW COLUMNS FROM project_stakeholders LIKE 'cash_percentage'");
if ($colCheckCash && $colCheckCash->num_rows === 0) {
    $conn->query("ALTER TABLE project_stakeholders ADD COLUMN cash_percentage DECIMAL(5,2) NOT NULL DEFAULT 100.00 AFTER work_type_key");
}

$colCheckApartment = $conn->query("SHOW COLUMNS FROM project_stakeholders LIKE 'apartment_percentage'");
if ($colCheckApartment && $colCheckApartment->num_rows === 0) {
    $conn->query("ALTER TABLE project_stakeholders ADD COLUMN apartment_percentage DECIMAL(5,2) NOT NULL DEFAULT 0.00 AFTER cash_percentage");
}

$colCheckAptPrice = $conn->query("SHOW COLUMNS FROM project_stakeholders LIKE 'apartment_meter_price'");
if ($colCheckAptPrice && $colCheckAptPrice->num_rows === 0) {
    $conn->query("ALTER TABLE project_stakeholders ADD COLUMN apartment_meter_price DECIMAL(14,2) NOT NULL DEFAULT 0.00 AFTER apartment_percentage");
}

$colCheck2 = $conn->query("SHOW COLUMNS FROM project_stakeholder_subparts LIKE 'metric_type'");
if ($colCheck2 && $colCheck2->num_rows === 0) {
    $conn->query("ALTER TABLE project_stakeholder_subparts ADD COLUMN metric_type VARCHAR(30) NOT NULL DEFAULT 'm²' AFTER unit_price");
}

$colCheck3 = $conn->query("SHOW COLUMNS FROM project_stakeholder_subparts LIKE 'currency_type'");
if ($colCheck3 && $colCheck3->num_rows === 0) {
    $conn->query("ALTER TABLE project_stakeholder_subparts ADD COLUMN currency_type VARCHAR(20) NOT NULL DEFAULT 'USD' AFTER metric_type");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save_stakeholder'])) {
        $stakeholder_id = (int)($_POST['stakeholder_id'] ?? 0);
        $stakeholder_name = trim($_POST['stakeholder_name'] ?? '');
        $stakeholder_date = trim($_POST['stakeholder_date'] ?? '');
        $stakeholder_date = $stakeholder_date !== '' ? $stakeholder_date : null;
        $work_type_key = trim($_POST['work_type_key'] ?? '');
        $cash_percentage = (float)($_POST['cash_percentage'] ?? 100);
        $apartment_percentage = (float)($_POST['apartment_percentage'] ?? 0);
        $apartment_meter_price = (float)($_POST['apartment_meter_price'] ?? 0);

        if ($cash_percentage < 0) {
            $cash_percentage = 0;
        }
        if ($apartment_percentage < 0) {
            $apartment_percentage = 0;
        }
        if ($cash_percentage > 100) {
            $cash_percentage = 100;
        }
        if ($apartment_percentage > 100) {
            $apartment_percentage = 100;
        }
        if ($apartment_meter_price < 0) {
            $apartment_meter_price = 0;
        }

        if (($cash_percentage + $apartment_percentage) > 100.0001) {
            $message = 'Cash % and Apartment % cannot be more than 100 in total.';
            $message_type = 'error';
        } elseif ($stakeholder_name && $work_type_key) {
            if ($stakeholder_id > 0) {
                $stmt = $conn->prepare('UPDATE project_stakeholders SET stakeholder_name = ?, stakeholder_date = ?, work_type_key = ?, cash_percentage = ?, apartment_percentage = ?, apartment_meter_price = ? WHERE id = ?');
                $stmt->bind_param('sssdddi', $stakeholder_name, $stakeholder_date, $work_type_key, $cash_percentage, $apartment_percentage, $apartment_meter_price, $stakeholder_id);
                if ($stmt->execute()) {
                    $message = 'Stakeholder updated successfully.';
                } else {
                    $message = 'Error updating stakeholder.';
                    $message_type = 'error';
                }
                $stmt->close();
            } else {
                $stmt = $conn->prepare('INSERT INTO project_stakeholders (stakeholder_name, stakeholder_date, work_type_key, cash_percentage, apartment_percentage, apartment_meter_price, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)');
                $stmt->bind_param('sssdddi', $stakeholder_name, $stakeholder_date, $work_type_key, $cash_percentage, $apartment_percentage, $apartment_meter_price, $user_id);
                if ($stmt->execute()) {
                    $message = 'Stakeholder added successfully.';
                } else {
                    $message = 'Error adding stakeholder (may already exist for this work type).';
                    $message_type = 'error';
                }
                $stmt->close();
            }
        } else {
            $message = 'Please fill stakeholder name and work type.';
            $message_type = 'error';
        }
    }

    if (isset($_POST['delete_stakeholder'])) {
        $stakeholder_id = (int)($_POST['stakeholder_id'] ?? 0);
        if ($stakeholder_id > 0) {
            $stmt = $conn->prepare('DELETE FROM project_stakeholder_subparts WHERE stakeholder_id = ?');
            $stmt->bind_param('i', $stakeholder_id);
            $stmt->execute();
            $stmt->close();

            $stmt = $conn->prepare('DELETE FROM project_stakeholders WHERE id = ?');
            $stmt->bind_param('i', $stakeholder_id);
            if ($stmt->execute()) {
                $message = 'Stakeholder deleted successfully.';
            } else {
                $message = 'Error deleting stakeholder.';
                $message_type = 'error';
            }
            $stmt->close();
        }
    }

    if (isset($_POST['save_subpart'])) {
        $subpart_id = (int)($_POST['subpart_id'] ?? 0);
        $stakeholder_id = (int)($_POST['stakeholder_id'] ?? 0);
        $subpart_name = trim($_POST['subpart_name'] ?? '');
        $unit_price = (float)($_POST['unit_price'] ?? 0);
        $metric_type = trim($_POST['metric_type'] ?? 'm²');
        $currency_type = trim($_POST['currency_type'] ?? 'USD');
        if ($currency_type === '') {
            $currency_type = 'USD';
        }

        if ($stakeholder_id > 0 && $subpart_name && $unit_price > 0 && $metric_type && $currency_type) {
            if ($subpart_id > 0) {
                $stmt = $conn->prepare('UPDATE project_stakeholder_subparts SET subpart_name = ?, unit_price = ?, metric_type = ?, currency_type = ? WHERE id = ?');
                $stmt->bind_param('sdssi', $subpart_name, $unit_price, $metric_type, $currency_type, $subpart_id);
                if ($stmt->execute()) {
                    $message = 'Subpart updated successfully.';
                } else {
                    $message = 'Error updating subpart.';
                    $message_type = 'error';
                }
                $stmt->close();
            } else {
                $stmt = $conn->prepare('INSERT INTO project_stakeholder_subparts (stakeholder_id, subpart_name, unit_price, metric_type, currency_type) VALUES (?, ?, ?, ?, ?)');
                $stmt->bind_param('isdss', $stakeholder_id, $subpart_name, $unit_price, $metric_type, $currency_type);
                if ($stmt->execute()) {
                    $message = 'Subpart added successfully.';
                } else {
                    $message = 'Error adding subpart (may already exist for this stakeholder).';
                    $message_type = 'error';
                }
                $stmt->close();
            }
        } else {
            $message = 'Please fill subpart, metric, currency and valid price.';
            $message_type = 'error';
        }
    }

    if (isset($_POST['delete_subpart'])) {
        $subpart_id = (int)($_POST['subpart_id'] ?? 0);
        if ($subpart_id > 0) {
            $stmt = $conn->prepare('DELETE FROM project_stakeholder_subparts WHERE id = ?');
            $stmt->bind_param('i', $subpart_id);
            if ($stmt->execute()) {
                $message = 'Subpart deleted successfully.';
            } else {
                $message = 'Error deleting subpart.';
                $message_type = 'error';
            }
            $stmt->close();
        }
    }
}

$stakeholders = $conn->query("SELECT ps.*, u.username AS created_by_name, wt.work_type_name,
    (SELECT COUNT(*) FROM project_stakeholder_subparts sps WHERE sps.stakeholder_id = ps.id AND sps.is_active = 1) AS subparts_count
    FROM project_stakeholders ps
    LEFT JOIN users u ON u.id = ps.created_by
    LEFT JOIN project_work_types wt ON wt.work_type_key = ps.work_type_key
    WHERE ps.is_active = 1
    ORDER BY ps.work_type_key, ps.stakeholder_name");

$workTypesForStakeholders = $conn->query("SELECT work_type_key, work_type_name
    FROM project_work_types
    WHERE is_active = 1
    ORDER BY work_type_name");

$workTypes = [];
if ($workTypesForStakeholders && $workTypesForStakeholders->num_rows > 0) {
    while ($wt = $workTypesForStakeholders->fetch_assoc()) {
        $workTypes[] = $wt;
    }
}

$pageTitle = 'Stakeholders - Green World Towers';
$pageCss = 'stakeholders.css';
$activePage = 'stakeholders';
require_once 'partials/header.php';
?>
<div id="stakeholders-page" class="dashboard-container">
<?php require_once 'partials/sidebar.php'; ?>

    <main class="main-content">
        <header class="page-header">
            <h1><i class="fas fa-handshake"></i> Stakeholders</h1>
            <div class="user-info">
                <span>Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?></span>
            </div>
        </header>

        <div class="content-wrapper">
            <?php if ($message): ?>
                <div class="alert alert-<?php echo $message_type; ?>">
                    <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <div class="stakeholders-layout">
                <div class="panel-card">
                    <div class="card-header">
                        <h2><i class="fas fa-user-plus"></i> Add / Edit Stakeholder</h2>
                    </div>
                    <form method="POST" id="stakeholder-form" class="settings-form">
                        <input type="hidden" name="stakeholder_id" id="stakeholder_id" value="">
                        <div class="form-group">
                            <label for="stakeholder_name">Stakeholder Name</label>
                            <input type="text" name="stakeholder_name" id="stakeholder_name" required placeholder="e.g., Finishing Team A">
                        </div>
                        <div class="form-group">
                            <label for="stakeholder_date">Date</label>
                            <input type="date" name="stakeholder_date" id="stakeholder_date">
                        </div>
                        <div class="form-group">
                            <label for="work_type_key">Work Type</label>
                            <select name="work_type_key" id="work_type_key" required>
                                <option value="">Select work type</option>
                                <?php if (!empty($workTypes)): ?>
                                    <?php foreach ($workTypes as $wt): ?>
                                        <option value="<?php echo htmlspecialchars($wt['work_type_key']); ?>"><?php echo htmlspecialchars($wt['work_type_name']); ?></option>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <option value="gechkari">Gechkari</option>
                                <?php endif; ?>
                            </select>
                        </div>
                        <div class="stakeholder-payment-grid">
                            <div class="form-group">
                                <label for="cash_percentage">Cash %</label>
                                <input type="number" name="cash_percentage" id="cash_percentage" min="0" max="100" step="0.01" value="100" placeholder="e.g., 60">
                            </div>
                            <div class="form-group">
                                <label for="apartment_percentage">Apartment %</label>
                                <input type="number" name="apartment_percentage" id="apartment_percentage" min="0" max="100" step="0.01" value="0" placeholder="e.g., 40">
                            </div>
                            <div class="form-group">
                                <label for="apartment_meter_price">Apartment Price per m² ($)</label>
                                <input type="number" name="apartment_meter_price" id="apartment_meter_price" min="0" step="0.01" value="0" placeholder="e.g., 12000">
                            </div>
                        </div>
                        <div class="form-actions">
                            <button type="submit" name="save_stakeholder" class="btn btn-primary"><i class="fas fa-save"></i> Save Stakeholder</button>
                            <button type="button" class="btn btn-secondary" onclick="resetStakeholderForm()"><i class="fas fa-undo"></i> Reset</button>
                        </div>
                    </form>
                </div>

                <div class="panel-card">
                    <div class="card-header">
                        <h2><i class="fas fa-list"></i> Stakeholder Work & Subparts</h2>
                    </div>

                    <?php if (!$stakeholders || $stakeholders->num_rows === 0): ?>
                        <p class="empty-text">No stakeholders added yet.</p>
                    <?php else: ?>
                        <div class="stakeholder-filters-card">
                            <div class="quick-filter-shell">
                                <div class="quick-filter-title-row">
                                    <h3><i class="fas fa-filter"></i> Quick Filters</h3>
                                </div>

                                <div class="stakeholder-filters quick-filter-grid">
                                    <div class="form-group quick-filter-item quick-filter-search">
                                        <label for="stakeholder-filter-search">Search Stakeholder</label>
                                        <input type="text" id="stakeholder-filter-search" placeholder="Search by stakeholder name...">
                                    </div>

                                    <div class="form-group quick-filter-item quick-filter-worktype">
                                        <label for="stakeholder-filter-worktype">Type of Job</label>
                                        <select id="stakeholder-filter-worktype">
                                            <option value="">All initialized work types</option>
                                            <?php if (!empty($workTypes)): ?>
                                                <?php foreach ($workTypes as $wt): ?>
                                                    <option value="<?php echo htmlspecialchars(strtolower($wt['work_type_key'])); ?>"><?php echo htmlspecialchars($wt['work_type_name']); ?></option>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </select>
                                    </div>

                                    <div class="quick-filter-item quick-filter-actions">
                                        <button type="button" class="btn btn-secondary" id="stakeholder-filter-clear">
                                            <i class="fas fa-times"></i> Clear Filters
                                        </button>
                                    </div>
                                </div>

                                <div class="quick-filter-footer">
                                    <p id="stakeholder-filter-result" class="filter-result-text"></p>
                                    <p id="stakeholder-filter-empty" class="empty-text" style="display:none; margin-top:0;">No stakeholders match your filters.</p>
                                </div>
                            </div>
                        </div>

                        <div class="stakeholder-list">
                            <?php while ($st = $stakeholders->fetch_assoc()): ?>
                                <?php $displayWorkType = trim((string)($st['work_type_name'] ?? '')) !== '' ? $st['work_type_name'] : ucfirst($st['work_type_key']); ?>
                                <div
                                    class="stakeholder-card"
                                    data-stakeholder-name="<?php echo htmlspecialchars(strtolower($st['stakeholder_name']), ENT_QUOTES); ?>"
                                    data-work-type="<?php echo htmlspecialchars(strtolower($st['work_type_key']), ENT_QUOTES); ?>"
                                >
                                    <div class="stakeholder-head">
                                        <div>
                                            <h3><?php echo htmlspecialchars($st['stakeholder_name']); ?></h3>
                                            <p>
                                                <span class="stakeholder-badge stakeholder-worktype-badge"><?php echo htmlspecialchars($displayWorkType); ?></span>
                                                <span class="stakeholder-badge stakeholder-subparts-badge"><?php echo (int)$st['subparts_count']; ?> subparts</span>
                                                <span class="stakeholder-badge stakeholder-cash-badge">Cash: <?php echo number_format((float)($st['cash_percentage'] ?? 100), 2); ?>%</span>
                                                <span class="stakeholder-badge stakeholder-apartment-badge">Apartment: <?php echo number_format((float)($st['apartment_percentage'] ?? 0), 2); ?>%</span>
                                                <span class="stakeholder-badge stakeholder-price-badge">Apt m²: $<?php echo number_format((float)($st['apartment_meter_price'] ?? 0), 2); ?></span>
                                                <?php if (!empty($st['stakeholder_date'])): ?>
                                                    <span class="stakeholder-badge stakeholder-date-badge"><?php echo htmlspecialchars($st['stakeholder_date']); ?></span>
                                                <?php endif; ?>
                                            </p>
                                        </div>
                                        <div class="stakeholder-actions">
                                            <button type="button" class="action-btn edit-btn" onclick="editStakeholder(<?php echo (int)$st['id']; ?>, '<?php echo htmlspecialchars($st['stakeholder_name'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($st['work_type_key'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($st['stakeholder_date'] ?? '', ENT_QUOTES); ?>', '<?php echo number_format((float)($st['cash_percentage'] ?? 100), 2, '.', ''); ?>', '<?php echo number_format((float)($st['apartment_percentage'] ?? 0), 2, '.', ''); ?>', '<?php echo number_format((float)($st['apartment_meter_price'] ?? 0), 2, '.', ''); ?>')"><i class="fas fa-edit"></i></button>
                                            <form method="POST" onsubmit="return confirm('Delete stakeholder and all subparts?');" style="display:inline;">
                                                <input type="hidden" name="stakeholder_id" value="<?php echo (int)$st['id']; ?>">
                                                <button type="submit" name="delete_stakeholder" class="action-btn delete-btn"><i class="fas fa-trash"></i></button>
                                            </form>
                                        </div>
                                    </div>

                                    <div class="subparts-table-wrap">
                                        <table class="data-table subparts-table">
                                            <thead>
                                                <tr>
                                                    <th>Subpart</th>
                                                    <th>Metric</th>
                                                    <th>Currency</th>
                                                    <th>Price</th>
                                                    <th>Action</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                            <?php
                                            $subStmt = $conn->prepare('SELECT * FROM project_stakeholder_subparts WHERE stakeholder_id = ? AND is_active = 1 ORDER BY subpart_name');
                                            $subStmt->bind_param('i', $st['id']);
                                            $subStmt->execute();
                                            $subRes = $subStmt->get_result();
                                            ?>

                                            <?php if ($subRes->num_rows === 0): ?>
                                                <tr><td colspan="5" class="empty-text">No subparts yet</td></tr>
                                            <?php else: ?>
                                                <?php while ($sp = $subRes->fetch_assoc()): ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars($sp['subpart_name']); ?></td>
                                                        <td><?php echo htmlspecialchars($sp['metric_type'] ?? 'm²'); ?></td>
                                                        <td><?php echo htmlspecialchars($sp['currency_type'] ?? 'USD'); ?></td>
                                                        <td><?php echo number_format((float)$sp['unit_price'], 2); ?></td>
                                                        <td class="row-actions">
                                                            <button
                                                                type="button"
                                                                class="action-btn edit-btn"
                                                                title="Load in form"
                                                                onclick="prefillSubpartForm(<?php echo (int)$st['id']; ?>, <?php echo (int)$sp['id']; ?>, '<?php echo htmlspecialchars($sp['subpart_name'], ENT_QUOTES); ?>', '<?php echo number_format((float)$sp['unit_price'], 2, '.', ''); ?>', '<?php echo htmlspecialchars($sp['metric_type'] ?? 'm²', ENT_QUOTES); ?>', '<?php echo htmlspecialchars($sp['currency_type'] ?? 'USD', ENT_QUOTES); ?>')"
                                                            ><i class="fas fa-edit"></i></button>
                                                            <form method="POST" style="display:inline;">
                                                                <input type="hidden" name="subpart_id" value="<?php echo (int)$sp['id']; ?>">
                                                                <button type="submit" name="delete_subpart" class="action-btn delete-btn" title="Delete" onclick="return confirm('Delete this subpart?');"><i class="fas fa-trash"></i></button>
                                                            </form>
                                                        </td>
                                                    </tr>
                                                <?php endwhile; ?>
                                            <?php endif; ?>
                                            <?php $subStmt->close(); ?>

                                            <tr>
                                                <td colspan="5">
                                                    <form method="POST" class="subpart-add-form">
                                                        <input type="hidden" name="subpart_id" id="subpart-id-<?php echo (int)$st['id']; ?>" value="">
                                                        <input type="hidden" name="stakeholder_id" value="<?php echo (int)$st['id']; ?>">
                                                        <input type="text" name="subpart_name" id="subpart-name-<?php echo (int)$st['id']; ?>" placeholder="New subpart (e.g., ceiling, walls)" required>
                                                        <select name="metric_type" id="subpart-metric-<?php echo (int)$st['id']; ?>" required>
                                                            <option value="m²">m²</option>
                                                            <option value="m">m</option>
                                                            <option value="per_apartment">per apartment</option>
                                                        </select>
                                                        <select name="currency_type" id="subpart-currency-<?php echo (int)$st['id']; ?>" required>
                                                            <option value="IQD">IQD</option>
                                                            <option value="USD">USD</option>
                                                            <option value="EUR">EUR</option>
                                                            <option value="GBP">GBP</option>
                                                            <option value="AED">AED</option>
                                                            <option value="SAR">SAR</option>
                                                            <option value="$">$</option>
                                                            <option value="€">€</option>
                                                            <option value="£">£</option>
                                                        </select>
                                                        <input type="number" name="unit_price" id="subpart-price-<?php echo (int)$st['id']; ?>" min="0.01" step="0.01" placeholder="Price" required>
                                                        <button type="submit" name="save_subpart" class="btn btn-sm btn-primary"><i class="fas fa-save"></i> Save Subpart</button>
                                                    </form>
                                                </td>
                                            </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>
</div>

<script>
function editStakeholder(id, name, workType, stakeholderDate, cashPercentage, apartmentPercentage, apartmentMeterPrice) {
    document.getElementById('stakeholder_id').value = id;
    document.getElementById('stakeholder_name').value = name;
    document.getElementById('stakeholder_date').value = stakeholderDate || '';
    document.getElementById('work_type_key').value = workType;
    document.getElementById('cash_percentage').value = (cashPercentage !== undefined && cashPercentage !== null && cashPercentage !== '') ? cashPercentage : '100';
    document.getElementById('apartment_percentage').value = (apartmentPercentage !== undefined && apartmentPercentage !== null && apartmentPercentage !== '') ? apartmentPercentage : '0';
    document.getElementById('apartment_meter_price').value = (apartmentMeterPrice !== undefined && apartmentMeterPrice !== null && apartmentMeterPrice !== '') ? apartmentMeterPrice : '0';
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function resetStakeholderForm() {
    document.getElementById('stakeholder-form').reset();
    document.getElementById('stakeholder_id').value = '';
}

function prefillSubpartForm(stakeholderId, subpartId, subpartName, unitPrice, metricType, currencyType) {
    var idInput = document.getElementById('subpart-id-' + stakeholderId);
    var nameInput = document.getElementById('subpart-name-' + stakeholderId);
    var priceInput = document.getElementById('subpart-price-' + stakeholderId);
    var metricInput = document.getElementById('subpart-metric-' + stakeholderId);
    var currencyInput = document.getElementById('subpart-currency-' + stakeholderId);
    if (!idInput || !nameInput || !priceInput || !metricInput || !currencyInput) return;
    idInput.value = subpartId;
    nameInput.value = subpartName;
    priceInput.value = unitPrice;
    metricInput.value = metricType || 'm²';
    currencyInput.value = currencyType || 'USD';
    nameInput.focus();
}

function applyStakeholderFilters() {
    var searchInput = document.getElementById('stakeholder-filter-search');
    var workTypeSelect = document.getElementById('stakeholder-filter-worktype');
    var resultText = document.getElementById('stakeholder-filter-result');
    var emptyText = document.getElementById('stakeholder-filter-empty');
    var cards = document.querySelectorAll('#stakeholders-page .stakeholder-card');

    if (!searchInput || !workTypeSelect || cards.length === 0) {
        return;
    }

    var searchValue = (searchInput.value || '').toLowerCase().trim();
    var workTypeValue = (workTypeSelect.value || '').toLowerCase().trim();
    var visible = 0;

    cards.forEach(function(card) {
        var cardName = (card.getAttribute('data-stakeholder-name') || '').toLowerCase();
        var cardWorkType = (card.getAttribute('data-work-type') || '').toLowerCase();

        var matchName = !searchValue || cardName.indexOf(searchValue) !== -1;
        var matchWorkType = !workTypeValue || cardWorkType === workTypeValue;
        var show = matchName && matchWorkType;

        card.style.display = show ? '' : 'none';
        if (show) {
            visible++;
        }
    });

    if (resultText) {
        resultText.textContent = visible + ' stakeholder' + (visible === 1 ? '' : 's') + ' found';
    }
    if (emptyText) {
        emptyText.style.display = visible === 0 ? '' : 'none';
    }
}

document.addEventListener('DOMContentLoaded', function() {
    var searchInput = document.getElementById('stakeholder-filter-search');
    var workTypeSelect = document.getElementById('stakeholder-filter-worktype');
    var clearBtn = document.getElementById('stakeholder-filter-clear');

    if (searchInput) {
        searchInput.addEventListener('input', applyStakeholderFilters);
    }
    if (workTypeSelect) {
        workTypeSelect.addEventListener('change', applyStakeholderFilters);
    }
    if (clearBtn) {
        clearBtn.addEventListener('click', function() {
            if (searchInput) searchInput.value = '';
            if (workTypeSelect) workTypeSelect.value = '';
            applyStakeholderFilters();
        });
    }

    applyStakeholderFilters();
});
</script>
</body>
</html>
