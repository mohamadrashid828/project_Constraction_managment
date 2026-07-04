<?php
session_start();
require_once '../config.php';
require_once 'includes/permissions.php';

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

// ── Shared helpers ──────────────────────────────────────────────────────────
function an_money(float $v, string $cur = 'IQD'): string
{
	$decimals = ($cur === 'USD' && fmod($v, 1.0) != 0.0) ? 2 : 0;
	$n = number_format($v, $decimals);
	return $cur === 'USD' ? '$' . $n : $n . ' IQD';
}

// The "Gechkari" quick-entry feature stores into the older `measurements`
// table (work_type_id = 1) rather than `project_work_entries`; every query
// below treats both as one "work entries" concept via this CTE, so the
// two-table split is an implementation detail the page never surfaces.
$unifiedCte = "
	WITH unified_entries AS (
		SELECT m.id, m.measurement_date AS entry_date, 'Gechkari' AS work_type_name,
		       m.total_price, 'USD' AS currency_type,
		       CASE WHEN m.status = 'approved' THEN 'accepted' ELSE m.status END AS status,
		       m.notes, m.created_at
		FROM measurements m WHERE m.work_type_id = 1
		UNION ALL
		SELECT e.id, e.work_date AS entry_date, COALESCE(t.work_type_name, e.work_type_key) AS work_type_name,
		       e.total_price, e.currency_type, e.status, e.notes, e.created_at
		FROM project_work_entries e
		LEFT JOIN project_work_types t ON t.work_type_key = e.work_type_key
	)
";

$statusMeta = [
	'accepted' => ['label' => 'Approved',     'class' => 'ok'],
	'medium'   => ['label' => 'Under Review', 'class' => 'warn'],
	'draft'    => ['label' => 'Waiting',      'class' => 'muted'],
	'rejected' => ['label' => 'Rejected',     'class' => 'bad'],
];
function an_status(array $meta, string $raw): array
{
	$key = strtolower(trim($raw));
	return $meta[$key] ?? ['label' => ucfirst($key ?: 'Unknown'), 'class' => 'muted'];
}

// ── Project structure ────────────────────────────────────────────────────────
$structure = $conn->query("
	SELECT
		(SELECT COUNT(*) FROM buildings)  AS buildings,
		(SELECT COUNT(*) FROM floors)     AS floors,
		(SELECT COUNT(*) FROM apartments) AS apartments
")->fetch_assoc();

// ── Unified work-entry summary: counts + value split by currency ───────────
$entryCount = 0;
$approvedByCur = [];
$reviewCount = 0;   // draft + medium: awaiting a decision
$rejectedCount = 0;

$res = $conn->query($unifiedCte . " SELECT status, currency_type, COUNT(*) c, COALESCE(SUM(total_price),0) v
                                    FROM unified_entries GROUP BY status, currency_type");
while ($res && $r = $res->fetch_assoc()) {
	$st = strtolower(trim((string)$r['status']));
	$cur = $r['currency_type'] ?: 'IQD';
	$c = (int)$r['c'];
	$v = (float)$r['v'];
	$entryCount += $c;
	if ($st === 'accepted') {
		$approvedByCur[$cur] = ($approvedByCur[$cur] ?? 0) + $v;
	} elseif ($st === 'draft' || $st === 'medium') {
		$reviewCount += $c;
	} elseif ($st === 'rejected') {
		$rejectedCount += $c;
	}
}

// ── Value by Category chart (approved work only, dominant currency) ────────
$dominantCur = 'IQD';
$best = -1;
foreach ($approvedByCur as $cur => $v) {
	if ($v > $best) { $best = $v; $dominantCur = $cur; }
}

$catValues = [];
$stmt = $conn->prepare($unifiedCte . " SELECT work_type_name, COALESCE(SUM(total_price),0) v
                                       FROM unified_entries
                                       WHERE status = 'accepted' AND currency_type = ?
                                       GROUP BY work_type_name HAVING v > 0
                                       ORDER BY v DESC LIMIT 8");
$stmt->bind_param('s', $dominantCur);
$stmt->execute();
$r2 = $stmt->get_result();
while ($row = $r2->fetch_assoc()) { $catValues[$row['work_type_name']] = (float)$row['v']; }
$stmt->close();
$chartLabels = array_keys($catValues);
$chartValues = array_values($catValues);

// ── Recent activity (latest 8, unified, currency + status aware) ───────────
$recentRows = [];
$res = $conn->query($unifiedCte . " SELECT entry_date, work_type_name, total_price, currency_type, status, notes
                                    FROM unified_entries ORDER BY created_at DESC, id DESC LIMIT 8");
while ($res && $r = $res->fetch_assoc()) { $recentRows[] = $r; }

// ── Report builder filter sources ──────────────────────────────────────────
$workTypes = [['key' => 'gechkari', 'name' => 'Gechkari']];
$workTypeSeen = ['gechkari' => true];
$workTypesQuery = $conn->query("SELECT work_type_key, work_type_name FROM project_work_types WHERE is_active = 1 ORDER BY work_type_name");
if ($workTypesQuery) {
	while ($row = $workTypesQuery->fetch_assoc()) {
		$key = strtolower(trim((string)$row['work_type_key']));
		if ($key === '' || isset($workTypeSeen[$key])) {
			continue;
		}
		$workTypes[] = [
			'key' => $key,
			'name' => trim((string)$row['work_type_name']) !== '' ? (string)$row['work_type_name'] : ucfirst($key),
		];
		$workTypeSeen[$key] = true;
	}
}

$buildingsList = [];
$buildingsQuery = $conn->query("SELECT id, building_name FROM buildings ORDER BY building_name");
if ($buildingsQuery) {
	while ($row = $buildingsQuery->fetch_assoc()) {
		$buildingsList[] = ['id' => (int)$row['id'], 'name' => $row['building_name']];
	}
}

$floorsList = [];
$floorsQuery = $conn->query("SELECT id, building_id, floor_name, floor_number FROM floors ORDER BY building_id, floor_number, floor_name");
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
$apartmentsQuery = $conn->query("SELECT id, building_id, floor_id, apartment_number FROM apartments ORDER BY building_id, floor_id, apartment_number");
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

$pageTitle = 'Analysis - Green World Towers';
$pageCss = 'analytics.css';
$activePage = 'analytics';
require_once 'partials/header.php';
?>
<div id="analytics-page" class="dashboard-container">
<?php require_once 'partials/sidebar.php'; ?>
	<main class="main-content">
		<header class="page-header">
			<h1><i class="fas fa-chart-line"></i> Analysis</h1>
			<div class="user-info">
				<span>Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?></span>
			</div>
		</header>

		<div class="content-wrapper analytics-wrapper">

			<!-- ═══ OVERVIEW ═══ -->
			<div class="analytics-section-head">
				<h2><i class="fas fa-gauge-high"></i> Overview</h2>
				<p>Where things stand right now, at a glance.</p>
			</div>

			<div class="analytics-kpis">
				<div class="analytics-kpi-card">
					<span class="kpi-label">Work Entries</span>
					<strong><?php echo number_format($entryCount); ?></strong>
					<span class="kpi-sub">recorded in total</span>
				</div>
				<div class="analytics-kpi-card">
					<span class="kpi-label">Approved Value</span>
					<?php if ($approvedByCur): $first = true; foreach ($approvedByCur as $cur => $v): if ($v <= 0) continue; ?>
						<strong class="<?php echo $first ? '' : 'kpi-strong-sub'; ?>"><?php echo an_money($v, $cur); ?></strong>
					<?php $first = false; endforeach; else: ?>
						<strong>0</strong>
					<?php endif; ?>
					<span class="kpi-sub">work that's been signed off</span>
				</div>
				<div class="analytics-kpi-card">
					<span class="kpi-label">Awaiting Decision</span>
					<strong><?php echo number_format($reviewCount); ?></strong>
					<span class="kpi-sub">entries need a review</span>
				</div>
				<div class="analytics-kpi-card">
					<span class="kpi-label">Rejected</span>
					<strong><?php echo number_format($rejectedCount); ?></strong>
					<span class="kpi-sub">entries turned down</span>
				</div>
				<div class="analytics-kpi-card analytics-kpi-card-wide">
					<span class="kpi-label">Project Size</span>
					<strong><?php echo (int)$structure['buildings']; ?> <span class="kpi-unit">buildings</span></strong>
					<span class="kpi-sub"><?php echo (int)$structure['floors']; ?> floors · <?php echo (int)$structure['apartments']; ?> apartments</span>
				</div>
			</div>

			<div class="analytics-grid">
				<section class="analytics-card">
					<div class="analytics-card-head">
						<h2><i class="fas fa-chart-bar"></i> Approved Value by Category</h2>
						<?php if ($chartLabels): ?><span class="analytics-card-note"><?php echo htmlspecialchars($dominantCur); ?></span><?php endif; ?>
					</div>
					<?php if ($chartLabels): ?>
					<div class="analytics-chart-wrap">
						<canvas id="analysisChart"></canvas>
					</div>
					<?php else: ?>
					<div class="analytics-empty"><i class="fas fa-chart-bar"></i> No approved work yet.</div>
					<?php endif; ?>
				</section>

				<section class="analytics-card">
					<div class="analytics-card-head">
						<h2><i class="fas fa-history"></i> Recent Activity</h2>
					</div>
					<?php if (empty($recentRows)): ?>
						<div class="analytics-empty"><i class="fas fa-clipboard"></i> No work entries yet.</div>
					<?php else: ?>
						<div class="analytics-table-wrap">
							<table class="analytics-table">
								<thead>
									<tr>
										<th>Date</th>
										<th>Type</th>
										<th>Value</th>
										<th>Status</th>
									</tr>
								</thead>
								<tbody>
									<?php foreach ($recentRows as $row): $meta = an_status($statusMeta, $row['status']); ?>
										<tr>
											<td><?php echo htmlspecialchars(date('d/m/Y', strtotime((string)$row['entry_date']))); ?></td>
											<td><?php echo htmlspecialchars((string)$row['work_type_name']); ?></td>
											<td><?php echo an_money((float)$row['total_price'], (string)($row['currency_type'] ?: 'IQD')); ?></td>
											<td><span class="analytics-status analytics-status-<?php echo $meta['class']; ?>"><?php echo $meta['label']; ?></span></td>
										</tr>
									<?php endforeach; ?>
								</tbody>
							</table>
						</div>
					<?php endif; ?>
				</section>
			</div>

			<!-- ═══ CUSTOM REPORT BUILDER ═══ -->
			<div class="analytics-section-head analytics-section-head-report">
				<h2><i class="fas fa-filter"></i> Custom Report Builder</h2>
				<p>Slice work entries by category, location, stakeholder, date, or status.</p>
			</div>

			<section class="analytics-card analytics-report-card">
				<div class="analytics-report-body">
					<div class="analytics-filter-grid">
						<div class="analytics-filter-group">
							<label for="report-breakdown">Breakdown</label>
							<select id="report-breakdown">
								<option value="category">By Category</option>
								<option value="building">By Building</option>
								<option value="floor">By Floor</option>
								<option value="apartment">By Apartment</option>
								<option value="stakeholder">By Stakeholder</option>
							</select>
						</div>
						<div class="analytics-filter-group">
							<label for="report-work-type">Category</label>
							<select id="report-work-type">
								<option value="">All Categories</option>
								<?php foreach ($workTypes as $wt): ?>
									<option value="<?php echo htmlspecialchars($wt['key']); ?>"><?php echo htmlspecialchars($wt['name']); ?></option>
								<?php endforeach; ?>
							</select>
						</div>
						<div class="analytics-filter-group">
							<label for="report-status">Status</label>
							<select id="report-status">
								<option value="">All Statuses</option>
								<option value="accepted">Approved</option>
								<option value="medium">Under Review</option>
								<option value="draft">Waiting</option>
								<option value="rejected">Rejected</option>
							</select>
						</div>
						<div class="analytics-filter-group">
							<label for="report-building">Building</label>
							<select id="report-building">
								<option value="">All Buildings</option>
								<?php foreach ($buildingsList as $building): ?>
									<option value="<?php echo (int)$building['id']; ?>"><?php echo htmlspecialchars($building['name']); ?></option>
								<?php endforeach; ?>
							</select>
						</div>
						<div class="analytics-filter-group">
							<label for="report-floor">Floor</label>
							<select id="report-floor">
								<option value="">All Floors</option>
							</select>
						</div>
						<div class="analytics-filter-group">
							<label for="report-apartment">Apartment</label>
							<select id="report-apartment">
								<option value="">All Apartments</option>
							</select>
						</div>
						<div class="analytics-filter-group">
							<label for="report-date-from">From Date</label>
							<input type="date" id="report-date-from">
						</div>
						<div class="analytics-filter-group">
							<label for="report-date-to">To Date</label>
							<input type="date" id="report-date-to">
						</div>
						<div class="analytics-filter-actions">
							<button type="button" class="btn btn-secondary" id="report-reset-btn"><i class="fas fa-undo"></i> Reset</button>
							<button type="button" class="btn btn-primary" id="report-run-btn"><i class="fas fa-chart-column"></i> Show Report</button>
						</div>
					</div>

					<div class="analytics-report-summary" id="report-summary-cards">
						<div class="analytics-mini-card">
							<span>Entries</span>
							<strong id="report-summary-entries">0</strong>
						</div>
						<div class="analytics-mini-card">
							<span>Groups</span>
							<strong id="report-summary-groups">0</strong>
						</div>
						<div class="analytics-mini-card">
							<span>Total Quantity</span>
							<strong id="report-summary-quantity">0.00</strong>
						</div>
						<div class="analytics-mini-card">
							<span>Total Value</span>
							<strong id="report-summary-value">0</strong>
						</div>
					</div>

					<div class="analytics-grid analytics-grid-report">
						<section class="analytics-card analytics-card-inner">
							<div class="analytics-card-head analytics-card-head-inner">
								<h2><i class="fas fa-chart-pie"></i> Report Chart</h2>
								<span class="analytics-card-note" id="report-chart-currency"></span>
							</div>
							<div class="analytics-chart-wrap analytics-chart-wrap-report">
								<canvas id="detailAnalysisChart"></canvas>
							</div>
						</section>
						<section class="analytics-card analytics-card-inner">
							<div class="analytics-card-head analytics-card-head-inner">
								<h2><i class="fas fa-table-list"></i> Group Summary</h2>
							</div>
							<div class="analytics-table-wrap">
								<table class="analytics-table">
									<thead>
										<tr>
											<th>Group</th>
											<th>Entries</th>
											<th>Quantity</th>
											<th>Total Value</th>
										</tr>
									</thead>
									<tbody id="report-groups-tbody">
										<tr><td colspan="4" class="analytics-empty-row">Run a report to see grouped data.</td></tr>
									</tbody>
								</table>
							</div>
						</section>
					</div>

					<section class="analytics-card analytics-card-inner analytics-details-card">
						<div class="analytics-card-head analytics-card-head-inner">
							<h2><i class="fas fa-list-check"></i> Detailed Entries</h2>
						</div>
						<div class="analytics-table-wrap">
							<table class="analytics-table analytics-detail-table">
								<thead>
									<tr>
										<th>Date</th>
										<th>Category</th>
										<th>Building</th>
										<th>Floor</th>
										<th>Apartment</th>
										<th>Stakeholder</th>
										<th>Quantity</th>
										<th>Total</th>
										<th>Status</th>
									</tr>
								</thead>
								<tbody id="report-details-tbody">
									<tr><td colspan="9" class="analytics-empty-row">Run a report to see detailed entries.</td></tr>
								</tbody>
							</table>
						</div>
					</section>
				</div>
			</section>
		</div>
	</main>
</div>

<script>
const analysisLabels = <?php echo json_encode($chartLabels, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
const analysisValues = <?php echo json_encode($chartValues, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
const analyticsFloors = <?php echo json_encode($floorsList, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
const analyticsApartments = <?php echo json_encode($apartmentsList, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
const statusLabels = { accepted: 'Approved', medium: 'Under Review', draft: 'Waiting', rejected: 'Rejected' };
const statusClasses = { accepted: 'ok', medium: 'warn', draft: 'muted', rejected: 'bad' };

let detailChartInstance = null;

if (analysisLabels.length > 0) {
	const ctx = document.getElementById('analysisChart');
	if (ctx) {
		new Chart(ctx, {
			type: 'bar',
			data: {
				labels: analysisLabels,
				datasets: [{
					label: 'Total Value',
					data: analysisValues,
					backgroundColor: 'rgba(59, 130, 246, 0.65)',
					borderColor: 'rgba(96, 165, 250, 1)',
					borderWidth: 1.5,
					borderRadius: 8,
					maxBarThickness: 48,
				}]
			},
			options: {
				responsive: true,
				maintainAspectRatio: false,
				plugins: {
					legend: { display: false }
				},
				scales: {
					x: {
						ticks: { color: '#cbd5e1' },
						grid: { display: false }
					},
					y: {
						ticks: { color: '#94a3b8' },
						grid: { color: 'rgba(148, 163, 184, 0.12)' }
					}
				}
			}
		});
	}
}

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

function fillFloorOptions(buildingId, selectedFloorId = '') {
	const floorSelect = document.getElementById('report-floor');
	if (!floorSelect) return;
	floorSelect.innerHTML = '<option value="">All Floors</option>';
	analyticsFloors
		.filter(floor => !buildingId || String(floor.building_id) === String(buildingId))
		.forEach(floor => {
			const option = document.createElement('option');
			option.value = floor.id;
			option.textContent = floor.name;
			if (String(selectedFloorId) === String(floor.id)) {
				option.selected = true;
			}
			floorSelect.appendChild(option);
		});
}

function fillApartmentOptions(floorId, selectedApartmentId = '') {
	const apartmentSelect = document.getElementById('report-apartment');
	if (!apartmentSelect) return;
	apartmentSelect.innerHTML = '<option value="">All Apartments</option>';
	analyticsApartments
		.filter(apartment => !floorId || String(apartment.floor_id) === String(floorId))
		.forEach(apartment => {
			const option = document.createElement('option');
			option.value = apartment.id;
			option.textContent = apartment.number;
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
				label: 'Total Value',
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
		tbody.innerHTML = '<tr><td colspan="4" class="analytics-empty-row">No grouped results for these filters.</td></tr>';
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
		tbody.innerHTML = '<tr><td colspan="9" class="analytics-empty-row">No detailed entries found for these filters.</td></tr>';
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
					groupsTbody.innerHTML = '<tr><td colspan="4" class="analytics-empty-row">' + escHtml(data.message || 'Unable to load report.') + '</td></tr>';
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
				groupsTbody.innerHTML = '<tr><td colspan="4" class="analytics-empty-row">Failed to load report. Please try again.</td></tr>';
			}
		});
}

document.getElementById('report-building').addEventListener('change', function() {
	fillFloorOptions(this.value, '');
	fillApartmentOptions('', '');
});

document.getElementById('report-floor').addEventListener('change', function() {
	fillApartmentOptions(this.value, '');
});

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

fillFloorOptions('', '');
fillApartmentOptions('', '');
runDetailedReport();
</script>
</body>
</html>
