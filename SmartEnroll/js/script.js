const slides = document.querySelectorAll(".slide");
let currentSlide = 0;

function showNextSlide() {
    if (!slides.length) return;
    slides[currentSlide].classList.remove("active");
    currentSlide = (currentSlide + 1) % slides.length;
    slides[currentSlide].classList.add("active");
}

if (slides.length) {
    setInterval(showNextSlide, 5000);
}

if (window.flatpickr && document.querySelector("#dob")) {
    const dobPicker = flatpickr("#dob", {
        dateFormat: "m/d/Y",
        maxDate: "today",

        /* UX */
        allowInput: true,              // MANUAL INPUT ENABLED
        clickOpens: true,
        monthSelectorType: "dropdown",
        yearSelectorType: "dropdown",
        defaultDate: "2010-01-01",

        onChange: function(selectedDates) {
            if (selectedDates.length) {
                calculateAge(selectedDates[0]);
            }
        }
    });

    const calendarIcon = document.querySelector(".calendar-icon");
    if (calendarIcon) {
        calendarIcon.addEventListener("click", function () {
            dobPicker.open();
        });
    }
}

/* AUTO AGE CALCULATION */
function calculateAge(dob) {
    const today = new Date();
    let age = today.getFullYear() - dob.getFullYear();
    const m = today.getMonth() - dob.getMonth();

    if (m < 0 || (m === 0 && today.getDate() < dob.getDate())) {
        age--;
    }

const ageInput = document.getElementById("age");
if (ageInput) {
    ageInput.value = age;
}
}

document.addEventListener("DOMContentLoaded", () => {
    const passwordFields = document.querySelectorAll(".password-field");

    passwordFields.forEach((field) => {
        const passwordInput = field.querySelector("input");
        const passwordToggle = field.querySelector(".password-icon");

        if (!passwordInput || !passwordToggle) {
            return;
        }

        passwordToggle.addEventListener("click", () => {
            const isHidden = passwordInput.type === "password";
            passwordInput.type = isHidden ? "text" : "password";
            const icon = passwordToggle.querySelector("i");
            if (icon) {
                icon.className = isHidden ? "fa-solid fa-eye-slash" : "fa-solid fa-eye";
            }
        });
    });
});

(function () {
    const LOADER_STYLE_ID = "smartenroll-page-loader-style";
    const LOADER_ID = "smartenroll-page-loader";
    const MIN_VISIBLE_MS = 260;
    const DEFAULT_TITLE = "Loading SMARTENROLL";
    const DEFAULT_MESSAGE = "Preparing your page. Please wait a moment.";
    const NAVIGATION_MESSAGE = "Opening the next page for you.";
    const SUBMIT_MESSAGE = "Processing your request.";

    let loaderElement = null;
    let loaderTitleElement = null;
    let loaderMessageElement = null;
    let visibleSince = 0;
    let hideTimerId = 0;

    function ensureLoaderStyles() {
        if (document.getElementById(LOADER_STYLE_ID)) {
            return;
        }

        const style = document.createElement("style");
        style.id = LOADER_STYLE_ID;
        style.textContent = `
            body.smartenroll-page-loader-active {
                overflow: hidden;
            }

            .smartenroll-page-loader {
                position: fixed;
                inset: 0;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 24px;
                background:
                    radial-gradient(circle at top, rgba(255, 214, 10, 0.20), transparent 36%),
                    rgba(15, 23, 42, 0.38);
                backdrop-filter: blur(10px);
                -webkit-backdrop-filter: blur(10px);
                opacity: 0;
                visibility: hidden;
                pointer-events: none;
                transition: opacity 0.22s ease, visibility 0.22s ease;
                z-index: 9999;
            }

            .smartenroll-page-loader.is-visible {
                opacity: 1;
                visibility: visible;
                pointer-events: auto;
            }

            .smartenroll-page-loader__box {
                width: min(360px, 100%);
                padding: 28px 24px 24px;
                border-radius: 24px;
                border: 1px solid rgba(255, 255, 255, 0.8);
                background:
                    linear-gradient(180deg, rgba(255, 255, 255, 0.98), rgba(244, 247, 251, 0.96));
                box-shadow: 0 24px 64px rgba(15, 23, 42, 0.22);
                text-align: center;
                color: #19325a;
                font-family: "Poppins", Arial, sans-serif;
                transform: translateY(18px) scale(0.97);
                transition: transform 0.24s ease;
            }

            .smartenroll-page-loader.is-visible .smartenroll-page-loader__box {
                transform: translateY(0) scale(1);
            }

            .smartenroll-page-loader__mark {
                position: relative;
                width: 88px;
                height: 88px;
                margin: 0 auto 18px;
                display: grid;
                place-items: center;
            }

            .smartenroll-page-loader__ring,
            .smartenroll-page-loader__ring::before,
            .smartenroll-page-loader__ring::after {
                position: absolute;
                inset: 0;
                border-radius: 50%;
                content: "";
            }

            .smartenroll-page-loader__ring {
                border: 3px solid rgba(25, 50, 90, 0.12);
                border-top-color: #1e88e5;
                animation: smartenroll-loader-spin 1.1s linear infinite;
            }

            .smartenroll-page-loader__ring::before {
                inset: 8px;
                border: 3px solid rgba(255, 214, 10, 0.22);
                border-bottom-color: #f5b700;
                animation: smartenroll-loader-spin-reverse 1.5s linear infinite;
            }

            .smartenroll-page-loader__ring::after {
                inset: 16px;
                border-radius: 24px;
                background: linear-gradient(145deg, #ffffff, #eef5ff);
                box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.8);
            }

            .smartenroll-page-loader__logo {
                position: relative;
                z-index: 1;
                width: 42px;
                height: 42px;
                object-fit: contain;
                animation: smartenroll-loader-pulse 1.6s ease-in-out infinite;
            }

            .smartenroll-page-loader__title {
                margin: 0;
                font-size: 22px;
                line-height: 1.2;
                color: #19325a;
            }

            .smartenroll-page-loader__message {
                margin: 10px 0 0;
                font-size: 14px;
                line-height: 1.6;
                color: #52627a;
            }

            .smartenroll-page-loader__dots {
                display: inline-flex;
                align-items: center;
                gap: 6px;
                margin-top: 16px;
            }

            .smartenroll-page-loader__dots span {
                width: 8px;
                height: 8px;
                border-radius: 999px;
                background: #1e88e5;
                opacity: 0.28;
                animation: smartenroll-loader-bounce 1s ease-in-out infinite;
            }

            .smartenroll-page-loader__dots span:nth-child(2) {
                animation-delay: 0.12s;
            }

            .smartenroll-page-loader__dots span:nth-child(3) {
                animation-delay: 0.24s;
            }

            @keyframes smartenroll-loader-spin {
                to {
                    transform: rotate(360deg);
                }
            }

            @keyframes smartenroll-loader-spin-reverse {
                to {
                    transform: rotate(-360deg);
                }
            }

            @keyframes smartenroll-loader-pulse {
                0%,
                100% {
                    transform: scale(1);
                }

                50% {
                    transform: scale(1.06);
                }
            }

            @keyframes smartenroll-loader-bounce {
                0%,
                80%,
                100% {
                    transform: translateY(0);
                    opacity: 0.28;
                }

                40% {
                    transform: translateY(-4px);
                    opacity: 1;
                }
            }

            @media (max-width: 640px) {
                .smartenroll-page-loader {
                    padding: 18px;
                }

                .smartenroll-page-loader__box {
                    padding: 24px 20px 20px;
                    border-radius: 22px;
                }

                .smartenroll-page-loader__title {
                    font-size: 20px;
                }
            }
        `;

        document.head.appendChild(style);
    }

    function ensureLoader() {
        if (!document.body) {
            return null;
        }

        if (loaderElement && document.body.contains(loaderElement)) {
            return loaderElement;
        }

        ensureLoaderStyles();

        loaderElement = document.createElement("div");
        loaderElement.id = LOADER_ID;
        loaderElement.className = "smartenroll-page-loader";
        loaderElement.setAttribute("aria-hidden", "true");
        loaderElement.innerHTML = `
            <div class="smartenroll-page-loader__box" role="status" aria-live="polite" aria-atomic="true">
                <div class="smartenroll-page-loader__mark" aria-hidden="true">
                    <span class="smartenroll-page-loader__ring"></span>
                    <img class="smartenroll-page-loader__logo" src="assets/logo.png" alt="">
                </div>
                <h3 class="smartenroll-page-loader__title">${DEFAULT_TITLE}</h3>
                <p class="smartenroll-page-loader__message">${DEFAULT_MESSAGE}</p>
                <div class="smartenroll-page-loader__dots" aria-hidden="true">
                    <span></span>
                    <span></span>
                    <span></span>
                </div>
            </div>
        `;

        document.body.appendChild(loaderElement);
        loaderTitleElement = loaderElement.querySelector(".smartenroll-page-loader__title");
        loaderMessageElement = loaderElement.querySelector(".smartenroll-page-loader__message");

        return loaderElement;
    }

    function setLoaderText(title, message) {
        if (loaderTitleElement) {
            loaderTitleElement.textContent = title || DEFAULT_TITLE;
        }

        if (loaderMessageElement) {
            loaderMessageElement.textContent = message || DEFAULT_MESSAGE;
        }
    }

    function showPageLoader(title = DEFAULT_TITLE, message = DEFAULT_MESSAGE) {
        const loader = ensureLoader();
        if (!loader) {
            return;
        }

        if (hideTimerId) {
            window.clearTimeout(hideTimerId);
            hideTimerId = 0;
        }

        setLoaderText(title, message);
        visibleSince = Date.now();
        loader.setAttribute("aria-hidden", "false");
        document.body.classList.add("smartenroll-page-loader-active");

        window.requestAnimationFrame(() => {
            loader.classList.add("is-visible");
        });
    }

    function hidePageLoader(force = false) {
        const loader = ensureLoader();
        if (!loader) {
            return;
        }

        const performHide = () => {
            loader.classList.remove("is-visible");
            loader.setAttribute("aria-hidden", "true");
            document.body.classList.remove("smartenroll-page-loader-active");
            hideTimerId = 0;
        };

        if (force) {
            if (hideTimerId) {
                window.clearTimeout(hideTimerId);
                hideTimerId = 0;
            }

            performHide();
            return;
        }

        const elapsed = Date.now() - visibleSince;
        const remaining = Math.max(0, MIN_VISIBLE_MS - elapsed);

        if (remaining > 0) {
            if (hideTimerId) {
                window.clearTimeout(hideTimerId);
            }

            hideTimerId = window.setTimeout(performHide, remaining);
            return;
        }

        performHide();
    }

    function shouldIgnoreLink(anchor) {
        if (!anchor) {
            return true;
        }

        if (anchor.dataset.skipPageLoader !== undefined || anchor.hasAttribute("download")) {
            return true;
        }

        const href = (anchor.getAttribute("href") || "").trim();
        if (href === "" || href === "#" || href.startsWith("javascript:")) {
            return true;
        }

        if (href.startsWith("mailto:") || href.startsWith("tel:")) {
            return true;
        }

        const target = (anchor.getAttribute("target") || "").trim().toLowerCase();
        if (target !== "" && target !== "_self") {
            return true;
        }

        try {
            const targetUrl = new URL(anchor.href, window.location.href);
            const currentUrl = new URL(window.location.href);
            const isSameDocument =
                targetUrl.origin === currentUrl.origin &&
                targetUrl.pathname === currentUrl.pathname &&
                targetUrl.search === currentUrl.search &&
                targetUrl.hash !== "";

            return isSameDocument;
        } catch (error) {
            return false;
        }
    }

    function bindNavigationLoader() {
        document.addEventListener("click", (event) => {
            if (
                event.defaultPrevented ||
                event.button !== 0 ||
                event.metaKey ||
                event.ctrlKey ||
                event.shiftKey ||
                event.altKey
            ) {
                return;
            }

            const target = event.target instanceof Element ? event.target.closest("a[href]") : null;
            if (!target || shouldIgnoreLink(target)) {
                return;
            }

            window.setTimeout(() => {
                if (!event.defaultPrevented) {
                    showPageLoader(DEFAULT_TITLE, NAVIGATION_MESSAGE);
                }
            }, 0);
        });

        document.addEventListener("submit", (event) => {
            const form = event.target instanceof HTMLFormElement ? event.target : null;
            if (!form || form.dataset.skipPageLoader !== undefined) {
                return;
            }

            const target = (form.getAttribute("target") || "").trim().toLowerCase();
            if (target !== "" && target !== "_self") {
                return;
            }

            window.setTimeout(() => {
                if (!event.defaultPrevented) {
                    showPageLoader(DEFAULT_TITLE, SUBMIT_MESSAGE);
                }
            }, 0);
        });

        window.addEventListener("beforeunload", () => {
            showPageLoader(DEFAULT_TITLE, NAVIGATION_MESSAGE);
        });

        window.addEventListener("pageshow", (event) => {
            if (event.persisted) {
                hidePageLoader(true);
            }
        });
    }

    function isPrintModePage() {
        if (document.body && document.body.dataset.printMode === "1") {
            return true;
        }

        const searchParams = new URLSearchParams(window.location.search);
        return searchParams.get("print") === "1";
    }

    function initializePageLoader() {
        if (!document.body || document.body.dataset.pageLoaderInitialized === "1" || isPrintModePage()) {
            return;
        }

        document.body.dataset.pageLoaderInitialized = "1";
        showPageLoader(DEFAULT_TITLE, DEFAULT_MESSAGE);
        bindNavigationLoader();

        if (document.readyState === "complete") {
            window.setTimeout(() => {
                hidePageLoader();
            }, 80);
        } else {
            window.addEventListener("load", () => {
                hidePageLoader();
            }, { once: true });
        }

        window.smartenrollPageLoader = {
            show: showPageLoader,
            hide: hidePageLoader
        };
    }

    if (document.body) {
        initializePageLoader();
    } else {
        document.addEventListener("DOMContentLoaded", initializePageLoader, { once: true });
    }
}());
