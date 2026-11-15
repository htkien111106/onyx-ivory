<?php
require_once __DIR__.'/../admin_catalog.php';
// Nạp danh sách nền khi mở chi tiết bằng GET; không thay đổi thao tác thành viên.
if ($customer) {
    $catalogLike = '%'.$search.'%';
    $listTotal = (int)query('SELECT COUNT(*) FROM customers WHERE full_name LIKE ? OR email LIKE ?',[$catalogLike,$catalogLike])->fetchColumn();
    $listPages = max(1,(int)ceil($listTotal/20));
    $listPage = max(1,min($listPages,(int)input('page','1')));
    $listOffset = ($listPage-1)*20;
    $listMembers = query("SELECT c.id,c.full_name,c.email,COALESCE(SUM(FLOOR(b.total_price / ?)),0) AS points
        FROM customers c LEFT JOIN bookings b ON b.customer_id=c.id AND b.status='completed'
        WHERE c.full_name LIKE ? OR c.email LIKE ? GROUP BY c.id,c.full_name,c.email
        ORDER BY points DESC,c.id ASC LIMIT 20 OFFSET $listOffset",[LOYALTY_VND_PER_POINT,$catalogLike,$catalogLike])->fetchAll();
} else {
    $listMembers=$members;$listTotal=$total;$listPages=$pages;$listPage=$page;$listOffset=$offset;
}
require __DIR__.'/../staff_header.php';
?>
<section class="admin-catalog" aria-label="Thành viên thân thiết">
    <div class="catalog-tools"><form method="get" class="catalog-filters" aria-label="Tìm thành viên">
        <label class="catalog-search"><span class="catalog-filter-label">Tìm thành viên</span><span class="catalog-search-field"><input type="search" name="q" value="<?= e($search) ?>" maxlength="100" placeholder="Họ tên hoặc email…"></span></label>
        <button type="submit" class="button catalog-filter-submit" aria-label="Tìm kiếm" title="Tìm kiếm"><?= catalog_icon('search') ?></button>
        <?php if($search!==''): ?><a class="catalog-clear" href="<?= e(url('admin/members.php')) ?>">Xóa lọc</a><?php endif; ?>
    </form></div>
    <div class="table-wrap catalog-table-scroll"><table class="catalog-table"><thead><tr><th>Thành viên</th><th>Email</th><th>Điểm</th><th>Hạng thành viên</th><th class="catalog-action-cell">Hành động</th></tr></thead><tbody>
    <?php foreach($listMembers as $member):$tier=loyalty_level((int)$member['points'])['current']; ?>
        <tr><td><span class="catalog-name"><?= e($member['full_name']) ?></span></td><td><?= e($member['email']) ?></td><td><?= (int)$member['points'] ?></td><td><?php catalog_status($tier['name'],true); ?></td><td><div class="catalog-row-actions"><?php catalog_action('eye','Xem quyền lợi của '.$member['full_name'],url('admin/members.php?customer_id='.(int)$member['id'].'&q='.rawurlencode($search).'&page='.$listPage)); ?></div></td></tr>
    <?php endforeach;if(!$listMembers): ?><tr class="catalog-empty"><td colspan="5">Không tìm thấy thành viên.</td></tr><?php endif; ?>
    </tbody></table></div>
    <div class="catalog-list-meta"><span><?= $listTotal?'Hiển thị '.($listOffset+1).'–'.min($listOffset+20,$listTotal).' / '.$listTotal.' thành viên':'0 thành viên' ?></span>
        <nav class="catalog-server-pages" aria-label="Phân trang thành viên"><?php if($listPage>1): ?><a class="button outline" href="<?= e(url('admin/members.php?q='.rawurlencode($search).'&page='.($listPage-1))) ?>">← Trang trước</a><?php endif; ?><span>Trang <?= $listPage ?> / <?= $listPages ?></span><?php if($listPage<$listPages): ?><a class="button outline" href="<?= e(url('admin/members.php?q='.rawurlencode($search).'&page='.($listPage+1))) ?>">Trang sau →</a><?php endif; ?></nav>
    </div>
    <?php if($customer):catalog_dialog_start('member-detail','Thành viên · '.$customer['full_name'],'Hồ sơ và quyền lợi theo đơn lưu trú',true); ?>
        <?php catalog_details(['Họ tên'=>$customer['full_name'],'Email'=>$customer['email'],'Điểm tích lũy'=>$points,'Hạng thành viên'=>$level['current']['name']]); ?>
        <h3 class="catalog-subheading">Quyền lợi theo đơn lưu trú</h3>
        <div class="table-wrap catalog-table-scroll"><table class="catalog-table member-benefits-table"><thead><tr><th>Quyền lợi</th><th>Đơn / loại phòng</th><th>Hạng / điểm</th><th>Trạng thái</th><th>Người trao</th><th>Hành động</th></tr></thead><tbody>
        <?php foreach($benefits as $benefit): ?>
            <tr><td><span class="catalog-name"><?= e($benefit['benefit_name']) ?></span></td><td>Đơn #<?= (int)$benefit['booking_id'] ?><span class="catalog-secondary"><?= e($benefit['room_name']) ?></span></td><td><?= e($benefit['tier_name']) ?><span class="catalog-secondary"><?= (int)$benefit['points_at_checkin'] ?> điểm</span></td><td><?= e(loyalty_benefit_status($benefit)) ?><?php if($benefit['delivered_at']): ?><span class="catalog-secondary"><?= e(date('d/m/Y H:i',strtotime($benefit['delivered_at']))) ?></span><?php endif; ?></td><td><?= $benefit['delivered_at']?e($benefit['delivered_by_name']??''):'—' ?></td><td><?php if(!$benefit['delivered_at']&&$benefit['booking_status']==='checked_in'): ?><form method="post" action="<?= e(url('admin/members.php?customer_id='.(int)$customer['id'])) ?>" data-confirm="Xác nhận khách đã nhận quyền lợi này?"><?= csrf() ?><?php catalog_hidden(['booking_id'=>(int)$benefit['booking_id']]); ?><button type="submit" class="small-button">Xác nhận đã trao</button></form><?php else: ?>—<?php endif; ?></td></tr>
        <?php endforeach;if(!$benefits): ?><tr class="catalog-empty"><td colspan="6">Thành viên chưa có quyền lợi được cấp.</td></tr><?php endif; ?>
        </tbody></table></div>
        <div class="catalog-dialog-actions"><button type="button" class="button outline" data-close-dialog>Đóng</button></div>
    <?php catalog_dialog_end();endif; ?>
</section>
<?php require __DIR__.'/../staff_footer.php'; ?>
