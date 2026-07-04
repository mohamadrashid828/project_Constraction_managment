<?php
session_start();
require_once '../config.php';
require_once 'includes/permissions.php';
require_once 'includes/csrf.php';
require_once 'includes/inventory.php';

header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$user_id = (int) $_SESSION['user_id'];
$permissions = get_user_permissions($conn, $user_id);

// Base gate: every action here requires at least View. Each action below
// additionally requires the specific permission for what it does, so
// e.g. a requester with only View+Create can never approve or issue stock.
if (!in_array('inventory.view', $permissions, true)) {
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}
if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
    echo json_encode(['success' => false, 'message' => 'Invalid or expired request. Please refresh.']);
    exit;
}

function require_inventory_permission(array $permissions, string $perm): void
{
    if (!in_array($perm, $permissions, true)) {
        echo json_encode(['success' => false, 'message' => 'You do not have permission to do that.']);
        exit;
    }
}

ensure_inventory_schema($conn);

$action = $_POST['action'] ?? '';

switch ($action) {

    // ── Items: Item Name, Unit of Measure, Item Code only ──────────────────
    case 'save_item': {
        $item_id = (int) ($_POST['item_id'] ?? 0);
        require_inventory_permission($permissions, $item_id > 0 ? 'inventory.edit' : 'inventory.create');

        $name = trim($_POST['item_name'] ?? '');
        $unit = trim($_POST['unit'] ?? '');
        $code = trim($_POST['item_code'] ?? '');
        if ($name === '' || $unit === '') {
            echo json_encode(['success' => false, 'message' => 'Item name and its unit of measure are required.']);
            exit;
        }

        $dup = $conn->prepare("SELECT id FROM inventory_items WHERE item_name = ? AND id != ? LIMIT 1");
        $dup->bind_param('si', $name, $item_id);
        $dup->execute();
        if ($dup->get_result()->fetch_assoc()) {
            $dup->close();
            echo json_encode(['success' => false, 'message' => 'An item named "' . $name . '" already exists.']);
            exit;
        }
        $dup->close();

        if ($item_id > 0) {
            $stmt = $conn->prepare("UPDATE inventory_items SET item_name = ?, unit = ?, item_code = ? WHERE id = ?");
            $stmt->bind_param('sssi', $name, $unit, $code, $item_id);
            $ok = $stmt->execute();
            $stmt->close();
            echo json_encode($ok ? ['success' => true, 'message' => 'Item updated.'] : ['success' => false, 'message' => 'Could not update item.']);
        } else {
            $stmt = $conn->prepare("INSERT INTO inventory_items (item_name, unit, item_code, created_by) VALUES (?, ?, ?, ?)");
            $stmt->bind_param('sssi', $name, $unit, $code, $user_id);
            $ok = $stmt->execute();
            $stmt->close();
            echo json_encode($ok ? ['success' => true, 'message' => 'Item added.'] : ['success' => false, 'message' => 'Could not add item.']);
        }
        break;
    }

    case 'delete_item': {
        require_inventory_permission($permissions, 'inventory.delete');
        $item_id = (int) ($_POST['item_id'] ?? 0);
        if ($item_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid item.']);
            exit;
        }
        // Soft delete: purchase/movement/request history keeps referencing this
        // item; it just stops appearing in the active catalogue/dropdowns.
        $stmt = $conn->prepare("UPDATE inventory_items SET is_active = 0 WHERE id = ?");
        $stmt->bind_param('i', $item_id);
        $ok = $stmt->execute();
        $stmt->close();
        echo json_encode($ok ? ['success' => true, 'message' => 'Item removed.'] : ['success' => false, 'message' => 'Could not remove item.']);
        break;
    }

    // ── Stock In (purchase an existing catalogue item; invoice PDF required) ─
    case 'stock_in': {
        require_inventory_permission($permissions, 'inventory.receive_stock');

        $item_id    = (int) ($_POST['item_id'] ?? 0);
        $quantity   = (float) ($_POST['quantity'] ?? 0);
        $unit_price = (float) ($_POST['unit_price'] ?? 0);
        $vendor     = trim($_POST['vendor'] ?? '');
        $ordered_by_name = trim($_POST['ordered_by_name'] ?? '');
        $invoice_no = trim($_POST['invoice_no'] ?? '');
        $date       = trim($_POST['purchase_date'] ?? '');
        $notes      = trim($_POST['notes'] ?? '');
        $request_id = inventory_opt_int($_POST['request_id'] ?? 0);

        if ($item_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Please select an item from the catalogue.']);
            exit;
        }
        if ($ordered_by_name === '') {
            echo json_encode(['success' => false, 'message' => 'Please enter the full name of who ordered this.']);
            exit;
        }
        $itemChk = $conn->prepare("SELECT id FROM inventory_items WHERE id = ? AND is_active = 1 LIMIT 1");
        $itemChk->bind_param('i', $item_id);
        $itemChk->execute();
        $itemExists = (bool) $itemChk->get_result()->fetch_assoc();
        $itemChk->close();
        if (!$itemExists) {
            echo json_encode(['success' => false, 'message' => 'Item not found. Add it in Items management first.']);
            exit;
        }
        if ($quantity <= 0) {
            echo json_encode(['success' => false, 'message' => 'Quantity must be greater than 0.']);
            exit;
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $date = date('Y-m-d');
        }
        if ($unit_price < 0) { $unit_price = 0; }
        $total_price = round($quantity * $unit_price, 2);

        // Invoice PDF is mandatory for every purchase.
        $invoiceResult = inventory_store_invoice($_FILES['invoice_file'] ?? []);
        if (!$invoiceResult['ok']) {
            echo json_encode(['success' => false, 'message' => $invoiceResult['message']]);
            exit;
        }
        $invoice_file = $invoiceResult['filename'];

        $conn->begin_transaction();
        try {
            $p = $conn->prepare("INSERT INTO inventory_purchases
                (item_id, quantity, unit_price, total_price, vendor, ordered_by_name, invoice_no, invoice_file, purchase_date, request_id, notes, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $p->bind_param('idddsssssisi', $item_id, $quantity, $unit_price, $total_price, $vendor, $ordered_by_name, $invoice_no, $invoice_file, $date, $request_id, $notes, $user_id);
            if (!$p->execute()) { throw new Exception('purchase insert failed'); }
            $purchase_id = (int) $conn->insert_id;
            $p->close();

            $m = $conn->prepare("INSERT INTO inventory_movements
                (item_id, movement_type, quantity, unit_price, reference_type, reference_id, notes, moved_by, person_name, movement_date)
                VALUES (?, 'in', ?, ?, 'purchase', ?, ?, ?, ?, ?)");
            $m->bind_param('iddisiss', $item_id, $quantity, $unit_price, $purchase_id, $notes, $user_id, $ordered_by_name, $date);
            if (!$m->execute()) { throw new Exception('movement insert failed'); }
            $m->close();

            if ($request_id) {
                $r = $conn->prepare("UPDATE inventory_purchase_requests SET status = 'fulfilled', reviewed_by = ?, reviewed_at = NOW() WHERE id = ? AND status <> 'rejected'");
                $r->bind_param('ii', $user_id, $request_id);
                $r->execute();
                $r->close();
            }

            $conn->commit();
            echo json_encode(['success' => true, 'message' => 'Stock received into storage.']);
        } catch (Throwable $e) {
            $conn->rollback();
            // Remove the just-uploaded file since the transaction didn't stick.
            @unlink(inventory_invoice_dir() . $invoice_file);
            error_log('storage stock_in failed: ' . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Could not record the purchase. Please try again.']);
        }
        break;
    }

    // ── Stock Out (usage, with optional/project-wide location) ────────────
    case 'stock_out': {
        require_inventory_permission($permissions, 'inventory.issue_stock');

        $item_id   = (int) ($_POST['item_id'] ?? 0);
        $quantity  = (float) ($_POST['quantity'] ?? 0);
        $date      = trim($_POST['movement_date'] ?? '');
        $notes     = trim($_POST['notes'] ?? '');
        $person_name = trim($_POST['person_name'] ?? '');
        $projectWide = !empty($_POST['is_project_wide']) ? 1 : 0;
        $building_id  = $projectWide ? null : inventory_opt_int($_POST['building_id'] ?? 0);
        $floor_id     = $projectWide ? null : inventory_opt_int($_POST['floor_id'] ?? 0);
        $apartment_id = $projectWide ? null : inventory_opt_int($_POST['apartment_id'] ?? 0);

        if ($item_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Please choose an item.']);
            exit;
        }
        if ($person_name === '') {
            echo json_encode(['success' => false, 'message' => 'Please enter the full name of who is taking the stock.']);
            exit;
        }
        if ($quantity <= 0) {
            echo json_encode(['success' => false, 'message' => 'Quantity must be greater than 0.']);
            exit;
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $date = date('Y-m-d');
        }

        $conn->begin_transaction();
        try {
            $balStmt = $conn->prepare("SELECT COALESCE(SUM(CASE WHEN movement_type='in' THEN quantity ELSE -quantity END),0) AS bal
                                       FROM inventory_movements WHERE item_id = ? FOR UPDATE");
            $balStmt->bind_param('i', $item_id);
            $balStmt->execute();
            $balance = (float) ($balStmt->get_result()->fetch_assoc()['bal'] ?? 0);
            $balStmt->close();

            if ($quantity > $balance + 1e-9) {
                $conn->rollback();
                echo json_encode(['success' => false, 'message' => 'Not enough stock. Available: ' . rtrim(rtrim(number_format($balance, 3), '0'), '.')]);
                exit;
            }

            $m = $conn->prepare("INSERT INTO inventory_movements
                (item_id, movement_type, quantity, reference_type, building_id, floor_id, apartment_id, is_project_wide, notes, moved_by, person_name, movement_date)
                VALUES (?, 'out', ?, 'usage', ?, ?, ?, ?, ?, ?, ?, ?)");
            $m->bind_param('idiiiisiss', $item_id, $quantity, $building_id, $floor_id, $apartment_id, $projectWide, $notes, $user_id, $person_name, $date);
            if (!$m->execute()) { throw new Exception('out movement failed'); }
            $m->close();

            $conn->commit();
            echo json_encode(['success' => true, 'message' => 'Stock issued.']);
        } catch (Throwable $e) {
            $conn->rollback();
            error_log('storage stock_out failed: ' . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Could not issue stock. Please try again.']);
        }
        break;
    }

    // ── Purchase requests ──────────────────────────────────────────────────
    case 'create_request': {
        require_inventory_permission($permissions, 'inventory.create');

        $item_id   = inventory_opt_int($_POST['item_id'] ?? 0);
        $item_name = trim($_POST['item_name'] ?? '');
        $unit      = trim($_POST['unit'] ?? '');
        $quantity  = (float) ($_POST['quantity'] ?? 0);
        $priority  = trim($_POST['priority'] ?? 'medium');
        if (!in_array($priority, ['low', 'medium', 'high', 'urgent'], true)) {
            $priority = 'medium';
        }
        $needed_by = trim($_POST['needed_by_date'] ?? '');
        $needed_by = preg_match('/^\d{4}-\d{2}-\d{2}$/', $needed_by) ? $needed_by : null;
        $notes     = trim($_POST['notes'] ?? '');

        // If an existing catalogue item was picked, use its real name and unit
        // (the free-text fields are only for a not-yet-catalogued "Other" item).
        if ($item_id) {
            $itemStmt = $conn->prepare("SELECT item_name, unit
                FROM inventory_items
                WHERE id = ? AND is_active = 1 LIMIT 1");
            $itemStmt->bind_param('i', $item_id);
            $itemStmt->execute();
            $itemRow = $itemStmt->get_result()->fetch_assoc();
            $itemStmt->close();
            if (!$itemRow) {
                echo json_encode(['success' => false, 'message' => 'Selected item not found.']);
                exit;
            }
            $item_name = $itemRow['item_name'];
            $unit = $itemRow['unit'];
        }

        if ($item_name === '' || $quantity <= 0) {
            echo json_encode(['success' => false, 'message' => 'Choose an item (or name a new one) and a quantity greater than 0.']);
            exit;
        }
        $stmt = $conn->prepare("INSERT INTO inventory_purchase_requests
            (item_name, item_id, quantity, priority, needed_by_date, unit, notes, requested_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param('sidssssi', $item_name, $item_id, $quantity, $priority, $needed_by, $unit, $notes, $user_id);
        $ok = $stmt->execute();
        $stmt->close();
        echo json_encode($ok
            ? ['success' => true, 'message' => 'Purchase request submitted.']
            : ['success' => false, 'message' => 'Could not submit request.']);
        break;
    }

    case 'review_request': {
        $request_id = (int) ($_POST['request_id'] ?? 0);
        $decision   = $_POST['decision'] ?? '';
        if ($request_id <= 0 || !in_array($decision, ['approved', 'rejected'], true)) {
            echo json_encode(['success' => false, 'message' => 'Invalid request or decision.']);
            exit;
        }
        // Approve and reject are independently grantable — a user might be
        // allowed to reject bad requests without being trusted to approve spend.
        require_inventory_permission($permissions, $decision === 'approved' ? 'inventory.approve' : 'inventory.reject');

        $stmt = $conn->prepare("UPDATE inventory_purchase_requests SET status = ?, reviewed_by = ?, reviewed_at = NOW() WHERE id = ? AND status = 'pending'");
        $stmt->bind_param('sii', $decision, $user_id, $request_id);
        $stmt->execute();
        $changed = $stmt->affected_rows > 0;
        $stmt->close();
        echo json_encode($changed
            ? ['success' => true, 'message' => 'Request ' . $decision . '.']
            : ['success' => false, 'message' => 'Request was already reviewed.']);
        break;
    }

    // Step 2 of the workflow: an already-approved request is separately marked
    // delivered once the goods actually arrive at storage. Restricted to store
    // staff (inventory.mark_delivered), independent of who approved it.
    case 'mark_delivered': {
        require_inventory_permission($permissions, 'inventory.mark_delivered');

        $request_id = (int) ($_POST['request_id'] ?? 0);
        if ($request_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid request.']);
            exit;
        }
        $stmt = $conn->prepare("UPDATE inventory_purchase_requests SET status = 'fulfilled', reviewed_by = ?, reviewed_at = NOW() WHERE id = ? AND status = 'approved'");
        $stmt->bind_param('ii', $user_id, $request_id);
        $stmt->execute();
        $changed = $stmt->affected_rows > 0;
        $stmt->close();
        echo json_encode($changed
            ? ['success' => true, 'message' => 'Marked as delivered.']
            : ['success' => false, 'message' => 'Request must be approved first.']);
        break;
    }

    default:
        echo json_encode(['success' => false, 'message' => 'Unknown action.']);
}
