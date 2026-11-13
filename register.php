<?php
require __DIR__ . '/includes/bootstrap.php';

$title = 'Đăng ký tài khoản';
$errors = [];

// Giữ lại thông tin đã nhập khi có lỗi.
// Không giữ lại mật khẩu.
$fullName = '';
$email = '';
$phone = '';

if (post()) {
    // Kiểm tra form được gửi từ phiên hợp lệ.
    check_csrf();

    $fullName = input('full_name');
    $email = strtolower(input('email'));
    $phone = input('phone');

    // Không trim mật khẩu để tránh tự thay đổi mật khẩu khách nhập.
    $password = isset($_POST['password'])
        && is_string($_POST['password'])
        ? $_POST['password']
        : '';

    $confirmPassword = isset($_POST['confirm_password'])
        && is_string($_POST['confirm_password'])
        ? $_POST['confirm_password']
        : '';

    // 1. Kiểm tra họ tên.
    if (mb_strlen($fullName) < 2 || mb_strlen($fullName) > 100) {
        $errors[] = 'Họ tên phải có từ 2 đến 100 ký tự.';
    }

    // 2. Kiểm tra email.
    if (
        !filter_var($email, FILTER_VALIDATE_EMAIL)
        || strlen($email) > 190
    ) {
        $errors[] = 'Vui lòng nhập địa chỉ email hợp lệ.';
    }

    // 3. Kiểm tra số điện thoại.
    // Cho phép dấu +, khoảng trắng, dấu ngoặc và gạch ngang.
    $phoneDigits = preg_replace('/\D/', '', $phone);

    if (
        !preg_match('/^\+?[0-9][0-9 ()-]{7,23}$/', $phone)
        || strlen($phoneDigits) < 9
        || strlen($phoneDigits) > 15
    ) {
        $errors[] = 'Số điện thoại phải có từ 9 đến 15 chữ số.';
    }

    // 4. Kiểm tra mật khẩu.
    if (mb_strlen($password) < 12) {
        $errors[] = 'Mật khẩu phải có ít nhất 12 ký tự.';
    }

    // Giới hạn phù hợp với thuật toán băm mật khẩu đang sử dụng.
    if (strlen($password) > 72) {
        $errors[] = 'Mật khẩu quá dài. Vui lòng dùng mật khẩu ngắn hơn.';
    }

    if ($password !== $confirmPassword) {
        $errors[] = 'Mật khẩu nhập lại không khớp.';
    }

    // 5. Kiểm tra đồng ý chính sách.
    if (input('agree') !== '1') {
        $errors[] = 'Bạn cần đồng ý với chính sách của khách sạn.';
    }

    // 6. Kiểm tra email và lưu tài khoản.
    if (!$errors) {
        try {
            $customerExists = query(
                'SELECT id FROM customers WHERE email = ? LIMIT 1',
                [$email]
            )->fetch();

            $staffExists = query(
                'SELECT id FROM admins WHERE email = ? LIMIT 1',
                [$email]
            )->fetch();

            if ($customerExists || $staffExists) {
                $errors[] = 'Email này đã được sử dụng. Vui lòng dùng email khác.';
            } else {
                // Băm mật khẩu trước khi lưu.
                $passwordHash = password_hash(
                    $password,
                    PASSWORD_DEFAULT
                );

                // Chỉ tạo tài khoản khách hàng.
                // Form đăng ký không được tạo quyền lễ tân/quản trị.
                query(
                    'INSERT INTO customers
                        (full_name, email, phone, password_hash)
                     VALUES (?, ?, ?, ?)',
                    [
                        $fullName,
                        $email,
                        $phone,
                        $passwordHash
                    ]
                );

                flash('Đăng ký thành công! Tài khoản khách hàng của bạn đã được tạo.');

                // Chuyển trang sau khi lưu để tránh gửi lại form khi F5.
                go('register.php');
            }
        } catch (PDOException $ex) {
            // Xử lý trường hợp email vừa được đăng ký ở một yêu cầu khác.
            if ($ex->getCode() === '23000') {
                $errors[] = 'Email này đã được sử dụng. Vui lòng dùng email khác.';
            } else {
                error_log((string) $ex);
                $errors[] = 'Chưa thể tạo tài khoản. Vui lòng thử lại sau.';
            }
        }
    }
}

require __DIR__ . '/includes/header.php';
?>

<section class="section narrow">
    <div class="eyebrow">
        THE ONYX & IVORY / THÀNH VIÊN
    </div>

    <h1>
        Bắt đầu một <em>kỳ nghỉ riêng.</em>
    </h1>

    <p>
        Tạo tài khoản khách hàng để thuận tiện hơn
        cho những lần đặt phòng tiếp theo.
    </p>

    <?php if ($errors): ?>
        <div class="notice error" role="alert">
            <strong>Vui lòng kiểm tra lại:</strong>

            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?= e($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="post" class="panel">
        <?= csrf() ?>

        <h2>Đăng ký tài khoản</h2>

        <label for="full_name">
            Họ và tên *

            <input
                type="text"
                id="full_name"
                name="full_name"
                value="<?= e($fullName) ?>"
                minlength="2"
                maxlength="100"
                autocomplete="name"
                placeholder="Nguyễn Văn An"
                required
            >
        </label>

        <label for="email">
            Email *

            <input
                type="email"
                id="email"
                name="email"
                value="<?= e($email) ?>"
                maxlength="190"
                autocomplete="email"
                placeholder="ban@email.com"
                required
            >
        </label>

        <label for="phone">
            Số điện thoại *

            <input
                type="tel"
                id="phone"
                name="phone"
                value="<?= e($phone) ?>"
                maxlength="25"
                autocomplete="tel"
                placeholder="0912345678"
                required
            >
        </label>

        <div class="form-grid">
            <label for="password">
                Mật khẩu *

                <input
                    type="password"
                    id="password"
                    name="password"
                    minlength="12"
                    maxlength="72"
                    autocomplete="new-password"
                    placeholder="Ít nhất 12 ký tự"
                    required
                >
            </label>

            <label for="confirm_password">
                Nhập lại mật khẩu *

                <input
                    type="password"
                    id="confirm_password"
                    name="confirm_password"
                    minlength="12"
                    maxlength="72"
                    autocomplete="new-password"
                    placeholder="Nhập lại mật khẩu"
                    required
                >
            </label>
        </div>

        <label class="checkbox">
            <input
                type="checkbox"
                name="agree"
                value="1"
                <?= input('agree') === '1' ? 'checked' : '' ?>
                required
            >

            <span>
                Tôi đồng ý với
                <a
                    href="<?= e(url('policies.php')) ?>"
                    target="_blank"
                    rel="noopener"
                >
                    chính sách của khách sạn
                </a>.
            </span>
        </label>

        <button type="submit" class="button full">
            TẠO TÀI KHOẢN ↗
        </button>
    </form>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>