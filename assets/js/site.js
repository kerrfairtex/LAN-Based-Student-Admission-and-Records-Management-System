/* TRAC JHS shared site behavior.
   Loaded by every page via the public-site header partial.
   Progressive enhancement: the page must be readable + navigable
   with JS disabled; this file only adds quality-of-life. */
(function () {
    'use strict';

    /* ----- Mobile menu toggle ----- */
    var btn = document.querySelector('.menu-btn');
    var nav = document.getElementById('mobile-nav');
    if (btn && nav) {
        btn.addEventListener('click', function () {
            var open = btn.getAttribute('aria-expanded') === 'true';
            btn.setAttribute('aria-expanded', open ? 'false' : 'true');
            if (open) {
                nav.setAttribute('hidden', '');
            } else {
                nav.removeAttribute('hidden');
            }
        });

        // Close the menu when a link inside it is activated
        nav.querySelectorAll('a').forEach(function (a) {
            a.addEventListener('click', function () {
                btn.setAttribute('aria-expanded', 'false');
                nav.setAttribute('hidden', '');
            });
        });
    }

    /* ----- Timeline scroll reveal ----- */
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
        // No IO support → show everything immediately
        steps.forEach(function (s) { s.classList.add('in'); });
    }

    /* ----- Inquiry form: lock submit button while in flight ----- */
    var forms = document.querySelectorAll('[data-inquiry-form]');
    forms.forEach(function (form) {
        form.addEventListener('submit', function () {
            var btn = form.querySelector('button[type="submit"]');
            if (btn && !btn.disabled) {
                btn.disabled = true;
                var original = btn.textContent;
                btn.textContent = 'Sending…';
                // Re-enable after a short delay so retry is possible if server stalls
                setTimeout(function () {
                    btn.disabled = false;
                    btn.textContent = original;
                }, 4000);
            }
        });
    });

    /* ----- Existing login-panel show-password toggle (legacy compat) ----- */
    var toggle = document.querySelector('[data-password-toggle]');
    var pwd = document.getElementById('password');
    if (toggle && pwd) {
        toggle.addEventListener('click', function () {
            var hidden = pwd.type === 'password';
            pwd.type = hidden ? 'text' : 'password';
            toggle.setAttribute('aria-label', hidden ? 'Hide password' : 'Show password');
            toggle.setAttribute('aria-pressed', hidden ? 'true' : 'false');
            toggle.innerHTML = hidden
                ? '<i class="bi bi-eye-slash" aria-hidden="true"></i>'
                : '<i class="bi bi-eye" aria-hidden="true"></i>';
        });
    }

    var loginForm = document.querySelector('.login-panel form');
    if (loginForm) {
        loginForm.addEventListener('submit', function () {
            var btn = loginForm.querySelector('.btn-signin');
            if (btn && !btn.disabled) {
                btn.disabled = true;
                btn.textContent = 'Signing in…';
            }
        });
    }
})();