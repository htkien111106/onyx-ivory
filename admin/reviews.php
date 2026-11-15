<?php
require __DIR__ . '/../includes/bootstrap.php';

$user = admin_required();
$title = 'Đánh giá sau lưu trú';
$error = '';

$filter = input('filter') === 'all' ? 'all' : 'unanswered';
$page = max(1, (int) input('page', '1'));
$replyBookingId = 0;
$replyText = '';

if (post()) {
    check_csrf();

    $replyBookingId = (int) input('booking_id');
    $replyText = input('admin_reply');

    try {
        if ($replyText === '' || mb_strlen($replyText) > 2000) {
            throw new DomainException('Phản hồi cần từ 1 đến 2.000 ký tự.');
        }

        db()->beginTransaction();

        $review = query(
            'SELECT booking_id, replied_at
             FROM booking_reviews
             WHERE booking_id = ?
             FOR UPDATE',
            [$replyBookingId]
        )->fetch();

        if (!$review) {
            throw new DomainException('Không tìm thấy đánh giá cần phản hồi.');
        }

        if ($review['replied_at'] !== null) {
            throw new DomainException(
                'Đánh giá này đã được phản hồi. Vui lòng xem trong mục Tất cả đánh giá.'
            );
        }

        query(
            'UPDATE booking_reviews
             SET admin_reply = ?, replied_by = ?, replied_at = NOW()
             WHERE booking_id = ?',
            [$replyText, $user['id'], $replyBookingId]
        );

        audit('review_reply', 'Phản hồi đánh giá của đơn #' . $replyBookingId);

        db()->commit();

        flash('Đã gửi phản hồi cho khách hàng.');
        go('admin/reviews.php?filter=' . $filter . '&page=' . $page);
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
        $error = 'Chưa lưu được phản hồi. Vui lòng tải lại trang và kiểm tra.';
    }
}

// Hai lựa chọn cố định, không đưa nội dung người dùng nhập vào SQL.
$where = $filter === 'unanswered' ? 'WHERE rv.replied_at IS NULL' : '';

$total = (int) query(
    "SELECT COUNT(*) FROM booking_reviews rv $where"
)->fetchColumn();

$perPage = 10;
$totalPages = max(1, (int) ceil($total / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

$reviews = query(
    "SELECT rv.*, c.full_name, r.name AS room_name,
            b.check_in, b.check_out, a.display_name AS responder
     FROM booking_reviews rv
     JOIN customers c ON c.id = rv.customer_id
     JOIN bookings b ON b.id = rv.booking_id
     JOIN room_types r ON r.id = b.room_type_id
     LEFT JOIN admins a ON a.id = rv.replied_by
     $where
     ORDER BY rv.created_at DESC, rv.booking_id DESC
     LIMIT $perPage OFFSET $offset"
)->fetchAll();

require __DIR__ . '/../includes/admin_views/reviews.php';
