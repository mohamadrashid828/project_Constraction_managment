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

-- Permissions table
CREATE TABLE IF NOT EXISTS permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) UNIQUE NOT NULL,
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
(3, 'engineer', 'Site engineer');

INSERT IGNORE INTO permissions (id, name, description) VALUES
(1, 'manage_users', 'Create/edit users'),
(2, 'manage_projects', 'Create/edit projects'),
(3, 'view_dashboard', 'View dashboard');

INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES
(1,1),(1,2),(1,3),
(2,2),(2,3),
(3,3);

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
INSERT IGNORE INTO buildings (building_name, total_floors) VALUES
('Building A', 15000.00),
('Building B', 15000.00),
('Building C', 12000.00);
