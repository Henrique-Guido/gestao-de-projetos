<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Csrf;
use App\Core\Flash;
use App\Models\Blog;

class BlogController extends Controller
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
        $blogs = Blog::all($search ?: null);

        $this->view('blogs/index', [
            'blogs' => $blogs,
            'search' => $search,
        ]);
    }

    public function create(): void
    {
        $this->guardPermissao('pode_criar_clientes');

        $this->view('blogs/create');
    }

    public function store(): void
    {
        $this->guardPermissao('pode_criar_clientes');
        $this->guardCsrf();

        $errors = $this->validate();

        if ($errors) {
            Flash::set('error', implode(' ', $errors));
            $this->redirect('/blogs/create');
        }

        Blog::create([
            'cliente' => $this->input('cliente'),
            'usuario' => $this->input('usuario'),
            'senha' => $this->inputRaw('senha'),
            'user_id' => Auth::id(),
        ]);

        Flash::set('success', 'Acesso ao blog cadastrado com sucesso.');
        $this->redirect('/blogs');
    }

    public function edit(string $id): void
    {
        $this->guardPermissao('pode_editar_clientes');

        $blog = Blog::find((int) $id);

        if (!$blog) {
            Flash::set('error', 'Blog não encontrado.');
            $this->redirect('/blogs');
        }

        $this->view('blogs/edit', ['blog' => $blog]);
    }

    public function update(string $id): void
    {
        $this->guardPermissao('pode_editar_clientes');
        $this->guardCsrf();

        $blog = Blog::find((int) $id);

        if (!$blog) {
            Flash::set('error', 'Blog não encontrado.');
            $this->redirect('/blogs');
        }

        $errors = $this->validate(requireSenha: false);

        if ($errors) {
            Flash::set('error', implode(' ', $errors));
            $this->redirect("/blogs/{$id}/edit");
        }

        Blog::update((int) $id, [
            'cliente' => $this->input('cliente'),
            'usuario' => $this->input('usuario'),
            'senha' => $this->inputRaw('senha'),
        ]);

        Flash::set('success', 'Acesso ao blog atualizado com sucesso.');
        $this->redirect('/blogs');
    }

    public function destroy(string $id): void
    {
        if (!Auth::isAdmin()) {
            Flash::set('error', 'Apenas administradores podem excluir blogs.');
            $this->redirect('/blogs');
        }

        $this->guardCsrf();

        Blog::delete((int) $id);

        Flash::set('success', 'Acesso ao blog removido.');
        $this->redirect('/blogs');
    }

    public function reveal(string $id): void
    {
        if (!Csrf::verify($this->input('_token'))) {
            $this->json(['error' => 'Token inválido.'], 403);
        }

        $blog = Blog::find((int) $id);

        if (!$blog) {
            $this->json(['error' => 'Não encontrado.'], 404);
        }

        $senha = Blog::decryptPassword($blog['senha']) ?? "Key Error";

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

        if ($this->input('usuario', '') === '') {
            $errors[] = 'O campo usuário é obrigatório.';
        } elseif (mb_strlen($this->input('usuario')) > 150) {
            $errors[] = 'O campo usuário deve ter no máximo 150 caracteres.';
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
            $this->redirect('/blogs');
        }
    }

    private function guardPermissao(string $permissao): void
    {
        if (!Auth::can($permissao)) {
            Flash::set('error', 'Você não tem permissão para realizar essa ação.');
            $this->redirect('/blogs');
        }
    }
}
