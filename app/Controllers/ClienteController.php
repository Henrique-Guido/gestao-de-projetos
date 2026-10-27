<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Csrf;
use App\Core\Flash;
use App\Models\Configuracao;
use App\Models\FtpAccount;

class ClienteController extends Controller
{
    public function __construct()
    {
        if (!Auth::check()) {
            header('Location: ' . (require dirname(__DIR__) . '/Config/config.php')['app']['url'] . '/login');
            exit;
        }
    }

    public function index(): void
    {
        $search = $this->query('q');
        $statusValidos = ['ok', 'moderado', 'critico'];
        $statusFiltro = in_array($this->query('status'), $statusValidos, true) ? $this->query('status') : '';

        $todos = FtpAccount::all($search ?: null);

        $severidade = ['critico' => 0, 'moderado' => 1, 'ok' => 2];
        usort($todos, static fn (array $a, array $b) => $severidade[$a['status']] <=> $severidade[$b['status']]
            ?: $a['cliente'] <=> $b['cliente']);

        $contagem = ['ok' => 0, 'moderado' => 0, 'critico' => 0];
        foreach ($todos as $cliente) {
            $contagem[$cliente['status']]++;
        }

        $clientes = $statusFiltro === ''
            ? $todos
            : array_values(array_filter($todos, static fn (array $c) => $c['status'] === $statusFiltro));

        $this->view('clientes/index', [
            'clientes' => $clientes,
            'total' => count($todos),
            'contagem' => $contagem,
            'search' => $search,
            'statusFiltro' => $statusFiltro,
            'limites' => FtpAccount::limitesStatus(),
        ]);
    }

    public function updateLimites(): void
    {
        if (!Auth::isAdmin()) {
            Flash::set('error', 'Apenas administradores podem alterar os critérios de status.');
            $this->redirect('/clientes');
        }

        $this->guardCsrf();

        $faixa = ['options' => ['min_range' => 1, 'max_range' => 120]];
        $ok = filter_var($this->input('meses_ok'), FILTER_VALIDATE_INT, $faixa);
        $moderado = filter_var($this->input('meses_moderado'), FILTER_VALIDATE_INT, $faixa);

        if ($ok === false || $moderado === false) {
            Flash::set('error', 'Informe os meses como números inteiros entre 1 e 120.');
            $this->redirect('/clientes');
        }

        if ($ok >= $moderado) {
            Flash::set('error', 'O limite de "Ok" deve ser menor que o de "Moderado".');
            $this->redirect('/clientes');
        }

        Configuracao::set('status_meses_ok', (string) $ok);
        Configuracao::set('status_meses_moderado', (string) $moderado);

        Flash::set('success', 'Critérios de status atualizados.');
        $this->redirect('/clientes');
    }

    public function edit(string $id): void
    {
        $this->guardPermissao();

        $cliente = FtpAccount::find((int) $id);

        if (!$cliente) {
            Flash::set('error', 'Cliente não encontrado.');
            $this->redirect('/clientes');
        }

        $this->view('clientes/edit', ['cliente' => $cliente]);
    }

    public function update(string $id): void
    {
        $this->guardPermissao();
        $this->guardCsrf();

        $cliente = FtpAccount::find((int) $id);

        if (!$cliente) {
            Flash::set('error', 'Cliente não encontrado.');
            $this->redirect('/clientes');
        }

        $errors = $this->validate();

        if ($errors) {
            Flash::set('error', implode(' ', $errors));
            $this->redirect("/clientes/{$id}/edit");
        }

        FtpAccount::updateCliente((int) $id, [
            'cliente' => $this->input('nome'),
            'site' => $this->inputUrl('site'),
            'ultima_reuniao' => $this->input('ultima_reuniao'),
            'proxima_reuniao' => $this->input('proxima_reuniao'),
        ]);

        Flash::set('success', 'Cliente atualizado com sucesso.');
        $this->redirect('/clientes');
    }

    private function validate(): array
    {
        $errors = [];

        if ($this->input('nome', '') === '') {
            $errors[] = 'O campo nome é obrigatório.';
        } elseif (mb_strlen($this->input('nome')) > 150) {
            $errors[] = 'O campo nome deve ter no máximo 150 caracteres.';
        }

        $site = $this->inputUrl('site');
        if ($site !== '' && !$this->isValidUrl($site)) {
            $errors[] = 'Informe um site válido.';
        }

        foreach (['ultima_reuniao' => 'última reunião', 'proxima_reuniao' => 'próxima reunião'] as $campo => $rotulo) {
            $data = $this->input($campo, '');
            if ($data !== '' && !$this->isValidDate($data)) {
                $errors[] = "A data de {$rotulo} é inválida.";
            }
        }

        return $errors;
    }

    private function guardCsrf(): void
    {
        if (!Csrf::verify($this->input('_token'))) {
            Flash::set('error', 'Sessão expirada. Tente novamente.');
            $this->redirect('/clientes');
        }
    }

    private function guardPermissao(): void
    {
        if (!Auth::can('pode_editar_clientes')) {
            Flash::set('error', 'Você não tem permissão para editar clientes.');
            $this->redirect('/clientes');
        }
    }
}
