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