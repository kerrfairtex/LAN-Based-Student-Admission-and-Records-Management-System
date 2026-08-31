<?php

declare(strict_types=1);

/**
 * Terms of use — linked from the footer's legal row.
 * Uses the shared public-site header/footer shell.
 */

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/includes/functions.php';

$page_title        = 'Terms of Use';
$page_description  = 'Acceptable use of TRAC JHS SARMS by staff and parents.';
$active_nav        = 'terms';

require __DIR__ . '/includes/site_header.php';
?>

<section class="about" style="padding-top:88px;">
    <div class="wrap" style="max-width:760px;">
        <span class="section-head kicker">Legal</span>
        <h1 class="display" style="font-size:clamp(28px,4vw,40px);margin-bottom:24px;">Terms of Use</h1>

        <p style="font-size:15.5px;line-height:1.7;color:var(--cream-200);opacity:0.88;">TRAC JHS SARMS is provided for the official use of the school's registrar, data encoders, and authorized administrators. By signing in you agree to use the system only for its intended purpose: managing student admission, enrollment, and academic records.</p>

        <h2 class="display" style="font-size:22px;color:var(--cream-100);margin-top:32px;">Acceptable use</h2>
        <p style="font-size:15.5px;line-height:1.7;color:var(--cream-200);opacity:0.88;">Do not share your sign-in credentials. Do not enter, alter, or delete records outside the scope of your assigned role. Do not export student data to personal devices or third-party services without written approval from the School Registrar.</p>

        <h2 class="display" style="font-size:22px;color:var(--cream-100);margin-top:32px;">Session security</h2>
        <p style="font-size:15.5px;line-height:1.7;color:var(--cream-200);opacity:0.88;">Sessions expire after 30 minutes of inactivity. Always sign out when stepping away from a workstation. Report a lost or compromised password to the registrar immediately so the account can be reset.</p>

        <h2 class="display" style="font-size:22px;color:var(--cream-100);margin-top:32px;">Audit</h2>
        <p style="font-size:15.5px;line-height:1.7;color:var(--cream-200);opacity:0.88;">All sign-ins, record edits, exports, and backups are recorded in the audit log. The Registrar reviews the audit log periodically. Misuse is grounds for revocation of access.</p>
    </div>
</section>

<?php require __DIR__ . '/includes/site_footer.php'; ?>