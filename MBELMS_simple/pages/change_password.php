<?php
require_once __DIR__ . '/../config/bootstrap.php';
require_login();

$conn = db();
$pageTitle = 'Change Password - ' . APP_NAME;

// Get current user's security question
$userId = current_user()['id'];
$stmt = $conn->prepare('SELECT security_question FROM users WHERE id = ?');
$stmt->bind_param('i', $userId);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

$hasQuestion = $user['security_question'] !== null;

// Step tracking
$step = (int)($_GET['step'] ?? 1);

if (is_post()) {
    csrf_validate();
    $action = post_str('action', 30);

    // Step 1: Verify security question
    if ($action === 'verify_security') {
        if (!$hasQuestion) {
            flash_set('error', 'You must set up a security question first.');
            redirect('/pages/security_question.php');
        }

        $answer = post_str('security_answer', 50);

        if ($answer === '') {
            flash_set('error', 'Security answer is required.');
            redirect('/pages/change_password.php');
        }

        // Verify security answer
        $stmt = $conn->prepare('SELECT security_answer_hash FROM users WHERE id = ?');
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $userSec = $result->fetch_assoc();
        $stmt->close();

        if (!password_verify(strtolower($answer), $userSec['security_answer_hash'])) {
            flash_set('error', 'Security answer is incorrect.');
            audit_log('password_change_wrong_security_answer', 'users', $userId);
            redirect('/pages/change_password.php');
        }

        // Store verification in session
        $_SESSION['password_change_verified'] = true;
        $_SESSION['password_change_time'] = time();
        redirect('/pages/change_password.php?step=2');
    }

    // Step 2: Change password
    if ($action === 'change_password') {
        // Check if user verified security question
        $verified = $_SESSION['password_change_verified'] ?? false;
        $verifyTime = $_SESSION['password_change_time'] ?? 0;

        // Verification expires after 5 minutes
        if (!$verified || (time() - $verifyTime) > 300) {
            flash_set('error', 'Security verification expired. Please verify again.');
            unset($_SESSION['password_change_verified'], $_SESSION['password_change_time']);
            redirect('/pages/change_password.php');
        }

        $newPassword = (string)($_POST['new_password'] ?? '');
        $confirmPassword = (string)($_POST['confirm_password'] ?? '');

        if ($newPassword === '' || $confirmPassword === '') {
            flash_set('error', 'All fields are required.');
            redirect('/pages/change_password.php?step=2');
        }

        if ($newPassword !== $confirmPassword) {
            flash_set('error', 'New passwords do not match.');
            redirect('/pages/change_password.php?step=2');
        }

        if (strlen($newPassword) < 8) {
            flash_set('error', 'Password must be at least 8 characters long.');
            redirect('/pages/change_password.php?step=2');
        }

        // Update password
        $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt = $conn->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
        $stmt->bind_param('si', $newHash, $userId);
        $stmt->execute();
        $stmt->close();

        // Clear verification session
        unset($_SESSION['password_change_verified'], $_SESSION['password_change_time']);

        audit_log('password_changed', 'users', $userId);
        flash_set('success', 'Password changed successfully!');
        redirect('/pages/dashboard.php');
    }
}

// Get security question text for display
function get_security_question_text($question) {
    $questions = [
        'favorite_color' => 'What is your favorite color?',
        'birth_city' => 'In what city were you born?',
        'first_pet' => 'What was the name of your first pet?'
    ];
    return $questions[$question] ?? 'Unknown question';
}

$colorOptions = ['green', 'blue', 'red', 'black', 'white', 'yellow'];

include __DIR__ . '/../includes/header.php';
?>

<div class="card" style="max-width:520px;margin:24px auto;">
  <h2>Change Password</h2>

  <?php if (!$hasQuestion): ?>
    <div class="alert" style="background:#fff3cd;border:1px solid #ffc107;padding:12px;margin-bottom:16px;border-radius:4px;">
      <strong>Security Question Required</strong>
      <p>You must set up a security question before you can change your password.</p>
      <a href="<?= e(BASE_URL) ?>/pages/security_question.php" class="btn btn-primary" style="margin-top:8px;">Set Up Security Question</a>
      <a href="<?= e(BASE_URL) ?>/pages/dashboard.php" class="btn btn-secondary" style="margin-top:8px;">Back to Dashboard</a>
    </div>

  <?php elseif ($step === 1): ?>
    <p><small>First, answer your security question to verify your identity.</small></p>

    <form method="post" autocomplete="off">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="verify_security" />

      <div style="margin-bottom:12px;">
        <label><?= e(get_security_question_text($user['security_question'])) ?></label>
        <?php if ($user['security_question'] === 'favorite_color'): ?>
          <select name="security_answer" required>
            <option value="">-- Select a color --</option>
            <?php foreach ($colorOptions as $color): ?>
              <option value="<?= e($color) ?>"><?= e(ucfirst($color)) ?></option>
            <?php endforeach; ?>
          </select>
        <?php else: ?>
          <input name="security_answer" type="text" maxlength="50" required />
        <?php endif; ?>
      </div>

      <button class="btn btn-primary" type="submit">Verify &amp; Continue</button>
      <a href="<?= e(BASE_URL) ?>/pages/dashboard.php" class="btn btn-secondary">Cancel</a>
    </form>

  <?php elseif ($step === 2): ?>
    <?php
    // Check if verified
    $verified = $_SESSION['password_change_verified'] ?? false;
    $verifyTime = $_SESSION['password_change_time'] ?? 0;

    if (!$verified || (time() - $verifyTime) > 300) {
        echo '<p>Security verification expired. <a href="' . e(BASE_URL) . '/pages/change_password.php">Start over</a></p>';
        unset($_SESSION['password_change_verified'], $_SESSION['password_change_time']);
        include __DIR__ . '/../includes/footer.php';
        exit;
    }
    ?>

    <div class="alert" style="background:#d1fae5;border:1px solid #10b981;padding:12px;margin-bottom:16px;border-radius:4px;">
      ✓ Security question verified. You can now set a new password.
    </div>

    <form method="post" autocomplete="off">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="change_password" />

      <div style="margin-bottom:12px;">
        <label for="new_password">New Password</label>
        <input id="new_password" name="new_password" type="password" minlength="8" required />
        <small>At least 8 characters</small>
      </div>

      <div style="margin-bottom:12px;">
        <label for="confirm_password">Confirm New Password</label>
        <input id="confirm_password" name="confirm_password" type="password" minlength="8" required />
      </div>

      <button class="btn btn-primary" type="submit">Change Password</button>
      <a href="<?= e(BASE_URL) ?>/pages/dashboard.php" class="btn btn-secondary">Cancel</a>
    </form>

  <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
