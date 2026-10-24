<?php
require_once __DIR__ . '/../includes/init.php';

$search  = trim($_GET['q'] ?? '');
$pg      = paginate($db->getApprovedCoaches($search), 12);
$coaches = $pg['items'];

$pageTitle = 'Find a coach';
$activeNav = 'find-coach';
require __DIR__ . '/../includes/header.php';
?>
      <header class="page-head">
        <div><h1>Find a coach</h1><p class="muted">Choose a coach, then book a time.</p></div>
      </header>

      <form class="searchbar" method="get" action="find-coach.php">
        <input type="search" name="q" placeholder="Search by name, title or topic" value="<?php echo e($search); ?>" aria-label="Search coaches">
        <button class="btn btn-sm" type="submit">Search</button>
      </form>

      <?php if (!$coaches): ?>
        <p class="empty"><?php echo $search !== '' ? 'No coaches match your search.' : 'No coaches are available yet. Please check back soon.'; ?></p>
      <?php else: ?>
      <div class="cards">
        <?php foreach ($coaches as $c): ?>
          <div class="card">
            <?php if ($c['profile_image']): ?>
              <img class="avatar-img" src="../<?php echo e($c['profile_image']); ?>" alt="" style="margin-bottom:.8rem">
            <?php else: ?>
              <div class="avatar"><?php echo e(initials($c['name'])); ?></div>
            <?php endif; ?>
            <h3><?php echo e($c['name']); ?></h3>
            <p class="muted"><?php echo e($c['professional_title']); ?></p>
            <p class="muted" style="margin-top:.4rem"><?php echo e(mb_strimwidth($c['bio'], 0, 110, '…')); ?></p>
            <span class="tag"><?php echo (int) $c['experience_years']; ?> yrs experience</span>
            <?php if ($c['reviews_count']): ?><span class="tag"><span class="stars"><?php echo stars($c['avg_rating']); ?></span><?php echo e($c['avg_rating']); ?> (<?php echo (int) $c['reviews_count']; ?>)</span><?php endif; ?>
            <p class="price">From <?php echo money($c['min_price']); ?> <small>/ session</small></p>
            <div class="actions" style="margin-top:.8rem">
              <a class="btn btn-sm btn-ghost" href="coach.php?id=<?php echo (int) $c['id']; ?>">View profile</a>
              <a class="btn btn-sm" href="book-session.php?coach=<?php echo (int) $c['id']; ?>">Book</a>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
      <?php echo pagination_nav($pg['page'], $pg['pages']); ?>
      <?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
