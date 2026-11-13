<?php
require __DIR__ . '/includes/bootstrap.php';

$title = 'Đăng nhập';
$error = '';
$email = '';

// Nhân viên đã đăng nhập sẽ được chuyển đến khu vực tương ứng.
if ($logged = actor()) {
    go(staff_home($logged));
}

if (post()) {
    check_csrf();

    $email = strtolower(input('email'));

    // Không trim mật khẩu để giữ nguyên ký tự người dùng nhập.
    $password = isset($_POST['password']) && is_string($_POST['password'])
        ? $_POST['password']
        : '';

    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) {
        $error = 'Vui lòng nhập địa chỉ email hợp lệ.';
    } elseif ($password === '') {
        $error = 'Vui lòng nhập mật khẩu.';
    } else {
        // Giới hạn số lần đăng nhập sai trong 15 phút.
        query(
            'DELETE FROM login_attempts
             WHERE attempted_at < DATE_SUB(NOW(), INTERVAL 15 MINUTE)'
        );

        $attempts = (int) query(
            'SELECT COUNT(*) FROM login_attempts WHERE ip = ?',
            [$ip]
        )->fetchColumn();

        if ($attempts >= 10) {
            $error = 'Bạn đã đăng nhập sai quá nhiều lần. Vui lòng thử lại sau 15 phút.';
        } else {
            $staff = query(
                'SELECT * FROM admins WHERE email = ? LIMIT 1',
                [$email]
            )->fetch();

            $customer = query(
                'SELECT * FROM customers WHERE email = ? LIMIT 1',
                [$email]
            )->fetch();

            $user = null;
            $accountType = null;

            // Mỗi email chỉ nên thuộc một tài khoản trong hệ thống.
            // Không tự chọn quyền nếu email tồn tại ở cả hai bảng.
            if ($staff && $customer) {
                $error = 'Email này đang bị trùng giữa các tài khoản. Vui lòng liên hệ quản trị viên.';
            } elseif ($staff) {
                $user = $staff;
                $accountType = 'staff';
            } elseif ($customer) {
                $user = $customer;
                $accountType = 'customer';
            }

            $validLogin = $user
                && (int) $user['active'] === 1
                && password_verify($password, $user['password_hash']);

            // Vai trò nhân viên phải được lấy từ cơ sở dữ liệu.
            if (
                $validLogin
                && $accountType === 'staff'
                && !in_array($user['role'], ['admin', 'receptionist'], true)
            ) {
                $validLogin = false;
            }

            if ($validLogin) {
                query(
                    'DELETE FROM login_attempts WHERE ip = ?',
                    [$ip]
                );

                // Đổi mã phiên sau khi đăng nhập.
                session_regenerate_id(true);

                // Xóa thông tin đăng nhập cũ để tránh lẫn các vai trò.
                unset(
                    $_SESSION['staff_id'],
                    $_SESSION['staff_version'],
                    $_SESSION['customer_id'],
                    $_SESSION['customer_version'],
                    $_SESSION['csrf']
                );

                if ($accountType === 'staff') {
                    $_SESSION['staff_id'] = (int) $user['id'];
                    $_SESSION['staff_version'] = (int) $user['session_version'];

                    go(staff_home($user));
                }

                $_SESSION['customer_id'] = (int) $user['id'];
                $_SESSION['customer_version'] = (int) $user['session_version'];

                flash(
                    'Đăng nhập thành công. Chào mừng '
                    . $user['full_name']
                    . ' trở lại The Onyx & Ivory!'
                );

                go('index.php');
            }

            query(
                'INSERT INTO login_attempts (ip) VALUES (?)',
                [$ip]
            );

            if ($error === '') {
                $error = 'Email hoặc mật khẩu không đúng, hoặc tài khoản đã bị khóa.';
            }
        }
    }
}

require __DIR__ . '/includes/header.php';
?>

<section class="section narrow">
    <div class="eyebrow">
        THE ONYX & IVORY / TÀI KHOẢN
    </div>

    <h1>Chào mừng <em>trở lại.</em></h1>

    <p>
        Đăng nhập để tiếp tục trải nghiệm của bạn
        tại The Onyx & Ivory.
    </p>

    <?php if ($error !== ''): ?>
        <div class="notice error" role="alert">
            <?= e($error) ?>
        </div>
    <?php endif; ?>

    <form
        action="<?= e(url('login.php')) ?>"
        method="post"
        class="panel"
    >
        <?= csrf() ?>

        <label for="login-email">
            Email
        </label>

        <input
            type="email"
            id="login-email"
            name="email"
            value="<?= e($email) ?>"
            placeholder="Nhập email của bạn"
            maxlength="190"
            autocomplete="username"
            required
        >

        <label for="login-password">
            Mật khẩu
        </label>

        <input
            type="password"
            id="login-password"
            name="password"
            placeholder="Nhập mật khẩu"
            autocomplete="current-password"
            required
        >

        <button type="submit" class="button full">
            ĐĂNG NHẬP ↗
        </button>
    </form>

    <p>
        Bạn chưa có tài khoản?
        <a
            class="text-link"
            href="<?= e(url('register.php')) ?>"
        >
            Đăng ký ngay
        </a>
    </p>

    <p>
        <a
            class="text-link"
            href="<?= e(url('index.php')) ?>"
        >
            ← Quay lại trang chủ
        </a>
    </p>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>