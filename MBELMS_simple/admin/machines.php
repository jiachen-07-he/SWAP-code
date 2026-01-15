<?php
require_once __DIR__ . '/../config/bootstrap.php';
require_role('admin');

$conn = db();
$pageTitle = 'Manage Machines - ' . APP_NAME;

if (is_post()) {
    csrf_validate();
    $action = post_str('action', 30);

    if ($action === 'add') {
        $name = post_str('name', 100);
        $location = post_str('location', 100);
        $desc = trim((string)($_POST['description'] ?? ''));

        if ($name === '') {
            flash_set('error', 'Machine name is required.');
            redirect('/admin/machines.php');
        }

        $stmt = $conn->prepare('INSERT INTO machines (name, location, description, is_active) VALUES (?, ?, ?, 1)');
        $stmt->bind_param('sss', $name, $location, $desc);
        $stmt->execute();
        $stmt->close();

        audit_log('admin_add_machine', 'machines', null);
        flash_set('success', 'Machine added.');
        redirect('/admin/machines.php');
    }

    if ($action === 'update') {
        $id = post_int('id');
        $name = post_str('name', 100);
        $location = post_str('location', 100);
        $desc = trim((string)($_POST['description'] ?? ''));

        if ($id <= 0 || $name === '') {
            flash_set('error', 'Invalid update.');
            redirect('/admin/machines.php');
        }

        $stmt = $conn->prepare('UPDATE machines SET name=?, location=?, description=? WHERE id=?');
        $stmt->bind_param('sssi', $name, $location, $desc, $id);
        $stmt->execute();
        $stmt->close();

        audit_log('admin_update_machine', 'machines', $id);
        flash_set('success', 'Machine updated.');
        redirect('/admin/machines.php');
    }

    if ($action === 'toggle') {
        $id = post_int('id');
        $active = post_int('is_active') ? 1 : 0;

        $stmt = $conn->prepare('UPDATE machines SET is_active=? WHERE id=?');
        $stmt->bind_param('ii', $active, $id);
        $stmt->execute();
        $stmt->close();

        audit_log('admin_toggle_machine', 'machines', $id);
        flash_set('success', 'Machine status updated.');
        redirect('/admin/machines.php');
    }

    if ($action === 'delete') {
        $id = post_int('id');
        if ($id <= 0) {
            flash_set('error', 'Invalid delete.');
            redirect('/admin/machines.php');
        }

        $stmt = $conn->prepare('DELETE FROM machines WHERE id=?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();

        audit_log('admin_delete_machine', 'machines', $id);
        flash_set('success', 'Machine deleted.');
        redirect('/admin/machines.php');
    }

    flash_set('error', 'Unknown action.');
    redirect('/admin/machines.php');
}

$editId = int_param('edit', 0);
$editRow = null;

if ($editId > 0) {
    $stmt = $conn->prepare('SELECT * FROM machines WHERE id=?');
    $stmt->bind_param('i', $editId);
    $stmt->execute();
    $editRow = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

$list = $conn->query('SELECT * FROM machines ORDER BY name ASC');

include __DIR__ . '/../includes/header.php';
?>

<div class="card">
  <h2>Manage Machines</h2>
  <p><small>Admin-only CRUD for machine records (proposal: "Admin – Equipment/Machine Record Management").</small></p>
</div>

<div class="grid">
  <div class="card">
    <h3><?= $editRow ? 'Edit Machine' : 'Add Machine' ?></h3>

    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="<?= $editRow ? 'update' : 'add' ?>" />
      <?php if ($editRow): ?>
        <input type="hidden" name="id" value="<?= (int)$editRow['id'] ?>" />
      <?php endif; ?>

      <div style="margin-bottom:12px;">
        <label>Name</label>
        <input name="name" required maxlength="100" value="<?= e($editRow['name'] ?? '') ?>" />
      </div>

      <div style="margin-bottom:12px;">
        <label>Location</label>
        <input name="location" maxlength="100" value="<?= e($editRow['location'] ?? '') ?>" />
      </div>

      <div style="margin-bottom:12px;">
        <label>Description</label>
        <textarea name="description"><?= e($editRow['description'] ?? '') ?></textarea>
      </div>

      <button class="btn btn-primary" type="submit"><?= $editRow ? 'Save changes' : 'Add machine' ?></button>
      <?php if ($editRow): ?>
        <a class="btn btn-secondary" href="<?= e(BASE_URL) ?>/admin/machines.php" style="margin-left:8px;">Cancel</a>
      <?php endif; ?>
    </form>
  </div>

  <div class="card">
    <h3>Machine List</h3>

    <table class="table">
      <thead>
        <tr><th>Name</th><th>Status</th><th>Actions</th></tr>
      </thead>
      <tbody>
      <?php while($r = $list->fetch_assoc()): ?>
        <tr>
          <td>
            <b><?= e($r['name']) ?></b><br>
            <small><?= e($r['location'] ?? '') ?></small>
          </td>
          <td><?= ((int)$r['is_active'] === 1) ? '<span class="badge">active</span>' : '<span class="badge">inactive</span>' ?></td>
          <td>
            <a class="btn btn-secondary" href="<?= e(BASE_URL) ?>/admin/machines.php?edit=<?= (int)$r['id'] ?>">Edit</a>

            <form method="post" class="inline" style="margin-left:6px;">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="toggle" />
              <input type="hidden" name="id" value="<?= (int)$r['id'] ?>" />
              <input type="hidden" name="is_active" value="<?= ((int)$r['is_active'] === 1) ? 0 : 1 ?>" />
              <button class="btn btn-primary" type="submit">
                <?= ((int)$r['is_active'] === 1) ? 'Deactivate' : 'Activate' ?>
              </button>
            </form>

            <form method="post" class="inline" style="margin-left:6px;" onsubmit="return confirmAction('Delete machine: <?= e($r['name']) ?> ?')">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="delete" />
              <input type="hidden" name="id" value="<?= (int)$r['id'] ?>" />
              <button class="btn btn-danger" type="submit">Delete</button>
            </form>
          </td>
        </tr>
      <?php endwhile; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
