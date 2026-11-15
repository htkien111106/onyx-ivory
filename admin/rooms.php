<?php require '../includes/bootstrap.php';require '../includes/operations.php';$user=admin_required();$title='Danh mục phòng';$error='';
if(post()){check_csrf();try{db()->beginTransaction();$action=input('action');
 if($action==='add'){
  $type=(int)input('room_type_id');$r=query('SELECT * FROM room_types WHERE id=? FOR UPDATE',[$type])->fetch();$number=input('number');
  if(!$r || !preg_match('/^[\p{L}\p{N} -]{1,20}$/u',$number))throw new DomainException('Chọn loại phòng và nhập số/tên phòng tối đa 20 ký tự (chữ, số, khoảng trắng, dấu gạch ngang).');
  if(query('SELECT COUNT(*) FROM hotel_rooms WHERE number=?',[$number])->fetchColumn())throw new DomainException('Số phòng đã tồn tại.');
  if(query('SELECT COUNT(*) FROM hotel_rooms WHERE room_type_id=?',[$type])->fetchColumn()>=100)throw new DomainException('Mỗi loại tối đa 100 phòng trong phiên bản này.');
  query('INSERT INTO hotel_rooms(room_type_id,number) VALUES(?,?)',[$type,$number]);query('UPDATE room_types SET inventory=(SELECT COUNT(*) FROM hotel_rooms WHERE room_type_id=? AND active=1) WHERE id=?',[$type,$type]);audit('room_add','Thêm phòng '.$number.' / loại #'.$type);
 }elseif($action==='update'){update_room_state((int)input('id'),input('housekeeping'),input('active')==='1'?1:0);}
 else throw new DomainException('Thao tác không hợp lệ.');
 db()->commit();flash('Đã lưu phòng. Tồn đặt phòng được tính từ danh mục phòng thực tế.');go('admin/rooms.php');
}catch(DomainException $ex){if(db()->inTransaction())db()->rollBack();$error=$ex->getMessage();}catch(PDOException $ex){if(db()->inTransaction())db()->rollBack();if($ex->getCode()==='23000')$error='Số phòng bị trùng. Hãy chọn số khác.';else throw $ex;}}
$types=query('SELECT * FROM room_types ORDER BY id')->fetchAll();$rooms=query('SELECT h.*,r.name room_name FROM hotel_rooms h JOIN room_types r ON r.id=h.room_type_id ORDER BY h.room_type_id,h.number')->fetchAll();require __DIR__ . '/../includes/admin_views/rooms.php';
