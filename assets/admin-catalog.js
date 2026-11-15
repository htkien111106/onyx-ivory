/* Điều khiển giao diện danh mục. Không tính giá, đổi quyền hoặc tự gửi biểu mẫu nghiệp vụ. */
(() => {
  'use strict';
  const shell = document.querySelector('.workspace-shell[data-workspace-mode]');
  if (shell) document.body.dataset.uiWorkspace = shell.dataset.workspaceMode;

  const menuToggle = document.querySelector('[data-admin-menu-toggle]');
  const menuClose = document.querySelector('[data-admin-menu-close]');
  function setMenu(open) {
    document.body.classList.toggle('admin-menu-open', open);
    menuToggle?.setAttribute('aria-expanded', String(open));
  }
  menuToggle?.addEventListener('click', () => setMenu(menuToggle.getAttribute('aria-expanded') !== 'true'));
  menuClose?.addEventListener('click', () => {setMenu(false);menuToggle?.focus();});
  document.addEventListener('keydown', event => {
    if (event.key === 'Escape' && document.body.classList.contains('admin-menu-open')) {
      setMenu(false);menuToggle?.focus();
    }
  });

  let returnFocus = null;
  function openDialog(dialog, trigger) {
    if (!(dialog instanceof HTMLDialogElement) || dialog.open) return;
    returnFocus = trigger || document.activeElement;
    dialog.showModal();
  }
  document.addEventListener('click', event => {
    const opener = event.target.closest('[data-open-dialog]');
    if (opener) {
      const dialog = document.getElementById(opener.dataset.openDialog);
      if (dialog) {event.preventDefault();openDialog(dialog, opener);}
      return;
    }
    const closer = event.target.closest('[data-close-dialog]');
    if (closer) closer.closest('dialog')?.close();
  });
  document.querySelectorAll('dialog.catalog-dialog').forEach(dialog => {
    dialog.addEventListener('close', () => {
      if (returnFocus instanceof HTMLElement && returnFocus.isConnected) returnFocus.focus({preventScroll:true});
      returnFocus = null;
    });
    dialog.addEventListener('click', event => {
      const rect = dialog.getBoundingClientRect();
      if (event.target === dialog && (event.clientX < rect.left || event.clientX > rect.right || event.clientY < rect.top || event.clientY > rect.bottom)) dialog.close();
    });
  });
  const autoDialog = document.querySelector('dialog.catalog-dialog[data-auto-open="true"]');
  if (autoDialog) openDialog(autoDialog);

  const normalize = value => value.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().replace(/đ/g, 'd').trim();
  const arrow = next => `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="${next?'m10 6 6 6-6 6':'m14 6-6 6 6 6'}"/></svg>`;

  document.querySelectorAll('.admin-catalog').forEach(catalog => {
    const table = catalog.querySelector(':scope > .catalog-table-scroll');
    const meta = catalog.querySelector(':scope > .catalog-list-meta');
    if (table && meta) table.after(meta);
    const form = catalog.querySelector('[data-catalog-filters]');
    if (!form) return;

    const rows = [...catalog.querySelectorAll('[data-catalog-row]')];
    const search = form.querySelector('[name="catalog_q"]');
    const status = form.querySelector('[name="catalog_status"]');
    const category = form.querySelector('[name="catalog_category"]');
    const from = form.querySelector('[name="catalog_from"]');
    const to = form.querySelector('[name="catalog_to"]');
    const clear = form.querySelector('[data-clear-catalog]');
    const count = catalog.querySelector('[data-catalog-count]');
    const empty = catalog.querySelector('[data-catalog-empty]');
    const error = catalog.querySelector('[data-catalog-filter-error]');
    const noun = form.dataset.catalogNoun || 'mục';
    const paginate = form.dataset.catalogPagination !== 'false';
    let page = 1;
    let pageSize = 10;
    let previous, next, current, pageTotal, pager, pageSizeSelect;

    if (meta && paginate) {
      pager = document.createElement('div');
      pager.className = 'catalog-pagination';
      pager.setAttribute('aria-label', 'Phân trang danh sách');
      previous = document.createElement('button');
      previous.type = 'button';
      previous.setAttribute('aria-label', 'Trang trước');
      previous.innerHTML = arrow(false);
      current = document.createElement('span');
      current.className = 'catalog-page-number';
      current.setAttribute('aria-current', 'page');
      pageTotal = document.createElement('span');
      next = document.createElement('button');
      next.type = 'button';
      next.setAttribute('aria-label', 'Trang sau');
      next.innerHTML = arrow(true);
      pager.append(previous, current, pageTotal, next);
      const sizeLabel = document.createElement('label');
      sizeLabel.className = 'catalog-page-size';
      sizeLabel.append(document.createTextNode('Hiển thị'));
      pageSizeSelect = document.createElement('select');
      pageSizeSelect.setAttribute('aria-label', 'Số dòng mỗi trang');
      for (const size of [10,20,50]) {
        const option = document.createElement('option');
        option.value = String(size);option.textContent = `${size} dòng`;
        pageSizeSelect.append(option);
      }
      sizeLabel.append(pageSizeSelect);
      meta.append(pager, sizeLabel);
      previous.addEventListener('click', () => {page = Math.max(1, page - 1);filter();});
      next.addEventListener('click', () => {page++;filter();});
      pageSizeSelect.addEventListener('change', () => {pageSize = Number(pageSizeSelect.value) || 10;page = 1;filter();});
    }

    function filter() {
      const invalid = Boolean(from?.value && to?.value && from.value > to.value);
      if (clear) clear.hidden = !search?.value && !status?.value && !category?.value && !from?.value && !to?.value;
      if (error) {error.textContent = invalid ? 'Ngày kết thúc phải bằng hoặc sau ngày bắt đầu.' : '';error.hidden = !invalid;}
      from?.setAttribute('aria-invalid', String(invalid));
      to?.setAttribute('aria-invalid', String(invalid));
      if (invalid) return;
      const needle = normalize(search?.value || '');
      const matches = rows.filter(row => {
        const nameMatch = normalize(row.dataset.search || '').includes(needle);
        const stateMatch = !status?.value || row.dataset.status === status.value;
        const categoryMatch = !category?.value || row.dataset.category === category.value;
        const dateMatch = (!from?.value || !row.dataset.dateEnd || row.dataset.dateEnd >= from.value)
          && (!to?.value || !row.dataset.dateStart || row.dataset.dateStart <= to.value);
        return nameMatch && stateMatch && categoryMatch && dateMatch;
      });
      const pages = paginate ? Math.max(1, Math.ceil(matches.length / pageSize)) : 1;
      page = Math.min(page, pages);
      const offset = paginate ? (page - 1) * pageSize : 0;
      const end = paginate ? Math.min(offset + pageSize, matches.length) : matches.length;
      const displayed = new Set(matches.slice(offset, end));
      rows.forEach(row => {row.hidden = !displayed.has(row);});
      if (count) count.textContent = matches.length ? `Hiển thị ${offset + 1}–${end} / ${matches.length} ${noun}` : `0 ${noun}`;
      if (empty) empty.hidden = matches.length > 0;
      if (pager) {
        pager.hidden = pages <= 1;
        previous.disabled = page <= 1;next.disabled = page >= pages;
        current.textContent = String(page);pageTotal.textContent = `/ ${pages}`;
      }
    }
    const applyFilters = () => {page = 1;filter();};
    form.addEventListener('submit', event => {event.preventDefault();applyFilters();});
    search?.addEventListener('input', applyFilters);
    for (const control of [status, category, from, to]) control?.addEventListener('change', applyFilters);
    clear?.addEventListener('click', () => {
      for (const control of [search, status, category, from, to]) if (control) control.value = '';
      applyFilters();search?.focus();
    });
    filter();
  });

  document.querySelectorAll('[data-report-tabs]').forEach(tabs => {
    const buttons = [...tabs.querySelectorAll('[data-report-target]')];
    const activate = selected => {
      buttons.forEach(button => {
        const active = button === selected;
        button.setAttribute('aria-selected', String(active));
        const panel = document.getElementById(button.dataset.reportTarget);
        if (panel) panel.hidden = !active;
      });
    };
    buttons.forEach(button => {
      button.addEventListener('click', () => activate(button));
      button.addEventListener('keydown', event => {
        const index = buttons.indexOf(button);
        let nextIndex;
        if (event.key === 'ArrowRight') nextIndex = (index + 1) % buttons.length;
        else if (event.key === 'ArrowLeft') nextIndex = (index - 1 + buttons.length) % buttons.length;
        else if (event.key === 'Home') nextIndex = 0;
        else if (event.key === 'End') nextIndex = buttons.length - 1;
        else return;
        event.preventDefault();activate(buttons[nextIndex]);buttons[nextIndex].focus();
      });
    });
  });
})();
