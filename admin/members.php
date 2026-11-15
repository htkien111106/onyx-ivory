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

require __DIR__ . '/../includes/admin_views/members.php';
