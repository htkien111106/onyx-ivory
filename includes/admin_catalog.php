<?php
// Các thành phần trình bày dùng chung. Không ghi dữ liệu hoặc thay đổi phân quyền.
function catalog_icon(string $name): string
{
    $paths = [
        'eye' => '<path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6S2 12 2 12Z"/><circle cx="12" cy="12" r="2.5"/>',
        'edit' => '<path d="m15 5 4 4M4 20l4-1 12-12a2.1 2.1 0 0 0-3-3L5 16l-1 4Z"/>',
        'trash' => '<path d="M4 7h16M9 7V4h6v3M6 7l1 13h10l1-13M10 11v5M14 11v5"/>',
        'power' => '<path d="M3 11a9 9 0 1 1 2.6 7M3 4v7h7"/>',
        'close' => '<path d="m6 6 12 12M6 18 18 6"/>',
        'search' => '<circle cx="10.5" cy="10.5" r="6.5"/><path d="m16 16 5 5"/>',
        'arrow' => '<path d="M4 12h15m-6-6 6 6-6 6"/>',
        'plus' => '<path d="M12 5v14M5 12h14"/>',
        'previous' => '<path d="m14 6-6 6 6 6"/>',
        'next' => '<path d="m10 6 6 6-6 6"/>',
        'menu' => '<path d="M4 6h16M4 12h16M4 18h16"/>',
        'report' => '<path d="M4 3v18h17M8 16v-5M13 16V8M18 16V5"/>',
        'members' => '<circle cx="9" cy="8" r="3"/><path d="M3 21v-3a6 6 0 0 1 12 0v3M16 5a3 3 0 0 1 0 6M21 21v-3a6 6 0 0 0-4-5"/>',
        'reviews' => '<path d="M21 11a8 8 0 0 1-8 8H5l-4 3V11a8 8 0 0 1 8-8h4a8 8 0 0 1 8 8ZM6 9h9M6 13h6"/>',
        'types' => '<path d="M3 18v3M21 18v3M3 10V6a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v4M2 11h20v7H2zM6 7h4M14 7h4"/>',
        'promotions' => '<path d="M4 4h16v5a3 3 0 0 0 0 6v5H4v-5a3 3 0 0 0 0-6V4ZM9 15l6-6"/><circle cx="9" cy="9" r=".5"/><circle cx="15" cy="15" r=".5"/>',
        'services' => '<path d="M3 17h18M5 14a7 7 0 0 1 14 0H5ZM12 5V3M10 3h4M3 21h18"/>',
        'rooms' => '<path d="M4 21h16M7 21V3l11 2v16M7 3h-2v18M14 12h.01"/>',
        'employees' => '<rect x="4" y="4" width="16" height="18" rx="2"/><path d="M9 4V2h6v2M8 18a4 4 0 0 1 8 0"/><circle cx="12" cy="11" r="3"/>',
        'audit' => '<rect x="5" y="4" width="14" height="18" rx="2"/><path d="M9 2h6v4H9zM9 11h6M9 15h6M9 19h4"/>',
        'account' => '<circle cx="12" cy="12" r="9"/><circle cx="12" cy="9" r="3"/><path d="M6 18a6 6 0 0 1 12 0"/>',
        'logout' => '<path d="M9 4H4v16h5M8 12h13m-4-4 4 4-4 4"/>',
    ];
    return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . ($paths[$name] ?? $paths['eye']) . '</svg>';
}

function catalog_action(string $icon, string $label, string $target, bool $danger = false, bool $disabled = false): void
{
    $class = 'catalog-icon-button' . ($danger ? ' is-danger' : '');
    $content = catalog_icon($icon) . ($icon === 'eye' ? '<span>Xem</span>' : '');
    if (str_starts_with($target, '#')) {
        echo '<button type="button" class="' . $class . '" data-open-dialog="' . e(substr($target, 1)) . '" title="' . e($label) . '" aria-label="' . e($label) . '"' . ($disabled ? ' disabled' : '') . '>' . $content . '</button>';
    } else {
        echo '<a class="' . $class . '" href="' . e($target) . '" title="' . e($label) . '" aria-label="' . e($label) . '">' . $content . '</a>';
    }
}

function catalog_hidden(array $fields): void
{
    foreach ($fields as $name => $value) {
        echo '<input type="hidden" name="' . e($name) . '" value="' . e($value) . '">';
    }
}

function catalog_toolbar(string $label, int $count, string $placeholder, string $addTarget = '', bool $dates = false, array $options = []): void
{
    $kinds = ['Tìm loại phòng'=>'types','Tìm dịch vụ'=>'services','Tìm khuyến mãi'=>'promotions','Tìm phòng'=>'rooms','Tìm nhân viên'=>'users','Tìm nhật ký'=>'audit'];
    $kind = $options['kind'] ?? ($kinds[$label] ?? '');
    $names = ['types'=>['Thêm loại phòng','loại phòng'],'services'=>['Thêm dịch vụ','dịch vụ'],'promotions'=>['Tạo khuyến mãi','khuyến mãi'],'rooms'=>['Thêm phòng','phòng'],'users'=>['Thêm nhân viên','nhân viên'],'audit'=>['','thao tác']];
    [$addLabel,$noun] = $names[$kind] ?? ['Thêm mới','mục'];
    $states = [
        'types'=>['active'=>'Đang mở bán','inactive'=>'Ngừng bán'],
        'services'=>['active'=>'Đang cung cấp','inactive'=>'Ngừng cung cấp'],
        'promotions'=>['active'=>'Đang áp dụng','upcoming'=>'Sắp bắt đầu','expired'=>'Đã hết hạn','inactive'=>'Tạm dừng'],
        'rooms'=>['ready'=>'Sẵn sàng','dirty'=>'Chờ dọn','maintenance'=>'Bảo trì','inactive'=>'Ngừng sử dụng'],
        'users'=>['active'=>'Hoạt động','inactive'=>'Đã khóa'],
    ][$kind] ?? [];
    $categories = $options['categories'] ?? ($kind === 'users' ? [['admin','Quản trị viên'],['receptionist','Staff / Lễ tân']] : []);
    ?>
    <div class="catalog-tools<?= $dates ? ' catalog-tools-dates' : '' ?>">
        <form method="get" class="catalog-filters" data-catalog-filters data-catalog-kind="<?= e($kind) ?>" data-catalog-noun="<?= e($noun) ?>" data-catalog-pagination="<?= ($options['paginate'] ?? true) ? 'true' : 'false' ?>">
            <label class="catalog-search"><span class="catalog-filter-label"><?= e($label) ?></span>
                <span class="catalog-search-field"><input type="search" name="catalog_q" value="<?= e(input('catalog_q')) ?>" placeholder="<?= e($placeholder) ?>" autocomplete="off" maxlength="190"></span>
            </label>
            <?php if ($states): ?><label class="catalog-choice"><span class="catalog-filter-label">Lọc trạng thái</span><select name="catalog_status"><option value="">Tất cả trạng thái</option><?php foreach ($states as $value=>$text): ?><option value="<?= e($value) ?>" <?= input('catalog_status')===$value?'selected':'' ?>><?= e($text) ?></option><?php endforeach; ?></select></label><?php endif; ?>
            <?php if ($categories): ?><label class="catalog-choice"><span class="catalog-filter-label"><?= $kind==='rooms'?'Lọc loại phòng':'Lọc vai trò' ?></span><select name="catalog_category"><option value=""><?= $kind==='rooms'?'Tất cả loại phòng':'Tất cả vai trò' ?></option><?php foreach ($categories as [$value,$text]): ?><option value="<?= e($value) ?>" <?= input('catalog_category')===(string)$value?'selected':'' ?>><?= e($text) ?></option><?php endforeach; ?></select></label><?php endif; ?>
            <?php if ($dates): ?>
                <label class="catalog-date-choice">Từ ngày<input type="date" name="catalog_from" value="<?= e(input('catalog_from')) ?>"></label>
                <label class="catalog-date-choice">Đến ngày<input type="date" name="catalog_to" value="<?= e(input('catalog_to')) ?>"></label>
            <?php endif; ?>
            <button class="button catalog-filter-submit" type="submit" aria-label="Tìm kiếm" title="Tìm kiếm"><?= catalog_icon('search') ?></button>
            <button type="button" class="catalog-clear" data-clear-catalog>Xóa lọc</button>
        </form>
        <?php if ($addTarget !== ''): ?>
            <?php if (str_starts_with($addTarget, '#')): ?>
                <button type="button" class="catalog-add" data-open-dialog="<?= e(substr($addTarget, 1)) ?>"><?= catalog_icon('plus') ?><span><?= e($addLabel) ?></span></button>
            <?php else: ?>
                <a class="catalog-add" href="<?= e($addTarget) ?>"><?= catalog_icon('plus') ?><span><?= e($addLabel) ?></span></a>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    <div class="catalog-list-meta"><span data-catalog-count aria-live="polite"><?= $count ?> <?= e($noun) ?></span></div>
    <p class="catalog-filter-error" role="alert" data-catalog-filter-error hidden></p>
    <?php
}

function catalog_status(string $text, bool $active, string $tone = ''): void
{
    echo '<span class="catalog-status ' . ($tone==='pending'?'is-pending':($active ? 'is-active' : 'is-inactive')) . '">' . e($text) . '</span>';
}

function catalog_dialog_start(string $id, string $title, string $subtitle = '', bool $auto = false, bool $small = false): void
{
    ?>
    <dialog class="catalog-dialog<?= $small ? ' catalog-dialog-small' : '' ?>" id="<?= e($id) ?>" aria-labelledby="<?= e($id) ?>-title" data-auto-open="<?= $auto ? 'true' : 'false' ?>">
        <div class="catalog-dialog-heading"><div><h2 id="<?= e($id) ?>-title"><?= e($title) ?></h2><?php if ($subtitle !== ''): ?><p><?= e($subtitle) ?></p><?php endif; ?></div><button type="button" class="catalog-dialog-close" data-close-dialog aria-label="Đóng cửa sổ"><?= catalog_icon('close') ?></button></div>
        <div class="catalog-dialog-body">
    <?php
}

function catalog_dialog_end(): void { echo '</div></dialog>'; }

function catalog_form_actions(string $submitLabel, bool $danger = false): void
{
    echo '<div class="catalog-dialog-actions"><button type="button" class="button outline" data-close-dialog>Hủy</button><button type="submit" class="button' . ($danger ? ' catalog-danger-submit' : '') . '">' . e($submitLabel) . '</button></div>';
}

function catalog_error(string $error): void
{
    if ($error !== '') echo '<div class="notice error" role="alert">' . e($error) . '</div>';
}

function catalog_details(array $fields): void
{
    echo '<dl class="catalog-details">';
    foreach ($fields as $label => $value) echo '<dt>' . e($label) . '</dt><dd>' . nl2br(e($value)) . '</dd>';
    echo '</dl>';
}

function catalog_empty(int $columns, bool $hasRows): void
{
    echo '<tr class="catalog-empty" data-catalog-empty' . ($hasRows ? ' hidden' : '') . '><td colspan="' . $columns . '">Không có dữ liệu phù hợp. Bạn có thể xóa bộ lọc hoặc bấm + để thêm mới.</td></tr>';
}
