<?php

namespace TransMore\Backend;

final class OfflineGrant
{
    private const LIFETIME = 86400;

    public static function issue(array $user): array
    {
        [$privateKey, $publicKey] = self::keys();
        $now = time();
        $payload = [
            'iss' => 'transmore-backend',
            'mode' => 'offline-operational',
            'iat' => $now,
            'exp' => $now + self::LIFETIME,
            'user' => $user,
        ];
        $encoded = self::base64UrlEncode(json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        $signature = sodium_crypto_sign_detached($encoded, $privateKey);
        return [
            'token' => $encoded . '.' . self::base64UrlEncode($signature),
            'publicKey' => self::base64UrlEncode($publicKey),
            'expiresAt' => gmdate(DATE_ATOM, $payload['exp']),
        ];
    }

    private static function keys(): array
    {
        $directory = __DIR__ . '/../storage';
        $privatePath = $directory . '/offline-private.key';
        $publicPath = $directory . '/offline-public.key';
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new \RuntimeException('Offline key directory is not writable.');
        }
        if (!is_file($privatePath) || !is_file($publicPath)) {
            $keyPair = sodium_crypto_sign_keypair();
            $privateKey = sodium_crypto_sign_secretkey($keyPair);
            $publicKey = sodium_crypto_sign_publickey($keyPair);
            if (file_put_contents($privatePath, $privateKey, LOCK_EX) === false || file_put_contents($publicPath, $publicKey, LOCK_EX) === false) {
                throw new \RuntimeException('Offline signing key could not be stored.');
            }
            @chmod($privatePath, 0600);
        }
        $privateKey = file_get_contents($privatePath);
        $publicKey = file_get_contents($publicPath);
        if (strlen($privateKey) !== SODIUM_CRYPTO_SIGN_SECRETKEYBYTES || strlen($publicKey) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES) {
            throw new \RuntimeException('Offline signing key is invalid.');
        }
        return [$privateKey, $publicKey];
    }

    private static function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
