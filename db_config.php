<?php
// db_config.php
// ----------------
// Update 'root' / 'your_mysql_password' to match your local setup.

$host = 'localhost';
$dbname = 'college_management';
$username = 'root';
$password = 'MyNewPassword123!';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
?>
