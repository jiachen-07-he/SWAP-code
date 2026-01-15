<?php
require_once __DIR__ . '/../config/bootstrap.php';

$pageTitle = 'Login - ' . APP_NAME;

if (is_logged_in()) {
  redirect('/pages/dashboard.php');
}

if (is_post()) {
  csrf_validate();

  $username = post_str('username', 50);
  $password = (string) ($_POST['password'] ?? '');

  if ($username === '' || $password === '') {
    flash_set('error', 'Username and password are required.');
    redirect('/pages/login.php');
  }

  // For showing lock/attempts info
  $conn = db();
  $ip = client_ip();
  $status = bruteforce_status($conn, $username, $ip);

  // If already locked, show message and stop
  if (!empty($status['locked_until']) && strtotime($status['locked_until']) > time()) {
    audit_log('login_locked', 'users', null);
    flash_set('error', 'Too many failed attempts. Locked until ' . $status['locked_until'] . '.');
    redirect('/pages/login.php');
  }

  if (auth_login($username, $password)) {
    audit_log('login_success', 'users', current_user()['id'] ?? null);
    flash_set('success', 'Welcome, ' . $username . '!');
    redirect('/pages/dashboard.php');
  }

  // After a failed login, fetch status again (it has just incremented)
  $status = bruteforce_status($conn, $username, $ip);
  $left = bruteforce_attempts_left($status['attempts']);

  audit_log('login_failed', 'users', null);

  if (!empty($status['locked_until']) && strtotime($status['locked_until']) > time()) {
    flash_set('error', 'Too many failed attempts. Locked until ' . $status['locked_until'] . '.');
  } else {
    flash_set('error', 'Invalid username or password. Attempts left: ' . $left . '.');
  }

  redirect('/pages/login.php');
}

/*
if (is_post()) {
  csrf_validate();

  $username = post_str('username', 50);
  $password = (string) ($_POST['password'] ?? '');
  $status = bruteforce_status($conn, $username, $ip);

  if ($username === '' || $password === '') {
    flash_set('error', 'Username and password are required.');
    redirect('/pages/login.php');
  }


if (auth_login($username, $password)) {
  audit_log('login_success', 'users', current_user()['id'] ?? null);
  flash_set('success', 'Welcome, ' . $username . '!');
  redirect('/pages/dashboard.php');
}

audit_log('login_failed', 'users', null);
flash_set('error', 'Invalid username or password.');
redirect('/pages/login.php');
}
*/
include __DIR__ . '/../includes/header.php';
?>

<div class="card" style="max-width:520px;margin:24px auto;">
  <h2>Login</h2>

  <form method="post" autocomplete="off">
    <?= csrf_field() ?>
    <div style="margin-bottom:12px;">
      <label for="username">Username</label>
      <input id="username" name="username" type="text" maxlength="50" required />
    </div>
    <div style="margin-bottom:12px;">
      <label for="password">Password</label>
      <input id="password" name="password" type="password" required />
    </div>
    <button class="btn btn-primary" type="submit">Login</button>
  </form>

  <div style="margin-top:16px;text-align:center;">
    <a href="<?= e(BASE_URL) ?>/pages/reset_password.php">Forgot your password?</a>
  </div>

  <hr style="border:none;border-top:1px solid #e5e7eb;margin:20px 0;">

  <div style="text-align:center;">
    <p style="margin-bottom:8px;">Don't have an account? <a href="<?= e(BASE_URL) ?>/pages/signup.php">Sign Up</a></p>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>