<?php
require __DIR__ . '/includes/bootstrap.php';

$room = query(
    'SELECT *
     FROM room_types
     WHERE id = ? AND active = 1',
    [input('id')]
)->fetch();

if (!$room) {
    http_response_code(404);
    $title = 'Không tìm thấy phòng';

    require __DIR__ . '/includes/header.php';
    ?>
    <section class="section">
        <h1>Không tìm thấy phòng</h1>
        <p>Hạng phòng này hiện chưa mở bán.</p>

        <a class="text-link" href="<?= e(url('rooms.php')) ?>">
            Xem các hạng phòng đang mở bán ↗
        </a>
    </section>
    <?php
    require __DIR__ . '/includes/footer.php';
    exit;
}

$services = query(
    'SELECT *
     FROM services
     WHERE active = 1
     ORDER BY id ASC'
)->fetchAll();

$amenitiesText = trim((string) ($room['amenities'] ?? ''));

if ($amenitiesText === '') {
    $amenitiesText = "Wi-Fi miễn phí\nĐiều hòa\nSmart TV\nPhòng tắm riêng\n"
        . "Két an toàn\nTrà & cà phê\nBàn làm việc\nBữa sáng hằng ngày";
}

$amenities = preg_split(
    '/\R/u',
    $amenitiesText,
    -1,
    PREG_SPLIT_NO_EMPTY
) ?: [];

// Giữ ngày từ trang tìm phòng nếu hợp lệ.
$today = new DateTimeImmutable('today');

$readDate = static function ($value): ?DateTimeImmutable {
    if (
        !is_string($value)
        || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)
    ) {
        return null;
    }

    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

    return $date && $date->format('Y-m-d') === $value
        ? $date
        : null;
};

$checkIn = $readDate(input('check_in', '')) ?? $today;

if ($checkIn < $today) {
    $checkIn = $today;
}

$firstCheckOut = $checkIn->modify('+1 day');
$checkOut = $readDate(input('check_out', '')) ?? $firstCheckOut;

if ($checkOut < $firstCheckOut) {
    $checkOut = $firstCheckOut;
}

$capacity = (int) $room['capacity'];
$quantity = max(1, min(5, (int) input('quantity', '1')));
$guestLimit = min(20, $capacity * $quantity);

$guests = max(
    1,
    (int) input('guests', (string) min(2, $capacity))
);

$title = $room['name'];

require __DIR__ . '/includes/header.php';
?>

<section class="detail-hero">
    <img
        src="<?= e(url('assets/images/' . $room['image'])) ?>"
        alt="<?= e($room['name']) ?>"
    >

    <div>
        <div class="eyebrow">
            <?= e($room['subtitle'] ?? '') ?>
        </div>

        <h1><?= e($room['name']) ?></h1>

        <a
            class="room-back-link"
            href="<?= e(url('rooms.php')) ?>"
        >
            <span aria-hidden="true">←</span>
            Quay lại danh sách phòng
        </a>
    </div>
</section>

<section class="section detail-grid">
    <div>
        <div class="eyebrow">
            A SPACE TO CALL YOUR OWN
        </div>

        <h2>Thoải mái trong từng chi tiết</h2>

        <p><?= e($room['description']) ?></p>

        <div class="specs">
            <span>
                <strong><?= e($room['area']) ?> m²</strong>
                Diện tích
            </span>

            <span>
                <strong><?= $capacity ?> khách</strong>
                Sức chứa mỗi phòng
            </span>

            <span>
                <strong><?= e($room['bed']) ?></strong>
                Loại giường
            </span>
        </div>

        <h3>Tiện nghi phòng</h3>

        <ul class="amenities">
            <?php foreach ($amenities as $item): ?>
                <?php if (trim($item) !== ''): ?>
                    <li><?= e(trim($item)) ?></li>
                <?php endif; ?>
            <?php endforeach; ?>
        </ul>

        <?php if ($services): ?>
            <h3>Dịch vụ theo yêu cầu</h3>

            <p>
                Bạn có thể đăng ký dịch vụ qua lễ tân.
                Bữa sáng tại nhà hàng đã gồm trong giá phòng.
                Bữa sáng gọi món phục vụ tại phòng tính phí riêng.
            </p>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th scope="col">Dịch vụ</th>
                            <th scope="col">Đơn giá</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php foreach ($services as $service): ?>
                            <?php
                            $price = $service['price']
                                ?? $service['unit_price']
                                ?? null;

                            $unit = trim(
                                (string) ($service['unit'] ?? '')
                            );
                            ?>

                            <tr>
                                <td><?= e($service['name']) ?></td>

                                <td>
                                    <?php if ($price !== null): ?>
                                        <?= money($price) ?>

                                        <?php if ($unit !== ''): ?>
                                            / <?= e($unit) ?>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        Liên hệ lễ tân
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <h3>Thông tin lưu trú</h3>

        <p>
            Nhận phòng từ <strong>14:00</strong>,
            trả phòng trước <strong>12:00</strong>.
            Nếu muốn nhận phòng sớm hoặc trả phòng muộn,
            vui lòng liên hệ lễ tân để kiểm tra tình trạng phòng.
        </p>

        <p>
            Phòng không hút thuốc. Khi đặt phòng, vui lòng tính cả trẻ em
            vào tổng số khách. Khách sạn chưa cung cấp giường phụ.
        </p>

        <a class="text-link" href="<?= e(url('policies.php')) ?>">
            Xem chính sách đặt phòng ↗
        </a>
    </div>

    <aside class="panel">
        <div class="eyebrow">ĐẶT TRỰC TIẾP</div>

        <h2><?= money($room['price']) ?></h2>

        <p>/ phòng / đêm · Đã gồm thuế và bữa sáng tại nhà hàng</p>

        <form
            id="room-booking-form"
            action="<?= e(url('booking.php')) ?>"
            method="get"
            data-capacity="<?= $capacity ?>"
        >
            <input
                type="hidden"
                name="id"
                value="<?= (int) $room['id'] ?>"
            >

            <label>
                Ngày nhận phòng
                <input
                    type="date"
                    name="check_in"
                    min="<?= $today->format('Y-m-d') ?>"
                    value="<?= $checkIn->format('Y-m-d') ?>"
                    required
                >
            </label>

            <label>
                Ngày trả phòng
                <input
                    type="date"
                    name="check_out"
                    min="<?= $firstCheckOut->format('Y-m-d') ?>"
                    value="<?= $checkOut->format('Y-m-d') ?>"
                    required
                >
            </label>

            <div class="form-grid">
                <label>
                    Số phòng
                    <input
                        type="number"
                        name="quantity"
                        min="1"
                        max="5"
                        step="1"
                        value="<?= $quantity ?>"
                        required
                    >
                </label>

                <label>
                    Tổng số khách
                    <input
                        type="number"
                        name="guests"
                        min="1"
                        max="<?= $guestLimit ?>"
                        step="1"
                        value="<?= $guests ?>"
                        aria-describedby="guest-limit"
                        required
                    >
                </label>
            </div>

            <p id="guest-limit" aria-live="polite">
                Với <?= $quantity ?> phòng, bạn có thể đặt tối đa
                <?= $guestLimit ?> khách.
            </p>

            <button type="submit" class="button full">
                KIỂM TRA &amp; ĐẶT PHÒNG ↗
            </button>
        </form>
    </aside>
</section>

<script>
(() => {
    "use strict";

    const form = document.getElementById('room-booking-form');

    if (!form) return;

    const checkIn = form.elements.namedItem('check_in');
    const checkOut = form.elements.namedItem('check_out');
    const quantity = form.elements.namedItem('quantity');
    const guests = form.elements.namedItem('guests');
    const guestHint = document.getElementById('guest-limit');
    const capacity = Number(form.dataset.capacity);

    function syncDates() {
        const nextDay = checkIn.valueAsDate;

        if (!nextDay || !checkIn.validity.valid) return;

        nextDay.setUTCDate(nextDay.getUTCDate() + 1);

        const minimum = nextDay.toISOString().slice(0, 10);

        checkOut.min = minimum;

        if (!checkOut.value || checkOut.value < minimum) {
            checkOut.value = minimum;
        }
    }

    function syncGuestLimit() {
        if (!quantity.validity.valid) return;

        const count = quantity.valueAsNumber;

        if (!Number.isInteger(count)) return;

        const limit = Math.min(20, capacity * count);

        guests.max = String(limit);

        guestHint.textContent =
            `Với ${count} phòng, bạn có thể đặt tối đa ${limit} khách.`;
    }

    checkIn.addEventListener('change', syncDates);
    quantity.addEventListener('input', syncGuestLimit);

    syncDates();
    syncGuestLimit();
})();
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>