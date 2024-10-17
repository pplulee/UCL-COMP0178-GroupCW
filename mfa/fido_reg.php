<?php

use model\User;
use service\MFA\FIDO;
use voku\helper\AntiXSS;

include "../include/common.php";

if ($_SESSION['logged_in'] === false) {
    exit();
}

header('Content-Type: application/json');
$user = (new User())->fetch($_SESSION['user_id']);
switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
        echo FIDO::fidoRegisterRequest($user);
        exit();
    case 'POST':
        $rawData = file_get_contents('php://input');
        $data = json_decode($rawData, true);
        $antiXss = new AntiXSS();
        echo json_encode(FIDO::fidoRegisterHandle($user, $antiXss->xss_clean($data)));
        break;
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