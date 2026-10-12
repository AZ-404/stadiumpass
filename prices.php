<?php
/* Admin: set the price of each section for each match - Rahaf */
require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/config/db.php';
require_role('admin');

$error   = '';
$success = '';

$matches = $pdo->query(
    "SELECT m.id, m.match_date, th.name AS home_team, ta.name AS away_team
     FROM matches m
     JOIN teams th ON th.id = m.home_team_id
     JOIN teams ta ON ta.id = m.away_team_id
     ORDER BY m.match_date"
)->fetchAll();
$sections = $pdo->query("SELECT id, name FROM sections ORDER BY id")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $match_id   = (int)($_POST['match_id'] ?? 0);
        $section_id = (int)($_POST['section_id'] ?? 0);
        $price      = $_POST['price'] ?? '';

        if ($match_id === 0 || $section_id === 0) {
            $error = 'Please choose a match and a section.';
        } elseif (!is_numeric($price) || (float)$price < 0) {
            $error = 'Please enter a valid price.';
        } else {
            // UNIQUE (match_id, section_id): insert, or update if it already exists
            $stmt = $pdo->prepare(
                "INSERT INTO prices (match_id, section_id, price)
                 VALUES (:m, :s, :p)
                 ON DUPLICATE KEY UPDATE price = VALUES(price)"
            );
            $stmt->execute([':m' => $match_id, ':s' => $section_id, ':p' => (float)$price]);
            header('Location: prices.php?msg=saved');
            exit;
        }

    } elseif ($action === 'delete') {
        $stmt = $pdo->prepare("DELETE FROM prices WHERE id = :id");
        $stmt->execute([':id' => (int)($_POST['id'] ?? 0)]);
        header('Location: prices.php?msg=deleted');
        exit;
    }
}

if (($_GET['msg'] ?? '') === 'saved')   { $success = 'Price saved.'; }
if (($_GET['msg'] ?? '') === 'deleted') { $success = 'Price removed.'; }

$prices = $pdo->query(
    "SELECT p.id, p.price, sec.name AS section_name, m.match_date,
            th.name AS home_team, ta.name AS away_team
     FROM prices p
     JOIN matches m   ON m.id   = p.match_id
     JOIN sections sec ON sec.id = p.section_id
     JOIN teams th    ON th.id  = m.home_team_id
     JOIN teams ta    ON ta.id  = m.away_team_id
     ORDER BY m.match_date, sec.id"
)->fetchAll();

$page_title = 'Prices';
require_once __DIR__ . '/includes/header.php';
?>

<h1 class="page-title">Ticket Prices</h1>

<?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
<?php if ($error):   ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<div class="card">
  <h2>Set a price</h2>
  <p style="color:#64748b; margin-bottom:1rem;">If a price already exists for that match and section, it is updated.</p>
  <form method="post" action="prices.php">
    <input type="hidden" name="action" value="save">
    <div class="form-row">
      <div class="form-group">
        <label for="match_id">Match</label>
        <select id="match_id" name="match_id" class="form-control" required>
          <option value="">Choose...</option>
          <?php foreach ($matches as $m): ?>
            <option value="<?= (int)$m['id'] ?>">
              <?= htmlspecialchars($m['home_team'] . ' vs ' . $m['away_team'] . ' (' . $m['match_date'] . ')') ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label for="section_id">Section</label>
        <select id="section_id" name="section_id" class="form-control" required>
          <option value="">Choose...</option>
          <?php foreach ($sections as $s): ?>
            <option value="<?= (int)$s['id'] ?>"><?= htmlspecialchars($s['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label for="price">Price</label>
        <input type="number" id="price" name="price" class="form-control" min="0" step="0.01" required>
      </div>
    </div>
    <button type="submit" class="btn">Save price</button>
  </form>
</div>

<div class="card">
  <h2>Current prices</h2>
  <?php if (count($prices) === 0): ?>
    <p>No prices yet.</p>
  <?php else: ?>
  <div class="table-wrap">
    <table class="data">
      <tr><th>Match</th><th>Date</th><th>Section</th><th>Price</th><th></th></tr>
      <?php foreach ($prices as $p): ?>
        <tr>
          <td><?= htmlspecialchars($p['home_team']) ?> vs <?= htmlspecialchars($p['away_team']) ?></td>
          <td><?= htmlspecialchars($p['match_date']) ?></td>
          <td><?= htmlspecialchars($p['section_name']) ?></td>
          <td>$<?= number_format((float)$p['price'], 2) ?></td>
          <td>
            <form method="post" action="prices.php" class="inline-form"
                  onsubmit="return confirm('Remove this price?');">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
              <button type="submit" class="btn btn-small btn-danger">Remove</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    </table>
  </div>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
