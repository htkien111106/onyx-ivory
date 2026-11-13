<?php require '../includes/bootstrap.php';if(!post()){http_response_code(405);exit('Chỉ dùng POST.');}check_csrf();$_SESSION=[];session_regenerate_id(true);go('admin/login.php');
