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
        $_SESSION['senha_impressao'] = self::impressaoSenha($user['password']);
        self::$user = $user;

        return true;
    }

    public static function logout(): void
    {
        self::$user = null;
        unset($_SESSION['user_id'], $_SESSION['senha_impressao']);
        session_regenerate_id(true);
    }

    /**
     * Após o próprio usuário logado trocar a senha: atualiza a impressão guardada na
     * sessão para que ele continue logado (as demais sessões dele são encerradas).
     */
    public static function renovarImpressaoSenha(): void
    {
        $user = User::findById((int) self::id());

        if ($user) {
            $_SESSION['senha_impressao'] = self::impressaoSenha($user['password']);
            self::$user = $user;
        }
    }

    /**
     * Identifica a senha atual sem guardá-la (nem o hash) na sessão. Se a senha mudar,
     * o hash bcrypt muda e a impressão deixa de bater.
     */
    private static function impressaoSenha(string $hash): string
    {
        return hash('sha256', $hash);
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
            $user = User::findById(self::id());

            // Encerra a sessão se o usuário foi removido do banco ou se a senha foi
            // trocada depois do login (inclui sessões antigas, sem impressão guardada).
            $impressao = $_SESSION['senha_impressao'] ?? null;
            if ($user === null || !is_string($impressao)
                || !hash_equals(self::impressaoSenha($user['password']), $impressao)) {
                self::logout();
                return null;
            }

            self::$user = $user;
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
