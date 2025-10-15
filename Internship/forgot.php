<?php
// forgot.php
session_start();
require_once 'db_config.php';

$step    = 1;
$error   = '';
$success = '';
$student_id = '';
$email      = '';
$showReset  = false;

// If initial form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'verify') {
    $student_id = trim($_POST['student_id'] ?? '');
    $email      = trim($_POST['email'] ?? '');

    if (empty($student_id) || empty($email)) {
        $error = "Student ID & Email are required.";
    } else {
        // Check if user exists (check users table with enrollment_no and email)
        $stmt = $pdo->prepare("SELECT u.* FROM users u WHERE u.enrollment_no = ? AND u.email = ?");
        $stmt->execute([$student_id, $email]);
        $user = $stmt->fetch();

        if (!$user) {
            $error = "No matching user found for that Enrollment ID & Email.";
        } else {
            // Verified – show reset form
            $step = 2;
            $showReset = true;
        }
    }
}
// If reset form is submitted
elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reset') {
    $student_id    = trim($_POST['student_id'] ?? '');
    $new_password  = $_POST['new_password'] ?? '';
    $confirm_pass  = $_POST['confirm_password'] ?? '';

    if (empty($new_password) || empty($confirm_pass)) {
        $error = "Please enter and confirm your new password.";
        $step = 2;
        $showReset = true;
    } elseif ($new_password !== $confirm_pass) {
        $error = "Passwords do not match.";
        $step = 2;
        $showReset = true;
    } else {
        // Update password in users table
        $hashed = hash('sha256', $new_password);
        $update = $pdo->prepare("UPDATE users SET password = ? WHERE enrollment_no = ?");
        $update->execute([$hashed, $student_id]);

        $success = "Password has been reset. You can now <a href='login.php'>sign in</a>.";
        $step = 3; // done
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Forgot Password</title>
  <link rel="stylesheet" href="css/style.css">
</head>
<body>
  <div class="container">
    <h2>Forgot Password</h2>

    <?php if ($error): ?>
      <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php elseif ($success): ?>
      <div class="success"><?= $success ?></div>
    <?php endif; ?>

    <?php if ($step === 1): ?>
      <!-- Step 1: Verify Student ID & Email -->
      <form action="forgot.php" method="post">
        <input type="hidden" name="action" value="verify">
        <div class="input-field">
          <label for="student_id">Enrollment ID</label>
          <input type="text" id="student_id" name="student_id" required
                 value="<?= htmlspecialchars($student_id ?? '') ?>">
        </div>
        <div class="input-field">
          <label for="email">Registered Email</label>
          <input type="email" id="email" name="email" required
                 value="<?= htmlspecialchars($email ?? '') ?>">
        </div>
        <button type="submit">Verify</button>
      </form>

      <div class="link-row">
        Remembered? <a href="login.php">Sign In</a> or <a href="signup.php">Sign Up</a>
      </div>

    <?php elseif ($step === 2 && $showReset): ?>
      <!-- Step 2: Show Reset Form -->
      <form action="forgot.php" method="post">
        <input type="hidden" name="action" value="reset">
        <!-- Keep student_id hidden so we know which record to update -->
        <input type="hidden" name="student_id" value="<?= htmlspecialchars($student_id) ?>">

        <div class="input-field">
          <label for="new_password">New Password</label>
          <input type="password" id="new_password" name="new_password" required>
        </div>
        <div class="input-field">
          <label for="confirm_password">Confirm New Password</label>
          <input type="password" id="confirm_password" name="confirm_password" required>
        </div>
        <button type="submit">Reset Password</button>
      </form>

    <?php elseif ($step === 3): ?>
      <!-- Step 3: Done. Show nothing more. -->
      <div class="link-row">
        <a href="login.php">Back to Sign In</a>
      </div>
    <?php endif; ?>

  </div>
</body>
</html>
