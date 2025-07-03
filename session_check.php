<?php
// session_check.php
function check_admin_session() {
    // Set session timeout to 2 hours (7200 seconds) BEFORE starting session
    ini_set('session.gc_maxlifetime', 7200);
    // Set session cookie lifetime to 2 hours BEFORE starting session
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params(7200);
        session_start();
    }

    // Check if session has expired (extended timeout for registration processes)
    $timeout_duration = 7200; // 2 hours instead of 1 hour
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $timeout_duration)) {
        // Last request was more than 2 hours ago
        session_destroy();
        header('Location: login.php');
        exit();
    }

    // Update last activity time
    $_SESSION['last_activity'] = time();

    // Check if user is logged in
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
        header('Location: login.php');
        exit();
    }

    // Check if user is admin
    if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== 1) {
        header('Location: dashboard.php');
        exit();
    }

    // Regenerate session ID if not initiated
    if (!isset($_SESSION['initiated'])) {
        session_regenerate_id(true);
        $_SESSION['initiated'] = true;
    }

    return true;
}

function check_professor_session() {
    // Set session timeout to 2 hours (7200 seconds) BEFORE starting session
    ini_set('session.gc_maxlifetime', 7200);
    // Set session cookie lifetime to 2 hours BEFORE starting session
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params(7200);
        session_start();
    }

    // Check if session has expired (extended timeout for registration processes)
    $timeout_duration = 7200; // 2 hours instead of 1 hour
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $timeout_duration)) {
        // Last request was more than 2 hours ago
        session_destroy();
        header('Location: login.php');
        exit();
    }

    // Update last activity time
    $_SESSION['last_activity'] = time();

    // Check if user is logged in and is a professor
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'professor') {
        header('Location: login.php');
        exit();
    }

    // Regenerate session ID if not initiated
    if (!isset($_SESSION['initiated'])) {
        session_regenerate_id(true);
        $_SESSION['initiated'] = true;
    }

    return true;
}
?>
