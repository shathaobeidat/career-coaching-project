<?php
require 'config/db.php';
if (!empty($_SESSION['user_id'])) { header('Location: dashboard.php'); exit; }

$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $pass  = $_POST['password'] ?? '';

    if (!csrf_ok()) {
        $error = 'Your session expired. Reload the page and try again.';
    } else {
        $stmt = $pdo->prepare('SELECT id, full_name, password_hash FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($pass, $user['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = (int)$user['id'];
            $_SESSION['user_name'] = $user['full_name'];
            header('Location: dashboard.php'); exit;
        }
        $error = 'The email or password is incorrect.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Log in | CareerPath</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:wght@600;700&family=Figtree:wght@400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="style.css">
</head>
<body class="auth-body">
  <main class="auth">
    <aside class="auth-side">
      <a class="brand" href="login.php"><span class="brand-mark"></span>CareerPath</a>
      <h1>Pick up where you left off.</h1>
      <p>Your goals, sessions, and next steps are waiting.</p>
      <svg class="trail" viewBox="0 0 320 120" aria-hidden="true">
        <path d="M6 100 C 70 100, 70 30, 140 40 S 230 90, 314 14" fill="none" stroke="currentColor" stroke-width="3" stroke-dasharray="2 9" stroke-linecap="round"/>
        <circle cx="6" cy="100" r="6" fill="currentColor"/><circle cx="140" cy="40" r="6" fill="currentColor"/><circle cx="314" cy="14" r="9" fill="#F2A93B"/>
      </svg>
    </aside>

    <section class="auth-panel">
      <h2>Log in</h2>
      <p class="muted">New here? <a href="signup.php">Create an account</a></p>

      <?php if ($error): ?><div class="alert" role="alert"><?= e($error) ?></div><?php endif; ?>

      <form method="post" novalidate>
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

        <label for="email">Email</label>
        <input id="email" name="email" type="email" autocomplete="email" value="<?= e($email) ?>" required autofocus>

        <label for="password">Password</label>
        <input id="password" name="password" type="password" autocomplete="current-password" required>

        <button class="btn" type="submit">Log in</button>
      </form>
    </section>
  </main>
</body>
</html>