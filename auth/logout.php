<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/auth.php';

logout_user();
flash('success', 'You have been signed out.');
redirect('/auth/login.php');
