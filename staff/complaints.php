<?php
require_once __DIR__ . '/../auth_check.php';
require_once __DIR__ . '/../config/db.php';
require_role('staff', 'admin');

$staff_id = (int)$_SESSION['user_id'];

$error   = "";
$success = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $description = trim($_POST['description'] ?? '');

        if ($description === '') {
            $error = "Please write the complaint before saving.";
        } else {
            $stmt = $pdo->prepare("INSERT INTO complaints (staff_id, description)
                                   VALUES (:staff, :desc)");
            $stmt->execute([':staff' => $staff_id, ':desc' => $description]);
            header("Location: complaints.php?saved=1");
            exit;
        }

    } elseif ($action === 'resolve') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $pdo->prepare("UPDATE complaints SET status = 'resolved' WHERE id = :id");
        $stmt->execute([':id' => $id]);

        header("Location: complaints.php?resolved=1");
        exit;
    }
}

if (isset($_GET['saved']))    { $success = "Complaint recorded successfully."; }
if (isset($_GET['resolved'])) { $success = "Complaint marked as resolved."; }

$stmt = $pdo->query(
    "SELECT c.id, c.description, c.created_at, u.name AS staff_name
     FROM complaints c
     JOIN users u ON u.id = c.staff_id
     WHERE c.status = 'open'
     ORDER BY c.created_at DESC"
);
$complaints = $stmt->fetchAll();

$page_title = 'Staff Complaints';
require_once __DIR__ . '/../includes/header.php';
?>

<h1 class="page-title">Staff Complaints</h1>

<?php if ($success !== ""): ?>
    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>
<?php if ($error !== ""): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<!-- Form: add a new complaint -->
<div class="card">
    <h2>Record New Complaint</h2>
    <form method="post" action="complaints.php">
        <input type="hidden" name="action" value="add">
        <div class="form-group">
            <label for="description">Incident / Complaint Details</label>
            <textarea id="description" name="description" class="form-control"
                      placeholder="Describe what happened at the gate or stand" rows="3" required></textarea>
        </div>
        <button type="submit" class="btn">Submit Complaint</button>
    </form>
</div>

<!-- Table: open complaints -->
<div class="card">
    <h2>Open Complaints</h2>

    <?php if (count($complaints) === 0): ?>
        <p style="color: #64748b;">There are no open complaints right now.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data">
                <tr>
                    <th>#</th>
                    <th>Description</th>
                    <th>Reported By</th>
                    <th>Date / Time</th>
                    <th>Action</th>
                </tr>
                <?php foreach ($complaints as $c): ?>
                    <tr>
                        <td><?= (int)$c['id'] ?></td>
                        <td><?= nl2br(htmlspecialchars($c['description'])) ?></td>
                        <td><?= htmlspecialchars($c['staff_name']) ?></td>
                        <td><?= htmlspecialchars($c['created_at']) ?></td>
                        <td>
                            <form method="post" action="complaints.php" class="inline-form">
                                <input type="hidden" name="action" value="resolve">
                                <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                                <button type="submit" class="btn btn-small" style="background:#15803d;">Resolve</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
