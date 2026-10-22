<?php

namespace App\Core;

/**
 * Criptografia reversível (AES-256-GCM) usada apenas para a senha de FTP,
 * que precisa ser recuperada em texto puro para uso no cliente de FTP.
 * A senha de login do sistema NUNCA passa por aqui: ela usa bcrypt (one-way).
 */
class Crypto
{
    private static function key(): string
    {
        $config = require dirname(__DIR__) . '/Config/config.php';
        $key = $config['app']['key'];

        if (empty($key)) {
            throw new \RuntimeException('APP_KEY não configurada no .env');
        }

        return hex2bin($key);
    }

    public static function encrypt(string $plainText): string
    {
        $iv = random_bytes(12);
        $tag = '';

        $cipherText = openssl_encrypt($plainText, 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, $iv, $tag);

        return base64_encode($iv . $tag . $cipherText);
    }

    public static function decrypt(string $encoded): ?string
    {
        $data = base64_decode($encoded, true);

        if ($data === false || strlen($data) < 28) {
            return null;
        }

        $iv = substr($data, 0, 12);
        $tag = substr($data, 12, 16);
        $cipherText = substr($data, 28);

        $plainText = openssl_decrypt($cipherText, 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, $iv, $tag);

        return $plainText === false ? null : $plainText;
    }
}
