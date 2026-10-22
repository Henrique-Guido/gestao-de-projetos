<?php

namespace App\Core;

use App\Models\User;

class Auth
{
    private static ?array $user = null;

    public static function attempt(string $name, string $password): bool
    {
        $user = User::findByName($name);

        if (!$user) {
            // Gera um hash mesmo sem usuário para igualar o tempo de resposta e não revelar quais nomes existem.
            password_hash($password, PASSWORD_BCRYPT);
            return false;
        }

        if (!password_verify($password, $user['password'])) {
            return false;
        }

        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        self::$user = $user;

        return true;
    }

    public static function logout(): void
    {
        self::$user = null;
        unset($_SESSION['user_id']);
        session_regenerate_id(true);
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function id(): ?int
    {
        return $_SESSION['user_id'] ?? null;
    }

    public static function user(): ?array
    {
        if (!isset($_SESSION['user_id'])) {
            return null;
        }

        if (self::$user === null) {
            self::$user = User::findById(self::id());

            // Usuário removido do banco: encerra a sessão órfã.
            if (self::$user === null) {
                self::logout();
            }
        }

        return self::$user;
    }

    public static function isAdmin(): bool
    {
        return (self::user()['role'] ?? 'user') === 'admin';
    }

    /**
     * Admin sempre tem acesso total; para os demais, verifica a flag de permissão
     * (ex.: 'pode_criar_clientes', 'pode_editar_clientes') no registro do usuário.
     */
    public static function can(string $permission): bool
    {
        if (self::isAdmin()) {
            return true;
        }

        return (bool) (self::user()[$permission] ?? false);
    }
}
