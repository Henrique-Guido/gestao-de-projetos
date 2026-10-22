<?php use App\Core\Csrf; ?>
<div class="page-header">
    <h1>Novo usuário</h1>
</div>

<div class="card" style="max-width: 520px;">
    <form method="POST" action="<?= $appUrl ?>/usuarios">
        <?= Csrf::field() ?>
        <div class="form-group">
            <label class="form-label" for="name">Nome</label>
            <input type="text" id="name" name="name" class="form-control" required autofocus value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">
        </div>
        <div class="form-group">
            <label class="form-label" for="email">E-mail</label>
            <input type="email" id="email" name="email" class="form-control" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
        </div>
        <div class="form-group">
            <label class="form-label" for="password">Senha</label>
            <input type="password" id="password" name="password" class="form-control" required autocomplete="new-password">
            <p class="form-hint">Mínimo de 8 caracteres.</p>
        </div>
        <div class="form-group">
            <label class="form-label" for="role">Papel</label>
            <select id="role" name="role" class="form-control">
                <option value="user">Usuário</option>
                <option value="admin">Administrador</option>
            </select>
            <p class="form-hint">Administradores têm acesso total, independente das permissões abaixo.</p>
        </div>
        <div class="form-group">
            <label class="form-label">
                <input type="checkbox" name="pode_criar_clientes" value="1"> Pode criar clientes
            </label>
        </div>
        <div class="form-group">
            <label class="form-label">
                <input type="checkbox" name="pode_editar_clientes" value="1"> Pode editar clientes
            </label>
        </div>
        <div style="display: flex; gap: .6rem;">
            <button type="submit" class="btn btn-primary">Salvar</button>
            <a href="<?= $appUrl ?>/usuarios" class="btn btn-outline">Cancelar</a>
        </div>
    </form>
</div>
