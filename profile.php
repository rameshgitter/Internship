<?php
// Prevent caching to avoid profile data conflicts between users
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Start session with proper configuration
session_start();
if (!isset($_SESSION['initiated'])) {
    $_SESSION['initiated'] = true;
}
require_once 'db_config.php';

// Check if user is logged in (either student or professor)
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: login.php');
    exit();
}

// Add session validation to prevent session hijacking
if (!isset($_SESSION['initiated']) || !isset($_SESSION['last_activity'])) {
    session_destroy();
    header('Location: login.php');
    exit();
}

// Check session timeout (2 hours)
$timeout_duration = 7200;
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $timeout_duration)) {
    session_destroy();
    header('Location: login.php');
    exit();
}

// Update last activity time
$_SESSION['last_activity'] = time();

// Generate unique page identifier to prevent conflicts between multiple tabs
$page_id = uniqid('profile_', true);

// Handle AJAX update requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_profile') {
    header('Content-Type: application/json');
    
    // Validate session again for AJAX requests
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
        echo json_encode(['success' => false, 'message' => 'Session expired. Please login again.']);
        exit();
    }
    
    $user_type = $_SESSION['role'];
    $user_id = $_SESSION['user_id'];
    $response = ['success' => false, 'message' => ''];
    
    // Validate required fields
    $required_fields = ['first_name', 'last_name', 'email', 'mobile_no'];
    foreach ($required_fields as $field) {
        if (empty($_POST[$field])) {
            $response['message'] = 'All required fields must be filled.';
            echo json_encode($response);
            exit();
        }
    }
    
    try {
        if ($user_type === 'student') {
            // Sanitize and validate all fields to avoid constraint violations
            $pin = isset($_POST['pin']) && $_POST['pin'] !== '' ? trim($_POST['pin']) : null;
            $guardian_mobile_no = isset($_POST['guardian_mobile_no']) && preg_match('/^[0-9]{10}$/', trim($_POST['guardian_mobile_no'] ?? '')) ? trim($_POST['guardian_mobile_no']) : null;
            $mobile_no = isset($_POST['mobile_no']) && preg_match('/^[0-9]{10}$/', trim($_POST['mobile_no'] ?? '')) ? trim($_POST['mobile_no']) : null;
            $dob = isset($_POST['dob']) && $_POST['dob'] !== '' ? trim($_POST['dob']) : null;
            $enrolment_date = isset($_POST['enrolment_date']) && $_POST['enrolment_date'] !== '' ? trim($_POST['enrolment_date']) : null;
            $registration_date = isset($_POST['registration_date']) && $_POST['registration_date'] !== '' ? trim($_POST['registration_date']) : null;
            $email = isset($_POST['email']) && filter_var(trim($_POST['email']), FILTER_VALIDATE_EMAIL) ? trim($_POST['email']) : null;
            $guardian_email = isset($_POST['guardian_email']) && trim($_POST['guardian_email']) !== '' && filter_var(trim($_POST['guardian_email']), FILTER_VALIDATE_EMAIL) ? trim($_POST['guardian_email']) : null;
            $gender = isset($_POST['gender']) && in_array($_POST['gender'], ['Male','Female','Other']) ? $_POST['gender'] : null;
            $status = isset($_POST['status']) && in_array($_POST['status'], ['Graduated','Ongoing']) ? $_POST['status'] : null;

            // Backend validation for required fields
            if (!$mobile_no) {
                $response['message'] = 'Mobile number is required and must be a 10-digit number.';
                echo json_encode($response);
                exit();
            }
            if (!$guardian_mobile_no) {
                $response['message'] = 'Guardian mobile number is required and must be a 10-digit number.';
                echo json_encode($response);
                exit();
            }
            if (!$email) {
                $response['message'] = 'Email is required and must be valid.';
                echo json_encode($response);
                exit();
            }
            // Validate department_id for students
            if (!isset($_POST['department_id']) || empty(trim($_POST['department_id']))) {
                $response['message'] = 'Department is required.';
                echo json_encode($response);
                exit();
            }
            // Check if department exists
            $dept_stmt = $pdo->prepare("SELECT department_id FROM department WHERE department_id = ?");
            $dept_stmt->execute([trim($_POST['department_id'])]);
            if (!$dept_stmt->fetch()) {
                $response['message'] = 'Selected department does not exist.';
                echo json_encode($response);
                exit();
            }

            $stmt = $pdo->prepare("UPDATE student_info SET 
                first_name = ?, 
                middle_name = ?,
                last_name = ?, 
                gender = ?,
                dob = ?,
                blood_group = ?,
                email = ?, 
                mobile_no = ?, 
                address = ?,
                city = ?,
                state = ?,
                pin = ?,
                country = ?,
                guardian_first_name = ?,
                guardian_middle_name = ?,
                guardian_last_name = ?,
                guardian_mobile_no = ?,
                guardian_email = ?,
                student_type = ?,
                department_id = ?,
                enrolment_date = ?,
                registration_no = ?,
                registration_date = ?,
                category_of_phd = ?,
                status = ?
                WHERE student_id = ?");
            
            $result = $stmt->execute([
                trim($_POST['first_name']),
                trim($_POST['middle_name'] ?? null),
                trim($_POST['last_name']),
                $gender,
                $dob,
                trim($_POST['blood_group'] ?? null),
                $email,
                $mobile_no,
                trim($_POST['address'] ?? null),
                trim($_POST['city'] ?? null),
                trim($_POST['state'] ?? null),
                $pin,
                trim($_POST['country'] ?? null),
                trim($_POST['guardian_first_name'] ?? null),
                trim($_POST['guardian_middle_name'] ?? null),
                trim($_POST['guardian_last_name'] ?? null),
                $guardian_mobile_no,
                $guardian_email,
                trim($_POST['student_type'] ?? null),
                trim($_POST['department_id'] ?? null),
                $enrolment_date,
                trim($_POST['registration_no'] ?? null),
                $registration_date,
                trim($_POST['category_of_phd'] ?? null),
                $status,
                $user_id
            ]);
        } elseif ($user_type === 'professor') {
            $doj = isset($_POST['doj']) && $_POST['doj'] !== '' ? trim($_POST['doj']) : null;
            $dol = isset($_POST['dol']) && $_POST['dol'] !== '' ? trim($_POST['dol']) : null;
            $email = isset($_POST['email']) && filter_var(trim($_POST['email']), FILTER_VALIDATE_EMAIL) ? trim($_POST['email']) : null;
            $mobile_no = isset($_POST['mobile_no']) && preg_match('/^[0-9]{10,}$/', trim($_POST['mobile_no'] ?? '')) ? trim($_POST['mobile_no']) : null;
            if (!$mobile_no) {
                $response['message'] = 'Mobile number is required and must be at least 10 digits.';
                echo json_encode($response);
                exit();
            }
            if (!$email) {
                $response['message'] = 'Email is required and must be valid.';
                echo json_encode($response);
                exit();
            }
            // Validate department_id for professors
            if (!isset($_POST['department_id']) || empty(trim($_POST['department_id']))) {
                $response['message'] = 'Department is required.';
                echo json_encode($response);
                exit();
            }
            $dept_stmt = $pdo->prepare("SELECT department_id FROM department WHERE department_id = ?");
            $dept_stmt->execute([trim($_POST['department_id'])]);
            if (!$dept_stmt->fetch()) {
                $response['message'] = 'Selected department does not exist.';
                echo json_encode($response);
                exit();
            }
            $stmt = $pdo->prepare("UPDATE staff_info SET 
                first_name = ?, 
                middle_name = ?,
                last_name = ?, 
                designation = ?,
                email = ?, 
                mobile_no = ?, 
                department_id = ?,
                doj = ?,
                dol = ?
                WHERE staff_id = ? AND staff_type = 'Professor'");
            
            $result = $stmt->execute([
                trim($_POST['first_name']),
                trim($_POST['middle_name'] ?? null),
                trim($_POST['last_name']),
                trim($_POST['designation'] ?? null),
                $email,
                $mobile_no,
                trim($_POST['department_id'] ?? null),
                $doj,
                $dol,
                $user_id
            ]);
        } elseif ($user_type === 'hod') {
            $doj = isset($_POST['doj']) && $_POST['doj'] !== '' ? trim($_POST['doj']) : null;
            $dol = isset($_POST['dol']) && $_POST['dol'] !== '' ? trim($_POST['dol']) : null;
            $email = isset($_POST['email']) && filter_var(trim($_POST['email']), FILTER_VALIDATE_EMAIL) ? trim($_POST['email']) : null;
            $mobile_no = isset($_POST['mobile_no']) && preg_match('/^[0-9]{10,}$/', trim($_POST['mobile_no'] ?? '')) ? trim($_POST['mobile_no']) : null;
            if (!$mobile_no) {
                $response['message'] = 'Mobile number is required and must be at least 10 digits.';
                echo json_encode($response);
                exit();
            }
            if (!$email) {
                $response['message'] = 'Email is required and must be valid.';
                echo json_encode($response);
                exit();
            }
            $stmt = $pdo->prepare("UPDATE hod SET 
                first_name = ?, 
                middle_name = ?,
                last_name = ?,
                designation = ?,
                email = ?, 
                mobile_no = ?, 
                department_name = ?,
                doj = ?,
                dol = ?
                WHERE hod_id = ?");
            
            $result = $stmt->execute([
                trim($_POST['first_name']),
                trim($_POST['middle_name'] ?? null),
                trim($_POST['last_name']),
                trim($_POST['designation'] ?? null),
                $email,
                $mobile_no,
                trim($_POST['department_name'] ?? null),
                $doj,
                $dol,
                $user_id
            ]);
        } elseif ($user_type === 'employee') {
            $doj = isset($_POST['doj']) && $_POST['doj'] !== '' ? trim($_POST['doj']) : null;
            $dol = isset($_POST['dol']) && $_POST['dol'] !== '' ? trim($_POST['dol']) : null;
            $email = isset($_POST['email']) && filter_var(trim($_POST['email']), FILTER_VALIDATE_EMAIL) ? trim($_POST['email']) : null;
            $mobile_no = isset($_POST['mobile_no']) && preg_match('/^[0-9]{10,}$/', trim($_POST['mobile_no'] ?? '')) ? trim($_POST['mobile_no']) : null;
            if (!$mobile_no) {
                $response['message'] = 'Mobile number is required and must be at least 10 digits.';
                echo json_encode($response);
                exit();
            }
            if (!$email) {
                $response['message'] = 'Email is required and must be valid.';
                echo json_encode($response);
                exit();
            }
            // Validate department_id for employees
            if (!isset($_POST['department_id']) || empty(trim($_POST['department_id']))) {
                $response['message'] = 'Department is required.';
                echo json_encode($response);
                exit();
            }
            $dept_stmt = $pdo->prepare("SELECT department_id FROM department WHERE department_id = ?");
            $dept_stmt->execute([trim($_POST['department_id'])]);
            if (!$dept_stmt->fetch()) {
                $response['message'] = 'Selected department does not exist.';
                echo json_encode($response);
                exit();
            }
            $stmt = $pdo->prepare("UPDATE staff_info SET 
                first_name = ?, 
                middle_name = ?,
                last_name = ?, 
                designation = ?,
                email = ?, 
                mobile_no = ?, 
                department_id = ?,
                doj = ?,
                dol = ?
                WHERE staff_id = ? AND staff_type = 'Employee'");
            
            $result = $stmt->execute([
                trim($_POST['first_name']),
                trim($_POST['middle_name'] ?? null),
                trim($_POST['last_name']),
                trim($_POST['designation'] ?? null),
                $email,
                $mobile_no,
                trim($_POST['department_id'] ?? null),
                $doj,
                $dol,
                $user_id
            ]);
        }
        
        if ($result) {
            $response['success'] = true;
            $response['message'] = 'Profile updated successfully!';
        } else {
            $response['message'] = 'Failed to update profile.';
        }
    } catch (PDOException $e) {
        error_log("Database error updating profile: " . $e->getMessage());
        $response['message'] = 'Database error occurred: ' . $e->getMessage();
    }
    
    echo json_encode($response);
    exit();
}

// Determine user type and get appropriate data
$user_type = $_SESSION['role'];
$user_data = null;
$error = '';

if ($user_type === 'student') {
    // Fetch student information
    try {
        $stmt = $pdo->prepare("SELECT * FROM student_info WHERE student_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user_data = $stmt->fetch();

        if (!$user_data) {
            $error = "Student information not found.";
        } else {
            // Ensure we have the full name
            $user_data['full_name'] = trim($user_data['first_name'] . ' ' . $user_data['last_name']);
        }
    } catch (PDOException $e) {
        error_log("Database error fetching student profile: " . $e->getMessage());
        $error = "Error fetching student information.";
    }
} elseif ($user_type === 'professor') {
    // Fetch professor information
    try {
        $stmt = $pdo->prepare("SELECT * FROM staff_info WHERE staff_id = ? AND staff_type = 'Professor'");
        $stmt->execute([$_SESSION['user_id']]);
        $user_data = $stmt->fetch();

        if (!$user_data) {
            $error = "Professor information not found.";
        } else {
            // Ensure we have the full name
            $user_data['full_name'] = trim($user_data['first_name'] . ' ' . $user_data['last_name']);
        }
    } catch (PDOException $e) {
        error_log("Database error fetching professor profile: " . $e->getMessage());
        $error = "Error fetching professor information.";
    }
} elseif ($user_type === 'hod') {
    // Fetch HOD information
    try {
        $stmt = $pdo->prepare("SELECT * FROM hod WHERE hod_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user_data = $stmt->fetch();

        if (!$user_data) {
            $error = "HOD information not found.";
        } else {
            // Ensure we have the full name
            $user_data['full_name'] = trim($user_data['first_name'] . ' ' . $user_data['last_name']);
        }
    } catch (PDOException $e) {
        error_log("Database error fetching HOD profile: " . $e->getMessage());
        $error = "Error fetching HOD information.";
    }
} elseif ($user_type === 'employee') {
    // Fetch staff information
    try {
        $stmt = $pdo->prepare("SELECT * FROM staff_info WHERE staff_id = ? AND staff_type = 'Employee'");
        $stmt->execute([$_SESSION['user_id']]);
        $user_data = $stmt->fetch();

        if (!$user_data) {
            $error = "Staff information not found.";
        } else {
            // Ensure we have the full name
            $user_data['full_name'] = trim($user_data['first_name'] . ' ' . $user_data['last_name']);
        }
    } catch (PDOException $e) {
        error_log("Database error fetching staff profile: " . $e->getMessage());
        $error = "Error fetching staff information.";
    }
} else {
    $error = "Invalid user session.";
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= ucfirst($user_type) ?> Profile</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Roboto', sans-serif;
            background-color: #f4f6f9;
            margin: 0;
            padding: 0;
            color: #444;
        }
        .sidebar {
            min-height: 100vh;
            background: #343a40;
            color: #fff;
            padding: 20px 0;
        }
        .sidebar h3 {
            color: #fff;
            font-weight: 500;
            text-align: center;
            margin-bottom: 30px;
        }
        .sidebar .nav-link {
            color: #ccc;
            margin: 8px 0;
            padding: 10px 20px;
            border-radius: 4px;
            transition: background 0.3s, color 0.3s;
        }
        .sidebar .nav-link i {
            margin-right: 10px;
        }
        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            background: #495057;
            color: #fff;
        }
        .main-content {
            padding: 30px 20px;
        }
        .main-content h2 {
            font-weight: 500;
            color: #343a40;
        }
        .main-content p.text-muted {
            font-size: 0.9rem;
        }
        .card {
            border: none;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            margin-bottom: 25px;
        }
        .card-header {
            background-color: #e9ecef;
            border-bottom: none;
            padding: 15px 20px;
            border-top-left-radius: 8px;
            border-top-right-radius: 8px;
        }
        .card-title {
            margin: 0;
            font-weight: 500;
            color: #495057;
        }
        .btn-primary {
            background-color: #007bff;
            border-color: #007bff;
            font-weight: 500;
        }
        .btn-primary:hover {
            background-color: #0069d9;
            border-color: #0062cc;
        }
        .btn-outline-primary {
            color: #007bff;
            border-color: #007bff;
        }
        .btn-outline-primary:hover {
            background-color: #007bff;
            color: #fff;
        }
        .btn-success {
            background-color: #28a745;
            border-color: #28a745;
        }
        .btn-success:hover {
            background-color: #218838;
            border-color: #1e7e34;
        }
        .btn-secondary {
            background-color: #6c757d;
            border-color: #6c757d;
        }
        .btn-secondary:hover {
            background-color: #5a6268;
            border-color: #545b62;
        }
        .profile-field {
            margin-bottom: 15px;
        }
        .profile-field .form-control {
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 8px 12px;
        }
        .profile-field .form-control:focus {
            border-color: #007bff;
            box-shadow: 0 0 0 0.2rem rgba(0,123,255,.25);
        }
        .view-mode .profile-field {
            border-bottom: 1px solid #eee;
            padding-bottom: 8px;
            margin-bottom: 8px;
        }
        .view-mode .profile-field:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }
        .edit-mode .profile-field {
            border-bottom: none;
            padding-bottom: 0;
        }
        .field-label {
            font-weight: bold;
            display: inline-block;
            min-width: 120px;
            margin-right: 10px;
            margin-bottom: 5px;
        }
        .field-value {
            font-weight: normal;
        }
        .edit-actions {
            margin-top: 20px;
            padding-top: 15px;
            border-top: 1px solid #eee;
        }
        .alert {
            margin-bottom: 20px;
        }
        .loading {
            opacity: 0.6;
            pointer-events: none;
        }
        @media (max-width: 767.98px) {
            .sidebar {
                text-align: center;
            }
            .sidebar .nav-link {
                margin: 5px auto;
                width: 90%;
            }
            .main-content {
                padding: 15px 10px;
            }
        }
    </style>
</head>
<body data-page-id="<?= htmlspecialchars($page_id) ?>" data-user-type="<?= htmlspecialchars($user_type) ?>" data-user-id="<?= htmlspecialchars($_SESSION['user_id']) ?>">
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar (dynamic based on user role) -->
            <nav class="col-md-3 col-lg-2 sidebar">
                <?php if ($user_type === 'student'): ?>
                    <h3>Student Portal</h3>
                    <div class="nav flex-column">
                        <a class="nav-link" href="dashboard.php">
                            <i class="fas fa-home"></i> Dashboard
                        </a>
                        <a class="nav-link" href="semester_registration.php">
                            <i class="fas fa-calendar-plus"></i> Register Semester
                        </a>
                        <a class="nav-link" href="marks.php">
                            <i class="fas fa-file-alt"></i> Marks
                        </a>
                        <a class="nav-link active" href="profile.php">
                            <i class="fas fa-user"></i> Profile
                        </a>
                        <a class="nav-link" href="logout.php">
                            <i class="fas fa-sign-out-alt"></i> Logout
                        </a>
                    </div>
                <?php elseif ($user_type === 'hod'): ?>
                    <h3>HOD Portal</h3>
                    <div class="nav flex-column">
                        <a class="nav-link" href="hod_dashboard.php">
                            <i class="fas fa-home"></i> Dashboard
                        </a>
                        <a class="nav-link" href="#students" onclick="window.location='hod_dashboard.php#students'">
                            <i class="fas fa-user-graduate"></i> Students
                        </a>
                        <a class="nav-link" href="#staff" onclick="window.location='hod_dashboard.php#staff'">
                            <i class="fas fa-users"></i> Staff
                        </a>
                        <a class="nav-link" href="#subjects" onclick="window.location='hod_dashboard.php#subjects'">
                            <i class="fas fa-book"></i> Subjects
                        </a>
                        <a class="nav-link" href="#registrations" onclick="window.location='hod_dashboard.php#registrations'">
                            <i class="fas fa-clipboard-list"></i> Registrations
                        </a>
                        <a class="nav-link" href="hod_assign_staff.php">
                            <i class="fas fa-user-plus"></i> Assign Employee
                        </a>
                        <a class="nav-link active" href="profile.php">
                            <i class="fas fa-user"></i> Profile
                        </a>
                        <a class="nav-link" href="logout.php">
                            <i class="fas fa-sign-out-alt"></i> Logout
                        </a>
                    </div>
                <?php elseif ($user_type === 'professor'): ?>
                    <h3>Professor Portal</h3>
                    <div class="nav flex-column">
                        <a class="nav-link" href="professor_dashboard.php">
                            <i class="fas fa-tachometer-alt"></i> Dashboard
                        </a>
                        <a class="nav-link active" href="profile.php">
                            <i class="fas fa-user"></i> Profile
                        </a>
                        <a class="nav-link" href="logout.php">
                            <i class="fas fa-sign-out-alt"></i> Logout
                        </a>
                    </div>
                <?php elseif ($user_type === 'employee'): ?>
                    <h3>Employee Portal</h3>
                    <div class="nav flex-column">
                        <a class="nav-link" href="employee_dashboard.php">
                            <i class="fas fa-tachometer-alt"></i> Dashboard
                        </a>
                        <a class="nav-link active" href="profile.php">
                            <i class="fas fa-user"></i> Profile
                        </a>
                        <a class="nav-link" href="logout.php">
                            <i class="fas fa-sign-out-alt"></i> Logout
                        </a>
                    </div>
                <?php endif; ?>
            </nav>
            <main class="col-md-9 col-lg-10 main-content">
                <div class="row mb-4">
                    <div class="col">
                        <h2><?= ucfirst($user_type) ?> Profile</h2>
                    </div>
                </div>
                <!-- Alert container for messages -->
                <div id="alertContainer"></div>
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                <?php elseif ($user_type === 'hod' && $user_data): ?>
                    <form id="profileForm">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="card-title mb-0">HOD Profile</h5>
                                <button type="button" class="btn btn-sm btn-outline-primary edit-btn" id="editBtn">
                                    <i class="fas fa-edit"></i> Edit
                                </button>
                            </div>
                            <div class="card-body view-mode" id="personalInfo">
                                <div class="container-fluid">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <h6 class="text-primary mt-2 mb-3">Personal Details</h6>
                                            <div class="profile-field">
                                                <span class="field-label">First Name:</span>
                                                <span class="field-value" id="display_first_name"><?= htmlspecialchars($user_data['first_name'] ?? '') ?></span>
                                                <input type="text" class="form-control d-none" id="edit_first_name" name="first_name" value="<?= htmlspecialchars($user_data['first_name'] ?? '') ?>">
                                            </div>
                                            <div class="profile-field">
                                                <span class="field-label">Middle Name:</span>
                                                <span class="field-value" id="display_middle_name"><?= htmlspecialchars($user_data['middle_name'] ?? '') ?></span>
                                                <input type="text" class="form-control d-none" id="edit_middle_name" name="middle_name" value="<?= htmlspecialchars($user_data['middle_name'] ?? '') ?>">
                                            </div>
                                            <div class="profile-field">
                                                <span class="field-label">Last Name:</span>
                                                <span class="field-value" id="display_last_name"><?= htmlspecialchars($user_data['last_name'] ?? '') ?></span>
                                                <input type="text" class="form-control d-none" id="edit_last_name" name="last_name" value="<?= htmlspecialchars($user_data['last_name'] ?? '') ?>">
                                            </div>
                                            <div class="profile-field">
                                                <span class="field-label">Designation:</span>
                                                <span class="field-value" id="display_designation"><?= htmlspecialchars($user_data['designation'] ?? '') ?></span>
                                                <input type="text" class="form-control d-none" id="edit_designation" name="designation" value="<?= htmlspecialchars($user_data['designation'] ?? '') ?>">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <h6 class="text-primary mt-2 mb-3">Contact & Department</h6>
                                            <div class="profile-field">
                                                <span class="field-label">Mobile No:</span>
                                                <span class="field-value" id="display_mobile_no"><?= htmlspecialchars($user_data['mobile_no'] ?? '') ?></span>
                                                <input type="text" class="form-control d-none" id="edit_mobile_no" name="mobile_no" value="<?= htmlspecialchars($user_data['mobile_no'] ?? '') ?>">
                                            </div>
                                            <div class="profile-field">
                                                <span class="field-label">Email:</span>
                                                <span class="field-value" id="display_email"><?= htmlspecialchars($user_data['email'] ?? '') ?></span>
                                                <input type="email" class="form-control d-none" id="edit_email" name="email" value="<?= htmlspecialchars($user_data['email'] ?? '') ?>">
                                            </div>
                                            <div class="profile-field">
                                                <span class="field-label">Department Name:</span>
                                                <span class="field-value" id="display_department_name"><?= htmlspecialchars($user_data['department_name'] ?? '') ?></span>
                                                <input type="text" class="form-control d-none" id="edit_department_name" name="department_name" value="<?= htmlspecialchars($user_data['department_name'] ?? '') ?>">
                                            </div>
                                            <div class="profile-field">
                                                <span class="field-label">Date of Joining:</span>
                                                <span class="field-value" id="display_doj"><?= htmlspecialchars($user_data['doj'] ?? '') ?></span>
                                                <input type="date" class="form-control d-none" id="edit_doj" name="doj" value="<?= htmlspecialchars($user_data['doj'] ?? '') ?>">
                                            </div>
                                            <div class="profile-field">
                                                <span class="field-label">Date of Leaving:</span>
                                                <span class="field-value" id="display_dol"><?= htmlspecialchars($user_data['dol'] ?? '') ?></span>
                                                <input type="date" class="form-control d-none" id="edit_dol" name="dol" value="<?= htmlspecialchars($user_data['dol'] ?? '') ?>">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="edit-actions d-none mt-3">
                                    <button type="button" class="btn btn-success btn-save" id="saveBtn">
                                        <i class="fas fa-save"></i> Save Changes
                                    </button>
                                    <button type="button" class="btn btn-secondary btn-cancel ms-2" id="cancelBtn">
                                        <i class="fas fa-times"></i> Cancel
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>
                <?php elseif ($user_type === 'student' && $user_data): ?>
                    <form id="profileForm">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="card-title mb-0">Student Profile</h5>
                                <button type="button" class="btn btn-sm btn-outline-primary edit-btn" id="editBtn">
                                    <i class="fas fa-edit"></i> Edit
                                </button>
                            </div>
                            <div class="card-body view-mode" id="personalInfo">
                                <div class="container-fluid">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <h6 class="text-primary mt-2 mb-3">Personal Details</h6>
                                            <div class="profile-field">
                                                <span class="field-label">First Name:</span>
                                                <span class="field-value" id="display_first_name"><?= htmlspecialchars($user_data['first_name'] ?? '') ?></span>
                                                <input type="text" class="form-control d-none" id="edit_first_name" name="first_name" value="<?= htmlspecialchars($user_data['first_name'] ?? '') ?>">
                                            </div>
                                            <div class="profile-field">
                                                <span class="field-label">Middle Name:</span>
                                                <span class="field-value" id="display_middle_name"><?= htmlspecialchars($user_data['middle_name'] ?? '') ?></span>
                                                <input type="text" class="form-control d-none" id="edit_middle_name" name="middle_name" value="<?= htmlspecialchars($user_data['middle_name'] ?? '') ?>">
                                            </div>
                                            <div class="profile-field">
                                                <span class="field-label">Last Name:</span>
                                                <span class="field-value" id="display_last_name"><?= htmlspecialchars($user_data['last_name'] ?? '') ?></span>
                                                <input type="text" class="form-control d-none" id="edit_last_name" name="last_name" value="<?= htmlspecialchars($user_data['last_name'] ?? '') ?>">
                                            </div>
                                            <div class="profile-field">
                                                <span class="field-label">Gender:</span>
                                                <span class="field-value" id="display_gender"><?= htmlspecialchars($user_data['gender'] ?? '') ?></span>
                                                <select class="form-control d-none" id="edit_gender" name="gender">
                                                    <option value="">Select Gender</option>
                                                    <option value="Male" <?= ($user_data['gender'] ?? '') === 'Male' ? 'selected' : '' ?>>Male</option>
                                                    <option value="Female" <?= ($user_data['gender'] ?? '') === 'Female' ? 'selected' : '' ?>>Female</option>
                                                    <option value="Other" <?= ($user_data['gender'] ?? '') === 'Other' ? 'selected' : '' ?>>Other</option>
                                                </select>
                                            </div>
                                            <div class="profile-field">
                                                <span class="field-label">Date of Birth:</span>
                                                <span class="field-value" id="display_dob"><?= htmlspecialchars($user_data['dob'] ?? '') ?></span>
                                                <input type="date" class="form-control d-none" id="edit_dob" name="dob" value="<?= htmlspecialchars($user_data['dob'] ?? '') ?>">
                                            </div>
                                            <div class="profile-field">
                                                <span class="field-label">Blood Group:</span>
                                                <span class="field-value" id="display_blood_group"><?= htmlspecialchars($user_data['blood_group'] ?? '') ?></span>
                                                <input type="text" class="form-control d-none" id="edit_blood_group" name="blood_group" value="<?= htmlspecialchars($user_data['blood_group'] ?? '') ?>">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <h6 class="text-primary mt-2 mb-3">Contact & Address</h6>
                                            <div class="profile-field">
                                                <span class="field-label">Mobile No:</span>
                                                <span class="field-value" id="display_mobile_no"><?= htmlspecialchars($user_data['mobile_no'] ?? '') ?></span>
                                                <input type="text" class="form-control d-none" id="edit_mobile_no" name="mobile_no" value="<?= htmlspecialchars($user_data['mobile_no'] ?? '') ?>">
                                            </div>
                                            <div class="profile-field">
                                                <span class="field-label">Email:</span>
                                                <span class="field-value" id="display_email"><?= htmlspecialchars($user_data['email'] ?? '') ?></span>
                                                <input type="email" class="form-control d-none" id="edit_email" name="email" value="<?= htmlspecialchars($user_data['email'] ?? '') ?>">
                                            </div>
                                            <div class="profile-field">
                                                <span class="field-label">Address:</span>
                                                <span class="field-value" id="display_address"><?= nl2br(htmlspecialchars($user_data['address'] ?? '')) ?></span>
                                                <textarea class="form-control d-none" id="edit_address" name="address" rows="2"><?= htmlspecialchars($user_data['address'] ?? '') ?></textarea>
                                            </div>
                                            <div class="profile-field">
                                                <span class="field-label">City:</span>
                                                <span class="field-value" id="display_city"><?= htmlspecialchars($user_data['city'] ?? '') ?></span>
                                                <input type="text" class="form-control d-none" id="edit_city" name="city" value="<?= htmlspecialchars($user_data['city'] ?? '') ?>">
                                            </div>
                                            <div class="profile-field">
                                                <span class="field-label">State:</span>
                                                <span class="field-value" id="display_state"><?= htmlspecialchars($user_data['state'] ?? '') ?></span>
                                                <input type="text" class="form-control d-none" id="edit_state" name="state" value="<?= htmlspecialchars($user_data['state'] ?? '') ?>">
                                            </div>
                                            <div class="profile-field">
                                                <span class="field-label">Pin:</span>
                                                <span class="field-value" id="display_pin"><?= htmlspecialchars($user_data['pin'] ?? '') ?></span>
                                                <input type="text" class="form-control d-none" id="edit_pin" name="pin" value="<?= htmlspecialchars($user_data['pin'] ?? '') ?>">
                                            </div>
                                            <div class="profile-field">
                                                <span class="field-label">Country:</span>
                                                <span class="field-value" id="display_country"><?= htmlspecialchars($user_data['country'] ?? '') ?></span>
                                                <input type="text" class="form-control d-none" id="edit_country" name="country" value="<?= htmlspecialchars($user_data['country'] ?? '') ?>">
                                            </div>
                                        </div>
                                    </div>
                                    <hr>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <h6 class="text-primary mt-2 mb-3">Guardian Details</h6>
                                            <div class="profile-field">
                                                <span class="field-label">Guardian First Name:</span>
                                                <span class="field-value" id="display_guardian_first_name"><?= htmlspecialchars($user_data['guardian_first_name'] ?? '') ?></span>
                                                <input type="text" class="form-control d-none" id="edit_guardian_first_name" name="guardian_first_name" value="<?= htmlspecialchars($user_data['guardian_first_name'] ?? '') ?>">
                                            </div>
                                            <div class="profile-field">
                                                <span class="field-label">Guardian Middle Name:</span>
                                                <span class="field-value" id="display_guardian_middle_name"><?= htmlspecialchars($user_data['guardian_middle_name'] ?? '') ?></span>
                                                <input type="text" class="form-control d-none" id="edit_guardian_middle_name" name="guardian_middle_name" value="<?= htmlspecialchars($user_data['guardian_middle_name'] ?? '') ?>">
                                            </div>
                                            <div class="profile-field">
                                                <span class="field-label">Guardian Last Name:</span>
                                                <span class="field-value" id="display_guardian_last_name"><?= htmlspecialchars($user_data['guardian_last_name'] ?? '') ?></span>
                                                <input type="text" class="form-control d-none" id="edit_guardian_last_name" name="guardian_last_name" value="<?= htmlspecialchars($user_data['guardian_last_name'] ?? '') ?>">
                                            </div>
                                            <div class="profile-field">
                                                <span class="field-label">Guardian Mobile No:</span>
                                                <span class="field-value" id="display_guardian_mobile_no"><?= htmlspecialchars($user_data['guardian_mobile_no'] ?? '') ?></span>
                                                <input type="text" class="form-control d-none" id="edit_guardian_mobile_no" name="guardian_mobile_no" value="<?= htmlspecialchars($user_data['guardian_mobile_no'] ?? '') ?>">
                                            </div>
                                            <div class="profile-field">
                                                <span class="field-label">Guardian Email:</span>
                                                <span class="field-value" id="display_guardian_email"><?= htmlspecialchars($user_data['guardian_email'] ?? '') ?></span>
                                                <input type="email" class="form-control d-none" id="edit_guardian_email" name="guardian_email" value="<?= htmlspecialchars($user_data['guardian_email'] ?? '') ?>">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <h6 class="text-primary mt-2 mb-3">Academic Details</h6>
                                            <div class="profile-field">
                                                <span class="field-label">Student Type:</span>
                                                <span class="field-value" id="display_student_type"><?= htmlspecialchars($user_data['student_type'] ?? '') ?></span>
                                                <select class="form-control d-none" id="edit_student_type" name="student_type">
                                                    <option value="UG" <?= ($user_data['student_type'] ?? '') === 'UG' ? 'selected' : '' ?>>Undergraduate (UG)</option>
                                                    <option value="PG" <?= ($user_data['student_type'] ?? '') === 'PG' ? 'selected' : '' ?>>Postgraduate (PG)</option>
                                                    <option value="PhD" <?= ($user_data['student_type'] ?? '') === 'PhD' ? 'selected' : '' ?>>PhD</option>
                                                    <option value="Other" <?= ($user_data['student_type'] ?? '') === 'Other' ? 'selected' : '' ?>>Other</option>
                                                </select>
                                            </div>
                                            <div class="profile-field">
                                                <span class="field-label">Department:</span>
                                                <span class="field-value" id="display_department_id">
                                                    <?php 
                                                    $dept_names = [
                                                        'CSE' => 'Computer Science and Engineering',
                                                        'ECE' => 'Electronics and Communication Engineering', 
                                                        'ME' => 'Mechanical Engineering',
                                                        'CE' => 'Civil Engineering',
                                                        'EE' => 'Electrical Engineering'
                                                    ];
                                                    echo htmlspecialchars($dept_names[$user_data['department_id']] ?? $user_data['department_id'] ?? 'N/A');
                                                    ?>
                                                </span>
                                                <select class="form-control d-none" id="edit_department_id" name="department_id">
                                                    <option value="">Select Department</option>
                                                    <option value="CSE" <?= ($user_data['department_id'] ?? '') === 'CSE' ? 'selected' : '' ?>>Computer Science and Engineering</option>
                                                    <option value="ECE" <?= ($user_data['department_id'] ?? '') === 'ECE' ? 'selected' : '' ?>>Electronics and Communication Engineering</option>
                                                    <option value="ME" <?= ($user_data['department_id'] ?? '') === 'ME' ? 'selected' : '' ?>>Mechanical Engineering</option>
                                                    <option value="CE" <?= ($user_data['department_id'] ?? '') === 'CE' ? 'selected' : '' ?>>Civil Engineering</option>
                                                    <option value="EE" <?= ($user_data['department_id'] ?? '') === 'EE' ? 'selected' : '' ?>>Electrical Engineering</option>
                                                </select>
                                            </div>
                                            <div class="profile-field">
                                                <span class="field-label">Enrolment Date:</span>
                                                <span class="field-value" id="display_enrolment_date"><?= htmlspecialchars($user_data['enrolment_date'] ?? '') ?></span>
                                                <input type="date" class="form-control d-none" id="edit_enrolment_date" name="enrolment_date" value="<?= htmlspecialchars($user_data['enrolment_date'] ?? '') ?>">
                                            </div>
                                            <div class="profile-field">
                                                <span class="field-label">Registration No:</span>
                                                <span class="field-value" id="display_registration_no"><?= htmlspecialchars($user_data['registration_no'] ?? '') ?></span>
                                                <input type="text" class="form-control d-none" id="edit_registration_no" name="registration_no" value="<?= htmlspecialchars($user_data['registration_no'] ?? '') ?>">
                                            </div>
                                            <div class="profile-field">
                                                <span class="field-label">Registration Date:</span>
                                                <span class="field-value" id="display_registration_date"><?= htmlspecialchars($user_data['registration_date'] ?? '') ?></span>
                                                <input type="date" class="form-control d-none" id="edit_registration_date" name="registration_date" value="<?= htmlspecialchars($user_data['registration_date'] ?? '') ?>">
                                            </div>
                                            <div class="profile-field">
                                                <span class="field-label">Category of PhD:</span>
                                                <span class="field-value" id="display_category_of_phd"><?= htmlspecialchars($user_data['category_of_phd'] ?? '') ?></span>
                                                <input type="text" class="form-control d-none" id="edit_category_of_phd" name="category_of_phd" value="<?= htmlspecialchars($user_data['category_of_phd'] ?? '') ?>">
                                            </div>
                                            <div class="profile-field">
                                                <span class="field-label">Status:</span>
                                                <span class="field-value" id="display_status"><?= htmlspecialchars($user_data['status'] ?? '') ?></span>
                                                <select class="form-control d-none" id="edit_status" name="status">
                                                    <option value="Ongoing" <?= ($user_data['status'] ?? '') === 'Ongoing' ? 'selected' : '' ?>>Ongoing</option>
                                                    <option value="Graduated" <?= ($user_data['status'] ?? '') === 'Graduated' ? 'selected' : '' ?>>Graduated</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="edit-actions d-none mt-3">
                                    <button type="button" class="btn btn-success btn-save" id="saveBtn">
                                        <i class="fas fa-save"></i> Save Changes
                                    </button>
                                    <button type="button" class="btn btn-secondary btn-cancel ms-2" id="cancelBtn">
                                        <i class="fas fa-times"></i> Cancel
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>

                <?php endif; ?>

            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            function showFieldError(fieldId, message) {
                let field = document.getElementById(fieldId);
                if (!field) return;
                let errorElem = document.getElementById(fieldId + '_error');
                if (!errorElem) {
                    errorElem = document.createElement('div');
                    errorElem.id = fieldId + '_error';
                    errorElem.className = 'text-danger small mt-1';
                    field.parentNode.appendChild(errorElem);
                }
                errorElem.textContent = message;
                field.classList.add('is-invalid');
            }
            function clearFieldError(fieldId) {
                let field = document.getElementById(fieldId);
                if (!field) return;
                let errorElem = document.getElementById(fieldId + '_error');
                if (errorElem) errorElem.textContent = '';
                field.classList.remove('is-invalid');
            }
            function validateProfileForm(userType, editableFields) {
                let valid = true;
                // Clear all previous errors
                editableFields.forEach(field => clearFieldError('edit_' + field));
                // Required fields for all
                const requiredFields = ['first_name', 'last_name', 'email', 'mobile_no'];
                requiredFields.forEach(field => {
                    let elem = document.getElementById('edit_' + field);
                    if (elem && !elem.classList.contains('d-none')) {
                        let value = elem.value.trim();
                        if (!value) {
                            showFieldError('edit_' + field, 'This field is required.');
                            valid = false;
                        }
                    }
                });
                // Email format
                let emailElem = document.getElementById('edit_email');
                if (emailElem && !emailElem.classList.contains('d-none')) {
                    let emailVal = emailElem.value.trim();
                    if (emailVal && !/^\S+@\S+\.\S+$/.test(emailVal)) {
                        showFieldError('edit_email', 'Invalid email format.');
                        valid = false;
                    }
                }
                // Mobile number (10 digits)
                let mobileElem = document.getElementById('edit_mobile_no');
                if (mobileElem && !mobileElem.classList.contains('d-none')) {
                    let mobileVal = mobileElem.value.trim();
                    if (mobileVal && !/^\d{10}$/.test(mobileVal)) {
                        showFieldError('edit_mobile_no', 'Mobile number must be 10 digits.');
                        valid = false;
                    }
                }
                // Student-specific: guardian mobile/email
                if (userType === 'student') {
                    let guardianMobileElem = document.getElementById('edit_guardian_mobile_no');
                    if (guardianMobileElem && !guardianMobileElem.classList.contains('d-none')) {
                        let guardianMobileVal = guardianMobileElem.value.trim();
                        if (!guardianMobileVal) {
                            showFieldError('edit_guardian_mobile_no', 'This field is required.');
                            valid = false;
                        } else if (!/^\d{10}$/.test(guardianMobileVal)) {
                            showFieldError('edit_guardian_mobile_no', 'Guardian mobile number must be 10 digits.');
                            valid = false;
                        }
                    }
                    let guardianEmailElem = document.getElementById('edit_guardian_email');
                    if (guardianEmailElem && !guardianEmailElem.classList.contains('d-none')) {
                        let guardianEmailVal = guardianEmailElem.value.trim();
                        if (guardianEmailVal && !/^\S+@\S+\.\S+$/.test(guardianEmailVal)) {
                            showFieldError('edit_guardian_email', 'Invalid email format.');
                            valid = false;
                        }
                    }
                }
                return valid;
            }
            function setupProfileEdit(userType, editableFields) {
                const editBtn = document.getElementById('editBtn');
                const saveBtn = document.getElementById('saveBtn');
                const cancelBtn = document.getElementById('cancelBtn');
                const profileForm = document.getElementById('profileForm');
                const alertContainer = document.getElementById('alertContainer');
                let originalValues = {};
                let isEditing = false;
                function enterEditMode() {
                    if (isEditing) return;
                    isEditing = true;
                    editableFields.forEach(field => {
                        const displayElement = document.getElementById('display_' + field);
                        const editElement = document.getElementById('edit_' + field);
                        if (displayElement && editElement) {
                            originalValues[field] = editElement.tagName === 'SELECT' ? editElement.selectedIndex : editElement.value;
                            displayElement.classList.add('d-none');
                            editElement.classList.remove('d-none');
                        }
                    });
                    if (editBtn) editBtn.classList.add('d-none');
                    const actions = document.querySelector('.edit-actions');
                    if (actions) actions.classList.remove('d-none');
                    const personalInfo = document.getElementById('personalInfo');
                    if (personalInfo) {
                        personalInfo.classList.remove('view-mode');
                        personalInfo.classList.add('edit-mode');
                    }
                }
                function exitEditMode() {
                    editableFields.forEach(field => {
                        const displayElement = document.getElementById('display_' + field);
                        const editElement = document.getElementById('edit_' + field);
                        if (displayElement && editElement && originalValues[field] !== undefined) {
                            if (editElement.tagName === 'SELECT') {
                                editElement.selectedIndex = originalValues[field];
                            } else {
                                editElement.value = originalValues[field];
                            }
                            displayElement.classList.remove('d-none');
                            editElement.classList.add('d-none');
                        }
                        clearFieldError('edit_' + field);
                    });
                    if (editBtn) editBtn.classList.remove('d-none');
                    const actions = document.querySelector('.edit-actions');
                    if (actions) actions.classList.add('d-none');
                    const personalInfo = document.getElementById('personalInfo');
                    if (personalInfo) {
                        personalInfo.classList.remove('edit-mode');
                        personalInfo.classList.add('view-mode');
                    }
                    if (alertContainer) alertContainer.innerHTML = '';
                    isEditing = false;
                }
                function showAlert(type, message) {
                    if (!alertContainer) return;
                    alertContainer.innerHTML = `<div class="alert alert-${type}">${message}</div>`;
                }
                function saveProfile() {
                    if (!profileForm) return;
                    // Frontend validation
                    if (!validateProfileForm(userType, editableFields)) {
                        showAlert('danger', 'Please fix the errors in the form before saving.');
                        return;
                    }
                    const formData = new FormData();
                    editableFields.forEach(field => {
                        const editElement = document.getElementById('edit_' + field);
                        if (editElement && !editElement.classList.contains('d-none')) {
                            formData.append(field, editElement.value);
                        }
                    });
                    formData.append('action', 'update_profile');
                    if (saveBtn) saveBtn.classList.add('loading');
                    fetch('profile.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (saveBtn) saveBtn.classList.remove('loading');
                        if (data.success) {
                            // Update display fields with new values
                            editableFields.forEach(field => {
                                const displayElement = document.getElementById('display_' + field);
                                const editElement = document.getElementById('edit_' + field);
                                if (displayElement && editElement) {
                                    let value = editElement.value;
                                    if (editElement.tagName === 'SELECT') {
                                        value = editElement.options[editElement.selectedIndex].text;
                                    }
                                    displayElement.textContent = value;
                                }
                            });
                            showAlert('success', data.message || 'Profile updated successfully!');
                            setTimeout(() => {
                                exitEditMode();
                            }, 1000);
                        } else {
                            showAlert('danger', data.message || 'Failed to update profile.');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        if (saveBtn) saveBtn.classList.remove('loading');
                        showAlert('danger', 'An error occurred while saving.');
                    });
                }
                if (editBtn) editBtn.onclick = enterEditMode;
                if (cancelBtn) cancelBtn.onclick = exitEditMode;
                if (saveBtn) saveBtn.onclick = saveProfile;
            }
            const userType = document.body.getAttribute('data-user-type');
            if (userType === 'student') {
                setupProfileEdit('student', [
                    'first_name','middle_name','last_name','gender','dob','blood_group','mobile_no','email','address','city','state','pin','country',
                    'guardian_first_name','guardian_middle_name','guardian_last_name','guardian_mobile_no','guardian_email',
                    'student_type','department_id','enrolment_date','registration_no','registration_date','category_of_phd','status'
                ]);
            } else if (userType === 'hod') {
                setupProfileEdit('hod', [
                    'first_name','middle_name','last_name','designation','mobile_no','email','department_name','doj','dol'
                ]);
            } else if (userType === 'professor') {
                setupProfileEdit('professor', [
                    'first_name','middle_name','last_name','designation','mobile_no','email','department_id','doj','dol'
                ]);
            } else if (userType === 'employee') {
                setupProfileEdit('employee', [
                    'first_name','middle_name','last_name','designation','mobile_no','email','department_id','doj','dol'
                ]);
            }
        });
    </script>
</body>
</html>