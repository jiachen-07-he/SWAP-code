<?php
require_once __DIR__ . '/../config/bootstrap.php';
require_role('admin');

$conn = db();
$pageTitle = 'Manage Equipment - ' . APP_NAME;

if (is_post()) {
    csrf_validate();
    $action = post_str('action', 30);

    if ($action === 'add') {
        $name = post_str('name', 100);
        $serial = post_str('serial_no', 100);
        $desc = trim((string)($_POST['description'] ?? ''));

        if ($name === '') {
            flash_set('error', 'Equipment name is required.');
            redirect('/admin/equipment.php');
        }

        $stmt = $conn->prepare('INSERT INTO equipment (name, serial_no, description, is_active) VALUES (?, ?, ?, 1)');
        $stmt->bind_param('sss', $name, $serial, $desc);
        $stmt->execute();
        $stmt->close();

        audit_log('admin_add_equipment', 'equipment', null);
        flash_set('success', 'Equipment added.');
        redirect('/admin/equipment.php');
    }

    if ($action === 'update') {
        $id = post_int('id');
        $name = post_str('name', 100);
        $serial = post_str('serial_no', 100);
        $desc = trim((string)($_POST['description'] ?? ''));

        if ($id <= 0 || $name === '') {
            flash_set('error', 'Invalid update.');
            redirect('/admin/equipment.php');
        }

        $stmt = $conn->prepare('UPDATE equipment SET name=?, serial_no=?, description=? WHERE id=?');
        $stmt->bind_param('sssi', $name, $serial, $desc, $id);
        $stmt->execute();
        $stmt->close();

        audit_log('admin_update_equipment', 'equipment', $id);
        flash_set('success', 'Equipment updated.');
        redirect('/admin/equipment.php');
    }

    if ($action === 'toggle') {
        $id = post_int('id');
        $active = post_int('is_active') ? 1 : 0;

        $stmt = $conn->prepare('UPDATE equipment SET is_active=? WHERE id=?');
        $stmt->bind_param('ii', $active, $id);
        $stmt->execute();
        $stmt->close();

        audit_log('admin_toggle_equipment', 'equipment', $id);
        flash_set('success', 'Equipment status updated.');
        redirect('/admin/equipment.php');
    }

    if ($action === 'delete') {
        $id = post_int('id');
        if ($id <= 0) {
            flash_set('error', 'Invalid delete.');
            redirect('/admin/equipment.php');
        }

        $stmt = $conn->prepare('DELETE FROM equipment WHERE id=?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();

        audit_log('admin_delete_equipment', 'equipment', $id);
        flash_set('success', 'Equipment deleted.');
        redirect('/admin/equipment.php');
    }

    flash_set('error', 'Unknown action.');
    redirect('/admin/equipment.php');
}

$editId = int_param('edit', 0);
$editRow = null;

if ($editId > 0) {
    $stmt = $conn->prepare('SELECT * FROM equipment WHERE id=?');
    $stmt->bind_param('i', $editId);
    $stmt->execute();
    $editRow = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

$list = $conn->query('SELECT * FROM equipment ORDER BY name ASC');

include __DIR__ . '/../includes/header.php';
?>

<div class="card">
  <h2>Manage Equipment</h2>
  <p><small>Admin-only CRUD for equipment records.</small></p>
</div>

<div class="grid">
  <div class="card">
    <h3><?= $editRow ? 'Edit Equipment' : 'Add Equipment' ?></h3>

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
        <label>Serial No</label>
        <input name="serial_no" maxlength="100" value="<?= e($editRow['serial_no'] ?? '') ?>" />
      </div>

      <div style="margin-bottom:12px;">
        <label>Description</label>
        <textarea name="description"><?= e($editRow['description'] ?? '') ?></textarea>
      </div>

      <button class="btn btn-primary" type="submit"><?= $editRow ? 'Save changes' : 'Add equipment' ?></button>
      <?php if ($editRow): ?>
        <a class="btn btn-secondary" href="<?= e(BASE_URL) ?>/admin/equipment.php" style="margin-left:8px;">Cancel</a>
      <?php endif; ?>
    </form>
  </div>

  <div class="card">
    <h3>Equipment List</h3>

    <table class="table">
      <thead>
        <tr><th>Name</th><th>Status</th><th>Actions</th></tr>
      </thead>
      <tbody>
      <?php while($r = $list->fetch_assoc()): ?>
        <tr>
          <td>
            <b><?= e($r['name']) ?></b><br>
            <small><?= e($r['serial_no'] ?? '') ?></small>
          </td>
          <td><?= ((int)$r['is_active'] === 1) ? '<span class="badge">active</span>' : '<span class="badge">inactive</span>' ?></td>
          <td>
            <a class="btn btn-secondary" href="<?= e(BASE_URL) ?>/admin/equipment.php?edit=<?= (int)$r['id'] ?>">Edit</a>

            <form method="post" class="inline" style="margin-left:6px;">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="toggle" />
              <input type="hidden" name="id" value="<?= (int)$r['id'] ?>" />
              <input type="hidden" name="is_active" value="<?= ((int)$r['is_active'] === 1) ? 0 : 1 ?>" />
              <button class="btn btn-primary" type="submit">
                <?= ((int)$r['is_active'] === 1) ? 'Deactivate' : 'Activate' ?>
              </button>
            </form>

            <form method="post" class="inline" style="margin-left:6px;" onsubmit="return confirmAction('Delete equipment: <?= e($r['name']) ?> ?')">
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
