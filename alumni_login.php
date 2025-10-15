<?php
// alumni_login.php - Alumni login page
session_start();
require_once 'db_config.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $alumni_id = trim($_POST['alumni_id'] ?? '');
    $password = $_POST['password'] ?? '';
    if (empty($alumni_id) || empty($password)) {
        $error = "All fields are required.";
    } else {
        $stmt = $pdo->prepare("SELECT * FROM alumni_info WHERE alumni_id = ?");
        $stmt->execute([$alumni_id]);
        $alumni = $stmt->fetch();
        if ($alumni && password_verify($password, $alumni['password'])) {
            session_regenerate_id(true);
            $_SESSION['alumni_id'] = $alumni['alumni_id'];
            $_SESSION['alumni_name'] = $alumni['first_name'] . ' ' . $alumni['last_name'];
            header('Location: alumni_dashboard.php');
            exit;
        } else {
            $error = "Invalid credentials.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alumni Login</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="container" style="max-width:400px;margin:auto;">
        <h2>Alumni Login</h2>
        <?php if ($error): ?>
            <div class="error"> <?= htmlspecialchars($error) ?> </div>
        <?php endif; ?>
        <form method="POST" action="alumni_login.php">
            <div class="input-field">
                <label for="alumni_id">Alumni ID (Enrollment No):</label>
                <input type="text" id="alumni_id" name="alumni_id" required>
            </div>
            <div class="input-field">
                <label for="password">Password:</label>
                <input type="password" id="password" name="password" required>
            </div>
            <button type="submit">Login</button>
        </form>
        <div class="link-row" style="text-align:center;margin-top:15px;">
            <a href="alumni_register.php">New Alumni Registration</a>
        </div>
    </div>
</body>
</html>
