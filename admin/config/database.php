<?php

return [
    'host'     => getenv('DB_HOST') !== false && getenv('DB_HOST') !== '' ? getenv('DB_HOST') : '127.0.0.1',
    'port'     => (int) (getenv('DB_PORT') !== false && getenv('DB_PORT') !== '' ? getenv('DB_PORT') : 3306),
    'database' => getenv('DB_DATABASE') !== false && getenv('DB_DATABASE') !== '' ? getenv('DB_DATABASE') : 'notary_management',
    'username' => getenv('DB_USERNAME') !== false && getenv('DB_USERNAME') !== '' ? getenv('DB_USERNAME') : 'root',
    'password' => getenv('DB_PASSWORD') !== false ? getenv('DB_PASSWORD') : '',
    'charset'  => 'utf8mb4',
];
