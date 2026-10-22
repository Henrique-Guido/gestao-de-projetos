<?php use App\Core\Csrf; ?>
<div class="page-header">
    <h1>Editar usuário</h1>
</div>

<div class="card" style="max-width: 520px;">
    <form method="POST" action="<?= $appUrl ?>/usuarios/<?= $usuario['id'] ?>">
        <?= Csrf::field() ?>
        <div class="form-group">
            <label class="form-label" for="name">Nome</label>
            <input type="text" id="name" name="name" class="form-control" required autofocus value="<?= htmlspecialchars($usuario['name']) ?>">
        </div>
        <div class="form-group">
            <label class="form-label" for="email">E-mail</label>
            <input type="email" id="email" name="email" class="form-control" required value="<?= htmlspecialchars($usuario['email']) ?>">
        </div>
        <div class="form-group">
            <label class="form-label" for="password">Senha</label>
            <input type="password" id="password" name="password" class="form-control" autocomplete="new-password">
            <p class="form-hint">Deixe em branco para manter a senha atual.</p>
        </div>
        <div class="form-group">
            <label class="form-label" for="role">Papel</label>
            <select id="role" name="role" class="form-control">
                <option value="user" <?= $usuario['role'] === 'user' ? 'selected' : '' ?>>Usuário</option>
                <option value="admin" <?= $usuario['role'] === 'admin' ? 'selected' : '' ?>>Administrador</option>
            </select>
            <p class="form-hint">Administradores têm acesso total, independente das permissões abaixo.</p>
        </div>
        <div class="form-group">
            <label class="form-label">
                <input type="checkbox" name="pode_criar_clientes" value="1" <?= $usuario['pode_criar_clientes'] ? 'checked' : '' ?>> Pode criar clientes
            </label>
        </div>
        <div class="form-group">
            <label class="form-label">
                <input type="checkbox" name="pode_editar_clientes" value="1" <?= $usuario['pode_editar_clientes'] ? 'checked' : '' ?>> Pode editar clientes
            </label>
        </div>
        <div style="display: flex; gap: .6rem;">
            <button type="submit" class="btn btn-primary">Atualizar</button>
            <a href="<?= $appUrl ?>/usuarios" class="btn btn-outline">Cancelar</a>
        </div>
    </form>
</div>
