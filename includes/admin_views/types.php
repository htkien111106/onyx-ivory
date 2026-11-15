<?php
require_once __DIR__ . '/../admin_catalog.php';
$roomCounts = [];
foreach (query('SELECT room_type_id, COUNT(*) AS total, SUM(active = 1) AS active_count FROM hotel_rooms GROUP BY room_type_id')->fetchAll() as $countRow) $roomCounts[(int)$countRow['room_type_id']] = $countRow;
$uiMode = input('ui');
$editType = $r;
if (post() && $error !== '') {
    foreach (['name','subtitle','description','area','capacity','bed','price','amenities'] as $field) $editType[$field] = input($field, (string)($r[$field] ?? ''));
    $editType['active'] = input('active') === '1' ? 1 : 0;
    $editType['id'] = (int)input('id');
}
require __DIR__ . '/../staff_header.php';
?>
<section class="admin-catalog" aria-label="Danh sách loại phòng">
    <?php catalog_toolbar('Tìm loại phòng', count($types), 'Nhập tên loại phòng…', '?ui=add&id=0'); ?>
    <div class="table-wrap catalog-table-scroll"><table class="catalog-table"><thead><tr><th>Loại phòng</th><th>Giá / phòng / đêm</th><th>Số phòng</th><th>Trạng thái</th><th class="catalog-action-cell">Hành động</th></tr></thead><tbody>
    <?php foreach ($types as $type): $typeId=(int)$type['id']; $counts=$roomCounts[$typeId]??['total'=>0,'active_count'=>0]; ?>
        <tr data-catalog-row data-search="<?= e($type['name']) ?>" data-status="<?= (int)$type['active']?'active':'inactive' ?>">
            <td><div class="catalog-record-name"><img src="<?= e(url('assets/images/'.$type['image'])) ?>" alt="<?= e($type['name']) ?>" loading="lazy"><div><span class="catalog-name"><?= e($type['name']) ?></span><span class="catalog-secondary"><?= (int)$type['area'] ?> m² · Tối đa <?= (int)$type['capacity'] ?> khách / phòng</span></div></div></td>
            <td class="catalog-price"><?= money($type['price']) ?></td>
            <td><?= (int)$counts['total'] ?> phòng</td>
            <td><?php catalog_status((int)$type['active']?'Mở bán':'Ngừng bán', (bool)$type['active']); ?></td>
            <td><div class="catalog-row-actions"><?php catalog_action('eye','Xem '.$type['name'],'#type-view-'.$typeId); catalog_action('edit','Sửa '.$type['name'],'?id='.$typeId.'&ui=edit'); catalog_action((int)$type['active']?'trash':'power',(int)$type['active']?'Ngừng bán '.$type['name']:'Mở bán '.$type['name'],'?id='.$typeId.'&ui=status',(bool)$type['active']); ?></div></td>
        </tr>
    <?php endforeach; catalog_empty(5, (bool)$types); ?>
    </tbody></table></div>
    <p class="catalog-note">Số phòng được lấy từ danh mục phòng thực tế. Ngừng bán loại phòng vẫn giữ nguyên lịch sử đặt phòng.</p>

    <?php foreach ($types as $type): $typeId=(int)$type['id']; ?>
        <?php catalog_dialog_start('type-view-'.$typeId,$type['name'],'Thông tin chi tiết loại phòng'); ?>
        <img class="catalog-preview-image" loading="lazy" src="<?= e(url('assets/images/'.$type['image'])) ?>" alt="<?= e($type['name']) ?>">
        <?php catalog_details(['Giới thiệu'=>$type['subtitle'],'Diện tích'=>$type['area'].' m²','Sức chứa'=>'Tối đa '.$type['capacity'].' khách / phòng','Loại giường'=>$type['bed'],'Giá / đêm'=>money($type['price']),'Tổng số phòng'=>($roomCounts[$typeId]['total']??0).' phòng','Số phòng hoạt động'=>($roomCounts[$typeId]['active_count']??0).' phòng','Mô tả'=>$type['description'],'Tiện nghi'=>$type['amenities']??$defaultAmenities,'Trạng thái'=>(int)$type['active']?'Mở bán':'Ngừng bán']); ?>
        <div class="catalog-dialog-actions"><button type="button" class="button outline" data-close-dialog>Đóng</button><a class="button" href="?id=<?= $typeId ?>&ui=edit">Chỉnh sửa</a></div>
        <?php catalog_dialog_end(); ?>

        <?php $target=(int)$type['active']?0:1; catalog_dialog_start('type-status-'.$typeId,$target?'Mở bán loại phòng':'Ngừng bán loại phòng',$type['name'],$uiMode==='status' && (int)$r['id']===$typeId,true); ?>
        <p><?= $target?'Loại phòng sẽ được mở bán trở lại.':'Loại phòng sẽ ngừng hiển thị để nhận đặt mới. Các đơn, ảnh và thông tin đã lưu được giữ lại.' ?></p>
        <form method="post" action="<?= e(url('admin/types.php')) ?>"><?= csrf() ?>
            <?php $statusFields=array_intersect_key($type,array_flip(['id','name','subtitle','description','area','capacity','bed','price']));$statusFields['amenities']=$type['amenities']??$defaultAmenities;$statusFields['active']=$target;catalog_hidden($statusFields);catalog_form_actions($target?'Mở bán':'Ngừng bán',!$target); ?>
        </form>
        <?php catalog_dialog_end(); ?>
    <?php endforeach; ?>

    <?php catalog_dialog_start('type-editor',(int)$editType['id']?'Chỉnh sửa loại phòng':'Thêm loại phòng','Thông tin phòng, giá và ảnh đại diện',in_array($uiMode,['add','edit'],true)||(post()&&$error!=='')); ?>
    <?php catalog_error($error); ?>
    <form method="post" action="<?= e(url('admin/types.php')) ?>" enctype="multipart/form-data">
        <?= csrf() ?><?php catalog_hidden(['id'=>(int)$editType['id']]); ?>
        <div class="form-grid">
            <label class="catalog-span">Tên loại phòng<input name="name" value="<?= e($editType['name']) ?>" minlength="2" maxlength="100" required></label>
            <label class="catalog-span">Giới thiệu ngắn<input name="subtitle" value="<?= e($editType['subtitle']) ?>" maxlength="150" required></label>
            <label>Diện tích (m²)<input type="number" name="area" value="<?= e($editType['area']) ?>" min="10" max="500" required></label>
            <label>Số khách tối đa / phòng<input type="number" name="capacity" value="<?= e($editType['capacity']) ?>" min="1" max="10" required></label>
            <label>Loại giường<input name="bed" value="<?= e($editType['bed']) ?>" maxlength="100" required></label>
            <label>Giá mỗi đêm (VND)<input type="number" name="price" value="<?= e($editType['price']) ?>" min="10000" max="100000000" required></label>
            <label class="catalog-span">Mô tả<textarea name="description" minlength="10" maxlength="5000" rows="4" required><?= e($editType['description']) ?></textarea></label>
            <label class="catalog-span">Tiện nghi (mỗi dòng một mục)<textarea name="amenities" maxlength="2000" rows="4"><?= e($editType['amenities']??$defaultAmenities) ?></textarea></label>
        </div>
        <div class="catalog-image-editor"><img src="<?= e(url('assets/images/'.$r['image'])) ?>" alt="Ảnh phòng hiện tại"><label>Ảnh đại diện<input type="file" name="image" accept="image/jpeg,image/png,image/webp"><small>JPG, PNG, WebP · tối đa 5 MB, 6000 × 6000 px. Để trống để giữ ảnh hiện tại.</small></label></div>
        <label class="checkbox"><input type="checkbox" name="active" value="1" <?= $editType['active']?'checked':'' ?>>Mở bán loại phòng</label>
        <?php catalog_form_actions('Lưu loại phòng'); ?>
    </form>
    <?php catalog_dialog_end(); ?>
</section>
<?php require __DIR__ . '/../staff_footer.php'; ?>
