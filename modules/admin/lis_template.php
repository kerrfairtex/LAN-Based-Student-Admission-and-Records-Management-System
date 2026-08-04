<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/lis.php';

require_registrar();

$activeYear = active_school_year();
$sample = [
    lis_school_id(),
    $activeYear['label'] ?? '2025-2026',
    APP_SCHOOL,
    APP_REGION,
    lis_division(),
    'Grade 7',
    'Makiling',
    '123456789012',
    'TRAC-2026-0001',
    'Dela Cruz',
    'Juan',
    'Santos',
    '',
    '2012-05-15',
    'Male',
    'Bongao, Tawi-Tawi',
    '09171234567',
    'Maria Dela Cruz',
    'Mother',
    '09179876543',
    'new',
    date('Y-m-d'),
    'active',
    '',
    'Sample row — delete before import',
];

lis_stream_csv(LIS_CSV_COLUMNS, [$sample], 'LIS_SF1_TRAC_JHS_template.csv');
