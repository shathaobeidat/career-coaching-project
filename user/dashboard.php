<?php
require_once __DIR__ . '/../includes/init.php';

$sessions = $db->getMenteeSessions($userId);
$now = date('Y-m-d H:i:s');

$upcoming = array_reverse(array_values(array_filter($sessions, function ($s) use ($now) {
    return in_array($s['status'], ['pending', 'confirmed'], true) && $s['end_at'] >= $now;
})));                                                                  // soonest first
$completed = count(array_filter($sessions, function ($s) { return $s['status'] === 'completed'; }));
$toReview  = count(array_filter($sessions, function ($s) { return $s['status'] === 'completed' && !$s['review_id']; }));

$pageTitle = 'Welcome back, ' . $userName;
$activeNav = 'dashboard';
require __DIR__ . '/../includes/header.php';
?>
      <header class="page-head">
        <div>
          <h1>Welcome back, <?php echo e($userName); ?></h1>
          <p class="muted">Here is where your career plan stands today.</p>
        </div>
        <a class="btn btn-sm" href="find-coach.php">Book a session</a>
      </header>

      <section class="stats">
        <div class="stat"><span class="stat-num"><?php echo count($upcoming); ?></span><span class="stat-label">Upcoming sessions</span></div>
        <div class="stat"><span class="stat-num"><?php echo $completed; ?></span><span class="stat-label">Completed sessions</span></div>
        <div class="stat"><span class="stat-num"><?php echo $toReview; ?></span><span class="stat-label">Waiting for your review</span></div>
      </section>

      <section class="panel">
        <h2>Upcoming sessions</h2>
        <?php if (!$upcoming): ?>
          <p class="empty">No upcoming sessions. <a href="find-coach.php">Book a session now</a></p>
        <?php else: foreach (array_slice($upcoming, 0, 5) as $b): ?>
          <div class="session">
            <div class="date"><b><?php echo date('d', strtotime($b['start_at'])); ?></b><small><?php echo date('M', strtotime($b['start_at'])); ?></small></div>
            <div>
              <strong><?php echo e($b['service_name']); ?></strong>
              <p class="muted">with <?php echo e($b['coach_name']); ?> at <?php echo date('g:i A', strtotime($b['start_at'])); ?> &nbsp;<?php echo status_badge($b['status']); ?></p>
            </div>
          </div>
        <?php endforeach; endif; ?>
        <?php if ($upcoming): ?><p style="margin-top:1rem"><a href="sessions.php">All sessions →</a></p><?php endif; ?>
      </section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
