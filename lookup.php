<?php
require __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/operations.php';

$title = 'Tra cứu đặt phòng';

$booking = null;
$error = '';
$success = '';
$code = '';
$email = '';

$roomTotal = 0;
$serviceTotal = 0;
$grandTotal = 0;
$netPaid = 0;
$remaining = 0;
$payments = [];
$canCancel = false;

if (post()) {
    check_csrf();

    $code = trim(input('code'));
    $email = trim(input('email'));
    $today = date('Y-m-d');

    try {
        db()->beginTransaction();

        $booking = query(
            'SELECT b.*, r.name AS room_name
             FROM bookings b
             JOIN room_types r ON r.id = b.room_type_id
             WHERE b.code = ? AND b.email = ?
             LIMIT 1',
            [$code, $email]
        )->fetch();

        if (!$booking) {
            throw new DomainException(
                'Không tìm thấy đơn khớp với mã đặt phòng và email bạn đã nhập.'
            );
        }

        $bookingId = (int) $booking['id'];

        if (input('action') === 'cancel') {
            // Cùng thứ tự khóa với các thao tác thay đổi tồn phòng.
            query(
                'SELECT id FROM room_types WHERE id = ? FOR UPDATE',
                [$booking['room_type_id']]
            )->fetchColumn();

            $current = query(
                'SELECT b.*, r.name AS room_name
                 FROM bookings b
                 JOIN room_types r ON r.id = b.room_type_id
                 WHERE b.id = ? AND b.code = ? AND b.email = ?
                 FOR UPDATE',
                [$bookingId, $code, $email]
            )->fetch();

            if (!$current) {
                throw new DomainException(
                    'Thông tin đơn vừa thay đổi. Vui lòng tra cứu lại.'
                );
            }

            $booking = $current;
        }

        $roomTotal = (int) $booking['total_price'];
        $serviceTotal = booking_service_total($bookingId);
        $grandTotal = $roomTotal + $serviceTotal;

        // Khóa đơn trước khi kiểm tra tiền nếu khách đang yêu cầu hủy.
        // Đọc các giao dịch hiện tại để thấy cả khoản vừa được lễ tân ghi nhận.
        $paymentSql = 'SELECT kind, amount, method, created_at
                       FROM payments
                       WHERE booking_id = ?
                       ORDER BY created_at DESC, id DESC';

        if (input('action') === 'cancel') {
            $paymentSql .= ' FOR UPDATE';
        }

        $payments = query($paymentSql, [$bookingId])->fetchAll();

        foreach ($payments as $payment) {
            $amount = (int) $payment['amount'];
            $netPaid += $payment['kind'] === 'refund' ? -$amount : $amount;
        }

        $canCancel = in_array($booking['status'], ['pending', 'confirmed'], true)
            && $booking['check_in'] > $today
            && $netPaid === 0;

        if (input('action') === 'cancel') {
            if ($booking['status'] === 'cancelled') {
                $success = 'Đơn đặt phòng này đã được hủy.';
            } elseif (!$canCancel) {
                $error = 'Đơn hiện không đủ điều kiện hủy trực tuyến. '
                    . 'Vui lòng liên hệ lễ tân để được hỗ trợ.';
            } else {
                query(
                    "UPDATE bookings
                     SET status = 'cancelled', updated_at = NOW()
                     WHERE id = ?",
                    [$bookingId]
                );

                $booking['status'] = 'cancelled';
                $canCancel = false;
                $success = 'Đã hủy đơn đặt phòng thành công.';
            }
        }

        $remaining = $booking['status'] === 'cancelled'
            ? 0
            : max(0, $grandTotal - $netPaid);

        db()->commit();
    } catch (DomainException $ex) {
        if (db()->inTransaction()) {
            db()->rollBack();
        }

        $booking = null;
        $error = $ex->getMessage();
    } catch (Throwable $ex) {
        if (db()->inTransaction()) {
            db()->rollBack();
        }

        throw $ex;
    }
}

require __DIR__ . '/includes/header.php';
?>

<section class="page-heading">
    <div class="eyebrow">MANAGE YOUR STAY</div>
    <h1>Kỳ nghỉ trong <em>tầm tay.</em></h1>
    <p>Nhập mã đặt phòng và email đã dùng khi đặt để xem thông tin kỳ nghỉ.</p>
</section>

<section class="section narrow compact">
    <?php if ($error !== ''): ?>
        <div class="notice error" role="alert">
            <?= e($error) ?>
        </div>
    <?php endif; ?>

    <?php if ($success !== ''): ?>
        <div class="notice success" role="status">
            <?= e($success) ?>
        </div>
    <?php endif; ?>

    <form method="post" class="panel">
        <?= csrf() ?>

        <label>
            Mã đặt phòng
            <input
                type="text"
                name="code"
                value="<?= e($code) ?>"
                maxlength="32"
                autocomplete="off"
                required
            >
        </label>

        <label>
            Email đã dùng khi đặt phòng
            <input
                type="email"
                name="email"
                value="<?= e($email) ?>"
                maxlength="190"
                autocomplete="email"
                required
            >
        </label>

        <button type="submit" class="button full">TRA CỨU ↗</button>
    </form>

    <?php if ($booking): ?>
        <article class="panel">
            <span class="badge"><?= e(status_name($booking['status'])) ?></span>
            <h2><?= e($booking['room_name']) ?></h2>

            <dl>
                <dt>Mã đặt phòng</dt>
                <dd style="overflow-wrap: anywhere;"><?= e($booking['code']) ?></dd>

                <dt>Người đặt</dt>
                <dd><?= e($booking['guest_name']) ?></dd>

                <dt>Nhận phòng</dt>
                <dd><?= e(date('d/m/Y', strtotime($booking['check_in']))) ?></dd>

                <dt>Trả phòng</dt>
                <dd><?= e(date('d/m/Y', strtotime($booking['check_out']))) ?></dd>

                <dt>Số lượng</dt>
                <dd>
                    <?= (int) $booking['quantity'] ?> phòng ·
                    <?= (int) $booking['guests'] ?> khách
                </dd>
            </dl>

            <h3>Chi phí và thanh toán</h3>

            <dl>
                <dt>Tiền phòng sau ưu đãi</dt>
                <dd><?= money($roomTotal) ?></dd>

                <dt>Dịch vụ phát sinh</dt>
                <dd><?= money($serviceTotal) ?></dd>

                <dt>Tổng chi phí<?= $booking['status'] === 'cancelled' ? ' trước khi hủy' : '' ?></dt>
                <dd><strong><?= money($grandTotal) ?></strong></dd>

                <dt>Đã thanh toán sau khi trừ tiền hoàn</dt>
                <dd><?= money($netPaid) ?></dd>

                <dt>Còn phải trả</dt>
                <dd><strong><?= money($remaining) ?></strong></dd>
            </dl>

            <h3>Lịch sử thanh toán</h3>

            <?php if ($payments): ?>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th scope="col">Thời gian</th>
                                <th scope="col">Giao dịch</th>
                                <th scope="col">Phương thức</th>
                                <th scope="col">Số tiền</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($payments as $payment): ?>
                                <tr>
                                    <td>
                                        <?= e(date('d/m/Y H:i', strtotime($payment['created_at']))) ?>
                                    </td>
                                    <td>
                                        <?= $payment['kind'] === 'refund' ? 'Hoàn tiền' : 'Thu tiền' ?>
                                    </td>
                                    <td>
                                        <?= $payment['method'] === 'bank' ? 'Chuyển khoản' : 'Tiền mặt' ?>
                                    </td>
                                    <td><?= money($payment['amount']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p>Đơn này chưa có giao dịch thanh toán nào được ghi nhận.</p>
            <?php endif; ?>

            <?php if ($canCancel): ?>
                <form
                    method="post"
                    data-confirm="Bạn muốn hủy đơn đặt phòng này?"
                >
                    <?= csrf() ?>
                    <input type="hidden" name="action" value="cancel">
                    <input type="hidden" name="code" value="<?= e($booking['code']) ?>">
                    <input type="hidden" name="email" value="<?= e($booking['email']) ?>">

                    <button type="submit" class="button outline">
                        HỦY ĐẶT PHÒNG
                    </button>
                </form>
            <?php endif; ?>
        </article>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>