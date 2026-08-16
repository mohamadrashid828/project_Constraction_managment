<?php
session_start();
require_once '../config.php';
require_once 'includes/i18n.php';
require_once 'includes/permissions.php';
require_once 'includes/stakeholders.php';

if (empty($_SESSION['user_id'])) {
	header('Location: index.html');
	exit;
}

$user_id = (int)$_SESSION['user_id'];
$permissions = get_user_permissions($conn, $user_id);

if (!in_array('analytics', $permissions, true)) {
	header('Location: dashboard.php?error=access_denied');
	exit;
}

function an_area(float $v): string
{
	if ($v <= 0) {
		return '—';
	}
	$decimals = fmod($v, 1.0) != 0.0 ? 2 : 0;
	return number_format($v, $decimals) . ' m²';
}

// ── Project structure ────────────────────────────────────────────────────────
// Per-building breakdown. Apartments are counted through their floor (the
// real parent chain) rather than the denormalized apartments.building_id
// column, and only 'active' rows count, so the numbers here always agree
// with what Project Settings actually contains.
$structureRows = [];
$res = $conn->query("
	SELECT b.id, b.building_name, COALESCE(b.total_area, 0) AS total_area,
		(SELECT COUNT(*) FROM floors f
		 WHERE f.building_id = b.id AND f.status = 'active') AS floors_count,
		(SELECT COUNT(*) FROM apartments a
		 JOIN floors fa ON fa.id = a.floor_id
		 WHERE fa.building_id = b.id AND fa.status = 'active' AND a.status = 'active') AS apartments_count,
		(SELECT COALESCE(SUM(a2.area_sqm), 0) FROM apartments a2
		 JOIN floors fa2 ON fa2.id = a2.floor_id
		 WHERE fa2.building_id = b.id AND fa2.status = 'active' AND a2.status = 'active') AS apartments_area
	FROM buildings b
	WHERE b.status = 'active'
	ORDER BY b.building_name
");
while ($res && $r = $res->fetch_assoc()) {
	$structureRows[(int)$r['id']] = [
		'name'            => (string)$r['building_name'],
		'total_area'      => (float)$r['total_area'],
		'floors'          => (int)$r['floors_count'],
		'apartments'      => (int)$r['apartments_count'],
		'apartments_area' => (float)$r['apartments_area'],
		'unit_mix'        => [],
	];
}

$res = $conn->query("
	SELECT f.building_id,
	       COALESCE(NULLIF(TRIM(a.apartment_type), ''), 'Unspecified') AS apt_type,
	       COUNT(*) AS c
	FROM apartments a
	JOIN floors f ON f.id = a.floor_id
	WHERE a.status = 'active' AND f.status = 'active'
	GROUP BY f.building_id, apt_type
	ORDER BY apt_type
");
while ($res && $r = $res->fetch_assoc()) {
	$bid = (int)$r['building_id'];
	if (isset($structureRows[$bid])) {
		$structureRows[$bid]['unit_mix'][] = (int)$r['c'] . ' × ' . (string)$r['apt_type'];
	}
}

$structureTotals = ['buildings' => count($structureRows), 'floors' => 0, 'apartments' => 0, 'total_area' => 0.0, 'apartments_area' => 0.0];
foreach ($structureRows as $b) {
	$structureTotals['floors']          += $b['floors'];
	$structureTotals['apartments']      += $b['apartments'];
	$structureTotals['total_area']      += $b['total_area'];
	$structureTotals['apartments_area'] += $b['apartments_area'];
}


// ── Report builder filter sources ──────────────────────────────────────────
$appLanguage = $_SESSION['language'] ?? 'en';
$workTypes = [['key' => 'gechkari', 'name' => 'Gechkari']];
$workTypeSeen = ['gechkari' => true];
$workTypesQuery = $conn->query("SELECT work_type_key, work_type_name, work_type_name_ku FROM project_work_types WHERE is_active = 1 ORDER BY work_type_name");
if ($workTypesQuery) {
	while ($row = $workTypesQuery->fetch_assoc()) {
		$key = strtolower(trim((string)$row['work_type_key']));
		if ($key === '' || isset($workTypeSeen[$key])) {
			continue;
		}
		$englishName = trim((string)$row['work_type_name']) !== '' ? (string)$row['work_type_name'] : ucfirst($key);
		$kuName = trim((string)($row['work_type_name_ku'] ?? ''));
		$name = ($appLanguage === 'ckb' && $kuName !== '') ? $kuName : $englishName;
		$workTypes[] = [
			'key' => $key,
			'name' => $name,
		];
		$workTypeSeen[$key] = true;
	}
}

$buildingsList = [];
$buildingsQuery = $conn->query("SELECT id, building_name FROM buildings WHERE status = 'active' ORDER BY building_name");
if ($buildingsQuery) {
	while ($row = $buildingsQuery->fetch_assoc()) {
		$buildingsList[] = ['id' => (int)$row['id'], 'name' => $row['building_name']];
	}
}

$floorsList = [];
$floorsQuery = $conn->query("SELECT id, building_id, floor_name, floor_number FROM floors WHERE status = 'active' ORDER BY building_id, floor_number, floor_name");
if ($floorsQuery) {
	while ($row = $floorsQuery->fetch_assoc()) {
		$floorsList[] = [
			'id' => (int)$row['id'],
			'building_id' => (int)$row['building_id'],
			'name' => $row['floor_name'],
			'number' => (int)$row['floor_number'],
		];
	}
}

$apartmentsList = [];
$apartmentsQuery = $conn->query("
	SELECT a.id, f.building_id, a.floor_id, a.apartment_number
	FROM apartments a
	JOIN floors f ON f.id = a.floor_id
	WHERE a.status = 'active' AND f.status = 'active'
	ORDER BY f.building_id, a.floor_id, a.apartment_number");
if ($apartmentsQuery) {
	while ($row = $apartmentsQuery->fetch_assoc()) {
		$apartmentsList[] = [
			'id' => (int)$row['id'],
			'building_id' => (int)$row['building_id'],
			'floor_id' => (int)$row['floor_id'],
			'number' => $row['apartment_number'],
		];
	}
}

$pageTitle = t('analysis', 'Analysis') . ' - ' . t('construction_management', 'Construction Management');
$pageCss = 'analytics.css';
$activePage = 'analytics';
require_once 'partials/header.php';
?>
<div id="analytics-page" class="dashboard-container">
<?php require_once 'partials/sidebar.php'; ?>
	<main class="main-content">
		<header class="page-header">
			<h1><i class="fas fa-chart-line"></i> <?php echo htmlspecialchars(t('analysis', 'Analysis')); ?></h1>
			<div class="user-info">
				<span><?php echo htmlspecialchars(t('welcome', 'Welcome')); ?>, <?php echo htmlspecialchars($_SESSION['username']); ?></span>
			</div>
		</header>

		<div class="content-wrapper analytics-wrapper">

			<!-- ═══ WORK & SUBWORK LEDGER ═══ -->
			<div class="analytics-section-head analytics-section-head-ledger">
				<h2><i class="fas fa-book"></i> <?php echo htmlspecialchars(t('work_subwork_ledger', 'Work & Subwork Ledger')); ?></h2>
				<p><?php echo htmlspecialchars(t('analytics_ledger_hint', 'Filter by date, building, or apartment — click a category or stakeholder to see the full detail behind it.')); ?></p>
			</div>

			<section class="analytics-card">
				<div class="analytics-card-head">
					<h2><i class="fas fa-filter"></i> <?php echo htmlspecialchars(t('filters', 'Filters')); ?></h2>
					<span class="analytics-card-note" id="ledger-scope-label"><?php echo htmlspecialchars(t('entire_project', 'Entire Project')); ?></span>
				</div>
				<div class="analytics-report-body">
					<div class="analytics-filter-grid">
						<div class="analytics-filter-group">
							<label for="ledger-date-from"><?php echo htmlspecialchars(t('from_date', 'From Date')); ?></label>
							<input type="date" id="ledger-date-from">
						</div>
						<div class="analytics-filter-group">
							<label for="ledger-date-to"><?php echo htmlspecialchars(t('to_date', 'To Date')); ?></label>
							<input type="date" id="ledger-date-to">
						</div>
						<div class="analytics-filter-group">
							<label for="ledger-building"><?php echo htmlspecialchars(t('building', 'Building')); ?></label>
							<select id="ledger-building">
								<option value=""><?php echo htmlspecialchars(t('all_buildings', 'All Buildings')); ?></option>
								<option value="0"><?php echo htmlspecialchars(t('project_wide_no_building', 'Project-Wide (No Building)')); ?></option>
								<?php foreach ($buildingsList as $building): ?>
									<option value="<?php echo (int)$building['id']; ?>"><?php echo htmlspecialchars($building['name']); ?></option>
								<?php endforeach; ?>
							</select>
						</div>
						<div class="analytics-filter-group">
							<label for="ledger-floor"><?php echo htmlspecialchars(t('floor', 'Floor')); ?></label>
							<select id="ledger-floor"><option value=""><?php echo htmlspecialchars(t('all_floors', 'All Floors')); ?></option></select>
						</div>
						<div class="analytics-filter-group">
							<label for="ledger-apartment"><?php echo htmlspecialchars(t('apartment', 'Apartment')); ?></label>
							<select id="ledger-apartment"><option value=""><?php echo htmlspecialchars(t('all_apartments', 'All Apartments')); ?></option></select>
						</div>
						<div class="analytics-filter-actions">
							<button type="button" class="btn btn-secondary" id="ledger-reset-btn"><i class="fas fa-undo"></i> <?php echo htmlspecialchars(t('reset', 'Reset')); ?></button>
							<button type="button" class="btn btn-primary" id="ledger-run-btn"><i class="fas fa-magnifying-glass"></i> <?php echo htmlspecialchars(t('apply', 'Apply')); ?></button>
						</div>
					</div>
				</div>
			</section>

			<div class="analytics-kpis" id="ledger-kpis"></div>
			<div class="analytics-kpis" id="ledger-kpis-settlement"></div>

			<div class="analytics-grid">
				<section class="analytics-card">
					<div class="analytics-card-head">
						<h2><i class="fas fa-chart-pie"></i> <?php echo htmlspecialchars(t('work_status_mix', 'Work Status Mix')); ?></h2>
						<span class="analytics-card-note" id="ledger-status-currency"></span>
					</div>
					<div class="analytics-chart-wrap analytics-chart-wrap-pie">
						<canvas id="statusMixPie"></canvas>
					</div>
				</section>
				<section class="analytics-card">
					<div class="analytics-card-head">
						<h2><i class="fas fa-chart-pie"></i> <?php echo htmlspecialchars(t('settlement_coverage', 'Settlement Coverage')); ?></h2>
						<span class="analytics-card-note" id="ledger-settlement-currency"></span>
					</div>
					<div class="analytics-chart-wrap analytics-chart-wrap-pie">
						<canvas id="settlementPie"></canvas>
					</div>
				</section>
			</div>

			<section class="analytics-card" id="ledger-location-card" style="display:none;">
				<div class="analytics-card-head">
					<h2><i class="fas fa-map-location-dot"></i> <?php echo htmlspecialchars(t('this_location', 'This Location')); ?></h2>
					<span class="analytics-card-note" id="ledger-location-cost"></span>
				</div>
				<div class="analytics-table-wrap">
					<table class="analytics-table">
						<thead>
							<tr>
								<th><?php echo htmlspecialchars(t('date', 'Date')); ?></th>
								<th><?php echo htmlspecialchars(t('category', 'Category')); ?> / <?php echo htmlspecialchars(t('sub_work', 'Subwork')); ?></th>
								<th><?php echo htmlspecialchars(t('engineer', 'Engineer')); ?></th>
								<th><?php echo htmlspecialchars(t('stakeholder', 'Stakeholder')); ?></th>
								<th><?php echo htmlspecialchars(t('quantity', 'Quantity')); ?></th>
								<th><?php echo htmlspecialchars(t('cost', 'Cost')); ?></th>
								<th><?php echo htmlspecialchars(t('status', 'Status')); ?></th>
							</tr>
						</thead>
						<tbody id="ledger-location-tbody"></tbody>
					</table>
				</div>
			</section>

			<section class="analytics-card">
				<div class="analytics-card-head">
					<h2><i class="fas fa-layer-group"></i> <?php echo htmlspecialchars(t('all_categories', 'All Categories')); ?></h2>
					<div class="analytics-list-controls">
						<label for="category-display-limit">Show</label>
						<select id="category-display-limit">
							<option value="3">3</option>
							<option value="5">5</option>
							<option value="10">10</option>
							<option value="20">20</option>
							<option value="50">50</option>
							<option value="all" selected>All</option>
						</select>
					</div>
					<span class="analytics-card-note" id="ledger-categories-count"></span>
				</div>
				<div class="analytics-expand-list" id="ledger-categories-list"></div>
			</section>

			<section class="analytics-card">
				<div class="analytics-card-head">
					<h2><i class="fas fa-users"></i> <?php echo htmlspecialchars(t('all_stakeholders', 'All Stakeholders')); ?></h2>
					<div class="analytics-list-controls">
						<label for="stakeholder-display-limit">Show</label>
						<select id="stakeholder-display-limit">
							<option value="3">3</option>
							<option value="5">5</option>
							<option value="10">10</option>
							<option value="20">20</option>
							<option value="50">50</option>
							<option value="all" selected>All</option>
						</select>
					</div>
					<span class="analytics-card-note" id="ledger-stakeholders-count"></span>
				</div>
				<div class="analytics-expand-list" id="ledger-stakeholders-list"></div>
			</section>

			<!-- ═══ PROJECT STRUCTURE ═══ -->
			<div class="analytics-section-head analytics-section-head-structure">
				<h2><i class="fas fa-building"></i> <?php echo htmlspecialchars(t('project_structure', 'Project Structure')); ?></h2>
				<p><?php echo htmlspecialchars(t('project_structure_hint', 'Live breakdown of every building as configured in Project Settings.')); ?></p>
			</div>

			<section class="analytics-card">
				<div class="analytics-card-head">
					<h2><i class="fas fa-sitemap"></i> <?php echo htmlspecialchars(t('building_breakdown', 'Building Breakdown')); ?></h2>
					<div class="analytics-list-controls">
						<label for="building-display-limit">Show</label>
						<select id="building-display-limit">
							<option value="3">3</option>
							<option value="5">5</option>
							<option value="10">10</option>
							<option value="20">20</option>
							<option value="50">50</option>
							<option value="all" selected>All</option>
						</select>
					</div>
					<span class="analytics-card-note">
						<?php echo (int)$structureTotals['buildings']; ?> <?php echo htmlspecialchars(t('buildings_count_label', 'buildings')); ?> ·
						<?php echo (int)$structureTotals['floors']; ?> <?php echo htmlspecialchars(t('floors_count_label', 'floors')); ?> ·
						<?php echo (int)$structureTotals['apartments']; ?> <?php echo htmlspecialchars(t('apartments_count_label', 'apartments')); ?>
					</span>
				</div>
				<?php if (empty($structureRows)): ?>
					<div class="analytics-empty"><i class="fas fa-building"></i> <?php echo htmlspecialchars(t('no_buildings_yet_add_in_settings', 'No buildings yet. Add them in Project Settings.')); ?></div>
				<?php else: ?>
					<div class="analytics-table-wrap">
						<table class="analytics-table analytics-structure-table">
							<thead>
								<tr>
									<th><?php echo htmlspecialchars(t('building', 'Building')); ?></th>
									<th><?php echo htmlspecialchars(t('floors_count_label', 'Floors')); ?></th>
									<th><?php echo htmlspecialchars(t('apartments_count_label', 'Apartments')); ?></th>
									<th><?php echo htmlspecialchars(t('unit_mix', 'Unit Mix')); ?></th>
									<th><?php echo htmlspecialchars(t('apartments_area', 'Apartments Area')); ?></th>
									<th><?php echo htmlspecialchars(t('building_area', 'Building Area')); ?></th>
								</tr>
							</thead>
							<tbody id="structure-table-body">
								<?php foreach ($structureRows as $b): ?>
									<tr data-building-row="true">
										<td class="analytics-structure-name"><?php echo htmlspecialchars($b['name']); ?></td>
										<td><?php echo number_format($b['floors']); ?></td>
										<td><?php echo number_format($b['apartments']); ?></td>
										<td>
											<?php if ($b['unit_mix']): foreach ($b['unit_mix'] as $mix): ?>
												<span class="analytics-mix-chip"><?php echo htmlspecialchars($mix); ?></span>
											<?php endforeach; else: ?>
												<span class="analytics-structure-none">—</span>
											<?php endif; ?>
										</td>
										<td><?php echo an_area($b['apartments_area']); ?></td>
										<td><?php echo an_area($b['total_area']); ?></td>
									</tr>
								<?php endforeach; ?>
							</tbody>
							<tfoot>
								<tr>
									<td><?php echo htmlspecialchars(t('total', 'Total')); ?></td>
									<td><?php echo number_format($structureTotals['floors']); ?></td>
									<td><?php echo number_format($structureTotals['apartments']); ?></td>
									<td></td>
									<td><?php echo an_area($structureTotals['apartments_area']); ?></td>
									<td><?php echo an_area($structureTotals['total_area']); ?></td>
								</tr>
							</tfoot>
						</table>
					</div>
				<?php endif; ?>
			</section>

			<!-- ═══ CUSTOM REPORT BUILDER ═══ -->
			<div class="analytics-section-head analytics-section-head-report">
				<h2><i class="fas fa-filter"></i> <?php echo htmlspecialchars(t('custom_report_builder', 'Custom Report Builder')); ?></h2>
				<p><?php echo htmlspecialchars(t('custom_report_hint', 'Slice work entries by category, location, stakeholder, date, or status.')); ?></p>
			</div>

			<section class="analytics-card analytics-report-card">
				<div class="analytics-report-body">
					<div class="analytics-filter-grid">
						<div class="analytics-filter-group">
							<label for="report-breakdown"><?php echo htmlspecialchars(t('breakdown', 'Breakdown')); ?></label>
							<select id="report-breakdown">
								<option value="category"><?php echo htmlspecialchars(t('by_category', 'By Category')); ?></option>
								<option value="building"><?php echo htmlspecialchars(t('by_building', 'By Building')); ?></option>
								<option value="floor"><?php echo htmlspecialchars(t('by_floor', 'By Floor')); ?></option>
								<option value="apartment"><?php echo htmlspecialchars(t('by_apartment', 'By Apartment')); ?></option>
								<option value="stakeholder"><?php echo htmlspecialchars(t('by_stakeholder', 'By Stakeholder')); ?></option>
							</select>
						</div>
						<div class="analytics-filter-group">
							<label for="report-work-type"><?php echo htmlspecialchars(t('category', 'Category')); ?></label>
							<select id="report-work-type">
								<option value=""><?php echo htmlspecialchars(t('all_categories', 'All Categories')); ?></option>
								<?php foreach ($workTypes as $wt): ?>
									<option value="<?php echo htmlspecialchars($wt['key']); ?>"><?php echo htmlspecialchars($wt['name']); ?></option>
								<?php endforeach; ?>
							</select>
						</div>
						<div class="analytics-filter-group">
							<label for="report-status"><?php echo htmlspecialchars(t('status', 'Status')); ?></label>
							<select id="report-status">
								<option value=""><?php echo htmlspecialchars(t('all_statuses', 'All Statuses')); ?></option>
								<option value="accepted"><?php echo htmlspecialchars(t('approved', 'Approved')); ?></option>
								<option value="medium"><?php echo htmlspecialchars(t('under_review', 'Under Review')); ?></option>
								<option value="draft"><?php echo htmlspecialchars(t('waiting', 'Waiting')); ?></option>
								<option value="rejected"><?php echo htmlspecialchars(t('rejected', 'Rejected')); ?></option>
							</select>
						</div>
						<div class="analytics-filter-group">
							<label for="report-building"><?php echo htmlspecialchars(t('building', 'Building')); ?></label>
							<select id="report-building">
								<option value=""><?php echo htmlspecialchars(t('all_buildings', 'All Buildings')); ?></option>
								<?php foreach ($buildingsList as $building): ?>
									<option value="<?php echo (int)$building['id']; ?>"><?php echo htmlspecialchars($building['name']); ?></option>
								<?php endforeach; ?>
							</select>
						</div>
						<div class="analytics-filter-group">
							<label for="report-floor"><?php echo htmlspecialchars(t('floor', 'Floor')); ?></label>
							<select id="report-floor">
								<option value=""><?php echo htmlspecialchars(t('all_floors', 'All Floors')); ?></option>
							</select>
						</div>
						<div class="analytics-filter-group">
							<label for="report-apartment"><?php echo htmlspecialchars(t('apartment', 'Apartment')); ?></label>
							<select id="report-apartment">
								<option value=""><?php echo htmlspecialchars(t('all_apartments', 'All Apartments')); ?></option>
							</select>
						</div>
						<div class="analytics-filter-group">
							<label for="report-date-from"><?php echo htmlspecialchars(t('from_date', 'From Date')); ?></label>
							<input type="date" id="report-date-from">
						</div>
						<div class="analytics-filter-group">
							<label for="report-date-to"><?php echo htmlspecialchars(t('to_date', 'To Date')); ?></label>
							<input type="date" id="report-date-to">
						</div>
						<div class="analytics-filter-actions">
							<button type="button" class="btn btn-secondary" id="report-reset-btn"><i class="fas fa-undo"></i> <?php echo htmlspecialchars(t('reset', 'Reset')); ?></button>
							<button type="button" class="btn btn-primary" id="report-run-btn"><i class="fas fa-chart-column"></i> <?php echo htmlspecialchars(t('show_report', 'Show Report')); ?></button>
						</div>
					</div>

					<div class="analytics-report-summary" id="report-summary-cards">
						<div class="analytics-mini-card">
							<span><?php echo htmlspecialchars(t('entries', 'Entries')); ?></span>
							<strong id="report-summary-entries">0</strong>
						</div>
						<div class="analytics-mini-card">
							<span><?php echo htmlspecialchars(t('groups', 'Groups')); ?></span>
							<strong id="report-summary-groups">0</strong>
						</div>
						<div class="analytics-mini-card">
							<span><?php echo htmlspecialchars(t('total_quantity', 'Total Quantity')); ?></span>
							<strong id="report-summary-quantity">0.00</strong>
						</div>
						<div class="analytics-mini-card">
							<span><?php echo htmlspecialchars(t('total_value', 'Total Value')); ?></span>
							<strong id="report-summary-value">0</strong>
						</div>
					</div>

					<div class="analytics-grid analytics-grid-report">
						<section class="analytics-card analytics-card-inner">
							<div class="analytics-card-head analytics-card-head-inner">
								<h2><i class="fas fa-chart-pie"></i> <?php echo htmlspecialchars(t('report_chart', 'Report Chart')); ?></h2>
								<span class="analytics-card-note" id="report-chart-currency"></span>
							</div>
							<div class="analytics-chart-wrap analytics-chart-wrap-report">
								<canvas id="detailAnalysisChart"></canvas>
							</div>
						</section>
						<section class="analytics-card analytics-card-inner">
							<div class="analytics-card-head analytics-card-head-inner">
								<h2><i class="fas fa-table-list"></i> <?php echo htmlspecialchars(t('group_summary', 'Group Summary')); ?></h2>
							</div>
							<div class="analytics-table-wrap">
								<table class="analytics-table">
									<thead>
										<tr>
											<th><?php echo htmlspecialchars(t('group', 'Group')); ?></th>
											<th><?php echo htmlspecialchars(t('entries', 'Entries')); ?></th>
											<th><?php echo htmlspecialchars(t('quantity', 'Quantity')); ?></th>
											<th><?php echo htmlspecialchars(t('total_value', 'Total Value')); ?></th>
										</tr>
									</thead>
									<tbody id="report-groups-tbody">
										<tr><td colspan="4" class="analytics-empty-row"><?php echo htmlspecialchars(t('run_report_to_see_grouped_data', 'Run a report to see grouped data.')); ?></td></tr>
									</tbody>
								</table>
							</div>
						</section>
					</div>

					<section class="analytics-card analytics-card-inner analytics-details-card">
						<div class="analytics-card-head analytics-card-head-inner">
							<h2><i class="fas fa-list-check"></i> <?php echo htmlspecialchars(t('detailed_entries', 'Detailed Entries')); ?></h2>
						</div>
						<div class="analytics-table-wrap">
							<table class="analytics-table analytics-detail-table">
								<thead>
									<tr>
										<th><?php echo htmlspecialchars(t('date', 'Date')); ?></th>
										<th><?php echo htmlspecialchars(t('category', 'Category')); ?></th>
										<th><?php echo htmlspecialchars(t('building', 'Building')); ?></th>
										<th><?php echo htmlspecialchars(t('floor', 'Floor')); ?></th>
										<th><?php echo htmlspecialchars(t('apartment', 'Apartment')); ?></th>
										<th><?php echo htmlspecialchars(t('stakeholder', 'Stakeholder')); ?></th>
										<th><?php echo htmlspecialchars(t('quantity', 'Quantity')); ?></th>
										<th><?php echo htmlspecialchars(t('total', 'Total')); ?></th>
										<th><?php echo htmlspecialchars(t('status', 'Status')); ?></th>
									</tr>
								</thead>
								<tbody id="report-details-tbody">
									<tr><td colspan="9" class="analytics-empty-row"><?php echo htmlspecialchars(t('run_report_to_see_detailed_entries', 'Run a report to see detailed entries.')); ?></td></tr>
								</tbody>
							</table>
						</div>
					</section>
				</div>
			</section>
		</div>
	</main>
</div>

	<!-- ═══ Subwork History Modal ═══ -->
	<div class="analytics-modal-overlay" id="subwork-history-overlay" onclick="closeSubworkHistory(event)">
		<div class="analytics-modal-card">
			<div class="analytics-modal-head">
				<h3 id="subwork-history-title"><i class="fas fa-clock-rotate-left"></i> <?php echo htmlspecialchars(t('subwork_history', 'Subwork History')); ?></h3>
				<button type="button" class="analytics-modal-close" onclick="closeSubworkHistory()" title="<?php echo htmlspecialchars(t('close', 'Close')); ?>"><i class="fas fa-times"></i></button>
			</div>
			<div class="analytics-modal-body">
				<div class="analytics-table-wrap">
					<table class="analytics-table">
						<thead>
							<tr><th><?php echo htmlspecialchars(t('date', 'Date')); ?></th><th><?php echo htmlspecialchars(t('location', 'Location')); ?></th><th><?php echo htmlspecialchars(t('engineer', 'Engineer')); ?></th><th><?php echo htmlspecialchars(t('quantity', 'Quantity')); ?></th><th><?php echo htmlspecialchars(t('cost', 'Cost')); ?></th><th><?php echo htmlspecialchars(t('status', 'Status')); ?></th></tr>
						</thead>
						<tbody id="subwork-history-tbody">
							<tr><td colspan="6" class="analytics-empty-row"><?php echo htmlspecialchars(t('loading', 'Loading…')); ?></td></tr>
						</tbody>
					</table>
				</div>
			</div>
		</div>
	</div>

<script>
const analyticsBuildings = <?php echo json_encode($buildingsList, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
const analyticsFloors = <?php echo json_encode($floorsList, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
const analyticsApartments = <?php echo json_encode($apartmentsList, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
const buildingNameById = Object.fromEntries(analyticsBuildings.map(b => [String(b.id), b.name]));
const floorById = Object.fromEntries(analyticsFloors.map(f => [String(f.id), f]));
const tx = (key, fallback) => {
	const values = window.appTranslations || {};
	const text = values[key];
	return text || fallback || key;
};
const analyticsLabels = {
	updating: tx('updating', 'Updating…'),
	loading: tx('loading', 'Loading…'),
	network_error: tx('network_error', 'Network error'),
	failed_to_load_history: tx('failed_to_load_history', 'Failed to load history.'),
	no_recorded_work_for_subwork: tx('no_recorded_work_for_subwork', 'No recorded work for this subwork in the current filters.')
};
const statusLabels = {
	accepted: tx('approved', 'Approved'),
	medium: tx('under_review', 'Under Review'),
	draft: tx('waiting', 'Waiting'),
	rejected: tx('rejected', 'Rejected')
};
const statusClasses = { accepted: 'ok', medium: 'warn', draft: 'muted', rejected: 'bad' };
const statusChartLabels = {
	approved: tx('approved', 'Approved'),
	review: tx('under_review', 'Under Review'),
	rejected: tx('rejected', 'Rejected'),
	settled: tx('settled_paid', 'Settled / Paid'),
	outstanding: tx('outstanding_payable', 'Outstanding Payable')
};

let detailChartInstance = null;

// Status is a small, fixed, reserved color scale (good/warning/critical) —
// the same three colors as the KPI cards and ledger table cells everywhere
// on this page, so a color means the same thing whether it's a KPI, a table
// cell, or a pie slice — never re-purposed as "series 4".
const STATUS_COLOR = {
	ok:   { fill: 'rgba(74, 222, 128, 0.85)',  border: '#4ade80' },
	warn: { fill: 'rgba(251, 146, 60, 0.85)',  border: '#fb923c' },
	bad:  { fill: 'rgba(248, 113, 113, 0.85)', border: '#f87171' }
};
// The ring between pie slices is a 2px gap in the surface color (never a
// stroke) — this app's dark card surface, so slices read as distinct without
// adding extra ink that isn't data.
const CHART_SURFACE = '#0f172a';

function pieOptions(currency) {
	return {
		responsive: true,
		maintainAspectRatio: false,
		plugins: {
			legend: { position: 'bottom', labels: { color: '#cbd5e1', boxWidth: 12, boxHeight: 12, padding: 14 } },
			tooltip: { callbacks: { label: (ctx) => ctx.label + ': ' + formatMoney(ctx.parsed, currency) } }
		}
	};
}

let statusMixPieInstance = null;
let settlementPieInstance = null;
let lastLedgerData = null;
const expandedCategories = new Set();
const expandedStakeholders = new Set();

function getDisplayLimit(value, fallback = 10) {
	if (value === 'all') return Number.MAX_SAFE_INTEGER;
	const parsed = Number(value);
	return Number.isFinite(parsed) && parsed > 0 ? parsed : fallback;
}

function applyVisibleLimit(items, selectId, totalLabelId, emptyText) {
	const select = document.getElementById(selectId);
	const totalLabel = document.getElementById(totalLabelId);
	if (!select || !totalLabel) return items;
	const limit = getDisplayLimit(select.value, 10);
	const visible = limit >= items.length ? items : items.slice(0, limit);
	if (items.length > 0) {
		const countText = limit >= items.length ? items.length : visible.length;
		totalLabel.textContent = 'Showing ' + countText + ' / ' + items.length + ' ' + (items.length === 1 ? 'item' : 'items');
	} else {
		totalLabel.textContent = emptyText;
	}
	return visible;
}

function applyBuildingLimit() {
	const select = document.getElementById('building-display-limit');
	const rows = Array.from(document.querySelectorAll('[data-building-row="true"]'));
	if (!select || !rows.length) return;
	const limit = getDisplayLimit(select.value, 10);
	rows.forEach((row, index) => {
		row.style.display = index < limit || limit >= rows.length ? '' : 'none';
	});
}

function renderLedgerKpis(kpi) {
	document.getElementById('ledger-kpis').innerHTML = `
		<div class="analytics-kpi-card">
			<span class="kpi-label">${tx('work_entries', 'Work Entries')}</span>
			<strong>${Number(kpi.entries).toLocaleString()}</strong>
			<span class="kpi-sub">${tx('in_this_scope', 'in this scope')}</span>
		</div>
		<div class="analytics-kpi-card">
			<span class="kpi-label">${tx('approved_recognized', 'Approved (Recognized)')}</span>
			<strong class="kpi-value-ok">${escHtml(kpi.approved_display)}</strong>
			<span class="kpi-sub">${tx('work_signed_off', "work that's been signed off")}</span>
		</div>
		<div class="analytics-kpi-card">
			<span class="kpi-label">${tx('under_review', 'Under Review')}</span>
			<strong class="kpi-value-warn">${escHtml(kpi.review_display)}</strong>
			<span class="kpi-sub">${Number(kpi.review_count).toLocaleString()} ${tx('entries_awaiting_decision', 'entries awaiting a decision')}</span>
		</div>
		<div class="analytics-kpi-card">
			<span class="kpi-label">${tx('rejected', 'Rejected')}</span>
			<strong class="kpi-value-bad">${escHtml(kpi.rejected_display)}</strong>
			<span class="kpi-sub">${Number(kpi.rejected_count).toLocaleString()} ${tx('entries_turned_down', 'entries turned down')}</span>
		</div>
	`;
	document.getElementById('ledger-kpis-settlement').innerHTML = `
		<div class="analytics-kpi-card">
			<span class="kpi-label">${tx('settled_paid', 'Settled / Paid')}</span>
			<strong class="kpi-value-ok">${escHtml(kpi.settled_display)}</strong>
			<span class="kpi-sub">${tx('disbursed_via_slfa', 'disbursed via Slfa')}</span>
		</div>
		<div class="analytics-kpi-card">
			<span class="kpi-label">${tx('outstanding_payable', 'Outstanding Payable')}</span>
			<strong class="kpi-value-warn">${escHtml(kpi.outstanding_display)}</strong>
			<span class="kpi-sub">${tx('approved_not_settled', 'approved, not yet settled')}</span>
		</div>
		<div class="analytics-kpi-card">
			<span class="kpi-label">${tx('settlement_coverage', 'Settlement Coverage')}</span>
			<strong>${kpi.settlement_coverage_pct}%</strong>
			<span class="kpi-sub">${tx('of_approved_value_paid', 'of approved value already paid')}</span>
		</div>
		<div class="analytics-kpi-card">
			<span class="kpi-label">${tx('rejection_rate', 'Rejection Rate')}</span>
			<strong>${kpi.rejection_rate_pct}%</strong>
			<span class="kpi-sub">${tx('of_decided_entries', 'of decided entries')}</span>
		</div>
	`;
}

function renderPies(statusMix, settlement) {
	document.getElementById('ledger-status-currency').textContent = statusMix.currency;
	document.getElementById('ledger-settlement-currency').textContent = settlement.currency;

	if (statusMixPieInstance) { statusMixPieInstance.destroy(); statusMixPieInstance = null; }
	if (settlementPieInstance) { settlementPieInstance.destroy(); settlementPieInstance = null; }

	const statusTotal = statusMix.approved + statusMix.review + statusMix.rejected;
	const statusCtx = document.getElementById('statusMixPie');
	if (statusCtx && statusTotal > 0.0001) {
		statusMixPieInstance = new Chart(statusCtx, {
			type: 'pie',
			data: {
				labels: [statusChartLabels.approved, statusChartLabels.review, statusChartLabels.rejected],
				datasets: [{
					data: [statusMix.approved, statusMix.review, statusMix.rejected],
					backgroundColor: [STATUS_COLOR.ok.fill, STATUS_COLOR.warn.fill, STATUS_COLOR.bad.fill],
					borderColor: CHART_SURFACE,
					borderWidth: 2
				}]
			},
			options: pieOptions(statusMix.currency)
		});
	}

	const settleTotal = settlement.settled + settlement.outstanding;
	const settleCtx = document.getElementById('settlementPie');
	if (settleCtx && settleTotal > 0.0001) {
		settlementPieInstance = new Chart(settleCtx, {
			type: 'pie',
			data: {
				labels: [statusChartLabels.settled, statusChartLabels.outstanding],
				datasets: [{
					data: [settlement.settled, settlement.outstanding],
					backgroundColor: [STATUS_COLOR.ok.fill, STATUS_COLOR.warn.fill],
					borderColor: CHART_SURFACE,
					borderWidth: 2
				}]
			},
			options: pieOptions(settlement.currency)
		});
	}
}

function renderLocationCard(data) {
	const card = document.getElementById('ledger-location-card');
	if (!data.has_location_filter) {
		card.style.display = 'none';
		return;
	}
	card.style.display = '';
	document.getElementById('ledger-location-cost').textContent = tx('approved_cost_to_date', 'Approved cost to date: ') + (data.kpi.approved_display || '0');
	const tbody = document.getElementById('ledger-location-tbody');
	if (!data.entries.length) {
		tbody.innerHTML = '<tr><td colspan="7" class="analytics-empty-row">' + tx('no_work_recorded_in_scope', 'No work recorded in this scope yet.') + '</td></tr>';
		return;
	}
	tbody.innerHTML = data.entries.map(e => `
		<tr>
			<td>${escHtml(e.date)}</td>
			<td>${escHtml(e.work_type)}${e.subpart !== '—' ? ' / ' + escHtml(e.subpart) : ''}</td>
			<td>${escHtml(e.engineer)}</td>
			<td>${escHtml(e.stakeholder)}</td>
			<td>${Number(e.quantity).toFixed(2)} ${escHtml(e.metric)}</td>
			<td>${escHtml(e.total_display)}</td>
			<td><span class="analytics-status analytics-status-${escHtml(statusClasses[e.status] || 'muted')}">${escHtml(statusLabels[e.status] || e.status)}</span></td>
		</tr>
	`).join('');
}

function renderCategories(categories) {
	const list = document.getElementById('ledger-categories-list');
	const visibleCategories = applyVisibleLimit(categories, 'category-display-limit', 'ledger-categories-count', tx('no_work_entries_in_scope', 'No work entries in this scope.'));
	if (!visibleCategories.length) {
		list.innerHTML = '<div class="analytics-empty"><i class="fas fa-layer-group"></i> ' + tx('no_work_entries_in_scope', 'No work entries in this scope.') + '</div>';
		return;
	}
	list.innerHTML = visibleCategories.map((c) => {
		const key = c.name + '|' + c.currency;
		const isOpen = expandedCategories.has(key);
		const stRows = c.stakeholders.map(s => `<tr><td>${escHtml(s.name)}</td><td>${s.entries}</td><td>${escHtml(s.approved_display)}</td></tr>`).join('');
		const bChips = c.buildings.map(b => `<span class="analytics-mix-chip">${escHtml(b.name)} (${b.entries})</span>`).join(' ');
		return `
		<div class="analytics-expand-item">
			<button type="button" class="analytics-expand-head" data-key="${escHtml(key)}" data-list="category">
				<i class="fas fa-chevron-${isOpen ? 'down' : 'right'}"></i>
				<span class="analytics-expand-title">${escHtml(c.name)} <small>(${escHtml(c.currency)})</small></span>
				<span class="analytics-expand-figures">
					<span class="ledger-value-ok">${escHtml(c.approved_display)}</span>
					<span class="ledger-value-warn">${escHtml(c.review_display)}</span>
					<span class="ledger-value-bad">${escHtml(c.rejected_display)}</span>
				</span>
			</button>
			<div class="analytics-expand-body" style="display:${isOpen ? 'block' : 'none'}">
				<div class="analytics-expand-stats">
					<span>${tx('entries', 'Entries')}: <strong>${c.entries}</strong></span>
					<span>${tx('total_quantity', 'Total Qty')}: <strong>${Number(c.quantity).toFixed(2)}</strong></span>
					<span>${tx('settled_paid', 'Settled')}: <strong class="ledger-value-ok">${c.settled_display ? escHtml(c.settled_display) : '— ' + tx('not_tracked', 'not tracked')}</strong></span>
					<span>${tx('outstanding_payable', 'Outstanding')}: <strong class="ledger-value-warn">${c.outstanding_display ? escHtml(c.outstanding_display) : '— ' + tx('not_tracked', 'not tracked')}</strong></span>
				</div>
				<h4>${tx('stakeholders_in_this_category', 'Stakeholders in this category')}</h4>
				<table class="analytics-table analytics-subtable">
					<thead><tr><th>${tx('stakeholder', 'Stakeholder')}</th><th>${tx('entries', 'Entries')}</th><th>${tx('approved', 'Approved')}</th></tr></thead>
					<tbody>${stRows || '<tr><td colspan="3" class="analytics-empty-row">—</td></tr>'}</tbody>
				</table>
				<h4>${tx('where_this_happened', 'Where this happened')}</h4>
				<div class="analytics-mix-chips">${bChips || '—'}</div>
			</div>
		</div>`;
	}).join('');
}

function renderStakeholders(stakeholders) {
	const list = document.getElementById('ledger-stakeholders-list');
	const visibleStakeholders = applyVisibleLimit(stakeholders, 'stakeholder-display-limit', 'ledger-stakeholders-count', tx('no_stakeholder_linked_work', 'No stakeholder-linked work in this scope.'));
	if (!visibleStakeholders.length) {
		list.innerHTML = '<div class="analytics-empty"><i class="fas fa-users"></i> ' + tx('no_stakeholder_linked_work', 'No stakeholder-linked work in this scope.') + '</div>';
		return;
	}
	list.innerHTML = visibleStakeholders.map((s) => {
		const isOpen = expandedStakeholders.has(String(s.id));
		const spRows = s.subparts.map(sp => `
			<tr class="analytics-subwork-row" data-subpart-id="${sp.id}" data-subpart-label="${escHtml(sp.name + ' — ' + s.name)}" title="${tx('click_to_see_subwork_history', "Click to see this subwork's history")}">
				<td>${escHtml(sp.name)}</td>
				<td>${escHtml(sp.metric)}</td>
				<td>${escHtml(sp.unit_price_display)}</td>
				<td>${sp.entries}</td>
				<td>${Number(sp.quantity).toFixed(2)}</td>
				<td class="ledger-value-ok">${escHtml(sp.approved_display)}</td>
				<td class="ledger-value-warn">${escHtml(sp.review_display)}</td>
				<td class="ledger-value-bad">${escHtml(sp.rejected_display)}</td>
				<td class="ledger-value-ok">${escHtml(sp.settled_display)}</td>
				<td class="ledger-value-warn">${escHtml(sp.outstanding_display)}</td>
			</tr>
		`).join('');
		return `
		<div class="analytics-expand-item">
			<button type="button" class="analytics-expand-head" data-key="${s.id}" data-list="stakeholder">
				<i class="fas fa-chevron-${isOpen ? 'down' : 'right'}"></i>
				<span class="analytics-expand-title">${escHtml(s.name)}</span>
				<span class="analytics-expand-figures">
					<span class="ledger-value-ok">${escHtml(s.approved_display)}</span>
					<span class="ledger-value-warn">${escHtml(s.outstanding_display)}</span>
				</span>
			</button>
			<div class="analytics-expand-body" style="display:${isOpen ? 'block' : 'none'}">
				<div class="analytics-expand-stats">
					<span>${tx('entries', 'Entries')}: <strong>${s.entries}</strong></span>
					<span>${tx('settled_paid', 'Settled')}: <strong class="ledger-value-ok">${escHtml(s.settled_display)}</strong></span>
					<span>${tx('outstanding_payable', 'Outstanding')}: <strong class="ledger-value-warn">${escHtml(s.outstanding_display)}</strong></span>
				</div>
				<table class="analytics-table analytics-subtable">
					<thead><tr><th>${tx('sub_work', 'Subwork')}</th><th>${tx('metric', 'Metric')}</th><th>${tx('price', 'Price')}</th><th>${tx('entries', 'Entries')}</th><th>${tx('quantity', 'Qty')}</th><th>${tx('approved', 'Approved')}</th><th>${tx('under_review', 'Review')}</th><th>${tx('rejected', 'Rejected')}</th><th>${tx('settled_paid', 'Settled')}</th><th>${tx('outstanding_payable', 'Outstanding')}</th></tr></thead>
					<tbody>${spRows || '<tr><td colspan="10" class="analytics-empty-row">' + tx('no_subwork_in_scope', 'No subwork recorded in this scope.') + '</td></tr>'}</tbody>
				</table>
			</div>
		</div>`;
	}).join('');
}

let ledgerRequestSeq = 0;

function loadLedgerReport() {
	// Visible proof that a filter change actually triggers a fresh fetch —
	// without this, a fast/cached-looking response can read as "nothing
	// happened" even though the data underneath is genuinely re-queried.
	const thisRequest = ++ledgerRequestSeq;
	const scopeLabelEl = document.getElementById('ledger-scope-label');
		scopeLabelEl.innerHTML = '<i class="fas fa-spinner fa-spin"></i> ' + analyticsLabels.updating;
	const params = new URLSearchParams({
		date_from: document.getElementById('ledger-date-from').value,
		date_to: document.getElementById('ledger-date-to').value,
		building_id: document.getElementById('ledger-building').value,
		floor_id: document.getElementById('ledger-floor').value,
		apartment_id: document.getElementById('ledger-apartment').value
	});
	fetch('get_ledger_report.php?' + params.toString())
		.then(r => r.json())
		.then(data => {
			document.getElementById('ledger-run-btn').disabled = false;
			// A slower earlier request finishing after a newer one would
			// otherwise clobber the screen with stale results.
			if (thisRequest !== ledgerRequestSeq) return;
			if (!data.success) {
				scopeLabelEl.textContent = tx('failed_to_load', 'Failed to load');
				return;
			}
			lastLedgerData = data;
			scopeLabelEl.textContent = data.scope_label;
			renderLedgerKpis(data.kpi);
			renderPies(data.status_mix, data.settlement);
			renderLocationCard(data);
			renderCategories(data.categories);
			renderStakeholders(data.stakeholders);
		})
		.catch(() => {
			document.getElementById('ledger-run-btn').disabled = false;
			if (thisRequest === ledgerRequestSeq) scopeLabelEl.textContent = analyticsLabels.network_error;
		});
}

document.getElementById('ledger-building').addEventListener('change', function() {
	fillFloorOptions(this.value, '', 'ledger-floor');
	fillApartmentOptions('', '', this.value, 'ledger-apartment');
	loadLedgerReport();
});
document.getElementById('ledger-floor').addEventListener('change', function() {
	fillApartmentOptions(this.value, '', document.getElementById('ledger-building').value, 'ledger-apartment');
	loadLedgerReport();
});
document.getElementById('ledger-apartment').addEventListener('change', loadLedgerReport);
document.getElementById('ledger-date-from').addEventListener('change', loadLedgerReport);
document.getElementById('ledger-date-to').addEventListener('change', loadLedgerReport);
document.getElementById('ledger-run-btn').addEventListener('click', loadLedgerReport);
document.getElementById('ledger-reset-btn').addEventListener('click', function() {
	document.getElementById('ledger-date-from').value = '';
	document.getElementById('ledger-date-to').value = '';
	document.getElementById('ledger-building').value = '';
	fillFloorOptions('', '', 'ledger-floor');
	fillApartmentOptions('', '', '', 'ledger-apartment');
	loadLedgerReport();
});

document.addEventListener('click', function(e) {
	const head = e.target.closest('.analytics-expand-head');
	if (head && lastLedgerData) {
		const key = head.getAttribute('data-key');
		const listType = head.getAttribute('data-list');
		const set = listType === 'category' ? expandedCategories : expandedStakeholders;
		if (set.has(key)) { set.delete(key); } else { set.add(key); }
		if (listType === 'category') { renderCategories(lastLedgerData.categories); }
		else { renderStakeholders(lastLedgerData.stakeholders); }
		return;
	}
	const subworkRow = e.target.closest('.analytics-subwork-row');
	if (subworkRow) {
		openSubworkHistory(subworkRow.getAttribute('data-subpart-id'), subworkRow.getAttribute('data-subpart-label'));
	}
});

function openSubworkHistory(subpartId, label) {
	document.getElementById('subwork-history-title').innerHTML = '<i class="fas fa-clock-rotate-left"></i> ' + escHtml(label);
	document.getElementById('subwork-history-tbody').innerHTML = '<tr><td colspan="6" class="analytics-empty-row">' + analyticsLabels.loading + '</td></tr>';
	document.getElementById('subwork-history-overlay').classList.add('open');
	document.body.style.overflow = 'hidden';

	// Same date/location filters currently applied to the ledger still apply
	// here — "history for this subwork" means within the scope you're looking
	// at, not an unrelated all-time dump.
	const params = new URLSearchParams({
		date_from: document.getElementById('ledger-date-from').value,
		date_to: document.getElementById('ledger-date-to').value,
		building_id: document.getElementById('ledger-building').value,
		floor_id: document.getElementById('ledger-floor').value,
		apartment_id: document.getElementById('ledger-apartment').value,
		subpart_id: subpartId
	});
	fetch('get_ledger_report.php?' + params.toString())
		.then(r => r.json())
		.then(data => {
			const tbody = document.getElementById('subwork-history-tbody');
			if (!data.success) {
				tbody.innerHTML = '<tr><td colspan="6" class="analytics-empty-row">' + analyticsLabels.failed_to_load_history + '</td></tr>';
				return;
			}
			if (!data.entries.length) {
				tbody.innerHTML = '<tr><td colspan="6" class="analytics-empty-row">' + analyticsLabels.no_recorded_work_for_subwork + '</td></tr>';
				return;
			}
			tbody.innerHTML = data.entries.map(e => `
				<tr>
					<td>${escHtml(e.date)}</td>
					<td>${escHtml(e.location)}</td>
					<td>${escHtml(e.engineer)}</td>
					<td>${Number(e.quantity).toFixed(2)} ${escHtml(e.metric)}</td>
					<td>${escHtml(e.total_display)}</td>
					<td><span class="analytics-status analytics-status-${escHtml(statusClasses[e.status] || 'muted')}">${escHtml(statusLabels[e.status] || e.status)}</span></td>
				</tr>
			`).join('');
		})
		.catch(() => {
			document.getElementById('subwork-history-tbody').innerHTML = '<tr><td colspan="6" class="analytics-empty-row">' + analyticsLabels.network_error + '</td></tr>';
		});
}

function closeSubworkHistory(event) {
	if (event && event.target !== document.getElementById('subwork-history-overlay')) return;
	document.getElementById('subwork-history-overlay').classList.remove('open');
	document.body.style.overflow = '';
}

document.addEventListener('keydown', function(e) {
	if (e.key === 'Escape') closeSubworkHistory();
});

loadLedgerReport();

// Formats a { IQD: 123, USD: 45 } style map into "123 IQD · $45.00", the
// same layout used on the Dashboard so a value is never a misleading sum
// of two different currencies.
function formatMoney(value, currency) {
	const cur = currency || 'IQD';
	const n = Number(value || 0);
	if (cur === 'USD') {
		return '$' + n.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
	}
	return n.toLocaleString(undefined, { maximumFractionDigits: 0 }) + ' IQD';
}

function formatMoneyByCurrency(byCurrency) {
	const entries = Object.entries(byCurrency || {}).filter(([, v]) => Math.abs(v) > 0.0001);
	if (!entries.length) return '0';
	return entries.map(([cur, v]) => formatMoney(v, cur)).join(' · ');
}

function dominantValue(byCurrency, dominant) {
	return (byCurrency && byCurrency[dominant]) ? byCurrency[dominant] : 0;
}

function floorLabel(floor) {
	return floor.name || ('Floor ' + floor.number);
}

function fillFloorOptions(buildingId, selectedFloorId = '', selectId = 'report-floor') {
	const floorSelect = document.getElementById(selectId);
	if (!floorSelect) return;
	floorSelect.innerHTML = '<option value="">' + tx('all_floors', 'All Floors') + '</option>';
	analyticsFloors
		.filter(floor => !buildingId || String(floor.building_id) === String(buildingId))
		.forEach(floor => {
			const option = document.createElement('option');
			option.value = floor.id;
			// Floor names repeat across buildings, so without a building
			// filter a bare name is ambiguous — qualify it.
			option.textContent = buildingId
				? floorLabel(floor)
				: floorLabel(floor) + ' — ' + (buildingNameById[String(floor.building_id)] || 'Building ' + floor.building_id);
			if (String(selectedFloorId) === String(floor.id)) {
				option.selected = true;
			}
			floorSelect.appendChild(option);
		});
}

function fillApartmentOptions(floorId, selectedApartmentId = '', buildingId = '', selectId = 'report-apartment') {
	const apartmentSelect = document.getElementById(selectId);
	if (!apartmentSelect) return;
	apartmentSelect.innerHTML = '<option value="">' + tx('all_apartments', 'All Apartments') + '</option>';
	analyticsApartments
		.filter(apartment => {
			if (floorId) return String(apartment.floor_id) === String(floorId);
			if (buildingId) return String(apartment.building_id) === String(buildingId);
			return true;
		})
		.forEach(apartment => {
			const option = document.createElement('option');
			option.value = apartment.id;
			if (floorId) {
				option.textContent = apartment.number;
			} else {
				const floor = floorById[String(apartment.floor_id)];
				const context = buildingId
					? (floor ? floorLabel(floor) : '')
					: (buildingNameById[String(apartment.building_id)] || '') + (floor ? ' / ' + floorLabel(floor) : '');
				option.textContent = context ? apartment.number + ' — ' + context : apartment.number;
			}
			if (String(selectedApartmentId) === String(apartment.id)) {
				option.selected = true;
			}
			apartmentSelect.appendChild(option);
		});
}

function renderReportChart(rows, dominant) {
	const ctx = document.getElementById('detailAnalysisChart');
	if (!ctx) return;
	if (detailChartInstance) {
		detailChartInstance.destroy();
		detailChartInstance = null;
	}

	if (!rows.length) {
		return;
	}

	detailChartInstance = new Chart(ctx, {
		type: 'bar',
		data: {
			labels: rows.map(row => row.label),
			datasets: [{
				label: tx('total_value', 'Total Value'),
				data: rows.map(row => dominantValue(row.total_value_by_currency, dominant)),
				backgroundColor: 'rgba(99, 102, 241, 0.6)',
				borderColor: 'rgba(129, 140, 248, 1)',
				borderWidth: 1.5,
				borderRadius: 8,
				maxBarThickness: 42
			}]
		},
		options: {
			responsive: true,
			maintainAspectRatio: false,
			plugins: { legend: { display: false } },
			scales: {
				x: { ticks: { color: '#cbd5e1' }, grid: { display: false } },
				y: { ticks: { color: '#94a3b8' }, grid: { color: 'rgba(148, 163, 184, 0.12)' } }
			}
		}
	});
}

// Escape server-supplied strings before interpolating into innerHTML, so a
// crafted building/stakeholder/work-type name can't inject script.
function escHtml(value) {
	const div = document.createElement('div');
	div.textContent = String(value == null ? '' : value);
	return div.innerHTML;
}

function renderGroupRows(rows) {
	const tbody = document.getElementById('report-groups-tbody');
	if (!tbody) return;
	if (!rows.length) {
		tbody.innerHTML = '<tr><td colspan="4" class="analytics-empty-row">' + tx('no_grouped_results', 'No grouped results for these filters.') + '</td></tr>';
		return;
	}

	tbody.innerHTML = rows.map(row =>
		'<tr>' +
			'<td>' + escHtml(row.label) + '</td>' +
			'<td>' + Number(row.entries_count).toLocaleString() + '</td>' +
			'<td>' + Number(row.total_quantity).toFixed(2) + ' ' + escHtml(row.primary_metric || '') + '</td>' +
			'<td>' + escHtml(formatMoneyByCurrency(row.total_value_by_currency)) + '</td>' +
		'</tr>'
	).join('');
}

function renderDetailRows(rows) {
	const tbody = document.getElementById('report-details-tbody');
	if (!tbody) return;
	if (!rows.length) {
		tbody.innerHTML = '<tr><td colspan="9" class="analytics-empty-row">' + tx('no_detailed_entries_found', 'No detailed entries found for these filters.') + '</td></tr>';
		return;
	}

	tbody.innerHTML = rows.map(row =>
		'<tr>' +
			'<td>' + escHtml(row.entry_date_display) + '</td>' +
			'<td>' + escHtml(row.work_type_name) + '</td>' +
			'<td>' + escHtml(row.building_name) + '</td>' +
			'<td>' + escHtml(row.floor_name) + '</td>' +
			'<td>' + escHtml(row.apartment_label) + '</td>' +
			'<td>' + escHtml(row.stakeholder_name) + '</td>' +
			'<td>' + Number(row.quantity).toFixed(2) + ' ' + escHtml(row.metric_type) + '</td>' +
			'<td>' + escHtml(formatMoney(row.total_price, row.currency_type)) + '</td>' +
			'<td><span class="analytics-status analytics-status-' + escHtml(statusClasses[row.status] || 'muted') + '">' + escHtml(row.status_label) + '</span></td>' +
		'</tr>'
	).join('');
}

function runDetailedReport() {
	const params = new URLSearchParams({
		breakdown: document.getElementById('report-breakdown').value,
		work_type_key: document.getElementById('report-work-type').value,
		status: document.getElementById('report-status').value,
		building_id: document.getElementById('report-building').value,
		floor_id: document.getElementById('report-floor').value,
		apartment_id: document.getElementById('report-apartment').value,
		date_from: document.getElementById('report-date-from').value,
		date_to: document.getElementById('report-date-to').value
	});

	const groupsTbody = document.getElementById('report-groups-tbody');

	fetch('get_dynamic_report.php?' + params.toString())
		.then(response => response.json())
		.then(data => {
			if (!data.success) {
				if (groupsTbody) {
					groupsTbody.innerHTML = '<tr><td colspan="4" class="analytics-empty-row">' + escHtml(data.message || tx('unable_to_load_report', 'Unable to load report.')) + '</td></tr>';
				}
				return;
			}

			const dominant = data.summary.dominant_currency || 'IQD';
			document.getElementById('report-summary-entries').textContent = Number(data.summary.entries_count || 0).toLocaleString();
			document.getElementById('report-summary-groups').textContent = Number(data.summary.groups_count || 0).toLocaleString();
			document.getElementById('report-summary-quantity').textContent = Number(data.summary.total_quantity || 0).toFixed(2);
			document.getElementById('report-summary-value').textContent = formatMoneyByCurrency(data.summary.total_value_by_currency);
			document.getElementById('report-chart-currency').textContent = (data.groups || []).length ? dominant : '';

			renderReportChart(data.groups || [], dominant);
			renderGroupRows(data.groups || []);
			renderDetailRows(data.details || []);
		})
		.catch(() => {
			if (groupsTbody) {
				groupsTbody.innerHTML = '<tr><td colspan="4" class="analytics-empty-row">' + tx('failed_to_load_report_try_again', 'Failed to load report. Please try again.') + '</td></tr>';
			}
		});
}

document.getElementById('report-building').addEventListener('change', function() {
	fillFloorOptions(this.value, '');
	fillApartmentOptions('', '', this.value);
	runDetailedReport();
});

document.getElementById('report-floor').addEventListener('change', function() {
	fillApartmentOptions(this.value, '', document.getElementById('report-building').value);
	runDetailedReport();
});

document.getElementById('report-apartment').addEventListener('change', runDetailedReport);
document.getElementById('report-work-type').addEventListener('change', runDetailedReport);
document.getElementById('report-status').addEventListener('change', runDetailedReport);
document.getElementById('report-breakdown').addEventListener('change', runDetailedReport);
document.getElementById('report-date-from').addEventListener('change', runDetailedReport);
document.getElementById('report-date-to').addEventListener('change', runDetailedReport);

document.getElementById('report-run-btn').addEventListener('click', runDetailedReport);

document.getElementById('report-reset-btn').addEventListener('click', function() {
	document.getElementById('report-breakdown').value = 'category';
	document.getElementById('report-work-type').value = '';
	document.getElementById('report-status').value = '';
	document.getElementById('report-building').value = '';
	document.getElementById('report-date-from').value = '';
	document.getElementById('report-date-to').value = '';
	fillFloorOptions('', '');
	fillApartmentOptions('', '');
	runDetailedReport();
});

['category-display-limit', 'stakeholder-display-limit', 'building-display-limit'].forEach(function(id) {
	const el = document.getElementById(id);
	if (el) {
		el.addEventListener('change', function() {
			if (id === 'building-display-limit') {
				applyBuildingLimit();
				return;
			}
			if (lastLedgerData) {
				if (id === 'category-display-limit') {
					renderCategories(lastLedgerData.categories || []);
				} else if (id === 'stakeholder-display-limit') {
					renderStakeholders(lastLedgerData.stakeholders || []);
				}
			}
		});
	}
});

fillFloorOptions('', '');
fillApartmentOptions('', '');
applyBuildingLimit();
runDetailedReport();
</script>
</body>
</html>
