<?php
require __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/customer.php';
require_once __DIR__ . '/includes/operations.php';

$customer = customer_required();

// Không cho phép hủy bằng cách mở đường dẫn trực tiếp.
if (!post()) {
    go('account.php#history');
}

check_csrf();

// Mã đơn chỉ được nhận từ biểu mẫu POST.
$idValue = $_POST['booking_id'] ?? '';

if (
    !is_string($idValue)
    || !ctype_digit($idValue)
    || (int) $idValue < 1
) {
    $_SESSION['cancel_booking_error'] = 'Mã đơn đặt phòng không hợp lệ.';
    go('account.php#history');
}

$bookingId = (int) $idValue;

// Kiểm tra quyền sở hữu trước khi xử lý.
$booking = query(
    'SELECT id, room_type_id
     FROM bookings
     WHERE id = ? AND customer_id = ?
     LIMIT 1',
    [$bookingId, $customer['id']]
)->fetch();

if (!$booking) {
    $_SESSION['cancel_booking_error'] =
        'Không tìm thấy đơn đặt phòng thuộc tài khoản của bạn.';

    go('account.php#history');
}

try {
    db()->beginTransaction();

    // Giữ cùng thứ tự khóa với các nghiệp vụ đặt phòng và lễ tân.
    query(
        'SELECT id FROM room_types WHERE id = ? FOR UPDATE',
        [$booking['room_type_id']]
    )->fetch();

    // Đọc lại trạng thái và quyền sở hữu sau khi khóa.
    $current = query(
        'SELECT *
         FROM bookings
         WHERE id = ? AND customer_id = ?
         FOR UPDATE',
        [$bookingId, $customer['id']]
    )->fetch();

    if (!$current) {
        throw new DomainException(
            'Không tìm thấy đơn đặt phòng thuộc tài khoản của bạn.'
        );
    }

    // Gửi lại yêu cầu hủy không làm phát sinh xử lý lần hai.
    if ($current['status'] === 'cancelled') {
        db()->commit();

        flash('Đơn đặt phòng này đã được hủy trước đó.');
        go('booking-detail.php?id=' . $bookingId);
    }

    if (
        !in_array(
            $current['status'],
            ['pending', 'confirmed'],
            true
        )
    ) {
        throw new DomainException(
            'Chỉ có thể hủy đơn đang chờ xác nhận hoặc đã xác nhận. '
            . 'Vui lòng liên hệ lễ tân nếu cần hỗ trợ.'
        );
    }

    if ($current['check_in'] <= date('Y-m-d')) {
        throw new DomainException(
            'Đã đến hoặc qua ngày nhận phòng. '
            . 'Bạn không thể tự hủy trực tuyến, vui lòng liên hệ lễ tân.'
        );
    }

    // Hàm hiện có: tổng tiền đã thu trừ tổng tiền đã hoàn.
    if (paid_amount($bookingId) !== 0) {
        throw new DomainException(
            'Đơn còn số dư thanh toán cần được xử lý. '
            . 'Vui lòng liên hệ lễ tân để được hỗ trợ hủy và hoàn tiền.'
        );
    }

    query(
        "UPDATE bookings
         SET status = 'cancelled', updated_at = NOW()
         WHERE id = ? AND customer_id = ?",
        [$bookingId, $customer['id']]
    );

    db()->commit();

    // Không xóa đơn: vẫn giữ trong lịch sử của khách hàng.
    flash('Đã hủy đơn đặt phòng thành công.');
    go('booking-detail.php?id=' . $bookingId);

} catch (DomainException $ex) {
    if (db()->inTransaction()) {
        db()->rollBack();
    }

    $_SESSION['cancel_booking_error'] = $ex->getMessage();
    go('booking-detail.php?id=' . $bookingId);

} catch (Throwable $ex) {
    if (db()->inTransaction()) {
        db()->rollBack();
    }

    error_log((string) $ex);

    $_SESSION['cancel_booking_error'] =
        'Chưa thể hủy đơn lúc này. Vui lòng thử lại hoặc liên hệ lễ tân.';

    go('booking-detail.php?id=' . $bookingId);
}