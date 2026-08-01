<?php

declare(strict_types=1);

const SF10_JHS_LEARNING_AREAS = [
    'Filipino',
    'English',
    'Mathematics',
    'Science',
    'Araling Panlipunan',
    'Edukasyon sa Pagpapakatao',
    'MAPEH',
    'Technology and Livelihood Education',
];

function fetch_sf10_entries(int $studentId, int $schoolYearId, int $gradeLevelId): array
{
    $stmt = db()->prepare(
        'SELECT * FROM sf10_grade_entries
         WHERE student_id = :student_id
           AND school_year_id = :school_year_id
           AND grade_level_id = :grade_level_id
         ORDER BY learning_area'
    );
    $stmt->execute([
        'student_id' => $studentId,
        'school_year_id' => $schoolYearId,
        'grade_level_id' => $gradeLevelId,
    ]);

    $entries = [];
    foreach ($stmt->fetchAll() as $row) {
        $entries[$row['learning_area']] = $row;
    }

    return $entries;
}

function save_sf10_entries(int $studentId, int $schoolYearId, int $gradeLevelId, array $grades, int $userId): void
{
    foreach (SF10_JHS_LEARNING_AREAS as $area) {
        $data = $grades[$area] ?? [];
        $q1 = $data['q1'] !== '' ? $data['q1'] : null;
        $q2 = $data['q2'] !== '' ? $data['q2'] : null;
        $q3 = $data['q3'] !== '' ? $data['q3'] : null;
        $q4 = $data['q4'] !== '' ? $data['q4'] : null;
        $final = $data['final'] !== '' ? $data['final'] : null;
        $remarks = $data['remarks'] !== '' ? $data['remarks'] : null;

        $existing = db()->prepare(
            'SELECT id FROM sf10_grade_entries
             WHERE student_id = :student_id AND school_year_id = :school_year_id
               AND grade_level_id = :grade_level_id AND learning_area = :learning_area'
        );
        $existing->execute([
            'student_id' => $studentId,
            'school_year_id' => $schoolYearId,
            'grade_level_id' => $gradeLevelId,
            'learning_area' => $area,
        ]);
        $row = $existing->fetch();

        if ($row) {
            $update = db()->prepare(
                'UPDATE sf10_grade_entries SET
                    q1_rating = :q1, q2_rating = :q2, q3_rating = :q3, q4_rating = :q4,
                    final_rating = :final, remarks = :remarks, updated_by = :updated_by
                 WHERE id = :id'
            );
            $update->execute([
                'q1' => $q1, 'q2' => $q2, 'q3' => $q3, 'q4' => $q4,
                'final' => $final, 'remarks' => $remarks,
                'updated_by' => $userId, 'id' => $row['id'],
            ]);
        } else {
            $insert = db()->prepare(
                'INSERT INTO sf10_grade_entries (
                    student_id, school_year_id, grade_level_id, learning_area,
                    q1_rating, q2_rating, q3_rating, q4_rating,
                    final_rating, remarks, updated_by
                ) VALUES (
                    :student_id, :school_year_id, :grade_level_id, :learning_area,
                    :q1, :q2, :q3, :q4, :final, :remarks, :updated_by
                )'
            );
            $insert->execute([
                'student_id' => $studentId,
                'school_year_id' => $schoolYearId,
                'grade_level_id' => $gradeLevelId,
                'learning_area' => $area,
                'q1' => $q1, 'q2' => $q2, 'q3' => $q3, 'q4' => $q4,
                'final' => $final, 'remarks' => $remarks,
                'updated_by' => $userId,
            ]);
        }
    }
}

function compute_general_average(array $entries): ?float
{
    $finals = [];
    foreach ($entries as $entry) {
        if ($entry['final_rating'] !== null) {
            $finals[] = (float) $entry['final_rating'];
        }
    }

    if (!$finals) {
        return null;
    }

    return round(array_sum($finals) / count($finals));
}
