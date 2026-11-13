<?php
require __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/operations.php';

$user = staff_required();
$id = (int) input('id');

$b = query(
    'SELECT b.*, r.name AS room_name, p.code AS promotion_code
     FROM bookings b
     JOIN room_types r ON r.id = b.room_type_id
     LEFT JOIN promotions p ON p.id = b.promotion_id
     WHERE b.id = ?',
    [$id]
)->fetch();

if (!$b) {
    http_response_code(404);
    exit('Không tìm thấy đơn.');
}

$title = 'Hóa đơn lưu trú nội bộ';

$nights = (int) (new DateTimeImmutable($b['check_in']))
    ->diff(new DateTimeImmutable($b['check_out']))->days;

$serviceRows = query(
    'SELECT service_name, unit, unit_price, quantity, created_at
     FROM booking_services
     WHERE booking_id = ? AND voided_at IS NULL
     ORDER BY created_at, id',
    [$id]
)->fetchAll();

$serviceTotal = 0;
foreach ($serviceRows as $row) {
    $serviceTotal += (int) $row['unit_price'] * (int) $row['quantity'];
}

$payments = query(
    'SELECT kind, amount, method, reference, created_at
     FROM payments
     WHERE booking_id = ?
     ORDER BY created_at, id',
    [$id]
)->fetchAll();

$totalPaid = 0;
$totalRefunded = 0;
foreach ($payments as $payment) {
    if ($payment['kind'] === 'payment') {
        $totalPaid += (int) $payment['amount'];
    } elseif ($payment['kind'] === 'refund') {
        $totalRefunded += (int) $payment['amount'];
    }
}

$roomTotal = (int) $b['total_price'];
$discount = (int) $b['discount_amount'];
$grossRoomTotal = $roomTotal + $discount;
$grandTotal = $roomTotal + $serviceTotal;

$netPaid = $totalPaid - $totalRefunded;
$isCancelled = $b['status'] === 'cancelled';
$amountDue = $isCancelled ? 0 : $grandTotal;
$remaining = max(0, $amountDue - $netPaid);
$overpaid = max(0, $netPaid - $amountDue);

$formatDate = static function ($value, bool $withTime = false): string {
    $timestamp = $value ? strtotime($value) : false;

    return $timestamp === false
        ? 'Chưa ghi nhận'
        : date($withTime ? 'd/m/Y H:i' : 'd/m/Y', $timestamp);
};

$paymentMethods = [
    'cash' => 'Tiền mặt',
    'bank' => 'Chuyển khoản'
];

require __DIR__ . '/../includes/header.php';
?>

<style>
.invoice .invoice-table {
    width: 100%;
    border-collapse: collapse;
}
.invoice .invoice-table th,
.invoice .invoice-table td {
    padding: 12px;
    border-bottom: 1px solid #ddd;
    text-align: left;
}
.invoice .invoice-table .amount { text-align: right; white-space: nowrap; }
.invoice .invoice-summary {
    margin-top: 28px;
    padding-top: 20px;
    border-top: 1px solid #c8b692;
}
.invoice .invoice-grand-total { color: #806739; font-size: 22px; font-weight: 700; }
.invoice .invoice-section-title { margin-top: 30px; }
.invoice .invoice-reference { overflow-wrap: anywhere; }

@media print {
    .site-header, .topline, .site-footer, body > footer,
    #flash-message, .invoice .actions { display: none !important; }
    .invoice { width: 100%; max-width: none; margin: 0; padding: 0; }
    .invoice .panel { padding: 16px; border: 0; box-shadow: none; }
    .invoice .table-wrap { overflow: visible; }
    .invoice .invoice-table th, .invoice .invoice-table td { padding: 8px; }
    .invoice thead { display: table-header-group; }
    .invoice tr, .invoice .invoice-summary { break-inside: avoid; }
    .invoice a { color: inherit; text-decoration: none; }
}
</style>

<section class="section invoice">
    <div class="eyebrow">THE ONYX &amp; IVORY</div>
    <h1>Hóa đơn lưu trú</h1>
    <p>Chứng từ nội bộ · Không thay thế hóa đơn thuế/VAT</p>

    <div class="panel">
        <dl>
            <dt>Số chứng từ</dt>
            <dd>INV-<?= e(str_pad((string) $id, 6, '0', STR_PAD_LEFT)) ?></dd>

            <dt>Mã đặt phòng</dt>
            <dd class="invoice-reference"><?= e($b['code']) ?></dd>

            <dt>Khách hàng</dt>
            <dd><?= e($b['guest_name']) ?></dd>

            <dt>Email / điện thoại</dt>
            <dd class="invoice-reference"><?= e($b['email']) ?> / <?= e($b['phone']) ?></dd>

            <dt>Ngày lưu trú dự kiến</dt>
            <dd><?= e($formatDate($b['check_in'])) ?> → <?= e($formatDate($b['check_out'])) ?></dd>

            <dt>Nhận phòng thực tế</dt>
            <dd><?= e($formatDate($b['actual_check_in'], true)) ?></dd>

            <dt>Trả phòng thực tế</dt>
            <dd><?= e($formatDate($b['actual_check_out'], true)) ?></dd>

            <dt>Trạng thái đơn</dt>
            <dd><?= e(status_name($b['status'])) ?></dd>
        </dl>

        <h2 class="invoice-section-title">Tiền phòng</h2>
        <div class="table-wrap">
            <table class="invoice-table">
                <thead>
                    <tr>
                        <th scope="col">Loại phòng</th>
                        <th scope="col">Số phòng</th>
                        <th scope="col">Số đêm</th>
                        <th scope="col" class="amount">Đơn giá / đêm</th>
                        <th scope="col" class="amount">Thành tiền trước ưu đãi</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><?= e($b['room_name']) ?></td>
                        <td><?= (int) $b['quantity'] ?></td>
                        <td><?= $nights ?></td>
                        <td class="amount"><?= money($b['nightly_price']) ?></td>
                        <td class="amount"><?= money($grossRoomTotal) ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
        <p><small>Tiền phòng đã gồm thuế và bữa sáng.</small></p>

        <h2 class="invoice-section-title">Dịch vụ phát sinh</h2>
        <?php if (!$serviceRows): ?>
            <p>Không có dịch vụ phát sinh được tính vào đơn.</p>
        <?php else: ?>
            <div class="table-wrap">
                <table class="invoice-table">
                    <thead>
                        <tr>
                            <th scope="col">Dịch vụ</th>
                            <th scope="col">Số lượng</th>
                            <th scope="col">Đơn vị</th>
                            <th scope="col" class="amount">Đơn giá</th>
                            <th scope="col" class="amount">Thành tiền</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($serviceRows as $row): ?>
                            <tr>
                                <td><?= e($row['service_name']) ?></td>
                                <td><?= (int) $row['quantity'] ?></td>
                                <td><?= e($row['unit']) ?></td>
                                <td class="amount"><?= money($row['unit_price']) ?></td>
                                <td class="amount">
                                    <?= money((int) $row['unit_price'] * (int) $row['quantity']) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <div class="invoice-summary">
            <dl>
                <?php if ($discount > 0): ?>
                    <dt>Tiền phòng trước ưu đãi</dt>
                    <dd><?= money($grossRoomTotal) ?></dd>

                    <dt>
                        Giảm giá khuyến mãi
                        <?php if ($b['promotion_code'] !== null): ?>
                            (<?= e($b['promotion_code']) ?>)
                        <?php endif; ?>
                    </dt>
                    <dd>−<?= money($discount) ?></dd>
                <?php endif; ?>

                <dt><?= $discount > 0 ? 'Tiền phòng sau ưu đãi' : 'Tiền phòng' ?></dt>
                <dd><?= money($roomTotal) ?></dd>

                <dt>Tiền dịch vụ</dt>
                <dd><?= money($serviceTotal) ?></dd>

                <dt>Tổng tiền phòng và dịch vụ</dt>
                <dd class="invoice-grand-total"><?= money($grandTotal) ?></dd>

                <dt>Tổng tiền đã thu</dt>
                <dd><?= money($totalPaid) ?></dd>

                <dt>Tổng tiền đã hoàn</dt>
                <dd><?= money($totalRefunded) ?></dd>

                <dt>Đã thu ròng</dt>
                <dd><?= money($netPaid) ?></dd>

                <dt><?= $isCancelled ? 'Còn phải thu (đơn đã hủy)' : 'Còn phải thu' ?></dt>
                <dd><strong><?= money($isCancelled ? 0 : $remaining) ?></strong></dd>

                <?php if ($overpaid > 0): ?>
                    <dt>Số tiền cần đối soát / hoàn lại</dt>
                    <dd><?= money($overpaid) ?></dd>
                <?php endif; ?>
            </dl>
        </div>

        <?php if ($isCancelled): ?>
            <div class="notice">
                Đơn đã hủy. Giá trị tiền phòng và dịch vụ được lưu để đối chiếu.
            </div>
        <?php endif; ?>

        <h2 class="invoice-section-title">Giao dịch đã ghi nhận</h2>
        <?php if (!$payments): ?>
            <p>Chưa ghi nhận giao dịch.</p>
        <?php else: ?>
            <div class="table-wrap">
                <table class="invoice-table">
                    <thead>
                        <tr>
                            <th scope="col">Thời gian</th>
                            <th scope="col">Giao dịch</th>
                            <th scope="col">Phương thức</th>
                            <th scope="col" class="amount">Số tiền</th>
                            <th scope="col">Tham chiếu / ghi chú</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($payments as $payment): ?>
                            <tr>
                                <td><?= e($formatDate($payment['created_at'], true)) ?></td>
                                <td><?= $payment['kind'] === 'payment' ? 'Thu tiền' : 'Hoàn tiền' ?></td>
                                <td><?= e($paymentMethods[$payment['method']] ?? $payment['method']) ?></td>
                                <td class="amount"><?= money($payment['amount']) ?></td>
                                <td class="invoice-reference"><?= e($payment['reference'] ?: '—') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <div class="actions">
        <button type="button" class="button" onclick="window.print()">
            IN / LƯU PDF
        </button>
        <a class="button outline"
           href="<?= e(url('reception/booking.php?id=' . $id)) ?>">
            QUAY LẠI ĐƠN
        </a>
    </div>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>