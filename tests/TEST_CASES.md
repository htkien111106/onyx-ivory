# Kiểm thử bản 2 — ba luồng nghiệp vụ

Môi trường: PHP 8.3.6 và MariaDB 10.11.14, cơ sở dữ liệu tạm độc lập. Các request PHP được gọi với phiên riêng và dữ liệu giả. Không dùng dữ liệu khách của người dùng.

**Kết quả: 72/72 kiểm tra tích hợp đạt; 30 file PHP qua kiểm tra cú pháp; JavaScript qua kiểm tra cú pháp.**

## Những nhóm đã kiểm tra

- Nâng cấp từ schema cũ giữ tài khoản, hash mật khẩu, đơn, giá và trạng thái; nhập lại migration không sinh trùng phòng.
- Cài mới, đăng nhập thực sự, quyền truy cập và thu hồi phiên khi khóa tài khoản/đổi mật khẩu.
- Lễ tân bị chặn cả GET và POST vào các chức năng quản trị; trường POST giả không cho sửa quyền hoặc kích hoạt phòng.
- Đặt online và tại quầy, chống lặp request, đúng giá đã chốt.
- Phân đúng số phòng/loại phòng; chặn phòng sai loại, chưa dọn và đã có khách.
- Thu từng phần, chống ghi trùng, chặn thu quá số còn lại, chặn hoàn quá số đã thu.
- Khách/lễ tân không hủy đơn còn số tiền đã thu; hoàn đủ tiền mới hủy.
- Trả phòng phải thu đủ; trả xong phòng chuyển chờ dọn; chứng từ nội bộ có thông tin thanh toán.
- Thêm/ngừng phòng, cập nhật tồn, chặn bảo trì làm thiếu phòng cho đơn đã giữ.
- Sửa giá không đổi giá đơn cũ; giảm sức chứa phải tôn trọng đơn hiện tại/tương lai; tiện nghi hiển thị ở trang khách.
- Hai request tranh phòng cuối: chỉ một đơn được tạo. Hai lễ tân tranh cùng phòng: chỉ một nhận khách thành công. Hai giao dịch cùng thu hết số dư: chỉ một được chấp nhận.
- Nhật ký thao tác và giới hạn đăng nhập sai.

Danh sách từng kiểm tra được ghi trong `integration-results.json`. Các kiểm tra render trang cũng được tính trong tổng số, không đồng nghĩa toàn bộ kiểm tra đều là thao tác giao diện.

## Kiểm tra giao diện

Đã xem trực quan trang Tổng quan & báo cáo và Sơ đồ phòng ở desktop, dùng HTML render từ PHP/MySQL và CSS thật. Không dùng bản HTML này làm mã nguồn vận hành; mã giao là PHP kết nối MySQL.

Nhánh từ chối upload không hợp lệ đã được kiểm tra tự động; upload ảnh thành công qua trình duyệt còn nằm trong checklist chạy trên XAMPP.

Chưa chạy trực tiếp trên Windows/XAMPP của người dùng và chưa kiểm thử toàn bộ thao tác qua trình duyệt. Không khẳng định đã kiểm tra mọi kích thước điện thoại. Các bước cần kiểm tra trên máy khi cài:

1. Dùng cửa sổ thường đăng nhập quản trị, cửa sổ ẩn danh đăng nhập lễ tân; kiểm tra chuyển trang và menu theo vai trò.
2. Đặt online rồi lễ tân xác nhận, nhận khách, thu tiền, xem/in hóa đơn; đến ngày trả phòng hoàn tất trả và chuyển phòng sang sẵn sàng sau dọn.
3. Đặt tại quầy, đổi ngày/loại phòng/số lượng; kiểm tra số tiền cập nhật và tổng tiền server.
4. Đặt nhiều phòng cùng loại, chọn đúng số phòng để nhận; thiếu/thừa/sai loại phải bị chặn.
5. Ghi thu từng phần và thử gửi lại; đối chiếu số tiền tại hóa đơn, báo cáo tháng và nhật ký.
6. Thử thu vượt, hoàn vượt; thử hủy đơn đang còn tiền đã thu; không được cập nhật sai.
7. Khóa tài khoản lễ tân; trang đang mở phải yêu cầu đăng nhập lại ở request tiếp theo. Đổi mật khẩu cũng thu hồi phiên cũ.
8. Thử lễ tân gõ URL `/admin/users.php`, `/admin/types.php`, `/admin/rooms.php`; phải trả 403.
9. Đưa phòng trống vào bảo trì và kiểm tra tồn. Không cho bảo trì phòng có khách hoặc làm thiếu phòng đã giữ.
10. Upload ảnh JPG/PNG/WebP ≤5 MB; thử file quá lớn, SVG hoặc PHP; chỉ ảnh hợp lệ được lưu và hiển thị.
11. Thử ở 390px, 768px và 1440px, thao tác menu bằng chuột và bàn phím; bảng dài cuộn trong khung.
12. Kiểm tra Apache chặn URL cấu hình, SQL và file thực thi trong uploads bằng các .htaccess đi kèm.

Chỉ sử dụng dữ liệu giả cho demo. Muốn thử trả phòng ngay trong ngày, chuẩn bị một đơn thử có ngày trả hôm nay trong database thử; không sửa ngày của đơn kinh doanh thật.
