<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/layout.php';

require_login();

$userId = (int) $_SESSION['user']['id'];
$username = (string) ($_SESSION['user']['username'] ?? '');
$forced = !empty($_SESSION['must_change_password']);
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $current = (string) ($_POST['current_password'] ?? '');
    $new = (string) ($_POST['new_password'] ?? '');
    $confirm = (string) ($_POST['confirm_password'] ?? '');

    $stmt = db()->prepare('SELECT password_hash FROM trac_jhs_sarms.users WHERE id = :id');
    $stmt->execute(['id' => $userId]);
    $user = $stmt->fetch();

    $policy = password_policy_errors($new, $username);

    if (!$user || !password_verify($current, $user['password_hash'])) {
        $error = 'Current password is incorrect.';
    } elseif ($policy !== []) {
        $error = 'New password ' . implode('; ', $policy) . '.';
    } elseif ($new !== $confirm) {
        $error = 'New passwords do not match.';
    } elseif (hash_equals($current, $new)) {
        $error = 'New password must be different from the current password.';
    } else {
        $saved = false;
        try {
            set_user_password($userId, $new, false);
            $saved = true;
        } catch (Throwable $e) {
            error_log('[password] change failed for user #' . $userId . ': ' . $e->getMessage());
            $error = 'Your password could not be saved. Nothing was changed; please try again or contact the School Registrar.';
        }
    }
    if (!empty($saved)) {
        unset($_SESSION['must_change_password']);
        session_regenerate_id(true);
        audit_log('update', 'users', $userId, $forced ? 'Changed password (forced change completed)' : 'Changed password');
        flash('success', 'Password updated successfully.');
        redirect($forced ? '/dashboard.php' : '/modules/account/password.php');
    }
}

render_header('Change Password', 'account');
?>
<div class="panel-card glass-panel" style="max-width:520px;">
    <?php if ($forced): ?>
        <div class="alert alert-warning">
            <strong>Password change required.</strong>
            Your account is using a temporary or publicly known password. Set a new one to continue.
        </div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger"><?= e($error) ?></div>
    <?php endif; ?>
    <form method="post" autocomplete="off">
        <?= csrf_field() ?>
        <div class="mb-3">
            <label class="form-label" for="current_password">Current Password</label>
            <input type="password" id="current_password" name="current_password" class="form-control"
                   autocomplete="current-password" required>
        </div>
        <div class="mb-3">
            <label class="form-label" for="new_password">New Password</label>
            <input type="password" id="new_password" name="new_password" class="form-control"
                   minlength="10" maxlength="128" autocomplete="new-password" required>
            <div class="form-text">At least 10 characters, with at least one letter and one number. Must not contain your username.</div>
        </div>
        <div class="mb-3">
            <label class="form-label" for="confirm_password">Confirm New Password</label>
            <input type="password" id="confirm_password" name="confirm_password" class="form-control"
                   minlength="10" maxlength="128" autocomplete="new-password" required>
        </div>
        <button type="submit" class="btn btn-primary">Update Password</button>
    </form>
</div>
<?php
render_footer();
