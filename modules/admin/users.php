<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/layout.php';

require_registrar();

$users = db()->query('SELECT id, username, full_name, role, is_active, last_active, created_at FROM users ORDER BY role, full_name')->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $username = trim($_POST['username'] ?? '');
        $fullName = trim($_POST['full_name'] ?? '');
        $role = $_POST['role'] ?? 'encoder';
        $password = $_POST['password'] ?? '';

        if ($username && $fullName && strlen($password) >= 8 && in_array($role, ['registrar', 'encoder'], true)) {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            try {
                db()->prepare(
                    'INSERT INTO users (username, password_hash, full_name, role) VALUES (:username, :hash, :full_name, :role)'
                )->execute(['username' => $username, 'hash' => $hash, 'full_name' => $fullName, 'role' => $role]);
                audit_log('create', 'users', (int) db()->lastInsertId(), "Created user {$username}");
                flash('success', "User {$username} created.");
            } catch (PDOException) {
                flash('danger', 'Username already exists.');
            }
        } else {
            flash('danger', 'Please fill all fields. Password must be at least 8 characters.');
        }
        redirect('/modules/admin/users.php');
    }

    if ($action === 'toggle' && isset($_POST['user_id'])) {
        $uid = (int) $_POST['user_id'];
        if ($uid !== (int) $_SESSION['user']['id']) {
            db()->prepare('UPDATE users SET is_active = NOT is_active WHERE id = :id')->execute(['id' => $uid]);
            audit_log('update', 'users', $uid, 'Toggled user active status');
            flash('success', 'User status updated.');
        }
        redirect('/modules/admin/users.php');
    }
}

render_header('User Management', 'users');
?>
<div class="row g-3">
    <div class="col-lg-5">
        <div class="panel-card glass-panel">
            <h3>Create User</h3>
            <form method="post">
                <input type="hidden" name="action" value="create">
                <div class="mb-3">
                    <label class="form-label">Username</label>
                    <input type="text" name="username" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Full Name</label>
                    <input type="text" name="full_name" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Role</label>
                    <select name="role" class="form-select">
                        <option value="encoder">Data Encoder (Staff)</option>
                        <option value="registrar">School Registrar (Admin)</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Initial Password</label>
                    <input type="password" name="password" class="form-control" minlength="8" required>
                </div>
                <button type="submit" class="btn btn-primary">Create User</button>
            </form>
        </div>
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
                                <td><?= e($user['last_active'] ?: 'Never') ?></td>
                                <td><?= $user['is_active'] ? 'Active' : 'Disabled' ?></td>
                                <td>
                                    <?php if ((int) $user['id'] !== (int) $_SESSION['user']['id']): ?>
                                        <form method="post" class="d-inline">
                                            <input type="hidden" name="action" value="toggle">
                                            <input type="hidden" name="user_id" value="<?= (int) $user['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-light"><?= $user['is_active'] ? 'Disable' : 'Enable' ?></button>
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
