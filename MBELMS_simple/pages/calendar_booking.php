<?php
require_once __DIR__ . '/../config/bootstrap.php';
require_login();

$conn = db();
$pageTitle = 'Machine Availability - ' . APP_NAME;

// Get selected machine (if any)
$selectedMachineId = (int)($_GET['machine_id'] ?? 0);
$selectedDate = $_GET['date'] ?? date('Y-m-d');

// Validate date format
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $selectedDate)) {
    $selectedDate = date('Y-m-d');
}

// Handle booking submission
if (is_post()) {
    csrf_validate();

    $machineId = post_int('machine_id');
    $bookingDate = post_str('booking_date', 20);
    $startTime = post_str('start_time', 10);
    $endTime = post_str('end_time', 10);

    if ($machineId <= 0 || $bookingDate === '' || $startTime === '' || $endTime === '') {
        flash_set('error', 'All fields are required.');
        redirect('/pages/calendar_booking.php');
    }

    // Validate time format
    if (!preg_match('/^\d{2}:\d{2}$/', $startTime) || !preg_match('/^\d{2}:\d{2}$/', $endTime)) {
        flash_set('error', 'Invalid time format.');
        redirect('/pages/calendar_booking.php');
    }

    // Create datetime strings
    $startDateTime = $bookingDate . ' ' . $startTime . ':00';
    $endDateTime = $bookingDate . ' ' . $endTime . ':00';

    // Validate end time is after start time
    if (strtotime($endDateTime) <= strtotime($startDateTime)) {
        flash_set('error', 'End time must be after start time.');
        redirect('/pages/calendar_booking.php?machine_id=' . $machineId . '&date=' . $bookingDate);
    }

    // Check if machine exists and is active
    $stmt = $conn->prepare('SELECT id, name FROM machines WHERE id = ? AND is_active = 1');
    $stmt->bind_param('i', $machineId);
    $stmt->execute();
    $machine = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$machine) {
        flash_set('error', 'Invalid machine.');
        redirect('/pages/calendar_booking.php');
    }

    // Check for overlapping bookings
    $stmt = $conn->prepare("
        SELECT id FROM machine_bookings
        WHERE machine_id = ?
          AND status = 'active'
          AND (
            (start_time <= ? AND end_time > ?) OR
            (start_time < ? AND end_time >= ?) OR
            (start_time >= ? AND end_time <= ?)
          )
        LIMIT 1
    ");
    $stmt->bind_param('issssss', $machineId, $startDateTime, $startDateTime, $endDateTime, $endDateTime, $startDateTime, $endDateTime);
    $stmt->execute();
    $overlap = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($overlap) {
        flash_set('error', 'This time slot is already booked. Please choose a different time.');
        redirect('/pages/calendar_booking.php?machine_id=' . $machineId . '&date=' . $bookingDate);
    }

    // Create booking
    $userId = current_user()['id'];
    $stmt = $conn->prepare('INSERT INTO machine_bookings (machine_id, user_id, status, start_time, end_time) VALUES (?, ?, "active", ?, ?)');
    $stmt->bind_param('iiss', $machineId, $userId, $startDateTime, $endDateTime);
    $stmt->execute();
    $stmt->close();

    audit_log('book_machine_timeslot', 'machine_bookings', null);
    flash_set('success', 'Machine booked successfully from ' . $startTime . ' to ' . $endTime . '!');
    redirect('/pages/dashboard.php');
}

// Get all active machines for dropdown
$machines = $conn->query("SELECT id, name, location FROM machines WHERE is_active = 1 ORDER BY name");

// Get bookings for selected machine and date
$bookings = [];
if ($selectedMachineId > 0) {
    $startOfDay = $selectedDate . ' 00:00:00';
    $endOfDay = $selectedDate . ' 23:59:59';

    $stmt = $conn->prepare("
        SELECT b.id, b.start_time, b.end_time, u.username
        FROM machine_bookings b
        JOIN users u ON u.id = b.user_id
        WHERE b.machine_id = ?
          AND b.status = 'active'
          AND b.start_time >= ?
          AND b.start_time <= ?
        ORDER BY b.start_time
    ");
    $stmt->bind_param('iss', $selectedMachineId, $startOfDay, $endOfDay);
    $stmt->execute();
    $bookings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

// Time slots (8 AM to 5 PM in 1-hour blocks)
$timeSlots = [
    '08:00', '09:00', '10:00', '11:00', '12:00',
    '13:00', '14:00', '15:00', '16:00', '17:00'
];

include __DIR__ . '/../includes/header.php';
?>

<div class="card">
  <h2>🕒 Machine Availability</h2>
  <p><small>Check availability and book machines by specific time slots</small></p>
</div>

<div class="grid">
  <!-- Left side: Machine selection and date -->
  <div class="card">
    <h3>Select Machine & Date</h3>

    <form method="get" action="<?= e(BASE_URL) ?>/pages/calendar_booking.php">
      <div style="margin-bottom:12px;">
        <label for="machine_id">Machine</label>
        <select id="machine_id" name="machine_id" required onchange="this.form.submit()">
          <option value="">-- Select a machine --</option>
          <?php while($m = $machines->fetch_assoc()): ?>
            <option value="<?= (int)$m['id'] ?>" <?= $selectedMachineId === (int)$m['id'] ? 'selected' : '' ?>>
              <?= e($m['name']) ?><?= $m['location'] ? ' - ' . e($m['location']) : '' ?>
            </option>
          <?php endwhile; ?>
        </select>
      </div>

      <div style="margin-bottom:12px;">
        <label for="date">Date</label>
        <input type="date" id="date" name="date" value="<?= e($selectedDate) ?>"
               min="<?= date('Y-m-d') ?>" required onchange="this.form.submit()" />
      </div>
    </form>

    <?php if ($selectedMachineId > 0): ?>
      <div style="margin-top:24px;padding:12px;background:#f9fafb;border-radius:8px;">
        <strong>Legend:</strong>
        <div style="display:flex;gap:12px;margin-top:8px;flex-wrap:wrap;">
          <div style="display:flex;align-items:center;gap:6px;">
            <div style="width:20px;height:20px;background:#10b981;border-radius:4px;"></div>
            <span style="font-size:0.9rem;">Available</span>
          </div>
          <div style="display:flex;align-items:center;gap:6px;">
            <div style="width:20px;height:20px;background:#ef4444;border-radius:4px;"></div>
            <span style="font-size:0.9rem;">Booked</span>
          </div>
        </div>
      </div>
    <?php endif; ?>
  </div>

  <!-- Right side: Time slot calendar -->
  <div class="card">
    <?php if ($selectedMachineId > 0): ?>
      <h3>Available Time Slots</h3>
      <p><small><?= date('l, F j, Y', strtotime($selectedDate)) ?></small></p>

      <div class="time-slot-grid">
        <?php foreach ($timeSlots as $i => $slot):
          $nextSlot = $timeSlots[$i + 1] ?? '18:00';
          $slotStart = $selectedDate . ' ' . $slot . ':00';
          $slotEnd = $selectedDate . ' ' . $nextSlot . ':00';

          // Check if this slot is booked
          $isBooked = false;
          $bookedBy = '';
          foreach ($bookings as $booking) {
            $bookingStart = strtotime($booking['start_time']);
            $bookingEnd = strtotime($booking['end_time']);
            $checkStart = strtotime($slotStart);
            $checkEnd = strtotime($slotEnd);

            if (($bookingStart < $checkEnd) && ($bookingEnd > $checkStart)) {
              $isBooked = true;
              $bookedBy = $booking['username'];
              break;
            }
          }

          $slotClass = $isBooked ? 'time-slot booked' : 'time-slot available';
        ?>
          <div class="<?= $slotClass ?>">
            <div class="time-slot-time"><?= $slot ?> - <?= $nextSlot ?></div>
            <?php if ($isBooked): ?>
              <div class="time-slot-status">Booked by <?= e($bookedBy) ?></div>
            <?php else: ?>
              <button type="button" class="btn btn-primary btn-sm" onclick="bookSlot('<?= $slot ?>', '<?= $nextSlot ?>')">
                Book
              </button>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>

      <!-- Hidden booking form -->
      <form id="booking-form" method="post" style="display:none;">
        <?= csrf_field() ?>
        <input type="hidden" name="machine_id" value="<?= (int)$selectedMachineId ?>" />
        <input type="hidden" name="booking_date" value="<?= e($selectedDate) ?>" />
        <input type="hidden" id="start_time" name="start_time" value="" />
        <input type="hidden" id="end_time" name="end_time" value="" />
      </form>

    <?php else: ?>
      <div style="text-align:center;padding:40px;color:#6b7280;">
        <p>Please select a machine to view available time slots</p>
      </div>
    <?php endif; ?>
  </div>
</div>

<script>
function bookSlot(startTime, endTime) {
  if (confirm('Book this machine from ' + startTime + ' to ' + endTime + '?')) {
    document.getElementById('start_time').value = startTime;
    document.getElementById('end_time').value = endTime;
    document.getElementById('booking-form').submit();
  }
}
</script>

<style>
.time-slot-grid {
  display: grid;
  gap: 12px;
  margin-top: 16px;
}

.time-slot {
  padding: 16px;
  border-radius: 8px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  transition: all 0.2s;
}

.time-slot.available {
  background: #ecfdf5;
  border: 2px solid #10b981;
}

.time-slot.available:hover {
  border-color: #059669;
  box-shadow: 0 2px 8px rgba(16, 185, 129, 0.2);
}

.time-slot.booked {
  background: #fef2f2;
  border: 2px solid #ef4444;
  opacity: 0.7;
}

.time-slot-time {
  font-weight: 600;
  font-size: 1rem;
}

.time-slot-status {
  font-size: 0.85rem;
  color: #991b1b;
}

.btn-sm {
  padding: 6px 12px;
  font-size: 0.9rem;
}
</style>

<?php include __DIR__ . '/../includes/footer.php'; ?>
