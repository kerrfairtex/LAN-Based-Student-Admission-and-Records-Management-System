<?php

declare(strict_types=1);

/**
 * TRAC JHS public landing page.
 *
 * The HTML markup is the single source of truth — it lives in templates/landing.html.
 * index.php loads the template, performs three surgical modifications, and renders it.
 *
 * Surgical modifications (must be the ONLY deviations from templates/landing.html):
 *   1. <a class="btn-portal" href="#staff">            → href="/auth/login.php"
 *   2. <a href="#staff" id="staff">                    → href="/auth/login.php"
 *   3. <div class="foot-form">                         → <form class="foot-form" method="post" action="/index.php#contact">
 *   4. <a class="btn-primary" href="#" style="...">     → <button class="btn-primary" type="submit" name="inquiry_submit" value="1" style="...">
 *   5. After opening <form>, inject the CSRF hidden field.
 *
 * To update the design: edit templates/landing.html, regenerate this PHP by re-applying
 * the surgical rules in build_landing.sh.
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
        $_POST = [];
    }
}

$templatePath = __DIR__ . '/templates/landing.html';
$html = file_get_contents($templatePath);

if ($html === false) {
    http_response_code(500);
    echo 'Landing template missing.';
    exit;
}

/* Surgical modification 1+2: repoint staff-sign-in links. */
$html = str_replace(
    '<a class="btn-portal" href="#staff">Staff Sign In</a>',
    '<a class="btn-portal" href="/auth/login.php">Staff Sign In</a>',
    $html
);
$html = str_replace(
    '<a href="#staff" id="staff" style="color:var(--gold-400);">Staff sign in →</a>',
    '<a href="/auth/login.php" id="staff" style="color:var(--gold-400);">Staff sign in →</a>',
    $html
);

/* Surgical modification 3+4+5: inject CSRF + flash + value persistence into the
   inquiry form (already a <form> in the template, just needs server plumbing). */
$csrfField = csrf_field();
$flashHtml = '';
if ($page_inquiry_success) {
    $flashHtml = '<div style="margin-bottom:14px;padding:10px 12px;border:1px solid rgba(183,225,176,0.3);border-radius:3px;font-size:13.5px;color:#B7E1B0;">Your admission inquiry has been received. The registrar\'s office will follow up with you.</div>';
} elseif ($page_inquiry_error !== '') {
    $flashHtml = '<div style="margin-bottom:14px;padding:10px 12px;border:1px solid rgba(232,183,183,0.3);border-radius:3px;font-size:13.5px;color:#E8B7B7;">' . e($page_inquiry_error) . '</div>';
}

$oldForm = <<<'HTML'
    <form class="foot-form" method="post" action="/index.php#contact">
      <label for="fname">Applicant's full name</label>
      <input id="fname" name="inquiry_name" type="text" placeholder="Juan Dela Cruz">
      <label for="fgrade">Grade level applying for</label>
      <select id="fgrade" name="inquiry_grade">
        <option>Grade 7</option><option>Grade 8</option><option>Grade 9</option><option>Grade 10</option>
      </select>
      <label for="fcontact">Contact number</label>
      <input id="fcontact" name="inquiry_contact" type="text" placeholder="09xx xxx xxxx">
      <button class="btn-primary" type="submit" name="inquiry_submit" value="1" style="display:block;text-align:center;border:0;cursor:pointer;font:inherit;color:inherit;">Send Admission Inquiry</button>
    </form>
HTML;

$gradeOptions = '';
foreach (['Grade 7','Grade 8','Grade 9','Grade 10'] as $g) {
    $sel = (($_POST['inquiry_grade'] ?? '') === $g) ? ' selected' : '';
    $gradeOptions .= "        <option{$sel}>{$g}</option>\n";
}

$nameValue    = e($_POST['inquiry_name']    ?? '');
$contactValue = e($_POST['inquiry_contact'] ?? '');

$newForm =
    "    <form class=\"foot-form\" method=\"post\" action=\"/index.php#contact\">\n"
  . "      {$csrfField}\n"
  . "      {$flashHtml}\n"
  . "      <label for=\"fname\">Applicant's full name</label>\n"
  . "      <input id=\"fname\" name=\"inquiry_name\" type=\"text\" placeholder=\"Juan Dela Cruz\" value=\"{$nameValue}\">\n"
  . "      <label for=\"fgrade\">Grade level applying for</label>\n"
  . "      <select id=\"fgrade\" name=\"inquiry_grade\">\n"
  . "{$gradeOptions}"
  . "      </select>\n"
  . "      <label for=\"fcontact\">Contact number</label>\n"
  . "      <input id=\"fcontact\" name=\"inquiry_contact\" type=\"text\" placeholder=\"09xx xxx xxxx\" value=\"{$contactValue}\">\n"
  . "      <button class=\"btn-primary\" type=\"submit\" name=\"inquiry_submit\" value=\"1\" style=\"display:block;width:100%;text-align:center;border:0;cursor:pointer;font:inherit;color:inherit;\">Send Admission Inquiry</button>\n"
  . "    </form>\n";

$html = str_replace($oldForm, $newForm, $html);

echo $html;