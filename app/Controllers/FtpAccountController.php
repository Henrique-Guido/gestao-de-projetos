<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Csrf;
use App\Core\Flash;
use App\Models\FtpAccount;

class FtpAccountController extends Controller
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
        $accounts = FtpAccount::all($search ?: null);

        $this->view('ftp/index', [
            'accounts' => $accounts,
            'search' => $search,
        ]);
    }

    public function create(): void
    {
        $this->guardPermissao('pode_criar_clientes');

        $this->view('ftp/create');
    }

    public function store(): void
    {
        $this->guardPermissao('pode_criar_clientes');
        $this->guardCsrf();

        $errors = $this->validate();

        if ($errors) {
            Flash::set('error', implode(' ', $errors));
            $this->redirect('/ftp/create');
        }

        FtpAccount::create([
            'cliente' => $this->input('cliente'),
            'site' => $this->inputUrl('site'),
            'ultima_reuniao' => $this->input('ultima_reuniao'),
            'proxima_reuniao' => $this->input('proxima_reuniao'),
            'host' => $this->input('host'),
            'usuario' => $this->input('usuario'),
            'senha' => $this->inputRaw('senha'),
            'user_id' => Auth::id(),
        ]);

        Flash::set('success', 'Acesso FTP cadastrado com sucesso.');
        $this->redirect('/ftp');
    }

    public function edit(string $id): void
    {
        $this->guardPermissao('pode_editar_clientes');

        $account = FtpAccount::find((int) $id);

        if (!$account) {
            Flash::set('error', 'Acesso não encontrado.');
            $this->redirect('/ftp');
        }

        $this->view('ftp/edit', ['account' => $account]);
    }

    public function update(string $id): void
    {
        $this->guardPermissao('pode_editar_clientes');
        $this->guardCsrf();

        $account = FtpAccount::find((int) $id);

        if (!$account) {
            Flash::set('error', 'Acesso não encontrado.');
            $this->redirect('/ftp');
        }

        $errors = $this->validate(requireSenha: false);

        if ($errors) {
            Flash::set('error', implode(' ', $errors));
            $this->redirect("/ftp/{$id}/edit");
        }

        FtpAccount::update((int) $id, [
            'cliente' => $this->input('cliente'),
            'site' => $this->inputUrl('site'),
            'ultima_reuniao' => $this->input('ultima_reuniao'),
            'proxima_reuniao' => $this->input('proxima_reuniao'),
            'host' => $this->input('host'),
            'usuario' => $this->input('usuario'),
            'senha' => $this->inputRaw('senha'),
        ]);

        Flash::set('success', 'Acesso FTP atualizado com sucesso.');
        $this->redirect('/ftp');
    }

    public function destroy(string $id): void
    {
        if (!Auth::isAdmin()) {
            Flash::set('error', 'Apenas administradores podem excluir clientes.');
            $this->redirect('/ftp');
        }

        $this->guardCsrf();

        FtpAccount::delete((int) $id);

        Flash::set('success', 'Acesso FTP removido.');
        $this->redirect('/ftp');
    }

    public function reveal(string $id): void
    {
        if (!Csrf::verify($this->input('_token'))) {
            $this->json(['error' => 'Token inválido.'], 403);
        }

        $account = FtpAccount::find((int) $id);

        if (!$account) {
            $this->json(['error' => 'Não encontrado.'], 404);
        }

        if (empty($account['senha'])) {
            $this->json(['error' => 'Senha não cadastrada.'], 404);
        }

        $senha = FtpAccount::decryptPassword($account['senha']) ?? "Key Error";

        $this->json(['senha' => $senha]);
    }

    public function test(string $id): void
    {
        if (!Csrf::verify($this->input('_token'))) {
            $this->json(['ok' => false, 'message' => 'Sessão expirada. Recarregue a página e tente novamente.'], 403);
        }

        $account = FtpAccount::find((int) $id);

        if (!$account) {
            $this->json(['ok' => false, 'message' => 'Acesso não encontrado.'], 404);
        }

        if (empty($account['host']) || empty($account['usuario']) || empty($account['senha'])) {
            $this->json(['ok' => false, 'message' => 'Cadastro incompleto: preencha host, usuário e senha antes de testar.']);
        }

        $senha = FtpAccount::decryptPassword($account['senha']);
        $resultado = FtpAccount::testConnection($account['host'], $account['usuario'], (string) $senha);

        $this->json($resultado);
    }

    private function validate(bool $requireSenha = true): array
    {
        $errors = [];

        if ($this->input('cliente', '') === '') {
            $errors[] = 'O campo cliente é obrigatório.';
        } elseif (mb_strlen($this->input('cliente')) > 150) {
            $errors[] = 'O campo cliente deve ter no máximo 150 caracteres.';
        }

        $site = $this->inputUrl('site');
        if ($site !== '' && !$this->isValidUrl($site)) {
            $errors[] = 'Informe um site válido.';
        }

        if (mb_strlen($this->input('host', '')) > 255) {
            $errors[] = 'O campo host deve ter no máximo 255 caracteres.';
        }

        if (mb_strlen($this->input('usuario', '')) > 150) {
            $errors[] = 'O campo usuário deve ter no máximo 150 caracteres.';
        }

        foreach (['ultima_reuniao' => 'última reunião', 'proxima_reuniao' => 'próxima reunião'] as $campo => $rotulo) {
            $data = $this->input($campo, '');
            if ($data !== '' && !$this->isValidDate($data)) {
                $errors[] = "A data de {$rotulo} é inválida.";
            }
        }

        /*
         * if ($this->input('host', '') === '') {
         *     $errors[] = 'O campo host é obrigatório.';
         * }
         *
         * if ($this->input('usuario', '') === '') {
         *     $errors[] = 'O campo usuário é obrigatório.';
         * }
         *
         * if ($requireSenha && $this->input('senha', '') === '') {
         *     $errors[] = 'O campo senha é obrigatório.';
         * }
         */

        return $errors;
    }

    private function guardCsrf(): void
    {
        if (!Csrf::verify($this->input('_token'))) {
            Flash::set('error', 'Sessão expirada. Tente novamente.');
            $this->redirect('/ftp');
        }
    }

    private function guardPermissao(string $permissao): void
    {
        if (!Auth::can($permissao)) {
            Flash::set('error', 'Você não tem permissão para realizar essa ação.');
            $this->redirect('/ftp');
        }
    }
}
