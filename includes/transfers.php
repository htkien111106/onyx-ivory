<?php
require_once __DIR__ . '/operations.php';
require_once __DIR__ . '/services.php';

function transfer_bank(): array
{
    static $config = null;

    if ($config === null) {
        $config = require __DIR__ . '/../config/bank.php';
    }

    return $config;
}

function transfer_bank_ready(): bool
{
    $bank = transfer_bank();

    return trim($bank['bank_name']) !== ''
        && trim($bank['account_number']) !== ''
        && trim($bank['account_holder']) !== ''
        && (int) $bank['deposit_percent'] >= 1
        && (int) $bank['deposit_percent'] <= 100;
}

function transfer_status_name(string $status): string
{
    return [
        'awaiting'  => 'Chờ khách chuyển khoản',
        'reported'  => 'Khách báo đã chuyển – chờ đối chiếu',
        'confirmed' => 'Đã xác nhận nhận tiền',
        'rejected'  => 'Đã từ chối',
        'cancelled' => 'Khách đã hủy yêu cầu',
    ][$status] ?? $status;
}

function transfer_purpose_name(string $purpose): string
{
    return $purpose === 'deposit'
        ? 'Đặt cọc tiền phòng'
        : 'Thanh toán phần còn lại';
}

function transfer_payable(array $booking): bool
{
    return in_array(
        $booking['status'],
        ['pending', 'confirmed', 'checked_in'],
        true
    );
}

function transfer_amounts(array $booking): array
{
    $roomTotal = (int) $booking['total_price'];
    $grand = $roomTotal + booking_service_total((int) $booking['id']);
    $paid = paid_amount((int) $booking['id']);
    $due = max(0, $grand - $paid);
    $percent = (int) transfer_bank()['deposit_percent'];

    // Làm tròn lên đến 1 đồng, không dùng số thực cho tiền.
    $depositTarget = intdiv($roomTotal * $percent + 99, 100);

    return [
        'grand'   => $grand,
        'paid'    => $paid,
        'due'     => $due,
        'deposit' => min($due, max(0, $depositTarget - $paid)),
    ];
}

/**
 * Gọi bên trong transaction, sau khi đã khóa dòng booking.
 * Tái sử dụng kiểm tra nghiệp vụ của record_payment().
 */
function transfer_post_receipt(array $transfer, string $bankRef): void
{
    $key = bin2hex(random_bytes(16));
    $_SESSION['payment_keys'][$key] = time();

    try {
        record_payment(
            (int) $transfer['booking_id'],
            'payment',
            (string) $transfer['amount'],
            'bank',
            $transfer['transfer_code'] . ' / ' . $bankRef,
            $key
        );
    } finally {
        unset($_SESSION['payment_keys'][$key]);
    }
}