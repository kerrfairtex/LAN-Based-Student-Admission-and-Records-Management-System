<?php

declare(strict_types=1);

/**
 * Build an application-relative URL (supports XAMPP subfolder installs).
 */
function url(string $path = '/'): string
{
    if (preg_match('#^https?://#i', $path)) {
        return $path;
    }

    if ($path === '' || $path === '/') {
        return APP_BASE_PATH === '' ? '/' : APP_BASE_PATH . '/';
    }

    return APP_BASE_PATH . '/' . ltrim($path, '/');
}

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function redirect(string $path): never
{
    header('Location: ' . url($path));
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

function csrf_token(): string
{
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['_csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function verify_csrf(?string $token): bool
{
    $sessionToken = $_SESSION['_csrf'] ?? '';

    return is_string($token)
        && $sessionToken !== ''
        && hash_equals($sessionToken, $token);
}

function require_csrf(): void
{
    $token = $_POST['_csrf'] ?? null;

    if (!verify_csrf(is_string($token) ? $token : null)) {
        http_response_code(419);
        flash('danger', 'Your session token expired. Please try again.');
        redirect('/');
    }
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

function validate_school_year_label(string $label): bool
{
    return (bool) preg_match(SCHOOL_YEAR_PATTERN, $label);
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
    $unassignedSections = 0;
    $overdueTransfers = 0;

    if ($yearId > 0) {
        $stmt = db()->prepare('SELECT COUNT(*) AS c FROM enrollments WHERE school_year_id = :year_id AND status = "enrolled"');
        $stmt->execute(['year_id' => $yearId]);
        $enrolledThisYear = (int) $stmt->fetch()['c'];

        $stmt = db()->prepare('SELECT COUNT(*) AS c FROM enrollments WHERE school_year_id = :year_id AND status = "enrolled" AND section_id IS NULL');
        $stmt->execute(['year_id' => $yearId]);
        $unassignedSections = (int) $stmt->fetch()['c'];
    }

    try {
        $overdueTransfers = (int) db()->query(
            "SELECT COUNT(*) AS c FROM transfer_requests
             WHERE status NOT IN ('completed', 'escalated') AND due_date < CURDATE()"
        )->fetch()['c'];
    } catch (PDOException) {
        $overdueTransfers = 0;
    }

    return [
        'total_students' => $totalStudents,
        'pending_admissions' => $pendingAdmissions,
        'enrolled_this_year' => $enrolledThisYear,
        'unassigned_sections' => $unassignedSections,
        'overdue_transfers' => $overdueTransfers,
        'active_school_year' => $activeYear['label'] ?? 'Not set',
    ];
}
