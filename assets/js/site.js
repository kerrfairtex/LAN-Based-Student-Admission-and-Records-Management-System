/* TRAC JHS shared site behavior.
   Loaded by every page via the public-site header partial.
   Progressive enhancement: the page must be readable + navigable
   with JS disabled; this file only adds quality-of-life. */
(function () {
    'use strict';

    /* =====================================================================
     * 1. Mobile off-canvas menu: hamburger → X, slide-in panel, focus trap
     * ===================================================================== */
    var btn = document.querySelector('.menu-btn');
    var nav = document.getElementById('mobile-nav');
    var backdrop = document.querySelector('.mobile-nav-backdrop');

    if (btn && nav) {
        var lastFocus = null;

        function focusableInside(el) {
            var sel = 'a[href], button:not([disabled]), [tabindex]:not([tabindex="-1"]), input:not([disabled]), select:not([disabled])';
            return Array.prototype.slice.call(el.querySelectorAll(sel));
        }

        function openMenu() {
            lastFocus = document.activeElement;
            nav.removeAttribute('hidden');
            // Force reflow so the transition runs from translateX(100%) → 0
            // eslint-disable-next-line no-unused-expressions
            nav.offsetWidth;
            nav.classList.add('is-open');
            if (backdrop) backdrop.classList.add('is-open');
            btn.setAttribute('aria-expanded', 'true');
            btn.setAttribute('aria-label', 'Close menu');
            document.documentElement.style.overflow = 'hidden';

            var first = focusableInside(nav)[0];
            if (first) first.focus();
        }

        function closeMenu() {
            nav.classList.remove('is-open');
            if (backdrop) backdrop.classList.remove('is-open');
            btn.setAttribute('aria-expanded', 'false');
            btn.setAttribute('aria-label', 'Open menu');
            document.documentElement.style.overflow = '';
            // Wait for transition to finish before hiding from a11y tree
            setTimeout(function () {
                if (!nav.classList.contains('is-open')) nav.setAttribute('hidden', '');
            }, 320);
            if (lastFocus && typeof lastFocus.focus === 'function') lastFocus.focus();
        }

        function isOpen() {
            return btn.getAttribute('aria-expanded') === 'true';
        }

        btn.addEventListener('click', function () {
            if (isOpen()) closeMenu(); else openMenu();
        });

        // Backdrop click closes
        if (backdrop) {
            backdrop.addEventListener('click', closeMenu);
        }

        // Any element with data-mobile-nav-close closes
        document.querySelectorAll('[data-mobile-nav-close]').forEach(function (el) {
            el.addEventListener('click', function (e) {
                if (el === backdrop) return; // already bound above
                if (isOpen()) { e.preventDefault(); closeMenu(); }
            });
        });

        // Close when a link inside the panel is activated
        nav.querySelectorAll('a').forEach(function (a) {
            a.addEventListener('click', function () {
                if (isOpen()) closeMenu();
            });
        });

        // Esc closes
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && isOpen()) {
                e.preventDefault();
                closeMenu();
            }
        });

        // Focus trap: Tab cycles within the panel while open
        nav.addEventListener('keydown', function (e) {
            if (e.key !== 'Tab' || !isOpen()) return;
            var items = focusableInside(nav);
            if (!items.length) return;
            var first = items[0];
            var last = items[items.length - 1];
            if (e.shiftKey && document.activeElement === first) {
                e.preventDefault();
                last.focus();
            } else if (!e.shiftKey && document.activeElement === last) {
                e.preventDefault();
                first.focus();
            }
        });
    }

    /* =====================================================================
     * 2. Inquiry form: spinner overlay + disabled button while in flight
     * ===================================================================== */
    var forms = document.querySelectorAll('[data-inquiry-form]');
    forms.forEach(function (form) {
        form.addEventListener('submit', function (e) {
            // HTML5 validation: prevent submit if required fields empty
            if (!form.checkValidity()) {
                e.preventDefault();
                form.reportValidity();
                return;
            }

            var submit = form.querySelector('button[type="submit"]');
            if (submit && !submit.disabled) {
                submit.disabled = true;
                form.setAttribute('data-state', 'sending');
                // Safety net: re-enable after 8s in case the server stalls
                setTimeout(function () {
                    if (form.getAttribute('data-state') === 'sending') {
                        submit.disabled = false;
                        form.removeAttribute('data-state');
                    }
                }, 8000);
            }
        });
    });

    /* =====================================================================
     * 2b. Login form: same lock + label swap (no spinner overlay; just disable)
     * ===================================================================== */
    var loginForms = document.querySelectorAll('[data-login-form]');
    loginForms.forEach(function (form) {
        form.addEventListener('submit', function () {
            var submit = form.querySelector('button[type="submit"]');
            if (submit && !submit.disabled) {
                submit.disabled = true;
                form.setAttribute('data-state', 'sending');
                // Safety net: re-enable after 8s
                setTimeout(function () {
                    if (form.getAttribute('data-state') === 'sending') {
                        submit.disabled = false;
                        form.removeAttribute('data-state');
                    }
                }, 8000);
            }
        });
    });

    /* =====================================================================
     * 3. Timeline scroll reveal (admissions section)
     * ===================================================================== */
    var steps = document.querySelectorAll('.step');
    if (steps.length && 'IntersectionObserver' in window) {
        var io = new IntersectionObserver(function (entries) {
            entries.forEach(function (e) {
                if (e.isIntersecting) {
                    e.target.classList.add('in');
                    io.unobserve(e.target);
                }
            });
        }, { threshold: 0.3 });
        steps.forEach(function (s) { io.observe(s); });
    } else {
        steps.forEach(function (s) { s.classList.add('in'); });
    }

    /* =====================================================================
     * 4. Staggered hero reveal on first paint
     * ===================================================================== */
    var heroTargets = document.querySelectorAll(
        '.hero .eyebrow-row, .hero h1, .hero p.lede, .hero-ctas, .hero-meta, .emblem-wrap'
    );
    if (heroTargets.length) {
        // If reduced motion is requested, reveal immediately with no animation
        var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        if (reduce) {
            heroTargets.forEach(function (el) { el.classList.add('is-in'); });
        } else if ('IntersectionObserver' in window) {
            var heroIO = new IntersectionObserver(function (entries) {
                entries.forEach(function (e) {
                    if (e.isIntersecting) {
                        e.target.classList.add('is-in');
                        heroIO.unobserve(e.target);
                    }
                });
            }, { threshold: 0.1 });
            heroTargets.forEach(function (el) { heroIO.observe(el); });
            // Safety: if IO never fires (e.g. layout 0-height, very old WebView),
            // reveal whatever is still hidden after 1.2s. This is the only path
            // that can re-trigger is-in redundantly, but it's idempotent.
            setTimeout(function () {
                heroTargets.forEach(function (el) {
                    if (!el.classList.contains('is-in')) el.classList.add('is-in');
                });
            }, 1200);
        } else {
            heroTargets.forEach(function (el) { el.classList.add('is-in'); });
        }
    }

    /* =====================================================================
     * 5. FAQ: open first item by default for visual richness (progressive)
     * ===================================================================== */
    var faqItems = document.querySelectorAll('.faq-item details');
    if (faqItems.length) {
        // Add a class hook so CSS can animate chevron without JS
        faqItems.forEach(function (d) {
            d.addEventListener('toggle', function () {
                if (d.open) {
                    d.closest('.faq-item').setAttribute('data-open', '');
                } else {
                    d.closest('.faq-item').removeAttribute('data-open');
                }
            });
        });
    }

    /* =====================================================================
     * 6. Login panel: show-password toggle + sign-in button lock
     * ===================================================================== */
    var toggle = document.querySelector('[data-password-toggle]');
    var pwd = document.getElementById('password');
    if (toggle && pwd) {
        toggle.addEventListener('click', function () {
            var isHidden = pwd.type === 'password';
            pwd.type = isHidden ? 'text' : 'password';
            toggle.setAttribute('aria-label', isHidden ? 'Hide password' : 'Show password');
            toggle.setAttribute('aria-pressed', isHidden ? 'true' : 'false');
            // For the new card-based markup, the .eye--closed / .eye--open SVGs
            // are siblings in the DOM; CSS toggles visibility via aria-pressed.
            // For legacy markup that uses a single <i> with bi-* classes, fall
            // back to swapping the icon HTML.
            if (!toggle.querySelector('.eye')) {
                toggle.innerHTML = isHidden
                    ? '<i class="bi bi-eye-slash" aria-hidden="true"></i>'
                    : '<i class="bi bi-eye" aria-hidden="true"></i>';
            }
        });
    }

    // Legacy (non-data-login-form) login form button lock
    var legacyLoginForm = document.querySelector('.login-panel form');
    if (legacyLoginForm && !legacyLoginForm.hasAttribute('data-login-form')) {
        legacyLoginForm.addEventListener('submit', function () {
            var btn = legacyLoginForm.querySelector('.btn-signin');
            if (btn && !btn.disabled) {
                btn.disabled = true;
                btn.textContent = 'Signing in…';
            }
        });
    }

    /* =====================================================================
     * 6. Login card: staggered entrance + click ripple
     * ===================================================================== */
    var loginCard = document.querySelector('.login-card');
    if (loginCard) {
        // Reveal on next frame so the entrance animation runs.
        requestAnimationFrame(function () {
            // Tiny beat so the user perceives the page first, then the card arrives.
            setTimeout(function () { loginCard.classList.add('is-in'); }, 80);
        });
    }

    var loginSubmit = document.querySelector('[data-login-form] .btn-signin');
    if (loginSubmit) {
        loginSubmit.addEventListener('click', function (e) {
            // Skip the ripple when the form is already in flight (sending state)
            if (loginSubmit.disabled) return;
            // Spawn a ripple at the click point
            var ripple = loginSubmit.querySelector('.btn-signin__ripple');
            if (!ripple) return;
            var rect = loginSubmit.getBoundingClientRect();
            var x = (e.clientX || rect.left + rect.width / 2) - rect.left;
            var y = (e.clientY || rect.top + rect.height / 2) - rect.top;
            ripple.style.left = x + 'px';
            ripple.style.top  = y + 'px';
            ripple.classList.remove('is-active');
            // Force reflow so the animation re-triggers
            // eslint-disable-next-line no-unused-expressions
            ripple.offsetWidth;
            ripple.classList.add('is-active');
        });
    }

    /* =====================================================================
     * 3. Top-of-page banner dismiss (post-logout, post-inquiry confirmation)
     * ===================================================================== */
    var banner = document.querySelector('[data-page-banner]');
    var bannerClose = document.querySelector('[data-page-banner-close]');
    if (banner && bannerClose) {
        bannerClose.addEventListener('click', function () {
            banner.style.transition = 'opacity .25s ease, max-height .25s ease, padding .25s ease, margin .25s ease';
            banner.style.maxHeight = banner.offsetHeight + 'px';
            // Force reflow so the transition runs from set values
            // eslint-disable-next-line no-unused-expressions
            banner.offsetWidth;
            banner.style.opacity = '0';
            banner.style.maxHeight = '0';
            banner.style.paddingTop = '0';
            banner.style.paddingBottom = '0';
            banner.style.overflow = 'hidden';
            banner.addEventListener('transitionend', function () {
                banner.parentNode && banner.parentNode.removeChild(banner);
            }, { once: true });
        });
    }
})();
