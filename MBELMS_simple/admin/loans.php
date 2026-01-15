<?php
require_once __DIR__ . '/../config/bootstrap.php';
require_role('admin');

$conn = db();
$pageTitle = 'All Loans - ' . APP_NAME;

if (is_post()) {
    csrf_validate();
    $action = post_str('action', 30);

    if ($action === 'mark_returned') {
        $id = post_int('id');
        $stmt = $conn->prepare("UPDATE equipment_loans SET status='returned', returned_at=NOW() WHERE id=? AND status='active'");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();

        audit_log('admin_mark_loan_returned', 'equipment_loans', $id);
        flash_set('success', 'Loan marked returned (if it was active).');
        redirect('/admin/loans.php');
    }

    flash_set('error', 'Unknown action.');
    redirect('/admin/loans.php');
}

$list = $conn->query("
  SELECT l.id, l.status, l.borrowed_at, l.returned_at,
         e.name AS equipment_name,
         u.username
  FROM equipment_loans l
  JOIN equipment e ON e.id = l.equipment_id
  JOIN users u ON u.id = l.user_id
  ORDER BY l.borrowed_at DESC
  LIMIT 200
");

include __DIR__ . '/../includes/header.php';
?>

<div class="card">
  <h2>All Equipment Loans</h2>
  <p><small>Admin can view and mark returns for system-wide loans.</small></p>
</div>

<div class="card">
  <table class="table">
    <thead><tr><th>Equipment</th><th>User</th><th>Status</th><th>Borrowed</th><th>Returned</th><th>Action</th></tr></thead>
    <tbody>
      <?php while($r = $list->fetch_assoc()): ?>
        <tr>
          <td><?= e($r['equipment_name']) ?></td>
          <td><?= e($r['username']) ?></td>
          <td><span class="badge"><?= e($r['status']) ?></span></td>
          <td><?= e($r['borrowed_at']) ?></td>
          <td><?= e($r['returned_at'] ?? '-') ?></td>
          <td>
            <?php if ($r['status'] === 'active'): ?>
              <form method="post" class="inline" onsubmit="return confirmAction('Mark returned?')">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="mark_returned" />
                <input type="hidden" name="id" value="<?= (int)$r['id'] ?>" />
                <button class="btn btn-secondary" type="submit">Mark returned</button>
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
