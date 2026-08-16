<?php
session_start();
if (empty($_SESSION['user_id'])) { http_response_code(403); exit; }
require_once '../config.php';
require_once 'includes/permissions.php';
require_once 'includes/i18n.php';

$currentUserId = (int)$_SESSION['user_id'];
if (!in_array('user_management', get_user_permissions($conn, $currentUserId), true)) {
    http_response_code(403);
    exit;
}
$usersQuery = $conn->query('SELECT u.id, u.username, u.email, u.full_name, u.is_active, u.role_id, r.name AS role_name FROM users u JOIN roles r ON u.role_id = r.id ORDER BY u.id ASC');

while ($user = $usersQuery->fetch_assoc()):
    $nameParts = explode(' ', $user['full_name'], 2);
    // HTML-escaped for display inside table cells.
    $username  = htmlspecialchars($user['username'], ENT_QUOTES);
    $email     = htmlspecialchars($user['email'], ENT_QUOTES);
    $roleName  = htmlspecialchars($user['role_name'], ENT_QUOTES);
    // JSON-encoded then attribute-escaped for safe use as JS-string arguments
    // inside onclick handlers (prevents the quote-breakout XSS).
    $jsFirst    = htmlspecialchars(json_encode($nameParts[0] ?? ''), ENT_QUOTES);
    $jsLast     = htmlspecialchars(json_encode($nameParts[1] ?? ''), ENT_QUOTES);
    $jsUsername = htmlspecialchars(json_encode($user['username']), ENT_QUOTES);
    $jsEmail    = htmlspecialchars(json_encode($user['email']), ENT_QUOTES);
    $jsRoleName = htmlspecialchars(json_encode($user['role_name']), ENT_QUOTES);
    $isActive  = (bool)$user['is_active'];
    $uid       = (int)$user['id'];
    $roleId    = (int)$user['role_id'];
?>
<tr id="user-row-<?= $uid ?>" style="border-bottom:1px solid rgba(255,255,255,0.05);transition:all 0.3s ease;" onmouseover="this.style.backgroundColor='rgba(96,165,250,0.08)'" onmouseout="this.style.backgroundColor='transparent'">
    <td style="padding:16px 12px;color:#94a3b8;"><?= $uid ?></td>
    <td style="padding:16px 12px;"><span style="background:rgba(96,165,250,0.2);padding:4px 12px;border-radius:6px;font-weight:500;color:#60a5fa;"><?= $username ?></span></td>
    <td style="padding:16px 12px;color:#cbd5e1;"><?= $email ?></td>
    <td style="padding:16px 12px;"><span style="background:rgba(139,92,246,0.2);padding:4px 12px;border-radius:6px;font-weight:500;color:#c084fc;"><?= $roleName ?></span></td>
    <td style="padding:16px 12px;" id="user-status-<?= $uid ?>">
        <span style="background:<?= $isActive ? 'rgba(16,185,129,0.2)' : 'rgba(239,68,68,0.2)' ?>;color:<?= $isActive ? '#10b981' : '#ef4444' ?>;padding:4px 12px;border-radius:6px;font-weight:500;font-size:0.9rem;">
            <i class="fas fa-<?= $isActive ? 'check-circle' : 'times-circle' ?>"></i> <?= htmlspecialchars($isActive ? t('active', 'Active') : t('inactive', 'Inactive')) ?>
        </span>
    </td>
    <td style="padding:16px 12px;">
        <div style="display:flex;gap:6px;align-items:center;">
            <button type="button" onclick="showEditModal(<?= $uid ?>,<?= $jsFirst ?>,<?= $jsLast ?>,<?= $jsEmail ?>,<?= $jsUsername ?>,<?= $roleId ?>)" style="padding:8px 12px;background:linear-gradient(135deg,#10b981,#059669);color:#fff;border:none;border-radius:6px;font-size:0.85rem;font-weight:600;cursor:pointer;transition:all 0.2s;display:inline-flex;align-items:center;gap:6px;">
                <i class="fas fa-edit"></i> <?= htmlspecialchars(t('edit', 'Edit')) ?>
            </button>
            <div style="position:relative;display:inline-block;">
                <button type="button" class="action-btn" onclick="toggleActionsMenu(<?= $uid ?>)" style="padding:8px 10px;background:rgba(96,165,250,0.2);color:#60a5fa;border:1px solid rgba(96,165,250,0.3);border-radius:6px;font-size:0.85rem;cursor:pointer;">
                    <i class="fas fa-ellipsis-v"></i>
                </button>
                <div id="actions-menu-<?= $uid ?>" class="actions-menu" style="display:none;position:absolute;right:0;top:100%;background:rgba(15,23,42,0.97);backdrop-filter:blur(10px);border:1px solid rgba(255,255,255,0.1);border-radius:8px;padding:8px 0;min-width:160px;z-index:1000;box-shadow:0 10px 25px rgba(0,0,0,0.4);margin-top:4px;">
                    <div class="menu-item" onclick="toggleUserStatus(<?= $uid ?>,'<?= $isActive ? 'active' : 'inactive' ?>')" style="padding:10px 16px;color:<?= $isActive ? '#ef4444' : '#10b981' ?>;cursor:pointer;font-size:0.85rem;display:flex;align-items:center;gap:8px;" onmouseover="this.style.background='<?= $isActive ? 'rgba(239,68,68,0.1)' : 'rgba(16,185,129,0.1)' ?>'" onmouseout="this.style.background='transparent'">
                        <i class="fas fa-<?= $isActive ? 'ban' : 'check' ?>"></i> <?= htmlspecialchars($isActive ? t('deactivate', 'Deactivate') : t('activate', 'Activate')) ?>
                    </div>
                    <div class="menu-item" onclick="showRoleModal(<?= $uid ?>,<?= $jsUsername ?>,<?= $jsRoleName ?>)" style="padding:10px 16px;color:#8b5cf6;cursor:pointer;font-size:0.85rem;display:flex;align-items:center;gap:8px;" onmouseover="this.style.background='rgba(139,92,246,0.1)'" onmouseout="this.style.background='transparent'">
                        <i class="fas fa-user-tag"></i> <?= htmlspecialchars(t('change_role', 'Change role')) ?>
                    </div>
                    <div class="menu-item" onclick="showPasswordModal(<?= $uid ?>,<?= $jsUsername ?>)" style="padding:10px 16px;color:#f59e0b;cursor:pointer;font-size:0.85rem;display:flex;align-items:center;gap:8px;" onmouseover="this.style.background='rgba(245,158,11,0.1)'" onmouseout="this.style.background='transparent'">
                        <i class="fas fa-key"></i> <?= htmlspecialchars(t('reset_password', 'Reset password')) ?>
                    </div>
                    <?php if ($uid !== $currentUserId): ?>
                    <div style="border-top:1px solid rgba(255,255,255,0.1);margin:4px 0;"></div>
                    <div class="menu-item" onclick="deleteUser(<?= $uid ?>,<?= $jsUsername ?>)" style="padding:10px 16px;color:#dc2626;cursor:pointer;font-size:0.85rem;display:flex;align-items:center;gap:8px;" onmouseover="this.style.background='rgba(220,38,38,0.1)'" onmouseout="this.style.background='transparent'">
                        <i class="fas fa-trash"></i> <?= htmlspecialchars(t('delete_user', 'Delete user')) ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </td>
</tr>
<?php endwhile; ?>
