<?php
require_once '../function.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === "POST" && isset($_POST['subtn'])) {
    $name     = trim($_POST['fname'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $rpass    = $_POST['rpassword'] ?? '';
    $role     = $_POST['role'] ?? 'mentee'; 

    if (empty($name) || empty($email) || empty($password)) {
        $error = 'All fields are required';
    } 
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email address';
    } 
    elseif ($password !== $rpass) {
        $error = 'Mismatch passwords';
    } 
    else {
  
        $reg = new DB_con();
        $result = $reg->register($name, $email, $password, $role);

        if ($result) {
       
            if ($role === 'mentor') {
                header("Location: ../coach/coach-dashboard.php");
                exit();
            } else {
                header("Location: ../user/dashboard.php");
                exit();
            }
        } else {
            $error = 'Registration failed. Email might already be in use.';
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
  <link rel="stylesheet" href="../style.css">
</head>
<body class="auth-body">
  <main class="auth">
    <aside class="auth-side">
      <a class="brand" href="index.html"><span class="brand-mark"></span>CareerPath</a>
      <h1>Your next role starts with a plan.</h1>
      <p>Set a goal, meet a coach, and track every step toward the job you want.</p>
      <svg class="trail" viewBox="0 0 320 120" aria-hidden="true"><path d="M6 100 C 70 100, 70 30, 140 40 S 230 90, 314 14" fill="none" stroke="currentColor" stroke-width="3" stroke-dasharray="2 9" stroke-linecap="round"/><circle cx="6" cy="100" r="6" fill="currentColor"/><circle cx="140" cy="40" r="6" fill="currentColor"/><circle cx="314" cy="14" r="9" fill="#F2A93B"/></svg>
    </aside>
    
    <section class="auth-panel">
      <h2>Create your account</h2>

      <?php if (!empty($error)): ?>
          <div class="alert-error" style="color: red; margin-bottom: 15px;"><?php echo $error; ?></div>
      <?php endif; ?>

      <p class="muted">Already registered? <a href="login.php">Log in</a></p>
      
      <form action="signup.php" method="POST">
        
        <fieldset class="role-group" style="border:none; padding:0; margin-bottom:20px;">
          <legend class="role-legend">I am joining as a...</legend>
          <div class="role-options" style="display:flex; gap:15px; margin-top:10px;">
            <label class="role-option">
              <input type="radio" name="role" value="mentor" required>
              <span class="role-card">
                <span class="role-icon" aria-hidden="true">✦</span>
                <span class="role-text">mentor</span>
                <span class="role-description">Share your experience</span>
              </span>
            </label>

            <label class="role-option">
              <input type="radio" name="role" value="mentee" checked required>
              <span class="role-card">
                <span class="role-icon" aria-hidden="true">↗</span>
                <span class="role-text">mentee</span>
                <span class="role-description">Grow your career</span>
              </span>
            </label>
          </div>
        </fieldset>

        <label for="full_name">Full name</label>
        <input id="full_name" type="text" autocomplete="name" required name="fname">
        
        <label for="email">Email</label>
        <input id="email" type="email" autocomplete="email" placeholder="shatha@email.com" required name="email">
       
        <div class="row">
          <div>
            <label for="password">Password</label>
            <input id="password" type="password" autocomplete="new-password" minlength="8" required name="password">
          </div>
          <div>
            <label for="confirm_password">Confirm password</label>
            <input id="confirm_password" type="password" autocomplete="new-password" required name="rpassword">
          </div>
        </div>
        
        <button class="btn" type="submit" name="subtn">Create account</button>
      </form>
    </section>
  </main>
</body>
</html>