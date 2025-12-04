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
            ensure_schema_upgrades($pdo);
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

function ensure_schema_upgrades(PDO $pdo): void
{
    static $checked = false;
    if ($checked) {
        return;
    }

    $cfg = db_config();

    try {
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = ? AND table_name = 'tournaments' AND column_name = 'target_points'"
        );
        $stmt->execute([$cfg['db_name']]);
        $exists = (int)$stmt->fetchColumn() > 0;

        if (!$exists) {
            $pdo->exec("ALTER TABLE tournaments ADD COLUMN target_points TINYINT NOT NULL DEFAULT 21 AFTER num_courts");
        }
    } catch (PDOException $e) {
        // Surface a clear message if the automatic migration fails so the organizer can fix the schema manually.
        http_response_code(500);
        echo '<h1>Database upgrade required</h1>';
        echo '<p>Could not update the database schema automatically. Please add a <code>target_points</code> column to the <code>tournaments</code> table (TINYINT NOT NULL DEFAULT 21).</p>';
        echo '<p><small>' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</small></p>';
        exit;
    }

    $checked = true;
}
