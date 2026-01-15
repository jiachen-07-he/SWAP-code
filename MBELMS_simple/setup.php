<?php
require_once __DIR__ . '/config/bootstrap.php';

$conn = db();

// If users already exist, block setup
$res = $conn->query('SELECT COUNT(*) AS c FROM users');
$count = (int)$res->fetch_assoc()['c'];

$pageTitle = 'Setup - ' . APP_NAME;
include __DIR__ . '/includes/header.php';

if ($count > 0) {
    echo '<div class="card"><h2>Setup already completed</h2><p>Users already exist in the database. For safety, setup is disabled.</p></div>';
    include __DIR__ . '/includes/footer.php';
    exit;
}

if (is_post()) {
    csrf_validate();

    $adminPass = 'Admin123!';
    $staffPass = 'Staff123!';

    $adminHash = password_hash($adminPass, PASSWORD_DEFAULT);
    $staffHash = password_hash($staffPass, PASSWORD_DEFAULT);

    $stmt = $conn->prepare('INSERT INTO users (username, password_hash, role) VALUES (?, ?, ?)');
    $u1 = 'admin'; $r1 = 'admin';
    $stmt->bind_param('sss', $u1, $adminHash, $r1);
    $stmt->execute();

    $u2 = 'staff'; $r2 = 'staff';
    $stmt->bind_param('sss', $u2, $staffHash, $r2);
    $stmt->execute();

    $stmt->close();

    audit_log('setup_created_demo_users', 'users', null);

    flash_set('success', 'Setup complete! Demo users created: admin/Admin123! and staff/Staff123!.');
    redirect('/pages/login.php');
}
?>

<div class="card">
  <h2>First-run Setup</h2>
  <p>This will create two demo accounts (for development/testing):</p>
  <ul>
    <li><b>admin</b> / Admin123!</li>
    <li><b>staff</b> / Staff123!</li>
  </ul>
  <form method="post">
    <?= csrf_field() ?>
    <button class="btn btn-primary" type="submit" onclick="return confirmAction('Create demo users now?')">Create demo users</button>
  </form>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
