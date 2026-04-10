<?php
declare(strict_types=1);

require_login();

$viewer = current_user();
if (!$viewer) {
    redirect('index.php?page=login');
}

$returnTo = safe_redirect((string) ($_SESSION['google_keep_return_to'] ?? 'index.php?page=shopping_list'));
$expectedState = (string) ($_SESSION['google_keep_oauth_state'] ?? '');
unset($_SESSION['google_keep_return_to'], $_SESSION['google_keep_oauth_state']);

if (!google_keep_is_configured()) {
    flash('error', 'Google Keep är inte konfigurerat ännu.');
    redirect($returnTo);
}

if (isset($_GET['error'])) {
    flash('error', 'Google Keep-inloggningen avbröts: ' . (string) $_GET['error']);
    redirect($returnTo);
}

$state = (string) ($_GET['state'] ?? '');
$code = trim((string) ($_GET['code'] ?? ''));

if ($expectedState === '' || !hash_equals($expectedState, $state) || $code === '') {
    flash('error', 'Google Keep-svaret kunde inte verifieras.');
    redirect($returnTo);
}

try {
    $tokenData = google_keep_exchange_code($code);
    google_keep_store_tokens((int) $viewer['id'], $tokenData);
    flash('success', 'Google Keep är nu anslutet.');
} catch (Throwable $exception) {
    flash('error', 'Google Keep-kopplingen misslyckades: ' . $exception->getMessage());
}

redirect($returnTo);
