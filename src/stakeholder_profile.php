<?php
session_start();
if (empty($_SESSION['user_id'])) {
    header('Location: index.html');
    exit;
}
require_once '../config.php';
require_once 'includes/i18n.php';
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

$stakeholder_id = (int)($_GET['id'] ?? 0);
if ($stakeholder_id <= 0) {
    header('Location: stakeholders.php');
    exit;
}

// Self-heal: guarantees the table exists even if this page is reached before
// stakeholders.php has ever run in this database (same idempotent pattern
// used for project_work_types across this app's pages).
$conn->query("CREATE TABLE IF NOT EXISTS project_stakeholder_documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    stakeholder_id INT NOT NULL,
    doc_name VARCHAR(160) NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_stakeholder (stakeholder_id)
)");

$message = '';
$message_type = 'success';
$validMetricTypes = array_keys(stakeholder_metric_options());
$validCurrencyTypes = ['IQD', 'USD'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        http_response_code(403);
        exit('Invalid or expired request.');
    }

    if (isset($_POST['save_subpart'])) {
        $subpart_id = (int)($_POST['subpart_id'] ?? 0);
        $subpart_name = trim($_POST['subpart_name'] ?? '');
        $unit_price = (float)($_POST['unit_price'] ?? 0);
        $metric_type = trim($_POST['metric_type'] ?? 'm²');
        $currency_type = trim($_POST['currency_type'] ?? 'USD');
        if ($currency_type === '') {
            $currency_type = 'USD';
        }

        if (!$subpart_name || $unit_price <= 0) {
            $message = 'Please fill subpart, metric, currency and valid price.';
            $message_type = 'error';
        } elseif (!in_array($metric_type, $validMetricTypes, true)) {
            $message = 'Please choose a valid metric.';
            $message_type = 'error';
        } elseif (!in_array($currency_type, $validCurrencyTypes, true)) {
            $message = 'Please choose a valid currency.';
            $message_type = 'error';
        } else {
            if ($subpart_id > 0) {
                // Scoped to this stakeholder — a tampered subpart_id from another
                // stakeholder simply matches no row and updates nothing.
                $stmt = $conn->prepare('UPDATE project_stakeholder_subparts SET subpart_name = ?, unit_price = ?, metric_type = ?, currency_type = ? WHERE id = ? AND stakeholder_id = ?');
                $stmt->bind_param('sdssii', $subpart_name, $unit_price, $metric_type, $currency_type, $subpart_id, $stakeholder_id);
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
        }
    }

    if (isset($_POST['delete_subpart'])) {
        $subpart_id = (int)($_POST['subpart_id'] ?? 0);
        if ($subpart_id > 0) {
            $stmt = $conn->prepare('DELETE FROM project_stakeholder_subparts WHERE id = ? AND stakeholder_id = ?');
            $stmt->bind_param('ii', $subpart_id, $stakeholder_id);
            if ($stmt->execute()) {
                $message = 'Subpart deleted successfully.';
            } else {
                $message = 'Error deleting subpart.';
                $message_type = 'error';
            }
            $stmt->close();
        }
    }

    if (isset($_POST['save_document'])) {
        $doc_name = trim($_POST['doc_name'] ?? '');
        $docResult = store_stakeholder_document($_FILES['doc_file'] ?? []);

        if (!$docResult['ok']) {
            $message = $docResult['message'];
            $message_type = 'error';
        } elseif ($doc_name === '') {
            @unlink(stakeholder_document_dir() . $docResult['filename']);
            $message = 'Please enter a name for the attachment.';
            $message_type = 'error';
        } else {
            $stmt = $conn->prepare('INSERT INTO project_stakeholder_documents (stakeholder_id, doc_name, file_name) VALUES (?, ?, ?)');
            $stmt->bind_param('iss', $stakeholder_id, $doc_name, $docResult['filename']);
            if ($stmt->execute()) {
                $message = 'Attachment added successfully.';
            } else {
                @unlink(stakeholder_document_dir() . $docResult['filename']);
                $message = 'Error saving the attachment.';
                $message_type = 'error';
            }
            $stmt->close();
        }
    }

    if (isset($_POST['delete_document'])) {
        $document_id = (int)($_POST['document_id'] ?? 0);
        if ($document_id > 0) {
            // Scoped to this stakeholder — a tampered document_id from another
            // stakeholder simply matches no row and deletes nothing.
            $fileStmt = $conn->prepare('SELECT file_name FROM project_stakeholder_documents WHERE id = ? AND stakeholder_id = ?');
            $fileStmt->bind_param('ii', $document_id, $stakeholder_id);
            $fileStmt->execute();
            $fileRow = $fileStmt->get_result()->fetch_assoc();
            $fileStmt->close();

            if ($fileRow) {
                $stmt = $conn->prepare('DELETE FROM project_stakeholder_documents WHERE id = ? AND stakeholder_id = ?');
                $stmt->bind_param('ii', $document_id, $stakeholder_id);
                if ($stmt->execute()) {
                    $docPath = stakeholder_document_dir() . basename($fileRow['file_name']);
                    if (file_exists($docPath)) @unlink($docPath);
                    $message = 'Attachment deleted successfully.';
                } else {
                    $message = 'Error deleting attachment.';
                    $message_type = 'error';
                }
                $stmt->close();
            }
        }
    }

    if (isset($_POST['regenerate_portal_link'])) {
        $new_token = generate_stakeholder_token();
        $stmt = $conn->prepare('UPDATE project_stakeholders SET access_token = ? WHERE id = ?');
        $stmt->bind_param('si', $new_token, $stakeholder_id);
        if ($stmt->execute()) {
            $message = 'New personal link created. The old link no longer works.';
        } else {
            $message = 'Error regenerating portal link.';
            $message_type = 'error';
        }
        $stmt->close();
    }
}

$stStmt = $conn->prepare("SELECT ps.*, wt.work_type_name, wt.work_type_name_ku
    FROM project_stakeholders ps
    LEFT JOIN project_work_types wt ON wt.work_type_key = ps.work_type_key
    WHERE ps.id = ? AND ps.is_active = 1
    LIMIT 1");
$stStmt->bind_param('i', $stakeholder_id);
$stStmt->execute();
$stakeholder = $stStmt->get_result()->fetch_assoc();
$stStmt->close();

if (!$stakeholder) {
    header('Location: stakeholders.php');
    exit;
}

$token = ensure_stakeholder_token_for_id($conn, $stakeholder_id);
$portalUrl = $token ? stakeholder_portal_url($token) : '';
$contractUrl = ($token && !empty($stakeholder['contract_file'])) ? stakeholder_contract_url($token) : '';
$displayWorkType = (($_SESSION['language'] ?? 'en') === 'ckb' && trim((string)($stakeholder['work_type_name_ku'] ?? '')) !== '')
    ? $stakeholder['work_type_name_ku']
    : (trim((string)($stakeholder['work_type_name'] ?? '')) !== '' ? $stakeholder['work_type_name'] : ucfirst($stakeholder['work_type_key']));

$subparts = [];
$subStmt = $conn->prepare('SELECT * FROM project_stakeholder_subparts WHERE stakeholder_id = ? AND is_active = 1 ORDER BY subpart_name');
$subStmt->bind_param('i', $stakeholder_id);
$subStmt->execute();
$subRes = $subStmt->get_result();
while ($sp = $subRes->fetch_assoc()) {
    $subparts[] = $sp;
}
$subStmt->close();

$documents = [];
$docListStmt = $conn->prepare('SELECT * FROM project_stakeholder_documents WHERE stakeholder_id = ? ORDER BY created_at DESC');
$docListStmt->bind_param('i', $stakeholder_id);
$docListStmt->execute();
$docListRes = $docListStmt->get_result();
while ($doc = $docListRes->fetch_assoc()) {
    $documents[] = $doc;
}
$docListStmt->close();

$pageTitle = htmlspecialchars($stakeholder['stakeholder_name']) . ' - Stakeholder Profile';
$pageCss = 'stakeholder_profile.css';
$activePage = 'stakeholders';
require_once 'partials/header.php';
?>
<div id="stakeholder-profile-page" class="dashboard-container">
<?php require_once 'partials/sidebar.php'; ?>

    <main class="main-content">
        <header class="page-header">
            <h1><i class="fas fa-user-hard-hat"></i> <?php echo htmlspecialchars($stakeholder['stakeholder_name']); ?></h1>
            <div class="user-info">
                <a href="stakeholders.php" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left"></i> <?php echo htmlspecialchars(t('back_to_stakeholders', 'Back to Stakeholders')); ?></a>
            </div>
        </header>

        <div class="content-wrapper">
            <?php if ($message): ?>
                <div class="alert alert-<?php echo $message_type; ?>">
                    <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <div class="profile-header-card">
                <div class="profile-header-banner"></div>
                <div class="profile-header-body">
                    <div class="profile-header-identity">
                        <div class="profile-avatar-lg">
                            <?php if (!empty($stakeholder['profile_image'])): ?>
                                <img src="stakeholder_photo.php?id=<?php echo $stakeholder_id; ?>" alt="">
                            <?php else: ?>
                                <i class="fas fa-user-hard-hat"></i>
                            <?php endif; ?>
                        </div>
                        <div>
                            <h2><?php echo htmlspecialchars($stakeholder['stakeholder_name']); ?></h2>
                            <?php if (!empty($stakeholder['company_name'])): ?>
                                <p class="profile-company"><i class="fas fa-building"></i> <?php echo htmlspecialchars($stakeholder['company_name']); ?></p>
                            <?php endif; ?>
                            <div class="profile-contact-row">
                                <?php if (!empty($stakeholder['email'])): ?>
                                    <a class="profile-contact-item" href="mailto:<?php echo htmlspecialchars($stakeholder['email']); ?>"><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($stakeholder['email']); ?></a>
                                <?php endif; ?>
                                <?php if (!empty($stakeholder['phone'])): ?>
                                    <a class="profile-contact-item" href="tel:<?php echo htmlspecialchars($stakeholder['phone']); ?>"><i class="fas fa-phone"></i> <?php echo htmlspecialchars($stakeholder['phone']); ?></a>
                                <?php endif; ?>
                            </div>
                            <div class="profile-badges">
                                <span class="stakeholder-badge stakeholder-worktype-badge"><?php echo htmlspecialchars($displayWorkType); ?></span>
                                <span class="stakeholder-badge stakeholder-subparts-badge"><?php echo count($subparts); ?> <?php echo htmlspecialchars(t('subparts', 'subparts')); ?></span>
                                <?php if (!empty($stakeholder['stakeholder_date'])): ?>
                                    <span class="stakeholder-badge stakeholder-date-badge"><i class="fas fa-calendar"></i> <?php echo htmlspecialchars($stakeholder['stakeholder_date']); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="profile-header-actions">
                        <a href="stakeholders.php?edit=<?php echo $stakeholder_id; ?>" class="btn btn-secondary"><i class="fas fa-edit"></i> <?php echo htmlspecialchars(t('edit_details', 'Edit Details')); ?></a>
                    </div>
                </div>
            </div>

            <div class="profile-info-grid">
                <div class="panel-card profile-terms-card">
                    <div class="card-header">
                        <h2><i class="fas fa-file-invoice-dollar"></i> <?php echo htmlspecialchars(t('payment_terms', 'Payment Terms')); ?></h2>
                    </div>
                    <div class="profile-terms-list">
                        <div class="profile-term-item">
                            <span><?php echo htmlspecialchars(t('cash_percent', 'Cash %')); ?></span>
                            <strong><?php echo number_format((float)($stakeholder['cash_percentage'] ?? 100), 2); ?>%</strong>
                        </div>
                        <div class="profile-term-item">
                            <span><?php echo htmlspecialchars(t('apartment_percent', 'Apartment %')); ?></span>
                            <strong><?php echo number_format((float)($stakeholder['apartment_percentage'] ?? 0), 2); ?>%</strong>
                        </div>
                        <div class="profile-term-item">
                            <span><?php echo htmlspecialchars(t('apt_price_per_sqm', 'Apt. Price / m²')); ?></span>
                            <strong>$<?php echo number_format((float)($stakeholder['apartment_meter_price'] ?? 0), 2); ?></strong>
                        </div>
                        <div class="profile-term-item">
                            <span><?php echo htmlspecialchars(t('contract', 'Contract')); ?></span>
                            <?php if ($contractUrl): ?>
                                <a href="<?php echo htmlspecialchars($contractUrl); ?>" target="_blank"><i class="fas fa-file-contract"></i> <?php echo htmlspecialchars(t('view_file', 'View file')); ?></a>
                            <?php else: ?>
                                <strong class="profile-term-muted"><?php echo htmlspecialchars(t('not_uploaded', 'Not uploaded')); ?></strong>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="panel-card profile-link-card">
                    <div class="card-header">
                        <h2><i class="fas fa-link"></i> <?php echo htmlspecialchars(t('personal_link', 'Personal Link')); ?></h2>
                    </div>
                    <p class="profile-link-note"><?php echo htmlspecialchars(t('share_link_with_name', 'Share this link with {name} — they can view their own profile, prices, work, and payments without logging in.', ['{name}' => $stakeholder['stakeholder_name']])); ?></p>
                    <div class="portal-link-copy-wrap">
                        <input type="text" class="portal-link-input" readonly value="<?php echo htmlspecialchars($portalUrl); ?>" id="profile-portal-link">
                        <button type="button" class="btn btn-sm btn-primary portal-copy-btn" onclick="copyPortalLink(document.getElementById('profile-portal-link').value, this)"><i class="fas fa-copy"></i> <?php echo htmlspecialchars(t('copy', 'Copy')); ?></button>
                        <a class="btn btn-sm btn-secondary portal-preview-btn" href="<?php echo htmlspecialchars($portalUrl); ?>" target="_blank"><i class="fas fa-eye"></i> <?php echo htmlspecialchars(t('preview', 'Preview')); ?></a>
                    </div>
                    <form method="POST" class="profile-regenerate-form" onsubmit="return confirm(<?php echo json_encode(t('confirm_new_link', 'Create a new link? The old link will stop working immediately.')); ?>);">
                        <?php echo csrf_field(); ?>
                        <button type="submit" name="regenerate_portal_link" class="btn btn-sm btn-secondary"><i class="fas fa-sync-alt"></i> <?php echo htmlspecialchars(t('create_new_link', 'Create new link')); ?></button>
                    </form>
                </div>
            </div>

            <div class="panel-card profile-subworks-card">
                <div class="card-header">
                    <h2><i class="fas fa-list"></i> <?php echo htmlspecialchars(t('subwork_pricing', 'Subwork & Pricing')); ?></h2>
                </div>

                <div class="subparts-table-wrap">
                    <table class="data-table subparts-table">
                        <thead>
                            <tr>
                                <th><?php echo htmlspecialchars(t('subpart', 'Subpart')); ?></th>
                                <th><?php echo htmlspecialchars(t('metric', 'Metric')); ?></th>
                                <th><?php echo htmlspecialchars(t('currency', 'Currency')); ?></th>
                                <th><?php echo htmlspecialchars(t('price', 'Price')); ?></th>
                                <th><?php echo htmlspecialchars(t('action', 'Action')); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($subparts)): ?>
                                <tr><td colspan="5" class="empty-text"><?php echo htmlspecialchars(t('no_subparts_yet', 'No subparts yet')); ?></td></tr>
                            <?php else: ?>
                                <?php foreach ($subparts as $sp): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($sp['subpart_name']); ?></td>
                                        <td><?php echo htmlspecialchars(stakeholder_metric_label($sp['metric_type'] ?? 'm²')); ?></td>
                                        <td><?php echo htmlspecialchars($sp['currency_type'] ?? 'USD'); ?></td>
                                        <td><?php echo number_format((float)$sp['unit_price'], 2); ?></td>
                                        <td class="row-actions">
                                            <button
                                                type="button"
                                                class="action-btn edit-btn"
                                                title="<?php echo htmlspecialchars(t('load_in_form', 'Load in form')); ?>"
                                                onclick="prefillSubpartForm(<?php echo (int)$sp['id']; ?>, '<?php echo htmlspecialchars($sp['subpart_name'], ENT_QUOTES); ?>', '<?php echo number_format((float)$sp['unit_price'], 2, '.', ''); ?>', '<?php echo htmlspecialchars($sp['metric_type'] ?? 'm²', ENT_QUOTES); ?>', '<?php echo htmlspecialchars($sp['currency_type'] ?? 'USD', ENT_QUOTES); ?>')"
                                            ><i class="fas fa-edit"></i></button>
                                            <form method="POST" style="display:inline;">
                                                <?php echo csrf_field(); ?>
                                                <input type="hidden" name="subpart_id" value="<?php echo (int)$sp['id']; ?>">
                                                <button type="submit" name="delete_subpart" class="action-btn delete-btn" title="<?php echo htmlspecialchars(t('delete', 'Delete')); ?>" onclick="return confirm(<?php echo json_encode(t('confirm_delete_subpart', 'Delete this subpart?')); ?>);"><i class="fas fa-trash"></i></button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>

                            <tr>
                                <td colspan="5">
                                    <form method="POST" class="subpart-add-form" id="subpart-form">
                                        <?php echo csrf_field(); ?>
                                        <input type="hidden" name="subpart_id" id="subpart-id" value="">
                                        <input type="text" name="subpart_name" id="subpart-name" placeholder="<?php echo htmlspecialchars(t('new_subpart_placeholder', 'New subpart (e.g., ceiling, walls)')); ?>" required>
                                        <select name="metric_type" id="subpart-metric" required>
                                            <?php foreach (stakeholder_metric_options() as $metricKey => $metricLabel): ?>
                                                <option value="<?php echo htmlspecialchars($metricKey); ?>"><?php echo htmlspecialchars($metricLabel); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <select name="currency_type" id="subpart-currency" required>
                                            <option value="IQD">IQD (Dinar)</option>
                                            <option value="USD">USD (Dollar)</option>
                                        </select>
                                        <input type="number" name="unit_price" id="subpart-price" min="0.01" step="0.01" placeholder="<?php echo htmlspecialchars(t('price', 'Price')); ?>" required>
                                        <div class="subpart-form-buttons">
                                            <button type="submit" name="save_subpart" class="btn btn-sm btn-primary"><i class="fas fa-save"></i> <?php echo htmlspecialchars(t('save_subpart', 'Save Subpart')); ?></button>
                                            <button type="button" class="btn btn-sm btn-secondary" onclick="resetSubpartForm()"><i class="fas fa-undo"></i> <?php echo htmlspecialchars(t('reset', 'Reset')); ?></button>
                                        </div>
                                    </form>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="panel-card profile-documents-card">
                <div class="card-header">
                    <h2><i class="fas fa-paperclip"></i> <?php echo htmlspecialchars(t('additional_documents', 'Additional Documents')); ?></h2>
                </div>

                <div class="documents-table-wrap">
                    <table class="data-table documents-table">
                        <thead>
                            <tr>
                                <th><?php echo htmlspecialchars(t('name', 'Name')); ?></th>
                                <th><?php echo htmlspecialchars(t('added', 'Added')); ?></th>
                                <th><?php echo htmlspecialchars(t('action', 'Action')); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($documents)): ?>
                                <tr><td colspan="3" class="empty-text"><?php echo htmlspecialchars(t('no_documents_yet', 'No additional documents yet')); ?></td></tr>
                            <?php else: ?>
                                <?php foreach ($documents as $doc): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($doc['doc_name']); ?></td>
                                        <td><?php echo htmlspecialchars(date('d M Y', strtotime((string)$doc['created_at']))); ?></td>
                                        <td class="row-actions">
                                            <a class="action-btn view-btn" href="stakeholder_document.php?id=<?php echo (int)$doc['id']; ?>" target="_blank" title="<?php echo htmlspecialchars(t('view', 'View')); ?>"><i class="fas fa-eye"></i></a>
                                            <form method="POST" style="display:inline;">
                                                <?php echo csrf_field(); ?>
                                                <input type="hidden" name="document_id" value="<?php echo (int)$doc['id']; ?>">
                                                <button type="submit" name="delete_document" class="action-btn delete-btn" title="<?php echo htmlspecialchars(t('delete', 'Delete')); ?>" onclick="return confirm(<?php echo json_encode(t('confirm_delete_attachment', 'Delete this attachment?')); ?>);"><i class="fas fa-trash"></i></button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>

                            <tr>
                                <td colspan="3">
                                    <form method="POST" class="document-add-form" enctype="multipart/form-data">
                                        <?php echo csrf_field(); ?>
                                        <input type="text" name="doc_name" placeholder="<?php echo htmlspecialchars(t('attachment_name_placeholder', 'Attachment name (e.g., ID Card, Passport, Agreement Addendum)')); ?>" required>
                                        <input type="file" name="doc_file" accept=".pdf,.jpg,.jpeg,.png,.gif,.webp" required>
                                        <button type="submit" name="save_document" class="btn btn-sm btn-primary"><i class="fas fa-upload"></i> <?php echo htmlspecialchars(t('add_attachment', 'Add Attachment')); ?></button>
                                    </form>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>
</div>

<script>
const stakeholderProfileTranslations = window.appTranslations || {};
const profileTx = function(key, fallback) {
    return stakeholderProfileTranslations[key] || fallback || key;
};

function copyPortalLink(url, btn) {
    if (!url) return;
    var done = function() {
        if (!btn) return;
        var original = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-check"></i> ' + profileTx('copied', 'Copied!');
        btn.classList.add('copied');
        setTimeout(function() {
            btn.innerHTML = original;
            btn.classList.remove('copied');
        }, 2000);
    };
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(url).then(done).catch(function() {
            window.prompt(profileTx('copy_this_link', 'Copy this link:'), url);
        });
    } else {
        window.prompt(profileTx('copy_this_link', 'Copy this link:'), url);
        done();
    }
}

function prefillSubpartForm(subpartId, subpartName, unitPrice, metricType, currencyType) {
    document.getElementById('subpart-id').value = subpartId;
    document.getElementById('subpart-name').value = subpartName;
    document.getElementById('subpart-price').value = unitPrice;
    document.getElementById('subpart-metric').value = metricType || 'm²';
    document.getElementById('subpart-currency').value = currencyType || 'USD';
    document.getElementById('subpart-name').focus();
}

function resetSubpartForm() {
    document.getElementById('subpart-form').reset();
    document.getElementById('subpart-id').value = '';
}
</script>
</body>
</html>
