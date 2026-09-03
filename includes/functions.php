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

/**
 * Best-effort client IP for audit/inquiry logging.
 *
 * Render sits behind Cloudflare, so REMOTE_ADDR alone is the Cloudflare edge.
 * Trust CF-Connecting-IP if present (single value, already validated by CF),
 * else fall back to X-Forwarded-For first hop (stripped of whitespace/ports),
 * else REMOTE_ADDR. Returns null if all sources are empty — the caller must
 * treat null as "unknown" (do not insert an empty string into a NOT NULL
 * column; use NULL).
 *
 * No trust beyond the first hop in XFF: subsequent hops could be spoofed by
 * the client. Do not use this for rate-limiting or auth decisions.
 */
function client_ip(): ?string
{
    if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
        return substr(trim((string) $_SERVER['HTTP_CF_CONNECTING_IP']), 0, 45);
    }
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $first = trim(explode(',', (string) $_SERVER['HTTP_X_FORWARDED_FOR'])[0]);
        if ($first !== '') {
            return substr($first, 0, 45);
        }
    }
    if (!empty($_SERVER['REMOTE_ADDR'])) {
        return substr((string) $_SERVER['REMOTE_ADDR'], 0, 45);
    }
    return null;
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

// ensure_dir() is defined in config/app.php (must load before session_start),
// which is included by every page via the partials/site_header bootstrap.

function get_flash(): ?array
{
    if (!isset($_SESSION['flash'])) {
        return null;
    }

    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);

    return $flash;
}

require_once __DIR__ . '/csrf.php';

function audit_log(string $action, string $entityType, ?int $entityId = null, ?string $details = null): void
{
    // The previous version returned early when $_SESSION['user'] was unset,
    // which silently dropped every login_failed event (those happen BEFORE a
    // user is authenticated, by definition). Track them by attempt-time IP
    // instead so failed-login rows actually land in audit_logs.
    //
    // Logged-in writes use $_SESSION['user']['id']; pre-auth events (currently
    // only login_failed, but the door is open for future self-service flows
    // like public inquiry logs if they ever need audit) use NULL.
    $userId = $_SESSION['user']['id'] ?? null;

    try {
        $stmt = db()->prepare(
            'INSERT INTO audit_logs (user_id, action, entity_type, entity_id, details, ip_address)
             VALUES (:user_id, :action, :entity_type, :entity_id, :details, :ip_address)'
        );

        $stmt->execute([
            'user_id'     => $userId,
            'action'      => $action,
            'entity_type' => $entityType,
            'entity_id'   => $entityId,
            'details'     => $details,
            // Use the shared client_ip() helper so we honor CF-Connecting-IP
            // / X-Forwarded-For first hop instead of the immediate socket peer
            // (which on Render is the platform's edge, not the actual visitor).
            'ip_address'  => client_ip(),
        ]);
    } catch (Throwable $e) {
        // Audit logging must never break the user-facing flow. If the
        // audit_logs write fails (DB outage, schema drift, etc.), log the
        // failure to PHP error log so operators see it in Render logs, and
        // keep going. The action the user just performed still succeeds.
        error_log('audit_log write failed: ' . $e->getMessage());
    }
}

function generate_student_id(): string
{
    $year = date('Y');
    $stmt = db()->prepare("SELECT COUNT(*) AS total FROM students WHERE student_id_no LIKE :pattern");
    $stmt->execute(['pattern' => "TRAC-{$year}-%"]);
    $count = (int) $stmt->fetch()['total'] + 1;

    return sprintf('TRAC-%s-%04d', $year, $count);
}

function generate_application_no(): string
{
    $year = date('Y');
    $stmt = db()->prepare("SELECT COUNT(*) AS total FROM admissions WHERE application_no LIKE :pattern");
    $stmt->execute(['pattern' => "ADM-{$year}-%"]);
    $count = (int) $stmt->fetch()['total'] + 1;

    return sprintf('ADM-%s-%04d', $year, $count);
}

/**
 * Retry-on-unique-violation wrapper for INSERT statements that derive a unique
 * identifier from generate_student_id() / generate_application_no(). Both helpers
 * use COUNT(*)+1 which can race under concurrent inserts; this wrapper catches
 * the SQLSTATE 23505 unique-violation exception and retries up to N times with
 * a freshly-generated id before giving up.
 *
 * Usage:
 *   insert_with_unique_retry(function () use ($stmt, $params) {
 *       $stmt->execute($params);
 *   });
 *
 * @param callable $fn    The insert closure (no args, no return).
 * @param int      $maxAttempts
 * @return int             Last inserted id (or 0 on final failure).
 */
function insert_with_unique_retry(callable $fn, int $maxAttempts = 5): int
{
    $attempt = 0;
    while ($attempt < $maxAttempts) {
        try {
            $fn();
            return (int) db()->lastInsertId();
        } catch (PDOException $e) {
            // 23505 = unique_violation in PostgreSQL. Also handle MySQL's 1062
            // for environments that still use it.
            $isUniqueViolation =
                $e->getCode() === '23505' ||
                (strpos($e->getMessage(), '23505') !== false) ||
                $e->getCode() === '1062' ||
                (strpos($e->getMessage(), '1062') !== false);

            if (!$isUniqueViolation || ++$attempt >= $maxAttempts) {
                throw $e;
            }
            // Loop continues: caller is expected to regenerate the id (e.g. via
            // $params['application_no'] = generate_application_no()) before the
            // next call. If your closure captures the id by value, capture by
            // reference instead.
        }
    }
    return 0;
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
    if (isset($_SESSION['selected_school_year_id'])) {
        $stmt = db()->prepare('SELECT * FROM school_years WHERE id = :id');
        $stmt->execute(['id' => $_SESSION['selected_school_year_id']]);
        $year = $stmt->fetch();
        if ($year) {
            return $year;
        }
    }
    $stmt = db()->query('SELECT * FROM school_years WHERE is_active = 1 LIMIT 1');
    return $stmt->fetch() ?: null;
}

function fetch_all_school_years(): array
{
    return db()->query('SELECT * FROM school_years ORDER BY label DESC')->fetchAll();
}

function select_school_year(int $yearId): void
{
    $_SESSION['selected_school_year_id'] = $yearId;
}

/**
 * @return array{overdue: array, recent: array}
 */
function fetch_notifications(): array
{
    $notifications = ['overdue' => [], 'recent' => []];

    try {
        $overdue = db()->query(
            "SELECT t.id, t.direction, t.counterpart_school, t.due_date,
                    s.student_id_no, s.first_name, s.last_name
             FROM transfer_requests t
             JOIN students s ON s.id = t.student_id
             WHERE t.status NOT IN ('completed', 'escalated')
               AND t.due_date < CURRENT_DATE
             ORDER BY t.due_date ASC
             LIMIT 10"
        )->fetchAll();
        $notifications['overdue'] = $overdue;
    } catch (PDOException) {
    }

    try {
        $recent = db()->query(
            "SELECT a.id, a.application_no, a.status, a.first_name, a.last_name,
                    a.created_at, u.full_name AS reviewer
             FROM admissions a
             LEFT JOIN users u ON u.id = a.reviewed_by
             WHERE a.status = 'approved'
               AND a.reviewed_at >= NOW() - INTERVAL '7 days'
             ORDER BY a.reviewed_at DESC
             LIMIT 10"
        )->fetchAll();
        $notifications['recent'] = $recent;
    } catch (PDOException) {
    }

    return $notifications;
}

/**
 * Pagination helper.
 *
 * @return array{data: array, total: int, per_page: int, current_page: int, last_page: int, offset: int}
 */
function paginate(int $total, int $perPage = 20, int $currentPage = 1): array
{
    $currentPage = max(1, $currentPage);
    $lastPage = (int) ceil($total / $perPage);
    $offset = ($currentPage - 1) * $perPage;

    return [
        'data' => [],
        'total' => $total,
        'per_page' => $perPage,
        'current_page' => $currentPage,
        'last_page' => max(1, $lastPage),
        'offset' => $offset,
    ];
}

function render_pager(int $currentPage, int $lastPage, string $baseUrl): string
{
    if ($lastPage <= 1) {
        return '';
    }

    $html = '<nav aria-label="Pagination"><ul class="pagination justify-content-center">';
    $params = $_GET;
    unset($params['page']);

    // First
    $firstUrl = $baseUrl . '?' . http_build_query(array_merge($params, ['page' => 1]));
    $html .= '<li class="page-item ' . ($currentPage <= 1 ? 'disabled' : '') . '"><a class="page-link" href="' . e($firstUrl) . '">&laquo;</a></li>';

    // Previous
    $prevUrl = $baseUrl . '?' . http_build_query(array_merge($params, ['page' => max(1, $currentPage - 1)]));
    $html .= '<li class="page-item ' . ($currentPage <= 1 ? 'disabled' : '') . '"><a class="page-link" href="' . e($prevUrl) . '">&lsaquo;</a></li>';

    // Page numbers (window of 5)
    $start = max(1, $currentPage - 2);
    $end = min($lastPage, $currentPage + 2);
    for ($i = $start; $i <= $end; $i++) {
        $pageUrl = $baseUrl . '?' . http_build_query(array_merge($params, ['page' => $i]));
        $html .= '<li class="page-item ' . ($i === $currentPage ? 'active' : '') . '"><a class="page-link" href="' . e($pageUrl) . '">' . $i . '</a></li>';
    }

    // Next
    $nextUrl = $baseUrl . '?' . http_build_query(array_merge($params, ['page' => min($lastPage, $currentPage + 1)]));
    $html .= '<li class="page-item ' . ($currentPage >= $lastPage ? 'disabled' : '') . '"><a class="page-link" href="' . e($nextUrl) . '">&rsaquo;</a></li>';

    // Last
    $lastUrl = $baseUrl . '?' . http_build_query(array_merge($params, ['page' => $lastPage]));
    $html .= '<li class="page-item ' . ($currentPage >= $lastPage ? 'disabled' : '') . '"><a class="page-link" href="' . e($lastUrl) . '">&raquo;</a></li>';

    $html .= '</ul></nav>';
    return $html;
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
        $stmt = db()->prepare('SELECT COUNT(*) AS c FROM enrollments WHERE school_year_id = :year_id AND status = \'enrolled\'');
        $stmt->execute(['year_id' => $yearId]);
        $enrolledThisYear = (int) $stmt->fetch()['c'];

        $stmt = db()->prepare('SELECT COUNT(*) AS c FROM enrollments WHERE school_year_id = :year_id AND status = \'enrolled\' AND section_id IS NULL');
        $stmt->execute(['year_id' => $yearId]);
        $unassignedSections = (int) $stmt->fetch()['c'];
    }

    try {
        $overdueTransfers = (int) db()->query(
            "SELECT COUNT(*) AS c FROM transfer_requests
             WHERE status NOT IN ('completed', 'escalated') AND due_date < CURRENT_DATE"
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
