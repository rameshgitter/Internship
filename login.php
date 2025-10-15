<?php
session_start();
require_once 'db_config.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = trim($_POST['user_id'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? '';

    if (empty($user_id) || empty($password) || empty($role)) {
        $error = "All fields are required.";
    } else {
        try {
            // Check user credentials from new 'index' table
            $stmt = $pdo->prepare("SELECT * FROM `index` WHERE user_id = ? AND role = ?");
            $stmt->execute([$user_id, $role]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['passcode'])) {
                session_regenerate_id(true);
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['change_passcode'] = $user['change_passcode'];
                $_SESSION['initiated'] = true;
                $_SESSION['last_activity'] = time();

                // Check if user needs to change password
                if ($user['change_passcode'] == 0) {
                    $_SESSION['force_password_change'] = true;
                    header('Location: change_password.php');
                    exit;
                }

                // Get additional user info based on role
                switch ($role) {
                    case 'student':
                        $stmt = $pdo->prepare("SELECT * FROM student_info WHERE student_id = ?");
                        $stmt->execute([$user_id]);
                        $info = $stmt->fetch();
                        if ($info) {
                            $_SESSION['student_info'] = $info;
                            header('Location: dashboard.php');
                        } else {
                            $error = "Student profile not found.";
                        }
                        break;
                    case 'professor':
                        $stmt = $pdo->prepare("SELECT * FROM staff_info WHERE staff_id = ? AND staff_type = 'Professor'");
                        $stmt->execute([$user_id]);
                        $info = $stmt->fetch();
                        if ($info) {
                            $_SESSION['professor_info'] = $info;
                            header('Location: professor_dashboard.php');
                        } else {
                            $error = "Professor profile not found.";
                        }
                        break;
                    case 'hod':
                        $stmt = $pdo->prepare("SELECT * FROM hod WHERE hod_id = ?");
                        $stmt->execute([$user_id]);
                        $info = $stmt->fetch();
                        if ($info) {
                            $_SESSION['hod_info'] = $info;
                            header('Location: hod_dashboard.php');
                        } else {
                            $error = "HOD profile not found.";
                        }
                        break;
                    case 'employee':
                        $stmt = $pdo->prepare("SELECT * FROM staff_info WHERE staff_id = ? AND staff_type = 'Staff'");
                        $stmt->execute([$user_id]);
                        $info = $stmt->fetch();
                        if ($info) {
                            $_SESSION['staff_info'] = $info;
                            header('Location: staff_dashboard.php');
                        } else {
                            $error = "Staff profile not found.";
                        }
                        break;
                }
                exit;
            } else {
                $error = "Invalid credentials or role.";
            }
        } catch (PDOException $e) {
            $error = "Database error occurred.";
            error_log("Login error: " . $e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - IIEST Shibpur</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f0f2f5;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 400px;
        }
        .logo-container {
            text-align: center;
            margin-bottom: 20px;
        }
        .logo-container img {
            max-width: 120px;
            height: auto;
        }
        h2 {
            text-align: center;
            margin-bottom: 30px;
            color: #333;
        }
        .input-field {
            margin-bottom: 20px;
        }
        .input-field label {
            display: block;
            margin-bottom: 5px;
            color: #666;
            font-weight: 500;
        }
        .input-field input,
        .input-field select {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
            box-sizing: border-box;
            transition: border-color 0.3s ease;
        }
        .input-field input:focus,
        .input-field select:focus {
            outline: none;
            border-color: #007bff;
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
            margin-bottom: 15px;
        }
        button[type="submit"]:hover {
            background-color: #0056b3;
        }
        .registration-buttons {
            display: flex;
            flex-direction: column;
            gap: 10px;
            margin-top: 20px;
        }
        .reg-button {
            padding: 12px 20px;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            cursor: pointer;
            transition: background-color 0.3s ease;
            text-decoration: none;
            text-align: center;
            display: block;
            width: 100%;
            box-sizing: border-box;
        }
        .student-reg {
            background-color: #28a745;
            color: white;
        }
        .professor-reg {
            background-color: #17a2b8;
            color: white;
        }
        .reg-button:hover {
            opacity: 0.9;
        }
        .error {
            background: #ffebee;
            color: #c62828;
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 20px;
            text-align: center;
            border: 1px solid #ef9a9a;
        }
        .success {
            background: #e8f5e8;
            color: #2e7d32;
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 20px;
            text-align: center;
            border: 1px solid #a5d6a7;
        }
        .forgot-password {
            text-align: center;
            margin-top: 15px;
        }
        .forgot-password a {
            color: #007bff;
            text-decoration: none;
        }
        .forgot-password a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo-container">
            <img src="images/iiests_logo.png" alt="IIEST Shibpur Logo">
        </div>
        <h2>Welcome to IIEST Shibpur</h2>

        <?php if (isset($_GET['success']) && $_GET['success'] == 1): ?>
            <div class="success">Registration successful! Please login with your credentials.</div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form action="login.php" method="post">
            <div class="input-field">
                <label for="user_id">User ID</label>
                <input type="text" id="user_id" name="user_id" required 
                       value="<?= htmlspecialchars($_POST['user_id'] ?? '') ?>">
            </div>

            <div class="input-field">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>

            <div class="input-field">
                <label for="role">Role</label>
                <select id="role" name="role" required>
                    <option value="">Select Your Role</option>
                    <option value="student" <?= (($_POST['role'] ?? '') === 'student') ? 'selected' : '' ?>>Student</option>
                    <option value="professor" <?= (($_POST['role'] ?? '') === 'professor') ? 'selected' : '' ?>>Professor</option>
                    <option value="hod" <?= (($_POST['role'] ?? '') === 'hod') ? 'selected' : '' ?>>HOD</option>
                    <option value="employee" <?= (($_POST['role'] ?? '') === 'employee') ? 'selected' : '' ?>>Employee</option>
                </select>
            </div>

            <button type="submit">Sign In</button>
        </form>

        <div class="registration-buttons">
            <a href="signup.php" class="reg-button student-reg">New User Registration</a>
        </div>

        <div class="forgot-password">
            <a href="forgot.php">Forgot Password?</a>
        </div>
    </div>
</body>
</html>