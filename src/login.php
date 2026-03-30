<?php
session_start();
require_once '../config.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($username && $password) {
        $stmt = $conn->prepare('SELECT id, username, password, role_id, is_active FROM users WHERE username = ? LIMIT 1');
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            if (!$user['is_active']) {
                $error = 'Account is inactive. Contact admin.';
            } elseif (password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role_id'] = $user['role_id'];
                header('Location: dashboard.php');
                exit;
            } else {
                $error = 'Incorrect username or password.';
            }
        } else {
            $error = 'Incorrect username or password.';
        }

        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login - Construction Management</title>
<link rel="stylesheet" href="../css/style.css">
</head>
<body>
<div class="login-page">
    <div class="login-card">
        <div class="login-illustration">
            <img src="../assets/images/download.jpeg" alt="Construction illustration" class="illustration-img" />
            <div class="shape" aria-hidden="true"></div>
            <h1>Welcome Back</h1>
            <p>Sign in to unlock your project dashboard and manage your construction workflows.</p>
        </div>

        <div class="login-form-wrapper">
            <div class="brand">
                <h2>BuildFlow</h2>
                <span>Construction Management Portal</span>
            </div>
            <?php if ($error): ?>
                <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form action="login.php" method="POST" class="login-form">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" placeholder="Enter your username" required>

                <label for="password">Password</label>
                <input type="password" id="password" name="password" placeholder="Enter your password" required>

                <button type="submit" class="btn-primary">Login</button>
            </form>

            <small>Only authorized users are allowed.</small>
        </div>
    </div>
</div>
</body>
</html>
