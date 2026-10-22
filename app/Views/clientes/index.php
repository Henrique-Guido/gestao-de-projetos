<?php
use App\Core\Auth;
use App\Core\Csrf;

$labels = ['ok' => 'Ok', 'moderado' => 'Moderado', 'critico' => 'Crítico'];

$formatarData = static function (?string $data): string {
    if (!$data) {
        return '—';
    }

    return (new DateTimeImmutable($data))->format('d/m/Y');
};

$buildUrl = static function (string $status) use ($appUrl, $search): string {
    $params = array_filter(['q' => $search ?: null, 'status' => $status ?: null]);

    return $appUrl . '/clientes' . ($params ? '?' . http_build_query($params) : '');
};
?>
<div class="page-header">
    <div>
        <h1>Clientes</h1>
        <p class="form-hint">Painel de acompanhamento de reuniões com clientes.</p>
    </div>
    <?php if (Auth::can('pode_criar_clientes')): ?>
        <a href="<?= $appUrl ?>/ftp/create" class="btn btn-primary">+ Novo cliente</a>
    <?php endif; ?>
</div>

<div class="card">
    <div class="filters-row">
        <form method="GET" action="<?= $appUrl ?>/clientes" class="search-form">
            <input type="hidden" name="status" value="<?= htmlspecialchars($statusFiltro) ?>">
            <input type="text" name="q" class="form-control" placeholder="Buscar por cliente..." value="<?= htmlspecialchars($search) ?>">
            <button type="submit" class="btn btn-outline">Buscar</button>
            <?php if ($statusFiltro !== '' || $search !== ''): ?>
                <a href="<?= $appUrl ?>/clientes" class="btn btn-outline">Limpar</a>
            <?php endif; ?>
        </form>

        <div class="filter-chips">
            <a class="filter-chip<?= $statusFiltro === '' ? ' filter-chip--active' : '' ?>" href="<?= $buildUrl('') ?>">
                <span class="filter-chip__count"><?= $total ?></span> Todos
            </a>
            <a class="filter-chip filter-chip--critico<?= $statusFiltro === 'critico' ? ' filter-chip--active' : '' ?>" href="<?= $buildUrl('critico') ?>">
                <span class="filter-chip__count"><?= $contagem['critico'] ?></span> Crítico
            </a>
            <a class="filter-chip filter-chip--moderado<?= $statusFiltro === 'moderado' ? ' filter-chip--active' : '' ?>" href="<?= $buildUrl('moderado') ?>">
                <span class="filter-chip__count"><?= $contagem['moderado'] ?></span> Moderado
            </a>
            <a class="filter-chip filter-chip--ok<?= $statusFiltro === 'ok' ? ' filter-chip--active' : '' ?>" href="<?= $buildUrl('ok') ?>">
                <span class="filter-chip__count"><?= $contagem['ok'] ?></span> Ok
            </a>
        </div>
    </div>

    <?php if (empty($clientes)): ?>
        <div class="empty-state">
            <p>
                Nenhum cliente encontrado
                <?= $search ? ' para "' . htmlspecialchars($search) . '"' : '' ?>
                <?= $statusFiltro ? ' com status "' . htmlspecialchars($labels[$statusFiltro]) . '"' : '' ?>.
            </p>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Nome</th>
                        <th>Última reunião</th>
                        <th>Próxima reunião</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($clientes as $cliente): ?>
                        <tr class="row-status row-status--<?= $cliente['status'] ?>">
                            <td class="cliente-cell"><strong><?= htmlspecialchars($cliente['cliente']) ?></strong></td>
                            <td><?= $formatarData($cliente['ultima_reuniao']) ?></td>
                            <td><?= $formatarData($cliente['proxima_reuniao']) ?></td>
                            <td>
                                <span class="status-badge status-badge--<?= $cliente['status'] ?>">
                                    <?= $labels[$cliente['status']] ?>
                                </span>
                            </td>
                            <td class="actions-cell">
                                <?php if (Auth::can('pode_editar_clientes')): ?>
                                    <a class="btn btn-sm btn-outline" href="<?= $appUrl ?>/clientes/<?= $cliente['id'] ?>/edit">Editar</a>
                                <?php endif; ?>
                                <?php if (Auth::isAdmin()): ?>
                                    <form method="POST" action="<?= $appUrl ?>/ftp/<?= $cliente['id'] ?>/delete"
                                        data-confirm="Remover o cliente <?= htmlspecialchars($cliente['cliente']) ?>?">
                                        <?= Csrf::field() ?>
                                        <button type="submit" class="btn btn-sm btn-danger">Excluir</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
