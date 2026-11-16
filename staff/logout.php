<?php
require __DIR__ . '/../includes/bootstrap.php';

if (!post()) {
    http_response_code(405);
    exit('Chỉ dùng POST.');
}
check_csrf();
$_SESSION = [];
session_regenerate_id(true);
flash('Bạn đã đăng xuất thành công.');
go('login.php');
