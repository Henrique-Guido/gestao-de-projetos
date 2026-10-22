<?php
use App\Core\Csrf;
use App\Core\Flash;

$error = Flash::get('error');
?>
<div class="auth-card__logo">
    <img src="<?= $appUrl ?>/assets/img/logo-publinet.png" alt="Publinet">
</div>
<h1>Gestão de Clientes</h1>
<p class="auth-card__subtitle">Entre com sua conta para continuar</p>

<?php if ($error): ?>
    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<form method="POST" action="<?= $appUrl ?>/login">
    <?= Csrf::field() ?>
    <div class="form-group">
        <label class="form-label" for="name">Nome</label>
        <input type="text" id="name" name="name" class="form-control" placeholder="Nome de usuário" required autofocus>
    </div>
    <div class="form-group">
        <label class="form-label" for="password">Senha</label>
        <input type="password" id="password" name="password" class="form-control" placeholder="Senha" required>
    </div>
    <button type="submit" class="btn btn-primary btn-block">Entrar</button>
</form>
