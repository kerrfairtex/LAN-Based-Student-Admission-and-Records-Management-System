<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/auth.php';

logout_user();
flash('success', 'You have been signed out.');
// Redirect to the login page (?reason=logout) where auth/login.php already
// renders the flash message as a visible notice. Previously redirected to
// '/' (the landing page) but index.php only renders flash messages inside
// the inquiry form — never as a global banner — so the 'you have been
// signed out' message was set and then silently dropped. The login page
// is the natural post-logout destination anyway: the user can sign back in
// or navigate onward.
redirect('/auth/login.php?reason=logout');
