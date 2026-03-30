<?php
session_start();
if (empty($_SESSION['user_id'])) {
    header('Location: index.html');
    exit;
}
require_once '../config.php';

$role_id = $_SESSION['role_id'];
$permissions = [];
$stmt = $conn->prepare('SELECT p.name FROM permissions p JOIN role_permissions rp ON p.id = rp.permission_id WHERE rp.role_id = ?');
$stmt->bind_param('i', $role_id);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $permissions[] = $row['name'];
}
$stmt->close();

if (!in_array('user_management', $permissions)) {
    header('Location: dashboard.php');
    exit;
}

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['create_user'])) {
        $username = trim($_POST['username']);
        $password = trim($_POST['password']);
        $email = trim($_POST['email']);
        $full_name = trim($_POST['full_name']);
        $user_role_id = (int) $_POST['role_id'];

        if ($username && $password && $email) {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare('INSERT INTO users (username, password, email, full_name, role_id) VALUES (?, ?, ?, ?, ?)');
            $stmt->bind_param('ssssi', $username, $hash, $email, $full_name, $user_role_id);
            if ($stmt->execute()) {
                $message = 'User created successfully!';
                $message_type = 'success';
            } else {
                $message = 'Error: ' . $stmt->error;
                $message_type = 'error';
            }
            $stmt->close();
        }
    } elseif (isset($_POST['reset_password'])) {
        $user_id = (int) $_POST['reset_user_id'];
        $new_password = trim($_POST['new_password']);
        if ($user_id && $new_password) {
            $hash = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare('UPDATE users SET password = ? WHERE id = ?');
            $stmt->bind_param('si', $hash, $user_id);
            if ($stmt->execute()) {
                $message = 'Password updated successfully!';
                $message_type = 'success';
            } else {
                $message = 'Error updating password: ' . $stmt->error;
                $message_type = 'error';
            }
            $stmt->close();
        }
    } elseif (isset($_POST['update_role'])) {
        $user_id = (int) $_POST['update_role_user_id'];
        $new_role_id = (int) $_POST['update_role_id'];
        if ($user_id && $new_role_id) {
            $stmt = $conn->prepare('UPDATE users SET role_id = ? WHERE id = ?');
            $stmt->bind_param('ii', $new_role_id, $user_id);
            if ($stmt->execute()) {
                $message = 'Role updated successfully!';
                $message_type = 'success';
            } else {
                $message = 'Error updating role: ' . $stmt->error;
                $message_type = 'error';
            }
            $stmt->close();
        }
    } elseif (isset($_POST['edit_user'])) {
        $user_id = (int) $_POST['edit_user_id'];
        $first_name = trim($_POST['edit_first_name']);
        $last_name = trim($_POST['edit_last_name']);
        $username = trim($_POST['edit_username']);
        $email = trim($_POST['edit_email']);
        $user_role_id = (int) $_POST['edit_role_id'];

        if ($user_id && $username && $email && $first_name && $last_name) {
            $full_name = $first_name . ' ' . $last_name;
            $stmt = $conn->prepare('UPDATE users SET username = ?, email = ?, full_name = ?, role_id = ? WHERE id = ?');
            $stmt->bind_param('sssii', $username, $email, $full_name, $user_role_id, $user_id);
            if ($stmt->execute()) {
                $message = 'User updated successfully!';
                $message_type = 'success';
            } else {
                $message = 'Error updating user: ' . $stmt->error;
                $message_type = 'error';
            }
            $stmt->close();
        }
    } elseif (isset($_POST['toggle_user_id'])) {
        $user_id = (int) $_POST['toggle_user_id'];
        if ($user_id) {
            // Get current status
            $stmt = $conn->prepare('SELECT is_active FROM users WHERE id = ?');
            $stmt->bind_param('i', $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                $user = $result->fetch_assoc();
                $new_status = $user['is_active'] ? 0 : 1;
                $stmt->close();

                $stmt = $conn->prepare('UPDATE users SET is_active = ? WHERE id = ?');
                $stmt->bind_param('ii', $new_status, $user_id);
                if ($stmt->execute()) {
                    $message = 'User ' . ($new_status ? 'activated' : 'deactivated') . ' successfully!';
                    $message_type = 'success';
                } else {
                    $message = 'Error updating user status: ' . $stmt->error;
                    $message_type = 'error';
                }
            }
            $stmt->close();
        }
    } elseif (isset($_POST['delete_user_id'])) {
        $user_id = (int) $_POST['delete_user_id'];

        // Prevent deleting the current user
        if ($user_id == $_SESSION['user_id']) {
            $message = 'You cannot delete your own account!';
            $message_type = 'error';
        } elseif ($user_id) {
            $stmt = $conn->prepare('DELETE FROM users WHERE id = ?');
            $stmt->bind_param('i', $user_id);
            if ($stmt->execute()) {
                $message = 'User deleted successfully!';
                $message_type = 'success';
            } else {
                $message = 'Error deleting user: ' . $stmt->error;
                $message_type = 'error';
            }
            $stmt->close();
        }
    }
}

$usersQuery = $conn->query('SELECT u.id, u.username, u.email, u.full_name, u.is_active, r.name AS role_name FROM users u JOIN roles r ON u.role_id = r.id ORDER BY u.id ASC');
$rolesQuery = $conn->query('SELECT id, name FROM roles ORDER BY name ASC');

// Handle edit mode
$editMode = false;
$editUser = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $editUserId = (int) $_GET['edit'];
    $stmt = $conn->prepare('SELECT u.id, u.username, u.email, u.full_name, u.role_id, r.name AS role_name FROM users u JOIN roles r ON u.role_id = r.id WHERE u.id = ?');
    $stmt->bind_param('i', $editUserId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $editUser = $result->fetch_assoc();
        $editMode = true;
    }
    $stmt->close();
}
?>
<?php
$pageTitle = 'User Management - Construction Management';
$pageCss = 'users.css';
$activePage = 'users';
require_once 'partials/header.php';
?>
    <div id="users-page" class="dashboard-container">
<?php
require_once 'partials/sidebar.php';
?>

        <!-- Main Content -->
        <main class="main-content">
            <header class="page-header">
                <h1><i class="fas fa-users"></i> User Management</h1>
                <div class="user-info">
                    <span>Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?></span>
                </div>
            </header>

            <div class="content-wrapper">
            <!-- Success/Error Message -->
            <?php if ($message): ?>
                <div class="alert alert-<?php echo strpos($message, 'Error') !== false ? 'error' : 'success'; ?>" style="margin-bottom: 24px;">
                    <i class="fas fa-<?php echo strpos($message, 'Error') !== false ? 'exclamation-circle' : 'check-circle'; ?>"></i>
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <!-- User Form -->
            <div style="background: rgba(255, 255, 255, 0.08); backdrop-filter: blur(10px); border: 1px solid rgba(255, 255, 255, 0.08); border-radius: 12px; padding: 32px; margin-bottom: 32px;">
                <h2 style="color: #fff; margin-bottom: 24px; display: flex; align-items: center; gap: 10px;">
                    <i class="fas fa-<?php echo $editMode ? 'user-edit' : 'user-plus'; ?>" style="color: #60a5fa;"></i> <?php echo $editMode ? 'Edit User' : 'Create New User'; ?>
                </h2>

                <form id="create-user-form" method="POST" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px;">
                    <?php if ($editMode): ?>
                        <input type="hidden" name="edit_user" value="1">
                        <input type="hidden" name="edit_user_id" value="<?php echo $editUser['id']; ?>">
                    <?php else: ?>
                        <input type="hidden" name="create_user" value="1">
                    <?php endif; ?>

                    <div>
                        <label style="display: block; color: #cbd5e1; font-size: 0.9rem; margin-bottom: 8px; font-weight: 500;">Username</label>
                        <input type="text" name="username" required style="width: 100%; padding: 12px 16px; background: rgba(255, 255, 255, 0.05); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 8px; color: #fff; font-size: 0.95rem; transition: all 0.3s ease;" placeholder="Enter username" value="<?php echo $editMode ? htmlspecialchars($editUser['username']) : ''; ?>" onmouseover="this.style.borderColor='rgba(96, 165, 250, 0.3)'" onmouseout="this.style.borderColor='rgba(255, 255, 255, 0.1)'">
                    </div>

                    <?php if (!$editMode): ?>
                    <div>
                        <label style="display: block; color: #cbd5e1; font-size: 0.9rem; margin-bottom: 8px; font-weight: 500;">Password</label>
                        <input type="password" name="password" required style="width: 100%; padding: 12px 16px; background: rgba(255, 255, 255, 0.05); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 8px; color: #fff; font-size: 0.95rem; transition: all 0.3s ease;" placeholder="Enter password" onmouseover="this.style.borderColor='rgba(96, 165, 250, 0.3)'" onmouseout="this.style.borderColor='rgba(255, 255, 255, 0.1)'">
                    </div>
                    <?php endif; ?>

                    <div>
                        <label style="display: block; color: #cbd5e1; font-size: 0.9rem; margin-bottom: 8px; font-weight: 500;">Email</label>
                        <input type="email" name="email" required style="width: 100%; padding: 12px 16px; background: rgba(255, 255, 255, 0.05); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 8px; color: #fff; font-size: 0.95rem; transition: all 0.3s ease;" placeholder="Enter email" value="<?php echo $editMode ? htmlspecialchars($editUser['email']) : ''; ?>" onmouseover="this.style.borderColor='rgba(96, 165, 250, 0.3)'" onmouseout="this.style.borderColor='rgba(255, 255, 255, 0.1)'">
                    </div>

                    <div>
                        <label style="display: block; color: #cbd5e1; font-size: 0.9rem; margin-bottom: 8px; font-weight: 500;">Full Name</label>
                        <input type="text" name="full_name" style="width: 100%; padding: 12px 16px; background: rgba(255, 255, 255, 0.05); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 8px; color: #fff; font-size: 0.95rem; transition: all 0.3s ease;" placeholder="Enter full name" value="<?php echo $editMode ? htmlspecialchars($editUser['full_name']) : ''; ?>" onmouseover="this.style.borderColor='rgba(96, 165, 250, 0.3)'" onmouseout="this.style.borderColor='rgba(255, 255, 255, 0.1)'">
                    </div>

                    <div>
                        <label style="display: block; color: #cbd5e1; font-size: 0.9rem; margin-bottom: 8px; font-weight: 500;">Role</label>
                        <select name="role_id" required style="width: 100%; padding: 12px 16px; background: rgba(255, 255, 255, 0.05); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 8px; color: #fff; font-size: 0.95rem; transition: all 0.3s ease; cursor: pointer;" onmouseover="this.style.borderColor='rgba(96, 165, 250, 0.3)'" onmouseout="this.style.borderColor='rgba(255, 255, 255, 0.1)'">
                            <option value="" style="background: #0f172a; color: #fff;">Select a role</option>
                            <?php
                            $rolesQuery->data_seek(0);
                            while ($role = $rolesQuery->fetch_assoc()):
                            ?>
                                <option value="<?php echo $role['id']; ?>" style="background: #0f172a; color: #fff;" <?php echo $editMode && $editUser['role_id'] == $role['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($role['name']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div></div>

                    <div style="display: flex; gap: 12px; grid-column: 1 / -1;">
                        <button type="submit" style="flex: 1; padding: 12px 28px; background: linear-gradient(135deg, #60a5fa 0%, #3b82f6 100%); color: #fff; border: none; border-radius: 8px; font-size: 0.95rem; font-weight: 600; cursor: pointer; transition: all 0.3s ease; box-shadow: 0 4px 15px rgba(96, 165, 250, 0.3);">
                            <i class="fas fa-<?php echo $editMode ? 'save' : 'plus'; ?>"></i> <?php echo $editMode ? 'Update User' : 'Create User'; ?>
                        </button>
                        <?php if ($editMode): ?>
                        <a href="users.php" style="padding: 12px 28px; background: rgba(255, 255, 255, 0.1); color: #cbd5e1; border: 1px solid rgba(255, 255, 255, 0.2); border-radius: 8px; font-size: 0.95rem; font-weight: 600; text-decoration: none; transition: all 0.3s ease; display: inline-flex; align-items: center; gap: 8px;">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <!-- Users Table -->
            <div style="background: rgba(255, 255, 255, 0.08); backdrop-filter: blur(10px); border: 1px solid rgba(255, 255, 255, 0.08); border-radius: 12px; padding: 32px; overflow-x: auto;">
                <h2 style="color: #fff; margin-bottom: 24px; display: flex; align-items: center; gap: 10px;">
                    <i class="fas fa-list" style="color: #34d399;"></i> All Users
                </h2>
                
                <table style="width: 100%; color: #cbd5e1; border-collapse: collapse;">
                    <thead>
                        <tr style="border-bottom: 2px solid rgba(255, 255, 255, 0.1);">
                            <th style="text-align: left; padding: 16px 12px; color: #94a3b8; font-weight: 600; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.5px;"><i class="fas fa-hashtag"></i> ID</th>
                            <th style="text-align: left; padding: 16px 12px; color: #94a3b8; font-weight: 600; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.5px;"><i class="fas fa-user"></i> Username</th>
                            <th style="text-align: left; padding: 16px 12px; color: #94a3b8; font-weight: 600; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.5px;"><i class="fas fa-envelope"></i> Email</th>
                            <th style="text-align: left; padding: 16px 12px; color: #94a3b8; font-weight: 600; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.5px;"><i class="fas fa-shield-alt"></i> Role</th>
                            <th style="text-align: left; padding: 16px 12px; color: #94a3b8; font-weight: 600; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.5px;"><i class="fas fa-power-off"></i> Status</th>
                            <th style="text-align: left; padding: 16px 12px; color: #94a3b8; font-weight: 600; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.5px;"><i class="fas fa-cog"></i> Actions</th>
                        </tr>
                    </thead>
                    <tbody id="users-tbody">
                        <?php while ($user = $usersQuery->fetch_assoc()): 
                            // Split full name into first and last name
                            $nameParts = explode(' ', $user['full_name'], 2);
                            $firstName = $nameParts[0] ?? '';
                            $lastName = $nameParts[1] ?? '';
                        ?>
                        <tr id="user-row-<?php echo $user['id']; ?>" style="border-bottom: 1px solid rgba(255, 255, 255, 0.05); transition: all 0.3s ease;" onmouseover="this.style.backgroundColor='rgba(96, 165, 250, 0.08)'" onmouseout="this.style.backgroundColor='transparent'">
                            <td style="padding: 16px 12px; color: #94a3b8;"><?php echo $user['id']; ?></td>
                            <td style="padding: 16px 12px;"><span style="background: rgba(96, 165, 250, 0.2); padding: 4px 12px; border-radius: 6px; font-weight: 500; color: #60a5fa;"><?php echo htmlspecialchars($user['username']); ?></span></td>
                            <td style="padding: 16px 12px; color: #cbd5e1;"><?php echo htmlspecialchars($user['email']); ?></td>
                            <td style="padding: 16px 12px;"><span style="background: rgba(139, 92, 246, 0.2); padding: 4px 12px; border-radius: 6px; font-weight: 500; color: #c084fc;"><?php echo htmlspecialchars($user['role_name']); ?></span></td>
                            <td style="padding: 16px 12px;">
                                <span style="background: <?php echo $user['is_active'] ? 'rgba(16, 185, 129, 0.2)' : 'rgba(239, 68, 68, 0.2)'; ?>; color: <?php echo $user['is_active'] ? '#10b981' : '#ef4444'; ?>; padding: 4px 12px; border-radius: 6px; font-weight: 500; font-size: 0.9rem;">
                                    <i class="fas fa-<?php echo $user['is_active'] ? 'check-circle' : 'times-circle'; ?>"></i> <?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?>
                                </span>
                            </td>
                            <td style="padding: 16px 12px;">
                                <div style="display: flex; gap: 6px; align-items: center;">
                                    <!-- Primary Edit Button -->
                                    <button type="button" onclick="showEditModal(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($firstName); ?>', '<?php echo htmlspecialchars($lastName); ?>', '<?php echo htmlspecialchars($user['email']); ?>', '<?php echo htmlspecialchars($user['username']); ?>', <?php echo $user['role_id']; ?>)" style="padding: 8px 12px; background: linear-gradient(135deg, #10b981, #059669); color: #fff; border: none; border-radius: 6px; font-size: 0.85rem; font-weight: 600; text-decoration: none; cursor: pointer; transition: all 0.2s ease; display: inline-flex; align-items: center; gap: 6px;">
                                        <i class="fas fa-edit"></i> Edit
                                    </button>

                                    <!-- Quick Actions Dropdown -->
                                    <div style="position: relative; display: inline-block;">
                                        <button type="button" class="action-btn" onclick="toggleActionsMenu(<?php echo $user['id']; ?>)" style="padding: 8px 10px; background: rgba(96, 165, 250, 0.2); color: #60a5fa; border: 1px solid rgba(96, 165, 250, 0.3); border-radius: 6px; font-size: 0.85rem; font-weight: 600; cursor: pointer; transition: all 0.2s ease;">
                                            <i class="fas fa-ellipsis-v"></i>
                                        </button>

                                        <!-- Actions Menu -->
                                        <div id="actions-menu-<?php echo $user['id']; ?>" class="actions-menu" style="display: none; position: absolute; right: 0; top: 100%; background: rgba(15, 23, 42, 0.95); backdrop-filter: blur(10px); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 8px; padding: 8px 0; min-width: 160px; z-index: 1000; box-shadow: 0 10px 25px rgba(0, 0, 0, 0.3); margin-top: 4px;">
                                            <!-- Status Toggle -->
                                            <div class="menu-item" onclick="toggleUserStatus(<?php echo $user['id']; ?>, '<?php echo $user['is_active'] ? 'active' : 'inactive'; ?>')" style="padding: 10px 16px; color: <?php echo $user['is_active'] ? '#ef4444' : '#10b981'; ?>; cursor: pointer; font-size: 0.85rem; display: flex; align-items: center; gap: 8px; transition: background 0.2s ease;" onmouseover="this.style.background='<?php echo $user['is_active'] ? 'rgba(239, 68, 68, 0.1)' : 'rgba(16, 185, 129, 0.1)'; ?>'" onmouseout="this.style.background='transparent'">
                                                <i class="fas fa-<?php echo $user['is_active'] ? 'ban' : 'check'; ?>"></i> <?php echo $user['is_active'] ? 'Deactivate' : 'Activate'; ?>
                                            </div>

                                            <!-- Role Update -->
                                            <div class="menu-item" onclick="showRoleModal(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>', '<?php echo htmlspecialchars($user['role_name']); ?>')" style="padding: 10px 16px; color: #8b5cf6; cursor: pointer; font-size: 0.85rem; display: flex; align-items: center; gap: 8px; transition: background 0.2s ease;" onmouseover="this.style.background='rgba(139, 92, 246, 0.1)'" onmouseout="this.style.background='transparent'">
                                                <i class="fas fa-user-tag"></i> Change Role
                                            </div>

                                            <!-- Password Reset -->
                                            <div class="menu-item" onclick="showPasswordModal(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')" style="padding: 10px 16px; color: #f59e0b; cursor: pointer; font-size: 0.85rem; display: flex; align-items: center; gap: 8px; transition: background 0.2s ease;" onmouseover="this.style.background='rgba(245, 158, 11, 0.1)'" onmouseout="this.style.background='transparent'">
                                                <i class="fas fa-key"></i> Reset Password
                                            </div>

                                            <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                            <!-- Delete User -->
                                            <div style="border-top: 1px solid rgba(255, 255, 255, 0.1); margin: 4px 0;"></div>
                                            <div class="menu-item" onclick="deleteUser(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')" style="padding: 10px 16px; color: #dc2626; cursor: pointer; font-size: 0.85rem; display: flex; align-items: center; gap: 8px; transition: background 0.2s ease;" onmouseover="this.style.background='rgba(220, 38, 38, 0.1)'" onmouseout="this.style.background='transparent'">
                                                <i class="fas fa-trash"></i> Delete User
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>

            <!-- Role Change Modal -->
            <div id="role-modal" class="modal-overlay" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.5); z-index: 2000; align-items: center; justify-content: center;">
                <div class="modal-content" style="background: rgba(15, 23, 42, 0.95); backdrop-filter: blur(20px); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 12px; padding: 24px; max-width: 400px; width: 90%; box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);">
                    <div class="modal-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                        <h3 style="color: #fff; margin: 0; font-size: 1.2rem;">Change User Role</h3>
                        <button onclick="closeRoleModal()" style="background: none; border: none; color: #94a3b8; font-size: 1.5rem; cursor: pointer; padding: 0;"><i class="fas fa-times"></i></button>
                    </div>
                    <form method="POST" id="role-form">
                        <input type="hidden" name="update_role_user_id" id="role_user_id">
                        <div style="margin-bottom: 20px;">
                            <label style="display: block; color: #cbd5e1; font-size: 0.9rem; margin-bottom: 8px; font-weight: 500;">User: <span id="role-username" style="color: #60a5fa;"></span></label>
                            <label style="display: block; color: #cbd5e1; font-size: 0.9rem; margin-bottom: 8px; font-weight: 500;">Current Role: <span id="role-current" style="color: #8b5cf6;"></span></label>
                        </div>
                        <div style="margin-bottom: 24px;">
                            <label for="modal_role_id" style="display: block; color: #cbd5e1; font-size: 0.9rem; margin-bottom: 8px; font-weight: 500;">New Role</label>
                            <select name="update_role_id" id="modal_role_id" required style="width: 100%; padding: 12px 16px; background: rgba(255, 255, 255, 0.05); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 8px; color: #fff; font-size: 0.95rem;">
                                <?php
                                $rolesQuery->data_seek(0);
                                while ($role = $rolesQuery->fetch_assoc()):
                                ?>
                                    <option value="<?php echo $role['id']; ?>" style="background: #0f172a; color: #fff;"><?php echo htmlspecialchars($role['name']); ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div style="display: flex; gap: 12px; justify-content: flex-end;">
                            <button type="button" onclick="closeRoleModal()" style="padding: 10px 20px; background: rgba(255, 255, 255, 0.1); color: #cbd5e1; border: 1px solid rgba(255, 255, 255, 0.2); border-radius: 8px; cursor: pointer;">Cancel</button>
                            <button type="submit" name="update_role" style="padding: 10px 20px; background: linear-gradient(135deg, #8b5cf6, #7c3aed); color: #fff; border: none; border-radius: 8px; cursor: pointer; font-weight: 600;">Update Role</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Password Reset Modal -->
            <div id="password-modal" class="modal-overlay" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.5); z-index: 2000; align-items: center; justify-content: center;">
                <div class="modal-content" style="background: rgba(15, 23, 42, 0.95); backdrop-filter: blur(20px); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 12px; padding: 24px; max-width: 400px; width: 90%; box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);">
                    <div class="modal-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                        <h3 style="color: #fff; margin: 0; font-size: 1.2rem;">Reset User Password</h3>
                        <button onclick="closePasswordModal()" style="background: none; border: none; color: #94a3b8; font-size: 1.5rem; cursor: pointer; padding: 0;"><i class="fas fa-times"></i></button>
                    </div>
                    <form method="POST" id="password-form">
                        <input type="hidden" name="reset_user_id" id="password_user_id">
                        <div style="margin-bottom: 20px;">
                            <p style="color: #cbd5e1; margin-bottom: 16px;">Enter a new password for <span id="password-username" style="color: #60a5fa; font-weight: 600;"></span></p>
                        </div>
                        <div style="margin-bottom: 24px;">
                            <label for="new_password" style="display: block; color: #cbd5e1; font-size: 0.9rem; margin-bottom: 8px; font-weight: 500;">New Password</label>
                            <input type="password" name="new_password" id="new_password" required style="width: 100%; padding: 12px 16px; background: rgba(255, 255, 255, 0.05); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 8px; color: #fff; font-size: 0.95rem;">
                        </div>
                        <div style="display: flex; gap: 12px; justify-content: flex-end;">
                            <button type="button" onclick="closePasswordModal()" style="padding: 10px 20px; background: rgba(255, 255, 255, 0.1); color: #cbd5e1; border: 1px solid rgba(255, 255, 255, 0.2); border-radius: 8px; cursor: pointer;">Cancel</button>
                            <button type="submit" name="reset_password" style="padding: 10px 20px; background: linear-gradient(135deg, #f59e0b, #d97706); color: #fff; border: none; border-radius: 8px; cursor: pointer; font-weight: 600;">Reset Password</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Edit User Modal -->
            <div id="edit-modal" class="modal-overlay" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.5); z-index: 2000; align-items: center; justify-content: center;">
                <div class="modal-content" style="background: rgba(15, 23, 42, 0.95); backdrop-filter: blur(20px); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 12px; padding: 24px; max-width: 500px; width: 90%; box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);">
                    <div class="modal-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                        <h3 style="color: #fff; margin: 0; font-size: 1.2rem;">Edit User</h3>
                        <button onclick="closeEditModal()" style="background: none; border: none; color: #94a3b8; font-size: 1.5rem; cursor: pointer; padding: 0;"><i class="fas fa-times"></i></button>
                    </div>
                    <form method="POST" id="edit-form">
                        <input type="hidden" name="edit_user_id" id="edit_user_id">
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 20px;">
                            <div>
                                <label for="edit_first_name" style="display: block; color: #cbd5e1; font-size: 0.9rem; margin-bottom: 8px; font-weight: 500;">First Name</label>
                                <input type="text" name="edit_first_name" id="edit_first_name" required style="width: 100%; padding: 12px 16px; background: rgba(255, 255, 255, 0.05); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 8px; color: #fff; font-size: 0.95rem;">
                            </div>
                            <div>
                                <label for="edit_last_name" style="display: block; color: #cbd5e1; font-size: 0.9rem; margin-bottom: 8px; font-weight: 500;">Last Name</label>
                                <input type="text" name="edit_last_name" id="edit_last_name" required style="width: 100%; padding: 12px 16px; background: rgba(255, 255, 255, 0.05); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 8px; color: #fff; font-size: 0.95rem;">
                            </div>
                        </div>
                        <div style="margin-bottom: 20px;">
                            <label for="edit_email" style="display: block; color: #cbd5e1; font-size: 0.9rem; margin-bottom: 8px; font-weight: 500;">Email</label>
                            <input type="email" name="edit_email" id="edit_email" required style="width: 100%; padding: 12px 16px; background: rgba(255, 255, 255, 0.05); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 8px; color: #fff; font-size: 0.95rem;">
                        </div>
                        <div style="margin-bottom: 20px;">
                            <label for="edit_username" style="display: block; color: #cbd5e1; font-size: 0.9rem; margin-bottom: 8px; font-weight: 500;">Username</label>
                            <input type="text" name="edit_username" id="edit_username" required style="width: 100%; padding: 12px 16px; background: rgba(255, 255, 255, 0.05); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 8px; color: #fff; font-size: 0.95rem;">
                        </div>
                        <div style="margin-bottom: 24px;">
                            <label for="edit_role_id" style="display: block; color: #cbd5e1; font-size: 0.9rem; margin-bottom: 8px; font-weight: 500;">Role</label>
                            <select name="edit_role_id" id="edit_role_id" required style="width: 100%; padding: 12px 16px; background: rgba(255, 255, 255, 0.05); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 8px; color: #fff; font-size: 0.95rem;">
                                <?php
                                $rolesQuery->data_seek(0);
                                while ($role = $rolesQuery->fetch_assoc()):
                                ?>
                                    <option value="<?php echo $role['id']; ?>" style="background: #0f172a; color: #fff;"><?php echo htmlspecialchars($role['name']); ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div style="display: flex; gap: 12px; justify-content: flex-end;">
                            <button type="button" onclick="closeEditModal()" style="padding: 10px 20px; background: rgba(255, 255, 255, 0.1); color: #cbd5e1; border: 1px solid rgba(255, 255, 255, 0.2); border-radius: 8px; cursor: pointer;">Cancel</button>
                            <button type="submit" name="edit_user" style="padding: 10px 20px; background: linear-gradient(135deg, #10b981, #059669); color: #fff; border: none; border-radius: 8px; cursor: pointer; font-weight: 600;">Update User</button>
                        </div>
                    </form>
                </div>
            </div>

            </div>
        </main>
    </div>

    <script>
        // ── Toast notification ────────────────────────────────────────────
        function showToast(message, type = 'success') {
            const toast = document.createElement('div');
            const isSuccess = type === 'success';
            toast.style.cssText = `
                position: fixed; top: 24px; right: 24px; z-index: 9999;
                padding: 14px 20px; border-radius: 10px; font-size: 0.9rem;
                font-weight: 600; display: flex; align-items: center; gap: 10px;
                min-width: 280px; pointer-events: none;
                background: ${isSuccess ? 'rgba(16,185,129,0.12)' : 'rgba(239,68,68,0.12)'};
                border: 1px solid ${isSuccess ? 'rgba(16,185,129,0.4)' : 'rgba(239,68,68,0.4)'};
                color: ${isSuccess ? '#10b981' : '#ef4444'};
                box-shadow: 0 10px 30px rgba(0,0,0,0.35);
                backdrop-filter: blur(10px);
                transform: translateX(120%); transition: transform 0.3s ease;`;
            toast.innerHTML = `<i class="fas fa-${isSuccess ? 'check-circle' : 'exclamation-circle'}"></i> ${message}`;
            document.body.appendChild(toast);
            setTimeout(() => toast.style.transform = 'translateX(0)', 10);
            setTimeout(() => {
                toast.style.transform = 'translateX(120%)';
                setTimeout(() => toast.remove(), 320);
            }, 3200);
        }

        // ── AJAX helper ───────────────────────────────────────────────────
        async function ajaxPost(data) {
            const fd = new FormData();
            for (const [k, v] of Object.entries(data)) fd.append(k, v);
            const res = await fetch('users_ajax.php', { method: 'POST', body: fd });
            return res.json();
        }

        // ── Refresh only the users table body ─────────────────────────────
        async function refreshUsersTable() {
            const tbody = document.getElementById('users-tbody');
            tbody.style.opacity = '0.4';
            try {
                const res = await fetch('get_users_table.php');
                tbody.innerHTML = await res.text();
            } finally {
                tbody.style.opacity = '1';
            }
        }

        // ── Create / Edit user (top form) ─────────────────────────────────
        document.getElementById('create-user-form').addEventListener('submit', async function(e) {
            e.preventDefault();
            const fd = new FormData(this);
            const isEdit = fd.get('edit_user') === '1';
            const data = { action: isEdit ? 'edit_user' : 'create_user' };
            if (isEdit) {
                data.user_id   = fd.get('edit_user_id');
                data.username  = fd.get('username');
                data.email     = fd.get('email');
                data.full_name = fd.get('full_name');
                data.role_id   = fd.get('role_id');
            } else {
                data.username  = fd.get('username');
                data.password  = fd.get('password');
                data.email     = fd.get('email');
                data.full_name = fd.get('full_name');
                data.role_id   = fd.get('role_id');
            }
            const btn = this.querySelector('button[type="submit"]');
            const origHtml = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
            const res = await ajaxPost(data);
            showToast(res.message, res.success ? 'success' : 'error');
            if (res.success) { this.reset(); await refreshUsersTable(); }
            btn.disabled = false;
            btn.innerHTML = origHtml;
        });

        // ── Edit modal submit ─────────────────────────────────────────────
        document.getElementById('edit-form').addEventListener('submit', async function(e) {
            e.preventDefault();
            const data = {
                action:     'edit_user',
                user_id:    document.getElementById('edit_user_id').value,
                first_name: document.getElementById('edit_first_name').value,
                last_name:  document.getElementById('edit_last_name').value,
                email:      document.getElementById('edit_email').value,
                username:   document.getElementById('edit_username').value,
                role_id:    document.getElementById('edit_role_id').value,
            };
            const res = await ajaxPost(data);
            showToast(res.message, res.success ? 'success' : 'error');
            if (res.success) { closeEditModal(); await refreshUsersTable(); }
        });

        // ── Role modal submit ─────────────────────────────────────────────
        document.getElementById('role-form').addEventListener('submit', async function(e) {
            e.preventDefault();
            const data = {
                action:  'update_role',
                user_id: document.getElementById('role_user_id').value,
                role_id: document.getElementById('modal_role_id').value,
            };
            const res = await ajaxPost(data);
            showToast(res.message, res.success ? 'success' : 'error');
            if (res.success) { closeRoleModal(); await refreshUsersTable(); }
        });

        // ── Password modal submit ─────────────────────────────────────────
        document.getElementById('password-form').addEventListener('submit', async function(e) {
            e.preventDefault();
            const data = {
                action:       'reset_password',
                user_id:      document.getElementById('password_user_id').value,
                new_password: document.getElementById('new_password').value,
            };
            const res = await ajaxPost(data);
            showToast(res.message, res.success ? 'success' : 'error');
            if (res.success) closePasswordModal();
        });

        // ── Toggle status (AJAX — no reload) ──────────────────────────────
        async function toggleUserStatus(userId, currentStatus) {
            const label = currentStatus === 'active' ? 'deactivate' : 'activate';
            if (!confirm('Are you sure you want to ' + label + ' this user?')) return;
            closeAllMenus();
            const res = await ajaxPost({ action: 'toggle_status', user_id: userId });
            showToast(res.message, res.success ? 'success' : 'error');
            if (res.success) await refreshUsersTable();
        }

        // ── Delete user (AJAX — fades row out, no reload) ─────────────────
        async function deleteUser(userId, username) {
            if (!confirm('Delete user "' + username + '"? This cannot be undone.')) return;
            closeAllMenus();
            const res = await ajaxPost({ action: 'delete_user', user_id: userId });
            showToast(res.message, res.success ? 'success' : 'error');
            if (res.success) {
                const row = document.getElementById('user-row-' + userId);
                if (row) {
                    row.style.opacity = '0';
                    row.style.transform = 'translateX(16px)';
                    setTimeout(() => row.remove(), 320);
                }
            }
        }

        // ── Menu helpers ──────────────────────────────────────────────────
        function closeAllMenus() {
            document.querySelectorAll('.actions-menu').forEach(m => m.style.display = 'none');
        }
        function toggleActionsMenu(userId) {
            const menu = document.getElementById('actions-menu-' + userId);
            document.querySelectorAll('.actions-menu').forEach(m => { if (m !== menu) m.style.display = 'none'; });
            menu.style.display = menu.style.display === 'block' ? 'none' : 'block';
        }
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.action-btn') && !e.target.closest('.actions-menu')) closeAllMenus();
        });

        // ── Modal helpers ─────────────────────────────────────────────────
        function showRoleModal(userId, username, currentRole) {
            document.getElementById('role_user_id').value = userId;
            document.getElementById('role-username').textContent = username;
            document.getElementById('role-current').textContent = currentRole;
            document.getElementById('role-modal').style.display = 'flex';
            closeAllMenus();
        }
        function closeRoleModal() {
            document.getElementById('role-modal').style.display = 'none';
            document.getElementById('role-form').reset();
        }
        function showPasswordModal(userId, username) {
            document.getElementById('password_user_id').value = userId;
            document.getElementById('password-username').textContent = username;
            document.getElementById('password-modal').style.display = 'flex';
            closeAllMenus();
        }
        function closePasswordModal() {
            document.getElementById('password-modal').style.display = 'none';
            document.getElementById('password-form').reset();
        }
        function showEditModal(userId, firstName, lastName, email, username, roleId) {
            document.getElementById('edit_user_id').value = userId;
            document.getElementById('edit_first_name').value = firstName;
            document.getElementById('edit_last_name').value = lastName;
            document.getElementById('edit_email').value = email;
            document.getElementById('edit_username').value = username;
            document.getElementById('edit_role_id').value = roleId;
            document.getElementById('edit-modal').style.display = 'flex';
            closeAllMenus();
        }
        function closeEditModal() {
            document.getElementById('edit-modal').style.display = 'none';
            document.getElementById('edit-form').reset();
        }

        // Close modals on overlay click
        ['role-modal', 'password-modal', 'edit-modal'].forEach(id => {
            document.getElementById(id).addEventListener('click', function(e) {
                if (e.target === this) this.style.display = 'none';
            });
        });
    </script>
</body>
</html>
