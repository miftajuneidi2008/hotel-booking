import Alpine from "alpinejs";

window.Alpine = Alpine;

Alpine.start();

document.addEventListener("DOMContentLoaded", () => {
    // Navbar: shadow on scroll + mobile menu
    const nav = document.querySelector("[data-navbar]");
    if (nav) {
        const onScroll = () =>
            (nav.dataset.scrolled = String(window.scrollY > 8));
        onScroll();
        addEventListener("scroll", onScroll, { passive: true });

        const toggle = nav.querySelector("[data-navbar-toggle]");
        const panel = nav.querySelector("[data-navbar-panel]");
        toggle?.addEventListener("click", () => {
            const open = toggle.getAttribute("aria-expanded") !== "true";
            toggle.setAttribute("aria-expanded", String(open));
            panel.dataset.open = String(open);
            panel.inert = !open;
        });
    }

    // Scroll reveal
    const items = document.querySelectorAll(".reveal");
    if ("IntersectionObserver" in window) {
        const io = new IntersectionObserver(
            (entries) => {
                entries.forEach((e) => {
                    if (e.isIntersecting) {
                        e.target.classList.add("is-visible");
                        io.unobserve(e.target);
                    }
                });
            },
            { threshold: 0.12 },
        );
        items.forEach((el) => io.observe(el));
    } else {
        items.forEach((el) => el.classList.add("is-visible"));
    }
});
