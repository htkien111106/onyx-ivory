<?php require '../includes/bootstrap.php';$user=admin_required();$title='Tổng quan & báo cáo';
$month=input('month',date('Y-m'));$dt=DateTimeImmutable::createFromFormat('!Y-m',$month);if(!$dt || $dt->format('Y-m')!==$month){$month=date('Y-m');$dt=new DateTimeImmutable(date('Y-m-01'));}
$start=$dt->format('Y-m-d');$end=$dt->modify('+1 month')->format('Y-m-d');
$cash=query("SELECT COALESCE(SUM(CASE WHEN kind='payment' THEN amount ELSE 0 END),0) collected,COALESCE(SUM(CASE WHEN kind='refund' THEN amount ELSE 0 END),0) refunded FROM payments WHERE created_at>=? AND created_at<?",[$start,$end])->fetch();
$stats=query("SELECT COUNT(*) total,COALESCE(SUM(status='pending'),0) pending,COALESCE(SUM(status='checked_in'),0) staying FROM bookings")->fetch();
$stock=(int)query("SELECT COUNT(*) FROM hotel_rooms WHERE active=1 AND housekeeping<>'maintenance'")->fetchColumn();
$occupied=(int)query("SELECT COUNT(DISTINCT a.room_id) FROM room_assignments a JOIN bookings b ON b.id=a.booking_id WHERE b.status='checked_in'")->fetchColumn();
$missing=(int)query("SELECT COUNT(*) FROM bookings b WHERE b.status='checked_in' AND (SELECT COUNT(*) FROM room_assignments a WHERE a.booking_id=b.id)<>b.quantity")->fetchColumn();
$rows=query("SELECT r.name,COUNT(b.id) total,COALESCE(SUM(CASE WHEN b.status<>'cancelled' THEN b.total_price ELSE 0 END),0) booked_value FROM room_types r LEFT JOIN bookings b ON b.room_type_id=r.id AND b.created_at>=? AND b.created_at<? GROUP BY r.id,r.name ORDER BY r.id",[$start,$end])->fetchAll();
$daily=query("SELECT DATE(created_at) day,COALESCE(SUM(CASE WHEN kind='payment' THEN amount ELSE -CAST(amount AS SIGNED) END),0) net FROM payments WHERE created_at>=? AND created_at<? GROUP BY DATE(created_at) ORDER BY day",[$start,$end])->fetchAll();
require __DIR__ . '/../includes/admin_views/index.php';
