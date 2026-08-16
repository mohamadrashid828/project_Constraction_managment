<?php
session_start();
require_once '../config.php';
require_once 'includes/i18n.php';

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
                $error = t('account_inactive', 'Account is inactive. Contact an administrator.');
            } elseif (password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role_id'] = $user['role_id'];
                header('Location: dashboard.php');
                exit;
            } else {
                $error = t('invalid_credentials', 'Incorrect username or password.');
            }
        } else {
            $error = t('invalid_credentials', 'Incorrect username or password.');
        }

        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($currentLanguage); ?>" dir="<?php echo locale_direction(); ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo htmlspecialchars(t('sign_in', 'Sign in')); ?> - Avazir</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<link rel="stylesheet" href="../css/style.css">
</head>
<body>
<div class="language-switcher language-switcher-login" aria-label="<?php echo htmlspecialchars(t('language', 'Language')); ?>">
    <label for="language-select"><?php echo htmlspecialchars(t('language', 'Language')); ?></label>
    <select id="language-select">
        <option value="en" <?php echo $currentLanguage === 'en' ? 'selected' : ''; ?>>English</option>
        <option value="ckb" <?php echo $currentLanguage === 'ckb' ? 'selected' : ''; ?>>کوردی</option>
        <option value="ar" <?php echo $currentLanguage === 'ar' ? 'selected' : ''; ?>>العربية</option>
    </select>
</div>
<main class="login-page login-modern">
    <div class="login-card login-modern-card">
        <section class="login-visual" aria-labelledby="visual-title">
            <div class="visual-topline">
                <a class="visual-brand" href="login.php" aria-label="Avazir home">
                    <span class="visual-brand-mark"><i class="fa-solid fa-building"></i></span>
                    <span><strong>AVAZIR</strong><small>by Kaver</small></span>
                </a>
                <span class="project-index">01 <i></i> 03</span>
            </div>

            <div class="visual-copy">
                <p class="visual-kicker"><?php echo htmlspecialchars(t('construction_intelligence', 'Construction intelligence')); ?></p>
                <h1 id="visual-title"><?php echo htmlspecialchars(t('make_every', 'Make every')); ?><br><span><?php echo htmlspecialchars(t('structure_count', 'structure count.')); ?></span></h1>
                <p class="visual-description"><?php echo htmlspecialchars(t('login_hero_description', 'Plan, coordinate, and deliver with one clear view of your people, progress, and places.')); ?></p>
            </div>

            <div class="architecture-frame" aria-label="Modern tower project illustration">
                <span class="frame-label">PROJECT / GREEN WORLD TOWERS</span>
                <span class="frame-coordinate">35&deg;41' / 51&deg;25'</span>
                <img src="../assets/images/avazir-architecture.svg" alt="Modern high-rise tower under construction" class="architecture-image">
                <span class="frame-line frame-line-left"></span>
                <span class="frame-line frame-line-bottom"></span>
            </div>

            <div class="visual-footer">
                <span><i class="fa-solid fa-circle-check"></i> <?php echo htmlspecialchars(t('systems_online', 'Systems online')); ?></span>
                <span><?php echo htmlspecialchars(t('build_better', 'Build better, together.')); ?></span>
            </div>
        </section>

        <section class="login-form-wrapper login-modern-form">
            <div class="form-intro">
                <div class="form-icon"><i class="fa-regular fa-user"></i></div>
                <p class="form-kicker"><?php echo htmlspecialchars(t('project_workspace', 'Project workspace')); ?></p>
                <h2><?php echo htmlspecialchars(t('welcome_back', 'Welcome back')); ?></h2>
                <p><?php echo htmlspecialchars(t('sign_in_continue', 'Sign in to continue to your account.')); ?></p>
            </div>

            <?php if ($error): ?>
                <div class="error-message" role="alert"><i class="fa-solid fa-circle-exclamation"></i><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form action="login.php" method="POST" class="login-form">
                <div class="field-group">
                    <label for="username"><?php echo htmlspecialchars(t('username_or_email', 'Username or email')); ?></label>
                    <div class="field-control">
                        <i class="fa-regular fa-user"></i>
                        <input type="text" id="username" name="username" placeholder="<?php echo htmlspecialchars(t('enter_username_or_email', 'Enter your username or email')); ?>" required autocomplete="username">
                    </div>
                </div>

                <div class="field-group">
                    <div class="field-label-row">
                        <label for="password"><?php echo htmlspecialchars(t('password', 'Password')); ?></label>
                        <span class="field-hint"><?php echo htmlspecialchars(t('required', 'Required')); ?></span>
                    </div>
                    <div class="field-control">
                        <i class="fa-solid fa-lock"></i>
                        <input type="password" id="password" name="password" placeholder="<?php echo htmlspecialchars(t('enter_password', 'Enter your password')); ?>" required autocomplete="current-password">
                        <button type="button" class="password-toggle" aria-label="<?php echo htmlspecialchars(t('show_password', 'Show password')); ?>"><i class="fa-regular fa-eye"></i></button>
                    </div>
                </div>

                <button type="submit" class="btn-primary login-submit"><span><?php echo htmlspecialchars(t('sign_in_workspace', 'Sign in to workspace')); ?></span><i class="fa-solid fa-arrow-right"></i></button>
            </form>

            <div class="form-bottom"><span><i class="fa-solid fa-shield-halved"></i> <?php echo htmlspecialchars(t('secure_access', 'Secure access')); ?></span><span><?php echo htmlspecialchars(t('need_help', 'Need help? Contact an administrator')); ?></span></div>
        </section>
    </div>
</main>

<script>
document.querySelector('.password-toggle')?.addEventListener('click', function () {
    const input = document.getElementById('password');
    const icon = this.querySelector('i');
    const isPassword = input.type === 'password';
    input.type = isPassword ? 'text' : 'password';
    icon.classList.toggle('fa-eye', !isPassword);
    icon.classList.toggle('fa-eye-slash', isPassword);
    const strings = <?php echo translation_json(); ?>;
    this.setAttribute('aria-label', isPassword ? (strings.hide_password || 'Hide password') : (strings.show_password || 'Show password'));
});
document.getElementById('language-select')?.addEventListener('change', function () {
    const url = new URL(window.location.href);
    url.searchParams.set('lang', this.value);
    window.location.assign(url.toString());
});
</script>
</body>
</html>
