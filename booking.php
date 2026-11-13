<?php
require __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/customer.php';
require_once __DIR__ . '/includes/promotions.php';

$bookingCustomer = current_customer();

$title = 'Đặt phòng';
$error = '';
$room = null;
$nights = 0;
$hasAvailability = false;

$today = date('Y-m-d');
$tomorrow = date('Y-m-d', strtotime('+1 day'));

$in = input('check_in', $today);
$out = input('check_out', $tomorrow);
$quantity = 1;
$guests = 2;

$name = (string) (
    $bookingCustomer ? $bookingCustomer['full_name'] : input('guest_name')
);
$email = (string) (
    $bookingCustomer ? $bookingCustomer['email'] : input('email')
);
$phone = (string) (
    $bookingCustomer ? $bookingCustomer['phone'] : input('phone')
);

$note = input('note');
$promoCode = promotion_normalize_code(input('promotion_code'));

try {
    if (post()) {
        check_csrf();
    }

    $room = query(
        'SELECT * FROM room_types WHERE id = ? AND active = 1',
        [input('id')]
    )->fetch();

    if (!$room) {
        throw new DomainException(
            'Phòng không tồn tại hoặc đang ngừng nhận đặt.'
        );
    }

    $quantity = integer('quantity', 1, 5);
    $guests = integer('guests', 1, 20, 2);
    $nights = valid_dates($in, $out);

    if ($guests > (int) $room['capacity'] * $quantity) {
        throw new DomainException(
            'Số khách vượt quá sức chứa của phòng đã chọn.'
        );
    }

    if (post()) {
        $key = input('request_key');

        if (
            !preg_match('/^[a-f0-9]{32}$/', $key)
            || empty($_SESSION['booking_keys'][$key])
        ) {
            throw new DomainException(
                'Phiên đặt phòng hết hạn. Vui lòng mở lại trang đặt phòng.'
            );
        }

        if (mb_strlen($name) < 2 || mb_strlen($name) > 100) {
            throw new DomainException('Họ tên cần từ 2 đến 100 ký tự.');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) {
            throw new DomainException('Email không hợp lệ.');
        }

        $phoneDigits = preg_replace('/\D/', '', $phone);

        if (
            !preg_match('/^\+?[0-9][0-9 ()-]{7,23}$/', $phone)
            || strlen($phoneDigits) < 9
            || strlen($phoneDigits) > 15
        ) {
            throw new DomainException(
                'Số điện thoại cần có từ 9 đến 15 chữ số.'
            );
        }

        if (mb_strlen($note) > 1000) {
            throw new DomainException('Ghi chú tối đa 1.000 ký tự.');
        }

        if (input('agree') !== '1') {
            throw new DomainException(
                'Vui lòng đồng ý chính sách đặt phòng.'
            );
        }

        $customerId = $bookingCustomer
            ? (int) $bookingCustomer['id']
            : null;

        db()->beginTransaction();

        // Các thao tác thay đổi tồn phòng cùng khóa loại phòng trước.
        $room = query(
            'SELECT * FROM room_types WHERE id = ? FOR UPDATE',
            [$room['id']]
        )->fetch();

        if (!$room) {
            throw new DomainException(
                'Phòng không còn tồn tại. Vui lòng chọn phòng khác.'
            );
        }

        // Nếu đã gửi thành công, mở lại đúng đơn cũ.
        $oldBooking = query(
            'SELECT code, customer_id
             FROM bookings
             WHERE request_key = ?',
            [$key]
        )->fetch();

        if ($oldBooking) {
            $oldCustomerId = $oldBooking['customer_id'] === null
                ? null
                : (int) $oldBooking['customer_id'];

            if ($oldCustomerId !== $customerId) {
                throw new DomainException(
                    'Biểu mẫu đã được sử dụng ở phiên tài khoản khác. '
                    . 'Vui lòng mở lại trang đặt phòng.'
                );
            }

            db()->commit();
            $_SESSION['booking_receipt'] = $oldBooking['code'];
            go('confirmation.php');
        }

        if ($guests > (int) $room['capacity'] * $quantity) {
            throw new DomainException(
                'Sức chứa vừa thay đổi. Vui lòng chọn lại phòng.'
            );
        }

        if (!$room['active'] || available($room, $in, $out) < $quantity) {
            throw new DomainException(
                'Không còn đủ phòng trong khoảng ngày này. '
                . 'Vui lòng đổi ngày ở hoặc chọn loại phòng khác.'
            );
        }

        $nightlyPrice = (int) $room['price'];
        $grossTotal = $nightlyPrice * $quantity * $nights;

        // Chỉ máy chủ quyết định số tiền được giảm.
        $pricing = promotion_for_new_booking($promoCode, $grossTotal);

        // So sánh cả số tiền cuối cùng mà khách vừa nhìn thấy.
        if (
            input('quoted_price') !== (string) $nightlyPrice
            || input('quoted_total') !== (string) $grossTotal
            || input('quoted_net') !== (string) $pricing['net']
        ) {
            throw new DomainException(
                'Giá phòng hoặc mức ưu đãi đã thay đổi. '
                . 'Vui lòng kiểm tra số tiền mới rồi gửi lại.'
            );
        }

        $code = bin2hex(random_bytes(16));

        query(
            'INSERT INTO bookings (
                code, request_key, room_type_id, customer_id,
                guest_name, email, phone, check_in, check_out,
                quantity, guests, nightly_price, total_price,
                promotion_id, discount_amount, note
             ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $code,
                $key,
                $room['id'],
                $customerId,
                $name,
                $email,
                $phone,
                $in,
                $out,
                $quantity,
                $guests,
                $nightlyPrice,
                $pricing['net'],
                $pricing['id'],
                $pricing['discount'],
                $note
            ]
        );

        db()->commit();
        $_SESSION['booking_receipt'] = $code;
        go('confirmation.php');
    }
} catch (DomainException $ex) {
    if (db()->inTransaction()) {
        db()->rollBack();
    }

    $error = $ex->getMessage();
} catch (Throwable $ex) {
    if (db()->inTransaction()) {
        db()->rollBack();
    }

    throw $ex;
}

if ($room && $nights > 0) {
    $hasAvailability =
        (bool) $room['active']
        && $guests <= (int) $room['capacity'] * $quantity
        && available($room, $in, $out) >= $quantity;

    if (!$hasAvailability && $error === '') {
        $error = 'Không còn đủ phòng phù hợp trong khoảng ngày này. '
            . 'Vui lòng cập nhật ngày ở hoặc chọn phòng khác.';
    }
}

$key = input('request_key');

if (
    !preg_match('/^[a-f0-9]{32}$/', $key)
    || empty($_SESSION['booking_keys'][$key])
) {
    $key = bin2hex(random_bytes(16));
    $_SESSION['booking_keys'][$key] = time();

    if (count($_SESSION['booking_keys']) > 20) {
        array_shift($_SESSION['booking_keys']);
    }
}

// Báo giá để hiển thị trên form và khôi phục sau lỗi nhập liệu.
$grossTotal = 0;
$previewDiscount = 0;
$offers = [];
$rules = [];
$promoMessage = '';

if ($room && $nights > 0) {
    $grossTotal = (int) $room['price'] * $quantity * $nights;

    $offers = query(
        'SELECT code, title, discount_percent, min_room_total
         FROM promotions
         WHERE active = 1 AND starts_on <= ? AND ends_on >= ?
         ORDER BY discount_percent DESC, code',
        [$today, $today]
    )->fetchAll();

    foreach ($offers as $offer) {
        $rules[$offer['code']] = [
            'percent' => (int) $offer['discount_percent'],
            'minimum' => (int) $offer['min_room_total']
        ];
    }

    if ($promoCode !== '') {
        if (!isset($rules[$promoCode])) {
            $promoMessage = 'Mã không tồn tại hoặc hiện chưa áp dụng.';
        } elseif ($grossTotal < $rules[$promoCode]['minimum']) {
            $promoMessage = 'Mã này áp dụng cho tiền phòng từ '
                . money($rules[$promoCode]['minimum']) . '.';
        } else {
            $previewDiscount = intdiv(
                $grossTotal * $rules[$promoCode]['percent'],
                100
            );
            $promoMessage = 'Đã áp dụng mã ' . $promoCode . '.';
        }
    }
}

$previewNet = $grossTotal - $previewDiscount;

$backToRoomsUrl = url('rooms.php') . '?' . http_build_query([
    'check_in'  => $in,
    'check_out' => $out,
    'quantity'  => $quantity,
    'guests'    => $guests,
]);
require __DIR__ . '/includes/header.php';
?>

<section class="page-heading">
    <div class="eyebrow">
        01 CHỌN PHÒNG &nbsp; — &nbsp;
        <strong>02 THÔNG TIN</strong>
        &nbsp; — &nbsp; 03 HOÀN TẤT
    </div>
    <h1>Kỳ nghỉ <em>của bạn.</em></h1>

    <a class="text-link" href="<?= e($backToRoomsUrl) ?>">
        ← Quay lại danh sách phòng
    </a>
</section>

<section class="section compact">
    <?php if ($error !== ''): ?>
        <div class="notice error" role="alert">
            <?= e($error) ?>
            <a href="<?= e(url('rooms.php')) ?>">Tìm phòng khác</a>

            <?php if ($bookingCustomer): ?>
                · <a href="<?= e(url('account.php#profile')) ?>">
                    Cập nhật thông tin cá nhân
                </a>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if ($room): ?>
        <div class="panel" style="margin-bottom:28px;">
            <h2>Thời gian lưu trú</h2>
            <p>
                Chọn ngày nhận và trả phòng rồi bấm “Cập nhật ngày ở”
                để kiểm tra phòng trống và tính lại tiền phòng.
            </p>

            <form action="<?= e(url('booking.php')) ?>"
                  method="get" id="stay-dates-form">

                <input type="hidden" name="id"
                       value="<?= (int) $room['id'] ?>">
                <input type="hidden" name="quantity"
                       value="<?= $quantity ?>">
                <input type="hidden" name="guests"
                       value="<?= $guests ?>">
                <input type="hidden" name="promotion_code"
                       id="stay-promo-code" value="<?= e($promoCode) ?>">

                <div class="form-grid">
                    <label for="stay-check-in">
                        Ngày nhận phòng *
                        <input type="date" id="stay-check-in" name="check_in"
                               value="<?= e($in) ?>" min="<?= e($today) ?>"
                               required>
                    </label>

                    <label for="stay-check-out">
                        Ngày trả phòng *
                        <input type="date" id="stay-check-out" name="check_out"
                               value="<?= e($out) ?>" min="<?= e($tomorrow) ?>"
                               required>
                    </label>
                </div>

                <button type="submit" class="button outline">
                    CẬP NHẬT NGÀY Ở ↗
                </button>

                <p id="stay-dates-warning" role="status" hidden>
                    Bạn đã thay đổi ngày. Vui lòng cập nhật ngày ở
                    trước khi gửi yêu cầu đặt phòng.
                </p>

                <small>
                    Cập nhật ngày sẽ tải lại trang.
                    Hãy chọn ngày trước khi nhập thông tin khách.
                </small>
            </form>
        </div>
    <?php endif; ?>

    <?php if ($room && $nights > 0): ?>
        <div class="detail-grid">
            <form action="<?= e(url('booking.php')) ?>"
                  method="post" class="panel"
                  id="reservation-form" data-booking-form>

                <?= csrf() ?>

                <input type="hidden" name="id" value="<?= (int) $room['id'] ?>">
                <input type="hidden" name="check_in" value="<?= e($in) ?>">
                <input type="hidden" name="check_out" value="<?= e($out) ?>">
                <input type="hidden" name="quantity" value="<?= $quantity ?>">
                <input type="hidden" name="guests" value="<?= $guests ?>">
                <input type="hidden" name="request_key" value="<?= e($key) ?>">

                <input type="hidden" name="quoted_price"
                       value="<?= (int) $room['price'] ?>">
                <input type="hidden" name="quoted_total"
                       value="<?= $grossTotal ?>">
                <input type="hidden" name="quoted_net"
                       id="quoted-net" value="<?= $previewNet ?>">

                <h2>Thông tin khách hàng</h2>

                <?php if ($bookingCustomer): ?>
                    <div class="notice">
                        <strong>Thông tin được điền từ tài khoản của bạn.</strong>
                        <p>
                            Bạn có thể xem đơn trong
                            <a href="<?= e(url('account.php#history')) ?>">
                                lịch sử đặt phòng
                            </a>.
                        </p>
                        <p>
                            Nếu muốn đổi thông tin, hãy
                            <a href="<?= e(url('account.php#profile')) ?>"
                               target="_blank" rel="noopener">cập nhật hồ sơ</a>
                            rồi tải lại trang này.
                        </p>
                    </div>
                <?php else: ?>
                    <p>Điền thông tin người đại diện nhận phòng.</p>
                    <p>
                        Đã có tài khoản?
                        <a href="<?= e(url('login.php')) ?>">Đăng nhập</a>
                        trước khi đặt để dùng hồ sơ đã lưu và theo dõi đơn.
                    </p>
                <?php endif; ?>

                <label>
                    Họ và tên *
                    <input name="guest_name" value="<?= e($name) ?>"
                           minlength="2" maxlength="100" autocomplete="name"
                           placeholder="Nguyễn Văn An" required
                           <?= $bookingCustomer ? 'readonly' : '' ?>>
                </label>

                <div class="form-grid">
                    <label>
                        Email *
                        <input type="email" name="email" value="<?= e($email) ?>"
                               maxlength="190" autocomplete="email"
                               placeholder="ban@email.com" required
                               <?= $bookingCustomer ? 'readonly' : '' ?>>
                    </label>

                    <label>
                        Số điện thoại *
                        <input type="tel" name="phone" value="<?= e($phone) ?>"
                               maxlength="25" autocomplete="tel"
                               placeholder="09xx xxx xxx" required
                               <?= $bookingCustomer ? 'readonly' : '' ?>>
                    </label>
                </div>

                <label>
                    Yêu cầu đặc biệt
                    <textarea name="note" rows="4" maxlength="1000"
                              placeholder="Giờ đến dự kiến, loại giường mong muốn…"><?= e($note) ?></textarea>
                </label>

                <small>
                    Yêu cầu đặc biệt tùy thuộc tình trạng thực tế của khách sạn.
                </small>

                <h3 style="margin-top:24px;">Ưu đãi cho kỳ nghỉ</h3>

                <label for="booking-promo-code">
                    Mã giảm giá
                    <input id="booking-promo-code" name="promotion_code"
                           list="booking-promo-options" maxlength="30"
                           autocomplete="off" value="<?= e($promoCode) ?>"
                           placeholder="Nhập mã hoặc để trống">
                </label>

                <datalist id="booking-promo-options">
                    <?php foreach ($offers as $offer): ?>
                        <option value="<?= e($offer['code']) ?>">
                            <?= e($offer['title']) ?>
                            · Giảm <?= (int) $offer['discount_percent'] ?>%
                            · Từ <?= money($offer['min_room_total']) ?>
                        </option>
                    <?php endforeach; ?>
                </datalist>

                <p id="booking-promo-message" aria-live="polite">
                    <?= e($promoMessage) ?>
                </p>

                <div class="notice">
                    <strong>Đơn sẽ được gửi đến khách sạn để xác nhận.</strong>
                    <p>Bạn có thể tra cứu bằng mã đặt phòng và email.</p>
                    <?php if ($bookingCustomer): ?>
                        <p>
                            Sau khi tạo đơn, mở chi tiết đơn trong tài khoản
                            để xem lựa chọn đặt cọc hoặc thanh toán.
                        </p>
                    <?php endif; ?>
                </div>

                <label class="checkbox">
                    <input type="checkbox" name="agree" value="1" required
                           <?= input('agree') === '1' ? 'checked' : '' ?>>
                    <span>
                        Tôi đồng ý với
                        <a href="<?= e(url('policies.php')) ?>"
                           target="_blank" rel="noopener">
                            chính sách lưu trú và xử lý dữ liệu
                        </a>.
                    </span>
                </label>

                <?php if ($hasAvailability): ?>
                    <button class="button full" type="submit">
                        GỬI YÊU CẦU ĐẶT PHÒNG ↗
                    </button>
                <?php else: ?>
                    <div class="notice error" role="alert">
                        Chưa thể đặt phòng với lựa chọn hiện tại.
                        Vui lòng đổi ngày ở hoặc chọn phòng khác.
                    </div>
                <?php endif; ?>
            </form>

            <aside class="panel booking-summary">
                <img src="<?= e(url('assets/images/' . $room['image'])) ?>"
                     alt="<?= e($room['name']) ?>">

                <div class="eyebrow">THE ONYX &amp; IVORY</div>
                <h2><?= e($room['name']) ?></h2>

                <dl>
                    <dt>Nhận phòng</dt>
                    <dd><?= e(date('d/m/Y', strtotime($in))) ?> · 14:00</dd>

                    <dt>Trả phòng</dt>
                    <dd><?= e(date('d/m/Y', strtotime($out))) ?> · 12:00</dd>

                    <dt>Thời gian</dt>
                    <dd><?= $nights ?> đêm</dd>

                    <dt>Số lượng</dt>
                    <dd><?= $quantity ?> phòng · <?= $guests ?> khách</dd>

                    <dt>Đơn giá / phòng / đêm</dt>
                    <dd><?= money($room['price']) ?></dd>

                    <dt>Tiền phòng trước ưu đãi</dt>
                    <dd><?= money($grossTotal) ?></dd>

                    <dt>Giảm giá</dt>
                    <dd id="summary-discount">
                        <?= $previewDiscount > 0 ? '−' : '' ?><?= money($previewDiscount) ?>
                    </dd>
                </dl>

                <div class="total">
                    <span>Tiền phòng sau ưu đãi</span>
                    <strong id="summary-net"><?= money($previewNet) ?></strong>
                </div>

                <p>Đã gồm thuế và bữa sáng. Dịch vụ phát sinh tính riêng.</p>
                <p>
                    <?= $hasAvailability
                        ? 'Còn phòng theo lựa chọn hiện tại.'
                        : 'Không đủ phòng phù hợp với lựa chọn hiện tại.' ?>
                </p>
            </aside>
        </div>
    <?php endif; ?>
</section>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const datesForm = document.getElementById('stay-dates-form');
    const bookingForm = document.getElementById('reservation-form');
    const warning = document.getElementById('stay-dates-warning');

    if (!datesForm || !warning) return;

    const checkIn = datesForm.elements.check_in;
    const checkOut = datesForm.elements.check_out;

    const appliedCheckIn = bookingForm
        ? bookingForm.elements.check_in.value
        : checkIn.value;

    const appliedCheckOut = bookingForm
        ? bookingForm.elements.check_out.value
        : checkOut.value;

    function datesChanged() {
        return checkIn.value !== appliedCheckIn
            || checkOut.value !== appliedCheckOut;
    }

    function updateDateWarning() {
        warning.hidden = !datesChanged();
    }

    datesForm.addEventListener('input', updateDateWarning);
    datesForm.addEventListener('change', updateDateWarning);
    window.addEventListener('pageshow', updateDateWarning);

    if (!bookingForm) return;

    const promoField = document.getElementById('booking-promo-code');
    const carriedCode = document.getElementById('stay-promo-code');
    const promoMessage = document.getElementById('booking-promo-message');
    const discountLabel = document.getElementById('summary-discount');
    const netLabel = document.getElementById('summary-net');
    const quotedNet = document.getElementById('quoted-net');

    const gross = <?= (int) $grossTotal ?>;
    const rules = <?= json_encode(
        (object) $rules,
        JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
    ) ?>;

    const currency = new Intl.NumberFormat('vi-VN', {
        style: 'currency',
        currency: 'VND',
        maximumFractionDigits: 0
    });

    function updatePromotion() {
        const code = promoField.value.trim().toUpperCase();
        let discount = 0;
        let errorMessage = '';

        carriedCode.value = code;

        if (code !== '') {
            if (!Object.prototype.hasOwnProperty.call(rules, code)) {
                errorMessage = 'Mã không tồn tại hoặc hiện chưa áp dụng. '
                    + 'Bạn có thể sửa mã hoặc để trống.';
            } else {
                const rule = rules[code];

                if (gross < rule.minimum) {
                    errorMessage = 'Mã ' + code + ' áp dụng cho tiền phòng từ '
                        + currency.format(rule.minimum) + '.';
                } else {
                    discount = Math.floor(gross * rule.percent / 100);
                }
            }
        }

        const net = gross - discount;

        promoField.setCustomValidity(errorMessage);
        discountLabel.textContent = (discount > 0 ? '−' : '')
            + currency.format(discount);
        netLabel.textContent = currency.format(net);
        quotedNet.value = String(net);

        if (errorMessage) {
            promoMessage.textContent = errorMessage;
        } else if (code) {
            promoMessage.textContent = 'Đã áp dụng ' + code
                + '. Bạn được giảm ' + currency.format(discount) + '.';
        } else {
            promoMessage.textContent = '';
        }
    }

    promoField.addEventListener('input', updatePromotion);
    promoField.addEventListener('change', updatePromotion);
    window.addEventListener('pageshow', updatePromotion);
    updatePromotion();

    // Không gửi đơn theo ngày cũ nếu khách vừa sửa ngày mà chưa cập nhật.
    bookingForm.addEventListener('submit', event => {
        if (!datesChanged()) return;

        event.preventDefault();
        event.stopImmediatePropagation();

        warning.hidden = false;
        warning.scrollIntoView({ behavior: 'smooth', block: 'center' });
        datesForm.querySelector('button[type="submit"]').focus();
    }, true);
});
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>