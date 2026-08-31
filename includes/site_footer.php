<?php

declare(strict_types=1);

/**
 * Backward-compat wrapper. The real footer lives in
 * includes/partials/footer.php. Public pages still call
 * `require __DIR__ . '/includes/site_footer.php';`.
 */

require_once __DIR__ . '/partials/footer.php';