<?php
require __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/customer.php';
require_once __DIR__ . '/includes/loyalty.php';

$customer = customer_required();
$customerId = (int) $customer['id'];

$title = 'Thành viên thân thiết';
$points = loyalty_points($customerId);
$level = loyalty_level($points);
$current = $level['current'];
$next = $level['next'];

$history = query(
    "SELECT b.id, b.check_out, b.actual_check_out, b.total_price,
            r.name AS room_name,
            FLOOR(b.total_price / ?) AS earned_points
     FROM bookings b
     JOIN room_types r ON r.id = b.room_type_id
     WHERE b.customer_id = ?
       AND b.status = 'completed'
     ORDER BY COALESCE(b.actual_check_out, b.check_out) DESC, b.id DESC
     LIMIT 20",
    [LOYALTY_VND_PER_POINT, $customerId]
)->fetchAll();

$benefits = query(
    'SELECT m.*, b.status AS booking_status,
            b.check_in, b.check_out, r.name AS room_name
     FROM booking_member_benefits m
     JOIN bookings b ON b.id = m.booking_id
     JOIN room_types r ON r.id = b.room_type_id
     WHERE m.customer_id = ? AND b.customer_id = ?
     ORDER BY m.granted_at DESC, m.booking_id DESC
     LIMIT 20',
    [$customerId, $customerId]
)->fetchAll();

require __DIR__ . '/includes/header.php';
?>

<section class="page-heading">
    <div class="eyebrow">THE ONYX &amp; IVORY / THÀNH VIÊN</div>
    <h1>Thẻ <em>thành viên.</em></h1>
</section>

<section class="section narrow">
    <p>
        <a class="text-link" href="<?= e(url('account.php')) ?>">
            ← Quay lại tài khoản
        </a>
    </p>

    <div class="panel" style="background:#202522;color:#faf7ef;">
        <div class="eyebrow" style="color:#d7bd86;">
            THE ONYX &amp; IVORY
        </div>

        <h2 style="color:inherit;">
            <?= e($customer['full_name']) ?>
        </h2>

        <p>
            Hạng <strong><?= e($current['name']) ?></strong>
            · <strong><?= number_format($points, 0, ',', '.') ?> điểm</strong>
        </p>

        <?php if ($next): ?>
            <p>
                Còn <strong><?= (int) $next['minimum'] - $points ?> điểm</strong>
                để lên hạng <?= e($next['name']) ?>.
            </p>
        <?php else: ?>
            <p>Bạn đang ở hạng cao nhất của chương trình.</p>
        <?php endif; ?>
    </div>

    <div class="panel" style="margin-top:24px;">
        <h2>Quyền lợi theo hạng</h2>
        <p>
            Mỗi <?= money(LOYALTY_VND_PER_POINT) ?> tiền phòng
            của một kỳ nghỉ đã hoàn tất được tính 1 điểm.
            Quyền lợi được xác định khi bạn nhận phòng.
        </p>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Hạng</th>
                        <th>Từ mức điểm</th>
                        <th>Quyền lợi mỗi kỳ nghỉ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (loyalty_tiers() as $tier): ?>
                        <tr>
                            <td><?= e($tier['name']) ?></td>
                            <td><?= (int) $tier['minimum'] ?></td>
                            <td><?= e($tier['benefit'] ?: 'Tích điểm qua các kỳ nghỉ') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="panel" style="margin-top:24px;">
        <h2>Quyền lợi của bạn</h2>

        <?php if (!$benefits): ?>
            <p>Bạn chưa có quyền lợi được cấp cho kỳ nghỉ nào.</p>
        <?php endif; ?>

        <?php foreach ($benefits as $benefit): ?>
            <article style="border-bottom:1px solid #ded8ca;padding:14px 0;">
                <strong><?= e($benefit['benefit_name']) ?></strong>

                <p>
                    <?= e($benefit['room_name']) ?>
                    · Hạng <?= e($benefit['tier_name']) ?> khi nhận phòng
                </p>

                <p>
                    <?= e(loyalty_benefit_status($benefit)) ?>
                    <?php if ($benefit['delivered_at']): ?>
                        · <?= e(date('d/m/Y H:i', strtotime($benefit['delivered_at']))) ?>
                    <?php endif; ?>
                </p>

                <a class="text-link"
                   href="<?= e(url(
                       'booking-detail.php?id=' . (int) $benefit['booking_id']
                   )) ?>">
                    Xem đơn #<?= (int) $benefit['booking_id'] ?> →
                </a>
            </article>
        <?php endforeach; ?>
    </div>

    <div class="panel" style="margin-top:24px;">
        <h2>Lịch sử tích điểm</h2>

        <?php if (!$history): ?>
            <p>
                Khi bạn hoàn tất kỳ nghỉ đầu tiên bằng tài khoản này,
                điểm sẽ xuất hiện tại đây.
            </p>
        <?php else: ?>
            <p>Hiển thị 20 kỳ nghỉ gần nhất. Tổng điểm gồm tất cả kỳ nghỉ đủ điều kiện.</p>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Kỳ nghỉ</th>
                            <th>Ngày trả phòng</th>
                            <th>Tiền phòng</th>
                            <th>Điểm</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($history as $stay): ?>
                            <tr>
                                <td>
                                    <a href="<?= e(url(
                                        'booking-detail.php?id=' . (int) $stay['id']
                                    )) ?>">
                                        <?= e($stay['room_name']) ?>
                                    </a>
                                </td>
                                <td>
                                    <?= e(date(
                                        'd/m/Y',
                                        strtotime($stay['actual_check_out'] ?: $stay['check_out'])
                                    )) ?>
                                </td>
                                <td><?= money($stay['total_price']) ?></td>
                                <td>+<?= (int) $stay['earned_points'] ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>