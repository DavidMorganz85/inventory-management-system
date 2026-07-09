<?php
/**
 * Shared helper functions: data-scoping, audit logging, flash messages.
 */

/** Record an action in the audit trail */
function log_audit(PDO $pdo, ?int $userId, string $action, ?string $targetType = null, ?int $targetId = null, ?string $details = null): void {
    $stmt = $pdo->prepare(
        "INSERT INTO audit_logs (user_id, action, target_type, target_id, details, ip_address)
         VALUES (?, ?, ?, ?, ?, ?)"
    );
    $stmt->execute([$userId, $action, $targetType, $targetId, $details, $_SERVER['REMOTE_ADDR'] ?? null]);
}

/** Flash message helpers (session based, one-time display) */
function set_flash(string $type, string $message): void {
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}
function get_flashes(): array {
    $flashes = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $flashes;
}

function e(?string $str): string {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

function user_avatar_url(?array $user): string {
    if (!empty($user['profile_picture'])) {
        return '/uiri-ims/uploads/profiles/' . rawurlencode($user['profile_picture']);
    }
    return '/uiri-ims/assets/img/uiri-logo.png';
}

/** Generate a unique item code, e.g. UIRI-ICT-0001 */
function generate_item_code(PDO $pdo, string $categoryPrefix = 'ITM'): string {
    $prefix = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $categoryPrefix), 0, 3) ?: 'ITM');
    do {
        $code = 'UIRI-' . $prefix . '-' . str_pad((string)random_int(1, 9999), 4, '0', STR_PAD_LEFT);
        $check = $pdo->prepare("SELECT COUNT(*) FROM items WHERE item_code = ?");
        $check->execute([$code]);
    } while ($check->fetchColumn() > 0);
    return $code;
}

/**
 * ---------------------------------------------------------------
 * DATA-SCOPING LAYER
 * This is what enforces the hierarchical isolation:
 *   admin          -> full visibility, no restriction
 *   dept_manager   -> only their department (all sections within it)
 *   section_chief  -> only their own single section
 * ---------------------------------------------------------------
 */

/**
 * Returns a SQL WHERE fragment + params array restricting the `items`
 * table (aliased `i`, joined to `sections s` and `departments d`) to
 * what the current user is allowed to see.
 * Assumes the base query already joins:
 *   items i JOIN sections s ON i.section_id = s.id
 *            JOIN departments d ON s.department_id = d.id
 */
function scope_items_clause(array $user): array {
    if ($user['role'] === 'admin') {
        return ['', []];
    }
    if ($user['role'] === 'dept_manager') {
        return [' AND d.id = ?', [$user['department_id']]];
    }
    // section_chief
    return [' AND s.id = ?', [$user['section_id']]];
}

/** Verify a specific section belongs to the user's allowed scope. Returns bool. */
function user_can_access_section(PDO $pdo, array $user, int $sectionId): bool {
    if ($user['role'] === 'admin') return true;
    $stmt = $pdo->prepare("SELECT department_id FROM sections WHERE id = ?");
    $stmt->execute([$sectionId]);
    $deptId = $stmt->fetchColumn();
    if ($deptId === false) return false;

    if ($user['role'] === 'dept_manager') {
        return (int)$deptId === (int)$user['department_id'];
    }
    // section_chief - must be exactly their own section
    return (int)$sectionId === (int)$user['section_id'];
}

/** Verify a specific department belongs to the user's allowed scope. */
function user_can_access_department(array $user, int $departmentId): bool {
    if ($user['role'] === 'admin') return true;
    if ($user['role'] === 'dept_manager') return (int)$departmentId === (int)$user['department_id'];
    return false; // section chiefs never manage at department level
}

/** Verify a given item belongs to the user's allowed scope. */
function user_can_access_item(PDO $pdo, array $user, int $itemId): bool {
    if ($user['role'] === 'admin') return true;
    $stmt = $pdo->prepare(
        "SELECT s.id AS section_id, s.department_id
         FROM items i JOIN sections s ON i.section_id = s.id
         WHERE i.id = ?"
    );
    $stmt->execute([$itemId]);
    $row = $stmt->fetch();
    if (!$row) return false;

    if ($user['role'] === 'dept_manager') {
        return (int)$row['department_id'] === (int)$user['department_id'];
    }
    return (int)$row['section_id'] === (int)$user['section_id'];
}

/** Human readable role label */
function role_label(string $role): string {
    return match ($role) {
        'admin' => 'Administrator',
        'dept_manager' => 'Department Manager',
        'section_chief' => 'Section Chief',
        default => ucfirst($role),
    };
}

function section_chief_can_view_reports(array $user): bool {
    return $user['role'] === 'section_chief' && !empty($user['can_view_reports_own_section']);
}

function section_chief_can_audit(array $user): bool {
    return $user['role'] === 'section_chief' && !empty($user['can_physical_audit']);
}

function section_chief_can_request_procurement(array $user): bool {
    return $user['role'] === 'section_chief' && !empty($user['can_procurement_liaison']);
}

function section_chief_can_request_writeoff(array $user): bool {
    return $user['role'] === 'section_chief' && !empty($user['can_request_writeoff']);
}

function section_chief_is_view_only(array $user): bool {
    return $user['role'] === 'section_chief' && !empty($user['can_delegate_view']);
}

function low_stock(array $item): bool {
    return (int)$item['quantity'] <= (int)$item['low_stock_threshold'];
}
