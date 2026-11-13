<?php
require __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/loyalty.php';

$user = admin_required();
$title = 'Thành viên thân thiết';
$error = '';

if (post()) {
    check_csrf();

    try {
        $bookingId = (int) input('booking_id');

        db()->beginTransaction();

        // Khóa đơn trước để đồng bộ với thao tác trả phòng.
        $booking = query(
            'SELECT id, customer_id, status
             FROM bookings
             WHERE id = ?
             FOR UPDATE',
            [$bookingId]
        )->fetch();

        if (!$booking || $booking['status'] !== 'checked_in') {
            throw new DomainException(
                'Chỉ xác nhận trao quyền lợi khi khách đang lưu trú.'
            );
        }

        $benefit = query(
            'SELECT * FROM booking_member_benefits
             WHERE booking_id = ? AND customer_id = ?
             FOR UPDATE',
            [$bookingId, $booking['customer_id']]
        )->fetch();

        if (!$benefit) {
            throw new DomainException('Đơn này chưa được cấp quyền lợi.');
        }

        if ($benefit['delivered_at'] !== null) {
            throw new DomainException('Quyền lợi của đơn đã được trao.');
        }

        query(
            'UPDATE booking_member_benefits
             SET delivered_at = NOW(), delivered_by = ?
             WHERE booking_id = ?',
            [$user['id'], $bookingId]
        );

        audit(
            'loyalty_delivered',
            'Đơn #' . $bookingId . ' / ' . $benefit['benefit_name']
        );

        db()->commit();
        flash('Đã ghi nhận trao quyền lợi cho khách.');
        go('admin/members.php?customer_id=' . (int) $booking['customer_id']);
    } catch (DomainException $ex) {
        if (db()->inTransaction()) db()->rollBack();
        $error = $ex->getMessage();
    } catch (Throwable $ex) {
        if (db()->inTransaction()) db()->rollBack();
        error_log((string) $ex);
        $error = 'Chưa lưu được kết quả. Vui lòng tải lại trang.';
    }
}

$search = mb_substr(input('q'), 0, 100);
$customerId = (int) input('customer_id');
$customer = null;
$benefits = [];

if ($customerId > 0) {
    $customer = query(
        'SELECT id, full_name, email FROM customers WHERE id = ?',
        [$customerId]
    )->fetch();

    if (!$customer) {
        http_response_code(404);
        exit('Không tìm thấy thành viên.');
    }

    $points = loyalty_points($customerId);
    $level = loyalty_level($points);

    $benefits = query(
        'SELECT m.*, b.status AS booking_status, r.name AS room_name,
                a.display_name AS delivered_by_name
         FROM booking_member_benefits m
         JOIN bookings b ON b.id = m.booking_id
         JOIN room_types r ON r.id = b.room_type_id
         LEFT JOIN admins a ON a.id = m.delivered_by
         WHERE m.customer_id = ?
         ORDER BY m.granted_at DESC, m.booking_id DESC',
        [$customerId]
    )->fetchAll();
} else {
    $like = '%' . $search . '%';
    $perPage = 20;

    $total = (int) query(
        'SELECT COUNT(*) FROM customers
         WHERE full_name LIKE ? OR email LIKE ?',
        [$like, $like]
    )->fetchColumn();

    $pages = max(1, (int) ceil($total / $perPage));
    $page = max(1, min($pages, (int) input('page', '1')));
    $offset = ($page - 1) * $perPage;

    $members = query(
        "SELECT c.id, c.full_name, c.email,
                COALESCE(SUM(FLOOR(b.total_price / ?)), 0) AS points
         FROM customers c
         LEFT JOIN bookings b
                ON b.customer_id = c.id AND b.status = 'completed'
         WHERE c.full_name LIKE ? OR c.email LIKE ?
         GROUP BY c.id, c.full_name, c.email
         ORDER BY points DESC, c.id ASC
         LIMIT $perPage OFFSET $offset",
        [LOYALTY_VND_PER_POINT, $like, $like]
    )->fetchAll();
}

require __DIR__ . '/../includes/staff_header.php';
?>

<?php if ($customer): ?>
    <p>
        <a class="text-link" href="<?= e(url('admin/members.php')) ?>">
            ← Danh sách thành viên
        </a>
    </p>

    <div class="panel">
        <h2><?= e($customer['full_name']) ?></h2>
        <p><?= e($customer['email']) ?></p>
        <p>
            Hạng <strong><?= e($level['current']['name']) ?></strong>
            · <?= (int) $points ?> điểm
        </p>
    </div>

    <h2 style="margin-top:24px;">Quyền lợi theo từng đơn</h2>

    <?php if (!$benefits): ?>
        <div class="notice">Thành viên chưa có quyền lợi được cấp.</div>
    <?php endif; ?>

    <?php foreach ($benefits as $benefit): ?>
        <article class="panel" style="margin-bottom:16px;">
            <h3><?= e($benefit['benefit_name']) ?></h3>

            <p>
                Đơn #<?= (int) $benefit['booking_id'] ?>
                · <?= e($benefit['room_name']) ?>
                · Hạng <?= e($benefit['tier_name']) ?>
                (<?= (int) $benefit['points_at_checkin'] ?> điểm khi nhận phòng)
            </p>

            <p><strong><?= e(loyalty_benefit_status($benefit)) ?></strong></p>

            <?php if ($benefit['delivered_at']): ?>
                <p>
                    <?= e(date('d/m/Y H:i', strtotime($benefit['delivered_at']))) ?>
                    · <?= e($benefit['delivered_by_name'] ?? '') ?>
                </p>
            <?php elseif ($benefit['booking_status'] === 'checked_in'): ?>
                <form method="post"
                      data-confirm="Xác nhận khách đã nhận quyền lợi này?">
                    <?= csrf() ?>
                    <input type="hidden" name="booking_id"
                           value="<?= (int) $benefit['booking_id'] ?>">

                    <button class="button" type="submit">
                        XÁC NHẬN ĐÃ TRAO
                    </button>
                </form>
            <?php endif; ?>
        </article>
    <?php endforeach; ?>

<?php else: ?>
    <form method="get" class="panel">
        <label>
            Tìm thành viên
            <input name="q" value="<?= e($search) ?>" maxlength="100"
                   placeholder="Họ tên hoặc email">
        </label>
        <button type="submit" class="button">TÌM KIẾM</button>
    </form>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Thành viên</th>
                    <th>Email</th>
                    <th>Điểm</th>
                    <th>Hạng</th>
                    <th>Quyền lợi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($members as $member): ?>
                    <?php $tier = loyalty_level((int) $member['points'])['current']; ?>
                    <tr>
                        <td><?= e($member['full_name']) ?></td>
                        <td><?= e($member['email']) ?></td>
                        <td><?= (int) $member['points'] ?></td>
                        <td><?= e($tier['name']) ?></td>
                        <td>
                            <a class="text-link"
                               href="<?= e(url(
                                   'admin/members.php?customer_id=' . (int) $member['id']
                               )) ?>">
                                Xem / trao quyền lợi →
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>

                <?php if (!$members): ?>
                    <tr><td colspan="5">Không tìm thấy thành viên.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <nav class="actions" aria-label="Phân trang thành viên">
        <?php if ($page > 1): ?>
            <a href="<?= e(url(
                'admin/members.php?q=' . rawurlencode($search) . '&page=' . ($page - 1)
            )) ?>">← Trang trước</a>
        <?php endif; ?>

        <span>Trang <?= $page ?> / <?= $pages ?></span>

        <?php if ($page < $pages): ?>
            <a href="<?= e(url(
                'admin/members.php?q=' . rawurlencode($search) . '&page=' . ($page + 1)
            )) ?>">Trang sau →</a>
        <?php endif; ?>
    </nav>
<?php endif; ?>

<?php require __DIR__ . '/../includes/staff_footer.php'; ?>