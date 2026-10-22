<?php
require 'config/db.php';
require_login();

$uid = $_SESSION['user_id'];

$goals = $pdo->prepare('SELECT title, progress FROM goals WHERE user_id = ? ORDER BY id DESC');
$goals->execute([$uid]);
$goals = $goals->fetchAll();

$sessions = $pdo->prepare('SELECT coach_name, topic, starts_at FROM coaching_sessions WHERE user_id = ? AND starts_at >= NOW() ORDER BY starts_at LIMIT 5');
$sessions->execute([$uid]);
$sessions = $sessions->fetchAll();

$avg = $goals ? (int)round(array_sum(array_column($goals, 'progress')) / count($goals)) : 0;
$first = explode(' ', $_SESSION['user_name'])[0];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Dashboard | CareerPath</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:wght@600;700&family=Figtree:wght@400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="style.css">
</head>
<body>
  <div class="app">
    <nav class="sidebar" aria-label="Main">
      <a class="brand" href="dashboard.php"><span class="brand-mark"></span>CareerPath</a>
      <a class="nav-link active" href="dashboard.php">Dashboard</a>
      <a class="nav-link" href="#goals">My goals</a>
      <a class="nav-link" href="#sessions">Sessions</a>
      <a class="nav-link" href="#coaches">Find a coach</a>
      <a class="nav-link logout" href="logout.php">Log out</a>
    </nav>

    <main class="content">
      <header class="page-head">
        <div>
          <h1>Welcome back, <?= e($first) ?></h1>
          <p class="muted">Here is where your career plan stands today.</p>
        </div>
        <a class="btn btn-sm" href="#coaches">Book a session</a>
      </header>

      <section class="stats">
        <div class="stat"><span class="stat-num"><?= count($goals) ?></span><span class="stat-label">Active goals</span></div>
        <div class="stat"><span class="stat-num"><?= count($sessions) ?></span><span class="stat-label">Upcoming sessions</span></div>
        <div class="stat"><span class="stat-num"><?= $avg ?>%</span><span class="stat-label">Average progress</span></div>
      </section>

      <div class="grid">
        <section class="panel" id="goals">
          <h2>Your goals</h2>
          <?php if (!$goals): ?>
            <p class="empty">You have no goals yet. Add one to start tracking your progress.</p>
          <?php else: foreach ($goals as $g): ?>
            <div class="goal">
              <div class="goal-top"><span><?= e($g['title']) ?></span><span><?= (int)$g['progress'] ?>%</span></div>
              <div class="bar" role="progressbar" aria-valuenow="<?= (int)$g['progress'] ?>" aria-valuemin="0" aria-valuemax="100">
                <span style="width: <?= (int)$g['progress'] ?>%"></span>
              </div>
            </div>
          <?php endforeach; endif; ?>
        </section>

        <section class="panel" id="sessions">
          <h2>Upcoming sessions</h2>
          <?php if (!$sessions): ?>
            <p class="empty">No sessions booked. Choose a coach to schedule your first one.</p>
          <?php else: foreach ($sessions as $s): $t = strtotime($s['starts_at']); ?>
            <div class="session">
              <div class="date"><b><?= date('d', $t) ?></b><small><?= date('M', $t) ?></small></div>
              <div>
                <strong><?= e($s['topic']) ?></strong>
                <p class="muted">with <?= e($s['coach_name']) ?> at <?= date('g:i A', $t) ?></p>
              </div>
            </div>
          <?php endforeach; endif; ?>
        </section>
      </div>
    </main>
  </div>
</body>
</html>