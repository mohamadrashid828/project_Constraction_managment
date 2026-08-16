<?php
session_start();
if (empty($_SESSION['user_id'])) {
    header('Location: index.html');
    exit;
}
require_once '../config.php';
require_once 'includes/stakeholder_access.php';
require_once 'includes/stakeholders.php';
require_once 'includes/permissions.php';
require_once 'includes/csrf.php';

$user_id = $_SESSION['user_id'];
$permissions = get_user_permissions($conn, $user_id);

if (!in_array('stakeholders', $permissions, true)) {
    header('Location: dashboard.php?error=access_denied');
    exit;
}

// Set when arriving from a profile page's "Edit Details" link, so the
// Add/Edit form below can be pre-filled for that stakeholder on load.
$editStakeholderId = (int)($_GET['edit'] ?? 0);
$autoEditScript = '';

// Builds the editStakeholder(...) JS call for a stakeholder row. Each argument
// is JSON-encoded with the HEX_* flags so the result is safe to embed either
// raw inside a <script> block or htmlspecialchars()-wrapped inside an
// onclick="" attribute.
function stakeholder_edit_js_call(array $st, string $portalUrl, string $contractUrl): string
{
    $flags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
    $args = [
        (int)$st['id'],
        (string)$st['stakeholder_name'],
        (string)($st['company_name'] ?? ''),
        (string)($st['email'] ?? ''),
        (string)($st['phone'] ?? ''),
        (string)$st['work_type_key'],
        (string)($st['stakeholder_date'] ?? ''),
        number_format((float)($st['cash_percentage'] ?? 100), 2, '.', ''),
        number_format((float)($st['apartment_percentage'] ?? 0), 2, '.', ''),
        number_format((float)($st['apartment_meter_price'] ?? 0), 2, '.', ''),
        (string)($st['contract_file'] ?? ''),
        $portalUrl,
        $contractUrl,
        !empty($st['profile_image']),
    ];
    $encoded = array_map(function ($v) use ($flags) {
        return json_encode($v, $flags);
    }, $args);
    return 'editStakeholder(' . implode(', ', $encoded) . ')';
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

$colCheckEmail = $conn->query("SHOW COLUMNS FROM project_stakeholders LIKE 'email'");
if ($colCheckEmail && $colCheckEmail->num_rows === 0) {
    $conn->query("ALTER TABLE project_stakeholders ADD COLUMN email VARCHAR(150) NULL AFTER stakeholder_name");
}

$colCheckPhone = $conn->query("SHOW COLUMNS FROM project_stakeholders LIKE 'phone'");
if ($colCheckPhone && $colCheckPhone->num_rows === 0) {
    $conn->query("ALTER TABLE project_stakeholders ADD COLUMN phone VARCHAR(50) NULL AFTER email");
}

$colCheckCompany = $conn->query("SHOW COLUMNS FROM project_stakeholders LIKE 'company_name'");
if ($colCheckCompany && $colCheckCompany->num_rows === 0) {
    $conn->query("ALTER TABLE project_stakeholders ADD COLUMN company_name VARCHAR(160) NULL AFTER phone");
}

$colCheckPhoto = $conn->query("SHOW COLUMNS FROM project_stakeholders LIKE 'profile_image'");
if ($colCheckPhoto && $colCheckPhoto->num_rows === 0) {
    $conn->query("ALTER TABLE project_stakeholders ADD COLUMN profile_image VARCHAR(255) NULL AFTER contract_file");
}

$conn->query("CREATE TABLE IF NOT EXISTS project_stakeholder_documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    stakeholder_id INT NOT NULL,
    doc_name VARCHAR(160) NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_stakeholder (stakeholder_id)
)");

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
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        http_response_code(403);
        exit('Invalid or expired request.');
    }

    if (isset($_POST['save_stakeholder'])) {
        $stakeholder_id = (int)($_POST['stakeholder_id'] ?? 0);
        $stakeholder_name = trim($_POST['stakeholder_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $company_name = trim($_POST['company_name'] ?? '');
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

        // Handle profile photo upload
        $photoResult = $upload_error ? ['ok' => true, 'message' => '', 'filename' => null] : store_stakeholder_photo($_FILES['profile_image'] ?? []);
        $new_photo_file = $photoResult['filename'];
        if (!$upload_error && !$photoResult['ok']) {
            $upload_error = $photoResult['message'];
        }

        if ($upload_error) {
            $message = $upload_error;
            $message_type = 'error';
        } elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = 'Please enter a valid email address.';
            $message_type = 'error';
        } elseif ($work_type_key !== '' && !in_array($work_type_key, $validWorkTypeKeys, true)) {
            $message = 'Invalid work type selected.';
            $message_type = 'error';
        } elseif (($cash_percentage + $apartment_percentage) > 100.0001) {
            $message = 'Cash % and Apartment % cannot be more than 100 in total.';
            $message_type = 'error';
        } elseif ($stakeholder_name && $work_type_key) {
            if ($stakeholder_id > 0) {
                // Fetch existing files so an upload that wasn't replaced this time is preserved
                $oldFileStmt = $conn->prepare('SELECT contract_file, profile_image FROM project_stakeholders WHERE id = ?');
                $oldFileStmt->bind_param('i', $stakeholder_id);
                $oldFileStmt->execute();
                $oldFileRow = $oldFileStmt->get_result();
                $oldFile = $oldFileRow ? $oldFileRow->fetch_assoc() : null;
                $oldFileStmt->close();

                $finalContractFile = $new_contract_file !== null ? $new_contract_file : ($oldFile['contract_file'] ?? null);
                $finalProfileImage = $new_photo_file !== null ? $new_photo_file : ($oldFile['profile_image'] ?? null);

                $stmt = $conn->prepare('UPDATE project_stakeholders SET stakeholder_name = ?, email = ?, phone = ?, company_name = ?, stakeholder_date = ?, work_type_key = ?, cash_percentage = ?, apartment_percentage = ?, apartment_meter_price = ?, contract_file = ?, profile_image = ? WHERE id = ?');
                $stmt->bind_param(
                    'ssssssdddssi',
                    $stakeholder_name, $email, $phone, $company_name, $stakeholder_date, $work_type_key,
                    $cash_percentage, $apartment_percentage, $apartment_meter_price,
                    $finalContractFile, $finalProfileImage, $stakeholder_id
                );
                if ($stmt->execute()) {
                    // Delete replaced files
                    if ($new_contract_file !== null && !empty($oldFile['contract_file'])) {
                        $oldPath = $contractUploadDir . basename($oldFile['contract_file']);
                        if (file_exists($oldPath)) @unlink($oldPath);
                    }
                    if ($new_photo_file !== null && !empty($oldFile['profile_image'])) {
                        $oldPhotoPath = stakeholder_photo_dir() . basename($oldFile['profile_image']);
                        if (file_exists($oldPhotoPath)) @unlink($oldPhotoPath);
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
                $stmt = $conn->prepare('INSERT INTO project_stakeholders (stakeholder_name, email, phone, company_name, stakeholder_date, work_type_key, cash_percentage, apartment_percentage, apartment_meter_price, contract_file, profile_image, access_token, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
                $stmt->bind_param(
                    'ssssssdddsssi',
                    $stakeholder_name, $email, $phone, $company_name, $stakeholder_date, $work_type_key,
                    $cash_percentage, $apartment_percentage, $apartment_meter_price,
                    $new_contract_file, $new_photo_file, $access_token, $user_id
                );
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
            // Fetch files before deletion
            $fileStmt = $conn->prepare('SELECT contract_file, profile_image FROM project_stakeholders WHERE id = ?');
            $fileStmt->bind_param('i', $stakeholder_id);
            $fileStmt->execute();
            $fileRow = $fileStmt->get_result();
            $fileData = $fileRow ? $fileRow->fetch_assoc() : null;
            $fileStmt->close();

            $docFiles = [];
            $docStmt = $conn->prepare('SELECT file_name FROM project_stakeholder_documents WHERE stakeholder_id = ?');
            $docStmt->bind_param('i', $stakeholder_id);
            $docStmt->execute();
            $docRes = $docStmt->get_result();
            while ($docRow = $docRes->fetch_assoc()) {
                $docFiles[] = $docRow['file_name'];
            }
            $docStmt->close();

            $stmt = $conn->prepare('DELETE FROM project_stakeholder_subparts WHERE stakeholder_id = ?');
            $stmt->bind_param('i', $stakeholder_id);
            $stmt->execute();
            $stmt->close();

            $stmt = $conn->prepare('DELETE FROM project_stakeholder_documents WHERE stakeholder_id = ?');
            $stmt->bind_param('i', $stakeholder_id);
            $stmt->execute();
            $stmt->close();

            $stmt = $conn->prepare('DELETE FROM project_stakeholders WHERE id = ?');
            $stmt->bind_param('i', $stakeholder_id);
            if ($stmt->execute()) {
                if (!empty($fileData['contract_file'])) {
                    $filePath = $contractUploadDir . basename($fileData['contract_file']);
                    if (file_exists($filePath)) @unlink($filePath);
                }
                if (!empty($fileData['profile_image'])) {
                    $photoPath = stakeholder_photo_dir() . basename($fileData['profile_image']);
                    if (file_exists($photoPath)) @unlink($photoPath);
                }
                foreach ($docFiles as $docFile) {
                    $docPath = stakeholder_document_dir() . basename($docFile);
                    if (file_exists($docPath)) @unlink($docPath);
                }
                $message = 'Stakeholder deleted successfully.';
            } else {
                $message = 'Error deleting stakeholder.';
                $message_type = 'error';
            }
            $stmt->close();
        }
    }

}

$stakeholders = $conn->query("SELECT ps.*, u.username AS created_by_name, wt.work_type_name, wt.work_type_name_ku,
    (SELECT COUNT(*) FROM project_stakeholder_subparts sps WHERE sps.stakeholder_id = ps.id AND sps.is_active = 1) AS subparts_count
    FROM project_stakeholders ps
    LEFT JOIN users u ON u.id = ps.created_by
    LEFT JOIN project_work_types wt ON wt.work_type_key = ps.work_type_key
    WHERE ps.is_active = 1
    ORDER BY ps.work_type_key, ps.stakeholder_name");

$workTypesForStakeholders = $conn->query("SELECT work_type_key, work_type_name, work_type_name_ku
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
            <h1><i class="fas fa-handshake"></i> <?php echo htmlspecialchars(t('stakeholders', 'Stakeholders')); ?></h1>
            <div class="user-info">
                <span><?php echo htmlspecialchars(t('welcome', 'Welcome')); ?>, <?php echo htmlspecialchars($_SESSION['username']); ?></span>
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
                            <strong><?php echo htmlspecialchars(t('personal_link', 'Personal link')); ?><?php echo $saved_stakeholder_name ? ' ' . htmlspecialchars(t('for_name', 'for')) . ' ' . htmlspecialchars($saved_stakeholder_name) : ''; ?></strong>
                            <p><?php echo htmlspecialchars(t('copy_link_send_to_stakeholder', 'Copy this link and send it to the stakeholder. They can open it without logging in.')); ?></p>
                        </div>
                    </div>
                    <div class="portal-link-copy-wrap portal-link-copy-wrap-alert">
                        <input type="text" class="portal-link-input" readonly value="<?php echo htmlspecialchars($saved_portal_url); ?>" id="saved-portal-link">
                        <button type="button" class="btn btn-primary portal-copy-btn" onclick="copyPortalLink(document.getElementById('saved-portal-link').value, this)"><i class="fas fa-copy"></i> <?php echo htmlspecialchars(t('copy_link', 'Copy link')); ?></button>
                        <a class="btn btn-secondary portal-preview-btn" href="<?php echo htmlspecialchars($saved_portal_url); ?>" target="_blank"><i class="fas fa-eye"></i> <?php echo htmlspecialchars(t('open_page', 'Open page')); ?></a>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Removed separate Stakeholder Personal Links table to simplify UI.
                 Personal links are available via the action buttons on each stakeholder card. -->

            <div class="stakeholders-layout">
                <div class="panel-card">
                    <div class="card-header">
                        <h2><i class="fas fa-user-plus"></i> <?php echo htmlspecialchars(t('add_edit_stakeholder', 'Add / Edit Stakeholder')); ?></h2>
                    </div>
                    <form method="POST" id="stakeholder-form" class="settings-form" enctype="multipart/form-data">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="stakeholder_id" id="stakeholder_id" value="">
                        <div class="form-group stakeholder-photo-group">
                            <label for="profile_image"><i class="fas fa-camera"></i> <?php echo htmlspecialchars(t('profile_photo', 'Profile Photo')); ?></label>
                            <div class="stakeholder-photo-input-row">
                                <img id="stakeholder-photo-preview" class="stakeholder-photo-preview" style="display:none;" alt="Preview">
                                <div class="stakeholder-photo-preview-placeholder" id="stakeholder-photo-preview-placeholder"><i class="fas fa-user-hard-hat"></i></div>
                                <input type="file" name="profile_image" id="profile_image" accept="image/png,image/jpeg,image/gif,image/webp" onchange="previewStakeholderPhoto(this)">
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="stakeholder_name"><?php echo htmlspecialchars(t('stakeholder_name', 'Stakeholder Name')); ?></label>
                            <input type="text" name="stakeholder_name" id="stakeholder_name" required placeholder="<?php echo htmlspecialchars(t('stakeholder_name_example', 'e.g., Finishing Team A')); ?>">
                        </div>
                        <div class="form-group">
                            <label for="company_name"><i class="fas fa-building"></i> <?php echo htmlspecialchars(t('company_name', 'Company Name')); ?></label>
                            <input type="text" name="company_name" id="company_name" placeholder="<?php echo htmlspecialchars(t('company_name_example', 'e.g., Rasti Construction Co.')); ?>">
                        </div>
                        <div class="stakeholder-contact-grid">
                            <div class="form-group">
                                <label for="email"><i class="fas fa-envelope"></i> <?php echo htmlspecialchars(t('email', 'Email')); ?></label>
                                <input type="email" name="email" id="email" placeholder="<?php echo htmlspecialchars(t('email_example', 'e.g., name@example.com')); ?>">
                            </div>
                            <div class="form-group">
                                <label for="phone"><i class="fas fa-phone"></i> <?php echo htmlspecialchars(t('phone', 'Phone')); ?></label>
                                <input type="text" name="phone" id="phone" placeholder="<?php echo htmlspecialchars(t('phone_example', 'e.g., 0770 123 4567')); ?>">
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="stakeholder_date"><?php echo htmlspecialchars(t('date', 'Date')); ?></label>
                            <input type="date" name="stakeholder_date" id="stakeholder_date">
                        </div>
                        <div class="form-group">
                            <label for="work_type_key"><?php echo htmlspecialchars(t('work_type', 'Work Type')); ?></label>
                            <select name="work_type_key" id="work_type_key" required>
                                <option value=""><?php echo htmlspecialchars(t('select_work_type', 'Select work type')); ?></option>
                                <?php if (!empty($workTypes)): ?>
                                    <?php foreach ($workTypes as $wt): ?>
                                        <?php $wtLabel = (($_SESSION['language'] ?? 'en') === 'ckb' && trim((string)($wt['work_type_name_ku'] ?? '')) !== '') ? $wt['work_type_name_ku'] : ($wt['work_type_name'] ?? ''); ?>
                                        <option value="<?php echo htmlspecialchars($wt['work_type_key']); ?>"><?php echo htmlspecialchars($wtLabel !== '' ? $wtLabel : ($wt['work_type_key'] ?? '')); ?></option>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <option value="gechkari">Gechkari</option>
                                <?php endif; ?>
                            </select>
                        </div>
                        <div class="stakeholder-payment-grid">
                            <div class="form-group">
                                <label for="cash_percentage"><?php echo htmlspecialchars(t('cash_percent', 'Cash %')); ?></label>
                                <input type="number" name="cash_percentage" id="cash_percentage" min="0" max="100" step="0.01" value="100" placeholder="<?php echo htmlspecialchars(t('example_60', 'e.g., 60')); ?>">
                            </div>
                            <div class="form-group">
                                <label for="apartment_percentage"><?php echo htmlspecialchars(t('apartment_percent', 'Apartment %')); ?></label>
                                <input type="number" name="apartment_percentage" id="apartment_percentage" min="0" max="100" step="0.01" value="0" placeholder="<?php echo htmlspecialchars(t('example_40', 'e.g., 40')); ?>">
                            </div>
                            <div class="form-group">
                                <label for="apartment_meter_price"><?php echo htmlspecialchars(t('apt_price_per_sqm', 'Apt. Price/m² ($)')); ?></label>
                                <input type="number" name="apartment_meter_price" id="apartment_meter_price" min="0" step="0.01" value="0" placeholder="<?php echo htmlspecialchars(t('example_12000', 'e.g., 12000')); ?>">
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="contract_file"><i class="fas fa-file-contract"></i> <?php echo htmlspecialchars(t('contract_scan', 'Contract Scan (PDF or Image)')); ?></label>
                            <input type="file" name="contract_file" id="contract_file" accept=".pdf,.jpg,.jpeg,.png,.gif,.webp">
                            <span id="current-contract-hint" class="contract-hint" style="display:none;"></span>
                        </div>
                        <div id="stakeholder-portal-link-box" class="stakeholder-form-portal-box" style="display:none;">
                            <label><i class="fas fa-link"></i> <?php echo htmlspecialchars(t('personal_link_for_stakeholder', 'Personal link for this stakeholder')); ?></label>
                            <div class="portal-link-copy-wrap">
                                <input type="text" class="portal-link-input" readonly id="stakeholder_form_portal_url" value="">
                                <button type="button" class="btn btn-sm btn-primary portal-copy-btn" onclick="copyPortalLink(document.getElementById('stakeholder_form_portal_url').value, this)"><i class="fas fa-copy"></i> <?php echo htmlspecialchars(t('copy', 'Copy')); ?></button>
                                <a class="btn btn-sm btn-secondary portal-preview-btn" id="stakeholder_form_portal_open" href="#" target="_blank"><i class="fas fa-eye"></i> <?php echo htmlspecialchars(t('open', 'Open')); ?></a>
                            </div>
                            <small><?php echo htmlspecialchars(t('send_link_after_saving', 'Send this link after saving. No login required for the stakeholder.')); ?></small>
                        </div>
                        <div class="form-actions">
                            <button type="submit" name="save_stakeholder" class="btn btn-primary"><i class="fas fa-save"></i> <?php echo htmlspecialchars(t('save_stakeholder', 'Save Stakeholder')); ?></button>
                            <button type="button" class="btn btn-secondary" onclick="resetStakeholderForm()"><i class="fas fa-undo"></i> <?php echo htmlspecialchars(t('reset', 'Reset')); ?></button>
                        </div>
                    </form>
                </div>

                <div class="panel-card">
                    <div class="card-header">
                        <h2><i class="fas fa-users"></i> <?php echo htmlspecialchars(t('all_stakeholders', 'All Stakeholders')); ?></h2>
                    </div>

                    <div class="stakeholder-share-guide">
                        <div class="share-guide-icon"><i class="fas fa-paper-plane"></i></div>
                        <div>
                            <h3><?php echo htmlspecialchars(t('click_stakeholder_profile', 'Click a stakeholder to open their profile')); ?></h3>
                            <ol>
                                <li><?php echo htmlspecialchars(t('open_profile_manage', 'Open a profile to manage their subwork, pricing, and contract')); ?></li>
                                <li><?php echo htmlspecialchars(t('or_click_copy_link', 'Or click Copy link on the card to grab their personal link')); ?></li>
                                <li><?php echo htmlspecialchars(t('send_link_note', 'Send it by WhatsApp, SMS, or email — they open it with no username or password needed')); ?></li>
                            </ol>
                            <p class="share-guide-note"><i class="fas fa-shield-alt"></i> <?php echo htmlspecialchars(t('link_shows_own_profile', 'Each link shows only that person\'s own profile, prices, work, and payments.')); ?></p>
                        </div>
                    </div>

                    <?php if (!$stakeholders || $stakeholders->num_rows === 0): ?>
                        <p class="empty-text"><?php echo htmlspecialchars(t('no_stakeholders_added', 'No stakeholders added yet.')); ?></p>
                    <?php else: ?>
                        <div class="stakeholder-filters-card">
                            <div class="quick-filter-shell">
                                <div class="quick-filter-title-row">
                                    <h3><i class="fas fa-filter"></i> <?php echo htmlspecialchars(t('quick_filters', 'Quick Filters')); ?></h3>
                                </div>

                                <div class="stakeholder-filters quick-filter-grid">
                                    <div class="form-group quick-filter-item quick-filter-search">
                                        <label for="stakeholder-filter-search"><?php echo htmlspecialchars(t('search_stakeholder', 'Search Stakeholder')); ?></label>
                                        <input type="text" id="stakeholder-filter-search" placeholder="<?php echo htmlspecialchars(t('search_by_stakeholder_name', 'Search by stakeholder name...')); ?>">
                                    </div>

                                    <div class="form-group quick-filter-item quick-filter-worktype">
                                        <label for="stakeholder-filter-worktype"><?php echo htmlspecialchars(t('type_of_job', 'Type of Job')); ?></label>
                                        <select id="stakeholder-filter-worktype">
                                            <option value=""><?php echo htmlspecialchars(t('all_initialized_work_types', 'All initialized work types')); ?></option>
                                            <?php if (!empty($workTypes)): ?>
                                                <?php foreach ($workTypes as $wt): ?>
                                                    <?php $wtLabel = (($_SESSION['language'] ?? 'en') === 'ckb' && trim((string)($wt['work_type_name_ku'] ?? '')) !== '') ? $wt['work_type_name_ku'] : ($wt['work_type_name'] ?? ''); ?>
                                                    <option value="<?php echo htmlspecialchars(strtolower($wt['work_type_key'])); ?>"><?php echo htmlspecialchars($wtLabel !== '' ? $wtLabel : ($wt['work_type_key'] ?? '')); ?></option>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </select>
                                    </div>

                                    <div class="quick-filter-item quick-filter-actions">
                                        <button type="button" class="btn btn-secondary" id="stakeholder-filter-clear">
                                            <i class="fas fa-times"></i> <?php echo htmlspecialchars(t('clear_filters', 'Clear Filters')); ?>
                                        </button>
                                    </div>
                                </div>

                                <div class="quick-filter-footer">
                                    <p id="stakeholder-filter-result" class="filter-result-text"></p>
                                    <p id="stakeholder-filter-empty" class="empty-text" style="display:none; margin-top:0;"><?php echo htmlspecialchars(t('no_stakeholder_match_filters', 'No stakeholders match your filters.')); ?></p>
                                </div>
                            </div>
                        </div>

                        <div class="stakeholder-profile-grid">
                            <?php while ($st = $stakeholders->fetch_assoc()): ?>
                                <?php
                                $displayWorkType = (($_SESSION['language'] ?? 'en') === 'ckb' && trim((string)($st['work_type_name_ku'] ?? '')) !== '')
                                    ? $st['work_type_name_ku']
                                    : (trim((string)($st['work_type_name'] ?? '')) !== '' ? $st['work_type_name'] : ucfirst($st['work_type_key']));
                                $portalUrl = !empty($st['access_token']) ? stakeholder_portal_url($st['access_token']) : '';
                                $contractUrl = !empty($st['access_token']) ? stakeholder_contract_url($st['access_token']) : '';

                                // Arrived via a profile page's "Edit Details" link — queue the
                                // same editStakeholder() call the card's own Edit button uses.
                                if ($editStakeholderId > 0 && (int)$st['id'] === $editStakeholderId) {
                                    $autoEditScript = stakeholder_edit_js_call($st, $portalUrl, $contractUrl) . ';';
                                }
                                ?>
                                <div
                                    class="stakeholder-profile-card"
                                    data-stakeholder-name="<?php echo htmlspecialchars(strtolower($st['stakeholder_name']), ENT_QUOTES); ?>"
                                    data-work-type="<?php echo htmlspecialchars(strtolower($st['work_type_key']), ENT_QUOTES); ?>"
                                    onclick="window.location.href='stakeholder_profile.php?id=<?php echo (int)$st['id']; ?>'"
                                >
                                    <div class="stakeholder-actions" onclick="event.stopPropagation()">
                                        <button type="button" class="action-btn edit-btn" title="<?php echo htmlspecialchars(t('edit_details', 'Edit details')); ?>" onclick="<?php echo htmlspecialchars(stakeholder_edit_js_call($st, $portalUrl, $contractUrl), ENT_QUOTES); ?>"><i class="fas fa-edit"></i></button>
                                        <form method="POST" onsubmit="return confirm(<?php echo json_encode(t('confirm_delete_stakeholder', 'Delete stakeholder and all subparts?')); ?>);" style="display:inline;">
                                            <?php echo csrf_field(); ?>
                                            <input type="hidden" name="stakeholder_id" value="<?php echo (int)$st['id']; ?>">
                                            <button type="submit" name="delete_stakeholder" class="action-btn delete-btn" title="<?php echo htmlspecialchars(t('delete', 'Delete')); ?>"><i class="fas fa-trash"></i></button>
                                        </form>
                                    </div>

                                    <div class="stakeholder-profile-avatar">
                                        <?php if (!empty($st['profile_image'])): ?>
                                            <img src="stakeholder_photo.php?id=<?php echo (int)$st['id']; ?>" alt="">
                                        <?php else: ?>
                                            <i class="fas fa-user-hard-hat"></i>
                                        <?php endif; ?>
                                    </div>
                                    <h3 class="stakeholder-profile-name"><?php echo htmlspecialchars($st['stakeholder_name']); ?></h3>
                                    <?php if (!empty($st['company_name'])): ?>
                                        <p class="stakeholder-profile-company"><i class="fas fa-building"></i> <?php echo htmlspecialchars($st['company_name']); ?></p>
                                    <?php endif; ?>
                                    <div class="stakeholder-profile-badges">
                                        <span class="stakeholder-badge stakeholder-worktype-badge"><?php echo htmlspecialchars($displayWorkType); ?></span>
                                        <span class="stakeholder-badge stakeholder-subparts-badge"><?php echo (int)$st['subparts_count']; ?> <?php echo htmlspecialchars(t('subparts', 'subparts')); ?></span>
                                    </div>
                                    <span class="stakeholder-profile-cta"><?php echo htmlspecialchars(t('view_profile', 'View profile')); ?> <i class="fas fa-arrow-right"></i></span>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>
</div>

<?php if ($autoEditScript): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    <?php echo $autoEditScript; ?>
});
</script>
<?php endif; ?>

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

function previewStakeholderPhoto(input) {
    var preview = document.getElementById('stakeholder-photo-preview');
    var placeholder = document.getElementById('stakeholder-photo-preview-placeholder');
    if (!preview || !placeholder) return;
    if (input.files && input.files[0]) {
        preview.src = URL.createObjectURL(input.files[0]);
        preview.style.display = '';
        placeholder.style.display = 'none';
    }
}

function editStakeholder(id, name, companyName, email, phone, workType, stakeholderDate, cashPercentage, apartmentPercentage, apartmentMeterPrice, contractFile, portalUrl, contractUrl, hasPhoto) {
    document.getElementById('stakeholder_id').value = id;
    document.getElementById('stakeholder_name').value = name;
    document.getElementById('company_name').value = companyName || '';
    document.getElementById('email').value = email || '';
    document.getElementById('phone').value = phone || '';
    document.getElementById('stakeholder_date').value = stakeholderDate || '';
    document.getElementById('work_type_key').value = workType;
    document.getElementById('cash_percentage').value = (cashPercentage !== undefined && cashPercentage !== null && cashPercentage !== '') ? cashPercentage : '100';
    document.getElementById('apartment_percentage').value = (apartmentPercentage !== undefined && apartmentPercentage !== null && apartmentPercentage !== '') ? apartmentPercentage : '0';
    document.getElementById('apartment_meter_price').value = (apartmentMeterPrice !== undefined && apartmentMeterPrice !== null && apartmentMeterPrice !== '') ? apartmentMeterPrice : '0';
    var photoPreview = document.getElementById('stakeholder-photo-preview');
    var photoPlaceholder = document.getElementById('stakeholder-photo-preview-placeholder');
    if (photoPreview && photoPlaceholder) {
        if (hasPhoto) {
            photoPreview.src = 'stakeholder_photo.php?id=' + id;
            photoPreview.style.display = '';
            photoPlaceholder.style.display = 'none';
        } else {
            photoPreview.removeAttribute('src');
            photoPreview.style.display = 'none';
            photoPlaceholder.style.display = '';
        }
    }
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
            hint.appendChild(document.createTextNode(' ' + <?php echo json_encode(t('current', 'Current')); ?> + ': '));
            var link = document.createElement('a');
            link.href = contractUrl || '#';
            link.target = '_blank';
            link.textContent = contractFile;
            hint.appendChild(link);
            hint.appendChild(document.createTextNode(' — ' + <?php echo json_encode(t('upload_new_file_to_replace', 'upload a new file to replace it')); ?>));
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
    var photoPreview = document.getElementById('stakeholder-photo-preview');
    var photoPlaceholder = document.getElementById('stakeholder-photo-preview-placeholder');
    if (photoPreview) { photoPreview.removeAttribute('src'); photoPreview.style.display = 'none'; }
    if (photoPlaceholder) { photoPlaceholder.style.display = ''; }
}

function applyStakeholderFilters() {
    var searchInput = document.getElementById('stakeholder-filter-search');
    var workTypeSelect = document.getElementById('stakeholder-filter-worktype');
    var resultText = document.getElementById('stakeholder-filter-result');
    var emptyText = document.getElementById('stakeholder-filter-empty');
    var cards = document.querySelectorAll('#stakeholders-page .stakeholder-profile-card');

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
        var foundLabel = <?php echo json_encode(t('stakeholders_found', '{count} stakeholder(s) found'), JSON_UNESCAPED_UNICODE); ?>;
        resultText.textContent = foundLabel.replace('{count}', visible);
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
