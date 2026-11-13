<?php
require __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/operations.php';

$user = staff_required();
$title = 'Dịch vụ phát sinh';
$error = '';

$id = (int) input('id');

if (post()) {
    check_csrf();

    try {
        db()->beginTransaction();

        // Khóa theo cùng thứ tự với thu tiền và trả phòng.
        $booking = locked_booking($id);
        $action = input('action');

        if ($action === 'add') {
            $key = input('request_key');

            if (
                !preg_match('/^[a-f0-9]{32}$/', $key)
                || (int) ($_SESSION['service_keys'][$key] ?? 0) !== $id
            ) {
                throw new DomainException(
                    'Phiên ghi nhận dịch vụ hết hạn. Vui lòng tải lại trang.'
                );
            }

            $old = query(
                'SELECT booking_id
                 FROM booking_services
                 WHERE request_key = ?',
                [$key]
            )->fetch();

            if ($old) {
                if ((int) $old['booking_id'] !== $id) {
                    throw new DomainException('Yêu cầu không hợp lệ.');
                }

                db()->commit();
                flash('Dịch vụ này đã được ghi nhận trước đó.');
                go('reception/services.php?id=' . $id);
            }

            if ($booking['status'] !== 'checked_in') {
                throw new DomainException(
                    'Chỉ ghi nhận dịch vụ cho khách đang lưu trú.'
                );
            }

            $quantity = integer('quantity', 1, 100);
            $note = input('note');

            if (mb_strlen($note) > 500) {
                throw new DomainException('Ghi chú tối đa 500 ký tự.');
            }

            $service = query(
                'SELECT * FROM services WHERE id = ? FOR UPDATE',
                [input('service_id')]
            )->fetch();

            if (!$service || (int) $service['active'] !== 1) {
                throw new DomainException(
                    'Dịch vụ không tồn tại hoặc đã ngừng cung cấp.'
                );
            }

            if (
                !ctype_digit(input('quoted_price'))
                || (int) input('quoted_price') !== (int) $service['price']
            ) {
                throw new DomainException(
                    'Giá dịch vụ vừa thay đổi. '
                    . 'Vui lòng kiểm tra giá mới và gửi lại.'
                );
            }

            query(
                'INSERT INTO booking_services (
                    booking_id, service_id, service_name,
                    unit, unit_price, quantity, note,
                    request_key, created_by
                 ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    $id,
                    $service['id'],
                    $service['name'],
                    $service['unit'],
                    $service['price'],
                    $quantity,
                    $note,
                    $key,
                    $user['id']
                ]
            );

            $lineId = (int) db()->lastInsertId();

            audit(
                'service.add',
                'Đơn #' . $id . ': thêm dòng dịch vụ #' . $lineId
            );

        } elseif ($action === 'void') {
            $line = query(
                'SELECT *
                 FROM booking_services
                 WHERE id = ? AND booking_id = ?
                 FOR UPDATE',
                [input('line_id'), $id]
            )->fetch();

            if (!$line) {
                throw new DomainException('Không tìm thấy dòng dịch vụ.');
            }

            if ($line['voided_at'] !== null) {
                db()->commit();
                flash('Dòng dịch vụ này đã được hủy trước đó.');
                go('reception/services.php?id=' . $id);
            }

            if ($booking['status'] !== 'checked_in') {
                throw new DomainException(
                    'Chỉ được hủy dòng dịch vụ khi khách đang lưu trú.'
                );
            }

            $reason = input('void_reason');

            if (mb_strlen($reason) < 5 || mb_strlen($reason) > 500) {
                throw new DomainException(
                    'Lý do hủy cần từ 5 đến 500 ký tự.'
                );
            }

            $lineTotal = (int) $line['unit_price']
                * (int) $line['quantity'];

            $newTotal = booking_grand_total($booking) - $lineTotal;

            if (paid_amount($id) > $newTotal) {
                throw new DomainException(
                    'Tiền đã thu vượt tổng tiền sau khi hủy dịch vụ. '
                    . 'Hãy hoàn phần chênh lệch thực tế và ghi nhận '
                    . 'hoàn tiền trong đơn trước khi hủy dòng này.'
                );
            }

            query(
                'UPDATE booking_services
                 SET voided_at = NOW(),
                     voided_by = ?,
                     void_reason = ?
                 WHERE id = ? AND booking_id = ?',
                [$user['id'], $reason, $line['id'], $id]
            );

            audit(
                'service.void',
                'Đơn #' . $id . ': hủy dòng dịch vụ #'
                . $line['id'] . '. Lý do: ' . $reason
            );

        } else {
            throw new DomainException('Thao tác không hợp lệ.');
        }

        db()->commit();
        flash('Đã cập nhật dịch vụ của đơn.');
        go('reception/services.php?id=' . $id);

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
        $error = 'Chưa thể ghi nhận dịch vụ. Vui lòng thử lại.';
    }
}

$booking = query(
    'SELECT * FROM bookings WHERE id = ?',
    [$id]
)->fetch();

if (!$booking) {
    http_response_code(404);
    exit('Không tìm thấy đơn đặt phòng.');
}

$catalog = query(
    'SELECT * FROM services WHERE active = 1 ORDER BY name, id'
)->fetchAll();

$lines = query(
    'SELECT s.*, a.display_name AS staff_name
     FROM booking_services s
     INNER JOIN admins a ON a.id = s.created_by
     WHERE s.booking_id = ?
     ORDER BY s.created_at DESC, s.id DESC',
    [$id]
)->fetchAll();

$serviceTotal = booking_service_total($id);
$grandTotal = (int) $booking['total_price'] + $serviceTotal;
$paid = paid_amount($id);
$canEdit = $booking['status'] === 'checked_in';

$key = bin2hex(random_bytes(16));
$_SESSION['service_keys'][$key] = $id;

if (count($_SESSION['service_keys']) > 40) {
    array_shift($_SESSION['service_keys']);
}

require __DIR__ . '/../includes/staff_header.php';
?>

<p>
    <a
        class="text-link"
        href="<?= e(url('reception/booking.php?id=' . $id)) ?>"
    >
        ← Quay lại đơn #<?= $id ?>
    </a>
</p>

<div class="panel">
    <h2><?= e($booking['guest_name']) ?></h2>

    <p>Trạng thái: <?= e(status_name($booking['status'])) ?></p>

    <dl>
        <dt>Tiền phòng</dt>
        <dd><?= money($booking['total_price']) ?></dd>

        <dt>Tiền dịch vụ</dt>
        <dd><?= money($serviceTotal) ?></dd>

        <dt>Tổng thanh toán</dt>
        <dd><strong><?= money($grandTotal) ?></strong></dd>

        <dt>Đã thu ròng</dt>
        <dd><?= money($paid) ?></dd>

        <dt>Còn phải thu</dt>
        <dd>
            <?= money(
                $booking['status'] === 'cancelled'
                    ? 0
                    : max(0, $grandTotal - $paid)
            ) ?>
        </dd>
    </dl>
</div>

<?php if ($canEdit && $catalog): ?>
    <form
        method="post"
        action="<?= e(url('reception/services.php')) ?>"
        class="panel"
        id="add-service-form"
    >
        <?= csrf() ?>

        <input type="hidden" name="id" value="<?= $id ?>">
        <input type="hidden" name="action" value="add">
        <input type="hidden" name="request_key" value="<?= e($key) ?>">

        <input
            type="hidden"
            name="quoted_price"
            id="service-quoted-price"
            value="<?= (int) $catalog[0]['price'] ?>"
        >

        <h2>Ghi nhận dịch vụ khách sử dụng</h2>

        <div class="form-grid">
            <label>
                Dịch vụ
                <select name="service_id" id="service-select" required>
                    <?php foreach ($catalog as $service): ?>
                        <option
                            value="<?= (int) $service['id'] ?>"
                            data-price="<?= (int) $service['price'] ?>"
                        >
                            <?= e($service['name']) ?>
                            — <?= money($service['price']) ?>
                            / <?= e($service['unit']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label>
                Số lượng
                <input
                    type="number"
                    name="quantity"
                    id="service-quantity"
                    value="1"
                    min="1"
                    max="100"
                    step="1"
                    required
                >
            </label>
        </div>

        <label>
            Ghi chú
            <input
                name="note"
                maxlength="500"
                placeholder="Ví dụ: minibar phòng 1001"
            >
        </label>

        <p>
            Thành tiền:
            <strong id="service-preview">
                <?= money($catalog[0]['price']) ?>
            </strong>
        </p>

        <button type="submit" class="button">
            GHI NHẬN DỊCH VỤ
        </button>
    </form>
<?php elseif ($canEdit): ?>
    <div class="notice">
        Chưa có dịch vụ đang cung cấp.
        Quản trị viên cần tạo danh mục dịch vụ trước.
    </div>
<?php else: ?>
    <div class="notice">
        Chỉ được thêm hoặc hủy dòng dịch vụ khi khách đang lưu trú.
    </div>
<?php endif; ?>

<h2>Dịch vụ đã ghi nhận</h2>

<?php if (!$lines): ?>
    <div class="panel">Chưa ghi nhận dịch vụ phát sinh.</div>
<?php endif; ?>

<?php foreach ($lines as $line): ?>
    <article class="panel" style="margin-bottom: 20px;">
        <h3><?= e($line['service_name']) ?></h3>

        <p>
            <?= (int) $line['quantity'] ?>
            <?= e($line['unit']) ?>
            × <?= money($line['unit_price']) ?>
            =
            <strong>
                <?= money(
                    (int) $line['quantity'] * (int) $line['unit_price']
                ) ?>
            </strong>
        </p>

        <p>
            Ghi nhận: <?= e($line['created_at']) ?>
            · <?= e($line['staff_name']) ?>
        </p>

        <?php if ($line['note'] !== ''): ?>
            <p>Ghi chú: <?= e($line['note']) ?></p>
        <?php endif; ?>

        <?php if ($line['voided_at'] !== null): ?>
            <div class="notice">
                <strong>Đã hủy — không cộng vào thanh toán.</strong>
                <p>Lý do: <?= e($line['void_reason']) ?></p>
                <p>Thời gian: <?= e($line['voided_at']) ?></p>
            </div>
        <?php elseif ($canEdit): ?>
            <details>
                <summary>Hủy dòng ghi nhầm</summary>

                <form
                    method="post"
                    action="<?= e(url('reception/services.php')) ?>"
                    data-confirm="Xác nhận hủy dòng dịch vụ này?"
                >
                    <?= csrf() ?>

                    <input type="hidden" name="id" value="<?= $id ?>">
                    <input type="hidden" name="action" value="void">

                    <input
                        type="hidden"
                        name="line_id"
                        value="<?= (int) $line['id'] ?>"
                    >

                    <label>
                        Lý do hủy
                        <input
                            name="void_reason"
                            minlength="5"
                            maxlength="500"
                            required
                        >
                    </label>

                    <button type="submit" class="button outline">
                        HỦY DÒNG DỊCH VỤ
                    </button>
                </form>
            </details>
        <?php endif; ?>
    </article>
<?php endforeach; ?>

<script>
(() => {
    const form = document.getElementById('add-service-form');
    if (!form) return;

    const select = document.getElementById('service-select');
    const quantity = document.getElementById('service-quantity');
    const quotedPrice = document.getElementById('service-quoted-price');
    const preview = document.getElementById('service-preview');

    const update = () => {
        const price = Number(select.selectedOptions[0].dataset.price);
        const count = Number(quantity.value);

        quotedPrice.value = String(price);

        preview.textContent =
            Number.isInteger(count) && count >= 1 && count <= 100
                ? new Intl.NumberFormat('vi-VN').format(price * count) + ' ₫'
                : 'Vui lòng nhập số lượng từ 1 đến 100';
    };

    select.addEventListener('change', update);
    quantity.addEventListener('input', update);
    update();
})();
</script>

<?php require __DIR__ . '/../includes/staff_footer.php'; ?>