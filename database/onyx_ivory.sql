-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Máy chủ: 127.0.0.1
-- Thời gian đã tạo: Th10 12, 2026 lúc 08:41 PM
-- Phiên bản máy phục vụ: 10.4.32-MariaDB
-- Phiên bản PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Cơ sở dữ liệu: `onyx_ivory`
--

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `admins`
--

CREATE TABLE `admins` (
  `id` int(10) UNSIGNED NOT NULL,
  `email` varchar(190) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `display_name` varchar(100) NOT NULL DEFAULT 'Quản trị viên',
  `role` enum('admin','receptionist') NOT NULL DEFAULT 'admin',
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `session_version` int(10) UNSIGNED NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `admins`
--

INSERT INTO `admins` (`id`, `email`, `password_hash`, `created_at`, `display_name`, `role`, `active`, `session_version`) VALUES
(1, '1@gmail.com', '$2y$10$7hv.dy6SJ1pQ6JryqHzPU.9iY09nbDT9zejf7fuo/LLH0X7e6pktu', '2026-11-09 15:38:25', 'Quản trị viên', 'admin', 1, 1),
(2, 'linhgay@gmail.com', '$2y$10$5sAi6j.92K82HePBIXWZg.mE/rG1UBPtSZXRoBro0XlbU2YYe14FO', '2026-11-09 16:18:49', 'Đào Mai Linh', 'receptionist', 1, 1);

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `audit_logs`
--

CREATE TABLE `audit_logs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `staff_id` int(10) UNSIGNED DEFAULT NULL,
  `action` varchar(60) NOT NULL,
  `detail` varchar(1000) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `audit_logs`
--

INSERT INTO `audit_logs` (`id`, `staff_id`, `action`, `detail`, `created_at`) VALUES
(1, 1, 'booking_status', 'Đơn #1: pending -> confirmed', '2026-11-09 16:17:47'),
(2, 1, 'booking_status', 'Đơn #1: confirmed -> checked_in', '2026-11-09 16:17:59'),
(3, 1, 'staff_create', 'Tạo tài khoản #2 / receptionist', '2026-11-09 16:18:49'),
(4, 1, 'booking_status', 'Đơn #8: pending -> confirmed', '2026-11-12 19:11:39'),
(5, 1, 'booking_status', 'Đơn #8: confirmed -> checked_in', '2026-11-12 19:11:48'),
(6, 1, 'payment', 'Đơn #8: 6.400.000 ₫ / bank', '2026-11-12 19:12:13'),
(7, 1, 'service.add', 'Đơn #8: thêm dòng dịch vụ #1', '2026-11-12 19:12:33'),
(8, 1, 'service.add', 'Đơn #8: thêm dòng dịch vụ #2', '2026-11-12 19:12:38'),
(9, 1, 'service.void', 'Đơn #8: hủy dòng dịch vụ #2. Lý do: Khách chọn nhầm', '2026-11-12 19:12:57'),
(10, 1, 'booking_status', 'Đơn #6: pending -> cancelled', '2026-11-12 19:13:59'),
(11, 2, 'booking_status', 'Đơn #5: pending -> cancelled', '2026-11-12 19:15:23'),
(12, 2, 'booking_status', 'Đơn #4: pending -> cancelled', '2026-11-12 19:16:14'),
(13, 2, 'payment', 'Đơn #1: 1.800.000 ₫ / cash', '2026-11-12 19:23:47'),
(14, 2, 'booking_status', 'Đơn #1: checked_in -> completed', '2026-11-12 19:23:51'),
(15, 2, 'booking_status', 'Đơn #3: pending -> cancelled', '2026-11-12 19:34:24');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `bookings`
--

CREATE TABLE `bookings` (
  `id` int(10) UNSIGNED NOT NULL,
  `code` char(32) NOT NULL,
  `request_key` char(32) NOT NULL,
  `room_type_id` int(10) UNSIGNED NOT NULL,
  `guest_name` varchar(100) NOT NULL,
  `email` varchar(190) NOT NULL,
  `phone` varchar(25) NOT NULL,
  `check_in` date NOT NULL,
  `check_out` date NOT NULL,
  `quantity` int(10) UNSIGNED NOT NULL,
  `guests` int(10) UNSIGNED NOT NULL,
  `nightly_price` int(10) UNSIGNED NOT NULL,
  `total_price` bigint(20) UNSIGNED NOT NULL,
  `note` varchar(1000) NOT NULL DEFAULT '',
  `status` enum('pending','confirmed','checked_in','completed','cancelled') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `source` enum('online','reception') NOT NULL DEFAULT 'online',
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `actual_check_in` datetime DEFAULT NULL,
  `actual_check_out` datetime DEFAULT NULL,
  `customer_id` int(10) UNSIGNED DEFAULT NULL,
  `promotion_id` int(10) UNSIGNED DEFAULT NULL,
  `discount_amount` bigint(20) UNSIGNED NOT NULL DEFAULT 0
) ;

--
-- Đang đổ dữ liệu cho bảng `bookings`
--

INSERT INTO `bookings` (`id`, `code`, `request_key`, `room_type_id`, `guest_name`, `email`, `phone`, `check_in`, `check_out`, `quantity`, `guests`, `nightly_price`, `total_price`, `note`, `status`, `created_at`, `updated_at`, `source`, `created_by`, `actual_check_in`, `actual_check_out`, `customer_id`, `promotion_id`, `discount_amount`) VALUES
(1, 'dc0b41ec10052f83b466b27306d0453d', '3364120e79a7627f75bbcdb7725ad224', 1, 'Hoàng Trung Kiên', 'hoangkienls1106@gmail.com', '0976121844', '2026-11-09', '2026-11-10', 1, 2, 1800000, 1800000, '', 'completed', '2026-11-09 16:13:25', '2026-11-12 19:23:51', 'online', NULL, '2026-11-09 23:17:59', '2026-11-13 02:23:51', NULL, NULL, 0),
(2, '7be9140358581d61d2409267165e0d53', '95a2592d38929d6a18fa35a507cd21bc', 1, 'Hoàng Trung Kiên', 'hoangkienls1106@gmail.com', '0976121844', '2026-11-09', '2026-11-10', 1, 2, 1800000, 1800000, '', 'pending', '2026-11-09 16:41:42', '2026-11-09 16:41:42', 'online', NULL, NULL, NULL, 1, NULL, 0),
(3, 'eba5b82906c3d4921fa40e3417e1c495', 'b48e9a605de51c9174d959f87abb5104', 2, 'Hoàng Trung Kiên', 'hoangkienls1106@gmail.com', '0976121844', '2026-11-11', '2026-11-12', 1, 2, 2900000, 2900000, '', 'cancelled', '2026-11-10 18:47:00', '2026-11-12 19:34:24', 'online', NULL, NULL, NULL, 1, NULL, 0),
(4, '7c05a0c4f853613851491fcb15ec26fc', '65ae2307acf48655ceb33f919da6a032', 3, 'Hoàng Trung Kiên', 'hoangkienls1106@gmail.com', '0976121844', '2026-11-11', '2026-11-12', 1, 2, 3600000, 3600000, '', 'cancelled', '2026-11-10 18:48:39', '2026-11-12 19:16:14', 'online', NULL, NULL, NULL, 1, NULL, 0),
(5, 'b3d2bbbab4eb55a6b066300c26eed19d', '63820131f0d314502378ffb88f141c53', 1, 'Hoàng Trung Kiên', 'hoangkienls1106@gmail.com', '0976121844', '2026-11-14', '2026-11-16', 1, 2, 1800000, 3600000, '', 'cancelled', '2026-11-10 18:59:07', '2026-11-12 19:15:23', 'online', NULL, NULL, NULL, 1, NULL, 0),
(6, '83eaacae4e87e05cc1f4daef569e9557', 'ad052784d423e2cc3361aa9a2617bff1', 1, 'Hoàng Trung Kiên', 'hoangkienls1106@gmail.com', '0976121844', '2026-11-12', '2026-11-15', 1, 2, 1800000, 5400000, '', 'cancelled', '2026-11-10 20:36:11', '2026-11-12 19:13:59', 'online', NULL, NULL, NULL, 1, NULL, 0),
(8, '12c3c6c34fb9607902d7e67f75856cf3', 'd2ea2ef17888d6955e097da2f6f398af', 4, 'Đào Mai Linh', 'linhgay@gmail.com', '0912345678', '2026-11-13', '2026-11-14', 2, 2, 3200000, 6400000, '', 'checked_in', '2026-11-12 19:10:36', '2026-11-12 19:11:48', 'online', NULL, '2026-11-13 02:11:48', NULL, NULL, NULL, 0);

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `booking_change_requests`
--

CREATE TABLE `booking_change_requests` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `booking_id` int(10) UNSIGNED NOT NULL,
  `customer_id` int(10) UNSIGNED NOT NULL,
  `requested_room_type_id` int(10) UNSIGNED NOT NULL,
  `requested_check_in` date NOT NULL,
  `requested_check_out` date NOT NULL,
  `quoted_nightly_price` int(10) UNSIGNED NOT NULL,
  `quoted_total_price` bigint(20) UNSIGNED NOT NULL,
  `original_total_price` bigint(20) UNSIGNED NOT NULL,
  `booking_signature` char(64) NOT NULL,
  `reason` varchar(1000) NOT NULL DEFAULT '',
  `status` enum('pending','approved','rejected','withdrawn') NOT NULL DEFAULT 'pending',
  `staff_response` varchar(1000) NOT NULL DEFAULT '',
  `reviewed_by` int(10) UNSIGNED DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `booking_change_requests`
--

INSERT INTO `booking_change_requests` (`id`, `booking_id`, `customer_id`, `requested_room_type_id`, `requested_check_in`, `requested_check_out`, `quoted_nightly_price`, `quoted_total_price`, `original_total_price`, `booking_signature`, `reason`, `status`, `staff_response`, `reviewed_by`, `reviewed_at`, `created_at`) VALUES
(1, 5, 1, 2, '2026-11-14', '2026-11-16', 2900000, 5800000, 3600000, 'ae6e30e402c19daf9a94be34681abd435db36fe021dc9f6f7230b7195ebad6e9', 'Tôi là gay', 'pending', '', NULL, NULL, '2026-11-10 20:24:23');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `booking_member_benefits`
--

CREATE TABLE `booking_member_benefits` (
  `booking_id` int(10) UNSIGNED NOT NULL,
  `customer_id` int(10) UNSIGNED NOT NULL,
  `tier_name` varchar(50) NOT NULL,
  `benefit_name` varchar(190) NOT NULL,
  `points_at_checkin` int(10) UNSIGNED NOT NULL,
  `granted_at` datetime NOT NULL DEFAULT current_timestamp(),
  `delivered_at` datetime DEFAULT NULL,
  `delivered_by` int(10) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `booking_reviews`
--

CREATE TABLE `booking_reviews` (
  `booking_id` int(10) UNSIGNED NOT NULL,
  `customer_id` int(10) UNSIGNED NOT NULL,
  `rating` tinyint(3) UNSIGNED NOT NULL,
  `comment` varchar(2000) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `admin_reply` varchar(2000) DEFAULT NULL,
  `replied_by` int(10) UNSIGNED DEFAULT NULL,
  `replied_at` datetime DEFAULT NULL
) ;

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `booking_services`
--

CREATE TABLE `booking_services` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `booking_id` int(10) UNSIGNED NOT NULL,
  `service_id` int(10) UNSIGNED NOT NULL,
  `service_name` varchar(100) NOT NULL,
  `unit` varchar(30) NOT NULL,
  `unit_price` int(10) UNSIGNED NOT NULL,
  `quantity` int(10) UNSIGNED NOT NULL,
  `note` varchar(500) NOT NULL DEFAULT '',
  `request_key` char(32) NOT NULL,
  `created_by` int(10) UNSIGNED NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `voided_at` datetime DEFAULT NULL,
  `voided_by` int(10) UNSIGNED DEFAULT NULL,
  `void_reason` varchar(500) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `booking_services`
--

INSERT INTO `booking_services` (`id`, `booking_id`, `service_id`, `service_name`, `unit`, `unit_price`, `quantity`, `note`, `request_key`, `created_by`, `created_at`, `voided_at`, `voided_by`, `void_reason`) VALUES
(1, 8, 2, 'Đưa đón sân bay Nội Bài hai chiều', 'gói khứ hồi', 3600000, 1, '', '4de6544997bc827adb6f76515bc3ba60', 1, '2026-11-12 19:12:33', NULL, NULL, ''),
(2, 8, 5, 'Set-up phòng trăng mật / kỷ niệm', 'gói', 4800000, 1, '', '671e652fafda8d867a4ba20279e5d6ab', 1, '2026-11-12 19:12:38', '2026-11-13 02:12:57', 1, 'Khách chọn nhầm');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `booking_transfers`
--

CREATE TABLE `booking_transfers` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `booking_id` int(10) UNSIGNED NOT NULL,
  `customer_id` int(10) UNSIGNED NOT NULL,
  `purpose` enum('deposit','balance') NOT NULL,
  `amount` bigint(20) UNSIGNED NOT NULL,
  `transfer_code` varchar(24) NOT NULL,
  `status` enum('awaiting','reported','confirmed','rejected','cancelled') NOT NULL DEFAULT 'awaiting',
  `bank_reference` varchar(100) DEFAULT NULL,
  `staff_note` varchar(500) NOT NULL DEFAULT '',
  `reviewed_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `reported_at` datetime DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Đang đổ dữ liệu cho bảng `booking_transfers`
--

INSERT INTO `booking_transfers` (`id`, `booking_id`, `customer_id`, `purpose`, `amount`, `transfer_code`, `status`, `bank_reference`, `staff_note`, `reviewed_by`, `created_at`, `reported_at`, `reviewed_at`) VALUES
(1, 6, 1, 'deposit', 1620000, 'OIBAB6174A716B4A', 'cancelled', NULL, '', NULL, '2026-11-11 03:36:39', NULL, NULL);

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `customers`
--

CREATE TABLE `customers` (
  `id` int(10) UNSIGNED NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(190) NOT NULL,
  `phone` varchar(25) NOT NULL DEFAULT '',
  `password_hash` varchar(255) NOT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `session_version` int(10) UNSIGNED NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `customers`
--

INSERT INTO `customers` (`id`, `full_name`, `email`, `phone`, `password_hash`, `active`, `session_version`, `created_at`) VALUES
(1, 'Hoàng Trung Kiên', 'hoangkienls1106@gmail.com', '0976121844', '$2y$10$xcMq/ILcFu.zShNUs3CZm.O.o4gO9nETLU3cNSFj/AGC7ZqH.lXiy', 1, 1, '2026-11-09 16:10:46');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `hotel_rooms`
--

CREATE TABLE `hotel_rooms` (
  `id` int(10) UNSIGNED NOT NULL,
  `room_type_id` int(10) UNSIGNED NOT NULL,
  `number` varchar(20) NOT NULL,
  `housekeeping` enum('ready','dirty','maintenance') NOT NULL DEFAULT 'ready',
  `active` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `hotel_rooms`
--

INSERT INTO `hotel_rooms` (`id`, `room_type_id`, `number`, `housekeeping`, `active`) VALUES
(1, 1, '1001', 'dirty', 1),
(2, 2, '2001', 'ready', 1),
(3, 3, '3001', 'ready', 1),
(4, 1, '1002', 'ready', 1),
(5, 2, '2002', 'ready', 1),
(6, 3, '3002', 'ready', 1),
(7, 1, '1003', 'ready', 1),
(8, 2, '2003', 'ready', 1),
(9, 3, '3003', 'ready', 1),
(10, 1, '1004', 'ready', 1),
(11, 2, '2004', 'ready', 1),
(12, 1, '1005', 'ready', 1),
(13, 1, '1006', 'ready', 1),
(14, 1, '1007', 'ready', 1),
(15, 1, '1008', 'ready', 1),
(16, 4, 'CS-201', 'ready', 1),
(17, 4, 'CS-202', 'ready', 1),
(18, 5, 'EP-401', 'ready', 1),
(19, 5, 'EP-402', 'ready', 1),
(20, 6, 'FC-301', 'ready', 1),
(21, 6, 'FC-302', 'ready', 1),
(22, 7, 'HS-501', 'ready', 1),
(23, 7, 'HS-502', 'ready', 1);

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `login_attempts`
--

CREATE TABLE `login_attempts` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `ip` varchar(45) NOT NULL,
  `attempted_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `payments`
--

CREATE TABLE `payments` (
  `id` int(10) UNSIGNED NOT NULL,
  `booking_id` int(10) UNSIGNED NOT NULL,
  `staff_id` int(10) UNSIGNED NOT NULL,
  `kind` enum('payment','refund') NOT NULL,
  `amount` bigint(20) UNSIGNED NOT NULL,
  `method` enum('cash','bank') NOT NULL,
  `reference` varchar(190) NOT NULL DEFAULT '',
  `request_key` char(32) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ;

--
-- Đang đổ dữ liệu cho bảng `payments`
--

INSERT INTO `payments` (`id`, `booking_id`, `staff_id`, `kind`, `amount`, `method`, `reference`, `request_key`, `created_at`) VALUES
(1, 8, 1, 'payment', 6400000, 'bank', '', '87e760aaa76cc9949969271bc1777423', '2026-11-12 19:12:13'),
(2, 1, 2, 'payment', 1800000, 'cash', '', 'c774c79af4f3d3e646a763254dc848de', '2026-11-12 19:23:47');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `promotions`
--

CREATE TABLE `promotions` (
  `id` int(10) UNSIGNED NOT NULL,
  `code` varchar(30) NOT NULL,
  `title` varchar(120) NOT NULL,
  `discount_percent` tinyint(3) UNSIGNED NOT NULL,
  `min_room_total` bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  `starts_on` date NOT NULL,
  `ends_on` date NOT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `room_assignments`
--

CREATE TABLE `room_assignments` (
  `booking_id` int(10) UNSIGNED NOT NULL,
  `room_id` int(10) UNSIGNED NOT NULL,
  `assigned_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `room_assignments`
--

INSERT INTO `room_assignments` (`booking_id`, `room_id`, `assigned_at`) VALUES
(1, 1, '2026-11-09 16:17:59'),
(8, 16, '2026-11-12 19:11:48'),
(8, 17, '2026-11-12 19:11:48');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `room_types`
--

CREATE TABLE `room_types` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(100) NOT NULL,
  `subtitle` varchar(150) NOT NULL,
  `description` text NOT NULL,
  `area` int(10) UNSIGNED NOT NULL,
  `capacity` int(10) UNSIGNED NOT NULL,
  `bed` varchar(100) NOT NULL,
  `price` int(10) UNSIGNED NOT NULL,
  `inventory` int(10) UNSIGNED NOT NULL,
  `image` varchar(100) NOT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `amenities` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `room_types`
--

INSERT INTO `room_types` (`id`, `name`, `subtitle`, `description`, `area`, `capacity`, `bed`, `price`, `inventory`, `image`, `active`, `amenities`) VALUES
(1, 'Ivory Deluxe', 'Tinh giản mà khác biệt', 'Sắc ngà dịu nhẹ, chất liệu gỗ ấm và ánh sáng tự nhiên tạo nên chốn nghỉ thanh lịch. Phòng có khu vực làm việc, phòng tắm riêng và bộ tiện nghi đầy đủ.', 28, 2, '1 giường King hoặc 2 giường đơn', 4800000, 8, 'deluxe.jpg', 1, NULL),
(2, 'Onyx Suite', 'Dấu ấn của riêng bạn', 'Không gian rộng rãi với khu vực tiếp khách riêng, tông màu trầm sang trọng và phòng tắm có bồn. Dành cho một kỳ nghỉ chậm rãi, riêng tư giữa lòng thành phố.', 42, 2, '1 giường King', 9800000, 4, 'suite.jpg', 1, NULL),
(3, 'Ivory Family', 'Những khoảnh khắc bên nhau', 'Một không gian thoải mái để cả gia đình cùng nghỉ ngơi. Hai giường lớn, góc ngồi thư giãn và tiện nghi chu đáo cho hành trình của bốn người.', 48, 4, '2 giường Queen', 8600000, 3, 'family.jpg', 1, NULL),
(4, 'Classic Studio', 'Chốn nghỉ riêng cho người đi một mình', 'Studio nhỏ gọn, hiện đại dành cho một khách, với giường đơn, góc làm việc và phòng tắm riêng. Phù hợp cho khách du lịch cá nhân khám phá Hà Nội.', 22, 1, '1 giường đơn', 3200000, 2, 'classic-studio.png', 1, 'Phòng dành cho một khách\nGóc làm việc riêng\nPhòng tắm riêng\nWi-Fi miễn phí\nĐiều hòa\nBữa sáng tại nhà hàng'),
(5, 'Executive Premier Room', 'Không gian làm việc cho chuyến công tác dài ngày', 'Phòng dành cho khách công tác, có bàn làm việc rộng, ghế công thái học và khu vực nghỉ ngơi riêng. Thuận tiện cho các buổi họp trực tuyến và những chuyến lưu trú dài ngày tại Hà Nội.', 36, 2, '1 giường King', 6900000, 2, 'executive-premier.png', 1, 'Bàn làm việc rộng\nGhế làm việc công thái học\nĐèn đọc sách và ổ cắm tại bàn\nWi-Fi miễn phí\nKét an toàn\nBữa sáng tại nhà hàng'),
(6, 'Ivory Family Connecting Room', 'Hai phòng ngủ thông nhau cho gia đình', 'Căn gồm hai phòng ngủ có cửa thông nhau, một giường King và hai giường Twin. Phù hợp cho gia đình hoặc nhóm bạn 4–5 người. Nhóm 5 khách vui lòng xác nhận cách bố trí chỗ ngủ với lễ tân.', 68, 5, '1 giường King + 2 giường Twin', 12800000, 2, 'ivory-family-connecting.png', 1, 'Hai phòng ngủ thông nhau\nMột giường King và hai giường Twin\nĐặt nguyên căn cho một nhóm khách\nWi-Fi miễn phí\nĐiều hòa và Smart TV\nBữa sáng tại nhà hàng'),
(7, 'The Onyx Heritage Suite', 'Ban công riêng giữa lòng phố cổ', 'Suite dành cho hai người, lấy cảm hứng từ những dinh thự phố cổ Hà Nội. Ban công riêng hướng phố đi bộ hoặc Hồ Gươm tùy vị trí phòng. Không gian tiếp khách và bồn tắm nằm riêng phù hợp cho kỳ nghỉ của các cặp đôi.', 55, 2, '1 giường King', 18900000, 2, 'onyx-heritage.png', 1, 'Ban công riêng hướng phố đi bộ hoặc Hồ Gươm\nBồn tắm nằm riêng\nKhu vực tiếp khách\nWi-Fi miễn phí\nĐiều hòa và Smart TV\nBữa sáng tại nhà hàng');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `schema_migrations`
--

CREATE TABLE `schema_migrations` (
  `version` int(11) NOT NULL,
  `applied_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `schema_migrations`
--

INSERT INTO `schema_migrations` (`version`, `applied_at`) VALUES
(2, '2026-11-09 15:34:53');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `services`
--

CREATE TABLE `services` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(100) NOT NULL,
  `unit` varchar(30) NOT NULL,
  `price` int(10) UNSIGNED NOT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `services`
--

INSERT INTO `services` (`id`, `name`, `unit`, `price`, `active`, `created_at`) VALUES
(1, 'Bữa sáng gọi món tại phòng', 'suất', 690000, 1, '2026-11-12 16:47:09'),
(2, 'Đưa đón sân bay Nội Bài hai chiều', 'gói khứ hồi', 3600000, 1, '2026-11-12 16:47:09'),
(3, 'Giặt ủi lấy nhanh trong ngày', 'kg', 320000, 1, '2026-11-12 16:47:09'),
(4, 'Nhận phòng sớm (Early Check-in)', 'phòng/lần', 1500000, 1, '2026-11-12 16:47:09'),
(5, 'Set-up phòng trăng mật / kỷ niệm', 'gói', 4800000, 1, '2026-11-12 16:47:09'),
(6, 'Trả phòng muộn (Late Check-out)', 'phòng/lần', 1800000, 1, '2026-11-12 16:47:09'),
(7, 'Trải nghiệm Spa & Xông hơi thảo mộc', 'khách', 2900000, 1, '2026-11-12 16:47:09');

--
-- Chỉ mục cho các bảng đã đổ
--

--
-- Chỉ mục cho bảng `admins`
--
ALTER TABLE `admins`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Chỉ mục cho bảng `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `staff_id` (`staff_id`);

--
-- Chỉ mục cho bảng `bookings`
--
ALTER TABLE `bookings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`),
  ADD UNIQUE KEY `request_key` (`request_key`),
  ADD KEY `availability_idx` (`room_type_id`,`status`,`check_in`,`check_out`),
  ADD KEY `idx_bookings_customer` (`customer_id`),
  ADD KEY `fk_bookings_promotion` (`promotion_id`);

--
-- Chỉ mục cho bảng `booking_change_requests`
--
ALTER TABLE `booking_change_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_change_customer` (`customer_id`),
  ADD KEY `fk_change_room_type` (`requested_room_type_id`),
  ADD KEY `fk_change_reviewer` (`reviewed_by`),
  ADD KEY `idx_change_booking_status` (`booking_id`,`status`),
  ADD KEY `idx_change_status_created` (`status`,`created_at`);

--
-- Chỉ mục cho bảng `booking_member_benefits`
--
ALTER TABLE `booking_member_benefits`
  ADD PRIMARY KEY (`booking_id`),
  ADD KEY `fk_member_benefit_staff` (`delivered_by`),
  ADD KEY `idx_member_benefit_customer` (`customer_id`,`granted_at`);

--
-- Chỉ mục cho bảng `booking_reviews`
--
ALTER TABLE `booking_reviews`
  ADD PRIMARY KEY (`booking_id`),
  ADD KEY `fk_review_customer` (`customer_id`),
  ADD KEY `fk_review_admin` (`replied_by`),
  ADD KEY `idx_review_reply_date` (`replied_at`,`created_at`);

--
-- Chỉ mục cho bảng `booking_services`
--
ALTER TABLE `booking_services`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `request_key` (`request_key`),
  ADD KEY `service_id` (`service_id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `voided_by` (`voided_by`),
  ADD KEY `booking_services_booking_idx` (`booking_id`,`voided_at`);

--
-- Chỉ mục cho bảng `booking_transfers`
--
ALTER TABLE `booking_transfers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `transfer_code` (`transfer_code`),
  ADD UNIQUE KEY `bank_reference` (`bank_reference`),
  ADD KEY `customer_id` (`customer_id`),
  ADD KEY `reviewed_by` (`reviewed_by`),
  ADD KEY `idx_transfer_booking` (`booking_id`,`status`),
  ADD KEY `idx_transfer_queue` (`status`,`id`);

--
-- Chỉ mục cho bảng `customers`
--
ALTER TABLE `customers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Chỉ mục cho bảng `hotel_rooms`
--
ALTER TABLE `hotel_rooms`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `number` (`number`),
  ADD KEY `room_type_idx` (`room_type_id`,`active`,`housekeeping`);

--
-- Chỉ mục cho bảng `login_attempts`
--
ALTER TABLE `login_attempts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `attempts_idx` (`ip`,`attempted_at`);

--
-- Chỉ mục cho bảng `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `request_key` (`request_key`),
  ADD KEY `booking_id` (`booking_id`),
  ADD KEY `staff_id` (`staff_id`);

--
-- Chỉ mục cho bảng `promotions`
--
ALTER TABLE `promotions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`),
  ADD KEY `idx_promotions_dates` (`active`,`starts_on`,`ends_on`);

--
-- Chỉ mục cho bảng `room_assignments`
--
ALTER TABLE `room_assignments`
  ADD PRIMARY KEY (`booking_id`,`room_id`),
  ADD KEY `room_id` (`room_id`);

--
-- Chỉ mục cho bảng `room_types`
--
ALTER TABLE `room_types`
  ADD PRIMARY KEY (`id`);

--
-- Chỉ mục cho bảng `schema_migrations`
--
ALTER TABLE `schema_migrations`
  ADD PRIMARY KEY (`version`);

--
-- Chỉ mục cho bảng `services`
--
ALTER TABLE `services`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT cho các bảng đã đổ
--

--
-- AUTO_INCREMENT cho bảng `admins`
--
ALTER TABLE `admins`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT cho bảng `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT cho bảng `bookings`
--
ALTER TABLE `bookings`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT cho bảng `booking_change_requests`
--
ALTER TABLE `booking_change_requests`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT cho bảng `booking_services`
--
ALTER TABLE `booking_services`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT cho bảng `booking_transfers`
--
ALTER TABLE `booking_transfers`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT cho bảng `customers`
--
ALTER TABLE `customers`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT cho bảng `hotel_rooms`
--
ALTER TABLE `hotel_rooms`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- AUTO_INCREMENT cho bảng `login_attempts`
--
ALTER TABLE `login_attempts`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT cho bảng `payments`
--
ALTER TABLE `payments`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT cho bảng `promotions`
--
ALTER TABLE `promotions`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT cho bảng `room_types`
--
ALTER TABLE `room_types`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT cho bảng `services`
--
ALTER TABLE `services`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- Các ràng buộc cho các bảng đã đổ
--

--
-- Các ràng buộc cho bảng `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD CONSTRAINT `audit_logs_ibfk_1` FOREIGN KEY (`staff_id`) REFERENCES `admins` (`id`);

--
-- Các ràng buộc cho bảng `bookings`
--
ALTER TABLE `bookings`
  ADD CONSTRAINT `bookings_ibfk_1` FOREIGN KEY (`room_type_id`) REFERENCES `room_types` (`id`),
  ADD CONSTRAINT `fk_bookings_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_bookings_promotion` FOREIGN KEY (`promotion_id`) REFERENCES `promotions` (`id`);

--
-- Các ràng buộc cho bảng `booking_change_requests`
--
ALTER TABLE `booking_change_requests`
  ADD CONSTRAINT `fk_change_booking` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`),
  ADD CONSTRAINT `fk_change_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`),
  ADD CONSTRAINT `fk_change_reviewer` FOREIGN KEY (`reviewed_by`) REFERENCES `admins` (`id`),
  ADD CONSTRAINT `fk_change_room_type` FOREIGN KEY (`requested_room_type_id`) REFERENCES `room_types` (`id`);

--
-- Các ràng buộc cho bảng `booking_member_benefits`
--
ALTER TABLE `booking_member_benefits`
  ADD CONSTRAINT `fk_member_benefit_booking` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`),
  ADD CONSTRAINT `fk_member_benefit_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`),
  ADD CONSTRAINT `fk_member_benefit_staff` FOREIGN KEY (`delivered_by`) REFERENCES `admins` (`id`);

--
-- Các ràng buộc cho bảng `booking_reviews`
--
ALTER TABLE `booking_reviews`
  ADD CONSTRAINT `fk_review_admin` FOREIGN KEY (`replied_by`) REFERENCES `admins` (`id`),
  ADD CONSTRAINT `fk_review_booking` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`),
  ADD CONSTRAINT `fk_review_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`);

--
-- Các ràng buộc cho bảng `booking_services`
--
ALTER TABLE `booking_services`
  ADD CONSTRAINT `booking_services_ibfk_1` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`),
  ADD CONSTRAINT `booking_services_ibfk_2` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`),
  ADD CONSTRAINT `booking_services_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `admins` (`id`),
  ADD CONSTRAINT `booking_services_ibfk_4` FOREIGN KEY (`voided_by`) REFERENCES `admins` (`id`);

--
-- Các ràng buộc cho bảng `booking_transfers`
--
ALTER TABLE `booking_transfers`
  ADD CONSTRAINT `booking_transfers_ibfk_1` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`),
  ADD CONSTRAINT `booking_transfers_ibfk_2` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`),
  ADD CONSTRAINT `booking_transfers_ibfk_3` FOREIGN KEY (`reviewed_by`) REFERENCES `admins` (`id`);

--
-- Các ràng buộc cho bảng `hotel_rooms`
--
ALTER TABLE `hotel_rooms`
  ADD CONSTRAINT `hotel_rooms_ibfk_1` FOREIGN KEY (`room_type_id`) REFERENCES `room_types` (`id`);

--
-- Các ràng buộc cho bảng `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`),
  ADD CONSTRAINT `payments_ibfk_2` FOREIGN KEY (`staff_id`) REFERENCES `admins` (`id`);

--
-- Các ràng buộc cho bảng `room_assignments`
--
ALTER TABLE `room_assignments`
  ADD CONSTRAINT `room_assignments_ibfk_1` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`),
  ADD CONSTRAINT `room_assignments_ibfk_2` FOREIGN KEY (`room_id`) REFERENCES `hotel_rooms` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
