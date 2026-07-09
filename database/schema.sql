-- =====================================================================
-- UIRI INVENTORY MANAGEMENT SYSTEM
-- Hierarchical RBAC:  Branch -> Department -> Section -> Items
-- Roles: admin | dept_manager | section_chief
-- =====================================================================

CREATE DATABASE IF NOT EXISTS uiri_ims CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE uiri_ims;

-- ---------------------------------------------------------------------
-- BRANCHES  (e.g. Nakawa HQ, Namanve)
-- ---------------------------------------------------------------------
CREATE TABLE branches (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    location VARCHAR(255) DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- USERS
-- branch_id / department_id / section_id give each user their scope.
-- admin      -> all NULL (unrestricted)
-- dept_manager -> branch_id + department_id set
-- section_chief -> branch_id + department_id + section_id set
-- ---------------------------------------------------------------------
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(150) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin','dept_manager','section_chief') NOT NULL,
    branch_id INT DEFAULT NULL,
    department_id INT DEFAULT NULL,
    section_id INT DEFAULT NULL,
    status ENUM('active','disabled') NOT NULL DEFAULT 'active',
    profile_picture VARCHAR(255) DEFAULT NULL,
    can_physical_audit TINYINT(1) NOT NULL DEFAULT 0,
    can_delegate_view TINYINT(1) NOT NULL DEFAULT 0,
    can_procurement_liaison TINYINT(1) NOT NULL DEFAULT 0,
    can_view_reports_own_section TINYINT(1) NOT NULL DEFAULT 0,
    can_request_writeoff TINYINT(1) NOT NULL DEFAULT 0,
    failed_login_attempts INT NOT NULL DEFAULT 0,
    last_failed_login DATETIME DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_users_branch FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- DEPARTMENTS (belong to a branch, have one manager)
-- ---------------------------------------------------------------------
CREATE TABLE departments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    branch_id INT NOT NULL,
    name VARCHAR(150) NOT NULL,
    manager_id INT DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_dept_branch FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE CASCADE,
    CONSTRAINT fk_dept_manager FOREIGN KEY (manager_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- SECTIONS (belong to a department, have one section chief)
-- ---------------------------------------------------------------------
CREATE TABLE sections (
    id INT AUTO_INCREMENT PRIMARY KEY,
    department_id INT NOT NULL,
    name VARCHAR(150) NOT NULL,
    chief_id INT DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_section_dept FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE CASCADE,
    CONSTRAINT fk_section_chief FOREIGN KEY (chief_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- SECTION REQUESTS
-- Procurement / disposal requests submitted by section chiefs.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS section_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    section_id INT NOT NULL,
    user_id INT NOT NULL,
    request_type ENUM('procurement','writeoff') NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_section_request_section FOREIGN KEY (section_id) REFERENCES sections(id) ON DELETE CASCADE,
    CONSTRAINT fk_section_request_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Now that departments/sections exist, add the reverse FKs on users
ALTER TABLE users
    ADD CONSTRAINT fk_users_department FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL,
    ADD CONSTRAINT fk_users_section FOREIGN KEY (section_id) REFERENCES sections(id) ON DELETE SET NULL;

ALTER TABLE users
    ADD COLUMN IF NOT EXISTS profile_picture VARCHAR(255) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS can_physical_audit TINYINT(1) NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS can_delegate_view TINYINT(1) NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS can_procurement_liaison TINYINT(1) NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS can_view_reports_own_section TINYINT(1) NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS can_request_writeoff TINYINT(1) NOT NULL DEFAULT 0;

-- ---------------------------------------------------------------------
-- CATEGORIES
-- ---------------------------------------------------------------------
CREATE TABLE categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- SUPPLIERS
-- ---------------------------------------------------------------------
CREATE TABLE suppliers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    contact_person VARCHAR(150) DEFAULT NULL,
    phone VARCHAR(50) DEFAULT NULL,
    email VARCHAR(150) DEFAULT NULL,
    address VARCHAR(255) DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- ITEMS  -- item always belongs to exactly one section (and therefore
-- transitively to one department and one branch). This is the key to
-- clean data isolation.
-- ---------------------------------------------------------------------
CREATE TABLE items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    section_id INT NOT NULL,
    category_id INT DEFAULT NULL,
    supplier_id INT DEFAULT NULL,
    item_code VARCHAR(50) NOT NULL UNIQUE,
    name VARCHAR(150) NOT NULL,
    description TEXT DEFAULT NULL,
    quantity INT NOT NULL DEFAULT 0,
    unit VARCHAR(30) DEFAULT 'pcs',
    low_stock_threshold INT NOT NULL DEFAULT 5,
    image VARCHAR(255) DEFAULT NULL,
    serial_number VARCHAR(100) DEFAULT NULL,
    expiry_date DATE DEFAULT NULL,
    purchase_date DATE DEFAULT NULL,
    unit_cost DECIMAL(12,2) DEFAULT NULL,
    added_by INT DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_items_section FOREIGN KEY (section_id) REFERENCES sections(id) ON DELETE CASCADE,
    CONSTRAINT fk_items_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
    CONSTRAINT fk_items_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE SET NULL,
    CONSTRAINT fk_items_added_by FOREIGN KEY (added_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- STOCK TRANSACTIONS (movement history: stock-in / stock-out)
-- ---------------------------------------------------------------------
CREATE TABLE stock_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_id INT NOT NULL,
    type ENUM('in','out') NOT NULL,
    quantity INT NOT NULL,
    note VARCHAR(255) DEFAULT NULL,
    performed_by INT DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_txn_item FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE,
    CONSTRAINT fk_txn_user FOREIGN KEY (performed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- AUDIT LOGS
-- ---------------------------------------------------------------------
CREATE TABLE audit_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT DEFAULT NULL,
    action VARCHAR(100) NOT NULL,
    target_type VARCHAR(50) DEFAULT NULL,
    target_id INT DEFAULT NULL,
    details VARCHAR(500) DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_audit_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- =====================================================================
-- SEED DATA
-- =====================================================================

INSERT INTO categories (name) VALUES
('ICT Equipment'), ('Laboratory Equipment'), ('Office Supplies'),
('Furniture'), ('Machinery'), ('Raw Materials'), ('Safety Equipment');

INSERT INTO branches (name, location) VALUES
('Nakawa HQ', 'Nakawa Industrial Area, Kampala'),
('Namanve', 'Namanve Industrial Park, Mukono');

-- Default administrator account
-- Email: admin@uiri.go.ug   Password: Admin@2026
-- (hash generated with PHP password_hash, bcrypt)
INSERT INTO users (full_name, email, password, role, branch_id, department_id, section_id, status)
VALUES ('System Administrator', 'admin@uiri.go.ug',
'$2b$10$qvVxjbulFPYUq.rXN5Kk4u7bHPiaE6AJKluJPw4X8avOAsxIONs52',
'admin', NULL, NULL, NULL, 'active');

-- For existing installations: add failed-login tracking columns if missing
ALTER TABLE users
    ADD COLUMN IF NOT EXISTS failed_login_attempts INT NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS last_failed_login DATETIME DEFAULT NULL;

-- For existing installations: add new optional columns if they are missing
ALTER TABLE items
    ADD COLUMN IF NOT EXISTS serial_number VARCHAR(100) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS expiry_date DATE DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS purchase_date DATE DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS unit_cost DECIMAL(12,2) DEFAULT NULL;
