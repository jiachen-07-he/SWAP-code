<?php
require_once __DIR__ . '/../config/bootstrap.php';
require_login();

$conn = db();
$pageTitle = 'Security Question - ' . APP_NAME;

// Get current user's security question
$userId = current_user()['id'];
$stmt = $conn->prepare('SELECT security_question FROM users WHERE id = ?');
$stmt->bind_param('i', $userId);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

$hasQuestion = $user['security_question'] !== null;

if (is_post()) {
    csrf_validate();

    $question = post_str('security_question', 20);
    $answer = post_str('security_answer', 50);
    $confirmAnswer = post_str('confirm_answer', 50);

    if ($question === '' || $answer === '' || $confirmAnswer === '') {
        flash_set('error', 'All fields are required.');
        redirect('/pages/security_question.php');
    }

    if ($answer !== $confirmAnswer) {
        flash_set('error', 'Answers do not match.');
        redirect('/pages/security_question.php');
    }

    $validQuestions = ['favorite_color', 'birth_city', 'first_pet'];
    if (!in_array($question, $validQuestions)) {
        flash_set('error', 'Invalid security question selected.');
        redirect('/pages/security_question.php');
    }

    // For favorite_color, validate against allowed colors
    if ($question === 'favorite_color') {
        $validColors = ['green', 'blue', 'red', 'black', 'white', 'yellow'];
        if (!in_array(strtolower($answer), $validColors)) {
            flash_set('error', 'Please select a valid color.');
            redirect('/pages/security_question.php');
        }
    }

    // Hash the answer (lowercase for case-insensitive comparison)
    $answerHash = password_hash(strtolower($answer), PASSWORD_DEFAULT);

    // Update user's security question
    $stmt = $conn->prepare('UPDATE users SET security_question = ?, security_answer_hash = ? WHERE id = ?');
    $stmt->bind_param('ssi', $question, $answerHash, $userId);
    $stmt->execute();
    $stmt->close();

    $action = $hasQuestion ? 'security_question_updated' : 'security_question_set';
    audit_log($action, 'users', $userId);

    flash_set('success', 'Security question saved successfully!');
    redirect('/pages/dashboard.php');
}

$questions = [
    'favorite_color' => 'What is your favorite color?',
    'birth_city' => 'In what city were you born?',
    'first_pet' => 'What was the name of your first pet?'
];

$colorOptions = ['green', 'blue', 'red', 'black', 'white', 'yellow'];

include __DIR__ . '/../includes/header.php';
?>

<div class="card" style="max-width:600px;margin:24px auto;">
  <h2><?= $hasQuestion ? 'Update' : 'Set Up' ?> Security Question</h2>
  <p><small>This will be used to recover your password if you forget it.</small></p>

  <?php if ($hasQuestion): ?>
    <div class="alert" style="background:#fff3cd;border:1px solid #ffc107;padding:12px;margin-bottom:16px;border-radius:4px;">
      <strong>Note:</strong> Updating your security question will replace your previous one.
    </div>
  <?php endif; ?>

  <form method="post" autocomplete="off">
    <?= csrf_field() ?>

    <div style="margin-bottom:12px;">
      <label for="security_question">Select a Security Question</label>
      <select id="security_question" name="security_question" required onchange="updateAnswerField(this.value)">
        <option value="">-- Choose a question --</option>
        <?php foreach ($questions as $key => $text): ?>
          <option value="<?= e($key) ?>" <?= ($user['security_question'] === $key) ? 'selected' : '' ?>>
            <?= e($text) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div id="answer-field" style="margin-bottom:12px;display:none;">
      <label for="security_answer">Your Answer</label>
      <input id="security_answer_text" name="security_answer" type="text" maxlength="50" style="display:none;" />
      <select id="security_answer_select" name="security_answer" style="display:none;">
        <option value="">-- Select a color --</option>
        <?php foreach ($colorOptions as $color): ?>
          <option value="<?= e($color) ?>"><?= e(ucfirst($color)) ?></option>
        <?php endforeach; ?>
      </select>
      <small id="answer-hint"></small>
    </div>

    <div id="confirm-field" style="margin-bottom:12px;display:none;">
      <label for="confirm_answer">Confirm Your Answer</label>
      <input id="confirm_answer_text" name="confirm_answer" type="text" maxlength="50" style="display:none;" />
      <select id="confirm_answer_select" name="confirm_answer" style="display:none;">
        <option value="">-- Select a color --</option>
        <?php foreach ($colorOptions as $color): ?>
          <option value="<?= e($color) ?>"><?= e(ucfirst($color)) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <button class="btn btn-primary" type="submit" id="submit-btn" style="display:none;">
      <?= $hasQuestion ? 'Update' : 'Save' ?> Security Question
    </button>
    <a href="<?= e(BASE_URL) ?>/pages/dashboard.php" class="btn btn-secondary">Cancel</a>
  </form>
</div>

<script>
function updateAnswerField(questionType) {
  const answerField = document.getElementById('answer-field');
  const confirmField = document.getElementById('confirm-field');
  const submitBtn = document.getElementById('submit-btn');
  const answerText = document.getElementById('security_answer_text');
  const answerSelect = document.getElementById('security_answer_select');
  const confirmText = document.getElementById('confirm_answer_text');
  const confirmSelect = document.getElementById('confirm_answer_select');
  const answerHint = document.getElementById('answer-hint');

  if (questionType === '') {
    answerField.style.display = 'none';
    confirmField.style.display = 'none';
    submitBtn.style.display = 'none';
    return;
  }

  answerField.style.display = 'block';
  confirmField.style.display = 'block';
  submitBtn.style.display = 'inline-block';

  if (questionType === 'favorite_color') {
    answerText.style.display = 'none';
    answerText.removeAttribute('required');
    answerSelect.style.display = 'block';
    answerSelect.setAttribute('required', 'required');

    confirmText.style.display = 'none';
    confirmText.removeAttribute('required');
    confirmSelect.style.display = 'block';
    confirmSelect.setAttribute('required', 'required');

    answerHint.textContent = 'Choose from: green, blue, red, black, white, yellow';
  } else {
    answerText.style.display = 'block';
    answerText.setAttribute('required', 'required');
    answerSelect.style.display = 'none';
    answerSelect.removeAttribute('required');

    confirmText.style.display = 'block';
    confirmText.setAttribute('required', 'required');
    confirmSelect.style.display = 'none';
    confirmSelect.removeAttribute('required');

    if (questionType === 'birth_city') {
      answerHint.textContent = 'Enter the city where you were born';
    } else if (questionType === 'first_pet') {
      answerHint.textContent = 'Enter the name of your first pet';
    }
  }
}

// Initialize if there's a pre-selected question
window.addEventListener('DOMContentLoaded', function() {
  const question = document.getElementById('security_question').value;
  if (question) {
    updateAnswerField(question);
  }
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
