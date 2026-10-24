<?php
require_once __DIR__ . '/../includes/init.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_valid()) {
        flash('error', 'Your session expired. Please try again.');
        redirect('sessions.php');
    }
    $bookingId = (int) ($_POST['booking_id'] ?? 0);

    if (($_POST['action'] ?? '') === 'cancel') {
        $res = $db->cancelMenteeBooking($bookingId, $userId);
        $res['ok'] ? flash('success', 'Booking cancelled.') : flash('error', implode(' ', $res['errors']));
    } elseif (($_POST['action'] ?? '') === 'review') {
        $res = $db->addReview($bookingId, $userId, $_POST['rating'] ?? 0, $_POST['comment'] ?? '');
        $res['ok'] ? flash('success', 'Thank you for your review!') : flash('error', implode(' ', $res['errors']));
    }
    redirect('sessions.php');
}

$sessions = $db->getMenteeSessions($userId);
$now = date('Y-m-d H:i:s');
$upcoming = $past = [];
foreach ($sessions as $s) {
    if (in_array($s['status'], ['pending', 'confirmed'], true) && $s['end_at'] >= $now) {
        array_unshift($upcoming, $s);       // soonest first
    } else {
        $past[] = $s;                       // newest first
    }
}

$pg   = paginate($past, 8);
$past = $pg['items'];

$pageTitle = 'Sessions';
$activeNav = 'sessions';
require __DIR__ . '/../includes/header.php';
?>
      <header class="page-head">
        <div><h1>Sessions</h1><p class="muted">Your upcoming and past coaching sessions.</p></div>
        <a class="btn btn-sm" href="find-coach.php">Book a session</a>
      </header>

      <section class="panel">
        <h2>Upcoming</h2>
        <p class="policy-note">Confirmed sessions can be cancelled or rescheduled up to <?php echo DB_con::CANCELLATION_HOURS; ?> hours before they start.</p>
        <?php if (!$upcoming): ?>
          <p class="empty">No upcoming sessions.</p>
        <?php else: foreach ($upcoming as $b): ?>
          <div class="list-row">
            <div>
              <strong><?php echo e($b['service_name']); ?></strong>
              <p class="muted">with <?php echo e($b['coach_name']); ?>, <?php echo date('D d M', strtotime($b['start_at'])); ?> at <?php echo date('g:i A', strtotime($b['start_at'])); ?> &middot; <?php echo (int) $b['duration_minutes']; ?> min &middot; <?php echo money($b['price']); ?></p>
            </div>
            <div class="actions">
              <?php echo status_badge($b['status']); ?>
              <?php if (DB_con::canCustomerModify($b['status'], $b['start_at'])): ?>
                <a class="btn btn-sm btn-xs btn-ghost" href="reschedule.php?booking=<?php echo (int) $b['id']; ?>">Reschedule</a>
                <form method="post" action="sessions.php" onsubmit="return confirm('Cancel this session?');">
                  <?php echo csrf_field(); ?>
                  <input type="hidden" name="action" value="cancel">
                  <input type="hidden" name="booking_id" value="<?php echo (int) $b['id']; ?>">
                  <button type="submit" class="btn btn-sm btn-xs btn-danger">Cancel</button>
                </form>
              <?php else: ?>
                <small class="muted">Too late to change (<?php echo DB_con::CANCELLATION_HOURS; ?>h policy)</small>
              <?php endif; ?>
            </div>
          </div>
        <?php endforeach; endif; ?>
      </section>

      <section class="panel" style="margin-top:1.2rem">
        <h2>Past</h2>
        <?php if (!$past): ?>
          <p class="empty">No past sessions yet.</p>
        <?php else: foreach ($past as $b): ?>
          <div class="list-row">
            <div>
              <strong><?php echo e($b['service_name']); ?></strong>
              <p class="muted">with <?php echo e($b['coach_name']); ?>, <?php echo date('d M Y', strtotime($b['start_at'])); ?></p>
              <?php if ($b['review_id']): ?><p><span class="stars"><?php echo stars($b['review_rating']); ?></span><span class="muted">Your review</span></p><?php endif; ?>
            </div>
            <?php echo status_badge($b['status']); ?>
            <?php if ($b['status'] === 'completed' && !$b['review_id']): ?>
              <details class="review-box">
                <summary class="btn btn-sm btn-xs btn-ghost">Leave a review</summary>
                <form method="post" action="sessions.php">
                  <?php echo csrf_field(); ?>
                  <input type="hidden" name="action" value="review">
                  <input type="hidden" name="booking_id" value="<?php echo (int) $b['id']; ?>">
                  <label for="rating-<?php echo (int) $b['id']; ?>">Rating</label>
                  <select id="rating-<?php echo (int) $b['id']; ?>" name="rating" required>
                    <option value="">Choose…</option>
                    <?php for ($i = 5; $i >= 1; $i--): ?><option value="<?php echo $i; ?>"><?php echo $i; ?> – <?php echo ['', 'Poor', 'Fair', 'Good', 'Very good', 'Excellent'][$i]; ?></option><?php endfor; ?>
                  </select>
                  <label for="comment-<?php echo (int) $b['id']; ?>">Comment <span class="optional">(optional)</span></label>
                  <textarea id="comment-<?php echo (int) $b['id']; ?>" name="comment" maxlength="2000"></textarea>
                  <button type="submit" class="btn btn-sm btn-xs">Submit review</button>
                </form>
              </details>
            <?php endif; ?>
          </div>
        <?php endforeach; endif; ?>
        <?php echo pagination_nav($pg['page'], $pg['pages']); ?>
      </section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
