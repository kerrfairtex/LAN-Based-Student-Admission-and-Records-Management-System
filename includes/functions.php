<?php

declare(strict_types=1);

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function redirect(string $path): never
{
    header('Location: ' . $path);
    exit;
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function get_flash(): ?array
{
    if (!isset($_SESSION['flash'])) {
        return null;
    }

    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);

    return $flash;
}

function audit_log(string $action, string $entityType, ?int $entityId = null, ?string $details = null): void
{
    if (!isset($_SESSION['user'])) {
        return;
    }

    $stmt = db()->prepare(
        'INSERT INTO audit_logs (user_id, action, entity_type, entity_id, details, ip_address)
         VALUES (:user_id, :action, :entity_type, :entity_id, :details, :ip_address)'
    );

    $stmt->execute([
        'user_id' => $_SESSION['user']['id'],
        'action' => $action,
        'entity_type' => $entityType,
        'entity_id' => $entityId,
        'details' => $details,
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
    ]);
}

function generate_student_id(): string
{
    $year = date('Y');
    $stmt = db()->query("SELECT COUNT(*) AS total FROM students WHERE student_id_no LIKE 'TRAC-{$year}-%'");
    $count = (int) $stmt->fetch()['total'] + 1;

    return sprintf('TRAC-%s-%04d', $year, $count);
}

function generate_application_no(): string
{
    $year = date('Y');
    $stmt = db()->query("SELECT COUNT(*) AS total FROM admissions WHERE application_no LIKE 'ADM-{$year}-%'");
    $count = (int) $stmt->fetch()['total'] + 1;

    return sprintf('ADM-%s-%04d', $year, $count);
}

function validate_lrn(?string $lrn): bool
{
    if ($lrn === null || $lrn === '') {
        return true;
    }

    return (bool) preg_match('/^\d{12}$/', $lrn);
}

function validate_required(array $fields, array $input): array
{
    $errors = [];

    foreach ($fields as $field => $label) {
        if (!isset($input[$field]) || trim((string) $input[$field]) === '') {
            $errors[$field] = "{$label} is required.";
        }
    }

    return $errors;
}

function fetch_school_years(): array
{
    return db()->query('SELECT * FROM school_years ORDER BY label DESC')->fetchAll();
}

function fetch_grade_levels(): array
{
    return db()->query('SELECT * FROM grade_levels ORDER BY id')->fetchAll();
}

function fetch_sections(?int $gradeLevelId = null): array
{
    if ($gradeLevelId) {
        $stmt = db()->prepare('SELECT * FROM sections WHERE grade_level_id = :grade_level_id ORDER BY name');
        $stmt->execute(['grade_level_id' => $gradeLevelId]);

        return $stmt->fetchAll();
    }

    return db()->query(
        'SELECT s.*, g.name AS grade_name
         FROM sections s
         JOIN grade_levels g ON g.id = s.grade_level_id
         ORDER BY g.id, s.name'
    )->fetchAll();
}

function active_school_year(): ?array
{
    $stmt = db()->query('SELECT * FROM school_years WHERE is_active = 1 LIMIT 1');

    return $stmt->fetch() ?: null;
}

function dashboard_stats(): array
{
    $activeYear = active_school_year();
    $yearId = $activeYear['id'] ?? 0;

    $totalStudents = (int) db()->query("SELECT COUNT(*) AS c FROM students WHERE status = 'active'")->fetch()['c'];
    $pendingAdmissions = (int) db()->query("SELECT COUNT(*) AS c FROM admissions WHERE status = 'pending'")->fetch()['c'];
    $enrolledThisYear = 0;

    if ($yearId > 0) {
        $stmt = db()->prepare('SELECT COUNT(*) AS c FROM enrollments WHERE school_year_id = :year_id AND status = "enrolled"');
        $stmt->execute(['year_id' => $yearId]);
        $enrolledThisYear = (int) $stmt->fetch()['c'];
    }

    return [
        'total_students' => $totalStudents,
        'pending_admissions' => $pendingAdmissions,
        'enrolled_this_year' => $enrolledThisYear,
        'active_school_year' => $activeYear['label'] ?? 'Not set',
    ];
}
