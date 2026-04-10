<?php
declare(strict_types=1);

require_login();

if (!google_keep_is_configured()) {
    flash('error', 'Google Keep är inte konfigurerat ännu.');
    redirect('index.php?page=shopping_list');
}

$returnTo = safe_redirect((string) ($_GET['return_to'] ?? 'index.php?page=shopping_list'));
redirect(google_keep_auth_url($returnTo));
