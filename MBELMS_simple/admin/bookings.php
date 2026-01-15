<?php
require_once __DIR__ . '/../config/bootstrap.php';
require_role('admin');

$conn = db();
$pageTitle = 'All Bookings - ' . APP_NAME;

if (is_post()) {
    csrf_validate();
    $action = post_str('action', 30);

    if ($action === 'cancel') {
        $id = post_int('id');
        $stmt = $conn->prepare("UPDATE machine_bookings SET status='cancelled' WHERE id=? AND status='active'");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();

        audit_log('admin_cancel_booking', 'machine_bookings', $id);
        flash_set('success', 'Booking cancelled (if it was active).');
        redirect('/admin/bookings.php');
    }

    flash_set('error', 'Unknown action.');
    redirect('/admin/bookings.php');
}

$list = $conn->query("
  SELECT b.id, b.status, b.created_at,
         m.name AS machine_name,
         u.username
  FROM machine_bookings b
  JOIN machines m ON m.id = b.machine_id
  JOIN users u ON u.id = b.user_id
  ORDER BY b.created_at DESC
  LIMIT 200
");

include __DIR__ . '/../includes/header.php';
?>

<div class="card">
  <h2>All Machine Bookings</h2>
  <p><small>Admin can view/cancel system-wide bookings.</small></p>
</div>

<div class="card">
  <table class="table">
    <thead><tr><th>Machine</th><th>User</th><th>Status</th><th>Created</th><th>Action</th></tr></thead>
    <tbody>
      <?php while($r = $list->fetch_assoc()): ?>
        <tr>
          <td><?= e($r['machine_name']) ?></td>
          <td><?= e($r['username']) ?></td>
          <td><span class="badge"><?= e($r['status']) ?></span></td>
          <td><?= e($r['created_at']) ?></td>
          <td>
            <?php if ($r['status'] === 'active'): ?>
              <form method="post" class="inline" onsubmit="return confirmAction('Cancel this booking?')">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="cancel" />
                <input type="hidden" name="id" value="<?= (int)$r['id'] ?>" />
                <button class="btn btn-danger" type="submit">Cancel</button>
              </form>
            <?php else: ?>
              <small>-</small>
            <?php endif; ?>
          </td>
        </tr>
      <?php endwhile; ?>
    </tbody>
  </table>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
