<?php
return [
    'driver' => 'mysql',
    'credentials' => [
        'host' => $_ENV['DB_HOST'] ?? 'localhost:3306',
        'database' => $_ENV['DB_NAME'] ?? 'amicididongiorgio',
        'username' => $_ENV['DB_USER'] ?? 'root',
        'password' => $_ENV['DB_PASS'] ?? ''
    ]
];
