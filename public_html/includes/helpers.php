<?php
require_once __DIR__ . '/db.php';

function h(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function redirect(string $location): void
{
    header('Location: ' . $location);
    exit;
}

function flash(string $key, ?string $message = null)
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    if ($message === null) {
        if (!isset($_SESSION['flash'][$key])) {
            return null;
        }
        $msg = $_SESSION['flash'][$key];
        unset($_SESSION['flash'][$key]);
        return $msg;
    }
    $_SESSION['flash'][$key] = $message;
}

function format_date(string $date): string
{
    return date('M j, Y', strtotime($date));
}

function fetch_levels(): array
{
    return ['A', 'B', 'C', 'D'];
}

function require_tournament(int $id): array
{
    $pdo = get_pdo();
    $stmt = $pdo->prepare('SELECT * FROM tournaments WHERE id = ?');
    $stmt->execute([$id]);
    $tournament = $stmt->fetch();
    if (!$tournament) {
        http_response_code(404);
        exit('Tournament not found');
    }
    return $tournament;
}

function players_in_use(int $playerId): bool
{
    $pdo = get_pdo();
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM tournament_players WHERE player_id = ?');
    $stmt->execute([$playerId]);
    return (bool)$stmt->fetchColumn();
}

function court_label(int $num): string
{
    return 'Court ' . $num;
}

function is_tournament_locked(int $tournamentId): bool
{
    $pdo = get_pdo();
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM games WHERE tournament_id = ?');
    $stmt->execute([$tournamentId]);
    return (int)$stmt->fetchColumn() > 0;
}

function view_path(string $file): string
{
    $candidates = [
        __DIR__ . '/../views/' . $file,
        __DIR__ . '/views/' . $file,
    ];

    foreach ($candidates as $candidate) {
        if (file_exists($candidate)) {
            return $candidate;
        }
    }

    throw new RuntimeException('View not found: ' . $file);
}

if (!function_exists('games_count_for_court')) {
    function games_count_for_court(int $courtId): int
    {
        $pdo = get_pdo();
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM games WHERE court_id = ?');
        $stmt->execute([$courtId]);
        return (int)$stmt->fetchColumn();
    }
}
