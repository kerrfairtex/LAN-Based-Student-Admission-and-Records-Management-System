<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/lis.php';

require_registrar();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/modules/admin/lis.php');
}

require_csrf();

$defaultSchoolYearId = (int) ($_POST['default_school_year_id'] ?? 0);

if ($defaultSchoolYearId <= 0) {
    flash('danger', 'Select a default school year.');
    redirect('/modules/admin/lis.php');
}

if (!isset($_FILES['lis_csv']) || $_FILES['lis_csv']['error'] !== UPLOAD_ERR_OK) {
    flash('danger', 'Please upload a valid CSV file.');
    redirect('/modules/admin/lis.php');
}

$filename = basename($_FILES['lis_csv']['name']);
$tmpPath = $_FILES['lis_csv']['tmp_name'];

$handle = fopen($tmpPath, 'r');
if (!$handle) {
    flash('danger', 'Unable to read uploaded file.');
    redirect('/modules/admin/lis.php');
}

$headerRow = fgetcsv($handle);
if (!$headerRow) {
    fclose($handle);
    flash('danger', 'CSV file is empty.');
    redirect('/modules/admin/lis.php');
}

$headerMap = lis_header_map($headerRow);
$required = ['Last_Name', 'First_Name', 'Birthdate', 'Sex', 'Address'];
$missing = array_filter($required, static fn (string $col): bool => !isset($headerMap[$col]));

if ($missing) {
    fclose($handle);
    flash('danger', 'Missing required columns: ' . implode(', ', $missing));
    redirect('/modules/admin/lis.php');
}

$userId = (int) $_SESSION['user']['id'];
$created = 0;
$updated = 0;
$skipped = 0;
$errors = 0;
$errorLines = [];
$lineNum = 1;

db()->beginTransaction();

try {
    while (($row = fgetcsv($handle)) !== false) {
        $lineNum++;

        if (count(array_filter($row, static fn ($v) => trim((string) $v) !== '')) === 0) {
            continue;
        }

        $data = [];
        foreach ($headerMap as $column => $index) {
            $data[$column] = isset($row[$index]) ? trim((string) $row[$index]) : '';
        }

        $result = lis_import_row($data, $defaultSchoolYearId, $userId);

        if ($result['error']) {
            $errors++;
            $errorLines[] = "Line {$lineNum}: {$result['error']}";
            continue;
        }

        if ($result['action'] === 'created') {
            $created++;
        } elseif ($result['action'] === 'updated') {
            $updated++;
        } else {
            $skipped++;
        }
    }

    db()->commit();
} catch (Throwable $e) {
    db()->rollBack();
    fclose($handle);
    flash('danger', 'Import failed: ' . $e->getMessage());
    redirect('/modules/admin/lis.php');
}

fclose($handle);

$total = $created + $updated + $skipped + $errors;
lis_log_import($filename, $defaultSchoolYearId, $total, $created, $updated, $skipped, $errors, $errorLines, $userId);
audit_log('import', 'lis_csv', $defaultSchoolYearId, "Imported {$filename}: {$created} created, {$updated} updated, {$errors} errors");

$message = "Import complete: {$created} created, {$updated} updated, {$skipped} skipped, {$errors} errors.";
if ($errors > 0) {
    flash('warning', $message);
} else {
    flash('success', $message);
}

if ($errorLines) {
    $_SESSION['lis_import_errors'] = array_slice($errorLines, 0, 20);
}

redirect('/modules/admin/lis.php');
