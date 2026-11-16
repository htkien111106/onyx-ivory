(() => {
  "use strict";

  function initRoomDetailHeader() {
    const body = document.body;

    if (!body.matches(".page-room:not(.staff-page)")) return;
    if (body.dataset.roomHeaderReady === "1") return;

    const header = document.querySelector(".site-header");
    const hero = document.querySelector(".detail-hero");

    // Trang không tìm thấy phòng vẫn giữ header có nền.
    if (!header || !hero) return;

    body.dataset.roomHeaderReady = "1";

    let scheduled = false;

    function updateHeader() {
      header.classList.toggle("is-scrolled", window.scrollY > 40);
      scheduled = false;
    }

    // Xác định màu trước khi chuyển header sang dạng phủ trên banner.
    updateHeader();
    body.classList.add("room-has-hero");

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
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", initRoomDetailHeader, {
      once: true,
    });
  } else {
    initRoomDetailHeader();
  }
})();
