<?php
require __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/customer.php';
require_once __DIR__ . '/includes/transfers.php';

$customer = customer_required();
$title = 'Thanh toán chuyển khoản';
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

if (post()) {
    check_csrf();

    try {
        db()->beginTransaction();

        $locked = locked_booking($id);

        if ((int) $locked['customer_id'] !== (int) $customer['id']) {
            throw new DomainException('Đơn không thuộc tài khoản của bạn.');
        }

        $action = input('action');

        if ($action === 'create') {
            if (!transfer_bank_ready()) {
                throw new DomainException(
                    'Khách sạn chưa cấu hình tài khoản nhận tiền.'
                );
            }

            if (!transfer_payable($locked)) {
                throw new DomainException(
                    'Đơn đã hủy hoặc trả phòng không thể tạo yêu cầu mới.'
                );
            }

            $existing = query(
                "SELECT id FROM booking_transfers
                 WHERE booking_id = ?
                   AND status IN ('awaiting', 'reported')
                 LIMIT 1",
                [$id]
            )->fetchColumn();

            if ($existing) {
                throw new DomainException(
                    'Đơn đang có yêu cầu chuyển khoản. '
                    . 'Vui lòng sử dụng yêu cầu đang hiển thị.'
                );
            }

            $purpose = input('purpose');

            if (!in_array($purpose, ['deposit', 'balance'], true)) {
                throw new DomainException('Mục đích chuyển khoản không hợp lệ.');
            }

            if ($purpose === 'deposit' && $locked['status'] === 'checked_in') {
                throw new DomainException(
                    'Bạn đã nhận phòng. Vui lòng chọn thanh toán phần còn lại.'
                );
            }

            $amounts = transfer_amounts($locked);
            $amount = $purpose === 'deposit'
                ? $amounts['deposit']
                : $amounts['due'];

            if ($amount < 1) {
                throw new DomainException('Không còn số tiền cần chuyển.');
            }

            if ((string) $amount !== input('quoted_amount')) {
                throw new DomainException(
                    'Số tiền của đơn vừa thay đổi. '
                    . 'Hãy kiểm tra số tiền mới và chọn lại.'
                );
            }

            $code = 'OI' . strtoupper(bin2hex(random_bytes(7)));

            query(
                'INSERT INTO booking_transfers
                    (booking_id, customer_id, purpose, amount, transfer_code)
                 VALUES (?, ?, ?, ?, ?)',
                [$id, $customer['id'], $purpose, $amount, $code]
            );

            $message = 'Đã tạo hướng dẫn chuyển khoản.';
        } elseif (in_array($action, ['report', 'cancel'], true)) {
            $transfer = query(
                'SELECT * FROM booking_transfers
                 WHERE id = ? AND booking_id = ? AND customer_id = ?
                 FOR UPDATE',
                [(int) input('transfer_id'), $id, $customer['id']]
            )->fetch();

            if (!$transfer || $transfer['status'] !== 'awaiting') {
                throw new DomainException(
                    'Yêu cầu đã được cập nhật. Vui lòng tải lại trang.'
                );
            }

            if ($action === 'report') {
                query(
                    "UPDATE booking_transfers
                     SET status = 'reported', reported_at = NOW()
                     WHERE id = ?",
                    [$transfer['id']]
                );

                $message = 'Đã báo cho lễ tân. '
                    . 'Vui lòng không chuyển lại khoản tiền này.';
            } else {
                query(
                    "UPDATE booking_transfers
                     SET status = 'cancelled'
                     WHERE id = ?",
                    [$transfer['id']]
                );

                $message = 'Đã hủy yêu cầu chuyển khoản.';
            }
        } else {
            throw new DomainException('Thao tác không hợp lệ.');
        }

        db()->commit();
        flash($message);
        go('booking-payment.php?id=' . $id);
    } catch (DomainException $ex) {
        if (db()->inTransaction()) db()->rollBack();
        $error = $ex->getMessage();
    } catch (Throwable $ex) {
        if (db()->inTransaction()) db()->rollBack();
        error_log((string) $ex);
        $error = 'Chưa thể xử lý yêu cầu. Vui lòng tải lại trang.';
    }
}

$bank = transfer_bank();
$amounts = transfer_amounts($booking);

$transfers = query(
    'SELECT * FROM booking_transfers
     WHERE booking_id = ? AND customer_id = ?
     ORDER BY id DESC',
    [$id, $customer['id']]
)->fetchAll();

$open = null;

foreach ($transfers as $item) {
    if (in_array($item['status'], ['awaiting', 'reported'], true)) {
        $open = $item;
        break;
    }
}

$refunds = query(
    "SELECT amount, method, created_at
     FROM payments
     WHERE booking_id = ? AND kind = 'refund'
     ORDER BY id DESC",
    [$id]
)->fetchAll();

require __DIR__ . '/includes/header.php';
?>

<section class="page-heading">
    <div class="eyebrow">THE ONYX &amp; IVORY / THANH TOÁN</div>
    <h1>Thanh toán <em>kỳ nghỉ.</em></h1>
    <p>Đặt cọc hoặc thanh toán bằng chuyển khoản ngân hàng.</p>
</section>

<section class="section narrow">
    <p>
        <a class="text-link"
           href="<?= e(url('booking-detail.php?id=' . $id)) ?>">
            ← Quay lại chi tiết đơn
        </a>
    </p>

    <?php if ($error !== ''): ?>
        <div class="notice error" role="alert"><?= e($error) ?></div>
    <?php endif; ?>

    <div class="panel">
        <div class="eyebrow"><?= e(status_name($booking['status'])) ?></div>
        <h2><?= e($booking['room_name']) ?></h2>

        <dl>
            <dt>Tổng tiền phòng và dịch vụ</dt>
            <dd><?= money($amounts['grand']) ?></dd>

            <dt>Đã thanh toán sau hoàn tiền</dt>
            <dd><?= money($amounts['paid']) ?></dd>

            <dt>Còn phải trả</dt>
            <dd>
                <strong>
                    <?= money(
                        $booking['status'] === 'cancelled'
                            ? 0 : $amounts['due']
                    ) ?>
                </strong>
            </dd>
        </dl>
    </div>

    <?php if ($open): ?>
        <div class="panel" style="margin-top:24px">
            <div class="eyebrow">
                <?= e(transfer_status_name($open['status'])) ?>
            </div>

            <h2><?= e(transfer_purpose_name($open['purpose'])) ?></h2>

            <?php
            $canStillSend = transfer_bank_ready()
                && transfer_payable($booking)
                && (int) $open['amount'] <= $amounts['due']
                && (
                    $open['purpose'] !== 'deposit'
                    || (
                        $booking['status'] !== 'checked_in'
                        && (int) $open['amount'] === $amounts['deposit']
                    )
                );
            ?>

            <?php if ($open['status'] === 'reported'): ?>
                <div class="notice">
                    Bạn đã báo chuyển <?= money($open['amount']) ?>.
                    Lễ tân đang đối chiếu mã
                    <strong><?= e($open['transfer_code']) ?></strong>.
                    Vui lòng không chuyển thêm cho mã này.
                </div>
            <?php else: ?>

                <?php if ($canStillSend): ?>
                    <p>
                        Mở ứng dụng ngân hàng của bạn và chuyển đúng
                        số tiền, tài khoản và nội dung sau:
                    </p>
                    <dl>
                        <dt>Ngân hàng</dt>
                        <dd><?= e($bank['bank_name']) ?></dd>
                        <dt>Chủ tài khoản</dt>
                        <dd><?= e($bank['account_holder']) ?></dd>
                        <dt>Số tài khoản</dt>
                        <dd><strong><?= e($bank['account_number']) ?></strong></dd>
                        <dt>Số tiền</dt>
                        <dd><strong><?= money($open['amount']) ?></strong></dd>
                        <dt>Nội dung chuyển khoản</dt>
                        <dd><strong><?= e($open['transfer_code']) ?></strong></dd>
                    </dl>
                <?php else: ?>
                    <div class="notice">
                        Thông tin thanh toán đã thay đổi.
                        Nếu chưa chuyển tiền, hãy hủy yêu cầu này.
                        Nếu đã chuyển, hãy báo cho lễ tân đối chiếu.
                    </div>
                <?php endif; ?>

                <form method="post">
                    <?= csrf() ?>
                    <input type="hidden" name="id" value="<?= $id ?>">
                    <input type="hidden" name="transfer_id"
                           value="<?= (int) $open['id'] ?>">

                    <div class="actions align-left">
                        <button class="button" name="action" value="report">
                            TÔI ĐÃ CHUYỂN KHOẢN
                        </button>
                        <button class="button outline" name="action" value="cancel">
                            CHƯA CHUYỂN – HỦY YÊU CẦU
                        </button>
                    </div>
                </form>
            <?php endif; ?>
        </div>

    <?php elseif (!transfer_payable($booking)): ?>
        <div class="notice">Đơn này không còn nhận thanh toán mới.</div>

    <?php elseif ($amounts['due'] === 0): ?>
        <div class="notice success">Bạn đã thanh toán đủ.</div>

    <?php elseif (!transfer_bank_ready()): ?>
        <div class="notice">
            Khách sạn chưa mở thanh toán chuyển khoản.
            Vui lòng liên hệ lễ tân.
        </div>

    <?php else: ?>
        <div class="panel" style="margin-top:24px">
            <h2>Chọn khoản muốn chuyển</h2>

            <?php
            $options = [];

            if ($booking['status'] !== 'checked_in' && $amounts['deposit'] > 0) {
                $options['deposit'] = $amounts['deposit'];
            }

            $options['balance'] = $amounts['due'];
            ?>

            <?php foreach ($options as $purpose => $value): ?>
                <form method="post" style="margin-bottom:16px">
                    <?= csrf() ?>
                    <input type="hidden" name="id" value="<?= $id ?>">
                    <input type="hidden" name="action" value="create">
                    <input type="hidden" name="purpose" value="<?= e($purpose) ?>">
                    <input type="hidden" name="quoted_amount" value="<?= (int) $value ?>">

                    <button type="submit" class="button full">
                        <?= e(transfer_purpose_name($purpose)) ?>
                        — <?= money($value) ?>
                    </button>
                </form>
            <?php endforeach; ?>

            <small>
                Mức cọc bằng <?= (int) $bank['deposit_percent'] ?>% tiền phòng.
                Khoản đã thanh toán được trừ vào số cần chuyển.
            </small>
        </div>
    <?php endif; ?>

    <h2 style="margin-top:32px">Lịch sử chuyển khoản</h2>

    <?php if (!$transfers): ?>
        <p>Chưa có yêu cầu chuyển khoản.</p>
    <?php endif; ?>

    <?php foreach ($transfers as $item): ?>
        <article class="panel" style="margin-bottom:12px">
            <strong><?= e(transfer_purpose_name($item['purpose'])) ?></strong>
            · <?= money($item['amount']) ?>

            <p>
                <?= e($item['transfer_code']) ?> ·
                <?= e(transfer_status_name($item['status'])) ?>
            </p>

            <?php if ($item['staff_note'] !== ''): ?>
                <p>Phản hồi: <?= e($item['staff_note']) ?></p>
            <?php endif; ?>
        </article>
    <?php endforeach; ?>

    <h2 style="margin-top:32px">Tiền đã hoàn cho đơn này</h2>

    <?php if (!$refunds): ?>
        <p>Chưa ghi nhận hoàn tiền.</p>
    <?php else: ?>
        <?php foreach ($refunds as $refund): ?>
            <p>
                <?= e(date('d/m/Y H:i', strtotime($refund['created_at']))) ?>
                · <?= money($refund['amount']) ?>
                · <?= $refund['method'] === 'bank'
                    ? 'Chuyển khoản' : 'Tiền mặt' ?>
            </p>
        <?php endforeach; ?>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>