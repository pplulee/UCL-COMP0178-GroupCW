<?php
switch ($_SERVER['REQUEST_METHOD']) {
    case 'POST':
        include "include/common.php";
        header('Content-Type: application/json');
        unset($_SESSION['logged_in']);
        unset($_SESSION['role']);
        unset($_SESSION['user_id']);
        setcookie('user', '', time(), '/');
        header('HX-Redirect: login.php');
        echo json_encode([
            'ret' => 1,
            'msg' => 'Logout successful'
        ]);
        exit();
    default:
        http_response_code(405);
        exit();
}
?>