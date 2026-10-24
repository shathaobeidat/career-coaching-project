<?php
require_once __DIR__ . '/function.php';
require_once __DIR__ . '/includes/helpers.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$role = $_SESSION['user_role'] ?? null;
$dashboards = ['mentee' => 'user/dashboard.php', 'mentor' => 'coach/coach-dashboard.php', 'admin' => 'admin/dashboard.php'];

$db      = new DB_con();
$search  = trim($_GET['q'] ?? '');
$pg      = paginate($db->getApprovedCoaches($search), 12);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Coaches | CareerPath</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:wght@600;700&family=Figtree:wght@400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="style.css">
</head>
<body>
<header class="site-nav">
  <a class="brand" href="index.html"><span class="brand-mark"></span>CareerPath</a>
  <nav class="links" aria-label="Main">
    <a href="index.html">Home</a>
    <a href="coaches.php" class="on">Coaches</a>
    <a href="about.html">About</a>
    <?php if ($role && isset($dashboards[$role])): ?>
      <a class="btn" href="<?php echo $dashboards[$role]; ?>">My dashboard</a>
    <?php else: ?>
      <a href="auth/login.php">Log in</a>
      <a class="btn" href="auth/signup.php">Get started</a>
    <?php endif; ?>
  </nav>
</header>
<section class="wrap">
  <h1 class="section-title">Our coaches</h1>
  <p class="muted">Every coach is reviewed and approved by our team before appearing here.</p>

  <form class="searchbar" method="get" action="coaches.php" style="margin-top:1.2rem">
    <input type="search" name="q" placeholder="Search by name, title or topic" value="<?php echo e($search); ?>" aria-label="Search coaches">
    <button class="btn btn-sm" type="submit">Search</button>
  </form>

  <?php if (!$pg['items']): ?>
    <p class="empty"><?php echo $search !== '' ? 'No coaches match your search.' : 'Our coaches are joining soon. Please check back shortly.'; ?></p>
  <?php else: ?>
  <div class="cards">
    <?php foreach ($pg['items'] as $c): ?>
      <div class="card">
        <?php if ($c['profile_image']): ?>
          <img class="avatar-img" src="<?php echo e($c['profile_image']); ?>" alt="" style="margin-bottom:.8rem">
        <?php else: ?>
          <div class="avatar"><?php echo e(initials($c['name'])); ?></div>
        <?php endif; ?>
        <h3><?php echo e($c['name']); ?></h3>
        <p class="muted"><?php echo e($c['professional_title']); ?></p>
        <p class="muted" style="margin-top:.4rem"><?php echo e(mb_strimwidth($c['bio'], 0, 110, '…')); ?></p>
        <span class="tag"><?php echo (int) $c['experience_years']; ?> yrs experience</span>
        <?php if ($c['reviews_count']): ?><span class="tag"><span class="stars"><?php echo stars($c['avg_rating']); ?></span><?php echo e($c['avg_rating']); ?> (<?php echo (int) $c['reviews_count']; ?>)</span><?php endif; ?>
        <p class="price">From <?php echo money($c['min_price']); ?> <small>/ session</small></p>
        <?php if ($role === 'mentee'): ?>
          <a class="btn" href="user/coach.php?id=<?php echo (int) $c['id']; ?>">View profile</a>
        <?php else: ?>
          <a class="btn" href="auth/signup.php">Sign up to book</a>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>
  <?php echo pagination_nav($pg['page'], $pg['pages']); ?>
  <?php endif; ?>
</section>
<footer class="site-foot">&copy; 2026 CareerPath. Coaching for every stage of your career.</footer>
</body></html>
