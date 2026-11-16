/* Chỉ tổ chức cách hiển thị.
   Không tính giá, gửi đơn, ghi tiền hay đổi quyền. */

(() => {
  "use strict";

  const body = document.body;

  // Tránh khởi tạo giao diện nhiều lần.
  if (!body || body.dataset.usabilityReady === "true") return;
  body.dataset.usabilityReady = "true";

  const path = location.pathname;
  const page = path.split("/").pop() || "index.php";
  const area = /\/(admin|reception|staff)\//.exec(path)?.[1] || "";
  const key = (area ? area + "-" : "") + page.replace(".php", "");

  body.dataset.uiArea = key;

  const workspace = document.querySelector(".workspace-content");

  // Giữ phân biệt giao diện nhân viên với giao diện khách hàng.
  if (area) body.classList.add("staff-page");

  const hasError = Boolean(document.querySelector(".notice.error"));

  const make = (tag, className, content) => {
    const node = document.createElement(tag);

    if (className) node.className = className;
    if (content !== undefined) node.textContent = content;

    return node;
  };

  const text = (node) => node?.textContent.trim().replace(/\s+/g, " ") || "";

  const fold = (value) =>
    value
      .normalize("NFD")
      .replace(/[\u0300-\u036f]/g, "")
      .toLowerCase()
      .replace(/đ/g, "d");

  const openParents = (node) => {
    for (
      let parent = node?.parentElement;
      parent;
      parent = parent.parentElement
    ) {
      if (parent.tagName === "DETAILS") {
        parent.open = true;
      }
    }
  };

  function collapse(node, title, subtitle = "", opened = false) {
    const details = make("details", "ui-collapse");
    details.open = opened;

    const summary = make("summary", "ui-collapse-trigger");
    const labels = make("span", "ui-collapse-labels");

    labels.append(make("span", "ui-collapse-title", title));

    if (subtitle) {
      labels.append(make("span", "ui-collapse-subtitle", subtitle));
    }

    summary.append(
      labels,
      make("span", "ui-collapse-affordance", "Mở / thu gọn"),
    );

    node.before(details);
    details.append(summary, node);
    node.classList.add("ui-collapse-body");

    return details;
  }

  // Menu chỉ sử dụng các liên kết được PHP cho phép hiển thị.
  function staffNavigation() {
    const nav = document.querySelector(".workspace-links");
    if (!nav) return;

    const links = Array.from(nav.querySelectorAll("a"));

    if (!links.some((link) => link.getAttribute("aria-current") === "page")) {
      const parentPage =
        key === "staff-customer-detail"
          ? "staff/customers.php"
          : area === "reception"
            ? "reception/index.php"
            : "";

      links
        .find((link) => parentPage && link.pathname.endsWith("/" + parentPage))
        ?.setAttribute("aria-current", "page");
    }

    if (nav.closest('.workspace-shell[data-workspace-mode="admin"]')) {
      return;
    }

    if (!nav.closest(".workspace-shell[data-workspace-mode]")) {
      const groups = [
        [
          "Vận hành khách sạn",
          (link) =>
            /\/reception\//.test(link.pathname) ||
            /\/staff\/customers\.php$/.test(link.pathname),
        ],
        ["Quản trị & danh mục", (link) => /\/admin\//.test(link.pathname)],
        ["Tài khoản", () => true],
      ];

      nav
        .querySelectorAll(".nav-divider")
        .forEach((divider) => divider.remove());

      for (const [label, matches] of groups) {
        const groupLinks = links.filter(
          (link) => !link.dataset.uiGrouped && matches(link),
        );

        if (!groupLinks.length) continue;

        const group = make("details", "ui-nav-group");
        const active = groupLinks.some(
          (link) => link.getAttribute("aria-current") === "page",
        );

        group.open =
          active ||
          (!document.querySelector(".ui-nav-group") && area === "reception");

        group.append(make("summary", "ui-nav-group-title", label));

        for (const link of groupLinks) {
          link.dataset.uiGrouped = "true";

          link.replaceChildren(make("span", "ui-nav-link-label", text(link)));

          group.append(link);
        }

        nav.append(group);
      }
    }

    const eyebrow = document.querySelector(".workspace-nav > .eyebrow");

    if (eyebrow) {
      eyebrow.textContent = "Khu vực làm việc";
    }

    const heading = document.querySelector(".workspace-nav > h3");

    if (heading) {
      const avatar = make(
        "span",
        "ui-avatar",
        text(heading).charAt(0).toUpperCase(),
      );

      avatar.setAttribute("aria-hidden", "true");
      heading.before(avatar);
    }

    document.querySelectorAll("#main-nav > a").forEach((link) => {
      if (link.hash || /\/(rooms|lookup)\.php$/.test(link.pathname)) {
        link.classList.add("ui-public-link");
      }
    });
  }

  function catalogueEditors() {
    if (!workspace || workspace.querySelector(".admin-catalog")) {
      return;
    }

    if (key === "reception-rooms") {
      const grid = workspace.querySelector(".physical-grid");

      grid?.querySelectorAll(".physical-card").forEach((card) => {
        card.dataset.uiState =
          Array.from(card.classList)
            .find((name) => name.startsWith("state-"))
            ?.slice(6) || "";

        const form = card.querySelector("form");

        if (form) {
          collapse(form, "Cập nhật tình trạng");
        }
      });

      localFinder(grid, ".physical-card", true);
    }

    if (key === "admin-rooms") {
      const add = workspace.querySelector(
        'form:has(input[name="action"][value="add"])',
      );

      if (add) {
        collapse(
          add,
          "+ Thêm phòng",
          "Chọn loại phòng và nhập số phòng mới",
          hasError,
        );
      }

      document.querySelectorAll(".physical-grid > form").forEach((form) => {
        const title = text(form.querySelector("h3"));
        const name = text(form.querySelector("p"));
        const active = form.querySelector('[name="active"]')?.checked;

        const housekeeping = form.querySelector('[name="housekeeping"]');

        const state = housekeeping?.selectedOptions[0]?.textContent.trim();

        const card = collapse(form, title, name, false);
        card.classList.add("ui-entity-card");

        card.dataset.uiState = active ? housekeeping?.value || "" : "inactive";

        card
          .querySelector("summary")
          .append(
            make("span", "ui-state-badge", active ? state : "Ngừng sử dụng"),
          );

        card.querySelector(".ui-collapse-affordance").textContent = "Chỉnh sửa";
      });

      localFinder(
        document.querySelector(".physical-grid"),
        ".ui-entity-card",
        true,
      );
    }

    if (key === "admin-users") {
      const forms = Array.from(
        workspace.querySelectorAll(":scope > form"),
      ).filter((form) => form.querySelector('[name="action"][value="update"]'));

      if (forms.length) {
        const grid = make("div", "ui-entity-grid");
        forms[0].before(grid);

        forms.forEach((form) => {
          grid.append(form);

          const name = text(form.querySelector("h3"));
          const email = form.querySelector('[name="email"]')?.value || "";

          const role =
            form
              .querySelector('[name="role"]')
              ?.selectedOptions[0]?.textContent.trim() || "";

          const card = collapse(form, name, email, false);
          card.classList.add("ui-entity-card");

          card
            .querySelector("summary")
            .append(make("span", "ui-state-badge", role));

          card.querySelector(".ui-collapse-affordance").textContent =
            "Chỉnh sửa";

          if (!form.querySelector('[name="active"]')?.checked) {
            card.classList.add("ui-inactive");

            card
              .querySelector("summary")
              .append(make("span", "ui-state-badge", "Đã khóa"));
          }
        });

        localFinder(grid, ".ui-entity-card");
      }

      if (hasError) {
        workspace.querySelector("details")?.setAttribute("open", "");
      }
    }

    if (key === "admin-services") {
      const panel = workspace.querySelector(":scope > .panel:has(form)");

      if (panel) {
        const editing = Number(panel.querySelector('[name="id"]')?.value) > 0;

        collapse(
          panel,
          editing ? "Chỉnh sửa dịch vụ" : "+ Thêm dịch vụ",
          "Tên, đơn vị tính, giá và trạng thái cung cấp",
          editing || hasError,
        );
      }
    }

    if (key === "admin-promotions") {
      const form = workspace.querySelector(
        'form:has(input[name="action"][value="create"])',
      );

      if (form) {
        collapse(
          form,
          "+ Tạo chương trình ưu đãi",
          "Mã giảm giá, thời hạn và điều kiện áp dụng",
          hasError,
        );
      }
    }

    if (["admin-services", "admin-promotions"].includes(key)) {
      const table = workspace.querySelector(".table-wrap");

      if (table) {
        localFinder(table, "tbody > tr:not(:has(td[colspan]))");
      }
    }
  }

  function localFinder(container, selector, roomStates = false) {
    if (!container || !container.querySelector(selector)) return;

    const records = Array.from(container.querySelectorAll(selector));

    const bar = make("div", "ui-list-tools");

    const label = make(
      "label",
      "ui-list-search",
      "Tìm trong danh sách đang hiển thị",
    );

    const search = make("input");
    search.type = "search";

    search.placeholder = roomStates
      ? "Số phòng hoặc loại phòng…"
      : "Nhập tên, email hoặc mã cần tìm…";

    search.autocomplete = "off";
    label.append(search);

    const count = make("span", "ui-list-count");
    count.setAttribute("role", "status");

    bar.append(label);

    let states;

    if (roomStates) {
      const stateLabel = make("label", "ui-state-filter", "Tình trạng phòng");

      states = make("select");

      const options = [
        ["", "Tất cả"],
        ["ready", "Sẵn sàng"],
        ["dirty", "Chờ dọn"],
        ["maintenance", "Bảo trì"],
        ["inactive", "Ngừng sử dụng"],
      ];

      if (records.some((record) => record.dataset.uiState === "occupied")) {
        options.splice(1, 0, ["occupied", "Đang có khách"]);
      }

      for (const [value, name] of options) {
        const option = make("option", "", name);
        option.value = value;
        states.append(option);
      }

      stateLabel.append(states);
      bar.append(stateLabel);
    }

    const clear = make("button", "ui-clear-filter", "Xóa lọc");
    clear.type = "button";

    bar.append(clear, count);

    const empty = make(
      "p",
      "ui-filter-empty",
      "Không có kết quả trong danh sách đang hiển thị. " +
        "Hãy xóa hoặc thay đổi bộ lọc.",
    );

    empty.hidden = true;

    container.before(bar);
    container.after(empty);

    const haystacks = records.map((record) => {
      const values = Array.from(
        record.querySelectorAll(
          'input:not([type="password"]):not([type="hidden"])',
        ),
      )
        .map((input) => input.value)
        .join(" ");

      return fold(text(record) + " " + values);
    });

    function apply() {
      let visible = 0;

      records.forEach((record, index) => {
        const show =
          haystacks[index].includes(fold(search.value.trim())) &&
          (!states?.value || record.dataset.uiState === states.value);

        record.hidden = !show;

        if (show) visible++;
      });

      count.textContent = `${visible} / ${records.length} mục`;
      empty.hidden = visible > 0;
      clear.hidden = !search.value && !states?.value;
    }

    search.addEventListener("input", apply);
    states?.addEventListener("change", apply);

    clear.addEventListener("click", () => {
      search.value = "";

      if (states) states.value = "";

      apply();
      search.focus();
    });

    apply();
  }

  function memberNavigation() {
    const pages = [
      "account.php",
      "membership.php",
      "change-password.php",
      "booking-detail.php",
      "booking-change.php",
      "booking-payment.php",
      "booking-review.php",
    ];

    if (
      area ||
      !pages.includes(page) ||
      !document.querySelector("#main-nav .nav-user")
    ) {
      return;
    }

    const brand = document.querySelector(".brand");

    const anchor =
      document.querySelector(".page-heading") ||
      document.querySelector("#main-content > section");

    if (!brand || !anchor) return;

    const nav = make("nav", "ui-member-nav");
    nav.setAttribute("aria-label", "Tài khoản thành viên");

    const base = new URL(".", brand.href);

    const links = [
      ["account.php#history", "Đơn đặt phòng"],
      ["account.php#profile", "Hồ sơ cá nhân"],
      ["membership.php", "Thẻ thành viên"],
      ["change-password.php", "Đổi mật khẩu"],
    ];

    for (const [href, title] of links) {
      const link = make("a", "", title);
      link.href = new URL(href, base).href;
      nav.append(link);
    }

    const update = () => {
      nav.querySelectorAll("a").forEach((link) => {
        const target = new URL(link.href);

        const active =
          target.pathname === path &&
          (page !== "account.php" ||
            target.hash === (location.hash || "#history"));

        if (active) {
          link.setAttribute("aria-current", "page");
        } else {
          link.removeAttribute("aria-current");
        }
      });
    };

    update();
    window.addEventListener("hashchange", update);
    anchor.after(nav);

    if (page === "account.php") {
      const profile = document.querySelector("#profile");
      const history = document.querySelector("#history");

      if (profile && history) {
        const dashboard = make("div", "ui-member-dashboard");

        profile.before(dashboard);
        dashboard.append(profile, history);

        const form = profile.querySelector("form");

        if (form) {
          collapse(
            form,
            "Chỉnh sửa hồ sơ",
            "Họ tên, số điện thoại và email tài khoản",
            hasError || location.hash === "#profile",
          );
        }

        history.querySelectorAll("article.panel").forEach((card) => {
          card.classList.add("ui-booking-card");

          const status = card.querySelector(":scope > .eyebrow");

          if (status) {
            status.classList.remove("eyebrow");
            status.classList.add("badge", "ui-history-status");
          }

          const rows = Array.from(card.querySelectorAll(":scope > p")).filter(
            (p) => p.querySelector("strong"),
          );

          if (rows.length) {
            const meta = make("div", "ui-booking-meta");
            rows[0].before(meta);
            rows.forEach((row) => meta.append(row));
          }
        });
      }
    }
  }

  function bookingForms() {
    if (!area && page === "booking.php") {
      const dates = document.querySelector("#stay-dates-form");
      dates?.parentElement?.classList.add("ui-stay-editor");

      const form = document.querySelector("#reservation-form");

      if (form) {
        const note = form.querySelector('[name="note"]');
        const label = note?.closest("label");

        if (note && label) {
          const helper =
            label.nextElementSibling?.tagName === "SMALL"
              ? label.nextElementSibling
              : null;

          const group = make("div", "ui-optional-body");

          label.before(group);
          group.append(label);

          if (helper) group.append(helper);

          collapse(
            group,
            "Yêu cầu đặc biệt",
            "Không bắt buộc",
            Boolean(note.value),
          );
        }

        const promo = form.querySelector("#booking-promo-code");

        if (promo) {
          const start = promo.closest("label")?.previousElementSibling;

          const end = form.querySelector("#booking-promo-message");

          if (start?.tagName === "H3" && end) {
            const group = make("div", "ui-optional-body");
            start.before(group);

            let current = start;

            while (current) {
              const next = current.nextElementSibling;
              group.append(current);

              if (current === end) break;

              current = next;
            }

            collapse(
              group,
              "Bạn có mã giảm giá?",
              "Mở để nhập hoặc chọn mã ưu đãi",
              Boolean(promo.value),
            );

            start.classList.add("ui-repeated-heading");
          }
        }
      }
    }

    if (key === "reception-booking" && workspace) {
      workspace.querySelectorAll(":scope > form").forEach((form) => {
        const action = form.querySelector('[name="action"]')?.value;

        const status = form.querySelector('[name="status"]')?.value;

        if (action === "payment") {
          collapse(
            form,
            "Ghi nhận thu / hoàn tiền",
            "Mở khi cần ghi nhận giao dịch thực tế",
            hasError,
          );
        }

        if (status === "cancelled") {
          collapse(
            form,
            "Hủy đơn đặt phòng",
            "Thao tác cần xác nhận",
            false,
          ).classList.add("ui-danger-zone");
        }
      });

      sectionNavigation(workspace);
    }

    if (key === "reception-transfers" && workspace) {
      workspace.querySelectorAll("article > form").forEach((form) => {
        collapse(
          form,
          "Đối chiếu & xử lý yêu cầu",
          "Kiểm tra tiền vào trước khi xác nhận",
          hasError,
        );
      });
    }

    if (
      key === "admin-reviews" &&
      workspace &&
      !workspace.querySelector(".admin-catalog")
    ) {
      workspace.querySelectorAll("form:has(textarea)").forEach((form) => {
        collapse(
          form,
          "Viết phản hồi đánh giá",
          "Nội dung trả lời khách hàng",
          hasError,
        );
      });
    }

    if (page === "booking-detail.php" && !area) {
      sectionNavigation(document.querySelector(".customer-booking-detail"));
    }
  }

  function sectionNavigation(container) {
    if (!container) return;

    const items = Array.from(container.querySelectorAll("h2")).filter(
      (heading) => !heading.closest("table"),
    );

    if (items.length < 3) return;

    const nav = make("nav", "ui-section-nav");
    nav.setAttribute("aria-label", "Đến nhanh nội dung");

    items.slice(0, 8).forEach((heading, index) => {
      const target = heading.closest(".ui-collapse") || heading;

      if (!target.id) {
        target.id = `ui-section-${index}`;
      }

      const link = make("a", "", index === 0 ? "Thông tin đơn" : text(heading));

      link.href = "#" + target.id;
      nav.append(link);
    });

    const actions = container.querySelector(
      ":scope > .actions, :scope > .booking-actions",
    );

    if (actions) {
      actions.after(nav);
    } else {
      container.prepend(nav);
    }
  }

  function passwordsAndNotices() {
    document
      .querySelectorAll('input[type="password"]')
      .forEach((input, index) => {
        // Trang đổi mật khẩu đã có nút hiện/ẩn riêng.
        const hasOwnToggle =
          input.id &&
          Array.from(document.querySelectorAll("[data-password-target]")).some(
            (button) => button.dataset.passwordTarget === input.id,
          );

        // Không tạo nút thứ hai.
        if (hasOwnToggle || input.closest(".ui-password-field")) {
          return;
        }

        if (!input.id) {
          input.id = `ui-password-${index}`;
        }

        const wrap = make("span", "ui-password-field");
        input.before(wrap);
        wrap.append(input);

        const toggle = make("button", "ui-password-toggle", "Hiện");

        toggle.type = "button";
        toggle.setAttribute("aria-controls", input.id);
        toggle.setAttribute("aria-label", "Hiện mật khẩu");
        toggle.setAttribute("aria-pressed", "false");

        toggle.addEventListener("click", () => {
          const showing = input.type === "password";

          input.type = showing ? "text" : "password";
          toggle.textContent = showing ? "Ẩn" : "Hiện";

          toggle.setAttribute(
            "aria-label",
            showing ? "Ẩn mật khẩu" : "Hiện mật khẩu",
          );

          toggle.setAttribute("aria-pressed", String(showing));
        });

        wrap.append(toggle);
      });

    // Thông báo cũ dành cho khu vực nhân viên.
    // Thông báo #hotel-toast đã có xử lý riêng trong header.php.
    const flash = document.querySelector("#flash-message");

    if (flash && !flash.querySelector(".ui-notice-close")) {
      flash.classList.add("ui-flash");

      const close = make("button", "ui-notice-close", "×");
      close.type = "button";
      close.setAttribute("aria-label", "Đóng thông báo");

      close.addEventListener("click", () => flash.remove());
      flash.append(close);
    }
  }

  function tablesAndStates() {
    document.querySelectorAll(".badge, .ui-state-badge").forEach((badge) => {
      const value = fold(text(badge));
      let state = "neutral";

      if (/cho xac nhan|cho don|cho khach|cho xu ly|cho duyet/.test(value)) {
        state = "pending";
      } else if (/da huy|tu choi|ngung|bao tri|khoa/.test(value)) {
        state = "danger";
      } else if (
        /dang luu tru|san sang|da xac nhan|da nhan|hoat dong|dang cung cap/.test(
          value,
        )
      ) {
        state = "success";
      } else if (/xac nhan|da chuyen|dang co khach/.test(value)) {
        state = "info";
      }

      badge.dataset.uiTone = state;
    });

    document.querySelectorAll(".table-wrap").forEach((wrap) => {
      const table = wrap.querySelector("table");
      if (!table) return;

      wrap.setAttribute("tabindex", "0");
      wrap.setAttribute("role", "region");
      wrap.setAttribute("aria-label", "Bảng dữ liệu, có thể cuộn ngang");

      const head = table.querySelector("thead tr");

      if (head?.lastElementChild && !text(head.lastElementChild)) {
        head.lastElementChild.textContent = "Thao tác";
      }

      if (
        head &&
        Array.from(table.querySelectorAll("tbody tr")).some((row) =>
          row.lastElementChild?.querySelector("a, button, details"),
        )
      ) {
        table.classList.add("ui-action-table");
      }

      const hint = make(
        "div",
        "ui-table-hint",
        "← Cuộn ngang trong bảng để xem đầy đủ →",
      );

      hint.hidden = true;
      wrap.before(hint);

      const update = () => {
        hint.hidden = wrap.scrollWidth <= wrap.clientWidth + 2;
      };

      new ResizeObserver(update).observe(wrap);
      update();
    });
  }

  staffNavigation();
  catalogueEditors();
  memberNavigation();
  bookingForms();
  passwordsAndNotices();
  tablesAndStates();

  // Mở nội dung thu gọn khi truy cập qua liên kết.
  function followHash() {
    if (!location.hash || location.hash.length < 2) return;

    let target;

    try {
      target = document.getElementById(
        decodeURIComponent(location.hash.slice(1)),
      );
    } catch {
      return;
    }

    if (!target) return;

    openParents(target);

    if (target.tagName === "DETAILS") {
      target.open = true;
    }

    if (target.id === "profile") {
      target.querySelector("details")?.setAttribute("open", "");
    }
  }

  document.addEventListener("click", (event) => {
    const link = event.target.closest('a[href^="#"]');
    if (!link) return;

    const target = document.getElementById(link.getAttribute("href").slice(1));

    if (!target) return;

    openParents(target);

    if (target.tagName === "DETAILS") {
      target.open = true;
    }
  });

  document.addEventListener(
    "invalid",
    (event) => openParents(event.target),
    true,
  );

  window.addEventListener("hashchange", followHash);
  followHash();
})();
