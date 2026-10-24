<?php
require_once '../function.php';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === "POST" && isset($_POST['login_btn'])) {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    if (empty($email) || empty($password)) {
        $error = 'All fields are required';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email address';
    } else {
        $db = new DB_con();
        $user = $db->login($email, $password);
        if ($user) {
                session_start();
            $_SESSION['user_id']   = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_role'] = $user['role'];

            if ($user['role'] === 'mentor') {
                header("Location: ../coach/coach-dashboard.php");
                exit();
            } elseif ($user['role'] === 'mentee') {
                header("Location: ../user/dashboard.php");
                exit();
            } else {
                header("Location: ../admin/dashboard.php");
                exit();
            }
        } else {
            $error = 'Invalid email or password';
        }
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
  <link rel="stylesheet" href="../style.css">
</head>
<body class="auth-body">
  <main class="auth">
    <aside class="auth-side">
      <a class="brand" href="../index.html"><span class="brand-mark"></span>CareerPath</a>
      <h1>Welcome back!</h1>
      <p>Continue tracking your goals and connecting with your mentors.</p>
    </aside>
    
    <section class="auth-panel">
      <h2>Log in to your account</h2>

      <?php if (!empty($error)): ?>
          <div class="alert-error" style="color: red; margin-bottom: 15px;"><?php echo $error; ?></div>
      <?php endif; ?>

      <p class="muted">Don't have an account? <a href="signup.php">Create account</a></p>
      
      <form action="login.php" method="POST">
        <label for="email">Email</label>
        <input id="email" type="email" autocomplete="email" placeholder="shatha@email.com" required name="email">
       
        <label for="password">Password</label>
        <input id="password" type="password" autocomplete="current-password" required name="password">
        
        <button class="btn" type="submit" name="login_btn">Log in</button>
      </form>
    </section>
  </main>
</body>
</html>