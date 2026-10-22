<?php use App\Core\Csrf; ?>
<div class="page-header">
    <h1>Editar Acesso FTP</h1>
</div>

<div class="card" style="max-width: 520px;">
    <form method="POST" action="<?= $appUrl ?>/ftp/<?= $account['id'] ?>">
        <?= Csrf::field() ?>
        <div class="form-group">
            <label class="form-label" for="cliente">Cliente</label>
            <input type="text" id="cliente" name="cliente" class="form-control" required autofocus value="<?= htmlspecialchars($account['cliente']) ?>">
        </div>
        <div class="form-group">
            <label class="form-label" for="ultima_reuniao">Última reunião</label>
            <input type="date" id="ultima_reuniao" name="ultima_reuniao" class="form-control" value="<?= htmlspecialchars($account['ultima_reuniao'] ?? '') ?>">
        </div>
        <div class="form-group">
            <label class="form-label" for="proxima_reuniao">Próxima reunião</label>
            <input type="date" id="proxima_reuniao" name="proxima_reuniao" class="form-control" value="<?= htmlspecialchars($account['proxima_reuniao'] ?? '') ?>">
        </div>
        <div class="form-group">
            <label class="form-label" for="host">Host</label>
            <input type="text" id="host" name="host" class="form-control" value="<?= htmlspecialchars($account['host'] ?? '') ?>">
        </div>
        <div class="form-group">
            <label class="form-label" for="usuario">Usuário</label>
            <input type="text" id="usuario" name="usuario" class="form-control" value="<?= htmlspecialchars($account['usuario'] ?? '') ?>">
        </div>
        <div class="form-group">
            <label class="form-label" for="senha">Senha</label>
            <input type="password" id="senha" name="senha" class="form-control" autocomplete="new-password">
            <p class="form-hint">Deixe em branco para manter a senha atual.</p>
        </div>
        <div style="display: flex; gap: .6rem;">
            <button type="submit" class="btn btn-primary">Atualizar</button>
            <a href="<?= $appUrl ?>/ftp" class="btn btn-outline">Cancelar</a>
        </div>
    </form>
</div>
