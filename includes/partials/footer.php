<?php

declare(strict_types=1);

/**
 * Shared public-site footer.
 * Loaded by every page that renders the TRAC JHS public shell.
 *
 * Caller may set $page_inquiry_success / $page_inquiry_error
 * (booleans) to surface inquiry-form feedback inside the foot-form.
 *
 * Caller may set $minimal_footer (bool, default false): when true,
 * the "Visit or reach the registrar" + inquiry form block is omitted.
 * Used by auth/login.php where the full contact + inquiry form is
 * redundant and forces a 600-800px scroll past the login card on mobile.
 * The legal links + copyright + attribution are still rendered.
 */
$minimal_footer = $minimal_footer ?? false;
?>
</main>

<?php if (!$minimal_footer): ?>
<footer class="site-footer" id="contact" role="contentinfo">
    <div class="wrap contact-grid">
        <div>
            <h2>Visit or reach the registrar</h2>
            <p>Have a question about enrollment, records, or the campus? The registrar's office handles all student and admission records.</p>
            <div class="contact-list">
                <div>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                        <path d="M21 10c0 6-9 12-9 12s-9-6-9-12a9 9 0 0118 0z"/>
                        <circle cx="12" cy="10" r="3"/>
                    </svg>
                    TRAC campus, Bongao, Tawi-Tawi, BARMM
                </div>
                <div>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                        <path d="M22 16.9v3a2 2 0 01-2.2 2 19.8 19.8 0 01-8.6-3 19.5 19.5 0 01-6-6 19.8 19.8 0 01-3-8.7A2 2 0 014.1 2h3a2 2 0 012 1.7c.1 1 .3 2 .6 2.9a2 2 0 01-.4 2.1L8 10a16 16 0 006 6l1.3-1.3a2 2 0 012.1-.4c.9.3 1.9.5 2.9.6a2 2 0 011.7 2z"/>
                    </svg>
                    Registrar's office &middot; Mon&ndash;Fri, 8am&ndash;5pm
                </div>
                <div>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                        <rect x="2" y="4" width="20" height="16" rx="2"/>
                        <path d="M2 7l10 6 10-6"/>
                    </svg>
                    registrar@tracjhs.edu.ph
                </div>
            </div>
        </div>

        <form class="foot-form" method="post" action="<?= e(url('/index.php')) ?>#contact" data-inquiry-form>
            <?= csrf_field() ?>

            <?php if (!empty($page_inquiry_success)): ?>
                <div class="form-flash form-flash--success" role="status">
                    Your inquiry has been received. The registrar's office will follow up by email.
                </div>
            <?php elseif (!empty($page_inquiry_error)): ?>
                <div class="form-flash form-flash--danger" role="alert">
                    <?= e($page_inquiry_error) ?>
                </div>
            <?php endif; ?>

            <label for="inq-name">Full name of applicant</label>
            <input id="inq-name" name="inquiry_name" type="text" autocomplete="name" required placeholder="Juan Dela Cruz" value="<?= e($_POST['inquiry_name'] ?? '') ?>">

            <label for="inq-grade">Applying for grade</label>
            <select id="inq-grade" name="inquiry_grade" required>
                <?php foreach (['Grade 7','Grade 8','Grade 9','Grade 10'] as $g): ?>
                    <option<?= (($_POST['inquiry_grade'] ?? '') === $g) ? ' selected' : '' ?>><?= e($g) ?></option>
                <?php endforeach; ?>
            </select>

            <label for="inq-contact">Contact number</label>
            <input id="inq-contact" name="inquiry_contact" type="text" autocomplete="tel" required placeholder="09xx xxx xxxx" value="<?= e($_POST['inquiry_contact'] ?? '') ?>">

            <button class="btn-primary" type="submit" name="inquiry_submit" value="1" style="display:block;width:100%;text-align:center;border:0;cursor:pointer;">
                Send inquiry
            </button>
        </form>
    </div>
<?php endif; ?>

<?php
/* $card_legal: when true, suppress the page-level footer entirely
   (including the <footer> tag itself) because all the legal content
   is rendered INSIDE the .login-card on the auth/login page.
   Prevents an empty <footer> element from polluting the DOM. */
$card_legal = $card_legal ?? false;
?>
<?php if (!$card_legal): ?>
<footer class="site-footer" id="contact" role="contentinfo">
    <div class="wrap foot-bottom">
        <span>&copy; <?= date('Y') ?> TRAC Junior High School. Laboratory school of Tawi-Tawi Regional Agricultural College.</span>
        <nav class="legal-row" aria-label="Legal">
            <a href="<?= e(url('/privacy.php')) ?>">Privacy</a>
            <a href="<?= e(url('/terms.php')) ?>">Terms</a>
            <a href="<?= e(url('/about.php')) ?>">About</a>
            <?php if ($is_authed ?? false): ?>
                <a href="<?= e(url('/auth/logout.php')) ?>">Sign out</a>
            <?php elseif (!$minimal_footer): ?>
                <a href="<?= e(url('/auth/login.php')) ?>">Staff sign in &rarr;</a>
            <?php endif; ?>
        </nav>
    </div>

    <div class="wrap foot-attribution">
        <p class="foot-attribution__system">TRAC JHS Student Admission and Records Management System</p>
        <p class="foot-attribution__credits">
            <span class="foot-attribution__label">System Development Team</span>
            <span class="foot-attribution__names">
                <span>Michael S. Giagales</span>
                <span class="foot-attribution__sep" aria-hidden="true">&middot;</span>
                <span>Omarkhan G. Sahisa</span>
                <span class="foot-attribution__sep" aria-hidden="true">&middot;</span>
                <span>Jeriko A. Binong</span>
                <span class="foot-attribution__sep" aria-hidden="true">&middot;</span>
                <span>Abumharwan Sabbaha</span>
            </span>
        </p>
    </div>
</footer>
<?php endif; ?>

<script src="<?= e(url('/assets/js/site.js')) ?>" defer></script>
</body>
</html>