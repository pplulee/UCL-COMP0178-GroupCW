<?php

use service\MFA\WebAuthn;
use voku\helper\AntiXSS;

include "../include/common.php";

if ($_SESSION['logged_in'] === true) {
    exit();
}

header('Content-Type: application/json');
switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
        echo WebAuthn::challengeRequest();
        exit();
    case 'POST':
        $rawData = file_get_contents('php://input');
        $data = json_decode($rawData, true);
        $antiXss = new AntiXSS();
        $result = WebAuthn::challengeHandle($antiXss->xss_clean($data));
        if ($result['ret'] === 1) {
            $user = $result['user'];
            $_SESSION['logged_in'] = true;
            $_SESSION['user_id'] = $user->id;
            $_SESSION['role'] = $user->role;
            echo json_encode(['ret' => 1, 'msg' => 'Login successful', 'redir' => '/']);
        } else {
            echo json_encode($result);
        }
        exit();
    default:
        http_response_code(405);
        exit();
}
