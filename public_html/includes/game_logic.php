<?php
require_once __DIR__ . '/helpers.php';

function leaderboard(int $tournamentId): array
{
    $pdo = get_pdo();
    $stmt = $pdo->prepare('SELECT tp.*, p.name, p.level FROM tournament_players tp JOIN players p ON p.id = tp.player_id WHERE tp.tournament_id = ? ORDER BY tp.points DESC, tp.games_played ASC, p.name ASC');
    $stmt->execute([$tournamentId]);
    return $stmt->fetchAll();
}

function final_leaderboard(int $tournamentId): array
{
    $pdo = get_pdo();
    $stmt = $pdo->prepare('SELECT tp.*, p.name, p.level, (CASE WHEN tp.games_played = 0 THEN 0 ELSE tp.points / tp.games_played END) as avg_points FROM tournament_players tp JOIN players p ON p.id = tp.player_id WHERE tp.tournament_id = ? ORDER BY tp.points DESC, avg_points DESC, tp.games_played ASC, p.name ASC');
    $stmt->execute([$tournamentId]);
    return $stmt->fetchAll();
}

function active_games(int $tournamentId): array
{
    $pdo = get_pdo();
    $stmt = $pdo->prepare('SELECT * FROM games WHERE tournament_id = ? AND status = "active"');
    $stmt->execute([$tournamentId]);
    return $stmt->fetchAll();
}

function players_assigned_with_game_number(int $tournamentId): array
{
    $pdo = get_pdo();
    $sql = 'SELECT gp.tournament_player_id, g.court_id, (
                SELECT COUNT(*) FROM games g2 WHERE g2.court_id = g.court_id AND g2.id <= g.id
            ) AS game_number
            FROM game_players gp
            JOIN games g ON g.id = gp.game_id
            WHERE g.tournament_id = ?';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$tournamentId]);
    return $stmt->fetchAll();
}

function current_game_for_court(int $courtId)
{
    $pdo = get_pdo();
    $stmt = $pdo->prepare('SELECT * FROM games WHERE court_id = ? AND status = "active" ORDER BY id DESC LIMIT 1');
    $stmt->execute([$courtId]);
    return $stmt->fetch();
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

function game_players_with_details(int $gameId): array
{
    $pdo = get_pdo();
    $sql = 'SELECT gp.*, p.name, p.level FROM game_players gp JOIN tournament_players tp ON tp.id = gp.tournament_player_id JOIN players p ON p.id = tp.player_id WHERE gp.game_id = ? ORDER BY gp.team, gp.id';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$gameId]);
    return $stmt->fetchAll();
}

function player_history_pairs(int $tournamentId): array
{
    $pdo = get_pdo();
    $stmt = $pdo->prepare('SELECT gp1.tournament_player_id as p1, gp2.tournament_player_id as p2, gp1.team as team1, gp2.team as team2 FROM game_players gp1 JOIN game_players gp2 ON gp1.game_id = gp2.game_id AND gp1.tournament_player_id < gp2.tournament_player_id JOIN games g ON g.id = gp1.game_id WHERE g.tournament_id = ? AND g.status = "completed"');
    $stmt->execute([$tournamentId]);
    $rows = $stmt->fetchAll();
    $teammates = [];
    $opponents = [];
    foreach ($rows as $row) {
        $key = $row['p1'] . '-' . $row['p2'];
        if ($row['team1'] === $row['team2']) {
            $teammates[$key] = ($teammates[$key] ?? 0) + 1;
        } else {
            $opponents[$key] = ($opponents[$key] ?? 0) + 1;
        }
    }
    return ['mates' => $teammates, 'opp' => $opponents];
}

function eligible_players(int $tournamentId, int $targetGameNumber, int $courtId): array
{
    $pdo = get_pdo();
    $assigned = players_assigned_with_game_number($tournamentId);
    $ineligible = [];
    foreach ($assigned as $row) {
        if ((int)$row['game_number'] === $targetGameNumber && (int)$row['court_id'] !== $courtId) {
            $ineligible[] = (int)$row['tournament_player_id'];
        }
    }

    $ineligible = array_values(array_unique($ineligible));

    $sql = 'SELECT tp.*, p.name, p.level FROM tournament_players tp JOIN players p ON p.id = tp.player_id WHERE tp.tournament_id = ?';
    $params = [$tournamentId];
    if ($ineligible) {
        $placeholders = str_repeat('?,', count($ineligible) - 1) . '?';
        $sql .= ' AND tp.id NOT IN (' . $placeholders . ')';
        $params = array_merge($params, $ineligible);
    }
    $sql .= ' ORDER BY tp.games_played ASC, tp.points ASC, RAND()';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function combination_valid(array $players, array $team, array $history): bool
{
    $team1 = [$players[$team[0]], $players[$team[1]]];
    $team2 = [];
    foreach ($players as $idx => $p) {
        if (!in_array($idx, $team, true)) {
            $team2[] = $p;
        }
    }
    foreach ([$team1, $team2] as $t) {
        $levels = [$t[0]['level'], $t[1]['level']];
        if ($levels[0] === 'D' && $levels[1] === 'D') {
            return false;
        }
    }

    // Avoid frequent teammate/opponent repeats
    $mateKey1 = teammate_key($team1[0]['id'], $team1[1]['id']);
    $mateKey2 = teammate_key($team2[0]['id'], $team2[1]['id']);
    if (($history['mates'][$mateKey1] ?? 0) >= 2 || ($history['mates'][$mateKey2] ?? 0) >= 2) {
        return false;
    }
    $oppPairs = [
        teammate_key($team1[0]['id'], $team2[0]['id']),
        teammate_key($team1[0]['id'], $team2[1]['id']),
        teammate_key($team1[1]['id'], $team2[0]['id']),
        teammate_key($team1[1]['id'], $team2[1]['id']),
    ];
    foreach ($oppPairs as $pair) {
        if (($history['opp'][$pair] ?? 0) >= 3) {
            return false;
        }
    }
    return true;
}

function teammate_key(int $a, int $b): string
{
    $sorted = [$a, $b];
    sort($sorted);
    return implode('-', $sorted);
}

function pick_players_for_game(int $tournamentId, int $targetGameNumber, int $courtId): ?array
{
    $eligible = eligible_players($tournamentId, $targetGameNumber, $courtId);
    if (count($eligible) < 4) {
        return null;
    }
    $history = player_history_pairs($tournamentId);
    // consider first 8 players for combinations to keep it efficient
    $pool = array_slice($eligible, 0, max(6, count($eligible)));
    $count = count($pool);
    for ($a = 0; $a < $count - 3; $a++) {
        for ($b = $a + 1; $b < $count - 2; $b++) {
            for ($c = $b + 1; $c < $count - 1; $c++) {
                for ($d = $c + 1; $d < $count; $d++) {
                    $group = [$pool[$a], $pool[$b], $pool[$c], $pool[$d]];
                    $teamCombos = [[0,1],[0,2],[0,3]]; // remaining players form team2 automatically
                    foreach ($teamCombos as $combo) {
                        if (combination_valid($group, $combo, $history)) {
                            $team1 = [$group[$combo[0]], $group[$combo[1]]];
                            $team2 = [];
                            foreach ($group as $idx => $p) {
                                if (!in_array($idx, $combo, true)) {
                                    $team2[] = $p;
                                }
                            }
                            return ['team1' => $team1, 'team2' => $team2];
                        }
                    }
                }
            }
        }
    }
    return null;
}

function create_game(int $tournamentId, int $courtId, array $teams): int
{
    $pdo = get_pdo();
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('INSERT INTO games (tournament_id, court_id, status, created_at) VALUES (?, ?, "active", NOW())');
        $stmt->execute([$tournamentId, $courtId]);
        $gameId = (int)$pdo->lastInsertId();
        foreach ([1 => $teams['team1'], 2 => $teams['team2']] as $teamNum => $players) {
            foreach ($players as $p) {
                $stmtPlayer = $pdo->prepare('INSERT INTO game_players (game_id, tournament_player_id, team) VALUES (?, ?, ?)');
                $stmtPlayer->execute([$gameId, $p['id'], $teamNum]);
            }
        }
        $pdo->commit();
        return $gameId;
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function assign_game_to_court(int $tournamentId, int $courtId): ?int
{
    $nextGameNumber = games_count_for_court($courtId) + 1;
    $teams = pick_players_for_game($tournamentId, $nextGameNumber, $courtId);
    if (!$teams) {
        return null;
    }
    return create_game($tournamentId, $courtId, $teams);
}

function save_game_result(int $gameId, int $team1Score, int $team2Score): void
{
    $pdo = get_pdo();
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('UPDATE games SET status = "completed", team1_score = ?, team2_score = ?, completed_at = NOW() WHERE id = ?');
        $stmt->execute([$team1Score, $team2Score, $gameId]);
        $stmtPlayers = $pdo->prepare('SELECT gp.team, tp.id, tp.tournament_id FROM game_players gp JOIN tournament_players tp ON tp.id = gp.tournament_player_id WHERE gp.game_id = ?');
        $stmtPlayers->execute([$gameId]);
        $players = $stmtPlayers->fetchAll();
        foreach ($players as $player) {
            $pointsToAdd = $player['team'] == 1 ? $team1Score : $team2Score;
            $update = $pdo->prepare('UPDATE tournament_players SET points = points + ?, games_played = games_played + 1 WHERE id = ?');
            $update->execute([$pointsToAdd, $player['id']]);
        }
        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function games_played_count(int $tournamentId): int
{
    $pdo = get_pdo();
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM games WHERE tournament_id = ? AND status = "completed"');
    $stmt->execute([$tournamentId]);
    return (int)$stmt->fetchColumn();
}

function check_tournament_finished(int $tournamentId): void
{
    $pdo = get_pdo();
    $stmt = $pdo->prepare('SELECT id, status FROM courts WHERE tournament_id = ?');
    $stmt->execute([$tournamentId]);
    $courts = $stmt->fetchAll();

    $activeGames = active_games($tournamentId);
    if ($activeGames) {
        return; // still running
    }

    $assignable = false;
    foreach ($courts as $court) {
        if ($court['status'] === 'active') {
            $nextNumber = games_count_for_court((int)$court['id']) + 1;
            $teams = pick_players_for_game($tournamentId, $nextNumber, (int)$court['id']);
            if ($teams) {
                $assignable = true;
                break;
            }
        }
    }

    if (!$assignable) {
        $upd = $pdo->prepare('UPDATE tournaments SET status = "finished" WHERE id = ?');
        $upd->execute([$tournamentId]);
    }
}
