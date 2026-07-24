<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/auth.php';

require_registrar();

$file = basename($_GET['file'] ?? '');
$path = __DIR__ . '/../../backups/' . $file;

if ($file === '' || !str_ends_with($file, '.sql') || !is_file($path)) {
    http_response_code(404);
    exit('Backup file not found.');
}

audit_log('download', 'database', null, "Downloaded backup {$file}");

header('Content-Type: application/sql');
header('Content-Disposition: attachment; filename="' . $file . '"');
header('Content-Length: ' . filesize($path));
readfile($path);
exit;
