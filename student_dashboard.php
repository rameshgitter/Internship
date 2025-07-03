<?php
// student_dashboard.php - Redirect to main dashboard.php
// This file exists to handle any legacy references to student_dashboard.php

session_start();

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'student') {
    header('Location: login.php');
    exit();
}

// Redirect to the main dashboard.php
header('Location: dashboard.php');
exit();
?>