<?php

namespace App\Models;

use App\Core\Crypto;
use App\Core\Database;

class FtpAccount
{
    /** Meses desde a última reunião até virar status "ok". */
    private const LIMITE_OK = 2;

    /** Meses desde a última reunião até virar status "moderado" (acima disso é "crítico"). */
    private const LIMITE_MODERADO = 4;

    public static function all(?string $search = null): array
    {
        $pdo = Database::connection();

        if ($search) {
            $stmt = $pdo->prepare(
                'SELECT * FROM ftp_accounts
                 WHERE cliente LIKE ? OR host LIKE ? OR usuario LIKE ?
                 ORDER BY cliente ASC'
            );
            $like = "%{$search}%";
            $stmt->execute([$like, $like, $like]);
        } else {
            $stmt = $pdo->query('SELECT * FROM ftp_accounts ORDER BY cliente ASC');
        }

        $accounts = $stmt->fetchAll();

        foreach ($accounts as &$account) {
            $account['status'] = self::calcularStatus($account['ultima_reuniao']);
        }

        return $accounts;
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM ftp_accounts WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $account = $stmt->fetch();

        if (!$account) {
            return null;
        }

        $account['status'] = self::calcularStatus($account['ultima_reuniao']);

        return $account;
    }

    public static function create(array $data): int
    {
        $senha = trim((string) ($data['senha'] ?? ''));

        $stmt = Database::connection()->prepare(
            'INSERT INTO ftp_accounts (cliente, ultima_reuniao, proxima_reuniao, host, usuario, senha, user_id)
             VALUES (:cliente, :ultima_reuniao, :proxima_reuniao, :host, :usuario, :senha, :user_id)'
        );

        $stmt->execute([
            'cliente' => $data['cliente'],
            'ultima_reuniao' => $data['ultima_reuniao'] ?: null,
            'proxima_reuniao' => $data['proxima_reuniao'] ?: null,
            'host' => $data['host'] ?: null,
            'usuario' => $data['usuario'] ?: null,
            'senha' => $senha !== '' ? Crypto::encrypt($senha) : null,
            'user_id' => $data['user_id'],
        ]);

        return (int) Database::connection()->lastInsertId();
    }

    public static function update(int $id, array $data): void
    {
        $sql = 'UPDATE ftp_accounts SET cliente = :cliente, ultima_reuniao = :ultima_reuniao,
                proxima_reuniao = :proxima_reuniao, host = :host, usuario = :usuario';
        $params = [
            'cliente' => $data['cliente'],
            'ultima_reuniao' => $data['ultima_reuniao'] ?: null,
            'proxima_reuniao' => $data['proxima_reuniao'] ?: null,
            'host' => $data['host'],
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

    public static function updateCliente(int $id, array $data): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE ftp_accounts SET cliente = :cliente, ultima_reuniao = :ultima_reuniao,
             proxima_reuniao = :proxima_reuniao WHERE id = :id'
        );

        $stmt->execute([
            'cliente' => $data['cliente'],
            'ultima_reuniao' => $data['ultima_reuniao'] ?: null,
            'proxima_reuniao' => $data['proxima_reuniao'] ?: null,
            'id' => $id,
        ]);
    }

    public static function delete(int $id): void
    {
        $stmt = Database::connection()->prepare('DELETE FROM ftp_accounts WHERE id = ?');
        $stmt->execute([$id]);
    }

    public static function decryptPassword(string $encrypted): ?string
    {
        return Crypto::decrypt($encrypted);
    }

    /**
     * Testa a conexão FTP com as credenciais informadas.
     * Retorna ['ok' => bool, 'message' => string] com uma mensagem amigável em caso de falha.
     */
    public static function testConnection(string $host, string $usuario, string $senha, int $porta = 21, int $timeout = 10): array
    {
        if (!function_exists('ftp_connect')) {
            return ['ok' => false, 'message' => 'A extensão FTP do PHP não está disponível no servidor.'];
        }

        $hostname = $host;
        if (str_contains($host, '://')) {
            $hostname = parse_url($host, PHP_URL_HOST) ?: $host;
        }

        set_error_handler(function () {
            return true;
        });

        try {
            $conn = ftp_connect($hostname, $porta, $timeout);

            if (!$conn) {
                return ['ok' => false, 'message' => "Não foi possível conectar ao host \"{$hostname}\" na porta {$porta}. Verifique o endereço e a porta."];
            }

            $login = @ftp_login($conn, $usuario, $senha);

            if (!$login) {
                ftp_close($conn);

                return ['ok' => false, 'message' => 'Usuário ou senha incorretos.'];
            }

            $pasv = @ftp_pasv($conn, true);

            if (!$pasv || @ftp_nlist($conn, '.') === false) {
                ftp_close($conn);

                return ['ok' => false, 'message' => 'Login realizado, mas não foi possível listar os arquivos. Verifique as permissões da conta.'];
            }

            ftp_close($conn);

            return ['ok' => true, 'message' => 'Conexão FTP realizada com sucesso!'];
        } finally {
            restore_error_handler();
        }
    }

    /**
     * Deriva o status a partir dos meses passados desde a última reunião:
     * até 2 meses = ok, de 2 a 4 = moderado, acima de 4 = crítico.
     * Sem reunião registrada, o cliente é tratado como crítico.
     */
    public static function calcularStatus(?string $ultimaReuniao): string
    {
        if (!$ultimaReuniao) {
            return 'critico';
        }

        $ultima = new \DateTimeImmutable($ultimaReuniao);
        $hoje = new \DateTimeImmutable('today');

        if ($ultima > $hoje) {
            return 'ok';
        }

        $mesesPassados = $ultima->diff($hoje)->y * 12 + $ultima->diff($hoje)->m;

        if ($mesesPassados <= self::LIMITE_OK) {
            return 'ok';
        }

        if ($mesesPassados <= self::LIMITE_MODERADO) {
            return 'moderado';
        }

        return 'critico';
    }
}
