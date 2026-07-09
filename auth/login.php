<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../includes/functions.php';

if (is_logged_in()) {
    redirect_to_dashboard();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $error = 'Please enter both email and password.';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && $user['status'] === 'active' && isset($user['failed_login_attempts']) && (int)$user['failed_login_attempts'] >= 3) {
            $pdo->prepare("UPDATE users SET status = 'disabled' WHERE id = ?")->execute([$user['id']]);
            log_audit($pdo, $user['id'], 'account_disabled', 'user', $user['id'], 'Account disabled after too many failed login attempts');
            $user['status'] = 'disabled';
        }

        if (!$user || !password_verify($password, $user['password'])) {
            $error = 'Invalid email or password.';
            // If a user row exists, try to increment failed attempts and disable after 3
            if ($user) {
                $colCheck = $pdo->prepare("SHOW COLUMNS FROM users LIKE 'failed_login_attempts'");
                try {
                    $colCheck->execute();
                    $hasFailedCol = (bool)$colCheck->fetch();
                } catch (Exception $e) {
                    $hasFailedCol = false;
                }

                if ($hasFailedCol) {
                    try {
                        $updateAttempts = $pdo->prepare("UPDATE users SET failed_login_attempts = failed_login_attempts + 1, last_failed_login = NOW() WHERE id = ?");
                        $updateAttempts->execute([$user['id']]);

                        $stmtCount = $pdo->prepare("SELECT failed_login_attempts FROM users WHERE id = ?");
                        $stmtCount->execute([$user['id']]);
                        $count = (int)$stmtCount->fetchColumn();

                        if ($count >= 3) {
                            $pdo->prepare("UPDATE users SET status = 'disabled' WHERE id = ?")->execute([$user['id']]);
                            log_audit($pdo, $user['id'], 'account_disabled', 'user', $user['id'], 'Account disabled after too many failed login attempts');
                            $error = 'Your account has been disabled after 3 failed login attempts. Contact the administrator for help.';
                        } else {
                            log_audit($pdo, $user['id'], 'login_failed', 'user', $user['id'], "Failed login attempt for $email (attempt {$count})");
                        }
                    } catch (Exception $e) {
                        log_audit($pdo, $user['id'], 'login_failed', 'user', $user['id'], "Failed login attempt for $email (schema update error)");
                    }
                } else {
                    log_audit($pdo, null, 'login_failed', 'user', null, "Failed login attempt for $email (schema missing)");
                }
            } else {
                log_audit($pdo, null, 'login_failed', 'user', null, "Failed login attempt for $email");
            }
        } elseif ($user['status'] !== 'active') {
            $error = 'Your account has been disabled. Contact the administrator.';
        } else {
            session_regenerate_id(true);
            // Reset failed attempts on successful login (if the columns exist)
            try {
                $colCheck = $pdo->prepare("SHOW COLUMNS FROM users LIKE 'failed_login_attempts'");
                $colCheck->execute();
                if ($colCheck->fetch()) {
                    $pdo->prepare("UPDATE users SET failed_login_attempts = 0, last_failed_login = NULL WHERE id = ?")->execute([$user['id']]);
                }
            } catch (Exception $e) {
                // ignore - reset not supported on older schema
            }
            $_SESSION['user_id']            = $user['id'];
            $_SESSION['full_name']          = $user['full_name'];
            $_SESSION['email']              = $user['email'];
            $_SESSION['role']               = $user['role'];
            $_SESSION['branch_id']          = $user['branch_id'];
            $_SESSION['department_id']      = $user['department_id'];
            $_SESSION['section_id']         = $user['section_id'];
            $_SESSION['profile_picture']    = $user['profile_picture'] ?? null;
            $_SESSION['can_physical_audit'] = $user['can_physical_audit'] ?? 0;
            $_SESSION['can_delegate_view']  = $user['can_delegate_view'] ?? 0;
            $_SESSION['can_procurement_liaison'] = $user['can_procurement_liaison'] ?? 0;
            $_SESSION['can_view_reports_own_section'] = $user['can_view_reports_own_section'] ?? 0;
            $_SESSION['can_request_writeoff'] = $user['can_request_writeoff'] ?? 0;
            $_SESSION['last_regen']         = time();

            log_audit($pdo, $user['id'], 'login_success', 'user', $user['id'], null);
            redirect_to_dashboard();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login — UIRI Inventory Management</title>
<link rel="stylesheet" href="/uiri-ims/assets/css/style.css">
<link rel="icon" href="/uiri-ims/assets/img/uiri-logo.png">
</head>
<body>
<div class="login-wrap">
    <div class="login-card">
        <img src="/uiri-ims/assets/img/uiri-logo.png" alt="UIRI Logo">
        <h1>UIRI Inventory Management System</h1>
        <p class="subtitle">Uganda Industrial Research Institute</p>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?= e($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <?= csrf_field() ?>
            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" required autofocus value="<?= e($_POST['email'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>
            <button type="submit" class="btn btn-primary btn-block">Sign In</button>
        </form>

        <div class="login-footer">
            &copy; <?= date('Y') ?> Uganda Industrial Research Institute<br>
     
        </div>
    </div>
</div>
</body>
</html>
