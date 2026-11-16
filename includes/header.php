<?php
$title = $title ?? 'Một chốn riêng, một dấu ấn';

// Tự cập nhật phiên bản CSS/JS khi nội dung file thay đổi.
$headerAssetUrl = static function (string $path): string {
    $file = __DIR__ . '/../' . $path;

    return url($path)
        . (is_file($file) ? '?v=' . filemtime($file) : '');
};

$uiPage = basename(
    $_SERVER['SCRIPT_NAME'] ?? 'index.php',
    '.php'
);

$uiPage = preg_replace('/[^a-z0-9-]/i', '-', $uiPage);

$headerWorkspace = $workspaceMode ?? '';
$headerIsAdmin = $headerWorkspace === 'admin';
$headerIsStaffPage = !empty($isAdminPath);

$headerIsHome = $uiPage === 'index' && !$headerIsStaffPage;
$headerIsRooms = $uiPage === 'rooms' && !$headerIsStaffPage;

// Kiểm tra phiên đăng nhập nhân viên.
$headerStaff = actor();
$headerCustomer = null;

// Kiểm tra phiên đăng nhập khách hàng.
if (!$headerStaff && !empty($_SESSION['customer_id'])) {
    $headerCustomer = query(
        'SELECT id, full_name, active, session_version
         FROM customers
         WHERE id = ?
         LIMIT 1',
        [$_SESSION['customer_id']]
    )->fetch();

    if (
        !$headerCustomer
        || (int) $headerCustomer['active'] !== 1
        || (int) $headerCustomer['session_version']
            !== (int) ($_SESSION['customer_version'] ?? 0)
    ) {
        unset(
            $_SESSION['customer_id'],
            $_SESSION['customer_version']
        );

        $headerCustomer = null;
    }
}
?>
<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <meta
        name="description"
        content="The Onyx &amp; Ivory — khách sạn boutique. Khám phá phòng nghỉ, kiểm tra phòng trống và đặt phòng trực tiếp."
    >

    <title><?= e($title) ?> | The Onyx &amp; Ivory</title>

    <link
        rel="icon"
        href="<?= e(url('assets/favicon.svg')) ?>"
        type="image/svg+xml"
    >

    <link
        rel="stylesheet"
        href="<?= e($headerAssetUrl('assets/style.css')) ?>"
    >

    <style>
        .skip-link {
            position: fixed;
            top: 12px;
            left: 12px;
            z-index: 10000;
            padding: 12px 18px;
            border-radius: 8px;
            background: #24342b;
            color: #fffaf0;
            transform: translateY(-200%);
        }

        .skip-link:focus {
            transform: translateY(0);
        }

        .site-header #main-nav {
            gap: 18px;
        }

        .site-header #main-nav > a {
            white-space: nowrap;
        }

        .site-header .nav-user {
            max-width: 200px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            font-size: 14px;
        }

        .site-header #main-nav .nav-logout {
            display: flex;
            align-items: center;
            margin: 0;
            padding: 0;
            border: 0;
            background: transparent;
            box-shadow: none;
        }

        .site-header .nav-logout-button {
            width: auto;
            margin: 0;
            padding: 0;
            border: 0;
            background: transparent;
            color: inherit;
            font: inherit;
            font-size: 14px;
            letter-spacing: normal;
            text-transform: none;
            white-space: nowrap;
            cursor: pointer;
        }

        .site-header .nav-logout-button:hover {
            text-decoration: underline;
            text-underline-offset: 4px;
        }

        @media (max-width: 1150px) {
            .site-header {
                height: 78px;
            }

            .site-header .menu-toggle {
                display: block;
                padding: 6px 12px;
                border: 1px solid var(--line, #ded8cc);
                background: transparent;
                color: inherit;
                font-size: 20px;
                cursor: pointer;
            }

            .site-header #main-nav {
                display: none;
                position: absolute;
                top: 78px;
                left: 0;
                right: 0;
                padding: 25px;
                background: var(--ivory, #f6f3ed);
                box-shadow: 0 15px 20px #0001;
            }

            .site-header #main-nav.open {
                display: flex;
                flex-direction: column;
                align-items: stretch;
                gap: 20px;
            }

            .site-header .nav-user {
                max-width: 100%;
            }
        }
    </style>

    <link
        rel="stylesheet"
        href="<?= e($headerAssetUrl('assets/usability.css')) ?>"
    >

    <script
        src="<?= e($headerAssetUrl('assets/app.js')) ?>"
        defer
    ></script>

    <script
        src="<?= e($headerAssetUrl('assets/usability.js')) ?>"
        defer
    ></script>

    <?php if (isset($workspaceMode)): ?>
        <link
            rel="stylesheet"
            href="<?= e($headerAssetUrl('assets/admin-catalog.css')) ?>"
        >

        <script
            src="<?= e($headerAssetUrl('assets/admin-catalog.js')) ?>"
            defer
        ></script>

        <?php if ($headerIsAdmin): ?>
            <link
                rel="stylesheet"
                href="<?= e($headerAssetUrl('assets/admin-approved.css')) ?>"
            >
        <?php endif; ?>
    <?php endif; ?>

    <?php if ($headerIsHome): ?>
        <link
            rel="stylesheet"
            href="<?= e($headerAssetUrl('assets/hotel-home.css')) ?>"
        >

        <script
            src="<?= e($headerAssetUrl('assets/hotel-home.js')) ?>"
            defer
        ></script>
    <?php endif; ?>

    <?php if ($headerIsRooms): ?>
        <link
            rel="stylesheet"
            href="<?= e($headerAssetUrl('assets/hotel-rooms.css')) ?>"
        >
    <?php endif; ?>
</head>

<body
    class="site-ui page-<?= e($uiPage) ?><?= $headerIsStaffPage ? ' staff-page' : '' ?>"
    <?php if (isset($workspaceMode)): ?>
        data-ui-workspace="<?= e($headerWorkspace) ?>"
    <?php endif; ?>
>
<a class="skip-link" href="#main-content">
    Đến nội dung chính
</a>

<div class="topline">
    HÀ NỘI, VIỆT NAM

    <span>
        MỘT KHÁCH SẠN. MỘT DẤU ẤN RIÊNG.
    </span>
</div>

<header class="site-header">
    <a class="brand" href="<?= e(url('index.php')) ?>">
        <span class="brand-symbol">
            O<span>&amp;</span>I
        </span>

        <span>
            THE ONYX &amp; IVORY

            <small>
                <?= $headerIsAdmin
                    ? 'HOTEL MANAGEMENT'
                    : 'BOUTIQUE HOTEL · HANOI' ?>
            </small>
        </span>
    </a>

    <button
        type="button"
        class="menu-toggle"
        aria-label="Mở menu"
        aria-expanded="false"
        aria-controls="main-nav"
    >
        ☰
    </button>

    <?php if ($headerIsAdmin): ?>
        <span class="admin-header-label">
            Quản trị khách sạn
        </span>
    <?php endif; ?>

    <nav id="main-nav" aria-label="Điều hướng chính">
        <a href="<?= e(url('index.php#about')) ?>">
            Câu chuyện
        </a>

        <a href="<?= e(url('rooms.php')) ?>">
            Phòng &amp; suite
        </a>

        <a href="<?= e(url('index.php#experiences')) ?>">
            Tiện nghi
        </a>

        <a href="<?= e(url('lookup.php')) ?>">
            Tra cứu đơn
        </a>

        <?php if ($headerStaff): ?>
            <a href="<?= e(url(staff_home($headerStaff))) ?>">
                <?= $headerStaff['role'] === 'admin'
                    ? 'Trang quản trị'
                    : 'Khu vực lễ tân' ?>
            </a>

        <?php elseif ($headerCustomer): ?>
            <a
                class="nav-user"
                href="<?= e(url('account.php')) ?>"
                title="<?= e(
                    'Xem tài khoản của ' . $headerCustomer['full_name']
                ) ?>"
            >
                Xin chào, <?= e($headerCustomer['full_name']) ?>
            </a>

        <?php else: ?>
            <a href="<?= e(url('login.php')) ?>">
                Đăng nhập
            </a>

            <a href="<?= e(url('register.php')) ?>">
                Đăng ký
            </a>
        <?php endif; ?>

        <?php if ($headerStaff || $headerCustomer): ?>
            <form
                action="<?= e(url('logout.php')) ?>"
                method="post"
                class="nav-logout"
            >
                <?= csrf() ?>

                <button
                    type="submit"
                    class="nav-logout-button"
                >
                    Đăng xuất
                </button>
            </form>
        <?php endif; ?>
    </nav>

    <?php if ($uiPage === 'room' && !$headerIsStaffPage): ?>
    <link
        rel="stylesheet"
        href="<?= e($headerAssetUrl('assets/hotel-room-detail.css')) ?>"
    >

    <script
        src="<?= e($headerAssetUrl('assets/hotel-room-detail.js')) ?>"
        defer
    ></script>
    <?php endif; ?>

    <?php if ($uiPage === 'booking' && !$headerIsStaffPage): ?>
    <link
        rel="stylesheet"
        href="<?= e($headerAssetUrl('assets/hotel-booking.css')) ?>"
    >
    <?php endif; ?>
    
    <?php if (
    !$headerIsStaffPage
    && in_array($uiPage, ['lookup', 'login', 'register'], true)
): ?>
    <link
        rel="stylesheet"
        href="<?= e($headerAssetUrl('assets/hotel-customer-pages.css')) ?>"
    >
<?php endif; ?>
</header>

<?php if (!empty($_SESSION['flash'])): ?>
    <div
        id="flash-message"
        class="notice success"
        role="status"
    >
        <?= e($_SESSION['flash']) ?>
    </div>

    <?php unset($_SESSION['flash']); ?>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const message = document.getElementById('flash-message');

            if (!message) return;

            setTimeout(() => {
                if (!message.isConnected) return;

                const reducedMotion = window.matchMedia(
                    '(prefers-reduced-motion: reduce)'
                ).matches;

                if (reducedMotion) {
                    message.remove();
                    return;
                }

                message.style.transition = 'opacity 0.4s ease';
                message.style.opacity = '0';

                setTimeout(() => {
                    message.remove();
                }, 400);
            }, 5000);
        }, { once: true });
    </script>
<?php endif; ?>

<main id="main-content" tabindex="-1">