<?php
declare(strict_types=1);

$viewer = current_user();
$flashMessages = consume_flash();
?>
<!doctype html>
<html lang="sv">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?></title>
    <link rel="stylesheet" href="assets/css/styles.css">
</head>
<body>
    <header class="site-header">
        <div class="container nav-shell">
            <a class="brand" href="index.php">
                <span class="brand-dot"></span>
                <span>Matarkiv</span>
            </a>

            <button class="mobile-nav-toggle" type="button" data-mobile-nav-toggle aria-label="Meny">Meny</button>

            <nav class="site-nav" data-mobile-nav>
                <a href="index.php" class="<?= $page === 'home' ? 'is-active' : '' ?>">Recept</a>
                <?php if ($viewer): ?>
                    <a href="index.php?page=create" class="<?= $page === 'create' ? 'is-active' : '' ?>">Publicera</a>
                    <a href="index.php?page=inventory" class="<?= $page === 'inventory' ? 'is-active' : '' ?>">Skafferi/Kyl/Frys</a>
                    <form action="index.php" method="post" class="inline-form">
                        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="action" value="logout">
                        <input type="hidden" name="redirect_to" value="index.php">
                        <button type="submit" class="link-button">Logga ut</button>
                    </form>
                <?php else: ?>
                    <a href="index.php?page=login" class="<?= $page === 'login' ? 'is-active' : '' ?>">Logga in</a>
                    <a href="index.php?page=register" class="<?= $page === 'register' ? 'is-active' : '' ?>">Skapa konto</a>
                <?php endif; ?>
            </nav>
        </div>
    </header>

    <main class="main-content">
        <div class="container">
            <?php foreach ($flashMessages as $message): ?>
                <div class="flash flash-<?= e($message['type']) ?>"><?= e($message['message']) ?></div>
            <?php endforeach; ?>
        </div>

