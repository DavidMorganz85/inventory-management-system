# UIRI Inventory Management System

A hierarchical, role-based Inventory Management System built for the **Uganda Industrial
Research Institute (UIRI)** in pure **PHP + MySQL** (no framework — runs directly on XAMPP).

## Organizational Hierarchy

```
Branch  (e.g. Nakawa HQ, Namanve)
 └─ Department   (has ONE Department Manager)
     └─ Section  (has ONE Section Chief)
         └─ Items (metadata, quantity, images, stock history)
```

## Roles & Permissions

| Action                              | Admin | Dept. Manager (own dept.) | Section Chief (own section) |
|--------------------------------------|:-----:|:--------------------------:|:-----------------------------:|
| Create/edit/delete Branches           | ✅    | ❌                          | ❌ |
| Create/edit/delete Departments        | ✅    | ❌                          | ❌ |
| Create/edit/delete Sections           | ✅    | ❌                          | ❌ |
| Assign Dept. Managers / Section Chiefs| ✅    | ❌                          | ❌ |
| Create / Read / Update items          | ✅ (any) | ✅ (any section in dept.) | ✅ (own section only) |
| Delete items                          | ✅    | ❌                          | ❌ |
| View other sections in same dept.     | ✅    | ✅                          | ❌ |
| View other departments                | ✅    | ❌                          | ❌ |
| Generate reports                      | ✅ (any branch/dept/section) | ✅ (own dept. only) | ❌ |
| Manage users / reassign roles         | ✅    | ❌                          | ❌ |

Data isolation is enforced **server-side** on every query and every form submission
(`includes/functions.php` → `scope_items_clause()`, `user_can_access_section()`,
`user_can_access_item()`), not just hidden in the UI — a section chief cannot see or modify
another section's data even by tampering with a form.

## Setup on XAMPP

1. Copy the whole `uiri-ims` folder into `C:\xampp\htdocs\` (Windows) or `/Applications/XAMPP/htdocs/` (Mac).
2. Start **Apache** and **MySQL** in the XAMPP Control Panel.
3. Open **phpMyAdmin** (`http://localhost/phpmyadmin`), click **Import**, and import
   `database/schema.sql`. This creates the `uiri_ims` database, all tables, seed
   categories, the two UIRI branches (Nakawa HQ, Namanve), and a default admin account.
4. If your MySQL root user has a password, update `config/db.php` accordingly (default
   XAMPP setup uses an empty password, which is already configured).
5. Visit `http://localhost/uiri-ims/` in your browser.

### Default login

```
Email:    admin@uiri.go.ug
Password: Admin@2026
```

**Change this password immediately** after first login (Users & Roles → Reset PW), or
create your own admin account and disable this one.

## Recommended first-time workflow (as Admin)

1. **Branches** — confirm/edit Nakawa HQ and Namanve, or add more.
2. **Departments** — create departments under a branch (e.g. "ICT Department").
3. **Users & Roles** — create user accounts for your department managers and section chiefs.
4. **Sections** — create sections under a department and assign a Section Chief to each
   (or assign later by editing the section).
5. Assign a **Department Manager** to each department (Departments page).
6. Log in as each manager/chief to confirm they only see their own scope.
7. Start adding **Inventory Items** — Section Chiefs add items to their own section,
   Department Managers can add/edit items in any section of their department, and the
   Admin has full control everywhere, including deleting items and generating
   cross-branch reports.

## Key features

- **Bcrypt password hashing**, CSRF protection on every form, PDO prepared statements
  throughout (SQL-injection safe), session regeneration, `.htaccess` lockdown of the
  `config/` folder and execution-blocking on `uploads/`.
- **Full audit trail** — every create/update/delete/login/logout is logged with user,
  timestamp, IP, and details (Admin → Audit Logs).
- **Stock-in / stock-out movement tracking** with automatic low-stock alerts, separate
  from simple quantity edits, so there's a full history of who moved what and when.
- **Auto-generated item codes** (e.g. `UIRI-ICT-0451`), image uploads per item, supplier
  and category management.
- **Print-ready reports** (Inventory Summary & Stock Movement) with branch/department/
  section/date filters — use the browser's "Print / Save as PDF" button.
- Clean, responsive UI themed around UIRI's navy/sky-blue/gold branding.

## Folder structure

```
uiri-ims/
├── admin/          Admin-only pages (branches, departments, sections, users, items, reports, audit log)
├── manager/         Department Manager pages (sections view, items, reports)
├── chief/            Section Chief pages (items only, locked to their section)
├── auth/            Login / logout
├── actions/         POST handlers — this is where all RBAC checks are enforced
├── config/          DB connection + session/auth/CSRF helpers
├── includes/         Shared header/sidebar/footer + reusable item modals
├── assets/          CSS, logo
├── uploads/items/    Uploaded item photos
└── database/schema.sql
```

## Notes for further development

- To add a new report type, follow the pattern in `admin/reports.php` — it's filter →
  query (respecting scope) → render, with a `.no-print` wrapper around the filter UI.
- All write operations live in `actions/*.php`; every one of them calls `require_role()`
  and, for items, `user_can_access_section()` / `user_can_access_item()` before touching
  the database — keep that pattern for any new feature so the isolation guarantee holds.
