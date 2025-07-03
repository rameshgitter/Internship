<?php
session_start();
require_once 'db_config.php';

// Check if user is logged in and is HOD
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'hod') {
    header('Location: login.php');
    exit();
}

$hod_id = $_SESSION['user_id'];
$error = '';
$success = '';

// Get HOD information
$stmt = $pdo->prepare("SELECT * FROM hod WHERE hod_id = ?");
$stmt->execute([$hod_id]);
$hod = $stmt->fetch();

// Handle semester and subject creation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['create_subject'])) {
        $taught_in = $_POST['taught_in'];
        $subject_type = $_POST['subject_type'];
        $subject_code = $_POST['subject_code'];
        $subject_name = $_POST['subject_name'];
        $subject_semester = $_POST['subject_semester'];
        $credit = $_POST['credit'];
        $lecture_hours = $_POST['lecture_hours'];
        $tutorial_hours = $_POST['tutorial_hours'];
        $practical_hours = $_POST['practical_hours'];
        
        try {
            $stmt = $pdo->prepare("INSERT INTO subjects_pool (taught_in, subject_type, subject_code, year_of_introduction, subject_name, subject_semester, credit, lecture_hours, tutorial_hours, practical_hours) VALUES (?, ?, ?, CURDATE(), ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$taught_in, $subject_type, $subject_code, $subject_name, $subject_semester, $credit, $lecture_hours, $tutorial_hours, $practical_hours]);
            $success = "Subject created successfully!";
        } catch (PDOException $e) {
            $error = "Error creating subject: " . $e->getMessage();
        }
    }
    
    if (isset($_POST['assign_verification'])) {
        $student_id = $_POST['student_id'];
        $staff_id = $_POST['staff_id'];
        $verification_type = $_POST['verification_type'];
        
        try {
            $stmt = $pdo->prepare("INSERT INTO verification_assignments (student_id, assigned_staff_id, assigned_by_hod, verification_type, assignment_date) VALUES (?, ?, ?, ?, CURDATE())");
            $stmt->execute([$student_id, $staff_id, $hod_id, $verification_type]);
            $success = "Verification assigned successfully!";
        } catch (PDOException $e) {
            $error = "Error assigning verification: " . $e->getMessage();
        }
    }
}

// Get all database entries for dashboard
$students = $pdo->query("SELECT s.*, i.change_passcode FROM student_info s JOIN `index` i ON s.student_id = i.user_id")->fetchAll();
$staff = $pdo->query("SELECT s.*, i.change_passcode FROM staff_info s JOIN `index` i ON s.staff_id = i.user_id")->fetchAll();
$subjects = $pdo->query("SELECT * FROM subjects_pool ORDER BY subject_semester, subject_code")->fetchAll();
$registrations = $pdo->query("SELECT sr.*, si.first_name, si.last_name FROM semester_registration sr JOIN student_info si ON sr.student_id = si.student_id ORDER BY sr.sem_reg_date DESC")->fetchAll();
$verifications = $pdo->query("SELECT va.*, si.first_name as student_name, si.last_name as student_lastname, st.first_name as staff_name, st.last_name as staff_lastname FROM verification_assignments va JOIN student_info si ON va.student_id = si.student_id JOIN staff_info st ON va.assigned_staff_id = st.staff_id ORDER BY va.assignment_date DESC")->fetchAll();

// Get staff for assignment dropdown
$available_staff = $pdo->query("SELECT staff_id, first_name, last_name FROM staff_info")->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HOD Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Roboto', sans-serif;
            background-color: #f4f6f9;
        }
        .sidebar {
            min-height: 100vh;
            background: #343a40;
            color: #fff;
            padding: 20px 0;
        }
        .sidebar h3 {
            color: #fff;
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
        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            background: #495057;
            color: #fff;
        }
        .main-content {
            padding: 30px 20px;
        }
        .card {
            border: none;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            margin-bottom: 25px;
        }
        .table-responsive {
            max-height: 400px;
            overflow-y: auto;
        }
        .badge-status {
            font-size: 0.85rem;
            padding: 0.4em 0.75em;
            border-radius: 12px;
        }
        .status-ongoing { background-color: #d4edda; color: #155724; }
        .status-graduated { background-color: #cce5ff; color: #004085; }
        .status-pending { background-color: #ffeeba; color: #856404; }
        .status-verified { background-color: #d4edda; color: #155724; }
        .status-rejected { background-color: #f8d7da; color: #721c24; }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <nav class="col-md-3 col-lg-2 sidebar">
                <h3>HOD Portal</h3>
                <div class="nav flex-column">
                    <a class="nav-link active" href="#dashboard" onclick="showSection('dashboard')">
                        <i class="fas fa-home"></i> Dashboard
                    </a>
                    <a class="nav-link" href="#students" onclick="showSection('students')">
                        <i class="fas fa-users"></i> Students
                    </a>
                    <a class="nav-link" href="#staff" onclick="showSection('staff')">
                        <i class="fas fa-user-tie"></i> Staff
                    </a>
                    <a class="nav-link" href="#subjects" onclick="showSection('subjects')">
                        <i class="fas fa-book"></i> Subjects
                    </a>
                    <a class="nav-link" href="#registrations" onclick="showSection('registrations')">
                        <i class="fas fa-clipboard-list"></i> Registrations
                    </a>
                    <a class="nav-link" href="#verification" onclick="showSection('verification')">
                        <i class="fas fa-check-circle"></i> Verification
                    </a>
                    <a class="nav-link" href="profile.php">
                        <i class="fas fa-user"></i> Profile
                    </a>
                    <a class="nav-link" href="logout.php">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </div>
            </nav>

            <!-- Main Content -->
            <main class="col-md-9 col-lg-10 main-content">
                <!-- Welcome Header -->
                <div class="row mb-4">
                    <div class="col">
                        <h2>Welcome, <?= htmlspecialchars($hod['first_name'] . ' ' . $hod['last_name']) ?></h2>
                        <p class="text-muted">HOD ID: <?= htmlspecialchars($hod['hod_id']) ?></p>
                        <?php if ($success): ?>
                            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
                        <?php endif; ?>
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Dashboard Overview -->
                <div id="dashboard" class="section">
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <div class="card text-center">
                                <div class="card-body">
                                    <h3 class="text-primary"><?= count($students) ?></h3>
                                    <p>Total Students</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card text-center">
                                <div class="card-body">
                                    <h3 class="text-success"><?= count($staff) ?></h3>
                                    <p>Total Staff</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card text-center">
                                <div class="card-body">
                                    <h3 class="text-info"><?= count($subjects) ?></h3>
                                    <p>Total Subjects</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card text-center">
                                <div class="card-body">
                                    <h3 class="text-warning"><?= count($registrations) ?></h3>
                                    <p>Registrations</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Students Section -->
                <div id="students" class="section" style="display: none;">
                    <div class="card">
                        <div class="card-header">
                            <h5>All Students</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Student ID</th>
                                            <th>Name</th>
                                            <th>Type</th>
                                            <th>Department</th>
                                            <th>Email</th>
                                            <th>Status</th>
                                            <th>Password Changed</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($students as $student): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($student['student_id']) ?></td>
                                            <td><?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) ?></td>
                                            <td><?= htmlspecialchars($student['student_type']) ?></td>
                                            <td><?= htmlspecialchars($student['department_id']) ?></td>
                                            <td><?= htmlspecialchars($student['email']) ?></td>
                                            <td><span class="badge-status status-<?= strtolower($student['status']) ?>"><?= htmlspecialchars($student['status']) ?></span></td>
                                            <td><?= $student['change_passcode'] ? 'Yes' : 'No' ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Staff Section -->
                <div id="staff" class="section" style="display: none;">
                    <div class="card">
                        <div class="card-header">
                            <h5>All Staff</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Staff ID</th>
                                            <th>Name</th>
                                            <th>Type</th>
                                            <th>Department</th>
                                            <th>Email</th>
                                            <th>Designation</th>
                                            <th>Password Changed</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($staff as $s): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($s['staff_id']) ?></td>
                                            <td><?= htmlspecialchars($s['first_name'] . ' ' . $s['last_name']) ?></td>
                                            <td><?= htmlspecialchars($s['staff_type']) ?></td>
                                            <td><?= htmlspecialchars($s['department_id']) ?></td>
                                            <td><?= htmlspecialchars($s['email']) ?></td>
                                            <td><?= htmlspecialchars($s['designation']) ?></td>
                                            <td><?= $s['change_passcode'] ? 'Yes' : 'No' ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Subjects Section -->
                <div id="subjects" class="section" style="display: none;">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">
                                    <h5>Create New Subject</h5>
                                </div>
                                <div class="card-body">
                                    <form method="POST">
                                        <div class="mb-3">
                                            <label class="form-label">Taught In</label>
                                            <select name="taught_in" class="form-select" required>
                                                <option value="UG">UG</option>
                                                <option value="PG">PG</option>
                                                <option value="PhD">PhD</option>
                                                <option value="Other">Other</option>
                                            </select>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Subject Type</label>
                                            <input type="text" name="subject_type" class="form-control" placeholder="e.g., Core, Elective" required>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Subject Code</label>
                                            <input type="text" name="subject_code" class="form-control" required>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Subject Name</label>
                                            <input type="text" name="subject_name" class="form-control" required>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Semester</label>
                                            <select name="subject_semester" class="form-select" required>
                                                <?php for($i = 1; $i <= 10; $i++): ?>
                                                <option value="<?= $i ?>"><?= $i ?></option>
                                                <?php endfor; ?>
                                            </select>
                                        </div>
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label class="form-label">Credits</label>
                                                    <input type="number" name="credit" class="form-control" min="0" max="10">
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label class="form-label">Lecture Hours</label>
                                                    <input type="number" name="lecture_hours" class="form-control" min="0">
                                                </div>
                                            </div>
                                        </div>
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label class="form-label">Tutorial Hours</label>
                                                    <input type="number" name="tutorial_hours" class="form-control" min="0">
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label class="form-label">Practical Hours</label>
                                                    <input type="number" name="practical_hours" class="form-control" min="0">
                                                </div>
                                            </div>
                                        </div>
                                        <button type="submit" name="create_subject" class="btn btn-primary">Create Subject</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">
                                    <h5>All Subjects</h5>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table table-sm">
                                            <thead>
                                                <tr>
                                                    <th>Code</th>
                                                    <th>Name</th>
                                                    <th>Sem</th>
                                                    <th>Credits</th>
                                                    <th>L-T-P</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($subjects as $subject): ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($subject['subject_code']) ?></td>
                                                    <td><?= htmlspecialchars($subject['subject_name']) ?></td>
                                                    <td><?= htmlspecialchars($subject['subject_semester']) ?></td>
                                                    <td><?= htmlspecialchars($subject['credit']) ?></td>
                                                    <td><?= $subject['lecture_hours'] . '-' . $subject['tutorial_hours'] . '-' . $subject['practical_hours'] ?></td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Registrations Section -->
                <div id="registrations" class="section" style="display: none;">
                    <div class="card">
                        <div class="card-header">
                            <h5>Student Registrations</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Student ID</th>
                                            <th>Student Name</th>
                                            <th>Semester</th>
                                            <th>Registration Date</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($registrations as $reg): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($reg['student_id']) ?></td>
                                            <td><?= htmlspecialchars($reg['first_name'] . ' ' . $reg['last_name']) ?></td>
                                            <td><?= htmlspecialchars($reg['semester']) ?></td>
                                            <td><?= htmlspecialchars($reg['sem_reg_date']) ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Verification Section -->
                <div id="verification" class="section" style="display: none;">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">
                                    <h5>Assign Staff for Verification</h5>
                                </div>
                                <div class="card-body">
                                    <form method="POST">
                                        <div class="mb-3">
                                            <label class="form-label">Student ID</label>
                                            <select name="student_id" class="form-select" required>
                                                <option value="">Select Student</option>
                                                <?php foreach ($students as $student): ?>
                                                <option value="<?= $student['student_id'] ?>"><?= $student['student_id'] ?> - <?= $student['first_name'] . ' ' . $student['last_name'] ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Assign to Staff</label>
                                            <select name="staff_id" class="form-select" required>
                                                <option value="">Select Staff</option>
                                                <?php foreach ($available_staff as $staff_member): ?>
                                                <option value="<?= $staff_member['staff_id'] ?>"><?= $staff_member['staff_id'] ?> - <?= $staff_member['first_name'] . ' ' . $staff_member['last_name'] ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Verification Type</label>
                                            <select name="verification_type" class="form-select" required>
                                                <option value="Document">Document</option>
                                                <option value="Attendance">Attendance</option>
                                                <option value="Exam">Exam</option>
                                                <option value="Other">Other</option>
                                            </select>
                                        </div>
                                        <button type="submit" name="assign_verification" class="btn btn-primary">Assign Verification</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">
                                    <h5>Verification Assignments</h5>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table table-sm">
                                            <thead>
                                                <tr>
                                                    <th>Student</th>
                                                    <th>Staff</th>
                                                    <th>Type</th>
                                                    <th>Status</th>
                                                    <th>Date</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($verifications as $verification): ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($verification['student_name'] . ' ' . $verification['student_lastname']) ?></td>
                                                    <td><?= htmlspecialchars($verification['staff_name'] . ' ' . $verification['staff_lastname']) ?></td>
                                                    <td><?= htmlspecialchars($verification['verification_type']) ?></td>
                                                    <td><span class="badge-status status-<?= strtolower($verification['verification_status']) ?>"><?= htmlspecialchars($verification['verification_status']) ?></span></td>
                                                    <td><?= htmlspecialchars($verification['assignment_date']) ?></td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function showSection(sectionId) {
            // Hide all sections
            const sections = document.querySelectorAll('.section');
            sections.forEach(section => {
                section.style.display = 'none';
            });
            
            // Show selected section
            document.getElementById(sectionId).style.display = 'block';
            
            // Update active nav link
            const navLinks = document.querySelectorAll('.nav-link');
            navLinks.forEach(link => {
                link.classList.remove('active');
            });
            event.target.classList.add('active');
        }
    </script>
</body>
</html>