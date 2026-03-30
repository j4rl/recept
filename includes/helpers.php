<?php
declare(strict_types=1);

function app_config(string $key, mixed $default = null): mixed
{
    return $GLOBALS['app_config'][$key] ?? $default;
}

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function redirect(string $path): never
{
    header('Location: ' . $path);
    exit;
}

function flash(string $type, string $message): void
{
    if (!isset($_SESSION['flash']) || !is_array($_SESSION['flash'])) {
        $_SESSION['flash'] = [];
    }

    $_SESSION['flash'][] = [
        'type' => $type,
        'message' => $message,
    ];
}

function consume_flash(): array
{
    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);

    return is_array($messages) ? $messages : [];
}

function csrf_token(): string
{
    if (!isset($_SESSION['_csrf']) || !is_string($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['_csrf'];
}

function verify_csrf(string $token): bool
{
    if (!isset($_SESSION['_csrf']) || !is_string($_SESSION['_csrf'])) {
        return false;
    }

    return hash_equals($_SESSION['_csrf'], $token);
}

function slugify(string $value): string
{
    $value = trim($value);
    $value = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) ?: $value;
    $value = strtolower($value);
    $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?: '';
    $value = trim($value, '-');

    return $value !== '' ? $value : 'recept';
}

function unique_recipe_slug(string $title): string
{
    $base = slugify($title);
    $slug = $base;
    $count = 1;

    while (db_one('SELECT id FROM recipes WHERE slug = ? LIMIT 1', 's', [$slug])) {
        $count++;
        $slug = $base . '-' . $count;
    }

    return $slug;
}

function normalize_ingredient_name(string $name): string
{
    $trimmed = preg_replace('/\s+/', ' ', trim($name)) ?: '';

    if ($trimmed === '') {
        return '';
    }

    return mb_convert_case($trimmed, MB_CASE_TITLE, 'UTF-8');
}

function bool_from_post(string $key): bool
{
    return isset($_POST[$key]) && $_POST[$key] === '1';
}

function minutes_total(?int $prep, ?int $cook): int
{
    return max(0, (int) $prep) + max(0, (int) $cook);
}

