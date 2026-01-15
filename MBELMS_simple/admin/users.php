<?php
require_once __DIR__ . '/../config/bootstrap.php';
require_role('admin');

$conn = db();
$pageTitle = 'Manage Users - ' . APP_NAME;

function valid_username(string $u): bool {
    return (bool)preg_match('/^[a-zA-Z0-9_]{3,50}$/', $u);
}

if (is_post()) {
    csrf_validate();
    $action = post_str('action', 30);

    if ($action === 'create') {
        $username = post_str('username', 50);
        $password = (string)($_POST['password'] ?? '');
        $role = (string)($_POST['role'] ?? 'staff');

        if (!valid_username($username)) {
            flash_set('error', 'Username must be 3-50 chars: letters, numbers, underscore only.');
            redirect('/admin/users.php');
        }
        if (strlen($password) < 8) {
            flash_set('error', 'Password must be at least 8 characters.');
            redirect('/admin/users.php');
        }
        if ($role !== 'admin' && $role !== 'staff' && $role !== 'user') {
            flash_set('error', 'Invalid role.');
            redirect('/admin/users.php');
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);

        try {
            $stmt = $conn->prepare('INSERT INTO users (username, password_hash, role) VALUES (?, ?, ?)');
            $stmt->bind_param('sss', $username, $hash, $role);
            $stmt->execute();
            $stmt->close();

            audit_log('admin_create_user', 'users', null);
            flash_set('success', 'User created.');
        } catch (mysqli_sql_exception $e) {
            flash_set('error', 'Unable to create user (maybe username already exists).');
        }
        redirect('/admin/users.php');
    }

    if ($action === 'delete') {
        $id = post_int('id');
        $me = current_user();
        if ($id <= 0 || ($me && (int)$me['id'] === $id)) {
            flash_set('error', 'Invalid delete (you cannot delete yourself).');
            redirect('/admin/users.php');
        }

        $stmt = $conn->prepare('DELETE FROM users WHERE id=?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();

        audit_log('admin_delete_user', 'users', $id);
        flash_set('success', 'User deleted.');
        redirect('/admin/users.php');
    }

    flash_set('error', 'Unknown action.');
    redirect('/admin/users.php');
}

$list = $conn->query("SELECT id, username, role, created_at FROM users ORDER BY created_at DESC");

include __DIR__ . '/../includes/header.php';
?>

<div class="card">
  <h2>Manage Users</h2>
  <p><small>Admin can create/delete accounts (proposal: "Admin – User Account Management").</small></p>
</div>

<div class="grid">
  <div class="card">
    <h3>Create User</h3>

    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="create" />

      <div style="margin-bottom:12px;">
        <label>Username</label>
        <input name="username" required maxlength="50" placeholder="e.g. amc_staff01" />
        <small>Allowed: letters, numbers, underscore. 3-50 chars.</small>
      </div>

      <div style="margin-bottom:12px;">
        <label>Password</label>
        <input name="password" type="password" required />
        <small>Minimum 8 characters.</small>
      </div>

      <div style="margin-bottom:12px;">
        <label>Role</label>
        <select name="role">
          <option value="user">user (limited access)</option>
          <option value="staff">staff</option>
          <option value="admin">admin</option>
        </select>
      </div>

      <button class="btn btn-primary" type="submit">Create</button>
    </form>
  </div>

  <div class="card">
    <h3>User List</h3>

    <table class="table">
      <thead><tr><th>Username</th><th>Role</th><th>Created</th><th>Action</th></tr></thead>
      <tbody>
        <?php while($r = $list->fetch_assoc()): ?>
          <tr>
            <td><?= e($r['username']) ?></td>
            <td><span class="badge"><?= e($r['role']) ?></span></td>
            <td><?= e($r['created_at']) ?></td>
            <td>
              <?php if ((int)$r['id'] !== (int)current_user()['id']): ?>
                <form method="post" class="inline" onsubmit="return confirmAction('Delete user: <?= e($r['username']) ?> ?')">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="delete" />
                  <input type="hidden" name="id" value="<?= (int)$r['id'] ?>" />
                  <button class="btn btn-danger" type="submit">Delete</button>
                </form>
              <?php else: ?>
                <small>(you)</small>
              <?php endif; ?>
            </td>
          </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
