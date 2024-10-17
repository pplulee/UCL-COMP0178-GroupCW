<?php

declare (strict_types=1);

namespace service\MFA;

use Cose\Algorithm\Manager;
use Cose\Algorithm\Signature\ECDSA;
use Cose\Algorithm\Signature\RSA;
use Cose\Algorithms;
use Exception;
use model\MFACredential;
use model\User;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\Serializer\SerializerInterface;
use Webauthn\AttestationStatement\AndroidKeyAttestationStatementSupport;
use Webauthn\AttestationStatement\AppleAttestationStatementSupport;
use Webauthn\AttestationStatement\AttestationStatementSupportManager;
use Webauthn\AttestationStatement\FidoU2FAttestationStatementSupport;
use Webauthn\AttestationStatement\NoneAttestationStatementSupport;
use Webauthn\AttestationStatement\PackedAttestationStatementSupport;
use Webauthn\AttestationStatement\TPMAttestationStatementSupport;
use Webauthn\AuthenticatorAssertionResponse;
use Webauthn\AuthenticatorAssertionResponseValidator;
use Webauthn\AuthenticatorAttestationResponse;
use Webauthn\AuthenticatorAttestationResponseValidator;
use Webauthn\AuthenticatorSelectionCriteria;
use Webauthn\CeremonyStep\CeremonyStepManagerFactory;
use Webauthn\Denormalizer\WebauthnSerializerFactory;
use Webauthn\PublicKeyCredential;
use Webauthn\PublicKeyCredentialCreationOptions;
use Webauthn\PublicKeyCredentialParameters;
use Webauthn\PublicKeyCredentialRequestOptions;
use Webauthn\PublicKeyCredentialRpEntity;
use Webauthn\PublicKeyCredentialSource;
use Webauthn\PublicKeyCredentialUserEntity;


class WebAuthn
{
    public static int $timeout = 30_000;

    public static function registerRequest(User $user): string
    {
        global $cache;
        $rpEntity = self::generateRPEntity();
        $userEntity = self::generateUserEntity($user);
        $authenticatorSelectionCriteria = AuthenticatorSelectionCriteria::create(
            userVerification: AuthenticatorSelectionCriteria::USER_VERIFICATION_REQUIREMENT_REQUIRED,
            residentKey: AuthenticatorSelectionCriteria::RESIDENT_KEY_REQUIREMENT_REQUIRED
        );
        $publicKeyCredentialCreationOptions =
            PublicKeyCredentialCreationOptions::create(
                $rpEntity,
                $userEntity,
                random_bytes(32),
                pubKeyCredParams: self::getPublicKeyCredentialParametersList(),
                authenticatorSelection: $authenticatorSelectionCriteria,
                attestation: PublicKeyCredentialCreationOptions::ATTESTATION_CONVEYANCE_PREFERENCE_NONE,
                timeout: self::$timeout,
            );
        $serializer = self::getSerializer();
        $jsonObject = $serializer->serialize($publicKeyCredentialCreationOptions, 'json');
        $cache->set('webauthn_register_' . session_id(), $jsonObject, 300);
        return $jsonObject;
    }

    public static function generateRPEntity(): PublicKeyCredentialRpEntity
    {
        return PublicKeyCredentialRpEntity::create(env('app_name'), $_SERVER['HTTP_HOST']);
    }

    public static function generateUserEntity(User $user): PublicKeyCredentialUserEntity
    {
        return PublicKeyCredentialUserEntity::create(
            $user->email,
            $user->uuid,
            $user->username
        );
    }

    public static function getPublicKeyCredentialParametersList(): array
    {
        return [
            PublicKeyCredentialParameters::create('public-key', Algorithms::COSE_ALGORITHM_ES256K),
            PublicKeyCredentialParameters::create('public-key', Algorithms::COSE_ALGORITHM_ES256),
            PublicKeyCredentialParameters::create('public-key', Algorithms::COSE_ALGORITHM_RS256),
            PublicKeyCredentialParameters::create('public-key', Algorithms::COSE_ALGORITHM_PS256),
            PublicKeyCredentialParameters::create('public-key', Algorithms::COSE_ALGORITHM_ED256),
        ];
    }

    public static function getSerializer(): SerializerInterface
    {
        $clock = new NativeClock();
        $coseAlgorithmManager = Manager::create();
        $coseAlgorithmManager->add(ECDSA\ES256::create());
        $coseAlgorithmManager->add(RSA\RS256::create());
        $attestationStatementSupportManager = AttestationStatementSupportManager::create();
        $attestationStatementSupportManager->add(NoneAttestationStatementSupport::create());
        $attestationStatementSupportManager->add(FidoU2FAttestationStatementSupport::create());
        $attestationStatementSupportManager->add(AppleAttestationStatementSupport::create());
        $attestationStatementSupportManager->add(AndroidKeyAttestationStatementSupport::create());
        $attestationStatementSupportManager->add(TPMAttestationStatementSupport::create($clock));
        $attestationStatementSupportManager->add(PackedAttestationStatementSupport::create($coseAlgorithmManager));
        $factory = new WebauthnSerializerFactory($attestationStatementSupportManager);
        return $factory->create();
    }

    public static function registerHandle(User $user, array $data): array
    {
        global $cache;
        $serializer = self::getSerializer();
        try {
            $publicKeyCredential = $serializer->deserialize(
                json_encode($data),
                PublicKeyCredential::class,
                'json'
            );
        } catch (Exception $e) {
            return ['ret' => 0, 'msg' => $e->getMessage()];
        }
        if (! $publicKeyCredential->response instanceof AuthenticatorAttestationResponse) {
            return ['ret' => 0, 'msg' => 'Wrong credential type'];
        }

        $publicKeyCredentialCreationOptions = $serializer->deserialize(
            $cache->get('webauthn_register_' . session_id()),
            PublicKeyCredentialCreationOptions::class,
            'json'
        );

        try {
            $authenticatorAttestationResponseValidator = self::getAuthenticatorAttestationResponseValidator();
            $publicKeyCredentialSource = $authenticatorAttestationResponseValidator->check(
                $publicKeyCredential->response,
                $publicKeyCredentialCreationOptions,
                $_SERVER['HTTP_HOST'],
            );
        } catch (Exception) {
            return ['ret' => 0, 'msg' => 'Verification failed'];
        }
        // save public key credential source
        $jsonStr = self::getSerializer()->serialize($publicKeyCredentialSource, 'json');
        $jsonObject = json_decode($jsonStr);
        $webauthn = new MFACredential();
        $webauthn->userid = $user->id;
        $webauthn->rawid = $jsonObject->publicKeyCredentialId;
        $webauthn->body = $jsonStr;
        $webauthn->created_at = date('Y-m-d H:i:s');
        $webauthn->used_at = null;
        $webauthn->name = $data['name'] === '' ? null : $data['name'];
        $webauthn->type = 'passkey';
        $webauthn->save();
        return ['ret' => 1, 'msg' => 'Device registered'];
    }

    public static function getAuthenticatorAttestationResponseValidator(): AuthenticatorAttestationResponseValidator
    {
        $csmFactory = new CeremonyStepManagerFactory();
        $creationCSM = $csmFactory->creationCeremony();
        return AuthenticatorAttestationResponseValidator::create(
            ceremonyStepManager: $creationCSM
        );
    }

    public static function challengeRequest(): string
    {
        global $cache;
        $publicKeyCredentialRequestOptions = self::getPublicKeyCredentialRequestOptions();
        $serializer = self::getSerializer();
        $jsonObject = $serializer->serialize($publicKeyCredentialRequestOptions, 'json');
        $cache->set('webauthn_assertion_' . session_id(), $jsonObject, 300);
        return $jsonObject;
    }

    public static function getPublicKeyCredentialRequestOptions(): PublicKeyCredentialRequestOptions
    {
        return PublicKeyCredentialRequestOptions::create(
            random_bytes(32),
            rpId: $_SERVER['HTTP_HOST'],
            userVerification: PublicKeyCredentialRequestOptions::USER_VERIFICATION_REQUIREMENT_REQUIRED,
            timeout: self::$timeout,
        );
    }

    public static function challengeHandle(array $data): array
    {
        global $cache, $conn;
        $serializer = self::getSerializer();
        $publicKeyCredential = $serializer->deserialize(json_encode($data), PublicKeyCredential::class, 'json');
        if (! $publicKeyCredential->response instanceof AuthenticatorAssertionResponse) {
            return ['ret' => 0, 'msg' => 'Verification failed'];
        }
        $stmt = $conn->prepare("SELECT * FROM `mfa_credential` WHERE `rawid` = :rawid AND `type` = 'passkey' LIMIT 1");
        $stmt->execute(['rawid' => $data['id']]);
        $publicKeyCredentialSource = $stmt->fetch();
        if (! $publicKeyCredentialSource) {
            return ['ret' => 0, 'msg' => 'Device not registered'];
        }
        $user = (new User())->fetch($publicKeyCredentialSource['userid']);
        if (! $user) {
            return ['ret' => 0, 'msg' => 'User not found'];
        }
        try {
            $publicKeyCredentialRequestOptions = $serializer->deserialize(
                $cache->get('webauthn_assertion_' . session_id()),
                PublicKeyCredentialRequestOptions::class,
                'json'
            );
            $authenticatorAssertionResponseValidator = self::getAuthenticatorAssertionResponseValidator();
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
        $stmt = $conn->prepare("UPDATE `mfa_credential` SET `used_at` = NOW(), `body` = :body WHERE `id` = :id");
        $stmt->execute(['body' => $serializer->serialize($result, 'json'), 'id' => $publicKeyCredentialSource['id']]);
        return ['ret' => 1, 'msg' => 'Verification successful', 'user' => $user];
    }

    public static function getAuthenticatorAssertionResponseValidator(): AuthenticatorAssertionResponseValidator
    {
        $csmFactory = new CeremonyStepManagerFactory();
        $requestCSM = $csmFactory->requestCeremony();
        return AuthenticatorAssertionResponseValidator::create(
            ceremonyStepManager: $requestCSM
        );
    }
}