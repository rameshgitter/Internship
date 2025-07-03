<?php
// Prevent caching to avoid profile data conflicts between users
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Start session with proper configuration
session_start();
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
        $stmt = $pdo->prepare("UPDATE student_info SET 
        first_name = ?, 
        last_name = ?, 
        email = ?, 
        mobile_no = ?, 
        address = ?,
        student_type = ?,
        department_id = ?,
        registration_no = ?,
        category_of_phd = ?
        WHERE student_id = ?");
        
        $result = $stmt->execute([
        $_POST['first_name'],
        $_POST['last_name'],
        $_POST['email'],
        $_POST['mobile_no'],
        $_POST['address'],
        $_POST['student_type'],
        $_POST['department_id'],
        $_POST['registration_no'],
        $_POST['category_of_phd'],
        $user_id
        ]);
        } elseif ($user_type === 'professor') {
            $stmt = $pdo->prepare("UPDATE staff_info SET 
                first_name = ?, 
                last_name = ?, 
                email = ?, 
                mobile_no = ?, 
                department_id = ?
                WHERE staff_id = ? AND staff_type = 'Professor'");
            
            $result = $stmt->execute([
                $_POST['first_name'],
                $_POST['last_name'],
                $_POST['email'],
                $_POST['mobile_no'],
                $_POST['department_id'],
                $user_id
            ]);
        } elseif ($user_type === 'hod') {
            $stmt = $pdo->prepare("UPDATE hod SET 
                first_name = ?, 
                last_name = ?, 
                email = ?, 
                mobile_no = ?, 
                department_id = ?
                WHERE hod_id = ?");
            
            $result = $stmt->execute([
                $_POST['first_name'],
                $_POST['last_name'],
                $_POST['email'],
                $_POST['mobile_no'],
                $_POST['department_id'],
                $user_id
            ]);
        } elseif ($user_type === 'staff') {
            $stmt = $pdo->prepare("UPDATE staff_info SET 
                first_name = ?, 
                last_name = ?, 
                email = ?, 
                mobile_no = ?, 
                department_id = ?
                WHERE staff_id = ? AND staff_type = 'Staff'");
            
            $result = $stmt->execute([
                $_POST['first_name'],
                $_POST['last_name'],
                $_POST['email'],
                $_POST['mobile_no'],
                $_POST['department_id'],
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
        $response['message'] = 'Database error occurred.';
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
} elseif ($user_type === 'staff') {
    // Fetch staff information
    try {
        $stmt = $pdo->prepare("SELECT * FROM staff_info WHERE staff_id = ? AND staff_type = 'Staff'");
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
    <style>
        .sidebar {
            min-height: 100vh;
            background: #2c3e50;
            color: white;
            padding: 20px;
        }
        .sidebar .nav-link {
            color: white;
            margin: 10px 0;
        }
        .sidebar .nav-link:hover {
            background: #34495e;
        }
        .main-content {
            padding: 20px;
        }
        .card {
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        /* Style for displaying information, can be adjusted */
        .profile-info label {
            font-weight: bold;
            margin-right: 10px;
        }
         .profile-info p {
           margin-bottom: 10px;
         }
         .profile-info p strong {
            display: inline-block;
            min-width: 120px; /* Adjust as needed for label alignment */
            margin-right: 10px;
         }
         .profile-info p span {
             font-weight: normal; /* Ensure the value text is not bold */
         }
         .card-header {
             background-color: #f8f9fa; /* Light grey background for header */
             font-weight: bold;
             position: relative;
         }
         .card-body {
             padding: 20px; /* Add some padding inside the card body */
         }
         .profile-info p {
             border-bottom: 1px solid #eee; /* Subtle separator between fields */
             padding-bottom: 8px;
             margin-bottom: 8px; /* Adjust spacing */
         }
         .profile-info p:last-child {
             border-bottom: none; /* No border for the last field */
             margin-bottom: 0;
             padding-bottom: 0;
         }
         
         /* Edit mode styles */
         .edit-btn {
             position: absolute;
             top: 50%;
             right: 15px;
             transform: translateY(-50%);
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
         
         .btn-save {
             background-color: #28a745;
             border-color: #28a745;
         }
         
         .btn-cancel {
             background-color: #6c757d;
             border-color: #6c757d;
         }
         
         .alert {
             margin-bottom: 20px;
         }
         
         .loading {
             opacity: 0.6;
             pointer-events: none;
         }
    </style>
</head>
<body data-page-id="<?= htmlspecialchars($page_id) ?>" data-user-type="<?= htmlspecialchars($user_type) ?>" data-user-id="<?= htmlspecialchars($_SESSION['user_id']) ?>">
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 sidebar">
                <h3 class="mb-4"><?= ucfirst($user_type) ?> Portal</h3>
                <div class="nav flex-column">
                    <?php if ($user_type === 'student'): ?>
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
                    <?php elseif ($user_type === 'professor'): ?>
                        <a class="nav-link" href="professor_dashboard.php">
                            <i class="fas fa-home"></i> Dashboard
                        </a>
                        <a class="nav-link" href="admin_add_semester.php">
                            <i class="fas fa-calendar-plus"></i> Add Semester
                        </a>
                        <a class="nav-link" href="admin_add_course.php">
                            <i class="fas fa-book-open"></i> Add Course
                        </a>
                        <a class="nav-link active" href="profile.php">
                            <i class="fas fa-user"></i> Profile
                        </a>
                        <a class="nav-link" href="logout.php">
                            <i class="fas fa-sign-out-alt"></i> Logout
                        </a>
                    <?php elseif ($user_type === 'hod'): ?>
                        <a class="nav-link" href="hod_dashboard.php">
                            <i class="fas fa-home"></i> Dashboard
                        </a>
                        <a class="nav-link" href="hod_assign_staff.php">
                            <i class="fas fa-user-plus"></i> Assign Staff
                        </a>
                        <a class="nav-link active" href="profile.php">
                            <i class="fas fa-user"></i> Profile
                        </a>
                        <a class="nav-link" href="logout.php">
                            <i class="fas fa-sign-out-alt"></i> Logout
                        </a>
                    <?php elseif ($user_type === 'staff'): ?>
                        <a class="nav-link" href="staff_dashboard.php">
                            <i class="fas fa-home"></i> Dashboard
                        </a>
                        <a class="nav-link" href="staff_verification.php">
                            <i class="fas fa-check-circle"></i> Verification
                        </a>
                        <a class="nav-link active" href="profile.php">
                            <i class="fas fa-user"></i> Profile
                        </a>
                        <a class="nav-link" href="logout.php">
                            <i class="fas fa-sign-out-alt"></i> Logout
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Main Content -->
            <div class="col-md-9 col-lg-10 main-content">
                <div class="row mb-4">
                    <div class="col">
                        <h2><?= ucfirst($user_type) ?> Profile</h2>
                    </div>
                </div>

                <!-- Alert container for messages -->
                <div id="alertContainer"></div>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                <?php elseif ($user_data): ?>
                    <form id="profileForm">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Personal Information</h5>
                                <button type="button" class="btn btn-sm btn-outline-primary edit-btn" id="editBtn">
                                    <i class="fas fa-edit"></i> Edit
                                </button>
                            </div>
                            <div class="card-body view-mode" id="personalInfo">
                                <div class="profile-field">
                                    <span class="field-label">First Name:</span>
                                    <span class="field-value" id="display_first_name"><?= htmlspecialchars($user_data['first_name'] ?? '') ?></span>
                                    <input type="text" class="form-control d-none" id="edit_first_name" name="first_name" value="<?= htmlspecialchars($user_data['first_name'] ?? '') ?>">
                                </div>
                                
                                <div class="profile-field">
                                    <span class="field-label">Last Name:</span>
                                    <span class="field-value" id="display_last_name"><?= htmlspecialchars($user_data['last_name'] ?? '') ?></span>
                                    <input type="text" class="form-control d-none" id="edit_last_name" name="last_name" value="<?= htmlspecialchars($user_data['last_name'] ?? '') ?>">
                                </div>
                                
                                <?php if ($user_type === 'student'): ?>
                                    <div class="profile-field">
                                        <span class="field-label">Student ID:</span>
                                        <span class="field-value"><?= htmlspecialchars($user_data['student_id']) ?></span>
                                    </div>
                                <?php elseif ($user_type === 'professor'): ?>
                                    <div class="profile-field">
                                        <span class="field-label">Staff ID:</span>
                                        <span class="field-value"><?= htmlspecialchars($user_data['staff_id']) ?></span>
                                    </div>
                                <?php elseif ($user_type === 'hod'): ?>
                                    <div class="profile-field">
                                        <span class="field-label">HOD ID:</span>
                                        <span class="field-value"><?= htmlspecialchars($user_data['hod_id']) ?></span>
                                    </div>
                                <?php elseif ($user_type === 'staff'): ?>
                                    <div class="profile-field">
                                        <span class="field-label">Staff ID:</span>
                                        <span class="field-value"><?= htmlspecialchars($user_data['staff_id']) ?></span>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="profile-field">
                                    <span class="field-label">Registration No:</span>
                                    <span class="field-value"><?= htmlspecialchars($user_data['registration_no'] ?? 'N/A') ?></span>
                                </div>
                                
                                <div class="profile-field">
                                    <span class="field-label">Email:</span>
                                    <span class="field-value" id="display_email"><?= htmlspecialchars($user_data['email'] ?? 'N/A') ?></span>
                                    <input type="email" class="form-control d-none" id="edit_email" name="email" value="<?= htmlspecialchars($user_data['email'] ?? '') ?>">
                                </div>
                                
                                <div class="profile-field">
                                    <span class="field-label">Phone:</span>
                                    <span class="field-value" id="display_mobile_no"><?= htmlspecialchars($user_data['mobile_no'] ?? $user_data['mobile_no_1'] ?? 'N/A') ?></span>
                                    <input type="tel" class="form-control d-none" id="edit_mobile_no" name="mobile_no" value="<?= htmlspecialchars($user_data['mobile_no'] ?? $user_data['mobile_no_1'] ?? '') ?>">
                                </div>
                                
                                <?php if ($user_type === 'student'): ?>
                                    <div class="profile-field">
                                        <span class="field-label">Address:</span>
                                        <span class="field-value" id="display_address"><?= nl2br(htmlspecialchars($user_data['address'] ?? 'N/A')) ?></span>
                                        <textarea class="form-control d-none" id="edit_address" name="address" rows="3"><?= htmlspecialchars($user_data['address'] ?? '') ?></textarea>
                                    </div>
                                <?php else: ?>
                                    <div class="profile-field">
                                        <span class="field-label">Department:</span>
                                        <span class="field-value" id="display_department_id"><?= htmlspecialchars($user_data['department_id'] ?? 'N/A') ?></span>
                                        <input type="text" class="form-control d-none" id="edit_department_id" name="department_id" value="<?= htmlspecialchars($user_data['department_id'] ?? '') ?>">
                                    </div>
                                <?php endif; ?>
                                
                                <div class="edit-actions d-none">
                                    <button type="button" class="btn btn-success btn-save" id="saveBtn">
                                        <i class="fas fa-save"></i> Save Changes
                                    </button>
                                    <button type="button" class="btn btn-secondary btn-cancel ms-2" id="cancelBtn">
                                        <i class="fas fa-times"></i> Cancel
                                    </button>
                                </div>
                            </div>
                        </div>

                        <?php if ($user_type === 'student'): ?>
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="card-title mb-0">Academic Information</h5>
                                    <button type="button" class="btn btn-sm btn-outline-primary edit-btn" id="editAcademicBtn">
                                        <i class="fas fa-edit"></i> Edit
                                    </button>
                                </div>
                                <div class="card-body view-mode" id="academicInfo">
                                    <div class="profile-field">
                                        <span class="field-label">College Name:</span>
                                        <span class="field-value">Indian Institute of Engineering Science and Technology, Shibpur (IIEST)</span>
                                    </div>
                                    
                                    <div class="profile-field">
                                        <span class="field-label">Student Type:</span>
                                        <span class="field-value" id="display_student_type"><?= htmlspecialchars($user_data['student_type'] ?? 'N/A') ?></span>
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
                                        <span class="field-label">Registration No:</span>
                                        <span class="field-value" id="display_registration_no"><?= htmlspecialchars($user_data['registration_no'] ?? 'N/A') ?></span>
                                        <input type="text" class="form-control d-none" id="edit_registration_no" name="registration_no" value="<?= htmlspecialchars($user_data['registration_no'] ?? '') ?>">
                                    </div>
                                    
                                    <div class="profile-field">
                                        <span class="field-label">Enrollment Date:</span>
                                        <span class="field-value"><?= htmlspecialchars($user_data['enrolment_date'] ?? 'N/A') ?></span>
                                    </div>
                                    
                                    <div class="profile-field">
                                        <span class="field-label">Category of PhD:</span>
                                        <span class="field-value" id="display_category_of_phd"><?= htmlspecialchars($user_data['category_of_phd'] ?? 'N/A') ?></span>
                                        <input type="text" class="form-control d-none" id="edit_category_of_phd" name="category_of_phd" value="<?= htmlspecialchars($user_data['category_of_phd'] ?? '') ?>">
                                    </div>
                                    
                                    <div class="profile-field">
                                        <span class="field-label">Status:</span>
                                        <span class="field-value"><?= htmlspecialchars($user_data['status'] ?? 'N/A') ?></span>
                                    </div>
                                    
                                    <div class="edit-actions d-none" id="academicEditActions">
                                        <button type="button" class="btn btn-success btn-save" id="saveAcademicBtn">
                                            <i class="fas fa-save"></i> Save Changes
                                        </button>
                                        <button type="button" class="btn btn-secondary btn-cancel ms-2" id="cancelAcademicBtn">
                                            <i class="fas fa-times"></i> Cancel
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php elseif ($user_type === 'professor'): ?>
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="card-title mb-0">Professional Information</h5>
                                    <button type="button" class="btn btn-sm btn-outline-primary edit-btn" id="editProfessionalBtn">
                                        <i class="fas fa-edit"></i> Edit
                                    </button>
                                </div>
                                <div class="card-body view-mode" id="professionalInfo">
                                    <div class="profile-field">
                                        <span class="field-label">Institution:</span>
                                        <span class="field-value">Indian Institute of Engineering Science and Technology, Shibpur (IIEST)</span>
                                    </div>
                                    
                                    <div class="profile-field">
                                        <span class="field-label">Department:</span>
                                        <span class="field-value" id="display_prof_department_id">
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
                                        <select class="form-control d-none" id="edit_prof_department_id" name="department_id">
                                            <option value="">Select Department</option>
                                            <option value="CSE" <?= ($user_data['department_id'] ?? '') === 'CSE' ? 'selected' : '' ?>>Computer Science and Engineering</option>
                                            <option value="ECE" <?= ($user_data['department_id'] ?? '') === 'ECE' ? 'selected' : '' ?>>Electronics and Communication Engineering</option>
                                            <option value="ME" <?= ($user_data['department_id'] ?? '') === 'ME' ? 'selected' : '' ?>>Mechanical Engineering</option>
                                            <option value="CE" <?= ($user_data['department_id'] ?? '') === 'CE' ? 'selected' : '' ?>>Civil Engineering</option>
                                            <option value="EE" <?= ($user_data['department_id'] ?? '') === 'EE' ? 'selected' : '' ?>>Electrical Engineering</option>
                                        </select>
                                    </div>
                                    
                                    <div class="profile-field">
                                        <span class="field-label">Position:</span>
                                        <span class="field-value">Professor</span>
                                    </div>
                                    
                                    <div class="edit-actions d-none" id="professionalEditActions">
                                        <button type="button" class="btn btn-success btn-save" id="saveProfessionalBtn">
                                            <i class="fas fa-save"></i> Save Changes
                                        </button>
                                        <button type="button" class="btn btn-secondary btn-cancel ms-2" id="cancelProfessionalBtn">
                                            <i class="fas fa-times"></i> Cancel
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php elseif ($user_type === 'hod'): ?>
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="card-title mb-0">Professional Information</h5>
                                    <button type="button" class="btn btn-sm btn-outline-primary edit-btn" id="editProfessionalBtn">
                                        <i class="fas fa-edit"></i> Edit
                                    </button>
                                </div>
                                <div class="card-body view-mode" id="professionalInfo">
                                    <div class="profile-field">
                                        <span class="field-label">Institution:</span>
                                        <span class="field-value">Indian Institute of Engineering Science and Technology, Shibpur (IIEST)</span>
                                    </div>
                                    
                                    <div class="profile-field">
                                        <span class="field-label">Department:</span>
                                        <span class="field-value" id="display_prof_department_id">
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
                                        <select class="form-control d-none" id="edit_prof_department_id" name="department_id">
                                            <option value="">Select Department</option>
                                            <option value="CSE" <?= ($user_data['department_id'] ?? '') === 'CSE' ? 'selected' : '' ?>>Computer Science and Engineering</option>
                                            <option value="ECE" <?= ($user_data['department_id'] ?? '') === 'ECE' ? 'selected' : '' ?>>Electronics and Communication Engineering</option>
                                            <option value="ME" <?= ($user_data['department_id'] ?? '') === 'ME' ? 'selected' : '' ?>>Mechanical Engineering</option>
                                            <option value="CE" <?= ($user_data['department_id'] ?? '') === 'CE' ? 'selected' : '' ?>>Civil Engineering</option>
                                            <option value="EE" <?= ($user_data['department_id'] ?? '') === 'EE' ? 'selected' : '' ?>>Electrical Engineering</option>
                                        </select>
                                    </div>
                                    
                                    <div class="profile-field">
                                        <span class="field-label">Position:</span>
                                        <span class="field-value">Head of Department</span>
                                    </div>
                                    
                                    <div class="edit-actions d-none" id="professionalEditActions">
                                        <button type="button" class="btn btn-success btn-save" id="saveProfessionalBtn">
                                            <i class="fas fa-save"></i> Save Changes
                                        </button>
                                        <button type="button" class="btn btn-secondary btn-cancel ms-2" id="cancelProfessionalBtn">
                                            <i class="fas fa-times"></i> Cancel
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php elseif ($user_type === 'staff'): ?>
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="card-title mb-0">Professional Information</h5>
                                    <button type="button" class="btn btn-sm btn-outline-primary edit-btn" id="editProfessionalBtn">
                                        <i class="fas fa-edit"></i> Edit
                                    </button>
                                </div>
                                <div class="card-body view-mode" id="professionalInfo">
                                    <div class="profile-field">
                                        <span class="field-label">Institution:</span>
                                        <span class="field-value">Indian Institute of Engineering Science and Technology, Shibpur (IIEST)</span>
                                    </div>
                                    
                                    <div class="profile-field">
                                        <span class="field-label">Department:</span>
                                        <span class="field-value" id="display_prof_department_id">
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
                                        <select class="form-control d-none" id="edit_prof_department_id" name="department_id">
                                            <option value="">Select Department</option>
                                            <option value="CSE" <?= ($user_data['department_id'] ?? '') === 'CSE' ? 'selected' : '' ?>>Computer Science and Engineering</option>
                                            <option value="ECE" <?= ($user_data['department_id'] ?? '') === 'ECE' ? 'selected' : '' ?>>Electronics and Communication Engineering</option>
                                            <option value="ME" <?= ($user_data['department_id'] ?? '') === 'ME' ? 'selected' : '' ?>>Mechanical Engineering</option>
                                            <option value="CE" <?= ($user_data['department_id'] ?? '') === 'CE' ? 'selected' : '' ?>>Civil Engineering</option>
                                            <option value="EE" <?= ($user_data['department_id'] ?? '') === 'EE' ? 'selected' : '' ?>>Electrical Engineering</option>
                                        </select>
                                    </div>
                                    
                                    <div class="profile-field">
                                        <span class="field-label">Position:</span>
                                        <span class="field-value">Staff</span>
                                    </div>
                                    
                                    <div class="edit-actions d-none" id="professionalEditActions">
                                        <button type="button" class="btn btn-success btn-save" id="saveProfessionalBtn">
                                            <i class="fas fa-save"></i> Save Changes
                                        </button>
                                        <button type="button" class="btn btn-secondary btn-cancel ms-2" id="cancelProfessionalBtn">
                                            <i class="fas fa-times"></i> Cancel
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </form>

                <?php endif; ?>

            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const editBtn = document.getElementById('editBtn');
            const saveBtn = document.getElementById('saveBtn');
            const cancelBtn = document.getElementById('cancelBtn');
            const editAcademicBtn = document.getElementById('editAcademicBtn');
            const saveAcademicBtn = document.getElementById('saveAcademicBtn');
            const cancelAcademicBtn = document.getElementById('cancelAcademicBtn');
            const editProfessionalBtn = document.getElementById('editProfessionalBtn');
            const saveProfessionalBtn = document.getElementById('saveProfessionalBtn');
            const cancelProfessionalBtn = document.getElementById('cancelProfessionalBtn');
            const profileForm = document.getElementById('profileForm');
            const alertContainer = document.getElementById('alertContainer');
            
            // Get page identifiers to prevent conflicts
            const pageId = document.body.getAttribute('data-page-id');
            const userType = document.body.getAttribute('data-user-type');
            const userId = document.body.getAttribute('data-user-id');
            
            // Store original values for cancel functionality
            let originalValues = {};
            let originalAcademicValues = {};
            let originalProfessionalValues = {};
            let isEditing = false;
            let isEditingAcademic = false;
            let isEditingProfessional = false;
            
            // Prevent multiple edit sessions
            function checkEditLock() {
                if (isEditing || isEditingAcademic || isEditingProfessional) {
                    showAlert('warning', 'Profile is currently being edited. Please save or cancel first.');
                    return false;
                }
                return true;
            }
            
            // Edit button click handlers
            if (editBtn) {
                editBtn.addEventListener('click', function() {
                    if (checkEditLock()) {
                        enterEditMode();
                    }
                });
            }
            
            if (editAcademicBtn) {
                editAcademicBtn.addEventListener('click', function() {
                    if (checkEditLock()) {
                        enterAcademicEditMode();
                    }
                });
            }
            
            if (editProfessionalBtn) {
                editProfessionalBtn.addEventListener('click', function() {
                    if (checkEditLock()) {
                        enterProfessionalEditMode();
                    }
                });
            }
            
            // Save button click handlers
            if (saveBtn) {
                saveBtn.addEventListener('click', function() {
                    if (isEditing) {
                        saveProfile();
                    }
                });
            }
            
            if (saveAcademicBtn) {
                saveAcademicBtn.addEventListener('click', function() {
                    if (isEditingAcademic) {
                        saveProfile();
                    }
                });
            }
            
            if (saveProfessionalBtn) {
                saveProfessionalBtn.addEventListener('click', function() {
                    if (isEditingProfessional) {
                        saveProfile();
                    }
                });
            }
            
            // Cancel button click handlers
            if (cancelBtn) {
                cancelBtn.addEventListener('click', function() {
                    if (isEditing) {
                        exitEditMode();
                    }
                });
            }
            
            if (cancelAcademicBtn) {
                cancelAcademicBtn.addEventListener('click', function() {
                    if (isEditingAcademic) {
                        exitAcademicEditMode();
                    }
                });
            }
            
            if (cancelProfessionalBtn) {
                cancelProfessionalBtn.addEventListener('click', function() {
                    if (isEditingProfessional) {
                        exitProfessionalEditMode();
                    }
                });
            }
            
            function enterEditMode() {
                if (!checkEditLock()) return;
                
                isEditing = true;
                // Store original values
                const editableFields = ['first_name', 'last_name', 'email', 'mobile_no', 'address', 'department_id'];
                editableFields.forEach(field => {
                    const displayElement = document.getElementById('display_' + field);
                    const editElement = document.getElementById('edit_' + field);
                    if (displayElement && editElement) {
                        originalValues[field] = editElement.value;
                        displayElement.classList.add('d-none');
                        editElement.classList.remove('d-none');
                    }
                });
                
                // Toggle UI elements
                editBtn.classList.add('d-none');
                document.querySelector('.edit-actions').classList.remove('d-none');
                
                // Change card body classes for personal info only
                const personalInfo = document.getElementById('personalInfo');
                if (personalInfo) {
                    personalInfo.classList.remove('view-mode');
                    personalInfo.classList.add('edit-mode');
                }
            }
            
            function enterAcademicEditMode() {
                if (!checkEditLock()) return;
                
                isEditingAcademic = true;
                // Store original values
                const editableFields = ['student_type', 'department_id', 'registration_no', 'category_of_phd'];
                editableFields.forEach(field => {
                    const displayElement = document.getElementById('display_' + field);
                    const editElement = document.getElementById('edit_' + field);
                    if (displayElement && editElement) {
                        originalAcademicValues[field] = editElement.value;
                        displayElement.classList.add('d-none');
                        editElement.classList.remove('d-none');
                    }
                });
                
                // Toggle UI elements
                editAcademicBtn.classList.add('d-none');
                document.getElementById('academicEditActions').classList.remove('d-none');
                
                // Change card body classes for academic info only
                const academicInfo = document.getElementById('academicInfo');
                if (academicInfo) {
                    academicInfo.classList.remove('view-mode');
                    academicInfo.classList.add('edit-mode');
                }
            }
            
            function enterProfessionalEditMode() {
                if (!checkEditLock()) return;
                
                isEditingProfessional = true;
                // Store original values
                const editableFields = ['prof_department_id'];
                editableFields.forEach(field => {
                    const displayElement = document.getElementById('display_' + field);
                    const editElement = document.getElementById('edit_' + field);
                    if (displayElement && editElement) {
                        originalProfessionalValues[field] = editElement.value;
                        displayElement.classList.add('d-none');
                        editElement.classList.remove('d-none');
                    }
                });
                
                // Toggle UI elements
                editProfessionalBtn.classList.add('d-none');
                document.getElementById('professionalEditActions').classList.remove('d-none');
                
                // Change card body classes for professional info only
                const professionalInfo = document.getElementById('professionalInfo');
                if (professionalInfo) {
                    professionalInfo.classList.remove('view-mode');
                    professionalInfo.classList.add('edit-mode');
                }
            }
            
            function exitEditMode() {
                // Restore original values
                const editableFields = ['first_name', 'last_name', 'email', 'mobile_no', 'address', 'department_id'];
                editableFields.forEach(field => {
                    const displayElement = document.getElementById('display_' + field);
                    const editElement = document.getElementById('edit_' + field);
                    if (displayElement && editElement && originalValues[field] !== undefined) {
                        editElement.value = originalValues[field];
                        displayElement.classList.remove('d-none');
                        editElement.classList.add('d-none');
                    }
                });
                
                // Toggle UI elements
                editBtn.classList.remove('d-none');
                document.querySelector('.edit-actions').classList.add('d-none');
                
                // Change card body classes for personal info only
                const personalInfo = document.getElementById('personalInfo');
                if (personalInfo) {
                    personalInfo.classList.remove('edit-mode');
                    personalInfo.classList.add('view-mode');
                }
                
                // Clear any alerts
                alertContainer.innerHTML = '';
                
                // Release edit lock
                isEditing = false;
            }
            
            function exitAcademicEditMode() {
                // Restore original values
                const editableFields = ['student_type', 'department_id', 'registration_no', 'category_of_phd'];
                editableFields.forEach(field => {
                    const displayElement = document.getElementById('display_' + field);
                    const editElement = document.getElementById('edit_' + field);
                    if (displayElement && editElement && originalAcademicValues[field] !== undefined) {
                        editElement.value = originalAcademicValues[field];
                        displayElement.classList.remove('d-none');
                        editElement.classList.add('d-none');
                    }
                });
                
                // Toggle UI elements
                editAcademicBtn.classList.remove('d-none');
                document.getElementById('academicEditActions').classList.add('d-none');
                
                // Change card body classes for academic info only
                const academicInfo = document.getElementById('academicInfo');
                if (academicInfo) {
                    academicInfo.classList.remove('edit-mode');
                    academicInfo.classList.add('view-mode');
                }
                
                // Clear any alerts
                alertContainer.innerHTML = '';
                
                // Release edit lock
                isEditingAcademic = false;
            }
            
            function saveProfile() {
                if (!isEditing && !isEditingAcademic) return;
                
                // Determine which button to update
                const currentSaveBtn = isEditing ? saveBtn : saveAcademicBtn;
                
                // Validate required fields before sending
                if (isEditing) {
                    const requiredFields = ['first_name', 'last_name', 'email', 'mobile_no'];
                    for (let field of requiredFields) {
                        const element = document.getElementById('edit_' + field);
                        if (element && !element.value.trim()) {
                            showAlert('danger', field.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase()) + ' is required.');
                            return;
                        }
                    }
                }
                
                // Show loading state
                currentSaveBtn.disabled = true;
                currentSaveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
                
                // Prepare form data
                const formData = new FormData(profileForm);
                formData.append('action', 'update_profile');
                formData.append('page_id', pageId);
                formData.append('user_type', userType);
                formData.append('user_id', userId);
                
                // Send AJAX request
                fetch('profile.php', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Update display values with new data
                        let editableFields = [];
                        if (isEditing) {
                            editableFields = ['first_name', 'last_name', 'email', 'mobile_no', 'address', 'department_id'];
                        } else if (isEditingAcademic) {
                            editableFields = ['student_type', 'department_id', 'registration_no', 'category_of_phd'];
                        }
                        
                        editableFields.forEach(field => {
                            const displayElement = document.getElementById('display_' + field);
                            const editElement = document.getElementById('edit_' + field);
                            if (displayElement && editElement) {
                                let newValue = editElement.value;
                                if (field === 'address') {
                                    newValue = newValue.replace(/\n/g, '<br>');
                                } else if (field === 'student_type') {
                                    // Get the text content of the selected option
                                    const selectedOption = editElement.options[editElement.selectedIndex];
                                    newValue = selectedOption ? selectedOption.text : newValue;
                                } else if (field === 'department_id' && editElement.tagName === 'SELECT') {
                                    // Get the text content of the selected option for department
                                    const selectedOption = editElement.options[editElement.selectedIndex];
                                    newValue = selectedOption ? selectedOption.text : newValue;
                                }
                                displayElement.innerHTML = newValue || 'N/A';
                            }
                        });
                        
                        // Show success message
                        showAlert('success', data.message);
                        
                        // Exit appropriate edit mode
                        if (isEditing) {
                            exitEditMode();
                        } else if (isEditingAcademic) {
                            exitAcademicEditMode();
                        }
                    } else {
                        // Show error message
                        showAlert('danger', data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showAlert('danger', 'An error occurred while saving the profile. Please try again.');
                })
                .finally(() => {
                    // Reset save button
                    currentSaveBtn.disabled = false;
                    currentSaveBtn.innerHTML = '<i class="fas fa-save"></i> Save Changes';
                });
            }
            
            function showAlert(type, message) {
                const alertHtml = `
                    <div class="alert alert-${type} alert-dismissible fade show" role="alert">
                        ${message}
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                `;
                alertContainer.innerHTML = alertHtml;
                
                // Auto-dismiss success alerts after 5 seconds
                if (type === 'success') {
                    setTimeout(() => {
                        const alert = alertContainer.querySelector('.alert');
                        if (alert) {
                            const bsAlert = new bootstrap.Alert(alert);
                            bsAlert.close();
                        }
                    }, 5000);
                }
            }
            
            // Handle page visibility changes to prevent conflicts
            document.addEventListener('visibilitychange', function() {
                if (document.hidden && (isEditing || isEditingAcademic)) {
                    // If user switches away while editing, show warning when they return
                    setTimeout(() => {
                        if (!document.hidden && (isEditing || isEditingAcademic)) {
                            showAlert('warning', 'You were editing your profile. Please save or cancel your changes.');
                        }
                    }, 1000);
                }
            });
            
            // Prevent accidental page refresh while editing
            window.addEventListener('beforeunload', function(e) {
                if (isEditing || isEditingAcademic) {
                    e.preventDefault();
                    e.returnValue = 'You have unsaved changes. Are you sure you want to leave?';
                    return 'You have unsaved changes. Are you sure you want to leave?';
                }
            });
        });
    </script>
</body>
</html>