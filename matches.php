<?php
/* Admin: manage matches (add / edit / delete) - Rahaf */
require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/config/db.php';
require_role('admin');

$error   = '';
$success = '';

// Teams for the dropdowns
$teams = $pdo->query("SELECT id, name FROM teams ORDER BY name")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add' || $action === 'update') {
        $home   = (int)($_POST['home_team_id'] ?? 0);
        $away   = (int)($_POST['away_team_id'] ?? 0);
        $date   = $_POST['match_date'] ?? '';
        $time   = $_POST['match_time'] ?? '';
        $status = $_POST['status'] ?? 'upcoming';
        $id     = (int)($_POST['id'] ?? 0);

        if ($home === 0 || $away === 0 || $date === '' || $time === '') {
            $error = 'Please fill in all fields.';
        } elseif ($home === $away) {
            $error = 'The two teams must be different.';
        } elseif (!in_array($status, ['upcoming', 'finished', 'cancelled'], true)) {
            $error = 'Invalid status.';
        } else {
            if ($action === 'add') {
                $stmt = $pdo->prepare(
                    "INSERT INTO matches (home_team_id, away_team_id, match_date, match_time)
                     VALUES (:h, :a, :d, :t)"
                );
                $stmt->execute([':h' => $home, ':a' => $away, ':d' => $date, ':t' => $time]);
                header('Location: matches.php?msg=added');
            } else {
                $stmt = $pdo->prepare(
                    "UPDATE matches SET home_team_id = :h, away_team_id = :a,
                            match_date = :d, match_time = :t, status = :s
                     WHERE id = :id"
                );
                $stmt->execute([':h' => $home, ':a' => $away, ':d' => $date,
                                ':t' => $time, ':s' => $status, ':id' => $id]);
                header('Location: matches.php?msg=updated');
            }
            exit;
        }

    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        try {
            $stmt = $pdo->prepare("DELETE FROM matches WHERE id = :id");
            $stmt->execute([':id' => $id]);
            header('Location: matches.php?msg=deleted');
            exit;
        } catch (PDOException $e) {
            // Foreign key: the match already has bookings/tickets
            $error = 'This match already has bookings, so it cannot be deleted. '
                   . 'Edit it and set the status to "cancelled" instead.';
        }
    }
}

$messages = [
    'added'   => 'Match added.',
    'updated' => 'Match updated.',
    'deleted' => 'Match deleted.',
];
if (isset($_GET['msg'], $messages[$_GET['msg']])) {
    $success = $messages[$_GET['msg']];
}

// Edit mode: load the match into the form
$edit = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM matches WHERE id = :id");
    $stmt->execute([':id' => (int)$_GET['edit']]);
    $edit = $stmt->fetch() ?: null;
}

$matches = $pdo->query(
    "SELECT m.id, m.match_date, m.match_time, m.status,
            th.name AS home_team, ta.name AS away_team
     FROM matches m
     JOIN teams th ON th.id = m.home_team_id
     JOIN teams ta ON ta.id = m.away_team_id
     ORDER BY m.match_date, m.match_time"
)->fetchAll();

$page_title = 'Manage Matches';
require_once __DIR__ . '/includes/header.php';
?>

<h1 class="page-title">Manage Matches</h1>

<?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
<?php if ($error):   ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<!-- Add / Edit form -->
<div class="card">
  <h2><?= $edit ? 'Edit match #' . (int)$edit['id'] : 'Add a new match' ?></h2>
  <form method="post" action="matches.php">
    <input type="hidden" name="action" value="<?= $edit ? 'update' : 'add' ?>">
    <?php if ($edit): ?><input type="hidden" name="id" value="<?= (int)$edit['id'] ?>"><?php endif; ?>

    <div class="form-row">
      <div class="form-group">
        <label for="home_team_id">First team (home)</label>
        <select id="home_team_id" name="home_team_id" class="form-control" required>
          <option value="">Choose...</option>
          <?php foreach ($teams as $t): ?>
            <option value="<?= (int)$t['id'] ?>" <?= ($edit && $edit['home_team_id'] == $t['id']) ? 'selected' : '' ?>>
              <?= htmlspecialchars($t['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label for="away_team_id">Second team (away)</label>
        <select id="away_team_id" name="away_team_id" class="form-control" required>
          <option value="">Choose...</option>
          <?php foreach ($teams as $t): ?>
            <option value="<?= (int)$t['id'] ?>" <?= ($edit && $edit['away_team_id'] == $t['id']) ? 'selected' : '' ?>>
              <?= htmlspecialchars($t['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label for="match_date">Date</label>
        <input type="date" id="match_date" name="match_date" class="form-control" required
               value="<?= $edit ? htmlspecialchars($edit['match_date']) : '' ?>">
      </div>
      <div class="form-group">
        <label for="match_time">Time</label>
        <input type="time" id="match_time" name="match_time" class="form-control" required
               value="<?= $edit ? htmlspecialchars(substr($edit['match_time'], 0, 5)) : '' ?>">
      </div>
      <?php if ($edit): ?>
      <div class="form-group">
        <label for="status">Status</label>
        <select id="status" name="status" class="form-control">
          <?php foreach (['upcoming', 'finished', 'cancelled'] as $s): ?>
            <option value="<?= $s ?>" <?= $edit['status'] === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php endif; ?>
    </div>

    <button type="submit" class="btn"><?= $edit ? 'Save changes' : 'Add match' ?></button>
    <?php if ($edit): ?><a href="matches.php" class="btn btn-secondary">Cancel</a><?php endif; ?>
  </form>
</div>

<!-- Matches table -->
<div class="card">
  <h2>All matches</h2>
  <?php if (count($matches) === 0): ?>
    <p>No matches yet.</p>
  <?php else: ?>
  <div class="table-wrap">
    <table class="data">
      <tr><th>#</th><th>Match</th><th>Date</th><th>Time</th><th>Status</th><th>Actions</th></tr>
      <?php foreach ($matches as $m): ?>
        <tr>
          <td><?= (int)$m['id'] ?></td>
          <td><?= htmlspecialchars($m['home_team']) ?> vs <?= htmlspecialchars($m['away_team']) ?></td>
          <td><?= htmlspecialchars($m['match_date']) ?></td>
          <td><?= htmlspecialchars(substr($m['match_time'], 0, 5)) ?></td>
          <td><span class="badge badge-<?= htmlspecialchars($m['status']) ?>"><?= htmlspecialchars($m['status']) ?></span></td>
          <td>
            <a href="matches.php?edit=<?= (int)$m['id'] ?>" class="btn btn-small btn-secondary">Edit</a>
            <form method="post" action="matches.php" class="inline-form"
                  onsubmit="return confirm('Delete this match?');">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
              <button type="submit" class="btn btn-small btn-danger">Delete</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    </table>
  </div>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
