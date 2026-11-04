<?php

namespace App\Core;

/**
 * Limite de tentativas de login contra força bruta.
 *
 * Dois contadores, guardados em arquivos (sem tabela no banco):
 * - nome + IP: bloqueia após poucas falhas. Por incluir o IP, alguém de fora não
 *   consegue trancar o acesso de um usuário legítimo que entra de outro lugar;
 * - só IP: limite folgado contra quem testa vários nomes a partir do mesmo IP.
 *   É folgado porque a equipe costuma sair pelo mesmo IP do escritório.
 *
 * Se não houver diretório gravável, o login segue liberado e o problema vai para o
 * log: uma falha de disco nunca deve trancar todo mundo do lado de fora.
 */
class LoginThrottle
{
    /** Período em que as falhas são somadas (segundos). */
    private const JANELA = 900;

    /** Duração do bloqueio ao atingir o limite (segundos). */
    private const BLOQUEIO = 900;

    private const MAX_FALHAS_NOME_IP = 5;
    private const MAX_FALHAS_IP = 30;

    /** Arquivos sem atividade há mais tempo que isso são apagados. */
    private const EXPIRACAO_ARQUIVOS = 86400;

    private static string|null|false $diretorio = false;

    /**
     * Segundos que faltam para liberar novas tentativas; 0 se não houver bloqueio.
     */
    public static function segundosBloqueado(string $nome, string $ip): int
    {
        $agora = time();
        $restante = 0;

        foreach (self::contadores($nome, $ip) as $chave => $max) {
            $dados = self::ler($chave);
            $restante = max($restante, (int) ($dados['bloqueado_ate'] ?? 0) - $agora);
        }

        return max(0, $restante);
    }

    public static function registrarFalha(string $nome, string $ip): void
    {
        foreach (self::contadores($nome, $ip) as $chave => $max) {
            self::atualizar($chave, function (array $dados) use ($max): array {
                $agora = time();

                if ($agora - (int) ($dados['inicio'] ?? 0) > self::JANELA) {
                    $dados['inicio'] = $agora;
                    $dados['falhas'] = 0;
                }

                $dados['falhas'] = (int) ($dados['falhas'] ?? 0) + 1;

                if ($dados['falhas'] >= $max) {
                    $dados['bloqueado_ate'] = $agora + self::BLOQUEIO;
                    // Depois do bloqueio, a contagem recomeça do zero.
                    $dados['inicio'] = $agora;
                    $dados['falhas'] = 0;
                }

                return $dados;
            });
        }

        self::apagarExpirados();
    }

    /**
     * Login bem-sucedido: zera o contador de nome + IP. O contador só de IP é
     * mantido, para que uma conta válida não sirva para "zerar" tentativas em outras.
     */
    public static function registrarSucesso(string $nome, string $ip): void
    {
        $arquivo = self::arquivo(self::chaveNomeIp($nome, $ip));

        if ($arquivo !== null && is_file($arquivo)) {
            @unlink($arquivo);
        }
    }

    /** @return array<string, int> chave do contador => máximo de falhas */
    private static function contadores(string $nome, string $ip): array
    {
        return [
            self::chaveNomeIp($nome, $ip) => self::MAX_FALHAS_NOME_IP,
            'ip|' . $ip => self::MAX_FALHAS_IP,
        ];
    }

    private static function chaveNomeIp(string $nome, string $ip): string
    {
        // O banco compara nomes sem diferenciar maiúsculas; o contador também.
        return 'nome|' . mb_strtolower(trim($nome)) . '|' . $ip;
    }

    private static function ler(string $chave): array
    {
        $arquivo = self::arquivo($chave);

        if ($arquivo === null || !is_file($arquivo)) {
            return [];
        }

        $dados = json_decode((string) @file_get_contents($arquivo), true);

        return is_array($dados) ? $dados : [];
    }

    private static function atualizar(string $chave, callable $alterar): void
    {
        $arquivo = self::arquivo($chave);

        if ($arquivo === null) {
            return;
        }

        $fp = @fopen($arquivo, 'c+');

        if ($fp === false) {
            error_log("LoginThrottle: não foi possível abrir {$arquivo}.");
            return;
        }

        try {
            // Trava o arquivo para que requisições simultâneas não percam falhas.
            if (!flock($fp, LOCK_EX)) {
                return;
            }

            $dados = json_decode((string) stream_get_contents($fp), true);
            $dados = $alterar(is_array($dados) ? $dados : []);

            ftruncate($fp, 0);
            rewind($fp);
            fwrite($fp, json_encode($dados));
            fflush($fp);
            flock($fp, LOCK_UN);
        } finally {
            fclose($fp);
        }
    }

    private static function arquivo(string $chave): ?string
    {
        $diretorio = self::diretorio();

        return $diretorio === null ? null : $diretorio . '/' . hash('sha256', $chave) . '.json';
    }

    /**
     * storage/ do projeto (fora de public/, não é servido pelo Apache); se não for
     * gravável (ex.: ambiente local com Apache como outro usuário), usa o temporário
     * do sistema.
     */
    private static function diretorio(): ?string
    {
        if (self::$diretorio !== false) {
            return self::$diretorio;
        }

        $raiz = dirname(__DIR__, 2);
        $candidatos = [
            $raiz . '/storage/login-throttle',
            sys_get_temp_dir() . '/gestao-projetos-login-' . substr(hash('sha256', $raiz), 0, 12),
        ];

        foreach ($candidatos as $candidato) {
            if (!is_dir($candidato) && !@mkdir($candidato, 0700, true)) {
                continue;
            }

            // Em /tmp compartilhado, ignora um diretório criado por outro usuário do servidor.
            if (function_exists('posix_geteuid') && @fileowner($candidato) !== posix_geteuid()) {
                continue;
            }

            if (is_writable($candidato)) {
                return self::$diretorio = $candidato;
            }
        }

        error_log('LoginThrottle: nenhum diretório gravável; limite de tentativas de login desativado.');

        return self::$diretorio = null;
    }

    private static function apagarExpirados(): void
    {
        $diretorio = self::diretorio();

        // Faxina ocasional, para não pesar em toda falha de login.
        if ($diretorio === null || random_int(1, 50) !== 1) {
            return;
        }

        $limite = time() - self::EXPIRACAO_ARQUIVOS;

        foreach (glob($diretorio . '/*.json') ?: [] as $arquivo) {
            if (@filemtime($arquivo) < $limite) {
                @unlink($arquivo);
            }
        }
    }
}
