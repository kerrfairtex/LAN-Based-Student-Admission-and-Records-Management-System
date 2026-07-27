(function () {
    const toggle = document.querySelector("[data-password-toggle]");
    const password = document.querySelector("#password");

    if (toggle && password) {
        toggle.addEventListener("click", function () {
            const isHidden = password.type === "password";
            password.type = isHidden ? "text" : "password";
            toggle.setAttribute("aria-label", isHidden ? "Hide password" : "Show password");
            toggle.setAttribute("aria-pressed", isHidden ? "true" : "false");
            toggle.innerHTML = isHidden
                ? '<i class="bi bi-eye-slash" aria-hidden="true"></i>'
                : '<i class="bi bi-eye" aria-hidden="true"></i>';
        });
    }

    const form = document.querySelector(".login-panel form");
    if (form) {
        form.addEventListener("submit", function () {
            const button = form.querySelector(".btn-signin");
            if (button && !button.disabled) {
                button.disabled = true;
                button.textContent = "Signing in…";
            }
        });
    }
})();
