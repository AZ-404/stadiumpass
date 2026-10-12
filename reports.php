<?php
/* Admin: sales report per match - Rahaf
 * sold      = tickets that are not cancelled (valid + used)
 * revenue   = sum of the section price of every sold ticket
 * occupancy = sold / total stadium capacity x 100
 */
require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/config/db.php';
require_role('admin');

// Total seats in the stadium (sum of all section capacities)
$capacity = (int)$pdo->query("SELECT COALESCE(SUM(capacity), 0) FROM sections")->fetchColumn();

$rows = $pdo->query(
    "SELECT m.id, m.match_date, m.status,
            th.name AS home_team, ta.name AS away_team,
            (SELECT COUNT(*)
               FROM tickets t
              WHERE t.match_id = m.id AND t.status <> 'cancelled') AS sold,
            (SELECT COALESCE(SUM(p.price), 0)
               FROM tickets t
               JOIN seats se ON se.id = t.seat_id
               JOIN prices p ON p.section_id = se.section_id AND p.match_id = t.match_id
              WHERE t.match_id = m.id AND t.status <> 'cancelled') AS revenue
     FROM matches m
     JOIN teams th ON th.id = m.home_team_id
     JOIN teams ta ON ta.id = m.away_team_id
     ORDER BY m.match_date"
)->fetchAll();

$total_sold = 0;
$total_rev  = 0.0;
foreach ($rows as $r) {
    $total_sold += (int)$r['sold'];
    $total_rev  += (float)$r['revenue'];
}

$page_title = 'Reports';
require_once __DIR__ . '/includes/header.php';
?>

<h1 class="page-title">Sales Reports</h1>

<div class="card">
  <h2>Tickets sold, revenue and occupancy per match</h2>
  <p style="color:#64748b; margin-bottom:1rem;">
    Stadium capacity: <strong><?= $capacity ?></strong> seats. Cancelled tickets are not counted.
  </p>

  <?php if (count($rows) === 0): ?>
    <p>No matches yet.</p>
  <?php else: ?>
  <div class="table-wrap">
    <table class="data">
      <tr>
        <th>Match</th><th>Date</th><th>Status</th>
        <th>Tickets sold</th><th>Revenue</th><th>Occupancy</th>
      </tr>
      <?php foreach ($rows as $r):
          $occ = $capacity > 0 ? round($r['sold'] / $capacity * 100, 1) : 0;
      ?>
        <tr>
          <td><?= htmlspecialchars($r['home_team']) ?> vs <?= htmlspecialchars($r['away_team']) ?></td>
          <td><?= htmlspecialchars($r['match_date']) ?></td>
          <td><span class="badge badge-<?= htmlspecialchars($r['status']) ?>"><?= htmlspecialchars($r['status']) ?></span></td>
          <td><?= (int)$r['sold'] ?></td>
          <td>$<?= number_format((float)$r['revenue'], 2) ?></td>
          <td>
            <span class="bar"><span style="width: <?= min(100, $occ) ?>%"></span></span>
            <?= $occ ?>%
          </td>
        </tr>
      <?php endforeach; ?>
      <tr class="total">
        <td colspan="3">Total</td>
        <td><?= $total_sold ?></td>
        <td>$<?= number_format($total_rev, 2) ?></td>
        <td></td>
      </tr>
    </table>
  </div>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
