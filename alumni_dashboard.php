<?php
session_start();
require_once 'db_config.php';

// Check if alumni is logged in
if (!isset($_SESSION['alumni_id'])) {
    header('Location: alumni_login.php');
    exit();
}

$alumni_id = $_SESSION['alumni_id'];
$error = '';
$success = '';

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $mobile_no = trim($_POST['mobile_no'] ?? '');
    $current_position = trim($_POST['current_position'] ?? '');
    $current_organization = trim($_POST['current_organization'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $bio = trim($_POST['bio'] ?? '');
    $linkedin_url = trim($_POST['linkedin_url'] ?? '');
    $other_links = trim($_POST['other_links'] ?? '');
    // Password change
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    try {
        $stmt = $pdo->prepare("UPDATE alumni_info SET first_name=?, last_name=?, email=?, mobile_no=?, current_position=?, current_organization=?, location=?, bio=?, linkedin_url=?, other_links=? WHERE alumni_id=?");
        $stmt->execute([$first_name, $last_name, $email, $mobile_no, $current_position, $current_organization, $location, $bio, $linkedin_url, $other_links, $alumni_id]);
        if ($new_password && $new_password === $confirm_password) {
            $hash = password_hash($new_password, PASSWORD_DEFAULT);
            $pdo->prepare("UPDATE alumni_info SET password=? WHERE alumni_id=?")->execute([$hash, $alumni_id]);
            $success = 'Profile and password updated successfully.';
        } elseif ($new_password || $confirm_password) {
            $error = 'Passwords do not match.';
        } else {
            $success = 'Profile updated successfully.';
        }
    } catch (Exception $e) {
        $error = 'Error updating profile: ' . $e->getMessage();
    }
}

// Fetch alumni data
$stmt = $pdo->prepare("SELECT * FROM alumni_info WHERE alumni_id = ?");
$stmt->execute([$alumni_id]);
$alumni = $stmt->fetch();
if (!$alumni) {
    die('Alumni record not found.');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Alumni Dashboard</title>
    <link rel="stylesheet" href="css/style.css">
    <meta name="viewport" content="width=device-width, initial-scale=1">
</head>
<body>
    <div class="container mt-5">
    <h2>Welcome, <?= htmlspecialchars($alumni['first_name'] . ' ' . $alumni['last_name']) ?> (<?= htmlspecialchars($alumni['alumni_id']) ?>)</h2>
        <?php if ($success): ?>
            <div class="alert alert-success"> <?= $success ?> </div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger"> <?= $error ?> </div>
        <?php endif; ?>
        <form method="post">
            <div class="row mb-3">
                <div class="col-md-6">
                    <label>First Name</label>
                    <input type="text" name="first_name" class="form-control" value="<?= htmlspecialchars($alumni['first_name']) ?>" required>
                </div>
                <div class="col-md-6">
                    <label>Last Name</label>
                    <input type="text" name="last_name" class="form-control" value="<?= htmlspecialchars($alumni['last_name']) ?>" required>
                </div>
            </div>
            <div class="mb-3">
                <label>Email</label>
                <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($alumni['email']) ?>" required>
            </div>
            <div class="mb-3">
                <label>Mobile Number</label>
                <input type="text" name="mobile_no" class="form-control" value="<?= htmlspecialchars($alumni['mobile_no'] ?? '') ?>">
            </div>
            <div class="mb-3">
                <label>Current Position</label>
                <input type="text" name="current_position" class="form-control" value="<?= htmlspecialchars($alumni['current_position'] ?? '') ?>">
            </div>
            <div class="mb-3">
                <label>Current Organization</label>
                <input type="text" name="current_organization" class="form-control" value="<?= htmlspecialchars($alumni['current_organization'] ?? '') ?>">
            </div>
            <div class="mb-3">
                <label>Location</label>
                <input type="text" name="location" class="form-control" value="<?= htmlspecialchars($alumni['location'] ?? '') ?>">
            </div>
            <div class="mb-3">
                <label>Bio</label>
                <textarea name="bio" class="form-control"><?= htmlspecialchars($alumni['bio'] ?? '') ?></textarea>
            </div>
            <div class="mb-3">
                <label>LinkedIn URL</label>
                <input type="url" name="linkedin_url" class="form-control" value="<?= htmlspecialchars($alumni['linkedin_url'] ?? '') ?>">
            </div>
            <div class="mb-3">
                <label>Other Links</label>
                <input type="text" name="other_links" class="form-control" value="<?= htmlspecialchars($alumni['other_links'] ?? '') ?>">
            </div>
            <hr>
            <h5>Change Password</h5>
            <div class="mb-3">
                <label>New Password</label>
                <input type="password" name="new_password" class="form-control">
            </div>
            <div class="mb-3">
                <label>Confirm New Password</label>
                <input type="password" name="confirm_password" class="form-control">
            </div>
            <button type="submit" class="btn btn-primary">Update Profile</button>
        </form>
        <hr>
        <h4>Old Student Data</h4>
        <ul>
            <li><strong>Alumni ID:</strong> <?= htmlspecialchars($alumni['alumni_id']) ?></li>
            <li><strong>Batch:</strong> <?= htmlspecialchars($alumni['batch'] ?? '') ?></li>
            <li><strong>Graduation Year:</strong> <?= htmlspecialchars($alumni['graduation_year'] ?? '') ?></li>
            <li><strong>Degree:</strong> <?= htmlspecialchars($alumni['degree'] ?? '') ?></li>
            <li><strong>Department:</strong> <?= htmlspecialchars($alumni['department_id'] ?? '') ?></li>
        </ul>
        <a href="logout.php" class="btn btn-secondary mt-3">Logout</a>
    </div>
</body>
</html>
