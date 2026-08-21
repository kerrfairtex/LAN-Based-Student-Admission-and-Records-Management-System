<?php

declare(strict_types=1);

const TRANSFER_SLA_DAYS = 30;

function transfer_sla_due_date(string $firstAttendanceDate): string
{
    return date('Y-m-d', strtotime($firstAttendanceDate . ' +' . TRANSFER_SLA_DAYS . ' days'));
}

function transfer_status_label(string $status): string
{
    return match ($status) {
        'pending' => 'Pending',
        'documents_sent' => 'Documents Sent',
        'documents_received' => 'Documents Received',
        'completed' => 'Completed',
        'escalated' => 'Escalated to SGOD',
        default => ucfirst($status),
    };
}

function transfer_is_overdue(array $transfer): bool
{
    if (in_array($transfer['status'], ['completed', 'escalated'], true)) {
        return false;
    }

    return strtotime($transfer['due_date']) < strtotime('today');
}

function transfer_days_remaining(array $transfer): int
{
    return (int) floor((strtotime($transfer['due_date']) - strtotime('today')) / 86400);
}

function fetch_transfer_requests(?string $direction = null, int $limit = 0, int $offset = 0): array
{
    $sql = 'SELECT t.*, s.student_id_no, s.lrn,
                   CONCAT(s.last_name, \', \', s.first_name) AS student_name,
                   u.full_name AS created_by_name
            FROM transfer_requests t
            JOIN students s ON s.id = t.student_id
            JOIN users u ON u.id = t.created_by';

    if ($direction) {
        $sql .= ' WHERE t.direction = :direction';
    }

    $sql .= ' ORDER BY t.due_date ASC, t.created_at DESC';

    if ($limit > 0) {
        $sql .= ' LIMIT :limit OFFSET :offset';
    }

    $stmt = db()->prepare($sql);
    $params = [];
    if ($direction) {
        $params['direction'] = $direction;
    }
    if ($limit > 0) {
        $params['limit'] = $limit;
        $params['offset'] = $offset;
    }
    $stmt->execute($params);

    return $stmt->fetchAll();
}

function overdue_transfer_count(): int
{
    $stmt = db()->query(
        "SELECT COUNT(*) AS c FROM transfer_requests
         WHERE status NOT IN ('completed', 'escalated')
           AND due_date < CURRENT_DATE"
    );

    return (int) $stmt->fetch()['c'];
}
