<?php

namespace App\Core;

class Csrf
{
    public static function token(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['csrf_token'];
    }

    public static function field(): string
    {
        return '<input type="hidden" name="_token" value="' . self::token() . '">';
    }

    public static function verify(?string $token): bool
    {
        $expected = $_SESSION['csrf_token'] ?? '';

        return $expected !== '' && is_string($token) && hash_equals($expected, $token);
    }
}
