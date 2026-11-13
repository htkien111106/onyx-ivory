<?php

function booking_service_total(int $bookingId): int
{
    return (int) query(
        'SELECT COALESCE(SUM(unit_price * quantity), 0)
         FROM booking_services
         WHERE booking_id = ?
           AND voided_at IS NULL',
        [$bookingId]
    )->fetchColumn();
}

function booking_grand_total(array $booking): int
{
    return (int) $booking['total_price']
        + booking_service_total((int) $booking['id']);
}