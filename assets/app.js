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
