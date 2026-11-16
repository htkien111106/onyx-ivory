<?php
$title = $title ?? 'Một chốn riêng, một dấu ấn';

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

$headerIsMemberPage = !$headerIsStaffPage && in_array(
    $uiPage,
    [
        'account',
        'membership',
        'change-password',
        'booking-detail',
        'booking-change',
        'booking-payment',
    ],
    true
);

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

$headerFlashMessage = '';

if (!empty($_SESSION['flash'])) {
    $headerFlashMessage = (string) $_SESSION['flash'];
    unset($_SESSION['flash']);
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

    <?php if ($headerIsMemberPage): ?>
        <link
            rel="stylesheet"
            href="<?= e($headerAssetUrl('assets/hotel-member.css')) ?>"
        >
    <?php endif; ?>

    <style>
        /* Thông báo nổi */
        #hotel-toast {
            position: fixed;
            inset: auto 24px 24px auto;
            z-index: 3000;

            display: grid;
            grid-template-columns: 34px minmax(0, 1fr) 28px;
            align-items: start;
            gap: 12px;

            width: min(420px, calc(100vw - 32px));
            max-height: calc(100dvh - 48px);
            overflow-y: auto;
            margin: 0;
            padding: 20px;

            border: 1px solid #dfd3bd;
            border-left: 3px solid #b28b46;
            border-radius: 6px;
            background: #fffdf8;
            color: #443c31;
            box-shadow: 0 12px 36px rgb(0 0 0 / 18%);

            font-family: Arial, sans-serif;
            text-align: left;

            animation: hotel-toast-enter 350ms ease-out;
            transition: opacity 200ms ease, translate 200ms ease;
        }

        #hotel-toast,
        #hotel-toast * {
            box-sizing: border-box;
            font-style: normal;
        }

        #hotel-toast .hotel-toast-icon {
            display: grid;
            place-items: center;
            width: 34px;
            height: 34px;
            border: 1px solid #dfd3bd;
            border-radius: 50%;
            background: #f4eee2;
            color: #97723b;
        }

        #hotel-toast .hotel-toast-icon svg {
            display: block;
            width: 18px;
            height: 18px;
        }

        #hotel-toast .hotel-toast-title {
            display: block;
            margin: 0 0 5px;
            color: #51412c;
            font-family: Arial, sans-serif;
            font-size: 11px;
            font-weight: 600;
            line-height: 1.6;
            letter-spacing: 1.5px;
        }

        #hotel-toast .hotel-toast-message {
            margin: 0;
            padding: 0;
            color: #645b50;
            font-family: Arial, sans-serif;
            font-size: 14px;
            font-weight: 400;
            line-height: 1.7;
            text-align: left;
            overflow-wrap: anywhere;
        }

        #hotel-toast .hotel-toast-close {
            position: static;
            display: grid;
            place-items: center;

            width: 28px;
            height: 28px;
            min-width: 0;
            min-height: 0;
            margin: -4px -4px 0 0;
            padding: 0;

            border: 0;
            border-radius: 50%;
            background: transparent;
            color: #8b7d68;
            box-shadow: none;

            font-family: Arial, sans-serif;
            font-size: 23px;
            font-weight: 400;
            line-height: 1;
            cursor: pointer;
        }

        #hotel-toast .hotel-toast-close:hover {
            background: #f0e9dd;
            color: #443c31;
        }

        #hotel-toast .hotel-toast-close:focus-visible {
            outline: 2px solid #a48249;
            outline-offset: 3px;
        }

        #hotel-toast.is-closing {
            opacity: 0;
            translate: 0 12px;
            pointer-events: none;
        }

        @keyframes hotel-toast-enter {
            from {
                opacity: 0;
                translate: 0 16px;
            }

            to {
                opacity: 1;
                translate: 0 0;
            }
        }

        @media (max-width: 600px) {
            #hotel-toast {
                right: 16px;
                bottom: calc(16px + env(safe-area-inset-bottom));
                padding: 16px;
                gap: 10px;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            #hotel-toast {
                animation: none;
                transition: none;
            }
        }
    </style>
</head>

<body
    class="site-ui page-<?= e($uiPage) ?><?= $headerIsStaffPage ? ' staff-page' : '' ?><?= $headerIsMemberPage ? ' member-ui' : '' ?>"
    <?php if (isset($workspaceMode)): ?>
        data-ui-workspace="<?= e($headerWorkspace) ?>"
    <?php endif; ?>
>
<a class="skip-link" href="#main-content">
    Đến nội dung chính
</a>

<div class="topline">
    HÀ NỘI, VIỆT NAM
    <span>MỘT KHÁCH SẠN. MỘT DẤU ẤN RIÊNG.</span>
</div>

<header class="site-header">
    <a class="brand" href="<?= e(url('index.php')) ?>">
        <span class="brand-symbol">O<span>&amp;</span>I</span>

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
</header>

<?php if ($headerFlashMessage !== ''): ?>
    <?php if ($headerIsStaffPage): ?>
        <div
            id="flash-message"
            class="notice success"
            role="status"
        >
            <?= e($headerFlashMessage) ?>
        </div>

        <script>
        document.addEventListener('DOMContentLoaded', () => {
            const message = document.getElementById('flash-message');
            if (!message) return;

            window.setTimeout(() => {
                if (!message.isConnected) return;

                const reducedMotion = window.matchMedia(
                    '(prefers-reduced-motion: reduce)'
                ).matches;

                if (reducedMotion) {
                    message.remove();
                    return;
                }

                message.style.transition = 'opacity 400ms ease';
                message.style.opacity = '0';

                window.setTimeout(() => message.remove(), 400);
            }, 5000);
        }, { once: true });
        </script>

    <?php else: ?>
        <div id="hotel-toast">
            <span class="hotel-toast-icon" aria-hidden="true">
                <svg
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="1.8"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                >
                    <circle cx="12" cy="7" r="1"></circle>
                    <path d="M12 11v6"></path>
                </svg>
            </span>

            <div role="status" aria-live="polite" aria-atomic="true">
                <span class="hotel-toast-title">
                    THE ONYX &amp; IVORY
                </span>

                <p class="hotel-toast-message">
                    <?= e($headerFlashMessage) ?>
                </p>
            </div>

            <button
                type="button"
                class="hotel-toast-close"
                aria-label="Đóng thông báo"
            >
                <span aria-hidden="true">&times;</span>
            </button>
        </div>

        <script>
        (() => {
            const toast = document.getElementById('hotel-toast');
            if (!toast) return;

            const closeButton = toast.querySelector('.hotel-toast-close');
            const reducedMotion = window.matchMedia(
                '(prefers-reduced-motion: reduce)'
            );

            let timer;
            let remaining = 6000;
            let startedAt = 0;
            let hovering = false;
            let focused = false;
            let closing = false;

            function dismiss() {
                if (closing || !toast.isConnected) return;

                closing = true;
                window.clearTimeout(timer);

                document.removeEventListener(
                    'visibilitychange',
                    handleVisibility
                );

                if (toast.contains(document.activeElement)) {
                    document.getElementById('main-content')?.focus({
                        preventScroll: true
                    });
                }

                if (reducedMotion.matches) {
                    toast.remove();
                    return;
                }

                toast.classList.add('is-closing');
                window.setTimeout(() => toast.remove(), 220);
            }

            function pause() {
                if (timer === undefined) return;

                window.clearTimeout(timer);
                timer = undefined;

                remaining = Math.max(
                    0,
                    remaining - (performance.now() - startedAt)
                );
            }

            function resume() {
                if (
                    closing ||
                    hovering ||
                    focused ||
                    document.hidden ||
                    timer !== undefined
                ) {
                    return;
                }

                startedAt = performance.now();
                timer = window.setTimeout(dismiss, remaining);
            }

            function handleVisibility() {
                if (document.hidden) {
                    pause();
                } else {
                    resume();
                }
            }

            closeButton.addEventListener('click', dismiss);

            toast.addEventListener('mouseenter', () => {
                hovering = true;
                pause();
            });

            toast.addEventListener('mouseleave', () => {
                hovering = false;
                resume();
            });

            toast.addEventListener('focusin', () => {
                focused = true;
                pause();
            });

            toast.addEventListener('focusout', (event) => {
                if (toast.contains(event.relatedTarget)) return;

                focused = false;
                resume();
            });

            toast.addEventListener('keydown', (event) => {
                if (event.key === 'Escape') {
                    event.preventDefault();
                    dismiss();
                }
            });

            document.addEventListener(
                'visibilitychange',
                handleVisibility
            );

            resume();
        })();
        </script>
    <?php endif; ?>
<?php endif; ?>

<main id="main-content" tabindex="-1">