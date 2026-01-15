<?php
require_once __DIR__ . '/../config/bootstrap.php';
require_role('admin');

$conn = db();
$pageTitle = 'Audit Logs - ' . APP_NAME;

$list = $conn->query("
  SELECT a.id, a.action, a.entity, a.entity_id, a.ip_address, a.created_at,
         u.username
  FROM audit_logs a
  LEFT JOIN users u ON u.id = a.user_id
  ORDER BY a.created_at DESC
  LIMIT 200
");

include __DIR__ . '/../includes/header.php';
?>

<div class="card">
  <h2>Audit Logs</h2>
  <p><small>Shows the latest 200 recorded actions for traceability.</small></p>
</div>

<div class="card">
  <table class="table">
    <thead><tr><th>Time</th><th>User</th><th>Action</th><th>Entity</th><th>IP</th></tr></thead>
    <tbody>
      <?php while($r = $list->fetch_assoc()): ?>
        <tr>
          <td><?= e($r['created_at']) ?></td>
          <td><?= e($r['username'] ?? '-') ?></td>
          <td><?= e($r['action']) ?></td>
          <td><?= e(($r['entity'] ?? '-') . (isset($r['entity_id']) ? (' #' . $r['entity_id']) : '')) ?></td>
          <td><?= e($r['ip_address'] ?? '-') ?></td>
        </tr>
      <?php endwhile; ?>
    </tbody>
  </table>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
