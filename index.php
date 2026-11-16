<?php
require __DIR__ . '/includes/bootstrap.php';

$title = 'Một chốn riêng giữa lòng Hà Nội';

$today = date('Y-m-d');
$tomorrow = date('Y-m-d', strtotime('+1 day'));

// Danh mục phòng đang mở bán.
$rooms = query(
    'SELECT *
     FROM room_types
     WHERE active = 1
     ORDER BY price DESC, id ASC'
)->fetchAll();

// Danh mục dịch vụ đang cung cấp.
$services = query(
    'SELECT *
     FROM services
     WHERE active = 1
     ORDER BY id ASC'
)->fetchAll();

// Các ưu đãi đang có hiệu lực.
$homePromotions = query(
    'SELECT code, title, discount_percent,
            min_room_total, starts_on, ends_on
     FROM promotions
     WHERE active = 1
       AND starts_on <= ?
       AND ends_on >= ?
     ORDER BY ends_on ASC, id DESC
     LIMIT 3',
    [$today, $today]
)->fetchAll();

// Đánh giá từ những đơn đã hoàn tất.
$homeReviews = query(
    "SELECT r.rating, r.comment
     FROM booking_reviews r
     INNER JOIN bookings b ON b.id = r.booking_id
     WHERE b.status = 'completed'
       AND TRIM(r.comment) <> ''
     ORDER BY r.created_at DESC, r.booking_id DESC
     LIMIT 3"
)->fetchAll();

// Cho phép ảnh trong assets/images và các thư mục con như uploads.
$homeRoomImage = static function (array $room): string {
    $imageDirectory = realpath(__DIR__ . '/assets/images');
    $filename = str_replace(
        '\\',
        '/',
        (string) ($room['image'] ?? '')
    );

    $candidate = $imageDirectory !== false
        ? realpath($imageDirectory . '/' . $filename)
        : false;

    if (
        $imageDirectory === false
        || $candidate === false
        || !is_file($candidate)
        || !str_starts_with(
            $candidate,
            $imageDirectory . DIRECTORY_SEPARATOR
        )
    ) {
        return url('assets/images/hero.jpg');
    }

    $relativePath = str_replace(
        DIRECTORY_SEPARATOR,
        '/',
        substr($candidate, strlen($imageDirectory) + 1)
    );

    $encodedPath = implode(
        '/',
        array_map('rawurlencode', explode('/', $relativePath))
    );

    return url('assets/images/' . $encodedPath);
};

require __DIR__ . '/includes/header.php';
?>

<div class="hotel-home">

    <!-- BANNER -->
    <section class="hh-hero" aria-labelledby="home-title">
        <img
            src="<?= e(url('assets/images/hero.jpg')) ?>"
            alt="Không gian nghỉ dưỡng tại The Onyx &amp; Ivory"
            fetchpriority="high"
            decoding="async"
        >

        <div class="hh-hero-content">
            <span class="hh-kicker">
                THE ONYX &amp; IVORY · HANOI
            </span>

            <h1 id="home-title">
                <span class="hh-title-line">
                    <span class="hh-enter-down">
                        Hai sắc thái
                    </span>
                </span>

                <span class="hh-title-line">
                    <em class="hh-enter-up">
                        Một chốn riêng
                    </em>
                </span>
            </h1>

            <p>
                Một khoảng lặng giữa lòng Hà Nội.
                Nơi vẻ sâu lắng của Onyx gặp nét thanh lịch của Ivory
            </p>

            <a class="hh-button hh-button-light" href="#find-room">
                ĐẶT PHÒNG ↗
            </a>
        </div>

        <div class="hh-hero-foot">
            <span>A PRIVATE CITY RETREAT</span>

            <a href="#about">
                KHÁM PHÁ KHÁCH SẠN ↓
            </a>
        </div>
    </section>

    <!-- TÌM PHÒNG -->
    <section
        class="hh-search"
        id="find-room"
        aria-label="Tìm phòng theo ngày lưu trú"
    >
        <div class="hh-container">
            <?php require __DIR__ . '/includes/search.php'; ?>
        </div>
    </section>

    <!-- GIỚI THIỆU -->
    <section class="hh-section" id="about">
        <div class="hh-container hh-intro">
            <div class="hh-intro-photo">
                <img
                    src="<?= e(url('assets/images/onyx-heritage.png')) ?>"
                    alt="Phòng suite với không gian nghỉ ngơi và ban công"
                    loading="lazy"
                    decoding="async"
                >
            </div>

            <div class="hh-intro-copy">
                <span class="hh-kicker">
                    CHÀO MỪNG ĐẾN THE ONYX &amp; IVORY
                </span>

                <h2 class="hh-title">
                    Giữa nhịp phố,<br>
                    <em>tìm một khoảng lặng</em>
                </h2>

                <p>
                    Đen sâu thẳm và trắng ngà ấm áp.
                    Hai sắc thái gặp nhau trong một không gian
                    dành cho sự thư thái, riêng tư và những trải nghiệm
                    đáng nhớ giữa lòng Hà Nội.
                </p>

                <p>
                    Một buổi sáng thong thả bên cửa sổ,
                    một căn phòng yên tĩnh sau ngày dài,
                    hay khoảng thời gian bên những người thân yêu.
                    Mỗi kỳ nghỉ đều có câu chuyện riêng của bạn.
                </p>

                <a class="hh-link" href="<?= e(url('rooms.php')) ?>">
                    TÌM CĂN PHÒNG CỦA BẠN ↗
                </a>

                <span class="hh-signature">
                    The art of contrast.
                </span>
            </div>
        </div>
    </section>

    <!-- PHÒNG NGHỈ DẠNG CAROUSEL -->
    <section
        class="hh-section hh-room-section hh-room-showcase"
        id="rooms"
        aria-labelledby="home-rooms-title"
    >
        <div class="hh-container">
            <div class="hh-rooms-heading">
                <div>
                    <span class="hh-kicker">
                        YOUR PRIVATE RETREAT
                    </span>

                    <h2 class="hh-title" id="home-rooms-title">
                        Phòng nghỉ <em>&amp; suite</em>
                    </h2>
                </div>

                <a
                    class="hh-view-all"
                    href="<?= e(url('rooms.php')) ?>"
                >
                    XEM TẤT CẢ ↗
                </a>
            </div>

            <?php if ($rooms): ?>
                <div
                    class="hh-room-carousel"
                    data-room-carousel
                    role="region"
                    aria-roledescription="carousel"
                    aria-label="Các loại phòng nghỉ"
                >
                    <div class="hh-carousel-controls" hidden>
                        <button
                            class="hh-carousel-arrow"
                            type="button"
                            data-room-prev
                            aria-label="Xem nhóm phòng trước"
                            aria-controls="home-room-grid"
                        >
                            <span aria-hidden="true">←</span>
                        </button>

                        <span
                            class="hh-carousel-status"
                            data-room-status
                            role="status"
                            aria-live="polite"
                            aria-atomic="true"
                        ></span>

                        <button
                            class="hh-carousel-arrow"
                            type="button"
                            data-room-next
                            aria-label="Xem nhóm phòng tiếp theo"
                            aria-controls="home-room-grid"
                        >
                            <span aria-hidden="true">→</span>
                        </button>
                    </div>

                    <div class="hh-room-grid" id="home-room-grid">
                        <?php foreach ($rooms as $room): ?>
                            <?php
                            $detailUrl = url(
                                'room.php?id=' . (int) $room['id']
                            );

                            $bookingUrl = url(
                                'booking.php?' . http_build_query([
                                    'id' => (int) $room['id'],
                                    'check_in' => $today,
                                    'check_out' => $tomorrow,
                                    'quantity' => 1,
                                    'guests' => max(
                                        1,
                                        min(2, (int) $room['capacity'])
                                    ),
                                ])
                            );
                            ?>

                            <article class="hh-room">
                                <a
                                    class="hh-room-photo"
                                    href="<?= e($detailUrl) ?>"
                                >
                                    <img
                                        src="<?= e($homeRoomImage($room)) ?>"
                                        alt="<?= e($room['name']) ?>"
                                        loading="lazy"
                                        decoding="async"
                                    >
                                </a>

                                <div class="hh-room-body">
                                    <h3>
                                        <a href="<?= e($detailUrl) ?>">
                                            <?= e($room['name']) ?>
                                        </a>
                                    </h3>

                                    <div class="hh-room-specs">
                                        <span>
                                            <?= e($room['area']) ?> m²
                                        </span>

                                        <span>
                                            Tối đa
                                            <?= (int) $room['capacity'] ?>
                                            khách/phòng
                                        </span>

                                        <span>
                                            <?= e($room['bed']) ?>
                                        </span>
                                    </div>

                                    <p class="hh-room-description">
                                        <?= e($room['description']) ?>
                                    </p>

                                    <div class="hh-room-price">
                                        <strong>
                                            <?= money($room['price']) ?>
                                        </strong>

                                        <small>
                                            / phòng / đêm
                                            · Đã gồm thuế và bữa sáng
                                        </small>
                                    </div>

                                    <div class="hh-room-actions">
                                        <a
                                            class="hh-link"
                                            href="<?= e($detailUrl) ?>"
                                        >
                                            Chi tiết phòng ↗
                                        </a>

                                        <a
                                            class="hh-button"
                                            href="<?= e($bookingUrl) ?>"
                                        >
                                            ĐẶT PHÒNG ↗
                                        </a>
                                    </div>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php else: ?>
                <p>
                    Khách sạn đang cập nhật danh mục phòng nghỉ.
                </p>
            <?php endif; ?>
        </div>
    </section>

    <!-- TRẢI NGHIỆM -->
    <section class="hh-section" id="experiences">
        <div class="hh-container">
            <div class="hh-heading">
                <span class="hh-kicker">
                    SAVOR EVERY MOMENT
                </span>

                <h2 class="hh-title">
                    Trọn vẹn <em>từng khoảnh khắc.</em>
                </h2>

                <p>
                    Dành thời gian cho một bữa ăn ngon,
                    một buổi thư giãn và những niềm vui giản dị.
                </p>
            </div>

            <article class="hh-experience">
                <img
                    src="<?= e(url('assets/images/dining.jpg')) ?>"
                    alt="Không gian nhà hàng The Ivory Table"
                    loading="lazy"
                    decoding="async"
                >

                <div class="hh-experience-copy">
                    <span class="hh-kicker">
                        01 / DINING
                    </span>

                    <h3>The Ivory Table</h3>

                    <p>
                        Bắt đầu ngày mới bằng một bữa sáng thong thả
                        trong không gian ấm cúng.
                        Nếu muốn riêng tư hơn, bạn có thể yêu cầu
                        bữa sáng gọi món tại phòng qua lễ tân.
                    </p>

                    <small>
                        06:30–10:00 · Bữa sáng tại nhà hàng đã gồm
                        trong giá phòng. Gọi món tại phòng tính phí riêng.
                    </small>

                    <a class="hh-link" href="#services">
                        XEM DỊCH VỤ &amp; GIÁ ↗
                    </a>
                </div>
            </article>

            <article class="hh-experience reverse">
                <img
                    src="<?= e(url('assets/images/spa.jpg')) ?>"
                    alt="Không gian chăm sóc và thư giãn Onyx Wellness"
                    loading="lazy"
                    decoding="async"
                >

                <div class="hh-experience-copy">
                    <span class="hh-kicker">
                        02 / WELLNESS
                    </span>

                    <h3>Onyx Wellness</h3>

                    <p>
                        Khép lại một ngày khám phá thành phố
                        bằng khoảng thời gian dành cho chính mình.
                        Trải nghiệm spa và xông hơi thảo mộc
                        trong không gian yên tĩnh.
                    </p>

                    <small>
                        09:00–21:00 · Liên hệ lễ tân để đặt lịch.
                        Dịch vụ tính phí riêng.
                    </small>

                    <a class="hh-link" href="#services">
                        KHÁM PHÁ DỊCH VỤ ↗
                    </a>
                </div>
            </article>
        </div>
    </section>

    <!-- ƯU ĐÃI -->
    <?php if ($homePromotions): ?>
        <section class="hh-section hh-offers" id="offers">
            <div class="hh-container">
                <div class="hh-heading">
                    <span class="hh-kicker">
                        SPECIAL OFFERS
                    </span>

                    <h2 class="hh-title">
                        Thêm lý do <em>để ở lại</em>
                    </h2>

                    <p>
                        Các mã ưu đãi đang có hiệu lực.
                        Nhập mã tại bước đặt phòng để kiểm tra
                        điều kiện áp dụng
                    </p>
                </div>

                <div class="hh-offer-grid">
                    <?php foreach ($homePromotions as $promotion): ?>
                        <article class="hh-offer">
                            <span class="hh-offer-rate">
                                −<?= e($promotion['discount_percent']) ?>%
                            </span>

                            <h3>
                                <?= e($promotion['title']) ?>
                            </h3>

                            <p>
                                Tổng tiền phòng tối thiểu:
                                <?= money($promotion['min_room_total']) ?>.
                                Áp dụng theo điều kiện của mã
                            </p>

                            <p>
                                Mã ưu đãi:<br>
                                <code><?= e($promotion['code']) ?></code>
                            </p>

                            <p>
                                Hiệu lực đến
                                <?= e(date(
                                    'd/m/Y',
                                    strtotime($promotion['ends_on'])
                                )) ?>.
                            </p>

                            <a
                                class="hh-link"
                                href="<?= e(url('rooms.php')) ?>"
                            >
                                CHỌN PHÒNG ↗
                            </a>
                        </article>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
    <?php endif; ?>

    <!-- DỊCH VỤ -->
    <section class="hh-section" id="services">
        <div class="hh-container">
            <div class="hh-heading">
                <span class="hh-kicker">
                    THOUGHTFULLY ARRANGED
                </span>

                <h2 class="hh-title">
                    Chăm chút <em>cho kỳ nghỉ.</em>
                </h2>

                <p>
                    Từ chuyến xe sân bay đến những chuẩn bị
                    cho ngày kỷ niệm. Liên hệ lễ tân để đăng ký
                    và xác nhận thời gian sử dụng dịch vụ
                </p>
            </div>

            <?php if ($services): ?>
                <ul class="hh-service-list">
                    <?php foreach ($services as $service): ?>
                        <li>
                            <span>
                                <?= e($service['name']) ?>
                            </span>

                            <strong>
                                <?= money($service['price']) ?>

                                <?php if (!empty($service['unit'])): ?>
                                    <small>
                                        / <?= e($service['unit']) ?>
                                    </small>
                                <?php endif; ?>
                            </strong>
                        </li>
                    <?php endforeach; ?>
                </ul>

                <p style="margin-top: 24px; font-size: 13px;">
                    Nhận phòng sớm và trả phòng muộn tùy tình trạng
                    phòng thực tế. Dịch vụ phát sinh được tính riêng
                    với tiền phòng
                </p>
            <?php else: ?>
                <p class="hh-center">
                    Bảng giá dịch vụ đang được cập nhật.
                    Vui lòng liên hệ lễ tân 
                </p>
            <?php endif; ?>
        </div>
    </section>

    <!-- ĐÁNH GIÁ -->
    <?php if ($homeReviews): ?>
        <section
            class="hh-section hh-room-section"
            id="testimonials"
        >
            <div class="hh-container">
                <div class="hh-heading">
                    <span class="hh-kicker">
                        GUEST EXPERIENCES
                    </span>

                    <h2 class="hh-title">
                        Những kỳ nghỉ,<br>
                        <em>những câu chuyện</em>
                    </h2>

                    <p>
                        Chia sẻ từ khách hàng có đơn lưu trú
                        đã hoàn tất tại The Onyx &amp; Ivory
                    </p>
                </div>

                <div class="hh-review-grid">
                    <?php foreach ($homeReviews as $review): ?>
                        <?php
                        $rating = max(
                            1,
                            min(5, (int) $review['rating'])
                        );
                        ?>

                        <figure class="hh-review">
                            <div
                                class="hh-stars"
                                role="img"
                                aria-label="<?= $rating ?> trên 5 sao"
                            >
                                <span aria-hidden="true">
                                    <?= str_repeat('★', $rating) ?>
                                    <?= str_repeat('☆', 5 - $rating) ?>
                                </span>
                            </div>

                            <blockquote><?= e(
                                $review['comment']
                            ) ?></blockquote>

                            <figcaption>
                                KHÁCH ĐÃ HOÀN TẤT LƯU TRÚ
                            </figcaption>
                        </figure>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
    <?php endif; ?>

    <!-- KẾT TRANG -->
    <section class="hh-closing">
        <span class="hh-kicker">
            YOUR NEXT CHAPTER
        </span>

        <h2 class="hh-title">
            Một kỳ nghỉ đáng nhớ,<br>
            <em>bắt đầu từ đây</em>
        </h2>

        <p>
            Chọn ngày lưu trú và tìm căn phòng dành cho bạn
        </p>

        <a
            class="hh-button"
            href="<?= e(url('rooms.php')) ?>"
        >
            KIỂM TRA PHÒNG TRỐNG ↗
        </a>
    </section>

</div>

<?php require __DIR__ . '/includes/footer.php'; ?>