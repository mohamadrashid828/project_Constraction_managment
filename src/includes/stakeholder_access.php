<?php

function ensure_stakeholder_access_column(mysqli $conn): void
{
    $colCheck = $conn->query("SHOW COLUMNS FROM project_stakeholders LIKE 'access_token'");
    if ($colCheck && $colCheck->num_rows === 0) {
        $conn->query("ALTER TABLE project_stakeholders ADD COLUMN access_token VARCHAR(64) NULL UNIQUE AFTER contract_file");
        $conn->query("ALTER TABLE project_stakeholders ADD INDEX idx_access_token (access_token)");
    }
}

function generate_stakeholder_token(): string
{
    return bin2hex(random_bytes(32));
}

function backfill_stakeholder_tokens(mysqli $conn): void
{
    $res = $conn->query("SELECT id FROM project_stakeholders WHERE access_token IS NULL OR access_token = ''");
    if (!$res) {
        return;
    }
    while ($row = $res->fetch_assoc()) {
        ensure_stakeholder_token_for_id($conn, (int)$row['id']);
    }
}

function ensure_stakeholder_token_for_id(mysqli $conn, int $stakeholderId): ?string
{
    if ($stakeholderId <= 0) {
        return null;
    }

    $stmt = $conn->prepare('SELECT access_token FROM project_stakeholders WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $stakeholderId);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc();
    $stmt->close();

    if (!$row) {
        return null;
    }

    $token = trim((string)($row['access_token'] ?? ''));
    if ($token !== '') {
        return $token;
    }

    $token = generate_stakeholder_token();
    $upd = $conn->prepare('UPDATE project_stakeholders SET access_token = ? WHERE id = ?');
    $upd->bind_param('si', $token, $stakeholderId);
    $upd->execute();
    $upd->close();

    return $token;
}

function get_stakeholder_by_token(mysqli $conn, string $token): ?array
{
    $token = trim($token);
    if ($token === '' || strlen($token) < 16) {
        return null;
    }

    $stmt = $conn->prepare("
        SELECT ps.*, COALESCE(wt.work_type_name, ps.work_type_key) AS work_type_name
        FROM project_stakeholders ps
        LEFT JOIN project_work_types wt ON wt.work_type_key = ps.work_type_key
        WHERE ps.access_token = ? AND ps.is_active = 1
        LIMIT 1
    ");
    $stmt->bind_param('s', $token);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc();
    $stmt->close();

    return $row ?: null;
}

function stakeholder_portal_url(string $token): string
{
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $base = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/project_1/src'), '/\\');
    return $scheme . '://' . $host . $base . '/stakeholder_portal.php?token=' . urlencode($token);
}

function stakeholder_contract_url(string $token): string
{
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $base = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/project_1/src'), '/\\');
    return $scheme . '://' . $host . $base . '/stakeholder_contract.php?token=' . urlencode($token);
}

function portal_status_label(string $status): string
{
    $map = [
        'accepted' => 'Approved',
        'medium' => 'Under review',
        'rejected' => 'Rejected',
        'draft' => 'Waiting',
    ];
    return $map[strtolower($status)] ?? ucfirst($status);
}

function portal_status_class(string $status): string
{
    $map = [
        'accepted' => 'status-accepted',
        'medium' => 'status-medium',
        'rejected' => 'status-rejected',
        'draft' => 'status-draft',
    ];
    return $map[strtolower($status)] ?? 'status-draft';
}
