<?php
require 'config/db.php';
if (!empty($_SESSION['user_id'])) { header('Location: dashboard.php'); exit; }

$errors = [];
$name = $email = $role = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_ok()) $errors[] = 'Your session expired. Reload the page and try again.';

    $name  = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $role  = trim($_POST['current_role'] ?? '');
    $pass  = $_POST['password'] ?? '';
    $pass2 = $_POST['confirm_password'] ?? '';

    if ($name === '') $errors[] = 'Enter your full name.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Enter a valid email address.';
    if (strlen($pass) < 8) $errors[] = 'Use a password with at least 8 characters.';
    if ($pass !== $pass2) $errors[] = 'The passwords do not match.';

    if (!$errors) {
        $check = $pdo->prepare('SELECT id FROM users WHERE email = ?');
        $check->execute([$email]);
        if ($check->fetch()) {
            $errors[] = 'An account with this email already exists. Log in instead.';
        } else {
            $ins = $pdo->prepare('INSERT INTO users (full_name, email, password_hash, job_title) VALUES (?,?,?,?)');
            $ins->execute([$name, $email, password_hash($pass, PASSWORD_DEFAULT), $role ?: null]);
            session_regenerate_id(true);
            $_SESSION['user_id'] = (int)$pdo->lastInsertId();
            $_SESSION['user_name'] = $name;
            header('Location: dashboard.php'); exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Create your account | CareerPath</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:wght@600;700&family=Figtree:wght@400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="style.css">
</head>
<body class="auth-body">
  <main class="auth">
    <aside class="auth-side">
      <a class="brand" href="login.php"><span class="brand-mark"></span>CareerPath</a>
      <h1>Your next role starts with a plan.</h1>
      <p>Set a goal, meet a coach, and track every step toward the job you want.</p>
      <svg class="trail" viewBox="0 0 320 120" aria-hidden="true">
        <path d="M6 100 C 70 100, 70 30, 140 40 S 230 90, 314 14" fill="none" stroke="currentColor" stroke-width="3" stroke-dasharray="2 9" stroke-linecap="round"/>
        <circle cx="6" cy="100" r="6" fill="currentColor"/><circle cx="140" cy="40" r="6" fill="currentColor"/><circle cx="314" cy="14" r="9" fill="#F2A93B"/>
      </svg>
    </aside>

    <section class="auth-panel">
      <h2>Create your account</h2>
      <p class="muted">Already registered? <a href="login.php">Log in</a></p>

      <?php if ($errors): ?>
        <div class="alert" role="alert">
          <ul><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul>
        </div>
      <?php endif; ?>

      <form method="post" novalidate>
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

        <label for="full_name">Full name</label>
        <input id="full_name" name="full_name" type="text" autocomplete="name" value="<?= e($name) ?>" required>

        <label for="email">Email</label>
        <input id="email" name="email" type="email" autocomplete="email" value="<?= e($email) ?>" required>

        <label for="current_role">Current job title <span class="optional">(optional)</span></label>
        <input id="current_role" name="current_role" type="text" value="<?= e($role) ?>">

        <div class="row">
          <div>
            <label for="password">Password</label>
            <input id="password" name="password" type="password" autocomplete="new-password" minlength="8" required>
          </div>
          <div>
            <label for="confirm_password">Confirm password</label>
            <input id="confirm_password" name="confirm_password" type="password" autocomplete="new-password" required>
          </div>
        </div>

        <button class="btn" type="submit">Create account</button>
      </form>
    </section>
  </main>
</body>
</html>