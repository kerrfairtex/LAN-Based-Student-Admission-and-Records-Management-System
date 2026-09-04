<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/auth.php';

require_registrar();

require_csrf();

$yearId = (int) ($_POST['year_id'] ?? ($_GET['year_id'] ?? 0));

if ($yearId > 0) {
    $stmt = db()->prepare('SELECT id FROM school_years WHERE id = :id');
    $stmt->execute(['id' => $yearId]);
    if ($stmt->fetch()) {
        select_school_year($yearId);
        flash('success', 'School year updated.');
    }
}

// Open-redirect protection: only redirect to internal paths on this host.
// Reject anything that starts with a scheme (http://, https://, //) or
// contains a backslash (some user-agents normalize these into schemes).
$referer = $_SERVER['HTTP_REFERER'] ?? '';
$safe = '/dashboard.php';
if ($referer !== '') {
    $parsed = parse_url($referer);
    $hostMatches = !isset($parsed['host']) || $parsed['host'] === ($_SERVER['HTTP_HOST'] ?? '');
    $isRelative = !isset($parsed['scheme']) && !isset($parsed['host']);
    $noBackslash = !str_contains($referer, '\\');
    if ($hostMatches && $isRelative && $noBackslash) {
        $safe = $referer;
    }
}
redirect($safe);