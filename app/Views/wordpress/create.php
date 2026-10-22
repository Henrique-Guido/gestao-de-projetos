<?php use App\Core\Csrf; ?>
<div class="page-header">
    <h1>Novo Acesso WordPress</h1>
</div>

<div class="card" style="max-width: 520px;">
    <form method="POST" action="<?= $appUrl ?>/wordpress">
        <?= Csrf::field() ?>
        <div class="form-group">
            <label class="form-label" for="cliente">Cliente</label>
            <input type="text" id="cliente" name="cliente" class="form-control" required autofocus value="<?= htmlspecialchars($_POST['cliente'] ?? '') ?>">
        </div>
        <div class="form-group">
            <label class="form-label" for="usuario">Usuário</label>
            <input type="text" id="usuario" name="usuario" class="form-control" required value="<?= htmlspecialchars($_POST['usuario'] ?? '') ?>">
        </div>
        <div class="form-group">
            <label class="form-label" for="senha">Senha</label>
            <input type="password" id="senha" name="senha" class="form-control" required autocomplete="new-password">
        </div>
        <div style="display: flex; gap: .6rem;">
            <button type="submit" class="btn btn-primary">Salvar</button>
            <a href="<?= $appUrl ?>/wordpress" class="btn btn-outline">Cancelar</a>
        </div>
    </form>
</div>
