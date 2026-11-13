<?php
$title = $title ?? 'Một chốn riêng, một dấu ấn';

// Thêm thời gian sửa file vào URL để trình duyệt tải đúng bản CSS và JS.
$styleUrl = url('assets/style.css') . '?v=' . filemtime(__DIR__ . '/../assets/style.css');
$scriptUrl = url('assets/app.js') . '?v=' . filemtime(__DIR__ . '/../assets/app.js');

$headerStaff = actor();
$headerCustomer = null;

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
        || (int) $headerCustomer['session_version'] !== (int) ($_SESSION['customer_version'] ?? 0)
    ) {
        unset($_SESSION['customer_id'], $_SESSION['customer_version']);
        $headerCustomer = null;
    }
}
?>
<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta
        name="description"
        content="The Onyx &amp; Ivory — khách sạn boutique. Khám phá phòng nghỉ, kiểm tra phòng trống và đặt phòng trực tiếp."
    >

    <title><?= e($title) ?> | The Onyx &amp; Ivory</title>

    <link rel="icon" href="<?= e(url('assets/favicon.svg')) ?>" type="image/svg+xml">
    <link rel="stylesheet" href="<?= e($styleUrl) ?>">
    <script src="<?= e($scriptUrl) ?>" defer></script>

    <style>
        .site-header #main-nav {
            gap: 18px;
        }

        .site-header #main-nav > a {
            white-space: nowrap;
        }

        .site-header .nav-user {
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
        }

        @media (max-width: 1150px) {
            .site-header {
                height: 78px;
            }

            .site-header .menu-toggle {
                display: block;
                padding: 6px 12px;
                border: 1px solid var(--line);
                background: transparent;
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
                background: var(--ivory);
                box-shadow: 0 15px 20px #0001;
            }

            .site-header #main-nav.open {
                display: flex;
                flex-direction: column;
                align-items: stretch;
                gap: 20px;
            }
        }
    </style>
</head>

<body>
<div class="topline">
    HÀ NỘI, VIỆT NAM
    <span>MỘT KHÁCH SẠN. MỘT DẤU ẤN RIÊNG.</span>
</div>

<header class="site-header">
    <a class="brand" href="<?= e(url('index.php')) ?>">
        <span class="brand-symbol">O<span>&amp;</span>I</span>
        <span>
            THE ONYX &amp; IVORY
            <small>BOUTIQUE HOTEL · HANOI</small>
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

    <nav id="main-nav">
        <a href="<?= e(url('index.php#about')) ?>">Câu chuyện</a>
        <a href="<?= e(url('rooms.php')) ?>">Phòng &amp; suite</a>
        <a href="<?= e(url('index.php#experiences')) ?>">Tiện nghi</a>
        <a href="<?= e(url('lookup.php')) ?>">Tra cứu đơn</a>

        <?php if ($headerStaff): ?>
            <a href="<?= e(url(staff_home($headerStaff))) ?>">
                <?= $headerStaff['role'] === 'admin' ? 'Trang quản trị' : 'Khu vực lễ tân' ?>
            </a>
        <?php elseif ($headerCustomer): ?>
            <a
                class="nav-user"
                href="<?= e(url('account.php')) ?>"
                title="Xem thông tin cá nhân và lịch sử đặt phòng"
            >
                Xin chào, <?= e($headerCustomer['full_name']) ?>
            </a>
        <?php else: ?>
            <a href="<?= e(url('login.php')) ?>">Đăng nhập</a>
            <a href="<?= e(url('register.php')) ?>">Đăng ký</a>
        <?php endif; ?>

        <?php if ($headerStaff || $headerCustomer): ?>
            <form
                action="<?= e(url('logout.php')) ?>"
                method="post"
                class="nav-logout"
            >
                <?= csrf() ?>
                <button type="submit" class="nav-logout-button">Đăng xuất</button>
            </form>
        <?php endif; ?>

        <a class="button" href="<?= e(url('rooms.php')) ?>">ĐẶT PHÒNG ↗</a>
    </nav>
</header>

<?php if (!empty($_SESSION['flash'])): ?>
    <div id="flash-message" class="notice success" role="status">
        <?= e($_SESSION['flash']) ?>
    </div>
    <?php unset($_SESSION['flash']); ?>

    <script>
        (() => {
            const message = document.getElementById('flash-message');
            if (!message) return;

            setTimeout(() => {
                message.style.transition = 'opacity 0.4s ease';
                message.style.opacity = '0';
                setTimeout(() => message.remove(), 400);
            }, 5000);
        })();
    </script>
<?php endif; ?>

<main>