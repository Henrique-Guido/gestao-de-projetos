<?php

namespace App\Models;

use App\Core\Crypto;
use App\Core\Database;

class Wordpress
{
    public static function all(?string $search = null): array
    {
        $pdo = Database::connection();

        if ($search) {
            $stmt = $pdo->prepare(
                'SELECT * FROM wordpress
                 WHERE cliente LIKE ? OR usuario LIKE ?
                 ORDER BY cliente ASC'
            );
            $like = "%{$search}%";
            $stmt->execute([$like, $like]);
        } else {
            $stmt = $pdo->query('SELECT * FROM wordpress ORDER BY cliente ASC');
        }

        return $stmt->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM wordpress WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $site = $stmt->fetch();

        return $site ?: null;
    }

    public static function create(array $data): int
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO wordpress (cliente, usuario, senha, user_id)
             VALUES (:cliente, :usuario, :senha, :user_id)'
        );

        $stmt->execute([
            'cliente' => $data['cliente'],
            'usuario' => $data['usuario'],
            'senha' => Crypto::encrypt($data['senha']),
            'user_id' => $data['user_id'],
        ]);

        return (int) Database::connection()->lastInsertId();
    }

    public static function update(int $id, array $data): void
    {
        $sql = 'UPDATE wordpress SET cliente = :cliente, usuario = :usuario';
        $params = [
            'cliente' => $data['cliente'],
            'usuario' => $data['usuario'],
            'id' => $id,
        ];

        if (!empty($data['senha'])) {
            $sql .= ', senha = :senha';
            $params['senha'] = Crypto::encrypt($data['senha']);
        }

        $sql .= ' WHERE id = :id';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
    }

    public static function delete(int $id): void
    {
        $stmt = Database::connection()->prepare('DELETE FROM wordpress WHERE id = ?');
        $stmt->execute([$id]);
    }

    public static function decryptPassword(string $encrypted): ?string
    {
        return Crypto::decrypt($encrypted);
    }
}
