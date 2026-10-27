<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Csrf;
use App\Core\Flash;
use App\Models\Ecommerce;

class EcommerceController extends Controller
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
        $lojas = Ecommerce::all($search ?: null);

        $this->view('ecommerce/index', [
            'lojas' => $lojas,
            'search' => $search,
        ]);
    }

    public function create(): void
    {
        $this->guardPermissao('pode_criar_clientes');

        $this->view('ecommerce/create');
    }

    public function store(): void
    {
        $this->guardPermissao('pode_criar_clientes');
        $this->guardCsrf();

        $errors = $this->validate();

        if ($errors) {
            Flash::set('error', implode(' ', $errors));
            $this->redirect('/ecommerce/create');
        }

        Ecommerce::create([
            'cliente' => $this->input('cliente'),
            'link' => $this->inputUrl('link'),
            'email' => $this->input('email'),
            'usuario' => $this->input('usuario'),
            'codigo' => $this->input('codigo'),
            'senha' => $this->inputRaw('senha'),
            'user_id' => Auth::id(),
        ]);

        Flash::set('success', 'Acesso ao e-commerce cadastrado com sucesso.');
        $this->redirect('/ecommerce');
    }

    public function edit(string $id): void
    {
        $this->guardPermissao('pode_editar_clientes');

        $loja = Ecommerce::find((int) $id);

        if (!$loja) {
            Flash::set('error', 'E-commerce não encontrado.');
            $this->redirect('/ecommerce');
        }

        $this->view('ecommerce/edit', ['loja' => $loja]);
    }

    public function update(string $id): void
    {
        $this->guardPermissao('pode_editar_clientes');
        $this->guardCsrf();

        $loja = Ecommerce::find((int) $id);

        if (!$loja) {
            Flash::set('error', 'E-commerce não encontrado.');
            $this->redirect('/ecommerce');
        }

        $errors = $this->validate(requireSenha: false);

        if ($errors) {
            Flash::set('error', implode(' ', $errors));
            $this->redirect("/ecommerce/{$id}/edit");
        }

        Ecommerce::update((int) $id, [
            'cliente' => $this->input('cliente'),
            'link' => $this->inputUrl('link'),
            'email' => $this->input('email'),
            'usuario' => $this->input('usuario'),
            'codigo' => $this->input('codigo'),
            'senha' => $this->inputRaw('senha'),
        ]);

        Flash::set('success', 'Acesso ao e-commerce atualizado com sucesso.');
        $this->redirect('/ecommerce');
    }

    public function destroy(string $id): void
    {
        if (!Auth::isAdmin()) {
            Flash::set('error', 'Apenas administradores podem excluir acessos de e-commerce.');
            $this->redirect('/ecommerce');
        }

        $this->guardCsrf();

        Ecommerce::delete((int) $id);

        Flash::set('success', 'Acesso ao e-commerce removido.');
        $this->redirect('/ecommerce');
    }

    public function reveal(string $id): void
    {
        if (!Csrf::verify($this->input('_token'))) {
            $this->json(['error' => 'Token inválido.'], 403);
        }

        $loja = Ecommerce::find((int) $id);

        if (!$loja) {
            $this->json(['error' => 'Não encontrado.'], 404);
        }

        $senha = Ecommerce::decryptPassword($loja['senha']) ?? "Key Error";

        $this->json(['senha' => $senha]);
    }

    private function validate(bool $requireSenha = true): array
    {
        $errors = [];

        if ($this->input('cliente', '') === '') {
            $errors[] = 'O campo cliente é obrigatório.';
        } elseif (mb_strlen($this->input('cliente')) > 150) {
            $errors[] = 'O campo cliente deve ter no máximo 150 caracteres.';
        }

        $link = $this->inputUrl('link');
        if ($link === '') {
            $errors[] = 'O campo link da loja é obrigatório.';
        } elseif (!$this->isValidUrl($link)) {
            $errors[] = 'Informe um link da loja válido.';
        }

        $email = $this->input('email', '');
        if ($email !== '' && (mb_strlen($email) > 150 || !filter_var($email, FILTER_VALIDATE_EMAIL))) {
            $errors[] = 'Informe um e-mail válido.';
        }

        if (mb_strlen($this->input('usuario', '')) > 150) {
            $errors[] = 'O campo usuário deve ter no máximo 150 caracteres.';
        }

        if (mb_strlen($this->input('codigo', '')) > 100) {
            $errors[] = 'O campo código deve ter no máximo 100 caracteres.';
        }

        if ($requireSenha && $this->inputRaw('senha', '') === '') {
            $errors[] = 'O campo senha é obrigatório.';
        }

        return $errors;
    }

    private function guardCsrf(): void
    {
        if (!Csrf::verify($this->input('_token'))) {
            Flash::set('error', 'Sessão expirada. Tente novamente.');
            $this->redirect('/ecommerce');
        }
    }

    private function guardPermissao(string $permissao): void
    {
        if (!Auth::can($permissao)) {
            Flash::set('error', 'Você não tem permissão para realizar essa ação.');
            $this->redirect('/ecommerce');
        }
    }
}
