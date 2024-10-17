<?php
declare (strict_types=1);

namespace service\MFA;

use Exception;
use model\MFACredential;
use model\User;
use Vectorface\GoogleAuthenticator;

class TOTP
{
    public static function totpRegisterRequest(User $user): array
    {
        global $conn, $cache;
        try {
            $stmt = $conn->prepare("SELECT * FROM `mfa_credential` WHERE `userid` = :userid AND `type` = 'totp'");
            $stmt->execute(['userid' => $user->id]);
            $mfaCredential = $stmt->fetch();
            if ($mfaCredential != null) {
                return ['ret' => 0, 'msg' => 'You have already registered TOTP'];
            }
            $ga = new GoogleAuthenticator();
            $token = $ga->createSecret(32);
            $cache->set('totp_register_' . session_id(), $token, 300);
            return ['ret' => 1, 'msg' => 'Request success', 'url' => self::getGaUrl($user, $token), 'token' => $token];
        } catch (Exception $e) {
            return ['ret' => 0, 'msg' => $e->getMessage()];
        }
    }

    public static function getGaUrl(User $user, string $token): string
    {
        return 'otpauth://totp/' . rawurlencode(env('app_name')) . ':' . rawurlencode($user->email) . '?secret=' . $token . '&issuer=' . rawurlencode(env('app_name'));
    }

    public static function totpRegisterHandle(User $user, string $code): array
    {
        global $cache;
        $token = $cache->get('totp_register_' . session_id());
        if ($token === false) {
            return ['ret' => 0, 'msg' => 'Request expired'];
        }
        $ga = new GoogleAuthenticator();
        if (! $ga->verifyCode($token, $code)) {
            return ['ret' => 0, 'msg' => 'Incorrect verification code'];
        }
        $mfaCredential = new MFACredential();
        $mfaCredential->userid = $user->id;
        $mfaCredential->name = 'TOTP';
        $mfaCredential->body = json_encode(['token' => $token]);
        $mfaCredential->type = 'totp';
        $mfaCredential->created_at = date('Y-m-d H:i:s');
        $mfaCredential->save();
        $cache->delete('totp_register_' . session_id());
        return ['ret' => 1, 'msg' => 'Register success'];
    }

    public static function totpVerifyHandle(User $user, string $code): array
    {
        global $conn;
        $ga = new GoogleAuthenticator();
        $stmt = $conn->prepare("SELECT * FROM `mfa_credential` WHERE `userid` = :userid AND `type` = 'totp'");
        $stmt->execute(['userid' => $user->id]);
        $mfaCredential = $stmt->fetch();
        if (! $mfaCredential) {
            return ['ret' => 0, 'msg' => 'You do not have TOTP enabled'];
        }
        $secret = json_decode($mfaCredential['body'], true)['token'] ?? '';
        return $ga->verifyCode($secret, $code) ? ['ret' => 1, 'msg' => 'Verification success'] : ['ret' => 0, 'msg' => 'Incorrect verification code'];
    }
}
