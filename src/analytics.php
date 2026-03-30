<?php
session_start();
require_once '../config.php';

if (empty($_SESSION['user_id'])) {
	header('Location: index.html');
	exit;
}

$user_id = (int)$_SESSION['user_id'];
$stmt = $conn->prepare('SELECT p.name FROM permissions p JOIN role_permissions rp ON p.id = rp.permission_id JOIN users u ON rp.role_id = u.role_id WHERE u.id = ?');
$stmt->bind_param('i', $user_id);
$stmt->execute();
$res = $stmt->get_result();
$permissions = [];
while ($row = $res->fetch_assoc()) {
	$permissions[] = $row['name'];
}
$stmt->close();

if (!in_array('data_entry', $permissions, true) && !in_array('project_settings', $permissions, true)) {
	header('Location: dashboard.php?error=access_denied');
	exit;
}

$summary = [
	'buildings' => 0,
	'apartments' => 0,
	'gechkari_entries' => 0,
	'generic_entries' => 0,
	'total_value' => 0,
];

$result = $conn->query("SELECT COUNT(*) AS c FROM buildings");
if ($result && ($row = $result->fetch_assoc())) {
	$summary['buildings'] = (int)$row['c'];
}

$result = $conn->query("SELECT COUNT(*) AS c FROM apartments");
if ($result && ($row = $result->fetch_assoc())) {
	$summary['apartments'] = (int)$row['c'];
}

$result = $conn->query("SELECT COUNT(*) AS c, COALESCE(SUM(total_price), 0) AS total_value FROM measurements WHERE work_type_id = 1");
if ($result && ($row = $result->fetch_assoc())) {
	$summary['gechkari_entries'] = (int)$row['c'];
	$summary['total_value'] += (float)$row['total_value'];
}

$conn->query("CREATE TABLE IF NOT EXISTS project_work_entries (
	id INT AUTO_INCREMENT PRIMARY KEY,
	work_date DATE NOT NULL,
	engineer_name VARCHAR(180) NOT NULL,
	work_type_key VARCHAR(80) NOT NULL,
	stakeholder_id INT NULL,
	subpart_id INT NULL,
	quantity DECIMAL(12,2) NOT NULL DEFAULT 0,
	unit_price DECIMAL(12,2) NOT NULL DEFAULT 0,
	total_price DECIMAL(14,2) NOT NULL DEFAULT 0,
	metric_type VARCHAR(30) NOT NULL DEFAULT 'unit',
	currency_type VARCHAR(20) NOT NULL DEFAULT 'USD',
	building_id INT NOT NULL,
	floor_id INT NOT NULL,
	apartment_id INT NOT NULL,
	notes TEXT NULL,
	status VARCHAR(30) NOT NULL DEFAULT 'draft',
	created_by INT NULL,
	created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
	updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	INDEX idx_work_type_key (work_type_key),
	INDEX idx_apartment (apartment_id),
	INDEX idx_work_date (work_date)
)");

$result = $conn->query("SELECT COUNT(*) AS c, COALESCE(SUM(total_price), 0) AS total_value FROM project_work_entries");
if ($result && ($row = $result->fetch_assoc())) {
	$summary['generic_entries'] = (int)$row['c'];
	$summary['total_value'] += (float)$row['total_value'];
}

$chartLabels = [];
$chartValues = [];
$chartQuery = $conn->query("SELECT label, total_value FROM (
	SELECT 'Gechkari' AS label, COALESCE(SUM(total_price), 0) AS total_value FROM measurements WHERE work_type_id = 1
	UNION ALL
	SELECT COALESCE(pwt.work_type_name, pwe.work_type_key) AS label, COALESCE(SUM(pwe.total_price), 0) AS total_value
	FROM project_work_entries pwe
	LEFT JOIN project_work_types pwt ON pwt.work_type_key = pwe.work_type_key
	GROUP BY COALESCE(pwt.work_type_name, pwe.work_type_key)
) totals WHERE total_value > 0 ORDER BY total_value DESC LIMIT 8");
if ($chartQuery) {
	while ($row = $chartQuery->fetch_assoc()) {
		$chartLabels[] = $row['label'];
		$chartValues[] = (float)$row['total_value'];
	}
}

$recentRows = [];
$recentQuery = $conn->query("SELECT * FROM (
	SELECT measurement_date AS entry_date, 'Gechkari' AS work_type_name, total_price, notes, created_at
	FROM measurements
	WHERE work_type_id = 1
	UNION ALL
	SELECT pwe.work_date AS entry_date, COALESCE(pwt.work_type_name, pwe.work_type_key) AS work_type_name, pwe.total_price, pwe.notes, pwe.created_at
	FROM project_work_entries pwe
	LEFT JOIN project_work_types pwt ON pwt.work_type_key = pwe.work_type_key
) recent_entries ORDER BY created_at DESC LIMIT 8");
if ($recentQuery) {
	while ($row = $recentQuery->fetch_assoc()) {
		$recentRows[] = $row;
	}
}

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
			<div class="analytics-kpis">
				<div class="analytics-kpi-card">
					<span class="kpi-label">Buildings</span>
					<strong><?php echo number_format($summary['buildings']); ?></strong>
				</div>
				<div class="analytics-kpi-card">
					<span class="kpi-label">Apartments</span>
					<strong><?php echo number_format($summary['apartments']); ?></strong>
				</div>
				<div class="analytics-kpi-card">
					<span class="kpi-label">Gechkari Entries</span>
					<strong><?php echo number_format($summary['gechkari_entries']); ?></strong>
				</div>
				<div class="analytics-kpi-card">
					<span class="kpi-label">Other Entries</span>
					<strong><?php echo number_format($summary['generic_entries']); ?></strong>
				</div>
				<div class="analytics-kpi-card analytics-kpi-card-wide">
					<span class="kpi-label">Total Recorded Value</span>
					<strong>$<?php echo number_format($summary['total_value'], 2); ?></strong>
				</div>
			</div>

			<div class="analytics-grid">
				<section class="analytics-card">
					<div class="analytics-card-head">
						<h2><i class="fas fa-chart-bar"></i> Value by Work Type</h2>
					</div>
					<div class="analytics-chart-wrap">
						<canvas id="analysisChart"></canvas>
					</div>
				</section>

				<section class="analytics-card">
					<div class="analytics-card-head">
						<h2><i class="fas fa-history"></i> Recent Activity</h2>
					</div>
					<?php if (empty($recentRows)): ?>
						<div class="analytics-empty">No analysis data yet.</div>
					<?php else: ?>
						<div class="analytics-table-wrap">
							<table class="analytics-table">
								<thead>
									<tr>
										<th>Date</th>
										<th>Type</th>
										<th>Value</th>
										<th>Notes</th>
									</tr>
								</thead>
								<tbody>
									<?php foreach ($recentRows as $row): ?>
										<tr>
											<td><?php echo htmlspecialchars(date('d/m/Y', strtotime((string)$row['entry_date']))); ?></td>
											<td><?php echo htmlspecialchars((string)$row['work_type_name']); ?></td>
											<td>$<?php echo number_format((float)$row['total_price'], 2); ?></td>
											<td class="analytics-notes"><?php echo htmlspecialchars(trim((string)$row['notes']) !== '' ? (string)$row['notes'] : '—'); ?></td>
										</tr>
									<?php endforeach; ?>
								</tbody>
							</table>
						</div>
					<?php endif; ?>
				</section>
			</div>

			<section class="analytics-card analytics-report-card">
				<div class="analytics-card-head">
					<h2><i class="fas fa-filter"></i> Detailed Analysis</h2>
				</div>
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
							<strong id="report-summary-value">$0.00</strong>
						</div>
					</div>

					<div class="analytics-grid analytics-grid-report">
						<section class="analytics-card analytics-card-inner">
							<div class="analytics-card-head analytics-card-head-inner">
								<h2><i class="fas fa-chart-pie"></i> Report Chart</h2>
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
									</tr>
								</thead>
								<tbody id="report-details-tbody">
									<tr><td colspan="8" class="analytics-empty-row">Run a report to see detailed entries.</td></tr>
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

function formatCurrency(value) {
	return '$' + Number(value || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
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

function renderReportChart(rows) {
	const ctx = document.getElementById('detailAnalysisChart');
	if (!ctx) return;
	if (detailChartInstance) {
		detailChartInstance.destroy();
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
				data: rows.map(row => row.total_value),
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

function renderGroupRows(rows) {
	const tbody = document.getElementById('report-groups-tbody');
	if (!tbody) return;
	if (!rows.length) {
		tbody.innerHTML = '<tr><td colspan="4" class="analytics-empty-row">No grouped results for these filters.</td></tr>';
		return;
	}

	tbody.innerHTML = rows.map(row =>
		'<tr>' +
			'<td>' + row.label + '</td>' +
			'<td>' + Number(row.entries_count).toLocaleString() + '</td>' +
			'<td>' + Number(row.total_quantity).toFixed(2) + ' ' + (row.primary_metric || '') + '</td>' +
			'<td>' + formatCurrency(row.total_value) + '</td>' +
		'</tr>'
	).join('');
}

function renderDetailRows(rows) {
	const tbody = document.getElementById('report-details-tbody');
	if (!tbody) return;
	if (!rows.length) {
		tbody.innerHTML = '<tr><td colspan="8" class="analytics-empty-row">No detailed entries found for these filters.</td></tr>';
		return;
	}

	tbody.innerHTML = rows.map(row =>
		'<tr>' +
			'<td>' + row.entry_date_display + '</td>' +
			'<td>' + row.work_type_name + '</td>' +
			'<td>' + row.building_name + '</td>' +
			'<td>' + row.floor_name + '</td>' +
			'<td>' + row.apartment_label + '</td>' +
			'<td>' + row.stakeholder_name + '</td>' +
			'<td>' + Number(row.quantity).toFixed(2) + ' ' + row.metric_type + '</td>' +
			'<td>' + formatCurrency(row.total_price) + '</td>' +
		'</tr>'
	).join('');
}

function runDetailedReport() {
	const params = new URLSearchParams({
		breakdown: document.getElementById('report-breakdown').value,
		work_type_key: document.getElementById('report-work-type').value,
		building_id: document.getElementById('report-building').value,
		floor_id: document.getElementById('report-floor').value,
		apartment_id: document.getElementById('report-apartment').value,
		date_from: document.getElementById('report-date-from').value,
		date_to: document.getElementById('report-date-to').value
	});

	fetch('get_dynamic_report.php?' + params.toString())
		.then(response => response.json())
		.then(data => {
			if (!data.success) {
				return;
			}

			document.getElementById('report-summary-entries').textContent = Number(data.summary.entries_count || 0).toLocaleString();
			document.getElementById('report-summary-groups').textContent = Number(data.summary.groups_count || 0).toLocaleString();
			document.getElementById('report-summary-quantity').textContent = Number(data.summary.total_quantity || 0).toFixed(2);
			document.getElementById('report-summary-value').textContent = formatCurrency(data.summary.total_value || 0);

			renderReportChart(data.groups || []);
			renderGroupRows(data.groups || []);
			renderDetailRows(data.details || []);
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
