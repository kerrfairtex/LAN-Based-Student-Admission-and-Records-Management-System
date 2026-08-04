<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/lis.php';

require_registrar();

$schoolYearId = (int) ($_GET['school_year_id'] ?? 0);
$gradeLevelId = (int) ($_GET['grade_level_id'] ?? 0) ?: null;
$sectionId = (int) ($_GET['section_id'] ?? 0) ?: null;

if ($schoolYearId <= 0) {
    $active = active_school_year();
    $schoolYearId = (int) ($active['id'] ?? 0);
}

if ($schoolYearId <= 0) {
    flash('danger', 'No school year selected.');
    redirect('/modules/admin/lis.php');
}

$stmt = db()->prepare('SELECT label FROM school_years WHERE id = :id');
$stmt->execute(['id' => $schoolYearId]);
$yearLabel = $stmt->fetch()['label'] ?? 'export';

$rows = lis_fetch_export_rows($schoolYearId, $gradeLevelId, $sectionId);
$csvRows = [];

foreach ($rows as $student) {
    $csvRows[] = lis_build_export_row($student);
}

$filename = sprintf(
    'LIS_SF1_TRAC_JHS_%s_%s.csv',
    str_replace('-', '', $yearLabel),
    date('Ymd')
);

audit_log('export', 'lis_csv', $schoolYearId, "Exported {$filename} (" . count($csvRows) . ' rows)');

lis_stream_csv(LIS_CSV_COLUMNS, $csvRows, $filename);
