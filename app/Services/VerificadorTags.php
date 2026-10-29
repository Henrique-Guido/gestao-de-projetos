<?php

namespace App\Services;

/**
 * Verifica se um site tem Google Tag Manager e Google Analytics instalados.
 *
 * Lê o HTML da página e, quando encontra contêineres do Google (GTM-… ou GT-…), baixa também o
 * JavaScript deles: o GA4 configurado dentro do Tag Manager não aparece no HTML do site.
 *
 * Como o endereço vem do cadastro, as requisições só vão para IPs públicos nas portas 80/443, com
 * o IP fixado após a validação e cada redirecionamento conferido, para que o recurso não sirva de
 * acesso à rede interna do servidor (SSRF).
 */
class VerificadorTags
{
    private const LIMITE_BYTES = 2 * 1024 * 1024;
    private const MAX_REDIRECIONAMENTOS = 5;
    private const MAX_CONTEINERES = 3;
    private const TEMPO_TOTAL = 20;
    private const USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko)'
        . ' Chrome/129.0 Safari/537.36 GestaoClientes/1.0';

    private float $prazo = 0;

    /**
     * @return array{estado: string, mensagem: string} estado: ok | parcial | ausente | erro
     */
    public function verificar(string $url): array
    {
        $this->prazo = microtime(true) + self::TEMPO_TOTAL;

        try {
            [$html, $urlFinal] = $this->buscar($url);
        } catch (\RuntimeException $e) {
            return ['estado' => 'erro', 'mensagem' => "Não foi possível verificar {$url}.\n\n" . $e->getMessage()];
        }

        $encontrado = self::analisar($html);
        $ga4 = array_fill_keys($encontrado['ga4'], null);
        $ua = $encontrado['ua'];

        foreach (array_slice(array_merge($encontrado['gtm'], $encontrado['googleTag']), 0, self::MAX_CONTEINERES) as $conteiner) {
            $script = str_starts_with($conteiner, 'GTM-') ? 'gtm.js' : 'gtag/js';

            try {
                [$js] = $this->buscar("https://www.googletagmanager.com/{$script}?id={$conteiner}");
            } catch (\RuntimeException) {
                continue;
            }

            $interno = self::analisarConteiner($js);

            foreach ($interno['ga4'] as $id) {
                // Guarda em qual contêiner o ID foi achado, a menos que já esteja direto no HTML.
                if (!array_key_exists($id, $ga4)) {
                    $ga4[$id] = $conteiner;
                }
            }

            $ua = array_values(array_unique(array_merge($ua, $interno['ua'])));
        }

        return self::montarResposta($urlFinal, $encontrado['gtm'], $ga4, $ua);
    }

    /**
     * IDs encontrados no HTML de uma página.
     *
     * @return array{gtm: string[], ga4: string[], googleTag: string[], ua: string[]}
     */
    public static function analisar(string $html): array
    {
        return [
            'gtm' => self::ids('/\b(GTM-[A-Z0-9]{4,10})\b/', $html),
            // G-… só conta em contextos do gtag (evita falsos positivos como nomes de produtos).
            'ga4' => self::ids(
                '/(?:gtag\/js\?id=|[\'"]config[\'"]\s*,\s*[\'"]|measurement_?id[\'"]?\s*[:=]\s*[\'"])(G-[A-Z0-9]{6,15})\b/i',
                $html
            ),
            'googleTag' => self::ids('/gtag\/js\?id=(GT-[A-Z0-9]{4,15})\b/', $html),
            'ua' => self::ids('/\b(UA-\d{4,10}-\d{1,4})\b/', $html),
        ];
    }

    /**
     * IDs encontrados no JavaScript de um contêiner do Google (gtm.js ou gtag/js).
     *
     * @return array{ga4: string[], ua: string[]}
     */
    public static function analisarConteiner(string $js): array
    {
        return [
            'ga4' => self::ids('/["\'](G-[A-Z0-9]{6,15})["\']/', $js),
            'ua' => self::ids('/["\'](UA-\d{4,10}-\d{1,4})["\']/', $js),
        ];
    }

    private static function ids(string $regex, string $texto): array
    {
        preg_match_all($regex, $texto, $m);

        return array_values(array_unique($m[1]));
    }

    /**
     * @param array<string, ?string> $ga4 ID => contêiner onde foi achado (null = direto no HTML)
     */
    private static function montarResposta(string $url, array $gtm, array $ga4, array $ua): array
    {
        $temGtm = $gtm !== [];
        $temGa4 = $ga4 !== [];

        [$estado, $resumo] = match (true) {
            $temGtm && $temGa4 => ['ok', 'Google Analytics e Tag Manager instalados.'],
            $temGtm => ['parcial', 'Tag Manager instalado, mas o Google Analytics 4 não foi encontrado.'],
            $temGa4 => ['parcial', 'Google Analytics 4 instalado, sem Tag Manager.'],
            default => ['ausente', 'Nenhuma tag do Google Analytics ou do Tag Manager foi encontrada.'],
        };

        $idsGa4 = [];
        foreach ($ga4 as $id => $conteiner) {
            $idsGa4[] = $conteiner ? "{$id} (dentro de {$conteiner})" : $id;
        }

        $linhas = [
            $resumo,
            '',
            "Site: {$url}",
            ($temGtm ? '✓' : '✗') . ' Google Tag Manager: ' . ($temGtm ? implode(', ', $gtm) : 'não encontrado'),
            ($temGa4 ? '✓' : '✗') . ' Google Analytics 4: ' . ($temGa4 ? implode(', ', $idsGa4) : 'não encontrado'),
        ];

        if ($ua !== []) {
            $linhas[] = '⚠ Universal Analytics: ' . implode(', ', $ua) . ' (versão antiga, descontinuada pelo Google; não coleta mais dados)';
        }

        if ($estado !== 'ok') {
            $linhas[] = '';
            $linhas[] = 'Obs.: a verificação lê o HTML da página inicial. Tags inseridas por JavaScript depois que a página'
                . ' carrega (comum em sites feitos em React/Next.js) ou só após o aceite de cookies podem não ser detectadas.';
        }

        return ['estado' => $estado, 'mensagem' => implode("\n", $linhas)];
    }

    /**
     * Baixa a URL seguindo redirecionamentos manualmente, validando cada destino.
     *
     * @return array{0: string, 1: string} corpo da resposta e URL final
     */
    private function buscar(string $url): array
    {
        for ($i = 0; $i <= self::MAX_REDIRECIONAMENTOS; $i++) {
            $partes = parse_url($url);
            $esquema = strtolower($partes['scheme'] ?? '');
            $host = $partes['host'] ?? '';

            if (!in_array($esquema, ['http', 'https'], true) || $host === '') {
                throw new \RuntimeException('O endereço do site é inválido.');
            }

            $porta = $partes['port'] ?? ($esquema === 'https' ? 443 : 80);

            if (!in_array($porta, [80, 443], true)) {
                throw new \RuntimeException('Só são verificados sites nas portas padrão (80 e 443).');
            }

            [$status, $headers, $corpo] = $this->requisitar($url, $host, $porta, self::ipPublico($host));

            if ($status >= 300 && $status < 400 && isset($headers['location'])) {
                $url = self::resolverUrl($url, $headers['location']);
                continue;
            }

            if ($status >= 400) {
                throw new \RuntimeException(match (true) {
                    in_array($status, [403, 429, 503], true) => "O site recusou o acesso automático (HTTP {$status}). Ele pode ter proteção contra robôs, como a do Cloudflare.",
                    $status === 404 => 'A página não foi encontrada (HTTP 404). Confira o endereço do site.',
                    default => "O site respondeu com erro (HTTP {$status}).",
                });
            }

            return [$corpo, $url];
        }

        throw new \RuntimeException('O site redirecionou vezes demais.');
    }

    /**
     * @return array{0: int, 1: array<string, string>, 2: string} status, headers e corpo
     */
    private function requisitar(string $url, string $host, int $porta, string $ip): array
    {
        $restanteMs = (int) (($this->prazo - microtime(true)) * 1000);

        if ($restanteMs <= 0) {
            throw new \RuntimeException('A verificação demorou demais e foi interrompida.');
        }

        $headers = [];
        $corpo = '';

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT_MS => min(5000, $restanteMs),
            CURLOPT_TIMEOUT_MS => $restanteMs,
            CURLOPT_ENCODING => '',
            CURLOPT_USERAGENT => self::USER_AGENT,
            CURLOPT_HTTPHEADER => ['Accept: text/html,application/xhtml+xml,*/*;q=0.8', 'Accept-Language: pt-BR,pt;q=0.9'],
            CURLOPT_HEADERFUNCTION => static function ($ch, string $linha) use (&$headers): int {
                if (str_contains($linha, ':')) {
                    [$nome, $valor] = explode(':', $linha, 2);
                    $headers[strtolower(trim($nome))] = trim($valor);
                }

                return strlen($linha);
            },
            CURLOPT_WRITEFUNCTION => static function ($ch, string $dados) use (&$corpo): int {
                $corpo .= $dados;

                // Retornar 0 interrompe o download ao passar do limite; o que já chegou é aproveitado.
                return strlen($corpo) > self::LIMITE_BYTES ? 0 : strlen($dados);
            },
        ]);

        // Fixa o IP já validado, para que um segundo DNS não aponte para outro destino.
        if (!filter_var(trim($host, '[]'), FILTER_VALIDATE_IP)) {
            curl_setopt($ch, CURLOPT_RESOLVE, ["{$host}:{$porta}:{$ip}"]);
            curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
        }

        curl_exec($ch);
        $erro = curl_errno($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);

        if ($erro !== 0 && !($erro === CURLE_WRITE_ERROR && strlen($corpo) > self::LIMITE_BYTES)) {
            throw new \RuntimeException(match ($erro) {
                CURLE_OPERATION_TIMEDOUT => 'O site demorou demais para responder.',
                CURLE_COULDNT_CONNECT => 'Não foi possível conectar ao servidor do site.',
                CURLE_COULDNT_RESOLVE_HOST => 'O domínio não foi encontrado (DNS). Confira o endereço do site.',
                CURLE_SSL_CACERT, CURLE_SSL_CONNECT_ERROR => 'O certificado de segurança (HTTPS) do site é inválido ou expirou.',
                default => "Falha na conexão com o site (código {$erro}).",
            });
        }

        return [$status, $headers, $corpo];
    }

    private static function ipPublico(string $host): string
    {
        $host = trim($host, '[]');
        $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : (gethostbynamel($host) ?: []);

        if ($ips === []) {
            throw new \RuntimeException('O domínio não foi encontrado (DNS). Confira o endereço do site.');
        }

        $flags = defined('FILTER_FLAG_GLOBAL_RANGE')
            ? FILTER_FLAG_GLOBAL_RANGE
            : FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE;

        foreach ($ips as $ip) {
            if (!filter_var($ip, FILTER_VALIDATE_IP, $flags)) {
                throw new \RuntimeException('O endereço aponta para uma rede interna e não pode ser verificado.');
            }
        }

        return $ips[0];
    }

    private static function resolverUrl(string $base, string $location): string
    {
        // Já tem esquema (http, https ou outro, que será recusado na validação).
        if (preg_match('#^[a-z][a-z0-9+.-]*:#i', $location)) {
            return $location;
        }

        $b = parse_url($base);

        if (str_starts_with($location, '//')) {
            return $b['scheme'] . ':' . $location;
        }

        $origem = $b['scheme'] . '://' . $b['host'] . (isset($b['port']) ? ':' . $b['port'] : '');

        if (str_starts_with($location, '/')) {
            return $origem . $location;
        }

        return $origem . preg_replace('#/[^/]*$#', '/', $b['path'] ?? '/') . $location;
    }
}
