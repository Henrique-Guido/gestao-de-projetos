<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Csrf;
use App\Core\Flash;
use App\Models\User;

class UserController extends Controller
{
    public function __construct()
    {
        if (!Auth::check()) {
            header('Location: ' . (require dirname(__DIR__) . '/Config/config.php')['app']['url'] . '/login');
            exit;
        }

        if (!Auth::isAdmin()) {
            Flash::set('error', 'Apenas administradores podem acessar essa área.');
            $this->redirect('/clientes');
        }
    }

    public function index(): void
    {
        $this->view('usuarios/index', ['usuarios' => User::all()]);
    }

    public function create(): void
    {
        $this->view('usuarios/create');
    }

    public function store(): void
    {
        $this->guardCsrf();

        $errors = $this->validate();

        if ($this->input('password', '') === '' || strlen($this->input('password', '')) < 8) {
            $errors[] = 'A senha deve ter pelo menos 8 caracteres.';
        }

        if (!$errors && User::findByName($this->input('name'))) {
            $errors[] = 'Já existe um usuário com este nome.';
        }

        if (!$errors && User::findByEmail($this->input('email'))) {
            $errors[] = 'Já existe um usuário com este e-mail.';
        }

        if ($errors) {
            Flash::set('error', implode(' ', $errors));
            $this->redirect('/usuarios/create');
        }

        User::create([
            'name' => $this->input('name'),
            'email' => $this->input('email'),
            'password' => $this->input('password'),
            'role' => $this->input('role'),
            'pode_criar_clientes' => $this->input('pode_criar_clientes') !== null,
            'pode_editar_clientes' => $this->input('pode_editar_clientes') !== null,
        ]);

        Flash::set('success', 'Usuário criado com sucesso.');
        $this->redirect('/usuarios');
    }

    public function edit(string $id): void
    {
        $usuario = User::findById((int) $id);

        if (!$usuario) {
            Flash::set('error', 'Usuário não encontrado.');
            $this->redirect('/usuarios');
        }

        $this->view('usuarios/edit', ['usuario' => $usuario]);
    }

    public function update(string $id): void
    {
        $this->guardCsrf();

        $usuario = User::findById((int) $id);

        if (!$usuario) {
            Flash::set('error', 'Usuário não encontrado.');
            $this->redirect('/usuarios');
        }

        $errors = $this->validate();

        $novoNome = $this->input('name');
        $outroComMesmoNome = User::findByName($novoNome);
        if ($outroComMesmoNome && (int) $outroComMesmoNome['id'] !== (int) $id) {
            $errors[] = 'Já existe um usuário com este nome.';
        }

        $novoEmail = $this->input('email');
        $outroComMesmoEmail = User::findByEmail($novoEmail);
        if ($outroComMesmoEmail && (int) $outroComMesmoEmail['id'] !== (int) $id) {
            $errors[] = 'Já existe um usuário com este e-mail.';
        }

        $novoRole = $this->input('role');
        if ($usuario['role'] === 'admin' && $novoRole !== 'admin' && User::countAdmins() <= 1) {
            $errors[] = 'Não é possível remover o último administrador do sistema.';
        }

        if ($errors) {
            Flash::set('error', implode(' ', $errors));
            $this->redirect("/usuarios/{$id}/edit");
        }

        User::update((int) $id, [
            'name' => $this->input('name'),
            'email' => $this->input('email'),
            'password' => $this->input('password'),
            'role' => $novoRole,
            'pode_criar_clientes' => $this->input('pode_criar_clientes') !== null,
            'pode_editar_clientes' => $this->input('pode_editar_clientes') !== null,
        ]);

        Flash::set('success', 'Usuário atualizado com sucesso.');
        $this->redirect('/usuarios');
    }

    public function destroy(string $id): void
    {
        $this->guardCsrf();

        $usuario = User::findById((int) $id);

        if ($usuario && (int) $id === Auth::id()) {
            Flash::set('error', 'Você não pode remover o seu próprio usuário.');
            $this->redirect('/usuarios');
        }

        if ($usuario && $usuario['role'] === 'admin' && User::countAdmins() <= 1) {
            Flash::set('error', 'Não é possível remover o último administrador do sistema.');
            $this->redirect('/usuarios');
        }

        User::delete((int) $id);

        Flash::set('success', 'Usuário removido.');
        $this->redirect('/usuarios');
    }

    private function validate(): array
    {
        $errors = [];

        if ($this->input('name', '') === '') {
            $errors[] = 'O campo nome é obrigatório.';
        }

        if ($this->input('email', '') === '') {
            $errors[] = 'O campo e-mail é obrigatório.';
        }

        if (!in_array($this->input('role'), ['admin', 'user'], true)) {
            $errors[] = 'Papel de usuário inválido.';
        }

        return $errors;
    }

    private function guardCsrf(): void
    {
        if (!Csrf::verify($this->input('_token'))) {
            Flash::set('error', 'Sessão expirada. Tente novamente.');
            $this->redirect('/usuarios');
        }
    }
}
