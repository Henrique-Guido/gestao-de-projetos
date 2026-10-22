<?php

namespace App\Models;

use App\Core\Database;
use PDO;
use PDOException;

class Configuracao
{
    private static ?array $cache = null;

    public static function get(string $chave, ?string $default = null): ?string
    {
        if (self::$cache === null) {
            try {
                self::$cache = Database::connection()
                    ->query('SELECT chave, valor FROM configuracoes')
                    ->fetchAll(PDO::FETCH_KEY_PAIR);
            } catch (PDOException $e) {
                // 42S02 = tabela inexistente (schema ainda não aplicado): usa os valores padrão.
                if ($e->getCode() !== '42S02') {
                    throw $e;
                }

                self::$cache = [];
            }
        }

        return self::$cache[$chave] ?? $default;
    }

    public static function set(string $chave, string $valor): void
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO configuracoes (chave, valor) VALUES (:chave, :valor)
             ON DUPLICATE KEY UPDATE valor = VALUES(valor)'
        );
        $stmt->execute(['chave' => $chave, 'valor' => $valor]);

        if (self::$cache !== null) {
            self::$cache[$chave] = $valor;
        }
    }
}
