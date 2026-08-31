<?php

declare(strict_types=1);

/**
 * Render / load-balancer healthcheck endpoint.
 *
 * Returns 200 + minimal JSON when the PHP process can serve requests.
 * Deliberately does NOT touch the DB so a transient DB outage doesn't
 * take the whole service out of the load balancer rotation.
 *
 * Required by render.yaml → healthCheckPath: /healthcheck.php
 */

http_response_code(200);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
echo json_encode([
    'status'  => 'ok',
    'service' => 'trac-jhs-sarms',
    'time'    => gmdate('c'),
], JSON_UNESCAPED_SLASHES) . "\n";