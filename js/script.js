// ===== StudentHub main script (safe on every page) =====

// FAQ accordion (only if FAQ exists on page)
document.querySelectorAll(".faq-question").forEach(function (question) {
    question.addEventListener("click", function () {
        const answer = question.nextElementSibling;
        answer.style.display = (answer.style.display === "block") ? "none" : "block";
    });
});

// Modal
const modal = document.getElementById("modal");
const openModal = document.getElementById("openModal");
const closeModal = document.getElementById("closeModal");

if (modal && openModal && closeModal) {
    openModal.addEventListener("click", function () {
        modal.style.display = "flex";
    });
    closeModal.addEventListener("click", function () {
        modal.style.display = "none";
    });
}

// Slider
const slides = document.querySelectorAll(".slide");
const previousButton = document.getElementById("previous");
const nextButton = document.getElementById("next");
let currentSlide = 0;

if (slides.length > 0 && previousButton && nextButton) {
    slides[currentSlide].style.display = "block";

    nextButton.addEventListener("click", function () {
        slides[currentSlide].style.display = "none";
        currentSlide = (currentSlide + 1) % slides.length;
        slides[currentSlide].style.display = "block";
    });

    previousButton.addEventListener("click", function () {
        slides[currentSlide].style.display = "none";
        currentSlide = (currentSlide - 1 + slides.length) % slides.length;
        slides[currentSlide].style.display = "block";
    });
}

// Notification banner
const notification = document.getElementById("notification");
const closeNotification = document.getElementById("closeNotification");

if (notification && closeNotification) {
    closeNotification.addEventListener("click", function () {
        notification.style.display = "none";
    });
}

// Side menu
const menuButton = document.getElementById("menuButton");
const sideMenu = document.getElementById("sideMenu");
const closeMenu = document.getElementById("closeMenu");

if (menuButton && sideMenu && closeMenu) {
    menuButton.addEventListener("click", function () {
        sideMenu.classList.add("open");
    });
    closeMenu.addEventListener("click", function () {
        sideMenu.classList.remove("open");
    });
}

// Theme switcher: floating moon / sun button in the top-right corner of every page.
// The choice is remembered in localStorage and applied on all pages.
(function () {
    document.querySelectorAll("#themeButton").forEach(function (old) { old.remove(); });   // remove any old in-header button

    const button = document.createElement("button");
    button.id = "themeButton";
    button.type = "button";
    button.className = "theme-toggle";

    function applyTheme(dark) {
        document.body.classList.toggle("dark-theme", dark);
        button.textContent = dark ? "\u2600\uFE0F" : "\uD83C\uDF19";          // sun when dark, moon when light
        button.setAttribute("aria-label", dark ? "Switch to light mode" : "Switch to dark mode");
        button.setAttribute("aria-pressed", dark ? "true" : "false");
        button.title = dark ? "Light mode" : "Dark mode";
    }

    let saved = null;
    try { saved = localStorage.getItem("theme"); } catch (e) { /* storage blocked */ }
    applyTheme(saved === "dark");

    button.addEventListener("click", function () {
        const dark = !document.body.classList.contains("dark-theme");
        applyTheme(dark);
        try { localStorage.setItem("theme", dark ? "dark" : "light"); } catch (e) { /* ignore */ }
    });

    document.body.appendChild(button);
})();

// Confirm before dangerous actions: <form data-confirm="Are you sure?">
document.querySelectorAll("form[data-confirm]").forEach(function (f) {
    f.addEventListener("submit", function (event) {
        if (!window.confirm(f.dataset.confirm)) {
            event.preventDefault();
        }
    });
});
