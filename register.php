<?php
require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/config/db.php';

if (!empty($_SESSION['user_id'])) {
    header('Location: ' . home_for_role($_SESSION['role']));
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 6) {
        $error = 'Please fill all fields properly. Password must be at least 6 characters.';
    } else {
        $check = $pdo->prepare('SELECT id FROM users WHERE email = ?');
        $check->execute([$email]);
        if ($check->fetch()) {
            $error = 'This email is already registered.';
        } else {
            $stmt = $pdo->prepare(
                "INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, 'fan')"
            );
            $stmt->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT)]);
            header('Location: login.php?registered=1');
            exit;
        }
    }
}

$page_title = 'Register';
require_once __DIR__ . '/includes/header.php';
?>

<div class="card" style="max-width: 440px; margin: 2rem auto;">
  <h2 class="page-title" style="font-size: 1.5rem; margin-bottom: 1rem;">Create an Account</h2>
  <?php if ($error): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>
  <form method="post" action="register.php">
    <div class="form-group">
      <label for="name">Full Name</label>
      <input type="text" id="name" name="name" class="form-control" required autofocus>
    </div>
    <div class="form-group">
      <label for="email">Email</label>
      <input type="email" id="email" name="email" class="form-control" required>
    </div>
    <div class="form-group">
      <label for="password">Password (min 6 characters)</label>
      <input type="password" id="password" name="password" class="form-control" minlength="6" required>
    </div>
    <button type="submit" class="btn" style="width: 100%; margin-top: 0.5rem;">Register</button>
  </form>
  <p style="margin-top: 1.25rem; text-align: center; color: #64748b;">
    Already have an account? <a href="login.php" style="color: var(--green); font-weight: 600;">Login here</a>
  </p>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>