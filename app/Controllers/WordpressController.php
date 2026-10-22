<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Csrf;
use App\Core\Flash;
use App\Models\Wordpress;

class WordpressController extends Controller
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
        $search = trim($_GET['q'] ?? '');
        $sites = Wordpress::all($search ?: null);

        $this->view('wordpress/index', [
            'sites' => $sites,
            'search' => $search,
        ]);
    }

    public function create(): void
    {
        $this->guardPermissao('pode_criar_clientes');

        $this->view('wordpress/create');
    }

    public function store(): void
    {
        $this->guardPermissao('pode_criar_clientes');
        $this->guardCsrf();

        $errors = $this->validate();

        if ($errors) {
            Flash::set('error', implode(' ', $errors));
            $this->redirect('/wordpress/create');
        }

        Wordpress::create([
            'cliente' => $this->input('cliente'),
            'usuario' => $this->input('usuario'),
            'senha' => $this->input('senha'),
            'user_id' => Auth::id(),
        ]);

        Flash::set('success', 'Acesso ao WordPress cadastrado com sucesso.');
        $this->redirect('/wordpress');
    }

    public function edit(string $id): void
    {
        $this->guardPermissao('pode_editar_clientes');

        $site = Wordpress::find((int) $id);

        if (!$site) {
            Flash::set('error', 'WordPress não encontrado.');
            $this->redirect('/wordpress');
        }

        $this->view('wordpress/edit', ['site' => $site]);
    }

    public function update(string $id): void
    {
        $this->guardPermissao('pode_editar_clientes');
        $this->guardCsrf();

        $site = Wordpress::find((int) $id);

        if (!$site) {
            Flash::set('error', 'WordPress não encontrado.');
            $this->redirect('/wordpress');
        }

        $errors = $this->validate(requireSenha: false);

        if ($errors) {
            Flash::set('error', implode(' ', $errors));
            $this->redirect("/wordpress/{$id}/edit");
        }

        Wordpress::update((int) $id, [
            'cliente' => $this->input('cliente'),
            'usuario' => $this->input('usuario'),
            'senha' => $this->input('senha'),
        ]);

        Flash::set('success', 'Acesso ao WordPress atualizado com sucesso.');
        $this->redirect('/wordpress');
    }

    public function destroy(string $id): void
    {
        if (!Auth::isAdmin()) {
            Flash::set('error', 'Apenas administradores podem excluir acessos WordPress.');
            $this->redirect('/wordpress');
        }

        $this->guardCsrf();

        Wordpress::delete((int) $id);

        Flash::set('success', 'Acesso ao WordPress removido.');
        $this->redirect('/wordpress');
    }

    public function reveal(string $id): void
    {
        if (!Csrf::verify($_POST['_token'] ?? null)) {
            $this->json(['error' => 'Token inválido.'], 419);
        }

        $site = Wordpress::find((int) $id);

        if (!$site) {
            $this->json(['error' => 'Não encontrado.'], 404);
        }

        $senha = Wordpress::decryptPassword($site['senha']) ?? "Key Error";

        $this->json(['senha' => $senha]);
    }

    private function validate(bool $requireSenha = true): array
    {
        $errors = [];

        if ($this->input('cliente', '') === '') {
            $errors[] = 'O campo cliente é obrigatório.';
        }

        if ($this->input('usuario', '') === '') {
            $errors[] = 'O campo usuário é obrigatório.';
        }

        if ($requireSenha && $this->input('senha', '') === '') {
            $errors[] = 'O campo senha é obrigatório.';
        }

        return $errors;
    }

    private function guardCsrf(): void
    {
        if (!Csrf::verify($this->input('_token'))) {
            Flash::set('error', 'Sessão expirada. Tente novamente.');
            $this->redirect('/wordpress');
        }
    }

    private function guardPermissao(string $permissao): void
    {
        if (!Auth::can($permissao)) {
            Flash::set('error', 'Você não tem permissão para realizar essa ação.');
            $this->redirect('/wordpress');
        }
    }
}
