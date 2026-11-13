<?php
require __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/transfers.php';

$user = staff_required();
$title = 'Đối chiếu chuyển khoản';
$error = '';

if (post()) {
    check_csrf();

    try {
        $id = (int) input('transfer_id');
        $action = input('action');
        $note = input('staff_note');
        $reference = strtoupper(trim(input('bank_reference')));

        if (!in_array($action, ['confirm', 'reject'], true)) {
            throw new DomainException('Thao tác không hợp lệ.');
        }

        if (mb_strlen($note) > 500) {
            throw new DomainException('Ghi chú tối đa 500 ký tự.');
        }

        $bookingId = query(
            'SELECT booking_id FROM booking_transfers WHERE id = ?',
            [$id]
        )->fetchColumn();

        if (!$bookingId) {
            throw new DomainException('Không tìm thấy yêu cầu.');
        }

        db()->beginTransaction();

        // Khóa đơn trước, sau đó khóa yêu cầu như bên khách hàng.
        $booking = locked_booking((int) $bookingId);

        $transfer = query(
            'SELECT * FROM booking_transfers
             WHERE id = ? AND booking_id = ? FOR UPDATE',
            [$id, $bookingId]
        )->fetch();

        if (!$transfer || !in_array(
            $transfer['status'],
            ['awaiting', 'reported'],
            true
        )) {
            throw new DomainException(
                'Yêu cầu đã được xử lý. Không ghi nhận lần thứ hai.'
            );
        }

        if ($action === 'reject') {
            if (mb_strlen($note) < 5) {
                throw new DomainException(
                    'Nhập lý do từ chối ít nhất 5 ký tự để khách biết.'
                );
            }

            query(
                "UPDATE booking_transfers
                 SET status = 'rejected', staff_note = ?,
                     reviewed_by = ?, reviewed_at = NOW()
                 WHERE id = ?",
                [$note, $user['id'], $id]
            );

            audit('transfer_reject', 'Từ chối ' . $transfer['transfer_code']);
            $message = 'Đã từ chối yêu cầu. Không ghi tăng tiền đã thu.';
        } else {
            if (!transfer_payable($booking)) {
                throw new DomainException(
                    'Đơn đã đóng. Nếu thực tế có tiền đến, '
                    . 'cần đối soát riêng; không xác nhận vào đơn này.'
                );
            }

            if (
                $reference === ''
                || mb_strlen($reference) > 100
                || $reference === $transfer['transfer_code']
            ) {
                throw new DomainException(
                    'Hãy nhập mã giao dịch thực tế trên sao kê ngân hàng.'
                );
            }

            if (input('verified') !== '1') {
                throw new DomainException(
                    'Bạn cần xác nhận đã đối chiếu tiền vào, '
                    . 'số tiền và nội dung chuyển khoản.'
                );
            }

            $amounts = transfer_amounts($booking);

            if ((int) $transfer['amount'] > $amounts['due']) {
                throw new DomainException(
                    'Khoản chuyển vượt số còn phải thu. '
                    . 'Hãy đối soát trước khi ghi nhận.'
                );
            }

            $used = query(
                'SELECT id FROM booking_transfers WHERE bank_reference = ?',
                [$reference]
            )->fetchColumn();

            if ($used) {
                throw new DomainException(
                    'Mã giao dịch ngân hàng này đã được sử dụng.'
                );
            }

            query(
                "UPDATE booking_transfers
                 SET status = 'confirmed', bank_reference = ?,
                     staff_note = ?, reviewed_by = ?, reviewed_at = NOW()
                 WHERE id = ?",
                [$reference, $note, $user['id'], $id]
            );

            // Cùng transaction: hoặc lưu cả hai, hoặc không lưu gì.
            transfer_post_receipt($transfer, $reference);

            audit(
                'transfer_confirm',
                'Xác nhận ' . $transfer['transfer_code']
                . ' / Đơn #' . $bookingId
                . ' / ' . money($transfer['amount'])
            );

            $message = 'Đã xác nhận tiền vào và cập nhật thanh toán của đơn.';
        }

        db()->commit();
        flash($message);
        go('reception/transfers.php');
    } catch (DomainException $ex) {
        if (db()->inTransaction()) db()->rollBack();
        $error = $ex->getMessage();
    } catch (PDOException $ex) {
        if (db()->inTransaction()) db()->rollBack();
        error_log((string) $ex);
        $error = (int) ($ex->errorInfo[1] ?? 0) === 1062
            ? 'Giao dịch trùng. Hãy kiểm tra lại mã trên sao kê.'
            : 'Chưa lưu được kết quả. Vui lòng tải lại trang.';
    } catch (Throwable $ex) {
        if (db()->inTransaction()) db()->rollBack();
        error_log((string) $ex);
        $error = 'Chưa thể xử lý yêu cầu. Vui lòng thử lại.';
    }
}

$filter = input('filter', 'open');
$filter = $filter === 'all' ? 'all' : 'open';

$where = $filter === 'open'
    ? "WHERE t.status IN ('awaiting', 'reported')"
    : '';

$page = max(1, (int) input('page', '1'));
$perPage = 15;

$count = (int) query(
    "SELECT COUNT(*) FROM booking_transfers t $where"
)->fetchColumn();

$totalPages = max(1, (int) ceil($count / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

$rows = query(
    "SELECT t.*, b.guest_name, b.status AS booking_status
     FROM booking_transfers t
     JOIN bookings b ON b.id = t.booking_id
     $where
     ORDER BY (t.status = 'reported') DESC, t.id DESC
     LIMIT $perPage OFFSET $offset"
)->fetchAll();

require __DIR__ . '/../includes/staff_header.php';
?>

<div class="actions align-left">
    <a class="button outline"
       href="<?= e(url('reception/transfers.php')) ?>">
        CHỜ ĐỐI CHIẾU
    </a>
    <a class="button outline"
       href="<?= e(url('reception/transfers.php?filter=all')) ?>">
        TẤT CẢ YÊU CẦU
    </a>
</div>

<p>
    Đối chiếu sao kê trước khi xác nhận. Với khoản có mã OI,
    chỉ ghi thu một lần tại trang này.
</p>

<?php if (!$rows): ?>
    <div class="notice">Không có yêu cầu trong danh sách này.</div>
<?php endif; ?>

<?php foreach ($rows as $row): ?>
    <article class="panel" style="margin-bottom:20px">
        <div class="eyebrow">
            <?= e(transfer_status_name($row['status'])) ?>
        </div>

        <h2><?= e($row['guest_name']) ?></h2>

        <p>
            Đơn #<?= (int) $row['booking_id'] ?>
            · <?= e(status_name($row['booking_status'])) ?>
        </p>
        <p>
            <?= e(transfer_purpose_name($row['purpose'])) ?>:
            <strong><?= money($row['amount']) ?></strong>
        </p>
        <p>
            Nội dung chuyển khoản:
            <strong><?= e($row['transfer_code']) ?></strong>
        </p>
        <p>Tạo lúc: <?= e($row['created_at']) ?></p>

        <p>
            <a class="text-link"
               href="<?= e(url(
                   'reception/booking.php?id=' . (int) $row['booking_id']
               )) ?>">
                Xem đơn, giao dịch và ghi nhận hoàn tiền →
            </a>
        </p>

        <?php if (in_array($row['status'], ['awaiting', 'reported'], true)): ?>
            <form method="post">
                <?= csrf() ?>
                <input type="hidden" name="transfer_id"
                       value="<?= (int) $row['id'] ?>">

                <label>
                    Mã giao dịch ngân hàng
                    <input name="bank_reference" maxlength="100"
                           placeholder="Lấy từ sao kê tài khoản nhận">
                </label>

                <label>
                    Phản hồi cho khách
                    <textarea name="staff_note" rows="2" maxlength="500"
                              placeholder="Bắt buộc nhập khi từ chối"></textarea>
                </label>

                <label class="checkbox">
                    <input type="checkbox" name="verified" value="1">
                    <span>
                        Tôi đã thấy tiền vào, kiểm tra đúng số tiền,
                        nội dung chuyển khoản và chưa ghi thu khoản này.
                    </span>
                </label>

                <div class="actions align-left">
                    <button class="button" name="action" value="confirm">
                        XÁC NHẬN ĐÃ NHẬN TIỀN
                    </button>
                    <button class="button outline" name="action" value="reject">
                        TỪ CHỐI
                    </button>
                </div>
            </form>
        <?php else: ?>
            <?php if ($row['bank_reference']): ?>
                <p>Mã giao dịch: <?= e($row['bank_reference']) ?></p>
            <?php endif; ?>
            <?php if ($row['staff_note'] !== ''): ?>
                <p>Phản hồi: <?= e($row['staff_note']) ?></p>
            <?php endif; ?>
        <?php endif; ?>
    </article>
<?php endforeach; ?>

<nav class="actions" aria-label="Phân trang chuyển khoản">
    <?php if ($page > 1): ?>
        <a href="<?= e(url(
            'reception/transfers.php?filter=' . $filter . '&page=' . ($page - 1)
        )) ?>">← Trang trước</a>
    <?php endif; ?>

    <span>Trang <?= $page ?> / <?= $totalPages ?></span>

    <?php if ($page < $totalPages): ?>
        <a href="<?= e(url(
            'reception/transfers.php?filter=' . $filter . '&page=' . ($page + 1)
        )) ?>">Trang sau →</a>
    <?php endif; ?>
</nav>

<?php require __DIR__ . '/../includes/staff_footer.php'; ?>