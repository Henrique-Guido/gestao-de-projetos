<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($appName) ?></title>
    <link rel="icon" href="<?= $appUrl ?>/favicon.ico" sizes="any">
    <link rel="icon" type="image/png" href="<?= $appUrl ?>/assets/img/logo-publinet.png">
    <link rel="stylesheet" href="<?= $appUrl ?>/assets/css/app.css">
</head>
<body>
    <div class="auth-page">
        <div>
            <div class="auth-card">
                <?php $content(); ?>
            </div>
            <p class="auth-footer">&copy; <?= date('Y') ?> Publinet &middot; Gestão de Clientes</p>
        </div>
    </div>
</body>
</html>
