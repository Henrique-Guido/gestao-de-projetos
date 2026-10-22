<?php

namespace App\Models;

use App\Core\Crypto;
use App\Core\Database;

class Ecommerce
{
    public static function all(?string $search = null): array
    {
        $pdo = Database::connection();

        if ($search) {
            $stmt = $pdo->prepare(
                'SELECT * FROM ecommerce
                 WHERE cliente LIKE ? OR link LIKE ? OR email LIKE ? OR usuario LIKE ?
                 ORDER BY cliente ASC'
            );
            $like = "%{$search}%";
            $stmt->execute([$like, $like, $like, $like]);
        } else {
            $stmt = $pdo->query('SELECT * FROM ecommerce ORDER BY cliente ASC');
        }

        return $stmt->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM ecommerce WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $loja = $stmt->fetch();

        return $loja ?: null;
    }

    public static function create(array $data): int
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO ecommerce (cliente, link, email, usuario, codigo, senha, user_id)
             VALUES (:cliente, :link, :email, :usuario, :codigo, :senha, :user_id)'
        );

        $stmt->execute([
            'cliente' => $data['cliente'],
            'link' => $data['link'],
            'email' => $data['email'] ?: null,
            'usuario' => $data['usuario'] ?: null,
            'codigo' => $data['codigo'] ?: null,
            'senha' => Crypto::encrypt($data['senha']),
            'user_id' => $data['user_id'],
        ]);

        return (int) Database::connection()->lastInsertId();
    }

    public static function update(int $id, array $data): void
    {
        $sql = 'UPDATE ecommerce SET cliente = :cliente, link = :link, email = :email,
                usuario = :usuario, codigo = :codigo';
        $params = [
            'cliente' => $data['cliente'],
            'link' => $data['link'],
            'email' => $data['email'] ?: null,
            'usuario' => $data['usuario'] ?: null,
            'codigo' => $data['codigo'] ?: null,
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
        $stmt = Database::connection()->prepare('DELETE FROM ecommerce WHERE id = ?');
        $stmt->execute([$id]);
    }

    public static function decryptPassword(string $encrypted): ?string
    {
        return Crypto::decrypt($encrypted);
    }
}
