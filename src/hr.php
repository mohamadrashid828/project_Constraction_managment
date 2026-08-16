<?php
session_start();
require_once '../config.php';
require_once 'includes/permissions.php';
require_once 'includes/csrf.php';
require_once 'includes/hr.php';

if (empty($_SESSION['user_id'])) {
    header('Location: index.html');
    exit;
}

$user_id = (int)$_SESSION['user_id'];

// Schema (and the self-healing 'hr' permission grant) must exist before the
// permission check, or an admin's very first visit would bounce.
ensure_hr_schema($conn);

$permissions = get_user_permissions($conn, $user_id);
if (!in_array('hr', $permissions, true)) {
    header('Location: dashboard.php?error=access_denied');
    exit;
}

$csrf = csrf_token();
$today = date('Y-m-d');
$types = hr_employment_types();
$empStatuses = hr_employee_statuses();
$attStatuses = hr_attendance_statuses();
$leaveTypes = hr_leave_types();

// Contracts whose end date has passed flip to 'expired' automatically.
$conn->query("UPDATE hr_contracts SET status = 'expired'
              WHERE status = 'active' AND end_date IS NOT NULL AND end_date < CURDATE()");

// ── Employees ───────────────────────────────────────────────────────────────
$employees = [];
$res = $conn->query("SELECT e.*, b.building_name FROM hr_employees e
                     LEFT JOIN buildings b ON b.id = e.building_id
                     WHERE e.is_deleted = 0
                     ORDER BY e.full_name");
while ($res && $r = $res->fetch_assoc()) {
    $employees[] = $r;
}

$totalEmployees = count($employees);
$activeEmployees = [];
$byType = array_fill_keys(array_keys($types), 0);
$departments = [];
foreach ($employees as $e) {
    if ($e['status'] === 'active') {
        $activeEmployees[] = $e;
    }
    if (isset($byType[$e['employment_type']])) {
        $byType[$e['employment_type']]++;
    }
    $dept = trim((string)$e['department']);
    if ($dept !== '') {
        $departments[$dept] = true;
    }
}

// ── Attendance for the selected day ────────────────────────────────────────
$attDate = (string)($_GET['att_date'] ?? $today);
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $attDate) || $attDate > $today) {
    $attDate = $today;
}
$attRows = [];
$stmt = $conn->prepare("SELECT * FROM hr_attendance WHERE att_date = ?");
$stmt->bind_param('s', $attDate);
$stmt->execute();
$res = $stmt->get_result();
while ($r = $res->fetch_assoc()) {
    $attRows[(int)$r['employee_id']] = $r;
}
$stmt->close();

$todayAtt = ['present' => 0, 'late' => 0, 'half_day' => 0, 'absent' => 0, 'leave' => 0];
$stmt = $conn->prepare("SELECT a.status, COUNT(*) c FROM hr_attendance a
                        JOIN hr_employees e ON e.id = a.employee_id AND e.is_deleted = 0
                        WHERE a.att_date = ? GROUP BY a.status");
$stmt->bind_param('s', $today);
$stmt->execute();
$res = $stmt->get_result();
while ($r = $res->fetch_assoc()) {
    $todayAtt[(string)$r['status']] = (int)$r['c'];
}
$stmt->close();
$presentToday = $todayAtt['present'] + $todayAtt['late'] + $todayAtt['half_day'];

// ── Leaves ──────────────────────────────────────────────────────────────────
$onLeaveToday = (int)($conn->query("SELECT COUNT(DISTINCT l.employee_id) c FROM hr_leaves l
                                    JOIN hr_employees e ON e.id = l.employee_id AND e.is_deleted = 0
                                    WHERE l.status = 'approved' AND l.date_from <= CURDATE() AND l.date_to >= CURDATE()")
                                   ->fetch_assoc()['c'] ?? 0);

$pendingLeaves = [];
$leaveHistory = [];
$res = $conn->query("SELECT l.*, e.full_name, e.employee_code, u.username AS reviewer
                     FROM hr_leaves l
                     JOIN hr_employees e ON e.id = l.employee_id
                     LEFT JOIN users u ON u.id = l.reviewed_by
                     ORDER BY l.created_at DESC LIMIT 200");
while ($res && $r = $res->fetch_assoc()) {
    if ($r['status'] === 'pending') {
        $pendingLeaves[] = $r;
    } else {
        $leaveHistory[] = $r;
    }
}

// ── Contracts ───────────────────────────────────────────────────────────────
$contracts = [];
$expiringContracts = [];
$expiryLimit = date('Y-m-d', strtotime('+30 days'));
$res = $conn->query("SELECT c.*, e.full_name, e.employee_code FROM hr_contracts c
                     JOIN hr_employees e ON e.id = c.employee_id
                     WHERE e.is_deleted = 0
                     ORDER BY (c.status = 'active') DESC, c.end_date IS NULL, c.end_date ASC, c.id DESC
                     LIMIT 300");
while ($res && $r = $res->fetch_assoc()) {
    $r['expiring'] = ($r['status'] === 'active' && $r['end_date'] !== null
        && $r['end_date'] >= $today && $r['end_date'] <= $expiryLimit);
    $contracts[] = $r;
    if ($r['expiring']) {
        $expiringContracts[] = $r;
    }
}

// ── Payroll ─────────────────────────────────────────────────────────────────
$payrollRows = [];
$res = $conn->query("SELECT p.*, e.full_name, e.employee_code, b.building_name
                     FROM hr_payroll p
                     JOIN hr_employees e ON e.id = p.employee_id
                     LEFT JOIN buildings b ON b.id = p.building_id
                     ORDER BY p.period_start DESC, p.id DESC LIMIT 200");
while ($res && $r = $res->fetch_assoc()) {
    $payrollRows[] = $r;
}
$unpaidCount = (int)($conn->query("SELECT COUNT(*) c FROM hr_payroll WHERE payment_status = 'unpaid'")->fetch_assoc()['c'] ?? 0);

// ── Dashboard charts ────────────────────────────────────────────────────────
$typeChartLabels = [];
$typeChartValues = [];
foreach ($byType as $key => $count) {
    if ($count > 0) {
        $typeChartLabels[] = $types[$key];
        $typeChartValues[] = $count;
    }
}

$weekLabels = [];
$weekValues = [];
$weekLookup = [];
$res = $conn->query("SELECT att_date, SUM(status IN ('present','late')) + 0.5 * SUM(status = 'half_day') AS worked
                     FROM hr_attendance
                     WHERE att_date >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
                     GROUP BY att_date");
while ($res && $r = $res->fetch_assoc()) {
    $weekLookup[(string)$r['att_date']] = (float)$r['worked'];
}
for ($i = 6; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    $weekLabels[] = date('D', strtotime($d));
    $weekValues[] = $weekLookup[$d] ?? 0;
}

$buildings = [];
$res = $conn->query("SELECT id, building_name FROM buildings WHERE status = 'active' ORDER BY building_name");
while ($res && $r = $res->fetch_assoc()) {
    $buildings[] = $r;
}

function hr_badge(string $status): string
{
    $map = [
        'active' => 'ok', 'present' => 'ok', 'paid' => 'ok', 'approved' => 'ok',
        'on_leave' => 'warn', 'late' => 'warn', 'pending' => 'warn', 'unpaid' => 'warn', 'half_day' => 'warn', 'renewed' => 'warn',
        'resigned' => 'muted', 'leave' => 'muted', 'expired' => 'muted', 'terminated' => 'muted',
        'absent' => 'bad', 'rejected' => 'bad',
    ];
    return $map[$status] ?? 'muted';
}

$pageTitle = 'HR Management - Green World Towers';
$pageCss = 'hr.css';
$activePage = 'hr';
require_once 'partials/header.php';
?>
<div id="hr-page" class="dashboard-container">
<?php require_once 'partials/sidebar.php'; ?>

<main class="main-content">
    <header class="page-header">
        <h1><i class="fas fa-id-card-clip"></i> <?php echo htmlspecialchars(t('hr_management', 'HR Management')); ?></h1>
        <div class="user-info">
            <span><?php echo htmlspecialchars(t('welcome', 'Welcome')); ?>, <?php echo htmlspecialchars($_SESSION['username']); ?></span>
        </div>
    </header>

    <div class="content-wrapper hr-wrapper">

        <!-- Stat cards -->
        <div class="hr-stats">
            <div class="hr-stat">
                <div class="hr-stat-icon hr-icon-blue"><i class="fas fa-users"></i></div>
                <div class="hr-stat-body"><span class="hr-stat-value"><?php echo $totalEmployees; ?></span><span class="hr-stat-label"><?php echo htmlspecialchars(t('total_employees', 'Total employees')); ?></span></div>
            </div>
            <div class="hr-stat">
                <div class="hr-stat-icon hr-icon-green"><i class="fas fa-user-check"></i></div>
                <div class="hr-stat-body"><span class="hr-stat-value"><?php echo $presentToday; ?></span><span class="hr-stat-label"><?php echo htmlspecialchars(t('present_today', 'Present today')); ?></span></div>
            </div>
            <div class="hr-stat">
                <div class="hr-stat-icon hr-icon-amber"><i class="fas fa-umbrella-beach"></i></div>
                <div class="hr-stat-body"><span class="hr-stat-value"><?php echo $onLeaveToday; ?></span><span class="hr-stat-label"><?php echo htmlspecialchars(t('on_leave_today', 'On leave today')); ?></span></div>
            </div>
            <div class="hr-stat">
                <div class="hr-stat-icon hr-icon-purple"><i class="fas fa-envelope-open-text"></i></div>
                <div class="hr-stat-body"><span class="hr-stat-value"><?php echo count($pendingLeaves); ?></span><span class="hr-stat-label"><?php echo htmlspecialchars(t('pending_leave_requests', 'Pending leave requests')); ?></span></div>
            </div>
            <div class="hr-stat">
                <div class="hr-stat-icon hr-icon-orange"><i class="fas fa-file-signature"></i></div>
                <div class="hr-stat-body"><span class="hr-stat-value"><?php echo count($expiringContracts); ?></span><span class="hr-stat-label"><?php echo htmlspecialchars(t('contracts_expiring_soon', 'Contracts expiring soon')); ?></span></div>
            </div>
            <div class="hr-stat">
                <div class="hr-stat-icon hr-icon-red"><i class="fas fa-money-check-dollar"></i></div>
                <div class="hr-stat-body"><span class="hr-stat-value"><?php echo $unpaidCount; ?></span><span class="hr-stat-label"><?php echo htmlspecialchars(t('unpaid_payments', 'Unpaid payments')); ?></span></div>
            </div>
        </div>

        <!-- Tabs -->
        <div class="hr-tabs">
            <button class="hr-tab active" data-tab="dashboard"><i class="fas fa-gauge-high"></i> <?php echo htmlspecialchars(t('dashboard', 'Dashboard')); ?></button>
            <button class="hr-tab" data-tab="employees"><i class="fas fa-users"></i> <?php echo htmlspecialchars(t('employees', 'Employees')); ?></button>
            <button class="hr-tab" data-tab="attendance"><i class="fas fa-calendar-check"></i> <?php echo htmlspecialchars(t('attendance', 'Attendance')); ?></button>
            <button class="hr-tab" data-tab="payroll"><i class="fas fa-money-bill-wave"></i> <?php echo htmlspecialchars(t('payroll', 'Payroll')); ?><?php if ($unpaidCount): ?> <span class="hr-badge-count"><?php echo $unpaidCount; ?></span><?php endif; ?></button>
            <button class="hr-tab" data-tab="leaves"><i class="fas fa-umbrella-beach"></i> <?php echo htmlspecialchars(t('leaves', 'Leaves')); ?><?php if (count($pendingLeaves)): ?> <span class="hr-badge-count"><?php echo count($pendingLeaves); ?></span><?php endif; ?></button>
            <button class="hr-tab" data-tab="contracts"><i class="fas fa-file-signature"></i> <?php echo htmlspecialchars(t('contracts', 'Contracts')); ?><?php if (count($expiringContracts)): ?> <span class="hr-badge-count"><?php echo count($expiringContracts); ?></span><?php endif; ?></button>
            <button class="hr-tab" data-tab="reports"><i class="fas fa-file-lines"></i> <?php echo htmlspecialchars(t('reports', 'Reports')); ?></button>
        </div>

        <!-- ═══ DASHBOARD ═══ -->
        <section class="hr-panel active" id="tab-dashboard">
            <div class="hr-grid-2">
                <div class="hr-card">
                    <div class="hr-card-head"><h2><i class="fas fa-chart-pie"></i> <?php echo htmlspecialchars(t('workforce_by_type', 'Workforce by Type')); ?></h2></div>
                    <?php if ($typeChartLabels): ?>
                        <div class="hr-chart-wrap"><canvas id="hrTypeChart"></canvas></div>
                    <?php else: ?>
                        <div class="hr-empty"><i class="fas fa-users"></i> <?php echo htmlspecialchars(t('no_employees_yet_add_first', 'No employees yet — add your first one in the Employees tab.')); ?></div>
                    <?php endif; ?>
                </div>
                <div class="hr-card">
                    <div class="hr-card-head"><h2><i class="fas fa-chart-column"></i> <?php echo htmlspecialchars(t('attendance_last_7_days', 'Attendance, Last 7 Days')); ?></h2><span class="hr-card-note"><?php echo htmlspecialchars(t('worked_days', 'worked days')); ?></span></div>
                    <div class="hr-chart-wrap"><canvas id="hrWeekChart"></canvas></div>
                </div>
            </div>

            <div class="hr-grid-2">
                <div class="hr-card">
                    <div class="hr-card-head"><h2><i class="fas fa-file-signature"></i> <?php echo htmlspecialchars(t('contracts_expiring_30_days', 'Contracts Expiring in 30 Days')); ?></h2></div>
                    <?php if (empty($expiringContracts)): ?>
                        <div class="hr-empty"><i class="fas fa-circle-check"></i> <?php echo htmlspecialchars(t('no_contracts_close_expiring', 'No contracts are close to expiring.')); ?></div>
                    <?php else: ?>
                        <div class="hr-table-wrap">
                            <table class="data-table hr-table">
                                <thead><tr><th><?php echo htmlspecialchars(t('employee', 'Employee')); ?></th><th><?php echo htmlspecialchars(t('contract', 'Contract')); ?></th><th><?php echo htmlspecialchars(t('ends', 'Ends')); ?></th><th><?php echo htmlspecialchars(t('days_left', 'Days Left')); ?></th></tr></thead>
                                <tbody>
                                <?php foreach ($expiringContracts as $c):
                                    $daysLeft = (int)((strtotime((string)$c['end_date']) - strtotime($today)) / 86400); ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($c['full_name']); ?> <span class="hr-dim"><?php echo htmlspecialchars($c['employee_code']); ?></span></td>
                                        <td><?php echo htmlspecialchars($c['contract_title'] ?: 'Employment contract'); ?></td>
                                        <td><?php echo htmlspecialchars((string)$c['end_date']); ?></td>
                                        <td><span class="hr-status hr-status-<?php echo $daysLeft <= 7 ? 'bad' : 'warn'; ?>"><?php echo $daysLeft; ?> days</span></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="hr-card">
                    <div class="hr-card-head"><h2><i class="fas fa-envelope-open-text"></i> <?php echo htmlspecialchars(t('pending_leave_requests_title', 'Pending Leave Requests')); ?></h2></div>
                    <?php if (empty($pendingLeaves)): ?>
                        <div class="hr-empty"><i class="fas fa-circle-check"></i> <?php echo htmlspecialchars(t('nothing_waiting_decision', 'Nothing waiting for a decision.')); ?></div>
                    <?php else: ?>
                        <div class="hr-table-wrap">
                            <table class="data-table hr-table">
                                <thead><tr><th><?php echo htmlspecialchars(t('employee', 'Employee')); ?></th><th><?php echo htmlspecialchars(t('type', 'Type')); ?></th><th><?php echo htmlspecialchars(t('dates', 'Dates')); ?></th><th style="text-align:right;"><?php echo htmlspecialchars(t('decision', 'Decision')); ?></th></tr></thead>
                                <tbody>
                                <?php foreach (array_slice($pendingLeaves, 0, 6) as $l): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($l['full_name']); ?></td>
                                        <td><?php echo $leaveTypes[$l['leave_type']] ?? htmlspecialchars($l['leave_type']); ?></td>
                                        <td><?php echo htmlspecialchars($l['date_from'] . ' → ' . $l['date_to']); ?> <span class="hr-dim">(<?php echo rtrim(rtrim((string)$l['days'], '0'), '.'); ?>d)</span></td>
                                        <td class="hr-actions-cell">
                                            <button class="hr-btn-icon hr-review-leave" data-id="<?php echo (int)$l['id']; ?>" data-decision="approved" title="Approve"><i class="fas fa-check"></i></button>
                                            <button class="hr-btn-icon hr-btn-danger hr-review-leave" data-id="<?php echo (int)$l['id']; ?>" data-decision="rejected" title="Reject"><i class="fas fa-xmark"></i></button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <!-- ═══ EMPLOYEES ═══ -->
        <section class="hr-panel" id="tab-employees">
            <div class="hr-panel-head">
                <h2><i class="fas fa-users"></i> <?php echo htmlspecialchars(t('employees', 'Employees')); ?></h2>
                <button class="btn btn-primary" id="btn-add-employee"><i class="fas fa-user-plus"></i> <?php echo htmlspecialchars(t('add_employee', 'Add Employee')); ?></button>
            </div>

            <form class="hr-filter-bar" id="employee-filters" onsubmit="return false;">
                <input type="text" id="emp-search" placeholder="<?php echo htmlspecialchars(t('search_name_code_job_phone', 'Search name, code, job, phone…')); ?>">
                <select id="emp-filter-type">
                    <option value=""><?php echo htmlspecialchars(t('all_types', 'All types')); ?></option>
                    <?php foreach ($types as $k => $label): ?><option value="<?php echo $k; ?>"><?php echo $label; ?></option><?php endforeach; ?>
                </select>
                <select id="emp-filter-status">
                    <option value=""><?php echo htmlspecialchars(t('all_statuses', 'All statuses')); ?></option>
                    <?php foreach ($empStatuses as $k => $label): ?><option value="<?php echo $k; ?>"><?php echo $label; ?></option><?php endforeach; ?>
                </select>
                <select id="emp-filter-site">
                    <option value=""><?php echo htmlspecialchars(t('all_sites', 'All sites')); ?></option>
                    <option value="0"><?php echo htmlspecialchars(t('office_unassigned', 'Office / Unassigned')); ?></option>
                    <?php foreach ($buildings as $b): ?><option value="<?php echo (int)$b['id']; ?>"><?php echo htmlspecialchars($b['building_name']); ?></option><?php endforeach; ?>
                </select>
            </form>

            <div class="hr-table-wrap">
                <table class="data-table hr-table" id="employees-table">
                    <thead>
                        <tr>
                            <th><?php echo htmlspecialchars(t('code', 'Code')); ?></th><th><?php echo htmlspecialchars(t('name', 'Name')); ?></th><th><?php echo htmlspecialchars(t('job_department', 'Job / Department')); ?></th><th><?php echo htmlspecialchars(t('type', 'Type')); ?></th>
                            <th><?php echo htmlspecialchars(t('project_site', 'Project / Site')); ?></th><th><?php echo htmlspecialchars(t('pay_rate', 'Pay Rate')); ?></th><th><?php echo htmlspecialchars(t('safety', 'Safety')); ?></th><th><?php echo htmlspecialchars(t('status', 'Status')); ?></th><th style="text-align:right;"><?php echo htmlspecialchars(t('actions', 'Actions')); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($employees)): ?>
                        <tr><td colspan="9" class="hr-empty-row"><?php echo htmlspecialchars(t('no_employees_yet_click_add', 'No employees yet. Click "Add Employee" to create the first record.')); ?></td></tr>
                    <?php else: foreach ($employees as $e): ?>
                        <tr data-type="<?php echo $e['employment_type']; ?>" data-status="<?php echo $e['status']; ?>" data-site="<?php echo (int)$e['building_id']; ?>"
                            data-search="<?php echo htmlspecialchars(strtolower($e['full_name'] . ' ' . $e['employee_code'] . ' ' . $e['job_title'] . ' ' . $e['department'] . ' ' . $e['phone'])); ?>">
                            <td class="hr-dim"><?php echo htmlspecialchars($e['employee_code']); ?></td>
                            <td><strong><?php echo htmlspecialchars($e['full_name']); ?></strong><?php if ($e['phone']): ?><br><span class="hr-dim"><?php echo htmlspecialchars($e['phone']); ?></span><?php endif; ?></td>
                            <td><?php echo htmlspecialchars($e['job_title'] ?: '—'); ?><?php if ($e['department']): ?><br><span class="hr-dim"><?php echo htmlspecialchars($e['department']); ?></span><?php endif; ?></td>
                            <td><?php echo $types[$e['employment_type']] ?? htmlspecialchars($e['employment_type']); ?></td>
                            <td><?php echo $e['building_name'] ? htmlspecialchars($e['building_name']) : '<span class="hr-dim">' . htmlspecialchars(t('office_unassigned', 'Office / Unassigned')) . '</span>'; ?></td>
                            <td><?php echo hr_money((float)$e['salary_amount'], (string)$e['currency']); ?> <span class="hr-dim">/ <?php echo $e['salary_period']; ?></span></td>
                            <td><?php echo $e['safety_training_done']
                                ? '<span class="hr-status hr-status-ok" title="' . htmlspecialchars((string)$e['safety_training_date']) . '"><i class="fas fa-helmet-safety"></i> ' . htmlspecialchars(t('done', 'Done')) . '</span>'
                                : '<span class="hr-status hr-status-muted">—</span>'; ?></td>
                            <td><span class="hr-status hr-status-<?php echo hr_badge($e['status']); ?>"><?php echo $empStatuses[$e['status']]; ?></span></td>
                            <td class="hr-actions-cell">
                                <button class="hr-btn-icon hr-view-employee" data-id="<?php echo (int)$e['id']; ?>" title="<?php echo htmlspecialchars(t('profile', 'Profile')); ?>"><i class="fas fa-address-card"></i></button>
                                <button class="hr-btn-icon hr-docs-employee" data-id="<?php echo (int)$e['id']; ?>" data-name="<?php echo htmlspecialchars($e['full_name']); ?>" title="<?php echo htmlspecialchars(t('documents', 'Documents')); ?>"><i class="fas fa-folder-open"></i></button>
                                <button class="hr-btn-icon hr-edit-employee" data-emp="<?php echo htmlspecialchars(json_encode([
                                    'id' => (int)$e['id'], 'full_name' => $e['full_name'], 'phone' => $e['phone'],
                                    'emergency_phone' => $e['emergency_phone'], 'national_id' => $e['national_id'], 'address' => $e['address'],
                                    'job_title' => $e['job_title'], 'department' => $e['department'], 'employment_type' => $e['employment_type'],
                                    'building_id' => $e['building_id'], 'hire_date' => $e['hire_date'], 'salary_amount' => $e['salary_amount'],
                                    'salary_period' => $e['salary_period'], 'currency' => $e['currency'], 'status' => $e['status'],
                                    'safety_training_done' => (int)$e['safety_training_done'], 'safety_training_date' => $e['safety_training_date'],
                                    'notes' => $e['notes'],
                                ], JSON_UNESCAPED_UNICODE), ENT_QUOTES); ?>" title="<?php echo htmlspecialchars(t('edit', 'Edit')); ?>"><i class="fas fa-pen"></i></button>
                                <button class="hr-btn-icon hr-btn-danger hr-delete-employee" data-id="<?php echo (int)$e['id']; ?>" data-name="<?php echo htmlspecialchars($e['full_name']); ?>" title="<?php echo htmlspecialchars(t('remove', 'Remove')); ?>"><i class="fas fa-trash"></i></button>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- ═══ ATTENDANCE ═══ -->
        <section class="hr-panel" id="tab-attendance">
            <div class="hr-panel-head">
                <h2><i class="fas fa-calendar-check"></i> <?php echo htmlspecialchars(t('attendance', 'Attendance')); ?> — <?php echo htmlspecialchars(date('d M Y', strtotime($attDate))); ?></h2>
                <div class="hr-head-actions">
                    <input type="date" id="attendance-date" value="<?php echo $attDate; ?>" max="<?php echo $today; ?>">
                    <button class="btn btn-secondary" id="btn-mark-all"><i class="fas fa-check-double"></i> <?php echo htmlspecialchars(t('mark_all_present', 'Mark All Present')); ?></button>
                </div>
            </div>

            <?php
            $markedCount = 0;
            foreach ($activeEmployees as $e) {
                if (isset($attRows[(int)$e['id']])) {
                    $markedCount++;
                }
            }
            ?>
            <p class="hr-subnote"><?php echo htmlspecialchars(str_replace(['{count}', '{total}'], [$markedCount, count($activeEmployees)], t('active_employees_marked', '{count} of {total} active employees marked for this date. Changes save automatically.'))); ?></p>

            <div class="hr-table-wrap">
                <table class="data-table hr-table" id="attendance-table">
                    <thead>
                        <tr><th><?php echo htmlspecialchars(t('employee', 'Employee')); ?></th><th><?php echo htmlspecialchars(t('status', 'Status')); ?></th><th><?php echo htmlspecialchars(t('check_in', 'Check In')); ?></th><th><?php echo htmlspecialchars(t('check_out', 'Check Out')); ?></th><th><?php echo htmlspecialchars(t('note', 'Note')); ?></th><th></th></tr>
                    </thead>
                    <tbody>
                    <?php if (empty($activeEmployees)): ?>
                        <tr><td colspan="6" class="hr-empty-row"><?php echo htmlspecialchars(t('no_active_employees_to_mark', 'No active employees to mark.')); ?></td></tr>
                    <?php else: foreach ($activeEmployees as $e):
                        $a = $attRows[(int)$e['id']] ?? null; ?>
                        <tr class="hr-att-row" data-employee="<?php echo (int)$e['id']; ?>">
                            <td><strong><?php echo htmlspecialchars($e['full_name']); ?></strong> <span class="hr-dim"><?php echo htmlspecialchars($e['employee_code']); ?></span></td>
                            <td>
                                <div class="hr-att-status-group">
                                    <?php foreach ($attStatuses as $k => $label): ?>
                                        <button type="button" class="hr-att-status<?php echo ($a && $a['status'] === $k) ? ' active st-' . $k : ''; ?>" data-status="<?php echo $k; ?>"><?php echo $label; ?></button>
                                    <?php endforeach; ?>
                                </div>
                            </td>
                            <td><input type="time" class="hr-att-in" value="<?php echo $a && $a['check_in'] ? substr((string)$a['check_in'], 0, 5) : ''; ?>"></td>
                            <td><input type="time" class="hr-att-out" value="<?php echo $a && $a['check_out'] ? substr((string)$a['check_out'], 0, 5) : ''; ?>"></td>
                            <td><input type="text" class="hr-att-note" maxlength="255" placeholder="Optional note" value="<?php echo $a ? htmlspecialchars((string)$a['notes']) : ''; ?>"></td>
                            <td class="hr-att-saved"><?php echo $a ? '<i class="fas fa-circle-check" title="Marked"></i>' : ''; ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- ═══ PAYROLL ═══ -->
        <section class="hr-panel" id="tab-payroll">
            <div class="hr-panel-head">
                <h2><i class="fas fa-money-bill-wave"></i> <?php echo htmlspecialchars(t('payroll', 'Payroll')); ?></h2>
                <div class="hr-head-actions">
                    <select id="payroll-filter-status">
                        <option value=""><?php echo htmlspecialchars(t('all_payments', 'All payments')); ?></option>
                        <option value="unpaid"><?php echo htmlspecialchars(t('unpaid_only', 'Unpaid only')); ?></option>
                        <option value="paid"><?php echo htmlspecialchars(t('paid_only', 'Paid only')); ?></option>
                    </select>
                    <button class="btn btn-primary" id="btn-add-payroll"><i class="fas fa-plus"></i> <?php echo htmlspecialchars(t('add_payment', 'Add Payment')); ?></button>
                </div>
            </div>

            <div class="hr-table-wrap">
                <table class="data-table hr-table" id="payroll-table">
                    <thead>
                        <tr><th><?php echo htmlspecialchars(t('employee', 'Employee')); ?></th><th><?php echo htmlspecialchars(t('period', 'Period')); ?></th><th><?php echo htmlspecialchars(t('basis', 'Basis')); ?></th><th><?php echo htmlspecialchars(t('base', 'Base')); ?></th><th><?php echo htmlspecialchars(t('overtime', 'Overtime')); ?></th><th><?php echo htmlspecialchars(t('bonus', 'Bonus')); ?></th><th><?php echo htmlspecialchars(t('deductions', 'Deductions')); ?></th><th><?php echo htmlspecialchars(t('net', 'Net')); ?></th><th><?php echo htmlspecialchars(t('status', 'Status')); ?></th><th style="text-align:right;"><?php echo htmlspecialchars(t('actions', 'Actions')); ?></th></tr>
                    </thead>
                    <tbody>
                    <?php if (empty($payrollRows)): ?>
                        <tr><td colspan="10" class="hr-empty-row"><?php echo htmlspecialchars(t('no_payment_records_yet', 'No payment records yet.')); ?></td></tr>
                    <?php else: foreach ($payrollRows as $p): $cur = (string)$p['currency']; ?>
                        <tr data-status="<?php echo $p['payment_status']; ?>">
                            <td><strong><?php echo htmlspecialchars($p['full_name']); ?></strong> <span class="hr-dim"><?php echo htmlspecialchars($p['employee_code']); ?></span></td>
                            <td><?php echo htmlspecialchars($p['period_start'] . ' → ' . $p['period_end']); ?><?php if ($p['building_name']): ?><br><span class="hr-dim"><?php echo htmlspecialchars($p['building_name']); ?></span><?php endif; ?></td>
                            <td class="hr-dim"><?php echo htmlspecialchars($p['work_basis'] ?: '—'); ?></td>
                            <td><?php echo hr_money((float)$p['base_amount'], $cur); ?></td>
                            <td><?php echo (float)$p['overtime_amount'] > 0 ? hr_money((float)$p['overtime_amount'], $cur) : '—'; ?></td>
                            <td><?php echo (float)$p['bonus_amount'] > 0 ? hr_money((float)$p['bonus_amount'], $cur) : '—'; ?></td>
                            <td><?php echo (float)$p['deduction_amount'] > 0 ? hr_money((float)$p['deduction_amount'], $cur) : '—'; ?></td>
                            <td><strong><?php echo hr_money((float)$p['net_amount'], $cur); ?></strong></td>
                            <td>
                                <span class="hr-status hr-status-<?php echo hr_badge($p['payment_status']); ?>"><?php echo ucfirst($p['payment_status']); ?></span>
                                <?php if ($p['paid_date']): ?><br><span class="hr-dim"><?php echo htmlspecialchars((string)$p['paid_date']); ?></span><?php endif; ?>
                            </td>
                            <td class="hr-actions-cell">
                                <button class="hr-btn-icon hr-toggle-paid" data-id="<?php echo (int)$p['id']; ?>" data-next="<?php echo $p['payment_status'] === 'paid' ? 'unpaid' : 'paid'; ?>"
                                        title="<?php echo $p['payment_status'] === 'paid' ? 'Mark unpaid' : 'Mark paid'; ?>">
                                    <i class="fas <?php echo $p['payment_status'] === 'paid' ? 'fa-rotate-left' : 'fa-circle-check'; ?>"></i>
                                </button>
                                <?php if ($p['payment_status'] === 'unpaid'): ?>
                                    <button class="hr-btn-icon hr-btn-danger hr-delete-payroll" data-id="<?php echo (int)$p['id']; ?>" title="Delete"><i class="fas fa-trash"></i></button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- ═══ LEAVES ═══ -->
        <section class="hr-panel" id="tab-leaves">
            <div class="hr-panel-head">
                <h2><i class="fas fa-umbrella-beach"></i> <?php echo htmlspecialchars(t('leave_management', 'Leave Management')); ?></h2>
                <button class="btn btn-primary" id="btn-add-leave"><i class="fas fa-plus"></i> <?php echo htmlspecialchars(t('add_leave_request', 'Add Leave Request')); ?></button>
            </div>

            <div class="hr-card hr-card-pad">
                <h3 class="hr-list-title"><i class="fas fa-hourglass-half"></i> <?php echo htmlspecialchars(t('waiting_for_decision', 'Waiting for a Decision')); ?></h3>
                <?php if (empty($pendingLeaves)): ?>
                    <div class="hr-empty"><i class="fas fa-circle-check"></i> <?php echo htmlspecialchars(t('no_pending_requests', 'No pending requests.')); ?></div>
                <?php else: ?>
                    <div class="hr-table-wrap">
                        <table class="data-table hr-table">
                            <thead><tr><th><?php echo htmlspecialchars(t('employee', 'Employee')); ?></th><th><?php echo htmlspecialchars(t('type', 'Type')); ?></th><th><?php echo htmlspecialchars(t('from', 'From')); ?></th><th><?php echo htmlspecialchars(t('to', 'To')); ?></th><th><?php echo htmlspecialchars(t('days', 'Days')); ?></th><th><?php echo htmlspecialchars(t('reason', 'Reason')); ?></th><th style="text-align:right;"><?php echo htmlspecialchars(t('actions', 'Actions')); ?></th></tr></thead>
                            <tbody>
                            <?php foreach ($pendingLeaves as $l): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($l['full_name']); ?></strong> <span class="hr-dim"><?php echo htmlspecialchars($l['employee_code']); ?></span></td>
                                    <td><?php echo $leaveTypes[$l['leave_type']] ?? htmlspecialchars($l['leave_type']); ?></td>
                                    <td><?php echo htmlspecialchars($l['date_from']); ?></td>
                                    <td><?php echo htmlspecialchars($l['date_to']); ?></td>
                                    <td><?php echo rtrim(rtrim((string)$l['days'], '0'), '.'); ?></td>
                                    <td class="hr-notes"><?php echo htmlspecialchars($l['reason'] ?: '—'); ?></td>
                                    <td class="hr-actions-cell">
                                        <button class="hr-btn-icon hr-review-leave" data-id="<?php echo (int)$l['id']; ?>" data-decision="approved" title="Approve"><i class="fas fa-check"></i></button>
                                        <button class="hr-btn-icon hr-btn-danger hr-review-leave" data-id="<?php echo (int)$l['id']; ?>" data-decision="rejected" title="Reject"><i class="fas fa-xmark"></i></button>
                                        <button class="hr-btn-icon hr-btn-danger hr-delete-leave" data-id="<?php echo (int)$l['id']; ?>" title="Delete request"><i class="fas fa-trash"></i></button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <div class="hr-card hr-card-pad">
                <h3 class="hr-list-title"><i class="fas fa-clock-rotate-left"></i> <?php echo htmlspecialchars(t('leave_history', 'Leave History')); ?></h3>
                <?php if (empty($leaveHistory)): ?>
                    <div class="hr-empty"><i class="fas fa-clipboard"></i> <?php echo htmlspecialchars(t('no_reviewed_leaves', 'No reviewed leave requests yet.')); ?></div>
                <?php else: ?>
                    <div class="hr-table-wrap">
                        <table class="data-table hr-table">
                            <thead><tr><th><?php echo htmlspecialchars(t('employee', 'Employee')); ?></th><th><?php echo htmlspecialchars(t('type', 'Type')); ?></th><th><?php echo htmlspecialchars(t('dates', 'Dates')); ?></th><th><?php echo htmlspecialchars(t('days', 'Days')); ?></th><th><?php echo htmlspecialchars(t('status', 'Status')); ?></th><th><?php echo htmlspecialchars(t('reviewed_by', 'Reviewed By')); ?></th></tr></thead>
                            <tbody>
                            <?php foreach (array_slice($leaveHistory, 0, 50) as $l): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($l['full_name']); ?></td>
                                    <td><?php echo $leaveTypes[$l['leave_type']] ?? htmlspecialchars($l['leave_type']); ?></td>
                                    <td><?php echo htmlspecialchars($l['date_from'] . ' → ' . $l['date_to']); ?></td>
                                    <td><?php echo rtrim(rtrim((string)$l['days'], '0'), '.'); ?></td>
                                    <td><span class="hr-status hr-status-<?php echo hr_badge($l['status']); ?>"><?php echo ucfirst($l['status']); ?></span></td>
                                    <td class="hr-dim"><?php echo htmlspecialchars($l['reviewer'] ?: '—'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <!-- ═══ CONTRACTS ═══ -->
        <section class="hr-panel" id="tab-contracts">
            <div class="hr-panel-head">
                <h2><i class="fas fa-file-signature"></i> <?php echo htmlspecialchars(t('contracts', 'Contracts')); ?></h2>
                <button class="btn btn-primary" id="btn-add-contract"><i class="fas fa-plus"></i> <?php echo htmlspecialchars(t('add_contract', 'Add Contract')); ?></button>
            </div>

            <div class="hr-table-wrap">
                <table class="data-table hr-table">
                    <thead>
                        <tr><th><?php echo htmlspecialchars(t('employee', 'Employee')); ?></th><th><?php echo htmlspecialchars(t('contract', 'Contract')); ?></th><th><?php echo htmlspecialchars(t('start', 'Start')); ?></th><th><?php echo htmlspecialchars(t('end', 'End')); ?></th><th><?php echo htmlspecialchars(t('pay', 'Pay')); ?></th><th><?php echo htmlspecialchars(t('status', 'Status')); ?></th><th><?php echo htmlspecialchars(t('file', 'File')); ?></th><th style="text-align:right;"><?php echo htmlspecialchars(t('actions', 'Actions')); ?></th></tr>
                    </thead>
                    <tbody>
                    <?php if (empty($contracts)): ?>
                        <tr><td colspan="8" class="hr-empty-row"><?php echo htmlspecialchars(t('no_contracts_recorded', 'No contracts recorded yet.')); ?></td></tr>
                    <?php else: foreach ($contracts as $c): ?>
                        <tr class="<?php echo $c['expiring'] ? 'hr-row-expiring' : ''; ?>">
                            <td><strong><?php echo htmlspecialchars($c['full_name']); ?></strong> <span class="hr-dim"><?php echo htmlspecialchars($c['employee_code']); ?></span></td>
                            <td><?php echo htmlspecialchars($c['contract_title'] ?: 'Employment contract'); ?></td>
                            <td><?php echo htmlspecialchars((string)$c['start_date']); ?></td>
                            <td><?php echo $c['end_date'] ? htmlspecialchars((string)$c['end_date']) : '<span class="hr-dim">' . htmlspecialchars(t('open_ended', 'Open-ended')) . '</span>'; ?>
                                <?php if ($c['expiring']): ?> <span class="hr-status hr-status-warn"><?php echo htmlspecialchars(t('soon', 'soon')); ?></span><?php endif; ?></td>
                            <td><?php echo hr_money((float)$c['salary_amount'], (string)$c['currency']); ?> <span class="hr-dim">/ <?php echo $c['salary_period']; ?></span></td>
                            <td><span class="hr-status hr-status-<?php echo hr_badge($c['status']); ?>"><?php echo ucfirst($c['status']); ?></span></td>
                            <td><?php echo $c['file_name']
                                ? '<a class="hr-link" href="hr_document.php?source=contract&id=' . (int)$c['id'] . '" target="_blank"><i class="fas fa-file-arrow-down"></i> ' . htmlspecialchars(t('view', 'View')) . '</a>'
                                : '<span class="hr-dim">—</span>'; ?></td>
                            <td class="hr-actions-cell">
                                <button class="hr-btn-icon hr-renew-contract" data-employee="<?php echo (int)$c['employee_id']; ?>" title="<?php echo htmlspecialchars(t('renew_new_contract', 'Renew (new contract)')); ?>"><i class="fas fa-rotate"></i></button>
                                <?php if ($c['status'] === 'active'): ?>
                                    <button class="hr-btn-icon hr-btn-danger hr-terminate-contract" data-id="<?php echo (int)$c['id']; ?>" title="<?php echo htmlspecialchars(t('terminate', 'Terminate')); ?>"><i class="fas fa-ban"></i></button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- ═══ REPORTS ═══ -->
        <section class="hr-panel" id="tab-reports">
            <div class="hr-panel-head">
                <h2><i class="fas fa-file-lines"></i> <?php echo htmlspecialchars(t('reports', 'Reports')); ?></h2>
            </div>

            <form class="hr-filter-bar hr-report-bar" id="report-form" onsubmit="return false;">
                <select id="report-type">
                    <option value="employees"><?php echo htmlspecialchars(t('employee_report', 'Employee report')); ?></option>
                    <option value="attendance"><?php echo htmlspecialchars(t('attendance_report', 'Attendance report')); ?></option>
                    <option value="payroll"><?php echo htmlspecialchars(t('payroll_report', 'Payroll report')); ?></option>
                    <option value="labor_cost"><?php echo htmlspecialchars(t('labor_cost_per_project', 'Labor cost per project')); ?></option>
                </select>
                <input type="date" id="report-from" value="<?php echo date('Y-m-01'); ?>">
                <input type="date" id="report-to" value="<?php echo $today; ?>">
                <select id="report-employee">
                    <option value=""><?php echo htmlspecialchars(t('all_employees', 'All employees')); ?></option>
                    <?php foreach ($employees as $e): ?><option value="<?php echo (int)$e['id']; ?>"><?php echo htmlspecialchars($e['full_name']); ?></option><?php endforeach; ?>
                </select>
                <select id="report-building">
                    <option value=""><?php echo htmlspecialchars(t('all_projects_sites', 'All projects/sites')); ?></option>
                    <?php foreach ($buildings as $b): ?><option value="<?php echo (int)$b['id']; ?>"><?php echo htmlspecialchars($b['building_name']); ?></option><?php endforeach; ?>
                </select>
                <div class="hr-report-actions">
                    <button class="btn btn-primary" id="btn-run-report"><i class="fas fa-play"></i> <?php echo htmlspecialchars(t('run', 'Run')); ?></button>
                    <button class="btn btn-secondary" id="btn-export-report" disabled><i class="fas fa-file-csv"></i> <?php echo htmlspecialchars(t('csv', 'CSV')); ?></button>
                    <button class="btn btn-secondary" id="btn-print-report" disabled><i class="fas fa-print"></i> <?php echo htmlspecialchars(t('print', 'Print')); ?></button>
                </div>
            </form>

            <div class="hr-card hr-card-pad" id="report-result-card">
                <div class="hr-table-wrap" id="report-print-area">
                    <table class="data-table hr-table" id="report-table">
                        <thead><tr><th><?php echo htmlspecialchars(t('report', 'Report')); ?></th></tr></thead>
                        <tbody><tr><td class="hr-empty-row"><?php echo htmlspecialchars(t('choose_report_run', 'Choose a report and press Run.')); ?></td></tr></tbody>
                    </table>
                </div>
            </div>
        </section>
    </div>
</main>
</div>

<!-- ═══ Employee add/edit modal ═══ -->
<div class="hr-modal-overlay" id="employee-modal">
    <div class="hr-modal">
        <div class="hr-modal-head"><h3 id="employee-modal-title"><i class="fas fa-user-plus"></i> <?php echo htmlspecialchars(t('add_employee', 'Add Employee')); ?></h3><button class="hr-modal-close" data-close="employee-modal">&times;</button></div>
        <form id="employee-form" class="hr-form">
            <input type="hidden" name="action" value="save_employee">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf); ?>">
            <input type="hidden" name="employee_id" value="">
            <div class="hr-form-grid">
                <label>Full name *<input type="text" name="full_name" required maxlength="160"></label>
                <label>Phone<input type="text" name="phone" maxlength="40"></label>
                <label>Emergency phone<input type="text" name="emergency_phone" maxlength="40"></label>
                <label>National / ID number<input type="text" name="national_id" maxlength="60"></label>
                <label class="hr-span-2">Address<input type="text" name="address" maxlength="255"></label>
                <label>Job title<input type="text" name="job_title" maxlength="120"></label>
                <label>Department<input type="text" name="department" list="hr-departments" maxlength="120"></label>
                <datalist id="hr-departments">
                    <?php foreach (array_keys($departments) as $d): ?><option value="<?php echo htmlspecialchars($d); ?>"><?php endforeach; ?>
                    <option value="Site"><option value="Office"><option value="Engineering"><option value="Finance"><option value="Safety">
                </datalist>
                <label>Employment type
                    <select name="employment_type" id="employee-type">
                        <?php foreach ($types as $k => $label): ?><option value="<?php echo $k; ?>"><?php echo $label; ?></option><?php endforeach; ?>
                    </select>
                </label>
                <label>Project / site
                    <select name="building_id">
                        <option value="">Office / Unassigned</option>
                        <?php foreach ($buildings as $b): ?><option value="<?php echo (int)$b['id']; ?>"><?php echo htmlspecialchars($b['building_name']); ?></option><?php endforeach; ?>
                    </select>
                </label>
                <label>Hire date<input type="date" name="hire_date" max="<?php echo $today; ?>"></label>
                <label>Status
                    <select name="status">
                        <?php foreach ($empStatuses as $k => $label): ?><option value="<?php echo $k; ?>"><?php echo $label; ?></option><?php endforeach; ?>
                    </select>
                </label>
                <label>Pay rate<input type="number" name="salary_amount" min="0" step="0.01" value="0"></label>
                <label>Per
                    <select name="salary_period" id="employee-period">
                        <option value="monthly">Month</option>
                        <option value="daily">Day</option>
                        <option value="hourly">Hour</option>
                    </select>
                </label>
                <label>Currency
                    <select name="currency">
                        <option value="IQD">IQD</option>
                        <option value="USD">USD</option>
                    </select>
                </label>
                <label class="hr-check-label"><input type="checkbox" name="safety_training_done" value="1" id="employee-safety"> Safety training completed</label>
                <label>Training date<input type="date" name="safety_training_date" max="<?php echo $today; ?>"></label>
                <label class="hr-span-2">Notes<textarea name="notes" rows="2" maxlength="1000"></textarea></label>
            </div>
            <div class="hr-modal-actions">
                <button type="button" class="btn btn-secondary" data-close="employee-modal">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> <span id="employee-save-label"><?php echo htmlspecialchars(t('add_employee', 'Add Employee')); ?></span></button>
            </div>
        </form>
    </div>
</div>

<!-- ═══ Employee profile modal ═══ -->
<div class="hr-modal-overlay" id="profile-modal">
    <div class="hr-modal hr-modal-wide">
        <div class="hr-modal-head"><h3><i class="fas fa-address-card"></i> <?php echo htmlspecialchars(t('employee_profile', 'Employee Profile')); ?></h3><button class="hr-modal-close" data-close="profile-modal">&times;</button></div>
        <div class="hr-modal-body" id="profile-body"><div class="hr-empty"><?php echo htmlspecialchars(t('loading', 'Loading…')); ?></div></div>
    </div>
</div>

<!-- ═══ Documents modal ═══ -->
<div class="hr-modal-overlay" id="docs-modal">
    <div class="hr-modal">
        <div class="hr-modal-head"><h3><i class="fas fa-folder-open"></i> <?php echo htmlspecialchars(t('documents', 'Documents')); ?> — <span id="docs-employee-name"></span></h3><button class="hr-modal-close" data-close="docs-modal">&times;</button></div>
        <div class="hr-modal-body">
            <div id="docs-list"><div class="hr-empty"><?php echo htmlspecialchars(t('loading', 'Loading…')); ?></div></div>
            <form id="document-form" class="hr-form hr-doc-form" enctype="multipart/form-data">
                <input type="hidden" name="action" value="upload_document">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf); ?>">
                <input type="hidden" name="employee_id" value="">
                <div class="hr-form-grid">
                    <label>Document name *<input type="text" name="doc_name" required maxlength="160" placeholder="e.g. National ID"></label>
                    <label>Type
                        <select name="doc_type">
                            <option value="id">ID document</option>
                            <option value="contract">Contract</option>
                            <option value="certificate">Certificate / Training</option>
                            <option value="other">Other</option>
                        </select>
                    </label>
                    <label class="hr-span-2">File (PDF, JPG, PNG — max 10MB) *<input type="file" name="document_file" accept=".pdf,.jpg,.jpeg,.png" required></label>
                </div>
                <div class="hr-modal-actions">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-upload"></i> Upload</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ═══ Payroll modal ═══ -->
<div class="hr-modal-overlay" id="payroll-modal">
    <div class="hr-modal">
        <div class="hr-modal-head"><h3><i class="fas fa-money-bill-wave"></i> <?php echo htmlspecialchars(t('add_payment_record', 'Add Payment Record')); ?></h3><button class="hr-modal-close" data-close="payroll-modal">&times;</button></div>
        <form id="payroll-form" class="hr-form">
            <input type="hidden" name="action" value="save_payroll">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf); ?>">
            <input type="hidden" name="work_basis" id="payroll-basis-field" value="">
            <div class="hr-form-grid">
                <label class="hr-span-2">Employee *
                    <select name="employee_id" id="payroll-employee" required>
                        <option value="">Select employee…</option>
                        <?php foreach ($employees as $e): ?>
                            <option value="<?php echo (int)$e['id']; ?>" data-period="<?php echo $e['salary_period']; ?>" data-currency="<?php echo $e['currency']; ?>">
                                <?php echo htmlspecialchars($e['full_name'] . ' (' . $e['employee_code'] . ') — ' . ($types[$e['employment_type']] ?? '')); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Period start *<input type="date" name="period_start" id="payroll-start" value="<?php echo date('Y-m-01'); ?>" required></label>
                <label>Period end *<input type="date" name="period_end" id="payroll-end" value="<?php echo date('Y-m-t'); ?>" required></label>
                <div class="hr-span-2 hr-suggest-row">
                    <button type="button" class="btn btn-secondary" id="btn-payroll-suggest"><i class="fas fa-wand-magic-sparkles"></i> <?php echo htmlspecialchars(t('calculate_from_attendance', 'Calculate from attendance')); ?></button>
                    <span class="hr-dim" id="payroll-suggest-note"></span>
                </div>
                <label>Base amount *<input type="number" name="base_amount" id="payroll-base" min="0" step="0.01" value="0" required></label>
                <label>Overtime<input type="number" name="overtime_amount" min="0" step="0.01" value="0"></label>
                <label>Bonus<input type="number" name="bonus_amount" min="0" step="0.01" value="0"></label>
                <label>Deductions<input type="number" name="deduction_amount" min="0" step="0.01" value="0"></label>
                <label class="hr-span-2">Notes<input type="text" name="notes" maxlength="255"></label>
            </div>
            <p class="hr-net-preview"><?php echo htmlspecialchars(t('net_pay', 'Net pay')); ?>: <strong id="payroll-net-preview">—</strong></p>
            <div class="hr-modal-actions">
                <button type="button" class="btn btn-secondary" data-close="payroll-modal"><?php echo htmlspecialchars(t('cancel', 'Cancel')); ?></button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> <?php echo htmlspecialchars(t('save_payment', 'Save Payment')); ?></button>
            </div>
        </form>
    </div>
</div>

<!-- ═══ Leave modal ═══ -->
<div class="hr-modal-overlay" id="leave-modal">
    <div class="hr-modal">
        <div class="hr-modal-head"><h3><i class="fas fa-umbrella-beach"></i> <?php echo htmlspecialchars(t('add_leave_request', 'Add Leave Request')); ?></h3><button class="hr-modal-close" data-close="leave-modal">&times;</button></div>
        <form id="leave-form" class="hr-form">
            <input type="hidden" name="action" value="save_leave">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf); ?>">
            <div class="hr-form-grid">
                <label class="hr-span-2">Employee *
                    <select name="employee_id" required>
                        <option value="">Select employee…</option>
                        <?php foreach ($employees as $e): ?><option value="<?php echo (int)$e['id']; ?>"><?php echo htmlspecialchars($e['full_name'] . ' (' . $e['employee_code'] . ')'); ?></option><?php endforeach; ?>
                    </select>
                </label>
                <label>Leave type
                    <select name="leave_type">
                        <?php foreach ($leaveTypes as $k => $label): ?><option value="<?php echo $k; ?>"><?php echo $label; ?></option><?php endforeach; ?>
                    </select>
                </label>
                <label>&nbsp;<span class="hr-dim" id="leave-days-note">&nbsp;</span></label>
                <label>From *<input type="date" name="date_from" id="leave-from" required></label>
                <label>To *<input type="date" name="date_to" id="leave-to" required></label>
                <label class="hr-span-2">Reason<input type="text" name="reason" maxlength="255"></label>
            </div>
            <div class="hr-modal-actions">
                <button type="button" class="btn btn-secondary" data-close="leave-modal"><?php echo htmlspecialchars(t('cancel', 'Cancel')); ?></button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> <?php echo htmlspecialchars(t('save_request', 'Save Request')); ?></button>
            </div>
        </form>
    </div>
</div>

<!-- ═══ Contract modal ═══ -->
<div class="hr-modal-overlay" id="contract-modal">
    <div class="hr-modal">
        <div class="hr-modal-head"><h3><i class="fas fa-file-signature"></i> <?php echo htmlspecialchars(t('add_contract', 'Add Contract')); ?></h3><button class="hr-modal-close" data-close="contract-modal">&times;</button></div>
        <form id="contract-form" class="hr-form" enctype="multipart/form-data">
            <input type="hidden" name="action" value="save_contract">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf); ?>">
            <div class="hr-form-grid">
                <label class="hr-span-2">Employee *
                    <select name="employee_id" id="contract-employee" required>
                        <option value="">Select employee…</option>
                        <?php foreach ($employees as $e): ?><option value="<?php echo (int)$e['id']; ?>"><?php echo htmlspecialchars($e['full_name'] . ' (' . $e['employee_code'] . ')'); ?></option><?php endforeach; ?>
                    </select>
                </label>
                <label class="hr-span-2">Contract title<input type="text" name="contract_title" maxlength="160" placeholder="e.g. 2026 annual contract"></label>
                <label>Start date *<input type="date" name="start_date" required value="<?php echo $today; ?>"></label>
                <label>End date <span class="hr-dim">(empty = open-ended)</span><input type="date" name="end_date"></label>
                <label>Pay rate<input type="number" name="salary_amount" min="0" step="0.01" value="0"></label>
                <label>Per
                    <select name="salary_period">
                        <option value="monthly">Month</option>
                        <option value="daily">Day</option>
                        <option value="hourly">Hour</option>
                    </select>
                </label>
                <label>Currency
                    <select name="currency">
                        <option value="IQD">IQD</option>
                        <option value="USD">USD</option>
                    </select>
                </label>
                <label>Contract file <span class="hr-dim">(optional)</span><input type="file" name="contract_file" accept=".pdf,.jpg,.jpeg,.png"></label>
                <label class="hr-span-2">Notes<input type="text" name="notes" maxlength="255"></label>
            </div>
            <p class="hr-subnote"><?php echo htmlspecialchars(t('contract_save_note', 'Saving a new contract marks the employee\'s current active contract as "renewed" and updates their pay rate.')); ?></p>
            <div class="hr-modal-actions">
                <button type="button" class="btn btn-secondary" data-close="contract-modal"><?php echo htmlspecialchars(t('cancel', 'Cancel')); ?></button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> <?php echo htmlspecialchars(t('save_contract', 'Save Contract')); ?></button>
            </div>
        </form>
    </div>
</div>

<div id="hr-toast"></div>

<script>
const HR_CSRF = <?php echo json_encode($csrf); ?>;
const HR_ATT_DATE = <?php echo json_encode($attDate); ?>;
const hrTypeData = { labels: <?php echo json_encode($typeChartLabels, JSON_UNESCAPED_UNICODE); ?>, values: <?php echo json_encode($typeChartValues); ?> };
const hrWeekData = { labels: <?php echo json_encode($weekLabels); ?>, values: <?php echo json_encode($weekValues); ?> };

// ── Toast ───────────────────────────────────────────────────────────────────
let hrToastTimer = null;
function hrToast(message, kind) {
    const el = document.getElementById('hr-toast');
    el.textContent = message;
    el.className = 'show ' + (kind === 'success' ? 'ok' : 'err');
    clearTimeout(hrToastTimer);
    hrToastTimer = setTimeout(() => { el.className = ''; }, 3200);
}

// ── Tabs (persist across reloads via hash) ──────────────────────────────────
function hrActivateTab(name) {
    const btn = document.querySelector('.hr-tab[data-tab="' + name + '"]');
    if (!btn) return;
    document.querySelectorAll('.hr-tab').forEach(b => b.classList.toggle('active', b.dataset.tab === name));
    document.querySelectorAll('.hr-panel').forEach(p => p.classList.toggle('active', p.id === 'tab-' + name));
}
document.querySelectorAll('.hr-tab').forEach(btn => {
    btn.addEventListener('click', () => { hrActivateTab(btn.dataset.tab); history.replaceState(null, '', '#' + btn.dataset.tab); });
});
if (location.hash) hrActivateTab(location.hash.slice(1));

// ── Modals ──────────────────────────────────────────────────────────────────
function hrOpenModal(id) { document.getElementById(id).classList.add('open'); }
function hrCloseModal(id) { document.getElementById(id).classList.remove('open'); }
document.querySelectorAll('[data-close]').forEach(b => b.addEventListener('click', () => hrCloseModal(b.dataset.close)));
document.querySelectorAll('.hr-modal-overlay').forEach(ov => ov.addEventListener('click', e => { if (e.target === ov) ov.classList.remove('open'); }));

// ── AJAX helper: mutations reload so every stat/tab picks up the change ────
function hrPost(formData, onOk) {
    fetch('hr_ajax.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(d => {
            hrToast(d.message || (d.success ? 'Saved.' : 'Something went wrong.'), d.success ? 'success' : 'error');
            if (d.success && onOk) onOk();
        })
        .catch(() => hrToast('Network error. Please try again.', 'error'));
}
function hrReload(tab) { location.hash = tab; location.reload(); }

['employee-form', 'document-form', 'payroll-form', 'leave-form', 'contract-form'].forEach(id => {
    const form = document.getElementById(id);
    form.addEventListener('submit', function (e) {
        e.preventDefault();
        const btn = form.querySelector('button[type="submit"]');
        if (btn) btn.disabled = true;
        const tab = { 'employee-form': 'employees', 'document-form': 'employees', 'payroll-form': 'payroll', 'leave-form': 'leaves', 'contract-form': 'contracts' }[id];
        hrPost(new FormData(form), () => hrReload(tab));
        setTimeout(() => { if (btn) btn.disabled = false; }, 1500);
    });
});

// ── Dashboard charts ────────────────────────────────────────────────────────
if (hrTypeData.labels.length && document.getElementById('hrTypeChart')) {
    new Chart(document.getElementById('hrTypeChart'), {
        type: 'doughnut',
        data: {
            labels: hrTypeData.labels,
            datasets: [{
                data: hrTypeData.values,
                backgroundColor: ['#3b82f6', '#8b5cf6', '#f59e0b', '#10b981', '#ef4444', '#06b6d4', '#f472b6'],
                borderColor: 'rgba(15, 23, 42, 0.9)',
                borderWidth: 2
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: false, cutout: '58%',
            plugins: { legend: { position: 'right', labels: { color: '#cbd5e1', boxWidth: 14 } } }
        }
    });
}
if (document.getElementById('hrWeekChart')) {
    new Chart(document.getElementById('hrWeekChart'), {
        type: 'bar',
        data: {
            labels: hrWeekData.labels,
            datasets: [{ data: hrWeekData.values, backgroundColor: 'rgba(59, 130, 246, 0.65)', borderColor: 'rgba(96, 165, 250, 1)', borderWidth: 1.5, borderRadius: 6, maxBarThickness: 40 }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                x: { ticks: { color: '#cbd5e1' }, grid: { display: false } },
                y: { beginAtZero: true, ticks: { color: '#94a3b8', precision: 0 }, grid: { color: 'rgba(148, 163, 184, 0.12)' } }
            }
        }
    });
}

// ── Employees: filters ──────────────────────────────────────────────────────
function hrFilterEmployees() {
    const q = document.getElementById('emp-search').value.trim().toLowerCase();
    const type = document.getElementById('emp-filter-type').value;
    const status = document.getElementById('emp-filter-status').value;
    const site = document.getElementById('emp-filter-site').value;
    document.querySelectorAll('#employees-table tbody tr[data-search]').forEach(tr => {
        const show = (!q || tr.dataset.search.includes(q))
            && (!type || tr.dataset.type === type)
            && (!status || tr.dataset.status === status)
            && (site === '' || tr.dataset.site === site);
        tr.style.display = show ? '' : 'none';
    });
}
['emp-search', 'emp-filter-type', 'emp-filter-status', 'emp-filter-site'].forEach(id => {
    const el = document.getElementById(id);
    el.addEventListener('input', hrFilterEmployees);
    el.addEventListener('change', hrFilterEmployees);
});

// ── Employees: add / edit / delete ─────────────────────────────────────────
const employeeForm = document.getElementById('employee-form');
document.getElementById('btn-add-employee').addEventListener('click', () => {
    employeeForm.reset();
    employeeForm.querySelector('[name="employee_id"]').value = '';
    document.getElementById('employee-modal-title').innerHTML = '<i class="fas fa-user-plus"></i> Add Employee';
    document.getElementById('employee-save-label').textContent = 'Add Employee';
    hrOpenModal('employee-modal');
});

// Daily/hourly/site-worker types default to a matching pay period.
document.getElementById('employee-type').addEventListener('change', function () {
    const period = document.getElementById('employee-period');
    if (this.value === 'daily' || this.value === 'site_worker') period.value = 'daily';
    else if (this.value === 'hourly') period.value = 'hourly';
    else period.value = 'monthly';
});

document.addEventListener('click', function (e) {
    const editBtn = e.target.closest('.hr-edit-employee');
    if (editBtn) {
        const emp = JSON.parse(editBtn.dataset.emp);
        employeeForm.reset();
        for (const [key, value] of Object.entries(emp)) {
            const field = employeeForm.querySelector('[name="' + (key === 'id' ? 'employee_id' : key) + '"]');
            if (!field) continue;
            if (field.type === 'checkbox') field.checked = !!Number(value);
            else field.value = value === null ? '' : value;
        }
        document.getElementById('employee-modal-title').innerHTML = '<i class="fas fa-pen"></i> Edit Employee';
        document.getElementById('employee-save-label').textContent = 'Save Changes';
        hrOpenModal('employee-modal');
        return;
    }

    const delBtn = e.target.closest('.hr-delete-employee');
    if (delBtn) {
        if (!confirm('Remove "' + delBtn.dataset.name + '"? Their attendance, payroll and contract history is kept.')) return;
        const fd = new FormData();
        fd.append('action', 'delete_employee');
        fd.append('csrf_token', HR_CSRF);
        fd.append('employee_id', delBtn.dataset.id);
        hrPost(fd, () => hrReload('employees'));
    }
});

// ── Employee profile ────────────────────────────────────────────────────────
function esc(v) { const d = document.createElement('div'); d.textContent = v == null ? '' : String(v); return d.innerHTML; }

document.addEventListener('click', function (e) {
    const btn = e.target.closest('.hr-view-employee');
    if (!btn) return;
    hrOpenModal('profile-modal');
    const body = document.getElementById('profile-body');
    body.innerHTML = '<div class="hr-empty">' + (window.appTranslations && window.appTranslations.loading ? window.appTranslations.loading : 'Loading…') + '</div>';
    window.translateStaticUi(body);
    fetch('hr_ajax.php?action=get_employee&employee_id=' + encodeURIComponent(btn.dataset.id))
        .then(r => r.json())
        .then(d => {
            if (!d.success) { body.innerHTML = '<div class="hr-empty">' + esc(d.message) + '</div>'; return; }
            const emp = d.employee;
            const att = d.attendance_month;
            const money = (v, c) => c === 'USD' ? '$' + Number(v).toLocaleString() : Number(v).toLocaleString() + ' IQD';
            let html = '<div class="hr-profile-grid">';
            html += '<div><span class="hr-profile-label">Code</span>' + esc(emp.employee_code) + '</div>';
            html += '<div><span class="hr-profile-label">Name</span><strong>' + esc(emp.full_name) + '</strong></div>';
            html += '<div><span class="hr-profile-label">Job</span>' + esc(emp.job_title || '—') + '</div>';
            html += '<div><span class="hr-profile-label">Department</span>' + esc(emp.department || '—') + '</div>';
            html += '<div><span class="hr-profile-label">Phone</span>' + esc(emp.phone || '—') + '</div>';
            html += '<div><span class="hr-profile-label">Emergency</span>' + esc(emp.emergency_phone || '—') + '</div>';
            html += '<div><span class="hr-profile-label">National ID</span>' + esc(emp.national_id || '—') + '</div>';
            html += '<div><span class="hr-profile-label">Hired</span>' + esc(emp.hire_date || '—') + '</div>';
            html += '<div><span class="hr-profile-label">Current site</span>' + esc(d.building_name || 'Office / Unassigned') + '</div>';
            html += '<div><span class="hr-profile-label">Pay</span>' + money(emp.salary_amount, emp.currency) + ' / ' + esc(emp.salary_period) + '</div>';
            html += '<div><span class="hr-profile-label">Safety training</span>' + (Number(emp.safety_training_done) ? 'Completed ' + esc(emp.safety_training_date || '') : 'Not recorded') + '</div>';
            html += '<div><span class="hr-profile-label">Address</span>' + esc(emp.address || '—') + '</div>';
            html += '</div>';
            html += '<h4 class="hr-profile-h">This Month\'s Attendance</h4>';
            html += '<div class="hr-profile-chips">'
                + '<span class="hr-status hr-status-ok">' + att.present + ' present</span>'
                + '<span class="hr-status hr-status-warn">' + att.late + ' late</span>'
                + '<span class="hr-status hr-status-warn">' + att.half_day + ' half-day</span>'
                + '<span class="hr-status hr-status-bad">' + att.absent + ' absent</span>'
                + '<span class="hr-status hr-status-muted">' + att.leave + ' leave</span></div>';
            if (d.assignments.length) {
                html += '<h4 class="hr-profile-h">Site Assignment History</h4><ul class="hr-profile-list">';
                d.assignments.forEach(a => {
                    html += '<li><strong>' + esc(a.building_name) + '</strong> <span class="hr-dim">' + esc(a.assigned_from) + ' → ' + esc(a.assigned_to || 'now') + '</span></li>';
                });
                html += '</ul>';
            }
            if (d.payroll.length) {
                html += '<h4 class="hr-profile-h">Recent Payments</h4><ul class="hr-profile-list">';
                d.payroll.forEach(p => {
                    html += '<li>' + esc(p.period_start) + ' → ' + esc(p.period_end) + ': <strong>' + money(p.net_amount, p.currency) + '</strong> <span class="hr-status hr-status-' + (p.payment_status === 'paid' ? 'ok' : 'warn') + '">' + esc(p.payment_status) + '</span></li>';
                });
                html += '</ul>';
            }
            if (d.documents.length) {
                html += '<h4 class="hr-profile-h">Documents</h4><ul class="hr-profile-list">';
                d.documents.forEach(doc => {
                    html += '<li><a class="hr-link" href="hr_document.php?id=' + doc.id + '" target="_blank"><i class="fas fa-file-arrow-down"></i> ' + esc(doc.doc_name) + '</a> <span class="hr-dim">(' + esc(doc.doc_type) + ')</span></li>';
                });
                html += '</ul>';
            }
            if (emp.notes) html += '<h4 class="hr-profile-h">Notes</h4><p class="hr-dim">' + esc(emp.notes) + '</p>';
            body.innerHTML = html;
        })
        .catch(() => { body.innerHTML = '<div class="hr-empty">Failed to load profile.</div>'; });
});

// ── Documents modal ─────────────────────────────────────────────────────────
function hrLoadDocs(employeeId) {
    const list = document.getElementById('docs-list');
    list.innerHTML = '<div class="hr-empty">' + (window.appTranslations && window.appTranslations.loading ? window.appTranslations.loading : 'Loading…') + '</div>';
    window.translateStaticUi(list);
    fetch('hr_ajax.php?action=get_employee&employee_id=' + encodeURIComponent(employeeId))
        .then(r => r.json())
        .then(d => {
            if (!d.success) { list.innerHTML = '<div class="hr-empty">' + esc(d.message) + '</div>'; return; }
            if (!d.documents.length) { list.innerHTML = '<div class="hr-empty"><i class="fas fa-folder-open"></i> No documents stored yet.</div>'; return; }
            let html = '<ul class="hr-doc-list">';
            d.documents.forEach(doc => {
                html += '<li><a class="hr-link" href="hr_document.php?id=' + doc.id + '" target="_blank"><i class="fas fa-file-arrow-down"></i> ' + esc(doc.doc_name) + '</a>'
                    + '<span class="hr-dim">' + esc(doc.doc_type) + '</span>'
                    + '<button type="button" class="hr-btn-icon hr-btn-danger hr-delete-doc" data-id="' + doc.id + '" title="Delete"><i class="fas fa-trash"></i></button></li>';
            });
            list.innerHTML = html + '</ul>';
        });
}
document.addEventListener('click', function (e) {
    const btn = e.target.closest('.hr-docs-employee');
    if (btn) {
        document.getElementById('docs-employee-name').textContent = btn.dataset.name;
        document.getElementById('document-form').querySelector('[name="employee_id"]').value = btn.dataset.id;
        hrOpenModal('docs-modal');
        hrLoadDocs(btn.dataset.id);
        return;
    }
    const del = e.target.closest('.hr-delete-doc');
    if (del) {
        if (!confirm('Delete this document?')) return;
        const fd = new FormData();
        fd.append('action', 'delete_document');
        fd.append('csrf_token', HR_CSRF);
        fd.append('document_id', del.dataset.id);
        hrPost(fd, () => hrLoadDocs(document.getElementById('document-form').querySelector('[name="employee_id"]').value));
    }
});

// ── Attendance ──────────────────────────────────────────────────────────────
document.getElementById('attendance-date').addEventListener('change', function () {
    if (this.value) location.href = 'hr.php?att_date=' + encodeURIComponent(this.value) + '#attendance';
});
document.getElementById('btn-mark-all').addEventListener('click', function () {
    const fd = new FormData();
    fd.append('action', 'mark_all_present');
    fd.append('csrf_token', HR_CSRF);
    fd.append('att_date', HR_ATT_DATE);
    hrPost(fd, () => hrReload('attendance'));
});

function hrSaveAttendance(row, status) {
    const fd = new FormData();
    fd.append('action', 'mark_attendance');
    fd.append('csrf_token', HR_CSRF);
    fd.append('employee_id', row.dataset.employee);
    fd.append('att_date', HR_ATT_DATE);
    fd.append('status', status);
    fd.append('check_in', row.querySelector('.hr-att-in').value);
    fd.append('check_out', row.querySelector('.hr-att-out').value);
    fd.append('notes', row.querySelector('.hr-att-note').value);
    fetch('hr_ajax.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            if (!d.success) { hrToast(d.message, 'error'); return; }
            row.querySelectorAll('.hr-att-status').forEach(b => {
                b.className = 'hr-att-status' + (b.dataset.status === status ? ' active st-' + status : '');
            });
            const saved = row.querySelector('.hr-att-saved');
            saved.innerHTML = '<i class="fas fa-circle-check" title="' + (window.appTranslations && window.appTranslations.saved ? window.appTranslations.saved : 'Saved') + '"></i>';
            saved.classList.add('flash');
            setTimeout(() => saved.classList.remove('flash'), 900);
        })
        .catch(() => hrToast('Network error. Please try again.', 'error'));
}

document.addEventListener('click', function (e) {
    const btn = e.target.closest('.hr-att-status');
    if (!btn) return;
    hrSaveAttendance(btn.closest('.hr-att-row'), btn.dataset.status);
});
document.querySelectorAll('.hr-att-in, .hr-att-out, .hr-att-note').forEach(input => {
    input.addEventListener('change', function () {
        const row = this.closest('.hr-att-row');
        const active = row.querySelector('.hr-att-status.active');
        if (!active) { hrToast('Pick a status first — times and notes save with it.', 'error'); return; }
        hrSaveAttendance(row, active.dataset.status);
    });
});

// ── Payroll ─────────────────────────────────────────────────────────────────
document.getElementById('btn-add-payroll').addEventListener('click', () => {
    document.getElementById('payroll-form').reset();
    document.getElementById('payroll-basis-field').value = '';
    document.getElementById('payroll-suggest-note').textContent = '';
    hrUpdateNetPreview();
    hrOpenModal('payroll-modal');
});

document.getElementById('payroll-filter-status').addEventListener('change', function () {
    document.querySelectorAll('#payroll-table tbody tr[data-status]').forEach(tr => {
        tr.style.display = (!this.value || tr.dataset.status === this.value) ? '' : 'none';
    });
});

function hrUpdateNetPreview() {
    const form = document.getElementById('payroll-form');
    const val = name => parseFloat(form.querySelector('[name="' + name + '"]').value) || 0;
    const net = val('base_amount') + val('overtime_amount') + val('bonus_amount') - val('deduction_amount');
    const sel = document.getElementById('payroll-employee');
    const cur = sel.selectedOptions[0] ? (sel.selectedOptions[0].dataset.currency || 'IQD') : 'IQD';
    document.getElementById('payroll-net-preview').textContent =
        (cur === 'USD' ? '$' : '') + net.toLocaleString(undefined, { maximumFractionDigits: 2 }) + (cur === 'USD' ? '' : ' IQD');
}
document.querySelectorAll('#payroll-form input[type="number"]').forEach(i => i.addEventListener('input', hrUpdateNetPreview));
document.getElementById('payroll-employee').addEventListener('change', hrUpdateNetPreview);

document.getElementById('btn-payroll-suggest').addEventListener('click', function () {
    const employeeId = document.getElementById('payroll-employee').value;
    const from = document.getElementById('payroll-start').value;
    const to = document.getElementById('payroll-end').value;
    if (!employeeId) { hrToast('Pick an employee first.', 'error'); return; }
    fetch('hr_ajax.php?action=payroll_prefill&employee_id=' + encodeURIComponent(employeeId) + '&period_start=' + encodeURIComponent(from) + '&period_end=' + encodeURIComponent(to))
        .then(r => r.json())
        .then(d => {
            if (!d.success) { hrToast(d.message, 'error'); return; }
            document.getElementById('payroll-base').value = d.amount;
            document.getElementById('payroll-basis-field').value = d.basis;
            document.getElementById('payroll-suggest-note').textContent = d.basis + ' at their ' + d.salary_period + ' rate';
            hrUpdateNetPreview();
        })
        .catch(() => hrToast('Could not calculate. Please try again.', 'error'));
});

document.addEventListener('click', function (e) {
    const toggle = e.target.closest('.hr-toggle-paid');
    if (toggle) {
        const fd = new FormData();
        fd.append('action', 'set_payroll_status');
        fd.append('csrf_token', HR_CSRF);
        fd.append('payroll_id', toggle.dataset.id);
        fd.append('status', toggle.dataset.next);
        hrPost(fd, () => hrReload('payroll'));
        return;
    }
    const del = e.target.closest('.hr-delete-payroll');
    if (del) {
        if (!confirm('Delete this unpaid payment record?')) return;
        const fd = new FormData();
        fd.append('action', 'delete_payroll');
        fd.append('csrf_token', HR_CSRF);
        fd.append('payroll_id', del.dataset.id);
        hrPost(fd, () => hrReload('payroll'));
    }
});

// ── Leaves ──────────────────────────────────────────────────────────────────
document.getElementById('btn-add-leave').addEventListener('click', () => {
    document.getElementById('leave-form').reset();
    document.getElementById('leave-days-note').innerHTML = '&nbsp;';
    hrOpenModal('leave-modal');
});
function hrLeaveDays() {
    const from = document.getElementById('leave-from').value;
    const to = document.getElementById('leave-to').value;
    const note = document.getElementById('leave-days-note');
    if (from && to && to >= from) {
        const days = Math.round((new Date(to) - new Date(from)) / 86400000) + 1;
        note.textContent = days + ' day(s)';
    } else { note.innerHTML = '&nbsp;'; }
}
document.getElementById('leave-from').addEventListener('change', hrLeaveDays);
document.getElementById('leave-to').addEventListener('change', hrLeaveDays);

document.addEventListener('click', function (e) {
    const review = e.target.closest('.hr-review-leave');
    if (review) {
        const fd = new FormData();
        fd.append('action', 'review_leave');
        fd.append('csrf_token', HR_CSRF);
        fd.append('leave_id', review.dataset.id);
        fd.append('decision', review.dataset.decision);
        hrPost(fd, () => hrReload('leaves'));
        return;
    }
    const del = e.target.closest('.hr-delete-leave');
    if (del) {
        if (!confirm('Delete this pending leave request?')) return;
        const fd = new FormData();
        fd.append('action', 'delete_leave');
        fd.append('csrf_token', HR_CSRF);
        fd.append('leave_id', del.dataset.id);
        hrPost(fd, () => hrReload('leaves'));
    }
});

// ── Contracts ───────────────────────────────────────────────────────────────
document.getElementById('btn-add-contract').addEventListener('click', () => {
    document.getElementById('contract-form').reset();
    hrOpenModal('contract-modal');
});
document.addEventListener('click', function (e) {
    const renew = e.target.closest('.hr-renew-contract');
    if (renew) {
        document.getElementById('contract-form').reset();
        document.getElementById('contract-employee').value = renew.dataset.employee;
        hrOpenModal('contract-modal');
        return;
    }
    const term = e.target.closest('.hr-terminate-contract');
    if (term) {
        if (!confirm('Terminate this contract?')) return;
        const fd = new FormData();
        fd.append('action', 'set_contract_status');
        fd.append('csrf_token', HR_CSRF);
        fd.append('contract_id', term.dataset.id);
        fd.append('status', 'terminated');
        hrPost(fd, () => hrReload('contracts'));
    }
});

// ── Reports ─────────────────────────────────────────────────────────────────
let hrReportData = null;
document.getElementById('btn-run-report').addEventListener('click', function () {
    const params = new URLSearchParams({
        action: 'report',
        type: document.getElementById('report-type').value,
        date_from: document.getElementById('report-from').value,
        date_to: document.getElementById('report-to').value,
        employee_id: document.getElementById('report-employee').value,
        building_id: document.getElementById('report-building').value
    });
    const table = document.getElementById('report-table');
    table.querySelector('tbody').innerHTML = '<tr><td class="hr-empty-row">' + (window.appTranslations && window.appTranslations.loading ? window.appTranslations.loading : 'Loading…') + '</td></tr>';
    window.translateStaticUi(table);
    fetch('hr_ajax.php?' + params.toString())
        .then(r => r.json())
        .then(d => {
            if (!d.success) { hrToast(d.message, 'error'); return; }
            hrReportData = d;
            table.querySelector('thead').innerHTML = '<tr>' + d.columns.map(c => '<th>' + esc(c) + '</th>').join('') + '</tr>';
            table.querySelector('tbody').innerHTML = d.rows.length
                ? d.rows.map(row => '<tr>' + row.map(cell => '<td>' + esc(cell) + '</td>').join('') + '</tr>').join('')
                : '<tr><td colspan="' + d.columns.length + '" class="hr-empty-row">No data for these filters.</td></tr>';
            document.getElementById('btn-export-report').disabled = !d.rows.length;
            document.getElementById('btn-print-report').disabled = !d.rows.length;
        })
        .catch(() => hrToast('Failed to load the report.', 'error'));
});

document.getElementById('btn-export-report').addEventListener('click', function () {
    if (!hrReportData) return;
    const escCsv = v => '"' + String(v).replace(/"/g, '""') + '"';
    const lines = [hrReportData.columns.map(escCsv).join(',')];
    hrReportData.rows.forEach(row => lines.push(row.map(escCsv).join(',')));
    const blob = new Blob(['﻿' + lines.join('\r\n')], { type: 'text/csv;charset=utf-8' });
    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = 'hr_' + document.getElementById('report-type').value + '_' + hrReportData.from + '_' + hrReportData.to + '.csv';
    a.click();
    URL.revokeObjectURL(a.href);
});

document.getElementById('btn-print-report').addEventListener('click', function () {
    if (!hrReportData) return;
    const dir = (window.appLanguage && ['ar', 'ckb'].includes(window.appLanguage)) ? 'rtl' : 'ltr';
    const tr = (key, fallback) => (window.appTranslations && window.appTranslations[key]) || fallback || key;
    const logoUrl = (window.location.origin || '') + '/assets/images/first.jpeg';
    const tableRows = hrReportData.rows.length
        ? hrReportData.rows.map(row => '<tr>' + row.map(cell => '<td>' + esc(cell) + '</td>').join('') + '</tr>').join('')
        : '<tr><td colspan="' + hrReportData.columns.length + '" style="text-align:center;padding:18px;">' + tr('no_data_for_filters', 'No data for these filters.') + '</td></tr>';
    const reportTitle = document.getElementById('report-type').selectedOptions[0].textContent || tr('report', 'Report');
    const win = window.open('', '_blank');
    win.document.write('<html dir="' + dir + '" lang="' + (window.appLanguage || 'en') + '"><head>'
        + '<title>' + esc(reportTitle) + '</title>'
        + '<style>'
        + 'body{margin:0;padding:28px;background:#edf2ff;font-family:Arial,Helvetica,sans-serif;color:#0f172a;direction:' + dir + ';} '
        + '.hr-report-modern-shell{background:linear-gradient(180deg,#f8fafc 0%, #eef4ff 100%);border:1px solid rgba(148,163,184,.28);border-radius:18px;box-shadow:0 18px 42px rgba(15,23,42,.08);overflow:hidden;} '
        + '.hr-report-modern-header{display:flex;align-items:center;justify-content:space-between;gap:18px;padding:24px 28px 18px;border-bottom:1px solid rgba(148,163,184,.2);background:linear-gradient(135deg,rgba(59,130,246,.08),rgba(15,118,110,.08));} '
        + '.hr-report-brand{display:flex;align-items:center;gap:14px;min-width:0;} '
        + '.hr-report-logo{width:72px;height:72px;object-fit:contain;background:rgba(255,255,255,.9);border:1px solid rgba(148,163,184,.3);border-radius:18px;padding:8px;box-shadow:0 8px 20px rgba(15,23,42,.08);} '
        + '.hr-report-company{display:flex;flex-direction:column;min-width:0;} '
        + '.hr-report-company-name{font-size:1.2rem;font-weight:800;letter-spacing:.02em;color:#0f172a;} '
        + '.hr-report-company-sub{font-size:.75rem;text-transform:uppercase;letter-spacing:.12em;color:#475569;} '
        + '.hr-report-meta{display:grid;gap:8px;min-width:200px;text-align:' + (dir === 'rtl' ? 'left' : 'right') + ';} '
        + '.hr-report-meta-label{font-size:.7rem;font-weight:700;letter-spacing:.08em;color:#64748b;text-transform:uppercase;} '
        + '.hr-report-meta-value{font-size:.96rem;font-weight:700;color:#0f172a;} '
        + '.hr-report-summary{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:14px;padding:18px 28px;border-bottom:1px solid rgba(148,163,184,.2);background:rgba(255,255,255,.4);} '
        + '.hr-report-summary-item{padding:14px 16px;border:1px solid rgba(148,163,184,.2);border-radius:14px;background:rgba(255,255,255,.62);} '
        + '.hr-report-summary-item small{display:block;font-size:.7rem;font-weight:700;letter-spacing:.09em;text-transform:uppercase;color:#64748b;margin-bottom:8px;} '
        + '.hr-report-summary-item strong{font-size:1rem;color:#0f172a;} '
        + '.hr-report-table-modern{width:100%;border-collapse:collapse;margin:0;background:rgba(255,255,255,.8);} '
        + '.hr-report-table-modern thead th{background:linear-gradient(135deg,#0f172a,#1e293b);color:#f8fafc;font-size:.76rem;text-transform:uppercase;letter-spacing:.05em;border:1px solid rgba(148,163,184,.18);padding:12px 10px;text-align:start;} '
        + '.hr-report-table-modern tbody td{border:1px solid rgba(148,163,184,.16);padding:10px 12px;font-size:.9rem;color:#0f172a;background:rgba(255,255,255,.22);text-align:start;} '
        + '.hr-report-table-modern tbody tr:nth-child(even) td{background:rgba(148,163,184,.04);} '
        + '.hr-report-signoff{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:18px;padding:24px 28px 10px;border-top:1px solid rgba(148,163,184,.2);background:rgba(255,255,255,.34);} '
        + '.hr-report-signature-box{min-height:96px;border-top:3px solid #3b82f6;padding-top:12px;display:flex;flex-direction:column;justify-content:flex-end;gap:8px;color:#475569;font-size:.78rem;font-weight:600;} '
        + '.hr-report-signature-box span{display:block;color:#64748b;font-size:.72rem;text-transform:uppercase;letter-spacing:.08em;} '
        + '.hr-report-footer{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:16px 28px 24px;color:#475569;font-size:.72rem;border-top:1px solid rgba(148,163,184,.2);} '
        + '.hr-report-stamp{border:1px dashed rgba(71,85,105,.4);border-radius:8px;padding:6px 12px;font-weight:700;color:#475569;background:rgba(255,255,255,.4);} '
        + '@media print{body{padding:10px;} .hr-report-modern-shell{box-shadow:none;border-radius:0;}}'
        + '</style></head><body>'
        + '<div class="hr-report-modern-shell">'
        + '<div class="hr-report-modern-header">'
        + '<div class="hr-report-brand">'
        + '<img class="hr-report-logo" src="' + logoUrl + '" alt="logo">'
        + '<div class="hr-report-company"><div class="hr-report-company-name">Green World Towers</div><div class="hr-report-company-sub">' + tr('hr_management', 'HR Management') + '</div></div>'
        + '</div>'
        + '<div class="hr-report-meta">'
        + '<div class="hr-report-meta-label">' + tr('report', 'Report') + '</div>'
        + '<div class="hr-report-meta-value">' + esc(reportTitle) + '</div>'
        + '</div>'
        + '</div>'
        + '<div class="hr-report-summary">'
        + '<div class="hr-report-summary-item"><small>' + tr('date_range', 'Date range') + '</small><strong>' + esc(hrReportData.from) + ' → ' + esc(hrReportData.to) + '</strong></div>'
        + '<div class="hr-report-summary-item"><small>' + tr('generated_at', 'Generated at') + '</small><strong>' + esc(new Date().toLocaleString()) + '</strong></div>'
        + '<div class="hr-report-summary-item"><small>' + tr('rows_count', 'Rows') + '</small><strong>' + esc(hrReportData.rows.length) + '</strong></div>'
        + '</div>'
        + '<div style="padding:18px 28px 0;">'
        + '<table class="hr-report-table-modern"><thead><tr>' + hrReportData.columns.map(c => '<th>' + esc(c) + '</th>').join('') + '</tr></thead><tbody>' + tableRows + '</tbody></table>'
        + '</div>'
        + '<div class="hr-report-signoff">'
        + '<div class="hr-report-signature-box"><span>' + tr('prepared_by', 'Prepared by') + '</span>____________________</div>'
        + '<div class="hr-report-signature-box"><span>' + tr('reviewed_by', 'Reviewed by') + '</span>____________________</div>'
        + '<div class="hr-report-signature-box"><span>' + tr('approved_by', 'Approved by') + '</span>____________________</div>'
        + '</div>'
        + '<div class="hr-report-footer"><div class="hr-report-stamp">' + tr('company_stamp', 'Company stamp') + '</div><div>' + tr('generated_by_system', 'Generated by HR system') + '</div></div>'
        + '</div>'
        + '</body></html>');
    win.document.close();
    win.focus();
    setTimeout(() => win.print(), 300);
});
</script>
</body>
</html>
