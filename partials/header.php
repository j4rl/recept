<?php
declare(strict_types=1);

$viewer = current_user();
$flashMessages = consume_flash();
$isMinePage = $page === 'home' && (($_GET['mine'] ?? '') === '1');
?>
<!doctype html>
<html lang="sv">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?></title>
    <script>
        (() => {
            const media = window.matchMedia('(prefers-color-scheme: dark)');
            let preference = 'auto';

            try {
                const stored = localStorage.getItem('theme_preference');
                if (stored === 'light' || stored === 'dark' || stored === 'auto') {
                    preference = stored;
                }
            } catch (error) {
                preference = 'auto';
            }

            const theme = preference === 'auto'
                ? (media.matches ? 'dark' : 'light')
                : preference;

            document.documentElement.setAttribute('data-theme-preference', preference);
            document.documentElement.setAttribute('data-theme', theme);
        })();
    </script>
    <link rel="stylesheet" href="assets/css/styles.css">
</head>
<body>
    <header class="site-header">
        <div class="container nav-shell">
            <a class="brand" href="index.php">
                <span>ReceptDatabas</span>
            </a>

            <button class="mobile-nav-toggle" type="button" data-mobile-nav-toggle aria-label="Meny">Meny</button>

            <nav class="site-nav" data-mobile-nav>
                <a href="index.php?page=home" class="<?= $page === 'home' && !$isMinePage ? 'is-active' : '' ?>">Hem</a>
                <?php if ($viewer): ?>
                    <a href="index.php?page=home&mine=1" class="<?= $isMinePage ? 'is-active' : '' ?>">Mina recept</a>
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
                <div class="theme-switch" role="group" aria-label="Välj tema" data-theme-switch>
                    <button type="button" class="theme-option" data-theme-option="light" aria-label="Ljust tema" title="Ljust tema" aria-pressed="false">
                        <span class="theme-option-icon" aria-hidden="true">☀</span>
                    </button>
                    <button type="button" class="theme-option" data-theme-option="auto" aria-label="Automatiskt tema" title="Automatiskt tema" aria-pressed="true">
                        <span class="theme-option-icon" aria-hidden="true">⚙</span>
                    </button>
                    <button type="button" class="theme-option" data-theme-option="dark" aria-label="Mörkt tema" title="Mörkt tema" aria-pressed="false">
                        <span class="theme-option-icon" aria-hidden="true">🌙</span>
                    </button>
                </div>
            </nav>
        </div>
    </header>

    <main class="main-content">
        <div class="container">
            <?php foreach ($flashMessages as $message): ?>
                <div class="flash flash-<?= e($message['type']) ?>"><?= e($message['message']) ?></div>
            <?php endforeach; ?>
        </div>
