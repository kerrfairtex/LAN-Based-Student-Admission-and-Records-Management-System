<?php

declare(strict_types=1);

/**
 * Backward-compat wrapper. The real header lives in
 * includes/partials/header.php. Public pages still call
 * `require __DIR__ . '/includes/site_header.php';`.
 */

require_once __DIR__ . '/partials/header.php';