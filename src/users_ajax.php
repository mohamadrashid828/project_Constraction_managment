<?php
session_start();
header('Content-Type: application/json');

if (empty($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}
require_once '../config.php';

$permissions = [];
$permStmt = $conn->prepare('SELECT p.name FROM permissions p JOIN role_permissions rp ON p.id = rp.permission_id JOIN users u ON rp.role_id = u.role_id WHERE u.id = ?');
$permStmt->bind_param('i', $_SESSION['user_id']);
$permStmt->execute();
$permRes = $permStmt->get_result();
while ($permRow = $permRes->fetch_assoc()) {
    $permissions[] = $permRow['name'];
}
$permStmt->close();

$canManageUsers = in_array('user_management', $permissions, true);
if (!$canManageUsers) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
    exit;
}

$action = $_POST['action'] ?? '';

switch ($action) {

    case 'create_user':
        $username  = trim($_POST['username'] ?? '');
        $password  = trim($_POST['password'] ?? '');
        $email     = trim($_POST['email'] ?? '');
        $full_name = trim($_POST['full_name'] ?? '');
        $role_id   = (int)($_POST['role_id'] ?? 0);
        if (!$username || !$password || !$email || !$role_id) {
            echo json_encode(['success' => false, 'message' => 'Username, password, email, and role are required.']);
            exit;
        }

        $checkStmt = $conn->prepare('SELECT id FROM users WHERE username=? OR email=? LIMIT 1');
        $checkStmt->bind_param('ss', $username, $email);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        if ($checkResult->num_rows > 0) {
            $checkStmt->close();
            echo json_encode(['success' => false, 'message' => 'A user with this username or email already exists.']);
            exit;
        }
        $checkStmt->close();

        try {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare('INSERT INTO users (username, password, email, full_name, role_id) VALUES (?,?,?,?,?)');
            $stmt->bind_param('ssssi', $username, $hash, $email, $full_name, $role_id);
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'User created successfully!']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error creating user: ' . $stmt->error]);
            }
            $stmt->close();
        } catch (Throwable $e) {
            echo json_encode(['success' => false, 'message' => 'Could not create user: ' . $e->getMessage()]);
        }
        break;

    case 'edit_user':
        $user_id  = (int)($_POST['user_id'] ?? 0);
        $username = trim($_POST['username'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $role_id  = (int)($_POST['role_id'] ?? 0);
        // Support both full_name (top form) and first_name+last_name (modal)
        if (!empty($_POST['full_name'])) {
            $full_name = trim($_POST['full_name']);
        } else {
            $full_name = trim(($_POST['first_name'] ?? '') . ' ' . ($_POST['last_name'] ?? ''));
        }
        if (!$user_id || !$username || !$email) {
            echo json_encode(['success' => false, 'message' => 'Required fields missing.']);
            exit;
        }

        $checkStmt = $conn->prepare('SELECT id FROM users WHERE (username=? OR email=?) AND id <> ? LIMIT 1');
        $checkStmt->bind_param('ssi', $username, $email, $user_id);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        if ($checkResult->num_rows > 0) {
            $checkStmt->close();
            echo json_encode(['success' => false, 'message' => 'A user with this username or email already exists.']);
            exit;
        }
        $checkStmt->close();

        try {
            $stmt = $conn->prepare('UPDATE users SET username=?, email=?, full_name=?, role_id=? WHERE id=?');
            $stmt->bind_param('sssii', $username, $email, $full_name, $role_id, $user_id);
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'User updated successfully!']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error updating user: ' . $stmt->error]);
            }
            $stmt->close();
        } catch (Throwable $e) {
            echo json_encode(['success' => false, 'message' => 'Could not update user: ' . $e->getMessage()]);
        }
        break;

    case 'delete_user':
        $user_id = (int)($_POST['user_id'] ?? 0);
        if ($user_id === (int)$_SESSION['user_id']) {
            echo json_encode(['success' => false, 'message' => 'You cannot delete your own account!']);
            exit;
        }
        $stmt = $conn->prepare('DELETE FROM users WHERE id=?');
        $stmt->bind_param('i', $user_id);
        echo json_encode($stmt->execute()
            ? ['success' => true,  'message' => 'User deleted successfully!']
            : ['success' => false, 'message' => 'Error: ' . $stmt->error]);
        $stmt->close();
        break;

    case 'toggle_status':
        $user_id = (int)($_POST['user_id'] ?? 0);
        if ($user_id === (int)$_SESSION['user_id']) {
            echo json_encode(['success' => false, 'message' => 'You cannot change your own status.']);
            exit;
        }
        $stmt = $conn->prepare('SELECT is_active FROM users WHERE id=?');
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$row) { echo json_encode(['success' => false, 'message' => 'User not found.']); exit; }
        $new_status = $row['is_active'] ? 0 : 1;
        $stmt = $conn->prepare('UPDATE users SET is_active=? WHERE id=?');
        $stmt->bind_param('ii', $new_status, $user_id);
        echo json_encode($stmt->execute()
            ? ['success' => true, 'message' => 'User ' . ($new_status ? 'activated' : 'deactivated') . '.', 'new_status' => $new_status]
            : ['success' => false, 'message' => 'Error: ' . $stmt->error]);
        $stmt->close();
        break;

    case 'update_role':
        $user_id = (int)($_POST['user_id'] ?? 0);
        $role_id = (int)($_POST['role_id'] ?? 0);
        if ($user_id === (int)$_SESSION['user_id']) {
            echo json_encode(['success' => false, 'message' => 'You cannot change your own role.']);
            exit;
        }
        // Validate the target role exists before writing (clear message instead
        // of relying on the FK to reject it).
        $roleChk = $conn->prepare('SELECT id FROM roles WHERE id=?');
        $roleChk->bind_param('i', $role_id);
        $roleChk->execute();
        $roleOk = (bool)$roleChk->get_result()->fetch_assoc();
        $roleChk->close();
        if (!$user_id || !$role_id || !$roleOk) {
            echo json_encode(['success' => false, 'message' => 'A valid user and role are required.']);
            exit;
        }
        $stmt = $conn->prepare('UPDATE users SET role_id=? WHERE id=?');
        $stmt->bind_param('ii', $role_id, $user_id);
        echo json_encode($stmt->execute()
            ? ['success' => true,  'message' => 'Role updated successfully!']
            : ['success' => false, 'message' => 'Could not update role.']);
        $stmt->close();
        break;

    case 'reset_password':
        $user_id      = (int)($_POST['user_id'] ?? 0);
        $new_password = trim($_POST['new_password'] ?? '');
        if (!$user_id || !$new_password) {
            echo json_encode(['success' => false, 'message' => 'Missing data.']);
            exit;
        }
        $hash = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare('UPDATE users SET password=? WHERE id=?');
        $stmt->bind_param('si', $hash, $user_id);
        echo json_encode($stmt->execute()
            ? ['success' => true,  'message' => 'Password reset successfully!']
            : ['success' => false, 'message' => 'Error: ' . $stmt->error]);
        $stmt->close();
        break;

    case 'create_role':
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $permission_ids = $_POST['permission_ids'] ?? [];
        if (!is_array($permission_ids)) {
            $permission_ids = [$permission_ids];
        }

        if (!$name) {
            echo json_encode(['success' => false, 'message' => 'Role name is required.']);
            exit;
        }

        $stmt = $conn->prepare('INSERT INTO roles (name, description) VALUES (?, ?)');
        $stmt->bind_param('ss', $name, $description);
        if (!$stmt->execute()) {
            echo json_encode(['success' => false, 'message' => 'Error creating role: ' . $stmt->error]);
            $stmt->close();
            exit;
        }
        $role_id = $stmt->insert_id;
        $stmt->close();

        if (is_array($permission_ids) && count($permission_ids) > 0) {
            $permStmt = $conn->prepare('INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)');
            foreach ($permission_ids as $perm_id) {
                $perm_id = (int)$perm_id;
                if ($perm_id > 0) {
                    $permStmt->bind_param('ii', $role_id, $perm_id);
                    $permStmt->execute();
                }
            }
            $permStmt->close();
        }

        echo json_encode(['success' => true, 'message' => 'Role created successfully!']);
        break;

    case 'update_role_record':
        $role_id = (int)($_POST['role_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $permission_ids = $_POST['permission_ids'] ?? [];
        if (!is_array($permission_ids)) {
            $permission_ids = [$permission_ids];
        }

        if (!$role_id || !$name) {
            echo json_encode(['success' => false, 'message' => 'Role ID and name are required.']);
            exit;
        }

        $stmt = $conn->prepare('UPDATE roles SET name=?, description=? WHERE id=?');
        $stmt->bind_param('ssi', $name, $description, $role_id);
        if (!$stmt->execute()) {
            echo json_encode(['success' => false, 'message' => 'Error updating role: ' . $stmt->error]);
            $stmt->close();
            exit;
        }
        $stmt->close();

        $stmt = $conn->prepare('DELETE FROM role_permissions WHERE role_id=?');
        $stmt->bind_param('i', $role_id);
        $stmt->execute();
        $stmt->close();

        if (is_array($permission_ids) && count($permission_ids) > 0) {
            $permStmt = $conn->prepare('INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)');
            foreach ($permission_ids as $perm_id) {
                $perm_id = (int)$perm_id;
                if ($perm_id > 0) {
                    $permStmt->bind_param('ii', $role_id, $perm_id);
                    $permStmt->execute();
                }
            }
            $permStmt->close();
        }

        echo json_encode(['success' => true, 'message' => 'Role updated successfully!']);
        break;

    case 'delete_role':
        $role_id = (int)($_POST['role_id'] ?? 0);
        if (!$role_id) {
            echo json_encode(['success' => false, 'message' => 'Role ID is required.']);
            exit;
        }

        $stmt = $conn->prepare('SELECT COUNT(*) AS user_count FROM users WHERE role_id=?');
        $stmt->bind_param('i', $role_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();

        if (!empty($row['user_count'])) {
            echo json_encode(['success' => false, 'message' => 'Cannot delete this role because it is assigned to one or more users. Reassign or remove those users first.']);
            exit;
        }

        $stmt = $conn->prepare('DELETE FROM role_permissions WHERE role_id=?');
        $stmt->bind_param('i', $role_id);
        $stmt->execute();
        $stmt->close();

        $stmt = $conn->prepare('DELETE FROM roles WHERE id=?');
        $stmt->bind_param('i', $role_id);
        echo json_encode($stmt->execute()
            ? ['success' => true, 'message' => 'Role deleted successfully!']
            : ['success' => false, 'message' => 'Error deleting role: ' . $stmt->error]);
        $stmt->close();
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Unknown action.']);
}
