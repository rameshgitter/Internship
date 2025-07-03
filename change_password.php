<?php
session_start();
require_once 'db_config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: login.php');
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error = "All fields are required.";
    } elseif ($new_password !== $confirm_password) {
        $error = "New passwords do not match.";
    } elseif (strlen($new_password) < 6) {
        $error = "New password must be at least 6 characters long.";
    } else {
        try {
            // Verify current password
            $stmt = $pdo->prepare("SELECT passcode FROM `index` WHERE user_id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch();
            
            if ($user && $user['passcode'] === $current_password) {
                // Update password
                $stmt = $pdo->prepare("UPDATE `index` SET passcode = ?, change_passcode = 1 WHERE user_id = ?");
                $stmt->execute([$new_password, $_SESSION['user_id']]);
                
                $_SESSION['change_passcode'] = 1;
                unset($_SESSION['force_password_change']);
                
                $success = "Password changed successfully!";
            } else {
                $error = "Current password is incorrect.";
            }
        } catch (PDOException $e) {
            $error = "Error changing password. Please try again.";
        }
    }
}

// Check if password change is forced
$force_change = isset($_SESSION['force_password_change']) && $_SESSION['force_password_change'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Change Password</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f0f2f5;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 400px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2 class="text-center mb-4">Change Password</h2>
        
        <?php if ($force_change): ?>
            <div class="alert alert-warning">
                <strong>Password Change Required!</strong><br>
                You must change your password before continuing. Your current password is the same as your User ID.
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success">
                <?= $success ?>
                <br><a href="<?= $_SESSION['role'] ?>_dashboard.php" class="btn btn-primary mt-2">Continue to Dashboard</a>
            </div>
        <?php else: ?>
            <form method="POST">
                <div class="mb-3">
                    <label for="current_password" class="form-label">Current Password</label>
                    <input type="password" class="form-control" id="current_password" name="current_password" required>
                    <small class="form-text text-muted">Your current password is your User ID</small>
                </div>
                
                <div class="mb-3">
                    <label for="new_password" class="form-label">New Password</label>
                    <input type="password" class="form-control" id="new_password" name="new_password" required minlength="6">
                </div>
                
                <div class="mb-3">
                    <label for="confirm_password" class="form-label">Confirm New Password</label>
                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required minlength="6">
                </div>
                
                <button type="submit" class="btn btn-primary w-100">Change Password</button>
            </form>
            
            <?php if (!$force_change): ?>
                <div class="text-center mt-3">
                    <a href="<?= $_SESSION['role'] ?>_dashboard.php" class="btn btn-link">Skip for now</a>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</body>
</html>