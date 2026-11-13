<?php
require __DIR__ . '/../includes/bootstrap.php';

$user = staff_required();
$title = 'Lịch phòng';
$error = '';

$today = new DateTimeImmutable('today');
$todayText = $today->format('Y-m-d');
$startInput = input('start', $todayText);

$start = DateTimeImmutable::createFromFormat('!Y-m-d', $startInput);

if (!$start || $start->format('Y-m-d') !== $startInput) {
    $start = $today;
    $error = 'Ngày bắt đầu không hợp lệ. Lịch đang hiển thị từ hôm nay.';
} elseif ($start < $today) {
    $start = $today;
    $error = 'Lịch điều hành hiển thị từ hôm nay trở đi.';
}

$startText = $start->format('Y-m-d');
$end = $start->modify('+7 days');
$endText = $end->format('Y-m-d');
$lastDay = $end->modify('-1 day');

$days = [];
$weekdays = [
    1 => 'Thứ 2',
    2 => 'Thứ 3',
    3 => 'Thứ 4',
    4 => 'Thứ 5',
    5 => 'Thứ 6',
    6 => 'Thứ 7',
    7 => 'Chủ nhật'
];

for ($i = 0; $i < 7; $i++) {
    $date = $start->modify('+' . $i . ' days');

    $days[] = [
        'key' => $date->format('Y-m-d'),
        'date' => $date->format('d/m'),
        'weekday' => $weekdays[(int) $date->format('N')],
        'is_today' => $date->format('Y-m-d') === $todayText
    ];
}

// Bao gồm cả loại phòng đã ngừng bán để lễ tân vẫn thấy khách đang ở.
$types = query(
    'SELECT id, name, active
     FROM room_types
     ORDER BY name, id'
)->fetchAll();

$typesById = [];

foreach ($types as $type) {
    $typesById[(int) $type['id']] = $type;
}

$typeInput = input('type_id', '0');
$selectedType = ctype_digit($typeInput) ? (int) $typeInput : 0;

if ($selectedType !== 0 && !isset($typesById[$selectedType])) {
    $selectedType = 0;
}

$visibleTypes = $selectedType > 0
    ? [$typesById[$selectedType]]
    : $types;

$typeWhere = $selectedType > 0 ? 'WHERE h.room_type_id = ?' : '';
$typeParams = $selectedType > 0 ? [$selectedType] : [];

$rooms = query(
    "SELECT h.id, h.number, h.room_type_id, h.housekeeping, h.active,
            r.name AS type_name
     FROM hotel_rooms h
     JOIN room_types r ON r.id = h.room_type_id
     $typeWhere
     ORDER BY r.name, h.number, h.id",
    $typeParams
)->fetchAll();

// Chỉ lấy đơn có ảnh hưởng đến khoảng đang xem.
// Đơn đã đến hạn trả nhưng vẫn checked_in tiếp tục giữ chỗ
// theo cùng quy tắc với phần kiểm tra phòng trống của website.
$bookingSql = "
    SELECT b.id, b.room_type_id, b.guest_name, b.status,
           b.check_in, b.check_out, b.quantity, b.guests,
           r.name AS room_name
    FROM bookings b
    JOIN room_types r ON r.id = b.room_type_id
    WHERE b.status IN ('pending', 'confirmed', 'checked_in')
      AND b.check_in < ?
      AND (
          b.check_out > ?
          OR (b.status = 'checked_in' AND b.check_out <= ?)
      )
";

$bookingParams = [$endText, $startText, $todayText];

if ($selectedType > 0) {
    $bookingSql .= ' AND b.room_type_id = ?';
    $bookingParams[] = $selectedType;
}

$bookingSql .= ' ORDER BY b.check_in, b.id';

$bookings = query($bookingSql, $bookingParams)->fetchAll();

// Số phòng có thể bán về mặt vật lý của từng loại.
$stock = [];

foreach ($rooms as $room) {
    $typeId = (int) $room['room_type_id'];

    if ((int) $room['active'] === 1 && $room['housekeeping'] !== 'maintenance') {
        $stock[$typeId] = ($stock[$typeId] ?? 0) + 1;
    }
}

// Tổng số phòng khách đã giữ cho từng đêm.
// Dùng quantity của đơn, kể cả khi chưa phân số phòng.
$used = [];
$arrivals = [];
$checkedIn = [];

foreach ($bookings as $booking) {
    $typeId = (int) $booking['room_type_id'];

    $stillInRoom = $booking['status'] === 'checked_in';
    $awaitingCheckout = $stillInRoom && $booking['check_out'] <= $todayText;

    if ($stillInRoom) {
        $checkedIn[(int) $booking['id']] = $booking;
    }

    if (
        in_array($booking['status'], ['pending', 'confirmed'], true)
        && $booking['check_in'] >= $startText
        && $booking['check_in'] < $endText
    ) {
        $arrivals[] = $booking;
    }

    foreach ($days as $day) {
        $key = $day['key'];

        // Ngày trả dự kiến không chiếm thêm một đêm,
        // trừ khi đơn đã đến hạn mà vẫn chưa làm thủ tục trả.
        if (
            $booking['check_in'] <= $key
            && ($key < $booking['check_out'] || $awaitingCheckout)
        ) {
            $used[$typeId][$key] =
                ($used[$typeId][$key] ?? 0) + (int) $booking['quantity'];
        }
    }
}

// Chỉ gắn tên khách vào một số phòng khi đã có phân phòng thực tế.
$assignmentSql = "
    SELECT a.room_id, a.booking_id
    FROM room_assignments a
    JOIN bookings b ON b.id = a.booking_id
    WHERE b.status = 'checked_in'
";

$assignmentParams = [];

if ($selectedType > 0) {
    $assignmentSql .= ' AND b.room_type_id = ?';
    $assignmentParams[] = $selectedType;
}

$assignments = query($assignmentSql, $assignmentParams)->fetchAll();

$staysByRoom = [];
$assignedCount = [];

foreach ($assignments as $assignment) {
    $bookingId = (int) $assignment['booking_id'];

    if (!isset($checkedIn[$bookingId])) {
        continue;
    }

    $roomId = (int) $assignment['room_id'];
    $staysByRoom[$roomId][] = $checkedIn[$bookingId];
    $assignedCount[$bookingId] = ($assignedCount[$bookingId] ?? 0) + 1;
}

$incompleteAssignments = [];

foreach ($checkedIn as $bookingId => $booking) {
    if (($assignedCount[$bookingId] ?? 0) !== (int) $booking['quantity']) {
        $incompleteAssignments[] = $booking;
    }
}

$calendarUrl = static function (
    DateTimeImmutable $date,
    int $typeId
): string {
    return url('reception/calendar.php?' . http_build_query([
        'start' => $date->format('Y-m-d'),
        'type_id' => $typeId
    ]));
};

$previousStart = $start->modify('-7 days');

if ($previousStart < $today) {
    $previousStart = $today;
}

require __DIR__ . '/../includes/staff_header.php';
?>

<style>
.room-calendar .calendar-toolbar {
    display: flex;
    flex-wrap: wrap;
    align-items: end;
    gap: 14px;
}

.room-calendar .calendar-toolbar label {
    flex: 1 1 180px;
    min-width: 0;
    margin: 0;
}

.room-calendar .calendar-toolbar .button {
    width: auto;
}

.room-calendar .calendar-navigation {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 16px;
    margin: 20px 0;
}

.room-calendar .calendar-block {
    margin-top: 30px;
}

.room-calendar .calendar-scroll {
    overflow-x: auto;
    border: 1px solid var(--line, #ddd7cb);
    background: #fffdf8;
}

.room-calendar .calendar-grid {
    width: 100%;
    min-width: 920px;
    border-collapse: separate;
    border-spacing: 0;
}

.room-calendar .calendar-grid th,
.room-calendar .calendar-grid td {
    padding: 10px;
    border-right: 1px solid #e4dfd5;
    border-bottom: 1px solid #e4dfd5;
    vertical-align: middle;
    text-align: center;
}

.room-calendar .calendar-grid thead th {
    background: #f1ece2;
    color: #252823;
}

.room-calendar .calendar-grid .first-col {
    position: sticky;
    left: 0;
    z-index: 1;
    min-width: 145px;
    text-align: left;
    background: #faf7f0;
}

.room-calendar .calendar-grid thead .first-col {
    z-index: 2;
    background: #eee7d9;
}

.room-calendar .calendar-grid .today-heading {
    box-shadow: inset 0 -3px 0 #a78951;
}

.room-calendar .calendar-date,
.room-calendar .room-type {
    display: block;
    margin-top: 3px;
    font-size: 12px;
    font-weight: 400;
}

.room-calendar .calendar-cell {
    display: block;
    padding: 8px 6px;
    border-radius: 3px;
    font-size: 13px;
    line-height: 1.4;
    overflow-wrap: anywhere;
}

.room-calendar .calendar-cell small {
    display: block;
    margin-top: 3px;
}

.room-calendar a.calendar-cell:hover {
    text-decoration: underline;
}

.room-calendar .state-free {
    background: #e8f0e7;
    color: #2c5738;
}

.room-calendar .state-staying {
    background: #dce7ed;
    color: #27485b;
}

.room-calendar .state-planned {
    background: #e9edf3;
    color: #415268;
}

.room-calendar .state-attention {
    background: #f6ead7;
    color: #715020;
}

.room-calendar .state-closed {
    background: #ece8e5;
    color: #605954;
}

.room-calendar .state-full {
    background: #f4e3dc;
    color: #794534;
}

.room-calendar .calendar-help {
    color: #64675f;
    font-size: 14px;
    line-height: 1.7;
}

.room-calendar .arrivals-table {
    width: 100%;
    min-width: 650px;
}

.room-calendar .arrivals-table th,
.room-calendar .arrivals-table td {
    text-align: left;
    padding: 12px;
}
</style>

<div class="room-calendar">
    <form method="get"
          action="<?= e(url('reception/calendar.php')) ?>"
          class="panel calendar-toolbar">

        <label for="calendar-start">
            Xem từ ngày
            <input
                type="date"
                id="calendar-start"
                name="start"
                value="<?= e($startText) ?>"
                min="<?= e($todayText) ?>"
                required
            >
        </label>

        <label for="calendar-type">
            Loại phòng
            <select id="calendar-type" name="type_id">
                <option value="0">Tất cả loại phòng</option>

                <?php foreach ($types as $type): ?>
                    <option
                        value="<?= (int) $type['id'] ?>"
                        <?= $selectedType === (int) $type['id'] ? 'selected' : '' ?>
                    >
                        <?= e($type['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>

        <button type="submit" class="button">XEM LỊCH</button>
    </form>

    <nav class="calendar-navigation" aria-label="Chuyển tuần">
        <?php if ($start > $today): ?>
            <a class="text-link"
               href="<?= e($calendarUrl($previousStart, $selectedType)) ?>">
                ← 7 ngày trước
            </a>
        <?php endif; ?>

        <a class="text-link"
           href="<?= e($calendarUrl($today, $selectedType)) ?>">
            Hôm nay
        </a>

        <strong>
            <?= e($start->format('d/m/Y')) ?>
            – <?= e($lastDay->format('d/m/Y')) ?>
        </strong>

        <a class="text-link"
           href="<?= e($calendarUrl($start->modify('+7 days'), $selectedType)) ?>">
            7 ngày sau →
        </a>
    </nav>

    <section class="calendar-block">
        <h2>Phòng còn nhận đặt</h2>
        <p class="calendar-help">
            Mỗi ô là số phòng còn nhận đặt trong đêm đó,
            sau khi trừ các đơn chờ xác nhận, đã xác nhận và đang lưu trú.
        </p>

        <div class="calendar-scroll">
            <table class="calendar-grid" aria-label="Phòng còn nhận đặt theo loại và đêm">
                <thead>
                    <tr>
                        <th scope="col" class="first-col">Loại phòng</th>

                        <?php foreach ($days as $day): ?>
                            <th scope="col"
                                class="<?= $day['is_today'] ? 'today-heading' : '' ?>">
                                <?= e($day['weekday']) ?>
                                <span class="calendar-date"><?= e($day['date']) ?></span>
                            </th>
                        <?php endforeach; ?>
                    </tr>
                </thead>

                <tbody>
                    <?php foreach ($visibleTypes as $type): ?>
                        <?php $typeId = (int) $type['id']; ?>
                        <tr>
                            <th scope="row" class="first-col">
                                <?= e($type['name']) ?>
                            </th>

                            <?php foreach ($days as $day): ?>
                                <?php
                                $free = max(
                                    0,
                                    ($stock[$typeId] ?? 0) - ($used[$typeId][$day['key']] ?? 0)
                                );

                                if (!(int) $type['active']) {
                                    $class = 'state-closed';
                                    $text = 'Ngừng bán';
                                } elseif ($free === 0) {
                                    $class = 'state-full';
                                    $text = 'Hết phòng';
                                } else {
                                    $class = 'state-free';
                                    $text = 'Còn ' . $free;
                                }
                                ?>
                                <td>
                                    <span class="calendar-cell <?= $class ?>">
                                        <?= e($text) ?>
                                    </span>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>

                    <?php if (!$visibleTypes): ?>
                        <tr><td colspan="8">Chưa có loại phòng.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="calendar-block">
        <h2>Lịch theo số phòng</h2>
        <p class="calendar-help">
            Ô có tên khách là phòng đã được phân. Các đơn sắp nhận
            chưa phân số phòng được liệt kê ở cuối trang.
            Bấm vào tên khách để mở đơn.
        </p>

        <div class="calendar-scroll">
            <table class="calendar-grid" aria-label="Lịch theo số phòng thực tế">
                <thead>
                    <tr>
                        <th scope="col" class="first-col">Phòng</th>

                        <?php foreach ($days as $day): ?>
                            <th scope="col"
                                class="<?= $day['is_today'] ? 'today-heading' : '' ?>">
                                <?= e($day['weekday']) ?>
                                <span class="calendar-date"><?= e($day['date']) ?></span>
                            </th>
                        <?php endforeach; ?>
                    </tr>
                </thead>

                <tbody>
                    <?php foreach ($rooms as $room): ?>
                        <?php $roomId = (int) $room['id']; ?>

                        <tr>
                            <th scope="row" class="first-col">
                                Phòng <?= e($room['number']) ?>
                                <span class="room-type"><?= e($room['type_name']) ?></span>
                            </th>

                            <?php foreach ($days as $day): ?>
                                <?php
                                $key = $day['key'];
                                $stay = null;

                                foreach ($staysByRoom[$roomId] ?? [] as $candidate) {
                                    if (
                                        $candidate['check_in'] <= $key
                                        && (
                                            $key < $candidate['check_out']
                                            || $candidate['check_out'] <= $todayText
                                        )
                                    ) {
                                        $stay = $candidate;
                                        break;
                                    }
                                }
                                ?>

                                <td>
                                    <?php if ($stay): ?>
                                        <?php
                                        if ($stay['check_out'] <= $todayText) {
                                            $class = 'state-attention';
                                            $label = 'Chờ ghi trả phòng';
                                        } elseif ($day['is_today']) {
                                            $class = 'state-staying';
                                            $label = 'Đang ở';
                                        } else {
                                            $class = 'state-planned';
                                            $label = 'Dự kiến đang ở';
                                        }
                                        ?>

                                        <a
                                            class="calendar-cell <?= $class ?>"
                                            href="<?= e(url(
                                                'reception/booking.php?id=' . (int) $stay['id']
                                            )) ?>"
                                        >
                                            <strong><?= e($label) ?></strong>
                                            <small><?= e($stay['guest_name']) ?></small>
                                        </a>

                                    <?php elseif (!(int) $room['active']): ?>
                                        <span class="calendar-cell state-closed">
                                            Ngừng sử dụng
                                        </span>

                                    <?php elseif ($room['housekeeping'] === 'maintenance'): ?>
                                        <span class="calendar-cell state-closed">
                                            Bảo trì
                                        </span>

                                    <?php elseif ($day['is_today'] && $room['housekeeping'] === 'dirty'): ?>
                                        <span class="calendar-cell state-attention">
                                            Chờ dọn
                                        </span>

                                    <?php elseif ($day['is_today']): ?>
                                        <span class="calendar-cell state-free">
                                            Trống · đã dọn
                                        </span>

                                    <?php else: ?>
                                        <span class="calendar-cell state-free">
                                            Chưa phân khách
                                        </span>
                                    <?php endif; ?>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>

                    <?php if (!$rooms): ?>
                        <tr>
                            <td colspan="8">
                                Chưa có phòng thực tế cho lựa chọn này.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($incompleteAssignments): ?>
            <div class="notice" style="margin-top:14px;">
                Một số đơn đang ở chưa được phân đủ số phòng.
                Các đơn này đã được trừ trong bảng phòng còn nhận đặt:
                <?php foreach ($incompleteAssignments as $booking): ?>
                    <a href="<?= e(url(
                        'reception/booking.php?id=' . (int) $booking['id']
                    )) ?>">
                        Đơn #<?= (int) $booking['id'] ?>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <section class="calendar-block">
        <h2>Khách sắp nhận phòng</h2>

        <?php if (!$arrivals): ?>
            <div class="notice">
                Không có đơn chờ xác nhận hoặc đã xác nhận
                đến nhận phòng trong 7 ngày đang xem.
            </div>
        <?php else: ?>
            <div class="calendar-scroll">
                <table class="arrivals-table">
                    <thead>
                        <tr>
                            <th scope="col">Nhận phòng</th>
                            <th scope="col">Khách hàng</th>
                            <th scope="col">Loại phòng</th>
                            <th scope="col">Số lượng</th>
                            <th scope="col">Trạng thái</th>
                            <th scope="col">Đơn</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php foreach ($arrivals as $booking): ?>
                            <tr>
                                <td>
                                    <?= e(date('d/m/Y', strtotime($booking['check_in']))) ?>
                                </td>
                                <td><?= e($booking['guest_name']) ?></td>
                                <td><?= e($booking['room_name']) ?></td>
                                <td>
                                    <?= (int) $booking['quantity'] ?> phòng
                                    · <?= (int) $booking['guests'] ?> khách
                                </td>
                                <td><?= e(status_name($booking['status'])) ?></td>
                                <td>
                                    <a class="text-link"
                                       href="<?= e(url(
                                           'reception/booking.php?id=' . (int) $booking['id']
                                       )) ?>">
                                        #<?= (int) $booking['id'] ?> →
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</div>

<?php require __DIR__ . '/../includes/staff_footer.php'; ?>