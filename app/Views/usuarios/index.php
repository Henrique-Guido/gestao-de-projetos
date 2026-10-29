<?php
use App\Core\Auth;
use App\Core\Csrf;
?>
<div class="page-header">
    <div>
        <h1>Usuários</h1>
        <p class="form-hint">Gerencie o acesso e as permissões de cada usuário.</p>
    </div>
    <a href="<?= $appUrl ?>/usuarios/create" class="btn btn-primary">+ Novo usuário</a>
</div>

<div class="card">
    <?php if (empty($usuarios)): ?>
        <div class="empty-state"><p>Nenhum usuário cadastrado.</p></div>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Nome</th>
                        <th>E-mail</th>
                        <th>Papel</th>
                        <th>Criar clientes</th>
                        <th>Editar clientes</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($usuarios as $usuario): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($usuario['name']) ?></strong></td>
                            <td><?= htmlspecialchars($usuario['email']) ?></td>
                            <td>
                                <?php if ($usuario['role'] === 'admin'): ?>
                                    <span class="badge">Administrador</span>
                                <?php else: ?>
                                    <span class="badge badge--neutral">Usuário</span>
                                <?php endif; ?>
                            </td>
                            <td><?= $usuario['role'] === 'admin' || $usuario['pode_criar_clientes'] ? 'Sim' : 'Não' ?></td>
                            <td><?= $usuario['role'] === 'admin' || $usuario['pode_editar_clientes'] ? 'Sim' : 'Não' ?></td>
                            <td>
                                <div class="actions-cell">
                                    <a class="btn btn-sm btn-outline" href="<?= $appUrl ?>/usuarios/<?= $usuario['id'] ?>/edit">Editar</a>
                                    <?php if ((int) $usuario['id'] !== Auth::id()): ?>
                                        <form method="POST" action="<?= $appUrl ?>/usuarios/<?= $usuario['id'] ?>/delete"
                                            data-confirm="Remover o usuário <?= htmlspecialchars($usuario['name']) ?>?">
                                            <?= Csrf::field() ?>
                                            <button type="submit" class="btn btn-sm btn-danger">Excluir</button>
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
