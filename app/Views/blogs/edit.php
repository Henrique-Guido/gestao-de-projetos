<?php use App\Core\Csrf; ?>
<div class="page-header">
    <h1>Editar Blog</h1>
</div>

<div class="card" style="max-width: 520px;">
    <form method="POST" action="<?= $appUrl ?>/blogs/<?= $blog['id'] ?>">
        <?= Csrf::field() ?>
        <div class="form-group">
            <label class="form-label" for="cliente">Cliente</label>
            <input type="text" id="cliente" name="cliente" class="form-control" required autofocus value="<?= htmlspecialchars($blog['cliente']) ?>">
        </div>
        <div class="form-group">
            <label class="form-label" for="usuario">Usuário</label>
            <input type="text" id="usuario" name="usuario" class="form-control" required value="<?= htmlspecialchars($blog['usuario']) ?>">
        </div>
        <div class="form-group">
            <label class="form-label" for="senha">Senha</label>
            <input type="password" id="senha" name="senha" class="form-control" autocomplete="new-password">
            <p class="form-hint">Deixe em branco para manter a senha atual.</p>
        </div>
        <div style="display: flex; gap: .6rem;">
            <button type="submit" class="btn btn-primary">Atualizar</button>
            <a href="<?= $appUrl ?>/blogs" class="btn btn-outline">Cancelar</a>
        </div>
    </form>
</div>
