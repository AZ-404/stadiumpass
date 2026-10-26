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

if ($action === 'matches') {
    $body_class = 'home';
    // Optional cinematic intro: only rendered when the video file has been uploaded.
    $intro_video  = 'assets/videos/football-intro.mp4';
    $intro_poster = 'assets/videos/football-intro-poster.jpg';
    $show_video   = is_file(__DIR__ . '/' . $intro_video);   // scroll-scrubbed video inside the hero
    $intro_overlay = false;                                   // set true to also show the skippable full-screen intro
    $show_intro   = $show_video && $intro_overlay;
    $has_poster   = is_file(__DIR__ . '/' . $intro_poster);
}

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

<?php
  // Display helpers only (no data access): crest initials + a stable hue per team name.
  $crest_initials = function (string $name): string {
      $words = preg_split('/\s+/u', trim($name), -1, PREG_SPLIT_NO_EMPTY) ?: [];
      $i = count($words) > 1
          ? mb_substr($words[0], 0, 1) . mb_substr($words[count($words) - 1], 0, 1)
          : mb_substr($name, 0, 3);
      return mb_strtoupper($i);
  };
  $crest_hue = fn (string $name): int => crc32($name) % 360;
?>

<?php if (!empty($show_intro)): ?>
  <script>(function(){var r=document.documentElement;try{if(!matchMedia('(prefers-reduced-motion: reduce)').matches&&!sessionStorage.getItem('sp_intro_seen'))r.classList.add('has-intro');}catch(e){r.classList.add('has-intro');}})();</script>
  <!-- INTRO VIDEO: shown once per browser session, skippable -->
  <div class="intro" id="intro" role="dialog" aria-modal="true" aria-label="Stadium Pass intro" data-src="<?= htmlspecialchars($intro_video); ?>">
    <div class="intro__bg"<?= $has_poster ? ' style="background-image:url(\'' . htmlspecialchars($intro_poster) . '\')"' : ''; ?>></div>
    <video class="intro__video" muted playsinline preload="none" aria-hidden="true" tabindex="-1"<?= $has_poster ? ' poster="' . htmlspecialchars($intro_poster) . '"' : ''; ?>></video>
    <div class="intro__shade"></div>
    <div class="intro__loader" role="status">Loading<i></i></div>
    <button type="button" class="intro__btn intro__skip" data-intro-skip>Skip intro</button>
    <button type="button" class="intro__btn intro__sound" data-intro-sound aria-pressed="false" hidden><span>Sound off</span></button>
    <button type="button" class="intro__btn intro__play" data-intro-play hidden>Play intro</button>
    <button type="button" class="intro__btn intro__enter" data-intro-enter>Enter stadium</button>
  </div>
<?php endif; ?>

  <!-- HERO: scroll-controlled kick-off scene (canvas is progressive enhancement) -->
  <section class="hero" data-hero aria-label="Stadium Pass introduction">
    <div class="hero-stage">
      <canvas id="stage" aria-hidden="true"></canvas>
<?php if (!empty($show_video)): ?>
      <script>document.documentElement.classList.add('video-hero');</script>
      <video class="hero-video" muted playsinline preload="metadata" aria-hidden="true" tabindex="-1" data-src="<?= htmlspecialchars($intro_video); ?>"<?= $has_poster ? ' poster="' . htmlspecialchars($intro_poster) . '"' : ''; ?>></video>
<?php endif; ?>
      <div class="hero-art" aria-hidden="true"></div>
      <div class="hero-vignette" aria-hidden="true"></div>
      <div class="hero-speed" aria-hidden="true"></div>
      <div class="hero-flash" aria-hidden="true"></div>
      <div class="hero-fade" aria-hidden="true"></div>

      <div class="hero-copy" data-hero-copy>
        <p class="eyebrow"><span></span>Live football tickets</p>
        <h1 class="hero-title">Your game.<br><em>Your moment.</em></h1>
        <p class="hero-sub">Discover the next big match. Secure your seat. Feel the game.</p>
        <a href="#fixtures" class="hero-cta" data-scroll-to="#fixtures">
          Explore matches
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
        </a>
      </div>

      <div class="scroll-cue" data-scroll-cue aria-hidden="true"><i></i>Scroll to kick off</div>
    </div>
  </section>

  <!-- FIXTURES -->
  <section class="fixtures" id="fixtures">
    <div class="fx-wrap">
      <header class="fx-head" data-reveal>
        <p class="eyebrow"><span></span>Upcoming fixtures<span></span></p>
        <h2>The next big moment</h2>
        <p>Your next unforgettable football experience starts here.</p>
      </header>

      <?php if (count($matches) > 0): ?>
        <div class="fx-filters" data-filters hidden>
          <label>Team
            <select data-filter="team"><option value="">All teams</option></select>
          </label>
          <label>Date
            <select data-filter="date"><option value="">All dates</option></select>
          </label>
          <button type="button" data-filter-reset>Reset</button>
          <span class="fx-count" data-count></span>
        </div>

        <div class="fx-grid">
          <?php foreach ($matches as $match):
            $home = (string)$match['home_team'];
            $away = (string)$match['away_team'];
            $kick_ts = strtotime($match['match_date'] . ' ' . $match['match_time']);
          ?>
            <article class="fx-card"
                     data-home="<?= htmlspecialchars($home); ?>"
                     data-away="<?= htmlspecialchars($away); ?>"
                     data-date="<?= htmlspecialchars($match['match_date']); ?>"
                     data-kickoff="<?= $kick_ts ? date('Y-m-d\TH:i:s', $kick_ts) : ''; ?>">
              <span class="fx-glow" aria-hidden="true"></span>
              <div class="fx-top">
                <span class="fx-badge" data-badge>Upcoming</span>
                <span class="fx-feat">Featured fixture</span>
              </div>

              <div class="fx-teams">
                <div class="fx-team">
                  <span class="fx-crest" style="--h: <?= $crest_hue($home); ?>" aria-hidden="true"><b><?= htmlspecialchars($crest_initials($home)); ?></b></span>
                  <strong><?= htmlspecialchars($home); ?></strong>
                </div>
                <div class="fx-vs"><span>VS</span></div>
                <div class="fx-team">
                  <span class="fx-crest" style="--h: <?= $crest_hue($away); ?>" aria-hidden="true"><b><?= htmlspecialchars($crest_initials($away)); ?></b></span>
                  <strong><?= htmlspecialchars($away); ?></strong>
                </div>
              </div>

              <div class="fx-meta">
                <span>
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/></svg>
                  <?= htmlspecialchars($match['match_date']); ?>
                </span>
                <span>
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
                  <?= htmlspecialchars($match['match_time']); ?>
                </span>
              </div>

              <div class="fx-count-down" data-countdown aria-hidden="true"></div>

              <a href="booking.php?action=seats&match_id=<?= $match['match_id']; ?>" class="fx-cta">
                Select seats
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
              </a>
            </article>
          <?php endforeach; ?>
        </div>
        <p class="fx-none" data-none hidden>No fixtures match those filters.</p>
      <?php else: ?>
        <div class="fx-empty" data-reveal>
          <svg width="56" height="56" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 8l3.5 2.5-1.3 4h-4.4l-1.3-4zM12 3v5M20.5 9.5l-5-1M17.5 19l-3.3-4.5M6.5 19l3.3-4.5M3.5 9.5l5-1"/></svg>
          <h3>No upcoming matches</h3>
          <p>No upcoming matches scheduled right now. Check back soon, the next kick-off will land here.</p>
        </div>
      <?php endif; ?>
    </div>
  </section>

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