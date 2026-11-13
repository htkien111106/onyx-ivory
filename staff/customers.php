<?php
require __DIR__ . '/../includes/bootstrap.php';

$user = staff_required();
$title = 'Quản lý khách hàng';

$search = mb_substr(input('q'), 0, 190);
$status = input('status');

$where = '1 = 1';
$args = [];

if ($search !== '') {
    $where .= '
        AND (
            c.full_name LIKE ?
            OR c.email LIKE ?
            OR c.phone LIKE ?
        )
    ';

    $keyword = '%' . $search . '%';
    $args[] = $keyword;
    $args[] = $keyword;
    $args[] = $keyword;
}

if ($status === 'active') {
    $where .= ' AND c.active = 1';
} elseif ($status === 'locked') {
    $where .= ' AND c.active = 0';
} else {
    $status = '';
}

$total = (int) query(
    'SELECT COUNT(*) FROM customers c WHERE ' . $where,
    $args
)->fetchColumn();

$perPage = 15;
$totalPages = max(1, (int) ceil($total / $perPage));

$pageValue = input('page', '1');
$page = ctype_digit($pageValue) ? (int) $pageValue : 1;
$page = max(1, min($page, $totalPages));

$offset = ($page - 1) * $perPage;

$customers = query(
    "SELECT
        c.id,
        c.full_name,
        c.email,
        c.phone,
        c.active,
        c.created_at,
        (
            SELECT COUNT(*)
            FROM bookings b
            WHERE b.customer_id = c.id
        ) AS booking_count,
        (
            SELECT COUNT(*)
            FROM bookings b
            WHERE b.customer_id = c.id
              AND b.status = 'completed'
        ) AS completed_count
     FROM customers c
     WHERE $where
     ORDER BY c.created_at DESC, c.id DESC
     LIMIT $perPage OFFSET $offset",
    $args
)->fetchAll();

$pageUrl = static function (int $number) use ($search, $status): string {
    return url('staff/customers.php?' . http_build_query([
        'q' => $search,
        'status' => $status,
        'page' => $number
    ]));
};

require __DIR__ . '/../includes/staff_header.php';
?>

<p>
    Tra cứu hồ sơ khách hàng và lịch sử đặt phòng theo tài khoản.
</p>

<form method="get" class="filter">
    <label>
        Tìm khách hàng
        <input
            name="q"
            value="<?= e($search) ?>"
            maxlength="190"
            placeholder="Họ tên, email hoặc số điện thoại"
        >
    </label>

    <label>
        Trạng thái tài khoản
        <select name="status">
            <option value="">Tất cả</option>

            <option
                value="active"
                <?= $status === 'active' ? 'selected' : '' ?>
            >
                Đang hoạt động
            </option>

            <option
                value="locked"
                <?= $status === 'locked' ? 'selected' : '' ?>
            >
                Đã khóa
            </option>
        </select>
    </label>

    <button type="submit" class="button">
        TÌM KIẾM
    </button>

    <a
        class="text-link"
        href="<?= e(url('staff/customers.php')) ?>"
    >
        Xóa bộ lọc
    </a>
</form>

<p>Tìm thấy <strong><?= $total ?></strong> khách hàng.</p>

<div class="panel" style="overflow-x: auto;">
    <?php if (!$customers): ?>
        <p>Không tìm thấy khách hàng phù hợp.</p>
    <?php else: ?>
        <table style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr>
                    <th>Mã KH</th>
                    <th>Họ tên</th>
                    <th>Liên hệ</th>
                    <th>Số đơn</th>
                    <th>Lượt hoàn tất</th>
                    <th>Trạng thái</th>
                    <th>Thao tác</th>
                </tr>
            </thead>

            <tbody>
                <?php foreach ($customers as $row): ?>
                    <tr>
                        <td>#<?= (int) $row['id'] ?></td>

                        <td><?= e($row['full_name']) ?></td>

                        <td style="overflow-wrap: anywhere;">
                            <?= e($row['email']) ?>
                            <br>
                            <?= e($row['phone']) ?>
                        </td>

                        <td><?= (int) $row['booking_count'] ?></td>

                        <td><?= (int) $row['completed_count'] ?></td>

                        <td>
                            <?= (int) $row['active'] === 1
                                ? 'Đang hoạt động'
                                : 'Đã khóa' ?>
                        </td>

                        <td>
                            <a
                                class="text-link"
                                href="<?= e(url(
                                    'staff/customer-detail.php?id='
                                    . (int) $row['id']
                                )) ?>"
                            >
                                Xem hồ sơ →
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<nav
    aria-label="Phân trang khách hàng"
    style="display: flex; gap: 20px; flex-wrap: wrap; margin-top: 24px;"
>
    <?php if ($page > 1): ?>
        <a class="text-link" href="<?= e($pageUrl($page - 1)) ?>">
            ← Trang trước
        </a>
    <?php endif; ?>

    <span>Trang <?= $page ?> / <?= $totalPages ?></span>

    <?php if ($page < $totalPages): ?>
        <a class="text-link" href="<?= e($pageUrl($page + 1)) ?>">
            Trang sau →
        </a>
    <?php endif; ?>
</nav>

<?php require __DIR__ . '/../includes/staff_footer.php'; ?>