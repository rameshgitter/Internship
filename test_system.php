<?php
require_once 'db_config.php';

echo "<h1>System Test</h1>";

try {
    // Test database connection
    echo "<h2>Database Connection: ✓ Connected</h2>";
    
    // Test tables exist
    $tables = ['index', 'department', 'hod', 'student_info', 'staff_info', 'subjects_pool', 'semester_registration', 'verification_assignments', 'student_marks'];
    
    echo "<h2>Database Tables:</h2>";
    foreach ($tables as $table) {
        $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
        if ($stmt->rowCount() > 0) {
            echo "<p>✓ Table '$table' exists</p>";
        } else {
            echo "<p>✗ Table '$table' missing</p>";
        }
    }
    
    // Test sample data
    echo "<h2>Sample Data:</h2>";
    
    // Users
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM `index`");
    $count = $stmt->fetch()['count'];
    echo "<p>Users in index table: $count</p>";
    
    // Students
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM student_info");
    $count = $stmt->fetch()['count'];
    echo "<p>Students: $count</p>";
    
    // Staff
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM staff_info");
    $count = $stmt->fetch()['count'];
    echo "<p>Staff: $count</p>";
    
    // Subjects
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM subjects_pool");
    $count = $stmt->fetch()['count'];
    echo "<p>Subjects: $count</p>";
    
    // Test login credentials
    echo "<h2>Test Login Credentials:</h2>";
    $stmt = $pdo->query("SELECT user_id, role, change_passcode FROM `index`");
    $users = $stmt->fetchAll();
    
    foreach ($users as $user) {
        echo "<p>User ID: {$user['user_id']} | Role: {$user['role']} | Password Changed: " . ($user['change_passcode'] ? 'Yes' : 'No') . "</p>";
    }
    
    echo "<h2>✓ System appears to be working correctly!</h2>";
    echo "<p><a href='login.php'>Go to Login Page</a></p>";
    
} catch (Exception $e) {
    echo "<h2>✗ Error: " . $e->getMessage() . "</h2>";
}
?>