<?php
session_start();
require_once '../config.php';
require_once 'includes/permissions.php';
require_once 'includes/csrf.php';
require_once 'includes/inventory.php';

if (empty($_SESSION['user_id'])) {
    header('Location: index.html');
    exit;
}

$user_id = (int) $_SESSION['user_id'];
$permissions = get_user_permissions($conn, $user_id);
if (!in_array('inventory.view', $permissions, true)) {
    header('Location: dashboard.php?error=access_denied');
    exit;
}
// Convenience flags for showing/hiding each action's UI. The AJAX endpoint
// enforces these independently server-side — this is only about not showing
// a control the user isn't allowed to use.
$can = [
    'create' => in_array('inventory.create', $permissions, true),
    'edit' => in_array('inventory.edit', $permissions, true),
    'delete' => in_array('inventory.delete', $permissions, true),
    'approve' => in_array('inventory.approve', $permissions, true),
    'reject' => in_array('inventory.reject', $permissions, true),
    'receive_stock' => in_array('inventory.receive_stock', $permissions, true),
    'issue_stock' => in_array('inventory.issue_stock', $permissions, true),
    'mark_delivered' => in_array('inventory.mark_delivered', $permissions, true),
    'export' => in_array('inventory.export', $permissions, true),
];

ensure_inventory_schema($conn);

$csrf = csrf_token();
$today = date('Y-m-d');

// ── Items with live balance and average purchase price. Kept fields: name,
//    unit of measure, item code (+ housekeeping). ──────────────────────────
$items = [];
$itemsRes = $conn->query("
    SELECT i.*,
           COALESCE(SUM(CASE WHEN m.movement_type = 'in' THEN m.quantity ELSE -m.quantity END), 0) AS balance,
           (SELECT COALESCE(AVG(p.unit_price), 0) FROM inventory_purchases p WHERE p.item_id = i.id AND p.unit_price > 0) AS avg_price
    FROM inventory_items i
    LEFT JOIN inventory_movements m ON m.item_id = i.id
    WHERE i.is_active = 1
    GROUP BY i.id
    ORDER BY i.item_name
");
while ($itemsRes && $r = $itemsRes->fetch_assoc()) { $items[] = $r; }

$totalItems = count($items);
$totalValue = 0.0;
$outOfStock = 0;
$lowStock = 0;
foreach ($items as $it) {
    $totalValue += (float) $it['balance'] * (float) $it['avg_price'];
    $status = inventory_stock_status((float) $it['balance']);
    if ($status === 'out') { $outOfStock++; }
    if ($status === 'low') { $lowStock++; }
}
$pendingRequestsCount = (int) ($conn->query("SELECT COUNT(*) c FROM inventory_purchase_requests WHERE status = 'pending'")->fetch_assoc()['c'] ?? 0);

// Approved-but-not-yet-delivered requests, for the optional "fulfilling
// request" link on Stock In — only an already-approved request qualifies.
$approvedRequests = [];
$arRes = $conn->query("SELECT r.*, u.username AS requester FROM inventory_purchase_requests r LEFT JOIN users u ON u.id = r.requested_by WHERE r.status = 'approved' ORDER BY r.created_at DESC");
while ($arRes && $ar = $arRes->fetch_assoc()) { $approvedRequests[] = $ar; }

$buildings = [];
$bRes = $conn->query("SELECT id, building_name FROM buildings ORDER BY building_name");
while ($bRes && $b = $bRes->fetch_assoc()) { $buildings[] = $b; }
$usersList = $conn->query("SELECT id, username FROM users ORDER BY username");

function money($v) { return '$' . number_format((float) $v, 2); }
function qty_fmt($v) { return rtrim(rtrim(number_format((float) $v, 3, '.', ','), '0'), '.'); }

$pageTitle = t('storage_inventory', 'Storage & Inventory') . ' - ' . t('construction_management', 'Construction Management');
$pageCss = 'storage.css';
$activePage = 'storage';
require_once 'partials/header.php';
?>
<div id="storage-page" class="dashboard-container">
<?php require_once 'partials/sidebar.php'; ?>

<main class="main-content">
    <header class="page-header">
        <h1><i class="fas fa-boxes-stacked"></i> <?php echo htmlspecialchars(t('storage_inventory', 'Storage & Inventory')); ?></h1>
        <div class="user-info">
            <span><?php echo htmlspecialchars(t('welcome', 'Welcome')); ?>, <?php echo htmlspecialchars($_SESSION['username']); ?></span>
        </div>
    </header>

    <div class="content-wrapper storage-wrapper">

        <!-- Stat cards -->
        <div class="storage-stats">
            <div class="stg-stat">
                <div class="stg-stat-icon icon-items"><i class="fas fa-box"></i></div>
                <div class="stg-stat-body"><span class="stg-stat-value"><?php echo $totalItems; ?></span><span class="stg-stat-label"><?php echo htmlspecialchars(t('items_in_catalogue', 'Items in catalogue')); ?></span></div>
            </div>
            <div class="stg-stat">
                <div class="stg-stat-icon icon-value"><i class="fas fa-sack-dollar"></i></div>
                <div class="stg-stat-body"><span class="stg-stat-value"><?php echo money($totalValue); ?></span><span class="stg-stat-label"><?php echo htmlspecialchars(t('estimated_stock_value', 'Estimated stock value')); ?></span></div>
            </div>
            <div class="stg-stat">
                <div class="stg-stat-icon icon-pending"><i class="fas fa-clipboard-list"></i></div>
                <div class="stg-stat-body"><span class="stg-stat-value"><?php echo $pendingRequestsCount; ?></span><span class="stg-stat-label"><?php echo htmlspecialchars(t('pending_requests', 'Pending requests')); ?></span></div>
            </div>
            <div class="stg-stat">
                <div class="stg-stat-icon icon-low"><i class="fas fa-battery-quarter"></i></div>
                <div class="stg-stat-body"><span class="stg-stat-value"><?php echo $lowStock; ?></span><span class="stg-stat-label"><?php echo htmlspecialchars(t('low_stock', 'Low stock')); ?></span></div>
            </div>
            <div class="stg-stat">
                <div class="stg-stat-icon icon-empty"><i class="fas fa-triangle-exclamation"></i></div>
                <div class="stg-stat-body"><span class="stg-stat-value"><?php echo $outOfStock; ?></span><span class="stg-stat-label"><?php echo htmlspecialchars(t('out_of_stock', 'Out of stock')); ?></span></div>
            </div>
        </div>

        <!-- Tabs -->
        <div class="storage-tabs">
            <button class="stg-tab active" data-tab="balance"><i class="fas fa-scale-balanced"></i> <?php echo htmlspecialchars(t('stock_balance', 'Stock Balance')); ?></button>
            <button class="stg-tab" data-tab="items"><i class="fas fa-box-open"></i> <?php echo htmlspecialchars(t('items', 'Items')); ?></button>
            <?php if ($can['receive_stock']): ?><button class="stg-tab" data-tab="stockin"><i class="fas fa-arrow-down-to-line"></i> <?php echo htmlspecialchars(t('stock_in', 'Stock In')); ?></button><?php endif; ?>
            <?php if ($can['issue_stock']): ?><button class="stg-tab" data-tab="stockout"><i class="fas fa-arrow-up-from-line"></i> <?php echo htmlspecialchars(t('stock_out', 'Stock Out')); ?></button><?php endif; ?>
            <button class="stg-tab" data-tab="requests"><i class="fas fa-clipboard-check"></i> <?php echo htmlspecialchars(t('requests', 'Requests')); ?><?php if ($pendingRequestsCount): ?> <span class="stg-badge-count"><?php echo $pendingRequestsCount; ?></span><?php endif; ?></button>
            <button class="stg-tab" data-tab="history"><i class="fas fa-clock-rotate-left"></i> <?php echo htmlspecialchars(t('history', 'History')); ?></button>
        </div>

        <!-- ═══ BALANCE ═══ -->
        <section class="stg-panel active" id="tab-balance">
            <div class="stg-panel-head">
                <h2><i class="fas fa-scale-balanced"></i> <?php echo htmlspecialchars(t('current_stock', 'Current Stock')); ?></h2>
            </div>

            <form class="stg-filter-bar" id="filter-balance">
                <input type="text" name="q" placeholder="<?php echo htmlspecialchars(t('search_item_name', 'Search item name…')); ?>">
                <select name="stock_status">
                    <option value=""><?php echo htmlspecialchars(t('all_stock_levels', 'All stock levels')); ?></option>
                    <option value="ok"><?php echo htmlspecialchars(t('in_stock', 'In Stock')); ?></option>
                    <option value="low"><?php echo htmlspecialchars(t('low_stock_label', 'Low Stock')); ?></option>
                    <option value="out"><?php echo htmlspecialchars(t('out_of_stock_label', 'Out of Stock')); ?></option>
                </select>
            </form>

            <div class="stg-table-wrap">
                <table class="data-table stg-table">
                    <thead>
                        <tr><th>Item</th><th>Unit</th><th class="num">In Stock</th><th class="num">Avg. Price</th><th class="num">Value</th></tr>
                    </thead>
                    <tbody id="balance-tbody">
                    <?php if (!$items): ?>
                        <tr><td colspan="5" class="stg-empty">No items yet. Add one in the Items tab.</td></tr>
                    <?php else: foreach ($items as $it):
                        $bal = (float) $it['balance']; $val = $bal * (float) $it['avg_price'];
                        $status = inventory_stock_status($bal); ?>
                        <tr>
                            <td class="stg-strong"><?php echo htmlspecialchars($it['item_name']); ?></td>
                            <td><?php echo htmlspecialchars($it['unit']); ?></td>
                            <td class="num">
                                <span class="stg-qty stg-qty-<?php echo $status; ?>"><?php echo qty_fmt($bal); ?></span>
                                <?php if ($status !== 'ok'): ?><span class="stg-stock-badge stg-stock-badge-<?php echo $status; ?>"><?php echo $status === 'out' ? 'Out of Stock' : 'Low'; ?></span><?php endif; ?>
                            </td>
                            <td class="num"><?php echo money($it['avg_price']); ?></td>
                            <td class="num"><?php echo money($val); ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- ═══ ITEMS ═══ -->
        <section class="stg-panel" id="tab-items">
            <div class="stg-grid">
                <?php if ($can['create'] || $can['edit']): ?>
                <div class="panel-card stg-form-card">
                    <div class="card-header"><h2><i class="fas fa-box-open"></i> <?php echo htmlspecialchars(t('add_item', 'Add Item')); ?></h2></div>
                    <form class="settings-form storage-ajax-form" id="item-form" data-refresh>
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="action" value="save_item">
                        <input type="hidden" name="item_id" value="">
                        <div class="form-group"><label><?php echo htmlspecialchars(t('item_name', 'Item Name')); ?></label><input type="text" name="item_name" placeholder="e.g., Cement, Block, Cable 2.5mm" required></div>
                        <div class="form-group">
                            <label><?php echo htmlspecialchars(t('unit_of_measure', 'Unit of Measure')); ?></label>
                            <input type="text" name="unit" placeholder="e.g., bag, m², pcs" required>
                        </div>
                        <div class="form-group"><label><?php echo htmlspecialchars(t('item_code', 'Item Code')); ?> <span class="stg-optional"><?php echo htmlspecialchars(t('optional', '(optional)')); ?></span></label><input type="text" name="item_code"></div>
                        <div class="stg-form-actions">
                            <button class="btn btn-primary" type="submit"><i class="fas fa-save"></i> <span id="item-form-btn-label"><?php echo htmlspecialchars(t('add_item', 'Add Item')); ?></span></button>
                            <button class="btn btn-secondary stg-cancel-edit" type="button" data-form="item-form" style="display:none;"><?php echo htmlspecialchars(t('cancel', 'Cancel')); ?></button>
                        </div>
                    </form>
                </div>
                <?php endif; ?>
                <div class="stg-list-card">
                    <h3 class="stg-list-title"><i class="fas fa-list"></i> <?php echo htmlspecialchars(t('catalogue', 'Catalogue')); ?></h3>
                    <div class="stg-table-wrap">
                        <table class="data-table stg-table">
                            <thead><tr><th><?php echo htmlspecialchars(t('item', 'Item')); ?></th><th><?php echo htmlspecialchars(t('unit', 'Unit')); ?></th><th><?php echo htmlspecialchars(t('code', 'Code')); ?></th><?php if ($can['edit'] || $can['delete']): ?><th><?php echo htmlspecialchars(t('actions', 'Actions')); ?></th><?php endif; ?></tr></thead>
                            <tbody>
                            <?php if (!$items): ?>
                                <tr><td colspan="4" class="stg-empty"><?php echo htmlspecialchars(t('no_items_yet_add', 'No items yet. Add one in the Items tab.')); ?></td></tr>
                            <?php else: foreach ($items as $it): ?>
                                <tr>
                                    <td class="stg-strong"><?php echo htmlspecialchars($it['item_name']); ?></td>
                                    <td><?php echo htmlspecialchars($it['unit']); ?></td>
                                    <td><?php echo $it['item_code'] ? htmlspecialchars($it['item_code']) : '<span class="loc-none">—</span>'; ?></td>
                                    <?php if ($can['edit'] || $can['delete']): ?>
                                    <td>
                                        <?php if ($can['edit']): ?>
                                        <button type="button" class="stg-icon-btn stg-edit-item"
                                            data-id="<?php echo (int) $it['id']; ?>"
                                            data-name="<?php echo htmlspecialchars($it['item_name'], ENT_QUOTES); ?>"
                                            data-unit="<?php echo htmlspecialchars($it['unit'], ENT_QUOTES); ?>"
                                            data-code="<?php echo htmlspecialchars($it['item_code'] ?? '', ENT_QUOTES); ?>"
                                            title="<?php echo htmlspecialchars(t('edit', 'Edit')); ?>"><i class="fas fa-edit"></i></button>
                                        <?php endif; ?>
                                        <?php if ($can['delete']): ?>
                                        <button type="button" class="stg-icon-btn stg-reject stg-delete-item" data-id="<?php echo (int) $it['id']; ?>" title="<?php echo htmlspecialchars(t('delete', 'Delete')); ?>"><i class="fas fa-trash"></i></button>
                                        <?php endif; ?>
                                    </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </section>

        <?php if ($can['receive_stock']): ?>
        <!-- ═══ STOCK IN ═══ -->
        <section class="stg-panel" id="tab-stockin">
            <div class="stg-grid">
                <div class="panel-card stg-form-card">
                    <div class="card-header"><h2><i class="fas fa-arrow-down-to-line"></i> <?php echo htmlspecialchars(t('receive_stock_purchase', 'Receive Stock (Purchase)')); ?></h2></div>
                    <form class="settings-form storage-ajax-form" id="stock-in-form" enctype="multipart/form-data" data-refresh>
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="action" value="stock_in">

                        <div class="form-group">
                            <label><?php echo htmlspecialchars(t('item', 'Item')); ?></label>
                            <select name="item_id" id="stockin-item" required>
                                <option value=""><?php echo htmlspecialchars(t('select_item', 'Select item…')); ?></option>
                                <?php foreach ($items as $it): ?>
                                    <option value="<?php echo (int) $it['id']; ?>" data-unit="<?php echo htmlspecialchars($it['unit'], ENT_QUOTES); ?>">
                                        <?php echo htmlspecialchars($it['item_name']); ?> — <?php echo htmlspecialchars($it['unit']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (!$items): ?><small class="stg-hint"><?php echo htmlspecialchars(t('no_items_hint', 'No items yet — add one in the Items tab first.')); ?></small><?php endif; ?>
                        </div>
                        <div class="stg-form-row">
                            <div class="form-group"><label><?php echo htmlspecialchars(t('quantity', 'Quantity')); ?> <span class="stg-unit-echo" id="stockin-unit-echo"></span></label><input type="number" name="quantity" id="stockin-qty" step="0.001" min="0" required></div>
                            <div class="form-group"><label><?php echo htmlspecialchars(t('unit_price', 'Unit Price')); ?></label><input type="number" name="unit_price" id="stockin-price" step="0.01" min="0" value="0"></div>
                        </div>
                        <div class="stg-live-total" id="stockin-total"><?php echo htmlspecialchars(t('total', 'Total')); ?>: —</div>
                        <div class="stg-form-row">
                            <div class="form-group"><label><?php echo htmlspecialchars(t('date', 'Date')); ?></label><input type="date" name="purchase_date" value="<?php echo $today; ?>"></div>
                            <div class="form-group"><label><?php echo htmlspecialchars(t('vendor', 'Vendor')); ?></label><input type="text" name="vendor" placeholder="<?php echo htmlspecialchars(t('supplier_optional', 'Supplier (optional)')); ?>"></div>
                        </div>
                        <div class="stg-form-row">
                            <div class="form-group"><label><?php echo htmlspecialchars(t('ordered_by_full_name', 'Ordered By (Full Name)')); ?></label><input type="text" name="ordered_by_name" placeholder="<?php echo htmlspecialchars(t('who_requested_ordered', 'Who requested/ordered this purchase')); ?>" required></div>
                            <div class="form-group"><label><?php echo htmlspecialchars(t('invoice_number', 'Invoice #')); ?></label><input type="text" name="invoice_no" placeholder="<?php echo htmlspecialchars(t('optional', '(optional)')); ?>"></div>
                        </div>
                        <div class="form-group">
                            <label><?php echo htmlspecialchars(t('invoice', 'Invoice')); ?> (PDF) <span class="stg-required"><?php echo htmlspecialchars(t('required', 'required')); ?></span></label>
                            <input type="file" name="invoice_file" accept="application/pdf" required>
                        </div>
                        <?php if ($approvedRequests): ?>
                        <div class="form-group">
                            <label><?php echo htmlspecialchars(t('fulfilling_request', 'Fulfilling Request')); ?> <span class="stg-optional"><?php echo htmlspecialchars(t('optional', '(optional)')); ?></span></label>
                            <select name="request_id">
                                <option value="">— <?php echo htmlspecialchars(t('none', 'None')); ?> —</option>
                                <?php foreach ($approvedRequests as $rq): ?>
                                    <option value="<?php echo (int) $rq['id']; ?>">
                                        <?php echo htmlspecialchars($rq['item_name']); ?> — <?php echo qty_fmt($rq['quantity']); ?> <?php echo htmlspecialchars($rq['unit'] ?? ''); ?>
                                        <?php if ($rq['requester']): ?> (by <?php echo htmlspecialchars($rq['requester']); ?>)<?php endif; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>
                        <div class="form-group"><label><?php echo htmlspecialchars(t('notes', 'Notes')); ?></label><input type="text" name="notes" placeholder="<?php echo htmlspecialchars(t('optional', 'Optional')); ?>"></div>
                        <button class="btn btn-primary btn-block" type="submit"><i class="fas fa-arrow-down-to-line"></i> <?php echo htmlspecialchars(t('record_stock_in', 'Record & Stock In')); ?></button>
                    </form>
                </div>
                <div class="stg-list-card">
                    <h3 class="stg-list-title"><i class="fas fa-receipt"></i> <?php echo htmlspecialchars(t('recent_purchases', 'Recent Purchases')); ?></h3>
                    <form class="stg-filter-bar" id="filter-purchases">
                        <input type="date" name="date_from" title="<?php echo htmlspecialchars(t('from_date', 'From date')); ?>">
                        <input type="date" name="date_to" title="<?php echo htmlspecialchars(t('to_date', 'To date')); ?>">
                        <select name="item_id">
                            <option value=""><?php echo htmlspecialchars(t('all_items', 'All items')); ?></option>
                            <?php foreach ($items as $it): ?><option value="<?php echo (int) $it['id']; ?>"><?php echo htmlspecialchars($it['item_name']); ?></option><?php endforeach; ?>
                        </select>
                        <input type="text" name="vendor" placeholder="<?php echo htmlspecialchars(t('vendor', 'Vendor')); ?>…">
                        <input type="text" name="ordered_by" placeholder="<?php echo htmlspecialchars(t('ordered_by', 'Ordered By')); ?>…">
                    </form>
                    <div class="stg-table-wrap">
                        <table class="data-table stg-table">
                            <thead><tr><th><?php echo htmlspecialchars(t('date', 'Date')); ?></th><th><?php echo htmlspecialchars(t('item', 'Item')); ?></th><th class="num"><?php echo htmlspecialchars(t('qty', 'Qty')); ?></th><th class="num"><?php echo htmlspecialchars(t('unit_price', 'Unit Price')); ?></th><th class="num"><?php echo htmlspecialchars(t('total', 'Total')); ?></th><th><?php echo htmlspecialchars(t('vendor', 'Vendor')); ?></th><th><?php echo htmlspecialchars(t('ordered_by', 'Ordered By')); ?></th><th><?php echo htmlspecialchars(t('invoice_number', 'Invoice #')); ?></th><th><?php echo htmlspecialchars(t('pdf', 'PDF')); ?></th></tr></thead>
                            <tbody id="purchases-tbody"></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </section>
        <?php endif; ?>

        <?php if ($can['issue_stock']): ?>
        <!-- ═══ STOCK OUT ═══ -->
        <section class="stg-panel" id="tab-stockout">
            <div class="stg-grid">
                <div class="panel-card stg-form-card">
                    <div class="card-header"><h2><i class="fas fa-arrow-up-from-line"></i> <?php echo htmlspecialchars(t('issue_stock_use', 'Issue Stock (Use)')); ?></h2></div>
                    <form class="settings-form storage-ajax-form" id="stock-out-form" data-refresh>
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="action" value="stock_out">

                        <div class="form-group">
                            <label><?php echo htmlspecialchars(t('item', 'Item')); ?></label>
                            <select name="item_id" id="stockout-item" required>
                                <option value=""><?php echo htmlspecialchars(t('select_item', 'Select item…')); ?></option>
                                <?php foreach ($items as $it): ?>
                                    <option value="<?php echo (int) $it['id']; ?>" data-balance="<?php echo (float) $it['balance']; ?>" data-unit="<?php echo htmlspecialchars($it['unit'], ENT_QUOTES); ?>">
                                        <?php echo htmlspecialchars($it['item_name']); ?> (<?php echo qty_fmt($it['balance']); ?> <?php echo htmlspecialchars($it['unit']); ?> <?php echo htmlspecialchars(t('in_stock', 'in stock')); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="stg-hint" id="stockout-balance-hint"></small>
                        </div>
                        <div class="stg-form-row">
                            <div class="form-group"><label><?php echo htmlspecialchars(t('quantity', 'Quantity')); ?></label><input type="number" name="quantity" step="0.001" min="0" required></div>
                            <div class="form-group"><label><?php echo htmlspecialchars(t('date', 'Date')); ?></label><input type="date" name="movement_date" value="<?php echo $today; ?>"></div>
                        </div>
                        <div class="form-group"><label><?php echo htmlspecialchars(t('taken_by_full_name', 'Taken By (Full Name)')); ?></label><input type="text" name="person_name" placeholder="<?php echo htmlspecialchars(t('who_is_taking_stock', 'Who is taking this stock')); ?>" required></div>
                        <label class="stg-projectwide">
                            <input type="checkbox" name="is_project_wide" id="projectwide-toggle" value="1">
                            <span><i class="fas fa-diagram-project"></i> <?php echo htmlspecialchars(t('project_wide_note', 'Project-wide (used across the whole project — allocate to apartments later)')); ?></span>
                        </label>
                        <div id="location-fields">
                            <div class="stg-form-row">
                                <div class="form-group">
                                    <label><?php echo htmlspecialchars(t('building', 'Building')); ?> <span class="stg-optional"><?php echo htmlspecialchars(t('optional', '(optional)')); ?></span></label>
                                    <select name="building_id" id="stockout-building">
                                        <option value="">—</option>
                                        <?php foreach ($buildings as $b): ?>
                                            <option value="<?php echo (int) $b['id']; ?>"><?php echo htmlspecialchars($b['building_name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label><?php echo htmlspecialchars(t('floor', 'Floor')); ?> <span class="stg-optional"><?php echo htmlspecialchars(t('optional', '(optional)')); ?></span></label>
                                    <select name="floor_id" id="stockout-floor"><option value="">—</option></select>
                                </div>
                            </div>
                            <div class="form-group">
                                <label><?php echo htmlspecialchars(t('apartment', 'Apartment')); ?> <span class="stg-optional"><?php echo htmlspecialchars(t('optional', '(optional)')); ?></span></label>
                                <select name="apartment_id" id="stockout-apartment"><option value="">—</option></select>
                            </div>
                        </div>
                        <div class="form-group"><label><?php echo htmlspecialchars(t('notes', 'Notes')); ?></label><input type="text" name="notes" placeholder="<?php echo htmlspecialchars(t('optional', 'Optional')); ?>"></div>
                        <button class="btn btn-primary btn-block" type="submit"><i class="fas fa-arrow-up-from-line"></i> <?php echo htmlspecialchars(t('issue_stock_use', 'Issue Stock')); ?></button>
                    </form>
                </div>
                <div class="stg-list-card">
                    <h3 class="stg-list-title"><i class="fas fa-dolly"></i> <?php echo htmlspecialchars(t('recent_stock_out', 'Recent Stock Out')); ?></h3>
                    <form class="stg-filter-bar" id="filter-movements">
                        <input type="date" name="date_from" title="<?php echo htmlspecialchars(t('from_date', 'From date')); ?>">
                        <input type="date" name="date_to" title="<?php echo htmlspecialchars(t('to_date', 'To date')); ?>">
                        <select name="item_id">
                            <option value=""><?php echo htmlspecialchars(t('all_items', 'All items')); ?></option>
                            <?php foreach ($items as $it): ?><option value="<?php echo (int) $it['id']; ?>"><?php echo htmlspecialchars($it['item_name']); ?></option><?php endforeach; ?>
                        </select>
                        <select name="building_id" id="movements-filter-building">
                            <option value=""><?php echo htmlspecialchars(t('all_locations', 'All locations')); ?></option>
                            <?php foreach ($buildings as $b): ?>
                                <option value="<?php echo (int) $b['id']; ?>"><?php echo htmlspecialchars($b['building_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <input type="text" name="taken_by" placeholder="<?php echo htmlspecialchars(t('taken_by', 'Taken by…')); ?>">
                    </form>
                    <div class="stg-table-wrap">
                        <table class="data-table stg-table">
                            <thead><tr><th><?php echo htmlspecialchars(t('date', 'Date')); ?></th><th><?php echo htmlspecialchars(t('item', 'Item')); ?></th><th class="num"><?php echo htmlspecialchars(t('quantity', 'Qty')); ?></th><th><?php echo htmlspecialchars(t('location', 'Location')); ?></th><th><?php echo htmlspecialchars(t('taken_by', 'Taken By')); ?></th><th><?php echo htmlspecialchars(t('notes', 'Notes')); ?></th></tr></thead>
                            <tbody id="movements-tbody"></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </section>
        <?php endif; ?>

        <!-- ═══ REQUESTS ═══ -->
        <section class="stg-panel" id="tab-requests">
            <div class="stg-grid">
                <?php if ($can['create']): ?>
                <div class="panel-card stg-form-card">
                    <div class="card-header"><h2><i class="fas fa-clipboard-list"></i> <?php echo htmlspecialchars(t('new_purchase_request', 'New Purchase Request')); ?></h2></div>
                    <form class="settings-form storage-ajax-form" id="request-form" data-refresh>
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="action" value="create_request">
                        <input type="hidden" name="item_id" id="request-item-id" value="">

                        <div class="stg-form-section">
                            <div class="stg-form-section-title"><i class="fas fa-box"></i> <?php echo htmlspecialchars(t('whats_needed', "What's Needed")); ?></div>
                            <div class="form-group">
                                <label><?php echo htmlspecialchars(t('item', 'Item')); ?></label>
                                <select id="request-item-select">
                                    <option value="">— <?php echo htmlspecialchars(t('select_existing_item', 'Select existing item')); ?> —</option>
                                    <?php foreach ($items as $it): ?>
                                        <option value="<?php echo (int) $it['id']; ?>" data-unit="<?php echo htmlspecialchars($it['unit'], ENT_QUOTES); ?>"><?php echo htmlspecialchars($it['item_name']); ?></option>
                                    <?php endforeach; ?>
                                    <option value="other"><?php echo htmlspecialchars(t('new_item_option', '+ New item (not yet in catalogue)')); ?></option>
                                </select>
                            </div>
                            <div class="form-group stg-hidden" id="request-new-item-wrap">
                                <label><?php echo htmlspecialchars(t('new_item_name', 'New Item Name')); ?></label>
                                <input type="text" name="item_name" id="request-new-item-name" placeholder="<?php echo htmlspecialchars(t('what_do_you_need', 'What do you need?')); ?>">
                            </div>
                            <div class="stg-form-row">
                                <div class="form-group"><label><?php echo htmlspecialchars(t('quantity', 'Quantity')); ?></label><input type="number" name="quantity" step="0.001" min="0" required></div>
                                <div class="form-group"><label><?php echo htmlspecialchars(t('unit', 'Unit')); ?></label><input type="text" name="unit" id="request-unit" placeholder="e.g. bag"></div>
                            </div>
                        </div>

                        <div class="stg-form-section">
                            <div class="stg-form-section-title"><i class="fas fa-flag"></i> <?php echo htmlspecialchars(t('priority_timing', 'Priority & Timing')); ?></div>
                            <div class="stg-form-row">
                                <div class="form-group">
                                    <label><?php echo htmlspecialchars(t('priority', 'Priority')); ?></label>
                                    <select name="priority">
                                        <option value="low"><?php echo htmlspecialchars(t('low', 'Low')); ?></option>
                                        <option value="medium" selected><?php echo htmlspecialchars(t('medium', 'Medium')); ?></option>
                                        <option value="high"><?php echo htmlspecialchars(t('high', 'High')); ?></option>
                                        <option value="urgent"><?php echo htmlspecialchars(t('urgent', 'Urgent')); ?></option>
                                    </select>
                                </div>
                                <div class="form-group"><label><?php echo htmlspecialchars(t('needed_by', 'Needed By')); ?> <span class="stg-optional"><?php echo htmlspecialchars(t('optional', '(optional)')); ?></span></label><input type="date" name="needed_by_date"></div>
                            </div>
                        </div>

                        <div class="form-group"><label><?php echo htmlspecialchars(t('notes_justification', 'Notes / Justification')); ?></label><input type="text" name="notes" placeholder="<?php echo htmlspecialchars(t('reason_details', 'Reason / details (optional)')); ?>"></div>
                        <button class="btn btn-primary btn-block" type="submit"><i class="fas fa-paper-plane"></i> <?php echo htmlspecialchars(t('submit_request', 'Submit Request')); ?></button>
                    </form>
                </div>
                <?php endif; ?>
                <div class="stg-list-card">
                    <h3 class="stg-list-title"><i class="fas fa-clipboard-check"></i> <?php echo htmlspecialchars(t('requests', 'Requests')); ?></h3>
                    <form class="stg-filter-bar" id="filter-requests">
                        <input type="date" name="date_from" title="<?php echo htmlspecialchars(t('from_date', 'From date')); ?>">
                        <input type="date" name="date_to" title="<?php echo htmlspecialchars(t('to_date', 'To date')); ?>">
                        <input type="text" name="item" placeholder="<?php echo htmlspecialchars(t('item', 'Item')); ?>…">
                        <select name="priority">
                            <option value=""><?php echo htmlspecialchars(t('all_priorities', 'All priorities')); ?></option>
                            <option value="low"><?php echo htmlspecialchars(t('low', 'Low')); ?></option>
                            <option value="medium"><?php echo htmlspecialchars(t('medium', 'Medium')); ?></option>
                            <option value="high"><?php echo htmlspecialchars(t('high', 'High')); ?></option>
                            <option value="urgent"><?php echo htmlspecialchars(t('urgent', 'Urgent')); ?></option>
                        </select>
                        <select name="requester_id">
                            <option value=""><?php echo htmlspecialchars(t('all_requesters', 'All requesters')); ?></option>
                            <?php while ($u = $usersList->fetch_assoc()): ?>
                                <option value="<?php echo (int) $u['id']; ?>"><?php echo htmlspecialchars($u['username']); ?></option>
                            <?php endwhile; ?>
                        </select>
                        <select name="status">
                            <option value=""><?php echo htmlspecialchars(t('all_statuses', 'All statuses')); ?></option>
                            <option value="pending"><?php echo htmlspecialchars(t('pending', 'Pending')); ?></option>
                            <option value="approved"><?php echo htmlspecialchars(t('approved_awaiting_delivery', 'Approved — Awaiting Delivery')); ?></option>
                            <option value="rejected"><?php echo htmlspecialchars(t('rejected', 'Rejected')); ?></option>
                            <option value="fulfilled"><?php echo htmlspecialchars(t('delivered', 'Delivered')); ?></option>
                        </select>
                    </form>
                    <div class="stg-table-wrap">
                        <table class="data-table stg-table">
                            <thead><tr><th><?php echo htmlspecialchars(t('item', 'Item')); ?></th><th class="num"><?php echo htmlspecialchars(t('quantity', 'Qty')); ?></th><th><?php echo htmlspecialchars(t('priority', 'Priority')); ?></th><th><?php echo htmlspecialchars(t('needed_by', 'Needed By')); ?></th><th><?php echo htmlspecialchars(t('requester', 'Requester')); ?></th><th><?php echo htmlspecialchars(t('status', 'Status')); ?></th><th><?php echo htmlspecialchars(t('action', 'Action')); ?></th></tr></thead>
                            <tbody id="requests-tbody"></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </section>

        <!-- ═══ HISTORY ═══ -->
        <section class="stg-panel" id="tab-history">
            <div class="stg-panel-head">
                <h2><i class="fas fa-clock-rotate-left"></i> <?php echo htmlspecialchars(t('movement_history', 'Movement History')); ?></h2>
                <?php if ($can['export']): ?>
                <a href="#" id="history-export-link" class="btn btn-secondary btn-sm" target="_blank"><i class="fas fa-file-csv"></i> <?php echo htmlspecialchars(t('export_csv', 'Export CSV')); ?></a>
                <?php endif; ?>
            </div>
            <form class="stg-filter-bar" id="filter-history">
                <input type="date" name="date_from" title="<?php echo htmlspecialchars(t('from_date', 'From date')); ?>">
                <input type="date" name="date_to" title="<?php echo htmlspecialchars(t('to_date', 'To date')); ?>">
                <select name="item_id">
                    <option value=""><?php echo htmlspecialchars(t('all_items', 'All items')); ?></option>
                    <?php foreach ($items as $it): ?><option value="<?php echo (int) $it['id']; ?>"><?php echo htmlspecialchars($it['item_name']); ?></option><?php endforeach; ?>
                </select>
                <select name="direction">
                    <option value=""><?php echo htmlspecialchars(t('in_and_out', 'In & Out')); ?></option>
                    <option value="in"><?php echo htmlspecialchars(t('stock_in_short', 'Stock In')); ?></option>
                    <option value="out"><?php echo htmlspecialchars(t('stock_out_short', 'Stock Out')); ?></option>
                </select>
                <input type="text" name="person" placeholder="<?php echo htmlspecialchars(t('person_recorded_by', 'Person / recorded by…')); ?>">
            </form>
            <div class="stg-table-wrap">
                <table class="data-table stg-table" id="history-table">
                    <thead><tr><th><?php echo htmlspecialchars(t('date', 'Date')); ?></th><th><?php echo htmlspecialchars(t('type', 'Type')); ?></th><th><?php echo htmlspecialchars(t('item', 'Item')); ?></th><th class="num"><?php echo htmlspecialchars(t('quantity', 'Qty')); ?></th><th><?php echo htmlspecialchars(t('location', 'Location')); ?></th><th><?php echo htmlspecialchars(t('person', 'Person')); ?></th><th><?php echo htmlspecialchars(t('recorded_by', 'Recorded By')); ?></th><th><?php echo htmlspecialchars(t('notes', 'Notes')); ?></th></tr></thead>
                    <tbody id="history-tbody"></tbody>
                </table>
            </div>
        </section>

    </div>
</main>
</div>

<div id="stg-toast-container"></div>

<script>
const STG_CSRF = <?php echo json_encode($csrf); ?>;
const STG_NETWORK_ERROR = <?php echo json_encode(t('network_try_again', 'Network error. Please try again.')); ?>;
const STG_REMOVE_ITEM_CONFIRM = <?php echo json_encode(t('remove_item_confirm', 'Remove this item from the active catalogue? Purchase/movement history is kept.')); ?>;
const STG_TOTAL_LABEL = <?php echo json_encode(t('total', 'Total')); ?>;

function stgToast(msg, type) {
    const t = document.createElement('div');
    t.className = 'stg-toast stg-toast-' + (type || 'success');
    t.innerHTML = '<i class="fas fa-' + (type === 'error' ? 'circle-exclamation' : 'circle-check') + '"></i> ' + msg;
    document.getElementById('stg-toast-container').appendChild(t);
    setTimeout(() => { t.classList.add('show'); }, 10);
    setTimeout(() => { t.classList.remove('show'); setTimeout(() => t.remove(), 300); }, 3200);
}

function debounce(fn, wait) {
    let timer;
    return function () {
        clearTimeout(timer);
        const args = arguments;
        timer = setTimeout(() => fn.apply(null, args), wait);
    };
}

// ── Tabs (persist across reload via hash) ─────────────────────────────────
function activateTab(name) {
    const btn = document.querySelector('.stg-tab[data-tab="' + name + '"]');
    if (!btn) return; // tab hidden for this user's permissions
    document.querySelectorAll('.stg-tab').forEach(b => b.classList.toggle('active', b.dataset.tab === name));
    document.querySelectorAll('.stg-panel').forEach(p => p.classList.toggle('active', p.id === 'tab-' + name));
}
document.querySelectorAll('.stg-tab').forEach(btn => {
    btn.addEventListener('click', () => { activateTab(btn.dataset.tab); history.replaceState(null, '', '#' + btn.dataset.tab); });
});
if (location.hash) { activateTab(location.hash.slice(1)); }

// ── Generic AJAX form submit (creation/edit forms reload on success so every
//    dropdown/table/stat card picks up the change) ─────────────────────────
function postStorage(formData, onOk) {
    fetch('storage_ajax.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(d => {
            stgToast(d.message, d.success ? 'success' : 'error');
            if (d.success && onOk) onOk();
        })
        .catch(() => stgToast(STG_NETWORK_ERROR, 'error'));
}
document.querySelectorAll('.storage-ajax-form').forEach(form => {
    form.addEventListener('submit', function (e) {
        e.preventDefault();
        const btn = form.querySelector('button[type="submit"]');
        if (btn) btn.disabled = true;
        postStorage(new FormData(form), () => {
            const active = document.querySelector('.stg-tab.active');
            if (active) location.hash = active.dataset.tab;
            location.reload();
        });
        setTimeout(() => { if (btn) btn.disabled = false; }, 1500);
    });
});

// ── Advanced filtering (Balance / Stock In / Stock Out / Requests / History)
function bindFilterForm(section, formId, tbodyId) {
    const form = document.getElementById(formId);
    const tbody = document.getElementById(tbodyId);
    if (!form || !tbody) return null;
    function reload() {
        const params = new URLSearchParams(new FormData(form));
        params.set('section', section);
        fetch('storage_filter.php?' + params.toString())
            .then(r => r.text())
            .then(html => { tbody.innerHTML = html; });
        return params;
    }
    form.addEventListener('change', reload);
    form.addEventListener('input', debounce(reload, 350));
    form.addEventListener('submit', e => { e.preventDefault(); reload(); });
    reload();
    return { form, reload };
}
bindFilterForm('balance', 'filter-balance', 'balance-tbody');
bindFilterForm('purchases', 'filter-purchases', 'purchases-tbody');
bindFilterForm('movements', 'filter-movements', 'movements-tbody');
bindFilterForm('requests', 'filter-requests', 'requests-tbody');
const historyFilter = bindFilterForm('history', 'filter-history', 'history-tbody');

// Keep the "Export CSV" link's query string in sync with the current History filters.
const historyExportLink = document.getElementById('history-export-link');
if (historyFilter && historyExportLink) {
    const updateExportLink = () => {
        const params = new URLSearchParams(new FormData(historyFilter.form));
        params.set('section', 'history');
        params.set('export', '1');
        historyExportLink.href = 'storage_filter.php?' + params.toString();
    };
    historyFilter.form.addEventListener('change', updateExportLink);
    historyFilter.form.addEventListener('input', debounce(updateExportLink, 350));
    updateExportLink();
}

// ── Approve / reject requests (delegated: the tbody is replaced by filters) ─
document.addEventListener('click', function (e) {
    const btn = e.target.closest('.stg-approve, .stg-reject');
    if (!btn) return;
    const decision = btn.classList.contains('stg-approve') ? 'approved' : 'rejected';
    const fd = new FormData();
    fd.append('action', 'review_request');
    fd.append('csrf_token', STG_CSRF);
    fd.append('request_id', btn.dataset.req);
    fd.append('decision', decision);
    postStorage(fd, () => { location.hash = 'requests'; location.reload(); });
});

// ── Step 2: mark an approved request as delivered to storage ──────────────
document.addEventListener('click', function (e) {
    const btn = e.target.closest('.stg-delivered');
    if (!btn) return;
    const fd = new FormData();
    fd.append('action', 'mark_delivered');
    fd.append('csrf_token', STG_CSRF);
    fd.append('request_id', btn.dataset.req);
    postStorage(fd, () => { location.hash = 'requests'; location.reload(); });
});

// ── Items: edit / delete ───────────────────────────────────────────────────
document.addEventListener('click', function (e) {
    const editBtn = e.target.closest('.stg-edit-item');
    if (editBtn) {
        const f = document.getElementById('item-form');
        f.querySelector('[name="item_id"]').value = editBtn.dataset.id;
        f.querySelector('[name="item_name"]').value = editBtn.dataset.name;
        f.querySelector('[name="unit"]').value = editBtn.dataset.unit || '';
        f.querySelector('[name="item_code"]').value = editBtn.dataset.code || '';
        document.getElementById('item-form-btn-label').textContent = <?php echo json_encode(t('save_changes', 'Save Changes')); ?>;
        f.querySelector('.stg-cancel-edit').style.display = '';
        f.scrollIntoView({ behavior: 'smooth', block: 'center' });
        return;
    }
    const delBtn = e.target.closest('.stg-delete-item');
    if (delBtn) {
        if (!confirm(STG_REMOVE_ITEM_CONFIRM)) return;
        const fd = new FormData();
        fd.append('action', 'delete_item');
        fd.append('csrf_token', STG_CSRF);
        fd.append('item_id', delBtn.dataset.id);
        postStorage(fd, () => { location.hash = 'items'; location.reload(); });
        return;
    }
});

document.querySelectorAll('.stg-cancel-edit').forEach(btn => {
    btn.addEventListener('click', function () {
        const f = document.getElementById(this.dataset.form);
        f.reset();
        f.querySelectorAll('input[type="hidden"]').forEach(h => { if (h.name.endsWith('_id')) h.value = ''; });
        document.getElementById('item-form-btn-label').textContent = <?php echo json_encode(t('add_item', 'Add Item')); ?>;
        this.style.display = 'none';
    });
});

// ── Stock In: unit echo + live total ───────────────────────────────────────
const siItem = document.getElementById('stockin-item');
if (siItem) siItem.addEventListener('change', function () {
    const opt = this.options[this.selectedIndex];
    const echo = document.getElementById('stockin-unit-echo');
    if (echo) echo.textContent = (opt && opt.dataset.unit) ? '(' + opt.dataset.unit + ')' : '';
    updateStockInTotal();
});

const siQty = document.getElementById('stockin-qty');
const siPrice = document.getElementById('stockin-price');
const siTotal = document.getElementById('stockin-total');
function updateStockInTotal() {
    if (!siTotal) return;
    const q = parseFloat(siQty && siQty.value) || 0;
    const p = parseFloat(siPrice && siPrice.value) || 0;
    siTotal.textContent = (q > 0 && p > 0)
        ? STG_TOTAL_LABEL + ': $' + (q * p).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })
        : STG_TOTAL_LABEL + ': —';
}
if (siQty) siQty.addEventListener('input', updateStockInTotal);
if (siPrice) siPrice.addEventListener('input', updateStockInTotal);

// ── Stock Out: balance hint + project-wide toggle + dependent selects ─────
const soItem = document.getElementById('stockout-item');
const soHint = document.getElementById('stockout-balance-hint');
if (soItem) soItem.addEventListener('change', function () {
    const opt = this.options[this.selectedIndex];
    const bal = opt ? opt.dataset.balance : null;
    const unit = opt ? (opt.dataset.unit || '') : '';
    soHint.textContent = (bal !== null && bal !== undefined) ? ('Available: ' + bal + (unit ? ' ' + unit : '')) : '';
});

const pwToggle = document.getElementById('projectwide-toggle');
const locFields = document.getElementById('location-fields');
if (pwToggle) pwToggle.addEventListener('change', function () {
    locFields.style.display = this.checked ? 'none' : '';
    if (this.checked) {
        ['stockout-building', 'stockout-floor', 'stockout-apartment'].forEach(id => {
            const el = document.getElementById(id); if (el) el.value = '';
        });
    }
});

const soBuilding = document.getElementById('stockout-building');
const soFloor = document.getElementById('stockout-floor');
const soApartment = document.getElementById('stockout-apartment');
if (soBuilding) soBuilding.addEventListener('change', function () {
    soFloor.innerHTML = '<option value="">—</option>';
    soApartment.innerHTML = '<option value="">—</option>';
    if (!this.value) return;
    fetch('get_floors.php', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: 'building_id=' + encodeURIComponent(this.value) })
        .then(r => r.text()).then(html => { soFloor.innerHTML = html; });
});
if (soFloor) soFloor.addEventListener('change', function () {
    soApartment.innerHTML = '<option value="">—</option>';
    if (!this.value) return;
    fetch('get_apartments.php', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: 'floor_id=' + encodeURIComponent(this.value) })
        .then(r => r.text()).then(html => { soApartment.innerHTML = html; });
});

// ── Requests: pick an existing item, or reveal a "new item" text field ────
const reqSelect = document.getElementById('request-item-select');
const reqItemId = document.getElementById('request-item-id');
const reqNewWrap = document.getElementById('request-new-item-wrap');
const reqNewName = document.getElementById('request-new-item-name');
const reqUnit = document.getElementById('request-unit');
if (reqSelect) reqSelect.addEventListener('change', function () {
    if (this.value === 'other') {
        reqItemId.value = '';
        reqNewWrap.classList.remove('stg-hidden');
        reqNewName.setAttribute('required', 'required');
    } else if (this.value) {
        reqItemId.value = this.value;
        reqNewWrap.classList.add('stg-hidden');
        reqNewName.removeAttribute('required');
        const opt = this.options[this.selectedIndex];
        if (opt && opt.dataset.unit) reqUnit.value = opt.dataset.unit;
    } else {
        reqItemId.value = '';
        reqNewWrap.classList.add('stg-hidden');
        reqNewName.removeAttribute('required');
    }
});
</script>
</body>
</html>
