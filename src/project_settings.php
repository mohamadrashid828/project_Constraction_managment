<?php
session_start();
if (empty($_SESSION['user_id'])) {
    header('Location: index.html');
    exit;
}
require_once '../config.php';

$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare('SELECT p.name FROM permissions p JOIN role_permissions rp ON p.id = rp.permission_id JOIN users u ON rp.role_id = u.role_id WHERE u.id = ?');
$stmt->bind_param('i', $user_id);
$stmt->execute();
$res = $stmt->get_result();
$permissions = [];
while ($row = $res->fetch_assoc()) {
    $permissions[] = $row['name'];
}
$stmt->close();

if (!in_array('project_settings', $permissions)) {
    header('Location: dashboard.php?error=access_denied');
    exit;
}

$message = '';
$message_type = 'success';

// Dynamic work-type configuration tables
$conn->query("CREATE TABLE IF NOT EXISTS project_work_types (
    id INT AUTO_INCREMENT PRIMARY KEY,
    work_type_name VARCHAR(120) NOT NULL,
    work_type_key VARCHAR(80) NOT NULL UNIQUE,
    quantity_unit VARCHAR(30) NOT NULL DEFAULT 'm²',
    scope_level VARCHAR(30) NOT NULL DEFAULT 'apartment',
    pricing_mode VARCHAR(30) NOT NULL DEFAULT 'per_unit',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_work_type_key (work_type_key)
)");

$conn->query("CREATE TABLE IF NOT EXISTS project_work_type_fields (
    id INT AUTO_INCREMENT PRIMARY KEY,
    work_type_id INT NOT NULL,
    field_key VARCHAR(80) NOT NULL,
    field_label VARCHAR(120) NOT NULL,
    input_type VARCHAR(30) NOT NULL DEFAULT 'number',
    unit_label VARCHAR(30) DEFAULT NULL,
    field_role VARCHAR(20) NOT NULL DEFAULT 'meta',
    is_required TINYINT(1) NOT NULL DEFAULT 0,
    sort_order INT NOT NULL DEFAULT 0,
    options_json TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_work_field (work_type_id, field_key),
    INDEX idx_work_type_id (work_type_id)
)");

// Handle building creation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save_work_type'])) {
        $work_type_name = trim($_POST['work_type_name'] ?? '');
        $work_type_key = strtolower(preg_replace('/[^a-z0-9_]+/', '_', $work_type_name));
        $work_type_key = trim($work_type_key, '_');
        if ($work_type_name && $work_type_key) {
            $quantity_unit = 'm²';
            $scope_level = 'apartment';
            $pricing_mode = 'per_unit';
            $existingId = 0;
            $stmt = $conn->prepare("SELECT id FROM project_work_types WHERE work_type_key = ? LIMIT 1");
            $stmt->bind_param("s", $work_type_key);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($row = $res->fetch_assoc()) {
                $existingId = (int)$row['id'];
            }
            $stmt->close();

            if ($existingId > 0) {
                $stmt = $conn->prepare("UPDATE project_work_types SET work_type_name = ?, is_active = 1, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                $stmt->bind_param("si", $work_type_name, $existingId);
                if ($stmt->execute()) {
                    $message = 'Category restored successfully!';
                    $message_type = 'success';
                } else {
                    $message = 'Error restoring category.';
                    $message_type = 'error';
                }
            } else {
                $stmt = $conn->prepare("INSERT INTO project_work_types (work_type_name, work_type_key, quantity_unit, scope_level, pricing_mode, created_by) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("sssssi", $work_type_name, $work_type_key, $quantity_unit, $scope_level, $pricing_mode, $user_id);
                if ($stmt->execute()) {
                    $message = 'Category created successfully!';
                    $message_type = 'success';
                } else {
                    $message = 'Error creating category (name may already exist).';
                    $message_type = 'error';
                }
            }
            $stmt->close();
        } else {
            $message = 'Please enter a category name.';
            $message_type = 'error';
        }
    }

    if (isset($_POST['delete_work_type'])) {
        $work_type_id = (int)($_POST['work_type_id'] ?? 0);
        if ($work_type_id > 0) {
            $stmt = $conn->prepare("DELETE FROM project_work_type_fields WHERE work_type_id = ?");
            $stmt->bind_param("i", $work_type_id);
            $stmt->execute();
            $stmt->close();

            $stmt = $conn->prepare("UPDATE project_work_types SET is_active = 0, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            $stmt->bind_param("i", $work_type_id);
            if ($stmt->execute()) {
                $message = 'Category deleted successfully!';
                $message_type = 'success';
            } else {
                $message = 'Error deleting category.';
                $message_type = 'error';
            }
            $stmt->close();
        }
    }

    if (isset($_POST['add_building'])) {
        $building_name = trim($_POST['building_name']);
        $total_area = (float)$_POST['total_area'];
        $comments = trim($_POST['comments']);

        if ($building_name && $total_area > 0) {
            $stmt = $conn->prepare("INSERT INTO buildings (building_name, total_area, comments) VALUES (?, ?, ?)");
            $stmt->bind_param("sds", $building_name, $total_area, $comments);
            if ($stmt->execute()) {
                $message = 'Building created successfully!';
                $message_type = 'success';
            } else {
                $message = 'Error creating building.';
                $message_type = 'error';
            }
            $stmt->close();
        }
    }

    if (isset($_POST['update_building'])) {
        $building_id = (int)$_POST['building_id'];
        $building_name = trim($_POST['building_name']);
        $total_area = (float)$_POST['total_area'];
        $comments = trim($_POST['comments']);

        if ($building_id && $building_name && $total_area > 0) {
            $stmt = $conn->prepare("UPDATE buildings SET building_name = ?, total_area = ?, comments = ? WHERE id = ?");
            $stmt->bind_param("sdsi", $building_name, $total_area, $comments, $building_id);
            if ($stmt->execute()) {
                $message = 'Building updated successfully!';
                $message_type = 'success';
            } else {
                $message = 'Error updating building.';
                $message_type = 'error';
            }
            $stmt->close();
        }
    }

    if (isset($_POST['delete_building'])) {
        $building_id = (int)$_POST['building_id'];

        if ($building_id) {
            $stmt = $conn->prepare("DELETE FROM buildings WHERE id = ?");
            $stmt->bind_param("i", $building_id);
            if ($stmt->execute()) {
                $message = 'Building deleted successfully!';
                $message_type = 'success';
            } else {
                $message = 'Error deleting building.';
                $message_type = 'error';
            }
            $stmt->close();
        }
    }

    if (isset($_POST['add_floor'])) {
        $building_id = (int)$_POST['building_id'];
        $floor_name = trim($_POST['floor_name']);
        $floor_number = (int)$_POST['floor_number'];
        $floor_area = (float)$_POST['floor_area'];

        if ($building_id && $floor_name && $floor_number > 0 && $floor_area > 0) {
            $stmt = $conn->prepare("INSERT INTO floors (building_id, floor_number, floor_name, area) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("iisi", $building_id, $floor_number, $floor_name, $floor_area);
            if ($stmt->execute()) {
                $message = 'Floor created successfully!';
                $message_type = 'success';
            } else {
                $message = 'Error creating floor.';
                $message_type = 'error';
            }
            $stmt->close();
        }
    }

    if (isset($_POST['update_floor'])) {
        $floor_id = (int)$_POST['floor_id'];
        $floor_number = (int)$_POST['floor_number'];
        $floor_name = trim($_POST['floor_name']);
        $floor_area = (float)$_POST['floor_area'];

        if ($floor_id && $floor_number > 0 && $floor_name && $floor_area > 0) {
            $stmt = $conn->prepare("UPDATE floors SET floor_number = ?, floor_name = ?, area = ? WHERE id = ?");
            $stmt->bind_param("isdi", $floor_number, $floor_name, $floor_area, $floor_id);
            if ($stmt->execute()) {
                $message = 'Floor updated successfully!';
                $message_type = 'success';
            } else {
                $message = 'Error updating floor.';
                $message_type = 'error';
            }
            $stmt->close();
        }
    }

    if (isset($_POST['delete_floor'])) {
        $floor_id = (int)$_POST['floor_id'];

        if ($floor_id) {
            $stmt = $conn->prepare("DELETE FROM floors WHERE id = ?");
            $stmt->bind_param("i", $floor_id);
            if ($stmt->execute()) {
                $message = 'Floor deleted successfully!';
                $message_type = 'success';
            } else {
                $message = 'Error deleting floor.';
                $message_type = 'error';
            }
            $stmt->close();
        }
    }

    if (isset($_POST['add_apartment'])) {
        $floor_id = (int)$_POST['floor_id'];
        $apartment_number = trim($_POST['apartment_number']);
        $apartment_type = trim($_POST['apartment_type']);
        $apartment_area = (float)$_POST['apartment_area'];

        if ($floor_id && $apartment_number && $apartment_type && $apartment_area > 0) {
            $stmt = $conn->prepare("INSERT INTO apartments (floor_id, building_id, apartment_number, apartment_type, area_sqm) VALUES (?, (SELECT building_id FROM floors WHERE id = ?), ?, ?, ?)");
            $stmt->bind_param("iissd", $floor_id, $floor_id, $apartment_number, $apartment_type, $apartment_area);
            if ($stmt->execute()) {
                $message = 'Apartment created successfully!';
                $message_type = 'success';
            } else {
                $message = 'Error creating apartment.';
                $message_type = 'error';
            }
            $stmt->close();
        }
    }

    if (isset($_POST['update_apartment'])) {
        $apartment_id = (int)$_POST['apartment_id'];
        $apartment_number = trim($_POST['apartment_number']);
        $apartment_type = trim($_POST['apartment_type']);
        $apartment_area = (float)$_POST['apartment_area'];

        if ($apartment_id && $apartment_number && $apartment_type && $apartment_area > 0) {
            $stmt = $conn->prepare("UPDATE apartments SET apartment_number = ?, apartment_type = ?, area_sqm = ? WHERE id = ?");
            $stmt->bind_param("ssdi", $apartment_number, $apartment_type, $apartment_area, $apartment_id);
            if ($stmt->execute()) {
                $message = 'Apartment updated successfully!';
                $message_type = 'success';
            } else {
                $message = 'Error updating apartment.';
                $message_type = 'error';
            }
            $stmt->close();
        }
    }

    if (isset($_POST['delete_apartment'])) {
        $apartment_id = (int)$_POST['apartment_id'];

        if ($apartment_id) {
            $stmt = $conn->prepare("DELETE FROM apartments WHERE id = ?");
            $stmt->bind_param("i", $apartment_id);
            if ($stmt->execute()) {
                $message = 'Apartment deleted successfully!';
                $message_type = 'success';
            } else {
                $message = 'Error deleting apartment.';
                $message_type = 'error';
            }
            $stmt->close();
        }
    }
}

$work_types = $conn->query("SELECT *
    FROM project_work_types
    WHERE is_active = 1
    ORDER BY work_type_name");

// Get all buildings
$buildings = $conn->query("SELECT * FROM buildings ORDER BY building_name");
?>
<?php
$pageTitle = 'Project Settings - Green World Towers';
$pageCss = 'project_settings.css';
$activePage = 'project-settings';
require_once 'partials/header.php';
?>
    <div id="project-settings-page" class="dashboard-container">
<?php
require_once 'partials/sidebar.php';
?>

        <!-- Main Content -->
        <main class="main-content">
            <header class="page-header">
                <h1><i class="fas fa-cog"></i> Project Settings</h1>
                <div class="user-info">
                    <span>Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?></span>
                </div>
            </header>

            <div class="content-wrapper">
            <!-- Success/Error Messages -->
            <?php if ($message): ?>
                <div class="alert alert-<?php echo $message_type; ?>">
                    <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <!-- Settings Container -->
            <div class="settings-layout">
                <!-- Left Panel: Add Building -->
                <div class="settings-panel">
                    <div class="panel-card">
                        <div class="card-header">
                            <h2><i class="fas fa-plus-circle"></i> Add New Building</h2>
                        </div>
                        <form method="POST" class="settings-form ajax-add-building">
                            <div class="form-group">
                                <label for="building_name">Building Name</label>
                                <input type="text" name="building_name" id="building_name" required placeholder="e.g., Tower C, Building B">
                            </div>
                            <div class="form-group">
                                <label for="total_area">Total Area (sqm)</label>
                                <input type="number" name="total_area" id="total_area" min="0" step="0.01" required placeholder="e.g., 2500.50">
                            </div>
                            <div class="form-group">
                                <label for="comments">Comments (Optional)</label>
                                <textarea name="comments" id="comments" rows="3" placeholder="Additional notes about the building..."></textarea>
                            </div>
                            <button type="submit" name="add_building" class="btn btn-primary btn-block">
                                <i class="fas fa-plus"></i> Add Building
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Right Panel: Buildings List -->
                <div class="buildings-panel">
                    <!-- Buildings Tabs -->
                    <div class="buildings-list" id="buildings-list-main">
                        <?php
                        $buildings->data_seek(0);
                        if ($buildings->num_rows === 0):
                        ?>
                            <div class="empty-state">
                                <i class="fas fa-inbox"></i>
                                <p>No buildings yet. Create one to get started!</p>
                            </div>
                        <?php else: ?>
                            <?php while ($building = $buildings->fetch_assoc()): ?>
                                <div class="building-card" id="building-card-<?php echo $building['id']; ?>">
                                    <div class="building-header">
                                        <div class="building-info">
                                            <h3><?php echo htmlspecialchars($building['building_name']); ?></h3>
                                            <p class="building-number"><?php echo number_format($building['total_area'], 2); ?> sqm</p>
                                            <?php if (!empty($building['comments'])): ?>
                                                <p class="building-comments" style="font-size: 0.85rem; color: #94a3b8; margin: 4px 0 0 0; font-style: italic;">
                                                    <?php echo htmlspecialchars($building['comments']); ?>
                                                </p>
                                            <?php endif; ?>
                                        </div>
                                        <div class="building-actions">
                                            <button class="action-btn edit-btn" onclick="editBuilding(<?php echo $building['id']; ?>, '<?php echo htmlspecialchars($building['building_name']); ?>', <?php echo $building['total_area']; ?>, '<?php echo htmlspecialchars($building['comments'] ?? ''); ?>')" title="Edit Building">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="action-btn delete-btn" onclick="deleteBuilding(<?php echo $building['id']; ?>, '<?php echo htmlspecialchars($building['building_name']); ?>')" title="Delete Building">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                            <button class="toggle-building" data-building-id="<?php echo $building['id']; ?>" type="button" title="Toggle Details">
                                                <i class="fas fa-chevron-down"></i>
                                            </button>
                                        </div>
                                    </div>
                                    <div class="building-content" id="building-<?php echo $building['id']; ?>" style="display: none;">
                                        <!-- Floors Section -->
                                        <div class="building-section">
                                            <h4><i class="fas fa-layer-group"></i> Floors</h4>
                                            <div class="add-floor-form">
                                                <form method="POST" class="inline-form ajax-add-floor" data-building-id="<?php echo $building['id']; ?>">
                                                    <input type="hidden" name="building_id" value="<?php echo $building['id']; ?>">
                                                    <input type="number" name="floor_number" placeholder="Floor #" min="1" required>
                                                    <input type="text" name="floor_name" placeholder="Floor name" required>
                                                    <input type="number" name="floor_area" placeholder="Area (sqft)" step="0.01" min="0" required>
                                                    <button type="submit" name="add_floor" class="btn btn-sm btn-primary">
                                                        <i class="fas fa-plus"></i> Add Floor
                                                    </button>
                                                </form>
                                            </div>

                                            <div class="floors-list" id="floors-list-<?php echo $building['id']; ?>">
                                                <?php
                                                $floors_stmt = $conn->prepare("SELECT * FROM floors WHERE building_id = ? ORDER BY floor_number");
                                                $floors_stmt->bind_param("i", $building['id']);
                                                $floors_stmt->execute();
                                                $floors_result = $floors_stmt->get_result();

                                                if ($floors_result->num_rows === 0):
                                                ?>
                                                    <p class="empty-text">No floors added yet</p>
                                                <?php else: ?>
                                                    <?php while ($floor = $floors_result->fetch_assoc()): ?>
                                                        <div class="floor-item">
                                                            <div class="floor-header">
                                                                <span class="floor-name">
                                                                    <i class="fas fa-layer-group"></i>
                                                                    <?php echo htmlspecialchars($floor['floor_name']); ?>
                                                                </span>
                                                                <div class="floor-actions">
                                                                    <button class="action-btn edit-btn" onclick="editFloor(<?php echo $floor['id']; ?>, <?php echo $floor['floor_number']; ?>, '<?php echo htmlspecialchars($floor['floor_name']); ?>', <?php echo $floor['area']; ?>, <?php echo $building['id']; ?>)" title="Edit Floor">
                                                                        <i class="fas fa-edit"></i>
                                                                    </button>
                                                                    <button class="action-btn delete-btn" onclick="deleteFloor(<?php echo $floor['id']; ?>, '<?php echo htmlspecialchars($floor['floor_name']); ?>', <?php echo $building['id']; ?>)" title="Delete Floor">
                                                                        <i class="fas fa-trash"></i>
                                                                    </button>
                                                                </div>
                                                                <span class="floor-area"><?php echo $floor['area']; ?> sqft</span>
                                                            </div>

                                                            <!-- Apartments Section -->
                                                            <div class="apartments-section">
                                                                <div class="apartments-header">
                                                                    <h5>Apartments</h5>
                                                                </div>

                                                                <div class="add-apartment-form">
                                                                    <form class="inline-form ajax-add-apartment" data-floor-id="<?php echo $floor['id']; ?>">
                                                                        <input type="hidden" name="floor_id" value="<?php echo $floor['id']; ?>">
                                                                        <input type="text" name="apartment_number" placeholder="Apt # (e.g., 101, A1)" required>
                                                                        <select name="apartment_type" required>
                                                                            <option value="">Type</option>
                                                                            <option value="1BR">1BR</option>
                                                                            <option value="2BR">2BR</option>
                                                                            <option value="3BR">3BR</option>
                                                                            <option value="4BR">4BR</option>
                                                                            <option value="Penthouse">Penthouse</option>
                                                                        </select>
                                                                        <input type="number" name="apartment_area" placeholder="Area (sqm)" step="0.01" min="0" required>
                                                                        <button type="submit" class="btn btn-sm btn-success">
                                                                            <i class="fas fa-plus"></i> Add
                                                                        </button>
                                                                    </form>
                                                                </div>

                                                                <div class="apartments-grid" id="apartments-grid-<?php echo $floor['id']; ?>">
                                                                    <?php
                                                                    $apts_stmt = $conn->prepare("SELECT * FROM apartments WHERE floor_id = ? ORDER BY apartment_number");
                                                                    $apts_stmt->bind_param("i", $floor['id']);
                                                                    $apts_stmt->execute();
                                                                    $apts_result = $apts_stmt->get_result();

                                                                    if ($apts_result->num_rows === 0):
                                                                    ?>
                                                                        <p class="empty-text">No apartments yet</p>
                                                                    <?php else: ?>
                                                                        <?php while ($apt = $apts_result->fetch_assoc()): ?>
                                                                            <div class="apartment-badge">
                                                                                <div class="apt-number"><?php echo htmlspecialchars($apt['apartment_number']); ?></div>
                                                                                <div class="apt-details">
                                                                                    <small><?php echo htmlspecialchars($apt['apartment_type']); ?></small>
                                                                                    <small><?php echo $apt['area_sqm']; ?> m²</small>
                                                                                </div>
                                                                                <div class="apt-actions">
                                                                                    <button class="action-btn edit-btn" onclick="editApartment(<?php echo $apt['id']; ?>, '<?php echo htmlspecialchars($apt['apartment_number']); ?>', '<?php echo htmlspecialchars($apt['apartment_type']); ?>', <?php echo $apt['area_sqm']; ?>, <?php echo $floor['id']; ?>)" title="Edit Apartment">
                                                                                        <i class="fas fa-edit"></i>
                                                                                    </button>
                                                                                    <button class="action-btn delete-btn" onclick="deleteApartment(<?php echo $apt['id']; ?>, '<?php echo htmlspecialchars($apt['apartment_number']); ?>', <?php echo $floor['id']; ?>)" title="Delete Apartment">
                                                                                        <i class="fas fa-trash"></i>
                                                                                    </button>
                                                                                </div>
                                                                            </div>
                                                                        <?php endwhile; ?>
                                                                    <?php endif; ?>
                                                                    <?php $apts_stmt->close(); ?>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    <?php endwhile; ?>
                                                <?php endif; ?>
                                                <?php $floors_stmt->close(); ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div id="work-types-settings-section" class="settings-layout work-types-settings-layout">
                <div class="settings-panel">
                    <div class="panel-card">
                        <div class="card-header">
                            <h2><i class="fas fa-layer-group"></i> Add Work Category</h2>
                        </div>
                        <form method="POST" class="settings-form ajax-work-type-form">
                            <div class="form-group">
                                <label for="work_type_name">Category Name</label>
                                <input type="text" name="work_type_name" id="work_type_name" required placeholder="e.g., Paint, Gechkari, Electrical">
                            </div>
                            <button type="submit" name="save_work_type" class="btn btn-primary btn-block">
                                <i class="fas fa-plus"></i> Add Category
                            </button>
                        </form>
                    </div>
                </div>

                <div class="buildings-panel">
                    <?php $workTypesCount = ($work_types && isset($work_types->num_rows)) ? (int)$work_types->num_rows : 0; ?>
                    <div class="work-categories-head">
                        <h3><i class="fas fa-th-large"></i> All Categories</h3>
                        <span class="work-categories-count"><?php echo $workTypesCount; ?> total</span>
                    </div>
                    <div class="buildings-list">
                        <?php if (!$work_types || $work_types->num_rows === 0): ?>
                            <div class="empty-state">
                                <i class="fas fa-inbox"></i>
                                <p>No categories yet. Add one to get started.</p>
                            </div>
                        <?php else: ?>
                            <?php while ($wt = $work_types->fetch_assoc()): ?>
                                <div class="building-card work-type-card">
                                    <div class="building-header work-type-header">
                                        <div class="work-type-card-content">
                                            <div class="work-type-top-row">
                                                <span class="work-type-category-chip"><i class="fas fa-layer-group"></i> Category</span>
                                                <div class="building-actions">
                                                    <form method="POST" class="ajax-delete-work-type-form">
                                                        <input type="hidden" name="work_type_id" value="<?php echo (int)$wt['id']; ?>">
                                                        <button type="submit" name="delete_work_type" class="action-btn delete-btn" title="Delete Category">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </form>
                                                </div>
                                            </div>
                                            <h3 class="work-type-title"><?php echo htmlspecialchars($wt['work_type_name']); ?></h3>
                                            <p class="work-type-key"><i class="fas fa-key"></i> <?php echo htmlspecialchars($wt['work_type_key']); ?></p>
                                        </div>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Summary Stats -->
            <div class="settings-stats">
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-building"></i></div>
                    <div class="stat-content">
                        <div class="stat-label">Total Buildings</div>
                        <div class="stat-value">
                            <?php
                            $count = $conn->query("SELECT COUNT(*) as count FROM buildings");
                            $row = $count->fetch_assoc();
                            echo $row['count'];
                            ?>
                        </div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-layer-group"></i></div>
                    <div class="stat-content">
                        <div class="stat-label">Total Floors</div>
                        <div class="stat-value">
                            <?php
                            $count = $conn->query("SELECT COUNT(*) as count FROM floors");
                            $row = $count->fetch_assoc();
                            echo $row['count'];
                            ?>
                        </div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-door-open"></i></div>
                    <div class="stat-content">
                        <div class="stat-label">Total Apartments</div>
                        <div class="stat-value">
                            <?php
                            $count = $conn->query("SELECT COUNT(*) as count FROM apartments");
                            $row = $count->fetch_assoc();
                            echo $row['count'];
                            ?>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        function parseDoc(html) {
            const parser = new DOMParser();
            return parser.parseFromString(html, 'text/html');
        }

        function replaceById(id, doc) {
            const current = document.getElementById(id);
            const next = doc.getElementById(id);
            if (current && next) {
                current.outerHTML = next.outerHTML;
            }
        }

        function editBuilding(id, name, area, comments) {
            document.getElementById('edit_building_id').value = id;
            document.getElementById('edit_building_name').value = name;
            document.getElementById('edit_total_area').value = area;
            document.getElementById('edit_comments').value = comments || '';
            document.getElementById('edit-building-modal').style.display = 'flex';
        }

        function closeEditBuildingModal() {
            document.getElementById('edit-building-modal').style.display = 'none';
            document.getElementById('edit-building-form').reset();
        }

        function deleteBuilding(id, name) {
            if (!confirm('Are you sure you want to delete building "' + name + '"? This will also delete all floors and apartments in this building.')) {
                return;
            }

            const formData = new FormData();
            formData.append('building_id', id);
            formData.append('delete_building', '1');

            fetch(window.location.href, { method: 'POST', body: formData })
                .then(res => res.text())
                .then(html => {
                    const doc = parseDoc(html);
                    replaceById('buildings-list-main', doc);
                });
        }

        function editFloor(id, number, name, area, buildingId) {
            document.getElementById('edit_floor_id').value = id;
            document.getElementById('edit_floor_building_id').value = buildingId;
            document.getElementById('edit_floor_number').value = number;
            document.getElementById('edit_floor_name').value = name;
            document.getElementById('edit_floor_area').value = area;
            document.getElementById('edit-floor-modal').style.display = 'flex';
        }

        function closeEditFloorModal() {
            document.getElementById('edit-floor-modal').style.display = 'none';
            document.getElementById('edit-floor-form').reset();
        }

        function deleteFloor(id, name, buildingId) {
            if (!confirm('Are you sure you want to delete floor "' + name + '"? This will also delete all apartments on this floor.')) {
                return;
            }
            const formData = new FormData();
            formData.append('floor_id', id);
            formData.append('delete_floor', '1');

            fetch(window.location.href, { method: 'POST', body: formData })
                .then(res => res.text())
                .then(html => {
                    const doc = parseDoc(html);
                    replaceById('floors-list-' + buildingId, doc);
                });
        }

        function editApartment(id, number, type, area, floorId) {
            document.getElementById('edit_apartment_id').value = id;
            document.getElementById('edit_apartment_floor_id').value = floorId;
            document.getElementById('edit_apartment_number').value = number;
            document.getElementById('edit_apartment_type').value = type;
            document.getElementById('edit_apartment_area').value = area;
            document.getElementById('edit-apartment-modal').style.display = 'flex';
        }

        function closeEditApartmentModal() {
            document.getElementById('edit-apartment-modal').style.display = 'none';
            document.getElementById('edit-apartment-form').reset();
        }

        function deleteApartment(id, number, floorId) {
            if (!confirm('Are you sure you want to delete apartment "' + number + '"?')) {
                return;
            }
            const formData = new FormData();
            formData.append('apartment_id', id);
            formData.append('delete_apartment', '1');

            fetch(window.location.href, { method: 'POST', body: formData })
                .then(res => res.text())
                .then(html => {
                    const doc = parseDoc(html);
                    replaceById('apartments-grid-' + floorId, doc);
                });
        }

        $(document).ready(function() {
            $(document).on('click', '.toggle-building', function(e) {
                e.preventDefault();
                const buildingId = $(this).data('building-id');
                const content = $('#building-' + buildingId);
                const icon = $(this).find('i');

                content.slideToggle(300, function() {
                    if (content.is(':visible')) {
                        icon.removeClass('fa-chevron-down').addClass('fa-chevron-up');
                    } else {
                        icon.removeClass('fa-chevron-up').addClass('fa-chevron-down');
                    }
                });
            });

            setTimeout(function() {
                $('.alert').fadeOut(300);
            }, 5000);

            $(document).on('submit', '.ajax-add-building', function(e) {
                e.preventDefault();
                const form = this;
                const formData = new FormData(form);
                formData.append('add_building', '1');

                fetch(window.location.href, { method: 'POST', body: formData })
                    .then(res => res.text())
                    .then(html => {
                        const doc = parseDoc(html);
                        replaceById('buildings-list-main', doc);
                        form.reset();
                    });
            });

            $(document).on('submit', '.ajax-add-floor', function(e) {
                e.preventDefault();
                const form = this;
                const buildingId = form.getAttribute('data-building-id');
                const formData = new FormData(form);
                formData.append('add_floor', '1');

                fetch(window.location.href, { method: 'POST', body: formData })
                    .then(res => res.text())
                    .then(html => {
                        const doc = parseDoc(html);
                        replaceById('floors-list-' + buildingId, doc);
                        form.reset();
                    });
            });

            $(document).on('submit', '.ajax-add-apartment', function(e) {
                e.preventDefault();
                const form = this;
                const floorId = form.getAttribute('data-floor-id');
                const formData = new FormData(form);
                formData.append('add_apartment', '1');

                fetch(window.location.href, { method: 'POST', body: formData })
                    .then(res => res.text())
                    .then(html => {
                        const doc = parseDoc(html);
                        replaceById('apartments-grid-' + floorId, doc);
                        form.reset();
                    });
            });

            $(document).on('submit', '.ajax-work-type-form', function(e) {
                e.preventDefault();
                const formData = new FormData(this);
                formData.append('save_work_type', '1');

                fetch(window.location.href, { method: 'POST', body: formData })
                    .then(res => res.text())
                    .then(html => {
                        const doc = parseDoc(html);
                        replaceById('work-types-settings-section', doc);
                    });
            });

            $(document).on('submit', '.ajax-delete-work-type-form', function(e) {
                e.preventDefault();
                if (!window.confirm('Delete this category?')) {
                    return;
                }
                const formData = new FormData(this);
                formData.append('delete_work_type', '1');

                fetch(window.location.href, { method: 'POST', body: formData })
                    .then(res => res.text())
                    .then(html => {
                        const doc = parseDoc(html);
                        replaceById('work-types-settings-section', doc);
                    });
            });

            document.addEventListener('click', function(e) {
                if (e.target.classList.contains('modal-overlay')) {
                    e.target.style.display = 'none';
                }
            });

            document.getElementById('edit-building-form').addEventListener('submit', function(e) {
                e.preventDefault();
                const buildingId = document.getElementById('edit_building_id').value;
                const formData = new FormData(this);
                formData.append('update_building', '1');

                fetch(window.location.href, { method: 'POST', body: formData })
                    .then(res => res.text())
                    .then(html => {
                        const doc = parseDoc(html);
                        replaceById('building-card-' + buildingId, doc);
                        closeEditBuildingModal();
                    });
            });

            document.getElementById('edit-floor-form').addEventListener('submit', function(e) {
                e.preventDefault();
                const buildingId = document.getElementById('edit_floor_building_id').value;
                const formData = new FormData(this);
                formData.append('update_floor', '1');

                fetch(window.location.href, { method: 'POST', body: formData })
                    .then(res => res.text())
                    .then(html => {
                        const doc = parseDoc(html);
                        replaceById('floors-list-' + buildingId, doc);
                        closeEditFloorModal();
                    });
            });

            document.getElementById('edit-apartment-form').addEventListener('submit', function(e) {
                e.preventDefault();
                const floorId = document.getElementById('edit_apartment_floor_id').value;
                const formData = new FormData(this);
                formData.append('update_apartment', '1');

                fetch(window.location.href, { method: 'POST', body: formData })
                    .then(res => res.text())
                    .then(html => {
                        const doc = parseDoc(html);
                        replaceById('apartments-grid-' + floorId, doc);
                        closeEditApartmentModal();
                    });
            });
        });
    </script>

    <!-- Edit Building Modal -->
    <div id="edit-building-modal" class="modal-overlay" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.5); z-index: 2000; align-items: center; justify-content: center;">
        <div class="modal-content" style="background: rgba(15, 23, 42, 0.95); backdrop-filter: blur(20px); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 12px; padding: 24px; max-width: 400px; width: 90%; box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);">
            <div class="modal-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h3 style="color: #fff; margin: 0; font-size: 1.2rem;">Edit Building</h3>
                <button onclick="closeEditBuildingModal()" style="background: none; border: none; color: #94a3b8; font-size: 1.5rem; cursor: pointer; padding: 0;"><i class="fas fa-times"></i></button>
            </div>
            <form method="POST" id="edit-building-form">
                <input type="hidden" name="building_id" id="edit_building_id">
                <div style="margin-bottom: 20px;">
                    <label for="edit_building_name" style="display: block; color: #cbd5e1; font-size: 0.9rem; margin-bottom: 8px; font-weight: 500;">Building Name</label>
                    <input type="text" name="building_name" id="edit_building_name" required style="width: 100%; padding: 12px 16px; background: rgba(255, 255, 255, 0.05); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 8px; color: #fff; font-size: 0.95rem;">
                </div>
                <div style="margin-bottom: 20px;">
                    <label for="edit_total_area" style="display: block; color: #cbd5e1; font-size: 0.9rem; margin-bottom: 8px; font-weight: 500;">Total Area (sqm)</label>
                    <input type="number" name="total_area" id="edit_total_area" min="0" step="0.01" required style="width: 100%; padding: 12px 16px; background: rgba(255, 255, 255, 0.05); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 8px; color: #fff; font-size: 0.95rem;">
                </div>
                <div style="margin-bottom: 24px;">
                    <label for="edit_comments" style="display: block; color: #cbd5e1; font-size: 0.9rem; margin-bottom: 8px; font-weight: 500;">Comments (Optional)</label>
                    <textarea name="comments" id="edit_comments" rows="3" style="width: 100%; padding: 12px 16px; background: rgba(255, 255, 255, 0.05); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 8px; color: #fff; font-size: 0.95rem; resize: vertical;"></textarea>
                </div>
                <div style="display: flex; gap: 12px; justify-content: flex-end;">
                    <button type="button" onclick="closeEditBuildingModal()" style="padding: 10px 20px; background: rgba(255, 255, 255, 0.1); color: #cbd5e1; border: 1px solid rgba(255, 255, 255, 0.2); border-radius: 8px; cursor: pointer;">Cancel</button>
                    <button type="submit" name="update_building" style="padding: 10px 20px; background: linear-gradient(135deg, #10b981, #059669); color: #fff; border: none; border-radius: 8px; cursor: pointer; font-weight: 600;">Update Building</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Floor Modal -->
    <div id="edit-floor-modal" class="modal-overlay" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.5); z-index: 2000; align-items: center; justify-content: center;">
        <div class="modal-content" style="background: rgba(15, 23, 42, 0.95); backdrop-filter: blur(20px); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 12px; padding: 24px; max-width: 400px; width: 90%; box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);">
            <div class="modal-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h3 style="color: #fff; margin: 0; font-size: 1.2rem;">Edit Floor</h3>
                <button onclick="closeEditFloorModal()" style="background: none; border: none; color: #94a3b8; font-size: 1.5rem; cursor: pointer; padding: 0;"><i class="fas fa-times"></i></button>
            </div>
            <form method="POST" id="edit-floor-form">
                <input type="hidden" name="floor_id" id="edit_floor_id">
                <input type="hidden" id="edit_floor_building_id">
                <div style="margin-bottom: 20px;">
                    <label for="edit_floor_number" style="display: block; color: #cbd5e1; font-size: 0.9rem; margin-bottom: 8px; font-weight: 500;">Floor Number</label>
                    <input type="number" name="floor_number" id="edit_floor_number" min="1" required style="width: 100%; padding: 12px 16px; background: rgba(255, 255, 255, 0.05); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 8px; color: #fff; font-size: 0.95rem;">
                </div>
                <div style="margin-bottom: 20px;">
                    <label for="edit_floor_name" style="display: block; color: #cbd5e1; font-size: 0.9rem; margin-bottom: 8px; font-weight: 500;">Floor Name</label>
                    <input type="text" name="floor_name" id="edit_floor_name" required style="width: 100%; padding: 12px 16px; background: rgba(255, 255, 255, 0.05); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 8px; color: #fff; font-size: 0.95rem;">
                </div>
                <div style="margin-bottom: 24px;">
                    <label for="edit_floor_area" style="display: block; color: #cbd5e1; font-size: 0.9rem; margin-bottom: 8px; font-weight: 500;">Area (sqft)</label>
                    <input type="number" name="floor_area" id="edit_floor_area" step="0.01" min="0" required style="width: 100%; padding: 12px 16px; background: rgba(255, 255, 255, 0.05); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 8px; color: #fff; font-size: 0.95rem;">
                </div>
                <div style="display: flex; gap: 12px; justify-content: flex-end;">
                    <button type="button" onclick="closeEditFloorModal()" style="padding: 10px 20px; background: rgba(255, 255, 255, 0.1); color: #cbd5e1; border: 1px solid rgba(255, 255, 255, 0.2); border-radius: 8px; cursor: pointer;">Cancel</button>
                    <button type="submit" name="update_floor" style="padding: 10px 20px; background: linear-gradient(135deg, #10b981, #059669); color: #fff; border: none; border-radius: 8px; cursor: pointer; font-weight: 600;">Update Floor</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Apartment Modal -->
    <div id="edit-apartment-modal" class="modal-overlay" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.5); z-index: 2000; align-items: center; justify-content: center;">
        <div class="modal-content" style="background: rgba(15, 23, 42, 0.95); backdrop-filter: blur(20px); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 12px; padding: 24px; max-width: 400px; width: 90%; box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);">
            <div class="modal-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h3 style="color: #fff; margin: 0; font-size: 1.2rem;">Edit Apartment</h3>
                <button onclick="closeEditApartmentModal()" style="background: none; border: none; color: #94a3b8; font-size: 1.5rem; cursor: pointer; padding: 0;"><i class="fas fa-times"></i></button>
            </div>
            <form method="POST" id="edit-apartment-form">
                <input type="hidden" name="apartment_id" id="edit_apartment_id">
                <input type="hidden" id="edit_apartment_floor_id">
                <div style="margin-bottom: 20px;">
                    <label for="edit_apartment_number" style="display: block; color: #cbd5e1; font-size: 0.9rem; margin-bottom: 8px; font-weight: 500;">Apartment Number</label>
                    <input type="text" name="apartment_number" id="edit_apartment_number" required style="width: 100%; padding: 12px 16px; background: rgba(255, 255, 255, 0.05); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 8px; color: #fff; font-size: 0.95rem;">
                </div>
                <div style="margin-bottom: 20px;">
                    <label for="edit_apartment_type" style="display: block; color: #cbd5e1; font-size: 0.9rem; margin-bottom: 8px; font-weight: 500;">Apartment Type</label>
                    <select name="apartment_type" id="edit_apartment_type" required style="width: 100%; padding: 12px 16px; background: rgba(255, 255, 255, 0.05); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 8px; color: #fff; font-size: 0.95rem;">
                        <option value="1BR">1BR</option>
                        <option value="2BR">2BR</option>
                        <option value="3BR">3BR</option>
                        <option value="4BR">4BR</option>
                        <option value="Penthouse">Penthouse</option>
                    </select>
                </div>
                <div style="margin-bottom: 24px;">
                    <label for="edit_apartment_area" style="display: block; color: #cbd5e1; font-size: 0.9rem; margin-bottom: 8px; font-weight: 500;">Area (sqm)</label>
                    <input type="number" name="apartment_area" id="edit_apartment_area" step="0.01" min="0" required style="width: 100%; padding: 12px 16px; background: rgba(255, 255, 255, 0.05); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 8px; color: #fff; font-size: 0.95rem;">
                </div>
                <div style="display: flex; gap: 12px; justify-content: flex-end;">
                    <button type="button" onclick="closeEditApartmentModal()" style="padding: 10px 20px; background: rgba(255, 255, 255, 0.1); color: #cbd5e1; border: 1px solid rgba(255, 255, 255, 0.2); border-radius: 8px; cursor: pointer;">Cancel</button>
                    <button type="submit" name="update_apartment" style="padding: 10px 20px; background: linear-gradient(135deg, #10b981, #059669); color: #fff; border: none; border-radius: 8px; cursor: pointer; font-weight: 600;">Update Apartment</button>
                </div>
            </form>
        </div>
    </div>

            </div>
        </main>
    </div>
</body>
</html>