<?php
require __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/customer.php';

$customer = customer_required();
$customerId = (int) $customer['id'];
$title = 'Đánh giá sau lưu trú';
$error = '';

$idText = input('id');
$bookingId = ctype_digit($idText) ? (int) $idText : 0;

// Luôn lấy customer_id từ phiên đăng nhập.
$booking = query(
    'SELECT b.id, b.status, b.check_in, b.check_out,
            b.actual_check_out, r.name AS room_name
     FROM bookings b
     JOIN room_types r ON r.id = b.room_type_id
     WHERE b.id = ? AND b.customer_id = ?
     LIMIT 1',
    [$bookingId, $customerId]
)->fetch();

if (!$booking) {
    http_response_code(404);
    require __DIR__ . '/includes/header.php';
    ?>
    <section class="section narrow">
        <h1>Không tìm thấy đơn đặt phòng.</h1>
        <p>Đơn không tồn tại hoặc không thuộc tài khoản của bạn.</p>
        <a class="button" href="<?= e(url('account.php#history')) ?>">
            QUAY LẠI LỊCH SỬ
        </a>
    </section>
    <?php
    require __DIR__ . '/includes/footer.php';
    exit;
}

$ratingLabels = [
    1 => 'Rất không hài lòng',
    2 => 'Không hài lòng',
    3 => 'Bình thường',
    4 => 'Hài lòng',
    5 => 'Rất hài lòng'
];

$ratingInput = input('rating');
$comment = input('comment');

if (post()) {
    check_csrf();

    try {
        db()->beginTransaction();

        // Kiểm tra lại quyền sở hữu và trạng thái ngay lúc ghi đánh giá.
        $lockedBooking = query(
            'SELECT id, status
             FROM bookings
             WHERE id = ? AND customer_id = ?
             FOR UPDATE',
            [$bookingId, $customerId]
        )->fetch();

        if (!$lockedBooking || $lockedBooking['status'] !== 'completed') {
            throw new DomainException(
                'Bạn chỉ có thể đánh giá sau khi khách sạn hoàn tất trả phòng.'
            );
        }

        $existing = query(
            'SELECT booking_id FROM booking_reviews WHERE booking_id = ?',
            [$bookingId]
        )->fetchColumn();

        if ($existing) {
            throw new DomainException('Kỳ nghỉ này đã có đánh giá của bạn.');
        }

        if (!in_array($ratingInput, ['1', '2', '3', '4', '5'], true)) {
            throw new DomainException('Vui lòng chọn mức đánh giá từ 1 đến 5 sao.');
        }

        if ($comment === '' || mb_strlen($comment) > 2000) {
            throw new DomainException('Nhận xét cần từ 1 đến 2.000 ký tự.');
        }

        query(
            'INSERT INTO booking_reviews
                (booking_id, customer_id, rating, comment)
             VALUES (?, ?, ?, ?)',
            [$bookingId, $customerId, (int) $ratingInput, $comment]
        );

        db()->commit();

        flash('Đã gửi đánh giá. Cảm ơn bạn đã chia sẻ về kỳ nghỉ.');
        go('booking-review.php?id=' . $bookingId);
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
        $error = 'Chưa lưu được đánh giá. Vui lòng tải lại trang và kiểm tra.';
    }
}

$review = query(
    'SELECT rating, comment, created_at, admin_reply, replied_at
     FROM booking_reviews
     WHERE booking_id = ? AND customer_id = ?',
    [$bookingId, $customerId]
)->fetch();

require __DIR__ . '/includes/header.php';
?>

<style>
.stay-review .review-options {
    display: grid;
    gap: 8px;
    margin: 12px 0 20px;
}

.stay-review .review-option {
    display: flex;
    align-items: center;
    gap: 12px;
    margin: 0;
    padding: 12px 14px;
    border: 1px solid var(--line, #ddd6ca);
    cursor: pointer;
}

.stay-review .review-option input {
    width: auto;
    margin: 0;
    accent-color: #79603b;
}

.stay-review .review-option:focus-within {
    outline: 2px solid #9a8055;
    outline-offset: 2px;
}

.stay-review .review-stars {
    color: #806437;
    letter-spacing: 2px;
}

.stay-review .review-text {
    white-space: pre-wrap;
    overflow-wrap: anywhere;
}

.stay-review .hotel-reply {
    margin-top: 24px;
    padding: 16px 20px;
    border-left: 3px solid #ae9060;
    background: #f5f1e9;
}
</style>

<section class="page-heading">
    <div class="eyebrow">THE ONYX &amp; IVORY / TÀI KHOẢN</div>
    <h1>Đánh giá <em>kỳ nghỉ.</em></h1>
</section>

<section class="section narrow stay-review">
    <p>
        <a class="text-link"
           href="<?= e(url('booking-detail.php?id=' . $bookingId)) ?>">
            ← Quay lại chi tiết đơn
        </a>
    </p>

    <div class="panel">
        <div class="eyebrow"><?= e(status_name($booking['status'])) ?></div>
        <h2><?= e($booking['room_name']) ?></h2>

        <p>
            Đơn #<?= $bookingId ?> ·
            <?= e(date('d/m/Y', strtotime($booking['check_in']))) ?>
            →
            <?= e(date('d/m/Y', strtotime($booking['check_out']))) ?>
        </p>

        <?php if ($booking['actual_check_out']): ?>
            <p>
                Đã trả phòng lúc
                <?= e(date('d/m/Y H:i', strtotime($booking['actual_check_out']))) ?>.
            </p>
        <?php endif; ?>
    </div>

    <?php if ($error !== ''): ?>
        <div class="notice error" role="alert"><?= e($error) ?></div>
    <?php endif; ?>

    <?php if ($review): ?>
        <article class="panel" style="margin-top:24px;">
            <h2>Đánh giá của bạn</h2>

            <p>
                <span class="review-stars" aria-hidden="true">
                    <?= str_repeat('★', (int) $review['rating']) ?>
                </span>
                <strong><?= (int) $review['rating'] ?>/5</strong>
                · <?= e($ratingLabels[(int) $review['rating']]) ?>
            </p>

            <p class="review-text"><?= e($review['comment']) ?></p>

            <small>
                Gửi lúc <?= e(date('d/m/Y H:i', strtotime($review['created_at']))) ?>
            </small>

            <?php if ($review['replied_at'] !== null): ?>
                <div class="hotel-reply">
                    <h3>Phản hồi từ The Onyx &amp; Ivory</h3>
                    <p class="review-text"><?= e($review['admin_reply']) ?></p>
                    <small>
                        <?= e(date('d/m/Y H:i', strtotime($review['replied_at']))) ?>
                    </small>
                </div>
            <?php else: ?>
                <p>Khách sạn đã nhận đánh giá của bạn. Phản hồi sẽ hiển thị tại đây.</p>
            <?php endif; ?>
        </article>

    <?php elseif ($booking['status'] !== 'completed'): ?>
        <div class="notice" style="margin-top:24px;">
            Bạn có thể đánh giá sau khi khách sạn hoàn tất thủ tục trả phòng.
        </div>

    <?php else: ?>
        <form method="post"
              action="<?= e(url('booking-review.php?id=' . $bookingId)) ?>"
              class="panel" style="margin-top:24px;">
            <?= csrf() ?>

            <h2>Kỳ nghỉ của bạn thế nào?</h2>

            <fieldset style="border:0;padding:0;margin:0;">
                <legend>Mức độ hài lòng</legend>

                <div class="review-options">
                    <?php foreach ($ratingLabels as $score => $label): ?>
                        <label class="review-option">
                            <input type="radio" name="rating"
                                   value="<?= $score ?>"
                                   <?= $ratingInput === (string) $score ? 'checked' : '' ?>
                                   required>
                            <span>
                                <strong><?= $score ?> sao</strong>
                                · <?= e($label) ?>
                            </span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </fieldset>

            <label for="review-comment">
                Nhận xét của bạn
                <textarea id="review-comment" name="comment"
                          rows="5" maxlength="2000" required
                          placeholder="Bạn ấn tượng điều gì? Khách sạn có thể cải thiện điều gì?"><?= e($comment) ?></textarea>
            </label>

            <p><small>Bạn có thể gửi một đánh giá cho kỳ nghỉ này.</small></p>

            <button type="submit" class="button full">
                GỬI ĐÁNH GIÁ ↗
            </button>
        </form>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>