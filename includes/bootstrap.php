<?php
declare(strict_types=1);

date_default_timezone_set('Asia/Ho_Chi_Minh');

ini_set('session.use_strict_mode', '1');

session_set_cookie_params([
    'httponly' => true,
    'samesite' => 'Lax',
    'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
]);

session_start();

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: same-origin');
header('Cache-Control: no-store');

$config = require __DIR__ . '/../config/config.php';

// Xác định đường dẫn gốc cho cả trang khách và khu vực nhân viên.
$scriptName = $_SERVER['SCRIPT_NAME'] ?? '/index.php';
$isAdminPath = preg_match('~/(admin|reception|staff)/[^/]+$~', $scriptName) === 1;

$base = rtrim(str_replace('\\', '/', dirname($scriptName)), '/');

if ($isAdminPath) {
    $base = rtrim(dirname($base), '/');
}

if ($base === '.' || $base === '/') {
    $base = '';
}

function url(string $path = ''): string
{
    global $base;

    return $base . '/' . $path;
}

function e($v): string
{
    return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
}

function money($v): string
{
    return number_format((float) $v, 0, ',', '.') . ' ₫';
}

function go(string $path): void
{
    header('Location: ' . url($path));
    exit;
}

function input(string $key, string $default = ''): string
{
    $value = $_POST[$key] ?? $_GET[$key] ?? $default;

    return is_string($value) ? trim($value) : $default;
}

function post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST';
}

function csrf(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }

    return '<input type="hidden" name="csrf" value="' . e($_SESSION['csrf']) . '">';
}

function check_csrf(): void
{
    if (
        !isset($_SESSION['csrf'])
        || !hash_equals($_SESSION['csrf'], input('csrf'))
    ) {
        http_response_code(403);
        exit('Phiên không hợp lệ. Vui lòng tải lại trang.');
    }
}

function db(): PDO
{
    static $db = null;
    global $config;

    if ($db === null) {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            $config['db_host'],
            $config['db_port'],
            $config['db_name']
        );

        $db = new PDO(
            $dsn,
            $config['db_user'],
            $config['db_pass'],
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );

        $db->exec("SET time_zone = '+07:00'");

        // Sau khi chờ khóa loại phòng, lượt đặt tiếp theo cần thấy
        // dữ liệu mà giao dịch trước vừa ghi nhận.
        $db->exec('SET SESSION TRANSACTION ISOLATION LEVEL READ COMMITTED');
    }

    return $db;
}

function query(string $sql, array $args = []): PDOStatement
{
    $statement = db()->prepare($sql);
    $statement->execute($args);

    return $statement;
}

function flash(string $msg): void
{
    $_SESSION['flash'] = $msg;
}

function valid_dates(string $in, string $out): int
{
    $checkIn = DateTimeImmutable::createFromFormat('!Y-m-d', $in);
    $checkOut = DateTimeImmutable::createFromFormat('!Y-m-d', $out);

    if (
        !$checkIn
        || !$checkOut
        || $checkIn->format('Y-m-d') !== $in
        || $checkOut->format('Y-m-d') !== $out
    ) {
        throw new DomainException(
            'Vui lòng chọn ngày nhận và trả phòng hợp lệ.'
        );
    }

    $nights = (int) $checkIn->diff($checkOut)->format('%r%a');
    $today = new DateTimeImmutable('today');

    if (
        $checkIn < $today
        || $checkIn > $today->modify('+1 year')
        || $nights < 1
        || $nights > 30
    ) {
        throw new DomainException(
            'Ngày nhận phòng từ hôm nay đến 1 năm tới; lưu trú từ 1 đến 30 đêm.'
        );
    }

    return $nights;
}

function integer(string $key, int $min, int $max, int $default = 1): int
{
    $value = input($key, (string) $default);

    if (!ctype_digit($value) || (int) $value < $min || (int) $value > $max) {
        throw new DomainException('Số lượng phòng hoặc số khách không hợp lệ.');
    }

    return (int) $value;
}

function sellable_count(int $type): int
{
    return (int) query(
        "SELECT COUNT(*)
         FROM hotel_rooms
         WHERE room_type_id = ?
           AND active = 1
           AND housekeeping <> 'maintenance'",
        [$type]
    )->fetchColumn();
}

/**
 * Số phòng cùng hạng có thể đặt cho toàn bộ kỳ nghỉ.
 * $in và $out là ngày dạng Y-m-d đã được bên gọi kiểm tra.
 */
function available(array $room, string $in, string $out): int
{
    $roomTypeId = (int) $room['id'];
    $stock = sellable_count($roomTypeId);

    if ($stock === 0) {
        return 0;
    }

    $today = date('Y-m-d');

    $bookings = query(
        "SELECT check_in, check_out, quantity, status
         FROM bookings
         WHERE room_type_id = ?
           AND status IN ('pending', 'confirmed', 'checked_in')
           AND check_in < ?
           AND (
               check_out > ?
               OR (status = 'checked_in' AND check_out <= ?)
           )",
        [$roomTypeId, $out, $in, $today]
    );

    // Mỗi ngày lưu số phòng tăng hoặc giảm trong ngày đó.
    $changes = [];

    while ($booking = $bookings->fetch()) {
        $quantity = (int) $booking['quantity'];

        $stillStaying = $booking['status'] === 'checked_in'
            && $booking['check_out'] <= $today;

        // Chỉ xét phần kỳ ở giao với khoảng ngày khách đang tìm.
        $start = max($in, $booking['check_in']);
        $end = $stillStaying ? $out : min($out, $booking['check_out']);

        $changes[$start] = ($changes[$start] ?? 0) + $quantity;
        $changes[$end] = ($changes[$end] ?? 0) - $quantity;
    }

    // Ngày Y-m-d sắp xếp theo chuỗi cũng là thứ tự thời gian.
    ksort($changes, SORT_STRING);

    $occupied = 0;
    $peakOccupied = 0;

    foreach ($changes as $change) {
        $occupied += $change;
        $peakOccupied = max($peakOccupied, $occupied);
    }

    return max(0, $stock - $peakOccupied);
}

function status_name(string $s): string
{
    $names = [
        'pending' => 'Chờ xác nhận',
        'confirmed' => 'Đã xác nhận',
        'checked_in' => 'Đang lưu trú',
        'completed' => 'Đã trả phòng',
        'cancelled' => 'Đã hủy',
    ];

    return $names[$s] ?? $s;
}

function actor(): ?array
{
    if (empty($_SESSION['staff_id'])) {
        return null;
    }

    $user = query(
        'SELECT id, email, display_name, role, active, session_version
         FROM admins
         WHERE id = ?',
        [$_SESSION['staff_id']]
    )->fetch();

    if (
        !$user
        || !$user['active']
        || (int) $user['session_version'] !== (int) ($_SESSION['staff_version'] ?? 0)
    ) {
        unset($_SESSION['staff_id'], $_SESSION['staff_version']);
        return null;
    }

    return $user;
}

function staff_home(array $user): string
{
    return $user['role'] === 'admin'
        ? 'admin/index.php'
        : 'reception/index.php';
}

function staff_required(): array
{
    $user = actor();

    if (!$user) {
        go('admin/login.php');
    }

    return $user;
}

function admin_required(): array
{
    $user = staff_required();

    if ($user['role'] !== 'admin') {
        http_response_code(403);
        exit('403 — Bạn không có quyền quản trị.');
    }

    return $user;
}

function audit(string $action, string $detail): void
{
    query(
        'INSERT INTO audit_logs (staff_id, action, detail)
         VALUES (?, ?, ?)',
        [
            $_SESSION['staff_id'] ?? null,
            $action,
            mb_substr($detail, 0, 1000),
        ]
    );
}

set_exception_handler(function (Throwable $ex): void {
    error_log((string) $ex);
    http_response_code(500);

    echo '<!doctype html>
        <html lang="vi">
        <meta charset="utf-8">
        <title>Không thể xử lý yêu cầu</title>
        <body style="font:18px Arial;padding:8%;background:#f6f3ed">
            <h1>Không thể xử lý yêu cầu</h1>
            <p>Vui lòng thử lại. Nếu lỗi tiếp diễn, hãy kiểm tra nhật ký PHP.</p>
            <a href="' . e(url('index.php')) . '">Về trang chủ</a>
        </body>
        </html>';
});