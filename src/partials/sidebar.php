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
        <li class="<?php echo $activePage === 'dashboard' ? 'active' : ''; ?>"><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
        <?php if (in_array('user_management', $permissions)): ?>
            <li class="<?php echo $activePage === 'users' ? 'active' : ''; ?>"><a href="users.php"><i class="fas fa-users"></i> User Management</a></li>
        <?php endif; ?>
        <?php if (in_array('project_settings', $permissions)): ?>
            <li class="<?php echo $activePage === 'project-settings' ? 'active' : ''; ?>"><a href="project_settings.php"><i class="fas fa-cog"></i> Project Settings</a></li>
        <?php endif; ?>
        <?php if (in_array('stakeholders', $permissions)): ?>
            <li class="<?php echo $activePage === 'stakeholders' ? 'active' : ''; ?>"><a href="stakeholders.php"><i class="fas fa-handshake"></i> Stakeholders</a></li>
        <?php endif; ?>
        <?php if (in_array('data_entry', $permissions)): ?>
            <li class="<?php echo $activePage === 'data-entry' ? 'active' : ''; ?>"><a href="data_entry.php"><i class="fas fa-edit"></i> Data Entry</a></li>
        <?php endif; ?>
        <?php if (in_array('analytics', $permissions)): ?>
            <li class="<?php echo $activePage === 'analytics' ? 'active' : ''; ?>"><a href="analytics.php"><i class="fas fa-chart-line"></i> Analysis</a></li>
        <?php endif; ?>
        <?php if (in_array('slfa', $permissions)): ?>
            <li class="<?php echo $activePage === 'slfa' ? 'active' : ''; ?>">
                <a href="slfa.php"><i class="fas fa-file-invoice-dollar"></i> Slfa <span style="font-size:0.75em;opacity:0.65;margin-left:3px;">سلفه</span></a>
            </li>
        <?php endif; ?>
        <?php if (in_array('inventory.view', $permissions)): ?>
            <li class="<?php echo $activePage === 'storage' ? 'active' : ''; ?>"><a href="storage.php"><i class="fas fa-boxes-stacked"></i> Storage</a></li>
        <?php endif; ?>
    </ul>
    <div class="sidebar-footer">
        <a href="logout.php" class="btn-logout">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
    </div>
</nav>
