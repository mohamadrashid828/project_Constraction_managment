<?php
session_start();
if (empty($_SESSION['user_id'])) {
    header('Location: index.html');
    exit;
}
require_once '../config.php';
require_once 'includes/permissions.php';

$user_id = (int)$_SESSION['user_id'];
$username = htmlspecialchars($_SESSION['username']);

// Live-role permissions (join through users.id), so a role change applies on
// the next request rather than after re-login.
$permissions = get_user_permissions($conn, $user_id);
?>
<?php
$pageTitle = 'Dashboard - Construction Management';
$pageCss = 'dashboard.css';
$activePage = 'dashboard';
require_once 'partials/header.php';
?>
    <div id="dashboard-page" class="dashboard-container">
<?php
require_once 'partials/sidebar.php';
?>

        <!-- Main Content -->
        <main class="main-content">
            <header class="page-header">
                <h1><i class="fas fa-tachometer-alt"></i> Dashboard</h1>
                <div class="user-info">
                    <span>Welcome, <?php echo $username; ?></span>
                </div>
            </header>

            <div class="content-wrapper">
        <?php
        $totalUsers = $conn->query('SELECT COUNT(*) as count FROM users')->fetch_assoc()['count'] ?? 0;
        // There is no "projects" table in this schema; buildings are the real
        // project unit (analytics.php uses the same proxy).
        $projectCount = (int)($conn->query('SELECT COUNT(*) as count FROM buildings')->fetch_assoc()['count'] ?? 0);
        $totalPayments = 12875000;
        $pendingTasks = 2;
        $teamCount = $totalUsers;
        ?>

        <!-- Statistics Grid -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 20px; margin-bottom: 32px;">
            <div style="background: rgba(255, 255, 255, 0.08); backdrop-filter: blur(10px); border: 1px solid rgba(255, 255, 255, 0.08); border-radius: 12px; padding: 24px; transition: all 0.3s ease;" onmouseover="this.style.background='rgba(96, 165, 250, 0.1)'" onmouseout="this.style.background='rgba(255, 255, 255, 0.08)'">
                <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                    <div>
                        <p style="color: #94a3b8; font-size: 0.9rem; margin-bottom: 8px;">Total Payments</p>
                        <p style="color: #fff; font-size: 2rem; font-weight: 700;"><?php echo number_format($totalPayments); ?></p>
                        <p style="color: #cbd5e1; font-size: 0.85rem; margin-top: 8px;">عراقي دينار</p>
                    </div>
                    <div style="background: rgba(96, 165, 250, 0.2); width: 60px; height: 60px; border-radius: 10px; display: flex; align-items: center; justify-content: center;">
                        <i class="fas fa-money-bill-wave" style="color: #60a5fa; font-size: 1.8rem;"></i>
                    </div>
                </div>
            </div>

            <div style="background: rgba(255, 255, 255, 0.08); backdrop-filter: blur(10px); border: 1px solid rgba(255, 255, 255, 0.08); border-radius: 12px; padding: 24px; transition: all 0.3s ease;" onmouseover="this.style.background='rgba(34, 197, 94, 0.1)'" onmouseout="this.style.background='rgba(255, 255, 255, 0.08)'">
                <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                    <div>
                        <p style="color: #94a3b8; font-size: 0.9rem; margin-bottom: 8px;">Team Members</p>
                        <p style="color: #fff; font-size: 2rem; font-weight: 700;"><?php echo $teamCount; ?></p>
                        <p style="color: #cbd5e1; font-size: 0.85rem; margin-top: 8px;">Active Users</p>
                    </div>
                    <div style="background: rgba(34, 197, 94, 0.2); width: 60px; height: 60px; border-radius: 10px; display: flex; align-items: center; justify-content: center;">
                        <i class="fas fa-users" style="color: #22c55e; font-size: 1.8rem;"></i>
                    </div>
                </div>
            </div>

            <div style="background: rgba(255, 255, 255, 0.08); backdrop-filter: blur(10px); border: 1px solid rgba(255, 255, 255, 0.08); border-radius: 12px; padding: 24px; transition: all 0.3s ease;" onmouseover="this.style.background='rgba(251, 146, 60, 0.1)'" onmouseout="this.style.background='rgba(255, 255, 255, 0.08)'">
                <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                    <div>
                        <p style="color: #94a3b8; font-size: 0.9rem; margin-bottom: 8px;">Pending Tasks</p>
                        <p style="color: #fff; font-size: 2rem; font-weight: 700;"><?php echo $pendingTasks; ?></p>
                        <p style="color: #cbd5e1; font-size: 0.85rem; margin-top: 8px;">Awaiting Attention</p>
                    </div>
                    <div style="background: rgba(251, 146, 60, 0.2); width: 60px; height: 60px; border-radius: 10px; display: flex; align-items: center; justify-content: center;">
                        <i class="fas fa-tasks" style="color: #fb923c; font-size: 1.8rem;"></i>
                    </div>
                </div>
            </div>

            <div style="background: rgba(255, 255, 255, 0.08); backdrop-filter: blur(10px); border: 1px solid rgba(255, 255, 255, 0.08); border-radius: 12px; padding: 24px; transition: all 0.3s ease;" onmouseover="this.style.background='rgba(139, 92, 246, 0.1)'" onmouseout="this.style.background='rgba(255, 255, 255, 0.08)'">
                <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                    <div>
                        <p style="color: #94a3b8; font-size: 0.9rem; margin-bottom: 8px;">Projects</p>
                        <p style="color: #fff; font-size: 2rem; font-weight: 700;"><?php echo $projectCount; ?></p>
                        <p style="color: #cbd5e1; font-size: 0.85rem; margin-top: 8px;">Active Projects</p>
                    </div>
                    <div style="background: rgba(139, 92, 246, 0.2); width: 60px; height: 60px; border-radius: 10px; display: flex; align-items: center; justify-content: center;">
                        <i class="fas fa-project-diagram" style="color: #a78bfa; font-size: 1.8rem;"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Activity Panels -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 24px;">
            <div style="background: rgba(255, 255, 255, 0.08); backdrop-filter: blur(10px); border: 1px solid rgba(255, 255, 255, 0.08); border-radius: 12px; padding: 24px;">
                <h3 style="color: #fff; margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
                    <i class="fas fa-credit-card" style="color: #60a5fa;"></i> Recent Payments
                </h3>
                <ul style="list-style: none; display: flex; flex-direction: column; gap: 12px;">
                    <li style="display: flex; justify-content: space-between; align-items: center; padding: 12px; background: rgba(96, 165, 250, 0.1); border-radius: 8px; border-left: 3px solid #60a5fa;">
                        <div>
                            <p style="color: #fff; font-weight: 500;">Private Company</p>
                            <p style="color: #94a3b8; font-size: 0.85rem;">15/03/2024</p>
                        </div>
                        <span style="color: #60a5fa; font-weight: 600;">د.ع 5,000,000</span>
                    </li>
                    <li style="display: flex; justify-content: space-between; align-items: center; padding: 12px; background: rgba(34, 197, 94, 0.1); border-radius: 8px; border-left: 3px solid #22c55e;">
                        <div>
                            <p style="color: #fff; font-weight: 500;">Advanced Company</p>
                            <p style="color: #94a3b8; font-size: 0.85rem;">18/03/2024</p>
                        </div>
                        <span style="color: #22c55e; font-weight: 600;">$3,500</span>
                    </li>
                    <li style="display: flex; justify-content: space-between; align-items: center; padding: 12px; background: rgba(251, 146, 60, 0.1); border-radius: 8px; border-left: 3px solid #fb923c;">
                        <div>
                            <p style="color: #fff; font-weight: 500;">Hawaran Company</p>
                            <p style="color: #94a3b8; font-size: 0.85rem;">20/03/2024</p>
                        </div>
                        <span style="color: #fb923c; font-weight: 600;">د.ع 2,800,000</span>
                    </li>
                </ul>
            </div>

            <div style="background: rgba(255, 255, 255, 0.08); backdrop-filter: blur(10px); border: 1px solid rgba(255, 255, 255, 0.08); border-radius: 12px; padding: 24px;">
                <h3 style="color: #fff; margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
                    <i class="fas fa-chart-line" style="color: #34d399;"></i> Progress Overview
                </h3>
                <div style="display: flex; flex-direction: column; gap: 16px;">
                    <div>
                        <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                            <p style="color: #cbd5e1; font-size: 0.9rem;">Phase 1</p>
                            <span style="color: #60a5fa; font-weight: 600;">65%</span>
                        </div>
                        <div style="background: rgba(255, 255, 255, 0.1); height: 8px; border-radius: 4px; overflow: hidden;">
                            <div style="background: linear-gradient(90deg, #60a5fa, #3b82f6); width: 65%; height: 100%;"></div>
                        </div>
                    </div>
                    <div>
                        <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                            <p style="color: #cbd5e1; font-size: 0.9rem;">Phase 2</p>
                            <span style="color: #34d399; font-weight: 600;">75%</span>
                        </div>
                        <div style="background: rgba(255, 255, 255, 0.1); height: 8px; border-radius: 4px; overflow: hidden;">
                            <div style="background: linear-gradient(90deg, #34d399, #10b981); width: 75%; height: 100%;"></div>
                        </div>
                    </div>
                    <div>
                        <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                            <p style="color: #cbd5e1; font-size: 0.9rem;">Phase 3</p>
                            <span style="color: #fb923c; font-weight: 600;">85%</span>
                        </div>
                        <div style="background: rgba(255, 255, 255, 0.1); height: 8px; border-radius: 4px; overflow: hidden;">
                            <div style="background: linear-gradient(90deg, #fb923c, #f97316); width: 85%; height: 100%;"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

            </div>
        </main>
    </div>
</body>
</html>