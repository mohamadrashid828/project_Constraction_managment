<?php
session_start();
require_once '../config.php';
require_once 'includes/permissions.php';
require_once 'includes/csrf.php';
require_once 'includes/hr.php';

header('Content-Type: application/json; charset=utf-8');

function hr_json(bool $success, string $message, array $extra = []): void
{
    echo json_encode(array_merge(['success' => $success, 'message' => $message], $extra), JSON_UNESCAPED_UNICODE);
    exit;
}

if (empty($_SESSION['user_id'])) {
    hr_json(false, 'Not authenticated');
}

$user_id = (int)$_SESSION['user_id'];

// Schema (and the self-healing 'hr' permission grant) must exist before the
// permission check, or an admin's very first call would be denied.
ensure_hr_schema($conn);

$permissions = get_user_permissions($conn, $user_id);
if (!in_array('hr', $permissions, true)) {
    hr_json(false, 'Access denied');
}

$action = trim((string)($_POST['action'] ?? $_GET['action'] ?? ''));

// Reads (GET) don't mutate anything; every write below requires the token.
$readActions = ['get_employee', 'payroll_prefill', 'report'];
if (!in_array($action, $readActions, true) && !verify_csrf_token($_POST['csrf_token'] ?? null)) {
    hr_json(false, 'Invalid or expired session token. Please refresh the page.');
}

function hr_load_employee(mysqli $conn, int $id): ?array
{
    $stmt = $conn->prepare("SELECT * FROM hr_employees WHERE id = ? AND is_deleted = 0 LIMIT 1");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

/** Close the open assignment row and open a new one (used on add/edit/move). */
function hr_assign_building(mysqli $conn, int $employeeId, ?int $buildingId, int $userId, string $note = ''): void
{
    $open = null;
    $stmt = $conn->prepare("SELECT id, building_id FROM hr_employee_assignments
                            WHERE employee_id = ? AND assigned_to IS NULL
                            ORDER BY id DESC LIMIT 1");
    $stmt->bind_param('i', $employeeId);
    $stmt->execute();
    $open = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $currentBuilding = $open ? ($open['building_id'] !== null ? (int)$open['building_id'] : null) : false;
    if ($open && $currentBuilding === $buildingId) {
        return; // unchanged
    }

    $today = date('Y-m-d');
    if ($open) {
        $stmt = $conn->prepare("UPDATE hr_employee_assignments SET assigned_to = ? WHERE id = ?");
        $stmt->bind_param('si', $today, $open['id']);
        $stmt->execute();
        $stmt->close();
    }

    $stmt = $conn->prepare("INSERT INTO hr_employee_assignments (employee_id, building_id, assigned_from, notes, created_by)
                            VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param('iissi', $employeeId, $buildingId, $today, $note, $userId);
    $stmt->execute();
    $stmt->close();
}

switch ($action) {

    // ── Employees ───────────────────────────────────────────────────────────
    case 'save_employee': {
        $employee_id = (int)($_POST['employee_id'] ?? 0);
        $full_name = trim((string)($_POST['full_name'] ?? ''));
        $phone = trim((string)($_POST['phone'] ?? ''));
        $emergency_phone = trim((string)($_POST['emergency_phone'] ?? ''));
        $national_id = trim((string)($_POST['national_id'] ?? ''));
        $address = trim((string)($_POST['address'] ?? ''));
        $job_title = trim((string)($_POST['job_title'] ?? ''));
        $department = trim((string)($_POST['department'] ?? ''));
        $employment_type = trim((string)($_POST['employment_type'] ?? 'permanent'));
        $building_id = (int)($_POST['building_id'] ?? 0) ?: null;
        $hire_date = trim((string)($_POST['hire_date'] ?? '')) ?: null;
        $salary_amount = (float)($_POST['salary_amount'] ?? 0);
        $salary_period = trim((string)($_POST['salary_period'] ?? 'monthly'));
        $currency = strtoupper(trim((string)($_POST['currency'] ?? 'IQD')));
        $status = trim((string)($_POST['status'] ?? 'active'));
        $safety_done = !empty($_POST['safety_training_done']) ? 1 : 0;
        $safety_date = trim((string)($_POST['safety_training_date'] ?? '')) ?: null;
        $notes = trim((string)($_POST['notes'] ?? ''));

        if ($full_name === '') {
            hr_json(false, 'Full name is required.');
        }
        if (!isset(hr_employment_types()[$employment_type])) {
            hr_json(false, 'Invalid employment type.');
        }
        if (!in_array($salary_period, ['monthly', 'daily', 'hourly'], true)) {
            hr_json(false, 'Invalid pay period.');
        }
        if (!in_array($currency, ['IQD', 'USD'], true)) {
            hr_json(false, 'Currency must be IQD or USD.');
        }
        if (!isset(hr_employee_statuses()[$status])) {
            hr_json(false, 'Invalid status.');
        }
        if ($salary_amount < 0) {
            hr_json(false, 'Salary cannot be negative.');
        }
        if ($building_id !== null) {
            $chk = $conn->prepare("SELECT id FROM buildings WHERE id = ? LIMIT 1");
            $chk->bind_param('i', $building_id);
            $chk->execute();
            if (!$chk->get_result()->fetch_assoc()) {
                hr_json(false, 'Selected project/site no longer exists.');
            }
            $chk->close();
        }
        if ($safety_done && $safety_date === null) {
            $safety_date = date('Y-m-d');
        }
        if (!$safety_done) {
            $safety_date = null;
        }

        if ($employee_id > 0) {
            if (!hr_load_employee($conn, $employee_id)) {
                hr_json(false, 'Employee not found.');
            }
            $stmt = $conn->prepare("UPDATE hr_employees SET
                full_name = ?, phone = ?, emergency_phone = ?, national_id = ?, address = ?,
                job_title = ?, department = ?, employment_type = ?, building_id = ?, hire_date = ?,
                salary_amount = ?, salary_period = ?, currency = ?, status = ?,
                safety_training_done = ?, safety_training_date = ?, notes = ?
                WHERE id = ?");
            $stmt->bind_param('ssssssssisdsssissi',
                $full_name, $phone, $emergency_phone, $national_id, $address,
                $job_title, $department, $employment_type, $building_id, $hire_date,
                $salary_amount, $salary_period, $currency, $status,
                $safety_done, $safety_date, $notes, $employee_id);
            if (!$stmt->execute()) {
                hr_json(false, 'Could not save the employee: ' . $stmt->error);
            }
            $stmt->close();
            hr_assign_building($conn, $employee_id, $building_id, $user_id, 'Updated from employee form');
            hr_json(true, 'Employee updated.');
        }

        $code = hr_next_employee_code($conn);
        $stmt = $conn->prepare("INSERT INTO hr_employees
            (employee_code, full_name, phone, emergency_phone, national_id, address, job_title, department,
             employment_type, building_id, hire_date, salary_amount, salary_period, currency, status,
             safety_training_done, safety_training_date, notes, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param('sssssssssisdsssissi',
            $code, $full_name, $phone, $emergency_phone, $national_id, $address, $job_title, $department,
            $employment_type, $building_id, $hire_date, $salary_amount, $salary_period, $currency, $status,
            $safety_done, $safety_date, $notes, $user_id);
        if (!$stmt->execute()) {
            hr_json(false, 'Could not add the employee: ' . $stmt->error);
        }
        $newId = (int)$stmt->insert_id;
        $stmt->close();
        hr_assign_building($conn, $newId, $building_id, $user_id, 'Initial assignment');
        hr_json(true, "Employee added as $code.");
    }

    case 'delete_employee': {
        $employee_id = (int)($_POST['employee_id'] ?? 0);
        if (!hr_load_employee($conn, $employee_id)) {
            hr_json(false, 'Employee not found.');
        }
        // Soft delete: attendance/payroll/contract history must keep resolving.
        $stmt = $conn->prepare("UPDATE hr_employees SET is_deleted = 1 WHERE id = ?");
        $stmt->bind_param('i', $employee_id);
        $ok = $stmt->execute();
        $stmt->close();
        hr_json($ok, $ok ? 'Employee removed.' : 'Could not remove the employee.');
    }

    case 'get_employee': {
        $employee_id = (int)($_GET['employee_id'] ?? 0);
        $employee = hr_load_employee($conn, $employee_id);
        if (!$employee) {
            hr_json(false, 'Employee not found.');
        }

        $buildingName = null;
        if ($employee['building_id'] !== null) {
            $stmt = $conn->prepare("SELECT building_name FROM buildings WHERE id = ?");
            $stmt->bind_param('i', $employee['building_id']);
            $stmt->execute();
            $buildingName = ($stmt->get_result()->fetch_assoc()['building_name'] ?? null);
            $stmt->close();
        }

        $documents = [];
        $stmt = $conn->prepare("SELECT id, doc_name, doc_type, created_at FROM hr_documents WHERE employee_id = ? ORDER BY created_at DESC");
        $stmt->bind_param('i', $employee_id);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $documents[] = $row;
        }
        $stmt->close();

        $assignments = [];
        $stmt = $conn->prepare("SELECT a.assigned_from, a.assigned_to, a.notes, b.building_name
                                FROM hr_employee_assignments a
                                LEFT JOIN buildings b ON b.id = a.building_id
                                WHERE a.employee_id = ? ORDER BY a.assigned_from DESC, a.id DESC LIMIT 12");
        $stmt->bind_param('i', $employee_id);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $row['building_name'] = $row['building_name'] ?: 'Office / Unassigned';
            $assignments[] = $row;
        }
        $stmt->close();

        // Attendance summary, current month.
        $monthStart = date('Y-m-01');
        $today = date('Y-m-d');
        $attSummary = ['present' => 0, 'late' => 0, 'half_day' => 0, 'absent' => 0, 'leave' => 0];
        $stmt = $conn->prepare("SELECT status, COUNT(*) c FROM hr_attendance
                                WHERE employee_id = ? AND att_date BETWEEN ? AND ? GROUP BY status");
        $stmt->bind_param('iss', $employee_id, $monthStart, $today);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $attSummary[(string)$row['status']] = (int)$row['c'];
        }
        $stmt->close();

        $payroll = [];
        $stmt = $conn->prepare("SELECT period_start, period_end, net_amount, currency, payment_status
                                FROM hr_payroll WHERE employee_id = ? ORDER BY period_start DESC LIMIT 6");
        $stmt->bind_param('i', $employee_id);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $payroll[] = $row;
        }
        $stmt->close();

        hr_json(true, '', [
            'employee' => $employee,
            'building_name' => $buildingName,
            'documents' => $documents,
            'assignments' => $assignments,
            'attendance_month' => $attSummary,
            'payroll' => $payroll,
        ]);
    }

    // ── Documents ───────────────────────────────────────────────────────────
    case 'upload_document': {
        $employee_id = (int)($_POST['employee_id'] ?? 0);
        $doc_name = trim((string)($_POST['doc_name'] ?? ''));
        $doc_type = trim((string)($_POST['doc_type'] ?? 'other'));
        if (!hr_load_employee($conn, $employee_id)) {
            hr_json(false, 'Employee not found.');
        }
        if ($doc_name === '') {
            hr_json(false, 'Please give the document a name.');
        }
        if (!in_array($doc_type, ['id', 'contract', 'certificate', 'other'], true)) {
            $doc_type = 'other';
        }
        $stored = hr_store_document($_FILES['document_file'] ?? []);
        if (!$stored['ok']) {
            hr_json(false, $stored['message']);
        }
        $stmt = $conn->prepare("INSERT INTO hr_documents (employee_id, doc_name, doc_type, file_name, uploaded_by)
                                VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param('isssi', $employee_id, $doc_name, $doc_type, $stored['filename'], $user_id);
        if (!$stmt->execute()) {
            @unlink(hr_document_dir() . $stored['filename']);
            hr_json(false, 'Could not save the document record.');
        }
        $stmt->close();
        hr_json(true, 'Document uploaded.');
    }

    case 'delete_document': {
        $doc_id = (int)($_POST['document_id'] ?? 0);
        $stmt = $conn->prepare("SELECT file_name FROM hr_documents WHERE id = ? LIMIT 1");
        $stmt->bind_param('i', $doc_id);
        $stmt->execute();
        $doc = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$doc) {
            hr_json(false, 'Document not found.');
        }
        $stmt = $conn->prepare("DELETE FROM hr_documents WHERE id = ?");
        $stmt->bind_param('i', $doc_id);
        $ok = $stmt->execute();
        $stmt->close();
        if ($ok) {
            @unlink(hr_document_dir() . basename((string)$doc['file_name']));
        }
        hr_json($ok, $ok ? 'Document deleted.' : 'Could not delete the document.');
    }

    // ── Attendance ──────────────────────────────────────────────────────────
    case 'mark_attendance': {
        $employee_id = (int)($_POST['employee_id'] ?? 0);
        $att_date = trim((string)($_POST['att_date'] ?? ''));
        $status = trim((string)($_POST['status'] ?? 'present'));
        $check_in = trim((string)($_POST['check_in'] ?? '')) ?: null;
        $check_out = trim((string)($_POST['check_out'] ?? '')) ?: null;
        $notes = trim((string)($_POST['notes'] ?? ''));

        if (!hr_load_employee($conn, $employee_id)) {
            hr_json(false, 'Employee not found.');
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $att_date)) {
            hr_json(false, 'Invalid date.');
        }
        if ($att_date > date('Y-m-d')) {
            hr_json(false, 'Attendance cannot be marked for a future date.');
        }
        if (!isset(hr_attendance_statuses()[$status])) {
            hr_json(false, 'Invalid attendance status.');
        }
        if ($check_in && $check_out && $check_out < $check_in) {
            hr_json(false, 'Check-out cannot be before check-in.');
        }

        $stmt = $conn->prepare("INSERT INTO hr_attendance (employee_id, att_date, status, check_in, check_out, notes, marked_by)
                                VALUES (?, ?, ?, ?, ?, ?, ?)
                                ON DUPLICATE KEY UPDATE
                                    status = VALUES(status), check_in = VALUES(check_in),
                                    check_out = VALUES(check_out), notes = VALUES(notes), marked_by = VALUES(marked_by)");
        $stmt->bind_param('isssssi', $employee_id, $att_date, $status, $check_in, $check_out, $notes, $user_id);
        $ok = $stmt->execute();
        $err = $stmt->error;
        $stmt->close();
        hr_json($ok, $ok ? 'Attendance saved.' : 'Could not save attendance: ' . $err);
    }

    case 'mark_all_present': {
        $att_date = trim((string)($_POST['att_date'] ?? ''));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $att_date) || $att_date > date('Y-m-d')) {
            hr_json(false, 'Invalid date.');
        }
        // Only fills the gaps — anyone already marked keeps their status.
        $stmt = $conn->prepare("INSERT INTO hr_attendance (employee_id, att_date, status, marked_by)
                                SELECT e.id, ?, 'present', ?
                                FROM hr_employees e
                                WHERE e.is_deleted = 0 AND e.status = 'active'
                                  AND NOT EXISTS (SELECT 1 FROM hr_attendance a WHERE a.employee_id = e.id AND a.att_date = ?)");
        $stmt->bind_param('sis', $att_date, $user_id, $att_date);
        $ok = $stmt->execute();
        $added = $stmt->affected_rows;
        $stmt->close();
        hr_json($ok, $ok ? ($added > 0 ? "Marked $added employee(s) present." : 'Everyone is already marked for this date.') : 'Could not mark attendance.');
    }

    // ── Leaves ──────────────────────────────────────────────────────────────
    case 'save_leave': {
        $employee_id = (int)($_POST['employee_id'] ?? 0);
        $leave_type = trim((string)($_POST['leave_type'] ?? 'annual'));
        $date_from = trim((string)($_POST['date_from'] ?? ''));
        $date_to = trim((string)($_POST['date_to'] ?? ''));
        $reason = trim((string)($_POST['reason'] ?? ''));

        if (!hr_load_employee($conn, $employee_id)) {
            hr_json(false, 'Employee not found.');
        }
        if (!isset(hr_leave_types()[$leave_type])) {
            hr_json(false, 'Invalid leave type.');
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_from) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_to)) {
            hr_json(false, 'Both dates are required.');
        }
        if ($date_to < $date_from) {
            hr_json(false, 'End date cannot be before start date.');
        }
        $days = (int)((strtotime($date_to) - strtotime($date_from)) / 86400) + 1;

        $stmt = $conn->prepare("INSERT INTO hr_leaves (employee_id, leave_type, date_from, date_to, days, reason, created_by)
                                VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param('isssdsi', $employee_id, $leave_type, $date_from, $date_to, $days, $reason, $user_id);
        $ok = $stmt->execute();
        $stmt->close();
        hr_json($ok, $ok ? "Leave request added ($days day(s))." : 'Could not save the leave request.');
    }

    case 'review_leave': {
        $leave_id = (int)($_POST['leave_id'] ?? 0);
        $decision = trim((string)($_POST['decision'] ?? ''));
        if (!in_array($decision, ['approved', 'rejected'], true)) {
            hr_json(false, 'Invalid decision.');
        }
        $stmt = $conn->prepare("SELECT id, status FROM hr_leaves WHERE id = ? LIMIT 1");
        $stmt->bind_param('i', $leave_id);
        $stmt->execute();
        $leave = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$leave) {
            hr_json(false, 'Leave request not found.');
        }
        if ($leave['status'] !== 'pending') {
            hr_json(false, 'This request was already reviewed.');
        }
        $now = date('Y-m-d H:i:s');
        $stmt = $conn->prepare("UPDATE hr_leaves SET status = ?, reviewed_by = ?, reviewed_at = ? WHERE id = ?");
        $stmt->bind_param('sisi', $decision, $user_id, $now, $leave_id);
        $ok = $stmt->execute();
        $stmt->close();
        hr_json($ok, $ok ? 'Leave ' . $decision . '.' : 'Could not update the leave request.');
    }

    case 'delete_leave': {
        $leave_id = (int)($_POST['leave_id'] ?? 0);
        // Only pending requests can be withdrawn; reviewed ones are history.
        $stmt = $conn->prepare("DELETE FROM hr_leaves WHERE id = ? AND status = 'pending'");
        $stmt->bind_param('i', $leave_id);
        $stmt->execute();
        $ok = $stmt->affected_rows > 0;
        $stmt->close();
        hr_json($ok, $ok ? 'Leave request deleted.' : 'Only pending requests can be deleted.');
    }

    // ── Contracts ───────────────────────────────────────────────────────────
    case 'save_contract': {
        $employee_id = (int)($_POST['employee_id'] ?? 0);
        $contract_title = trim((string)($_POST['contract_title'] ?? ''));
        $start_date = trim((string)($_POST['start_date'] ?? ''));
        $end_date = trim((string)($_POST['end_date'] ?? '')) ?: null;
        $salary_amount = (float)($_POST['salary_amount'] ?? 0);
        $salary_period = trim((string)($_POST['salary_period'] ?? 'monthly'));
        $currency = strtoupper(trim((string)($_POST['currency'] ?? 'IQD')));
        $notes = trim((string)($_POST['notes'] ?? ''));

        if (!hr_load_employee($conn, $employee_id)) {
            hr_json(false, 'Employee not found.');
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start_date)) {
            hr_json(false, 'Start date is required.');
        }
        if ($end_date !== null && $end_date < $start_date) {
            hr_json(false, 'End date cannot be before start date.');
        }
        if (!in_array($salary_period, ['monthly', 'daily', 'hourly'], true) || !in_array($currency, ['IQD', 'USD'], true)) {
            hr_json(false, 'Invalid pay settings.');
        }

        $file_name = null;
        if (!empty($_FILES['contract_file']) && ($_FILES['contract_file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $stored = hr_store_document($_FILES['contract_file']);
            if (!$stored['ok']) {
                hr_json(false, $stored['message']);
            }
            $file_name = $stored['filename'];
        }

        // A new active contract supersedes the employee's current active one.
        $stmt = $conn->prepare("UPDATE hr_contracts SET status = 'renewed' WHERE employee_id = ? AND status = 'active'");
        $stmt->bind_param('i', $employee_id);
        $stmt->execute();
        $stmt->close();

        $stmt = $conn->prepare("INSERT INTO hr_contracts
            (employee_id, contract_title, start_date, end_date, salary_amount, salary_period, currency, file_name, notes, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param('isssdssssi', $employee_id, $contract_title, $start_date, $end_date,
            $salary_amount, $salary_period, $currency, $file_name, $notes, $user_id);
        if (!$stmt->execute()) {
            hr_json(false, 'Could not save the contract: ' . $stmt->error);
        }
        $stmt->close();

        // Keep the employee's pay fields in step with their newest contract.
        $stmt = $conn->prepare("UPDATE hr_employees SET salary_amount = ?, salary_period = ?, currency = ? WHERE id = ?");
        $stmt->bind_param('dssi', $salary_amount, $salary_period, $currency, $employee_id);
        $stmt->execute();
        $stmt->close();

        hr_json(true, 'Contract saved.');
    }

    case 'set_contract_status': {
        $contract_id = (int)($_POST['contract_id'] ?? 0);
        $status = trim((string)($_POST['status'] ?? ''));
        if (!in_array($status, ['active', 'terminated'], true)) {
            hr_json(false, 'Invalid contract status.');
        }
        $stmt = $conn->prepare("UPDATE hr_contracts SET status = ? WHERE id = ?");
        $stmt->bind_param('si', $status, $contract_id);
        $stmt->execute();
        $ok = $stmt->affected_rows > 0;
        $stmt->close();
        hr_json($ok, $ok ? 'Contract updated.' : 'Contract not found or unchanged.');
    }

    // ── Payroll ─────────────────────────────────────────────────────────────
    case 'payroll_prefill': {
        $employee_id = (int)($_GET['employee_id'] ?? 0);
        $from = trim((string)($_GET['period_start'] ?? ''));
        $to = trim((string)($_GET['period_end'] ?? ''));
        $employee = hr_load_employee($conn, $employee_id);
        if (!$employee) {
            hr_json(false, 'Employee not found.');
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to) || $to < $from) {
            hr_json(false, 'Pick a valid period first.');
        }
        $suggestion = hr_payroll_suggestion($conn, $employee, $from, $to);
        hr_json(true, '', [
            'amount' => $suggestion['amount'],
            'basis' => $suggestion['basis'],
            'currency' => $employee['currency'],
            'salary_period' => $employee['salary_period'],
        ]);
    }

    case 'save_payroll': {
        $employee_id = (int)($_POST['employee_id'] ?? 0);
        $period_start = trim((string)($_POST['period_start'] ?? ''));
        $period_end = trim((string)($_POST['period_end'] ?? ''));
        $base_amount = (float)($_POST['base_amount'] ?? 0);
        $overtime_amount = (float)($_POST['overtime_amount'] ?? 0);
        $bonus_amount = (float)($_POST['bonus_amount'] ?? 0);
        $deduction_amount = (float)($_POST['deduction_amount'] ?? 0);
        $work_basis = trim((string)($_POST['work_basis'] ?? ''));
        $notes = trim((string)($_POST['notes'] ?? ''));

        $employee = hr_load_employee($conn, $employee_id);
        if (!$employee) {
            hr_json(false, 'Employee not found.');
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $period_start) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $period_end) || $period_end < $period_start) {
            hr_json(false, 'A valid pay period is required.');
        }
        if ($base_amount < 0 || $overtime_amount < 0 || $bonus_amount < 0 || $deduction_amount < 0) {
            hr_json(false, 'Amounts cannot be negative.');
        }

        // One record per employee per overlapping period keeps double-pay out.
        $stmt = $conn->prepare("SELECT id FROM hr_payroll
                                WHERE employee_id = ? AND period_start <= ? AND period_end >= ? LIMIT 1");
        $stmt->bind_param('iss', $employee_id, $period_end, $period_start);
        $stmt->execute();
        $overlap = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($overlap) {
            hr_json(false, 'A payment record already covers part of this period for this employee.');
        }

        $net = round($base_amount + $overtime_amount + $bonus_amount - $deduction_amount, 2);
        if ($net < 0) {
            hr_json(false, 'Deductions are larger than the total pay.');
        }
        $currency = (string)$employee['currency'];
        $building_id = $employee['building_id'] !== null ? (int)$employee['building_id'] : null;

        $stmt = $conn->prepare("INSERT INTO hr_payroll
            (employee_id, period_start, period_end, building_id, work_basis, base_amount, overtime_amount,
             bonus_amount, deduction_amount, net_amount, currency, notes, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param('issisdddddssi', $employee_id, $period_start, $period_end, $building_id, $work_basis,
            $base_amount, $overtime_amount, $bonus_amount, $deduction_amount, $net, $currency, $notes, $user_id);
        $ok = $stmt->execute();
        $err = $stmt->error;
        $stmt->close();
        hr_json($ok, $ok ? 'Payment record created (' . hr_money($net, $currency) . ').' : 'Could not save the payment: ' . $err);
    }

    case 'set_payroll_status': {
        $payroll_id = (int)($_POST['payroll_id'] ?? 0);
        $status = trim((string)($_POST['status'] ?? ''));
        if (!in_array($status, ['paid', 'unpaid'], true)) {
            hr_json(false, 'Invalid payment status.');
        }
        $paid_date = $status === 'paid' ? date('Y-m-d') : null;
        $stmt = $conn->prepare("UPDATE hr_payroll SET payment_status = ?, paid_date = ? WHERE id = ?");
        $stmt->bind_param('ssi', $status, $paid_date, $payroll_id);
        $stmt->execute();
        $ok = $stmt->affected_rows > 0;
        $stmt->close();
        hr_json($ok, $ok ? 'Marked as ' . $status . '.' : 'Payment record not found or unchanged.');
    }

    case 'delete_payroll': {
        $payroll_id = (int)($_POST['payroll_id'] ?? 0);
        // Paid records are financial history — unmark first, then delete.
        $stmt = $conn->prepare("DELETE FROM hr_payroll WHERE id = ? AND payment_status = 'unpaid'");
        $stmt->bind_param('i', $payroll_id);
        $stmt->execute();
        $ok = $stmt->affected_rows > 0;
        $stmt->close();
        hr_json($ok, $ok ? 'Payment record deleted.' : 'Only unpaid records can be deleted (unmark it first).');
    }

    // ── Reports ─────────────────────────────────────────────────────────────
    case 'report': {
        $type = trim((string)($_GET['type'] ?? ''));
        $from = trim((string)($_GET['date_from'] ?? ''));
        $to = trim((string)($_GET['date_to'] ?? ''));
        $building_id = (int)($_GET['building_id'] ?? 0);
        $employee_id = (int)($_GET['employee_id'] ?? 0);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
            $from = date('Y-m-01');
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
            $to = date('Y-m-d');
        }

        $types = hr_employment_types();
        $rows = [];
        $columns = [];

        if ($type === 'employees') {
            $columns = ['Code', 'Name', 'Job Title', 'Department', 'Type', 'Project / Site', 'Hire Date', 'Pay Rate', 'Status'];
            $sql = "SELECT e.*, b.building_name FROM hr_employees e
                    LEFT JOIN buildings b ON b.id = e.building_id
                    WHERE e.is_deleted = 0" . ($building_id > 0 ? " AND e.building_id = " . $building_id : "") . "
                    ORDER BY e.full_name";
            $res = $conn->query($sql);
            $statuses = hr_employee_statuses();
            while ($res && $r = $res->fetch_assoc()) {
                $rows[] = [
                    $r['employee_code'], $r['full_name'], $r['job_title'] ?: '—', $r['department'] ?: '—',
                    $types[$r['employment_type']] ?? $r['employment_type'],
                    $r['building_name'] ?: 'Office / Unassigned',
                    $r['hire_date'] ?: '—',
                    hr_money((float)$r['salary_amount'], (string)$r['currency']) . ' / ' . $r['salary_period'],
                    $statuses[$r['status']] ?? $r['status'],
                ];
            }
        } elseif ($type === 'attendance') {
            $columns = ['Code', 'Name', 'Present', 'Late', 'Half Day', 'Absent', 'On Leave', 'Worked Days'];
            $stmt = $conn->prepare("SELECT e.employee_code, e.full_name,
                    SUM(a.status = 'present') present_c, SUM(a.status = 'late') late_c,
                    SUM(a.status = 'half_day') half_c, SUM(a.status = 'absent') absent_c,
                    SUM(a.status = 'leave') leave_c
                FROM hr_employees e
                JOIN hr_attendance a ON a.employee_id = e.id AND a.att_date BETWEEN ? AND ?
                WHERE e.is_deleted = 0 " . ($employee_id > 0 ? " AND e.id = " . $employee_id : "") . "
                GROUP BY e.id, e.employee_code, e.full_name
                ORDER BY e.full_name");
            $stmt->bind_param('ss', $from, $to);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($r = $res->fetch_assoc()) {
                $worked = (float)$r['present_c'] + (float)$r['late_c'] + 0.5 * (float)$r['half_c'];
                $rows[] = [
                    $r['employee_code'], $r['full_name'], (int)$r['present_c'], (int)$r['late_c'],
                    (int)$r['half_c'], (int)$r['absent_c'], (int)$r['leave_c'],
                    rtrim(rtrim(number_format($worked, 1, '.', ''), '0'), '.'),
                ];
            }
            $stmt->close();
        } elseif ($type === 'payroll') {
            $columns = ['Employee', 'Period', 'Project / Site', 'Base', 'Overtime', 'Bonus', 'Deductions', 'Net', 'Status', 'Paid On'];
            $stmt = $conn->prepare("SELECT p.*, e.full_name, e.employee_code, b.building_name
                FROM hr_payroll p
                JOIN hr_employees e ON e.id = p.employee_id
                LEFT JOIN buildings b ON b.id = p.building_id
                WHERE p.period_start <= ? AND p.period_end >= ?" .
                ($employee_id > 0 ? " AND p.employee_id = " . $employee_id : "") .
                ($building_id > 0 ? " AND p.building_id = " . $building_id : "") . "
                ORDER BY p.period_start DESC, e.full_name");
            $stmt->bind_param('ss', $to, $from);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($r = $res->fetch_assoc()) {
                $cur = (string)$r['currency'];
                $rows[] = [
                    $r['full_name'] . ' (' . $r['employee_code'] . ')',
                    $r['period_start'] . ' → ' . $r['period_end'],
                    $r['building_name'] ?: 'Office / Unassigned',
                    hr_money((float)$r['base_amount'], $cur),
                    hr_money((float)$r['overtime_amount'], $cur),
                    hr_money((float)$r['bonus_amount'], $cur),
                    hr_money((float)$r['deduction_amount'], $cur),
                    hr_money((float)$r['net_amount'], $cur),
                    ucfirst((string)$r['payment_status']),
                    $r['paid_date'] ?: '—',
                ];
            }
            $stmt->close();
        } elseif ($type === 'labor_cost') {
            // Simple summary: payroll net per project per currency in the range.
            $columns = ['Project / Site', 'Payments', 'Total (IQD)', 'Total (USD)'];
            $stmt = $conn->prepare("SELECT COALESCE(b.building_name, 'Office / Unassigned') AS site,
                    COUNT(*) c,
                    SUM(CASE WHEN p.currency = 'IQD' THEN p.net_amount ELSE 0 END) iqd_total,
                    SUM(CASE WHEN p.currency = 'USD' THEN p.net_amount ELSE 0 END) usd_total
                FROM hr_payroll p
                LEFT JOIN buildings b ON b.id = p.building_id
                WHERE p.period_start <= ? AND p.period_end >= ?
                GROUP BY site
                ORDER BY iqd_total DESC, usd_total DESC");
            $stmt->bind_param('ss', $to, $from);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($r = $res->fetch_assoc()) {
                $rows[] = [
                    $r['site'], (int)$r['c'],
                    (float)$r['iqd_total'] > 0 ? hr_money((float)$r['iqd_total'], 'IQD') : '—',
                    (float)$r['usd_total'] > 0 ? hr_money((float)$r['usd_total'], 'USD') : '—',
                ];
            }
            $stmt->close();
        } else {
            hr_json(false, 'Unknown report type.');
        }

        hr_json(true, '', ['columns' => $columns, 'rows' => $rows, 'from' => $from, 'to' => $to]);
    }

    default:
        hr_json(false, 'Unknown action.');
}
