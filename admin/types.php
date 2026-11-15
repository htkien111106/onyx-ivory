<?php require '../includes/bootstrap.php';$user=admin_required();$title='Loại phòng & giá';$error='';$uploaded=null;
if(post()){check_csrf();try{
 $id=(int)input('id');$name=input('name');$sub=input('subtitle');$desc=input('description');$price=input('price');$bed=input('bed');$area=integer('area',10,500);$capacity=integer('capacity',1,10);$amenities=input('amenities');
 if(mb_strlen($name)<2 || mb_strlen($name)>100 || mb_strlen($sub)>150 || mb_strlen($desc)<10 || mb_strlen($desc)>5000 || !ctype_digit($price) || (int)$price<10000 || (int)$price>100000000 || mb_strlen($bed)<2 || mb_strlen($bed)>100 || mb_strlen($amenities)>2000)throw new DomainException('Kiểm tra tên, mô tả, giường, tiện nghi và giá (10.000–100.000.000 VND).');
 db()->beginTransaction();$old=$id?query('SELECT * FROM room_types WHERE id=? FOR UPDATE',[$id])->fetch():null;
 if($id && !$old)throw new DomainException('Không tìm thấy loại phòng.');
 if($old && query("SELECT COUNT(*) FROM bookings WHERE room_type_id=? AND status IN ('pending','confirmed','checked_in') AND (check_out>CURDATE() OR status='checked_in') AND guests>quantity*?",[$id,$capacity])->fetchColumn())throw new DomainException('Không thể giảm sức chứa vì có đơn hiện tại/tương lai vượt sức chứa mới.');
 $image=$old['image']??'deluxe.jpg';$file=$_FILES['image']??null;
 if($file && ($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_NO_FILE){
  if(!is_int($file['error']) || $file['error']!==UPLOAD_ERR_OK || !is_string($file['tmp_name']) || !is_uploaded_file($file['tmp_name']) || $file['size']>5*1024*1024)throw new DomainException('Ảnh tải lên không hợp lệ hoặc vượt 5 MB.');
  $mime=(new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);$ext=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'][$mime]??null;$size=@getimagesize($file['tmp_name']);
  if(!$ext || !$size || $size[0]>6000 || $size[1]>6000)throw new DomainException('Chỉ nhận ảnh JPG, PNG, WebP tối đa 6000 × 6000 pixel.');
  $image='uploads/'.bin2hex(random_bytes(16)).'.'.$ext;$uploaded=__DIR__.'/../assets/images/'.$image;
  if(!move_uploaded_file($file['tmp_name'],$uploaded))throw new DomainException('Không lưu được ảnh. Kiểm tra quyền ghi thư mục assets/images/uploads.');
 }
 $values=[$name,$sub,$desc,$area,$capacity,$bed,(int)$price,$image,input('active')==='1'?1:0,$amenities];
 if($id){$values[]=$id;query('UPDATE room_types SET name=?,subtitle=?,description=?,area=?,capacity=?,bed=?,price=?,image=?,active=?,amenities=? WHERE id=?',$values);}
 else{query('INSERT INTO room_types(name,subtitle,description,area,capacity,bed,price,image,active,amenities,inventory) VALUES(?,?,?,?,?,?,?,?,?,?,0)',$values);$id=(int)db()->lastInsertId();}
 audit('room_type_save','Lưu loại phòng #'.$id);db()->commit();flash('Đã lưu loại phòng. Thêm phòng cụ thể tại Danh mục phòng nếu đây là loại mới.');go('admin/types.php?id='.$id);
}catch(DomainException $ex){if(db()->inTransaction())db()->rollBack();if($uploaded && is_file($uploaded))unlink($uploaded);$error=$ex->getMessage();}}
$types=query('SELECT * FROM room_types ORDER BY id')->fetchAll();$selected=input('id',(string)($types[0]['id']??0));$r=['id'=>0,'name'=>'','subtitle'=>'','description'=>'','area'=>28,'capacity'=>2,'bed'=>'1 giường King','price'=>1800000,'image'=>'deluxe.jpg','active'=>1,'amenities'=>null];foreach($types as $x)if((string)$x['id']===$selected)$r=$x;
$defaultAmenities="Wi-Fi miễn phí\nĐiều hòa\nSmart TV\nPhòng tắm riêng\nKét an toàn\nTrà & cà phê\nBàn làm việc\nBữa sáng hằng ngày";
require __DIR__ . '/../includes/admin_views/types.php';
