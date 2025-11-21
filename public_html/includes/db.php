<?php
$config = require __DIR__ . '/config.php';

function get_pdo(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        global $config;
        $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', $config['db_host'], $config['db_name'], $config['db_charset']);
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ];
        $pdo = new PDO($dsn, $config['db_user'], $config['db_pass'], $options);
    }
    return $pdo;
}
