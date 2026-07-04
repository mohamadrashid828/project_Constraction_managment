<?php

/**
 * Single source of truth for "what can this user do". Always joins through
 * users.id (the session user_id) rather than trusting a cached role_id, so a
 * role change takes effect on the very next request.
 */
function get_user_permissions(mysqli $conn, int $userId): array
{
    ensure_core_permissions($conn);

    $stmt = $conn->prepare(
        'SELECT p.name FROM permissions p
         JOIN role_permissions rp ON p.id = rp.permission_id
         JOIN users u ON rp.role_id = u.role_id
         WHERE u.id = ?'
    );
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $names = array_column($stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'name');
    $stmt->close();
    return $names;
}

/**
 * Self-healing permission catalogue. Stakeholders and Analytics used to
 * piggyback on the project_settings permission; splitting them into their
 * own rows lets each tab be granted independently in the Roles &
 * Permissions UI, which groups checkboxes by whatever is in this table —
 * so a future page just needs its permission row inserted here, no UI
 * code changes required.
 */
function ensure_core_permissions(mysqli $conn): void
{
    $exists = $conn->query("SELECT 1 FROM permissions WHERE name = 'stakeholders' LIMIT 1");
    if ($exists && $exists->num_rows > 0) {
        return;
    }

    $insert = $conn->prepare("INSERT IGNORE INTO permissions (name, module, action_label, description) VALUES (?, ?, ?, ?)");
    $rows = [
        ['stakeholders', 'Stakeholders', 'Access', 'Can manage project stakeholders'],
        ['analytics', 'Analytics', 'Access', 'Can view the analytics/analysis page'],
    ];
    foreach ($rows as $r) {
        $insert->bind_param('ssss', $r[0], $r[1], $r[2], $r[3]);
        $insert->execute();
    }
    $insert->close();

    // Any role that already had project_settings implicitly had access to
    // these tabs — grant the split-out permissions so nobody loses access.
    $conn->query("
        INSERT IGNORE INTO role_permissions (role_id, permission_id)
        SELECT DISTINCT rp.role_id, p.id
        FROM role_permissions rp
        JOIN permissions old ON old.id = rp.permission_id AND old.name = 'project_settings'
        JOIN permissions p ON p.name IN ('stakeholders', 'analytics')
    ");
}
