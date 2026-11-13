<?php
require __DIR__ . '/../includes/bootstrap.php';

$user = admin_required();
$title = 'Danh mục dịch vụ';
$error = '';

$editId = (int) input('id', '0');

$form = [
    'name' => '',
    'unit' => 'lần',
    'price' => '',
    'active' => 1
];

if ($editId > 0) {
    $existing = query(
        'SELECT * FROM services WHERE id = ?',
        [$editId]
    )->fetch();

    if (!$existing) {
        http_response_code(404);
        exit('Không tìm thấy dịch vụ.');
    }

    $form = $existing;
}

if (post()) {
    check_csrf();

    $form['name'] = input('name');
    $form['unit'] = input('unit');
    $form['price'] = input('price');
    $form['active'] = input('active') === '1' ? 1 : 0;

    try {
        if (
            mb_strlen($form['name']) < 2
            || mb_strlen($form['name']) > 100
        ) {
            throw new DomainException(
                'Tên dịch vụ cần từ 2 đến 100 ký tự.'
            );
        }

        if (
            mb_strlen($form['unit']) < 1
            || mb_strlen($form['unit']) > 30
        ) {
            throw new DomainException(
                'Đơn vị tính cần từ 1 đến 30 ký tự.'
            );
        }

        if (
            !ctype_digit($form['price'])
            || strlen($form['price']) > 8
            || (int) $form['price'] < 1
        ) {
            throw new DomainException(
                'Giá dịch vụ phải từ 1 đến 99.999.999 đồng.'
            );
        }

        db()->beginTransaction();

        if ($editId > 0) {
            $locked = query(
                'SELECT id FROM services WHERE id = ? FOR UPDATE',
                [$editId]
            )->fetch();

            if (!$locked) {
                throw new DomainException('Dịch vụ không còn tồn tại.');
            }

            query(
                'UPDATE services
                 SET name = ?, unit = ?, price = ?, active = ?
                 WHERE id = ?',
                [
                    $form['name'],
                    $form['unit'],
                    (int) $form['price'],
                    $form['active'],
                    $editId
                ]
            );

            audit('service.update', 'Cập nhật dịch vụ #' . $editId);
        } else {
            query(
                'INSERT INTO services (name, unit, price, active)
                 VALUES (?, ?, ?, ?)',
                [
                    $form['name'],
                    $form['unit'],
                    (int) $form['price'],
                    $form['active']
                ]
            );

            $newId = (int) db()->lastInsertId();
            audit('service.create', 'Tạo dịch vụ #' . $newId);
        }

        db()->commit();

        flash('Đã lưu thông tin dịch vụ.');
        go('admin/services.php');

    } catch (DomainException $ex) {
        if (db()->inTransaction()) {
            db()->rollBack();
        }

        $error = $ex->getMessage();

    } catch (Throwable $ex) {
        if (db()->inTransaction()) {
            db()->rollBack();
        }

        error_log((string) $ex);
        $error = 'Chưa thể lưu dịch vụ. Vui lòng thử lại.';
    }
}

$services = query(
    'SELECT * FROM services ORDER BY active DESC, name, id'
)->fetchAll();

require __DIR__ . '/../includes/staff_header.php';
?>

<div class="panel">
    <h2><?= $editId > 0 ? 'Chỉnh sửa dịch vụ' : 'Thêm dịch vụ' ?></h2>

    <form action="<?= e(url('admin/services.php')) ?>" method="post">
        <?= csrf() ?>

        <input type="hidden" name="id" value="<?= $editId ?>">

        <div class="form-grid">
            <label>
                Tên dịch vụ
                <input
                    name="name"
                    value="<?= e($form['name']) ?>"
                    minlength="2"
                    maxlength="100"
                    placeholder="Giặt là, minibar, đưa đón…"
                    required
                >
            </label>

            <label>
                Đơn vị tính
                <input
                    name="unit"
                    value="<?= e($form['unit']) ?>"
                    maxlength="30"
                    placeholder="kg, chai, lượt…"
                    required
                >
            </label>

            <label>
                Giá mỗi đơn vị (VND)
                <input
                    type="number"
                    name="price"
                    value="<?= e($form['price']) ?>"
                    min="1"
                    max="99999999"
                    step="1"
                    required
                >
            </label>

            <label>
                Trạng thái
                <select name="active">
                    <option
                        value="1"
                        <?= (int) $form['active'] === 1 ? 'selected' : '' ?>
                    >
                        Đang cung cấp
                    </option>

                    <option
                        value="0"
                        <?= (int) $form['active'] === 0 ? 'selected' : '' ?>
                    >
                        Ngừng cung cấp
                    </option>
                </select>
            </label>
        </div>

        <button type="submit" class="button">LƯU DỊCH VỤ</button>

        <?php if ($editId > 0): ?>
            <a
                class="text-link"
                href="<?= e(url('admin/services.php')) ?>"
            >
                Thêm dịch vụ mới
            </a>
        <?php endif; ?>
    </form>

    <p>
        Thay đổi giá chỉ áp dụng cho các lần ghi nhận mới.
        Dịch vụ đã ghi nhận vào đơn giữ nguyên tên và giá tại thời điểm đó.
    </p>
</div>

<div class="table-wrap">
    <table>
        <thead>
            <tr>
                <th>Dịch vụ</th>
                <th>Đơn vị</th>
                <th>Giá</th>
                <th>Trạng thái</th>
                <th>Thao tác</th>
            </tr>
        </thead>

        <tbody>
            <?php foreach ($services as $service): ?>
                <tr>
                    <td><?= e($service['name']) ?></td>
                    <td><?= e($service['unit']) ?></td>
                    <td><?= money($service['price']) ?></td>
                    <td>
                        <?= (int) $service['active'] === 1
                            ? 'Đang cung cấp'
                            : 'Ngừng cung cấp' ?>
                    </td>
                    <td>
                        <a href="<?= e(url(
                            'admin/services.php?id=' . (int) $service['id']
                        )) ?>">
                            Chỉnh sửa
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>

            <?php if (!$services): ?>
                <tr>
                    <td colspan="5">Chưa có dịch vụ.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require __DIR__ . '/../includes/staff_footer.php'; ?>