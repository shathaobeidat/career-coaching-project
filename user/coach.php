<?php
require_once __DIR__ . '/../includes/init.php';

$coachId = (int) ($_GET['id'] ?? 0);
$coach   = $coachId ? $db->getApprovedCoach($coachId) : false;
if (!$coach) {
    flash('error', 'That coach is not available.');
    redirect('find-coach.php');
}

$services = $db->getActiveServices($coachId);
$rating   = $db->getCoachRatingSummary($coachId);
$reviews  = $db->getCoachPublicReviews($coachId, 20);
$slots    = $db->getBookableSlots($coachId);
$next     = $slots ? $slots[0] : null;

$pageTitle = $coach['name'];
$activeNav = 'find-coach';
require __DIR__ . '/../includes/header.php';
?>
      <header class="page-head">
        <div><h1><?php echo e($coach['name']); ?></h1><p class="muted"><a href="find-coach.php">← All coaches</a></p></div>
        <a class="btn btn-sm" href="book-session.php?coach=<?php echo $coachId; ?>">Book a session</a>
      </header>

      <div class="stack">
        <section class="panel">
          <div class="profile-head">
            <?php if ($coach['profile_image']): ?><img class="avatar-img" src="../<?php echo e($coach['profile_image']); ?>" alt=""><?php else: ?><div class="avatar"><?php echo e(initials($coach['name'])); ?></div><?php endif; ?>
            <div>
              <h2><?php echo e($coach['professional_title']); ?></h2>
              <p class="muted"><?php echo (int) $coach['experience_years']; ?> years of experience
                <?php if ($rating['total']): ?> &middot; <span class="stars"><?php echo stars($rating['average']); ?></span><?php echo e($rating['average']); ?> (<?php echo $rating['total']; ?> review<?php echo $rating['total'] === 1 ? '' : 's'; ?>)<?php endif; ?></p>
            </div>
          </div>
          <dl class="details">
            <dt>About</dt><dd><?php echo nl2br(e($coach['bio'])); ?></dd>
            <?php if ($coach['education']): ?><dt>Education</dt><dd><?php echo e($coach['education']); ?></dd><?php endif; ?>
            <?php if ($coach['certifications']): ?><dt>Certifications</dt><dd><?php echo nl2br(e($coach['certifications'])); ?></dd><?php endif; ?>
            <?php if ($coach['linkedin_url']): ?><dt>LinkedIn</dt><dd><a href="<?php echo e($coach['linkedin_url']); ?>" target="_blank" rel="noopener noreferrer">View profile</a></dd><?php endif; ?>
            <dt>Next available</dt><dd><?php echo $next ? date('l, d F \a\t g:i A', strtotime($next['start_at'])) . ' <span class="muted">(' . count($slots) . ' open slot' . (count($slots) === 1 ? '' : 's') . ')</span>' : '<span class="muted">No open slots right now</span>'; ?></dd>
          </dl>
        </section>

        <section class="panel">
          <h2>Services</h2>
          <?php foreach ($services as $s): ?>
            <div class="list-row">
              <div>
                <strong><?php echo e($s['name']); ?></strong>
                <p class="muted"><?php echo (int) $s['duration_minutes']; ?> min &middot; <?php echo money($s['price']); ?></p>
                <?php if ($s['description']): ?><p><?php echo nl2br(e($s['description'])); ?></p><?php endif; ?>
              </div>
              <a class="btn btn-sm btn-xs" href="book-session.php?coach=<?php echo $coachId; ?>&amp;service=<?php echo (int) $s['id']; ?>">Book this</a>
            </div>
          <?php endforeach; ?>
        </section>

        <section class="panel">
          <h2>Reviews</h2>
          <?php if (!$reviews): ?>
            <p class="empty">No reviews yet.</p>
          <?php else: foreach ($reviews as $r): ?>
            <div class="list-row review">
              <div>
                <span class="stars" aria-label="<?php echo (int) $r['rating']; ?> out of 5"><?php echo stars($r['rating']); ?></span>
                <strong><?php echo e($r['first_name']); ?></strong> <span class="muted">&middot; <?php echo e($r['service_name']); ?></span>
                <?php if (!empty($r['comment'])): ?><p><?php echo nl2br(e($r['comment'])); ?></p><?php endif; ?>
              </div>
              <small class="muted"><?php echo date('d M Y', strtotime($r['created_at'])); ?></small>
            </div>
          <?php endforeach; endif; ?>
        </section>
      </div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
