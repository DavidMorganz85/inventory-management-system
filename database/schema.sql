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
-- INVENTORY INCIDENTS (damaged / missing stock tracking)
-- Reporting removes units from available stock. Recovery restores them;
-- write-off closes the incident without changing the reduced balance.
-- ---------------------------------------------------------------------
CREATE TABLE inventory_incidents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_id INT NOT NULL,
    incident_type ENUM('damaged','missing') NOT NULL,
    quantity INT NOT NULL,
    details VARCHAR(500) NOT NULL,
    incident_date DATE NOT NULL,
    status ENUM('open','recovered','written_off') NOT NULL DEFAULT 'open',
    reported_by INT DEFAULT NULL,
    resolved_by INT DEFAULT NULL,
    resolution_note VARCHAR(500) DEFAULT NULL,
    resolved_at DATETIME DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_incident_item FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE,
    CONSTRAINT fk_incident_reporter FOREIGN KEY (reported_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_incident_resolver FOREIGN KEY (resolved_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_incident_status (status),
    INDEX idx_incident_type (incident_type),
    INDEX idx_incident_date (incident_date)
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

-- Sample suppliers. Reserved example.com addresses make it clear that these
-- are demonstration records and not production vendor contacts.
INSERT INTO suppliers (name, contact_person, phone, email, address)
SELECT 'TechSource Uganda Ltd', 'Sarah Nakato', '+256 700 000101',
       'sales@techsource.example.com', 'Nakawa, Kampala'
WHERE NOT EXISTS (SELECT 1 FROM suppliers WHERE name = 'TechSource Uganda Ltd');

INSERT INTO suppliers (name, contact_person, phone, email, address)
SELECT 'Kampala Safety Supplies Ltd', 'Daniel Okello', '+256 700 000102',
       'orders@safetysupplies.example.com', 'Industrial Area, Kampala'
WHERE NOT EXISTS (SELECT 1 FROM suppliers WHERE name = 'Kampala Safety Supplies Ltd');

INSERT INTO suppliers (name, contact_person, phone, email, address)
SELECT 'UIRI Office Solutions Ltd', 'Grace Namusoke', '+256 700 000103',
       'info@officesolutions.example.com', 'Ntinda, Kampala'
WHERE NOT EXISTS (SELECT 1 FROM suppliers WHERE name = 'UIRI Office Solutions Ltd');

INSERT INTO suppliers (name, contact_person, phone, email, address)
SELECT 'Namanve Industrial Traders Ltd', 'Peter Mugisha', '+256 700 000104',
       'supplies@namanvetraders.example.com', 'Namanve Industrial Park, Mukono'
WHERE NOT EXISTS (SELECT 1 FROM suppliers WHERE name = 'Namanve Industrial Traders Ltd');

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

-- ---------------------------------------------------------------------
-- SAMPLE TRANSACTION DATA
-- Creates the minimum related structure required by stock transactions.
-- The WHERE NOT EXISTS checks keep this block safe to run more than once.
-- Item balances equal stock received minus stock issued.
-- ---------------------------------------------------------------------
INSERT INTO departments (branch_id, name)
SELECT b.id, 'Administration and ICT'
FROM branches b
WHERE b.name = 'Nakawa HQ'
  AND NOT EXISTS (
      SELECT 1 FROM departments d
      WHERE d.branch_id = b.id AND d.name = 'Administration and ICT'
  )
LIMIT 1;

SET @sample_department_id = (
    SELECT d.id
    FROM departments d
    JOIN branches b ON d.branch_id = b.id
    WHERE b.name = 'Nakawa HQ' AND d.name = 'Administration and ICT'
    LIMIT 1
);

INSERT INTO sections (department_id, name)
SELECT @sample_department_id, 'ICT Support'
WHERE @sample_department_id IS NOT NULL
  AND NOT EXISTS (
      SELECT 1 FROM sections
      WHERE department_id = @sample_department_id AND name = 'ICT Support'
  );

INSERT INTO sections (department_id, name)
SELECT @sample_department_id, 'Central Stores'
WHERE @sample_department_id IS NOT NULL
  AND NOT EXISTS (
      SELECT 1 FROM sections
      WHERE department_id = @sample_department_id AND name = 'Central Stores'
  );

SET @ict_section_id = (
    SELECT id FROM sections
    WHERE department_id = @sample_department_id AND name = 'ICT Support'
    LIMIT 1
);
SET @stores_section_id = (
    SELECT id FROM sections
    WHERE department_id = @sample_department_id AND name = 'Central Stores'
    LIMIT 1
);
SET @sample_admin_id = (
    SELECT id FROM users WHERE email = 'admin@uiri.go.ug' LIMIT 1
);

INSERT INTO items
    (section_id, category_id, item_code, name, description, quantity, unit,
     low_stock_threshold, unit_cost, added_by)
SELECT @ict_section_id, c.id, 'SAMPLE-TONER-001', 'Printer Toner Cartridge',
       'Sample inventory item used by the transaction seed data', 24, 'cartridges', 5, 185000.00, @sample_admin_id
FROM categories c
WHERE c.name = 'Office Supplies' AND @ict_section_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM items WHERE item_code = 'SAMPLE-TONER-001')
LIMIT 1;

INSERT INTO items
    (section_id, category_id, item_code, name, description, quantity, unit,
     low_stock_threshold, unit_cost, added_by)
SELECT @stores_section_id, c.id, 'SAMPLE-GLOVE-001', 'Safety Gloves',
       'Sample inventory item used by the transaction seed data', 80, 'pairs', 20, 15000.00, @sample_admin_id
FROM categories c
WHERE c.name = 'Safety Equipment' AND @stores_section_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM items WHERE item_code = 'SAMPLE-GLOVE-001')
LIMIT 1;

INSERT INTO items
    (section_id, category_id, item_code, name, description, quantity, unit,
     low_stock_threshold, unit_cost, added_by)
SELECT @stores_section_id, c.id, 'SAMPLE-CHAIR-001', 'Office Chair',
       'Sample inventory item used by the transaction seed data', 13, 'chairs', 3, 320000.00, @sample_admin_id
FROM categories c
WHERE c.name = 'Furniture' AND @stores_section_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM items WHERE item_code = 'SAMPLE-CHAIR-001')
LIMIT 1;

-- Stock-in examples
INSERT INTO stock_transactions (item_id, type, quantity, note, performed_by, created_at)
SELECT i.id, 'in', 30, 'Delivery note DN-1001', @sample_admin_id, '2026-07-01 09:15:00'
FROM items i WHERE i.item_code = 'SAMPLE-TONER-001'
  AND NOT EXISTS (SELECT 1 FROM stock_transactions WHERE note = 'Delivery note DN-1001');

INSERT INTO stock_transactions (item_id, type, quantity, note, performed_by, created_at)
SELECT i.id, 'in', 100, 'Safety supplies delivery DN-1002', @sample_admin_id, '2026-07-03 11:30:00'
FROM items i WHERE i.item_code = 'SAMPLE-GLOVE-001'
  AND NOT EXISTS (SELECT 1 FROM stock_transactions WHERE note = 'Safety supplies delivery DN-1002');

INSERT INTO stock_transactions (item_id, type, quantity, note, performed_by, created_at)
SELECT i.id, 'in', 15, 'Furniture delivery DN-1003', @sample_admin_id, '2026-07-05 14:00:00'
FROM items i WHERE i.item_code = 'SAMPLE-CHAIR-001'
  AND NOT EXISTS (SELECT 1 FROM stock_transactions WHERE note = 'Furniture delivery DN-1003');

-- Stock-out examples
INSERT INTO stock_transactions (item_id, type, quantity, note, performed_by, created_at)
SELECT i.id, 'out', 6, 'Issued to Administration printer room', @sample_admin_id, '2026-07-08 10:20:00'
FROM items i WHERE i.item_code = 'SAMPLE-TONER-001'
  AND NOT EXISTS (SELECT 1 FROM stock_transactions WHERE note = 'Issued to Administration printer room');

INSERT INTO stock_transactions (item_id, type, quantity, note, performed_by, created_at)
SELECT i.id, 'out', 20, 'Issued to workshop team', @sample_admin_id, '2026-07-10 08:45:00'
FROM items i WHERE i.item_code = 'SAMPLE-GLOVE-001'
  AND NOT EXISTS (SELECT 1 FROM stock_transactions WHERE note = 'Issued to workshop team');

INSERT INTO stock_transactions (item_id, type, quantity, note, performed_by, created_at)
SELECT i.id, 'out', 2, 'Issued to new staff offices', @sample_admin_id, '2026-07-12 15:10:00'
FROM items i WHERE i.item_code = 'SAMPLE-CHAIR-001'
  AND NOT EXISTS (SELECT 1 FROM stock_transactions WHERE note = 'Issued to new staff offices');

-- Connect the demonstration inventory to the demonstration suppliers.
UPDATE items
SET supplier_id = (SELECT id FROM suppliers WHERE name = 'TechSource Uganda Ltd' LIMIT 1)
WHERE item_code = 'SAMPLE-TONER-001' AND supplier_id IS NULL;

UPDATE items
SET supplier_id = (SELECT id FROM suppliers WHERE name = 'Kampala Safety Supplies Ltd' LIMIT 1)
WHERE item_code = 'SAMPLE-GLOVE-001' AND supplier_id IS NULL;

UPDATE items
SET supplier_id = (SELECT id FROM suppliers WHERE name = 'UIRI Office Solutions Ltd' LIMIT 1)
WHERE item_code = 'SAMPLE-CHAIR-001' AND supplier_id IS NULL;

-- ---------------------------------------------------------------------
-- SAMPLE DEPARTMENT MANAGER ACCOUNTS
-- Login email format: firstname@uiri.go.ug
-- Initial password for every account: user@uiri.go.ug
-- Existing department-manager assignments are not replaced.
-- ---------------------------------------------------------------------
DROP TEMPORARY TABLE IF EXISTS sample_department_users;
CREATE TEMPORARY TABLE sample_department_users (
    department_name VARCHAR(150) PRIMARY KEY,
    full_name VARCHAR(150) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE
);

INSERT INTO sample_department_users (department_name, full_name, email) VALUES
('Administration and ICT', 'Peter Kato', 'peter@uiri.go.ug'),
('Bakery and Confectionery Technology Section', 'Amina Nansubuga', 'amina@uiri.go.ug'),
('Bamboo Processing Section', 'Brian Ssemanda', 'brian@uiri.go.ug'),
('Central Warehouse & General Stores', 'Carol Namuli', 'carol@uiri.go.ug'),
('Ceramics and Materials Processing Section', 'David Mugerwa', 'david@uiri.go.ug'),
('Chemistry Analytical Laboratory', 'Esther Nabirye', 'esther@uiri.go.ug'),
('Civil Works & Estate Management', 'Frank Tumusiime', 'frank@uiri.go.ug'),
('Dairy Processing Technology Section', 'Grace Akello', 'grace@uiri.go.ug'),
('Executive Directorate', 'Henry Kiyingi', 'henry@uiri.go.ug'),
('Finance and Accounts Department', 'Irene Nakato', 'irene@uiri.go.ug'),
('Fruits and Vegetables Processing Section', 'Joseph Balikuddembe', 'joseph@uiri.go.ug'),
('Human Resources & Administration', 'Kevin Ochieng', 'kevin@uiri.go.ug'),
('In-House Business Incubation Hub', 'Lydia Nambasa', 'lydia@uiri.go.ug'),
('Instrumentation Design and Electronics Prototyping Laboratory', 'Moses Wanyama', 'moses@uiri.go.ug'),
('Meat Processing Technology Section', 'Nancy Atim', 'nancy@uiri.go.ug'),
('Microbiology and Biotechnology Laboratory', 'Oscar Sserunjogi', 'oscar@uiri.go.ug'),
('Mineral Testing Laboratory', 'Patricia Asiimwe', 'patricia@uiri.go.ug'),
('Printed Circuit Board Production Unit', 'Robert Kaggwa', 'robert@uiri.go.ug'),
('Procurement and Disposal Unit', 'Susan Nakitto', 'susan@uiri.go.ug'),
('Virtual Business Incubation Hub', 'Timothy Okello', 'timothy@uiri.go.ug'),
('Wood Technology and Carpentry Unit', 'Viola Namusoke', 'viola@uiri.go.ug');

INSERT INTO users
    (full_name, email, password, role, branch_id, department_id, section_id, status)
SELECT su.full_name, su.email,
       '$2y$10$ToO35wDilE.YPjPKNphna.1udHnAjhc7vNc7pMCkwF6E27Otx4ivC',
       'dept_manager', d.branch_id, d.id, NULL, 'active'
FROM sample_department_users su
JOIN departments d ON d.name = su.department_name
WHERE d.manager_id IS NULL
  AND NOT EXISTS (SELECT 1 FROM users u WHERE u.email = su.email);

UPDATE departments d
JOIN sample_department_users su ON su.department_name = d.name
JOIN users u ON u.email = su.email
SET d.manager_id = u.id
WHERE d.manager_id IS NULL;

DROP TEMPORARY TABLE sample_department_users;
