<?php
require __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/customer.php';
require_once __DIR__ . '/includes/booking_changes.php';

$customer = customer_required();

$title = 'Yêu cầu thay đổi đặt phòng';
$error = '';
$id = (int) input('id');

$booking = query(
    'SELECT b.*, r.name AS room_name
     FROM bookings b
     JOIN room_types r ON r.id = b.room_type_id
     WHERE b.id = ? AND b.customer_id = ?',
    [$id, $customer['id']]
)->fetch();

if (!$booking) {
    http_response_code(404);
    exit('Không tìm thấy đơn thuộc tài khoản của bạn.');
}

$typeId = input(
    'room_type_id',
    (string) $booking['room_type_id']
);
$checkIn = input('check_in', $booking['check_in']);
$checkOut = input('check_out', $booking['check_out']);
$reason = input('reason');

if (post()) {
    check_csrf();

    try {
        $action = input('action');

        if (!in_array($action, ['send', 'withdraw'], true)) {
            throw new DomainException('Thao tác không hợp lệ.');
        }

        $targetTypeId = $action === 'send'
            ? (int) $typeId
            : (int) $booking['room_type_id'];

        db()->beginTransaction();

        $locked = change_lock_booking($id, $targetTypeId);

        if (
            (int) $locked['customer_id']
            !== (int) $customer['id']
        ) {
            throw new DomainException(
                'Bạn không có quyền thao tác đơn này.'
            );
        }

        if ($action === 'withdraw') {
            $request = query(
                'SELECT *
                 FROM booking_change_requests
                 WHERE id = ?
                   AND booking_id = ?
                   AND customer_id = ?
                 FOR UPDATE',
                [
                    (int) input('request_id'),
                    $id,
                    $customer['id']
                ]
            )->fetch();

            if (!$request || $request['status'] !== 'pending') {
                throw new DomainException(
                    'Yêu cầu đã được xử lý hoặc không tồn tại.'
                );
            }

            query(
                "UPDATE booking_change_requests
                 SET status = 'withdrawn',
                     reviewed_at = NOW()
                 WHERE id = ?",
                [$request['id']]
            );

            $message = 'Đã rút yêu cầu. Đơn đặt phòng không thay đổi.';
        } else {
            if (mb_strlen($reason) < 5 || mb_strlen($reason) > 1000) {
                throw new DomainException(
                    'Lý do thay đổi cần từ 5 đến 1.000 ký tự.'
                );
            }

            if (input('agree') !== '1') {
                throw new DomainException(
                    'Vui lòng đồng ý mức giá dự kiến của phương án mới.'
                );
            }

            $pending = query(
                "SELECT id
                 FROM booking_change_requests
                 WHERE booking_id = ? AND status = 'pending'
                 LIMIT 1",
                [$id]
            )->fetchColumn();

            if ($pending) {
                throw new DomainException(
                    'Đơn đã có một yêu cầu đang chờ xử lý.'
                );
            }

            $quote = change_quote(
                $locked,
                $targetTypeId,
                $checkIn,
                $checkOut
            );

            $submittedPrice = input('quoted_price');

            if (
                !ctype_digit($submittedPrice) ||
                (int) $submittedPrice !== $quote['nightly_price']
            ) {
                throw new DomainException(
                    'Giá phòng vừa thay đổi. Hãy tải lại trang '
                    . 'để kiểm tra giá mới trước khi gửi.'
                );
            }

            query(
                'INSERT INTO booking_change_requests (
                    booking_id,
                    customer_id,
                    requested_room_type_id,
                    requested_check_in,
                    requested_check_out,
                    quoted_nightly_price,
                    quoted_total_price,
                    original_total_price,
                    booking_signature,
                    reason
                 ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    $id,
                    $customer['id'],
                    $targetTypeId,
                    $checkIn,
                    $checkOut,
                    $quote['nightly_price'],
                    $quote['total_price'],
                    $locked['total_price'],
                    change_booking_signature($locked),
                    $reason
                ]
            );

            $message = 'Đã gửi yêu cầu thay đổi. '
                . 'Đơn hiện tại được giữ nguyên đến khi lễ tân duyệt.';
        }

        db()->commit();

        flash($message);
        go('booking-change.php?id=' . $id);
    } catch (DomainException $exception) {
        if (db()->inTransaction()) {
            db()->rollBack();
        }

        $error = $exception->getMessage();
    } catch (Throwable $exception) {
        if (db()->inTransaction()) {
            db()->rollBack();
        }

        error_log((string) $exception);
        $error = 'Chưa thể xử lý yêu cầu. Vui lòng tải lại trang.';
    }
}

$types = query(
    'SELECT id, name, price, capacity
     FROM room_types
     WHERE active = 1
     ORDER BY price, id'
)->fetchAll();

$history = query(
    'SELECT c.*, r.name AS requested_room_name
     FROM booking_change_requests c
     JOIN room_types r ON r.id = c.requested_room_type_id
     WHERE c.booking_id = ? AND c.customer_id = ?
     ORDER BY c.id DESC
     LIMIT 20',
    [$id, $customer['id']]
)->fetchAll();

$pending = query(
    "SELECT id
     FROM booking_change_requests
     WHERE booking_id = ? AND status = 'pending'
     LIMIT 1",
    [$id]
)->fetchColumn();

$blockedReason = '';

try {
    change_assert_allowed($booking);
} catch (DomainException $exception) {
    $blockedReason = $exception->getMessage();
}

require __DIR__ . '/includes/header.php';
?>

<section class="page-heading">
    <div class="eyebrow">THE ONYX &amp; IVORY / TÀI KHOẢN</div>
    <h1>Điều chỉnh <em>kỳ nghỉ.</em></h1>
    <p>Gửi yêu cầu đổi ngày lưu trú hoặc loại phòng.</p>
</section>

<section class="section narrow">
    <p>
        <a class="text-link"
           href="<?= e(url('booking-detail.php?id=' . $id)) ?>">
            ← Quay lại chi tiết đơn
        </a>
    </p>

    <?php if ($error !== ''): ?>
        <div class="notice error" role="alert">
            <?= e($error) ?>
        </div>
    <?php endif; ?>

    <div class="panel">
        <h2>Đặt phòng hiện tại</h2>
        <p><strong><?= e($booking['room_name']) ?></strong></p>
        <p>
            <?= e(date('d/m/Y', strtotime($booking['check_in']))) ?>
            →
            <?= e(date('d/m/Y', strtotime($booking['check_out']))) ?>
        </p>
        <p>
            <?= (int) $booking['quantity'] ?> phòng ·
            <?= (int) $booking['guests'] ?> khách
        </p>
        <p>
            Tiền phòng:
            <strong><?= money($booking['total_price']) ?></strong>
        </p>
    </div>

    <?php if ($pending): ?>
        <div class="notice" style="margin-top: 24px;">
            Đơn đang có yêu cầu chờ xử lý. Bạn có thể rút yêu cầu
            bên dưới nếu muốn gửi phương án khác.
        </div>
    <?php elseif ($blockedReason !== ''): ?>
        <div class="notice" style="margin-top: 24px;">
            <?= e($blockedReason) ?>
        </div>
    <?php else: ?>
        <form method="post" class="panel" style="margin-top: 24px;">
            <?= csrf() ?>

            <input type="hidden" name="id" value="<?= $id ?>">
            <input type="hidden" name="action" value="send">
            <input type="hidden"
                   name="quoted_price"
                   id="change-quoted-price">

            <h2>Phương án mong muốn</h2>

            <p>
                Giữ nguyên số phòng và số khách của đơn hiện tại.
                Bạn có thể đổi ngày, đổi loại phòng hoặc cả hai.
            </p>

            <label>
                Loại phòng mới
                <select name="room_type_id" id="change-type" required>
                    <?php foreach ($types as $type): ?>
                        <option
                            value="<?= (int) $type['id'] ?>"
                            data-price="<?= (int) $type['price'] ?>"
                            <?= (int) $typeId === (int) $type['id']
                                ? 'selected' : '' ?>
                        >
                            <?= e($type['name']) ?>
                            — <?= money($type['price']) ?>/phòng/đêm
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>

            <div class="form-grid">
                <label>
                    Ngày nhận phòng mới
                    <input
                        type="date"
                        name="check_in"
                        id="change-in"
                        value="<?= e($checkIn) ?>"
                        min="<?= date('Y-m-d', strtotime('+1 day')) ?>"
                        max="<?= date('Y-m-d', strtotime('+1 year')) ?>"
                        required
                    >
                </label>

                <label>
                    Ngày trả phòng mới
                    <input
                        type="date"
                        name="check_out"
                        id="change-out"
                        value="<?= e($checkOut) ?>"
                        required
                    >
                </label>
            </div>

            <div class="notice" id="change-estimate" aria-live="polite">
                Chọn phương án để xem tiền phòng dự kiến.
            </div>

            <label>
                Lý do thay đổi
                <textarea
                    name="reason"
                    rows="4"
                    minlength="5"
                    maxlength="1000"
                    required
                    placeholder="Ví dụ: Tôi muốn lùi lịch nhận phòng..."
                ><?= e($reason) ?></textarea>
            </label>

            <label class="checkbox">
                <input type="checkbox" name="agree" value="1" required>
                <span>
                    Tôi đồng ý mức tiền phòng dự kiến và hiểu rằng
                    yêu cầu chỉ có hiệu lực sau khi lễ tân duyệt.
                </span>
            </label>

            <p>
                Dịch vụ phát sinh được tính riêng.
                Gửi yêu cầu chưa giữ chỗ cho phương án mới.
            </p>

            <button class="button full" type="submit">
                GỬI YÊU CẦU THAY ĐỔI ↗
            </button>
        </form>

        <script>
        (() => {
            const type = document.getElementById('change-type');
            const checkIn = document.getElementById('change-in');
            const checkOut = document.getElementById('change-out');
            const priceInput = document.getElementById('change-quoted-price');
            const estimate = document.getElementById('change-estimate');

            const quantity = <?= (int) $booking['quantity'] ?>;
            const oldTotal = <?= (int) $booking['total_price'] ?>;

            const money = value => new Intl.NumberFormat('vi-VN', {
                style: 'currency',
                currency: 'VND',
                maximumFractionDigits: 0
            }).format(value);

            function updateEstimate() {
                const option = type.selectedOptions[0];
                const price = Number(option?.dataset.price || 0);

                priceInput.value = String(price);

                const start = Date.parse(checkIn.value + 'T00:00:00Z');
                const end = Date.parse(checkOut.value + 'T00:00:00Z');
                const nights = (end - start) / 86400000;

                if (
                    !option ||
                    !Number.isFinite(nights) ||
                    nights < 1 ||
                    nights > 30
                ) {
                    estimate.textContent =
                        'Vui lòng chọn thời gian lưu trú từ 1 đến 30 đêm.';
                    return;
                }

                const total = price * quantity * nights;
                const difference = total - oldTotal;

                const differenceText = difference > 0
                    ? 'Tăng ' + money(difference)
                    : difference < 0
                        ? 'Giảm ' + money(-difference)
                        : 'Không thay đổi';

                estimate.textContent =
                    'Tiền phòng dự kiến: ' + money(total) +
                    ' (' + quantity + ' phòng × ' + nights + ' đêm). ' +
                    differenceText + ' so với tiền phòng hiện tại.';
            }

            [type, checkIn, checkOut].forEach(element => {
                element.addEventListener('change', updateEstimate);
            });

            updateEstimate();
        })();
        </script>
    <?php endif; ?>

    <h2 style="margin-top: 40px;">Lịch sử yêu cầu</h2>

    <?php if (!$history): ?>
        <p>Bạn chưa gửi yêu cầu thay đổi cho đơn này.</p>
    <?php endif; ?>

    <?php foreach ($history as $request): ?>
        <article class="panel" style="margin-bottom: 20px;">
            <div class="eyebrow">
                <?= e(change_request_status($request['status'])) ?>
            </div>

            <h3><?= e($request['requested_room_name']) ?></h3>

            <p>
                <?= e(date(
                    'd/m/Y',
                    strtotime($request['requested_check_in'])
                )) ?>
                →
                <?= e(date(
                    'd/m/Y',
                    strtotime($request['requested_check_out'])
                )) ?>
            </p>

            <p>
                Tiền phòng đề nghị:
                <strong><?= money($request['quoted_total_price']) ?></strong>
            </p>

            <p style="white-space: pre-wrap; overflow-wrap: anywhere;">Lý do: <?= e($request['reason']) ?></p>

            <?php if ($request['staff_response'] !== ''): ?>
                <div class="notice" style="white-space: pre-wrap; overflow-wrap: anywhere;"><?= e($request['staff_response']) ?></div>
            <?php endif; ?>

            <small>
                Gửi lúc:
                <?= e(date(
                    'd/m/Y H:i',
                    strtotime($request['created_at'])
                )) ?>
            </small>

            <?php if ($request['status'] === 'pending'): ?>
                <form method="post" style="margin-top: 16px;">
                    <?= csrf() ?>
                    <input type="hidden" name="id" value="<?= $id ?>">
                    <input type="hidden" name="action" value="withdraw">
                    <input type="hidden"
                           name="request_id"
                           value="<?= (int) $request['id'] ?>">

                    <button type="submit" class="button outline">
                        RÚT YÊU CẦU
                    </button>
                </form>
            <?php endif; ?>
        </article>
    <?php endforeach; ?>

    <p><small>Hiển thị tối đa 20 yêu cầu gần nhất của đơn.</small></p>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>