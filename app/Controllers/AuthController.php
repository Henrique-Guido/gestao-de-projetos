<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Csrf;
use App\Core\Flash;

class AuthController extends Controller
{
    public function showLogin(): void
    {
        if (Auth::check()) {
            $this->redirect('/ftp');
        }

        $this->view('auth/login', [], 'auth');
    }

    public function login(): void
    {
        if (!Csrf::verify($this->input('_token'))) {
            Flash::set('error', 'Sessão expirada. Tente novamente.');
            $this->redirect('/login');
        }

        $name = $this->input('name', '');
        $password = $this->inputRaw('password', '');

        if ($name === '' || $password === '') {
            Flash::set('error', 'Informe nome e senha.');
            $this->redirect('/login');
        }

        if (!Auth::attempt($name, $password)) {
            Flash::set('error', 'Nome ou senha inválidos.');
            $this->redirect('/login');
        }

        $this->redirect('/ftp');
    }

    public function logout(): void
    {
        if (!Csrf::verify($this->input('_token'))) {
            Flash::set('error', 'Sessão expirada. Tente novamente.');
            $this->redirect('/ftp');
        }

        Auth::logout();
        $this->redirect('/login');
    }
}
