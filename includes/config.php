<?php
declare(strict_types=1);

return [
    'db_host' => getenv('RECEPT_DB_HOST') ?: '127.0.0.1',
    'db_user' => getenv('RECEPT_DB_USER') ?: 'root',
    'db_pass' => getenv('RECEPT_DB_PASS') ?: '',
    'db_name' => getenv('RECEPT_DB_NAME') ?: 'receptdb',
    'db_port' => (int) (getenv('RECEPT_DB_PORT') ?: 3306),
    'app_name' => 'Matarkiv',
];

