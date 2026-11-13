<?php
require __DIR__ . '/includes/bootstrap.php';

$title = 'Phòng & suite';

$in = input('check_in', date('Y-m-d'));
$out = input('check_out', date('Y-m-d', strtotime('+1 day')));

$quantity = 1;
$guests = 2;
$nights = 0;
$error = '';
$rooms = [];

try {
    $quantity = integer('quantity', 1, 5);
    $guests = integer('guests', 1, 5, 2);
    $nights = valid_dates($in, $out);

    // Chỉ xét những hạng phòng có đủ sức chứa cho cả nhóm.
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
} catch (DomainException $ex) {
    $error = $ex->getMessage();
}

require __DIR__ . '/includes/header.php';
?>

<section class="page-heading">
    <div class="eyebrow">YOUR PRIVATE RETREAT</div>
    <h1>Chọn một <em>chốn riêng.</em></h1>
    <p>Tìm hạng phòng phù hợp với kỳ nghỉ của bạn.</p>
</section>

<div class="section compact">
    <?php require __DIR__ . '/includes/search.php'; ?>

    <?php if ($error !== ''): ?>
        <div class="notice error" role="alert">
            <?= e($error) ?>
        </div>
    <?php else: ?>
        <p class="result-count">
            <?= count($rooms) ?> hạng phòng phù hợp ·
            <?= $nights ?> đêm ·
            <?= $quantity ?> phòng ·
            <?= $guests ?> khách ·
            Giá đã gồm thuế và bữa sáng
        </p>

        <?php if (!$rooms): ?>
            <div class="notice" role="status">
                Không có hạng phòng nào còn đủ <?= $quantity ?> phòng
                cho <?= $guests ?> khách trong khoảng ngày đã chọn.
                Bạn có thể đổi ngày lưu trú hoặc điều chỉnh số phòng và tìm lại.
            </div>
        <?php else: ?>
            <div class="room-list">
                <?php foreach ($rooms as $room): ?>
                    <?php
                    $params = http_build_query([
                        'id'        => (int) $room['id'],
                        'check_in'  => $in,
                        'check_out' => $out,
                        'quantity'  => $quantity,
                        'guests'    => $guests,
                    ]);

                    $totalPrice = $room['price'] * $quantity * $nights;
                    ?>

                    <article class="room-row">
                        <img
                            src="<?= e(url('assets/images/' . $room['image'])) ?>"
                            alt="<?= e($room['name']) ?>"
                        >

                        <div class="room-info">
                            <div class="eyebrow">
                                <?= e($room['subtitle']) ?>
                            </div>

                            <h2><?= e($room['name']) ?></h2>

                            <p>
                                <?= e($room['area']) ?> m² ·
                                Tối đa <?= (int) $room['capacity'] ?> khách/phòng
                            </p>

                            <p><?= e($room['description']) ?></p>

                            <a
                                class="text-link"
                                href="<?= e(url('room.php?' . $params)) ?>"
                            >
                                Chi tiết &amp; tiện nghi ↗
                            </a>
                        </div>

                        <div class="room-price">
                            <span class="badge">
                                Còn <?= (int) $room['available_rooms'] ?> phòng
                            </span>

                            <h3><?= money($room['price']) ?></h3>
                            <small>mỗi phòng / đêm</small>

                            <p>
                                Tổng cho <?= $quantity ?> phòng · <?= $nights ?> đêm
                                <br>
                                <strong><?= money($totalPrice) ?></strong>
                            </p>

                            <a
                                class="button"
                                href="<?= e(url('booking.php?' . $params)) ?>"
                            >
                                CHỌN PHÒNG ↗
                            </a>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>