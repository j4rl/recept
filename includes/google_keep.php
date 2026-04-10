<?php
declare(strict_types=1);

function google_keep_scope(): string
{
    return 'https://www.googleapis.com/auth/keep';
}

function google_keep_is_configured(): bool
{
    return trim((string) app_config('google_keep_client_id')) !== ''
        && trim((string) app_config('google_keep_client_secret')) !== '';
}

function google_keep_redirect_uri(): string
{
    $configured = trim((string) app_config('google_keep_redirect_uri'));
    if ($configured !== '') {
        return $configured;
    }

    $isHttps = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    $scheme = $isHttps ? 'https' : 'http';
    $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
    $script = (string) ($_SERVER['SCRIPT_NAME'] ?? '/index.php');
    $basePath = rtrim(str_replace('\\', '/', dirname($script)), '/.');

    return $scheme . '://' . $host . ($basePath !== '' ? $basePath : '') . '/index.php?page=keep_callback';
}

function google_keep_connection(int $userId): ?array
{
    return db_one(
        'SELECT user_id, access_token, refresh_token, scope, token_type, expires_at, created_at, updated_at
         FROM google_keep_tokens
         WHERE user_id = ?
         LIMIT 1',
        'i',
        [$userId]
    );
}

function google_keep_is_connected(int $userId): bool
{
    return google_keep_connection($userId) !== null;
}

function google_keep_store_tokens(int $userId, array $tokenData): void
{
    $expiresAt = null;
    if (isset($tokenData['expires_in']) && is_numeric($tokenData['expires_in'])) {
        $expiresAt = (new DateTimeImmutable('now'))
            ->modify('+' . max(0, (int) $tokenData['expires_in']) . ' seconds')
            ->format('Y-m-d H:i:s');
    }

    $existing = google_keep_connection($userId);
    $refreshToken = trim((string) ($tokenData['refresh_token'] ?? ($existing['refresh_token'] ?? '')));

    db_execute(
        'INSERT INTO google_keep_tokens (user_id, access_token, refresh_token, scope, token_type, expires_at)
         VALUES (?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
            access_token = VALUES(access_token),
            refresh_token = VALUES(refresh_token),
            scope = VALUES(scope),
            token_type = VALUES(token_type),
            expires_at = VALUES(expires_at),
            updated_at = CURRENT_TIMESTAMP',
        'isssss',
        [
            $userId,
            (string) ($tokenData['access_token'] ?? ''),
            $refreshToken,
            (string) ($tokenData['scope'] ?? google_keep_scope()),
            (string) ($tokenData['token_type'] ?? 'Bearer'),
            $expiresAt,
        ]
    );
}

function google_keep_disconnect(int $userId): void
{
    db_execute('DELETE FROM google_keep_tokens WHERE user_id = ?', 'i', [$userId]);
}

function google_keep_auth_url(string $returnTo = 'index.php?page=shopping_list'): string
{
    $state = bin2hex(random_bytes(16));
    $_SESSION['google_keep_oauth_state'] = $state;
    $_SESSION['google_keep_return_to'] = safe_redirect($returnTo);

    return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
        'client_id' => (string) app_config('google_keep_client_id'),
        'redirect_uri' => google_keep_redirect_uri(),
        'response_type' => 'code',
        'scope' => google_keep_scope(),
        'access_type' => 'offline',
        'include_granted_scopes' => 'true',
        'prompt' => 'consent',
        'state' => $state,
    ]);
}

function google_keep_exchange_code(string $code): array
{
    return google_keep_http_form_request(
        'https://oauth2.googleapis.com/token',
        [
            'code' => $code,
            'client_id' => (string) app_config('google_keep_client_id'),
            'client_secret' => (string) app_config('google_keep_client_secret'),
            'redirect_uri' => google_keep_redirect_uri(),
            'grant_type' => 'authorization_code',
        ]
    );
}

function google_keep_refresh_token(int $userId): ?array
{
    $connection = google_keep_connection($userId);
    if (!$connection || trim((string) ($connection['refresh_token'] ?? '')) === '') {
        return null;
    }

    $tokenData = google_keep_http_form_request(
        'https://oauth2.googleapis.com/token',
        [
            'client_id' => (string) app_config('google_keep_client_id'),
            'client_secret' => (string) app_config('google_keep_client_secret'),
            'refresh_token' => (string) $connection['refresh_token'],
            'grant_type' => 'refresh_token',
        ]
    );

    google_keep_store_tokens($userId, $tokenData);

    return google_keep_connection($userId);
}

function google_keep_access_token(int $userId): ?string
{
    $connection = google_keep_connection($userId);
    if (!$connection) {
        return null;
    }

    $expiresAt = trim((string) ($connection['expires_at'] ?? ''));
    $needsRefresh = $expiresAt !== '' && strtotime($expiresAt) <= (time() + 60);

    if ($needsRefresh) {
        $connection = google_keep_refresh_token($userId);
        if (!$connection) {
            return null;
        }
    }

    return trim((string) ($connection['access_token'] ?? '')) ?: null;
}

function google_keep_create_list_note(int $userId, string $title, array $items): array
{
    $accessToken = google_keep_access_token($userId);
    if ($accessToken === null) {
        throw new RuntimeException('Google Keep är inte anslutet.');
    }

    $listItems = [];
    foreach ($items as $item) {
        $text = trim((string) $item);
        if ($text === '') {
            continue;
        }

        $listItems[] = [
            'text' => ['text' => $text],
            'checked' => false,
        ];
    }

    if (count($listItems) === 0) {
        throw new RuntimeException('Det finns inga ingredienser att skicka till Google Keep.');
    }

    $payload = [
        'title' => $title,
        'body' => [
            'list' => [
                'listItems' => $listItems,
            ],
        ],
    ];

    try {
        return google_keep_http_json_request(
            'https://keep.googleapis.com/v1/notes',
            $payload,
            $accessToken
        );
    } catch (RuntimeException $exception) {
        if (str_contains($exception->getMessage(), '401')) {
            $refreshed = google_keep_refresh_token($userId);
            if ($refreshed && !empty($refreshed['access_token'])) {
                return google_keep_http_json_request(
                    'https://keep.googleapis.com/v1/notes',
                    $payload,
                    (string) $refreshed['access_token']
                );
            }
        }

        throw $exception;
    }
}

function google_keep_http_form_request(string $url, array $data): array
{
    return google_keep_http_request(
        $url,
        [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($data),
            CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
        ]
    );
}

function google_keep_http_json_request(string $url, array $data, string $accessToken): array
{
    return google_keep_http_request(
        $url,
        [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $accessToken,
            ],
        ]
    );
}

function google_keep_http_request(string $url, array $options): array
{
    if (!function_exists('curl_init')) {
        throw new RuntimeException('PHP cURL saknas och krävs för Google Keep-integrationen.');
    }

    $ch = curl_init($url);
    if ($ch === false) {
        throw new RuntimeException('Kunde inte starta HTTP-klienten för Google Keep.');
    }

    curl_setopt_array($ch, $options + [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20,
    ]);

    $response = curl_exec($ch);
    $statusCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        throw new RuntimeException('Google Keep-anropet misslyckades: ' . $curlError);
    }

    $decoded = json_decode($response, true);
    if (!is_array($decoded)) {
        $decoded = [];
    }

    if ($statusCode < 200 || $statusCode >= 300) {
        $message = (string) ($decoded['error_description'] ?? $decoded['error']['message'] ?? ('HTTP ' . $statusCode));
        throw new RuntimeException('Google Keep API-fel (' . $statusCode . '): ' . $message);
    }

    return $decoded;
}
