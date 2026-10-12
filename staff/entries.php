<?php
require_once __DIR__ . '/../auth_check.php';
require_once __DIR__ . '/../config/db.php';
require_role('staff', 'admin');

$stmt = $pdo->query(
    "SELECT sec.id, sec.name, sec.capacity, COUNT(e.id) AS entered
     FROM sections sec
     LEFT JOIN entries e ON e.section_id = sec.id
     GROUP BY sec.id, sec.name, sec.capacity
     ORDER BY sec.id"
);
$sections = $stmt->fetchAll();

$page_title = 'Section Entries';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="card">
    <h1 class="page-title" style="margin-bottom: 1rem;">Entries per Section</h1>

    <?php if (count($sections) === 0): ?>
        <p>No sections found. Ask an administrator to configure sections first.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data">
                <tr>
                    <th>Section</th>
                    <th>Entered</th>
                    <th>Capacity</th>
                    <th>Remaining</th>
                </tr>
                <?php foreach ($sections as $row): ?>
                    <?php $remaining = $row['capacity'] - $row['entered']; ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($row['name']) ?></strong></td>
                        <td><?= (int)$row['entered'] ?></td>
                        <td><?= (int)$row['capacity'] ?></td>
                        <td>
                            <?php if ($remaining <= 0): ?>
                                <span class="badge badge-cancelled">Full (0)</span>
                            <?php else: ?>
                                <span class="badge badge-valid"><?= $remaining ?> seats left</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
