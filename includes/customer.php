<?php

function current_customer(): ?array
{
    // Không sử dụng phiên khách hàng khi đang đăng nhập nhân viên.
    if (actor()) {
        return null;
    }

    if (empty($_SESSION['customer_id'])) {
        return null;
    }

    $customer = query(
        'SELECT id, full_name, email, phone, active, session_version
         FROM customers
         WHERE id = ?
         LIMIT 1',
        [$_SESSION['customer_id']]
    )->fetch();

    if (
        !$customer
        || (int) $customer['active'] !== 1
        || (int) $customer['session_version']
            !== (int) ($_SESSION['customer_version'] ?? 0)
    ) {
        unset(
            $_SESSION['customer_id'],
            $_SESSION['customer_version']
        );

        return null;
    }

    return $customer;
}

function customer_required(): array
{
    if ($staff = actor()) {
        go(staff_home($staff));
    }

    $customer = current_customer();

    if (!$customer) {
        flash('Vui lòng đăng nhập để xem tài khoản của bạn.');
        go('login.php');
    }

    return $customer;
}