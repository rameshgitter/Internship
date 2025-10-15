<?php
session_start();
require_once 'db_config.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id      = trim($_POST['user_id'] ?? '');
    $password     = $_POST['password'] ?? '';
    $role         = $_POST['role'] ?? '';
    $first_name   = trim($_POST['first_name'] ?? '');
    $middle_name  = trim($_POST['middle_name'] ?? '');
    $last_name    = trim($_POST['last_name'] ?? '');
    $email        = trim($_POST['email'] ?? '');
    $mobile_no    = trim($_POST['mobile_no'] ?? '');
    $dob          = $_POST['dob'] ?? '';
    $guardian_first_name = trim($_POST['guardian_first_name'] ?? '');
    $guardian_middle_name = trim($_POST['guardian_middle_name'] ?? '');
    $guardian_last_name = trim($_POST['guardian_last_name'] ?? '');
    $guardian_mobile_no = trim($_POST['guardian_mobile_no'] ?? '');
    $guardian_email = trim($_POST['guardian_email'] ?? '');
    $status        = $_POST['status'] ?? 'Ongoing';

    // If password is empty, set it to user_id (enrollment no)
    if (empty($password)) {
        $password = $user_id;
    }

    // basic validation
    if (empty($user_id) || empty($role)
        || empty($first_name) || empty($last_name) || empty($email)
        || empty($mobile_no) || empty($dob)
        || ($role === 'student' && (empty($guardian_first_name) || empty($guardian_last_name) || empty($guardian_mobile_no) || empty($status)))
    ) {
        $error = "All fields are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format.";
    } elseif (!preg_match('/^[0-9]{10}$/', $mobile_no)) {
        $error = "Mobile number must be 10 digits.";
    } else {
        // check existing user
        $stmt = $pdo->prepare("SELECT * FROM `index` WHERE user_id = ?");
        $stmt->execute([$user_id]);
        if ($stmt->fetch()) {
            $error = "User ID already exists.";
        } else {
            try {
                $pdo->beginTransaction();
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare(
                    "INSERT INTO `index` (user_id, passcode, role) VALUES (?, ?, ?)"
                );
                $stmt->execute([$user_id, $hash, $role]);

                switch ($role) {
                    case 'student':
                        $stmt = $pdo->prepare("
                            INSERT INTO student_info (
                              student_id, student_type, first_name, middle_name, last_name,
                              dob, mobile_no, email,
                              guardian_first_name, guardian_middle_name, guardian_last_name, guardian_mobile_no, guardian_email,
                              status
                            ) VALUES (?, 'UG', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                        ");
                        $stmt->execute([
                          $user_id,
                          $first_name, $middle_name, $last_name,
                          $dob, $mobile_no, $email,
                          $guardian_first_name, $guardian_middle_name, $guardian_last_name, $guardian_mobile_no, $guardian_email,
                          $status
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
                    case 'employee':
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
                $error = "Registration failed: " . $e->getMessage();
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
    
    html, body {
      height: 100%;
      margin: 0;
      padding: 40px 0 20px;
      font-family: Arial, sans-serif;
      background-color: #f0f2f5;
    }

    .container {
      background: white;
      padding: 30px;
      border-radius: 10px;
      box-shadow: 0 2px 10px rgba(0,0,0,0.1);
      width: 100%;
      max-width: 500px;
      margin: 0 auto;
      max-height: calc(100vh - 60px);
      overflow-y: auto;
      min-height: auto;
      /* Ensure container is not taller than viewport, but can grow */
      box-sizing: border-box;
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
      html, body {
        padding: 0;
        min-height: unset;
      }
      .container {
        padding: 16px 6px;
        margin: 20px auto 10px auto;
      }
      .name-row {
        flex-direction: column;
        gap: 0;
      }
      h2 {
        font-size: 20px;
      }
    }
  </style>
</head>
<body style="min-height:100vh;display:flex;align-items:center;justify-content:center;background:#f0f2f5;">
  <div class="container" style="max-width:400px;width:100%;margin:auto;">
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
        <input type="password" id="password" name="password" placeholder="Leave blank to use User ID" >
      </div>

      <div class="input-field">
        <label for="role">Role:</label>
        <select id="role" name="role" required>
          <option value="">Select Role</option>
          <?php foreach (["student","professor","hod","employee"] as $r): ?>
            <option value="<?= $r ?>"
              <?= (isset($role) && $role === $r) ? 'selected' : '' ?>>
              <?= ucfirst($r) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="name-row" style="display:flex;gap:10px;">
        <div class="input-field" style="flex:1;">
          <label for="first_name">First Name:</label>
          <input type="text" id="first_name" name="first_name"
                 value="<?= htmlspecialchars($first_name ?? '') ?>" required>
        </div>

        <div class="input-field" style="flex:1;">
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

      <div class="input-field guardian-fields" style="display:none;">
        <label for="guardian_first_name">Guardian First Name:</label>
        <input type="text" id="guardian_first_name" name="guardian_first_name" value="<?= htmlspecialchars($guardian_first_name ?? '') ?>">
      </div>
      <div class="input-field guardian-fields" style="display:none;">
        <label for="guardian_middle_name">Guardian Middle Name:</label>
        <input type="text" id="guardian_middle_name" name="guardian_middle_name" value="<?= htmlspecialchars($guardian_middle_name ?? '') ?>">
      </div>
      <div class="input-field guardian-fields" style="display:none;">
        <label for="guardian_last_name">Guardian Last Name:</label>
        <input type="text" id="guardian_last_name" name="guardian_last_name" value="<?= htmlspecialchars($guardian_last_name ?? '') ?>">
      </div>
      <div class="input-field guardian-fields" style="display:none;">
        <label for="guardian_mobile_no">Guardian Mobile Number:</label>
        <input type="tel" id="guardian_mobile_no" name="guardian_mobile_no" value="<?= htmlspecialchars($guardian_mobile_no ?? '') ?>">
      </div>
      <div class="input-field guardian-fields" style="display:none;">
        <label for="guardian_email">Guardian Email:</label>
        <input type="email" id="guardian_email" name="guardian_email" value="<?= htmlspecialchars($guardian_email ?? '') ?>">
      </div>
      <div class="input-field guardian-fields" style="display:none;">
        <label for="status">Status:</label>
        <select id="status" name="status">
          <option value="Ongoing" selected>Ongoing</option>
          <option value="Graduated">Graduated</option>
        </select>
      </div>

      <button type="submit">Sign Up</button>
    </form>

    <div class="link-row" style="text-align:center;margin-top:15px;">
      <p>Already have an account? <a href="login.php">Sign In</a></p>
      <p><a href="forgot.php">Forgot Password?</a></p>
    </div>
  </div>

  <script>
  // Show guardian fields only for student role
  const roleSelect = document.getElementById('role');
  const guardianFields = document.querySelectorAll('.guardian-fields');
  function toggleGuardianFields() {
    if (roleSelect.value === 'student') {
      guardianFields.forEach(f => f.style.display = 'block');
    } else {
      guardianFields.forEach(f => f.style.display = 'none');
    }
  }
  roleSelect.addEventListener('change', toggleGuardianFields);
  toggleGuardianFields();
  </script>
</body>
</html>