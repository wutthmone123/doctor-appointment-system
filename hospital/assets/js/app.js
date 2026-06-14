document.addEventListener("DOMContentLoaded", function () {
    const themeStorageKey = "hms_theme";
    const root = document.documentElement;
    const themeToggleButtons = document.querySelectorAll("[data-theme-toggle]");

    function getSavedTheme() {
        try {
            return localStorage.getItem(themeStorageKey);
        } catch (error) {
            return null;
        }
    }

    function saveTheme(theme) {
        try {
            localStorage.setItem(themeStorageKey, theme);
        } catch (error) {
            // Ignore storage errors and keep in-memory behavior.
        }
    }

    function updateThemeButton(theme) {
        const nextTheme = theme === "dark" ? "light" : "dark";
        themeToggleButtons.forEach(function (button) {
            const icon = button.querySelector("[data-theme-icon]");
            const label = button.querySelector("[data-theme-label]");
            if (icon) {
                icon.className = nextTheme === "dark" ? "bi bi-moon-stars-fill" : "bi bi-sun-fill";
            }
            if (label) {
                label.textContent = nextTheme === "dark" ? "Dark" : "Light";
            }
            button.setAttribute("title", "Switch to " + nextTheme + " mode");
        });
    }

    function applyTheme(theme) {
        root.setAttribute("data-theme", theme);
        saveTheme(theme);
        updateThemeButton(theme);
    }

    const savedTheme = getSavedTheme();
    const initialTheme =
        savedTheme === "dark" || savedTheme === "light"
            ? savedTheme
            : (window.matchMedia && window.matchMedia("(prefers-color-scheme: dark)").matches ? "dark" : "light");

    applyTheme(initialTheme);

    themeToggleButtons.forEach(function (button) {
        button.addEventListener("click", function () {
            const currentTheme = root.getAttribute("data-theme") === "dark" ? "dark" : "light";
            applyTheme(currentTheme === "dark" ? "light" : "dark");
        });
    });

    function setupPasswordToggles() {
        const passwordInputs = document.querySelectorAll('input[type="password"]:not([data-password-ready="1"])');
        passwordInputs.forEach(function (input) {
            input.setAttribute("data-password-ready", "1");

            const wrapper = document.createElement("div");
            wrapper.className = "password-toggle-wrap";
            input.parentNode.insertBefore(wrapper, input);
            wrapper.appendChild(input);

            const button = document.createElement("button");
            button.type = "button";
            button.className = "password-toggle-btn";
            button.setAttribute("aria-label", "Show password");
            button.innerHTML = '<i class="bi bi-eye"></i>';

            button.addEventListener("click", function () {
                const showing = input.type === "text";
                input.type = showing ? "password" : "text";
                button.innerHTML = showing ? '<i class="bi bi-eye"></i>' : '<i class="bi bi-eye-slash"></i>';
                button.setAttribute("aria-label", showing ? "Show password" : "Hide password");
            });

            wrapper.appendChild(button);
        });
    }

    setupPasswordToggles();

    const editDoctorModal = document.getElementById("editDoctorModal");
    if (editDoctorModal) {
        editDoctorModal.addEventListener("show.bs.modal", function (event) {
            const button = event.relatedTarget;
            if (!button) return;
            this.querySelector('input[name="doctor_id"]').value = button.getAttribute("data-id") || "";
            this.querySelector('input[name="name"]').value = button.getAttribute("data-name") || "";
            this.querySelector('input[name="email"]').value = button.getAttribute("data-email") || "";
            this.querySelector('input[name="phone"]').value = button.getAttribute("data-phone") || "";
            this.querySelector('textarea[name="address"]').value = button.getAttribute("data-address") || "";
            this.querySelector('input[name="specialty"]').value = button.getAttribute("data-specialty") || "";
            this.querySelector('input[name="schedule_min_time"]').value = button.getAttribute("data-schedule-min") || "";
            this.querySelector('input[name="schedule_max_time"]').value = button.getAttribute("data-schedule-max") || "";
            this.querySelector('input[name="min_patients_per_day"]').value = button.getAttribute("data-min-patients") || "1";
            this.querySelector('input[name="max_patients_per_day"]').value = button.getAttribute("data-max-patients") || "30";
        });
    }

    const editPatientModal = document.getElementById("editPatientModal");
    if (editPatientModal) {
        editPatientModal.addEventListener("show.bs.modal", function (event) {
            const button = event.relatedTarget;
            if (!button) return;
            this.querySelector('input[name="patient_id"]').value = button.getAttribute("data-id") || "";
            this.querySelector('input[name="name"]').value = button.getAttribute("data-name") || "";
            this.querySelector('input[name="email"]').value = button.getAttribute("data-email") || "";
            this.querySelector('input[name="phone"]').value = button.getAttribute("data-phone") || "";
        });
    }

    const prescriptionModal = document.getElementById("prescriptionModal");
    if (prescriptionModal) {
        prescriptionModal.addEventListener("show.bs.modal", function (event) {
            const button = event.relatedTarget;
            if (!button) return;
            this.querySelector('input[name="appointment_id"]').value = button.getAttribute("data-id") || "";
            this.querySelector('textarea[name="description"]').value = button.getAttribute("data-description") || "";
        });
    }
});
