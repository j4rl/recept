<?php
declare(strict_types=1);

function db(): mysqli
{
    static $connection = null;

    if ($connection instanceof mysqli) {
        return $connection;
    }

    $connection = new mysqli(
        app_config('db_host'),
        app_config('db_user'),
        app_config('db_pass'),
        app_config('db_name'),
        app_config('db_port')
    );

    if ($connection->connect_errno !== 0) {
        throw new RuntimeException('Databasanslutning misslyckades: ' . $connection->connect_error);
    }

    if (!$connection->set_charset('utf8mb4')) {
        throw new RuntimeException('Kunde inte satt teckenkodning: ' . $connection->error);
    }

    return $connection;
}

function db_query(string $sql, string $types = '', array $params = []): mysqli_stmt
{
    $statement = db()->prepare($sql);

    if (!$statement) {
        throw new RuntimeException('Kunde inte forbereda SQL: ' . db()->error);
    }

    if ($types !== '') {
        db_bind($statement, $types, $params);
    }

    if (!$statement->execute()) {
        $error = $statement->error;
        $statement->close();
        throw new RuntimeException('SQL-fel: ' . $error);
    }

    return $statement;
}

function db_all(string $sql, string $types = '', array $params = []): array
{
    $statement = db_query($sql, $types, $params);
    $result = $statement->get_result();
    $rows = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    $statement->close();

    return $rows;
}

function db_one(string $sql, string $types = '', array $params = []): ?array
{
    $rows = db_all($sql, $types, $params);

    return $rows[0] ?? null;
}

function db_execute(string $sql, string $types = '', array $params = []): int
{
    $statement = db_query($sql, $types, $params);
    $affected = $statement->affected_rows;
    $statement->close();

    return $affected;
}

function db_bind(mysqli_stmt $statement, string $types, array $params): void
{
    if ($types === '') {
        return;
    }

    if (strlen($types) !== count($params)) {
        throw new InvalidArgumentException('Antal bind-typer matchar inte antal parametrar.');
    }

    $bindValues = [];
    $bindValues[] = &$types;

    foreach ($params as $index => $value) {
        $bindValues[] = &$params[$index];
    }

    if (!call_user_func_array([$statement, 'bind_param'], $bindValues)) {
        throw new RuntimeException('Kunde inte binda parametrar: ' . $statement->error);
    }
}

