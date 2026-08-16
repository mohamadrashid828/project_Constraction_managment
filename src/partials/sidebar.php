<?php
if (!isset($permissions)) {
    $permissions = [];
}
if (!isset($activePage)) {
    $activePage = '';
}
?>
<nav class="sidebar">
    <div class="sidebar-header">
        <h2><i class="fas fa-building"></i> Green World Towers</h2>
        <p class="company-name">Dahenkar Company</p>
    </div>
    <ul class="sidebar-menu">
        <li class="<?php echo $activePage === 'dashboard' ? 'active' : ''; ?>"><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> <?php echo htmlspecialchars(t('dashboard', 'Dashboard')); ?></a></li>
        <?php if (in_array('user_management', $permissions)): ?>
            <li class="<?php echo $activePage === 'users' ? 'active' : ''; ?>"><a href="users.php"><i class="fas fa-users"></i> <?php echo htmlspecialchars(t('user_management', 'User Management')); ?></a></li>
        <?php endif; ?>
        <?php if (in_array('project_settings', $permissions)): ?>
            <li class="<?php echo $activePage === 'project-settings' ? 'active' : ''; ?>"><a href="project_settings.php"><i class="fas fa-cog"></i> <?php echo htmlspecialchars(t('project_settings', 'Project Settings')); ?></a></li>
        <?php endif; ?>
        <?php if (in_array('stakeholders', $permissions)): ?>
            <li class="<?php echo $activePage === 'stakeholders' ? 'active' : ''; ?>"><a href="stakeholders.php"><i class="fas fa-handshake"></i> <?php echo htmlspecialchars(t('stakeholders', 'Stakeholders')); ?></a></li>
        <?php endif; ?>
        <?php if (in_array('data_entry', $permissions)): ?>
            <li class="<?php echo $activePage === 'data-entry' ? 'active' : ''; ?>"><a href="data_entry.php"><i class="fas fa-edit"></i> <?php echo htmlspecialchars(t('data_entry', 'Data Entry')); ?></a></li>
        <?php endif; ?>
        <?php if (in_array('analytics', $permissions)): ?>
            <li class="<?php echo $activePage === 'analytics' ? 'active' : ''; ?>"><a href="analytics.php"><i class="fas fa-chart-line"></i> <?php echo htmlspecialchars(t('analysis', 'Analysis')); ?></a></li>
        <?php endif; ?>
        <?php if (in_array('slfa', $permissions)): ?>
            <li class="<?php echo $activePage === 'slfa' ? 'active' : ''; ?>">
                <a href="slfa.php"><i class="fas fa-file-invoice-dollar"></i> <?php echo htmlspecialchars(t('payments', 'Payments')); ?></a>
            </li>
        <?php endif; ?>
        <?php if (in_array('inventory.view', $permissions)): ?>
            <li class="<?php echo $activePage === 'storage' ? 'active' : ''; ?>"><a href="storage.php"><i class="fas fa-boxes-stacked"></i> <?php echo htmlspecialchars(t('storage', 'Storage')); ?></a></li>
        <?php endif; ?>
        <?php if (in_array('hr', $permissions)): ?>
            <li class="<?php echo $activePage === 'hr' ? 'active' : ''; ?>"><a href="hr.php"><i class="fas fa-id-card-clip"></i> <?php echo htmlspecialchars(t('hr', 'HR')); ?></a></li>
        <?php endif; ?>
    </ul>
    <div class="sidebar-footer">
        <div class="language-switcher language-switcher-sidebar" aria-label="<?php echo htmlspecialchars(t('language', 'Language')); ?>">
            <label for="language-select"><?php echo htmlspecialchars(t('language', 'Language')); ?></label>
            <select id="language-select">
                <option value="en" <?php echo $currentLanguage === 'en' ? 'selected' : ''; ?>>English</option>
                <option value="ckb" <?php echo $currentLanguage === 'ckb' ? 'selected' : ''; ?>>کوردی</option>
                <option value="ar" <?php echo $currentLanguage === 'ar' ? 'selected' : ''; ?>>العربية</option>
            </select>
        </div>
        <a href="logout.php" class="btn-logout">
            <i class="fas fa-sign-out-alt"></i> <?php echo htmlspecialchars(t('logout', 'Logout')); ?>
        </a>
    </div>
</nav>
