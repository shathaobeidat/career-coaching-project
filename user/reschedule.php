<?php
require_once __DIR__ . '/../includes/init.php';

$bookingId = (int) ($_GET['booking'] ?? $_POST['booking'] ?? 0);
$booking   = $bookingId ? $db->getMenteeBooking($bookingId, $userId) : false;

if (!$booking || !in_array($booking['status'], ['pending', 'confirmed'], true)) {
    flash('error', 'This booking cannot be rescheduled.');
    redirect('sessions.php');
}
if (!DB_con::canCustomerModify($booking['status'], $booking['start_at'])) {
    flash('error', DB_con::deadlineMessage());
    redirect('sessions.php');
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_valid()) {
        flash('error', 'Your session expired. Please try again.');
        redirect('reschedule.php?booking=' . $bookingId);
    }
    $res = $db->rescheduleBooking($bookingId, $userId, (int) ($_POST['availability_id'] ?? 0));
    if ($res['ok']) {
        flash('success', 'Session rescheduled. ' . $booking['coach_name'] . ' needs to confirm the new time.');
        redirect('sessions.php');
    }
    $errors = $res['errors'];
}

$byDate = [];
foreach ($db->getBookableSlots((int) $booking['coach_id']) as $s) {
    $byDate[date('Y-m-d', strtotime($s['start_at']))][] = $s;
}

$pageTitle = 'Reschedule session';
$activeNav = 'sessions';
require __DIR__ . '/../includes/header.php';
?>
      <header class="page-head">
        <div><h1>Reschedule session</h1><p class="muted"><a href="sessions.php">← Back to sessions</a></p></div>
      </header>

      <section class="panel form-panel">
        <h2><?php echo e($booking['service_name']); ?> with <?php echo e($booking['coach_name']); ?></h2>
        <p class="muted">Currently: <?php echo date('l, d F \a\t g:i A', strtotime($booking['start_at'])); ?></p>
        <p class="policy-note">Choosing a new time sends the booking back to your coach for confirmation.</p>

        <?php echo error_list($errors); ?>

        <?php if (!$byDate): ?>
          <p class="empty">Your coach has no other free times right now. Please check back later.</p>
        <?php else: ?>
        <form method="post" action="reschedule.php?booking=<?php echo $bookingId; ?>">
          <?php echo csrf_field(); ?>
          <input type="hidden" name="booking" value="<?php echo $bookingId; ?>">
          <label>Choose a new time</label>
          <?php foreach ($byDate as $date => $daySlots): ?>
            <p class="slot-day"><?php echo date('l, d F', strtotime($date)); ?></p>
            <div class="slots">
              <?php foreach ($daySlots as $s): ?>
                <label><input type="radio" name="availability_id" value="<?php echo (int) $s['id']; ?>"><span><?php echo date('g:i A', strtotime($s['start_at'])); ?> – <?php echo date('g:i A', strtotime($s['end_at'])); ?></span></label>
              <?php endforeach; ?>
            </div>
          <?php endforeach; ?>
          <button class="btn" type="submit">Reschedule</button>
        </form>
        <?php endif; ?>
      </section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
