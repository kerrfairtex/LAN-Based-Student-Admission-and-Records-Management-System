<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/storage.php';

require_registrar();

$file = basename($_GET['file'] ?? '');

// Validate filename to prevent path traversal. The stored files are in
// the 'backups/' prefix of the storage bucket.
$path = 'backups/' . $file;

if ($file === '' || !str_ends_with($file, '.sql') || !preg_match('/^trac_jhs_backup_\\d{4}-\\d{2}-\\d{2}_\\d{6}\\.sql$/', $file)) {
    http_response_code(404);
    exit('Backup file not found.');
}

if (!storage_exists($path)) {
    http_response_code(404);
    exit('Backup file not found.');
}

audit_log('download', 'database', null, "Downloaded backup {$file}");

// Stream the file from storage to the browser for download.
storage_stream_download($path, 'application/sql', $file);
