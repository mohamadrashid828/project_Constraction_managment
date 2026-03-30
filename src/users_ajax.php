<?php
session_start();
header('Content-Type: application/json');

if (empty($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}
require_once '../config.php';

$action = $_POST['action'] ?? '';

switch ($action) {

    case 'create_user':
        $username  = trim($_POST['username'] ?? '');
        $password  = trim($_POST['password'] ?? '');
        $email     = trim($_POST['email'] ?? '');
        $full_name = trim($_POST['full_name'] ?? '');
        $role_id   = (int)($_POST['role_id'] ?? 0);
        if (!$username || !$password || !$email) {
            echo json_encode(['success' => false, 'message' => 'Username, password and email are required.']);
            exit;
        }
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare('INSERT INTO users (username, password, email, full_name, role_id) VALUES (?,?,?,?,?)');
        $stmt->bind_param('ssssi', $username, $hash, $email, $full_name, $role_id);
        echo json_encode($stmt->execute()
            ? ['success' => true,  'message' => 'User created successfully!']
            : ['success' => false, 'message' => 'Error: ' . $stmt->error]);
        $stmt->close();
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
        $stmt = $conn->prepare('UPDATE users SET username=?, email=?, full_name=?, role_id=? WHERE id=?');
        $stmt->bind_param('sssii', $username, $email, $full_name, $role_id, $user_id);
        echo json_encode($stmt->execute()
            ? ['success' => true,  'message' => 'User updated successfully!']
            : ['success' => false, 'message' => 'Error: ' . $stmt->error]);
        $stmt->close();
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
        $stmt = $conn->prepare('UPDATE users SET role_id=? WHERE id=?');
        $stmt->bind_param('ii', $role_id, $user_id);
        echo json_encode($stmt->execute()
            ? ['success' => true,  'message' => 'Role updated successfully!']
            : ['success' => false, 'message' => 'Error: ' . $stmt->error]);
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

    default:
        echo json_encode(['success' => false, 'message' => 'Unknown action.']);
}
