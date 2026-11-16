(() => {
  "use strict";

  function initHotelHome() {
    const body = document.body;

    if (!body.matches(".page-index:not(.staff-page)")) return;
    if (body.dataset.hotelHomeReady === "1") return;

    body.dataset.hotelHomeReady = "1";

    const reducedMotion = window.matchMedia("(prefers-reduced-motion: reduce)");

    const mobile = window.matchMedia("(max-width: 700px)");
    const tablet = window.matchMedia("(max-width: 1050px)");
    const animations = new Set();
    const observers = new Set();

    function stopAnimations(container) {
      for (const animation of Array.from(animations)) {
        const target = animation.effect?.target;

        if (!container || (target && container.contains(target))) {
          animation.cancel();
          animations.delete(animation);
        }
      }
    }

    function animateElement(element, direction = "up", delay = 0) {
      if (
        !element ||
        reducedMotion.matches ||
        typeof element.animate !== "function" ||
        element.contains(document.activeElement)
      ) {
        return;
      }

      let transform = "translateY(28px)";

      if (!mobile.matches && direction === "left") {
        transform = "translateX(-28px)";
      } else if (!mobile.matches && direction === "right") {
        transform = "translateX(28px)";
      }

      const animation = element.animate(
        [
          { opacity: 0, transform },
          { opacity: 1, transform: "translate(0, 0)" },
        ],
        {
          duration: 800,
          delay,
          easing: "cubic-bezier(0.22, 1, 0.36, 1)",
          fill: "backwards",
        },
      );

      animations.add(animation);

      const cleanup = () => animations.delete(animation);

      animation.addEventListener("finish", cleanup, { once: true });
      animation.addEventListener("cancel", cleanup, { once: true });
    }

    function revealOnce(element, callback) {
      if (
        !element ||
        reducedMotion.matches ||
        !("IntersectionObserver" in window) ||
        element.getBoundingClientRect().bottom <= 0
      ) {
        return null;
      }

      const observer = new IntersectionObserver(
        (entries) => {
          if (!entries.some((entry) => entry.isIntersecting)) return;

          observer.disconnect();
          observers.delete(observer);
          callback();
        },
        { threshold: 0.08 },
      );

      observers.add(observer);
      observer.observe(element);

      return observer;
    }

    // Header trong suốt ở đầu trang, có nền khi cuộn.
    const header = document.querySelector(".site-header");

    if (header) {
      let scheduled = false;

      const updateHeader = () => {
        header.classList.toggle("is-scrolled", window.scrollY > 40);
        scheduled = false;
      };

      window.addEventListener(
        "scroll",
        () => {
          if (scheduled) return;

          scheduled = true;
          window.requestAnimationFrame(updateHeader);
        },
        { passive: true },
      );

      window.addEventListener("pageshow", updateHeader);
      updateHeader();
    }

    // Carousel phòng: 3 phòng trên máy tính, 2 trên tablet, 1 trên mobile.
    document.querySelectorAll("[data-room-carousel]").forEach((carousel) => {
      const grid = carousel.querySelector(".hh-room-grid");
      const controls = carousel.querySelector(".hh-carousel-controls");
      const previous = carousel.querySelector("[data-room-prev]");
      const next = carousel.querySelector("[data-room-next]");
      const status = carousel.querySelector("[data-room-status]");

      if (!grid || !controls || !previous || !next) return;

      const cards = Array.from(grid.querySelectorAll(".hh-room"));

      if (!cards.length) return;

      previous.type = "button";
      next.type = "button";

      // Loại bỏ trạng thái của cơ chế hiệu ứng cũ.
      cards.forEach((card) => {
        card.classList.remove("hh-scroll-reveal", "is-visible");
        card.style.removeProperty("--room-reveal-delay");
      });

      const getPageSize = () =>
        Math.min(mobile.matches ? 1 : tablet.matches ? 2 : 3, cards.length);

      let page = 0;
      let pageSize = getPageSize();
      let firstReveal = null;

      function animateCards() {
        stopAnimations(grid);

        Array.from(grid.children)
          .filter((card) => !card.hidden)
          .forEach((card, index) => {
            animateElement(card, "up", index * 100);
          });
      }

      function render(animate = false) {
        stopAnimations(grid);

        const pageCount = Math.ceil(cards.length / pageSize);
        page = ((page % pageCount) + pageCount) % pageCount;

        const visible = Array.from(
          { length: pageSize },
          (_, offset) => cards[(page * pageSize + offset) % cards.length],
        );

        const visibleSet = new Set(visible);
        const focused = document.activeElement;
        const focusedCard = cards.find((card) => card.contains(focused));

        if (focusedCard && !visibleSet.has(focusedCard)) {
          if (pageCount > 1) {
            next.focus({ preventScroll: true });
          } else {
            carousel.setAttribute("tabindex", "-1");
            carousel.focus({ preventScroll: true });
          }
        }

        const fragment = document.createDocumentFragment();

        visible.forEach((card) => {
          card.hidden = false;
          fragment.appendChild(card);
        });

        cards.forEach((card) => {
          if (visibleSet.has(card)) return;

          card.hidden = true;
          fragment.appendChild(card);
        });

        grid.appendChild(fragment);

        controls.hidden = pageCount <= 1;
        previous.disabled = pageCount <= 1;
        next.disabled = pageCount <= 1;

        if (status) {
          status.textContent = `Nhóm ${page + 1} / ${pageCount}`;
        }

        if (animate) animateCards();
      }

      function changePage(direction) {
        if (firstReveal) {
          firstReveal.disconnect();
          observers.delete(firstReveal);
          firstReveal = null;
        }

        page += direction;
        render(true);
      }

      previous.addEventListener("click", () => changePage(-1));
      next.addEventListener("click", () => changePage(1));

      function updateLayout() {
        const newPageSize = getPageSize();

        if (newPageSize === pageSize) return;

        const firstIndex = page * pageSize;

        pageSize = newPageSize;
        page = Math.floor(firstIndex / pageSize);
        render();
      }

      mobile.addEventListener("change", updateLayout);
      tablet.addEventListener("change", updateLayout);

      render();
      firstReveal = revealOnce(grid, animateCards);
    });

    // Hiệu ứng cuộn cho các phần nội dung.
    const home = document.querySelector(".hotel-home");

    if (home) {
      const targets = new Map();

      function register(element, direction = "up", delay = 0) {
        if (element && !targets.has(element)) {
          targets.set(element, { direction, delay });
        }
      }

      register(home.querySelector(".hh-intro-photo"), "left");
      register(home.querySelector(".hh-intro-copy"), "up", 120);

      register(home.querySelector("#experiences .hh-heading"));

      home.querySelectorAll(".hh-experience").forEach((section) => {
        register(
          section.querySelector(":scope > img"),
          section.classList.contains("reverse") ? "right" : "left",
        );

        register(section.querySelector(".hh-experience-copy"), "up", 140);
      });

      register(home.querySelector("#services .hh-heading"));

      home
        .querySelectorAll("#services .hh-service-list > li")
        .forEach((item, index) => {
          register(item, "up", Math.min(index, 4) * 80);
        });

      register(home.querySelector("#services .hh-container > p"), "up", 100);

      const closing = home.querySelector(".hh-closing");

      if (closing) {
        register(closing.querySelector(".hh-kicker"));
        register(closing.querySelector(".hh-title"), "up", 100);
        register(closing.querySelector("p"), "up", 200);
        register(closing.querySelector(".hh-button"), "up", 300);
      }

      if (!reducedMotion.matches && "IntersectionObserver" in window) {
        const observer = new IntersectionObserver(
          (entries) => {
            entries.forEach((entry) => {
              if (!entry.isIntersecting) return;

              observer.unobserve(entry.target);

              const settings = targets.get(entry.target);

              if (settings) {
                animateElement(
                  entry.target,
                  settings.direction,
                  settings.delay,
                );
              }
            });
          },
          { threshold: 0.08 },
        );

        observers.add(observer);

        targets.forEach((settings, element) => {
          if (element.getBoundingClientRect().bottom > 0) {
            observer.observe(element);
          }
        });
      }

      home.addEventListener("focusin", (event) => {
        for (const animation of Array.from(animations)) {
          const target = animation.effect?.target;

          if (target?.contains(event.target)) {
            animation.cancel();
          }
        }
      });
    }

    reducedMotion.addEventListener("change", (event) => {
      if (!event.matches) return;

      stopAnimations();

      observers.forEach((observer) => observer.disconnect());
      observers.clear();
    });
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", initHotelHome, {
      once: true,
    });
  } else {
    initHotelHome();
  }
})();
