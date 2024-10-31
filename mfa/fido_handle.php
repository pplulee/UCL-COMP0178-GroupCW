<?php

use model\User;
use service\MFA\FIDO;
use voku\helper\AntiXSS;

include "../include/common.php";

if ($_SESSION['logged_in'] === true) {
    exit();
}

header('Content-Type: application/json');
global $cache;
switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
        $userId = $cache->get('mfa_userid_' . session_id());
        if ($userId === null) {
            echo json_encode([
                'ret' => 0,
                'msg' => 'Session expired, please login again'
            ]);
        }
        $user = (new User())->fetch($userId);
        echo FIDO::fidoAssertRequest($user);
        exit();
    case 'POST':
        $userId = $cache->get('mfa_userid_' . session_id());
        if ($userId === null) {
            echo json_encode([
                'ret' => 0,
                'msg' => 'Session expired, please login again'
            ]);
        }
        $user = (new User())->fetch($userId);
        $rawData = file_get_contents('php://input');
        $data = json_decode($rawData, true);
        $antiXss = new AntiXSS();
        $result = FIDO::fidoAssertHandle($user, $antiXss->xss_clean($data));
        if ($result['ret'] === 1) {
            $user = $result['user'];
            $_SESSION['logged_in'] = true;
            $_SESSION['user_id'] = $user->id;
            $_SESSION['admin'] = $user->admin;
            if ($cache->get('mfa_rememberme_' . session_id()) === true) {
                setcookie('user', $user->email, time() + 2592000, '/');
                setcookie('uuid', $user->uuid, time() + 2592000, '/');
            }
            $cache->delete('mfa_userid_' . session_id());
            $cache->delete('mfa_rememberme_' . session_id());
            echo json_encode(['ret' => 1, 'msg' => 'Login successful', 'redir' => '/']);
        } else {
            echo json_encode($result);
        }
        exit();
    default:
        http_response_code(405);
        exit();
}
