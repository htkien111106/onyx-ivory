<?php
require __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/customer.php';

$customer = customer_required();
$title = 'Chi tiết đơn đặt phòng';
$id = input('id');
$booking = false;

if (ctype_digit($id) && (int) $id > 0) {
    $booking = query(
        'SELECT b.*, r.name AS room_name, p.code AS promotion_code
         FROM bookings b
         JOIN room_types r ON r.id = b.room_type_id
         LEFT JOIN promotions p ON p.id = b.promotion_id
         WHERE b.id = ? AND b.customer_id = ?
         LIMIT 1',
        [(int) $id, $customer['id']]
    )->fetch();
}

if (!$booking) {
    http_response_code(404);
    require __DIR__ . '/includes/header.php';
    ?>
    <section class="section narrow">
        <div class="eyebrow">THE ONYX &amp; IVORY</div>
        <h1>Không tìm thấy đơn đặt phòng.</h1>
        <p>Đơn không tồn tại hoặc không thuộc tài khoản của bạn.</p>
        <a class="button" href="<?= e(url('account.php#history')) ?>">
            QUAY LẠI LỊCH SỬ
        </a>
    </section>
    <?php
    require __DIR__ . '/includes/footer.php';
    exit;
}

$bookingId = (int) $booking['id'];

$payments = query(
    'SELECT kind, amount, method, created_at
     FROM payments
     WHERE booking_id = ?
     ORDER BY created_at DESC, id DESC',
    [$bookingId]
)->fetchAll();

$assignedRooms = query(
    'SELECT h.number
     FROM room_assignments a
     JOIN hotel_rooms h ON h.id = a.room_id
     WHERE a.booking_id = ?
     ORDER BY h.number',
    [$bookingId]
)->fetchAll(PDO::FETCH_COLUMN);

$services = query(
    'SELECT service_name, unit, unit_price, quantity, created_at
     FROM booking_services
     WHERE booking_id = ? AND voided_at IS NULL
     ORDER BY created_at, id',
    [$bookingId]
)->fetchAll();

$serviceTotal = 0;
foreach ($services as $service) {
    $serviceTotal += (int) $service['unit_price'] * (int) $service['quantity'];
}

$totalPaid = 0;
$totalRefunded = 0;
foreach ($payments as $payment) {
    if ($payment['kind'] === 'payment') {
        $totalPaid += (int) $payment['amount'];
    } elseif ($payment['kind'] === 'refund') {
        $totalRefunded += (int) $payment['amount'];
    }
}

// Các số tiền này là giá trị đã lưu lúc đặt hoặc lúc duyệt đổi phòng.
$roomTotal = (int) $booking['total_price'];
$discount = (int) $booking['discount_amount'];
$grossRoomTotal = $roomTotal + $discount;
$grandTotal = $roomTotal + $serviceTotal;

$netPaid = $totalPaid - $totalRefunded;
$isCancelled = $booking['status'] === 'cancelled';
$amountDue = $isCancelled ? 0 : $grandTotal;
$remaining = max(0, $amountDue - $netPaid);
$overpaid = max(0, $netPaid - $amountDue);

if ($isCancelled) {
    $paymentStatus = 'Đơn đã hủy';
} elseif ($overpaid > 0) {
    $paymentStatus = 'Có số tiền thanh toán dư';
} elseif ($remaining === 0) {
    $paymentStatus = 'Đã thanh toán đủ';
} elseif ($netPaid > 0) {
    $paymentStatus = 'Đã thanh toán một phần';
} else {
    $paymentStatus = 'Chưa thanh toán';
}

$nights = (int) (new DateTimeImmutable($booking['check_in']))
    ->diff(new DateTimeImmutable($booking['check_out']))->days;

$formatDate = static function ($value, bool $withTime = false): string {
    $timestamp = $value ? strtotime($value) : false;

    return $timestamp === false
        ? 'Chưa ghi nhận'
        : date($withTime ? 'd/m/Y H:i' : 'd/m/Y', $timestamp);
};

$statusDescriptions = [
    'pending' => 'Khách sạn đã nhận yêu cầu và đang chờ xác nhận.',
    'confirmed' => 'Đơn đặt phòng đã được khách sạn xác nhận.',
    'checked_in' => 'Bạn đã nhận phòng và đang lưu trú.',
    'completed' => 'Bạn đã trả phòng và hoàn tất lưu trú.',
    'cancelled' => 'Đơn đặt phòng đã được hủy.'
];

$paymentMethods = [
    'cash' => 'Tiền mặt',
    'bank' => 'Chuyển khoản'
];

$cancelError = $_SESSION['cancel_booking_error'] ?? '';
unset($_SESSION['cancel_booking_error']);

$cancelReason = '';

if ($isCancelled) {
    $cancelReason = 'Đơn đặt phòng này đã được hủy.';
} elseif (!in_array($booking['status'], ['pending', 'confirmed'], true)) {
    $cancelReason = 'Trạng thái hiện tại không cho phép tự hủy đơn. '
        . 'Vui lòng liên hệ lễ tân nếu cần hỗ trợ.';
} elseif ($booking['check_in'] <= date('Y-m-d')) {
    $cancelReason = 'Đã đến hoặc qua ngày nhận phòng. '
        . 'Vui lòng liên hệ lễ tân nếu muốn hủy.';
} elseif ($netPaid !== 0) {
    $cancelReason = 'Đơn còn số dư thanh toán. '
        . 'Vui lòng liên hệ lễ tân để được hỗ trợ hủy và hoàn tiền.';
}

$canCancel = $cancelReason === '';

$stayDetails = [
    'Ngày tạo đơn' => $formatDate($booking['created_at'], true),
    'Ngày nhận phòng' => $formatDate($booking['check_in']) . ' — 14:00',
    'Ngày trả phòng' => $formatDate($booking['check_out']) . ' — 12:00',
    'Số đêm' => $nights . ' đêm',
    'Số lượng phòng' => (int) $booking['quantity'] . ' phòng',
    'Số khách' => (int) $booking['guests'] . ' khách',
    'Phòng đã phân' => $assignedRooms ? implode(', ', $assignedRooms) : 'Chưa phân phòng',
    'Nhận phòng thực tế' => $formatDate($booking['actual_check_in'], true),
    'Trả phòng thực tế' => $formatDate($booking['actual_check_out'], true)
];

require __DIR__ . '/includes/header.php';
?>

<style>
.customer-booking-detail .detail-grid { align-items: start; }
.customer-booking-detail .section-gap { margin-top: 28px; }
.customer-booking-detail .detail-list { margin: 0; }
.customer-booking-detail .detail-row {
    display: grid;
    grid-template-columns: minmax(120px, 1fr) minmax(0, 1.5fr);
    gap: 16px;
    padding: 13px 0;
    border-bottom: 1px solid var(--line, #ddd);
}
.customer-booking-detail .detail-row dt { font-weight: 600; }
.customer-booking-detail .detail-row dd { margin: 0; overflow-wrap: anywhere; }
.customer-booking-detail .booking-code { overflow-wrap: anywhere; }
.customer-booking-detail .booking-note { white-space: pre-wrap; overflow-wrap: anywhere; }
.customer-booking-detail .booking-table-wrap { overflow-x: auto; }
.customer-booking-detail .booking-table { width: 100%; border-collapse: collapse; }
.customer-booking-detail .booking-table th,
.customer-booking-detail .booking-table td {
    padding: 14px 12px;
    border-bottom: 1px solid var(--line, #ddd);
    text-align: left;
    vertical-align: top;
}
.customer-booking-detail .booking-table th {
    font-size: 12px;
    letter-spacing: .5px;
    background: #f5f1e9;
}
.customer-booking-detail .booking-table .amount { text-align: right; white-space: nowrap; }
.customer-booking-detail .nowrap { white-space: nowrap; }
.customer-booking-detail .grand-total { color: #79603b; font-size: 18px; }
.customer-booking-detail .amount-due {
    background: #f5f1e9;
    padding: 16px;
    margin-top: 12px;
    border: 0;
}
.customer-booking-detail .amount-due dd { font-weight: 700; }
.customer-booking-detail .muted { color: #6c6e68; line-height: 1.8; }
.customer-booking-detail .booking-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    align-items: center;
    margin-bottom: 24px;
}
.customer-booking-detail .booking-actions .button { text-align: center; white-space: normal; }

.onyx-dialog {
    position: fixed;
    inset: 0;
    box-sizing: border-box;
    width: min(520px, calc(100vw - 32px));
    max-width: none;
    max-height: calc(100vh - 32px);
    max-height: calc(100dvh - 32px);
    margin: auto;
    padding: 0;
    overflow-y: auto;
    border: 1px solid rgba(177,147,91,.5);
    border-radius: 4px;
    background: #f8f5ee;
    color: #202321;
    box-shadow: 0 30px 100px rgba(0,0,0,.35);
}
.onyx-dialog::backdrop { background: rgba(15,18,16,.65); backdrop-filter: blur(7px); }
.onyx-dialog[open] { animation: onyx-dialog-enter .25s ease-out; }
.onyx-dialog-content { position: relative; padding: 42px 38px 28px; text-align: center; }
.onyx-dialog-close {
    position: absolute;
    top: 12px;
    right: 14px;
    width: 40px;
    height: 40px;
    padding: 0;
    border: 0;
    border-radius: 50%;
    background: transparent;
    color: #777;
    font: 30px Arial, sans-serif;
    cursor: pointer;
}
.onyx-dialog-close:hover { background: #eee8dd; }
.onyx-dialog-brand { color: #82704f; font: 10px Arial, sans-serif; letter-spacing: 3px; }
.onyx-dialog-symbol { margin: 22px 0; color: #b1935b; font: 48px Georgia, serif; }
.onyx-dialog-symbol span { font-size: 25px; }
.onyx-dialog .onyx-dialog-eyebrow { font-size: 10px; letter-spacing: 1.8px; color: #82704f; }
.onyx-dialog h2 {
    margin: 16px 0;
    font: 400 clamp(32px, 6vw, 43px)/1.18 Georgia, serif;
    letter-spacing: normal;
}
.onyx-dialog h2 em { color: #917444; }
.onyx-dialog-description { color: #696b65; font-size: 14px; line-height: 1.8; }
.onyx-dialog-booking {
    display: grid;
    gap: 7px;
    margin: 25px 0;
    padding: 18px 12px;
    border-block: 1px solid #dfd6c5;
}
.onyx-dialog-booking strong { font: 24px Georgia, serif; overflow-wrap: anywhere; }
.onyx-dialog-booking span { font-size: 13px; color: #696b65; }
.onyx-dialog-actions { display: grid; gap: 10px; }
.onyx-dialog-actions button {
    width: 100%;
    min-height: 49px;
    padding: 14px 16px;
    border-radius: 0;
    font: 11px Arial, sans-serif;
    letter-spacing: 1.5px;
    cursor: pointer;
}
.onyx-dialog-keep { border: 1px solid #202823; background: #202823; color: white; }
.onyx-dialog-keep:hover { background: #354139; }
.onyx-dialog-confirm { border: 1px solid #c8b692; background: transparent; color: #775e36; }
.onyx-dialog-confirm:hover { background: #ece3d3; }
.onyx-dialog button:focus-visible { outline: 2px solid #b1935b; outline-offset: 3px; }
.onyx-dialog button:disabled { opacity: .6; cursor: wait; }
.onyx-dialog-footnote { margin-top: 18px; color: #777; font-size: 11px; line-height: 1.7; }
@keyframes onyx-dialog-enter {
    from { opacity: 0; transform: translateY(12px); }
    to { opacity: 1; transform: translateY(0); }
}
@media (max-width: 600px) {
    .customer-booking-detail .detail-row { grid-template-columns: 1fr; gap: 5px; }
    .customer-booking-detail .booking-actions .button { width: 100%; }
}
@media (max-width: 480px) {
    .onyx-dialog-content { padding: 38px 22px 24px; }
}
@media (prefers-reduced-motion: reduce) {
    .onyx-dialog[open] { animation: none; }
}
</style>

<section class="page-heading">
    <div class="eyebrow">THE ONYX &amp; IVORY / TÀI KHOẢN</div>
    <h1>Chi tiết <em>kỳ nghỉ.</em></h1>
    <p>Thông tin đặt phòng, dịch vụ và thanh toán của bạn.</p>
</section>

<section class="section customer-booking-detail">
    <div class="booking-actions">
        <a class="button outline" href="<?= e(url('account.php#history')) ?>">
            ← LỊCH SỬ ĐẶT PHÒNG
        </a>
        <a class="button outline"
           href="<?= e(url('booking-change.php?id=' . $bookingId)) ?>">
            YÊU CẦU ĐỔI / XEM KẾT QUẢ ↗
        </a>
        <a class="button"
           href="<?= e(url('booking-payment.php?id=' . $bookingId)) ?>">
            THANH TOÁN / XEM CHUYỂN KHOẢN ↗
        </a>

        <?php if ($booking['status'] === 'completed'): ?>
        <a class="button outline"
            href="<?= e(url('booking-review.php?id=' . $bookingId)) ?>">
                ĐÁNH GIÁ KỲ NGHỈ / XEM PHẢN HỒI ↗
        </a>
        <?php endif; ?>
    </div>

    <div class="panel">
        <span class="badge"><?= e(status_name($booking['status'])) ?></span>
        <h2><?= e($booking['room_name']) ?></h2>
        <p class="booking-code">
            <strong>Mã đặt phòng:</strong> <?= e($booking['code']) ?>
        </p>
        <p><?= e($statusDescriptions[$booking['status']] ?? '') ?></p>
    </div>

    <div class="detail-grid section-gap">
        <div class="panel">
            <h2>Thông tin đặt phòng</h2>
            <dl class="detail-list">
                <?php foreach ($stayDetails as $label => $value): ?>
                    <div class="detail-row">
                        <dt><?= e($label) ?></dt>
                        <dd><?= e($value) ?></dd>
                    </div>
                <?php endforeach; ?>
            </dl>
        </div>

        <div class="panel">
            <h2>Người nhận phòng</h2>
            <dl class="detail-list">
                <div class="detail-row">
                    <dt>Họ và tên</dt><dd><?= e($booking['guest_name']) ?></dd>
                </div>
                <div class="detail-row">
                    <dt>Email</dt><dd><?= e($booking['email']) ?></dd>
                </div>
                <div class="detail-row">
                    <dt>Số điện thoại</dt><dd><?= e($booking['phone']) ?></dd>
                </div>
            </dl>

            <p class="muted">
                Thông tin người nhận phòng được lưu tại thời điểm đặt.
                Việc cập nhật hồ sơ tài khoản áp dụng cho các đơn sau.
            </p>

            <h3>Yêu cầu đặc biệt</h3>
            <p class="booking-note"><?= e(
                trim((string) $booking['note']) !== ''
                    ? $booking['note']
                    : 'Không có yêu cầu đặc biệt.'
            ) ?></p>
        </div>
    </div>

    <div class="panel section-gap">
        <h2>Thông tin thanh toán</h2>
        <div class="notice" role="status"><?= e($paymentStatus) ?></div>

        <dl class="detail-list">
            <div class="detail-row">
                <dt>Đơn giá / phòng / đêm</dt>
                <dd><?= money($booking['nightly_price']) ?></dd>
            </div>

            <?php if ($discount > 0): ?>
                <div class="detail-row">
                    <dt>Tiền phòng trước ưu đãi</dt>
                    <dd><?= money($grossRoomTotal) ?></dd>
                </div>
                <div class="detail-row">
                    <dt>
                        Giảm giá khuyến mãi
                        <?php if ($booking['promotion_code'] !== null): ?>
                            (<?= e($booking['promotion_code']) ?>)
                        <?php endif; ?>
                    </dt>
                    <dd>−<?= money($discount) ?></dd>
                </div>
            <?php endif; ?>

            <div class="detail-row">
                <dt><?= $discount > 0 ? 'Tiền phòng sau ưu đãi' : 'Tiền phòng' ?></dt>
                <dd><?= money($roomTotal) ?></dd>
            </div>
            <div class="detail-row">
                <dt>Tổng tiền dịch vụ</dt><dd><?= money($serviceTotal) ?></dd>
            </div>
            <div class="detail-row grand-total">
                <dt>Tổng tiền phòng và dịch vụ</dt>
                <dd><strong><?= money($grandTotal) ?></strong></dd>
            </div>
            <div class="detail-row">
                <dt>Tổng tiền đã thu</dt><dd><?= money($totalPaid) ?></dd>
            </div>
            <div class="detail-row">
                <dt>Tổng tiền đã hoàn</dt><dd><?= money($totalRefunded) ?></dd>
            </div>
            <div class="detail-row">
                <dt>Đã thu sau hoàn tiền</dt><dd><?= money($netPaid) ?></dd>
            </div>
            <?php if (!$isCancelled): ?>
                <div class="detail-row amount-due">
                    <dt>Còn phải thanh toán</dt><dd><?= money($remaining) ?></dd>
                </div>
            <?php endif; ?>
            <?php if ($overpaid > 0): ?>
                <div class="detail-row">
                    <dt>Số tiền cần đối soát / hoàn lại</dt>
                    <dd><?= money($overpaid) ?></dd>
                </div>
            <?php endif; ?>
        </dl>

        <?php if ($isCancelled): ?>
            <p class="muted">
                Đơn đã hủy. Các khoản trên được lưu để đối chiếu.
                Nếu còn số dư, vui lòng liên hệ lễ tân.
            </p>
        <?php else: ?>
            <p class="muted">
                Tiền phòng đã gồm thuế và bữa sáng. Dịch vụ phát sinh được
                liệt kê bên dưới. Các khoản thu và hoàn tiền được cập nhật
                sau khi lễ tân ghi nhận giao dịch.
            </p>
        <?php endif; ?>
    </div>

    <div class="panel section-gap">
        <h2>Dịch vụ đã sử dụng</h2>
        <?php if (!$services): ?>
            <p>Chưa có dịch vụ phát sinh được ghi nhận cho đơn này.</p>
        <?php else: ?>
            <div class="booking-table-wrap">
                <table class="booking-table">
                    <thead>
                        <tr>
                            <th scope="col">Thời gian ghi nhận</th>
                            <th scope="col">Dịch vụ</th>
                            <th scope="col">Số lượng</th>
                            <th scope="col" class="amount">Đơn giá</th>
                            <th scope="col" class="amount">Thành tiền</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($services as $service): ?>
                            <tr>
                                <td class="nowrap"><?= e($formatDate($service['created_at'], true)) ?></td>
                                <td><?= e($service['service_name']) ?></td>
                                <td class="nowrap">
                                    <?= (int) $service['quantity'] ?> <?= e($service['unit']) ?>
                                </td>
                                <td class="amount"><?= money($service['unit_price']) ?></td>
                                <td class="amount">
                                    <?= money((int) $service['unit_price'] * (int) $service['quantity']) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <th scope="row" colspan="4">Tổng tiền dịch vụ</th>
                            <td class="amount"><strong><?= money($serviceTotal) ?></strong></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            <p class="muted">Đơn giá được lưu tại thời điểm sử dụng. Các lượt đã hủy được loại khỏi tổng tiền.</p>
        <?php endif; ?>
    </div>

    <div class="panel section-gap">
        <h2>Lịch sử giao dịch</h2>
        <?php if (!$payments): ?>
            <p>Chưa có giao dịch thanh toán được ghi nhận.</p>
        <?php else: ?>
            <div class="booking-table-wrap">
                <table class="booking-table">
                    <thead>
                        <tr>
                            <th scope="col">Thời gian</th>
                            <th scope="col">Giao dịch</th>
                            <th scope="col">Phương thức</th>
                            <th scope="col" class="amount">Số tiền</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($payments as $payment): ?>
                            <tr>
                                <td class="nowrap"><?= e($formatDate($payment['created_at'], true)) ?></td>
                                <td><?= $payment['kind'] === 'payment' ? 'Thu tiền' : 'Hoàn tiền' ?></td>
                                <td><?= e($paymentMethods[$payment['method']] ?? $payment['method']) ?></td>
                                <td class="amount"><?= money($payment['amount']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <div class="panel section-gap" id="cancel-booking">
        <h2>Hủy đơn đặt phòng</h2>

        <?php if ($cancelError !== ''): ?>
            <div class="notice error" role="alert"><?= e($cancelError) ?></div>
        <?php endif; ?>

        <?php if ($canCancel): ?>
            <p>
                Bạn có thể hủy trước ngày nhận phòng khi đơn đang chờ xác nhận
                hoặc đã xác nhận và không còn số dư thanh toán.
            </p>
            <form action="<?= e(url('cancel-booking.php')) ?>"
                  method="post" id="cancel-booking-form">
                <?= csrf() ?>
                <input type="hidden" name="booking_id" value="<?= $bookingId ?>">
                <button type="button" class="button outline" id="open-cancel-dialog">
                    HỦY ĐƠN ĐẶT PHÒNG
                </button>
            </form>
        <?php else: ?>
            <p><?= e($cancelReason) ?></p>
        <?php endif; ?>
    </div>

    <div class="actions section-gap">
        <a class="button outline" href="<?= e(url('account.php#history')) ?>">
            ← QUAY LẠI TÀI KHOẢN
        </a>
    </div>
</section>

<?php if ($canCancel): ?>
    <dialog id="cancel-booking-dialog" class="onyx-dialog"
            aria-labelledby="cancel-dialog-title"
            aria-describedby="cancel-dialog-description">
        <div class="onyx-dialog-content">
            <button type="button" class="onyx-dialog-close"
                    id="close-cancel-dialog" aria-label="Đóng hộp thoại">&times;</button>

            <div class="onyx-dialog-brand">THE ONYX &amp; IVORY</div>
            <div class="onyx-dialog-symbol" aria-hidden="true">O<span>&amp;</span>I</div>
            <p class="onyx-dialog-eyebrow">KẾ HOẠCH CỦA BẠN ĐÃ THAY ĐỔI?</p>

            <h2 id="cancel-dialog-title">Bạn muốn hủy<br><em>kỳ nghỉ này?</em></h2>
            <p id="cancel-dialog-description" class="onyx-dialog-description">
                Khách sạn sẽ ngừng giữ phòng cho đơn này.
                Nếu muốn quay lại, bạn cần tạo một đơn đặt phòng mới.
            </p>

            <div class="onyx-dialog-booking">
                <strong><?= e($booking['room_name']) ?></strong>
                <span>
                    <?= e($formatDate($booking['check_in'])) ?>
                    → <?= e($formatDate($booking['check_out'])) ?>
                </span>
                <span>
                    <?= (int) $booking['quantity'] ?> phòng ·
                    <?= (int) $booking['guests'] ?> khách
                </span>
            </div>

            <div class="onyx-dialog-actions">
                <button type="button" id="keep-booking" class="onyx-dialog-keep" autofocus>
                    GIỮ LẠI ĐẶT PHÒNG
                </button>
                <button type="button" id="confirm-cancel-booking" class="onyx-dialog-confirm">
                    XÁC NHẬN HỦY
                </button>
            </div>

            <p class="onyx-dialog-footnote">Đơn đã hủy vẫn được lưu trong lịch sử tài khoản.</p>
        </div>
    </dialog>

    <script>
    (() => {
        const dialog = document.getElementById('cancel-booking-dialog');
        const form = document.getElementById('cancel-booking-form');
        const openButton = document.getElementById('open-cancel-dialog');
        const closeButton = document.getElementById('close-cancel-dialog');
        const keepButton = document.getElementById('keep-booking');
        const confirmButton = document.getElementById('confirm-cancel-booking');
        const buttons = [openButton, closeButton, keepButton, confirmButton];

        let submitting = false;
        let previousOverflow = '';

        openButton.addEventListener('click', () => {
            if (dialog.open || submitting) return;
            previousOverflow = document.body.style.overflow;
            dialog.showModal();
            document.body.style.overflow = 'hidden';
        });

        function closeDialog() {
            if (!submitting) dialog.close();
        }

        closeButton.addEventListener('click', closeDialog);
        keepButton.addEventListener('click', closeDialog);

        dialog.addEventListener('close', () => {
            document.body.style.overflow = previousOverflow;
            openButton.focus();
        });

        dialog.addEventListener('cancel', event => {
            if (submitting) event.preventDefault();
        });

        dialog.addEventListener('click', event => {
            if (event.target !== dialog) return;

            const rect = dialog.getBoundingClientRect();
            const outside = event.clientX < rect.left || event.clientX > rect.right
                || event.clientY < rect.top || event.clientY > rect.bottom;

            if (outside) closeDialog();
        });

        confirmButton.addEventListener('click', () => {
            if (!submitting) form.requestSubmit();
        });

        form.addEventListener('submit', event => {
            if (submitting) {
                event.preventDefault();
                return;
            }

            submitting = true;
            buttons.forEach(button => { button.disabled = true; });
            confirmButton.textContent = 'ĐANG XỬ LÝ…';
        });

        window.addEventListener('pageshow', event => {
            if (!event.persisted) return;

            submitting = false;
            buttons.forEach(button => { button.disabled = false; });
            confirmButton.textContent = 'XÁC NHẬN HỦY';
            if (dialog.open) dialog.close();
            document.body.style.overflow = previousOverflow;
        });
    })();
    </script>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>