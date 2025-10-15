<?php
// hod_assign_staff.php - HOD assigns employee for verification tasks
session_start();
require_once 'db_config.php';

// Check if user is logged in and is HOD
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'hod') {
    header('Location: login.php');
    exit();
}

$error = '';
$success = '';
$hod_id = $_SESSION['user_id'];

// Get HOD info
$hod_info = $_SESSION['hod_info'] ?? null;
if (!$hod_info) {
    $stmt = $pdo->prepare("SELECT * FROM hod WHERE hod_id = ?");
    $stmt->execute([$hod_id]);
    $hod_info = $stmt->fetch();
    $_SESSION['hod_info'] = $hod_info;
}

// Handle employee assignment
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['assign_employee'])) {
        $employee_id = $_POST['employee_id'];
        $assignment_type = $_POST['assignment_type'];
        $department_id = $hod_info['department_id'] ?? '';
        
        try {
            // Check if assignment already exists
            $stmt = $pdo->prepare("
                SELECT * FROM staff_assignments 
                WHERE staff_id = ? AND assignment_type = ? AND is_active = 1
            ");
            $stmt->execute([$employee_id, $assignment_type]);
            
            if ($stmt->fetch()) {
                $error = "This employee is already assigned to this task.";
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO staff_assignments (staff_id, assigned_by, department_id, assignment_type)
                    VALUES (?, ?, ?, ?)
                ");
                $stmt->execute([$employee_id, $hod_id, $department_id, $assignment_type]);
                $success = "Employee assigned successfully!";
            }
        } catch (Exception $e) {
            $error = "Error assigning employee: " . $e->getMessage();
        }
    }
    
    if (isset($_POST['remove_assignment'])) {
        $assignment_id = $_POST['assignment_id'];
        
        try {
            $stmt = $pdo->prepare("
                UPDATE staff_assignments 
                SET is_active = 0 
                WHERE assignment_id = ? AND assigned_by = ?
            ");
            $stmt->execute([$assignment_id, $hod_id]);
            $success = "Assignment removed successfully!";
        } catch (Exception $e) {
            $error = "Error removing assignment: " . $e->getMessage();
        }
    }
}

// Get available employees
$available_employees = [];
try {
    $stmt = $pdo->prepare("
        SELECT uc.user_id, ei.first_name, ei.last_name, ei.email
        FROM user_credentials uc
        JOIN employee_info ei ON uc.user_id = ei.emp_id
        WHERE uc.role = 'employee' AND ei.emp_type = 'Employee'
        ORDER BY ei.first_name, ei.last_name
    ");
    $stmt->execute();
    $available_employees = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("Error fetching employees: " . $e->getMessage());
}

// Get current assignments
$current_assignments = [];
try {
    $stmt = $pdo->prepare("
        SELECT sa.*, ei.first_name, ei.last_name, ei.email
        FROM staff_assignments sa
        JOIN employee_info ei ON sa.staff_id = ei.emp_id
        WHERE sa.assigned_by = ? AND sa.is_active = 1
        ORDER BY sa.assigned_date DESC
    ");
    $stmt->execute([$hod_id]);
    $current_assignments = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("Error fetching assignments: " . $e->getMessage());
}

// Get verification statistics
$verification_stats = [];
try {
    $stmt = $pdo->prepare("
        SELECT 
            sa.staff_id,
            ei.first_name,
            ei.last_name,
            sa.assignment_type,
            COUNT(v.id) as total_verifications,
            COUNT(CASE WHEN DATE(v.created_at) = CURDATE() THEN 1 END) as today_verifications
        FROM staff_assignments sa
        JOIN employee_info ei ON sa.staff_id = ei.emp_id
        LEFT JOIN verification v ON sa.staff_id = v.verified_by
        WHERE sa.assigned_by = ? AND sa.is_active = 1
        GROUP BY sa.staff_id, sa.assignment_type
        ORDER BY total_verifications DESC
    ");
    $stmt->execute([$hod_id]);
    $verification_stats = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("Error fetching verification stats: " . $e->getMessage());
}

// Fetch available semesters
$semesters = [];
try {
    $stmt = $pdo->query("SELECT DISTINCT semester FROM semester_registration ORDER BY semester");
    $semesters = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    error_log("Error fetching semesters: " . $e->getMessage());
}

// Fetch all employees for assignment
$all_employees = $pdo->query("SELECT staff_id, first_name, last_name, email FROM staff_info WHERE staff_type = 'Employee' ORDER BY first_name, last_name")->fetchAll();

// Handle AJAX request for students by semester
if (isset($_GET['fetch_students']) && isset($_GET['semester'])) {
    $semester = $_GET['semester'];
    $students = [];
    $stmt = $pdo->prepare("SELECT sr.student_id, si.first_name, si.last_name, si.email FROM semester_registration sr JOIN student_info si ON sr.student_id = si.student_id WHERE sr.semester = ?");
    $stmt->execute([$semester]);
    $students = $stmt->fetchAll();
    header('Content-Type: application/json');
    echo json_encode($students);
    exit();
}

// Handle new assignment POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_employees'])) {
    $semester = $_POST['semester'] ?? '';
    $student_ids = $_POST['student_ids'] ?? [];
    $assignment_type = $_POST['assignment_type'] ?? '';
    $employee_ids = $_POST['employee_ids'] ?? [];
    $department_id = $hod_info['department_id'] ?? '';
    if (!$semester || empty($student_ids) || !$assignment_type || empty($employee_ids)) {
        $error = "Please select all required fields.";
    } else {
        try {
            foreach ($student_ids as $student_id) {
                foreach ($employee_ids as $employee_id) {
                    $stmt = $pdo->prepare("INSERT INTO staff_assignments (staff_id, assigned_by, department_id, assignment_type, student_id, semester) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$employee_id, $hod_id, $department_id, $assignment_type, $student_id, $semester]);
                }
            }
            $success = "Employees assigned successfully!";
        } catch (Exception $e) {
            $error = "Error assigning employees: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assign Employee - HOD Portal</title>
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
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <nav class="col-md-3 col-lg-2 sidebar">
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
                    <a class="nav-link active" href="hod_assign_staff.php">
                        <i class="fas fa-user-plus"></i> Assign Employee
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
                <div class="row mb-4">
                    <div class="col">
                        <h2>Employee Assignment Management</h2>
                        <p class="text-muted">Assign employees to verification tasks</p>
                    </div>
                </div>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error) ?></div>
                <?php endif; ?>
                <?php if ($success): ?>
                    <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?></div>
                <?php endif; ?>
                
                <!-- Assign New Employee -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title">Assign Employees to Students</h5>
                    </div>
                    <div class="card-body">
                        <form method="post" id="assignForm">
                            <input type="hidden" name="assign_employees" value="1">
                            <div class="row mb-3">
                                <div class="col-md-4">
                                    <label for="semester" class="form-label">Select Semester</label>
                                    <select name="semester" id="semester" class="form-select" required>
                                        <option value="">Choose Semester</option>
                                        <?php foreach ($semesters as $sem): ?>
                                            <option value="<?= htmlspecialchars($sem) ?>">Semester <?= htmlspecialchars($sem) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label for="assignment_type" class="form-label">Verification Type</label>
                                    <select name="assignment_type" id="assignment_type" class="form-select" required>
                                        <option value="">Choose Verification Type</option>
                                        <option value="registration_verification">Registration Verification</option>
                                        <option value="marks_verification">Marks Verification</option>
                                        <option value="general">General Tasks</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Assign Employee(s)</label>
                                    <div class="border rounded p-2" style="background:#f8f9fa; max-height:200px; overflow-y:auto;">
                                        <?php if (count($all_employees) > 0): ?>
                                            <?php foreach ($all_employees as $emp): ?>
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" name="assign_employees[]" id="emp_<?= htmlspecialchars($emp['staff_id']) ?>" value="<?= htmlspecialchars($emp['staff_id']) ?>">
                                                    <label class="form-check-label" for="emp_<?= htmlspecialchars($emp['staff_id']) ?>">
                                                        <?= htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']) ?> (<?= htmlspecialchars($emp['email']) ?>)
                                                    </label>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <span class="text-muted">No employees available for assignment.</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Select Students</label>
                                <div id="studentsList" class="border rounded p-3 bg-light" style="max-height: 250px; overflow-y: auto;">
                                    <span class="text-muted">Select a semester to load students.</span>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-user-plus"></i> Assign Employees
                            </button>
                        </form>
                    </div>
                </div>
                
                <!-- Current Assignments -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title">Current Employee Assignments</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($current_assignments)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-users fa-3x text-muted mb-3"></i>
                                <p class="text-muted fs-5">No employee assignments yet. Assign employees to verification tasks.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Employee</th>
                                            <th>Email</th>
                                            <th>Assignment Type</th>
                                            <th>Assigned Date</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($current_assignments as $assignment): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($assignment['first_name'] . ' ' . $assignment['last_name']) ?></td>
                                                <td><?= htmlspecialchars($assignment['email']) ?></td>
                                                <td>
                                                    <span class="badge bg-primary">
                                                        <?= ucwords(str_replace('_', ' ', $assignment['assignment_type'])) ?>
                                                    </span>
                                                </td>
                                                <td><?= date('M d, Y', strtotime($assignment['assigned_date'])) ?></td>
                                                <td>
                                                    <form method="post" class="d-inline" onsubmit="return confirm('Remove this assignment?')">
                                                        <input type="hidden" name="remove_assignment" value="1">
                                                        <input type="hidden" name="assignment_id" value="<?= $assignment['assignment_id'] ?>">
                                                        <button type="submit" class="btn btn-sm btn-outline-danger">
                                                            <i class="fas fa-trash"></i> Remove
                                                        </button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Verification Statistics -->
                <?php if (!empty($verification_stats)): ?>
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title">Employee Performance Statistics</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Employee</th>
                                        <th>Assignment Type</th>
                                        <th>Total Verifications</th>
                                        <th>Today's Verifications</th>
                                        <th>Performance</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($verification_stats as $stat): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($stat['first_name'] . ' ' . $stat['last_name']) ?></td>
                                            <td>
                                                <span class="badge bg-info">
                                                    <?= ucwords(str_replace('_', ' ', $stat['assignment_type'])) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge bg-success"><?= $stat['total_verifications'] ?></span>
                                            </td>
                                            <td>
                                                <span class="badge bg-primary"><?= $stat['today_verifications'] ?></span>
                                            </td>
                                            <td>
                                                <?php
                                                $performance = $stat['total_verifications'];
                                                if ($performance >= 50) {
                                                    echo '<span class="badge bg-success">Excellent</span>';
                                                } elseif ($performance >= 20) {
                                                    echo '<span class="badge bg-warning">Good</span>';
                                                } elseif ($performance >= 5) {
                                                    echo '<span class="badge bg-info">Average</span>';
                                                } else {
                                                    echo '<span class="badge bg-secondary">New</span>';
                                                }
                                                ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </main>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
    $(function() {
        $('#semester').change(function() {
            var semester = $(this).val();
            if (!semester) {
                $('#studentsList').html('<span class="text-muted">Select a semester to load students.</span>');
                return;
            }
            $('#studentsList').html('<span class="text-muted">Loading students...</span>');
            $.get('?fetch_students=1&semester=' + encodeURIComponent(semester), function(data) {
                if (data.length === 0) {
                    $('#studentsList').html('<span class="text-danger">No students registered for this semester.</span>');
                    return;
                }
                var html = '<div class="form-check mb-2">'
                    + '<input class="form-check-input" type="checkbox" id="selectAllStudents">'
                    + '<label class="form-check-label fw-bold" for="selectAllStudents">Select All</label>'
                    + '</div>';
                data.forEach(function(student) {
                    html += '<div class="form-check mb-1">'
                        + '<input class="form-check-input student-checkbox" type="checkbox" name="student_ids[]" value="' + student.student_id + '" id="student_' + student.student_id + '">' 
                        + '<label class="form-check-label" for="student_' + student.student_id + '">'
                        + student.first_name + ' ' + student.last_name + ' (' + student.email + ')'
                        + '</label></div>';
                });
                $('#studentsList').html(html);
                $('#selectAllStudents').on('change', function() {
                    $('.student-checkbox').prop('checked', this.checked);
                });
            });
        });
    });
    </script>
</body>
</html>