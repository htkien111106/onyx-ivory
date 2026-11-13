<?php
require __DIR__ . '/../includes/bootstrap.php';

$user = staff_required();
$title = 'Hồ sơ khách hàng';
$error = '';

$idValue = input('id');
$customerId = ctype_digit($idValue) ? (int) $idValue : 0;

// Chặn lễ tân gửi yêu cầu thay đổi trạng thái,
// kể cả khi tự tạo biểu mẫu hoặc sửa HTML.
if (post()) {
    admin_required();
    check_csrf();

    $action = input('action');
    $versionValue = input('session_version');

    if (
        $customerId < 1
        || !in_array($action, ['lock', 'unlock'], true)
        || !ctype_digit($versionValue)
    ) {
        $error = 'Yêu cầu không hợp lệ.';
    } else {
        try {
            db()->beginTransaction();

            $current = query(
                'SELECT id, active, session_version
                 FROM customers
                 WHERE id = ?
                 FOR UPDATE',
                [$customerId]
            )->fetch();

            if (!$current) {
                throw new DomainException(
                    'Không tìm thấy tài khoản khách hàng.'
                );
            }

            // Tránh ghi đè thao tác vừa được thực hiện ở cửa sổ khác.
            if (
                (int) $current['session_version']
                !== (int) $versionValue
            ) {
                throw new DomainException(
                    'Tài khoản vừa được cập nhật. '
                    . 'Vui lòng kiểm tra trạng thái mới và thao tác lại.'
                );
            }

            $newActive = $action === 'unlock' ? 1 : 0;

            if ((int) $current['active'] === $newActive) {
                throw new DomainException(
                    'Tài khoản đã ở trạng thái này.'
                );
            }

            query(
                'UPDATE customers
                 SET active = ?,
                     session_version = session_version + 1
                 WHERE id = ?',
                [$newActive, $customerId]
            );

            audit(
                $action === 'lock'
                    ? 'customer.lock'
                    : 'customer.unlock',
                ($action === 'lock'
                    ? 'Khóa tài khoản khách hàng #'
                    : 'Mở tài khoản khách hàng #'
                ) . $customerId
            );

            db()->commit();

            flash(
                $newActive === 1
                    ? 'Đã mở tài khoản khách hàng.'
                    : 'Đã khóa tài khoản khách hàng. Các phiên đăng nhập cũ đã mất hiệu lực.'
            );

            go('staff/customer-detail.php?id=' . $customerId);

        } catch (DomainException $ex) {
            if (db()->inTransaction()) {
                db()->rollBack();
            }

            $error = $ex->getMessage();

        } catch (Throwable $ex) {
            if (db()->inTransaction()) {
                db()->rollBack();
            }

            error_log((string) $ex);

            $error =
                'Chưa thể cập nhật trạng thái tài khoản. Vui lòng thử lại.';
        }
    }
}

// Không truy vấn hoặc hiển thị mật khẩu của khách hàng.
$customer = query(
    'SELECT id, full_name, email, phone, active,
            session_version, created_at
     FROM customers
     WHERE id = ?
     LIMIT 1',
    [$customerId]
)->fetch();

if (!$customer) {
    http_response_code(404);

    require __DIR__ . '/../includes/staff_header.php';
    ?>
    <div class="panel">
        <p>Không tìm thấy khách hàng.</p>

        <a
            class="text-link"
            href="<?= e(url('staff/customers.php')) ?>"
        >
            ← Quay lại danh sách
        </a>
    </div>
    <?php
    require __DIR__ . '/../includes/staff_footer.php';
    exit;
}

$stats = query(
    "SELECT
        COUNT(*) AS total,
        COALESCE(SUM(status = 'completed'), 0) AS completed,
        COALESCE(SUM(status = 'checked_in'), 0) AS staying,
        COALESCE(SUM(status = 'cancelled'), 0) AS cancelled
     FROM bookings
     WHERE customer_id = ?",
    [$customerId]
)->fetch();

$total = (int) $stats['total'];
$perPage = 10;
$totalPages = max(1, (int) ceil($total / $perPage));

$pageValue = input('page', '1');
$page = ctype_digit($pageValue) ? (int) $pageValue : 1;
$page = max(1, min($page, $totalPages));
$offset = ($page - 1) * $perPage;

$bookings = query(
    "SELECT b.*, r.name AS room_name
     FROM bookings b
     INNER JOIN room_types r ON r.id = b.room_type_id
     WHERE b.customer_id = ?
     ORDER BY b.created_at DESC, b.id DESC
     LIMIT $perPage OFFSET $offset",
    [$customerId]
)->fetchAll();

$isAdmin = $user['role'] === 'admin';

$formatDate = static function ($value, bool $withTime = false): string {
    if (!$value) {
        return 'Chưa ghi nhận';
    }

    return date(
        $withTime ? 'd/m/Y H:i' : 'd/m/Y',
        strtotime($value)
    );
};

$pageUrl = static function (int $number) use ($customerId): string {
    return url(
        'staff/customer-detail.php?'
        . http_build_query([
            'id' => $customerId,
            'page' => $number
        ])
        . '#customer-history'
    );
};

require __DIR__ . '/../includes/staff_header.php';
?>

<p>
    <a
        class="text-link"
        href="<?= e(url('staff/customers.php')) ?>"
    >
        ← Quay lại danh sách khách hàng
    </a>
</p>

<div class="stats">
    <div>
        <span>Tổng đơn đặt phòng</span>
        <strong><?= $total ?></strong>
    </div>

    <div>
        <span>Lượt lưu trú hoàn tất</span>
        <strong><?= (int) $stats['completed'] ?></strong>
    </div>

    <div>
        <span>Đơn đang lưu trú</span>
        <strong><?= (int) $stats['staying'] ?></strong>
    </div>

    <div>
        <span>Đơn đã hủy</span>
        <strong><?= (int) $stats['cancelled'] ?></strong>
    </div>
</div>

<div class="panel">
    <div class="eyebrow">
        KHÁCH HÀNG #<?= (int) $customer['id'] ?>
    </div>

    <h2><?= e($customer['full_name']) ?></h2>

    <p style="overflow-wrap: anywhere;">
        <strong>Email:</strong>
        <?= e($customer['email']) ?>
    </p>

    <p>
        <strong>Số điện thoại:</strong>
        <?= e($customer['phone']) ?>
    </p>

    <p>
        <strong>Ngày đăng ký:</strong>
        <?= e($formatDate($customer['created_at'], true)) ?>
    </p>

    <p>
        <strong>Trạng thái tài khoản:</strong>
        <?= (int) $customer['active'] === 1
            ? 'Đang hoạt động'
            : 'Đã khóa' ?>
    </p>

    <?php if ($isAdmin): ?>
        <hr>

        <h3>Quản lý trạng thái tài khoản</h3>

        <p>
            Khóa tài khoản sẽ ngăn khách đăng nhập và làm mất hiệu lực
            các phiên đăng nhập cũ. Thao tác này không xóa hồ sơ,
            không hủy các đơn đặt phòng đang có.
        </p>

        <form
            action="<?= e(url('staff/customer-detail.php')) ?>"
            method="post"
            data-confirm="<?= e(
                (int) $customer['active'] === 1
                    ? 'Bạn muốn khóa tài khoản khách hàng này? Các đơn đặt phòng vẫn được giữ nguyên.'
                    : 'Bạn muốn mở lại tài khoản khách hàng này?'
            ) ?>"
        >
            <?= csrf() ?>

            <input
                type="hidden"
                name="id"
                value="<?= (int) $customer['id'] ?>"
            >

            <input
                type="hidden"
                name="session_version"
                value="<?= (int) $customer['session_version'] ?>"
            >

            <input
                type="hidden"
                name="action"
                value="<?= (int) $customer['active'] === 1
                    ? 'lock'
                    : 'unlock' ?>"
            >

            <button type="submit" class="button outline">
                <?= (int) $customer['active'] === 1
                    ? 'KHÓA TÀI KHOẢN'
                    : 'MỞ TÀI KHOẢN' ?>
            </button>
        </form>
    <?php endif; ?>
</div>

<div
    class="panel"
    id="customer-history"
    style="margin-top: 28px;"
>
    <h2>Lịch sử đặt phòng và lưu trú</h2>

    <p>
        Hiển thị các đơn đã được liên kết với tài khoản này.
        Đơn vãng lai không tự gộp chỉ vì trùng email.
    </p>

    <?php if (!$bookings): ?>
        <p>Khách hàng chưa có đơn đặt phòng liên kết với tài khoản.</p>
    <?php else: ?>
        <?php foreach ($bookings as $booking): ?>
            <article
                style="padding: 22px 0; border-bottom: 1px solid #ddd;"
            >
                <div class="eyebrow">
                    <?= e(status_name($booking['status'])) ?>
                </div>

                <h3><?= e($booking['room_name']) ?></h3>

                <p style="overflow-wrap: anywhere;">
                    <strong>Mã đơn:</strong>
                    <?= e($booking['code']) ?>
                </p>

                <p>
                    <strong>Người nhận phòng:</strong>
                    <?= e($booking['guest_name']) ?>
                </p>

                <p>
                    <strong>Thời gian dự kiến:</strong>
                    <?= e($formatDate($booking['check_in'])) ?>
                    —
                    <?= e($formatDate($booking['check_out'])) ?>
                </p>

                <p>
                    <strong>Nhận phòng thực tế:</strong>
                    <?= e($formatDate($booking['actual_check_in'], true)) ?>
                </p>

                <p>
                    <strong>Trả phòng thực tế:</strong>
                    <?= e($formatDate($booking['actual_check_out'], true)) ?>
                </p>

                <p>
                    <strong>Số lượng:</strong>
                    <?= (int) $booking['quantity'] ?> phòng ·
                    <?= (int) $booking['guests'] ?> khách
                </p>

                <p>
                    <strong>Tổng tiền phòng:</strong>
                    <?= money($booking['total_price']) ?>
                </p>

                <a
                    class="text-link"
                    href="<?= e(url(
                        'reception/booking.php?id='
                        . (int) $booking['id']
                    )) ?>"
                >
                    Xem và xử lý đơn →
                </a>
            </article>
        <?php endforeach; ?>

        <nav
            aria-label="Phân trang lịch sử"
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
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../includes/staff_footer.php'; ?>