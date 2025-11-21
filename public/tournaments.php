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
$action = $_POST['action'] ?? $_GET['action'] ?? null;

if ($action === 'create') {
    $name = trim($_POST['name'] ?? '');
    $date = $_POST['date'] ?? '';
    $courts = max(1, (int)($_POST['num_courts'] ?? 1));
    $notes = trim($_POST['notes'] ?? '');
    if ($name === '' || $date === '') {
        flash('error', 'Please provide name and date.');
    } else {
        $stmt = $pdo->prepare('INSERT INTO tournaments (name, date, num_courts, notes, status, created_at, updated_at) VALUES (?, ?, ?, ?, "setup", NOW(), NOW())');
        $stmt->execute([$name, $date, $courts, $notes]);
        $tournamentId = (int)$pdo->lastInsertId();
        for ($i = 1; $i <= $courts; $i++) {
            $pdo->prepare('INSERT INTO courts (tournament_id, court_number, status) VALUES (?, ?, "active")')->execute([$tournamentId, $i]);
        }
        flash('success', 'Tournament created.');
        redirect('/tournament.php?id=' . $tournamentId);
    }
    redirect('/tournaments.php');
}

if ($action === 'clone') {
    $sourceId = (int)($_POST['id'] ?? 0);
    $stmt = $pdo->prepare('SELECT * FROM tournaments WHERE id = ?');
    $stmt->execute([$sourceId]);
    if ($src = $stmt->fetch()) {
        $stmtIns = $pdo->prepare('INSERT INTO tournaments (name, date, num_courts, notes, status, created_at, updated_at) VALUES (?, ?, ?, ?, "setup", NOW(), NOW())');
        $stmtIns->execute([$src['name'] . ' (Copy)', $src['date'], $src['num_courts'], $src['notes']]);
        $newId = (int)$pdo->lastInsertId();
        for ($i = 1; $i <= $src['num_courts']; $i++) {
            $pdo->prepare('INSERT INTO courts (tournament_id, court_number, status) VALUES (?, ?, "active")')->execute([$newId, $i]);
        }
        $players = $pdo->prepare('SELECT player_id FROM tournament_players WHERE tournament_id = ?');
        $players->execute([$sourceId]);
        foreach ($players->fetchAll(PDO::FETCH_COLUMN) as $pid) {
            $pdo->prepare('INSERT INTO tournament_players (tournament_id, player_id, points, games_played) VALUES (?, ?, 0, 0)')->execute([$newId, $pid]);
        }
        flash('success', 'Tournament cloned.');
        redirect('/tournament.php?id=' . $newId);
    } else {
        flash('error', 'Original tournament not found.');
    }
    redirect('/tournaments.php');
}

$stmt = $pdo->query('SELECT * FROM tournaments ORDER BY date DESC, id DESC');
$tournaments = $stmt->fetchAll();

include __DIR__ . '/../views/header.php';
?>
<div class="card">
    <h2>Create Tournament</h2>
    <form method="post">
        <input type="hidden" name="action" value="create">
        <div class="row">
            <div class="col">
                <label>Name</label>
                <input type="text" name="name" required>
            </div>
            <div class="col">
                <label>Date</label>
                <input type="date" name="date" required>
            </div>
            <div class="col">
                <label>Courts</label>
                <input type="number" name="num_courts" min="1" value="1" required>
            </div>
        </div>
        <label style="margin-top:8px; display:block;">Notes</label>
        <textarea name="notes" rows="2" placeholder="Description"></textarea>
        <button type="submit" class="btn" style="margin-top:10px;">Create Tournament</button>
    </form>
</div>
<div class="card">
    <h2>All Tournaments</h2>
    <table class="table">
        <thead>
            <tr>
                <th>Name</th>
                <th>Date</th>
                <th>Courts</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($tournaments as $t): ?>
            <tr>
                <td><?= h($t['name']) ?></td>
                <td><?= format_date($t['date']) ?></td>
                <td><?= (int)$t['num_courts'] ?></td>
                <td><span class="badge"><?= h($t['status']) ?></span></td>
                <td>
        <a class="btn inline" href="/tournament.php?id=<?= (int)$t['id'] ?>">Manage</a>
                    <form method="post" style="display:inline;">
                        <input type="hidden" name="action" value="clone">
                        <input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
                        <button class="btn secondary inline" type="submit">Clone</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php include __DIR__ . '/../views/footer.php'; ?>
