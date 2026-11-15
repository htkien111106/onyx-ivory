<?php require_once __DIR__.'/../admin_catalog.php';require __DIR__.'/../staff_header.php'; ?>
<section class="admin-reports" aria-label="Báo cáo hoạt động khách sạn">
    <?php if($missing): ?><div class="notice error">Có <?= (int)$missing ?> đơn đang ở từ bản cũ chưa bổ sung phòng, nên công suất hiện tại chưa đầy đủ. <a href="<?= e(url('reception/rooms.php')) ?>">Xử lý tại sơ đồ phòng</a>.</div><?php endif; ?>
    <form method="get" class="filter"><label>Tháng báo cáo<input name="month" type="month" value="<?= e($month) ?>" required></label><button type="submit" class="button">Xem báo cáo</button></form>
    <div class="tabs" role="tablist" aria-label="Nhóm báo cáo" data-report-tabs>
        <button type="button" role="tab" id="tab-cash" aria-controls="report-cash" aria-selected="true" data-report-target="report-cash">Thu chi theo tháng</button>
        <button type="button" role="tab" id="tab-types" aria-controls="report-types" aria-selected="false" data-report-target="report-types">Đơn theo loại phòng</button>
        <button type="button" role="tab" id="tab-daily" aria-controls="report-daily" aria-selected="false" data-report-target="report-daily">Thu ròng theo ngày</button>
        <button type="button" role="tab" id="tab-operation" aria-controls="report-operation" aria-selected="false" data-report-target="report-operation">Vận hành hiện tại</button>
    </div>
    <div id="report-cash" role="tabpanel" aria-labelledby="tab-cash" data-report-panel>
        <div class="table-wrap catalog-table-scroll"><table class="catalog-table"><thead><tr><th>Tháng báo cáo</th><th>Tiền đã thu</th><th>Tiền đã hoàn</th><th>Thu ròng</th></tr></thead><tbody><tr><td><?= e($dt->format('m/Y')) ?></td><td class="catalog-price"><?= money($cash['collected']) ?></td><td><?= money($cash['refunded']) ?></td><td class="catalog-price"><?= money((int)$cash['collected']-(int)$cash['refunded']) ?></td></tr></tbody></table></div>
        <p class="catalog-note">Thu ròng dựa trên các giao dịch nhân viên đã ghi nhận, không phải lợi nhuận hay doanh thu kế toán. Dữ liệu bản cũ chưa có sổ thu tiền nên không tự suy ra đã thanh toán.</p>
    </div>
    <div id="report-types" role="tabpanel" aria-labelledby="tab-types" data-report-panel hidden>
        <div class="table-wrap catalog-table-scroll"><table class="catalog-table"><thead><tr><th>Loại phòng</th><th>Tổng đơn (gồm đã hủy)</th><th>Giá trị đơn chưa hủy</th></tr></thead><tbody><?php foreach($rows as $reportRow): ?><tr><td><?= e($reportRow['name']) ?></td><td><?= (int)$reportRow['total'] ?></td><td class="catalog-price"><?= money($reportRow['booked_value']) ?></td></tr><?php endforeach; ?></tbody></table></div>
    </div>
    <div id="report-daily" role="tabpanel" aria-labelledby="tab-daily" data-report-panel hidden>
        <div class="table-wrap catalog-table-scroll"><table class="catalog-table"><thead><tr><th>Ngày ghi nhận</th><th>Thu − hoàn</th></tr></thead><tbody><?php foreach($daily as $day): ?><tr><td><?= e(date('d/m/Y',strtotime($day['day']))) ?></td><td class="catalog-price"><?= money($day['net']) ?></td></tr><?php endforeach;if(!$daily): ?><tr class="catalog-empty"><td colspan="2">Chưa có giao dịch trong tháng này.</td></tr><?php endif; ?></tbody></table></div>
    </div>
    <div id="report-operation" role="tabpanel" aria-labelledby="tab-operation" data-report-panel hidden>
        <div class="table-wrap catalog-table-scroll"><table class="catalog-table"><thead><tr><th>Chỉ số</th><th>Giá trị</th><th>Phạm vi</th></tr></thead><tbody>
            <tr><td>Tổng đơn đặt phòng</td><td><?= (int)$stats['total'] ?></td><td>Mọi thời điểm</td></tr>
            <tr><td>Đơn chờ xác nhận</td><td><?= (int)$stats['pending'] ?></td><td>Hiện tại</td></tr>
            <tr><td>Đơn đang lưu trú</td><td><?= (int)$stats['staying'] ?></td><td>Hiện tại</td></tr>
            <tr><td>Phòng đang có khách / phòng hoạt động</td><td><?= (int)$occupied ?> / <?= (int)$stock ?></td><td>Hiện tại, loại trừ phòng bảo trì</td></tr>
            <tr><td>Công suất hiện tại</td><td><?= $stock?number_format($occupied/$stock*100,1,',','.'):0 ?>%</td><td>Phòng đã phân cho khách đang ở</td></tr>
        </tbody></table></div>
        <p class="catalog-note">Công suất = số phòng đã phân cho khách đang ở / số phòng đang hoạt động, không bảo trì. Phòng chờ dọn thuộc mẫu số.</p>
    </div>
</section>
<?php require __DIR__.'/../staff_footer.php'; ?>
