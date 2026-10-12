<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$is_staff_dir = (basename(dirname($_SERVER['SCRIPT_NAME'])) === 'staff');
$root_path = $is_staff_dir ? '../' : '';
$current_page = basename($_SERVER['PHP_SELF']);

$role = $_SESSION['role'] ?? 'guest';
$user_display_name = $_SESSION['user_name'] ?? $_SESSION['name'] ?? '';
$title = $page_title ?? 'Stadium Pass';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($title) ?> - Stadium Pass</title>
  <link rel="stylesheet" href="<?= $root_path ?>style.css">
</head>
<body>
  <header class="site-header">
    <div class="nav-container">
      <a href="<?= $root_path ?>index.php" class="brand-logo">STADIUM<span>PASS</span></a>
      <ul class="nav-links">
        <?php if ($role === 'admin'): ?>
          <li><a href="<?= $root_path ?>matches.php" class="<?= $current_page === 'matches.php' ? 'active' : '' ?>">Matches</a></li>
          <li><a href="<?= $root_path ?>sections.php" class="<?= $current_page === 'sections.php' ? 'active' : '' ?>">Sections</a></li>
          <li><a href="<?= $root_path ?>prices.php" class="<?= $current_page === 'prices.php' ? 'active' : '' ?>">Prices</a></li>
          <li><a href="<?= $root_path ?>reports.php" class="<?= $current_page === 'reports.php' ? 'active' : '' ?>">Reports</a></li>
          <li><a href="<?= $root_path ?>booking.php?action=matches">Fan View</a></li>
          <li><a href="<?= $root_path ?>staff/verify.php">Staff Gate</a></li>
        <?php elseif ($role === 'staff'): ?>
          <li><a href="<?= $root_path ?>staff/verify.php" class="<?= $current_page === 'verify.php' ? 'active' : '' ?>">Verify Ticket</a></li>
          <li><a href="<?= $root_path ?>staff/entries.php" class="<?= $current_page === 'entries.php' ? 'active' : '' ?>">Section Entries</a></li>
          <li><a href="<?= $root_path ?>staff/complaints.php" class="<?= $current_page === 'complaints.php' ? 'active' : '' ?>">Complaints</a></li>
        <?php else: ?>
          <li><a href="<?= $root_path ?>booking.php?action=matches" class="<?= ($current_page === 'booking.php' && ($_GET['action'] ?? '') !== 'my_bookings') ? 'active' : '' ?>">Browse Matches</a></li>
          <li><a href="<?= $root_path ?>my_bookings.php" class="<?= $current_page === 'my_bookings.php' ? 'active' : '' ?>">My Bookings</a></li>
        <?php endif; ?>

        <?php if (!empty($_SESSION['user_id'])): ?>
          <li><span class="nav-user">Welcome, <?= htmlspecialchars($user_display_name ?: ucfirst($role)) ?> (<?= ucfirst($role) ?>)</span></li>
          <li><a href="<?= $root_path ?>logout.php" class="btn-nav-outline">Logout</a></li>
        <?php else: ?>
          <li><a href="<?= $root_path ?>login.php" class="btn-nav-outline">Login</a></li>
          <li><a href="<?= $root_path ?>register.php" class="btn btn-small">Sign Up</a></li>
        <?php endif; ?>
      </ul>
    </div>
  </header>
  <main class="main-content">
