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

require __DIR__ . '/../includes/admin_views/services.php';
