<?php
$labels = ['critico' => 'Crítico', 'moderado' => 'Moderado', 'ok' => 'Ok'];

$formatarData = static function (?string $data): string {
    if (!$data) {
        return '—';
    }

    return (new DateTimeImmutable($data))->format('d/m/Y');
};

$meses = static function (?int $n): string {
    if ($n === null) {
        return 'Sem reunião registrada';
    }
    if ($n === 0) {
        return 'Menos de 1 mês';
    }

    return $n . ($n === 1 ? ' mês' : ' meses');
};

$percentual = static fn (int $parte): string => $total > 0 ? round($parte / $total * 100) . '%' : '0%';

// Posição horizontal (em %) de um valor de meses na escala do gráfico.
$posicao = static fn (int $valor): string => round($valor / $escalaMax * 100, 2) . '%';

$resumoDistribuicao = implode(', ', array_map(
    static fn (string $s): string => "{$labels[$s]}: {$contagem[$s]} ({$percentual($contagem[$s])})",
    array_keys($labels)
));
?>
<div class="page-header">
    <div>
        <h1>Status dos clientes</h1>
        <p class="form-hint">Distribuição por status e tempo desde a última reunião de cada cliente.</p>
    </div>
    <a href="<?= $appUrl ?>/clientes" class="btn btn-outline">← Voltar para clientes</a>
</div>

<div class="chart-page">
    <?php if ($total === 0): ?>
        <div class="card">
            <div class="empty-state"><p>Nenhum cliente cadastrado.</p></div>
        </div>
    <?php else: ?>
        <section class="card">
            <h2 class="chart-title">Distribuição por status</h2>

            <div class="stat-tiles">
                <div class="stat-tile">
                    <span class="stat-tile__label">Total de clientes</span>
                    <span class="stat-tile__value"><?= $total ?></span>
                </div>
                <?php foreach ($labels as $status => $label): ?>
                    <a class="stat-tile stat-tile--link" href="<?= $appUrl ?>/clientes?status=<?= $status ?>"
                        title="Ver clientes com status <?= $label ?>">
                        <span class="stat-tile__label">
                            <span class="chart-swatch chart-fill--<?= $status ?>" aria-hidden="true"></span><?= $label ?>
                        </span>
                        <span class="stat-tile__value"><?= $contagem[$status] ?></span>
                        <span class="stat-tile__sub"><?= $percentual($contagem[$status]) ?> dos clientes</span>
                    </a>
                <?php endforeach; ?>
            </div>

            <div class="segmented-bar" role="img" aria-label="Distribuição por status: <?= $resumoDistribuicao ?>">
                <?php foreach ($labels as $status => $label): ?>
                    <?php if ($contagem[$status] > 0): ?>
                        <span class="segmented-bar__part chart-fill--<?= $status ?>"
                            style="flex-grow: <?= $contagem[$status] ?>;"
                            data-chart-tip
                            data-tip-value="<?= $contagem[$status] ?> (<?= $percentual($contagem[$status]) ?>)"
                            data-tip-label="<?= $label ?>"
                            data-tip-status="<?= $status ?>"></span>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="card">
            <h2 class="chart-title">Meses desde a última reunião</h2>
            <p class="form-hint chart-subtitle">
                Um cliente por linha, do mais atrasado ao mais recente. As linhas verticais marcam os limites de status.
            </p>

            <ul class="chart-legend" aria-label="Legenda">
                <?php foreach ($labels as $status => $label): ?>
                    <li><span class="chart-swatch chart-fill--<?= $status ?>" aria-hidden="true"></span><?= $label ?></li>
                <?php endforeach; ?>
            </ul>

            <div class="bar-chart" role="group" aria-label="Meses desde a última reunião por cliente">
                <div class="bar-chart__overlay" aria-hidden="true">
                    <?php for ($tick = 0; $tick <= $escalaMax; $tick += $escalaPasso): ?>
                        <span class="bar-chart__grid" style="left: <?= $posicao($tick) ?>;"></span>
                        <span class="bar-chart__tick" style="left: <?= $posicao($tick) ?>;"><?= $tick ?></span>
                    <?php endfor; ?>
                    <span class="bar-chart__limit" style="left: <?= $posicao($limites['ok']) ?>;">
                        <span class="bar-chart__limit-label bar-chart__limit-label--before">Ok até <?= $meses($limites['ok']) ?></span>
                    </span>
                    <span class="bar-chart__limit" style="left: <?= $posicao($limites['moderado']) ?>;">
                        <span class="bar-chart__limit-label">Moderado até <?= $meses($limites['moderado']) ?></span>
                    </span>
                    <span class="bar-chart__axis-title">meses</span>
                </div>

                <?php foreach ($clientes as $cliente): ?>
                    <?php $texto = $meses($cliente['meses']); ?>
                    <div class="bar-row" tabindex="0"
                        aria-label="<?= htmlspecialchars($cliente['cliente']) ?>: <?= $texto ?>, status <?= $labels[$cliente['status']] ?>"
                        data-chart-tip
                        data-tip-value="<?= $texto ?>"
                        data-tip-label="<?= htmlspecialchars($cliente['cliente']) ?> · <?= $labels[$cliente['status']] ?>"
                        data-tip-detail="Última reunião: <?= $formatarData($cliente['ultima_reuniao']) ?>"
                        data-tip-status="<?= $cliente['status'] ?>">
                        <span class="bar-row__name" title="<?= htmlspecialchars($cliente['cliente']) ?>"><?= htmlspecialchars($cliente['cliente']) ?></span>
                        <span class="bar-row__plot" aria-hidden="true">
                            <?php if ($cliente['meses'] === null): ?>
                                <span class="bar-row__empty">Sem reunião registrada</span>
                            <?php else: ?>
                                <?php if ($cliente['meses'] > 0): ?>
                                    <span class="bar-row__bar chart-fill--<?= $cliente['status'] ?>" style="width: <?= $posicao($cliente['meses']) ?>;"></span>
                                <?php endif; ?>
                                <span class="bar-row__value" style="left: <?= $posicao($cliente['meses']) ?>;"><?= $texto ?></span>
                            <?php endif; ?>
                        </span>
                    </div>
                <?php endforeach; ?>
            </div>

            <details class="chart-table">
                <summary>Ver dados em tabela</summary>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Cliente</th>
                                <th>Última reunião</th>
                                <th>Tempo desde a reunião</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($clientes as $cliente): ?>
                                <tr>
                                    <td class="cliente-cell"><strong><?= htmlspecialchars($cliente['cliente']) ?></strong></td>
                                    <td><?= $formatarData($cliente['ultima_reuniao']) ?></td>
                                    <td><?= $meses($cliente['meses']) ?></td>
                                    <td>
                                        <span class="status-badge status-badge--<?= $cliente['status'] ?>"><?= $labels[$cliente['status']] ?></span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </details>
        </section>
    <?php endif; ?>
</div>
