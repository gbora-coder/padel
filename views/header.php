<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Padel Americano League</title>
    <link rel="stylesheet" href="/assets/style.css">
</head>
<body>
<header class="topbar">
    <div class="brand">Padel Americano League</div>
    <nav>
        <a href="/tournaments.php">Tournaments</a>
        <a href="/players.php">Players</a>
    </nav>
</header>
<main class="container">
<?php if ($message = flash('success')): ?>
    <div class="alert success"><?= h($message) ?></div>
<?php endif; ?>
<?php if ($message = flash('error')): ?>
    <div class="alert error"><?= h($message) ?></div>
<?php endif; ?>
