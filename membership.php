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

$remainingPoints = $next
    ? max(0, (int) $next['minimum'] - $points)
    : 0;

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

$tiers = loyalty_tiers();

require __DIR__ . '/includes/header.php';
?>

<style>
    /* Chỉ áp dụng cho trang thành viên */
    body.page-membership:not(.staff-page) {
        background: #faf8f3;
    }

    body.page-membership:not(.staff-page) main,
    body.page-membership:not(.staff-page) main * {
        box-sizing: border-box;
        font-style: normal !important;
    }

    body.page-membership .membership-heading {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 14px;
        padding: 44px 20px;
        border-bottom: 1px solid #e1d8c8;
        background: #f0ece3;
        text-align: center;
    }

    body.page-membership .membership-heading .eyebrow {
        margin: 0;
        color: #967444;
        font-size: 10px;
        letter-spacing: 2.5px;
    }

    body.page-membership .membership-heading h1 {
        margin: 0;
        color: #433c32;
        font-family: "Times New Roman", serif;
        font-size: clamp(34px, 4vw, 50px);
        font-weight: 400;
        line-height: 1.3;
    }

    body.page-membership .membership-heading p {
        max-width: 650px;
        margin: 0;
        color: #746b5e;
        font-size: 14px;
        line-height: 1.8;
        text-align: center;
    }

    body.page-membership main > .membership-content {
        width: min(900px, 92%);
        max-width: none;
        margin: 0 auto;
        padding: 32px 0 64px;
    }

    .membership-content .membership-back {
        margin: 0 0 24px;
    }

    .membership-content .text-link {
        display: inline-block;
        padding: 5px 0 8px;
        border-bottom: 1px solid #b39561;
        color: #806239;
        font-size: 13px;
        text-decoration: none;
    }

    .membership-content .text-link:hover {
        color: #45351f;
        border-bottom-color: #45351f;
    }

    .membership-content .panel {
        margin: 0;
        padding: 30px;
        border: 1px solid #e1d8c8;
        border-radius: 5px;
        background: #fffdf8;
        box-shadow: 0 8px 26px rgb(60 42 20 / 4%);
    }

    .membership-content > .panel + .panel {
        margin-top: 24px;
    }

    .membership-content h2 {
        margin: 0 0 16px;
        color: #433c32;
        font-family: "Times New Roman", serif;
        font-size: 29px;
        font-weight: 400;
        line-height: 1.35;
    }

    .membership-content p {
        color: #746b5e;
        font-size: 14px;
        line-height: 1.8;
    }

    /* Thẻ thành viên */
    body.page-membership .membership-content .member-card {
        padding: 36px;
        border: 1px solid #9a8056;
        border-radius: 8px;
        background: linear-gradient(125deg, #293d32, #17251f);
        color: #f8f1e4;
        box-shadow: 0 14px 32px rgb(23 37 31 / 16%);
    }

    .membership-content .member-card-top {
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 16px;
        margin-bottom: 32px;
    }

    .membership-content .member-card .eyebrow {
        margin: 0;
        color: #dfc38f;
        font-size: 10px;
        line-height: 1.8;
        letter-spacing: 2.5px;
    }

    .membership-content .member-tier {
        display: inline-block;
        padding: 7px 13px;
        border: 1px solid rgb(223 195 143 / 45%);
        border-radius: 2px;
        color: #eed4a0;
        font-size: 12px;
        line-height: 1.5;
    }

    body.page-membership .member-card h2 {
        margin: 0 0 26px;
        color: #fff8e9;
        font-size: clamp(28px, 4vw, 38px);
        overflow-wrap: anywhere;
    }

    .membership-content .member-points {
        display: flex;
        align-items: baseline;
        flex-wrap: wrap;
        gap: 10px;
        padding-bottom: 24px;
        border-bottom: 1px solid rgb(223 195 143 / 25%);
    }

    .membership-content .member-points strong {
        color: #eed4a0;
        font-family: "Times New Roman", serif;
        font-size: 46px;
        font-weight: 400;
        line-height: 1;
    }

    .membership-content .member-points span {
        color: #e0d8c8;
        font-size: 13px;
    }

    body.page-membership .member-card .member-next {
        margin: 18px 0 0;
        color: #ded8ca;
        font-size: 14px;
    }

    .membership-content .member-next strong {
        color: #eed4a0;
    }

    /* Bảng quyền lợi và lịch sử */
    .membership-content .table-wrap {
        max-width: 100%;
        margin-top: 22px;
        overflow-x: auto;
    }

    .membership-content table {
        width: 100%;
        min-width: 540px;
        border-collapse: collapse;
        font-size: 13px;
    }

    .membership-content th {
        padding: 14px 16px;
        border-bottom: 1px solid #ded2bf;
        background: #f0eadf;
        color: #735b36;
        font-weight: 600;
        text-align: left;
    }

    .membership-content td {
        padding: 16px;
        border-bottom: 1px solid #e8e0d3;
        color: #655c50;
        line-height: 1.7;
        vertical-align: top;
    }

    .membership-content tbody tr:last-child td {
        border-bottom: 0;
    }

    .membership-content td a {
        color: #846335;
        text-decoration: underline;
        text-underline-offset: 4px;
    }

    .membership-content .points-earned {
        color: #355b43;
        font-weight: 600;
        white-space: nowrap;
    }

    /* Quyền lợi đã được cấp */
    .membership-content .member-benefit {
        padding: 22px 0;
        border-bottom: 1px solid #e1d8c8;
    }

    .membership-content .member-benefit:last-child {
        padding-bottom: 0;
        border-bottom: 0;
    }

    .membership-content .member-benefit h3 {
        margin: 0 0 10px;
        color: #59472d;
        font-family: Arial, sans-serif;
        font-size: 15px;
        font-weight: 600;
        line-height: 1.6;
    }

    .membership-content .member-benefit p {
        margin: 8px 0;
    }

    .membership-content .member-empty {
        margin: 16px 0 0;
        padding: 18px 20px;
        border-left: 2px solid #b69864;
        background: #f4efe6;
        color: #746b5e;
    }

    .membership-content a:focus-visible,
    .membership-content .table-wrap:focus-visible {
        outline: 2px solid #a48249;
        outline-offset: 4px;
    }

    @media (max-width: 600px) {
        body.page-membership .membership-heading {
            padding: 32px 20px;
        }

        .membership-content .panel {
            padding: 24px 18px;
        }

        body.page-membership .membership-content .member-card {
            padding: 28px 22px;
        }

        .membership-content .member-card-top {
            margin-bottom: 26px;
        }

        .membership-content h2 {
            font-size: 26px;
        }

        .membership-content th,
        .membership-content td {
            padding: 13px 12px;
        }
    }
</style>

<section class="page-heading membership-heading">
    <div class="eyebrow">
        THE ONYX &amp; IVORY / THÀNH VIÊN
    </div>

    <h1>Thẻ thành viên</h1>

    <p>
        Theo dõi điểm tích lũy và khám phá quyền lợi dành cho bạn
    </p>
</section>

<section class="section narrow membership-content">
    <p class="membership-back">
        <a class="text-link" href="<?= e(url('account.php')) ?>">
            ← Quay lại tài khoản
        </a>
    </p>

    <div class="panel member-card">
        <div class="member-card-top">
            <div class="eyebrow">
                THE ONYX &amp; IVORY
            </div>

            <span class="member-tier">
                Hạng <?= e($current['name']) ?>
            </span>
        </div>

        <h2><?= e($customer['full_name']) ?></h2>

        <div class="member-points">
            <strong>
                <?= number_format($points, 0, ',', '.') ?>
            </strong>

            <span>điểm tích lũy</span>
        </div>

        <?php if ($next): ?>
            <p class="member-next">
                Còn
                <strong>
                    <?= number_format($remainingPoints, 0, ',', '.') ?> điểm
                </strong>
                để lên hạng
                <strong><?= e($next['name']) ?></strong>
            </p>
        <?php else: ?>
            <p class="member-next">
                Bạn đang ở hạng cao nhất của chương trình
            </p>
        <?php endif; ?>
    </div>

    <div class="panel">
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
                        <th scope="col">Hạng thành viên</th>
                        <th scope="col">Từ mức điểm</th>
                        <th scope="col">Quyền lợi mỗi kỳ nghỉ</th>
                    </tr>
                </thead>

                <tbody>
                    <?php foreach ($tiers as $tier): ?>
                        <tr>
                            <td><?= e($tier['name']) ?></td>

                            <td>
                                <?= number_format(
                                    (int) $tier['minimum'],
                                    0,
                                    ',',
                                    '.'
                                ) ?>
                            </td>

                            <td>
                                <?= e(
                                    $tier['benefit']
                                    ?: 'Tích điểm qua các kỳ nghỉ'
                                ) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="panel">
        <h2>Quyền lợi của bạn</h2>

        <?php if (!$benefits): ?>
            <p class="member-empty">
                Bạn chưa có quyền lợi được cấp cho kỳ nghỉ nào
            </p>
        <?php else: ?>
            <?php foreach ($benefits as $benefit): ?>
                <article class="member-benefit">
                    <h3><?= e($benefit['benefit_name']) ?></h3>

                    <p>
                        <?= e($benefit['room_name']) ?>
                        · Hạng <?= e($benefit['tier_name']) ?>
                        khi nhận phòng
                    </p>

                    <p>
                        <?= e(loyalty_benefit_status($benefit)) ?>

                        <?php if ($benefit['delivered_at']): ?>
                            ·
                            <?= e(date(
                                'd/m/Y H:i',
                                strtotime($benefit['delivered_at'])
                            )) ?>
                        <?php endif; ?>
                    </p>

                    <a
                        class="text-link"
                        href="<?= e(url(
                            'booking-detail.php?id='
                            . (int) $benefit['booking_id']
                        )) ?>"
                    >
                        Xem đơn #<?= (int) $benefit['booking_id'] ?> ↗
                    </a>
                </article>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div class="panel">
        <h2>Lịch sử tích điểm</h2>

        <?php if (!$history): ?>
            <p class="member-empty">
                Khi bạn hoàn tất kỳ nghỉ đầu tiên bằng tài khoản này,
                điểm sẽ xuất hiện tại đây
            </p>
        <?php else: ?>
            <p>
                Hiển thị 20 kỳ nghỉ gần nhất.
                Tổng điểm gồm tất cả kỳ nghỉ đủ điều kiện.
            </p>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th scope="col">Kỳ nghỉ</th>
                            <th scope="col">Ngày trả phòng</th>
                            <th scope="col">Tiền phòng</th>
                            <th scope="col">Điểm</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php foreach ($history as $stay): ?>
                            <tr>
                                <td>
                                    <a
                                        href="<?= e(url(
                                            'booking-detail.php?id='
                                            . (int) $stay['id']
                                        )) ?>"
                                    >
                                        <?= e($stay['room_name']) ?>
                                    </a>
                                </td>

                                <td>
                                    <?= e(date(
                                        'd/m/Y',
                                        strtotime(
                                            $stay['actual_check_out']
                                            ?: $stay['check_out']
                                        )
                                    )) ?>
                                </td>

                                <td>
                                    <?= money($stay['total_price']) ?>
                                </td>

                                <td class="points-earned">
                                    +<?= (int) $stay['earned_points'] ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>