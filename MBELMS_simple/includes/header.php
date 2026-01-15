<?php
declare(strict_types=1);
$u = current_user();
$f = flash_get();
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?= e($pageTitle ?? APP_NAME) ?></title>
  <link rel="stylesheet" href="<?= e(BASE_URL) ?>/public/css/style.css" />
</head>
<body>
<header class="topbar">
  <div class="container topbar-inner">
    <div class="brand">
      <a href="<?= e(BASE_URL) ?>/"><?= e(APP_NAME) ?></a>
    </div>
    <nav class="nav">
      <?php if ($u): ?>
        <a href="<?= e(BASE_URL) ?>/pages/dashboard.php" class="<?= $currentPage === 'dashboard.php' ? 'active' : '' ?>">Dashboard</a>
        <a href="<?= e(BASE_URL) ?>/pages/calendar_booking.php" class="<?= $currentPage === 'calendar_booking.php' ? 'active' : '' ?>">🕒 Availability</a>
        <?php if (($u['role'] ?? '') === 'admin'): ?>
          <a href="<?= e(BASE_URL) ?>/admin/machines.php" class="<?= $currentPage === 'machines.php' ? 'active' : '' ?>">Machines</a>
          <a href="<?= e(BASE_URL) ?>/admin/equipment.php" class="<?= $currentPage === 'equipment.php' ? 'active' : '' ?>">Equipment</a>
          <a href="<?= e(BASE_URL) ?>/admin/users.php" class="<?= $currentPage === 'users.php' ? 'active' : '' ?>">Users</a>
          <a href="<?= e(BASE_URL) ?>/admin/bookings.php" class="<?= $currentPage === 'bookings.php' ? 'active' : '' ?>">Bookings</a>
          <a href="<?= e(BASE_URL) ?>/admin/loans.php" class="<?= $currentPage === 'loans.php' ? 'active' : '' ?>">Loans</a>
          <a href="<?= e(BASE_URL) ?>/admin/audit_logs.php" class="<?= $currentPage === 'audit_logs.php' ? 'active' : '' ?>">Audit Logs</a>
        <?php endif; ?>
        <form class="inline" method="post" action="<?= e(BASE_URL) ?>/pages/logout.php">
          <?= csrf_field() ?>
          <button class="btn btn-link" type="submit">Logout (<?= e($u['username']) ?>)</button>
        </form>
      <?php else: ?>
        <a href="<?= e(BASE_URL) ?>/pages/login.php">Login</a>
      <?php endif; ?>
    </nav>
  </div>
</header>

<main class="container">
  <?php if ($f): ?>
    <div class="alert <?= e($f['type']) ?>" id="flash-message" role="alert">
      <?= e($f['message']) ?>
    </div>
  <?php endif; ?>
