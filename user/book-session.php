<?php
require_once __DIR__ . '/../includes/init.php';

$coachId = (int) ($_GET['coach'] ?? $_POST['coach'] ?? 0);
$coach   = $coachId ? $db->getApprovedCoach($coachId) : false;
if (!$coach) {
    flash('error', 'That coach is not available. Please pick another one.');
    redirect('find-coach.php');
}

$errors = [];
$form = ['service_id' => (int) ($_GET['service'] ?? 0), 'availability_id' => 0, 'notes' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_valid()) {
        flash('error', 'Your session expired. Please try again.');
        redirect('book-session.php?coach=' . $coachId);
    }
    $form = [
        'service_id'      => (int) ($_POST['service_id'] ?? 0),
        'availability_id' => (int) ($_POST['availability_id'] ?? 0),
        'notes'           => trim($_POST['notes'] ?? ''),
    ];
    $res = $db->createBooking($userId, $form['service_id'], $form['availability_id'], $form['notes']);
    if ($res['ok']) {
        flash('success', 'Booking request sent. ' . $coach['name'] . ' will confirm it soon.');
        redirect('sessions.php');
    }
    $errors = $res['errors'];
}

$services = $db->getActiveServices($coachId);
$slots    = $db->getBookableSlots($coachId);
$byDate = [];
foreach ($slots as $s) {
    $byDate[date('Y-m-d', strtotime($s['start_at']))][] = $s;
}

$pageTitle = 'Book a session';
$activeNav = 'find-coach';
require __DIR__ . '/../includes/header.php';
?>
      <header class="page-head">
        <div><h1>Book a session</h1><p class="muted">Pick a topic and a time that suits you.</p></div>
      </header>

      <section class="panel form-panel">
        <div class="profile-head">
          <?php if ($coach['profile_image']): ?><img class="avatar-img" src="../<?php echo e($coach['profile_image']); ?>" alt=""><?php else: ?><div class="avatar"><?php echo e(initials($coach['name'])); ?></div><?php endif; ?>
          <div><h2><?php echo e($coach['name']); ?></h2><p class="muted"><?php echo e($coach['professional_title']); ?></p></div>
        </div>

        <?php echo error_list($errors); ?>

        <?php if (!$byDate): ?>
          <p class="empty">This coach has no free times right now. <a href="find-coach.php">Browse other coaches</a></p>
        <?php else: ?>
        <form method="post" action="book-session.php?coach=<?php echo $coachId; ?>">
          <?php echo csrf_field(); ?>
          <input type="hidden" name="coach" value="<?php echo $coachId; ?>">

          <label for="service_id">Topic</label>
          <select id="service_id" name="service_id" required>
            <?php foreach ($services as $s): ?>
              <option value="<?php echo (int) $s['id']; ?>"<?php echo $form['service_id'] === (int) $s['id'] ? ' selected' : ''; ?>><?php echo e($s['name']); ?> — <?php echo (int) $s['duration_minutes']; ?> min, <?php echo money($s['price']); ?></option>
            <?php endforeach; ?>
          </select>

          <label>Choose a time</label>
          <?php foreach ($byDate as $date => $daySlots): ?>
            <p class="slot-day"><?php echo date('l, d F', strtotime($date)); ?></p>
            <div class="slots">
              <?php foreach ($daySlots as $s): ?>
                <label><input type="radio" name="availability_id" value="<?php echo (int) $s['id']; ?>"<?php echo $form['availability_id'] === (int) $s['id'] ? ' checked' : ''; ?>><span><?php echo date('g:i A', strtotime($s['start_at'])); ?> – <?php echo date('g:i A', strtotime($s['end_at'])); ?></span></label>
              <?php endforeach; ?>
            </div>
          <?php endforeach; ?>

          <label for="notes">What do you want to work on? <span class="optional">(optional)</span></label>
          <textarea id="notes" name="notes" maxlength="1000"><?php echo e($form['notes']); ?></textarea>

          <button class="btn" type="submit">Send booking request</button>
        </form>
        <?php endif; ?>
      </section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
