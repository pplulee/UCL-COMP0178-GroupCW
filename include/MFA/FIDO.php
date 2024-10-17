<?php
declare (strict_types=1);

namespace service\MFA;

use Exception;
use model\MFACredential;
use model\User;
use Webauthn\AuthenticatorAssertionResponse;
use Webauthn\AuthenticatorAttestationResponse;
use Webauthn\AuthenticatorSelectionCriteria;
use Webauthn\PublicKeyCredential;
use Webauthn\PublicKeyCredentialCreationOptions;
use Webauthn\PublicKeyCredentialDescriptor;
use Webauthn\PublicKeyCredentialRequestOptions;
use Webauthn\PublicKeyCredentialSource;

class FIDO
{
    public static function fidoRegisterRequest(User $user): string
    {
        global $cache;
        $rpEntity = WebAuthn::generateRPEntity();
        $userEntity = WebAuthn::generateUserEntity($user);
        $authenticatorSelectionCriteria = AuthenticatorSelectionCriteria::create();
        $publicKeyCredentialCreationOptions =
            PublicKeyCredentialCreationOptions::create(
                $rpEntity,
                $userEntity,
                random_bytes(32),
                pubKeyCredParams: WebAuthn::getPublicKeyCredentialParametersList(),
                authenticatorSelection: $authenticatorSelectionCriteria,
                attestation: PublicKeyCredentialCreationOptions::ATTESTATION_CONVEYANCE_PREFERENCE_NONE,
                timeout: WebAuthn::$timeout,
            );
        $serializer = WebAuthn::getSerializer();
        $jsonObject = $serializer->serialize($publicKeyCredentialCreationOptions, 'json');
        $cache->set('fido_register_' . session_id(), $jsonObject, 300);
        return $jsonObject;
    }

    public static function fidoRegisterHandle(User $user, array $data): array
    {
        global $cache;
        $serializer = WebAuthn::getSerializer();
        try {
            $publicKeyCredential = $serializer->deserialize(
                json_encode($data),
                PublicKeyCredential::class,
                'json'
            );
        } catch (Exception $e) {
            return ['ret' => 0, 'msg' => $e->getMessage()];
        }
        if (! isset($publicKeyCredential->response) || ! $publicKeyCredential->response instanceof AuthenticatorAttestationResponse) {
            return ['ret' => 0, 'msg' => 'Wrong credential type'];
        }

        $publicKeyCredentialCreationOptions = $serializer->deserialize(
            $cache->get('fido_register_' . session_id()),
            PublicKeyCredentialCreationOptions::class,
            'json'
        );

        try {
            $authenticatorAttestationResponseValidator = WebAuthn::getAuthenticatorAttestationResponseValidator();
            $publicKeyCredentialSource = $authenticatorAttestationResponseValidator->check(
                $publicKeyCredential->response,
                $publicKeyCredentialCreationOptions,
                $_SERVER['HTTP_HOST']
            );
        } catch (Exception) {
            return ['ret' => 0, 'msg' => 'Verification failed'];
        }
        $jsonStr = WebAuthn::getSerializer()->serialize($publicKeyCredentialSource, 'json');
        $jsonObject = json_decode($jsonStr);
        $mfaCredential = new MFACredential();
        $mfaCredential->userid = $user->id;
        $mfaCredential->rawid = $jsonObject->publicKeyCredentialId;
        $mfaCredential->body = $jsonStr;
        $mfaCredential->created_at = date('Y-m-d H:i:s');
        $mfaCredential->used_at = null;
        $mfaCredential->name = $data['name'] === '' ? null : $data['name'];
        $mfaCredential->type = 'fido';
        $mfaCredential->save();
        return ['ret' => 1, 'msg' => 'Device register success'];
    }

    public static function fidoAssertRequest(User $user): string
    {
        global $conn, $cache;
        $serializer = WebAuthn::getSerializer();
        $stmt = $conn->prepare("SELECT body FROM `mfa_credential` WHERE `userid` = :userid AND `type` = 'fido'");
        $stmt->execute(['userid' => $user->id]);
        $userCredentials = $stmt->fetchAll();
        $credentials = [];
        foreach ($userCredentials as $credential) {
            $credentials[] = $serializer->deserialize($credential['body'], PublicKeyCredentialSource::class, 'json');
        }
        $allowedCredentials = array_map(
            static function (PublicKeyCredentialSource $credential): PublicKeyCredentialDescriptor {
                return $credential->getPublicKeyCredentialDescriptor();
            },
            $credentials
        );
        $publicKeyCredentialRequestOptions = PublicKeyCredentialRequestOptions::create(
            random_bytes(32),
            rpId: $_SERVER['HTTP_HOST'],
            allowCredentials: $allowedCredentials,
            userVerification: 'discouraged',
            timeout: WebAuthn::$timeout,
        );
        $jsonObject = $serializer->serialize($publicKeyCredentialRequestOptions, 'json');
        $cache->set('fido_assertion_' . session_id(), $jsonObject, 300);
        return $jsonObject;
    }

    public static function fidoAssertHandle(User $user, array $data): array
    {
        global $conn, $cache;
        $serializer = WebAuthn::getSerializer();
        $publicKeyCredential = $serializer->deserialize(json_encode($data), PublicKeyCredential::class, 'json');
        if (! $publicKeyCredential->response instanceof AuthenticatorAssertionResponse) {
            return ['ret' => 0, 'msg' => 'Verification failed'];
        }
        $stmt = $conn->prepare("SELECT * FROM `mfa_credential` WHERE `rawid` = :rawid AND `userid` = :userid AND `type` = 'fido'");
        $stmt->execute(['rawid' => $data['id'], 'userid' => $user->id]);
        $publicKeyCredentialSource = $stmt->fetch();
        if (! $publicKeyCredentialSource) {
            return ['ret' => 0, 'msg' => 'No such credential'];
        }
        try {
            $publicKeyCredentialRequestOptions = $serializer->deserialize(
                $cache->get('fido_assertion_' . session_id()),
                PublicKeyCredentialRequestOptions::class,
                'json'
            );
            $authenticatorAssertionResponseValidator = WebAuthn::getAuthenticatorAssertionResponseValidator();
            $publicKeyCredentialSource_body = $serializer->deserialize($publicKeyCredentialSource['body'], PublicKeyCredentialSource::class, 'json');
            $result = $authenticatorAssertionResponseValidator->check(
                $publicKeyCredentialSource_body,
                $publicKeyCredential->response,
                $publicKeyCredentialRequestOptions,
                $_SERVER['HTTP_HOST'],
                $user->uuid,
            );
        } catch (Exception $e) {
            return ['ret' => 0, 'msg' => $e->getMessage()];
        }
        $stmt = $conn->prepare("UPDATE `mfa_credential` SET `used_at` = :used_at, `body` = :body WHERE `id` = :id");
        $stmt->execute([
            'used_at' => date('Y-m-d H:i:s'),
            'body' => $serializer->serialize($result, 'json'),
            'id' => $publicKeyCredentialSource['id']
        ]);
        return ['ret' => 1, 'msg' => 'Verification passed', 'user' => $user];
    }
}
