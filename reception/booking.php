<?php
require __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/operations.php';

$user = staff_required();

$error = '';
$id = (int) input('id');

if (post()) {
    check_csrf();

    try {
        db()->beginTransaction();

        $action = input('action');

        if ($action === 'status') {
            change_booking(
                $id,
                input('status'),
                selected_rooms()
            );
        } elseif ($action === 'reconcile') {
            reconcile_legacy($id, selected_rooms());
        } elseif ($action === 'payment') {
            record_payment(
                $id,
                input('kind'),
                input('amount'),
                input('method'),
                input('reference'),
                input('request_key')
            );
        } else {
            throw new DomainException('Thao tác không hợp lệ.');
        }

        db()->commit();

        flash('Đã ghi nhận thao tác.');
        go('reception/booking.php?id=' . $id);

    } catch (DomainException $ex) {
        if (db()->inTransaction()) {
            db()->rollBack();
        }

        $error = $ex->getMessage();

    } catch (Throwable $ex) {
        if (db()->inTransaction()) {
            db()->rollBack();
        }

        error_log((string) $ex);

        $error = 'Chưa thể ghi nhận thao tác. Vui lòng thử lại.';
    }
}

$b = query(
    'SELECT b.*, r.name AS room_name
     FROM bookings b
     INNER JOIN room_types r ON r.id = b.room_type_id
     WHERE b.id = ?',
    [$id]
)->fetch();

if (!$b) {
    http_response_code(404);
    exit('Không tìm thấy đơn.');
}

$title = 'Đơn đặt phòng #' . $id;

// Tổng thanh toán bao gồm tiền phòng và dịch vụ chưa hủy.
$paid = paid_amount($id);
$serviceTotal = booking_service_total($id);
$grandTotal = (int) $b['total_price'] + $serviceTotal;

$remaining = $b['status'] === 'cancelled'
    ? 0
    : max(0, $grandTotal - $paid);

$assigned = query(
    'SELECT h.*
     FROM room_assignments a
     INNER JOIN hotel_rooms h ON h.id = a.room_id
     WHERE a.booking_id = ?
     ORDER BY h.number',
    [$id]
)->fetchAll();

$choices = query(
    "SELECT h.*
     FROM hotel_rooms h
     WHERE h.room_type_id = ?
       AND h.active = 1
       AND h.housekeeping = 'ready'
       AND NOT EXISTS (
           SELECT 1
           FROM room_assignments a
           INNER JOIN bookings x ON x.id = a.booking_id
           WHERE a.room_id = h.id
             AND x.status = 'checked_in'
       )
     ORDER BY h.number",
    [$b['room_type_id']]
)->fetchAll();

$payments = query(
    'SELECT p.*, a.display_name
     FROM payments p
     INNER JOIN admins a ON a.id = p.staff_id
     WHERE p.booking_id = ?
     ORDER BY p.id DESC',
    [$id]
)->fetchAll();

$key = bin2hex(random_bytes(16));
$_SESSION['payment_keys'][$key] = time();

if (count($_SESSION['payment_keys']) > 40) {
    array_shift($_SESSION['payment_keys']);
}

require __DIR__ . '/../includes/staff_header.php';
?>

<div class="actions align-left">
    <a
        class="button outline"
        href="<?= e(url('reception/index.php')) ?>"
    >
        ← DANH SÁCH ĐƠN
    </a>

    <a
        class="button outline"
        href="<?= e(url('reception/services.php?id=' . $id)) ?>"
    >
        DỊCH VỤ PHÁT SINH
    </a>

    <a
        class="button"
        href="<?= e(url('staff/invoice.php?id=' . $id)) ?>"
    >
        HÓA ĐƠN NỘI BỘ / IN ↗
    </a>
</div>

<div class="detail-grid">
    <div class="panel">
        <span class="badge">
            <?= e(status_name($b['status'])) ?>
        </span>

        <h2><?= e($b['guest_name']) ?></h2>

        <p>
            <?= e($b['email']) ?> · <?= e($b['phone']) ?>
        </p>

        <p class="code" style="overflow-wrap: anywhere;">
            Mã tra cứu: <?= e($b['code']) ?>
        </p>

        <dl>
            <dt>Loại phòng</dt>
            <dd><?= e($b['room_name']) ?></dd>

            <dt>Lưu trú</dt>
            <dd>
                <?= e($b['check_in']) ?>
                →
                <?= e($b['check_out']) ?>
            </dd>

            <dt>Số lượng</dt>
            <dd>
                <?= (int) $b['quantity'] ?> phòng ·
                <?= (int) $b['guests'] ?> khách
            </dd>

            <dt>Nguồn đơn</dt>
            <dd>
                <?= $b['source'] === 'online'
                    ? 'Trực tuyến'
                    : 'Tại quầy' ?>
            </dd>

            <dt>Phòng thực tế</dt>
            <dd>
                <?= e(
                    implode(', ', array_column($assigned, 'number'))
                    ?: 'Chưa phân phòng'
                ) ?>
            </dd>

            <dt>Nhận phòng thực tế</dt>
            <dd><?= e($b['actual_check_in'] ?: '—') ?></dd>

            <dt>Trả phòng thực tế</dt>
            <dd><?= e($b['actual_check_out'] ?: '—') ?></dd>
        </dl>

        <p style="overflow-wrap: anywhere;">
            Ghi chú: <?= e($b['note'] ?: 'Không có') ?>
        </p>
    </div>

    <div class="panel">
        <h2>Thanh toán</h2>

        <dl>
            <dt>Tổng tiền phòng</dt>
            <dd><?= money($b['total_price']) ?></dd>

            <dt>Tiền dịch vụ</dt>
            <dd><?= money($serviceTotal) ?></dd>

            <dt>Tổng thanh toán</dt>
            <dd>
                <strong><?= money($grandTotal) ?></strong>
            </dd>

            <dt>Đã thu ròng</dt>
            <dd><?= money($paid) ?></dd>

            <dt>Còn phải thu</dt>
            <dd>
                <strong><?= money($remaining) ?></strong>
            </dd>
        </dl>

        <?php if ($b['status'] === 'cancelled'): ?>
            <p>
                Đơn đã hủy. Tổng tiền hiển thị là giá trị của đơn,
                không phải yêu cầu thanh toán mới.
            </p>
        <?php endif; ?>

        <p>
            Đã thu ròng = tiền thu − tiền hoàn.
            Chỉ ghi nhận tiền đã nhận hoặc hoàn thực tế;
            đây không phải cổng thanh toán.
        </p>

        <a
            class="text-link"
            href="<?= e(url('reception/services.php?id=' . $id)) ?>"
        >
            Xem chi tiết dịch vụ →
        </a>
    </div>
</div>

<?php if ($b['status'] === 'pending'): ?>
    <form method="post" class="panel">
        <?= csrf() ?>

        <input type="hidden" name="id" value="<?= $id ?>">
        <input type="hidden" name="action" value="status">
        <input type="hidden" name="status" value="confirmed">

        <button type="submit" class="button">
            XÁC NHẬN ĐẶT PHÒNG
        </button>
    </form>
<?php endif; ?>

<?php if (
    $b['status'] === 'confirmed'
    || ($b['status'] === 'checked_in' && !$assigned)
): ?>
    <form
        method="post"
        class="panel"
        data-confirm="Xác nhận phân các phòng đã chọn cho đơn này?"
    >
        <?= csrf() ?>

        <input type="hidden" name="id" value="<?= $id ?>">

        <input
            type="hidden"
            name="action"
            value="<?= $b['status'] === 'confirmed'
                ? 'status'
                : 'reconcile' ?>"
        >

        <input type="hidden" name="status" value="checked_in">

        <h2>
            <?= $b['status'] === 'confirmed'
                ? 'Phân phòng & nhận phòng'
                : 'Bổ sung phòng cho đơn từ bản cũ' ?>
        </h2>

        <p>
            Chọn đúng <?= (int) $b['quantity'] ?> phòng cùng loại.
            Phòng phải đã dọn và không có khách đang ở.
            Phân phòng khi nhận khách, chưa hỗ trợ giữ trước
            một số phòng cụ thể.
        </p>

        <div class="room-choice">
            <?php foreach ($choices as $room): ?>
                <label class="checkbox">
                    <input
                        type="checkbox"
                        name="room_ids[]"
                        value="<?= (int) $room['id'] ?>"
                    >
                    Phòng <?= e($room['number']) ?>
                </label>
            <?php endforeach; ?>
        </div>

        <?php if (!$choices): ?>
            <div class="notice">
                Chưa có phòng đã dọn phù hợp. Kiểm tra sơ đồ phòng.
            </div>
        <?php else: ?>
            <button type="submit" class="button">
                <?= $b['status'] === 'confirmed'
                    ? 'PHÂN PHÒNG & NHẬN KHÁCH'
                    : 'LƯU PHÒNG THỰC TẾ' ?>
            </button>
        <?php endif; ?>
    </form>
<?php endif; ?>

<?php if (in_array(
    $b['status'],
    ['pending', 'confirmed', 'checked_in'],
    true
)): ?>
    <form
        method="post"
        class="panel"
        data-confirm="Bạn đã thực hiện thu/hoàn tiền thực tế và muốn ghi vào sổ?"
    >
        <?= csrf() ?>

        <input type="hidden" name="id" value="<?= $id ?>">
        <input type="hidden" name="action" value="payment">

        <input
            type="hidden"
            name="request_key"
            value="<?= e($key) ?>"
        >

        <h2>Ghi nhận thu / hoàn tiền</h2>

        <p>
            Tổng tiền phòng và dịch vụ:
            <strong><?= money($grandTotal) ?></strong>.
            Còn phải thu:
            <strong><?= money($remaining) ?></strong>.
        </p>

        <div class="form-grid">
            <label>
                Loại giao dịch
                <select name="kind">
                    <option value="payment">Thu tiền</option>
                    <option value="refund">Hoàn tiền</option>
                </select>
            </label>

            <label>
                Số tiền (VND)
                <input
                    type="number"
                    name="amount"
                    min="1"
                    max="<?= (int) max($grandTotal, $paid) ?>"
                    step="1"
                    required
                >
            </label>

            <label>
                Phương thức
                <select name="method">
                    <option value="cash">Tiền mặt</option>
                    <option value="bank">Chuyển khoản</option>
                </select>
            </label>

            <label>
                Mã tham chiếu / ghi chú
                <input
                    name="reference"
                    maxlength="190"
                    placeholder="Mã chuyển khoản hoặc nội dung thu"
                >
            </label>
        </div>

        <p>
            Không được thu vượt số còn phải thanh toán
            hoặc hoàn vượt số tiền đã thu ròng.
        </p>

        <button type="submit" class="button">
            GHI NHẬN GIAO DỊCH
        </button>
    </form>
<?php endif; ?>

<?php if ($b['status'] === 'checked_in'): ?>
    <form
        method="post"
        class="panel"
        data-confirm="Xác nhận khách trả phòng? Các phòng sẽ chuyển sang chờ dọn."
    >
        <?= csrf() ?>

        <input type="hidden" name="id" value="<?= $id ?>">
        <input type="hidden" name="action" value="status">
        <input type="hidden" name="status" value="completed">

        <h2>Trả phòng</h2>

        <p>
            Chỉ thực hiện từ ngày trả đã đặt, sau khi đã thu đủ
            tiền phòng và dịch vụ. Phòng tự chuyển sang chờ dọn.
        </p>

        <?php if ($remaining > 0): ?>
            <div class="notice">
                Còn phải thu <?= money($remaining) ?>.
                Vui lòng ghi nhận thanh toán trước khi trả phòng.
            </div>
        <?php endif; ?>

        <button type="submit" class="button">
            HOÀN TẤT TRẢ PHÒNG
        </button>
    </form>
<?php endif; ?>

<?php if (in_array(
    $b['status'],
    ['pending', 'confirmed'],
    true
)): ?>
    <form
        method="post"
        class="panel"
        data-confirm="Hủy đơn này? Phải hoàn đủ tiền đã thu trước khi hủy."
    >
        <?= csrf() ?>

        <input type="hidden" name="id" value="<?= $id ?>">
        <input type="hidden" name="action" value="status">
        <input type="hidden" name="status" value="cancelled">

        <button type="submit" class="button outline">
            HỦY ĐƠN ĐẶT PHÒNG
        </button>
    </form>
<?php endif; ?>

<h2>Lịch sử thanh toán</h2>

<div class="table-wrap">
    <table>
        <thead>
            <tr>
                <th>Thời gian</th>
                <th>Giao dịch</th>
                <th>Số tiền</th>
                <th>Phương thức</th>
                <th>Nhân viên / tham chiếu</th>
            </tr>
        </thead>

        <tbody>
            <?php foreach ($payments as $payment): ?>
                <tr>
                    <td><?= e($payment['created_at']) ?></td>

                    <td>
                        <?= $payment['kind'] === 'payment'
                            ? 'Thu'
                            : 'Hoàn' ?>
                    </td>

                    <td><?= money($payment['amount']) ?></td>

                    <td>
                        <?= $payment['method'] === 'cash'
                            ? 'Tiền mặt'
                            : 'Chuyển khoản' ?>
                    </td>

                    <td>
                        <?= e($payment['display_name']) ?>
                        <br>
                        <?= e($payment['reference']) ?>
                    </td>
                </tr>
            <?php endforeach; ?>

            <?php if (!$payments): ?>
                <tr>
                    <td colspan="5">Chưa ghi nhận giao dịch.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require __DIR__ . '/../includes/staff_footer.php'; ?>