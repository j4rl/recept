<?php
declare(strict_types=1);

return [
    'db_host' => getenv('RECEPT_DB_HOST') ?: '127.0.0.1',
    'db_user' => getenv('RECEPT_DB_USER') ?: 'root',
    'db_pass' => getenv('RECEPT_DB_PASS') ?: '',
    'db_name' => getenv('RECEPT_DB_NAME') ?: 'receptdb',
    'db_port' => (int) (getenv('RECEPT_DB_PORT') ?: 3306),
    'upload_base_dir' => getenv('RECEPT_UPLOAD_BASE_DIR') ?: dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads',
    'upload_base_url' => getenv('RECEPT_UPLOAD_BASE_URL') ?: 'uploads',
    'app_name' => 'Matarkiv',
];
