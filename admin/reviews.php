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

require __DIR__ . '/../includes/staff_header.php';
?>

<div class="actions align-left">
    <a class="button <?= $filter === 'unanswered' ? '' : 'outline' ?>"
       href="<?= e(url('admin/reviews.php')) ?>">
        CHƯA PHẢN HỒI
    </a>

    <a class="button <?= $filter === 'all' ? '' : 'outline' ?>"
       href="<?= e(url('admin/reviews.php?filter=all')) ?>">
        TẤT CẢ ĐÁNH GIÁ
    </a>
</div>

<p>
    <?= $filter === 'unanswered'
        ? 'Số đánh giá đang chờ phản hồi'
        : 'Tổng số đánh giá' ?>
    <strong><?= $total ?></strong>
</p>

<?php if (!$reviews): ?>
    <div class="notice">
        <?= $filter === 'unanswered'
            ? 'Không có đánh giá nào đang chờ phản hồi.'
            : 'Chưa có đánh giá sau lưu trú.' ?>
    </div>
<?php endif; ?>

<?php foreach ($reviews as $review): ?>
    <?php $bookingId = (int) $review['booking_id']; ?>

    <article class="panel" style="margin-bottom:20px;">
        <div class="eyebrow">
            <?= $review['replied_at'] === null ? 'CHƯA PHẢN HỒI' : 'ĐÃ PHẢN HỒI' ?>
        </div>

        <h2><?= e($review['full_name']) ?></h2>

        <p>
            <strong><?= (int) $review['rating'] ?>/5 sao</strong>
            · <?= e($review['room_name']) ?>
            · <a class="text-link"
                 href="<?= e(url('reception/booking.php?id=' . $bookingId)) ?>">
                Đơn #<?= $bookingId ?>
            </a>
        </p>

        <p>
            <?= e(date('d/m/Y', strtotime($review['check_in']))) ?>
            →
            <?= e(date('d/m/Y', strtotime($review['check_out']))) ?>
        </p>

        <p style="white-space:pre-wrap;overflow-wrap:anywhere;"><?= e($review['comment']) ?></p>

        <small>
            Khách gửi lúc <?= e(date('d/m/Y H:i', strtotime($review['created_at']))) ?>
        </small>

        <?php if ($review['replied_at'] !== null): ?>
            <div class="notice" style="margin-top:16px;">
                <strong>Phản hồi của khách sạn</strong>

                <p style="white-space:pre-wrap;overflow-wrap:anywhere;"><?= e($review['admin_reply']) ?></p>

                <small>
                    <?= e($review['responder'] ?? '') ?>
                    · <?= e(date('d/m/Y H:i', strtotime($review['replied_at']))) ?>
                </small>
            </div>
        <?php else: ?>
            <form method="post"
                  action="<?= e(url(
                      'admin/reviews.php?filter=' . $filter . '&page=' . $page
                  )) ?>"
                  style="margin-top:20px;">
                <?= csrf() ?>

                <input type="hidden" name="booking_id" value="<?= $bookingId ?>">

                <label for="reply-<?= $bookingId ?>">
                    Phản hồi cho khách
                    <textarea id="reply-<?= $bookingId ?>"
                              name="admin_reply" rows="3" maxlength="2000"
                              required><?= e($replyBookingId === $bookingId ? $replyText : '') ?></textarea>
                </label>

                <button type="submit" class="button">
                    GỬI PHẢN HỒI
                </button>
            </form>
        <?php endif; ?>
    </article>
<?php endforeach; ?>

<?php if ($totalPages > 1): ?>
    <nav class="actions" aria-label="Phân trang đánh giá">
        <?php if ($page > 1): ?>
            <a href="<?= e(url(
                'admin/reviews.php?filter=' . $filter . '&page=' . ($page - 1)
            )) ?>">
                ← Trang trước
            </a>
        <?php endif; ?>

        <span>Trang <?= $page ?> / <?= $totalPages ?></span>

        <?php if ($page < $totalPages): ?>
            <a href="<?= e(url(
                'admin/reviews.php?filter=' . $filter . '&page=' . ($page + 1)
            )) ?>">
                Trang sau →
            </a>
        <?php endif; ?>
    </nav>
<?php endif; ?>

<?php require __DIR__ . '/../includes/staff_footer.php'; ?>