<?php
require_once __DIR__ . '/operations.php';
require_once __DIR__ . '/services.php';
require_once __DIR__ . '/promotions.php';

function change_request_status(string $status): string
{
    return [
        'pending' => 'Chờ xử lý',
        'approved' => 'Đã duyệt',
        'rejected' => 'Đã từ chối',
        'withdrawn' => 'Khách đã rút yêu cầu'
    ][$status] ?? $status;
}

/**
 * Giữ cùng cách tạo chữ ký để các yêu cầu đang chờ
 * từ trước khi thêm khuyến mãi vẫn có thể được kiểm tra.
 */
function change_booking_signature(array $booking): string
{
    $values = [];

    foreach ([
        'room_type_id',
        'check_in',
        'check_out',
        'quantity',
        'guests',
        'nightly_price',
        'total_price'
    ] as $field) {
        $values[] = (string) $booking[$field];
    }

    return hash('sha256', implode('|', $values));
}

/**
 * Gọi bên trong transaction.
 * Khóa loại phòng cũ và mới theo thứ tự ID, sau đó khóa đơn.
 */
function change_lock_booking(int $bookingId, int $targetTypeId): array
{
    $initial = query(
        'SELECT room_type_id FROM bookings WHERE id = ?',
        [$bookingId]
    )->fetch();

    if (!$initial) {
        throw new DomainException('Không tìm thấy đơn đặt phòng.');
    }

    $typeIds = array_unique([
        (int) $initial['room_type_id'],
        $targetTypeId
    ]);

    sort($typeIds, SORT_NUMERIC);

    foreach ($typeIds as $typeId) {
        $exists = query(
            'SELECT id FROM room_types WHERE id = ? FOR UPDATE',
            [$typeId]
        )->fetchColumn();

        if (!$exists) {
            throw new DomainException('Loại phòng không tồn tại.');
        }
    }

    $booking = query(
        'SELECT * FROM bookings WHERE id = ? FOR UPDATE',
        [$bookingId]
    )->fetch();

    if (
        !$booking
        || (int) $booking['room_type_id'] !== (int) $initial['room_type_id']
    ) {
        throw new DomainException(
            'Đơn vừa thay đổi. Vui lòng tải lại trang và thử lại.'
        );
    }

    return $booking;
}

function change_assert_allowed(array $booking): void
{
    if (!in_array($booking['status'], ['pending', 'confirmed'], true)) {
        throw new DomainException(
            'Chỉ được đổi đơn đang chờ xác nhận hoặc đã xác nhận.'
        );
    }

    if ($booking['check_in'] <= date('Y-m-d')) {
        throw new DomainException(
            'Chỉ được yêu cầu đổi trước ngày nhận phòng.'
        );
    }

    $assigned = (int) query(
        'SELECT COUNT(*) FROM room_assignments WHERE booking_id = ?',
        [$booking['id']]
    )->fetchColumn();

    if ($assigned > 0) {
        throw new DomainException(
            'Đơn đã phân phòng thực tế. Vui lòng liên hệ lễ tân.'
        );
    }
}

/**
 * Tính số phòng trống xuyên suốt khoảng ngày mới.
 * Loại chính đơn đang đổi khỏi số phòng đã giữ.
 */
function change_available(
    int $typeId,
    string $checkIn,
    string $checkOut,
    int $excludeBookingId
): int {
    $stock = sellable_count($typeId);
    $peak = 0;
    $end = new DateTimeImmutable($checkOut);
    $today = date('Y-m-d');

    for (
        $date = new DateTimeImmutable($checkIn);
        $date < $end;
        $date = $date->modify('+1 day')
    ) {
        $day = $date->format('Y-m-d');

        $used = (int) query(
            "SELECT COALESCE(SUM(quantity), 0)
             FROM bookings
             WHERE room_type_id = ?
               AND id <> ?
               AND status IN ('pending', 'confirmed', 'checked_in')
               AND check_in <= ?
               AND (
                   check_out > ?
                   OR (status = 'checked_in' AND check_out <= ?)
               )",
            [$typeId, $excludeBookingId, $day, $day, $today]
        )->fetchColumn();

        $peak = max($peak, $used);
    }

    return max(0, $stock - $peak);
}

/**
 * Tính báo giá cho phương án mới.
 * total_price là tiền phòng sau ưu đãi, chưa cộng dịch vụ.
 */
function change_quote(
    array $booking,
    int $typeId,
    string $checkIn,
    string $checkOut
): array {
    change_assert_allowed($booking);

    $nights = valid_dates($checkIn, $checkOut);

    if ($checkIn <= date('Y-m-d')) {
        throw new DomainException(
            'Ngày nhận phòng mới phải sau hôm nay.'
        );
    }

    if (
        $typeId === (int) $booking['room_type_id']
        && $checkIn === $booking['check_in']
        && $checkOut === $booking['check_out']
    ) {
        throw new DomainException(
            'Bạn chưa thay đổi ngày lưu trú hoặc loại phòng.'
        );
    }

    $room = query(
        'SELECT * FROM room_types WHERE id = ? AND active = 1',
        [$typeId]
    )->fetch();

    if (!$room) {
        throw new DomainException(
            'Loại phòng mới đang ngừng nhận đặt.'
        );
    }

    $quantity = (int) $booking['quantity'];

    if ((int) $booking['guests'] > (int) $room['capacity'] * $quantity) {
        throw new DomainException(
            'Loại phòng mới không đủ sức chứa cho số khách của đơn.'
        );
    }

    $available = change_available(
        $typeId,
        $checkIn,
        $checkOut,
        (int) $booking['id']
    );

    if ($available < $quantity) {
        throw new DomainException(
            'Không còn đủ phòng cho phương án bạn chọn.'
        );
    }

    $nightlyPrice = (int) $room['price'];
    $grossPrice = $nightlyPrice * $quantity * $nights;

    // Dùng mã đã gắn với đơn và xét lại mức tiền tối thiểu.
    $pricing = promotion_for_changed_booking($booking, $grossPrice);

    return [
        'room' => $room,
        'nights' => $nights,
        'available' => $available,
        'nightly_price' => $nightlyPrice,
        'gross_price' => $grossPrice,
        'discount_amount' => $pricing['discount'],
        'total_price' => $pricing['net']
    ];
}

/**
 * Duyệt hoặc từ chối yêu cầu đổi đặt phòng.
 * Trang lễ tân chịu trách nhiệm mở, commit hoặc rollback transaction.
 */
function change_review(
    int $requestId,
    string $action,
    string $response,
    array $staff
): void {
    if (!in_array($action, ['approve', 'reject'], true)) {
        throw new DomainException('Thao tác không hợp lệ.');
    }

    if (mb_strlen($response) > 1000) {
        throw new DomainException('Phản hồi tối đa 1.000 ký tự.');
    }

    if ($action === 'reject' && mb_strlen($response) < 5) {
        throw new DomainException(
            'Vui lòng nhập lý do từ chối từ 5 ký tự.'
        );
    }

    $initial = query(
        'SELECT booking_id, requested_room_type_id
         FROM booking_change_requests
         WHERE id = ?',
        [$requestId]
    )->fetch();

    if (!$initial) {
        throw new DomainException('Không tìm thấy yêu cầu.');
    }

    $booking = change_lock_booking(
        (int) $initial['booking_id'],
        (int) $initial['requested_room_type_id']
    );

    $request = query(
        'SELECT *
         FROM booking_change_requests
         WHERE id = ?
         FOR UPDATE',
        [$requestId]
    )->fetch();

    if (!$request || $request['status'] !== 'pending') {
        throw new DomainException(
            'Yêu cầu đã được xử lý hoặc khách đã rút yêu cầu.'
        );
    }

    if ($action === 'approve') {
        if ((int) $booking['customer_id'] !== (int) $request['customer_id']) {
            throw new DomainException(
                'Tài khoản liên kết với đơn đã thay đổi.'
            );
        }

        if (change_booking_signature($booking) !== $request['booking_signature']) {
            throw new DomainException(
                'Thông tin đơn đã thay đổi sau khi gửi yêu cầu. '
                . 'Hãy từ chối và đề nghị khách gửi yêu cầu mới.'
            );
        }

        // Kiểm tra lại phòng trống và tính lại tiền ngay lúc duyệt.
        $quote = change_quote(
            $booking,
            (int) $request['requested_room_type_id'],
            $request['requested_check_in'],
            $request['requested_check_out']
        );

        if (
            $quote['nightly_price'] !== (int) $request['quoted_nightly_price']
            || $quote['total_price'] !== (int) $request['quoted_total_price']
        ) {
            throw new DomainException(
                'Giá phòng hoặc mức ưu đãi đã khác báo giá khi khách gửi yêu cầu. '
                . 'Hãy từ chối và đề nghị khách gửi yêu cầu mới.'
            );
        }

        $bookingId = (int) $booking['id'];
        $newGrandTotal = $quote['total_price'] + booking_service_total($bookingId);
        $paid = paid_amount($bookingId);

        if ($paid > $newGrandTotal) {
            throw new DomainException(
                'Cần hoàn lại ' . money($paid - $newGrandTotal)
                . ' và ghi nhận hoàn tiền trước khi duyệt.'
            );
        }

        query(
            'UPDATE bookings
             SET room_type_id = ?,
                 check_in = ?,
                 check_out = ?,
                 nightly_price = ?,
                 total_price = ?,
                 discount_amount = ?,
                 updated_at = NOW()
             WHERE id = ?',
            [
                $request['requested_room_type_id'],
                $request['requested_check_in'],
                $request['requested_check_out'],
                $quote['nightly_price'],
                $quote['total_price'],
                $quote['discount_amount'],
                $bookingId
            ]
        );

        $nextStatus = 'approved';
    } else {
        $nextStatus = 'rejected';
    }

    query(
        'UPDATE booking_change_requests
         SET status = ?,
             staff_response = ?,
             reviewed_by = ?,
             reviewed_at = NOW()
         WHERE id = ?',
        [
            $nextStatus,
            $response,
            $staff['id'],
            $requestId
        ]
    );

    audit(
        'booking_change_' . $nextStatus,
        'Yêu cầu #' . $requestId
        . ' / Đơn #' . $booking['id']
        . ' / ' . $response
    );
}