<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_login();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /uiri-ims/index.php');
    exit;
}
verify_csrf();

$user = current_user();
if (!$user) {
    header('Location: /uiri-ims/auth/login.php');
    exit;
}

try {
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $profilePicture = null;

    if (!empty($_FILES['profile_picture']['name'])) {
        $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
        $ext = strtolower(pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed, true)) {
            throw new Exception('Only JPG, PNG, WEBP and GIF images are allowed.');
        }
        if ($_FILES['profile_picture']['size'] > 4 * 1024 * 1024) {
            throw new Exception('Profile picture must be under 4MB.');
        }
        $fileName = 'user_' . $user['id'] . '_' . time() . '.' . $ext;
        $dest = __DIR__ . '/../uploads/profiles/' . $fileName;
        if (!move_uploaded_file($_FILES['profile_picture']['tmp_name'], $dest)) {
            throw new Exception('Could not save profile picture.');
        }
        $profilePicture = $fileName;
    }

    $updates = [];
    $params = [];

    if ($profilePicture) {
        $updates[] = 'profile_picture = ?';
        $params[] = $profilePicture;
    }

    if ($newPassword !== '' || $confirmPassword !== '' || $currentPassword !== '') {
        if ($currentPassword === '' || $newPassword === '' || $confirmPassword === '') {
            throw new Exception('Please fill in current password and the new password fields.');
        }
        if (strlen($newPassword) < 6) {
            throw new Exception('New password must be at least 6 characters.');
        }
        if ($newPassword !== $confirmPassword) {
            throw new Exception('New password and confirmation do not match.');
        }

        $stmt = $pdo->prepare('SELECT password FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$user['id']]);
        $stored = $stmt->fetchColumn();
        if (!$stored || !password_verify($currentPassword, $stored)) {
            throw new Exception('Current password is incorrect.');
        }

        $updates[] = 'password = ?';
        $params[] = password_hash($newPassword, PASSWORD_BCRYPT);
    }

    if ($updates) {
        $sql = 'UPDATE users SET ' . implode(', ', $updates) . ' WHERE id = ?';
        $params[] = $user['id'];
        $pdo->prepare($sql)->execute($params);
        $_SESSION['profile_picture'] = $profilePicture ?? ($_SESSION['profile_picture'] ?? null);
        set_flash('success', 'Profile updated successfully.');
    } else {
        set_flash('info', 'No changes were made.');
    }
} catch (Exception $e) {
    set_flash('danger', $e->getMessage());
}

$dashboardPath = match ($user['role']) {
    'admin' => '/uiri-ims/admin/dashboard.php',
    'dept_manager' => '/uiri-ims/manager/dashboard.php',
    'section_chief' => '/uiri-ims/chief/dashboard.php',
    default => '/uiri-ims/index.php',
};
header('Location: ' . $dashboardPath);
exit;
