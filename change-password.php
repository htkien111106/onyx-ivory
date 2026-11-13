<?php
require __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/customer.php';

$customer = customer_required();

$title = 'Đổi mật khẩu';
$errors = [];

// Đọc mật khẩu nguyên trạng, không xóa khoảng trắng.
$readPassword = static function (string $key): string {
    $value = $_POST[$key] ?? '';
    return is_string($value) ? $value : '';
};

if (post()) {
    check_csrf();

    $currentPassword = $readPassword('current_password');
    $newPassword = $readPassword('new_password');
    $confirmPassword = $readPassword('confirm_password');

    // Giới hạn số lần thử mật khẩu hiện tại trong phiên.
    $now = time();

    $attempts = $_SESSION['customer_password_attempts'] ?? [];
    $attempts = is_array($attempts) ? $attempts : [];

    $attempts = array_values(array_filter(
        $attempts,
        static function ($timestamp) use ($now): bool {
            return is_int($timestamp) && $timestamp > $now - 900;
        }
    ));

    $_SESSION['customer_password_attempts'] = $attempts;

    if (count($attempts) >= 5) {
        $errors[] =
            'Bạn đã nhập sai mật khẩu hiện tại quá nhiều lần. '
            . 'Vui lòng thử lại sau khoảng 15 phút.';
    }

    if ($currentPassword === '') {
        $errors[] = 'Vui lòng nhập mật khẩu hiện tại.';
    } elseif (strlen($currentPassword) > 72) {
        $errors[] = 'Mật khẩu hiện tại không hợp lệ.';
    }

    if (mb_strlen($newPassword) < 12) {
        $errors[] = 'Mật khẩu mới phải có ít nhất 12 ký tự.';
    }

    if (strlen($newPassword) > 72) {
        $errors[] =
            'Mật khẩu mới quá dài. Vui lòng dùng tối đa 72 byte '
            . '(ký tự có dấu có thể chiếm nhiều byte).';
    }

    if (
        strpos($currentPassword, "\0") !== false
        || strpos($newPassword, "\0") !== false
    ) {
        $errors[] = 'Mật khẩu chứa ký tự không hợp lệ.';
    }

    if ($newPassword !== $confirmPassword) {
        $errors[] = 'Mật khẩu xác nhận không khớp.';
    }

    if (!$errors) {
        try {
            db()->beginTransaction();

            // Khóa tài khoản trong lúc kiểm tra và cập nhật mật khẩu.
            $account = query(
                'SELECT id, password_hash, active, session_version
                 FROM customers
                 WHERE id = ?
                 FOR UPDATE',
                [$customer['id']]
            )->fetch();

            if (
                !$account
                || (int) $account['active'] !== 1
                || (int) $account['session_version']
                    !== (int) ($_SESSION['customer_version'] ?? 0)
            ) {
                db()->rollBack();

                unset(
                    $_SESSION['customer_id'],
                    $_SESSION['customer_version'],
                    $_SESSION['csrf']
                );

                session_regenerate_id(true);

                flash('Phiên đăng nhập đã hết hiệu lực. Vui lòng đăng nhập lại.');
                go('login.php');
            }

            if (
                !password_verify(
                    $currentPassword,
                    $account['password_hash']
                )
            ) {
                $_SESSION['customer_password_attempts'][] = time();

                throw new DomainException(
                    'Mật khẩu hiện tại không đúng.'
                );
            }

            if (
                password_verify(
                    $newPassword,
                    $account['password_hash']
                )
            ) {
                throw new DomainException(
                    'Mật khẩu mới phải khác mật khẩu hiện tại.'
                );
            }

            $passwordHash = password_hash(
                $newPassword,
                PASSWORD_DEFAULT
            );

            query(
                'UPDATE customers
                 SET password_hash = ?,
                     session_version = session_version + 1
                 WHERE id = ?',
                [$passwordHash, $customer['id']]
            );

            db()->commit();

            // Đăng xuất phiên hiện tại.
            // Các phiên khác sẽ bị từ chối khi kiểm tra session_version.
            unset(
                $_SESSION['customer_id'],
                $_SESSION['customer_version'],
                $_SESSION['customer_password_attempts'],
                $_SESSION['csrf']
            );

            session_regenerate_id(true);

            flash(
                'Đổi mật khẩu thành công. '
                . 'Vui lòng đăng nhập bằng mật khẩu mới.'
            );

            go('login.php');

        } catch (DomainException $ex) {
            if (db()->inTransaction()) {
                db()->rollBack();
            }

            $errors[] = $ex->getMessage();

        } catch (Throwable $ex) {
            if (db()->inTransaction()) {
                db()->rollBack();
            }

            error_log((string) $ex);

            $errors[] =
                'Chưa thể đổi mật khẩu lúc này. Vui lòng thử lại.';
        }
    }
}

require __DIR__ . '/includes/header.php';
?>

<style>
    .password-page .password-field {
        margin-bottom: 20px;
    }

    .password-page .password-input-wrap {
        position: relative;
        margin-top: 8px;
    }

    .password-page .password-input-wrap input {
        width: 100%;
        margin: 0;
        padding-right: 76px;
    }

    .password-page .password-toggle {
        position: absolute;
        top: 50%;
        right: 12px;
        transform: translateY(-50%);
        width: auto;
        margin: 0;
        padding: 8px;
        border: 0;
        background: transparent;
        color: #876d40;
        font: 13px Arial, sans-serif;
        cursor: pointer;
    }

    .password-page .password-toggle:hover {
        color: #202823;
    }

    .password-page .password-toggle:focus-visible {
        outline: 2px solid #b1935b;
        outline-offset: 2px;
    }

    .password-page .password-help {
        margin: 12px 0 24px;
        color: #6b6d66;
        font-size: 14px;
        line-height: 1.8;
    }
</style>

<section class="page-heading">
    <div class="eyebrow">
        THE ONYX &amp; IVORY / TÀI KHOẢN
    </div>

    <h1>Đổi <em>mật khẩu.</em></h1>

    <p>Cập nhật mật khẩu để bảo vệ tài khoản của bạn.</p>
</section>

<section class="section narrow password-page">
    <?php if ($errors): ?>
        <div class="notice error" role="alert">
            <?php foreach ($errors as $message): ?>
                <div><?= e($message) ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <form
        action="<?= e(url('change-password.php')) ?>"
        method="post"
        class="panel"
    >
        <?= csrf() ?>

        <h2>Bảo mật tài khoản</h2>

        <div class="password-field">
            <label for="current-password">
                Mật khẩu hiện tại *
            </label>

            <div class="password-input-wrap">
                <input
                    id="current-password"
                    type="password"
                    name="current_password"
                    autocomplete="current-password"
                    maxlength="72"
                    required
                >

                <button
                    type="button"
                    class="password-toggle"
                    data-password-target="current-password"
                    aria-controls="current-password"
                    aria-label="Hiện mật khẩu hiện tại"
                    aria-pressed="false"
                >
                    Hiện
                </button>
            </div>
        </div>

        <div class="password-field">
            <label for="new-password">
                Mật khẩu mới *
            </label>

            <div class="password-input-wrap">
                <input
                    id="new-password"
                    type="password"
                    name="new_password"
                    autocomplete="new-password"
                    minlength="12"
                    maxlength="72"
                    aria-describedby="password-help"
                    required
                >

                <button
                    type="button"
                    class="password-toggle"
                    data-password-target="new-password"
                    aria-controls="new-password"
                    aria-label="Hiện mật khẩu mới"
                    aria-pressed="false"
                >
                    Hiện
                </button>
            </div>
        </div>

        <div class="password-field">
            <label for="confirm-password">
                Xác nhận mật khẩu mới *
            </label>

            <div class="password-input-wrap">
                <input
                    id="confirm-password"
                    type="password"
                    name="confirm_password"
                    autocomplete="new-password"
                    minlength="12"
                    maxlength="72"
                    required
                >

                <button
                    type="button"
                    class="password-toggle"
                    data-password-target="confirm-password"
                    aria-controls="confirm-password"
                    aria-label="Hiện mật khẩu xác nhận"
                    aria-pressed="false"
                >
                    Hiện
                </button>
            </div>
        </div>

        <p id="password-help" class="password-help">
            Mật khẩu mới cần ít nhất 12 ký tự và khác mật khẩu hiện tại.
            Bạn có thể dùng một cụm từ dài, dễ nhớ với riêng mình.
            Sau khi đổi thành công, bạn cần đăng nhập lại.
        </p>

        <button type="submit" class="button full">
            CẬP NHẬT MẬT KHẨU ↗
        </button>
    </form>

    <p>
        <a class="text-link" href="<?= e(url('account.php#profile')) ?>">
            ← Quay lại tài khoản
        </a>
    </p>
</section>

<script>
(() => {
    document.querySelectorAll('[data-password-target]').forEach(button => {
        const input = document.getElementById(
            button.dataset.passwordTarget
        );

        if (!input) return;

        const originalLabel = button.getAttribute('aria-label');

        button.addEventListener('click', () => {
            const showPassword = input.type === 'password';

            input.type = showPassword ? 'text' : 'password';
            button.textContent = showPassword ? 'Ẩn' : 'Hiện';

            button.setAttribute(
                'aria-pressed',
                String(showPassword)
            );

            button.setAttribute(
                'aria-label',
                showPassword
                    ? originalLabel.replace('Hiện', 'Ẩn')
                    : originalLabel
            );
        });
    });
})();
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>