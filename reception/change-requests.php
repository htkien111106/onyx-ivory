<?php
require __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/booking_changes.php';

$user = staff_required();

$title = 'Yêu cầu thay đổi đặt phòng';
$error = '';
$id = (int) input('id');

if (post()) {
    check_csrf();

    try {
        db()->beginTransaction();

        change_review(
            $id,
            input('action'),
            input('staff_response'),
            $user
        );

        db()->commit();

        flash('Đã xử lý yêu cầu thay đổi đặt phòng.');
        go('reception/change-requests.php?id=' . $id);
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

        $error = 'Chưa thể xử lý yêu cầu. '
            . 'Vui lòng tải lại trang và thử lại.';
    }
}

$request = null;
$booking = null;
$quote = null;
$reviewError = '';
$paid = 0;
$serviceTotal = 0;

if ($id > 0) {
    $request = query(
        'SELECT c.*, r.name AS requested_room_name
         FROM booking_change_requests c
         JOIN room_types r ON r.id = c.requested_room_type_id
         WHERE c.id = ?',
        [$id]
    )->fetch();

    if (!$request) {
        http_response_code(404);
        exit('Không tìm thấy yêu cầu.');
    }

    $booking = query(
        'SELECT b.*, r.name AS room_name
         FROM bookings b
         JOIN room_types r ON r.id = b.room_type_id
         WHERE b.id = ?',
        [$request['booking_id']]
    )->fetch();

    $paid = paid_amount((int) $booking['id']);
    $serviceTotal = booking_service_total((int) $booking['id']);

    if ($request['status'] === 'pending') {
        try {
            if (
                change_booking_signature($booking)
                !== $request['booking_signature']
            ) {
                throw new DomainException(
                    'Đơn đã thay đổi sau khi khách gửi yêu cầu. '
                    . 'Cần từ chối và đề nghị khách gửi yêu cầu mới.'
                );
            }

            $quote = change_quote(
                $booking,
                (int) $request['requested_room_type_id'],
                $request['requested_check_in'],
                $request['requested_check_out']
            );

            if (
                $quote['nightly_price']
                    !== (int) $request['quoted_nightly_price'] ||
                $quote['total_price']
                    !== (int) $request['quoted_total_price']
            ) {
                throw new DomainException(
                    'Giá hiện tại đã thay đổi so với mức khách đồng ý. '
                    . 'Cần đề nghị khách gửi yêu cầu mới.'
                );
            }

            $newTotal = $quote['total_price'] + $serviceTotal;

            if ($paid > $newTotal) {
                throw new DomainException(
                    'Cần ghi nhận hoàn '
                    . money($paid - $newTotal)
                    . ' cho khách trước khi duyệt.'
                );
            }
        } catch (DomainException $exception) {
            $reviewError = $exception->getMessage();
        }
    }
} else {
    $page = max(1, min(100000, (int) input('page', '1')));
    $perPage = 20;

    $total = (int) query(
        'SELECT COUNT(*) FROM booking_change_requests'
    )->fetchColumn();

    $totalPages = max(1, (int) ceil($total / $perPage));
    $page = min($page, $totalPages);
    $offset = ($page - 1) * $perPage;

    $requests = query(
        "SELECT c.*, b.guest_name, r.name AS requested_room_name
         FROM booking_change_requests c
         JOIN bookings b ON b.id = c.booking_id
         JOIN room_types r ON r.id = c.requested_room_type_id
         ORDER BY (c.status = 'pending') DESC, c.id DESC
         LIMIT $perPage OFFSET $offset"
    )->fetchAll();
}

require __DIR__ . '/../includes/staff_header.php';
?>

<?php if ($request): ?>
    <div class="actions align-left">
        <a class="button outline"
           href="<?= e(url('reception/change-requests.php')) ?>">
            ← DANH SÁCH YÊU CẦU
        </a>

        <a class="button outline"
           href="<?= e(url(
               'reception/booking.php?id=' . (int) $booking['id']
           )) ?>">
            XEM ĐƠN / THU HOÀN TIỀN
        </a>
    </div>

    <div class="panel">
        <div class="eyebrow">
            <?= e(change_request_status($request['status'])) ?>
        </div>

        <h2><?= e($booking['guest_name']) ?></h2>
        <p><?= e($booking['email']) ?> · <?= e($booking['phone']) ?></p>

        <p>
            Đơn #<?= (int) $booking['id'] ?>
            — <?= e(status_name($booking['status'])) ?>
        </p>

        <p style="white-space: pre-wrap; overflow-wrap: anywhere;">Lý do của khách: <?= e($request['reason']) ?></p>
    </div>

    <div class="detail-grid" style="margin-top: 24px;">
        <div class="panel">
            <h2>Đơn hiện tại</h2>
            <p><strong><?= e($booking['room_name']) ?></strong></p>
            <p>
                <?= e($booking['check_in']) ?>
                → <?= e($booking['check_out']) ?>
            </p>
            <p>
                <?= (int) $booking['quantity'] ?> phòng ·
                <?= (int) $booking['guests'] ?> khách
            </p>
            <p>Tiền phòng: <?= money($booking['total_price']) ?></p>
        </div>

        <div class="panel">
            <h2>Phương án khách đề nghị</h2>
            <p>
                <strong><?= e($request['requested_room_name']) ?></strong>
            </p>
            <p>
                <?= e($request['requested_check_in']) ?>
                → <?= e($request['requested_check_out']) ?>
            </p>
            <p>
                Đơn giá khách đã đồng ý:
                <?= money($request['quoted_nightly_price']) ?>
                / phòng / đêm
            </p>
            <p>
                Tiền phòng mới:
                <strong>
                    <?= money($request['quoted_total_price']) ?>
                </strong>
            </p>

            <?php
            $difference = (int) $request['quoted_total_price']
                - (int) $request['original_total_price'];
            ?>

            <p>
                Chênh lệch so với lúc gửi:
                <strong>
                    <?php if ($difference > 0): ?>
                        Tăng <?= money($difference) ?>
                    <?php elseif ($difference < 0): ?>
                        Giảm <?= money(-$difference) ?>
                    <?php else: ?>
                        Không thay đổi
                    <?php endif; ?>
                </strong>
            </p>
        </div>
    </div>

    <div class="panel" style="margin-top: 24px;">
        <h2>Thanh toán theo phương án đề nghị</h2>

        <?php
        $proposedGrand = (int) $request['quoted_total_price']
            + $serviceTotal;
        ?>

        <p>Tiền dịch vụ hiện có: <?= money($serviceTotal) ?></p>
        <p>
            Tổng phòng và dịch vụ dự kiến:
            <strong><?= money($proposedGrand) ?></strong>
        </p>
        <p>Đã thu sau hoàn tiền: <?= money($paid) ?></p>
        <p>
            Còn phải thu sau khi duyệt:
            <?= money(max(0, $proposedGrand - $paid)) ?>
        </p>
        <p>
            Cần hoàn trước khi duyệt:
            <?= money(max(0, $paid - $proposedGrand)) ?>
        </p>
    </div>

    <?php if ($request['status'] === 'pending'): ?>
        <?php if ($reviewError !== ''): ?>
            <div class="notice error" role="alert">
                <?= e($reviewError) ?>
            </div>
        <?php else: ?>
            <div class="notice success">
                Hiện còn <?= (int) $quote['available'] ?> phòng phù hợp;
                đơn cần <?= (int) $booking['quantity'] ?> phòng.
                Hệ thống sẽ kiểm tra lại khi bấm duyệt.
            </div>
        <?php endif; ?>

        <form method="post" class="panel">
            <?= csrf() ?>
            <input type="hidden" name="id" value="<?= $id ?>">

            <h2>Phản hồi yêu cầu</h2>

            <label>
                Nội dung phản hồi
                <textarea
                    name="staff_response"
                    rows="4"
                    maxlength="1000"
                    placeholder="Bắt buộc nhập lý do nếu từ chối."
                ><?= e(input('staff_response')) ?></textarea>
            </label>

            <div class="actions align-left">
                <?php if ($reviewError === ''): ?>
                    <button
                        class="button"
                        type="submit"
                        name="action"
                        value="approve"
                    >
                        DUYỆT &amp; CẬP NHẬT ĐƠN
                    </button>
                <?php endif; ?>

                <button
                    class="button outline"
                    type="submit"
                    name="action"
                    value="reject"
                >
                    TỪ CHỐI YÊU CẦU
                </button>
            </div>

            <p>
                Duyệt sẽ cập nhật ngày lưu trú, loại phòng và tiền phòng.
                Số phòng, số khách, dịch vụ và giao dịch đã ghi nhận
                được giữ nguyên.
            </p>
        </form>
    <?php else: ?>
        <div class="panel" style="margin-top: 24px;">
            <h2>Kết quả xử lý</h2>
            <p><?= e(change_request_status($request['status'])) ?></p>

            <p style="white-space: pre-wrap; overflow-wrap: anywhere;"><?= e(
                $request['staff_response'] ?: 'Không có phản hồi bổ sung.'
            ) ?></p>

            <?php if ($request['reviewed_at']): ?>
                <p>
                    Thời gian:
                    <?= e(date(
                        'd/m/Y H:i',
                        strtotime($request['reviewed_at'])
                    )) ?>
                </p>
            <?php endif; ?>
        </div>
    <?php endif; ?>

<?php else: ?>
    <p>
        Yêu cầu chờ xử lý được ưu tiên hiển thị trước.
        Chọn một yêu cầu để kiểm tra phòng và chênh lệch giá.
    </p>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Yêu cầu / đơn</th>
                    <th>Khách hàng</th>
                    <th>Phương án mới</th>
                    <th>Tiền phòng</th>
                    <th>Trạng thái</th>
                    <th>Thao tác</th>
                </tr>
            </thead>

            <tbody>
                <?php foreach ($requests as $item): ?>
                    <tr>
                        <td>
                            #<?= (int) $item['id'] ?><br>
                            Đơn #<?= (int) $item['booking_id'] ?>
                        </td>
                        <td><?= e($item['guest_name']) ?></td>
                        <td>
                            <?= e($item['requested_room_name']) ?><br>
                            <?= e($item['requested_check_in']) ?>
                            → <?= e($item['requested_check_out']) ?>
                        </td>
                        <td><?= money($item['quoted_total_price']) ?></td>
                        <td>
                            <?= e(change_request_status($item['status'])) ?>
                        </td>
                        <td>
                            <a class="text-link"
                               href="<?= e(url(
                                   'reception/change-requests.php?id='
                                   . (int) $item['id']
                               )) ?>">
                                Xem yêu cầu →
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>

                <?php if (!$requests): ?>
                    <tr>
                        <td colspan="6">Chưa có yêu cầu thay đổi.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <nav class="actions" aria-label="Phân trang yêu cầu">
        <?php if ($page > 1): ?>
            <a href="<?= e(url(
                'reception/change-requests.php?page=' . ($page - 1)
            )) ?>">
                ← Trang trước
            </a>
        <?php endif; ?>

        <span>Trang <?= $page ?> / <?= $totalPages ?></span>

        <?php if ($page < $totalPages): ?>
            <a href="<?= e(url(
                'reception/change-requests.php?page=' . ($page + 1)
            )) ?>">
                Trang sau →
            </a>
        <?php endif; ?>
    </nav>
<?php endif; ?>

<?php require __DIR__ . '/../includes/staff_footer.php'; ?>