<?php
require __DIR__ . '/includes/bootstrap.php';

$rooms = query(
    'SELECT *
     FROM room_types
     WHERE active = 1
     ORDER BY price ASC, id ASC'
)->fetchAll();

$services = query(
    'SELECT *
     FROM services
     WHERE active = 1
     ORDER BY id ASC'
)->fetchAll();

require __DIR__ . '/includes/header.php';
?>

<style>
    .home-service-list {
        margin: 24px 0 0;
        padding: 0;
        list-style: none;
        border-top: 1px solid var(--line, #d9d2c5);
    }

    .home-service-list li {
        display: grid;
        grid-template-columns: minmax(0, 1fr) auto;
        align-items: baseline;
        gap: 12px 28px;
        padding: 18px 0;
        border-bottom: 1px solid var(--line, #d9d2c5);
    }

    .home-service-price {
        font-variant-numeric: tabular-nums;
        text-align: right;
    }

    .home-service-unit {
        font-size: 0.9em;
        font-weight: normal;
    }

    @media (max-width: 600px) {
        .home-service-list li {
            grid-template-columns: 1fr;
            gap: 4px;
        }

        .home-service-price {
            text-align: left;
        }
    }
</style>

<section class="hero">
    <img
        class="hero-image"
        src="<?= e(url('assets/images/hero.jpg')) ?>"
        alt="Không gian phòng nghỉ với chất liệu gỗ và sắc ngà"
    >

    <div class="hero-content">
        <div class="eyebrow">THE ART OF CONTRAST</div>

        <h1>Hai sắc thái.<br><em>Một chốn riêng.</em></h1>

        <p>
            Vẻ sâu lắng của Onyx. Nét thanh lịch của Ivory.<br>
            Một kỳ nghỉ mang dấu ấn của riêng bạn, giữa lòng Hà Nội.
        </p>

        <a class="button light" href="<?= e(url('rooms.php')) ?>">
            KHÁM PHÁ PHÒNG NGHỈ ↗
        </a>
    </div>

    <div class="hero-caption">
        <span>01 — A PRIVATE CITY RETREAT</span>
        <a href="#about">CUỘN ĐỂ KHÁM PHÁ ↓</a>
    </div>
</section>

<div class="search-wrap">
    <?php require __DIR__ . '/includes/search.php'; ?>
</div>

<section class="section intro" id="about">
    <div>
        <div class="eyebrow">CHÀO MỪNG ĐẾN THE ONYX &amp; IVORY</div>
        <h2>Giữa nhịp phố,<br><em>tìm một khoảng lặng.</em></h2>
    </div>

    <div>
        <p>
            Đen sâu thẳm và trắng ngà ấm áp. Hai sắc thái gặp nhau
            trong một không gian nghỉ dưỡng riêng tư giữa lòng Hà Nội.
        </p>

        <p>
            Một ban công để ngắm phố thức giấc, một góc làm việc yên tĩnh,
            hay hai phòng ngủ thông nhau để cả gia đình luôn ở gần nhau.
            Hãy chọn căn phòng phù hợp với kỳ nghỉ của bạn.
        </p>

        <a class="text-link" href="<?= e(url('rooms.php')) ?>">
            Tìm căn phòng của bạn ↗
        </a>
    </div>
</section>

<section class="section rooms-section" id="rooms">
    <div class="section-heading">
        <div>
            <div class="eyebrow">STAY IN YOUR ELEMENT</div>
            <h2>Phòng nghỉ <em>&amp; suite</em></h2>
        </div>

        <a class="text-link" href="<?= e(url('rooms.php')) ?>">
            Xem tất cả phòng ↗
        </a>
    </div>

    <?php if ($rooms): ?>
        <div class="room-grid">
            <?php foreach ($rooms as $room): ?>
                <?php require __DIR__ . '/includes/card.php'; ?>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <p>Khách sạn đang cập nhật danh mục phòng nghỉ.</p>
    <?php endif; ?>
</section>

<section class="promise">
    <span>ĐẶT PHÒNG TRỰC TIẾP</span>
    <p>
        Minh bạch giá phòng <b>✦</b>
        Bữa sáng mỗi ngày <b>✦</b>
        Chăm sóc tận tâm
    </p>
</section>

<section class="section" id="experiences">
    <div class="section-heading">
        <div>
            <div class="eyebrow">SAVOR EVERY MOMENT</div>
            <h2>Trọn vẹn <em>từng khoảnh khắc.</em></h2>
        </div>

        <a class="text-link" href="#services">Xem bảng giá dịch vụ ↓</a>
    </div>

    <div class="experience-grid">
        <article>
            <img
                src="<?= e(url('assets/images/dining.jpg')) ?>"
                alt="Không gian nhà hàng The Ivory Table"
                loading="lazy"
            >

            <div>
                <span class="eyebrow">ẨM THỰC</span>
                <h3>The Ivory Table</h3>
                <p>
                    Bắt đầu ngày mới với bữa sáng tại nhà hàng.
                    Nếu muốn dùng bữa trong phòng, bạn có thể gọi món
                    riêng qua lễ tân.
                </p>
                <small>
                    06:30–10:00 · Bữa sáng tại nhà hàng đã gồm trong giá phòng.
                    Bữa sáng gọi món tại phòng tính phí riêng.
                </small>
            </div>
        </article>

        <article>
            <img
                src="<?= e(url('assets/images/spa.jpg')) ?>"
                alt="Không gian thư giãn tại Onyx Wellness"
                loading="lazy"
            >

            <div>
                <span class="eyebrow">THƯ GIÃN</span>
                <h3>Onyx Wellness</h3>
                <p>
                    Sau một ngày dạo phố, dành cho mình một buổi spa
                    và xông hơi thảo mộc. Lễ tân sẽ hỗ trợ bạn chọn giờ.
                </p>
                <small>09:00–21:00 · Đặt lịch qua lễ tân</small>
            </div>
        </article>
    </div>
</section>

<section class="section" id="services" aria-labelledby="services-title">
    <div class="section-heading">
        <div>
            <div class="eyebrow">THE ONYX &amp; IVORY</div>
            <h2 id="services-title">Dịch vụ <em>trong kỳ nghỉ.</em></h2>
        </div>

        <p>Liên hệ lễ tân để đăng ký. Nhận phòng sớm và trả phòng muộn tùy tình trạng phòng.</p>
    </div>

    <?php if ($services): ?>
        <ul class="home-service-list">
            <?php foreach ($services as $service): ?>
                <?php
                $servicePrice = $service['price'] ?? $service['unit_price'] ?? null;
                $serviceUnit = trim((string) ($service['unit'] ?? ''));
                ?>

                <li>
                    <span><?= e($service['name']) ?></span>

                    <strong class="home-service-price">
                        <?php if ($servicePrice !== null): ?>
                            <?= money($servicePrice) ?>

                            <?php if ($serviceUnit !== ''): ?>
                                <span class="home-service-unit">
                                    / <?= e($serviceUnit) ?>
                                </span>
                            <?php endif; ?>
                        <?php else: ?>
                            Liên hệ lễ tân
                        <?php endif; ?>
                    </strong>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php else: ?>
        <p>Khách sạn đang cập nhật bảng giá dịch vụ. Bạn có thể liên hệ lễ tân để được hỗ trợ.</p>
    <?php endif; ?>
</section>

<section class="closing">
    <div class="eyebrow">YOUR NEXT CHAPTER</div>
    <h2>Một kỳ nghỉ đáng nhớ,<br><em>bắt đầu từ đây.</em></h2>

    <a class="button light" href="<?= e(url('rooms.php')) ?>">
        CHỌN NGÀY LƯU TRÚ ↗
    </a>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>