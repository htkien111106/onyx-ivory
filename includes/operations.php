<?php
require_once __DIR__ . '/services.php';
require_once __DIR__ . '/loyalty.php';

// Các thao tác thay đổi phòng khóa loại phòng trước,
// sau đó mới khóa đơn hoặc phòng thực tế.
function locked_booking(int $id): array
{
    $booking = query(
        'SELECT * FROM bookings WHERE id = ?',
        [$id]
    )->fetch();

    if (!$booking) {
        throw new DomainException('Không tìm thấy đơn.');
    }

    query(
        'SELECT id FROM room_types WHERE id = ? FOR UPDATE',
        [$booking['room_type_id']]
    )->fetch();

    $locked = query(
        'SELECT b.*, r.name AS room_name
         FROM bookings b
         INNER JOIN room_types r ON r.id = b.room_type_id
         WHERE b.id = ?
         FOR UPDATE',
        [$id]
    )->fetch();

    if (!$locked) {
        throw new DomainException('Không tìm thấy đơn.');
    }

    return $locked;
}

// Tiền đã thu ròng = tổng thu - tổng hoàn.
function paid_amount(int $id): int
{
    return (int) query(
        "SELECT COALESCE(
            SUM(
                CASE
                    WHEN kind = 'payment' THEN amount
                    ELSE -CAST(amount AS SIGNED)
                END
            ),
            0
         )
         FROM payments
         WHERE booking_id = ?",
        [$id]
    )->fetchColumn();
}

// Số phòng đã được giữ chỗ cao nhất theo thời gian.
function peak_committed(int $type): int
{
    $events = [];
    $today = date('Y-m-d');

    $bookings = query(
        "SELECT *
         FROM bookings
         WHERE room_type_id = ?
           AND status IN ('pending', 'confirmed', 'checked_in')
           AND (check_out > ? OR status = 'checked_in')",
        [$type, $today]
    )->fetchAll();

    foreach ($bookings as $booking) {
        $start = max($today, $booking['check_in']);

        $end = (
            $booking['status'] === 'checked_in'
            && $booking['check_out'] <= $today
        )
            ? '9999-12-31'
            : $booking['check_out'];

        $events[$start] = ($events[$start] ?? 0)
            + (int) $booking['quantity'];

        $events[$end] = ($events[$end] ?? 0)
            - (int) $booking['quantity'];
    }

    ksort($events);

    $peak = 0;
    $current = 0;

    foreach ($events as $change) {
        $current += $change;
        $peak = max($peak, $current);
    }

    return $peak;
}

// Đọc danh sách phòng được chọn từ biểu mẫu.
function selected_rooms(): array
{
    $raw = $_POST['room_ids'] ?? [];

    if (!is_array($raw) || count($raw) > 5) {
        throw new DomainException('Danh sách phòng không hợp lệ.');
    }

    $ids = [];

    foreach ($raw as $value) {
        if (
            !is_string($value)
            || !ctype_digit($value)
            || (int) $value < 1
        ) {
            throw new DomainException('Mã phòng không hợp lệ.');
        }

        $ids[] = (int) $value;
    }

    if (count(array_unique($ids)) !== count($ids)) {
        throw new DomainException(
            'Không được chọn một phòng hai lần.'
        );
    }

    return $ids;
}

// Phân phòng thực tế cho đơn.
function assign_rooms(array $booking, array $ids): void
{
    if (count($ids) !== (int) $booking['quantity']) {
        throw new DomainException(
            'Phải chọn đúng '
            . $booking['quantity']
            . ' phòng cụ thể.'
        );
    }

    $assignedCount = (int) query(
        'SELECT COUNT(*)
         FROM room_assignments
         WHERE booking_id = ?',
        [$booking['id']]
    )->fetchColumn();

    if ($assignedCount > 0) {
        throw new DomainException('Đơn đã được phân phòng.');
    }

    foreach ($ids as $roomId) {
        $room = query(
            'SELECT * FROM hotel_rooms WHERE id = ? FOR UPDATE',
            [$roomId]
        )->fetch();

        if (
            !$room
            || (int) $room['room_type_id']
                !== (int) $booking['room_type_id']
            || !(int) $room['active']
            || $room['housekeeping'] !== 'ready'
        ) {
            throw new DomainException(
                'Phòng được chọn sai loại, chưa dọn '
                . 'hoặc đang ngừng sử dụng.'
            );
        }

        $occupied = (int) query(
            "SELECT COUNT(*)
             FROM room_assignments a
             INNER JOIN bookings b ON b.id = a.booking_id
             WHERE a.room_id = ?
               AND b.status = 'checked_in'",
            [$roomId]
        )->fetchColumn();

        if ($occupied > 0) {
            throw new DomainException(
                'Phòng vừa được khách khác nhận. Vui lòng chọn lại.'
            );
        }
    }

    foreach ($ids as $roomId) {
        query(
            'INSERT INTO room_assignments (booking_id, room_id)
             VALUES (?, ?)',
            [$booking['id'], $roomId]
        );
    }
}

// Chuyển trạng thái đơn đặt phòng.
function change_booking(
    int $id,
    string $next,
    array $ids = []
): void {
    $booking = locked_booking($id);

    $allowed = [
        'pending' => ['confirmed', 'cancelled'],
        'confirmed' => ['checked_in', 'cancelled'],
        'checked_in' => ['completed'],
        'completed' => [],
        'cancelled' => []
    ];

    if (
        !in_array(
            $next,
            $allowed[$booking['status']] ?? [],
            true
        )
    ) {
        throw new DomainException(
            'Trạng thái đơn đã thay đổi hoặc thao tác không hợp lệ.'
        );
    }

    if (
        $next === 'confirmed'
        && $booking['check_in'] < date('Y-m-d')
    ) {
        throw new DomainException(
            'Ngày nhận phòng đã qua; hãy hủy đơn và tạo đơn mới.'
        );
    }

    if ($next === 'cancelled' && paid_amount($id) !== 0) {
        throw new DomainException(
            'Đơn còn tiền đã thu. Hãy hoàn đủ tiền '
            . 'và ghi nhận hoàn tiền trước khi hủy.'
        );
    }

    if ($next === 'checked_in') {
        if (
            $booking['check_in'] > date('Y-m-d')
            || $booking['check_out'] <= date('Y-m-d')
        ) {
            throw new DomainException(
                'Chỉ nhận phòng trong khoảng ngày lưu trú.'
            );
        }

        $legacyCount = (int) query(
            "SELECT COUNT(*)
             FROM bookings b
             WHERE b.room_type_id = ?
               AND b.status = 'checked_in'
               AND (
                   SELECT COUNT(*)
                   FROM room_assignments a
                   WHERE a.booking_id = b.id
               ) <> b.quantity",
            [$booking['room_type_id']]
        )->fetchColumn();

        if ($legacyCount > 0) {
            throw new DomainException(
                'Cần bổ sung phòng cho các đơn đang lưu trú '
                . 'từ bản cũ trước.'
            );
        }

        assign_rooms($booking, $ids);

        query(
            'UPDATE bookings
             SET actual_check_in = NOW()
             WHERE id = ?',
            [$id]
        );
    }

    if ($next === 'completed') {
        if ($booking['check_out'] > date('Y-m-d')) {
            throw new DomainException(
                'Chưa đến ngày trả phòng. '
                . 'Bản này chưa xử lý trả sớm.'
            );
        }

        // Tổng cần thanh toán bao gồm tiền phòng và dịch vụ chưa hủy.
        $grandTotal = booking_grand_total($booking);

        if (paid_amount($id) !== $grandTotal) {
            throw new DomainException(
                'Phải ghi nhận đủ tiền phòng và dịch vụ '
                . 'trước khi trả phòng.'
            );
        }

        $assigned = query(
            'SELECT room_id
             FROM room_assignments
             WHERE booking_id = ?',
            [$id]
        )->fetchAll();

        if (count($assigned) !== (int) $booking['quantity']) {
            throw new DomainException(
                'Bổ sung phòng thực tế cho đơn cũ trước khi trả phòng.'
            );
        }

        foreach ($assigned as $assignment) {
            query(
                "UPDATE hotel_rooms
                 SET housekeeping = 'dirty'
                 WHERE id = ?",
                [$assignment['room_id']]
            );
        }

        query(
            'UPDATE bookings
             SET actual_check_out = NOW()
             WHERE id = ?',
            [$id]
        );
    }

    query(
        'UPDATE bookings SET status = ? WHERE id = ?',
        [$next, $id]
    );

    if ($next === 'checked_in') {
        loyalty_grant_at_checkin($id);
    }

    audit(
        'booking_status',
        'Đơn #' . $id . ': ' . $booking['status'] . ' -> ' . $next
    );
}

// Bổ sung phòng thực tế cho dữ liệu lưu trú từ bản cũ.
function reconcile_legacy(int $id, array $ids): void
{
    $booking = locked_booking($id);

    if ($booking['status'] !== 'checked_in') {
        throw new DomainException(
            'Chỉ bổ sung phòng cho đơn đang lưu trú từ bản cũ.'
        );
    }

    assign_rooms($booking, $ids);

    audit(
        'legacy_assign',
        'Bổ sung phòng cho đơn cũ #' . $id
    );
}

// Ghi nhận thu tiền hoặc hoàn tiền.
function record_payment(
    int $id,
    string $kind,
    string $rawAmount,
    string $method,
    string $reference,
    string $key
): void {
    $booking = locked_booking($id);

    if (
        !preg_match('/^[a-f0-9]{32}$/', $key)
        || empty($_SESSION['payment_keys'][$key])
    ) {
        throw new DomainException(
            'Phiên ghi nhận thanh toán hết hạn; tải lại trang.'
        );
    }

    $oldBookingId = query(
        'SELECT booking_id
         FROM payments
         WHERE request_key = ?',
        [$key]
    )->fetchColumn();

    if ($oldBookingId !== false) {
        if ((int) $oldBookingId !== $id) {
            throw new DomainException('Mã giao dịch không hợp lệ.');
        }

        return;
    }

    if (
        !in_array(
            $booking['status'],
            ['pending', 'confirmed', 'checked_in'],
            true
        )
    ) {
        throw new DomainException(
            'Không ghi thu/hoàn tiền cho đơn đã hủy hoặc đã trả phòng.'
        );
    }

    if (
        !in_array($kind, ['payment', 'refund'], true)
        || !in_array($method, ['cash', 'bank'], true)
        || !ctype_digit($rawAmount)
        || strlen($rawAmount) > 12
        || (int) $rawAmount < 1
        || mb_strlen($reference) > 190
    ) {
        throw new DomainException(
            'Thông tin thanh toán không hợp lệ.'
        );
    }

    $amount = (int) $rawAmount;
    $paid = paid_amount($id);

    // Bao gồm cả tiền dịch vụ.
    $grandTotal = booking_grand_total($booking);

    if ($kind === 'payment' && $amount > $grandTotal - $paid) {
        throw new DomainException(
            'Số tiền thu vượt số còn phải thanh toán.'
        );
    }

    if ($kind === 'refund' && $amount > $paid) {
        throw new DomainException(
            'Số hoàn tiền vượt số tiền đã thu.'
        );
    }

    query(
        'INSERT INTO payments (
            booking_id,
            staff_id,
            kind,
            amount,
            method,
            reference,
            request_key
         ) VALUES (?, ?, ?, ?, ?, ?, ?)',
        [
            $id,
            $_SESSION['staff_id'],
            $kind,
            $amount,
            $method,
            $reference,
            $key
        ]
    );

    audit(
        $kind,
        'Đơn #' . $id . ': ' . money($amount) . ' / ' . $method
    );
}

// Cập nhật trạng thái vệ sinh và hoạt động của phòng.
function update_room_state(
    int $id,
    string $state,
    ?int $active = null
): void {
    $room = query(
        'SELECT * FROM hotel_rooms WHERE id = ?',
        [$id]
    )->fetch();

    if (!$room) {
        throw new DomainException('Không tìm thấy phòng.');
    }

    query(
        'SELECT id FROM room_types WHERE id = ? FOR UPDATE',
        [$room['room_type_id']]
    )->fetch();

    $room = query(
        'SELECT * FROM hotel_rooms WHERE id = ? FOR UPDATE',
        [$id]
    )->fetch();

    if (!$room) {
        throw new DomainException('Không tìm thấy phòng.');
    }

    if (!in_array($state, ['ready', 'dirty', 'maintenance'], true)) {
        throw new DomainException('Tình trạng phòng không hợp lệ.');
    }

    if ($active !== null && !in_array($active, [0, 1], true)) {
        throw new DomainException(
            'Trạng thái hoạt động của phòng không hợp lệ.'
        );
    }

    $occupied = (int) query(
        "SELECT COUNT(*)
         FROM room_assignments a
         INNER JOIN bookings b ON b.id = a.booking_id
         WHERE a.room_id = ?
           AND b.status = 'checked_in'",
        [$id]
    )->fetchColumn();

    if ($occupied > 0) {
        throw new DomainException(
            'Không đổi tình trạng hoặc đóng phòng khi khách đang ở.'
        );
    }

    $legacyCount = (int) query(
        "SELECT COUNT(*)
         FROM bookings b
         WHERE b.room_type_id = ?
           AND b.status = 'checked_in'
           AND (
               SELECT COUNT(*)
               FROM room_assignments a
               WHERE a.booking_id = b.id
           ) <> b.quantity",
        [$room['room_type_id']]
    )->fetchColumn();

    if ($legacyCount > 0) {
        throw new DomainException(
            'Cần bổ sung phòng cho đơn đang ở từ bản cũ trước.'
        );
    }

    $active = $active ?? (int) $room['active'];

    query(
        'UPDATE hotel_rooms
         SET housekeeping = ?, active = ?
         WHERE id = ?',
        [$state, $active, $id]
    );

    if (
        sellable_count((int) $room['room_type_id'])
        < peak_committed((int) $room['room_type_id'])
    ) {
        throw new DomainException(
            'Không thể giảm phòng khả dụng vì đã có đơn giữ chỗ.'
        );
    }

    query(
        'UPDATE room_types
         SET inventory = (
             SELECT COUNT(*)
             FROM hotel_rooms
             WHERE room_type_id = ? AND active = 1
         )
         WHERE id = ?',
        [$room['room_type_id'], $room['room_type_id']]
    );

    audit(
        'room_state',
        'Phòng ' . $room['number']
        . ': ' . $state
        . ', active=' . $active
    );
}

// Tạo mã chống gửi trùng cho đơn tại quầy.
function booking_request_key(): string
{
    $key = bin2hex(random_bytes(16));
    $_SESSION['booking_keys'][$key] = time();

    if (count($_SESSION['booking_keys']) > 40) {
        array_shift($_SESSION['booking_keys']);
    }

    return $key;
}

// Tạo đơn đặt phòng tại quầy.
function create_staff_booking(): int
{
    $in = input('check_in');
    $out = input('check_out');
    $nights = valid_dates($in, $out);

    $quantity = integer('quantity', 1, 5);
    $guests = integer('guests', 1, 20, 2);

    $name = input('guest_name');
    $email = input('email');
    $phone = input('phone');
    $note = input('note');
    $key = input('request_key');

    $phoneDigits = preg_replace('/\D/', '', $phone);

    if (
        mb_strlen($name) < 2
        || mb_strlen($name) > 100
        || !filter_var($email, FILTER_VALIDATE_EMAIL)
        || strlen($email) > 190
        || !preg_match('/^\+?[0-9][0-9 ()-]{7,23}$/', $phone)
        || strlen($phoneDigits) < 9
        || strlen($phoneDigits) > 15
        || mb_strlen($note) > 1000
    ) {
        throw new DomainException(
            'Kiểm tra họ tên (2–100 ký tự), email, '
            . 'điện thoại (9–15 chữ số), '
            . 'ghi chú (tối đa 1.000 ký tự).'
        );
    }

    if (
        !preg_match('/^[a-f0-9]{32}$/', $key)
        || empty($_SESSION['booking_keys'][$key])
    ) {
        throw new DomainException('Phiên tạo đơn hết hạn.');
    }

    $roomType = query(
        'SELECT * FROM room_types WHERE id = ? FOR UPDATE',
        [input('room_type_id')]
    )->fetch();

    if (!$roomType || !(int) $roomType['active']) {
        throw new DomainException(
            'Loại phòng đang ngừng nhận đặt.'
        );
    }

    $oldId = query(
        'SELECT id FROM bookings WHERE request_key = ?',
        [$key]
    )->fetchColumn();

    if ($oldId !== false) {
        return (int) $oldId;
    }

    if ($guests > $quantity * $roomType['capacity']) {
        throw new DomainException('Tổng khách vượt sức chứa.');
    }

    if (available($roomType, $in, $out) < $quantity) {
        throw new DomainException(
            'Không còn đủ phòng trong thời gian này.'
        );
    }

    if ((int) input('quoted_price') !== (int) $roomType['price']) {
        throw new DomainException(
            'Giá đã thay đổi; tải lại trang để xem giá mới.'
        );
    }

    query(
        "INSERT INTO bookings (
            code,
            request_key,
            room_type_id,
            guest_name,
            email,
            phone,
            check_in,
            check_out,
            quantity,
            guests,
            nightly_price,
            total_price,
            note,
            status,
            source,
            created_by
         ) VALUES (
            ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,
            'confirmed', 'reception', ?
         )",
        [
            bin2hex(random_bytes(16)),
            $key,
            $roomType['id'],
            $name,
            $email,
            $phone,
            $in,
            $out,
            $quantity,
            $guests,
            $roomType['price'],
            $roomType['price'] * $quantity * $nights,
            $note,
            $_SESSION['staff_id']
        ]
    );

    $id = (int) db()->lastInsertId();

    audit(
        'booking_create',
        'Tạo đơn tại quầy #' . $id
    );

    return $id;
}