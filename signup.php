<?php
session_start();
require_once 'db_config.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id      = trim($_POST['user_id'] ?? '');
    $password     = $_POST['password'] ?? '';
    $confirm_pass = $_POST['confirm_password'] ?? '';
    $role         = $_POST['role'] ?? '';
    $first_name   = trim($_POST['first_name'] ?? '');
    $middle_name  = trim($_POST['middle_name'] ?? '');
    $last_name    = trim($_POST['last_name'] ?? '');
    $email        = trim($_POST['email'] ?? '');
    $mobile_no    = trim($_POST['mobile_no'] ?? '');
    $dob          = $_POST['dob'] ?? '';

    // basic validation
    if (empty($user_id) || empty($password) || empty($confirm_pass) || empty($role)
        || empty($first_name) || empty($last_name) || empty($email)
        || empty($mobile_no) || empty($dob)
    ) {
        $error = "All fields are required.";
    } elseif ($password !== $confirm_pass) {
        $error = "Passwords do not match.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format.";
    } elseif (!preg_match('/^[0-9]{10}$/', $mobile_no)) {
        $error = "Mobile number must be 10 digits.";
    } else {
        // check existing user
        $stmt = $pdo->prepare("SELECT * FROM user_credentials WHERE user_id = ?");
        $stmt->execute([$user_id]);
        if ($stmt->fetch()) {
            $error = "User ID already exists.";
        } else {
            try {
                $pdo->beginTransaction();
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare(
                    "INSERT INTO user_credentials (user_id, passcode, role) VALUES (?, ?, ?)"
                );
                $stmt->execute([$user_id, $hash, $role]);

                switch ($role) {
                    case 'student':
                        $stmt = $pdo->prepare("
                            INSERT INTO student_info (
                              student_id, student_type, first_name, middle_name, last_name,
                              dob, mobile_no, email
                            ) VALUES (?, 'UG', ?, ?, ?, ?, ?, ?)
                        ");
                        $stmt->execute([
                          $user_id,
                          $first_name, $middle_name, $last_name,
                          $dob, $mobile_no, $email
                        ]);
                        break;
                    case 'professor':
                        $stmt = $pdo->prepare("
                            INSERT INTO employee_info (
                              emp_id, emp_type, first_name, middle_name, last_name,
                              email, mobile_no_1, date_join
                            ) VALUES (?, 'Professor', ?, ?, ?, ?, ?, CURDATE())
                        ");
                        $stmt->execute([
                          $user_id,
                          $first_name, $middle_name, $last_name,
                          $email, $mobile_no
                        ]);
                        break;
                    case 'hod':
                        $stmt = $pdo->prepare("
                            INSERT INTO hod (
                              hod_id, first_name, middle_name, last_name,
                              email, mobile_no, doj
                            ) VALUES (?, ?, ?, ?, ?, ?, CURDATE())
                        ");
                        $stmt->execute([
                          $user_id,
                          $first_name, $middle_name, $last_name,
                          $email, $mobile_no
                        ]);
                        break;
                    case 'staff':
                        $stmt = $pdo->prepare("
                            INSERT INTO employee_info (
                              emp_id, emp_type, first_name, middle_name, last_name,
                              email, mobile_no_1, date_join
                            ) VALUES (?, 'Staff', ?, ?, ?, ?, ?, CURDATE())
                        ");
                        $stmt->execute([
                          $user_id,
                          $first_name, $middle_name, $last_name,
                          $email, $mobile_no
                        ]);
                        break;
                }

                $pdo->commit();
                header('Location: login.php?success=1');
                exit;
            } catch (PDOException $e) {
                $pdo->rollBack();
                $error = "An error occurred during registration. Please try again.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Sign Up – College ERP</title>
  <link rel="stylesheet" href="css/style.css">
  <style>
    * {
      box-sizing: border-box;
    }
    
    body {
      font-family: Arial, sans-serif;
      background-color: #f0f2f5;
      margin: 0;
      padding: 20px 10px;
      min-height: 100vh;
      overflow-y: auto;
    }
    
    .container {
      background: white;
      padding: 30px;
      border-radius: 10px;
      box-shadow: 0 2px 10px rgba(0,0,0,0.1);
      width: 100%;
      max-width: 500px;
      margin: 0 auto;
      min-height: auto;
    }
    
    h2 {
      text-align: center;
      margin-bottom: 25px;
      color: #333;
      font-size: 24px;
    }
    
    .input-field {
      margin-bottom: 15px;
    }
    
    .input-field label {
      display: block;
      margin-bottom: 5px;
      color: #666;
      font-weight: 500;
      font-size: 14px;
    }
    
    .input-field input,
    .input-field select {
      width: 100%;
      padding: 10px;
      border: 1px solid #ddd;
      border-radius: 5px;
      font-size: 16px;
      transition: border-color 0.3s ease;
    }
    
    .input-field input:focus,
    .input-field select:focus {
      outline: none;
      border-color: #007bff;
      box-shadow: 0 0 0 2px rgba(0,123,255,0.25);
    }
    
    button[type="submit"] {
      width: 100%;
      padding: 12px;
      background-color: #007bff;
      color: white;
      border: none;
      border-radius: 5px;
      font-size: 16px;
      cursor: pointer;
      transition: background-color 0.3s ease;
      margin-top: 10px;
      margin-bottom: 20px;
    }
    
    button[type="submit"]:hover {
      background-color: #0056b3;
    }
    
    .error {
      background: #ffebee;
      color: #c62828;
      padding: 12px;
      border-radius: 5px;
      margin-bottom: 20px;
      text-align: center;
      border: 1px solid #ef9a9a;
      font-size: 14px;
    }
    
    .link-row {
      text-align: center;
      margin-top: 15px;
    }
    
    .link-row p {
      margin: 5px 0;
      font-size: 14px;
    }
    
    .link-row a {
      color: #007bff;
      text-decoration: none;
    }
    
    .link-row a:hover {
      text-decoration: underline;
    }
    
    /* Two column layout for name fields */
    .name-row {
      display: flex;
      gap: 10px;
    }
    
    .name-row .input-field {
      flex: 1;
    }
    
    /* Mobile responsiveness */
    @media (max-width: 600px) {
      body {
        padding: 10px 5px;
      }
      
      .container {
        padding: 20px;
        margin: 10px auto;
      }
      
      .name-row {
        flex-direction: column;
        gap: 0;
      }
      
      h2 {
        font-size: 20px;
      }
    }
    
    /* Ensure proper scrolling */
    html {
      overflow-y: auto;
    }
  </style>
</head>
<body>
  <div class="container">
    <h2>Sign Up</h2>

    <?php if ($error): ?>
      <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="signup.php">
      <div class="input-field">
        <label for="user_id">User ID:</label>
        <input type="text" id="user_id" name="user_id"
               value="<?= htmlspecialchars($user_id ?? '') ?>" required>
      </div>

      <div class="input-field">
        <label for="password">Password:</label>
        <input type="password" id="password" name="password" required>
      </div>

      <div class="input-field">
        <label for="confirm_password">Confirm Password:</label>
        <input type="password" id="confirm_password"
               name="confirm_password" required>
      </div>

      <div class="input-field">
        <label for="role">Role:</label>
        <select id="role" name="role" required>
          <option value="">Select Role</option>
          <?php foreach (['student','professor','hod','staff'] as $r): ?>
            <option value="<?= $r ?>"
              <?= (isset($role) && $role === $r) ? 'selected' : '' ?>>
              <?= ucfirst($r) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="name-row">
        <div class="input-field">
          <label for="first_name">First Name:</label>
          <input type="text" id="first_name" name="first_name"
                 value="<?= htmlspecialchars($first_name ?? '') ?>" required>
        </div>

        <div class="input-field">
          <label for="middle_name">Middle Name:</label>
          <input type="text" id="middle_name" name="middle_name"
                 value="<?= htmlspecialchars($middle_name ?? '') ?>">
        </div>
      </div>

      <div class="input-field">
        <label for="last_name">Last Name:</label>
        <input type="text" id="last_name" name="last_name"
               value="<?= htmlspecialchars($last_name ?? '') ?>" required>
      </div>

      <div class="input-field">
        <label for="email">Email:</label>
        <input type="email" id="email" name="email"
               value="<?= htmlspecialchars($email ?? '') ?>" required>
      </div>

      <div class="input-field">
        <label for="mobile_no">Mobile Number:</label>
        <input type="tel" id="mobile_no" name="mobile_no"
               value="<?= htmlspecialchars($mobile_no ?? '') ?>" required>
      </div>

      <div class="input-field">
        <label for="dob">Date of Birth:</label>
        <input type="date" id="dob" name="dob"
               value="<?= htmlspecialchars($dob ?? '') ?>" required>
      </div>

      <button type="submit">Sign Up</button>
    </form>

    <div class="link-row">
      <p>Already have an account? <a href="login.php">Sign In</a></p>
      <p><a href="forgot.php">Forgot Password?</a></p>
    </div>
  </div>
</body>
</html>