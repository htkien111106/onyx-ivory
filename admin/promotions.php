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

require __DIR__ . '/../includes/admin_views/promotions.php';
