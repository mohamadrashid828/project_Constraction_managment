<?php
session_start();
if (empty($_SESSION['user_id'])) {
    header('Location: index.html');
    exit;
}
require_once '../config.php';
require_once 'includes/stakeholder_access.php';
require_once 'includes/permissions.php';
require_once 'includes/csrf.php';

$user_id = $_SESSION['user_id'];
$permissions = get_user_permissions($conn, $user_id);

if (!in_array('stakeholders', $permissions, true)) {
    header('Location: dashboard.php?error=access_denied');
    exit;
}

$message = '';
$message_type = 'success';
$saved_portal_url = '';
$saved_stakeholder_name = '';

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

$colCheckContract = $conn->query("SHOW COLUMNS FROM project_stakeholders LIKE 'contract_file'");
if ($colCheckContract && $colCheckContract->num_rows === 0) {
    $conn->query("ALTER TABLE project_stakeholders ADD COLUMN contract_file VARCHAR(255) NULL AFTER apartment_meter_price");
}

$colCheck2 = $conn->query("SHOW COLUMNS FROM project_stakeholder_subparts LIKE 'metric_type'");
if ($colCheck2 && $colCheck2->num_rows === 0) {
    $conn->query("ALTER TABLE project_stakeholder_subparts ADD COLUMN metric_type VARCHAR(30) NOT NULL DEFAULT 'm²' AFTER unit_price");
}

$colCheck3 = $conn->query("SHOW COLUMNS FROM project_stakeholder_subparts LIKE 'currency_type'");
if ($colCheck3 && $colCheck3->num_rows === 0) {
    $conn->query("ALTER TABLE project_stakeholder_subparts ADD COLUMN currency_type VARCHAR(20) NOT NULL DEFAULT 'USD' AFTER metric_type");
}

ensure_stakeholder_access_column($conn);
backfill_stakeholder_tokens($conn);

$contractUploadDir = dirname(__DIR__) . '/data/contracts/';

$validWorkTypeKeys = [];
$vwtRes = $conn->query("SELECT work_type_key FROM project_work_types WHERE is_active = 1");
if ($vwtRes) {
    while ($vwtRow = $vwtRes->fetch_assoc()) {
        $validWorkTypeKeys[] = $vwtRow['work_type_key'];
    }
}
$validMetricTypes = ['m²', 'm', 'per_apartment'];
$validCurrencyTypes = ['IQD', 'USD', 'EUR', 'GBP', 'AED', 'SAR', '$', '€', '£'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        http_response_code(403);
        exit('Invalid or expired request.');
    }

    if (isset($_POST['save_stakeholder'])) {
        $stakeholder_id = (int)($_POST['stakeholder_id'] ?? 0);
        $stakeholder_name = trim($_POST['stakeholder_name'] ?? '');
        $stakeholder_date = trim($_POST['stakeholder_date'] ?? '');
        $stakeholder_date = $stakeholder_date !== '' ? $stakeholder_date : null;
        $work_type_key = trim($_POST['work_type_key'] ?? '');
        $cash_percentage = (float)($_POST['cash_percentage'] ?? 100);
        $apartment_percentage = (float)($_POST['apartment_percentage'] ?? 0);
        $apartment_meter_price = (float)($_POST['apartment_meter_price'] ?? 0);

        if ($cash_percentage < 0) $cash_percentage = 0;
        if ($apartment_percentage < 0) $apartment_percentage = 0;
        if ($cash_percentage > 100) $cash_percentage = 100;
        if ($apartment_percentage > 100) $apartment_percentage = 100;
        if ($apartment_meter_price < 0) $apartment_meter_price = 0;

        // Handle contract file upload
        $new_contract_file = null;
        $upload_error = '';
        if (!empty($_FILES['contract_file']['name'])) {
            $allowed_types = ['application/pdf', 'image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            $allowed_exts  = ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'webp'];
            $file_tmp  = $_FILES['contract_file']['tmp_name'];
            $file_size = $_FILES['contract_file']['size'];
            $file_ext  = strtolower(pathinfo($_FILES['contract_file']['name'], PATHINFO_EXTENSION));
            $finfo     = new finfo(FILEINFO_MIME_TYPE);
            $mime      = $finfo->file($file_tmp);

            if ($_FILES['contract_file']['error'] !== UPLOAD_ERR_OK) {
                $upload_error = 'File upload error.';
            } elseif ($file_size > 10 * 1024 * 1024) {
                $upload_error = 'Contract file must be under 10 MB.';
            } elseif (!in_array($file_ext, $allowed_exts, true) || !in_array($mime, $allowed_types, true)) {
                $upload_error = 'Only PDF or image files (JPG, PNG, GIF, WEBP) are allowed.';
            } else {
                $new_contract_file = 'contract_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $file_ext;
                if (!move_uploaded_file($file_tmp, $contractUploadDir . $new_contract_file)) {
                    $upload_error = 'Failed to save the contract file.';
                    $new_contract_file = null;
                }
            }
        }

        if ($upload_error) {
            $message = $upload_error;
            $message_type = 'error';
        } elseif ($work_type_key !== '' && !in_array($work_type_key, $validWorkTypeKeys, true)) {
            $message = 'Invalid work type selected.';
            $message_type = 'error';
        } elseif (($cash_percentage + $apartment_percentage) > 100.0001) {
            $message = 'Cash % and Apartment % cannot be more than 100 in total.';
            $message_type = 'error';
        } elseif ($stakeholder_name && $work_type_key) {
            if ($stakeholder_id > 0) {
                // Fetch old contract file to delete if replaced
                $oldFileStmt = $conn->prepare('SELECT contract_file FROM project_stakeholders WHERE id = ?');
                $oldFileStmt->bind_param('i', $stakeholder_id);
                $oldFileStmt->execute();
                $oldFileRow = $oldFileStmt->get_result();
                $oldFile = $oldFileRow ? $oldFileRow->fetch_assoc() : null;
                $oldFileStmt->close();

                if ($new_contract_file !== null) {
                    $stmt = $conn->prepare('UPDATE project_stakeholders SET stakeholder_name = ?, stakeholder_date = ?, work_type_key = ?, cash_percentage = ?, apartment_percentage = ?, apartment_meter_price = ?, contract_file = ? WHERE id = ?');
                    $stmt->bind_param('sssdddsi', $stakeholder_name, $stakeholder_date, $work_type_key, $cash_percentage, $apartment_percentage, $apartment_meter_price, $new_contract_file, $stakeholder_id);
                } else {
                    $stmt = $conn->prepare('UPDATE project_stakeholders SET stakeholder_name = ?, stakeholder_date = ?, work_type_key = ?, cash_percentage = ?, apartment_percentage = ?, apartment_meter_price = ? WHERE id = ?');
                    $stmt->bind_param('sssdddi', $stakeholder_name, $stakeholder_date, $work_type_key, $cash_percentage, $apartment_percentage, $apartment_meter_price, $stakeholder_id);
                }
                if ($stmt->execute()) {
                    // Delete old file if replaced
                    if ($new_contract_file !== null && !empty($oldFile['contract_file'])) {
                        $oldPath = $contractUploadDir . basename($oldFile['contract_file']);
                        if (file_exists($oldPath)) @unlink($oldPath);
                    }
                    $token = ensure_stakeholder_token_for_id($conn, $stakeholder_id);
                    if ($token) {
                        $saved_portal_url = stakeholder_portal_url($token);
                        $saved_stakeholder_name = $stakeholder_name;
                    }
                    $message = 'Stakeholder updated successfully.';
                } else {
                    $message = 'Error updating stakeholder.';
                    $message_type = 'error';
                }
                $stmt->close();
            } else {
                $access_token = generate_stakeholder_token();
                $stmt = $conn->prepare('INSERT INTO project_stakeholders (stakeholder_name, stakeholder_date, work_type_key, cash_percentage, apartment_percentage, apartment_meter_price, contract_file, access_token, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
                $stmt->bind_param('sssdddssi', $stakeholder_name, $stakeholder_date, $work_type_key, $cash_percentage, $apartment_percentage, $apartment_meter_price, $new_contract_file, $access_token, $user_id);
                if ($stmt->execute()) {
                    $new_stakeholder_id = (int)$conn->insert_id;
                    $token = ensure_stakeholder_token_for_id($conn, $new_stakeholder_id);
                    if ($token) {
                        $saved_portal_url = stakeholder_portal_url($token);
                        $saved_stakeholder_name = $stakeholder_name;
                    }
                    $message = 'Stakeholder added successfully. Personal link created — copy it below and send to them.';
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
            // Fetch contract file before deletion
            $fileStmt = $conn->prepare('SELECT contract_file FROM project_stakeholders WHERE id = ?');
            $fileStmt->bind_param('i', $stakeholder_id);
            $fileStmt->execute();
            $fileRow = $fileStmt->get_result();
            $fileData = $fileRow ? $fileRow->fetch_assoc() : null;
            $fileStmt->close();

            $stmt = $conn->prepare('DELETE FROM project_stakeholder_subparts WHERE stakeholder_id = ?');
            $stmt->bind_param('i', $stakeholder_id);
            $stmt->execute();
            $stmt->close();

            $stmt = $conn->prepare('DELETE FROM project_stakeholders WHERE id = ?');
            $stmt->bind_param('i', $stakeholder_id);
            if ($stmt->execute()) {
                // Delete contract file if exists
                if (!empty($fileData['contract_file'])) {
                    $filePath = $contractUploadDir . basename($fileData['contract_file']);
                    if (file_exists($filePath)) @unlink($filePath);
                }
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

    if (isset($_POST['regenerate_portal_link'])) {
        $stakeholder_id = (int)($_POST['stakeholder_id'] ?? 0);
        if ($stakeholder_id > 0) {
            $new_token = generate_stakeholder_token();
            $stmt = $conn->prepare('UPDATE project_stakeholders SET access_token = ? WHERE id = ?');
            $stmt->bind_param('si', $new_token, $stakeholder_id);
            if ($stmt->execute()) {
                $token = ensure_stakeholder_token_for_id($conn, $stakeholder_id);
                if ($token) {
                    $saved_portal_url = stakeholder_portal_url($token);
                    $nameStmt = $conn->prepare('SELECT stakeholder_name FROM project_stakeholders WHERE id = ? LIMIT 1');
                    $nameStmt->bind_param('i', $stakeholder_id);
                    $nameStmt->execute();
                    $nameRes = $nameStmt->get_result();
                    if ($nameRow = $nameRes->fetch_assoc()) {
                        $saved_stakeholder_name = $nameRow['stakeholder_name'];
                    }
                    $nameStmt->close();
                }
                $message = 'New personal link created. The old link no longer works.';
            } else {
                $message = 'Error regenerating portal link.';
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

backfill_stakeholder_tokens($conn);

$portalLinks = [];
$portalLinksRes = $conn->query("
    SELECT ps.id, ps.stakeholder_name, ps.access_token,
           COALESCE(wt.work_type_name, ps.work_type_key) AS work_type_name
    FROM project_stakeholders ps
    LEFT JOIN project_work_types wt ON wt.work_type_key = ps.work_type_key
    WHERE ps.is_active = 1
    ORDER BY ps.stakeholder_name
");
if ($portalLinksRes) {
    while ($pl = $portalLinksRes->fetch_assoc()) {
        $token = ensure_stakeholder_token_for_id($conn, (int)$pl['id']);
        if ($token) {
            $pl['access_token'] = $token;
            $pl['portal_url'] = stakeholder_portal_url($token);
            $portalLinks[] = $pl;
        }
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

            <?php if ($saved_portal_url): ?>
                <div class="portal-link-created-alert">
                    <div class="portal-link-created-head">
                        <i class="fas fa-link"></i>
                        <div>
                            <strong>Personal link<?php echo $saved_stakeholder_name ? ' for ' . htmlspecialchars($saved_stakeholder_name) : ''; ?></strong>
                            <p>Copy this link and send it to the stakeholder. They can open it without logging in.</p>
                        </div>
                    </div>
                    <div class="portal-link-copy-wrap portal-link-copy-wrap-alert">
                        <input type="text" class="portal-link-input" readonly value="<?php echo htmlspecialchars($saved_portal_url); ?>" id="saved-portal-link">
                        <button type="button" class="btn btn-primary portal-copy-btn" onclick="copyPortalLink(document.getElementById('saved-portal-link').value, this)"><i class="fas fa-copy"></i> Copy link</button>
                        <a class="btn btn-secondary portal-preview-btn" href="<?php echo htmlspecialchars($saved_portal_url); ?>" target="_blank"><i class="fas fa-eye"></i> Open page</a>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Removed separate Stakeholder Personal Links table to simplify UI.
                 Personal links are available via the action buttons on each stakeholder card. -->

            <div class="stakeholders-layout">
                <div class="panel-card">
                    <div class="card-header">
                        <h2><i class="fas fa-user-plus"></i> Add / Edit Stakeholder</h2>
                    </div>
                    <form method="POST" id="stakeholder-form" class="settings-form" enctype="multipart/form-data">
                        <?php echo csrf_field(); ?>
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
                                <label for="apartment_meter_price">Apt. Price/m² ($)</label>
                                <input type="number" name="apartment_meter_price" id="apartment_meter_price" min="0" step="0.01" value="0" placeholder="e.g., 12000">
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="contract_file"><i class="fas fa-file-contract"></i> Contract Scan (PDF or Image)</label>
                            <input type="file" name="contract_file" id="contract_file" accept=".pdf,.jpg,.jpeg,.png,.gif,.webp">
                            <span id="current-contract-hint" class="contract-hint" style="display:none;"></span>
                        </div>
                        <div id="stakeholder-portal-link-box" class="stakeholder-form-portal-box" style="display:none;">
                            <label><i class="fas fa-link"></i> Personal link for this stakeholder</label>
                            <div class="portal-link-copy-wrap">
                                <input type="text" class="portal-link-input" readonly id="stakeholder_form_portal_url" value="">
                                <button type="button" class="btn btn-sm btn-primary portal-copy-btn" onclick="copyPortalLink(document.getElementById('stakeholder_form_portal_url').value, this)"><i class="fas fa-copy"></i> Copy</button>
                                <a class="btn btn-sm btn-secondary portal-preview-btn" id="stakeholder_form_portal_open" href="#" target="_blank"><i class="fas fa-eye"></i> Open</a>
                            </div>
                            <small>Send this link after saving. No login required for the stakeholder.</small>
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

                    <div class="stakeholder-share-guide">
                        <div class="share-guide-icon"><i class="fas fa-paper-plane"></i></div>
                        <div>
                            <h3>Send a personal link to each stakeholder</h3>
                            <ol>
                                <li>Find the stakeholder below and click <strong>Copy link</strong></li>
                                <li>Send it by WhatsApp, SMS, or email</li>
                                <li>They open it in their browser — <strong>no username or password</strong> needed</li>
                            </ol>
                            <p class="share-guide-note"><i class="fas fa-shield-alt"></i> Each link shows only that person's own profile, prices, work, and payments.</p>
                        </div>
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
                                                <?php if (!empty($st['contract_file'])): ?>
                                                    <?php if (!empty($st['access_token'])): ?>
                                                    <a class="stakeholder-badge stakeholder-contract-badge" href="<?php echo htmlspecialchars(stakeholder_contract_url($st['access_token'])); ?>" target="_blank" title="View Contract"><i class="fas fa-file-contract"></i> Contract</a>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                            </p>
                                        </div>
                                        <div class="stakeholder-actions">
                                            <?php if (!empty($st['access_token'])): ?>
                                            <?php $portalUrl = stakeholder_portal_url($st['access_token']); ?>
                                            <button type="button" class="action-btn share-btn share-btn-labeled" title="Copy personal link" onclick="copyPortalLink('<?php echo htmlspecialchars($portalUrl, ENT_QUOTES); ?>', this)"><i class="fas fa-link"></i><span>Copy link</span></button>
                                            <a class="action-btn view-portal-btn view-portal-btn-labeled" href="<?php echo htmlspecialchars($portalUrl); ?>" target="_blank" title="Preview their page"><i class="fas fa-eye"></i><span>Preview</span></a>
                                            <form method="POST" onsubmit="return confirm('Create a new link? The old link will stop working immediately.');" style="display:inline;">
                                                <?php echo csrf_field(); ?>
                                                <input type="hidden" name="stakeholder_id" value="<?php echo (int)$st['id']; ?>">
                                                <button type="submit" name="regenerate_portal_link" class="action-btn regenerate-btn regenerate-btn-labeled" title="Create new link"><i class="fas fa-sync-alt"></i><span>New link</span></button>
                                            </form>
                                            <?php endif; ?>
                                            <button type="button" class="action-btn edit-btn" onclick="editStakeholder(<?php echo (int)$st['id']; ?>, '<?php echo htmlspecialchars($st['stakeholder_name'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($st['work_type_key'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($st['stakeholder_date'] ?? '', ENT_QUOTES); ?>', '<?php echo number_format((float)($st['cash_percentage'] ?? 100), 2, '.', ''); ?>', '<?php echo number_format((float)($st['apartment_percentage'] ?? 0), 2, '.', ''); ?>', '<?php echo number_format((float)($st['apartment_meter_price'] ?? 0), 2, '.', ''); ?>', '<?php echo htmlspecialchars($st['contract_file'] ?? '', ENT_QUOTES); ?>', '<?php echo !empty($st['access_token']) ? htmlspecialchars(stakeholder_portal_url($st['access_token']), ENT_QUOTES) : ''; ?>', '<?php echo !empty($st['access_token']) ? htmlspecialchars(stakeholder_contract_url($st['access_token']), ENT_QUOTES) : ''; ?>')"><i class="fas fa-edit"></i></button>
                                            <form method="POST" onsubmit="return confirm('Delete stakeholder and all subparts?');" style="display:inline;">
                                                <?php echo csrf_field(); ?>
                                                <input type="hidden" name="stakeholder_id" value="<?php echo (int)$st['id']; ?>">
                                                <button type="submit" name="delete_stakeholder" class="action-btn delete-btn"><i class="fas fa-trash"></i></button>
                                            </form>
                                        </div>
                                    </div>

                                    <!-- Personal link is available via the action buttons above; removed expanded link row for simplicity -->

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
                                                                <?php echo csrf_field(); ?>
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
                                                        <?php echo csrf_field(); ?>
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
function copyPortalLink(url, btn) {
    if (!url) return;
    var done = function() {
        if (!btn) return;
        var original = btn.innerHTML;
        var isLabeled = btn.classList.contains('share-btn-labeled') || btn.classList.contains('portal-copy-btn');
        btn.innerHTML = isLabeled ? '<i class="fas fa-check"></i> Copied!' : '<i class="fas fa-check"></i>';
        btn.classList.add('copied');
        setTimeout(function() {
            btn.innerHTML = original;
            btn.classList.remove('copied');
        }, 2000);
    };
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(url).then(done).catch(function() {
            window.prompt('Copy this link:', url);
        });
    } else {
        window.prompt('Copy this link:', url);
        done();
    }
}

function editStakeholder(id, name, workType, stakeholderDate, cashPercentage, apartmentPercentage, apartmentMeterPrice, contractFile, portalUrl, contractUrl) {
    document.getElementById('stakeholder_id').value = id;
    document.getElementById('stakeholder_name').value = name;
    document.getElementById('stakeholder_date').value = stakeholderDate || '';
    document.getElementById('work_type_key').value = workType;
    document.getElementById('cash_percentage').value = (cashPercentage !== undefined && cashPercentage !== null && cashPercentage !== '') ? cashPercentage : '100';
    document.getElementById('apartment_percentage').value = (apartmentPercentage !== undefined && apartmentPercentage !== null && apartmentPercentage !== '') ? apartmentPercentage : '0';
    document.getElementById('apartment_meter_price').value = (apartmentMeterPrice !== undefined && apartmentMeterPrice !== null && apartmentMeterPrice !== '') ? apartmentMeterPrice : '0';
    var portalBox = document.getElementById('stakeholder-portal-link-box');
    var portalInput = document.getElementById('stakeholder_form_portal_url');
    var portalOpen = document.getElementById('stakeholder_form_portal_open');
    if (portalBox && portalInput && portalOpen) {
        if (portalUrl) {
            portalBox.style.display = '';
            portalInput.value = portalUrl;
            portalOpen.href = portalUrl;
        } else {
            portalBox.style.display = 'none';
            portalInput.value = '';
            portalOpen.href = '#';
        }
    }
    var hint = document.getElementById('current-contract-hint');
    if (hint) {
        if (contractFile) {
            hint.style.display = '';
            // Build with DOM APIs (not innerHTML) so a crafted file name can't
            // inject markup, and link through the token-gated contract endpoint
            // rather than the now-denied raw /data/contracts/ path.
            hint.textContent = '';
            var icon = document.createElement('i');
            icon.className = 'fas fa-paperclip';
            hint.appendChild(icon);
            hint.appendChild(document.createTextNode(' Current: '));
            var link = document.createElement('a');
            link.href = contractUrl || '#';
            link.target = '_blank';
            link.textContent = contractFile;
            hint.appendChild(link);
            hint.appendChild(document.createTextNode(' — upload a new file to replace it'));
        } else {
            hint.style.display = 'none';
            hint.textContent = '';
        }
    }
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function resetStakeholderForm() {
    document.getElementById('stakeholder-form').reset();
    document.getElementById('stakeholder_id').value = '';
    var portalBox = document.getElementById('stakeholder-portal-link-box');
    if (portalBox) portalBox.style.display = 'none';
    var hint = document.getElementById('current-contract-hint');
    if (hint) { hint.style.display = 'none'; hint.innerHTML = ''; }
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
