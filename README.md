# The Onyx & Ivory — phiên bản 2, ba luồng nghiệp vụ

Website duy nhất một khách sạn bằng PHP thuần, HTML, CSS, JavaScript và MySQL/MariaDB. Dùng VS Code + XAMPP, không cần Composer, Laravel hay npm. Đã tích hợp bản sửa font tiếng Việt.

**Nếu bạn đã chạy bản trước: làm theo `NANG_CAP_V2.md`, nhập `database/upgrade_v2.sql` để giữ dữ liệu.**

## Cài mới

1. Giải nén thư mục `onyx-ivory` vào `C:\xampp\htdocs\onyx-ivory` (index.php nằm trực tiếp trong thư mục này).
2. Bật Apache và MySQL trong XAMPP.
3. Mở `http://localhost/phpmyadmin` → Import → chọn `database/schema.sql` → Go. File tạo database `onyx_ivory`, bảng và dữ liệu 3 loại phòng/15 phòng thực tế.
4. Mở thư mục bằng VS Code. Trong `config/config.php`: mặc định MySQL `127.0.0.1:3306`, user `root`, mật khẩu trống. Sửa theo máy bạn nếu khác.
5. Mở `http://localhost/onyx-ivory/install.php` để tạo quản trị viên đầu tiên. Không có tài khoản/mật khẩu mặc định. Cài đặt chỉ cho phép từ localhost và tự khóa khi đã có tài khoản.
6. Đăng nhập quản trị, vào Nhân viên & phân quyền, tạo tài khoản Lễ tân.

PHP 8.1+, extension PDO, pdo_mysql, mbstring, ctype, fileinfo và session. MySQL 8/MariaDB 10.4+, InnoDB. Dùng `http://localhost/...`, không mở file PHP trực tiếp và không chạy bằng VS Code Live Server. Apache mặc định XAMPP đọc các file .htaccess đi kèm.

## Đường dẫn và phân quyền

| Luồng | URL | Chức năng |
|---|---|---|
| Khách hàng | `/onyx-ivory/` | Xem phòng, tìm phòng trống, đặt phòng, nhận mã, tra cứu/hủy đơn, in xác nhận |
| Đăng nhập nhân viên chung | `/onyx-ivory/admin/login.php` | Tự chuyển trang theo vai trò |
| Lễ tân | `/onyx-ivory/reception/` | Đơn đặt phòng, tạo đơn tại quầy, phân phòng khi nhận khách, nhận/trả phòng, cập nhật dọn phòng/bảo trì, ghi thu/hoàn tiền, hóa đơn nội bộ |
| Quản trị viên | `/onyx-ivory/admin/` | Toàn bộ nghiệp vụ lễ tân, quản lý nhân viên/quyền, loại phòng/giá/ảnh/tiện nghi, danh mục phòng thực tế, báo cáo và nhật ký |

Khách không cần tài khoản; mã đơn ngẫu nhiên + email là phương thức tra cứu. Lễ tân và quản trị viên có tài khoản. Đây là **ba luồng sử dụng trong một hệ thống**, không phải ba website tách rời.

## Luồng công việc

### Khách hàng
Tìm phòng theo ngày/số phòng/số khách → chọn phòng → nhập thông tin → nhận mã đơn chờ → tra cứu trạng thái. Hủy trực tuyến trước ngày nhận nếu đơn chờ/đã xác nhận và chưa còn khoản tiền đã thu. Nếu đã thanh toán, liên hệ lễ tân để hoàn tiền rồi hủy.

### Lễ tân
- Tiếp nhận và xác nhận đơn online; hoặc tạo đơn đã xác nhận cho khách gọi điện/đến trực tiếp.
- Khi khách đến, chọn đúng số phòng cụ thể của đúng loại và nhận khách. Phòng phải hoạt động, đã dọn và chưa có khách khác đang ở.
- Thu tiền từng lần hoặc toàn bộ; ghi hình thức tiền mặt/chuyển khoản và mã tham chiếu. Tổng thu ròng không vượt tiền phòng.
- Hoàn tiền chỉ trong phạm vi số đã thu, trước khi hủy đơn đã có thanh toán. Giao dịch đã ghi không bị xóa; dùng giao dịch hoàn tiền khi cần điều chỉnh.
- In hóa đơn lưu trú nội bộ, có số chứng từ và lịch sử thu/hoàn. Không thay thế hóa đơn VAT/điện tử.
- Trả phòng từ ngày trả đã đặt, sau khi thu đủ; phòng chuyển Chờ dọn. Dọn xong mới chuyển Sẵn sàng.
- Cập nhật bảo trì khi không có khách và không làm thiếu tồn cho các đơn hiện tại/tương lai.

### Quản trị viên
- Tạo nhân viên, chọn vai trò Lễ tân/Quản trị viên, khóa/mở tài khoản, đặt lại mật khẩu cho nhân viên khác.
- Đổi quyền, khóa tài khoản hoặc đặt lại mật khẩu thu hồi các phiên đăng nhập cũ. Không tự khóa/hạ quyền ở trang quản trị nhân viên.
- Tạo/sửa loại phòng, mô tả, diện tích, sức chứa, loại giường, giá, tiện nghi và ảnh đại diện. Không giảm sức chứa trái với các đơn đang có.
- Thêm phòng thực tế, đóng/mở sử dụng; không xóa phòng có lịch sử. Tồn thực tế được tính từ bảng phòng.
- Xem tổng đơn, công suất hiện tại, thu/hoàn và thu ròng theo tháng/ngày, giá trị đơn tạo trong tháng theo loại phòng, nhật ký thao tác.

## Quy tắc quan trọng

- Một đơn đặt một loại phòng, 1–5 phòng, 1–20 khách; ở 1–30 đêm, ngày nhận không quá 1 năm tới. Trẻ em tính vào tổng khách; chưa có giường phụ/phụ thu theo tuổi.
- Tổng tiền = đơn giá/phòng/đêm × số phòng × số đêm. Giá mẫu gồm thuế và bữa sáng; đơn cũ giữ giá cũ khi đổi bảng giá.
- Tồn nhận đặt = số phòng hoạt động, không bảo trì − số phòng bị chiếm nhiều nhất trong từng đêm. Phòng chờ dọn vẫn tính cho việc đặt trước, nhưng không được giao khi check-in.
- Đơn pending/confirmed/checked_in giữ tồn. Đơn chờ không tự hết hạn, cần nhân viên xử lý.
- Phân phòng cụ thể được thực hiện khi nhận khách; chưa hỗ trợ gán trước số phòng cho ngày tương lai, đổi phòng đang ở hoặc gia hạn/trả sớm.
- Khách đang ở đến/quá ngày trả mà chưa check-out tiếp tục chặn tồn đặt mới cho đến khi lễ tân xử lý. Danh sách quá hạn nằm trên sơ đồ phòng.
- Trạng thái: pending → confirmed/cancelled; confirmed → checked_in/cancelled; checked_in → completed. Hủy/hoàn tất không khôi phục ngược.
- Thay đổi tồn/phân phòng/thanh toán dùng giao dịch, khóa dòng loại phòng và isolation READ COMMITTED để những yêu cầu chờ khóa đọc được kết quả mới nhất. Chống lặp request đặt phòng và thu tiền.
- Thời gian PHP và kết nối MySQL đều dùng giờ Việt Nam (+07:00).
- Công suất là ảnh chụp hiện tại, không phải báo cáo công suất lịch sử. Thu ròng là khoản tiền do nhân viên đã ghi nhận, không phải lợi nhuận/doanh thu kế toán.

## Cấu trúc chính

- `index.php`, `rooms.php`, `room.php`, `booking.php`, `lookup.php`, `confirmation.php`, `policies.php`: luồng khách.
- `reception/index.php`, `new.php`, `booking.php`, `rooms.php`: luồng lễ tân.
- `admin/index.php`, `users.php`, `types.php`, `rooms.php`, `audit.php`: quản trị.
- `admin/login.php`, `staff/account.php`, `staff/logout.php`, `staff/invoice.php`: chức năng nhân viên chung.
- `includes/bootstrap.php`: PDO, kiểm tra session/quyền, CSRF, tính tồn, tiện ích.
- `includes/operations.php`: nghiệp vụ đặt tại quầy, phân phòng, thanh toán, dọn/bảo trì, kiểm tra giao dịch.
- `includes/staff_header.php`: menu theo vai trò.
- `config/config.php`: kết nối DB, thông tin khách sạn.
- `database/schema.sql`: cài mới; `database/upgrade_v2.sql`: nâng cấp.
- `assets/style.css`, `assets/app.js`, `assets/images/`: giao diện, ảnh cục bộ; `assets/images/uploads/`: ảnh do admin tải lên.
- `tests/TEST_CASES.md`: kết quả và checklist kiểm thử.

Bảng chính: admins (tài khoản nhân viên, giữ tên bảng cũ để nâng cấp), room_types, bookings, hotel_rooms, room_assignments, payments, audit_logs, login_attempts, schema_migrations.

## Tải ảnh

Chỉ admin tải ảnh đại diện loại phòng. JPG/PNG/WebP ≤5 MB, kích thước ≤6000 × 6000. Kiểm tra MIME/nội dung ảnh, lưu tên ngẫu nhiên và không dùng tên file người tải gửi. File .htaccess chặn mã thực thi trong thư mục uploads.

Nếu ảnh trên 2 MB bị từ chối bởi cấu hình XAMPP mặc định, chỉnh `upload_max_filesize=5M`, `post_max_size=8M` trong `C:\xampp\php\php.ini`, rồi restart Apache. Bật `extension=fileinfo`. Đây là cấu hình máy, mã nguồn không tự sửa php.ini.

## Giới hạn phiên bản

Chưa có cổng thanh toán online, email tự động, tài khoản khách, tour, nhiều khách sạn, mã giảm giá, đánh giá, đặt dịch vụ spa/nhà hàng, phụ thu, hóa đơn thuế, gia hạn, đổi phòng hoặc check-out sớm. Thông tin thương mại và ảnh là minh họa. Nhà hàng/spa hiện là nội dung giới thiệu.

Bản này dành cho đồ án/local. Nếu triển khai thật cần cấu hình HTTPS, DB user riêng, sao lưu, chính sách dữ liệu thực tế và kiểm tra vận hành. File install.php nên xóa sau cài đặt. Tất cả thao tác ghi yêu cầu POST + CSRF, truy vấn tham số hóa; phân quyền được kiểm tra phía PHP, không chỉ ẩn menu.

## Xử lý lỗi

- 404: đúng thư mục `htdocs/onyx-ivory`, không lồng thêm thư mục ZIP; bật Apache.
- Lỗi kết nối/unknown column: bật MySQL, kiểm tra config, nhập đúng SQL cài mới/nâng cấp.
- Giao diện cũ: Ctrl+F5. Không thay CSS mới bằng file sửa font riêng của bản trước.
- 403: lễ tân đang mở chức năng chỉ dành cho admin; hoặc POST có token CSRF sai.
- Bị đưa về đăng nhập: tài khoản bị khóa/đổi quyền/đổi mật khẩu, hoặc cookie hết hạn.
- Chi tiết lỗi: XAMPP → Apache → Logs → error.log.

## Nguồn tham khảo

Cấu trúc giới thiệu/phòng/tiện nghi tham khảo https://lasinfoniavietnam.com/delrey/ ; thương hiệu, nội dung và mã nguồn được xây dựng riêng theo tên The Onyx & Ivory. Ảnh minh họa lưu cục bộ từ Unsplash:
- photo-1611892440504-42a792e24d32 (hero/Deluxe)
- photo-1566665797739-1674de7a421a (Suite)
- photo-1590490360182-c33d57733427 (Family)
- photo-1517248135467-4c7edcad34c4 (nhà hàng)
- photo-1540555700478-4be289fbecef (spa)

Các ảnh lấy từ `https://images.unsplash.com/<photo-id>`; thông tin giấy phép tại https://unsplash.com/license . Không phải ảnh khách sạn thực mang tên The Onyx & Ivory.
