<?php

declare(strict_types=1);

/**
 * Privacy policy — linked from the footer's legal row.
 * Uses the shared public-site header/footer shell.
 */

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/includes/functions.php';

$page_title        = 'Privacy Policy';
$page_description  = 'How TRAC JHS SARMS handles student records, data access, and retention.';
$active_nav        = 'privacy';

require __DIR__ . '/includes/site_header.php';
?>

<section class="about" style="padding-top:88px;">
    <div class="wrap" style="max-width:760px;">
        <span class="section-head kicker">Legal</span>
        <h1 class="display" style="font-size:clamp(28px,4vw,40px);margin-bottom:24px;">Privacy Policy</h1>

        <p style="font-size:15.5px;line-height:1.7;color:var(--cream-200);opacity:0.88;">TRAC JHS SARMS handles student records in accordance with the Data Privacy Act of 2012 (RA 10173) and DepEd Order 40, s. 2012 (Child Protection Policy). Only the School Registrar and authorized Data Encoders have write access to student records; parents and guardians may request a copy of their child's record through the registrar's office.</p>

        <h2 class="display" style="font-size:22px;color:var(--cream-100);margin-top:32px;">What we store</h2>
        <p style="font-size:15.5px;line-height:1.7;color:var(--cream-200);opacity:0.88;">For each enrolled or applying student: full name, LRN, birthdate, address, parent or guardian contact information, enrollment history, and academic records (grades, sections, school form 10). No biometric data is collected by the system.</p>

        <h2 class="display" style="font-size:22px;color:var(--cream-100);margin-top:32px;">How data is used</h2>
        <p style="font-size:15.5px;line-height:1.7;color:var(--cream-200);opacity:0.88;">Data is used solely for the administration of enrollment, academic records, and LIS reporting required by DepEd and the Bangsamoro Basic Education division. Data is not sold, traded, or shared with third parties outside these reporting obligations.</p>

        <h2 class="display" style="font-size:22px;color:var(--cream-100);margin-top:32px;">Access requests</h2>
        <p style="font-size:15.5px;line-height:1.7;color:var(--cream-200);opacity:0.88;">Parents, guardians, or students aged 18 and above may request a printed copy of their records, request correction of inaccurate data, or request deletion of inquiry-only submissions by visiting the registrar's office with a valid ID.</p>

        <h2 class="display" style="font-size:22px;color:var(--cream-100);margin-top:32px;">Contact</h2>
        <p style="font-size:15.5px;line-height:1.7;color:var(--cream-200);opacity:0.88;">For privacy questions or to file a request, contact the registrar at the address listed on the landing page.</p>
    </div>
</section>

<?php require __DIR__ . '/includes/site_footer.php'; ?>