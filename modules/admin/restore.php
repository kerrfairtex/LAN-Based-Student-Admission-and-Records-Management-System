<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/layout.php';

require_registrar();

$backupDir = __DIR__ . '/../../backups';
$backups = [];

if (is_dir($backupDir)) {
    $files = glob($backupDir . '/*.sql');
    if ($files) {
        foreach ($files as $file) {
            $backups[] = [
                'name' => basename($file),
                'size' => filesize($file),
                'modified' => date('Y-m-d H:i:s', filemtime($file)),
                'path' => $file,
            ];
        }
        usort($backups, static fn ($a, $b) => strcmp($b['modified'], $a['modified']));
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $file = $_POST['backup_file'] ?? '';

    // Validate filename to prevent path traversal
    $safeName = basename($file);
    $fullPath = $backupDir . '/' . $safeName;

    if ($safeName === '' || !preg_match('/^trac_jhs_backup_\d{4}-\d{2}-\d{2}_\d{6}\.sql$/', $safeName)) {
        flash('danger', 'Invalid backup file name.');
    } elseif (!is_file($fullPath)) {
        flash('danger', 'Backup file does not exist.');
    } else {
        try {
            $sql = file_get_contents($fullPath);
            if ($sql === false || trim($sql) === '') {
                throw new RuntimeException('Backup file is empty or unreadable.');
            }

            db()->exec('SET FOREIGN_KEY_CHECKS = 0');
            db()->exec($sql);
            db()->exec('SET FOREIGN_KEY_CHECKS = 1');

            audit_log('restore', 'database', 0, 'Database restored from ' . $safeName);
            flash('success', "Database restored from {$safeName}. Any current data was replaced.");
        } catch (Throwable $e) {
            db()->exec('SET FOREIGN_KEY_CHECKS = 1');
            flash('danger', 'Restore failed: ' . $e->getMessage());
        }
    }
    redirect('/modules/admin/restore.php');
}

render_header('Database Restore', 'backup');
?>
<div class="row g-3">
    <div class="col-lg-8">
        <div class="panel-card glass-panel">
            <h3>Available Backups</h3>
            <p class="text-muted small">Restoring replaces the <strong>entire current database</strong> with the contents of the selected backup file. This cannot be undone.</p>

            <?php if (!$backups): ?>
                <div class="text-muted py-4 text-center">
                    <i class="bi bi-inbox display-6 d-block mb-2"></i>
                    No backup files found in <code>backups/</code>. Generate one first via <a href="<?= e(url('/modules/admin/backup.php')) ?>">Database Backup</a>.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr><th>File</th><th>Size</th><th>Modified</th><th></th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($backups as $b): ?>
                                <tr>
                                    <td><i class="bi bi-file-earmark-zip me-1"></i><?= e($b['name']) ?></td>
                                    <td><?= number_format($b['size'] / 1024, 1) ?> KB</td>
                                    <td><?= e($b['modified']) ?></td>
                                    <td>
                                        <form method="post" onsubmit="return confirm('WARNING: This will REPLACE all current data with the backup. Continue?');">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="backup_file" value="<?= e($b['name']) ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger">
                                                <i class="bi bi-arrow-counterclockwise"></i> Restore
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="panel-card glass-panel">
            <h3>Restore Guidance</h3>
            <ul class="text-muted small mb-0">
                <li class="mb-2">A restore <strong>drops and recreates</strong> all tables from the backup's <code>CREATE TABLE</code> statements.</li>
                <li class="mb-2">All records created <strong>after</strong> the backup date will be lost.</li>
                <li class="mb-2">For safety, export a fresh backup <em>before</em> restoring.</li>
                <li>Restores are recorded in the audit log.</li>
            </ul>
        </div>
    </div>
</div>
<?php
render_footer();