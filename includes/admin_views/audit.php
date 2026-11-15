<?php require_once __DIR__.'/../admin_catalog.php';require __DIR__.'/../staff_header.php'; ?>
<section class="admin-catalog" aria-label="Nhật ký thao tác">
    <?php catalog_toolbar('Tìm nhật ký',count($rows),'Nội dung hoặc người thực hiện…','',false,['paginate'=>false]); ?>
    <div class="table-wrap catalog-table-scroll"><table class="catalog-table"><thead><tr><th>Hành động</th><th>Người thực hiện</th><th>Nội dung</th><th>Thời gian</th><th class="catalog-action-cell">Hành động</th></tr></thead><tbody>
    <?php foreach($rows as $log):$logId=(int)$log['id']; ?>
        <tr data-catalog-row data-search="<?= e(($log['display_name']??'Khách / hệ thống').' '.$log['action'].' '.$log['detail']) ?>"><td><span class="catalog-name"><?= e($log['action']) ?></span></td><td><?= e($log['display_name']??'Khách / hệ thống') ?></td><td><?= e(mb_strimwidth($log['detail']??'',0,90,'…')) ?></td><td><?= e(date('d/m/Y H:i',strtotime($log['created_at']))) ?></td><td><div class="catalog-row-actions"><?php catalog_action('eye','Xem nhật ký #'.$logId,'#audit-'.$logId); ?></div></td></tr>
    <?php endforeach;catalog_empty(5,(bool)$rows); ?>
    </tbody></table></div>
    <p class="catalog-note">Tìm nhanh trong trang hiện tại. Nhật ký ghi nhận thao tác của nhân viên, không lưu mật khẩu.</p>
    <nav class="catalog-pager" aria-label="Phân trang nhật ký"><?php if($page>1): ?><a class="button outline" href="?page=<?= $page-1 ?>">← Trang trước</a><?php endif; ?><span>Trang <?= (int)$page ?> / <?= (int)$pages ?> · <?= (int)$count ?> thao tác</span><?php if($page<$pages): ?><a class="button outline" href="?page=<?= $page+1 ?>">Trang sau →</a><?php endif; ?></nav>
    <?php foreach($rows as $log):$logId=(int)$log['id'];catalog_dialog_start('audit-'.$logId,'Chi tiết nhật ký #'.$logId);catalog_details(['Hành động'=>$log['action'],'Người thực hiện'=>$log['display_name']??'Khách / hệ thống','Nội dung'=>$log['detail']??'','Thời gian'=>date('d/m/Y H:i',strtotime($log['created_at']))]); ?>
        <div class="catalog-dialog-actions"><button type="button" class="button outline" data-close-dialog>Đóng</button></div>
    <?php catalog_dialog_end();endforeach; ?>
</section>
<?php require __DIR__.'/../staff_footer.php'; ?>
