<?php
declare(strict_types=1);

if (is_logged_in()) {
    redirect('index.php');
}

$pageTitle = app_config('app_name') . ' - Skapa konto';
?>

<section class="form-shell narrow">
    <h1>Skapa konto</h1>
    <p>Med konto kan du publicera recept och kryssa i vad du har hemma.</p>

    <form method="post" action="index.php" class="stack-form">
        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="register">
        <input type="hidden" name="redirect_to" value="index.php">

        <label for="name">Namn</label>
        <input id="name" name="name" type="text" required minlength="2" maxlength="80" autocomplete="name">

        <label for="email">E-post</label>
        <input id="email" name="email" type="email" required autocomplete="email">

        <label for="password">Lösenord</label>
        <input id="password" name="password" type="password" required minlength="8" autocomplete="new-password">

        <label for="password_confirm">Bekräfta lösenord</label>
        <input id="password_confirm" name="password_confirm" type="password" required minlength="8" autocomplete="new-password">

        <button type="submit">Skapa konto</button>
    </form>

    <p class="form-alt">Har du redan konto? <a href="index.php?page=login">Logga in</a></p>
</section>
