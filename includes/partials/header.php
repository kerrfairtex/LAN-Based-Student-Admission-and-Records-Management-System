<?php

declare(strict_types=1);

/**
 * Shared public-site header.
 * Loaded by every page that renders the TRAC JHS public shell
 * (index.php, auth/login.php, about.php, privacy.php, terms.php, contact.php).
 *
 * Self-sufficient: this partial pulls in everything it needs
 * (config + functions + auth helpers) so any page that requires
 * site_header.php is automatically safe to call is_logged_in(),
 * redirect(), and csrf_field().
 *
 * Caller may set:
 *   $page_title        — string, default 'TRAC JHS'
 *   $page_description  — string, default TRAC JHS landing description
 *   $active_nav        — one of '', 'about', 'programs', 'admissions',
 *                        'campus', 'contact', 'login'
 *   $body_class        — string added to <body class="…">
 *   $hide_nav_links    — bool (default false): hide the in-page anchor nav
 *                        (used by auth/login.php where the menu doesn't apply)
 */

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../functions.php';
require_once __DIR__ . '/../auth.php';

$page_title        = $page_title        ?? 'TRAC JHS';
$page_description  = $page_description  ?? 'TRAC JHS — Junior High School of Tawi-Tawi Regional Agricultural College, Bongao, Tawi-Tawi.';
$active_nav        = $active_nav        ?? '';
$body_class        = $body_class        ?? '';
$hide_nav_links    = $hide_nav_links    ?? false;
$is_authed         = is_logged_in();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?= e($page_description) ?>">
    <title><?= e($page_title) ?> &middot; TRAC JHS</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,300;9..144,500;9..144,600;9..144,700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="<?= e(url('/assets/css/tokens.css')) ?>" rel="stylesheet">
    <link href="<?= e(url('/assets/css/landing.css')) ?>" rel="stylesheet">
</head>
<body class="<?= e($body_class) ?>">
<a class="skip-link" href="#main">Skip to main content</a>

<header class="site-header" role="banner">
    <div class="wrap site-header__row">
        <a class="brand" href="<?= e(url('/')) ?>">
            <svg class="brand-mark" viewBox="0 0 40 40" fill="none" aria-hidden="true">
                <circle cx="20" cy="20" r="19" stroke="#D4A72C" stroke-width="1"/>
                <circle cx="20" cy="20" r="8" fill="none" stroke="#D4A72C" stroke-width="1"/>
                <path d="M20 8 L20 12 M20 28 L20 32 M8 20 L12 20 M28 20 L32 20 M11.5 11.5 L14.3 14.3 M25.7 25.7 L28.5 28.5 M11.5 28.5 L14.3 25.7 M25.7 14.3 L28.5 11.5" stroke="#D4A72C" stroke-width="1"/>
            </svg>
            <span class="brand-text">
                <strong>TRAC JHS</strong>
                <span>Bongao, Tawi-Tawi</span>
            </span>
        </a>

        <?php if (!$hide_nav_links): ?>
        <nav class="site-nav" aria-label="Public sections">
            <a class="site-nav__link<?= $active_nav==='about'      ?' is-active':'' ?>" href="<?= e(url('/')) ?>#about">About</a>
            <a class="site-nav__link<?= $active_nav==='programs'   ?' is-active':'' ?>" href="<?= e(url('/')) ?>#programs">Academics</a>
            <a class="site-nav__link<?= $active_nav==='admissions' ?' is-active':'' ?>" href="<?= e(url('/')) ?>#admissions">Admissions</a>
            <a class="site-nav__link<?= $active_nav==='campus'     ?' is-active':'' ?>" href="<?= e(url('/')) ?>#campus">Campus</a>
            <a class="site-nav__link<?= $active_nav==='contact'    ?' is-active':'' ?>" href="<?= e(url('/')) ?>#contact">Contact</a>
        </nav>
        <?php endif; ?>

        <div class="site-header__actions">
            <?php if ($is_authed): ?>
                <a class="btn-portal" href="<?= e(url('/dashboard.php')) ?>">Dashboard</a>
            <?php else: ?>
                <a class="btn-portal" href="<?= e(url('/auth/login.php')) ?>">Staff Sign In</a>
            <?php endif; ?>
            <button class="menu-btn" type="button" aria-label="Toggle menu" aria-expanded="false" aria-controls="mobile-nav">☰</button>
        </div>
    </div>

    <?php if (!$hide_nav_links): ?>
    <nav class="mobile-nav" id="mobile-nav" aria-label="Public sections (mobile)" hidden>
        <a href="<?= e(url('/')) ?>#about">About</a>
        <a href="<?= e(url('/')) ?>#programs">Academics</a>
        <a href="<?= e(url('/')) ?>#admissions">Admissions</a>
        <a href="<?= e(url('/')) ?>#campus">Campus</a>
        <a href="<?= e(url('/')) ?>#contact">Contact</a>
        <?php if ($is_authed): ?>
            <a href="<?= e(url('/dashboard.php')) ?>">Dashboard</a>
        <?php else: ?>
            <a href="<?= e(url('/auth/login.php')) ?>">Staff Sign In</a>
        <?php endif; ?>
    </nav>
    <?php endif; ?>
</header>

<main id="main">