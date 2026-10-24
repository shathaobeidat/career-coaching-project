<?php
require_once __DIR__ . '/../includes/init.php';

$accountErrors = $passwordErrors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_valid()) {
        flash('error', 'Your session expired. Please try again.');
        redirect('profile.php');
    }
    $type = $_POST['form_type'] ?? '';

    if ($type === 'account') {
        $res = $db->updateUserAccount($userId, $_POST['name'] ?? '', $_POST['email'] ?? '');
        if ($res['ok']) {
            $_SESSION['user_name'] = trim($_POST['name']);
            flash('success', 'Your details were updated.');
            redirect('profile.php');
        }
        $accountErrors = $res['errors'];
    } elseif ($type === 'password') {
        if (($_POST['new_password'] ?? '') !== ($_POST['confirm_password'] ?? '')) {
            $passwordErrors = ['The new passwords do not match.'];
        } else {
            $res = $db->changePassword($userId, $_POST['current_password'] ?? '', $_POST['new_password'] ?? '');
            if ($res['ok']) {
                flash('success', 'Password changed.');
                redirect('profile.php');
            }
            $passwordErrors = $res['errors'];
        }
    }
}

$account = $db->getUserById($userId);

$pageTitle = 'Profile';
$activeNav = 'profile';
require __DIR__ . '/../includes/header.php';
?>
      <header class="page-head">
        <div><h1>Profile</h1><p class="muted">Update your details and password.</p></div>
      </header>

      <div class="stack">
        <section class="panel form-panel">
          <div class="profile-head"><div class="avatar"><?php echo e(initials($account['name'])); ?></div><div><h2><?php echo e($account['name']); ?></h2><p class="muted">Member since <?php echo date('F Y', strtotime($account['created_at'])); ?></p></div></div>
          <?php echo error_list($accountErrors); ?>
          <form method="post" action="profile.php">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="form_type" value="account">
            <label for="name">Full name</label>
            <input id="name" name="name" type="text" maxlength="100" required value="<?php echo e($_POST['name'] ?? $account['name']); ?>">
            <label for="email">Email</label>
            <input id="email" name="email" type="email" maxlength="255" required value="<?php echo e($_POST['email'] ?? $account['email']); ?>">
            <button class="btn btn-sm" type="submit">Save changes</button>
          </form>
        </section>

        <section class="panel form-panel">
          <h2>Change password</h2>
          <?php echo error_list($passwordErrors); ?>
          <form method="post" action="profile.php">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="form_type" value="password">
            <label for="current_password">Current password</label>
            <input id="current_password" name="current_password" type="password" autocomplete="current-password" required>
            <label for="new_password">New password</label>
            <input id="new_password" name="new_password" type="password" autocomplete="new-password" minlength="8" required>
            <label for="confirm_password">Confirm new password</label>
            <input id="confirm_password" name="confirm_password" type="password" autocomplete="new-password" minlength="8" required>
            <button class="btn btn-sm" type="submit">Change password</button>
          </form>
        </section>
      </div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
