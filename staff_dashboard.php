<?php
session_start();
require_once 'db_config.php';

// Check if user is logged in and is employee
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'employee') {
    header('Location: login.php');
    exit();
}

$employee_id = $_SESSION['user_id'];
$error = '';
$success = '';

// Get employee information
$stmt = $pdo->prepare("SELECT * FROM staff_info WHERE staff_id = ?");
$stmt->execute([$employee_id]);
$employee = $stmt->fetch();

// Handle verification updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_verification'])) {
    $verification_id = $_POST['verification_id'];
    $status = $_POST['verification_status'];
    $remarks = $_POST['remarks'];
    
    try {
        $stmt = $pdo->prepare("UPDATE verification_assignments SET verification_status = ?, verification_date = CURDATE(), remarks = ? WHERE id = ? AND assigned_staff_id = ?");
        $stmt->execute([$status, $remarks, $verification_id, $employee_id]);
        $success = "Verification updated successfully!";
    } catch (PDOException $e) {
        $error = "Error updating verification: " . $e->getMessage();
    }
}

// Get verification assignments for this employee
$verifications = $pdo->prepare("
    SELECT va.*, si.first_name, si.last_name, si.student_id, si.email, si.mobile_no
    FROM verification_assignments va 
    JOIN student_info si ON va.student_id = si.student_id 
    WHERE va.assigned_staff_id = ? 
    ORDER BY va.assignment_date DESC
");
$verifications->execute([$employee_id]);
$assigned_verifications = $verifications->fetchAll();

// Get all students for reference
$students = $pdo->query("SELECT * FROM student_info ORDER BY student_id")->fetchAll();

// Get all employees for reference
$all_employees = $pdo->query("SELECT * FROM staff_info ORDER BY staff_id")->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Dashboard</title>
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
        .status-pending { background-color: #ffeeba; color: #856404; }
        .status-verified { background-color: #d4edda; color: #155724; }
        .status-rejected { background-color: #f8d7da; color: #721c24; }
        .status-ongoing { background-color: #d4edda; color: #155724; }
        .status-graduated { background-color: #cce5ff; color: #004085; }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <nav class="col-md-3 col-lg-2 sidebar">
                <h3>Employee Portal</h3>
                <div class="nav flex-column">
                    <a class="nav-link active" href="staff_dashboard.php">
                        <i class="fas fa-home"></i> Dashboard
                    </a>
                    <a class="nav-link" href="staff_verification.php">
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
                        <h2>Welcome, <?= htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']) ?></h2>
                        <p class="text-muted">Employee ID: <?= htmlspecialchars($employee['staff_id']) ?></p>
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
                                    <h3 class="text-primary"><?= count($assigned_verifications) ?></h3>
                                    <p>Assigned Verifications</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card text-center">
                                <div class="card-body">
                                    <h3 class="text-success"><?= count(array_filter($assigned_verifications, function($v) { return $v['verification_status'] == 'Verified'; })) ?></h3>
                                    <p>Completed</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card text-center">
                                <div class="card-body">
                                    <h3 class="text-warning"><?= count(array_filter($assigned_verifications, function($v) { return $v['verification_status'] == 'Pending'; })) ?></h3>
                                    <p>Pending</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card text-center">
                                <div class="card-body">
                                    <h3 class="text-info"><?= count($students) ?></h3>
                                    <p>Total Students</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header">
                            <h5>Employee Information</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <p><strong>Name:</strong> <?= htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']) ?></p>
                                    <p><strong>Employee ID:</strong> <?= htmlspecialchars($employee['staff_id']) ?></p>
                                    <p><strong>Department:</strong> <?= htmlspecialchars($employee['department_id']) ?></p>
                                </div>
                                <div class="col-md-6">
                                    <p><strong>Designation:</strong> <?= htmlspecialchars($employee['designation']) ?></p>
                                    <p><strong>Email:</strong> <?= htmlspecialchars($employee['email']) ?></p>
                                    <p><strong>Mobile:</strong> <?= htmlspecialchars($employee['mobile_no']) ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Verifications Section -->
                <div id="verifications" class="section" style="display: none;">
                    <div class="card">
                        <div class="card-header">
                            <h5>My Verification Assignments</h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($assigned_verifications)): ?>
                                <div class="text-center py-4">
                                    <i class="fas fa-clipboard-check fa-3x text-muted mb-3"></i>
                                    <p class="text-muted">No verification assignments yet.</p>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th>Student ID</th>
                                                <th>Student Name</th>
                                                <th>Email</th>
                                                <th>Mobile</th>
                                                <th>Type</th>
                                                <th>Status</th>
                                                <th>Assigned Date</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($assigned_verifications as $verification): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($verification['student_id']) ?></td>
                                                <td><?= htmlspecialchars($verification['first_name'] . ' ' . $verification['last_name']) ?></td>
                                                <td><?= htmlspecialchars($verification['email']) ?></td>
                                                <td><?= htmlspecialchars($verification['mobile_no']) ?></td>
                                                <td><?= htmlspecialchars($verification['verification_type']) ?></td>
                                                <td><span class="badge-status status-<?= strtolower($verification['verification_status']) ?>"><?= htmlspecialchars($verification['verification_status']) ?></span></td>
                                                <td><?= htmlspecialchars($verification['assignment_date']) ?></td>
                                                <td>
                                                    <?php if ($verification['verification_status'] == 'Pending'): ?>
                                                    <button class="btn btn-sm btn-primary" onclick="openVerificationModal(<?= $verification['id'] ?>, '<?= $verification['student_id'] ?>', '<?= htmlspecialchars($verification['first_name'] . ' ' . $verification['last_name']) ?>')">
                                                        Update
                                                    </button>
                                                    <?php else: ?>
                                                    <small class="text-muted">Completed</small>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
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
                                            <th>Mobile</th>
                                            <th>Status</th>
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
                                            <td><?= htmlspecialchars($student['mobile_no']) ?></td>
                                            <td><span class="badge-status status-<?= strtolower($student['status']) ?>"><?= htmlspecialchars($student['status']) ?></span></td>
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
                            <h5>All Employees</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Employee ID</th>
                                            <th>Name</th>
                                            <th>Type</th>
                                            <th>Department</th>
                                            <th>Email</th>
                                            <th>Designation</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($all_employees as $s): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($s['staff_id']) ?></td>
                                            <td><?= htmlspecialchars($s['first_name'] . ' ' . $s['last_name']) ?></td>
                                            <td><?= htmlspecialchars($s['staff_type']) ?></td>
                                            <td><?= htmlspecialchars($s['department_id']) ?></td>
                                            <td><?= htmlspecialchars($s['email']) ?></td>
                                            <td><?= htmlspecialchars($s['designation']) ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Verification Modal -->
    <div class="modal fade" id="verificationModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Update Verification</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="verification_id" id="verification_id">
                        <div class="mb-3">
                            <label class="form-label">Student</label>
                            <input type="text" class="form-control" id="student_info" readonly>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Verification Status</label>
                            <select name="verification_status" class="form-select" required>
                                <option value="Pending">Pending</option>
                                <option value="Verified">Verified</option>
                                <option value="Rejected">Rejected</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Remarks</label>
                            <textarea name="remarks" class="form-control" rows="3" placeholder="Add any remarks or notes..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="update_verification" class="btn btn-primary">Update Verification</button>
                    </div>
                </form>
            </div>
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

        function openVerificationModal(verificationId, studentId, studentName) {
            document.getElementById('verification_id').value = verificationId;
            document.getElementById('student_info').value = studentId + ' - ' + studentName;
            
            const modal = new bootstrap.Modal(document.getElementById('verificationModal'));
            modal.show();
        }
    </script>
</body>
</html>