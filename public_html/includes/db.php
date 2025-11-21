<?php
// Load config and allow environment variable overrides for easy hosting deployments
$config = require __DIR__ . '/config.php';

function db_config(): array
{
    global $config;
    static $merged = null;

    if ($merged === null) {
        $merged = $config;
        $envMap = [
            'DB_HOST' => 'db_host',
            'DB_NAME' => 'db_name',
            'DB_USER' => 'db_user',
            'DB_PASS' => 'db_pass',
            'DB_CHARSET' => 'db_charset',
        ];

        foreach ($envMap as $env => $key) {
            $value = getenv($env);
            if ($value !== false && $value !== '') {
                $merged[$key] = $value;
            }
        }
    }

    return $merged;
}

function get_pdo(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $cfg = db_config();
        $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', $cfg['db_host'], $cfg['db_name'], $cfg['db_charset']);
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ];
        try {
            $pdo = new PDO($dsn, $cfg['db_user'], $cfg['db_pass'], $options);
        } catch (PDOException $e) {
            http_response_code(500);
            $friendly = 'Database connection failed. Update credentials in public_html/includes/config.php ' .
                'or set DB_HOST, DB_NAME, DB_USER, DB_PASS environment variables.';
            echo '<h1>Database connection failed</h1>';
            echo '<p>' . htmlspecialchars($friendly, ENT_QUOTES, 'UTF-8') . '</p>';
            echo '<p><small>' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</small></p>';
            exit;
        }
    }
    return $pdo;
}
