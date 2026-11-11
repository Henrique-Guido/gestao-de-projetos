<?php

namespace App\Core;

/**
 * Destinos aceitos para as conexões que o servidor abre a partir de dados do cadastro
 * (teste de FTP e verificação de tags), para que esses recursos não sirvam de acesso
 * à rede interna do servidor (SSRF).
 */
class Rede
{
    /** Nunca liberadas, nem pela configuração: o próprio servidor e o serviço de metadados da nuvem. */
    private const SEMPRE_BLOQUEADAS = ['0.0.0.0/8', '127.0.0.0/8', '169.254.0.0/16', '::/128', '::1/128', 'fe80::/10'];

    /**
     * IP público, ou IP privado dentro de uma faixa de REDES_INTERNAS_PERMITIDAS.
     *
     * Em hospedagens com DNS interno (como a uni5), os domínios hospedados lá resolvem para
     * IPs privados quando consultados de dentro do servidor. Liberar só essa faixa mantém o
     * resto da rede interna bloqueado.
     */
    public static function ipPermitido(string $ip): bool
    {
        if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return false;
        }

        foreach (self::SEMPRE_BLOQUEADAS as $faixa) {
            if (self::naFaixa($ip, $faixa)) {
                return false;
            }
        }

        $flags = defined('FILTER_FLAG_GLOBAL_RANGE')
            ? FILTER_FLAG_GLOBAL_RANGE
            : FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE;

        if (filter_var($ip, FILTER_VALIDATE_IP, $flags) !== false) {
            return true;
        }

        $config = require dirname(__DIR__) . '/Config/config.php';

        foreach ($config['rede']['internas_permitidas'] as $faixa) {
            if (self::naFaixa($ip, $faixa)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Confere se o IP está na faixa em notação CIDR (ex.: 10.19.0.0/16). Faixa inválida nunca casa.
     */
    public static function naFaixa(string $ip, string $cidr): bool
    {
        [$rede, $bits] = array_pad(explode('/', trim($cidr), 2), 2, null);

        $ipBin = @inet_pton($ip);
        $redeBin = @inet_pton((string) $rede);

        if ($ipBin === false || $redeBin === false || strlen($ipBin) !== strlen($redeBin)) {
            return false;
        }

        $max = strlen($ipBin) * 8;

        if ($bits === null) {
            $bits = $max;
        } elseif (ctype_digit($bits) && (int) $bits <= $max) {
            $bits = (int) $bits;
        } else {
            return false;
        }

        $bytes = intdiv($bits, 8);

        if (strncmp($ipBin, $redeBin, $bytes) !== 0) {
            return false;
        }

        $resto = $bits % 8;

        if ($resto === 0) {
            return true;
        }

        $mascara = chr((0xFF << (8 - $resto)) & 0xFF);

        return ($ipBin[$bytes] & $mascara) === ($redeBin[$bytes] & $mascara);
    }
}
