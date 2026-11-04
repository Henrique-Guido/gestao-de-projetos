<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($appName) ?></title>
    <?php // Prévia ao compartilhar o link (WhatsApp, Slack, LinkedIn...). Quem gera a prévia
          // não está logado, então qualquer link do sistema cai nesta tela. ?>
    <meta property="og:type" content="website">
    <meta property="og:locale" content="pt_BR">
    <meta property="og:site_name" content="<?= htmlspecialchars($appName) ?>">
    <meta property="og:title" content="<?= htmlspecialchars($appName) ?>">
    <meta property="og:description" content="Sistema interno de gestão de clientes e projetos. Acesso restrito à equipe.">
    <meta property="og:url" content="<?= $appUrl ?>/">
    <meta property="og:image" content="<?= $appUrl ?>/assets/img/og-image.jpg">
    <meta property="og:image:type" content="image/jpeg">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:image:alt" content="<?= htmlspecialchars($appName) ?>">
    <meta name="twitter:card" content="summary_large_image">
    <link rel="icon" href="<?= $appUrl ?>/favicon.ico" sizes="any">
    <link rel="icon" type="image/png" href="<?= $appUrl ?>/assets/img/logo.png">
    <link rel="stylesheet" href="<?= $appUrl ?>/assets/css/app.css">
</head>
<body>
    <div class="auth-page">
        <div>
            <div class="auth-card">
                <?php $content(); ?>
            </div>
            <p class="auth-footer">&copy; <?= date('Y') ?> <?= htmlspecialchars($appName) ?></p>
        </div>
    </div>
</body>
</html>
