<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/layout.php';

require_registrar();

$selfId = (int) $_SESSION['user']['id'];
$validRoles = [ROLE_REGISTRAR, ROLE_ENCODER];

/** Fetch one user row by id (null if missing). */
$findUser = static function (int $id): ?array {
    $stmt = db()->prepare('SELECT id, username, full_name, role, is_active FROM users WHERE id = :id');
    $stmt->execute(['id' => $id]);
    $row = $stmt->fetch();
    return $row ?: null;
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'create') {
        $username = trim((string) ($_POST['username'] ?? ''));
        $fullName = trim((string) ($_POST['full_name'] ?? ''));
        $role = (string) ($_POST['role'] ?? ROLE_ENCODER);
        $password = (string) ($_POST['password'] ?? '');

        $messages = [];
        if (!preg_match('/^[A-Za-z0-9._-]{3,32}$/', $username)) {
            $messages[] = 'username must be 3-32 chars, letters/digits/._- only';
        }
        if ($fullName === '' || mb_strlen($fullName) > 150) {
            $messages[] = 'full name is required (max 150 characters)';
        }
        if (!in_array($role, $validRoles, true)) {
            $messages[] = 'invalid role';
        }
        foreach (password_policy_errors($password, $username) as $p) {
            $messages[] = 'password ' . $p;
        }

        if ($messages === []) {
            try {
                db()->prepare(
                    'INSERT INTO users (username, password_hash, full_name, role) VALUES (:username, :hash, :full_name, :role)'
                )->execute([
                    'username' => $username,
                    'hash' => password_hash($password, PASSWORD_DEFAULT),
                    'full_name' => $fullName,
                    'role' => $role,
                ]);
                $newId = (int) db()->lastInsertId();
                // The registrar chose this password, so the user must replace
                // it with one only they know on first sign-in.
                set_user_password($newId, $password, true);
                audit_log('create', 'users', $newId, "Created user {$username} ({$role})");
                flash('success', "User {$username} created. They will be asked to set a new password on first sign-in.");
            } catch (PDOException $e) {
                flash('danger', $e->getCode() === '23505' ? 'Username already exists.' : 'Could not create user.');
            }
        } else {
            flash('danger', 'Cannot create user: ' . implode('; ', $messages) . '.');
        }
        redirect('/modules/admin/users.php');
    }

    if ($action === 'update') {
        $uid = (int) ($_POST['user_id'] ?? 0);
        $target = $findUser($uid);
        $fullName = trim((string) ($_POST['full_name'] ?? ''));
        $role = (string) ($_POST['role'] ?? '');

        if (!$target) {
            flash('danger', 'User not found.');
        } elseif ($fullName === '' || mb_strlen($fullName) > 150) {
            flash('danger', 'Full name is required (max 150 characters).');
        } elseif (!in_array($role, $validRoles, true)) {
            flash('danger', 'Invalid role.');
        } elseif ($uid === $selfId && $role !== $target['role']) {
            flash('danger', 'You cannot change your own role. Ask another registrar.');
        } elseif ($target['role'] === ROLE_REGISTRAR && $role !== ROLE_REGISTRAR
            && (int) $target['is_active'] === 1 && active_registrar_count() <= 1) {
            flash('danger', 'Cannot demote the last active registrar.');
        } else {
            db()->prepare('UPDATE users SET full_name = :fn, role = :role WHERE id = :id')
                ->execute(['fn' => $fullName, 'role' => $role, 'id' => $uid]);
            $changes = [];
            if ($fullName !== $target['full_name']) {
                $changes[] = 'name';
            }
            if ($role !== $target['role']) {
                $changes[] = "role {$target['role']} -> {$role}";
            }
            audit_log('update', 'users', $uid, "Updated user {$target['username']}" . ($changes ? ': ' . implode(', ', $changes) : ''));
            flash('success', "User {$target['username']} updated.");
        }
        redirect('/modules/admin/users.php');
    }

    if ($action === 'reset_password') {
        $uid = (int) ($_POST['user_id'] ?? 0);
        $target = $findUser($uid);
        $password = (string) ($_POST['temp_password'] ?? '');
        $policy = $target ? password_policy_errors($password, $target['username']) : [];

        if (!$target) {
            flash('danger', 'User not found.');
        } elseif ($uid === $selfId) {
            flash('danger', 'Use Account → Change Password for your own account.');
        } elseif ($policy !== []) {
            flash('danger', 'Temporary password ' . implode('; ', $policy) . '.');
        } else {
            set_user_password($uid, $password, true);
            $cleared = reset_login_throttle($target['username']);
            audit_log('update', 'users', $uid, "Reset password for {$target['username']} (must change on next sign-in; cleared {$cleared} lockout row(s))");
            flash('success', "Password for {$target['username']} reset. Give them the temporary password; they must change it on next sign-in.");
        }
        redirect('/modules/admin/users.php');
    }

    if ($action === 'toggle') {
        $uid = (int) ($_POST['user_id'] ?? 0);
        $target = $findUser($uid);
        if ($target && $uid !== $selfId) {
            $newState = (int) $target['is_active'] ? 0 : 1;
            if ($newState === 0 && $target['role'] === ROLE_REGISTRAR && active_registrar_count() <= 1) {
                flash('danger', 'Cannot disable the last active registrar.');
            } else {
                db()->prepare('UPDATE users SET is_active = :s WHERE id = :id')
                    ->execute(['s' => $newState, 'id' => $uid]);
                audit_log('update', 'users', $uid, "User {$target['username']} set to " . ($newState ? 'active' : 'disabled'));
                flash('success', "User {$target['username']} " . ($newState ? 'enabled' : 'disabled (signed out on their next request)') . '.');
            }
        }
        redirect('/modules/admin/users.php');
    }

    if ($action === 'reset_throttle') {
        $target = trim((string) ($_POST['username'] ?? ''));
        if ($target !== '') {
            $cleared = reset_login_throttle($target);
            audit_log('update', 'users', null, "Cleared {$cleared} failed-login audit row(s) for {$target}");
            flash('success', "Cleared {$cleared} failed-login record(s) for {$target}. They can sign in again now.");
        }
        redirect('/modules/admin/users.php');
    }

    flash('danger', 'Unknown action.');
    redirect('/modules/admin/users.php');
}

$users = db()->query(
    'SELECT id, username, full_name, role, is_active, last_active, created_at FROM users ORDER BY role, full_name'
)->fetchAll();

$editId = (int) ($_GET['edit'] ?? 0);
$editUser = $editId > 0 ? $findUser($editId) : null;

render_header('User Management', 'users');
?>
<div class="row g-3">
    <div class="col-lg-5">
        <?php if ($editUser): ?>
            <div class="panel-card glass-panel mb-3">
                <h3>Edit User: <?= e($editUser['username']) ?></h3>
                <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="user_id" value="<?= (int) $editUser['id'] ?>">
                    <div class="mb-3">
                        <label class="form-label" for="edit_full_name">Full Name</label>
                        <input type="text" id="edit_full_name" name="full_name" class="form-control" maxlength="150"
                               value="<?= e($editUser['full_name']) ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="edit_role">Role</label>
                        <select id="edit_role" name="role" class="form-select" <?= (int) $editUser['id'] === $selfId ? 'disabled' : '' ?>>
                            <option value="encoder" <?= $editUser['role'] === 'encoder' ? 'selected' : '' ?>>Data Encoder (Staff)</option>
                            <option value="registrar" <?= $editUser['role'] === 'registrar' ? 'selected' : '' ?>>School Registrar (Admin)</option>
                        </select>
                        <?php if ((int) $editUser['id'] === $selfId): ?>
                            <input type="hidden" name="role" value="<?= e($editUser['role']) ?>">
                            <div class="form-text">You cannot change your own role.</div>
                        <?php endif; ?>
                    </div>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                    <a href="<?= e(url('/modules/admin/users.php')) ?>" class="btn btn-outline-light">Cancel</a>
                </form>
            </div>

            <?php if ((int) $editUser['id'] !== $selfId): ?>
                <div class="panel-card glass-panel mb-3">
                    <h3>Reset Password</h3>
                    <p class="text-muted small">Sets a temporary password and clears any sign-in lockout. The user must choose a new password at their next sign-in.</p>
                    <form method="post" autocomplete="off">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="reset_password">
                        <input type="hidden" name="user_id" value="<?= (int) $editUser['id'] ?>">
                        <div class="mb-3">
                            <label class="form-label" for="temp_password">Temporary Password</label>
                            <input type="text" id="temp_password" name="temp_password" class="form-control"
                                   minlength="10" maxlength="128" autocomplete="off" required>
                            <div class="form-text">At least 10 characters, with a letter and a number.</div>
                        </div>
                        <button type="submit" class="btn btn-warning">Reset Password</button>
                    </form>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <div class="panel-card glass-panel">
                <h3>Create User</h3>
                <form method="post" autocomplete="off">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="create">
                    <div class="mb-3">
                        <label class="form-label" for="username">Username</label>
                        <input type="text" id="username" name="username" class="form-control"
                               pattern="[A-Za-z0-9._\-]{3,32}" maxlength="32" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="full_name">Full Name</label>
                        <input type="text" id="full_name" name="full_name" class="form-control" maxlength="150" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="role">Role</label>
                        <select id="role" name="role" class="form-select">
                            <option value="encoder">Data Encoder (Staff)</option>
                            <option value="registrar">School Registrar (Admin)</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="password">Initial Password</label>
                        <input type="password" id="password" name="password" class="form-control"
                               minlength="10" maxlength="128" autocomplete="new-password" required>
                        <div class="form-text">At least 10 characters, with a letter and a number. The user must change it on first sign-in.</div>
                    </div>
                    <button type="submit" class="btn btn-primary">Create User</button>
                </form>
            </div>
        <?php endif; ?>
    </div>
    <div class="col-lg-7">
        <div class="table-card glass-panel">
            <h3>System Users</h3>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Username</th>
                            <th>Name</th>
                            <th>Role</th>
                            <th>Last Active</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td><?= e($user['username']) ?></td>
                                <td><?= e($user['full_name']) ?></td>
                                <td><?= e(ucfirst($user['role'])) ?></td>
                                <td><?= e($user['last_active'] ? date('M j, Y g:i A', strtotime((string) $user['last_active'])) : 'Never') ?></td>
                                <td><?= $user['is_active'] ? 'Active' : 'Disabled' ?></td>
                                <td class="text-nowrap">
                                    <a href="<?= e(url('/modules/admin/users.php?edit=' . (int) $user['id'])) ?>"
                                       class="btn btn-sm btn-outline-light">Edit</a>
                                    <?php if ((int) $user['id'] !== $selfId): ?>
                                        <form method="post" class="d-inline">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="toggle">
                                            <input type="hidden" name="user_id" value="<?= (int) $user['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-light"><?= $user['is_active'] ? 'Disable' : 'Enable' ?></button>
                                        </form>
                                        <form method="post" class="d-inline">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="reset_throttle">
                                            <input type="hidden" name="username" value="<?= e($user['username']) ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-warning"
                                                    title="Clear failed-login counter for this user (un-throttle a locked-out account)">
                                                Reset Lockout
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php
render_footer();
