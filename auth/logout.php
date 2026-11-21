<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/auth.php';

// Logout is a state-changing action, so it is POST-only and CSRF-protected.
// A plain GET link (<img src="/auth/logout.php">) on any page could otherwise
// sign a user out without their consent. GET requests are bounced back.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(is_logged_in() ? '/dashboard.php' : '/auth/login.php');
}

require_csrf();

logout_user();
flash('success', 'You have been signed out.');
// Redirect to the login page (?reason=logout) where auth/login.php already
// renders the flash message as a visible notice.
redirect('/auth/login.php?reason=logout');
