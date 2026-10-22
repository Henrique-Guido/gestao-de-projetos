<?php
use App\Core\Auth;
use App\Core\Csrf;
?>
<div class="page-header">
    <div>
        <h1>Acessos FTP</h1>
        <p class="form-hint">Gerencie os acessos de FTP dos clientes.</p>
    </div>
    <?php if (Auth::can('pode_criar_clientes')): ?>
        <a href="<?= $appUrl ?>/ftp/create" class="btn btn-primary">+ Novo acesso</a>
    <?php endif; ?>
</div>

<div class="card">
    <form method="GET" action="<?= $appUrl ?>/ftp" class="search-form" style="margin-bottom: 1.25rem;">
        <input type="text" name="q" class="form-control" placeholder="Buscar por cliente, host ou usuário..." value="<?= htmlspecialchars($search) ?>">
        <button type="submit" class="btn btn-outline">Buscar</button>
    </form>

    <?php if (empty($accounts)): ?>
        <div class="empty-state">
            <p>Nenhum acesso FTP cadastrado<?= $search ? ' para "' . htmlspecialchars($search) . '"' : '' ?>.</p>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Cliente</th>
                        <th>Host</th>
                        <th>Usuário</th>
                        <th>Senha</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($accounts as $account): ?>
                        <tr>
                            <td class="cliente-cell"><strong><?= htmlspecialchars($account['cliente']) ?></strong></td>
                            <td><?= $account['host'] ? '<span class="badge">' . htmlspecialchars($account['host']) . '</span>' : '—' ?></td>
                            <td><?= htmlspecialchars($account['usuario'] ?? '') ?: '—' ?></td>
                            <td>
                                <?php if ($account['senha']): ?>
                                    <div class="password-cell">
                                        <span class="password-mask">••••••••</span>
                                        <button type="button" class="btn btn-sm btn-outline btn-icon" data-revealed="false"
                                            data-reveal-url="<?= $appUrl ?>/ftp/<?= $account['id'] ?>/reveal"
                                            aria-label="Mostrar senha" title="Mostrar senha">
                                            <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8Z"/><circle cx="12" cy="12" r="3"/></svg>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline"
                                            data-copy-url="<?= $appUrl ?>/ftp/<?= $account['id'] ?>/reveal">Copiar</button>
                                    </div>
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </td>
                            <td class="actions-cell">
                                <button type="button" class="btn btn-sm btn-outline" data-test-url="<?= $appUrl ?>/ftp/<?= $account['id'] ?>/test">Testar conexão</button>
                                <?php if (Auth::can('pode_editar_clientes')): ?>
                                    <a class="btn btn-sm btn-outline btn-icon" href="<?= $appUrl ?>/ftp/<?= $account['id'] ?>/edit"
                                        aria-label="Editar" title="Editar">
                                        <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>
                                    </a>
                                <?php endif; ?>
                                <?php if (Auth::isAdmin()): ?>
                                    <form method="POST" action="<?= $appUrl ?>/ftp/<?= $account['id'] ?>/delete"
                                        data-confirm="Remover o acesso FTP de <?= htmlspecialchars($account['cliente']) ?>?">
                                        <?= Csrf::field() ?>
                                        <button type="submit" class="btn btn-sm btn-danger btn-icon" aria-label="Excluir" title="Excluir">
                                            <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg>
                                        </button>
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
