<?php
/* Fan: view and print one ticket with its QR code - Malak
 * URL: ticket.php?id=<ticket id>
 *
 * The QR content is the ticket's qr_code value (the same value staff types/scans in verify.php).
 * QR generation:
 *   1. If the phpqrcode library is installed at /lib/phpqrcode/qrlib.php, the QR image is made on the server.
 *   2. Otherwise a small JavaScript library (loaded from cdnjs) draws it in the browser.
 */
require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/config/db.php';

if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
$user_id   = (int)$_SESSION['user_id'];
$ticket_id = (int)($_GET['id'] ?? 0);

// Join through bookings so a fan can only open his own tickets
$stmt = $pdo->prepare(
    "SELECT t.id, t.qr_code, t.status,
            se.row_label, se.seat_number, sec.name AS section_name,
            m.match_date, m.match_time,
            th.name AS home_team, ta.name AS away_team,
            p.price, u.name AS fan_name
     FROM tickets t
     JOIN bookings b   ON b.id  = t.booking_id
     JOIN users u      ON u.id  = b.user_id
     JOIN matches m    ON m.id  = t.match_id
     JOIN teams th     ON th.id = m.home_team_id
     JOIN teams ta     ON ta.id = m.away_team_id
     JOIN seats se     ON se.id = t.seat_id
     JOIN sections sec ON sec.id = se.section_id
     LEFT JOIN prices p ON p.match_id = t.match_id AND p.section_id = sec.id
     WHERE t.id = :t AND b.user_id = :u"
);
$stmt->execute([':t' => $ticket_id, ':u' => $user_id]);
$ticket = $stmt->fetch();

$qr_image = null;   // data URI when phpqrcode is available
if ($ticket && $ticket['status'] !== 'cancelled') {
    $lib1 = __DIR__ . '/lib/phpqrcode/qrlib.php';
    $lib2 = __DIR__ . '/../lib/phpqrcode/qrlib.php';
    $lib = file_exists($lib1) ? $lib1 : (file_exists($lib2) ? $lib2 : null);
    if ($lib) {
        require_once $lib;
        ob_start();
        QRcode::png($ticket['qr_code'], false, QR_ECLEVEL_L, 6, 2);
        $qr_image = 'data:image/png;base64,' . base64_encode(ob_get_clean());
    }
}

$page_title = 'My Ticket';
require_once __DIR__ . '/includes/header.php';
?>

<?php if (!$ticket): ?>
  <div class="card">
    <p>Ticket not found. <a href="my_bookings.php">Back to my bookings</a></p>
  </div>

<?php else: ?>
  <div class="no-print" style="max-width:640px; margin:0 auto 1rem; display:flex; gap:.5rem; flex-wrap:wrap;">
    <a href="my_bookings.php" class="btn btn-secondary">&larr; My bookings</a>
    <?php if ($ticket['status'] !== 'cancelled'): ?>
      <button type="button" class="btn" onclick="window.print()">Print ticket</button>
    <?php endif; ?>
  </div>

  <div class="ticket">
    <div class="ticket-head">
      <div class="brand">STADIUM<span>PASS</span></div>
      <div>Ticket #<?= (int)$ticket['id'] ?></div>
    </div>

    <?php if ($ticket['status'] === 'cancelled'): ?>
      <div class="ticket-void">This ticket was cancelled and is no longer valid.</div>
    <?php elseif ($ticket['status'] === 'used'): ?>
      <div class="ticket-void" style="background:#fff3cd; color:#856404;">This ticket has already been used.</div>
    <?php endif; ?>

    <div class="ticket-teams">
      <?= htmlspecialchars($ticket['home_team']) ?>
      <span class="vs">VS</span>
      <?= htmlspecialchars($ticket['away_team']) ?>
    </div>

    <div class="ticket-body">
      <dl class="ticket-info">
        <dt>Date</dt><dd><?= htmlspecialchars($ticket['match_date']) ?></dd>
        <dt>Time</dt><dd><?= htmlspecialchars(substr($ticket['match_time'], 0, 5)) ?></dd>
        <dt>Section</dt><dd><?= htmlspecialchars($ticket['section_name']) ?></dd>
        <dt>Row / Seat</dt><dd>Row <?= htmlspecialchars($ticket['row_label']) ?>, Seat <?= (int)$ticket['seat_number'] ?></dd>
        <dt>Ticket number</dt><dd>#<?= (int)$ticket['id'] ?></dd>
        <dt>Holder</dt><dd><?= htmlspecialchars($ticket['fan_name']) ?></dd>
        <?php if ($ticket['price'] !== null): ?>
          <dt>Price</dt><dd>$<?= number_format((float)$ticket['price'], 2) ?></dd>
        <?php endif; ?>
      </dl>

      <?php if ($ticket['status'] !== 'cancelled'): ?>
      <div class="ticket-qr">
        <?php if ($qr_image): ?>
          <img src="<?= $qr_image ?>" alt="QR code" width="180" height="180">
        <?php else: ?>
          <div id="qrbox"></div>
        <?php endif; ?>
        <small><?= htmlspecialchars($ticket['qr_code']) ?></small>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <?php if ($ticket['status'] !== 'cancelled' && !$qr_image): ?>
    <!-- Fallback: draw the QR in the browser -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <script>
      new QRCode(document.getElementById('qrbox'), {
        text: <?= json_encode($ticket['qr_code']) ?>,
        width: 180,
        height: 180
      });
    </script>
  <?php endif; ?>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
