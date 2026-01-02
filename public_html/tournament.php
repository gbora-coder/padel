<?php
$incDir = __DIR__ . '/../includes';
if (!is_dir($incDir)) {
    $incDir = __DIR__ . '/includes';
}
if (!is_dir($incDir)) {
    throw new RuntimeException('Includes directory not found');
}
require_once $incDir . '/helpers.php';
require_once $incDir . '/game_logic.php';

$pdo = get_pdo();
$id = (int)($_GET['id'] ?? 0);
$tournament = require_tournament($id);
$levels = fetch_levels();
$locked = is_tournament_locked($id);

if (isset($_GET['download']) && $_GET['download'] === 'csv' && $tournament['status'] === 'finished') {
    $rows = final_leaderboard($id);
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="tournament-' . $id . '-results.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Rank', 'Name', 'Level', 'Points', 'Games Played', 'Avg Points per Game']);
    $rank = 1;
    foreach ($rows as $r) {
        $avg = $r['games_played'] > 0 ? round($r['points'] / $r['games_played'], 2) : 0;
        fputcsv($out, [$rank++, $r['name'], $r['level'], $r['points'], $r['games_played'], $avg]);
    }
    fclose($out);
    exit;
}

$action = $_POST['action'] ?? null;

if ($action === 'add_existing' && !$locked) {
    $selected = $_POST['players'] ?? [];
    if ($selected) {
        foreach ($selected as $playerId) {
            $playerId = (int)$playerId;
            $exists = $pdo->prepare('SELECT COUNT(*) FROM tournament_players WHERE tournament_id = ? AND player_id = ?');
            $exists->execute([$id, $playerId]);
            if (!$exists->fetchColumn()) {
                $pdo->prepare('INSERT INTO tournament_players (tournament_id, player_id, points, games_played) VALUES (?, ?, 0, 0)')->execute([$id, $playerId]);
            }
        }
        flash('success', 'Players added to tournament.');
    }
    redirect('/tournament.php?id=' . $id);
}

if ($action === 'add_new' && !$locked) {
    $name = trim($_POST['name'] ?? '');
    $level = $_POST['level'] ?? '';
    if ($name && in_array($level, $levels, true)) {
        $pdo->prepare('INSERT INTO players (name, level, created_at, updated_at) VALUES (?, ?, NOW(), NOW())')->execute([$name, $level]);
        $playerId = (int)$pdo->lastInsertId();
        $pdo->prepare('INSERT INTO tournament_players (tournament_id, player_id, points, games_played) VALUES (?, ?, 0, 0)')->execute([$id, $playerId]);
        flash('success', 'Player added.');
    } else {
        flash('error', 'Please provide name and level.');
    }
    redirect('/tournament.php?id=' . $id);
}

if ($action === 'remove_player' && !$locked) {
    $tpId = (int)($_POST['tp_id'] ?? 0);
    $pdo->prepare('DELETE FROM tournament_players WHERE id = ? AND tournament_id = ?')->execute([$tpId, $id]);
    flash('success', 'Player removed from tournament.');
    redirect('/tournament.php?id=' . $id);
}

if ($action === 'start' && $tournament['status'] === 'setup') {
    $countStmt = $pdo->prepare('SELECT COUNT(*) FROM tournament_players WHERE tournament_id = ?');
    $countStmt->execute([$id]);
    $count = (int)$countStmt->fetchColumn();
    if ($count < 4) {
        flash('error', 'You need at least 4 players to start.');
        redirect('/tournament.php?id=' . $id);
    }
    $pdo->prepare('UPDATE tournaments SET status = "active", updated_at = NOW() WHERE id = ?')->execute([$id]);
    $courts = $pdo->prepare('SELECT * FROM courts WHERE tournament_id = ?');
    $courts->execute([$id]);
    foreach ($courts->fetchAll() as $court) {
        $existing = current_game_for_court((int)$court['id']);
        if (!$existing) {
            assign_game_to_court($id, (int)$court['id']);
        }
    }
    flash('success', 'Tournament started.');
    redirect('/tournament.php?id=' . $id);
}

if ($action === 'save_result') {
    $gameId = (int)($_POST['game_id'] ?? 0);
    $courtId = (int)($_POST['court_id'] ?? 0);
    $team1 = (int)($_POST['team1_score'] ?? -1);
    $team2 = (int)($_POST['team2_score'] ?? -1);
    $finish = isset($_POST['finish']);
    $targetPoints = (int)$tournament['target_points'];
    if (
        $gameId &&
        $team1 >= 0 &&
        $team2 >= 0 &&
        $team1 <= $targetPoints &&
        $team2 <= $targetPoints &&
        ($team1 + $team2) === $targetPoints
    ) {
        save_game_result($gameId, $team1, $team2);
        if ($finish) {
            $pdo->prepare('UPDATE courts SET status = "finished" WHERE id = ?')->execute([$courtId]);
        } else {
            $newGame = assign_game_to_court($id, $courtId);
            if (!$newGame) {
                flash('error', 'No more games can be scheduled for this court right now.');
            }
        }
        check_tournament_finished($id);
        flash('success', 'Result saved.');
    } else {
        flash('error', 'Invalid score. Totals must equal ' . $targetPoints . ' points.');
    }
    redirect('/tournament.php?id=' . $id);
}

if ($action === 'generate_game') {
    $courtId = (int)($_POST['court_id'] ?? 0);
    $courtCheck = $pdo->prepare('SELECT status FROM courts WHERE id = ? AND tournament_id = ?');
    $courtCheck->execute([$courtId, $id]);
    $status = $courtCheck->fetchColumn();
    if ($status === 'finished') {
        flash('error', 'Court already finished.');
    } else {
        $newGame = assign_game_to_court($id, $courtId);
        if ($newGame) {
            flash('success', 'New game scheduled.');
        } else {
            flash('error', 'No available players to start a game on this court.');
        }
    }
    redirect('/tournament.php?id=' . $id);
}

if ($action === 'finish_court') {
    $courtId = (int)($_POST['court_id'] ?? 0);
    $pdo->prepare('UPDATE courts SET status = "finished" WHERE id = ?')->execute([$courtId]);
    check_tournament_finished($id);
    flash('success', 'Court marked as finished.');
    redirect('/tournament.php?id=' . $id);
}

// reload data
$tournament = require_tournament($id);
$scoreOptions = range(0, (int)$tournament['target_points']);
$locked = is_tournament_locked($id);

$playersStmt = $pdo->prepare('SELECT tp.*, p.name, p.level FROM tournament_players tp JOIN players p ON p.id = tp.player_id WHERE tp.tournament_id = ? ORDER BY p.name ASC');
$playersStmt->execute([$id]);
$tournamentPlayers = $playersStmt->fetchAll();

$courtsStmt = $pdo->prepare('SELECT * FROM courts WHERE tournament_id = ? ORDER BY court_number ASC');
$courtsStmt->execute([$id]);
$courts = $courtsStmt->fetchAll();

$activeGames = [];
foreach ($courts as $court) {
    $game = current_game_for_court((int)$court['id']);
    if ($game) {
        $activeGames[$court['id']] = $game;
    }
}

$leaderboard = leaderboard($id);
$playersCount = count($tournamentPlayers);

// players not in tournament for selection
$search = trim($_GET['q'] ?? '');
$sql = 'SELECT * FROM players WHERE id NOT IN (SELECT player_id FROM tournament_players WHERE tournament_id = ?)';
$params = [$id];
if ($search !== '') {
    $sql .= ' AND name LIKE ?';
    $params[] = '%' . $search . '%';
}
$sql .= ' ORDER BY name ASC LIMIT 25';
$availablePlayersStmt = $pdo->prepare($sql);
$availablePlayersStmt->execute($params);
$availablePlayers = $availablePlayersStmt->fetchAll();

include view_path('header.php');
?>
<div class="card">
    <h2><?= h($tournament['name']) ?> <span class="badge"><?= h($tournament['status']) ?></span></h2>
    <p class="small">Date: <?= format_date($tournament['date']) ?> | Courts: <?= (int)$tournament['num_courts'] ?> | Players: <?= $playersCount ?> | Target points: <?= (int)$tournament['target_points'] ?></p>
    <?php if ($tournament['status'] === 'setup'): ?>
        <form method="post" onsubmit="return confirm('Start tournament now?');">
            <input type="hidden" name="action" value="start">
            <button class="btn">Start Tournament</button>
        </form>
    <?php elseif ($tournament['status'] === 'finished'): ?>
        <a class="btn" href="?id=<?= $id ?>&download=csv">Download results (CSV)</a>
    <?php endif; ?>
</div>

<?php if ($tournament['status'] === 'setup'): ?>
<div class="card">
    <h3>Add players from database</h3>
    <form method="get" class="inline-form" style="margin-bottom:8px;">
        <input type="hidden" name="id" value="<?= $id ?>">
        <input type="text" name="q" value="<?= h($search) ?>" placeholder="Search players...">
        <button class="btn secondary" type="submit">Search</button>
    </form>
    <form method="post">
        <input type="hidden" name="action" value="add_existing">
        <table class="table">
            <thead>
                <tr><th></th><th>Name</th><th>Level</th></tr>
            </thead>
            <tbody>
                <?php foreach ($availablePlayers as $p): ?>
                    <tr>
                        <td><input type="checkbox" name="players[]" value="<?= (int)$p['id'] ?>"></td>
                        <td><?= h($p['name']) ?></td>
                        <td><?= h($p['level']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <button class="btn" type="submit">Add to tournament</button>
    </form>
    <hr>
    <h3>Add new player</h3>
    <form method="post" class="row">
        <input type="hidden" name="action" value="add_new">
        <div class="col">
            <label>Name</label>
            <input type="text" name="name" required>
        </div>
        <div class="col">
            <label>Level</label>
            <select name="level" required>
                <option value="">Select level</option>
                <?php foreach ($levels as $lvl): ?>
                    <option value="<?= h($lvl) ?>"><?= h($lvl) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col" style="align-self:flex-end;">
            <button class="btn" type="submit">Create &amp; Add</button>
        </div>
    </form>
</div>
<?php endif; ?>

<?php if ($tournament['status'] !== 'setup'): ?>
<div class="flex" style="gap:16px; align-items:flex-start; flex-wrap:wrap;">
    <div style="flex:2; min-width:320px;">
        <div class="courts">
            <?php foreach ($courts as $court): ?>
                <?php $game = $activeGames[$court['id']] ?? null; ?>
                <?php
                $gameCount = games_count_for_court((int)$court['id']);
                $gameNumber = $game ? $gameCount : ($court['status'] === 'active' ? $gameCount + 1 : $gameCount);
                ?>
                <div class="card <?= $court['status'] === 'finished' ? 'status-finished' : '' ?>">
                    <div class="flex" style="justify-content:space-between; align-items:center;">
                        <h3 class="court-title"><?= h(court_label($court['court_number'])) ?><?php if ($gameNumber > 0): ?> - Game <?= $gameNumber ?><?php endif; ?></h3>
                        <span class="badge"><?= h($court['status']) ?></span>
                    </div>
                    <?php if ($game): ?>
                        <?php $players = game_players_with_details((int)$game['id']); ?>
                        <div class="teams">
                            <p><strong>Team 1:</strong> <?= h($players[0]['name']) ?> (<?= h($players[0]['level']) ?>) + <?= h($players[1]['name']) ?> (<?= h($players[1]['level']) ?>)</p>
                            <p><strong>Team 2:</strong> <?= h($players[2]['name']) ?> (<?= h($players[2]['level']) ?>) + <?= h($players[3]['name']) ?> (<?= h($players[3]['level']) ?>)</p>
                        </div>
                        <form method="post" class="score-row">
                            <input type="hidden" name="action" value="save_result">
                            <input type="hidden" name="game_id" value="<?= (int)$game['id'] ?>">
                            <input type="hidden" name="court_id" value="<?= (int)$court['id'] ?>">
                            <div class="score-col">
                                <label>Team 1 games</label>
                                <select
                                    name="team1_score"
                                    class="score-select"
                                    id="team1-<?= (int)$game['id'] ?>"
                                    data-pair="team2-<?= (int)$game['id'] ?>"
                                    data-target="<?= (int)$tournament['target_points'] ?>"
                                    required
                                >
                                    <?php foreach ($scoreOptions as $opt): ?>
                                        <option value="<?= $opt ?>"><?= $opt ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="score-col right">
                                <label>Team 2 games</label>
                                <select
                                    name="team2_score"
                                    class="score-select"
                                    id="team2-<?= (int)$game['id'] ?>"
                                    data-pair="team1-<?= (int)$game['id'] ?>"
                                    data-target="<?= (int)$tournament['target_points'] ?>"
                                    required
                                >
                                    <?php foreach ($scoreOptions as $opt): ?>
                                        <option value="<?= $opt ?>"><?= $opt ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="score-actions">
                                <button class="btn inline" type="submit">Save &amp; New Game</button>
                                <button class="btn secondary inline" name="finish" value="1" type="submit">Save &amp; Finish</button>
                            </div>
                        </form>
                    <?php else: ?>
                        <?php if ($court['status'] === 'finished'): ?>
                            <p class="small">Court finished, no more games.</p>
                        <?php else: ?>
                            <p class="small">No game assigned currently.</p>
                            <form method="post">
                                <input type="hidden" name="action" value="generate_game">
                                <input type="hidden" name="court_id" value="<?= (int)$court['id'] ?>">
                                <button class="btn inline" type="submit">Generate game</button>
                            </form>
                            <form method="post" style="margin-top:8px;">
                                <input type="hidden" name="action" value="finish_court">
                                <input type="hidden" name="court_id" value="<?= (int)$court['id'] ?>">
                                <button class="btn secondary" type="submit" onclick="return confirm('Finish this court?')">Mark finished</button>
                            </form>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <div style="flex:1; min-width:280px;">
        <div class="card leaderboard">
            <h3>Leaderboard</h3>
            <table class="table">
                <thead><tr><th>#</th><th>Name</th><th>Lvl</th><th>Pts</th><th>Games</th></tr></thead>
                <tbody>
                    <?php $rank = 1; foreach ($leaderboard as $row): ?>
                        <tr>
                            <td><?= $rank++ ?></td>
                            <td><?= h($row['name']) ?></td>
                            <td><?= h($row['level']) ?></td>
                            <td><?= (int)$row['points'] ?></td>
                            <td><?= (int)$row['games_played'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if ($tournament['status'] === 'finished'): ?>
<div class="card">
    <h3>Final Leaderboard</h3>
    <?php $final = final_leaderboard($id); ?>
    <table class="table">
        <thead><tr><th>Rank</th><th>Name</th><th>Level</th><th>Points</th><th>Games</th><th>Avg PPG</th></tr></thead>
        <tbody>
            <?php $r = 1; foreach ($final as $row): ?>
                <tr class="<?= $r <= 3 ? 'highlight' : '' ?>">
                    <td><?= $r ?></td>
                    <td><?= h($row['name']) ?></td>
                    <td><?= h($row['level']) ?></td>
                    <td><?= (int)$row['points'] ?></td>
                    <td><?= (int)$row['games_played'] ?></td>
                    <td><?= $row['games_played'] ? number_format($row['points'] / $row['games_played'], 2) : '0.00' ?></td>
                </tr>
            <?php $r++; endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>
<script>
(function() {
    const selects = document.querySelectorAll('.score-select');
    function sync(select) {
        const pairId = select.dataset.pair;
        const target = parseInt(select.dataset.target, 10);
        const pair = document.getElementById(pairId);
        if (!pair || Number.isNaN(target)) return;
        const value = parseInt(select.value, 10);
        if (Number.isNaN(value)) return;
        const other = Math.max(0, target - value);
        pair.value = String(other);
    }
    selects.forEach((sel) => {
        sync(sel);
        sel.addEventListener('change', () => sync(sel));
    });
})();
</script>
    <?php include view_path('footer.php'); ?>
