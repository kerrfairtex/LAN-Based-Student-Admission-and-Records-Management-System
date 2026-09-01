<?php

declare(strict_types=1);

/**
 * Contact — long-form contact page linked from the landing footer.
 * Uses the shared public-site header/footer shell (with the inquiry form).
 */

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

$page_inquiry_success = false;
$page_inquiry_error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['inquiry_submit'])) {
    require_csrf();

    $name    = trim((string) ($_POST['inquiry_name']    ?? ''));
    $grade   = trim((string) ($_POST['inquiry_grade']   ?? ''));
    $contact = trim((string) ($_POST['inquiry_contact'] ?? ''));

    $allowedGrades = ['Grade 7', 'Grade 8', 'Grade 9', 'Grade 10'];

    if ($name === '' || $grade === '' || $contact === '') {
        $page_inquiry_error = 'Please fill in all fields before sending the inquiry.';
    } elseif (!in_array($grade, $allowedGrades, true)) {
        $page_inquiry_error = 'Invalid grade selection.';
    } elseif (!preg_match('/^[0-9 +()-]{7,20}$/', $contact)) {
        $page_inquiry_error = 'Please provide a valid contact number.';
    } else {
        $page_inquiry_success = true;
        $_POST = [];
    }
}

$page_title        = 'Contact';
$page_description  = 'How to reach the TRAC JHS registrar, including office hours, location, and inquiry form.';
$active_nav        = 'contact';

require __DIR__ . '/includes/site_header.php';
?>

<section class="about" id="contact" style="padding-top:88px;">
    <div class="wrap">
        <span class="section-head kicker">Contact</span>
        <h1 class="display" style="font-size:clamp(28px,4vw,40px);margin-bottom:24px;">Visit or reach the registrar</h1>

        <div class="contact-list" style="margin-top:24px;display:flex;flex-direction:column;gap:16px;">
            <div>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M21 10c0 6-9 12-9 12s-9-6-9-12a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/></svg>
                <strong style="display:block;color:var(--cream-100);">Address</strong>
                TRAC campus, Bongao, Tawi-Tawi, BARMM, Philippines
            </div>
            <div>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M22 16.9v3a2 2 0 01-2.2 2 19.8 19.8 0 01-8.6-3 19.5 19.5 0 01-6-6 19.8 19.8 0 01-3-8.7A2 2 0 014.1 2h3a2 2 0 012 1.7c.1 1 .3 2 .6 2.9a2 2 0 01-.4 2.1L8 10a16 16 0 006 6l1.3-1.3a2 2 0 012.1-.4c.9.3 1.9.5 2.9.6a2 2 0 011.7 2z"/></svg>
                <strong style="display:block;color:var(--cream-100);">Office hours</strong>
                Monday to Friday, 8:00 AM &ndash; 5:00 PM
            </div>
            <div>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="M2 7l10 6 10-6"/></svg>
                <strong style="display:block;color:var(--cream-100);">Email</strong>
                registrar@tracjhs.edu.ph
            </div>
        </div>

        <p style="margin-top:36px;font-size:15.5px;line-height:1.7;color:var(--cream-200);opacity:0.88;max-width:60ch;">
            Use the form at the bottom of this page (or the form on the landing page) to send a written inquiry. The registrar's office responds within two working days.
        </p>
    </div>
</section>

<?php require __DIR__ . '/includes/site_footer.php'; ?>