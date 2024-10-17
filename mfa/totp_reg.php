<?php

use model\User;
use service\MFA\TOTP;

include "../include/common.php";

if ($_SESSION['logged_in'] === false) {
    exit();
}

header('Content-Type: application/json');
$user = (new User())->fetch($_SESSION['user_id']);
switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
        echo json_encode(TOTP::totpRegisterRequest($user));
        exit();
    case 'POST':
        $rawData = file_get_contents('php://input');
        parse_str($rawData, $data);
        if (! isset($data['code'])) {
            echo json_encode([
                'ret' => 0,
                'msg' => 'Code cannot be empty'
            ]);
            exit();
        }
        $result = TOTP::totpRegisterHandle($user, $data['code']);
        if ($result['ret'] === 1) {
            header('HX-Refresh: true');
        }
        echo json_encode($result);
        exit();
    case 'DELETE':
        global $conn;
        $rawData = file_get_contents('php://input');
        parse_str($rawData, $data);
        $stmt = $conn->prepare("DELETE FROM mfa_credential WHERE id = :id AND userid = :userid RETURNING *");
        $stmt->execute([
            'id' => $data['id'],
            'userid' => $user->id
        ]);
        $device = $stmt->fetch();
        if ($device === false) {
            echo json_encode([
                'ret' => 0,
                'msg' => 'Device not found'
            ]);
        } else {
            header('HX-Refresh: true');
            echo json_encode([
                'ret' => 1,
                'msg' => 'Device deleted'
            ]);
        }
        exit();
    default:
        http_response_code(405);
        exit();
}