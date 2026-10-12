<?php
/* Fan: my bookings + cancellation - Malak
 * A booking can be cancelled only if:
 *   - it belongs to the logged-in user,
 *   - it is still "confirmed",
 *   - the match is upcoming and has not started yet,
 *   - no ticket of it has been used at the gate.
 * Cancelling sets the booking and all its tickets to "cancelled".
 * Editing a booking = cancel it and book again.
 */
require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/config/db.php';

if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
$user_id = (int)$_SESSION['user_id'];

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'cancel') {
    $booking_id = (int)($_POST['booking_id'] ?? 0);

    try {
        $pdo->beginTransaction();

        // Lock the booking row; the user_id check stops people cancelling others' bookings
        $stmt = $pdo->prepare(
            "SELECT b.id, b.status, m.match_date, m.match_time, m.status AS match_status
             FROM bookings b
             JOIN matches m ON m.id = b.match_id
             WHERE b.id = :id AND b.user_id = :u
             FOR UPDATE"
        );
        $stmt->execute([':id' => $booking_id, ':u' => $user_id]);
        $b = $stmt->fetch();

        if (!$b) {
            $error = 'Booking not found.';
        } elseif ($b['status'] !== 'confirmed') {
            $error = 'This booking is already cancelled.';
        } elseif ($b['match_status'] !== 'upcoming'
                  || strtotime($b['match_date'] . ' ' . $b['match_time']) <= time()) {
            $error = 'You cannot cancel a booking for a match that has already started or finished.';
        } else {
            $used = $pdo->prepare(
                "SELECT COUNT(*) FROM tickets WHERE booking_id = :id AND status = 'used'"
            );
            $used->execute([':id' => $booking_id]);

            if ((int)$used->fetchColumn() > 0) {
                $error = 'A ticket from this booking has already been used.';
            } else {
                $pdo->prepare("UPDATE bookings SET status = 'cancelled' WHERE id = :id")
                    ->execute([':id' => $booking_id]);
                $pdo->prepare("UPDATE tickets SET status = 'cancelled' WHERE booking_id = :id")
                    ->execute([':id' => $booking_id]);
                $success = 'Booking #' . $booking_id . ' was cancelled. Your seats are available again.';
            }
        }

        if ($error !== '') { $pdo->rollBack(); } else { $pdo->commit(); }
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        $error = 'Could not cancel the booking. Please try again.';
    }
}

// The user's bookings (newest first)
$stmt = $pdo->prepare(
    "SELECT b.id, b.total, b.status, b.created_at,
            m.match_date, m.match_time, m.status AS match_status,
            th.name AS home_team, ta.name AS away_team
     FROM bookings b
     JOIN matches m ON m.id = b.match_id
     JOIN teams th  ON th.id = m.home_team_id
     JOIN teams ta  ON ta.id = m.away_team_id
     WHERE b.user_id = :u
     ORDER BY b.created_at DESC, b.id DESC"
);
$stmt->execute([':u' => $user_id]);
$bookings = $stmt->fetchAll();

// Tickets of those bookings, grouped by booking id
$tickets = [];
if ($bookings) {
    $ids = array_column($bookings, 'id');
    $in  = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare(
        "SELECT t.id, t.booking_id, t.status, se.row_label, se.seat_number, sec.name AS section_name
         FROM tickets t
         JOIN seats se     ON se.id  = t.seat_id
         JOIN sections sec ON sec.id = se.section_id
         WHERE t.booking_id IN ($in)
         ORDER BY sec.id, se.row_label, se.seat_number"
    );
    $stmt->execute($ids);
    foreach ($stmt->fetchAll() as $t) {
        $tickets[$t['booking_id']][] = $t;
    }
}

$page_title = 'My Bookings';
require_once __DIR__ . '/includes/header.php';
?>

    <h1 class="page-title">My Bookings</h1>

    <?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
    <?php if ($error):   ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <?php if (count($bookings) === 0): ?>
      <div class="card">
        <p>You have no bookings yet. <a href="booking.php?action=matches">Browse matches</a> to book your first seat.</p>
      </div>
    <?php endif; ?>

    <?php foreach ($bookings as $b):
        $starts_at  = strtotime($b['match_date'] . ' ' . $b['match_time']);
        $can_cancel = $b['status'] === 'confirmed'
                   && $b['match_status'] === 'upcoming'
                   && $starts_at > time();
    ?>
      <div class="card">
        <div style="display:flex; justify-content:space-between; flex-wrap:wrap; gap:.5rem; margin-bottom:.75rem;">
          <div>
            <h2 style="margin-bottom:.25rem;">
              <?= htmlspecialchars($b['home_team']) ?> vs <?= htmlspecialchars($b['away_team']) ?>
            </h2>
            <span style="color:#64748b;">
              <?= htmlspecialchars($b['match_date']) ?> at <?= htmlspecialchars(substr($b['match_time'], 0, 5)) ?>
              &middot; Booking #<?= (int)$b['id'] ?>
              &middot; Total $<?= number_format((float)$b['total'], 2) ?>
            </span>
          </div>
          <div><span class="badge badge-<?= htmlspecialchars($b['status']) ?>"><?= htmlspecialchars($b['status']) ?></span></div>
        </div>

        <div class="table-wrap">
          <table class="data">
            <tr><th>Ticket</th><th>Section</th><th>Row</th><th>Seat</th><th>Status</th><th></th></tr>
            <?php foreach ($tickets[$b['id']] ?? [] as $t): ?>
              <tr>
                <td>#<?= (int)$t['id'] ?></td>
                <td><?= htmlspecialchars($t['section_name']) ?></td>
                <td><?= htmlspecialchars($t['row_label']) ?></td>
                <td><?= (int)$t['seat_number'] ?></td>
                <td><span class="badge badge-<?= htmlspecialchars($t['status']) ?>"><?= htmlspecialchars($t['status']) ?></span></td>
                <td>
                  <?php if ($t['status'] !== 'cancelled'): ?>
                    <a href="ticket.php?id=<?= (int)$t['id'] ?>" class="btn btn-small">View ticket</a>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </table>
        </div>

        <?php if ($can_cancel): ?>
          <form method="post" action="my_bookings.php" style="margin-top:1rem;"
                onsubmit="return confirm('Cancel this whole booking? This cannot be undone.');">
            <input type="hidden" name="action" value="cancel">
            <input type="hidden" name="booking_id" value="<?= (int)$b['id'] ?>">
            <button type="submit" class="btn btn-danger">Cancel booking</button>
            <span style="color:#64748b; margin-left:.5rem;">To change seats, cancel and book again.</span>
          </form>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
