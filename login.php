<?php
require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/config/db.php';

if (!empty($_SESSION['user_id'])) {
    header('Location: ' . home_for_role($_SESSION['role']));
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $pdo->prepare('SELECT id, name, password_hash, role FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['user_id']   = $user['id'];
        $_SESSION['name']      = $user['name'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['role']      = $user['role'];
        header('Location: ' . home_for_role($user['role']));
        exit;
    }
    $error = 'Invalid email or password.';
}

$page_title = 'Login';
require_once __DIR__ . '/includes/header.php';
?>

<div class="card" style="max-width: 420px; margin: 2rem auto;">
  <h2 class="page-title" style="font-size: 1.5rem; margin-bottom: 1rem;">Login</h2>
  <?php if ($error): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>
  <form method="post" action="login.php">
    <div class="form-group">
      <label for="email">Email</label>
      <input type="email" id="email" name="email" class="form-control" required autofocus>
    </div>
    <div class="form-group">
      <label for="password">Password</label>
      <input type="password" id="password" name="password" class="form-control" required>
    </div>
    <button type="submit" class="btn" style="width: 100%; margin-top: 0.5rem;">Login</button>
  </form>
  <p style="margin-top: 1.25rem; text-align: center; color: #64748b;">
    Don't have an account? <a href="register.php" style="color: var(--green); font-weight: 600;">Register here</a>
  </p>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>