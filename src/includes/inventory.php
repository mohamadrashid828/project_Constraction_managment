<?php

/**
 * Storage / Inventory module — schema bootstrap and shared helpers.
 *
 * Tables:
 *  - inventory_item_types        optional grouping for items (e.g. Cement, Tools)
 *  - inventory_items             master catalogue — each item is defined ONCE with
 *                                its name, its item type, its measure unit, and an
 *                                optional item code; Stock In/Out and requests just
 *                                select the item from a dropdown
 *  - inventory_purchase_requests requests to buy (pre-purchase workflow), with
 *                                priority + needed-by date for triage
 *  - inventory_purchases         purchase records (each one also stocks in); invoice PDF required
 *  - inventory_movements         the ledger that drives balance + history
 *
 * Current stock of an item = SUM(in) - SUM(out) over inventory_movements.
 * Location columns on an "out" movement are optional; is_project_wide = 1 marks
 * usage that applies to the whole project (to be allocated to apartments later).
 *
 * Permissions are granular (module='Inventory' rows in the permissions table):
 * inventory.view / .create / .edit / .delete / .approve / .reject /
 * .receive_stock / .issue_stock / .mark_delivered / .export.
 */

function ensure_inventory_schema(mysqli $conn): void
{
    // Renames must run before the CREATE TABLE IF NOT EXISTS calls below: on a
    // genuinely old install, inventory_categories still exists under its old
    // name, and CREATE TABLE IF NOT EXISTS inventory_item_types would create a
    // second, empty table instead of the rename actually happening.
    inventory_migrate_legacy_category_table($conn);

    $conn->query("CREATE TABLE IF NOT EXISTS inventory_item_types (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_by INT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_item_type_name (name)
    )");

    $conn->query("CREATE TABLE IF NOT EXISTS inventory_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        item_name VARCHAR(160) NOT NULL,
        item_type_id INT NULL,
        unit VARCHAR(30) NOT NULL DEFAULT 'pcs',
        item_code VARCHAR(60) NULL,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_by INT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_item_name (item_name),
        INDEX idx_item_active (is_active),
        INDEX idx_item_type (item_type_id),
        FOREIGN KEY (item_type_id) REFERENCES inventory_item_types(id)
    )");

    $conn->query("CREATE TABLE IF NOT EXISTS inventory_purchase_requests (
        id INT AUTO_INCREMENT PRIMARY KEY,
        item_name VARCHAR(160) NOT NULL,
        item_id INT NULL,
        quantity DECIMAL(14,3) NOT NULL DEFAULT 0,
        priority ENUM('low','medium','high','urgent') NOT NULL DEFAULT 'medium',
        needed_by_date DATE NULL,
        unit VARCHAR(30) NULL,
        notes TEXT NULL,
        status ENUM('pending','approved','rejected','fulfilled') NOT NULL DEFAULT 'pending',
        requested_by INT NULL,
        reviewed_by INT NULL,
        reviewed_at DATETIME NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_req_status (status),
        INDEX idx_req_item (item_id),
        FOREIGN KEY (item_id) REFERENCES inventory_items(id)
    )");

    $conn->query("CREATE TABLE IF NOT EXISTS inventory_purchases (
        id INT AUTO_INCREMENT PRIMARY KEY,
        item_id INT NOT NULL,
        quantity DECIMAL(14,3) NOT NULL DEFAULT 0,
        unit_price DECIMAL(14,2) NOT NULL DEFAULT 0,
        total_price DECIMAL(16,2) NOT NULL DEFAULT 0,
        vendor VARCHAR(160) NULL,
        ordered_by_name VARCHAR(160) NULL,
        invoice_no VARCHAR(80) NULL,
        invoice_file VARCHAR(255) NULL,
        purchase_date DATE NOT NULL,
        request_id INT NULL,
        notes TEXT NULL,
        created_by INT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_purch_item (item_id),
        INDEX idx_purch_date (purchase_date),
        FOREIGN KEY (item_id) REFERENCES inventory_items(id)
    )");

    // Location columns are deliberately NOT foreign keys so movement history
    // survives even if a building/floor/apartment is later removed.
    // person_name is denormalized here (not just on inventory_purchases) so
    // History/filtering never needs an extra join: it holds the orderer's name
    // for an 'in' row and the stock-taker's name for an 'out' row.
    $conn->query("CREATE TABLE IF NOT EXISTS inventory_movements (
        id INT AUTO_INCREMENT PRIMARY KEY,
        item_id INT NOT NULL,
        movement_type ENUM('in','out') NOT NULL,
        quantity DECIMAL(14,3) NOT NULL DEFAULT 0,
        unit_price DECIMAL(14,2) NULL,
        reference_type VARCHAR(30) NOT NULL DEFAULT 'manual',
        reference_id INT NULL,
        building_id INT NULL,
        floor_id INT NULL,
        apartment_id INT NULL,
        is_project_wide TINYINT(1) NOT NULL DEFAULT 0,
        notes TEXT NULL,
        moved_by INT NULL,
        person_name VARCHAR(160) NULL,
        movement_date DATE NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_mv_item (item_id),
        INDEX idx_mv_type (movement_type),
        INDEX idx_mv_date (movement_date),
        FOREIGN KEY (item_id) REFERENCES inventory_items(id)
    )");

    inventory_run_migrations($conn);
}

/**
 * Runs BEFORE the CREATE TABLE IF NOT EXISTS calls in ensure_inventory_schema():
 * on a genuinely old install, inventory_categories (and items.category free
 * text, even older) must be normalized and renamed to inventory_item_types
 * while it's still the only table with that data — otherwise CREATE TABLE IF
 * NOT EXISTS inventory_item_types would create a second, empty table instead
 * of this rename ever happening.
 */
function inventory_migrate_legacy_category_table(mysqli $conn): void
{
    $hasTable = fn(string $t) => $conn->query("SHOW TABLES LIKE '" . $conn->real_escape_string($t) . "'")->num_rows > 0;
    $hasCol = fn(string $t, string $c) => $hasTable($t) && $conn->query("SHOW COLUMNS FROM `$t` LIKE '$c'")->num_rows > 0;

    if (!$hasTable('inventory_items') || $hasTable('inventory_item_types')) {
        return; // fresh install, or already migrated
    }

    // Oldest possible shape: items.category was free text and no categorization
    // table existed at all yet. Create inventory_item_types directly.
    if (!$hasTable('inventory_categories') && $hasCol('inventory_items', 'category')) {
        $conn->query("CREATE TABLE inventory_item_types (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_by INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_item_type_name (name)
        )");
        $conn->query("INSERT IGNORE INTO inventory_item_types (name)
            SELECT DISTINCT CONCAT(UPPER(LEFT(TRIM(category),1)), SUBSTRING(TRIM(category),2))
            FROM inventory_items WHERE category IS NOT NULL AND TRIM(category) <> ''");
        if (!$hasCol('inventory_items', 'item_type_id')) {
            $conn->query("ALTER TABLE inventory_items ADD COLUMN item_type_id INT NULL AFTER category");
        }
        $conn->query("UPDATE inventory_items i
            JOIN inventory_item_types t ON t.name = CONCAT(UPPER(LEFT(TRIM(i.category),1)), SUBSTRING(TRIM(i.category),2))
            SET i.item_type_id = t.id
            WHERE i.category IS NOT NULL AND TRIM(i.category) <> '' AND i.item_type_id IS NULL");
        $conn->query("ALTER TABLE inventory_items DROP COLUMN category");
        return;
    }

    if (!$hasTable('inventory_categories')) {
        return; // nothing to migrate from
    }

    // A categories table exists (possibly alongside old free-text category text
    // predating it). Normalize any leftover free text into it, then rename.
    if ($hasCol('inventory_items', 'category')) {
        $conn->query("INSERT IGNORE INTO inventory_categories (name)
            SELECT DISTINCT CONCAT(UPPER(LEFT(TRIM(category),1)), SUBSTRING(TRIM(category),2))
            FROM inventory_items WHERE category IS NOT NULL AND TRIM(category) <> ''");
        if (!$hasCol('inventory_items', 'category_id')) {
            $conn->query("ALTER TABLE inventory_items ADD COLUMN category_id INT NULL AFTER category");
        }
        $conn->query("UPDATE inventory_items i
            JOIN inventory_categories c ON c.name = CONCAT(UPPER(LEFT(TRIM(i.category),1)), SUBSTRING(TRIM(i.category),2))
            SET i.category_id = c.id
            WHERE i.category IS NOT NULL AND TRIM(i.category) <> '' AND i.category_id IS NULL");
        $conn->query("ALTER TABLE inventory_items DROP COLUMN category");
    }

    $fk = $conn->query("SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS
        WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'inventory_items'
          AND CONSTRAINT_TYPE = 'FOREIGN KEY' AND CONSTRAINT_NAME LIKE '%categor%'");
    if ($fk && ($row = $fk->fetch_assoc())) {
        $conn->query("ALTER TABLE inventory_items DROP FOREIGN KEY `{$row['CONSTRAINT_NAME']}`");
    }
    $conn->query("RENAME TABLE inventory_categories TO inventory_item_types");
    if ($hasCol('inventory_items', 'category_id') && !$hasCol('inventory_items', 'item_type_id')) {
        $conn->query("ALTER TABLE inventory_items CHANGE category_id item_type_id INT NULL");
    }
}

/**
 * Self-healing migration covering every prior shape this module has had
 * during development (mirrors the column-by-column upgrade pattern used
 * elsewhere in this app, e.g. stakeholders.php). Every step is independently
 * guarded by SHOW COLUMNS/table-existence checks, so it is safe to run on a
 * fresh install (all no-ops), a fully-migrated install (all no-ops), or any
 * older intermediate shape (each step advances it one increment).
 */
function inventory_run_migrations(mysqli $conn): void
{
    $hasTable = fn(string $t) => $conn->query("SHOW TABLES LIKE '" . $conn->real_escape_string($t) . "'")->num_rows > 0;
    $hasCol = fn(string $t, string $c) => $conn->query("SHOW COLUMNS FROM `$t` LIKE '$c'")->num_rows > 0;
    $fkExists = function (string $table, string $fkName) use ($conn): bool {
        $r = $conn->query("SELECT 1 FROM information_schema.TABLE_CONSTRAINTS
            WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = '$table' AND CONSTRAINT_NAME = '$fkName'");
        return $r && $r->num_rows > 0;
    };

    // Belt-and-suspenders: item_type_id may still be missing if the legacy
    // pre-step above didn't apply (e.g. no inventory_categories ever existed).
    if (!$hasCol('inventory_items', 'item_type_id')) {
        $conn->query("ALTER TABLE inventory_items ADD COLUMN item_type_id INT NULL AFTER item_name");
    }

    // ── Unit lives on the ITEM. A mid-development iteration briefly moved it
    //    to the category/item-type; migrate any such install back. ──────────
    if (!$hasCol('inventory_items', 'unit')) {
        $conn->query("ALTER TABLE inventory_items ADD COLUMN unit VARCHAR(30) NOT NULL DEFAULT 'pcs' AFTER item_type_id");
        if ($hasCol('inventory_item_types', 'default_unit')) {
            $conn->query("UPDATE inventory_items i JOIN inventory_item_types t ON t.id = i.item_type_id SET i.unit = t.default_unit");
        }
    }
    if ($hasCol('inventory_item_types', 'default_unit')) {
        $conn->query("ALTER TABLE inventory_item_types DROP COLUMN default_unit");
    }

    // ── Items keep only: name, item type, unit, item code (+ housekeeping).
    //    Price/description were briefly present; drop them if still around. ─
    if ($hasCol('inventory_items', 'default_unit_price')) {
        $conn->query("ALTER TABLE inventory_items DROP COLUMN default_unit_price");
    }
    if ($hasCol('inventory_items', 'description')) {
        $conn->query("ALTER TABLE inventory_items DROP COLUMN description");
    }

    // item_type is optional grouping, never required.
    $itemTypeNullable = $conn->query("SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'inventory_items'
          AND COLUMN_NAME = 'item_type_id' AND IS_NULLABLE = 'YES'")->num_rows > 0;
    if (!$itemTypeNullable) {
        $conn->query("ALTER TABLE inventory_items MODIFY item_type_id INT NULL");
    }
    if (!$fkExists('inventory_items', 'fk_inv_item_type') && $hasTable('inventory_item_types')) {
        $conn->query("ALTER TABLE inventory_items ADD CONSTRAINT fk_inv_item_type FOREIGN KEY (item_type_id) REFERENCES inventory_item_types(id)");
    }

    // ── Purchase requests: item linkage, accountability, delivery workflow ──
    if (!$hasCol('inventory_purchase_requests', 'item_id')) {
        $conn->query("ALTER TABLE inventory_purchase_requests ADD COLUMN item_id INT NULL AFTER item_name");
        $conn->query("UPDATE inventory_purchase_requests r
            JOIN inventory_items i ON i.item_name = r.item_name
            SET r.item_id = i.id WHERE r.item_id IS NULL");
    }
    if (!$fkExists('inventory_purchase_requests', 'fk_inv_req_item')) {
        $conn->query("ALTER TABLE inventory_purchase_requests ADD CONSTRAINT fk_inv_req_item FOREIGN KEY (item_id) REFERENCES inventory_items(id)");
    }
    if (!$hasCol('inventory_purchase_requests', 'priority')) {
        $conn->query("ALTER TABLE inventory_purchase_requests ADD COLUMN priority ENUM('low','medium','high','urgent') NOT NULL DEFAULT 'medium' AFTER quantity");
    }
    if (!$hasCol('inventory_purchase_requests', 'needed_by_date')) {
        $conn->query("ALTER TABLE inventory_purchase_requests ADD COLUMN needed_by_date DATE NULL AFTER priority");
    }

    // ── Purchases/movements: invoice + accountability fields ────────────────
    if (!$hasCol('inventory_purchases', 'invoice_file')) {
        $conn->query("ALTER TABLE inventory_purchases ADD COLUMN invoice_file VARCHAR(255) NULL AFTER invoice_no");
    }
    if (!$hasCol('inventory_purchases', 'ordered_by_name')) {
        $conn->query("ALTER TABLE inventory_purchases ADD COLUMN ordered_by_name VARCHAR(160) NULL AFTER vendor");
    }
    if (!$hasCol('inventory_movements', 'person_name')) {
        $conn->query("ALTER TABLE inventory_movements ADD COLUMN person_name VARCHAR(160) NULL AFTER moved_by");
    }

    // ── Granular action-based permissions (replaces the old flat 'inventory'
    //    permission, which granted every action to anyone with any access). ─
    $hasGranular = $conn->query("SELECT 1 FROM permissions WHERE name = 'inventory.view'")->num_rows > 0;
    if (!$hasGranular) {
        if ($conn->query("SHOW COLUMNS FROM permissions LIKE 'module'")->num_rows === 0) {
            $conn->query("ALTER TABLE permissions ADD COLUMN module VARCHAR(50) NULL AFTER name");
        }
        if ($conn->query("SHOW COLUMNS FROM permissions LIKE 'action_label'")->num_rows === 0) {
            $conn->query("ALTER TABLE permissions ADD COLUMN action_label VARCHAR(50) NULL AFTER module");
        }
        $conn->query("INSERT INTO permissions (name, module, action_label, description) VALUES
            ('inventory.view', 'Inventory', 'View', 'View storage/inventory pages and data'),
            ('inventory.create', 'Inventory', 'Create', 'Add catalogue items/item types and submit purchase requests'),
            ('inventory.edit', 'Inventory', 'Edit', 'Edit catalogue items/item types'),
            ('inventory.delete', 'Inventory', 'Delete', 'Remove catalogue items/item types'),
            ('inventory.approve', 'Inventory', 'Approve', 'Approve a pending purchase request'),
            ('inventory.reject', 'Inventory', 'Reject', 'Reject a pending purchase request'),
            ('inventory.receive_stock', 'Inventory', 'Receive Stock', 'Record a purchase / stock in'),
            ('inventory.issue_stock', 'Inventory', 'Issue Stock', 'Issue stock out to a location'),
            ('inventory.mark_delivered', 'Inventory', 'Mark as Delivered', 'Mark an approved request as delivered to storage'),
            ('inventory.export', 'Inventory', 'Export', 'Export inventory data (CSV)')");
        // Any role that had the old flat 'inventory' permission keeps full access.
        $conn->query("INSERT IGNORE INTO role_permissions (role_id, permission_id)
            SELECT r.id, p.id FROM roles r
            JOIN role_permissions rp_old ON rp_old.role_id = r.id
            JOIN permissions p_old ON p_old.id = rp_old.permission_id AND p_old.name = 'inventory'
            JOIN permissions p ON p.module = 'Inventory'");
        $conn->query("DELETE FROM role_permissions WHERE permission_id IN (SELECT id FROM (SELECT id FROM permissions WHERE name = 'inventory') x)");
        $conn->query("DELETE FROM permissions WHERE name = 'inventory'");
    }
}

/** Normalise an optional id (location, item type, etc.): a positive int, or null for "none". */
function inventory_opt_int($v): ?int
{
    $n = (int) $v;
    return $n > 0 ? $n : null;
}

/**
 * Append a "column BETWEEN ? AND ?"-style date-range condition (either bound
 * present) to the running WHERE/params/types accumulators used by the
 * storage_filter.php dynamic queries. Shared so every date-filtered section
 * doesn't repeat the same from/to branching.
 */
function inventory_push_date_range(array &$where, array &$params, string &$types, string $column, ?string $from, ?string $to): void
{
    if ($from && preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
        $where[] = "$column >= ?";
        $params[] = $from;
        $types .= 's';
    }
    if ($to && preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
        $where[] = "$column <= ?";
        $params[] = $to;
        $types .= 's';
    }
}

/** Prepare + bind a variable-length parameter list + execute; returns the mysqli_result. */
function inventory_run_dynamic(mysqli $conn, string $sql, string $types, array $params)
{
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return false;
    }
    if ($types !== '') {
        $refs = [$types];
        foreach ($params as $i => $v) {
            $refs[] = &$params[$i];
        }
        call_user_func_array([$stmt, 'bind_param'], $refs);
    }
    $stmt->execute();
    return $stmt->get_result();
}

/**
 * Human label for a purchase request's status. The workflow is
 * pending -> approved|rejected, then an approved request is separately marked
 * delivered once the goods actually arrive at storage.
 */
function inventory_request_status_label(string $status): string
{
    $labels = [
        'pending' => 'pending',
        'approved' => 'approved_awaiting_delivery',
        'rejected' => 'rejected',
        'fulfilled' => 'delivered',
    ];
    if (function_exists('t')) {
        $key = $labels[$status] ?? null;
        if ($key) {
            return t($key, ucfirst(str_replace('_', ' ', $key)));
        }
    }
    $fallback = [
        'pending' => 'Pending',
        'approved' => 'Approved — Awaiting Delivery',
        'rejected' => 'Rejected',
        'fulfilled' => 'Delivered',
    ];
    return $fallback[$status] ?? ucfirst($status);
}

/** Human label + CSS suffix for a priority value. */
function inventory_priority_label(string $priority): string
{
    $labels = [
        'low' => 'low',
        'medium' => 'medium',
        'high' => 'high',
        'urgent' => 'urgent',
    ];
    if (function_exists('t')) {
        $key = $labels[$priority] ?? null;
        if ($key) {
            return t($key, ucfirst($priority));
        }
    }
    return ucfirst($priority);
}

/** Current stock quantity for one item (SUM in − SUM out). */
function inventory_item_balance(mysqli $conn, int $itemId): float
{
    $stmt = $conn->prepare(
        "SELECT COALESCE(SUM(CASE WHEN movement_type = 'in' THEN quantity ELSE -quantity END), 0) AS bal
         FROM inventory_movements WHERE item_id = ?"
    );
    $stmt->bind_param('i', $itemId);
    $stmt->execute();
    $bal = (float) ($stmt->get_result()->fetch_assoc()['bal'] ?? 0);
    $stmt->close();
    return $bal;
}

/**
 * Simple stock-status classification used by the Balance low/out-of-stock
 * filter. There is no per-item reorder threshold in this system (the Items
 * catalogue intentionally keeps only name/type/unit/code), so "low" uses one
 * fixed, transparent threshold rather than a per-item setting.
 */
const INVENTORY_LOW_STOCK_THRESHOLD = 10.0;

function inventory_stock_status(float $balance): string
{
    if ($balance <= 0) {
        return 'out';
    }
    if ($balance <= INVENTORY_LOW_STOCK_THRESHOLD) {
        return 'low';
    }
    return 'ok';
}

/**
 * Human label for a movement's location. Expects a row that may contain
 * is_project_wide plus the joined building_name / floor_name / apartment_number.
 * Returns an HTML-escaped string ready for output.
 */
function inventory_location_label(array $row): string
{
    if (!empty($row['is_project_wide'])) {
        return '<span class="loc-project-wide">' . htmlspecialchars(function_exists('t') ? t('project_wide', 'Project-wide') : 'Project-wide') . '</span>';
    }
    $parts = [];
    if (!empty($row['building_name'])) { $parts[] = htmlspecialchars($row['building_name']); }
    if (!empty($row['floor_name'])) { $parts[] = htmlspecialchars($row['floor_name']); }
    if (!empty($row['apartment_number'])) { $parts[] = (function_exists('t') ? t('apt', 'Apt') : 'Apt') . ' ' . htmlspecialchars($row['apartment_number']); }
    return $parts ? implode(' <i class="fas fa-angle-right"></i> ', $parts) : '<span class="loc-none">—</span>';
}

/** Absolute path to the (web-inaccessible) invoice storage directory, creating it + its deny-all guard if needed. */
function inventory_invoice_dir(): string
{
    $dir = dirname(__DIR__, 2) . '/data/invoices/';
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
 * Validate and store an uploaded invoice as a PDF. Checks both the reported
 * MIME type and the file's actual magic bytes (defense-in-depth against a
 * renamed/spoofed upload), enforces a size cap, and writes it under a
 * randomly generated name so the on-disk filename never comes from user input.
 *
 * @return array{ok: bool, message: string, filename: ?string}
 */
function inventory_store_invoice(array $file): array
{
    if (empty($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return ['ok' => false, 'message' => 'An invoice PDF is required for every purchase.', 'filename' => null];
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'message' => 'The invoice file failed to upload. Please try again.', 'filename' => null];
    }

    $maxBytes = 10 * 1024 * 1024; // 10MB
    if ($file['size'] <= 0 || $file['size'] > $maxBytes) {
        return ['ok' => false, 'message' => 'Invoice file must be between 1 byte and 10MB.', 'filename' => null];
    }
    if (!is_uploaded_file($file['tmp_name'])) {
        return ['ok' => false, 'message' => 'Invalid upload.', 'filename' => null];
    }

    // Magic-byte check: a real PDF starts with "%PDF-".
    $handle = fopen($file['tmp_name'], 'rb');
    $header = $handle ? fread($handle, 5) : '';
    if ($handle) { fclose($handle); }
    if ($header !== '%PDF-') {
        return ['ok' => false, 'message' => 'Only PDF invoices are accepted.', 'filename' => null];
    }

    // MIME check via the file's actual content (not the client-supplied type).
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = $finfo ? finfo_file($finfo, $file['tmp_name']) : false;
        if ($finfo) { finfo_close($finfo); }
        if ($mime !== 'application/pdf') {
            return ['ok' => false, 'message' => 'Only PDF invoices are accepted.', 'filename' => null];
        }
    }

    $dir = inventory_invoice_dir();
    $filename = 'inv_' . bin2hex(random_bytes(16)) . '.pdf';
    if (!move_uploaded_file($file['tmp_name'], $dir . $filename)) {
        return ['ok' => false, 'message' => 'Could not save the invoice file.', 'filename' => null];
    }

    return ['ok' => true, 'message' => '', 'filename' => $filename];
}
