<?php

/**
 * HR module — schema bootstrap and shared helpers.
 *
 * One tab, seven sections: Dashboard / Employees / Attendance / Payroll /
 * Leaves / Contracts / Reports. Deliberately simple — no tax, banking or
 * accounting logic; payroll rows are plain "who gets how much for which
 * period" records marked paid/unpaid.
 *
 * Tables:
 *  - hr_employees             one row per worker, office or site; soft-deleted
 *                             so payroll/attendance history always resolves
 *  - hr_employee_assignments  site/project history; the open row (assigned_to
 *                             IS NULL) is the current assignment, and
 *                             hr_employees.building_id mirrors it for cheap lists
 *  - hr_attendance            one row per employee per day (upsert)
 *  - hr_leaves                requests with approve/reject workflow
 *  - hr_contracts             employment contracts; adding a new active one
 *                             marks the previous active contract 'renewed'
 *  - hr_payroll               payment records; building_id is snapshotted at
 *                             creation so labor cost per project stays true
 *                             even after the worker moves site
 *  - hr_documents             uploaded files (ID, contract, certificate…)
 *
 * Access is a single permission: 'hr' (module 'HR'). Roles that already have
 * user_management are granted it automatically so admins see the tab at once.
 */

function ensure_hr_schema(mysqli $conn): void
{
    $conn->query("CREATE TABLE IF NOT EXISTS hr_employees (
        id INT AUTO_INCREMENT PRIMARY KEY,
        employee_code VARCHAR(20) NOT NULL,
        full_name VARCHAR(160) NOT NULL,
        phone VARCHAR(40) NULL,
        emergency_phone VARCHAR(40) NULL,
        national_id VARCHAR(60) NULL,
        address VARCHAR(255) NULL,
        job_title VARCHAR(120) NULL,
        department VARCHAR(120) NULL,
        employment_type ENUM('permanent','contract','temporary','daily','hourly','intern','site_worker') NOT NULL DEFAULT 'permanent',
        building_id INT NULL,
        hire_date DATE NULL,
        salary_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
        salary_period ENUM('monthly','daily','hourly') NOT NULL DEFAULT 'monthly',
        currency VARCHAR(10) NOT NULL DEFAULT 'IQD',
        status ENUM('active','on_leave','resigned') NOT NULL DEFAULT 'active',
        safety_training_done TINYINT(1) NOT NULL DEFAULT 0,
        safety_training_date DATE NULL,
        notes TEXT NULL,
        is_deleted TINYINT(1) NOT NULL DEFAULT 0,
        created_by INT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_employee_code (employee_code),
        INDEX idx_emp_status (status),
        INDEX idx_emp_type (employment_type),
        INDEX idx_emp_building (building_id),
        INDEX idx_emp_deleted (is_deleted)
    )");

    $conn->query("CREATE TABLE IF NOT EXISTS hr_employee_assignments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        employee_id INT NOT NULL,
        building_id INT NULL,
        assigned_from DATE NOT NULL,
        assigned_to DATE NULL,
        notes VARCHAR(255) NULL,
        created_by INT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_asg_employee (employee_id),
        INDEX idx_asg_building (building_id),
        FOREIGN KEY (employee_id) REFERENCES hr_employees(id)
    )");

    $conn->query("CREATE TABLE IF NOT EXISTS hr_attendance (
        id INT AUTO_INCREMENT PRIMARY KEY,
        employee_id INT NOT NULL,
        att_date DATE NOT NULL,
        status ENUM('present','late','half_day','absent','leave') NOT NULL DEFAULT 'present',
        check_in TIME NULL,
        check_out TIME NULL,
        notes VARCHAR(255) NULL,
        marked_by INT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_att_emp_date (employee_id, att_date),
        INDEX idx_att_date (att_date),
        FOREIGN KEY (employee_id) REFERENCES hr_employees(id)
    )");

    $conn->query("CREATE TABLE IF NOT EXISTS hr_leaves (
        id INT AUTO_INCREMENT PRIMARY KEY,
        employee_id INT NOT NULL,
        leave_type ENUM('annual','sick','unpaid','other') NOT NULL DEFAULT 'annual',
        date_from DATE NOT NULL,
        date_to DATE NOT NULL,
        days DECIMAL(5,1) NOT NULL DEFAULT 1,
        reason VARCHAR(255) NULL,
        status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
        reviewed_by INT NULL,
        reviewed_at DATETIME NULL,
        created_by INT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_leave_employee (employee_id),
        INDEX idx_leave_status (status),
        INDEX idx_leave_from (date_from),
        FOREIGN KEY (employee_id) REFERENCES hr_employees(id)
    )");

    $conn->query("CREATE TABLE IF NOT EXISTS hr_contracts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        employee_id INT NOT NULL,
        contract_title VARCHAR(160) NULL,
        start_date DATE NOT NULL,
        end_date DATE NULL,
        salary_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
        salary_period ENUM('monthly','daily','hourly') NOT NULL DEFAULT 'monthly',
        currency VARCHAR(10) NOT NULL DEFAULT 'IQD',
        status ENUM('active','expired','terminated','renewed') NOT NULL DEFAULT 'active',
        file_name VARCHAR(255) NULL,
        notes VARCHAR(255) NULL,
        created_by INT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_con_employee (employee_id),
        INDEX idx_con_status (status),
        INDEX idx_con_end (end_date),
        FOREIGN KEY (employee_id) REFERENCES hr_employees(id)
    )");

    $conn->query("CREATE TABLE IF NOT EXISTS hr_payroll (
        id INT AUTO_INCREMENT PRIMARY KEY,
        employee_id INT NOT NULL,
        period_start DATE NOT NULL,
        period_end DATE NOT NULL,
        building_id INT NULL,
        work_basis VARCHAR(80) NULL,
        base_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
        overtime_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
        bonus_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
        deduction_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
        net_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
        currency VARCHAR(10) NOT NULL DEFAULT 'IQD',
        payment_status ENUM('unpaid','paid') NOT NULL DEFAULT 'unpaid',
        paid_date DATE NULL,
        notes VARCHAR(255) NULL,
        created_by INT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_pay_employee (employee_id),
        INDEX idx_pay_status (payment_status),
        INDEX idx_pay_start (period_start),
        INDEX idx_pay_building (building_id),
        FOREIGN KEY (employee_id) REFERENCES hr_employees(id)
    )");

    $conn->query("CREATE TABLE IF NOT EXISTS hr_documents (
        id INT AUTO_INCREMENT PRIMARY KEY,
        employee_id INT NOT NULL,
        doc_name VARCHAR(160) NOT NULL,
        doc_type ENUM('id','contract','certificate','other') NOT NULL DEFAULT 'other',
        file_name VARCHAR(255) NOT NULL,
        uploaded_by INT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_doc_employee (employee_id),
        FOREIGN KEY (employee_id) REFERENCES hr_employees(id)
    )");

    hr_ensure_permission($conn);
}

/**
 * Self-healing 'hr' permission row, granted to every role that already has
 * user_management so admins/managers see the tab without extra setup. The
 * Roles & Permissions UI picks it up automatically (it groups by module).
 */
function hr_ensure_permission(mysqli $conn): void
{
    $exists = $conn->query("SELECT 1 FROM permissions WHERE name = 'hr' LIMIT 1");
    if ($exists && $exists->num_rows > 0) {
        return;
    }

    $conn->query("INSERT IGNORE INTO permissions (name, module, action_label, description)
                  VALUES ('hr', 'HR', 'Access', 'Can access the HR management module')");
    $conn->query("
        INSERT IGNORE INTO role_permissions (role_id, permission_id)
        SELECT DISTINCT rp.role_id, p.id
        FROM role_permissions rp
        JOIN permissions old ON old.id = rp.permission_id AND old.name = 'user_management'
        JOIN permissions p ON p.name = 'hr'
    ");
}

// ── Vocabulary ──────────────────────────────────────────────────────────────

function hr_employment_types(): array
{
    return [
        'permanent'   => 'Permanent',
        'contract'    => 'Contract',
        'temporary'   => 'Temporary',
        'daily'       => 'Daily Wage',
        'hourly'      => 'Hourly',
        'intern'      => 'Intern',
        'site_worker' => 'Site Worker',
    ];
}

function hr_employee_statuses(): array
{
    return ['active' => 'Active', 'on_leave' => 'On Leave', 'resigned' => 'Resigned'];
}

function hr_attendance_statuses(): array
{
    return [
        'present'  => 'Present',
        'late'     => 'Late',
        'half_day' => 'Half Day',
        'absent'   => 'Absent',
        'leave'    => 'On Leave',
    ];
}

function hr_leave_types(): array
{
    return ['annual' => 'Annual', 'sick' => 'Sick', 'unpaid' => 'Unpaid', 'other' => 'Other'];
}

/** How much of a working day an attendance status counts for in payroll. */
function hr_attendance_day_value(string $status): float
{
    if ($status === 'present' || $status === 'late') {
        return 1.0;
    }
    if ($status === 'half_day') {
        return 0.5;
    }
    return 0.0;
}

function hr_money(float $value, string $currency = 'IQD'): string
{
    $decimals = ($currency === 'USD' && fmod($value, 1.0) != 0.0) ? 2 : 0;
    $formatted = number_format($value, $decimals);
    return $currency === 'USD' ? '$' . $formatted : $formatted . ' IQD';
}

/** Next EMP-0001-style code (max existing numeric suffix + 1). */
function hr_next_employee_code(mysqli $conn): string
{
    $res = $conn->query("SELECT MAX(CAST(SUBSTRING(employee_code, 5) AS UNSIGNED)) AS n
                         FROM hr_employees WHERE employee_code LIKE 'EMP-%'");
    $n = (int)(($res ? $res->fetch_assoc() : [])['n'] ?? 0);
    return 'EMP-' . str_pad((string)($n + 1), 4, '0', STR_PAD_LEFT);
}

// ── Document storage (same conventions as inventory invoices) ──────────────

function hr_document_dir(): string
{
    $dir = dirname(__DIR__, 2) . '/data/hr_docs/';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    $htaccess = $dir . '.htaccess';
    if (!is_file($htaccess)) {
        file_put_contents($htaccess, "Require all denied\n");
    }
    return $dir;
}

/**
 * Validate and store an uploaded HR document (PDF, JPG or PNG, max 10MB).
 * Content is checked by magic bytes — the client-supplied name/type is never
 * trusted — and the on-disk name is randomly generated.
 *
 * @return array{ok: bool, message: string, filename: ?string}
 */
function hr_store_document(array $file): array
{
    if (empty($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return ['ok' => false, 'message' => 'Please choose a file to upload.', 'filename' => null];
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'message' => 'The file failed to upload. Please try again.', 'filename' => null];
    }

    $maxBytes = 10 * 1024 * 1024;
    if ($file['size'] <= 0 || $file['size'] > $maxBytes) {
        return ['ok' => false, 'message' => 'Files must be between 1 byte and 10MB.', 'filename' => null];
    }
    if (!is_uploaded_file($file['tmp_name'])) {
        return ['ok' => false, 'message' => 'Invalid upload.', 'filename' => null];
    }

    $handle = fopen($file['tmp_name'], 'rb');
    $header = $handle ? (string)fread($handle, 8) : '';
    if ($handle) {
        fclose($handle);
    }

    $ext = null;
    if (strncmp($header, '%PDF-', 5) === 0) {
        $ext = 'pdf';
    } elseif (strncmp($header, "\xFF\xD8\xFF", 3) === 0) {
        $ext = 'jpg';
    } elseif (strncmp($header, "\x89PNG\r\n\x1a\n", 8) === 0) {
        $ext = 'png';
    }
    if ($ext === null) {
        return ['ok' => false, 'message' => 'Only PDF, JPG or PNG files are accepted.', 'filename' => null];
    }

    $filename = 'hr_' . bin2hex(random_bytes(16)) . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], hr_document_dir() . $filename)) {
        return ['ok' => false, 'message' => 'Could not save the file.', 'filename' => null];
    }

    return ['ok' => true, 'message' => '', 'filename' => $filename];
}

/**
 * Suggested base pay for a payroll period, from the employee's rate and (for
 * daily/hourly staff) their attendance in the range. Monthly staff get their
 * flat salary; hourly staff need check-in/out times, otherwise a full
 * attendance day counts as 8 hours.
 *
 * @return array{amount: float, basis: string}
 */
function hr_payroll_suggestion(mysqli $conn, array $employee, string $from, string $to): array
{
    $rate = (float)$employee['salary_amount'];
    $period = (string)$employee['salary_period'];

    if ($period === 'monthly') {
        return ['amount' => $rate, 'basis' => 'Monthly salary'];
    }

    $stmt = $conn->prepare("SELECT status, check_in, check_out FROM hr_attendance
                            WHERE employee_id = ? AND att_date BETWEEN ? AND ?");
    $stmt->bind_param('iss', $employee['id'], $from, $to);
    $stmt->execute();
    $res = $stmt->get_result();

    $days = 0.0;
    $hours = 0.0;
    while ($row = $res->fetch_assoc()) {
        $dayValue = hr_attendance_day_value((string)$row['status']);
        if ($dayValue <= 0) {
            continue;
        }
        $days += $dayValue;
        if (!empty($row['check_in']) && !empty($row['check_out'])) {
            $worked = (strtotime((string)$row['check_out']) - strtotime((string)$row['check_in'])) / 3600;
            $hours += max(0.0, $worked);
        } else {
            $hours += 8.0 * $dayValue;
        }
    }
    $stmt->close();

    if ($period === 'hourly') {
        return [
            'amount' => round($hours * $rate, 2),
            'basis'  => rtrim(rtrim(number_format($hours, 2, '.', ''), '0'), '.') . ' hours worked',
        ];
    }
    return [
        'amount' => round($days * $rate, 2),
        'basis'  => rtrim(rtrim(number_format($days, 1, '.', ''), '0'), '.') . ' days worked',
    ];
}
