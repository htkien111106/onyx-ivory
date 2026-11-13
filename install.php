<?php require 'includes/bootstrap.php';$title='Cài đặt quản trị';$error='';
// Chỉ cài từ chính máy chạy XAMPP, không qua mạng LAN.
if(!in_array($_SERVER['REMOTE_ADDR'] ?? '',['127.0.0.1','::1'],true)){http_response_code(403);exit('Chỉ mở cài đặt từ localhost trên máy chạy XAMPP.');}
$exists=(int)query('SELECT COUNT(*) FROM admins')->fetchColumn();
if(post() && !$exists){check_csrf();try {
 $email=input('email');$password=input('password');
 if(!filter_var($email,FILTER_VALIDATE_EMAIL) || strlen($email)>190 || strlen($password)<12 || strlen($password)>72)throw new DomainException('Nhập email hợp lệ và mật khẩu từ 12 đến 72 ký tự.');
 if((int)query("SELECT GET_LOCK('onyx_ivory_install',5)")->fetchColumn()!==1)throw new DomainException('Đang có lượt cài đặt khác. Hãy thử lại.');
 try {if(query('SELECT COUNT(*) FROM admins')->fetchColumn())throw new DomainException('Quản trị viên đã được tạo.');query('INSERT INTO admins(email,password_hash) VALUES(?,?)',[$email,password_hash($password,PASSWORD_DEFAULT)]);} finally {query("SELECT RELEASE_LOCK('onyx_ivory_install')");}
 flash('Đã tạo quản trị viên. Bạn có thể đăng nhập.');go('admin/login.php');
} catch(DomainException $ex){$error=$ex->getMessage();}}
require 'includes/header.php'; ?><section class="section narrow"><div class="eyebrow">FIRST-TIME SETUP</div><h1>Khởi tạo <em>quản trị.</em></h1><?php if($exists): ?><div class="notice">Đã cài đặt. Trang này không cho tạo thêm tài khoản.</div><a class="button" href="<?=e(url('admin/login.php'))?>">ĐĂNG NHẬP</a><?php else: ?><p>Bạn đã nhập cơ sở dữ liệu thành công. Tạo tài khoản quản trị đầu tiên.</p><?php if($error): ?><div class="notice error"><?=e($error)?></div><?php endif; ?><form method="post" class="panel"><?=csrf()?><label>Email quản trị<input type="email" name="email" required maxlength="190" autocomplete="username"></label><label>Mật khẩu (12–72 ký tự)<input type="password" name="password" minlength="12" maxlength="72" required autocomplete="new-password"></label><button class="button full">TẠO TÀI KHOẢN</button></form><?php endif; ?></section><?php require 'includes/footer.php'; ?>
