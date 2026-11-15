<?php require_once __DIR__.'/../admin_catalog.php';$uiMode=input('ui');require __DIR__.'/../staff_header.php'; ?>
<section class="admin-catalog" aria-label="Danh sách dịch vụ">
    <?php catalog_toolbar('Tìm dịch vụ',count($services),'Nhập tên dịch vụ…','?ui=add'); ?>
    <div class="table-wrap catalog-table-scroll"><table class="catalog-table"><thead><tr><th>Dịch vụ</th><th>Đơn vị tính</th><th>Đơn giá</th><th>Trạng thái</th><th class="catalog-action-cell">Hành động</th></tr></thead><tbody>
    <?php foreach($services as $service):$serviceId=(int)$service['id']; ?>
        <tr data-catalog-row data-search="<?= e($service['name']) ?>" data-status="<?= (int)$service['active']?'active':'inactive' ?>"><td><span class="catalog-name"><?= e($service['name']) ?></span><span class="catalog-secondary">DV<?= str_pad((string)$serviceId,3,'0',STR_PAD_LEFT) ?></span></td><td><?= e($service['unit']) ?></td><td class="catalog-price"><?= money($service['price']) ?></td><td><?php catalog_status((int)$service['active']?'Đang cung cấp':'Ngừng cung cấp',(bool)$service['active']); ?></td><td><div class="catalog-row-actions"><?php catalog_action('eye','Xem '.$service['name'],'#service-view-'.$serviceId);catalog_action('edit','Sửa '.$service['name'],'?id='.$serviceId.'&ui=edit');catalog_action((int)$service['active']?'trash':'power',(int)$service['active']?'Ngừng cung cấp '.$service['name']:'Mở lại '.$service['name'],'?id='.$serviceId.'&ui=status',(bool)$service['active']); ?></div></td></tr>
    <?php endforeach;catalog_empty(5,(bool)$services); ?>
    </tbody></table></div>
    <p class="catalog-note">Giá mới áp dụng cho lần ghi nhận dịch vụ mới. Dịch vụ đã dùng trong đơn giữ nguyên tên và giá đã lưu.</p>
    <?php foreach($services as $service):$serviceId=(int)$service['id']; ?>
        <?php catalog_dialog_start('service-view-'.$serviceId,$service['name'],'Chi tiết dịch vụ');catalog_details(['Mã dịch vụ'=>'DV'.str_pad((string)$serviceId,3,'0',STR_PAD_LEFT),'Đơn vị tính'=>$service['unit'],'Đơn giá'=>money($service['price']),'Trạng thái'=>(int)$service['active']?'Đang cung cấp':'Ngừng cung cấp']); ?>
        <div class="catalog-dialog-actions"><button type="button" class="button outline" data-close-dialog>Đóng</button><a class="button" href="?id=<?= $serviceId ?>&ui=edit">Chỉnh sửa</a></div><?php catalog_dialog_end(); ?>
        <?php $target=(int)$service['active']?0:1;catalog_dialog_start('service-status-'.$serviceId,$target?'Mở lại dịch vụ':'Ngừng cung cấp dịch vụ',$service['name'],$uiMode==='status'&&$editId===$serviceId,true); ?>
        <p><?= $target?'Dịch vụ sẽ xuất hiện lại trong danh mục để lễ tân lựa chọn.':'Ngừng ghi nhận dịch vụ này cho lần sử dụng mới. Dữ liệu dịch vụ trong các đơn cũ vẫn được giữ lại.' ?></p>
        <form method="post" action="<?= e(url('admin/services.php')) ?>"><?= csrf() ?><?php catalog_hidden(['id'=>$serviceId,'name'=>$service['name'],'unit'=>$service['unit'],'price'=>$service['price'],'active'=>$target]);catalog_form_actions($target?'Mở lại':'Ngừng cung cấp',!$target); ?></form><?php catalog_dialog_end(); ?>
    <?php endforeach; ?>
    <?php catalog_dialog_start('service-editor',$editId?'Chỉnh sửa dịch vụ':'Thêm dịch vụ','Tên, đơn vị tính và giá cung cấp',in_array($uiMode,['add','edit'],true)||(post()&&$error!==''));catalog_error($error); ?>
    <form method="post" action="<?= e(url('admin/services.php')) ?>"><?= csrf() ?><?php catalog_hidden(['id'=>$editId]); ?>
        <div class="form-grid"><label class="catalog-span">Tên dịch vụ<input name="name" value="<?= e($form['name']) ?>" minlength="2" maxlength="100" placeholder="Giặt là, minibar, đưa đón…" required></label><label>Đơn vị tính<input name="unit" value="<?= e($form['unit']) ?>" maxlength="30" placeholder="kg, chai, lượt…" required></label><label>Giá mỗi đơn vị (VND)<input type="number" name="price" value="<?= e($form['price']) ?>" min="1" max="99999999" step="1" required></label><label class="catalog-span">Trạng thái<select name="active"><option value="1" <?= (int)$form['active']===1?'selected':'' ?>>Đang cung cấp</option><option value="0" <?= (int)$form['active']===0?'selected':'' ?>>Ngừng cung cấp</option></select></label></div>
        <?php catalog_form_actions('Lưu dịch vụ'); ?>
    </form><?php catalog_dialog_end(); ?>
</section><?php require __DIR__.'/../staff_footer.php'; ?>
