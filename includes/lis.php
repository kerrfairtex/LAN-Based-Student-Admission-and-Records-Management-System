<?php

declare(strict_types=1);

/**
 * LIS / SF1-aligned CSV columns for DepEd Learner Information System compatibility.
 * Reference: SF1 School Register, Enhanced BEEF encoding fields, DO 35 s.2022.
 */
const LIS_CSV_COLUMNS = [
    'School_ID',
    'School_Year',
    'School_Name',
    'Region',
    'Division',
    'Grade_Level',
    'Section',
    'LRN',
    'Student_ID',
    'Last_Name',
    'First_Name',
    'Middle_Name',
    'Suffix',
    'Birthdate',
    'Sex',
    'Address',
    'Contact_Number',
    'Guardian_Name',
    'Guardian_Relationship',
    'Guardian_Contact',
    'Enrollment_Type',
    'Date_Enrolled',
    'Student_Status',
    'Previous_School',
    'Remarks',
];

function get_app_setting(string $key, string $default = ''): string
{
    try {
        $stmt = db()->prepare('SELECT setting_value FROM app_settings WHERE setting_key = :key');
        $stmt->execute(['key' => $key]);

        $row = $stmt->fetch();

        return $row ? (string) $row['setting_value'] : $default;
    } catch (PDOException) {
        return $default;
    }
}

function set_app_setting(string $key, string $value): void
{
    $stmt = db()->prepare(
        'INSERT INTO app_settings (setting_key, setting_value) VALUES (:key, :value)
         ON DUPLICATE KEY UPDATE setting_value = :value'
    );
    $stmt->execute(['key' => $key, 'value' => $value]);
}

function lis_school_id(): string
{
    return get_app_setting('lis_school_id', '000000');
}

function lis_division(): string
{
    return get_app_setting('lis_division', 'Division of Tawi-Tawi');
}

function normalize_csv_header(string $header): string
{
    $header = trim($header);
    $header = preg_replace('/\s+/', '_', $header) ?? $header;

    return strtolower(str_replace(['-', '/'], '_', $header));
}

function lis_header_map(array $headers): array
{
    $map = [];
    $aliases = [
        'school_id' => 'School_ID',
        'school_year' => 'School_Year',
        'school_name' => 'School_Name',
        'region' => 'Region',
        'division' => 'Division',
        'grade_level' => 'Grade_Level',
        'grade' => 'Grade_Level',
        'section' => 'Section',
        'lrn' => 'LRN',
        'learner_reference_number' => 'LRN',
        'student_id' => 'Student_ID',
        'last_name' => 'Last_Name',
        'firstname' => 'First_Name',
        'first_name' => 'First_Name',
        'middle_name' => 'Middle_Name',
        'middlename' => 'Middle_Name',
        'suffix' => 'Suffix',
        'extension' => 'Suffix',
        'birthdate' => 'Birthdate',
        'birth_date' => 'Birthdate',
        'date_of_birth' => 'Birthdate',
        'sex' => 'Sex',
        'gender' => 'Sex',
        'address' => 'Address',
        'contact_number' => 'Contact_Number',
        'contact' => 'Contact_Number',
        'guardian_name' => 'Guardian_Name',
        'parent_guardian_name' => 'Guardian_Name',
        'guardian_relationship' => 'Guardian_Relationship',
        'relationship' => 'Guardian_Relationship',
        'guardian_contact' => 'Guardian_Contact',
        'enrollment_type' => 'Enrollment_Type',
        'date_enrolled' => 'Date_Enrolled',
        'enrolled_at' => 'Date_Enrolled',
        'student_status' => 'Student_Status',
        'status' => 'Student_Status',
        'previous_school' => 'Previous_School',
        'remarks' => 'Remarks',
    ];

    foreach ($headers as $index => $header) {
        $normalized = normalize_csv_header($header);
        if (isset($aliases[$normalized])) {
            $map[$aliases[$normalized]] = $index;
        }
    }

    return $map;
}

function lis_normalize_grade(string $value): ?string
{
    $value = trim($value);
    if ($value === '') {
        return null;
    }

    if (preg_match('/grade\s*(\d+)/i', $value, $m)) {
        return 'Grade ' . $m[1];
    }

    if (preg_match('/^(\d+)$/', $value, $m) && (int) $m[1] >= 7 && (int) $m[1] <= 10) {
        return 'Grade ' . $m[1];
    }

    return $value;
}

function lis_normalize_sex(string $value): ?string
{
    $v = strtolower(trim($value));
    if ($v === 'm' || $v === 'male') {
        return 'Male';
    }
    if ($v === 'f' || $v === 'female') {
        return 'Female';
    }

    return in_array($value, SEX_OPTIONS, true) ? $value : null;
}

function lis_parse_date(string $value): ?string
{
    $value = trim($value);
    if ($value === '') {
        return null;
    }

    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
        return $value;
    }

    $formats = ['Y-m-d', 'd/m/Y', 'm/d/Y', 'd-m-Y', 'Y/m/d'];
    foreach ($formats as $format) {
        $dt = DateTime::createFromFormat($format, $value);
        if ($dt instanceof DateTime) {
            return $dt->format('Y-m-d');
        }
    }

    $ts = strtotime($value);

    return $ts ? date('Y-m-d', $ts) : null;
}

function lis_normalize_enrollment_type(string $value): string
{
    $v = strtolower(trim($value));
    if (in_array($v, ENROLLMENT_TYPES, true)) {
        return $v;
    }

    if (str_contains($v, 'transfer')) {
        return 'transferee';
    }
    if (str_contains($v, 'return')) {
        return 'returning';
    }

    return 'new';
}

function lis_normalize_student_status(string $value): string
{
    $v = strtolower(trim($value));

    return in_array($v, STUDENT_STATUSES, true) ? $v : STUDENT_STATUS_ACTIVE;
}

function lis_find_grade_level_id(string $gradeName): ?int
{
    $normalized = lis_normalize_grade($gradeName);
    if (!$normalized) {
        return null;
    }

    $stmt = db()->prepare('SELECT id FROM grade_levels WHERE name = :name LIMIT 1');
    $stmt->execute(['name' => $normalized]);

    $row = $stmt->fetch();

    return $row ? (int) $row['id'] : null;
}

function lis_find_section_id(int $gradeLevelId, string $sectionName): ?int
{
    $sectionName = trim($sectionName);
    if ($sectionName === '') {
        return null;
    }

    $stmt = db()->prepare(
        'SELECT id FROM sections WHERE grade_level_id = :grade AND name = :name LIMIT 1'
    );
    $stmt->execute(['grade' => $gradeLevelId, 'name' => $sectionName]);

    $row = $stmt->fetch();

    return $row ? (int) $row['id'] : null;
}

function lis_find_school_year_id(string $label): ?int
{
    $label = trim($label);
    if ($label === '') {
        return null;
    }

    $stmt = db()->prepare('SELECT id FROM school_years WHERE label = :label LIMIT 1');
    $stmt->execute(['label' => $label]);

    $row = $stmt->fetch();

    return $row ? (int) $row['id'] : null;
}

function lis_fetch_export_rows(int $schoolYearId, ?int $gradeLevelId = null, ?int $sectionId = null): array
{
    $sql = 'SELECT s.*, e.enrollment_type, e.enrolled_at, e.status AS enrollment_status,
                   sy.label AS school_year, g.name AS grade_name, sec.name AS section_name
            FROM enrollments e
            JOIN students s ON s.id = e.student_id
            JOIN school_years sy ON sy.id = e.school_year_id
            JOIN grade_levels g ON g.id = e.grade_level_id
            LEFT JOIN sections sec ON sec.id = e.section_id
            WHERE e.school_year_id = :year_id AND e.status = "enrolled"';

    $params = ['year_id' => $schoolYearId];

    if ($gradeLevelId) {
        $sql .= ' AND e.grade_level_id = :grade_id';
        $params['grade_id'] = $gradeLevelId;
    }

    if ($sectionId) {
        $sql .= ' AND e.section_id = :section_id';
        $params['section_id'] = $sectionId;
    }

    $sql .= ' ORDER BY g.id, sec.name, s.last_name, s.first_name';

    $stmt = db()->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll();
}

function lis_build_export_row(array $student): array
{
    return [
        lis_school_id(),
        $student['school_year'] ?? '',
        APP_SCHOOL,
        APP_REGION,
        lis_division(),
        $student['grade_name'] ?? '',
        $student['section_name'] ?? '',
        $student['lrn'] ?? '',
        $student['student_id_no'] ?? '',
        $student['last_name'] ?? '',
        $student['first_name'] ?? '',
        $student['middle_name'] ?? '',
        $student['suffix'] ?? '',
        $student['birthdate'] ?? '',
        $student['sex'] ?? '',
        $student['address'] ?? '',
        $student['contact_number'] ?? '',
        $student['guardian_name'] ?? '',
        $student['guardian_relationship'] ?? '',
        $student['guardian_contact'] ?? '',
        $student['enrollment_type'] ?? '',
        $student['enrolled_at'] ?? '',
        $student['status'] ?? '',
        $student['previous_school'] ?? '',
        $student['remarks'] ?? '',
    ];
}

function lis_stream_csv(array $headers, array $rows, string $filename): void
{
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-store');

    echo "\xEF\xBB\xBF";

    $out = fopen('php://output', 'w');
    fputcsv($out, $headers);

    foreach ($rows as $row) {
        fputcsv($out, $row);
    }

    fclose($out);
    exit;
}

/**
 * @return array{created:int,updated:int,skipped:int,errors:array<int,string>}
 */
function lis_import_row(array $data, int $defaultSchoolYearId, int $userId): array
{
    $result = ['action' => 'skipped', 'error' => null];

    $lastName = trim($data['Last_Name'] ?? '');
    $firstName = trim($data['First_Name'] ?? '');
    $birthdate = lis_parse_date($data['Birthdate'] ?? '');
    $sex = lis_normalize_sex($data['Sex'] ?? '');
    $address = trim($data['Address'] ?? '');
    $guardianName = trim($data['Guardian_Name'] ?? '');
    $guardianRel = trim($data['Guardian_Relationship'] ?? '');
    $guardianContact = trim($data['Guardian_Contact'] ?? '');

    if ($lastName === '' || $firstName === '' || !$birthdate || !$sex || $address === '') {
        $result['error'] = 'Missing required fields (name, birthdate, sex, address).';

        return $result;
    }

    if ($guardianName === '' || $guardianRel === '' || $guardianContact === '') {
        $result['error'] = 'Missing guardian information.';

        return $result;
    }

    $lrn = trim($data['LRN'] ?? '');
    if ($lrn !== '' && !validate_lrn($lrn)) {
        $result['error'] = 'Invalid LRN (must be 12 digits).';

        return $result;
    }

    $studentIdNo = trim($data['Student_ID'] ?? '');
    $student = null;

    if ($lrn !== '') {
        $stmt = db()->prepare('SELECT * FROM students WHERE lrn = :lrn LIMIT 1');
        $stmt->execute(['lrn' => $lrn]);
        $student = $stmt->fetch();
    }

    if (!$student && $studentIdNo !== '') {
        $stmt = db()->prepare('SELECT * FROM students WHERE student_id_no = :sid LIMIT 1');
        $stmt->execute(['sid' => $studentIdNo]);
        $student = $stmt->fetch();
    }

    $fields = [
        'lrn' => $lrn ?: null,
        'first_name' => $firstName,
        'middle_name' => trim($data['Middle_Name'] ?? '') ?: null,
        'last_name' => $lastName,
        'suffix' => trim($data['Suffix'] ?? '') ?: null,
        'birthdate' => $birthdate,
        'sex' => $sex,
        'address' => $address,
        'contact_number' => trim($data['Contact_Number'] ?? '') ?: null,
        'guardian_name' => $guardianName,
        'guardian_relationship' => $guardianRel,
        'guardian_contact' => $guardianContact,
        'previous_school' => trim($data['Previous_School'] ?? '') ?: null,
        'remarks' => trim($data['Remarks'] ?? '') ?: null,
        'status' => lis_normalize_student_status($data['Student_Status'] ?? ''),
    ];

    if ($student) {
        $update = db()->prepare(
            'UPDATE students SET
                lrn = :lrn, first_name = :first_name, middle_name = :middle_name,
                last_name = :last_name, suffix = :suffix, birthdate = :birthdate,
                sex = :sex, address = :address, contact_number = :contact_number,
                guardian_name = :guardian_name, guardian_relationship = :guardian_relationship,
                guardian_contact = :guardian_contact, previous_school = :previous_school,
                remarks = :remarks, status = :status
             WHERE id = :id'
        );
        $update->execute(array_merge($fields, ['id' => (int) $student['id']]));
        $studentId = (int) $student['id'];
        $result['action'] = 'updated';
    } else {
        if ($studentIdNo === '') {
            $studentIdNo = generate_student_id();
        } else {
            $check = db()->prepare('SELECT id FROM students WHERE student_id_no = :sid');
            $check->execute(['sid' => $studentIdNo]);
            if ($check->fetch()) {
                $studentIdNo = generate_student_id();
            }
        }

        $insert = db()->prepare(
            'INSERT INTO students (
                student_id_no, lrn, first_name, middle_name, last_name, suffix,
                birthdate, sex, address, contact_number, guardian_name,
                guardian_relationship, guardian_contact, previous_school, remarks,
                status, created_by
            ) VALUES (
                :student_id_no, :lrn, :first_name, :middle_name, :last_name, :suffix,
                :birthdate, :sex, :address, :contact_number, :guardian_name,
                :guardian_relationship, :guardian_contact, :previous_school, :remarks,
                :status, :created_by
            )'
        );
        $insert->execute(array_merge($fields, [
            'student_id_no' => $studentIdNo,
            'created_by' => $userId,
        ]));
        $studentId = (int) db()->lastInsertId();
        $result['action'] = 'created';
    }

    $schoolYearLabel = trim($data['School_Year'] ?? '');
    $schoolYearId = $schoolYearLabel ? lis_find_school_year_id($schoolYearLabel) : null;
    if (!$schoolYearId) {
        $schoolYearId = $defaultSchoolYearId;
    }

    $gradeName = trim($data['Grade_Level'] ?? '');
    $gradeLevelId = $gradeName ? lis_find_grade_level_id($gradeName) : null;

    if ($schoolYearId && $gradeLevelId) {
        $sectionId = lis_find_section_id($gradeLevelId, $data['Section'] ?? '');
        $enrollmentType = lis_normalize_enrollment_type($data['Enrollment_Type'] ?? 'new');
        $enrolledAt = lis_parse_date($data['Date_Enrolled'] ?? '') ?: date('Y-m-d');

        $existing = db()->prepare(
            'SELECT id FROM enrollments WHERE student_id = :student_id AND school_year_id = :year_id'
        );
        $existing->execute(['student_id' => $studentId, 'year_id' => $schoolYearId]);
        $enrollment = $existing->fetch();

        if ($enrollment) {
            db()->prepare(
                'UPDATE enrollments SET grade_level_id = :grade_id, section_id = :section_id,
                 enrollment_type = :type, enrolled_at = :enrolled_at WHERE id = :id'
            )->execute([
                'grade_id' => $gradeLevelId,
                'section_id' => $sectionId,
                'type' => $enrollmentType,
                'enrolled_at' => $enrolledAt,
                'id' => (int) $enrollment['id'],
            ]);
        } else {
            db()->prepare(
                'INSERT INTO enrollments (
                    student_id, school_year_id, grade_level_id, section_id,
                    enrollment_type, enrolled_at, created_by
                ) VALUES (
                    :student_id, :school_year_id, :grade_level_id, :section_id,
                    :enrollment_type, :enrolled_at, :created_by
                )'
            )->execute([
                'student_id' => $studentId,
                'school_year_id' => $schoolYearId,
                'grade_level_id' => $gradeLevelId,
                'section_id' => $sectionId,
                'enrollment_type' => $enrollmentType,
                'enrolled_at' => $enrolledAt,
                'created_by' => $userId,
            ]);
        }
    }

    return $result;
}

function lis_log_import(
    string $filename,
    ?int $schoolYearId,
    int $total,
    int $created,
    int $updated,
    int $skipped,
    int $errors,
    array $errorLines,
    int $userId
): void
{
    try {
        $stmt = db()->prepare(
            'INSERT INTO lis_import_logs (
                filename, school_year_id, rows_total, rows_created, rows_updated,
                rows_skipped, rows_errors, error_details, imported_by
            ) VALUES (
                :filename, :school_year_id, :total, :created, :updated,
                :skipped, :errors, :error_details, :imported_by
            )'
        );
        $stmt->execute([
            'filename' => $filename,
            'school_year_id' => $schoolYearId,
            'total' => $total,
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
            'errors' => $errors,
            'error_details' => $errorLines ? implode("\n", $errorLines) : null,
            'imported_by' => $userId,
        ]);
    } catch (PDOException) {
        // Table may not exist on older installs; import still succeeds.
    }
}

function lis_recent_import_logs(): array
{
    try {
        return db()->query(
            'SELECT l.*, u.full_name AS imported_by_name, sy.label AS school_year
             FROM lis_import_logs l
             JOIN users u ON u.id = l.imported_by
             LEFT JOIN school_years sy ON sy.id = l.school_year_id
             ORDER BY l.created_at DESC
             LIMIT 10'
        )->fetchAll();
    } catch (PDOException) {
        return [];
    }
}
