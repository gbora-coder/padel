<?php
$incDir = __DIR__ . '/../includes';
if (!is_dir($incDir)) {
    $incDir = __DIR__ . '/includes';
}
if (!is_dir($incDir)) {
    throw new RuntimeException('Includes directory not found');
}
require_once $incDir . '/helpers.php';

$pdo = get_pdo();
$levels = fetch_levels();
$action = $_POST['action'] ?? $_GET['action'] ?? null;

if ($action === 'create') {
    $name = trim($_POST['name'] ?? '');
    $level = $_POST['level'] ?? '';
    if ($name === '' || !in_array($level, $levels, true)) {
        flash('error', 'Please provide a valid name and level.');
    } else {
        $stmt = $pdo->prepare('INSERT INTO players (name, level, created_at, updated_at) VALUES (?, ?, NOW(), NOW())');
        $stmt->execute([$name, $level]);
        flash('success', 'Player created.');
    }
    redirect('/players.php');
}

if ($action === 'update') {
    $id = (int)($_POST['id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $level = $_POST['level'] ?? '';
    if ($id && $name !== '' && in_array($level, $levels, true)) {
        $stmt = $pdo->prepare('UPDATE players SET name = ?, level = ?, updated_at = NOW() WHERE id = ?');
        $stmt->execute([$name, $level, $id]);
        flash('success', 'Player updated.');
    } else {
        flash('error', 'Invalid data for update.');
    }
    redirect('/players.php');
}

if ($action === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id) {
        if (players_in_use($id)) {
            flash('error', 'Player is part of a tournament and cannot be deleted.');
        } else {
            $stmt = $pdo->prepare('DELETE FROM players WHERE id = ?');
            $stmt->execute([$id]);
            flash('success', 'Player deleted.');
        }
    }
    redirect('/players.php');
}

$levelFilter = $_GET['level'] ?? '';
$sort = $_GET['sort'] ?? 'name';
$direction = $_GET['dir'] ?? 'asc';
$allowedSort = ['name', 'level'];
$sortField = in_array($sort, $allowedSort, true) ? $sort : 'name';
$dir = strtolower($direction) === 'desc' ? 'DESC' : 'ASC';

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 10;
$offset = ($page - 1) * $perPage;

$where = 'WHERE 1=1';
$params = [];
if ($levelFilter && in_array($levelFilter, $levels, true)) {
    $where .= ' AND level = ?';
    $params[] = $levelFilter;
}

$totalStmt = $pdo->prepare("SELECT COUNT(*) FROM players $where");
$totalStmt->execute($params);
$total = (int)$totalStmt->fetchColumn();

$listStmt = $pdo->prepare("SELECT * FROM players $where ORDER BY $sortField $dir LIMIT $perPage OFFSET $offset");
$listStmt->execute($params);
$players = $listStmt->fetchAll();

include __DIR__ . '/../views/header.php';
?>
<div class="card">
    <h2>Players</h2>
    <form method="get" class="inline-form" style="margin-bottom:12px;">
        <label for="level">Filter by level:</label>
        <select name="level" id="level" onchange="this.form.submit()">
            <option value="">All</option>
            <?php foreach ($levels as $lvl): ?>
                <option value="<?= h($lvl) ?>" <?= $levelFilter === $lvl ? 'selected' : '' ?>><?= h($lvl) ?></option>
            <?php endforeach; ?>
        </select>
    </form>
    <div class="card" style="padding:12px;">
        <h3>Add Player</h3>
        <form method="post">
            <input type="hidden" name="action" value="create">
            <div class="row">
                <div class="col">
                    <label>Name</label>
                    <input type="text" name="name" required placeholder="Player name">
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
            </div>
            <button class="btn" type="submit" style="margin-top:10px;">Create Player</button>
        </form>
    </div>
    <table class="table">
        <thead>
            <tr>
                <th><a href="?sort=name&dir=<?= $dir === 'ASC' ? 'desc' : 'asc' ?>">Name</a></th>
                <th><a href="?sort=level&dir=<?= $dir === 'ASC' ? 'desc' : 'asc' ?>">Level</a></th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($players as $player): ?>
                <tr>
                    <td><?= h($player['name']) ?></td>
                    <td><?= h($player['level']) ?></td>
                    <td>
                        <details>
                            <summary class="btn secondary inline">Edit</summary>
                            <form method="post" style="margin-top:8px;">
                                <input type="hidden" name="action" value="update">
                                <input type="hidden" name="id" value="<?= (int)$player['id'] ?>">
                                <input type="text" name="name" value="<?= h($player['name']) ?>" required>
                                <select name="level" required>
                                    <?php foreach ($levels as $lvl): ?>
                                        <option value="<?= h($lvl) ?>" <?= $player['level'] === $lvl ? 'selected' : '' ?>><?= h($lvl) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <button class="btn inline" type="submit">Save</button>
                            </form>
                        </details>
                        <form method="post" onsubmit="return confirm('Delete this player?')" style="display:inline;">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= (int)$player['id'] ?>">
                            <button class="btn danger inline" type="submit">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <div class="small">Page <?= $page ?> of <?= max(1, ceil($total / $perPage)) ?></div>
    <div style="margin-top:8px;">
        <?php for ($i = 1; $i <= max(1, ceil($total / $perPage)); $i++): ?>
            <a class="btn secondary inline" href="?page=<?= $i ?>&sort=<?= h($sortField) ?>&dir=<?= strtolower($dir) ?>&level=<?= h($levelFilter) ?>"><?= $i ?></a>
        <?php endfor; ?>
    </div>
</div>
<?php include __DIR__ . '/../views/footer.php'; ?>
