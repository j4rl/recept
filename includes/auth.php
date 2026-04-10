<?php
declare(strict_types=1);

function current_user(): ?array
{
    $userId = $_SESSION['user_id'] ?? null;

    if (!$userId) {
        return null;
    }

    $roleSelect = db_column_exists('users', 'role') ? 'role' : "'user' AS role";

    return db_one(
        "SELECT id, name, email, inventory_enabled, {$roleSelect}, created_at FROM users WHERE id = ? LIMIT 1",
        'i',
        [(int) $userId]
    );
}

function is_logged_in(): bool
{
    return current_user() !== null;
}

function require_login(): void
{
    if (!is_logged_in()) {
        flash('error', 'Du måste logga in för att använda den funktionen.');
        redirect('index.php?page=login');
    }
}

function login_user(int $userId): void
{
    session_regenerate_id(true);
    $_SESSION['user_id'] = $userId;
}

function logout_user(): void
{
    unset($_SESSION['user_id']);
    session_regenerate_id(true);
}

function register_user(string $name, string $email, string $password): bool
{
    if (mb_strlen($name) < 2) {
        flash('error', 'Namn måste vara minst 2 tecken.');
        return false;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        flash('error', 'Ogiltig e-postadress.');
        return false;
    }

    if (strlen($password) < 8) {
        flash('error', 'Lösenord måste vara minst 8 tecken.');
        return false;
    }

    $existing = db_one('SELECT id FROM users WHERE email = ? LIMIT 1', 's', [$email]);
    if ($existing) {
        flash('error', 'E-postadressen är redan registrerad.');
        return false;
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);

    db_execute(
        'INSERT INTO users (name, email, password_hash, inventory_enabled) VALUES (?, ?, ?, 0)',
        'sss',
        [$name, strtolower($email), $hash]
    );

    return true;
}

function attempt_login(string $email, string $password): bool
{
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        flash('error', 'Ogiltig e-postadress.');
        return false;
    }

    $user = db_one('SELECT id, password_hash FROM users WHERE email = ? LIMIT 1', 's', [strtolower($email)]);
    if (!$user || !password_verify($password, $user['password_hash'])) {
        flash('error', 'Fel e-post eller lösenord.');
        return false;
    }

    login_user((int) $user['id']);
    return true;
}

function is_admin(?array $user = null): bool
{
    $candidate = $user ?? current_user();

    return $candidate !== null && (($candidate['role'] ?? 'user') === 'admin');
}

function can_manage_recipe(?array $user, array $recipe): bool
{
    if ($user === null) {
        return false;
    }

    return is_admin($user) || (int) ($recipe['user_id'] ?? 0) === (int) $user['id'];
}
