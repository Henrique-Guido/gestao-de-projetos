<?php

namespace App\Models;

use App\Core\Database;

class User
{
    public static function all(): array
    {
        return Database::connection()
            ->query('SELECT id, name, email, role, pode_criar_clientes, pode_editar_clientes, created_at FROM users ORDER BY name ASC')
            ->fetchAll();
    }

    public static function findByEmail(string $email): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        return $user ?: null;
    }

    public static function findByName(string $name): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM users WHERE name = ? LIMIT 1');
        $stmt->execute([$name]);
        $user = $stmt->fetch();

        return $user ?: null;
    }

    public static function findById(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $user = $stmt->fetch();

        return $user ?: null;
    }

    public static function create(array $data): int
    {
        $hash = password_hash($data['password'], PASSWORD_BCRYPT);

        $stmt = Database::connection()->prepare(
            'INSERT INTO users (name, email, password, role, pode_criar_clientes, pode_editar_clientes)
             VALUES (:name, :email, :password, :role, :pode_criar_clientes, :pode_editar_clientes)'
        );

        $stmt->execute([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $hash,
            'role' => $data['role'] === 'admin' ? 'admin' : 'user',
            'pode_criar_clientes' => !empty($data['pode_criar_clientes']) ? 1 : 0,
            'pode_editar_clientes' => !empty($data['pode_editar_clientes']) ? 1 : 0,
        ]);

        return (int) Database::connection()->lastInsertId();
    }

    public static function update(int $id, array $data): void
    {
        $sql = 'UPDATE users SET name = :name, email = :email, role = :role,
                pode_criar_clientes = :pode_criar_clientes, pode_editar_clientes = :pode_editar_clientes';
        $params = [
            'name' => $data['name'],
            'email' => $data['email'],
            'role' => $data['role'] === 'admin' ? 'admin' : 'user',
            'pode_criar_clientes' => !empty($data['pode_criar_clientes']) ? 1 : 0,
            'pode_editar_clientes' => !empty($data['pode_editar_clientes']) ? 1 : 0,
            'id' => $id,
        ];

        if (!empty($data['password'])) {
            $sql .= ', password = :password';
            $params['password'] = password_hash($data['password'], PASSWORD_BCRYPT);
        }

        $sql .= ' WHERE id = :id';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
    }

    public static function delete(int $id): void
    {
        $stmt = Database::connection()->prepare('DELETE FROM users WHERE id = ?');
        $stmt->execute([$id]);
    }

    public static function countAdmins(): int
    {
        return (int) Database::connection()
            ->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")
            ->fetchColumn();
    }
}
