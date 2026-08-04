<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/../../includes/lis.php';
    $action = $_POST['action'] ?? '';

    if ($action === 'add_year') {
        $label = trim($_POST['label'] ?? '');
        $start = $_POST['start_date'] ?? null;
        $end = $_POST['end_date'] ?? null;
        $setActive = isset($_POST['is_active']);

        if ($label) {
            if ($setActive) {
                db()->exec('UPDATE school_years SET is_active = 0');
            }
            db()->prepare(
                'INSERT INTO school_years (label, is_active, start_date, end_date) VALUES (:label, :active, :start, :end)'
            )->execute([
                'label' => $label,
                'active' => $setActive ? 1 : 0,
                'start' => $start ?: null,
                'end' => $end ?: null,
            ]);
            flash('success', "School year {$label} added.");
        }
        redirect('/modules/admin/settings.php');
    }

    if ($action === 'activate_year' && isset($_POST['year_id'])) {
        db()->exec('UPDATE school_years SET is_active = 0');
        db()->prepare('UPDATE school_years SET is_active = 1 WHERE id = :id')
            ->execute(['id' => (int) $_POST['year_id']]);
        flash('success', 'Active school year updated.');
        redirect('/modules/admin/settings.php');
    }

    if ($action === 'add_section') {
        $gradeId = (int) ($_POST['grade_level_id'] ?? 0);
        $name = trim($_POST['section_name'] ?? '');
        if ($gradeId && $name) {
            db()->prepare('INSERT INTO sections (grade_level_id, name) VALUES (:grade, :name)')
                ->execute(['grade' => $gradeId, 'name' => $name]);
            flash('success', "Section {$name} added.");
        }
        redirect('/modules/admin/settings.php');
    }

    if ($action === 'lis_settings') {
        $schoolId = trim($_POST['lis_school_id'] ?? '');
        $division = trim($_POST['lis_division'] ?? '');

        if ($schoolId !== '' && preg_match('/^\d{6}$/', $schoolId)) {
            set_app_setting('lis_school_id', $schoolId);
        }
        if ($division !== '') {
            set_app_setting('lis_division', $division);
        }
        flash('success', 'LIS export settings updated.');
        redirect('/modules/admin/settings.php');
    }
</div>
<?php
render_footer();
