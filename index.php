<?php

declare(strict_types=1);

/**
 * TRAC JHS public landing page.
 *
 * Responsibilities:
 *   - If logged in, redirect to /dashboard.php
 *   - If inquiry form submitted (POST), validate + flash success / error
 *   - Render the public landing shell
 *
 * No login form on this page. Sign-in lives at /auth/login.php.
 * The "Staff Sign In" link in the header points there.
 */

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

if (is_logged_in()) {
    redirect('/dashboard.php');
}

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
        // Clear form values on success
        $_POST = [];
    }
}

$page_title       = 'TRAC JHS — Bongao, Tawi-Tawi';
$page_description = 'Junior High School of Tawi-Tawi Regional Agricultural College — laboratory school in Bongao, Tawi-Tawi, BARMM.';
$body_class       = 'landing-body';

require __DIR__ . '/includes/site_header.php';
?>

<!-- HERO -->
<section class="hero">
    <div class="wrap hero-grid">
        <div>
            <div class="eyebrow-row">
                <span class="dot"></span>
                Laboratory High School &middot; Tawi-Tawi Regional Agricultural College
            </div>
            <h1>Junior high education, <em>rooted</em> in the land and the community it serves.</h1>
            <p class="lede">TRAC JHS prepares students in Bongao for senior high and beyond, pairing the DepEd junior high curriculum with hands-on agricultural science and Bangsamoro values — inside a working college campus.</p>
            <div class="hero-ctas">
                <a class="btn-primary" href="#admissions">Start an application</a>
                <a class="btn-ghost" href="#about">About the school</a>
            </div>
            <div class="hero-meta">
                <div><strong>Grades 7&ndash;10</strong><span>Junior high program</span></div>
                <div><strong>1:28</strong><span>Teacher to student ratio</span></div>
                <div><strong>BARMM</strong><span>Basic education dept.</span></div>
            </div>
        </div>

        <div class="emblem-wrap">
            <div class="emblem-ring" aria-hidden="true"></div>
            <svg id="sunburst" viewBox="0 0 300 300" width="100%" aria-hidden="true">
                <circle class="fillshape" cx="150" cy="150" r="95"/>
                <circle cx="150" cy="150" r="95"/>
                <circle cx="150" cy="150" r="60"/>
                <g>
                    <path d="M150 40 L150 8 M150 260 L150 292 M40 150 L8 150 M260 150 L292 150"/>
                    <path d="M76 76 L54 54 M224 224 L246 246 M76 224 L54 246 M224 76 L246 54"/>
                </g>
                <path class="kris" d="M150 210 C 140 225, 160 235, 150 250 C 142 260, 158 268, 150 280"/>
                <path d="M115 150 Q150 120 185 150 Q150 180 115 150 Z"/>
            </svg>
        </div>
    </div>
</section>
<div class="weave" aria-hidden="true"></div>

<!-- ABOUT -->
<section class="about" id="about">
    <div class="wrap about-grid">
        <div>
            <span class="section-head kicker">About the school</span>
            <h2 style="margin-bottom:20px;font-size:clamp(24px,3vw,32px);">A campus built for learning by doing.</h2>
            <p>TRAC JHS operates as the laboratory high school of Tawi-Tawi Regional Agricultural College, sharing its grounds, farm facilities, and faculty expertise. Students move between standard classroom instruction and applied sessions on the college's working fields.</p>
            <p>The school follows the DepEd Basic Education curriculum under the Bangsamoro Autonomous Region, with an exploratory track in agricultural technology and integrated instruction in Bangsamoro history, values, and Arabic language.</p>
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
</section>

<!-- PROGRAMS -->
<section id="programs">
    <div class="wrap">
        <div class="section-head">
            <span class="kicker">Academics</span>
            <h2>What students study</h2>
            <p>Core subjects follow the national curriculum; the school's exploratory and applied tracks reflect its setting inside an agricultural college.</p>
        </div>
        <div class="programs-grid">
            <div class="program-card">
                <div class="num">Core</div>
                <h3>General junior high curriculum</h3>
                <p>Filipino, English, mathematics, science, Araling Panlipunan, MAPEH, and TLE, taught to DepEd Grades 7&ndash;10 standards.</p>
            </div>
            <div class="program-card">
                <div class="num">Applied</div>
                <h3>Agricultural technology track</h3>
                <p>Rotating fieldwork in crop production, poultry, and small-scale fisheries alongside the college's agriculture students.</p>
            </div>
            <div class="program-card">
                <div class="num">Cultural</div>
                <h3>Bangsamoro studies &amp; Arabic</h3>
                <p>Regional history, Islamic values education, and Arabic language instruction integrated across grade levels.</p>
            </div>
        </div>
    </div>
</section>

<!-- ADMISSIONS -->
<section class="admissions" id="admissions">
    <div class="wrap admissions-grid">
        <div>
            <span class="section-head kicker" style="display:block;margin-bottom:12px;">Admissions</span>
            <h2 style="font-size:clamp(24px,3vw,32px);margin-bottom:36px;">How to apply</h2>
            <div class="timeline">
                <div class="step">
                    <div class="step-dot">1</div>
                    <div>
                        <h3>Register interest</h3>
                        <p>Submit the inquiry form at the bottom of this page or visit the registrar's office on campus with a valid ID for the parent or guardian.</p>
                    </div>
                </div>
                <div class="step">
                    <div class="step-dot">2</div>
                    <div>
                        <h3>Submit documents</h3>
                        <p>Bring the completed enrollment form, PSA birth certificate, and Form 138 (report card) from the previous school.</p>
                    </div>
                </div>
                <div class="step">
                    <div class="step-dot">3</div>
                    <div>
                        <h3>Placement assessment</h3>
                        <p>Incoming Grade 7 students sit a short placement check; transferees are assessed against their previous grade level.</p>
                    </div>
                </div>
                <div class="step">
                    <div class="step-dot">4</div>
                    <div>
                        <h3>Confirm enrollment</h3>
                        <p>Once accepted, families confirm the slot with the registrar and receive the student's section and schedule.</p>
                    </div>
                </div>
            </div>
        </div>

        <aside class="admissions-side" aria-label="Requirements checklist">
            <h3>Requirements checklist</h3>
            <p>What to prepare before your visit to the registrar.</p>
            <ul class="req-list">
                <li>PSA birth certificate (original + photocopy)</li>
                <li>Form 138 / report card from previous school</li>
                <li>Certificate of Good Moral Character</li>
                <li>2 pcs 1x1 ID photo</li>
                <li>Barangay residency certificate</li>
            </ul>
            <a class="btn-primary" href="#contact" style="display:inline-block;width:100%;text-align:center;">Contact the registrar</a>
        </aside>
    </div>
</section>

<!-- CAMPUS -->
<section id="campus">
    <div class="wrap">
        <div class="section-head">
            <span class="kicker">Campus</span>
            <h2>Where students learn</h2>
        </div>
        <div class="campus-grid">
            <div class="campus-card">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M3 12h18M5 12v7h14v-7M9 19v-5h6v5"/></svg>
                <h3>Classrooms</h3>
                <p>Sectioned by grade level, shared within the college's academic building.</p>
            </div>
            <div class="campus-card">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M4 19V7l8-4 8 4v12M12 3v18M4 7l8 4 8-4"/></svg>
                <h3>Agricultural labs</h3>
                <p>Working fields and greenhouses used for applied TLE and science sessions.</p>
            </div>
            <div class="campus-card">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M4 19.5A2.5 2.5 0 016.5 17H20M4 4.5A2.5 2.5 0 016.5 2H20v18H6.5A2.5 2.5 0 014 17.5v-13z"/></svg>
                <h3>Library</h3>
                <p>Reference materials and reading space shared with the college.</p>
            </div>
            <div class="campus-card">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><rect x="3" y="4" width="18" height="14" rx="1"/><path d="M8 21h8M12 18v3"/></svg>
                <h3>Computer room</h3>
                <p>Used for TLE instruction and the registrar's records system.</p>
            </div>
        </div>
    </div>
</section>

<!-- QUOTE -->
<section class="quote-section">
    <div class="wrap">
        <div class="quote-box">
            <p class="q">&ldquo;Being on a working agricultural campus means our students see the science before they read it in a textbook.&rdquo;</p>
            <p class="quote-attr">Office of the Registrar<span>TRAC Junior High School</span></p>
        </div>
    </div>
</section>

<?php require __DIR__ . '/includes/site_footer.php'; ?>