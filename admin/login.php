<?php require '../includes/bootstrap.php';if($logged=actor())go(staff_home($logged));$title='Đăng nhập nhân viên';$error='';
if(post()){check_csrf();$ip=$_SERVER['REMOTE_ADDR'] ?? 'unknown';
 query('DELETE FROM login_attempts WHERE attempted_at < DATE_SUB(NOW(), INTERVAL 15 MINUTE)');
 $attempts=(int)query('SELECT COUNT(*) FROM login_attempts WHERE ip=?',[$ip])->fetchColumn();
 if($attempts>=10){$error='Quá nhiều lần đăng nhập sai. Vui lòng thử lại sau 15 phút.';}else{
 $admin=query('SELECT * FROM admins WHERE email=?',[input('email')])->fetch();
 if($admin && $admin['active'] && password_verify(input('password'),$admin['password_hash'])){session_regenerate_id(true);$_SESSION['staff_id']=$admin['id'];$_SESSION['staff_version']=$admin['session_version'];query('DELETE FROM login_attempts WHERE ip=?',[$ip]);go(staff_home($admin));}
 query('INSERT INTO login_attempts(ip) VALUES(?)',[$ip]);$error='Email hoặc mật khẩu không đúng.';
 }}require '../includes/header.php'; ?><section class="section narrow"><div class="eyebrow">THE ONYX & IVORY / NHÂN VIÊN</div><h1>Chào mừng <em>trở lại.</em></h1><p>Dành cho lễ tân và quản trị viên. Hệ thống tự chuyển đến khu vực theo quyền của bạn.</p><?php if($error): ?><div class="notice error" role="alert"><?=e($error)?></div><?php endif; ?><form method="post" class="panel"><?=csrf()?><label>Email<input type="email" name="email" value="<?=e(input('email'))?>" required autocomplete="username"></label><label>Mật khẩu<input type="password" name="password" required autocomplete="current-password"></label><button class="button full">ĐĂNG NHẬP ↗</button></form><p>Lần đầu chạy? <a class="text-link" href="<?=e(url('install.php'))?>">Tạo tài khoản quản trị</a></p></section><?php require '../includes/footer.php'; ?>
