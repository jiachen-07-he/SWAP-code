<?php
require_once __DIR__ . '/../config/bootstrap.php';

$pageTitle = 'Sign Up - ' . APP_NAME;

if (is_logged_in()) {
    redirect('/pages/dashboard.php');
}

if (is_post()) {
    csrf_validate();

    $username = post_str('username', 50);
    $password = (string)($_POST['password'] ?? '');
    $confirmPassword = (string)($_POST['confirm_password'] ?? '');
    $securityQuestion = post_str('security_question', 50);
    $securityAnswer = (string)($_POST['security_answer'] ?? '');

    // Validation
    if ($username === '' || $password === '' || $confirmPassword === '') {
        flash_set('error', 'All fields are required.');
        redirect('/pages/signup.php');
    }

    if (strlen($username) < 3) {
        flash_set('error', 'Username must be at least 3 characters.');
        redirect('/pages/signup.php');
    }

    if (strlen($password) < 6) {
        flash_set('error', 'Password must be at least 6 characters.');
        redirect('/pages/signup.php');
    }

    if ($password !== $confirmPassword) {
        flash_set('error', 'Passwords do not match.');
        redirect('/pages/signup.php');
    }

    if ($securityQuestion === '' || $securityAnswer === '') {
        flash_set('error', 'Security question and answer are required.');
        redirect('/pages/signup.php');
    }

    // Check if username already exists
    $conn = db();
    $stmt = $conn->prepare('SELECT id FROM users WHERE username = ?');
    $stmt->bind_param('s', $username);
    $stmt->execute();
    $existing = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($existing) {
        flash_set('error', 'Username already taken. Please choose another.');
        redirect('/pages/signup.php');
    }

    // Create user with 'user' role
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    $answerHash = password_hash(strtolower($securityAnswer), PASSWORD_DEFAULT);
    $role = 'user';

    $stmt = $conn->prepare('INSERT INTO users (username, password_hash, role, security_question, security_answer_hash) VALUES (?, ?, ?, ?, ?)');
    $stmt->bind_param('sssss', $username, $passwordHash, $role, $securityQuestion, $answerHash);

    if ($stmt->execute()) {
        $stmt->close();
        audit_log('user_signup', 'users', null);
        flash_set('success', 'Account created successfully! You can now log in.');
        redirect('/pages/login.php');
    } else {
        $stmt->close();
        flash_set('error', 'Failed to create account. Please try again.');
        redirect('/pages/signup.php');
    }
}

include __DIR__ . '/../includes/header.php';
?>

<div class="card" style="max-width:520px;margin:24px auto;">
  <h2>Sign Up</h2>
  <p style="margin-bottom:16px;"><small>Create a new account to get started.</small></p>

  <form method="post" autocomplete="off">
    <?= csrf_field() ?>
    <div style="margin-bottom:12px;">
      <label for="username">Username</label>
      <input id="username" name="username" type="text" maxlength="50" required />
    </div>
    <div style="margin-bottom:12px;">
      <label for="password">Password</label>
      <input id="password" name="password" type="password" required />
      <small style="color:#6b7280;">Minimum 6 characters</small>
    </div>
    <div style="margin-bottom:12px;">
      <label for="confirm_password">Confirm Password</label>
      <input id="confirm_password" name="confirm_password" type="password" required />
    </div>

    <hr style="border:none;border-top:1px solid #e5e7eb;margin:20px 0;">

    <p style="margin-bottom:12px;"><small>Security question for password recovery:</small></p>
    <div style="margin-bottom:12px;">
      <label for="security_question">Security Question</label>
      <select id="security_question" name="security_question" required>
        <option value="">-- Select a question --</option>
        <option value="favorite_color">What is your favorite color?</option>
        <option value="birth_city">What city were you born in?</option>
        <option value="first_pet">What was the name of your first pet?</option>
      </select>
    </div>
    <div style="margin-bottom:12px;" id="answer-container">
      <label for="security_answer">Your Answer</label>
      <input id="security_answer" name="security_answer" type="text" maxlength="100" required />
    </div>

    <button class="btn btn-primary" type="submit" style="width:100%;">Create Account</button>
  </form>

  <div style="margin-top:16px;text-align:center;">
    <p>Already have an account? <a href="<?= e(BASE_URL) ?>/pages/login.php">Log in</a></p>
  </div>
</div>

<script>
document.getElementById('security_question').addEventListener('change', function() {
  const container = document.getElementById('answer-container');
  const currentAnswer = document.getElementById('security_answer');

  if (this.value === 'favorite_color') {
    container.innerHTML = `
      <label for="security_answer">Your Answer</label>
      <select id="security_answer" name="security_answer" required>
        <option value="">-- Select a color --</option>
        <option value="green">Green</option>
        <option value="blue">Blue</option>
        <option value="red">Red</option>
        <option value="black">Black</option>
        <option value="white">White</option>
        <option value="yellow">Yellow</option>
      </select>
    `;
  } else {
    container.innerHTML = `
      <label for="security_answer">Your Answer</label>
      <input id="security_answer" name="security_answer" type="text" maxlength="100" required />
    `;
  }
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
