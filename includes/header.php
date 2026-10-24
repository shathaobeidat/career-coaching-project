<?php

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?php echo e($pageTitle); ?> | <?php echo e($areaConfig['title']); ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:wght@600;700&family=Figtree:wght@400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../style.css">
</head>
<body>
  <div class="app">
    <nav class="sidebar" aria-label="Main">
      <a class="brand" href="<?php echo e($areaConfig['home']); ?>"><span class="brand-mark"></span>CareerPath</a>
      <?php foreach ($areaConfig['nav'] as $key => [$href, $label]): ?>
        <a class="nav-link<?php echo $activeNav === $key ? ' active' : ''; ?>" href="<?php echo e($href); ?>"<?php echo $activeNav === $key ? ' aria-current="page"' : ''; ?>><?php echo e($label); ?></a>
      <?php endforeach; ?>
      <a class="nav-link logout" href="../auth/logout.php">Log out</a>
    </nav>

    <main class="content<?php echo $areaConfig['wide'] ? ' wide' : ''; ?>">
      <?php foreach (flash_pull() as $f): ?>
        <div class="alert alert-<?php echo e($f['type']); ?>" role="status"><?php echo e($f['message']); ?></div>
      <?php endforeach; ?>

      <?php if ($areaConfig['role'] === 'mentor'): ?>
        <?php if (!$profile && $activeNav !== 'profile'): ?>
          <div class="alert alert-info">Your coach profile is not set up yet. <a href="coach-profile.php">Complete it now</a> so mentees can find you.</div>
        <?php elseif ($profile && $profile['status'] === 'pending'): ?>
          <div class="alert alert-info">Your profile is <strong>pending approval</strong>. You can set up services and availability now; mentees will see you once an admin approves it.</div>
        <?php elseif ($profile && $profile['status'] === 'rejected'): ?>
          <div class="alert alert-error">Your profile was <strong>rejected</strong>. <a href="coach-profile.php">Update it</a> to send it for review again.</div>
        <?php endif; ?>
      <?php endif; ?>
