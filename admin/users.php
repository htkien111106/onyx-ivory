<?php require '../includes/bootstrap.php';$user=admin_required();$title='Nhân viên & phân quyền';$error='';
if(post()){check_csrf();try{
 $action=input('action');$name=input('display_name');$email=input('email');$role=input('role');$pw=input('password');$active=input('active')==='1'?1:0;$id=(int)input('id');
 if(mb_strlen($name)<2 || mb_strlen($name)>100 || !filter_var($email,FILTER_VALIDATE_EMAIL) || strlen($email)>190 || !in_array($role,['admin','receptionist'],true))throw new DomainException('Kiểm tra tên, email và vai trò.');
 if(($action==='create' || $pw!=='') && (strlen($pw)<12 || strlen($pw)>72))throw new DomainException('Mật khẩu cần từ 12 đến 72 ký tự.');
 db()->beginTransaction();query('SELECT id FROM admins ORDER BY id FOR UPDATE')->fetchAll();
 if(query('SELECT COUNT(*) FROM admins WHERE email=? AND id<>?',[$email,$action==='create'?0:$id])->fetchColumn())throw new DomainException('Email đã có tài khoản.');
 if($action==='create'){
  query('INSERT INTO admins(email,password_hash,display_name,role,active) VALUES(?,?,?,?,?)',[$email,password_hash($pw,PASSWORD_DEFAULT),$name,$role,$active]);$new=(int)db()->lastInsertId();audit('staff_create','Tạo tài khoản #'.$new.' / '.$role);
 }elseif($action==='update'){
  $old=query('SELECT * FROM admins WHERE id=?',[$id])->fetch();if(!$old)throw new DomainException('Không tìm thấy nhân viên.');
  if($id===(int)$user['id'] && ($role!=='admin' || !$active || $pw!==''))throw new DomainException('Không tự hạ quyền, khóa hoặc đặt lại mật khẩu ở đây. Đổi mật khẩu tại Tài khoản của tôi.');
  if($old['role']==='admin' && $old['active'] && ($role!=='admin' || !$active) && (int)query("SELECT COUNT(*) FROM admins WHERE role='admin' AND active=1")->fetchColumn()<=1)throw new DomainException('Phải giữ ít nhất một quản trị viên hoạt động.');
  $revoke=($old['role']!==$role || (int)$old['active']!==$active || $pw!=='' || $old['email']!==$email)?1:0;
  query('UPDATE admins SET display_name=?,email=?,role=?,active=?,session_version=session_version+? WHERE id=?',[$name,$email,$role,$active,$revoke,$id]);
  if($pw!=='')query('UPDATE admins SET password_hash=? WHERE id=?',[password_hash($pw,PASSWORD_DEFAULT),$id]);
  if($id===(int)$user['id'])$_SESSION['staff_version']=(int)$old['session_version']+$revoke;
  audit('staff_update','Sửa tài khoản #'.$id.' / '.$role.' / active='.$active);
 }else throw new DomainException('Thao tác không hợp lệ.');
 db()->commit();flash('Đã lưu tài khoản. Đổi quyền, khóa hoặc đặt lại mật khẩu sẽ thu hồi phiên cũ.');go('admin/users.php');
}catch(DomainException $ex){if(db()->inTransaction())db()->rollBack();$error=$ex->getMessage();}}
$users=query('SELECT id,display_name,email,role,active FROM admins ORDER BY id')->fetchAll();require __DIR__ . '/../includes/admin_views/users.php';
