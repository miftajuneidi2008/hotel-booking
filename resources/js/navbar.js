// Behaviour for <x-navbar />. No dependencies.
const DESKTOP = window.matchMedia("(min-width: 48rem)"); // Tailwind "md"

export function initNavbar() {
    document.querySelectorAll("[data-navbar]").forEach(setup);
}

function setup(root) {
    const toggle = root.querySelector("[data-navbar-toggle]");
    const panel = root.querySelector("[data-navbar-panel]");
    if (!toggle || !panel) return;

    const isOpen = () => toggle.getAttribute("aria-expanded") === "true";

    const setOpen = (open, { restoreFocus = false } = {}) => {
        toggle.setAttribute("aria-expanded", String(open));
        panel.dataset.open = String(open);
        panel.inert = !open; // closed menu links cannot be reached with Tab
        if (!open && restoreFocus) toggle.focus();
    };

    toggle.addEventListener("click", () => setOpen(!isOpen()));

    // Close after choosing a link
    panel.addEventListener("click", (e) => {
        if (e.target.closest("a")) setOpen(false);
    });

    // Escape closes and returns focus to the button
    root.addEventListener("keydown", (e) => {
        if (e.key === "Escape" && isOpen())
            setOpen(false, { restoreFocus: true });
    });

    // Click outside closes
    document.addEventListener("click", (e) => {
        if (isOpen() && !root.contains(e.target)) setOpen(false);
    });

    // Reset when the screen grows to desktop size
    DESKTOP.addEventListener("change", (e) => {
        if (e.matches) setOpen(false);
    });

    // Soft shadow after the page starts scrolling
    const onScroll = () => {
        root.dataset.scrolled = String(window.scrollY > 8);
    };
    onScroll();
    window.addEventListener("scroll", onScroll, { passive: true });
}
