<?php
declare(strict_types=1);

if (is_logged_in()) {
    redirect('index.php');
}

$pageTitle = app_config('app_name') . ' - Logga in';
?>

<section class="form-shell narrow">
    <h1>Logga in</h1>
    <p>Publicera egna recept och hantera ditt Skafferi/Kyl/Frys.</p>

    <form method="post" action="index.php" class="stack-form">
        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="login">
        <input type="hidden" name="redirect_to" value="index.php">

        <label for="email">E-post</label>
        <input id="email" name="email" type="email" required autocomplete="email">

        <label for="password">Lösenord</label>
        <input id="password" name="password" type="password" required autocomplete="current-password">

        <button type="submit">Logga in</button>
    </form>

    <p class="form-alt">Har du inget konto? <a href="index.php?page=register">Skapa konto</a></p>
</section>
