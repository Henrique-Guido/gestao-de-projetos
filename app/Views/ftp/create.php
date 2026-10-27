<?php use App\Core\Csrf; ?>
<div class="page-header">
    <h1>Novo Cliente</h1>
</div>

<div class="card" style="max-width: 520px;">
    <form method="POST" action="<?= $appUrl ?>/ftp">
        <?= Csrf::field() ?>
        <div class="form-group">
            <label class="form-label" for="cliente">Cliente</label>
            <input type="text" id="cliente" name="cliente" class="form-control" required autofocus value="<?= htmlspecialchars($_POST['cliente'] ?? '') ?>">
        </div>
        <div class="form-group">
            <label class="form-label" for="site">Site</label>
            <input type="text" id="site" name="site" class="form-control" placeholder="https://www.exemplo.com.br">
        </div>
        <div class="form-group">
            <label class="form-label" for="ultima_reuniao">Última reunião</label>
            <input type="date" id="ultima_reuniao" name="ultima_reuniao" class="form-control" value="<?= htmlspecialchars($_POST['ultima_reuniao'] ?? '') ?>">
        </div>
        <div class="form-group">
            <label class="form-label" for="proxima_reuniao">Próxima reunião</label>
            <input type="date" id="proxima_reuniao" name="proxima_reuniao" class="form-control" value="<?= htmlspecialchars($_POST['proxima_reuniao'] ?? '') ?>">
        </div>
        <div class="form-group">
            <label class="form-label" for="host">Host</label>
            <input type="text" id="host" name="host" class="form-control" placeholder="ftp.exemplo.com.br" value="<?= htmlspecialchars($_POST['host'] ?? '') ?>">
        </div>
        <div class="form-group">
            <label class="form-label" for="usuario">Usuário</label>
            <input type="text" id="usuario" name="usuario" class="form-control" value="<?= htmlspecialchars($_POST['usuario'] ?? '') ?>">
        </div>
        <div class="form-group">
            <label class="form-label" for="senha">Senha</label>
            <input type="password" id="senha" name="senha" class="form-control" autocomplete="new-password">
            <p class="form-hint">Opcional — preencha somente se o cliente utilizar FTP.</p>
        </div>
        <div style="display: flex; gap: .6rem;">
            <button type="submit" class="btn btn-primary">Salvar</button>
            <a href="<?= $appUrl ?>/ftp" class="btn btn-outline">Cancelar</a>
        </div>
    </form>
</div>
