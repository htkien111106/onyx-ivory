// Menu trên điện thoại
const menuToggle = document.querySelector(".menu-toggle");
const mainNav = document.querySelector("#main-nav");

if (menuToggle && mainNav) {
  menuToggle.addEventListener("click", () => {
    const open = menuToggle.getAttribute("aria-expanded") !== "true";

    menuToggle.setAttribute("aria-expanded", String(open));
    mainNav.classList.toggle("open", open);
  });

  mainNav.querySelectorAll("a").forEach((link) => {
    link.addEventListener("click", () => {
      mainNav.classList.remove("open");
      menuToggle.setAttribute("aria-expanded", "false");
    });
  });
}

// Ngày trả phòng sớm nhất là ngày kế tiếp sau ngày nhận.
document.querySelectorAll("form").forEach((form) => {
  const checkIn = form.querySelector('input[type="date"][name="check_in"]');
  const checkOut = form.querySelector('input[type="date"][name="check_out"]');

  if (!checkIn || !checkOut) return;

  function syncDates() {
    const nextDay = checkIn.valueAsDate;
    if (!nextDay) return;

    nextDay.setUTCDate(nextDay.getUTCDate() + 1);
    const minimum = nextDay.toISOString().slice(0, 10);

    checkOut.min = minimum;

    if (!checkOut.value || checkOut.value < minimum) {
      checkOut.value = minimum;
    }
  }

  checkIn.addEventListener("change", syncDates);
  syncDates();
});

// Hộp xác nhận dùng chung cho các form có data-confirm.
const confirmDialog = document.createElement("dialog");
confirmDialog.className = "onyx-confirm";
confirmDialog.setAttribute("aria-labelledby", "onyx-confirm-title");
confirmDialog.setAttribute("aria-describedby", "onyx-confirm-message");

confirmDialog.innerHTML = `
    <p class="onyx-confirm-brand">THE ONYX &amp; IVORY</p>
    <h2 id="onyx-confirm-title">Xác nhận thao tác</h2>
    <p id="onyx-confirm-message"></p>

    <div class="onyx-confirm-actions">
        <button type="button" class="onyx-back" autofocus>Quay lại</button>
        <button type="button" class="onyx-accept">Xác nhận</button>
    </div>
`;

document.body.appendChild(confirmDialog);

const confirmMessage = confirmDialog.querySelector("#onyx-confirm-message");
const backButton = confirmDialog.querySelector(".onyx-back");
const acceptButton = confirmDialog.querySelector(".onyx-accept");

let pendingForm = null;
let pendingSubmitter = null;
let approvedForm = null;

document.addEventListener(
  "submit",
  (event) => {
    const form = event.target;

    if (!form.matches("form[data-confirm]") || form === approvedForm) {
      return;
    }

    // Chờ người dùng xác nhận trước khi chạy các xử lý gửi đơn.
    event.preventDefault();
    event.stopImmediatePropagation();

    if (confirmDialog.open) return;

    pendingForm = form;
    pendingSubmitter = event.submitter;
    confirmMessage.textContent = form.dataset.confirm;
    confirmDialog.showModal();
  },
  true,
);

function dismissConfirmation() {
  pendingForm = null;
  pendingSubmitter = null;
  confirmDialog.close();
}

backButton.addEventListener("click", dismissConfirmation);

confirmDialog.addEventListener("cancel", (event) => {
  // Nhấn Esc có tác dụng như bấm Quay lại.
  event.preventDefault();
  dismissConfirmation();
});

acceptButton.addEventListener("click", () => {
  const form = pendingForm;
  const submitter = pendingSubmitter;

  dismissConfirmation();
  if (!form) return;

  // Gửi đúng form và đúng nút đã bấm, kể cả form có nhiều thao tác.
  approvedForm = form;

  try {
    if (submitter) {
      form.requestSubmit(submitter);
    } else {
      form.requestSubmit();
    }
  } finally {
    approvedForm = null;
  }
});

// Khóa nút đặt phòng trong lúc gửi và khôi phục khi quay lại trang.
document.querySelectorAll("form[data-booking-form]").forEach((form) => {
  const button = form.querySelector('button[type="submit"]');
  if (!button) return;

  const originalLabel = button.textContent;

  form.addEventListener("submit", (event) => {
    if (event.defaultPrevented) return;

    button.disabled = true;
    button.textContent = "ĐANG GỬI YÊU CẦU…";
  });

  window.addEventListener("pageshow", () => {
    button.disabled = false;
    button.textContent = originalLabel;
  });
});

// Tính tổng tiền khi lễ tân tạo đơn tại quầy.
const desk = document.querySelector("#desk-booking");

if (desk) {
  const roomType = desk.querySelector('[name="room_type_id"]');
  const quotedPrice = desk.querySelector('[name="quoted_price"]');
  const checkIn = desk.querySelector('[name="check_in"]');
  const checkOut = desk.querySelector('[name="check_out"]');
  const quantity = desk.querySelector('[name="quantity"]');
  const total = desk.querySelector("#desk-total");
  const currency = new Intl.NumberFormat("vi-VN");

  function recalculateTotal() {
    const price = Number(roomType.selectedOptions[0]?.dataset.price || 0);
    quotedPrice.value = String(price);

    const start = Date.parse(checkIn.value + "T00:00:00Z");
    const end = Date.parse(checkOut.value + "T00:00:00Z");
    const nights = (end - start) / 86400000;
    const count = Number(quantity.value);

    if (nights > 0 && Number.isInteger(count) && count > 0) {
      const amount = currency.format(price * nights * count);
      total.textContent =
        `${nights} đêm × ${count} phòng · Tổng: ${amount} ₫ ` +
        "(đã gồm thuế và bữa sáng)";
    } else {
      total.textContent = "Vui lòng chọn ngày và số phòng hợp lệ.";
    }
  }

  desk.addEventListener("change", recalculateTotal);
  recalculateTotal();
}
/* Hiệu ứng xuất hiện cho danh sách phòng */
(() => {
  "use strict";

  function initRoomsAnimation() {
    if (!document.body.matches(".page-rooms:not(.staff-page)")) {
      return;
    }

    const list = document.querySelector(".room-list");

    if (!list || list.dataset.revealReady === "1") return;

    list.dataset.revealReady = "1";

    const cards = Array.from(list.querySelectorAll(".room-row"));
    const reducedMotion = window.matchMedia("(prefers-reduced-motion: reduce)");

    if (
      !cards.length ||
      reducedMotion.matches ||
      !("IntersectionObserver" in window)
    ) {
      return;
    }

    const animations = new Map();
    const revealed = new WeakSet();

    function reveal(card, delay = 0) {
      if (revealed.has(card)) return;

      revealed.add(card);

      if (
        reducedMotion.matches ||
        typeof card.animate !== "function" ||
        card.contains(document.activeElement)
      ) {
        return;
      }

      const animation = card.animate(
        [
          {
            opacity: 0,
            transform: "translateY(36px)",
          },
          {
            opacity: 1,
            transform: "translateY(0)",
          },
        ],
        {
          duration: 850,
          delay,
          easing: "cubic-bezier(0.22, 1, 0.36, 1)",
          fill: "backwards",
        },
      );

      animations.set(card, animation);

      const cleanup = () => animations.delete(card);

      animation.addEventListener("finish", cleanup, { once: true });
      animation.addEventListener("cancel", cleanup, { once: true });
    }

    const observer = new IntersectionObserver(
      (entries) => {
        entries
          .filter((entry) => entry.isIntersecting)
          .sort((a, b) => {
            return a.boundingClientRect.top - b.boundingClientRect.top;
          })
          .forEach((entry, index) => {
            observer.unobserve(entry.target);
            reveal(entry.target, Math.min(index, 2) * 120);
          });
      },
      {
        threshold: 0.08,
      },
    );

    cards.forEach((card) => {
      // Không chạy lại các phòng đã cuộn qua khi tải lại trang.
      if (card.getBoundingClientRect().bottom <= 0) {
        revealed.add(card);
        return;
      }

      observer.observe(card);
    });

    // Hiển thị ngay nếu khách dùng bàn phím chọn vào thẻ phòng.
    list.addEventListener("focusin", (event) => {
      const card = event.target.closest(".room-row");

      if (!card) return;

      observer.unobserve(card);
      revealed.add(card);
      animations.get(card)?.cancel();
    });

    // Dừng hiệu ứng khi bật chế độ giảm chuyển động.
    reducedMotion.addEventListener("change", (event) => {
      if (!event.matches) return;

      observer.disconnect();

      for (const animation of Array.from(animations.values())) {
        animation.cancel();
      }

      animations.clear();
    });
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", initRoomsAnimation, {
      once: true,
    });
  } else {
    initRoomsAnimation();
  }
})();
