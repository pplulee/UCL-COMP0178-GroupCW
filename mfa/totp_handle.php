<?php

use model\User;
use service\MFA\TOTP;

include "../include/common.php";

if ($_SESSION['logged_in'] === true) {
    exit();
}

header('Content-Type: application/json');
global $cache;
switch ($_SERVER['REQUEST_METHOD']) {
    case 'POST':
        global $cache;
        $userId = $cache->get('mfa_userid_' . session_id());
        if ($userId === null) {
            echo json_encode([
                'ret' => 0,
                'msg' => 'Session expired, please login again'
            ]);
        }
        $user = (new User())->fetch($userId);
        $rawData = file_get_contents('php://input');
        parse_str($rawData, $data);
        $result = TOTP::totpVerifyHandle($user, $data['code']);
        if ($result['ret'] === 1) {
            $_SESSION['logged_in'] = true;
            $_SESSION['user_id'] = $user->id;
            $_SESSION['role'] = $user->role;
            header('HX-Redirect: index.php');
            echo json_encode(['ret' => 1, 'msg' => 'Login successful']);
        } else {
            echo json_encode($result);
        }
        exit();
    default:
        http_response_code(405);
        exit();
}
