<?php

declare(strict_types=1);

/**
 * Public User Guidelines page.
 *
 * Renders docs/USER_GUIDE.md at runtime through the shared public-site
 * shell (includes/partials/header.php + footer.php). The markdown file is
 * the single source of truth — this page never duplicates its content.
 *
 * Design notes:
 *   - No authentication (public documentation, like about/privacy/terms).
 *   - No database access (never calls db(); renders even during a DB
 *     outage).
 *   - Content is rendered by includes/markdown.php, which escapes all
 *     raw HTML before formatting and whitelists link URLs — the guide's
 *     <script> examples appear as visible text, never executable markup.
 *   - Page-specific styles load through the $page_css conditional added
 *     to the shared header (assets/css/user-guide.css).
 *
 * The clean URL /user-guidelines (no .php) is provided by .htaccess on
 * Apache/Render; the local PHP dev server serves /user-guidelines.php.
 */

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/markdown.php';

// Document switcher: the default is the comprehensive system guide
// (docs/USER_GUIDE.md); ?doc=journey renders the step-by-step journey
// guide (docs/USER_JOURNEY_GUIDE.md). The value is whitelisted — it
// never reaches a file path directly.
$doc = $_GET['doc'] ?? 'system';
if (!in_array($doc, ['system', 'journey'], true)) {
    $doc = 'system';
}

$page_title       = $doc === 'journey' ? 'User Journey Guide' : 'User Guidelines';
$page_description = $doc === 'journey'
    ? 'Step-by-step user journey guide for the TRAC JHS Student Admission and Records Management System: how staff sign in, navigate, enter records, and handle problems.'
    : 'Complete user guide for the TRAC JHS Student Admission and Records Management System: verified workflows, roles, installation, security, and troubleshooting.';
$active_nav       = '';
$page_css         = '/assets/css/user-guide.css';

$guidePath = $doc === 'journey'
    ? __DIR__ . '/docs/USER_JOURNEY_GUIDE.md'
    : __DIR__ . '/docs/USER_GUIDE.md';
$guideHtml = null;
$guideMissing = false;

if (is_readable($guidePath)) {
    $markdown = file_get_contents($guidePath);
    if ($markdown !== false) {
        $guideHtml = markdown_to_html($markdown);
    }
}

if ($guideHtml === null) {
    $guideMissing = true;
    // The guide file ships with the repository. A missing/unreadable file is
    // a deployment problem, not something a visitor can fix — say so plainly
    // without leaking filesystem details.
    $guideHtml = '<p>The user guide is currently unavailable. Please contact the registrar\'s office.</p>';
}

require __DIR__ . '/includes/site_header.php';
?>

<section class="ug" aria-labelledby="ug-title">
    <div class="wrap">
        <span class="section-head kicker">Documentation</span>
        <h1 class="display" id="ug-title" style="font-size:clamp(28px,4vw,40px);margin-bottom:12px;"><?= $doc === 'journey' ? 'User Journey Guide' : 'User Guidelines' ?></h1>
        <p class="ug-revision"><?= $doc === 'journey'
            ? 'Step-by-step manual for registrars, encoders, and administrators &mdash; from opening the website to signing out.'
            : 'Complete operational guide for registrars, encoders, and administrators.' ?></p>

        <nav class="ug-switcher" aria-label="Guide selection">
            <a href="<?= e(url('/user-guidelines.php')) ?>?doc=system"<?= $doc === 'system' ? ' class="is-active" aria-current="page"' : '' ?>>System Guide</a>
            <a href="<?= e(url('/user-guidelines.php')) ?>?doc=journey"<?= $doc === 'journey' ? ' class="is-active" aria-current="page"' : '' ?>>Journey Guide</a>
        </nav>

        <div class="ug-body">
            <?php
            // Already fully sanitized by markdown_to_html(): raw HTML escaped,
            // link hrefs whitelisted to http/https/#anchor.
            echo $guideHtml;
            ?>
        </div>

        <a class="ug-backtop" href="#ug-title">&uarr; Back to top</a>
    </div>
</section>

<?php require __DIR__ . '/includes/site_footer.php'; ?>
