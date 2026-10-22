<?php use App\Core\Csrf; ?>
<div class="page-header">
    <h1>Editar Cliente</h1>
</div>

<div class="card" style="max-width: 520px;">
    <form method="POST" action="<?= $appUrl ?>/clientes/<?= $cliente['id'] ?>">
        <?= Csrf::field() ?>
        <div class="form-group">
            <label class="form-label" for="nome">Nome</label>
            <input type="text" id="nome" name="nome" class="form-control" required autofocus value="<?= htmlspecialchars($cliente['cliente']) ?>">
        </div>
        <div class="form-group">
            <label class="form-label" for="ultima_reuniao">Última reunião</label>
            <input type="date" id="ultima_reuniao" name="ultima_reuniao" class="form-control" value="<?= htmlspecialchars($cliente['ultima_reuniao'] ?? '') ?>">
        </div>
        <div class="form-group">
            <label class="form-label" for="proxima_reuniao">Próxima reunião</label>
            <input type="date" id="proxima_reuniao" name="proxima_reuniao" class="form-control" value="<?= htmlspecialchars($cliente['proxima_reuniao'] ?? '') ?>">
            <p class="form-hint">Os dados de acesso FTP são gerenciados na tela de <a href="<?= $appUrl ?>/ftp/<?= $cliente['id'] ?>/edit">Acessos FTP</a>.</p>
        </div>
        <div style="display: flex; gap: .6rem;">
            <button type="submit" class="btn btn-primary">Atualizar</button>
            <a href="<?= $appUrl ?>/clientes" class="btn btn-outline">Cancelar</a>
        </div>
    </form>
</div>
