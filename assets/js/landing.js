(function () {
    const toggle = document.querySelector("[data-password-toggle]");
    const password = document.querySelector("#password");
    if (toggle && password) toggle.addEventListener("click", function () {
        const hidden = password.type === "password";
        password.type = hidden ? "text" : "password";
        toggle.setAttribute("aria-label", hidden ? "Hide password" : "Show password");
        toggle.setAttribute("aria-pressed", hidden ? "true" : "false");
        toggle.innerHTML = hidden ? '<i class="bi bi-eye-slash" aria-hidden="true"></i>' : '<i class="bi bi-eye" aria-hidden="true"></i>';
    });
    const form = document.querySelector(".hero__card form");
    if (form) form.addEventListener("submit", function () { const button = form.querySelector(".btn-signin"); if (button && !button.disabled) { button.disabled = true; button.textContent = "Signing in…"; } });
    const menuButton = document.querySelector("[data-menu-toggle]");
    const nav = document.querySelector("[data-mobile-nav]");
    if (menuButton && nav) menuButton.addEventListener("click", function () { const open = nav.classList.toggle("is-open"); menuButton.setAttribute("aria-expanded", open ? "true" : "false"); });
    document.querySelectorAll(".site-nav a").forEach((link) => link.addEventListener("click", () => { nav?.classList.remove("is-open"); menuButton?.setAttribute("aria-expanded", "false"); }));
    const reveal = document.querySelectorAll(".reveal");
    if (window.matchMedia("(prefers-reduced-motion: reduce)").matches || !("IntersectionObserver" in window)) reveal.forEach((item) => item.classList.add("is-visible"));
    else { const observer = new IntersectionObserver((entries, obs) => entries.forEach((entry) => { if (entry.isIntersecting) { entry.target.classList.add("is-visible"); obs.unobserve(entry.target); } }), { threshold: .14 }); reveal.forEach((item) => observer.observe(item)); }
    const tabs = document.querySelector("[data-tabs]");
    if (tabs) { const buttons = tabs.querySelectorAll("[role=tab]"); const panels = tabs.querySelectorAll("[role=tabpanel]"); buttons.forEach((button, index) => button.addEventListener("click", () => { buttons.forEach((item) => item.setAttribute("aria-selected", item === button ? "true" : "false")); panels.forEach((panel) => { panel.hidden = panel.id !== button.getAttribute("aria-controls"); }); })); buttons.forEach((button, index) => button.addEventListener("keydown", (event) => { if (event.key === "ArrowRight" || event.key === "ArrowLeft") { event.preventDefault(); buttons[(index + (event.key === "ArrowRight" ? 1 : buttons.length - 1)) % buttons.length].focus(); } })); }
})();
