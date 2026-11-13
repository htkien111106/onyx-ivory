<?php require __DIR__ . '/header.php'; ?>

<section class="workspace-shell">
    <aside class="workspace-nav">
        <div class="eyebrow">WORKSPACE</div>

        <h3><?= e($user['display_name']) ?></h3>

        <p>
            <?= $user['role'] === 'admin'
                ? 'Quản trị viên'
                : 'Lễ tân' ?>
        </p>

        <nav class="workspace-links">
            <a href="<?= e(url('reception/index.php')) ?>">
                Đơn đặt phòng
            </a>

            <a href="<?= e(url('reception/transfers.php')) ?>">
                Đối chiếu chuyển khoản
            </a>

            <a href="<?= e(url('reception/change-requests.php')) ?>">
                Yêu cầu đổi đặt phòng
            </a>

            <a href="<?= e(url('reception/new.php')) ?>">
                Tạo đơn tại quầy
            </a>

            <a href="<?= e(url('reception/rooms.php')) ?>">
                Sơ đồ phòng
            </a>

            <a href="<?= e(url('reception/calendar.php')) ?>">
                Lịch phòng
            </a>

            <a href="<?= e(url('staff/customers.php')) ?>">
                Khách hàng
            </a>

            <?php if ($user['role'] === 'admin'): ?>
                <div class="nav-divider"></div>

                <a href="<?= e(url('admin/index.php')) ?>">
                    Tổng quan &amp; báo cáo
                </a>

                <a href="<?= e(url('admin/members.php')) ?>">
                    Thành viên thân thiết
                </a>

                <a href="<?= e(url('admin/reviews.php')) ?>">
                    Đánh giá sau lưu trú
                </a>

                <a href="<?= e(url('admin/types.php')) ?>">
                    Loại phòng &amp; giá
                </a>

                <a href="<?= e(url('admin/promotions.php')) ?>">
                    Khuyến mãi &amp; mã giảm giá
                </a>

                <a href="<?= e(url('admin/services.php')) ?>">
                    Danh mục dịch vụ
                </a>

                <a href="<?= e(url('admin/rooms.php')) ?>">
                    Danh mục phòng
                </a>

                <a href="<?= e(url('admin/users.php')) ?>">
                    Nhân viên &amp; phân quyền
                </a>

                <a href="<?= e(url('admin/audit.php')) ?>">
                    Nhật ký thao tác
                </a>
            <?php endif; ?>

            <div class="nav-divider"></div>

            <a href="<?= e(url('staff/account.php')) ?>">
                Tài khoản của tôi
            </a>
        </nav>

        <form
            method="post"
            action="<?= e(url('staff/logout.php')) ?>"
        >
            <?= csrf() ?>

            <button type="submit" class="button outline full">
                ĐĂNG XUẤT
            </button>
        </form>
    </aside>

    <div class="workspace-content">
        <div class="eyebrow">
            THE ONYX &amp; IVORY /
            <?= $user['role'] === 'admin'
                ? 'QUẢN TRỊ VIÊN'
                : 'LỄ TÂN' ?>
        </div>

        <h1><?= e($title) ?></h1>

        <?php if (!empty($error)): ?>
            <div class="notice error" role="alert">
                <?= e($error) ?>
            </div>
        <?php endif; ?>