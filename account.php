<?php
require __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/customer.php';

$customer = customer_required();

$title = 'Tài khoản của tôi';
$errors = [];

$fullName = $customer['full_name'];
$phone = $customer['phone'];

if (post()) {
    check_csrf();

    $fullName = input('full_name');
    $phone = input('phone');

    if (mb_strlen($fullName) < 2 || mb_strlen($fullName) > 100) {
        $errors[] = 'Họ tên cần từ 2 đến 100 ký tự.';
    }

    $phoneDigits = preg_replace('/\D/', '', $phone);

    if (
        !preg_match('/^\+?[0-9][0-9 ()-]{7,23}$/', $phone)
        || strlen($phoneDigits) < 9
        || strlen($phoneDigits) > 15
    ) {
        $errors[] = 'Số điện thoại cần có từ 9 đến 15 chữ số.';
    }

    if (!$errors) {
        // Chỉ cập nhật tài khoản đang đăng nhập.
        query(
            'UPDATE customers
             SET full_name = ?, phone = ?
             WHERE id = ?',
            [$fullName, $phone, $customer['id']]
        );

        flash('Đã lưu thông tin cá nhân của bạn.');
        go('account.php');
    }
}

// Phân trang lịch sử: mỗi trang 10 đơn.
$pageValue = input('page', '1');

$page = ctype_digit($pageValue)
    ? max(1, min(100000, (int) $pageValue))
    : 1;

$perPage = 10;

$totalBookings = (int) query(
    'SELECT COUNT(*) FROM bookings WHERE customer_id = ?',
    [$customer['id']]
)->fetchColumn();

$totalPages = max(1, (int) ceil($totalBookings / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

// Chỉ lấy đơn thuộc tài khoản đang đăng nhập.
$bookings = query(
    "SELECT b.*, r.name AS room_name
     FROM bookings b
     INNER JOIN room_types r ON r.id = b.room_type_id
     WHERE b.customer_id = ?
     ORDER BY b.created_at DESC, b.id DESC
     LIMIT $perPage OFFSET $offset",
    [$customer['id']]
)->fetchAll();

$cancelError = $_SESSION['cancel_booking_error'] ?? '';
unset($_SESSION['cancel_booking_error']);

require __DIR__ . '/includes/header.php';
?>

<section class="page-heading">
    <div class="eyebrow">
        THE ONYX &amp; IVORY / TÀI KHOẢN
    </div>

    <h1>Không gian <em>của bạn.</em></h1>

    <p>Cập nhật thông tin và theo dõi những kỳ nghỉ của bạn.</p>
</section>

<section class="section narrow" id="profile">

    <div class="actions align-left">
        <a class="button outline" href="<?= e(url('membership.php')) ?>">
            THẺ THÀNH VIÊN &amp; ĐIỂM TÍCH LŨY ↗
        </a>
    </div>

    <h2>Thông tin cá nhân</h2>

    <p>
        Thông tin đã lưu sẽ được sử dụng khi bạn đặt phòng
        bằng tài khoản này.
    </p>

    <?php if ($errors): ?>
        <div class="notice error" role="alert">
            <?php foreach ($errors as $message): ?>
                <div><?= e($message) ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <form
        action="<?= e(url('account.php')) ?>"
        method="post"
        class="panel"
    >
        <?= csrf() ?>

        <label for="profile-name">
            Họ và tên *
            <input
                id="profile-name"
                name="full_name"
                value="<?= e($fullName) ?>"
                minlength="2"
                maxlength="100"
                autocomplete="name"
                required
            >
        </label>

        <label for="profile-email">
            Email tài khoản
            <input
                id="profile-email"
                type="email"
                value="<?= e($customer['email']) ?>"
                readonly
            >
        </label>

        <p>
            Email này được dùng để đăng nhập và liên hệ
            về đơn đặt phòng.
        </p>

        <label for="profile-phone">
            Số điện thoại *
            <input
                id="profile-phone"
                name="phone"
                type="tel"
                value="<?= e($phone) ?>"
                maxlength="25"
                autocomplete="tel"
                required
            >
        </label>

        <button type="submit" class="button full">
            LƯU THÔNG TIN ↗
        </button>

        <p style="margin-top: 20px; text-align: center;">
            <a
                class="text-link"
                href="<?= e(url('change-password.php')) ?>"
            >
                Đổi mật khẩu →
            </a>
        </p>
    </form>
</section>

<section class="section narrow" id="history">
    <?php if ($cancelError !== ''): ?>
        <div class="notice error" role="alert">
            <?= e($cancelError) ?>
        </div>
    <?php endif; ?>

    <h2>Lịch sử đặt phòng và lưu trú</h2>

    <p>
        Bạn có <?= $totalBookings ?> đơn đặt phòng
        được liên kết với tài khoản.
    </p>

    <?php if (!$bookings): ?>

        <div class="panel">
            <p>Bạn chưa có đơn đặt phòng nào trong tài khoản.</p>

            <a class="button" href="<?= e(url('rooms.php')) ?>">
                KHÁM PHÁ PHÒNG NGHỈ ↗
            </a>
        </div>

    <?php else: ?>

        <?php foreach ($bookings as $booking): ?>
            <?php
            $detailUrl = url(
                'booking-detail.php?id=' . (int) $booking['id']
            );
            ?>

            <article class="panel" style="margin-bottom: 24px;">
                <div class="eyebrow">
                    <?= e(status_name($booking['status'])) ?>
                </div>

                <h3>
                    <a href="<?= e($detailUrl) ?>">
                        <?= e($booking['room_name']) ?>
                    </a>
                </h3>

                <p style="overflow-wrap: anywhere;">
                    <strong>Mã đặt phòng:</strong>
                    <?= e($booking['code']) ?>
                </p>

                <p>
                    <strong>Người nhận phòng:</strong>
                    <?= e($booking['guest_name']) ?>
                </p>

                <p>
                    <strong>Nhận phòng:</strong>
                    <?= e(date(
                        'd/m/Y',
                        strtotime($booking['check_in'])
                    )) ?>
                    — 14:00
                </p>

                <p>
                    <strong>Trả phòng:</strong>
                    <?= e(date(
                        'd/m/Y',
                        strtotime($booking['check_out'])
                    )) ?>
                    — 12:00
                </p>

                <p>
                    <strong>Số lượng:</strong>
                    <?= (int) $booking['quantity'] ?> phòng ·
                    <?= (int) $booking['guests'] ?> khách
                </p>

                <p>
                    <strong>Tổng tiền đặt phòng:</strong>
                    <?= money($booking['total_price']) ?>
                </p>

                <p>
                    <strong>Ngày tạo đơn:</strong>
                    <?= e(date(
                        'd/m/Y H:i',
                        strtotime($booking['created_at'])
                    )) ?>
                </p>

                <?php if ($booking['note'] !== ''): ?>
                    <p style="overflow-wrap: anywhere;">
                        <strong>Ghi chú:</strong>
                        <?= e($booking['note']) ?>
                    </p>
                <?php endif; ?>

                <p>
                    <a class="button" href="<?= e($detailUrl) ?>">
                        XEM CHI TIẾT ĐƠN ↗
                    </a>
                </p>
            </article>
        <?php endforeach; ?>

        <nav
            aria-label="Phân trang lịch sử"
            style="display: flex; flex-wrap: wrap; gap: 20px; align-items: center;"
        >
            <?php if ($page > 1): ?>
                <a
                    class="text-link"
                    href="<?= e(url(
                        'account.php?page=' . ($page - 1) . '#history'
                    )) ?>"
                >
                    ← Trang trước
                </a>
            <?php endif; ?>

            <span>
                Trang <?= $page ?> / <?= $totalPages ?>
            </span>

            <?php if ($page < $totalPages): ?>
                <a
                    class="text-link"
                    href="<?= e(url(
                        'account.php?page=' . ($page + 1) . '#history'
                    )) ?>"
                >
                    Trang sau →
                </a>
            <?php endif; ?>
        </nav>

    <?php endif; ?>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>