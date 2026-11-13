<?php require '../includes/bootstrap.php';$user=staff_required();$title='Tài khoản của tôi';$error='';
if(post()){check_csrf();try{
 $a=query('SELECT * FROM admins WHERE id=?',[$user['id']])->fetch();$pw=input('new_password');
 if(!password_verify(input('current_password'),$a['password_hash']))throw new DomainException('Mật khẩu hiện tại không đúng.');
 if(strlen($pw)<12 || strlen($pw)>72)throw new DomainException('Mật khẩu mới cần 12–72 ký tự.');
 db()->beginTransaction();query('UPDATE admins SET password_hash=?,session_version=session_version+1 WHERE id=?',[password_hash($pw,PASSWORD_DEFAULT),$user['id']]);audit('password_change','Đổi mật khẩu tài khoản #'.$user['id']);db()->commit();
 $_SESSION=[];session_regenerate_id(true);flash('Đã đổi mật khẩu và kết thúc các phiên đăng nhập cũ. Hãy đăng nhập lại.');go('admin/login.php');
}catch(DomainException $ex){if(db()->inTransaction())db()->rollBack();$error=$ex->getMessage();}}
require '../includes/staff_header.php'; ?><div class="panel"><p><?=e($user['email'])?> · <?=$user['role']==='admin'?'Quản trị viên':'Lễ tân'?></p><form method="post" class="narrow"><?=csrf()?><label>Mật khẩu hiện tại<input type="password" name="current_password" autocomplete="current-password" required></label><label>Mật khẩu mới<input type="password" name="new_password" minlength="12" maxlength="72" autocomplete="new-password" required></label><button class="button">ĐỔI MẬT KHẨU</button></form></div><?php require '../includes/staff_footer.php'; ?>
