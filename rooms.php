<?php
require __DIR__ . '/includes/bootstrap.php';

$title = 'Phòng & suite';

$isSearching = input('search', '') === '1';

$in = input('check_in', date('Y-m-d'));
$out = input('check_out', date('Y-m-d', strtotime('+1 day')));

$quantity = 1;
$guests = 2;
$nights = 0;
$error = '';
$rooms = [];

try {
    if ($isSearching) {
        // Kiểm tra điều kiện tìm phòng.
        $quantity = integer('quantity', 1, 5);
        $guests = integer('guests', 1, 5, 2);
        $nights = valid_dates($in, $out);

        $roomTypes = query(
            'SELECT *
             FROM room_types
             WHERE active = 1
               AND capacity * ? >= ?
             ORDER BY price ASC, id ASC',
            [$quantity, $guests]
        )->fetchAll();

        foreach ($roomTypes as $room) {
            $availableRooms = available($room, $in, $out);

            if ($availableRooms < $quantity) {
                continue;
            }

            $room['available_rooms'] = $availableRooms;
            $rooms[] = $room;
        }
    } else {
        // Chưa tìm kiếm: hiển thị tất cả hạng phòng đang mở bán.
        $rooms = query(
            'SELECT *
             FROM room_types
             WHERE active = 1
             ORDER BY price ASC, id ASC'
        )->fetchAll();
    }
} catch (DomainException $ex) {
    $error = $ex->getMessage();
}

require __DIR__ . '/includes/header.php';
?>

<section class="page-heading">
    <div class="eyebrow">
        YOUR PRIVATE RETREAT
    </div>

    <h1>Chọn một chốn riêng</h1>

    <p>Tìm hạng phòng phù hợp với kỳ nghỉ của bạn</p>
</section>

<div class="section compact">
    <?php require __DIR__ . '/includes/search.php'; ?>

    <?php if ($error !== ''): ?>
        <div class="notice error" role="alert">
            <?= e($error) ?>
        </div>

    <?php else: ?>
        <p class="result-count" role="status">
            <?php if ($isSearching): ?>
                <?= count($rooms) ?> hạng phòng phù hợp ·
                <?= $nights ?> đêm ·
                <?= $quantity ?> phòng ·
                <?= $guests ?> khách ·
                Giá đã gồm thuế và bữa sáng
            <?php else: ?>
                Khám phá <?= count($rooms) ?> hạng phòng ·
                Chọn ngày lưu trú để kiểm tra phòng trống ·
                Giá đã gồm thuế và bữa sáng
            <?php endif; ?>
        </p>

        <?php if ($isSearching): ?>
            <p>
                <a
                    class="text-link"
                    href="<?= e(url('rooms.php')) ?>"
                >
                    Bỏ bộ lọc · Xem tất cả hạng phòng ↗
                </a>
            </p>
        <?php endif; ?>

        <?php if (!$rooms): ?>
            <div class="notice" role="status">
                <?php if ($isSearching): ?>
                    Không có hạng phòng nào còn đủ
                    <?= $quantity ?> phòng cho
                    <?= $guests ?> khách trong khoảng ngày đã chọn.
                    Bạn có thể đổi ngày lưu trú hoặc điều chỉnh
                    số phòng và tìm lại.
                <?php else: ?>
                    Hiện chưa có hạng phòng nào đang mở bán.
                    Vui lòng liên hệ lễ tân để được hỗ trợ.
                <?php endif; ?>
            </div>

        <?php else: ?>
            <div class="room-list">
                <?php foreach ($rooms as $room): ?>
                    <?php
                    $roomId = (int) $room['id'];
                    $params = ['id' => $roomId];

                    if ($isSearching) {
                        $params += [
                            'check_in'  => $in,
                            'check_out' => $out,
                            'quantity'  => $quantity,
                            'guests'    => $guests,
                        ];
                    }

                    $queryString = http_build_query($params);
                    $detailUrl = url('room.php?' . $queryString);

                    $bookingUrl = $isSearching
                        ? url('booking.php?' . $queryString)
                        : $detailUrl;

                    $imageUrl = url('assets/images/' . $room['image']);
                    ?>

                    <article class="room-row">
                        <a
                            class="room-photo-link"
                            href="<?= e($detailUrl) ?>"
                            aria-label="<?= e(
                                'Xem chi tiết phòng ' . $room['name']
                            ) ?>"
                        >
                            <img
                                src="<?= e($imageUrl) ?>"
                                alt="<?= e($room['name']) ?>"
                                loading="lazy"
                                decoding="async"
                            >
                        </a>

                        <div class="room-info">
                            <div class="eyebrow">
                                <?= e($room['subtitle'] ?? '') ?>
                            </div>

                            <h2><?= e($room['name']) ?></h2>

                            <p>
                                <?= e($room['area']) ?> m² ·
                                Tối đa
                                <?= (int) $room['capacity'] ?>
                                khách/phòng
                            </p>

                            <p>
                                <?= e($room['description'] ?? '') ?>
                            </p>

                            <a
                                class="text-link"
                                href="<?= e($detailUrl) ?>"
                            >
                                Chi tiết &amp; tiện nghi ↗
                            </a>
                        </div>

                        <div class="room-price">
                            <?php if ($isSearching): ?>
                                <span class="badge">
                                    Còn
                                    <?= (int) $room['available_rooms'] ?>
                                    phòng
                                </span>
                            <?php else: ?>
                                <span class="badge">
                                    <?= (int) $room['capacity'] ?>
                                    khách/phòng
                                </span>
                            <?php endif; ?>

                            <h3><?= money($room['price']) ?></h3>

                            <small>mỗi phòng / đêm</small>

                            <?php if ($isSearching): ?>
                                <p>
                                    Tổng cho <?= $quantity ?> phòng ·
                                    <?= $nights ?> đêm
                                    <br>
                                    <strong>
                                        <?= money(
                                            $room['price']
                                            * $quantity
                                            * $nights
                                        ) ?>
                                    </strong>
                                </p>
                            <?php else: ?>
                                <p>
                                    Đã gồm thuế và bữa sáng.
                                    Chọn ngày lưu trú để kiểm tra
                                    số phòng còn trống.
                                </p>
                            <?php endif; ?>

                            <a
                                class="button"
                                href="<?= e($bookingUrl) ?>"
                            >
                                <?= $isSearching
                                    ? 'CHỌN PHÒNG ↗'
                                    : 'XEM PHÒNG & CHỌN NGÀY ↗' ?>
                            </a>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>