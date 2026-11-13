<?php

// Số tiền phòng tương ứng với 1 điểm.
const LOYALTY_VND_PER_POINT = 100000;

function loyalty_tiers(): array
{
    // Sắp xếp từ mốc thấp đến cao.
    return [
        [
            'name' => 'Thành viên',
            'minimum' => 0,
            'benefit' => '',
        ],
        [
            'name' => 'Bạc',
            'minimum' => 20,
            'benefit' => 'Một phần đồ uống chào mừng',
        ],
        [
            'name' => 'Vàng',
            'minimum' => 50,
            'benefit' => 'Một phần trái cây tại phòng',
        ],
        [
            'name' => 'Kim cương',
            'minimum' => 100,
            'benefit' => 'Một phần trà chiều cho hai người',
        ],
    ];
}

function loyalty_points(int $customerId): int
{
    return (int) query(
        "SELECT COALESCE(SUM(FLOOR(total_price / ?)), 0)
         FROM bookings
         WHERE customer_id = ?
           AND status = 'completed'",
        [LOYALTY_VND_PER_POINT, $customerId]
    )->fetchColumn();
}

function loyalty_level(int $points): array
{
    $tiers = loyalty_tiers();
    $current = $tiers[0];
    $next = null;

    foreach ($tiers as $tier) {
        if ($points < $tier['minimum']) {
            $next = $tier;
            break;
        }

        $current = $tier;
    }

    return [
        'current' => $current,
        'next' => $next,
    ];
}

/**
 * Gọi khi nhận phòng thành công, bên trong transaction hiện có.
 * Lưu tên hạng và quà tại thời điểm nhận phòng.
 */
function loyalty_grant_at_checkin(int $bookingId): void
{
    $booking = query(
        'SELECT id, customer_id, status
         FROM bookings
         WHERE id = ?
         FOR UPDATE',
        [$bookingId]
    )->fetch();

    if (
        !$booking ||
        $booking['status'] !== 'checked_in' ||
        empty($booking['customer_id'])
    ) {
        return;
    }

    $existing = query(
        'SELECT booking_id
         FROM booking_member_benefits
         WHERE booking_id = ?',
        [$bookingId]
    )->fetchColumn();

    if ($existing) {
        return;
    }

    $customerId = (int) $booking['customer_id'];
    $points = loyalty_points($customerId);
    $tier = loyalty_level($points)['current'];

    if ($tier['benefit'] === '') {
        return;
    }

    query(
        'INSERT INTO booking_member_benefits
            (booking_id, customer_id, tier_name, benefit_name, points_at_checkin)
         VALUES (?, ?, ?, ?, ?)',
        [
            $bookingId,
            $customerId,
            $tier['name'],
            $tier['benefit'],
            $points,
        ]
    );
}

function loyalty_benefit_status(array $benefit): string
{
    if (!empty($benefit['delivered_at'])) {
        return 'Đã trao';
    }

    if ($benefit['booking_status'] === 'checked_in') {
        return 'Chờ trao trong kỳ nghỉ';
    }

    return 'Kỳ nghỉ đã kết thúc – chưa ghi nhận trao';
}