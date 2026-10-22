<?php use App\Core\Csrf; ?>
<div class="page-header">
    <h1>Novo Acesso E-commerce</h1>
</div>

<div class="card" style="max-width: 520px;">
    <form method="POST" action="<?= $appUrl ?>/ecommerce">
        <?= Csrf::field() ?>
        <div class="form-group">
            <label class="form-label" for="cliente">Cliente</label>
            <input type="text" id="cliente" name="cliente" class="form-control" required autofocus>
        </div>
        <div class="form-group">
            <label class="form-label" for="link">Link da loja</label>
            <input type="text" id="link" name="link" class="form-control" required placeholder="https://loja.exemplo.com.br">
        </div>
        <div class="form-group">
            <label class="form-label" for="email">E-mail</label>
            <input type="email" id="email" name="email" class="form-control">
        </div>
        <div class="form-group">
            <label class="form-label" for="usuario">Usuário</label>
            <input type="text" id="usuario" name="usuario" class="form-control">
        </div>
        <div class="form-group">
            <label class="form-label" for="codigo">Código</label>
            <input type="text" id="codigo" name="codigo" class="form-control">
        </div>
        <div class="form-group">
            <label class="form-label" for="senha">Senha</label>
            <input type="password" id="senha" name="senha" class="form-control" required autocomplete="new-password">
        </div>
        <div style="display: flex; gap: .6rem;">
            <button type="submit" class="btn btn-primary">Salvar</button>
            <a href="<?= $appUrl ?>/ecommerce" class="btn btn-outline">Cancelar</a>
        </div>
    </form>
</div>
