# UIRI Inventory Management System

[![PHP Version](https://img.shields.io/badge/php-%E2%89%A5%208.0-777bb4.svg)](https://www.php.net/)
[![MySQL](https://img.shields.io/badge/mysql-%2300f.svg?style=flat&logo=mysql&logoColor=white)](https://www.mysql.com/)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)

A hierarchical, secure, role-based Inventory Management System custom-built for the **Uganda Industrial Research Institute (UIRI)**. Developed in pure **PHP and MySQL** without external heavy frameworks, ensuring optimal performance and direct execution on standard stack environments like XAMPP.

---

## 🏗️ System Architecture & Data Scope

The system maps directly to UIRI's organizational structure, ensuring strict data isolation at every tier. Data isolation is enforced strictly **server-side** on every query and form submission via security hooks (`includes/functions.php` $\rightarrow$ `scope_items_clause()`, `user_can_access_section()`). 

A Section Chief cannot view or modify another section's data, even by tampering with form payloads or URL parameters.

#UIRI Root
└─ Branch (e.g., Nakawa HQ, Namanve)
└─ Department (Managed by: Department Manager)
└─ Section (Managed by: Section Chief)
└─ Inventory Items (Metadata, Stock History, Uploads)

## 🔐 Roles & Permissions Matrix

| Action | Admin | Dept. Manager (Own Dept.) | Section Chief (Own Section) |
| :--- | :---: | :---: | :---: |
| **Manage Branches** *(Create/Edit/Delete)* | ✅ | ❌ | ❌ |
| **Manage Departments** *(Create/Edit/Delete)* | ✅ | ❌ | ❌ |
| **Manage Sections** *(Create/Edit/Delete)* | ✅ | ❌ | ❌ |
| **Assign Managers / Section Chiefs** | ✅ | ❌ | ❌ |
| **Inventory Items** *(Create/Read/Update)* | ✅ *(Global)* | ✅ *(Any Section in Dept.)* | ✅ *(Own Section Only)* |
| **Delete Inventory Items** | ✅ | ❌ | ❌ |
| **Cross-Section Visibility** | ✅ *(Global)* | ✅ *(Within Dept.)* | ❌ |
| **Cross-Department Visibility** | ✅ | ❌ | ❌ |
| **Generate Intelligence Reports** | ✅ *(Global)* | ✅ *(Own Dept.)* | ❌ |
| **User & Access Management** | ✅ | ❌ | ❌ |

---

## 🚀 Key Features

* **Enterprise-Grade Security:** Powered by `Bcrypt` password hashing, anti-CSRF tokens on all state-changing forms, PDO prepared statements for total SQL-injection immunity, and session regeneration.
* **Server Lockdown:** Native `.htaccess` blocks external access to the `config/` directory and stops script execution within the `uploads/` path.
* **Immutable Audit Trail:** Comprehensive tracking of every action (`Create`, `Update`, `Delete`, `Login`, `Logout`) capturing user IDs, timestamps, IP addresses, and mutation details via the Admin Audit Dashboard.
* **Granular Inventory Controls:** Dedicated stock-in/stock-out movement tracking decoupled from master quantity overrides to keep historical velocity logs intact. Includes low-stock threshold triggers.
* **Smart Asset Management:** Automated structural item code generation (e.g., `UIRI-ICT-0451`), multimedia file upload handlers, and structured supplier mappings.
* **Reporting Suite:** Clean, print-optimized layouts for Inventory Summaries and Stock Manifests with dynamic branch/date filtering built straight into native browser printing engines.

---

## 📂 Repository File Structure

```text
uiri-ims/
├── admin/          # Privileged Admin layouts (System management, logs, global reports)
├── manager/        # Mid-tier Department Manager operational screens
├── chief/          # Ground-level Section Chief narrow-scope screens
├── auth/           # Session orchestration (Login/Logout workflows)
├── actions/        # POST Processing Engine (Where all RBAC checks live)
├── config/         # System environment, DB handlers, Security initializers
├── includes/       # Reusable components (Global navigation, structural frames, modals)
├── assets/         # Compiled UI elements (CSS typography, branding assets)
├── uploads/items/  # Isolated persistent asset directory for item pictures
└── database/       # Schema drops and relational structure updates
🛠️ Local Installation & Setup (XAMPP)
Prerequisites
XAMPP with PHP 8.0+ and MySQL / MariaDB enabled.

Step-by-Step Guide
Clone / Copy Project: Move the entire uiri-ims directory into your local web root:

Windows: C:\xampp\htdocs\uiri-ims

macOS: /Applications/XAMPP/htdocs/uiri-ims

Boot Stack Engines: Open your XAMPP Control Panel and start Apache and MySQL.

Database Migration: * Navigate to your local database engine management UI: http://localhost/phpmyadmin

Create a database named uiri_ims.

Click Import, select database/schema.sql from the project files, and execute. This initializes tables, structure, seed categories, default branches, and an admin user.

Configure Environment: If your local MySQL setup requires credentials, edit config/db.php with your root password parameters:

PHP
define('DB_HOST', 'localhost');
define('DB_NAME', 'uiri_ims');
define('DB_USER', 'root');
define('DB_PASS', 'YOUR_PASSWORD_HERE');
Launch application: Open your browser and access http://localhost/uiri-ims/.

Initial Development Credentials
⚠️ CRITICAL: Change these credentials immediately via the Users & Roles panel upon initial setup.

Identifier: admin@uiri.go.ug

Secret: Admin@2026

🧑‍💻 Recommended Initial Testing Workflow
To experience the system's scoping features fully, follow this configuration loop as an Admin:

Verify seeded structural anchors under Branches (Nakawa HQ / Namanve).

Spin up a new organizational branch node under Departments (e.g., ICT Department).

Go to Users & Roles and instantiate two testing accounts: one manager, one section chief.

Navigate to Sections, instantiate an operational cell (e.g., Software Dev Unit), and attach your new Section Chief.

Head back to Departments and assign your Department Manager to their respective department node.

Open a private browsing container, authenticate as each user, and check that access boundaries hold.

🧠 Core Development Policies
When expanding code features or adding operational hooks, maintain these development standards:

Write-State Isolation: Every action file executing mutating database actions in actions/*.php must explicitly loop through authorization verifications before touching data:

PHP
require_role(['Admin', 'Dept. Manager']);
if (!user_can_access_section($section_id)) {
    die("Unauthorized access attempt logged.");
}
Report Generation Extension: When designing a new report type in admin/reports.php, use the .no-print helper class to hide configuration controls during standard media printing executions.