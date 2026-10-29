<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Crypto;
use App\Core\Flash;
use App\Core\XlsxWriter;
use App\Models\Blog;
use App\Models\Ecommerce;
use App\Models\FtpAccount;
use App\Models\Wordpress;

class ExportController extends Controller
{
    /** Rótulo, cor de fundo e cor do texto de cada status (mesmas cores da tela de Clientes). */
    private const STATUS = [
        'ok' => ['Ok', 'E6F7EE', '0B7A44'],
        'moderado' => ['Moderado', 'FFF6DD', 'A86B00'],
        'critico' => ['Crítico', 'FDECEA', 'B3261E'],
    ];

    private const CORES = [
        'resumo' => '0D1117',
        'clientes' => '58A6FF',
        'ftp' => '0E7C86',
        'blogs' => 'E8710A',
        'wordpress' => '21759B',
        'ecommerce' => '7B3FA0',
    ];

    public function __construct()
    {
        if (!Auth::check()) {
            header('Location: ' . (require dirname(__DIR__) . '/Config/config.php')['app']['url'] . '/login');
            exit;
        }

        // A planilha leva todas as senhas em texto puro.
        if (!Auth::isAdmin()) {
            Flash::set('error', 'Apenas administradores podem exportar os dados.');
            $this->redirect('/clientes');
        }
    }

    public function excel(): void
    {
        $clientes = FtpAccount::all();
        $blogs = Blog::all();
        $sites = Wordpress::all();
        $lojas = Ecommerce::all();
        $appName = (require dirname(__DIR__) . '/Config/config.php')['app']['name'];

        $severidade = ['critico' => 0, 'moderado' => 1, 'ok' => 2];
        $porStatus = $clientes;
        usort($porStatus, static fn (array $a, array $b) => $severidade[$a['status']] <=> $severidade[$b['status']]
            ?: $a['cliente'] <=> $b['cliente']);

        $xlsx = new XlsxWriter();

        $xlsx->addSheet('Resumo', self::CORES['resumo'], [
            ['titulo' => '', 'largura' => 34],
            ['titulo' => '', 'largura' => 44],
        ], $this->resumo($appName, [
            'Clientes' => [self::CORES['clientes'], count($clientes)],
            'Acessos FTP' => [self::CORES['ftp'], count($clientes)],
            'Blogs' => [self::CORES['blogs'], count($blogs)],
            'WordPress' => [self::CORES['wordpress'], count($sites)],
            'E-commerce' => [self::CORES['ecommerce'], count($lojas)],
        ]), tabela: false);

        $xlsx->addSheet('Clientes', self::CORES['clientes'], [
            ['titulo' => 'Cliente', 'largura' => 38],
            ['titulo' => 'Site', 'largura' => 36],
            ['titulo' => 'Última reunião', 'largura' => 18],
            ['titulo' => 'Próxima reunião', 'largura' => 18],
            ['titulo' => 'Status', 'largura' => 14],
        ], array_map(fn (array $c) => [
            ['valor' => $c['cliente'], 'negrito' => true],
            ['valor' => $c['site'], 'tipo' => 'link'],
            ['valor' => $c['ultima_reuniao'], 'tipo' => 'data'],
            ['valor' => $c['proxima_reuniao'], 'tipo' => 'data'],
            $this->status($c['status']),
        ], $porStatus));

        $xlsx->addSheet('Acessos FTP', self::CORES['ftp'], [
            ['titulo' => 'Cliente', 'largura' => 38],
            ['titulo' => 'Host', 'largura' => 32],
            ['titulo' => 'Usuário', 'largura' => 26],
            ['titulo' => 'Senha', 'largura' => 26],
        ], array_map(fn (array $c) => [
            ['valor' => $c['cliente'], 'negrito' => true],
            $c['host'],
            $c['usuario'],
            $this->senha($c['senha']),
        ], $clientes));

        $xlsx->addSheet('Blogs', self::CORES['blogs'], [
            ['titulo' => 'Cliente', 'largura' => 38],
            ['titulo' => 'Usuário', 'largura' => 28],
            ['titulo' => 'Senha', 'largura' => 26],
        ], array_map(fn (array $b) => [
            ['valor' => $b['cliente'], 'negrito' => true],
            $b['usuario'],
            $this->senha($b['senha']),
        ], $blogs));

        $xlsx->addSheet('WordPress', self::CORES['wordpress'], [
            ['titulo' => 'Cliente', 'largura' => 38],
            ['titulo' => 'Usuário', 'largura' => 28],
            ['titulo' => 'Senha', 'largura' => 26],
        ], array_map(fn (array $s) => [
            ['valor' => $s['cliente'], 'negrito' => true],
            $s['usuario'],
            $this->senha($s['senha']),
        ], $sites));

        $xlsx->addSheet('E-commerce', self::CORES['ecommerce'], [
            ['titulo' => 'Cliente', 'largura' => 34],
            ['titulo' => 'Link da loja', 'largura' => 40],
            ['titulo' => 'E-mail', 'largura' => 32],
            ['titulo' => 'Usuário', 'largura' => 24],
            ['titulo' => 'Código', 'largura' => 16],
            ['titulo' => 'Senha', 'largura' => 24],
        ], array_map(fn (array $l) => [
            ['valor' => $l['cliente'], 'negrito' => true],
            ['valor' => $l['link'], 'tipo' => 'link'],
            $l['email'],
            $l['usuario'],
            $l['codigo'],
            $this->senha($l['senha']),
        ], $lojas));

        $arquivo = tempnam(sys_get_temp_dir(), 'xlsx');

        try {
            $xlsx->save($arquivo, $appName);

            while (ob_get_level() > 0) {
                ob_end_clean();
            }

            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment; filename="gestao-clientes-' . date('Y-m-d-His') . '.xlsx"');
            header('Content-Length: ' . filesize($arquivo));
            header('Cache-Control: no-store');
            readfile($arquivo);
        } finally {
            unlink($arquivo);
        }

        exit;
    }

    /**
     * Aba de abertura: data da exportação, quantidade de registros por aba e legenda dos status.
     */
    private function resumo(string $appName, array $abas): array
    {
        $cabecalho = static fn (string $texto) => ['valor' => $texto, 'negrito' => true, 'cor' => 'FFFFFF', 'fundo' => self::CORES['resumo'], 'borda' => true];
        $limites = FtpAccount::limitesStatus();

        $linhas = [
            [['valor' => $appName, 'negrito' => true, 'tamanho' => 16, 'cor' => self::CORES['clientes']]],
            [['valor' => 'Exportado em ' . date('d/m/Y \à\s H:i') . ' por ' . (Auth::user()['name'] ?? ''), 'cor' => '5A5A5A']],
            [],
            [$cabecalho('Aba'), $cabecalho('Registros')],
        ];

        foreach ($abas as $nome => [$cor, $total]) {
            $linhas[] = [
                ['valor' => $nome, 'negrito' => true, 'cor' => $cor, 'borda' => true],
                ['valor' => $total, 'borda' => true],
            ];
        }

        $linhas[] = [];
        $linhas[] = [$cabecalho('Status (aba Clientes)'), $cabecalho('Critério')];
        $linhas[] = [$this->status('ok') + ['borda' => true], ['valor' => "Última reunião há até {$limites['ok']} meses", 'borda' => true]];
        $linhas[] = [$this->status('moderado') + ['borda' => true], ['valor' => "Última reunião há até {$limites['moderado']} meses", 'borda' => true]];
        $linhas[] = [$this->status('critico') + ['borda' => true], ['valor' => "Mais de {$limites['moderado']} meses ou sem reunião", 'borda' => true]];

        return $linhas;
    }

    private function status(string $status): array
    {
        [$rotulo, $fundo, $cor] = self::STATUS[$status];

        return ['valor' => $rotulo, 'fundo' => $fundo, 'cor' => $cor, 'negrito' => true];
    }

    private function senha(?string $cifrada): string
    {
        if (!$cifrada) {
            return '';
        }

        return Crypto::decrypt($cifrada) ?? '(não foi possível decifrar)';
    }
}
