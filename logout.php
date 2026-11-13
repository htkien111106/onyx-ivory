<?php
require __DIR__ . '/includes/bootstrap.php';

// Chỉ đăng xuất khi người dùng gửi biểu mẫu hợp lệ.
if (!post()) {
    go('index.php');
}

check_csrf();

// Xóa thông tin đăng nhập của cả khách hàng và nhân viên.
unset(
    $_SESSION['staff_id'],
    $_SESSION['staff_version'],
    $_SESSION['customer_id'],
    $_SESSION['customer_version'],
    $_SESSION['csrf']
);

session_regenerate_id(true);

flash('Bạn đã đăng xuất thành công.');

go('index.php');