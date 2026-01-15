<?php
require_once __DIR__ . '/../config/bootstrap.php';

// If already logged in, redirect to dashboard
if (is_logged_in()) {
    redirect('/pages/dashboard.php');
}

$conn = db();
$pageTitle = 'Reset Password - ' . APP_NAME;

// Step tracking
$step = (int)($_GET['step'] ?? 1);

if (is_post()) {
    csrf_validate();
    $action = post_str('action', 30);

    // Step 1: Verify username and security question
    if ($action === 'verify_user') {
        $username = post_str('username', 50);

        if ($username === '') {
            flash_set('error', 'Username is required.');
            redirect('/pages/reset_password.php');
        }

        $stmt = $conn->prepare('SELECT id, security_question FROM users WHERE username = ?');
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();

        if (!$user) {
            flash_set('error', 'User not found.');
            audit_log('password_reset_user_not_found', 'users', null);
            redirect('/pages/reset_password.php');
        }

        if ($user['security_question'] === null) {
            flash_set('error', 'This user has not set up a security question. Please contact an administrator.');
            redirect('/pages/reset_password.php');
        }

        // Store username in session for next step
        $_SESSION['reset_username'] = $username;
        $_SESSION['reset_question'] = $user['security_question'];
        redirect('/pages/reset_password.php?step=2');
    }

    // Step 2: Verify security answer and reset password
    if ($action === 'reset_password') {
        $username = $_SESSION['reset_username'] ?? '';
        $question = $_SESSION['reset_question'] ?? '';
        $answer = post_str('security_answer', 50);
        $newPassword = (string)($_POST['new_password'] ?? '');
        $confirmPassword = (string)($_POST['confirm_password'] ?? '');

        if ($username === '' || $question === '') {
            flash_set('error', 'Invalid session. Please start over.');
            unset($_SESSION['reset_username'], $_SESSION['reset_question']);
            redirect('/pages/reset_password.php');
        }

        if ($answer === '' || $newPassword === '' || $confirmPassword === '') {
            flash_set('error', 'All fields are required.');
            redirect('/pages/reset_password.php?step=2');
        }

        if ($newPassword !== $confirmPassword) {
            flash_set('error', 'New passwords do not match.');
            redirect('/pages/reset_password.php?step=2');
        }

        if (strlen($newPassword) < 8) {
            flash_set('error', 'Password must be at least 8 characters long.');
            redirect('/pages/reset_password.php?step=2');
        }

        // Verify security answer
        $stmt = $conn->prepare('SELECT id, security_answer_hash FROM users WHERE username = ?');
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();

        if (!$user || !password_verify(strtolower($answer), $user['security_answer_hash'])) {
            flash_set('error', 'Security answer is incorrect.');
            audit_log('password_reset_wrong_answer', 'users', $user['id'] ?? null);
            redirect('/pages/reset_password.php?step=2');
        }

        // Update password
        $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt = $conn->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
        $stmt->bind_param('si', $newHash, $user['id']);
        $stmt->execute();
        $stmt->close();

        // Clear session
        unset($_SESSION['reset_username'], $_SESSION['reset_question']);

        audit_log('password_reset_success', 'users', $user['id']);
        flash_set('success', 'Password reset successfully! You can now login with your new password.');
        redirect('/pages/login.php');
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

include __DIR__ . '/../includes/header.php';
?>

<div class="card" style="max-width:520px;margin:24px auto;">
  <h2>Reset Password</h2>

  <?php if ($step === 1): ?>
    <p><small>Enter your username to begin the password reset process.</small></p>

    <form method="post" autocomplete="off">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="verify_user" />

      <div style="margin-bottom:12px;">
        <label for="username">Username</label>
        <input id="username" name="username" type="text" maxlength="50" required />
      </div>

      <button class="btn btn-primary" type="submit">Continue</button>
      <a href="<?= e(BASE_URL) ?>/pages/login.php" class="btn btn-secondary">Back to Login</a>
    </form>

  <?php elseif ($step === 2): ?>
    <?php
    $username = $_SESSION['reset_username'] ?? '';
    $question = $_SESSION['reset_question'] ?? '';

    if ($username === '' || $question === '') {
        echo '<p>Invalid session. <a href="' . e(BASE_URL) . '/pages/reset_password.php">Start over</a></p>';
        include __DIR__ . '/../includes/footer.php';
        exit;
    }

    $questionText = get_security_question_text($question);
    $colorOptions = ['green', 'blue', 'red', 'black', 'white', 'yellow'];
    ?>

    <p><small>Answer your security question and set a new password.</small></p>

    <form method="post" autocomplete="off">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="reset_password" />

      <div style="margin-bottom:12px;">
        <label><?= e($questionText) ?></label>
        <?php if ($question === 'favorite_color'): ?>
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

      <div style="margin-bottom:12px;">
        <label for="new_password">New Password</label>
        <input id="new_password" name="new_password" type="password" minlength="8" required />
        <small>At least 8 characters</small>
      </div>

      <div style="margin-bottom:12px;">
        <label for="confirm_password">Confirm New Password</label>
        <input id="confirm_password" name="confirm_password" type="password" minlength="8" required />
      </div>

      <button class="btn btn-primary" type="submit">Reset Password</button>
      <a href="<?= e(BASE_URL) ?>/pages/reset_password.php" class="btn btn-secondary">Cancel</a>
    </form>

  <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
