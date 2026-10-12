<?php
require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/config/db.php';

$action = $_GET['action'] ?? 'matches';
$error = null;

// Handle Checkout / Payment submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pay_now'])) {
    if (empty($_SESSION['user_id'])) {
        header("Location: login.php");
        exit();
    }

    $match_id = $_POST['match_id'] ?? null;
    $seat_ids = !empty($_POST['seat_ids']) ? explode(',', $_POST['seat_ids']) : [];
    $user_id = (int)$_SESSION['user_id'];
    $total_price = $_POST['total_price'] ?? 0;

    if ($pdo && $match_id && !empty($seat_ids)) {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("INSERT INTO bookings (user_id, match_id, total, status, created_at) VALUES (?, ?, ?, 'confirmed', NOW())");
            $stmt->execute([$user_id, $match_id, $total_price]);
            $booking_id = $pdo->lastInsertId();

            $stmtTicket = $pdo->prepare("INSERT INTO tickets (booking_id, match_id, seat_id, qr_code, status) VALUES (?, ?, ?, ?, 'valid')");

            foreach ($seat_ids as $seat_id) {
                $qr_code = "TICK-" . $match_id . "-" . $seat_id . "-" . rand(10000, 99999);
                $stmtTicket->execute([$booking_id, $match_id, $seat_id, $qr_code]);
            }

            $pdo->commit();
            header("Location: booking.php?action=success&booking_id=" . $booking_id);
            exit();

        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if ($e->getCode() == '23000') {
                $error = "This seat has just been booked by another user. Please choose another seat.";
            } else {
                $error = "Booking failed: " . $e->getMessage();
            }
            $action = 'checkout';
        }
    }
}

$page_title = match($action) {
    'seats' => 'Select Seats',
    'checkout' => 'Checkout',
    'success' => 'Booking Confirmed',
    default => 'Upcoming Fixtures',
};

require_once __DIR__ . '/includes/header.php';
?>

<?php
// BROWSE MATCHES
if ($action === 'matches'):
    $matches = [];
    if ($pdo) {
        $query = "
          SELECT m.id AS match_id, m.match_date, m.match_time, t1.name AS home_team, t2.name AS away_team
          FROM matches m
          JOIN teams t1 ON m.home_team_id = t1.id
          JOIN teams t2 ON m.away_team_id = t2.id
          WHERE m.status = 'upcoming'
          ORDER BY m.match_date ASC
        ";
        $stmt = $pdo->prepare($query);
        $stmt->execute();
        $matches = $stmt->fetchAll();
    }
?>

  <h1 class="page-title">Upcoming Fixtures</h1>
  <div class="matches-grid">
    <?php if (count($matches) > 0): ?>
      <?php foreach ($matches as $match): ?>
        <div class="match-card">
          <div>
            <div class="teams-vs">
              <span><?= htmlspecialchars($match['home_team']); ?></span>
              <span class="vs-badge">VS</span>
              <span><?= htmlspecialchars($match['away_team']); ?></span>
            </div>
            <div class="match-info">
              <p><strong>Date:</strong> <?= htmlspecialchars($match['match_date']); ?></p>
              <p><strong>Time:</strong> <?= htmlspecialchars($match['match_time']); ?></p>
            </div>
          </div>
          <a href="booking.php?action=seats&match_id=<?= $match['match_id']; ?>" class="btn-primary">
            Select Seats
          </a>
        </div>
      <?php endforeach; ?>
    <?php else: ?>
      <p>No upcoming matches scheduled right now.</p>
    <?php endif; ?>
  </div>

<?php
// SEAT SELECTION
elseif ($action === 'seats'):
    $match_id = $_GET['match_id'] ?? null;
    if (!$match_id) { header("Location: booking.php?action=matches"); exit(); }

    $seat_rows = [];
    if ($pdo) {
        $query = "
          SELECT s.id AS seat_id, s.row_label, s.seat_number, sec.id AS section_id, sec.name AS section_name, p.price,
                 IF(t.id IS NOT NULL AND t.status != 'cancelled', 1, 0) AS is_booked
          FROM seats s
          JOIN sections sec ON s.section_id = sec.id
          JOIN prices p ON p.section_id = sec.id AND p.match_id = :match_id
          LEFT JOIN tickets t ON t.seat_id = s.id AND t.match_id = :match_id
          ORDER BY sec.id, s.row_label ASC, s.seat_number ASC
        ";
        $stmt = $pdo->prepare($query);
        $stmt->execute(['match_id' => $match_id]);
        $seat_rows = $stmt->fetchAll();
    }

    $sections = [];
    foreach ($seat_rows as $row) {
        $sections[$row['section_name']]['price'] = $row['price'];
        $sections[$row['section_name']]['seats'][] = $row;
    }
?>

  <h1 class="page-title">Select Seats</h1>
  <form action="booking.php?action=checkout" method="POST" id="seatForm">
    <input type="hidden" name="match_id" value="<?= htmlspecialchars($match_id); ?>">
    <input type="hidden" name="selected_seats" id="selectedSeatsInput">

    <div class="stadium-container">
      <?php if (empty($sections)): ?>
        <div class="card">
          <p>No pricing or seats have been configured for this match yet. Please check back later.</p>
          <a href="booking.php?action=matches" class="btn" style="margin-top:1rem;">Back to matches</a>
        </div>
      <?php else: ?>
        <?php foreach ($sections as $sec_name => $sec_data): ?>
          <div class="section-block">
            <div class="section-header">
              <span><?= htmlspecialchars($sec_name); ?></span>
              <span>$<?= number_format($sec_data['price'], 2); ?> / seat</span>
            </div>
            <div class="seats-grid">
              <?php foreach ($sec_data['seats'] as $seat): ?>
                <?php $seat_label = htmlspecialchars($seat['row_label'] . $seat['seat_number']); ?>
                <?php if ($seat['is_booked']): ?>
                  <button type="button" class="seat-btn booked" disabled title="Already booked"><?= $seat_label; ?></button>
                <?php else: ?>
                  <button type="button" class="seat-btn available" data-id="<?= $seat['seat_id']; ?>" data-price="<?= $seat['price']; ?>" onclick="toggleSeat(this)">
                    <?= $seat_label; ?>
                  </button>
                <?php endif; ?>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <?php if (!empty($sections)): ?>
      <div class="booking-bar">
        <div>Selected Seats: <strong id="selectedCount">0</strong> | Total: <strong id="totalPrice">$0.00</strong></div>
        <button type="submit" class="btn-primary" style="width: auto;">Proceed to Checkout</button>
      </div>
    <?php endif; ?>
  </form>

  <script>
    let selectedSeats = [];
    let totalPrice = 0;

    function toggleSeat(btn) {
      const seatId = btn.getAttribute('data-id');
      const price = parseFloat(btn.getAttribute('data-price'));

      if (btn.classList.contains('selected')) {
        btn.classList.remove('selected');
        selectedSeats = selectedSeats.filter(id => id !== seatId);
        totalPrice -= price;
      } else {
        btn.classList.add('selected');
        selectedSeats.push(seatId);
        totalPrice += price;
      }

      document.getElementById('selectedCount').innerText = selectedSeats.length;
      document.getElementById('totalPrice').innerText = '$' + totalPrice.toFixed(2);
      document.getElementById('selectedSeatsInput').value = selectedSeats.join(',');
    }
  </script>

<?php
// BOOKING CHECKOUT
elseif ($action === 'checkout'):
    $match_id = $_POST['match_id'] ?? null;
    $selected_seats = $_POST['selected_seats'] ?? '';

    if (!$selected_seats) {
        echo "<div class='checkout-card'><p>No seats selected. <a href='booking.php?action=matches'>Return to matches</a></p></div>";
    } else {
        $seat_array = explode(',', $selected_seats);
        $placeholders = implode(',', array_fill(0, count($seat_array), '?'));

        $total = 0;
        if ($pdo) {
            $stmt = $pdo->prepare("SELECT SUM(p.price) as total FROM seats s JOIN prices p ON s.section_id = p.section_id WHERE s.id IN ($placeholders) AND p.match_id = ?");
            $params = array_merge($seat_array, [$match_id]);
            $stmt->execute($params);
            $total = $stmt->fetch()['total'] ?? 0;
        }
?>

    <div class="checkout-card">
      <h2 class="page-title" style="margin-bottom: 1rem;">Payment Checkout</h2>

      <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error); ?></div>
      <?php endif; ?>

      <?php if (empty($_SESSION['user_id'])): ?>
        <div class="alert alert-info">
          You must be logged in to confirm your booking. <a href="login.php" style="color: var(--navy); font-weight: 700;">Login here</a> or <a href="register.php" style="color: var(--navy); font-weight: 700;">Sign Up</a>.
        </div>
      <?php endif; ?>

      <div style="margin-bottom: 1.5rem; border-bottom: 1px solid #e2e8f0; padding-bottom: 1rem;">
        <p><strong>Seats Count:</strong> <?= count($seat_array); ?></p>
        <p style="font-size: 1.25rem; font-weight: 700; color: var(--green); margin-top: 0.5rem;">
          Total Amount: $<?= number_format($total, 2); ?>
        </p>
      </div>

      <form action="booking.php?action=checkout" method="POST">
        <input type="hidden" name="match_id" value="<?= htmlspecialchars($match_id); ?>">
        <input type="hidden" name="seat_ids" value="<?= htmlspecialchars($selected_seats); ?>">
        <input type="hidden" name="total_price" value="<?= htmlspecialchars($total); ?>">

        <div class="form-group">
          <label>Name on Card</label>
          <input type="text" class="form-control" placeholder="Cardholder Name" required>
        </div>

        <div class="form-group">
          <label>Card Number (Simulated)</label>
          <input type="text" class="form-control" placeholder="4532 •••• •••• 8899" required>
        </div>

        <div style="display: flex; gap: 1rem;">
          <div class="form-group" style="flex: 1;">
            <label>Expiry Date</label>
            <input type="text" class="form-control" placeholder="MM/YY" required>
          </div>
          <div class="form-group" style="flex: 1;">
            <label>CVV</label>
            <input type="text" class="form-control" placeholder="123" required>
          </div>
        </div>

        <?php if (empty($_SESSION['user_id'])): ?>
          <a href="login.php" class="btn-primary" style="display:inline-block; text-decoration:none;">Login to Pay</a>
        <?php else: ?>
          <button type="submit" name="pay_now" class="btn-primary">Confirm & Pay</button>
        <?php endif; ?>
      </form>
    </div>

<?php 
    }
elseif ($action === 'success'):
?>
  <div class="checkout-card" style="text-align: center;">
    <h2 class="page-title" style="color: var(--green);">Booking Confirmed!</h2>
    <p style="margin-bottom: 1.5rem; color: #64748b;">Your seats have been booked successfully<?php if (!empty($_GET['booking_id'])): ?> (Booking #<?= (int)$_GET['booking_id'] ?>)<?php endif; ?>.</p>
    <div style="display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap;">
      <a href="my_bookings.php" class="btn">View My Bookings & Tickets</a>
      <a href="booking.php?action=matches" class="btn btn-secondary">Book More Matches</a>
    </div>
  </div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>