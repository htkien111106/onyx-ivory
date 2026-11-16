<?php
require __DIR__ . '/includes/bootstrap.php';

$title = 'Phòng & suite';

// Đường dẫn ảnh nền đầu trang.
// Lấy ảnh phòng có sẵn, không phụ thuộc kết quả tìm kiếm.
$heroRoom = query(
    "SELECT image
     FROM room_types
     WHERE active = 1
       AND image IS NOT NULL
       AND image <> ''
     ORDER BY price ASC, id ASC
     LIMIT 1"
)->fetch();

$heroImage = $heroRoom
    ? url('assets/images/' . $heroRoom['image'])
    : '';

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
        // Mới mở trang: hiển thị toàn bộ hạng phòng đang mở bán.
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

<section class="rooms-hero" aria-labelledby="rooms-hero-title">
    <?php if ($heroImage !== ''): ?>
        <img
            class="rooms-hero-image"
            src="<?= e($heroImage) ?>"
            alt=""
            fetchpriority="high"
            decoding="async"
        >
    <?php endif; ?>

    <div class="rooms-hero-content">
        <div class="eyebrow">
            YOUR PRIVATE RETREAT
        </div>

        <h1 id="rooms-hero-title">Phòng nghỉ &amp; suite</h1>

        <p>Chọn một chốn riêng cho kỳ nghỉ của bạn</p>

        <a class="rooms-hero-button" href="#rooms-search">
            Khám phá các hạng phòng ↓
        </a>
    </div>
</section>

<div class="section compact" id="rooms-search">
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
                    href="<?= e(url('rooms.php#rooms-search')) ?>"
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

                    $imageUrl = url(
                        'assets/images/' . $room['image']
                    );
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

<script>
(() => {
    const body = document.body;

    if (
        !body.classList.contains('page-rooms') ||
        body.classList.contains('staff-page') ||
        body.dataset.roomsHeaderReady === 'true'
    ) {
        return;
    }

    const header = document.querySelector('.site-header');
    const searchSection = document.getElementById('rooms-search');

    if (!header) return;

    body.dataset.roomsHeaderReady = 'true';

    let scheduled = false;

    function updateHeader() {
        header.classList.toggle(
            'is-scrolled',
            window.scrollY > 40
        );

        scheduled = false;
    }

    window.addEventListener('scroll', () => {
        if (scheduled) return;

        scheduled = true;
        window.requestAnimationFrame(updateHeader);
    }, { passive: true });

    window.addEventListener('pageshow', updateHeader);

    updateHeader();

    // Khi tìm phòng, đưa khách đến phần kết quả
    // thay vì phải cuộn qua ảnh nền thêm lần nữa.
    const isSearching = <?= $isSearching ? 'true' : 'false' ?>;

    if (isSearching && !window.location.hash && searchSection) {
        const showResults = () => {
            searchSection.scrollIntoView({
                behavior: 'instant',
                block: 'start'
            });

            updateHeader();
        };

        if (document.readyState === 'complete') {
            showResults();
        } else {
            window.addEventListener('load', showResults, {
                once: true
            });
        }
    }
})();
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>