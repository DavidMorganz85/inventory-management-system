<?php
/**
 * Session bootstrap + RBAC guards.
 * Include this AFTER db.php on every protected page.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_httponly' => true,
        'cookie_samesite' => 'Lax',
    ]);
}

// Regenerate session id periodically to reduce fixation risk
if (!isset($_SESSION['last_regen'])) {
    $_SESSION['last_regen'] = time();
} elseif (time() - $_SESSION['last_regen'] > 900) {
    session_regenerate_id(true);
    $_SESSION['last_regen'] = time();
}

function is_logged_in(): bool {
    return isset($_SESSION['user_id']);
}

function current_user(): ?array {
    if (!is_logged_in()) return null;
    return [
        'id'                => $_SESSION['user_id'],
        'full_name'         => $_SESSION['full_name'],
        'email'             => $_SESSION['email'],
        'role'              => $_SESSION['role'],
        'branch_id'         => $_SESSION['branch_id'],
        'department_id'     => $_SESSION['department_id'],
        'section_id'        => $_SESSION['section_id'],
        'profile_picture'   => $_SESSION['profile_picture'] ?? null,
        'can_physical_audit' => $_SESSION['can_physical_audit'] ?? 0,
        'can_delegate_view' => $_SESSION['can_delegate_view'] ?? 0,
        'can_procurement_liaison' => $_SESSION['can_procurement_liaison'] ?? 0,
        'can_view_reports_own_section' => $_SESSION['can_view_reports_own_section'] ?? 0,
        'can_request_writeoff' => $_SESSION['can_request_writeoff'] ?? 0,
    ];
}

/** Redirect unauthenticated users to login */
function require_login(): void {
    if (!is_logged_in()) {
        header('Location: /uiri-ims/auth/login.php');
        exit;
    }
}

/** Restrict a page to one or more roles. Usage: require_role(['admin']) */
function require_role(array $roles): void {
    require_login();
    if (!in_array($_SESSION['role'], $roles, true)) {
        http_response_code(403);
        die('<div style="font-family:sans-serif;padding:40px;text-align:center;">
                <h2>403 - Access Denied</h2>
                <p>You do not have permission to view this page.</p>
                <a href="/uiri-ims/index.php">Return to dashboard</a>
             </div>');
    }
}

/** Send a logged-in user to the correct dashboard for their role */
function redirect_to_dashboard(): void {
    switch ($_SESSION['role']) {
        case 'admin':
            header('Location: /uiri-ims/admin/dashboard.php'); break;
        case 'dept_manager':
            header('Location: /uiri-ims/manager/dashboard.php'); break;
        case 'section_chief':
            header('Location: /uiri-ims/chief/dashboard.php'); break;
        default:
            header('Location: /uiri-ims/auth/login.php');
    }
    exit;
}

function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . csrf_token() . '">';
}

function verify_csrf(): void {
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(400);
        die('Invalid CSRF token. Please go back and try again.');
    }
}
