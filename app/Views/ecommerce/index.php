<?php
use App\Core\Auth;
use App\Core\Csrf;

// Valor em uma linha só, cortado com reticências pelo CSS; o texto completo fica no title.
$truncado = static function (?string $valor): string {
    if ($valor === null || $valor === '') {
        return '—';
    }
    $valor = htmlspecialchars($valor);
    return '<span title="' . $valor . '">' . $valor . '</span>';
};
?>
<div class="page-header">
    <div>
        <h1>E-commerce</h1>
        <p class="form-hint">Gerencie os acessos às lojas virtuais dos clientes.</p>
    </div>
    <?php if (Auth::can('pode_criar_clientes')): ?>
        <a href="<?= $appUrl ?>/ecommerce/create" class="btn btn-primary">+ Novo acesso</a>
    <?php endif; ?>
</div>

<div class="card">
    <form method="GET" action="<?= $appUrl ?>/ecommerce" class="search-form" style="margin-bottom: 1.25rem;">
        <input type="text" name="q" class="form-control" placeholder="Buscar por cliente, link, e-mail ou usuário..." value="<?= htmlspecialchars($search) ?>">
        <button type="submit" class="btn btn-outline">Buscar</button>
    </form>

    <?php if (empty($lojas)): ?>
        <div class="empty-state">
            <p>Nenhum acesso de e-commerce cadastrado<?= $search ? ' para "' . htmlspecialchars($search) . '"' : '' ?>.</p>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Cliente</th>
                        <th>Loja</th>
                        <th>E-mail</th>
                        <th>Usuário</th>
                        <th>Código</th>
                        <th>Senha</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($lojas as $loja): ?>
                        <tr>
                            <td class="cliente-cell"><strong><?= htmlspecialchars($loja['cliente']) ?></strong></td>
                            <td class="truncate-cell">
                                <a href="<?= htmlspecialchars($loja['link']) ?>" target="_blank" rel="noopener noreferrer"
                                    title="<?= htmlspecialchars($loja['link']) ?>">
                                    <?= htmlspecialchars(preg_replace('#^https?://#i', '', rtrim($loja['link'], '/'))) ?>
                                </a>
                            </td>
                            <td class="truncate-cell"><?= $truncado($loja['email']) ?></td>
                            <td class="truncate-cell"><?= $truncado($loja['usuario']) ?></td>
                            <td class="truncate-cell"><?= $truncado($loja['codigo']) ?></td>
                            <td>
                                <div class="password-cell">
                                    <span class="password-mask">••••••••</span>
                                    <button type="button" class="btn btn-sm btn-outline btn-icon" data-revealed="false"
                                        data-reveal-url="<?= $appUrl ?>/ecommerce/<?= $loja['id'] ?>/reveal"
                                        aria-label="Mostrar senha" title="Mostrar senha">
                                        <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8Z"/><circle cx="12" cy="12" r="3"/></svg>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline"
                                        data-copy-url="<?= $appUrl ?>/ecommerce/<?= $loja['id'] ?>/reveal">Copiar</button>
                                </div>
                            </td>
                            <td>
                                <div class="actions-cell">
                                    <?php if (Auth::can('pode_editar_clientes')): ?>
                                        <a class="btn btn-sm btn-outline btn-icon" href="<?= $appUrl ?>/ecommerce/<?= $loja['id'] ?>/edit"
                                            aria-label="Editar" title="Editar">
                                            <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>
                                        </a>
                                    <?php endif; ?>
                                    <?php if (Auth::isAdmin()): ?>
                                        <form method="POST" action="<?= $appUrl ?>/ecommerce/<?= $loja['id'] ?>/delete"
                                            data-confirm="Remover o acesso ao e-commerce de <?= htmlspecialchars($loja['cliente']) ?>?">
                                            <?= Csrf::field() ?>
                                            <button type="submit" class="btn btn-sm btn-danger btn-icon" aria-label="Excluir" title="Excluir">
                                                <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
