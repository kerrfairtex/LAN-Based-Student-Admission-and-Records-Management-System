<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/auth.php';

require_login();

$yearId = (int) ($_GET['year_id'] ?? 0);

if ($yearId > 0) {
    $stmt = db()->prepare('SELECT id FROM school_years WHERE id = :id');
    $stmt->execute(['id' => $yearId]);
    if ($stmt->fetch()) {
        select_school_year($yearId);
        flash('success', 'School year updated.');
    }
}

$referer = $_SERVER['HTTP_REFERER'] ?? '';
$safe = $referer !== '' ? $referer : '/dashboard.php';
redirect($safe);