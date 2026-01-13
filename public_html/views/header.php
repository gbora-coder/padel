<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$basePath = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
$assetUrl = ($basePath ? $basePath : '') . '/assets/style.css';
$baseNav = $basePath ?: '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DigiPadel</title>
    <link rel="stylesheet" href="<?= h($assetUrl) ?>">
</head>
<body>
<header class="topbar">
    <div class="brand">DigiPadel</div>
    <nav>
        <a href="<?= h($baseNav) ?>/tournaments.php">Tournaments</a>
        <a href="<?= h($baseNav) ?>/players.php">Players</a>
    </nav>
</header>
<main class="container">
<?php if ($message = flash('success')): ?>
    <div class="alert success"><?= h($message) ?></div>
<?php endif; ?>
<?php if ($message = flash('error')): ?>
    <div class="alert error"><?= h($message) ?></div>
<?php endif; ?>
