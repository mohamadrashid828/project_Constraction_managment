<?php

/**
 * Shared renderer for the dependent <select> AJAX endpoints (floors,
 * apartments, and any future lookup of the same shape). Requires an
 * authenticated session before touching the database, then echoes a
 * placeholder <option> followed by one <option> per row.
 *
 * @param string $postKey   the $_POST key holding the parent id (e.g. 'building_id')
 * @param string $sql       a SELECT with exactly one '?' placeholder, returning $idCol and $labelCol
 * @param string $placeholder text for the leading empty option
 */
function render_options_endpoint(
    mysqli $conn,
    string $postKey,
    string $sql,
    string $idCol,
    string $labelCol,
    string $placeholder
): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (empty($_SESSION['user_id'])) {
        http_response_code(401);
        echo '<option value="">Not authorized</option>';
        return;
    }

    echo '<option value="">' . htmlspecialchars($placeholder) . '</option>';

    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST[$postKey])) {
        return;
    }

    $parentId = (int) $_POST[$postKey];
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return;
    }
    $stmt->bind_param('i', $parentId);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        echo '<option value="' . (int) $row[$idCol] . '">'
            . htmlspecialchars((string) $row[$labelCol]) . '</option>';
    }
    $stmt->close();
}
