<?php

/**
 * Script de linha de comando para criar um usuário de acesso ao sistema.
 * Uso: php database/create-user.php "Nome" "email@publinet1.com.br" "senha"
 */

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Models\User;

[$name, $email, $password] = [$argv[1] ?? null, $argv[2] ?? null, $argv[3] ?? null];

if (!$name || !$email || !$password) {
    fwrite(STDERR, "Uso: php database/create-user.php \"Nome\" \"email@exemplo.com\" \"senha\"\n");
    exit(1);
}

if (strlen($password) < 8) {
    fwrite(STDERR, "A senha deve ter pelo menos 8 caracteres.\n");
    exit(1);
}

if (User::findByName($name)) {
    fwrite(STDERR, "Já existe um usuário com este nome.\n");
    exit(1);
}

if (User::findByEmail($email)) {
    fwrite(STDERR, "Já existe um usuário com este e-mail.\n");
    exit(1);
}

$id = User::create([
    'name' => $name,
    'email' => $email,
    'password' => $password,
    'role' => 'user',
    'pode_criar_clientes' => false,
    'pode_editar_clientes' => false,
]);

echo "Usuário #{$id} criado com sucesso ({$email}).\n";
