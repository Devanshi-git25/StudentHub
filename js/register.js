// ===== Practical 5: Registration form validation =====

const form = document.getElementById("registerForm");

if (form) {

    // Regular expressions
    const patterns = {
        fullName: /^[A-Za-z]+(?: [A-Za-z]+)+$/,                 // at least first + last name, letters only
        email: /^[^\s@]+@[^\s@]+\.[A-Za-z]{2,}$/,
        mobile: /^[6-9]\d{9}$/,                                  // Indian 10-digit mobile
        password: /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9]).{8,}$/
    };

    // Helpers
    function showError(id, message) {
        document.getElementById(id + "-error").textContent = message;
        const field = document.getElementById(id);
        if (field) {
            field.classList.toggle("invalid", message !== "");
            field.setAttribute("aria-invalid", message !== "" ? "true" : "false");
        }
        return message === "";
    }

    // Individual validators (return true if valid)
    function validateName() {
        const v = document.getElementById("fullName").value.trim();
        if (v === "") return showError("fullName", "Full name is required.");
        if (!patterns.fullName.test(v)) return showError("fullName", "Enter first and last name (letters only).");
        return showError("fullName", "");
    }

    function validateEmail() {
        const v = document.getElementById("regEmail").value.trim();
        if (v === "") return showError("regEmail", "Email is required.");
        if (!patterns.email.test(v)) return showError("regEmail", "Enter a valid email, e.g. name@example.com.");
        return showError("regEmail", "");
    }

    function validateMobile() {
        const v = document.getElementById("mobile").value.trim();
        if (v === "") return showError("mobile", "Mobile number is required.");
        if (!patterns.mobile.test(v)) return showError("mobile", "Enter a valid 10-digit number starting with 6-9.");
        return showError("mobile", "");
    }

    function validatePassword() {
        const v = document.getElementById("regPassword").value;
        if (v === "") return showError("regPassword", "Password is required.");
        if (!patterns.password.test(v)) {
            return showError("regPassword", "Min 8 chars with uppercase, lowercase, number and symbol.");
        }
        return showError("regPassword", "");
    }

    function validateConfirm() {
        const p = document.getElementById("regPassword").value;
        const c = document.getElementById("confirmPassword").value;
        if (c === "") return showError("confirmPassword", "Please confirm your password.");
        if (p !== c) return showError("confirmPassword", "Passwords do not match.");
        return showError("confirmPassword", "");
    }

    function validateSelect(id, label) {
        const v = document.getElementById(id).value;
        if (v === "") return showError(id, "Please select your " + label + ".");
        return showError(id, "");
    }

    function validateGender() {
        const chosen = form.querySelector('input[name="gender"]:checked');
        document.getElementById("gender-error").textContent = chosen ? "" : "Please select a gender.";
        return !!chosen;
    }

    function validateTerms() {
        const ok = document.getElementById("terms").checked;
        document.getElementById("terms-error").textContent = ok ? "" : "You must accept the terms.";
        return ok;
    }

    // Password strength meter
    const strengthFill = document.getElementById("strengthFill");
    const strengthText = document.getElementById("strengthText");

    function checkStrength(pw) {
        let score = 0;
        if (pw.length >= 8) score++;
        if (/[a-z]/.test(pw) && /[A-Z]/.test(pw)) score++;
        if (/\d/.test(pw)) score++;
        if (/[^A-Za-z0-9]/.test(pw)) score++;

        const levels = [
            { text: "Too weak", color: "#d32f2f", width: "10%" },
            { text: "Weak", color: "#d32f2f", width: "25%" },
            { text: "Fair", color: "#f57c00", width: "50%" },
            { text: "Good", color: "#fbc02d", width: "75%" },
            { text: "Strong", color: "#388e3c", width: "100%" }
        ];
        const level = pw === "" ? { text: "Use 8+ characters with upper, lower, number and symbol.", color: "#ccc", width: "0%" } : levels[score];
        strengthFill.style.width = level.width;
        strengthFill.style.backgroundColor = level.color;
        strengthText.textContent = pw === "" ? level.text : "Strength: " + level.text;
    }

    // Real-time validation (keyup / change / blur)
    document.getElementById("fullName").addEventListener("keyup", validateName);
    document.getElementById("regEmail").addEventListener("keyup", validateEmail);
    document.getElementById("mobile").addEventListener("keyup", validateMobile);
    document.getElementById("regPassword").addEventListener("keyup", function () {
        checkStrength(this.value);
        validatePassword();
        if (document.getElementById("confirmPassword").value !== "") validateConfirm();
    });
    document.getElementById("confirmPassword").addEventListener("keyup", validateConfirm);
    document.getElementById("course").addEventListener("change", function () { validateSelect("course", "course"); });
    document.getElementById("year").addEventListener("change", function () { validateSelect("year", "year"); });
    form.querySelectorAll('input[name="gender"]').forEach(function (r) { r.addEventListener("change", validateGender); });
    document.getElementById("terms").addEventListener("change", validateTerms);

    // Only allow digits in mobile
    document.getElementById("mobile").addEventListener("input", function () {
        this.value = this.value.replace(/\D/g, "");
    });

    // Submit: block only when invalid; valid forms are posted to register.php (server validates again)
    form.addEventListener("submit", function (event) {
        const results = [
            validateName(),
            validateEmail(),
            validateMobile(),
            validatePassword(),
            validateConfirm(),
            validateSelect("course", "course"),
            validateSelect("year", "year"),
            validateGender(),
            validateTerms()
        ];

        if (!results.every(Boolean)) {
            event.preventDefault();
            const firstInvalid = form.querySelector(".invalid");
            if (firstInvalid) firstInvalid.focus();
        }
    });

    // Reset: clear fields (including values filled in by PHP), messages and meter
    form.addEventListener("reset", function (event) {
        event.preventDefault();
        form.querySelectorAll('input[type="text"], input[type="email"], input[type="tel"], input[type="password"]')
            .forEach(function (i) { i.value = ""; });
        form.querySelectorAll("select").forEach(function (s) { s.selectedIndex = 0; });
        form.querySelectorAll('input[type="radio"], input[type="checkbox"]')
            .forEach(function (i) { i.checked = false; });
        form.querySelectorAll(".error").forEach(function (e) { e.textContent = ""; });
        form.querySelectorAll(".invalid").forEach(function (e) { e.classList.remove("invalid"); });
        checkStrength("");
    });
}
