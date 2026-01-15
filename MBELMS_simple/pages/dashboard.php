<?php
require_once __DIR__ . '/../config/bootstrap.php';
require_login();

$conn = db();
$u = current_user();
$isAdmin = (($u['role'] ?? '') === 'admin');

$pageTitle = 'Dashboard - ' . APP_NAME;

// Handle actions
if (is_post()) {
    csrf_validate();

    $action = post_str('action', 40);

    if ($action === 'book_machine') {
        $machineId = post_int('machine_id');
        if ($machineId <= 0) {
            flash_set('error', 'Please select a machine.');
            redirect('/pages/dashboard.php');
        }

        // Ensure machine active
        $stmt = $conn->prepare('SELECT id FROM machines WHERE id = ? AND is_active = 1');
        $stmt->bind_param('i', $machineId);
        $stmt->execute();
        $m = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$m) {
            flash_set('error', 'Invalid machine.');
            redirect('/pages/dashboard.php');
        }

        // Check no active booking for this machine
        $stmt = $conn->prepare("SELECT id FROM machine_bookings WHERE machine_id = ? AND status = 'active' LIMIT 1");
        $stmt->bind_param('i', $machineId);
        $stmt->execute();
        $exists = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($exists) {
            flash_set('error', 'This machine is already booked.');
            redirect('/pages/dashboard.php');
        }

        $stmt = $conn->prepare('INSERT INTO machine_bookings (machine_id, user_id, status) VALUES (?, ?, "active")');
        $uid = (int)$u['id'];
        $stmt->bind_param('ii', $machineId, $uid);
        $stmt->execute();
        $stmt->close();

        audit_log('book_machine', 'machine_bookings', null);
        flash_set('success', 'Machine booked successfully.');
        redirect('/pages/dashboard.php');
    }

    if ($action === 'cancel_booking') {
        $bookingId = post_int('booking_id');
        if ($bookingId <= 0) {
            flash_set('error', 'Invalid booking.');
            redirect('/pages/dashboard.php');
        }

        // Staff can only cancel own booking; admin can cancel any
        if ($isAdmin) {
            $stmt = $conn->prepare("UPDATE machine_bookings SET status='cancelled' WHERE id = ? AND status='active'");
            $stmt->bind_param('i', $bookingId);
        } else {
            $uid = (int)$u['id'];
            $stmt = $conn->prepare("UPDATE machine_bookings SET status='cancelled' WHERE id = ? AND user_id = ? AND status='active'");
            $stmt->bind_param('ii', $bookingId, $uid);
        }
        $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();

        if ($affected > 0) {
            audit_log('cancel_booking', 'machine_bookings', $bookingId);
            flash_set('success', 'Booking cancelled.');
        } else {
            flash_set('error', 'Unable to cancel booking (maybe not yours or already cancelled).');
        }
        redirect('/pages/dashboard.php');
    }

    if ($action === 'borrow_equipment') {
        $equipmentId = post_int('equipment_id');
        if ($equipmentId <= 0) {
            flash_set('error', 'Please select equipment.');
            redirect('/pages/dashboard.php');
        }

        // Ensure equipment active
        $stmt = $conn->prepare('SELECT id FROM equipment WHERE id = ? AND is_active = 1');
        $stmt->bind_param('i', $equipmentId);
        $stmt->execute();
        $e = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$e) {
            flash_set('error', 'Invalid equipment.');
            redirect('/pages/dashboard.php');
        }

        // Check no active loan for this equipment
        $stmt = $conn->prepare("SELECT id FROM equipment_loans WHERE equipment_id = ? AND status = 'active' LIMIT 1");
        $stmt->bind_param('i', $equipmentId);
        $stmt->execute();
        $exists = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($exists) {
            flash_set('error', 'This equipment is currently on loan.');
            redirect('/pages/dashboard.php');
        }

        $stmt = $conn->prepare('INSERT INTO equipment_loans (equipment_id, user_id, status) VALUES (?, ?, "active")');
        $uid = (int)$u['id'];
        $stmt->bind_param('ii', $equipmentId, $uid);
        $stmt->execute();
        $stmt->close();

        audit_log('borrow_equipment', 'equipment_loans', null);
        flash_set('success', 'Equipment borrowed successfully.');
        redirect('/pages/dashboard.php');
    }

    if ($action === 'return_equipment') {
        $loanId = post_int('loan_id');
        if ($loanId <= 0) {
            flash_set('error', 'Invalid loan.');
            redirect('/pages/dashboard.php');
        }

        // Staff can only return own loan; admin can mark returned for any
        if ($isAdmin) {
            $stmt = $conn->prepare("UPDATE equipment_loans SET status='returned', returned_at = NOW() WHERE id = ? AND status='active'");
            $stmt->bind_param('i', $loanId);
        } else {
            $uid = (int)$u['id'];
            $stmt = $conn->prepare("UPDATE equipment_loans SET status='returned', returned_at = NOW() WHERE id = ? AND user_id = ? AND status='active'");
            $stmt->bind_param('ii', $loanId, $uid);
        }
        $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();

        if ($affected > 0) {
            audit_log('return_equipment', 'equipment_loans', $loanId);
            flash_set('success', 'Equipment returned.');
        } else {
            flash_set('error', 'Unable to return (maybe not yours or already returned).');
        }
        redirect('/pages/dashboard.php');
    }

    flash_set('error', 'Unknown action.');
    redirect('/pages/dashboard.php');
}

// Data for dropdowns
$machines = $conn->query("
    SELECT m.id, m.name
    FROM machines m
    WHERE m.is_active = 1
      AND NOT EXISTS (
        SELECT 1 FROM machine_bookings b WHERE b.machine_id = m.id AND b.status = 'active'
      )
    ORDER BY m.name ASC
");

$equipment = $conn->query("
    SELECT e.id, e.name
    FROM equipment e
    WHERE e.is_active = 1
      AND NOT EXISTS (
        SELECT 1 FROM equipment_loans l WHERE l.equipment_id = e.id AND l.status = 'active'
      )
    ORDER BY e.name ASC
");

// My active bookings & loans
$uid = (int)$u['id'];
$stmt = $conn->prepare("
    SELECT b.id, m.name, b.created_at
    FROM machine_bookings b
    JOIN machines m ON m.id = b.machine_id
    WHERE b.user_id = ? AND b.status = 'active'
    ORDER BY b.created_at DESC
");
$stmt->bind_param('i', $uid);
$stmt->execute();
$myBookings = $stmt->get_result();
$stmt->close();

$stmt = $conn->prepare("
    SELECT l.id, e.name, l.borrowed_at
    FROM equipment_loans l
    JOIN equipment e ON e.id = l.equipment_id
    WHERE l.user_id = ? AND l.status = 'active'
    ORDER BY l.borrowed_at DESC
");
$stmt->bind_param('i', $uid);
$stmt->execute();
$myLoans = $stmt->get_result();
$stmt->close();

include __DIR__ . '/../includes/header.php';
?>

<div class="card">
  <div style="display:flex; justify-content:space-between; align-items:flex-start;">
    <h2 style="margin:0;">Dashboard</h2>
    <a href="<?= e(BASE_URL) ?>/pages/change_password.php" class="btn btn-secondary">Change Password</a>
  </div>
  <p style="margin-top:12px;">
    Logged in as <b><?= e($u['username']) ?></b>
  </p>
  <p style="margin-top:8px;"><small>Note: You'll need to answer your security question to change your password. <a href="<?= e(BASE_URL) ?>/pages/security_question.php">Update security question</a></small></p>

  <?php if ($isAdmin): ?>
    <p style="margin-top:12px;"><small>Admin view: use the top navigation to manage machines/equipment/users and to view system-wide bookings/loans.</small></p>
  <?php elseif (($u['role'] ?? '') === 'staff'): ?>
    <p style="margin-top:12px;"><small>Staff view: book machines and borrow/return equipment. You can only cancel/return your own records.</small></p>
  <?php else: ?>
    <p style="margin-top:12px;"><small>User view: book machines, borrow equipment, and check availability. Use the Availability page for time-slot bookings.</small></p>
  <?php endif; ?>
</div>

<div class="grid">
  <div class="card">
    <h3>Machine Booking</h3>
    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="book_machine" />
      <label for="machine_id">Select machine (available only)</label>
      <select id="machine_id" name="machine_id" required>
        <option value="">-- Choose --</option>
        <?php while($m = $machines->fetch_assoc()): ?>
          <option value="<?= (int)$m['id'] ?>"><?= e($m['name']) ?></option>
        <?php endwhile; ?>
      </select>
      <div style="margin-top:12px;">
        <button class="btn btn-primary" type="submit">Book machine</button>
      </div>
    </form>

    <hr style="border:none;border-top:1px solid #e5e7eb;margin:16px 0;"/>

    <h4>My active bookings</h4>
    <?php if ($myBookings->num_rows === 0): ?>
      <p><small>No active bookings.</small></p>
    <?php else: ?>
      <table class="table">
        <thead><tr><th>Machine</th><th>Booked at</th><th>Action</th></tr></thead>
        <tbody>
        <?php while($b = $myBookings->fetch_assoc()): ?>
          <tr>
            <td><?= e($b['name']) ?></td>
            <td><?= e($b['created_at']) ?></td>
            <td>
              <form method="post" class="inline" onsubmit="return confirmAction('Cancel this booking?')">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="cancel_booking" />
                <input type="hidden" name="booking_id" value="<?= (int)$b['id'] ?>" />
                <button class="btn btn-danger" type="submit">Cancel</button>
              </form>
            </td>
          </tr>
        <?php endwhile; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>

  <div class="card">
    <h3>Equipment Loan / Return</h3>
    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="borrow_equipment" />
      <label for="equipment_id">Select equipment (available only)</label>
      <select id="equipment_id" name="equipment_id" required>
        <option value="">-- Choose --</option>
        <?php while($e = $equipment->fetch_assoc()): ?>
          <option value="<?= (int)$e['id'] ?>"><?= e($e['name']) ?></option>
        <?php endwhile; ?>
      </select>
      <div style="margin-top:12px;">
        <button class="btn btn-primary" type="submit">Borrow equipment</button>
      </div>
    </form>

    <hr style="border:none;border-top:1px solid #e5e7eb;margin:16px 0;"/>

    <h4>My active loans</h4>
    <?php if ($myLoans->num_rows === 0): ?>
      <p><small>No active loans.</small></p>
    <?php else: ?>
      <table class="table">
        <thead><tr><th>Equipment</th><th>Borrowed at</th><th>Action</th></tr></thead>
        <tbody>
        <?php while($l = $myLoans->fetch_assoc()): ?>
          <tr>
            <td><?= e($l['name']) ?></td>
            <td><?= e($l['borrowed_at']) ?></td>
            <td>
              <form method="post" class="inline" onsubmit="return confirmAction('Return this equipment?')">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="return_equipment" />
                <input type="hidden" name="loan_id" value="<?= (int)$l['id'] ?>" />
                <button class="btn btn-secondary" type="submit">Return</button>
              </form>
            </td>
          </tr>
        <?php endwhile; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
