<?php

function promotion_normalize_code(string $value): string
{
    return strtoupper(trim($value));
}

function promotion_valid_date(string $value): bool
{
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

    return $date !== false && $date->format('Y-m-d') === $value;
}

/**
 * Tính mức giảm trên tiền phòng. Làm tròn xuống đến 1 đồng.
 */
function promotion_discount(int $gross, array $promotion): int
{
    if ($gross < (int) $promotion['min_room_total']) {
        return 0;
    }

    return intdiv($gross * (int) $promotion['discount_percent'], 100);
}

/**
 * Gọi trong transaction tạo đơn.
 * Kiểm tra lại mã và giá trị đơn bằng dữ liệu trên máy chủ.
 */
function promotion_for_new_booking(string $rawCode, int $gross): array
{
    $code = promotion_normalize_code($rawCode);

    if ($code === '') {
        return [
            'id' => null,
            'discount' => 0,
            'net' => $gross,
        ];
    }

    if (!preg_match('/^[A-Z0-9_-]{3,30}$/', $code)) {
        throw new DomainException('Mã giảm giá không hợp lệ.');
    }

    $promotion = query(
        'SELECT *
         FROM promotions
         WHERE code = ?
         FOR UPDATE',
        [$code]
    )->fetch();

    if (!$promotion) {
        throw new DomainException('Không tìm thấy mã giảm giá này.');
    }

    $today = date('Y-m-d');

    if (!(int) $promotion['active']) {
        throw new DomainException('Mã giảm giá đang tạm dừng.');
    }

    if ($today < $promotion['starts_on']) {
        throw new DomainException('Mã giảm giá chưa đến ngày áp dụng.');
    }

    if ($today > $promotion['ends_on']) {
        throw new DomainException('Mã giảm giá đã hết hạn.');
    }

    if ($gross < (int) $promotion['min_room_total']) {
        throw new DomainException(
            'Mã ' . $code . ' áp dụng cho tiền phòng từ '
            . money($promotion['min_room_total']) . '.'
        );
    }

    $discount = promotion_discount($gross, $promotion);

    return [
        'id' => (int) $promotion['id'],
        'discount' => $discount,
        'net' => $gross - $discount,
    ];
}

/**
 * Khi khách đổi ngày hoặc loại phòng, giữ quy tắc ưu đãi
 * đã nhận và xét lại ngưỡng tiền phòng theo phương án mới.
 */
function promotion_for_changed_booking(array $booking, int $gross): array
{
    if (empty($booking['promotion_id'])) {
        return ['discount' => 0, 'net' => $gross];
    }

    $promotion = query(
        'SELECT discount_percent, min_room_total
         FROM promotions
         WHERE id = ?',
        [$booking['promotion_id']]
    )->fetch();

    if (!$promotion) {
        throw new DomainException(
            'Chưa tìm thấy chương trình ưu đãi gắn với đơn.'
        );
    }

    $discount = promotion_discount($gross, $promotion);

    return [
        'discount' => $discount,
        'net' => $gross - $discount,
    ];
}