<?php
/* Admin: manage sections (and generate their seats) - Rahaf */
require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/config/db.php';
require_role('admin');

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $name     = trim($_POST['name'] ?? '');
        $capacity = (int)($_POST['capacity'] ?? 0);

        if ($name === '') {
            $error = 'Please enter the section name.';
        } elseif ($capacity < 1 || $capacity > 260) {
            $error = 'Capacity must be between 1 and 260.';
        } else {
            try {
                $pdo->beginTransaction();
                $stmt = $pdo->prepare("INSERT INTO sections (name, capacity) VALUES (:n, :c)");
                $stmt->execute([':n' => $name, ':c' => $capacity]);
                $section_id = (int)$pdo->lastInsertId();

                // Create one seat row per capacity: rows A, B, C... with 10 seats each
                $seat = $pdo->prepare(
                    "INSERT INTO seats (section_id, row_label, seat_number) VALUES (:s, :r, :n)"
                );
                for ($i = 0; $i < $capacity; $i++) {
                    $seat->execute([
                        ':s' => $section_id,
                        ':r' => chr(65 + intdiv($i, 10)),
                        ':n' => ($i % 10) + 1,
                    ]);
                }
                $pdo->commit();
                header('Location: sections.php?msg=added');
                exit;
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) { $pdo->rollBack(); }
                $error = 'Could not add the section.';
            }
        }

    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        try {
            $stmt = $pdo->prepare("DELETE FROM sections WHERE id = :id");
            $stmt->execute([':id' => $id]);
            header('Location: sections.php?msg=deleted');
            exit;
        } catch (PDOException $e) {
            $error = 'This section has sold tickets or entries, so it cannot be deleted.';
        }
    }
}

if (($_GET['msg'] ?? '') === 'added')   { $success = 'Section added with its seats.'; }
if (($_GET['msg'] ?? '') === 'deleted') { $success = 'Section deleted.'; }

$sections = $pdo->query(
    "SELECT sec.id, sec.name, sec.capacity, COUNT(se.id) AS seat_count
     FROM sections sec
     LEFT JOIN seats se ON se.section_id = sec.id
     GROUP BY sec.id, sec.name, sec.capacity
     ORDER BY sec.id"
)->fetchAll();

$page_title = 'Sections';
require_once __DIR__ . '/includes/header.php';
?>

<h1 class="page-title">Sections</h1>

<?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
<?php if ($error):   ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<div class="card">
  <h2>Add a section</h2>
  <form method="post" action="sections.php">
    <input type="hidden" name="action" value="add">
    <div class="form-row">
      <div class="form-group">
        <label for="name">Section name</label>
        <input type="text" id="name" name="name" class="form-control" maxlength="100"
               placeholder="e.g. East Stand" required>
      </div>
      <div class="form-group">
        <label for="capacity">Capacity (seats)</label>
        <input type="number" id="capacity" name="capacity" class="form-control"
               min="1" max="260" value="20" required>
      </div>
    </div>
    <p style="color:#64748b; margin-bottom:1rem;">
      Seats are created automatically: rows A, B, C... with 10 seats per row.
    </p>
    <button type="submit" class="btn">Add section</button>
  </form>
</div>

<div class="card">
  <h2>All sections</h2>
  <?php if (count($sections) === 0): ?>
    <p>No sections yet.</p>
  <?php else: ?>
  <div class="table-wrap">
    <table class="data">
      <tr><th>#</th><th>Name</th><th>Capacity</th><th>Seats created</th><th></th></tr>
      <?php foreach ($sections as $s): ?>
        <tr>
          <td><?= (int)$s['id'] ?></td>
          <td><?= htmlspecialchars($s['name']) ?></td>
          <td><?= (int)$s['capacity'] ?></td>
          <td><?= (int)$s['seat_count'] ?></td>
          <td>
            <form method="post" action="sections.php" class="inline-form"
                  onsubmit="return confirm('Delete this section and its seats?');">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
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
