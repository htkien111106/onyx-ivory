<?php
require __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/promotions.php';

$user = admin_required();
$title = 'Khuyến mãi & mã giảm giá';
$error = '';

if (post()) {
    check_csrf();

    try {
        $action = input('action');

        db()->beginTransaction();

        if ($action === 'create') {
            $code = promotion_normalize_code(input('code'));
            $name = input('title');
            $percentText = input('discount_percent');
            $minimumText = input('min_room_total');
            $start = input('starts_on');
            $end = input('ends_on');

            if (!preg_match('/^[A-Z0-9_-]{3,30}$/', $code)) {
                throw new DomainException(
                    'Mã cần từ 3 đến 30 ký tự, gồm chữ A–Z, số, dấu - hoặc _.'
                );
            }

            if (mb_strlen($name) < 3 || mb_strlen($name) > 120) {
                throw new DomainException(
                    'Tên chương trình cần từ 3 đến 120 ký tự.'
                );
            }

            if (
                !ctype_digit($percentText)
                || (int) $percentText < 1
                || (int) $percentText > 100
            ) {
                throw new DomainException('Mức giảm phải từ 1% đến 100%.');
            }

            if (
                !ctype_digit($minimumText)
                || strlen($minimumText) > 12
            ) {
                throw new DomainException(
                    'Tiền phòng tối thiểu phải là số nguyên không âm, tối đa 12 chữ số.'
                );
            }

            if (
                !promotion_valid_date($start)
                || !promotion_valid_date($end)
                || $end < $start
            ) {
                throw new DomainException(
                    'Ngày kết thúc phải bằng hoặc sau ngày bắt đầu.'
                );
            }

            query(
                'INSERT INTO promotions
                    (code, title, discount_percent, min_room_total,
                     starts_on, ends_on, active)
                 VALUES (?, ?, ?, ?, ?, ?, 1)',
                [$code, $name, (int) $percentText, (int) $minimumText, $start, $end]
            );

            audit('promotion_create', 'Tạo mã ' . $code);
            $message = 'Đã tạo chương trình ' . $code . '.';
        } elseif ($action === 'set_active') {
            $id = (int) input('promotion_id');
            $target = input('active');

            if (!in_array($target, ['0', '1'], true)) {
                throw new DomainException('Trạng thái không hợp lệ.');
            }

            $promotion = query(
                'SELECT id, code FROM promotions WHERE id = ? FOR UPDATE',
                [$id]
            )->fetch();

            if (!$promotion) {
                throw new DomainException('Không tìm thấy chương trình.');
            }

            query(
                'UPDATE promotions SET active = ? WHERE id = ?',
                [(int) $target, $id]
            );

            $message = $target === '1'
                ? 'Đã bật mã ' . $promotion['code'] . '.'
                : 'Đã tạm dừng mã ' . $promotion['code'] . '.';

            audit('promotion_status', $message);
        } else {
            throw new DomainException('Thao tác không hợp lệ.');
        }

        db()->commit();
        flash($message);
        go('admin/promotions.php');
    } catch (DomainException $ex) {
        if (db()->inTransaction()) db()->rollBack();
        $error = $ex->getMessage();
    } catch (PDOException $ex) {
        if (db()->inTransaction()) db()->rollBack();

        if ((int) ($ex->errorInfo[1] ?? 0) === 1062) {
            $error = 'Mã này đã tồn tại. Vui lòng chọn mã khác.';
        } else {
            error_log((string) $ex);
            $error = 'Chưa lưu được chương trình khuyến mãi.';
        }
    } catch (Throwable $ex) {
        if (db()->inTransaction()) db()->rollBack();
        error_log((string) $ex);
        $error = 'Chưa xử lý được thao tác. Vui lòng thử lại.';
    }
}

$promotions = query(
    'SELECT p.*,
            (SELECT COUNT(*) FROM bookings b
             WHERE b.promotion_id = p.id
               AND b.discount_amount > 0
               AND b.status <> ?) AS booking_count
     FROM promotions p
     ORDER BY p.id DESC',
    ['cancelled']
)->fetchAll();

$today = date('Y-m-d');

require __DIR__ . '/../includes/staff_header.php';
?>

<form method="post" class="panel">
    <?= csrf() ?>
    <input type="hidden" name="action" value="create">

    <h2>Tạo chương trình ưu đãi</h2>

    <label>
        Tên chương trình
        <input name="title" minlength="3" maxlength="120" required
               value="<?= e(input('title')) ?>"
               placeholder="Ưu đãi kỳ nghỉ mùa đông">
    </label>

    <div class="form-grid">
        <label>
            Mã giảm giá
            <input name="code" minlength="3" maxlength="30" required
                   value="<?= e(input('code')) ?>"
                   placeholder="ONYX10">
        </label>

        <label>
            Mức giảm (%)
            <input type="number" name="discount_percent"
                   min="1" max="100" step="1" required
                   value="<?= e(input('discount_percent', '10')) ?>">
        </label>

        <label>
            Tiền phòng tối thiểu (đ)
            <input type="number" name="min_room_total"
                   min="0" max="999999999999" step="1" required
                   value="<?= e(input('min_room_total', '0')) ?>">
        </label>

        <label>
            Áp dụng từ ngày
            <input type="date" name="starts_on" required
                   value="<?= e(input('starts_on', $today)) ?>">
        </label>

        <label>
            Đến hết ngày
            <input type="date" name="ends_on" required
                   value="<?= e(input('ends_on')) ?>">
        </label>
    </div>

    <button type="submit" class="button">
        TẠO CHƯƠNG TRÌNH
    </button>
</form>

<h2 style="margin-top:28px;">Danh sách khuyến mãi</h2>

<div class="table-wrap">
    <table>
        <thead>
            <tr>
                <th>Chương trình</th>
                <th>Điều kiện</th>
                <th>Thời hạn</th>
                <th>Trạng thái</th>
                <th>Đơn đang áp dụng</th>
                <th>Thao tác</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($promotions as $promo): ?>
            <?php
            if (!(int) $promo['active']) {
                $statusText = 'Tạm dừng';
            } elseif ($today < $promo['starts_on']) {
                $statusText = 'Sắp bắt đầu';
            } elseif ($today > $promo['ends_on']) {
                $statusText = 'Đã hết hạn';
            } else {
                $statusText = 'Đang áp dụng';
            }
            ?>
            <tr>
                <td>
                    <strong><?= e($promo['code']) ?></strong><br>
                    <?= e($promo['title']) ?>
                </td>
                <td>
                    Giảm <?= (int) $promo['discount_percent'] ?>%<br>
                    Từ <?= money($promo['min_room_total']) ?>
                </td>
                <td>
                    <?= e($promo['starts_on']) ?> → <?= e($promo['ends_on']) ?>
                </td>
                <td><?= e($statusText) ?></td>
                <td><?= (int) $promo['booking_count'] ?></td>
                <td>
                    <form method="post">
                        <?= csrf() ?>
                        <input type="hidden" name="action" value="set_active">
                        <input type="hidden" name="promotion_id"
                               value="<?= (int) $promo['id'] ?>">
                        <input type="hidden" name="active"
                               value="<?= (int) $promo['active'] ? '0' : '1' ?>">
                        <button type="submit" class="button outline">
                            <?= (int) $promo['active'] ? 'TẠM DỪNG' : 'BẬT LẠI' ?>
                        </button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>

        <?php if (!$promotions): ?>
            <tr><td colspan="6">Chưa có chương trình khuyến mãi.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require __DIR__ . '/../includes/staff_footer.php'; ?>