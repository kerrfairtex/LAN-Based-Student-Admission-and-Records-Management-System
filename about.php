<?php

declare(strict_types=1);

/**
 * About TRAC JHS — long-form page linked from the landing nav.
 * Uses the shared public-site header/footer shell.
 */

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/includes/functions.php';

$page_title        = 'About';
$page_description  = 'About TRAC JHS, the laboratory junior high school of Tawi-Tawi Regional Agricultural College in Bongao, Tawi-Tawi.';
$active_nav        = 'about';

require __DIR__ . '/includes/site_header.php';
?>

<section class="about" style="padding-top:88px;">
    <div class="wrap">
        <span class="section-head kicker">About the school</span>
        <h1 class="display" style="font-size:clamp(28px,4vw,40px);margin-bottom:24px;">A campus built for learning by doing.</h1>

        <div class="about-grid" style="margin-top:32px;">
            <div>
                <p>TRAC JHS operates as the laboratory high school of Tawi-Tawi Regional Agricultural College, sharing its grounds, farm facilities, and faculty expertise. Students move between standard classroom instruction and applied sessions on the college's working fields.</p>
                <p>The school follows the DepEd Basic Education curriculum under the Bangsamoro Autonomous Region, with an exploratory track in agricultural technology and integrated instruction in Bangsamoro history, values, and Arabic language.</p>
                <p>The laboratory-school relationship gives TRAC JHS students early access to college-level resources, including lecture halls, demonstration farms, and shared faculty appointments in agricultural science, while staying inside a junior-high setting appropriate to their age.</p>
            </div>
            <ul class="credential-list">
                <li>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M12 3l9 5-9 5-9-5 9-5z"/><path d="M5 10v6c0 1.5 3 3 7 3s7-1.5 7-3v-6"/></svg>
                    DepEd-recognized junior high curriculum, Grades 7&ndash;10
                </li>
                <li>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><rect x="3" y="4" width="18" height="16" rx="1"/><path d="M3 9h18M8 4v5"/></svg>
                    Shared campus and faculty with Tawi-Tawi Regional Agricultural College
                </li>
                <li>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M12 21s-7-4.5-9-9a5 5 0 019-3 5 5 0 019 3c-2 4.5-9 9-9 9z"/></svg>
                    Bangsamoro values and Arabic language integrated into daily instruction
                </li>
                <li>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 3"/></svg>
                    Weekly applied sessions on the college's agricultural fields and labs
                </li>
            </ul>
        </div>
    </div>
</section>

<?php require __DIR__ . '/includes/site_footer.php'; ?>