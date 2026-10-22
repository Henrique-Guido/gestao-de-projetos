<?php
use App\Core\Auth;
use App\Core\Csrf;

$currentUser = Auth::user();
$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '';
$isActive = static fn (string $path): string => str_starts_with($currentPath, $path) ? ' is-active' : '';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= htmlspecialchars(Csrf::token()) ?>">
    <title><?= htmlspecialchars($appName) ?></title>
    <link rel="icon" href="<?= $appUrl ?>/favicon.ico" sizes="any">
    <link rel="icon" type="image/png" href="<?= $appUrl ?>/assets/img/logo-publinet.png">
    <link rel="stylesheet" href="<?= $appUrl ?>/assets/css/app.css?v=<?= filemtime(dirname(__DIR__, 3) . '/public/assets/css/app.css') ?>">
</head>
<body>
    <div class="app-shell">
        <aside class="sidebar">
            <div class="sidebar__logo">
                <img src="<?= $appUrl ?>/assets/img/logo-publinet.png" alt="Publinet">
            </div>
            <nav class="sidebar__nav">
                <a class="sidebar__link<?= $isActive('/clientes') ?>" href="<?= $appUrl ?>/clientes">Clientes</a>
                <a class="sidebar__link<?= $isActive('/ftp') ?>" href="<?= $appUrl ?>/ftp">Acessos FTP</a>
                <a class="sidebar__link<?= $isActive('/blogs') ?>" href="<?= $appUrl ?>/blogs">Blogs</a>
                <a class="sidebar__link<?= $isActive('/wordpress') ?>" href="<?= $appUrl ?>/wordpress">WordPress</a>
                <a class="sidebar__link<?= $isActive('/ecommerce') ?>" href="<?= $appUrl ?>/ecommerce">E-commerce</a>
            </nav>
            <?php if (Auth::isAdmin()): ?>
                <div class="sidebar__divider"></div>
                <nav class="sidebar__nav sidebar__nav--admin">
                    <a class="sidebar__link<?= $isActive('/usuarios') ?>" href="<?= $appUrl ?>/usuarios">Usuários</a>
                    <a class="sidebar__link sidebar__link--export" href="<?= $appUrl ?>/exportar/excel"
                        title="Baixa uma planilha com todos os dados, uma aba por tela (inclui senhas)">
                        <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                        Exportar Excel
                    </a>
                </nav>
            <?php endif; ?>
            <?php if ($currentUser): ?>
            <div class="sidebar__user">
                <strong><?= htmlspecialchars($currentUser['name']) ?></strong>
                <span><?= htmlspecialchars($currentUser['email']) ?></span>
                <?php if ($currentUser['role'] === 'admin'): ?>
                    <span class="badge" style="margin-top:.35rem;">Administrador</span>
                <?php endif; ?>
                <form method="POST" action="<?= $appUrl ?>/logout">
                    <?= Csrf::field() ?>
                    <button type="submit" class="logout-btn">Sair</button>
                </form>
            </div>
            <?php endif; ?>
        </aside>
        <main class="main">
            <?php $content(); ?>
        </main>
    </div>
    <?php
    $flashSuccess = \App\Core\Flash::get('success');
    $flashError = \App\Core\Flash::get('error');
    ?>
    <div class="toast-container">
        <?php if ($flashSuccess): ?>
            <div class="toast toast--success" role="status">
                <span class="toast__message"><?= htmlspecialchars($flashSuccess) ?></span>
                <button type="button" class="toast__close" aria-label="Fechar notificação">&times;</button>
            </div>
        <?php endif; ?>
        <?php if ($flashError): ?>
            <div class="toast toast--error" role="status">
                <span class="toast__message"><?= htmlspecialchars($flashError) ?></span>
                <button type="button" class="toast__close" aria-label="Fechar notificação">&times;</button>
            </div>
        <?php endif; ?>
    </div>
    <dialog id="confirm-dialog" class="confirm-dialog">
        <p class="confirm-dialog__message"></p>
        <div class="confirm-dialog__actions">
            <button type="button" class="btn btn-outline" data-confirm-cancel>Cancelar</button>
            <button type="button" class="btn btn-danger" data-confirm-ok>Confirmar</button>
        </div>
    </dialog>
    <dialog id="info-dialog" class="confirm-dialog">
        <div class="confirm-dialog__icon" data-info-icon></div>
        <p class="confirm-dialog__message" data-info-message></p>
        <div class="confirm-dialog__actions confirm-dialog__actions--center">
            <button type="button" class="btn btn-primary" data-info-ok>Fechar</button>
        </div>
    </dialog>
    <script src="<?= $appUrl ?>/assets/js/app.js?v=<?= filemtime(dirname(__DIR__, 3) . '/public/assets/js/app.js') ?>"></script>
</body>
</html>
