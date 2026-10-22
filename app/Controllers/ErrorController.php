<?php

namespace App\Controllers;

use App\Core\Controller;

class ErrorController extends Controller
{
    public function notFound(): void
    {
        $this->view('errors/404', [], null);
    }

    public function serverError(): void
    {
        $this->view('errors/500', [], null);
    }
}
