-- Create database for construction management
CREATE DATABASE IF NOT EXISTS construction_management CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE construction_management;

-- Roles table
CREATE TABLE IF NOT EXISTS roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) UNIQUE NOT NULL,
    description VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Permissions table. Action-based: each row is one specific action within a
-- module (module + action_label are for grouping/display in the Roles &
-- Permissions UI), so a role can be granted e.g. "Inventory: Approve"
-- without also getting "Inventory: Issue Stock".
CREATE TABLE IF NOT EXISTS permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) UNIQUE NOT NULL,
    module VARCHAR(50),
    action_label VARCHAR(50),
    description VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Role permission pivot
CREATE TABLE IF NOT EXISTS role_permissions (
    role_id INT NOT NULL,
    permission_id INT NOT NULL,
    PRIMARY KEY (role_id, permission_id),
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
    FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
);

-- Users table
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    full_name VARCHAR(100),
    role_id INT NOT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (role_id) REFERENCES roles(id)
);

-- Project settings table
CREATE TABLE IF NOT EXISTS project_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Default data
INSERT IGNORE INTO roles (id, name, description) VALUES
(1, 'admin', 'Full administration'),
(2, 'manager', 'Project manager'),
(3, 'engineer', 'Site engineer'),
(4, 'Store Staff', 'Receives purchased stock and marks approved requests as delivered');

-- NOTE: these permission names must exactly match the in_array() checks
-- throughout src/ (see src/partials/sidebar.php and every page/AJAX endpoint
-- that gates on them). Most module-level permissions below are coarse (one
-- flag = full access to that whole page); the ten inventory.* rows are the
-- action-based model — grant only the specific actions a role needs instead
-- of all-or-nothing access to Storage. New tabs/modules just need a new row
-- here (see ensure_core_permissions() in includes/permissions.php for the
-- self-healing equivalent on an existing install) — the Roles & Permissions
-- UI groups checkboxes by the `module` column automatically.
INSERT IGNORE INTO permissions (id, name, module, action_label, description) VALUES
(1, 'data_entry', 'Data Entry', 'Access', 'Can enter measurement data'),
(2, 'user_management', 'User Management', 'Access', 'Can manage users and roles'),
(3, 'project_settings', 'Project Settings', 'Access', 'Can configure project settings'),
(4, 'slfa', 'Slfa', 'Access', 'Can access the SLFA payments module'),
(5, 'inventory.view', 'Inventory', 'View', 'View storage/inventory pages and data'),
(6, 'inventory.create', 'Inventory', 'Create', 'Add catalogue items/item types and submit purchase requests'),
(7, 'inventory.edit', 'Inventory', 'Edit', 'Edit catalogue items/item types'),
(8, 'inventory.delete', 'Inventory', 'Delete', 'Remove catalogue items/item types'),
(9, 'inventory.approve', 'Inventory', 'Approve', 'Approve a pending purchase request'),
(10, 'inventory.reject', 'Inventory', 'Reject', 'Reject a pending purchase request'),
(11, 'inventory.receive_stock', 'Inventory', 'Receive Stock', 'Record a purchase / stock in'),
(12, 'inventory.issue_stock', 'Inventory', 'Issue Stock', 'Issue stock out to a location'),
(13, 'inventory.mark_delivered', 'Inventory', 'Mark as Delivered', 'Mark an approved request as delivered to storage'),
(14, 'inventory.export', 'Inventory', 'Export', 'Export inventory data (CSV)'),
(15, 'stakeholders', 'Stakeholders', 'Access', 'Can manage project stakeholders'),
(16, 'analytics', 'Analytics', 'Access', 'Can view the analytics/analysis page');

INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES
(1,1),(1,2),(1,3),(1,4),(1,5),(1,6),(1,7),(1,8),(1,9),(1,10),(1,11),(1,12),(1,13),(1,14),(1,15),(1,16),
(2,1),(2,2),(2,3),(2,4),(2,5),(2,6),(2,7),(2,8),(2,9),(2,10),(2,11),(2,12),(2,13),(2,14),(2,15),(2,16),
(3,1),(3,4),
(4,5),(4,11),(4,12),(4,13);

INSERT IGNORE INTO users (id, username, password, email, full_name, role_id) VALUES
(1, 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin@example.com', 'Super Admin', 1),
(2, 'manager', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'manager@example.com', 'Project Manager', 2),
(3, 'engineer', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'engineer@example.com', 'Field Engineer', 3);

INSERT IGNORE INTO project_settings (setting_key, setting_value) VALUES
('project_name', 'Green World Towers'),
('project_description', 'Construction project managed by Dahenkar Company'),
('num_buildings', '3');

-- Buildings table
CREATE TABLE IF NOT EXISTS buildings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    building_name VARCHAR(100) NOT NULL,
    total_area DECIMAL(10,2) DEFAULT 0,
    status ENUM('active','inactive') DEFAULT 'active',
    comments TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Floors table
CREATE TABLE IF NOT EXISTS floors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    building_id INT NOT NULL,
    floor_number INT NOT NULL,
    floor_name VARCHAR(100),
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    area DECIMAL(10,2),
    FOREIGN KEY (building_id) REFERENCES buildings(id) ON DELETE CASCADE
);

-- Apartments/Units table
CREATE TABLE IF NOT EXISTS apartments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    building_id INT NOT NULL,
    floor_id INT NOT NULL,
    apartment_number VARCHAR(50) NOT NULL,
    apartment_type VARCHAR(100),
    area_sqm DECIMAL(10,2),
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (building_id) REFERENCES buildings(id) ON DELETE CASCADE,
    FOREIGN KEY (floor_id) REFERENCES floors(id) ON DELETE CASCADE
);

-- Work types table
CREATE TABLE IF NOT EXISTS work_types (
    id INT AUTO_INCREMENT PRIMARY KEY,
    work_type_code VARCHAR(50) UNIQUE NOT NULL,
    work_type_name VARCHAR(200) NOT NULL,
    description TEXT,
    unit VARCHAR(50) DEFAULT 'm²',
    category VARCHAR(100),
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Measurements table (main data entry)
CREATE TABLE IF NOT EXISTS measurements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    building_id INT NOT NULL,
    floor_id INT NOT NULL,
    apartment_id INT,
    work_type_id INT NOT NULL,
    quantity DECIMAL(15,3) NOT NULL,
    unit_price DECIMAL(10,2),
    total_price DECIMAL(12,2),
    measurement_date DATE NOT NULL,
    measured_by INT NOT NULL,
    notes TEXT,
    status ENUM('draft', 'approved', 'rejected') DEFAULT 'draft',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (building_id) REFERENCES buildings(id),
    FOREIGN KEY (floor_id) REFERENCES floors(id),
    FOREIGN KEY (apartment_id) REFERENCES apartments(id),
    FOREIGN KEY (work_type_id) REFERENCES work_types(id),
    FOREIGN KEY (measured_by) REFERENCES users(id)
);

-- Default work types (including Gechkari)
INSERT IGNORE INTO work_types (work_type_code, work_type_name, description, unit, category) VALUES
('GECHKARI', 'Gechkari Work', 'Gechkari construction measurements', 'm²', 'Foundation'),
('CONCRETE', 'Concrete Work', 'Concrete pouring and finishing', 'm³', 'Structure'),
('BRICKWORK', 'Brick Work', 'Brick laying and masonry', 'm²', 'Structure'),
('PLASTERING', 'Plastering', 'Internal and external plastering', 'm²', 'Finishing'),
('ELECTRICAL', 'Electrical Work', 'Electrical installations', 'point', 'Services'),
('PLUMBING', 'Plumbing Work', 'Plumbing installations', 'point', 'Services'),
('PAINTING', 'Painting Work', 'Interior and exterior painting', 'm²', 'Finishing'),
('TILING', 'Tiling Work', 'Floor and wall tiling', 'm²', 'Finishing');

-- Default buildings (based on project settings)
INSERT IGNORE INTO buildings (building_name, total_area) VALUES
('Building A', 15000.00),
('Building B', 15000.00),
('Building C', 12000.00);

-- The tables below (project_work_types onward) were previously only ever
-- created ad hoc via scattered `CREATE TABLE IF NOT EXISTS` calls inside
-- project_settings.php, stakeholders.php, data_entry.php, analytics.php,
-- get_dynamic_report.php and slfa.php. Defining them here means a fresh
-- install/import gets a complete, self-consistent schema instead of
-- crashing (fatal "call to a member function on bool" from a failed
-- prepare()) the first time a page is opened out of the "right" order
-- (e.g. a stakeholder's portal link opened before any admin has ever
-- loaded stakeholders.php).

-- Custom work-type categories configured in Project Settings
CREATE TABLE IF NOT EXISTS project_work_types (
    id INT AUTO_INCREMENT PRIMARY KEY,
    work_type_name VARCHAR(120) NOT NULL,
    work_type_name_ku VARCHAR(120) NULL,
    work_type_key VARCHAR(80) NOT NULL,
    quantity_unit VARCHAR(30) NOT NULL DEFAULT 'm²',
    scope_level VARCHAR(30) NOT NULL DEFAULT 'apartment',
    pricing_mode VARCHAR(30) NOT NULL DEFAULT 'per_unit',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY work_type_key (work_type_key),
    INDEX idx_work_type_key (work_type_key)
);

-- Per-work-type custom field definitions configured in Project Settings
CREATE TABLE IF NOT EXISTS project_work_type_fields (
    id INT AUTO_INCREMENT PRIMARY KEY,
    work_type_id INT NOT NULL,
    field_key VARCHAR(80) NOT NULL,
    field_label VARCHAR(120) NOT NULL,
    input_type VARCHAR(30) NOT NULL DEFAULT 'number',
    unit_label VARCHAR(30) NULL,
    field_role VARCHAR(20) NOT NULL DEFAULT 'meta',
    is_required TINYINT(1) NOT NULL DEFAULT 0,
    sort_order INT NOT NULL DEFAULT 0,
    options_json TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_work_field (work_type_id, field_key),
    INDEX idx_work_type_id (work_type_id)
);

-- Stakeholders (subcontractors/partners) managed in the Stakeholders page
-- and exposed to themselves via the token-gated stakeholder portal
CREATE TABLE IF NOT EXISTS project_stakeholders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    stakeholder_name VARCHAR(160) NOT NULL,
    stakeholder_date DATE NULL,
    work_type_key VARCHAR(80) NOT NULL,
    cash_percentage DECIMAL(5,2) NOT NULL DEFAULT 100.00,
    apartment_percentage DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    apartment_meter_price DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    contract_file VARCHAR(255) NULL,
    access_token VARCHAR(64) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_stakeholder_work (stakeholder_name, work_type_key),
    UNIQUE KEY access_token (access_token),
    INDEX idx_work_type (work_type_key),
    INDEX idx_access_token (access_token)
);

-- Priced sub-scopes of work under each stakeholder
CREATE TABLE IF NOT EXISTS project_stakeholder_subparts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    stakeholder_id INT NOT NULL,
    subpart_name VARCHAR(160) NOT NULL,
    unit_price DECIMAL(12,2) NOT NULL,
    metric_type VARCHAR(30) NOT NULL DEFAULT 'm²',
    currency_type VARCHAR(20) NOT NULL DEFAULT 'USD',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_stakeholder_subpart (stakeholder_id, subpart_name),
    INDEX idx_stakeholder (stakeholder_id),
    FOREIGN KEY (stakeholder_id) REFERENCES project_stakeholders(id) ON DELETE CASCADE
);

-- Day-to-day priced work entries recorded from Data Entry / Gechkari, the
-- basis for Analytics reports and SLFA settlement
CREATE TABLE IF NOT EXISTS project_work_entries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    work_date DATE NOT NULL,
    engineer_name VARCHAR(180) NOT NULL,
    work_type_key VARCHAR(80) NOT NULL,
    stakeholder_id INT NULL,
    subpart_id INT NULL,
    quantity DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    unit_price DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    total_price DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    metric_type VARCHAR(30) NOT NULL DEFAULT 'unit',
    currency_type VARCHAR(20) NOT NULL DEFAULT 'USD',
    building_id INT NOT NULL,
    floor_id INT NOT NULL,
    apartment_id INT NOT NULL,
    notes TEXT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'draft',
    slfa_payment_id INT NULL,
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    status_changed_by INT NULL,
    status_changed_at DATETIME NULL,
    previous_status VARCHAR(30) NULL,
    INDEX idx_work_type_key (work_type_key),
    INDEX idx_apartment (apartment_id),
    INDEX idx_work_date (work_date),
    INDEX idx_slfa_payment (slfa_payment_id)
);

-- Settlement batches recorded from the SLFA payments page
CREATE TABLE IF NOT EXISTS slfa_payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    stakeholder_id INT NOT NULL,
    payment_date DATE NOT NULL,
    total_work_value DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    cash_percentage DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    cash_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    apartment_percentage DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    apartment_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    apartment_sqm DECIMAL(14,4) NOT NULL DEFAULT 0.0000,
    apartment_meter_price DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    entry_count INT NOT NULL DEFAULT 0,
    notes TEXT NULL,
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_stakeholder (stakeholder_id),
    INDEX idx_payment_date (payment_date),
    FOREIGN KEY (stakeholder_id) REFERENCES project_stakeholders(id)
);

-- ── Storage / Inventory module ────────────────────────────────────────────
-- Optional grouping for items (e.g. Cement, Electrical, Tools).
CREATE TABLE IF NOT EXISTS inventory_item_types (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_item_type_name (name)
);

-- Master catalogue of materials/equipment. Each item is defined ONCE with
-- its name, its item type, its measure unit, and an optional item code;
-- Stock In/Out and requests just select the item from a dropdown.
CREATE TABLE IF NOT EXISTS inventory_items (
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
);

-- Purchase requests (pre-purchase workflow). item_id links to an existing
-- catalogue item when picked; NULL when requesting a not-yet-catalogued item
-- (item_name is always kept so the request still displays correctly).
-- Workflow: pending -> approved|rejected, then an approved request is
-- separately marked 'fulfilled' (displayed as "Delivered") once the goods
-- actually arrive at storage.
CREATE TABLE IF NOT EXISTS inventory_purchase_requests (
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
);

-- Purchase records (each purchase also stocks the item in). invoice_file is
-- required by the application for every new purchase (PDF only); nullable at
-- the schema level only so historical rows predating the requirement are valid.
CREATE TABLE IF NOT EXISTS inventory_purchases (
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
);

-- Stock movement ledger — drives balance and history. Location columns are
-- intentionally not foreign keys so history survives if a building/floor/
-- apartment is later removed; is_project_wide flags whole-project usage.
CREATE TABLE IF NOT EXISTS inventory_movements (
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
);
