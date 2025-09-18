<?php

return [
    'app.env' => $_ENV['APP_ENV'] ?? 'prod',
    'app.debug' => (bool) ($_ENV['APP_DEBUG'] ?? false),
    'app.base_url' => $_ENV['BASE_URL'] ?? 'http://localhost:8000',
    'db.host' => $_ENV['DB_HOST'] ?? '127.0.0.1',
    'db.port' => (int) ($_ENV['DB_PORT'] ?? 3306),
    'db.name' => $_ENV['DB_NAME'] ?? 'genesis_reborn',
    'db.user' => $_ENV['DB_USER'] ?? 'root',
    'db.pass' => ($_ENV['DB_PASS'] ?? null) ?: null,
];
