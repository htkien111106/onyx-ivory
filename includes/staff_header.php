<?php
require_once __DIR__ . '/admin_catalog.php';
$workspaceScript = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
$workspaceStaffView = str_contains($workspaceScript, '/reception/')
    || preg_match('~/staff/(customers|customer-detail)\.php$~', $workspaceScript)
    || input('workspace') === 'staff';
$workspaceMode = $user['role'] === 'admin' && !$workspaceStaffView ? 'admin' : 'staff';
$workspaceItems = $workspaceMode === 'admin' ? [
    ['admin/index.php','Tổng quan & báo cáo'],
    ['admin/members.php','Thành viên thân thiết'],
    ['admin/reviews.php','Đánh giá sau lưu trú'],
    ['admin/types.php','Loại phòng & giá'],
    ['admin/promotions.php','Khuyến mãi & mã giảm giá'],
    ['admin/services.php','Danh mục dịch vụ'],
    ['admin/rooms.php','Danh mục phòng'],
    ['admin/users.php','Nhân viên & phân quyền'],
    ['admin/audit.php','Nhật ký thao tác'],
] : [
    ['reception/index.php','Đơn đặt phòng'],
    ['reception/transfers.php','Đối chiếu chuyển khoản'],
    ['reception/change-requests.php','Yêu cầu đổi đặt phòng'],
    ['reception/new.php','Tạo đơn tại quầy'],
    ['reception/rooms.php','Sơ đồ phòng'],
    ['reception/calendar.php','Lịch phòng'],
    ['staff/customers.php','Khách hàng'],
];
$workspaceCounters = [];
if ($workspaceMode === 'admin') {
    $workspaceCounters = query('SELECT
        (SELECT COUNT(*) FROM room_types) AS types_total,
        (SELECT COUNT(*) FROM promotions) AS promotions_total,
        (SELECT COUNT(*) FROM services) AS services_total,
        (SELECT COUNT(*) FROM hotel_rooms) AS rooms_total,
        (SELECT COUNT(*) FROM admins) AS users_total')->fetch();
}
$workspaceNavMeta = [
    'admin/index.php'=>['report',null],
    'admin/members.php'=>['members',null],
    'admin/reviews.php'=>['reviews',null],
    'admin/types.php'=>['types','types_total'],
    'admin/promotions.php'=>['promotions','promotions_total'],
    'admin/services.php'=>['services','services_total'],
    'admin/rooms.php'=>['rooms','rooms_total'],
    'admin/users.php'=>['employees','users_total'],
    'admin/audit.php'=>['audit',null],
];
require __DIR__ . '/header.php';
?>
<section class="workspace-shell" data-workspace-mode="<?= e($workspaceMode) ?>">
<?php if ($workspaceMode === 'admin'): ?>
    <aside class="workspace-nav admin-sidebar">
        <button class="admin-nav-close" type="button" data-admin-menu-close><?= catalog_icon('close') ?><span>Đóng danh mục</span></button>
        <div class="admin-profile"><span class="admin-avatar" aria-hidden="true">AD</span><div><h3><?= e($user['display_name']) ?></h3><p>Quản trị viên</p></div></div>
        <div class="admin-nav-label">QUẢN TRỊ KHÁCH SẠN</div>
        <nav class="workspace-links" id="workspace-menu" aria-label="Danh mục quản trị">
            <?php foreach ($workspaceItems as [$workspacePath,$workspaceLabel]): [$navIcon,$counterKey] = $workspaceNavMeta[$workspacePath]; ?>
                <a href="<?= e(url($workspacePath)) ?>"><?= catalog_icon($navIcon) ?><span class="admin-nav-text"><?= e($workspaceLabel) ?></span><?php if ($counterKey !== null): ?><span class="admin-nav-count"><?= (int)$workspaceCounters[$counterKey] ?></span><?php endif; ?></a>
            <?php endforeach; ?>
        </nav>
        <div class="admin-sidebar-bottom">
            <a class="workspace-context-switch" href="<?= e(url('reception/index.php')) ?>"><span>Chuyển sang Staff</span><?= catalog_icon('arrow') ?></a>
            <a class="admin-account-link" href="<?= e(url('staff/account.php')) ?>" <?= str_ends_with($workspaceScript,'/staff/account.php')?'aria-current="page"':'' ?>><?= catalog_icon('account') ?><span>Tài khoản của tôi</span></a>
            <form method="post" action="<?= e(url('staff/logout.php')) ?>"><?= csrf() ?><button class="admin-logout" type="submit"><?= catalog_icon('logout') ?><span>Đăng xuất</span></button></form>
        </div>
    </aside>
<?php else: ?>
    <aside class="workspace-nav staff-sidebar">
        <div class="staff-profile">
            <span class="staff-avatar" aria-hidden="true"><?= e(mb_strtoupper(mb_substr(trim($user['display_name']), 0, 1))) ?></span>
            <div><h3><?= e($user['display_name']) ?></h3><p><?= $user['role'] === 'admin' ? 'Quản trị viên / Lễ tân' : 'Staff / Lễ tân' ?></p></div>
        </div>
        <div class="staff-nav-label">NGHIỆP VỤ LỄ TÂN</div>
        <button type="button" class="workspace-menu-toggle" aria-expanded="false" aria-controls="workspace-menu">Danh mục <?= catalog_icon('menu') ?></button>
        <nav class="workspace-links" id="workspace-menu" aria-label="Điều hướng nghiệp vụ">
            <?php
            $staffIcons = ['report','audit','reviews','plus','rooms','types','members'];
            foreach ($workspaceItems as $staffIndex => [$workspacePath,$workspaceLabel]):
                $staffActive = str_ends_with($workspaceScript, '/' . $workspacePath);
            ?>
                <a href="<?= e(url($workspacePath)) ?>" <?= $staffActive ? 'aria-current="page"' : '' ?>><?= catalog_icon($staffIcons[$staffIndex] ?? 'report') ?><span><?= e($workspaceLabel) ?></span></a>
            <?php endforeach; ?>
        </nav>
        <div class="staff-sidebar-bottom">
            <?php if ($user['role'] === 'admin'): ?>
                <a class="workspace-context-switch" href="<?= e(url('admin/index.php')) ?>"><span>Về trang Admin</span><?= catalog_icon('arrow') ?></a>
            <?php endif; ?>
            <a class="staff-account-link" href="<?= e(url('staff/account.php'.($user['role'] === 'admin' ? '?workspace=staff' : ''))) ?>" <?= str_ends_with($workspaceScript, '/staff/account.php') ? 'aria-current="page"' : '' ?>><?= catalog_icon('account') ?><span>Tài khoản của tôi</span></a>
            <form method="post" action="<?= e(url('staff/logout.php')) ?>"><?= csrf() ?><button type="submit" class="staff-logout"><?= catalog_icon('logout') ?><span>Đăng xuất</span></button></form>
        </div>
    </aside>
<?php endif; ?>
    <div class="workspace-content">
<?php if ($workspaceMode === 'admin'): ?>
        <div class="admin-page-heading"><div><h1><?= e($title) ?></h1><div class="admin-breadcrumb">Quản trị <span aria-hidden="true">/</span> <?= e($title) ?></div></div><button type="button" class="admin-menu-toggle" data-admin-menu-toggle aria-label="Mở danh mục" aria-controls="workspace-menu" aria-expanded="false"><?= catalog_icon('menu') ?></button></div>
<?php else: ?>
        <div class="eyebrow">THE ONYX &amp; IVORY / <?= $workspaceMode === 'admin' ? 'QUẢN TRỊ' : 'STAFF' ?></div>
        <h1><?= e($title) ?></h1>
<?php endif; ?>
        <?php if (!empty($error)): ?><div class="notice error" role="alert"><?= e($error) ?></div><?php endif; ?>
