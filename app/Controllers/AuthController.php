<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Csrf;
use App\Core\Flash;
use App\Core\LoginThrottle;

class AuthController extends Controller
{
    public function showLogin(): void
    {
        if (Auth::check()) {
            $this->redirect('/clientes');
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

        // REMOTE_ADDR é o IP real do visitante (não há proxy na frente). Headers como
        // X-Forwarded-For não são usados porque o próprio atacante pode forjá-los.
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');

        $espera = LoginThrottle::segundosBloqueado($name, $ip);
        if ($espera > 0) {
            $minutos = (int) ceil($espera / 60);
            Flash::set('error', "Muitas tentativas de login. Tente novamente em {$minutos} minuto" . ($minutos > 1 ? 's' : '') . '.');
            $this->redirect('/login');
        }

        if (!Auth::attempt($name, $password)) {
            LoginThrottle::registrarFalha($name, $ip);
            Flash::set('error', 'Nome ou senha inválidos.');
            $this->redirect('/login');
        }

        LoginThrottle::registrarSucesso($name, $ip);
        $this->redirect('/clientes');
    }

    public function logout(): void
    {
        if (!Csrf::verify($this->input('_token'))) {
            Flash::set('error', 'Sessão expirada. Tente novamente.');
            $this->redirect('/clientes');
        }

        Auth::logout();
        $this->redirect('/login');
    }
}
